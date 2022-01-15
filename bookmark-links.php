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

	$table_name       = $wpdb->prefix . 'linksmeta';
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
	$wpdb->linksmeta = $wpdb->prefix . 'linksmeta';
	$wpdb->tables[]  = 'linksmeta';

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

function blinks_load() {
	blinks_loader(
		array(
			'class-wp-bookmarks.php',
			'functions.php',
		)
	);
}

add_action( 'plugins_loaded', 'blinks_load' );
