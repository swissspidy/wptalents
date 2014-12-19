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
 * Text Domain:       wptalents
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();

// Define our constants
( ! defined( 'WP_TALENTS_DIR' ) ) && define( 'WP_TALENTS_DIR', plugin_dir_path( __FILE__ ) );
( ! defined( 'WP_TALENTS_URL' ) ) && define( 'WP_TALENTS_URL', plugins_url( '', __FILE__ ) );

if ( file_exists( WP_TALENTS_DIR . 'vendor/autoload.php' ) ) {
	require_once( WP_TALENTS_DIR . 'vendor/autoload.php' );
}

// WP-CLI Command
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	include( WP_TALENTS_DIR . 'includes/cli/Talent_Command.php' );
}

/**
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 */

function wptalents_activation() {

	$wptalents = new WPTalents\Core\Plugin();
	$wptalents->activate();

}

register_activation_hook( __FILE__, 'wptalents_activation' );

function wptalents_deactivation() {

	$wptalents = new \WPTalents\Core\Plugin();
	$wptalents->deactivate();

}

register_deactivation_hook( __FILE__, 'wptalents_deactivation' );


/**
 * Plugin Initialization
 */
function wptalents_startup() {
	require_once( 'public/template-tags.php' );

	new \WPTalents\Core\Plugin();
}

add_action( 'plugins_loaded', 'wptalents_startup' );