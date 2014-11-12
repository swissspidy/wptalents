<?php

namespace WPTalents\Types;

interface Type {

	public function register_post_type();

	public function register_taxonomy();

	public function add_meta_boxes( array $meta_boxes );

	public function filter_body_class( array $classes );

	public function filter_post_class( array $classes );

} 