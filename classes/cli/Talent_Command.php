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
	 * Holds the users that will be bulk synced.
	 *
	 * @var array
	 */
	private $users = array();

	/**
	 * Holds all of the users that failed to index during a bulk index.
	 *
	 * @var array
	 */
	private $failed_users = array();

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

	/**
	 * Index users with Elasticsearch using ElasticPress
	 *
	 * Requires prior setup using wp elasticsearch index --setup
	 *
	 * ## EXAMPLES
	 *
	 * wp talent index
	 */
	public function index() {
		$this->_connect_check();

		// Deactivate our search integration.
		$this->ep_deactivate();

		timer_start();

		WP_CLI::log( __( 'Indexing users...', 'wptalents' ) );

		$result = $this->_index_helper();

		WP_CLI::log( sprintf( __( 'Number of users synced: %d', 'wptalents' ), $result['synced'] ) );

		if ( ! empty( $result['errors'] ) ) {
			WP_CLI::error( sprintf( __( 'Number of user sync errors: %d', 'wptalents' ), count( $result['errors'] ) ) );
		}

		WP_CLI::log( WP_CLI::colorize( '%Y' . __( 'Total time elapsed: ', 'wptalents' ) . '%N' . timer_stop() ) );

		// Reactivate our search integration.
		$this->ep_activate();

		WP_CLI::success( __( 'Done!', 'wptalents' ) );
	}

	/**
	 * Helper method for indexing users.
	 *
	 * @param int $users_per_page Number of users to retrieve per page.
	 * @param int $page           Number of page we're on.
	 *
	 * @return array
	 */
	private function _index_helper( $users_per_page = 350, $page = 1 ) {
		global $wpdb;

		$synced = 0;
		$errors = array();

		while ( true ) {
			$args = apply_filters( 'ep_index_user_args', array(
				'per_page' => $users_per_page,
				'page'     => $page,
			) );

			$query = new \BP_User_Query( $args );

			if ( empty( $query->results ) ) {
				break;
			}

			foreach ( $query->results as $uid => $data ) {
				$result = $this->queue_user( $uid, count( $query->results ) );

				if ( ! $result ) {
					$errors[] = $uid;
				} else {
					$synced ++;
				}
			}

			WP_CLI::log( 'Indexed ' . ( $users_per_page * ( $page - 1 ) + count( $query->results ) ) . '/' . $query->total_users . ' entries. . .' );

			$page ++;

			usleep( 500 );

			// Avoid running out of memory.
			$wpdb->queries = array();
		}

		$this->send_bulk_errors();

		return array( 'synced' => $synced, 'errors' => $errors );
	}

	/**
	 * Queues up a user for bulk indexing
	 *
	 * @param int $user_id      User ID.
	 * @param int $bulk_trigger Bulk trigger.
	 *
	 * @return bool
	 */
	private function queue_user( $user_id, $bulk_trigger ) {
		static $user_count = 0;

		$args = \wptalents_ep_prepare_user( $user_id );

		// Put the user into the queue.
		$this->users[ $user_id ][] = '{ "index": { "_id": "' . absint( $user_id ) . '" } }';
		$this->users[ $user_id ][] = addcslashes( json_encode( $args ), "\n" );

		// Augment the counter.
		$user_count ++;

		// If we have hit the trigger, initiate the bulk request.
		if ( absint( $bulk_trigger ) === $user_count ) {
			$this->bulk_index();

			// Reset the user count.
			$user_count = 0;

			// Reset the users.
			$this->users = array();
		}

		return true;
	}

	/**
	 * Perform the bulk index operation
	 *
	 * @since 0.9.2
	 */
	private function bulk_index() {
		// Monitor how many times we attempt to add this particular bulk request.
		static $attempts = 0;

		// Augment the attempts.
		++ $attempts;

		// Make sure we actually have something to index.
		if ( empty( $this->users ) ) {
			WP_CLI::error( 'There are no users to index.' );
		}

		$flatten = array();

		foreach ( $this->users as $user ) {
			$flatten[] = $user[0];
			$flatten[] = $user[1];
		}

		// Make sure to add a new line at the end or the request will fail.
		$body = rtrim( implode( "\n", $flatten ) ) . "\n";

		// Show the content length in bytes if in debug.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			WP_CLI::log( 'Request string length: ' . size_format( mb_strlen( $body, '8bit' ), 2 ) );
		}

		// Decode the response.
		$response = wptalents_ep_bulk_index_users( $body );

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( implode( "\n", $response->get_error_messages() ) );
		}

		// If we did have errors, try to add the documents again.
		if ( isset( $response['errors'] ) && true === $response['errors'] ) {
			if ( $attempts < 5 ) {
				foreach ( $response['items'] as $item ) {
					if ( empty( $item['index']['error'] ) ) {
						unset( $this->users[ $item['index']['_id'] ] );
					}
				}
				$this->bulk_index();
			} else {
				foreach ( $response['items'] as $item ) {
					if ( ! empty( $item['index']['_id'] ) ) {
						$this->failed_users[] = $item['index']['_id'];
					}
				}
				$attempts = 0;
			}
		} else {
			// There were no errors, all the users were added.
			$attempts = 0;
		}
	}

	/**
	 * Send any bulk indexing errors
	 */
	private function send_bulk_errors() {
		if ( ! empty( $this->failed_users ) ) {
			$error_text = __( "The following users failed to index:\r\n\r\n", 'wptalents' );
			foreach ( $this->failed_users as $failed ) {
				$failed_user = get_user_by( 'id', $failed );
				if ( $failed_user ) {
					$error_text .= "- {$failed}: " . $failed_user->display_name . "\r\n";
				}
			}

			WP_CLI::log( $error_text );

			// Clear failed posts after printing to the screen.
			$this->failed_users = array();
		}
	}

	/**
	 * Provide better error messaging for common connection errors
	 *
	 * @since 0.9.3
	 */
	private function _connect_check() {
		if ( ! defined( 'EP_HOST' ) ) {
			WP_CLI::error( __( 'EP_HOST is not defined! Check wp-config.php', 'wptalents' ) );
		}

		if ( false === ep_elasticsearch_alive() ) {
			WP_CLI::error( __( 'Unable to reach Elasticsearch Server! Check that service is running.', 'wptalents' ) );
		}
	}

	/**
	 * Activate ElasticPress
	 *
	 * @since 0.9.3
	 */
	public function ep_activate() {
		$this->_connect_check();

		$status = ep_is_activated();

		if ( $status ) {
			WP_CLI::warning( 'ElasticPress is already activated.' );
		} else {
			WP_CLI::log( 'ElasticPress is currently deactivated, activating...' );

			$result = ep_activate();

			if ( $result ) {
				WP_CLI::Success( 'ElasticPress was activated!' );
			} else {
				WP_CLI::warning( 'ElasticPress was unable to be activated.' );
			}
		}
	}

	/**
	 * Deactivate ElasticPress
	 *
	 * @since 0.9.3
	 */
	public function ep_deactivate() {
		$this->_connect_check();

		$status = ep_is_activated();

		if ( ! $status ) {
			WP_CLI::warning( 'ElasticPress is already deactivated.' );
		} else {
			WP_CLI::log( 'ElasticPress is currently activated, deactivating...' );

			$result = ep_deactivate();

			if ( $result ) {
				WP_CLI::Success( 'ElasticPress was deactivated!' );
			} else {
				WP_CLI::warning( 'ElasticPress was unable to be deactivated.' );
			}
		}
	}
}
