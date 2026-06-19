<?php
/**
 * Shared module helpers.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base class for self-contained plugin modules.
 */
abstract class FP_Copilot_Module_Base implements FP_Copilot_Module {

	/**
	 * Absolute path to this module's directory.
	 */
	private string $dir;

	/**
	 * Public URL to this module's directory.
	 */
	private string $url;

	/**
	 * @param string $dir Module directory path.
	 * @param string $url Module directory URL.
	 */
	public function __construct( string $dir, string $url ) {
		$this->dir = trailingslashit( $dir );
		$this->url = trailingslashit( $url );
	}

	/**
	 * Whether this module is enabled.
	 *
	 * Disable individual modules with:
	 * add_filter( 'fp_copilot_module_enabled_{id}', '__return_false' );
	 */
	public function is_enabled(): bool {
		return (bool) apply_filters( 'fp_copilot_module_enabled_' . $this->id(), true );
	}

	/**
	 * Absolute path to a file inside this module.
	 *
	 * @param string $path Relative path.
	 */
	protected function path( string $path = '' ): string {
		return $this->dir . ltrim( $path, '/\\' );
	}

	/**
	 * Public URL to a file inside this module.
	 *
	 * @param string $path Relative path.
	 */
	protected function url( string $path = '' ): string {
		return $this->url . ltrim( $path, '/\\' );
	}

	/**
	 * Require a PHP file from this module's directory once.
	 *
	 * @param string $path Relative path.
	 */
	protected function require_once( string $path ): void {
		require_once $this->path( $path );
	}
}
