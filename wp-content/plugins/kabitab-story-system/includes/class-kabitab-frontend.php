<?php
/**
 * Frontend template loading for KABITAB Story System content.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

class Kabitab_Frontend {
	/**
	 * Prevents homepage section links from all appearing active at once.
	 *
	 * WordPress treats /#write, /#categories, and /#about as the current home
	 * page. Only the actual Home item should receive the persistent current state.
	 *
	 * @param string[] $classes Menu item classes.
	 * @param WP_Post  $item    Menu item.
	 * @return string[]
	 */
	public static function clean_home_anchor_menu_classes( $classes, $item ) {
		if ( ! is_front_page() || ! $item instanceof WP_Post ) {
			return $classes;
		}

		$url = isset( $item->url ) ? (string) $item->url : '';
		if ( '' === (string) wp_parse_url( $url, PHP_URL_FRAGMENT ) ) {
			return $classes;
		}

		return array_values(
			array_filter(
				$classes,
				static function ( $class_name ) {
					return ! in_array( $class_name, array( 'current-menu-item', 'current_page_item', 'current-menu-ancestor', 'current_page_ancestor' ), true );
				}
			)
		);
	}

	/**
	 * Turns the visual genre cards on the home page into useful discovery links.
	 *
	 * The original page was built before the live discovery grid existed, so every
	 * category card pointed at the same unfiltered Explore page. Keeping the page
	 * content intact, this adds a small, human-readable topic to those links. The
	 * discovery grid then uses that topic to start with a relevant search.
	 *
	 * @param string $content Rendered page content.
	 * @return string
	 */
	public static function link_home_category_cards( $content ) {
		if ( ! is_front_page() || false === strpos( $content, 'kabitab-home__category-card' ) ) {
			return $content;
		}

		$topics = array(
			'प्रेम'   => 'romance',
			'रहस्य'  => 'mystery',
			'कथा'    => 'stories',
			'कविता'  => 'कविता',
			'उपन्यास' => 'novel',
			'गजल'    => 'gazal',
			'जीवन'   => 'life',
			'प्रेरणा' => 'motivation',
			'समाज'   => 'social',
		);

		return (string) preg_replace_callback(
			'/<a\b(?P<attributes>[^>]*\bkabitab-home__category-card\b[^>]*)>(?P<label>.*?)<\/a>/is',
			static function ( $match ) use ( $topics ) {
				$label = wp_strip_all_tags( $match['label'], true );
				$topic = '';
				foreach ( $topics as $needle => $value ) {
					if ( false !== strpos( $label, $needle ) ) {
						$topic = $value;
						break;
					}
				}

				if ( '' === $topic ) {
					return $match[0];
				}

				$url        = add_query_arg( 'topic', $topic, home_url( '/explore/' ) );
				$attributes = (string) preg_replace( '/\shref=("|\').*?\1/i', ' href="' . esc_url( $url ) . '"', $match['attributes'] );

				return '<a' . $attributes . '>' . $match['label'] . '</a>';
			},
			$content
		);
	}

	/**
	 * Adds the account route to Astra's mobile menu without duplicating desktop UI.
	 *
	 * @param string   $items Rendered navigation items.
	 * @param stdClass $args  Navigation arguments.
	 * @return string
	 */
	public static function add_mobile_account_menu_item( $items, $args ) {
		if ( is_admin() || empty( $args->theme_location ) || ! in_array( $args->theme_location, array( 'primary', 'mobile_menu' ), true ) ) {
			return $items;
		}

		$url   = is_user_logged_in() ? home_url( '/account/' ) : wp_login_url( home_url( '/account/' ) );
		$label = is_user_logged_in() ? __( 'My Account', 'kabitab-story-system' ) : ( get_option( 'users_can_register' ) ? __( 'Login / Sign Up', 'kabitab-story-system' ) : __( 'Login', 'kabitab-story-system' ) );

		return $items . sprintf(
			'<li class="menu-item kabitab-mobile-account-menu-item"><a class="menu-link" href="%1$s">%2$s</a></li>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	/**
	 * Prevents a legacy WPUF edit shortcode from echoing HTML before REST JSON.
	 *
	 * The shortcode still renders normally on the public Edit page; only its REST
	 * representation is replaced because the third-party callback echoes markup.
	 *
	 * @param string $content Page content before shortcode expansion.
	 * @return string
	 */
	public static function protect_rest_page_content( $content ) {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST || false === stripos( $content, '[wpuf_edit' ) ) {
			return $content;
		}

		return (string) preg_replace(
			'/\[wpuf_edit(?:\s[^\]]*)?\]/i',
			'<p>' . esc_html__( 'The post editing form is available on the website.', 'kabitab-story-system' ) . '</p>',
			$content
		);
	}

	/**
	 * Loads plugin-owned templates for reusable Story System content.
	 *
	 * @param string $template Resolved WordPress template path.
	 * @return string
	 */
	public static function load_template( $template ) {
		if ( is_singular( 'kabitab_story' ) ) {
			$candidate = KABITAB_STORY_SYSTEM_PATH . 'templates/single-kabitab_story.php';
			return file_exists( $candidate ) ? $candidate : $template;
		}

		if ( is_singular( 'kabitab_chapter' ) ) {
			$candidate = KABITAB_STORY_SYSTEM_PATH . 'templates/single-kabitab_chapter.php';
			return file_exists( $candidate ) ? $candidate : $template;
		}

		return $template;
	}

	/**
	 * Sends duplicate legacy Pages to their canonical Story System single URLs.
	 *
	 * This preserves the original Pages and limits redirects to exact slug
	 * collisions with a published KABITAB Story or Chapter.
	 */
	public static function redirect_legacy_reader_pages() {
		if ( is_admin() || ! is_page() || ! is_singular() ) {
			return;
		}

		$page = get_queried_object();
		if ( ! $page instanceof WP_Post || 'page' !== $page->post_type || 'publish' !== $page->post_status || '' === $page->post_name ) {
			return;
		}

		/*
		 * Keep the established Story Detail URL public. Home and Explore already
		 * point readers to /adhuro-prem/, so redirecting that legacy Page makes
		 * the story appear to disappear whenever the newer Story System view is
		 * unavailable. Only legacy reader-Part URLs are canonicalized here.
		 */
		foreach ( array( 'kabitab_chapter' ) as $post_type ) {
			$reader_post = get_page_by_path( $page->post_name, OBJECT, $post_type );
			if ( ! $reader_post instanceof WP_Post || 'publish' !== $reader_post->post_status ) {
				continue;
			}

			$canonical_url = get_permalink( $reader_post );
			if ( is_string( $canonical_url ) && '' !== $canonical_url ) {
				wp_safe_redirect( $canonical_url, 301, 'KABITAB Story System' );
				exit;
			}
		}
	}

	/**
	 * Enqueues the scoped frontend stylesheet only for Story System singles.
	 */
	public static function enqueue_assets() {
		$site_stylesheet = KABITAB_STORY_SYSTEM_PATH . 'assets/css/site-ui.css';
		if ( file_exists( $site_stylesheet ) ) {
			wp_enqueue_style( 'kabitab-site-ui', KABITAB_STORY_SYSTEM_URL . 'assets/css/site-ui.css', array(), (string) filemtime( $site_stylesheet ) );
		}

		if ( ! is_singular( array( 'kabitab_story', 'kabitab_chapter' ) ) ) {
			return;
		}

		$stylesheet = KABITAB_STORY_SYSTEM_PATH . 'assets/css/frontend.css';
		$version    = file_exists( $stylesheet ) ? (string) filemtime( $stylesheet ) : KABITAB_STORY_SYSTEM_VERSION;
		wp_enqueue_style( 'kabitab-story-system-frontend', KABITAB_STORY_SYSTEM_URL . 'assets/css/frontend.css', array(), $version );

		$share_script = KABITAB_STORY_SYSTEM_PATH . 'assets/js/share-controls.js';
		if ( file_exists( $share_script ) ) {
			wp_enqueue_script( 'kabitab-share-controls', KABITAB_STORY_SYSTEM_URL . 'assets/js/share-controls.js', array(), (string) filemtime( $share_script ), true );
			wp_script_add_data( 'kabitab-share-controls', 'strategy', 'defer' );
		}

		if ( is_singular( 'kabitab_chapter' ) ) {
			$chapter_id       = get_queried_object_id();
			$story_id         = kabitab_get_chapter_parent_story_id( $chapter_id );
			$reading_progress = is_user_logged_in() ? Kabitab_Reading_Progress::get_for_story( $story_id ) : array();
			$resume_requested = isset( $_GET['kabitab_resume'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['kabitab_resume'] ) );
			$resume_percent   = ! empty( $reading_progress ) && $chapter_id === absint( $reading_progress['chapter_id'] ) ? absint( $reading_progress['percent'] ) : 0;
			$script         = KABITAB_STORY_SYSTEM_PATH . 'assets/js/reader-ui.js';
			$script_version = file_exists( $script ) ? (string) filemtime( $script ) : KABITAB_STORY_SYSTEM_VERSION;
			wp_enqueue_script( 'kabitab-reader-ui', KABITAB_STORY_SYSTEM_URL . 'assets/js/reader-ui.js', array(), $script_version, true );
			wp_script_add_data( 'kabitab-reader-ui', 'strategy', 'defer' );
			wp_localize_script(
				'kabitab-reader-ui',
				'kabitabReaderProgress',
				array(
					'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( 'kabitab_reading_progress' ),
					'loggedIn'  => is_user_logged_in(),
					'chapterId'       => $chapter_id,
					'storyId'         => $story_id,
					'resumeRequested' => $resume_requested,
					'resumePercent'   => $resume_percent,
				)
			);
		}
	}

	/**
	 * Removes front-end bundles that the self-contained reader templates do not use.
	 *
	 * The active theme remains intact so its header and footer continue to work.
	 * Only assets owned by unrelated feature plugins are removed, and only on
	 * the self-contained KABITAB reader, discovery, writers, and dashboard views.
	 */
	public static function dequeue_unneeded_assets() {
		if ( ! is_front_page() && ! is_page( array( 'explore', 'writers', 'dashboard' ) ) && ! is_singular( array( 'kabitab_story', 'kabitab_chapter' ) ) ) {
			return;
		}

		global $wp_styles, $wp_scripts;

		$blocked_sources = array(
			'/wp-content/plugins/easy-digital-downloads/',
			'/wp-content/plugins/elementor/',
			'/wp-content/plugins/favorites/',
			'/wp-content/plugins/spectra-blocks/',
			'/wp-content/plugins/sureforms/',
			'/wp-content/plugins/wp-user-frontend/',
		);
		$blocked_prefixes = array(
			'edd',
			'elementor',
			'favorites',
			'simple-favorites',
			'spectra',
			'srfm',
			'sureforms',
			'uagb',
			'wpuf',
		);

		/* Story Detail and the dashboard library render Favorites controls. */
		if ( is_singular( 'kabitab_story' ) || is_page( 'dashboard' ) ) {
			$blocked_sources  = array_values( array_diff( $blocked_sources, array( '/wp-content/plugins/favorites/' ) ) );
			$blocked_prefixes = array_values( array_diff( $blocked_prefixes, array( 'favorites', 'simple-favorites' ) ) );
		}

		self::dequeue_matching_assets( $wp_styles, 'style', $blocked_sources, $blocked_prefixes );
		self::dequeue_matching_assets( $wp_scripts, 'script', $blocked_sources, $blocked_prefixes );
	}

	/**
	 * Removes the redundant legacy post list from the unified KABITAB Dashboard.
	 *
	 * The focused writer workspace and reader library remain on the page. The
	 * original WPUF shortcode and page content are not deleted from WordPress.
	 *
	 * @param string $content Page content before shortcode expansion.
	 * @return string
	 */
	public static function prepare_dashboard_content( $content ) {
		if ( ! is_page( 'dashboard' ) || false === stripos( $content, '[wpuf_dashboard' ) ) {
			return $content;
		}

		return (string) preg_replace( '/\[wpuf_dashboard(?:\s[^\]]*)?\]/i', '', $content );
	}

	/**
	 * Dequeues matching registered assets without deregistering shared handles.
	 *
	 * @param WP_Dependencies|null $dependencies     Registered dependency manager.
	 * @param string               $asset_type       style or script.
	 * @param string[]             $blocked_sources  URL fragments to remove.
	 * @param string[]             $blocked_prefixes Handle prefixes to remove.
	 */
	private static function dequeue_matching_assets( $dependencies, $asset_type, $blocked_sources, $blocked_prefixes ) {
		if ( ! $dependencies instanceof WP_Dependencies ) {
			return;
		}

		foreach ( array_values( $dependencies->queue ) as $handle ) {
			if ( 'kabitab-story-system-frontend' === $handle ) {
				continue;
			}

			$source  = isset( $dependencies->registered[ $handle ] ) ? (string) $dependencies->registered[ $handle ]->src : '';
			$blocked = false;

			foreach ( $blocked_sources as $fragment ) {
				if ( '' !== $source && false !== strpos( $source, $fragment ) ) {
					$blocked = true;
					break;
				}
			}

			if ( ! $blocked ) {
				foreach ( $blocked_prefixes as $prefix ) {
					if ( 0 === strpos( $handle, $prefix ) ) {
						$blocked = true;
						break;
					}
				}
			}

			if ( ! $blocked ) {
				continue;
			}

			if ( 'style' === $asset_type ) {
				wp_dequeue_style( $handle );
			} else {
				wp_dequeue_script( $handle );
			}
		}
	}

	/**
	 * Replaces the legacy placeholder Explore composition with live Story data.
	 *
	 * @param string $content Rendered page content.
	 * @return string
	 */
	public static function clean_explore_content( $content ) {
		if ( ! is_page( 'explore' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$story_count   = (int) wp_count_posts( 'kabitab_story' )->publish;
		$chapter_count = (int) wp_count_posts( 'kabitab_chapter' )->publish;
		$account_url   = is_user_logged_in() ? home_url( '/account/' ) : wp_login_url( home_url( '/account/' ) );
		$account_label = is_user_logged_in() ? __( 'Open your library', 'kabitab-story-system' ) : __( 'Sign in to save stories', 'kabitab-story-system' );

		ob_start();
		?>
		<div class="kabitab-live-explore">
			<section class="kabitab-live-explore__hero" aria-labelledby="kabitab-explore-title">
				<div>
					<p class="kabitab-live-explore__eyebrow"><?php esc_html_e( 'Story discovery', 'kabitab-story-system' ); ?></p>
					<h1 id="kabitab-explore-title"><?php esc_html_e( 'Explore Nepali stories', 'kabitab-story-system' ); ?></h1>
				</div>
				<div class="kabitab-live-explore__intro">
					<p><?php esc_html_e( 'Discover published poems and stories from real KABITAB writers. Search by title, writer, or genre.', 'kabitab-story-system' ); ?></p>
					<div class="kabitab-live-explore__facts" aria-label="<?php esc_attr_e( 'Published library totals', 'kabitab-story-system' ); ?>">
						<span><strong><?php echo esc_html( number_format_i18n( $story_count ) ); ?></strong> <?php esc_html_e( 'published stories', 'kabitab-story-system' ); ?></span>
						<span><strong><?php echo esc_html( number_format_i18n( $chapter_count ) ); ?></strong> <?php esc_html_e( 'chapters', 'kabitab-story-system' ); ?></span>
					</div>
				</div>
			</section>

			<?php if ( Kabitab_Story_Grid_Shortcode::has_editor_picks() ) : ?>
				<section class="kabitab-live-explore__library kabitab-live-explore__library--picks" aria-labelledby="kabitab-editor-picks-title">
					<div class="kabitab-live-explore__section-heading">
						<p><?php esc_html_e( 'Curated by KABITAB', 'kabitab-story-system' ); ?></p>
						<h2 id="kabitab-editor-picks-title"><?php esc_html_e( 'Editor’s picks', 'kabitab-story-system' ); ?></h2>
					</div>
					<?php echo do_shortcode( '[kabitab_story_grid context="explore" collection="editor_picks" limit="6" show_filters="false"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</section>
			<?php endif; ?>

			<?php if ( Kabitab_Story_Grid_Shortcode::has_trending_stories() ) : ?>
				<section class="kabitab-live-explore__library kabitab-live-explore__library--trending" aria-labelledby="kabitab-trending-title">
					<div class="kabitab-live-explore__section-heading">
						<p><?php esc_html_e( 'Reader momentum', 'kabitab-story-system' ); ?></p>
						<h2 id="kabitab-trending-title"><?php esc_html_e( 'Trending on KABITAB', 'kabitab-story-system' ); ?></h2>
					</div>
					<?php echo do_shortcode( '[kabitab_story_grid context="explore" collection="trending" limit="6" show_filters="false"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</section>
			<?php endif; ?>

			<section class="kabitab-live-explore__library" aria-labelledby="kabitab-live-library-title">
				<div class="kabitab-live-explore__section-heading">
					<p><?php esc_html_e( 'Published now', 'kabitab-story-system' ); ?></p>
					<h2 id="kabitab-live-library-title"><?php esc_html_e( 'Read what is actually on KABITAB', 'kabitab-story-system' ); ?></h2>
				</div>
				<?php echo do_shortcode( '[kabitab_story_grid context="explore" show_filters="true"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</section>

			<section class="kabitab-live-explore__cta" aria-labelledby="kabitab-explore-cta-title">
				<div>
					<p class="kabitab-live-explore__eyebrow"><?php esc_html_e( 'A consistent reading experience', 'kabitab-story-system' ); ?></p>
					<h2 id="kabitab-explore-cta-title"><?php esc_html_e( 'Save stories, follow writers, and continue where you stopped.', 'kabitab-story-system' ); ?></h2>
				</div>
				<div class="kabitab-live-explore__actions">
					<a class="kabitab-live-explore__primary" href="<?php echo esc_url( $account_url ); ?>"><?php echo esc_html( $account_label ); ?></a>
					<a href="<?php echo esc_url( home_url( '/writers/' ) ); ?>"><?php esc_html_e( 'Meet the writers', 'kabitab-story-system' ); ?></a>
				</div>
			</section>

			<footer class="kabitab-live-explore__footer" aria-label="<?php esc_attr_e( 'KABITAB Explore Footer', 'kabitab-story-system' ); ?>">
				<div><h2>KABITAB / कविताब</h2><p><?php esc_html_e( 'Nepal’s reader–writer community for stories, poems, novels, gazals, and new voices.', 'kabitab-story-system' ); ?></p></div>
				<nav aria-label="<?php esc_attr_e( 'Explore footer navigation', 'kabitab-story-system' ); ?>">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'kabitab-story-system' ); ?></a>
					<a href="<?php echo esc_url( home_url( '/explore/' ) ); ?>"><?php esc_html_e( 'Explore', 'kabitab-story-system' ); ?></a>
					<a href="<?php echo esc_url( home_url( '/#write' ) ); ?>"><?php esc_html_e( 'Write', 'kabitab-story-system' ); ?></a>
					<a href="<?php echo esc_url( home_url( '/writers/' ) ); ?>"><?php esc_html_e( 'Writers', 'kabitab-story-system' ); ?></a>
					<a href="<?php echo esc_url( home_url( '/#about' ) ); ?>"><?php esc_html_e( 'About', 'kabitab-story-system' ); ?></a>
					<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact', 'kabitab-story-system' ); ?></a>
				</nav>
				<p class="kabitab-live-explore__copyright"><?php echo esc_html( sprintf( '© %d KABITAB. All rights reserved.', (int) gmdate( 'Y' ) ) ); ?></p>
			</footer>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Adds reader-specific selectors while preserving the active theme's body classes.
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public static function add_body_classes( $classes ) {
		if ( ! get_option( 'users_can_register' ) ) {
			$classes[] = 'kabitab-registration-closed';
		}

		if ( is_singular( 'kabitab_story' ) ) {
			$classes[] = 'kabitab-story-system-page';
			$classes[] = 'kabitab-story-detail';
		}

		if ( is_singular( 'kabitab_chapter' ) ) {
			$classes[] = 'kabitab-story-system-page';
			$classes[] = 'kabitab-chapter-reader';
		}

		return array_unique( $classes );
	}

	/**
	 * Keeps the account dashboard out of public search results.
	 *
	 * The Dashboard is a logged-in reader and writer workspace rather than
	 * discoverable content. Keep following enabled so normal internal-link
	 * discovery is unaffected when a crawler encounters the URL.
	 *
	 * @param array<string, bool|string> $robots Existing robots directives.
	 * @return array<string, bool|string>
	 */
	public static function discourage_dashboard_indexing( $robots ) {
		if ( ! is_page( 'dashboard' ) ) {
			return $robots;
		}

		$robots['noindex'] = true;
		unset( $robots['nofollow'] );

		return $robots;
	}

	/**
	 * Applies Dashboard indexing protection when Rank Math controls robots meta.
	 *
	 * @param array<string, string> $robots Existing Rank Math directives.
	 * @return array<string, string>
	 */
	public static function discourage_dashboard_rank_math_indexing( $robots ) {
		if ( ! is_page( 'dashboard' ) ) {
			return $robots;
		}

		$robots['index']  = 'noindex';
		$robots['follow'] = 'follow';

		return $robots;
	}

	/**
	 * Returns an optimized Story cover with a WebP source when a matching local
	 * derivative exists. The original WordPress image remains inside the picture
	 * element as a safe fallback for older browsers and for incomplete uploads.
	 *
	 * @param int                    $post_id    Story post ID.
	 * @param string|array<int, int> $size       Registered image size.
	 * @param array<string, string>  $attributes Image attributes.
	 * @return string
	 */
	public static function get_story_cover_markup( $post_id, $size = 'kabitab-story-card', $attributes = array() ) {
		$thumbnail = get_the_post_thumbnail( $post_id, $size, $attributes );
		if ( '' === $thumbnail ) {
			return '';
		}

		$attachment_id = get_post_thumbnail_id( $post_id );
		$image         = $attachment_id ? wp_get_attachment_image_src( $attachment_id, $size ) : false;
		if ( ! is_array( $image ) || empty( $image[0] ) ) {
			return $thumbnail;
		}

		$image_url = (string) $image[0];
		$webp_url  = (string) preg_replace( '/\.(?:png|jpe?g)$/i', '.webp', $image_url );
		if ( $webp_url === $image_url ) {
			return $thumbnail;
		}

		$uploads  = wp_get_upload_dir();
		$base_url = isset( $uploads['baseurl'] ) ? trailingslashit( (string) $uploads['baseurl'] ) : '';
		$base_dir = isset( $uploads['basedir'] ) ? trailingslashit( (string) $uploads['basedir'] ) : '';
		if ( '' === $base_url || '' === $base_dir || 0 !== strpos( $image_url, $base_url ) ) {
			return $thumbnail;
		}

		$relative_path = ltrim( substr( $image_url, strlen( $base_url ) ), '/' );
		$webp_path     = (string) preg_replace( '/\.(?:png|jpe?g)$/i', '.webp', $base_dir . $relative_path );
		if ( ! is_readable( $webp_path ) ) {
			return $thumbnail;
		}

		return sprintf( '<picture><source type="image/webp" srcset="%1$s" />%2$s</picture>', esc_url( $webp_url ), $thumbnail );
	}

	/**
	 * Gets the public-facing author name for a Story System post.
	 *
	 * This display-only mapping keeps WordPress account data and post ownership
	 * unchanged while allowing KABITAB-specific pen names on Story System pages.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_author_label( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$author_source = $post;
		if ( 'kabitab_chapter' === $post->post_type ) {
			$story_id = kabitab_get_chapter_parent_story_id( $post->ID );
			$story    = $story_id ? get_post( $story_id ) : null;
			if ( $story instanceof WP_Post && 'kabitab_story' === $story->post_type ) {
				$author_source = $story;
			}
		}

		if ( ! $author_source->post_author ) {
			return '';
		}

		$pen_name = get_post_meta( $author_source->ID, '_kabitab_public_author_name', true );
		$pen_name = is_string( $pen_name ) ? trim( $pen_name ) : '';
		if ( '' !== $pen_name ) {
			return $pen_name;
		}

		$name = get_the_author_meta( 'display_name', (int) $author_source->post_author );
		$name = is_string( $name ) ? trim( $name ) : '';

		return $name;
	}
}
