<?php
/**
 * WPML to Polylang
 *
 * PHP version 5.6
 *
 * @package              wpml-to-polylang
 * @author               WP SYNTEX
 * @license              GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin name:          WPML to Polylang
 * Plugin URI:           https://polylang.pro
 * Description:          Import multilingual data from WPML into Polylang
 * Version:              0.6
 * Requires at least:    5.8
 * Requires PHP:         5.6
 * Author:               WP SYNTEX
 * Author URI:           https://polylang.pro
 * Text Domain:          wpml-to-polylang
 * Domain Path:          /languages
 * License:              GPL v3 or later
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * Copyright 2013-2020 Frédéric Demarle
 * Copyright 2021-2023 WP SYNTEX
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

namespace WP_Syntex\WPML_To_Polylang;

defined( 'ABSPATH' ) || exit;

define( 'WPML_TO_POLYLANG_VERSION', '0.6' );
define( 'WPML_TO_POLYLANG_MIN_WP_VERSION', '5.8' );
define( 'WPML_TO_POLYLANG_MIN_PLL_VERSION', '3.4' );

if ( ! defined( 'WPML_TO_POLYLANG_QUERY_BATCH_SIZE' ) ) {
	define( 'WPML_TO_POLYLANG_QUERY_BATCH_SIZE', 5000 ); // Limits the size of database queries.
}

require __DIR__ . '/vendor/autoload.php';
( new Plugin() )->init();
