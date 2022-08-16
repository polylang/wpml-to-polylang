<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the objects with no language.
 *
 * In some cases, there is no language assigned in icl_translations table,
 * but WPML displays the default language anyway.
 *
 * @since 0.5
 */
class NoLangObjects extends AbstractAction {

	/**
	 * Returns the action name.
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	public function getName() {
		return 'process_no_language_objects';
	}

	/**
	 * Returns the processing message.
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	protected function getMessage() {
		return esc_html__( 'Processing post and terms with no language', 'wpml-to-polylang' );
	}

	/**
	 * Processes the objects with no language.
	 *
	 * @since 0.5
	 *
	 * @return void
	 */
	protected function handle() {
		$wpml_settings = get_option( 'icl_sitepress_settings' );

		if ( ! is_array( $wpml_settings ) ) {
			return; // Something's wrong.
		}

		$nolang = PLL()->model->get_objects_with_no_lang( WPML_TO_POLYLANG_QUERY_BATCH_SIZE );

		while ( $nolang ) {
			if ( ! empty( $nolang['posts'] ) ) {
				PLL()->model->set_language_in_mass( 'post', $nolang['posts'], $wpml_settings['default_language'] );
			}

			if ( ! empty( $nolang['terms'] ) ) {
				PLL()->model->set_language_in_mass( 'term', $nolang['terms'], $wpml_settings['default_language'] );
			}

			$nolang = PLL()->model->get_objects_with_no_lang( WPML_TO_POLYLANG_QUERY_BATCH_SIZE );
		}
	}
}
