<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_Price extends GF_Field {

	public $type = 'price';

	function get_form_editor_field_settings() {
		return array(
			'rules_setting',
			'conditional_logic_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'placeholder_setting',
			'size_setting',
			'duplicate_setting',
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
			$this->validation_message = empty( $this->errorMessage ) ? __( 'Please enter a valid amount.', 'gravityforms' ) : $this->errorMessage;
		}
	}

	public function get_field_input( $form, $value = '', $entry = null ) {
		$form_id         = absint( $form['id'] );
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$id       = (int) $this->id;
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$value = esc_attr( $value );

		$logic_event           = $this->get_conditional_logic_event( 'keyup' );
		$placeholder_attribute = $this->get_field_placeholder_attribute();

		$size         = $this->size;
		$class_suffix = $is_entry_detail ? '_admin' : '';
		$class        = $size . $class_suffix;
		$class        = esc_attr( $class );

		$disabled_text = $is_form_editor ? 'disabled="disabled"' : '';

		$tabindex = $this->get_tabindex();

		return "<div class='ginput_container ginput_container_product_price'>
					<input name='input_{$id}' id='{$field_id}' type='text' value='{$value}' class='{$class} ginput_amount' {$tabindex} {$logic_event} {$placeholder_attribute} {$disabled_text}/>
				</div>";


	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		return GFCommon::to_money( $value, $currency );
	}


}

GF_Fields::register( new GF_Field_Price() );