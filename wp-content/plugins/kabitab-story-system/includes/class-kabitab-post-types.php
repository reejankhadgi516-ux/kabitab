<?php
/**
 * Post types and taxonomy registration.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

class Kabitab_Post_Types {
	public static function register() {
		register_post_type(
			'kabitab_story',
			array(
				'labels'       => self::get_story_labels(),
				'public'       => true,
				'show_in_rest' => true,
				'rest_base'    => 'kabitab_story',
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'story' ),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
				'menu_icon'    => 'dashicons-book-alt',
			)
		);
		register_post_type(
			'kabitab_chapter',
			array(
				'labels'       => self::get_chapter_labels(),
				'public'       => true,
				'show_in_rest' => true,
				'rest_base'    => 'kabitab_chapter',
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'chapter' ),
				'supports'     => array( 'title', 'editor', 'thumbnail', 'author', 'revisions', 'comments' ),
				'menu_icon'    => 'dashicons-media-document',
			)
		);
		register_taxonomy(
			'story_genre',
			array( 'kabitab_story' ),
			array(
				'labels'       => self::get_genre_labels(),
				'public'       => true,
				'show_in_rest' => true,
				'rest_base'    => 'story_genre',
				'hierarchical' => false,
				'rewrite'      => array( 'slug' => 'story-genre' ),
			)
		);
		self::register_meta();
	}

	private static function register_meta() {
		$protected_meta = array(
			'single'          => true,
			'auth_callback'   => 'kabitab_can_edit_registered_meta',
			'show_in_rest'    => false,
		);
		register_post_meta( 'kabitab_story', '_kabitab_blurb', array_merge( $protected_meta, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ) ) );
		register_post_meta( 'kabitab_story', '_kabitab_status', array_merge( $protected_meta, array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_story_status' ) ) ) );
		register_post_meta( 'kabitab_story', '_kabitab_is_editor_pick', array_merge( $protected_meta, array( 'type' => 'integer', 'sanitize_callback' => array( __CLASS__, 'sanitize_binary_flag' ) ) ) );
		register_post_meta( 'kabitab_story', '_kabitab_is_manual_trending', array_merge( $protected_meta, array( 'type' => 'integer', 'sanitize_callback' => array( __CLASS__, 'sanitize_binary_flag' ) ) ) );
		register_post_meta( 'kabitab_story', '_kabitab_reads_count', array_merge( $protected_meta, array( 'type' => 'integer', 'sanitize_callback' => 'kabitab_sanitize_nonnegative_integer' ) ) );
		register_post_meta( 'kabitab_story', '_kabitab_likes_count', array_merge( $protected_meta, array( 'type' => 'integer', 'sanitize_callback' => 'kabitab_sanitize_nonnegative_integer' ) ) );
		register_post_meta( 'kabitab_chapter', '_kabitab_parent_story', array_merge( $protected_meta, array( 'type' => 'integer', 'sanitize_callback' => 'kabitab_get_valid_story_id' ) ) );
		register_post_meta( 'kabitab_chapter', '_kabitab_chapter_number', array_merge( $protected_meta, array( 'type' => 'integer', 'sanitize_callback' => 'kabitab_sanitize_positive_integer' ) ) );
		register_post_meta( 'kabitab_chapter', '_kabitab_reading_time_override', array_merge( $protected_meta, array( 'type' => 'integer', 'sanitize_callback' => 'kabitab_sanitize_nonnegative_integer' ) ) );
	}

	public static function sanitize_story_status( $value ) {
		$value = sanitize_key( $value );
		return array_key_exists( $value, kabitab_get_story_statuses() ) ? $value : '';
	}

	public static function sanitize_binary_flag( $value ) {
		return empty( $value ) ? 0 : 1;
	}

	private static function get_story_labels() {
		return array(
			'name'          => __( 'Stories', 'kabitab-story-system' ),
			'singular_name' => __( 'Story', 'kabitab-story-system' ),
			'add_new_item'  => __( 'Add New Story', 'kabitab-story-system' ),
			'edit_item'     => __( 'Edit Story', 'kabitab-story-system' ),
			'all_items'     => __( 'Stories', 'kabitab-story-system' ),
			'menu_name'     => __( 'Stories', 'kabitab-story-system' ),
		);
	}

	private static function get_chapter_labels() {
		return array(
			'name'          => __( 'Chapters', 'kabitab-story-system' ),
			'singular_name' => __( 'Chapter', 'kabitab-story-system' ),
			'add_new_item'  => __( 'Add New Chapter', 'kabitab-story-system' ),
			'edit_item'     => __( 'Edit Chapter', 'kabitab-story-system' ),
			'all_items'     => __( 'Chapters', 'kabitab-story-system' ),
			'menu_name'     => __( 'Chapters', 'kabitab-story-system' ),
		);
	}

	private static function get_genre_labels() {
		return array(
			'name'          => __( 'Genres', 'kabitab-story-system' ),
			'singular_name' => __( 'Genre', 'kabitab-story-system' ),
			'search_items'  => __( 'Search Genres', 'kabitab-story-system' ),
			'all_items'     => __( 'Genres', 'kabitab-story-system' ),
			'edit_item'     => __( 'Edit Genre', 'kabitab-story-system' ),
			'add_new_item'  => __( 'Add New Genre', 'kabitab-story-system' ),
			'menu_name'     => __( 'Genres', 'kabitab-story-system' ),
		);
	}
}
