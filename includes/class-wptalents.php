<?php

class WP_Talents {

	/* @var $types WP_Talents_Type[] */
	protected $types = array();

	/* @var $router WP_Talents_Router */
	protected $router;

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '1.0.0';

	public function __construct() {

		// Setup the router class
		$this->router = new WP_Talents_Router();

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

		add_filter( 'facetwp_sort_options', array( __CLASS__, 'facetwp_sort_options' ), 10, 2 );

		add_filter( 'facetwp_pager_html', array( __CLASS__, 'facetwp_pager_html' ), 10, 2 );

		// JSON API

		add_filter( 'json_url_prefix', array( __CLASS__, 'api_url_prefix' ) );

		add_action( 'wp_json_server_before_serve', array( __CLASS__, 'api_init' ) );

	}

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

	public function add_types() {

		$this->types = apply_filters( 'wptalents_types', array(
			'person'   => new WP_Talents_Person(),
			'company'  => new WP_Talents_Company(),
			'activity' => new WP_Talents_Activity(),
			'product'  => new WP_Talents_Product(),
		) );

	}

	public function register_post_types() {

		/** @var $type WP_Talents_Type */
		foreach ( $this->types as $type ) {
			$type->register_post_type();
		}

	}

	public function register_taxonomies() {

		/** @var $type WP_Talents_Type */
		foreach ( $this->types as $type ) {
			$type->register_taxonomy();
		}

	}

	/**
	 * Registers our connections using Posts 2 Posts.
	 *
	 * @uses  p2p_register_connection_type()
	 * @since 1.0.0
	 *
	 * @return void
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
				'context' => 'side'
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
			'title'           => __( 'Owner', 'wptalents' ),
			'admin_box'       => array(
				'show'    => 'from',
				'context' => 'side'
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
				'show'    => 'from',
				'context' => 'side'
			),
			'fields'          => array(
				// Todo: Add From-To dates so we can see past companies
				'role' => array(
					'title'   => __( 'Role', 'wptalents' ),
					'values'  => array(
						'ceo'      => __( 'CEO', 'wptalents' ),
						'cto'      => __( 'CTO', 'wptalents' ),
						'founder'  => __( 'Founder', 'wptalents' ),
						'employee' => __( 'Employee', 'wptalents' )
					),
					'default' => 'employee'
				),
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

	public function add_cmb_field_types( array $cmb_field_types ) {

		require_once( WP_TALENTS_DIR . 'includes/class-wptalents-cmb-gmap-field.php' );

		$cmb_field_types['gmap'] = 'WP_Talents_CMB_Gmap_Field';

		return $cmb_field_types;

	}

	public function filter_body_class() {

		/** @var $type WP_Talents_Type */
		foreach ( $this->types as $type ) {
			add_filter( 'body_class', array( $type, 'filter_body_class' ) );
		}

	}

	public function filter_post_class() {

		/** @var $type WP_Talents_Type */
		foreach ( $this->types as $type ) {
			add_filter( 'post_class', array( $type, 'filter_post_class' ) );
		}

	}

	/**
	 * Fetches the map of the talent's location and sets it
	 * as the post thumbnail.
	 *
	 * @param int $post_id The ID of the current post.
	 */
	public function add_map_on_save_post( $post_id ) {

		// If this is just a revision or the post already has a thumbnail, don't proceed
		if ( wp_is_post_revision( $post_id ) || has_post_thumbnail( $post_id ) ) {
			return;
		}

		if ( ! in_array( get_post_type( $post_id ), array_keys( $this->types ) ) ) {
			;
		}

		// Get the talent's location data
		$location = WP_Talents_Helper::get_talent_meta( get_the_ID(), 'location' );

		if ( empty( $location['name'] ) ) {
			return;
		}

		$map_url = sprintf(
			'https://maps.googleapis.com/maps/api/staticmap?center=%s&scale=2&zoom=6&size=600x320&maptype=roadmap',
			urlencode( $location['name'] )
		);

		$tmp = download_url( $map_url );

		// Set variables for storage
		$file_array = array(
			'name'     => get_post( $post_id )->post_name . '-map.png',
			'tmp_name' => $tmp
		);

		// If error storing temporarily, unlink
		if ( is_wp_error( $tmp ) ) {
			@unlink( $file_array['tmp_name'] );
			$file_array['tmp_name'] = '';
		}

		// do the validation and storage stuff
		$id = media_handle_sideload( $file_array, $post_id );

		// If error storing permanently, unlink
		if ( is_wp_error( $id ) ) {
			@unlink( $file_array['tmp_name'] );
		}

		// Set map as post thumbnail
		set_post_thumbnail( $post_id, $id );

	}

	/**
	 * Filter the FacetWP sort options.
	 *
	 * @param  array $options
	 * @param  array $params
	 *
	 * @return array
	 */
	public static function facetwp_sort_options( $options, $params ) {

		$options['score_desc'] = array(
			'label'      => __( 'Score (Highest)', 'wptalents' ),
			'query_args' => array(
				'orderby'  => 'meta_value_num',
				'order'    => 'DESC',
				'meta_key' => '_score'
			)
		);

		$options['score_asc'] = array(
			'label'      => __( 'Score (Lowest)', 'wptalents' ),
			'query_args' => array(
				'orderby'  => 'meta_value_num',
				'order'    => 'ASC',
				'meta_key' => '_score'
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

		$prev = _x( '←', 'Previous page link in pagination', 'wptalents' );
		$next = _x( '→', 'Next page link in pagination', 'talented' );


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

		require_once WP_TALENTS_DIR . 'includes/api/class-wptalents-products-api.php';
		require_once WP_TALENTS_DIR . 'includes/api/class-wptalents-talents-api.php';

		new WP_Talents_Talents_API( $server );
		new WP_Talents_Products_API( $server );

	}

	/**
	 * @return string The WP-API prefix.
	 */
	public static function api_url_prefix() {
		return 'api';
	}

}