<?php
/**
 * Secure front-end story and chapter editing for KABITAB writers.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

class Kabitab_Writer_Workflow {
	const ROLE = 'kabitab_writer';

	/**
	 * Registers form handlers.
	 */
	public static function register() {
		add_action( 'admin_post_kabitab_save_story', array( __CLASS__, 'save_story' ) );
		add_action( 'admin_post_kabitab_save_chapter', array( __CLASS__, 'save_chapter' ) );
	}

	/**
	 * Creates and synchronizes the least-privilege Writer role.
	 */
	public static function ensure_writer_role() {
		$role = get_role( self::ROLE );
		if ( ! $role ) {
			add_role(
				self::ROLE,
				__( 'KABITAB Writer', 'kabitab-story-system' ),
				array(
					'read'                 => true,
					'upload_files'         => true,
					'edit_posts'           => true,
					'edit_published_posts' => true,
				)
			);
			$role = get_role( self::ROLE );
		}

		if ( ! $role ) {
			return;
		}

		$allowed_capabilities = array( 'read', 'upload_files', 'edit_posts', 'edit_published_posts' );
		foreach ( array_keys( $role->capabilities ) as $capability ) {
			if ( ! in_array( $capability, $allowed_capabilities, true ) ) {
				$role->remove_cap( $capability );
			}
		}
		foreach ( $allowed_capabilities as $capability ) {
			$role->add_cap( $capability );
		}
	}

	/**
	 * Returns a dashboard URL for a writer action.
	 *
	 * @param array $args Query arguments.
	 * @return string
	 */
	public static function dashboard_url( $args = array() ) {
		$url = home_url( '/dashboard/' );
		return empty( $args ) ? $url : add_query_arg( $args, $url );
	}

	/**
	 * Renders the requested front-end editor, or an empty string for the desk.
	 *
	 * @return string
	 */
	public static function render_requested_form() {
		$action = isset( $_GET['writer_action'] ) ? sanitize_key( wp_unslash( $_GET['writer_action'] ) ) : '';
		if ( ! $action ) {
			return '';
		}

		if ( 'new_story' === $action ) {
			return self::render_story_form();
		}
		if ( 'edit_story' === $action ) {
			return self::render_story_form( isset( $_GET['story_id'] ) ? absint( $_GET['story_id'] ) : 0 );
		}
		if ( 'new_chapter' === $action ) {
			return self::render_chapter_form( 0, isset( $_GET['story_id'] ) ? absint( $_GET['story_id'] ) : 0 );
		}
		if ( 'edit_chapter' === $action ) {
			return self::render_chapter_form( isset( $_GET['chapter_id'] ) ? absint( $_GET['chapter_id'] ) : 0 );
		}

		return self::form_notice( __( 'That writing action is not available.', 'kabitab-story-system' ) );
	}

	/**
	 * Saves a writer-owned story.
	 */
	public static function save_story() {
		self::require_writer();
		check_admin_referer( 'kabitab_save_frontend_story', 'kabitab_writer_nonce' );

		$story_id = isset( $_POST['story_id'] ) ? absint( wp_unslash( $_POST['story_id'] ) ) : 0;
		if ( $story_id && ! self::owns_post( $story_id, 'kabitab_story' ) ) {
			wp_die( esc_html__( 'You can edit only your own stories.', 'kabitab-story-system' ), '', array( 'response' => 403 ) );
		}

		$title  = isset( $_POST['story_title'] ) ? sanitize_text_field( wp_unslash( $_POST['story_title'] ) ) : '';
		$blurb  = isset( $_POST['story_blurb'] ) ? sanitize_textarea_field( wp_unslash( $_POST['story_blurb'] ) ) : '';
		$pen_name = isset( $_POST['story_public_author_name'] ) ? sanitize_text_field( wp_unslash( $_POST['story_public_author_name'] ) ) : '';
		$status = isset( $_POST['story_progress'] ) ? Kabitab_Post_Types::sanitize_story_status( wp_unslash( $_POST['story_progress'] ) ) : 'ongoing';
		$status = $status ? $status : 'ongoing';
		$submission_intent = isset( $_POST['submission_intent'] ) && 'submit' === sanitize_key( wp_unslash( $_POST['submission_intent'] ) ) ? 'submit' : 'draft';
		$can_publish       = current_user_can( 'publish_posts' );

		if ( '' === $title || '' === $blurb ) {
			self::redirect( 'missing_story_fields', $story_id ? array( 'writer_action' => 'edit_story', 'story_id' => $story_id ) : array( 'writer_action' => 'new_story' ) );
		}

		if ( $story_id && 'publish' === get_post_status( $story_id ) && ! current_user_can( 'publish_posts' ) ) {
			self::redirect( 'published_story_requires_editor', array( 'writer_action' => 'edit_story', 'story_id' => $story_id ) );
		}

		$post_status = $story_id ? get_post_status( $story_id ) : ( $can_publish ? 'publish' : ( 'submit' === $submission_intent ? 'pending' : 'draft' ) );
		if ( $story_id && ! $can_publish && in_array( $post_status, array( 'pending', 'draft' ), true ) ) {
			$post_status = 'submit' === $submission_intent ? 'pending' : 'draft';
		}
		if ( ! in_array( $post_status, array( 'publish', 'pending', 'draft' ), true ) ) {
			$post_status = $can_publish ? 'publish' : ( 'submit' === $submission_intent ? 'pending' : 'draft' );
		}
		$post_data = array(
			'post_type'   => 'kabitab_story',
			'post_title'  => $title,
			'post_excerpt'=> $blurb,
			'post_status' => $post_status,
		);
		if ( $story_id ) {
			$post_data['ID'] = $story_id;
		} else {
			$post_data['post_author'] = get_current_user_id();
		}

		$saved_id = wp_insert_post( wp_slash( $post_data ), true );
		if ( is_wp_error( $saved_id ) ) {
			self::redirect( 'story_save_failed' );
		}

		update_post_meta( $saved_id, '_kabitab_blurb', $blurb );
		update_post_meta( $saved_id, '_kabitab_status', $status );
		update_post_meta( $saved_id, '_kabitab_public_author_name', $pen_name );

		$genre_id = isset( $_POST['story_genre'] ) ? absint( wp_unslash( $_POST['story_genre'] ) ) : 0;
		wp_set_object_terms( $saved_id, $genre_id ? array( $genre_id ) : array(), 'story_genre' );

		if ( ! empty( $_POST['remove_cover'] ) ) {
			delete_post_thumbnail( $saved_id );
		}
		$image_result = self::handle_image_upload( 'story_cover', $saved_id );
		if ( is_wp_error( $image_result ) ) {
			self::redirect( 'image_failed', array( 'writer_action' => 'edit_story', 'story_id' => $saved_id ) );
		}
		$source_result = self::handle_document_upload( 'story_source_file', $saved_id );
		if ( is_wp_error( $source_result ) ) {
			self::redirect( 'source_file_failed', array( 'writer_action' => 'edit_story', 'story_id' => $saved_id ) );
		}
		if ( $source_result ) {
			update_post_meta( $saved_id, '_kabitab_story_source_file_id', $source_result );
		}

		if ( ! $can_publish ) {
			self::redirect( 'submit' === $submission_intent ? 'story_submitted' : 'story_draft_saved' );
		}

		self::redirect( $story_id ? 'story_updated' : 'story_created' );
	}

	/**
	 * Saves a writer-owned chapter beneath a writer-owned story.
	 */
	public static function save_chapter() {
		self::require_writer();
		check_admin_referer( 'kabitab_save_frontend_chapter', 'kabitab_writer_nonce' );

		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( wp_unslash( $_POST['chapter_id'] ) ) : 0;
		$story_id   = isset( $_POST['story_id'] ) ? absint( wp_unslash( $_POST['story_id'] ) ) : 0;
		if ( ! self::owns_post( $story_id, 'kabitab_story' ) || ( $chapter_id && ! self::owns_post( $chapter_id, 'kabitab_chapter' ) ) ) {
			wp_die( esc_html__( 'You can edit only chapters belonging to your own story.', 'kabitab-story-system' ), '', array( 'response' => 403 ) );
		}
		if ( $chapter_id && $story_id !== kabitab_get_chapter_parent_story_id( $chapter_id ) ) {
			wp_die( esc_html__( 'This chapter does not belong to the selected story.', 'kabitab-story-system' ), '', array( 'response' => 400 ) );
		}

		$title   = isset( $_POST['chapter_title'] ) ? sanitize_text_field( wp_unslash( $_POST['chapter_title'] ) ) : '';
		$content = isset( $_POST['chapter_content'] ) ? wp_kses_post( wp_unslash( $_POST['chapter_content'] ) ) : '';
		$number  = isset( $_POST['chapter_number'] ) ? kabitab_sanitize_positive_integer( wp_unslash( $_POST['chapter_number'] ) ) : 0;
		$submission_intent = isset( $_POST['submission_intent'] ) && 'submit' === sanitize_key( wp_unslash( $_POST['submission_intent'] ) ) ? 'submit' : 'draft';
		$can_publish       = current_user_can( 'publish_posts' );
		if ( '' === $title || '' === trim( wp_strip_all_tags( $content ) ) || ! $number ) {
			$args = $chapter_id ? array( 'writer_action' => 'edit_chapter', 'chapter_id' => $chapter_id ) : array( 'writer_action' => 'new_chapter', 'story_id' => $story_id );
			self::redirect( 'missing_chapter_fields', $args );
		}
		if ( kabitab_chapter_number_exists( $story_id, $number, $chapter_id ) ) {
			$args = $chapter_id ? array( 'writer_action' => 'edit_chapter', 'chapter_id' => $chapter_id ) : array( 'writer_action' => 'new_chapter', 'story_id' => $story_id );
			self::redirect( 'duplicate_chapter_number', $args );
		}
		if ( $chapter_id && 'publish' === get_post_status( $chapter_id ) && ! current_user_can( 'publish_posts' ) ) {
			self::redirect( 'published_chapter_requires_editor', array( 'writer_action' => 'edit_chapter', 'chapter_id' => $chapter_id ) );
		}

		$post_status = $chapter_id ? get_post_status( $chapter_id ) : ( $can_publish ? 'publish' : ( 'submit' === $submission_intent ? 'pending' : 'draft' ) );
		if ( $chapter_id && ! $can_publish && in_array( $post_status, array( 'pending', 'draft' ), true ) ) {
			$post_status = 'submit' === $submission_intent ? 'pending' : 'draft';
		}
		if ( ! in_array( $post_status, array( 'publish', 'pending', 'draft' ), true ) ) {
			$post_status = $can_publish ? 'publish' : ( 'submit' === $submission_intent ? 'pending' : 'draft' );
		}
		$post_data = array(
			'post_type'    => 'kabitab_chapter',
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => $post_status,
		);
		if ( $chapter_id ) {
			$post_data['ID'] = $chapter_id;
		} else {
			$post_data['post_author'] = get_current_user_id();
		}

		$saved_id = wp_insert_post( wp_slash( $post_data ), true );
		if ( is_wp_error( $saved_id ) ) {
			self::redirect( 'chapter_save_failed' );
		}
		update_post_meta( $saved_id, '_kabitab_parent_story', $story_id );
		update_post_meta( $saved_id, '_kabitab_chapter_number', $number );

		if ( ! empty( $_POST['remove_chapter_image'] ) ) {
			delete_post_thumbnail( $saved_id );
		}
		$image_result = self::handle_image_upload( 'chapter_image', $saved_id );
		if ( is_wp_error( $image_result ) ) {
			self::redirect( 'image_failed', array( 'writer_action' => 'edit_chapter', 'chapter_id' => $saved_id ) );
		}
		$source_result = self::handle_document_upload( 'chapter_source_file', $saved_id );
		if ( is_wp_error( $source_result ) ) {
			self::redirect( 'source_file_failed', array( 'writer_action' => 'edit_chapter', 'chapter_id' => $saved_id ) );
		}
		if ( $source_result ) {
			update_post_meta( $saved_id, '_kabitab_chapter_source_file_id', $source_result );
		}
		$existing_narrations = wp_parse_id_list( get_post_meta( $saved_id, '_kabitab_narration_ids', true ) );
		$remove_narrations   = isset( $_POST['remove_narration_ids'] ) ? wp_parse_id_list( wp_unslash( $_POST['remove_narration_ids'] ) ) : array();
		$remove_narrations   = array_values( array_intersect( $existing_narrations, $remove_narrations ) );
		if ( ! empty( $remove_narrations ) ) {
			$existing_narrations = array_values( array_diff( $existing_narrations, $remove_narrations ) );
			update_post_meta( $saved_id, '_kabitab_narration_ids', $existing_narrations );
		}
		$narration_result = self::handle_narration_uploads( 'chapter_narrations', $saved_id );
		if ( is_wp_error( $narration_result ) ) {
			self::redirect( 'narration_failed', array( 'writer_action' => 'edit_chapter', 'chapter_id' => $saved_id ) );
		}
		if ( ! empty( $narration_result ) ) {
			update_post_meta( $saved_id, '_kabitab_narration_ids', array_values( array_unique( array_merge( $existing_narrations, $narration_result ) ) ) );
		}

		if ( ! $can_publish ) {
			self::redirect( 'submit' === $submission_intent ? 'chapter_submitted' : 'chapter_draft_saved' );
		}

		self::redirect( $chapter_id ? 'chapter_updated' : 'chapter_created' );
	}

	private static function render_story_form( $story_id = 0 ) {
		$story = $story_id ? get_post( $story_id ) : null;
		if ( $story_id && ! self::owns_post( $story_id, 'kabitab_story' ) ) {
			return self::form_notice( __( 'You can edit only your own stories.', 'kabitab-story-system' ) );
		}

		$title    = $story ? $story->post_title : '';
		$blurb    = $story ? (string) get_post_meta( $story_id, '_kabitab_blurb', true ) : '';
		$progress = $story ? (string) get_post_meta( $story_id, '_kabitab_status', true ) : 'ongoing';
		$pen_name = $story ? (string) get_post_meta( $story_id, '_kabitab_public_author_name', true ) : '';
		$can_publish = current_user_can( 'publish_posts' );
		$editor_feedback = $story ? trim( (string) get_post_meta( $story_id, '_kabitab_editor_feedback', true ) ) : '';
		$terms    = get_the_terms( $story_id, 'story_genre' );
		$genre_id = $terms && ! is_wp_error( $terms ) ? (int) $terms[0]->term_id : 0;
		$genres   = get_terms( array( 'taxonomy' => 'story_genre', 'hide_empty' => false ) );
		ob_start();
		?>
		<section class="kabitab-writer-form" aria-labelledby="kabitab-story-form-title">
			<header><p><?php esc_html_e( 'Writer workspace', 'kabitab-story-system' ); ?></p><h2 id="kabitab-story-form-title"><?php echo esc_html( $story ? __( 'Edit your story', 'kabitab-story-system' ) : __( 'Create or upload a new story', 'kabitab-story-system' ) ); ?></h2><span><?php esc_html_e( 'Write the details here, then add chapters. You may also attach your original manuscript or source file for editorial reference.', 'kabitab-story-system' ); ?></span></header>
			<?php if ( '' !== $editor_feedback ) : ?><aside class="kabitab-writer-form__feedback" aria-label="<?php esc_attr_e( 'Editor feedback', 'kabitab-story-system' ); ?>"><strong><?php esc_html_e( 'Editor feedback', 'kabitab-story-system' ); ?></strong><p><?php echo nl2br( esc_html( $editor_feedback ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p></aside><?php endif; ?>
			<?php echo self::current_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="kabitab_save_story" />
				<input type="hidden" name="story_id" value="<?php echo esc_attr( $story_id ); ?>" />
				<?php wp_nonce_field( 'kabitab_save_frontend_story', 'kabitab_writer_nonce' ); ?>
				<label><span><?php esc_html_e( 'Story title', 'kabitab-story-system' ); ?> *</span><input type="text" name="story_title" value="<?php echo esc_attr( $title ); ?>" maxlength="140" required /></label>
				<label><span><?php esc_html_e( 'Short summary', 'kabitab-story-system' ); ?> *</span><textarea name="story_blurb" rows="5" maxlength="700" required><?php echo esc_textarea( $blurb ); ?></textarea><small><?php esc_html_e( 'Tell readers what the story is about without giving away the ending.', 'kabitab-story-system' ); ?></small></label>
				<div class="kabitab-writer-form__row">
					<label><span><?php esc_html_e( 'Genre', 'kabitab-story-system' ); ?></span><select name="story_genre"><option value=""><?php esc_html_e( 'Choose a genre', 'kabitab-story-system' ); ?></option><?php foreach ( $genres as $genre ) : ?><option value="<?php echo esc_attr( $genre->term_id ); ?>" <?php selected( $genre_id, $genre->term_id ); ?>><?php echo esc_html( $genre->name ); ?></option><?php endforeach; ?></select></label>
					<label><span><?php esc_html_e( 'Story progress', 'kabitab-story-system' ); ?></span><select name="story_progress"><?php foreach ( kabitab_get_story_statuses() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $progress, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				</div>
				<label><span><?php esc_html_e( 'Public author name', 'kabitab-story-system' ); ?></span><input type="text" name="story_public_author_name" value="<?php echo esc_attr( $pen_name ); ?>" maxlength="120" /><small><?php esc_html_e( 'This is the name readers see. Leave it blank to use your account display name.', 'kabitab-story-system' ); ?></small></label>
				<label class="kabitab-writer-form__upload"><span><?php esc_html_e( 'Cover image', 'kabitab-story-system' ); ?></span><?php if ( $story && has_post_thumbnail( $story_id ) ) : ?><?php echo get_the_post_thumbnail( $story_id, 'medium' ); ?><em><input type="checkbox" name="remove_cover" value="1" /> <?php esc_html_e( 'Remove current cover', 'kabitab-story-system' ); ?></em><?php endif; ?><input type="file" name="story_cover" accept="image/jpeg,image/png,image/webp,image/gif" /><small><?php esc_html_e( 'JPG, PNG, WebP, or GIF. A landscape image works best.', 'kabitab-story-system' ); ?></small></label>
				<label class="kabitab-writer-form__upload"><span><?php esc_html_e( 'Story source file', 'kabitab-story-system' ); ?></span><?php $source_file_id = $story ? absint( get_post_meta( $story_id, '_kabitab_story_source_file_id', true ) ) : 0; ?><?php if ( $source_file_id ) : ?><a href="<?php echo esc_url( wp_get_attachment_url( $source_file_id ) ); ?>"><?php esc_html_e( 'Download current source file', 'kabitab-story-system' ); ?></a><?php endif; ?><input type="file" name="story_source_file" accept=".txt,.pdf,.doc,.docx" /><small><?php esc_html_e( 'Optional: TXT, PDF, DOC, or DOCX. This stays attached to your story; add reader-facing text as chapters below.', 'kabitab-story-system' ); ?></small></label>
				<div class="kabitab-writer-form__actions">
					<?php if ( $can_publish ) : ?>
						<button type="submit"><?php echo esc_html( $story ? __( 'Update story', 'kabitab-story-system' ) : __( 'Create story', 'kabitab-story-system' ) ); ?></button>
					<?php else : ?>
						<button class="is-secondary" type="submit" name="submission_intent" value="draft"><?php esc_html_e( 'Save draft', 'kabitab-story-system' ); ?></button>
						<button type="submit" name="submission_intent" value="submit"><?php esc_html_e( 'Submit for review', 'kabitab-story-system' ); ?></button>
					<?php endif; ?>
					<a href="<?php echo esc_url( self::dashboard_url() ); ?>"><?php esc_html_e( 'Back to writing desk', 'kabitab-story-system' ); ?></a>
				</div>
			</form>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_chapter_form( $chapter_id = 0, $story_id = 0 ) {
		$chapter = $chapter_id ? get_post( $chapter_id ) : null;
		if ( $chapter ) {
			$story_id = kabitab_get_chapter_parent_story_id( $chapter_id );
		}
		if ( ! self::owns_post( $story_id, 'kabitab_story' ) || ( $chapter_id && ! self::owns_post( $chapter_id, 'kabitab_chapter' ) ) ) {
			return self::form_notice( __( 'You can add or edit chapters only inside your own story.', 'kabitab-story-system' ) );
		}

		$number = $chapter ? kabitab_sanitize_positive_integer( get_post_meta( $chapter_id, '_kabitab_chapter_number', true ) ) : self::next_chapter_number( $story_id );
		$can_publish = current_user_can( 'publish_posts' );
		$editor_feedback = $chapter ? trim( (string) get_post_meta( $chapter_id, '_kabitab_editor_feedback', true ) ) : '';
		ob_start();
		?>
		<section class="kabitab-writer-form" aria-labelledby="kabitab-chapter-form-title">
			<header><p><?php echo esc_html( get_the_title( $story_id ) ); ?></p><h2 id="kabitab-chapter-form-title"><?php echo esc_html( $chapter ? __( 'Edit chapter', 'kabitab-story-system' ) : __( 'Add a new chapter', 'kabitab-story-system' ) ); ?></h2><span><?php esc_html_e( 'Keep chapter titles clear and arrange them using the chapter number.', 'kabitab-story-system' ); ?></span></header>
			<?php if ( '' !== $editor_feedback ) : ?><aside class="kabitab-writer-form__feedback" aria-label="<?php esc_attr_e( 'Editor feedback', 'kabitab-story-system' ); ?>"><strong><?php esc_html_e( 'Editor feedback', 'kabitab-story-system' ); ?></strong><p><?php echo nl2br( esc_html( $editor_feedback ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p></aside><?php endif; ?>
			<?php echo self::current_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="kabitab_save_chapter" />
				<input type="hidden" name="chapter_id" value="<?php echo esc_attr( $chapter_id ); ?>" />
				<input type="hidden" name="story_id" value="<?php echo esc_attr( $story_id ); ?>" />
				<?php wp_nonce_field( 'kabitab_save_frontend_chapter', 'kabitab_writer_nonce' ); ?>
				<div class="kabitab-writer-form__row kabitab-writer-form__row--chapter"><label><span><?php esc_html_e( 'Chapter title', 'kabitab-story-system' ); ?> *</span><input type="text" name="chapter_title" value="<?php echo esc_attr( $chapter ? $chapter->post_title : '' ); ?>" maxlength="160" required /></label><label><span><?php esc_html_e( 'Chapter number', 'kabitab-story-system' ); ?> *</span><input type="number" name="chapter_number" value="<?php echo esc_attr( $number ); ?>" min="1" step="1" required /></label></div>
				<label><span><?php esc_html_e( 'Chapter text', 'kabitab-story-system' ); ?> *</span><textarea class="kabitab-writer-form__editor" name="chapter_content" rows="22" required><?php echo esc_textarea( $chapter ? $chapter->post_content : '' ); ?></textarea><small><?php esc_html_e( 'Paragraph breaks are preserved. You can write in Nepali or English.', 'kabitab-story-system' ); ?></small></label>
				<label class="kabitab-writer-form__upload"><span><?php esc_html_e( 'Chapter image', 'kabitab-story-system' ); ?></span><?php if ( $chapter && has_post_thumbnail( $chapter_id ) ) : ?><?php echo get_the_post_thumbnail( $chapter_id, 'medium' ); ?><em><input type="checkbox" name="remove_chapter_image" value="1" /> <?php esc_html_e( 'Remove current image', 'kabitab-story-system' ); ?></em><?php endif; ?><input type="file" name="chapter_image" accept="image/jpeg,image/png,image/webp,image/gif" /><small><?php esc_html_e( 'Optional. Use one meaningful image that supports this chapter.', 'kabitab-story-system' ); ?></small></label>
				<label class="kabitab-writer-form__upload"><span><?php esc_html_e( 'Chapter source file', 'kabitab-story-system' ); ?></span><?php $chapter_source_file_id = $chapter ? absint( get_post_meta( $chapter_id, '_kabitab_chapter_source_file_id', true ) ) : 0; ?><?php if ( $chapter_source_file_id ) : ?><a href="<?php echo esc_url( wp_get_attachment_url( $chapter_source_file_id ) ); ?>"><?php esc_html_e( 'Download current source file', 'kabitab-story-system' ); ?></a><?php endif; ?><input type="file" name="chapter_source_file" accept=".txt,.pdf,.doc,.docx" /><small><?php esc_html_e( 'Optional: TXT, PDF, DOC, or DOCX. Keep the reader-facing chapter text above.', 'kabitab-story-system' ); ?></small></label>
				<div class="kabitab-writer-form__upload">
					<span><?php esc_html_e( 'Narration tracks', 'kabitab-story-system' ); ?></span>
					<input type="file" name="chapter_narrations[]" accept="audio/mpeg,audio/mp4,audio/wav,audio/x-wav,audio/ogg,video/mp4,.mp3,.m4a,.wav,.ogg,.mp4" multiple />
					<small><?php esc_html_e( 'Optional: upload MP3, M4A, WAV, OGG, or MP4 narration. Upload only voices you have permission to publish.', 'kabitab-story-system' ); ?></small>
					<?php $narration_ids = $chapter ? wp_parse_id_list( get_post_meta( $chapter_id, '_kabitab_narration_ids', true ) ) : array(); ?>
					<?php if ( ! empty( $narration_ids ) ) : ?>
						<p class="kabitab-writer-form__file-list"><?php echo esc_html( sprintf( _n( '%d narration track attached', '%d narration tracks attached', count( $narration_ids ), 'kabitab-story-system' ), count( $narration_ids ) ) ); ?></p>
						<ul class="kabitab-writer-form__narration-list">
							<?php foreach ( $narration_ids as $narration_id ) : ?>
								<li><label><input type="checkbox" name="remove_narration_ids[]" value="<?php echo esc_attr( $narration_id ); ?>" /> <?php echo esc_html( sprintf( __( 'Remove %s', 'kabitab-story-system' ), get_the_title( $narration_id ) ) ); ?></label></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
				<div class="kabitab-writer-form__actions">
					<?php if ( $can_publish ) : ?>
						<button type="submit"><?php echo esc_html( $chapter ? __( 'Update chapter', 'kabitab-story-system' ) : __( 'Add chapter', 'kabitab-story-system' ) ); ?></button>
					<?php else : ?>
						<button class="is-secondary" type="submit" name="submission_intent" value="draft"><?php esc_html_e( 'Save draft', 'kabitab-story-system' ); ?></button>
						<button type="submit" name="submission_intent" value="submit"><?php esc_html_e( 'Submit for review', 'kabitab-story-system' ); ?></button>
					<?php endif; ?>
					<a href="<?php echo esc_url( self::dashboard_url() ); ?>"><?php esc_html_e( 'Back to writing desk', 'kabitab-story-system' ); ?></a>
				</div>
			</form>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private static function next_chapter_number( $story_id ) {
		$ids = get_posts( array( 'post_type' => 'kabitab_chapter', 'post_status' => array( 'publish', 'pending', 'draft' ), 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_kabitab_parent_story', 'meta_value' => $story_id, 'no_found_rows' => true ) );
		$max = 0;
		foreach ( $ids as $id ) {
			$max = max( $max, kabitab_sanitize_positive_integer( get_post_meta( $id, '_kabitab_chapter_number', true ) ) );
		}
		return $max + 1;
	}

	private static function owns_post( $post_id, $post_type ) {
		$post = get_post( $post_id );
		return $post && $post_type === $post->post_type && ( (int) $post->post_author === get_current_user_id() || current_user_can( 'edit_others_posts' ) );
	}

	private static function require_writer() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Your account does not have Writer access.', 'kabitab-story-system' ), '', array( 'response' => 403 ) );
		}
	}

	private static function handle_image_upload( $field, $post_id ) {
		if ( empty( $_FILES[ $field ]['name'] ) ) {
			return 0;
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'upload_permission' );
		}
		$allowed = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );
		$type    = isset( $_FILES[ $field ]['type'] ) ? sanitize_mime_type( wp_unslash( $_FILES[ $field ]['type'] ) ) : '';
		if ( ! in_array( $type, $allowed, true ) ) {
			return new WP_Error( 'invalid_image' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = media_handle_upload( $field, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		set_post_thumbnail( $post_id, $attachment_id );
		return $attachment_id;
	}

	/**
	 * Uploads an optional manuscript/source attachment without exposing it on the reader page.
	 *
	 * @param string $field Form field name.
	 * @param int    $post_id Parent post ID.
	 * @return int|WP_Error Attachment ID, zero, or an error.
	 */
	private static function handle_document_upload( $field, $post_id ) {
		if ( empty( $_FILES[ $field ]['name'] ) ) {
			return 0;
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'upload_permission' );
		}

		$extension = strtolower( pathinfo( sanitize_file_name( wp_unslash( $_FILES[ $field ]['name'] ) ), PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, array( 'txt', 'pdf', 'doc', 'docx' ), true ) ) {
			return new WP_Error( 'invalid_source_file' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		return media_handle_upload( $field, $post_id );
	}

	/**
	 * Uploads one or more reader-facing narration tracks for a chapter.
	 *
	 * @param string $field Form field name.
	 * @param int    $post_id Parent chapter ID.
	 * @return int[]|WP_Error Attachment IDs or an error.
	 */
	private static function handle_narration_uploads( $field, $post_id ) {
		if ( empty( $_FILES[ $field ]['name'] ) || ! is_array( $_FILES[ $field ]['name'] ) ) {
			return array();
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'upload_permission' );
		}

		$allowed_extensions = array( 'mp3', 'm4a', 'wav', 'ogg', 'mp4' );
		foreach ( $_FILES[ $field ]['name'] as $index => $file_name ) {
			$error = isset( $_FILES[ $field ]['error'][ $index ] ) ? absint( $_FILES[ $field ]['error'][ $index ] ) : UPLOAD_ERR_NO_FILE;
			if ( UPLOAD_ERR_NO_FILE === $error ) {
				continue;
			}
			if ( UPLOAD_ERR_OK !== $error || ! in_array( strtolower( pathinfo( sanitize_file_name( wp_unslash( $file_name ) ), PATHINFO_EXTENSION ) ), $allowed_extensions, true ) ) {
				return new WP_Error( 'invalid_narration_file' );
			}
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_ids = array();
		$original_files = $_FILES;
		foreach ( $_FILES[ $field ]['name'] as $index => $file_name ) {
			if ( empty( $file_name ) ) {
				continue;
			}
			$_FILES[ $field ] = array(
				'name'     => $file_name,
				'type'     => isset( $original_files[ $field ]['type'][ $index ] ) ? $original_files[ $field ]['type'][ $index ] : '',
				'tmp_name' => isset( $original_files[ $field ]['tmp_name'][ $index ] ) ? $original_files[ $field ]['tmp_name'][ $index ] : '',
				'error'    => isset( $original_files[ $field ]['error'][ $index ] ) ? $original_files[ $field ]['error'][ $index ] : UPLOAD_ERR_NO_FILE,
				'size'     => isset( $original_files[ $field ]['size'][ $index ] ) ? $original_files[ $field ]['size'][ $index ] : 0,
			);
			$attachment_id = media_handle_upload( $field, $post_id );
			if ( is_wp_error( $attachment_id ) ) {
				$_FILES = $original_files;
				return $attachment_id;
			}
			$attachment_ids[] = (int) $attachment_id;
		}
		$_FILES = $original_files;
		return $attachment_ids;
	}

	private static function redirect( $notice, $args = array() ) {
		$args['writer_notice'] = sanitize_key( $notice );
		wp_safe_redirect( self::dashboard_url( $args ) );
		exit;
	}

	public static function current_notice() {
		$notice = isset( $_GET['writer_notice'] ) ? sanitize_key( wp_unslash( $_GET['writer_notice'] ) ) : '';
		$messages = array(
			'missing_story_fields'   => __( 'Add both a title and a short summary.', 'kabitab-story-system' ),
			'missing_chapter_fields' => __( 'Add a title, chapter number, and chapter text.', 'kabitab-story-system' ),
			'duplicate_chapter_number' => __( 'That chapter number is already used in this story. Choose another number before saving.', 'kabitab-story-system' ),
			'published_story_requires_editor' => __( 'Published stories are protected while the review workflow is being finalized. Ask an editor to make this update.', 'kabitab-story-system' ),
			'published_chapter_requires_editor' => __( 'Published chapters are protected while the review workflow is being finalized. Ask an editor to make this update.', 'kabitab-story-system' ),
			'image_failed'           => __( 'The image could not be uploaded. Use a valid JPG, PNG, WebP, or GIF.', 'kabitab-story-system' ),
			'source_file_failed'     => __( 'The source file could not be uploaded. Use a TXT, PDF, DOC, or DOCX file.', 'kabitab-story-system' ),
			'narration_failed'       => __( 'The narration track could not be uploaded. Use MP3, M4A, WAV, OGG, or MP4.', 'kabitab-story-system' ),
			'story_save_failed'      => __( 'The story could not be saved. Please try again.', 'kabitab-story-system' ),
			'chapter_save_failed'    => __( 'The chapter could not be saved. Please try again.', 'kabitab-story-system' ),
			'story_created'          => __( 'Your story was created successfully.', 'kabitab-story-system' ),
			'story_updated'          => __( 'Your story was updated successfully.', 'kabitab-story-system' ),
			'chapter_created'        => __( 'Your chapter was added successfully.', 'kabitab-story-system' ),
			'chapter_updated'        => __( 'Your chapter was updated successfully.', 'kabitab-story-system' ),
			'story_draft_saved'      => __( 'Your story draft was saved. It is visible only to you and editors.', 'kabitab-story-system' ),
			'story_submitted'        => __( 'Your story was submitted for editorial review.', 'kabitab-story-system' ),
			'chapter_draft_saved'    => __( 'Your chapter draft was saved. It is visible only to you and editors.', 'kabitab-story-system' ),
			'chapter_submitted'      => __( 'Your chapter was submitted for editorial review.', 'kabitab-story-system' ),
		);
		if ( ! isset( $messages[ $notice ] ) ) {
			return '';
		}
		$success = in_array( $notice, array( 'story_created', 'story_updated', 'chapter_created', 'chapter_updated', 'story_draft_saved', 'story_submitted', 'chapter_draft_saved', 'chapter_submitted' ), true );
		return '<p class="kabitab-writer-form__notice ' . ( $success ? 'is-success' : 'is-error' ) . '" role="status">' . esc_html( $messages[ $notice ] ) . '</p>';
	}

	private static function form_notice( $message ) {
		return '<section class="kabitab-writer-form"><p class="kabitab-writer-form__notice is-error">' . esc_html( $message ) . '</p><a href="' . esc_url( self::dashboard_url() ) . '">' . esc_html__( 'Back to writing desk', 'kabitab-story-system' ) . '</a></section>';
	}
}
