<?php

namespace WPTalents\Collector;

use \DOMDocument;
use \DOMXPath;
use \DateTime;

/**
 * Class Profile_Collector
 * @package WPTalents\Collector
 */
class Profile_Collector extends Collector {

	/**
	 * @access public
	 * @return mixed
	 */
	public function get_data() {

		$data = get_post_meta( $this->post->ID, '_profile', true );

		if ( ( ! $data ||
		       ( isset( $data['expiration'] ) && time() >= $data['expiration'] ) )
		     && $this->options['may_renew']
		) {
			add_action( 'shutdown', array( $this, '_retrieve_data' ) );
		}

		if ( ! $data ) {
			return 0;
		}

		return $data['data'];
	}

	/**
	 * @access protected
	 *
	 * @return bool
	 */
	public function _retrieve_data() {

		$url = 'https://profiles.wordpress.org/' . $this->options['username'];

		$body = wp_remote_retrieve_body( wp_safe_remote_get( $url ) );

		if ( '' === $body ) {
			return false;
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

		if ( $website->length ) {
			$data['website'] = trim( $website->item( 0 )->getAttribute( 'href' ) );
		}

		foreach ( $badges as $badge ) {
			$data['badges'][] = $badge->getAttribute( 'title' );
		}

		$location = get_post_meta( $this->post->ID, 'location' );

		if ( empty( $location ) ) {
			update_post_meta( $this->post->ID, 'location', $data['location'] );
		}

		$social = (array) get_post_meta( $this->post->ID, 'social' );

		if ( ! isset( $social['url'] ) && isset( $data['website'] ) ) {
			$social['url'] = $data['website'];
			update_post_meta( $this->post->ID, 'social', $social );
		}

		if ( $this->post->post_title === $this->options['username'] && ! empty( $data['name'] ) ) {
			$this->post->post_title = (string) $data['name'];

			if ( empty( $this->post->post_name ) ) {
				$this->post->post_name = sanitize_title( $this->post->post_title );
			}
		}

		if ( '' === $this->post->post_content ) {
			$this->post->post_content = $description;
		}

		wp_update_post( $this->post );

		if ( ! empty( $data['company'] ) && 'person' === $this->post->post_type )  {
			if ( '' === get_post_meta( $this->post->ID, 'job', true ) ) {
				update_post_meta( $this->post->ID, 'job', $data['company'] );
			}
		}

		$data = array(
			'data'       => $data,
			'expiration' => time() + $this->expiration,
		);

		update_post_meta( $this->post->ID, '_profile', $data );

		return $data;

	}

}