<?php
/**
 * WordPress Code collector that is hooked to a cron event.
 *
 * @package WPTalent
 */

namespace WPTalents\Collector;

/**
 * Class Codex_Collector
 *
 * @package WPTalents\Collector
 */
class Codex_Collector {
	/**
	 * Retrieve data from the Gravatar profile of a user.
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

		$username = $user->user_login;

		$backup_username = xprofile_get_field_data( 'WordPress.org Username', $user_id );

		if ( '' !== $backup_username ) {
			$username = $backup_username;
		}

		$results_url = add_query_arg(
			array(
				'action'  => 'query',
				'list'    => 'users',
				'ususers' => $username,
				'usprop'  => 'editcount',
				'format'  => 'json',
			),
			'https://codex.wordpress.org/api.php'
		);

		$results = wp_remote_retrieve_body( wp_safe_remote_get( $results_url ) );

		if ( is_wp_error( $results ) ) {
			return false;
		}

		$raw = json_decode( $results );

		if ( isset( $raw->query->users[0]->editcount ) ) {
			$count = absint( $raw->query->users[0]->editcount );
		} else {
			$count = 0;
		}

		bp_update_user_meta( $user_id, '_wptalents_codex_count', $count );

		return true;
	}
}
