<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

/**
 * Handles the menus.
 */
class Menus extends AbstractAction {

	/**
	 * Returns the action name.
	 *
	 * @return string
	 */
	public function getName() {
		return 'process_menus';
	}

	/**
	 * Returns the processing message.
	 *
	 * @return string
	 */
	protected function getMessage() {
		return esc_html__( 'Processing navigation menus', 'wpml-to-polylang' );
	}

	/**
	 * Processes the menus.
	 *
	 * @return void
	 */
	protected function handle() {
		$options = get_option( 'polylang' );

		if ( ! is_array( $options ) ) {
			$options = [];
		}

		$theme        = get_option( 'stylesheet' );
		$locations    = get_nav_menu_locations();
		$translations = $this->getWPMLTranslations();

		if ( empty( $locations ) || empty( $translations ) ) {
			return;
		}

		foreach ( array_keys( $locations ) as $loc ) {
			foreach ( $translations as $translation ) {
				$options['nav_menus'][ $theme ][ $loc ][ $translation->language_code ] = $translation->term_id;
			}
		}

		update_option( 'polylang', $options );
	}

	/**
	 * Gets the WPML menu translations.
	 *
	 * @return \stdClass[]
	 */
	protected function getWPMLTranslations() {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT tt.term_id, wpml.language_code
			FROM {$wpdb->term_taxonomy} AS tt
			INNER JOIN {$wpdb->prefix}icl_translations AS wpml
			ON wpml.element_id = tt.term_taxonomy_id
			AND wpml.element_type = CONCAT('tax_', tt.taxonomy)
			WHERE wpml.element_type = 'tax_nav_menu'"
		);
	}
}
