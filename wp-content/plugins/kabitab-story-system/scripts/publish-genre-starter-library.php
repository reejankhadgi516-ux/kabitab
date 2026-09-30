<?php
/**
 * Gives the local genre starter drafts fictional pen names, cover images, and
 * published first chapters. This is intentionally local-development only.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || require_once dirname( __DIR__, 4 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$cover_directory = 'C:\\Users\\LENOVO\\OneDrive\\Documents\\KABITAB\\assets\\story-covers';
$writer_role     = get_role( 'kabitab_writer' ) ? 'kabitab_writer' : 'author';
$starter_stories = array(
	'romance'    => array( 'pen_name' => 'माया तामाङ', 'username' => 'kabitab-maya-tamang', 'cover' => 'bardaliko-chithi-cover.png' ),
	'mystery'    => array( 'pen_name' => 'निश्चल गुरुङ', 'username' => 'kabitab-nischal-gurung', 'cover' => 'rato-chhatako-rahasya-cover.png' ),
	'stories'    => array( 'pen_name' => 'सुभा श्रेष्ठ', 'username' => 'kabitab-subha-shrestha', 'cover' => 'hajuraamako-rumal-cover.png' ),
	'poems'      => array( 'pen_name' => 'रश्मि दाहाल', 'username' => 'kabitab-rashmi-dahal', 'cover' => 'jhyalko-ujyalo-cover.png' ),
	'novels'     => array( 'pen_name' => 'अनिल कार्की', 'username' => 'kabitab-anil-karki', 'cover' => 'paanimuniko-shahar-cover.png' ),
	'gazal'      => array( 'pen_name' => 'पल्लवी राई', 'username' => 'kabitab-pallavi-rai', 'cover' => 'saharle-sodheko-raat-cover.png' ),
	'life'       => array( 'pen_name' => 'दीपक भण्डारी', 'username' => 'kabitab-deepak-bhandari', 'cover' => 'basko-jhyal-cover.png' ),
	'motivation' => array( 'pen_name' => 'सिम्रन थापा', 'username' => 'kabitab-simran-thapa', 'cover' => 'ek-kadam-thap-cover.png' ),
	'society'    => array( 'pen_name' => 'मनोज सुनार', 'username' => 'kabitab-manoj-sunar', 'cover' => 'sadakpariko-vidyalaya-cover.png' ),
);

/** Create a private local writer account for a fictional pen name, if needed. */
function kabitab_local_starter_writer( $writer, $role ) {
	$user = get_user_by( 'login', $writer['username'] );
	if ( ! $user instanceof WP_User ) {
		$user_id = wp_insert_user(
			array(
				'user_login'   => $writer['username'],
				'user_pass'    => wp_generate_password( 32, true, true ),
				'user_email'   => $writer['username'] . '@example.invalid',
				'display_name' => $writer['pen_name'],
				'nickname'     => $writer['pen_name'],
				'role'         => $role,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		return (int) $user_id;
	}

	wp_update_user(
		array(
			'ID'           => $user->ID,
			'display_name' => $writer['pen_name'],
			'nickname'     => $writer['pen_name'],
			'role'         => $role,
		)
	);
	return (int) $user->ID;
}

/** Imports a project-owned cover without moving the source copy. */
function kabitab_local_starter_cover( $story_id, $cover_path, $story_title ) {
	if ( has_post_thumbnail( $story_id ) ) {
		return get_post_thumbnail_id( $story_id );
	}
	if ( ! is_readable( $cover_path ) ) {
		return new WP_Error( 'missing_cover', sprintf( 'Missing cover: %s', $cover_path ) );
	}

	$temp_path = wp_tempnam( wp_basename( $cover_path ) );
	if ( ! $temp_path || ! copy( $cover_path, $temp_path ) ) {
		return new WP_Error( 'cover_copy_failed', sprintf( 'Could not prepare cover: %s', $cover_path ) );
	}

	$attachment_id = media_handle_sideload(
		array(
			'name'     => wp_basename( $cover_path ),
			'tmp_name' => $temp_path,
		),
		$story_id,
		sprintf( '%s cover', $story_title )
	);
	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $temp_path );
		return $attachment_id;
	}

	set_post_thumbnail( $story_id, $attachment_id );
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', sprintf( '%s cover', $story_title ) );
	return (int) $attachment_id;
}

/* Replace placeholder AI-facing display labels on the existing published work. */
$existing_pen_names = array(
	'हिउँ पग्लिएको बिहान' => 'आरती राई',
	'अधुरो प्रेम'          => 'आरती राई',
	'असमर्थ'               => 'सुदीप कार्की',
	'हारपछि पनि तिमी'     => 'सुदीप कार्की',
);
foreach ( $existing_pen_names as $title => $pen_name ) {
	$existing = get_page_by_title( $title, OBJECT, 'kabitab_story' );
	if ( $existing instanceof WP_Post ) {
		update_post_meta( $existing->ID, '_kabitab_public_author_name', $pen_name );
	}
}

$published = 0;
foreach ( $starter_stories as $seed => $writer ) {
	$stories = get_posts(
		array(
			'post_type'      => 'kabitab_story',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_kabitab_genre_starter_seed',
			'meta_value'     => $seed,
			'no_found_rows'  => true,
		)
	);
	if ( empty( $stories ) ) {
		fwrite( STDERR, sprintf( "Starter story not found for %s.\n", $seed ) );
		continue;
	}

	$story     = $stories[0];
	$writer_id = kabitab_local_starter_writer( $writer, $writer_role );
	if ( is_wp_error( $writer_id ) ) {
		fwrite( STDERR, sprintf( "Could not create writer for %s.\n", $story->post_title ) );
		continue;
	}

	$cover = kabitab_local_starter_cover( $story->ID, $cover_directory . '\\' . $writer['cover'], $story->post_title );
	if ( is_wp_error( $cover ) ) {
		fwrite( STDERR, sprintf( "Could not attach cover for %s: %s\n", $story->post_title, $cover->get_error_message() ) );
		continue;
	}

	wp_update_post(
		array(
			'ID'          => $story->ID,
			'post_author' => $writer_id,
			'post_status' => 'publish',
		)
	);
	update_post_meta( $story->ID, '_kabitab_public_author_name', $writer['pen_name'] );

	$chapters = get_posts(
		array(
			'post_type'      => 'kabitab_chapter',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_key'       => '_kabitab_parent_story',
			'meta_value'     => $story->ID,
			'no_found_rows'  => true,
		)
	);
	foreach ( $chapters as $chapter ) {
		wp_update_post(
			array(
				'ID'          => $chapter->ID,
				'post_author' => $writer_id,
				'post_status' => 'publish',
			)
		);
	}

	++$published;
	fwrite( STDOUT, sprintf( "Published: %s — %s\n", $story->post_title, $writer['pen_name'] ) );
}

fwrite( STDOUT, sprintf( "Published %d genre starter stories.\n", $published ) );
