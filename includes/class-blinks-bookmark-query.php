<?php
/**
 * Bookmark API: Blinks_Bookmark_Query class
 *
 * @package Bookmark_Links
 */

/**
 * Class for Bookmark Queries
 *
 * @see Blinks_Bookmark_Query::__construct() for accepted arguments.
 */
class Blinks_Bookmark_Query {

	/**
	 * SQL for database query.
	 *
	 * @var string
	 */
	public $request;

	/**
	 * Metadata query container
	 *
	 * @var WP_Meta_Query A meta query instance.
	 */
	public $meta_query = false;

	/**
	 * Metadata query clauses.
	 *
	 * @var array
	 */
	protected $meta_query_clauses;

	/**
	 * SQL query clauses.
	 *
	 * @var array
	 */
	protected $sql_clauses = array(
		'select'  => '',
		'from'    => '',
		'where'   => array(),
		'groupby' => '',
		'orderby' => '',
		'limits'  => '',
	);

	/**
	 * SQL WHERE clause.
	 *
	 * Stored after the {@see 'bookmarks_clauses'} filter is run on the compiled WHERE sub-clauses.
	 *
	 * @var string
	 */
	protected $filtered_where_clause;

	/**
	 * Date query container
	 *
	 * @var WP_Date_Query A date query instance.
	 */
	public $date_query = false;

	/**
	 * Query vars set by the user.
	 *
	 * @var array
	 */
	public $query_vars;

	/**
	 * Default values for query vars.
	 *
	 * @var array
	 */
	public $query_var_defaults;

	/**
	 * List of bookmarks located by the query.
	 *
	 * @var array
	 */
	public $bookmarks;

	/**
	 * The amount of found bookmarks for the current query.
	 *
	 * @var int
	 */
	public $found_bookmarks = 0;

	/**
	 * The number of pages.
	 *
	 * @var int
	 */
	public $max_num_pages = 0;

	/**
	 * Constructor.
	 *
	 * Sets up the bookmark query, based on the query vars passed.
	 *
	 * @param string|array $query {
	 *     Optional. Array or query string of bookmark query parameters. Default empty.
	 *
	 *     @type string       $link_name                    Link name. Default empty.
	 *     @type string       $link_url                     Link URL. Default empty.
	 *     @type int[]        $owner__in                Array of owner IDs to include links for. Default empty.
	 *     @type int[]        $owner__not_in            Array of owner IDs to exclude links for. Default empty.
	 *     @type int[]        $bookmark__in               Array of link IDs to include. Default empty.
	 *     @type int[]        $bookmark__not_in           Array of link IDs to exclude. Default empty.
	 *     @type bool         $count                     Whether to return a bookmark count (true) or array of
	 *                                                   bookmark objects (false). Default false.
	 *     @type array        $date_query                Date query clauses to limit bookmarks by. See WP_Date_Query.
	 *                                                   Default null.
	 *     @type string       $fields                    Fields to return. Accepts:
	 *                                                   - '' Returns an array of complete bookmark objects (`WP_Bookmark[]`).
	 *                                                    - 'ids' Returns an array of link ids (`int[]`).
	 *                                                    - 'id=>url' Returns an associative array of urls,
	 *                                                      keyed by link ID (`int[]`).
	 *                                                    Default ''.
	 *     @type int          $link_rating                     Rating to retrieve matching bookmarks for.
	 *                                                   Default empty.
	 *     @type string       $link_visible                   Filter by visible parameter.
	 *                                                   Default ''. If hide_invisible is set, this parameter will be unset.
	 *     @type int|bool     $hide_invisible            Whether to only show links marked as visible.
	 *                                                   Accepts 1|true or 0|false. Default 1|true.
	 *     @type string       $meta_key                  Include bookmarks with a matching bookmark meta key.
	 *                                                   Default empty.
	 *     @type string       $meta_value                Include bookmarks with a matching bookmark meta value.
	 *                                                   Requires `$meta_key` to be set. Default empty.
	 *     @type array        $meta_query                Meta query clauses to limit retrieved bookmarks by.
	 *                                                   See WP_Meta_Query. Default empty.
	 *     @type int          $number                    Maximum number of bookmarks to retrieve.
	 *                                                   Default empty (no limit).
	 *     @type int          $paged                     When used with $number, defines the page of results to return.
	 *                                                   When used with $offset, $offset takes precedence. Default 1.
	 *     @type int          $offset                    Number of bookmarkss to offset the query. Used to build
	 *                                                   LIMIT clause. Default 0.
	 *     @type bool         $no_found_rows             Whether to disable the `SQL_CALC_FOUND_ROWS` query.
	 *                                                   Default: true.
	 *     @type string|array $orderby                   How to order the bookmarks. To use 'meta_value'
	 *                                                   or 'meta_value_num', `$meta_key` must also be defined.
	 *                                                   To sort by a specific `$meta_query` clause, use that
	 *                                                   clause's array key. Accepts 'id',
	 *                                                   'link_id', 'name', 'link_name', 'url', 'link_url',
	 *                                                   'visible', 'link_visible', 'rating', 'link_rating',
	 *                                                   'owner', 'link_owner', 'updated', 'link_updated',
	 *                                                   'notes', 'link_notes', 'description', 'link_description',
	 *                                                   Also accepts false, an empty array, or
	 *                                                   'none' to disable `ORDER BY` clause.
	 *                                                   Default: 'name'.
	 *     @type string       $order                     How to order retrieved bookmarks. Accepts 'ASC', 'DESC'.
	 *                                                   Default: 'ASC'.
	 *     @type string       $cache_domain              Unique cache key to be produced when this query is stored in
	 *                                                   an object cache. Default is 'core'.
	 *     @type bool         $update_bookmark_meta_cache Whether to prime the metadata cache for found bookmarks.
	 *                                                   Default true.
	 * }
	 */
	public function __construct( $query = '' ) {
		$this->query_var_defaults = array(
			'name'                       => '',
			'url'                        => '',
			'owner__in'                  => '',
			'owner__not_in'              => '',
			'bookmark__in'               => '',
			'bookmark__not_in'           => '',
			'count'                      => false,
			'date_query'                 => null, // See WP_Date_Query.
			'fields'                     => '',
			'rating'                     => '',
			'visible'                    => '',
			'hide_invisible'             => '1',
			'meta_key'                   => '',
			'meta_value'                 => '',
			'meta_query'                 => '',
			'number'                     => '',
			'paged'                      => 1,
			'offset'                     => '',
			'no_found_rows'              => true,
			'orderby'                    => 'name',
			'order'                      => 'ASC',
			'cache_domain'               => 'core',
			'update_bookmark_meta_cache' => true,
			'search'                     => ''
		);

		if ( ! empty( $query ) ) {
			$this->query( $query );
		}
	}

	/**
	 * Parse arguments passed to the bookmark query with default query parameters.
	 *
	 * @param string|array $query Blinks_Bookmark_Query arguments. See Blinks_Bookmark_Query::__construct()
	 */
	public function parse_query( $query = '' ) {
		if ( empty( $query ) ) {
			$query = $this->query_vars;
		}

		$this->query_vars = wp_parse_args( $query, $this->query_var_defaults );

		/**
		 * Fires after the bookmark query vars have been parsed.
		 *
		 * @param Blinks_Bookmark_Query $this The Blinks_Bookmark_Query instance (passed by reference).
		 */
		do_action_ref_array( 'parse_bookmark_query', array( &$this ) );
	}

	/**
	 * Sets up the WordPress query for retrieving bookmarks.
	 *
	 * @param string|array $query Array or URL query string of parameters.
	 * @return array|int List of bookmarks, or number of bookmarks when 'count' is passed as a query var.
	 */
	public function query( $query ) {
		$this->query_vars = wp_parse_args( $query );
		return $this->get_bookmarks();
	}

	/**
	 * Get a list of bookmarks matching the query vars.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return int|array List of bookmarks or number of found bookmarks if `$count` argument is true.
	 */
	public function get_bookmarks() {
		global $wpdb;

		$this->parse_query();

		// Parse meta query.
		$this->meta_query = new WP_Meta_Query();
		$this->meta_query->parse_query_vars( $this->query_vars );

		/**
		 * Fires before bookmarks are retrieved.
		 *
		 * @param Blinks_Bookmark_Query $this Current instance of Blinks_Bookmark_Query (passed by reference).
		 */
		do_action_ref_array( 'pre_get_bookmarks', array( &$this ) );

		// Reparse query vars, in case they were modified in a 'pre_get_bookmarks' callback.
		$this->meta_query->parse_query_vars( $this->query_vars );
		if ( ! empty( $this->meta_query->queries ) ) {
			$this->meta_query_clauses = $this->meta_query->get_sql( 'link', $wpdb->links, 'link_id', $this );
		}

		$bookmark_data = null;

		/**
		 * Filters the bookmarks data before the query takes place.
		 *
		 * Return a non-null value to bypass WordPress' default bookmark queries.
		 *
		 * The expected return type from this filter depends on the value passed as per the parse_query() function
		 *
		 * Note that if the filter returns an array of bookmark data, it will be assigned
		 * to the `bookmarks` property of the current instance.
		 *
		 * @param array|int|null   $bookmark_data Return an array of bookmark data to short-circuit a bookmark query,
		 *                                       the bookmark count as an integer if `$this->query_vars['count']` is set,
		 *                                       or null to allow this function to run its normal queries.
		 * @param Blinks_Bookmark_Query $query        The Blinks_Bookmark_Query instance, passed by reference.
		 */
		$bookmark_data = apply_filters_ref_array( 'bookmarks_pre_query', array( $bookmark_data, &$this ) );

		if ( null !== $bookmark_data ) {
			if ( is_array( $bookmark_data ) && ! $this->query_vars['count'] ) {
				$this->bookmarks = $bookmark_data;
			}

			return $bookmark_data;
		}

		/*
		 * Only use the args defined in the query_var_defaults to compute the key,
		 * but ignore 'fields', which does not affect query results.
		 */
		$_args = wp_array_slice_assoc( $this->query_vars, array_keys( $this->query_var_defaults ) );
		unset( $_args['fields'] );

		$key          = md5( serialize( $_args ) );
		$last_changed = wp_cache_get_last_changed( 'bookmark' );

		$cache_key   = "get_bookmarks:$key:$last_changed";
		$cache_value = wp_cache_get( $cache_key, 'bookmark' );
		if ( false === $cache_value ) {
			$bookmark_ids = $this->get_bookmark_ids();
			if ( $bookmark_ids ) {
				$this->set_found_bookmarks();
			}

			$cache_value = array(
				'bookmark_ids'    => $bookmark_ids,
				'found_bookmarks' => $this->found_bookmarks,
			);
			wp_cache_add( $cache_key, $cache_value, 'bookmark' );
		} else {
			$bookmark_ids          = $cache_value['bookmark_ids'];
			$this->found_bookmarks = $cache_value['found_bookmarks'];
		}

		if ( $this->found_bookmarks && $this->query_vars['number'] ) {
			$this->max_num_pages = ceil( $this->found_bookmarks / $this->query_vars['number'] );
		}

		// If querying for a count only, there's nothing more to do.
		if ( $this->query_vars['count'] ) {
			// $bookmark_ids is actually a count in this case.
			return (int) $bookmark_ids;
		}

		$bookmark_ids = array_map( 'intval', $bookmark_ids );

		if ( 'ids' === $this->query_vars['fields'] ) {
			$this->bookmarks = $bookmark_ids;
			return $this->bookmarks;
		}

		prime_bookmark_caches( $bookmark_ids, $this->query_vars['update_bookmark_meta_cache'] );

		// Fetch full bookmark objects from the primed cache.
		$_bookmarks = array();
		foreach ( $bookmark_ids as $bookmark_id ) {
			$_bookmark = blinks_get_bookmark( $bookmark_id );
			if ( $_bookmark ) {
				$_bookmarks[] = $_bookmark;
			}
		}

		/**
		 * Filters the bookmark query results.
		 *
		 * @param WP_Bookmark[]     $_bookmarks An array of bookmarkss.
		 * @param Blinks_Bookmark_Query $query     Current instance of Blinks_Bookmark_Query (passed by reference).
		 */
		$_bookmarks = apply_filters_ref_array( 'the_bookmarks', array( $_bookmarks, &$this ) );

		// Convert to WP_Bookmark instances.
		$bookmarks = array_map( 'blinks_get_bookmark', $_bookmarks );

		$this->bookmarks = $bookmarks;
		return $this->bookmarks;
	}

	/**
	 * Used internally to get a list of bookmark IDs matching the query vars.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return int|array A single count of bookmark IDs if a count query. An array of bookmark IDs if a full query.
	 */
	protected function get_bookmark_ids() {
		global $wpdb;

		$order = ( 'ASC' === strtoupper( $this->query_vars['order'] ) ) ? 'ASC' : 'DESC';

		// Disable ORDER BY with 'none', an empty array, or boolean false.
		if ( in_array( $this->query_vars['orderby'], array( 'none', array(), false ), true ) ) {
			$orderby = '';
		} elseif ( ! empty( $this->query_vars['orderby'] ) ) {
			$ordersby = is_array( $this->query_vars['orderby'] ) ?
				$this->query_vars['orderby'] :
				preg_split( '/[,\s]/', $this->query_vars['orderby'] );

			$orderby_array             = array();
			$found_orderby_bookmark_id = false;
			foreach ( $ordersby as $_key => $_value ) {
				if ( ! $_value ) {
					continue;
				}

				if ( is_int( $_key ) ) {
					$_orderby = $_value;
					$_order   = $order;
				} else {
					$_orderby = $_key;
					$_order   = $_value;
				}

				if ( ! $found_orderby_bookmark_id && 'bookmark__in' === $_orderby ) {
					$found_orderby_bookmark_id = true;
				}

				$parsed = $this->parse_orderby( $_orderby );

				if ( ! $parsed ) {
					continue;
				}

				if ( 'bookmark__in' === $_orderby ) {
					$orderby_array[] = $parsed;
					continue;
				}

				$orderby_array[] = $parsed . ' ' . $this->parse_order( $_order );
			}

			// To ensure determinate sorting, always include a link_id clause.
			if ( ! $found_orderby_bookmark_id ) {
				$bookmark_id_order = '';

				$orderby_array[] = "$wpdb->links.link_id $bookmark_id_order";
			}

			$orderby = implode( ', ', $orderby_array );
		} else {
			$orderby = "$wpdb->links.link_name $order";
		}

		$number = absint( $this->query_vars['number'] );
		$offset = absint( $this->query_vars['offset'] );
		$paged  = absint( $this->query_vars['paged'] );
		$limits = '';

		if ( ! empty( $number ) ) {
			if ( $offset ) {
				$limits = 'LIMIT ' . $offset . ',' . $number;
			} else {
				$limits = 'LIMIT ' . ( $number * ( $paged - 1 ) ) . ',' . $number;
			}
		}

		if ( $this->query_vars['count'] ) {
			$fields = 'COUNT(*)';
		} else {
			$fields = "$wpdb->links.link_id";
		}

		// Parse bookmark IDs for an IN clause.
		if ( ! empty( $this->query_vars['bookmark__in'] ) ) {
			$this->sql_clauses['where']['bookmark__in'] = "$wpdb->links.link_id IN ( " . implode( ',', wp_parse_id_list( $this->query_vars['bookmark__in'] ) ) . ' )';
		}

		// Parse bookmark IDs for a NOT IN clause.
		if ( ! empty( $this->query_vars['bookmark__not_in'] ) ) {
			$this->sql_clauses['where']['bookmark__not_in'] = "$wpdb->links.link_id NOT IN ( " . implode( ',', wp_parse_id_list( $this->query_vars['bookmark__not_in'] ) ) . ' )';
		}

		if ( '' !== $this->query_vars['name'] ) {
			$this->sql_clauses['where']['name'] = $wpdb->prepare( 'link_name = %s', $this->query_vars['name'] );
		}

		if ( '' !== $this->query_vars['url'] ) {
			$this->sql_clauses['where']['url'] = $wpdb->prepare( 'link_url = %s', $this->query_vars['url'] );
		}

		if ( '' !== $this->query_vars['rating'] ) {
			$this->sql_clauses['where']['rating'] = $wpdb->prepare( 'link_rating = %d', $this->query_vars['rating'] );
		}

		// Falsey search strings are ignored.
		if ( strlen( $this->query_vars['search'] ) ) {
			$search_sql = $this->get_search_sql(
				$this->query_vars['search'],
				array( 'link_name', 'link_url', 'link_note', 'link_description' )
			);

			// Strip leading 'AND'.
			$this->sql_clauses['where']['search'] = preg_replace( '/^\s*AND\s*/', '', $search_sql );
		}

		// Link owner IDs for an IN clause.
		if ( ! empty( $this->query_vars['owner__in'] ) ) {
			$this->sql_clauses['where']['owner__in'] = 'link_owner IN ( ' . implode( ',', wp_parse_id_list( $this->query_vars['owner__in'] ) ) . ' )';
		}

		// Link owner IDs for a NOT IN clause.
		if ( ! empty( $this->query_vars['owner__not_in'] ) ) {
			$this->sql_clauses['where']['owner__not_in'] = 'link_owner NOT IN ( ' . implode( ',', wp_parse_id_list( $this->query_vars['owner__not_in'] ) ) . ' )';
		}

		$join    = '';
		$groupby = '';

		if ( ! empty( $this->meta_query_clauses ) ) {
			$join .= $this->meta_query_clauses['join'];

			// Strip leading 'AND'.
			$this->sql_clauses['where']['meta_query'] = preg_replace( '/^\s*AND\s*/', '', $this->meta_query_clauses['where'] );

			if ( ! $this->query_vars['count'] ) {
				$groupby = "{$wpdb->links}.link_id";
			}
		}

		if ( ! empty( $this->query_vars['date_query'] ) && is_array( $this->query_vars['date_query'] ) ) {
			$this->date_query                         = new WP_Date_Query( $this->query_vars['date_query'], 'link_updated' );
			$this->sql_clauses['where']['date_query'] = preg_replace( '/^\s*AND\s*/', '', $this->date_query->get_sql() );
		}

		$where = implode( ' AND ', $this->sql_clauses['where'] );

		$pieces = array( 'fields', 'join', 'where', 'orderby', 'limits', 'groupby' );
		/**
		 * Filters the bookmark query clauses.
		 *
		 * @param string[]         $pieces An associative array of bookmark query clauses.
		 * @param Blinks_Bookmark_Query $query  Current instance of Blinks_Bookmark_Query (passed by reference).
		 */
		$clauses = apply_filters_ref_array( 'bookmarks_clauses', array( compact( $pieces ), &$this ) );

		$fields  = isset( $clauses['fields'] ) ? $clauses['fields'] : '';
		$join    = isset( $clauses['join'] ) ? $clauses['join'] : '';
		$where   = isset( $clauses['where'] ) ? $clauses['where'] : '';
		$orderby = isset( $clauses['orderby'] ) ? $clauses['orderby'] : '';
		$limits  = isset( $clauses['limits'] ) ? $clauses['limits'] : '';
		$groupby = isset( $clauses['groupby'] ) ? $clauses['groupby'] : '';

		$this->filtered_where_clause = $where;

		if ( $where ) {
			$where = 'WHERE ' . $where;
		}

		if ( $groupby ) {
			$groupby = 'GROUP BY ' . $groupby;
		}

		if ( $orderby ) {
			$orderby = "ORDER BY $orderby";
		}

		$found_rows = '';
		if ( ! $this->query_vars['no_found_rows'] ) {
			$found_rows = 'SQL_CALC_FOUND_ROWS';
		}

		$this->sql_clauses['select']  = "SELECT $found_rows $fields";
		$this->sql_clauses['from']    = "FROM $wpdb->links $join";
		$this->sql_clauses['groupby'] = $groupby;
		$this->sql_clauses['orderby'] = $orderby;
		$this->sql_clauses['limits']  = $limits;

		$this->request = "{$this->sql_clauses['select']} {$this->sql_clauses['from']} {$where} {$this->sql_clauses['groupby']} {$this->sql_clauses['orderby']} {$this->sql_clauses['limits']}";

		if ( $this->query_vars['count'] ) {
			return (int) $wpdb->get_var( $this->request );
		} else {
			$bookmark_ids = $wpdb->get_col( $this->request );
			return array_map( 'intval', $bookmark_ids );
		}
	}

	/**
	 * Populates found_bookmarks and max_num_pages properties for the current
	 * query if the limit clause was used.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	private function set_found_bookmarks() {
		global $wpdb;

		if ( $this->query_vars['number'] && ! $this->query_vars['no_found_rows'] ) {
			/**
			 * Filters the query used to retrieve found bookmark count.
			 *
			 * @param string           $found_bookmarks_query SQL query. Default 'SELECT FOUND_ROWS()'.
			 * @param Blinks_Bookmark_Query $bookmark_query        The `Blinks_Bookmark_Query` instance.
			 */
			$found_bookmarks_query = apply_filters( 'found_bookmarks_query', 'SELECT FOUND_ROWS()', $this );

			$this->found_bookmarks = (int) $wpdb->get_var( $found_bookmarks_query );
		}
	}

	/**
	 * Used internally to generate an SQL string for searching across multiple columns
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $string
	 * @param array  $cols
	 * @return string
	 */
	protected function get_search_sql( $string, $cols ) {
		global $wpdb;

		$like = '%' . $wpdb->esc_like( $string ) . '%';

		$searches = array();
		foreach ( $cols as $col ) {
			$searches[] = $wpdb->prepare( "$col LIKE %s", $like );
		}

		return ' AND (' . implode( ' OR ', $searches ) . ')';
	}

	/**
	 * Parse and sanitize 'orderby' keys passed to the bookmark query.
	 *
	 * @since 4.2.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $orderby Alias for the field to order by.
	 * @return string|false Value to used in the ORDER clause. False otherwise.
	 */
	protected function parse_orderby( $orderby ) {
		global $wpdb;

		$allowed_keys = array(
			'link_id',
			'link_name',
			'link_url',
			'link_visible',
			'link_rating',
			'link_owner',
			'link_update',
			'link_notes',
			'link_description',
		);

		if ( ! empty( $this->query_vars['meta_key'] ) ) {
			$allowed_keys[] = $this->query_vars['meta_key'];
			$allowed_keys[] = 'meta_value';
			$allowed_keys[] = 'meta_value_num';
		}

		$meta_query_clauses = $this->meta_query->get_clauses();
		if ( $meta_query_clauses ) {
			$allowed_keys = array_merge( $allowed_keys, array_keys( $meta_query_clauses ) );
		}

		$parsed = false;
		if ( $this->query_vars['meta_key'] === $orderby || 'meta_value' === $orderby ) {
			$parsed = "$wpdb->linksmeta.meta_value";
		} elseif ( 'meta_value_num' === $orderby ) {
			$parsed = "$wpdb->linksmeta.meta_value+0";
		} elseif ( in_array( $orderby, $allowed_keys, true ) ) {
			if ( isset( $meta_query_clauses[ $orderby ] ) ) {
				$meta_clause = $meta_query_clauses[ $orderby ];
				$parsed      = sprintf( 'CAST(%s.meta_value AS %s)', esc_sql( $meta_clause['alias'] ), esc_sql( $meta_clause['cast'] ) );
			} else {
				$parsed = "$wpdb->links.$orderby";
			}
		}

		return $parsed;
	}

	/**
	 * Parse an 'order' query variable and cast it to ASC or DESC as necessary.
	 *
	 * @since 4.2.0
	 *
	 * @param string $order The 'order' query variable.
	 * @return string The sanitized 'order' query variable.
	 */
	protected function parse_order( $order ) {
		if ( ! is_string( $order ) || empty( $order ) ) {
			return 'ASC';
		}

		if ( 'ASC' === strtoupper( $order ) ) {
			return 'ASC';
		} else {
			return 'DESC';
		}
	}
}
