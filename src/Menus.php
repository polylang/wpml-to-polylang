<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the menus.
 *
 * @since 0.5
 */
class Menus extends AbstractAction {

	/**
	 * Returns the action name.
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	public function getName() {
		return 'process_menus';
	}

	/**
	 * Returns the processing message.
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	protected function getMessage() {
		return esc_html__( 'Processing navigation menus', 'wpml-to-polylang' );
	}

	/**
	 * Processes the menus.
	 *
	 * @since 0.5
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

		$tr_locations = [];

		// Associate translation ids to nav menu locations.
		foreach ( $locations as $location => $loc_menu_id ) {
			if ( empty( $loc_menu_id ) ) {
				continue; // This eliminates our translated locations.
			}

			foreach ( $translations as $trid => $menus ) {
				foreach ( $menus as $menu_id ) {
					if ( $menu_id === $loc_menu_id ) {
						$tr_locations[ $trid ] = $location;
					}
				}
			}
		}

		// Build nav_menus option.
		foreach ( $translations as $trid => $menus ) {
			if ( isset( $tr_locations[ $trid ] ) ) {
				foreach ( $menus as $lang => $menu_id ) {
					$options['nav_menus'][ $theme ][ $tr_locations[ $trid ] ][ $lang ] = $menu_id;
				}
			}
		}

		update_option( 'polylang', $options );
	}

	/**
	 * Gets the WPML menu translations.
	 *
	 * @since 0.5
	 *
	 * @return int[][]
	 */
	protected function getWPMLTranslations() {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT DISTINCT tt.term_id AS id, wpml.language_code, wpml.trid
			FROM {$wpdb->term_taxonomy} AS tt
			INNER JOIN {$wpdb->prefix}icl_translations AS wpml
			ON wpml.element_id = tt.term_taxonomy_id
			AND wpml.element_type = CONCAT('tax_', tt.taxonomy)
			WHERE wpml.element_type = 'tax_nav_menu'"
		);

		$translations = [];

		foreach ( $results as $mt ) {
			if ( ! empty( $mt->trid ) && ! empty( $mt->language_code ) && ! empty( $mt->id ) ) {
				$translations[ $mt->trid ][ $mt->language_code ] = (int) $mt->id;
			}
		}

		return $translations;
	}
}
