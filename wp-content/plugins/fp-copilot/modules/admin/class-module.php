<?php
/**
 * WordPress admin integration.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles wp-admin UI for FirmPilot Copilot.
 */
final class FP_Copilot_Module_Admin extends FP_Copilot_Module_Base {

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'admin';
	}

	/**
	 * {@inheritdoc}
	 */
	public function name(): string {
		return __( 'Admin', 'fp-copilot' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function description(): string {
		return __( 'Admin screens, menus, and notices.', 'fp-copilot' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		$this->require_once( 'includes/class-admin-utilities.php' );
		$this->require_once( 'includes/class-admin-rest.php' );
		$this->require_once( 'includes/class-admin-assets.php' );

		FP_Copilot_Admin_Rest::register();

		if ( ! is_admin() ) {
			return;
		}

		FP_Copilot_Admin_Assets::register();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_notices', array( $this, 'plugins_screen_notice' ) );
	}

	/**
	 * Register the plugin admin page under Tools.
	 */
	public function register_menu(): void {
		add_management_page(
			__( 'FirmPilot Copilot', 'fp-copilot' ),
			__( 'FirmPilot Copilot', 'fp-copilot' ),
			'manage_options',
			'fp-copilot',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the React admin mount point.
	 */
	public function render_admin_page(): void {
		echo '<div class="wrap fp-copilot-admin-wrap">';
		echo '<h1>' . esc_html__( 'FirmPilot Copilot', 'fp-copilot' ) . '</h1>';
		echo '<div id="fp-copilot-admin-root">';
		echo '<p class="fp-copilot-admin__boot-message">' . esc_html__( 'Loading…', 'fp-copilot' ) . '</p>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Show a confirmation notice on the Plugins screen.
	 */
	public function plugins_screen_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'FirmPilot Copilot is active.', 'fp-copilot' )
		);
	}
}
