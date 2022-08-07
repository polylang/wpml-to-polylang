<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

use WP_Error;

/**
 * Main plugin class.
 */
class Page {

	/**
	 * Action to execute first.
	 *
	 * @var string
	 */
	protected $action;

	/**
	 * Constructor.
	 *
	 * @param string $action Action to execute first.
	 */
	public function __construct( $action ) {
		$this->action = $action;
	}

	/**
	 * Setups hooks.
	 *
	 * @return void
	 */
	public function addHooks() {
		add_action( 'admin_menu', [ $this, 'addMenus' ] );
	}

	/**
	 * Adds the link to the languages panel in the WordPress admin menu.
	 *
	 * @return void
	 */
	public function addMenus() {
		load_plugin_textdomain( 'wpml-to-polylang' ); // Plugin i18n.
		$title = __( 'WPML importer', 'wpml-to-polylang' );
		add_submenu_page(
			'tools.php',
			$title,
			$title,
			'manage_options',
			'wpml-importer',
			[ $this, 'display' ]
		);
	}

	/**
	 * Displays the page.
	 *
	 * @return void
	 */
	public function display() {
		$errors = $this->getErrors();
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'WPML Importer', 'wpml-to-polylang' ); ?></h2>
			<?php
			if ( empty( $errors ) ) {
				$this->displayForm();
			} else {
				foreach ( $errors as $error ) {
					$this->displayNotice( $error );
				}
			}
			?>
		</div>
		<?php

	}

	/**
	 * Displays the form.
	 *
	 * @return void
	 */
	protected function displayForm() {
		?>
		<div class="form-wrap">
			<p><?php esc_html_e( 'Your website is ready to import WPML data to Polylang.', 'wpml-to-polylang' ); ?></p>
			<form id="batch" method="post">
				<input type="hidden" name="action" value="<?php echo esc_attr( $this->action ); ?>">
				<?php wp_nonce_field( 'wpml-importer', '_wpnonce_wpml-importer' ); ?>
				<?php submit_button( __( 'Import', 'wpml-to-polylang' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Displays error notices.
	 *
	 * @param WP_Error $error WP_Error object.
	 * @return void
	 */
	protected function displayNotice( $error ) {
		?>
		<div class="notice notice-error">
			<p>
				<?php echo esc_html( $error->get_error_message() ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Performs checks before the import is run.
	 *
	 * @return WP_Error[]
	 */
	protected function getErrors() {
		global $sitepress, $wp_version;

		$checks = [];

		if ( false === get_option( 'icl_sitepress_settings' ) ) {
			$checks[] = new WP_Error(
				'wpml_to_polylang_error',
				__( 'WPML is not installed on this website.', 'wpml-to-polylang' )
			);
			return $checks;
		}

		if ( version_compare( $wp_version, WPML_TO_POLYLANG_MIN_WP_VERSION, '<' ) ) {
			$checks[] = new WP_Error(
				'wpml_to_polylang_error',
				__( 'Your version of WordPress is too old. Please update.', 'wpml-to-polylang' )
			);
		}

		if ( ! empty( $sitepress ) ) {
			$checks[] = new WP_Error(
				'wpml_to_polylang_error',
				__( 'WPML is activated. Please deactivate it.', 'wpml-to-polylang' )
			);
		}

		if ( ! defined( 'POLYLANG_VERSION' ) ) {
			$checks[] = new WP_Error(
				'wpml_to_polylang_error',
				__( 'Please install and activate Polylang to run the import.', 'wpml-to-polylang' )
			);
		} else {
			if ( version_compare( POLYLANG_VERSION, WPML_TO_POLYLANG_MIN_PLL_VERSION, '<' ) ) {
				$checks[] = new WP_Error(
					'wpml_to_polylang_error',
					__( 'Your version of Polylang is too old. Please update.', 'wpml-to-polylang' )
				);
			}

			if ( PLL()->model->get_languages_list() ) {
				$checks[] = new WP_Error(
					'wpml_to_polylang_error',
					__( 'Polylang has already been installed on this website. Impossible to run the import.', 'wpml-to-polylang' )
				);
			}
		}

		return $checks;
	}
}
