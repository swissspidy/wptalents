<?php
/**
 * @package WP Talents
 */

class WP_Talents_Products_API extends WP_JSON_CustomPostType {

	/**
	 * Base route name.
	 *
	 * @var string Route base (e.g. /my-plugin/my-type)
	 */
	protected $base = '/products';

	/**
	 * Associated post types.
	 *
	 * @var array Type slug
	 */
	protected $type = 'product';

	/**
	 * Construct the API handler object.
	 *
	 * @param WP_JSON_ResponseHandler $server
	 */
	public function __construct( WP_JSON_ResponseHandler $server ) {

		add_filter( 'json_endpoints',  array( $this, 'register_routes' ) );

		parent::__construct( $server );

	}

	/**
	 * Register the routes for the talents
	 *
	 * @param array $routes Routes for the post type
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$routes[$this->base] = array(
			array( array( $this, 'get_posts'), WP_JSON_Server::READABLE ),
		);

		$routes[$this->base . '/(?P<id>\d+)'] = array(
			array( array( $this, 'get_post'), WP_JSON_Server::READABLE ),
		);

		return $routes;
	}

	public function get_posts( $filter = array(), $context = 'view', $type = null, $page = 1 ) {
		if ( ! empty( $type ) && $type !== $this->type ) {
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );
		}

		return parent::get_posts( $filter, $context, $this->type, $page );
	}

}