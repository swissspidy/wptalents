<?php

namespace WPTalents\Collector;

use \DOMDocument;
use \DomXPath;
use \DateTime;

class WordPressTv_Collector extends Collector {

	/**
	 * @access public
	 * @return mixed
	 */
	public function get_data() {

		$data = get_post_meta( $this->post->ID, '_wordpresstv', true );

		if ( ( ! $data ||
		       ( isset( $data['expiration'] ) && time() >= $data['expiration'] ) )
		     && $this->options['may_renew']
		) {
			add_action( 'shutdown', array( $this, '_retrieve_data' ) );
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
	public function _retrieve_data() {

		$url = trailingslashit( 'http://wordpress.tv/speakers/' . $this->post->post_name );

		$data = $this->_retrieve_videos( $url );

		$data = array(
			'data'       => $data,
			'expiration' => time() + $this->expiration,
		);

		update_post_meta( $this->post->ID, '_wordpresstv', $data );

		return $data;

	}

	public function _retrieve_videos( $url ) {

		$body = wp_remote_retrieve_body( wp_remote_get( $url ) );

		if ( '' === $body ) {
			return false;
		}

		$dom = new DOMDocument();

		libxml_use_internal_errors( true );
		$dom->loadHTML( $body );
		libxml_clear_errors();

		$finder = new DomXPath( $dom );

		$videos       = $finder->query( '//*[contains(@class, "video-list")]/li' );
		$older_videos = $finder->query( '//*[contains(@class, "nav-previous")]/a' );

		$data = array(
			'videos'       => '',
			'total_videos' => $videos->length,
		);

		/** @var $reply \DOMNode */
		foreach ( $videos as $video ) {
			$img    = $finder->query( '*[contains(@class, "video-thumbnail")]/img', $video )->item( 0 )->getAttribute( 'src' );
			$a_text = $finder->query( '*[contains(@class, "video-description")]/h4/a', $video )->item( 0 )->nodeValue;
			$a_href = $finder->query( '*[contains(@class, "video-description")]/h4/a', $video )->item( 0 )->getAttribute( 'href' );

			$event = $finder->query( '*[contains(@class, "video-description")]/*[contains(@class, "video-events")]/a', $video )->item( 0 )->nodeValue;

			$description = $finder->query( '*[contains(@class, "video-description")]/*[contains(@class, "video-excerpt")]/p', $video )->item( 0 )->nodeValue;
			preg_match( '/^((?:\S+\s+){2}\S+).*/', $description, $matches );

			$date = new DateTime( $matches[1] );

			$data['videos'][] = array(
				'title'       => $a_text,
				'date'        => $date->format( 'Y-m-d' ),
				'url'         => $a_href,
				'image'       => $img,
				'event'       => $event,
				'description' => $description
			);
		}

		if ( $older_videos->length ) {
			$more_videos    = $this->_retrieve_videos( $older_videos->item( 0 )->getAttribute( 'href' ) );
			$data['videos'] = array_merge( $data['videos'], $more_videos['videos'] );
			$data['total_videos'] += $more_videos['total_videos'];
		}

		return $data;
	}

}