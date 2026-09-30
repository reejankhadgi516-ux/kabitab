<?php
/**
 * Admin-only presentation assets.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

class Kabitab_Admin {
	public static function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( 'kabitab_story', 'kabitab_chapter' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'kabitab-story-system-admin', KABITAB_STORY_SYSTEM_URL . 'assets/css/admin.css', array(), KABITAB_STORY_SYSTEM_VERSION );
		wp_enqueue_script( 'kabitab-story-system-admin', KABITAB_STORY_SYSTEM_URL . 'assets/js/admin.js', array(), KABITAB_STORY_SYSTEM_VERSION, true );
	}

	/**
	 * Adds one editorial screen for the collections shown on the public site.
	 */
	public static function register_curation_page() {
		add_submenu_page(
			'edit.php?post_type=kabitab_story',
			__( 'Curation', 'kabitab-story-system' ),
			__( 'Curation', 'kabitab-story-system' ),
			'edit_others_posts',
			'kabitab-story-curation',
			array( __CLASS__, 'render_curation_page' )
		);
	}

	/**
	 * Adds the editorial queue for Story and Chapter submissions awaiting review.
	 */
	public static function register_review_page() {
		add_submenu_page(
			'edit.php?post_type=kabitab_story',
			__( 'Review submissions', 'kabitab-story-system' ),
			__( 'Review submissions', 'kabitab-story-system' ),
			'edit_others_posts',
			'kabitab-review-submissions',
			array( __CLASS__, 'render_review_page' )
		);
	}

	/**
	 * Approves a pending submission or returns it to the writer with feedback.
	 */
	public static function review_submission() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to review submissions.', 'kabitab-story-system' ), '', array( 'response' => 403 ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		check_admin_referer( 'kabitab_review_submission_' . $post_id, 'kabitab_review_nonce' );

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'kabitab_story', 'kabitab_chapter' ), true ) || 'pending' !== $post->post_status || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'That submission is no longer available for review.', 'kabitab-story-system' ), '', array( 'response' => 400 ) );
		}

		$decision = isset( $_POST['review_decision'] ) ? sanitize_key( wp_unslash( $_POST['review_decision'] ) ) : '';
		$feedback = isset( $_POST['editor_feedback'] ) ? sanitize_textarea_field( wp_unslash( $_POST['editor_feedback'] ) ) : '';
		$notice   = '';

		if ( 'approve' === $decision ) {
			if ( ! current_user_can( 'publish_posts' ) ) {
				wp_die( esc_html__( 'You do not have permission to publish this submission.', 'kabitab-story-system' ), '', array( 'response' => 403 ) );
			}
			$result = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ), true );
			if ( ! is_wp_error( $result ) ) {
				delete_post_meta( $post_id, '_kabitab_editor_feedback' );
				$notice = 'approved';
			}
		} elseif ( 'changes' === $decision ) {
			if ( '' === trim( $feedback ) ) {
				self::redirect_review_page( 'feedback_required' );
			}
			$result = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ), true );
			if ( ! is_wp_error( $result ) ) {
				update_post_meta( $post_id, '_kabitab_editor_feedback', $feedback );
				$notice = 'changes_requested';
			}
		}

		self::redirect_review_page( $notice ? $notice : 'update_failed' );
	}

	/**
	 * Renders the small editorial queue without replacing WordPress post editing.
	 */
	public static function render_review_page() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}

		$submissions = get_posts(
			array(
				'post_type'      => array( 'kabitab_story', 'kabitab_chapter' ),
				'post_status'    => 'pending',
				'posts_per_page' => -1,
				'orderby'        => 'modified',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$notice = isset( $_GET['kabitab_review_notice'] ) ? sanitize_key( wp_unslash( $_GET['kabitab_review_notice'] ) ) : '';
		$messages = array(
			'approved'          => __( 'Submission published.', 'kabitab-story-system' ),
			'changes_requested' => __( 'Submission returned to the writer with feedback.', 'kabitab-story-system' ),
			'feedback_required' => __( 'Add feedback before returning a submission to draft.', 'kabitab-story-system' ),
			'update_failed'     => __( 'The submission could not be updated. Please try again.', 'kabitab-story-system' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Review submissions', 'kabitab-story-system' ); ?></h1>
			<p><?php esc_html_e( 'Approve ready Stories and Chapters, or return them to the Writer as a draft with clear feedback.', 'kabitab-story-system' ); ?></p>
			<?php if ( isset( $messages[ $notice ] ) ) : ?>
				<div class="notice <?php echo esc_attr( in_array( $notice, array( 'approved', 'changes_requested' ), true ) ? 'notice-success' : 'notice-error' ); ?> is-dismissible"><p><?php echo esc_html( $messages[ $notice ] ); ?></p></div>
			<?php endif; ?>
			<?php if ( empty( $submissions ) ) : ?>
				<p><?php esc_html_e( 'There are no Stories or Chapters waiting for review.', 'kabitab-story-system' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th scope="col"><?php esc_html_e( 'Submission', 'kabitab-story-system' ); ?></th><th scope="col"><?php esc_html_e( 'Writer', 'kabitab-story-system' ); ?></th><th scope="col"><?php esc_html_e( 'Submitted', 'kabitab-story-system' ); ?></th><th scope="col"><?php esc_html_e( 'Review', 'kabitab-story-system' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $submissions as $submission ) : ?>
							<?php
							$story_id = 'kabitab_chapter' === $submission->post_type ? kabitab_get_chapter_parent_story_id( $submission->ID ) : $submission->ID;
							$author   = Kabitab_Frontend::get_author_label( $story_id ? $story_id : $submission->ID );
							?>
							<tr>
								<td><strong><a href="<?php echo esc_url( get_edit_post_link( $submission->ID ) ); ?>"><?php echo esc_html( $submission->post_title ); ?></a></strong><br /><span class="description"><?php echo esc_html( 'kabitab_story' === $submission->post_type ? __( 'Story', 'kabitab-story-system' ) : sprintf( __( 'Chapter in %s', 'kabitab-story-system' ), get_the_title( $story_id ) ) ); ?></span></td>
								<td><?php echo esc_html( $author ); ?></td>
								<td><?php echo esc_html( get_the_modified_date( '', $submission ) ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="kabitab_review_submission" />
										<input type="hidden" name="post_id" value="<?php echo esc_attr( $submission->ID ); ?>" />
										<?php wp_nonce_field( 'kabitab_review_submission_' . $submission->ID, 'kabitab_review_nonce' ); ?>
										<p><label for="kabitab-editor-feedback-<?php echo esc_attr( $submission->ID ); ?>" class="screen-reader-text"><?php esc_html_e( 'Feedback for the writer', 'kabitab-story-system' ); ?></label><textarea id="kabitab-editor-feedback-<?php echo esc_attr( $submission->ID ); ?>" name="editor_feedback" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Feedback is required when requesting changes.', 'kabitab-story-system' ); ?>"></textarea></p>
										<button type="submit" class="button button-primary" name="review_decision" value="approve"><?php esc_html_e( 'Approve and publish', 'kabitab-story-system' ); ?></button>
										<button type="submit" class="button" name="review_decision" value="changes"><?php esc_html_e( 'Request changes', 'kabitab-story-system' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function redirect_review_page( $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'              => 'kabitab_story',
					'page'                   => 'kabitab-review-submissions',
					'kabitab_review_notice' => sanitize_key( $notice ),
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Persists editorial collection choices from the Curation screen.
	 */
	public static function save_curation() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to curate stories.', 'kabitab-story-system' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'kabitab_save_story_curation', 'kabitab_curation_nonce' );

		$story_ids = get_posts(
			array(
				'post_type'      => 'kabitab_story',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$editor_picks = isset( $_POST['kabitab_editor_picks'] ) ? wp_parse_id_list( wp_unslash( $_POST['kabitab_editor_picks'] ) ) : array();
		$trending     = isset( $_POST['kabitab_trending_stories'] ) ? wp_parse_id_list( wp_unslash( $_POST['kabitab_trending_stories'] ) ) : array();

		foreach ( $story_ids as $story_id ) {
			if ( ! current_user_can( 'edit_post', $story_id ) ) {
				continue;
			}
			update_post_meta( $story_id, '_kabitab_is_editor_pick', in_array( $story_id, $editor_picks, true ) ? 1 : 0 );
			update_post_meta( $story_id, '_kabitab_is_manual_trending', in_array( $story_id, $trending, true ) ? 1 : 0 );
		}
		delete_transient( Kabitab_Story_Engagement::TRENDING_CACHE_KEY );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'                => 'kabitab_story',
					'page'                     => 'kabitab-story-curation',
					'kabitab_curation_updated' => 1,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Renders a single, low-friction editorial collection page.
	 */
	public static function render_curation_page() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		$stories = get_posts(
			array(
				'post_type'      => 'kabitab_story',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Story Curation', 'kabitab-story-system' ); ?></h1>
			<p><?php esc_html_e( 'Choose the stories you want readers to see in Editor’s Pick and at the top of Trending. Any remaining Trending spaces are filled automatically from reader activity.', 'kabitab-story-system' ); ?></p>
			<?php if ( isset( $_GET['kabitab_curation_updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Curation choices saved.', 'kabitab-story-system' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="kabitab_save_story_curation" />
				<?php wp_nonce_field( 'kabitab_save_story_curation', 'kabitab_curation_nonce' ); ?>
				<table class="widefat striped">
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'Story', 'kabitab-story-system' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Author shown to readers', 'kabitab-story-system' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Editor’s Pick', 'kabitab-story-system' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Featured in Trending', 'kabitab-story-system' ); ?></th>
					</tr></thead>
					<tbody>
						<?php foreach ( $stories as $story ) : ?>
							<?php $author_name = get_post_meta( $story->ID, '_kabitab_public_author_name', true ); ?>
							<tr>
								<td><strong><a href="<?php echo esc_url( get_edit_post_link( $story->ID ) ); ?>"><?php echo esc_html( $story->post_title ); ?></a></strong></td>
								<td><?php echo esc_html( $author_name ? $author_name : get_the_author_meta( 'display_name', $story->post_author ) ); ?></td>
								<td><label class="screen-reader-text" for="kabitab-editor-pick-<?php echo esc_attr( $story->ID ); ?>"><?php echo esc_html( sprintf( __( 'Add %s to Editor’s Pick', 'kabitab-story-system' ), $story->post_title ) ); ?></label><input id="kabitab-editor-pick-<?php echo esc_attr( $story->ID ); ?>" type="checkbox" name="kabitab_editor_picks[]" value="<?php echo esc_attr( $story->ID ); ?>" <?php checked( 1, (int) get_post_meta( $story->ID, '_kabitab_is_editor_pick', true ) ); ?> /></td>
								<td><label class="screen-reader-text" for="kabitab-trending-<?php echo esc_attr( $story->ID ); ?>"><?php echo esc_html( sprintf( __( 'Feature %s in Trending', 'kabitab-story-system' ), $story->post_title ) ); ?></label><input id="kabitab-trending-<?php echo esc_attr( $story->ID ); ?>" type="checkbox" name="kabitab_trending_stories[]" value="<?php echo esc_attr( $story->ID ); ?>" <?php checked( 1, (int) get_post_meta( $story->ID, '_kabitab_is_manual_trending', true ) ); ?> /></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button( __( 'Save Curation', 'kabitab-story-system' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function add_story_columns( $columns ) {
		$added   = false;
		$new     = array();
		$custom  = array(
			'kabitab_status'           => __( 'Status', 'kabitab-story-system' ),
			'kabitab_genre'            => __( 'Genre', 'kabitab-story-system' ),
			'kabitab_published_chapters' => __( 'Published Chapters', 'kabitab-story-system' ),
			'kabitab_reads'            => __( 'Reads', 'kabitab-story-system' ),
			'kabitab_likes'            => __( 'Likes', 'kabitab-story-system' ),
		);
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new   = array_merge( $new, $custom );
				$added = true;
			}
		}
		return $added ? $new : array_merge( $columns, $custom );
	}

	public static function render_story_column( $column, $post_id ) {
		$value = '—';
		if ( 'kabitab_status' === $column ) {
			$status   = get_post_meta( $post_id, '_kabitab_status', true );
			$statuses = kabitab_get_story_statuses();
			$value    = isset( $statuses[ $status ] ) ? $statuses[ $status ] : $value;
		} elseif ( 'kabitab_genre' === $column ) {
			$terms = get_the_terms( $post_id, 'story_genre' );
			if ( is_array( $terms ) && ! empty( $terms ) ) {
				$value = implode( ', ', wp_list_pluck( $terms, 'name' ) );
			}
		} elseif ( 'kabitab_published_chapters' === $column ) {
			$value = (string) kabitab_get_published_chapter_count( $post_id );
		} elseif ( in_array( $column, array( 'kabitab_reads', 'kabitab_likes' ), true ) ) {
			$key = 'kabitab_reads' === $column ? '_kabitab_reads_count' : '_kabitab_likes_count';
			if ( metadata_exists( 'post', $post_id, $key ) ) {
				$value = (string) kabitab_sanitize_nonnegative_integer( get_post_meta( $post_id, $key, true ) );
			}
		}
		echo esc_html( $value );
	}

	public static function add_chapter_columns( $columns ) {
		$custom = array(
			'kabitab_parent_story'   => __( 'Parent Story', 'kabitab-story-system' ),
			'kabitab_chapter_number' => __( 'Chapter Number', 'kabitab-story-system' ),
			'kabitab_reading_time'   => __( 'Reading Time', 'kabitab-story-system' ),
		);
		return array_merge( $columns, $custom );
	}

	public static function render_chapter_column( $column, $post_id ) {
		$value = '—';
		if ( 'kabitab_parent_story' === $column ) {
			$story_id = kabitab_get_chapter_parent_story_id( $post_id );
			if ( $story_id ) {
				$title = get_the_title( $story_id );
				$value = '' !== $title ? $title : $value;
			}
		} elseif ( 'kabitab_chapter_number' === $column ) {
			$number = kabitab_sanitize_positive_integer( get_post_meta( $post_id, '_kabitab_chapter_number', true ) );
			if ( $number ) {
				$value = (string) $number;
			}
		} elseif ( 'kabitab_reading_time' === $column ) {
			$reading_time = kabitab_get_chapter_reading_time( $post_id );
			$value        = sprintf( _n( '%d min', '%d min', $reading_time, 'kabitab-story-system' ), $reading_time );
		}
		echo esc_html( $value );
	}

	public static function add_chapter_sortable_columns( $columns ) {
		$columns['kabitab_chapter_number'] = 'kabitab_chapter_number';
		return $columns;
	}

	public static function sort_chapter_number_column( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'kabitab_chapter' !== $query->get( 'post_type' ) || 'kabitab_chapter_number' !== $query->get( 'orderby' ) ) {
			return;
		}
		$query->set( 'meta_key', '_kabitab_chapter_number' );
		$query->set( 'orderby', 'meta_value_num' );
	}
}
