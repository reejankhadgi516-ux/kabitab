<?php
/**
 * Dynamic Story card template.
 *
 * @package KABITAB_Story_System
 *
 * @var array<string, mixed> $card Card data prepared by the shortcode.
 */

defined( 'ABSPATH' ) || exit;

$heading_id = 'kabitab-story-card-title-' . absint( $card['id'] );
?>
<article class="kabitab-story-card" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
	<div class="kabitab-story-card__cover">
		<?php echo $card['cover']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constructed from trusted WordPress image markup. ?>
	</div>
	<div class="kabitab-story-card__body">
		<?php if ( '' !== $card['status'] ) : ?>
			<p class="kabitab-story-card__status"><?php echo esc_html( $card['status'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $card['genre'] ) : ?>
			<p class="kabitab-story-card__genre"><?php echo esc_html( $card['genre'] ); ?></p>
		<?php endif; ?>
		<h2 id="<?php echo esc_attr( $heading_id ); ?>" class="kabitab-story-card__title"><?php echo esc_html( $card['title'] ); ?></h2>
		<?php if ( '' !== $card['author'] ) : ?>
			<p class="kabitab-story-card__author"><?php echo esc_html( sprintf( __( 'लेखक: %s', 'kabitab-story-system' ), $card['author'] ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $card['blurb'] ) : ?>
			<p class="kabitab-story-card__blurb"><?php echo esc_html( $card['blurb'] ); ?></p>
		<?php endif; ?>
		<dl class="kabitab-story-card__stats">
			<div><dt><?php esc_html_e( 'Chapters', 'kabitab-story-system' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) $card['chapter_count'] ) ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Reads', 'kabitab-story-system' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) $card['reads'] ) ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Likes', 'kabitab-story-system' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) $card['likes'] ) ); ?></dd></div>
		</dl>
		<a class="kabitab-story-card__action" href="<?php echo esc_url( $card['permalink'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Read %s', 'kabitab-story-system' ), $card['title'] ) ); ?>"><?php esc_html_e( 'Read Now', 'kabitab-story-system' ); ?></a>
	</div>
</article>
