<?php
/**
 * Native metadata editor interface and secure persistence.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

class Kabitab_Meta_Boxes {
	private static $chapter_save_error = '';
	public static function register() {
		add_meta_box( 'kabitab_story_details', __( 'Story Details', 'kabitab-story-system' ), array( __CLASS__, 'render_story' ), 'kabitab_story', 'normal', 'high' );
		add_meta_box( 'kabitab_chapter_details', __( 'Chapter Details', 'kabitab-story-system' ), array( __CLASS__, 'render_chapter' ), 'kabitab_chapter', 'normal', 'high' );
	}

	public static function render_story( $post ) {
		wp_nonce_field( 'kabitab_save_story_details', 'kabitab_story_details_nonce' );
		$blurb       = get_post_meta( $post->ID, '_kabitab_blurb', true );
		$status      = get_post_meta( $post->ID, '_kabitab_status', true );
		$pen_name    = get_post_meta( $post->ID, '_kabitab_public_author_name', true );
		$editor_pick = (int) get_post_meta( $post->ID, '_kabitab_is_editor_pick', true );
		$manual_trending = (int) get_post_meta( $post->ID, '_kabitab_is_manual_trending', true );
		$reads       = absint( get_post_meta( $post->ID, '_kabitab_reads_count', true ) );
		$likes       = absint( get_post_meta( $post->ID, '_kabitab_likes_count', true ) );
		$statuses    = kabitab_get_story_statuses();
		?>
		<p class="kabitab-featured-image-note"><?php esc_html_e( 'Use the Featured Image as the story cover.', 'kabitab-story-system' ); ?></p>
		<p><label for="kabitab_blurb"><strong><?php esc_html_e( 'Blurb', 'kabitab-story-system' ); ?></strong></label><br />
		<textarea class="widefat" rows="4" id="kabitab_blurb" name="kabitab_blurb"><?php echo esc_textarea( $blurb ); ?></textarea></p>
		<p><label for="kabitab_status"><strong><?php esc_html_e( 'Status', 'kabitab-story-system' ); ?></strong></label><br />
		<select id="kabitab_status" name="kabitab_status">
			<?php foreach ( $statuses as $status_key => $status_label ) : ?>
				<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $status, $status_key ); ?>><?php echo esc_html( $status_label ); ?></option>
			<?php endforeach; ?>
		</select></p>
		<p><label for="kabitab_public_author_name"><strong><?php esc_html_e( 'Public pen name', 'kabitab-story-system' ); ?></strong></label><br />
		<input class="regular-text" type="text" id="kabitab_public_author_name" name="kabitab_public_author_name" value="<?php echo esc_attr( $pen_name ); ?>" />
		<span class="description"><?php esc_html_e( 'Shown to readers instead of the WordPress account name. Leave blank to use the account display name.', 'kabitab-story-system' ); ?></span></p>
		<p><label><input type="checkbox" name="kabitab_is_editor_pick" value="1" <?php checked( 1, $editor_pick ); ?> /> <?php esc_html_e( 'Editor’s Pick', 'kabitab-story-system' ); ?></label></p>
		<p><label><input type="checkbox" name="kabitab_is_manual_trending" value="1" <?php checked( 1, $manual_trending ); ?> /> <?php esc_html_e( 'Feature in Trending', 'kabitab-story-system' ); ?></label></p>
		<p><label for="kabitab_reads_count"><strong><?php esc_html_e( 'Editorial read baseline', 'kabitab-story-system' ); ?></strong></label><br />
		<span class="description"><?php esc_html_e( 'Optional number carried over from earlier reporting. New reads are tracked automatically.', 'kabitab-story-system' ); ?></span><br />
		<input type="number" min="0" step="1" id="kabitab_reads_count" name="kabitab_reads_count" value="<?php echo esc_attr( $reads ); ?>" /></p>
		<p><label for="kabitab_likes_count"><strong><?php esc_html_e( 'Editorial like baseline', 'kabitab-story-system' ); ?></strong></label><br />
		<span class="description"><?php esc_html_e( 'Optional number carried over from earlier reporting. Reader likes are tracked automatically.', 'kabitab-story-system' ); ?></span><br />
		<input type="number" min="0" step="1" id="kabitab_likes_count" name="kabitab_likes_count" value="<?php echo esc_attr( $likes ); ?>" /></p>
		<?php
	}

	public static function render_chapter( $post ) {
		wp_nonce_field( 'kabitab_save_chapter_details', 'kabitab_chapter_details_nonce' );
		$parent_story          = absint( get_post_meta( $post->ID, '_kabitab_parent_story', true ) );
		if ( ! $parent_story && isset( $_GET['kabitab_parent_story'] ) ) {
			$parent_story = kabitab_get_valid_story_id( wp_unslash( $_GET['kabitab_parent_story'] ) );
		}
		$chapter_number        = absint( get_post_meta( $post->ID, '_kabitab_chapter_number', true ) );
		$reading_time_override = absint( get_post_meta( $post->ID, '_kabitab_reading_time_override', true ) );
		$stories               = get_posts( array( 'post_type' => 'kabitab_story', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) );
		?>
		<p class="kabitab-featured-image-note"><?php esc_html_e( 'Use the Featured Image as the chapter image.', 'kabitab-story-system' ); ?></p>
		<p><label for="kabitab_parent_story"><strong><?php esc_html_e( 'Parent Story', 'kabitab-story-system' ); ?></strong></label><br />
		<select class="widefat" id="kabitab_parent_story" name="kabitab_parent_story">
			<option value="0"><?php esc_html_e( 'Select a story', 'kabitab-story-system' ); ?></option>
			<?php foreach ( $stories as $story ) : ?>
				<option value="<?php echo esc_attr( $story->ID ); ?>" <?php selected( $parent_story, $story->ID ); ?>><?php echo esc_html( $story->post_title ); ?></option>
			<?php endforeach; ?>
		</select></p>
		<p><label for="kabitab_chapter_number"><strong><?php esc_html_e( 'Chapter Number', 'kabitab-story-system' ); ?></strong></label><br />
		<input type="number" min="1" step="1" id="kabitab_chapter_number" name="kabitab_chapter_number" value="<?php echo esc_attr( $chapter_number ); ?>" /></p>
		<p><label for="kabitab_reading_time_override"><strong><?php esc_html_e( 'Reading Time Override (minutes)', 'kabitab-story-system' ); ?></strong></label><br />
		<input type="number" min="0" step="1" id="kabitab_reading_time_override" name="kabitab_reading_time_override" value="<?php echo esc_attr( $reading_time_override ); ?>" /></p>
		<?php
	}

	public static function save_story( $post_id, $post ) {
		if ( ! kabitab_can_save_post_meta( $post_id, $post, 'kabitab_story_details_nonce', 'kabitab_save_story_details' ) ) {
			return;
		}
		if ( kabitab_request_has_field( 'kabitab_blurb' ) ) {
			update_post_meta( $post_id, '_kabitab_blurb', sanitize_textarea_field( kabitab_get_request_field( 'kabitab_blurb' ) ) );
		}
		if ( kabitab_request_has_field( 'kabitab_status' ) ) {
			$status = Kabitab_Post_Types::sanitize_story_status( kabitab_get_request_field( 'kabitab_status' ) );
			if ( '' !== $status ) {
				update_post_meta( $post_id, '_kabitab_status', $status );
			}
		}
		if ( kabitab_request_has_field( 'kabitab_public_author_name' ) ) {
			update_post_meta( $post_id, '_kabitab_public_author_name', sanitize_text_field( kabitab_get_request_field( 'kabitab_public_author_name' ) ) );
		}
		update_post_meta( $post_id, '_kabitab_is_editor_pick', kabitab_request_has_field( 'kabitab_is_editor_pick' ) ? 1 : 0 );
		update_post_meta( $post_id, '_kabitab_is_manual_trending', kabitab_request_has_field( 'kabitab_is_manual_trending' ) ? 1 : 0 );
		if ( kabitab_request_has_field( 'kabitab_reads_count' ) ) {
			update_post_meta( $post_id, '_kabitab_reads_count', kabitab_sanitize_nonnegative_integer( kabitab_get_request_field( 'kabitab_reads_count' ) ) );
		}
		if ( kabitab_request_has_field( 'kabitab_likes_count' ) ) {
			update_post_meta( $post_id, '_kabitab_likes_count', kabitab_sanitize_nonnegative_integer( kabitab_get_request_field( 'kabitab_likes_count' ) ) );
		}
	}

	public static function save_chapter( $post_id, $post ) {
		if ( ! kabitab_can_save_post_meta( $post_id, $post, 'kabitab_chapter_details_nonce', 'kabitab_save_chapter_details' ) ) {
			return;
		}
		$parent_story = kabitab_request_has_field( 'kabitab_parent_story' ) ? kabitab_get_valid_story_id( kabitab_get_request_field( 'kabitab_parent_story' ) ) : kabitab_get_chapter_parent_story_id( $post_id );
		$chapter_number = kabitab_request_has_field( 'kabitab_chapter_number' ) ? kabitab_sanitize_positive_integer( kabitab_get_request_field( 'kabitab_chapter_number' ) ) : kabitab_sanitize_positive_integer( get_post_meta( $post_id, '_kabitab_chapter_number', true ) );

		if ( ! $parent_story || ! $chapter_number ) {
			self::set_chapter_save_error( 'invalid' );
		} elseif ( kabitab_chapter_number_exists( $parent_story, $chapter_number, $post_id ) ) {
			self::set_chapter_save_error( 'duplicate' );
		} else {
			update_post_meta( $post_id, '_kabitab_parent_story', $parent_story );
			update_post_meta( $post_id, '_kabitab_chapter_number', $chapter_number );
		}
		if ( kabitab_request_has_field( 'kabitab_reading_time_override' ) ) {
			update_post_meta( $post_id, '_kabitab_reading_time_override', kabitab_sanitize_nonnegative_integer( kabitab_get_request_field( 'kabitab_reading_time_override' ) ) );
		}
	}

	private static function set_chapter_save_error( $error ) {
		self::$chapter_save_error = $error;
		add_filter( 'redirect_post_location', array( __CLASS__, 'append_chapter_save_error' ) );
	}

	public static function append_chapter_save_error( $location ) {
		if ( '' === self::$chapter_save_error ) {
			return $location;
		}

		return add_query_arg( 'kabitab_chapter_error', self::$chapter_save_error, $location );
	}

	public static function render_chapter_save_error() {
		if ( ! isset( $_GET['kabitab_chapter_error'] ) ) {
			return;
		}
		$error = sanitize_key( wp_unslash( $_GET['kabitab_chapter_error'] ) );
		if ( 'duplicate' === $error ) {
			$message = __( 'Chapter number was not saved because this Story already has a Chapter with that number. The title and editor content were preserved.', 'kabitab-story-system' );
		} elseif ( 'invalid' === $error ) {
			$message = __( 'Chapter details were not saved. Select a valid Parent Story and enter a positive Chapter Number.', 'kabitab-story-system' );
		} else {
			return;
		}
		printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $message ) );
	}
}
