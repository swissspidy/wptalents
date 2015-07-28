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
