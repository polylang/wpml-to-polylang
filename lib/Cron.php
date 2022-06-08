<?php

namespace WPML_To_Polylang;

// Deny direct access
if ( ! defined( 'ABSPATH' ) ) {
	header( "HTTP/1.0 404 Not Found" );
	exit();
}

/**
 * Responsible for scheduling and invoking the cron event
 */
class Cron {

	const HOOK_NAME = 'wpml_to_polylang_import';

	public static function add_cron_hook() {
		// Cron needs to be outside the is_admin check
		add_action( self::HOOK_NAME, __CLASS__ . '::trigger_processor' );
	}

	public static function schedule_event() {
		if ( false === \wp_next_scheduled( self::HOOK_NAME ) ) {
			\wp_schedule_single_event( time(), self::HOOK_NAME );
		}
	}

	public static function clear_schedule_event() {
		\wp_clear_scheduled_hook( self::HOOK_NAME );
	}

	public static function trigger_processor() {
		new Processor();
	}

}