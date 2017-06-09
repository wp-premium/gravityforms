<?php

// If the GF_Field class isn't available, bail.
if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Class GF_Field_MultiSelect
 *
 * Allows the creation of multiselect fields.
 *
 * @since Unknown
 *
 * @uses GF_Field
 */
class GF_Field_MultiSelect extends GF_Field {

	public $type = 'multiselect';

	/**
	 * Returns the field title.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return string The field title. Escaped.
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Multi Select', 'gravityforms' );
	}

	/**
	 * Returns the class names of the settings which should be available on the field in the form editor.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return array Settings available within the field editor.
	 */
	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'enable_enhanced_ui_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'size_setting',
			'choices_setting',
			'rules_setting',
			'visibility_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	/**
	 * Indicates this field type can be used when configuring conditional logic rules.
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
	 * @uses GF_Field_MultiSelect::is_entry_detail()
	 * @uses GF_Field_MultiSelect::is_form_editor()
	 * @uses GF_Field_MultiSelect::get_conditional_logic_event()
	 * @uses GF_Field_MultiSelect::get_tabindex()
	 *
	 * @param array        $form  The Form Object currently being processed.
	 * @param string|array $value The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param null|array   $entry Null or the Entry Object currently being edited.
	 *
	 * @return string The field input HTML markup.
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		$form_id         = absint( $form['id'] );
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$id       = $this->id;
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$logic_event   = $this->get_conditional_logic_event( 'keyup' );
		$size          = $this->size;
		$class_suffix  = $is_entry_detail ? '_admin' : '';
		$class         = $size . $class_suffix;
		$css_class     = trim( esc_attr( $class ) . ' gfield_select' );
		$tabindex      = $this->get_tabindex();
		$disabled_text = $is_form_editor ? 'disabled="disabled"' : '';


		/**
		 * Allow the placeholder used by the enhanced ui to be overridden
		 *
		 * @since 1.9.14 Third parameter containing the field ID was added.
		 * @since Unknown
		 *
		 * @param string  $placeholder The placeholder text.
		 * @param integer $form_id     The ID of the current form.
		 */
		$placeholder = gf_apply_filters( array(
			'gform_multiselect_placeholder',
			$form_id,
			$this->id
		), __( 'Click to select...', 'gravityforms' ), $form_id, $this );
		$placeholder = $this->enableEnhancedUI ? "data-placeholder='" . esc_attr( $placeholder ) . "'" : '';

		$size = $this->multiSelectSize;
		if ( empty( $size ) ) {
			$size = 7;
		}

		return sprintf( "<div class='ginput_container ginput_container_multiselect'><select multiple='multiple' {$placeholder} size='{$size}' name='input_%d[]' id='%s' {$logic_event} class='%s' $tabindex %s>%s</select></div>", $id, esc_attr( $field_id ), $css_class, $disabled_text, $this->get_choices( $value ) );
	}

	/**
	 * Helper for retrieving the markup for the choices.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFCommon::get_select_choices()
	 *
	 * @param string|array $value The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 *
	 * @return string Returns the choices available within the multi-select field.
	 */
	public function get_choices( $value ) {

		// If we are in the entry editor, convert value to an array.
		$value = $this->is_entry_detail() ? $this->to_array( $value ) : $value;

		return GFCommon::get_select_choices( $this, $value, false );

	}

	/**
	 * Format the entry value for display on the entries list page.
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
	 * @return string $value The value of the field. Escaped.
	 */
	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		// Add space after comma-delimited values.
		$value = implode( ', ', $this->to_array( $value ) );
		return esc_html( $value );
	}

	/**
	 * Format the entry value for display on the entry detail page and for the {all_fields} merge tag.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFCommon::selection_display()
	 *
	 * @param string|array $value    The field value.
	 * @param string       $currency The entry currency code.
	 * @param bool|false   $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param string       $format   The format requested for the location the merge is being used. Possible values: html, text or url.
	 * @param string       $media    The location where the value will be displayed. Possible values: screen or email.
	 *
	 * @return string The list items, stored within an unordered list.
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		if ( empty( $value ) || $format == 'text' ) {
			return $value;
		}

		$value = $this->to_array( $value );

		$items = '';
		foreach ( $value as $item ) {
			$item_value = GFCommon::selection_display( $item, $this, $currency, $use_text );
			$items .= '<li>' . esc_html( $item_value ) . '</li>';
		}

		return "<ul class='bulleted'>{$items}</ul>";
	}

	/**
	 * Format the value before it is saved to the Entry Object.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GF_Field_MultiSelect::sanitize_entry_value()
	 *
	 * @param array|string $value      The value to be saved.
	 * @param array        $form       The Form Object currently being processed.
	 * @param string       $input_name The input name used when accessing the $_POST.
	 * @param int          $lead_id    The ID of the Entry currently being processed.
	 * @param array        $lead       The Entry Object currently being processed.
	 *
	 * @return string $value The field value. Comma separated if an array.
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {

		if ( is_array( $value ) ) {
			foreach ( $value as &$v ) {
				$v = $this->sanitize_entry_value( $v, $form['id'] );
			}
		} else {
			$value = $this->sanitize_entry_value( $value, $form['id'] );
		}

		return empty( $value ) ? '' : $this->to_string( $value );
	}

	/**
	 * Format the entry value for when the field/input merge tag is processed.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFCommon::format_post_category()
	 * @uses GFCommon::format_variable_value()
	 * @uses GFCommon::selection_display()
	 * @uses GFCommon::implode_non_blank()
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
	 * @return string $return The merge tag value.
	 */
	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		$items = $this->to_array( $raw_value );

		if ( $this->type == 'post_category' ) {
			$use_id = $modifier == 'id';

			if ( is_array( $items ) ) {
				foreach ( $items as &$item ) {
					$cat  = GFCommon::format_post_category( $item, $use_id );
					$item = GFCommon::format_variable_value( $cat, $url_encode, $esc_html, $format );
				}
			}
		} elseif ( $modifier != 'value' ) {

			foreach ( $items as &$item ) {
				$item = GFCommon::selection_display( $item, $this, rgar( $entry, 'currency' ), true );
				$item = GFCommon::format_variable_value( $item, $url_encode, $esc_html, $format );
			}
		}

		$return = GFCommon::implode_non_blank( ', ', $items );

		if ( $format == 'html' || $esc_html ) {
			$return = esc_html( $return );
		}

		return $return;
	}

	/**
	 * Format the entry value before it is used in entry exports and by framework add-ons using GFAddOn::get_field_value().
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFCommon::selection_display()
	 * @uses GFCommon::implode_non_blank()
	 *
	 * @param array      $entry    The entry currently being processed.
	 * @param string     $input_id The field or input ID.
	 * @param bool|false $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param bool|false $is_csv   Is the value going to be used in the .csv entries export?
	 *
	 * @return string $value The value of a field from an export file.
	 */
	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		if ( empty( $input_id ) ) {
			$input_id = $this->id;
		}

		$value = rgar( $entry, $input_id );

		if ( ! empty( $value ) && ! $is_csv ) {
			$items = $this->to_array( $value );

			foreach ( $items as &$item ) {
				$item = GFCommon::selection_display( $item, $this, rgar( $entry, 'currency' ), $use_text );
			}
			$value = GFCommon::implode_non_blank( ', ', $items );

		} elseif ( $this->storageType === 'json' ) {

			$items = json_decode( $value );
			$value = GFCommon::implode_non_blank( ', ', $items );
		}

		return $value;
	}

	/**
	 * Converts an array to a string.
	 *
	 * @since 2.2
	 * @access private
	 *
	 * @uses \GF_Field_MultiSelect::$storageType
	 *
	 * @param array The array to convert to a string.
	 *
	 * @return string The converted string.
	 */
	private function to_string( $value ) {
		if ( $this->storageType === 'json' ) {
			return json_encode( $value );
		} else {
			return is_array( $value ) ? implode( ',', $value ) : $value;
		}
	}

	/**
	 * Converts a string to an array.
	 *
	 * @since 2.2
	 * @access private
	 *
	 * @uses \GF_Field_MultiSelect::$storageType
	 *
	 * @param string A comma-separated or JSON string to convert.
	 *
	 * @return array The converted array.
	 */
	private function to_array( $value ) {
		if ( $this->storageType === 'json' ) {
			$json = json_decode( $value, true );
			return $json == null ? array() : $json;
		} else {
			return explode( ',', $value );
		}
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
	 * @return void
	 *
	 */
	public function sanitize_settings() {
		parent::sanitize_settings();
		$this->enableEnhancedUI = (bool) $this->enableEnhancedUI;

		$this->storageType = empty( $this->storageType ) || $this->storageType === 'json' ? $this->storageType : 'json';

		if ( $this->type === 'post_category' ) {
			$this->displayAllCategories = (bool) $this->displayAllCategories;
		}
	}
}

// Register the new field type.
GF_Fields::register( new GF_Field_MultiSelect() );
