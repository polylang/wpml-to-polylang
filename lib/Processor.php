<?php
/**
 * Processor
 *
 * @package wpml-to-polylang
 */

namespace WPML_To_Polylang;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 404 Not Found' );
	exit();
}

if ( false === defined( 'WPML_TO_POLYLANG_SCRIPT_TIMEOUT_IN_SECONDS' ) ) {
	define( 'WPML_TO_POLYLANG_SCRIPT_TIMEOUT_IN_SECONDS', 1800 ); // never use 0.
}
if ( false === defined( 'WPML_TO_POLYLANG_QUERY_BATCH_SIZE' ) ) {
	define( 'WPML_TO_POLYLANG_QUERY_BATCH_SIZE', 25000 ); }

/**
 * Responsible for processing the actual import process from WPML to PolyLang
 */
class Processor {

	/**
	 * @var \PLL_Admin_Model
	 */
	protected $model;

	/**
	 * WPML settings.
	 *
	 * @var array
	 */
	protected $icl_settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->icl_settings = \get_option( 'icl_sitepress_settings' );
		$this->model        = $GLOBALS['polylang']->model;
		$this->import();
	}

	/**
	 * Dispatches the different import steps.
	 *
	 * @return void
	 * @since 0.1
	 */
	protected function import() {
		set_time_limit( WPML_TO_POLYLANG_SCRIPT_TIMEOUT_IN_SECONDS ); // never use 0.

		Status::update( Status::STATUS_PREPARING_DATA );

		// Order of operations.
		$this->create_polylang_languages();
		$this->prepare_for_import();

		// Migrate Post Type and Taxonomies translations.
		$this->import_wpml_translations();
		$this->import_objects_with_no_lang();

		// Migrate strings translations and options.
		$this->process_strings_translations();
		$this->process_options();

		$this->cleanup();
		$this->complete();
	}

	/**
	 * Actions to perform to prepare for import.
	 *
	 * @return void
	 */
	protected function prepare_for_import() {
		// Purge term_translations to prevent issues.
		/** @var int[] */
		$term_ids = \get_terms( 'term_translations', array( 'hide_empty' => false, 'fields' => 'ids' ) );
		if ( is_array( $term_ids ) ) {
			// Delete the translation group of the default category to avoid a conflict later.
			foreach ( $term_ids as $term_id ) {
				\wp_delete_term( $term_id, 'term_translations' );
			}
		}
		// Free memory.
		$term_ids = null;
		unset( $term_ids );
		time_nanosleep( 0, 10000000 );

		// Update the languages list.
		$this->model->clean_languages_cache();
	}

	/**
	 * Handles any cleanup operations.
	 *
	 * @return void
	 */
	protected function cleanup() {
		// Make sure that the page on front and page for posts are correctly included in the cached language objects.
		if ( 'page' === \get_option( 'show_on_front' ) ) {
			\wp_cache_delete( \get_option( 'page_on_front' ), 'post_translations_relationships' );
			\wp_cache_delete( \get_option( 'page_for_posts' ), 'language_relationships' );
			\wp_cache_delete( \get_option( 'page_on_front' ), 'language_relationships' );
			\wp_cache_delete( \get_option( 'page_for_posts' ), 'post_translations_relationships' );
			$this->model->clean_languages_cache();
		}
	}

	/**
	 * Completes the import process.
	 *
	 * @return void
	 */
	protected function complete() {
		\flush_rewrite_rules();
		Status::update( Status::STATUS_COMPLETED );
		// Remove the wizard notice as it isn't needed or expected after import is complete.
		if ( class_exists( 'PLL_Admin_Notices' ) ) {
			\PLL_Admin_Notices::dismiss( 'wizard' );
		}
	}

	/**
	 * Creates the Polylang languages.
	 *
	 * @return void
	 * @since 0.1
	 */
	private function create_polylang_languages() {
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

		// Process language order from WPML.
		foreach ( $this->icl_settings['languages_order'] as $ordered_lang_slug ) {
			// Loop over WPML languages and add to PolyLang in the same order.
			// NOTE: assumption that languages_order has all languages (tests show this is always true).
			foreach ( $wpml_languages as $index => $lang ) {
				if ( $lang['slug'] !== $ordered_lang_slug ) {
					continue;
				}

				$lang['term_group']     = 0;
				$lang['no_default_cat'] = 1; // Prevent the creation of a new default category.

				// We need a flag and can be more exhaustive for the rtl languages list.
				$lang['rtl']  = 'rtl' === $languages[ $lang['locale'] ]['dir'] ? 1 : 0;
				$lang['flag'] = $languages[ $lang['locale'] ]['flag'];

				$this->model->add_language( $lang );

				// Remove since we already processed it (no need to use this one again).
				unset( $wpml_languages[ $index ] );
			}
		}
	}

	/**
	 * Imports WPML translations into PolyLang.
	 *
	 * @return void
	 */
	private function import_wpml_translations() {
		global $wpdb;

		Status::update( Status::STATUS_PROCESSING_POST_AND_TAX_TRANSLATIONS );

		/**
		 * Collect necessary data for processing translations.
		 *
		 * @var \PLL_Language[]
		 */
		$languages = array();
		foreach ( $this->model->get_languages_list() as $lang ) {
			$languages[ $lang->slug ] = $lang;
		}
		$term_ids = $this->get_term_ids_with_correspondence();

		$default_cat = (int) \get_option( 'default_category' );

		$icl_translations = array();
		$post_languages   = array();
		$term_languages   = array();

		// Paginate the translations.
		$total_records = (int) $wpdb->get_var( "SELECT COUNT(1) as total FROM {$wpdb->prefix}icl_translations" );
		$total_pages   = (int) ceil( $total_records / WPML_TO_POLYLANG_QUERY_BATCH_SIZE );
		for ( $page = 1; $page <= $total_pages; $page ++ ) {
			$offset  = ( $page * WPML_TO_POLYLANG_QUERY_BATCH_SIZE ) - WPML_TO_POLYLANG_QUERY_BATCH_SIZE;
			$results = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_translations LIMIT %d, %d", $offset, WPML_TO_POLYLANG_QUERY_BATCH_SIZE )
			);

			foreach ( $results as $r ) {
				if ( ! empty( $r->language_code ) && ! empty( $languages[ $r->language_code ] ) ) {
					// TODO Optimize this.

					// Posts and terms languages.
					if ( 0 === strpos( $r->element_type, 'post_' ) && $this->is_translated_post_type( substr( $r->element_type, 5 ) ) ) {
						// $wpdb->prepare is overkill and unnecessary here (dealing with integers)
						$post_languages[] = '(' . (int) $r->element_id . ', ' . (int) $languages[ $r->language_code ]->term_taxonomy_id . ')';
					} elseif ( 0 === strpos( $r->element_type, 'tax_' ) && $this->is_translated_taxonomy( substr( $r->element_type, 4 ) ) && ! empty( $term_ids[ $r->element_id ] ) && (int) $term_ids[ $r->element_id ]->term_id !== $default_cat ) {
						// $wpdb->prepare is overkill and unnecessary here (dealing with integers)
						$term_languages[] = '(' . (int) $term_ids[ $r->element_id ]->term_id . ', ' . (int) $languages[ $r->language_code ]->tl_term_taxonomy_id . ')';
					}

					// Arrange translations in a convenient way.
					if ( 'tax_nav_menu' === $r->element_type && ! empty( $term_ids[ $r->element_id ] ) ) {
						$icl_translations['nav_menu'][ $r->trid ][ $r->language_code ] = (int) $term_ids[ $r->element_id ]->term_id;
					} elseif ( 0 === strpos( $r->element_type, 'post_' ) && $this->is_translated_post_type( substr( $r->element_type, 5 ) ) ) {
						$icl_translations['post'][ $r->trid ][ $r->language_code ] = (int) $r->element_id;
					} elseif ( 0 === strpos( $r->element_type, 'tax_' ) && $this->is_translated_taxonomy( substr( $r->element_type, 4 ) ) && ! empty( $term_ids[ $r->element_id ] ) ) {
						$icl_translations['term'][ $r->trid ][ $r->language_code ] = (int) $term_ids[ $r->element_id ]->term_id;
					}
				}
			}
		}
		$term_ids = null;
		unset( $term_ids );
		$results = null;
		unset( $results );
		time_nanosleep( 0, 10000000 ); // Free memory.

		$post_languages = array_unique( $post_languages );
		$term_languages = array_unique( $term_languages );

		// Migrate languages and translations.
		if ( ! empty( $post_languages ) ) {
			$post_languages = array_chunk( $post_languages, WPML_TO_POLYLANG_QUERY_BATCH_SIZE );
			foreach ( $post_languages as $chunk ) {
				$wpdb->query( "INSERT INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id) VALUES " . implode( ',', $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
		// Free memory.
		$post_languages = null;
		unset( $post_languages );
		$chunk = null;
		unset( $chunk );
		time_nanosleep( 0, 10000000 );

		if ( ! empty( $term_languages ) ) {
			$term_languages = array_chunk( $term_languages, WPML_TO_POLYLANG_QUERY_BATCH_SIZE );
			foreach ( $term_languages as $chunk ) {
				$wpdb->query( "INSERT INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id) VALUES " . implode( ',', $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
		// Free memory.
		$term_languages = null;
		unset( $term_languages );
		$chunk = null;
		unset( $chunk );
		time_nanosleep( 0, 10000000 );

		// Update language counts.
		foreach ( $languages as $lang ) {
			$lang->update_count();
		}

		$this->process_post_term_translations( $icl_translations );
		$this->process_nav_menu_translations( $icl_translations );

		// Free memory.
		$icl_translations = null;
		unset( $icl_translations );
		time_nanosleep( 0, 10000000 );
	}

	/**
	 * Returns term ids with correspondence between term_taxonomy_id and term_id.
	 *
	 * @return \stdClass[]
	 */
	private function get_term_ids_with_correspondence() {
		global $wpdb;

		/*
		* Get the correspondence between term_taxonomy_id and term_id ( duplicates are discarded ).
		* Required as WPML stores term_taxonomy_id in icl_translations while Polylang translates term_id.
		* Thanks to Nickness. See http://wordpress.org/support/topic/wpml-languages-import-is-broken-with-last-polylang-update-15-16
		*/
		$_taxonomies = array( 'category', 'post_tag', 'nav_menu' );
		if ( ! empty( $this->icl_settings['taxonomies_sync_option'] ) ) {
			$_taxonomies = array_merge( $_taxonomies, array_keys( array_filter( $this->icl_settings['taxonomies_sync_option'] ) ) );
		}

		$taxonomies = array();
		foreach ( $_taxonomies as $tax ) {
			// $wpdb->prepare is not needed here, this is fresh from the DB (if this is corrupt, they have bigger issues).
			$taxonomies[] = $tax;
		}
		// Free memory.
		$_taxonomies = null;
		unset( $_taxonomies );
		time_nanosleep( 0, 10000000 );

		$term_ids   = array();
		$taxonomies = array_chunk( $taxonomies, WPML_TO_POLYLANG_QUERY_BATCH_SIZE );
		foreach ( $taxonomies as $chunk ) {
			$_tmp_term_ids = $wpdb->get_results( "SELECT term_taxonomy_id, term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('" . implode( "', '", $chunk ) . "')", OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$term_ids      = array_merge( $term_ids, $_tmp_term_ids );
		}

		// Free memory.
		$taxonomies = null;
		unset( $taxonomies );
		$_tmp_term_ids = null;
		unset( $_tmp_term_ids );
		time_nanosleep( 0, 10000000 );

		return $term_ids;
	}

	/**
	 * Creates translations groups.
	 *
	 * @param array $icl_translations array of icl translations.
	 * @return void
	 * @since 0.1
	 */
	private function process_post_term_translations( $icl_translations ) {
		global $wpdb;

		Status::update( Status::STATUS_PROCESSING_POST_TERM_TRANSLATIONS );

		foreach ( array( 'post', 'term' ) as $type ) {
			$terms       = array();
			$slugs       = array();
			$description = array();
			$count       = array();
			$tts         = array();
			$trs         = array();

			if ( empty( $icl_translations[ $type ] ) ) {
				continue;
			}

			foreach ( $icl_translations[ $type ] as $t ) {
				$t = array_filter( $t ); // It looks like WPML can have 0 as translation id.
				if ( empty( $t ) ) {
					continue;
				}

				$term = uniqid( 'pll_' ); // The term name.
				$term = esc_sql( $term ); // not really needed but best to be safe (due to _).

				// $wpdb->prepare is overkill for this (we are generating this and can trust the $term).
				$terms[] = "('{$term}', '{$term}')";
				$slugs[] = $term;

				$description[ $term ] = serialize( $t );
				$count[ $term ]       = count( $t );
			}
			$terms = array_unique( $terms );

			// Insert terms.
			if ( ! empty( $terms ) ) {
				$terms = array_chunk( $terms, WPML_TO_POLYLANG_QUERY_BATCH_SIZE );
				foreach ( $terms as $chunk ) {
					$wpdb->query( "INSERT INTO {$wpdb->terms} (slug, name) VALUES " . implode( ',', $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}
			}
			// Free memory.
			$terms = null;
			unset( $terms );
			time_nanosleep( 0, 10000000 );

			// Get all terms with their term_id.
			$terms = array(); // Free memory before reassign as this actually adds to the current memory for it (especially for php5).
			$slugs = array_chunk( $slugs, WPML_TO_POLYLANG_QUERY_BATCH_SIZE );
			foreach ( $slugs as $chunk ) {
				$_tmp_terms = $wpdb->get_results( "SELECT term_id, slug FROM {$wpdb->terms} WHERE slug IN ('" . implode( "','", $chunk ) . "')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$terms      = array_merge( $terms, $_tmp_terms );
			}


			// Prepare terms taxonomy relationship.
			foreach ( $terms as $term ) {
				$tts[] = $wpdb->prepare( '(%d, %s, %s, %d)', $term->term_id, $type . '_translations', $description[ $term->slug ], $count[ $term->slug ] );
			}
			$tts = array_unique( $tts );

			// Insert term_taxonomy.
			if ( ! empty( $tts ) ) {
				$tts = array_chunk( $tts, WPML_TO_POLYLANG_QUERY_BATCH_SIZE );
				foreach ( $tts as $chunk ) {
					$wpdb->query( "INSERT INTO {$wpdb->term_taxonomy} (term_id, taxonomy, description, count) VALUES " . implode( ',', $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}
			}
			// Free memory.
			$tts = null;
			unset( $tts );
			$chunk = null;
			unset( $chunk );
			time_nanosleep( 0, 10000000 );


			// Get all terms with term_taxonomy_id.
			$terms = get_terms( $type . '_translations', array( 'hide_empty' => false ) );

			// Prepare objects relationships.
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$translations = unserialize( $term->description );
					foreach ( $translations as $object_id ) {
						if ( ! empty( $object_id ) ) {
							// $wpdb->prepare is overkill and unnecessary here.
							$trs[] = '(' . (int) $object_id . ', ' . $term->term_taxonomy_id . ')';
						}
					}
				}
			}
			$trs = array_unique( $trs );

			// Insert term_relationships.
			if ( ! empty( $trs ) ) {
				$trs = array_chunk( $trs, WPML_TO_POLYLANG_QUERY_BATCH_SIZE );
				foreach ( $trs as $chunk ) {
					$wpdb->query( "INSERT INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id) VALUES " . implode( ',', $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}
			}
			// Free memory.
			$trs = null;
			unset( $trs );
			$chunk = null;
			unset( $chunk );
			time_nanosleep( 0, 10000000 );
		}
	}

	/**
	 * Process Nav Menu translations.
	 *
	 * @param array $icl_translations array of icl translations.
	 * @return void
	 */
	private function process_nav_menu_translations( $icl_translations ) {
		Status::update( Status::STATUS_PROCESSING_NAV_MENU_TRANSLATIONS );

		// Nav menus.
		$options   = \get_option( 'polylang' );
		$theme     = \get_option( 'stylesheet' );
		$locations = \get_nav_menu_locations(); // FIXME does not work (looks good to me, not sure why this is here).

		if ( ! empty( $locations ) && ! empty( $icl_translations['nav_menu'] ) ) {
			foreach ( $locations as $loc => $menu ) {
				foreach ( $icl_translations['nav_menu'] as $trid ) {
					if ( in_array( $menu, $trid ) ) {
						$options['nav_menus'][ $theme ][ $loc ] = $trid;
					}
				}
			}
		}
		\update_option( 'polylang', $options );

	}

	/**
	 * Import objects with no lang.
	 * Note: In some cases, there is no language assigned in icl_translations table, but WPML displays the default language anyway.
	 *
	 * @return void
	 */
	private function import_objects_with_no_lang() {
		Status::update( Status::STATUS_PROCESSING_OBJECT_WITH_NO_LANGUAGE );
		// Exact same logic as from PLL_Wizard->save_step_untranslated_contents.
		while ( $nolang = $this->model->get_objects_with_no_lang( WPML_TO_POLYLANG_QUERY_BATCH_SIZE ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( ! empty( $nolang['posts'] ) ) {
				$this->model->set_language_in_mass( 'post', $nolang['posts'], $this->icl_settings['default_language'] );
			}
			if ( ! empty( $nolang['terms'] ) ) {
				$this->model->set_language_in_mass( 'term', $nolang['terms'], $this->icl_settings['default_language'] );
			}
		}
	}

	/**
	 * Adds strings translations.
	 *
	 * @return void
	 * @since 0.1
	 */
	private function process_strings_translations() {
		global $wpdb;

		Status::update( Status::STATUS_PROCESSING_STRING_TRANSLATIONS );

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
					$mo = new \PLL_MO();
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
	 * @return void
	 * @since 0.1
	 */
	private function process_options() {
		Status::update( Status::STATUS_PROCESSING_OPTIONS );

		$options = \get_option( 'polylang' );

		$options['rewrite']       = 1; // Remove /language/ in permalinks ( was the opposite before 0.7.2 ).
		$options['hide_default']  = 1; // Remove URL language information for default language.
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
			$post_types            = array_keys( array_filter( $this->icl_settings['custom_posts_sync_option'] ) );
			$post_types            = array_diff( $post_types, array( 'post', 'page', 'attachment', 'wp_block' ) );
			$options['post_types'] = $post_types;
		}

		// Taxonomies.
		if ( ! empty( $this->icl_settings['taxonomies_sync_option'] ) ) {
			$taxonomies            = array_keys( array_filter( $this->icl_settings['taxonomies_sync_option'] ) );
			$taxonomies            = array_diff( $taxonomies, array( 'category', 'post_tag' ) );
			$options['taxonomies'] = $taxonomies;
		}

		// Sync.
		$sync = array(
			'sync_page_ordering' => 'menu_order',
			'sync_page_parent'   => 'post_parent',
			'sync_page_template' => '_wp_page_template',
			'sync_pingStatus'    => 'pingStatus',
			'sync_commentStatus' => 'commentStatus',
			'sync_sticky_flag'   => 'sticky_posts',
		);

		$options['sync'] = array();
		foreach ( $sync as $wpml_opt => $pll_opt ) {
			if ( ! empty( $this->icl_settings[ $wpml_opt ] ) ) {
				$options['sync'][] = $pll_opt;
			}
		}
		\update_option( 'polylang', $options );

		// Default category in default language.
		\update_option( 'default_category', (int) $this->icl_settings['default_categories'][ $this->icl_settings['default_language'] ] );
	}

	/**
	 * Returns true if WPML manages post type language and translation
	 *
	 * @param string $type Post type name.
	 * @return bool
	 * @since 0.1
	 */
	private function is_translated_post_type( $type ) {
		return in_array(
			$type,
			array(
				'post',
				'page',
			) 
		) || ! empty( $this->icl_settings['custom_posts_sync_option'][ $type ] );
	}

	/**
	 * Returns true if WPML manages taxonomy language and translation
	 *
	 * @param string $tax Taxonomy name.
	 * @return bool
	 * @since 0.1
	 */
	private function is_translated_taxonomy( $tax ) {
		return in_array(
			$tax,
			array(
				'category',
				'post_tag',
			) 
		) || ! empty( $this->icl_settings['taxonomies_sync_option'][ $tax ] );
	}
}
