<?php
/**
 * WP_Service_Worker_Registry_Aware interface.
 *
 * @package PWA
 */

/**
 * Interface for classes that host a registry.
 *
 * @since 0.2
 */
interface WP_Service_Worker_Registry_Aware {

	/**
	 * Gets the registry.
	 *
	 * @return WP_Service_Worker_Registry Registry instance.
	 */
	public function get_registry();
}
