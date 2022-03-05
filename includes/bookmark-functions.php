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
 *                                    If empty, uses default link or bookmark category.
 *     @type int    $import_id        Optional. The link ID to be used when inserting a new link. If specified, must not match any existing link ID. Default 0.
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
		'import_id'   => 0,
	);

	$parsed_args = wp_parse_args( $linkdata, $defaults );

	$parsed_args = wp_unslash( sanitize_bookmark( $parsed_args, 'db' ) );

	$link_id   = $parsed_args['link_id'];
	$link_name = $parsed_args['link_name'];
	$link_url  = $parsed_args['link_url'];

	if ( 0 === $link_id && ! empty( $link_url ) ) {
		$matches = blinks_get_bookmarks(
			array(
				'fields' => 'id',
				'url'    => $link_url,
			)
		);
		if ( ! empty( $matches ) && is_array( $matches ) ) {
			return new WP_Error( 'duplicate_bookmark', __( 'This URL already exists in the database', 'bookmark-links' ) );
		}
	}

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
	$link_visible     = ( ! empty( $parsed_args['link_visible'] ) ) ? $parsed_args['link_visible'] : blinks_get_default_link_visible();
	$link_owner       = ( ! empty( $parsed_args['link_owner'] ) ) ? $parsed_args['link_owner'] : get_current_user_id();
	$link_updated     = ( ! empty( $parsed_args['link_updated'] ) ) ? $parsed_args['link_updated'] : current_time( 'mysql' );
	$link_notes       = ( ! empty( $parsed_args['link_notes'] ) ) ? $parsed_args['link_notes'] : '';
	$link_description = ( ! empty( $parsed_args['link_description'] ) ) ? $parsed_args['link_description'] : '';
	$link_rss         = ( ! empty( $parsed_args['link_rss'] ) ) ? $parsed_args['link_rss'] : '';
	$link_rel         = ( ! empty( $parsed_args['link_rel'] ) ) ? $parsed_args['link_rel'] : '';
	$link_category    = ( ! empty( $parsed_args['link_category'] ) ) ? $parsed_args['link_category'] : array();
	$import_id        = isset( $parsed_args['import_id'] ) ? $parsed_args['import_id'] : 0;

	// Make sure we set a valid category.
	if ( ! is_array( $link_category ) || 0 === count( $link_category ) ) {
		if ( empty( $link_rss ) ) {
			$link_category = array( get_option( 'default_bookmark_category' ) );
		} else {
			$link_category = array( get_option( 'default_link_category' ) );
		}
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
		// If there is a suggested ID, use it if not already present.
		if ( ! empty( $import_id ) ) {
			$import_id = (int) $import_id;
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->links WHERE link_id = %d", $import_id ) ) ) {
				$data['link_id'] = $import_id;
			}
		}
		if ( false === $wpdb->insert( $wpdb->links, $data ) ) {
			if ( $wp_error ) {
				return new WP_Error( 'db_insert_error', __( 'Could not insert link into the database.', 'default' ), $wpdb->last_error );
			} else {
				return 0;
			}
		}
		$link_id = (int) $wpdb->insert_id;
	}

	// If $link_categories isn't already an array, make it one:
	if ( ! is_array( $link_category ) || 0 === count( $link_category ) ) {
		$link_category = array( get_option( 'default_link_category' ) );
	}

	$link_category = array_map( 'intval', $link_category );
	$link_category = array_unique( $link_category );

	wp_set_object_terms( $link_id, $link_category, 'link_category' );

	if ( isset( $parsed_args['tags_input'] ) && is_object_in_taxonomy( 'link', 'link_tag' ) ) {
		wp_set_object_terms( $link_id, $parsed_args['tags_input'], 'link_tag' );
	}

	if ( ! empty( $parsed_args['tax_input'] ) ) {
		foreach ( $parsed_args['tax_input'] as $taxonomy => $tags ) {
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

	if ( isset( $parsed_args['link_toread'] ) ) {
		if ( $parsed_args['link_toread'] ) {
			update_link_meta( $link_id, 'link_toread', 1 );
		} else {
			delete_link_meta( $link_id, 'link_toread' );
		}
	} else {
		delete_link_meta( $link_id, 'link_toread' );
	}

	foreach ( array_keys( get_registered_meta_keys( 'link' ) ) as $property ) {
		if ( isset( $parsed_args[ $property ] ) ) {
			if ( empty( $parsed_args[ $property ] ) ) {
				delete_link_meta( $link_id, $property );
			} else {
				update_link_meta( $link_id, $property, $parsed_args[ $property ] );
			}
		}
	}

	if ( ! empty( $parsed_args['meta_input'] ) ) {
		foreach ( $parsed_args['meta_input'] as $field => $value ) {
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
	$linkdata = (array) $linkdata;
	$link_id  = (int) $linkdata['link_id'];
	$link     = blinks_get_bookmark( $link_id, ARRAY_A );
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

/**
 * Return a list of bookmarks
 *
 * @param int|WP_Bookmark $bookmarks Array of Bookmarks
 * @param array Arguments
 * @return string
 */
function blinks_list_bookmarks( $bookmarks, $args = array() ) {
	$defaults    = array(
		'show_updated'     => 0,
		'show_description' => 1,
		'show_images'      => 0,
		'show_name'        => 1,
		'before'           => '<li>',
		'after'            => '</li>',
		'between'          => "\n",
		'show_rating'      => 0,
		'link_before'      => '',
		'link_after'       => '',
		'container'        => 'ul',
		'container-class'  => 'bookmarks',
	);
	$parsed_args = wp_parse_args( $args, $defaults );
	$content     = '';
	foreach ( $bookmarks as $bookmark ) {
		$content .= $parsed_args['before'] . blinks_get_the_bookmark(
			$bookmark,
			$parsed_args
		) . $parsed_args['after'];
	}
	return sprintf( '<%1$s class="%2$s">%3$s</%1$s>', $parsed_args['container'], $parsed_args['container-class'], $content );
}

/**
 * Return a marked up single bookmark.
 *
 * @param int|WP_Bookmark $bookmark Bookmark
 * @param array           $args Arguments.
 * @return string Outputted string.
 */
function blinks_get_the_bookmark( $bookmark, $args = array() ) {
	$defaults    = array(
		'show_updated'     => 0,
		'show_description' => 1,
		'show_images'      => 0,
		'show_name'        => 1,
		'between'          => "\n",
		'show_rating'      => 0,
		'link_before'      => '',
		'link_after'       => '',
	);
	$parsed_args = wp_parse_args( $args, $defaults );

	$bookmark = blinks_get_bookmark( $bookmark );
	if ( ! $bookmark ) {
		return '';
	}

	$output = $parsed_args['before'];

	if ( $parsed_args['show_updated'] ) {
		$output .= '<em>';
	}

		$the_link = '#';
	if ( ! empty( $bookmark->link_url ) ) {
		$the_link = esc_url( $bookmark->link_url );
	}

		$desc  = esc_attr( sanitize_bookmark_field( 'link_description', $bookmark->link_description, $bookmark->link_id, 'display' ) );
		$name  = esc_attr( sanitize_bookmark_field( 'link_name', $bookmark->link_name, $bookmark->link_id, 'display' ) );
		$title = $desc;

	if ( $parsed_args['show_updated'] && '00' !== substr( $bookmark->link_updated, 0, 2 ) ) {
		$updated = get_link_datetime( $bookmark );
		$title  .= ' (';
		$title  .= sprintf(
			/* translators: %s: Date and time of last update. */
			__( 'Bookmarked: %s', 'bookmark-links' ),
			$updated->format( get_option( 'date_format' ) )
		);
		$title .= ')';
	}
		$alt = ' alt="' . $name . ( $args['show_description'] ? ' ' . $title : '' ) . '"';

	if ( '' !== $title ) {
		$title = ' title="' . $title . '"';
	}

	$rel = $bookmark->link_rel;

	$target = $bookmark->link_target;

	if ( '' !== $target ) {
		if ( is_string( $rel ) && '' !== $rel ) {
			if ( ! str_contains( $rel, 'noopener' ) ) {
				$rel = trim( $rel ) . ' noopener';
			}
		} else {
			$rel = 'noopener';
		}

		$target = ' target="' . $target . '"';
	}

	if ( '' !== $rel ) {
		$rel = ' rel="' . esc_attr( $rel ) . '"';
	}

	$output .= '<a href="' . $the_link . '"' . $rel . $title . $target . '>';
	$output .= $args['link_before'];

	if ( null != $bookmark->link_image && $parsed_args['show_images'] ) {
		if ( strpos( $bookmark->link_image, 'http' ) === 0 ) {
			$output .= "<img src=\"$bookmark->link_image\" $alt $title />";
		} else { // If it's a relative path.
			$output .= '<img src="' . get_option( 'siteurl' ) . "$bookmark->link_image\" $alt $title />";
		}
		if ( $parsed_args['show_name'] ) {
			$output .= " $name";
		}
	} else {
		$output .= $name;
	}

		$output .= $parsed_args['link_after'];

		$output .= '</a>';

	if ( $parsed_args['show_updated'] ) {
		$output .= '</em>';
	}

	if ( $parsed_args['show_description'] && '' !== $desc ) {
		$output .= $parsed_args['between'] . $desc;
	}

	if ( $parsed_args['show_rating'] ) {
		$output .= $parsed_args['between'] . sanitize_bookmark_field(
			'link_rating',
			$bookmark->link_rating,
			$bookmark->link_id,
			'display'
		);
	}
	$output .= $parsed_args['after'] . "\n";
	return $output;

}


/*
 * Make Bookmark Post using a list of links
 *
 * $param array $links An array of link_ids or of WP_Bookmark Objects
 * @return int Post ID.
 */

function blinks_make_post( $links ) {
	$content = blinks_list_bookmarks(
		$links,
		array(
			'before' => '<li>',
			'after'  => '</li>',
		)
	);
	return wp_insert_post(
		array(
			'post_content' => $content,
			'post_status'  => 'draft',
			'post_title'   => current_datetime()->format( get_option( 'date_format' ) ),
		)
	);
}


/*
 * Prepare bookmarks as an array for export.
 *
 * $param array $args Query arguments, a subset of those accepted by Bookmark Query
 * @return array
 */

function blinks_prepare_export_bookmarks( $args = array() ) {
	$defaults   = array(
		'owner__in'        => '',
		'owner__not_in'    => '',
		'bookmark__in'     => '',
		'bookmark__not_in' => '',
		'category'         => '',
		'tag'              => '',
		'taxonomy'         => '',
		'term'             => '',
		'toread'           => 'all',
		'date_query'       => null, // See WP_Date_Query.
		'meta_key'         => '',
		'meta_value'       => '',
		'meta_query'       => '',
		'hide_invisible'   => 1,
	);
	$args       = wp_array_slice_assoc( $args, array_keys( $defaults ) );
	$args       = wp_parse_args( $args, $defaults );
	$_bookmarks = blinks_get_bookmarks( $args );
	$bookmarks  = array();
	foreach ( $_bookmarks as $bookmark ) {
		$b = $bookmark->to_array();
		unset( $b['link_id'] );

		// Export updated with timezone offset
		$updated = new DateTime( $b['link_updated'] );
		$updated->setTimeZone( $wptz );
		$b['link_updated'] = $updated->format( DATE_W3C );

		$b['meta'] = get_link_meta( $bookmark->link_id );
		$b['tags'] = $bookmark->tags_input;

		if ( ! empty( $b['link_category'] ) ) {
			$b['categories'] = wp_get_object_terms( $bookmark->link_id, 'link_category', array( 'fields' => 'names' ) );
			unset( $b['link_category'] );
		}

		$owner = get_user_by( 'id', $bookmark->link_owner );
		if ( $owner ) {
			$b['link_owner'] = $owner->display_name;
		}

		$bookmarks[] = $b;
	}
	return $bookmarks;
}


/*
 * Fetch and Refresh a Bookmarks Metadata
 *
 * $param int $link_id
 * @return boolean|WP_Error
 */
function blinks_refresh_bookmark( $link_id ) {
	$bookmark = blinks_get_bookmark( $link_id, ARRAY_A );
	if ( isset( $bookmark['link_url'] ) ) {
		$parse = new Parse_This( $bookmark['link_url'] );
		$fetch = $parse->fetch();
		if ( ! is_wp_error( $fetch ) ) {
			$parse->parse();
			$results = $parse->get();
			if ( isset( $results['name'] ) && ( $bookmark['link_name'] === $bookmark['link_url'] || empty( $bookmark['link_name'] ) ) ) {
				$bookmark['link_name'] = $results['name'];
			}
			if ( empty( $bookmark['link_image'] ) ) {
				if ( isset( $results['featured'] ) ) {
					$bookmark['link_image'] = $results['featured'];
				} elseif ( isset( $results['photo'] ) ) {
					if ( is_string( $results['photo'] ) ) {
						$bookmark['link_image'] = $results['photo'];
					} elseif ( is_array( $results['photo'] ) ) {
						$bookmark['link_image'] = $results['photo'][0];
					}
				}
			}

			if ( empty( $bookmark['link_published'] ) && isset( $results['published'] ) ) {
				$bookmark['link_published'] = $results['published'];
			}

			if ( isset( $results['author'] ) ) {
				if ( isset( $results['author']['name'] ) && empty( $bookmark['link_author'] ) ) {
					$bookmark['link_author'] = $results['author']['name'];
				}
				if ( isset( $results['author']['url'] ) && empty( $bookmark['link_author_url'] ) ) {
					$bookmark['link_author_url'] = $results['author']['url'];
				}
				if ( isset( $results['author']['photo'] ) && empty( $bookmark['link_author_photo'] ) && is_string( $results['author']['photo'] ) ) {
					$bookmark['link_author_photo'] = $results['author']['photo'];
				}
			}
			if ( isset( $results['publication'] ) ) {
				if ( is_string( $results['publication'] ) ) {
					$bookmark['link_site'] = $results['publication'];
				} elseif ( is_array( $results['publication'] ) ) {
					if ( isset( $results['publication']['name'] ) ) {
						$bookmark['link_site'] = $results['publication']['name'];
					}
					if ( isset( $results['publication']['url'] ) ) {
						$bookmark['link_site_url'] = $results['publication']['url'];
					}
				}
			}
			if ( isset( $results['type'] ) && 'feed' === $results['type'] ) {
				$bookmark['link_rss'] = $results['url'];
			}
			if ( isset( $results['category'] ) ) {
				if ( is_array( $results['category'] ) ) {
					$results['category'] = implode( ',', $results['category'] );
				}
				$bookmark['tags_input'] = $results['category'];
			}
		}
		return blinks_update_bookmark( $bookmark, true );
	}
	return false;
}
