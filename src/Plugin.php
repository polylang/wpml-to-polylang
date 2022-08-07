<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

/**
 * Main plugin class.
 */
class Plugin {
	/**
	 * Initializes the plugin.
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
	}
}
