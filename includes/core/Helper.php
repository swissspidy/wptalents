<?php

namespace WPTalents\Core;

use WPTalents\Collector\Changeset_Collector;
use WPTalents\Collector\Codex_Collector;
use WPTalents\Collector\Contribution_Collector;
use WPTalents\Collector\Forums_Collector;
use WPTalents\Collector\Plugin_Collector;
use WPTalents\Collector\Profile_Collector;
use WPTalents\Collector\Score_Collector;
use WPTalents\Collector\Theme_Collector;
use WPTalents\Collector\WordPressTv_Collector;
use \WP_Post;
use \wpdb;

/**
 * Class Helper
 * @package WPTalents\Core
 */
class Helper {

	/**
	 * Generate post type labels used by register_post_type().
	 *
	 * @param string $singular
	 * @param string $plural
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
	 * Determine if a post exists based on post_name (slug) and post_type.
	 *
	 * @param string       $post_name The post slug.
	 * @param array|string $post_type Post Type. Defaults to post
	 *
	 * @return object|null The resulting row on success, null on failure.
	 */
	public static function post_exists( $post_name, $post_type = 'post' ) {

		/** @var wpdb $wpdb */
		global $wpdb;

		$query = "SELECT ID, post_type FROM $wpdb->posts WHERE 1=1 AND post_status IN ( 'publish', 'draft' ) ";
		$args  = array();

		if ( ! empty( $post_name ) ) {
			$query .= ' AND post_name LIKE \'%s\' ';
			$args[] = $post_name;
		}
		if ( ! empty( $post_type ) ) {
			$post_type_in = implode( ', ', array_fill( 0, count( (array) $post_type ), '%s' ) );

			$query .= " AND post_type IN ( $post_type_in )";
			foreach ( (array) $post_type as $type ) {
				$args[] = $type;
			}
		}

		$query .= ' LIMIT 1';

		$result = $wpdb->get_row( $wpdb->prepare( $query, $args ) );

		if ( null === $result ) {
			return false;
		}

		return $result;

	}

	/**
	 * @param WP_Post $post     The post object or ID.
	 * @param string  $type     The meta to retrieve. Defaults to all.
	 *                          Possible values are:
	 *                          - profile
	 *                          - plugins
	 *                          - themes
	 *
	 * @return mixed            The required meta if available,
	 *                          false if the post does not exist.
	 */
	public static function get_talent_meta( WP_Post $post, $type = 'all' ) {

		switch ( $type ) {
			case 'profile':
				$collector = new Profile_Collector( $post );

				return $collector->get_data();
				break;
			case 'badges':
				$collector = new Profile_Collector( $post );

				return $collector->get_data()['badges'];
				break;
			case 'plugins':
				$collector = new Plugin_Collector( $post );

				return $collector->get_data();
				break;
			case 'themes':
				$collector = new Theme_Collector( $post );

				return $collector->get_data();
				break;
			case 'score':
				$collector = new Score_Collector( $post );

				return $collector->get_data();
				break;
			case 'forums':
				$collector = new Forums_Collector( $post );

				return $collector->get_data();
				break;
			case 'wordpresstv':
				$collector = new WordPressTv_Collector( $post );

				return $collector->get_data();
				break;
			case 'contributions':
				$collector = new Contribution_Collector( $post );

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
						'name' => $location,
					);
				}

				return $location;
				break;
			default:
				// Return all meta

				$theme_collector        = new Theme_Collector( $post );
				$plugin_collector       = new Plugin_Collector( $post );
				$profile_collector      = new Profile_Collector( $post );
				$contribution_collector = new Contribution_Collector( $post );
				$codex_collector        = new Codex_Collector( $post );
				$changeset_collector    = new Changeset_Collector( $post );
				$score_collector        = new Score_Collector( $post );
				$forums_collector       = new Forums_Collector( $post );
				$wordpresstv_collector  = new WordPressTv_Collector( $post );

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
					'forums'          => $forums_collector->get_data(),
					'wordpresstv'     => $wordpresstv_collector->get_data(),
				);
				break;

		}
	}

	/**
	 * Get the avatar URL of a talent.
	 *
	 * @param \WP_Post|int $post The post object or ID.
	 *
	 * @param int          $size Size of the avatar image.
	 *
	 * @return string The avatar URL.
	 */
	public static function get_avatar_url( $post, $size ) {

		/** @var \WP_Post $post */
		$post = get_post( $post );

		$profile = self::get_talent_meta( $post, 'profile' );

		if ( ! isset( $profile['avatar'] ) ) {
			$profile = array( 'avatar' => 'https://secure.gravatar.com/avatar/' );
		}

		// Add size parameter
		$avatar = add_query_arg( array( 's' => absint( $size ), 'd' => 'mm' ), $profile['avatar'] );

		return esc_url( $avatar );

	}

	/**
	 * Get the avatar of a talent.
	 *
	 * @param \WP_Post|int $post The post object or ID.
	 *
	 * @param int          $size
	 *
	 * @return mixed            The avatar img tag on success,
	 *                          false if the post does not exist.
	 */
	public static function get_avatar( WP_Post $post, $size = 144 ) {

		$avatar = self::get_avatar_url( $post, $size );

		return sprintf(
			'<img src="%1$s" alt="%2$s" width="%3$d" height="%3$d" />',
			esc_url( $avatar ),
			esc_attr( get_the_title( $post ) ),
			absint( $size )
		);

	}

	/**
	 * @param \WP_Post $post
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
			if ( empty( $value ) ) {
				continue;
			}

			switch ( $field ) {
				case 'wordpressdotorg':
					$social_links[ $field ] = array(
						'name' => __( 'WordPress.org', 'wptalents' ),
						'url'  => esc_url( 'https://profiles.wordpress.org/' . $value ),
					);
					break;
				case 'url':
					$social_links[ $field ] = array(
						'name' => __( 'Website', 'wptalents' ),
						'url'  => esc_url( $value ),
					);
					break;
				case 'linkedin':
					$social_links[ $field ] = array(
						'name' => __( 'LinkedIn', 'wptalents' ),
						'url'  => esc_url( $value ),
					);
					break;
				case 'github':
					$social_links[ $field ] = array(
						'name' => __( 'GitHub', 'wptalents' ),
						'url'  => esc_url( 'https://github.com/' . $value ),
					);
					break;
				case 'twitter':
					$social_links[ $field ] = array(
						'name' => __( 'Twitter', 'wptalents' ),
						'url'  => esc_url( 'https://twitter.com/' . $value ),
					);
					break;
				case 'facebook':
					$social_links[ $field ] = array(
						'name' => __( 'Facebook', 'wptalents' ),
						'url'  => esc_url( 'https://www.facebook.com/' . $value ),
					);
					break;
				case 'google-plus':
					$social_links[ $field ] = array(
						'name' => __( 'Google+', 'wptalents' ),
						'url'  => esc_url( 'https://plus.google.com/' . $value ),
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
	 * @param WP_Post $post The post object.
	 *
	 * @return array Location data as an array
	 */
	public static function get_map_data( WP_Post $post ) {

		$location = self::get_talent_meta( $post, 'location' );

		if ( empty( $location['lat'] ) || empty( $location['long'] ) || empty( $location['name'] ) ) {
			return false;
		}

		$thumbnail = '';
		if ( has_post_thumbnail( $post->ID ) ) {
			$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'full' );
			if ( $image ) {
				$thumbnail = $image[0];
			}
		}

		return array(
			'name'  => $location['name'],
			'lat'   => $location['lat'],
			'long'  => $location['long'],
			'image' => $thumbnail,
		);

	}

	/**
	 * Get the URL of an attachment based on its ID.
	 *
	 * @param int    $attachment
	 * @param string $size
	 *
	 * @return bool|string
	 */
	public static function get_attachment_url( $attachment, $size = 'full' ) {

		$image = wp_get_attachment_image_src( $attachment, $size );

		if ( $image ) {
			return esc_url( $image[0] );
		}

		return false;

	}

	/**
	 * Examine a url and try to determine the post ID it represents.
	 *
	 * Checks are supposedly from the hosted site blog.
	 *
	 * Extends the url_to_postid() function from WordPress to work
	 * with WP Talents' permalink structure.
	 *
	 * @param string $url Permalink to check.
	 *
	 * @return int Post ID, or 0 on failure.
	 */
	public static function url_to_postid( $url ) {
		global $wp_rewrite;

		/**
		 * Filter the URL to derive the post ID from.
		 *
		 * @since 2.2.0
		 *
		 * @param string $url The URL to derive the post ID from.
		 */
		$url = apply_filters( 'url_to_postid', $url );

		// First, check to see if there is a 'p=N' or 'page_id=N' to match against
		if ( preg_match( '#[?&](p|page_id|attachment_id)=(\d+)#', $url, $values ) ) {
			$id = absint( $values[2] );
			if ( $id ) {
				return $id;
			}
		}

		// Check to see if we are using rewrite rules
		$rewrite = $wp_rewrite->wp_rewrite_rules();

		// Not using rewrite rules, and 'p=N' and 'page_id=N' methods failed, so we're out of options
		if ( empty( $rewrite ) ) {
			return 0;
		}

		// Get rid of the #anchor
		$url_split = explode( '#', $url );
		$url       = $url_split[0];

		// Get rid of URL ?query=string
		$url_split = explode( '?', $url );
		$url       = $url_split[0];

		// Add 'www.' if it is absent and should be there
		if ( false !== strpos( home_url(), '://www.' ) && false === strpos( $url, '://www.' ) ) {
			$url = str_replace( '://', '://www.', $url );
		}

		// Strip 'www.' if it is present and shouldn't be
		if ( false === strpos( home_url(), '://www.' ) ) {
			$url = str_replace( '://www.', '://', $url );
		}

		// Strip 'index.php/' if we're not using path info permalinks
		if ( ! $wp_rewrite->using_index_permalinks() ) {
			$url = str_replace( $wp_rewrite->index . '/', '', $url );
		}

		if ( false !== strpos( trailingslashit( $url ), home_url( '/' ) ) ) {
			// Chop off http://domain.com/[path]
			$url = str_replace( home_url(), '', $url );
		} else {
			// Chop off /path/to/blog
			$home_path = parse_url( home_url( '/' ) );
			$home_path = isset( $home_path['path'] ) ? $home_path['path'] : '';
			$url       = preg_replace( sprintf( '#^%s#', preg_quote( $home_path ) ), '', trailingslashit( $url ) );
		}

		// Trim leading and lagging slashes
		$url = trim( $url, '/' );

		$request              = $url;
		$post_type_query_vars = array();

		foreach ( get_post_types( array(), 'objects' ) as $post_type => $t ) {
			if ( ! empty( $t->query_var ) ) {
				$post_type_query_vars[ $t->query_var ] = $post_type;
			}
		}

		// Look for matches.
		$request_match = $request;
		foreach ( (array) $rewrite as $match => $query ) {

			// If the requesting file is the anchor of the match, prepend it
			// to the path info.
			if ( ! empty( $url ) && ( $url != $request ) && ( 0 === strpos( $match, $url ) ) ) {
				$request_match = $url . '/' . $request;
			}

			if ( preg_match( "#^$match#", $request_match, $matches ) ) {

				if ( $wp_rewrite->use_verbose_page_rules && preg_match( '/pagename=\$matches\[([0-9]+)\]/', $query, $varmatch ) ) {
					// this is a verbose page match, lets check to be sure about it
					if ( ! get_page_by_path( $matches[ $varmatch[1] ] ) ) {
						continue;
					}
				}

				// Got a match.
				// Trim the query of everything up to the '?'.
				$query = preg_replace( '!^.+\?!', '', $query );

				// Substitute the substring matches into the query.
				$query = addslashes( \WP_MatchesMapRegex::apply( $query, $matches ) );

				// Filter out non-public query vars
				global $wp;
				parse_str( $query, $query_vars );
				$query = array();
				foreach ( (array) $query_vars as $key => $value ) {
					if ( in_array( $key, $wp->public_query_vars ) ) {
						$query[ $key ] = $value;
						if ( isset( $post_type_query_vars[ $key ] ) ) {
							$query['post_type'] = $post_type_query_vars[ $key ];
							$query['name']      = $value;
						} else if ( 'talent' === $key ) {
							$query['post_type'] = array( 'person', 'company', 'product' );
							$query['name']      = $value;
						}
					}
				}

				// Do the query
				$query = new \WP_Query( $query );
				if ( ! empty( $query->posts ) && $query->is_singular ) {
					return $query->post->ID;
				} else {
					return 0;
				}
			}
		}

		return 0;
	}

}