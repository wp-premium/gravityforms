<?php

// If Gravity Forms isn't loaded, bail.
if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Class GF_Field_Time
 *
 * Handles Time fields.
 *
 * @since Unknown
 * @uses  GF_Field
 */
class GF_Field_Time extends GF_Field {

	/**
	 * Sets the field type to be used in the field framework.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @var string $type The type of field this is.
	 */
	public $type = 'time';

	/**
	 * Sets the title of the field to be used in the form editor.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFCommon::get_field_type_title()
	 * @used-by GFAddOn::get_field_map_choices()
	 * @used-by GFAddOn::prepare_field_select_field()
	 * @used-by GFAddOn::settings_field_map_select()
	 * @used-by GF_Field::get_form_editor_button()
	 *
	 * @return string The field title.
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Time', 'gravityforms' );
	}

	/**
	 * Defines the field editor settings that are available for this field.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormDetail::inline_scripts()
	 *
	 * @return array Contains the settings available within the field editor.
	 */
	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'label_setting',
			'sub_labels_setting',
			'label_placement_setting',
			'sub_label_placement_setting',
			'admin_label_setting',
			'time_format_setting',
			'rules_setting',
			'visibility_setting',
			'duplicate_setting',
			'default_input_values_setting',
			'input_placeholders_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	/**
	 * Validates the field inputs.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormDisplay::validate()
	 * @uses    GF_Field_Time::$failed_validation
	 * @uses    GF_Field_Time::$validation_message
	 * @uses    GF_Field_Time::$timeFormat
	 * @uses    GF_Field_Time::$errorMessage
	 *
	 * @param array|string $value The field value or values to validate.
	 * @param array        $form  The Form Object.
	 *
	 * @return void
	 */
	public function validate( $value, $form ) {
		// Create variable values if time came in one field.
		if ( ! is_array( $value ) && ! empty( $value ) ) {
			preg_match( '/^(\d*):(\d*) ?(.*)$/', $value, $matches );
			$value    = array();
			$value[0] = $matches[1];
			$value[1] = $matches[2];
		}

		$hour   = rgar( $value, 0 );
		$minute = rgar( $value, 1 );

		if ( empty( $hour ) && empty( $minute ) ) {
			return;
		}

		$is_valid_format = is_numeric( $hour ) && is_numeric( $minute );

		$min_hour   = $this->timeFormat == '24' ? 0 : 1;
		$max_hour   = $this->timeFormat == '24' ? 24 : 12;
		$max_minute = $hour >= 24 ? 0 : 59;

		if ( ! $is_valid_format || $hour < $min_hour || $hour > $max_hour || $minute < 0 || $minute > $max_minute ) {
			$this->failed_validation  = true;
			$this->validation_message = empty( $this->errorMessage ) ? esc_html__( 'Please enter a valid time.', 'gravityforms' ) : $this->errorMessage;
		}
	}

	/**
	 * Defines how the Time field input is shown.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFCommon::get_field_input()
	 * @uses    GF_Field::is_entry_detail()
	 * @uses    GF_Field::is_form_editor()
	 * @uses    GF_Field_Time::$subLabelPlacement
	 * @uses    GFFormsModel::get_input()
	 * @uses    GF_Field::get_input_placeholder_attribute()
	 * @uses    GF_Field::get_tabindex()
	 * @uses    GFFormsModel::is_html5_enabled()
	 *
	 * @param array      $form  The Form Object.
	 * @param string     $value The field default value. Defaults to empty string.
	 * @param array|null $entry The Entry Object, if available. Defaults to null.
	 *
	 * @return string The field HTML markup.
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {

		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$form_id  = absint( $form['id'] );
		$id       = intval( $this->id );
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$form_sub_label_placement  = rgar( $form, 'subLabelPlacement' );
		$field_sub_label_placement = $this->subLabelPlacement;
		$is_sub_label_above        = $field_sub_label_placement == 'above' || ( empty( $field_sub_label_placement ) && $form_sub_label_placement == 'above' );
		$sub_label_class_attribute = $field_sub_label_placement == 'hidden_label' ? "class='hidden_sub_label screen-reader-text'" : '';

		$disabled_text = $is_form_editor ? "disabled='disabled'" : '';

		$hour = $minute = $am_selected = $pm_selected = '';

		if ( ! is_array( $value ) && ! empty( $value ) ) {
			preg_match( '/^(\d*):(\d*) ?(.*)$/', $value, $matches );
			$hour        = esc_attr( $matches[1] );
			$minute      = esc_attr( $matches[2] );
			$the_rest    = strtolower( rgar( $matches, 3 ) );
			$am_selected = strpos( $the_rest, 'am' ) > -1 ? "selected='selected'" : '';
			$pm_selected = strpos( $the_rest, 'pm' ) > -1  ? "selected='selected'" : '';
		} elseif ( is_array( $value ) ) {
			$value       = array_values( $value );
			$hour        = esc_attr( $value[0] );
			$minute      = esc_attr( $value[1] );
			$am_selected = strtolower( rgar( $value, 2 ) ) == 'am' ? "selected='selected'" : '';
			$pm_selected = strtolower( rgar( $value, 2 ) ) == 'pm' ? "selected='selected'" : '';
		}

		$hour_input   = GFFormsModel::get_input( $this, $this->id . '.1' );
		$minute_input = GFFormsModel::get_input( $this, $this->id . '.2' );

		$hour_placeholder_attribute   = $this->get_input_placeholder_attribute( $hour_input );
		$minute_placeholder_attribute = $this->get_input_placeholder_attribute( $minute_input );

		$hour_tabindex   = $this->get_tabindex();
		$minute_tabindex = $this->get_tabindex();
		$ampm_tabindex   = $this->get_tabindex();

		$is_html5   = RGFormsModel::is_html5_enabled();
		$input_type = $is_html5 ? 'number' : 'text';

		$max_hour = $this->timeFormat == '24' ? 24 : 12;
		$hour_html5_attributes   = $is_html5 ? "min='0' max='{$max_hour}' step='1'" : '';
		$minute_html5_attributes = $is_html5 ? "min='0' max='59' step='1'" : '';

		$ampm_field_style = $is_form_editor && $this->timeFormat == '24' ? "style='display:none;'" : '';
		if ( $is_form_editor || $this->timeFormat != '24' ) {
			$am_text = esc_html__( 'AM', 'gravityforms' );
			$pm_text = esc_html__( 'PM', 'gravityforms' );
			$ampm_field = $is_sub_label_above ? "<div class='gfield_time_ampm ginput_container ginput_container_time' {$ampm_field_style}>
                                                            <label for='{$field_id}_3'>&nbsp;</label>
                                                            <select name='input_{$id}[]' id='{$field_id}_3' $ampm_tabindex {$disabled_text}>
                                                                <option value='am' {$am_selected}>{$am_text}</option>
                                                                <option value='pm' {$pm_selected}>{$pm_text}</option>
                                                            </select>
                                                          </div>"
												: "<div class='gfield_time_ampm ginput_container ginput_container_time' {$ampm_field_style}>
                                                            <select name='input_{$id}[]' id='{$field_id}_3' $ampm_tabindex {$disabled_text}>
                                                                <option value='am' {$am_selected}>{$am_text}</option>
                                                                <option value='pm' {$pm_selected}>{$pm_text}</option>
                                                            </select>
                                                          </div>";
		} else {
			$ampm_field = '';
		}

		$hour_label = rgar( $hour_input, 'customLabel' ) != '' ? $hour_input['customLabel'] : esc_html__( 'HH', 'gravityforms' );
		$minute_label = rgar( $minute_input, 'customLabel' ) != '' ? $minute_input['customLabel'] : esc_html( _x( 'MM', 'Abbreviation: Minutes', 'gravityforms' ) );

		if ( $is_sub_label_above ) {
			return "<div class='clear-multi'>
                        <div class='gfield_time_hour ginput_container ginput_container_time' id='{$field_id}'>
                            <label for='{$field_id}_1' {$sub_label_class_attribute}>{$hour_label}</label>
                            <input type='{$input_type}' maxlength='2' name='input_{$id}[]' id='{$field_id}_1' value='{$hour}' {$hour_tabindex} {$hour_html5_attributes} {$disabled_text} {$hour_placeholder_attribute}/> <i>:</i>
                        </div>
                        <div class='gfield_time_minute ginput_container ginput_container_time'>
                            <label for='{$field_id}_2' {$sub_label_class_attribute}>{$minute_label}</label>
                            <input type='{$input_type}' maxlength='2' name='input_{$id}[]' id='{$field_id}_2' value='{$minute}' {$minute_tabindex} {$minute_html5_attributes} {$disabled_text} {$minute_placeholder_attribute}/>
                        </div>
                        {$ampm_field}
                    </div>";
		} else {
			return "<div class='clear-multi'>
                        <div class='gfield_time_hour ginput_container ginput_container_time' id='{$field_id}'>
                            <input type='{$input_type}' maxlength='2' name='input_{$id}[]' id='{$field_id}_1' value='{$hour}' {$hour_tabindex} {$hour_html5_attributes} {$disabled_text} {$hour_placeholder_attribute}/> <i>:</i>
                            <label for='{$field_id}_1' {$sub_label_class_attribute}>{$hour_label}</label>
                        </div>
                        <div class='gfield_time_minute ginput_container ginput_container_time'>
                            <input type='{$input_type}' maxlength='2' name='input_{$id}[]' id='{$field_id}_2' value='{$minute}' {$minute_tabindex} {$minute_html5_attributes} {$disabled_text} {$minute_placeholder_attribute}/>
                            <label for='{$field_id}_2' {$sub_label_class_attribute}>{$minute_label}</label>
                        </div>
                        {$ampm_field}
                    </div>";
		}
	}

	/**
	 * Adds additional classes to the field labels.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GF_Field::get_field_content()
	 *
	 * @return string The class string to use for the Time field.
	 */
	public function get_field_label_class(){
		return 'gfield_label gfield_label_before_complex';
	}

	/**
	 * Determines if any of the submission values are empty.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormDisplay::is_empty()
	 *
	 * @param int $form_id The form ID.
	 *
	 * @return bool True if empty. False otherwise.
	 */
	public function is_value_submission_empty( $form_id ) {
		$value = rgpost( 'input_' . $this->id );
		if ( is_array( $value ) ) {
			// Date field and date drop-downs.
			foreach ( $value as $input ) {
				if ( strlen( trim( $input ) ) <= 0 ) {
					return true;
				}
			}

			return false;
		} else {

			// Date picker.
			return strlen( trim( $value ) ) <= 0;
		}
	}

	/**
	 * Prepares the field value to be saved after an entry is submitted.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormsModel::prepare_value()
	 *
	 * @param string $value      The value to prepare.
	 * @param array  $form       The Form Object. Not used.
	 * @param string $input_name The name of the input. Not used.
	 * @param int    $lead_id    The entry ID. Not used.
	 * @param array  $lead       The Entry Object. Not used.
	 *
	 * @return array|string      The field value, prepared and stripped of tags.
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {

		// If $value is a default value and also an array, it will be an associative array; to be safe, let's convert all array $value to numeric.
		if( is_array( $value ) ) {
			$value = array_values( $value );
		}

		if ( ! is_array( $value ) && ! empty( $value ) ) {
			preg_match( '/^(\d*):(\d*) ?(.*)$/', $value, $matches );
			$value    = array();
			$value[0] = $matches[1];
			$value[1] = $matches[2];
			$value[2] = rgar( $matches, 3 );
		}

		$hour   = empty( $value[0] ) ? '0' : wp_strip_all_tags( $value[0] );
		$minute = empty( $value[1] ) ? '0' : wp_strip_all_tags( $value[1] );
		$ampm   = wp_strip_all_tags( rgar( $value, 2 ) );
		if ( ! empty( $ampm ) ) {
			$ampm = " $ampm";
		}

		if ( ! ( empty( $hour ) && empty( $minute ) ) ) {
			$value = sprintf( '%02d:%02d%s', $hour, $minute, $ampm );
		} else {
			$value = '';
		}

		return $value;
	}

	/**
	 * Overrides GF_Field to prevent the standard input ID from being used.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return null
	 */
	public function get_entry_inputs() {
		return null;
	}


	/**
	 * Support for legacy Time fields which did not have an inputs array.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GF_Field::get_field_content()
	 * @uses    GF_Field::get_first_input_id()
	 *
	 * @param array $form The Form Object
	 *
	 * @return string The first input ID.
	 */
	public function get_first_input_id( $form ) {

		// Legacy (< 1.9) Time fields did not have an inputs array.
		if ( ! is_array( $this->inputs ) ){
			return 'input_' . $form['id'] . '_' . $this->id . '_1';
		}

		return parent::get_first_input_id( $form );
	}

	/**
	 * Sanitizes settings for the Time field.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormDetail::add_field()
	 * @used-by GFFormsModel::sanitize_settings()
	 * @uses    GF_Field::sanitize_settings
	 * @uses    GF_Field_Time::$timeFormat
	 *
	 * @return void
	 */
	public function sanitize_settings() {
		parent::sanitize_settings();
		if ( ! $this->timeFormat || ! in_array( $this->timeFormat, array( 12, 24 ) ) ) {
			$this->timeFormat = '12';
		}
	}

}

// Register the Time field with the field framework.
GF_Fields::register( new GF_Field_Time() );
