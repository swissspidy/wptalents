<?php
/**
 * WP Talents Plugin
 *
 * @package   WP_Talents
 * @author    Pascal Birchler <pascal.birchler@spinpress.com>
 * @license   GPL-2.0+
 * @link      https://spinpress.com
 * @copyright 2014 WP Talents
 *
 * @wordpress-plugin
 * Plugin Name:       WP Talents
 * Plugin URI:        https://spinpress.com
 * Description:       AngelList + CrunchBase + WordPress = ♥
 * Version:           0.0.1
 * Author:            Pascal Birchler
 * Author URI:        https://spinpress.com
 * Text Domain:       wptalents-base
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define our constants
( ! defined( 'WP_TALENTS_DIR') ) &&	define( 'WP_TALENTS_DIR', plugin_dir_path( __FILE__ ) );
( ! defined( 'WP_TALENTS_URL' ) ) && define( 'WP_TALENTS_URL', plugins_url( '', __FILE__ ) );

/**
 * General functionality
 */

require_once( WP_TALENTS_DIR . 'includes/class-wptalents-collector.php' );
require_once( WP_TALENTS_DIR . 'includes/class-wptalents.php' );

/**
 * Public-facing functionality
 */

/**
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 */
register_activation_hook( __FILE__, array( 'WP_Talents', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WP_Talents', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WP_Talents', 'get_instance' ) );