<?php
/**
 * Phase 3: keep discovery cards truthful.
 *
 * Only the published Adhuro Prem card can take readers to a story. Every
 * other static Home or Explore card is visibly marked Coming soon until it
 * has a matching public story page.
 */

defined( 'WP_CLI' ) || exit;

$story_url = home_url( '/adhuro-prem/' );
$targets   = array( 544, 2195 );
$report    = array();

/**
 * Update a static discovery-card fragment without changing its design.
 *
 * @param string $html HTML fragment.
 * @param string $url  Published story URL.
 * @return array{html:string, published:int, coming_soon:int}
 */
function kabitab_phase3_update_discovery_cards( $html, $url ) {
	$published   = 0;
	$coming_soon = 0;
	$pattern     = '/<article\b[^>]*class=(["\'])[^"\']*(?:kabitab-home__story-card|kabitab-explore__card)[^"\']*\1[^>]*>.*?<\/article>/is';

	$html = preg_replace_callback(
		$pattern,
		static function ( $match ) use ( $url, &$published, &$coming_soon ) {
			$card = $match[0];
			$is_adhuro_prem = false !== stripos( $card, 'adhuro-prem' ) || false !== mb_stripos( wp_strip_all_tags( $card ), 'अधुरो प्रेम', 0, 'UTF-8' );
			if ( $is_adhuro_prem ) {
				$updated = preg_replace(
					'/(<a\b[^>]*\bhref=)(["\'])[^"\']*\2([^>]*>\s*Read\s+Now\s*<\/a>)/i',
					'$1$2' . esc_url( $url ) . '$2$3',
					$card,
					-1,
					$replacements
				);
				if ( $replacements > 0 ) {
					$published += $replacements;
					return $updated;
				}

				$updated = preg_replace(
					'/<span\b[^>]*\bkabitab-story-card__coming-soon\b[^>]*>\s*Coming\s+soon\s*<\/span>/i',
					'<a class="kabitab-home__button" href="' . esc_url( $url ) . '">Read Now</a>',
					$card,
					-1,
					$replacements
				);
				if ( $replacements > 0 ) {
					$published += $replacements;
					return $updated;
				}
				return $card;
			}

			$updated = preg_replace(
				'/<a\b[^>]*>\s*Read\s+Now\s*<\/a>/i',
				'<span class="kabitab-story-card__coming-soon" role="status">Coming soon</span>',
				$card,
				-1,
				$replacements
			);
			if ( $replacements > 0 ) {
				$coming_soon += $replacements;
				return $updated;
			}

			return $card;
		},
		$html
	);

	return array(
		'html'        => is_string( $html ) ? $html : '',
		'published'   => $published,
		'coming_soon' => $coming_soon,
	);
}

/**
 * Recursively update Elementor HTML widgets in one document.
 *
 * @param array $elements Elementor element tree.
 * @param string $url     Published story URL.
 * @param int    $published Published-link counter.
 * @param int    $coming_soon Coming-soon counter.
 * @return array
 */
function kabitab_phase3_update_elementor_elements( $elements, $url, &$published, &$coming_soon ) {
	foreach ( $elements as &$element ) {
		if ( isset( $element['settings']['html'] ) && is_string( $element['settings']['html'] ) ) {
			$updated                           = kabitab_phase3_update_discovery_cards( $element['settings']['html'], $url );
			$element['settings']['html']        = $updated['html'];
			$published                         += $updated['published'];
			$coming_soon                       += $updated['coming_soon'];
		}

		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$element['elements'] = kabitab_phase3_update_elementor_elements( $element['elements'], $url, $published, $coming_soon );
		}
	}
	unset( $element );

	return $elements;
}

foreach ( $targets as $post_id ) {
	$content = (string) get_post_field( 'post_content', $post_id );
	$updated = kabitab_phase3_update_discovery_cards( $content, $story_url );
	$changed = $updated['html'] !== $content;

	if ( $changed ) {
		$result = wp_update_post( wp_slash( array( 'ID' => $post_id, 'post_content' => $updated['html'] ) ), true );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
	}

	$elementor_raw = (string) get_post_meta( $post_id, '_elementor_data', true );
	$elementor     = json_decode( $elementor_raw, true );
	$elementor_changed = false;

	if ( is_array( $elementor ) ) {
		$published   = 0;
		$coming_soon = 0;
		$elementor   = kabitab_phase3_update_elementor_elements( $elementor, $story_url, $published, $coming_soon );
		$encoded     = wp_json_encode( $elementor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( is_string( $encoded ) && $encoded !== $elementor_raw ) {
			update_post_meta( $post_id, '_elementor_data', wp_slash( $encoded ) );
			$elementor_changed = true;
		}
		$updated['published']   += $published;
		$updated['coming_soon'] += $coming_soon;
	}

	$report[] = array(
		'id'               => $post_id,
		'published_links'  => $updated['published'],
		'coming_soon'      => $updated['coming_soon'],
		'content_updated'  => $changed,
		'elementor_updated'=> $elementor_changed,
	);
}

WP_CLI::success( wp_json_encode( $report ) );
