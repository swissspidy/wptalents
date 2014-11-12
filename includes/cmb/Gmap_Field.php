<?php
/**
 * @package WP_Talents
 * @subpackge CMB
 */

namespace WPTalents\CMB;

if ( ! class_exists( '\CMB_Gmap_Field' ) ) {
	return;
}

/**
 * Google map field class for CMB
 *
 * Extends the default field to also store the place name
 *
 * @see CMB_Gmap_Field
 *
 */
class Gmap_Field extends \CMB_Gmap_Field {
	/**
	 * Return the default args for the Map field.
	 *
	 * @return array $args
	 */
	public function get_default_args() {
		return array_merge(
			parent::get_default_args(),
			array(
				'field_width'         => '100%',
				'field_height'        => '250px',
				'default_lat'         => '47.3686498',
				'default_long'        => '8.53918250',
				'default_zoom'        => '8',
				'string-marker-title' => __( 'Drag to set the exact location', 'wptalents' ),
			)
		);
	}

	public function enqueue_scripts() {

		parent::enqueue_scripts();

		wp_deregister_script( 'cmb-google-maps-script' );
		wp_enqueue_script( 'cmb-google-maps-script', trailingslashit( WP_TALENTS_URL ) . 'js/field-gmap.js', array( 'jquery', 'cmb-google-maps' ) );

		wp_localize_script( 'cmb-google-maps-script', 'CMBGmaps', array(
			'defaults' => array(
				'latitude'  => $this->args['default_lat'],
				'longitude' => $this->args['default_long'],
				'zoom'      => $this->args['default_zoom'],
			),
			'strings'  => array(
				'markerTitle' => $this->args['string-marker-title']
			)
		) );

	}

	public function html() {
		/**
		 * Ensure all args used are set
		 */

		$value = $this->get_value();

		/**
		 * Backwards Compatibility with earlier version
		 * where location was stored as a string
		 */
		$location = ( is_string( $value ) && ! empty( $value ) ) ? $value : null;
		( is_string( $value) ) && $value = array();

		$value = wp_parse_args(
			$value,
			array( 'lat' => null, 'long' => null, 'elevation' => null, 'name' => $location )
		);

		$style = array(
			sprintf( 'width: %s;', $this->args['field_width'] ),
			sprintf( 'height: %s;', $this->args['field_height'] ),
			'border: 1px solid #eee;',
			'margin-top: 8px;'
		);
		?>

		<input type="text" <?php $this->class_attr( 'map-search' ); ?> <?php $this->id_attr(); ?> <?php $this->name_attr( '[name]' ); ?> value="<?php echo esc_attr( $value['name'] ); ?>" />

		<div class="map" style="<?php echo esc_attr( implode( ' ', $style ) ); ?>"></div>

		<input type="hidden" <?php $this->class_attr( 'latitude' ); ?>  <?php $this->name_attr( '[lat]' ); ?>       value="<?php echo esc_attr( $value['lat'] ); ?>" />
		<input type="hidden" <?php $this->class_attr( 'longitude' ); ?> <?php $this->name_attr( '[long]' ); ?>      value="<?php echo esc_attr( $value['long'] ); ?>" />
		<input type="hidden" <?php $this->class_attr( 'elevation' ); ?> <?php $this->name_attr( '[elevation]' ); ?> value="<?php echo esc_attr( $value['elevation'] ); ?>" />

	<?php
	}
}