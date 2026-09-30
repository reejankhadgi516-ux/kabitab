<?php
/**
 * Reusable dynamic Story card shortcode.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

class Kabitab_Story_Card_Shortcode {
	/**
	 * Registers the shortcode.
	 */
	public static function register() {
		add_shortcode( 'kabitab_story_card', array( __CLASS__, 'render' ) );
	}

	/**
	 * Registers the card stylesheet only for pages that contain the shortcode.
	 */
	public static function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		global $post;
		if ( $post instanceof WP_Post && has_shortcode( (string) $post->post_content, 'kabitab_story_card' ) ) {
			self::enqueue_style();
		}
	}

	/**
	 * Renders one valid Story card.
	 *
	 * @param array<string, mixed> $attributes Shortcode attributes.
	 * @return string
	 */
	public static function render( $attributes ) {
		$attributes = shortcode_atts(
			array(
				'id' => 0,
			),
			$attributes,
			'kabitab_story_card'
		);

		$story_id = absint( $attributes['id'] );
		if ( ! $story_id || 'kabitab_story' !== get_post_type( $story_id ) || 'publish' !== get_post_status( $story_id ) ) {
			return '';
		}

		$story = get_post( $story_id );
		if ( ! $story ) {
			return '';
		}

		self::enqueue_style();

		$genres = wp_get_post_terms( $story_id, 'story_genre' );
		$genre  = ( ! is_wp_error( $genres ) && ! empty( $genres ) ) ? $genres[0]->name : '';
		$states = kabitab_get_story_statuses();
		$status = (string) get_post_meta( $story_id, '_kabitab_status', true );

		$card = array(
			'id'            => $story_id,
			'title'         => get_the_title( $story_id ),
			'author'        => Kabitab_Frontend::get_author_label( $story_id ),
			'genre'         => $genre,
			'blurb'         => (string) get_post_meta( $story_id, '_kabitab_blurb', true ),
			'status'        => isset( $states[ $status ] ) ? $states[ $status ] : $status,
			'chapter_count' => kabitab_get_published_chapter_count( $story_id ),
			'reads'         => kabitab_sanitize_nonnegative_integer( get_post_meta( $story_id, '_kabitab_reads_count', true ) ),
			'likes'         => kabitab_sanitize_nonnegative_integer( get_post_meta( $story_id, '_kabitab_likes_count', true ) ),
			'permalink'     => get_permalink( $story_id ),
			'cover'         => Kabitab_Frontend::get_story_cover_markup(
				$story_id,
				'kabitab-story-card',
				array(
					'alt' => sprintf( __( '%s cover', 'kabitab-story-system' ), get_the_title( $story_id ) ),
				)
			),
		);

		$template = KABITAB_STORY_SYSTEM_PATH . 'templates/parts/story-card.php';
		if ( ! file_exists( $template ) ) {
			return '';
		}

		ob_start();
		include $template;
		return (string) ob_get_clean();
	}

	/**
	 * Enqueues the scoped stylesheet.
	 */
	private static function enqueue_style() {
		$stylesheet = KABITAB_STORY_SYSTEM_PATH . 'assets/css/story-card.css';
		if ( file_exists( $stylesheet ) ) {
			wp_enqueue_style( 'kabitab-story-card', KABITAB_STORY_SYSTEM_URL . 'assets/css/story-card.css', array(), (string) filemtime( $stylesheet ) );
		}
	}
}
