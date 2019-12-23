<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * The Consent Field keeps track of exactly what the user consented to. The consent value ("1"), checkbox label and the Form revision ID
 * are all stored in the entry meta table in separate input values when consent is given.
 *
 * @since 2.4
 *
 * Class GF_Field_Consent
 */
class GF_Field_Consent extends GF_Field {

	/**
	 * Declare the field type.
	 *
	 * @since 2.4
	 *
	 * @var string
	 */
	public $type = 'consent';

	/**
	 * Checked indicator URL.
	 *
	 * @since 2.4
	 *
	 * @var string
	 */
	public $checked_indicator_url = '';

	/**
	 * Checked indicator image markup.
	 *
	 * @since 2.4
	 *
	 * @var string
	 */
	public $checked_indicator_markup = '';

	/**
	 * GF_Field_Consent constructor.
	 *
	 * @since 2.4
	 *
	 * @param array $data Data needed when initiate the class.
	 */
	public function __construct( $data = array() ) {
		parent::__construct( $data );

		/**
		 * Filters the consent checked indicator (image) URL.
		 *
		 * @since 2.4
		 *
		 * @param string $url Image URL.
		 */
		$this->checked_indicator_url = apply_filters( 'gform_consent_checked_indicator', GFCommon::get_base_url() . '/images/tick.png' );

		/**
		 * Filters the consent checked indicator (image) element.
		 *
		 * @since 2.4
		 *
		 * @param string $tag Image tag.
		 */
		$this->checked_indicator_markup = apply_filters( 'gform_consent_checked_indicator_markup', '<img src="' . esc_url( $this->checked_indicator_url ) . '" />' );
	}

	/**
	 * Returns the field title.
	 *
	 * @since 2.4
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Consent', 'gravityforms' );
	}

	/**
	 * Returns the field button properties for the form editor. The array contains two elements:
	 * 'group' => 'standard_fields' // or  'advanced_fields', 'post_fields', 'pricing_fields'
	 * 'text'  => 'Button text'
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'advanced_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	/**
	 * Returns the class names of the settings which should be available on the field in the form editor.
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'checkbox_label_setting',
			'rules_setting',
			'visibility_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	/**
	 * Indicate if this field type can be used when configuring conditional logic rules.
	 *
	 * @since 2.4
	 *
	 * @return bool
	 */
	public function is_conditional_logic_supported() {
		return true;
	}

	/**
	 * Returns the field inner markup.
	 *
	 * @since 2.4
	 *
	 * @param array      $form  The Form Object currently being processed.
	 * @param array      $value The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param null|array $entry Null or the Entry Object currently being edited.
	 *
	 * @return string
	 */
	public function get_field_input( $form, $value = array(), $entry = null ) {
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$is_admin        = $is_form_editor || $is_entry_detail;

		$html_input_type = 'checkbox';

		$id                 = (int) $this->id;
		$tabindex           = $this->get_tabindex();
		$disabled_text      = $is_form_editor ? 'disabled="disabled"' : '';
		$required_attribute = $this->isRequired ? 'aria-required="true"' : '';
		$invalid_attribute  = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';

		$target_input_id       = parent::get_first_input_id( $form );
		$for_attribute         = empty( $target_input_id ) ? '' : "for='{$target_input_id}'";
		$label_class_attribute = 'class="gfield_consent_label"';
		$required_div          = ( $this->labelPlacement === 'hidden_label' && ( $is_admin || $this->isRequired ) ) ? sprintf( "<span class='gfield_required'>%s</span>", $this->isRequired ? '*' : '' ) : '';

		if ( $is_admin && ! GFCommon::is_entry_detail_edit() ) {
			$checkbox_label = ! is_array( $value ) || empty( $value[ $id . '.2' ] ) ? $this->checkboxLabel : $value[ $id . '.2' ];
			$revision_id    = ! is_array( $value ) || empty( $value[ $id . '.3' ] ) ? GFFormsModel::get_latest_form_revisions_id( $form['id'] ) : $value[ $id . '.3' ];
			$value          = ! is_array( $value ) || empty( $value[ $id . '.1' ] ) ? '0' : esc_attr( $value[ $id . '.1' ] );
		} else {
			$checkbox_label = trim( $this->checkboxLabel );
			$revision_id    = GFFormsModel::get_latest_form_revisions_id( $form['id'] );
			// We compare if the description text from different revisions has been changed.
			$current_description   = $this->get_field_description_from_revision( $revision_id );
			$submitted_description = $this->get_field_description_from_revision( $value[ $id . '.3' ] );

			$value = ! is_array( $value ) || empty( $value[ $id . '.1' ] ) || ( $checkbox_label !== $value[ $id . '.2' ] ) || ( $current_description !== $submitted_description ) ? '0' : esc_attr( $value[ $id . '.1' ] );
		}
		$checked = $is_form_editor ? '' : checked( '1', $value, false );

		$aria_describedby  = '';
		$description       = $is_entry_detail ? $this->get_field_description_from_revision( $revision_id ) : $this->description;
		if ( ! empty( $description ) ) {
			$aria_describedby = "aria-describedby='gfield_consent_description_{$form['id']}_{$this->id}'";
		}

		$input  = "<input name='input_{$id}.1' id='{$target_input_id}' type='{$html_input_type}' value='1' {$tabindex} {$aria_describedby} {$required_attribute} {$invalid_attribute} {$disabled_text} {$checked} /> <label {$label_class_attribute} {$for_attribute} >{$checkbox_label}</label>{$required_div}";
		$input .= "<input type='hidden' name='input_{$id}.2' value='" . esc_attr( $checkbox_label ) . "' class='gform_hidden' />";
		$input .= "<input type='hidden' name='input_{$id}.3' value='" . esc_attr( $revision_id ) . "' class='gform_hidden' />";

		if ( $is_entry_detail ) {
			$input .= $this->get_description( $this->get_field_description_from_revision( $revision_id ), '' );
		}

		return sprintf( "<div class='ginput_container ginput_container_consent'>%s</div>", $input );
	}

	/**
	 * Returns the input ID to be assigned to the field label for attribute.
	 *
	 * @since  2.4
	 *
	 * @param array $form The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_first_input_id( $form ) {

		return '';

	}

	/**
	 * Returns the markup for the field description.
	 *
	 * @since 2.4
	 *
	 * @param string $description The field description.
	 * @param string $css_class   The css class to be assigned to the description container.
	 *
	 * @return string
	 */
	public function get_description( $description, $css_class ) {
		if ( ! empty( $description ) ) {
			$id = "gfield_consent_description_{$this->formId}_{$this->id}";

			$css_class .= ' gfield_consent_description';

			return "<div class='$css_class' id='$id'>" . nl2br( $description ) . '</div>';
		}

		return parent::get_description( $description, $css_class );
	}

	/**
	 * Return the result (bool) by setting $this->failed_validation.
	 * Return the validation message (string) by setting $this->validation_message.
	 *
	 * @since 2.4
	 *
	 * @param string|array $value The field value from get_value_submission().
	 * @param array        $form  The Form Object currently being processed.
	 */
	public function validate( $value, $form ) {
		$consent = rgget( $this->id . '.1', $value );

		if ( $this->isRequired && rgblank( $consent ) ) {
			$this->failed_validation  = true;
			$this->validation_message = empty( $this->errorMessage ) ? esc_html__( 'This field is required.', 'gravityforms' ) : $this->errorMessage;
		}
	}

	/**
	 * Sanitize and format the value before it is saved to the Entry Object.
	 * We also add the value of inputs .2 and .3 here since they are not displayed in the form.
	 *
	 * @since 2.4
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
		list( $input, $field_id, $input_id ) = rgexplode( '_', $input_name, 3 );

		switch ( $input_id ) {
			case '1':
				$value = ( ! empty( $value ) ) ? '1' : '';
				break;
			case '2':
				$value = ( $lead[ $field_id . '.1' ] === '1' ) ? $value : '';
				break;
			case '3':
				$value = ( $lead[ $field_id . '.1' ] === '1' ) ? $value : '';
				break;
		}

		return $value;
	}

	/**
	 * Set the values of consent field inputs in merge tags.
	 *
	 * @since  2.4
	 *
	 * @param string|array $value      The field value. Depending on the location the merge tag is being used the following functions may have already been applied to the value: esc_html, nl2br, and urlencode.
	 * @param string       $input_id   The field or input ID from the merge tag currently being processed.
	 * @param array        $entry      The Entry Object currently being processed.
	 * @param array        $form       The Form Object currently being processed.
	 * @param string       $modifier   The merge tag modifier. e.g. value.
	 * @param string|array $raw_value  The raw field value from before any formatting was applied to $value.
	 * @param bool         $url_encode Indicates if the urlencode function may have been applied to the $value.
	 * @param bool         $esc_html   Indicates if the esc_html function may have been applied to the $value.
	 * @param string       $format     The format requested for the location the merge is being used. Possible values: html, text or url.
	 * @param bool         $nl2br      Indicates if the nl2br function may have been applied to the $value.
	 *
	 * @return string
	 */
	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		list( $field_id, $input_id ) = explode( '.', $input_id );

		switch ( $input_id ) {
			case '1':
				$value = ! rgblank( $value ) ? $this->checked_indicator_markup : '';
				break;
			case '3':
				$value = ! rgblank( $value ) ? $this->get_field_description_from_revision( $value ) : '';
				if ( $value !== '' && $nl2br ) {
					$value = nl2br( $value );
				}
				break;
		}

		return $value;
	}

	/**
	 * Format the entry value for display on the entries list page.
	 *
	 * @since 2.4
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
		list( $field_id, $input_id ) = explode( '.', $field_id );

		switch ( $input_id ) {
			case '1':
				$value  = ! rgblank( $value ) ? $this->checked_indicator_markup : '';
				$value .= ! rgblank( $value ) ? ' ' . trim( $entry[ $this->id . '.2' ] ) : '';
				break;
		}

		return $value;
	}

	/**
	 * Format the entry value for display on the entry detail page and for the {all_fields} merge tag.
	 *
	 * @since 2.4
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
		$return = '';

		if ( is_array( $value ) && ! empty( $value ) ) {
			$consent     = trim( $value[ $this->id . '.1' ] );
			$text        = trim( $value[ $this->id . '.2' ] );
			$revision_id = absint( trim( $value[ $this->id . '.3' ] ) );

			if ( ! rgblank( $consent ) ) {
				$return  = $this->checked_indicator_markup;
				$return .= ' ' . $text;

				// checking revisions.
				$description = $this->get_field_description_from_revision( $revision_id );

				if ( ! empty( $description ) ) {
					$return .= '<br /><div class="gfield_consent_description">' . nl2br( $description ) . '</div>';
				}
			}
		}

		return $return;
	}

	/**
	 * Format the entry value before it is used in entry exports and by framework add-ons using GFAddOn::get_field_value().
	 *
	 * For CSV export return a string or array.
	 *
	 * @since 2.4
	 *
	 * @param array      $entry    The entry currently being processed.
	 * @param string     $input_id The field or input ID.
	 * @param bool|false $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param bool|false $is_csv   Is the value going to be used in the .csv entries export.
	 *
	 * @return string|array
	 */
	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		$value = parent::get_value_export( $entry, $input_id, $use_text, $is_csv );

		list( $field_id, $input_id ) = explode( '.', $input_id );

		switch ( $input_id ) {
			case '1':
				$value = ! rgblank( $value ) ? esc_html__( 'Checked', 'gravityforms' ) : esc_html__( 'Not Checked', 'gravityforms' );
				break;
			case '3':
				$value = ! rgblank( $value ) ? $this->get_field_description_from_revision( $value ) : '';
				break;
		}

		return $value;
	}

	/**
	 * Forces settings into expected values while saving the form object.
	 *
	 * No escaping should be done at this stage to prevent double escaping on output.
	 *
	 * @since 2.4
	 */
	public function sanitize_settings() {
		parent::sanitize_settings();
		$this->checkboxLabel = $this->maybe_wp_kses( $this->checkboxLabel );
	}

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
			'key'             => $this->id . '.1',
			'text'            => GFFormsModel::get_label( $this ),
			'preventMultiple' => false,
			'operators'       => $this->get_filter_operators(),
		);

		$values = $this->get_filter_values();
		if ( ! empty( $values ) ) {
			$filter_settings['values'] = $values;
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
		$operators = array( 'is', 'isnot' );

		return $operators;
	}

	/**
	 * Returns the filters values setting for the current field.
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public function get_filter_values() {
		$choices = array(
			array(
				'value' => '1',
				'text'  => esc_html__( 'Checked', 'gravityforms' ),
			),
		);

		return $choices;
	}

	/**
	 * Get consent description from the form revision.
	 *
	 * @since 2.4
	 *
	 * @param int $revision_id Revision ID.
	 *
	 * @return string
	 */
	public function get_field_description_from_revision( $revision_id ) {
		global $wpdb;
		$revisions_table_name = GFFormsModel::get_form_revisions_table_name();
		$display_meta         = $wpdb->get_var( $wpdb->prepare( "SELECT display_meta FROM $revisions_table_name WHERE form_id=%d AND id=%d", $this->formId, $revision_id ) );
		$value                = '';
		$is_entry_detail = $this->is_entry_detail();

		if ( ! empty( $display_meta ) ) {
			$display_meta_array = json_decode( $display_meta, true );
			foreach ( $display_meta_array['fields'] as $field ) {
				if ( $field['id'] === $this->id ) {
					$value = $field['description'];

					break;
				}
			}
		} else {
			$value = ( ! empty( $this->description ) ) ? $this->description : '';
		}

		if ( $is_entry_detail ) {
			$value = $this->maybe_wp_kses( $value );
		}

		return $value;
	}

}

GF_Fields::register( new GF_Field_Consent() );
