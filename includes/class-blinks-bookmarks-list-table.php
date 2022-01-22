<?php
/**
 * List Table API: WP_Links_List_Table class
 *
 * @package WordPress
 * @subpackage Administration
 * @since 3.1.0
 */

/**
 * Core class used to implement displaying links in a list table.
 *
 * @since 3.1.0
 * @access private
 *
 * @see WP_List_Table
 */
class Blinks_Bookmarks_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 *
	 * @see WP_List_Table::__construct() for more information on default arguments.
	 *
	 * @param array $args An associative array of arguments.
	 */
	public function __construct( $args = array() ) {
		parent::__construct(
			array(
				'plural' => 'bookmarks',
				'screen' => isset( $args['screen'] ) ? $args['screen'] : null,
			)
		);
	}

	/**
	 * @return bool
	 */
	public function ajax_user_can() {
		return current_user_can( 'manage_links' );
	}

	/**
	 * @global int    $cat_id
	 * @global string $s
	 * @global string $orderby
	 * @global string $order
	 */
	public function prepare_items() {
		global $cat_id, $s, $orderby, $order, $term, $taxonomy;

		wp_reset_vars( array( 'action', 'cat_id', 'link_id', 'orderby', 'order', 's', 'taxonomy', 'term' ) );

		$args = array(
			'hide_invisible' => 0,
			'hide_empty'     => 0,
		);

		if ( 'all' !== $cat_id ) {
			$args['category'] = $cat_id;
		}

		if ( ! empty( $s ) ) {
			$args['search'] = $s;
		}
		if ( ! empty( $orderby ) ) {
			$args['orderby'] = $orderby;
		}
		if ( ! empty( $order ) ) {
			$args['order'] = $order;
		}

		if ( ! empty( $taxonomy ) ) {
			$args['taxonomy'] = $taxonomy;
		}

		if ( ! empty( $term ) ) {
			$args['term'] = $term;
		}

		$this->items = blinks_get_bookmarks( $args );
	}

	/**
	 */
	public function no_items() {
		_e( 'No links found.' );
	}

	/**
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions           = array();
		$actions['delete'] = __( 'Delete' );

		return $actions;
	}

	/**
	 * Helper to create links to edit.php with params.
	 *
	 * @param string[] $args  Associative array of URL parameters for the link.
	 * @param string   $label Link text.
	 * @param string   $class Optional. Class attribute. Default empty string.
	 * @return string The formatted link string.
	 */
	protected function get_edit_link( $args, $label, $class = '' ) {
		$url = add_query_arg( $args, 'link-manager.php' );

		$class_html   = '';
		$aria_current = '';

		if ( ! empty( $class ) ) {
			$class_html = sprintf(
				' class="%s"',
				esc_attr( $class )
			);

			if ( 'current' === $class ) {
				$aria_current = ' aria-current="page"';
			}
		}

		return sprintf(
			'<a href="%s"%s%s>%s</a>',
			esc_url( $url ),
			$class_html,
			$aria_current,
			$label
		);
	}

	/**
	 * @global int $cat_id
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		global $cat_id;

		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<?php
			$dropdown_options = array(
				'selected'        => $cat_id,
				'name'            => 'cat_id',
				'taxonomy'        => 'link_category',
				'show_option_all' => get_taxonomy( 'link_category' )->labels->all_items,
				'hide_empty'      => true,
				'hierarchical'    => 1,
				'show_count'      => 0,
				'orderby'         => 'name',
			);

			echo '<label class="screen-reader-text" for="cat_id">' . get_taxonomy( 'link_category' )->labels->filter_by_item . '</label>';

			wp_dropdown_categories( $dropdown_options );

			submit_button( __( 'Filter' ), '', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
			?>
		</div>
		<?php
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		$link_columns = array(
			'cb'         => '<input type="checkbox" />',
			'name'       => _x( 'Name', 'link name' ),
			'url'        => __( 'URL' ),
			'categories' => __( 'Categories' ),
			'rel'        => __( 'Relationship' ),
			'visible'    => __( 'Visible' ),
			'rating'     => __( 'Rating' ),
			'updated'    => __( 'Updated' ),
		);

		$taxonomies = get_object_taxonomies( 'link', 'objects' );
		$taxonomies = wp_filter_object_list( $taxonomies, array( 'show_admin_column' => true ), 'and', 'name' );
		/**
		 * Filters the taxonomy columns in the bookmarks list table.
		 *
		 * @param string[] $taxonomies Array of taxonomy names to show columns for.
		 */
		$taxonomies     = apply_filters( 'manage_taxonomies_for_link_columns', $taxonomies );
			$taxonomies = array_filter( $taxonomies, 'taxonomy_exists' );

		foreach ( $taxonomies as $taxonomy ) {
			if ( 'link_tag' === $taxonomy ) {
				$column_key = 'tags';
			} else {
				$column_key = 'taxonomy-' . $taxonomy;
			}
			$link_columns[ $column_key ] = get_taxonomy( $taxonomy )->labels->name;
		}

		/**
		 * Filters the columns displayed in the Bookmarks list table.
		 *
		 * @param string[] $link_columns An associative array of column headings.
		 */
		$link_columns = apply_filters( 'manage_link_columns', $link_columns );

		return $link_columns;

	}

	/**
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'name'    => 'name',
			'url'     => 'url',
			'visible' => 'visible',
			'rating'  => 'rating',
			'updated' => 'updated'
		);
	}

	/**
	 * Get the name of the default primary column.
	 *
	 * @since 4.3.0
	 *
	 * @return string Name of the default primary column, in this case, 'name'.
	 */
	protected function get_default_primary_column_name() {
		return 'name';
	}

	/**
	 * Handles the checkbox column output.
	 *
	 * @since 4.3.0
	 * @since 5.9.0 Renamed `$link` to `$item` to match parent class for PHP 8 named parameter support.
	 *
	 * @param object $item The current link object.
	 */
	public function column_cb( $item ) {
		// Restores the more descriptive, specific name for use within this method.
		$link = $item;

		?>
		<label class="screen-reader-text" for="cb-select-<?php echo $link->link_id; ?>">
			<?php
			/* translators: %s: Link name. */
			printf( __( 'Select %s' ), $link->link_name );
			?>
		</label>
		<input type="checkbox" name="linkcheck[]" id="cb-select-<?php echo $link->link_id; ?>" value="<?php echo esc_attr( $link->link_id ); ?>" />
		<?php
	}

	/**
	 * Handles the link name column output.
	 *
	 * @since 4.3.0
	 *
	 * @param object $link The current link object.
	 */
	public function column_name( $link ) {
		$edit_link = get_edit_bookmark_link( $link );
		printf(
			'<strong><a class="row-title" href="%s" aria-label="%s">%s</a></strong>',
			$edit_link,
			/* translators: %s: Link name. */
			esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $link->link_name ) ),
			$link->link_name
		);
	}

	/**
	 * Handles the link URL column output.
	 *
	 * @since 4.3.0
	 *
	 * @param object $link The current link object.
	 */
	public function column_url( $link ) {
		$short_url = url_shorten( $link->link_url );
		echo "<a href='$link->link_url'>$short_url</a>";
	}

	/**
	 * Handles the link categories column output.
	 *
	 * @since 4.3.0
	 *
	 * @global int $cat_id
	 *
	 * @param object $link The current link object.
	 */
	public function column_categories( $link ) {
		global $cat_id;

		$cat_names = array();
		foreach ( $link->link_category as $category ) {
			$cat = get_term( $category, 'link_category', OBJECT, 'display' );
			if ( is_wp_error( $cat ) ) {
				echo $cat->get_error_message();
			}
			$cat_name = $cat->name;
			if ( (int) $cat_id !== $category ) {
				$cat_name = "<a href='link-manager.php?cat_id=$category'>$cat_name</a>";
			}
			$cat_names[] = $cat_name;
		}
		echo implode( ', ', $cat_names );
	}

	/**
	 * Handles the link relation column output.
	 *
	 * @since 4.3.0
	 *
	 * @param object $link The current link object.
	 */
	public function column_rel( $link ) {
		echo empty( $link->link_rel ) ? '<br />' : $link->link_rel;
	}

	/**
	 * Handles the link updated column output.
	 *
	 * @param object $link The current link object.
	 */
	public function column_updated( $link ) {
		if ( '0000-00-00 00:00:00' === $link->link_updated ) {
			echo __( 'Never Updated', 'bookmark-links' );
		} else {
			$updated = new DateTimeImmutable( $link->link_updated, wp_timezone() );
			echo sprintf(
			/* translators: 1: Link date, 2: Link time. */
				__( '%1$s at %2$s' ),
				/* translators: Link date format. See https://www.php.net/manual/datetime.format.php */
				wp_date( __( 'Y/m/d' ), $updated->getTimestamp() ),
				/* translators: Link time format. See https://www.php.net/manual/datetime.format.php */
				wp_date( __( 'g:i a' ), $updated->getTimestamp() )
			);
		}
	}

	/**
	 * Handles the link visibility column output.
	 *
	 * @since 4.3.0
	 *
	 * @param object $link The current link object.
	 */
	public function column_visible( $link ) {
		if ( 'Y' === $link->link_visible ) {
			_e( 'Yes' );
		} else {
			_e( 'No' );
		}
	}

	/**
	 * Handles the link rating column output.
	 *
	 * @since 4.3.0
	 *
	 * @param object $link The current link object.
	 */
	public function column_rating( $link ) {
		echo $link->link_rating;
	}

	/**
	 * Handles the default column output.
	 *
	 * @since 4.3.0
	 * @since 5.9.0 Renamed `$link` to `$item` to match parent class for PHP 8 named parameter support.
	 *
	 * @param object $item        Link object.
	 * @param string $column_name Current column name.
	 */
	public function column_default( $item, $column_name ) {
		if ( 'tags' === $column_name ) {
			$taxonomy = 'link_tag';
		} elseif ( 0 === strpos( $column_name, 'taxonomy-' ) ) {
			$taxonomy = substr( $column_name, 9 );
		} else {
			$taxonomy = false;
		}

		if ( $taxonomy ) {
			$taxonomy_object = get_taxonomy( $taxonomy );
			$terms           = wp_get_object_terms( $item->link_id, $taxonomy );

			if ( is_array( $terms ) ) {
				$term_links = array();

				foreach ( $terms as $t ) {
					$links_in_term_qv = array();

					if ( $taxonomy_object->query_var ) {
						$links_in_term_qv[ $taxonomy_object->query_var ] = $t->slug;
					} else {
						$links_in_term_qv['taxonomy'] = $taxonomy;
						$links_in_term_qv['term']     = $t->slug;
					}

					$label = esc_html( sanitize_term_field( 'name', $t->name, $t->term_id, $taxonomy, 'display' ) );

					$term_links[] = $this->get_edit_link( $links_in_term_qv, $label );
				}

				/* translators: Used between list items, there is a space after the comma. */
				echo implode( __( ', ' ), $term_links );
			} else {
				echo '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . $taxonomy_object->labels->no_terms . '</span>';
			}

			return;
		}

		/**
		 * Fires for each registered custom link column.
		 *
		 * @since 2.1.0
		 *
		 * @param string $column_name Name of the custom column.
		 * @param int    $link_id     Link ID.
		 */
		do_action( 'manage_link_custom_column', $column_name, $item->link_id );
	}

	public function display_rows() {
		foreach ( $this->items as $link ) {
			$link            = sanitize_bookmark( $link );
			$link->link_name = esc_attr( $link->link_name );
			// $link->link_category = wp_get_link_cats( $link->link_id );
			?>
		<tr id="link-<?php echo $link->link_id; ?>">
			<?php $this->single_row_columns( $link ); ?>
		</tr>
			<?php
		}
	}

	/**
	 * Generates and displays row action links.
	 *
	 * @since 4.3.0
	 * @since 5.9.0 Renamed `$link` to `$item` to match parent class for PHP 8 named parameter support.
	 *
	 * @param object $item        Link being acted upon.
	 * @param string $column_name Current column name.
	 * @param string $primary     Primary column name.
	 * @return string Row actions output for links, or an empty string
	 *                if the current column is not the primary column.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		// Restores the more descriptive, specific name for use within this method.
		$link      = $item;
		$edit_link = get_edit_bookmark_link( $link );

		$actions           = array();
		$actions['edit']   = '<a href="' . $edit_link . '">' . __( 'Edit' ) . '</a>';
		$actions['delete'] = sprintf(
			'<a class="submitdelete" href="%s" onclick="return confirm( \'%s\' );">%s</a>',
			wp_nonce_url( "link.php?action=delete&amp;link_id=$link->link_id", 'delete-bookmark_' . $link->link_id ),
			/* translators: %s: Link name. */
			esc_js( sprintf( __( "You are about to delete this link '%s'\n  'Cancel' to stop, 'OK' to delete." ), $link->link_name ) ),
			__( 'Delete' )
		);

		return $this->row_actions( $actions );
	}
}
