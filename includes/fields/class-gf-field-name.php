<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Field_Name extends GF_Field {

	public $type = 'name';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Name', 'gravityforms' );
	}

	function validate( $value, $form ) {

		if ( $this->isRequired && $this->nameFormat != 'simple' ) {
			$first = rgpost( 'input_' . $this->id . '_3' );
			$last  = rgpost( 'input_' . $this->id . '_6' );
			if (   ( empty( $first ) && ! $this->get_input_property( '3', 'isHidden' ) )
				|| ( empty( $last )  && ! $this->get_input_property( '6', 'isHidden' ) ) ) {
				$this->failed_validation  = true;
				$this->validation_message = empty( $this->errorMessage ) ? esc_html__( 'This field is required. Please enter the first and last name.', 'gravityforms' ) : $this->errorMessage;
			}
		}
	}

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
			'name_setting',
			'rules_setting',
			'visibility_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	public function get_field_input( $form, $value = '', $entry = null ) {

		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$is_admin = $is_entry_detail || $is_form_editor;

		$form_id  = $form['id'];
		$id       = intval( $this->id );
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
		$form_id  = ( $is_entry_detail || $is_form_editor ) && empty( $form_id ) ? rgget( 'id' ) : $form_id;

		$size         = $this->size;
		$class_suffix = RG_CURRENT_VIEW == 'entry' ? '_admin' : '';
		$class        = $size . $class_suffix;

		$disabled_text = $is_form_editor ? "disabled='disabled'" : '';
		$class_suffix  = $is_entry_detail ? '_admin' : '';

		$form_sub_label_placement  = rgar( $form, 'subLabelPlacement' );
		$field_sub_label_placement = $this->subLabelPlacement;
		$is_sub_label_above        = $field_sub_label_placement == 'above' || ( empty( $field_sub_label_placement ) && $form_sub_label_placement == 'above' );
		$sub_label_class_attribute = $field_sub_label_placement == 'hidden_label' ? "class='hidden_sub_label'" : '';

		$prefix = '';
		$first  = '';
		$middle = '';
		$last   = '';
		$suffix = '';

		if ( is_array( $value ) ) {
			$prefix = esc_attr( RGForms::get( $this->id . '.2', $value ) );
			$first  = esc_attr( RGForms::get( $this->id . '.3', $value ) );
			$middle = esc_attr( RGForms::get( $this->id . '.4', $value ) );
			$last   = esc_attr( RGForms::get( $this->id . '.6', $value ) );
			$suffix = esc_attr( RGForms::get( $this->id . '.8', $value ) );
		}

		$prefix_input = GFFormsModel::get_input( $this, $this->id . '.2' );
		$first_input  = GFFormsModel::get_input( $this, $this->id . '.3' );
		$middle_input = GFFormsModel::get_input( $this, $this->id . '.4' );
		$last_input   = GFFormsModel::get_input( $this, $this->id . '.6' );
		$suffix_input = GFFormsModel::get_input( $this, $this->id . '.8' );

		$first_placeholder_attribute  = GFCommon::get_input_placeholder_attribute( $first_input );
		$middle_placeholder_attribute = GFCommon::get_input_placeholder_attribute( $middle_input );
		$last_placeholder_attribute   = GFCommon::get_input_placeholder_attribute( $last_input );
		$suffix_placeholder_attribute = GFCommon::get_input_placeholder_attribute( $suffix_input );

		switch ( $this->nameFormat ) {

			case 'advanced' :
			case 'extended' :
				$prefix_tabindex = GFCommon::get_tabindex();
				$first_tabindex  = GFCommon::get_tabindex();
				$middle_tabindex = GFCommon::get_tabindex();
				$last_tabindex   = GFCommon::get_tabindex();
				$suffix_tabindex = GFCommon::get_tabindex();

				$prefix_sub_label      = rgar( $prefix_input, 'customLabel' ) != '' ? $prefix_input['customLabel'] : apply_filters( "gform_name_prefix_{$form_id}", apply_filters( 'gform_name_prefix', esc_html__( 'Prefix', 'gravityforms' ), $form_id ), $form_id );
				$first_name_sub_label  = rgar( $first_input, 'customLabel' ) != '' ? $first_input['customLabel'] : apply_filters( "gform_name_first_{$form_id}", apply_filters( 'gform_name_first', esc_html__( 'First', 'gravityforms' ), $form_id ), $form_id );
				$middle_name_sub_label = rgar( $middle_input, 'customLabel' ) != '' ? $middle_input['customLabel'] : apply_filters( "gform_name_middle_{$form_id}", apply_filters( 'gform_name_middle', esc_html__( 'Middle', 'gravityforms' ), $form_id ), $form_id );
				$last_name_sub_label   = rgar( $last_input, 'customLabel' ) != '' ? $last_input['customLabel'] : apply_filters( "gform_name_last_{$form_id}", apply_filters( 'gform_name_last', esc_html__( 'Last', 'gravityforms' ), $form_id ), $form_id );
				$suffix_sub_label      = rgar( $suffix_input, 'customLabel' ) != '' ? $suffix_input['customLabel'] : apply_filters( "gform_name_suffix_{$form_id}", apply_filters( 'gform_name_suffix', esc_html__( 'Suffix', 'gravityforms' ), $form_id ), $form_id );

				$prefix_markup         = '';
				$first_markup          = '';
				$middle_markup         = '';
				$last_markup           = '';
				$suffix_markup         = '';
				if ( $is_sub_label_above ) {

					$style = ( $is_admin && rgar( $prefix_input, 'isHidden' ) ) ? "style='display:none;'" : '';
					if ( $is_admin || ! rgar( $prefix_input, 'isHidden' ) ) {
						$prefix_select_class = isset( $prefix_input['choices'] ) && is_array( $prefix_input['choices'] ) ? 'name_prefix_select' : '';
						$prefix_markup       = self::get_name_prefix_field( $prefix_input, $id, $field_id, $prefix, $disabled_text, $prefix_tabindex );
						$prefix_markup       = "<span id='{$field_id}_2_container' class='name_prefix {$prefix_select_class}' {$style}>
                                                    <label for='{$field_id}_2' {$sub_label_class_attribute}>{$prefix_sub_label}</label>
                                                    {$prefix_markup}
                                                  </span>";
					}

					$style = ( $is_admin && rgar( $first_input, 'isHidden' ) ) ? "style='display:none;'" : '';
					if ( $is_admin || ! rgar( $first_input, 'isHidden' ) ) {
						$first_markup = "<span id='{$field_id}_3_container' class='name_first' {$style}>
                                                    <label for='{$field_id}_3' {$sub_label_class_attribute}>{$first_name_sub_label}</label>
                                                    <input type='text' name='input_{$id}.3' id='{$field_id}_3' value='{$first}' {$first_tabindex} {$disabled_text} {$first_placeholder_attribute}/>
                                                </span>";
					}

					$style = ( $is_admin && ( ! isset( $middle_input['isHidden'] ) || rgar( $middle_input, 'isHidden' ) ) ) ? "style='display:none;'" : '';
					if ( $is_admin || ( isset( $middle_input['isHidden'] ) && $middle_input['isHidden'] == false ) ) {
						$middle_markup = "<span id='{$field_id}_4_container' class='name_middle' {$style}>
                                                    <label for='{$field_id}_4' {$sub_label_class_attribute}>{$middle_name_sub_label}</label>
                                                    <input type='text' name='input_{$id}.4' id='{$field_id}_4' value='{$middle}' {$middle_tabindex} {$disabled_text} {$middle_placeholder_attribute}/>
                                                </span>";
					}

					$style = ( $is_admin && rgar( $last_input, 'isHidden' ) ) ? "style='display:none;'" : '';
					if ( $is_admin || ! rgar( $last_input, 'isHidden' ) ) {
						$last_markup = "<span id='{$field_id}_6_container' class='name_last' {$style}>
                                                            <label for='{$field_id}_6' {$sub_label_class_attribute}>{$last_name_sub_label}</label>
                                                            <input type='text' name='input_{$id}.6' id='{$field_id}_6' value='{$last}' {$last_tabindex} {$disabled_text} {$last_placeholder_attribute}/>
                                                        </span>";
					}

					$style = ( $is_admin && rgar( $suffix_input, 'isHidden' ) ) ? "style='display:none;'" : '';
					if ( $is_admin || ! rgar( $suffix_input, 'isHidden' ) ) {
						$suffix_select_class = isset( $suffix_input['choices'] ) && is_array( $suffix_input['choices'] ) ? 'name_suffix_select' : '';
						$suffix_markup       = "<span id='{$field_id}_8_container' class='name_suffix {$suffix_select_class}' {$style}>
                                                        <label for='{$field_id}_8' {$sub_label_class_attribute}>{$suffix_sub_label}</label>
                                                        <input type='text' name='input_{$id}.8' id='{$field_id}_8' value='{$suffix}' {$suffix_tabindex} {$disabled_text} {$suffix_placeholder_attribute}/>
                                                    </span>";
					}
				} else {
					$style = ( $is_admin && rgar( $prefix_input, 'isHidden' ) ) ? "style='display:none;'" : '';
					if ( $is_admin || ! rgar( $prefix_input, 'isHidden' ) ) {
						$prefix_select_class = isset( $prefix_input['choices'] ) && is_array( $prefix_input['choices'] ) ? 'name_prefix_select' : '';
						$prefix_markup       = self::get_name_prefix_field( $prefix_input, $id, $field_id, $prefix, $disabled_text, $prefix_tabindex );
						$prefix_markup       = "<span id='{$field_id}_2_container' class='name_prefix {$prefix_select_class}' {$style}>
                                                    {$prefix_markup}
                                                    <label for='{$field_id}_2' {$sub_label_class_attribute}>{$prefix_sub_label}</label>
                                                  </span>";
					}

					$style = ( $is_admin && rgar( $first_input, 'isHidden' ) ) ? "style='display:none;'" : '';
					if ( $is_admin || ! rgar( $first_input, 'isHidden' ) ) {
						$first_markup = "<span id='{$field_id}_3_container' class='name_first' {$style}>
                                                    <input type='text' name='input_{$id}.3' id='{$field_id}_3' value='{$first}' {$first_tabindex} {$disabled_text} {$first_placeholder_attribute}/>
                                                    <label for='{$field_id}_3' {$sub_label_class_attribute}>{$first_name_sub_label}</label>
                                                </span>";
					}

					$style = ( $is_admin && ( ! isset( $middle_input['isHidden'] ) || rgar( $middle_input, 'isHidden' ) ) ) ? "style='display:none;'" : '';
					if ( $is_admin || ( isset( $middle_input['isHidden'] ) && $middle_input['isHidden'] == false ) ) {
						$middle_markup = "<span id='{$field_id}_4_container' class='name_middle' {$style}>
                                                    <input type='text' name='input_{$id}.4' id='{$field_id}_4' value='{$middle}' {$middle_tabindex} {$disabled_text} {$middle_placeholder_attribute}/>
                                                    <label for='{$field_id}_4' {$sub_label_class_attribute}>{$middle_name_sub_label}</label>
                                                </span>";
					}

					$style = ( $is_admin && rgar( $last_input, 'isHidden' ) ) ? "style='display:none;'" : '';
					if ( $is_admin || ! rgar( $last_input, 'isHidden' ) ) {
						$last_markup = "<span id='{$field_id}_6_container' class='name_last' {$style}>
                                                    <input type='text' name='input_{$id}.6' id='{$field_id}_6' value='{$last}' {$last_tabindex} {$disabled_text} {$last_placeholder_attribute}/>
                                                    <label for='{$field_id}_6' {$sub_label_class_attribute}>{$last_name_sub_label}</label>
                                                </span>";
					}

					$style = ( $is_admin && rgar( $suffix_input, 'isHidden' ) ) ? "style='display:none;'" : '';
					if ( $is_admin || ! rgar( $suffix_input, 'isHidden' ) ) {
						$suffix_select_class = isset( $suffix_input['choices'] ) && is_array( $suffix_input['choices'] ) ? 'name_suffix_select' : '';
						$suffix_markup       = "<span id='{$field_id}_8_container' class='name_suffix {$suffix_select_class}' {$style}>
                                                    <input type='text' name='input_{$id}.8' id='{$field_id}_8' value='{$suffix}' {$suffix_tabindex} {$disabled_text} {$suffix_placeholder_attribute}/>
                                                    <label for='{$field_id}_8' {$sub_label_class_attribute}>{$suffix_sub_label}</label>
                                                </span>";
					}
				}
				$css_class = $this->get_css_class();

				return "<div class='ginput_complex{$class_suffix} ginput_container {$css_class}' id='{$field_id}'>
                            {$prefix_markup}
                            {$first_markup}
                            {$middle_markup}
                            {$last_markup}
                            {$suffix_markup}
                        </div>";

			case 'simple' :
				$value                 = esc_attr( $value );
				$class                 = esc_attr( $class );
				$tabindex              = GFCommon::get_tabindex();
				$placeholder_attribute = GFCommon::get_field_placeholder_attribute( $this );

				return "<div class='ginput_container'>
                                    <input name='input_{$id}' id='{$field_id}' type='text' value='{$value}' class='{$class}' {$tabindex} {$disabled_text} {$placeholder_attribute}/>
                                </div>";

			default :
				$first_tabindex       = GFCommon::get_tabindex();
				$last_tabindex        = GFCommon::get_tabindex();
				$first_name_sub_label = rgar( $first_input, 'customLabel' ) != '' ? $first_input['customLabel'] : apply_filters( "gform_name_first_{$form_id}", apply_filters( 'gform_name_first', esc_html__( 'First', 'gravityforms' ), $form_id ), $form_id );
				$last_name_sub_label  = rgar( $last_input, 'customLabel' ) != '' ? $last_input['customLabel'] : apply_filters( "gform_name_last_{$form_id}", apply_filters( 'gform_name_last', esc_html__( 'Last', 'gravityforms' ), $form_id ), $form_id );
				if ( $is_sub_label_above ) {
					$first_markup = '';
					$style        = ( $is_admin && rgar( $first_input, 'isHidden' ) ) ? "style='display:none;'" : '';
					if ( $is_admin || ! rgar( $first_input, 'isHidden' ) ) {
						$first_markup = "<span id='{$field_id}_3_container' class='name_first' {$style}>
                                                    <label for='{$field_id}_3' {$sub_label_class_attribute}>{$first_name_sub_label}</label>
                                                    <input type='text' name='input_{$id}.3' id='{$field_id}_3' value='{$first}' {$first_tabindex} {$disabled_text} {$first_placeholder_attribute}/>
                                                </span>";
					}

					$last_markup = '';
					$style       = ( $is_admin && rgar( $last_input, 'isHidden' ) ) ? "style='display:none;'" : '';
					if ( $is_admin || ! rgar( $last_input, 'isHidden' ) ) {
						$last_markup = "<span id='{$field_id}_6_container' class='name_last' {$style}>
                                                <label for='{$field_id}_6' {$sub_label_class_attribute}>" . $last_name_sub_label . "</label>
                                                <input type='text' name='input_{$id}.6' id='{$field_id}_6' value='{$last}' {$last_tabindex} {$disabled_text} {$last_placeholder_attribute}/>
                                            </span>";
					}
				} else {
					$first_markup = '';
					$style        = ( $is_admin && rgar( $first_input, 'isHidden' ) ) ? "style='display:none;'" : '';
					if ( $is_admin || ! rgar( $first_input, 'isHidden' ) ) {
						$first_markup = "<span id='{$field_id}_3_container' class='name_first' {$style}>
                                                    <input type='text' name='input_{$id}.3' id='{$field_id}_3' value='{$first}' {$first_tabindex} {$disabled_text} {$first_placeholder_attribute}/>
                                                    <label for='{$field_id}_3' {$sub_label_class_attribute}>{$first_name_sub_label}</label>
                                               </span>";
					}

					$last_markup = '';
					$style       = ( $is_admin && rgar( $last_input, 'isHidden' ) ) ? "style='display:none;'" : '';
					if ( $is_admin || ! rgar( $last_input, 'isHidden' ) ) {
						$last_markup = "<span id='{$field_id}_6_container' class='name_last' {$style}>
                                                    <input type='text' name='input_{$id}.6' id='{$field_id}_6' value='{$last}' {$last_tabindex} {$disabled_text} {$last_placeholder_attribute}/>
                                                    <label for='{$field_id}_6' {$sub_label_class_attribute}>{$last_name_sub_label}</label>
                                                </span>";
					}
				}

				$css_class = $this->get_css_class();

				return "<div class='ginput_complex{$class_suffix} ginput_container {$css_class}' id='{$field_id}'>
                            {$first_markup}
                            {$last_markup}
                            <div class='gf_clear gf_clear_complex'></div>
                        </div>";
		}
	}

	public function get_css_class() {

		$prefix_input = GFFormsModel::get_input( $this, $this->id . '.2' );
		$first_input  = GFFormsModel::get_input( $this, $this->id . '.3' );
		$middle_input = GFFormsModel::get_input( $this, $this->id . '.4' );
		$last_input   = GFFormsModel::get_input( $this, $this->id . '.6' );
		$suffix_input = GFFormsModel::get_input( $this, $this->id . '.8' );

		$css_class = '';

		if ( $prefix_input && ! rgar( $prefix_input, 'isHidden' ) ) {
			$css_class .= 'has_prefix ';
		} else {
			$css_class .= 'no_prefix ';
		}

		if ( $first_input && ! rgar( $first_input, 'isHidden' ) ) {
			$css_class .= 'has_first_name ';
		} else {
			$css_class .= 'no_first_name ';
		}

		if ( $middle_input && ! rgar( $middle_input, 'isHidden' ) ) {
			$css_class .= 'has_middle_name ';
		} else {
			$css_class .= 'no_middle_name ';
		}

		if ( $last_input && ! rgar( $last_input, 'isHidden' ) ) {
			$css_class .= 'has_last_name ';
		} else {
			$css_class .= 'no_last_name ';
		}

		if ( $suffix_input && ! rgar( $suffix_input, 'isHidden' ) ) {
			$css_class .= 'has_suffix ';
		} else {
			$css_class .= 'no_suffix ';
		}

		return trim( $css_class );
	}

	public static function get_name_prefix_field( $input, $id, $field_id, $value, $disabled_text, $tabindex ) {

		if ( isset( $input['choices'] ) && is_array( $input['choices'] ) ) {
			$placeholder_value = GFCommon::get_input_placeholder_value( $input );
			$options           = "<option value=''>{$placeholder_value}</option>";
			$value_enabled     = rgar( $input, 'enableChoiceValue' );
			foreach ( $input['choices'] as $choice ) {
				$choice_value            = $value_enabled ? $choice['value'] : $choice['text'];
				$is_selected_by_default  = rgar( $choice, 'isSelected' );
				$is_this_choice_selected = empty( $value ) ? $is_selected_by_default : strtolower( $choice_value ) == strtolower( $value );
				$selected                = $is_this_choice_selected ? "selected='selecteed'" : '';
				$options .= "<option value='{$choice_value}' {$selected}>{$choice['text']}</option>";
			}
			$markup = "<select name='input_{$id}.2' id='{$field_id}_2' {$tabindex} {$disabled_text}>
                          {$options}
                      </select>";
		} else {
			$placeholder_attribute = GFCommon::get_input_placeholder_attribute( $input );

			$markup = "<input type='text' name='input_{$id}.2' id='{$field_id}_2' value='{$value}' {$tabindex} {$disabled_text} {$placeholder_attribute}/>";
		}

		return $markup;
	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		if ( is_array( $value ) ) {
			$prefix = trim( rgget( $this->id . '.2', $value ) );
			$first  = trim( rgget( $this->id . '.3', $value ) );
			$middle = trim( rgget( $this->id . '.4', $value ) );
			$last   = trim( rgget( $this->id . '.6', $value ) );
			$suffix = trim( rgget( $this->id . '.8', $value ) );

			$name = $prefix;
			$name .= ! empty( $name ) && ! empty( $first ) ? " $first" : $first;
			$name .= ! empty( $name ) && ! empty( $middle ) ? " $middle" : $middle;
			$name .= ! empty( $name ) && ! empty( $last ) ? " $last" : $last;
			$name .= ! empty( $name ) && ! empty( $suffix ) ? " $suffix" : $suffix;

			return $name;
		} else {
			return $value;
		}

	}

	public function get_input_property( $input_id, $property_name ) {
		$input = GFFormsModel::get_input( $this, $this->id . '.' . (string) $input_id );

		return rgar( $input, $property_name );
	}

	public function sanitize_settings() {
		parent::sanitize_settings();
		if ( is_array( $this->inputs ) ) {
			foreach ( $this->inputs as &$input ) {
				if ( isset ( $input['choices'] ) && is_array( $input['choices'] ) ) {
					$input['choices'] = $this->sanitize_settings_choices( $input['choices'] );
				}
			}
		}
	}
}

GF_Fields::register( new GF_Field_Name() );