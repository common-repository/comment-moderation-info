<?php
/*
Plugin Name: Comment Moderation Info
Plugin URI: https://jeanbaptisteaudras.com
Description: Display comments moderation info such as last modified date and the author of the edition.
Author: Jb Audras @ Whodunit
Version: 0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://paypal.me/audrasjb
Text Domain: comment-moderation-info
*/

/**
 * Registers a new comments column.
 */
function comodinfo_manage_edit_comments_columns( $columns ) {
	unset( $columns['date'] );
	$columns['comodinfo_date_columns'] = esc_html( 'Date', 'comment-moderation-info' );
	return $columns;
}
add_filter( 'manage_edit-comments_columns', 'comodinfo_manage_edit_comments_columns', 10, 1 );

/**
 * Gets the last update from comment revisions.
 */
function comodinfo_get_last_update_from_comment_revisions( $comment_revisions, $show_author = 1 ) {
	$last_comment_data = end( $comment_revisions );
	$modified_by_user = get_userdata( $last_comment_data['author_id'] );
	$modified_by = $modified_by_user->display_name;
	if ( 1 === $show_author ) {
		$modified = sprintf(
			/* translators: 1: Comment date, 2: Comment time, 3: Comment editor. */
			esc_html__( 'Edited on %1$s at %2$s, by %3$s', 'comment-moderation-info' ),
			mysql2date( get_option( 'date_format' ), $last_comment_data['modified_date'] ),
			mysql2date( get_option( 'time_format' ), $last_comment_data['modified_date'] ),
			$modified_by
		);
	} else {
		$modified = sprintf(
			/* translators: 1: Comment date, 2: Comment time, 3: Comment editor. */
			esc_html__( 'Edited on %1$s at %2$s', 'comment-moderation-info' ),
			mysql2date( get_option( 'date_format' ), $last_comment_data['modified_date'] ),
			mysql2date( get_option( 'time_format' ), $last_comment_data['modified_date'] )
		);
	}
	return esc_html( $modified );
}

/**
 * Filters the comments columns.
 */
function comodinfo_manage_comments_custom_column( $column, $comment_ID ) {
	if ( 'comodinfo_date_columns' !== $column ) {
		return;
	}

	// Recreate built-in column info first.
	$submitted = sprintf(
		/* translators: 1: Comment date, 2: Comment time. */
		esc_html__( '%1$s at %2$s', 'comment-moderation-info' ),
		/* translators: Comment date format. See https://www.php.net/manual/datetime.format.php */
		get_comment_date( get_option( 'date_format' ), $comment_ID ),
		/* translators: Comment time format. See https://www.php.net/manual/datetime.format.php */
		get_comment_date( get_option( 'time_format' ), $comment_ID )
	);
	echo '<div class="submitted-on">';
	if ( 'approved' === wp_get_comment_status( $comment_ID ) ) {
		printf(
			'<a href="%s">%s</a>',
			esc_url( get_comment_link( $comment_ID ) ),
			esc_html( $submitted )
		);
	} else {
		esc_html_e( $submitted );
	}
	echo '</div>';

	// CMDA: Display last modified date.
	$comment_revisions = get_comment_meta( $comment_ID, 'comodinfo_comment_revisions', true );

	if ( ! empty( $comment_revisions ) ) {
		$modified = comodinfo_get_last_update_from_comment_revisions( $comment_revisions );
		echo esc_html( $modified );
	}
}
add_action( 'manage_comments_custom_column', 'comodinfo_manage_comments_custom_column', 10, 2 );

/**
 * Adds Core functions.
 */
function comodinfo_after_update_comment_metadata( $comment_ID, $data ) {
	$date	  = current_time( 'mysql' );
	$date_gmt  = get_gmt_from_date( $date );
	$author_id = get_current_user_id();
	$content   = $data['comment_content'];

	$new_data = array(
		'modified_date'	 => $date,
		'modified_date_gmt' => $date_gmt,
		'author_id'		 => $author_id,
		'content'		   => $content,
	);

	// Get existing revisons for the comment.
	$comment_revisions = get_comment_meta( $comment_ID, 'comodinfo_comment_revisions' );
	
	// Push the new revision.
	$comment_revisions[] = $new_data;

	// Update Comment meta.
	update_comment_meta( $comment_ID, 'comodinfo_comment_revisions', $comment_revisions );
}
add_action( 'edit_comment', 'comodinfo_after_update_comment_metadata', 10, 2 );

/**
 * Adds Templating functions.
 */

/**
 * Filters comment_text to display the last modified date.
 */
function comodinfo_add_last_modified_date( $comment_text, $comment ) {

	$comment_ID = $comment->comment_ID;
	$comment_revisions = get_comment_meta( $comment_ID, 'comodinfo_comment_revisions', true );

	if ( ! empty( $comment_revisions ) ) {

		$option_position = get_site_option( 'comodinfo_setting_field_position' );
		$position        = ( isset( $option_position ) ) ? esc_html( $option_position ) : '';
		$option_author   = get_site_option( 'comodinfo_setting_field_author' );
		$show_author     = ( isset( $option_author ) ) ? esc_html( $option_author ) : '';

		if ( ! empty( $position ) ) {
			$modified = comodinfo_get_last_update_from_comment_revisions( $comment_revisions, $show_author );
			if ( 'before_comment' === $position ) {
				$comment_text = '<p class="cmda-last-modified">' . $modified . '</p>' . $comment_text;
			} elseif ( 'after_comment' === $position ) {
				$comment_text = $comment_text . '<p class="cmda-last-modified">' . $modified . '</p>';
			}
		}

	}
	return $comment_text;
}
add_filter( 'comment_text', 'comodinfo_add_last_modified_date', 10, 2 );


/**
 * Adds plugin settings.
 */
function comodinfo_add_settings_section() {
	add_settings_section(
		'comodinfo_setting_section',
		esc_html__( 'Last modified date settings', 'comment-moderation-info' ),
		'comodinfo_setting_section_callback',
		'discussion'
	);
	register_setting( 'discussion', 'comodinfo_setting_field_position' );
	add_settings_field(
		'comodinfo_setting_field_position',
		esc_html__( 'Position', 'comment-moderation-info' ),
		'comodinfo_setting_field_position_callback',
		'discussion',
		'comodinfo_setting_section'
	);
	register_setting( 'discussion', 'comodinfo_setting_field_author' );
	add_settings_field(
		'comodinfo_setting_field_author',
		esc_html__( 'Show editor', 'comment-moderation-info' ),
		'comodinfo_setting_field_author_callback',
		'discussion',
		'comodinfo_setting_section'
	);
}
add_filter( 'admin_init', 'comodinfo_add_settings_section' );

/**
 * Settings section display callbacks.
 */
function comodinfo_setting_section_callback( $args ) {
	/**/
}
function comodinfo_setting_field_position_callback( $args ) {
	$option_position = get_site_option( 'comodinfo_setting_field_position' );
	$position        = ( isset( $option_position ) ) ? esc_html( $option_position ) : 'none';
	?>
	<fieldset>
		<label for="comodinfo_setting_field_position">
			<?php esc_html_e( 'Show the last modified date…', 'comment-moderation-info' ); ?>
		</label><br />
		<select name="comodinfo_setting_field_position" id="comodinfo_setting_field_position">
			<option value="after_comment" <?php selected( $position, 'after_comment', true ); ?>>
				<?php esc_html_e( 'After the comment (default)', 'comment-moderation-info' ); ?>
			</option>
			<option value="before_comment" <?php selected( $position, 'before_comment', true ); ?>>
				<?php esc_html_e( 'Before the comment', 'comment-moderation-info' ); ?>
			</option>
			<option value="none" <?php selected( $position, 'none', true ); ?>>
				<?php esc_html_e( 'Do not show the last modified date on front-end', 'comment-moderation-info' ); ?>
			</option>
		</select>
	</fieldset>
	<?php
}

function comodinfo_setting_field_author_callback( $args ) {
	$option_author = get_site_option( 'comodinfo_setting_field_author' );
	$show_author   = ( isset( $option_author ) ) ? esc_html( $option_author ) : 1;
	?>
	<fieldset>
		<label for="comodinfo_setting_field_author">
			<?php esc_html_e( 'Wether to display the author of the edition', 'comment-moderation-info' ); ?>
		</label><br />
		<select name="comodinfo_setting_field_author" id="comodinfo_setting_field_author">
			<option value="1" <?php selected( $show_author, '1', true ); ?>>
				<?php esc_html_e( 'Yes, display the author username', 'comment-moderation-info' ); ?>
			</option>
			<option value="0" <?php selected( $show_author, '0', true ); ?>>
				<?php esc_html_e( 'No, do not display the author username', 'comment-moderation-info' ); ?>
			</option>
		</select>
	</fieldset>
	<?php
}
