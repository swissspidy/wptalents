<?php

namespace WPTalents\Collector;

use WPTalents\Core\Helper;
use \DateTime;

/**
 * Class Score_Collector
 * @package WPTalents\Collector
 */
class Score_Collector extends Collector {

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

		$talent_meta = Helper::get_talent_meta( $this->post );

		// Minimum value
		$score = 1;

		// Calculate plugins score
		$score += $this->_calculate_plugin_score( $talent_meta['plugins'] );

		// Calculate themes score
		$score += $this->_calculate_theme_score( $talent_meta['themes'] );

		// Calculate WordPress.org profile data score
		$score += $this->_calculate_badge_score( $talent_meta['profile']['badges'] );

		// Adjust score based on number of core contributions
		$score += $this->_calculate_contribution_score(
			$talent_meta['contributions'],
			$talent_meta['codex_count'],
			$talent_meta['contribution_count']
		);

		// Adjust score based on number of WordPress.tv videos
		$score += $this->_calculate_wordpresstv_score( $talent_meta['wordpresstv'] );

		$score += $this->_calculate_forums_score( $talent_meta['forums'] );

		// Get median score for the company
		if ( 'company' === get_post_type( $this->post ) ) {
			if ( 1 == get_post_meta( $this->post->ID, 'wordpress_vip' ) ) {
				$score += 10;
			}

			$team_score = $this->_calculate_team_score();

			if ( $team_score ) {
				$score = ( $team_score + $score ) / 2;
			}
		}

		update_post_meta( $this->post->ID, '_score', absint( $score ) );
		update_post_meta( $this->post->ID, '_score_expiration', time() + $this->expiration );

		return $score;

	}

	/**
	 * Calculate score based on a user's plugins.
	 *
	 * @param array $plugins
	 *
	 * @return int
	 */
	public function _calculate_plugin_score( $plugins ) {

		$score = 0;

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
		$avg_downloads = $total_downloads[ (int) round( ( count( $total_downloads ) ) / 2 ) - 1 ];

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

		return $score;

	}

	/**
	 * Calculate score based on a user's themes.
	 *
	 * @param $themes
	 *
	 * @return int
	 */
	public function _calculate_theme_score( $themes ) {

		$score = 0;

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
		$avg_downloads = $total_downloads[ (int) round( count( $total_downloads ) / 2 ) - 1 ];

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

		return $score;

	}

	/**
	 * Calculate the score for all the user's badges.
	 *
	 * @param array $badges
	 *
	 * @return int
	 */
	public function _calculate_badge_score( $badges ) {

		$score = 0;

		// Loop through badges, adjust score depending on type
		foreach ( $badges as $badge ) {
			switch ( $badge ) {
				case 'Core Team':
					$score += 50;
					break;
				case 'Meta Team':
					$score += 25;
					break;
				case 'Plugin Developer':
					$score += 15;
					break;
				case 'Theme Developer':
					$score += 15;
					break;
				case 'Community Team':
					$score += 10;
					break;
				case 'WordCamp Speaker':
					$score += 10;
					break;
				case 'Theme Review Team':
					$score += 5;
					break;
				default:
					$score += 2;
					break;
			}
		}

		return $score;

	}

	/**
	 * Calculate the score for all the users' contributions.
	 *
	 * @param array $contributions
	 * @param int   $codex_count
	 * @param int   $changeset_count
	 *
	 * @return int
	 */
	public function _calculate_contribution_score( $contributions, $codex_count = 0, $changeset_count = 0 ) {

		$score = 0;

		$contribution_types = array();

		// Save number of contributions in this array
		foreach ( $contributions as $contribution ) {
			$contribution_types[ $contribution ] ++;
		}

		// Depending on role the score will be higher or lower
		foreach ( $contribution_types as $type => $count ) {
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
		$score += ( $codex_count / 20 < 20 ) ? $codex_count / 20 : 20;

		// Adjust score based on number of props
		$score += ( $changeset_count / 20 < 20 ) ? $changeset_count / 20 : 20;

		return absint( $score );

	}

	/**
	 * Calculate the overall team score.
	 *
	 * @return bool|int
	 */
	public function _calculate_team_score() {

		$score = 0;

		// Find connected posts
		$people = get_posts( array(
			'connected_type'   => 'team',
			'connected_items'  => $this->post,
			'posts_per_page'   => - 1,
			'suppress_filters' => false,
		) );

		/** @var \WP_Post $person */
		foreach ( $people as $person ) {
			$person_collector = new self( $person );
			$score += $person_collector->get_data();

			unset( $person_collector );
		}

		if ( $people ) {
			return absint( $score / count( $people ) );
		}

		return false;

	}

	/**
	 * Calculate score based on a user's videos on WordPress.tv.
	 *
	 * @param array $videos
	 *
	 * @return int
	 */
	public function _calculate_wordpresstv_score( $videos ) {

		$score = 0;

		$videos = count( $videos );

		// Adjust score based on average downloads
		if ( $videos > 20 ) {
			$score += 20;
		} else if ( $videos > 15 ) {
			$score += 15;
		} else if ( $videos > 10 ) {
			$score += 9;
		} else if ( $videos > 3 ) {
			$score += 3;
		} else {
			$score += 1;
		}

		return $score;

	}

	/**
	 * Calculate score based on a user's forums contributions.
	 *
	 * @param array $forums
	 *
	 * @return int
	 */
	public function _calculate_forums_score( $forums ) {

		$score = 0;

		$total_replies = $forums['total_replies'];
		$threads       = count( $forums['threads'] );

		if ( $total_replies >= 1000 ) {
			$score = 50;
		} else if ( $total_replies > 750 ) {
			$score = 37;
		} else if ( $total_replies >= 490 ) {
			$score = 25;
		} else if ( $total_replies >= 90 ) {
			$score = 10;
		} else if ( $total_replies > 10 ) {
			$score = 2;
		}

		if ( $threads > 5 ) {
			$score += 5;
		}

		return $score;

	}

}