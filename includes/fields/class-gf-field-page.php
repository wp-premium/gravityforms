<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Field_Page extends GF_Field {

	public $type = 'page';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Page', 'gravityforms' );
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
		$field_content = "{$admin_buttons} <label class='gfield_label'>&nbsp;</label><div class='gf-pagebreak-inline gf-pagebreak-container'><div class='gf-pagebreak-text-before'>" . esc_html__( 'end of page', 'gravityforms' ) . "</div><div class='gf-pagebreak-text-main'><span>" . esc_html__( 'PAGE BREAK', 'gravityforms' ) . "</span></div><div class='gf-pagebreak-text-after'>" . esc_html__( 'top of new page', 'gravityforms' ) . '</div></div>';
		return $field_content;
	}

	public function sanitize_settings() {
		parent::sanitize_settings();
		if ( $this->nextButton ) {
			$this->nextButton['imageUrl'] = wp_strip_all_tags( $this->nextButton['imageUrl'] );
			$allowed_tags      = wp_kses_allowed_html( 'post' );
			$this->nextButton['text'] = wp_kses( $this->nextButton['text'], $allowed_tags );
			$this->nextButton['type'] = wp_strip_all_tags( $this->nextButton['type'] );
			if ( isset( $this->nextButton['conditionalLogic'] ) && is_array( $this->nextButton['conditionalLogic'] ) ) {
				$this->nextButton['conditionalLogic'] = $this->sanitize_settings_conditional_logic( $this->nextButton['conditionalLogic'] );
			}
		}
	}

}

GF_Fields::register( new GF_Field_Page() );