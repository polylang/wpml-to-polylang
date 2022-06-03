<?php

namespace WPML_To_Polylang;

// Deny direct access
if (!defined('ABSPATH')) {
    header("HTTP/1.0 404 Not Found");
    exit();
}

/**
 * Responsible for scheduling and invoking the cron event
 */
class Cron {

    public static function add_cron_hook() {
        // Cron needs to be outside the is_admin check
        add_action('wpml_to_polylang_import', __CLASS__.'::triggerProcessor');
    }

    public static function schedule_event() {
        if ( false === \wp_next_scheduled( 'wpml_to_polylang_import' ) ) {
            \wp_schedule_single_event(time(), 'wpml_to_polylang_import');
        }
    }

    public static function triggerProcessor() {
        new Processor();
    }

}