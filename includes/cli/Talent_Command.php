<?php
/**
 * Created by PhpStorm.
 * User: Pascal
 * Date: 19.12.14
 * Time: 09:50
 */

namespace WPTalents\CLI;

use WPTalents\Core\Importer;
use \WP_CLI;

class Talent_Command extends \WP_CLI_Command {

	/**
	 * Import a talent.
	 *
	 * ## OPTIONS
	 *
	 * <username>
	 * : The talent's WordPress.org username
	 *
	 * ## EXAMPLES
	 *
	 * wp talent add johndoe --name=John Doe --type=person
	 * wp talent add automattic --name=Automattic --type=company
	 *
	 * @synopsis <username> [--name=<username>] [--type=<person>]
	 */
	function add( $args, $assoc_args ) {
		list( $username ) = $args;

		$defaults = array(
			'type' => 'person',
			'name' => $username,
		);

		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		$importer = new Importer( $username, $assoc_args['name'], $assoc_args['type'] );
		WP_CLI::line( 'Importing ' . $username . '…' );

		$result = $importer->import();

		if ( is_wp_error( $result ) ) {
			/** @var \WP_Error $status */
			WP_CLI::warning( $result->get_error_message() );
		} else {
			WP_CLI::success( sprintf( 'Successfully imported %s. Post ID: %s', $username, $result ) );
		}
	}

	/**
	 * Update the talent's data
	 *
	 * ## OPTIONS
	 *
	 * <username>
	 * : The talent's WordPress.org username
	 *
	 * ## EXAMPLES
	 *
	 * wp talent update johndoe --force-update
	 * wp talent update automattic
	 *
	 * @synopsis <username> [--force-update]
	 */
	function update( $args, $assoc_args ) {
		list( $username ) = $args;

		$defaults = array(
			'force-update' => false,
		);

		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		$query = new \WP_Query( array(
			'post_type'      => array( 'person', 'company' ),
			'post_status'    => 'any',
			'meta_key'       => 'wordpress-username',
			'meta_value'     => $username,
			'posts_per_page' => 1,
		) );

		if ( ! $query->have_posts() ) {
			WP_CLI::warning( __( 'Talent does not exist!', 'wptalents' ) );

			return;
		}

		$post = $query->posts[0];

		WP_CLI::line( sprintf( __( 'Updating %s (ID: %d)…', 'wptalents' ), $post->post_title, $post->ID ) );

		$collectors = array(
			'Changeset_Collector',
			'Codex_Collector',
			'Contribution_Collector',
			'Forums_Collector',
			'Gravatar_Collector',
			'Plugin_Collector',
			'Profile_Collector',
			'Score_Collector',
			'Theme_Collector',
			'WordPressTv_Collector',
		);

		foreach ( $collectors as $collector ) {
			$collector = '\\WPTalents\\Collector\\' . $collector;
			$collector = new $collector( $post );

			if ( true === $assoc_args['force-update'] ) {
				$collector->_retrieve_data();
			}
		}

		WP_CLI::success( __( 'Talent successfully updated!', 'wptalents' ) );
	}

}

WP_CLI::add_command( 'talent', 'WPTalents\CLI\Talent_Command' );