<?php
/**
 * FirmPilot Reviews – list table featured image column. Used with admin/pages/list/list-thumb.js.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*------------------------------------------------------------
	List thumb component
------------------------------------------------------------*/

// Allowed HTML for list thumb markup (link, button, span, img).
function fp_list_thumb_kses() {
	return array(
		'a'      => array( 'href' => true, 'class' => true, 'title' => true, 'data-post-id' => true, 'data-nonce' => true, 'data-thumbnail-id' => true ),
		'button' => array( 'type' => true, 'class' => true, 'aria-label' => true, 'data-post-id' => true, 'data-nonce' => true ),
		'span'   => array( 'class' => true ),
		'img'    => array( 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'class' => true ),
	);
}

// Output (or return) the list table thumbnail column content.
function fp_list_thumb( $post_id, $args = array() ) {
	$post_id = (int) $post_id;
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $args['return'] ?? false ? '' : null;
	}

	$defaults = array(
		'change_title' => __( 'Change featured image', 'firmpilot-reviews' ),
		'remove_label' => __( 'Remove featured image', 'firmpilot-reviews' ),
		'return'       => false,
	);
	$r = array_merge( $defaults, $args );

	$nonce    = wp_create_nonce( 'set_post_thumbnail-' . $post_id );
	$url      = admin_url( 'media-upload.php?post_id=' . $post_id . '&type=image&TB_iframe=1&_wpnonce=' . $nonce );
	$thumb_id = has_post_thumbnail( $post_id ) ? get_post_thumbnail_id( $post_id ) : 0;

	$attrs = 'class="fp-column__thumb" href="' . esc_url( $url ) . '" data-post-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( $nonce ) . '" title="' . esc_attr( $r['change_title'] ) . '"';
	if ( $thumb_id ) {
		$attrs .= ' data-thumbnail-id="' . esc_attr( $thumb_id ) . '"';
	}

	$link = '<a ' . $attrs . '>';
	if ( $thumb_id ) {
		$remove_btn = '<button type="button" class="fp-column__thumb__remove" aria-label="' . esc_attr( $r['remove_label'] ) . '" data-post-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( $nonce ) . '"><span class="dashicons dashicons-trash"></span></button>';
		$link .= $remove_btn . wp_get_attachment_image( $thumb_id, array( 60, 60 ) );
		$out = $link . '</a>';
	} else {
		$link .= '<span class="dashicons dashicons-plus-alt2"></span></a>';
		$out = $link;
	}

	$out = wp_kses( $out, fp_list_thumb_kses() );

	if ( ! empty( $r['return'] ) ) {
		return $out;
	}

	echo $out;
}

// Return the empty-state markup (no featured image) for the list thumb column. Use in AJAX remove_thumbnail response.
function fp_list_thumb_empty_markup( $post_id, $args = array() ) {
	$post_id = (int) $post_id;
	$defaults = array(
		'change_title' => __( 'Change featured image', 'firmpilot-reviews' ),
		'remove_label' => __( 'Remove featured image', 'firmpilot-reviews' ),
	);
	$r = array_merge( $defaults, $args );

	$nonce = wp_create_nonce( 'set_post_thumbnail-' . $post_id );
	$url   = admin_url( 'media-upload.php?post_id=' . $post_id . '&type=image&TB_iframe=1&_wpnonce=' . $nonce );
	$html  = '<a class="fp-column__thumb" href="' . esc_url( $url ) . '" data-post-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( $nonce ) . '" title="' . esc_attr( $r['change_title'] ) . '"><span class="dashicons dashicons-plus-alt2"></span></a>';

	return wp_kses( $html, fp_list_thumb_kses() );
}

// Return the image + remove button markup for the list thumb column. Use in AJAX get_thumbnail response (after user selects a new image).
function fp_list_thumb_image_markup( $post_id, $thumb_id, $args = array() ) {
	$post_id  = (int) $post_id;
	$thumb_id = (int) $thumb_id;
	if ( ! $thumb_id ) {
		return '';
	}

	$defaults = array(
		'remove_label' => __( 'Remove featured image', 'firmpilot-reviews' ),
	);
	$r = array_merge( $defaults, $args );

	$nonce      = wp_create_nonce( 'set_post_thumbnail-' . $post_id );
	$remove_btn = '<button type="button" class="fp-column__thumb__remove" aria-label="' . esc_attr( $r['remove_label'] ) . '" data-post-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( $nonce ) . '"><span class="dashicons dashicons-trash"></span></button>';
	$img        = wp_get_attachment_image( $thumb_id, array( 60, 60 ) );

	return wp_kses( $remove_btn . $img, fp_list_thumb_kses() );
}

// Handle AJAX "set thumbnail": verify nonce/capability, set the featured image, output image markup, exit. Plugins should call this from their get_thumbnail action when thumbnail_id is in the request.
function fp_list_thumb_handle_set( $post_id, $thumb_id, $args = array() ) {
	$post_id  = (int) $post_id;
	$thumb_id = (int) $thumb_id;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( '', 403 );
	}
	check_ajax_referer( 'set_post_thumbnail-' . $post_id, '_ajax_nonce' );
	if ( ! $thumb_id ) {
		wp_die( '', 400 );
	}
	set_post_thumbnail( $post_id, $thumb_id );
	echo fp_list_thumb_image_markup( $post_id, $thumb_id, $args );
	exit;
}

// Handle AJAX "remove thumbnail": verify nonce/capability, delete the featured image, output empty markup, exit. Plugins should call this from their remove_thumbnail action.
function fp_list_thumb_handle_remove( $post_id, $args = array() ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
	}
	check_ajax_referer( 'set_post_thumbnail-' . $post_id, '_ajax_nonce' );
	delete_post_thumbnail( $post_id );
	echo fp_list_thumb_empty_markup( $post_id, $args );
	exit;
}
