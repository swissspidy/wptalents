<?php

namespace WPTalents\Types;
use WPTalents\Core\Helper;

class Activity implements Type {

	protected $post_type = 'activity';

	public function __construct() {}

	public function register_post_type() {

		$args = array(
			'labels'        => Helper::post_type_labels( 'Activity', 'Activities' ),
			'public'        => true,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'query_var'     => true,
			'rewrite'       => array(
				'slug' => 'activity'
			),
			'has_archive'   => true,
			'hierarchical'  => false,
			'menu_position' => 35,
			'supports'      => array( 'editor', 'custom-fields' ),
		);

		register_post_type( $this->post_type, $args );

	}

	public function register_taxonomy() {

		$args = array(
			'show_ui'               => true,
			'show_admin_column'     => true,
			'update_count_callback' => '_update_post_term_count',
			'public'                => true,
			'show_tagcloud'         => false,
			'rewrite'               => false,
		);

		register_taxonomy( 'activity-type', $this->post_type, $args );

	}

	public function add_meta_boxes( array $meta_boxes ) {

		return $meta_boxes;

	}

	public function filter_body_class( array $classes ) {

		return $classes;

	}

	public function filter_post_class( array $classes ) {

		return $classes;

	}

}