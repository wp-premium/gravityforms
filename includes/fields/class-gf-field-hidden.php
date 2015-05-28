<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_Hidden extends GF_Field {

	public $type = 'hidden';

	public function get_form_editor_field_title() {
		return __( 'Hidden', 'gravityforms' );
	}

	public function is_conditional_logic_supported(){
		return true;
	}

	function get_form_editor_field_settings() {
		return array(
			'prepopulate_field_setting',
			'label_setting',
			'default_value_setting',
		);
	}

	public function get_field_input( $form, $value = '', $entry = null ) {
		$form_id         = $form['id'];
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$id          = (int) $this->id;
		$field_id    = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$disabled_text         = $is_form_editor ? 'disabled="disabled"' : '';

		$field_type      = $is_entry_detail || $is_form_editor ? 'text' : 'hidden';
		$class_attribute = $is_entry_detail || $is_form_editor ? '' : "class='gform_hidden'";

		return sprintf( "<input name='input_%d' id='%s' type='$field_type' {$class_attribute} value='%s' %s/>", $id, $field_id, esc_attr( $value ), $disabled_text );
	}

	public function get_field_content( $value, $force_frontend_label, $form ) {
		$form_id         = $form['id'];
		$admin_buttons   = $this->get_admin_buttons();
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$is_admin        = $is_entry_detail || $is_form_editor;
		$field_label     = $this->get_field_label( $force_frontend_label, $value );
		$field_id        = $is_admin || $form_id == 0 ? "input_{$this->id}" : 'input_' . $form_id . "_{$this->id}";
		$field_content   = ! $is_admin ? '{FIELD}' : $field_content = sprintf( "%s<label class='gfield_label' for='%s'>%s</label>{FIELD}", $admin_buttons, $field_id, esc_html( $field_label ) );

		return $field_content;
	}


}

GF_Fields::register( new GF_Field_Hidden() );