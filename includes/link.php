<?php
/**
 * Manage link administration actions.
 *
 * This page is accessed by the link management pages and handles the forms and
 * Ajax processes for link actions.
 *
 * @package Bookmark Links
 */

global $cat_id;
global $action;
global $link_id;


if ( ! empty( $_REQUEST['action'] ) ) {
	$action = $_REQUEST['action'];
	/**
	 * Fires when an 'action' request variable is sent.
	 *
	 * The dynamic portion of the hook name, `$action`, refers to
	 * the action derived from the `GET` or `POST` request.
	 *
	 * @since 2.6.0
	 */
	do_action( "admin_action_{$action}" );
}

wp_reset_vars( array( 'action', 'cat_id', 'link_id' ) );

if ( ! current_user_can( 'manage_links' ) ) {
	wp_link_manager_disabled_message();
}

if ( ! empty( $_POST['deletebookmarks'] ) ) {
	$action = 'deletebookmarks';
}
if ( ! empty( $_POST['move'] ) ) {
	$action = 'move';
}
if ( ! empty( $_POST['linkcheck'] ) ) {
	$linkcheck = $_POST['linkcheck'];
}

$this_file = admin_url( 'link-manager.php' );

switch ( $action ) {
	case 'deletebookmarks':
		check_admin_referer( 'bulk-bookmarks' );

		// For each link id (in $linkcheck[]) change category to selected value.
		if ( count( $linkcheck ) === 0 ) {
			wp_redirect( $this_file );
			exit;
		}

		$deleted = 0;
		foreach ( $linkcheck as $link_id ) {
			$link_id = (int) $link_id;

			if ( blinks_delete_bookmark( $link_id ) ) {
				$deleted++;
			}
		}

		wp_redirect( "$this_file?deleted=$deleted" );
		exit;
	case 'move':
		check_admin_referer( 'bulk-bookmarks' );

		// For each link id (in $linkcheck[]) change category to selected value.
		if ( count( $linkcheck ) === 0 ) {
			wp_redirect( $this_file );
			exit;
		}
		$all_links = implode( ',', $linkcheck );
		/*
		 * Should now have an array of links we can change:
		 *     $q = $wpdb->query("update $wpdb->links SET link_category='$category' WHERE link_id IN ($all_links)");
		 */

		wp_redirect( $this_file );
		exit;

	case 'add':
		check_admin_referer( 'add-bookmark' );

		$redir   = wp_get_referer();
		$link_id = blinks_insert_bookmark( $_POST, true );
		if ( is_wp_error( $link_id ) ) {
			$redir = add_query_arg( 'error', $link_id->get_error_message(), $redir );
		} else {
			$redir = add_query_arg( 'added', 'true', $redir );
		}

		wp_redirect( $redir );
		exit;

	case 'read':
		$link_id = (int) $_GET['link_id'];
		check_admin_referer( 'read-bookmark_' . $link_id );

		delete_link_meta( $link_id, 'link_toread' );

		wp_redirect( $this_file );
		exit;

	case 'toread':
		$link_id = (int) $_GET['link_id'];
		check_admin_referer( 'toread-bookmark_' . $link_id );

		update_link_meta( $link_id, 'link_toread', true );

		wp_redirect( $this_file );
		exit;

	case 'refresh':
		$link_id = (int) $_GET['link_id'];
		check_admin_referer( 'refresh-bookmark_' . $link_id );

		blinks_refresh_bookmark( $link_id );

		wp_redirect( $this_file );
		exit;



	case 'save':
		$link_id = (int) $_POST['link_id'];
		check_admin_referer( 'update-bookmark_' . $link_id );

		blinks_update_bookmark( $_POST );

		wp_redirect( $this_file );
		exit;

	case 'delete':
		$link_id = (int) $_GET['link_id'];
		check_admin_referer( 'delete-bookmark_' . $link_id );

		blinks_delete_bookmark( $link_id );

		wp_redirect( $this_file );
		exit;

	case 'edit':
		wp_enqueue_script( 'link' );
		wp_enqueue_script( 'xfn' );

		if ( wp_is_mobile() ) {
			wp_enqueue_script( 'jquery-touch-punch' );
		}

		$parent_file  = 'link-manager.php';
		$submenu_file = 'link-manager.php';
		$title        = __( 'Edit Link' );

		$link_id = (int) $_GET['link_id'];

		$link = get_link_to_edit( $link_id );
		if ( ! $link ) {
			wp_die( __( 'Link not found.' ) );
		}

		require __DIR__ . '/edit-link-form.php';
		require_once ABSPATH . 'wp-admin/admin-footer.php';
		break;

	default:
		break;
}
