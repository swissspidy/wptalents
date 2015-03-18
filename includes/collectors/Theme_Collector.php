<?php

namespace WPTalents\Collector;

/**
 * Class Theme_Collector
 * @package WPTalents\Collector
 */
class Theme_Collector extends Collector {

	/**
	 * @access public
	 * @return mixed
	 */
	public function get_data() {

		$data = get_user_meta( $this->user->ID, '_wptalents_themes', true );

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

		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$args = array(
			'author'   => $this->options['username'],
			'per_page' => 30,
			'fields'   => array(
				'description'   => false,
				'compatibility' => false,
				//'banners'     => true,
				'icons'         => true,
				'downloaded'    => true,
				'last_updated'  => true,
			),
		);

		$data = themes_api( 'query_themes', $args );

		if ( $data && isset( $data->themes ) ) {

			$data = array(
				'data'       => $data->themes,
				'expiration' => time() + $this->expiration,
			);

			update_user_meta( $this->user->ID, '_wptalents_themes', $data );

			return $data;
		}

		return false;

	}

}