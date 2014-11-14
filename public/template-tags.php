<?php

use WPTalents\Core\Helper;

/**
 * Get the avatar of a talent.
 *
 * @param WP_Post|int $post The post object or ID.
 * @param int         $size The desired avatar size
 *
 * @return mixed            The avatar URL on success,
 *                          false if the post does not exist.
 */
function wptalents_get_avatar( $post = null, $size = 144 ) {
	return Helper::get_avatar( get_post( $post ), $size );
}

function wptalents_get_attachment_url( $attachment, $size = 'full' ) {
	return Helper::get_attachment_url( $attachment, $size );
}

/**
 * @param WP_Post|int $post The post object or ID.
 * @param string      $type The meta to retrieve. Defaults to all.
 *                          Possible values are:
 *                          - profile
 *                          - plugins
 *                          - themes
 *
 * @return mixed            The required meta if available,
 *                          false if the post does not exist.
 */
function wptalents_get_talent_meta( $post = null, $type = 'all' ) {
	return Helper::get_talent_meta( get_post( $post ), $type );
}