<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Field_Address extends GF_Field {

	public $type = 'address';

	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'label_setting',
			'admin_label_setting',
			'label_placement_setting',
			'sub_label_placement_setting',
			'default_input_values_setting',
			'input_placeholders_setting',
			'address_setting',
			'rules_setting',
			'copy_values_option',
			'description_setting',
			'visibility_setting',
			'css_class_setting',
		);
	}

	public function is_conditional_logic_supported() {
		return true;
	}

	public function get_form_editor_field_title() {
		return esc_attr__( 'Address', 'gravityforms' );
	}

	function validate( $value, $form ) {

		if ( $this->isRequired ) {
			$copy_values_option_activated = $this->enableCopyValuesOption && rgpost( 'input_' . $this->id . '_copy_values_activated' );
			if ( $copy_values_option_activated ) {
				// validation will occur in the source field
				return;
			}

			$street  = rgar( $value, $this->id . '.1' );
			$city    = rgar( $value, $this->id . '.3' );
			$state   = rgar( $value, $this->id . '.4' );
			$zip     = rgar( $value, $this->id . '.5' );
			$country = rgar( $value, $this->id . '.6' );

			if ( empty( $street ) && ! $this->get_input_property( $this->id . '.1', 'isHidden' )
			     || empty( $city ) && ! $this->get_input_property( $this->id . '.3', 'isHidden' )
			     || empty( $zip ) && ! $this->get_input_property( $this->id . '.5', 'isHidden' )
			     || ( empty( $state ) && ! ( $this->hideState || $this->get_input_property( $this->id . '.4', 'isHidden' ) ) )
			     || ( empty( $country ) && ! ( $this->hideCountry || $this->get_input_property( $this->id . '.6', 'isHidden' ) ) )
			) {
				$this->failed_validation  = true;
				$this->validation_message = empty( $this->errorMessage ) ? esc_html__( 'This field is required. Please enter a complete address.', 'gravityforms' ) : $this->errorMessage;
			}
		}
	}

	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {

		$value                                         = parent::get_value_submission( $field_values, $get_from_post_global_var );
		$value[ $this->id . '_copy_values_activated' ] = (bool) rgpost( 'input_' . $this->id . '_copy_values_activated' );

		return $value;
	}

	public function get_field_input( $form, $value = '', $entry = null ) {

		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$is_admin        = $is_entry_detail || $is_form_editor;

		$form_id  = absint( $form['id'] );
		$id       = intval( $this->id );
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
		$form_id  = ( $is_entry_detail || $is_form_editor ) && empty( $form_id ) ? rgget( 'id' ) : $form_id;

		$disabled_text      = $is_form_editor ? "disabled='disabled'" : '';
		$class_suffix       = $is_entry_detail ? '_admin' : '';
		$required_attribute = $this->isRequired ? 'aria-required="true"' : '';

		$form_sub_label_placement  = rgar( $form, 'subLabelPlacement' );
		$field_sub_label_placement = $this->subLabelPlacement;
		$is_sub_label_above        = $field_sub_label_placement == 'above' || ( empty( $field_sub_label_placement ) && $form_sub_label_placement == 'above' );
		$sub_label_class_attribute = $field_sub_label_placement == 'hidden_label' ? "class='hidden_sub_label screen-reader-text'" : '';

		$street_value  = '';
		$street2_value = '';
		$city_value    = '';
		$state_value   = '';
		$zip_value     = '';
		$country_value = '';

		if ( is_array( $value ) ) {
			$street_value  = esc_attr( rgget( $this->id . '.1', $value ) );
			$street2_value = esc_attr( rgget( $this->id . '.2', $value ) );
			$city_value    = esc_attr( rgget( $this->id . '.3', $value ) );
			$state_value   = esc_attr( rgget( $this->id . '.4', $value ) );
			$zip_value     = esc_attr( rgget( $this->id . '.5', $value ) );
			$country_value = esc_attr( rgget( $this->id . '.6', $value ) );
		}

		// Inputs.
		$address_street_field_input  = GFFormsModel::get_input( $this, $this->id . '.1' );
		$address_street2_field_input = GFFormsModel::get_input( $this, $this->id . '.2' );
		$address_city_field_input    = GFFormsModel::get_input( $this, $this->id . '.3' );
		$address_state_field_input   = GFFormsModel::get_input( $this, $this->id . '.4' );
		$address_zip_field_input     = GFFormsModel::get_input( $this, $this->id . '.5' );
		$address_country_field_input = GFFormsModel::get_input( $this, $this->id . '.6' );

		// Placeholders.
		$street_placeholder_attribute  = GFCommon::get_input_placeholder_attribute( $address_street_field_input );
		$street2_placeholder_attribute = GFCommon::get_input_placeholder_attribute( $address_street2_field_input );
		$city_placeholder_attribute    = GFCommon::get_input_placeholder_attribute( $address_city_field_input );
		$zip_placeholder_attribute     = GFCommon::get_input_placeholder_attribute( $address_zip_field_input );

		$address_types = $this->get_address_types( $form_id );
		$addr_type     = empty( $this->addressType ) ? $this->get_default_address_type( $form_id ) : $this->addressType;
		$address_type  = rgar( $address_types, $addr_type );

		$state_label  = empty( $address_type['state_label'] ) ? esc_html__( 'State', 'gravityforms' ) : $address_type['state_label'];
		$zip_label    = empty( $address_type['zip_label'] ) ? esc_html__( 'Zip Code', 'gravityforms' ) : $address_type['zip_label'];
		$hide_country = ! empty( $address_type['country'] ) || $this->hideCountry || rgar( $address_country_field_input, 'isHidden' );

		if ( empty( $country_value ) ) {
			$country_value = $this->defaultCountry;
		}

		if ( empty( $state_value ) ) {
			$state_value = $this->defaultState;
		}

		$country_placeholder = GFCommon::get_input_placeholder_value( $address_country_field_input );
		$country_list        = $this->get_country_dropdown( $country_value, $country_placeholder );

		// Changing css classes based on field format to ensure proper display.
		$address_display_format = apply_filters( 'gform_address_display_format', 'default', $this );
		$city_location          = $address_display_format == 'zip_before_city' ? 'right' : 'left';
		$zip_location           = $address_display_format != 'zip_before_city' && ( $this->hideState || rgar( $address_state_field_input, 'isHidden' ) ) ? 'right' : 'left'; // support for $this->hideState legacy property
		$state_location         = $address_display_format == 'zip_before_city' ? 'left' : 'right';
		$country_location       = $this->hideState || rgar( $address_state_field_input, 'isHidden' ) ? 'left' : 'right'; // support for $this->hideState legacy property

		// Labels.
		$address_street_sub_label  = rgar( $address_street_field_input, 'customLabel' ) != '' ? $address_street_field_input['customLabel'] : esc_html__( 'Street Address', 'gravityforms' );
		$address_street_sub_label  = gf_apply_filters( array( 'gform_address_street', $form_id, $this->id ), $address_street_sub_label, $form_id );
		$address_street2_sub_label = rgar( $address_street2_field_input, 'customLabel' ) != '' ? $address_street2_field_input['customLabel'] : esc_html__( 'Address Line 2', 'gravityforms' );
		$address_street2_sub_label = gf_apply_filters( array( 'gform_address_street2', $form_id, $this->id ), $address_street2_sub_label, $form_id );
		$address_zip_sub_label     = rgar( $address_zip_field_input, 'customLabel' ) != '' ? $address_zip_field_input['customLabel'] : $zip_label;
		$address_zip_sub_label     = gf_apply_filters( array( 'gform_address_zip', $form_id, $this->id ), $address_zip_sub_label, $form_id );
		$address_city_sub_label    = rgar( $address_city_field_input, 'customLabel' ) != '' ? $address_city_field_input['customLabel'] : esc_html__( 'City', 'gravityforms' );
		$address_city_sub_label    = gf_apply_filters( array( 'gform_address_city', $form_id, $this->id ), $address_city_sub_label, $form_id );
		$address_state_sub_label   = rgar( $address_state_field_input, 'customLabel' ) != '' ? $address_state_field_input['customLabel'] : $state_label;
		$address_state_sub_label   = gf_apply_filters( array( 'gform_address_state', $form_id, $this->id ), $address_state_sub_label, $form_id );
		$address_country_sub_label = rgar( $address_country_field_input, 'customLabel' ) != '' ? $address_country_field_input['customLabel'] : esc_html__( 'Country', 'gravityforms' );
		$address_country_sub_label = gf_apply_filters( array( 'gform_address_country', $form_id, $this->id ), $address_country_sub_label, $form_id );

		// Address field.
		$street_address = '';
		$tabindex       = $this->get_tabindex();
		$style          = ( $is_admin && rgar( $address_street_field_input, 'isHidden' ) ) ? "style='display:none;'" : '';
		if ( $is_admin || ! rgar( $address_street_field_input, 'isHidden' ) ) {
			if ( $is_sub_label_above ) {
				$street_address = " <span class='ginput_full{$class_suffix} address_line_1' id='{$field_id}_1_container' {$style}>
                                        <label for='{$field_id}_1' id='{$field_id}_1_label' {$sub_label_class_attribute}>{$address_street_sub_label}</label>
                                        <input type='text' name='input_{$id}.1' id='{$field_id}_1' value='{$street_value}' {$tabindex} {$disabled_text} {$street_placeholder_attribute} {$required_attribute}/>
                                    </span>";
			} else {
				$street_address = " <span class='ginput_full{$class_suffix} address_line_1' id='{$field_id}_1_container' {$style}>
                                        <input type='text' name='input_{$id}.1' id='{$field_id}_1' value='{$street_value}' {$tabindex} {$disabled_text} {$street_placeholder_attribute} {$required_attribute}/>
                                        <label for='{$field_id}_1' id='{$field_id}_1_label' {$sub_label_class_attribute}>{$address_street_sub_label}</label>
                                    </span>";
			}
		}

		// Address line 2 field.
		$street_address2 = '';
		$style           = ( $is_admin && ( $this->hideAddress2 || rgar( $address_street2_field_input, 'isHidden' ) ) ) ? "style='display:none;'" : ''; // support for $this->hideAddress2 legacy property
		if ( $is_admin || ( ! $this->hideAddress2 && ! rgar( $address_street2_field_input, 'isHidden' ) ) ) {
			$tabindex = $this->get_tabindex();
			if ( $is_sub_label_above ) {
				$street_address2 = "<span class='ginput_full{$class_suffix} address_line_2' id='{$field_id}_2_container' {$style}>
                                        <label for='{$field_id}_2' id='{$field_id}_2_label' {$sub_label_class_attribute}>{$address_street2_sub_label}</label>
                                        <input type='text' name='input_{$id}.2' id='{$field_id}_2' value='{$street2_value}' {$tabindex} {$disabled_text} {$street2_placeholder_attribute}/>
                                    </span>";
			} else {
				$street_address2 = "<span class='ginput_full{$class_suffix} address_line_2' id='{$field_id}_2_container' {$style}>
                                        <input type='text' name='input_{$id}.2' id='{$field_id}_2' value='{$street2_value}' {$tabindex} {$disabled_text} {$street2_placeholder_attribute}/>
                                        <label for='{$field_id}_2' id='{$field_id}_2_label' {$sub_label_class_attribute}>{$address_street2_sub_label}</label>
                                    </span>";
			}
		}

		if ( $address_display_format == 'zip_before_city' ) {
			// Zip field.
			$zip      = '';
			$tabindex = $this->get_tabindex();
			$style    = ( $is_admin && rgar( $address_zip_field_input, 'isHidden' ) ) ? "style='display:none;'" : '';
			if ( $is_admin || ! rgar( $address_zip_field_input, 'isHidden' ) ) {
				if ( $is_sub_label_above ) {
					$zip = "<span class='ginput_{$zip_location}{$class_suffix} address_zip' id='{$field_id}_5_container' {$style}>
                                    <label for='{$field_id}_5' id='{$field_id}_5_label' {$sub_label_class_attribute}>{$address_zip_sub_label}</label>
                                    <input type='text' name='input_{$id}.5' id='{$field_id}_5' value='{$zip_value}' {$tabindex} {$disabled_text} {$zip_placeholder_attribute} {$required_attribute}/>
                                </span>";
				} else {
					$zip = "<span class='ginput_{$zip_location}{$class_suffix} address_zip' id='{$field_id}_5_container' {$style}>
                                    <input type='text' name='input_{$id}.5' id='{$field_id}_5' value='{$zip_value}' {$tabindex} {$disabled_text} {$zip_placeholder_attribute} {$required_attribute}/>
                                    <label for='{$field_id}_5' id='{$field_id}_5_label' {$sub_label_class_attribute}>{$address_zip_sub_label}</label>
                                </span>";
				}
			}

			// City field.
			$city     = '';
			$tabindex = $this->get_tabindex();
			$style    = ( $is_admin && rgar( $address_city_field_input, 'isHidden' ) ) ? "style='display:none;'" : '';
			if ( $is_admin || ! rgar( $address_city_field_input, 'isHidden' ) ) {
				if ( $is_sub_label_above ) {
					$city = "<span class='ginput_{$city_location}{$class_suffix} address_city' id='{$field_id}_3_container' {$style}>
                                    <label for='{$field_id}_3' id='{$field_id}_3_label' {$sub_label_class_attribute}>{$address_city_sub_label}</label>
                                    <input type='text' name='input_{$id}.3' id='{$field_id}_3' value='{$city_value}' {$tabindex} {$disabled_text} {$city_placeholder_attribute} {$required_attribute}/>
                                 </span>";
				} else {
					$city = "<span class='ginput_{$city_location}{$class_suffix} address_city' id='{$field_id}_3_container' {$style}>
                                    <input type='text' name='input_{$id}.3' id='{$field_id}_3' value='{$city_value}' {$tabindex} {$disabled_text} {$city_placeholder_attribute} {$required_attribute}/>
                                    <label for='{$field_id}_3' id='{$field_id}_3_label' {$sub_label_class_attribute}>{$address_city_sub_label}</label>
                                 </span>";
				}
			}

			// State field.
			$style = ( $is_admin && ( $this->hideState || rgar( $address_state_field_input, 'isHidden' ) ) ) ? "style='display:none;'" : ''; // support for $this->hideState legacy property
			if ( $is_admin || ( ! $this->hideState && ! rgar( $address_state_field_input, 'isHidden' ) ) ) {
				$state_field = $this->get_state_field( $id, $field_id, $state_value, $disabled_text, $form_id );
				if ( $is_sub_label_above ) {
					$state = "<span class='ginput_{$state_location}{$class_suffix} address_state' id='{$field_id}_4_container' {$style}>
                                           <label for='{$field_id}_4' id='{$field_id}_4_label' {$sub_label_class_attribute}>{$address_state_sub_label}</label>
                                           $state_field
                                      </span>";
				} else {
					$state = "<span class='ginput_{$state_location}{$class_suffix} address_state' id='{$field_id}_4_container' {$style}>
                                           $state_field
                                           <label for='{$field_id}_4' id='{$field_id}_4_label' {$sub_label_class_attribute}>{$address_state_sub_label}</label>
                                      </span>";
				}
			} else {
				$state = sprintf( "<input type='hidden' class='gform_hidden' name='input_%d.4' id='%s_4' value='%s'/>", $id, $field_id, $state_value );
			}
		} else {

			// City field.
			$city     = '';
			$tabindex = $this->get_tabindex();
			$style    = ( $is_admin && rgar( $address_city_field_input, 'isHidden' ) ) ? "style='display:none;'" : '';
			if ( $is_admin || ! rgar( $address_city_field_input, 'isHidden' ) ) {
				if ( $is_sub_label_above ) {
					$city = "<span class='ginput_{$city_location}{$class_suffix} address_city' id='{$field_id}_3_container' {$style}>
                                    <label for='{$field_id}_3' id='{$field_id}_3_label' {$sub_label_class_attribute}>{$address_city_sub_label}</label>
                                    <input type='text' name='input_{$id}.3' id='{$field_id}_3' value='{$city_value}' {$tabindex} {$disabled_text} {$city_placeholder_attribute} {$required_attribute}/>
                                 </span>";
				} else {
					$city = "<span class='ginput_{$city_location}{$class_suffix} address_city' id='{$field_id}_3_container' {$style}>
                                    <input type='text' name='input_{$id}.3' id='{$field_id}_3' value='{$city_value}' {$tabindex} {$disabled_text} {$city_placeholder_attribute} {$required_attribute}/>
                                    <label for='{$field_id}_3' id='{$field_id}_3_label' {$sub_label_class_attribute}>{$address_city_sub_label}</label>
                                 </span>";
				}
			}

			// State field.
			$style = ( $is_admin && ( $this->hideState || rgar( $address_state_field_input, 'isHidden' ) ) ) ? "style='display:none;'" : ''; // support for $this->hideState legacy property
			if ( $is_admin || ( ! $this->hideState && ! rgar( $address_state_field_input, 'isHidden' ) ) ) {
				$state_field = $this->get_state_field( $id, $field_id, $state_value, $disabled_text, $form_id );
				if ( $is_sub_label_above ) {
					$state = "<span class='ginput_{$state_location}{$class_suffix} address_state' id='{$field_id}_4_container' {$style}>
                                        <label for='{$field_id}_4' id='{$field_id}_4_label' {$sub_label_class_attribute}>$address_state_sub_label</label>
                                        $state_field
                                      </span>";
				} else {
					$state = "<span class='ginput_{$state_location}{$class_suffix} address_state' id='{$field_id}_4_container' {$style}>
                                        $state_field
                                        <label for='{$field_id}_4' id='{$field_id}_4_label' {$sub_label_class_attribute}>$address_state_sub_label</label>
                                      </span>";
				}
			} else {
				$state = sprintf( "<input type='hidden' class='gform_hidden' name='input_%d.4' id='%s_4' value='%s'/>", $id, $field_id, $state_value );
			}

			// Zip field.
			$zip      = '';
			$tabindex = GFCommon::get_tabindex();
			$style    = ( $is_admin && rgar( $address_zip_field_input, 'isHidden' ) ) ? "style='display:none;'" : '';
			if ( $is_admin || ! rgar( $address_zip_field_input, 'isHidden' ) ) {
				if ( $is_sub_label_above ) {
					$zip = "<span class='ginput_{$zip_location}{$class_suffix} address_zip' id='{$field_id}_5_container' {$style}>
                                    <label for='{$field_id}_5' id='{$field_id}_5_label' {$sub_label_class_attribute}>{$address_zip_sub_label}</label>
                                    <input type='text' name='input_{$id}.5' id='{$field_id}_5' value='{$zip_value}' {$tabindex} {$disabled_text} {$zip_placeholder_attribute} {$required_attribute}/>
                                </span>";
				} else {
					$zip = "<span class='ginput_{$zip_location}{$class_suffix} address_zip' id='{$field_id}_5_container' {$style}>
                                    <input type='text' name='input_{$id}.5' id='{$field_id}_5' value='{$zip_value}' {$tabindex} {$disabled_text} {$zip_placeholder_attribute} {$required_attribute}/>
                                    <label for='{$field_id}_5' id='{$field_id}_5_label' {$sub_label_class_attribute}>{$address_zip_sub_label}</label>
                                </span>";
				}
			}
		}

		if ( $is_admin || ! $hide_country ) {
			$style    = $hide_country ? "style='display:none;'" : '';
			$tabindex = $this->get_tabindex();
			if ( $is_sub_label_above ) {
				$country = "<span class='ginput_{$country_location}{$class_suffix} address_country' id='{$field_id}_6_container' {$style}>
                                        <label for='{$field_id}_6' id='{$field_id}_6_label' {$sub_label_class_attribute}>{$address_country_sub_label}</label>
                                        <select name='input_{$id}.6' id='{$field_id}_6' {$tabindex} {$disabled_text} {$required_attribute}>{$country_list}</select>
                                    </span>";
			} else {
				$country = "<span class='ginput_{$country_location}{$class_suffix} address_country' id='{$field_id}_6_container' {$style}>
                                        <select name='input_{$id}.6' id='{$field_id}_6' {$tabindex} {$disabled_text} {$required_attribute}>{$country_list}</select>
                                        <label for='{$field_id}_6' id='{$field_id}_6_label' {$sub_label_class_attribute}>{$address_country_sub_label}</label>
                                    </span>";
			}
		} else {
			$country = sprintf( "<input type='hidden' class='gform_hidden' name='input_%d.6' id='%s_6' value='%s'/>", $id, $field_id, $country_value );
		}

		$inputs = $address_display_format == 'zip_before_city' ? $street_address . $street_address2 . $zip . $city . $state . $country : $street_address . $street_address2 . $city . $state . $zip . $country;

		$copy_values_option = '';
		$input_style        = '';
		if ( ( $this->enableCopyValuesOption || $is_form_editor ) && ! $is_entry_detail ) {
			$copy_values_style      = $is_form_editor && ! $this->enableCopyValuesOption ? "style='display:none;'" : '';
			$copy_values_is_checked = isset( $value[$this->id . '_copy_values_activated'] ) ? $value[$this->id . '_copy_values_activated'] == true : $this->copyValuesOptionDefault == true;
			$copy_values_checked    = checked( true, $copy_values_is_checked, false );
			$copy_values_option     = "<div id='{$field_id}_copy_values_option_container' class='copy_values_option_container' {$copy_values_style}>
                                        <input type='checkbox' id='{$field_id}_copy_values_activated' class='copy_values_activated' value='1' name='input_{$id}_copy_values_activated' {$disabled_text} {$copy_values_checked}/>
                                        <label for='{$field_id}_copy_values_activated' id='{$field_id}_copy_values_option_label' class='copy_values_option_label inline'>{$this->copyValuesOptionLabel}</label>
                                    </div>";
			if ( $copy_values_is_checked ) {
				$input_style = "style='display:none;'";
			}
		}

		$css_class = $this->get_css_class();

		return "    {$copy_values_option}
                    <div class='ginput_complex{$class_suffix} ginput_container {$css_class}' id='$field_id' {$input_style}>
                        {$inputs}
                    <div class='gf_clear gf_clear_complex'></div>
                </div>";
	}

	public function get_field_label_class(){
		return 'gfield_label gfield_label_before_complex';
	}

	public function get_css_class() {

		$address_street_field_input  = GFFormsModel::get_input( $this, $this->id . '.1' );
		$address_street2_field_input = GFFormsModel::get_input( $this, $this->id . '.2' );
		$address_city_field_input    = GFFormsModel::get_input( $this, $this->id . '.3' );
		$address_state_field_input   = GFFormsModel::get_input( $this, $this->id . '.4' );
		$address_zip_field_input     = GFFormsModel::get_input( $this, $this->id . '.5' );
		$address_country_field_input = GFFormsModel::get_input( $this, $this->id . '.6' );

		$css_class = '';
		if ( ! rgar( $address_street_field_input, 'isHidden' ) ) {
			$css_class .= 'has_street ';
		}
		if ( ! rgar( $address_street2_field_input, 'isHidden' ) ) {
			$css_class .= 'has_street2 ';
		}
		if ( ! rgar( $address_city_field_input, 'isHidden' ) ) {
			$css_class .= 'has_city ';
		}
		if ( ! rgar( $address_state_field_input, 'isHidden' ) ) {
			$css_class .= 'has_state ';
		}
		if ( ! rgar( $address_zip_field_input, 'isHidden' ) ) {
			$css_class .= 'has_zip ';
		}
		if ( ! rgar( $address_country_field_input, 'isHidden' ) ) {
			$css_class .= 'has_country ';
		}

		$css_class .= 'ginput_container_address';

		return trim( $css_class );
	}

	public function get_address_types( $form_id ) {

		$addressTypes = array(
			'international' => array( 'label'       => esc_html__( 'International', 'gravityforms' ),
			                          'zip_label'   => gf_apply_filters( array( 'gform_address_zip', $form_id ), esc_html__( 'ZIP / Postal Code', 'gravityforms' ), $form_id ),
			                          'state_label' => gf_apply_filters( array( 'gform_address_state', $form_id ), esc_html__( 'State / Province / Region', 'gravityforms' ), $form_id )
			),
			'us'            => array(
				'label'       => esc_html__( 'United States', 'gravityforms' ),
				'zip_label'   => gf_apply_filters( array( 'gform_address_zip', $form_id ), esc_html__( 'ZIP Code', 'gravityforms' ), $form_id ),
				'state_label' => gf_apply_filters( array( 'gform_address_state', $form_id ), esc_html__( 'State', 'gravityforms' ), $form_id ),
				'country'     => 'United States',
				'states'      => array_merge( array( '' ), $this->get_us_states() )
			),
			'canadian'      => array(
				'label'       => esc_html__( 'Canadian', 'gravityforms' ),
				'zip_label'   => gf_apply_filters( array( 'gform_address_zip', $form_id ), esc_html__( 'Postal Code', 'gravityforms' ), $form_id ),
				'state_label' => gf_apply_filters( array( 'gform_address_state', $form_id ), esc_html__( 'Province', 'gravityforms' ), $form_id ),
				'country'     => 'Canada',
				'states'      => array_merge( array( '' ), $this->get_canadian_provinces() )
			)
		);

		/**
		 * Filters the address types available.
		 *
		 * @since Unknown
		 *
		 * @param array $addressTypes Contains the details for existing address types.
		 * @param int   $form_id      The form ID.
		 */
		return gf_apply_filters( array( 'gform_address_types', $form_id ), $addressTypes, $form_id );
	}

	/**
	 * Retrieve the default address type for this field.
	 *
	 * @param int $form_id The current form ID.
	 *
	 * @return string
	 */
	public function get_default_address_type( $form_id ) {
		$default_address_type = 'international';

		/**
		 * Allow the default address type to be overridden.
		 *
		 * @param string $default_address_type The default address type of international.
		 */
		$default_address_type = apply_filters( 'gform_default_address_type', $default_address_type, $form_id );

		return apply_filters( 'gform_default_address_type_' . $form_id, $default_address_type, $form_id );
	}

	public function get_state_field( $id, $field_id, $state_value, $disabled_text, $form_id ) {

		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$is_admin        = $is_entry_detail || $is_form_editor;

		$required_attribute     = $this->isRequired ? 'aria-required="true"' : '';

		$state_dropdown_class = $state_text_class = $state_style = $text_style = $state_field_id = '';

		if ( empty( $state_value ) ) {
			$state_value = $this->defaultState;

			// For backwards compatibility (Canadian address type used to store the default state into the defaultProvince property).
			if ( $this->addressType == 'canadian' && ! empty( $this->defaultProvince ) ) {
				$state_value = $this->defaultProvince;
			}
		}

		$address_type        = empty( $this->addressType ) ? $this->get_default_address_type( $form_id ) : $this->addressType;
		$address_types       = $this->get_address_types( $form_id );
		$has_state_drop_down = isset( $address_types[ $address_type ]['states'] ) && is_array( $address_types[ $address_type ]['states'] );

		if ( $is_admin && rgget('view') != 'entry' ) {
			$state_dropdown_class = "class='state_dropdown'";
			$state_text_class     = "class='state_text'";
			$state_style          = ! $has_state_drop_down ? "style='display:none;'" : '';
			$text_style           = $has_state_drop_down ? "style='display:none;'" : '';
			$state_field_id       = '';
		} else {
			// ID only displayed on front end.
			$state_field_id = "id='" . $field_id . "_4'";
		}

		$tabindex         = $this->get_tabindex();
		$state_input      = GFFormsModel::get_input( $this, $this->id . '.4' );
		$sate_placeholder = GFCommon::get_input_placeholder_value( $state_input );
		$states           = empty( $address_types[ $address_type ]['states'] ) ? array() : $address_types[ $address_type ]['states'];
		$state_dropdown   = sprintf( "<select name='input_%d.4' %s {$tabindex} %s {$state_dropdown_class} {$state_style} {$required_attribute}>%s</select>", $id, $state_field_id, $disabled_text, $this->get_state_dropdown( $states, $state_value, $sate_placeholder ) );

		$tabindex                    = $this->get_tabindex();
		$state_placeholder_attribute = GFCommon::get_input_placeholder_attribute( $state_input );
		$state_text                  = sprintf( "<input type='text' name='input_%d.4' %s value='%s' {$tabindex} %s {$state_text_class} {$text_style} {$state_placeholder_attribute} {$required_attribute}/>", $id, $state_field_id, $state_value, $disabled_text );

		if ( $is_admin && rgget('view') != 'entry' ) {
			return $state_dropdown . $state_text;
		} elseif ( $has_state_drop_down ) {
			return $state_dropdown;
		} else {
			return $state_text;
		}
	}

	/**
	 * Returns a list of countries.
	 *
	 * @since Unknown
	 * @since 2.4     Updated to use ISO 3166-1 list of countries.
	 *
	 * @return array
	 */
	public function get_countries() {

		/**
		 * A list of countries displayed in the Address field country drop down.
		 *
		 * @since Unknown
		 *
		 * @param array $countries ISO 3166-1 list of countries.
		 */
		return apply_filters(
			'gform_countries', array(
				__( 'Afghanistan', 'gravityforms' ),
				__( 'Åland Islands', 'gravityforms' ),
				__( 'Albania', 'gravityforms' ),
				__( 'Algeria', 'gravityforms' ),
				__( 'American Samoa', 'gravityforms' ),
				__( 'Andorra', 'gravityforms' ),
				__( 'Angola', 'gravityforms' ),
				__( 'Anguilla', 'gravityforms' ),
				__( 'Antarctica', 'gravityforms' ),
				__( 'Antigua and Barbuda', 'gravityforms' ),
				__( 'Argentina', 'gravityforms' ),
				__( 'Armenia', 'gravityforms' ),
				__( 'Aruba', 'gravityforms' ),
				__( 'Australia', 'gravityforms' ),
				__( 'Austria', 'gravityforms' ),
				__( 'Azerbaijan', 'gravityforms' ),
				__( 'Bahamas', 'gravityforms' ),
				__( 'Bahrain', 'gravityforms' ),
				__( 'Bangladesh', 'gravityforms' ),
				__( 'Barbados', 'gravityforms' ),
				__( 'Belarus', 'gravityforms' ),
				__( 'Belgium', 'gravityforms' ),
				__( 'Belize', 'gravityforms' ),
				__( 'Benin', 'gravityforms' ),
				__( 'Bermuda', 'gravityforms' ),
				__( 'Bhutan', 'gravityforms' ),
				__( 'Bolivia', 'gravityforms' ),
				__( 'Bonaire, Sint Eustatius and Saba', 'gravityforms' ),
				__( 'Bosnia and Herzegovina', 'gravityforms' ),
				__( 'Botswana', 'gravityforms' ),
				__( 'Bouvet Island', 'gravityforms' ),
				__( 'Brazil', 'gravityforms' ),
				__( 'British Indian Ocean Territory', 'gravityforms' ),
				__( 'Brunei Darussalam', 'gravityforms' ),
				__( 'Bulgaria', 'gravityforms' ),
				__( 'Burkina Faso', 'gravityforms' ),
				__( 'Burundi', 'gravityforms' ),
				__( 'Cambodia', 'gravityforms' ),
				__( 'Cameroon', 'gravityforms' ),
				__( 'Canada', 'gravityforms' ),
				__( 'Cape Verde', 'gravityforms' ),
				__( 'Cayman Islands', 'gravityforms' ),
				__( 'Central African Republic', 'gravityforms' ),
				__( 'Chad', 'gravityforms' ),
				__( 'Chile', 'gravityforms' ),
				__( 'China', 'gravityforms' ),
				__( 'Christmas Island', 'gravityforms' ),
				__( 'Cocos Islands', 'gravityforms' ),
				__( 'Colombia', 'gravityforms' ),
				__( 'Comoros', 'gravityforms' ),
				__( 'Congo, Democratic Republic of the', 'gravityforms' ),
				__( 'Congo, Republic of the', 'gravityforms' ),
				__( 'Cook Islands', 'gravityforms' ),
				__( 'Costa Rica', 'gravityforms' ),
				__( "Côte d'Ivoire", 'gravityforms' ),
				__( 'Croatia', 'gravityforms' ),
				__( 'Cuba', 'gravityforms' ),
				__( 'Curaçao', 'gravityforms' ),
				__( 'Cyprus', 'gravityforms' ),
				__( 'Czech Republic', 'gravityforms' ),
				__( 'Denmark', 'gravityforms' ),
				__( 'Djibouti', 'gravityforms' ),
				__( 'Dominica', 'gravityforms' ),
				__( 'Dominican Republic', 'gravityforms' ),
				__( 'Ecuador', 'gravityforms' ),
				__( 'Egypt', 'gravityforms' ),
				__( 'El Salvador', 'gravityforms' ),
				__( 'Equatorial Guinea', 'gravityforms' ),
				__( 'Eritrea', 'gravityforms' ),
				__( 'Estonia', 'gravityforms' ),
				__( 'Eswatini (Swaziland)', 'gravityforms' ),
				__( 'Ethiopia', 'gravityforms' ),
				__( 'Falkland Islands', 'gravityforms' ),
				__( 'Faroe Islands', 'gravityforms' ),
				__( 'Fiji', 'gravityforms' ),
				__( 'Finland', 'gravityforms' ),
				__( 'France', 'gravityforms' ),
				__( 'French Guiana', 'gravityforms' ),
				__( 'French Polynesia', 'gravityforms' ),
				__( 'French Southern Territories', 'gravityforms' ),
				__( 'Gabon', 'gravityforms' ),
				__( 'Gambia', 'gravityforms' ),
				_x( 'Georgia', 'Country', 'gravityforms' ),
				__( 'Germany', 'gravityforms' ),
				__( 'Ghana', 'gravityforms' ),
				__( 'Gibraltar', 'gravityforms' ),
				__( 'Greece', 'gravityforms' ),
				__( 'Greenland', 'gravityforms' ),
				__( 'Grenada', 'gravityforms' ),
				__( 'Guadeloupe', 'gravityforms' ),
				__( 'Guam', 'gravityforms' ),
				__( 'Guatemala', 'gravityforms' ),
				__( 'Guernsey', 'gravityforms' ),
				__( 'Guinea', 'gravityforms' ),
				__( 'Guinea-Bissau', 'gravityforms' ),
				__( 'Guyana', 'gravityforms' ),
				__( 'Haiti', 'gravityforms' ),
				__( 'Heard and McDonald Islands', 'gravityforms' ),
				__( 'Holy See', 'gravityforms' ),
				__( 'Honduras', 'gravityforms' ),
				__( 'Hong Kong', 'gravityforms' ),
				__( 'Hungary', 'gravityforms' ),
				__( 'Iceland', 'gravityforms' ),
				__( 'India', 'gravityforms' ),
				__( 'Indonesia', 'gravityforms' ),
				__( 'Iran', 'gravityforms' ),
				__( 'Iraq', 'gravityforms' ),
				__( 'Ireland', 'gravityforms' ),
				__( 'Isle of Man', 'gravityforms' ),
				__( 'Israel', 'gravityforms' ),
				__( 'Italy', 'gravityforms' ),
				__( 'Jamaica', 'gravityforms' ),
				__( 'Japan', 'gravityforms' ),
				__( 'Jersey', 'gravityforms' ),
				__( 'Jordan', 'gravityforms' ),
				__( 'Kazakhstan', 'gravityforms' ),
				__( 'Kenya', 'gravityforms' ),
				__( 'Kiribati', 'gravityforms' ),
				__( 'Kuwait', 'gravityforms' ),
				__( 'Kyrgyzstan', 'gravityforms' ),
				__( "Lao People's Democratic Republic", 'gravityforms' ),
				__( 'Latvia', 'gravityforms' ),
				__( 'Lebanon', 'gravityforms' ),
				__( 'Lesotho', 'gravityforms' ),
				__( 'Liberia', 'gravityforms' ),
				__( 'Libya', 'gravityforms' ),
				__( 'Liechtenstein', 'gravityforms' ),
				__( 'Lithuania', 'gravityforms' ),
				__( 'Luxembourg', 'gravityforms' ),
				__( 'Macau', 'gravityforms' ),
				__( 'Macedonia', 'gravityforms' ),
				__( 'Madagascar', 'gravityforms' ),
				__( 'Malawi', 'gravityforms' ),
				__( 'Malaysia', 'gravityforms' ),
				__( 'Maldives', 'gravityforms' ),
				__( 'Mali', 'gravityforms' ),
				__( 'Malta', 'gravityforms' ),
				__( 'Marshall Islands', 'gravityforms' ),
				__( 'Martinique', 'gravityforms' ),
				__( 'Mauritania', 'gravityforms' ),
				__( 'Mauritius', 'gravityforms' ),
				__( 'Mayotte', 'gravityforms' ),
				__( 'Mexico', 'gravityforms' ),
				__( 'Micronesia', 'gravityforms' ),
				__( 'Moldova', 'gravityforms' ),
				__( 'Monaco', 'gravityforms' ),
				__( 'Mongolia', 'gravityforms' ),
				__( 'Montenegro', 'gravityforms' ),
				__( 'Montserrat', 'gravityforms' ),
				__( 'Morocco', 'gravityforms' ),
				__( 'Mozambique', 'gravityforms' ),
				__( 'Myanmar', 'gravityforms' ),
				__( 'Namibia', 'gravityforms' ),
				__( 'Nauru', 'gravityforms' ),
				__( 'Nepal', 'gravityforms' ),
				__( 'Netherlands', 'gravityforms' ),
				__( 'New Caledonia', 'gravityforms' ),
				__( 'New Zealand', 'gravityforms' ),
				__( 'Nicaragua', 'gravityforms' ),
				__( 'Niger', 'gravityforms' ),
				__( 'Nigeria', 'gravityforms' ),
				__( 'Niue', 'gravityforms' ),
				__( 'Norfolk Island', 'gravityforms' ),
				__( 'North Korea', 'gravityforms' ),
				__( 'Northern Mariana Islands', 'gravityforms' ),
				__( 'Norway', 'gravityforms' ),
				__( 'Oman', 'gravityforms' ),
				__( 'Pakistan', 'gravityforms' ),
				__( 'Palau', 'gravityforms' ),
				__( 'Palestine, State of', 'gravityforms' ),
				__( 'Panama', 'gravityforms' ),
				__( 'Papua New Guinea', 'gravityforms' ),
				__( 'Paraguay', 'gravityforms' ),
				__( 'Peru', 'gravityforms' ),
				__( 'Philippines', 'gravityforms' ),
				__( 'Pitcairn', 'gravityforms' ),
				__( 'Poland', 'gravityforms' ),
				__( 'Portugal', 'gravityforms' ),
				__( 'Puerto Rico', 'gravityforms' ),
				__( 'Qatar', 'gravityforms' ),
				__( 'Réunion', 'gravityforms' ),
				__( 'Romania', 'gravityforms' ),
				__( 'Russia', 'gravityforms' ),
				__( 'Rwanda', 'gravityforms' ),
				__( 'Saint Barthélemy', 'gravityforms' ),
				__( 'Saint Helena', 'gravityforms' ),
				__( 'Saint Kitts and Nevis', 'gravityforms' ),
				__( 'Saint Lucia', 'gravityforms' ),
				__( 'Saint Martin', 'gravityforms' ),
				__( 'Saint Pierre and Miquelon', 'gravityforms' ),
				__( 'Saint Vincent and the Grenadines', 'gravityforms' ),
				__( 'Samoa', 'gravityforms' ),
				__( 'San Marino', 'gravityforms' ),
				__( 'Sao Tome and Principe', 'gravityforms' ),
				__( 'Saudi Arabia', 'gravityforms' ),
				__( 'Senegal', 'gravityforms' ),
				__( 'Serbia', 'gravityforms' ),
				__( 'Seychelles', 'gravityforms' ),
				__( 'Sierra Leone', 'gravityforms' ),
				__( 'Singapore', 'gravityforms' ),
				__( 'Sint Maarten', 'gravityforms' ),
				__( 'Slovakia', 'gravityforms' ),
				__( 'Slovenia', 'gravityforms' ),
				__( 'Solomon Islands', 'gravityforms' ),
				__( 'Somalia', 'gravityforms' ),
				__( 'South Africa', 'gravityforms' ),
				_x( 'South Georgia', 'Country', 'gravityforms' ),
				__( 'South Korea', 'gravityforms' ),
				__( 'South Sudan', 'gravityforms' ),
				__( 'Spain', 'gravityforms' ),
				__( 'Sri Lanka', 'gravityforms' ),
				__( 'Sudan', 'gravityforms' ),
				__( 'Suriname', 'gravityforms' ),
				__( 'Svalbard and Jan Mayen Islands', 'gravityforms' ),
				__( 'Sweden', 'gravityforms' ),
				__( 'Switzerland', 'gravityforms' ),
				__( 'Syria', 'gravityforms' ),
				__( 'Taiwan', 'gravityforms' ),
				__( 'Tajikistan', 'gravityforms' ),
				__( 'Tanzania', 'gravityforms' ),
				__( 'Thailand', 'gravityforms' ),
				__( 'Timor-Leste', 'gravityforms' ),
				__( 'Togo', 'gravityforms' ),
				__( 'Tokelau', 'gravityforms' ),
				__( 'Tonga', 'gravityforms' ),
				__( 'Trinidad and Tobago', 'gravityforms' ),
				__( 'Tunisia', 'gravityforms' ),
				__( 'Turkey', 'gravityforms' ),
				__( 'Turkmenistan', 'gravityforms' ),
				__( 'Turks and Caicos Islands', 'gravityforms' ),
				__( 'Tuvalu', 'gravityforms' ),
				__( 'Uganda', 'gravityforms' ),
				__( 'Ukraine', 'gravityforms' ),
				__( 'United Arab Emirates', 'gravityforms' ),
				__( 'United Kingdom', 'gravityforms' ),
				__( 'United States', 'gravityforms' ),
				__( 'Uruguay', 'gravityforms' ),
				__( 'US Minor Outlying Islands', 'gravityforms' ),
				__( 'Uzbekistan', 'gravityforms' ),
				__( 'Vanuatu', 'gravityforms' ),
				__( 'Venezuela', 'gravityforms' ),
				__( 'Vietnam', 'gravityforms' ),
				__( 'Virgin Islands, British', 'gravityforms' ),
				__( 'Virgin Islands, U.S.', 'gravityforms' ),
				__( 'Wallis and Futuna', 'gravityforms' ),
				__( 'Western Sahara', 'gravityforms' ),
				__( 'Yemen', 'gravityforms' ),
				__( 'Zambia', 'gravityforms' ),
				__( 'Zimbabwe', 'gravityforms' ),
			)
		);

	}

	public function get_country_code( $country_name ) {
		$codes = $this->get_country_codes();

		return rgar( $codes, GFCommon::safe_strtoupper( $country_name ) );
	}

	/**
	 * Returns a list of countries and their country codes.
	 *
	 * @since Unknown
	 * @since 2.4     Updated to use ISO 3166-1 list of countries.
	 *
	 * @return array
	 */
	public function get_country_codes() {

		$codes = array(
			__( 'AFGHANISTAN', 'gravityforms' )                       => 'AF',
			__( 'ÅLAND ISLANDS', 'gravityforms' )                     => 'AX',
			__( 'ALBANIA', 'gravityforms' )                           => 'AL',
			__( 'ALGERIA', 'gravityforms' )                           => 'DZ',
			__( 'AMERICAN SAMOA', 'gravityforms' )                    => 'AS',
			__( 'ANDORRA', 'gravityforms' )                           => 'AD',
			__( 'ANGOLA', 'gravityforms' )                            => 'AO',
			__( 'ANGUILLA', 'gravityforms' )                          => 'AI',
			__( 'ANTARCTICA', 'gravityforms' )                        => 'AQ',
			__( 'ANTIGUA AND BARBUDA', 'gravityforms' )               => 'AG',
			__( 'ARGENTINA', 'gravityforms' )                         => 'AR',
			__( 'ARMENIA', 'gravityforms' )                           => 'AM',
			__( 'ARUBA', 'gravityforms' )                             => 'AW',
			__( 'AUSTRALIA', 'gravityforms' )                         => 'AU',
			__( 'AUSTRIA', 'gravityforms' )                           => 'AT',
			__( 'AZERBAIJAN', 'gravityforms' )                        => 'AZ',
			__( 'BAHAMAS', 'gravityforms' )                           => 'BS',
			__( 'BAHRAIN', 'gravityforms' )                           => 'BH',
			__( 'BANGLADESH', 'gravityforms' )                        => 'BD',
			__( 'BARBADOS', 'gravityforms' )                          => 'BB',
			__( 'BELARUS', 'gravityforms' )                           => 'BY',
			__( 'BELGIUM', 'gravityforms' )                           => 'BE',
			__( 'BELIZE', 'gravityforms' )                            => 'BZ',
			__( 'BENIN', 'gravityforms' )                             => 'BJ',
			__( 'BERMUDA', 'gravityforms' )                           => 'BM',
			__( 'BHUTAN', 'gravityforms' )                            => 'BT',
			__( 'BOLIVIA', 'gravityforms' )                           => 'BO',
			__( 'BONAIRE, SINT EUSTATIUS AND SABA', 'gravityforms' )  => 'BQ',
			__( 'BOSNIA AND HERZEGOVINA', 'gravityforms' )            => 'BA',
			__( 'BOTSWANA', 'gravityforms' )                          => 'BW',
			__( 'BOUVET ISLAND', 'gravityforms' )                     => 'BV',
			__( 'BRAZIL', 'gravityforms' )                            => 'BR',
			__( 'BRITISH INDIAN OCEAN TERRITORY', 'gravityforms' )    => 'IO',
			__( 'BRUNEI DARUSSALAM', 'gravityforms' )                 => 'BN',
			__( 'BULGARIA', 'gravityforms' )                          => 'BG',
			__( 'BURKINA FASO', 'gravityforms' )                      => 'BF',
			__( 'BURUNDI', 'gravityforms' )                           => 'BI',
			__( 'CAMBODIA', 'gravityforms' )                          => 'KH',
			__( 'CAMEROON', 'gravityforms' )                          => 'CM',
			__( 'CANADA', 'gravityforms' )                            => 'CA',
			__( 'CAPE VERDE', 'gravityforms' )                        => 'CV',
			__( 'CAYMAN ISLANDS', 'gravityforms' )                    => 'KY',
			__( 'CENTRAL AFRICAN REPUBLIC', 'gravityforms' )          => 'CF',
			__( 'CHAD', 'gravityforms' )                              => 'TD',
			__( 'CHILE', 'gravityforms' )                             => 'CL',
			__( 'CHINA', 'gravityforms' )                             => 'CN',
			__( 'CHRISTMAS ISLAND', 'gravityforms' )                  => 'CX',
			__( 'COCOS ISLANDS', 'gravityforms' )                     => 'CC',
			__( 'COLOMBIA', 'gravityforms' )                          => 'CO',
			__( 'COMOROS', 'gravityforms' )                           => 'KM',
			__( 'CONGO, DEMOCRATIC REPUBLIC OF THE', 'gravityforms' ) => 'CD',
			__( 'CONGO, REPUBLIC OF THE', 'gravityforms' )            => 'CG',
			__( 'COOK ISLANDS', 'gravityforms' )                      => 'CK',
			__( 'COSTA RICA', 'gravityforms' )                        => 'CR',
			__( "CÔTE D'IVOIRE", 'gravityforms' )                     => 'CI',
			__( 'CROATIA', 'gravityforms' )                           => 'HR',
			__( 'CUBA', 'gravityforms' )                              => 'CU',
			__( 'CURAÇAO', 'gravityforms' )                           => 'CW',
			__( 'CYPRUS', 'gravityforms' )                            => 'CY',
			__( 'CZECH REPUBLIC', 'gravityforms' )                    => 'CZ',
			__( 'DENMARK', 'gravityforms' )                           => 'DK',
			__( 'DJIBOUTI', 'gravityforms' )                          => 'DJ',
			__( 'DOMINICA', 'gravityforms' )                          => 'DM',
			__( 'DOMINICAN REPUBLIC', 'gravityforms' )                => 'DO',
			__( 'ECUADOR', 'gravityforms' )                           => 'EC',
			__( 'EGYPT', 'gravityforms' )                             => 'EG',
			__( 'EL SALVADOR', 'gravityforms' )                       => 'SV',
			__( 'EQUATORIAL GUINEA', 'gravityforms' )                 => 'GQ',
			__( 'ERITREA', 'gravityforms' )                           => 'ER',
			__( 'ESTONIA', 'gravityforms' )                           => 'EE',
			__( 'ESWATINI (SWAZILAND)', 'gravityforms' )              => 'SZ',
			__( 'ETHIOPIA', 'gravityforms' )                          => 'ET',
			__( 'FALKLAND ISLANDS', 'gravityforms' )                  => 'FK',
			__( 'FAROE ISLANDS', 'gravityforms' )                     => 'FO',
			__( 'FIJI', 'gravityforms' )                              => 'FJ',
			__( 'FINLAND', 'gravityforms' )                           => 'FI',
			__( 'FRANCE', 'gravityforms' )                            => 'FR',
			__( 'FRENCH GUIANA', 'gravityforms' )                     => 'GF',
			__( 'FRENCH POLYNESIA', 'gravityforms' )                  => 'PF',
			__( 'FRENCH SOUTHERN TERRITORIES', 'gravityforms' )       => 'TF',
			__( 'GABON', 'gravityforms' )                             => 'GA',
			__( 'GAMBIA', 'gravityforms' )                            => 'GM',
			_x( 'GEORGIA', 'Country', 'gravityforms' )                => 'GE',
			__( 'GERMANY', 'gravityforms' )                           => 'DE',
			__( 'GHANA', 'gravityforms' )                             => 'GH',
			__( 'GIBRALTAR', 'gravityforms' )                         => 'GI',
			__( 'GREECE', 'gravityforms' )                            => 'GR',
			__( 'GREENLAND', 'gravityforms' )                         => 'GL',
			__( 'GRENADA', 'gravityforms' )                           => 'GD',
			__( 'GUADELOUPE', 'gravityforms' )                        => 'GP',
			__( 'GUAM', 'gravityforms' )                              => 'GU',
			__( 'GUATEMALA', 'gravityforms' )                         => 'GT',
			__( 'GUERNSEY', 'gravityforms' )                          => 'GG',
			__( 'GUINEA', 'gravityforms' )                            => 'GN',
			__( 'GUINEA-BISSAU', 'gravityforms' )                     => 'GW',
			__( 'GUYANA', 'gravityforms' )                            => 'GY',
			__( 'HAITI', 'gravityforms' )                             => 'HT',
			__( 'HEARD AND MCDONALD ISLANDS', 'gravityforms' )        => 'HM',
			__( 'HOLY SEE', 'gravityforms' )                          => 'VA',
			__( 'HONDURAS', 'gravityforms' )                          => 'HN',
			__( 'HONG KONG', 'gravityforms' )                         => 'HK',
			__( 'HUNGARY', 'gravityforms' )                           => 'HU',
			__( 'ICELAND', 'gravityforms' )                           => 'IS',
			__( 'INDIA', 'gravityforms' )                             => 'IN',
			__( 'INDONESIA', 'gravityforms' )                         => 'ID',
			__( 'IRAN', 'gravityforms' )                              => 'IR',
			__( 'IRAQ', 'gravityforms' )                              => 'IQ',
			__( 'IRELAND', 'gravityforms' )                           => 'IE',
			__( 'ISLE OF MAN', 'gravityforms' )                       => 'IM',
			__( 'ISRAEL', 'gravityforms' )                            => 'IL',
			__( 'ITALY', 'gravityforms' )                             => 'IT',
			__( 'JAMAICA', 'gravityforms' )                           => 'JM',
			__( 'JAPAN', 'gravityforms' )                             => 'JP',
			__( 'JERSEY', 'gravityforms' )                            => 'JE',
			__( 'JORDAN', 'gravityforms' )                            => 'JO',
			__( 'KAZAKHSTAN', 'gravityforms' )                        => 'KZ',
			__( 'KENYA', 'gravityforms' )                             => 'KE',
			__( 'KIRIBATI', 'gravityforms' )                          => 'KI',
			__( 'KUWAIT', 'gravityforms' )                            => 'KW',
			__( 'KYRGYZSTAN', 'gravityforms' )                        => 'KG',
			__( "LAO PEOPLE'S DEMOCRATIC REPUBLIC", 'gravityforms' )  => 'LA',
			__( 'LATVIA', 'gravityforms' )                            => 'LV',
			__( 'LEBANON', 'gravityforms' )                           => 'LB',
			__( 'LESOTHO', 'gravityforms' )                           => 'LS',
			__( 'LIBERIA', 'gravityforms' )                           => 'LR',
			__( 'LIBYA', 'gravityforms' )                             => 'LY',
			__( 'LIECHTENSTEIN', 'gravityforms' )                     => 'LI',
			__( 'LITHUANIA', 'gravityforms' )                         => 'LT',
			__( 'LUXEMBOURG', 'gravityforms' )                        => 'LU',
			__( 'MACEDONIA', 'gravityforms' )                         => 'MK',
			__( 'MACAU', 'gravityforms' )                             => 'MO',
			__( 'MADAGASCAR', 'gravityforms' )                        => 'MG',
			__( 'MALAWI', 'gravityforms' )                            => 'MW',
			__( 'MALAYSIA', 'gravityforms' )                          => 'MY',
			__( 'MALDIVES', 'gravityforms' )                          => 'MV',
			__( 'MALI', 'gravityforms' )                              => 'ML',
			__( 'MALTA', 'gravityforms' )                             => 'MT',
			__( 'MARSHALL ISLANDS', 'gravityforms' )                  => 'MH',
			__( 'MARTINIQUE', 'gravityforms' )                        => 'MQ',
			__( 'MAURITANIA', 'gravityforms' )                        => 'MR',
			__( 'MAURITIUS', 'gravityforms' )                         => 'MU',
			__( 'MAYOTTE', 'gravityforms' )                           => 'YT',
			__( 'MEXICO', 'gravityforms' )                            => 'MX',
			__( 'MICRONESIA', 'gravityforms' )                        => 'FM',
			__( 'MOLDOVA', 'gravityforms' )                           => 'MD',
			__( 'MONACO', 'gravityforms' )                            => 'MC',
			__( 'MONGOLIA', 'gravityforms' )                          => 'MN',
			__( 'MONTENEGRO', 'gravityforms' )                        => 'ME',
			__( 'MONTSERRAT', 'gravityforms' )                        => 'MS',
			__( 'MOROCCO', 'gravityforms' )                           => 'MA',
			__( 'MOZAMBIQUE', 'gravityforms' )                        => 'MZ',
			__( 'MYANMAR', 'gravityforms' )                           => 'MM',
			__( 'NAMIBIA', 'gravityforms' )                           => 'NA',
			__( 'NAURU', 'gravityforms' )                             => 'NR',
			__( 'NEPAL', 'gravityforms' )                             => 'NP',
			__( 'NETHERLANDS', 'gravityforms' )                       => 'NL',
			__( 'NEW CALEDONIA', 'gravityforms' )                     => 'NC',
			__( 'NEW ZEALAND', 'gravityforms' )                       => 'NZ',
			__( 'NICARAGUA', 'gravityforms' )                         => 'NI',
			__( 'NIGER', 'gravityforms' )                             => 'NE',
			__( 'NIGERIA', 'gravityforms' )                           => 'NG',
			__( 'NIUE', 'gravityforms' )                              => 'NU',
			__( 'NORFOLK ISLAND', 'gravityforms' )                    => 'NF',
			__( 'NORTH KOREA', 'gravityforms' )                       => 'KP',
			__( 'NORTHERN MARIANA ISLANDS', 'gravityforms' )          => 'MP',
			__( 'NORWAY', 'gravityforms' )                            => 'NO',
			__( 'OMAN', 'gravityforms' )                              => 'OM',
			__( 'PAKISTAN', 'gravityforms' )                          => 'PK',
			__( 'PALAU', 'gravityforms' )                             => 'PW',
			__( 'PALESTINE, STATE OF', 'gravityforms' )               => 'PS',
			__( 'PANAMA', 'gravityforms' )                            => 'PA',
			__( 'PAPUA NEW GUINEA', 'gravityforms' )                  => 'PG',
			__( 'PARAGUAY', 'gravityforms' )                          => 'PY',
			__( 'PERU', 'gravityforms' )                              => 'PE',
			__( 'PHILIPPINES', 'gravityforms' )                       => 'PH',
			__( 'PITCAIRN', 'gravityforms' )                          => 'PN',
			__( 'POLAND', 'gravityforms' )                            => 'PL',
			__( 'PORTUGAL', 'gravityforms' )                          => 'PT',
			__( 'PUERTO RICO', 'gravityforms' )                       => 'PR',
			__( 'QATAR', 'gravityforms' )                             => 'QA',
			__( 'RÉUNION', 'gravityforms' )                           => 'RE',
			__( 'ROMANIA', 'gravityforms' )                           => 'RO',
			__( 'RUSSIA', 'gravityforms' )                            => 'RU',
			__( 'RWANDA', 'gravityforms' )                            => 'RW',
			__( 'SAINT BARTHÉLEMY', 'gravityforms' )                  => 'BL',
			__( 'SAINT HELENA', 'gravityforms' )                      => 'SH',
			__( 'SAINT KITTS AND NEVIS', 'gravityforms' )             => 'KN',
			__( 'SAINT LUCIA', 'gravityforms' )                       => 'LC',
			__( 'SAINT MARTIN', 'gravityforms' )                      => 'MF',
			__( 'SAINT PIERRE AND MIQUELON', 'gravityforms' )         => 'PM',
			__( 'SAINT VINCENT AND THE GRENADINES', 'gravityforms' )  => 'VC',
			__( 'SAMOA', 'gravityforms' )                             => 'WS',
			__( 'SAN MARINO', 'gravityforms' )                        => 'SM',
			__( 'SAO TOME AND PRINCIPE', 'gravityforms' )             => 'ST',
			__( 'SAUDI ARABIA', 'gravityforms' )                      => 'SA',
			__( 'SENEGAL', 'gravityforms' )                           => 'SN',
			__( 'SERBIA', 'gravityforms' )                            => 'RS',
			__( 'SEYCHELLES', 'gravityforms' )                        => 'SC',
			__( 'SIERRA LEONE', 'gravityforms' )                      => 'SL',
			__( 'SINGAPORE', 'gravityforms' )                         => 'SG',
			__( 'SINT MAARTEN', 'gravityforms' )                      => 'SX',
			__( 'SLOVAKIA', 'gravityforms' )                          => 'SK',
			__( 'SLOVENIA', 'gravityforms' )                          => 'SI',
			__( 'SOLOMON ISLANDS', 'gravityforms' )                   => 'SB',
			__( 'SOMALIA', 'gravityforms' )                           => 'SO',
			__( 'SOUTH AFRICA', 'gravityforms' )                      => 'ZA',
			_x( 'SOUTH GEORGIA', 'Country', 'gravityforms' )          => 'GS',
			__( 'SOUTH KOREA', 'gravityforms' )                       => 'KR',
			__( 'SOUTH SUDAN', 'gravityforms' )                       => 'SS',
			__( 'SPAIN', 'gravityforms' )                             => 'ES',
			__( 'SRI LANKA', 'gravityforms' )                         => 'LK',
			__( 'SUDAN', 'gravityforms' )                             => 'SD',
			__( 'SUDAN, SOUTH', 'gravityforms' )                      => 'SS',
			__( 'SURINAME', 'gravityforms' )                          => 'SR',
			__( 'SVALBARD AND JAN MAYEN ISLANDS', 'gravityforms' )    => 'SJ',
			__( 'SWEDEN', 'gravityforms' )                            => 'SE',
			__( 'SWITZERLAND', 'gravityforms' )                       => 'CH',
			__( 'SYRIA', 'gravityforms' )                             => 'SY',
			__( 'TAIWAN', 'gravityforms' )                            => 'TW',
			__( 'TAJIKISTAN', 'gravityforms' )                        => 'TJ',
			__( 'TANZANIA', 'gravityforms' )                          => 'TZ',
			__( 'THAILAND', 'gravityforms' )                          => 'TH',
			__( 'TIMOR-LESTE', 'gravityforms' )                       => 'TL',
			__( 'TOGO', 'gravityforms' )                              => 'TG',
			__( 'TOKELAU', 'gravityforms' )                           => 'TK',
			__( 'TONGA', 'gravityforms' )                             => 'TO',
			__( 'TRINIDAD AND TOBAGO', 'gravityforms' )               => 'TT',
			__( 'TUNISIA', 'gravityforms' )                           => 'TN',
			__( 'TURKEY', 'gravityforms' )                            => 'TR',
			__( 'TURKMENISTAN', 'gravityforms' )                      => 'TM',
			__( 'TURKS AND CAICOS ISLANDS', 'gravityforms' )          => 'TC',
			__( 'TUVALU', 'gravityforms' )                            => 'TV',
			__( 'UGANDA', 'gravityforms' )                            => 'UG',
			__( 'UKRAINE', 'gravityforms' )                           => 'UA',
			__( 'UNITED ARAB EMIRATES', 'gravityforms' )              => 'AE',
			__( 'UNITED KINGDOM', 'gravityforms' )                    => 'GB',
			__( 'UNITED STATES', 'gravityforms' )                     => 'US',
			__( 'URUGUAY', 'gravityforms' )                           => 'UY',
			__( 'US MINOR OUTLYING ISLANDS', 'gravityforms' )         => 'UM',
			__( 'UZBEKISTAN', 'gravityforms' )                        => 'UZ',
			__( 'VANUATU', 'gravityforms' )                           => 'VU',
			__( 'VATICAN CITY', 'gravityforms' )                      => 'VA',
			__( 'VENEZUELA', 'gravityforms' )                         => 'VE',
			__( 'VIRGIN ISLANDS, BRITISH', 'gravityforms' )           => 'VG',
			__( 'VIRGIN ISLANDS, U.S.', 'gravityforms' )              => 'VI',
			__( 'VIETNAM', 'gravityforms' )                           => 'VN',
			__( 'WALLIS AND FUTUNA', 'gravityforms' )                 => 'WF',
			__( 'WESTERN SAHARA', 'gravityforms' )                    => 'EH',
			__( 'YEMEN', 'gravityforms' )                             => 'YE',
			__( 'ZAMBIA', 'gravityforms' )                            => 'ZM',
			__( 'ZIMBABWE', 'gravityforms' )                          => 'ZW',
		);

		return $codes;
	}

	public function get_us_states() {
		return apply_filters(
			'gform_us_states', array(
				__( 'Alabama', 'gravityforms' ),
				__( 'Alaska', 'gravityforms' ),
				__( 'Arizona', 'gravityforms' ),
				__( 'Arkansas', 'gravityforms' ),
				__( 'California', 'gravityforms' ),
				__( 'Colorado', 'gravityforms' ),
				__( 'Connecticut', 'gravityforms' ),
				__( 'Delaware', 'gravityforms' ),
				__( 'District of Columbia', 'gravityforms' ),
				__( 'Florida', 'gravityforms' ),
				_x( 'Georgia', 'US State', 'gravityforms' ),
				__( 'Hawaii', 'gravityforms' ),
				__( 'Idaho', 'gravityforms' ),
				__( 'Illinois', 'gravityforms' ),
				__( 'Indiana', 'gravityforms' ),
				__( 'Iowa', 'gravityforms' ),
				__( 'Kansas', 'gravityforms' ),
				__( 'Kentucky', 'gravityforms' ),
				__( 'Louisiana', 'gravityforms' ),
				__( 'Maine', 'gravityforms' ),
				__( 'Maryland', 'gravityforms' ),
				__( 'Massachusetts', 'gravityforms' ),
				__( 'Michigan', 'gravityforms' ),
				__( 'Minnesota', 'gravityforms' ),
				__( 'Mississippi', 'gravityforms' ),
				__( 'Missouri', 'gravityforms' ),
				__( 'Montana', 'gravityforms' ),
				__( 'Nebraska', 'gravityforms' ),
				__( 'Nevada', 'gravityforms' ),
				__( 'New Hampshire', 'gravityforms' ),
				__( 'New Jersey', 'gravityforms' ),
				__( 'New Mexico', 'gravityforms' ),
				__( 'New York', 'gravityforms' ),
				__( 'North Carolina', 'gravityforms' ),
				__( 'North Dakota', 'gravityforms' ),
				__( 'Ohio', 'gravityforms' ),
				__( 'Oklahoma', 'gravityforms' ),
				__( 'Oregon', 'gravityforms' ),
				__( 'Pennsylvania', 'gravityforms' ),
				__( 'Rhode Island', 'gravityforms' ),
				__( 'South Carolina', 'gravityforms' ),
				__( 'South Dakota', 'gravityforms' ),
				__( 'Tennessee', 'gravityforms' ),
				__( 'Texas', 'gravityforms' ),
				__( 'Utah', 'gravityforms' ),
				__( 'Vermont', 'gravityforms' ),
				__( 'Virginia', 'gravityforms' ),
				__( 'Washington', 'gravityforms' ),
				__( 'West Virginia', 'gravityforms' ),
				__( 'Wisconsin', 'gravityforms' ),
				__( 'Wyoming', 'gravityforms' ),
				__( 'Armed Forces Americas', 'gravityforms' ),
				__( 'Armed Forces Europe', 'gravityforms' ),
				__( 'Armed Forces Pacific', 'gravityforms' ),
			)
		);
	}

	public function get_us_state_code( $state_name ) {
		$states = array(
			GFCommon::safe_strtoupper( __( 'Alabama', 'gravityforms' ) )               => 'AL',
			GFCommon::safe_strtoupper( __( 'Alaska', 'gravityforms' ) )                => 'AK',
			GFCommon::safe_strtoupper( __( 'Arizona', 'gravityforms' ) )               => 'AZ',
			GFCommon::safe_strtoupper( __( 'Arkansas', 'gravityforms' ) )              => 'AR',
			GFCommon::safe_strtoupper( __( 'California', 'gravityforms' ) )            => 'CA',
			GFCommon::safe_strtoupper( __( 'Colorado', 'gravityforms' ) )              => 'CO',
			GFCommon::safe_strtoupper( __( 'Connecticut', 'gravityforms' ) )           => 'CT',
			GFCommon::safe_strtoupper( __( 'Delaware', 'gravityforms' ) )              => 'DE',
			GFCommon::safe_strtoupper( __( 'District of Columbia', 'gravityforms' ) )  => 'DC',
			GFCommon::safe_strtoupper( __( 'Florida', 'gravityforms' ) )               => 'FL',
			GFCommon::safe_strtoupper( _x( 'Georgia', 'US State', 'gravityforms' ) )   => 'GA',
			GFCommon::safe_strtoupper( __( 'Hawaii', 'gravityforms' ) )                => 'HI',
			GFCommon::safe_strtoupper( __( 'Idaho', 'gravityforms' ) )                 => 'ID',
			GFCommon::safe_strtoupper( __( 'Illinois', 'gravityforms' ) )              => 'IL',
			GFCommon::safe_strtoupper( __( 'Indiana', 'gravityforms' ) )               => 'IN',
			GFCommon::safe_strtoupper( __( 'Iowa', 'gravityforms' ) )                  => 'IA',
			GFCommon::safe_strtoupper( __( 'Kansas', 'gravityforms' ) )                => 'KS',
			GFCommon::safe_strtoupper( __( 'Kentucky', 'gravityforms' ) )              => 'KY',
			GFCommon::safe_strtoupper( __( 'Louisiana', 'gravityforms' ) )             => 'LA',
			GFCommon::safe_strtoupper( __( 'Maine', 'gravityforms' ) )                 => 'ME',
			GFCommon::safe_strtoupper( __( 'Maryland', 'gravityforms' ) )              => 'MD',
			GFCommon::safe_strtoupper( __( 'Massachusetts', 'gravityforms' ) )         => 'MA',
			GFCommon::safe_strtoupper( __( 'Michigan', 'gravityforms' ) )              => 'MI',
			GFCommon::safe_strtoupper( __( 'Minnesota', 'gravityforms' ) )             => 'MN',
			GFCommon::safe_strtoupper( __( 'Mississippi', 'gravityforms' ) )           => 'MS',
			GFCommon::safe_strtoupper( __( 'Missouri', 'gravityforms' ) )              => 'MO',
			GFCommon::safe_strtoupper( __( 'Montana', 'gravityforms' ) )               => 'MT',
			GFCommon::safe_strtoupper( __( 'Nebraska', 'gravityforms' ) )              => 'NE',
			GFCommon::safe_strtoupper( __( 'Nevada', 'gravityforms' ) )                => 'NV',
			GFCommon::safe_strtoupper( __( 'New Hampshire', 'gravityforms' ) )         => 'NH',
			GFCommon::safe_strtoupper( __( 'New Jersey', 'gravityforms' ) )            => 'NJ',
			GFCommon::safe_strtoupper( __( 'New Mexico', 'gravityforms' ) )            => 'NM',
			GFCommon::safe_strtoupper( __( 'New York', 'gravityforms' ) )              => 'NY',
			GFCommon::safe_strtoupper( __( 'North Carolina', 'gravityforms' ) )        => 'NC',
			GFCommon::safe_strtoupper( __( 'North Dakota', 'gravityforms' ) )          => 'ND',
			GFCommon::safe_strtoupper( __( 'Ohio', 'gravityforms' ) )                  => 'OH',
			GFCommon::safe_strtoupper( __( 'Oklahoma', 'gravityforms' ) )              => 'OK',
			GFCommon::safe_strtoupper( __( 'Oregon', 'gravityforms' ) )                => 'OR',
			GFCommon::safe_strtoupper( __( 'Pennsylvania', 'gravityforms' ) )          => 'PA',
			GFCommon::safe_strtoupper( __( 'Rhode Island', 'gravityforms' ) )          => 'RI',
			GFCommon::safe_strtoupper( __( 'South Carolina', 'gravityforms' ) )        => 'SC',
			GFCommon::safe_strtoupper( __( 'South Dakota', 'gravityforms' ) )          => 'SD',
			GFCommon::safe_strtoupper( __( 'Tennessee', 'gravityforms' ) )             => 'TN',
			GFCommon::safe_strtoupper( __( 'Texas', 'gravityforms' ) )                 => 'TX',
			GFCommon::safe_strtoupper( __( 'Utah', 'gravityforms' ) )                  => 'UT',
			GFCommon::safe_strtoupper( __( 'Vermont', 'gravityforms' ) )               => 'VT',
			GFCommon::safe_strtoupper( __( 'Virginia', 'gravityforms' ) )              => 'VA',
			GFCommon::safe_strtoupper( __( 'Washington', 'gravityforms' ) )            => 'WA',
			GFCommon::safe_strtoupper( __( 'West Virginia', 'gravityforms' ) )         => 'WV',
			GFCommon::safe_strtoupper( __( 'Wisconsin', 'gravityforms' ) )             => 'WI',
			GFCommon::safe_strtoupper( __( 'Wyoming', 'gravityforms' ) )               => 'WY',
			GFCommon::safe_strtoupper( __( 'Armed Forces Americas', 'gravityforms' ) ) => 'AA',
			GFCommon::safe_strtoupper( __( 'Armed Forces Europe', 'gravityforms' ) )   => 'AE',
			GFCommon::safe_strtoupper( __( 'Armed Forces Pacific', 'gravityforms' ) )  => 'AP',
		);

		$state_name = GFCommon::safe_strtoupper( $state_name );
		$code       = isset( $states[ $state_name ] ) ? $states[ $state_name ] : $state_name;

		return $code;
	}

	public function get_canadian_provinces() {
		return array(
			__( 'Alberta', 'gravityforms' ),
			__( 'British Columbia', 'gravityforms' ),
			__( 'Manitoba', 'gravityforms' ),
			__( 'New Brunswick', 'gravityforms' ),
			__( 'Newfoundland and Labrador', 'gravityforms' ),
			__( 'Northwest Territories', 'gravityforms' ),
			__( 'Nova Scotia', 'gravityforms' ),
			__( 'Nunavut', 'gravityforms' ),
			__( 'Ontario', 'gravityforms' ),
			__( 'Prince Edward Island', 'gravityforms' ),
			__( 'Quebec', 'gravityforms' ),
			__( 'Saskatchewan', 'gravityforms' ),
			__( 'Yukon', 'gravityforms' )
		);
	}

	public function get_state_dropdown( $states, $selected_state = '', $placeholder = '' ) {
		$str = '';
		foreach ( $states as $code => $state ) {
			if ( is_array( $state ) ) {
				$str .= sprintf( '<optgroup label="%1$s">%2$s</optgroup>', esc_attr( $code ), $this->get_state_dropdown( $state, $selected_state, $placeholder ) );
			} else {
				if ( is_numeric( $code ) ) {
					$code = $state;
				}
				if ( empty( $state ) ) {
					$state = $placeholder;
				}

				$str .= $this->get_select_option( $code, $state, $selected_state );
			}
		}

		return $str;
	}

	/**
	 * Returns the option tag for the current choice.
	 *
	 * @param string $value The choice value.
	 * @param string $label The choice label.
	 * @param string $selected_value The value for the selected choice.
	 *
	 * @return string
	 */
	public function get_select_option( $value, $label, $selected_value ) {
		$selected = $value == $selected_value ? "selected='selected'" : '';

		return sprintf( "<option value='%s' %s>%s</option>", esc_attr( $value ), $selected, esc_html( $label ) );
	}

	public function get_us_state_dropdown( $selected_state = '' ) {
		$states = array_merge( array( '' ), $this->get_us_states() );
		$str    = '';
		foreach ( $states as $code => $state ) {
			if ( is_numeric( $code ) ) {
				$code = $state;
			}

			$selected = $code == $selected_state ? "selected='selected'" : '';
			$str .= "<option value='" . esc_attr( $code ) . "' $selected>" . esc_html( $state ) . '</option>';
		}

		return $str;
	}

	public function get_canadian_provinces_dropdown( $selected_province = '' ) {
		$states = array_merge( array( '' ), $this->get_canadian_provinces() );
		$str    = '';
		foreach ( $states as $state ) {
			$selected = $state == $selected_province ? "selected='selected'" : '';
			$str .= "<option value='" . esc_attr( $state ) . "' $selected>" . esc_html( $state ) . '</option>';
		}

		return $str;
	}

	public function get_country_dropdown( $selected_country = '', $placeholder = '' ) {
		$str       = '';
		$selected_country = strtolower( $selected_country );
		$countries = array_merge( array( '' ), $this->get_countries() );
		foreach ( $countries as $code => $country ) {
			if ( is_numeric( $code ) ) {
				$code = $country;
			}
			if ( empty( $country ) ) {
				$country = $placeholder;
			}
			$selected = strtolower( esc_attr( $code ) ) == $selected_country ? "selected='selected'" : '';
			$str .= "<option value='" . esc_attr( $code ) . "' $selected>" . esc_html( $country ) . '</option>';
		}

		return $str;
	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		if ( is_array( $value ) ) {
			$street_value  = trim( rgget( $this->id . '.1', $value ) );
			$street2_value = trim( rgget( $this->id . '.2', $value ) );
			$city_value    = trim( rgget( $this->id . '.3', $value ) );
			$state_value   = trim( rgget( $this->id . '.4', $value ) );
			$zip_value     = trim( rgget( $this->id . '.5', $value ) );
			$country_value = trim( rgget( $this->id . '.6', $value ) );

			if ( $format === 'html' ) {
				$street_value  = esc_html( $street_value );
				$street2_value = esc_html( $street2_value );
				$city_value    = esc_html( $city_value );
				$state_value   = esc_html( $state_value );
				$zip_value     = esc_html( $zip_value );
				$country_value = esc_html( $country_value );

				$line_break = '<br />';
			} else {
				$line_break = "\n";
			}

			/**
			 * Filters the format that the address is displayed in.
			 *
			 * @since Unknown
			 *
			 * @param string           'default' The format to use. Defaults to 'default'.
			 * @param GF_Field_Address $this     An instance of the GF_Field_Address object.
			 */
			$address_display_format = apply_filters( 'gform_address_display_format', 'default', $this );
			if ( $address_display_format == 'zip_before_city' ) {
				/*
                Sample:
                3333 Some Street
                suite 16
                2344 City, State
                Country
                */

				$addr_ary   = array();
				$addr_ary[] = $street_value;

				if ( ! empty( $street2_value ) ) {
					$addr_ary[] = $street2_value;
				}

				$zip_line = trim( $zip_value . ' ' . $city_value );
				$zip_line .= ! empty( $zip_line ) && ! empty( $state_value ) ? ", {$state_value}" : $state_value;
				$zip_line = trim( $zip_line );
				if ( ! empty( $zip_line ) ) {
					$addr_ary[] = $zip_line;
				}

				if ( ! empty( $country_value ) ) {
					$addr_ary[] = $country_value;
				}

				$address = implode( '<br />', $addr_ary );

			} else {
				$address = $street_value;
				$address .= ! empty( $address ) && ! empty( $street2_value ) ? $line_break . $street2_value : $street2_value;
				$address .= ! empty( $address ) && ( ! empty( $city_value ) || ! empty( $state_value ) ) ? $line_break . $city_value : $city_value;
				$address .= ! empty( $address ) && ! empty( $city_value ) && ! empty( $state_value ) ? ", $state_value" : $state_value;
				$address .= ! empty( $address ) && ! empty( $zip_value ) ? " $zip_value" : $zip_value;
				$address .= ! empty( $address ) && ! empty( $country_value ) ? $line_break . $country_value : $country_value;
			}

			// Adding map link.
			/**
			 * Disables the Google Maps link from displaying in the address field.
			 *
			 * @since 1.9
			 *
			 * @param bool false Determines if the map link should be disabled. Set to true to disable. Defaults to false.
			 */
			$map_link_disabled = apply_filters( 'gform_disable_address_map_link', false );
			if ( ! empty( $address ) && $format == 'html' && ! $map_link_disabled ) {
				$address_qs = str_replace( $line_break, ' ', $address ); //replacing <br/> and \n with spaces
				$address_qs = urlencode( $address_qs );
				$address .= "<br/><a href='http://maps.google.com/maps?q={$address_qs}' target='_blank' class='map-it-link'>Map It</a>";
			}

			return $address;
		} else {
			return '';
		}
	}

	public function get_input_property( $input_id, $property_name ) {
		$input = GFFormsModel::get_input( $this, $input_id );

		return rgar( $input, $property_name );
	}

	public function sanitize_settings() {
		parent::sanitize_settings();
		if ( $this->addressType ) {
			$this->addressType = wp_strip_all_tags( $this->addressType );
		}

		if ( $this->defaultCountry ) {
			$this->defaultCountry = wp_strip_all_tags( $this->defaultCountry );
		}

		if ( $this->defaultProvince ) {
			$this->defaultProvince = wp_strip_all_tags( $this->defaultProvince );
		}

	}

	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		if ( empty( $input_id ) ) {
			$input_id = $this->id;
		}

		if ( absint( $input_id ) == $input_id ) {
			$street_value  = str_replace( '  ', ' ', trim( rgar( $entry, $input_id . '.1' ) ) );
			$street2_value = str_replace( '  ', ' ', trim( rgar( $entry, $input_id . '.2' ) ) );
			$city_value    = str_replace( '  ', ' ', trim( rgar( $entry, $input_id . '.3' ) ) );
			$state_value   = str_replace( '  ', ' ', trim( rgar( $entry, $input_id . '.4' ) ) );
			$zip_value     = trim( rgar( $entry, $input_id . '.5' ) );
			$country_value = $this->get_country_code( trim( rgar( $entry, $input_id . '.6' ) ) );

			$address = $street_value;
			$address .= ! empty( $address ) && ! empty( $street2_value ) ? "  $street2_value" : $street2_value;
			$address .= ! empty( $address ) && ( ! empty( $city_value ) || ! empty( $state_value ) ) ? ", $city_value," : $city_value;
			$address .= ! empty( $address ) && ! empty( $city_value ) && ! empty( $state_value ) ? "  $state_value" : $state_value;
			$address .= ! empty( $address ) && ! empty( $zip_value ) ? "  $zip_value," : $zip_value;
			$address .= ! empty( $address ) && ! empty( $country_value ) ? "  $country_value" : $country_value;

			return $address;
		} else {

			return rgar( $entry, $input_id );
		}
	}

	/**
	 * Removes the "for" attribute in the field label. Inputs are only allowed one label (a11y) and the inputs already have labels.
	 *
	 * @since  2.4
	 * @access public
	 *
	 * @param array $form The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_first_input_id( $form ) {
		return '';
	}

	// # FIELD FILTER UI HELPERS ---------------------------------------------------------------------------------------

	/**
	 * Returns the sub-filters for the current field.
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public function get_filter_sub_filters() {
		$sub_filters = array();
		$inputs      = $this->inputs;

		foreach ( $inputs as $input ) {
			if ( rgar( $input, 'isHidden' ) ) {
				continue;
			}

			$sub_filters[] = array(
				'key'             => rgar( $input, 'id' ),
				'text'            => rgar( $input, 'customLabel', rgar( $input, 'label' ) ),
				'preventMultiple' => false,
				'operators'       => $this->get_filter_operators(),
			);
		}

		return $sub_filters;
	}

	/**
	 * Returns the filter operators for the current field.
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public function get_filter_operators() {
		$operators   = parent::get_filter_operators();
		$operators[] = 'contains';

		return $operators;
	}
}

GF_Fields::register( new GF_Field_Address() );
