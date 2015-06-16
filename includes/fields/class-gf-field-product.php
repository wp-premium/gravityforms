<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_Product extends GF_Field {

	public $type = 'product';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Product', 'gravityforms' );
	}

	function get_form_editor_field_settings() {
		return array(
			'product_field_type_setting',
			'prepopulate_field_setting',
			'label_setting',
			'admin_label_setting',
			'label_placement_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	public function get_field_input( $form, $value = '', $entry = null ) {
		return '';
	}
}

GF_Fields::register( new GF_Field_Product() );