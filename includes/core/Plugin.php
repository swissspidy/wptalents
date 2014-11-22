<?php

namespace WPTalents\Core;

use WPTalents\Types\Activity;
use WPTalents\Types\Company;
use WPTalents\Types\Job;
use WPTalents\Types\Person;
use WPTalents\Types\Product;
use WPTalents\API\Talents;
use WPTalents\API\Products;
use \WP_JSON_ResponseHandler;

/**
 * Class Plugin
 * @package WPTalents\Core
 */
class Plugin {

	/* @var $types \WPTalents\Types\Type[] */
	protected $types = array();

	/* @var $router Router */
	protected $router;

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '1.0.0';

	/**
	 * Initialize the constructor.
	 */
	public function __construct() {

		// Setup the router class
		$this->router = new Router();

		add_action( 'init', array( $this, 'load_plugin_textdomain' ), 4 );

		add_action( 'init', array( $this, 'add_types' ), 8 );

		add_action( 'init', array( $this, 'add_rewrite_rules' ) );

		add_action( 'init', array( $this, 'register_post_types' ) );

		add_action( 'init', array( $this, 'register_taxonomies' ) );

		add_action( 'p2p_init', array( $this, 'register_connections' ) );

		add_action( 'init', array( $this, 'filter_body_class' ) );

		add_action( 'init', array( $this, 'filter_post_class' ) );

		add_action( 'init', array( $this, 'add_meta_boxes' ) );

		add_action( 'save_post', array( $this, 'add_map_on_save_post' ) );

		add_filter( 'cmb_field_types', array( $this, 'add_cmb_field_types' ) );

		// FacetWP

		add_filter( 'facetwp_sort_options', array( __CLASS__, 'facetwp_sort_options' ) );

		add_filter( 'facetwp_pager_html', array( __CLASS__, 'facetwp_pager_html' ), 10, 2 );

		// JSON API

		add_filter( 'json_url_prefix', array( __CLASS__, 'api_url_prefix' ) );

		add_action( 'wp_json_server_before_serve', array( __CLASS__, 'api_init' ) );

	}

	/**
	 * Add rewrite rules for the plugin.
	 */
	public function add_rewrite_rules() {

		add_rewrite_rule( '([^/]+)?/?$', 'index.php?talent=$matches[1]', 'top' );

	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {

		$locale = apply_filters( 'plugin_locale', get_locale(), 'wptalents' );

		load_textdomain(
			'wptalents',
			trailingslashit( WP_LANG_DIR ) . 'wptalents/wptalents' . $locale . '.mo'
		);

		load_plugin_textdomain(
			'wptalents',
			false,
			basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/languages/'
		);

	}

	/**
	 * Fired when the plugin is activated.
	 */
	public function activate() {

		// Register post types
		$this->register_post_types();

		// Register taxonomies
		$this->register_taxonomies();

		// Change JSON API URL prefix
		add_filter( 'json_url_prefix', array( __CLASS__, 'api_url_prefix' ) );

		// Update the rewrite rules
		flush_rewrite_rules();

	}

	/**
	 * Fired when the plugin is deactivated.
	 */
	public function deactivate() {

		flush_rewrite_rules();

	}

	/**
	 * Initialize all the content types.
	 */
	public function add_types() {

		$this->types = apply_filters( 'wptalents_types', array(
			'person'   => new Person(),
			'company'  => new Company(),
			'activity' => new Activity(),
			'product'  => new Product(),
			'job'      => new Job(),
		) );

	}

	/**
	 * Register all post types.
	 */
	public function register_post_types() {

		/** @var $type \WPTalents\Types\Type */
		foreach ( $this->types as $type ) {
			$type->register_post_type();
		}

	}

	/**
	 * Register all taxonomies.
	 */
	public function register_taxonomies() {

		/** @var $type \WPTalents\Types\Type */
		foreach ( $this->types as $type ) {
			$type->register_taxonomy();
		}

	}

	/**
	 * Registers our connections using Posts 2 Posts.
	 *
	 * @uses  p2p_register_connection_type()
	 */
	public static function register_connections() {

		p2p_register_connection_type( array(
			'name'            => 'talent_activity',
			'from'            => 'activity',
			'to'              => array( 'company', 'person', 'product' ),
			'cardinality'     => 'many-to-many',
			'title'           => __( 'Connected', 'wptalents' ),
			'admin_box'       => array(
				'show'    => 'from',
				'context' => 'side',
			),
			'can_create_post' => false,
			'to_query_vars'   => array( 'post_status' => 'any' ),
			'from_query_vars' => array( 'post_status' => 'any' ),
		) );

		p2p_register_connection_type( array(
			'name'            => 'product_owner',
			'from'            => 'product',
			'to'              => array( 'person', 'company' ),
			'cardinality'     => 'many-to-many',
			'title'           => array(
				'from' => __( 'Owner', 'wptalents' ),
				'to'   => __( 'Products', 'wptalents' )
			),
			'admin_box'       => array(
				'show'    => 'any',
				'context' => 'side',
			),
			'can_create_post' => false,
			'to_query_vars'   => array( 'post_status' => 'any' ),
			'from_query_vars' => array( 'post_status' => 'any' ),
		) );

		p2p_register_connection_type( array(
			'name'            => 'team',
			'from'            => 'company',
			'to'              => 'person',
			'cardinality'     => 'many-to-many',
			'title'           => __( 'Employees', 'wptalents' ),
			'admin_box'       => array(
				'show'    => 'any',
				'context' => 'advanced',
			),
			'fields'          => array(
				'role' => array(
					'title'   => __( 'Role', 'wptalents' ),
					'values'  => array(
						'ceo'      => __( 'CEO', 'wptalents' ),
						'cto'      => __( 'CTO', 'wptalents' ),
						'founder'  => __( 'Founder', 'wptalents' ),
						'employee' => __( 'Employee', 'wptalents' )
					),
					'default' => 'employee',
				),
				'from' => array(
					'title'   => __( 'From', 'wptalents' ),
					'type'    => 'date',
					'default' => '',
				),
				'to'   => array(
					'title'   => __( 'To', 'wptalents' ),
					'type'    => 'date',
					'default' => '',
				),
			),
			'can_create_post' => false,
			'to_query_vars'   => array( 'post_status' => 'any' ),
			'from_query_vars' => array( 'post_status' => 'any' ),
		) );

		p2p_register_connection_type( array(
			'name'            => 'hiring',
			'from'            => 'company',
			'to'              => 'job',
			'cardinality'     => 'one-to-many',
			'title'           => array(
				'from' => __( 'Open Jobs', 'wptalents' ),
				'to'   => __( 'Company', 'wptalents' ),
			),
			'admin_box'       => array(
				'show'    => 'from',
				'context' => 'side',
			),
			'can_create_post' => false,
			'to_query_vars'   => array( 'post_status' => 'any' ),
			'from_query_vars' => array( 'post_status' => 'any' ),
		) );

	}

	public function add_meta_boxes() {

		/** @var $type WP_Talents_Type */
		foreach ( $this->types as $type ) {
			add_filter( 'cmb_meta_boxes', array( $type, 'add_meta_boxes' ) );
		}

	}

	/**
	 * Add our custom CMB field types.
	 *
	 * @param array $cmb_field_types
	 *
	 * @return array
	 */
	public function add_cmb_field_types( array $cmb_field_types ) {

		$cmb_field_types['gmap'] = 'WPTalents\CMB\Gmap_Field';

		return $cmb_field_types;

	}

	/**
	 * Activate each type's body_class filter.
	 */
	public function filter_body_class() {

		/** @var $type WP_Talents_Type */
		foreach ( $this->types as $type ) {
			add_filter( 'body_class', array( $type, 'filter_body_class' ) );
		}

	}

	/**
	 * Activate each type's post_class filter
	 */
	public function filter_post_class() {

		/** @var $type WP_Talents_Type */
		foreach ( $this->types as $type ) {
			add_filter( 'post_class', array( $type, 'filter_post_class' ) );
		}

	}

	/**
	 * Fetches the map of the talent's location from Google Maps
	 * and sets it as the post thumbnail.
	 *
	 * It also replaces all intermediate image sizes with
	 * the file from Google Maps, as they are from better quality
	 * and way smaller than the generated ones.
	 *
	 * @param int $post_id The ID of the current post.
	 */
	public function add_map_on_save_post( $post_id ) {

		// If this is just a revision or the post already has a thumbnail, don't proceed
		if ( wp_is_post_revision( $post_id ) || has_post_thumbnail( $post_id ) ) {
			return;
		}

		if ( ! in_array( get_post_type( $post_id ), array_keys( $this->types ) ) ) {
			return;
		}

		// Get the talent's location data
		$location = Helper::get_talent_meta( get_post( $post_id ), 'location' );

		if ( empty( $location['name'] ) ) {
			return;
		}

		$map_retina = sprintf(
			'https://maps.googleapis.com/maps/api/staticmap?center=%s&scale=2&zoom=6&size=600x320&maptype=roadmap',
			urlencode( $location['name'] )
		);

		$tmp_retina = download_url( $map_retina );

		// Set variables for storage
		$file_array = array(
			'name'     => get_post( $post_id )->post_name . '-map.png',
			'tmp_name' => $tmp_retina,
		);

		// If error storing temporarily, unlink
		if ( is_wp_error( $tmp_retina ) ) {
			return;
		}

		// do the validation and storage stuff
		$attachment_id = media_handle_sideload( $file_array, $post_id, $location['name'] );

		// If error storing permanently, unlink
		if ( is_wp_error( $attachment_id ) ) {
			unlink( $file_array['tmp_name'] );

			return;
		}

		// Set map as post thumbnail
		set_post_thumbnail( $post_id, $attachment_id );

		// Add Normal image as image size of the attachment

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$attachment_path = get_attached_file( $attachment_id );
		$attachment_file = basename( $attachment_path );

		foreach ( $this->get_image_sizes() as $size => $values ) {

			$map = sprintf(
				'https://maps.googleapis.com/maps/api/staticmap?center=%s&scale=1&zoom=6&size=%s&maptype=roadmap',
				urlencode( $location['name'] ),
				$values['width'] . 'x' . $values['height']
			);

			$tmp = download_url( $map );

			// Set variables for storage
			$file_array = array(
				'name'     => $metadata['sizes'][ $size ]['file'],
				'tmp_name' => $tmp,
			);

			// If error storing temporarily, unlink
			if ( is_wp_error( $tmp ) ) {
				unlink( $file_array['tmp_name'] );

				continue;
			}

			unlink( str_replace( $attachment_file, $metadata['sizes'][ $size ]['file'], $attachment_path ) );

			$post = get_post( $post_id );
			$time = $post->post_date;

			$file = wp_handle_sideload( $file_array, array( 'test_form' => false ), $time );

			if ( isset( $file['error'] ) ) {
				unlink( $file_array['tmp_name'] );
			}
		}

	}

	public function get_image_sizes( $size = '' ) {

		global $_wp_additional_image_sizes;

		$sizes                        = array();
		$get_intermediate_image_sizes = get_intermediate_image_sizes();

		// Create the full array with sizes and crop info
		foreach ( $get_intermediate_image_sizes as $_size ) {

			if ( in_array( $_size, array( 'thumbnail', 'medium', 'large' ) ) ) {

				$sizes[ $_size ]['width']  = get_option( $_size . '_size_w' );
				$sizes[ $_size ]['height'] = get_option( $_size . '_size_h' );
				$sizes[ $_size ]['crop']   = (bool) get_option( $_size . '_crop' );

			} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {

				$sizes[ $_size ] = array(
					'width'  => $_wp_additional_image_sizes[ $_size ]['width'],
					'height' => $_wp_additional_image_sizes[ $_size ]['height'],
					'crop'   => $_wp_additional_image_sizes[ $_size ]['crop']
				);

			}

		}

		// Get only 1 size if found
		if ( $size ) {

			if ( isset( $sizes[ $size ] ) ) {
				return $sizes[ $size ];
			} else {
				return false;
			}

		}

		return $sizes;
	}

	/**
	 * Filter the FacetWP sort options.
	 *
	 * @param  array $options
	 *
	 * @return array
	 */
	public static function facetwp_sort_options( $options ) {

		$options['score_desc'] = array(
			'label'      => __( 'Score (Highest)', 'wptalents' ),
			'query_args' => array(
				'orderby'  => 'meta_value_num',
				'order'    => 'DESC',
				'meta_key' => '_score',
			)
		);

		$options['score_asc'] = array(
			'label'      => __( 'Score (Lowest)', 'wptalents' ),
			'query_args' => array(
				'orderby'  => 'meta_value_num',
				'order'    => 'ASC',
				'meta_key' => '_score',
			)
		);

		unset( $options['date_desc'] );
		unset( $options['date_asc'] );

		return $options;

	}

	/**
	 * Filter the FacetWP pagination output.
	 *
	 * @param  string $output
	 * @param  array  $params
	 *
	 * @return string
	 */
	public static function facetwp_pager_html( $output, $params ) {

		unset( $output );
		$output = '';

		$page       = (int) $params['page'];
		$per_page   = (int) $params['per_page'];
		$total_rows = (int) $params['total_rows'];

		// Prevent division by zero
		if ( $per_page < 1 ) {
			$total_pages = 0;
		} else {
			$total_pages = ceil( $total_rows / $per_page );
		}

		// Only show pagination when > 1 page
		if ( 1 >= $total_pages ) {
			return $output;
		}

		if ( 3 < $page ) {
			$output .= '<a class="facetwp-page first-page" data-page="1">&laquo;</a>';
		}

		if ( 1 < $page ) {
			$output .= '<a class="facetwp-page previous-page" data-page="' . ( $page - 1 ) . '">&lsaquo;</a>';
		}

		if ( 1 < ( $page - 10 ) ) {
			$output .= '<a class="facetwp-page" data-page="' . ( $page - 10 ) . '">' . ( $page - 10 ) . '</a>';
		}

		for ( $i = 2; $i > 0; $i -- ) {
			if ( 0 < ( $page - $i ) ) {
				$output .= '<a class="facetwp-page" data-page="' . ( $page - $i ) . '">' . ( $page - $i ) . '</a>';
			}
		}

		// Current page
		$output .= '<a class="facetwp-page active" data-page="' . $page . '">' . $page . '</a>';

		for ( $i = 1; $i <= 2; $i ++ ) {
			if ( $total_pages >= ( $page + $i ) ) {
				$output .= '<a class="facetwp-page" data-page="' . ( $page + $i ) . '">' . ( $page + $i ) . '</a>';
			}
		}

		if ( $total_pages > ( $page + 10 ) ) {
			$output .= '<a class="facetwp-page" data-page="' . ( $page + 10 ) . '">' . ( $page + 10 ) . '</a>';
		}

		if ( $page < $total_pages && $total_pages > 1 ) {
			$output .= '<a class="facetwp-page next-page" data-page="' . ( $page + 1 ) . '">&rsaquo;</a>';
		}

		if ( $total_pages > ( $page + 2 ) ) {
			$output .= '<a class="facetwp-page last-page" data-page="' . $total_pages . '">&raquo;</a>';
		}

		return $output;

	}

	/**
	 * Initialize our API endpoint.
	 *
	 * @param WP_JSON_ResponseHandler $server
	 */
	public static function api_init( WP_JSON_ResponseHandler $server ) {

		new Talents( $server );
		new Products( $server );

	}

	/**
	 * @return string The WP-API prefix.
	 */
	public static function api_url_prefix() {
		return 'api';
	}

}