<?php
/**
 * Private, in-app updates for followed writers and chapter discussion.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

class Kabitab_Notifications {
	const META_KEY       = '_kabitab_notifications';
	const MAX_PER_READER = 40;

	public static function register() {
		add_shortcode( 'kabitab_notifications', array( __CLASS__, 'render' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'notify_followers_of_new_chapter' ), 10, 3 );
		add_action( 'comment_post', array( __CLASS__, 'notify_chapter_author_of_comment' ), 10, 3 );
		add_action( 'transition_comment_status', array( __CLASS__, 'notify_author_when_comment_is_approved' ), 10, 3 );
	}

	/**
	 * Adds one update for every reader who follows the chapter's writer.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       Updated post.
	 */
	public static function notify_followers_of_new_chapter( $new_status, $old_status, $post ) {
		if ( 'kabitab_chapter' !== $post->post_type || 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		$story_id  = kabitab_get_chapter_parent_story_id( $post->ID );
		$author_id = $story_id ? (int) get_post_field( 'post_author', $story_id ) : (int) $post->post_author;
		if ( ! $author_id ) {
			return;
		}

		$author_name = Kabitab_Frontend::get_author_label( $story_id ? $story_id : $post->ID );
		$story_title = $story_id ? get_the_title( $story_id ) : __( 'a KABITAB story', 'kabitab-story-system' );
		$message     = sprintf(
			/* translators: 1: writer name, 2: story title, 3: chapter title. */
			__( '%1$s published a new chapter in %2$s: %3$s', 'kabitab-story-system' ),
			$author_name,
			$story_title,
			get_the_title( $post )
		);

		foreach ( self::get_follower_ids( $author_id ) as $reader_id ) {
			self::add( $reader_id, 'chapter', 'chapter-' . $post->ID, $message, get_permalink( $post ) );
		}
	}

	/**
	 * Alerts a chapter writer immediately when an approved comment is added.
	 *
	 * @param int   $comment_id Comment ID.
	 * @param int   $approved   Whether the comment is approved.
	 * @param array $commentdata Submitted comment data.
	 */
	public static function notify_chapter_author_of_comment( $comment_id, $approved, $commentdata ) {
		if ( 1 !== (int) $approved ) {
			return;
		}
		self::add_comment_notification( $comment_id );
	}

	/**
	 * Alerts a chapter writer when a moderated comment becomes visible.
	 *
	 * @param string     $new_status New comment status.
	 * @param string     $old_status Previous comment status.
	 * @param WP_Comment $comment    Updated comment.
	 */
	public static function notify_author_when_comment_is_approved( $new_status, $old_status, $comment ) {
		if ( 'approved' !== $new_status || 'approved' === $old_status || ! $comment instanceof WP_Comment ) {
			return;
		}
		self::add_comment_notification( $comment->comment_ID );
	}

	/**
	 * Renders a reader's recent account-specific updates.
	 *
	 * @return string
	 */
	public static function render() {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$notifications = self::get( get_current_user_id() );
		if ( empty( $notifications ) ) {
			return '';
		}

		$unread_count = count(
			array_filter(
				$notifications,
				static function ( $notification ) {
					return empty( $notification['read_at'] );
				}
			)
		);

		ob_start();
		?>
		<section class="kabitab-notifications" aria-labelledby="kabitab-notifications-title">
			<div class="kabitab-notifications__title">
				<h3 id="kabitab-notifications-title"><?php esc_html_e( 'Latest updates', 'kabitab-story-system' ); ?></h3>
				<?php if ( $unread_count ) : ?><span class="kabitab-notifications__count" aria-label="<?php echo esc_attr( sprintf( _n( '%d unread update', '%d unread updates', $unread_count, 'kabitab-story-system' ), $unread_count ) ); ?>"><?php echo esc_html( $unread_count ); ?></span><?php endif; ?>
			</div>
			<ul class="kabitab-notifications__list">
				<?php foreach ( array_slice( $notifications, 0, 8 ) as $notification ) : ?>
					<li>
						<a href="<?php echo esc_url( $notification['url'] ); ?>"><?php echo esc_html( $notification['message'] ); ?></a>
						<time datetime="<?php echo esc_attr( gmdate( 'c', $notification['created_at'] ) ); ?>"><?php echo esc_html( human_time_diff( $notification['created_at'], current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'kabitab-story-system' ) ); ?></time>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php

		self::mark_as_read( get_current_user_id(), $notifications );
		return (string) ob_get_clean();
	}

	/**
	 * Creates a single deduplicated notification for an account.
	 *
	 * @param int    $user_id Account to notify.
	 * @param string $type    Update category.
	 * @param string $key     Event-specific unique key.
	 * @param string $message Human-readable update.
	 * @param string $url     Destination URL.
	 */
	private static function add( $user_id, $type, $key, $message, $url ) {
		$user_id       = absint( $user_id );
		$notifications = self::get( $user_id );
		if ( ! $user_id || ! get_user_by( 'id', $user_id ) ) {
			return;
		}

		foreach ( $notifications as $notification ) {
			if ( $key === $notification['key'] ) {
				return;
			}
		}

		array_unshift(
			$notifications,
			array(
				'key'        => sanitize_key( $key ),
				'type'       => sanitize_key( $type ),
				'message'    => sanitize_text_field( $message ),
				'url'        => esc_url_raw( $url ),
				'created_at' => time(),
				'read_at'    => 0,
			)
		);

		update_user_meta( $user_id, self::META_KEY, array_slice( $notifications, 0, self::MAX_PER_READER ) );
	}

	/**
	 * Adds a notification for a visible comment if it belongs to a Chapter.
	 *
	 * @param int $comment_id Comment ID.
	 */
	private static function add_comment_notification( $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment instanceof WP_Comment || 'kabitab_chapter' !== get_post_type( $comment->comment_post_ID ) ) {
			return;
		}

		$author_id = (int) get_post_field( 'post_author', $comment->comment_post_ID );
		if ( ! $author_id || $author_id === (int) $comment->user_id ) {
			return;
		}

		$reader_name = '' !== trim( $comment->comment_author ) ? $comment->comment_author : __( 'A reader', 'kabitab-story-system' );
		$message     = sprintf(
			/* translators: 1: reader name, 2: chapter title. */
			__( '%1$s commented on %2$s', 'kabitab-story-system' ),
			$reader_name,
			get_the_title( $comment->comment_post_ID )
		);
		self::add( $author_id, 'comment', 'comment-' . $comment->comment_ID, $message, get_comment_link( $comment ) );
	}

	/**
	 * Finds readers who follow one writer without exposing their data publicly.
	 *
	 * @param int $author_id Writer ID.
	 * @return int[]
	 */
	private static function get_follower_ids( $author_id ) {
		$followers = get_users(
			array(
				'fields'       => 'ids',
				'meta_key'     => Kabitab_Writer_Following::META_KEY,
				'meta_value'   => 'i:' . absint( $author_id ) . ';',
				'meta_compare' => 'LIKE',
				'number'       => -1,
			)
		);
		return array_map( 'absint', $followers );
	}

	/**
	 * Retrieves valid stored notifications for one account.
	 *
	 * @param int $user_id Account ID.
	 * @return array[]
	 */
	private static function get( $user_id ) {
		$stored = get_user_meta( absint( $user_id ), self::META_KEY, true );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		$notifications = array();
		foreach ( $stored as $notification ) {
			if ( ! is_array( $notification ) || empty( $notification['key'] ) || empty( $notification['message'] ) || empty( $notification['url'] ) ) {
				continue;
			}
			$notifications[] = array(
				'key'        => sanitize_key( $notification['key'] ),
				'type'       => isset( $notification['type'] ) ? sanitize_key( $notification['type'] ) : '',
				'message'    => sanitize_text_field( $notification['message'] ),
				'url'        => esc_url_raw( $notification['url'] ),
				'created_at' => isset( $notification['created_at'] ) ? absint( $notification['created_at'] ) : 0,
				'read_at'    => isset( $notification['read_at'] ) ? absint( $notification['read_at'] ) : 0,
			);
		}
		return $notifications;
	}

	/**
	 * Keeps the badge useful without adding client-side state or polling.
	 *
	 * @param int     $user_id       Account ID.
	 * @param array[] $notifications Notifications just rendered.
	 */
	private static function mark_as_read( $user_id, $notifications ) {
		$changed = false;
		foreach ( $notifications as &$notification ) {
			if ( empty( $notification['read_at'] ) ) {
				$notification['read_at'] = time();
				$changed                  = true;
			}
		}
		unset( $notification );

		if ( $changed ) {
			update_user_meta( absint( $user_id ), self::META_KEY, $notifications );
		}
	}
}
