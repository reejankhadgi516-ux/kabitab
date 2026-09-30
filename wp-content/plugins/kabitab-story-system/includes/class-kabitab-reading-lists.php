<?php
/**
 * Private reader-managed Story collections.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

class Kabitab_Reading_Lists {
	const META_KEY = '_kabitab_reading_lists';

	public static function register() {
		add_action( 'admin_post_kabitab_manage_reading_list', array( __CLASS__, 'handle_submission' ) );
	}

	/**
	 * Renders an add-to-list form on a public Story.
	 *
	 * @param int $story_id Story post ID.
	 * @return string
	 */
	public static function render_story_control( $story_id ) {
		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<p class="kabitab-reading-list-cta"><a href="%1$s">%2$s</a></p>',
				esc_url( wp_login_url( get_permalink( $story_id ) ) ),
				esc_html__( 'Sign in to add this story to a reading list', 'kabitab-story-system' )
			);
		}

		$lists = self::get_lists();
		if ( empty( $lists ) ) {
			return sprintf(
				'<p class="kabitab-reading-list-cta"><a href="%1$s">%2$s</a></p>',
				esc_url( home_url( '/account/#kabitab-reading-lists' ) ),
				esc_html__( 'Create a reading list to organise this story', 'kabitab-story-system' )
			);
		}

		ob_start();
		?>
		<form class="kabitab-reading-list-add" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="kabitab_manage_reading_list" />
			<input type="hidden" name="kabitab_reading_list_action" value="add_story" />
			<input type="hidden" name="story_id" value="<?php echo esc_attr( $story_id ); ?>" />
			<input type="hidden" name="return_url" value="<?php echo esc_url( get_permalink( $story_id ) ); ?>" />
			<?php wp_nonce_field( 'kabitab_manage_reading_lists', 'kabitab_reading_lists_nonce' ); ?>
			<label><span><?php esc_html_e( 'Add to list', 'kabitab-story-system' ); ?></span><select name="list_id"><?php foreach ( $lists as $list ) : ?><option value="<?php echo esc_attr( $list['id'] ); ?>"><?php echo esc_html( $list['name'] ); ?></option><?php endforeach; ?></select></label>
			<button type="submit"><?php esc_html_e( 'Add', 'kabitab-story-system' ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders the signed-in reader's private list manager.
	 *
	 * @return string
	 */
	public static function render_library() {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$lists = self::get_lists();
		ob_start();
		?>
		<section id="kabitab-reading-lists" class="kabitab-reading-lists" aria-labelledby="kabitab-reading-lists-title">
			<h3 id="kabitab-reading-lists-title"><?php esc_html_e( 'Reading lists', 'kabitab-story-system' ); ?></h3>
			<p><?php esc_html_e( 'Keep private collections for the stories you want to return to.', 'kabitab-story-system' ); ?></p>
			<?php self::render_notice(); ?>
			<form class="kabitab-reading-lists__create" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="kabitab_manage_reading_list" />
				<input type="hidden" name="kabitab_reading_list_action" value="create" />
				<input type="hidden" name="return_url" value="<?php echo esc_url( self::library_url() ); ?>" />
				<?php wp_nonce_field( 'kabitab_manage_reading_lists', 'kabitab_reading_lists_nonce' ); ?>
				<label><span class="screen-reader-text"><?php esc_html_e( 'New list name', 'kabitab-story-system' ); ?></span><input type="text" name="list_name" maxlength="80" required placeholder="<?php esc_attr_e( 'New list name', 'kabitab-story-system' ); ?>" /></label>
				<button type="submit"><?php esc_html_e( 'Create list', 'kabitab-story-system' ); ?></button>
			</form>
			<?php if ( empty( $lists ) ) : ?>
				<p class="kabitab-reading-lists__empty"><?php esc_html_e( 'No reading lists yet. Create one, then add stories from their Story pages.', 'kabitab-story-system' ); ?></p>
			<?php else : ?>
				<div class="kabitab-reading-lists__grid">
					<?php foreach ( $lists as $list ) : ?>
						<article>
							<div class="kabitab-reading-lists__heading"><h4><?php echo esc_html( $list['name'] ); ?></h4><span><?php echo esc_html( sprintf( _n( '%d story', '%d stories', count( $list['story_ids'] ), 'kabitab-story-system' ), count( $list['story_ids'] ) ) ); ?></span></div>
							<?php if ( empty( $list['story_ids'] ) ) : ?>
								<p><?php esc_html_e( 'This list is ready for stories.', 'kabitab-story-system' ); ?></p>
							<?php else : ?>
								<ul>
									<?php foreach ( $list['story_ids'] as $story_id ) : ?>
										<?php if ( 'kabitab_story' !== get_post_type( $story_id ) || 'publish' !== get_post_status( $story_id ) ) { continue; } ?>
										<li><a href="<?php echo esc_url( get_permalink( $story_id ) ); ?>"><?php echo esc_html( get_the_title( $story_id ) ); ?></a><?php self::render_action_form( 'remove_story', $list['id'], $story_id, __( 'Remove', 'kabitab-story-system' ) ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
							<?php self::render_action_form( 'delete_list', $list['id'], 0, __( 'Delete list', 'kabitab-story-system' ) ); ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function handle_submission() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( 'kabitab_manage_reading_lists', 'kabitab_reading_lists_nonce' );

		$action = isset( $_POST['kabitab_reading_list_action'] ) ? sanitize_key( wp_unslash( $_POST['kabitab_reading_list_action'] ) ) : '';
		$lists  = self::get_lists();
		$notice = 'list_error';

		if ( 'create' === $action ) {
			$name = isset( $_POST['list_name'] ) ? sanitize_text_field( wp_unslash( $_POST['list_name'] ) ) : '';
			if ( '' !== $name && count( $lists ) < 20 ) {
				$lists[] = array( 'id' => wp_generate_uuid4(), 'name' => $name, 'story_ids' => array(), 'created_at' => time(), 'updated_at' => time() );
				$notice  = 'list_created';
			}
		} elseif ( in_array( $action, array( 'add_story', 'remove_story', 'delete_list' ), true ) ) {
			$list_id = isset( $_POST['list_id'] ) ? preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) wp_unslash( $_POST['list_id'] ) ) ) : '';
			foreach ( $lists as $index => $list ) {
				if ( $list_id !== $list['id'] ) {
					continue;
				}
				if ( 'delete_list' === $action ) {
					unset( $lists[ $index ] );
					$notice = 'list_deleted';
					break;
				}

				$story_id = isset( $_POST['story_id'] ) ? absint( wp_unslash( $_POST['story_id'] ) ) : 0;
				if ( 'kabitab_story' !== get_post_type( $story_id ) || 'publish' !== get_post_status( $story_id ) ) {
					break;
				}
				if ( 'add_story' === $action ) {
					$lists[ $index ]['story_ids'] = array_values( array_unique( array_merge( $list['story_ids'], array( $story_id ) ) ) );
					$notice = 'story_added';
				} else {
					$lists[ $index ]['story_ids'] = array_values( array_diff( $list['story_ids'], array( $story_id ) ) );
					$notice = 'story_removed';
				}
				$lists[ $index ]['updated_at'] = time();
				break;
			}
		}

		update_user_meta( get_current_user_id(), self::META_KEY, array_values( $lists ) );
		self::redirect( $notice );
	}

	private static function get_lists() {
		$raw   = get_user_meta( get_current_user_id(), self::META_KEY, true );
		$lists = array();
		foreach ( is_array( $raw ) ? array_slice( $raw, 0, 20 ) : array() as $list ) {
			if ( ! is_array( $list ) ) {
				continue;
			}
			$id   = isset( $list['id'] ) ? preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $list['id'] ) ) : '';
			$name = isset( $list['name'] ) ? sanitize_text_field( (string) $list['name'] ) : '';
			if ( '' === $id || '' === $name ) {
				continue;
			}
			$lists[] = array( 'id' => $id, 'name' => $name, 'story_ids' => wp_parse_id_list( isset( $list['story_ids'] ) ? $list['story_ids'] : array() ) );
		}
		return $lists;
	}

	private static function render_action_form( $action, $list_id, $story_id, $label ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="kabitab_manage_reading_list" />
			<input type="hidden" name="kabitab_reading_list_action" value="<?php echo esc_attr( $action ); ?>" />
			<input type="hidden" name="list_id" value="<?php echo esc_attr( $list_id ); ?>" />
			<input type="hidden" name="story_id" value="<?php echo esc_attr( $story_id ); ?>" />
			<input type="hidden" name="return_url" value="<?php echo esc_url( self::library_url() ); ?>" />
			<?php wp_nonce_field( 'kabitab_manage_reading_lists', 'kabitab_reading_lists_nonce' ); ?>
			<button type="submit"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	private static function render_notice() {
		$notice = isset( $_GET['kabitab_list_notice'] ) ? sanitize_key( wp_unslash( $_GET['kabitab_list_notice'] ) ) : '';
		$messages = array(
			'list_created'  => __( 'Reading list created.', 'kabitab-story-system' ),
			'list_deleted'  => __( 'Reading list deleted.', 'kabitab-story-system' ),
			'story_added'   => __( 'Story added to your reading list.', 'kabitab-story-system' ),
			'story_removed' => __( 'Story removed from your reading list.', 'kabitab-story-system' ),
			'list_error'    => __( 'That reading-list action could not be completed.', 'kabitab-story-system' ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			echo '<p class="kabitab-reading-lists__notice" role="status">' . esc_html( $messages[ $notice ] ) . '</p>';
		}
	}

	private static function library_url() {
		return home_url( '/account/#kabitab-reading-lists' );
	}

	private static function redirect( $notice ) {
		$default = self::library_url();
		$url = isset( $_POST['return_url'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return_url'] ) ), $default ) : $default;
		wp_safe_redirect( add_query_arg( 'kabitab_list_notice', $notice, $url ) );
		exit;
	}
}
