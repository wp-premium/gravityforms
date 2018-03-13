<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

require_once( plugin_dir_path( __FILE__ ) . 'class-gf-field-textarea.php' );

class GF_Field_Post_Content extends GF_Field_Textarea {

	public $type = 'post_content';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Body', 'gravityforms' );
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
			'rich_text_editor_setting',
		);
	}

	public function allow_html() {
		return true;
	}

	/**
	 * Filter the rich_editing option for the current user.
	 *
	 * @since 2.2.5.14
	 *
	 * @param string $value The value of the rich_editing option for the current user.
	 *
	 * @return string
	 */
	public function filter_user_option_rich_editing( $value ) {
		return is_user_logged_in() ? $value : 'true';
	}

}

GF_Fields::register( new GF_Field_Post_Content() );
