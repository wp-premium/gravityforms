<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_Date extends GF_Field {

	public $type = 'date';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Date', 'gravityforms' );
	}

	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'sub_label_placement_setting',
			'admin_label_setting',
			'rules_setting',
			'date_input_type_setting',
			'visibility_setting',
			'duplicate_setting',
			'date_format_setting',
			'default_value_setting',
			'placeholder_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	public function validate( $value, $form ) {
		if ( is_array( $value ) && rgempty( 0, $value ) && rgempty( 1, $value ) && rgempty( 2, $value ) ) {
			$value = null;
		}

		if ( ! empty( $value ) ) {
			$format = empty( $this->dateFormat ) ? 'mdy' : $this->dateFormat;
			$date   = GFCommon::parse_date( $value, $format );

			if ( empty( $date ) || ! $this->checkdate( $date['month'], $date['day'], $date['year'] ) ) {
				$this->failed_validation = true;
				$format_name             = '';
				switch ( $format ) {
					case 'mdy' :
						$format_name = 'mm/dd/yyyy';
						break;
					case 'dmy' :
						$format_name = 'dd/mm/yyyy';
						break;
					case 'dmy_dash' :
						$format_name = 'dd-mm-yyyy';
						break;
					case 'dmy_dot' :
						$format_name = 'dd.mm.yyyy';
						break;
					case 'ymd_slash' :
						$format_name = 'yyyy/mm/dd';
						break;
					case 'ymd_dash' :
						$format_name = 'yyyy-mm-dd';
						break;
					case 'ymd_dot' :
						$format_name = 'yyyy.mm.dd';
						break;
				}
				$message                  = $this->dateType == 'datepicker' ? sprintf( esc_html__( 'Please enter a valid date in the format (%s).', 'gravityforms' ), $format_name ) : esc_html__( 'Please enter a valid date.', 'gravityforms' );
				$this->validation_message = empty( $this->errorMessage ) ? $message : $this->errorMessage;
			}
		}
	}

	public function is_value_submission_empty( $form_id ) {
		$value = rgpost( 'input_' . $this->id );
		if ( is_array( $value ) ) {
			// Date field and date drop-downs
			foreach ( $value as $input ) {
				if ( strlen( trim( $input ) ) <= 0 ) {
					return true;
				}
			}

			return false;
		} else {

			// Date picker
			return strlen( trim( $value ) ) <= 0;
		}
	}

	public function get_field_input( $form, $value = '', $entry = null ) {

		$picker_value = '';
		if ( is_array( $value ) ) {
			// GFCommon::parse_date() takes a numeric array.
			$value = array_values( $value );
		} else {
			$picker_value = $value;
		}
		$format    = empty( $this->dateFormat ) ? 'mdy' : esc_attr( $this->dateFormat );
		$date_info = GFCommon::parse_date( $value, $format );

		$day_value   = esc_attr( rgget( 'day', $date_info ) );
		$month_value = esc_attr( rgget( 'month', $date_info ) );
		$year_value  = esc_attr( rgget( 'year', $date_info ) );

		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$form_id  = $form['id'];
		$id       = intval( $this->id );
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$size          = $this->size;
		$disabled_text = $is_form_editor ? "disabled='disabled'" : '';
		$class_suffix  = $is_entry_detail ? '_admin' : '';
		$class         = $size . $class_suffix;

		$form_sub_label_placement  = rgar( $form, 'subLabelPlacement' );
		$field_sub_label_placement = rgar( $this, 'subLabelPlacement' );
		$is_sub_label_above        = $field_sub_label_placement == 'above' || ( empty( $field_sub_label_placement ) && $form_sub_label_placement == 'above' );
		$sub_label_class_attribute = $field_sub_label_placement == 'hidden_label' ? "class='hidden_sub_label screen-reader-text'" : '';

		$month_input = GFFormsModel::get_input( $this, $this->id . '.1' );
		$day_input   = GFFormsModel::get_input( $this, $this->id . '.2' );
		$year_input  = GFFormsModel::get_input( $this, $this->id . '.3' );

		$month_sub_label = rgar( $month_input, 'customLabel' ) != '' ? $month_input['customLabel'] : esc_html( _x( 'MM', 'Abbreviation: Month', 'gravityforms' ) );
		$day_sub_label   = rgar( $day_input, 'customLabel' ) != '' ? $day_input['customLabel'] : esc_html__( 'DD', 'gravityforms' );
		$year_sub_label  = rgar( $year_input, 'customLabel' ) != '' ? $year_input['customLabel'] : esc_html__( 'YYYY', 'gravityforms' );

		$month_placeholder_attribute = GFCommon::get_input_placeholder_attribute( $month_input );
		$day_placeholder_attribute   = GFCommon::get_input_placeholder_attribute( $day_input );
		$year_placeholder_attribute  = GFCommon::get_input_placeholder_attribute( $year_input );

		$month_placeholder_value = GFCommon::get_input_placeholder_value( $month_input );
		$day_placeholder_value   = GFCommon::get_input_placeholder_value( $day_input );
		$year_placeholder_value  = GFCommon::get_input_placeholder_value( $year_input );

		$date_picker_placeholder = $this->get_field_placeholder_attribute();

		$is_html5               = RGFormsModel::is_html5_enabled();
		$date_input_type        = $is_html5 ? 'number' : 'text';

		$month_html5_attributes = $is_html5 ? "min='1' max='12' step='1'" : '';
		$day_html5_attributes   = $is_html5 ? "min='1' max='31' step='1'" : '';

		$year_min = apply_filters( 'gform_date_min_year', '1920', $form, $this );
		$year_max = apply_filters( 'gform_date_max_year', date( 'Y' ) + 1, $form, $this );

		$year_min_attribute  = $is_html5 && is_numeric( $year_min ) ? "min='{$year_min}'" : '';
		$year_max_attribute  = $is_html5 && is_numeric( $year_max ) ? "max='{$year_max}'" : '';
		$year_step_attribute = $is_html5 ? "step='1'" : '';

		$field_position = substr( $format, 0, 3 );
		if ( $is_form_editor ) {
			$datepicker_display = in_array( $this->dateType, array( 'datefield', 'datedropdown' ) ) ? 'none' : 'inline';
			$datefield_display  = $this->dateType == 'datefield' ? 'inline' : 'none';
			$dropdown_display   = $this->dateType == 'datedropdown' ? 'inline' : 'none';
			$icon_display       = $this->calendarIconType == 'calendar' ? 'inline' : 'none';

			if ( $is_sub_label_above ) {
				$month_field = "<div class='gfield_date_month ginput_date' id='gfield_input_date_month' style='display:$datefield_display'>
                                    <label for='{$field_id}_1' {$sub_label_class_attribute}>{$month_sub_label}</label>
                                    <input id='{$field_id}_1' name='ginput_month' type='text' {$month_placeholder_attribute} {$disabled_text} value='{$month_value}'/>
                                </div>";
				$day_field   = "<div class='gfield_date_day ginput_date' id='gfield_input_date_day' style='display:$datefield_display'>
                                    <label for='{$field_id}_2' {$sub_label_class_attribute}>{$day_sub_label}</label>
                                    <input id='{$field_id}_2' name='ginput_day' type='text' {$day_placeholder_attribute} {$disabled_text} value='{$day_value}'/>
                               </div>";
				$year_field  = "<div class='gfield_date_year ginput_date' id='gfield_input_date_year' style='display:$datefield_display'>
                                    <label {$sub_label_class_attribute}>{$year_sub_label}</label>
                                    <input id='{$field_id}_3' type='text' name='text' {$year_placeholder_attribute} {$disabled_text} value='{$year_value}'/>
                               </div>";
			} else {
				$month_field = "<div class='gfield_date_month ginput_date' id='gfield_input_date_month' style='display:$datefield_display'>
                                    <input id='{$field_id}_1' name='ginput_month' type='text' {$month_placeholder_attribute} {$disabled_text} value='{$month_value}'/>
                                    <label for='{$field_id}_1' {$sub_label_class_attribute}>{$month_sub_label}</label>
                                </div>";
				$day_field   = "<div class='gfield_date_day ginput_date' id='gfield_input_date_day' style='display:$datefield_display'>
                                    <input id='{$field_id}_2' name='ginput_day' type='text' {$day_placeholder_attribute} {$disabled_text} value='{$day_value}'/>
                                    <label for='{$field_id}_2' {$sub_label_class_attribute}>{$day_sub_label}</label>
                              </div>";
				$year_field  = "<div class='gfield_date_year ginput_date' id='gfield_input_date_year' style='display:$datefield_display'>
                                    <input type='text' id='{$field_id}_3' name='ginput_year' {$year_placeholder_attribute} {$disabled_text} value='{$year_value}'/>
                                    <label for='{$field_id}_3' {$sub_label_class_attribute}>{$year_sub_label}</label>
                               </div>";
			}

			$month_dropdown = "<div class='gfield_date_dropdown_month ginput_date_dropdown' id='gfield_dropdown_date_month' style='display:$dropdown_display'>" . $this->get_month_dropdown( '', "{$field_id}_1", rgar( $date_info, 'month' ), '', $disabled_text, $month_placeholder_value ) . '</div>';
			$day_dropdown   = "<div class='gfield_date_dropdown_day ginput_date_dropdown' id='gfield_dropdown_date_day' style='display:$dropdown_display'>" . $this->get_day_dropdown( '', "{$field_id}_2", rgar( $date_info, 'day' ), '', $disabled_text, $day_placeholder_value ) . '</div>';
			$year_dropdown  = "<div class='gfield_date_dropdown_year ginput_date_dropdown' id='gfield_dropdown_date_year' style='display:$dropdown_display'>" . $this->get_year_dropdown( '', "{$field_id}_3", rgar( $date_info, 'year' ), '', $disabled_text, $year_placeholder_value, $form ) . '</div>';

			$field_string = "<div class='ginput_container ginput_container_date' id='gfield_input_datepicker' style='display:$datepicker_display'><input name='ginput_datepicker' type='text' {$date_picker_placeholder} {$disabled_text} value = '{$picker_value}'/><img src='" . GFCommon::get_base_url() . "/images/calendar.png' id='gfield_input_datepicker_icon' style='display:$icon_display'/></div>";

			switch ( $field_position ) {
				case 'dmy' :
					$date_inputs = $day_field . $month_field . $year_field . $day_dropdown . $month_dropdown . $year_dropdown;
					break;

				case 'ymd' :
					$date_inputs = $year_field . $month_field . $day_field . $year_dropdown . $month_dropdown . $day_dropdown;
					break;

				default :
					$date_inputs = $month_field . $day_field . $year_field . $month_dropdown . $day_dropdown . $year_dropdown;
					break;
			}

			$field_string .= "<div id='{$field_id}' class='ginput_container ginput_container_date'>{$date_inputs}</div>";

			return $field_string;
		} else {

			$date_type = $this->dateType;
			if ( in_array( $date_type, array( 'datefield', 'datedropdown' ) ) ) {

				switch ( $field_position ) {

					case 'dmy' :

						$tabindex = $this->get_tabindex();

						if ( $date_type == 'datedropdown' ) {

							$field_str = "<div class='clear-multi'><div class='gfield_date_dropdown_day ginput_container ginput_container_date' id='{$field_id}_2_container'>" . $this->get_day_dropdown( "input_{$id}[]", "{$field_id}_2", rgar( $date_info, 'day' ), $tabindex, $disabled_text, $day_placeholder_value ) . '</div>';

							$tabindex = $this->get_tabindex();

							$field_str .= "<div class='gfield_date_dropdown_month ginput_container ginput_container_date' id='{$field_id}_1_container'>" . $this->get_month_dropdown( "input_{$id}[]", "{$field_id}_1", rgar( $date_info, 'month' ), $tabindex, $disabled_text, $month_placeholder_value ) . '</div>';

							$tabindex = $this->get_tabindex();

							$field_str .= "<div class='gfield_date_dropdown_year ginput_container ginput_container_date' id='{$field_id}_3_container'>" . $this->get_year_dropdown( "input_{$id}[]", "{$field_id}_3", rgar( $date_info, 'year' ), $tabindex, $disabled_text, $year_placeholder_value, $form ) . '</div></div>';
						} else {

							$field_str = $is_sub_label_above
								? "<div class='clear-multi'>
                                        <div class='gfield_date_day ginput_container ginput_container_date' id='{$field_id}_2_container'>
                                            <label for='{$field_id}_2' {$sub_label_class_attribute}>{$day_sub_label}</label>
                                            <input type='{$date_input_type}' maxlength='2' name='input_{$id}[]' id='{$field_id}_2' value='$day_value' {$tabindex} {$disabled_text} {$day_placeholder_attribute} {$day_html5_attributes}/>
                                        </div>"
								: "<div class='clear-multi'>
                                        <div class='gfield_date_day ginput_container ginput_container_date' id='{$field_id}_2_container'>
                                            <input type='{$date_input_type}' maxlength='2' name='input_{$id}[]' id='{$field_id}_2' value='$day_value' {$tabindex} {$disabled_text} {$day_placeholder_attribute} {$day_html5_attributes}/>
                                            <label for='{$field_id}_2' {$sub_label_class_attribute}>{$day_sub_label}</label>
                                        </div>";

							$tabindex = $this->get_tabindex();

							$field_str .= $is_sub_label_above
								? "<div class='gfield_date_month ginput_container ginput_container_date' id='{$field_id}_1_container'>
                                        <label for='{$field_id}_1' {$sub_label_class_attribute}>{$month_sub_label}</label>
                                        <input type='{$date_input_type}' maxlength='2' name='input_{$id}[]' id='{$field_id}_1' value='{$month_value}' {$tabindex} {$disabled_text} {$month_placeholder_attribute} {$month_html5_attributes}/>
                                   </div>"
								: "<div class='gfield_date_month ginput_container ginput_container_date' id='{$field_id}_1_container'>
                                        <input type='{$date_input_type}' maxlength='2' name='input_{$id}[]' id='{$field_id}_1' value='{$month_value}' {$tabindex} {$disabled_text} {$month_placeholder_attribute} {$month_html5_attributes}/>
                                        <label for='{$field_id}_1' {$sub_label_class_attribute}>{$month_sub_label}</label>
                                   </div>";

							$tabindex = $this->get_tabindex();

							$field_str .= $is_sub_label_above
								? "<div class='gfield_date_year ginput_container ginput_container_date' id='{$field_id}_3_container'>
                                            <label for='{$field_id}_3' {$sub_label_class_attribute}>{$year_sub_label}</label>
                                            <input type='{$date_input_type}' maxlength='4' name='input_{$id}[]' id='{$field_id}_3' value='{$year_value}' {$tabindex} {$disabled_text} {$year_placeholder_attribute} {$year_min_attribute} {$year_max_attribute} {$year_step_attribute}/>
                                       </div>
                                    </div>"
								: "<div class='gfield_date_year ginput_container ginput_container_date' id='{$field_id}_3_container'>
                                        <input type='{$date_input_type}' maxlength='4' name='input_{$id}[]' id='{$field_id}_3' value='{$year_value}' {$tabindex} {$disabled_text} {$year_placeholder_attribute} {$year_min_attribute} {$year_max_attribute} {$year_step_attribute}/>
                                        <label for='{$field_id}_3' {$sub_label_class_attribute}>{$year_sub_label}</label>
                                   </div>
                                </div>";

						}

						break;

					case 'ymd' :

						$tabindex = $this->get_tabindex();

						if ( $date_type == 'datedropdown' ) {

							$field_str = "<div class='clear-multi'><div class='gfield_date_dropdown_year ginput_container ginput_container_date' id='{$field_id}_3_container'>" . $this->get_year_dropdown( "input_{$id}[]", "{$field_id}_3", rgar( $date_info, 'year' ), $tabindex, $disabled_text, $year_placeholder_value, $form ) . '</div>';

							$tabindex = $this->get_tabindex();

							$field_str .= "<div class='gfield_date_dropdown_month ginput_container ginput_container_date' id='{$field_id}_1_container'>" . $this->get_month_dropdown( "input_{$id}[]", "{$field_id}_1", rgar( $date_info, 'month' ), $tabindex, $disabled_text, $month_placeholder_value ) . '</div>';

							$tabindex = $this->get_tabindex();

							$field_str .= "<div class='gfield_date_dropdown_day ginput_container ginput_container_date' id='{$field_id}_2_container'>" . $this->get_day_dropdown( "input_{$id}[]", "{$field_id}_2", rgar( $date_info, 'day' ), $tabindex, $disabled_text, $day_placeholder_value ) . '</div></div>';
						} else {

							$field_str = $is_sub_label_above
								? "<div class='clear-multi'>
                                            <div class='gfield_date_year ginput_container ginput_container_date' id='{$field_id}_3_container'>
                                                <label for='{$field_id}_3' {$sub_label_class_attribute}>{$year_sub_label}</label>
                                                <input type='{$date_input_type}' maxlength='4' name='input_{$id}[]' id='{$field_id}_3' value='{$year_value}' {$tabindex} {$disabled_text} {$year_placeholder_attribute} {$year_min_attribute} {$year_max_attribute} {$year_step_attribute}/>
                                            </div>"
								: "<div class='clear-multi'>
                                            <div class='gfield_date_year ginput_container ginput_container_date' id='{$field_id}_3_container'>
                                                <input type='{$date_input_type}' maxlength='4' name='input_{$id}[]' id='{$field_id}_3' value='{$year_value}' {$tabindex} {$disabled_text} {$year_placeholder_attribute} {$year_min_attribute} {$year_max_attribute} {$year_step_attribute}/>
                                                <label for='{$field_id}_3' {$sub_label_class_attribute}>{$year_sub_label}</label>
                                            </div>";

							$tabindex = $this->get_tabindex();

							$field_str .= $is_sub_label_above
								? "<div class='gfield_date_month ginput_container ginput_container_date' id='{$field_id}_1_container'>
                                                <label for='{$field_id}_1' {$sub_label_class_attribute}>{$month_sub_label}</label>
                                                <input type='{$date_input_type}' maxlength='2' name='input_{$id}[]' id='{$field_id}_1' value='{$month_value}' {$tabindex} {$disabled_text} {$month_placeholder_attribute} {$month_html5_attributes}/>
                                            </div>"
								: "<div class='gfield_date_month ginput_container ginput_container_date' id='{$field_id}_1_container'>
                                                <input type='{$date_input_type}' maxlength='2' name='input_{$id}[]' id='{$field_id}_1' value='{$month_value}' {$tabindex} {$disabled_text} {$month_placeholder_attribute} {$month_html5_attributes}/>
                                                <label for='{$field_id}_1' {$sub_label_class_attribute}>{$month_sub_label}</label>
                                            </div>";

							$tabindex = $this->get_tabindex();

							$field_str .= $is_sub_label_above
								? "<div class='gfield_date_day ginput_container ginput_container_date' id='{$field_id}_2_container'>
                                                <label for='{$field_id}_2' {$sub_label_class_attribute}>{$day_sub_label}</label>
                                                <input type='{$date_input_type}' maxlength='2' name='input_{$id}[]' id='{$field_id}_2' value='{$day_value}' {$tabindex} {$disabled_text} {$day_placeholder_attribute} {$day_html5_attributes}/>
                                           </div>
                                        </div>"
								: "<div class='gfield_date_day ginput_container ginput_container_date' id='{$field_id}_2_container'>
                                                <input type='{$date_input_type}' maxlength='2' name='input_{$id}[]' id='{$field_id}_2' value='{$day_value}' {$tabindex} {$disabled_text} {$day_placeholder_attribute} {$day_html5_attributes}/>
                                                <label for='{$field_id}_2' {$sub_label_class_attribute}>{$day_sub_label}</label>
                                           </div>
                                        </div>";
						}

						break;

					default :
						$tabindex = $this->get_tabindex();

						if ( $date_type == 'datedropdown' ) {

							$field_str = "<div class='clear-multi'><div class='gfield_date_dropdown_month ginput_container ginput_container_date' id='{$field_id}_1_container'>" . $this->get_month_dropdown( "input_{$id}[]", "{$field_id}_1", rgar( $date_info, 'month' ), $tabindex, $disabled_text, $month_placeholder_value ) . '</div>';

							$tabindex = $this->get_tabindex();

							$field_str .= "<div class='gfield_date_dropdown_day ginput_container ginput_container_date' id='{$field_id}_2_container'>" . $this->get_day_dropdown( "input_{$id}[]", "{$field_id}_2", rgar( $date_info, 'day' ), $tabindex, $disabled_text, $day_placeholder_value ) . '</div>';

							$tabindex = $this->get_tabindex();

							$field_str .= "<div class='gfield_date_dropdown_year ginput_container ginput_container_date' id='{$field_id}_3_container'>" . $this->get_year_dropdown( "input_{$id}[]", "{$field_id}_3", rgar( $date_info, 'year' ), $tabindex, $disabled_text, $year_placeholder_value, $form ) . '</div></div>';
						} else {

							$field_str = $is_sub_label_above
								? "<div class='clear-multi'><div class='gfield_date_month ginput_container ginput_container_date' id='{$field_id}_1_container'>
                                            <label for='{$field_id}_1' {$sub_label_class_attribute}>{$month_sub_label}</label>
                                            <input type='{$date_input_type}' maxlength='2' name='input_{$id}[]' id='{$field_id}_1' value='{$month_value}' {$tabindex} {$disabled_text} {$month_placeholder_attribute} {$month_html5_attributes}/>
                                        </div>"
								: "<div class='clear-multi'><div class='gfield_date_month ginput_container ginput_container_date' id='{$field_id}_1_container'>
                                            <input type='{$date_input_type}' maxlength='2' name='input_{$id}[]' id='{$field_id}_1' value='{$month_value}' {$tabindex} {$disabled_text} {$month_placeholder_attribute} {$month_html5_attributes}/>
                                            <label for='{$field_id}_1' {$sub_label_class_attribute}>{$month_sub_label}</label>
                                        </div>";

							$tabindex = $this->get_tabindex();

							$field_str .= $is_sub_label_above
								? "<div class='gfield_date_day ginput_container ginput_container_date' id='{$field_id}_2_container'>
                                            <label for='{$field_id}_2' {$sub_label_class_attribute}>{$day_sub_label}</label>
                                            <input type='{$date_input_type}' maxlength='2' name='input_{$id}[]' id='{$field_id}_2' value='{$day_value}' {$tabindex} {$disabled_text} {$day_placeholder_attribute} {$day_html5_attributes}/>
                                        </div>"
								: "<div class='gfield_date_day ginput_container ginput_container_date' id='{$field_id}_2_container'>
                                            <input type='{$date_input_type}' maxlength='2' name='input_{$id}[]' id='{$field_id}_2' value='{$day_value}' {$tabindex} {$disabled_text} {$day_placeholder_attribute} {$day_html5_attributes}/>
                                            <label for='{$field_id}_2' {$sub_label_class_attribute}>{$day_sub_label}</label>
                                        </div>";

							$tabindex = $this->get_tabindex();

							$field_str .= $is_sub_label_above
								? "<div class='gfield_date_year ginput_container ginput_container_date' id='{$field_id}_3_container'>
                                            <label for='{$field_id}_3' {$sub_label_class_attribute}>{$year_sub_label}</label>
                                            <input type='{$date_input_type}' maxlength='4' name='input_{$id}[]' id='{$field_id}_3' value='{$year_value}' {$tabindex} {$disabled_text} {$year_placeholder_attribute} {$year_min_attribute} {$year_max_attribute} {$year_step_attribute}/>
                                       </div>
                                   </div>"
								: "<div class='gfield_date_year ginput_container ginput_container_date' id='{$field_id}_3_container'>
                                            <input type='{$date_input_type}' maxlength='4' name='input_{$id}[]' id='{$field_id}_3' value='{$year_value}' {$tabindex} {$disabled_text} {$year_placeholder_attribute} {$year_min_attribute} {$year_max_attribute} {$year_step_attribute}/>
                                            <label for='{$field_id}_3' {$sub_label_class_attribute}>{$year_sub_label}</label>
                                       </div>
                                   </div>";
						}

						break;
				}

				return "<div id='{$field_id}' class='ginput_container ginput_container_date'>$field_str</div>";
			} else {
				$picker_value = esc_attr( GFCommon::date_display( $picker_value, $format ) );
				$icon_class   = $this->calendarIconType == 'none' ? 'datepicker_no_icon' : 'datepicker_with_icon';
				$icon_url     = empty( $this->calendarIconUrl ) ? GFCommon::get_base_url() . '/images/calendar.png' : $this->calendarIconUrl;
				$icon_url = esc_url( $icon_url );
				$tabindex     = $this->get_tabindex();
				$class        = esc_attr( $class );

				return "<div class='ginput_container ginput_container_date'>
                            <input name='input_{$id}' id='{$field_id}' type='text' value='{$picker_value}' class='datepicker {$class} {$format} {$icon_class}' {$tabindex} {$disabled_text} {$date_picker_placeholder}/>
                        </div>
                        <input type='hidden' id='gforms_calendar_icon_$field_id' class='gform_hidden' value='$icon_url'/>";
			}
		}
	}

	public function get_value_default() {

		$value = parent::get_value_default();

		if ( is_array( $this->inputs ) ) {
			$value = $this->get_date_array_by_format( $value );
		}

		return $value;
	}

	/**
	 * The default value for mulit-input date fields will always be an array in mdy order
	 * this code will alter the order of the values to the date format of the field
	 */
	public function get_date_array_by_format( $value ) {
		$format   = empty( $this->dateFormat ) ? 'mdy' : esc_attr( $this->dateFormat );
		$position = substr( $format, 0, 3 );
		$date     = array_combine( array( 'm', 'd', 'y' ), $value );            // takes our numerical array and converts it to an associative array
		$value    = array_merge( array_flip( str_split( $position ) ), $date ); // uses the mdy position as the array keys and creates a new array in the desired order

		return $value;
	}

	public function checkdate( $month, $day, $year ) {
		if ( empty( $month ) || ! is_numeric( $month ) || empty( $day ) || ! is_numeric( $day ) || empty( $year ) || ! is_numeric( $year ) || strlen( $year ) != 4 ) {
			return false;
		}

		return checkdate( $month, $day, $year );
	}

	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		return GFCommon::date_display( $value, $this->dateFormat );
	}


	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		return GFCommon::date_display( $value, $this->dateFormat );
	}

	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		$format_modifier = empty( $modifier ) ? $this->dateFormat : $modifier;

		return GFCommon::date_display( $value, $format_modifier );
	}

	private function get_month_dropdown( $name = '', $id = '', $selected_value = '', $tabindex = '', $disabled_text = '', $placeholder = '' ) {
		if ( $placeholder == '' ) {
			$placeholder = esc_html__( 'Month', 'gravityforms' );
		}

		return $this->get_number_dropdown( $name, $id, $selected_value, $tabindex, $disabled_text, $placeholder, 1, 12 );
	}

	private function get_day_dropdown( $name = '', $id = '', $selected_value = '', $tabindex = '', $disabled_text = '', $placeholder = '' ) {
		if ( $placeholder == '' ) {
			$placeholder = esc_html__( 'Day', 'gravityforms' );
		}

		return $this->get_number_dropdown( $name, $id, $selected_value, $tabindex, $disabled_text, $placeholder, 1, 31 );
	}

	private function get_year_dropdown( $name = '', $id = '', $selected_value = '', $tabindex = '', $disabled_text = '', $placeholder = '', $form ) {
		if ( $placeholder == '' ) {
			$placeholder = esc_html__( 'Year', 'gravityforms' );
		}
		$year_min = apply_filters( 'gform_date_min_year', '1920', $form, $this );
		$year_max = apply_filters( 'gform_date_max_year', date( 'Y' ) + 1, $form, $this );

		return $this->get_number_dropdown( $name, $id, $selected_value, $tabindex, $disabled_text, $placeholder, $year_max, $year_min );
	}

	private function get_number_dropdown( $name, $id, $selected_value, $tabindex, $disabled_text, $placeholder, $start_number, $end_number ) {
		$str = "<select name='{$name}' id='{$id}' {$tabindex} {$disabled_text} >";
		if ( $placeholder !== false ) {
			$str .= "<option value=''>{$placeholder}</option>";
		}

		$increment = $start_number < $end_number ? 1 : - 1;

		for ( $i = $start_number; $i != ( $end_number + $increment ); $i += $increment ) {
			$selected = intval( $i ) == intval( $selected_value ) ? "selected='selected'" : '';
			$str .= "<option value='{$i}' {$selected}>{$i}</option>";
		}
		$str .= '</select>';

		return $str;
	}

	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		// if $value is a default value and also an array, it will be an associative array; to be safe, let's convert all array $value to numeric
		if( is_array( $value ) ) {
			$value = array_values( $value );
		}
		return GFFormsModel::prepare_date( $this->dateFormat, $value );
	}

	public function get_entry_inputs() {
		return null;
	}

	public function sanitize_settings() {
		parent::sanitize_settings();
		$this->calendarIconType = wp_strip_all_tags( $this->calendarIconType );
		$this->calendarIconUrl = wp_strip_all_tags( $this->calendarIconUrl );
		if ( $this->dateFormat && ! in_array( $this->dateFormat, array( 'mdy', 'dmy', 'dmy_dash', 'dmy_dot', 'ymd_slash', 'ymd_dash', 'ymd_dot'  ) ) ) {
			$this->dateFormat = 'mdy';
		}

	}

}

GF_Fields::register( new GF_Field_Date() );