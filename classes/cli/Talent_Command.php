<?php
/**
 * Talent WP-CLI command.
 *
 * @package WPTalents
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

		// Only works if provided a single username.
		if ( 1 < count( $args ) ) {
			$assoc_args['name'] = '';
		}

		foreach ( $args as $talent ) {
			$this->import( $talent, $assoc_args['name'], $assoc_args['type'] );
		}
	}

	/**
	 * Import a user from WordPress.org.
	 *
	 * @param string $username WordPress.org username.
	 * @param string $name     Talent's full name.
	 * @param string $type     Talent type (person or company).
	 */
	protected function import( $username, $name, $type ) {
		$importer = new Importer( $username, $name, $type );
		WP_CLI::line( 'Importing ' . $username . '...' );

		if ( ! $importer->remote_user_exists() ) {
			WP_CLI::error( 'No such user exists on WordPress.org.' );
		}

		$result = $importer->import();

		if ( is_wp_error( $result ) ) {
			WP_CLI::warning( $result->get_error_message() );
		} else {
			WP_CLI::success( sprintf( 'Successfully imported %s. User ID: %s', $result->user_login, $result->ID ) );
			WP_CLI::line( sprintf( 'You may want to activate the user now by running `wp talent activate <%s>`', $result->user_login ) );
		}
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

		$user = get_user_by( 'login', $username );

		if ( ! is_a( $user, 'WP_User' ) ) {
			WP_CLI::warning( __( 'Talent does not exist!', 'wptalents' ) );

			return;
		}

		// Activate signup.
		bp_core_activate_signup( wp_hash( $user->ID ) );

		WP_CLI::success( __( 'Talent successfully activated! ', 'wptalents' ) );
	}
}
