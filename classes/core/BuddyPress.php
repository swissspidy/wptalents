<?php
/**
 * Customizing BuddyPress
 * @package WPTalents
 */

namespace WPTalents\Core;

use WPTalents\Lib\WP_Stack_Plugin2;

/**
 * Class BuddyPssre
 *
 * @package WPTalents\Core
 */
class BuddyPress extends WP_Stack_Plugin2 {
	/**
	 * Instance of this class.
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Constructs the object, hooks in to `plugins_loaded`.
	 */
	protected function __construct() {
		$this->hook( 'plugins_loaded', 'add_hooks' );
	}

	/**
	 * Adds hooks.
	 */
	public function add_hooks() {
		// BuddyPress Essentials.
		$this->hook( 'bp_register_member_types' );
		$this->hook( 'bp_core_fetch_avatar' );
		$this->hook( 'bp_is_username_compatibility_mode' );
		$this->hook( 'bp_core_get_username' );
		$this->hook( 'bp_members_suggestions_get_suggestions' );
		$this->hook( 'bp_core_get_user_domain' );

		// BuddyPress Profile Fields.
		$this->hook( 'bp_setup_nav' );
		$this->hook( 'bp_register_activity_actions' );

		// BuddyPress Signup.
		$this->hook( 'bp_core_validate_user_signup' );
		$this->hook( 'bp_core_signup_send_validation_email_to' );
		$this->hook( 'bp_core_signup_send_validation_email_message' );
		$this->hook( 'bp_core_activated_user' );
	}

	/**
	 * Register BuddyPress member types.
	 */
	public function bp_register_member_types() {
		bp_register_member_type( 'person', array(
			'labels'        => array(
				'name'          => __( 'People', 'wptalents' ),
				'singular_name' => __( 'Person', 'wptalents' ),
			),
			'has_directory' => 'people',
		) );

		bp_register_member_type( 'company', array(
			'labels'        => array(
				'name'          => __( 'Companies', 'wptalents' ),
				'singular_name' => __( 'Company', 'wptalents' ),
			),
			'has_directory' => 'companies',
		) );
	}

	/**
	 * Filters the avatars from BuddyPress to display the Gravatar from WordPress.org.
	 *
	 * @param string $avatar_url The avatar URL.
	 * @param array  $params     Additional params.
	 *
	 * @return string
	 */
	public function bp_core_fetch_avatar( $avatar_url, $params ) {
		if ( 'user' !== $params['object'] ) {
			return $avatar_url;
		}

		if ( false === strpos( $avatar_url, 'gravatar.com' ) ) {
			return $avatar_url;
		}

		$user = get_user_by( 'id', $params['item_id'] );

		$avatar = get_user_meta( $user->ID, '_wptalents_avatar', true );

		if ( ! empty( $avatar ) ) {
			$custom_avatar = add_query_arg( array(
				's' => absint( $params['width'] ),
				'd' => 'mm',
			), esc_url( $avatar ) );
		} else {
			return $avatar_url;
		}

		if ( true === $params['html'] ) {

			$html_css_id = '';
			if ( ! empty( $params['css_id'] ) ) {
				$html_css_id = ' id="' . esc_attr( $params['css_id'] ) . '"';
			}

			// Use an alias to leave the param unchanged.
			$avatar_classes = $params['class'];
			if ( ! is_array( $avatar_classes ) ) {
				$avatar_classes = explode( ' ', $avatar_classes );
			}

			// Merge classes.
			$avatar_classes = array_merge( $avatar_classes, array(
				$params['object'] . '-' . $params['item_id'] . '-avatar',
				'avatar-' . $params['width'],
			) );

			// Sanitize each class.
			$avatar_classes = array_map( 'sanitize_html_class', $avatar_classes );

			// Populate the class attribute.
			$html_class = ' class="' . join( ' ', $avatar_classes ) . ' photo"';

			$html_title = '';
			if ( ! empty( $params['title'] ) ) {
				$html_title = ' title="' . esc_attr( $params['title'] ) . '"';
			}

			$avatar_url = '<img src="' . $custom_avatar . '" width="' . $params['width'] . '" height="' . $params['height'] . '" alt="' . esc_attr( $params['alt'] ) . '"' . $html_css_id . $html_class . $html_title . ' />';
		}

		return $avatar_url;
	}

	/**
	 * Filter 'bp_is_username_compatibility_mode' to alter the value.
	 *
	 * It's an ugly hack because we want the compatibility mode for mentions (@swissspidy),
	 * but not for URLs (https://wptalents.com/pascal-birchler).
	 *
	 * @param bool $is_compatibility_mode If we are running username compatibility mode.
	 *
	 * @return bool
	 */
	public function bp_is_username_compatibility_mode( $is_compatibility_mode ) {

		$backtrace = debug_backtrace();

		if ( isset( $backtrace[4] ) && 'bp_core_get_username' === $backtrace[4]['function'] ) {
			$is_compatibility_mode = true;
		}

		if ( isset( $backtrace[5] ) && 'bp_core_get_user_domain' === $backtrace[5]['function'] ) {
			$is_compatibility_mode = false;
		}

		if ( isset( $backtrace[4] ) && 'bp_core_get_user_domain' === $backtrace[4]['function'] ) {
			$is_compatibility_mode = false;
		}

		if ( isset( $backtrace[4] ) && 'bp_activity_get_user_mentionname' === $backtrace[4]['function'] ) {
			$is_compatibility_mode = true;
		}

		if ( isset( $backtrace[6] ) && 'bp_activity_at_name_filter_updates' === $backtrace[6]['function'] ) {
			$is_compatibility_mode = true;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$is_compatibility_mode = true;
		}

		return $is_compatibility_mode;
	}

	/**
	 * Filter the username.
	 *
	 * @param string $username The username.
	 *
	 * @return string
	 */
	public function bp_core_get_username( $username ) {
		$backtrace = debug_backtrace();

		if ( isset( $backtrace[4] ) && 'bp_activity_screen_notification_settings' === $backtrace[4]['function'] ) {
			$user = get_user_by( 'slug', $username );

			return $user->user_login;
		}

		return $username;
	}

	/**
	 * Filters the members suggestions results.
	 *
	 * @param array $results Array of users to suggest.
	 *
	 * @return array
	 */
	public function bp_members_suggestions_get_suggestions( $results ) {
		foreach ( $results as &$result ) {
			$user       = get_user_by( 'slug', $result->ID );
			$result->ID = $user->user_login;
			unset( $user );
		}

		return $results;
	}

	/**
	 * Filters the domain for the passed user.
	 *
	 * @param string      $domain        Domain for the passed user.
	 * @param int         $user_id       ID of the passed user.
	 * @param string|bool $user_nicename Optional. user_nicename of the user.
	 * @param string|bool $user_login    Optional. user_login of the user.
	 *
	 * @return string
	 */
	public function bp_core_get_user_domain( $domain, $user_id, $user_nicename = false, $user_login = false ) {
		if ( ! $user_nicename || ! $user_login ) {
			$user          = get_user_by( 'id', $user_id );
			$user_nicename = $user->user_nicename;
			$user_login    = $user->user_login;
		}
		$domain = str_replace( $user_login, $user_nicename, $domain );

		return $domain;
	}

	/**
	 * Customize the BuddyPress components navigation
	 */
	public function bp_setup_nav() {
		global $bp;
		$bp->bp_nav['profile']['position'] = 10;
		$bp->bp_nav['activity']['position'] = 15;

		bp_core_new_nav_item( array(
			'name'                => __( 'Overview', 'wptalents' ),
			'slug'                => 'overview',
			'position'            => 5,
			'screen_function'     => function () {
				// $this->hook( 'bp_template_title', 'profile_tab_overview_title' );
				// $this->hook( 'bp_template_content', 'profile_tab_overview_content' );
				do_action( 'bp_template_profile_tab_overview' );
				bp_core_load_template( 'members/single/plugins' );
			},
			'item_css_id'         => 'overview',
			'default_subnav_slug' => 'overview',
		) );

		// Todo: only show these if there's at least 1 product/plugin/theme.
		bp_core_new_nav_item( array(
			'name'                    => __( 'Jobs', 'wptalents' ),
			'slug'                    => 'jobs',
			'position'                => 50,
			'show_for_displayed_user' => ( 'company' === bp_get_member_type( bp_displayed_user_id() ) ),
			'screen_function'         => function () {
				do_action( 'bp_template_profile_tab_jobs' );
				bp_core_load_template( 'members/single/plugins' );
			},
			'item_css_id'             => 'jobs',
			'default_subnav_slug'     => 'jobs',
		) );

		bp_core_new_nav_item( array(
			'name'                => __( 'Products', 'wptalents' ),
			'slug'                => 'products',
			'position'            => 40,
			'screen_function'     => function () {
				do_action( 'bp_template_profile_tab_products' );
				bp_core_load_template( 'members/single/plugins' );
			},
			'item_css_id'         => 'products',
			'default_subnav_slug' => 'products',
		) );

		bp_core_new_nav_item( array(
			'name'                => __( 'Plugins', 'wptalents' ),
			'slug'                => 'plugins',
			'position'            => 40,
			'screen_function'     => function () {
				do_action( 'bp_template_profile_tab_plugins' );
				bp_core_load_template( 'members/single/plugins' );
			},
			'item_css_id'         => 'plugins',
			'default_subnav_slug' => 'plugins',
		) );

		bp_core_new_nav_item( array(
			'name'                => __( 'Themes', 'wptalents' ),
			'slug'                => 'themes',
			'position'            => 40,
			'screen_function'     => function () {
				do_action( 'bp_template_profile_tab_themes' );
				bp_core_load_template( 'members/single/plugins' );
			},
			'item_css_id'         => 'themes',
			'default_subnav_slug' => 'themes',
		) );

		bp_core_new_nav_item( array(
			'name'                => __( 'WordPress.tv', 'wptalents' ),
			'slug'                => 'wordpresstv',
			'position'            => 40,
			'screen_function'     => function () {
				do_action( 'bp_template_profile_tab_wordpresstv' );
				bp_core_load_template( 'members/single/plugins' );
			},
			'item_css_id'         => 'wordpresstv',
			'default_subnav_slug' => 'wordpresstv',
		) );

	}

	/**
	 * Register BuddyPress activity actions.
	 */
	public function bp_register_activity_actions() {
		bp_activity_set_action(
			'wptalents',
			'user_created',
			__( 'New talent', 'buddypress' ),
			'wptalents_format_activity_action_user_created',
			__( 'WP Talents', 'buddypress' )
		);
	}

	/**
	 * Syncs Xprofile data to the standard built in WordPress profile data.
	 *
	 * @param int $user_id ID of the updated user.
	 */
	public function update_user_nicename( $user_id = 0 ) {
		// Bail if profile syncing is disabled.
		if ( bp_disable_profile_sync() ) {
			return;
		}

		if ( empty( $user_id ) ) {
			$user_id = bp_loggedin_user_id();
		}

		if ( empty( $user_id ) ) {
			return;
		}

		$fullname = xprofile_get_field_data( bp_xprofile_fullname_field_id(), $user_id );

		wp_update_user( array(
			'ID'            => $user_id,
			'user_nicename' => sanitize_title( $fullname ),
		) );
	}

	/**
	 * Check existencs of WordPress.org profile on signup.
	 *
	 * @param array $result Information about the username, the email address and errors.
	 *
	 * @return array
	 */
	public function bp_core_validate_user_signup( $result ) {
		$url = 'https://profiles.wordpress.org/' . $result['user_name'];

		$response = wp_safe_remote_head( $url );

		if ( is_wp_error( $response ) || 302 === $response['response']['code'] ) {
			$result['errors']->add( 'user_name', __( 'That WordPress.org username doesn&#39;t seem to exist', 'wptalents' ) );
		}

		return $result;
	}

	/**
	 * Send validation emails to the user's chat.wordpress.org email address.
	 *
	 * @param string $user_email The user's email address.
	 * @param int    $user_id    User ID.
	 *
	 * @return string
	 */
	public function bp_core_signup_send_validation_email_to( $user_email, $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( $user ) {
			return $user->user_login . '@chat.wordpress.org';
		}

		return $user_email;
	}

	/**
	 * Filter the validation email message.
	 *
	 * @param string $message      The validation email message.
	 * @param int    $user_id      User ID.
	 * @param string $activate_url The URL to activate the account.
	 *
	 * @return string
	 */
	public function bp_core_signup_send_validation_email_message( $message, $user_id, $activate_url ) {
		$message .= sprintf( __( "If that wasn't you, please ignore this email.\n\n", 'wptalents' ), $activate_url );

		return $message;
	}

	/**
	 * Schedule cron events on user activation.
	 *
	 * @param int $user_id User ID.
	 */
	public function bp_core_activated_user( $user_id ) {
		// Schedule weekly events.
		foreach (
			array(
				'bbpress',
				'buddypress',
				'changesets',
				'codex',
				'forums',
				'plugins',
				'score',
				'themes',
				'wordpressorg',
				'wpse',
				'wordpresstv',
			) as $type
		) {
			wp_schedule_event( time(), 'weekly', 'wptalents_collect_' . $type, array( $user_id ) );
		}

		// Schedule single events.
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'wptalents_collect_gravatar', array( $user_id ) );
	}
}
