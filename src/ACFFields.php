<?php
/**
 * Advanced Custom Fields import.
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the ACF fields.
 *
 * @since 0.7
 */
class ACFFields extends AbstractSteppable {
	const ACFML_KEY = 'wpml_cf_preferences';
	const TYPES     = [
		0 => 'none', // WPML_IGNORE_CUSTOM_FIELD.
		1 => 'copy', // WPML_COPY_CUSTOM_FIELD.
		2 => 'translate', // WPML_TRANSLATE_CUSTOM_FIELD.
		3 => 'copy_once', // WPML_COPY_ONCE_CUSTOM_FIELD.
	];

	/**
	 * Returns the action name.
	 *
	 * @since 0.7
	 *
	 * @return string
	 */
	public function getName() {
		return 'process_acf_fields';
	}

	/**
	 * Returns the processing message.
	 *
	 * @since 0.7
	 *
	 * @return string
	 */
	protected function getMessage() {
		return esc_html__( 'Processing ACF fields', 'wpml-to-polylang' );
	}

	/**
	 * Processes the ACF fields.
	 *
	 * @since 0.7
	 *
	 * @return void
	 */
	protected function handle() {
		global $wpdb;

		$batch_size = $this->getBatchSyze();
		$offset     = absint( ( $this->step * $batch_size ) - $batch_size );
		$results    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content
				FROM {$wpdb->posts}
				WHERE post_type = 'acf-field' AND post_content LIKE %s
				ORDER BY ID ASC
				LIMIT %d OFFSET %d",
				'%"' . $wpdb->esc_like( self::ACFML_KEY ) . '"%',
				$batch_size,
				$offset
			)
		);

		$values = [];

		foreach ( $results as $field ) {
			$post_content = maybe_unserialize( $field->post_content );

			if ( ! is_array( $post_content ) ) {
				// Unserialization failure.
				continue;
			}

			if ( ! is_numeric( $post_content[ self::ACFML_KEY ] ) || ! isset( self::TYPES[ $post_content[ self::ACFML_KEY ] ] ) ) {
				// Should not happen.
				$post_content['translations'] = 'none';
			} else {
				$post_content['translations'] = self::TYPES[ $post_content[ self::ACFML_KEY ] ];
			}

			$post_content = serialize( $post_content ); // PHPCS:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

			if ( ! is_string( $post_content ) ) {
				// Serialization failure.
				continue;
			}

			$values[ $field->ID ] = [ $field->ID, $post_content ];
		} // End foreach.

		if ( empty( $values ) ) {
			return;
		}

		// Update the whole batch in one query.
		$wpdb->query(
			$wpdb->prepare(
				sprintf(
					"UPDATE {$wpdb->posts} SET post_content = (CASE ID %s ELSE post_content END) WHERE ID IN (%s)",
					implode( ' ', array_fill( 0, count( $values ), 'WHEN %d THEN %s' ) ),
					implode( ',', array_fill( 0, count( $values ), '%d' ) )
				),
				array_merge( array_merge( ...$values ), array_keys( $values ) )
			)
		);
	}

	/**
	 * Returns the batch size.
	 *
	 * @since 0.7
	 *
	 * @return int A positive integer.
	 */
	protected function getBatchSyze() {
		return absint( WPML_TO_POLYLANG_QUERY_BATCH_SIZE / 100 ); // 50 by default, to limit the size of the UPDATE query.
	}

	/**
	 * Returns the number of ACF fields to update.
	 *
	 * @since 0.7
	 *
	 * @return int
	 */
	protected function getTotal() {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->posts}
				WHERE post_type = 'acf-field' AND post_content LIKE %s",
				'%"' . $wpdb->esc_like( self::ACFML_KEY ) . '"%'
			)
		);
	}
}
