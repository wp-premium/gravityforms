<?php
/**
 * Background Upgrader
 *
 * Uses https://github.com/A5hleyRich/wp-background-processing to handle DB
 * updates in the background.
 *
 * @class    GF_Background_Upgrader
 * @version  2.3
 * @category Class
 * @author   Rocketgenius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( 'libraries/wp-async-request.php' );
require_once( 'libraries/gf-background-process.php' );

/**
 * GF_Background_Upgrader Class.
 */
class GF_Background_Upgrader extends GF_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'gf_upgrader';
	
	/**
	 * Returns the data for the background upgrader.
	 *
	 * @since 2.3
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Is the queue empty for all blogs?
	 *
	 * @since 2.3
	 *
	 * @return bool
	 */
	public function is_queue_empty() {
		return parent::is_queue_empty();
	}

	/**
	 * Is the updater running?
	 * @return boolean
	 */
	public function is_updating() {
		return false === $this->is_queue_empty();
	}

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param string $callback Update callback function
	 * @return mixed
	 */
	protected function task( $callback ) {
		if ( ! defined( 'GF_UPGRADING' ) ) {
			define( 'GF_UPGRADING', true );
		}

		if ( is_callable( $callback ) ) {
			GFCommon::log_debug( sprintf( '%s(): Running callback: %s', __METHOD__, print_r( $callback, 1 ) ) );
			$needs_more_time = call_user_func( $callback );
			if ( $needs_more_time ) {
				GFCommon::log_debug( sprintf( '%s(): Callback needs another run: %s', __METHOD__, print_r( $callback, 1 ) ) );
				return $callback;
			} else {
				GFCommon::log_debug( sprintf( '%s(): Finished callback: %s', __METHOD__, print_r( $callback, 1 ) ) );
			}
		} else {
			GFCommon::log_debug( sprintf( '%s(): Could not find callback: %s', __METHOD__, print_r( $callback, 1 ) ) );
		}

		return false;
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();
	}

}
