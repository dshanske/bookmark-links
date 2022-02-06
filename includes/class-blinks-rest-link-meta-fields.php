<?php
/**
 * REST API: Blinks_Rest_Link_Meta_Fields class
 *
 * @package Bookmark-links
 */

/**
 * Class used to manage meta values for links via the REST API.
 *
 * @see WP_REST_Meta_Fields
 */
class Blinks_REST_Link_Meta_Fields extends WP_REST_Meta_Fields {

	/**
	 * Retrieves the link meta type.
	 *
	 * @return string The link meta type.
	 */
	protected function get_meta_type() {
		return 'link';
	}

	/**
	 * Retrieves the link meta type.
	 *
	 * @return string The link meta type.
	 */
	protected function get_link_type() {
		return 'link';
	}

	/**
	 * Retrieves the link meta subtype.
	 *
	 * @return string 'link' There are no subtypes.
	 */
	protected function get_meta_subtype() {
		return 'link';
	}

	/**
	 * Retrieves the type for register_rest_field().
	 *
	 * @return string The link REST field type.
	 */
	public function get_rest_field_type() {
		return 'link';
	}
}
