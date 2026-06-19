<?php
/**
 * Discovers and parses XML sitemaps.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fetches and parses website sitemaps into a flat URL list.
 */
final class FP_Copilot_Sitemap_Fetcher {

	/**
	 * Maximum URLs to collect.
	 */
	private int $max_urls;

	/**
	 * Maximum sitemap index recursion depth.
	 */
	private int $max_depth;

	/**
	 * HTTP request timeout in seconds.
	 */
	private int $timeout;

	/**
	 * Sitemap URLs already fetched (prevents loops).
	 *
	 * @var array<string, true>
	 */
	private array $visited_sitemaps = array();

	/**
	 * @param array<string, mixed> $args Fetch arguments.
	 */
	public function __construct( array $args = array() ) {
		$this->max_urls  = max( 1, (int) ( $args['max_urls'] ?? 5000 ) );
		$this->max_depth = max( 1, (int) ( $args['max_depth'] ?? 5 ) );
		$this->timeout   = max( 1, (int) ( $args['timeout'] ?? 15 ) );
	}

	/**
	 * Fetch all URLs listed in a website's sitemap.
	 *
	 * @param string $website_url Any URL belonging to the target site.
	 * @return string[]|WP_Error
	 */
	public function fetch( string $website_url ) {
		$origin = $this->site_origin( $website_url );

		if ( is_wp_error( $origin ) ) {
			return $origin;
		}

		$sitemap_urls = $this->discover_sitemaps( $origin );

		if ( empty( $sitemap_urls ) ) {
			return new WP_Error(
				'fp_copilot_sitemap_not_found',
				__( 'No sitemap could be found for this website.', 'fp-copilot' ),
				array( 'origin' => $origin )
			);
		}

		$page_urls = array();

		foreach ( $sitemap_urls as $sitemap_url ) {
			$result = $this->parse_sitemap( $sitemap_url, 0 );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$page_urls = array_merge( $page_urls, $result );

			if ( count( $page_urls ) >= $this->max_urls ) {
				break;
			}
		}

		$page_urls = array_values( array_unique( $page_urls ) );

		if ( count( $page_urls ) > $this->max_urls ) {
			$page_urls = array_slice( $page_urls, 0, $this->max_urls );
		}

		/**
		 * Filter the collected sitemap URLs before they are returned.
		 *
		 * @param string[] $page_urls   Collected page URLs.
		 * @param string   $website_url Original website URL argument.
		 * @param string   $origin      Normalized site origin used for discovery.
		 */
		return apply_filters( 'fp_copilot_sitemap_urls', $page_urls, $website_url, $origin );
	}

	/**
	 * Normalize a URL to the site origin (scheme + host + port).
	 *
	 * @param string $url Website or page URL.
	 * @return string|WP_Error
	 */
	private function site_origin( string $url ) {
		$url = trim( $url );

		if ( '' === $url ) {
			return new WP_Error(
				'fp_copilot_sitemap_invalid_url',
				__( 'A website URL is required.', 'fp-copilot' )
			);
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . ltrim( $url, '/' );
		}

		$parts = wp_parse_url( $url );

		if ( empty( $parts['host'] ) ) {
			return new WP_Error(
				'fp_copilot_sitemap_invalid_url',
				__( 'The website URL is not valid.', 'fp-copilot' )
			);
		}

		$scheme = strtolower( $parts['scheme'] ?? 'https' );

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new WP_Error(
				'fp_copilot_sitemap_invalid_scheme',
				__( 'Only HTTP and HTTPS URLs are supported.', 'fp-copilot' )
			);
		}

		$origin = $scheme . '://' . $parts['host'];

		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . $parts['port'];
		}

		return untrailingslashit( $origin );
	}

	/**
	 * Discover sitemap URLs via robots.txt and common paths.
	 *
	 * @param string $origin Site origin.
	 * @return string[]
	 */
	private function discover_sitemaps( string $origin ): array {
		$candidates = array();

		$robots = $this->request_body( $origin . '/robots.txt' );

		if ( is_string( $robots ) ) {
			if ( preg_match_all( '/^\s*Sitemap:\s*(\S+)/im', $robots, $matches ) ) {
				foreach ( $matches[1] as $sitemap_url ) {
					$candidates[] = esc_url_raw( trim( $sitemap_url ) );
				}
			}
		}

		$candidates = array_merge(
			$candidates,
			array(
				$origin . '/sitemap.xml',
				$origin . '/sitemap_index.xml',
				$origin . '/sitemap-index.xml',
				$origin . '/wp-sitemap.xml',
				$origin . '/wp-sitemap.xml/',
			)
		);

		/**
		 * Filter sitemap discovery candidate URLs.
		 *
		 * @param string[] $candidates Sitemap URLs to try, in order.
		 * @param string   $origin     Site origin.
		 */
		$candidates = apply_filters( 'fp_copilot_sitemap_discovery_urls', $candidates, $origin );

		$found = array();

		foreach ( array_unique( array_filter( $candidates ) ) as $candidate ) {
			if ( $this->sitemap_exists( $candidate ) ) {
				$found[] = $candidate;
			}
		}

		return array_values( array_unique( $found ) );
	}

	/**
	 * Whether a URL responds as a usable XML sitemap.
	 */
	private function sitemap_exists( string $url ): bool {
		$response = $this->request( $url );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );

		return is_string( $body ) && $this->looks_like_sitemap( $body );
	}

	/**
	 * Parse a sitemap document, recursing into sitemap indexes.
	 *
	 * @param string $sitemap_url Sitemap URL.
	 * @param int    $depth       Current recursion depth.
	 * @return string[]|WP_Error
	 */
	private function parse_sitemap( string $sitemap_url, int $depth ) {
		if ( isset( $this->visited_sitemaps[ $sitemap_url ] ) ) {
			return array();
		}

		if ( $depth > $this->max_depth ) {
			return new WP_Error(
				'fp_copilot_sitemap_max_depth',
				__( 'Sitemap index nesting exceeds the maximum allowed depth.', 'fp-copilot' )
			);
		}

		$this->visited_sitemaps[ $sitemap_url ] = true;

		$body = $this->request_body( $sitemap_url );

		if ( ! is_string( $body ) ) {
			return new WP_Error(
				'fp_copilot_sitemap_fetch_failed',
				__( 'Unable to fetch the sitemap.', 'fp-copilot' ),
				array( 'sitemap' => $sitemap_url )
			);
		}

		$document = $this->load_xml( $body );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		$root = $document->documentElement;

		if ( ! $root ) {
			return new WP_Error(
				'fp_copilot_sitemap_invalid',
				__( 'The sitemap XML is empty.', 'fp-copilot' )
			);
		}

		$locations = $this->extract_locations( $root );

		if ( $this->root_is_index( $root ) ) {
			$urls = array();

			foreach ( $locations as $child_sitemap ) {
				$child_urls = $this->parse_sitemap( $child_sitemap, $depth + 1 );

				if ( is_wp_error( $child_urls ) ) {
					return $child_urls;
				}

				$urls = array_merge( $urls, $child_urls );

				if ( count( $urls ) >= $this->max_urls ) {
					return array_slice( array_values( array_unique( $urls ) ), 0, $this->max_urls );
				}
			}

			return $urls;
		}

		return $locations;
	}

	/**
	 * Whether the XML root element is a sitemap index.
	 */
	private function root_is_index( DOMElement $root ): bool {
		return 'sitemapindex' === strtolower( $root->localName ?: $root->tagName );
	}

	/**
	 * Extract all <loc> values from a sitemap document.
	 *
	 * @return string[]
	 */
	private function extract_locations( DOMElement $root ): array {
		$locations = array();

		foreach ( $root->getElementsByTagName( 'loc' ) as $node ) {
			$url = trim( $node->textContent );

			if ( '' !== $url ) {
				$locations[] = esc_url_raw( $url );
			}
		}

		return $locations;
	}

	/**
	 * Load sitemap XML safely.
	 *
	 * @param string $body Response body.
	 * @return DOMDocument|WP_Error
	 */
	private function load_xml( string $body ) {
		if ( ! $this->looks_like_sitemap( $body ) ) {
			return new WP_Error(
				'fp_copilot_sitemap_not_xml',
				__( 'The response is not a valid XML sitemap.', 'fp-copilot' )
			);
		}

		$previous = libxml_use_internal_errors( true );

		$document = new DOMDocument();
		$loaded   = $document->loadXML( $body, LIBXML_NONET | LIBXML_COMPACT );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return new WP_Error(
				'fp_copilot_sitemap_parse_error',
				__( 'Unable to parse the sitemap XML.', 'fp-copilot' )
			);
		}

		return $document;
	}

	/**
	 * Quick check that the body resembles a sitemap document.
	 */
	private function looks_like_sitemap( string $body ): bool {
		return (bool) preg_match( '/<\s*(urlset|sitemapindex)\b/i', $body );
	}

	/**
	 * Perform an HTTP GET request.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function request( string $url ) {
		/**
		 * Filter whether a sitemap URL may be fetched.
		 *
		 * Return null to use WordPress URL validation, true to allow, false to block.
		 *
		 * @param bool|null $allowed Whether the URL is allowed.
		 * @param string    $url     Request URL.
		 */
		$allowed = apply_filters( 'fp_copilot_sitemap_allow_url', null, $url );

		if ( null === $allowed ) {
			$allowed = ! function_exists( 'wp_http_validate_url' ) || wp_http_validate_url( $url );
		}

		if ( ! $allowed ) {
			return new WP_Error(
				'fp_copilot_sitemap_unsafe_url',
				__( 'A sitemap URL was blocked for safety.', 'fp-copilot' )
			);
		}

		return wp_remote_get(
			$url,
			array(
				'timeout'     => $this->timeout,
				'redirection' => 3,
				'user-agent'  => 'FirmPilot-Copilot/' . FP_COPILOT_VERSION,
				'headers'     => array(
					'Accept' => 'application/xml,text/xml;q=0.9,*/*;q=0.8',
				),
			)
		);
	}

	/**
	 * Fetch a URL and return its body on success.
	 *
	 * @return string|null
	 */
	private function request_body( string $url ): ?string {
		$response = $this->request( $url );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );

		return is_string( $body ) ? $body : null;
	}
}
