<?php
/**
 * Metabox Functions.
 *
 * @package Bookmark_Links
 */

function link_tags_meta_box( $link, $box ) {
	$defaults = array( 'taxonomy' => 'link_tag' );
	if ( ! isset( $box['args'] ) || ! is_array( $box['args'] ) ) {
		$args = array();
	} else {
		$args = $box['args'];
	}
	$parsed_args           = wp_parse_args( $args, $defaults );
	$tax_name              = esc_attr( $parsed_args['taxonomy'] );
	$taxonomy              = get_taxonomy( $tax_name );
	$user_can_assign_terms = current_user_can( $taxonomy->cap->assign_terms );
	$comma                 = _x( ',', 'tag delimiter' );

	if ( isset( $link->link_id ) ) {
		$terms_to_edit = get_link_terms_to_edit( $link->link_id, $tax_name );
	} else {
		$terms_to_edit = '';
	}

	?>

	<div class="tagsdiv" id="<?php echo $tax_name; ?>">
		<div class="jaxtag">
			<div class="nojs-tags">
			<label for="tax-input-<?php echo $tax_name; ?>"><?php echo $taxonomy->labels->add_or_remove_items; ?></label>
			<p><textarea name="<?php echo "tax_input[$tax_name]"; ?>" rows="3" cols="20" class="the-tags" id="tax-input-<?php echo $tax_name; ?>" <?php disabled( ! $user_can_assign_terms ); ?> aria-describedby="new-tag-<?php echo $tax_name; ?>-desc"><?php echo str_replace( ',', $comma . ' ', $terms_to_edit ); // textarea_escaped by esc_attr() ?></textarea></p>
			</div>
		<?php if ( $user_can_assign_terms ) : ?>
			<p class="howto" id="new-tag-<?php echo $tax_name; ?>-desc"><?php echo $taxonomy->labels->separate_items_with_commas; ?></p>
				<?php elseif ( empty( $terms_to_edit ) ) : ?>
					<p><?php echo $taxonomy->labels->no_terms; ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

function link_meta_meta_box( $link ) {
	if ( ! isset( $link->link_id ) ) {
		return;
	}
	echo '<div id="link_meta_box">';

	$linkmeta = get_link_meta( $link->link_id );
	if ( empty( $linkmeta ) ) {
		_e( 'No Link Metadata Found', 'bookmark-links' );
		return;
	}
	?>
	<table>
		<thead>
			<tr>
				<th class="key-column"><?php esc_html_e( 'Key', 'bookmark-links' ); ?></th>
				<th class="value-column"><?php esc_html_e( 'Value', 'bookmark-links' ); ?></th>
			</tr>
		</thead>
		<tbody>

	<?php
	foreach ( $linkmeta as $key => $value ) {
		$value = wp_json_encode( $value, JSON_PRETTY_PRINT );
		?>
		<tr>
			<td class="key-column"><?php echo esc_html( $key ); ?></td>
			<td class="value-column"><?php echo esc_html( $value ); ?></td>
		</tr>
		<?php
	}
	echo '</tbody></table>';
	echo '</div>';
}

/**
 * Display advanced link options form fields.
 *
 * @param object $link
 */
function blinks_advanced_meta_box( $link ) {
	?>
	<table class="links-table" cellpadding="0">
		<tr>
			<th scope="row"><label for="rss_uri"><?php _e( 'Feed Address', 'bookmark-links' ); ?></label></th>
				<td><input name="link_rss" class="code" type="text" id="rss_uri" maxlength="255" value="<?php echo ( isset( $link->link_rss ) ? esc_attr( $link->link_rss ) : '' ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="link_image"><?php _e( 'Featured Image Address', 'bookmark-links' ); ?></label></th>
			<td><input type="text" name="link_image" class="code" id="link_image" maxlength="255" value="<?php echo ( isset( $link->link_image ) ? esc_attr( $link->link_image ) : '' ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="link_author"><?php _e( 'Author Name' ); ?></label></th>
			<td><input type="text" name="link_author" class="code" id="link_author" maxlength="255" value="<?php echo blinks_form_get_meta( $link, 'link_author' ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="link_author_url"><?php _e( 'Author URL' ); ?></label></th>
			<td><input type="text" name="link_author_url" class="code" id="link_author_url" maxlength="255" value="<?php echo blinks_form_get_meta( $link, 'link_author_url' ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="link_author_photo"><?php _e( 'Author Photo URL' ); ?></label></th>
			<td><input type="text" name="link_author_photo" class="code" id="link_author_photo" maxlength="255" value="<?php echo blinks_form_get_meta( $link, 'link_author_photo' ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="link_site"><?php _e( 'Site or Publication Name' ); ?></label></th>
			<td><input type="text" name="link_site" class="code" id="link_site" maxlength="255" value="<?php echo blinks_form_get_meta( $link, 'link_site' ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="link_site_url"><?php _e( 'Site or Publication URL' ); ?></label></th>
			<td><input type="text" name="link_site_url" class="code" id="link_site_url" maxlength="255" value="<?php echo blinks_form_get_meta( $link, 'link_site_url' ); ?>" /></td>
		</tr>
	</table>
	 <?php
}


/**
 * Returns meta for form data
 *
 * @param object $link
 * @param string $key
 */
function blinks_form_get_meta( $link, $key ) {
	if ( ! isset( $link->link_id ) ) {
		return '';
	}
	$meta = get_link_meta( $link->link_id, $key, true );
	if ( $meta ) {
		return esc_attr( $meta );
	}
	return '';

}

function blinks_ratings_meta_box( $link ) {
	?>
		<label for="link_rating"><span class="dashicons dashicons-star-filled"></span><?php _e( 'Rating' ); ?></label>
				<select name="link_rating" id="link_rating" size="1">
			  <?php
				for ( $parsed_args = 0; $parsed_args <= 10; $parsed_args++ ) {
					echo '<option value="' . $parsed_args . '"';
					if ( isset( $link->link_rating ) && $link->link_rating == $parsed_args ) {
						echo ' selected="selected"';
					}
					echo( '>' . $parsed_args . '</option>' );
				}
				?>
				
				</select>&nbsp;<p><?php _e( '(Leave at 0 for no rating.)' ); ?></p>
	<?php
}

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
	$columns['link_tags'] = __( 'Links', 'default' );
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


//
// Link-related Meta Boxes.
//

/**
 * Display link create form fields.
 *
 * @param object $link
 */
function blinks_submit_meta_box( $linkarr ) {

	// If this is an add link screen, set the visibility based on the default option.
	if ( ! isset( $linkarr->link_id ) ) {
		$linkarr->link_visible = blinks_get_default_link_visible();
		$toread                = get_option( 'link_toread' );
	} else {
		$toread = (int) get_link_meta( $linkarr->link_id, 'link_toread', true );
	}
	$link = new WP_Bookmark( $linkarr );
	?>
<div class="submitbox" id="submitlink">

<div id="minor-publishing">

	<?php // Hidden submit button early on so that the browser chooses the right button when form is submitted with Return key. ?>
<div style="display:none;">
	<?php submit_button( __( 'Save' ), '', 'save', false ); ?>
</div>

<div id="minor-publishing-actions">
<div id="preview-action">
	<?php if ( ! empty( $link->link_id ) ) { ?>
	<a class="preview button" href="<?php echo $link->link_url; ?>" target="_blank"><?php _e( 'Visit Link' ); ?></a>
<?php } ?>
</div>
<div class="clear"></div>
</div>

<div id="misc-publishing-actions">
<div class="misc-pub-section misc-pub-private" id="visibility">
	<label for="link_private" class="selectit">

	<?php _e( 'Keep this link private' ); ?>
	<input id="link_private" name="link_visible" type="checkbox" value="N" <?php checked( $link->link_visible, 'N' ); ?> /> 

	</label>
</div>
<div class="misc-pub-section misc-pub-toread" id="toread">
	<label for="link_private" class="selectit">
	<span class="dashicons dashicons-book-alt"></span>
	<?php _e( 'Read Later' ); ?>
	<input id="link_toread" name="link_toread" type="checkbox" value="1" <?php checked( $toread, 1 ); ?> /> 
	</label>
</div>
	<?php
	/**
	 * Fires before the link updated setting in the Publish meta box.
	 *
	 * @param WP_Bookmark $link object for the current link.
	 */
	 do_action( 'link_submitbox_minor_actions', $link );

	?>
<div class="misc-pub-section curtime misc-pub-curtime">
	<?php
	if ( '0000-00-00 00:00:00' !== $link->link_updated ) {
		$updated = sprintf(
		/* translators: 1: Link date, 2: Link time. */
			__( '%1$s at %2$s' ),
			/* translators: Publish box date format, see https://www.php.net/manual/datetime.format.php */
					date_i18n( _x( 'M j, Y', 'link box date format' ), strtotime( $link->link_updated ) ),
			/* translators: Publish box time format, see https://www.php.net/manual/datetime.format.php */
					date_i18n( _x( 'H:i', 'link box time format' ), strtotime( $link->link_updated ) )
		);
	} else {
		$updated = __( 'Not Set', 'bookmark-links' );
	}
	?>
						<span id="timestamp">
						<?php
						/* translators: %s: Updated date. */
						printf( __( 'Last Updated: %s' ), '<b>' . $updated . '</b>' );
						?>
						</span>
						</div>

</div>

</div>

<div id="major-publishing-actions">
	<?php
	/** This action is documented in wp-admin/includes/meta-boxes.php */
	do_action( 'post_submitbox_start', null );
	?>
<div id="delete-action">
	<?php
	if ( ! empty( $_GET['action'] ) && 'edit' === $_GET['action'] && current_user_can( 'manage_links' ) ) {
		printf(
			'<a class="submitdelete deletion" href="%s" onclick="return confirm( \'%s\' );">%s</a>',
			wp_nonce_url( "link.php?action=delete&amp;link_id=$link->link_id", 'delete-bookmark_' . $link->link_id ),
			/* translators: %s: Link name. */
			esc_js( sprintf( __( "You are about to delete this link '%s'\n  'Cancel' to stop, 'OK' to delete." ), $link->link_name ) ),
			__( 'Delete' )
		);
	}
	?>
</div>

<div id="publishing-action">
	<?php if ( ! empty( $link->link_id ) ) { ?>
	<input name="save" type="submit" class="button button-primary button-large" id="publish" value="<?php esc_attr_e( 'Update Link' ); ?>" />
<?php } else { ?>
	<input name="save" type="submit" class="button button-primary button-large" id="publish" value="<?php esc_attr_e( 'Add Link' ); ?>" />
<?php } ?>
</div>
<div class="clear"></div>
</div>
	<?php
	/**
	 * Fires at the end of the Publish box in the Link editing screen.
	 *
	 * @since 2.5.0
	 */
	do_action( 'submitlink_box' );
	?>
<div class="clear"></div>
</div>
	<?php
}

function blinks_default_hidden_columns( $hidden, $screen ) {
	if ( 'link-manager' === $screen->id ) {
		$hidden = array( 'rel' );
	}

	if ( 'link' === $screen->id ) {
		$hidden = array( 'linkxfndiv', 'linktargetdiv' );
	}
	return $hidden;
}

add_filter( 'default_hidden_columns', 'blinks_default_hidden_columns', 10, 2 );

function blinks_enqueue_admin_script( $hook ) {
	if ( 'link.php' !== $hook ) {
		return;
	}
	wp_enqueue_script( 'tags-box' );
}

add_action( 'admin_enqueue_scripts', 'blinks_enqueue_admin_script' );
