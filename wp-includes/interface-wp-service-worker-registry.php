<?php
/**
 * WP_Service_Worker_Registry interface.
 *
 * @package PWA
 */

/**
 * Interface representing a service worker registry.
 *
 * @since 0.2
 */
interface WP_Service_Worker_Registry {

	/**
	 * Registers an item.
	 *
	 * @since 0.2
	 *
	 * @param string $handle Handle of the item.
	 * @param array  $args   Optional. Additional arguments. Default empty array.
	 */
	public function register( $handle, $args = array() );

	/**
	 * Gets all registered items.
	 *
	 * @since 0.2
	 *
	 * @return array List of registered items.
	 */
	public function get_all();
}
