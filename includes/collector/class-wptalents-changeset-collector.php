<?php

class WP_Talents_Changeset_Collector {

	/** @var  int $expiration */
	protected $expiration = WEEK_IN_SECONDS;

	/** @var WP_Post $post */
	protected $post;

	/**
	 * Initialize the collector.
	 *
	 * @since 0.0.1
	 *
	 * @param WP_Post|int $post
	 * @param array       $args
	 */
	public function __construct( $post, $args = array() ) {

		if ( ! is_a( $this->post = get_post( $post ), 'WP_Post' ) ) {
			return false;
		}

		$may_renew = true;

		if (
			( defined( 'JSON_REQUEST' ) && JSON_REQUEST ) ||
	        ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ||
			! is_singular() ||
			isset( $_POST['action'] )
		)	{
			$may_renew = false;
		}

		$defaults = array(
			'username' => get_post_meta( $this->post->ID, 'wordpress-username', true ),
			'may_renew'  => $may_renew
		);

		$this->options = apply_filters( 'wptalents_data_collector_options', wp_parse_args( $args, $defaults ), $post );

		if ( empty( $this->options['username'] ) ) {
			return false;
		}

	}

	/**
	 * @access public
	 * @return mixed
	 */
	public function get_data() {

		$data = get_post_meta( $this->post->ID, '_changeset_count', true );

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

		$results_url = add_query_arg(
			array(
				'q'           => 'props+' . $this->options['username'],
				'noquickjump' => '1',
				'changeset'   => 'on'
			),
			'https://core.trac.wordpress.org/search'
		);

		$response    = wp_remote_get( $results_url );

		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			$results = wp_remote_retrieve_body( $response );
			$pattern = '/<meta name="totalResults" content="(\d*)" \/>/';

			preg_match( $pattern, $results, $matches );

			$count = intval( $matches[1] );

			$data = array(
				'data'       => $count,
				'expiration' => time() + $this->expiration,
			);

			update_post_meta( $this->post->ID, '_changeset_count', $data );

			return $data;
		}

		return false;

	}

}