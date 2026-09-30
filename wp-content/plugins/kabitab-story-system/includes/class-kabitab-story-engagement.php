<?php
/**
 * Privacy-conscious story reads, likes, saves, and trending discovery.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

class Kabitab_Story_Engagement {
	const LIKED_STORIES_META = '_kabitab_liked_stories';
	const VOTED_CHAPTERS_META = '_kabitab_voted_chapters';
	const TRACKED_READS_META = '_kabitab_tracked_reads_count';
	const USER_LIKES_META    = '_kabitab_user_likes_count';
	const USER_CHAPTER_VOTES_META = '_kabitab_user_chapter_votes_count';
	const TRENDING_CACHE_KEY = 'kabitab_trending_story_ids';

	/**
	 * The ordered Trending shelf for the current request.
	 *
	 * @var WP_Post[]|null
	 */
	private static $trending_stories = null;

	public static function register() {
		add_shortcode( 'kabitab_story_like', array( __CLASS__, 'render_like_control' ) );
		add_action( 'wp_ajax_kabitab_toggle_story_like', array( __CLASS__, 'toggle_like' ) );
		add_action( 'wp_ajax_kabitab_toggle_chapter_vote', array( __CLASS__, 'toggle_chapter_vote' ) );
	}

	/**
	 * Counts one story read per browser per day. This deliberately avoids
	 * profiles, cross-site tracking, and third-party analytics.
	 */
	public static function record_story_view() {
		if ( is_admin() || is_preview() || wp_doing_ajax() || ! is_singular( array( 'kabitab_story', 'kabitab_chapter' ) ) ) {
			return;
		}

		$story_id = is_singular( 'kabitab_story' ) ? get_queried_object_id() : kabitab_get_chapter_parent_story_id( get_queried_object_id() );
		if ( ! $story_id || 'publish' !== get_post_status( $story_id ) ) {
			return;
		}

		$cookie_name = 'kabitab_story_read_' . absint( $story_id );
		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			return;
		}

		$reads = absint( get_post_meta( $story_id, self::TRACKED_READS_META, true ) );
		update_post_meta( $story_id, self::TRACKED_READS_META, $reads + 1 );
		setcookie(
			$cookie_name,
			'1',
			array(
				'expires'  => time() + DAY_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ $cookie_name ] = '1';
		delete_transient( self::TRENDING_CACHE_KEY );
	}

	public static function enqueue_assets() {
		if ( ! is_singular( array( 'kabitab_story', 'kabitab_chapter' ) ) ) {
			return;
		}

		$script = KABITAB_STORY_SYSTEM_PATH . 'assets/js/story-engagement.js';
		if ( ! file_exists( $script ) ) {
			return;
		}

		wp_enqueue_script( 'kabitab-story-engagement', KABITAB_STORY_SYSTEM_URL . 'assets/js/story-engagement.js', array(), (string) filemtime( $script ), true );
		wp_localize_script(
			'kabitab-story-engagement',
			'kabitabStoryEngagement',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'error'   => __( 'We could not update that like. Please try again.', 'kabitab-story-system' ),
			)
		);
	}

	/**
	 * Displays a login-aware story like control.
	 *
	 * @param array $attributes Shortcode attributes.
	 * @return string
	 */
	public static function render_like_control( $attributes = array() ) {
		$attributes = shortcode_atts( array( 'story_id' => 0 ), $attributes, 'kabitab_story_like' );
		$story_id   = absint( $attributes['story_id'] );
		if ( ! $story_id ) {
			$story_id = get_queried_object_id();
		}
		if ( ! $story_id || 'kabitab_story' !== get_post_type( $story_id ) || 'publish' !== get_post_status( $story_id ) ) {
			return '';
		}

		if ( ! is_user_logged_in() ) {
			return '<a class="kabitab-like-control kabitab-like-control--login" href="' . esc_url( wp_login_url( get_permalink( $story_id ) ) ) . '">' . esc_html__( 'Sign in to like', 'kabitab-story-system' ) . '</a>';
		}

		$liked = self::is_liked( get_current_user_id(), $story_id );
		$label = $liked ? __( 'Liked', 'kabitab-story-system' ) : __( 'Like story', 'kabitab-story-system' );
		return sprintf(
			'<button class="kabitab-like-control%s" type="button" data-kabitab-story-like data-story-id="%d" data-nonce="%s" aria-pressed="%s">%s</button>',
			$liked ? ' is-liked' : '',
			$story_id,
			esc_attr( wp_create_nonce( 'kabitab_story_like_' . $story_id ) ),
			$liked ? 'true' : 'false',
			esc_html( $label )
		);
	}

	public static function toggle_like() {
		$story_id = isset( $_POST['story_id'] ) ? absint( wp_unslash( $_POST['story_id'] ) ) : 0;
		check_ajax_referer( 'kabitab_story_like_' . $story_id, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please sign in first.', 'kabitab-story-system' ) ), 401 );
		}
		if ( ! $story_id || 'kabitab_story' !== get_post_type( $story_id ) || 'publish' !== get_post_status( $story_id ) ) {
			wp_send_json_error( array( 'message' => __( 'That story is not available.', 'kabitab-story-system' ) ), 400 );
		}

		$user_id = get_current_user_id();
		$liked   = self::get_liked_stories( $user_id );
		if ( in_array( $story_id, $liked, true ) ) {
			$liked     = array_values( array_diff( $liked, array( $story_id ) ) );
			$is_liked  = false;
			$increment = -1;
		} else {
			$liked[]    = $story_id;
			$liked      = array_values( array_unique( array_map( 'absint', $liked ) ) );
			$is_liked   = true;
			$increment  = 1;
		}

		update_user_meta( $user_id, self::LIKED_STORIES_META, $liked );
		$user_likes = absint( get_post_meta( $story_id, self::USER_LIKES_META, true ) );
		update_post_meta( $story_id, self::USER_LIKES_META, max( 0, $user_likes + $increment ) );
		delete_transient( self::TRENDING_CACHE_KEY );

		wp_send_json_success(
			array(
				'liked' => $is_liked,
				'label' => $is_liked ? __( 'Liked', 'kabitab-story-system' ) : __( 'Like story', 'kabitab-story-system' ),
				'count' => self::get_like_count( $story_id ),
			)
		);
	}

	/**
	 * Displays a login-aware vote control for a published Chapter.
	 *
	 * @param int $chapter_id Chapter post ID.
	 * @return string
	 */
	public static function render_chapter_vote_control( $chapter_id = 0 ) {
		$chapter_id = absint( $chapter_id ? $chapter_id : get_queried_object_id() );
		if ( ! $chapter_id || 'kabitab_chapter' !== get_post_type( $chapter_id ) || 'publish' !== get_post_status( $chapter_id ) ) {
			return '';
		}
		$count = self::get_chapter_vote_count( $chapter_id );
		if ( ! is_user_logged_in() ) {
			return '<a class="kabitab-like-control kabitab-like-control--login" href="' . esc_url( wp_login_url( get_permalink( $chapter_id ) ) ) . '">' . esc_html__( 'Sign in to vote', 'kabitab-story-system' ) . '</a><span class="kabitab-chapter-vote-count" data-kabitab-chapter-vote-count>' . esc_html( number_format_i18n( $count ) ) . '</span>';
		}

		$voted = in_array( $chapter_id, wp_parse_id_list( get_user_meta( get_current_user_id(), self::VOTED_CHAPTERS_META, true ) ), true );
		return sprintf(
			'<button class="kabitab-like-control%s" type="button" data-kabitab-chapter-vote data-chapter-id="%d" data-nonce="%s" aria-pressed="%s">%s</button><span class="kabitab-chapter-vote-count" data-kabitab-chapter-vote-count>%s</span>',
			$voted ? ' is-liked' : '',
			$chapter_id,
			esc_attr( wp_create_nonce( 'kabitab_chapter_vote_' . $chapter_id ) ),
			$voted ? 'true' : 'false',
			esc_html( $voted ? __( 'Voted', 'kabitab-story-system' ) : __( 'Vote for this chapter', 'kabitab-story-system' ) ),
			esc_html( number_format_i18n( $count ) )
		);
	}

	public static function toggle_chapter_vote() {
		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( wp_unslash( $_POST['chapter_id'] ) ) : 0;
		check_ajax_referer( 'kabitab_chapter_vote_' . $chapter_id, 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please sign in first.', 'kabitab-story-system' ) ), 401 );
		}
		if ( ! $chapter_id || 'kabitab_chapter' !== get_post_type( $chapter_id ) || 'publish' !== get_post_status( $chapter_id ) ) {
			wp_send_json_error( array( 'message' => __( 'That chapter is not available.', 'kabitab-story-system' ) ), 400 );
		}

		$user_id = get_current_user_id();
		$voted = wp_parse_id_list( get_user_meta( $user_id, self::VOTED_CHAPTERS_META, true ) );
		if ( in_array( $chapter_id, $voted, true ) ) {
			$voted = array_values( array_diff( $voted, array( $chapter_id ) ) );
			$is_voted = false;
			$increment = -1;
		} else {
			$voted = array_values( array_unique( array_merge( $voted, array( $chapter_id ) ) ) );
			$is_voted = true;
			$increment = 1;
		}
		update_user_meta( $user_id, self::VOTED_CHAPTERS_META, $voted );
		$count = absint( get_post_meta( $chapter_id, self::USER_CHAPTER_VOTES_META, true ) );
		update_post_meta( $chapter_id, self::USER_CHAPTER_VOTES_META, max( 0, $count + $increment ) );
		wp_send_json_success(
			array(
				'voted' => $is_voted,
				'label' => $is_voted ? __( 'Voted', 'kabitab-story-system' ) : __( 'Vote for this chapter', 'kabitab-story-system' ),
				'count' => self::get_chapter_vote_count( $chapter_id ),
			)
		);
	}

	/**
	 * @param int $story_id Story ID.
	 * @return int
	 */
	public static function get_read_count( $story_id ) {
		return absint( get_post_meta( $story_id, '_kabitab_reads_count', true ) ) + absint( get_post_meta( $story_id, self::TRACKED_READS_META, true ) );
	}

	/**
	 * @param int $story_id Story ID.
	 * @return int
	 */
	public static function get_like_count( $story_id ) {
		return absint( get_post_meta( $story_id, '_kabitab_likes_count', true ) ) + absint( get_post_meta( $story_id, self::USER_LIKES_META, true ) );
	}

	/**
	 * @param int $chapter_id Chapter post ID.
	 * @return int
	 */
	public static function get_chapter_vote_count( $chapter_id ) {
		return absint( get_post_meta( $chapter_id, self::USER_CHAPTER_VOTES_META, true ) );
	}

	/**
	 * @param int $story_id Story ID.
	 * @return int
	 */
	public static function get_save_count( $story_id ) {
		if ( function_exists( 'get_favorites_count' ) ) {
			return absint( get_favorites_count( $story_id, null, false ) );
		}
		return absint( get_post_meta( $story_id, 'simplefavorites_count', true ) );
	}

	/**
	 * @param int $story_id Story ID.
	 * @return bool
	 */
	public static function has_engagement( $story_id ) {
		return self::get_read_count( $story_id ) > 0 || self::get_like_count( $story_id ) > 0 || self::get_save_count( $story_id ) > 0;
	}

	/**
	 * Returns published stories ordered by a transparent engagement score.
	 *
	 * Saves are weighted most because they signal an intentional return; likes
	 * and privacy-safe daily reads provide additional discovery context.
	 *
	 * @param int $limit Maximum stories to return.
	 * @return WP_Post[]
	 */
	public static function get_trending_stories( $limit = 6 ) {
		$limit      = max( 1, min( 24, absint( $limit ) ) );

		if ( null === self::$trending_stories ) {
			$manual_ids = get_posts(
				array(
					'post_type'      => 'kabitab_story',
					'post_status'    => 'publish',
					'posts_per_page' => 24,
					'fields'         => 'ids',
					'meta_key'       => '_kabitab_is_manual_trending',
					'meta_value'     => 1,
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				)
			);
			$cached_ids = get_transient( self::TRENDING_CACHE_KEY );
			if ( ! is_array( $cached_ids ) ) {
				$stories = get_posts(
					array(
						'post_type'      => 'kabitab_story',
						'post_status'    => 'publish',
						'posts_per_page' => 60,
						'orderby'        => 'modified',
						'order'          => 'DESC',
						'no_found_rows'  => true,
					)
				);
				$scores = array();
				foreach ( $stories as $story ) {
					$score = self::get_read_count( $story->ID ) + ( self::get_like_count( $story->ID ) * 4 ) + ( self::get_save_count( $story->ID ) * 6 );
					if ( $score > 0 ) {
						$scores[ $story->ID ] = $score;
					}
				}
				arsort( $scores, SORT_NUMERIC );
				$cached_ids = array_map( 'absint', array_keys( $scores ) );
				set_transient( self::TRENDING_CACHE_KEY, $cached_ids, 10 * MINUTE_IN_SECONDS );
			}

			$story_ids = array_slice(
				array_values(
					array_unique(
						array_merge(
							wp_parse_id_list( $manual_ids ),
							wp_parse_id_list( $cached_ids )
						)
					)
				),
				0,
				24
			);

			if ( empty( $story_ids ) ) {
				self::$trending_stories = array();
			} else {
				self::$trending_stories = get_posts(
					array(
						'post_type'      => 'kabitab_story',
						'post_status'    => 'publish',
						'post__in'       => $story_ids,
						'orderby'        => 'post__in',
						'posts_per_page' => 24,
						'no_found_rows'  => true,
					)
				);
			}
		}

		return array_slice( self::$trending_stories, 0, $limit );
	}

	public static function has_trending_stories() {
		return ! empty( self::get_trending_stories( 1 ) );
	}

	/**
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	private static function get_liked_stories( $user_id ) {
		$liked = get_user_meta( absint( $user_id ), self::LIKED_STORIES_META, true );
		$liked = is_array( $liked ) ? array_map( 'absint', $liked ) : array();
		return array_values( array_filter( array_unique( $liked ) ) );
	}

	/**
	 * @param int $user_id  User ID.
	 * @param int $story_id Story ID.
	 * @return bool
	 */
	private static function is_liked( $user_id, $story_id ) {
		return in_array( absint( $story_id ), self::get_liked_stories( $user_id ), true );
	}
}
