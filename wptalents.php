<?php
/**
 * Plugin Name: WP Talents
 * Plugin URI:  https://github.com/swissspidy/wptalents
 * Description: AngelList + CrunchBase + WordPress = ♥
 * Version:     0.1.0
 * Author:      Pascal Birchler
 * Author URI:  https://spinpress.com
 * License:     GPLv2+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wptalents
 * Domain Path: /languages
 *
 * @package WPTalents
 */

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

defined( 'WPINC' ) or die;

if ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
	require_once( dirname( __FILE__ ) . '/vendor/autoload.php' );
}

$wptalents_requirements_check = new \WPTalents\Lib\Requirements_Check( array(
	'title' => 'WP Talents',
	'php'   => '5.5',
	'wp'    => '4.3-beta4',
	'file'  => __FILE__,
) );

if ( $wptalents_requirements_check->passes() ) {
	require_once( dirname( __FILE__ ) . '/public/template-tags.php' );

	\WPTalents\Core\Plugin::start( __FILE__ );
	\WPTalents\Core\BuddyPress::start( __FILE__ );
	\WPTalents\Core\ElasticPress::start( __FILE__ );

	// Activation / deactivation hooks.
	register_activation_hook( __FILE__, array( \WPTalents\Core\Plugin::get_instance(), 'activate_plugin' ) );
	register_deactivation_hook( __FILE__, array( \WPTalents\Core\Plugin::get_instance(), 'deactivate_plugin' ) );

	// WP-CLI command.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::add_command( 'talent', 'WPTalents\CLI\Talent_Command' );
	}

	// Add cron commands.
	add_action( 'wptalents_collect_bbpress', array(
		'WPTalents\Collector\bbPress_Collector',
		'retrieve_data',
	) );

	add_action( 'wptalents_collect_buddypress', array(
		'WPTalents\Collector\BuddyPress_Collector',
		'retrieve_data',
	) );

	add_action( 'wptalents_collect_changesets', array(
		'WPTalents\Collector\Changeset_Collector',
		'retrieve_data',
	) );

	add_action( 'wptalents_collect_forums', array(
		'WPTalents\Collector\Forums_Collector',
		'retrieve_data',
	) );

	add_action( 'wptalents_collect_gravatar', array(
		'WPTalents\Collector\Gravatar_Collector',
		'retrieve_data',
	) );

	add_action( 'wptalents_collect_plugins', array(
		'WPTalents\Collector\Plugin_Collector',
		'retrieve_data',
	) );

	add_action( 'wptalents_collect_score', array(
		'WPTalents\Collector\Score_Collector',
		'retrieve_data',
	) );

	add_action( 'wptalents_collect_themes', array(
		'WPTalents\Collector\Theme_Collector',
		'retrieve_data',
	) );

	add_action( 'wptalents_collect_wordpressorg', array(
		'WPTalents\Collector\Profile_Collector',
		'retrieve_data',
	) );

	add_action( 'wptalents_collect_wpse', array(
		'WPTalents\Collector\Stack_Exchange_Collector',
		'retrieve_data',
	) );

	add_action( 'wptalents_collect_wordpresstv', array(
		'WPTalents\Collector\WordPressTv_Collector',
		'retrieve_data',
	) );
}

unset( $wptalents_requirements_check );
