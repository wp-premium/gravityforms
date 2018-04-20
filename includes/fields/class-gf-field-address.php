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

			$street  = rgpost( 'input_' . $this->id . '_1' );
			$city    = rgpost( 'input_' . $this->id . '_3' );
			$state   = rgpost( 'input_' . $this->id . '_4' );
			$zip     = rgpost( 'input_' . $this->id . '_5' );
			$country = rgpost( 'input_' . $this->id . '_6' );

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

		$disabled_text = $is_form_editor ? "disabled='disabled'" : '';
		$class_suffix  = $is_entry_detail ? '_admin' : '';

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
		$address_street_sub_label  = esc_html( $address_street_sub_label );
		$address_street2_sub_label = rgar( $address_street2_field_input, 'customLabel' ) != '' ? $address_street2_field_input['customLabel'] : esc_html__( 'Address Line 2', 'gravityforms' );
		$address_street2_sub_label = gf_apply_filters( array( 'gform_address_street2', $form_id, $this->id ), $address_street2_sub_label, $form_id );
		$address_street2_sub_label = esc_html( $address_street2_sub_label );
		$address_zip_sub_label     = rgar( $address_zip_field_input, 'customLabel' ) != '' ? $address_zip_field_input['customLabel'] : $zip_label;
		$address_zip_sub_label     = gf_apply_filters( array( 'gform_address_zip', $form_id, $this->id ), $address_zip_sub_label, $form_id );
		$address_zip_sub_label     = esc_html( $address_zip_sub_label );
		$address_city_sub_label    = rgar( $address_city_field_input, 'customLabel' ) != '' ? $address_city_field_input['customLabel'] : esc_html__( 'City', 'gravityforms' );
		$address_city_sub_label    = gf_apply_filters( array( 'gform_address_city', $form_id, $this->id ), $address_city_sub_label, $form_id );
		$address_city_sub_label    = esc_html( $address_city_sub_label );
		$address_state_sub_label   = rgar( $address_state_field_input, 'customLabel' ) != '' ? $address_state_field_input['customLabel'] : $state_label;
		$address_state_sub_label   = gf_apply_filters( array( 'gform_address_state', $form_id, $this->id ), $address_state_sub_label, $form_id );
		$address_state_sub_label   = esc_html( $address_state_sub_label );
		$address_country_sub_label = rgar( $address_country_field_input, 'customLabel' ) != '' ? $address_country_field_input['customLabel'] : esc_html__( 'Country', 'gravityforms' );
		$address_country_sub_label = gf_apply_filters( array( 'gform_address_country', $form_id, $this->id ), $address_country_sub_label, $form_id );
		$address_country_sub_label = esc_html( $address_country_sub_label );

		// Address field.
		$street_address = '';
		$tabindex       = $this->get_tabindex();
		$style          = ( $is_admin && rgar( $address_street_field_input, 'isHidden' ) ) ? "style='display:none;'" : '';
		if ( $is_admin || ! rgar( $address_street_field_input, 'isHidden' ) ) {
			if ( $is_sub_label_above ) {
				$street_address = " <span class='ginput_full{$class_suffix} address_line_1' id='{$field_id}_1_container' {$style}>
                                        <label for='{$field_id}_1' id='{$field_id}_1_label' {$sub_label_class_attribute}>{$address_street_sub_label}</label>
                                        <input type='text' name='input_{$id}.1' id='{$field_id}_1' value='{$street_value}' {$tabindex} {$disabled_text} {$street_placeholder_attribute}/>
                                    </span>";
			} else {
				$street_address = " <span class='ginput_full{$class_suffix} address_line_1' id='{$field_id}_1_container' {$style}>
                                        <input type='text' name='input_{$id}.1' id='{$field_id}_1' value='{$street_value}' {$tabindex} {$disabled_text} {$street_placeholder_attribute}/>
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
                                    <input type='text' name='input_{$id}.5' id='{$field_id}_5' value='{$zip_value}' {$tabindex} {$disabled_text} {$zip_placeholder_attribute}/>
                                </span>";
				} else {
					$zip = "<span class='ginput_{$zip_location}{$class_suffix} address_zip' id='{$field_id}_5_container' {$style}>
                                    <input type='text' name='input_{$id}.5' id='{$field_id}_5' value='{$zip_value}' {$tabindex} {$disabled_text} {$zip_placeholder_attribute}/>
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
                                    <input type='text' name='input_{$id}.3' id='{$field_id}_3' value='{$city_value}' {$tabindex} {$disabled_text} {$city_placeholder_attribute}/>
                                 </span>";
				} else {
					$city = "<span class='ginput_{$city_location}{$class_suffix} address_city' id='{$field_id}_3_container' {$style}>
                                    <input type='text' name='input_{$id}.3' id='{$field_id}_3' value='{$city_value}' {$tabindex} {$disabled_text} {$city_placeholder_attribute}/>
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
                                    <input type='text' name='input_{$id}.3' id='{$field_id}_3' value='{$city_value}' {$tabindex} {$disabled_text} {$city_placeholder_attribute}/>
                                 </span>";
				} else {
					$city = "<span class='ginput_{$city_location}{$class_suffix} address_city' id='{$field_id}_3_container' {$style}>
                                    <input type='text' name='input_{$id}.3' id='{$field_id}_3' value='{$city_value}' {$tabindex} {$disabled_text} {$city_placeholder_attribute}/>
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
                                    <input type='text' name='input_{$id}.5' id='{$field_id}_5' value='{$zip_value}' {$tabindex} {$disabled_text} {$zip_placeholder_attribute}/>
                                </span>";
				} else {
					$zip = "<span class='ginput_{$zip_location}{$class_suffix} address_zip' id='{$field_id}_5_container' {$style}>
                                    <input type='text' name='input_{$id}.5' id='{$field_id}_5' value='{$zip_value}' {$tabindex} {$disabled_text} {$zip_placeholder_attribute}/>
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
                                        <select name='input_{$id}.6' id='{$field_id}_6' {$tabindex} {$disabled_text}>{$country_list}</select>
                                    </span>";
			} else {
				$country = "<span class='ginput_{$country_location}{$class_suffix} address_country' id='{$field_id}_6_container' {$style}>
                                        <select name='input_{$id}.6' id='{$field_id}_6' {$tabindex} {$disabled_text}>{$country_list}</select>
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
                    <div class='ginput_complex{$class_suffix} ginput_container {$css_class} gfield_trigger_change' id='$field_id' {$input_style}>
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
		$state_dropdown   = sprintf( "<select name='input_%d.4' %s $tabindex %s $state_dropdown_class $state_style>%s</select>", $id, $state_field_id, $disabled_text, $this->get_state_dropdown( $states, $state_value, $sate_placeholder ) );

		$tabindex                    = $this->get_tabindex();
		$state_placeholder_attribute = GFCommon::get_input_placeholder_attribute( $state_input );
		$state_text                  = sprintf( "<input type='text' name='input_%d.4' %s value='%s' {$tabindex} %s {$state_text_class} {$text_style} {$state_placeholder_attribute}/>", $id, $state_field_id, $state_value, $disabled_text );

		if ( $is_admin && rgget('view') != 'entry' ) {
			return $state_dropdown . $state_text;
		} elseif ( $has_state_drop_down ) {
			return $state_dropdown;
		} else {
			return $state_text;
		}
	}

	public function get_countries() {
		return apply_filters(
			'gform_countries', array(
				esc_html__( 'Afghanistan', 'gravityforms' ),
				esc_html__( 'Albania', 'gravityforms' ),
				esc_html__( 'Algeria', 'gravityforms' ),
				esc_html__( 'American Samoa', 'gravityforms' ),
				esc_html__( 'Andorra', 'gravityforms' ),
				esc_html__( 'Angola', 'gravityforms' ),
				esc_html__( 'Antigua and Barbuda', 'gravityforms' ),
				esc_html__( 'Argentina', 'gravityforms' ),
				esc_html__( 'Armenia', 'gravityforms' ),
				esc_html__( 'Australia', 'gravityforms' ),
				esc_html__( 'Austria', 'gravityforms' ),
				esc_html__( 'Azerbaijan', 'gravityforms' ),
				esc_html__( 'Bahamas', 'gravityforms' ),
				esc_html__( 'Bahrain', 'gravityforms' ),
				esc_html__( 'Bangladesh', 'gravityforms' ),
				esc_html__( 'Barbados', 'gravityforms' ),
				esc_html__( 'Belarus', 'gravityforms' ),
				esc_html__( 'Belgium', 'gravityforms' ),
				esc_html__( 'Belize', 'gravityforms' ),
				esc_html__( 'Benin', 'gravityforms' ),
				esc_html__( 'Bermuda', 'gravityforms' ),
				esc_html__( 'Bhutan', 'gravityforms' ),
				esc_html__( 'Bolivia', 'gravityforms' ),
				esc_html__( 'Bosnia and Herzegovina', 'gravityforms' ),
				esc_html__( 'Botswana', 'gravityforms' ),
				esc_html__( 'Brazil', 'gravityforms' ),
				esc_html__( 'Brunei', 'gravityforms' ),
				esc_html__( 'Bulgaria', 'gravityforms' ),
				esc_html__( 'Burkina Faso', 'gravityforms' ),
				esc_html__( 'Burundi', 'gravityforms' ),
				esc_html__( 'Cambodia', 'gravityforms' ),
				esc_html__( 'Cameroon', 'gravityforms' ),
				esc_html__( 'Canada', 'gravityforms' ),
				esc_html__( 'Cape Verde', 'gravityforms' ),
				esc_html__( 'Cayman Islands', 'gravityforms' ),
				esc_html__( 'Central African Republic', 'gravityforms' ),
				esc_html__( 'Chad', 'gravityforms' ),
				esc_html__( 'Chile', 'gravityforms' ),
				esc_html__( 'China', 'gravityforms' ),
				esc_html__( 'Colombia', 'gravityforms' ),
				esc_html__( 'Comoros', 'gravityforms' ),
				esc_html__( 'Congo, Democratic Republic of the', 'gravityforms' ),
				esc_html__( 'Congo, Republic of the', 'gravityforms' ),
				esc_html__( 'Costa Rica', 'gravityforms' ),
				esc_html__( "Côte d'Ivoire", 'gravityforms' ),
				esc_html__( 'Croatia', 'gravityforms' ),
				esc_html__( 'Cuba', 'gravityforms' ),
				esc_html__( 'Curaçao', 'gravityforms' ),
				esc_html__( 'Cyprus', 'gravityforms' ),
				esc_html__( 'Czech Republic', 'gravityforms' ),
				esc_html__( 'Denmark', 'gravityforms' ),
				esc_html__( 'Djibouti', 'gravityforms' ),
				esc_html__( 'Dominica', 'gravityforms' ),
				esc_html__( 'Dominican Republic', 'gravityforms' ),
				esc_html__( 'East Timor', 'gravityforms' ),
				esc_html__( 'Ecuador', 'gravityforms' ),
				esc_html__( 'Egypt', 'gravityforms' ),
				esc_html__( 'El Salvador', 'gravityforms' ),
				esc_html__( 'Equatorial Guinea', 'gravityforms' ),
				esc_html__( 'Eritrea', 'gravityforms' ),
				esc_html__( 'Estonia', 'gravityforms' ),
				esc_html__( 'Ethiopia', 'gravityforms' ),
				esc_html__( 'Faroe Islands', 'gravityforms' ),
				esc_html__( 'Fiji', 'gravityforms' ),
				esc_html__( 'Finland', 'gravityforms' ),
				esc_html__( 'France', 'gravityforms' ),
				esc_html__( 'French Polynesia', 'gravityforms' ),
				esc_html__( 'Gabon', 'gravityforms' ),
				esc_html__( 'Gambia', 'gravityforms' ),
				esc_html( _x( 'Georgia', 'Country', 'gravityforms' ) ),
				esc_html__( 'Germany', 'gravityforms' ),
				esc_html__( 'Ghana', 'gravityforms' ),
				esc_html__( 'Greece', 'gravityforms' ),
				esc_html__( 'Greenland', 'gravityforms' ),
				esc_html__( 'Grenada', 'gravityforms' ),
				esc_html__( 'Guam', 'gravityforms' ),
				esc_html__( 'Guatemala', 'gravityforms' ),
				esc_html__( 'Guinea', 'gravityforms' ),
				esc_html__( 'Guinea-Bissau', 'gravityforms' ),
				esc_html__( 'Guyana', 'gravityforms' ),
				esc_html__( 'Haiti', 'gravityforms' ),
				esc_html__( 'Honduras', 'gravityforms' ),
				esc_html__( 'Hong Kong', 'gravityforms' ),
				esc_html__( 'Hungary', 'gravityforms' ),
				esc_html__( 'Iceland', 'gravityforms' ),
				esc_html__( 'India', 'gravityforms' ),
				esc_html__( 'Indonesia', 'gravityforms' ),
				esc_html__( 'Iran', 'gravityforms' ),
				esc_html__( 'Iraq', 'gravityforms' ),
				esc_html__( 'Ireland', 'gravityforms' ),
				esc_html__( 'Israel', 'gravityforms' ),
				esc_html__( 'Italy', 'gravityforms' ),
				esc_html__( 'Jamaica', 'gravityforms' ),
				esc_html__( 'Japan', 'gravityforms' ),
				esc_html__( 'Jordan', 'gravityforms' ),
				esc_html__( 'Kazakhstan', 'gravityforms' ),
				esc_html__( 'Kenya', 'gravityforms' ),
				esc_html__( 'Kiribati', 'gravityforms' ),
				esc_html__( 'North Korea', 'gravityforms' ),
				esc_html__( 'South Korea', 'gravityforms' ),
				esc_html__( 'Kosovo', 'gravityforms' ),
				esc_html__( 'Kuwait', 'gravityforms' ),
				esc_html__( 'Kyrgyzstan', 'gravityforms' ),
				esc_html__( 'Laos', 'gravityforms' ),
				esc_html__( 'Latvia', 'gravityforms' ),
				esc_html__( 'Lebanon', 'gravityforms' ),
				esc_html__( 'Lesotho', 'gravityforms' ),
				esc_html__( 'Liberia', 'gravityforms' ),
				esc_html__( 'Libya', 'gravityforms' ),
				esc_html__( 'Liechtenstein', 'gravityforms' ),
				esc_html__( 'Lithuania', 'gravityforms' ),
				esc_html__( 'Luxembourg', 'gravityforms' ),
				esc_html__( 'Macedonia', 'gravityforms' ),
				esc_html__( 'Madagascar', 'gravityforms' ),
				esc_html__( 'Malawi', 'gravityforms' ),
				esc_html__( 'Malaysia', 'gravityforms' ),
				esc_html__( 'Maldives', 'gravityforms' ),
				esc_html__( 'Mali', 'gravityforms' ),
				esc_html__( 'Malta', 'gravityforms' ),
				esc_html__( 'Marshall Islands', 'gravityforms' ),
				esc_html__( 'Mauritania', 'gravityforms' ),
				esc_html__( 'Mauritius', 'gravityforms' ),
				esc_html__( 'Mexico', 'gravityforms' ),
				esc_html__( 'Micronesia', 'gravityforms' ),
				esc_html__( 'Moldova', 'gravityforms' ),
				esc_html__( 'Monaco', 'gravityforms' ),
				esc_html__( 'Mongolia', 'gravityforms' ),
				esc_html__( 'Montenegro', 'gravityforms' ),
				esc_html__( 'Morocco', 'gravityforms' ),
				esc_html__( 'Mozambique', 'gravityforms' ),
				esc_html__( 'Myanmar', 'gravityforms' ),
				esc_html__( 'Namibia', 'gravityforms' ),
				esc_html__( 'Nauru', 'gravityforms' ),
				esc_html__( 'Nepal', 'gravityforms' ),
				esc_html__( 'Netherlands', 'gravityforms' ),
				esc_html__( 'New Zealand', 'gravityforms' ),
				esc_html__( 'Nicaragua', 'gravityforms' ),
				esc_html__( 'Niger', 'gravityforms' ),
				esc_html__( 'Nigeria', 'gravityforms' ),
				esc_html__( 'Northern Mariana Islands', 'gravityforms' ),
				esc_html__( 'Norway', 'gravityforms' ),
				esc_html__( 'Oman', 'gravityforms' ),
				esc_html__( 'Pakistan', 'gravityforms' ),
				esc_html__( 'Palau', 'gravityforms' ),
				esc_html__( 'Palestine, State of', 'gravityforms' ),
				esc_html__( 'Panama', 'gravityforms' ),
				esc_html__( 'Papua New Guinea', 'gravityforms' ),
				esc_html__( 'Paraguay', 'gravityforms' ),
				esc_html__( 'Peru', 'gravityforms' ),
				esc_html__( 'Philippines', 'gravityforms' ),
				esc_html__( 'Poland', 'gravityforms' ),
				esc_html__( 'Portugal', 'gravityforms' ),
				esc_html__( 'Puerto Rico', 'gravityforms' ),
				esc_html__( 'Qatar', 'gravityforms' ),
				esc_html__( 'Romania', 'gravityforms' ),
				esc_html__( 'Russia', 'gravityforms' ),
				esc_html__( 'Rwanda', 'gravityforms' ),
				esc_html__( 'Saint Kitts and Nevis', 'gravityforms' ),
				esc_html__( 'Saint Lucia', 'gravityforms' ),
				esc_html__( 'Saint Vincent and the Grenadines', 'gravityforms' ),
				esc_html__( 'Saint Martin', 'gravityforms' ),
				esc_html__( 'Samoa', 'gravityforms' ),
				esc_html__( 'San Marino', 'gravityforms' ),
				esc_html__( 'Sao Tome and Principe', 'gravityforms' ),
				esc_html__( 'Saudi Arabia', 'gravityforms' ),
				esc_html__( 'Senegal', 'gravityforms' ),
				esc_html__( 'Serbia', 'gravityforms' ),
				esc_html__( 'Seychelles', 'gravityforms' ),
				esc_html__( 'Sierra Leone', 'gravityforms' ),
				esc_html__( 'Singapore', 'gravityforms' ),
				esc_html__( 'Sint Maarten', 'gravityforms' ),
				esc_html__( 'Slovakia', 'gravityforms' ),
				esc_html__( 'Slovenia', 'gravityforms' ),
				esc_html__( 'Solomon Islands', 'gravityforms' ),
				esc_html__( 'Somalia', 'gravityforms' ),
				esc_html__( 'South Africa', 'gravityforms' ),
				esc_html__( 'Spain', 'gravityforms' ),
				esc_html__( 'Sri Lanka', 'gravityforms' ),
				esc_html__( 'Sudan', 'gravityforms' ),
				esc_html__( 'Sudan, South', 'gravityforms' ),
				esc_html__( 'Suriname', 'gravityforms' ),
				esc_html__( 'Swaziland', 'gravityforms' ),
				esc_html__( 'Sweden', 'gravityforms' ),
				esc_html__( 'Switzerland', 'gravityforms' ),
				esc_html__( 'Syria', 'gravityforms' ),
				esc_html__( 'Taiwan', 'gravityforms' ),
				esc_html__( 'Tajikistan', 'gravityforms' ),
				esc_html__( 'Tanzania', 'gravityforms' ),
				esc_html__( 'Thailand', 'gravityforms' ),
				esc_html__( 'Togo', 'gravityforms' ),
				esc_html__( 'Tonga', 'gravityforms' ),
				esc_html__( 'Trinidad and Tobago', 'gravityforms' ),
				esc_html__( 'Tunisia', 'gravityforms' ),
				esc_html__( 'Turkey', 'gravityforms' ),
				esc_html__( 'Turkmenistan', 'gravityforms' ),
				esc_html__( 'Tuvalu', 'gravityforms' ),
				esc_html__( 'Uganda', 'gravityforms' ),
				esc_html__( 'Ukraine', 'gravityforms' ),
				esc_html__( 'United Arab Emirates', 'gravityforms' ),
				esc_html__( 'United Kingdom', 'gravityforms' ),
				esc_html__( 'United States', 'gravityforms' ),
				esc_html__( 'Uruguay', 'gravityforms' ),
				esc_html__( 'Uzbekistan', 'gravityforms' ),
				esc_html__( 'Vanuatu', 'gravityforms' ),
				esc_html__( 'Vatican City', 'gravityforms' ),
				esc_html__( 'Venezuela', 'gravityforms' ),
				esc_html__( 'Vietnam', 'gravityforms' ),
				esc_html__( 'Virgin Islands, British', 'gravityforms' ),
				esc_html__( 'Virgin Islands, U.S.', 'gravityforms' ),
				esc_html__( 'Yemen', 'gravityforms' ),
				esc_html__( 'Zambia', 'gravityforms' ),
				esc_html__( 'Zimbabwe', 'gravityforms' ),
			)
		);
	}

	public function get_country_code( $country_name ) {
		$codes = $this->get_country_codes();

		return rgar( $codes, GFCommon::safe_strtoupper( $country_name ) );
	}

	public function get_country_codes() {
		$codes = array(
			esc_html__( 'AFGHANISTAN', 'gravityforms' )                       => 'AF',
			esc_html__( 'ALBANIA', 'gravityforms' )                           => 'AL',
			esc_html__( 'ALGERIA', 'gravityforms' )                           => 'DZ',
			esc_html__( 'AMERICAN SAMOA', 'gravityforms' )                    => 'AS',
			esc_html__( 'ANDORRA', 'gravityforms' )                           => 'AD',
			esc_html__( 'ANGOLA', 'gravityforms' )                            => 'AO',
			esc_html__( 'ANTIGUA AND BARBUDA', 'gravityforms' )               => 'AG',
			esc_html__( 'ARGENTINA', 'gravityforms' )                         => 'AR',
			esc_html__( 'ARMENIA', 'gravityforms' )                           => 'AM',
			esc_html__( 'AUSTRALIA', 'gravityforms' )                         => 'AU',
			esc_html__( 'AUSTRIA', 'gravityforms' )                           => 'AT',
			esc_html__( 'AZERBAIJAN', 'gravityforms' )                        => 'AZ',
			esc_html__( 'BAHAMAS', 'gravityforms' )                           => 'BS',
			esc_html__( 'BAHRAIN', 'gravityforms' )                           => 'BH',
			esc_html__( 'BANGLADESH', 'gravityforms' )                        => 'BD',
			esc_html__( 'BARBADOS', 'gravityforms' )                          => 'BB',
			esc_html__( 'BELARUS', 'gravityforms' )                           => 'BY',
			esc_html__( 'BELGIUM', 'gravityforms' )                           => 'BE',
			esc_html__( 'BELIZE', 'gravityforms' )                            => 'BZ',
			esc_html__( 'BENIN', 'gravityforms' )                             => 'BJ',
			esc_html__( 'BERMUDA', 'gravityforms' )                           => 'BM',
			esc_html__( 'BHUTAN', 'gravityforms' )                            => 'BT',
			esc_html__( 'BOLIVIA', 'gravityforms' )                           => 'BO',
			esc_html__( 'BOSNIA AND HERZEGOVINA', 'gravityforms' )            => 'BA',
			esc_html__( 'BOTSWANA', 'gravityforms' )                          => 'BW',
			esc_html__( 'BRAZIL', 'gravityforms' )                            => 'BR',
			esc_html__( 'BRUNEI', 'gravityforms' )                            => 'BN',
			esc_html__( 'BULGARIA', 'gravityforms' )                          => 'BG',
			esc_html__( 'BURKINA FASO', 'gravityforms' )                      => 'BF',
			esc_html__( 'BURUNDI', 'gravityforms' )                           => 'BI',
			esc_html__( 'CAMBODIA', 'gravityforms' )                          => 'KH',
			esc_html__( 'CAMEROON', 'gravityforms' )                          => 'CM',
			esc_html__( 'CANADA', 'gravityforms' )                            => 'CA',
			esc_html__( 'CAPE VERDE', 'gravityforms' )                        => 'CV',
			esc_html__( 'CAYMAN ISLANDS', 'gravityforms' )                    => 'KY',
			esc_html__( 'CENTRAL AFRICAN REPUBLIC', 'gravityforms' )          => 'CF',
			esc_html__( 'CHAD', 'gravityforms' )                              => 'TD',
			esc_html__( 'CHILE', 'gravityforms' )                             => 'CL',
			esc_html__( 'CHINA', 'gravityforms' )                             => 'CN',
			esc_html__( 'COLOMBIA', 'gravityforms' )                          => 'CO',
			esc_html__( 'COMOROS', 'gravityforms' )                           => 'KM',
			esc_html__( 'CONGO, DEMOCRATIC REPUBLIC OF THE', 'gravityforms' ) => 'CD',
			esc_html__( 'CONGO, REPUBLIC OF THE', 'gravityforms' )            => 'CG',
			esc_html__( 'COSTA RICA', 'gravityforms' )                        => 'CR',
			esc_html__( "CÔTE D'IVOIRE", 'gravityforms' )                     => 'CI',
			esc_html__( 'CROATIA', 'gravityforms' )                           => 'HR',
			esc_html__( 'CUBA', 'gravityforms' )                              => 'CU',
			esc_html__( 'CURAÇAO', 'gravityforms' )                           => 'CW',
			esc_html__( 'CYPRUS', 'gravityforms' )                            => 'CY',
			esc_html__( 'CZECH REPUBLIC', 'gravityforms' )                    => 'CZ',
			esc_html__( 'DENMARK', 'gravityforms' )                           => 'DK',
			esc_html__( 'DJIBOUTI', 'gravityforms' )                          => 'DJ',
			esc_html__( 'DOMINICA', 'gravityforms' )                          => 'DM',
			esc_html__( 'DOMINICAN REPUBLIC', 'gravityforms' )                => 'DO',
			esc_html__( 'EAST TIMOR', 'gravityforms' )                        => 'TL',
			esc_html__( 'ECUADOR', 'gravityforms' )                           => 'EC',
			esc_html__( 'EGYPT', 'gravityforms' )                             => 'EG',
			esc_html__( 'EL SALVADOR', 'gravityforms' )                       => 'SV',
			esc_html__( 'EQUATORIAL GUINEA', 'gravityforms' )                 => 'GQ',
			esc_html__( 'ERITREA', 'gravityforms' )                           => 'ER',
			esc_html__( 'ESTONIA', 'gravityforms' )                           => 'EE',
			esc_html__( 'ETHIOPIA', 'gravityforms' )                          => 'ET',
			esc_html__( 'FAROE ISLANDS', 'gravityforms' )                     => 'FO',
			esc_html__( 'FIJI', 'gravityforms' )                              => 'FJ',
			esc_html__( 'FINLAND', 'gravityforms' )                           => 'FI',
			esc_html__( 'FRANCE', 'gravityforms' )                            => 'FR',
			esc_html__( 'FRENCH POLYNESIA', 'gravityforms' )                  => 'PF',
			esc_html__( 'GABON', 'gravityforms' )                             => 'GA',
			esc_html__( 'GAMBIA', 'gravityforms' )                            => 'GM',
			esc_html( _x( 'GEORGIA', 'Country', 'gravityforms' ) )            => 'GE',
			esc_html__( 'GERMANY', 'gravityforms' )                           => 'DE',
			esc_html__( 'GHANA', 'gravityforms' )                             => 'GH',
			esc_html__( 'GREECE', 'gravityforms' )                            => 'GR',
			esc_html__( 'GREENLAND', 'gravityforms' )                         => 'GL',
			esc_html__( 'GRENADA', 'gravityforms' )                           => 'GD',
			esc_html__( 'GUAM', 'gravityforms' )                              => 'GU',
			esc_html__( 'GUATEMALA', 'gravityforms' )                         => 'GT',
			esc_html__( 'GUINEA', 'gravityforms' )                            => 'GN',
			esc_html__( 'GUINEA-BISSAU', 'gravityforms' )                     => 'GW',
			esc_html__( 'GUYANA', 'gravityforms' )                            => 'GY',
			esc_html__( 'HAITI', 'gravityforms' )                             => 'HT',
			esc_html__( 'HONDURAS', 'gravityforms' )                          => 'HN',
			esc_html__( 'HONG KONG', 'gravityforms' )                         => 'HK',
			esc_html__( 'HUNGARY', 'gravityforms' )                           => 'HU',
			esc_html__( 'ICELAND', 'gravityforms' )                           => 'IS',
			esc_html__( 'INDIA', 'gravityforms' )                             => 'IN',
			esc_html__( 'INDONESIA', 'gravityforms' )                         => 'ID',
			esc_html__( 'IRAN', 'gravityforms' )                              => 'IR',
			esc_html__( 'IRAQ', 'gravityforms' )                              => 'IQ',
			esc_html__( 'IRELAND', 'gravityforms' )                           => 'IE',
			esc_html__( 'ISRAEL', 'gravityforms' )                            => 'IL',
			esc_html__( 'ITALY', 'gravityforms' )                             => 'IT',
			esc_html__( 'JAMAICA', 'gravityforms' )                           => 'JM',
			esc_html__( 'JAPAN', 'gravityforms' )                             => 'JP',
			esc_html__( 'JORDAN', 'gravityforms' )                            => 'JO',
			esc_html__( 'KAZAKHSTAN', 'gravityforms' )                        => 'KZ',
			esc_html__( 'KENYA', 'gravityforms' )                             => 'KE',
			esc_html__( 'KIRIBATI', 'gravityforms' )                          => 'KI',
			esc_html__( 'NORTH KOREA', 'gravityforms' )                       => 'KP',
			esc_html__( 'SOUTH KOREA', 'gravityforms' )                       => 'KR',
			esc_html__( 'KOSOVO', 'gravityforms' )                            => 'KV',
			esc_html__( 'KUWAIT', 'gravityforms' )                            => 'KW',
			esc_html__( 'KYRGYZSTAN', 'gravityforms' )                        => 'KG',
			esc_html__( 'LAOS', 'gravityforms' )                              => 'LA',
			esc_html__( 'LATVIA', 'gravityforms' )                            => 'LV',
			esc_html__( 'LEBANON', 'gravityforms' )                           => 'LB',
			esc_html__( 'LESOTHO', 'gravityforms' )                           => 'LS',
			esc_html__( 'LIBERIA', 'gravityforms' )                           => 'LR',
			esc_html__( 'LIBYA', 'gravityforms' )                             => 'LY',
			esc_html__( 'LIECHTENSTEIN', 'gravityforms' )                     => 'LI',
			esc_html__( 'LITHUANIA', 'gravityforms' )                         => 'LT',
			esc_html__( 'LUXEMBOURG', 'gravityforms' )                        => 'LU',
			esc_html__( 'MACEDONIA', 'gravityforms' )                         => 'MK',
			esc_html__( 'MADAGASCAR', 'gravityforms' )                        => 'MG',
			esc_html__( 'MALAWI', 'gravityforms' )                            => 'MW',
			esc_html__( 'MALAYSIA', 'gravityforms' )                          => 'MY',
			esc_html__( 'MALDIVES', 'gravityforms' )                          => 'MV',
			esc_html__( 'MALI', 'gravityforms' )                              => 'ML',
			esc_html__( 'MALTA', 'gravityforms' )                             => 'MT',
			esc_html__( 'MARSHALL ISLANDS', 'gravityforms' )                  => 'MH',
			esc_html__( 'MAURITANIA', 'gravityforms' )                        => 'MR',
			esc_html__( 'MAURITIUS', 'gravityforms' )                         => 'MU',
			esc_html__( 'MEXICO', 'gravityforms' )                            => 'MX',
			esc_html__( 'MICRONESIA', 'gravityforms' )                        => 'FM',
			esc_html__( 'MOLDOVA', 'gravityforms' )                           => 'MD',
			esc_html__( 'MONACO', 'gravityforms' )                            => 'MC',
			esc_html__( 'MONGOLIA', 'gravityforms' )                          => 'MN',
			esc_html__( 'MONTENEGRO', 'gravityforms' )                        => 'ME',
			esc_html__( 'MOROCCO', 'gravityforms' )                           => 'MA',
			esc_html__( 'MOZAMBIQUE', 'gravityforms' )                        => 'MZ',
			esc_html__( 'MYANMAR', 'gravityforms' )                           => 'MM',
			esc_html__( 'NAMIBIA', 'gravityforms' )                           => 'NA',
			esc_html__( 'NAURU', 'gravityforms' )                             => 'NR',
			esc_html__( 'NEPAL', 'gravityforms' )                             => 'NP',
			esc_html__( 'NETHERLANDS', 'gravityforms' )                       => 'NL',
			esc_html__( 'NEW ZEALAND', 'gravityforms' )                       => 'NZ',
			esc_html__( 'NICARAGUA', 'gravityforms' )                         => 'NI',
			esc_html__( 'NIGER', 'gravityforms' )                             => 'NE',
			esc_html__( 'NIGERIA', 'gravityforms' )                           => 'NG',
			esc_html__( 'NORTHERN MARIANA ISLANDS', 'gravityforms' )          => 'MP',
			esc_html__( 'NORWAY', 'gravityforms' )                            => 'NO',
			esc_html__( 'OMAN', 'gravityforms' )                              => 'OM',
			esc_html__( 'PAKISTAN', 'gravityforms' )                          => 'PK',
			esc_html__( 'PALAU', 'gravityforms' )                             => 'PW',
			esc_html__( 'PALESTINE, STATE OF', 'gravityforms' )               => 'PS',
			esc_html__( 'PANAMA', 'gravityforms' )                            => 'PA',
			esc_html__( 'PAPUA NEW GUINEA', 'gravityforms' )                  => 'PG',
			esc_html__( 'PARAGUAY', 'gravityforms' )                          => 'PY',
			esc_html__( 'PERU', 'gravityforms' )                              => 'PE',
			esc_html__( 'PHILIPPINES', 'gravityforms' )                       => 'PH',
			esc_html__( 'POLAND', 'gravityforms' )                            => 'PL',
			esc_html__( 'PORTUGAL', 'gravityforms' )                          => 'PT',
			esc_html__( 'PUERTO RICO', 'gravityforms' )                       => 'PR',
			esc_html__( 'QATAR', 'gravityforms' )                             => 'QA',
			esc_html__( 'ROMANIA', 'gravityforms' )                           => 'RO',
			esc_html__( 'RUSSIA', 'gravityforms' )                            => 'RU',
			esc_html__( 'RWANDA', 'gravityforms' )                            => 'RW',
			esc_html__( 'SAINT KITTS AND NEVIS', 'gravityforms' )             => 'KN',
			esc_html__( 'SAINT LUCIA', 'gravityforms' )                       => 'LC',
			esc_html__( 'SAINT MARTIN', 'gravityforms' )					  => 'MF',
			esc_html__( 'SAINT VINCENT AND THE GRENADINES', 'gravityforms' )  => 'VC',
			esc_html__( 'SAMOA', 'gravityforms' )                             => 'WS',
			esc_html__( 'SAN MARINO', 'gravityforms' )                        => 'SM',
			esc_html__( 'SAO TOME AND PRINCIPE', 'gravityforms' )             => 'ST',
			esc_html__( 'SAUDI ARABIA', 'gravityforms' )                      => 'SA',
			esc_html__( 'SENEGAL', 'gravityforms' )                           => 'SN',
			esc_html__( 'SERBIA', 'gravityforms' )                            => 'RS',
			esc_html__( 'SEYCHELLES', 'gravityforms' )                        => 'SC',
			esc_html__( 'SIERRA LEONE', 'gravityforms' )                      => 'SL',
			esc_html__( 'SINGAPORE', 'gravityforms' )                         => 'SG',
			esc_html__( 'SINT MAARTEN', 'gravityforms' )                      => 'SX',
			esc_html__( 'SLOVAKIA', 'gravityforms' )                          => 'SK',
			esc_html__( 'SLOVENIA', 'gravityforms' )                          => 'SI',
			esc_html__( 'SOLOMON ISLANDS', 'gravityforms' )                   => 'SB',
			esc_html__( 'SOMALIA', 'gravityforms' )                           => 'SO',
			esc_html__( 'SOUTH AFRICA', 'gravityforms' )                      => 'ZA',
			esc_html__( 'SPAIN', 'gravityforms' )                             => 'ES',
			esc_html__( 'SRI LANKA', 'gravityforms' )                         => 'LK',
			esc_html__( 'SUDAN', 'gravityforms' )                             => 'SD',
			esc_html__( 'SUDAN, SOUTH', 'gravityforms' )                      => 'SS',
			esc_html__( 'SURINAME', 'gravityforms' )                          => 'SR',
			esc_html__( 'SWAZILAND', 'gravityforms' )                         => 'SZ',
			esc_html__( 'SWEDEN', 'gravityforms' )                            => 'SE',
			esc_html__( 'SWITZERLAND', 'gravityforms' )                       => 'CH',
			esc_html__( 'SYRIA', 'gravityforms' )                             => 'SY',
			esc_html__( 'TAIWAN', 'gravityforms' )                            => 'TW',
			esc_html__( 'TAJIKISTAN', 'gravityforms' )                        => 'TJ',
			esc_html__( 'TANZANIA', 'gravityforms' )                          => 'TZ',
			esc_html__( 'THAILAND', 'gravityforms' )                          => 'TH',
			esc_html__( 'TOGO', 'gravityforms' )                              => 'TG',
			esc_html__( 'TONGA', 'gravityforms' )                             => 'TO',
			esc_html__( 'TRINIDAD AND TOBAGO', 'gravityforms' )               => 'TT',
			esc_html__( 'TUNISIA', 'gravityforms' )                           => 'TN',
			esc_html__( 'TURKEY', 'gravityforms' )                            => 'TR',
			esc_html__( 'TURKMENISTAN', 'gravityforms' )                      => 'TM',
			esc_html__( 'TUVALU', 'gravityforms' )                            => 'TV',
			esc_html__( 'UGANDA', 'gravityforms' )                            => 'UG',
			esc_html__( 'UKRAINE', 'gravityforms' )                           => 'UA',
			esc_html__( 'UNITED ARAB EMIRATES', 'gravityforms' )              => 'AE',
			esc_html__( 'UNITED KINGDOM', 'gravityforms' )                    => 'GB',
			esc_html__( 'UNITED STATES', 'gravityforms' )                     => 'US',
			esc_html__( 'URUGUAY', 'gravityforms' )                           => 'UY',
			esc_html__( 'UZBEKISTAN', 'gravityforms' )                        => 'UZ',
			esc_html__( 'VANUATU', 'gravityforms' )                           => 'VU',
			esc_html__( 'VATICAN CITY', 'gravityforms' )                      => 'VA',
			esc_html__( 'VENEZUELA', 'gravityforms' )                         => 'VE',
			esc_html__( 'VIRGIN ISLANDS, BRITISH', 'gravityforms' )           => 'VG',
			esc_html__( 'VIRGIN ISLANDS, U.S.', 'gravityforms' )              => 'VI',
			esc_html__( 'VIETNAM', 'gravityforms' )                           => 'VN',
			esc_html__( 'YEMEN', 'gravityforms' )                             => 'YE',
			esc_html__( 'ZAMBIA', 'gravityforms' )                            => 'ZM',
			esc_html__( 'ZIMBABWE', 'gravityforms' )                          => 'ZW',
		);

		return $codes;
	}

	public function get_us_states() {
		return apply_filters(
			'gform_us_states', array(
				esc_html__( 'Alabama', 'gravityforms' ),
				esc_html__( 'Alaska', 'gravityforms' ),
				esc_html__( 'Arizona', 'gravityforms' ),
				esc_html__( 'Arkansas', 'gravityforms' ),
				esc_html__( 'California', 'gravityforms' ),
				esc_html__( 'Colorado', 'gravityforms' ),
				esc_html__( 'Connecticut', 'gravityforms' ),
				esc_html__( 'Delaware', 'gravityforms' ),
				esc_html__( 'District of Columbia', 'gravityforms' ),
				esc_html__( 'Florida', 'gravityforms' ),
				esc_html( _x( 'Georgia', 'US State', 'gravityforms' ) ),
				esc_html__( 'Hawaii', 'gravityforms' ),
				esc_html__( 'Idaho', 'gravityforms' ),
				esc_html__( 'Illinois', 'gravityforms' ),
				esc_html__( 'Indiana', 'gravityforms' ),
				esc_html__( 'Iowa', 'gravityforms' ),
				esc_html__( 'Kansas', 'gravityforms' ),
				esc_html__( 'Kentucky', 'gravityforms' ),
				esc_html__( 'Louisiana', 'gravityforms' ),
				esc_html__( 'Maine', 'gravityforms' ),
				esc_html__( 'Maryland', 'gravityforms' ),
				esc_html__( 'Massachusetts', 'gravityforms' ),
				esc_html__( 'Michigan', 'gravityforms' ),
				esc_html__( 'Minnesota', 'gravityforms' ),
				esc_html__( 'Mississippi', 'gravityforms' ),
				esc_html__( 'Missouri', 'gravityforms' ),
				esc_html__( 'Montana', 'gravityforms' ),
				esc_html__( 'Nebraska', 'gravityforms' ),
				esc_html__( 'Nevada', 'gravityforms' ),
				esc_html__( 'New Hampshire', 'gravityforms' ),
				esc_html__( 'New Jersey', 'gravityforms' ),
				esc_html__( 'New Mexico', 'gravityforms' ),
				esc_html__( 'New York', 'gravityforms' ),
				esc_html__( 'North Carolina', 'gravityforms' ),
				esc_html__( 'North Dakota', 'gravityforms' ),
				esc_html__( 'Ohio', 'gravityforms' ),
				esc_html__( 'Oklahoma', 'gravityforms' ),
				esc_html__( 'Oregon', 'gravityforms' ),
				esc_html__( 'Pennsylvania', 'gravityforms' ),
				esc_html__( 'Rhode Island', 'gravityforms' ),
				esc_html__( 'South Carolina', 'gravityforms' ),
				esc_html__( 'South Dakota', 'gravityforms' ),
				esc_html__( 'Tennessee', 'gravityforms' ),
				esc_html__( 'Texas', 'gravityforms' ),
				esc_html__( 'Utah', 'gravityforms' ),
				esc_html__( 'Vermont', 'gravityforms' ),
				esc_html__( 'Virginia', 'gravityforms' ),
				esc_html__( 'Washington', 'gravityforms' ),
				esc_html__( 'West Virginia', 'gravityforms' ),
				esc_html__( 'Wisconsin', 'gravityforms' ),
				esc_html__( 'Wyoming', 'gravityforms' ),
				esc_html__( 'Armed Forces Americas', 'gravityforms' ),
				esc_html__( 'Armed Forces Europe', 'gravityforms' ),
				esc_html__( 'Armed Forces Pacific', 'gravityforms' ),
			)
		);
	}

	public function get_us_state_code( $state_name ) {
		$states = array(
			GFCommon::safe_strtoupper( esc_html__( 'Alabama', 'gravityforms' ) )                 => 'AL',
			GFCommon::safe_strtoupper( esc_html__( 'Alaska', 'gravityforms' ) )                  => 'AK',
			GFCommon::safe_strtoupper( esc_html__( 'Arizona', 'gravityforms' ) )                 => 'AZ',
			GFCommon::safe_strtoupper( esc_html__( 'Arkansas', 'gravityforms' ) )                => 'AR',
			GFCommon::safe_strtoupper( esc_html__( 'California', 'gravityforms' ) )              => 'CA',
			GFCommon::safe_strtoupper( esc_html__( 'Colorado', 'gravityforms' ) )                => 'CO',
			GFCommon::safe_strtoupper( esc_html__( 'Connecticut', 'gravityforms' ) )             => 'CT',
			GFCommon::safe_strtoupper( esc_html__( 'Delaware', 'gravityforms' ) )                => 'DE',
			GFCommon::safe_strtoupper( esc_html__( 'District of Columbia', 'gravityforms' ) )    => 'DC',
			GFCommon::safe_strtoupper( esc_html__( 'Florida', 'gravityforms' ) )                 => 'FL',
			GFCommon::safe_strtoupper( esc_html( _x( 'Georgia', 'US State', 'gravityforms' ) ) ) => 'GA',
			GFCommon::safe_strtoupper( esc_html__( 'Hawaii', 'gravityforms' ) )                  => 'HI',
			GFCommon::safe_strtoupper( esc_html__( 'Idaho', 'gravityforms' ) )                   => 'ID',
			GFCommon::safe_strtoupper( esc_html__( 'Illinois', 'gravityforms' ) )                => 'IL',
			GFCommon::safe_strtoupper( esc_html__( 'Indiana', 'gravityforms' ) )                 => 'IN',
			GFCommon::safe_strtoupper( esc_html__( 'Iowa', 'gravityforms' ) )                    => 'IA',
			GFCommon::safe_strtoupper( esc_html__( 'Kansas', 'gravityforms' ) )                  => 'KS',
			GFCommon::safe_strtoupper( esc_html__( 'Kentucky', 'gravityforms' ) )                => 'KY',
			GFCommon::safe_strtoupper( esc_html__( 'Louisiana', 'gravityforms' ) )               => 'LA',
			GFCommon::safe_strtoupper( esc_html__( 'Maine', 'gravityforms' ) )                   => 'ME',
			GFCommon::safe_strtoupper( esc_html__( 'Maryland', 'gravityforms' ) )                => 'MD',
			GFCommon::safe_strtoupper( esc_html__( 'Massachusetts', 'gravityforms' ) )           => 'MA',
			GFCommon::safe_strtoupper( esc_html__( 'Michigan', 'gravityforms' ) )                => 'MI',
			GFCommon::safe_strtoupper( esc_html__( 'Minnesota', 'gravityforms' ) )               => 'MN',
			GFCommon::safe_strtoupper( esc_html__( 'Mississippi', 'gravityforms' ) )             => 'MS',
			GFCommon::safe_strtoupper( esc_html__( 'Missouri', 'gravityforms' ) )                => 'MO',
			GFCommon::safe_strtoupper( esc_html__( 'Montana', 'gravityforms' ) )                 => 'MT',
			GFCommon::safe_strtoupper( esc_html__( 'Nebraska', 'gravityforms' ) )                => 'NE',
			GFCommon::safe_strtoupper( esc_html__( 'Nevada', 'gravityforms' ) )                  => 'NV',
			GFCommon::safe_strtoupper( esc_html__( 'New Hampshire', 'gravityforms' ) )           => 'NH',
			GFCommon::safe_strtoupper( esc_html__( 'New Jersey', 'gravityforms' ) )              => 'NJ',
			GFCommon::safe_strtoupper( esc_html__( 'New Mexico', 'gravityforms' ) )              => 'NM',
			GFCommon::safe_strtoupper( esc_html__( 'New York', 'gravityforms' ) )                => 'NY',
			GFCommon::safe_strtoupper( esc_html__( 'North Carolina', 'gravityforms' ) )          => 'NC',
			GFCommon::safe_strtoupper( esc_html__( 'North Dakota', 'gravityforms' ) )            => 'ND',
			GFCommon::safe_strtoupper( esc_html__( 'Ohio', 'gravityforms' ) )                    => 'OH',
			GFCommon::safe_strtoupper( esc_html__( 'Oklahoma', 'gravityforms' ) )                => 'OK',
			GFCommon::safe_strtoupper( esc_html__( 'Oregon', 'gravityforms' ) )                  => 'OR',
			GFCommon::safe_strtoupper( esc_html__( 'Pennsylvania', 'gravityforms' ) )            => 'PA',
			GFCommon::safe_strtoupper( esc_html__( 'Rhode Island', 'gravityforms' ) )            => 'RI',
			GFCommon::safe_strtoupper( esc_html__( 'South Carolina', 'gravityforms' ) )          => 'SC',
			GFCommon::safe_strtoupper( esc_html__( 'South Dakota', 'gravityforms' ) )            => 'SD',
			GFCommon::safe_strtoupper( esc_html__( 'Tennessee', 'gravityforms' ) )               => 'TN',
			GFCommon::safe_strtoupper( esc_html__( 'Texas', 'gravityforms' ) )                   => 'TX',
			GFCommon::safe_strtoupper( esc_html__( 'Utah', 'gravityforms' ) )                    => 'UT',
			GFCommon::safe_strtoupper( esc_html__( 'Vermont', 'gravityforms' ) )                 => 'VT',
			GFCommon::safe_strtoupper( esc_html__( 'Virginia', 'gravityforms' ) )                => 'VA',
			GFCommon::safe_strtoupper( esc_html__( 'Washington', 'gravityforms' ) )              => 'WA',
			GFCommon::safe_strtoupper( esc_html__( 'West Virginia', 'gravityforms' ) )           => 'WV',
			GFCommon::safe_strtoupper( esc_html__( 'Wisconsin', 'gravityforms' ) )               => 'WI',
			GFCommon::safe_strtoupper( esc_html__( 'Wyoming', 'gravityforms' ) )                 => 'WY',
			GFCommon::safe_strtoupper( esc_html__( 'Armed Forces Americas', 'gravityforms' ) )   => 'AA',
			GFCommon::safe_strtoupper( esc_html__( 'Armed Forces Europe', 'gravityforms' ) )     => 'AE',
			GFCommon::safe_strtoupper( esc_html__( 'Armed Forces Pacific', 'gravityforms' ) )    => 'AP',
		);

		$state_name = GFCommon::safe_strtoupper( $state_name );
		$code       = isset( $states[ $state_name ] ) ? $states[ $state_name ] : $state_name;

		return $code;
	}

	public function get_canadian_provinces() {
		return array( esc_html__( 'Alberta', 'gravityforms' ), esc_html__( 'British Columbia', 'gravityforms' ), esc_html__( 'Manitoba', 'gravityforms' ), esc_html__( 'New Brunswick', 'gravityforms' ), esc_html__( 'Newfoundland & Labrador', 'gravityforms' ), esc_html__( 'Northwest Territories', 'gravityforms' ), esc_html__( 'Nova Scotia', 'gravityforms' ), esc_html__( 'Nunavut', 'gravityforms' ), esc_html__( 'Ontario', 'gravityforms' ), esc_html__( 'Prince Edward Island', 'gravityforms' ), esc_html__( 'Quebec', 'gravityforms' ), esc_html__( 'Saskatchewan', 'gravityforms' ), esc_html__( 'Yukon', 'gravityforms' ) );

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
			$selected = strtolower( $code ) == $selected_country ? "selected='selected'" : '';
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
}

GF_Fields::register( new GF_Field_Address() );
