<?php

namespace WPTalents\Collector;

/**
 * Class Gravatar_Collector
 * @package WPTalents\Collector
 */
class Gravatar_Collector extends Collector {

	/**
	 * @access public
	 * @return mixed
	 */
	public function get_data() {

		$expiration = get_user_meta( $this->user->ID, '_wptalents_gravatar_expiration', true );

		if ( ( ! $expiration ||
		       ( ! empty( $expiration ) && time() >= $expiration ) )
		     && $this->options['may_renew']
		) {
			add_action( 'shutdown', array( $this, '_retrieve_data' ) );
		}

	}

	/**
	 * @access protected
	 *
	 * @return bool
	 */
	public function _retrieve_data() {

		$avatar = get_user_meta( $this->user->ID, '_wptalents_avatar', true );

		$url = str_replace( 'https://secure.gravatar.com/avatar/', 'https://www.gravatar.com/', $avatar );

		$url = remove_query_arg( array( 's', 'd' ), $url ) . '.json';

		$response = wp_safe_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( wp_safe_remote_get( $url ) );

		if ( '' === $body ) {
			return new \WP_Error( 'retrieval_failed', __( 'Could not retrieve data', 'wptalents' ) );
		}

		$body = json_decode( $body );

		if ( null === $body ) {
			return false;
		}

		if ( ! isset( $body->entry[0] ) ) {
			return false;
		}

		if ( isset( $body->entry[0]->accounts ) ) {
			foreach ( $body->entry[0]->accounts as $account ) {
				switch ( $account->shortname ) {
					case 'linkedin':
						if ( '' === xprofile_get_field_data( 'LinkedIn', $this->user->ID ) ) {
							xprofile_set_field_data( 'LinkedIn', $this->user->ID, $account->url );
						}
						break;
					case 'twitter';
						if ( '' === xprofile_get_field_data( 'Twitter', $this->user->ID ) ) {
							xprofile_set_field_data( 'Twitter', $this->user->ID, $account->username );
						}
						break;
					case 'facebook';
						if ( '' === xprofile_get_field_data( 'Facebook', $this->user->ID ) ) {
							xprofile_set_field_data( 'Facebook', $this->user->ID, $account->username );
						}
						break;
					case 'google':
						if ( '' === xprofile_get_field_data( 'Google+', $this->user->ID ) ) {
							xprofile_set_field_data( 'Google+', $this->user->ID, $account->userid );
						}
						break;
					case 'wordpress':
						if ( '' === xprofile_get_field_data( 'Website', $this->user->ID ) ) {
							xprofile_set_field_data( 'Website', $this->user->ID, $account->url );
						}
					default:
						break;
				}
			}
		}

		if ( '' === xprofile_get_field_data( 'Website', $this->user->ID ) && ! empty( $body->entry[0]->urls ) ) {
			xprofile_set_field_data( 'Website', $this->user->ID, $body->entry[0]->urls[0]->value );
		}

		return true;
	}

}