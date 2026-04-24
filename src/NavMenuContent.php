<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

defined( 'ABSPATH' ) || exit;

/**
 * Rewrites hardcoded nav_menu IDs inside post content for translated posts.
 *
 * Problem: WPBakery Page Builder stores menus by term ID, e.g.
 *   [vc_wp_custommenu nav_menu="56"]
 * With WPML active, those IDs were resolved to the current language's menu at
 * render time. Polylang does NOT do this unless menus are explicitly assigned
 * a language and linked as translations.
 *
 * Solution: Update the post_content of every non-default-language post so
 * that each `nav_menu="X"` attribute is replaced with the menu ID that WPML
 * recorded as the translation of menu X for that language.
 *
 * This is a one-step, non-batched action because the number of affected posts
 * is typically very small (e.g. one global-section post per language).
 *
 * @since 1.0
 */
class NavMenuContent extends AbstractAction {

	/**
	 * Returns the action name.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function getName() {
		return 'process_nav_menu_content';
	}

	/**
	 * Returns the processing message.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	protected function getMessage() {
		return esc_html__( 'Fixing menu references in translated post content', 'wpml-to-polylang' );
	}

	/**
	 * Rewrites nav_menu IDs in all translated posts.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	protected function handle() {
		global $wpdb;

		// Build a map: source_menu_id → [ lang => translated_menu_id ]
		$menuMap = $this->buildMenuMap();
		if ( empty( $menuMap ) ) {
			return;
		}

		$defaultLang = pll_default_language();

		// Find all translated (non-default-language) posts that contain nav_menu= attribute.
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_content, lang.slug AS lang
				FROM {$wpdb->posts} p
				JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				JOIN {$wpdb->term_taxonomy} tt
					ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'language'
				JOIN {$wpdb->terms} lang ON lang.term_id = tt.term_id
				WHERE p.post_status NOT IN ('trash','auto-draft')
				  AND p.post_content LIKE %s
				  AND lang.slug != %s",
				'%nav_menu="%',
				$defaultLang
			)
		);

		if ( empty( $posts ) ) {
			return;
		}

		foreach ( $posts as $post ) {
			$newContent = $this->rewriteMenuIds( $post->post_content, $post->lang, $menuMap );

			if ( $newContent === $post->post_content ) {
				continue;
			}

			$wpdb->update(
				$wpdb->posts,
				[ 'post_content' => $newContent ],
				[ 'ID' => $post->ID ],
				[ '%s' ],
				[ '%d' ]
			);

			clean_post_cache( $post->ID );
		}
	}

	/**
	 * Replaces every `nav_menu="X"` in $content with the translated menu ID
	 * for $lang, falling back to the original ID when no translation exists.
	 *
	 * @param string   $content Post content.
	 * @param string   $lang    Language slug (e.g. 'fr').
	 * @param int[][]  $menuMap Source-menu-id → [ lang => translated_id ].
	 * @return string
	 */
	private function rewriteMenuIds( $content, $lang, array $menuMap ) {
		return preg_replace_callback(
			'/nav_menu="(\d+)"/',
			function ( $matches ) use ( $lang, $menuMap ) {
				$sourceId = (int) $matches[1];
				if ( isset( $menuMap[ $sourceId ][ $lang ] ) ) {
					return 'nav_menu="' . $menuMap[ $sourceId ][ $lang ] . '"';
				}
				return $matches[0];
			},
			$content
		);
	}

	/**
	 * Builds a map of source-language menu IDs to their per-language translations.
	 *
	 * Returns: [ sourceMenuId => [ langSlug => translatedMenuId, … ], … ]
	 *
	 * @return int[][]
	 */
	private function buildMenuMap() {
		global $wpdb;

		$defaultLang = pll_default_language();

		$rows = $wpdb->get_results(
			"SELECT DISTINCT t.term_id AS menu_id, it.language_code AS lang, it.trid
			FROM {$wpdb->terms} t
			JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id AND tt.taxonomy = 'nav_menu'
			JOIN {$wpdb->prefix}icl_translations it
				ON it.element_id = tt.term_taxonomy_id
				AND it.element_type = 'tax_nav_menu'"
		);

		if ( empty( $rows ) ) {
			return [];
		}

		// Group by trid so we can identify the source-language menu in each group.
		$byTrid = [];
		foreach ( $rows as $row ) {
			$byTrid[ $row->trid ][ $row->lang ] = (int) $row->menu_id;
		}

		$map = [];
		foreach ( $byTrid as $translations ) {
			if ( empty( $translations[ $defaultLang ] ) ) {
				continue; // No source-language menu in this group.
			}
			$sourceId = $translations[ $defaultLang ];
			foreach ( $translations as $lang => $menuId ) {
				if ( $lang !== $defaultLang ) {
					$map[ $sourceId ][ $lang ] = $menuId;
				}
			}
		}

		return $map;
	}
}
