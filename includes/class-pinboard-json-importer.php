<?php

if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) {
	return;
}

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( ! class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) ) {
		require_once $class_wp_importer;
	}
}

/** Load WordPress Administration Bootstrap */
$parent_file  = 'tools.php';
$submenu_file = 'import.php';
$title        = __( 'Import Pinboard JSON', 'bookmark-links' );

/**
 * OPML Importer, derived from OPML Importer plugin.
 *
 * @package Bookmark_Links
 */
if ( class_exists( 'WP_Importer' ) ) {
	class Pinboard_JSON_Importer extends WP_Importer {

		function dispatch() {
			global $wpdb, $user_ID;
			$step = isset( $_POST['step'] ) ? $_POST['step'] : 0;

			switch ( $step ) {
				case 0: {
					include_once ABSPATH . 'wp-admin/admin-header.php';
					if ( ! current_user_can( 'manage_links' ) ) {
						wp_die( __( 'You do not have permission to add bookmarks', 'bookmark-links' ) );
					}

					$opmltype = 'blogrolling'; // default.
					?>

<div class="wrap">
<h2><?php _e( 'Import your bookmarks from a Pinboard file', 'bookmark-links' ); ?> </h2>
<form enctype="multipart/form-data" action="admin.php?import=pinboard" method="post" name="pinboard_bookmarks">
					<?php wp_nonce_field( 'import-bookmarks' ); ?>

<p><?php _e( 'You may import a Pinboard JSON export file here.', 'bookmark-links' ); ?></p>
<div style="width: 70%; margin: auto; height: 8em;">
<input type="hidden" name="step" value="1" />
<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo wp_max_upload_size(); ?>" />
<div style="width: 48%;" class="alignleft">
<h3><label for="pinboard_url"><?php _e( 'Specify a Pinboard URL:', 'bookmarks-links' ); ?></label></h3>
<input type="text" name="pinboard_url" id="pinboard_url" size="50" class="code" style="width: 90%;" value="http://" />
</div>

<div style="width: 48%;" class="alignleft">
<h3><label for="userfile"><?php _e( 'Or choose from your local disk:', 'bookmark-links' ); ?></label></h3>
<input id="userfile" name="userfile" type="file" size="30" />
</div>

</div>

<p style="clear: both; margin-top: 1em;"><label for="cat_id"><?php _e( 'Now select a category you want to put these links in.', 'bookmark-links' ); ?></label><br />
					<?php _e( 'Category:', 'bookmark-links' ); ?> <select name="cat_id" id="cat_id">
					<?php
						$categories = get_terms( 'link_category', array( 'get' => 'all' ) );
					foreach ( $categories as $category ) {
						?>
<option value="<?php echo $category->term_id; ?>"><?php echo esc_html( apply_filters( 'link_category', $category->name ) ); ?></option>
							<?php
					} // end foreach
					?>
</select></p>

<p class="submit"><input type="submit" name="submit" value="<?php esc_attr_e( 'Import Pinboard JSON File', 'bookmark-links' ); ?>" /></p>
</form>

</div>
					<?php
					break;
				} // end case 0

				case 1: {
					check_admin_referer( 'import-bookmarks' );

					include_once ABSPATH . 'wp-admin/admin-header.php';
					if ( ! current_user_can( 'manage_links' ) ) {
						wp_die( __( 'You do not have permission to add bookmarks', 'bookmark-links' ) );
					}
					?>
<div class="wrap">

<h2><?php _e( 'Importing...', 'bookmark-links' ); ?></h2>
					<?php
					$cat_id = abs( (int) $_POST['cat_id'] );
					if ( $cat_id < 1 ) {
						$cat_id = 1;
					}

					$pinboard_url = esc_url_raw( $_POST['pinboard_url'] );
					if ( isset( $pinboard_url ) && $pinboard_url != '' && wp_http_validate_url( $pinboard_url ) ) {
						$blogrolling = true;
					} else { // try to get the upload file.
						$overrides                   = array(
							'test_form' => false,
							'test_type' => false,
						);
						$_FILES['userfile']['name'] .= '.txt';
						$file                        = wp_handle_upload( $_FILES['userfile'], $overrides );

						if ( isset( $file['error'] ) ) {
							wp_die( $file['error'] );
						}

						$url          = $file['url'];
						$pinboard_url = $file['file'];
						$blogrolling  = false;
					}

					// global $opml, $updated_timestamp, $all_links, $map, $names, $urls, $targets, $descriptions, $feeds;
					if ( isset( $pinboard_url ) && $pinboard_url != '' ) {
						if ( $blogrolling === true ) {
							$pinboard = wp_remote_fopen( $pinboard_url );
						} else {
							$pinboard = file_get_contents( $pinboard_url );
						}

						if ( ! $pinboard ) {
							return;
						}

						$links = json_decode( $pinboard, true );
						$wptz  = wp_timezone();

						foreach ( $links as $link ) {
							$updated = new DateTime( $link['time'] );
							$updated->setTimeZone( $wptz );
							$tags = explode( ' ', $link['tags'] );
							foreach ( $tags as $key => $tag ) {
								$tags[ $key ] = str_replace( '_', ' ', $tag );
							}
							$bookmark = array(
								'link_url'         => $link['href'],
								'link_name'        => $link['description'],
								'link_description' => $link['extended'],
								'link_category'    => array( $cat_id ),
								'link_updated'     => $updated->format( 'Y-m-d H:i:s' ),
								'link_owner'       => get_current_user_id(),
								'link_toread'      => ( 'yes' === $link['toread'] ) ? 1 : 0,
								'link_visibility'  => ( 'yes' === $link['shared'] ) ? 'Y' : 'N',
								'tags_input'       => $tags,
								'meta_input'       => array(
									'pinboard_hash' => $link['hash'],
								),

							);
							blinks_insert_bookmark( $bookmark );
							echo sprintf( '<p>' . __( 'Inserted <strong>%s</strong>', 'bookmark-links' ) . '</p>', $bookmark['link_name'] );
						}
						?>

<p><?php printf( __( 'Inserted %1$d links into category %2$s. All done! Go <a href="%3$s">manage those links</a>.', 'bookmark-links' ), count( $links ), $cat_id, 'link-manager.php' ); ?></p>

						<?php
					} // end if got url
					else {
						echo '<p>' . __( 'You need to supply your Pinboard url. Press back on your browser and try again', 'bookmark-links' ) . "</p>\n";
					} // end else

					if ( ! $blogrolling ) {
						do_action( 'wp_delete_file', $pinboard_url );
					}
					@unlink( $pinboard_url );
					?>
</div>
					<?php
					break;
				} // end case 1
			} // end switch
		}

		function Pinboard_JSON_Import() {}
	}

	$pinboard_importer = new Pinboard_JSON_Importer();

	register_importer( 'pinboard', __( 'Pinboard JSON', 'opml-importer' ), __( 'Import bookmarks in from a Pinboard JSON export.', 'bookmark-links' ), array( &$pinboard_importer, 'dispatch' ) );

} // class_exists( 'WP_Importer' )

