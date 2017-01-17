<?php

// If Gravity Forms isn't loaded, bail.
if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Class GF_Field_List
 *
 * Handles the behavior of List fields.
 *
 * @since Unknown
 */
class GF_Field_List extends GF_Field {

	/**
	 * Sets the field type for the List field.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @var string The field type.
	 */
	public $type = 'list';

	/**
	 * Sets the field title to be displayed in the form editor.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFCommon::get_field_type_title()
	 * @used-by GFAddOn::get_field_map_choices()
	 * @used-by GF_Field::get_form_editor_button()
	 *
	 * @return string The field title. Escaped and translatable.
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'List', 'gravityforms' );
	}

	/**
	 * Defines the field settings available in the form editor.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormDetail::inline_scripts()
	 *
	 * @return array The settings available.
	 */
	function get_form_editor_field_settings() {
		return array(
			'columns_setting',
			'maxrows_setting',
			'add_icon_url_setting',
			'delete_icon_url_setting',
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
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

	/**
	 * Gets the ID of the first input.
	 *
	 * @since Unknown
	 * @access public
	 *
	 * @uses GF_Field::is_form_editor()
	 * @uses GF_Field_List::$id
	 *
	 * @param array $form The Form Object.
	 *
	 * @return string The ID of the first input. Empty string if not found.
	 */
	public function get_first_input_id( $form ) {
		return ! $this->is_form_editor() ? sprintf( 'input_%s_%s_shim', $form['id'], $this->id ) : '';
	}

	/**
	 * Defines if the inline style block has been printed.
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @used-by GF_Field_List::get_field_input()
	 *
	 * @var bool false
	 */
	private static $_style_block_printed = false;

	/**
	 * Builds the field input HTML markup.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFCommon::get_field_input()
	 * @uses    GF_Field::is_entry_detail()
	 * @uses    GF_Field::is_form_editor()
	 * @uses    GF_Field_List::$_style_block_printed
	 * @uses    GF_Field_List::$maxRow
	 * @uses    GF_Field_List::$addIconUrl
	 * @uses    GF_Field_List::$deleteIconUrl
	 * @uses    GFCommon::get_base_url()
	 *
	 * @param array      $form  The Form Object.
	 * @param string     $value The field value. Defaults to empty string.
	 * @param null|array $entry The Entry Object. Defaults to null.
	 *
	 * @return string The List field HTML markup.
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {

		$form_id         = $form['id'];
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$disabled_text = $is_form_editor ? 'disabled="disabled"' : '';

		if ( ! empty( $value ) ) {
			$value = maybe_unserialize( $value );
		}

		if ( ! is_array( $value ) ) {
			$value = array( array() );
		}

		$has_columns = is_array( $this->choices );
		$columns     = $has_columns ? $this->choices : array( array() );

		$shim_style  = is_rtl() ? 'position:absolute;left:999em;' : 'position:absolute;left:-999em;';
		$label_target_shim = sprintf( '<input type=\'text\' id=\'input_%1$s_%2$s_shim\' style=\'%3$s\' onfocus=\'jQuery( "#field_%1$s_%2$s table tr td:first-child input" ).focus();\' />', $form_id, $this->id, $shim_style );

		$list = '';
		if ( ! self::$_style_block_printed ){
			// This style block needs to be inline so that the list field continues to work even if the option to turn off CSS output is activated.
			$list .= '<style type="text/css">

						body .ginput_container_list table.gfield_list tbody tr td.gfield_list_icons {
							vertical-align: middle !important;
						}

						body .ginput_container_list table.gfield_list tbody tr td.gfield_list_icons img.add_list_item,
						body .ginput_container_list table.gfield_list tbody tr td.gfield_list_icons img.delete_list_item {
							background-color: transparent !important;
							background-position: 0 0;
							background-size: 16px 16px !important;
							background-repeat: no-repeat;
							border: none !important;
							width: 16px !important;
							height: 16px !important;
							opacity: 0.5;
							transition: opacity .5s ease-out;
						    -moz-transition: opacity .5s ease-out;
						    -webkit-transition: opacity .5s ease-out;
						    -o-transition: opacity .5s ease-out;
						}

						body .ginput_container_list table.gfield_list tbody tr td.gfield_list_icons img.add_list_item:hover,
						body .ginput_container_list table.gfield_list tbody tr td.gfield_list_icons img.delete_list_item:hover {
							opacity: 1.0;
						}

						</style>';

			self::$_style_block_printed = true;
		}

		$list .= "<div class='ginput_container ginput_container_list ginput_list'>" .
			$label_target_shim .
			"<table class='gfield_list gfield_list_container'>";

		$class_attr = '';
		if ( $has_columns ) {

			$list .= '<colgroup>';
			for ( $colnum = 1; $colnum <= count( $columns ) + 1; $colnum++ ) {
				$odd_even = ( $colnum % 2 ) == 0 ? 'even' : 'odd';
				$list .= sprintf( "<col id='gfield_list_%d_col_%d' class='gfield_list_col_%s' />", $this->id, $colnum, $odd_even );
			}
			$list .= '</colgroup>';

			$list .= '<thead><tr>';
			foreach ( $columns as $column ) {
				$list .= '<th>' . esc_html( $column['text'] ) . '</th>';
			}
			$list .= '<th>&nbsp;</th></tr></thead>';
		} else {
			$list .=
				'<colgroup>' .
					"<col id='gfield_list_{$this->id}_col1' class='gfield_list_col_odd' />" .
					"<col id='gfield_list_{$this->id}_col2' class='gfield_list_col_even' />" .
				'</colgroup>';
		}

		$delete_display      = count( $value ) == 1 ? 'visibility:hidden;' : '';
		$maxRow              = intval( $this->maxRows );
		$disabled_icon_class = ! empty( $maxRow ) && count( $value ) >= $maxRow ? 'gfield_icon_disabled' : '';

		$add_icon    = ! empty( $this->addIconUrl ) ? $this->addIconUrl : GFCommon::get_base_url() . '/images/list-add.svg';
		$delete_icon = ! empty( $this->deleteIconUrl ) ? $this->deleteIconUrl : GFCommon::get_base_url() . '/images/list-remove.svg';

		$add_events    = $is_form_editor ? '' : "onclick='gformAddListItem(this, {$maxRow})' onkeypress='gformAddListItem(this, {$maxRow})'";
		$delete_events = $is_form_editor ? '' : "onclick='gformDeleteListItem(this, {$maxRow})' onkeypress='gformDeleteListItem(this, {$maxRow})'";

		$list .= '<tbody>';
		$rownum = 1;
		foreach ( $value as $item ) {

			$odd_even = ( $rownum % 2 ) == 0 ? 'even' : 'odd';

			$list .= "<tr class='gfield_list_row_{$odd_even} gfield_list_group'>";
			$colnum = 1;
			foreach ( $columns as $column ) {
				$data_label = '';

				// Getting value. Taking into account columns being added/removed from form meta.
				if ( is_array( $item ) ) {
					if ( $has_columns ) {
						$val = rgar( $item, $column['text'] );
						$data_label = "data-label='" . esc_attr( $column['text'] ) . "'";
					} else {
						$vals = array_values( $item );
						$val  = rgar( $vals, 0 );
					}
				} else {
					$val = $colnum == 1 ? $item : '';
				}

				$list .= "<td class='gfield_list_cell gfield_list_{$this->id}_cell{$colnum}' {$data_label}>" . $this->get_list_input( $has_columns, $column, $val, $form_id ) . '</td>';
				$colnum ++;
			}

			if ( $this->maxRows != 1 ) {

				// Can't replace these icons with the webfont versions since they appear on the front end.

				$list .= "<td class='gfield_list_icons'>";
				$list .= "   <img src='{$add_icon}' class='add_list_item {$disabled_icon_class}' {$disabled_text} title='" . esc_attr__( 'Add another row', 'gravityforms' ) . "' alt='" . esc_attr__( 'Add a new row', 'gravityforms' ) . "' {$add_events} style='cursor:pointer;' " . $this->get_tabindex() . "/>" .
				         "   <img src='{$delete_icon}' class='delete_list_item' {$disabled_text} title='" . esc_attr__( 'Remove this row', 'gravityforms' ) . "' alt='" . esc_attr__( 'Remove this row', 'gravityforms' ) . "' {$delete_events} style='cursor:pointer; {$delete_display}' " . $this->get_tabindex() . "/>";
				$list .= '</td>';

			}

			$list .= '</tr>';

			if ( ! empty( $maxRow ) && $rownum >= $maxRow ) {
				break;
			}

			$rownum ++;
		}

		$list .= '</tbody>';
		$list .= '</table></div>';

		return $list;

	}

	/**
	 * Builds the input that will be inside the List field.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GF_Field::get_tabindex()
	 * @uses GF_Field::is_form_editor()
	 * @uses GF_Field_List::$choices
	 *
	 * @param bool   $has_columns If the input has columns.
	 * @param array  $column      The column details.
	 * @param string $value       The existing value of the input.
	 * @param int    $form_id     The form ID.
	 *
	 * @return string The input HTML markup.
	 */
	public function get_list_input( $has_columns, $column, $value, $form_id ) {

		$tabindex = $this->get_tabindex();
		$disabled = $this->is_form_editor() ? 'disabled' : '';

		$column_index = 1;
		if ( $has_columns && is_array( $this->choices ) ) {
			foreach ( $this->choices as $choice ) {
				if ( $choice['text'] == $column['text'] ) {
					break;
				}

				$column_index ++;
			}
		}
		$input_info = array( 'type' => 'text' );

		/**
		 * Filters the column input.
		 *
		 * @since Unknown
		 *
		 * @param array  $input_info     Information about the input. Contains the input type.
		 * @param object GF_Field_List   Field object for this field type.
		 * @param string $column['text'] The column text value.
		 * @param int    $form_id        The form ID.
		 */
		$input_info = gf_apply_filters( array(
			'gform_column_input',
			$form_id,
			$this->id,
			$column_index
		), $input_info, $this, rgar( $column, 'text' ), $value, $form_id );

		switch ( $input_info['type'] ) {

			case 'select' :
				$input = "<select name='input_{$this->id}[]' {$tabindex} {$disabled} >";
				if ( ! is_array( $input_info['choices'] ) ) {
					$input_info['choices'] = array_map( 'trim', explode( ',', $input_info['choices'] ) );
				}

				foreach ( $input_info['choices'] as $choice ) {
					if ( is_array( $choice ) ) {
						$choice_value    = $choice['value'];
						$choice_text     = $choice['text'];
						$choice_selected = array_key_exists( 'isSelected', $choice ) ? $choice['isSelected'] : false;
					} else {
						$choice_value    = $choice;
						$choice_text     = $choice;
						$choice_selected = false;
					}
					$is_selected = empty( $value ) ? $choice_selected : $choice_value == $value;
					$selected    = $is_selected ? "selected='selected'" : '';
					$input .= "<option value='" . esc_attr( $choice_value ) . "' {$selected}>" . esc_html( $choice_text ) . '</option>';
				}
				$input .= '</select>';

				break;

			default :
				$input = "<input type='text' name='input_{$this->id}[]' value='" . esc_attr( $value ) . "' {$tabindex} {$disabled}/>";
				break;
		}

		/**
		 * Filters the column input HTML markup.
		 *
		 * @since Unknown
		 *
		 * @param string $input          The input markup.
		 * @param array  $input_info     The information that was used to build the input.
		 * @param object GF_Field_List   An instance of the List field object.
		 * @param string $column['text'] The column text value.
		 * @param int    $form_id        The form ID.
		 */
		return gf_apply_filters( array(
			'gform_column_input_content',
			$form_id,
			$this->id,
			$column_index
		), $input, $input_info, $this, rgar( $column, 'text' ), $value, $form_id );

	}

	/**
	 * Gets the CSS class to be used in the field label.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GF_Field::get_field_content()
	 *
	 * @return string String containing the CSS class names.
	 */
	public function get_field_label_class(){

		$has_columns = is_array( $this->choices );

		return $has_columns ? 'gfield_label gfield_label_before_complex' : 'gfield_label';
	}

	/**
	 * Gets the value of te field from the form submission.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormsModel::get_field_value()
	 * @uses    GF_Field::get_input_value_submission()
	 * @uses    GF_Field_List::create_list_array()
	 *
	 * @param array $field_values             The properties to search for.
	 * @param bool  $get_from_post_global_var If the global GET variable should be used to obtain the value. Defaults to true.
	 *
	 * @return array The submission value.
	 */
	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {
		$value = $this->get_input_value_submission( 'input_' . $this->id, $this->inputName, $field_values, $get_from_post_global_var );

		// Allow the value to be an array of row arrays in addition to the array of rows.
		if ( is_array( rgar( $value, 0 ) ) ){
			// Already in correct format, return value unchanged.
			return $value;
		}

		// Not already in the correct format.
		$value = $this->create_list_array( $value );

		return $value;
	}

	/**
	 * Check if the submission value is empty.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormDisplay::is_empty()
	 * @uses    GF_Field_List::$id
	 *
	 * @param int $form_id The form ID to check.
	 *
	 * @return bool True if empty. False otherwise.
	 */
	public function is_value_submission_empty( $form_id ) {
		$value = rgpost( 'input_' . $this->id );
		if ( is_array( $value ) ) {
			// Empty if all inputs are empty (for inputs with the same name).
			foreach ( $value as $input ) {
				if ( strlen( trim( $input ) ) > 0 ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Gets the field value HTML markup to be used on the entry detail page.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFCommon::get_lead_field_display()
	 *
	 * @param array  $value    The submitted entry value.
	 * @param string $currency Not used.
	 * @param bool   $use_text Not used.
	 * @param string $format   The format to be used when building the items.
	 *                         Accepted values are text, url, or html. Defaults to html.
	 * @param string $media    Defines how the content will be output.
	 *                         Accepted values are screen or email. Defaults to screen.
	 *
	 * @return string The HTML markup to be displayed.
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		if ( empty( $value ) ) {
			return '';
		}

		$value = maybe_unserialize( $value );
		
		if( ! is_array( $value ) ) {
			return '';
		}

		$has_columns = is_array( $value[0] );

		if ( ! $has_columns ) {
			$items = '';
			foreach ( $value as $key => $item ) {
				if ( ! empty( $item ) ) {
					$item = wp_kses_post( $item );
					switch ( $format ) {
						case 'text' :
							$items .= $item . ', ';
							break;
						case 'url' :
							$items .= $item . ',';
							break;
						default :
							if ( $media == 'email' ) {
								$items .= "<li>{$item}</li>";
							} else {
								$items .= "<li>{$item}</li>";
							}
							break;
					}
				}
			}

			if ( empty( $items ) ) {
				return '';
			} elseif ( $format == 'text' ) {
				return substr( $items, 0, strlen( $items ) - 2 ); // Removing last comma.
			} elseif ( $format == 'url' ) {
				return substr( $items, 0, strlen( $items ) - 1 ); // Removing last comma.
			} elseif ( $media == 'email' ) {
				return "<ul class='bulleted'>{$items}</ul>";
			} else {
				return "<ul class='bulleted'>{$items}</ul>";
			}
		} elseif ( is_array( $value ) ) {
			$columns = array_keys( $value[0] );

			$list = '';

			switch ( $format ) {
				case 'text' :
					$is_first_row = true;
					foreach ( $value as $item ) {
						if ( ! $is_first_row ) {
							$list .= "\n\n" . $this->label . ': ';
						}

						$item = array_map( 'wp_kses_post', $item );

						$list .= implode( ',', array_values( $item ) );

						$is_first_row = false;
					}
					break;

				case 'url' :
					foreach ( $value as $item ) {
						$item = array_map( 'wp_kses_post', $item );
						$list .= implode( "|", array_values( $item ) ) . ',';
					}
					if ( ! empty( $list ) ) {
						$list = substr( $list, 0, strlen( $list ) - 1 );
					}

					break;

				default :
					if ( $media == 'email' ) {
						$list = "<table class='gfield_list' style='border-top: 1px solid #DFDFDF; border-left: 1px solid #DFDFDF; border-spacing: 0; padding: 0; margin: 2px 0 6px; width: 100%'><thead><tr>\n";

						//reading columns from entry data
						foreach ( $columns as $column ) {
							$list .= "<th style='background-image: none; border-right: 1px solid #DFDFDF; border-bottom: 1px solid #DFDFDF; padding: 6px 10px; font-family: sans-serif; font-size: 12px; font-weight: bold; background-color: #F1F1F1; color:#333; text-align:left'>" . esc_html( $column ) . '</th>' . "\n";
						}
						$list .= '</tr></thead>' . "\n";

						$list .= "<tbody style='background-color: #F9F9F9'>";
						foreach ( $value as $item ) {
							$list .= '<tr>';
							foreach ( $columns as $column ) {
								$val = rgar( $item, $column );
								$val = wp_kses_post( $val );
								$list .= "<td style='padding: 6px 10px; border-right: 1px solid #DFDFDF; border-bottom: 1px solid #DFDFDF; border-top: 1px solid #FFF; font-family: sans-serif; font-size:12px;'>{$val}</td>\n";
							}

							$list .= '</tr>' . "\n";
						}

						$list .= '<tbody></table>' . "\n";
					} else {
						$list = "<table class='gfield_list'><thead><tr>";

						// Reading columns from entry data.
						foreach ( $columns as $column ) {
							$list .= '<th>' . esc_html( $column ) . '</th>' . "\n";
						}
						$list .= '</tr></thead>' . "\n";

						$list .= '<tbody>';
						foreach ( $value as $item ) {
							$list .= '<tr>';
							foreach ( $columns as $column ) {
								$val = rgar( $item, $column );
								$val = wp_kses_post( $val );
								$list .= "<td>{$val}</td>\n";
							}

							$list .= '</tr>' . "\n";
						}

						$list .= '<tbody></table>' . "\n";
					}
					break;
			}

			return $list;
		}

		return '';
	}

	/**
	 * Gets the value of the field when the entry is saved.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormsModel::prepare_value()
	 * @uses    GF_Field_List::$adminOnly
	 * @uses    GF_Field_List::$allowsPrepopulate
	 * @uses    GF_Field_List::create_list_array()
	 * @uses    GFCommon::is_empty_array()
	 * @uses    GF_Field::sanitize_entry_value()
	 *
	 * @param string $value      The value to use.
	 * @param array  $form       The form that the entry is associated with.
	 * @param string $input_name The name of the input containing the value.
	 * @param int    $lead_id    The entry ID.
	 * @param array  $lead       The Entry Object.
	 *
	 * @return string The entry value. Escaped.
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {

		if ( $this->is_administrative() && $this->allowsPrepopulate ) {
			$value = json_decode( $value );
		}

		if ( GFCommon::is_empty_array( $value ) ) {
			$value = '';
		} else {
			$value = $this->create_list_array( $value );
			$value = serialize( $value );
		}

		$value_safe = $this->sanitize_entry_value( $value, $form['id'] );

		return $value_safe;
	}

	/**
	 * Gets merge tag values.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by
	 * @uses GFCommon::get_lead_field_display()
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
		$output_format = in_array( $modifier, array( 'text', 'html', 'url' ) ) ? $modifier : $format;

		return GFCommon::get_lead_field_display( $this, $raw_value, $entry['currency'], true, $output_format );
	}

	/**
	 * Creates an array from the list items.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GF_Field_List::get_value_save_entry()
	 * @used-by GF_Field_List::get_value_submission()
	 *
	 * @param array $value The pre-formatted list.
	 *
	 * @return array The list rows.
	 */
	function create_list_array( $value ) {
		if ( ! $this->enableColumns ) {
			return $value;
		} else {
			$col_count = count( $this->choices );
			$rows      = array();

			$row_count = count( $value ) / $col_count;

			$col_index = 0;
			for ( $i = 0; $i < $row_count; $i ++ ) {
				$row = array();
				foreach ( $this->choices as $column ) {
					$row[ $column['text'] ] = rgar( $value, $col_index );
					$col_index ++;
				}
				$rows[] = $row;
			}

			return $rows;
		}
	}

	/**
	 * Sanitizes the field settings.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormDetail::add_field()
	 * @used-by GFFormsModel::sanitize_settings()
	 * @uses    GF_Field::sanitize_settings()
	 * @uses    GF_Field_List::$maxRows
	 *
	 * @return void
	 */
	public function sanitize_settings() {
		parent::sanitize_settings();
		$this->maxRows = absint( $this->maxRows );
	}

	/**
	 * Gets the field value, formatted for exports.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFExport::start_export()
	 * @used-by GFAddOn::get_field_value()
	 * @uses    GF_Field_List::$id
	 * @uses    GF_Field_List::$enableColumns
	 * @uses    GF_Field_List::$choices
	 * @uses    GFCommon::implode_non_blank()
	 *
	 * @param array  $entry    The Entry Object.
	 * @param string $input_id Input ID to export. If not defined, uses the current input ID. Defaults to empty string.
	 * @param bool   $use_text Not used. Defaults to false.
	 * @param bool   $is_csv   If the export should be formatted as CSV. Defaults to false.
	 *
	 * @return string
	 */
	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		if ( empty( $input_id ) ) {
			$input_id = $this->id;
		} elseif ( ! ctype_digit( $input_id ) ) {
			$field_id_array = explode( '.', $input_id );
			$input_id       = rgar( $field_id_array, 0 );
			$column_num     = rgar( $field_id_array, 1 );
		}

		$value = rgar( $entry, $input_id );
		if ( empty( $value ) || $is_csv ) {

			return $value;
		}

		$list_values = $column_values = unserialize( $value );

		if ( isset( $column_num ) && is_numeric( $column_num ) && $this->enableColumns ) {
			$column        = rgars( $this->choices, "{$column_num}/text" );
			$column_values = array();
			foreach ( $list_values as $value ) {
				$column_values[] = rgar( $value, $column );
			}
		} elseif ( $this->enableColumns ) {

			return json_encode( $list_values );
		}

		return GFCommon::implode_non_blank( ', ', $column_values );
	}

}

// Register the list field.
GF_Fields::register( new GF_Field_List() );
