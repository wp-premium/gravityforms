<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Field_Post_Content extends GF_Field {

	public $type = 'post_content';

	public function get_form_editor_field_title() {
		return __( 'Body', 'gravityforms' );
	}

	function get_form_editor_field_settings() {
		return array(
			'post_content_template_setting',
			'post_status_setting',
			'post_category_setting',
			'post_author_setting',
			'post_format_setting',
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'size_setting',
			'maxlen_setting',
			'rules_setting',
			'visibility_setting',
			'default_value_textarea_setting',
			'placeholder_textarea_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	public function is_conditional_logic_supported(){
		return true;
	}

	public function get_field_input( $form, $value = '', $entry = null ) {

		$form_id         = $form['id'];
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$id       = (int) $this->id;
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$value        = esc_textarea( $value );
		$size         = $this->size;
		$class_suffix = $is_entry_detail ? '_admin' : '';
		$class        = $size . $class_suffix;
		$class        = esc_attr( $class );

		$disabled_text = $is_form_editor ? 'disabled="disabled"' : '';

		$tabindex = $this->get_tabindex();

		$logic_event           = $this->get_conditional_logic_event( 'keyup' );
		$placeholder_attribute = $this->get_field_placeholder_attribute();

		return "<div class='ginput_container'>
					<textarea name='input_{$id}' id='{$field_id}' class='textarea {$class}' {$tabindex} {$logic_event} {$placeholder_attribute} {$disabled_text} rows='10' cols='50'>{$value}</textarea>
				</div>";
	}

	public function allow_html(){
		return true;
	}
}

GF_Fields::register( new GF_Field_Post_Content() );