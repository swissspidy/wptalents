<?php

namespace WPTalents\Core;

use WP_Error;
use WPTalents\Collector\Gravatar_Collector;
use WPTalents\Collector\Profile_Collector;

/**
 * Importer class.
 *
 * @package WPTalents\Core
 */
class Importer {

	/**
	 * @var string WordPress.org username
	 */
	protected $username;

	/**
	 * @var string Post Type.
	 */
	protected $type = 'person';

	/**
	 * @param string $username WordPress.org username.
	 * @param string $name     The talent's real name.
	 * @param string $type     Post type, either person or company.
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
	 * Imports a talent into the site based on their WordPress.org username.
	 * @return bool|\WP_User User object on success, false otherwise.
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

		$user_id = wp_insert_user( array(
			'user_login'    => $this->username,
			'user_nicename' => sanitize_title( $this->name ),
			'user_pass'     => wp_generate_password( 50 ),
			'user_email'    => $this->username . '@chat.wordpress.org',
		) );

		if ( is_wp_error( $user_id ) ) {
			return new WP_Error( 'insert_failed', sprintf( __( 'Importing %s failed!', 'wptalents' ), $this->name ) );
		}

		if ( $this->name !== $this->username ) {
			xprofile_set_field_data( 'Name', $user_id, $this->name );
		}

		// Set member tpe
		bp_set_member_type( $user_id, $this->type );

		// Disable email notifications, otherwise they could get spammed

		// Activity Component
		add_user_meta( $user_id, 'notification_activity_new_mention', 'no', true );
		add_user_meta( $user_id, 'notification_activity_new_reply', 'no', true );

		// BuddyPress Team Plugin
		add_user_meta( $user_id, 'notification_team_membership_request', 'no', true );
		add_user_meta( $user_id, 'notification_team_membership_accepted', 'no', true );

		// BuddyPress Follow Plugin
		add_user_meta( $user_id, 'notification_starts_following', 'no', true );

		$user = get_user_by( 'id', $user_id );

		$collector = new Profile_Collector( $user );
		$profile   = $collector->_retrieve_data();

		// If there's an error, delete the user again
		if ( is_wp_error( $profile ) ) {
			wp_delete_user( $user_id );

			return $profile;
		}

		$collector = new Gravatar_Collector( $user );
		$collector->_retrieve_data();

		return $user;
	}

} 