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

		$data = get_post_meta( $this->post->ID, '_changeset_count', true );

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
				'q'           => 'props+' . $this->options['username'],
				'noquickjump' => '1',
				'changeset'   => 'on',
			),
			'https://core.trac.wordpress.org/search'
		);

		$results = wp_remote_retrieve_body( wp_safe_remote_get( $results_url ) );

		if ( is_wp_error( $results ) ) {
			return false;
		}

		$pattern = '/<meta name="totalResults" content="(\d*)" \/>/';

		preg_match( $pattern, $results, $matches );

		$count = 0;

		if ( isset( $matches[1] ) ) {
			$count = intval( $matches[1] );
		}

		$data = array(
			'data'       => $count,
			'expiration' => time() + $this->expiration,
		);

		update_post_meta( $this->post->ID, '_changeset_count', $data );

		return $data;

	}

}