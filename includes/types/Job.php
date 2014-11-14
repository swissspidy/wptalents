<?php

namespace WPTalents\Types;
use WPTalents\Core\Helper;

/**
 * Class Job
 * @package WPTalents\Types
 */
class Job implements Type {

	protected $post_type = 'job';

	/**
	 *
	 */
	public function __construct() {

		add_filter( 'wptalents_filter_request', array( $this, 'filter_request' ) );

	}

	/**
	 * Filters the request variables.
	 *
	 * @param array $query_vars
	 *
	 * @return array
	 */
	public function filter_request( $query_vars ) {

		if ( Helper::post_exists( $query_vars['talent'], $this->post_type ) ) {
			// Single Job

			$query_vars['post_type'] = $this->post_type;
			$query_vars['name']      = $query_vars['talent'];

		}

		return $query_vars;

	}

	public function register_post_type() {

		$args = array(
			'labels'        => Helper::post_type_labels( 'Job' ),
			'public'        => true,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'query_var'     => true,
			'rewrite'       => array(
				'slug' => $this->post_type,
			),
			'has_archive'   => true,
			'hierarchical'  => false,
			'menu_position' => 30,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ),
		);

		register_post_type( $this->post_type, $args );

	}

	public function register_taxonomy() {}

	/**
	 * Add CMB meta boxes.
	 *
	 * @param array $meta_boxes
	 *
	 * @return array|mixed
	 */
	public function add_meta_boxes( array $meta_boxes ) {

		return $meta_boxes;

	}

	/**
	 * Filters the body_class.
	 *
	 * @param array $classes
	 *
	 * @return array
	 */
	public function filter_body_class( array $classes ) {

		return $classes;

	}

	/**
	 * Filters the post_class.
	 *
	 * @param array $classes
	 *
	 * @return array
	 */
	public function filter_post_class( array $classes ) {

		/** @var WP_Post $post */
		global $post;

		if ( $this->post_type !== $post->post_type ) {
			return $classes;
		}

		// Add default classes
		$classes[] = 'talent';

		if ( $post !== get_queried_object() ) {
			$classes[] = 'talent--small';
		}

		return $classes;

	}

}