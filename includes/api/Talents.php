<?php
/**
 * @package WP Talents
 */

namespace WPTalents\API;
use WPTalents\Core\Helper;
use \WP_Error;
use \WP_JSON_Server;
use \WP_JSON_Response;
use \WP_JSON_CustomPostType;
use \WP_JSON_ResponseHandler;

/**
 * Class Talents
 * @package WPTalents\API
 */
class Talents extends WP_JSON_CustomPostType {

	/**
	 * Base route name.
	 *
	 * @var string Route base (e.g. /my-plugin/my-type)
	 */
	protected $base = '/talents';

	/**
	 * Associated post types.
	 *
	 * @var array Type slug
	 */
	protected $type = array( 'company', 'person' );

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

		$routes[ $this->base ] = array(
			array( array( $this, 'get_posts' ), WP_JSON_Server::READABLE ),
		);

		$routes[ $this->base . '/(?P<id>\d+)' ] = array(
			array( array( $this, 'get_post' ), WP_JSON_Server::READABLE ),
		);

		return $routes;

	}

	/**
	 * @param array  $filter
	 * @param string $context
	 * @param null   $type
	 * @param int    $page
	 *
	 * @return \WP_Error|array
	 */
	public function get_posts( $filter = array(), $context = 'view', $type = null, $page = 1 ) {
		if ( ! empty( $type ) && $type !== $this->type ) {
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );
		}

		return parent::get_posts( $filter, $context, $this->type, $page );
	}

	/**
	 * Retrieve a post
	 *
	 * @see WP_JSON_Posts::get_post()
	 *
	 * @param        $id
	 * @param string $context
	 *
	 * @return \WP_JSON_Response
	 */
	public function get_post( $id, $context = 'view' ) {
		$id = (int) $id;

		if ( empty( $id ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		/** @var array $post */
		$post = get_post( $id, ARRAY_A );

		if ( ! in_array( $post['post_type'], $this->type ) ) {
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );
		}

		if ( ! $this->check_read_permission( $post ) ) {
			return new WP_Error( 'json_user_cannot_read', __( 'Sorry, you cannot read this post.' ), array( 'status' => 401 ) );
		}

		// Link headers (see RFC 5988)

		$response = new WP_JSON_Response();
		$response->header( 'Last-Modified', mysql2date( 'D, d M Y H:i:s', $post['post_modified_gmt'] ) . 'GMT' );

		$post = $this->prepare_post( $post, $context );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		foreach ( $post['meta']['links'] as $rel => $url ) {
			$response->link_header( $rel, $url );
		}

		$response->link_header( 'alternate',  get_permalink( $id ), array( 'type' => 'text/html' ) );
		$response->set_data( $post );

		return $response;
	}

	/**
	 * Prepare post data
	 *
	 * @param array $post The unprepared post data
	 * @param string $context The context for the prepared post. (view|view-revision|edit|embed|single-parent)
	 * @return array The prepared post data
	 */
	protected function prepare_post( $post, $context = 'view' ) {
		// Holds the data for this post.
		$_post = array( 'ID' => (int) $post['ID'] );

		if ( ! $this->check_read_permission( $post ) ) {
			return new WP_Error( 'json_user_cannot_read', __( 'Sorry, you cannot read this post.' ), array( 'status' => 401 ) );
		}

		$post_obj = get_post( $post['ID'] );

		$GLOBALS['post'] = $post_obj;
		setup_postdata( $post_obj );

		// Fetch our talent meta
		$talent_meta = Helper::get_talent_meta( $post_obj );

		// Prepare common post fields
		$post_fields = array(
			'title' => get_the_title( $post['ID'] ), // $post['post_title'],
			'type'  => $post['post_type'],
			'link'  => get_permalink( $post['ID'] ),
		);

		$post_fields_extended = array(
			'slug'          => $post['post_name'],
			'excerpt'       => $this->prepare_excerpt( $post['post_excerpt'] ),
			'content'       => apply_filters( 'the_content', $post['post_content'] ),
			'byline'        => esc_html( get_post_meta( $post['ID'], 'byline', true ) ),
			'job'           => (string) get_post_meta( $post['ID'], 'job', true ),
			'wordpress_vip' => ( 1 == get_post_meta( $post['ID'], 'wordpress_vip', true ) ) ? true : false,
			'location'      => $talent_meta['map'],
			'score'         => $talent_meta['score'],
			'social'        => $talent_meta['social'],
			'badges'        => $talent_meta['profile']['badges'],
			'contributions' => array(
				'codex' => array( 'count' => $talent_meta['codex_count'] ),
				'props' => array( 'count' => $talent_meta['changeset_count'] ),
				'core'  => $talent_meta['contributions'],
			),
			'plugins'       => $talent_meta['plugins'],
			'themes'        => $talent_meta['themes'],
			//'sticky' => ( $post['post_type'] === 'post' && is_sticky( $post['ID'] ) ),
		);

		// Company itself has no job, and a person can't be a WordPress.com VIP
		if ( 'person' === $post['post_type'] ) {
			unset( $post_fields_extended['wordpress_vip'] );
		} else {
			unset( $post_fields_extended['job'] );
		}

		// Dates
		if ( '0000-00-00 00:00:00' === $post['post_date_gmt']  ) {
			$post_fields['date'] = null;
			$post_fields_extended['date_gmt'] = null;
		}
		else {
			$post_fields['date']              = json_mysql_to_rfc3339( $post['post_date'] );
			$post_fields_extended['date_gmt'] = json_mysql_to_rfc3339( $post['post_date_gmt'] );
		}

		if ( '0000-00-00 00:00:00' === $post['post_modified_gmt']  ) {
			$post_fields['modified'] = null;
			$post_fields_extended['modified_gmt'] = null;
		}
		else {
			$post_fields['modified']              = json_mysql_to_rfc3339( $post['post_modified'] );
			$post_fields_extended['modified_gmt'] = json_mysql_to_rfc3339( $post['post_modified_gmt'] );
		}

		// Merge requested $post_fields fields into $_post
		$_post = array_merge( $_post, $post_fields );

		// Include extended fields. We might come back to this.
		$_post = array_merge( $_post, $post_fields_extended );

		// Entity meta
		$links = array(
			'self'       => json_url( '/talents/' . $post['ID'] ),
			'collection' => json_url( '/talents' ),
		);

		$_post['meta'] = array( 'links' => $links );

		return apply_filters( 'json_prepare_talent', $_post, $post, $context );
	}

}