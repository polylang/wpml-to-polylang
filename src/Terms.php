<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the translations of terms.
 *
 * @since 0.5
 */
class Terms extends AbstractObjects {

	/**
	 * Returns the action name.
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	public function getName() {
		return 'process_terms';
	}

	/**
	 * Returns the processing message.
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	protected function getMessage() {
		return esc_html__( 'Processing terms language and translations', 'wpml-to-polylang' );
	}

	/**
	 * Gets the languages term taxonomy ids related to this object type.
	 *
	 * @since 0.5
	 *
	 * @return int[]
	 */
	protected function getLanguageTermTaxonomyIds() {
		$languages = [];

		foreach ( PLL()->model->get_languages_list() as $lang ) {
			$languages[ $lang->slug ] = $lang->get_tax_prop( 'term_language', 'term_taxonomy_id' );
		}

		return $languages;
	}

	/**
	 * Returns the number of WPML term translations.
	 *
	 * @since 0.5
	 *
	 * @return int
	 */
	protected function getTotal() {
		global $wpdb;

		return (int) $wpdb->get_var(
			sprintf(
				"SELECT COUNT( DISTINCT( wpml.trid ) )
				FROM {$wpdb->term_taxonomy} AS tt
				INNER JOIN {$wpdb->prefix}icl_translations AS wpml
				ON wpml.element_id = tt.term_taxonomy_id
				AND wpml.element_type = CONCAT( 'tax_', tt.taxonomy )
				WHERE tt.taxonomy IN ( '%s' )",
				implode( "', '", esc_sql( $this->getTranslatedTaxonomies() ) )
			)
		);
	}

	/**
	 * Returns the translation taxonomy name.
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	protected function getTranslationTaxonomy() {
		return 'term_translations';
	}

	/**
	 * Gets the WPML term translation ids.
	 *
	 * @since 0.5
	 *
	 * @return int[]
	 */
	protected function getWPMLTranslationIds() {
		global $wpdb;

		$trids = $wpdb->get_col(
			sprintf(
				"SELECT DISTINCT wpml.trid
				FROM {$wpdb->term_taxonomy} AS tt
				INNER JOIN {$wpdb->prefix}icl_translations AS wpml
				ON wpml.element_id = tt.term_taxonomy_id
				AND wpml.element_type = CONCAT( 'tax_', tt.taxonomy )
				WHERE tt.taxonomy IN ( '%s' )
				LIMIT %d, %d",
				implode( "', '", esc_sql( $this->getTranslatedTaxonomies() ) ),
				absint( $this->step * WPML_TO_POLYLANG_QUERY_BATCH_SIZE - WPML_TO_POLYLANG_QUERY_BATCH_SIZE ),
				absint( WPML_TO_POLYLANG_QUERY_BATCH_SIZE )
			)
		);

		return array_map( 'absint', $trids );
	}

	/**
	 * Gets the WPML term translations.
	 *
	 * @since 0.5
	 *
	 * @param int[] $trids WPML translation ids.
	 * @return int[][]
	 */
	protected function getWPMLTranslations( $trids ) {
		global $wpdb;

		$results = $wpdb->get_results(
			sprintf(
				"SELECT DISTINCT tt.term_id AS id, wpml.language_code, wpml.trid
				FROM {$wpdb->term_taxonomy} AS tt
				INNER JOIN {$wpdb->prefix}icl_translations AS wpml
				ON wpml.element_id = tt.term_taxonomy_id
				AND wpml.element_type = CONCAT( 'tax_', tt.taxonomy )
				WHERE wpml.trid IN ( %s )",
				implode( ',', array_map( 'absint', $trids ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			)
		);

		$translations = [];

		// Remove empty ids and group translations by translation group.
		foreach ( $results as $t ) {
			if ( ! empty( $t->trid ) && ! empty( $t->language_code ) && ! empty( $t->id ) ) {
				$translations[ 'pll_wpml_' . $t->trid ][ $t->language_code ] = (int) $t->id;
			}
		}

		return $translations;
	}

	/**
	 * Gets the translated taxonomies.
	 *
	 * @since 0.5
	 *
	 * @return string[]
	 */
	protected function getTranslatedTaxonomies() {
		$taxonomies = [ 'category', 'post_tag' ];
		$settings   = get_option( 'icl_sitepress_settings' );

		if ( is_array( $settings ) && is_array( $settings['taxonomies_sync_option'] ) ) {
			$icl_taxonomies = array_keys( $settings['taxonomies_sync_option'] );
			$icl_taxonomies = array_filter( $icl_taxonomies, 'is_string' );
			$taxonomies     = array_merge( $taxonomies, $icl_taxonomies );
		}

		$taxonomies = array_diff( $taxonomies, [ 'wp_theme', 'wp_template_part_area' ] );

		return $taxonomies;
	}
}
