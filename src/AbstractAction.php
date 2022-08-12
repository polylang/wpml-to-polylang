<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

/**
 * Abstract class for actions.
 */
abstract class AbstractAction {

	/**
	 * Name of the next action to process if any.
	 *
	 * @var string
	 */
	protected $next = '';

	/**
	 * Current step being processed.
	 *
	 * @var int
	 */
	protected $step = 0;

	/**
	 * Returns the action name.
	 *
	 * @return string
	 */
	abstract public function getName();

	/**
	 * Returns the processing message.
	 *
	 * @return string
	 */
	abstract protected function getMessage();

	/**
	 * Processes the action.
	 *
	 * @return void
	 */
	abstract protected function handle();

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function addHooks() {
		add_action( 'wp_ajax_' . $this->getName(), [ $this, 'ajaxResponse' ] );
	}

	/**
	 * Handles the ajax response.
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
	 * @param string $name Next action name.
	 * @return void
	 */
	public function setNext( $name ) {
		$this->next = $name;
	}

	/**
	 * Returns the action completion percentage.
	 *
	 * @return int
	 */
	protected function getPercentage() {
		return 100;
	}
}
