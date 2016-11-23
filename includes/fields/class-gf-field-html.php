<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Field_HTML extends GF_Field {

	public $type = 'html';

	public function get_form_editor_field_title() {
		return esc_attr__( 'HTML', 'gravityforms' );
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
															<i class='fa fa-code fa-lg'></i> " . esc_html__( 'HTML Content', 'gravityforms' ) .
															'</span><span>' . esc_html__( 'This is a content placeholder. HTML content is not displayed in the form admin. Preview this form to view the content.', 'gravityforms' ) . '</span></div>'
														: $this->content;
		$content = GFCommon::replace_variables_prepopulate( $content ); // adding support for merge tags

		// adding support for shortcodes
		$content = $this->do_shortcode( $content );

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
		$this->content = GFCommon::maybe_wp_kses( $this->content );
	}

	public function do_shortcode( $content ){

		if( isset($GLOBALS['wp_embed']) ) {
			// adds support for the [embed] shortcode
			$content = $GLOBALS['wp_embed']->run_shortcode( $content );
		}
		// executes all other shortcodes
		$content = do_shortcode( $content );

		return $content;
	}
}

GF_Fields::register( new GF_Field_HTML() );
