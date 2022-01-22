<?php
/**
 * Metabox Functions.
 *
 * @package Bookmark_Links
 */

function link_tags_meta_box( $link ) {
	$taxonomy              = get_taxonomy( 'link_tag' );
	$user_can_assign_terms = current_user_can( $taxonomy->cap->assign_terms );
	$comma                 = _x( ',', 'tag delimiter' );

	if ( isset( $link->link_id ) ) {
		$terms_to_edit = get_link_terms_to_edit( $link->link_id, 'link_tag' );
	} else {
		$terms_to_edit = '';
	}

	?>

<div class="tagsdiv" id="taxonomy-link_tag">
	<div class="tags">
		<p><textarea name="tags_input" rows="3" class="widefat the-tags" id="tax-input-link_tag" <?php disabled( ! $user_can_assign_terms ); ?> aria-describedby="new-tag-link_tag-desc"><?php echo str_replace( ',', $comma . ' ', $terms_to_edit ); // textarea_escaped by esc_attr() ?></textarea></p>
	</div>
	<p class="howto" id="new-tag-link_tag-desc"><?php echo $taxonomy->labels->separate_items_with_commas; ?></p>
	</div>
	<?php
}

function add_blinks_meta_boxes() {
	add_meta_box( 'linktagdiv', __( 'Tags', 'bookmark-links' ), 'link_tags_meta_box', null, 'normal', 'core' );
}

add_action( 'add_meta_boxes', 'add_blinks_meta_boxes' );

function link_tag_admin_page() {
	global $submenu;
	$tax = get_taxonomy( 'link_tag' );
	add_links_page(
		esc_attr( $tax->labels->menu_name ),
		esc_attr( $tax->labels->menu_name ),
		$tax->cap->manage_terms,
		'edit-tags.php?taxonomy=' . $tax->name
	);
}


/* Adds the taxonomy page in the admin. */
add_action( 'admin_menu', 'link_tag_admin_page' );


/**
 * Set Links menu as parent for Link Taxonomy edit page.
 *
 * @param string $parent
 *
 * @return string
 */
function blinks_parent_menu( $parent = '' ) {
	global $pagenow;

	// If we're editing one of the link taxonomies
	// We must be within the link menu, so highlight that
	if ( ! empty( $_GET['taxonomy'] ) && 'edit-tags.php' === $pagenow && 'link_tag' === sanitize_key( $_GET['taxonomy'] ) ) {
		$parent = 'link-manager.php';
	}
	return $parent;
}

add_filter( 'parent_file', 'blinks_parent_menu' );


/*
 * Function Hooks into the New and Update Link hooks to allow for using the same _POST properties as recognized by the save post functionality.
 */
function save_link_data( $link_id ) {
	if ( ! current_user_can( 'manage_links' ) ) {
		return;
	}

	// Convert taxonomy input to term IDs, to avoid ambiguity.
	if ( isset( $_POST['tax_input'] ) ) {
		foreach ( (array) $_POST['tax_input'] as $taxonomy => $terms ) {
			$tax_object = get_taxonomy( $taxonomy );
			if ( $tax_object && isset( $tax_object->meta_box_sanitize_cb ) ) {
				$_POST['tax_input'][ $taxonomy ] = call_user_func_array( $tax_object->meta_box_sanitize_cb, array( $taxonomy, $terms ) );
				if ( current_user_can( $tax_object->cap->assign_terms ) ) {
					wp_set_object_terms( $link_id, $_POST['tax_input'][ $taxonomy ], $taxonomy );
				}
			}
		}
	}

	if ( isset( $_POST['tags_input'] ) ) {
		$tax_object = get_taxonomy( 'link_tag' );
		if ( isset( $tax_object->meta_box_sanitize_cb ) ) {
			$_POST['tags_input'] = call_user_func_array( $tax_object->meta_box_sanitize_cb, array( 'link_tag', $_POST['tags_input'] ) );
		}
	}

	if ( ! empty( $_POST['meta_input'] ) ) {
		foreach ( $_POST['meta_input'] as $field => $value ) {
			$value = sanitize_text_field( $value );
			$field = sanitize_key( $field );
			update_post_meta( $post_ID, $field, $value );
		}
	}

}

add_action( 'edit_link', 'save_link_data' );
add_action( 'add_link', 'save_link_data' );
