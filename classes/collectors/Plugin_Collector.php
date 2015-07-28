<?php
/**
 * WordPress.org plugins collector that is hooked to a cron event.
 *
 * @package WPTalent
 */

namespace WPTalents\Collector;

/**
 * Class Plugin_Collector
 * @package WPTalents\Collector
 */
class Plugin_Collector {
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

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$args = array(
			'author'   => $user->user_login,
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
			return update_user_meta( $user_id, '_wptalents_plugins', $data->plugins );
		}

		return false;
	}
}
