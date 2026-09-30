<?php
/**
 * Plugin Name: KABITAB Story System
 * Description: Foundation for KABITAB stories, chapters, genres, and editorial metadata.
 * Version: 1.3.0
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Author: KABITAB
 * Text Domain: kabitab-story-system
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

define( 'KABITAB_STORY_SYSTEM_VERSION', '1.3.0' );
define( 'KABITAB_STORY_SYSTEM_FILE', __FILE__ );
define( 'KABITAB_STORY_SYSTEM_PATH', plugin_dir_path( __FILE__ ) );
define( 'KABITAB_STORY_SYSTEM_URL', plugin_dir_url( __FILE__ ) );

require_once KABITAB_STORY_SYSTEM_PATH . 'includes/helpers.php';
require_once KABITAB_STORY_SYSTEM_PATH . 'includes/class-kabitab-post-types.php';
require_once KABITAB_STORY_SYSTEM_PATH . 'includes/class-kabitab-meta-boxes.php';
require_once KABITAB_STORY_SYSTEM_PATH . 'includes/class-kabitab-admin.php';
require_once KABITAB_STORY_SYSTEM_PATH . 'includes/class-kabitab-frontend.php';
require_once KABITAB_STORY_SYSTEM_PATH . 'includes/class-kabitab-story-card-shortcode.php';
require_once KABITAB_STORY_SYSTEM_PATH . 'includes/class-kabitab-story-grid-shortcode.php';
require_once KABITAB_STORY_SYSTEM_PATH . 'includes/class-kabitab-writer-workflow.php';
require_once KABITAB_STORY_SYSTEM_PATH . 'includes/class-kabitab-writer-dashboard.php';
require_once KABITAB_STORY_SYSTEM_PATH . 'includes/class-kabitab-reader-interactions.php';
require_once KABITAB_STORY_SYSTEM_PATH . 'includes/class-kabitab-reading-progress.php';
require_once KABITAB_STORY_SYSTEM_PATH . 'includes/class-kabitab-reading-lists.php';
require_once KABITAB_STORY_SYSTEM_PATH . 'includes/class-kabitab-reader-library.php';
require_once KABITAB_STORY_SYSTEM_PATH . 'includes/class-kabitab-writer-following.php';
require_once KABITAB_STORY_SYSTEM_PATH . 'includes/class-kabitab-notifications.php';
require_once KABITAB_STORY_SYSTEM_PATH . 'includes/class-kabitab-story-engagement.php';

function kabitab_story_system_activate() {
	Kabitab_Post_Types::register();
	Kabitab_Writer_Dashboard::ensure_writer_role();
	flush_rewrite_rules();
}

function kabitab_story_system_deactivate() {
	flush_rewrite_rules();
}

/**
 * A focused card size keeps discovery artwork sharp without loading originals.
 */
function kabitab_story_system_register_image_sizes() {
	add_image_size( 'kabitab-story-card', 640, 480, true );
}

register_activation_hook( KABITAB_STORY_SYSTEM_FILE, 'kabitab_story_system_activate' );
register_deactivation_hook( KABITAB_STORY_SYSTEM_FILE, 'kabitab_story_system_deactivate' );

add_action( 'after_setup_theme', 'kabitab_story_system_register_image_sizes' );
add_action( 'init', array( 'Kabitab_Post_Types', 'register' ) );
add_action( 'init', array( 'Kabitab_Writer_Dashboard', 'ensure_writer_role' ), 5 );
add_action( 'add_meta_boxes', array( 'Kabitab_Meta_Boxes', 'register' ) );
add_action( 'save_post_kabitab_story', array( 'Kabitab_Meta_Boxes', 'save_story' ), 10, 3 );
add_action( 'save_post_kabitab_chapter', array( 'Kabitab_Meta_Boxes', 'save_chapter' ), 10, 3 );
add_action( 'admin_enqueue_scripts', array( 'Kabitab_Admin', 'enqueue_assets' ) );
add_action( 'admin_menu', array( 'Kabitab_Admin', 'register_curation_page' ) );
add_action( 'admin_menu', array( 'Kabitab_Admin', 'register_review_page' ) );
add_action( 'admin_post_kabitab_save_story_curation', array( 'Kabitab_Admin', 'save_curation' ) );
add_action( 'admin_post_kabitab_review_submission', array( 'Kabitab_Admin', 'review_submission' ) );
add_action( 'admin_notices', array( 'Kabitab_Meta_Boxes', 'render_chapter_save_error' ) );
add_filter( 'manage_kabitab_story_posts_columns', array( 'Kabitab_Admin', 'add_story_columns' ) );
add_action( 'manage_kabitab_story_posts_custom_column', array( 'Kabitab_Admin', 'render_story_column' ), 10, 2 );
add_filter( 'manage_kabitab_chapter_posts_columns', array( 'Kabitab_Admin', 'add_chapter_columns' ) );
add_action( 'manage_kabitab_chapter_posts_custom_column', array( 'Kabitab_Admin', 'render_chapter_column' ), 10, 2 );
add_filter( 'manage_edit-kabitab_chapter_sortable_columns', array( 'Kabitab_Admin', 'add_chapter_sortable_columns' ) );
add_action( 'pre_get_posts', array( 'Kabitab_Admin', 'sort_chapter_number_column' ) );
add_filter( 'template_include', array( 'Kabitab_Frontend', 'load_template' ), 99 );
add_filter( 'body_class', array( 'Kabitab_Frontend', 'add_body_classes' ) );
add_filter( 'wp_robots', array( 'Kabitab_Frontend', 'discourage_dashboard_indexing' ) );
add_filter( 'rank_math/frontend/robots', array( 'Kabitab_Frontend', 'discourage_dashboard_rank_math_indexing' ), 20 );
add_filter( 'wp_nav_menu_items', array( 'Kabitab_Frontend', 'add_mobile_account_menu_item' ), 10, 2 );
add_filter( 'nav_menu_css_class', array( 'Kabitab_Frontend', 'clean_home_anchor_menu_classes' ), 20, 2 );
add_filter( 'the_content', array( 'Kabitab_Frontend', 'link_home_category_cards' ), 12 );
add_action( 'template_redirect', array( 'Kabitab_Frontend', 'redirect_legacy_reader_pages' ) );
add_action( 'wp_enqueue_scripts', array( 'Kabitab_Frontend', 'enqueue_assets' ) );
add_action( 'wp_enqueue_scripts', array( 'Kabitab_Frontend', 'dequeue_unneeded_assets' ), PHP_INT_MAX );
add_filter( 'the_content', array( 'Kabitab_Frontend', 'protect_rest_page_content' ), 10 );
add_filter( 'the_content', array( 'Kabitab_Frontend', 'prepare_dashboard_content' ), 10 );
add_filter( 'the_content', array( 'Kabitab_Frontend', 'clean_explore_content' ), PHP_INT_MAX );
add_action( 'init', array( 'Kabitab_Story_Card_Shortcode', 'register' ) );
add_action( 'wp_enqueue_scripts', array( 'Kabitab_Story_Card_Shortcode', 'enqueue_assets' ) );
add_action( 'init', array( 'Kabitab_Story_Grid_Shortcode', 'register' ) );
add_action( 'wp_enqueue_scripts', array( 'Kabitab_Story_Grid_Shortcode', 'enqueue_assets' ) );
add_action( 'init', array( 'Kabitab_Writer_Workflow', 'register' ) );
add_action( 'init', array( 'Kabitab_Reading_Lists', 'register' ) );
add_action( 'init', array( 'Kabitab_Writer_Dashboard', 'register' ) );
add_action( 'wp_enqueue_scripts', array( 'Kabitab_Writer_Dashboard', 'enqueue_assets' ) );
add_action( 'wp_insert_post_data', array( 'Kabitab_Reader_Interactions', 'set_new_chapter_comment_status' ), 10, 2 );
add_action( 'init', array( 'Kabitab_Reading_Progress', 'register' ) );
add_action( 'init', array( 'Kabitab_Reader_Library', 'register' ) );
add_action( 'wp_enqueue_scripts', array( 'Kabitab_Reader_Library', 'enqueue_assets' ) );
add_action( 'init', array( 'Kabitab_Writer_Following', 'register' ) );
add_action( 'wp_enqueue_scripts', array( 'Kabitab_Writer_Following', 'enqueue_assets' ) );
add_action( 'init', array( 'Kabitab_Notifications', 'register' ) );
add_action( 'init', array( 'Kabitab_Story_Engagement', 'register' ) );
add_action( 'template_redirect', array( 'Kabitab_Story_Engagement', 'record_story_view' ) );
add_action( 'wp_enqueue_scripts', array( 'Kabitab_Story_Engagement', 'enqueue_assets' ) );
