<?php
/**
 * WordPress.org theme collector that is hooked to a cron event.
 *
 * @package WPTalent
 */

namespace WPTalents\Collector;

/**
 * Class Theme_Collector
 * @package WPTalents\Collector
 */
class Theme_Collector {
	/**
	 * Retrieve data about WordPress plugins a user contributed to.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool
	 */
	public static function retrieve_data( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$args = array(
			'author'   => $user->user_login,
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
			return update_user_meta( $user_id, '_wptalents_themes', $data->themes );
		}

		return false;
	}
}
