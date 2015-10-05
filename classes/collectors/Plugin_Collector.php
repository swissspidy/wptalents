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
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$args = array(
			'author'   => $user->user_login,
			'per_page' => 1000,
			'fields'   => array(
				'author'          => false,
				'active_installs' => true,
				'banners'         => true,
				'compatibility'   => false,
				'description'     => false,
				'downloaded'      => false,
				'homepage'        => false,
				'icons'           => true,
				'last_updated'    => true,
				'num_ratings'     => false,
				'ratings'         => false,
			),
		);

		$data = plugins_api( 'query_plugins', $args );

		if ( isset( $data->plugins ) ) {
			$plugins = array();

			foreach ( $data->plugins as $plugin ) {
				$plugins[] = self::process_plugin_data( $plugin );
			}

			bp_update_user_meta( $user_id, '_wptalents_plugins', $plugins );
		}

		// Get user's favorites.
		$args['user'] = $args['author'];
		unset( $args['author'] );

		$data = plugins_api( 'query_plugins', $args );

		if ( isset( $data->plugins ) ) {
			$plugins = array();

			foreach ( $data->plugins as $plugin ) {
				$plugins[] = self::process_plugin_data( $plugin );
			}

			bp_update_user_meta( $user_id, '_wptalents_favorite_plugins', $plugins );
		}
	}

	/**
	 * Prepare plugin object for storage in the database.
	 *
	 * @param \stdClass $plugin Plugin object.
	 *
	 * @return array
	 */
	public static function process_plugin_data( $plugin ) {
		$banner = '';

		if ( ! empty( $plugin->banners ) ) {
			$banner = empty( $plugin->banners['high'] ) ? $plugin->banners['low'] : $plugin->banners['high'];
		}

		if ( ! empty( $plugin->icons['svg'] ) ) {
			$plugin_icon_url = $plugin->icons['svg'];
		} elseif ( ! empty( $plugin->icons['2x'] ) ) {
			$plugin_icon_url = $plugin->icons['2x'];
		} elseif ( ! empty( $plugin->icons['1x'] ) ) {
			$plugin_icon_url = $plugin->icons['1x'];
		} else {
			$plugin_icon_url = $plugin->icons['default'];
		}

		return array(
			'active_installs'   => $plugin->active_installs,
			'banner'            => $banner,
			'contributors'      => array_keys( $plugin->contributors ),
			'icon'              => $plugin_icon_url,
			'last_updated'      => $plugin->last_updated,
			'name'              => $plugin->name,
			'rating'            => floatval( $plugin->rating ),
			'short_description' => $plugin->short_description,
			'slug'              => $plugin->slug,
			'tested'            => $plugin->tested,
			'version'           => $plugin->version,
		);
	}
}
