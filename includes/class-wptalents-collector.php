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

		$defaults = array(
			'username' => get_post_meta( $this->post->ID, 'wordpress-username', true ),
			'ajax'     => ( defined( DOING_AJAX ) && DOING_AJAX || isset( $_POST ) ) ? true : false,
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

		if ( ! $this->options->is_ajax &&
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
	 *
	 * @access private
	 * @return bool
	 */
	public function _calculate_score() {
		$score = 1;

		// Calculate plugins score
		$plugins = (array) $this->get_plugins();

		$avg_downloads = array();

		$old_plugins = 0;

		$now = new DateTime( 'now' );

		foreach( $plugins as $plugin ) {
			// Check when the plugin was last updated
			$plugin_updated = new DateTime( $plugin->last_updated );
			$date_diff = $now->diff($plugin_updated);

			// Don't take into account plugins that haven't been updated for 2+ years
			if ( 2 >= $date_diff->format( '%y' ) ) {
				$old_plugins++;

				continue;
			}

			$score += 1 + 1 * ( $plugin->rating / 100 );


			$avg_downloads[] = $plugin->downloaded;
		}

		// Check the average downloads count using the median value
		sort( $avg_downloads, SORT_NUMERIC );
		$avg_downloads = $avg_downloads[ round( ( count( $avg_downloads ) - $old_plugins ) / 2 ) -1 ];

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

		$avg_downloads = array();

		foreach( $themes as $theme ) {
			$score += 1 + 1 * ( $theme->rating / 100 );

			$avg_downloads[] = $theme->downloaded;
		}

		// Check the average downloads count using the median value
		sort( $avg_downloads, SORT_NUMERIC );
		$avg_downloads = $avg_downloads[ round( count( $avg_downloads ) / 2 ) -1 ];

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
			foreach ( $profile['badges'] as $badge ) {
				switch( $badge ) {
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
			foreach( $people as $person ) {
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

		if ( ! $this->options->is_ajax &&
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
	 * @access private
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

		if ( ! $this->options->is_ajax &&
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
	 * @access private
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

		if ( ! $this->options->is_ajax &&
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
	 * @param string $username The WordPress.org username.
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
		$socials  = $finder->query( '//ul[@id="user-social-media-accounts"]/li/a' );
		$badges   = $finder->query( '//ul[@id="user-badges"]/li/div' );

		$data = array(
			'name'     => trim( $name->item( 0 )->nodeValue ),
			'avatar'   => strtok( $avatar->item( 0 )->getAttribute( 'src' ), '?' ),
			'location' => trim( $location->item( 0 )->nodeValue ),
			'company'  => '',
			'website'  => '',
			'socials'  => array(),
			'badges'   => array(),
		);

		if ( $company->length ) {
			$data['company'] = trim( preg_replace( '/\t+/', '', $company->item( 0 )->nodeValue ) );
		}

		if ( $website->length ) {
			$data['website'] = trim( $website->item( 0 )->getAttribute( 'href' ) );
		}

		foreach ( $socials as $item ) {
			$icon = $item->getElementsByTagName( "div" );

			$data['socials'][ $icon->item( 0 )->getAttribute( 'title' ) ] = $item->getAttribute( 'href' );
		}

		foreach ( $badges as $badge ) {
			$data['badges'][] = $badge->getAttribute( 'title' );
		}

		$data = array(
			'data'       => $data,
			'expiration' => time() + HOUR_IN_SECONDS * 12,
		);

		update_post_meta( $this->post->ID, '_profile',  $data );

		return $data;
	}

	public function get_contributions_of_user( $username ) {
		global $wp_version;

		$version = number_format( $wp_version, 1, '.', '' );

		$contributions = array();
		while ( $version ) {
			$version = number_format( $version, 1, '.', '' );

			$role = $this->_loop_wp_version( $version, $username );

			if ( false !== $role ) {
				if ( $role ) {
					$contributions[ $version ] = $role;
				}

				$version -= 0.1;
			}
			else {
				$version = false;
			}
		}

		return $contributions;
	}

	private function _loop_wp_version( $version, $username = false ) {
		$credits  = $this->_get_credits( $version );

		if ( $credits ) {

			foreach ( $credits['groups'] as $group_slug => $group_data ) {
				if ( 'libraries' == $group_data['type'] ) {
					continue;
				}

				foreach ( $group_data['data'] as $person_username => $person_data ) {
					if ( strtolower( $person_username ) == $username ) {
						$role = '';

						if ( 'titles' == $group_data['type'] ) {
							if ( $person_data[3] ) {
								$role = $person_data[3];
							}
							else if ( $group_data['name'] ) {
								$role = $group_data['name'];
							}
							else {
								$role = ucfirst( str_replace( '-', ' ', $group_slug ) );
							}

							$role = rtrim( $role, 's' );
						}
						else {
							$role = __( 'Core Contributor', 'wpcentral-api' );
						}

						return $role;
					}
				}
			}

			return null;
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
	public function _get_credits( $wp_version ) {
		// We can't request data before this.
		if ( version_compare( $wp_version, '3.2', '<' ) ) {
			return false;
		}

		$response = wp_remote_get( 'http://api.wordpress.org/core/credits/1.1/?version=' . $wp_version . '&locale=' .$locale );

		if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$results = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $results ) || $results['data']['version'] != (string) $wp_version ) {
			return false;
		}

		return $results;
	}

	/**
	 * Retrieve language packs
	 *
	 * @param string $wp_version The WordPress version.
	 *
	 * @return array|bool A list of all locales with language packs, or false on error.
	 */
	public function get_language_packs( $wp_version ) {
		// We can't request data before this.
		if ( version_compare( $wp_version, '4.0', '<' ) ) {
			return false;
		}

		$response = wp_remote_get( 'http://api.wordpress.org/translations/core/1.0/?version=' . $wp_version );

		if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$results = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $results ) ) {
			return false;
		}

		return $results;
	}

	public function get_changeset_items( $username ) {
		if ( ! $username ) {
			return false;
		}

		$items = array();

		$results_url = add_query_arg(
			array(
				'q'             => 'props+' . $username,
				'noquickjump'   => '1',
				'changeset'     => 'on'
			),
			'https://core.trac.wordpress.org/search'
		);
		$response = wp_remote_get( $results_url );

		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			$results  = wp_remote_retrieve_body( $response );

			$results  = preg_replace( '/\s+/', ' ', $results );
			$results  = str_replace( PHP_EOL, '', $results );
			$pattern  = '/<dt><a href="(.*?)" class="searchable">\[(.*?)\]: ((?s).*?)<\/a><\/dt>\s*(<dd class="searchable">.*?. #(.*?) .*?.<\/dd>)/';

			preg_match_all( $pattern, $results, $matches, PREG_SET_ORDER );

			foreach ( $matches as $match ) {
				array_shift( $match );

				$new_match = array(
					'link'          => 'https://core.trac.wordpress.org' . $match[0],
					'changeset'     => intval($match[1]),
					'description'   => $match[2],
					'ticket'        => isset( $match[3] ) ? intval($match[4]) : '',
				);

				array_push( $items, $new_match );
			}

		}

		return $items;
	}

	public function get_changeset_count( $username ) {
		if ( ! $username ) {
			return false;
		}

		$count = 0;

		$results_url = add_query_arg(
			array(
				'q'             => 'props+' . $username,
				'noquickjump'   => '1',
				'changeset'     => 'on'
			),
			'https://core.trac.wordpress.org/search'
		);
		$response = wp_remote_get( $results_url );

		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			$results = wp_remote_retrieve_body( $response );
			$pattern = '/<meta name="totalResults" content="(\d*)" \/>/';

			preg_match( $pattern, $results, $matches );

			$count = intval( $matches[1] );
		}

		return $count;
	}

	public function get_codex_items( $username, $limit = 10 ) {
		if ( ! $username ) {
			return false;
		}

		$items = array();

		$results_url = add_query_arg( array(
			'action'    => 'query',
			'list'      => 'usercontribs',
			'ucuser'    => $username,
			'uclimit'   => $limit,
			'ucdir'     => 'older',
			'format'    => 'json'
		), 'https://codex.wordpress.org/api.php' );
		$response = wp_remote_get( $results_url );

		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			$results   = wp_remote_retrieve_body( $response );
			$raw       = json_decode( $results );

			foreach ( $raw->query->usercontribs as $item ) {
				$count = 0;
				$clean_title = preg_replace( '/^Function Reference\//', '', (string) $item->title, 1, $count );

				$new_item = array(
					'title'         => $clean_title,
					'description'   => (string) $item->comment,
					'revision'      => (int) $item->revid,
					'function_ref'  => (bool) $count
				);

				array_push( $items, $new_item );
			}
		}

		return $items;
	}

	public function get_codex_count( $username ) {
		if ( ! $username ) {
			return false;
		}

		$count = 0;

		$results_url = add_query_arg(
			array(
				'action'    =>  'query',
				'list'      =>  'users',
				'ususers'   =>  $username,
				'usprop'    =>  'editcount',
				'format'    =>  'json'
			),
			'https://codex.wordpress.org/api.php'
		);
		$response = wp_remote_get( $results_url );

		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			$results  = wp_remote_retrieve_body( $response );

			$raw   = json_decode( $results );
			$count = (int) $raw->query->users[0]->editcount;
		}

		return $count;
	}

}