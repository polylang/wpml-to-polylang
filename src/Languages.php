<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

/**
 * Handles the languages creation.
 */
class Languages extends AbstractAction {

	/**
	 * Returns the action name.
	 *
	 * @return string
	 */
	public function getName() {
		return 'create_languages';
	}

	/**
	 * Returns the processing message.
	 *
	 * @return string
	 */
	protected function getMessage() {
		return esc_html__( 'Creating languages', 'wpml-to-polylang' );
	}

	/**
	 * Creates the languages.
	 *
	 * @return void
	 */
	protected function handle() {
		$predefinedLanguages = include POLYLANG_DIR . '/settings/languages.php';

		$wpmlLanguages = $this->getWPMLLanguages();
		$wpmlLanguages = $this->orderLanguages( $wpmlLanguages );

		foreach ( $wpmlLanguages as $lang ) {
			$lang['term_group']     = 0;
			$lang['no_default_cat'] = 1; // Prevent the creation of a new default category.

			// We need a flag and can be more exhaustive for the rtl languages list.
			$lang['rtl']  = isset( $predefinedLanguages[ $lang['locale'] ]['dir'] ) && 'rtl' === $predefinedLanguages[ $lang['locale'] ]['dir'] ? 1 : 0;
			$lang['flag'] = isset( $predefinedLanguages[ $lang['locale'] ]['flag'] ) ? $predefinedLanguages[ $lang['locale'] ]['flag'] : '';

			PLL()->model->add_language( $lang );
		}

		$this->cleanup();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function addHooks() {
		parent::addHooks();

		add_filter( 'filterModel', [ $this, 'filterModel' ] );
	}

	/**
	 * Uses PLL_Admin_Model to be able to create languages.
	 *
	 * @return string
	 */
	public function filterModel() {
		return 'PLL_Admin_Model';
	}

	/**
	 * Deletes the translation group of the default category to avoid a conflict later.
	 *
	 * @return void
	 */
	protected function cleanup() {
		$termIds = get_terms(
			[
				'taxonomy'   => 'term_translations',
				'hide_empty' => false,
				'fields'     => 'ids',
			]
		);

		if ( is_array( $termIds ) ) {
			foreach ( $termIds as $termId ) {
				wp_delete_term( $termId, 'term_translations' );
			}
		}

		PLL()->model->clean_languages_cache(); // Update the languages list.
	}

	/**
	 * Gets the list of WPML languages from the database.
	 *
	 * @return string[][] {
	 *   An array of languages.
	 *
	 *   @type string $slug   Language code.
	 *   @type string $locale Locale.
	 *   @type string $name   Native language name.
	 * }
	 */
	protected function getWPMLLanguages() {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT l.code AS slug, l.default_locale AS locale, lt.name
			FROM {$wpdb->prefix}icl_languages AS l
			INNER JOIN {$wpdb->prefix}icl_languages_translations AS lt ON l.code = lt.language_code
			WHERE l.active = 1 AND lt.language_code = lt.display_language_code",
			ARRAY_A
		);
	}

	/**
	 * Mimics how WPML orders the languages.
	 *
	 * @see SitePress::order_languages().
	 *
	 * @param string[][] $languages The list of WPML languages.
	 * @return string[][] Ordered list of languages.
	 */
	protected function orderLanguages( $languages ) {
		$orderedLanguages = [];

		$settings = get_option( 'icl_sitepress_settings' );

		if ( is_array( $settings ) && is_array( $settings['languages_order'] ) ) {
			foreach ( $settings['languages_order'] as $code ) {
				if ( isset( $languages[ $code ] ) ) {
					$orderedLanguages[ $code ] = $languages[ $code ];
					unset( $languages[ $code ] );
				}
			}
		}

		if ( ! empty( $languages ) ) {
			foreach ( $languages as $code => $lang ) {
				$orderedLanguages[ $code ] = $lang;
			}
		}

		return $orderedLanguages;
	}
}