<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 *
 * @since 0.5
 */
class Plugin {
	/**
	 * Initializes the plugin.
	 *
	 * @since 0.5
	 *
	 * @return void
	 */
	public function init() {

		// ── Full migration chain ──────────────────────────────────────────────
		$actions = [
			new Languages(),
			new Posts(),
			new Terms(),
			new Menus(),
			new NavMenuContent(),
			new NoLangObjects(),
			new Strings(),
			new Options(),
			new ACFFields(),
		];

		$nextAction = '';
		foreach ( array_reverse( $actions ) as $action ) {
			if ( ! empty( $nextAction ) ) {
				$action->setNext( $nextAction );
			}
			$action->addHooks();
			$nextAction = $action->getName();
		}

		// ── Standalone (repeatable) actions ───────────────────────────────────
		$stringsAction    = new StandaloneStrings();
		$acfOptionsAction = new AcfOptions();
		$navMenuAction    = new StandaloneNavMenuContent();

		$stringsAction->addHooks();
		$acfOptionsAction->addHooks();
		$navMenuAction->addHooks();

		// ── Single unified admin page ─────────────────────────────────────────
		$page = new Page( reset( $actions )->getName() );

		$page->addTool(
			'strings',
			$stringsAction->getName(),
			__( 'String Translations', 'wpml-to-polylang' ),
			__( 'Re-import WPML string translations into Polylang. Safe to run multiple times — existing translations are overwritten with WPML values.', 'wpml-to-polylang' ),
			__( 'Run String Import', 'wpml-to-polylang' )
		);

		$page->addTool(
			'acf-options',
			$acfOptionsAction->getName(),
			__( 'ACF Options Migration', 'wpml-to-polylang' ),
			__( 'Copies ACF options-page translations from the old slug-prefix format (options_fr_*) to the locale-prefix format (options_fr_FR_*) required by "ACF Options For Polylang" v2.0+.', 'wpml-to-polylang' ),
			__( 'Run ACF Migration', 'wpml-to-polylang' )
		);

		$page->addTool(
			'nav-menu',
			$navMenuAction->getName(),
			__( 'Fix Translated Menus', 'wpml-to-polylang' ),
			__( 'Rewrites hardcoded English menu IDs inside translated post content (e.g. WPBakery nav_menu="56") so each language uses its own translated menu.', 'wpml-to-polylang' ),
			__( 'Fix Menu References', 'wpml-to-polylang' )
		);

		$page->addHooks();
	}
}
