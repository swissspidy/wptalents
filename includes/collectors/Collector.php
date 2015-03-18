<?php

namespace WPTalents\Collector;

use WP_Post;

/**
 * Class Collector
 * @package WPTalents\Collector
 */
abstract class Collector {

	/** @var  int $expiration */
	protected $expiration = WEEK_IN_SECONDS;

	/** @var WP_Post $post */
	protected $post;

	/** @var array */
	protected $options;

	/**
	 * Initialize the collector.
	 *
	 * @param \WP_User $user
	 */
	public function __construct( \WP_User $user ) {

		$this->user = $user;

		$may_renew = true;

		if (
			( defined( 'JSON_REQUEST' ) && JSON_REQUEST ) ||
			( defined( 'DOING_AJAX' ) && DOING_AJAX ) ||
			! is_singular() ||
			isset( $_POST['action'] )
		) {
			$may_renew = false;
		}

		$this->options = array(
			'username'  => $this->user->user_login,
			'may_renew' => $may_renew,
		);

		$this->options = apply_filters( 'wptalents_data_collector_options', $this->options, $this->user );

	}

	public abstract function get_data();

	public abstract function _retrieve_data();

}