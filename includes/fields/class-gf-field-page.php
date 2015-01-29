<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Field_Page extends GF_Field {

	public $type = 'page';

	public function get_form_editor_field_title() {
		return __( 'Page', 'gravityforms' );
	}

	function get_form_editor_field_settings() {
		return array(
			'next_button_setting',
			'previous_button_setting',
			'css_class_setting',
			'conditional_logic_page_setting',
			'conditional_logic_nextbutton_setting',
		);
	}

	public function get_field_content( $value, $force_frontend_label, $form ) {
		$admin_buttons = $this->get_admin_buttons();
		$field_content = "{$admin_buttons} <label class='gfield_label'>&nbsp;</label><div class='gf-pagebreak-inline gf-pagebreak-container'><div class='gf-pagebreak-text-before'>" . __( 'end of page', 'gravityforms' ) . "</div><div class='gf-pagebreak-text-main'><span>" . __( 'PAGE BREAK', 'gravityforms' ) . "</span></div><div class='gf-pagebreak-text-after'>" . __( 'top of new page', 'gravityforms' ) . '</div></div>';
		return $field_content;
	}


}

GF_Fields::register( new GF_Field_Page() );