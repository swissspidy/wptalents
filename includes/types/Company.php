<?php

namespace WPTalents\Types;
use WPTalents\Core\Helper;

class Company implements Type {

	protected $post_type = 'company';

	public function __construct() {

		add_filter( 'wptalents_filter_request', array( $this, 'filter_request' ) );

		add_filter( 'wptalents_archive_post_types', array( $this, 'filter_archive_post_types' ) );

		add_filter( 'post_type_link', array( $this, 'filter_post_type_link' ), 10, 3 );

		add_filter( 'post_type_archive_link', array( $this, 'filter_post_type_archive_link' ), 10, 2 );

	}

	public function filter_request( $query_vars ) {

		if ( Helper::post_exists( $query_vars['talent'], $this->post_type ) ) {
			// Single Person

			$query_vars['post_type'] = $this->post_type;
			$query_vars['name']      = $query_vars['talent'];

		}

		return $query_vars;

	}

	public function filter_archive_post_types( array $post_types ) {

		$post_types[] = $this->post_type;

		return $post_types;

	}

	/**
	 * Filters the permalinks for companies and people.
	 *
	 * Removes the rewrite slug from the permalink
	 * so the permalinks are on the top-level.
	 *
	 * Example: example.com/company/xy/ becomes example.com/xy/
	 *
	 * @param  string  $permalink The current permalink of the post.
	 * @param  WP_Post $post      The current post object
	 * @param  bool    $leavename Whether to keep the post name.
	 *
	 * @return string             The modified permalink.
	 */
	public function filter_post_type_link( $permalink, $post, $leavename ) {

		if ( $this->post_type === $post->post_type ) {
			$permalink = esc_url( home_url( user_trailingslashit( "%$post->post_type%" ) ) );
		}

		if ( ! $leavename ) {
			$permalink = str_replace( "%$post->post_type%", $post->post_name, $permalink );
		}

		return $permalink;

	}

	public function register_post_type() {

		$args = array(
			'labels'        => Helper::post_type_labels( 'Company', 'Companies' ),
			'public'        => true,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'query_var'     => true,
			'rewrite'       => false,
			'has_archive'   => true,
			'hierarchical'  => false,
			'menu_position' => 30,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ),
		);

		register_post_type( $this->post_type, $args );

	}

	public function register_taxonomy() {

		if ( taxonomy_exists( 'region', 'person' ) ) {

			register_taxonomy_for_object_type( 'region', 'person' );

		} else {

			$region_args = array(
				'labels'                => Helper::post_type_labels( 'Region', 'Regions' ),
				'hierarchical'          => true,
				'show_ui'               => true,
				'show_admin_column'     => true,
				'update_count_callback' => '_update_post_term_count',
				'public'                => true,
				'show_tagcloud'         => false,
				'rewrite'               => false
			);

			register_taxonomy( 'region', array( 'company', 'person' ), $region_args );

		}

		if ( taxonomy_exists( 'service' ) ) {

			register_taxonomy_for_object_type( 'service', 'person' );

		} else {

			$region_args = array(
				'labels'                => Helper::post_type_labels( 'Service' ),
				'hierarchical'          => true,
				'show_ui'               => true,
				'show_admin_column'     => true,
				'update_count_callback' => '_update_post_term_count',
				'public'                => true,
				'show_tagcloud'         => false,
				'rewrite'               => false,
			);

			register_taxonomy( 'service', 'person', $region_args );

		}

	}

	public function add_meta_boxes( array $meta_boxes ) {

		$talent_details = array(
			array(
				'id'   => 'wordpress-username',
				'name' => __( 'WordPress.org username', 'wptalents' ),
				'type' => 'text',
				'cols' => 4
			),
			array(
				'id'   => 'byline',
				'name' => __( 'Byline', 'wptalents' ),
				'type' => 'text',
				'cols' => 4
			),
			array(
				'id'   => 'wordpress-com-vip',
				'name' => __( 'WordPress.com VIP partner', 'wptalents' ),
				'type' => 'checkbox',
				'cols' => 4
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
						'cols' => 4
					),
					array(
						'id'   => 'github',
						'name' => __( 'Github Username', 'wptalents' ),
						'type' => 'text',
						'cols' => 4
					),
					array(
						'id'   => 'twitter',
						'name' => __( 'Twitter Username', 'wptalents' ),
						'type' => 'text',
						'cols' => 4
					),
					array(
						'id'   => 'facebook',
						'name' => __( 'Facebook (Vanity URL)', 'wptalents' ),
						'type' => 'text',
						'cols' => 4
					),
					array(
						'id'   => 'google-plus',
						'name' => __( 'Google+ (ID)', 'wptalents' ),
						'type' => 'text',
						'cols' => 4
					),
					array(
						'id'   => 'linkedin',
						'name' => __( 'LinkedIn URL', 'wptalents' ),
						'type' => 'text_url',
						'cols' => 4
					),
					array(
						'id'   => 'crunchbase',
						'name' => __( 'CrunchBase URL', 'wptalents' ),
						'type' => 'text_url',
						'cols' => 4
					),
				)
			)
		);

		$location = array(
			array(
				'id'   => 'location',
				'name' => __( 'Location', 'wptalents' ),
				'desc' => __( "Stores name, coordinates and elevation.", 'wptalents' ),
				'type' => 'gmap',
			),
		);

		$meta_boxes[] = array(
			'title'    => __( 'Location', 'wptalents' ),
			'pages'    => $this->post_type,
			'context'  => 'advanced',
			'priority' => 'high',
			'fields'   => $location
		);

		$meta_boxes[] = array(
			'title'    => __( 'Talent Details', 'wptalents' ),
			'pages'    => $this->post_type,
			'context'  => 'advanced',
			'priority' => 'high',
			'fields'   => array_merge( $talent_details, $social_profiles )
		);

		return $meta_boxes;

	}

	public function filter_body_class( array $classes ) {

		/** @var WP_Post $post */
		global $post;

		if ( $this->post_type === $post->post_type ) {
			// Add default classes
			$classes[] = 'talent';


			if ( has_post_thumbnail( $post->ID ) ) {

				$thumbnail = get_attached_file( get_post_thumbnail_id( $post->ID ) );

				// Add map thumbnail class
				if ( $thumbnail && 0 === strpos( basename( $thumbnail ), $post->post_name . '-map' ) ) {
					$classes[] = 'has-post-thumbnail-map';
				}
			}
		}

		return $classes;

	}

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

		if ( has_post_thumbnail( $post->ID ) ) {

			$thumbnail = get_attached_file( get_post_thumbnail_id( $post->ID ) );

			// Add map thumbnail class
			if ( $thumbnail && 0 === strpos( basename( $thumbnail ), $post->post_name . '-map' ) ) {
				$classes[] = 'has-post-thumbnail-map';
			}
		}

		return $classes;

	}

	/**
	 * Filter the post type archive permalink.
	 *
	 * @since 3.1.0
	 *
	 * @param string $link      The post type archive permalink.
	 * @param string $post_type Post type name.
	 *
	 * @return string
	 */
	public function filter_post_type_archive_link( $link, $post_type ) {

		if ( $this->post_type === $post_type ) {
			return home_url( user_trailingslashit( 'talents', 'post_type_archive' ) );
		}

		return $link;

	}

}