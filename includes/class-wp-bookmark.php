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
	 * @var int
	 */
	public $link_rss = '';

	/**
	 * Retrieve WP_Bookmark instance.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $link_id Post ID.
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

			$_bookmark = sanitize_bookmark( $_bookmark, $filter );
			wp_cache_add( $_bookmark->link_id, $_bookmark, 'bookmark' );
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
		if ( 'link_category' === $key ) {
			if ( is_object_in_taxonomy( 'links', 'link_category' ) ) {
				$terms = get_the_terms( $this, 'category' );
			}

			if ( empty( $terms ) ) {
				return array();
			}

			return wp_list_pluck( $terms, 'term_id' );
		}

		return $value;
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
