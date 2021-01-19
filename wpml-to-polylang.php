<?php
/**
 * WPML to Polylang
 *
 * @package              wpml-to-polylang
 * @author               WP SYNTEX
 * @license              GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin name:          WPML to Polylang
 * Plugin URI:           https://polylang.pro
 * Description:          Import multilingual data from WPML into Polylang
 * Version:              0.4
 * Requires at least:    4.9
 * Requires PHP:         5.6
 * Author:               WP SYNTEX
 * Author URI:           https://polylang.pro
 * Text Domain:          wpml-to-polylang
 * Domain Path:          /languages
 * License:              GPL v3 or later
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * Copyright 2013-2020 Frédéric Demarle
 * Copyright 2021 WP SYNTEX
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * A class to manage migration from WPML to Polylang.
 *
 * @since 0.1
 *
 * @see http://wpml.org/documentation/support/wpml-tables/
 */
class WPML_To_Polylang {

	/**
	 * @var PLL_Admin_Model
	 */
	protected $model;

	/**
	 * WPML settings.
	 *
	 * @var array
	 */
	protected $icl_settings;

	/**
	 * Constructor
	 *
	 * @since 0.1
	 */
	public function __construct() {
		// Adds the link to the languages panel in the WordPress admin menu.
		add_action( 'admin_menu', array( $this, 'add_menus' ) );

		if ( is_admin() && isset( $_GET['page'] ) && 'wpml-importer' === $_GET['page'] && class_exists( 'PLL_Admin_Model' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_filter( 'pll_model', array( $this, 'pll_model' ) );
			$this->icl_settings = get_option( 'icl_sitepress_settings' );
		}
	}

	/**
	 * Uses PLL_Admin_Model to be able to create languages.
	 *
	 * @since 0.2
	 *
	 * @return string
	 */
	public function pll_model() {
		return 'PLL_Admin_Model';
	}

	/**
	 * Adds the link to the languages panel in the WordPress admin menu.
	 *
	 * @since 0.1
	 *
	 * @return void
	 */
	public function add_menus() {
		load_plugin_textdomain( 'wpml-to-polylang', false, basename( dirname( __FILE__ ) ) . '/languages' ); // Plugin i18n.
		$title = __( 'WPML importer', 'wpml-to-polylang' );
		add_submenu_page( 'tools.php', $title, $title, 'manage_options', 'wpml-importer', array( $this, 'tools_page' ) );
	}

	/**
	 * Displays the import page.
	 * Processes the import action.
	 *
	 * @since 0.1
	 *
	 * @return void
	 */
	public function tools_page() {
		?>
		<div class="wrap">
			<h2> <?php esc_html_e( 'WPML Importer', 'wpml-to-polylang' ); ?></h2>
			<?php

			if ( isset( $_POST['pll_action'] ) && 'import' === $_POST['pll_action'] ) {
				check_admin_referer( 'wpml-importer', '_wpnonce_wpml-importer' );
				$this->import();
				?>
				<p><?php esc_html_e( 'Import from WPML to Polylang should have been successul!', 'wpml-to-polylang' ); ?></p>
				<?php
			} else {
				global $sitepress, $wp_version;

				$min_wp_version  = '4.9';
				$min_pll_version = '2.8';
				$checks = array();

				$checks[] = array(
					/* translators: %s is the WordPress version */
					sprintf( __( 'You are using WordPress %s or later', 'wpml-to-polylang' ), $min_wp_version ),
					version_compare( $wp_version, $min_wp_version, '>=' ) ? 1 : 0,
				);

				$checks[] = array(
					__( 'WPML is installed on this website', 'wpml-to-polylang' ),
					false !== get_option( 'icl_sitepress_settings' ) ? 1 : 0,
				);

				$checks[] = array(
					__( 'WPML is deactivated', 'wpml-to-polylang' ),
					empty( $sitepress ) ? 1 : 0,
				);

				$checks[] = array(
					/* translators: %s is the Polylang version */
					sprintf( __( 'Polylang %s or later is activated', 'wpml-to-polylang' ), $min_pll_version ),
					defined( 'POLYLANG_VERSION' ) && version_compare( POLYLANG_VERSION, $min_pll_version, '>=' ) ? 1 : 0,
				);

				if ( $checks[3][1] ) {
					$this->model = $GLOBALS['polylang']->model;

					$checks[] = array(
						__( 'No language has been created with Polylang', 'wpml-to-polylang' ),
						$this->model->get_languages_list() ? 0 : 1,
					);
				}

				// html form.
				?>
				<div class="form-wrap">
					<form id="import" method="post" action="admin.php?page=wpml-importer">
					<input type="hidden" name="pll_action" value="import" />
					<?php wp_nonce_field( 'wpml-importer', '_wpnonce_wpml-importer' ); ?>
					<table class="form-table">
					<?php
					foreach ( $checks as $check ) {
						printf(
							'<tr><th style="width:300px">%s</th><td style="color:%s">%s</td></tr>',
							esc_html( $check[0] ),
							$check[1] ? 'green' : 'red',
							esc_html( $check[1] ? __( 'OK', 'wpml-to-polylang' ) : __( 'KO', 'wpml-to-polylang' ) )
						);

						if ( ! $check[1] ) {
							$deactivated = true;
						}
					}
					?>
					</table>
					<?php
					$attr = empty( $deactivated ) ? array() : array( 'disabled' => 'disabled' );
					submit_button( __( 'Import', 'wpml-to-polylang' ), 'primary', 'submit', true, $attr ); // Since WP 3.1.
					?>
					</form>
				</div><!-- form-wrap -->
				<?php
			}
			?>
		</div><!-- wrap -->
		<?php
	}

	/**
	 * Dispatches the different import steps.
	 *
	 * @since 0.1
	 *
	 * @return void
	 */
	public function import() {
		global $wpdb;

		set_time_limit( 0 );

		$this->model = $GLOBALS['polylang']->model;
		$this->add_languages();

		// Get WPML translations.
		$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}icl_translations" );

		/*
		 * Get the correspondance between term_taxonomy_id and term_id ( duplicates are discarded ).
		 * Required as WPML stores term_taxonomy_id in icl_translations while Polylang translates term_id.
		 * Thanks to Nickness. See http://wordpress.org/support/topic/wpml-languages-import-is-broken-with-last-polylang-update-15-16
		 */
		$_taxonomies = array( 'category', 'post_tag', 'nav_menu' );
		if ( ! empty( $this->icl_settings['taxonomies_sync_option'] ) ) {
			$_taxonomies = array_merge( $_taxonomies, array_keys( array_filter( $this->icl_settings['taxonomies_sync_option'] ) ) );
		}

		$taxonomies = array();
		foreach ( $_taxonomies as $tax ) {
			$taxonomies[] = $wpdb->prepare( '%s', $tax );
		}
		$term_ids = $wpdb->get_results( "SELECT term_taxonomy_id, term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN (" . implode( ', ', $taxonomies ) . ')', OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Migrate languages and translations.
		$this->process_post_term_languages( $results, $term_ids );
		$this->process_post_term_translations( $results, $term_ids );

		// In some cases, there is no language assigned in icl_translations table, but WPML displays the default language anyway.
		$nolang = $this->model->get_objects_with_no_lang();
		if ( $nolang ) {
			if ( ! empty( $nolang['posts'] ) ) {
				$this->model->set_language_in_mass( 'post', $nolang['posts'], $this->icl_settings['default_language'] );
			}
			if ( ! empty( $nolang['terms'] ) ) {
				$this->model->set_language_in_mass( 'term', $nolang['terms'], $this->icl_settings['default_language'] );
			}
		}

		// Migrate strings translations and options.
		$this->process_strings_translations();
		$this->process_options();

		// Make sure that the page on front and page for posts are correctly included in the cached language objects.
		if ( 'page' === get_option( 'show_on_front' ) ) {
			wp_cache_delete( get_option( 'page_on_front' ), 'language_relationships' );
			wp_cache_delete( get_option( 'page_on_front' ), 'post_translations_relationships' );
			wp_cache_delete( get_option( 'page_for_posts' ), 'language_relationships' );
			wp_cache_delete( get_option( 'page_for_posts' ), 'post_translations_relationships' );
			$this->model->clean_languages_cache();
		}

		flush_rewrite_rules();
	}

	/**
	 * Creates the Polylang languages.
	 *
	 * @since 0.1
	 *
	 * @return void
	 */
	public function add_languages() {
		global $wpdb;

		// Get Polylang predefined languages list.
		$languages = include POLYLANG_DIR . '/settings/languages.php';

		// Get WPML languages.
		$wpml_languages = $wpdb->get_results(
			"SELECT l.code AS slug, l.default_locale AS locale, lt.name
			FROM {$wpdb->prefix}icl_languages AS l
			INNER JOIN {$wpdb->prefix}icl_languages_translations AS lt ON l.code = lt.language_code
			WHERE l.active = 1 AND lt.language_code = lt.display_language_code",
			ARRAY_A
		);

		foreach ( $wpml_languages as $lang ) {
			// FIXME WPML defines a language order ( since 2.8.1 ) http://wpml.org/2013/04/wpml-2-8/.
			// This is lost as I don't know how it is stored.
			$lang['term_group'] = 0;
			$lang['no_default_cat'] = 1; // Prevent the creation of a new default category.

			// We need a flag and can be more exhaustive for the rtl languages list.
			$lang['rtl'] = 'rtl' === $languages[ $lang['locale'] ]['dir'] ? 1 : 0;
			$lang['flag'] = $languages[ $lang['locale'] ]['flag'];

			$this->model->add_language( $lang );
		}

		/** @var int[] */
		$term_ids = get_terms( 'term_translations', array( 'hide_empty' => false, 'fields' => 'ids' ) );
		if ( is_array( $term_ids ) ) {
			// Delete the translation group of the default category to avoid a conflict later.
			foreach ( $term_ids as $term_id ) {
				wp_delete_term( $term_id, 'term_translations' );
			}
		}

		$this->model->clean_languages_cache(); // Update the languages list.
	}

	/**
	 * Assigns languages to posts and terms.
	 *
	 * @since 0.1
	 *
	 * @param stdClass[] $results  icl_translations table entries.
	 * @param stdClass[] $term_ids Correspondances between term_id and term_taxonomy_id.
	 * @return void
	 */
	public function process_post_term_languages( $results, $term_ids ) {
		global $wpdb;

		$languages = array();
		$post_languages = array();
		$term_languages = array();

		$default_cat = (int) get_option( 'default_category' );

		foreach ( $this->model->get_languages_list() as $lang ) {
			$languages[ $lang->slug ] = $lang;
		}

		// Posts and terms languages.
		foreach ( $results as $r ) {
			if ( ! empty( $r->language_code ) && ! empty( $languages[ $r->language_code ] ) ) {
				if ( 0 === strpos( $r->element_type, 'post_' ) && $this->is_translated_post_type( substr( $r->element_type, 5 ) ) ) {
					$post_languages[] = $wpdb->prepare( '(%d, %d)', (int) $r->element_id, (int) $languages[ $r->language_code ]->term_taxonomy_id );
				}

				if ( 0 === strpos( $r->element_type, 'tax_' ) && $this->is_translated_taxonomy( substr( $r->element_type, 4 ) ) && (int) $term_ids[ $r->element_id ]->term_id !== $default_cat ) {
					$term_languages[] = $wpdb->prepare( '(%d, %d)', (int) $term_ids[ $r->element_id ]->term_id, (int) $languages[ $r->language_code ]->tl_term_taxonomy_id );
				}
			}
		}

		$post_languages = array_unique( $post_languages );

		if ( ! empty( $post_languages ) ) {
			$wpdb->query( "INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id) VALUES " . implode( ',', $post_languages ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$term_languages = array_unique( $term_languages );

		if ( ! empty( $term_languages ) ) {
			$wpdb->query( "INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id) VALUES " . implode( ',', $term_languages ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		foreach ( $this->model->get_languages_list() as $lang ) {
			$lang->update_count();
		}
	}

	/**
	 * Creates translations groups.
	 *
	 * @since 0.1
	 *
	 * @param stdClass[] $results  icl_translations table entries.
	 * @param stdClass[] $term_ids Correspondances between term_id and term_taxonomy_id.
	 * @return void
	 */
	public function process_post_term_translations( $results, $term_ids ) {
		global $wpdb;

		$languages = array();
		$icl_translations = array();

		foreach ( $this->model->get_languages_list() as $lang ) {
			$languages[ $lang->slug ] = $lang;
		}

		// Arrange translations in a convenient way.
		foreach ( $results as $r ) {
			if ( ! empty( $r->language_code ) && ! empty( $languages[ $r->language_code ] ) ) {
				if ( 'tax_nav_menu' === $r->element_type && ! empty( $term_ids[ $r->element_id ] ) ) {
					$icl_translations['nav_menu'][ $r->trid ][ $r->language_code ] = (int) $term_ids[ $r->element_id ]->term_id;
				}

				if ( 0 === strpos( $r->element_type, 'post_' ) && $this->is_translated_post_type( substr( $r->element_type, 5 ) ) ) {
					$icl_translations['post'][ $r->trid ][ $r->language_code ] = (int) $r->element_id;
				}

				if ( 0 === strpos( $r->element_type, 'tax_' ) && $this->is_translated_taxonomy( substr( $r->element_type, 4 ) ) && ! empty( $term_ids[ $r->element_id ] ) ) {
					$icl_translations['term'][ $r->trid ][ $r->language_code ] = (int) $term_ids[ $r->element_id ]->term_id;
				}
			}
		}

		foreach ( array( 'post', 'term' ) as $type ) {
			$terms = array();
			$slugs = array();
			$description = array();
			$count = array();
			$tts = array();
			$trs = array();

			if ( empty( $icl_translations[ $type ] ) ) {
				continue;
			}

			foreach ( $icl_translations[ $type ] as $t ) {
				$t = array_filter( $t ); // It looks like WPML can have 0 as translation id.
				if ( empty( $t ) ) {
					continue;
				}

				$term = uniqid( 'pll_' ); // The term name.
				$terms[] = $wpdb->prepare( '(%s, %s)', $term, $term );
				$slugs[] = $wpdb->prepare( '%s', $term );
				$description[ $term ] = serialize( $t );
				$count[ $term ] = count( $t );
			}

			$terms = array_unique( $terms );

			// Insert terms.
			if ( ! empty( $terms ) ) {
				$wpdb->query( "INSERT INTO $wpdb->terms (slug, name) VALUES " . implode( ',', $terms ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}

			// Get all terms with their term_id.
			$terms = $wpdb->get_results( "SELECT term_id, slug FROM $wpdb->terms WHERE slug IN (" . implode( ',', $slugs ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			// Prepare terms taxonomy relationship.
			foreach ( $terms as $term ) {
				$tts[] = $wpdb->prepare( '(%d, %s, %s, %d)', $term->term_id, $type . '_translations', $description[ $term->slug ], $count[ $term->slug ] );
			}
			$tts = array_unique( $tts );

			// Insert term_taxonomy.
			if ( ! empty( $tts ) ) {
				$wpdb->query( "INSERT INTO $wpdb->term_taxonomy (term_id, taxonomy, description, count) VALUES " . implode( ',', $tts ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}

			// Get all terms with term_taxonomy_id.
			$terms = get_terms( $type . '_translations', array( 'hide_empty' => false ) );

			// Prepare objects relationships.
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$translations = unserialize( $term->description );
					foreach ( $translations as $object_id ) {
						if ( ! empty( $object_id ) ) {
							$trs[] = $wpdb->prepare( '(%d, %d)', $object_id, $term->term_taxonomy_id );
						}
					}
				}
			}

			$trs = array_unique( $trs );

			// Insert term_relationships.
			if ( ! empty( $trs ) ) {
				$wpdb->query( "INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id) VALUES " . implode( ',', $trs ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		// Nav menus.
		$options = get_option( 'polylang' );
		$theme = get_option( 'stylesheet' );
		$locations = get_nav_menu_locations(); // FIXME does not work.

		if ( ! empty( $locations ) ) {
			foreach ( $locations as $loc => $menu ) {
				foreach ( $icl_translations['nav_menu'] as $trid ) {
					if ( in_array( $menu, $trid ) ) {
						$options['nav_menus'][ $theme ][ $loc ] = $trid;
					}
				}
			}
		}
		update_option( 'polylang', $options );
	}

	/**
	 * Adds strings translations
	 *
	 * @since 0.1
	 *
	 * @return void
	 */
	public function process_strings_translations() {
		global $wpdb;

		$string_translations = array();

		/**
		 * WPML string translations.
		 *
		 * @var stdClass[]
		 */
		$results = $wpdb->get_results(
			"SELECT s.value AS string, st.language, st.value AS translation
			FROM {$wpdb->prefix}icl_strings AS s
			INNER JOIN {$wpdb->prefix}icl_string_translations AS st ON st.string_id = s.id"
		);

		// Order them in a convenient way.
		foreach ( $results as $st ) {
			if ( ! empty( $st->string ) ) {
				$string_translations[ $st->language ][] = array( $st->string, $st->translation );
			}
		}

		// Save Polylang string translations.
		if ( ! empty( $string_translations ) ) {
			foreach ( $string_translations as $lang => $strings ) {
				$language = $this->model->get_language( $lang );
				if ( $language ) {
					$mo = new PLL_MO();
					foreach ( $strings as $msg ) {
						$mo->add_entry( $mo->make_entry( $msg[0], $msg[1] ) );
					}
					$mo->export_to_db( $language );
				}
			}
		}
	}

	/**
	 * Defines Polylang options
	 *
	 * @since 0.1
	 *
	 * @return void
	 */
	public function process_options() {
		$options = get_option( 'polylang' );

		$options['rewrite'] = 1; // Remove /language/ in permalinks ( was the opposite before 0.7.2 ).
		$options['hide_default'] = 1; // Remove URL language information for default language.
		$options['redirect_lang'] = 1; // Redirect the language page to the homepage.

		// Default language.
		$options['default_lang'] = $this->icl_settings['default_language'];

		// Urls modifications.
		switch ( $this->icl_settings['language_negotiation_type'] ) {
			case 1:
				$options['force_lang'] = 1;
				break;
			case 2:
				$options['force_lang'] = 3;
				break;
			case 3:
			default:
				$options['force_lang'] = 0;
				break;
		}

		// Domains.
		$options['domains'] = isset( $this->icl_settings['language_domains'] ) ? $this->icl_settings['language_domains'] : array();

		// Post types.
		if ( ! empty( $this->icl_settings['custom_posts_sync_option'] ) ) {
			$post_types = array_keys( array_filter( $this->icl_settings['custom_posts_sync_option'] ) );
			$post_types = array_diff( $post_types, array( 'post', 'page', 'attachment', 'wp_block' ) );
			$options['post_types'] = $post_types;
		}

		// Taxonomies.
		if ( ! empty( $this->icl_settings['taxonomies_sync_option'] ) ) {
			$taxonomies = array_keys( array_filter( $this->icl_settings['taxonomies_sync_option'] ) );
			$taxonomies = array_diff( $taxonomies, array( 'category', 'post_tag' ) );
			$options['taxonomies'] = $taxonomies;
		}

		// Sync.
		$sync = array(
			'sync_page_ordering'  => 'menu_order',
			'sync_page_parent'    => 'post_parent',
			'sync_page_template'  => '_wp_page_template',
			'sync_ping_status'    => 'ping_status',
			'sync_comment_status' => 'comment_status',
			'sync_sticky_flag'    => 'sticky_posts',
		);

		$options['sync'] = array();
		foreach ( $sync as $wpml_opt => $pll_opt ) {
			if ( ! empty( $this->icl_settings[ $wpml_opt ] ) ) {
				$options['sync'][] = $pll_opt;
			}
		}
		update_option( 'polylang', $options );

		// Default category in default language.
		update_option( 'default_category', (int) $this->icl_settings['default_categories'][ $this->icl_settings['default_language'] ] );
	}

	/**
	 * Returns true if WPML manages post type language and translation
	 *
	 * @since 0.1
	 *
	 * @param string $type Post type name.
	 * @return bool
	 */
	public function is_translated_post_type( $type ) {
		return in_array( $type, array( 'post', 'page' ) ) || ! empty( $this->icl_settings['custom_posts_sync_option'][ $type ] );
	}

	/**
	 * Returns true if WPML manages taxonomy language and translation
	 *
	 * @since 0.1
	 *
	 * @param string $tax Taxonomy name.
	 * @return bool
	 */
	public function is_translated_taxonomy( $tax ) {
		return in_array( $tax, array( 'category', 'post_tag' ) ) || ! empty( $this->icl_settings['taxonomies_sync_option'][ $tax ] );
	}
}

new WPML_To_Polylang();
