<?php
/**
 * @package WP Talents
 */

namespace WPTalents\API;

use WPTalents\Core\Helper;
use \WP_Post;
use \WP_Error;
use \WP_JSON_Server;
use \WP_JSON_Response;
use \WP_JSON_CustomPostType;
use \WP_JSON_ResponseHandler;

/**
 * @package WPTalents\API
 */
class Oembed_Provider {

	/**
	 * Server object
	 *
	 * @var WP_JSON_ResponseHandler
	 */
	protected $server;

	/**
	 * Base route name.
	 *
	 * @var string Route base (e.g. /my-plugin/my-type)
	 */
	protected $base = '/oembed';

	/**
	 * Associated post types.
	 *
	 * @var array Type slug
	 */
	protected $type = array( 'company', 'person', 'product' );

	/**
	 * Construct the API handler object.
	 *
	 * @param WP_JSON_Server $server
	 */
	public function __construct( WP_JSON_Server $server ) {

		$this->server = $server;

		add_filter( 'json_endpoints', array( $this, 'register_routes' ) );

	}

	/**
	 * Register the routes for the talents
	 *
	 * @param array $routes Routes for the post type
	 *
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {

		$routes[ $this->base ] = array(
			array(
				array( $this, 'get_oembed_response' ),
				WP_JSON_Server::READABLE,
			),
		);

		return $routes;

	}

	/**
	 * @param string $url
	 * @param string $format
	 * @param int    $maxwidth
	 * @param int    $maxheight
	 * @param string $callback
	 *
	 * @return array|WP_JSON_Response|WP_Error
	 */
	public function get_oembed_response( $url, $format = 'json', $maxwidth = 640, $maxheight = 420, $callback = '' ) {

		if ( 'json' !== $format ) {
			return new WP_Error( 'json_oembed_invalid_format', __( 'Format not supported.' ), array( 'status' => 501 ) );
		}

		$id = Helper::url_to_postid( $url );

		if ( 0 === $id || ! in_array( get_post_type( $id ), $this->type ) ) {
			return new WP_Error( 'json_oembed_invalid_url', __( 'Invalid URL.' ), array( 'status' => 404 ) );
		}

		if ( 320 > $maxwidth || 180 > $maxheight ) {
			return new WP_Error( 'json_oembed_invalid_dimensions', __( 'Not implemented.' ), array( 'status' => 501 ) );
		}

		/** @var array $post */
		$post = get_post( $id, ARRAY_A );

		// Link headers (see RFC 5988)

		$response = new WP_JSON_Response();
		$response->header( 'Last-Modified', mysql2date( 'D, d M Y H:i:s', $post['post_modified_gmt'] ) . 'GMT' );

		$post = $this->prepare_response( $post, $maxwidth, $maxheight );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$response->link_header( 'alternate', get_permalink( $id ), array( 'type' => 'text/html' ) );
		$response->set_data( $post );

		if ( '' !== $callback ) {
			$_GET['_jsonp'] = $callback;
		}

		return $response;

	}

	/**
	 * Prepare oEmbed response.
	 *
	 * @param array $post The unprepared post data
	 * @param int   $maxwidth
	 * @param int   $maxheight
	 *
	 * @return array The prepared post data
	 */
	protected function prepare_response( $post, $maxwidth, $maxheight ) {

		$post_obj = get_post( $post['ID'] );

		$GLOBALS['post'] = $post_obj;
		setup_postdata( $post_obj );

		// Prepare common post fields
		$response_fields = array(
			'version'          => '1.0',
			'provider_name'    => get_bloginfo( 'name' ),
			'provider_url'     => get_home_url(),
			'title'            => $post_obj->post_title,
			'type'             => 'rich',
			'cache_age'        => WEEK_IN_SECONDS,
			'html'             => $this->get_oembed_html( $post_obj, $maxwidth, $maxheight ),
			'thumbnail_url'    => Helper::get_avatar_url( $post_obj, 512 ),
			'thumbnail_width'  => 512,
			'thumbnail_height' => 512,
		);

		return apply_filters( 'json_prepare_oembed', $response_fields, $post, $maxwidth, $maxheight );
	}

	/**
	 * @param WP_Post $post
	 * @param int     $maxwidth
	 * @param int     $maxheight
	 *
	 * @return string
	 */
	protected function get_oembed_html( $post, $maxwidth, $maxheight ) {

		$output = '';

		// Default dimensions
		$width  = 640;
		$height = 420;

		if ( $maxwidth !== $width ) {
			$height = $height / $width * $maxwidth;
			$width  = $maxwidth;
		} else if ( $maxheight !== $height ) {
			$width  = $width / $height * $maxheight;
			$height = $maxheight;
		}

		$output = sprintf(
			'<iframe src="%1$s" width="%2$d" height="%3$d" frameborder="0" marginwidth="0" marginheight="0" scrolling="no"></iframe>',
			trailingslashit( get_permalink( $post ) ) . user_trailingslashit( 'embed' ),
			$width,
			$height
		);

		return apply_filters( 'json_oembed_html', $output, $post, $maxwidth, $maxheight );

	}

}