<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_Shipping extends GF_Field {

	public $type = 'shipping';

	function get_form_editor_field_settings() {
		return array(
			'shipping_field_type_setting',
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'label_setting',
			'admin_label_setting',
			'label_placement_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	public function get_form_editor_field_title() {
		return __( 'Shipping', 'gravityforms' );
	}

}

GF_Fields::register( new GF_Field_Shipping() );