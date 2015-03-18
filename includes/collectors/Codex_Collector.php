<?php

namespace WPTalents\Collector;

/**
 * Class Codex_Collector
 * @package WPTalents\Collector
 */
class Codex_Collector extends Collector {

	/**
	 * @access public
	 * @return mixed
	 */
	public function get_data() {

		$data = get_user_meta( $this->user->ID, '_codex_count', true );

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
		$count = $this->get_codex_count();

		$data = array(
			'data'       => array(
				'count'         => $count,
				'contributions' => array(),
			),
			'expiration' => time() + $this->expiration,
		);

		if ( $count > 0 ) {

			$results_url = add_query_arg( array(
				'action'  => 'query',
				'list'    => 'usercontribs',
				'ucuser'  => $this->options['username'],
				'uclimit' => 100,
				'ucdir'   => 'older',
				'format'  => 'json',
			), 'https://codex.wordpress.org/api.php' );

			$results = wp_remote_retrieve_body( wp_safe_remote_get( $results_url ) );

			if ( is_wp_error( $results ) ) {
				return false;
			}

			$raw = json_decode( $results );

			/* Expected array format is as follows:
			 * Array
			 * (
			 *     [query] => Array
			 *         (
			 *             [usercontribs] => Array
			 *                 (
			 *                     [0] => Array
			 *                         (
			 *                             [user] => Mbijon
			 *                             [pageid] => 23000
			 *                             [revid] => 112024
			 *                             [ns] => 0
			 *                             [title] => Function Reference/add help tab
			 *                             [timestamp] => 2011-12-13T23:49:38Z
			 *                             [minor] =>
			 *                             [comment] => Functions typo fix
			 *                         )
			 **/

			foreach ( $raw->query->usercontribs as $item ) {
				$ref_count       = 0;
				$clean_title = str_replace( 'Function Reference/', '', $item->title, $ref_count );
				$new_item    = array(
					'title'        => $clean_title,
					'description'  => (string) $item->comment,
					'revision'     => (int) $item->revid,
					'function_ref' => (bool) $ref_count,
				);

				array_push( $data['data']['contributions'], $new_item );
			}
		}

		update_user_meta( $this->user->ID, '_wptalents_codex', $data );

		return $data;
	}

	protected function get_codex_count() {
		$results_url = add_query_arg(
			array(
				'action'  => 'query',
				'list'    => 'users',
				'ususers' => $this->options['username'],
				'usprop'  => 'editcount',
				'format'  => 'json',
			),
			'https://codex.wordpress.org/api.php'
		);

		$results = wp_remote_retrieve_body( wp_safe_remote_get( $results_url ) );

		if ( is_wp_error( $results ) ) {
			return false;
		}

		$raw = json_decode( $results );

		if ( isset( $raw->query->users[0]->editcount ) ) {
			$count = (int) $raw->query->users[0]->editcount;
		} else {
			$count = 0;
		}

		return $count;
	}

}