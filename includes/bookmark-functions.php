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
 * @param int|WP_Bookmark $bookmark
 * @param string          $output   Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which
 *                                  correspond to an WP_Bookmark object, an associative array, or a numeric array,
 *                                  respectively. Default OBJECT.
 * @return array|object|null Type returned depends on $output value.
 */
function blinks_get_bookmark( $bookmark, $output = OBJECT ) {
	if ( empty( $bookmark ) && isset( $GLOBALS['link'] ) ) {
		$bookmark = $GLOBALS['link'];
	}

	if ( $bookmark instanceof WP_Bookmark ) {
		$_bookmark = $bookmark;
	} elseif ( is_object( $bookmark ) ) {
		$_bookmark = WP_Bookmark::get_instance( $bookmark->link_id );
	} else {
		$_bookmark = WP_Bookmark::get_instance( $bookmark );
	}

	if ( ! $_bookmark ) {
		return null;
	}

	if ( ARRAY_A === $output ) {
		return $_bookmark->to_array();
	} elseif ( ARRAY_N === $output ) {
		return array_values( $_bookmark->to_array() );
	}
				 
	return $_bookmark;
}

/**
 * Retrieves the list of bookmarks
 *
 * Attempts to retrieve from the cache first based on MD5 hash of arguments. If
 * that fails, then the query will be built from the arguments and executed. The
 * results will be stored to the cache.
 *
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string|array $args {
 *     Optional. String or array of arguments to retrieve bookmarks.
 *
 *     @type string   $orderby        How to order the links by. Accepts 'id', 'link_id', 'name', 'link_name',
 *                                    'url', 'link_url', 'visible', 'link_visible', 'rating', 'link_rating',
 *                                    'owner', 'link_owner', 'updated', 'link_updated', 'notes', 'link_notes',
 *                                    'description', 'link_description', 'length' and 'rand'.
 *                                    When `$orderby` is 'length', orders by the character length of
 *                                    'link_name'. Default 'name'.
 *     @type string   $order          Whether to order bookmarks in ascending or descending order.
 *                                    Accepts 'ASC' (ascending) or 'DESC' (descending). Default 'ASC'.
 *     @type int      $limit          Amount of bookmarks to display. Accepts any positive number or
 *                                    -1 for all.  Default -1.
 *     @type string   $category       Comma-separated list of category IDs to include links from.
 *                                    Default empty.
 *     @type string   $category_name  Category to retrieve links for by name. Default empty.
 *     @type int|bool $hide_invisible Whether to show or hide links marked as 'invisible'. Accepts
 *                                    1|true or 0|false. Default 1|true.
 *     @type int|bool $show_updated   Whether to display the time the bookmark was last updated.
 *                                    Accepts 1|true or 0|false. Default 0|false.
 *     @type string   $include        Comma-separated list of bookmark IDs to include. Default empty.
 *     @type string   $exclude        Comma-separated list of bookmark IDs to exclude. Default empty.
 *     @type string   $search         Search terms. Will be SQL-formatted with wildcards before and after
 *                                    and searched in 'link_url', 'link_name' and 'link_description'.
 *                                    Default empty.
 * }
 * @return object[] List of bookmark row objects.
 */
function get_blinks_bookmarks( $args = '' ) {
	global $wpdb;

	$defaults = array(
		'orderby'        => 'name',
		'order'          => 'ASC',
		'limit'          => -1,
		'category'       => '',
		'category_name'  => '',
		'hide_invisible' => 1,
		'show_updated'   => 0,
		'include'        => '',
		'exclude'        => '',
		'search'         => '',
	);

	$parsed_args = wp_parse_args( $args, $defaults );

	$key   = md5( serialize( $parsed_args ) );
	$cache = wp_cache_get( 'get_bookmarks', 'bookmark' );

	if ( 'rand' !== $parsed_args['orderby'] && $cache ) {
		if ( is_array( $cache ) && isset( $cache[ $key ] ) ) {
			$bookmarks = $cache[ $key ];
			/**
			 * Filters the returned list of bookmarks.
			 *
			 * The first time the hook is evaluated in this file, it returns the cached
			 * bookmarks list. The second evaluation returns a cached bookmarks list if the
			 * link category is passed but does not exist. The third evaluation returns
			 * the full cached results.
			 *
			 * @since 2.1.0
			 *
			 * @see get_bookmarks()
			 *
			 * @param array $bookmarks   List of the cached bookmarks.
			 * @param array $parsed_args An array of bookmark query arguments.
			 */
			return apply_filters( 'get_bookmarks', $bookmarks, $parsed_args );
		}
	}

	if ( ! is_array( $cache ) ) {
		$cache = array();
	}

	$inclusions = '';
	if ( ! empty( $parsed_args['include'] ) ) {
		$parsed_args['exclude']       = '';  // Ignore exclude, category, and category_name params if using include.
		$parsed_args['category']      = '';
		$parsed_args['category_name'] = '';

		$inclinks = wp_parse_id_list( $parsed_args['include'] );
		if ( count( $inclinks ) ) {
			foreach ( $inclinks as $inclink ) {
				if ( empty( $inclusions ) ) {
					$inclusions = ' AND ( link_id = ' . $inclink . ' ';
				} else {
					$inclusions .= ' OR link_id = ' . $inclink . ' ';
				}
			}
		}
	}
	if ( ! empty( $inclusions ) ) {
		$inclusions .= ')';
	}

	$exclusions = '';
	if ( ! empty( $parsed_args['exclude'] ) ) {
		$exlinks = wp_parse_id_list( $parsed_args['exclude'] );
		if ( count( $exlinks ) ) {
			foreach ( $exlinks as $exlink ) {
				if ( empty( $exclusions ) ) {
					$exclusions = ' AND ( link_id <> ' . $exlink . ' ';
				} else {
					$exclusions .= ' AND link_id <> ' . $exlink . ' ';
				}
			}
		}
	}
	if ( ! empty( $exclusions ) ) {
		$exclusions .= ')';
	}

	if ( ! empty( $parsed_args['category_name'] ) ) {
		$parsed_args['category'] = get_term_by( 'name', $parsed_args['category_name'], 'link_category' );
		if ( $parsed_args['category'] ) {
			$parsed_args['category'] = $parsed_args['category']->term_id;
		} else {
			$cache[ $key ] = array();
			wp_cache_set( 'get_bookmarks', $cache, 'bookmark' );
			/** This filter is documented in wp-includes/bookmark.php */
			return apply_filters( 'get_bookmarks', array(), $parsed_args );
		}
	}

	$search = '';
	if ( ! empty( $parsed_args['search'] ) ) {
		$like   = '%' . $wpdb->esc_like( $parsed_args['search'] ) . '%';
		$search = $wpdb->prepare( ' AND ( (link_url LIKE %s) OR (link_name LIKE %s) OR (link_description LIKE %s) ) ', $like, $like, $like );
	}

	$category_query = '';
	$join           = '';
	if ( ! empty( $parsed_args['category'] ) ) {
		$incategories = wp_parse_id_list( $parsed_args['category'] );
		if ( count( $incategories ) ) {
			foreach ( $incategories as $incat ) {
				if ( empty( $category_query ) ) {
					$category_query = ' AND ( tt.term_id = ' . $incat . ' ';
				} else {
					$category_query .= ' OR tt.term_id = ' . $incat . ' ';
				}
			}
		}
	}
	if ( ! empty( $category_query ) ) {
		$category_query .= ") AND taxonomy = 'link_category'";
		$join            = " INNER JOIN $wpdb->term_relationships AS tr ON ($wpdb->links.link_id = tr.object_id) INNER JOIN $wpdb->term_taxonomy as tt ON tt.term_taxonomy_id = tr.term_taxonomy_id";
	}

	if ( $parsed_args['show_updated'] ) {
		$recently_updated_test = ', IF (DATE_ADD(link_updated, INTERVAL 120 MINUTE) >= NOW(), 1,0) as recently_updated ';
	} else {
		$recently_updated_test = '';
	}

	$get_updated = ( $parsed_args['show_updated'] ) ? ', UNIX_TIMESTAMP(link_updated) AS link_updated_f ' : '';

	$orderby = strtolower( $parsed_args['orderby'] );
	$length  = '';
	switch ( $orderby ) {
		case 'length':
			$length = ', CHAR_LENGTH(link_name) AS length';
			break;
		case 'rand':
			$orderby = 'rand()';
			break;
		case 'link_id':
			$orderby = "$wpdb->links.link_id";
			break;
		default:
			$orderparams = array();
			$keys        = array( 'link_id', 'link_name', 'link_url', 'link_visible', 'link_rating', 'link_owner', 'link_updated', 'link_notes', 'link_description' );
			foreach ( explode( ',', $orderby ) as $ordparam ) {
				$ordparam = trim( $ordparam );

				if ( in_array( 'link_' . $ordparam, $keys, true ) ) {
					$orderparams[] = 'link_' . $ordparam;
				} elseif ( in_array( $ordparam, $keys, true ) ) {
					$orderparams[] = $ordparam;
				}
			}
			$orderby = implode( ',', $orderparams );
	}

	if ( empty( $orderby ) ) {
		$orderby = 'link_name';
	}

	$order = strtoupper( $parsed_args['order'] );
	if ( '' !== $order && ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
		$order = 'ASC';
	}

	$visible = '';
	if ( $parsed_args['hide_invisible'] ) {
		$visible = "AND link_visible = 'Y'";
	}

	$query  = "SELECT * $length $recently_updated_test $get_updated FROM $wpdb->links $join WHERE 1=1 $visible $category_query";
	$query .= " $exclusions $inclusions $search";
	$query .= " ORDER BY $orderby $order";
	if ( -1 != $parsed_args['limit'] ) {
		$query .= ' LIMIT ' . $parsed_args['limit'];
	}

	$results = $wpdb->get_results( $query );

	if ( 'rand()' !== $orderby ) {
		$cache[ $key ] = $results;
		wp_cache_set( 'get_bookmarks', $cache, 'bookmark' );
	}

	/** This filter is documented in wp-includes/bookmark.php */
	return apply_filters( 'get_bookmarks', $results, $parsed_args );
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
