<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * The Repeater field.
 *
 * 2.4
 *
 * Class GF_Field_Repeater
 */
class GF_Field_Repeater extends GF_Field {

	public $type = 'repeater';

	/**
	 * Returns the field title for the form editor.
	 *
	 * @since 2.4
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Repeater', 'gravityforms' );
	}

	/**
	 * Returns the field settings for the form editor.
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'rules_setting',
			'visibility_setting',
			'description_setting',
			'css_class_setting',
		);
	}

	public function get_form_editor_button() {
		return array();
	}

	/**
	 * Used to determine the required validation result.
	 *
	 * @since 2.4
	 *
	 * @param int $form_id The ID of the form currently being processed.
	 *
	 * @return bool
	 */
	public function is_value_submission_empty( $form_id ) {
		return false;
	}

	/**
	 * Validates each sub-field.
	 *
	 * @since 2.4
	 *
	 * @param string|array $items The field values from get_value_submission().
	 * @param array        $form  The Form Object currently being processed.
	 */
	public function validate( $items, $form ) {

		if ( empty( $items ) ) {
			return;
		}

		/* @var GF_Field[] $fields */
		$fields = $this->fields;

		foreach ( $items as $i => $item ) {
			foreach ( $fields as $field ) {

				$field->set_context_property( 'itemIndex', $i );

				$inputs = $field->get_entry_inputs();
				if ( is_array( $inputs ) ) {
					$field_value = array();
					$field_keys  = array_keys( $item );
					foreach ( $field_keys as $input_id ) {
						if ( is_numeric( $input_id ) && absint( $input_id ) == absint( $field->id ) ) {
							$field_value[ $input_id ] = $item[ $input_id ];
						}
					}
				} else {
					$field_value = isset( $item[ $field->id ] ) ? $item[ $field->id ] : '';
				}

				if ( $field->isRequired && $field->is_value_empty( $field_value ) ) {
					$field->failed_validation = true;
				} else {
					$field->validate( $field_value, $form );
				}

				$custom_validation_result = gf_apply_filters( array( 'gform_field_validation', $form['id'], $field->id ), array(
					'is_valid' => $field->failed_validation ? false : true,
					'message'  => $field->validation_message
				), $field_value, $form, $field );
				$this->failed_validation  = rgar( $custom_validation_result, 'is_valid' ) ? false : true;

				// Reset the field validation and item index.
				$field->failed_validation = false;
				$field->set_context_property( 'itemIndex', null );

				if ( $this->failed_validation ) {
					// One field has failed validation so the entire repeater fails.
					return;
				}

			}
		}
	}

	/**
	 * Retrieve the field value on submission.
	 *
	 * @since 2.4
	 *
	 * @param array     $field_values             The dynamic population parameter names with their corresponding values to be populated.
	 * @param bool|true $get_from_post_global_var Whether to get the value from the $_POST array as opposed to $field_values.
	 *
	 * @return array|string
	 */
	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {

		$submission_values = $this->get_value_submission_recursive( $field_values, $get_from_post_global_var );

		$items = $this->hydrate( $submission_values );
		return $items[ $this->id ];
	}

	/**
	 * Returns the submission values for the repeater.
	 *
	 * @since 2.4
	 *
	 * @param array $field_values
	 * @param bool $get_from_post_global_var
	 *
	 * @return array
	 */
	public function get_value_submission_recursive( $field_values, $get_from_post_global_var ) {

		$items = array();

		if ( isset( $this->fields ) && is_array( $this->fields ) ) {

			foreach ( $this->fields as $sub_field ) {
				/* @var GF_Field $sub_field */
				if ( isset( $sub_field->fields ) && is_array( $sub_field->fields ) ) {
					/* @var GF_Field_Repeater_Table $sub_field */
					$field_items = $sub_field->get_value_submission_recursive( $field_values, $get_from_post_global_var );
				} else {
					$values = $sub_field->get_value_submission( $field_values, $get_from_post_global_var );

					if ( is_array( $sub_field->get_entry_inputs() ) ) {
						$prefix = '';
					} else {
						$prefix = $sub_field->id . '_';
					}

					$field_items = $this->flatten( $values, $prefix, $sub_field->is_value_submission_array() );
				}
				$items = array_merge( $items, $field_items );
			}
		} else {
			$values      = $this->get_value_submission( $field_values, $get_from_post_global_var );
			$field_items = $this->flatten( $values, $this->id . '_', $this->is_value_submission_array() );
			$items       = array_merge( $items, $field_items );
		}

		return $items;

	}

	/**
	 * Utility to flatten array values recursively so they can be saved with the appropriate index.
	 *
	 * @since 2.4
	 *
	 * @param        $array
	 * @param string $prefix
	 * @param bool   $field_value_is_array
	 *
	 * @return array
	 */
	private function flatten( $array, $prefix = '', $field_value_is_array = false ) {
		$result = array();
		if ( ! is_array( $array ) ) {
			return $result;
		}
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( $field_value_is_array && ! is_array( $value[0] ) ) {
					$result[ $prefix . $key ] = $value;
				} else {
					$result = $result + $this->flatten( $value, $prefix . $key . '_' );
				}
			} else {
				$result[ $prefix . $key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Returns the field inner markup.
	 *
	 * @since 2.4
	 *
	 * @param array        $form  The Form Object currently being processed.
	 * @param string|array $values The field values. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param null|array   $entry Null or the Entry Object currently being edited.
	 *
	 * @return string
	 */
	public function get_field_input( $form, $values = '', $entry = null ) {

		if ( $this->is_form_editor() ) {
			return sprintf( "<p>%s</p>", $this->label );
		}

		if ( empty( $values ) ) {
			$values = array( '' );
		}

		$input_top = $this->get_input_top( $values );

		$items = $this->get_input_items( $values, $entry );

		$html = $input_top . $items . $this->get_input_bottom();

		$max_items = intval( $this->maxItems );

		return sprintf( "<div class='gfield_repeater_wrapper' data-max_items='{$max_items}'>%s</div>", $html );
	}

	/**
	 * Returns the markup for the top of the repeater container.
	 *
	 * This method must return the opening tag for the container and this tag must have the class 'gfield_repeater_container'
	 *
	 * @since 2.4
	 *
	 * @param $values
	 *
	 * @return string
	 */
	public function get_input_top( $values ) {
		$html = "<fieldset class='gfield_repeater gfield_repeater_container'>\n";
		$label = esc_html( $this->label );
		$html .= "<legend class='gfield_label'>{$label}</legend>";
		return $html;
	}

	/**
	 * Returns the markup for the items.
	 *
	 * This method must return a single HTML element with the class 'gfield_repeater_items'. This elemment must contain
	 * all the items as direct children and each item must have the class 'gfield_repeater_item'.
	 *
	 * @since 2.4
	 *
	 * @param $values
	 * @param $entry
	 *
	 * @return string
	 */
	public function get_input_items( $values, $entry ) {

		/* @var GF_Field[] $fields */
		$fields = $this->fields;

		$form = GFAPI::get_form( $this->formId );

		$rows = '<div class="gfield_repeater_items">';

		$i = 0;
		foreach ( $values as $value ) {
			$row = "<div class='gfield_repeater_item'>";
			foreach ( $fields as $field ) {

				$field_value = $this->get_field_value( $field, $value );

				$field->set_context_property( 'itemIndex', $i );

				$field_input = $this->get_sub_field_input( $field, $form, $field_value, $entry, $i );

				$row .= "<div class='gfield_repeater_cell'>" . $field_input . '</div>';

				$field->set_context_property( 'itemIndex', null );
			}
			$buttons = $this->get_buttons( $values );
			$row .= "<div class='gfield_repeater_buttons'>{$buttons}</div>";
			$row .= '</div>';
			$rows .= $row;
			$i++;
		}
		$rows .= '</div>';
		return $rows;
	}

	/**
	 * Return the markup for the bottom of the repeater. Close the tags opened in the top.
	 *
	 * @since 2.4
	 *
	 * @return string
	 */
	public function get_input_bottom() {
		return '</fieldset>';
	}

	public function get_field_content( $value, $force_frontend_label, $form ) {

		$is_form_editor  = $this->is_form_editor();
		$is_entry_detail = $this->is_entry_detail();
		$is_admin        = $is_form_editor || $is_entry_detail;

		$admin_buttons = $this->get_admin_buttons();

		$description = $this->get_description( $this->description, 'gfield_description' );
		if ( $this->is_description_above( $form ) ) {
			$clear         = $is_admin ? "<div class='gf_clear'></div>" : '';
			$field_content = sprintf( "%s%s{FIELD}$clear", $admin_buttons, $description );
		} else {
			$field_content = sprintf( "%s{FIELD}%s", $admin_buttons, $description );
		}

		return $field_content;
	}

	/**
	 * Returns the repeater buttons.
	 *
	 * @since 2.4
	 *
	 * @return string
	 */
	public function get_buttons( $values ) {
		$is_form_editor = $this->is_form_editor();

		$delete_display = count( $values ) == 1 ? 'visibility:hidden;' : '';

		$add_events    = $is_form_editor ? '' : "onclick='gformAddRepeaterItem(this)' onkeypress='gformAddRepeaterItem(this)'";
		$delete_events = $is_form_editor ? '' : sprintf( "onclick='if(confirm(\"%s\")){gformDeleteRepeaterItem(this)};' onkeypress='gformDeleteRepeaterItem(this)'", esc_js( __( 'Are you sure you want to remove this item?', 'gravityforms' ) ) );

		$disabled_icon_class = ! empty( $this->maxItems ) && count( $values ) >= intval( $this->maxItems ) ? 'gfield_icon_disabled' : '';

		$add_button_text    = $this->addButtonText ? $this->addButtonText : '&#43;';
		$remove_button_text = $this->removeButtonText ? $this->removeButtonText : '&#45;' ;

		$add_button_class = $this->addButtonText ? 'add_repeater_item_text' : 'add_repeater_item_plus';
		$remove_button_class = $this->removeButtonText ? 'remove_repeater_item_text' : 'remove_repeater_item_minus';
		$html = "<button type='button' class='add_repeater_item {$disabled_icon_class} {$add_button_class}' {$add_events}>" . $add_button_text . "</button>" .
		        "<button type='button' class='remove_repeater_item {$remove_button_class}' {$delete_events} style='{$delete_display}'>" . $remove_button_text . "</button>";

		return $html;
	}

	/**
	 * Gets merge tag values.
	 *
	 * @since 2.4
	 *
	 * @param array|string $value      The value of the input.
	 * @param string       $input_id   The input ID to use.
	 * @param array        $entry      The Entry Object.
	 * @param array        $form       The Form Object
	 * @param string       $modifier   The modifier passed.
	 * @param array|string $raw_value  The raw value of the input.
	 * @param bool         $url_encode If the result should be URL encoded.
	 * @param bool         $esc_html   If the HTML should be escaped.
	 * @param string       $format     The format that the value should be.
	 * @param bool         $nl2br      If the nl2br function should be used.
	 *
	 * @return string The processed merge tag.
	 */
	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {

		$use_value = in_array( 'value', $this->get_modifiers() );
		$use_text  = ! $use_value;

		if ( $format == 'html' ) {
			$media = $esc_html ? 'screen' :'email';
			$merge_tag = $this->get_value_entry_detail( $raw_value, $entry['currency'], $use_text, $format, $media );
		} else {
			$merge_tag = $this->get_value_export_recursive( $entry, $input_id, $use_text, false, 0, '&nbsp;&nbsp;&nbsp;&nbsp;' );
		}

		return $merge_tag;
	}

	/**
	 * Format the entry value safe for displaying on the entry list page.
	 *
	 * @since 2.4
	 *
	 * @param string $value    The field value.
	 * @param array  $entry    The Entry Object currently being processed.
	 * @param string $field_id The field or input ID currently being processed.
	 * @param array  $columns  The properties for the columns being displayed on the entry list page.
	 * @param array  $form     The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {

		/* translators: %d: the number of items in value of the repeater field. */
		$display_value = is_array( $value ) ? sprintf( esc_html__( 'Number of items: %d' ), count( $value ) ) : '';

		return $display_value;
	}

	/**
	 * Format the entry value safe for displaying on the entry detail page and for the {all_fields} merge tag.
	 *
	 * @since 2.4
	 *
	 * @param string|array $item_values The field value.
	 * @param string $currency The entry currency code.
	 * @param bool|false $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param string $format The format requested for the location the merge is being used. Possible values: html, text or url.
	 * @param string $media The location where the value will be displayed. Possible values: screen or email.
	 *
	 * @return string
	 */
	public function get_value_entry_detail( $item_values, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		if ( $format == 'text' ) {
			return $this->get_value_export_recursive( array( $this->id => $item_values ), $this->id, $use_text, false, 0, '&nbsp;&nbsp;&nbsp;&nbsp;' );
		}

		$repeater_style        = $media == 'email' ? "style='padding: 5px 0 0 15px;font-size: 14px'" : '';
		$label_style           = $media == 'email' ? "style='color: rgba(35, 40, 45, 1.000);font-weight:600; padding-top:10px;font-size: 14px'" : '';
		$sub_field_label_style = $media == 'email' ? "style='color:rgb(155, 154, 154);padding-top:8px;font-size: 14px;'" : '';

		/* @var GF_Field[] $fields */
		$fields = $this->fields;
		$html   = "<div class='gfield_repeater' {$repeater_style}>";
		$repeater_label = $this->nestingLevel === 0 ? '' : $this->label;
		$html   .= "<div class='gfield_label' {$label_style}>{$repeater_label}</div>";
		$html   .= '<div class="gfield_repeater_items">';
		foreach ( $item_values as $item_value ) {
			$html .= '<div class="gfield_repeater_item">';
			foreach ( $fields as $sub_field ) {
				if ( $sub_field->fields ) {
					$sub_field_value = $item_value[ $sub_field->id ];
				} else {
					$sub_field_value = $this->get_field_value( $sub_field, $item_value );
				}
				$label = $sub_field->get_field_label( true, $item_values );
				$label = empty( $sub_field->fields ) ? "<div class='gfield_repeater_label' {$sub_field_label_style}>{$label}</div>" : '';
				$value = $sub_field->get_value_entry_detail( $sub_field_value, $currency, $use_text, 'html', $media );
				$value = "<div class='gfield_repeater_value' style='color:rgba(117, 117, 117, 1);font-size: 14px'>{$value}</div>";
				$html .= '<div class="gfield_repeater_cell">' . $label . $value . '</div>';
			}
			$html .= '</div>';
		}
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Returns the value for a field inside a repeater.
	 *
	 * @since 2.4
	 *
	 * @param GF_Field $field
	 * @param array|string $value
	 *
	 * @return array|string
	 */
	public function get_field_value( $field, $value ) {
		if ( $field->fields ) {
			$field_value = isset( $value[ $field->id ] ) ? $value[ $field->id ] : '';
		} else {
			$inputs = $field->get_entry_inputs();
			if ( is_array( $value ) ) {
				if ( is_array( $inputs ) ) {
					$field_value = array();
					$field_keys = array_keys( $value );
					natsort( $field_keys );
					foreach ( $field_keys as $input_id ) {
						if ( is_numeric( $input_id ) && absint( $input_id ) == absint( $field->id ) ) {
							$val = $value[ $input_id ];
							$field_value[ $input_id ] = $val;
						}
					}
				} else {
					$field_value = isset( $value[ $field->id ] ) ? $value[ $field->id ] : '';
				}
			} else {
				$field_value = '';
			}
		}

		return $field_value;
	}

	/**
	 * Returns the input markup for a field inside a repeater.
	 *
	 * Appends the item index to the name and id attributes and validates the value.
	 *
	 * @since 2.4
	 *
	 * @param GF_Field $field
	 * @param array $form
	 * @param array $field_value
	 * @param array $entry
	 * @param int $index
	 *
	 * @return mixed
	 */
	public function get_sub_field_input( $field, $form, $field_value, $entry, $index ) {
		$field_content = $this->get_sub_field_content( $field, $field_value, $form, $entry );

		// Adjust all the name attributes in the markup
		preg_match_all( "/(name='input_[^\[|']*)((\[[0-9]*\])*)'/", $field_content, $matches, PREG_SET_ORDER );

		$replaced = array();
		foreach ( $matches as $match ) {
			if ( ! in_array( $match[0], $replaced ) ) {
				$input_name    = str_replace( $match[1], $match[1] . "[{$index}]", $match[0] );
				$field_content = str_replace( $match[0], $input_name, $field_content );
				$replaced[]    = $match[0];
			}
		}

		// Adjust all the id attributes in the markup
		preg_match_all( "/(id='((input|choice)_[0-9|_]*))[0-9|-]*'/", $field_content, $matches, PREG_SET_ORDER );

		$replaced = array();
		foreach ( $matches as $match ) {
			if ( ! in_array( $match[0], $replaced ) ) {
				$input_id      = str_replace( $match[1], $match[1] . "-{$index}", $match[0] ) ;
				$field_content = str_replace( $match[0], $input_id, $field_content );
				$replaced[]    = $match[0];
			}
		}

		// Adjust all the for attributes in the markup
		preg_match_all( "/(for='(input|choice)_[^\[']*)'/", $field_content, $matches, PREG_SET_ORDER );

		$replaced = array();
		foreach ( $matches as $match ) {
			if ( ! in_array( $match[1], $replaced ) ) {
				$input_id      = $match[1] . "-{$index}";
				$field_content = str_replace( $match[1], $input_id, $field_content );
				$replaced[]    = $match[1];
			}
		}

		$target_page = rgpost( 'gform_target_page_number_' . $this->formId );
		$source_page = rgpost( 'gform_source_page_number_' . $this->formId );
		$validate = $source_page == $field->pageNumber && rgpost( 'is_submit_' . $this->formId ) && ( $target_page == 0 || $target_page > $source_page );

		if ( $validate ) {
			$field->failed_validation = false;
			if ( $field->isRequired && $field->is_value_empty( $field_value ) ) {
				$field->failed_validation  = true;
				$field->validation_message = empty( $field->errorMessage ) ? __( 'This field is required.', 'gravityforms' ) : $field->errorMessage;
			}

			if ( ! $field->failed_validation ) {
				$field->validate( $field_value, $form );
			}

			$custom_validation_result = gf_apply_filters( array( 'gform_field_validation', $form['id'], $field->id ), array(
				'is_valid' => $field->failed_validation ? false : true,
				'message'  => $field->validation_message
			), $field_value, $form, $field );
			$field->failed_validation  = rgar( $custom_validation_result, 'is_valid' ) ? false : true;
		}

		$validation_message = ( $field->failed_validation && ! empty( $field->validation_message ) ) ? sprintf( "<div class='gfield_description validation_message'>%s</div>", $field->validation_message ) : '';

		return $field_content . $validation_message;
	}

	/**
	 * Returns the markup for the sub field.
	 *
	 * @since 2.4
	 *
	 * @param GF_Field     $field
	 * @param string|array $value
	 * @param array        $form
	 * @param array        $entry
	 *
	 * @return string
	 */
	public function get_sub_field_content( $field, $value, $form, $entry ) {

		$validation_status = $field->failed_validation;

		if ( empty( $field->fields ) ) {
			// Validation will be handled later inside GF_Field_Repeater::get_sub_field_input so temporarily set failed_validation to false.
			$field->failed_validation = false;
		}

		if ( ! class_exists( 'GFFormDisplay' ) ) {
			require_once( GFCommon::get_base_path() .'/form_display.php' );
		}

		$field_content = GFFormDisplay::get_field_content( $field, $value, true, $form['id'], $form );

		$field->failed_validation = $validation_status;

		return $field_content;
	}

	/**
	 * Builds the repeater's array of items.
	 *
	 * @since 2.4
	 *
	 * @param $entry
	 *
	 * @return mixed
	 */
	public function hydrate( $entry ) {
		$entry[ $this->id ] = $this->get_repeater_items( $entry );
		return $entry;
	}

	/**
	 * Recursively converts the repeater values from flattened values in the entry array into a multidimensional array
	 * of items.
	 *
	 * @since 2.4
	 *
	 * @param array             $entry
	 * @param GF_Field_Repeater $repeater_field
	 * @param string            $index
	 *
	 * @return array
	 */
	public function get_repeater_items( &$entry, $repeater_field = null, $index = '' ) {

		if ( ! $repeater_field ) {
			$repeater_field = $this;
		}

		$items = array();

		// Blank items are not stored but we need to display them if a value exists with a higher index.
		$max_indexes = $this->get_max_indexes( $entry, $repeater_field, $index );

		$repeater_fields = array();

		foreach ( $repeater_field->fields as $field ) {
			if ( is_array( $field->fields ) ) {
				$repeater_fields[] = $field;
				continue;
			}

			for ( $i = 0; $i <= $max_indexes[ $field->id ]; $i ++ ) {
				$inputs = $field->get_entry_inputs();

				if ( is_array( $inputs ) ) {
					foreach ( $inputs as $input ) {

						$input_id = $input['id'];

						$key = $input_id . $index . '_' . $i;

						$value = isset( $entry[ $key ] ) ? $entry[ $key ] : '';

						$items[ $i ][ $input_id ] = $value;

						if ( isset( $entry[ $key ] ) ) {
							unset( $entry[ $key ] );
						}
					}
				} else {

					$key = $field->id . $index . '_' . $i;

					$value = isset( $entry[ $key ] ) ? $entry[ $key ] : '';

					$items[ $i ][ $field->id ] = $value;

					if ( isset( $entry[ $key ] ) ) {
						unset( $entry[ $key ] );
					}
				}
			}
		}

		if ( ! empty( $repeater_fields ) ) {

			$i = 0;

			do {
				$all_repeaters_have_values = true;
				foreach ( $repeater_fields as $repeater ) {
					$v = $this->get_repeater_items( $entry, $repeater, $index . '_' . $i );

					$is_empty = $this->empty_deep( $v );

					if ( ( $i == 0 || ! $is_empty ) || ( empty( $index ) && isset( $items[ $i ] ) && ! $this->empty_deep( $items[ $i ] ) ) ) {
						$items[ $i ][ $field->id ] = $v;
					}

					if ( $is_empty ) {
						$all_repeaters_have_values = false;
					}
				}
				$i ++;
			} while ( $all_repeaters_have_values );
		}

		return $items;
	}

	/**
	 * Parses all the flat entry array keys and returns the maximum index by field ID.
	 *
	 * @since 2.4
	 *
	 * @param array $entry                          The entry array
	 * @param GF_Field_Repeater $repeater_field     The repeater field
	 * @param string $index                         The index prefix
	 *
	 * @return array
	 */
	protected function get_max_indexes( $entry, $repeater_field, $index ) {

		$field_ids = array_keys( $entry );

		$max_indexes = array();

		$matches = array();


		foreach ( $repeater_field->fields as $field ) {
			if ( ! isset( $matches[ $field->id ] ) ) {
				$matches[ $field->id ] = array( 0 );
			}
			foreach ( $field_ids as $f_id ) {
				if ( preg_match( "/{$field->id}[^_]*{$index}_([0-9]+)/", $f_id, $m ) ) {
					$matches[ $field->id ][] = intval( $m[1] );
				}
			}
			$max_indexes[ $field->id ] = max( $matches[ $field->id ] );
		}

		return $max_indexes;
	}

	/**
	 * Recursively checks whether a multi-dimensional array is empty.
	 *
	 * @since 2.4
	 *
	 * @param $val
	 *
	 * @return bool
	 */
	public function empty_deep( $val ) {

		$result = true;

		if ( is_array( $val ) && count( $val ) > 0 ) {
			foreach ( $val as $v ) {
				$result = $result && $this->empty_deep( $v );
			}
		} else {
			$result = empty( $val );
		}

		return $result;

	}

	/**
	 * Returns the sub-filters for the current field.
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public function get_filter_sub_filters() {
		$filters = array();
		$fields  = $this->fields;
		foreach ( $fields as $field ) {
			/** @var GF_Field $field */
			$filter_settings = array(
				'key'  => $field->id,
				'text' => GFFormsModel::get_label( $field, false, true ),
			);

			if ( is_array( $field->fields ) ) {
				$filter_settings = $field->get_filter_settings();
				$filters[]       = $filter_settings;
				continue;
			}
			$sub_filters = $field->get_filter_sub_filters();

			if ( ! empty( $sub_filters ) ) {
				$filter_settings['group']   = true;
				$filter_settings['filters'] = $sub_filters;
			} else {
				$filter_settings['preventMultiple'] = false;
				$filter_settings['operators']       = $field->get_filter_operators();

				$values = $field->get_filter_values();
				if ( ! empty( $values ) ) {
					$filter_settings['values'] = $values;
				}
			}

			$values = $field->get_filter_values();
			if ( ! empty( $values ) ) {
				$filter_settings['values'] = $values;
			}

			$filters[] = $filter_settings;
		}

		return $filters;
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

		$filter_settings = parent::get_filter_settings();

		$filter_settings['isNestable'] = true;

		return $filter_settings;
	}

	/**
	 * Format the entry value before it is used in entry exports and by framework add-ons using GFAddOn::get_field_value().
	 *
	 * @since 2.4
	 *
	 * @param array      $entry    The entry currently being processed.
	 * @param string     $input_id The field or input ID.
	 * @param bool|false $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param bool|false $is_csv   Is the value going to be used in the .csv entries export?
	 *
	 * @return string|array
	 */
	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		$export = $this->get_value_export_recursive( $entry, $input_id, $use_text, $is_csv );
		return $export;
	}

	/**
	 * Format the entry value before it is used in entry exports and by framework add-ons using GFAddOn::get_field_value().
	 *
	 * @since 2.4
	 *
	 * @param array      $entry    The entry currently being processed.
	 * @param string     $input_id The field or input ID.
	 * @param bool|false $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param bool|false $is_csv   Is the value going to be used in the .csv entries export?
	 *
	 * @return string|array
	 */
	public function get_value_export_recursive( $entry, $input_id = '', $use_text = false, $is_csv = false, $depth = 0, $padding = '    ' ) {
		if ( empty( $input_id ) ) {
			$input_id = $this->id;
		}

		$items = rgar( $entry, $input_id );

		/* @var GF_Field[] $fields */
		$fields = $this->fields;

		$csv = array();

		foreach ( $items as $item ) {

			foreach ( $fields as $field ) {

				$inputs = $field->get_entry_inputs();

				if ( is_array( $inputs ) ) {
					$field_value = array();
					$field_keys  = array_keys( $item );
					foreach ( $field_keys as $input_id ) {
						if ( is_numeric( $input_id ) && absint( $input_id ) == absint( $field->id ) ) {
							$field_value[ $input_id ] = $item[ $input_id ];
						}
					}
				} else {
					$field_value = isset( $item[ $field->id ] ) ? $item[ $field->id ] : '';
					$field_value = array( (string) $field->id => $field_value );
				}

				$label = str_repeat( $padding, $depth ) . GFFormsModel::get_label( $field );

				if ( is_array( $field->fields ) ) {
					$new_depth = $depth + 1;
					$line      = $label . "\n" . $field->get_value_export_recursive( $field_value, $field->id, $use_text, $is_csv, $new_depth, $padding );
					if ( $depth == 0 ) {
						$line .= "\n";
					}
				} else {

					if ( 'list' === $field->get_input_type() && ! empty( $field_value[ $field->id ] ) ) {

						$list_rows = maybe_unserialize( $field_value[ $field->id ] );

						if ( is_array( $list_rows[0] ) ) {
							$lines = array();
							foreach ( $list_rows as $i => $list_row ) {
								$row_label = $label . ' ' . ( $i + 1 );

								// Prepare row value.
								$row_value = implode( '|', $list_row );
								if ( strpos( $row_value, '=' ) === 0 ) {
									// Prevent Excel formulas
									$row_value = "'" . $row_value;
								}

								$lines[] = $row_label . ': ' . $row_value;
							}
							$line = implode( "\n", $lines );
						} else {
							$value = implode( '|', $list_rows );
							if ( strpos( $value, '=' ) === 0 ) {
								// Prevent Excel formulas
								$value = "'" . $value;
							}
							$line = $label . ': ' . $value;
						}

					} else {
						$line = $label . ': ' . $field->get_value_export( $field_value, $field->id, $use_text, $is_csv );
					}

				}

				$csv[] = $line;
			}
		}

		return implode( "\n", $csv );
	}

	/**
	 * Store the modifiers so they can be accessed when preparing the {all_fields} and field merge tag output.
	 *
	 * @since 2.4
	 *
	 * @param array $modifiers An array of modifiers to be stored.
	 */
	public function set_modifiers( $modifiers ) {
		parent::set_modifiers( $modifiers );

		/* @var GF_Field $sub_field */
		foreach ( $this->fields as $sub_field ) {
			$sub_field->set_modifiers( $modifiers );
		}
	}

}

GF_Fields::register( new GF_Field_Repeater() );
