<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * @deprecated since 1.6
 *
 * Class GF_Field_Donationç
 */
class GF_Field_Donation extends GF_Field {

	public $type = 'donation';

	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'donation_field_type_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'label_setting',
			'admin_label_setting',
			'label_placement_setting',
			'rules_setting',
			'default_value_setting',
			'placeholder_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	public function get_form_editor_button() {
		return array();
	}

	public function validate( $value, $form ) {
		if ( ! class_exists( 'RGCurrency' ) ) {
			require_once( GFCommon::get_base_path() . '/currency.php' );
		}

		$price = GFCommon::to_number( $value );
		if ( ! rgblank( $value ) && ( $price === false || $price < 0 ) ) {
			$this->failed_validation  = true;
			$this->validation_message = empty( $this->errorMessage ) ? esc_html__( 'Please enter a valid amount.', 'gravityforms' ) : $this->errorMessage;
		}
	}


	public function get_field_input( $form, $value = '', $entry = null ) {
		$form_id         = absint( $form['id'] );
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$id       = absint( $this->id );
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$value = esc_attr( $value );

		$placeholder_attribute = $this->get_field_placeholder_attribute();
		$required_attribute    = $this->isRequired ? 'aria-required="true"' : '';
		$invalid_attribute     = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';

		$size         = $this->size;
		$class_suffix = $is_entry_detail ? '_admin' : '';
		$class        = $size . $class_suffix;
		$class        = esc_attr( $class );

		$disabled_text = $is_form_editor ? 'disabled="disabled"' : '';

		$tabindex = $this->get_tabindex();

		return "<div class='ginput_container ginput_container_donation'>
					<input name='input_{$id}' id='{$field_id}' type='text' value='{$value}' class='{$class} ginput_donation_amount' {$tabindex} {$placeholder_attribute} {$required_attribute} {$invalid_attribute} {$disabled_text}/>
				</div>";

	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		return GFCommon::to_money( $value, $currency );
	}

}

GF_Fields::register( new GF_Field_Donation() );