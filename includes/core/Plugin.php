<?php

namespace WPTalents\Core;

use WPTalents\API\Jobs;
use WPTalents\API\Oembed_Provider;
use WPTalents\API\Products;
use WPTalents\API\Talents;
use WPTalents\Types\Job;
use WPTalents\Types\Product;

/**
 * Class Plugin
 * @package WPTalents\Core
 */
class Plugin {

	/* @var $types \WPTalents\Types\Type[] */
	protected $types = array();

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

		add_action( 'init', array( $this, 'load_plugin_textdomain' ), 4 );

		add_action( 'http_request_timeout', array( $this, 'http_request_timeout' ) );

		add_action( 'init', array( $this, 'add_types' ), 8 );

		// BuddyPress

		add_action( 'bp_init', array( $this, 'register_member_types' ) );

		add_filter( 'bp_core_fetch_avatar', array( $this, 'filter_avatar' ), 10, 2 );

		add_filter( 'bp_is_username_compatibility_mode', array( $this, 'bp_is_username_compatibility_mode' ) );

		add_filter( 'bp_core_get_username', array( $this, 'bp_core_get_username' ) );

		add_filter( 'bp_members_suggestions_get_suggestions', array(
			$this,
			'bp_members_suggestions_get_suggestions'
		) );

		add_filter( 'bp_core_get_user_domain', array( $this, 'bp_core_get_user_domain' ), 10, 4 );

		add_action( 'bp_setup_nav', array( $this, 'bp_setup_nav' ) );

		add_action( 'bp_register_activity_actions', array( $this, 'register_activity_actions' ) );

		/*
		 * todo: consider this again at a later point
		 *
		 * Problems to solve: redirect user after updating the profile, rewrite all activity items.
		 * Maybe this should only be done manually.
		 * @link https://buddypress.org/support/topic/how-to-redirect-users-to-their-profile-after-they-edit-their-profile/
		 */
		//add_action( 'xprofile_updated_profile', array( $this, 'update_user_nicename' ) );
		//add_action( 'bp_core_signup_user', array( $this, 'update_user_nicename' ) );
		//add_action( 'bp_core_activated_user', array( $this, 'update_user_nicename' ) );

		// URL Rewriting

		add_action( 'init', array( $this, 'add_rewrite_rules' ) );

		// Post Types, Taxonomies and Connections

		add_action( 'init', array( $this, 'register_post_types' ) );

		add_action( 'init', array( $this, 'register_taxonomies' ) );

		add_action( 'p2p_init', array( $this, 'register_connections' ) );

		add_action( 'init', array( $this, 'add_meta_boxes' ) );

		// Body / Post Classes

		add_action( 'init', array( $this, 'filter_body_class' ) );

		add_action( 'init', array( $this, 'filter_post_class' ) );

		add_action( 'save_post', array( $this, 'add_map_on_save_post' ) );

		// FacetWP

		add_filter( 'facetwp_sort_options', array( __CLASS__, 'facetwp_sort_options' ) );

		add_filter( 'facetwp_pager_html', array( __CLASS__, 'facetwp_pager_html' ), 10, 2 );

		// JSON API

		add_filter( 'json_url_prefix', array( __CLASS__, 'api_url_prefix' ) );

		add_action( 'wp_json_server_before_serve', array( __CLASS__, 'api_init' ) );

		add_action( 'wp_head', array( $this, 'add_oembed_links' ) );

		add_filter( 'template_redirect', array( $this, 'template_redirect' ) );

	}

	public function http_request_timeout() {
		return 10;
	}

	/**
	 * Add rewrite rules for the plugin.
	 */
	public function add_rewrite_rules() {

		add_rewrite_rule( '([^/]+)?/embed(/(.*))?/?$', 'index.php?talent=$matches[1]&embed=$matches[2]', 'top' );
		add_rewrite_endpoint( 'embed', EP_PERMALINK );

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
			'product' => new Product(),
			'job'     => new Job(),
		) );

	}

	public function register_member_types() {
		bp_register_member_type( 'person', array(
			'labels' => array(
				'name'          => __( 'People', 'wptalents' ),
				'singular_name' => __( 'Person', 'wptalents' ),
			),
		) );

		bp_register_member_type( 'company', array(
			'labels' => array(
				'name'          => __( 'Companies', 'wptalents' ),
				'singular_name' => __( 'Company', 'wptalents' ),
			),
		) );
	}

	/**
	 * Filters the avatars from BuddyPress to display the Gravatar from WordPress.org.
	 *
	 * @param string $avatar_url
	 * @param array  $params
	 *
	 * @return string
	 */
	public function filter_avatar( $avatar_url, $params ) {
		if ( 'user' !== $params['object'] ) {
			return $avatar_url;
		}

		if ( false === strpos( $avatar_url, 'gravatar.com' ) ) {
			return $avatar_url;
		}

		$user = get_user_by( 'id', $params['item_id'] );

		$avatar = get_user_meta( $user->ID, '_wptalents_avatar', true );

		if ( ! empty( $avatar ) ) {
			$custom_avatar = add_query_arg( array(
				's' => absint( $params['width'] ),
				'd' => 'mm'
			), esc_url( $avatar ) );
		} else {
			return $avatar_url;
		}

		if ( true === $params['html'] ) {

			$html_css_id = '';
			if ( ! empty( $params['css_id'] ) ) {
				$html_css_id = ' id="' . esc_attr( $params['css_id'] ) . '"';
			}

			// Use an alias to leave the param unchanged
			$avatar_classes = $params['class'];
			if ( ! is_array( $avatar_classes ) ) {
				$avatar_classes = explode( ' ', $avatar_classes );
			}

			// merge classes
			$avatar_classes = array_merge( $avatar_classes, array(
				$params['object'] . '-' . $params['item_id'] . '-avatar',
				'avatar-' . $params['width'],
			) );

			// Sanitize each class
			$avatar_classes = array_map( 'sanitize_html_class', $avatar_classes );

			// populate the class attribute
			$html_class = ' class="' . join( ' ', $avatar_classes ) . ' photo"';

			$html_title = '';
			if ( ! empty( $params['title'] ) ) {
				$html_title = ' title="' . esc_attr( $params['title'] ) . '"';
			}

			$avatar_url = '<img src="' . $custom_avatar . '" width="' . $params['width'] . '" height="' . $params['height'] . '" alt="' . esc_attr( $params['alt'] ) . '"' . $html_css_id . $html_class . $html_title . ' />';
		}

		return $avatar_url;
	}

	/**
	 * Filter 'bp_is_username_compatibility_mode' to alter the value.
	 *
	 * It's an ugly hack because we want the compatibility mode for mentions (@swissspidy),
	 * but not for URLs (https://wptalents.com/pascal-birchler).
	 *
	 * @param bool $is_compatibility_mode re we running username compatibility mode?
	 *
	 * @return bool
	 */
	public function bp_is_username_compatibility_mode( $is_compatibility_mode ) {

		$backtrace = debug_backtrace();

		if ( isset( $backtrace[4] ) && 'bp_core_get_username' === $backtrace[4]['function'] ) {
			$is_compatibility_mode = true;
		}

		if ( isset( $backtrace[5] ) && 'bp_core_get_user_domain' === $backtrace[5]['function'] ) {
			$is_compatibility_mode = false;
		}

		if ( isset( $backtrace[4] ) && 'bp_core_get_user_domain' === $backtrace[4]['function'] ) {
			$is_compatibility_mode = false;
		}

		if ( isset( $backtrace[4] ) && 'bp_activity_get_user_mentionname' === $backtrace[4]['function'] ) {
			$is_compatibility_mode = true;
		}

		if ( isset( $backtrace[6] ) && 'bp_activity_at_name_filter_updates' === $backtrace[6]['function'] ) {
			$is_compatibility_mode = true;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$is_compatibility_mode = true;
		}

		return $is_compatibility_mode;
	}

	public function bp_core_get_username( $username ) {
		$backtrace = debug_backtrace();

		if ( isset( $backtrace[4] ) && 'bp_activity_screen_notification_settings' === $backtrace[4]['function'] ) {
			$user = get_user_by( 'slug', $username );

			return $user->user_login;
		}

		return $username;
	}

	/**
	 * Filters the members suggestions results.
	 *
	 * @param array $results Array of users to suggest.
	 *
	 * @return array
	 */
	public function bp_members_suggestions_get_suggestions( $results ) {
		foreach ( $results as &$result ) {
			$user       = get_user_by( 'slug', $result->ID );
			$result->ID = $user->user_login;
			unset( $user );
		}

		return $results;
	}

	/**
	 * Filters the domain for the passed user.
	 *
	 * @param string $domain        Domain for the passed user.
	 * @param int    $user_id       ID of the passed user.
	 * @param string $user_nicename Optional. user_nicename of the user.
	 * @param string $user_login    Optional. user_login of the user.
	 *
	 * @return string
	 */
	public function bp_core_get_user_domain( $domain, $user_id, $user_nicename = false, $user_login = false ) {
		if ( ! $user_nicename || ! $user_login ) {
			$user          = get_user_by( 'id', $user_id );
			$user_nicename = $user->user_nicename;
			$user_login    = $user->user_login;
		}
		$domain = str_replace( $user_login, $user_nicename, $domain );

		return $domain;
	}

	public function bp_setup_nav() {
		global $bp;
		$bp->bp_nav['profile']['position'] = 10;
		$bp->bp_nav['activty']['position'] = 15;

		bp_core_new_nav_item( array(
			'name'                => __( 'Overview', 'wptalents' ),
			'slug'                => 'overview',
			'position'            => 5,
			'screen_function'     => array( $this, 'profile_tab_overview' ),
			'item_css_id'         => 'overview',
			'default_subnav_slug' => 'overview',
		) );

		// todo: only show these if there's at least 1 product/plugin/theme?

		bp_core_new_nav_item( array(
			'name'                    => __( 'Jobs', 'wptalents' ),
			'slug'                    => 'jobs',
			'position'                => 50,
			'show_for_displayed_user' => ( 'company' === bp_get_member_type( bp_displayed_user_id() ) ),
			'screen_function'         => array( $this, 'profile_tab_jobs' ),
			'item_css_id'             => 'jobs',
			'default_subnav_slug'     => 'jobs',
		) );

		bp_core_new_nav_item( array(
			'name'                => __( 'Products', 'wptalents' ),
			'slug'                => 'products',
			'position'            => 40,
			'screen_function'     => array( $this, 'profile_tab_products' ),
			'item_css_id'         => 'products',
			'default_subnav_slug' => 'products',
		) );

		bp_core_new_nav_item( array(
			'name'                => __( 'Plugins', 'wptalents' ),
			'slug'                => 'plugins',
			'position'            => 40,
			'screen_function'     => array( $this, 'profile_tab_plugins' ),
			'item_css_id'         => 'plugins',
			'default_subnav_slug' => 'plugins',
		) );

		bp_core_new_nav_item( array(
			'name'                => __( 'Themes', 'wptalents' ),
			'slug'                => 'themes',
			'position'            => 40,
			'screen_function'     => array( $this, 'profile_tab_themes' ),
			'item_css_id'         => 'themes',
			'default_subnav_slug' => 'themes',
		) );

		bp_core_new_nav_item( array(
			'name'                => __( 'WordPress.tv', 'wptalents' ),
			'slug'                => 'wordpresstv',
			'position'            => 40,
			'screen_function'     => array( $this, 'profile_tab_wordpresstv' ),
			'item_css_id'         => 'wordpresstv',
			'default_subnav_slug' => 'wordpresstv',
		) );

	}

	public function register_activity_actions() {
		bp_activity_set_action(
			'wptalents',
			'user_created',
			__( 'New talent', 'buddypress' ),
			'wptalents_format_activity_action_user_created',
			__( 'WP Talents', 'buddypress' )
		);
	}

	public function profile_tab_overview() {
		add_action( 'bp_template_title', array( $this, 'profile_tab_overview_title' ) );
		add_action( 'bp_template_content', array( $this, 'profile_tab_overview_content' ) );
		do_action( 'bp_template_profile_tab_overview' );
		bp_core_load_template( 'members/single/plugins' );
	}

	public function profile_tab_overview_title() {
		_e( 'Overview', 'wptalents' );
	}

	public function profile_tab_overview_content() {
		_e( 'Overview Content', 'wptalents' );
	}

	public function profile_tab_plugins() {
		add_action( 'bp_template_title', array( $this, 'profile_tab_overview_title' ) );
		add_action( 'bp_template_content', array( $this, 'profile_tab_overview_content' ) );
		do_action( 'bp_template_profile_tab_plugins' );
		bp_core_load_template( 'members/single/plugins' );
	}

	/**
	 * Syncs Xprofile data to the standard built in WordPress profile data.
	 *
	 * @package BuddyPress Core
	 *
	 * @param int $user_id ID of the updated user.
	 */
	public function update_user_nicename( $user_id = 0 ) {
		// Bail if profile syncing is disabled
		if ( bp_disable_profile_sync() ) {
			return;
		}

		if ( empty( $user_id ) ) {
			$user_id = bp_loggedin_user_id();
		}

		if ( empty( $user_id ) ) {
			return;
		}

		$fullname = xprofile_get_field_data( bp_xprofile_fullname_field_id(), $user_id );

		wp_update_user( array(
			'ID'            => $user_id,
			'user_nicename' => sanitize_title( $fullname )
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
			'name'            => 'product_owner',
			'from'            => 'user',
			'to'              => 'product',
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
			'name'            => 'hiring',
			'from'            => 'user',
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
	 * Add oEmbed discovery links to single talent & product pages
	 */
	public function add_oembed_links() {

		if ( is_singular( array( 'company', 'person', 'product' ) ) ) {
			echo '<link rel="alternate" type="application/json+oembed" href="' . esc_url( get_json_url( null, 'oembed/?url=' . get_permalink() ) ) . '" />' . "\n";
		}

	}

	/**
	 * Add template redirect for the oEmbed output.
	 */
	public function template_redirect() {

		global $wp_query, $post;

		if ( ! isset( $wp_query->query_vars['embed'] ) ) {
			return;
		}

		do_action( 'wptalents_oembed_output', $post );
		exit;

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

		$slug = get_post( $post_id )->post_name;

		if ( '' === $slug ) {
			$slug = sanitize_title( get_the_title( $post_id ) );
		}

		// Set variables for storage
		$file_array = array(
			'name'     => $slug . '-map.png',
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
	 * @param \WP_JSON_Server $server
	 */
	public static function api_init( \WP_JSON_Server $server ) {

		new Talents( $server );
		new Products( $server );
		new Jobs( $server );
		new Oembed_Provider( $server );

	}

	/**
	 * @return string The WP-API prefix.
	 */
	public static function api_url_prefix() {
		return 'api';
	}

}