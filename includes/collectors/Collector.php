<?php

namespace WPTalents\Collector;

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
	 * @param \WP_Post $post
	 */
	public function __construct( \WP_Post $post ) {

		$this->post = $post;

		$may_renew = true;

		if (
			( defined( 'JSON_REQUEST' ) && JSON_REQUEST ) ||
			( defined( 'DOING_AJAX' ) && DOING_AJAX ) ||
			! is_singular() ||
			isset( $_POST['action'] )
		)	{
			$may_renew = false;
		}

		$this->options = array(
			'username' => get_post_meta( $post->ID, 'wordpress-username', true ),
			'may_renew'  => $may_renew
		);

		$this->options = apply_filters( 'wptalents_data_collector_options', $this->options, $post );

	}

	public abstract function get_data();

	protected abstract function _retrieve_data();

}