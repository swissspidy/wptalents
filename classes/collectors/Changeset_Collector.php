<?php
/**
 * Changeset collector that is hooked to a cron event.
 *
 * @package WPTalent
 */

namespace WPTalents\Collector;

use DOMXPath;
use DOMDocument;

/**
 * Class Changeset_Collector
 *
 * @package WPTalents\Collector
 */
class Changeset_Collector {
	/**
	 * Retrieve trac data for a user.
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

		$url = add_query_arg(
			array(
				'q'           => 'props+' . $user->user_login,
				'noquickjump' => '1',
				'changeset'   => 'on',
			),
			'https://core.trac.wordpress.org/search'
		);

		$body = wp_remote_retrieve_body( wp_safe_remote_get( $url ) );

		if ( '' === $body ) {
			return false;
		}

		$dom = new DOMDocument();

		libxml_use_internal_errors( true );
		$dom->loadHTML( $body );
		libxml_clear_errors();

		$finder = new DOMXPath( $dom );

		// Count.
		$count = $finder->query( '/html/head/meta[@name="totalResults"]' )->item( 0 )->getAttribute( 'content' );

		/* @var \DOMNode $results */
		$results = $finder->query( '//dl[@id="results"]/dt' );

		$changesets = array();

		/* @var \DOMNode $child */
		foreach ( $results as $node ) {
			$changeset   = explode( '/', $finder->query( 'a', $node )->item( 0 )->getAttribute( 'href' ) );
			$description = explode( ':', $finder->query( 'a', $node )->item( 0 )->nodeValue, 2 );
			preg_match( '/#([0-9]*)/', $finder->query( '../dd', $node )->item( 0 )->nodeValue, $matches );

			$changesets[] = array(
				'changeset'   => $changeset[2],
				'description' => $description[1],
				'ticket'      => $matches[1],
			);
		}

		$data = array(
			'props_count' => $count,
			'changesets'  => $changesets,
		);

		return update_user_meta( $user_id, '_wptalents_changesets', $data );
	}
}
