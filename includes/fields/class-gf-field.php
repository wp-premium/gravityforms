<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Class GF_Field
 *
 * Note to third party developers:
 * GF_Field is still in a state of flux at the moment so we don’t recommend that you start using it just yet as a base for new fields in production environments.
 * Once it’s stable we’ll provide documentation and instructions on how to use it for your own projects.
 *
 */
class GF_Field extends stdClass implements ArrayAccess {

	// Suppress deprecation until all the add-ons have been updated
	const SUPPRESS_DEPRECATION_NOTICE = true;

	private static $deprecation_notice_fired = false;

	private $_is_entry_detail = null;

	public function __construct( $data = array() ) {
		if ( empty( $data ) ) {
			return;
		}
		foreach ( $data as $key => $value ) {
			$this->{$key} = $value;
		}
	}

	/*
	 * Fires the deprecation notice only once per page
	 */
	private function maybe_fire_array_access_deprecation_notice( $offset ) {
		if ( self::SUPPRESS_DEPRECATION_NOTICE ) {
			return;
		};

		if ( ! self::$deprecation_notice_fired ) {
			_deprecated_function( 'Array access to the field object is now deprecated. Further notices will be suppressed. Offset: ' . $offset, '1.9', 'the object operator e.g. $field->' . $offset );
			self::$deprecation_notice_fired = true;
		}
	}

	/**
	 * Handles array notation
	 *
	 * @param mixed $offset
	 *
	 * @return bool
	 */
	public function offsetExists( $offset ) {
		$this->maybe_fire_array_access_deprecation_notice( $offset );

		return isset( $this->$offset );
	}

	public function offsetGet( $offset ) {
		$this->maybe_fire_array_access_deprecation_notice( $offset );
		if ( ! isset( $this->$offset ) ) {
			$this->$offset = '';
		}

		return $this->$offset;
	}

	public function offsetSet( $offset, $data ) {
		$this->maybe_fire_array_access_deprecation_notice( $offset );
		if ( $offset === null ) {
			$this[] = $data;
		} else {
			$this->$offset = $data;
		}
	}

	public function offsetUnset( $offset ) {
		$this->maybe_fire_array_access_deprecation_notice( $offset );
		unset( $this->$offset );
	}

	public function __isset( $key ) {
		return isset( $this->$key );
	}

	public function __set( $key, $value ) {
		$this->$key = $value;
	}

	public function &__get( $key ) {
		if ( ! isset( $this->$key ) ) {
			$this->$key = '';
		}

		return $this->$key;
	}

	public function __unset( $key ) {
		unset( $this->$key );
	}

	public function is_conditional_logic_supported() {
		return false;
	}

	public function get_value_default_if_empty( $value ) {

		if ( ! GFCommon::is_empty_array( $value ) ) {
			return $value;
		}

		return $this->get_value_default();
	}

	public function get_value_default() {

		if ( is_array( $this->inputs ) ) {
			$value = array();
			foreach ( $this->inputs as $input ) {
				$value[ strval( $input['id'] ) ] = $this->is_form_editor() ? rgar( $input, 'defaultValue' ) : GFCommon::replace_variables_prepopulate( rgar( $input, 'defaultValue' ) );
			}
		} else {
			$value = $this->is_form_editor() ? $this->defaultValue : GFCommon::replace_variables_prepopulate( $this->defaultValue );
		}

		return $value;
	}

	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		return $value;
	}

	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		return esc_html( $value );
	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		if ( ! is_array( $value ) && $format == 'html' ) {
			$value = nl2br( $value );
		}

		return $value;
	}

	public function is_description_above( $form ) {
		$form_label_placement        = rgar( $form, 'labelPlacement' );
		$field_label_placement       = $this->labelPlacement;
		$form_description_placement  = rgar( $form, 'descriptionPlacement' );
		$field_description_placement = $this->descriptionPlacement;
		if ( empty( $field_description_placement ) ) {
			$field_description_placement = $form_description_placement;
		}
		$is_description_above = $field_description_placement == 'above' && ( $field_label_placement == 'top_label' || $field_label_placement == 'hidden_label' || ( empty( $field_label_placement ) && $form_label_placement == 'top_label' ) );

		return $is_description_above;
	}

	public function get_description( $description, $css_class ) {
		$is_form_editor  = $this->is_form_editor();
		$is_entry_detail = $this->is_entry_detail();
		$is_admin        = $is_form_editor || $is_entry_detail;

		return $is_admin || ! empty( $description ) ? "<div class='$css_class'>" . $description . '</div>' : '';
	}

	public function get_field_content( $value, $force_frontend_label, $form ) {

		$field_label = $this->get_field_label( $force_frontend_label, $value );

		$validation_message = ( $this->failed_validation && ! empty( $this->validation_message ) ) ? sprintf( "<div class='gfield_description validation_message'>%s</div>", $this->validation_message ) : '';

		$is_form_editor  = $this->is_form_editor();
		$is_entry_detail = $this->is_entry_detail();
		$is_admin        = $is_form_editor || $is_entry_detail;

		$required_div = $is_admin || $this->isRequired ? sprintf( "<span class='gfield_required'>%s</span>", $this->isRequired ? '*' : '' ) : '';

		$admin_buttons = $this->get_admin_buttons();

		$target_input_id = $this->get_first_input_id( $form );

		$for_attribute = empty( $target_input_id ) ? '' : "for='{$target_input_id}'";

		$description = $this->get_description( $this->description, 'gfield_description' );
		if ( $this->is_description_above( $form ) ) {
			$clear         = $is_admin ? "<div class='gf_clear'></div>" : '';
			$field_content = sprintf( "%s<label class='gfield_label' $for_attribute >%s%s</label>%s{FIELD}%s$clear", $admin_buttons, esc_html( $field_label ), $required_div, $description, $validation_message );
		} else {
			$field_content = sprintf( "%s<label class='gfield_label' $for_attribute >%s%s</label>{FIELD}%s%s", $admin_buttons, esc_html( $field_label ), $required_div, $description, $validation_message );
		}

		return $field_content;
	}

	public function get_first_input_id( $form ) {
		$form_id = $form['id'];

		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$field_id        = $is_entry_detail || $is_form_editor || $form_id == 0 ? 'input_' : "input_{$form_id}_";

		if ( is_array( $this->inputs ) ) {
			foreach ( $this->inputs as $input ) {
				if ( ! isset( $input['isHidden'] ) || ! $input['isHidden'] ) {
					$field_id .= str_replace( '.', '_', $input['id'] );
					break;
				}
			}
		} else {
			$field_id .= $this->id;
		}

		return $field_id;
	}

	public function get_field_label( $force_frontend_label, $value ) {
		$field_label = $force_frontend_label ? $this->label : GFCommon::get_label( $this );
		if ( ( $this->inputType == 'singleproduct' || $this->inputType == 'calculation' ) && ! rgempty( $this->id . '.1', $value ) ) {
			$field_label = rgar( $value, $this->id . '.1' );
		}

		return $field_label;
	}

	public function get_admin_buttons() {
		$duplicate_disabled   = array(
			'captcha',
			'post_title',
			'post_content',
			'post_excerpt',
			'total',
			'shipping',
			'creditcard'
		);
		$duplicate_field_link = ! in_array( $this->type, $duplicate_disabled ) ? "<a class='field_duplicate_icon' id='gfield_duplicate_{$this->id}' title='" . esc_attr__( 'click to duplicate this field', 'gravityforms' ) . "' href='#' onclick='StartDuplicateField(this); return false;'><i class='fa fa-files-o fa-lg'></i></a>" : '';

		/**
		 * This filter allows for modification of the form field duplicate link. This will change the link for all fields
		 *
		 * @param string $duplicate_field_link The Duplicate Field Link (in HTML)
		 */
		$duplicate_field_link = apply_filters( 'gform_duplicate_field_link', $duplicate_field_link );

		$delete_field_link = "<a class='field_delete_icon' id='gfield_delete_{$this->id}' title='" . esc_attr__( 'click to delete this field', 'gravityforms' ) . "' href='#' onclick='StartDeleteField(this); return false;'><i class='fa fa-times fa-lg'></i></a>";

		/**
		 * This filter allows for modification of a form field delete link. This will change the link for all fields
		 *
		 * @param string $delete_field_link The Delete Field Link (in HTML)
		 */
		$delete_field_link = apply_filters( 'gform_delete_field_link', $delete_field_link );
		$field_type_title  = esc_html( GFCommon::get_field_type_title( $this->type ) );

		$is_form_editor  = $this->is_form_editor();
		$is_entry_detail = $this->is_entry_detail();
		$is_admin        = $is_form_editor || $is_entry_detail;

		$admin_buttons = $is_admin ? "<div class='gfield_admin_icons'><div class='gfield_admin_header_title'>{$field_type_title} : " . esc_html__( 'Field ID', 'gravityforms' ) . " {$this->id}</div>" . $delete_field_link . $duplicate_field_link . "<a class='field_edit_icon edit_icon_collapsed' title='" . esc_attr__( 'click to expand and edit the options for this field', 'gravityforms' ) . "'><i class='fa fa-caret-down fa-lg'></i></a></div>" : '';

		return $admin_buttons;
	}

	public function get_field_input( $form, $value = '', $entry = null ) {
		return '';
	}

	public function validate( $value, $form ) {
		//
	}

	public function get_entry_inputs() {
		return $this->inputs;
	}

	public function get_input_value_submission( $standard_name, $custom_name = '', $field_values = array(), $get_from_post_global_var = true ) {

		$form_id = $this->formId;
		if ( ! empty( $_POST[ 'is_submit_' . $form_id ] ) && $get_from_post_global_var ) {
			$value = rgpost( $standard_name );
			$value = GFFormsModel::maybe_trim_input( $value, $form_id, $this );

			return $value;
		} elseif ( $this->allowsPrepopulate ) {
			return GFFormsModel::get_parameter_value( $custom_name, $field_values, $this );
		}

	}

	/**
	 * Used to determine the required validation result.
	 *
	 * @param int $form_id
	 *
	 * @return bool
	 */
	public function is_value_submission_empty( $form_id ) {

		$copy_values_option_activated = $this->enableCopyValuesOption && rgpost( 'input_' . $this->id . '_copy_values_activated' );

		if ( is_array( $this->inputs ) ) {
			foreach ( $this->inputs as $input ) {

				if ( $copy_values_option_activated ) {
					$input_id          = $input['id'];
					$input_name        = 'input_' . str_replace( '.', '_', $input_id );
					$source_field_id   = $this->copyValuesOptionField;
					$source_input_name = str_replace( 'input_' . $this->id, 'input_' . $source_field_id, $input_name );
					$value             = rgpost( $source_input_name );
				} else {
					$value = rgpost( 'input_' . str_replace( '.', '_', $input['id'] ) );
				}

				if ( is_array( $value ) && ! empty( $value ) ) {
					return false;
				}

				if ( ! is_array( $value ) && strlen( trim( $value ) ) > 0 ) {
					return false;
				}
			}

			return true;
		} else {
			if ( $copy_values_option_activated ) {
				$value = rgpost( 'input_' . $this->copyValuesOptionField );
			} else {
				$value = rgpost( 'input_' . $this->id );
			}

			if ( is_array( $value ) ) {
				//empty if any of the inputs are empty (for inputs with the same name)
				foreach ( $value as $input ) {
					if ( strlen( trim( $input ) ) <= 0 ) {
						return true;
					}
				}

				return false;
			} elseif ( $this->enablePrice ) {
				list( $label, $price ) = explode( '|', $value );
				$is_empty = ( strlen( trim( $price ) ) <= 0 );

				return $is_empty;
			} else {
				$is_empty = ( strlen( trim( $value ) ) <= 0 ) || ( $this->type == 'post_category' && $value < 0 );

				return $is_empty;
			}
		}
	}

	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {

		$inputs = $this->get_entry_inputs();

		if ( is_array( $inputs ) ) {
			$value = array();
			foreach ( $inputs as $input ) {
				$value[ strval( $input['id'] ) ] = $this->get_input_value_submission( 'input_' . str_replace( '.', '_', strval( $input['id'] ) ), RGForms::get( 'name', $input ), $field_values, $get_from_post_global_var );;
			}
		} else {
			$value = $this->get_input_value_submission( 'input_' . $this->id, $this->inputName, $field_values, $get_from_post_global_var );
		}

		return $value;
	}

	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {

		// only sanitize non-array based values
		if ( ! is_array( $value ) ) {

			$value = $this->sanitize_entry_value( $value, $form['id'] );

		}

		return $value;
	}


	public function has_calculation() {

		$type = $this->get_input_type();

		if ( $type == 'number' ) {
			return $this->enableCalculation && $this->calculationFormula;
		}

		return $type == 'calculation';
	}

	public function get_conditional_logic_event( $event ) {
		if ( empty( $this->conditionalLogicFields ) || $this->is_entry_detail() || $this->is_form_editor() ) {
			return '';
		}

		switch ( $event ) {
			case 'keyup' :
				return "onchange='gf_apply_rules(" . $this->formId . ',' . GFCommon::json_encode( $this->conditionalLogicFields ) . ");' onkeyup='clearTimeout(__gf_timeout_handle); __gf_timeout_handle = setTimeout(\"gf_apply_rules(" . $this->formId . ',' . GFCommon::json_encode( $this->conditionalLogicFields ) . ")\", 300);'";
				break;

			case 'click' :
				return "onclick='gf_apply_rules(" . $this->formId . ',' . GFCommon::json_encode( $this->conditionalLogicFields ) . ");'";
				break;

			case 'change' :
				return "onchange='gf_apply_rules(" . $this->formId . ',' . GFCommon::json_encode( $this->conditionalLogicFields ) . ");'";
				break;
		}
	}

	public function register_form_init_scripts( $form ) {
		GFFormDisplay::add_init_script( $form['id'], $this->type . '_' . $this->id, GFFormDisplay::ON_PAGE_RENDER, $this->get_form_inline_script_on_page_render( $form ) );
	}

	public function get_form_inline_script_on_page_render( $form ) {
		return '';
	}

	public function get_field_placeholder_attribute() {

		$placeholder_value = GFCommon::replace_variables_prepopulate( $this->placeholder );

		return ! empty( $placeholder_value ) ? sprintf( "placeholder='%s'", esc_attr( $placeholder_value ) ) : '';
	}

	public function get_input_placeholder_attribute( $input ) {

		$placeholder_value = GFCommon::get_input_placeholder_value( $input );

		return ! empty( $placeholder_value ) ? sprintf( "placeholder='%s'", esc_attr( $placeholder_value ) ) : '';
	}

	public function get_input_placeholder_value( $input ) {

		$placeholder = rgar( $input, 'placeholder' );

		return empty( $placeholder ) ? '' : GFCommon::replace_variables_prepopulate( $placeholder );
	}

	public function add_button( $field_groups ) {

		// Check a button for the type hasn't already been added
		foreach ( $field_groups as $group ) {
			foreach ( $group['fields'] as $button ) {
				if ( isset( $button['data-type'] ) && $button['data-type'] == $this->type ) {
					return $field_groups;
				}
			}
		}


		$new_button = $this->get_form_editor_button();
		if ( ! empty( $new_button ) ) {
			foreach ( $field_groups as &$group ) {
				if ( $group['name'] == $new_button['group'] ) {
					$group['fields'][] = array(
						'class'     => 'button',
						'value'     => $new_button['text'],
						'data-type' => $this->type,
						'onclick'   => "StartAddField('{$this->type}');",
					);
					break;
				}
			}
		}

		return $field_groups;
	}

	/**
	 * Returns the button for the form editor. The array contains two elements:
	 * 'group' => 'standard_fields' // or  'advanced_fields', 'post_fields', 'pricing_fields'
	 * 'text'  => 'Button text'
	 *
	 * Built-in fields don't need to implement this because the buttons are added in sequence in GFFormDetail
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'standard_fields',
			'text'  => $this->get_form_editor_field_title()
		);
	}

	public function get_form_editor_field_title() {
		return $this->type;
	}

	public function get_form_editor_field_settings() {
		return array();
	}

	public function get_form_editor_inline_script_on_page_render() {
		return '';
	}

	public function get_tabindex() {
		return GFCommon::$tab_index > 0 ? "tabindex='" . GFCommon::$tab_index ++ . "'" : '';
	}

	public function is_form_editor() {
		return GFCommon::is_form_editor();
	}

	public function is_entry_detail() {
		return isset( $this->_is_entry_detail ) ? (bool) $this->_is_entry_detail : GFCommon::is_entry_detail();
	}

	public function is_entry_detail_edit() {
		return GFCommon::is_entry_detail_edit();
	}

	public function strip_script_tag( $string ) {
		$allowable_tags = '<a><abbr><acronym><address><area><area /><b><base><base /><bdo><big><blockquote><body><br><br /><button><caption><cite><code><col><col /><colgroup><command><command /><dd><del><dfn><div><dl><DOCTYPE><dt><em><fieldset><form><h1><h2><h3><h4><h5><h6><head><html><hr><hr /><i><img><img /><input><input /><ins><kbd><label><legend><li><link><map><meta><meta /><noscript><ol><optgroup><option><p><param><param /><pre><q><samp><select><small><span><strong><style><sub><sup><table><tbody><td><textarea><tfoot><th><thead><title><tr><tt><ul><var><wbr><wbr />';

		$string = strip_tags( $string, $allowable_tags );

		return $string;
	}

	public function allow_html() {

		return false;

	}

	/**
	 * @param $value
	 * @param $form_id
	 *
	 * @return mixed|string
	 */
	public function sanitize_entry_value( $value, $form_id ) {

		//allow HTML for certain field types
		$allow_html = $this->allow_html();

		$allowable_tags = gf_apply_filters( 'gform_allowable_tags', $form_id, $allow_html, $this, $form_id );


		if ( $allowable_tags !== true ) {
			$value = strip_tags( $value, $allowable_tags );

			return $value;
		} else {
			//removing script tags from value if $allowable_tags variable is not specified.
			$value = $this->strip_script_tag( $value );

			return $value;
		}
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
		$this->id     = absint( $this->id );
		$this->type   = wp_strip_all_tags( $this->type );
		$this->formId = absint( $this->formId );

		$allowed_tags      = wp_kses_allowed_html( 'post' );
		$this->label       = wp_kses( $this->label, $allowed_tags );
		$this->adminLabel  = wp_kses( $this->adminLabel, $allowed_tags );
		$this->description = wp_kses( $this->description, $allowed_tags );

		$this->isRequired = (bool) $this->isRequired;

		$this->allowsPrepopulate = (bool) $this->allowsPrepopulate;

		$this->inputMask = (bool) $this->inputMask;
		$this->inputMaskValue = wp_strip_all_tags( $this->inputMaskValue );

		if ( $this->inputType ) {
			$this->inputType = wp_strip_all_tags( $this->inputType );
		}

		if ( $this->size ) {
			$this->size = wp_strip_all_tags( $this->size );
		}

		if ( $this->errorMessage ) {
			$this->errorMessage = sanitize_text_field( $this->errorMessage );
		}

		if ( $this->labelPlacement ) {
			$this->labelPlacement = wp_strip_all_tags( $this->labelPlacement );
		}

		if ( $this->descriptionPlacement ) {
			$this->descriptionPlacement = wp_strip_all_tags( $this->descriptionPlacement );
		}

		if ( $this->subLabelPlacement ) {
			$this->subLabelPlacement = wp_strip_all_tags( $this->subLabelPlacement );
		}

		if ( $this->placeholder ) {
			$this->placeholder = sanitize_text_field( $this->placeholder );
		}

		if ( $this->cssClass ) {
			$this->cssClass = wp_strip_all_tags( $this->cssClass );
		}

		if ( $this->inputName ) {
			$this->inputName = wp_strip_all_tags( $this->inputName );
		}

		$this->adminOnly = (bool) $this->adminOnly;

		$this->noDuplicates = (bool) $this->noDuplicates;

		if ( $this->defaultValue ) {
			$this->defaultValue = wp_kses( $this->defaultValue, $allowed_tags );
		}

		if ( is_array( $this->inputs ) ) {
			foreach ( $this->inputs as &$input ) {
				if ( isset ( $input['id'] ) ) {
					$input['id'] = wp_strip_all_tags( $input['id'] );
				}
				if ( isset ( $input['customLabel'] ) ) {
					$input['customLabel'] = wp_kses( $input['customLabel'], $allowed_tags );
				}
				if ( isset ( $input['label'] ) ) {
					$input['label'] = wp_kses( $input['label'], $allowed_tags );
				}
				if ( isset ( $input['name'] ) ) {
					$input['name'] = wp_strip_all_tags( $input['name'] );
				}

				if ( isset ( $input['placeholder'] ) ) {
					$input['placeholder'] = sanitize_text_field( $input['placeholder'] );
				}

				if ( isset ( $input['defaultValue'] ) ) {
					$input['defaultValue'] = wp_strip_all_tags( $input['defaultValue'] );
				}
			}
		}

		$this->sanitize_settings_choices();
		$this->sanitize_settings_conditional_logic();

	}

	public function sanitize_settings_choices( $choices = null ) {

		if ( is_null( $choices ) ) {
			$choices = &$this->choices;
		}

		if ( ! is_array( $choices ) ) {
			return $choices;
		}

		$allowed_tags = wp_kses_allowed_html( 'post' );
		foreach ( $choices as &$choice ) {
			if ( isset ( $choice['isSelected'] ) ) {
				$choice['isSelected'] = (bool) $choice['isSelected'];
			}

			if ( isset ( $choice['price'] ) && ! empty( $choice['price'] ) ) {
				$price_number = GFCommon::to_number( $choice['price'] );
				$choice['price'] = GFCommon::to_money( $price_number );
			}

			if ( isset ( $choice['text'] ) ) {
				$choice['text'] = wp_kses( $choice['text'], $allowed_tags );
			}

			if ( isset ( $choice['value'] ) ) {
				$choice['value'] = wp_kses( $choice['value'], $allowed_tags );
			}
		}

		return $choices;
	}

	public function sanitize_settings_conditional_logic( $logic = null ) {

		if ( is_null( $logic ) ) {
			$logic = &$this->conditionalLogic;
		}
		$logic = GFFormsModel::sanitize_conditional_logic( $logic );
		return $logic;
	}

	public function get_input_type() {

		return empty( $this->inputType ) ? $this->type : $this->inputType;
	}

	/**
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

		return rgar( $entry, $input_id );
	}
}
