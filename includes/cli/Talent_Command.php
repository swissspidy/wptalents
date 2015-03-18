<?php
/**
 * Created by PhpStorm.
 * User: Pascal
 * Date: 19.12.14
 * Time: 09:50
 */

namespace WPTalents\CLI;

use WP_CLI;
use WPTalents\Core\Importer;

/**
 * Import and update WordPress talents.
 */
class Talent_Command extends \WP_CLI_Command {

	/**
	 * Import a talent.
	 *
	 * ## OPTIONS
	 *
	 * <username>...
	 * : One or more WordPress.org usernames to import.
	 *
	 * [--name]
	 * : Overwrite the talent's full name (only works when importing 1 talent).
	 *
	 * [--type]
	 * : The talent type (person or company).
	 *
	 * ## EXAMPLES
	 *
	 * wp talent add johndoe --name=John Doe --type=person
	 * wp talent add johndoe janedoe
	 */
	public function add( $args, $assoc_args ) {
		$defaults = array(
			'type' => 'person',
			'name' => '',
		);

		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		// Only person and company are allowed values
		if ( 'person' !== $assoc_args['type'] ) {
			$assoc_args['type'] = 'company';
		}

		// Only works if provided a single username
		if ( 1 < count( $assoc_args ) ) {
			$assoc_args['name'] = '';
		}

		foreach ( $args as $talent ) {
			$this->import( $talent, $assoc_args['name'], $assoc_args['type'] );
		}
	}

	/**
	 * @param string $username WordPress.org username.
	 * @param string $name     Talent's full name.
	 * @param string $type     Talent type (person or company).
	 */
	protected function import( $username, $name, $type ) {
		$importer = new Importer( $username, $name, $type );
		WP_CLI::line( 'Importing ' . $username . '...' );

		$result = $importer->import();

		if ( is_wp_error( $result ) ) {
			/** @var \WP_Error $status */
			WP_CLI::warning( $result->get_error_message() );
		} else {
			WP_CLI::success( sprintf( 'Successfully imported %s. User ID: %s', $result->user_nicename, $result->ID ) );
		}
	}

	/**
	 * Update the talent's data.
	 *
	 * ## OPTIONS
	 *
	 * <username>
	 * : The talent's WordPress.org username
	 *
	 * [--force-update]
	 * : Update even data that hasn't expired yet.
	 *
	 * ## EXAMPLES
	 *
	 * wp talent update johndoe --force-update
	 * wp talent update automattic
	 *
	 * @synopsis <username> [--force-update]
	 */
	public function update( $args, $assoc_args ) {
		list( $username ) = $args;

		$defaults = array(
			'force-update' => false,
		);

		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		$user = get_user_by( 'slug', $username );

		if ( ! is_a( $user, 'WP_User' ) ) {
			WP_CLI::warning( __( 'Talent does not exist!', 'wptalents' ) );

			return;
		}


		WP_CLI::line( sprintf( __( 'Updating %s (ID: %d)...', 'wptalents' ), $user->user_login, $user->ID ) );

		/** @var \WPTalents\Collector\Collector[] $collectors */
		$collectors = array(
			'Changeset_Collector',
			'Codex_Collector',
			'Contribution_Collector',
			'Forums_Collector',
			'Gravatar_Collector',
			'Plugin_Collector',
			'Profile_Collector',
			'Score_Collector',
			'Theme_Collector',
			'WordPressTv_Collector',
		);

		/** @var \WPTalents\Collector\Collector $collector */
		foreach ( $collectors as $collector ) {
			$collector = '\\WPTalents\\Collector\\' . $collector;
			$collector = new $collector( $user );

			if ( true === $assoc_args['force-update'] ) {
				$collector->_retrieve_data();
			}
		}

		WP_CLI::success( __( 'Talent successfully updated!', 'wptalents' ) );
	}

	/**
	 * Fake activate a newly added user.
	 *
	 * This sets the user's time of last activity to now.
	 *
	 * Without this, the user won't be showing up in the BuddyPress members directory.
	 *
	 * ## OPTIONS
	 *
	 * <username>
	 * : The talent's WordPress.org username
	 *
	 * ## EXAMPLES
	 *
	 * wp talent activate johndoe
	 *
	 * @synopsis <username>
	 */
	public function activate( $args ) {
		list( $username ) = $args;

		$user = get_user_by( 'slug', $username );

		if ( ! is_a( $user, 'WP_User' ) ) {
			WP_CLI::warning( __( 'Talent does not exist!', 'wptalents' ) );

			return;
		}

		// Switch to the user and back again
		bp_update_user_last_activity( $user->ID );

		// Add activity item
		$userlink = bp_core_get_userlink( $user->ID );

		bp_activity_add( array(
			'user_id'   => $user->ID,
			'action'    => sprintf( __( '%s was added to WP Talents', 'wptalents' ), $userlink ),
			'component' => 'profile',
			'type'      => 'user_created',
		) );

		WP_CLI::success( __( 'Talent successfully activated! ', 'wptalents' ) );
	}

}

WP_CLI::add_command( 'talent', 'WPTalents\CLI\Talent_Command' );