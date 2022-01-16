<?php
/**
 * Global Functions.
 *
 * @package Bookmark_Links
 */

/**
 * Adds meta data field to a link.
 *
 * @param int    $link_id    Link ID.
 * @param string $meta_key   Metadata name.
 * @param mixed  $meta_value Metadata value.
 * @param bool   $unique     Optional, default is false. Whether the same key should not be added.
 * @return int|false Meta ID on success, false on failure.
 */
function add_link_meta( $link_id, $meta_key, $meta_value, $unique = false ) {
	return add_metadata( 'link', $link_id, $meta_key, $meta_value, $unique );
}

/**
 * Removes metadata matching criteria from a link.
 *
 * You can match based on the key, or key and value. Removing based on key and
 * value, will keep from removing duplicate metadata with the same key. It also
 * allows removing all metadata matching key, if needed.
 *
 * @param int    $link_id    Link ID.
 * @param string $meta_key   Metadata name.
 * @param mixed  $meta_value Optional. Metadata value.
 * @return bool True on success, false on failure.
 */
function delete_link_meta( $link_id, $meta_key, $meta_value = '' ) {
	return delete_metadata( 'link', $link_id, $meta_key, $meta_value );
}

/**
 * Retrieve meta field for a link.
 *
 * @param int    $link_id link ID.
 * @param string $key     Optional. The meta key to retrieve. By default, returns data for all keys.
 * @param bool   $single  Whether to return a single value.
 * @return mixed Will be an array if $single is false. Will be value of meta data field if $single is true.
 */
function get_link_meta( $link_id, $key = '', $single = false ) {
	return get_metadata( 'link', $link_id, $key, $single );
}

/**
 * Update link meta field based on link ID.
 *
 * Use the $prev_value parameter to differentiate between meta fields with the
 * same key and link ID.
 *
 * If the meta field for the user does not exist, it will be added.
 *
 * @param int    $link_id   Link ID.
 * @param string $meta_key   Metadata key.
 * @param mixed  $meta_value Metadata value.
 * @param mixed  $prev_value Optional. Previous value to check before removing.
 * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure.
 */
function update_link_meta( $link_id, $meta_key, $meta_value, $prev_value = '' ) {
	return update_metadata( 'link', $link_id, $meta_key, $meta_value, $prev_value );
}

/**
 * Retrieve Bookmark data
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int|WP_Bookmark $bookmark
 * @param string          $output   Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which
 *                                  correspond to an stdClass object, an associative array, or a numeric array,
 *                                  respectively. Default OBJECT.
 * @param string          $filter   Optional. How to sanitize bookmark fields. Default 'raw'.
 * @return array|object|null Type returned depends on $output value.
 */
function get_bookmark_object( $bookmark, $output = OBJECT, $filter = 'raw' ) {
	$bookmark = get_bookmark( $bookmark, $output, $filter );
	if ( OBJECT === $output ) {
		return new WP_Bookmark( $bookmark );
	}
}

function get_link_terms_to_edit( $link_id, $taxonomy = 'link_tag' ) {
	$link_id = (int) $link_id;
	if ( ! $link_id ) {
		return false;
	}

	$terms = get_object_term_cache( $link_id, $taxonomy );
	if ( false === $terms ) {
		$terms = wp_get_object_terms( $link_id, $taxonomy );
		wp_cache_add( $link_id, wp_list_pluck( $terms, 'term_id' ), $taxonomy . '_relationships' );
	}

	if ( ! $terms ) {
		return false;
	}
	if ( is_wp_error( $terms ) ) {
		return $terms;
	}
	$term_names = array();
	foreach ( $terms as $term ) {
		$term_names[] = $term->name;
	}

	$terms_to_edit = esc_attr( implode( ',', $term_names ) );

	return $terms_to_edit;
}
