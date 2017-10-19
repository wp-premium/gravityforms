<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Field_Post_Content extends GF_Field {

	public $type = 'post_content';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Body', 'gravityforms' );
	}

	function get_form_editor_field_settings() {
		return array(
			'post_content_template_setting',
			'post_status_setting',
			'post_category_setting',
			'post_author_setting',
			'post_format_setting',
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'size_setting',
			'maxlen_setting',
			'rules_setting',
			'visibility_setting',
			'default_value_textarea_setting',
			'placeholder_textarea_setting',
			'description_setting',
			'css_class_setting',
			'rich_text_editor_setting',
		);
	}

	public function is_conditional_logic_supported() {
		return true;
	}

	public function enqueue_rich_text_editor_scripts() {
		//have to print scripts/styles to footer for the editor to work on the preview page
		wp_print_footer_scripts();
	}

	public function get_field_input( $form, $value = '', $entry = null ) {

		$field = new GF_Field_Textarea( clone $this );

		return $field->get_field_input( $form, $value, $entry );
	}

	public function validate( $value, $form ) {
		if ( ! is_numeric( $this->maxLength ) ) {
			return;
		}

		if ( $this->useRichTextEditor ) {
			$value = wp_specialchars_decode( $value );
		}

		// Clean the string of characters not counted by the textareaCounter plugin.
		$value = strip_tags( $value );
		$value = str_replace( "\r", '', $value );
		$value = trim( $value );

		if ( GFCommon::safe_strlen( $value ) > $this->maxLength ) {
			$this->failed_validation  = true;
			$this->validation_message = empty( $this->errorMessage ) ? esc_html__( 'The text entered exceeds the maximum number of characters.', 'gravityforms' ) : $this->errorMessage;
		}
	}

	public function allow_html() {
		return true;
	}


	/**
	 * Format the entry value for display on the entry detail page and for the {all_fields} merge tag.
	 * Return a value that's safe to display for the context of the given $format.
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

		if ( $format === 'html' ) {

			$allowable_tags = $this->get_allowable_tags();

			if ( $allowable_tags === false ) {
				// The value is unsafe so encode the value.
				$value = esc_html( $value );
				$return = nl2br( $value );

			} else {
				// The value contains HTML but the value was sanitized before saving.
				$return = wpautop( $value );
			}
		} else {
			$return = $value;
		}

		return $return;
	}

	/**
	 * Format the entry value for when the field/input merge tag is processed. Not called for the {all_fields} merge tag.
	 *
	 * Return a value that is safe for the context specified by $format.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GF_Field::get_allowable_tags()
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
			$form_id        = absint( $form['id'] );
			$allowable_tags = $this->get_allowable_tags( $form_id );

			if ( $allowable_tags === false ) {
				// The raw value is unsafe so escape it.
				$return = esc_html( $raw_value );
				// Run nl2br() to preserve line breaks when auto-formatting is disabled on notifications/confirmations.
				$return = nl2br( $return );
			} else {
				// The value contains HTML but the value was sanitized before saving.
				$return = wpautop( $raw_value );
			}
		} else {
			$return = $value;
		}

		return $return;
	}
}

GF_Fields::register( new GF_Field_Post_Content() );
