<?php
/**
 * Reader interactions that use WordPress-native comments and Favorites.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

class Kabitab_Reader_Interactions {
	/**
	 * New Chapters accept discussion by default without changing site-wide
	 * WordPress comment defaults or any unrelated post type.
	 *
	 * @param array $data    Post data before database insertion.
	 * @param array $postarr Original post data.
	 * @return array
	 */
	public static function set_new_chapter_comment_status( $data, $postarr ) {
		if ( 'kabitab_chapter' === $data['post_type'] && empty( $postarr['ID'] ) && 'closed' === $data['comment_status'] ) {
			$data['comment_status'] = 'open';
		}
		return $data;
	}
}
