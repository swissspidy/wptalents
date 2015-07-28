<?php
/**
 * WordPress.tv collector that is hooked to a cron event.
 *
 * @package WPTalent
 */

namespace WPTalents\Collector;

use DateTime;
use DOMDocument;
use DOMXPath;

/**
 * Class WordPressTv_Collector.
 *
 * @package WPTalents\Collector
 */
class WordPressTv_Collector {
	/**
	 * Retrieve data about the user from WordPress.tv
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

		$name = sanitize_title( xprofile_get_field_data( 'Name', $user_id ) );

		if ( '' === $name ) {
			return false;
		}

		$url = trailingslashit( 'https://wordpress.tv/speakers/' . $name );

		$data = self::_retrieve_videos( $url );

		if ( is_array( $data ) ) {
			return bp_update_user_meta( $user_id, '_wptalents_wordpresstv', $data );
		}

		return false;
	}

	/**
	 * Load the videos from a specified page. Is partly recursive.
	 *
	 * @param string $url WordPress.tv URL.
	 *
	 * @return array
	 */
	protected static function _retrieve_videos( $url ) {

		$body = wp_remote_retrieve_body( wp_safe_remote_get( $url ) );

		if ( '' === $body ) {
			return false;
		}

		$dom = new DOMDocument();

		libxml_use_internal_errors( true );
		$dom->loadHTML( $body );
		libxml_clear_errors();

		$finder = new DOMXPath( $dom );

		$videos       = $finder->query( '//*[contains(@class, "video-list")]/li' );
		$older_videos = $finder->query( '//*[contains(@class, "nav-previous")]/a' );

		$data = array(
			'videos'       => '',
			'total_videos' => $videos->length,
		);

		/* @var $reply \DOMNode */
		foreach ( $videos as $video ) {
			$img    = $finder->query( '*[contains(@class, "video-thumbnail")]/img', $video )->item( 0 )->getAttribute( 'src' );
			$a_text = $finder->query( '*[contains(@class, "video-description")]/h4/a', $video )->item( 0 )->nodeValue;
			$a_href = $finder->query( '*[contains(@class, "video-description")]/h4/a', $video )->item( 0 )->getAttribute( 'href' );

			$event = $finder->query( '*[contains(@class, "video-description")]/*[contains(@class, "video-events")]/a', $video )->item( 0 )->nodeValue;

			$description = $finder->query( '*[contains(@class, "video-description")]/*[contains(@class, "video-excerpt")]/p', $video )->item( 0 )->nodeValue;
			preg_match( '/^((?:\S+\s+){2}\S+).*/', $description, $matches );

			$description = str_replace( '&#8212', '–', $description );

			$date = new DateTime( $matches[1] );

			$data['videos'][] = array(
				'title'       => $a_text,
				'date'        => $date->format( 'Y-m-d' ),
				'url'         => $a_href,
				'image'       => $img,
				'event'       => $event,
				'description' => $description,
			);
		}

		if ( $older_videos->length ) {
			$more_videos    = self::_retrieve_videos( $older_videos->item( 0 )->getAttribute( 'href' ) );
			$data['videos'] = array_merge( $data['videos'], $more_videos['videos'] );
			$data['total_videos'] += $more_videos['total_videos'];
		}

		return $data;
	}
}
