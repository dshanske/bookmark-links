<?php
/**
 * Bookmarks REST Controller Class
 *
 * @package Bookmark_Links
 */

/**
 * Class used to manage bookmarks.
 *
 * @see WP_REST_Controller
 */
class Blinks_REST_Bookmarks_Controller extends WP_REST_Controller {
	/**
	 * Instance of a link meta fields object.
	 *
	 * @var Blinks_REST_Link_Meta_Fields
	 */
	protected $meta;

	/**
	 * Column to have the bookmarks be sorted by.
	 *
	 * @var string
	 */
	protected $sort_column;

	/**
	 * Number of links that were found.
	 *
	 * @var int
	 */
	protected $total_links;

	/**
	 * Whether the controller supports batching.
	 *
	 * @var array
	 */
	protected $allow_batch = array( 'v1' => true );

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'bookmarks';
		$this->namespace = 'blinks/v1';

		$this->meta = new Blinks_REST_Link_Meta_Fields();
	}

	/**
	 * Registers the routes for bookmarks.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'allow_batch' => $this->allow_batch,
				'schema'      => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'        => array(
					'id' => array(
						'description' => __( 'Unique identifier for the link.' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'force' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Required to be true, as links do not support trashing.' ),
						),
					),
				),
				'allow_batch' => $this->allow_batch,
				'schema'      => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Checks if a request has access to read links.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, otherwise false or WP_Error object.
	 */
	public function get_items_permissions_check( $request ) {
		if ( 'edit' === $request['context'] && ! current_user_can( 'manage_links' ) ) {
			return new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to edit links.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Retrieves links.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {

		// Retrieve the list of registered collection query parameters.
		$registered = $this->get_collection_params();

		/*
		 * This array defines mappings between public API query parameters whose
		 * values are accepted as-passed, and their internal query parameter
		 * name equivalents (some are the same). Only values which are also
		 * present in $registered will be set.
		 */
		$parameter_mappings = array(
			'exclude'        => 'bookmark__not_in',
			'include'        => 'bookmark__in',
			'owner__in'      => 'owner__in',
			'order'          => 'order',
			'orderby'        => 'orderby',
			'post'           => 'post',
			'hide_invisible' => 'hide_invisible',
			'per_page'       => 'number',
			'search'         => 'search',
			'offset'         => 'offset',
		);

		$prepared_args = array();

		// Check for & assign any parameters which require special handling or setting.
		$prepared_args['date_query'] = array();

		/*
		 * For each known parameter which is both registered and present in the request,
		 * set the parameter's value on the query $prepared_args.
		 */
		foreach ( $parameter_mappings as $api_param => $wp_param ) {
			if ( isset( $registered[ $api_param ], $request[ $api_param ] ) ) {
				$prepared_args[ $wp_param ] = $request[ $api_param ];
			}
		}

		if ( isset( $registered['before'], $request['before'] ) ) {
			$prepared_args['date_query'][] = array(
				'before' => $request['before'],
				'column' => 'link_updated',
			);
		}

		if ( isset( $registered['after'], $request['after'] ) ) {
			$prepared_args['date_query'][] = array(
				'after'  => $request['after'],
				'column' => 'link_updated',
			);
		}

		$query = new Blinks_Bookmark_Query( $prepared_args );

		$query_result = $query->query( $prepared_args );

		$count_args = $prepared_args;

		unset( $count_args['number'], $count_args['offset'] );

		$total_bookmarks = (int) $query->found_bookmarks;
		$max_pages       = (int) $query->max_num_pages;

		if ( ! $total_bookmarks ) {
			$total_bookmarks = 0;
		}

		$response = array();

		foreach ( $query_result as $bookmark ) {
			$data       = $this->prepare_item_for_response( $bookmark, $request );
			$response[] = $this->prepare_response_for_collection( $data );
		}

		$response = rest_ensure_response( $response );

		// Store pagination values for headers.
		$per_page = (int) $prepared_args['number'];
		$page     = ceil( ( ( (int) $prepared_args['offset'] ) / $per_page ) + 1 );

		$response->header( 'X-WP-Total', (int) $total_bookmarks );

		$max_pages = ceil( $total_bookmarks / $per_page );

		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		$base = add_query_arg( urlencode_deep( $request->get_query_params() ), rest_url( $this->namespace . '/' . $this->rest_base ) );
		if ( $page > 1 ) {
			$prev_page = $page - 1;

			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}

			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}
		if ( $max_pages > $page ) {
			$next_page = $page + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );

			$response->link_header( 'next', $next_link );
		}

		return $response;
	}

	/**
	 * Get the bookmark, if the ID is valid.
	 *
	 * @param int $id Supplied ID.
	 * @return WP_Bookmark|WP_Error Boommark object if ID is valid, WP_Error otherwise.
	 */
	protected function get_bookmark( $id ) {
		$error = new WP_Error(
			'rest_bookmark_invalid',
			__( 'Bookmark does not exist.', 'bookmark-links' ),
			array( 'status' => 404 )
		);

		if ( (int) $id <= 0 ) {
			return $error;
		}

		$bookmark = blinks_get_bookmark( (int) $id );
		if ( empty( $bookmark ) ) {
			return $error;
		}

		return $bookmark;
	}

	/**
	 * Checks if a request has access to read or edit the specified bookmark.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access for the item, otherwise false or WP_Error object.
	 */
	public function get_item_permissions_check( $request ) {
		$bookmark = $this->get_bookmark( $request['id'] );

		if ( is_wp_error( $bookmark ) ) {
			return $bookmark;
		}

		if ( 'edit' === $request['context'] && ! current_user_can( 'manage_links', $bookmarks->link_id ) ) {
			return new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to edit this bookmark.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Gets a single link.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$bookmark = $this->get_bookmark( $request['id'] );
		if ( is_wp_error( $bookmark ) ) {
			return $bookmark;
		}

		$response = $this->prepare_item_for_response( $bookmark, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Checks if a request has access to create a link.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to create items, false or WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_links' ) ) {
			return new WP_Error(
				'rest_cannot_create',
				__( 'Sorry, you are not allowed to create links' ),
				array(
					'status' => rest_authorization_required_code(),
					'data'   => get_current_user_id(),
				)
			);
		}

		return true;
	}

	/**
	 * Creates a bookmark
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		$prepared_item = $this->prepare_item_for_database( $request );

		$id = blinks_insert_bookmark( $prepared_item, true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$bookmark = blinks_get_bookmark( $id );

		/**
		 * Fires after a single bookmark is created or updated via the REST API.
		 *
		 * @param WP_Boommark        $bookmark     Inserted or updated bookmark object.
		 * @param WP_REST_Request $request  Request object.
		 * @param bool            $creating True when creating a link, false when updating.
		 */
		do_action( 'rest_insert_bookmark', $bookmark, $request, true );

		$schema = $this->get_item_schema();
		if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) {
			$meta_update = $this->meta->update_value( $request['meta'], $bookmark->link_id );

			if ( is_wp_error( $meta_update ) ) {
				return $meta_update;
			}
		}

		$fields_update = $this->update_additional_fields_for_object( $bookmark, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$request->set_param( 'context', 'edit' );

		/**
		 * Fires after a single bookmark is completely created or updated via the REST API.
		 *
		 * @param WP_Bookmark        $bookmark     Inserted or updated bookmark object.
		 * @param WP_REST_Request $request  Request object.
		 * @param bool            $creating True when creating a link, false when updating.
		 */
		do_action( 'rest_after_insert_bookmark', $bookmark, $request, true );

		$response = $this->prepare_item_for_response( $bookmark, $request );
		$response = rest_ensure_response( $response );

		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $bookmark->link_id ) );

		return $response;
	}

	/**
	 * Checks if a request has access to update the specified link.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to update the item, false or WP_Error object otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		$bookmark = $this->get_bookmark( $request['id'] );

		if ( is_wp_error( $bookmark ) ) {
			return $bookmark;
		}

		if ( ! current_user_can( 'manage_links', $bookmark->link_id ) ) {
			return new WP_Error(
				'rest_cannot_update',
				__( 'Sorry, you are not allowed to edit this bookmark.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Updates a single bookmark.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		$bookmark = $this->get_bookmark( $request['id'] );
		if ( is_wp_error( $bookmark ) ) {
			return $bookmark;
		}

		$prepared_bookmark = $this->prepare_item_for_database( $request );

		// Only update the bookmark if we have something to update.
		if ( ! empty( $prepared_bookmark ) ) {
			$update = blinks_update_bookmark( $prepared_bookmark, true );

			if ( is_wp_error( $update ) ) {
				return $update;
			}
		}

		$bookmark = blinks_get_bookmark( $update );

		do_action( 'rest_insert_bookmarks', $bookmark, $request, false );

		$schema = $this->get_item_schema();
		if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) {
			$meta_update = $this->meta->update_value( $request['meta'], $bookmark->link_id );

			if ( is_wp_error( $meta_update ) ) {
				return $meta_update;
			}
		}

		$fields_update = $this->update_additional_fields_for_object( $bookmark, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$request->set_param( 'context', 'edit' );

		do_action( 'rest_after_insert_bookmark', $bookmark, $request, false );

		$response = $this->prepare_item_for_response( $bookmark, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Checks if a request has access to delete the specified bookmark.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to delete the item, otherwise false or WP_Error object.
	 */
	public function delete_item_permissions_check( $request ) {
		$bookmark = $this->get_bookmark( $request['id'] );

		if ( is_wp_error( $bookmark ) ) {
			return $bookmark;
		}

		if ( ! current_user_can( 'manage_links', $bookmark->link_id ) ) {
			return new WP_Error(
				'rest_cannot_delete',
				__( 'Sorry, you are not allowed to delete this bookmark.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Deletes a single bookmark.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$bookmark = $this->get_bookmark( $request['id'] );
		if ( is_wp_error( $bookmark ) ) {
			return $bookmark;
		}

		$force = isset( $request['force'] ) ? (bool) $request['force'] : false;

		// We don't support trashing for links.
		if ( ! $force ) {
			return new WP_Error(
				'rest_trash_not_supported',
				/* translators: %s: force=true */
				sprintf( __( "Links do not support trashing. Set '%s' to delete." ), 'force=true' ),
				array( 'status' => 501 )
			);
		}

		$request->set_param( 'context', 'view' );

		$previous = $this->prepare_item_for_response( $bookmark, $request );

		$retval = blinks_delete_bookmark( $bookmark->link_id );

		if ( ! $retval ) {
			return new WP_Error(
				'rest_cannot_delete',
				__( 'The bookmark cannot be deleted.' ),
				array( 'status' => 500 )
			);
		}

		$response = new WP_REST_Response();
		$response->set_data(
			array(
				'deleted'  => true,
				'previous' => $previous->get_data(),
			)
		);

		/**
		 * Fires after a single bookmark is deleted via the REST API.
		 *
		 * @param WP_Bookmark          $bookmark     The deleted bookmark.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'rest_delete_bookmark', $bookmark, $response, $request );

		return $response;
	}

	/**
	 * Prepares a single bookmark for create or update.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return object Bookmark object.
	 */
	public function prepare_item_for_database( $request ) {
		$prepared_bookmark = new stdClass();

		$schema = $this->get_item_schema();

		if ( isset( $request['id'] ) && ! empty( $schema['properties']['id'] ) ) {
			$prepared_bookmark->link_id = $request['id'];
		}

		if ( isset( $request['name'] ) && ! empty( $schema['properties']['name'] ) ) {
			$prepared_bookmark->link_name = $request['name'];
		}

		if ( isset( $request['url'] ) && ! empty( $schema['properties']['url'] ) ) {
			$prepared_bookmark->link_url = $request['url'];
		}

		if ( isset( $request['description'] ) && ! empty( $schema['properties']['description'] ) ) {
			$prepared_bookmark->link_description = $request['description'];
		}

		if ( isset( $request['notes'] ) && ! empty( $schema['properties']['notes'] ) ) {
			$prepared_bookmark->link_notes = $request['notes'];
		}

		if ( isset( $request['image'] ) && ! empty( $schema['properties']['image'] ) ) {
			$prepared_bookmark->link_image = $request['image'];
		}

		if ( isset( $request['visible'] ) && ! empty( $schema['properties']['visible'] ) ) {
			$prepared_bookmark->link_visible = ( $request['visible'] ) ? 'Y' : 'N';
		}

		if ( isset( $request['rating'] ) && ! empty( $schema['properties']['rating'] ) ) {
			$prepared_bookmark->link_rating = (int) $request['rating'];
		}

		if ( isset( $request['rss'] ) && ! empty( $schema['properties']['rss'] ) ) {
			$prepared_bookmark->link_rss = $request['rss'];
		}

		if ( isset( $request['toread'] ) && ! empty( $schema['properties']['toread'] ) ) {
			$prepared_bookmark->link_toread = (int) $request['toread'];
		}

		/**
		 * Filters bookmark data before inserting bookmark via the REST API.
		 *
		 * @param object          $prepared_bookmark Bookmark object.
		 * @param WP_REST_Request $request       Request object.
		 */
		return apply_filters( 'rest_pre_insert_bookmark', $prepared_bookmark, $request );
	}

	/**
	 * Prepares a single bookmark output for response.
	 *
	 * @param WP_Bookmark     $item    Bookmark object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {

		$fields = $this->get_fields_for_response( $request );
		$data   = array();

		if ( in_array( 'id', $fields, true ) ) {
			$data['id'] = (int) $item->link_id;
		}

		if ( in_array( 'description', $fields, true ) ) {
			$data['description'] = $item->link_description;
		}

		if ( in_array( 'url', $fields, true ) ) {
			$data['url'] = $item->link_url;
		}

		if ( in_array( 'name', $fields, true ) ) {
			$data['name'] = $item->link_name;
		}

		if ( in_array( 'rss', $fields, true ) ) {
			$data['rss'] = $item->link_rss;
		}

		if ( in_array( 'image', $fields, true ) ) {
			$data['image'] = $item->link_image;
		}

		if ( in_array( 'rating', $fields, true ) ) {
			$data['rating'] = (int) $item->link_rating;
		}

		if ( in_array( 'rel', $fields, true ) ) {
			if ( empty( $item->link_rel ) ) {
				$data['rel'] = array();
			} else {
				$data['rel'] = explode( ' ', $item->link_rel );
			}
		}

		if ( in_array( 'updated', $fields, true ) ) {
			if ( ! empty( $item->link_updated ) ) {
				$updated         = new DateTime( $item->link_updated, wp_timezone() );
				$data['updated'] = $updated->format( DATE_W3C );

			} else {
				$data['updated'] = '';
			}
		}

		if ( in_array( 'notes', $fields, true ) ) {
			$data['notes'] = $item->link_notes;
		}

		if ( in_array( 'owner', $fields, true ) ) {
			$data['owner'] = (int) $item->link_owner;
		}

		if ( in_array( 'visible', $fields, true ) ) {
			$data['visible'] = $item->link_visible;
		}

		if ( in_array( 'target', $fields, true ) ) {
			$data['target'] = $item->link_target;
		}

		if ( in_array( 'type', $fields, true ) ) {
			$data['type'] = $item->link_type;
		}

		if ( in_array( 'toread', $fields, true ) ) {
			$data['toread'] = get_link_meta( $item->link_id, 'link_toread', true ) ? true : false;
		}

		if ( in_array( 'meta', $fields, true ) ) {
			$data['meta'] = $this->meta->get_value( $item->link_id, $request );
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $item ) );

		/**
		 * Filters the bookmark data for a REST API response.
		 *
		 * Allows modification of the bookmark data right before it is returned.
		 *
		 * @param WP_REST_Response  $response  The response object.
		 * @param WP_Bookmark           $item      The original bookmark object.
		 * @param WP_REST_Request   $request   Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_bookmark', $response, $item, $request );
	}

	/**
	 * Prepares links for the request.
	 *
	 * @param WP_Bookmark $bookmark Bookmark object.
	 * @return array Links for the given bookmark.
	 */
	protected function prepare_links( $bookmark ) {
		$base  = $this->namespace . '/' . $this->rest_base;
		$links = array(
			'self'       => array(
				'href' => rest_url( trailingslashit( $base ) . $bookmark->link_id ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'about'      => array(
				'href' => rest_url( sprintf( 'blinks/v1/bookmark/' ) ),
			),
			'owner'      => array(
				'href' => rest_url( 'wp/v2/users/' . $bookmark->link_owner ),
			),
		);

		return $links;
	}

	/**
	 * Retrieves the bookmarks's schema, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bookmark',
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'description' => __( 'Unique identifier for the bookmark.' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'embed', 'edit' ),
					'readonly'    => true,
				),
				'updated'     => array(
					'description' => __( 'Last time the link was updated.' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'description' => array(
					'description' => __( 'Short description of the bookmark.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'embed', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'url'         => array(
					'description' => __( 'Bookmark URL' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'embed', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'required'    => true,
				),
				'name'        => array(
					'description' => __( 'Name for the Bookmark.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'embed', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'required'    => true,
				),
				'image'       => array(
					'description' => __( 'Featured Image for the Bookmark.' ),
					'type'        => 'uri',
					'context'     => array( 'view', 'embed', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'rss'         => array(
					'description' => __( 'RSS URL for Bookmark' ),
					'type'        => 'uri',
					'context'     => array( 'view', 'embed', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'type'        => array(
					'description' => __( 'Type' ),
					'type'        => 'string',
					'context'     => array( 'view', 'embed', 'edit' ),
					'readonly'    => true,
				),
				'visible'     => array(
					'description' => __( 'Link Visibility' ),
					'type'        => 'string',
					'context'     => array( 'view', 'embed', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'default'     => blinks_get_default_link_visible(),
				),
				'notes'       => array(
					'description' => __( 'Notes for Bookmark' ),
					'type'        => 'string',
					'context'     => array( 'view', 'embed', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'rating'      => array(
					'description' => __( 'Rating Bookmark' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'embed', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'owner'       => array(
					'description' => __( 'Bookmark Owner' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'embed', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'target'      => array(
					'description' => __( 'Bookmark Link Target' ),
					'type'        => 'string',
					'context'     => array( 'view', 'embed', 'edit' ),
					'enum'        => array(
						'_top',
						'_blank',
						'',
					),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'rel'         => array(
					'description' => __( 'Bookmark Rel Properties' ),
					'type'        => 'string',
					'context'     => array( 'view', 'embed', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'toread'      => array(
					'description' => __( 'Read Bookmark Later' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'embed', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
		);

		$schema['properties']['meta'] = $this->meta->get_field_schema();

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Retrieves the query params for collections.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();

		$query_params['context']['default'] = 'view';

		$query_params['hide_invisible'] = array(
			'description' => __( 'Ensure result shows only visible links by default' ),
			'type'        => 'integer',
			'default'     => '1',
		);

		$query_params['per_page'] = array(
			'description' => __( 'How many results returned per_page' ),
			'type'        => 'integer',
			'default'     => '10',
		);

		$query_params['exclude'] = array(
			'description' => __( 'Ensure result set excludes specific IDs.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);

		$query_params['include'] = array(
			'description' => __( 'Limit result set to specific IDs.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);

		$query_params['before'] = array(
			'description' => __( 'Limit response to links updated a given ISO8601 compliant date.' ),
			'type'        => 'string',
			'format'      => 'date-time',
		);

		$query_params['offset'] = array(
			'description' => __( 'Offset the result set by a specific number of items.' ),
			'type'        => 'integer',
			'default'     => 0,
		);

		$query_params['order'] = array(
			'description' => __( 'Order sort attribute ascending or descending.' ),
			'type'        => 'string',
			'default'     => 'asc',
			'enum'        => array(
				'asc',
				'desc',
			),
		);

		$query_params['orderby'] = array(
			'description' => __( 'Sort collection by attribute.' ),
			'type'        => 'string',
			'default'     => 'id',
			'enum'        => array(
				'id',
				'name',
				'description',
				'url',
				'rating',
				'updated',
				'owner',
			),
		);

		/**
		 * Filters collection parameters for the bookmarks controller.
		 *
		 * @param array       $query_params JSON Schema-formatted collection parameters.
		 */
		return apply_filters( 'rest_bookmarks_collection_params', $query_params );
	}
}
