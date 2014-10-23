<?php
/**
 * WP Talents Base Class.
 *
 * @package   WP_Talents
 * @author    Pascal Birchler <pascal.birchler@spinpress.com>
 * @license   GPL-2.0+
 * @link      https://spinpress.com
 * @copyright 2014 WP Talents
 */

/**
 * Main WP Talents Class
 *
 * @package WP_Talents
 * @author  Pascal Birchler <pascal.birchler@spinpress.com>
 */
class WP_Talents {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '1.0.0';

	/**
	 * Unique identifier for the plugin.
	 *
	 *
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * plugin file.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected static $plugin_slug = 'wptalents';

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin by setting localization
	 * and some other background stuff.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {
		// Load plugin text domain
		add_action( 'init',                        array( __CLASS__, 'load_plugin_textdomain' ) );

		// Add custom post type
		add_action( 'init',                        array( __CLASS__, 'register_post_types' ) );

		// Add custom taxonomies
		add_action( 'init',                        array( __CLASS__, 'register_taxonomies' ) );

		// Add custom rewrite rules
		add_action( 'init',                        array( __CLASS__, 'add_rewrite_rules' ) );

		add_filter( 'query_vars',                  array( __CLASS__, 'filter_query_vars' ) );

		add_filter( 'request',                     array( __CLASS__, 'filter_request' ) );

		add_filter( 'template_include',            array( __CLASS__, 'template_include' ) );

		add_action( 'pre_get_posts',               array( __CLASS__, 'pre_get_posts' ) );

		add_filter( 'body_class',                  array( __CLASS__, 'filter_body_class' ) );

		add_filter( 'post_type_archive_title',     array( __CLASS__, 'filter_post_type_archive_title' ), 10, 2 );

		add_filter( 'post_type_archive_link',      array( __CLASS__, 'filter_post_type_archive_link' ), 10, 2 );

		add_filter( 'post_type_link',              array( __CLASS__, 'filter_post_type_link' ), 10, 4 );

		// Register post type connections
		add_action( 'p2p_init',                    array( __CLASS__, 'register_connections' ) );

		// Add custom meta boxes for our post type
		add_action( 'cmb_meta_boxes',              array( __CLASS__, 'add_meta_boxes' ) );

		// FacetWP

		add_filter( 'facetwp_sort_options',        array( __CLASS__, 'facetwp_sort_options' ), 10, 2 );

		add_filter( 'facetwp_pager_html',          array( __CLASS__, 'facetwp_pager_html' ), 10, 2 );

		/**
		 * JSON API
		 */
		add_filter( 'json_url_prefix',             array( __CLASS__, 'api_url_prefix' ) );

		add_action( 'wp_json_server_before_serve', array( __CLASS__, 'api_init' ) );
	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return self::$plugin_slug;
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean $network_wide       True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {
		// Register our post type
		self::register_post_types();

		// Change JSON API URL prefix
		add_filter( 'json_url_prefix', array( __CLASS__, 'api_url_prefix' ) );

		// Update the rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean $network_wide       True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {
		flush_rewrite_rules();
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public static function load_plugin_textdomain() {

		$domain = self::get_plugin_slug();
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/languages/' );

	}

	private static function _post_type_labels( $singular, $plural = '' ) {
		if ( '' === $plural ) {
			$plural = $singular . 's';
		}

		return array(
			'name'               => _x( $plural, 'post type general name' ),
			'singular_name'      => _x( $singular, 'post type singular name' ),
			'add_new'            => __( 'Add New', 'wptalents-theme' ),
			'add_new_item'       => __( 'Add New ' . $singular, 'wptalents-theme' ),
			'edit_item'          => __( 'Edit ' . $singular, 'wptalents-theme' ),
			'new_item'           => __( 'New ' . $singular, 'wptalents-theme' ),
			'view_item'          => __( 'View ' . $singular, 'wptalents-theme' ),
			'search_items'       => __( 'Search ' . $plural, 'wptalents-theme' ),
			'not_found'          => __( 'No ' . $plural . ' found', 'wptalents-theme' ),
			'not_found_in_trash' => __( 'No ' . $plural . ' found in Trash', 'wptalents-theme' ),
			'parent_item_colon'  => ''
		);
	}

	/**
	 * Register all the custom post types.
	 *
	 * Registers a talent post type using the
	 * built-in register_post_type() function.
	 *
	 * @uses  register_post_type()
	 * @uses  WP_Talents::_post_type_labels()
	 * @since 1.0.0
	 */
	public static function register_post_types() {

		/**
		 * Company Post Type
		 */

		$company_args = array(
			'labels'        => self::_post_type_labels( 'Company', 'Companies' ),
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

		register_post_type( 'company', $company_args );

		/**
		 * Company Post Type
		 */

		$person_args = array(
			'labels'        => self::_post_type_labels( 'Person', 'People' ),
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

		register_post_type( 'person', $person_args );

		/**
		 * Activity Post Type
		 */

		$activity_args = array(
			'labels'        => self::_post_type_labels( 'Activity', 'Activities' ),
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

		register_post_type( 'activity', $activity_args );
	}

	/**
	 * @uses register_taxonomy()
	 */
	public static function register_taxonomies() {

		$region_args = array(
			'labels'                => self::_post_type_labels( 'Region', 'Regions' ),
			'hierarchical'          => true,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'update_count_callback' => '_update_post_term_count',
			'public'                => true,
			'show_tagcloud'         => false,
			'rewrite'               => false
		);

		register_taxonomy( 'region', array( 'company', 'person' ), $region_args );

		$region_args = array(
			'labels'                => self::_post_type_labels( 'Service' ),
			'hierarchical'          => true,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'update_count_callback' => '_update_post_term_count',
			'public'                => true,
			'show_tagcloud'         => false,
			'rewrite'               => false,
		);

		register_taxonomy( 'service', array( 'company', 'person' ), $region_args );

		$activity_type_args = array(
			'show_ui'               => true,
			'show_admin_column'     => true,
			'update_count_callback' => '_update_post_term_count',
			'public'                => true,
			'show_tagcloud'         => false,
			'rewrite'               => false,
		);

		register_taxonomy( 'activity-type', 'activity', $activity_type_args );
	}

	/**
	 * @uses add_rewrite_rule()
	 */
	public static function add_rewrite_rules() {
		/** @var WP_Rewrite $wp_rewrite */
		global $wp_rewrite;

		/**
		 * Single Talents and Archive
		 */
		add_rewrite_rule( '([^/]+)?/?$', 'index.php?talent=$matches[1]', 'top' );

		/**
		 * Paged Archive
		 *
		 * Somehow it only works when adding both rules...
		 */
		add_rewrite_rule( '([^/]+)/page/?([0-9]{1,})/?$', 'index.php?talent=$matches[1]&paged=$matches[2]', 'top' );
		add_rewrite_rule( '([^/]+)/page/?([0-9]{1,})/?$', 'index.php?talent=$matches[1]&paged=$matches[2]', 'bottom' );
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
	 * @param  bool    $sample    Is it a sample permalink.
	 *
	 * @return string             The modified permalink.
	 */
	public static function filter_post_type_link( $permalink, $post, $leavename, $sample ) {
		if ( 'person' === $post->post_type || 'company' === $post->post_type ) {
			$permalink = esc_url( home_url( user_trailingslashit( "%$post->post_type%" ) ) );
		}

		if ( ! $leavename ) {
			$permalink = str_replace( "%$post->post_type%", $post->post_name, $permalink );
		}

		return $permalink;
	}

	/**
	 * Filters the current request to modify query vars.
	 *
	 * @param array $query_vars
	 *
	 * @return array $query_vars
	 */
	public static function filter_request( $query_vars ) {
		if ( isset( $query_vars['talent'] ) ) {

			if ( 'talents' === $query_vars['talent'] ) {
				// Talents Archive

				$query_vars['post_type'] = 'company';

			} else if ( self::post_exists( $query_vars['talent'], 'company' ) ) {
				// Single Company

				$query_vars['post_type'] = 'company';
				$query_vars['name']      = $query_vars['talent'];

			} else if ( self::post_exists( $query_vars['talent'], 'person' ) ) {
				// Single Person

				$query_vars['post_type'] = 'person';
				$query_vars['name']      = $query_vars['talent'];

			} else if ( self::post_exists( $query_vars['talent'], 'page' ) ) {
				// Single Page

				$query_vars['post_type'] = 'page';
				$query_vars['name']      = $query_vars['talent'];

			} else {
				// Single Post

				$query_vars['post_type'] = 'post';
				$query_vars['name']      = $query_vars['talent'];

			}

			// Unset the unused query var
			unset ( $query_vars['talent'] );

		}

		return $query_vars;
	}

	/**
	 * @param $query
	 */
	public static function pre_get_posts( $query ) {
		if( ! $query->is_main_query() || is_admin() ) {
			return;
		}

		if ( is_post_type_archive( 'company' ) ) {
			$query->set( 'post_type', array( 'company', 'person' ) );
			$query->set( 'orderby',   'name' );
			$query->set( 'order',     'ASC' );
		}
	}

	/**
	 * Filter the path of the current template before including it.
	 *
	 * @param string $template The path of the template to include.
	 *
	 * @return string The template to include.
	 */
	public static function template_include( $template ) {
		$post_types = array_filter( (array) get_query_var( 'post_type' ) );

		if ( in_array( 'person', $post_types ) && in_array( 'company', $post_types ) ) {
			return locate_template( 'archive-talent.php' );
		}

		if ( get_query_var( 'region' ) || get_query_var( 'service' ) ) {
			return locate_template( 'archive-talent.php' );
		}

		return $template;
	}

	/**
	 * @param  array $body_class
	 *
	 * @return array
	 */
	public static function filter_body_class( $body_class ) {

		if ( is_post_type_archive( 'company' ) ) {
			$body_class[] = 'post-type-archive-person';
			$body_class[] = 'archive-talents';
		}

		return $body_class;
	}

	/**
	 * Filter the post type archive title.
	 *
	 * @param string $name      The post type archive title.
	 * @param string $post_type Post type name.
	 *
	 * @return string
	 */
	public static function filter_post_type_archive_title( $name, $post_type ) {
		if ( 'company' === $post_type || 'person' === $post_type ) {
			return __( 'Talents', 'wptalents' );
		}

		return $name;
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
	public static function filter_post_type_archive_link( $link, $post_type ) {
		if ( 'company' === $post_type || 'person' === $post_type ) {
			return home_url( user_trailingslashit( 'talents', 'post_type_archive' ) );
		}

		return $link;
	}

	/**
	 * Filters query vars to add our own talent var.
	 *
	 * @param  array $query_vars The current query vars array.
	 *
	 * @return array             The modified query vars.
	 */
	public static function filter_query_vars( $query_vars ) {
		$query_vars[] = 'talent';

		return $query_vars;
	}

	/**
	 * Determine if a post exists based on post_name (slug) and post_type.
	 *
	 * @param string $post_name The post slug.
	 * @param string $post_type Post Type. Defaults to post
	 *
	 * @return int The ID on success, 0 on failure.
	 */
	private static function post_exists( $post_name, $post_type = 'post' ) {
		global $wpdb;

		$query = "SELECT ID FROM $wpdb->posts WHERE 1=1";
		$args  = array();

		if ( ! empty ( $post_name ) ) {
			$query .= " AND post_name LIKE '%s' ";
			$args[] = $post_name;
		}
		if ( ! empty ( $post_type ) ) {
			$query .= " AND post_type = '%s' ";
			$args[] = $post_type;
		}

		if ( ! empty ( $args ) && null !== $wpdb->get_var( $wpdb->prepare( $query, $args ) ) ) {
			return true;
		}

		return false;
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
		if ( ! function_exists( 'p2p_register_connection_type' ) ) {
			return;
		}

		/** @var P2P_Connection_Type $company_activity */
		$company_activity = p2p_register_connection_type( array(
			'name'            => 'company_activity',
			'from'            => 'activity',
			'to'              => 'company',
			'cardinality'     => 'many-to-many',
			'title'           => __( 'Connected Company', 'wptalents' ),
			'admin_box'       => array(
				'show'    => 'from',
				'context' => 'side'
			),
			'can_create_post' => false,
			'to_query_vars'   => array( 'post_status' => 'any' ),
			'from_query_vars' => array( 'post_status' => 'any' ),
		) );

		/** @var P2P_Connection_Type $person_activity */
		$person_activity = p2p_register_connection_type( array(
			'name'            => 'person_activity',
			'from'            => 'activity',
			'to'              => 'person',
			'cardinality'     => 'many-to-many',
			'title'           => __( 'Connected Person', 'wptalents' ),
			'admin_box'       => array(
				'show'    => 'from',
				'context' => 'side'
			),
			'can_create_post' => false,
			'to_query_vars'   => array( 'post_status' => 'any' ),
			'from_query_vars' => array( 'post_status' => 'any' ),
		) );

		/** @var P2P_Connection_Type $employees */
		$employees = p2p_register_connection_type( array(
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

	/**
	 * Add custom meta boxes to our post types.
	 *
	 * @param  array $meta_boxes An array of existing meta boxes.
	 *
	 * @return array $meta_boxes The modified meta boxes array.
	 */
	public static function add_meta_boxes( $meta_boxes ) {
		$talent_details = array(
			array(
				'id'   => 'wordpress-username',
				'name' => __( 'WordPress.org username', 'wptalents' ),
				'type' => 'text',
				'cols' => 4
			),
			array(
				'id'   => 'location',
				'name' => __( 'Location', 'wptalents' ),
				'type' => 'text',
				'cols' => 4
			),
			array(
				'id'   => 'url',
				'name' => __( 'Website URL', 'wptalents' ),
				'type' => 'text_url',
				'cols' => 4
			),
			array(
				'id'   => 'byline',
				'name' => __( 'Byline', 'wptalents' ),
				'type' => 'text',
				'cols' => 4
			),
		);

		$company_details = array(
			array(
				'id'   => 'wordpress_vip',
				'name' => __( 'WordPress.com VIP partner', 'wptalents' ),
				'type' => 'checkbox',
				'cols' => 4
			),
		);

		$person_details = array(
			array(
				'id'   => 'job',
				'name' => __( 'Job Title', 'wptalents' ),
				'type' => 'text',
				'cols' => 6
			),
		);

		$meta_boxes[] = array(
			'title'    => __( 'Talent Details', 'wptalents' ),
			'pages'    => 'company',
			'context'  => 'advanced',
			'priority' => 'high',
			'fields'   => array_merge( $talent_details, $company_details )
		);

		$meta_boxes[] = array(
			'title'    => __( 'Talent Details', 'wptalents' ),
			'pages'    => 'person',
			'context'  => 'advanced',
			'priority' => 'high',
			'fields'   => array_merge( $talent_details, $person_details )
		);

		return $meta_boxes;
	}

	/**
	 * @param WP_Post|int $post The post object or ID.
	 * @param string      $type The meta to retrieve. Defaults to all.
	 *                          Possible values are:
	 *                          - profile
	 *                          - plugins
	 *                          - themes
	 *
	 * @return mixed            The required meta if available,
	 *                          false if the post does not exist.
	 */
	public static function get_talent_meta( $post = null, $type = 'all' ) {
		if ( null === $post ) {
			$post = get_queried_object();
		}

		if ( ! is_object( $post = get_post( $post ) ) ) {
			return false;
		}

		$collector = new WP_Talents_Collector( $post );

		if ( 'profile' === $type ) {
			return $collector->get_profile();
		} else if ( 'plugins' === $type ) {
			return $collector->get_plugins();
		} else if ( 'themes' === $type ) {
			return $collector->get_themes();
		} else if ( 'score' === $type ) {
			return $collector->get_score();
		} else {
			// Return all meta
			return array(
				'profile' => $collector->get_profile(),
				'plugins' => $collector->get_plugins(),
				'themes'  => $collector->get_themes(),
			);
		}
	}

	/**
	 * Get the avatar of a talent.
	 *
	 * @param WP_Post|int $post The post object or ID.
	 *
	 * @return mixed            The avatar URL on success,
	 *                          false if the post does not exist.
	 */
	public static function get_avatar( $post = null, $size = 128 ) {
		$profile = self::get_talent_meta( $post, 'profile' );

		if ( ! $profile ) {
			return false;
		}

		// Add size parameter
		$profile['avatar'] = add_query_arg( array( 's' => $size ), $profile['avatar'] );

		return sprintf(
			'<img src="%1$s" alt="%2$s" width="%3$d" height="%3$d" />',
			esc_url( $profile['avatar'] ),
			esc_attr( get_the_title( $post ) ),
			$size
		);
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
	 * @param  array $params
	 *
	 * @return string
	 */
	public static function facetwp_pager_html( $output, $params ) {
		$output     = '';

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

	public static function api_init( $server ) {
		require_once dirname( __FILE__ ) . '/class-wptalents-api.php';

		$wptalents_api = new WP_Talents_API( $server );
		$wptalents_api->register_filters();
	}

	public static function api_url_prefix() {
		return 'api';
	}
}