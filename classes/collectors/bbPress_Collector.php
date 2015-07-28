<?php
/**
 * bbPress.org profile collector that is hooked to a cron event.
 *
 * @package WPTalent
 */

namespace WPTalents\Collector;

use DOMDocument;
use DOMXPath;

/**
 * Class bbPress_Collector.
 *
 * @package WPTalents\Collector
 */
class bbPress_Collector {
	/**
	 * Retrieve data from the WordPress.org profile of a user.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool
	 */
	public static function retrieve_data( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return false;
		}

		$body = wp_remote_retrieve_body( wp_safe_remote_get( 'https://bbpress.org/forums/profile/' . $user->user_login . '/' ) );

		if ( '' === $body ) {
			return false;
		}

		$dom = new DOMDocument();

		libxml_use_internal_errors( true );
		$dom->loadHTML( $body );
		libxml_clear_errors();

		$finder = new DOMXPath( $dom );

		$topics_count  = $finder->query( '//p[@class="bbp-user-topic-count"]' );
		$replies_count = $finder->query( '//p[@class="bbp-user-reply-count"]' );

		preg_match( '/: ([0-9]+)/', $topics_count->item( 0 )->nodeValue, $matches );

		if ( isset ( $matches[0] ) ) {
			bp_update_user_meta( $user_id, '_wptalents_bbpress_topics_count', absint( $matches[0] ) );
		}

		preg_match( '/: ([0-9]+)/', $replies_count->item( 0 )->nodeValue, $matches );

		if ( isset ( $matches[0] ) ) {
			bp_update_user_meta( $user_id, '_wptalents_bbpress_replies_count', absint( $matches[0] ) );
		}

		return true;
	}
}
