<?php
/**
 * Admin screen assets.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the admin application using WordPress core packages (no build step).
 */
final class FP_Copilot_Admin_Assets {

	/**
	 * Admin page hook suffix.
	 */
	private const PAGE_HOOK = 'tools_page_fp-copilot';

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Enqueue scripts and styles on the plugin admin page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue( string $hook_suffix ): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'fp-copilot' !== $page && self::PAGE_HOOK !== $hook_suffix ) {
			return;
		}

		$script_path = FP_COPILOT_PLUGIN_DIR . 'modules/admin/assets/admin.js';
		$style_path  = FP_COPILOT_PLUGIN_DIR . 'modules/admin/assets/admin.css';

		if ( ! file_exists( $script_path ) ) {
			return;
		}

		wp_enqueue_style( 'wp-components' );

		wp_enqueue_style(
			'fp-copilot-admin',
			FP_COPILOT_PLUGIN_URL . 'modules/admin/assets/admin.css',
			array( 'wp-components' ),
			file_exists( $style_path ) ? (string) filemtime( $style_path ) : FP_COPILOT_VERSION
		);

		$script_deps = array(
			'wp-element',
			'wp-components',
			'wp-i18n',
			'wp-api-fetch',
		);

		if ( wp_script_is( 'wp-icons', 'registered' ) ) {
			$script_deps[] = 'wp-icons';
		}

		wp_enqueue_script(
			'fp-copilot-admin',
			FP_COPILOT_PLUGIN_URL . 'modules/admin/assets/admin.js',
			$script_deps,
			(string) filemtime( $script_path ),
			true
		);

		wp_set_script_translations( 'fp-copilot-admin', 'fp-copilot', FP_COPILOT_PLUGIN_DIR . 'languages' );

		wp_add_inline_script(
			'fp-copilot-admin',
			'window.fpCopilotAdmin = ' . wp_json_encode(
				array(
					'apiRoot'   => esc_url_raw( rest_url() ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'pluginUrl' => FP_COPILOT_PLUGIN_URL,
				)
			) . ';',
			'before'
		);
	}
}
