<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_Radio extends GF_Field {

	public $type = 'radio';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Radio Buttons', 'gravityforms' );
	}

	function get_form_editor_field_settings() {
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
			'duplicate_setting',
			'description_setting',
			'css_class_setting',
			'other_choice_setting',
		);
	}

	public function is_conditional_logic_supported() {
		return true;
	}

	public function validate( $value, $form ) {
		if ( $this->isRequired && $this->enableOtherChoice && rgpost( "input_{$this->id}" ) == 'gf_other_choice' ) {
			if ( empty( $value ) || strtolower( $value ) == strtolower( GFCommon::get_other_choice_value( $this ) ) ) {
				$this->failed_validation  = true;
				$this->validation_message = empty( $this->errorMessage ) ? esc_html__( 'This field is required.', 'gravityforms' ) : $this->errorMessage;
			}
		}
	}

	public function get_first_input_id( $form ) {
		return '';
	}

	public function get_field_input( $form, $value = '', $entry = null ) {

		$form_id         = $form['id'];
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$id            = $this->id;
		$field_id      = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
		$disabled_text = $is_form_editor ? 'disabled="disabled"' : '';

		return sprintf( "<div class='ginput_container ginput_container_radio'><ul class='gfield_radio' id='%s'>%s</ul></div>", $field_id, $this->get_radio_choices( $value, $disabled_text, $form_id ) );

	}

	public function get_radio_choices( $value = '', $disabled_text = '', $form_id = 0 ) {
		$choices = '';

		if ( is_array( $this->choices ) ) {
			$is_entry_detail    = $this->is_entry_detail();
			$is_form_editor     = $this->is_form_editor();
			$is_admin           = $is_entry_detail || $is_form_editor;

			$field_choices      = $this->choices;
			$needs_other_choice = $this->enableOtherChoice;
			$editor_limited     = false;

			$choice_id = 0;
			$count     = 1;

			foreach ( $field_choices as $choice ) {
				if ( rgar( $choice, 'isOtherChoice' ) ) {
					if ( ! $needs_other_choice ) {
						continue;
					}
					$needs_other_choice = false;
				}

				$choices .= $this->get_choice_html( $choice, $choice_id, $value, $disabled_text, $is_admin );

				if ( $is_form_editor && $count >= 5 ) {
					$editor_limited = true;
					break;
				}

				$count ++;
			}

			if ( $needs_other_choice ) {
				$other_choice    = array(
					'text'          => GFCommon::get_other_choice_value( $this ),
					'value'         => 'gf_other_choice',
					'isSelected'    => false,
					'isOtherChoice' => true,
				);
				$field_choices[] = $other_choice;

				if ( ! $is_form_editor || ! $editor_limited ) {
					$choices .= $this->get_choice_html( $other_choice, $choice_id, $value, $disabled_text, $is_admin );
					$count ++;
				}
			}

			$total = sizeof( $field_choices );
			if ( $is_form_editor && ( $count < $total ) ) {
				$choices .= "<li class='gchoice_total'>" . sprintf( esc_html__( '%d of %d items shown. Edit field to view all', 'gravityforms' ), $count, $total ) . '</li>';
			}
		}

		/**
		 * Allows the HTML for multiple choices to be overridden.
		 *
		 * @since unknown
		 *
		 * @param string         $choices The choices HTML.
		 * @param GF_Field_Radio $field   The current field object.
		 */
		return gf_apply_filters( array( 'gform_field_choices', $this->formId ), $choices, $this );
	}

	/**
	 * Returns the choice HTML.
	 *
	 * @since 2.4.17
	 *
	 * @param array  $choice        The choice properties.
	 * @param int    &$choice_id    The choice number.
	 * @param string $value         The current field value.
	 * @param string $disabled_text The disabled attribute or an empty string.
	 * @param bool   $is_admin      Indicates if this is the form editor or entry detail page.
	 *
	 * @return string
	 */
	public function get_choice_html( $choice, &$choice_id, $value, $disabled_text, $is_admin ) {
		$form_id = absint( $this->formId );

		if ( $is_admin || $form_id == 0 ) {
			$id = $this->id . '_' . $choice_id ++;
		} else {
			$id = $form_id . '_' . $this->id . '_' . $choice_id ++;
		}

		$field_value = ! empty( $choice['value'] ) || $this->enableChoiceValue ? $choice['value'] : $choice['text'];

		if ( $this->enablePrice ) {
			$price       = rgempty( 'price', $choice ) ? 0 : GFCommon::to_number( rgar( $choice, 'price' ) );
			$field_value .= '|' . $price;
		}

		if ( rgblank( $value ) && rgget( 'view' ) != 'entry' ) {
			$checked = rgar( $choice, 'isSelected' ) ? "checked='checked'" : '';
		} else {
			$checked = GFFormsModel::choice_value_match( $this, $choice, $value ) ? "checked='checked'" : '';
		}

		$tabindex    = $this->get_tabindex();
		$label       = sprintf( "<label for='choice_%s' id='label_%s'>%s</label>", $id, $id, $choice['text'] );
		$input_focus = '';

		// Handle 'other' choice.
		if ( $this->enableOtherChoice && rgar( $choice, 'isOtherChoice' ) ) {
			$other_default_value = empty( $choice['text'] ) ? GFCommon::get_other_choice_value( $this ) : $choice['text'];

			$onfocus = ! $is_admin ? 'jQuery(this).prev("input")[0].click(); if(jQuery(this).val() == "' . $other_default_value . '") { jQuery(this).val(""); }' : '';
			$onblur  = ! $is_admin ? 'if(jQuery(this).val().replace(" ", "") == "") { jQuery(this).val("' . $other_default_value . '"); }' : '';

			$input_focus  = ! $is_admin ? "onfocus=\"jQuery(this).next('input').focus();\"" : '';
			$value_exists = GFFormsModel::choices_value_match( $this, $this->choices, $value );

			if ( $value == 'gf_other_choice' && rgpost( "input_{$this->id}_other" ) ) {
				$other_value = rgpost( "input_{$this->id}_other" );
			} elseif ( ! $value_exists && ! empty( $value ) ) {
				$other_value = $value;
				$value       = 'gf_other_choice';
				$checked     = "checked='checked'";
			} else {
				$other_value = $other_default_value;
			}

			$label = "<input id='input_{$this->formId}_{$this->id}_other' name='input_{$this->id}_other' type='text' value='" . esc_attr( $other_value ) . "' aria-label='" . esc_attr__( 'Other', 'gravityforms' ) . "' onfocus='$onfocus' onblur='$onblur' $tabindex $disabled_text />";
		}

		$choice_markup = sprintf( "<li class='gchoice_$id'><input name='input_%d' type='radio' value='%s' %s id='choice_%s' $tabindex %s %s />%s</li>", $this->id, esc_attr( $field_value ), $checked, $id, $disabled_text, $input_focus, $label );

		/**
		 * Allows the HTML for a specific choice to be overridden.
		 *
		 * @since 1.9.6
		 * @since 1.9.12 Added the field specific version.
		 * @since 2.4.17 Moved from GF_Field_Radio::get_radio_choices().
		 *
		 * @param string         $choice_markup The choice HTML.
		 * @param array          $choice        The choice properties.
		 * @param GF_Field_Radio $field         The current field object.
		 * @param string         $value         The current field value.
		 */
		return gf_apply_filters( array( 'gform_field_choice_markup_pre_render', $this->formId, $this->id ), $choice_markup, $choice, $this, $value );
	}

	public function get_value_default() {
		return $this->is_form_editor() ? $this->defaultValue : GFCommon::replace_variables_prepopulate( $this->defaultValue );
	}

	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {

		$value = $this->get_input_value_submission( 'input_' . $this->id, $this->inputName, $field_values, $get_from_post_global_var );
		if ( $value == 'gf_other_choice' ) {
			//get value from text box
			$value = $this->get_input_value_submission( 'input_' . $this->id . '_other', $this->inputName, $field_values, $get_from_post_global_var );
		}

		return $value;
	}

	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		return wp_kses_post( GFCommon::selection_display( $value, $this, $entry['currency'] ) );
	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		return wp_kses_post( GFCommon::selection_display( $value, $this, $currency, $use_text ) );
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
	 * @return string The processed merge tag.
	 */
	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		$modifiers       = $this->get_modifiers();
		$use_value       = in_array( 'value', $modifiers );
		$format_currency = ! $use_value && in_array( 'currency', $modifiers );
		$use_price       = $format_currency || ( ! $use_value && in_array( 'price', $modifiers ) );

		if ( is_array( $raw_value ) && (string) intval( $input_id ) != $input_id ) {
			$items = array( $input_id => $value ); // Float input Ids. (i.e. 4.1 ). Used when targeting specific checkbox items.
		} elseif ( is_array( $raw_value ) ) {
			$items = $raw_value;
		} else {
			$items = array( $input_id => $raw_value );
		}

		$ary = array();

		foreach ( $items as $input_id => $item ) {
			if ( $use_value ) {
				list( $val, $price ) = rgexplode( '|', $item, 2 );
			} elseif ( $use_price ) {
				list( $name, $val ) = rgexplode( '|', $item, 2 );
				if ( $format_currency ) {
					$val = GFCommon::to_money( $val, rgar( $entry, 'currency' ) );
				}
			} elseif ( $this->type == 'post_category' ) {
				$use_id     = strtolower( $modifier ) == 'id';
				$item_value = GFCommon::format_post_category( $item, $use_id );

				$val = RGFormsModel::is_field_hidden( $form, $this, array(), $entry ) ? '' : $item_value;
			} else {
				$val = RGFormsModel::is_field_hidden( $form, $this, array(), $entry ) ? '' : RGFormsModel::get_choice_text( $this, $raw_value, $input_id );
			}

			$ary[] = GFCommon::format_variable_value( $val, $url_encode, $esc_html, $format );
		}

		return GFCommon::implode_non_blank( ', ', $ary );
	}

	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {

		if ( $this->enableOtherChoice && $value == 'gf_other_choice' ) {
			$value = rgpost( "input_{$this->id}_other" );
		}

		$value = $this->sanitize_entry_value( $value, $form['id'] );

		return $value;
	}

	public function allow_html() {
		return true;
	}

	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		if ( empty( $input_id ) ) {
			$input_id = $this->id;
		}

		$value = rgar( $entry, $input_id );

		return $is_csv ? $value : GFCommon::selection_display( $value, $this, rgar( $entry, 'currency' ), $use_text );
	}

	/**
	 * Strip scripts and some HTML tags.
	 *
	 * @param string $value The field value to be processed.
	 * @param int $form_id The ID of the form currently being processed.
	 *
	 * @return string
	 */
	public function sanitize_entry_value( $value, $form_id ) {

		if ( is_array( $value ) ) {
			return '';
		}

		$allowable_tags = $this->get_allowable_tags( $form_id );

		if ( $allowable_tags !== true ) {
			$value = strip_tags( $value, $allowable_tags );
		}

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
		$operators = $this->type == 'product' ? array( 'is' ) : array( 'is', 'isnot', '>', '<' );

		return $operators;
	}

}

GF_Fields::register( new GF_Field_Radio() );
