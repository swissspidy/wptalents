<?php

namespace WPTalents\Collector;

/**
 * Class Changeset_Collector
 * @package WPTalents\Collector
 */
class Changeset_Collector extends Collector {

	/**
	 * @access public
	 * @return mixed
	 */
	public function get_data() {

		$data = get_user_meta( $this->user->ID, '_wptalents_changesets', true );

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

		$url = add_query_arg(
			array(
				'q'           => 'props+' . $this->options['username'],
				'noquickjump' => '1',
				'changeset'   => 'on',
			),
			'https://core.trac.wordpress.org/search'
		);

		$body = wp_remote_retrieve_body( wp_safe_remote_get( $url ) );

		if ( '' === $body ) {
			return new \WP_Error( 'retrieval_failed', __( 'Could not retrieve data', 'wptalents' ) );
		}

		$dom = new \DOMDocument();

		libxml_use_internal_errors( true );
		$dom->loadHTML( $body );
		libxml_clear_errors();

		/** @var \DOMXPath $finder */
		$finder = new \DOMXPath( $dom );

		// Count

		$count = $finder->query( '/html/head/meta[@name="totalResults"]' )->item( 0 )->getAttribute( 'content' );

		$data = array(
			'data'       => array(
				'count' => $count,
			),
			'expiration' => time() + $this->expiration,
		);

		// All Changesets

		/** @var \DOMNode $results */
		$results = $finder->query( '//dl[@id="results"]/dt' );

		/** @var \DOMNode $child */
		foreach ( $results as $node ) {
			$changeset   = explode( '/', $finder->query( 'a', $node )->item( 0 )->getAttribute( 'href' ) );
			$description = explode( ':', $finder->query( 'a', $node )->item( 0 )->nodeValue, 2 );
			preg_match( '/#([0-9]*)/', $finder->query( '../dd', $node )->item( 0 )->nodeValue, $matches );

			$data['changesets'][] = array(
				'changeset'   => $changeset[2],
				'description' => $description[1],
				'ticket'      => $matches[1],
			);
		}

		update_user_meta( $this->user->ID, '_wptalents_changesets', $data );

		return $data;

	}

}