<?php
/**
 * WordPress.org forums collector that is hooked to a cron event.
 *
 * @package WPTalent
 */

namespace WPTalents\Collector;

use \DOMDocument;
use \DOMXPath;

/**
 * Class Forums_Collector
 * @package WPTalents\Collector
 */
class Forums_Collector {
	/**
	 * Retrieve user data from the WordPress.org forums.
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

		$intl_forums_base = xprofile_get_field_data( 'International Support Forums', $user_id );

		if ( '' !== $intl_forums_base ) {
			$url = 'https://' . $intl_forums_base . '.forums.wordpress.org/profile/' . $user->user_login;

			$data = self::_retrieve_forums_data( $url );

			bp_update_user_meta( $user_id, '_wptalents_intl_forums', $data );
		}

		$url = 'https://wordpress.org/support/profile/' . $user->user_login;

		$data = self::_retrieve_forums_data( $url );

		return bp_update_user_meta( $user_id, '_wptalents_forums', $data );
	}

	/**
	 * Retrieve the forums data from a given site.
	 *
	 * @param string $url The forums profile URL.
	 *
	 * @return array|bool Data on success, false otherwise.
	 */
	public static function _retrieve_forums_data( $url ) {
		$body = wp_remote_retrieve_body( wp_safe_remote_get( $url ) );

		if ( '' === $body ) {
			return false;
		}

		$dom = new DOMDocument();

		libxml_use_internal_errors( true );
		$dom->loadHTML( $body );
		libxml_clear_errors();

		$finder = new DOMXPath( $dom );

		$recent_replies  = $finder->query( '//div[@id="user-replies"]/ol/li' );
		$threads_started = $finder->query( '//div[@id="user-threads"]/ol/li' );
		$page_numbers    = $finder->query( '//*[contains(@class, "page-numbers")]' );

		$data = array(
			'replies'       => '',
			'threads'       => '',
			'total_replies' => '',
		);

		if ( $page_numbers->length ) {

			$total_pages = $page_numbers->item( $page_numbers->length / 2 - 2 )->nodeValue;

			// It's not 100% accurate, as there may be not so many replies on the last page.
			$data['total_replies'] = $total_pages * $recent_replies->length;

		} else {
			$data['total_replies'] = $recent_replies->length;
		}

		/* @var $reply \DOMNode */
		foreach ( $recent_replies as $reply ) {
			$a_text = $finder->query( 'a', $reply )->item( 0 )->nodeValue;
			$a_href = $finder->query( 'a', $reply )->item( 0 )->getAttribute( 'href' );

			$node_text = $finder->query( 'text()', $reply )->item( 1 )->nodeValue;
			preg_match( '/((([^ ]*)[\s.]+){3})$/', $node_text, $matches );

			$data['replies'][] = array(
				'title' => $a_text,
				'url'   => esc_url_raw( $a_href ),
				'date'  => str_replace( '.', '', trim( $matches[0] ) ),
			);
		}

		foreach ( $threads_started as $thread ) {
			$a_text = $finder->query( 'a', $thread )->item( 0 )->nodeValue;
			$a_href = $finder->query( 'a', $thread )->item( 0 )->getAttribute( 'href' );

			$node_text = $finder->query( 'text()', $thread )->item( 1 )->nodeValue;
			preg_match( '/((([^ ]*)[\s.]+){3})$/', $node_text, $matches );

			$data['threads'][] = array(
				'title' => $a_text,
				'url'   => esc_url_raw( $a_href ),
				'date'  => str_replace( '.', '', trim( $matches[0] ) ),
			);
		}

		return $data;
	}
}
