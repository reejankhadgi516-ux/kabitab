<?php
/**
 * Shared helpers for KABITAB Story System.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

function kabitab_get_story_statuses() {
	return array(
		'ongoing'   => __( 'Ongoing', 'kabitab-story-system' ),
		'completed' => __( 'Completed', 'kabitab-story-system' ),
	);
}

function kabitab_can_save_post_meta( $post_id, $post, $nonce, $action ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return false;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return false;
	}
	if ( ! isset( $_POST[ $nonce ] ) ) {
		return false;
	}
	$nonce_value = sanitize_text_field( wp_unslash( $_POST[ $nonce ] ) );
	if ( ! wp_verify_nonce( $nonce_value, $action ) ) {
		return false;
	}
	return current_user_can( 'edit_post', $post->ID );
}

function kabitab_request_has_field( $field ) {
	return array_key_exists( $field, $_POST );
}

function kabitab_get_request_field( $field ) {
	return isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : null;
}

function kabitab_sanitize_nonnegative_integer( $value ) {
	return max( 0, (int) $value );
}

function kabitab_sanitize_positive_integer( $value ) {
	$value = (int) $value;
	return $value > 0 ? $value : 0;
}

/**
 * Counts human-readable words using Unicode whitespace rather than ASCII boundaries.
 *
 * @param string $text Text or post content to count.
 * @return int
 */
function kabitab_count_words( $text ) {
	$text = html_entity_decode( wp_strip_all_tags( strip_shortcodes( (string) $text ), true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = trim( preg_replace( '/[\p{Z}\s]+/u', ' ', $text ) ?: '' );

	if ( '' === $text ) {
		return 0;
	}

	$words = preg_split( '/[\p{Z}\s]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
	if ( false === $words ) {
		$words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
	}

	return is_array( $words ) ? max( 0, count( $words ) ) : 0;
}

/**
 * Returns a positive Story ID only for a non-trashed KABITAB Story.
 *
 * @param mixed $story_id Story ID.
 * @return int
 */
function kabitab_get_valid_story_id( $story_id ) {
	$story_id = absint( $story_id );
	if ( ! $story_id || 'kabitab_story' !== get_post_type( $story_id ) || 'trash' === get_post_status( $story_id ) ) {
		return 0;
	}

	return $story_id;
}

/**
 * Returns a Chapter's valid parent Story ID.
 *
 * @param mixed $chapter_id Chapter ID.
 * @return int
 */
function kabitab_get_chapter_parent_story_id( $chapter_id ) {
	$chapter_id = absint( $chapter_id );
	if ( ! $chapter_id || 'kabitab_chapter' !== get_post_type( $chapter_id ) ) {
		return 0;
	}

	return kabitab_get_valid_story_id( get_post_meta( $chapter_id, '_kabitab_parent_story', true ) );
}

/**
 * Calculates a Chapter's reading time without persisting calculated values.
 *
 * @param int|string|WP_Post $chapter_or_content Chapter ID/post object or raw content.
 * @return int
 */
function kabitab_get_chapter_reading_time( $chapter_or_content ) {
	$chapter = null;
	if ( $chapter_or_content instanceof WP_Post ) {
		$chapter = $chapter_or_content;
	} elseif ( is_numeric( $chapter_or_content ) ) {
		$chapter = get_post( absint( $chapter_or_content ) );
	}

	if ( $chapter instanceof WP_Post && 'kabitab_chapter' === $chapter->post_type ) {
		$override = kabitab_sanitize_positive_integer( get_post_meta( $chapter->ID, '_kabitab_reading_time_override', true ) );
		if ( $override > 0 ) {
			return $override;
		}
		$content = $chapter->post_content;
	} elseif ( $chapter instanceof WP_Post || is_numeric( $chapter_or_content ) ) {
		return 0;
	} else {
		$content = (string) $chapter_or_content;
	}

	$word_count = kabitab_count_words( $content );
	return $word_count > 0 ? max( 1, (int) ceil( $word_count / 180 ) ) : 0;
}

/**
 * Counts published Chapters for one Story, with a request-local cache.
 *
 * @param mixed $story_id Story ID.
 * @return int
 */
function kabitab_get_published_chapter_count( $story_id ) {
	static $counts = array();

	$story_id = kabitab_get_valid_story_id( $story_id );
	if ( ! $story_id ) {
		return 0;
	}
	if ( isset( $counts[ $story_id ] ) ) {
		return $counts[ $story_id ];
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'kabitab_chapter',
			'post_status'            => 'publish',
			'meta_key'               => '_kabitab_parent_story',
			'meta_value'             => $story_id,
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$counts[ $story_id ] = max( 0, (int) $query->found_posts );
	return $counts[ $story_id ];
}

/**
 * Retrieves a finite ordered list of published Chapters for one Story.
 *
 * @param mixed $story_id      Story ID.
 * @param int   $posts_per_page Maximum Chapter posts to return.
 * @return WP_Post[]
 */
function kabitab_get_ordered_story_chapters( $story_id, $posts_per_page = 100 ) {
	static $chapters = array();

	$story_id = kabitab_get_valid_story_id( $story_id );
	if ( ! $story_id ) {
		return array();
	}

	$posts_per_page = absint( $posts_per_page );
	$posts_per_page = $posts_per_page ? min( 100, $posts_per_page ) : 100;
	$cache_key      = $story_id . ':' . $posts_per_page;
	if ( isset( $chapters[ $cache_key ] ) ) {
		return $chapters[ $cache_key ];
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'kabitab_chapter',
			'post_status'            => 'publish',
			'posts_per_page'         => $posts_per_page,
			'meta_query'             => array(
				'relation' => 'AND',
				array(
					'key'   => '_kabitab_parent_story',
					'value' => $story_id,
				),
				'chapter_number_clause' => array(
					'key'     => '_kabitab_chapter_number',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
			'orderby'                => array(
				'chapter_number_clause' => 'ASC',
				'date'                  => 'ASC',
				'ID'                    => 'ASC',
			),
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);

	$chapters[ $cache_key ] = $query->posts;
	return $chapters[ $cache_key ];
}

/**
 * Finds whether a non-trashed Chapter already uses a Story/number pair.
 *
 * @param mixed $story_id       Story ID.
 * @param mixed $chapter_number Chapter number.
 * @param mixed $exclude_id     Chapter ID to exclude.
 * @return bool
 */
function kabitab_chapter_number_exists( $story_id, $chapter_number, $exclude_id = 0 ) {
	$story_id       = kabitab_get_valid_story_id( $story_id );
	$chapter_number = kabitab_sanitize_positive_integer( $chapter_number );
	$exclude_id     = absint( $exclude_id );
	if ( ! $story_id || ! $chapter_number ) {
		return false;
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'kabitab_chapter',
			'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'post__not_in'           => $exclude_id ? array( $exclude_id ) : array(),
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'meta_query'             => array(
				'relation' => 'AND',
				array( 'key' => '_kabitab_parent_story', 'value' => $story_id ),
				array( 'key' => '_kabitab_chapter_number', 'value' => $chapter_number, 'type' => 'NUMERIC' ),
			),
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	return ! empty( $query->posts );
}

/**
 * Returns the adjacent published Chapter within the same Story.
 *
 * @param mixed $chapter_id Current Chapter ID.
 * @param string $direction  previous or next.
 * @return WP_Post|null
 */
function kabitab_get_adjacent_chapter( $chapter_id, $direction ) {
	$chapter_id = absint( $chapter_id );
	if ( ! $chapter_id || 'kabitab_chapter' !== get_post_type( $chapter_id ) ) {
		return null;
	}

	$story_id      = kabitab_get_chapter_parent_story_id( $chapter_id );
	$chapter_number = kabitab_sanitize_positive_integer( get_post_meta( $chapter_id, '_kabitab_chapter_number', true ) );
	if ( ! $story_id || ! $chapter_number || ! in_array( $direction, array( 'previous', 'next' ), true ) ) {
		return null;
	}

	$chapters = kabitab_get_ordered_story_chapters( $story_id );
	foreach ( $chapters as $index => $chapter ) {
		if ( (int) $chapter->ID !== $chapter_id ) {
			continue;
		}
		if ( 'previous' === $direction ) {
			return $index > 0 ? $chapters[ $index - 1 ] : null;
		}
		return isset( $chapters[ $index + 1 ] ) ? $chapters[ $index + 1 ] : null;
	}

	return null;
}

/**
 * Returns the previous published Chapter in the current Chapter's Story.
 *
 * @param mixed $chapter_id Current Chapter ID.
 * @return WP_Post|null
 */
function kabitab_get_previous_chapter( $chapter_id ) {
	return kabitab_get_adjacent_chapter( $chapter_id, 'previous' );
}

/**
 * Returns the next published Chapter in the current Chapter's Story.
 *
 * @param mixed $chapter_id Current Chapter ID.
 * @return WP_Post|null
 */
function kabitab_get_next_chapter( $chapter_id ) {
	return kabitab_get_adjacent_chapter( $chapter_id, 'next' );
}

/**
 * Returns the raw parent Story permalink for a valid Chapter relationship.
 *
 * @param mixed $chapter_id Chapter ID.
 * @return string
 */
function kabitab_get_chapter_story_url( $chapter_id ) {
	$story_id = kabitab_get_chapter_parent_story_id( $chapter_id );
	$url      = $story_id ? get_permalink( $story_id ) : false;
	return is_string( $url ) ? $url : '';
}

/**
 * Allows post-meta mutation only to users who can edit that post.
 *
 * @param bool   $allowed     Current authorization result.
 * @param string $meta_key    Meta key.
 * @param int    $post_id     Post ID.
 * @param int    $user_id     User ID.
 * @return bool
 */
function kabitab_can_edit_registered_meta( $allowed, $meta_key, $post_id, $user_id ) {
	return user_can( $user_id, 'edit_post', $post_id );
}
