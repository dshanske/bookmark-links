<?php
/**
 * Bookmark Links
 *
 * @package           bookmark-links
 * @author            David Shanske
 * @copyright         2021 David Shanske
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Bookmark Links
 * Plugin URI:        https://github.com/dshanske/bookmark-links
 * Description:       Upgrades the links functionality in WordPress to act as a bookmarking system.
 * Version:           0.0.1
 * Requires at least: 4.7
 * Requires PHP:      5.6
 * Author:            David Shanske
 * Author URI:        https://david.shanske.com
 * Text Domain:       bookmark-links
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

/* Before anything else, enable the links manager system */
 add_filter( 'pre_option_link_manager_enabled', '__return_true' );

register_activation_hook( __FILE__, 'blinks_create_tables' );

function blinks_create_tables() {
	global $wpdb;

	// Let's not break the site with exception messages
	$wpdb->hide_errors();

	if ( ! function_exists( 'dbDelta' ) ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}

	$collate = '';
	if ( $wpdb->has_cap( 'collation' ) ) {
		$charset_collate = $wpdb->get_charset_collate();
	}

	$table_name       = $wpdb->prefix . 'linkmeta';
	$max_index_length = 191;

	$schema = "CREATE TABLE $table_name (
			meta_id bigint(20) unsigned NOT NULL auto_increment,
			link_id bigint(20) unsigned NOT NULL default '0',
			meta_key varchar(255) default NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY link (link_id),
			KEY meta_key (meta_key($max_index_length))
		) $charset_collate;";

	dbDelta( $schema );
}

function blinks_metadata_api() {
	global $wpdb;
	$wpdb->linkmeta = $wpdb->prefix . 'linkmeta';
	$wpdb->tables[] = 'linkmeta';

	return;
}

// hook into init for single site, priority 0 = highest priority
add_action( 'init', 'blinks_metadata_api', 0 );

// hook in to switch blog to support multisite
add_action( 'switch_blog', 'blinks_metadata_api', 0 );

/**
 * Load files.
 *
 * Checks for the existence of and loads files.
 *
 * @param array  $files An array of filenames.
 * @param string $dir The directory the files can be found in, relative to the current directory.
 */
function blinks_loader( $files, $dir = 'includes/' ) {
	if ( empty( $files ) ) {
		return;
	}
		$path = plugin_dir_path( __FILE__ ) . $dir;
	foreach ( $files as $file ) {
		if ( file_exists( $path . $file ) ) {
			require_once $path . $file;
		} else {
			error_log( $path . $file );
		}
	}
}

function blinks_options() {
	register_setting(
		'writing',
		'link_visible',
		array(
			'type'         => 'boolean',
			'description'  => __( 'Bookmarks Visible by Default', 'bookmark-links' ),
			'show_in_rest' => true,
			'default'      => 1,
		)
	);

	register_setting(
		'writing',
		'link_toread',
		array(
			'type'         => 'boolean',
			'description'  => __( 'Bookmarks Read Later By Default', 'bookmark-links' ),
			'show_in_rest' => true,
			'default'      => 0,
		)
	);
}

add_action( 'init', 'blinks_options' );


function blinks_settings_field() {
	add_settings_field(
		'link_visible',
		__( 'Bookmarks Visible by Default', 'bookmark-links' ),
		'blinks_settings_checkbox',
		'writing',
		'default',
		array(
			'name' => 'link_visible',
		)
	);

	add_settings_field(
		'link_toread',
		__( 'Bookmarks Read Later by Default', 'bookmark-links' ),
		'blinks_settings_checkbox',
		'writing',
		'default',
		array(
			'name' => 'link_toread',
		)
	);
}

add_action( 'admin_init', 'blinks_settings_field' );

function blinks_settings_checkbox( $args ) {
	if ( ! array_key_exists( 'name', $args ) ) {
		return;
	}
	$checked = (int) get_option( $args['name'] );
	printf( '<input name="%1$s" type="hidden" value="0" />', esc_attr( $args['name'] ) ); // phpcs:ignore
	printf( '<input name="%1$s" type="checkbox" value="1" %2$s />', esc_attr( $args['name'] ), checked( 1, $checked, false) ); // phpcs:ignore	
}

function blinks_export_menu() {
		add_management_page(
			__( 'Export Bookmarks', 'bookmark-links' ), // page title
			__( 'Export Bookmarks', 'bookmark-links' ), // menu title
			'manage_options', // access capability
			'bookmark-links',
			'blinks_export_page'
		);
}

add_action( 'admin_menu', 'blinks_export_menu' );


function blinks_export_page() {
	?>

	<div class="wrap">
	<h1><?php esc_html_e( 'Bookmarks Export', 'bookmark-links' ); ?></h1>
	
	<p><?php _e( 'When you click the button below WordPress will create an JSON file for you to save to your computer.', 'bookmark-links' ); ?></p>
	<p><?php _e( 'Once you&#8217;ve saved the download file, you can use the Import function in another WordPress Installation', 'bookmark-links' ); ?></p>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="GET" >
	<input type="hidden" name="action" value="downloadbookmarks" />
	<?php submit_button( __( 'Download Export File' ) ); ?>
	</form>
	</div>
	<?php

}


function blinks_download_handler() {
	$bookmarks = blinks_prepare_export_bookmarks();

	$sitename = sanitize_key( get_bloginfo( 'name' ) );
	if ( ! empty( $sitename ) ) {
		$sitename .= '.';
	}
	$date     = gmdate( 'Y-m-d' );
	$filename = $sitename . 'Bookmarks.' . $date . '.json';
	header( 'Content-Description: File Transfer' );
	header( 'Content-Disposition: attachment; filename=' . $filename );
	header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ), true );
	echo wp_json_encode( $bookmarks );
}

add_action( 'admin_post_downloadbookmarks', 'blinks_download_handler' );


function blinks_register() {
	register_taxonomy(
		'link_tag',
		'link',
		array(
			'hierarchical'         => false,
			'labels'               => array(
				'name'                       => __( 'Link Tags' ),
				'singular_name'              => __( 'Link Tag' ),
				'search_items'               => __( 'Search Link Tag' ),
				'popular_items'              => null,
				'all_items'                  => __( 'All Link Tags' ),
				'edit_item'                  => __( 'Edit Link Tag' ),
				'update_item'                => __( 'Update Link Tag' ),
				'add_new_item'               => __( 'Add New Link Tag' ),
				'new_item_name'              => __( 'New Link Tag Name' ),
				'separate_items_with_commas' => __( 'Separate Items with Commas' ),
				'add_or_remove_items'        => __( 'Add Link Tag' ),
				'choose_from_most_used'      => __( 'Choose From Most Used', 'default' ),
				'back_to_items'              => __( '&larr; Go to Link Categories' ),
				'no_terms'                   => __( 'No Terms' ),
			),
			'capabilities'         => array(
				'manage_terms' => 'manage_links',
				'edit_terms'   => 'manage_links',
				'delete_terms' => 'manage_links',
				'assign_terms' => 'manage_links',
			),
			'query_var'            => false,
			'rewrite'              => false,
			'public'               => false,
			'show_ui'              => true,
			'show_admin_column'    => true,
			'show_in_rest'         => true,
			'meta_box_sanitize_cb' => 'taxonomy_meta_box_sanitize_cb_input',
		)
	);

	$args = array(
		'type'         => 'string',
		'description'  => __( 'Date Link was Published', 'bookmark-links' ),
		'single'       => true,
		'show_in_rest' => true,
	);
	register_meta( 'link', 'link_published', $args );

	$args = array(
		'type'         => 'string',
		'description'  => __( 'Link Author Name', 'bookmark-links' ),
		'single'       => true,
		'show_in_rest' => true,
	);
	register_meta( 'link', 'link_author', $args );

	$args = array(
		'type'         => 'string',
		'description'  => __( 'Link Author URL', 'bookmark-links' ),
		'single'       => true,
		'show_in_rest' => true,
	);
	register_meta( 'link', 'link_author_url', $args );

	$args = array(
		'type'         => 'string',
		'description'  => __( 'Link Author Photo', 'bookmark-links' ),
		'single'       => true,
		'show_in_rest' => true,
	);
	register_meta( 'link', 'link_author_photo', $args );

	$args = array(
		'type'         => 'string',
		'description'  => __( 'Link Site', 'bookmark-links' ),
		'single'       => true,
		'show_in_rest' => true,
	);
	register_meta( 'link', 'link_site', $args );

	$args = array(
		'type'         => 'string',
		'description'  => __( 'Link Site URL', 'bookmark-links' ),
		'single'       => true,
		'show_in_rest' => true,
	);
	register_meta( 'link', 'link_site_url', $args );

	$args = array(
		'type'        => 'string',
		'description' => __( 'Link To Read', 'bookmark-links' ),
		'single'      => true,
	);
	register_meta( 'link', 'link_toread', $args );

}

function blinks_load() {
	blinks_loader(
		array(
			'compat-functions.php',
			'bookmark-functions.php',
			'class-blinks-rest-bookmarks-controller.php',
			'class-blinks-rest-link-meta-fields.php',
			'class-blinks-bookmark-query.php',
			'functions.php',
			'class-wp-bookmark.php',
			'bookmark-links-metabox.php',
			'class-pinboard-json-importer.php',
			'class-blinks-json-importer.php',
		)
	);

	$parse_this_load = array(
		'compat-functions.php',
		'autoload.php',
		'functions.php',
	);

	if ( ! class_exists( 'REST_Parse_This' ) ) {
		$parse_this_load[] = 'class-rest-parse-this.php';
	}

	blinks_loader(
		$parse_this_load,
		'/lib/parse-this/includes/'
	);

	blinks_register();
}

add_action( 'plugins_loaded', 'blinks_load' );


function blinks_rest_api() {
	$controller = new Blinks_REST_Bookmarks_Controller();
	$controller->register_routes();
}

add_action( 'rest_api_init', 'blinks_rest_api' );


function blinks_link_manager() {
	add_screen_option(
		'per_page',
		array(
			'label'   => __( 'Number of links per screen', 'bookmark-links' ),
			'default' => '25',
			'max'     => '300',
			'option'  => 'links_per_page',
		)
	);
	require_once __DIR__ . '/includes/link-manager.php';
	exit();
}

add_action( 'load-link-manager.php', 'blinks_link_manager' );

function blinks_link_add() {
	require_once __DIR__ . '/includes/link-add.php';
	exit();
}

add_action( 'load-link-add.php', 'blinks_link_add' );

function blinks_set_screen_option( $status, $option, $value ) {
	if ( 'links_per_page' === $option ) {
		return $value;
	}
}

add_filter( 'set-screen-option', 'blinks_set_screen_option', 10, 3 );

function blinks_link_php() {
	require_once __DIR__ . '/includes/link.php';
	exit();
}

add_action( 'load-link.php', 'blinks_link_php' );

function blinks_category_taxonomy( $args, $taxonomy ) {
	if ( 'link_category' === $taxonomy ) {
		$args['show_in_rest'] = true;
	}
	return $args;
}

add_filter( 'register_taxonomy_args', 'blinks_category_taxonomy', 10, 2 );
