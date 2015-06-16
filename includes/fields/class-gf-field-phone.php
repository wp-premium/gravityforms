<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_Phone extends GF_Field {

	public $type = 'phone';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Phone', 'gravityforms' );
	}

	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'size_setting',
			'rules_setting',
			'visibility_setting',
			'duplicate_setting',
			'default_value_setting',
			'placeholder_setting',
			'description_setting',
			'phone_format_setting',
			'css_class_setting',
		);
	}

	public function is_conditional_logic_supported() {
		return true;
	}

	public function validate( $value, $form ) {
		$regex = '/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/';
		if ( $this->phoneFormat == 'standard' && $value !== '' && $value !== 0 && ! preg_match( $regex, $value ) ) {
			$this->failed_validation = true;
			if ( ! empty( $this->errorMessage ) ) {
				$this->validation_message = $this->errorMessage;
			}
		}
	}

	public function get_field_input( $form, $value = '', $entry = null ) {

		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$form_id  = $form['id'];
		$id       = intval( $this->id );
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$size          = $this->size;
		$disabled_text = $is_form_editor ? "disabled='disabled'" : '';
		$class_suffix  = $is_entry_detail ? '_admin' : '';
		$class         = $size . $class_suffix;

		$instruction           = $this->phoneFormat == 'standard' ? esc_html__( 'Phone format:', 'gravityforms' ) . ' (###) ###-####' : '';
		$instruction_div       = $this->failed_validation && ! empty( $instruction ) ? "<div class='instruction validation_message'>$instruction</div>" : '';
		$html_input_type       = RGFormsModel::is_html5_enabled() ? 'tel' : 'text';
		$logic_event           = $this->get_conditional_logic_event( 'keyup' );
		$placeholder_attribute = $this->get_field_placeholder_attribute();

		$tabindex = $this->get_tabindex();

		return sprintf( "<div class='ginput_container'><input name='input_%d' id='%s' type='{$html_input_type}' value='%s' class='%s' {$tabindex} {$logic_event} {$placeholder_attribute} %s/>{$instruction_div}</div>", $id, $field_id, esc_attr( $value ), esc_attr( $class ), $disabled_text );

	}

	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {

		if ( $this->phoneFormat == 'standard' && preg_match( '/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/', $value, $matches ) ) {
			$value = sprintf( '(%s) %s-%s', $matches[1], $matches[2], $matches[3] );
		}

		return $value;
	}

	public function get_form_inline_script_on_page_render( $form ) {
		$script = '';
		if ( $this->phoneFormat == 'standard' ) {
			$script = "if(!/(android)/i.test(navigator.userAgent)){jQuery('#input_{$form['id']}_{$this->id}').mask('(999) 999-9999').bind('keypress', function(e){if(e.which == 13){jQuery(this).blur();} } );}";
		}
		return $script;
	}

	public function sanitize_settings() {
		parent::sanitize_settings();

		if ( $this->phoneFormat && ! in_array( $this->phoneFormat, array( 'standard', 'international' ) ) ) {
			$this->phoneFormat = 'standard';
		}
	}


}

GF_Fields::register( new GF_Field_Phone() );