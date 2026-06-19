<?php
/**
 * Discovers and boots plugin modules.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads self-contained modules from the modules directory.
 */
final class FP_Copilot_Module_Loader {

	/**
	 * Booted modules keyed by ID.
	 *
	 * @var array<string, FP_Copilot_Module>
	 */
	private array $modules = array();

	/**
	 * Discover and boot all enabled modules.
	 */
	public function boot(): void {
		$module_files = (array) apply_filters(
			'fp_copilot_module_files',
			glob( FP_COPILOT_PLUGIN_DIR . 'modules/*/module.php' ) ?: array()
		);

		foreach ( $module_files as $module_file ) {
			if ( ! is_readable( $module_file ) ) {
				continue;
			}

			$module = include $module_file;

			if ( ! $module instanceof FP_Copilot_Module ) {
				continue;
			}

			if ( ! $module->is_enabled() ) {
				continue;
			}

			$module->boot();
			$this->modules[ $module->id() ] = $module;
		}

		/**
		 * Fires after all enabled modules have booted.
		 *
		 * @param array<string, FP_Copilot_Module> $modules Loaded modules.
		 */
		do_action( 'fp_copilot_modules_loaded', $this->modules );
	}

	/**
	 * Returns all booted modules.
	 *
	 * @return array<string, FP_Copilot_Module>
	 */
	public function all(): array {
		return $this->modules;
	}

	/**
	 * Returns a single module by ID.
	 */
	public function get( string $id ): ?FP_Copilot_Module {
		return $this->modules[ $id ] ?? null;
	}
}
