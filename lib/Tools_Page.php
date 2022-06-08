<?php
/**
 * Tools Page
 *
 * @package wpml-to-polylang
 */

namespace WPML_To_Polylang;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 404 Not Found' );
	exit();
}

if ( false === defined( 'WPML_TO_POLYLANG_LEGACY_SUBMISSION' ) ) {
	define( 'WPML_TO_POLYLANG_LEGACY_SUBMISSION', false );
}

/**
 * Builds the WPML Importer tools page.
 */
class Tools_Page {

	const AJAX_ACTION_IMPORT = 'wpml-importer';
	const AJAX_ACTION_STATUS_CHECK = 'wpml-importer-status-check';

	// AJAX status check interval.
	const STATUS_CHECK_INTERVAL_IN_SECONDS = 10;

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 */
	public function __construct() {
		// Adds the link to the languages panel in the WordPress admin menu.
		add_action( 'admin_menu', array( $this, 'add_menus' ) );
		add_filter(
			'pll_model',
			array(
				$this,
				'pll_model',
			)
		); // Must be outside the is_admin check for the cron to use it.

		Cron::add_cron_hook(); // needs to be outside the is_admin check.

		if ( is_admin() && class_exists( 'PLL_Admin_Model' ) ) {

			if ( false === WPML_TO_POLYLANG_LEGACY_SUBMISSION ) {
				// AJAX import method.
				add_action(
					'wp_ajax_' . self::AJAX_ACTION_IMPORT,
					array(
						$this,
						'process_ajax_request_import',
					),
					10,
					0
				);
				add_action(
					'wp_ajax_' . self::AJAX_ACTION_STATUS_CHECK,
					array(
						$this,
						'process_ajax_request_status',
					),
					10,
					0
				);
			}
		}
	}

	/**
	 * Uses PLL_Admin_Model to be able to create languages.
	 *
	 * @return string
	 * @since 0.2
	 */
	public function pll_model() {
		return 'PLL_Admin_Model';
	}

	/**
	 * Adds the link to the languages panel in the WordPress admin menu.
	 *
	 * @return void
	 * @since 0.1
	 */
	public function add_menus() {
		load_plugin_textdomain( 'wpml-to-polylang', false, basename( dirname( __FILE__ ) ) . '/languages' ); // Plugin i18n.
		$title = __( 'WPML importer', 'wpml-to-polylang' );
		add_submenu_page(
			'tools.php',
			$title,
			$title,
			'manage_options',
			'wpml-importer',
			array(
				$this,
				'tools_page',
			)
		);
	}

	/**
	 * Displays the import page.
	 * Processes the import action.
	 *
	 * @return void
	 * @since 0.1
	 */
	public function tools_page() {
		$_has_import_in_progress = Status::get() !== false;
		?>
		<div class="wrap">
			<h2> <?php esc_html_e( 'WPML Importer', 'wpml-to-polylang' ); ?></h2>
			<?php
			if ( isset( $_POST['pll_action'] ) && 'import' === $_POST['pll_action'] ) {
				check_admin_referer( 'wpml-importer', '_wpnonce_wpml-importer' );
				new Processor();
				Status::remove_from_db();
				?>
				<p><?php esc_html_e( 'Import from WPML to Polylang should have been successul!', 'wpml-to-polylang' ); ?></p>
				<?php
			} else {
				global $sitepress, $wp_version;

				if ( WPML_TO_POLYLANG_LEGACY_SUBMISSION && $_has_import_in_progress ) {
					echo '<h3 style="color: red;">' . esc_html( __( 'Import is already in progress', 'wpml-to-polylang' ) ) . '</h3>';

					return; // nothing more to do here.
				}

				$min_wp_version  = '4.9';
				$min_pll_version = '2.8';
				$checks          = array();

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
					$checks[] = array(
						__( 'No language has been created with Polylang', 'wpml-to-polylang' ),
						$GLOBALS['polylang']->model->get_languages_list() ? 0 : 1,
					);
				}

				// html form.
				?>
				<div class="form-wrap">
					<form id="import" method="post" action="">
						<input type="hidden" name="pll_action" value="import"/>
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
				<?php if ( false === WPML_TO_POLYLANG_LEGACY_SUBMISSION ) : ?>
				<div id="wpml-importer-status"></div>
				<script type="text/javascript">
					let button_submit = jQuery('#submit');
					let status_cage = jQuery('#wpml-importer-status');
					let has_import_in_progress = <?php echo ( $_has_import_in_progress ) ? 'true' : 'false'; ?>;

					// Check if we are already performing an import.
					if (has_import_in_progress) {
						// Disable the submit button to prevent issues with multiple submissions.
						button_submit.prop('disabled', true);
						_buildStatusDetails();
						_check_WPML_to_PolyLang_importer_status();
					}

					// AJAX sumission to trigger the import process.
					button_submit.on("click", function (e) {
						e.preventDefault(); // do not allow an actual submission.

						jQuery.ajax({
							"type": 'POST',
							"url": ajaxurl,
							"dataType": 'json',
							"data": {
								"action": "<?php echo esc_attr( self::AJAX_ACTION_IMPORT ); ?>",
								"_wpnonce": "<?php echo esc_attr( wp_create_nonce( self::AJAX_ACTION_IMPORT ) ); ?>",
							},
							"beforeSend": function () {
								// Disable the submit button to prevent issues with multiple submissions.
								button_submit.prop('disabled', true);
								_buildStatusDetails();
							},
							"success": function () {
								_trigger_check_interval();
							},
						});
					});

					// Builds the status details to show the user the status of the import.
					function _buildStatusDetails() {
						// Add spinner
						let _spinner = jQuery('<div>', {
							id: 'wpml-importer-spinner',
							class: 'spinner',
							title: 'Processing',
						});
						status_cage.append(_spinner);
						_spinner
							.css('visibility', 'visible')
							.css('display', 'inline-block')
							.css('position', 'absolute')
							.css('left', '0')
							.css('vertical-align', 'middle')
						;

						// Add status message.
						let Status = jQuery('<p>', {
							id: 'wpml-importer-spinner-status',
							text: "<?php echo esc_attr( Status::get_as_text( Status::STATUS_WAITING_ON_CRON ) ); ?>"
						});
						Status
							.css('display', 'inline-block')
							.css('position', 'absolute')
							.css('left', '40px')
							.css('vertical-align', 'middle')
							.css('line-height', '.5em')
						;
						status_cage.append(Status);
					}

					// Triggers the interval to check for the status of the import process.
					function _trigger_check_interval() {
						setTimeout(_check_WPML_to_PolyLang_importer_status, <?php echo (int) self::STATUS_CHECK_INTERVAL_IN_SECONDS * 1000; ?>);
					}

					// The request to check the status of the import process.
					function _check_WPML_to_PolyLang_importer_status() {
						jQuery.ajax({
							"type": 'GET',
							"url": ajaxurl,
							"dataType": 'json',
							"data": {
								"action": "<?php echo esc_attr( self::AJAX_ACTION_STATUS_CHECK ); ?>",
								"_wpnonce": "<?php echo esc_attr( wp_create_nonce( self::AJAX_ACTION_STATUS_CHECK ) ); ?>",
							},
							"success": function (json, textStatus, jqXHR) {
								if (undefined !== json.status && undefined !== json.message) {
									// Update the status message.
									jQuery("#wpml-importer-spinner-status").text(json.message);
									// Return to normal when complete.
									if (json.status == <?php echo esc_attr( Status::STATUS_COMPLETED ); ?>) {
										status_cage.empty();
										button_submit.prop('disabled', false);

										let complete_message = jQuery('<h3>', {
											id: 'wpml-importer-spinner-status',
											text: "<?php echo esc_attr( Status::get_as_text( Status::STATUS_COMPLETED ) ); ?>"
										});
										complete_message
											.css('display', 'inline-block')
											.css('color', 'green')
										;
										status_cage.append(complete_message);
									} else {
										_trigger_check_interval();
									}
								}
							},
						});
					}
				</script>
					<?php
			endif;
			}
			?>
		</div><!-- wrap -->
		<?php
	}

	/**
	 * Process the AJAX request to start the import process.
	 *
	 * @return void
	 * @throws \Exception This is not needed since it is caught but phpcs want this here (false-flag).
	 */
	public function process_ajax_request_import() {
		try {
			check_ajax_referer( self::AJAX_ACTION_IMPORT );

			if ( false === current_user_can( 'manage_options' ) ) {
				$string = __( 'You do not have permissions to perform the import ', 'wpml-to-polylang' );
				throw new \Exception( $string );
			}

			// Output to trigger UI change.
			Status::update( Status::STATUS_WAITING_ON_CRON );

			// Schedule the cron event.
			Cron::schedule_event();

			// Send the response.
			echo \wp_json_encode( array( 'started' => time() ) );
			exit();
		} catch ( \Exception $e ) {
			Status::update( Status::STATUS_ERRORED );
			die( esc_attr( $e->getMessage() ) );
		}
	}

	/**
	 * Processes the AJAX request for import status checks.
	 *
	 * @return void
	 * @throws \Exception This is not needed since it is caught but phpcs want this here (false-flag).
	 */
	public function process_ajax_request_status() {
		try {
			check_ajax_referer( self::AJAX_ACTION_STATUS_CHECK );

			if ( false === current_user_can( 'manage_options' ) ) {
				$string = __( 'You do not have permissions to check the import status', 'wpml-to-polylang' );
				throw new \Exception( $string );
			}

			$_status     = Status::get();
			$_status_text = Status::get_as_text( $_status );

			// Remove if we are complete, no need to keep this in the DB.
			if ( Status::STATUS_COMPLETED === $_status ) {
				Status::remove_from_db();
			}

			// Send the response.
			echo \wp_json_encode(
				array(
					'status'  => $_status,
					'message' => $_status_text,
				)
			);
			exit();
		} catch ( \Exception $e ) {
			Status::update( Status::STATUS_ERRORED );
			die( esc_attr( $e->getMessage() ) );
		}
	}
}
