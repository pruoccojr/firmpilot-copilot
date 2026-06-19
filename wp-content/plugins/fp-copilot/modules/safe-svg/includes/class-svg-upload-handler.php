<?php
/**
 * SVG upload integration for WordPress media.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers MIME types, sanitizes uploads, and fixes media library handling.
 */
final class FP_Copilot_Svg_Upload_Handler {

	/**
	 * SVG sanitizer.
	 */
	private FP_Copilot_Svg_Sanitizer $sanitizer;

	/**
	 * @param FP_Copilot_Svg_Sanitizer $sanitizer Sanitizer instance.
	 */
	public function __construct( FP_Copilot_Svg_Sanitizer $sanitizer ) {
		$this->sanitizer = $sanitizer;
	}

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		add_filter( 'upload_mimes', array( $this, 'allow_svg_mime' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_svg_filetype' ), 10, 4 );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_upload' ) );
		add_filter( 'file_is_displayable_image', array( $this, 'svg_is_displayable' ), 10, 2 );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'attachment_metadata' ), 10, 2 );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'prepare_attachment_for_js' ), 10, 3 );
		add_filter( 'wp_get_attachment_image_src', array( $this, 'attachment_image_src' ), 10, 4 );
		add_action( 'admin_head', array( $this, 'admin_svg_styles' ) );
	}

	/**
	 * Capability required to upload SVG files.
	 */
	private function upload_capability(): string {
		return (string) apply_filters( 'fp_copilot_safe_svg_upload_capability', 'manage_options' );
	}

	/**
	 * Whether the file is an SVG upload.
	 *
	 * @param string $filename File name.
	 * @param string $mime     Optional MIME type.
	 */
	private function is_svg_file( string $filename, string $mime = '' ): bool {
		if ( 'image/svg+xml' === $mime ) {
			return true;
		}

		return 'svg' === strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	}

	/**
	 * Add SVG to allowed upload MIME types.
	 *
	 * @param array<string, string> $mimes Allowed MIME types.
	 * @return array<string, string>
	 */
	public function allow_svg_mime( array $mimes ): array {
		$mimes['svg'] = 'image/svg+xml';

		return $mimes;
	}

	/**
	 * Ensure WordPress recognizes .svg extension and MIME type.
	 *
	 * @param array<string, string|false> $data     File data.
	 * @param string                      $file     Full file path.
	 * @param string                      $filename File name.
	 * @param array<string, string>|null  $mimes    Allowed MIME types.
	 * @return array<string, string|false>
	 */
	public function fix_svg_filetype( array $data, string $file, string $filename, $mimes ): array {
		if ( ! $this->is_svg_file( $filename, (string) ( $data['type'] ?? '' ) ) ) {
			return $data;
		}

		$data['ext']  = 'svg';
		$data['type'] = 'image/svg+xml';

		return $data;
	}

	/**
	 * Sanitize SVG files before they are moved into uploads.
	 *
	 * @param array<string, mixed> $file Upload file data.
	 * @return array<string, mixed>
	 */
	public function sanitize_upload( array $file ): array {
		if ( empty( $file['name'] ) || empty( $file['tmp_name'] ) ) {
			return $file;
		}

		if ( ! $this->is_svg_file( (string) $file['name'], (string) ( $file['type'] ?? '' ) ) ) {
			return $file;
		}

		if ( ! current_user_can( $this->upload_capability() ) ) {
			$file['error'] = esc_html__( 'You are not allowed to upload SVG files.', 'fp-copilot' );

			return $file;
		}

		$max_bytes = (int) apply_filters( 'fp_copilot_safe_svg_max_bytes', 1024 * 1024 );
		$size      = isset( $file['size'] ) ? (int) $file['size'] : 0;

		if ( $max_bytes > 0 && $size > $max_bytes ) {
			$file['error'] = esc_html__( 'SVG file exceeds the maximum allowed size.', 'fp-copilot' );

			return $file;
		}

		$contents = file_get_contents( $file['tmp_name'] );

		if ( false === $contents ) {
			$file['error'] = esc_html__( 'Unable to read the SVG file.', 'fp-copilot' );

			return $file;
		}

		$result = $this->sanitizer->sanitize( $contents );

		if ( is_wp_error( $result ) ) {
			$file['error'] = $result->get_error_message();

			return $file;
		}

		$written = file_put_contents( $file['tmp_name'], $result );

		if ( false === $written ) {
			$file['error'] = esc_html__( 'Unable to save the sanitized SVG file.', 'fp-copilot' );

			return $file;
		}

		$file['type'] = 'image/svg+xml';

		return $file;
	}

	/**
	 * Allow SVG previews in the media modal.
	 *
	 * @param bool   $displayable Whether the file is displayable.
	 * @param string $path        File path.
	 */
	public function svg_is_displayable( bool $displayable, string $path ): bool {
		if ( $this->is_svg_file( $path ) ) {
			return true;
		}

		return $displayable;
	}

	/**
	 * Store SVG dimensions when available.
	 *
	 * @param array<string, mixed> $metadata      Attachment metadata.
	 * @param int                  $attachment_id Attachment ID.
	 * @return array<string, mixed>
	 */
	public function attachment_metadata( array $metadata, int $attachment_id ): array {
		if ( 'image/svg+xml' !== get_post_mime_type( $attachment_id ) ) {
			return $metadata;
		}

		$dimensions = $this->get_svg_dimensions( get_attached_file( $attachment_id ) );

		if ( null !== $dimensions ) {
			$metadata['width']  = $dimensions['width'];
			$metadata['height'] = $dimensions['height'];
		}

		return $metadata;
	}

	/**
	 * Ensure the media library can preview SVG attachments.
	 *
	 * @param array<string, mixed> $response   Attachment response.
	 * @param WP_Post              $attachment Attachment post.
	 * @param array<string, mixed> $meta       Attachment meta.
	 * @return array<string, mixed>
	 */
	public function prepare_attachment_for_js( array $response, WP_Post $attachment, array $meta ): array {
		if ( 'image/svg+xml' !== $response['mime'] ) {
			return $response;
		}

		$dimensions = $this->get_svg_dimensions( get_attached_file( $attachment->ID ) );

		if ( null !== $dimensions ) {
			$response['width']  = $dimensions['width'];
			$response['height'] = $dimensions['height'];
		}

		$response['sizes'] = array(
			'full' => array(
				'url'         => $response['url'],
				'width'       => $response['width'] ?? 0,
				'height'      => $response['height'] ?? 0,
				'orientation' => 'portrait',
			),
		);

		return $response;
	}

	/**
	 * Return the SVG URL when WordPress requests a generated image size.
	 *
	 * @param array<int|string>|false $image         Image data.
	 * @param int                     $attachment_id Attachment ID.
	 * @param string|int[]            $size          Requested size.
	 */
	public function attachment_image_src( $image, int $attachment_id, $size, bool $icon ) {
		if ( $icon || 'image/svg+xml' !== get_post_mime_type( $attachment_id ) ) {
			return $image;
		}

		$url = wp_get_attachment_url( $attachment_id );

		if ( ! $url ) {
			return $image;
		}

		$dimensions = $this->get_svg_dimensions( get_attached_file( $attachment_id ) );

		return array(
			$url,
			$dimensions['width'] ?? 0,
			$dimensions['height'] ?? 0,
			false,
		);
	}

	/**
	 * Improve SVG thumbnails in the media grid.
	 */
	public function admin_svg_styles(): void {
		echo '<style>.attachment .thumbnail img[src$=".svg"], .media-icon img[src$=".svg"]{width:100%;height:auto;}</style>';
	}

	/**
	 * Parse width and height from an SVG file.
	 *
	 * @param string|false $file Absolute file path.
	 * @return array{width:int,height:int}|null
	 */
	private function get_svg_dimensions( $file ): ?array {
		if ( ! is_string( $file ) || ! is_readable( $file ) ) {
			return null;
		}

		$contents = file_get_contents( $file, false, null, 0, 8192 );

		if ( false === $contents ) {
			return null;
		}

		$width  = 0;
		$height = 0;

		if ( preg_match( '/<svg[^>]*\bwidth=["\']?([\d.]+)/i', $contents, $match ) ) {
			$width = (int) round( (float) $match[1] );
		}

		if ( preg_match( '/<svg[^>]*\bheight=["\']?([\d.]+)/i', $contents, $match ) ) {
			$height = (int) round( (float) $match[1] );
		}

		if ( ( 0 === $width || 0 === $height ) && preg_match( '/\bviewBox=["\']?\s*[\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)/i', $contents, $match ) ) {
			if ( 0 === $width ) {
				$width = (int) round( (float) $match[1] );
			}
			if ( 0 === $height ) {
				$height = (int) round( (float) $match[2] );
			}
		}

		if ( $width <= 0 || $height <= 0 ) {
			return null;
		}

		return array(
			'width'  => $width,
			'height' => $height,
		);
	}
}
