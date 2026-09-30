<?php
/**
 * Reader-facing writer discovery and private follow controls.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

class Kabitab_Writer_Following {
	const META_KEY = '_kabitab_followed_writers';

	public static function register() {
		add_shortcode( 'kabitab_writer_directory', array( __CLASS__, 'render_directory' ) );
		add_shortcode( 'kabitab_follow_writer', array( __CLASS__, 'render_follow_control' ) );
		add_shortcode( 'kabitab_followed_writers', array( __CLASS__, 'render_followed_writers' ) );
		add_action( 'wp_ajax_kabitab_toggle_writer_follow', array( __CLASS__, 'toggle_follow' ) );
	}

	public static function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		global $post;
		$uses_following = is_singular( 'kabitab_story' );
		if ( $post instanceof WP_Post ) {
			$content        = (string) $post->post_content;
			$uses_following = $uses_following || has_shortcode( $content, 'kabitab_writer_directory' ) || has_shortcode( $content, 'kabitab_followed_writers' ) || has_shortcode( $content, 'kabitab_reader_library' );
		}

		if ( ! $uses_following ) {
			return;
		}

		$style = KABITAB_STORY_SYSTEM_PATH . 'assets/css/writer-following.css';
		if ( file_exists( $style ) ) {
			wp_enqueue_style( 'kabitab-writer-following', KABITAB_STORY_SYSTEM_URL . 'assets/css/writer-following.css', array(), (string) filemtime( $style ) );
		}

		$script = KABITAB_STORY_SYSTEM_PATH . 'assets/js/writer-following.js';
		if ( file_exists( $script ) && is_user_logged_in() ) {
			wp_enqueue_script( 'kabitab-writer-following', KABITAB_STORY_SYSTEM_URL . 'assets/js/writer-following.js', array(), (string) filemtime( $script ), true );
			wp_localize_script(
				'kabitab-writer-following',
				'kabitabWriterFollowing',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'error'   => __( 'We could not update that follow right now. Please try again.', 'kabitab-story-system' ),
				)
			);
		}
	}

	public static function render_directory() {
		$writers = self::get_story_writers();
		$total_stories = 0;
		foreach ( $writers as $writer ) {
			$total_stories += count_user_posts( $writer->ID, 'kabitab_story', true );
		}
		$write_url = is_user_logged_in() ? home_url( '/dashboard/' ) : wp_login_url( home_url( '/dashboard/' ) );
		ob_start();
		?>
		<section class="kabitab-writer-directory" aria-labelledby="kabitab-writer-directory-title">
			<header class="kabitab-writer-directory__header">
				<div>
					<p class="kabitab-writer-directory__eyebrow"><?php esc_html_e( 'KABITAB writers', 'kabitab-story-system' ); ?></p>
					<h1 id="kabitab-writer-directory-title"><?php esc_html_e( 'Meet the voices behind the stories', 'kabitab-story-system' ); ?></h1>
					<p><?php esc_html_e( 'Open a writer’s published work, follow the voices you enjoy, and return when their next chapter arrives.', 'kabitab-story-system' ); ?></p>
				</div>
				<div class="kabitab-writer-directory__actions">
					<a class="kabitab-writer-directory__primary" href="<?php echo esc_url( $write_url ); ?>"><?php esc_html_e( 'Start writing', 'kabitab-story-system' ); ?></a>
					<a href="<?php echo esc_url( home_url( '/explore/' ) ); ?>"><?php esc_html_e( 'Explore stories', 'kabitab-story-system' ); ?></a>
				</div>
			</header>
			<p class="kabitab-writer-directory__summary"><?php echo esc_html( sprintf( _n( '%1$d writer · %2$d published story', '%1$d writers · %2$d published stories', count( $writers ), 'kabitab-story-system' ), count( $writers ), $total_stories ) ); ?></p>

			<?php if ( empty( $writers ) ) : ?>
				<p class="kabitab-writer-directory__empty"><?php esc_html_e( 'Writer profiles will appear here as stories are published.', 'kabitab-story-system' ); ?></p>
			<?php else : ?>
				<div class="kabitab-writer-directory__grid">
					<?php foreach ( $writers as $writer ) : ?>
						<?php
						$stories     = self::get_writer_stories( $writer->ID );
						$story_count = count_user_posts( $writer->ID, 'kabitab_story', true );
						$latest      = ! empty( $stories ) ? $stories[0] : null;
						$public_name = self::get_public_writer_name( $writer, $latest );
						$initial     = function_exists( 'mb_substr' ) ? mb_substr( $public_name, 0, 1 ) : substr( $public_name, 0, 1 );
						?>
						<article class="kabitab-writer-card">
							<div class="kabitab-writer-card__identity">
								<span class="kabitab-writer-card__monogram" aria-hidden="true"><?php echo esc_html( $initial ); ?></span>
								<div>
									<h2><?php echo esc_html( $public_name ); ?></h2>
									<p><?php echo esc_html( sprintf( _n( '%d published story', '%d published stories', $story_count, 'kabitab-story-system' ), $story_count ) ); ?></p>
								</div>
							</div>
							<?php if ( ! empty( $stories ) ) : ?>
								<ul class="kabitab-writer-card__stories" aria-label="<?php echo esc_attr( sprintf( __( 'Published stories by %s', 'kabitab-story-system' ), $public_name ) ); ?>">
									<?php foreach ( $stories as $story ) : ?>
										<li><a href="<?php echo esc_url( get_permalink( $story ) ); ?>"><span><?php echo esc_html( get_the_title( $story ) ); ?></span><small><?php echo esc_html( sprintf( _n( '%d chapter', '%d chapters', kabitab_get_published_chapter_count( $story->ID ), 'kabitab-story-system' ), kabitab_get_published_chapter_count( $story->ID ) ) ); ?></small></a></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
							<?php echo self::render_follow_control( array( 'author_id' => $writer->ID ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function render_follow_control( $attributes = array() ) {
		$attributes = shortcode_atts( array( 'author_id' => 0 ), $attributes, 'kabitab_follow_writer' );
		$author_id  = absint( $attributes['author_id'] );
		if ( ! $author_id ) {
			$author_id = (int) get_post_field( 'post_author', get_the_ID() );
		}

		$writer = get_user_by( 'id', $author_id );
		if ( ! $writer || ( is_user_logged_in() && get_current_user_id() === $author_id ) ) {
			return '';
		}

		if ( ! is_user_logged_in() ) {
			$redirect = is_singular() ? get_permalink() : home_url( '/writers/' );
			$url      = wp_login_url( $redirect );
			return '<a class="kabitab-follow-control kabitab-follow-control--login" href="' . esc_url( $url ) . '">' . esc_html__( 'Sign in to follow', 'kabitab-story-system' ) . '</a>';
		}

		$following = self::is_following( get_current_user_id(), $author_id );
		$label     = $following ? __( 'Following', 'kabitab-story-system' ) : __( 'Follow writer', 'kabitab-story-system' );

		return sprintf(
			'<button class="kabitab-follow-control%s" type="button" data-kabitab-follow-writer data-author-id="%d" data-nonce="%s" aria-pressed="%s">%s</button>',
			$following ? ' is-following' : '',
			$author_id,
			esc_attr( wp_create_nonce( 'kabitab_follow_writer_' . $author_id ) ),
			$following ? 'true' : 'false',
			esc_html( $label )
		);
	}

	public static function render_followed_writers() {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$writer_ids = self::get_following( get_current_user_id() );
		if ( empty( $writer_ids ) ) {
			return '';
		}

		$writers = get_users( array( 'include' => $writer_ids, 'orderby' => 'include' ) );
		if ( empty( $writers ) ) {
			return '';
		}

		ob_start();
		?>
		<section class="kabitab-followed-writers" aria-labelledby="kabitab-followed-writers-title">
			<h2 id="kabitab-followed-writers-title"><?php esc_html_e( 'Writers you follow', 'kabitab-story-system' ); ?></h2>
			<ul>
				<?php foreach ( $writers as $writer ) : ?>
					<?php $latest = self::get_latest_story( $writer->ID ); ?>
					<li><?php echo esc_html( self::get_public_writer_name( $writer, $latest ) ); ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function toggle_follow() {
		$author_id = isset( $_POST['author_id'] ) ? absint( wp_unslash( $_POST['author_id'] ) ) : 0;
		check_ajax_referer( 'kabitab_follow_writer_' . $author_id, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please sign in first.', 'kabitab-story-system' ) ), 401 );
		}

		$user_id = get_current_user_id();
		if ( ! $author_id || $user_id === $author_id || ! get_user_by( 'id', $author_id ) ) {
			wp_send_json_error( array( 'message' => __( 'That writer is not available.', 'kabitab-story-system' ) ), 400 );
		}

		$following = self::get_following( $user_id );
		if ( in_array( $author_id, $following, true ) ) {
			$following = array_values( array_diff( $following, array( $author_id ) ) );
			$is_following = false;
		} else {
			$following[]  = $author_id;
			$following    = array_values( array_unique( array_map( 'absint', $following ) ) );
			$is_following = true;
		}

		update_user_meta( $user_id, self::META_KEY, $following );
		wp_send_json_success(
			array(
				'following' => $is_following,
				'label'     => $is_following ? __( 'Following', 'kabitab-story-system' ) : __( 'Follow writer', 'kabitab-story-system' ),
			)
		);
	}

	private static function get_story_writers() {
		$query = new WP_Query(
			array(
				'post_type'      => 'kabitab_story',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$author_ids = array();
		foreach ( $query->posts as $story_id ) {
			$author_id = (int) get_post_field( 'post_author', $story_id );
			if ( $author_id ) {
				$author_ids[] = $author_id;
			}
		}

		$author_ids = array_values( array_unique( $author_ids ) );
		return empty( $author_ids ) ? array() : get_users( array( 'include' => $author_ids, 'orderby' => 'include' ) );
	}

	private static function get_latest_story( $author_id ) {
		$stories = self::get_writer_stories( $author_id, 1 );
		return ! empty( $stories ) ? $stories[0] : null;
	}

	private static function get_writer_stories( $author_id, $limit = 3 ) {
		return get_posts(
			array(
				'author'         => absint( $author_id ),
				'post_type'      => 'kabitab_story',
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, absint( $limit ) ),
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
	}

	private static function get_public_writer_name( $writer, $latest = null ) {
		if ( $latest instanceof WP_Post ) {
			$name = Kabitab_Frontend::get_author_label( $latest->ID );
			if ( '' !== $name ) {
				return $name;
			}
		}
		return $writer instanceof WP_User ? (string) $writer->display_name : '';
	}

	private static function get_following( $user_id ) {
		$following = get_user_meta( absint( $user_id ), self::META_KEY, true );
		$following = is_array( $following ) ? array_map( 'absint', $following ) : array();
		return array_values( array_filter( array_unique( $following ) ) );
	}

	private static function is_following( $user_id, $author_id ) {
		return in_array( absint( $author_id ), self::get_following( $user_id ), true );
	}
}
