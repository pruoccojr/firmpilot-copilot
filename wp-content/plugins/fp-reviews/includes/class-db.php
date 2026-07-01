<?php
/**
 * Reviews for FirmPilot – data layer: CPT fp_review.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FP_Reviews_DB {

	const META_PREFIX = '_fp_review_';

	private static function set_review_category_terms( $post_id, $slugs ) {
		$taxonomy = FP_Reviews_Post_Type::TAXONOMY;
		$slugs = array_filter( array_map( 'trim', explode( ',', (string) $slugs ) ) );
		$slugs = array_map( 'sanitize_title', $slugs );
		$term_ids = array();
		foreach ( $slugs as $slug ) {
			if ( $slug === '' ) {
				continue;
			}
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( ! $term || is_wp_error( $term ) ) {
				$term = wp_insert_term( $slug, $taxonomy, array( 'slug' => $slug ) );
				if ( ! is_wp_error( $term ) ) {
					$term_ids[] = (int) $term['term_id'];
				}
			} else {
				$term_ids[] = (int) $term->term_id;
			}
		}
		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, $taxonomy );
		}
	}

	private static function post_to_row( WP_Post $post ) {
		$id = (int) $post->ID;
		$terms = get_the_terms( $id, FP_Reviews_Post_Type::TAXONOMY );
		$category_slug = '';
		if ( $terms && ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$first = reset( $terms );
			$category_slug = $first ? (string) $first->slug : '';
		}
		$display_name = get_post_meta( $id, self::META_PREFIX . 'name', true );
		if ( $display_name === '' || $display_name === null ) {
			$display_name = $post->post_title;
		}
		return (object) array(
			'id' => $id,
			'quote' => $post->post_content,
			'author_name' => $post->post_title,
			'display_name' => (string) $display_name,
			'location' => get_post_meta( $id, self::META_PREFIX . 'location', true ),
			'company' => get_post_meta( $id, self::META_PREFIX . 'company', true ),
			'job_title' => get_post_meta( $id, self::META_PREFIX . 'job_title', true ),
			'rating' => (int) get_post_meta( $id, self::META_PREFIX . 'rating', true ),
			'image_id' => (int) get_post_thumbnail_id( $id ),
			'category_slug' => $category_slug,
			'sort_order' => (int) get_post_meta( $id, self::META_PREFIX . 'sort_order', true ),
			'date_published' => get_post_time( 'c', true, $post ),
		);
	}

	public static function get_all( $category_slugs = array(), $orderby = 'created_at', $order = 'DESC', $limit = -1, $random = false ) {
		$args = array(
			'post_type' => FP_Reviews_Post_Type::POST_TYPE,
			'post_status' => 'publish',
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'order' => strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC',
		);
		if ( $random ) {
			$args['orderby'] = 'rand';
		} elseif ( $orderby === 'sort_order' ) {
			$args['meta_key'] = self::META_PREFIX . 'sort_order';
			$args['orderby'] = 'meta_value_num';
		} elseif ( $orderby === 'author_name' ) {
			$args['orderby'] = 'title';
		} elseif ( $orderby === 'id' ) {
			$args['orderby'] = 'ID';
		} elseif ( $orderby === 'created_at' ) {
			$args['orderby'] = 'date';
		} else {
			$args['meta_key'] = self::META_PREFIX . 'sort_order';
			$args['orderby'] = 'meta_value_num';
		}
		if ( ! empty( $category_slugs ) ) {
			$args['tax_query'] = array( array( 'taxonomy' => FP_Reviews_Post_Type::TAXONOMY, 'field' => 'slug', 'terms' => $category_slugs ) );
		}
		$query = new WP_Query( $args );
		$out = array();
		foreach ( $query->posts as $post ) {
			$out[] = self::post_to_row( $post );
		}
		return $out;
	}

	public static function get( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || $post->post_type !== FP_Reviews_Post_Type::POST_TYPE ) {
			return null;
		}
		return self::post_to_row( $post );
	}

	public static function insert( $data ) {
		$post_id = wp_insert_post( array(
			'post_type' => FP_Reviews_Post_Type::POST_TYPE,
			'post_title' => isset( $data['author_name'] ) ? sanitize_text_field( $data['author_name'] ) : '',
			'post_content' => isset( $data['quote'] ) ? wp_kses_post( $data['quote'] ) : '',
			'post_status' => 'publish',
		) );
		if ( is_wp_error( $post_id ) ) {
			return 0;
		}
		update_post_meta( $post_id, self::META_PREFIX . 'location', isset( $data['location'] ) ? sanitize_text_field( $data['location'] ) : '' );
		update_post_meta( $post_id, self::META_PREFIX . 'company', isset( $data['company'] ) ? sanitize_text_field( $data['company'] ) : '' );
		update_post_meta( $post_id, self::META_PREFIX . 'job_title', isset( $data['job_title'] ) ? sanitize_text_field( $data['job_title'] ) : '' );
		update_post_meta( $post_id, self::META_PREFIX . 'rating', isset( $data['rating'] ) ? max( 0, min( 5, (int) $data['rating'] ) ) : 0 );
		if ( isset( $data['image_id'] ) && $data['image_id'] > 0 ) {
			set_post_thumbnail( $post_id, (int) $data['image_id'] );
		}
		if ( array_key_exists( 'category_slug', $data ) ) {
			self::set_review_category_terms( $post_id, is_string( $data['category_slug'] ) ? $data['category_slug'] : '' );
		}
		update_post_meta( $post_id, self::META_PREFIX . 'sort_order', isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0 );
		if ( array_key_exists( 'name', $data ) ) {
			update_post_meta( $post_id, self::META_PREFIX . 'name', sanitize_text_field( $data['name'] ) );
		}
		return $post_id;
	}

	public static function update( $id, $data ) {
		$post = get_post( (int) $id );
		if ( ! $post || $post->post_type !== FP_Reviews_Post_Type::POST_TYPE ) {
			return false;
		}
		if ( array_key_exists( 'author_name', $data ) ) {
			wp_update_post( array( 'ID' => (int) $id, 'post_title' => sanitize_text_field( $data['author_name'] ) ) );
		}
		if ( array_key_exists( 'quote', $data ) ) {
			wp_update_post( array( 'ID' => (int) $id, 'post_content' => wp_kses_post( $data['quote'] ) ) );
		}
		if ( array_key_exists( 'category_slug', $data ) ) {
			self::set_review_category_terms( $id, is_string( $data['category_slug'] ) ? $data['category_slug'] : '' );
		}
		$allowed = array( 'location', 'company', 'job_title', 'rating', 'image_id', 'sort_order', 'name' );
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$val = $data[ $key ];
			if ( $key === 'image_id' ) {
				if ( (int) $val > 0 ) {
					set_post_thumbnail( $id, (int) $val );
				} else {
					delete_post_thumbnail( $id );
				}
			} elseif ( $key === 'rating' ) {
				update_post_meta( $id, self::META_PREFIX . $key, max( 0, min( 5, (int) $val ) ) );
			} elseif ( $key === 'sort_order' ) {
				update_post_meta( $id, self::META_PREFIX . $key, (int) $val );
			} elseif ( $key === 'name' ) {
				update_post_meta( $id, self::META_PREFIX . 'name', sanitize_text_field( $val ) );
			} else {
				update_post_meta( $id, self::META_PREFIX . $key, sanitize_text_field( $val ) );
			}
		}
		return true;
	}

	public static function delete( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || $post->post_type !== FP_Reviews_Post_Type::POST_TYPE ) {
			return false;
		}
		return (bool) wp_delete_post( (int) $id, true );
	}
}

