<?php
/**
 * Add Link Administration Screen.
 *
 * @package Bookmark_Links
 */

if ( ! current_user_can( 'manage_links' ) ) {
	wp_die( __( 'Sorry, you are not allowed to add links to this site.', 'default' ) );
}

$title       = __( 'Add New Link' );
$parent_file = 'link-manager.php';

wp_reset_vars( array( 'action', 'cat_id', 'link_id', 'tag_id' ) );

wp_enqueue_script( 'link' );
wp_enqueue_script( 'xfn' );

if ( wp_is_mobile() ) {
	wp_enqueue_script( 'jquery-touch-punch' );
}

$link = get_default_link_to_edit();
require __DIR__ . '/edit-link-form.php';

require_once ABSPATH . 'wp-admin/admin-footer.php';
