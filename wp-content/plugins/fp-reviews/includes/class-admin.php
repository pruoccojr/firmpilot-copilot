<?php

/**
 * Reviews for FirmPilot – meta boxes and list columns for fp_review (list/editor are native).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FP_Reviews_Admin {

	const META_PREFIX = '_fp_review_';
	const META_SORT = '_fp_review_sort_order';

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . FP_Reviews_Post_Type::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_list_scripts' ) );
		add_filter( 'manage_' . FP_Reviews_Post_Type::POST_TYPE . '_posts_columns', array( $this, 'list_columns' ) );
		add_action( 'manage_' . FP_Reviews_Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'list_column_content' ), 10, 2 );
		add_filter( 'manage_edit-' . FP_Reviews_Post_Type::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'sortable_orderby' ) );
		fp_reviews_register_sortable_list_ajax_hooks( $this, 'fp_reviews_save_order' );
		fp_reviews_register_list_thumb_ajax_hooks( $this, 'fp_reviews_get_thumbnail', 'fp_reviews_remove_thumbnail' );
	}

	// Default list order: by sort order (custom order). Click column header to sort by Title/Date etc.
	public function sortable_orderby( WP_Query $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== FP_Reviews_Post_Type::POST_TYPE ) {
			return;
		}
		$orderby = $query->get( 'orderby' );
		if ( $orderby === 'fp_review_sort' ) {
			$query->set( 'meta_key', self::META_SORT );
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'order', $query->get( 'order' ) ?: 'ASC' );
		} elseif ( $orderby === '' || $orderby === 'menu_order' ) {
			$query->set( 'meta_key', self::META_SORT );
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'order', 'ASC' );
		}
	}

	public function enqueue_list_scripts( $hook ) {
		$screen          = get_current_screen();
		$post_type_get   = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		$is_reviews_list = ( $screen && 'edit' === $screen->base && FP_Reviews_Post_Type::POST_TYPE === $screen->post_type )
			|| ( 'edit.php' === $hook && FP_Reviews_Post_Type::POST_TYPE === $post_type_get );
		if ( ! $is_reviews_list ) {
			return;
		}
		wp_enqueue_style(
			'fp-reviews-admin',
			fp_reviews_asset_url( 'admin/assets/css/admin.css' ),
			array( 'wp-admin' ),
			FP_REVIEWS_VERSION
		);
		if ( wp_script_is( 'fp-admin-sortable', 'registered' ) ) {
			wp_enqueue_script( 'fp-admin-sortable' );
			wp_localize_script(
				'fp-admin-sortable',
				'fpSortableOpts',
				array(
					'action'         => 'fp_reviews_save_order',
					'nonce'          => wp_create_nonce( 'fp_reviews_save_order' ),
					'handleSelector' => '.fp-sortable__drag-handle',
				)
			);
		}
		wp_enqueue_media();
		wp_register_script(
			'fp-reviews-list-thumb',
			fp_reviews_asset_url( 'admin/pages/list/list-thumb.js' ),
			array( 'media-models' ),
			FP_REVIEWS_VERSION,
			true
		);
		wp_script_add_data( 'fp-reviews-list-thumb', 'strategy', 'defer' );
		wp_enqueue_script( 'fp-reviews-list-thumb' );
		wp_localize_script( 'fp-reviews-list-thumb', 'fpListThumb', array(
			'getThumbnailAction'   => 'fp_reviews_get_thumbnail',
			'removeThumbnailAction' => 'fp_reviews_remove_thumbnail',
			'buttonText'          => __( 'Use as thumbnail', 'firmpilot-reviews' ),
			'changeTitle'         => __( 'Change featured image', 'firmpilot-reviews' ),
		) );
	}

	public function ajax_save_order() {
		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fp_reviews_save_order' ) ) {
			wp_send_json_error();
		}
		$order = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? array_map( 'intval', wp_unslash( $_POST['order'] ) ) : array();
		foreach ( $order as $index => $post_id ) {
			if ( $post_id > 0 && get_post_type( $post_id ) === FP_Reviews_Post_Type::POST_TYPE ) {
				update_post_meta( $post_id, self::META_SORT, $index );
			}
		}
		wp_send_json_success();
	}

	private static function get_drag_handle_svg() {
		return '<span class="fp-sortable__drag-handle" role="button" tabindex="0" title="' . esc_attr__( 'Drag to reorder', 'firmpilot-reviews' ) . '" aria-label="' . esc_attr__( 'Drag to reorder', 'firmpilot-reviews' ) . '">' . fp_reviews_icon_drag_handle() . '</span>';
	}

	public function add_meta_boxes() {
		if ( ! use_block_editor_for_post_type( FP_Reviews_Post_Type::POST_TYPE ) ) {
			add_meta_box(
				'fp_review_details',
				__( 'Review details', 'firmpilot-reviews' ),
				array( $this, 'render_meta_box' ),
				FP_Reviews_Post_Type::POST_TYPE,
				'side',
				20
			);
		}
	}

	public function render_meta_box( WP_Post $post ) {
		wp_nonce_field( 'fp_review_save_meta', 'fp_review_meta_nonce' );
		$name       = get_post_meta( $post->ID, self::META_PREFIX . 'name', true );
		$location   = get_post_meta( $post->ID, self::META_PREFIX . 'location', true );
		$company    = get_post_meta( $post->ID, self::META_PREFIX . 'company', true );
		$job_title  = get_post_meta( $post->ID, self::META_PREFIX . 'job_title', true );
		$rating     = (int) get_post_meta( $post->ID, self::META_PREFIX . 'rating', true );
		$sort_order = (int) get_post_meta( $post->ID, self::META_PREFIX . 'sort_order', true );
		echo '<p class="description">' . esc_html__( 'Use the Categories box to assign review categories. Use the Featured Image panel to set the review image.', 'firmpilot-reviews' ) . '</p>';
		echo '<p><label for="fp_review_name">' . esc_html__( 'Name', 'firmpilot-reviews' ) . '</label><br><input type="text" class="widefat" id="fp_review_name" name="fp_review_name" value="' . esc_attr( (string) $name ) . '"></p>';
		echo '<p><label for="fp_review_location">' . esc_html__( 'Location', 'firmpilot-reviews' ) . '</label><br><input type="text" class="widefat" id="fp_review_location" name="fp_review_location" value="' . esc_attr( $location ) . '"></p>';
		echo '<p><label for="fp_review_company">' . esc_html__( 'Company', 'firmpilot-reviews' ) . '</label><br><input type="text" class="widefat" id="fp_review_company" name="fp_review_company" value="' . esc_attr( $company ) . '"></p>';
		echo '<p><label for="fp_review_job_title">' . esc_html__( 'Job Title', 'firmpilot-reviews' ) . '</label><br><input type="text" class="widefat" id="fp_review_job_title" name="fp_review_job_title" value="' . esc_attr( $job_title ) . '"></p>';
		echo '<p><label for="fp_review_rating">' . esc_html__( 'Rating (0-5)', 'firmpilot-reviews' ) . '</label><br><input type="number" name="fp_review_rating" id="fp_review_rating" min="0" max="5" value="' . esc_attr( (string) $rating ) . '"></p>';
		echo '<p><label for="fp_review_sort">' . esc_html__( 'Sort order', 'firmpilot-reviews' ) . '</label><br><input type="number" name="fp_review_sort_order" id="fp_review_sort" value="' . esc_attr( (string) $sort_order ) . '"></p>';
	}

	public function save_meta( $post_id, WP_Post $post ) {
		if ( ! isset( $_POST['fp_review_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fp_review_meta_nonce'] ) ), 'fp_review_save_meta' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['fp_review_name'] ) ) {
			update_post_meta( $post_id, self::META_PREFIX . 'name', sanitize_text_field( wp_unslash( $_POST['fp_review_name'] ) ) );
		}
		if ( isset( $_POST['fp_review_location'] ) ) {
			update_post_meta( $post_id, self::META_PREFIX . 'location', sanitize_text_field( wp_unslash( $_POST['fp_review_location'] ) ) );
		}
		if ( isset( $_POST['fp_review_company'] ) ) {
			update_post_meta( $post_id, self::META_PREFIX . 'company', sanitize_text_field( wp_unslash( $_POST['fp_review_company'] ) ) );
		}
		if ( isset( $_POST['fp_review_job_title'] ) ) {
			update_post_meta( $post_id, self::META_PREFIX . 'job_title', sanitize_text_field( wp_unslash( $_POST['fp_review_job_title'] ) ) );
		}
		if ( isset( $_POST['fp_review_rating'] ) ) {
			update_post_meta( $post_id, self::META_PREFIX . 'rating', max( 0, min( 5, (int) $_POST['fp_review_rating'] ) ) );
		}
		if ( isset( $_POST['fp_review_sort_order'] ) ) {
			update_post_meta( $post_id, self::META_PREFIX . 'sort_order', (int) $_POST['fp_review_sort_order'] );
		}
	}

	public function list_columns( $columns ) {
		$new = array( 'fp_review_sort' => '' );
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
		}

		$tax_col = 'taxonomy-' . FP_Reviews_Post_Type::TAXONOMY;
		if ( isset( $new[ $tax_col ] ) ) {
			$new[ $tax_col ] = __( 'Categories', 'firmpilot-reviews' );
		}

		// Insert Featured Image and review meta columns after Categories when taxonomy column exists.
		$ordered  = array();
		$inserted = false;
		foreach ( $new as $key => $label ) {
			$ordered[ $key ] = $label;
			if ( $key === $tax_col ) {
				$ordered['thumb']              = __( 'Featured Image', 'firmpilot-reviews' );
				$ordered['fp_review_location'] = __( 'Location', 'firmpilot-reviews' );
				$ordered['fp_review_rating']   = __( 'Rating', 'firmpilot-reviews' );
				$inserted                      = true;
			}
		}

		// When the taxonomy column is not registered, place columns immediately after Title.
		if ( ! $inserted ) {
			$ordered = array();
			foreach ( $new as $key => $label ) {
				$ordered[ $key ] = $label;
				if ( $key === 'title' ) {
					$ordered['thumb']              = __( 'Featured Image', 'firmpilot-reviews' );
					$ordered['fp_review_location'] = __( 'Location', 'firmpilot-reviews' );
					$ordered['fp_review_rating']   = __( 'Rating', 'firmpilot-reviews' );
				}
			}
		}

		return $ordered;
	}

	public function list_column_content( $column, $post_id ) {
		if ( $column === 'thumb' ) {
			$this->render_thumb_column( $post_id );
			return;
		}
		if ( $column === 'fp_review_sort' ) {
			echo self::get_drag_handle_svg();
		}
		if ( $column === 'fp_review_location' ) {
			echo esc_html( get_post_meta( $post_id, self::META_PREFIX . 'location', true ) ?: '—' );
		}
		if ( $column === 'fp_review_rating' ) {
			echo (int) get_post_meta( $post_id, self::META_PREFIX . 'rating', true );
		}
	}

	public function sortable_columns( $columns ) {
		return $columns;
	}

	private function render_thumb_column( $post_id ) {
		fp_list_thumb( $post_id, array(
			'change_title' => __( 'Change featured image', 'firmpilot-reviews' ),
			'remove_label' => __( 'Remove featured image', 'firmpilot-reviews' ),
		) );
	}

	// AJAX: set featured image and return thumbnail HTML.
	public function ajax_get_thumbnail() {
		$post_id  = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$thumb_id = isset( $_POST['thumbnail_id'] ) ? (int) $_POST['thumbnail_id'] : 0;
		if ( $post_id <= 0 || get_post_type( $post_id ) !== FP_Reviews_Post_Type::POST_TYPE || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'firmpilot-reviews' ) ), 403 );
		}
		if ( $thumb_id ) {
			fp_list_thumb_handle_set( $post_id, $thumb_id, array(
				'remove_label' => __( 'Remove featured image', 'firmpilot-reviews' ),
			) );
		}
		exit;
	}

	// AJAX: remove featured image and return empty-state HTML.
	public function ajax_remove_thumbnail() {
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( $post_id <= 0 || get_post_type( $post_id ) !== FP_Reviews_Post_Type::POST_TYPE || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'firmpilot-reviews' ) ), 403 );
		}
		fp_list_thumb_handle_remove( $post_id, array(
			'change_title' => __( 'Change featured image', 'firmpilot-reviews' ),
		) );
		exit;
	}
}
