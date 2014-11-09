<?php

class WP_Talents_Helper {

	public static function post_type_labels( $singular, $plural = '' ) {

		if ( '' === $plural ) {
			$plural = $singular . 's';
		}

		return array(
			'name'               => _x( $plural, 'post type general name' ),
			'singular_name'      => _x( $singular, 'post type singular name' ),
			'add_new'            => __( 'Add New', 'wptalents-theme' ),
			'add_new_item'       => __( 'Add New ' . $singular, 'wptalents-theme' ),
			'edit_item'          => __( 'Edit ' . $singular, 'wptalents-theme' ),
			'new_item'           => __( 'New ' . $singular, 'wptalents-theme' ),
			'view_item'          => __( 'View ' . $singular, 'wptalents-theme' ),
			'search_items'       => __( 'Search ' . $plural, 'wptalents-theme' ),
			'not_found'          => __( 'No ' . $plural . ' found', 'wptalents-theme' ),
			'not_found_in_trash' => __( 'No ' . $plural . ' found in Trash', 'wptalents-theme' ),
			'parent_item_colon'  => ''
		);

	}

	/**
	 * Determine if a post exists based on post_name (slug) and post_type.
	 *
	 * @param string $post_name The post slug.
	 * @param string $post_type Post Type. Defaults to post
	 *
	 * @return int The ID on success, 0 on failure.
	 */
	public static function post_exists( $post_name, $post_type = 'post' ) {

		global $wpdb;

		$query = "SELECT ID FROM $wpdb->posts WHERE 1=1 AND post_status IN ( 'publish', 'draft' ) ";
		$args  = array();

		if ( ! empty ( $post_name ) ) {
			$query .= " AND post_name LIKE '%s' ";
			$args[] = $post_name;
		}
		if ( ! empty ( $post_type ) ) {
			$query .= " AND post_type = '%s' ";
			$args[] = $post_type;
		}

		if ( ! empty ( $args ) && null !== $wpdb->get_var( $wpdb->prepare( $query, $args ) ) ) {
			return true;
		}

		return false;

	}

	/**
	 * @param WP_Post|int $post The post object or ID.
	 * @param string      $type The meta to retrieve. Defaults to all.
	 *                          Possible values are:
	 *                          - profile
	 *                          - plugins
	 *                          - themes
	 *
	 * @return mixed            The required meta if available,
	 *                          false if the post does not exist.
	 */
	public static function get_talent_meta( $post = null, $type = 'all' ) {

		if ( null === $post ) {
			$post = get_queried_object();
		}

		if ( ! is_object( $post = get_post( $post ) ) ) {
			return false;
		}

		switch ( $type ) {
			case 'profile':
				$collector = new WP_Talents_Profile_Collector( $post );

				return $collector->get_data();
				break;
			case 'badges':
				$collector = new WP_Talents_Profile_Collector( $post );

				return $collector->get_data()['badges'];
				break;
			case 'plugins':
				$collector = new WP_Talents_Plugin_Collector( $post );

				return $collector->get_data();
				break;
			case 'themes':
				$collector = new WP_Talents_Theme_Collector( $post );

				return $collector->get_data();
				break;
			case 'score':
				$collector = new WP_Talents_Score_Collector( $post );

				return $collector->get_data();
				break;
			case 'social':
				return self::get_social_links( $post );
				break;
			case 'dawnpatrol':
				if ( ! $profile = esc_url( get_post_meta( $post->ID, 'dawnpatrol', true ) ) ) {
					return false;
				}

				return array(
					'profile' => $profile,
					'video'   => esc_url( get_post_meta( $post->ID, 'dawnpatrol-video', true ) ),
				);
			case 'map':
				return self::get_map_data( $post );
				break;
			case 'location':
				$location = get_post_meta( $post->ID, 'location', true );

				if ( is_string( $location ) ) {
					return array(
						'name' => $location
					);
				}

				return $location;
				break;
			default:
				// Return all meta

				$theme_collector        = new WP_Talents_Theme_Collector( $post );
				$plugin_collector       = new WP_Talents_Plugin_Collector( $post );
				$profile_collector      = new WP_Talents_Profile_Collector( $post );
				$contribution_collector = new WP_Talents_Contribution_Collector( $post );
				$codex_collector        = new WP_Talents_Codex_Collector( $post );
				$changeset_collector    = new WP_Talents_Changeset_Collector( $post );
				$score_collector        = new WP_Talents_Score_Collector( $post );

				return array(
					'score'           => $score_collector->get_data(),
					'social'          => self::get_social_links( $post ),
					'dawnpatrol'      => self::get_talent_meta( $post, 'dawnpatrol' ),
					'profile'         => $profile_collector->get_data(),
					'map'             => self::get_map_data( $post ),
					'plugins'         => $plugin_collector->get_data(),
					'themes'          => $theme_collector->get_data(),
					'contributions'   => $contribution_collector->get_data(),
					'codex_count'     => $codex_collector->get_data(),
					'changeset_count' => $changeset_collector->get_data(),
				);
				break;

		}
	}

	/**
	 * Get the avatar of a talent.
	 *
	 * @param WP_Post|int $post The post object or ID.
	 *
	 * @return mixed            The avatar URL on success,
	 *                          false if the post does not exist.
	 */
	public static function get_avatar( $post = null, $size = 144 ) {

		$profile = self::get_talent_meta( $post, 'profile' );

		if ( ! $profile ) {
			return false;
		}

		// Add size parameter
		$avatar = add_query_arg( array( 's' => $size ), $profile['avatar'] );

		return sprintf(
			'<img src="%1$s" alt="%2$s" width="%3$d" height="%3$d" />',
			esc_url( $avatar ),
			esc_attr( get_the_title( $post ) ),
			$size
		);

	}

	/**
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	public static function get_social_links( WP_Post $post ) {

		$social_links = array();

		$meta_fields = array_merge(
			array( 'wordpressdotorg' => get_post_meta( $post->ID, 'wordpress-username', true ) ),
			(array) get_post_meta( $post->ID, 'social', true )
		);

		foreach ( $meta_fields as $field => $value ) {
			if ( empty ( $value ) ) {
				continue;
			}

			switch ( $field ) {
				case 'wordpressdotorg':
					$social_links[ $field ] = array(
						'name' => __( 'WordPress.org', 'wptalents' ),
						'url'  => 'https://profiles.wordpress.org/' . $value,
					);
					break;
				case 'url':
					$social_links[ $field ] = array(
						'name' => __( 'Website', 'wptalents' ),
						'url'  => $value,
					);
					break;
				case 'linkedin':
					$social_links[ $field ] = array(
						'name' => __( 'LinkedIn', 'wptalents' ),
						'url'  => $value,
					);
					break;
				case 'github':
					$social_links[ $field ] = array(
						'name' => __( 'GitHub', 'wptalents' ),
						'url'  => 'https://github.com/' . $value,
					);
					break;
				case 'twitter':
					$social_links[ $field ] = array(
						'name' => __( 'Twitter', 'wptalents' ),
						'url'  => 'https://twitter.com/' . $value,
					);
					break;
				case 'facebook':
					$social_links[ $field ] = array(
						'name' => __( 'Facebook', 'wptalents' ),
						'url'  => 'https://www.facebook.com/' . $value,
					);
					break;
				case 'google-plus':
					$social_links[ $field ] = array(
						'name' => __( 'Google+', 'wptalents' ),
						'url'  => 'https://plus.google.com/' . $value,
					);
					break;
				default:
					break;
			}
		}

		return $social_links;

	}

	/**
	 * Gets the map data of a talent.
	 *
	 * If it's a company, it returns the locations of all
	 * team members so we can show one big map.
	 *
	 * @param WP_Post The post object.
	 *
	 * @return array Location data as an array
	 */
	public static function get_map_data( WP_Post $post ) {

		$all_locations = array();

		$location = self::get_talent_meta( $post->ID, 'location' );

		if ( empty( $location['lat'] ) || empty( $location['long'] ) ) {
			return false;
		}

		$all_locations[] = array(
			'id'    => $post->ID,
			'title' => $post->post_title,
			'name'  => $location['name'],
			'lat'   => $location['lat'],
			'long'  => $location['long'],
		);

		if ( 'company' === $post->post_type ) {
			// Find connected posts
			$people = get_posts( array(
				'connected_type'   => 'team',
				'connected_items'  => $post,
				'nopaging'         => true,
				'suppress_filters' => false
			) );

			/** @var WP_Post $person */
			foreach ( $people as $person ) {

				if ( ! $person_location = self::get_map_data( $person ) ) {
					continue;
				}

				$all_locations = array_merge( $all_locations, $person_location );

			}
		}

		return $all_locations;

	}

	public static function get_attachment_url( $attachment, $size = 'full' ) {

		$image = wp_get_attachment_image_src( $attachment, $size );

		if ( $image ) {
			return esc_url( $image[0] );
		}

		return false;

	}

}