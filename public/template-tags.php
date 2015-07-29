<?php
/**
 * WP Talents template tags.
 *
 * @package WPTalents
 */

/**
 * Get social links for a specific talent.
 *
 * @param int $user_id User ID.
 *
 * @return mixed
 */
function wptalents_get_social_links( $user_id = null ) {
	if ( ! $user_id ) {
		$user_id = bp_displayed_user_id();
	}

	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return false;
	}

	WPTalents\Core\Helper::get_social_links( $user );
}

/**
 * Get talent meta data.
 *
 * @param int    $user_id User ID.
 * @param string $type    The meta to retrieve. Defaults to all.
 *
 * @return mixed
 */
function wptalents_get_meta( $user_id = null, $type = 'all' ) {
	if ( ! $user_id ) {
		$user_id = bp_displayed_user_id();
	}

	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return false;
	}

	switch ( $type ) {
		case 'bbpress':
			return array(
				'topics_count'  => get_user_meta( $user_id, '_wptalents_bbpress_topics_count', true ),
				'replies_count' => get_user_meta( $user_id, '_wptalents_bbpress_replies_count', true ),
			);
			break;
		case 'buddypress':
			return array(
				'topics_count'  => get_user_meta( $user_id, '_wptalents_buddypress_topics_count', true ),
				'replies_count' => get_user_meta( $user_id, '_wptalents_buddypress_replies_count', true ),
			);
			break;
		case 'forums':
			return get_user_meta( $user_id, '_wptalents_forums', true );
			break;
		case 'plugins':
			return get_user_meta( $user_id, '_wptalents_plugins', true );
			break;
		case 'score':
			return get_user_meta( $user_id, '_wptalents_score', true );
			break;
		case 'themes':
			return get_user_meta( $user_id, '_wptalents_themes', true );
			break;
		case 'wordpresstv':
			return get_user_meta( $user_id, '_wptalents_wordpresstv', true );
			break;
		case 'wordpresstv':
			return get_user_meta( $user_id, '_wptalents_wordpresstv', true );
			break;
		case 'wpse':
			return get_user_meta( $user_id, '_wptalents_wpse', true );
			break;
	}

	return false;
}


/**
 * Get the WordPress core contributions for a specific user.
 *
 * @param int $user_id User ID. Defaults to the currently displayed user.
 *
 * @return array|bool
 */
function wptalents_get_contributions( $user_id = null ) {
	if ( ! $user_id ) {
		$user_id = bp_displayed_user_id();
	}

	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return false;
	}

	global $wp_version;

	$contributions = array();

	foreach ( range( 3.2, $wp_version, 0.1 ) as $version ) {
		$credits = get_option( 'wptalents_wordpress_credits_' . number_format( $version, 1 ) );

		foreach ( $credits['groups'] as $group_slug => $group_data ) {
			if ( 'libraries' === $group_data['type'] ) {
				continue;
			}

			foreach ( $group_data['data'] as $person_username => $person_data ) {
				if ( strtolower( $person_username ) == $user->user_login ) {
					$role = __( 'Core Contributor', 'wptalents' );

					if ( 'titles' == $group_data['type'] ) {
						$role = ucfirst( str_replace( '-', ' ', $group_slug ) );

						if ( $person_data[3] ) {
							$role = $person_data[3];
						} else if ( $group_data['name'] ) {
							$role = $group_data['name'];
						}

						$role = rtrim( $role, 's' );

						if ( 'Contributing developer' === $role ) {
							$role = 'Contributing Developer';
						}
					}

					$contributions[ $version ] = $role;
				}
			}
		}
	}

	return $contributions;
}

/**
 * Get the WP Talents score for a user.
 *
 * @param int $user_id User ID. Defaults to the currently displayed user.
 *
 * @return int
 */
function wptalents_get_score( $user_id = null ) {
	if ( ! $user_id ) {
		$user_id = bp_displayed_user_id();
	}

	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return 0;
	}

	$score = bp_get_user_meta( $user_id, '_wptalents_score', true );

	if ( ! $score ) {
		$score = 0;
	}

	return absint( $score );
}

/**
 * Get the forums count for a user.
 *
 * @param int    $user_id User ID. Defaults to the currently displayed user.
 * @param string $forums  THe forums to get the stats from. Either `bbpress` or `buddypress`.
 * @param string $type    Either `replies` or `topics`.
 *
 * @return int
 */
function wptalents_get_bb_forums_count( $user_id = null, $forums, $type ) {
	if ( ! $user_id ) {
		$user_id = bp_displayed_user_id();
	}

	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return 0;
	}

	$count = bp_get_user_meta( $user_id, '_wptalents_' . $forums . '_' . $type . '_score', true );

	if ( ! $count ) {
		$count = 0;
	}

	return absint( $count );
}

/**
 * Get the WordPress.org forums replies count for a user.
 *
 * @param int $user_id User ID. Defaults to the currently displayed user.
 *
 * @return int
 */
function wptalents_get_forums_count( $user_id = null ) {
	if ( ! $user_id ) {
		$user_id = bp_displayed_user_id();
	}

	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return 0;
	}

	$count = 0;

	$forums_data = bp_get_user_meta( $user_id, '_wptalents_forums', true );
	if ( is_array( $forums_data ) && isset( $forums_data['total_replies'] ) ) {
		$count += $forums_data['total_replies'];
	}

	$forums_data = bp_get_user_meta( $user_id, '_wptalents_intl_forums', true );
	if ( is_array( $forums_data ) && isset( $forums_data['total_replies'] ) ) {
		$count += $forums_data['total_replies'];
	}

	return absint( $count );
}

/**
 * Get the changesets for a user.
 *
 * @param int $user_id User ID. Defaults to the currently displayed user.
 *
 * @return int
 */
function wptalents_get_changesets( $user_id = null ) {
	if ( ! $user_id ) {
		$user_id = bp_displayed_user_id();
	}

	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return array();
	}

	$changesets = bp_get_user_meta( $user_id, '_wptalents_changesets', true );
	if ( isset( $changesets['changesets'] ) ) {
		return $changesets['changesets'];
	}

	return array();
}

/**
 * Get the core props count for a user.
 *
 * @param int $user_id User ID. Defaults to the currently displayed user.
 *
 * @return int
 */
function wptalents_get_props_count( $user_id = null ) {
	if ( ! $user_id ) {
		$user_id = bp_displayed_user_id();
	}

	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return 0;
	}

	$count = 0;

	$changesets = bp_get_user_meta( $user_id, '_wptalents_changesets', true );
	if ( is_array( $changesets ) && isset( $changesets['props_count'] ) ) {
		$count += $changesets['props_count'];
	}

	return absint( $count );
}

/**
 * Get the number of edited Codex pages for a user.
 *
 * @param int $user_id User ID. Defaults to the currently displayed user.
 *
 * @return int
 */
function wptalents_get_codex_count( $user_id = null ) {
	if ( ! $user_id ) {
		$user_id = bp_displayed_user_id();
	}

	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return 0;
	}

	$count = bp_get_user_meta( $user_id, '_wptalents_codex_count', true );

	if ( ! $count ) {
		$count = 0;
	}

	return absint( $count );
}

/**
 * Get the plugins for a user.
 *
 * @param int $user_id User ID. Defaults to the currently displayed user.
 *
 * @return int
 */
function wptalents_get_plugins( $user_id = null ) {
	if ( ! $user_id ) {
		$user_id = bp_displayed_user_id();
	}

	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return array();
	}

	$plugins = bp_get_user_meta( $user_id, '_wptalents_plugins', true );
	if ( is_array( $plugins ) && ! empty( $plugins ) ) {
		return $plugins;
	}

	return array();
}

/**
 * Get the themes for a user.
 *
 * @param int $user_id User ID. Defaults to the currently displayed user.
 *
 * @return int
 */
function wptalents_get_themes( $user_id = null ) {
	if ( ! $user_id ) {
		$user_id = bp_displayed_user_id();
	}

	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return array();
	}

	$themes = bp_get_user_meta( $user_id, '_wptalents_themes', true );
	if ( is_array( $themes ) && ! empty( $themes ) ) {
		return $themes;
	}

	return array();
}

/**
 * Get the themes for a user.
 *
 * @param int $user_id User ID. Defaults to the currently displayed user.
 *
 * @return int
 */
function wptalents_get_videos( $user_id = null ) {
	if ( ! $user_id ) {
		$user_id = bp_displayed_user_id();
	}

	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return array();
	}

	$videos = bp_get_user_meta( $user_id, '_wptalents_wordpresstv', true );
	if ( isset( $videos['videos'] ) && is_array( $videos['videos'] ) && ! empty( $videos['videos'] ) ) {
		return $videos['videos'];
	}

	return array();
}

/**
 * Prepare a user for syncing.
 *
 * @param int $user_id User ID.
 *
 * @return bool|array
 */
function wptalents_ep_prepare_user( $user_id ) {
	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return false;
	}

	$args = array(
		// Default WordPress properties.
		'user_id'                    => $user->ID,
		'display_name'               => $user->display_name,
		'user_login'                 => $user->user_login,
		'user_nicename'              => $user->user_nicename,
		'user_email'                 => $user->user_email,
		'user_registered'            => $user->user_registered,
		'permalink'                  => bp_core_get_user_domain( $user->ID, $user->user_nicename, $user->user_login ),
		'usermeta'                   => wptalents_ep_prepare_meta( $user->ID ),
		// Properties specific to BuddyPress and WP Talents.
		'member_type'                => bp_get_member_type( $user->ID ),
		'score'                      => wptalents_get_score( $user->ID ),
		'badges'                     => xprofile_get_field_data( 'Badges', $user->ID ),
		// Forums data.
		'bbpress_replies_count'      => wptalents_get_bb_forums_count( $user->ID, 'bbpress', 'replies' ),
		'bbpress_topics_count'       => wptalents_get_bb_forums_count( $user->ID, 'bbpress', 'topics' ),
		'buddypress_replies_count'   => wptalents_get_bb_forums_count( $user->ID, 'buddypress', 'replies' ),
		'buddypress_topics_count'    => wptalents_get_bb_forums_count( $user->ID, 'buddypress', 'topics' ),
		'wporg_forums_replies_count' => wptalents_get_forums_count( $user->ID ),
		// Core contributions.
		'props_count'                => wptalents_get_props_count( $user->ID ),
		'changesets'                 => wptalents_get_changesets( $user->ID ),
		// Plugins & Themes.
		'plugins'                    => wptalents_get_plugins( $user->ID ),
		'themes'                     => wptalents_get_themes( $user->ID ),
		// WordPress.tv.
		'videos'                     => wptalents_get_videos( $user->ID ),
		// WordPress Codex.
		'codex_count'                => wptalents_get_codex_count( $user->ID ),
		// Team members and companies.
		// 'team'                       => array(),
		// Location fields.
		// 'location'                   => array(),
		// 'city'                       => '',
		// 'country'                    => ,
		// Todo: Profile fields (Slack, Website, etc.).
	);

	return $args;
}

/**
 * Prepare us meta to send to ES
 *
 * @param int $user_id Userer ID.
 *
 * @since 0.1.0
 * @return array
 */
function wptalents_ep_prepare_meta( $user_id ) {
	$meta = (array) get_user_meta( $user_id );

	if ( empty( $meta ) ) {
		return array();
	}

	$prepared_meta = array();

	foreach ( $meta as $key => $value ) {
		if ( ! is_protected_meta( $key, 'user' ) ) {
			$prepared_meta[ $key ] = maybe_unserialize( $value );
		}
	}

	return $prepared_meta;
}

/**
 * Decode the bulk index response
 *
 * @param string $body POST body.
 *
 * @return array|object|WP_Error
 */
function wptalents_ep_bulk_index_users( $body ) {
	// Create the url with index name and type so that we don't have to repeat it over and over in the request (thereby reducing the request size).
	$path = trailingslashit( ep_get_index_name() ) . 'user/_bulk';

	$request_args = array(
		'method'  => 'POST',
		'body'    => $body,
		'timeout' => 30,
	);

	$request = ep_remote_request( $path, $request_args );

	if ( is_wp_error( $request ) ) {
		return $request;
	}

	$response = wp_remote_retrieve_response_code( $request );

	if ( 200 !== $response ) {
		return new WP_Error( $response, wp_remote_retrieve_response_message( $request ), $request );
	}

	return json_decode( wp_remote_retrieve_body( $request ), true );
}
