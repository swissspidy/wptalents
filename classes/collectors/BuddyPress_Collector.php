<?php
/**
 * BuddyPress.org profile collector that is hooked to a cron event.
 *
 * @package WPTalent
 */

namespace WPTalents\Collector;

use DOMDocument;
use DOMXPath;

/**
 * Class BuddyPress_Collector.
 *
 * @package WPTalents\Collector
 */
class BuddyPress_Collector {
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

		$topics_count = self::_retrieve_count( 'https://buddypress.org/members/' . $user->user_login . '/forums/' );

		if ( $topics_count ) {
			update_user_meta( $user_id, '_wptalents_buddypress_topics_count', $topics_count );
		}

		$replies_count = self::_retrieve_count( 'https://buddypress.org/members/' . $user->user_login . '/forums/replies/' );

		if ( $replies_count ) {
			update_user_meta( $user_id, '_wptalents_buddypress_replies_count', $replies_count );
		}
	}

	protected static function _retrieve_count( $url ) {
		$body = wp_remote_retrieve_body( wp_safe_remote_get( $url ) );

		if ( '' === $body ) {
			return false;
		}

		$dom = new DOMDocument();

		libxml_use_internal_errors( true );
		$dom->loadHTML( $body );
		libxml_clear_errors();

		$finder = new DOMXPath( $dom );

		$pagination_count = $finder->query( '//div[@class="bbp-pagination-count"]' );
		preg_match( '/of ([0-9,]+) total/', $pagination_count->item( 0 )->nodeValue, $matches );

		if ( isset ( $matches[1] ) ) {
			return absint( trim( str_replace( ',', '', $matches[1] ) ) );
		}

		return false;
	}
}
