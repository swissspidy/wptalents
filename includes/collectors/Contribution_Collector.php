<?php

namespace WPTalents\Collector;
use \WP_Post;

class Contribution_Collector extends Collector {

	public function __construct( WP_Post $post ) {

		$this->expiration = 4 * WEEK_IN_SECONDS;

		parent::__construct( $post );
	}

	/**
	 * @access public
	 * @return mixed
	 */
	public function get_data() {

		return $this->_retrieve_data();

	}

	/**
	 * @access protected
	 *
	 * @return bool
	 */
	protected function _retrieve_data() {

		global $wp_version;

		$version = number_format( $wp_version, 1, '.', '' );

		$contributions = array();
		while ( $version ) {
			$version = number_format( $version, 1, '.', '' );

			$role = $this->_loop_wp_version( $version, $this->options['username'] );

			if ( false !== $role ) {
				if ( $role ) {
					$contributions[ $version ] = $role;
				}

				$version -= 0.1;
			} else {
				$version = false;
			}
		}

		return $contributions;

	}

	/**
	 * @param string $version The WP version to check.
	 *
	 * @return bool|string| The user's role on success, false otherwise.
	 */
	protected function _loop_wp_version( $version ) {

		if ( false === ( $credits = get_transient( 'wordpress-credits-' . $version ) ) ) {
			$credits = $this->_get_credits( $version );
		}

		if ( $credits ) {

			foreach ( $credits['groups'] as $group_slug => $group_data ) {
				if ( 'libraries' == $group_data['type'] ) {
					continue;
				}

				foreach ( $group_data['data'] as $person_username => $person_data ) {
					if ( strtolower( $person_username ) == $this->options['username'] ) {
						if ( 'titles' == $group_data['type'] ) {
							if ( $person_data[3] ) {
								$role = $person_data[3];
							} else if ( $group_data['name'] ) {
								$role = $group_data['name'];
							} else {
								$role = ucfirst( str_replace( '-', ' ', $group_slug ) );
							}

							$role = rtrim( $role, 's' );
						} else {
							$role = __( 'Core Contributor', 'wptalents' );
						}

						return $role;
					}
				}
			}
		}

		return false;

	}

	/**
	 * Retrieve the contributor credits.
	 *
	 * @param string $wp_version The WordPress version.
	 * @param string $locale     Locale to get translation data for
	 *
	 * @return array|bool A list of all of the contributors, or false on error.
	 */
	protected function _get_credits( $wp_version, $locale = '' ) {

		// We can't request data before this.
		if ( version_compare( $wp_version, '3.2', '<' ) ) {
			return false;
		}

		$response = wp_remote_get( 'http://api.wordpress.org/core/credits/1.1/?version=' . $wp_version . '&locale=' . $locale );

		if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$results = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $results ) || $results['data']['version'] != (string) $wp_version ) {
			return false;
		}

		set_transient( 'wordpress-credits-' . $wp_version, $results, $this->expiration );

		return $results;

	}

}