<?php
/**
 * WP Talents Base Class.
 *
 * @package   WP_Talents
 * @author    Pascal Birchler <pascal.birchler@spinpress.com>
 * @license   GPL-2.0+
 * @link      https://spinpress.com
 * @copyright 2014 WP Talents
 */

/**
 * WP Talents Data Collector Class.
 *
 * @package WP_Talents_Collector
 * @author  Pascal Birchler <pascal.birchler@spinpress.com>
 */
class WP_Talents_Collector {

	/**
	 * Initialize the collector.
	 *
	 * @since 0.0.1
	 *
	 * @param WP_Post|int $post
	 * @param array       $args
	 */
	public function __construct( $post, $args = array() ) {
		$this->post = get_post( $post );

		if ( ! is_object( $this->post ) ) {
			return false;
		}

		$may_renew = true;

		if (
			( defined( 'JSON_REQUEST' ) && JSON_REQUEST ) ||
	        ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ||
			isset( $_POST['action'] )
		)	{
			$may_renew = false;
		}

		$defaults = array(
			'username' => get_post_meta( $this->post->ID, 'wordpress-username', true ),
			'may_renew'  => $may_renew
		);

		$this->options = wp_parse_args( $args, $defaults );
	}

	/**
	 * @access public
	 * @return mixed
	 */
	public function get_score() {
		$score     = get_post_meta( $this->post->ID, '_score', true );
		$score_exp = get_post_meta( $this->post->ID, '_score_expiration', true );

		if ( $this->options['may_renew'] &&
		     ( ! ( $score || $score_exp ) ||
		       ( isset( $score_exp ) && time() >= $score_exp )
		     )
		) {
			add_action( 'shutdown', array( $this, '_calculate_score' ) );
		}

		if ( ! $score ) {
			return 1;
		}

		return (int) $score;
	}

	/**
	 * Calculates the score of the current talent.
	 *
	 * @access protected
	 *
	 * @return int The score, with a minimum value of 1.
	 */
	public function _calculate_score() {
		// Minimum value
		$score = 1;

		// Calculate plugins score
		$plugins = (array) $this->get_plugins();

		// Store the download counts in this array
		$total_downloads = array();

		// Set today's date
		$now = new DateTime( 'now' );

		// Loop through plugins
		foreach ( $plugins as $plugin ) {
			// Check when the plugin was last updated
			$plugin_updated = new DateTime( $plugin->last_updated );
			$date_diff      = $now->diff( $plugin_updated );

			// Don't take into account plugins that haven't been updated for 2+ years
			if ( 2 >= $date_diff->format( '%y' ) ) {
				continue;
			}

			// Adjust score based on the plugin's rating
			$score += 1 + 1 * ( $plugin->rating / 100 );

			// Add to downloads array
			$total_downloads[] = $plugin->downloaded;
		}

		// Check the average downloads count using the median value
		sort( $total_downloads, SORT_NUMERIC );
		$avg_downloads = $total_downloads[ round( ( count( $total_downloads ) ) / 2 ) - 1 ];

		// Adjust score based on average downloads
		if ( $avg_downloads > 100000 ) {
			$score += 10;
		} else if ( $avg_downloads > 50000 ) {
			$score += 7;
		} else if ( $avg_downloads > 10000 ) {
			$score += 4;
		} else if ( $avg_downloads > 1000 ) {
			$score += 2;
		} else {
			$score += 1;
		}

		// Calculate themes score
		$themes = (array) $this->get_themes();

		// Store the download counts in this array
		$total_downloads = array();

		// Loop through themes
		foreach ( $themes as $theme ) {
			// Adjust score based on the theme's rating
			$score += 1 + 1 * ( $theme->rating / 100 );

			// Add to downloads array
			$total_downloads[] = $theme->downloaded;
		}

		// Check the average downloads count using the median value
		sort( $total_downloads, SORT_NUMERIC );
		$avg_downloads = $total_downloads[ round( count( $total_downloads ) / 2 ) - 1 ];

		// Adjust score based on average downloads
		if ( $avg_downloads > 100000 ) {
			$score += 15;
		} else if ( $avg_downloads > 50000 ) {
			$score += 9;
		} else if ( $avg_downloads > 10000 ) {
			$score += 5;
		} else if ( $avg_downloads > 1000 ) {
			$score += 3;
		} else {
			$score += 1;
		}

		// Calculate WordPress.org profile data score
		$profile = $this->get_profile();

		if ( is_array( $profile['badges'] ) ) {
			// Loop through badges, adjust score depending on type
			foreach ( $profile['badges'] as $badge ) {
				switch ( $badge ) {
					case 'Core Team':
						$score += 50;
						break;
					case 'Plugin Developer':
						$score += 15;
						break;
					case 'Theme Developer':
						$score += 7;
						break;
					case 'Theme Review Team':
						$score += 3;
						break;
					case 'Community Team':
						$score += 5;
						break;
					case 'WordCamp Speaker':
						$score += 10;
						break;
					default:
						$score += 2;
						break;
				}
			}
		}

		// Adjust score based on number of core contributions
		$contributions = $this->get_contributions();
		$contribution_types = array();

		// Save number of contributions in this array
		foreach( $contributions as $contribution ) {
			$contribution_types[$contribution]++;
		}

		// Depending on role the score will be higher or lower
		foreach( $contribution_types as $type => $count ) {
			switch ( $type ) {
				case 'Core Contributor':
					$factor = 2;
					break;
				case 'Core Committer':
					$factor = 3;
					break;
				case 'Core Developer':
					$factor = 4;
					break;
				case 'Lead Developer':
					$factor = 5;
					break;
				case 'Release Lead':
					$factor = 6;
					break;
				default:
					$factor = 1;
					break;
			}

			$score += $factor * $count;
		}

		// Adjust score based on number of codex contributions
		$codex_count = $this->get_codex_count();
		$score += ( $codex_count / 20 < 20 ) ? $codex_count / 20 : 20;

		// Adjust score based on number of props
		$changeset_count = $this->get_changeset_count();
		$score += ( $changeset_count / 20 < 20 ) ? $changeset_count / 20 : 20;

		// Get median score for the company
		if ( 'company' === get_post_type( $this->post ) ) {
			$team_score = 0;

			if ( 1 == get_post_meta( $this->post->ID, 'wordpress_vip' ) ) {
				$team_score += 10;
			}

			// Find connected posts
			$people = get_posts( array(
				'connected_type'   => 'team',
				'connected_items'  => $this->post,
				'nopaging'         => true,
				'suppress_filters' => false
			) );

			/** @var WP_Post $person */
			foreach ( $people as $person ) {
				$person_collector = new self( $person->ID );
				$team_score += $person_collector->get_score();

				unset( $person_collector );
			}

			$team_score = $team_score / count( $people );

			//$score = ( $team_score > $score ) ? $team_score : $score;
			$score = ( $team_score + $score ) / 2;
		}

		update_post_meta( $this->post->ID, '_score', absint( $score ) );
		update_post_meta( $this->post->ID, '_score_expiration', time() + HOUR_IN_SECONDS * 12 );

		return $score;
	}

	/**
	 * @access public
	 * @return mixed
	 */
	public function get_themes() {
		if ( '' === $this->options['username'] ) {
			return false;
		}


		$data = get_post_meta( $this->post->ID, '_themes', true );

		if ( $this->options['may_renew'] &&
		     ( ! $data ||
		       ( isset( $data['expiration'] ) &&
		         time() >= $data['expiration'] ) )
		) {
			add_action( 'shutdown', array( $this, '_retrieve_themes' ) );
		}

		if ( ! $data ) {
			return 0;
		}

		return $data['data'];
	}

	/**
	 * @access protected
	 *
	 * @return bool
	 */
	public function _retrieve_themes() {
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$args = array(
			'author'   => $this->options['username'],
			'per_page' => 30,
			'fields'   => array(
				'description'   => false,
				'compatibility' => false,
				//'banners'     => true,
				'icons'         => true,
				'downloaded'    => true,
				'last_updated'  => true,
			),
		);

		$data = themes_api( 'query_themes', $args );

		if ( $data && isset( $data->themes ) ) {

			$data = array(
				'data'       => $data->themes,
				'expiration' => time() + HOUR_IN_SECONDS * 12,
			);

			update_post_meta( $this->post->ID, '_themes', $data );

			return $data;
		}

		return false;
	}

	/**
	 * @access public
	 * @return mixed
	 */
	public function get_plugins() {
		if ( '' === $this->options['username'] ) {
			return false;
		}

		$data = get_post_meta( $this->post->ID, '_plugins', true );

		if ( $this->options['may_renew'] &&
		     ( ! $data ||
		       ( isset( $data['expiration'] ) && time() >= $data['expiration'] ) )
		) {
			add_action( 'shutdown', array( $this, '_retrieve_plugins' ) );
		}

		if ( ! $data ) {
			return 0;
		}

		return $data['data'];
	}

	/**
	 * @access protected
	 *
	 * @return mixed
	 */
	public function _retrieve_plugins() {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$args = array(
			'author'   => $this->options['username'],
			'per_page' => 100,
			'fields'   => array(
				'description'   => false,
				'compatibility' => false,
				//'banners'     => true,
				'icons'         => true,
				'downloaded'    => true,
				'last_updated'  => true,
			),
		);

		$data = plugins_api( 'query_plugins', $args );

		if ( $data && isset( $data->plugins ) ) {

			$data = array(
				'data'       => $data->plugins,
				'expiration' => time() + HOUR_IN_SECONDS * 12,
			);

			update_post_meta( $this->post->ID, '_plugins', $data );

			return $data;
		}

		return false;
	}

	/**
	 * @return mixed The profile data as an array, false on failure.
	 */
	public function get_profile() {
		$data = get_post_meta( $this->post->ID, '_profile', true );

		if ( $this->options['may_renew'] &&
		     ( ! $data ||
		       ( isset( $data['expiration'] ) && time() >= $data['expiration'] ) )
		) {
			add_action( 'shutdown', array( $this, '_retrieve_wordpress_org_profile_data' ) );
		}

		if ( ! $data ) {
			return false;
		}

		return $data['data'];
	}

	/**
	 * @access protected
	 *
	 * @return array
	 */
	public function _retrieve_wordpress_org_profile_data() {
		$url = 'https://profiles.wordpress.org/' . $this->options['username'];

		$request = wp_remote_get( $url, array( 'redirection' => 0 ) );
		$code    = wp_remote_retrieve_response_code( $request );

		if ( 200 !== $code ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $request );

		$dom = new DOMDocument();
		@$dom->loadHTML( $body ); // Error supressing due to the fact that special characters haven't been converted to HTML.
		$finder = new DomXPath( $dom );

		$name     = $finder->query( '//h2[@class="fn"]' );
		$avatar   = $finder->query( '//div[@id="meta-status-badge-container"]/a/img' );
		$location = $finder->query( '//li[@id="user-location"]' );
		$website  = $finder->query( '//li[@id="user-website"]/a' );
		$company  = $finder->query( '//li[@id="user-company"]' );
		$badges   = $finder->query( '//ul[@id="user-badges"]/li/div' );

		$data = array(
			'name'     => trim( $name->item( 0 )->nodeValue ),
			'avatar'   => strtok( $avatar->item( 0 )->getAttribute( 'src' ), '?' ),
			'location' => trim( $location->item( 0 )->nodeValue ),
			'company'  => '',
			'website'  => '',
			'badges'   => array(),
		);

		if ( $company->length ) {
			$data['company'] = trim( preg_replace( '/\t+/', '', $company->item( 0 )->nodeValue ) );
		}

		if ( $website->length ) {
			$data['website'] = trim( $website->item( 0 )->getAttribute( 'href' ) );
		}

		foreach ( $badges as $badge ) {
			$data['badges'][] = $badge->getAttribute( 'title' );
		}

		$data = array(
			'data'       => $data,
			'expiration' => time() + HOUR_IN_SECONDS * 12,
		);

		update_post_meta( $this->post->ID, '_profile', $data );

		return $data;
	}

	public function get_social_links() {
		$social_links = array();

		$meta_fields = array_merge(
			array( 'wordpressdotorg' => get_post_meta( $this->post->ID, 'wordpress-username', true ) ),
			(array) get_post_meta( $this->post->ID, 'social', true )
		);

		foreach ( $meta_fields as $field => $value ) {
			if ( empty ( $value ) ) {
				continue;
			}

			switch ( $field ) {
				case 'wordpressdotorg':
					$social_links[$field] = array(
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
	 * @return array Location data as an array
	 */
	public function get_map_data() {
		$all_locations = array();

		$location = WP_Talents::get_talent_meta( $this->post->ID, 'location' );

		if ( empty( $location['lat'] ) || empty( $location['long'] ) ) {
			return false;
		}

		$all_locations[] = array(
			'id'    => $this->post->ID,
			'title' => $this->post->post_title,
			'name'  => $location['name'],
			'lat'   => $location['lat'],
			'long'  => $location['long'],
		);

		if ( 'company' === $this->post->post_type ) {
			// Find connected posts
			$people = get_posts( array(
				'connected_type'   => 'team',
				'connected_items'  => $this->post,
				'nopaging'         => true,
				'suppress_filters' => false
			) );

			/** @var WP_Post $person */
			foreach ( $people as $person ) {
				$person_collector = new self( $person->ID );

				if ( ! $person_location = $person_collector->get_map_data() ) {
					continue;
				}

				$all_locations = array_merge( $all_locations, $person_location );
			}
		}

		return $all_locations;
	}

	public function get_contributions() {
		global $wp_version;

		$version = number_format( $wp_version, 1, '.', '' );

		$contributions = array();
		while ( $version ) {
			$version = number_format( $version, 1, '.', '' );

			$role = $this->_loop_wp_version( $version, $this->options['username'] );

			if ( false !== $role ) {
				if ( $role ) {
					$contributions[ $version ] = $role;
				}

				$version -= 0.1;
			} else {
				$version = false;
			}
		}

		return $contributions;
	}

	/**
	 * @param string $version The WP version to check.
	 *
	 * @return bool|string| The user's role on success, false otherwise.
	 */
	protected function _loop_wp_version( $version ) {
		if ( false === ( $credits = get_transient( 'wordpress-credits-' . $version ) ) ) {
			$credits = $this->_get_credits( $version );
		}

		if ( $credits ) {

			foreach ( $credits['groups'] as $group_slug => $group_data ) {
				if ( 'libraries' == $group_data['type'] ) {
					continue;
				}

				foreach ( $group_data['data'] as $person_username => $person_data ) {
					if ( strtolower( $person_username ) == $this->options['username'] ) {
						if ( 'titles' == $group_data['type'] ) {
							if ( $person_data[3] ) {
								$role = $person_data[3];
							} else if ( $group_data['name'] ) {
								$role = $group_data['name'];
							} else {
								$role = ucfirst( str_replace( '-', ' ', $group_slug ) );
							}

							$role = rtrim( $role, 's' );
						} else {
							$role = __( 'Core Contributor', 'wptalents' );
						}

						return $role;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Retrieve the contributor credits.
	 *
	 * @param string $wp_version The WordPress version.
	 *
	 * @return array|bool A list of all of the contributors, or false on error.
	 */
	protected function _get_credits( $wp_version, $locale = '' ) {
		// We can't request data before this.
		if ( version_compare( $wp_version, '3.2', '<' ) ) {
			return false;
		}

		$response = wp_remote_get( 'http://api.wordpress.org/core/credits/1.1/?version=' . $wp_version . '&locale=' . $locale );

		if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$results = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $results ) || $results['data']['version'] != (string) $wp_version ) {
			return false;
		}

		set_transient( 'wordpress-credits-' . $wp_version, $results, 12 * HOUR_IN_SECONDS );

		return $results;
	}

	/**
	 * @access public
	 * @return mixed
	 */
	public function get_changeset_count() {
		if ( '' === $this->options['username'] ) {
			return false;
		}

		$data = get_post_meta( $this->post->ID, '_changeset_count', true );

		if ( $this->options['may_renew'] &&
		     ( ! $data ||
		       ( isset( $data['expiration'] ) && time() >= $data['expiration'] ) )
		) {
			add_action( 'shutdown', array( $this, '_retrieve_changeset_count' ) );
		}

		if ( ! $data ) {
			return 0;
		}

		return $data['data'];
	}

	/**
	 * @access protected
	 *
	 * @return array|bool
	 */
	public function _retrieve_changeset_count() {
		$results_url = add_query_arg(
			array(
				'q'           => 'props+' . $this->options['username'],
				'noquickjump' => '1',
				'changeset'   => 'on'
			),
			'https://core.trac.wordpress.org/search'
		);

		$response    = wp_remote_get( $results_url );

		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			$results = wp_remote_retrieve_body( $response );
			$pattern = '/<meta name="totalResults" content="(\d*)" \/>/';

			preg_match( $pattern, $results, $matches );

			$count = intval( $matches[1] );

			$data = array(
				'data'       => $count,
				'expiration' => time() + HOUR_IN_SECONDS * 12,
			);

			update_post_meta( $this->post->ID, '_changeset_count', $data );

			return $data;
		}

		return false;
	}

	/**
	 * @access public
	 * @return mixed
	 */
	public function get_codex_count() {
		if ( '' === $this->options['username'] ) {
			return false;
		}

		$data = get_post_meta( $this->post->ID, '_codex_count', true );

		if ( $this->options['may_renew'] &&
		     ( ! $data ||
		       ( isset( $data['expiration'] ) && time() >= $data['expiration'] ) )
		) {
			add_action( 'shutdown', array( $this, '_retrieve_codex_count' ) );
		}

		if ( ! $data ) {
			return 0;
		}

		return $data['data'];
	}

	/**
	 * @access protected
	 *
	 * @return array|bool
	 */
	public function _retrieve_codex_count() {
		$results_url = add_query_arg(
			array(
				'action'  => 'query',
				'list'    => 'users',
				'ususers' => $this->options['username'],
				'usprop'  => 'editcount',
				'format'  => 'json'
			),
			'https://codex.wordpress.org/api.php'
		);

		$response    = wp_remote_get( $results_url );

		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			$results = wp_remote_retrieve_body( $response );

			$raw   = json_decode( $results );
			$count = (int) $raw->query->users[0]->editcount;

			$data = array(
				'data'       => $count,
				'expiration' => time() + HOUR_IN_SECONDS * 12,
			);

			update_post_meta( $this->post->ID, '_codex_count', $data );

			return $data;
		}

		return false;
	}

}