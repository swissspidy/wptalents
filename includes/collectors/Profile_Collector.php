<?php

namespace WPTalents\Collector;

use DateTime;
use DOMDocument;
use DOMXPath;

/**
 * Class Profile_Collector
 * @package WPTalents\Collector
 */
class Profile_Collector extends Collector {

	/**
	 * @access public
	 */
	public function get_data() {

		$expiration = get_user_meta( $this->user->ID, '_wptalents_profile_expiration', true );

		if ( ( ! $expiration ||
		       ( ! empty( $expiration ) && time() >= $expiration ) )
		     && $this->options['may_renew']
		) {
			add_action( 'shutdown', array( $this, '_retrieve_data' ) );
		}

		// todo: which fields should be returned here?
		return array(
			'badges' => xprofile_get_field_data( 'Badges', $this->user->ID )
		);

	}

	/**
	 * @access protected
	 *
	 * @return bool
	 */
	public function _retrieve_data() {

		$url = 'https://profiles.wordpress.org/' . $this->options['username'];

		$response = wp_safe_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( wp_safe_remote_get( $url ) );

		if ( '' === $body ) {
			return new \WP_Error( 'retrieval_failed', __( 'Could not retrieve data', 'wptalents' ) );
		}

		$dom = new DOMDocument();

		libxml_use_internal_errors( true );
		$dom->loadHTML( $body );
		libxml_clear_errors();

		$finder = new DOMXPath( $dom );

		$name         = $finder->query( '//h2[@class="fn"]' );
		$avatar       = $finder->query( '//div[@id="meta-status-badge-container"]/a/img' );
		$description  = $finder->query( '//div[@class="item-meta-about"]' );
		$location     = $finder->query( '//li[@id="user-location"]' );
		$member_since = $finder->query( '//li[@id="user-member-since"]' );
		$website      = $finder->query( '//li[@id="user-website"]/a' );
		$company      = $finder->query( '//li[@id="user-company"]' );
		$slack        = $finder->query( '//li[@id="slack-username"]' );
		$badges       = $finder->query( '//ul[@id="user-badges"]/li/div' );

		$data = array(
			'name'         => trim( $name->item( 0 )->nodeValue ),
			'avatar'       => strtok( $avatar->item( 0 )->getAttribute( 'src' ), '?' ),
			'location'     => '',
			'company'      => '',
			'member_since' => '',
			'website'      => '',
			'badges'       => array(),
		);

		preg_match( '/((([^ ]*)[\s.]+){3})$/', $member_since->item( 0 )->nodeValue, $matches );

		$date                 = new DateTime( $matches[0] );
		$data['member_since'] = $date->format( 'Y-m-d' );

		if ( $description->length ) {
			$description = trim( $description->item( 0 )->nodeValue );
		} else {
			$description = '';
		}

		if ( $location->length ) {
			$data['location'] = trim( $location->item( 0 )->nodeValue );
		}

		if ( $company->length ) {
			$data['company'] = trim( preg_replace( '/\t+/', '', $company->item( 0 )->nodeValue ) );
		}

		if ( $slack->length ) {
			$data['slack'] = trim( preg_replace( '/\t+/', '', $slack->item( 0 )->nodeValue ) );
			$username      = explode( ' ', $data['slack'] );
			$data['slack'] = substr( trim( $username[0] ), 1 );
		}

		if ( $website->length ) {
			$data['website'] = trim( $website->item( 0 )->getAttribute( 'href' ) );
		}

		foreach ( $badges as $badge ) {
			$data['badges'][] = $badge->getAttribute( 'title' );
		}

		update_user_meta( $this->user->ID, '_wptalents_avatar', $data['avatar'] );

		if ( '' === xprofile_get_field_data( 'Badges', $this->user->ID ) ) {
			xprofile_set_field_data( 'Badges', $this->user->ID, $data['badges'] );
		}

		if ( '' === xprofile_get_field_data( 'Location', $this->user->ID ) ) {
			xprofile_set_field_data( 'Location', $this->user->ID, $data['location'] );
		}

		if ( '' === xprofile_get_field_data( 'Bio', $this->user->ID ) ) {
			xprofile_set_field_data( 'Bio', $this->user->ID, $description );
		}

		if ( '' === xprofile_get_field_data( 'Website', $this->user->ID ) ) {
			xprofile_set_field_data( 'Website', $this->user->ID, $description );
		}

		if ( '' === xprofile_get_field_data( 'Name', $this->user->ID ) && ! empty( $data['name'] ) ) {
			xprofile_set_field_data( 'Name', $this->user->ID, $data['name'] );
		}

		if ( '' === xprofile_get_field_data( 'Slack', $this->user->ID ) ) {
			xprofile_set_field_data( 'Slack', $this->user->ID, $data['slack'] );
		}

		if ( ! empty( $data['company'] ) && 'person' === bp_get_member_type( $this->user->ID ) ) {
			if ( '' === xprofile_get_field_data( 'Job', $this->user->ID ) ) {
				xprofile_set_field_data( 'Job', $this->user->ID, $data['company'] );
			}
		}

		update_user_meta( $this->user->ID, '_wptalents_profile_expiration', time() + $this->expiration );

		return true;

	}

}