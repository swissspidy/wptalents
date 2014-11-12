<?php

namespace WPTalents\Collector;

class Codex_Collector extends Collector {

	/**
	 * @access public
	 * @return mixed
	 */
	public function get_data() {

		$data = get_post_meta( $this->post->ID, '_codex_count', true );

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

		$results_url = add_query_arg(
			array(
				'action'  => 'query',
				'list'    => 'users',
				'ususers' => $this->options['username'],
				'usprop'  => 'editcount',
				'format'  => 'json'
			),
			'https://codex.wordpress.org/api.php'
		);

		$response    = wp_remote_get( $results_url );

		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			$results = wp_remote_retrieve_body( $response );

			$raw   = json_decode( $results );
			$count = (int) $raw->query->users[0]->editcount;

			$data = array(
				'data'       => $count,
				'expiration' => time() + $this->expiration,
			);

			update_post_meta( $this->post->ID, '_codex_count', $data );

			return $data;
		}

		return false;

	}

}