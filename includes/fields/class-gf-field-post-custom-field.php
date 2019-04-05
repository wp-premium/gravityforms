<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Field_Post_Custom_Field extends GF_Field {

	public $type = 'post_custom_field';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Custom Field', 'gravityforms' );
	}

	function get_form_editor_field_settings() {
		return array(
			'post_custom_field_type_setting',
			'post_custom_field_setting',
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
			'description_setting',
			'css_class_setting',
		);
	}

	public function is_conditional_logic_supported() {
		return true;
	}

	public function get_field_input( $form, $value = '', $entry = null ) {
		$form_id         = absint( $form['id'] );
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$id       = (int) $this->id;
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$value        = esc_attr( $value );
		$size         = $this->size;
		$class_suffix = $is_entry_detail ? '_admin' : '';
		$class        = $size . $class_suffix;
		$class        = esc_attr( $class );

		$disabled_text = $is_form_editor ? 'disabled="disabled"' : '';

		$tabindex              = $this->get_tabindex();
		$placeholder_attribute = $this->get_field_placeholder_attribute();
		$required_attribute    = $this->isRequired ? 'aria-required="true"' : '';
		$invalid_attribute     = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';
		$aria_describedby      = $this->get_aria_describedby();

		return "<div class='ginput_container ginput_container_post_custom_field'>
					<input name='input_{$id}' id='{$field_id}' type='text' value='{$value}' class='{$class}' {$tabindex} {$placeholder_attribute} {$disabled_text} {$required_attribute} {$invalid_attribute} {$aria_describedby} />
				</div>";
	}

	public function allow_html() {
		return true;
	}
}

GF_Fields::register( new GF_Field_Post_Custom_Field() );