<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_Checkbox extends GF_Field {

	public $type = 'checkbox';

	public function get_form_editor_field_title() {
		return __( 'Checkboxes', 'gravityforms' );
	}

	public function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'choices_setting',
			'rules_setting',
			'visibility_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	public function is_conditional_logic_supported() {
		return true;
	}

	public function get_field_input( $form, $value = '', $entry = null ) {

		$form_id         = $form['id'];
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$id            = $this->id;
		$field_id      = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
		$disabled_text = $is_form_editor ? 'disabled="disabled"' : '';

		return sprintf( "<div class='ginput_container'><ul class='gfield_checkbox' id='%s'>%s</ul></div>", $field_id, $this->get_checkbox_choices( $value, $disabled_text, $form_id ) );
	}

	public function get_first_input_id( $form ) {
		return '';
	}

	public function get_value_default() {
		return $this->is_form_editor() ? $this->defaultValue : GFCommon::replace_variables_prepopulate( $this->defaultValue );
	}

	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {
		$parameter_values = GFFormsModel::get_parameter_value( $this->inputName, $field_values, $this );
		if ( ! empty( $parameter_values ) && ! is_array( $parameter_values ) ) {
			$parameter_values = explode( ',', $parameter_values );
		}

		if ( ! is_array( $this->inputs ) ) {
			return '';
		}

		$choice_index = 0;
		$value = array();
		foreach ( $this->inputs as $input ) {
			if ( ! empty( $_POST[ 'is_submit_' . $this->formId ] ) && $get_from_post_global_var ) {
				$value[ strval( $input['id'] ) ] = rgpost( 'input_' . str_replace( '.', '_', strval( $input['id'] ) ) );
			} else {
				if ( is_array( $parameter_values ) ) {
					foreach ( $parameter_values as $item ) {
						$item = trim( $item );
						if ( GFFormsModel::choice_value_match( $this, $this->choices[ $choice_index ], $item ) ) {
							$value[ $input['id'] . '' ] = $item;
							break;
						}
					}
				}
			}
			$choice_index ++;
		}
		return $value;
	}

	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		//if this is the main checkbox field (not an input), display a comma separated list of all inputs
		if ( absint( $field_id ) == $field_id ) {
			$lead_field_keys = array_keys( $entry );
			$items           = array();
			foreach ( $lead_field_keys as $input_id ) {
				if ( is_numeric( $input_id ) && absint( $input_id ) == $field_id ) {
					$items[] = GFCommon::selection_display( rgar( $entry, $input_id ), null, $entry['currency'], false );
				}
			}
			$value = GFCommon::implode_non_blank( ', ', $items );

			// special case for post category checkbox fields
			if ( $this->type == 'post_category' ) {
				$value = GFCommon::prepare_post_category_value( $value, $this, 'entry_list' );
			}
		} else {
			$value = '';

			if ( GFFormsModel::is_checkbox_checked( $field_id, $columns[ $field_id ]['label'], $entry, $form ) ) {
				$value = "<i class='fa fa-check gf_valid'></i>";
			}
		}
		return $value;
	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		if ( is_array( $value ) ) {

			$items = '';

			foreach ( $value as $key => $item ) {
				if ( ! empty( $item ) ) {
					switch ( $format ) {
						case 'text' :
							$items .= GFCommon::selection_display( $item, $this, $currency, $use_text ) . ', ';
							break;

						default:
							$items .= '<li>' . GFCommon::selection_display( $item, $this, $currency, $use_text ) . '</li>';
							break;
					}
				}
			}
			if ( empty( $items ) ) {
				return '';
			} elseif ( $format == 'text' ) {
				return substr( $items, 0, strlen( $items ) - 2 ); //removing last comma
			} else {
				return "<ul class='bulleted'>$items</ul>";
			}
		} else {
			return $value;
		}

	}

	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		$use_value       = $modifier == 'value';
		$use_price       = in_array( $modifier, array( 'price', 'currency' ) );
		$format_currency = $modifier == 'currency';

		if ( is_array( $raw_value ) && (string) intval( $input_id ) != $input_id ) {
			$items = array( $input_id => $value ); //float input Ids. (i.e. 4.1 ). Used when targeting specific checkbox items
		} elseif ( is_array( $raw_value ) ) {
			$items = $raw_value;
		} else {
			$items = array( $input_id => $raw_value );
		}

		$ary = array();

		foreach ( $items as $input_id => $item ) {
			if ( $use_value ) {
				list( $val, $price ) = rgexplode( '|', $item, 2 );
			} elseif ( $use_price ) {
				list( $name, $val ) = rgexplode( '|', $item, 2 );
				if ( $format_currency ) {
					$val = GFCommon::to_money( $val, rgar( $entry, 'currency' ) );
				}
			} elseif ( $this->type == 'post_category' ) {
				$use_id     = strtolower( $modifier ) == 'id';
				$item_value = GFCommon::format_post_category( $item, $use_id );

				$val = RGFormsModel::is_field_hidden( $form, $this, array(), $entry ) ? '' : $item_value;
			} else {
				$val = RGFormsModel::is_field_hidden( $form, $this, array(), $entry ) ? '' : RGFormsModel::get_choice_text( $this, $raw_value, $input_id );
			}

			$ary[] = GFCommon::format_variable_value( $val, $url_encode, $esc_html, $format );
		}

		return GFCommon::implode_non_blank( ', ', $ary );
	}

	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {

		if ( empty( $value ) ){
			return '';
		} elseif ( is_array( $value ) ){
			return implode( ',', $value );
		} else {
			return $this->sanitize_entry_value( $value, $form['id'] );
		}
	}

	public function get_checkbox_choices( $value, $disabled_text, $form_id = 0 ) {
		$choices = '';
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		if ( is_array( $this->choices ) ) {
			$choice_number = 1;
			$count         = 1;
			foreach ( $this->choices as $choice ) {
				if ( $choice_number % 10 == 0 ) { //hack to skip numbers ending in 0. so that 5.1 doesn't conflict with 5.10
					$choice_number ++;
				}

				$input_id = $this->id . '.' . $choice_number;

				if ( $is_entry_detail || $is_form_editor || $form_id == 0 ){
					$id = $this->id . '_' . $choice_number ++;
				} else {
					$id = $form_id . '_' . $this->id . '_' . $choice_number ++;
				}

				if ( ! isset( $_GET['gf_token'] ) && empty( $_POST ) && rgar( $choice, 'isSelected' ) ) {
					$checked = "checked='checked'";
				} elseif ( is_array( $value ) && RGFormsModel::choice_value_match( $this, $choice, rgget( $input_id, $value ) ) ) {
					$checked = "checked='checked'";
				} elseif ( ! is_array( $value ) && RGFormsModel::choice_value_match( $this, $choice, $value ) ) {
					$checked = "checked='checked'";
				} else {
					$checked = '';
				}

				$logic_event = $this->get_conditional_logic_event( 'click' );

				$tabindex     = $this->get_tabindex();
				$choice_value = $choice['value'];
				if ( $this->enablePrice ) {
					$price = rgempty( 'price', $choice ) ? 0 : GFCommon::to_number( rgar( $choice, 'price' ) );
					$choice_value .= '|' . $price;
				}
				$choice_value  = esc_attr( $choice_value );
				$choice_markup = "<li class='gchoice_{$id}'>
								<input name='input_{$input_id}' type='checkbox' $logic_event value='{$choice_value}' {$checked} id='choice_{$id}' {$tabindex} {$disabled_text} />
								<label for='choice_{$id}' id='label_{$id}'>{$choice['text']}</label>
							</li>";

				$choices .= apply_filters( 'gform_field_choice_markup_pre_render_' . $this->formId, apply_filters( 'gform_field_choice_markup_pre_render', $choice_markup, $choice, $this, $value ), $choice, $this, $value );

				$is_entry_detail = $this->is_entry_detail();
				$is_form_editor  = $this->is_form_editor();
				$is_admin = $is_entry_detail || $is_form_editor;

				if ( $is_admin && RG_CURRENT_VIEW != 'entry' && $count >= 5 ) {
					break;
				}

				$count ++;
			}

			$total = sizeof( $this->choices );
			if ( $count < $total ) {
				$choices .= "<li class='gchoice_total'>" . sprintf( __( '%d of %d items shown. Edit field to view all', 'gravityforms' ), $count, $total ) . '</li>';
			}
		}

		return apply_filters( 'gform_field_choices_' . $this->formId, apply_filters( 'gform_field_choices', $choices, $this ), $this );

	}

	public function allow_html() {
		return true;
	}

	public function sanitize_settings() {
		parent::sanitize_settings();
		if ( $this->type === 'option' ) {
			$this->productField = absint( $this->productField );
		}

		if ( $this->type === 'post_category' ) {
			$this->displayAllCategories = (bool) $this->displayAllCategories;
		}
	}

}

GF_Fields::register( new GF_Field_Checkbox() );