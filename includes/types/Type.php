<?php

namespace WPTalents\Types;

/**
 * Interface Type
 * @package WPTalents\Types
 */
interface Type {

	/**
	 * @return void
	 */
	public function register_post_type();

	/**
	 * @return void
	 */
	public function register_taxonomy();

	/**
	 * @param array $meta_boxes
	 *
	 * @return mixed
	 */
	public function add_meta_boxes( array $meta_boxes );

	/**
	 * @param array $classes
	 *
	 * @return array
	 */
	public function filter_body_class( array $classes );

	/**
	 * @param array $classes
	 *
	 * @return array
	 */
	public function filter_post_class( array $classes );

} 