<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Field_Checkbox extends GF_Field {

	/**
	 * @var string $type The field type.
	 */
	public $type = 'checkbox';





	// # FORM EDITOR & FIELD MARKUP -------------------------------------------------------------------------------------

	/**
	 * Returns the field title.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {

		return esc_attr__( 'Checkboxes', 'gravityforms' );

	}

	/**
	 * The class names of the settings which should be available on the field in the form editor.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings() {

		return array(
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'choices_setting',
			'rules_setting',
			'visibility_setting',
			'description_setting',
			'css_class_setting',
			'select_all_choices_setting',
		);

	}

	/**
	 * Indicate if this field type can be used when configuring conditional logic rules.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return bool
	 */
	public function is_conditional_logic_supported() {

		return true;

	}

	/**
	 * Returns the field inner markup.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param array        $form  The Form Object currently being processed.
	 * @param string|array $value The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param null|array   $entry Null or the Entry Object currently being edited.
	 *
	 * @uses GF_Field::is_entry_detail()
	 * @uses GF_Field::is_form_editor()
	 * @uses GF_Field_Checkbox::get_checkbox_choices()
	 *
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {

		$form_id         = absint( $form['id'] );
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$id            = $this->id;
		$field_id      = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
		$disabled_text = $is_form_editor ? 'disabled="disabled"' : '';

		return sprintf(
			"<div class='ginput_container ginput_container_checkbox'><ul class='gfield_checkbox' id='%s'>%s</ul></div>",
			esc_attr( $field_id ),
			$this->get_checkbox_choices( $value, $disabled_text, $form_id )
		);

	}





	// # SUBMISSION -----------------------------------------------------------------------------------------------------

	/**
	 * Retrieve the field value on submission.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param array     $field_values             The dynamic population parameter names with their corresponding values to be populated.
	 * @param bool|true $get_from_post_global_var Whether to get the value from the $_POST array as opposed to $field_values.
	 *
	 * @uses GFFormsModel::choice_value_match()
	 * @uses GFFormsModel::get_parameter_value()
	 *
	 * @return array|string
	 */
	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {

		// Get parameter values for field.
		$parameter_values = GFFormsModel::get_parameter_value( $this->inputName, $field_values, $this );

		// If parameter values exist but are not an array, convert to array.
		if ( ! empty( $parameter_values ) && ! is_array( $parameter_values ) ) {
			$parameter_values = explode( ',', $parameter_values );
		}

		// If no inputs are defined, return an empty string.
		if ( ! is_array( $this->inputs ) ) {
			return '';
		}

		// Set initial choice index.
		$choice_index = 0;

		// Initialize submission value array.
		$value = array();

		// Loop through field inputs.
		foreach ( $this->inputs as $input ) {

			if ( ! empty( $_POST[ 'is_submit_' . $this->formId ] ) && $get_from_post_global_var ) {

				$input_value = rgpost( 'input_' . str_replace( '.', '_', strval( $input['id'] ) ) );

				$value[ strval( $input['id'] ) ] = $input_value;

			} else {

				if ( is_array( $parameter_values ) ) {

					foreach ( $parameter_values as $item ) {

						$item = trim( $item );

						if ( GFFormsModel::choice_value_match( $this, $this->choices[ $choice_index ], $item ) ) {
							$value[ $input['id'] . '' ] = $item;
							break;
						}

					}

				}

			}

			// Increase choice index.
			$choice_index ++;

		}

		return $value;

	}





	// # ENTRY RELATED --------------------------------------------------------------------------------------------------

	/**
	 * Format the entry value for display on the entries list page.
	 *
	 * Return a value that's safe to display on the page.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param string|array $value    The field value.
	 * @param array        $entry    The Entry Object currently being processed.
	 * @param string       $field_id The field or input ID currently being processed.
	 * @param array        $columns  The properties for the columns being displayed on the entry list page.
	 * @param array        $form     The Form Object currently being processed.
	 *
	 * @uses GFCommon::implode_non_blank()
	 * @uses GFCommon::prepare_post_category_value()
	 * @uses GFCommon::selection_display()
	 * @uses GF_Field_Checkbox::is_checkbox_checked()
	 *
	 * @return string
	 */
	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {

		// If this is the main checkbox field (not an input), display a comma separated list of all inputs.
		if ( absint( $field_id ) == $field_id ) {

			$lead_field_keys = array_keys( $entry );
			$items           = array();

			foreach ( $lead_field_keys as $input_id ) {
				if ( is_numeric( $input_id ) && absint( $input_id ) == $field_id ) {
					$items[] = GFCommon::selection_display( rgar( $entry, $input_id ), null, $entry['currency'], false );
				}
			}

			$value = GFCommon::implode_non_blank( ', ', $items );

			// Special case for post category checkbox fields.
			if ( $this->type == 'post_category' ) {
				$value = GFCommon::prepare_post_category_value( $value, $this, 'entry_list' );
			}

		} else {

			$value = '';

			if ( ! rgblank( $this->is_checkbox_checked( $field_id, $columns[ $field_id ]['label'], $entry ) ) ) {
				$value = "<i class='fa fa-check gf_valid'></i>";
			}

		}

		return $value;

	}

	/**
	 * Format the entry value for display on the entry detail page and for the {all_fields} merge tag.
	 *
	 * Return a value that's safe to display for the context of the given $format.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param string|array $value    The field value.
	 * @param string       $currency The entry currency code.
	 * @param bool|false   $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param string       $format   The format requested for the location the merge is being used. Possible values: html, text or url.
	 * @param string       $media    The location where the value will be displayed. Possible values: screen or email.
	 *
	 * @uses GFCommon::selection_display()
	 *
	 * @return string
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		if ( is_array( $value ) ) {

			$items = '';

			foreach ( $value as $key => $item ) {
				if ( ! rgblank( $item ) ) {
					switch ( $format ) {
						case 'text' :
							$items .= GFCommon::selection_display( $item, $this, $currency, $use_text ) . ', ';
							break;

						default:
							$items .= '<li>' . GFCommon::selection_display( $item, $this, $currency, $use_text ) . '</li>';
							break;
					}
				}
			}

			if ( empty( $items ) ) {
				return '';
			} elseif ( $format == 'text' ) {
				return substr( $items, 0, strlen( $items ) - 2 ); // Removing last comma.
			} else {
				return "<ul class='bulleted'>$items</ul>";
			}

		} else {

			return $value;

		}

	}

	/**
	 * Gets merge tag values.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFCommon::to_money()
	 * @uses GFCommon::format_post_category()
	 * @uses GFFormsModel::is_field_hidden()
	 * @uses GFFormsModel::get_choice_text()
	 * @uses GFCommon::format_variable_value()
	 * @uses GFCommon::implode_non_blank()
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
	 * @uses GFCommon::format_post_category()
	 * @uses GFCommon::format_variable_value()
	 * @uses GFCommon::implode_non_blank()
	 * @uses GFCommon::to_money()
	 * @uses GFFormsModel::is_field_hidden()
	 *
	 * @return string The processed merge tag.
	 */
	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {

		// Check for passed modifiers.
		$use_value       = $modifier == 'value';
		$use_price       = in_array( $modifier, array( 'price', 'currency' ) );
		$format_currency = $modifier == 'currency';

		if ( is_array( $raw_value ) && (string) intval( $input_id ) != $input_id ) {
			$items = array( $input_id => $value ); // Float input IDs. (i.e. 4.1 ). Used when targeting specific checkbox items.
		} elseif ( is_array( $raw_value ) ) {
			$items = $raw_value;
		} else {
			$items = array( $input_id => $raw_value );
		}

		$ary = array();

		// Get the items available within the merge tags.
		foreach ( $items as $input_id => $item ) {

			// If the 'value' modifier was passed.
			if ( $use_value ) {

				list( $val, $price ) = rgexplode( '|', $item, 2 );

			// If the 'price' or 'currency' modifiers were passed.
			} elseif ( $use_price ) {

				list( $name, $val ) = rgexplode( '|', $item, 2 );

				if ( $format_currency ) {
					$val = GFCommon::to_money( $val, rgar( $entry, 'currency' ) );
				}

			// If this is a post category checkbox.
			} else if ( $this->type == 'post_category' ) {

				$use_id     = strtolower( $modifier ) == 'id';
				$item_value = GFCommon::format_post_category( $item, $use_id );

				$val = GFFormsModel::is_field_hidden( $form, $this, array(), $entry ) ? '' : $item_value;

			// If no modifiers were passed.
			} else {

				$val = GFFormsModel::is_field_hidden( $form, $this, array(), $entry ) ? '' : RGFormsModel::get_choice_text( $this, $raw_value, $input_id );

			}

			$ary[] = GFCommon::format_variable_value( $val, $url_encode, $esc_html, $format );

		}

		return GFCommon::implode_non_blank( ', ', $ary );

	}

	/**
	 * Sanitize and format the value before it is saved to the Entry Object.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param string $value      The value to be saved.
	 * @param array  $form       The Form Object currently being processed.
	 * @param string $input_name The input name used when accessing the $_POST.
	 * @param int    $lead_id    The ID of the Entry currently being processed.
	 * @param array  $lead       The Entry Object currently being processed.
	 *
	 * @uses GF_Field_Checkbox::sanitize_entry_value()
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
	 * Format the entry value before it is used in entry exports and by framework add-ons using GFAddOn::get_field_value().
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param array      $entry    The entry currently being processed.
	 * @param string     $input_id The field or input ID.
	 * @param bool|false $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param bool|false $is_csv   Is the value going to be used in the .csv entries export?
	 *
	 * @uses GFCommon::get_label()
	 * @uses GFCommon::selection_display()
	 * @uses GF_Field_Checkbox::is_checkbox_checked()
	 *
	 * @return string
	 */
	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {

		if ( empty( $input_id ) || absint( $input_id ) == $input_id ) {

			$selected = array();

			foreach ( $this->inputs as $input ) {

				$index = (string) $input['id'];

				if ( ! rgempty( $index, $entry ) ) {
					$selected[] = GFCommon::selection_display( rgar( $entry, $index ), $this, rgar( $entry, 'currency' ), $use_text );
				}

			}

			return implode( ', ', $selected );

		} else if ( $is_csv ) {

			$value = $this->is_checkbox_checked( $input_id, GFCommon::get_label( $this, $input_id ), $entry );

			return empty( $value ) ? '' : $value;

		} else {

			return GFCommon::selection_display( rgar( $entry, $input_id ), $this, rgar( $entry, 'currency' ), $use_text );

		}

	}





	// # INPUT ATTRIBUTE HELPERS ----------------------------------------------------------------------------------------

	/**
	 * Get checkbox choice inputs for field.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param string|array $value         The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param string       $disabled_text The HTML disabled attribute.
	 * @param int          $form_id       The current form ID.
	 *
	 * @uses GFCommon::to_number()
	 * @uses GF_Field::get_conditional_logic_event()
	 * @uses GF_Field::get_tabindex()
	 * @uses GF_Field::is_entry_detail()
	 * @uses GF_Field::is_form_editor()
	 * @uses GFFormsModel::choice_value_match()
	 *
	 * @return string
	 */
	public function get_checkbox_choices( $value, $disabled_text, $form_id = 0 ) {

		$choices = '';
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		if ( is_array( $this->choices ) ) {

			$choice_number = 1;
			$count         = 1;

			// Add Select All choice.
			if ( $this->enableSelectAll ) {

				/**
				 * Modify the "Select All" checkbox label.
				 *
				 * @since 2.3
				 *
				 * @param string $select_label The "Select All" label.
				 * @param object $field        The field currently being processed.
				 */
				$select_label = gf_apply_filters( array( 'gform_checkbox_select_all_label', $this->formId, $this->id ), esc_html__( 'Select All', 'gravityforms' ), $this );
				$select_label = esc_html( $select_label );
				
				/**
				 * Modify the "Deselect All" checkbox label.
				 *
				 * @since 2.3
				 *
				 * @param string $deselect_label The "Deselect All" label.
				 * @param object $field          The field currently being processed.
				 */
				$deselect_label = gf_apply_filters( array( 'gform_checkbox_deselect_all_label', $this->formId, $this->id ), esc_html__( 'Deselect All', 'gravityforms' ), $this );
				$deselect_label = esc_html( $deselect_label );

				// Get tabindex.
				$tabindex = $this->get_tabindex();

				// Prepare choice ID.
				$id = 'choice_' . $this->id . '_select_all';

				// Prepare choice markup.
				$choice_markup = "<li class='gchoice_select_all'>
						<input type='checkbox' id='{$id}' {$tabindex} {$disabled_text} onclick='gformToggleCheckboxes( this )' onkeypress='gformToggleCheckboxes( this )' />
						<label for='{$id}' id='label_" . $this->id . "_select_all' data-label-select='{$select_label}' data-label-deselect='{$deselect_label}'>{$select_label}</label>
					</li>";

				/**
				 * Override the default choice markup used when rendering radio button, checkbox and drop down type fields.
				 *
				 * @since 1.9.6
				 *
				 * @param string $choice_markup The string containing the choice markup to be filtered.
				 * @param array  $choice        An associative array containing the choice properties.
				 * @param object $field         The field currently being processed.
				 * @param string $value         The value to be selected if the field is being populated.
				 */
				$choices .= gf_apply_filters( array( 'gform_field_choice_markup_pre_render', $this->formId, $this->id ), $choice_markup, array(), $this, $value );

			}

			// Loop through field choices.
			foreach ( $this->choices as $choice ) {

				// Hack to skip numbers ending in 0, so that 5.1 doesn't conflict with 5.10.
				if ( $choice_number % 10 == 0 ) {
					$choice_number ++;
				}

				// Prepare input ID.
				$input_id = $this->id . '.' . $choice_number;

				if ( $is_entry_detail || $is_form_editor || $form_id == 0 ) {
					$id = $this->id . '_' . $choice_number ++;
				} else {
					$id = $form_id . '_' . $this->id . '_' . $choice_number ++;
				}

				if ( ( $is_form_editor || ( ! isset( $_GET['gf_token'] ) && empty( $_POST ) ) ) && rgar( $choice, 'isSelected' ) ) {
					$checked = "checked='checked'";
				} elseif ( is_array( $value ) && GFFormsModel::choice_value_match( $this, $choice, rgget( $input_id, $value ) ) ) {
					$checked = "checked='checked'";
				} elseif ( ! is_array( $value ) && GFFormsModel::choice_value_match( $this, $choice, $value ) ) {
					$checked = "checked='checked'";
				} else {
					$checked = '';
				}

				$tabindex     = $this->get_tabindex();
				$choice_value = $choice['value'];

				if ( $this->enablePrice ) {
					$price = rgempty( 'price', $choice ) ? 0 : GFCommon::to_number( rgar( $choice, 'price' ) );
					$choice_value .= '|' . $price;
				}

				$choice_value  = esc_attr( $choice_value );
				$choice_markup = "<li class='gchoice_{$id}'>
								<input name='input_{$input_id}' type='checkbox'  value='{$choice_value}' {$checked} id='choice_{$id}' {$tabindex} {$disabled_text} />
								<label for='choice_{$id}' id='label_{$id}'>{$choice['text']}</label>
							</li>";

				/**
				 * Override the default choice markup used when rendering radio button, checkbox and drop down type fields.
				 *
				 * @since 1.9.6
				 *
				 * @param string $choice_markup The string containing the choice markup to be filtered.
				 * @param array  $choice        An associative array containing the choice properties.
				 * @param object $field         The field currently being processed.
				 * @param string $value         The value to be selected if the field is being populated.
				 */
				$choices .= gf_apply_filters( array( 'gform_field_choice_markup_pre_render', $this->formId, $this->id ), $choice_markup, $choice, $this, $value );

				$is_admin = $is_entry_detail || $is_form_editor;

				if ( $is_admin && rgget('view') != 'entry' && $count >= 5 ) {
					break;
				}

				$count ++;

			}

			$total = sizeof( $this->choices );

			if ( $count < $total ) {
				$choices .= "<li class='gchoice_total'>" . sprintf( esc_html__( '%d of %d items shown. Edit field to view all', 'gravityforms' ), $count, $total ) . '</li>';
			}

		}

		/**
		 * Modify the checkbox items before they are added to the checkbox list.
		 *
		 * @since Unknown
		 *
		 * @param string $choices The string containing the choices to be filtered.
		 * @param object $field   Ahe field currently being processed.
		 */
		return gf_apply_filters( array( 'gform_field_choices', $this->formId, $this->id ), $choices, $this );

	}

	/**
	 * Determine if a specific checkbox is checked.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param int    $field_id    Field ID.
	 * @param string $field_label Field label.
	 * @param array  $entry       Entry object.
	 *
	 * @return bool
	 */
	public function is_checkbox_checked( $field_id, $field_label, $entry ) {

		$allowed_tags = wp_kses_allowed_html( 'post' );

		$entry_field_keys = array_keys( $entry );

		// Looping through lead detail values trying to find an item identical to the column label. Mark with a tick if found.
		foreach ( $entry_field_keys as $input_id ) {

			// Mark as a tick if input label (from form meta) is equal to submitted value (from lead)
			if ( is_numeric( $input_id ) && absint( $input_id ) == absint( $field_id ) ) {

				$sanitized_value = wp_kses( $entry[ $input_id ], $allowed_tags );
				$sanitized_label = wp_kses( $field_label, $allowed_tags );

				if ( $sanitized_value == $sanitized_label ) {

					return $entry[ $input_id ];

				} else {
	
					if ( $this->enableChoiceValue || $this->enablePrice ) {

						foreach ( $this->choices as $choice ) {

							if ( $choice['value'] == $entry[ $field_id ] ) {

								return $choice['value'];

							} else if ( $this->enablePrice ) {

								$ary   = explode( '|', $entry[ $field_id ] );
								$val   = count( $ary ) > 0 ? $ary[0] : '';
								$price = count( $ary ) > 1 ? $ary[1] : '';

								if ( $val == $choice['value'] ) {
									return $choice['value'];
								}

							}

						}

					}

				}

			}

		}

		return false;

	}





	// # OTHER HELPERS --------------------------------------------------------------------------------------------------

	/**
	 * Returns the input ID to be assigned to the field label for attribute.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param array $form The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_first_input_id( $form ) {

		return '';

	}

	/**
	 * Retrieve the field default value.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFCommon::replace_variables_prepopulate()
	 * @uses GF_Field::is_form_editor()
	 *
	 * @return array|string
	 */
	public function get_value_default() {

		return $this->is_form_editor() ? $this->defaultValue : GFCommon::replace_variables_prepopulate( $this->defaultValue );

	}





	// # SANITIZATION ---------------------------------------------------------------------------------------------------

	/**
	 * If the field should allow html tags to be saved with the entry value. Default is false.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return bool
	 */
	public function allow_html() {

		return true;

	}

	/**
	 * Forces settings into expected values while saving the form object.
	 *
	 * No escaping should be done at this stage to prevent double escaping on output.
	 *
	 * Currently called only for forms created after version 1.9.6.10.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 */
	public function sanitize_settings() {

		parent::sanitize_settings();

		if ( 'option' === $this->type ) {
			$this->productField = absint( $this->productField );
		}

		if ( 'post_category' === $this->type ) {
			$this->displayAllCategories = (bool) $this->displayAllCategories;
		}

	}

	/**
	 * Strip scripts and some HTML tags.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param string $value   The field value to be processed.
	 * @param int    $form_id The ID of the form currently being processed.
	 *
	 * @uses GF_Field::get_allowable_tags()
	 *
	 * @return string
	 */
	public function sanitize_entry_value( $value, $form_id ) {

		// If the value is an array, return an empty string.
		if ( is_array( $value ) ) {
			return '';
		}

		// Get allowable tags for field value.
		$allowable_tags = $this->get_allowable_tags( $form_id );

		// If allowable tags are defined, strip unallowed tags.
		if ( $allowable_tags !== true ) {
			$value = strip_tags( $value, $allowable_tags );
		}

		// Sanitize value.
		$allowed_protocols = wp_allowed_protocols();
		$value             = wp_kses_no_null( $value, array( 'slash_zero' => 'keep' ) );
		$value             = wp_kses_hook( $value, 'post', $allowed_protocols );
		$value             = wp_kses_split( $value, 'post', $allowed_protocols );

		return $value;

	}

	// # FIELD FILTER UI HELPERS ---------------------------------------------------------------------------------------

	/**
	 * Returns the filter operators for the current field.
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public function get_filter_operators() {
		return array( 'is' );
	}

}

GF_Fields::register( new GF_Field_Checkbox() );
