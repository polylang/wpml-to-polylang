<?php
/**
 * WPML to Polylang
 *
 * @package              wpml-to-polylang
 * @author               WP SYNTEX
 * @license              GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin name:          WPML to Polylang
 * Plugin URI:           https://polylang.pro
 * Description:          Import multilingual data from WPML into Polylang
 * Version:              0.4
 * Requires at least:    4.9
 * Requires PHP:         5.6
 * Author:               WP SYNTEX
 * Author URI:           https://polylang.pro
 * Text Domain:          wpml-to-polylang
 * Domain Path:          /languages
 * License:              GPL v3 or later
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * Copyright 2013-2020 Frédéric Demarle
 * Copyright 2021 WP SYNTEX
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace WPML_To_Polylang;

// Deny direct access
if (!defined('ABSPATH')) {
    header("HTTP/1.0 404 Not Found");
    exit();
}

// Define namespaced constants
define(__NAMESPACE__ . '\PLUGIN_DIRECTORY', plugin_dir_path(__FILE__));
define(__NAMESPACE__ . '\LIBRARY_DIRECTORY', PLUGIN_DIRECTORY . 'lib');

/**
 * A class to manage migration from WPML to Polylang.
 *
 * @since 0.1
 *
 * @see http://wpml.org/documentation/support/wpml-tables/
 */
class Plugin {

    /**
     * Constructor
     *
     * @since 0.1
     */
    public function __construct() {
        // Register plugin autoloader
        spl_autoload_register(__NAMESPACE__ . '\\Plugin::autoload');
        new Tools_Page();
    }

    /**
     * Autoloads the plugin resources
     * @param string $class
     */
    public static function autoload($class) {
        $class = ltrim($class, '\\');
        // Make sure we are in the current namespace
        if (strpos($class, __NAMESPACE__) !== 0) {
            return;
        }
        // Fix class
        $class = str_replace(__NAMESPACE__, '', $class);
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        // Namespaced files are located in the lib directory
        $path = LIBRARY_DIRECTORY . $class . '.php';
        if (file_exists($path)) {
            require_once($path);
        }
    }

}

// Init the plugin
new Plugin();