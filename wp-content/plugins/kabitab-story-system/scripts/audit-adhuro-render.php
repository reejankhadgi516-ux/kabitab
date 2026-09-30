<?php
/** Compare Adhuro Prem's saved content with its WordPress-rendered content. */

defined( 'WP_CLI' ) || exit;

$post_id = 2313;
$raw     = (string) get_post_field( 'post_content', $post_id );
$rendered = apply_filters( 'the_content', $raw );
$needle  = 'patan.cloudforstudent.com';

WP_CLI::line(
	wp_json_encode(
		array(
			'raw_has_legacy_url'      => false !== strpos( $raw, $needle ),
			'rendered_has_legacy_url' => false !== strpos( $rendered, $needle ),
			'raw_part_1_local'        => false !== strpos( $raw, home_url( '/adhuro-prem-part-1/' ) ),
			'raw_part_2_local'        => false !== strpos( $raw, home_url( '/adhuro-prem-part-2/' ) ),
			'rendered_part_1_local'   => false !== strpos( $rendered, home_url( '/adhuro-prem-part-1/' ) ),
			'rendered_part_2_local'   => false !== strpos( $rendered, home_url( '/adhuro-prem-part-2/' ) ),
		)
	)
);
