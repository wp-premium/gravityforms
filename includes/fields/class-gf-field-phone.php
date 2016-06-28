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
		$phone_format = $this->get_phone_format();

		if ( rgar( $phone_format, 'regex' ) && $value !== '' && $value !== 0 && ! preg_match( $phone_format['regex'], $value ) ) {
			$this->failed_validation = true;
			if ( ! empty( $this->errorMessage ) ) {
				$this->validation_message = $this->errorMessage;
			}
		}
	}

	/**
	 * Returns the field input.
	 *
	 * @param array $form
	 * @param string $value
	 * @param null|array $entry
	 *
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {

		if ( is_array( $value ) ) {
			$value = '';
		}

		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$form_id  = $form['id'];
		$id       = intval( $this->id );
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$size          = $this->size;
		$disabled_text = $is_form_editor ? "disabled='disabled'" : '';
		$class_suffix  = $is_entry_detail ? '_admin' : '';
		$class         = $size . $class_suffix;

		$instruction_div = '';
		if ( $this->failed_validation ) {
			$phone_format = $this->get_phone_format();
			if ( rgar( $phone_format, 'instruction' ) ) {
				$instruction_div = sprintf( "<div class='instruction validation_message'>%s %s</div>", esc_html__( 'Phone format:', 'gravityforms' ), $phone_format['instruction'] );
			}
		}

		$html_input_type       = RGFormsModel::is_html5_enabled() ? 'tel' : 'text';
		$logic_event           = $this->get_conditional_logic_event( 'keyup' );
		$placeholder_attribute = $this->get_field_placeholder_attribute();
		$required_attribute    = $this->isRequired ? 'aria-required="true"' : '';
		$invalid_attribute     = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';

		$tabindex = $this->get_tabindex();

		return sprintf( "<div class='ginput_container ginput_container_phone'><input name='input_%d' id='%s' type='{$html_input_type}' value='%s' class='%s' {$tabindex} {$logic_event} {$placeholder_attribute} {$required_attribute} {$invalid_attribute} %s/>{$instruction_div}</div>", $id, $field_id, esc_attr( $value ), esc_attr( $class ), $disabled_text );

	}

	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {

		$value = parent::get_value_submission( $field_values, $get_from_post_global_var );
		$value = $this->sanitize_entry_value( $value, $this->formId );

		return $value;
	}

	public function sanitize_entry_value( $value, $form_id ) {
		$value = is_array( $value ) ? '' : sanitize_text_field( $value );
		return $value;
	}

	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		$value = $this->sanitize_entry_value( $value, $form['id'] );

		if ( $this->phoneFormat == 'standard' && preg_match( '/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/', $value, $matches ) ) {
			$value = sprintf( '(%s) %s-%s', $matches[1], $matches[2], $matches[3] );
		}

		return $value;
	}

	public function get_form_inline_script_on_page_render( $form ) {
		$script       = '';
		$phone_format = $this->get_phone_format();

		if ( rgar( $phone_format, 'mask' ) ) {
			$script = "if(!/(android)/i.test(navigator.userAgent)){jQuery('#input_{$form['id']}_{$this->id}').mask('{$phone_format['mask']}').bind('keypress', function(e){if(e.which == 13){jQuery(this).blur();} } );}";
		}
		return $script;
	}

	public function sanitize_settings() {
		parent::sanitize_settings();

		if ( ! $this->get_phone_format() ) {
			$this->phoneFormat = 'standard';
		}
	}

	/**
	 * Get an array of phone formats.
	 * 
	 * @param null|int $form_id The ID of the current form or null to use the value from the current fields form_id property.
	 *
	 * @return array
	 */
	public function get_phone_formats( $form_id = null ) {

		if ( empty( $form_id ) ) {
			$form_id = $this->form_id;
		}
		$form_id = absint( $form_id );

		$phone_formats = array(
			'standard'      => array(
				'label'       => '(###) ###-####',
				'mask'        => '(999) 999-9999',
				'regex'       => '/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/',
				'instruction' => '(###) ###-####',
			),
			'international' => array(
				'label'       => __( 'International', 'gravityforms' ),
				'mask'        => false,
				'regex'       => false,
				'instruction' => false,
			),
		);

		/**
		 * Allow custom phone formats to be defined.
		 *
		 * @param array $phone_formats The phone formats.
		 * @param int $form_id The ID of the current form.
		 */
		$phone_formats = apply_filters( 'gform_phone_formats', $phone_formats, $form_id );

		return apply_filters( 'gform_phone_formats_' . $form_id, $phone_formats, $form_id );
	}

	/**
	 * Get the properties for the fields selected phone format.
	 * 
	 * @return array
	 */
	public function get_phone_format() {
		$phone_formats = $this->get_phone_formats();

		return rgar( $phone_formats, $this->phoneFormat );
	}
}

GF_Fields::register( new GF_Field_Phone() );