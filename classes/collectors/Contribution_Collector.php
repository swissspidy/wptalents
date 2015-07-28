<?php
/**
 * Contribution collector that is hooked to a cron event.
 *
 * @package WPTalent
 */

namespace WPTalents\Collector;

/**
 * Class Contribution_Collector
 * @package WPTalents\Collector
 */
class Contribution_Collector {
	/**
	 * @access protected
	 *
	 * @return bool
	 */
	public static function retrieve_data() {
		global $wp_version;

		$stable_version = $wp_version;

		// If we're on a beta release, fall back to the latest stable.
		if ( false !== strpos( $stable_version, '-' ) ) {
			list( $stable_version ) = explode( '-', $wp_version );
			$stable_version -= 0.1;
		}

		foreach ( range( 3.2, $stable_version, 0.1 ) as $version ) {
			self::_get_credits( number_format( $version, 1 ) );
		}
	}

	/**
	 * Retrieve the contributor credits.
	 *
	 * @param string $wp_version The WordPress version.
	 * @param string $locale     Locale to get translation data for
	 *
	 * @return bool
	 */
	protected static function _get_credits( $wp_version, $locale = '' ) {
		$response = wp_remote_retrieve_body( wp_safe_remote_get(
			'https://api.wordpress.org/core/credits/1.1/?version=' . $wp_version . '&locale=' . $locale
		) );

		if ( '' === $response ) {
			return false;
		}

		$results = json_decode( $response, true );

		if ( ! is_array( $results ) || (string) $wp_version !== $results['data']['version'] ) {
			return false;
		}

		return update_option( 'wptalents_wordpress_credits_' . $wp_version, $results, false );
	}
}
