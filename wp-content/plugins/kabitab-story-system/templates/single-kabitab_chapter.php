<?php
/**
 * Reusable Chapter Reader template.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

$chapter_id    = get_queried_object_id();
$chapter       = get_post( $chapter_id );
$story_id      = kabitab_get_chapter_parent_story_id( $chapter_id );
$story_url     = kabitab_get_chapter_story_url( $chapter_id );
$previous      = kabitab_get_previous_chapter( $chapter_id );
$next          = kabitab_get_next_chapter( $chapter_id );
$reading_time  = kabitab_get_chapter_reading_time( $chapter_id );
$chapter_title = get_the_title( $chapter_id );
$author        = Kabitab_Frontend::get_author_label( $chapter_id );
$narration_ids = wp_parse_id_list( get_post_meta( $chapter_id, '_kabitab_narration_ids', true ) );

get_header();
?>
<div class="kabitab-reading-progress" role="progressbar" aria-label="<?php esc_attr_e( 'Chapter reading progress', 'kabitab-story-system' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
	<span class="kabitab-reading-progress__bar" data-kabitab-reading-progress></span>
</div>
<main class="kabitab-reader-shell kabitab-reader-shell--chapter">
	<article class="kabitab-reader-article">
		<header class="kabitab-reader-header">
			<?php if ( $story_id && '' !== $story_url ) : ?>
				<p class="kabitab-eyebrow"><a href="<?php echo esc_url( $story_url ); ?>"><?php echo esc_html( get_the_title( $story_id ) ); ?></a></p>
			<?php else : ?>
				<p class="kabitab-eyebrow"><?php esc_html_e( 'KABITAB Chapter', 'kabitab-story-system' ); ?></p>
			<?php endif; ?>
			<h1><?php echo esc_html( $chapter_title ); ?></h1>
			<?php if ( '' !== $author ) : ?>
				<p class="kabitab-byline"><?php echo esc_html( sprintf( __( 'लेखक: %s', 'kabitab-story-system' ), $author ) ); ?></p>
			<?php endif; ?>
			<?php if ( $reading_time > 0 ) : ?>
				<p class="kabitab-reading-label"><?php echo esc_html( sprintf( _n( '%d min read', '%d min read', $reading_time, 'kabitab-story-system' ), $reading_time ) ); ?></p>
			<?php endif; ?>
		</header>

		<div class="kabitab-reader-tools" role="group" aria-label="<?php esc_attr_e( 'Reading preferences', 'kabitab-story-system' ); ?>">
			<span class="kabitab-reader-tools__label"><?php esc_html_e( 'Reading', 'kabitab-story-system' ); ?></span>
			<div class="kabitab-reader-tools__group" aria-label="<?php esc_attr_e( 'Text size', 'kabitab-story-system' ); ?>">
				<button type="button" data-kabitab-text-size="small" aria-pressed="false" aria-label="<?php esc_attr_e( 'Use smaller text', 'kabitab-story-system' ); ?>">A−</button>
				<button type="button" data-kabitab-text-size="normal" aria-pressed="true" aria-label="<?php esc_attr_e( 'Use default text size', 'kabitab-story-system' ); ?>">A</button>
				<button type="button" data-kabitab-text-size="large" aria-pressed="false" aria-label="<?php esc_attr_e( 'Use larger text', 'kabitab-story-system' ); ?>">A+</button>
			</div>
			<button class="kabitab-reader-theme-toggle" type="button" data-kabitab-reader-theme aria-pressed="false"><?php esc_html_e( 'Night mode', 'kabitab-story-system' ); ?></button>
		</div>

		<?php if ( has_post_thumbnail( $chapter_id ) ) : ?>
			<div class="kabitab-chapter-image">
				<?php echo get_the_post_thumbnail( $chapter_id, 'large', array( 'loading' => 'eager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endif; ?>

		<div class="kabitab-reading-content">
			<?php echo apply_filters( 'the_content', get_post_field( 'post_content', $chapter_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<nav class="kabitab-reader-navigation" aria-label="<?php esc_attr_e( 'Chapter navigation', 'kabitab-story-system' ); ?>">
			<?php if ( $previous instanceof WP_Post ) : ?>
				<a class="kabitab-nav-link kabitab-nav-link--previous" href="<?php echo esc_url( get_permalink( $previous ) ); ?>"><span><?php esc_html_e( 'Previous Part', 'kabitab-story-system' ); ?></span><strong><?php echo esc_html( get_the_title( $previous ) ); ?></strong></a>
			<?php else : ?>
				<span class="kabitab-nav-link kabitab-nav-link--empty"></span>
			<?php endif; ?>

			<?php if ( $story_id && '' !== $story_url ) : ?>
				<a class="kabitab-back-link" href="<?php echo esc_url( $story_url ); ?>"><?php esc_html_e( 'Back to Story', 'kabitab-story-system' ); ?></a>
			<?php endif; ?>

			<?php if ( $next instanceof WP_Post ) : ?>
				<a class="kabitab-nav-link kabitab-nav-link--next" href="<?php echo esc_url( get_permalink( $next ) ); ?>"><span><?php esc_html_e( 'Next Part', 'kabitab-story-system' ); ?></span><strong><?php echo esc_html( get_the_title( $next ) ); ?></strong></a>
			<?php else : ?>
				<span class="kabitab-nav-link kabitab-nav-link--empty"></span>
			<?php endif; ?>
		</nav>

		<section class="kabitab-chapter-reaction" aria-label="<?php esc_attr_e( 'Chapter reaction', 'kabitab-story-system' ); ?>">
			<p><?php esc_html_e( 'Enjoyed this chapter? Support the writer with a vote.', 'kabitab-story-system' ); ?></p>
			<div><?php echo Kabitab_Story_Engagement::render_chapter_vote_control( $chapter_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</section>

		<div class="kabitab-share-controls kabitab-share-controls--chapter" data-kabitab-share data-kabitab-share-title="<?php echo esc_attr( $chapter_title ); ?>" data-kabitab-share-url="<?php echo esc_url( get_permalink( $chapter_id ) ); ?>">
			<button type="button" data-kabitab-share-button><?php esc_html_e( 'Share this chapter', 'kabitab-story-system' ); ?></button>
			<button type="button" data-kabitab-copy-link><?php esc_html_e( 'Copy link', 'kabitab-story-system' ); ?></button>
			<p data-kabitab-share-status role="status" aria-live="polite"></p>
		</div>

		<section class="kabitab-read-aloud" aria-labelledby="kabitab-read-aloud-title">
			<h2 id="kabitab-read-aloud-title"><?php esc_html_e( 'सुन्नुहोस् / Listen', 'kabitab-story-system' ); ?></h2>
			<?php if ( ! empty( $narration_ids ) ) : ?>
				<p><?php echo esc_html( sprintf( _n( '%d narration track is available for this chapter.', '%d narration tracks are available for this chapter.', count( $narration_ids ), 'kabitab-story-system' ), count( $narration_ids ) ) ); ?></p>
				<div class="kabitab-read-aloud__tracks">
					<?php foreach ( $narration_ids as $narration_id ) : ?>
						<?php $narration_url = wp_get_attachment_url( $narration_id ); $narration_type = get_post_mime_type( $narration_id ); ?>
						<?php if ( ! $narration_url || ! $narration_type ) { continue; } ?>
						<article class="kabitab-read-aloud__track">
							<p class="kabitab-read-aloud__track-title"><?php echo esc_html( get_the_title( $narration_id ) ); ?></p>
							<?php if ( 0 === strpos( $narration_type, 'video/' ) ) : ?>
								<video controls preload="metadata"><source src="<?php echo esc_url( $narration_url ); ?>" type="<?php echo esc_attr( $narration_type ); ?>" /><?php esc_html_e( 'Your browser cannot play this narration.', 'kabitab-story-system' ); ?></video>
							<?php else : ?>
								<audio controls preload="metadata"><source src="<?php echo esc_url( $narration_url ); ?>" type="<?php echo esc_attr( $narration_type ); ?>" /><?php esc_html_e( 'Your browser cannot play this narration.', 'kabitab-story-system' ); ?></audio>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<p class="kabitab-read-aloud__help"><?php esc_html_e( 'Listen with an available device voice, or select text in this chapter and play only that passage.', 'kabitab-story-system' ); ?></p>
			<div class="kabitab-read-aloud__controls" role="group" aria-label="<?php esc_attr_e( 'Read aloud controls', 'kabitab-story-system' ); ?>">
				<button type="button" class="kabitab-read-aloud__button" data-kabitab-read-aloud aria-pressed="false"><?php esc_html_e( 'Read chapter', 'kabitab-story-system' ); ?></button>
				<button type="button" class="kabitab-read-aloud__secondary" data-kabitab-read-aloud-pause disabled><?php esc_html_e( 'Pause', 'kabitab-story-system' ); ?></button>
				<button type="button" class="kabitab-read-aloud__secondary" data-kabitab-read-selection><?php esc_html_e( 'Read selected text', 'kabitab-story-system' ); ?></button>
				<label><span><?php esc_html_e( 'Speed', 'kabitab-story-system' ); ?></span><select data-kabitab-read-speed aria-label="<?php esc_attr_e( 'Reading speed', 'kabitab-story-system' ); ?>"><option value="0.75">0.75×</option><option value="0.88">0.88×</option><option value="1">1×</option><option value="1.15">1.15×</option></select></label>
				<label><span><?php esc_html_e( 'Voice', 'kabitab-story-system' ); ?></span><select data-kabitab-read-voice aria-label="<?php esc_attr_e( 'Reading voice', 'kabitab-story-system' ); ?>"><option value=""><?php esc_html_e( 'Device default', 'kabitab-story-system' ); ?></option></select></label>
			</div>
			<p class="kabitab-read-aloud__status" data-kabitab-read-aloud-status role="status" aria-live="polite"></p>
		</section>

		<?php if ( comments_open( $chapter_id ) || get_comments_number( $chapter_id ) ) : ?>
			<section class="kabitab-chapter-discussion" aria-label="<?php esc_attr_e( 'Chapter discussion', 'kabitab-story-system' ); ?>">
				<?php comments_template(); ?>
			</section>
		<?php endif; ?>
	</article>
</main>
<?php get_footer(); ?>
