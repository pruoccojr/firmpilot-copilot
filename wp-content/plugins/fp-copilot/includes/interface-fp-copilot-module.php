<?php
/**
 * Module contract.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every FirmPilot Copilot module implements this interface.
 */
interface FP_Copilot_Module {

	/**
	 * Unique module identifier (slug).
	 */
	public function id(): string;

	/**
	 * Human-readable module name.
	 */
	public function name(): string;

	/**
	 * Short description of what the module does.
	 */
	public function description(): string;

	/**
	 * Whether the module should load.
	 */
	public function is_enabled(): bool;

	/**
	 * Register hooks, routes, and other runtime behavior.
	 */
	public function boot(): void;
}
