<?php
/**
 * Logged-in reader progress storage and resume helpers.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

class Kabitab_Reading_Progress {
	const META_KEY = '_kabitab_reading_progress';

	/**
	 * Registers the authenticated progress endpoint.
	 */
	public static function register() {
		add_action( 'wp_ajax_kabitab_save_reading_progress', array( __CLASS__, 'save' ) );
	}

	/**
	 * Returns a validated progress record for one story.
	 *
	 * @param int $story_id Story post ID.
	 * @param int $user_id  User ID, or the current user when omitted.
	 * @return array{chapter_id:int,percent:int,updated_at:int}|array{}
	 */
	public static function get_for_story( $story_id, $user_id = 0 ) {
		$story_id = absint( $story_id );
		$user_id  = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $story_id || ! $user_id ) {
			return array();
		}

		$all    = get_user_meta( $user_id, self::META_KEY, true );
		$record = is_array( $all ) && isset( $all[ $story_id ] ) && is_array( $all[ $story_id ] ) ? $all[ $story_id ] : array();
		$chapter_id = isset( $record['chapter_id'] ) ? absint( $record['chapter_id'] ) : 0;
		if ( ! $chapter_id || $story_id !== kabitab_get_chapter_parent_story_id( $chapter_id ) || 'publish' !== get_post_status( $chapter_id ) ) {
			return array();
		}

		return array(
			'chapter_id' => $chapter_id,
			'percent'    => isset( $record['percent'] ) ? max( 0, min( 100, absint( $record['percent'] ) ) ) : 0,
			'updated_at' => isset( $record['updated_at'] ) ? absint( $record['updated_at'] ) : 0,
		);
	}

	/**
	 * Saves the current chapter and percentage for an authenticated reader.
	 */
	public static function save() {
		check_ajax_referer( 'kabitab_reading_progress', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Sign in to sync reading progress.', 'kabitab-story-system' ) ), 401 );
		}

		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( wp_unslash( $_POST['chapter_id'] ) ) : 0;
		$story_id   = isset( $_POST['story_id'] ) ? absint( wp_unslash( $_POST['story_id'] ) ) : 0;
		$percent    = isset( $_POST['percent'] ) ? (int) round( (float) wp_unslash( $_POST['percent'] ) ) : 0;
		$percent    = max( 0, min( 100, $percent ) );

		if ( ! $chapter_id || ! $story_id || 'publish' !== get_post_status( $chapter_id ) || $story_id !== kabitab_get_chapter_parent_story_id( $chapter_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid reading position.', 'kabitab-story-system' ) ), 400 );
		}

		$user_id = get_current_user_id();
		$all     = get_user_meta( $user_id, self::META_KEY, true );
		$all     = is_array( $all ) ? $all : array();
		$all[ $story_id ] = array(
			'chapter_id' => $chapter_id,
			'percent'    => $percent,
			'updated_at' => time(),
		);
		update_user_meta( $user_id, self::META_KEY, $all );

		wp_send_json_success(
			array(
				'chapter_id' => $chapter_id,
				'story_id'   => $story_id,
				'percent'    => $percent,
			)
		);
	}
}
