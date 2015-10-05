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
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$args = array(
			'author'   => $user->user_login,
			'per_page' => 1000,
			'fields'   => array(
				'author'             => false,
				'banners'            => true,
				'compatibility'      => false,
				'description'        => true,
				'downloaded'         => true,
				'extended_author'    => true,
				'homepage'           => false,
				'icons'              => true,
				'last_updated'       => true,
				'num_ratings'        => false,
				'parent'             => true,
				'photon_screenshots' => true,
				'ratings'            => false,
				'theme_url'          => true,
			),
		);

		$data = themes_api( 'query_themes', $args );

		if ( isset( $data->themes ) ) {
			$themes = array();

			foreach ( $data->themes as $theme ) {
				$themes[] = self::process_theme_data( $theme );
			}

			bp_update_user_meta( $user_id, '_wptalents_themes', $themes );
		}

		// Get user's favorites.
		$args['browse'] = 'favorites';
		$args['user']   = $args['author'];
		unset( $args['author'] );

		$data = themes_api( 'query_themes', $args );

		if ( isset( $data->themes ) ) {
			$themes = array();

			foreach ( $data->themes as $theme ) {
				$themes[] = self::process_theme_data( $theme );
			}

			bp_update_user_meta( $user_id, '_wptalents_favorite_themes', $themes );
		}
	}

	/**
	 * Prepare theme object for storage in the database.
	 *
	 * @param \stdClass $theme Theme object.
	 *
	 * @return array
	 */
	public static function process_theme_data( $theme ) {
		return array(
			'description'    => $theme->description,
			'downloaded'     => $theme->downloaded,
			'last_updated'   => $theme->last_updated,
			'name'           => $theme->name,
			'rating'         => floatval( $theme->rating ),
			'screenshot_url' => $theme->screenshot_url,
			'slug'           => $theme->slug,
			'version'        => $theme->version,
		);
	}
}
