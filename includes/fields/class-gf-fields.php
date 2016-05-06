<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

require_once( plugin_dir_path( __FILE__ ) . 'class-gf-field.php' );

class GF_Fields {

	public static $deprecation_notice_fired = false;

	/* @var GF_Field[] */
	private static $_fields = array();

	public static function register( $field ) {
		if ( ! is_subclass_of( $field, 'GF_Field' ) ) {
			throw new Exception( 'Must be a subclass of GF_Field' );
		}
		if ( empty( $field->type ) ) {
			throw new Exception( 'The type must be set' );
		}
		if ( isset( self::$_fields[ $field->type ] ) ) {
			throw new Exception( 'Field type already registered: ' . $field->type );
		}
		self::$_fields[ $field->type ] = $field;
	}

	public static function exists( $field_type ) {
		return isset( self::$_fields[ $field_type ] );
	}

	/**
	 * @param $field_type
	 *
	 * @return GF_Field
	 */
	public static function get_instance( $field_type ) {
		return isset( self::$_fields[ $field_type ] ) ? self::$_fields[ $field_type ] : false;
	}

	/**
	 * Alias for get_instance()
	 *
	 * @param $field_type
	 *
	 * @return GF_Field
	 */
	public static function get( $field_type ) {
		return self::get_instance($field_type);
	}

	/**
	 * @return GF_Field[]
	 */
	public static function get_all() {
		return self::$_fields;
	}

	/**
	 * @param $properties
	 *
	 * @return GF_Field | bool
	 */
	public static function create( $properties ) {
		$type = isset($properties['type']) ? $properties['type'] : '';
		$type = empty( $properties['inputType'] ) ? $type : $properties['inputType'];
		if ( empty($type) || ! isset( self::$_fields[ $type ] ) ) {
			return new GF_Field( $properties );
		}
		$class      = self::$_fields[ $type ];
		$class_name = get_class( $class );
		$field      = new $class_name( $properties );

		/**
		 * Filter the GF_Field object after it is created.
		 *
		 * @since  1.9.18.2
		 *
		 * @param  GF_Field $field      A GF_Field object.
		 * @param  array    $properties An array of field properties used to generate the GF_Field object.
		 * @see    https://www.gravityhelp.com/documentation/article/gform_gf_field_create/
		 */
		return apply_filters( 'gform_gf_field_create', $field, $properties );

	}
}

// load all the field files automatically
foreach ( glob( plugin_dir_path( __FILE__ ) . 'class-gf-field-*.php' ) as $gf_field_filename ) {
	require_once( $gf_field_filename );
}
