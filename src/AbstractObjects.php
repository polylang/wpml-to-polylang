<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

/**
 * Abstract class to handle the translations of objects, typically posts or terms.
 */
abstract class AbstractObjects extends AbstractSteppable {

	/**
	 * Gets the languages term taxonomy ids related to this object type.
	 *
	 * @return int[]
	 */
	abstract protected function getLanguageTermTaxonomyIds();

	/**
	 * Returns the translation taxonomy name.
	 *
	 * @return string
	 */
	abstract protected function getTranslationTaxonomy();

	/**
	 * Gets the WPML term translation ids.
	 *
	 * @return int[]
	 */
	abstract protected function getWPMLTranslationIds();

	/**
	 * Gets the WPML term translations.
	 *
	 * @param int[] $trids WPML translation ids.
	 * @return \stdClass[]
	 */
	abstract protected function getWPMLTranslations( $trids );

	/**
	 * Processes the translations of this object type.
	 *
	 * @return void
	 */
	protected function handle() {
		$trids        = $this->getWPMLTranslationIds();
		$translations = $this->getWPMLTranslations( $trids );
		$this->processLanguages( $translations );
		$this->processTranslations( $translations );
	}

	/**
	 * Creates the relationship between the terms and languages.
	 *
	 * @param \stdClass[] $translations WPML translations.
	 * @return void
	 */
	protected function processLanguages( $translations ) {
		global $wpdb;

		$languages = $this->getLanguageTermTaxonomyIds();

		$relations = [];

		foreach ( $translations as $t ) {
			if ( ! empty( $t->language_code ) && ! empty( $languages[ $t->language_code ] ) ) {
				$relations[] = sprintf( '(%d, %d)', (int) $t->id, (int) $languages[ $t->language_code ] );
			}
		}

		$relations = array_unique( $relations );

		$wpdb->query( "INSERT INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id) VALUES " . implode( ',', $relations ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Creates translation groups.
	 *
	 * @param \stdClass[] $translations WPML translations.
	 * @return void
	 */
	protected function processTranslations( $translations ) {
		global $wpdb;

		$groupedTranslations = [];

		// Group translations by translation group.
		foreach ( $translations as $t ) {
			$groupedTranslations[ 'pll_wpml_' . $t->trid ][ $t->language_code ] = (int) $t->id;
		}

		$terms = [];

		foreach ( $groupedTranslations as $name => $t ) {
			$terms[] = sprintf( '(%s, %s)', $name, $name );
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
				implode( ',', esc_sql( array_keys( $groupedTranslations ) ) )
			)
		);

		$tts = [];

		foreach ( $terms as $term ) {
			$tts[] = $wpdb->prepare(
				'(%d, %s, %s, %d)',
				$term->term_id,
				$this->getTranslationTaxonomy(),
				serialize( $groupedTranslations[ $term->slug ] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				count( $groupedTranslations[ $term->slug ] )
			);
		}

		$tts = array_unique( $tts );

		// Insert term taxonomy par of terms.
		if ( ! empty( $tts ) ) {
			$wpdb->query( "INSERT INTO $wpdb->term_taxonomy (term_id, taxonomy, description, count) VALUES " . implode( ',', $tts ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Get all terms with their term taxonomy id.
		$terms = get_terms(
			[
				'taxonomy'   => $this->getTranslationTaxonomy(),
				'hide_empty' => false,
			]
		);

		$trs = [];

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				foreach ( $groupedTranslations[ $term->slug ] as $object_id ) {
					$trs[] = $wpdb->prepare( '(%d, %d)', $object_id, $term->term_taxonomy_id );
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
