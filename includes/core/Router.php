<?php

namespace WPTalents\Core;

/**
 * Class Router
 * @package WPTalents\Core
 */
class Router {

	/**
	 *
	 */
	public function __construct() {

		add_filter( 'query_vars', array( $this, 'filter_query_vars' ) );

		add_filter( 'request', array( $this, 'filter_request' ) );

		add_filter( 'template_include', array( $this, 'template_include' ) );

		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );

	}

	/**
	 * Filters query vars to add our own talent var.
	 *
	 * @param  array $query_vars The current query vars array.
	 *
	 * @return array             The modified query vars.
	 */
	public function filter_query_vars( $query_vars ) {

		$query_vars[] = 'talent';

		return $query_vars;

	}

	/**
	 * Filters the current request to modify query vars.
	 *
	 * @todo Make this more flexible by using the types registered in the plugin.
	 *
	 * @param array $query_vars
	 *
	 * @return array $query_vars
	 */
	public static function filter_request( $query_vars ) {

		if ( is_admin() || ! isset( $query_vars['talent'] ) ) {
			return $query_vars;
		}

		if ( 'talents' === $query_vars['talent'] ) {
			// Talents Archive, return early

			$query_vars['post_type'] = 'company';

			unset( $query_vars['talent'] );

			return $query_vars;
		}

		$query_vars['name'] = $query_vars['talent'];

		$post = Helper::post_exists( $query_vars['talent'], array( 'company', 'person', 'page', 'post' ) );

		if ( null === $post ) {
			// Fallback
			$query_vars['post_type'] = 'post';

		} else {
			$query_vars['post_type'] = $post->post_type;

			if ( 'page' === $post->post_type ) {
				$query_vars['pagename'] = $query_vars['talent'];
				unset( $query_vars['name'] );
			}
		}

		// Unset the unused query var
		unset( $query_vars['talent'] );

		return $query_vars;

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

		return $template;

	}

	/**
	 * @param \WP_Query $query
	 */
	public function pre_get_posts( $query ) {

		if ( ! $query->is_main_query() || is_admin() ) {
			return;
		}

		$post_types = apply_filters( 'wptalents_archive_post_types', array() );

		if ( is_post_type_archive( 'company' ) ) {
			$query->set( 'post_type', $post_types );
			$query->set( 'orderby', 'name' );
			$query->set( 'order', 'ASC' );
		}

	}

} 