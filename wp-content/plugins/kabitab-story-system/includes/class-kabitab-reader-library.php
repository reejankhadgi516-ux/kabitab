<?php
/**
 * Saved-story library powered by the existing Favorites plugin.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

class Kabitab_Reader_Library {
	public static function register() {
		add_shortcode( 'kabitab_reader_library', array( __CLASS__, 'render' ) );
	}

	public static function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}
		global $post;
		if ( ! $post instanceof WP_Post || ! has_shortcode( (string) $post->post_content, 'kabitab_reader_library' ) ) {
			return;
		}
		$stylesheet = KABITAB_STORY_SYSTEM_PATH . 'assets/css/reader-library.css';
		if ( file_exists( $stylesheet ) ) {
			wp_enqueue_style( 'kabitab-reader-library', KABITAB_STORY_SYSTEM_URL . 'assets/css/reader-library.css', array(), (string) filemtime( $stylesheet ) );
		}
		$script = KABITAB_STORY_SYSTEM_PATH . 'assets/js/reader-library.js';
		if ( file_exists( $script ) ) {
			wp_enqueue_script( 'kabitab-reader-library', KABITAB_STORY_SYSTEM_URL . 'assets/js/reader-library.js', array(), (string) filemtime( $script ), true );
			wp_script_add_data( 'kabitab-reader-library', 'strategy', 'defer' );
		}
	}

	public static function render() {
		if ( is_page( 'dashboard' ) && isset( $_GET['writer_action'] ) ) {
			return '';
		}

		if ( ! is_user_logged_in() ) {
			// The writer dashboard already presents the primary sign-in action. Avoid
			// repeating a second, weaker notice immediately beneath it.
			if ( is_page( 'dashboard' ) ) {
				return '';
			}

			return '<section class="kabitab-reader-library"><p>' . esc_html__( 'Sign in to view your saved stories.', 'kabitab-story-system' ) . '</p></section>';
		}
		if ( ! shortcode_exists( 'user_favorites' ) ) {
			return '<section class="kabitab-reader-library"><p>' . esc_html__( 'Saved stories are temporarily unavailable.', 'kabitab-story-system' ) . '</p></section>';
		}

		$continue_reading = self::get_continue_reading();
		ob_start();
		?>
		<section class="kabitab-reader-library" aria-labelledby="kabitab-reader-library-title">
			<header>
				<p class="kabitab-reader-library__eyebrow"><?php esc_html_e( 'Reader library', 'kabitab-story-system' ); ?></p>
				<h2 id="kabitab-reader-library-title"><?php esc_html_e( 'Your reading space', 'kabitab-story-system' ); ?></h2>
				<p><?php esc_html_e( 'Resume a chapter, revisit saved stories, and keep track of writers you follow.', 'kabitab-story-system' ); ?></p>
			</header>
			<?php if ( ! empty( $continue_reading ) ) : ?>
				<section class="kabitab-reader-library__continue" aria-labelledby="kabitab-continue-reading-title">
					<h3 id="kabitab-continue-reading-title"><?php esc_html_e( 'Continue reading', 'kabitab-story-system' ); ?></h3>
					<div class="kabitab-reader-library__continue-grid">
						<?php foreach ( $continue_reading as $item ) : ?>
							<article>
								<p><?php echo esc_html( get_the_title( $item['story'] ) ); ?></p>
								<h4><?php echo esc_html( get_the_title( $item['chapter'] ) ); ?></h4>
								<div class="kabitab-reader-library__progress" role="progressbar" aria-label="<?php esc_attr_e( 'Reading progress', 'kabitab-story-system' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $item['percent'] ); ?>"><span style="width:<?php echo esc_attr( $item['percent'] ); ?>%"></span></div>
								<div><small><?php echo esc_html( sprintf( __( '%d%% complete', 'kabitab-story-system' ), $item['percent'] ) ); ?></small><a href="<?php echo esc_url( add_query_arg( 'kabitab_resume', '1', get_permalink( $item['chapter'] ) ) ); ?>"><?php esc_html_e( 'Continue', 'kabitab-story-system' ); ?></a></div>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
			<?php echo do_shortcode( '[kabitab_notifications]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo Kabitab_Reading_Lists::render_library(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h3 class="kabitab-reader-library__saved-title"><?php esc_html_e( 'Saved stories', 'kabitab-story-system' ); ?></h3>
			<div class="kabitab-reader-library__saved-tools">
				<label for="kabitab-saved-story-search"><?php esc_html_e( 'Search saved stories', 'kabitab-story-system' ); ?></label>
				<input id="kabitab-saved-story-search" type="search" data-kabitab-saved-search placeholder="<?php esc_attr_e( 'Search by title', 'kabitab-story-system' ); ?>" autocomplete="off" />
				<p data-kabitab-saved-result role="status" aria-live="polite"></p>
			</div>
			<div class="kabitab-reader-library__items" data-kabitab-saved-items>
				<?php echo do_shortcode( '[user_favorites post_types="kabitab_story" include_links="true" include_buttons="true" include_thumbnails="true" include_excerpts="false" no_favorites="No saved stories yet."]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php echo do_shortcode( '[kabitab_followed_writers]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private static function get_continue_reading() {
		$stories = get_posts(
			array(
				'post_type'      => 'kabitab_story',
				'post_status'    => 'publish',
				'posts_per_page' => 8,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		$items = array();
		foreach ( $stories as $story ) {
			$progress = Kabitab_Reading_Progress::get_for_story( $story->ID );
			if ( empty( $progress ) ) {
				continue;
			}
			$chapter = get_post( $progress['chapter_id'] );
			if ( ! $chapter instanceof WP_Post ) {
				continue;
			}
			$items[] = array(
				'story'    => $story,
				'chapter'  => $chapter,
				'percent'  => absint( $progress['percent'] ),
				'updated'  => absint( $progress['updated_at'] ),
			);
		}
		usort( $items, static function ( $a, $b ) { return $b['updated'] <=> $a['updated']; } );
		return array_slice( $items, 0, 4 );
	}
}
