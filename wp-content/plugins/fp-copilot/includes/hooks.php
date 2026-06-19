<?php
/**
 * Shared plugin hooks and public API functions.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

require_once FP_COPILOT_PLUGIN_DIR . 'includes/class-fp-copilot-sitemap-fetcher.php';

add_filter( 'fp_copilot_fetch_sitemap_urls', 'fp_copilot_default_fetch_sitemap_urls', 10, 3 );

/**
 * Fetch all page URLs from a website's sitemap.
 *
 * Discovers the sitemap from robots.txt or common locations, then parses
 * single-level sitemaps and multi-level sitemap indexes recursively.
 *
 * Modules and themes can short-circuit or extend this hook:
 *
 *     add_filter( 'fp_copilot_fetch_sitemap_urls', function ( $urls, $website_url, $args ) {
 *         if ( null !== $urls ) {
 *             return $urls;
 *         }
 *         // Custom logic, or return array|WP_Error to replace the default.
 *     }, 10, 3 );
 *
 * @param string               $website_url Site URL (homepage, section URL, or any page URL).
 * @param array<string, mixed> $args {
 *     Optional fetch arguments.
 *
 *     @type int $max_urls   Maximum URLs to collect. Default 5000.
 *     @type int $max_depth  Maximum sitemap index nesting depth. Default 5.
 *     @type int $timeout    HTTP timeout in seconds. Default 15.
 * }
 * @return string[]|WP_Error Flat list of URLs, or an error.
 */
function fp_copilot_fetch_sitemap_urls( string $website_url, array $args = array() ) {
	/**
	 * Fetch URLs from a website sitemap.
	 *
	 * Return null to use the default fetcher, an array of URL strings on success,
	 * or WP_Error on failure. Later callbacks may further modify a non-null return value.
	 *
	 * @param string[]|WP_Error|null $urls        URLs, error, or null for default handling.
	 * @param string                 $website_url Website URL passed to the fetch call.
	 * @param array<string, mixed>   $args        Fetch arguments.
	 */
	$result = apply_filters( 'fp_copilot_fetch_sitemap_urls', null, $website_url, $args );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	if ( ! is_array( $result ) ) {
		return new WP_Error(
			'fp_copilot_sitemap_empty',
			__( 'No sitemap URLs were returned.', 'fp-copilot' )
		);
	}

	return array_values( $result );
}

/**
 * Default handler for the fetch sitemap URLs hook.
 *
 * @param string[]|WP_Error|null $urls        Existing result, or null.
 * @param string                 $website_url Website URL.
 * @param array<string, mixed>   $args        Fetch arguments.
 * @return string[]|WP_Error
 */
function fp_copilot_default_fetch_sitemap_urls( $urls, string $website_url, array $args ) {
	if ( null !== $urls ) {
		return $urls;
	}

	$fetcher = new FP_Copilot_Sitemap_Fetcher( $args );

	return $fetcher->fetch( $website_url );
}

/**
 * Returns the site-specific FirmPilot Copilot API key.
 */
function fp_copilot_api_key(): string {
	return FP_Copilot_Api_Key::get();
}

/**
 * Validates an API key against this site's stored key.
 */
function fp_copilot_validate_api_key( string $key ): bool {
	return FP_Copilot_Api_Key::validate( $key );
}

/**
 * Returns the current site health snapshot.
 *
 * @return array<string, mixed>
 */
function fp_copilot_health_data(): array {
	return FP_Copilot_Health_Data::collect();
}

/**
 * Returns tracked form submission stats.
 *
 * @return array<string, mixed>
 */
function fp_copilot_form_submission_stats(): array {
	return FP_Copilot_Form_Submissions::get_stats();
}
