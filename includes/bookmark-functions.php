<?php
/**
 * Enhanced Bookmark API
 *
 * @package Bookmark_Links
 */

/**
 * Retrieve Bookmark data
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int|WP_Bookmark $bookmark Optional.
 * @param string          $output   Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which
 *                                  correspond to an WP_Bookmark object, an associative array, or a numeric array,
 *                                  respectively. Default OBJECT.
 * @param string          $filter   Optional. How to sanitize bookmark fields.
 * @return array|object|null Type returned depends on $output value.
 */
function blinks_get_bookmark( $bookmark = null, $output = OBJECT, $filter = 'raw' ) {
	if ( empty( $bookmark ) && isset( $GLOBALS['link'] ) ) {
		$bookmark = $GLOBALS['link'];
	}

	if ( $bookmark instanceof WP_Bookmark ) {
		$_bookmark = $bookmark;
	} elseif ( is_object( $bookmark ) ) {
		if ( empty( $bookmark->filter ) ) {
			$_bookmark = sanitize_bookmark( $bookmark, 'raw' );
			$_bookmark = new WP_Bookmark( $bookmark );
		} elseif ( 'raw' === $bookmark->filter ) {
			$_bookmark = new WP_Bookmark( $bookmark );
		} else {
			$_bookmark = WP_Bookmark::get_instance( $bookmark );
		}
	} else {
		$_bookmark = WP_Bookmark::get_instance( $bookmark );
	}

	if ( ! $_bookmark ) {
		return null;
	}

	$_bookmark = $_bookmark->filter( $filter );

	if ( ARRAY_A === $output ) {
		return $_bookmark->to_array();
	} elseif ( ARRAY_N === $output ) {
		return array_values( $_bookmark->to_array() );
	}

	return $_bookmark;
}

function blinks_get_default_link_visible() {
	$option = get_option( 'link_visible' );
	return $option ? 'Y' : 'N';
}

/**
 * Inserts a link into the database, or updates an existing link.
 *
 * Runs all the necessary sanitizing, provides default values if arguments are missing,
 * and finally saves the link. Updated version of wp_insert_link with extra functionality.
 * Core function does not, despite doc, allow for updating link_updated.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array $linkdata {
 *     Elements that make up the link to insert.
 *
 *     @type int    $link_id          Optional. The ID of the existing link if updating.
 *     @type string $link_url         The URL the link points to.
 *     @type string $link_name        The title of the link.
 *     @type string $link_image       Optional. A URL of an image.
 *     @type string $link_target      Optional. The target element for the anchor tag.
 *     @type string $link_description Optional. A short description of the link.
 *     @type string $link_visible     Optional. 'Y' means visible, anything else means not.
 *     @type int    $link_owner       Optional. A user ID.
 *     @type int    $link_rating      Optional. A rating for the link.
 *     @type string $link_updated     Optional. When the link was last updated.
 *     @type string $link_rel         Optional. A relationship of the link to you.
 *     @type string $link_notes       Optional. An extended description of or notes on the link.
 *     @type string $link_rss         Optional. A URL of an associated RSS feed.
 *     @type int    $link_category    Optional. The term ID of the link category.
 *                                    If empty, uses default link category.
 * }
 * @param bool  $wp_error Optional. Whether to return a WP_Error object on failure. Default false.
 * @return int|WP_Error Value 0 or WP_Error on failure. The link ID on success.
 */
function blinks_insert_bookmark( $linkdata, $wp_error = false ) {
	global $wpdb;

	$defaults = array(
		'link_id'     => 0,
		'link_name'   => '',
		'link_url'    => '',
		'link_rating' => 0,
	);

	$parsed_args = wp_parse_args( $linkdata, $defaults );

	$parsed_args = wp_unslash( sanitize_bookmark( $parsed_args, 'db' ) );

	$link_id   = $parsed_args['link_id'];
	$link_name = $parsed_args['link_name'];
	$link_url  = $parsed_args['link_url'];

	$update = false;
	if ( ! empty( $link_id ) ) {
		$update = true;
	}

	if ( '' === trim( $link_name ) ) {
		if ( '' !== trim( $link_url ) ) {
			$link_name = $link_url;
		} else {
			return 0;
		}
	}

	if ( '' === trim( $link_url ) ) {
		return 0;
	}

	$link_rating      = ( ! empty( $parsed_args['link_rating'] ) ) ? $parsed_args['link_rating'] : 0;
	$link_image       = ( ! empty( $parsed_args['link_image'] ) ) ? $parsed_args['link_image'] : '';
	$link_target      = ( ! empty( $parsed_args['link_target'] ) ) ? $parsed_args['link_target'] : '';
	$link_visible     = ( ! empty( $parsed_args['link_visible'] ) ) ? $parsed_args['link_visible'] : 'Y';
	$link_owner       = ( ! empty( $parsed_args['link_owner'] ) ) ? $parsed_args['link_owner'] : get_current_user_id();
	$link_updated     = ( ! empty( $parsed_args['link_updated'] ) ) ? $parsed_args['link_updated'] : current_time( 'mysql' );
	$link_notes       = ( ! empty( $parsed_args['link_notes'] ) ) ? $parsed_args['link_notes'] : '';
	$link_description = ( ! empty( $parsed_args['link_description'] ) ) ? $parsed_args['link_description'] : blinks_get_default_link_visible();
	$link_rss         = ( ! empty( $parsed_args['link_rss'] ) ) ? $parsed_args['link_rss'] : '';
	$link_rel         = ( ! empty( $parsed_args['link_rel'] ) ) ? $parsed_args['link_rel'] : '';
	$link_category    = ( ! empty( $parsed_args['link_category'] ) ) ? $parsed_args['link_category'] : array();

	// Make sure we set a valid category.
	if ( ! is_array( $link_category ) || 0 === count( $link_category ) ) {
		$link_category = array( get_option( 'default_link_category' ) );
	}

	$data = compact( 'link_url', 'link_name', 'link_image', 'link_target', 'link_description', 'link_visible', 'link_owner', 'link_updated', 'link_rating', 'link_rel', 'link_notes', 'link_rss' );

	$emoji_fields = array( 'link_name', 'link_notes', 'link_description' );

	foreach ( $emoji_fields as $emoji_field ) {
		if ( isset( $data[ $emoji_field ] ) ) {
			$charset = $wpdb->get_col_charset( $wpdb->links, $emoji_field );
			if ( 'utf8' === $charset ) {
				$data[ $emoji_field ] = wp_encode_emoji( $data[ $emoji_field ] );
			}
		}
	}

	 /**
		  * Filters slashed link data just before it is inserted into the database.
	  *
	  * @param array $data                An array of slashed, sanitized, and processed link data.
	  * @param array $linkdata            The array originally passed to the blinks_insert_bookmark function.
	  */
	$data = apply_filters( 'blinks_insert_bookmark_data', $data, $linkdata );

	if ( $update ) {
		if ( false === $wpdb->update( $wpdb->links, $data, compact( 'link_id' ) ) ) {
			if ( $wp_error ) {
				return new WP_Error( 'db_update_error', __( 'Could not update link in the database.', 'default' ), $wpdb->last_error );
			} else {
				return 0;
			}
		}
	} else {
		if ( false === $wpdb->insert( $wpdb->links, $data ) ) {
			if ( $wp_error ) {
				return new WP_Error( 'db_insert_error', __( 'Could not insert link into the database.', 'default' ), $wpdb->last_error );
			} else {
				return 0;
			}
		}
		$link_id = (int) $wpdb->insert_id;
	}

	wp_set_link_cats( $link_id, $link_category );

	if ( isset( $linkdata['tags_input'] ) && is_object_in_taxonomy( 'link', 'link_tag' ) ) {
		wp_set_object_terms( $link_id, $linkdata['tags_input'], 'link_tag' );
	}

	if ( ! empty( $linkdata['tax_input'] ) ) {
		foreach ( $linkdata['tax_input'] as $taxonomy => $tags ) {
			$taxonomy_obj = get_taxonomy( $taxonomy );

			if ( ! $taxonomy_obj ) {
				continue;
			}

			// array = hierarchical, string = non-hierarchical.
			if ( is_array( $tags ) ) {
				$tags = array_filter( $tags );
			}

			if ( current_user_can( $taxonomy_obj->cap->assign_terms ) ) {
				wp_set_object_terms( $link_id, $tags, $taxonomy );
			}
		}
	}

	if ( isset( $linkdata['link_toread'] ) ) {
		if ( $linkdata['link_toread'] ) {
			update_link_meta( $link_id, 'link_toread', 1 );
		} else {
			delete_link_meta( $link_id, 'link_toread' );
		}
	} else {
		delete_link_meta( $link_id, 'link_toread' );
	}

	foreach ( array( 'link_site', 'link_site_url', 'link_author', 'link_author', 'link_author_url', 'link_author_photo' ) as $property ) {
		if ( isset( $linkdata[ $property ] ) ) {
			if ( empty( $linkdata[ $property ] ) ) {
				delete_link_meta( $link_id, $property );
			} else {
				update_link_meta( $link_id, $property, $linkdata[ $property ] );
			}
		}
	}

	if ( ! empty( $linkdata['meta_input'] ) ) {
		foreach ( $linkdata['meta_input'] as $field => $value ) {
			update_link_meta( $link_id, $field, $value );
		}
	}

	clean_bookmark_cache( $link_id );

	$bookmark = get_bookmark( $link_id );

	if ( $update ) {
		/**
		 * Fires after a link was updated in the database.
		 *
		 * @param int $link_id ID of the link that was updated.
		 * @param array $parsed_args A sanitized version of the data originally passed to the insert function.
		 */
		do_action( 'edit_bookmark', $link_id, $parsed_args );

			/**
			 * Fires after a link was updated in the database.
			 *
		 * @since 2.0.0
		 *
		 * @param int $link_id ID of the link that was updated.
		 */
		do_action( 'edit_link', $link_id );
	} else {
		/**
		 * Fires after a link was added to the database.
		 *
		 * @since 2.0.0
		 *
		 * @param int $link_id ID of the link that was added.
		 */
		do_action( 'add_link', $link_id );
	}

	/**
	 * Fires after a bookmark has been saved.
	 *
	 * @param int $link_id ID of the link that was inserted.
	 * @param WP_Bookmark $bookmark Bookmark object.
	 * @param bool $update Whether this is an existing bookmark being updated.
	 */
	do_action( 'insert_bookmark', $link_id, $bookmark, $update );

	return $link_id;
}

/**
 * Deletes a specified link from the database.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int $link_id ID of the link to delete
 * @return true Always true.
 */
function blinks_delete_bookmark( $link_id ) {
	global $wpdb;
	/**
	 * Fires before a link is deleted.
	 *
	 * @since 2.0.0
	 *
	 * @param int $link_id ID of the link to delete.
	 */
	do_action( 'delete_link', $link_id );

	wp_delete_object_term_relationships( $link_id, get_object_taxonomies( 'link' ) );

	$link_meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT meta_id FROM $wpdb->linkmeta WHERE link_id = %d ", $link_id ) );
	foreach ( $link_meta_ids as $mid ) {
		delete_metadata_by_mid( 'link', $mid );
	}

	$wpdb->delete( $wpdb->links, array( 'link_id' => $link_id ) );

	/**
	 * Fires after a link has been deleted.
	 *
	 * @since 2.2.0
	 *
	 * @param int $link_id ID of the deleted link.
	 */
	do_action( 'deleted_link', $link_id );

	clean_bookmark_cache( $link_id );

	return true;
}

/**
 * Updates a link into the database, or updates an existing link.
 *
 * @param array $linkdata {
 *     Elements that make up the link to insert.
 *
 *     @type int    $link_id          Optional. The ID of the existing link if updating.
 *     @type string $link_url         The URL the link points to.
 *     @type string $link_name        The title of the link.
 *     @type string $link_image       Optional. A URL of an image.
 *     @type string $link_target      Optional. The target element for the anchor tag.
 *     @type string $link_description Optional. A short description of the link.
 *     @type string $link_visible     Optional. 'Y' means visible, anything else means not.
 *     @type int    $link_owner       Optional. A user ID.
 *     @type int    $link_rating      Optional. A rating for the link.
 *     @type string $link_updated     Optional. When the link was last updated.
 *     @type string $link_rel         Optional. A relationship of the link to you.
 *     @type string $link_notes       Optional. An extended description of or notes on the link.
 *     @type string $link_rss         Optional. A URL of an associated RSS feed.
 *     @type int    $link_category    Optional. The term ID of the link category.
 *                                    If empty, uses default link category.
 * }
 * @param bool  $wp_error Optional. Whether to return a WP_Error object on failure. Default false.
 * @return int|WP_Error Value 0 or WP_Error on failure. The link ID on success.
 */
function blinks_update_bookmark( $linkdata, $wp_error = false ) {
	$link_id = (int) $linkdata['link_id'];
	$link    = blinks_get_bookmark( $link_id, ARRAY_A );
	// Escape data pulled from DB.
	$link = wp_slash( $link );

	// Passed link category list overwrites existing category list if not empty.
	if ( isset( $linkdata['link_category'] ) && is_array( $linkdata['link_category'] )
		   && count( $linkdata['link_category'] ) > 0
	) {
		$link_cats = $linkdata['link_category'];
	} else {
		$link_cats = $link['link_category'];
	}

	// Merge old and new fields with new fields overwriting old ones.
	$linkdata                  = array_merge( $link, $linkdata );
	$linkdata['link_category'] = $link_cats;
	return blinks_insert_bookmark( $linkdata );
}


/**
 * Retrieves the list of bookmarks
 *
 * Wrapper around Blinks_Bookmark_Query.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array $args See Blinks_Bookmark_Query for parameters
 * @return array See Blinks_Bookmark_Query for format.
 * }
 */
function blinks_get_bookmarks( $args = array() ) {
	$query = new Blinks_Bookmark_Query();
	return $query->query( $args );
}

if ( ! function_exists( 'get_link_timestamp' ) ) {
	/**
	 * Retrieve link updated time as a Unix timestamp.
	 *
	 * Note that this function returns a true Unix timestamp, not summed with timezone offset
	 * like older WP functions.
	 *
	 * @param int|WP_Bookmark $link  WP_Bookmark object or ID.
	 * @return int|false Unix timestamp on success, false on failure.
	 */
	function get_link_timestamp( $link ) {
		$datetime = get_link_datetime( $post );
		if ( false === $datetime ) {
			return false;
		}
		return $datetime->getTimestamp();
	}
}


if ( ! function_exists( 'get_link_datetime' ) ) {
	/**
	 * Retrieve link updated time as a `DateTimeImmutable` object instance.
	 *
	 * The object will be set to the timezone from WordPress settings.
	 *
	 * @param int|WP_Bookmark $link  WP_Bookmark object or ID.
	 * @return DateTimeImmutable|false Time object on success, false on failure.
	 */
	function get_link_datetime( $link ) {
		$link = new WP_Bookmark( $link );
		if ( ! $link ) {
			return false;
		}
		$time = $link->link_updated;
		if ( empty( $time ) || '0000-00-00 00:00:00' === $time ) {
			return false;
		}
		return date_create_immutable_from_format( 'Y-m-d H:i:s', $time, wp_timezone() );
	}
}


if ( ! function_exists( 'update_bookmark_cache' ) ) {

	/**
	 * Updates the bookmark cache of given bookmarks.
	 *
	 * Will add the bookmarks in $bookmarks to the cache. If bookmark ID already exists
	 * in the bookmark cache then it will not be updated. The bookmark is added to the
	 * cache using the bookmark group with the key using the ID of the bookmarks.
	 *
	 * @param WP_Bookmark[] $comments          Array of bookmark objects
	 * @param bool          $update_meta_cache Whether to update bookmarkmeta cache. Default true.
	 */
	function update_bookmark_cache( $bookmarks, $update_meta_cache = true ) {
		foreach ( (array) $bookmarks as $bookmark ) {
			wp_cache_add( $bookmark->link_id, $bookmark, 'bookmark' );
		}

		if ( $update_meta_cache ) {
			// Avoid `wp_list_pluck()` in case `$bookmarks` is passed by reference.
			$bookmark_ids = array();
			foreach ( $bookmarks as $bookmark ) {
				$bookmark_ids[] = $bookmark->link_id;
			}
			update_meta_cache( 'link', $bookmark_ids );
		}
	}
}

if ( ! function_exists( 'prime_bookmark_caches' ) ) {

	/**
	 * Adds any bookmarks from the given IDs to the cache that do not already exist in cache.
	 *
	 * @see update_bookmark_cache()
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int[] $bookmark_ids       Array of bookmark IDs.
	 * @param bool  $update_meta_cache Optional. Whether to update the meta cache. Default true.
	 */
	function prime_bookmark_caches( $bookmark_ids, $update_meta_cache = true ) {
		global $wpdb;

		$non_cached_ids = array();
		$cache_values   = wp_cache_get_multiple( $bookmark_ids, 'bookmark' );
		foreach ( $cache_values as $id => $value ) {
			if ( ! $value ) {
				$non_cached_ids[] = (int) $id;
			}
		}

		if ( ! empty( $non_cached_ids ) ) {
			$fresh_bookmarks = $wpdb->get_results( sprintf( "SELECT $wpdb->links.* FROM $wpdb->links WHERE link_id IN (%s)", implode( ',', array_map( 'intval', $non_cached_ids ) ) ) );

			foreach ( $fresh_bookmarks as $key => $bookmark ) {
				$fresh_bookmarks[ $key ]->link_category = array_unique( wp_get_object_terms( $bookmark->link_id, 'link_category', array( 'fields' => 'ids' ) ) );
			}

			update_bookmark_cache( $fresh_bookmarks, $update_meta_cache );
		}
	}
}

/**
 * Displays a bookmark action link.
 *
 * @param int|WP_Bookmark $link Optional. Bookmark ID. Default is the ID of the current bookmark.
 * @param
 * @return string|void The edit bookmark link URL.
 */
function get_bookmark_action_link( $link = 0, $action = 'edit' ) {
	$link = blinks_get_bookmark( $link );

	if ( ! current_user_can( 'manage_links' ) ) {
		return;
	}

	$location = add_query_arg(
		array(
			'action'  => $action,
			'link_id' => $link->link_id,
		),
		admin_url( 'link.php' )
	);

	/**
	 * Filters the bookmark edit link.
	 *
	 * @since 2.7.0
	 *
	 * @param string $location The edit link.
	 * @param int    $link_id  Bookmark ID.
	 * @param string $action The Action.
	 */
	$location = apply_filters( 'get_bookmark_action_link', $location, $link->link_id, $action );
	$action   = $action . '-bookmark_' . $link->link_id;
	return wp_nonce_url( $location, $action );
}
