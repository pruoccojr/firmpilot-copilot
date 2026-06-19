<?php
/**
 * Secure SVG uploads for the media library.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Safe SVG module.
 */
final class FP_Copilot_Module_Safe_Svg extends FP_Copilot_Module_Base {

	/**
	 * Option key for the on/off toggle.
	 */
	public const OPTION_ENABLED = 'fp_copilot_safe_svg_enabled';

	/**
	 * Upload handler.
	 */
	private ?FP_Copilot_Svg_Upload_Handler $upload_handler = null;

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'safe-svg';
	}

	/**
	 * {@inheritdoc}
	 */
	public function name(): string {
		return __( 'Safe SVG', 'fp-copilot' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function description(): string {
		return __( 'Allows sanitized SVG uploads to the media library.', 'fp-copilot' );
	}

	/**
	 * Whether the feature toggle is on.
	 */
	public function is_active(): bool {
		return (bool) get_option( self::OPTION_ENABLED, false );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		$this->require_once( 'includes/class-svg-sanitizer.php' );
		$this->require_once( 'includes/class-svg-upload-handler.php' );

		$this->upload_handler = new FP_Copilot_Svg_Upload_Handler( new FP_Copilot_Svg_Sanitizer() );

		if ( $this->is_active() ) {
			$this->upload_handler->register();
		}
	}
}
