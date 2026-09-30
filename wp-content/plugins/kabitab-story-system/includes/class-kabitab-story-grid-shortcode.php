<?php
/**
 * Live Story discovery grid with server-side Explore search and pagination.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

class Kabitab_Story_Grid_Shortcode {
	/**
	 * Editor pick posts are reused when Explore first checks whether the shelf
	 * exists and then renders it during the same request.
	 *
	 * @var WP_Post[]|null
	 */
	private static $editor_pick_stories = null;

	public static function register() {
		add_shortcode( 'kabitab_story_grid', array( __CLASS__, 'render' ) );
	}

	public static function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		global $post;
		if ( is_page( 'explore' ) || ( $post instanceof WP_Post && has_shortcode( (string) $post->post_content, 'kabitab_story_grid' ) ) ) {
			self::enqueue_styles_and_script();
		}
	}

	public static function render( $attributes ) {
		$attributes = shortcode_atts(
			array(
				'context'      => 'explore',
				'limit'        => 24,
				'show_filters' => 'true',
				'collection'   => 'latest',
			),
			$attributes,
			'kabitab_story_grid'
		);

		$context      = in_array( $attributes['context'], array( 'home', 'explore' ), true ) ? $attributes['context'] : 'explore';
		$limit        = max( 1, min( 24, absint( $attributes['limit'] ) ) );
		$show_filters = filter_var( $attributes['show_filters'], FILTER_VALIDATE_BOOLEAN );
		$collection   = in_array( $attributes['collection'], array( 'latest', 'editor_picks', 'trending' ), true ) ? $attributes['collection'] : 'latest';
		$query             = null;
		$stories           = array();
		$search_term       = '';
		$selected_genre    = '';
		$current_page      = 1;
		$is_public_library = ( 'explore' === $context && $show_filters && 'latest' === $collection );

		if ( 'trending' === $collection ) {
			$stories = Kabitab_Story_Engagement::get_trending_stories( $limit );
		} elseif ( 'editor_picks' === $collection ) {
			$stories = self::get_editor_pick_stories( $limit );
		} else {
			if ( $is_public_library ) {
				$search_term    = self::get_request_search_term();
				$selected_genre = self::get_request_genre_slug();
				$current_page   = self::get_request_page();
			}

			$query_args = array(
				'post_type'           => 'kabitab_story',
				'post_status'         => 'publish',
				'posts_per_page'      => $limit,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
			);

			if ( $is_public_library ) {
				$query_args['paged'] = $current_page;

				if ( '' !== $selected_genre ) {
					$query_args['tax_query'] = array(
						array(
							'taxonomy' => 'story_genre',
							'field'    => 'slug',
							'terms'    => $selected_genre,
						),
					);
				}

				if ( '' !== $search_term ) {
					$query_args['post__in'] = self::get_search_story_ids( $search_term, $selected_genre );
				}
			}

			$query   = new WP_Query( $query_args );
			$stories = $query->posts;
		}

		self::enqueue_styles_and_script();

		$genres         = self::get_genres( $stories, $is_public_library );
		$chapter_counts = self::get_published_chapter_counts( $stories );
		$grid_id        = 'kabitab-story-grid-' . wp_rand( 1000, 999999 );
		$total_stories  = $query instanceof WP_Query ? (int) $query->found_posts : count( $stories );

		ob_start();
		?>
		<section id="<?php echo esc_attr( $grid_id ); ?>" class="kabitab-story-grid kabitab-story-grid--<?php echo esc_attr( $context ); ?>" data-kabitab-story-grid<?php echo $is_public_library ? ' data-kabitab-server-grid' : ''; ?>>
			<?php if ( $show_filters ) : ?>
				<?php if ( $is_public_library ) : ?>
					<form class="kabitab-story-grid__tools" action="<?php echo esc_url( self::get_explore_url() ); ?>" method="get" role="search">
						<label class="screen-reader-text" for="<?php echo esc_attr( $grid_id . '-search' ); ?>"><?php esc_html_e( 'Search stories', 'kabitab-story-system' ); ?></label>
						<div class="kabitab-story-grid__search-row">
							<input id="<?php echo esc_attr( $grid_id . '-search' ); ?>" class="kabitab-story-grid__search" type="search" name="kabitab_query" value="<?php echo esc_attr( $search_term ); ?>" placeholder="कथा, लेखक वा विधा खोज्नुहोस्">
							<button class="kabitab-story-grid__submit" type="submit"><?php esc_html_e( 'खोज्नुहोस्', 'kabitab-story-system' ); ?></button>
						</div>
						<div class="kabitab-story-grid__filters" aria-label="<?php esc_attr_e( 'Story genres', 'kabitab-story-system' ); ?>">
							<a class="kabitab-story-grid__filter<?php echo '' === $selected_genre ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::get_explore_url( $search_term ) ); ?>"<?php echo '' === $selected_genre ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'सबै', 'kabitab-story-system' ); ?></a>
							<?php foreach ( $genres as $genre ) : ?>
								<a class="kabitab-story-grid__filter<?php echo $selected_genre === $genre->slug ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::get_explore_url( $search_term, $genre->slug ) ); ?>"<?php echo $selected_genre === $genre->slug ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $genre->name ); ?></a>
							<?php endforeach; ?>
						</div>
						<?php if ( '' !== $search_term || '' !== $selected_genre ) : ?>
							<a class="kabitab-story-grid__clear" href="<?php echo esc_url( self::get_explore_url() ); ?>"><?php esc_html_e( 'खोजी खाली गर्नुहोस्', 'kabitab-story-system' ); ?></a>
						<?php endif; ?>
					</form>
				<?php else : ?>
					<div class="kabitab-story-grid__tools">
						<label class="screen-reader-text" for="<?php echo esc_attr( $grid_id . '-search' ); ?>"><?php esc_html_e( 'Search stories', 'kabitab-story-system' ); ?></label>
						<input id="<?php echo esc_attr( $grid_id . '-search' ); ?>" class="kabitab-story-grid__search" type="search" placeholder="कथा, लेखक वा विधा खोज्नुहोस्" data-kabitab-story-search>
						<div class="kabitab-story-grid__filters" aria-label="<?php esc_attr_e( 'Story genres', 'kabitab-story-system' ); ?>">
							<button class="kabitab-story-grid__filter is-active" type="button" data-kabitab-genre="all" aria-pressed="true"><?php esc_html_e( 'सबै', 'kabitab-story-system' ); ?></button>
							<?php foreach ( $genres as $genre ) : ?>
								<button class="kabitab-story-grid__filter" type="button" data-kabitab-genre="<?php echo esc_attr( $genre->slug ); ?>" aria-pressed="false"><?php echo esc_html( $genre->name ); ?></button>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<p class="kabitab-story-grid__result-count" data-kabitab-result-count aria-live="polite"><?php echo esc_html( sprintf( _n( '%d published story', '%d published stories', $total_stories, 'kabitab-story-system' ), $total_stories ) ); ?></p>
			<div class="kabitab-story-grid__items" data-kabitab-story-items>
				<?php foreach ( $stories as $story ) : ?>
					<?php self::render_card( $story, isset( $chapter_counts[ $story->ID ] ) ? $chapter_counts[ $story->ID ] : 0 ); ?>
				<?php endforeach; ?>
			</div>
			<p class="kabitab-story-grid__empty" data-kabitab-empty<?php echo empty( $stories ) ? '' : ' hidden'; ?>>
				<span><?php esc_html_e( 'यस खोजीसँग मिल्ने प्रकाशित रचना भेटिएन।', 'kabitab-story-system' ); ?></span>
				<?php if ( 'explore' === $context && $show_filters ) : ?>
					<a href="<?php echo esc_url( home_url( '/explore/' ) ); ?>"><?php esc_html_e( 'सबै प्रकाशित रचना हेर्नुहोस्', 'kabitab-story-system' ); ?></a>
				<?php endif; ?>
			</p>
			<?php if ( $is_public_library && $query instanceof WP_Query && $query->max_num_pages > 1 ) : ?>
				<nav class="kabitab-story-grid__pagination" aria-label="<?php esc_attr_e( 'Story pages', 'kabitab-story-system' ); ?>">
					<?php
					$pagination_base = self::get_explore_url( $search_term, $selected_genre, 999999999 );
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => str_replace( '999999999', '%#%', $pagination_base ),
								'format'    => '',
								'current'   => $current_page,
								'total'     => (int) $query->max_num_pages,
								'type'      => 'list',
								'prev_text' => esc_html__( 'अघि', 'kabitab-story-system' ),
								'next_text' => esc_html__( 'पछि', 'kabitab-story-system' ),
							)
						)
					);
					?>
				</nav>
			<?php endif; ?>
		</section>
		<?php
		wp_reset_postdata();
		return (string) ob_get_clean();
	}

	/**
	 * Determines whether the curated Explore shelf should be displayed.
	 *
	 * @return bool
	 */
	public static function has_editor_picks() {
		return ! empty( self::get_editor_pick_stories( 1 ) );
	}

	/**
	 * Returns Editor's Picks from one request-local result set.
	 *
	 * Explore calls this once to decide whether to show the shelf and once to
	 * render it. Reusing the result prevents duplicate database work while
	 * keeping each fresh page request fully up to date.
	 *
	 * @param int $limit Maximum stories to return.
	 * @return WP_Post[]
	 */
	public static function get_editor_pick_stories( $limit = 6 ) {
		$limit = max( 1, min( 24, absint( $limit ) ) );

		if ( null === self::$editor_pick_stories ) {
			self::$editor_pick_stories = get_posts(
				array(
					'post_type'      => 'kabitab_story',
					'post_status'    => 'publish',
					'posts_per_page' => 24,
					'meta_key'       => '_kabitab_is_editor_pick',
					'meta_value'     => 1,
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				)
			);
		}

		return array_slice( self::$editor_pick_stories, 0, $limit );
	}

	/**
	 * Determines whether reader engagement has enough signal for a trending shelf.
	 *
	 * @return bool
	 */
	public static function has_trending_stories() {
		return Kabitab_Story_Engagement::has_trending_stories();
	}

	/**
	 * Reads a reader's Explore search, retaining the existing home-page topic links.
	 *
	 * @return string
	 */
	private static function get_request_search_term() {
		$search_term = '';

		if ( isset( $_GET['kabitab_query'] ) ) {
			$search_term = sanitize_text_field( wp_unslash( $_GET['kabitab_query'] ) );
		} elseif ( isset( $_GET['topic'] ) ) {
			$search_term = sanitize_text_field( wp_unslash( $_GET['topic'] ) );
		}

		$search_term = trim( $search_term );
		return function_exists( 'mb_substr' ) ? mb_substr( $search_term, 0, 100 ) : substr( $search_term, 0, 100 );
	}

	/**
	 * Reads a valid genre filter from the Explore request.
	 *
	 * @return string
	 */
	private static function get_request_genre_slug() {
		if ( ! isset( $_GET['kabitab_genre'] ) ) {
			return '';
		}

		$slug = sanitize_title( wp_unslash( $_GET['kabitab_genre'] ) );
		return term_exists( $slug, 'story_genre' ) ? $slug : '';
	}

	/**
	 * Reads the bounded Explore page number.
	 *
	 * @return int
	 */
	private static function get_request_page() {
		if ( ! isset( $_GET['kabitab_page'] ) ) {
			return 1;
		}

		return min( 10000, max( 1, absint( wp_unslash( $_GET['kabitab_page'] ) ) ) );
	}

	/**
	 * Finds published stories that match a public title, description, author, or genre.
	 *
	 * WordPress's standard keyword query does not include the Story-specific blurb
	 * or public-author fields, so their matching IDs are merged before the paged
	 * query is run. A dedicated search service can replace this only when the
	 * catalogue has enough scale to need one.
	 *
	 * @param string $search_term Search text.
	 * @param string $selected_genre Active genre slug.
	 * @return int[]
	 */
	private static function get_search_story_ids( $search_term, $selected_genre = '' ) {
		$base_args = array(
			'post_type'              => 'kabitab_story',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		$story_ids = array();

		$title_ids = get_posts(
			array_merge(
				$base_args,
				array(
					's' => $search_term,
				)
			)
		);
		$story_ids = array_merge( $story_ids, $title_ids );

		$meta_ids = get_posts(
			array_merge(
				$base_args,
				array(
					'meta_query' => array(
						'relation' => 'OR',
						array(
							'key'     => '_kabitab_blurb',
							'value'   => $search_term,
							'compare' => 'LIKE',
						),
						array(
							'key'     => '_kabitab_public_author_name',
							'value'   => $search_term,
							'compare' => 'LIKE',
						),
					),
				)
			)
		);
		$story_ids = array_merge( $story_ids, $meta_ids );

		$matching_term_ids = get_terms(
			array(
				'taxonomy'   => 'story_genre',
				'hide_empty' => false,
				'fields'     => 'ids',
				'search'     => $search_term,
			)
		);
		$matching_slug = sanitize_title( $search_term );
		if ( '' !== $matching_slug ) {
			$slug_term = get_term_by( 'slug', $matching_slug, 'story_genre' );
			if ( $slug_term instanceof WP_Term ) {
				$matching_term_ids[] = (int) $slug_term->term_id;
			}
		}
		$matching_term_ids = wp_parse_id_list( is_wp_error( $matching_term_ids ) ? array() : $matching_term_ids );
		if ( ! empty( $matching_term_ids ) ) {
			$genre_ids = get_posts(
				array_merge(
					$base_args,
					array(
						'tax_query' => array(
							array(
								'taxonomy' => 'story_genre',
								'field'    => 'term_id',
								'terms'    => $matching_term_ids,
							),
						),
					)
				)
			);
			$story_ids = array_merge( $story_ids, $genre_ids );
		}

		$author_ids = get_users(
			array(
				'search'         => '*' . $search_term . '*',
				'search_columns' => array( 'user_login', 'user_nicename', 'display_name' ),
				'fields'         => 'ID',
			)
		);
		if ( ! empty( $author_ids ) ) {
			$author_story_ids = get_posts(
				array_merge(
					$base_args,
					array(
						'author__in' => wp_parse_id_list( $author_ids ),
					)
				)
			);
			$story_ids = array_merge( $story_ids, $author_story_ids );
		}

		$story_ids = wp_parse_id_list( $story_ids );

		if ( '' !== $selected_genre && ! empty( $story_ids ) ) {
			$story_ids = get_posts(
				array_merge(
					$base_args,
					array(
						'post__in' => $story_ids,
						'tax_query' => array(
							array(
								'taxonomy' => 'story_genre',
								'field'    => 'slug',
								'terms'    => $selected_genre,
							),
						),
					)
				)
			);
		}

		return empty( $story_ids ) ? array( 0 ) : $story_ids;
	}

	/**
	 * Creates the canonical Explore URL for a server-side search state.
	 *
	 * @param string $search_term Search text.
	 * @param string $genre_slug Genre slug.
	 * @param int    $page Page number.
	 * @return string
	 */
	private static function get_explore_url( $search_term = '', $genre_slug = '', $page = 1 ) {
		$args = array();

		if ( '' !== $search_term ) {
			$args['kabitab_query'] = $search_term;
		}
		if ( '' !== $genre_slug ) {
			$args['kabitab_genre'] = $genre_slug;
		}
		if ( $page > 1 ) {
			$args['kabitab_page'] = $page;
		}

		return empty( $args ) ? home_url( '/explore/' ) : add_query_arg( $args, home_url( '/explore/' ) );
	}

	/**
	 * Counts published chapters for all cards in one query rather than one query
	 * per Story. This only exists for the current grid render, so published
	 * counts remain current without a cache invalidation concern.
	 *
	 * @param WP_Post[] $stories Stories displayed in the grid.
	 * @return array<int, int> Counts keyed by Story ID.
	 */
	private static function get_published_chapter_counts( $stories ) {
		$story_ids = wp_parse_id_list( wp_list_pluck( $stories, 'ID' ) );
		$counts    = array_fill_keys( $story_ids, 0 );

		if ( empty( $story_ids ) ) {
			return $counts;
		}

		$chapter_ids = get_posts(
			array(
				'post_type'              => 'kabitab_chapter',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'meta_query'             => array(
					array(
						'key'     => '_kabitab_parent_story',
						'value'   => $story_ids,
						'compare' => 'IN',
					),
				),
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $chapter_ids as $chapter_id ) {
			$story_id = absint( get_post_meta( $chapter_id, '_kabitab_parent_story', true ) );
			if ( isset( $counts[ $story_id ] ) ) {
				++$counts[ $story_id ];
			}
		}

		return $counts;
	}

	private static function get_genres( $stories, $all_public_genres = false ) {
		if ( $all_public_genres ) {
			$genres = get_terms(
				array(
					'taxonomy'   => 'story_genre',
					'hide_empty' => true,
					'orderby'    => 'name',
					'order'      => 'ASC',
				)
			);

			return is_wp_error( $genres ) ? array() : $genres;
		}

		$genres = array();
		foreach ( $stories as $story ) {
			$story_genres = wp_get_post_terms( $story->ID, 'story_genre' );
			if ( is_wp_error( $story_genres ) ) {
				continue;
			}
			foreach ( $story_genres as $genre ) {
				$genres[ $genre->term_id ] = $genre;
			}
		}
		return $genres;
	}

	private static function render_card( $story, $chapter_count = 0 ) {
		$story_id    = $story->ID;
		$genres      = wp_get_post_terms( $story_id, 'story_genre' );
		$genre_names = is_wp_error( $genres ) ? array() : wp_list_pluck( $genres, 'name' );
		$genre_slugs = is_wp_error( $genres ) ? array() : wp_list_pluck( $genres, 'slug' );
		$states      = kabitab_get_story_statuses();
		$status_key  = (string) get_post_meta( $story_id, '_kabitab_status', true );
		$status      = $states[ $status_key ] ?? '';
		$blurb       = (string) get_post_meta( $story_id, '_kabitab_blurb', true );
		$author      = Kabitab_Frontend::get_author_label( $story_id );
		$reads       = Kabitab_Story_Engagement::get_read_count( $story_id );
		$likes       = Kabitab_Story_Engagement::get_like_count( $story_id );
		$saves       = Kabitab_Story_Engagement::get_save_count( $story_id );
		$search_text = implode( ' ', array_merge( array( get_the_title( $story_id ), $author, $blurb ), $genre_names ) );
		$thumbnail   = Kabitab_Frontend::get_story_cover_markup(
			$story_id,
			'kabitab-story-card',
			array(
				'alt'      => sprintf( __( '%s cover', 'kabitab-story-system' ), get_the_title( $story_id ) ),
				// The card is much narrower than WordPress's default 1024px image assumption.
				// This allows browsers to select the existing responsive thumbnails instead.
				'sizes'    => '(max-width: 767px) calc(100vw - 80px), (max-width: 1200px) calc(50vw - 64px), 565px',
				// Story cards sit below the hero, so they should not delay the first screen.
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
		?>
		<article class="kabitab-story-grid__card" data-kabitab-story-card data-kabitab-search="<?php echo esc_attr( $search_text ); ?>" data-kabitab-genres="<?php echo esc_attr( implode( ' ', $genre_slugs ) ); ?>">
			<div class="kabitab-story-grid__cover">
				<?php if ( '' !== $thumbnail ) : ?>
					<?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constructed from trusted WordPress image markup. ?>
				<?php else : ?>
					<span><?php echo esc_html( ! empty( $genre_names ) ? $genre_names[0] : __( 'KABITAB', 'kabitab-story-system' ) ); ?></span>
				<?php endif; ?>
			</div>
			<div class="kabitab-story-grid__card-body">
				<div class="kabitab-story-grid__labels">
					<?php if ( ! empty( $genre_names ) ) : ?><span><?php echo esc_html( implode( ' · ', $genre_names ) ); ?></span><?php endif; ?>
					<?php if ( '' !== $status ) : ?><span><?php echo esc_html( $status ); ?></span><?php endif; ?>
				</div>
				<h2 class="kabitab-story-grid__title"><a href="<?php echo esc_url( get_permalink( $story_id ) ); ?>"><?php echo esc_html( get_the_title( $story_id ) ); ?></a></h2>
				<?php if ( '' !== $author ) : ?><p class="kabitab-story-grid__author"><?php echo esc_html( $author ); ?></p><?php endif; ?>
				<?php if ( '' !== $blurb ) : ?><p class="kabitab-story-grid__blurb"><?php echo esc_html( $blurb ); ?></p><?php endif; ?>
				<p class="kabitab-story-grid__meta"><?php echo esc_html( sprintf( _n( '%d chapter', '%d chapters', $chapter_count, 'kabitab-story-system' ), $chapter_count ) ); ?></p>
				<?php if ( $reads || $likes || $saves ) : ?><p class="kabitab-story-grid__engagement"><?php echo esc_html( sprintf( __( '%1$s reads · %2$s likes · %3$s saves', 'kabitab-story-system' ), number_format_i18n( $reads ), number_format_i18n( $likes ), number_format_i18n( $saves ) ) ); ?></p><?php endif; ?>
				<a class="kabitab-story-grid__action" href="<?php echo esc_url( get_permalink( $story_id ) ); ?>"><?php esc_html_e( 'पढ्न सुरु गर्नुहोस्', 'kabitab-story-system' ); ?></a>
			</div>
		</article>
		<?php
	}

	private static function enqueue_styles_and_script() {
		$style = KABITAB_STORY_SYSTEM_PATH . 'assets/css/story-grid.css';
		$script = KABITAB_STORY_SYSTEM_PATH . 'assets/js/story-grid.js';
		if ( file_exists( $style ) ) {
			wp_enqueue_style( 'kabitab-story-grid', KABITAB_STORY_SYSTEM_URL . 'assets/css/story-grid.css', array(), (string) filemtime( $style ) );
		}
		if ( file_exists( $script ) ) {
			wp_enqueue_script( 'kabitab-story-grid', KABITAB_STORY_SYSTEM_URL . 'assets/js/story-grid.js', array(), (string) filemtime( $script ), true );
		}
	}
}
