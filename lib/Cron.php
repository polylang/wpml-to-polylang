<?php
/**
 * Cron
 *
 * @package wpml-to-polylang
 */

namespace WPML_To_Polylang;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 404 Not Found' );
	exit();
}

/**
 * Responsible for scheduling and invoking the cron event.
 */
class Cron {

	const HOOK_NAME = 'wpml_to_polylang_import';

	/**
	 * Registers the action to be used for the cron.
	 *
	 * @return void
	 */
	public static function add_cron_hook() {
		// Cron needs to be outside the is_admin check.
		add_action( self::HOOK_NAME, __CLASS__ . '::trigger_processor' );
	}

	/**
	 * Schedules the cron single event.
	 *
	 * @return void
	 */
	public static function schedule_event() {
		if ( false === \wp_next_scheduled( self::HOOK_NAME ) ) {
			\wp_schedule_single_event( time(), self::HOOK_NAME );
		}
	}

	/**
	 * Clears to cron single event.
	 *
	 * @return void
	 */
	public static function clear_schedule_event() {
		\wp_clear_scheduled_hook( self::HOOK_NAME );
	}

	/**
	 * Callback used to trigger the cron process.
	 *
	 * @return void
	 */
	public static function trigger_processor() {
		new Processor();
	}

}
