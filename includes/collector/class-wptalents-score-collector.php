<?php

class WP_Talents_Score_Collector extends WP_Talents_Data_Collector {

	/**
	 * @access public
	 * @return mixed
	 */
	public function get_data() {

		$score     = get_post_meta( $this->post->ID, '_score', true );
		$score_exp = get_post_meta( $this->post->ID, '_score_expiration', true );

		if ( ( ! ( $score || $score_exp ) ||
		       ( isset( $score_exp ) && time() >= $score_exp ) )
		     && $this->options['may_renew']
		) {
			add_action( 'shutdown', array( $this, '_retrieve_data' ) );
		}

		$score = apply_filters( 'wptalents_score', $score );

		if ( ! $score ) {
			return 1;
		}

		return absint( $score );

	}

	/**
	 * @access protected
	 *
	 * @return bool
	 */
	public function _retrieve_data() {

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

			if ( $people ) {
				$team_score = $team_score / count( $people );
				$score      = ( $team_score + $score ) / 2;
			}
		}

		update_post_meta( $this->post->ID, '_score', absint( $score ) );
		update_post_meta( $this->post->ID, '_score_expiration', time() + $this->expiration );

		return $score;

	}

}