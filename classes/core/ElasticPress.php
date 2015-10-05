<?php
/**
 * Customizing ElasticPress
 * @package WPTalents
 */

namespace WPTalents\Core;

use WPTalents\Lib\WP_Stack_Plugin2;

/**
 * Class ElasticPress
 *
 * @package WPTalents\Core
 */
class ElasticPress extends WP_Stack_Plugin2 {
	/**
	 * Instance of this class.
	 *
	 * @var self
	 */
	protected static $instance;

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
		// Extend ElasticPress.
		$this->hook( 'ep_config_mapping' );

		// Syncing.
		$this->hook( 'bp_core_activated_user', 'sync_on_update', 20 );
		$this->hook( 'deleted_user' );

		foreach (
			array(
				'bbpress',
				'buddypress',
				'changesets',
				'codex',
				'forums',
				'plugins',
				'score',
				'themes',
				'wordpressorg',
				'wpse',
				'wordpresstv',
			) as $type
		) {
			add_action( 'wptalents_collect_' . $type, array( $this, 'sync_on_update' ), 20 );
		}
	}

	/**
	 * Delete user from the index after its removal from the database.
	 *
	 * Runs on the hook with the same name.
	 *
	 * @param int $user_id User ID.
	 */
	public function deleted_user( $user_id ) {
		do_action( 'ep_delete_user', $user_id );

		$this->delete_user( $user_id );
	}

	/**
	 * Delete a user from the index.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool
	 */
	public function delete_user( $user_id ) {
		$index = trailingslashit( ep_get_index_name() );

		$path = $index . '/user/' . $user_id;

		$request_args = array( 'method' => 'DELETE', 'timeout' => 15 );

		$request = ep_remote_request( $path, apply_filters( 'ep_delete_user_request_args', $request_args, $user_id ) );

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			$response = json_decode( $response_body, true );

			if ( ! empty( $response['found'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sync ES index with what happened to the post being saved
	 *
	 * @param int $user_id User ID.
	 */
	public function sync_on_update( $user_id ) {
		global $importer;

		// If we have an importer we must be doing an import - let's abort.
		if ( $importer ) {
			return;
		}

		$this->sync_user( $user_id );
	}

	/**
	 * Sync a user.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool|array
	 */
	public function sync_user( $user_id ) {
		$args = wptalents_ep_prepare_user( $user_id );

		$response = ep_index_post( $args );

		return $response;
	}

	/**
	 * Extend the mapping used by Elasticsearch.
	 *
	 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
	 *
	 * @param array $mapping Elasticsearch mapping.
	 *
	 * @return array
	 */
	public function ep_config_mapping( $mapping ) {
		$mapping['user'] = array(
			'date_detection'    => false,
			'dynamic_templates' => array(
				array(
					'template_badges' => array(
						'path_match' => 'badges.*',
						'mapping'    => array(
							'type'       => 'object',
							'path'       => 'full',
							'properties' => array(
								'name' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
				array(
					'template_changesets' => array(
						'path_match' => 'changesets.*',
						'mapping'    => array(
							'type'       => 'object',
							'path'       => 'full',
							'properties' => array(
								'changeset'   => array(
									'type' => 'long',
								),
								'ticket'      => array(
									'type' => 'long',
								),
								'description' => array(
									'type'  => 'string',
									'index' => 'not_analyzed',
								),
							),
						),
					),
				),
				array(
					'template_plugins' => array(
						'path_match' => 'plugins.*',
						'mapping'    => array(
							'type'       => 'object',
							'path'       => 'full',
							'properties' => array(
								'active_installs'   => array(
									'type' => 'integer',
								),
								'banner'            => array(
									'type' => 'string',
								),
								'contributors'      => array(
									'type' => 'string',
								),
								'icon'              => array(
									'type' => 'string',
								),
								'last_updated'      => array(
									'type' => 'date_hour_minute_second',
								),
								'name'              => array(
									'type' => 'string',
								),
								'rating'            => array(
									'type' => 'float',
								),
								'short_description' => array(
									'type' => 'string',
								),
								'slug'              => array(
									'type' => 'string',
								),
								'tested'            => array(
									'type' => 'string',
								),
								'version'           => array(
									'type' => 'string',
								),
							),
						),
					),
				),
				array(
					'template_favorite_plugins' => array(
						'path_match' => 'favorite_plugins.*',
						'mapping'    => array(
							'type'       => 'object',
							'path'       => 'full',
							'properties' => array(
								'active_installs'   => array(
									'type' => 'integer',
								),
								'banner'            => array(
									'type' => 'string',
								),
								'contributors'      => array(
									'type' => 'string',
								),
								'icon'              => array(
									'type' => 'string',
								),
								'last_updated'      => array(
									'type' => 'date_hour_minute_second',
								),
								'name'              => array(
									'type' => 'string',
								),
								'rating'            => array(
									'type' => 'float',
								),
								'short_description' => array(
									'type' => 'string',
								),
								'slug'              => array(
									'type' => 'string',
								),
								'tested'            => array(
									'type' => 'string',
								),
								'version'           => array(
									'type' => 'string',
								),
							),
						),
					),
				),
				array(
					'template_themes' => array(
						'path_match' => 'themes.*',
						'mapping'    => array(
							'type'       => 'object',
							'path'       => 'full',
							'properties' => array(
								'description'    => array(
									'type' => 'integer',
								),
								'downloaded'     => array(
									'type' => 'string',
								),
								'last_updated'   => array(
									'type' => 'date_hour_minute_second',
								),
								'name'           => array(
									'type' => 'string',
								),
								'rating'         => array(
									'type' => 'float',
								),
								'screenshot_url' => array(
									'type' => 'string',
								),
								'slug'           => array(
									'type' => 'string',
								),
								'version'        => array(
									'type' => 'string',
								),
							),
						),
					),
				),
				array(
					'template_favorite_themes' => array(
						'path_match' => 'favorite_themes.*',
						'mapping'    => array(
							'type'       => 'object',
							'path'       => 'full',
							'properties' => array(
								'description'    => array(
									'type' => 'integer',
								),
								'downloaded'     => array(
									'type' => 'string',
								),
								'last_updated'   => array(
									'type' => 'date_hour_minute_second',
								),
								'name'           => array(
									'type' => 'string',
								),
								'rating'         => array(
									'type' => 'float',
								),
								'screenshot_url' => array(
									'type' => 'string',
								),
								'slug'           => array(
									'type' => 'string',
								),
								'version'        => array(
									'type' => 'string',
								),
							),
						),
					),
				),
				array(
					'template_wpseo' => array(
						'path_match' => 'wpse.*',
						'mapping'    => array(
							'type'       => 'object',
							'path'       => 'full',
							'properties' => array(
								'badge_counts'  => array(
									'type' => 'integer',
								),
								'creation_date' => array(
									'type' => 'date_hour_minute_second',
								),
								'reputation'    => array(
									'type' => 'integer',
								),
								'user_id'       => array(
									'type' => 'integer',
								),
							),
						),
					),
				),
				array(
					'template_xprofile' => array(
						'path_match' => 'xprofile.*',
						'mapping'    => array(
							'type'       => 'object',
							'path'       => 'full',
							// XProfile Fields with IDs.
							'properties' => array(
								// Website.
								'field_2'  => array(
									'type' => 'string',
								),
								// Twitter.
								'field_3'  => array(
									'type' => 'string',
								),
								// Facebook.
								'field_4'  => array(
									'type' => 'string',
								),
								// LinkedIn.
								'field_5'  => array(
									'type' => 'string',
								),
								// Location.
								'field_6'  => array(
									'type' => 'string',
								),
								// Job.
								'field_8'  => array(
									'type' => 'string',
								),
								// Google+.
								'field_9'  => array(
									'type' => 'string',
								),
								// Badges.
								'field_10' => array(
									'type' => 'string',
								),
								// Slack.
								'field_18' => array(
									'type' => 'string',
								),
								// WordPress.com VIP?
								'field_19' => array(
									'type' => 'string',
								),
								// Dawn Patrol.
								'field_21' => array(
									'type' => 'string',
								),
								// Dawn Patrol Video.
								'field_22' => array(
									'type' => 'string',
								),
								// GitHub.
								'field_23' => array(
									'type' => 'string',
								),
								// Bitbucket.
								'field_27' => array(
									'type' => 'string',
								),
								// Bio.
								'field_28' => array(
									'type' => 'string',
								),
								// Member on WordPress.org since.
								'field_52' => array(
									'type' => 'date',
								),
							),
						),
					),
				),
			),
			'_all'              => array(
				'analyzer' => 'simple',
			),
			'properties'        => array(
				// Default WordPress properties.
				'user_id'                  => array(
					'type'  => 'long',
					'index' => 'not_analyzed',
				),
				'display_name'             => array(
					'type'     => 'string',
					'analyzer' => 'standard',
				),
				'user_login'               => array(
					'type'     => 'string',
					'analyzer' => 'standard',
				),
				'user_nicename'            => array(
					'type'     => 'string',
					'analyzer' => 'standard',
				),
				'user_email'               => array(
					'type'     => 'string',
					'analyzer' => 'standard',
				),
				'user_registered'          => array(
					'type'           => 'date',
					'format'         => 'YYYY-MM-dd HH:mm:ss',
					'include_in_all' => false,
				),
				'permalink'                => array(
					'type' => 'string',
				),
				// Properties specific to BuddyPress and WP Talents.
				'member_type'              => array(
					'type' => 'string',
				),
				'score'                    => array(
					'type' => 'integer',
				),
				'badges'                   => array(
					'type' => 'object',
				),
				// Forums data.
				'bbpress_replies_count'    => array(
					'type' => 'long',
				),
				'bbpress_topics_count'     => array(
					'type' => 'long',
				),
				'buddypress_replies_count' => array(
					'type' => 'long',
				),
				'buddypress_topics_count'  => array(
					'type' => 'long',
				),
				// Core contributions.
				'props_count'              => array(
					'type' => 'long',
				),
				'changesets'               => array(
					'type' => 'string',
				),
				// Plugins & Themes.
				'plugins'                  => array(
					'type' => 'string',
				),
				'favorite_plugins'         => array(
					'type' => 'string',
				),
				'themes'                   => array(
					'type' => 'string',
				),
				'favorite_themes'          => array(
					'type' => 'string',
				),
				// WordPress.tv.
				'videos'                   => array(
					'type' => 'string',
				),
				// WordPress Codex.
				'codex_count'              => array(
					'type' => 'long',
				),
				// WordPress Stack Exchange.
				'wpse'                     => array(
					'type' => 'string',
				),
				//
				// Team members and companies.
				// 'team'                     => array( 'type' => 'string', ),
				// XProfile fields.
				// Badges.
				'xprofile'                 => array(
					'type' => 'string',
				),
				// Location fields.
				'location'                 => array(
					'type' => 'geo_point',
				),
			),
		);

		return $mapping;
	}

}
