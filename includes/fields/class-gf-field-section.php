<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Field_Section extends GF_Field {

	public $type = 'section';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Section', 'gravityforms' );
	}

	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'label_setting',
			'description_setting',
			'visibility_setting',
			'css_class_setting',
		);
	}

	public function get_field_content( $value, $force_frontend_label, $form ) {

		$field_label = $this->get_field_label( $force_frontend_label, $value );

		$admin_buttons = $this->get_admin_buttons();

		$description   = $this->get_description( $this->description, 'gsection_description' );
		$field_content = sprintf( "%s<h2 class='gsection_title'>%s</h2>%s", $admin_buttons, esc_html( $field_label ), $description );

		return $field_content;
	}

}

GF_Fields::register( new GF_Field_Section() );