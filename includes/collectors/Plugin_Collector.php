<?php

namespace WPTalents\Collector;

class Plugin_Collector extends Collector {

	/**
	 * @access public
	 * @return mixed
	 */
	public function get_data() {

		$data = get_post_meta( $this->post->ID, '_plugins', true );

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

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$args = array(
			'author'   => $this->options['username'],
			'per_page' => 100,
			'fields'   => array(
				'description'   => false,
				'compatibility' => false,
				//'banners'     => true,
				'icons'         => true,
				'downloaded'    => true,
				'last_updated'  => true,
			),
		);

		$data = plugins_api( 'query_plugins', $args );

		if ( $data && isset( $data->plugins ) ) {

			$data = array(
				'data'       => $data->plugins,
				'expiration' => time() + $this->expiration,
			);

			update_post_meta( $this->post->ID, '_plugins', $data );

			return $data;
		}

		return false;

	}

}