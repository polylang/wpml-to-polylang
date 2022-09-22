<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract class to handle the translations of objects, typically posts or terms.
 *
 * @since 0.5
 */
abstract class AbstractObjects extends AbstractSteppable {

	/**
	 * Gets the languages term taxonomy ids related to this object type.
	 *
	 * @since 0.5
	 *
	 * @return int[]
	 */
	abstract protected function getLanguageTermTaxonomyIds();

	/**
	 * Returns the translation taxonomy name.
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	abstract protected function getTranslationTaxonomy();

	/**
	 * Gets the WPML term translation ids.
	 *
	 * @since 0.5
	 *
	 * @return int[]
	 */
	abstract protected function getWPMLTranslationIds();

	/**
	 * Gets the WPML term translations.
	 *
	 * @since 0.5
	 *
	 * @param int[] $trids WPML translation ids.
	 * @return int[][]
	 */
	abstract protected function getWPMLTranslations( $trids );

	/**
	 * Processes the translations of this object type.
	 *
	 * @since 0.5
	 *
	 * @return void
	 */
	protected function handle() {
		$trids        = $this->getWPMLTranslationIds();
		$translations = $this->getWPMLTranslations( $trids );
		unset( $trids ); // Free some memory.

		$this->processLanguages( $translations );
		$this->processTranslations( $translations );
	}

	/**
	 * Creates the relationship between the terms and languages.
	 *
	 * @since 0.5
	 *
	 * @param int[][] $translations WPML translations.
	 * @return void
	 */
	protected function processLanguages( $translations ) {
		global $wpdb;

		$languages = $this->getLanguageTermTaxonomyIds();

		$relations = [];

		foreach ( $translations as $t ) {
			foreach ( $t as $language_code => $id ) {
				if ( ! empty( $languages[ $language_code ] ) ) {
					$relations[] = sprintf( '(%d, %d)', (int) $id, (int) $languages[ $language_code ] );
				}
			}
		}

		$relations = array_unique( $relations );

		if ( ! empty( $relations ) ) {
			$wpdb->query( "INSERT INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id) VALUES " . implode( ',', $relations ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		foreach ( PLL()->model->get_languages_list() as $lang ) {
			$lang->update_count();
		}
	}

	/**
	 * Creates translation groups.
	 *
	 * @since 0.5
	 *
	 * @param int[][] $translations WPML translations.
	 * @return void
	 */
	protected function processTranslations( $translations ) {
		global $wpdb;

		$terms = [];

		foreach ( array_keys( $translations ) as $name ) {
			$terms[] = $wpdb->prepare( '(%s, %s)', $name, $name );
		}

		$terms = array_unique( $terms );

		// Insert terms.
		if ( ! empty( $terms ) ) {
			$wpdb->query( "INSERT INTO $wpdb->terms (slug, name) VALUES " . implode( ',', $terms ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Get all terms with their term_id.
		$terms = $wpdb->get_results(
			sprintf(
				"SELECT term_id, slug FROM $wpdb->terms WHERE slug IN ( '%s' )",
				implode( "', '", esc_sql( array_keys( $translations ) ) )
			)
		);

		$tts = [];

		foreach ( $terms as $term ) {
			$tts[] = $wpdb->prepare(
				'(%d, %s, %s, %d)',
				$term->term_id,
				$this->getTranslationTaxonomy(),
				serialize( $translations[ $term->slug ] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				count( $translations[ $term->slug ] )
			);
		}

		$tts = array_unique( $tts );

		// Insert term taxonomy part of terms.
		if ( ! empty( $tts ) ) {
			$wpdb->query( "INSERT INTO $wpdb->term_taxonomy (term_id, taxonomy, description, count) VALUES " . implode( ',', $tts ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		unset( $terms, $tts ); // Free some memory.

		// Get all terms with their term taxonomy id.
		$terms = $wpdb->get_results(
			sprintf(
				"SELECT tt.term_taxonomy_id, t.slug FROM $wpdb->terms AS t
				INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
				WHERE tt.taxonomy = '%s'
				AND t.slug IN ( '%s' )",
				esc_sql( $this->getTranslationTaxonomy() ),
				implode( "', '", esc_sql( array_keys( $translations ) ) )
			)
		);

		$trs = [];

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				foreach ( $translations[ $term->slug ] as $object_id ) {
					$trs[] = sprintf( '(%d, %d)', (int) $object_id, (int) $term->term_taxonomy_id );
				}
			}
		}

		$trs = array_unique( $trs );

		// Insert term relationships.
		if ( ! empty( $trs ) ) {
			$wpdb->query( "INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id) VALUES " . implode( ',', $trs ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}
}
