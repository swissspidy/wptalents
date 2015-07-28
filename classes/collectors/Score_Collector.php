<?php
/**
 * Collect the score of an individual talent.
 */

namespace WPTalents\Collector;

use DateTime;
use WPTalents\Core\Helper;

/**
 * Class Score_Collector
 * @package WPTalents\Collector
 */
class Score_Collector {
	/**
	 * Retrieve data from the WordPress.org profile of a user.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool
	 */
	public static function retrieve_data( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return false;
		}

		$talent_meta = Helper::get_talent_meta( $user );

		// Minimum value
		$score = 1;

		// Calculate plugins score
		$score += self::_calculate_plugin_score( $talent_meta['plugins'] );

		// Calculate themes score
		$score += self::_calculate_theme_score( $talent_meta['themes'] );

		// Calculate WordPress.org profile data score
		$score += self::_calculate_badge_score( $talent_meta['profile']['badges'] );

		// Adjust score based on number of core contributions.
		if ( isset( $talent_meta['contributions'] ) ) {
			$score += self::_calculate_contribution_score(
				$talent_meta['contributions'],
				$talent_meta['changesets']['count']
			);
		}

		// Adjust score based on number of WordPress.tv videos
		$score += self::_calculate_wordpresstv_score( $talent_meta['wordpresstv'] );

		$score += self::_calculate_forums_score( $talent_meta['forums'] );

		// Get median score for the company
		if ( 'company' === bp_get_member_type( $user_id ) ) {
			if ( '' === xprofile_get_field_data( 'Badges', $user_id ) ) {
				$score += 10;
			}

			$team_score = self::_calculate_team_score( $user_id );

			if ( $team_score ) {
				$score = ( $team_score + $score ) / 2;
			}
		}

		update_user_meta( $user_id, '_wptalents_score', absint( $score ) );

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

		if ( ! is_array( $plugins ) || empty ( $plugins ) ) {
			return $score;
		}

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

		if ( ! empty( $total_downloads ) ) {
			$avg_downloads = $total_downloads[ (int) round( ( count( $total_downloads ) ) / 2 ) - 1 ];
		} else {
			$avg_downloads = 0;
		}

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
	public static function _calculate_theme_score( $themes ) {

		$score = 0;

		if ( ! is_array( $themes ) || 1 > count( $themes ) ) {
			return $score;
		}

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

		if ( ! empty( $total_downloads ) ) {
			$avg_downloads = $total_downloads[ (int) round( count( $total_downloads ) / 2 ) - 1 ];
		} else {
			$avg_downloads = 0;
		}

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
	public static function _calculate_badge_score( $badges ) {

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
	 * @todo Refactoring without looping over the array twice.
	 *
	 * @param array $contributions
	 * @param int   $changeset_count
	 *
	 * @return int
	 */
	public static function _calculate_contribution_score( $contributions, $changeset_count = 0 ) {

		$score = 0;

		$contribution_types = array_combine( array_values( $contributions ), array_fill( 0, count( $contributions ), 0 ) );

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

		// Adjust score based on number of props
		$score += ( $changeset_count / 20 < 20 ) ? $changeset_count / 20 : 20;

		return absint( $score );

	}

	/**
	 * Calculate the overall team score.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool|int
	 */
	public static function _calculate_team_score( $user_id ) {

		$score = 0;

		$people = team_get_member_user_ids( $user_id );

		/** @var int[] $person */
		foreach ( $people as $person ) {
			// Todo: Get score of each person.
			//$score += wptalents_get_score ( $person );
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
	public static function _calculate_wordpresstv_score( $videos ) {

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
	public static function _calculate_forums_score( $forums ) {

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
