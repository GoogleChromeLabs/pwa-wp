<?php
/**
 * WP_Service_Worker_Component interface.
 *
 * @package PWA
 */

/**
 * Interface representing a service worker core component.
 *
 * @since 0.2
 */
interface WP_Service_Worker_Component {

	/**
	 * Adds the component functionality to the service worker.
	 *
	 * @since 0.2
	 *
	 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
	 */
	public function serve( WP_Service_Worker_Scripts $scripts );

	/**
	 * Gets the priority this component should be hooked into the service worker action with.
	 *
	 * @since 0.2
	 *
	 * @return int Hook priority. A higher number means a lower priority.
	 */
	public function get_priority();
}
