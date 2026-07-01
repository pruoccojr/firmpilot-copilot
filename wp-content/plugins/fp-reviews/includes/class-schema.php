<?php
/**
 * Reviews for FirmPilot – Schema.org JSON-LD for reviews collections and singles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FP_Reviews_Schema {

	const CONTEXT = 'https://schema.org';

	/**
	 * Whether JSON-LD output is enabled.
	 */
	public static function is_enabled() {
		return (bool) apply_filters( 'fp_reviews_enable_schema', true );
	}

	/**
	 * Default itemReviewed node (typically the site / organization).
	 *
	 * @return array
	 */
	public static function get_item_reviewed() {
		$default = array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
		);

		$home = home_url( '/' );
		if ( is_string( $home ) && $home !== '' ) {
			$default['url'] = $home;
		}

		$logo_id = (int) get_theme_mod( 'custom_logo', 0 );
		if ( $logo_id > 0 ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( is_string( $logo_url ) && $logo_url !== '' ) {
				$default['logo'] = $logo_url;
			}
		}

		$item = apply_filters( 'fp_reviews_schema_item_reviewed', $default );
		return is_array( $item ) ? $item : $default;
	}

	/**
	 * Plain-text review body for structured data.
	 *
	 * @param string $html Review content HTML.
	 * @return string
	 */
	public static function plain_review_body( $html ) {
		$text = wp_strip_all_tags( (string) $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', trim( $text ) );
		return is_string( $text ) ? $text : '';
	}

	/**
	 * Build one Schema.org Review node from a DB row object.
	 *
	 * @param object $row Review row from {@see FP_Reviews_DB}.
	 * @return array|null
	 */
	public static function build_review_node( $row ) {
		if ( ! is_object( $row ) ) {
			return null;
		}

		$name = isset( $row->display_name ) ? trim( (string) $row->display_name ) : '';
		if ( $name === '' && isset( $row->author_name ) ) {
			$name = trim( (string) $row->author_name );
		}
		if ( $name === '' ) {
			return null;
		}

		$body = self::plain_review_body( isset( $row->quote ) ? $row->quote : '' );
		if ( $body === '' ) {
			return null;
		}

		$node = array(
			'@type'         => 'Review',
			'author'        => array(
				'@type' => 'Person',
				'name'  => $name,
			),
			'reviewBody'    => $body,
			'itemReviewed'  => self::get_item_reviewed(),
		);

		$rating = isset( $row->rating ) ? (int) $row->rating : 0;
		if ( $rating > 0 ) {
			$node['reviewRating'] = array(
				'@type'       => 'Rating',
				'ratingValue' => $rating,
				'bestRating'  => 5,
				'worstRating' => 0,
			);
		}

		if ( ! empty( $row->date_published ) ) {
			$node['datePublished'] = (string) $row->date_published;
		}

		if ( ! empty( $row->id ) ) {
			$node['@id'] = home_url( '/#fp-review-' . (int) $row->id );
		}

		$job_title = isset( $row->job_title ) ? trim( (string) $row->job_title ) : '';
		$company   = isset( $row->company ) ? trim( (string) $row->company ) : '';
		if ( $job_title !== '' ) {
			$node['author']['jobTitle'] = $job_title;
		}
		if ( $company !== '' ) {
			$node['author']['worksFor'] = array(
				'@type' => 'Organization',
				'name'  => $company,
			);
		}

		return apply_filters( 'fp_reviews_schema_review_node', $node, $row );
	}

	/**
	 * Optional AggregateRating when the collection has rated reviews.
	 *
	 * @param array $items Review row objects.
	 * @return array|null
	 */
	public static function build_aggregate_rating( array $items ) {
		$values = array();
		foreach ( $items as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			$rating = isset( $row->rating ) ? (int) $row->rating : 0;
			if ( $rating > 0 ) {
				$values[] = $rating;
			}
		}

		$count = count( $values );
		if ( $count < 1 ) {
			return null;
		}

		return array(
			'@type'       => 'AggregateRating',
			'ratingValue' => round( array_sum( $values ) / $count, 1 ),
			'reviewCount' => $count,
			'bestRating'  => 5,
			'worstRating' => 0,
		);
	}

	/**
	 * Build @graph payload for a list of reviews.
	 *
	 * @param array $items Review row objects.
	 * @return array
	 */
	public static function build_graph( array $items ) {
		$reviews = array();
		$list    = array();

		foreach ( $items as $index => $row ) {
			$review = self::build_review_node( $row );
			if ( ! is_array( $review ) ) {
				continue;
			}
			$reviews[] = $review;
			$list[]    = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'item'     => isset( $review['@id'] ) ? $review['@id'] : $review,
			);
		}

		if ( empty( $reviews ) ) {
			return array();
		}

		$graph = array(
			array(
				'@type'           => 'ItemList',
				'name'            => __( 'Customer reviews', 'firmpilot-reviews' ),
				'numberOfItems'   => count( $reviews ),
				'itemListElement' => $list,
			),
		);

		$aggregate = self::build_aggregate_rating( $items );
		if ( is_array( $aggregate ) ) {
			$organization = self::get_item_reviewed();
			if ( ! isset( $organization['@type'] ) ) {
				$organization['@type'] = 'Organization';
			}
			$organization['aggregateRating'] = $aggregate;
			$graph[] = $organization;
		}

		$graph = array_merge( $graph, $reviews );

		return apply_filters( 'fp_reviews_schema_graph', $graph, $items );
	}

	/**
	 * Render a JSON-LD script tag for review data.
	 *
	 * @param array $items Review row objects.
	 * @return string
	 */
	public static function render_script( array $items ) {
		if ( ! self::is_enabled() || empty( $items ) ) {
			return '';
		}

		$graph = self::build_graph( $items );
		if ( empty( $graph ) ) {
			return '';
		}

		$payload = array(
			'@context' => self::CONTEXT,
			'@graph'   => $graph,
		);

		$payload = apply_filters( 'fp_reviews_schema_payload', $payload, $items );
		$json    = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		if ( ! is_string( $json ) || $json === '' ) {
			return '';
		}

		return '<script type="application/ld+json">' . $json . '</script>';
	}

	/**
	 * Output JSON-LD in wp_head for singular review views.
	 */
	public static function maybe_print_single_review_head() {
		if ( ! self::is_enabled() || ! is_singular( FP_Reviews_Post_Type::POST_TYPE ) ) {
			return;
		}

		$row = FP_Reviews_DB::get( (int) get_queried_object_id() );
		if ( ! $row ) {
			return;
		}

		echo self::render_script( array( $row ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
