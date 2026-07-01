<?php

/**
 * Reviews for FirmPilot – register fp_review post type for default list and block editor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FP_Reviews_Post_Type {

	const POST_TYPE   = 'fp_review';
	const TAXONOMY    = 'fp_review_category';
	const META_SORT = '_fp_review_sort_order';

	public function __construct()
	{
		add_action('init', array($this, 'register_post_type'), 5);
		add_action('init', array($this, 'register_meta'), 10);
		add_action('init', array($this, 'register_category_taxonomy'), 11);
		add_action('save_post_' . self::POST_TYPE, array($this, 'ensure_sort_order_meta'), 20, 2);
		add_action('wp_insert_post', array($this, 'set_default_category_on_create'), 20, 3);
		add_filter('template_include', array($this, 'template_include'), 99);
		add_action('wp_enqueue_scripts', array($this, 'enqueue_single_assets'));
		add_filter('the_content', array($this, 'filter_single_review_content'), 20);
	}

	// Ensure _fp_review_sort_order meta exists so the post appears in the All Reviews list. The list orders by this meta; WP_Query excludes posts without the meta when using meta_value_num.
	public function ensure_sort_order_meta($post_id, $post = null)
	{
		if (! $post_id || ($post && $post->post_type !== self::POST_TYPE)) {
			return;
		}
		if (get_post_type($post_id) !== self::POST_TYPE) {
			return;
		}
		if (! metadata_exists('post', $post_id, self::META_SORT)) {
			update_post_meta($post_id, self::META_SORT, 0);
		}
	}

	// Register plugin-specific review category taxonomy (separate from blog Categories).
	public function register_category_taxonomy()
	{
		$labels = array(
			'name'              => _x('Review Categories', 'taxonomy general name', 'firmpilot-reviews'),
			'singular_name'     => _x('Review Category', 'taxonomy singular name', 'firmpilot-reviews'),
			'search_items'      => __('Search Review Categories', 'firmpilot-reviews'),
			'all_items'         => __('All Review Categories', 'firmpilot-reviews'),
			'parent_item'       => __('Parent Review Category', 'firmpilot-reviews'),
			'parent_item_colon' => __('Parent Review Category:', 'firmpilot-reviews'),
			'edit_item'         => __('Edit Review Category', 'firmpilot-reviews'),
			'update_item'       => __('Update Review Category', 'firmpilot-reviews'),
			'add_new_item'      => __('Add New Review Category', 'firmpilot-reviews'),
			'new_item_name'     => __('New Review Category Name', 'firmpilot-reviews'),
			'menu_name'         => __('Categories', 'firmpilot-reviews'),
		);
		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => false,
			'default_term'      => array(
				'name' => __('Uncategorized', 'firmpilot-reviews'),
				'slug' => 'uncategorized',
			),
		);
		register_taxonomy(self::TAXONOMY, array(self::POST_TYPE), $args);
	}

	// When a review is created: assign default category if none, and ensure sort_order meta exists so the post appears in the All Reviews list (which orders by _fp_review_sort_order).
	public function set_default_category_on_create($post_id, $post, $update)
	{
		if ($post->post_type !== self::POST_TYPE) {
			return;
		}
		$terms = get_the_terms($post_id, self::TAXONOMY);
		if (! $terms || is_wp_error($terms) || empty($terms)) {
			$default = get_term_by('slug', 'uncategorized', self::TAXONOMY);
			if ($default && ! is_wp_error($default)) {
				wp_set_object_terms($post_id, array((int) $default->term_id), self::TAXONOMY);
			}
		}
		$this->ensure_sort_order_meta($post_id, $post);
	}

	public function register_post_type()
	{
		$labels = array(
			'name' => _x('Reviews', 'post type general name', 'firmpilot-reviews'),
			'singular_name' => _x('Review', 'post type singular name', 'firmpilot-reviews'),
			'menu_name' => __('Reviews', 'firmpilot-reviews'),
			'all_items' => __('All Reviews', 'firmpilot-reviews'),
			'add_new' => __('Add Review', 'firmpilot-reviews'),
			'add_new_item' => __('Add Review', 'firmpilot-reviews'),
			'edit_item' => __('Edit Review', 'firmpilot-reviews'),
			'new_item' => __('New Review', 'firmpilot-reviews'),
			'view_item' => __('View Review', 'firmpilot-reviews'),
			'search_items' => __('Search Reviews', 'firmpilot-reviews'),
			'not_found' => __('No reviews found.', 'firmpilot-reviews'),
			'not_found_in_trash' => __('No reviews found in Trash.', 'firmpilot-reviews'),
		);
		$args = array(
			'labels'            => $labels,
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'menu_icon'         => 'dashicons-format-quote',
			'menu_position'     => 28,
			'capability_type'   => 'post',
			'supports'          => array('title', 'editor', 'thumbnail', 'custom-fields'),
			'show_in_rest'      => true,
			'rewrite'           => false,
		);
		register_post_type(self::POST_TYPE, $args);
	}

	// Use the theme's default page template for single review view (not the post template).
	public function template_include($template)
	{
		if (is_singular(self::POST_TYPE)) {
			$page_template = get_query_template('page');
			if ($page_template) {
				return $page_template;
			}
		}
		return $template;
	}

	// Front-end styles/scripts for single review view (card, avatar, stars).
	public function enqueue_single_assets()
	{
		if (! is_singular(self::POST_TYPE)) {
			return;
		}
		wp_enqueue_style( 'fp-reviews' );
	}

	// Replace post body with structured review markup (Name meta, quote, meta fields).
	public function filter_single_review_content($content)
	{
		if (! empty($GLOBALS['FP_Reviews_skip_content_filter'])) {
			return $content;
		}
		if (! is_singular(self::POST_TYPE) || ! in_the_loop() || ! is_main_query()) {
			return $content;
		}
		$html = FP_Reviews_Render::get_single_review_html(get_the_ID());
		return $html !== '' ? $html : $content;
	}

	public function register_meta()
	{
		$auth = function () {
			return current_user_can('edit_posts');
		};
		register_post_meta(self::POST_TYPE, '_fp_review_name', array('show_in_rest' => true, 'single' => true, 'type' => 'string', 'default' => '', 'auth_callback' => $auth));
		register_post_meta(self::POST_TYPE, '_fp_review_location', array('show_in_rest' => true, 'single' => true, 'type' => 'string', 'default' => '', 'auth_callback' => $auth));
		register_post_meta(self::POST_TYPE, '_fp_review_company', array('show_in_rest' => true, 'single' => true, 'type' => 'string', 'default' => '', 'auth_callback' => $auth));
		register_post_meta(self::POST_TYPE, '_fp_review_job_title', array('show_in_rest' => true, 'single' => true, 'type' => 'string', 'default' => '', 'auth_callback' => $auth));
		register_post_meta(self::POST_TYPE, '_fp_review_rating', array('show_in_rest' => true, 'single' => true, 'type' => 'integer', 'default' => 0, 'auth_callback' => $auth));
		register_post_meta(self::POST_TYPE, self::META_SORT, array('show_in_rest' => true, 'single' => true, 'type' => 'integer', 'default' => 0, 'auth_callback' => $auth));
	}
}
