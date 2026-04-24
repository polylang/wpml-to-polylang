<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

defined( 'ABSPATH' ) || exit;

/**
 * String translations handler for the standalone, repeatable importer.
 *
 * Extends Strings with a distinct AJAX action name so it does not interfere
 * with the main migration flow and can be run independently any number of times.
 *
 * @since 0.7
 */
class StandaloneStrings extends Strings {

	/**
	 * Returns the action name.
	 *
	 * @since 0.7
	 *
	 * @return string
	 */
	public function getName() {
		return 'process_standalone_strings_translations';
	}
}
