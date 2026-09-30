<?php
/**
 * Reusable Story Detail template.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

$story_id       = get_queried_object_id();
$story          = get_post( $story_id );
$chapters       = kabitab_get_ordered_story_chapters( $story_id );
$first_chapter  = ! empty( $chapters ) ? $chapters[0] : null;
$blurb          = (string) get_post_meta( $story_id, '_kabitab_blurb', true );
$summary        = '' !== trim( $blurb ) ? $blurb : (string) get_the_excerpt( $story );
$author         = Kabitab_Frontend::get_author_label( $story_id );
$status         = (string) get_post_meta( $story_id, '_kabitab_status', true );
$status_labels  = kabitab_get_story_statuses();
$genres         = wp_get_post_terms( $story_id, 'story_genre' );
$reads          = Kabitab_Story_Engagement::get_read_count( $story_id );
$likes          = Kabitab_Story_Engagement::get_like_count( $story_id );
$saves          = Kabitab_Story_Engagement::get_save_count( $story_id );
$resume         = is_user_logged_in() ? Kabitab_Reading_Progress::get_for_story( $story_id ) : array();
$resume_chapter = ! empty( $resume ) ? get_post( $resume['chapter_id'] ) : null;
$resume_percent = ! empty( $resume ) ? absint( $resume['percent'] ) : 0;
$narrated_chapters = array_values(
	array_filter(
		$chapters,
		static function ( $chapter ) {
			return ! empty( wp_parse_id_list( get_post_meta( $chapter->ID, '_kabitab_narration_ids', true ) ) );
		}
	)
);
$first_narrated_chapter = ! empty( $narrated_chapters ) ? $narrated_chapters[0] : null;

if ( $resume_chapter instanceof WP_Post && $resume_percent >= 95 ) {
	$current_index = array_search( (int) $resume_chapter->ID, array_map( 'absint', wp_list_pluck( $chapters, 'ID' ) ), true );
	if ( false !== $current_index && isset( $chapters[ $current_index + 1 ] ) ) {
		$resume_chapter = $chapters[ $current_index + 1 ];
		$resume_percent = 0;
	}
}
$primary_chapter = $resume_chapter instanceof WP_Post ? $resume_chapter : $first_chapter;
$primary_chapter_url = $primary_chapter instanceof WP_Post ? get_permalink( $primary_chapter ) : '';

if ( $resume_chapter instanceof WP_Post && $resume_percent > 0 && '' !== $primary_chapter_url ) {
	$primary_chapter_url = add_query_arg( 'kabitab_resume', '1', $primary_chapter_url );
}

get_header();
?>
<main class="kabitab-reader-shell">
	<article class="kabitab-story-card">
		<?php if ( has_post_thumbnail( $story_id ) ) : ?>
			<div class="kabitab-story-cover">
				<?php echo get_the_post_thumbnail( $story_id, 'large', array( 'loading' => 'eager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endif; ?>

		<div class="kabitab-story-content">
			<p class="kabitab-eyebrow"><?php esc_html_e( 'KABITAB Story', 'kabitab-story-system' ); ?></p>
			<h1><?php echo esc_html( get_the_title( $story_id ) ); ?></h1>

			<?php if ( '' !== $author ) : ?>
				<p class="kabitab-byline"><?php echo esc_html( sprintf( __( 'लेखक: %s', 'kabitab-story-system' ), $author ) ); ?></p>
			<?php endif; ?>

			<?php echo do_shortcode( '[kabitab_follow_writer author_id="' . absint( $story->post_author ) . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<?php if ( ! empty( $genres ) || isset( $status_labels[ $status ] ) ) : ?>
				<div class="kabitab-meta-row" aria-label="<?php esc_attr_e( 'Story details', 'kabitab-story-system' ); ?>">
					<?php if ( ! empty( $genres ) ) : ?>
						<span><?php echo esc_html( implode( ' · ', wp_list_pluck( $genres, 'name' ) ) ); ?></span>
					<?php endif; ?>
					<?php if ( isset( $status_labels[ $status ] ) ) : ?>
						<span><?php echo esc_html( $status_labels[ $status ] ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( '' !== trim( $summary ) ) : ?>
				<div class="kabitab-story-summary"><?php echo wp_kses_post( wpautop( $summary ) ); ?></div>
			<?php endif; ?>

			<?php if ( Kabitab_Story_Engagement::has_engagement( $story_id ) ) : ?>
				<div class="kabitab-story-stats">
					<span><strong><?php echo esc_html( number_format_i18n( $reads ) ); ?></strong> <?php esc_html_e( 'Reads', 'kabitab-story-system' ); ?></span>
					<span><strong data-kabitab-like-count><?php echo esc_html( number_format_i18n( $likes ) ); ?></strong> <?php esc_html_e( 'Likes', 'kabitab-story-system' ); ?></span>
					<span><strong><?php echo esc_html( number_format_i18n( $saves ) ); ?></strong> <?php esc_html_e( 'Saves', 'kabitab-story-system' ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( $primary_chapter instanceof WP_Post ) : ?>
				<p class="kabitab-primary-action">
					<a href="<?php echo esc_url( $primary_chapter_url ); ?>"><?php echo esc_html( $resume_chapter instanceof WP_Post ? __( 'Continue reading', 'kabitab-story-system' ) : __( 'पढ्न सुरु गर्नुहोस्', 'kabitab-story-system' ) ); ?></a>
					<?php if ( $resume_chapter instanceof WP_Post ) : ?><span class="kabitab-resume-note"><?php echo esc_html( sprintf( __( '%1$s · %2$d%% complete', 'kabitab-story-system' ), get_the_title( $resume_chapter ), $resume_percent ) ); ?></span><?php endif; ?>
				</p>
			<?php endif; ?>

			<?php if ( $first_narrated_chapter instanceof WP_Post ) : ?>
				<p class="kabitab-listen-action">
					<a href="<?php echo esc_url( get_permalink( $first_narrated_chapter ) ); ?>"><?php esc_html_e( 'Start listening', 'kabitab-story-system' ); ?></a>
					<span><?php echo esc_html( sprintf( _n( '%1$d of %2$d chapter has narration', '%1$d of %2$d chapters have narration', count( $narrated_chapters ), 'kabitab-story-system' ), count( $narrated_chapters ), count( $chapters ) ) ); ?></span>
				</p>
			<?php endif; ?>

			<?php if ( shortcode_exists( 'favorite_button' ) ) : ?>
				<div class="kabitab-save-action">
					<?php echo do_shortcode( '[favorite_button post_id="' . absint( $story_id ) . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo Kabitab_Story_Engagement::render_like_control( array( 'story_id' => $story_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php else : ?>
				<div class="kabitab-save-action"><?php echo Kabitab_Story_Engagement::render_like_control( array( 'story_id' => $story_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php endif; ?>

			<?php echo Kabitab_Reading_Lists::render_story_control( $story_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<div class="kabitab-share-controls" data-kabitab-share data-kabitab-share-title="<?php echo esc_attr( get_the_title( $story_id ) ); ?>" data-kabitab-share-url="<?php echo esc_url( get_permalink( $story_id ) ); ?>">
				<button type="button" data-kabitab-share-button><?php esc_html_e( 'Share this story', 'kabitab-story-system' ); ?></button>
				<button type="button" data-kabitab-copy-link><?php esc_html_e( 'Copy link', 'kabitab-story-system' ); ?></button>
				<p data-kabitab-share-status role="status" aria-live="polite"></p>
			</div>
		</div>
	</article>

	<section class="kabitab-chapter-index" aria-labelledby="kabitab-chapter-index-title">
		<h2 id="kabitab-chapter-index-title"><?php esc_html_e( 'कथाका भागहरू', 'kabitab-story-system' ); ?></h2>
		<?php if ( empty( $chapters ) ) : ?>
			<p class="kabitab-empty-state"><?php esc_html_e( 'यस कथाका भागहरू अझै उपलब्ध छैनन्।', 'kabitab-story-system' ); ?></p>
		<?php else : ?>
			<ol class="kabitab-chapter-list">
				<?php foreach ( $chapters as $chapter ) : ?>
					<?php $reading_time = kabitab_get_chapter_reading_time( $chapter ); $has_narration = ! empty( wp_parse_id_list( get_post_meta( $chapter->ID, '_kabitab_narration_ids', true ) ) ); ?>
					<li<?php echo $resume_chapter instanceof WP_Post && (int) $resume_chapter->ID === (int) $chapter->ID ? ' class="is-current"' : ''; ?>>
						<a href="<?php echo esc_url( get_permalink( $chapter ) ); ?>">
							<span class="kabitab-chapter-number"><?php echo esc_html( sprintf( __( 'भाग %d', 'kabitab-story-system' ), kabitab_sanitize_positive_integer( get_post_meta( $chapter->ID, '_kabitab_chapter_number', true ) ) ) ); ?></span>
							<span class="kabitab-chapter-title"><?php echo esc_html( get_the_title( $chapter ) ); ?></span>
							<span class="kabitab-reading-time"><?php echo esc_html( sprintf( _n( '%d min read', '%d min read', $reading_time, 'kabitab-story-system' ), $reading_time ) ); ?></span>
							<?php if ( $has_narration ) : ?><span class="kabitab-audio-badge"><?php esc_html_e( 'Audio available', 'kabitab-story-system' ); ?></span><?php endif; ?>
							<?php if ( $resume_chapter instanceof WP_Post && (int) $resume_chapter->ID === (int) $chapter->ID ) : ?><span class="kabitab-continue-badge"><?php esc_html_e( 'Continue here', 'kabitab-story-system' ); ?></span><?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</section>
</main>
<?php get_footer(); ?>
