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
defined( 'ABSPATH' ) or die();

// Define our constants
( ! defined( 'WP_TALENTS_DIR') ) &&	define( 'WP_TALENTS_DIR', plugin_dir_path( __FILE__ ) );
( ! defined( 'WP_TALENTS_URL' ) ) && define( 'WP_TALENTS_URL', plugins_url( '', __FILE__ ) );

/**
 * General functionality
 */

// Collectors
require_once( WP_TALENTS_DIR . 'includes/collector/class-wptalents-data-collector.php' );
require_once( WP_TALENTS_DIR . 'includes/collector/class-wptalents-score-collector.php' );
require_once( WP_TALENTS_DIR . 'includes/collector/class-wptalents-theme-collector.php' );
require_once( WP_TALENTS_DIR . 'includes/collector/class-wptalents-plugin-collector.php' );
require_once( WP_TALENTS_DIR . 'includes/collector/class-wptalents-profile-collector.php' );
require_once( WP_TALENTS_DIR . 'includes/collector/class-wptalents-codex-collector.php' );
require_once( WP_TALENTS_DIR . 'includes/collector/class-wptalents-contribution-collector.php' );
require_once( WP_TALENTS_DIR . 'includes/collector/class-wptalents-changeset-collector.php' );

// Helpers
require_once( WP_TALENTS_DIR . 'includes/class-wptalents-helper.php' );
require_once( WP_TALENTS_DIR . 'includes/class-wptalents-router.php' );

require_once( WP_TALENTS_DIR . 'includes/types/interface-wptalents-type.php' );
require_once( WP_TALENTS_DIR . 'includes/types/class-wptalents-activity.php' );
require_once( WP_TALENTS_DIR . 'includes/types/class-wptalents-product.php' );
require_once( WP_TALENTS_DIR . 'includes/types/class-wptalents-company.php' );
require_once( WP_TALENTS_DIR . 'includes/types/class-wptalents-person.php' );

require_once( WP_TALENTS_DIR . 'includes/class-wptalents.php' );

/**
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 */

function wptalents_activation() {

	$wptalents = new WP_Talents();
	$wptalents->activate();

}

register_activation_hook( __FILE__, 'wptalents_activation' );

function wptalents_deactivation() {

	$wptalents = new WP_Talents();
	$wptalents->deactivate();

}

register_deactivation_hook( __FILE__, 'wptalents_deactivation' );


/**
 * Plugin Initialization
 */
function wptalents_startup() {
	return new WP_Talents();
}

add_action( 'plugins_loaded', 'wptalents_startup' );