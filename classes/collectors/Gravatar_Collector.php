<?php
/**
 * Gravatar profile collector that is hooked to a cron event.
 *
 * @package WPTalent
 */

namespace WPTalents\Collector;

/**
 * Class Gravatar_Collector
 *
 * @package WPTalents\Collector
 */
class Gravatar_Collector {
	/**
	 * Retrieve data from the Gravatar profile of a user.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool
	 */
	public static function retrieve_data( $user_id ) {
		$avatar = bp_get_user_meta( $user_id, '_wptalents_avatar', true );

		if ( '' === $avatar ) {
			return false;
		}

		$url = esc_url( $avatar );

		if ( 0 === strpos( $avatar, '//' ) ) {
			$url = str_replace( '//www', 'https://www', $url );
		}

		$url = str_replace( 'secure.gravatar.com', 'www.gravatar.com', $url );
		$url = str_replace( '/avatar/', '/', $url );
		$url = remove_query_arg( array( 's', 'd' ), $url ) . '.json';

		$body = wp_remote_retrieve_body( wp_safe_remote_get( $url ) );

		if ( '' === $body ) {
			return false;
		}

		$body = json_decode( $body );

		if ( null === $body || ! isset( $body->entry[0] ) ) {
			return false;
		}

		if ( isset( $body->entry[0]->accounts ) ) {
			foreach ( $body->entry[0]->accounts as $account ) {
				switch ( $account->shortname ) {
					case 'linkedin':
						if ( '' === xprofile_get_field_data( 'LinkedIn', $user_id ) ) {
							xprofile_set_field_data( 'LinkedIn', $user_id, $account->url );
						}
						break;
					case 'twitter';
						if ( '' === xprofile_get_field_data( 'Twitter', $user_id ) ) {
							xprofile_set_field_data( 'Twitter', $user_id, $account->username );
						}
						break;
					case 'facebook';
						if ( '' === xprofile_get_field_data( 'Facebook', $user_id ) ) {
							xprofile_set_field_data( 'Facebook', $user_id, $account->username );
						}
						break;
					case 'google':
						if ( '' === xprofile_get_field_data( 'Google+', $user_id ) ) {
							xprofile_set_field_data( 'Google+', $user_id, $account->userid );
						}
						break;
					case 'wordpress':
						if ( '' === xprofile_get_field_data( 'Website', $user_id ) ) {
							xprofile_set_field_data( 'Website', $user_id, $account->url );
						}
						break;
					default:
						break;
				}
			}
		}

		if ( '' === xprofile_get_field_data( 'Website', $user_id ) && ! empty( $body->entry[0]->urls ) ) {
			xprofile_set_field_data( 'Website', $user_id, $body->entry[0]->urls[0]->value );
		}

		return true;
	}
}
