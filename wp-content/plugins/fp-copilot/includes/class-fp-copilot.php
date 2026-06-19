<?php
/**
 * Main plugin bootstrap.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * FirmPilot Copilot plugin.
 */
final class FP_Copilot {

	/**
	 * Plugin instance.
	 *
	 * @var FP_Copilot|null
	 */
	private static ?FP_Copilot $instance = null;

	/**
	 * Module loader.
	 */
	private FP_Copilot_Module_Loader $modules;

	/**
	 * Returns the singleton instance.
	 */
	public static function instance(): FP_Copilot {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->modules = new FP_Copilot_Module_Loader();

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 0 );
		add_action( 'plugins_loaded', array( $this, 'boot_modules' ), 5 );
	}

	/**
	 * Load plugin translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'fp-copilot',
			false,
			dirname( plugin_basename( FP_COPILOT_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Boot all registered modules.
	 */
	public function boot_modules(): void {
		$this->modules->boot();
	}

	/**
	 * Returns the module loader.
	 */
	public function modules(): FP_Copilot_Module_Loader {
		return $this->modules;
	}
}
