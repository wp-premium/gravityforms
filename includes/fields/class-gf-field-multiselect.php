<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_MultiSelect extends GF_Field {

	public $type = 'multiselect';

	/**
	 * Returns the field title.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Multi Select', 'gravityforms' );
	}

	/**
	 * Returns the class names of the settings which should be available on the field in the form editor.
	 *
	 * @return array
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
	 * @param array $form The Form Object currently being processed.
	 * @param string|array $value The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param null|array $entry Null or the Entry Object currently being edited.
	 *
	 * @return string
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
		 * @param string $placeholder The placeholder text.
		 * @param integer $form_id The ID of the current form.
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
	 * @param string|array $value The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 *
	 * @return string
	 */
	public function get_choices( $value ) {
		return GFCommon::get_select_choices( $this, $value, false );
	}

	/**
	 * Format the entry value for display on the entries list page.
	 *
	 * @param string|array $value The field value.
	 * @param array $entry The Entry Object currently being processed.
	 * @param string $field_id The field or input ID currently being processed.
	 * @param array $columns The properties for the columns being displayed on the entry list page.
	 * @param array $form The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		// add space after comma-delimited values
		$value = implode( ', ', explode( ',', $value ) );
		return esc_html( $value );
	}

	/**
	 * Format the entry value for display on the entry detail page and for the {all_fields} merge tag.
	 *
	 * @param string|array $value The field value.
	 * @param string $currency The entry currency code.
	 * @param bool|false $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param string $format The format requested for the location the merge is being used. Possible values: html, text or url.
	 * @param string $media The location where the value will be displayed. Possible values: screen or email.
	 *
	 * @return string
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		if ( empty( $value ) || $format == 'text' ) {
			return $value;
		}

		$value = explode( ',', $value );

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
	 * @param array|string $value The value to be saved.
	 * @param array $form The Form Object currently being processed.
	 * @param string $input_name The input name used when accessing the $_POST.
	 * @param int $lead_id The ID of the Entry currently being processed.
	 * @param array $lead The Entry Object currently being processed.
	 *
	 * @return array|string
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {

		if ( is_array( $value ) ) {
			foreach ( $value as &$v ) {
				$v = $this->sanitize_entry_value( $v, $form['id'] );
			}
		} else {
			$value = $this->sanitize_entry_value( $value, $form['id'] );
		}

		return empty( $value ) ? '' : is_array( $value ) ? implode( ',', $value ) : $value;
	}

	/**
	 * Format the entry value for when the field/input merge tag is processed.
	 *
	 * @param string|array $value The field value. Depending on the location the merge tag is being used the following functions may have already been applied to the value: esc_html, nl2br, and urlencode.
	 * @param string $input_id The field or input ID from the merge tag currently being processed.
	 * @param array $entry The Entry Object currently being processed.
	 * @param array $form The Form Object currently being processed.
	 * @param string $modifier The merge tag modifier. e.g. value
	 * @param string|array $raw_value The raw field value from before any formatting was applied to $value.
	 * @param bool $url_encode Indicates if the urlencode function may have been applied to the $value.
	 * @param bool $esc_html Indicates if the esc_html function may have been applied to the $value.
	 * @param string $format The format requested for the location the merge is being used. Possible values: html, text or url.
	 * @param bool $nl2br Indicates if the nl2br function may have been applied to the $value.
	 *
	 * @return string
	 */
	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		$items = explode( ',', $value );

		if ( $this->type == 'post_category' ) {
			$use_id = $modifier == 'id';

			if ( is_array( $items ) ) {
				foreach ( $items as &$item ) {
					$cat    = GFCommon::format_post_category( $item, $use_id );
					$item = GFCommon::format_variable_value( $cat, $url_encode, $esc_html, $format );
				}
			}
		} elseif ( $modifier != 'value' ) {
			foreach ( $items as &$item ) {
				$item = GFCommon::selection_display( $item, $this, rgar( $entry, 'currency' ), true );
			}
		}

		return GFCommon::implode_non_blank( ', ', $items );
	}

	/**
	 * Format the entry value before it is used in entry exports and by framework add-ons using GFAddOn::get_field_value().
	 *
	 * @param array $entry The entry currently being processed.
	 * @param string $input_id The field or input ID.
	 * @param bool|false $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param bool|false $is_csv Is the value going to be used in the .csv entries export?
	 *
	 * @return string
	 */
	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		if ( empty( $input_id ) ) {
			$input_id = $this->id;
		}

		$value = rgar( $entry, $input_id );

		if ( ! empty( $value ) && ! $is_csv ) {
			$items = explode( ',', $value );

			foreach ( $items as &$item ) {
				$item = GFCommon::selection_display( $item, $this, rgar( $entry, 'currency' ), $use_text );
			}

			$value = GFCommon::implode_non_blank( ', ', $items );
		}

		return $value;
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
		parent::sanitize_settings();
		$this->enableEnhancedUI = (bool) $this->enableEnhancedUI;

		if ( $this->type === 'post_category' ) {
			$this->displayAllCategories = (bool) $this->displayAllCategories;
		}
	}

}

GF_Fields::register( new GF_Field_MultiSelect() );