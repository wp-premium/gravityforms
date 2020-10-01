<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Class GF_Field
 *
 * This class provides the base functionality for developers when creating new fields for Gravity Forms. It facilitates the following:
 *  Adding a button for the field to the form editor
 *  Defining the field title to be used in the form editor
 *  Defining which settings should be present when editing the field
 *  Registering the field as compatible with conditional logic
 *  Outputting field scripts to the form editor and front-end
 *  Defining the field appearance on the front-end, in the form editor and on the entry detail page
 *  Validating the field during submission
 *  Saving the entry value
 *  Defining how the entry value is displayed when merge tags are processed, on the entries list and entry detail pages
 *  Defining how the entry value should be formatted when used in csv exports and by framework based add-ons
 */
class GF_Field extends stdClass implements ArrayAccess {

	const SUPPRESS_DEPRECATION_NOTICE = true;

	private static $deprecation_notice_fired = false;

	private $_is_entry_detail = null;

	/**
	 * An array of properties used to help define and determine the context for the field.
	 * As this is private, it won't be available in any json_encode() output and consequently not saved in the Form array.
	 *
	 * @since 2.3
	 *
	 * @private
	 *
	 * @var array
	 */
	private $_context_properties = array();

	/**
	 * @var array $_merge_tag_modifiers An array of modifiers specified on the field or all_fields merge tag being processed.
	 */
	private $_merge_tag_modifiers = array();

	public function __construct( $data = array() ) {
		if ( empty( $data ) ) {
			return;
		}
		foreach ( $data as $key => $value ) {
			$this->{$key} = $value;
		}
	}

	/**
	 * Fires the deprecation notice only once per page. Not fired during AJAX requests.
	 *
	 * @param string $offset The array key being accessed.
	 */
	private function maybe_fire_array_access_deprecation_notice( $offset ) {

		if ( self::SUPPRESS_DEPRECATION_NOTICE ) {
			return;
		};

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( ! self::$deprecation_notice_fired ) {
			_deprecated_function( "Array access to the field object is now deprecated. Further notices will be suppressed. \$field['" . $offset . "']", '2.0', 'the object operator e.g. $field->' . $offset );
			self::$deprecation_notice_fired = true;
		}
	}

	/**
	 * Handles array notation
	 *
	 * @param mixed $offset
	 *
	 * @return bool
	 */
	public function offsetExists( $offset ) {
		$this->maybe_fire_array_access_deprecation_notice( $offset );

		return isset( $this->$offset );
	}

	public function offsetGet( $offset ) {
		$this->maybe_fire_array_access_deprecation_notice( $offset );
		if ( ! isset( $this->$offset ) ) {
			$this->$offset = '';
		}

		return $this->$offset;
	}

	public function offsetSet( $offset, $data ) {
		$this->maybe_fire_array_access_deprecation_notice( $offset );
		if ( $offset === null ) {
			$this[] = $data;
		} else {
			$this->$offset = $data;
		}
	}

	public function offsetUnset( $offset ) {
		$this->maybe_fire_array_access_deprecation_notice( $offset );
		unset( $this->$offset );
	}

	public function __isset( $key ) {
		return isset( $this->$key );
	}

	public function __set( $key, $value ) {
		switch( $key ) {
			case '_context_properties' :
				_doing_it_wrong( '$field->_context_properties', 'Use $field->get_context_property() instead.', '2.3' );
				break;
			case 'adminOnly':
				// intercept 3rd parties trying to set the adminOnly property and convert to visibility property
				$this->visibility = $value ? 'administrative' : 'visible';
				break;
			default:
				$this->$key = $value;
		}
	}

	/**
	 * The getter method of the field property.
	 *
	 * @since unknown
	 * @since 2.4.19  Add whitelist for the size property.
	 *
	 * @param string $key The field property.
	 *
	 * @return bool|mixed
	 */
	public function &__get( $key ) {

		switch ( $key ) {
			case '_context_properties' :
				_doing_it_wrong( '$field->_context_properties', 'Use $field->get_context_property() instead.', '2.3' );
				$value = false;

				return $value;
			case 'adminOnly' :
				// intercept 3rd parties trying to get the adminOnly property and fetch visibility property instead
				$value = $this->visibility == 'administrative'; // set and return variable to avoid notice

				return $value;
			case 'size':
				$value = '';

				if ( isset( $this->size ) ) {
					$value = GFCommon::whitelist( $this->size, array( 'small', 'medium', 'large' ) );
				}

				return $value;
			default:
				if ( ! isset( $this->$key ) ) {
					$this->$key = '';
				}
		}

		return $this->$key;
	}

	public function __unset( $key ) {
		unset( $this->$key );
	}

	public function set_context_property( $property_key, $value ) {
		$this->_context_properties[ $property_key ] = $value;
	}

	public function get_context_property( $property_key ) {
		return isset( $this->_context_properties[ $property_key ] ) ? $this->_context_properties[ $property_key ] : null;
	}


	// # FORM EDITOR & FIELD MARKUP -------------------------------------------------------------------------------------

	/**
	 * Returns the field title.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return $this->type;
	}

	/**
	 * Returns the field button properties for the form editor. The array contains two elements:
	 * 'group' => 'standard_fields' // or  'advanced_fields', 'post_fields', 'pricing_fields'
	 * 'text'  => 'Button text'
	 *
	 * Built-in fields don't need to implement this because the buttons are added in sequence in GFFormDetail
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'standard_fields',
			'text'  => $this->get_form_editor_field_title()
		);
	}

	/**
	 * Returns the class names of the settings which should be available on the field in the form editor.
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings() {
		return array();
	}

	/**
	 * Override to indicate if this field type can be used when configuring conditional logic rules.
	 *
	 * @return bool
	 */
	public function is_conditional_logic_supported() {
		return false;
	}

	/**
	 * Returns the scripts to be included for this field type in the form editor.
	 *
	 * @return string
	 */
	public function get_form_editor_inline_script_on_page_render() {
		return '';
	}

	/**
	 * Returns the scripts to be included with the form init scripts on the front-end.
	 *
	 * @param array $form The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_form_inline_script_on_page_render( $form ) {
		return '';
	}

	/**
	 * Returns the field inner markup.
	 *
	 * @param array        $form  The Form Object currently being processed.
	 * @param string|array $value The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param null|array   $entry Null or the Entry Object currently being edited.
	 *
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		return '';
	}

	/**
	 * Returns the field markup; including field label, description, validation, and the form editor admin buttons.
	 *
	 * The {FIELD} placeholder will be replaced in GFFormDisplay::get_field_content with the markup returned by GF_Field::get_field_input().
	 *
	 * @param string|array $value                The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param bool         $force_frontend_label Should the frontend label be displayed in the admin even if an admin label is configured.
	 * @param array        $form                 The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_field_content( $value, $force_frontend_label, $form ) {
		$form_id = (int) rgar( $form, 'id' );

		$field_label = $this->get_field_label( $force_frontend_label, $value );

		$validation_message_id = 'validation_message_' . $form_id . '_' . $this->id;
		$validation_message = ( $this->failed_validation && ! empty( $this->validation_message ) ) ? sprintf( "<div id='%s' class='gfield_description validation_message' aria-live='polite'>%s</div>", $validation_message_id, $this->validation_message ) : '';

		$is_form_editor  = $this->is_form_editor();
		$is_entry_detail = $this->is_entry_detail();
		$is_admin        = $is_form_editor || $is_entry_detail;

		$required_div = $is_admin || $this->isRequired ? sprintf( "<span class='gfield_required'>%s</span>", $this->isRequired ? '*' : '' ) : '';

		$admin_buttons = $this->get_admin_buttons();

		$target_input_id = $this->get_first_input_id( $form );

		$for_attribute = empty( $target_input_id ) ? '' : "for='{$target_input_id}'";

		$description = $this->get_description( $this->description, 'gfield_description' );
		if ( $this->is_description_above( $form ) ) {
			$clear         = $is_admin ? "<div class='gf_clear'></div>" : '';
			$field_content = sprintf( "%s<label class='%s' $for_attribute >%s%s</label>%s{FIELD}%s$clear", $admin_buttons, esc_attr( $this->get_field_label_class() ), esc_html( $field_label ), $required_div, $description, $validation_message );
		} else {
			$field_content = sprintf( "%s<label class='%s' $for_attribute >%s%s</label>{FIELD}%s%s", $admin_buttons, esc_attr( $this->get_field_label_class() ), esc_html( $field_label ), $required_div, $description, $validation_message );
		}

		return $field_content;
	}

	public function get_field_label_class() {
		return 'gfield_label';
	}


	// # SUBMISSION -----------------------------------------------------------------------------------------------------

	/**
	 * Whether this field expects an array during submission.
	 *
	 * @since 2.4
	 *
	 * @return bool
	 */
	public function is_value_submission_array() {
		return false;
	}

	/**
	 * Used to determine the required validation result.
	 *
	 * @param int $form_id The ID of the form currently being processed.
	 *
	 * @return bool
	 */
	public function is_value_submission_empty( $form_id ) {

		$copy_values_option_activated = $this->enableCopyValuesOption && rgpost( 'input_' . $this->id . '_copy_values_activated' );

		if ( is_array( $this->inputs ) ) {
			foreach ( $this->inputs as $input ) {

				if ( $copy_values_option_activated ) {
					$input_id          = $input['id'];
					$input_name        = 'input_' . str_replace( '.', '_', $input_id );
					$source_field_id   = $this->copyValuesOptionField;
					$source_input_name = str_replace( 'input_' . $this->id, 'input_' . $source_field_id, $input_name );
					$value             = rgpost( $source_input_name );
				} else {
					$value = rgpost( 'input_' . str_replace( '.', '_', $input['id'] ) );
				}

				if ( is_array( $value ) && ! empty( $value ) ) {
					return false;
				}

				if ( ! is_array( $value ) && strlen( trim( $value ) ) > 0 ) {
					return false;
				}
			}

			return true;
		} else {
			if ( $copy_values_option_activated ) {
				$value = rgpost( 'input_' . $this->copyValuesOptionField );
			} else {
				$value = rgpost( 'input_' . $this->id );
			}

			if ( is_array( $value ) ) {
				//empty if any of the inputs are empty (for inputs with the same name)
				foreach ( $value as $input ) {
					$input = GFCommon::trim_deep( $input );
					if ( GFCommon::safe_strlen( $input ) <= 0 ) {
						return true;
					}
				}

				return false;
			} elseif ( $this->enablePrice ) {
				list( $label, $price ) = rgexplode( '|', $value, 2 );
				$is_empty = ( strlen( trim( $price ) ) <= 0 );

				return $is_empty;
			} else {
				$is_empty = ( strlen( trim( $value ) ) <= 0 ) || ( $this->type == 'post_category' && $value < 0 );

				return $is_empty;
			}
		}
	}

	/**
	 * Is the given value considered empty for this field.
	 *
	 * @since 2.4
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	public function is_value_empty( $value ) {
		if ( is_array( $this->inputs ) ) {
			if ( $this->is_value_submission_array() ) {
				foreach ( $this->inputs as $i => $input ) {
					$v = isset( $value[ $i ] ) ?  $value[ $i ] : '';
					if ( is_array( $v ) && ! empty( $v ) ) {
						return false;
					}

					if ( ! is_array( $v ) && strlen( trim( $v ) ) > 0 ) {
						return false;
					}
				}
			} else {
				foreach ( $this->inputs as $input ) {
					$input_id = (string) $input['id'];
					$v = isset( $value[ $input_id ] ) ?  $value[ $input_id ] : '';
					if ( is_array( $v ) && ! empty( $v ) ) {
						return false;
					}

					if ( ! is_array( $v ) && strlen( trim( $v ) ) > 0 ) {
						return false;
					}
				}
			}

		} elseif ( is_array( $value ) ) {
			// empty if any of the inputs are empty (for inputs with the same name)
			foreach ( $value as $input ) {
				$input = GFCommon::trim_deep( $input );
				if ( GFCommon::safe_strlen( $input ) <= 0 ) {
					return true;
				}
			}

			return false;
		} elseif ( empty( $value ) ) {
			return true;
		} else {
			return false;
		}

		return true;
	}

	/**
	 * Override this method to perform custom validation logic.
	 *
	 * Return the result (bool) by setting $this->failed_validation.
	 * Return the validation message (string) by setting $this->validation_message.
	 *
	 * @param string|array $value The field value from get_value_submission().
	 * @param array        $form  The Form Object currently being processed.
	 */
	public function validate( $value, $form ) {
		//
	}

	/**
	 * Retrieve the field value on submission.
	 *
	 * @param array     $field_values             The dynamic population parameter names with their corresponding values to be populated.
	 * @param bool|true $get_from_post_global_var Whether to get the value from the $_POST array as opposed to $field_values.
	 *
	 * @return array|string
	 */
	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {

		$inputs = $this->get_entry_inputs();

		if ( is_array( $inputs ) ) {
			$value = array();
			foreach ( $inputs as $input ) {
				$value[ strval( $input['id'] ) ] = $this->get_input_value_submission( 'input_' . str_replace( '.', '_', strval( $input['id'] ) ), RGForms::get( 'name', $input ), $field_values, $get_from_post_global_var );
			}
		} else {
			$value = $this->get_input_value_submission( 'input_' . $this->id, $this->inputName, $field_values, $get_from_post_global_var );
		}

		return $value;
	}

	/**
	 * Retrieve the input value on submission.
	 *
	 * @param string    $standard_name            The input name used when accessing the $_POST.
	 * @param string    $custom_name              The dynamic population parameter name.
	 * @param array     $field_values             The dynamic population parameter names with their corresponding values to be populated.
	 * @param bool|true $get_from_post_global_var Whether to get the value from the $_POST array as opposed to $field_values.
	 *
	 * @return array|string
	 */
	public function get_input_value_submission( $standard_name, $custom_name = '', $field_values = array(), $get_from_post_global_var = true ) {

		$form_id = $this->formId;
		if ( ! empty( $_POST[ 'is_submit_' . $form_id ] ) && $get_from_post_global_var ) {
			$value = rgpost( $standard_name );
			$value = GFFormsModel::maybe_trim_input( $value, $form_id, $this );

			return $value;
		} elseif ( $this->allowsPrepopulate ) {
			return GFFormsModel::get_parameter_value( $custom_name, $field_values, $this );
		}

	}


	// # ENTRY RELATED --------------------------------------------------------------------------------------------------

	/**
	 * Override and return null if a multi-input field value is to be stored under the field ID instead of the individual input IDs.
	 *
	 * @return array|null
	 */
	public function get_entry_inputs() {
		return $this->inputs;
	}

	/**
	 * Sanitize and format the value before it is saved to the Entry Object.
	 *
	 * @param string $value      The value to be saved.
	 * @param array  $form       The Form Object currently being processed.
	 * @param string $input_name The input name used when accessing the $_POST.
	 * @param int    $lead_id    The ID of the Entry currently being processed.
	 * @param array  $lead       The Entry Object currently being processed.
	 *
	 * @return array|string The safe value.
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		if ( rgblank( $value ) ) {

			return '';

		} elseif ( is_array( $value ) ) {

			foreach ( $value as &$v ) {

				if ( is_array( $v ) ) {
					$v = '';
				}

				$v = $this->sanitize_entry_value( $v, $form['id'] );

			}

			return implode( ',', $value );

		} else {

			return $this->sanitize_entry_value( $value, $form['id'] );

		}
	}

	/**
	 * Format the entry value for when the field/input merge tag is processed. Not called for the {all_fields} merge tag.
	 *
	 * Return a value that is safe for the context specified by $format.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param string|array $value      The field value. Depending on the location the merge tag is being used the following functions may have already been applied to the value: esc_html, nl2br, and urlencode.
	 * @param string       $input_id   The field or input ID from the merge tag currently being processed.
	 * @param array        $entry      The Entry Object currently being processed.
	 * @param array        $form       The Form Object currently being processed.
	 * @param string       $modifier   The merge tag modifier. e.g. value
	 * @param string|array $raw_value  The raw field value from before any formatting was applied to $value.
	 * @param bool         $url_encode Indicates if the urlencode function may have been applied to the $value.
	 * @param bool         $esc_html   Indicates if the esc_html function may have been applied to the $value.
	 * @param string       $format     The format requested for the location the merge is being used. Possible values: html, text or url.
	 * @param bool         $nl2br      Indicates if the nl2br function may have been applied to the $value.
	 *
	 * @return string
	 */
	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {

		if ( $format === 'html' ) {
			$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : null;
			$allowable_tags = $this->get_allowable_tags( $form_id );

			if ( $allowable_tags === false ) {
				// The value is unsafe so encode the value.
				if ( is_array( $value ) ) {
					foreach ( $value as &$v ) {
						$v = esc_html( $v );
					}
					$return = $value;
				} else {
					$return = esc_html( $value );
				}
			} else {
				// The value contains HTML but the value was sanitized before saving.
				if ( is_array( $raw_value ) ) {
					$return = rgar( $raw_value, $input_id );
				} else {
					$return = $raw_value;
				}
			}

			if ( $nl2br ) {
				if ( is_array( $return ) ) {
					foreach ( $return as &$r ) {
						$r = nl2br( $r );
					}
				} else {
					$return = nl2br( $return );
				}
			}
		} else {
			$return = $value;
		}

		return $return;
	}

	/**
	 * Format the entry value for display on the entries list page.
	 *
	 * Return a value that's safe to display on the page.
	 *
	 * @param string|array $value    The field value.
	 * @param array        $entry    The Entry Object currently being processed.
	 * @param string       $field_id The field or input ID currently being processed.
	 * @param array        $columns  The properties for the columns being displayed on the entry list page.
	 * @param array        $form     The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		$allowable_tags = $this->get_allowable_tags( $form['id'] );

		if ( $allowable_tags === false ) {
			// The value is unsafe so encode the value.
			$return = esc_html( $value );
		} else {
			// The value contains HTML but the value was sanitized before saving.
			$return = $value;
		}

		return $return;
	}

	/**
	 * Format the entry value for display on the entry detail page and for the {all_fields} merge tag.
	 *
	 * Return a value that's safe to display for the context of the given $format.
	 *
	 * @param string|array $value    The field value.
	 * @param string       $currency The entry currency code.
	 * @param bool|false   $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param string       $format   The format requested for the location the merge is being used. Possible values: html, text or url.
	 * @param string       $media    The location where the value will be displayed. Possible values: screen or email.
	 *
	 * @return string
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		if ( is_array( $value ) ) {
			_doing_it_wrong( __METHOD__, 'Override this method to handle array values', '2.0' );
			return $value;
		}

		if ( $format === 'html' ) {
			$value = nl2br( $value );

			$allowable_tags = $this->get_allowable_tags();

			if ( $allowable_tags === false ) {
				// The value is unsafe so encode the value.
				$return = esc_html( $value );
			} else {
				// The value contains HTML but the value was sanitized before saving.
				$return = $value;
			}
		} else {
			$return = $value;
		}

		return $return;
	}

	/**
	 * Format the entry value before it is used in entry exports and by framework add-ons using GFAddOn::get_field_value().
	 *
	 * For CSV export return a string or array.
	 *
	 * @param array      $entry    The entry currently being processed.
	 * @param string     $input_id The field or input ID.
	 * @param bool|false $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param bool|false $is_csv   Is the value going to be used in the .csv entries export?
	 *
	 * @return string|array
	 */
	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		if ( empty( $input_id ) ) {
			$input_id = $this->id;
		}

		return rgar( $entry, $input_id );
	}


	// # INPUT ATTRIBUTE HELPERS ----------------------------------------------------------------------------------------

	/**
	 * Maybe return the input attribute which will trigger evaluation of conditional logic rules which depend on this field.
	 *
	 * @since 2.4
	 *
	 * @param string $event The event attribute which should be returned. Possible values: keyup, click, or change.
	 *
	 * @deprecated 2.4 Conditional Logic is now triggered based on .gfield class name. No need to hardcode calls to gf_apply_rules() to every field.
	 *
	 * @return string
	 */
	public function get_conditional_logic_event( $event ) {

		_deprecated_function( __CLASS__ . ':' . __METHOD__, '2.4' );

		if ( empty( $this->conditionalLogicFields ) || $this->is_entry_detail() || $this->is_form_editor() ) {
			return '';
		}

		switch ( $event ) {
			case 'keyup' :
				return "onchange='gf_apply_rules(" . $this->formId . ',' . GFCommon::json_encode( $this->conditionalLogicFields ) . ");' onkeyup='clearTimeout(__gf_timeout_handle); __gf_timeout_handle = setTimeout(\"gf_apply_rules(" . $this->formId . ',' . GFCommon::json_encode( $this->conditionalLogicFields ) . ")\", 300);'";
				break;

			case 'click' :
				return "onclick='gf_apply_rules(" . $this->formId . ',' . GFCommon::json_encode( $this->conditionalLogicFields ) . ");' onkeypress='gf_apply_rules(" . $this->formId . ',' . GFCommon::json_encode( $this->conditionalLogicFields ) . ");'";
				break;

			case 'change' :
				return "onchange='gf_apply_rules(" . $this->formId . ',' . GFCommon::json_encode( $this->conditionalLogicFields ) . ");'";
				break;
		}
	}

	/**
	 * Maybe return the tabindex attribute.
	 *
	 * @return string
	 */
	public function get_tabindex() {
		return GFCommon::$tab_index > 0 ? "tabindex='" . GFCommon::$tab_index ++ . "'" : '';
	}

	/**
	 * If the field placeholder property has a value return the input placeholder attribute.
	 *
	 * @return string
	 */
	public function get_field_placeholder_attribute() {

		$placeholder_value = GFCommon::replace_variables_prepopulate( $this->placeholder );

		return ! rgblank( $placeholder_value ) ? sprintf( "placeholder='%s'", esc_attr( $placeholder_value ) ) : '';
	}

	/**
	 * If the input placeholder property has a value return the input placeholder attribute.
	 *
	 * @param array $input The input currently being processed.
	 *
	 * @return string
	 */
	public function get_input_placeholder_attribute( $input ) {

		$placeholder_value = $this->get_input_placeholder_value( $input );

		return ! rgblank( $placeholder_value ) ? sprintf( "placeholder='%s'", esc_attr( $placeholder_value ) ) : '';
	}

	/**
	 * If configured retrieve the input placeholder value.
	 *
	 * @param array $input The input currently being processed.
	 *
	 * @return string
	 */
	public function get_input_placeholder_value( $input ) {

		$placeholder = rgar( $input, 'placeholder' );

		return rgblank( $placeholder ) ? '' : GFCommon::replace_variables_prepopulate( $placeholder );
	}


	// # BOOLEAN HELPERS ------------------------------------------------------------------------------------------------

	/**
	 * Determine if the current location is the form editor.
	 *
	 * @return bool
	 */
	public function is_form_editor() {
		return GFCommon::is_form_editor();
	}

	/**
	 * Determine if the current location is the entry detail page.
	 *
	 * @return bool
	 */
	public function is_entry_detail() {
		return isset( $this->_is_entry_detail ) ? (bool) $this->_is_entry_detail : GFCommon::is_entry_detail();
	}

	/**
	 * Determine if the current location is the edit entry page.
	 *
	 * @return bool
	 */
	public function is_entry_detail_edit() {
		return GFCommon::is_entry_detail_edit();
	}

	/**
	 * Is this a calculated product field or a number field with a calculation enabled and formula configured.
	 *
	 * @return bool
	 */
	public function has_calculation() {

		$type = $this->get_input_type();

		if ( $type == 'number' ) {
			return $this->enableCalculation && $this->calculationFormula;
		}

		return $type == 'calculation';
	}

	/**
	 * Determines if the field description should be positioned above or below the input.
	 *
	 * @param array $form The Form Object currently being processed.
	 *
	 * @return bool
	 */
	public function is_description_above( $form ) {
		$form_label_placement        = rgar( $form, 'labelPlacement' );
		$field_label_placement       = $this->labelPlacement;
		$form_description_placement  = rgar( $form, 'descriptionPlacement' );
		$field_description_placement = $this->descriptionPlacement;
		if ( empty( $field_description_placement ) ) {
			$field_description_placement = $form_description_placement;
		}
		$is_description_above = $field_description_placement == 'above' && ( $field_label_placement == 'top_label' || $field_label_placement == 'hidden_label' || ( empty( $field_label_placement ) && $form_label_placement == 'top_label' ) );

		return $is_description_above;
	}


	public function is_administrative() {
		return $this->visibility == 'administrative';
	}


	// # OTHER HELPERS --------------------------------------------------------------------------------------------------

	/**
	 * Store the modifiers so they can be accessed in get_value_entry_detail() when preparing the content for the {all_fields} output.
	 *
	 * @param array $modifiers An array of modifiers to be stored.
	 */
	public function set_modifiers( $modifiers ) {

		$this->_merge_tag_modifiers = $modifiers;
	}

	/**
	 * Retrieve the merge tag modifiers.
	 *
	 * @return array
	 */
	public function get_modifiers() {

		return $this->_merge_tag_modifiers;
	}

	/**
	 * Retrieves the field input type.
	 *
	 * @return string
	 */
	public function get_input_type() {

		return empty( $this->inputType ) ? $this->type : $this->inputType;
	}

	/**
	 * Adds the field button to the specified group.
	 *
	 * @param array $field_groups
	 *
	 * @return array
	 */
	public function add_button( $field_groups ) {

		// Check a button for the type hasn't already been added
		foreach ( $field_groups as $group ) {
			foreach ( $group['fields'] as $button ) {
				if ( isset( $button['data-type'] ) && $button['data-type'] == $this->type ) {
					return $field_groups;
				}
			}
		}


		$new_button = $this->get_form_editor_button();
		if ( ! empty( $new_button ) ) {
			foreach ( $field_groups as &$group ) {
				if ( $group['name'] == $new_button['group'] ) {
					$group['fields'][] = array(
						'class'      => 'button',
						'value'      => $new_button['text'],
						'data-type'  => $this->type,
						'onclick'    => "StartAddField('{$this->type}');",
						'onkeypress' => "StartAddField('{$this->type}');",
					);
					break;
				}
			}
		}

		return $field_groups;
	}

	/**
	 * Returns the field admin buttons for display in the form editor.
	 *
	 * @return string
	 */
	public function get_admin_buttons() {
		$duplicate_disabled   = array(
			'captcha',
			'post_title',
			'post_content',
			'post_excerpt',
			'total',
			'shipping',
			'creditcard'
		);
		$duplicate_field_link = ! in_array( $this->type, $duplicate_disabled ) ? "<a class='field_duplicate_icon' id='gfield_duplicate_{$this->id}' title='" . esc_attr__( 'click to duplicate this field', 'gravityforms' ) . "' href='#' onclick='StartDuplicateField(this); return false;' onkeypress='StartDuplicateField(this); return false;'><i class='fa fa-files-o fa-lg'></i></a>" : '';

		/**
		 * This filter allows for modification of the form field duplicate link. This will change the link for all fields
		 *
		 * @param string $duplicate_field_link The Duplicate Field Link (in HTML)
		 */
		$duplicate_field_link = apply_filters( 'gform_duplicate_field_link', $duplicate_field_link );

		$delete_field_link = "<a class='field_delete_icon' id='gfield_delete_{$this->id}' title='" . esc_attr__( 'click to delete this field', 'gravityforms' ) . "' href='#' onclick='DeleteField(this); return false;' onkeypress='DeleteField(this); return false;'><i class='fa fa-times fa-lg'></i></a>";

		/**
		 * This filter allows for modification of a form field delete link. This will change the link for all fields
		 *
		 * @param string $delete_field_link The Delete Field Link (in HTML)
		 */
		$delete_field_link = apply_filters( 'gform_delete_field_link', $delete_field_link );
		$field_type_title  = esc_html( GFCommon::get_field_type_title( $this->type ) );

		$is_form_editor  = $this->is_form_editor();
		$is_entry_detail = $this->is_entry_detail();
		$is_admin        = $is_form_editor || $is_entry_detail;

		$admin_buttons = $is_admin ? "<div class='gfield_admin_icons'><div class='gfield_admin_header_title'>{$field_type_title} : " . esc_html__( 'Field ID', 'gravityforms' ) . " {$this->id}</div>" . $delete_field_link . $duplicate_field_link . "<a href='javascript:void(0);' class='field_edit_icon edit_icon_collapsed' aria-expanded='false' title='" . esc_attr__( 'click to expand and edit the options for this field', 'gravityforms' ) . "'><i class='fa fa-caret-down fa-lg'></i></a></div>" : '';

		return $admin_buttons;
	}

	/**
	 * Retrieve the field label.
	 *
	 * @param bool $force_frontend_label Should the frontend label be displayed in the admin even if an admin label is configured.
	 * @param string $value The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 *
	 * @return string
	 */
	public function get_field_label( $force_frontend_label, $value ) {
		$field_label = $force_frontend_label ? $this->label : GFCommon::get_label( $this );
		if ( ( $this->inputType == 'singleproduct' || $this->inputType == 'calculation' ) && ! rgempty( $this->id . '.1', $value ) ) {
			$field_label = rgar( $value, $this->id . '.1' );
		}

		return $field_label;
	}

	/**
	 * Returns the input ID to be assigned to the field label for attribute.
	 *
	 * @param array $form The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_first_input_id( $form ) {
		$form_id = (int) rgar( $form, 'id' );

		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$field_id        = $is_entry_detail || $is_form_editor || $form_id == 0 ? 'input_' : "input_{$form_id}_";

		if ( is_array( $this->inputs ) ) {
			foreach ( $this->inputs as $input ) {
				// Validate if input id is in x.x format.
				if ( ! is_numeric( $input['id'] ) ) {
					break;
				}

				if ( ! isset( $input['isHidden'] ) || ! $input['isHidden'] ) {
					$field_id .= str_replace( '.', '_', $input['id'] );
					break;
				}
			}
		} else {
			$field_id .= $this->id;
		}

		// The value is used as an HTML attribute, escape it.
		return esc_attr( $field_id );
	}

	/**
	 * Returns the markup for the field description.
	 *
	 * @param string $description The field description.
	 * @param string $css_class   The css class to be assigned to the description container.
	 *
	 * @return string
	 */
	public function get_description( $description, $css_class ) {
		$is_form_editor  = $this->is_form_editor();
		$is_entry_detail = $this->is_entry_detail();
		$is_admin        = $is_form_editor || $is_entry_detail;
		$id              = "gfield_description_{$this->formId}_{$this->id}";

		return $is_admin || ! empty( $description ) ? "<div class='$css_class' id='$id'>" . $description . '</div>' : '';
	}

	/**
	 * If a field has a description, the aria-describedby attribute for the input field is returned.
	 *
	 * @return string
	 */
	public function get_aria_describedby() {

		if ( empty( $this->description ) ) {
			return '';
		}
		$id = "gfield_description_{$this->formId}_{$this->id}";

		return 'aria-describedby="' . $id . '"';

	}

	/**
	 * Returns the field default value if the field does not already have a value.
	 *
	 * @param array|string $value The field value.
	 *
	 * @return array|string
	 */
	public function get_value_default_if_empty( $value ) {

		if ( is_array( $this->inputs ) && is_array( $value ) ) {
			$defaults = $this->get_value_default();
			foreach( $value as $index => &$input_value ) {
				if ( rgblank( $input_value ) ) {
					$input_value = rgar( $defaults, $index );
				}
			}
		}

		if ( ! GFCommon::is_empty_array( $value ) ) {
			return $value;
		}

		return $this->get_value_default();
	}

	/**
	 * Retrieve the field default value.
	 *
	 * @return array|string
	 */
	public function get_value_default() {

		if ( is_array( $this->inputs ) ) {
			$value = array();
			foreach ( $this->inputs as $input ) {
				$value[ strval( $input['id'] ) ] = $this->is_form_editor() ? rgar( $input, 'defaultValue' ) : GFCommon::replace_variables_prepopulate( rgar( $input, 'defaultValue' ) );
			}
		} else {
			$value = $this->is_form_editor() ? $this->defaultValue : GFCommon::replace_variables_prepopulate( $this->defaultValue );
		}

		return $value;
	}

	/**
	 * Registers the script returned by get_form_inline_script_on_page_render() for display on the front-end.
	 *
	 * @param array $form The Form Object currently being processed.
	 */
	public function register_form_init_scripts( $form ) {
		GFFormDisplay::add_init_script( $form['id'], $this->type . '_' . $this->id, GFFormDisplay::ON_PAGE_RENDER, $this->get_form_inline_script_on_page_render( $form ) );
	}


	// # SANITIZATION ---------------------------------------------------------------------------------------------------

	/**
	 * Strip unsafe tags from the field value.
	 *
	 * @param string $string The field value to be processed.
	 *
	 * @return string
	 */
	public function strip_script_tag( $string ) {
		$allowable_tags = '<a><abbr><acronym><address><area><area /><b><base><base /><bdo><big><blockquote><body><br><br /><button><caption><cite><code><col><col /><colgroup><command><command /><dd><del><dfn><div><dl><DOCTYPE><dt><em><fieldset><form><h1><h2><h3><h4><h5><h6><head><html><hr><hr /><i><img><img /><input><input /><ins><kbd><label><legend><li><link><map><meta><meta /><noscript><ol><optgroup><option><p><param><param /><pre><q><samp><select><small><span><strong><style><sub><sup><table><tbody><td><textarea><tfoot><th><thead><title><tr><tt><ul><var><wbr><wbr />';

		$string = strip_tags( $string, $allowable_tags );

		return $string;
	}

	/**
	 * Override this if the field should allow html tags to be saved with the entry value. Default is false.
	 *
	 * @return bool
	 */
	public function allow_html() {

		return false;
	}

	/**
	 * Fields should override this method to implement the appropriate sanitization specific to the field type before the value is saved.
	 *
	 * This base method will only strip HTML tags if the field or the gform_allowable_tags filter allows HTML.
	 *
	 * @param string $value   The field value to be processed.
	 * @param int    $form_id The ID of the form currently being processed.
	 *
	 * @return string
	 */
	public function sanitize_entry_value( $value, $form_id ) {

		if ( is_array( $value ) ) {
			return '';
		}

		$allowable_tags = $this->get_allowable_tags( $form_id );

		if ( $allowable_tags === true ) {

			// HTML is expected. Output will not be encoded so the value will stripped of scripts and some tags and encoded.
			$return = wp_kses_post( $value );

		} elseif ( $allowable_tags === false ) {

			// HTML is not expected. Output will be encoded.
			$return = $value;

		} else {

			// Some HTML is expected. Output will not be encoded so the value will stripped of scripts and some tags and encoded.
			$value = wp_kses_post( $value );

			// Strip all tags except those allowed by the gform_allowable_tags filter.
			$return = strip_tags( $value, $allowable_tags );
		}

		return $return;
	}

	/**
	 * Forces settings into expected values while saving the form object.
	 *
	 * No escaping should be done at this stage to prevent double escaping on output.
	 *
	 * Currently called only for forms created after version 1.9.6.10.
	 *
	 */
	public function sanitize_settings() {
		$this->id     = absint( $this->id );
		$this->type   = wp_strip_all_tags( $this->type );
		$this->formId = absint( $this->formId );

		$this->label       = $this->maybe_wp_kses( $this->label );
		$this->adminLabel  = $this->maybe_wp_kses( $this->adminLabel );
		$this->description = $this->maybe_wp_kses( $this->description );

		$this->isRequired = (bool) $this->isRequired;

		$this->allowsPrepopulate = (bool) $this->allowsPrepopulate;

		$this->inputMask      = (bool) $this->inputMask;
		$this->inputMaskValue = wp_strip_all_tags( $this->inputMaskValue );

		if ( $this->inputMaskIsCustom !== '' ) {
			$this->inputMaskIsCustom = (bool) $this->inputMaskIsCustom;
		}

		if ( $this->maxLength ) {
			$this->maxLength = absint( $this->maxLength );
		}

		if ( $this->inputType ) {
			$this->inputType = wp_strip_all_tags( $this->inputType );
		}

		if ( $this->size ) {
			$this->size = GFCommon::whitelist( $this->size, $this->get_size_choices( true ) );
		}

		if ( $this->errorMessage ) {
			$this->errorMessage = sanitize_text_field( $this->errorMessage );
		}

		if ( $this->labelPlacement ) {
			$this->labelPlacement = wp_strip_all_tags( $this->labelPlacement );
		}

		if ( $this->descriptionPlacement ) {
			$this->descriptionPlacement = wp_strip_all_tags( $this->descriptionPlacement );
		}

		if ( $this->subLabelPlacement ) {
			$this->subLabelPlacement = wp_strip_all_tags( $this->subLabelPlacement );
		}

		if ( $this->placeholder ) {
			$this->placeholder = sanitize_text_field( $this->placeholder );
		}

		if ( $this->cssClass ) {
			$this->cssClass = wp_strip_all_tags( $this->cssClass );
		}

		if ( $this->inputName ) {
			$this->inputName = wp_strip_all_tags( $this->inputName );
		}

		$this->visibility = wp_strip_all_tags( $this->visibility );
		$this->noDuplicates = (bool) $this->noDuplicates;

		if ( $this->defaultValue ) {
			$this->defaultValue = $this->maybe_wp_kses( $this->defaultValue );
		}

		if ( is_array( $this->inputs ) ) {
			foreach ( $this->inputs as &$input ) {
				if ( isset( $input['id'] ) ) {
					$input['id'] = wp_strip_all_tags( $input['id'] );
				}
				if ( isset( $input['customLabel'] ) ) {
					$input['customLabel'] = $this->maybe_wp_kses( $input['customLabel'] );
				}
				if ( isset( $input['label'] ) ) {
					$input['label'] = $this->maybe_wp_kses( $input['label'] );
				}
				if ( isset( $input['name'] ) ) {
					$input['name'] = wp_strip_all_tags( $input['name'] );
				}

				if ( isset( $input['placeholder'] ) ) {
					$input['placeholder'] = sanitize_text_field( $input['placeholder'] );
				}

				if ( isset( $input['defaultValue'] ) ) {
					$input['defaultValue'] = wp_strip_all_tags( $input['defaultValue'] );
				}
			}
		}

		$this->sanitize_settings_choices();
		$this->sanitize_settings_conditional_logic();

	}

	/**
	 * Sanitize the field choices property.
	 *
	 * @param array|null $choices The field choices property.
	 *
	 * @return array|null
	 */
	public function sanitize_settings_choices( $choices = null ) {

		if ( is_null( $choices ) ) {
			$choices = &$this->choices;
		}

		if ( ! is_array( $choices ) ) {
			return $choices;
		}

		foreach ( $choices as &$choice ) {
			if ( isset( $choice['isSelected'] ) ) {
				$choice['isSelected'] = (bool) $choice['isSelected'];
			}

			if ( isset( $choice['price'] ) && ! empty( $choice['price'] ) ) {
				$price_number    = GFCommon::to_number( $choice['price'] );
				$choice['price'] = GFCommon::to_money( $price_number );
			}

			if ( isset( $choice['text'] ) ) {
				$choice['text'] = $this->maybe_wp_kses( $choice['text'] );
			}

			if ( isset( $choice['value'] ) ) {
				// Strip scripts but don't encode
				$allowed_protocols = wp_allowed_protocols();
				$choice['value']   = wp_kses_no_null( $choice['value'], array( 'slash_zero' => 'keep' ) );
				$choice['value']   = wp_kses_hook( $choice['value'], 'post', $allowed_protocols );
				$choice['value']   = wp_kses_split( $choice['value'], 'post', $allowed_protocols );
			}
		}

		return $choices;
	}

	/**
	 * Sanitize the field conditional logic object.
	 *
	 * @param array|null $logic The field conditional logic object.
	 *
	 * @return array|null
	 */
	public function sanitize_settings_conditional_logic( $logic = null ) {

		if ( is_null( $logic ) ) {
			$logic = &$this->conditionalLogic;
		}
		$logic = GFFormsModel::sanitize_conditional_logic( $logic );

		return $logic;
	}

	/**
	 * Applies wp_kses() if the current user doesn't have the unfiltered_html capability
	 *
	 * @param $html
	 * @param string $allowed_html
	 * @param array  $allowed_protocols
	 *
	 * @return string
	 */
	public function maybe_wp_kses( $html, $allowed_html = 'post', $allowed_protocols = array() ) {
		return GFCommon::maybe_wp_kses( $html, $allowed_html, $allowed_protocols );
	}

	/**
	 * Returns the allowed HTML tags for the field value.
	 *
	 * FALSE disallows HTML tags.
	 * TRUE allows all HTML tags allowed by wp_kses_post().
	 * A string of HTML tags allowed. e.g. '<p><a><strong><em>'
	 *
	 * @param null|int $form_id If not specified the form_id field property is used.
	 *
	 * @return bool|string TRUE, FALSE or a string of tags.
	 */
	public function get_allowable_tags( $form_id = null ) {
		if ( empty( $form_id ) ) {
			$form_id = $this->form_id;
		}
		$form_id    = absint( $form_id );
		$allow_html = $this->allow_html();

		/**
		 * Allows the list of tags allowed in the field value to be modified.
		 *
		 * Return FALSE to disallow HTML tags.
		 * Return TRUE to allow all HTML tags allowed by wp_kses_post().
		 * Return a string of HTML tags allowed. e.g. '<p><a><strong><em>'
		 *
		 * @since Unknown
		 *
		 * @param bool     $allow_html
		 * @param GF_Field $this
		 * @param int      $form_id
		 */
		$allowable_tags = apply_filters( 'gform_allowable_tags', $allow_html, $this, $form_id );
		$allowable_tags = apply_filters( "gform_allowable_tags_{$form_id}", $allowable_tags, $this, $form_id );

		return $allowable_tags;
	}

	/**
	 * Actions to be performed after the field has been converted to an object.
	 *
	 * @since  2.1.2.7
	 * @access public
	 *
	 * @uses    GF_Field::failed_validation()
	 * @uses    GF_Field::validation_message()
	 * @used-by GFFormsModel::convert_field_objects()
	 *
	 * @return void
	 */
	public function post_convert_field() {
		// Fix an issue where fields can show up as invalid in the form editor if the form was updated using the form object returned after a validation failure.
		unset( $this->failed_validation );
		unset( $this->validation_message );
	}

	/**
	 * Returns the choices for the Field Size setting.
	 *
	 * @since 2.4.19
	 *
	 * @param bool $values_only Indicates if only the choice values should be returned.
	 *
	 * @return array
	 */
	public function get_size_choices( $values_only = false ) {
		$choices = array(
			array( 'value' => 'small', 'text' => __( 'Small', 'gravityforms' ) ),
			array( 'value' => 'medium', 'text' => __( 'Medium', 'gravityforms' ) ),
			array( 'value' => 'large', 'text' => __( 'Large', 'gravityforms' ) ),
		);

		/**
		 * Allows the choices for Field Size setting to be customized.
		 *
		 * @since 2.4.19
		 *
		 * @param array $choices An array of choices (value and text) to be included in the Field Size setting.
		 */
		$choices = apply_filters( 'gform_field_size_choices', $choices );

		return $values_only ? wp_list_pluck( $choices, 'value' ) : $choices;
	}

	// # FIELD FILTER UI HELPERS ---------------------------------------------------------------------------------------

	/**
	 * Returns the filter settings for the current field.
	 *
	 * If overriding to add custom settings call the parent method first to get the default settings.
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public function get_filter_settings() {
		$filter_settings = array(
			'key'  => $this->id,
			'text' => GFFormsModel::get_label( $this ),
		);

		$sub_filters = $this->get_filter_sub_filters();
		if ( ! empty( $sub_filters ) ) {
			$filter_settings['group']   = true;
			$filter_settings['filters'] = $sub_filters;
		} else {
			$filter_settings['preventMultiple'] = false;
			$filter_settings['operators']       = $this->get_filter_operators();

			$values = $this->get_filter_values();
			if ( ! empty( $values ) ) {
				$filter_settings['values'] = $values;
			}
		}

		return $filter_settings;
	}

	/**
	 * Returns the filter operators for the current field.
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public function get_filter_operators() {
		return array( 'is', 'isnot', '>', '<' );
	}

	/**
	 * Returns the filters values setting for the current field.
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public function get_filter_values() {
		if ( ! is_array( $this->choices ) ) {
			return array();
		}

		$choices = $this->choices;
		if ( $this->type == 'post_category' ) {
			foreach ( $choices as &$choice ) {
				$choice['value'] = $choice['text'] . ':' . $choice['value'];
			}
		}

		if ( $this->enablePrice ) {
			foreach ( $choices as &$choice ) {
				$price = rgempty( 'price', $choice ) ? 0 : GFCommon::to_number( rgar( $choice, 'price' ) );

				$choice['value'] .= '|' . $price;
			}
		}

		return $choices;
	}

	/**
	 * Returns the sub-filters for the current field.
	 *
	 * @since  2.4
	 *
	 * @return array
	 */
	public function get_filter_sub_filters() {
		return array();
	}

}
