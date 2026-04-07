<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

use PLL_Translated_Object;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract class to handle the translations of objects, typically posts or terms.
 *
 * @since 0.5
 */
abstract class AbstractObjects extends AbstractSteppable {
	/**
	 * Returns the type of the object (post, term...).
	 * Must match `PLL_Translatable_Object::get_type()`.
	 *
	 * @since 0.7
	 *
	 * @return string
	 */
	abstract protected function getObjectType();

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

		foreach ( PLL()->model->languages->get_list() as $lang ) {
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
		$translated_object = PLL()->model->translatable_objects->get( $this->getObjectType() );

		if ( ! $translated_object instanceof PLL_Translated_Object ) {
			// Uh?
			return;
		}

		$translated_object->set_translation_in_mass( $translations );
	}
}
