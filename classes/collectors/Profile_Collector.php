<?php
/**
 * WordPress.org profile collector that is hooked to a cron event.
 *
 * @package WPTalent
 */

namespace WPTalents\Collector;

use DateTime;
use DOMDocument;
use DOMXPath;

/**
 * Class Profile_Collector.
 *
 * @package WPTalents\Collector
 */
class Profile_Collector {
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

		$url = 'https://profiles.wordpress.org/' . $user->user_login;

		$body = wp_remote_retrieve_body( wp_safe_remote_get( $url ) );

		if ( '' === $body ) {
			return false;
		}

		$dom = new DOMDocument();

		libxml_use_internal_errors( true );
		$dom->loadHTML( $body );
		libxml_clear_errors();

		$finder = new DOMXPath( $dom );

		$avatar       = $finder->query( '//div[@id="meta-status-badge-container"]/a/img' );
		$description  = $finder->query( '//div[@class="item-meta-about"]' );
		$location     = $finder->query( '//li[@id="user-location"]' );
		$member_since = $finder->query( '//li[@id="user-member-since"]' );
		$website      = $finder->query( '//li[@id="user-website"]/a' );
		$company      = $finder->query( '//li[@id="user-company"]' );
		$slack        = $finder->query( '//li[@id="slack-username"]' );
		$badges       = $finder->query( '//ul[@id="user-badges"]/li/div' );

		// Fill Member since field.
		if ( '' === xprofile_get_field_data( 'Member since', $user_id ) ) {
			preg_match( '/((([^ ]*)[\s.]+){3})$/', $member_since->item( 0 )->nodeValue, $matches );
			$date         = new DateTime( $matches[0] );
			$member_since = $date->format( 'Y-m-d' );

			xprofile_set_field_data( 'Member since', $user_id, $member_since );
		}

		// Store gravatar hash.
		bp_update_user_meta( $user_id, '_wptalents_avatar', strtok( $avatar->item( 0 )->getAttribute( 'src' ), '?' ) );

		// Fill Bio field.
		if ( $description->length && '' === xprofile_get_field_data( 'Bio', $user_id ) ) {
			xprofile_set_field_data( 'Bio', $user_id, $description->item( 0 )->ownerDocument->saveHTML( $description->item( 0 ) ) );
		}

		if ( $location->length && '' === xprofile_get_field_data( 'Location', $user_id ) ) {
			xprofile_set_field_data( 'Location', $user_id, trim( $location->item( 0 )->nodeValue ) );
		}

		if ( 'person' === bp_get_member_type( $user_id ) ) {
			if ( $company->length && '' === xprofile_get_field_data( 'Job', $user_id ) ) {
				xprofile_set_field_data( 'Job', $user_id, trim( preg_replace( '/\t+/', '', $company->item( 0 )->nodeValue ) ) );
			}
		}

		// Fill Slack field.
		if ( $slack->length && '' === xprofile_get_field_data( 'Slack', $user_id ) ) {
			$slack_username = trim( preg_replace( '/\t+/', '', $slack->item( 0 )->nodeValue ) );
			$slack_username = explode( ' ', $slack_username );

			xprofile_set_field_data( 'Slack', $user_id, substr( trim( $slack_username[0] ), 1 ) );
		}

		// Fill Website field.
		if ( $website->length && '' === xprofile_get_field_data( 'Website', $user_id ) ) {
			xprofile_set_field_data( 'Website', $user_id, trim( $website->item( 0 )->getAttribute( 'href' ) ) );
		}

		// Fill Badges field.
		$badge_names = array();
		foreach ( $badges as $badge ) {
			$badge_names = $badge->getAttribute( 'title' );
		}

		xprofile_set_field_data( 'Badges', $user_id, $badge_names );
	}
}
