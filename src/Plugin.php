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
		$actions = [
			new Languages(),
			new Posts(),
			new Terms(),
			new Menus(),
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

		$page = new Page( reset( $actions )->getName() );
		$page->addHooks();

		add_action( 'pll_init', [ $this, 'fixConflicts' ], 999 ); // After PLLWC.
	}

	/**
	 * Prevent Polylang for WooCommerce to create default product categories.
	 *
	 * @since 0.7
	 *
	 * @return void
	 */
	public function fixConflicts() {
		if ( function_exists( 'PLLWC' ) ) {
			remove_action( 'admin_init', [ PLLWC(), 'maybe_upgrade' ] );
		}
	}
}
