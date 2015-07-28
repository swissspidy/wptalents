<?php
/**
 * Some helper methods.
 *
 * @package WPTalents
 */

namespace WPTalents\Core;

use WPTalents\Collector\Codex_Collector;

/**
 * Class Helper
 * @package WPTalents\Core
 */
class Helper {
	/**
	 * Generate post type labels used by register_post_type().
	 *
	 * @param string $singular Singular form.
	 * @param string $plural   Plural form.
	 *
	 * @return array
	 */
	public static function post_type_labels( $singular, $plural = '' ) {
		if ( '' === $plural ) {
			$plural = $singular . 's';
		}

		return array(
			'name'               => sprintf( _x( '%s', 'post type general name', 'wptalents' ), $plural ),
			'singular_name'      => sprintf( _x( '%s', 'post type singular name', 'wptalents' ), $singular ),
			'add_new'            => __( 'Add New', 'wptalents-theme' ),
			'add_new_item'       => sprintf( __( 'Add New %s', 'wptalents' ), $singular ),
			'edit_item'          => sprintf( __( 'Edit %s', 'wptalents' ), $singular ),
			'new_item'           => sprintf( __( 'New %s', 'wptalents' ), $singular ),
			'view_item'          => sprintf( __( 'View %s', 'wptalents' ), $singular ),
			'search_items'       => sprintf( __( 'Search %s', 'wptalents' ), $plural ),
			'not_found'          => sprintf( __( 'No %s found', 'wptalents' ), $plural ),
			'not_found_in_trash' => sprintf( __( 'No %s found in Trash', 'wptalents' ), $plural ),
			'parent_item_colon'  => '',
		);
	}


	/**
	 * Get social links for a specific user.
	 *
	 * @param \WP_User $user User object.
	 *
	 * @return array
	 */
	public static function get_social_links( \WP_User $user ) {
		$social_links = array(
			'WordPress.org' => esc_url( 'https://profiles.wordpress.org/' . $user->user_login ),
		);

		$profile_fields = array(
			'Bitbucket',
			'Dawn Patrol',
			'Facebook',
			'GitHub',
			'Google+',
			'LinkedIn',
			'Twitter',
			'Website',
		);

		foreach ( $profile_fields as $field ) {
			$value = xprofile_get_field_data( $field, $user->ID );

			switch ( $field ) {
				case 'GitHub':
					$value = esc_url( 'https://github.com/' . $value );
					break;
				case 'Twitter':
					$value = esc_url( 'https://twitter.com/' . $value );
					break;
				case 'Facebook':
					$value = esc_url( 'https://www.facebook.com/' . $value );
					break;
				case 'Google+':
					$value = esc_url( 'https://plus.google.com/' . $value );
					break;
			}

			$social_links[ $field ] = esc_url( $value );
		}

		return $social_links;
	}
}
