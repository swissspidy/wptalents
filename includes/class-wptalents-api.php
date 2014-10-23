<?php
/**
 * @package WP Talents
 */

/**
 * Main WP Talents Class
 *
 * @package WP_Talents
 * @author  Pascal Birchler <pascal.birchler@spinpress.com>
 */
class WP_Talents_API extends WP_JSON_CustomPostType {

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
		parent::__construct( $server );
	}

	public function register_filters() {
		add_filter( 'json_endpoints',  array( $this, 'register_routes' ) );
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

	/**
	 * Retrieve a post
	 *
	 * @see WP_JSON_Posts::get_post()
	 */
	public function get_post( $id, $context = 'view' ) {
		$id = (int) $id;

		if ( empty( $id ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

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

	public function api_get_user_meta( $username, $key ) {
		if ( ! $contributor = get_page_by_path( $username, OBJECT, 'contributor' ) ) {
			$created = WP_Central_Contributor::create( $username );

			if ( ! $created ) {
				return new WP_Error( 'json_user_invalid_id', __( "User doesn't exist." ), array( 'status' => 400 ) );
			}
		}

		$user_fields = array(
			'data' => WP_Central_Data_Colector::get_wp_user_data( $contributor, $contributor->post_name, $key )
		);

		if ( ! $user_fields['data'] ) {
			return new WP_Error( 'json_user_invalid_id', __( 'This meta key is not an option' ), array( 'status' => 400 ) );
		}

		$user_fields['meta'] = array(
			'links' => array(
				'self'    => json_url( $this->base .'/' . $contributor->post_name ) . '/meta/' . $key,
				'profile' => json_url( $this->base .'/' . $contributor->post_name ),
			),
		);

		return $user_fields;
	}

	/**
	 *
	 * Prepare a User entity from a WP_User instance.
	 *
	 * @param WP_Post $talent
	 *
	 * @return array
	 */
	protected function api_prepare_talent( $talent ) {
		$user_fields = array(
			'username'    => $talent->post_name,
			'name'        => $talent->post_title,
			'avatar'      => $talent->avatar,
			'location'    => $talent->location,
			'company'     => $talent->company,
			'website'     => $talent->website,
			'socials'     => $talent->socials,
			'badges'      => $talent->badges,
		);

		$user_fields = wp_parse_args( WP_Central_Data_Colector::get_wp_user_data( $talent, $talent->post_name ), $user_fields );

		$user_fields['meta'] = array(
			'links' => array(
				'self' => json_url( $this->base . '/' . $talent->post_name ),
			),
		);

		return $user_fields;
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

		$post_type = get_post_type_object( $post['post_type'] );

		if ( ! $this->check_read_permission( $post ) ) {
			return new WP_Error( 'json_user_cannot_read', __( 'Sorry, you cannot read this post.' ), array( 'status' => 401 ) );
		}

		$post_obj = get_post( $post['ID'] );

		$GLOBALS['post'] = $post_obj;
		setup_postdata( $post_obj );

		$talent_meta = WP_Talents::get_talent_meta( $post_obj );

		// prepare common post fields
		$post_fields = array(
			'title'           => get_the_title( $post['ID'] ), // $post['post_title'],
			'type'            => $post['post_type'],
			'content'         => apply_filters( 'the_content', $post['post_content'] ),
			'link'            => get_permalink( $post['ID'] ),
		);

		$post_fields_extended = array(
			'slug'           => $post['post_name'],
			'excerpt'        => $this->prepare_excerpt( $post['post_excerpt'] ),
			'byline'         => esc_html( get_post_meta( $post['ID'], 'byline', true ) ),
			'location'       => esc_html( get_post_meta( $post['ID'], 'byline', true ) ),
			'website'        => esc_url( get_post_meta( $post['ID'], 'byline', true ) ),
			'profile'        => $talent_meta['profile'],
			'plugins'        => $talent_meta['plugins'],
			'themes'         => $talent_meta['themes'],
			//'comment_status' => $post['comment_status'],
			//'sticky'         => ( $post['post_type'] === 'post' && is_sticky( $post['ID'] ) ),
		);

		if ( 'person' === $post['post_type'] ) {
			$post_fields['job'] = (string) get_post_meta( $post['ID'], 'job', true );
		} else {
			$post_fields['wordpress_vip'] = ( 1 == get_post_meta( $post['ID'], 'wordpress_vip', true ) ) ? true : false;
		}

		// Dates
		if ( $post['post_date_gmt'] === '0000-00-00 00:00:00' ) {
			$post_fields['date'] = null;
			$post_fields_extended['date_gmt'] = null;
		}
		else {
			$post_fields['date']              = json_mysql_to_rfc3339( $post['post_date'] );
			$post_fields_extended['date_gmt'] = json_mysql_to_rfc3339( $post['post_date_gmt'] );
		}

		if ( $post['post_modified_gmt'] === '0000-00-00 00:00:00' ) {
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
			'self'       => json_url( '/posts/' . $post['ID'] ),
			'collection' => json_url( '/posts' ),
		);

		$_post['meta'] = array( 'links' => $links );

		return apply_filters( "json_prepare_talent", $_post, $post, $context );
	}

}