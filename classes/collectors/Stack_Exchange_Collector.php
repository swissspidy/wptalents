<?php
/**
 * WPSE collector that is hooked to a cron event.
 *
 * @package WPTalent
 */

namespace WPTalents\Collector;

/**
 * Class Stack_Exchange_Collector
 *
 * @package WPTalents\Collector
 */
class Stack_Exchange_Collector {
	/**
	 * Retrieve data from WordPress Stack Exchange
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool
	 */
	public static function retrieve_data( $user_id ) {
		$name = sanitize_title( xprofile_get_field_data( 'WordPress Stack Exchange', $user_id ) );

		if ( '' === $name ) {
			return false;
		}

		$url = 'https://api.stackexchange.com/2.2/users?page=1&pagesize=1&order=desc&sort=reputation&inname=' . $name . '&site=wordpress';

		$body = wp_remote_retrieve_body( wp_safe_remote_get( $url ) );

		if ( '' === $body ) {
			return false;
		}

		$body = json_decode( $body );

		if ( null === $body || ! isset( $body->items[0] ) ) {
			return false;
		}

		update_user_meta( $user_id, '_wptalents_wpse', $body->items[0] );

		return true;
	}
}
