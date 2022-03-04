<?php
/**
 * WP_Service_Worker_Integration interface.
 *
 * @package PWA
 */

/**
 * Interface representing a service worker integration.
 *
 * @since 0.2
 * @deprecated 0.7 Integrations will not be proposed for WordPress core merge.
 */
interface WP_Service_Worker_Integration {

	/**
	 * Gets the scope this integration applies to.
	 *
	 * @since 0.2
	 * @deprecated 0.7 Integrations will not be proposed for WordPress core merge.
	 *
	 * @return int Either WP_Service_Workers::SCOPE_FRONT, WP_Service_Workers::SCOPE_ADMIN, or
	 *             WP_Service_Workers::SCOPE_ALL.
	 */
	public function get_scope();

	/**
	 * Gets the priority this integration should be hooked into the service worker action with.
	 *
	 * @since 0.2
	 * @deprecated 0.7 Integrations will not be proposed for WordPress core merge.
	 *
	 * @return int Hook priority. A higher number means a lower priority.
	 */
	public function get_priority();

	/**
	 * Registers the integration functionality.
	 *
	 * @since 0.2
	 * @deprecated 0.7 Integrations will not be proposed for WordPress core merge.
	 *
	 * @param WP_Service_Worker_Scripts $scripts Instance to register service worker behavior with.
	 */
	public function register( WP_Service_Worker_Scripts $scripts );
}
