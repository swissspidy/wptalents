<?php
/**
 * Main plugin class.
 *
 * @package WPTalents
 */

namespace WPTalents\Core;

use WPTalents\API;
use WPTalents\Lib\WP_Stack_Plugin2;

/**
 * Class WP_API_oEmbed_Plugin
 */
class Plugin extends WP_Stack_Plugin2 {
	/**
	 * Instance of this class.
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Plugin version.
	 */
	const VERSION = '0.1.0';

	/**
	 * Constructs the object, hooks in to `plugins_loaded`.
	 */
	protected function __construct() {
		$this->hook( 'plugins_loaded', 'add_hooks' );
	}

	/**
	 * Adds hooks.
	 */
	public function add_hooks() {
		$this->hook( 'init' );

		// Configure the REST API route.
		add_action( 'rest_api_init', array( new API\Controller(), 'register_routes' ) );

		add_filter( 'rest_url_prefix', function () {
			return 'api';
		} );

		// General stuff.
		add_action( 'http_request_timeout', function () {
			return 100;
		} );

		// Add a weekly cron schedule.
		$this->hook( 'cron_schedules' );
	}

	/**
	 * Initializes the plugin, registers textdomain, etc.
	 */
	public function init() {
		$this->load_textdomain( 'wptalents', '/languages' );
	}

	/**
	 * Add our post types and taxonomies on plugin activation.
	 */
	public function activate_plugin() {
		// Schedule cron events.
		wp_schedule_event( current_time( 'timestamp' ), 'weekly', 'wptalents_collect_contributions' );

		// Register post types.
		$this->register_post_types();

		// Register taxonomies.
		$this->register_taxonomies();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Flush rewrite rules on plugin deactivation.
	 */
	public function deactivate_plugin() {
		flush_rewrite_rules();
	}

	/**
	 * Register all post types.
	 */
	public function register_post_types() {
	}

	/**
	 * Register all taxonomies.
	 */
	public function register_taxonomies() {
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
				'to'   => __( 'Products', 'wptalents' ),
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

	/**
	 * Add a weekly cron schedule.
	 *
	 * @param array $schedules Registered cron schedules.
	 *
	 * @return array
	 */
	public function cron_schedules( $schedules ) {
		$schedules['weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once a week' ),
		);

		return $schedules;
	}
}
