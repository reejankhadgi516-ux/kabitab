<?php
/** Repair the Adhuro Prem Home card with real markup and verified local data. */

defined( 'WP_CLI' ) || exit;

$home_id   = 544;
$story_url = home_url( '/adhuro-prem/' );
$cover_url = home_url( '/wp-content/uploads/2026/07/adhuro-prem-cover.webp' );
$content   = (string) get_post_field( 'post_content', $home_id );

$card = sprintf(
	<<<'HTML'
<article class="kabitab-home__story-card" data-kabitab-story="adhuro-prem">
  <div class="kabitab-home__story-cover cover-brown" style="position:relative;overflow:hidden;isolation:isolate;">
    <img src="%1$s" alt="अधुरो प्रेम cover artwork" style="position:absolute;inset:0;width:100%%;height:100%%;object-fit:cover;display:block;z-index:0;" />
    <span aria-hidden="true" style="position:absolute;inset:0;display:block;background:linear-gradient(180deg,rgba(35,16,18,.14) 0%%,rgba(35,16,18,.62) 100%%);z-index:1;"></span>
    <span style="position:relative;z-index:2;">Readers’ romance</span>
    <strong style="position:relative;z-index:2;">अधुरो प्रेम</strong>
  </div>
  <div class="kabitab-home__story-body">
    <div class="kabitab-home__story-topline"><span class="kabitab-home__pill">Stories / कथा</span></div>
    <h3>अधुरो प्रेम</h3>
    <p class="kabitab-home__writer">By स्मृति अधिकारी</p>
    <p class="kabitab-home__story-category">Category: प्रेम / Romance</p>
    <p class="kabitab-home__excerpt">शहरको भीडभाडबीच आकस्मिक रूपमा सुरु भएको भेट, नबोलेका भावनाहरू, र फेरि भेटिने आशाले बनाएको कथा।</p>
    <div class="kabitab-home__story-meta"><span>2 Parts</span><span>4.8K Reads</span><span>Ongoing</span></div>
    <div class="kabitab-home__story-actions"><a class="kabitab-home__button" href="%2$s">Read Now</a></div>
  </div>
</article>
HTML,
	esc_url( $cover_url ),
	esc_url( $story_url )
);

$updated = preg_replace(
	'/<article\b[^>]*\bdata-kabitab-story=(["\'])adhuro-prem\1[^>]*>.*?<\/article>/is',
	$card,
	$content,
	1,
	$replacements
);

if ( 0 === $replacements ) {
	$updated = preg_replace(
		'/(<div\b[^>]*class=(["\'])[^"\']*\bkabitab-home__story-grid\b[^"\']*\2[^>]*>)/i',
		'$1' . "\n" . $card,
		$content,
		1,
		$replacements
	);
}

if ( 1 !== $replacements || ! is_string( $updated ) ) {
	WP_CLI::error( 'The Home story card location was not found; no content was changed.' );
}

$result = wp_update_post(
	wp_slash(
		array(
			'ID'           => $home_id,
			'post_content' => $updated,
		)
	),
	true
);

if ( is_wp_error( $result ) ) {
	WP_CLI::error( $result->get_error_message() );
}

WP_CLI::success( 'The Adhuro Prem Home card was repaired.' );
