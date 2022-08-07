<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

/**
 * Handles the strings translations.
 */
class Strings extends AbstractAction {

	/**
	 * Returns the action name.
	 *
	 * @return string
	 */
	public function getName() {
		return 'process_strings_translations';
	}

	/**
	 * Returns the processing message.
	 *
	 * @return string
	 */
	protected function getMessage() {
		return esc_html__( 'Processing strings translations', 'wpml-to-polylang' );
	}

	/**
	 * Processes the strings translations.
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
	 * Returns the action completion percentage.
	 *
	 * @return int
	 */
	protected function getPercentage() {
		$total      = $this->getTotal();
		$percentage = ( $this->step * WPML_TO_POLYLANG_QUERY_BATCH_SIZE ) / $total * 100;
		$percentage = (int) ceil( $percentage );

		return $percentage > 100 ? 100 : $percentage;
	}

	/**
	 * Returns the number of WPML strings translations.
	 *
	 * @return int
	 */
	protected function getTotal() {
		global $wpdb;

		return (int) $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$wpdb->prefix}icl_strings AS s
			INNER JOIN {$wpdb->prefix}icl_string_translations AS st ON st.string_id = s.id"
		);
	}

	/**
	 * Gets the WPML Strings translations.
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
			$wpdb->prepare(
				"SELECT s.value AS string, st.language, st.value AS translation
				FROM {$wpdb->prefix}icl_strings AS s
				INNER JOIN {$wpdb->prefix}icl_string_translations AS st ON st.string_id = s.id
				LIMIT %d, %d",
				$offset,
				WPML_TO_POLYLANG_QUERY_BATCH_SIZE
			)
		);

		$stringTranslations = [];

		// Order them in a convenient way.
		foreach ( $results as $st ) {
			if ( ! empty( $st->string ) ) {
				$stringTranslations[ $st->language ][] = [ $st->string, $st->translation ];
			}
		}

		return $stringTranslations;
	}
}
