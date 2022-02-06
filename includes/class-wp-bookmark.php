<?php
/**
 * WP_Bookmark class
 *
 * @package Bookmark-Links
 */

/**
 * Class used to implement the WP_Bookmark object, which is based on the WP_Post object.
 *
 * Normally would not use a WP prefix, but this is a direct manipulation of a Core object.
 */
final class WP_Bookmark {

	/**
	 * Link ID.
	 *
	 * @var int
	 */
	public $link_id;

	/**
	 * The link's URL.
	 *
	 * @var string
	 */
	public $link_url = '';

	/**
	 * The link's name.
	 *
	 * @var string
	 */
	public $link_name = '';

	/**
	 * The link's image.
	 *
	 * @var string
	 */
	public $link_image = '';

	/**
	 * The link's target.
	 *
	 * @var string
	 */
	public $link_target = '';

	/**
	 * The link's description.
	 *
	 * @var string
	 */
	public $link_description = '';

	/**
	 * The link's visibility
	 *
	 * @var string
	 */
	public $link_visible = 'Y';

	/**
	 * The link's owner.
	 *
	 * A numeric string for compatibility reasons.
	 *
	 * @var string
	 */
	public $link_owner = 0;

	/**
	 * The link's rating.
	 *
	 * @var string
	 */
	public $link_rating = 0;

	/**
	 * Whether pings are allowed.
	 *
	 * @var string
	 */
	public $ping_status = 'open';

	/**
	 * The link's last updated time.
	 *
	 * @var string
	 */
	public $link_updated = '0000-00-00 00:00:00';

	/**
	 * The link's rel values.
	 *
	 * @var string
	 */
	public $link_rel = '';

	/**
	 * The link's notes.
	 *
	 * @var string
	 */
	public $link_notes = '';

	/**
	 * The link's rss feed.
	 *
	 * @var string
	 */
	public $link_rss = '';

	/**
	 * The link type derived from the RSS field.
	 *
	 * @var string
	 */
	public $link_type = '';

	/**
	 * The link's category.
	 *
	 * @var int[]
	 */
	public $link_category = array();

	 /**
	  * Stores the post object's sanitization level.
	  *
	  * Does not correspond to a DB field.
	  *
	  * @var string
	  */
	  public $filter;

	/**
	 * Retrieve WP_Bookmark instance.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $link_id Link ID.
	 * @return WP_Link|false Link object, false otherwise.
	 */
	public static function get_instance( $link_id ) {
		global $wpdb;

		$link_id = (int) $link_id;
		if ( ! $link_id ) {
			return false;
		}

		$_bookmark = wp_cache_get( $link_id, 'bookmark' );

		if ( ! $_bookmark ) {
			$_bookmark = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->links WHERE link_id = %d LIMIT 1", $link_id ) );
			if ( ! $_bookmark ) {
				return false;
			}

			$_bookmark->link_category = array_unique( wp_get_object_terms( $_bookmark->link_id, 'link_category', array( 'fields' => 'ids' ) ) );
			$_bookmark->link_type     = self::type( $_bookmark );

			$_bookmark = sanitize_bookmark( $_bookmark, 'raw' );
			wp_cache_add( $_bookmark->link_id, $_bookmark, 'bookmark' );
		} elseif ( empty( $_bookmark->filter ) ) {
			$_bookmark = sanitize_bookmark( $_bookmark, 'raw' );
		}

		return new WP_Bookmark( $_bookmark );
	}

	/**
	 * Constructor.
	 *
	 * @param WP_Bookmark|object $bookmark Bookmark object.
	 */
	public function __construct( $bookmark ) {
		foreach ( get_object_vars( $bookmark ) as $key => $value ) {
			$this->$key = $value;
		}
		if ( empty( $this->link_type ) ) {
			$this->link_type = $this->type( $bookmark );
		}
	}

	/**
	 * Isset-er.
	 *
	 * @param string $key Property to check if set.
	 * @return bool
	 */
	public function __isset( $key ) {
		if ( 'link_category' === $key ) {
			return true;
		}

		return metadata_exists( 'links', $this->ID, $key );
	}

	/**
	 * Getter.
	 *
	 * @since 3.5.0
	 *
	 * @param string $key Key to get.
	 * @return mixed
	 */
	public function __get( $key ) {
		if ( 'tags_input' === $key ) {
			$terms = wp_get_object_terms( $this->link_id, 'link_tag' );
			if ( empty( $terms ) ) {
				return array();
			}
			return wp_list_pluck( $terms, 'name' );
		}

		$value = get_link_meta( $this->link_id, $key, true );

		if ( $this->filter ) {
			$value = sanitize_bookmark_field( $key, $value, $this->link_id, $this->filter );
		}

		return $value;
	}

	/**
	 * {@Missing Summary}
	 *
	 * @param string $filter Filter.
	 * @return WP_Bookmark
	 */
	public function filter( $filter ) {
		if ( $this->filter === $filter ) {
			return $this;
		}

		if ( 'raw' === $filter ) {
			return self::get_instance( $this->link_id );
		}

		return sanitize_bookmark( $bookmark, $filter );
	}

	/**
	 * Bookmark Type Setter
	 *
	 * @return string Type.
	 */
	public static function type( $bookmark ) {
		if ( isset( $bookmark->link_rss ) && '' === $bookmark->link_rss ) {
			return 'single';
		} elseif ( isset( $bookmark->link_rss ) && $bookmark->link_url === $bookmark->link_rss ) {
			return 'h-feed';
		} else {
			return 'feed';
		}
	}


	/**
	 * Convert object to array.
	 *
	 * @since 3.5.0
	 *
	 * @return array Object as array.
	 */
	public function to_array() {
		$bookmark = get_object_vars( $this );
		return $bookmark;
	}

}
