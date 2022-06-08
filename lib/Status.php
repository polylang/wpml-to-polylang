<?php
/**
 * Status
 *
 * @package wpml-to-polylang
 */

namespace WPML_To_Polylang;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 404 Not Found' );
	exit();
}

/**
 * A status controller for the import process
 */
class Status {

	const IMPORT_STATUS_FLAG = 'wpml-importer-status';

	// Import statuses (value is irrelevant, just needs to be unique).
	const STATUS_WAITING_ON_CRON = 0;
	const STATUS_COMPLETED = 1;
	const STATUS_ERRORED = 2;
	const STATUS_PREPARING_DATA = 3;
	const STATUS_PROCESSING_POST_AND_TAX_TRANSLATIONS = 4;
	const STATUS_PROCESSING_POST_TERM_TRANSLATIONS = 5;
	const STATUS_PROCESSING_NAV_MENU_TRANSLATIONS = 6;
	const STATUS_PROCESSING_OBJECT_WITH_NO_LANGUAGE = 7;
	const STATUS_PROCESSING_STRING_TRANSLATIONS = 8;
	const STATUS_PROCESSING_OPTIONS = 9;


	/**
	 * Update the import status flag
	 *
	 * @param int $status The status to update to.
	 * @return void
	 */
	public static function update( $status ) {
		\update_option( self::IMPORT_STATUS_FLAG, $status, false );
	}

	/**
	 * Returns the import status flag
	 *
	 * @return false|int
	 */
	public static function get() {
		$_tmp = \get_option( self::IMPORT_STATUS_FLAG );

		return false !== $_tmp ? (int) $_tmp : false;
	}

	/**
	 * Removes the status flag from the database
	 *
	 * @return bool
	 */
	public static function remove_from_db() {
		return \delete_option( self::IMPORT_STATUS_FLAG );
	}

	/**
	 * Returns the string for the text to show the user for a status
	 *
	 * @param int $status The status to get a string representation for.
	 * @return string
	 */
	public static function get_as_text( $status ) {
		switch ( $status ) {
			case self::STATUS_COMPLETED:
				$string = \__( 'Import from WPML to Polylang should have been successful!', 'wpml-to-polylang' );
				break;
			case self::STATUS_ERRORED:
				$string = \__( 'An error occurred during the import, please check you logs', 'wpml-to-polylang' );
				break;
			case self::STATUS_PREPARING_DATA:
				$string = \__( 'Preparing Data', 'wpml-to-polylang' );
				break;
			case self::STATUS_PROCESSING_POST_AND_TAX_TRANSLATIONS:
				$string = \__( 'Processing Post, Taxonomy, and Nav Menu Translations', 'wpml-to-polylang' );
				break;
			case self::STATUS_PROCESSING_POST_TERM_TRANSLATIONS:
				$string = \__( 'Processing Post Term Translations', 'wpml-to-polylang' );
				break;
			case self::STATUS_PROCESSING_NAV_MENU_TRANSLATIONS:
				$string = \__( 'Processing Nav Menu Translations', 'wpml-to-polylang' );
				break;
			case self::STATUS_PROCESSING_OBJECT_WITH_NO_LANGUAGE:
				$string = \__( 'Processing Objects With No Translation', 'wpml-to-polylang' );
				break;
			case self::STATUS_PROCESSING_STRING_TRANSLATIONS:
				$string = \__( 'Processing String Translations', 'wpml-to-polylang' );
				break;
			case self::STATUS_PROCESSING_OPTIONS:
				$string = \__( 'Processing Options', 'wpml-to-polylang' );
				break;
			case self::STATUS_WAITING_ON_CRON:
			default:
				$string = \__( 'Waiting for cron to take over the request', 'wpml-to-polylang' );
				break;
		}

		return \esc_html( $string );
	}
}
