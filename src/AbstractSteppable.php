<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

/**
 * Abstract class for steppable actions.
 */
abstract class AbstractSteppable extends AbstractAction {

	/**
	 * Returns the total number of items to process.
	 *
	 * @return int
	 */
	abstract protected function getTotal();

	/**
	 * Returns the action completion percentage.
	 *
	 * @return int
	 */
	protected function getPercentage() {
		$percentage = 100;
		$total      = $this->getTotal();

		if ( $total ) {
			$percentage = ( $this->step * WPML_TO_POLYLANG_QUERY_BATCH_SIZE ) / $total * 100;
			$percentage = (int) ceil( $percentage );
		}

		return $percentage > 100 ? 100 : $percentage;
	}
}
