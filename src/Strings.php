<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the strings translations.
 *
 * @since 0.5
 */
class Strings extends AbstractSteppable {

	/**
	 * Returns the action name.
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	public function getName() {
		return 'process_strings_translations';
	}

	/**
	 * Returns the processing message.
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	protected function getMessage() {
		return esc_html__( 'Processing strings translations', 'wpml-to-polylang' );
	}

	/**
	 * Processes the strings translations.
	 *
	 * @since 0.5
	 *
	 * @return void
	 */
	protected function handle() {
		$stringTranslations = $this->getWPMLStringsTranslations();

		if ( empty( $stringTranslations ) ) {
			return;
		}

		foreach ( $stringTranslations as $lang => $strings ) {
			$language = PLL()->model->get_language( $lang );

			if ( empty( $language ) ) {
				continue;
			}

			$mo = new \PLL_MO();
			$mo->import_from_db( $language ); // Import strings saved in a previous step.

			foreach ( $strings as $msg ) {
				$mo->add_entry( $mo->make_entry( $msg[0], $msg[1] ) );
			}

			$mo->export_to_db( $language );
		}
	}

	/**
	 * Returns the number of WPML strings translations.
	 *
	 * @since 0.5
	 *
	 * @return int
	 */
	protected function getTotal() {
		global $wpdb;

		return (int) $wpdb->get_var(
			sprintf(
				"SELECT COUNT(*)
				FROM {$wpdb->prefix}icl_strings AS s
				INNER JOIN {$wpdb->prefix}icl_string_translations AS st ON st.string_id = s.id
				WHERE s.context NOT IN ( '%s' )",
				implode( "', '", esc_sql( $this->getDomains() ) )
			)
		);
	}

	/**
	 * Gets the WPML Strings translations.
	 *
	 * @since 0.5
	 *
	 * @return string[][][]
	 */
	protected function getWPMLStringsTranslations() {
		global $wpdb;

		$offset = ( $this->step * WPML_TO_POLYLANG_QUERY_BATCH_SIZE ) - WPML_TO_POLYLANG_QUERY_BATCH_SIZE;

		/**
		 * WPML string translations.
		 *
		 * @var \stdClass[]
		 */
		$results = $wpdb->get_results(
			sprintf(
				"SELECT s.value AS string, st.language, st.value AS translation
				FROM {$wpdb->prefix}icl_strings AS s
				INNER JOIN {$wpdb->prefix}icl_string_translations AS st ON st.string_id = s.id
				WHERE s.context NOT IN ( '%s' )
				LIMIT %d, %d",
				implode( "', '", esc_sql( $this->getDomains() ) ),
				absint( $offset ),
				absint( WPML_TO_POLYLANG_QUERY_BATCH_SIZE )
			)
		);

		$stringTranslations = [];

		// Order them in a convenient way.
		foreach ( $results as $st ) {
			if ( ! empty( $st->string ) & ! empty( $st->translation ) ) {
				$stringTranslations[ $st->language ][] = [ $st->string, $st->translation ];
			}
		}

		return $stringTranslations;
	}

	/**
	 * Returns mo files text domains stored by WPML.
	 *
	 * @since 0.5
	 *
	 * @return string[]
	 */
	protected function getDomains() {
		global $wpdb;

		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}icl_mo_files_domains'" ) ) {
			return [ '' ]; // A trick to avoid an empty NOT IN in sql query.
		}

		$domains = $wpdb->get_col( "SELECT DISTINCT domain FROM {$wpdb->prefix}icl_mo_files_domains" );

		if ( empty( $domains ) ) {
			return [ '' ];
		}

		return $domains;
	}
}
