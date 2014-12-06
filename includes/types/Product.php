<?php

namespace WPTalents\Types;
use WPTalents\Core\Helper;

/**
 * Class Product
 * @package WPTalents\Types
 */
class Product implements Type {

	protected $post_type = 'product';

	/**
	 *
	 */
	public function __construct() {

		add_filter( 'wptalents_filter_request', array( $this, 'filter_request' ) );

	}

	public function register_post_type() {

		$args = array(
			'labels'        => Helper::post_type_labels( 'Product' ),
			'public'        => true,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'query_var'     => true,
			'rewrite'       => array(
				'slug'       => $this->post_type,
			),
			'has_archive'   => true,
			'hierarchical'  => false,
			'menu_position' => 30,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ),
		);

		register_post_type( $this->post_type, $args );

	}

	public function register_taxonomy() {

		$args = array(
			'labels'                => Helper::post_type_labels( 'Category', 'Categories' ),
			'hierarchical'          => true,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'update_count_callback' => '_update_post_term_count',
			'public'                => true,
			'show_tagcloud'         => false,
			'rewrite'               => false,
		);

		register_taxonomy( 'product-category', $this->post_type, $args );

	}

	/**
	 * Add CMB meta boxes.
	 *
	 * @param array $meta_boxes
	 *
	 * @return array|mixed
	 */
	public function add_meta_boxes( array $meta_boxes ) {

		$product_details = array(
			array(
				'id'   => 'byline',
				'name' => __( 'Byline', 'wptalents' ),
				'type' => 'text',
				'cols' => 4,
			),
		);

		$social_profiles = array(
			array(
				'id'     => 'social',
				'name'   => __( 'Social', 'wptalents' ),
				'type'   => 'group',
				'fields' => array(
					array(
						'id'   => 'url',
						'name' => __( 'Website URL', 'wptalents' ),
						'type' => 'text_url',
						'cols' => 4,
					),
					array(
						'id'   => 'crunchbase',
						'name' => __( 'CrunchBase URL', 'wptalents' ),
						'type' => 'text_url',
						'cols' => 4,
					),
				),
			),
		);

		$meta_boxes[] = array(
			'title'    => __( 'Product Details', 'wptalents' ),
			'pages'    => $this->post_type,
			'context'  => 'advanced',
			'priority' => 'high',
			'fields'   => array_merge( $product_details, $social_profiles ),
		);

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

		/** @var \WP_Post $post */
		global $post;

		if ( $this->post_type !== $post->post_type ) {
			return $classes;
		}

		// Add default classes
		$classes[] = 'talent';

		if ( $post !== get_queried_object() || wptalents_is_oembed() ) {
			$classes[] = 'talent--small';
		}

		return $classes;

	}

}