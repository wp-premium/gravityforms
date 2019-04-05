<?php

// If Gravity Forms isn't loaded, bail.
if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Class GF_Field_Phone
 *
 * Handles the behavior of Phone fields.
 *
 * @since Unknown
 */
class GF_Field_Phone extends GF_Field {

	/**
	 * Defines the field type.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @var string The field type.
	 */
	public $type = 'phone';

	/**
	 * Defines the field title to be used in the form editor.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFCommon::get_field_type_title()
	 *
	 * @return string The field title. Translatable and escaped.
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Phone', 'gravityforms' );
	}

	/**
	 * Defines the field settings available within the field editor.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return array The field settings available for the field.
	 */
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

	/**
	 * Defines if conditional logic is supported in this field type.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormDetail::inline_scripts()
	 * @used-by GFFormSettings::output_field_scripts()
	 *
	 * @return bool true
	 */
	public function is_conditional_logic_supported() {
		return true;
	}

	/**
	 * Validates inputs for the Phone field.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormDisplay::validate()
	 * @uses    GF_Field_Phone::get_phone_format()
	 * @uses    GF_Field_Phone::$validation_message
	 * @uses    GF_Field_Phone::$errorMessage
	 *
	 * @param array|string $value The field value to be validated.
	 * @param array        $form  The Form Object.
	 *
	 * @return void
	 */
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
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFCommon::get_field_input()
	 * @uses    GF_Field::is_entry_detail()
	 * @uses    GF_Field::is_form_editor()
	 * @uses    GF_Field_Phone::$failed_validation
	 * @uses    GF_Field_Phone::get_phone_format()
	 * @uses    GFFormsModel::is_html5_enabled()
	 * @uses    GF_Field::get_field_placeholder_attribute()
	 * @uses    GF_Field_Phone::$isRequired
	 * @uses    GF_Field::get_tabindex()
	 *
	 * @param array      $form  The Form Object.
	 * @param string     $value The value of the input. Defaults to empty string.
	 * @param null|array $entry The Entry Object. Defaults to null.
	 *
	 * @return string The HTML markup for the field.
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
		$placeholder_attribute = $this->get_field_placeholder_attribute();
		$required_attribute    = $this->isRequired ? 'aria-required="true"' : '';
		$invalid_attribute     = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';
		$aria_describedby      = $this->get_aria_describedby();

		$tabindex = $this->get_tabindex();

		return sprintf( "<div class='ginput_container ginput_container_phone'><input name='input_%d' id='%s' type='{$html_input_type}' value='%s' class='%s' {$tabindex} {$placeholder_attribute} {$required_attribute} {$invalid_attribute} {$aria_describedby} %s/>{$instruction_div}</div>", $id, $field_id, esc_attr( $value ), esc_attr( $class ), $disabled_text );

	}

	/**
	 * Gets the value of the submitted field.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormsModel::get_field_value()
	 * @uses    GF_Field::get_value_submission()
	 * @uses    GF_Field_Phone::sanitize_entry_value()
	 *
	 * @param array $field_values             The dynamic population parameter names with their corresponding values to be populated.
	 * @param bool  $get_from_post_global_var Whether to get the value from the $_POST array as opposed to $field_values. Defaults to true.
	 *
	 * @return array|string
	 */
	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {

		$value = parent::get_value_submission( $field_values, $get_from_post_global_var );
		$value = $this->sanitize_entry_value( $value, $this->formId );

		return $value;
	}

	/**
	 * Sanitizes the entry value.
	 *
	 * @since Unknown
	 * @access public
	 *
	 * @used-by GF_Field_Phone::get_value_save_entry()
	 * @used-by GF_Field_Phone::get_value_submission()
	 *
	 * @param string $value   The value to be sanitized.
	 * @param int    $form_id The form ID of the submitted item.
	 *
	 * @return string The sanitized value.
	 */
	public function sanitize_entry_value( $value, $form_id ) {
		$value = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( $value );
		return $value;
	}

	/**
	 * Gets the field value when an entry is being saved.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormsModel::prepare_value()
	 * @uses    GF_Field_Phone::sanitize_entry_value()
	 * @uses    GF_Field_Phone::$phoneFormat
	 *
	 * @param string $value      The input value.
	 * @param array  $form       The Form Object.
	 * @param string $input_name The input name.
	 * @param int    $lead_id    The Entry ID.
	 * @param array  $lead       The Entry Object.
	 *
	 * @return string The field value.
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		$value = $this->sanitize_entry_value( $value, $form['id'] );

		if ( $this->phoneFormat == 'standard' && preg_match( '/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/', $value, $matches ) ) {
			$value = sprintf( '(%s) %s-%s', $matches[1], $matches[2], $matches[3] );
		}

		return $value;
	}

	/**
	 * Outputs any inline scripts to be used when the page is rendered.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GF_Field::register_form_init_scripts()
	 * @uses    GF_Field_Phone::get_phone_format()
	 *
	 * @param array $form The Form Object.
	 *
	 * @return string The inline scripts.
	 */
	public function get_form_inline_script_on_page_render( $form ) {
		$script       = '';
		$phone_format = $this->get_phone_format();

		if ( rgar( $phone_format, 'mask' ) ) {
			$script = "jQuery('#input_{$form['id']}_{$this->id}').mask('{$phone_format['mask']}').bind('keypress', function(e){if(e.which == 13){jQuery(this).blur();} } );";
		}
		return $script;
	}

	/**
	 * Sanitizes the field settings.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormDetail::add_field()
	 * @used-by GFFormsModel::sanitize_settings()
	 * @uses    GF_Field::sanitize_settings()
	 * @uses    GF_Field_Phone::get_phone_format()
	 * @uses    GF_Field_Phone::$phoneFormat
	 *
	 * @return void
	 */
	public function sanitize_settings() {
		parent::sanitize_settings();

		if ( ! $this->get_phone_format() ) {
			$this->phoneFormat = 'standard';
		}
	}

	/**
	 * Get an array of phone formats.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GF_Field_Phone::get_phone_format()
	 *
	 * @param null|int $form_id The ID of the current form or null to use the value from the current fields form_id property. Defaults to null.
	 *
	 * @return array The phone formats available.
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
		 * @since 2.0.0
		 *
		 * @param array $phone_formats The phone formats.
		 * @param int   $form_id       The ID of the current form.
		 */
		$phone_formats = apply_filters( 'gform_phone_formats', $phone_formats, $form_id );

		/**
		 * Filters the custom form inputs only for a specific form ID.
		 *
		 * @since 2.0.0
		 *
		 * @param array $phone_formats The phone formats.
		 * @param int   $form_id       The ID of the current form.
		 */
		return apply_filters( 'gform_phone_formats_' . $form_id, $phone_formats, $form_id );
	}

	/**
	 * Get the properties for the fields selected phone format.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GF_Field_Phone::get_field_input()
	 * @used-by GF_Field_Phone::get_form_inline_script_on_page_render()
	 * @used-by GF_Field_Phone::sanitize_settings()
	 * @used-by GF_Field_Phone::validate()
	 * @uses    GF_Field_Phone::get_phone_formats()
	 * @uses    GF_Field_Phone::$phoneFormat
	 *
	 * @return array The phone format.
	 */
	public function get_phone_format() {
		$phone_formats = $this->get_phone_formats();

		return rgar( $phone_formats, $this->phoneFormat );
	}
}

// Register the phone field with the field framework.
GF_Fields::register( new GF_Field_Phone() );
