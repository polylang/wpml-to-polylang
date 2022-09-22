<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract class for actions.
 *
 * @since 0.5
 */
abstract class AbstractAction {

	/**
	 * Name of the next action to process if any.
	 *
	 * @since 0.5
	 *
	 * @var string
	 */
	protected $next = '';

	/**
	 * Current step being processed.
	 *
	 * @since 0.5
	 *
	 * @var int
	 */
	protected $step = 0;

	/**
	 * Returns the action name.
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	abstract public function getName();

	/**
	 * Returns the processing message.
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	abstract protected function getMessage();

	/**
	 * Processes the action.
	 *
	 * @since 0.5
	 *
	 * @return void
	 */
	abstract protected function handle();

	/**
	 * Add hooks.
	 *
	 * @since 0.5
	 *
	 * @return void
	 */
	public function addHooks() {
		add_action( 'wp_ajax_' . $this->getName(), [ $this, 'ajaxResponse' ] );
	}

	/**
	 * Handles the ajax response.
	 *
	 * @since 0.5
	 *
	 * @return void
	 */
	public function ajaxResponse() {
		check_ajax_referer( 'wpml-importer', '_wpnonce_wpml-importer' );

		if ( empty( $_POST['action'] ) ) {
			wp_die(); // Something's wrong.
		}

		$this->step = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1;

		$this->handle();

		$percentage = $this->getPercentage(); // Save the value before we increment the step.
		$message    = sprintf( '%s : %d%%', $this->getMessage(), $percentage );

		$response = [
			'action'  => sanitize_key( $_POST['action'] ),
			'message' => $message,
			'step'    => ++$this->step,
		];

		if ( 100 === $percentage ) {
			if ( ! empty( $this->next ) ) {
				$response['action'] = $this->next;
				$response['step']   = 1;
			} else {
				$response = [
					'done'    => true,
					'message' => esc_html__( 'Done!', 'wpml-to-polylang' ),
				];
			}
		}

		wp_send_json( $response );
	}

	/**
	 * Sets the next action to process.
	 *
	 * @since 0.5
	 *
	 * @param string $name Next action name.
	 * @return void
	 */
	public function setNext( $name ) {
		$this->next = $name;
	}

	/**
	 * Returns the action completion percentage.
	 *
	 * @since 0.5
	 *
	 * @return int
	 */
	protected function getPercentage() {
		return 100;
	}
}
