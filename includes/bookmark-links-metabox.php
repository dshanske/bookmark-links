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

function blinks_manage_link_tags_columns( $columns ) {
	$columns['link_tags'] = __( 'Tags', 'bookmark-links' );
	unset( $columns['posts'] );
	return $columns;
}


add_filter( 'manage_edit-link_tag_columns', 'blinks_manage_link_tags_columns' );

function blinks_link_tag_column( $string, $name, $id ) {
	if ( 'link_tags' !== $name ) {
		return;
	}

	$term = get_term( $id, 'link_tag' );


	$count = number_format_i18n( $term->count );

	printf( "<a href='link-manager.php?taxonomy=link_tag&term=%s'>%s</a>", $term->slug, $count );
}

add_filter( 'manage_link_tag_custom_column', 'blinks_link_tag_column', 10, 3 );
