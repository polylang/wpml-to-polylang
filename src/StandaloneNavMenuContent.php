<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

defined( 'ABSPATH' ) || exit;

/**
 * Standalone (repeatable) variant of NavMenuContent.
 *
 * Uses a distinct AJAX action name so it can be triggered from the admin page
 * independently of the main migration chain without conflicting with the chained
 * instance (which has a $next action set).
 *
 * @since 1.0
 */
class StandaloneNavMenuContent extends NavMenuContent {

	/**
	 * Returns the unique AJAX action name for the standalone tool.
	 *
	 * @return string
	 */
	public function getName() {
		return 'process_standalone_nav_menu_content';
	}
}
