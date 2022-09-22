<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the options.
 *
 * @since 0.5
 */
class Options extends AbstractAction {

	/**
	 * Returns the action name.
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	public function getName() {
		return 'process_options';
	}

	/**
	 * Returns the processing message.
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	protected function getMessage() {
		return esc_html__( 'Processing options', 'wpml-to-polylang' );
	}

	/**
	 * Processes the options.
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

		$options = get_option( 'polylang' );

		if ( ! is_array( $options ) ) {
			$options = [];
		}

		$options['rewrite']       = 1; // Remove /language/ in permalinks.
		$options['hide_default']  = 1; // Remove URL language information for default language.
		$options['redirect_lang'] = 1; // Redirect the language page to the homepage.

		// Default language.
		$options['default_lang'] = $wpml_settings['default_language'];

		// Urls modifications.
		switch ( $wpml_settings['language_negotiation_type'] ) {
			case 2:
				$options['force_lang'] = 3;
				break;
			case 1:
			case 3: // We do not support the language added as a parameter except for plain permalinks.
			default:
				$options['force_lang'] = 1;
				break;
		}

		// Domains.
		$options['domains'] = isset( $wpml_settings['language_domains'] ) ? $wpml_settings['language_domains'] : [];

		// Post types.
		if ( ! empty( $wpml_settings['custom_posts_sync_option'] ) ) {
			$post_types = array_keys( array_filter( $wpml_settings['custom_posts_sync_option'] ) );
			$post_types = array_diff( $post_types, get_post_types( [ '_builtin' => true ] ) );

			$options['post_types']    = $post_types;
			$options['media_support'] = (int) ! empty( $wpml_settings['custom_posts_sync_option']['attachment'] );
		}

		// Taxonomies.
		if ( ! empty( $wpml_settings['taxonomies_sync_option'] ) ) {
			$taxonomies = array_keys( array_filter( $wpml_settings['taxonomies_sync_option'] ) );
			$taxonomies = array_diff( $taxonomies, get_taxonomies( [ '_builtin' => true ] ) );

			$options['taxonomies'] = $taxonomies;
		}

		// Sync.
		$sync = [
			'sync_page_ordering'  => 'menu_order',
			'sync_page_parent'    => 'post_parent',
			'sync_page_template'  => '_wp_page_template',
			'sync_ping_status'    => 'ping_status',
			'sync_comment_status' => 'comment_status',
			'sync_sticky_flag'    => 'sticky_posts',
		];

		$options['sync'] = [];
		foreach ( $sync as $wpml_opt => $pll_opt ) {
			if ( ! empty( $wpml_settings[ $wpml_opt ] ) ) {
				$options['sync'][] = $pll_opt;
			}
		}

		update_option( 'polylang', $options );

		// Default category in default language.
		update_option( 'default_category', (int) $wpml_settings['default_categories'][ $wpml_settings['default_language'] ] );

		// And finally flush rewrite rules as WPML doesn't use them but we do.
		flush_rewrite_rules();
	}
}
