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

/**
 * Import and update WordPress talents.
 */
class Talent_Command extends \WP_CLI_Command {

	/**
	 * Import a talent.
	 *
	 * ## OPTIONS
	 *
	 * <username>...
	 * : One or more WordPress.org usernames to import.
	 *
	 * [--name]
	 * : Overwrite the talent's full name (only works when importing 1 talent).
	 *
	 * [--type]
	 * : The talent type (person or company).
	 *
	 * ## EXAMPLES
	 *
	 * wp talent add johndoe --name=John Doe --type=person
	 * wp talent add johndoe janedoe
	 */
	public function add( $args, $assoc_args ) {
		$defaults = array(
			'type' => 'person',
			'name' => '',
		);

		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		// Only person and company are allowed values
		if ( 'person' !== $assoc_args['type'] ) {
			$assoc_args['type'] = 'company';
		}

		// Only works if provided a single username
		if ( 1 < count( $assoc_args ) ) {
			$assoc_args['name'] = '';
		}

		foreach ( $args as $talent ) {
			$this->import( $talent, $assoc_args['name'], $assoc_args['type'] );
		}
	}

	/**
	 * @param string $username WordPress.org username.
	 * @param string $name     Talent's full name.
	 * @param string $type     Talent type (person or company).
	 */
	protected function import( $username, $name, $type ) {
		$importer = new Importer( $username, $name, $type );
		WP_CLI::line( 'Importing ' . $username . '...' );

		$result = $importer->import();

		if ( is_wp_error( $result ) ) {
			/** @var \WP_Error $status */
			WP_CLI::warning( $result->get_error_message() );
		} else {
			WP_CLI::success( sprintf( 'Successfully imported %s. Post ID: %s', get_the_title( $result ), $result ) );
		}
	}

	/**
	 * Update the talent's data.
	 *
	 * ## OPTIONS
	 *
	 * <username>
	 * : The talent's WordPress.org username
	 *
	 * [--force-update]
	 * : Update even data that hasn't expired yet.
	 *
	 * ## EXAMPLES
	 *
	 * wp talent update johndoe --force-update
	 * wp talent update automattic
	 *
	 * @synopsis <username> [--force-update]
	 */
	public function update( $args, $assoc_args ) {
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

		/** @var \WP_Post $post */
		$post = $query->posts[0];

		WP_CLI::line( sprintf( __( 'Updating %s (ID: %d)...', 'wptalents' ), $post->post_title, $post->ID ) );

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

		/** @var \WPTalents\Collector\Collector $collector */
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