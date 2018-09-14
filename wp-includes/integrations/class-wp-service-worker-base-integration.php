<?php
/**
 * WP_Service_Worker_Base_Integration class.
 *
 * @package PWA
 */

/**
 * Base class representing a service worker integration.
 *
 * @since 0.2
 */
abstract class WP_Service_Worker_Base_Integration implements WP_Service_Worker_Integration {

	/**
	 * Scope this integration applies to.
	 *
	 * @since 0.2
	 * @var int
	 */
	protected $scope;

	/**
	 * Constructor.
	 *
	 * Sets the scope of the integration.
	 *
	 * @since 0.2
	 */
	public function __construct() {
		$this->define_scope();
	}

	/**
	 * Gets the scope this integration applies to.
	 *
	 * @since 0.2
	 *
	 * @return int Either WP_Service_Workers::SCOPE_FRONT, WP_Service_Workers::SCOPE_ADMIN, or
	 *             WP_Service_Workers::SCOPE_ALL.
	 */
	public function get_scope() {
		return $this->scope;
	}

	/**
	 * Defines the scope of this integration by setting `$this->scope`.
	 *
	 * @since 0.2
	 */
	abstract protected function define_scope();

	/**
	 * Gets the URLs for a given attachment image and size.
	 *
	 * @since 0.2
	 *
	 * @param int          $attachment_id Attachment ID.
	 * @param string|array $image_size    Image size.
	 * @return array Image URLs.
	 */
	protected function get_attachment_image_urls( $attachment_id, $image_size ) {
		if ( preg_match_all( '#(?:^|\s)(https://\S+)#', (string) wp_get_attachment_image_srcset( $attachment_id, $image_size ), $matches ) ) {
			return $matches[1];
		}

		return array();
	}
}
