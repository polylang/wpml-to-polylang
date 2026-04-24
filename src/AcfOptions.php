<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

defined( 'ABSPATH' ) || exit;

/**
 * Migrates ACF options-page values that were stored with the Polylang language SLUG
 * as a prefix (e.g. `options_fr_my_field`) into the LOCALE-prefix format expected by
 * "ACF Options For Polylang" v2.0+ (e.g. `options_fr_FR_my_field`).
 *
 * Background:
 * Older versions of the "ACF Options For Polylang" plugin appended the Polylang
 * language SLUG to the ACF post_id (options_fr, options_de, …). Version 2.0 switched
 * to the LOCALE (options_fr_FR, options_de_DE, …) which is also what ACF's own
 * Polylang integration uses for the built-in "options" page.
 *
 * This class reads every `options_{slug}_*` and `_options_{slug}_*` row in wp_options
 * and writes a copy under `options_{locale}_*` / `_options_{locale}_*`.
 * Existing locale-format entries are never overwritten.
 *
 * @since 0.7
 */
class AcfOptions extends AbstractAction {

	/**
	 * Returns the action name.
	 *
	 * @since 0.7
	 *
	 * @return string
	 */
	public function getName() {
		return 'process_acf_options';
	}

	/**
	 * Returns the processing message.
	 *
	 * @since 0.7
	 *
	 * @return string
	 */
	protected function getMessage() {
		return esc_html__( 'Migrating ACF options page translations', 'wpml-to-polylang' );
	}

	/**
	 * Copies ACF options from slug-prefix format to locale-prefix format.
	 *
	 * @since 0.7
	 *
	 * @return void
	 */
	protected function handle() {
		global $wpdb;

		$languages = PLL()->model->get_languages_list();

		if ( empty( $languages ) ) {
			return;
		}

		foreach ( $languages as $language ) {
			if ( $language->is_default ) {
				continue;
			}

			$slug   = $language->slug;
			$locale = $language->locale;

			if ( $slug === $locale ) {
				// Nothing to migrate when slug and locale are identical.
				continue;
			}

			// Fetch all options stored with the slug-prefix format (values + field refs).
			$like_value = $wpdb->esc_like( "options_{$slug}_" ) . '%';
			$like_ref   = $wpdb->esc_like( "_options_{$slug}_" ) . '%';

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- values are esc_like-escaped above.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value, autoload
					 FROM {$wpdb->options}
					 WHERE option_name LIKE %s OR option_name LIKE %s",
					$like_value,
					$like_ref
				)
			);

			if ( empty( $rows ) ) {
				continue;
			}

			foreach ( $rows as $row ) {
				$old_name = $row->option_name;

				// Replace the first occurrence of _{slug}_ with _{locale}_.
				$new_name = $this->renameOption( $old_name, $slug, $locale );

				if ( null === $new_name || $new_name === $old_name ) {
					continue;
				}

				// Never overwrite an existing locale-format entry.
				if ( false !== get_option( $new_name ) ) {
					continue;
				}

				add_option( $new_name, maybe_unserialize( $row->option_value ), '', 'no' );
			}
		}
	}

	/**
	 * Renames an option key from slug-prefix to locale-prefix format.
	 *
	 * Handles both the value entry (`options_{slug}_{rest}`) and the ACF field
	 * reference entry (`_options_{slug}_{rest}`).
	 *
	 * @since 0.7
	 *
	 * @param string $option_name Original option name.
	 * @param string $slug        Polylang language slug (e.g. 'fr').
	 * @param string $locale      Polylang language locale (e.g. 'fr_FR').
	 * @return string|null New option name, or null if no substitution was needed.
	 */
	protected function renameOption( $option_name, $slug, $locale ) {
		foreach ( [ "options_{$slug}_", "_options_{$slug}_" ] as $slug_prefix ) {
			if ( 0 === strpos( $option_name, $slug_prefix ) ) {
				$locale_prefix = str_replace( "_{$slug}_", "_{$locale}_", $slug_prefix );
				return $locale_prefix . substr( $option_name, strlen( $slug_prefix ) );
			}
		}

		return null;
	}
}
