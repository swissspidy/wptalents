<?php

class WP_Talents_Profile_Collector extends WP_Talents_Data_Collector {

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

		$request = wp_remote_get( $url, array( 'redirection' => 0 ) );
		$code    = wp_remote_retrieve_response_code( $request );

		if ( 200 !== $code ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $request );

		$dom = new DOMDocument();
		@$dom->loadHTML( $body ); // Error supressing due to the fact that special characters haven't been converted to HTML.
		$finder = new DomXPath( $dom );

		$name     = $finder->query( '//h2[@class="fn"]' );
		$avatar   = $finder->query( '//div[@id="meta-status-badge-container"]/a/img' );
		$location = $finder->query( '//li[@id="user-location"]' );
		$website  = $finder->query( '//li[@id="user-website"]/a' );
		$company  = $finder->query( '//li[@id="user-company"]' );
		$badges   = $finder->query( '//ul[@id="user-badges"]/li/div' );

		$data = array(
			'name'     => trim( $name->item( 0 )->nodeValue ),
			'avatar'   => strtok( $avatar->item( 0 )->getAttribute( 'src' ), '?' ),
			'location' => trim( $location->item( 0 )->nodeValue ),
			'company'  => '',
			'website'  => '',
			'badges'   => array(),
		);

		if ( $company->length ) {
			$data['company'] = trim( preg_replace( '/\t+/', '', $company->item( 0 )->nodeValue ) );
		}

		if ( $website->length ) {
			$data['website'] = trim( $website->item( 0 )->getAttribute( 'href' ) );
		}

		foreach ( $badges as $badge ) {
			$data['badges'][] = $badge->getAttribute( 'title' );
		}

		$data = array(
			'data'       => $data,
			'expiration' => time() + $this->expiration,
		);

		update_post_meta( $this->post->ID, '_profile', $data );

		return $data;

	}

}