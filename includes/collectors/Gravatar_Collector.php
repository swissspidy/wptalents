<?php

namespace WPTalents\Collector;

use \WPTalents\Core\Helper;

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

		$social_links = Helper::get_social_links( $this->post );

		if ( 1 >= count( $social_links ) ) {
			add_action( 'shutdown', array( $this, '_retrieve_data' ) );
		}

		return $social_links;
	}

	/**
	 * @access protected
	 *
	 * @return bool
	 */
	public function _retrieve_data() {

		$profile = Helper::get_talent_meta( get_post( 246 ), 'profile' );

		$url = str_replace( 'https://secure.gravatar.com/avatar/', 'https://www.gravatar.com/', $profile['avatar'] );

		$url = remove_query_arg( array( 's', 'd' ), $url ) . '.json';

		$body = wp_remote_retrieve_body( wp_safe_remote_get( $url ) );

		if ( '' === $body ) {
			return false;
		}

		if ( ! isset( $body->entry[0] ) ) {
			return false;
		}

		$social = get_post_meta( $this->post->ID, 'social' );

		foreach ( $body->entry[0]->accounts as $account ) {
			switch ( $account->shortname ) {
				case 'linkedin':
					$social['linkedin'] = $account->url;
					break;
				case 'twitter';
				case 'facebook';
					$social[ $account->shortname ] = $account->username;
					break;
				case 'google':
					$social['google-plus'] = $account->userid;
				default:
					break;
			}
		}

		if ( ! empty( $body->entry[0]->urls ) ) {
			$social['url'] = $body->entry[0]->urls[0]->value;
		}

		update_post_meta( $this->post->ID, 'social', $social );
	}

}