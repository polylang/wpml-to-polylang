<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

/**
 * Handles the translations of posts.
 */
class Posts extends AbstractObjects {

	/**
	 * Returns the action name.
	 *
	 * @return string
	 */
	public function getName() {
		return 'process_posts';
	}

	/**
	 * Returns the processing message.
	 *
	 * @return string
	 */
	protected function getMessage() {
		return esc_html__( 'Processing posts languages and translations', 'wpml-to-polylang' );
	}

	/**
	 * Gets the languages term taxonomy ids related to this object type.
	 *
	 * @return int[]
	 */
	protected function getLanguageTermTaxonomyIds() {
		$languages = [];

		foreach ( PLL()->model->get_languages_list() as $lang ) {
			$languages[ $lang->slug ] = $lang->term_taxonomy_id;
		}

		return $languages;
	}

	/**
	 * Returns the number of WPML term translations.
	 *
	 * @return int
	 */
	protected function getTotal() {
		global $wpdb;

		return (int) $wpdb->get_var(
			sprintf(
				"SELECT COUNT( DISTINCT( trid ) )
				FROM {$wpdb->prefix}icl_translations
				WHERE SUBSTR( element_type, 6 ) IN ( '%s' )",
				implode( "', '", esc_sql( $this->getTranslatedPostTypes() ) )
			)
		);
	}

	/**
	 * Returns the translation taxonomy name.
	 *
	 * @return string
	 */
	protected function getTranslationTaxonomy() {
		return 'post_translations';
	}

	/**
	 * Gets the WPML term translation ids.
	 *
	 * @return int[]
	 */
	protected function getWPMLTranslationIds() {
		global $wpdb;

		$trids = $wpdb->get_results(
			sprintf(
				"SELECT DISTINCT trid
				FROM {$wpdb->prefix}icl_translations
				WHERE SUBSTR( element_type, 6 ) IN ( '%s' )
				LIMIT %d, %d",
				implode( "', '", esc_sql( $this->getTranslatedPostTypes() ) ),
				absint( $this->step * WPML_TO_POLYLANG_QUERY_BATCH_SIZE - WPML_TO_POLYLANG_QUERY_BATCH_SIZE ),
				absint( WPML_TO_POLYLANG_QUERY_BATCH_SIZE )
			)
		);

		return array_map( 'absint', $trids );
	}

	/**
	 * Gets the WPML term translations.
	 *
	 * @param int[] $trids WPML translation ids.
	 * @return \stdClass[]
	 */
	protected function getWPMLTranslations( $trids ) {
		global $wpdb;

		return $wpdb->get_results(
			sprintf(
				"SELECT DISTINCT element_id AS id, language_code, trid
				FROM {$wpdb->prefix}icl_translations AS wpml
				WHERE trid IN ( %s )",
				implode( ',', array_map( 'absint', $trids ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			)
		);
	}

	/**
	 * Gets the translated post types.
	 *
	 * @return string[]
	 */
	protected function getTranslatedPostTypes() {
		$types    = [ 'post', 'page', 'wp_block' ];
		$settings = get_option( 'icl_sitepress_settings' );

		if ( is_array( $settings ) && is_array( $settings['taxonomies_sync_option'] ) ) {
			$icl_types = array_keys( $settings['custom_posts_sync_option'] );
			$icl_types = array_filter( $icl_types, 'is_string' );
			$types     = array_merge( $types, $icl_types );
		}

		$types = array_diff( $types, [ 'wp_template' ] );

		return $types;
	}
}
