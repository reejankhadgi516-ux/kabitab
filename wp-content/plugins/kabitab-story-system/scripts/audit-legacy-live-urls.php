<?php
/** Report KABITAB legacy live-site URLs remaining in WordPress content. */

defined( 'WP_CLI' ) || exit;

global $wpdb;
$needle = '%patan.cloudforstudent.com/rijan-khadgi%';

$posts = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT ID AS object_id, 'post_content' AS field_name FROM {$wpdb->posts} WHERE post_content LIKE %s",
		$needle
	),
	ARRAY_A
);
$meta = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT post_id AS object_id, meta_key AS field_name FROM {$wpdb->postmeta} WHERE meta_value LIKE %s",
		$needle
	),
	ARRAY_A
);

$options = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT 0 AS object_id, option_name AS field_name FROM {$wpdb->options} WHERE option_value LIKE %s",
		$needle
	),
	ARRAY_A
);

WP_CLI::line( wp_json_encode( array_merge( $posts, $meta, $options ) ) );
