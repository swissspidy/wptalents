<?php

namespace WPTalents\Core;

use WPTalents\Collector\Gravatar_Collector;
use \WPTalents\Collector\Profile_Collector;
use \WP_Error;
use \WP_Query;

/**
 * Importer class.
 *
 * @package WPTalents\Core
 */
class Importer {

	/**
	 * @var string WordPress.org username
	 */
	protected $username;

	/**
	 * @var string Post Type.
	 */
	protected $type = 'person';

	/**
	 * @param string $username WordPress.org username.
	 * @param string $name     The talent's real name.
	 * @param string $type     Post type, either person or company.
	 */
	public function __construct( $username, $name = '', $type = 'person' ) {
		$this->name     = (string) $username;
		$this->username = (string) $username;
		$this->type     = (string) $type;

		if ( ! empty( $name ) ) {
			$this->name = $name;
		}
	}

	/**
	 * Imports a talent into the site based on their WordPress.org username.
	 * @return int|WP_Error Post ID on success, WP_Error object otherwise.
	 */
	public function import() {
		$query = new WP_Query( array(
			'post_type'      => $this->type,
			'post_status'    => 'any',
			'meta_key'       => 'wordpress-username',
			'meta_value'     => $this->username,
			'posts_per_page' => 1,
		) );

		if ( $query->have_posts() ) {
			return new WP_Error(
				'already_exists',
				sprintf( __( 'Talent already exists! (ID: %d)', 'wptalents' ), $query->posts[0]->ID )
			);
		}

		$post_id = wp_insert_post( array(
				'post_title'   => $this->name,
				'post_type'    => 'person',
				'post_excerpt' => '',
			)
		);

		if ( 0 === $post_id || is_wp_error( $post_id ) ) {
			return new WP_Error( 'insert_failed', sprintf( __( 'Importing %s failed!', 'wptalents' ), $this->name ) );
		}

		update_post_meta( $post_id, 'wordpress-username', $this->username );

		$collector = new Profile_Collector( get_post( $post_id ) );
		$collector->_retrieve_data();

		new Gravatar_Collector( get_post( $post_id ) );

		return $post_id;
	}

} 