<?php

namespace WPTalents\Collector;

/**
 * Class Contribution_Collector
 * @package WPTalents\Collector
 */
class Contribution_Collector extends Collector {

	/**
	 * Initialize the collector.
	 *
	 * @param \WP_User $user
	 */
	public function __construct( \WP_User $user ) {

		$this->expiration = 4 * WEEK_IN_SECONDS;

		parent::__construct( $user );
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
	public function _retrieve_data() {

		global $wp_version;

		$contributions = array();

		foreach ( range( 3.2, $wp_version, 0.1 ) as $version ) {
			$version = number_format( $version, 1 );
			$role = $this->_loop_wp_version( $version, $this->options['username'] );

			if ( $role ) {
				$contributions[ $version ] = $role;
			}
		}

		return $contributions;

	}

	/**
	 * @param string $version The WP version to check.
	 *
	 * @return bool|string The user's role on success, false otherwise.
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

		$response = wp_remote_retrieve_body( wp_safe_remote_get(
			'https://api.wordpress.org/core/credits/1.1/?version=' . $wp_version . '&locale=' . $locale
		) );

		if ( '' === $response ) {
			return false;
		}

		$results = json_decode( $response, true );

		if ( ! is_array( $results ) || $results['data']['version'] != (string) $wp_version ) {
			return false;
		}

		set_transient( 'wordpress-credits-' . $wp_version, $results, $this->expiration );

		return $results;

	}

}