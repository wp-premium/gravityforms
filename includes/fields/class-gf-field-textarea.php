<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_Textarea extends GF_Field {

	public $type = 'textarea';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Paragraph Text', 'gravityforms' );
	}

	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'maxlen_setting',
			'size_setting',
			'rules_setting',
			'visibility_setting',
			'duplicate_setting',
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

	public function allow_html() {
		return empty( $this->useRichTextEditor ) ? false : true;
	}

	public function get_field_input( $form, $value = '', $entry = null ) {

		$form_id         = absint( $form['id'] );
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$is_admin = $is_entry_detail || $is_form_editor;

		$id            = intval( $this->id );
		$field_id      = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
		$size          = $this->size;
		$class_suffix  = $is_entry_detail ? '_admin' : '';
		$class         = $size . $class_suffix;
		$class         = esc_attr( $class );
		$disabled_text = $is_form_editor ? 'disabled="disabled"' : '';

		$logic_event           = $this->get_conditional_logic_event( 'keyup' );
		$placeholder_attribute = $this->get_field_placeholder_attribute();
		$required_attribute    = $this->isRequired ? 'aria-required="true"' : '';
		$invalid_attribute     = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';

		$tabindex = $this->get_tabindex();

		if ( $this->get_allowable_tags() === false ) {
			$value = esc_textarea( $value );
		}

		//see if the field is set to use the rich text editor
		if ( $this->useRichTextEditor && ! $is_admin ) {
			//placeholders cannot be used with the rte; message displayed in admin when this occurs
			//field cannot be used in conditional logic by another field; message displayed in admin and field removed from conditional logic drop down
			$tabindex = GFCommon::$tab_index > 0 ? GFCommon::$tab_index ++ : '';

			add_filter( 'mce_buttons', array( $this, 'filter_mce_buttons' ), 10, 2 );

			/**
			 * Filters the field options for the rich text editor.
			 *
			 * @since 2.0.0
			 *
			 * @param array  $editor_settings Array of settings that can be changed.
			 * @param object $this            The field object
			 * @param array  $form            Current form object
			 * @param array  $entry           Current entry object, if available
			 *
			 * Additional filters for specific form and fields IDs.
			 */
			$editor_settings = apply_filters( 'gform_rich_text_editor_options', array(
				'textarea_name' => 'input_' . $id,
				'wpautop' 		=> true,
				'editor_class' 	=> $class,
				'editor_height' => rgar( array( 'small' => 110, 'medium' => 180, 'large' => 280 ), $this->size ? $this->size : 'medium' ),
				'tabindex' 		=> $tabindex,
				'media_buttons' => false,
				'quicktags'     => false,
				'tinymce'		=> array( 'init_instance_callback' =>  "function (editor) {
												editor.on( 'keyup paste mouseover', function (e) {
													var content = editor.getContent( { format: 'text' } ).trim();													
													var textarea = jQuery( '#' + editor.id ); 
													textarea.val( content ).trigger( 'keyup' ).trigger( 'paste' ).trigger( 'mouseover' );													
												
													
												});}" ),
			), $this, $form, $entry );

			$editor_settings = apply_filters( sprintf( 'gform_rich_text_editor_options_%d', $form['id'] ),               $editor_settings, $this, $form, $entry );
			$editor_settings = apply_filters( sprintf( 'gform_rich_text_editor_options_%d_%d', $form['id'], $this->id ), $editor_settings, $this, $form, $entry );

			if ( ! has_action( 'wp_tiny_mce_init', array( __class__, 'start_wp_tiny_mce_init_buffer' ) ) ) {
				add_action( 'wp_tiny_mce_init', array( __class__, 'start_wp_tiny_mce_init_buffer' ) );
			}

			ob_start();
			wp_editor( $value, $field_id, $editor_settings );
			$input = ob_get_clean();

			remove_filter( 'mce_buttons', array( $this, 'filter_mce_buttons' ), 10 );
		} else {

			$input       = '';
			$input_style = '';

			// RTE preview
			if ( $this->is_form_editor() ) {
				$display     = $this->useRichTextEditor ? 'block' : 'none';
				$input_style = $this->useRichTextEditor ? 'style="display:none;"' : '';
				$size        = $this->size ? $this->size : 'medium';
				$input       = sprintf( '<div id="%s_rte_preview" class="gform-rte-preview %s" style="display:%s"></div>', $field_id, $size, $display );
			}

			$input .= "<textarea name='input_{$id}' id='{$field_id}' class='textarea {$class}' {$tabindex} {$logic_event} {$placeholder_attribute} {$required_attribute} {$invalid_attribute} {$disabled_text} {$input_style} rows='10' cols='50'>{$value}</textarea>";

		}

		return sprintf( "<div class='ginput_container ginput_container_textarea'>%s</div>", $input );
	}

	public function validate( $value, $form ) {
		if ( ! is_numeric( $this->maxLength ) ) {
			return;
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

	public static function start_wp_tiny_mce_init_buffer() {
		ob_start();
		add_action( 'after_wp_tiny_mce', array( __class__, 'end_wp_tiny_mce_init_buffer' ), 1 );
	}

	public static function end_wp_tiny_mce_init_buffer() {

		$script = ob_get_clean();
		$pattern = '/(<script.*>)([\s\S]+)(<\/script>)/';

		preg_match_all( $pattern, $script, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {

			list( $search, $open_tag, $guts, $close_tag ) = $match;

			$custom  = 'for( var id in tinymce.editors ) { tinymce.EditorManager.remove( tinymce.editors[id] ); }';
			$replace = sprintf( "%s\njQuery( document ).bind( 'gform_post_render', function() { \n%s\n%s } );\n%s", $open_tag, $custom, $guts, $close_tag );
			$script  = str_replace( $search, $replace, $script );

		}

		echo $script;

	}

	public function filter_mce_buttons( $mce_buttons, $editor_id ) {

		$remove_key = array_search( 'wp_more', $mce_buttons );
		if ( $remove_key !== false ) {
			unset( $mce_buttons[ $remove_key ] );
		}
		/**
		 * Filters the buttons within the TinyMCE editor
		 *
		 * @since 2.0.0
		 *
		 * @param array  $mce_buttons Buttons to be included.
		 * @param string $editor_id   HTML ID of the field.
		 * @param object $this        The field object
		 *
		 * Additional filters for specific form and fields IDs.
		 */
		$mce_buttons = apply_filters( 'gform_rich_text_editor_buttons',                                            $mce_buttons, $editor_id, $this );
		$mce_buttons = apply_filters( sprintf( 'gform_rich_text_editor_buttons_%d', $this->formId ),               $mce_buttons, $editor_id, $this );
		$mce_buttons = apply_filters( sprintf( 'gform_rich_text_editor_buttons_%d_%d', $this->formId, $this->id ), $mce_buttons, $editor_id, $this );

		return $mce_buttons;
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

GF_Fields::register( new GF_Field_Textarea() );
