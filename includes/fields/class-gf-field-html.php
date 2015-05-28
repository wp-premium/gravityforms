<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Field_HTML extends GF_Field {

	public $type = 'html';

	public function get_form_editor_field_title() {
		return __( 'HTML', 'gravityforms' );
	}

	function get_form_editor_field_settings() {
		return array(
			'content_setting',
			'disable_margins_setting',
			'conditional_logic_field_setting',
			'label_setting',
			'css_class_setting',
		);
	}

	public function get_field_input( $form, $value = '', $entry = null ) {

		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$content = $is_entry_detail || $is_form_editor ? "<div class='gf-html-container'><span class='gf_blockheader'>
															<i class='fa fa-code fa-lg'></i> " . __( 'HTML Content', 'gravityforms' ) .
															'</span><span>' . __( 'This is a content placeholder. HTML content is not displayed in the form admin. Preview this form to view the content.', 'gravityforms' ) . '</span></div>'
														: $this->content;
		$content = GFCommon::replace_variables_prepopulate( $content ); // adding support for merge tags
		$content = do_shortcode( $content ); // adding support for shortcodes
		return $content;
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

	public function sanitize_settings() {
		parent::sanitize_settings();
		$allowed_tags  = wp_kses_allowed_html( 'post' );
		$this->content = wp_kses( $this->content, $allowed_tags );
	}
}

GF_Fields::register( new GF_Field_HTML() );