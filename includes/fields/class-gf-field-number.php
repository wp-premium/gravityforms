<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_Number extends GF_Field {

	public $type = 'number';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Number', 'gravityforms' );
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
			'number_format_setting',
			'range_setting',
			'rules_setting',
			'visibility_setting',
			'duplicate_setting',
			'default_value_setting',
			'placeholder_setting',
			'description_setting',
			'css_class_setting',
			'calculation_setting',
		);
	}

	public function is_conditional_logic_supported() {
		return true;
	}

	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {

		$value = $this->get_input_value_submission( 'input_' . $this->id, $this->inputName, $field_values, $get_from_post_global_var );
		$value = trim( $value );
		if ( $this->numberFormat == 'currency' ) {
			require_once( GFCommon::get_base_path() . '/currency.php' );
			$currency = new RGCurrency( GFCommon::get_currency() );
			$value    = $currency->to_number( $value );
		} elseif ( $this->numberFormat == 'decimal_comma' ) {
			$value = GFCommon::clean_number( $value, 'decimal_comma' );
		} elseif ( $this->numberFormat == 'decimal_dot' ) {
			$value = GFCommon::clean_number( $value, 'decimal_dot' );
		}

		return $value;
	}

	public function validate( $value, $form ) {

		// the POST value has already been converted from currency or decimal_comma to decimal_dot and then cleaned in get_field_value()

		$value     = GFCommon::maybe_add_leading_zero( $value );
		$raw_value = $_POST[ 'input_' . $this->id ]; //Raw value will be tested against the is_numeric() function to make sure it is in the right format.

		$requires_valid_number = ! rgblank( $raw_value ) && ! $this->has_calculation();

		$raw_value       = GFCommon::maybe_add_leading_zero( $raw_value );
		$is_valid_number = $this->validate_range( $value ) && GFCommon::is_numeric( $raw_value, $this->numberFormat );

		if ( $requires_valid_number && ! $is_valid_number ) {
			$this->failed_validation  = true;
			$this->validation_message = empty( $this->errorMessage ) ? $this->get_range_message() : $this->errorMessage;
		} elseif ( $this->type == 'quantity' ) {
			if ( intval( $value ) != $value ) {
				$this->failed_validation  = true;
				$this->validation_message = empty( $field['errorMessage'] ) ? esc_html__( 'Please enter a valid quantity. Quantity cannot contain decimals.', 'gravityforms' ) : $field['errorMessage'];
			} elseif ( ! empty( $value ) && ( ! is_numeric( $value ) || intval( $value ) != floatval( $value ) || intval( $value ) < 0 ) ) {
				$this->failed_validation  = true;
				$this->validation_message = empty( $field['errorMessage'] ) ? esc_html__( 'Please enter a valid quantity', 'gravityforms' ) : $field['errorMessage'];
			}
		}

	}

	/**
	 * Validates the range of the number according to the field settings.
	 *
	 * @param array $value A decimal_dot formatted string
	 *
	 * @return true|false True on valid or false on invalid
	 */
	private function validate_range( $value ) {

		if ( ! GFCommon::is_numeric( $value, 'decimal_dot' ) ) {
			return false;
		}

		$numeric_min = $this->numberFormat == 'decimal_comma' ? GFCommon::clean_number( $this->rangeMin, 'decimal_comma' ) : $this->rangeMin;
		$numeric_max = $this->numberFormat == 'decimal_comma' ? GFCommon::clean_number( $this->rangeMax, 'decimal_comma' ) : $this->rangeMax;

		if ( ( is_numeric( $numeric_min ) && $value < $numeric_min ) ||
		     ( is_numeric( $numeric_max ) && $value > $numeric_max )
		) {
			return false;
		} else {
			return true;
		}
	}

	public function get_range_message() {
		$min     = $this->rangeMin;
		$max     = $this->rangeMax;

		$numeric_min = $min;
		$numeric_max = $max;

		if( $this->numberFormat == 'decimal_comma' ){
			$numeric_min = empty( $min ) ? '' : GFCommon::clean_number( $min, 'decimal_comma', '');
			$numeric_max = empty( $max ) ? '' : GFCommon::clean_number( $max, 'decimal_comma', '');
		}

		$message = '';

		if ( is_numeric( $numeric_min ) && is_numeric( $numeric_max ) ) {
			$message = sprintf( esc_html__( 'Please enter a value between %s and %s.', 'gravityforms' ), "<strong>$min</strong>", "<strong>$max</strong>" );
		} elseif ( is_numeric( $numeric_min ) ) {
			$message = sprintf( esc_html__( 'Please enter a value greater than or equal to %s.', 'gravityforms' ), "<strong>$min</strong>" );
		} elseif ( is_numeric( $numeric_max ) ) {
			$message = sprintf( esc_html__( 'Please enter a value less than or equal to %s.', 'gravityforms' ), "<strong>$max</strong>" );
		} elseif ( $this->failed_validation ) {
			$message = esc_html__( 'Please enter a valid number', 'gravityforms' );
		}

		return $message;
	}

	public function get_field_input( $form, $value = '', $entry = null ) {
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$form_id  = $form['id'];
		$id       = intval( $this->id );
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$size          = $this->size;
		$disabled_text = $is_form_editor ? "disabled='disabled'" : '';
		$class_suffix  = $is_entry_detail ? '_admin' : '';
		$class         = $size . $class_suffix;

		$instruction = '';
		$read_only   = '';

		if ( ! $is_entry_detail && ! $is_form_editor ) {

			if ( $this->has_calculation() ) {

				// calculation-enabled fields should be read only
				$read_only = 'readonly="readonly"';

			} else {

				$message          = $this->get_range_message();
				$validation_class = $this->failed_validation ? 'validation_message' : '';

				if ( ! $this->failed_validation && ! empty( $message ) && empty( $this->errorMessage ) ) {
					$instruction = "<div class='instruction $validation_class'>" . $message . '</div>';
				}
			}
		} elseif ( RG_CURRENT_VIEW == 'entry' ) {
			$value = GFCommon::format_number( $value, $this->numberFormat, rgar( $entry, 'currency' ) );
		}

		$is_html5        = RGFormsModel::is_html5_enabled();
		$html_input_type = $is_html5 && ! $this->has_calculation() && ( $this->numberFormat != 'currency' && $this->numberFormat != 'decimal_comma' ) ? 'number' : 'text'; // chrome does not allow number fields to have commas, calculations and currency values display numbers with commas
		$step_attr       = $is_html5 ? "step='any'" : '';

		$min = $this->rangeMin;
		$max = $this->rangeMax;

		$min_attr = $is_html5 && is_numeric( $min ) ? "min='{$min}'" : '';
		$max_attr = $is_html5 && is_numeric( $max ) ? "max='{$max}'" : '';

		$logic_event = $this->get_conditional_logic_event( 'keyup' );

		$include_thousands_sep = apply_filters( 'gform_include_thousands_sep_pre_format_number', $html_input_type == 'text', $this );
		$value                 = GFCommon::format_number( $value, $this->numberFormat, rgar( $entry, 'currency' ), $include_thousands_sep );

		$placeholder_attribute = $this->get_field_placeholder_attribute();
		$required_attribute    = $this->isRequired ? 'aria-required="true"' : '';
		$invalid_attribute     = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';

		$tabindex = $this->get_tabindex();

		$input = sprintf( "<div class='ginput_container ginput_container_number'><input name='input_%d' id='%s' type='{$html_input_type}' {$step_attr} {$min_attr} {$max_attr} value='%s' class='%s' {$tabindex} {$logic_event} {$read_only} %s %s %s %s/>%s</div>", $id, $field_id, esc_attr( $value ), esc_attr( $class ), $disabled_text, $placeholder_attribute, $required_attribute, $invalid_attribute, $instruction );
		return $input;
	}

	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		$include_thousands_sep = apply_filters( 'gform_include_thousands_sep_pre_format_number', true, $this );

		return GFCommon::format_number( $value, $this->numberFormat, rgar( $entry, 'currency' ), $include_thousands_sep );
	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		$include_thousands_sep = apply_filters( 'gform_include_thousands_sep_pre_format_number', $use_text, $this );

		return GFCommon::format_number( $value, $this->numberFormat, $currency, $include_thousands_sep );
	}

	/**
	 * Gets merge tag values.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFCommon::format_number()
	 *
	 * @param array|string $value      The value of the input.
	 * @param string       $input_id   The input ID to use.
	 * @param array        $entry      The Entry Object.
	 * @param array        $form       The Form Object
	 * @param string       $modifier   The modifier passed.
	 * @param array|string $raw_value  The raw value of the input.
	 * @param bool         $url_encode If the result should be URL encoded.
	 * @param bool         $esc_html   If the HTML should be escaped.
	 * @param string       $format     The format that the value should be.
	 * @param bool         $nl2br      If the nl2br function should be used.
	 *
	 * @return string The processed merge tag.
	 */
	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		/**
		 * Filters is the thousands separator should be used when displaying the a number field result.
		 *
		 * @since Unknown
		 *
		 * @param bool   $modifier != 'value' If the modifier passed in the merge tag is not 'value', false. Otherwise, true.
		 * @param object GF_Field_Number      An instance of this class.
		 */
		$include_thousands_sep = apply_filters( 'gform_include_thousands_sep_pre_format_number', $modifier != 'value', $this );
		$formatted_value       = GFCommon::format_number( $value, $this->numberFormat, rgar( $entry, 'currency' ), $include_thousands_sep );

		return $url_encode ? urlencode( $formatted_value ) : $formatted_value;
	}

	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {

		$value = GFCommon::maybe_add_leading_zero( $value );

		$lead  = empty( $lead ) ? RGFormsModel::get_lead( $lead_id ) : $lead;
		$value = $this->has_calculation() ? GFCommon::round_number( GFCommon::calculate( $this, $form, $lead ), $this->calculationRounding ) : $this->clean_number( $value );
		//return the value as a string when it is zero and a calc so that the "==" comparison done when checking if the field has changed isn't treated as false
		if ( $this->has_calculation() && $value == 0 ) {
			$value = '0';
		}

		$value_safe = $this->sanitize_entry_value( $value, $form['id'] );

		return $value_safe;
	}

	public function sanitize_settings() {
		parent::sanitize_settings();
		$this->enableCalculation = (bool) $this->enableCalculation;

		if ( $this->numberFormat == 'currency' ) {
			require_once( GFCommon::get_base_path() . '/currency.php' );
			$currency = new RGCurrency( GFCommon::get_currency() );
			$this->rangeMin    = $currency->to_number( $this->rangeMin );
			$this->rangeMax    = $currency->to_number( $this->rangeMax );

		} elseif ( $this->numberFormat == 'decimal_comma' ) {
			$this->rangeMin = GFCommon::clean_number( $this->rangeMin, 'decimal_comma' );
			$this->rangeMax = GFCommon::clean_number( $this->rangeMax, 'decimal_comma' );

			$this->rangeMin = GFCommon::format_number( $this->rangeMin, 'decimal_comma' );
			$this->rangeMax = GFCommon::format_number( $this->rangeMax, 'decimal_comma' );

		} elseif ( $this->numberFormat == 'decimal_dot' ) {
			$this->rangeMin = GFCommon::clean_number( $this->rangeMin, 'decimal_dot' );
			$this->rangeMax = GFCommon::clean_number( $this->rangeMax, 'decimal_dot' );

		}
	}

	public function clean_number( $value ) {

		if ( $this->numberFormat == 'currency' ) {
			return GFCommon::to_number( $value );
		} else {
			return GFCommon::clean_number( $value, $this->numberFormat );
		}
	}
}

GF_Fields::register( new GF_Field_Number() );