<?php
/**
 * Importer class.
 *
 * @package WPTalents
 */

namespace WPTalents\Core;

use WP_Error;

/**
 * Importer class.
 *
 * @package WPTalents\Core
 */
class Importer {
	/**
	 * WordPress.org username.
	 *
	 * @var string
	 */
	protected $username;

	/**
	 * Member type.
	 *
	 * @var string
	 */
	protected $type = 'person';

	/**
	 * Constructor.
	 *
	 * @param string $username WordPress.org username.
	 * @param string $name     The talent's real name.
	 * @param string $type     Member type, either person or company.
	 */
	public function __construct( $username, $name = '', $type = 'person' ) {
		$this->name     = (string) $username;
		$this->username = (string) $username;
		$this->type     = (string) $type;

		if ( ! empty( $name ) ) {
			$this->name = $name;
		}
	}

	/**
	 * Check if there's a WordPress.org profile for a given username.
	 *
	 * @return bool True if the user exists, false otherwise.
	 */
	public function remote_user_exists() {
		$url = 'https://profiles.wordpress.org/' . $this->username;

		$response = wp_safe_remote_head( $url );

		if ( is_wp_error( $response ) || 302 === $response['response']['code'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Imports a talent into the site based on their WordPress.org username.
	 *
	 * @return \WP_Error|\WP_User
	 */
	public function import() {
		if ( null === bp_get_member_type_object( $this->type ) ) {
			return new WP_Error(
				'invalid_member_type',
				sprintf( __( 'Invalid member type "%s"!', 'wptalents' ), $this->type )
			);
		}

		$user = get_user_by( 'login', $this->username );

		if ( is_a( $user, 'WP_User' ) ) {
			return new WP_Error(
				'already_exists',
				sprintf( __( 'Talent already exists! (ID: %d)', 'wptalents' ), $user->ID )
			);
		}

		// Temporarily disable the activation email.
		add_filter( 'bp_core_signup_send_activation_key', '__return_false' );

		$user_id = bp_core_signup_user(
			$this->username,
			wp_generate_password( 50 ),
			$this->username . '@chat.wordpress.org',
			array()
		);

		add_filter( 'bp_core_signup_send_activation_key', '__return_true' );

		if ( is_wp_error( $user_id ) ) {
			return new WP_Error( 'insert_failed', sprintf( __( 'Importing %s failed!', 'wptalents' ), $this->name ) );
		}

		// Update the user_nicename which is used for the URLs.
		wp_update_user( array( 'ID' => $user_id, 'user_nicename' => $this->name ) );

		if ( $this->name !== $this->username ) {
			xprofile_set_field_data( 'Name', $user_id, $this->name );
		}

		// Set member tpe.
		bp_set_member_type( $user_id, $this->type );

		/**
		 * Disable email notifications, otherwise they could get spammed.
		 */

		// Activity Component.
		add_user_meta( $user_id, 'notification_activity_new_mention', 'no', true );
		add_user_meta( $user_id, 'notification_activity_new_reply', 'no', true );

		// BuddyPress Team Plugin.
		add_user_meta( $user_id, 'notification_team_membership_request', 'no', true );
		add_user_meta( $user_id, 'notification_team_membership_accepted', 'no', true );

		// BuddyPress Follow Plugin.
		add_user_meta( $user_id, 'notification_starts_following', 'no', true );

		return get_user_by( 'id', $user_id );
	}
}
