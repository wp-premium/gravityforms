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
		return '';
	}

	/**
	 * Defines if the inline style block has been printed.
	 *
	 * @since  Unknown
	 * @access private
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
	 * @param array      $form  The Form Object.
	 * @param string     $value The field value. Defaults to empty string.
	 * @param null|array $entry The Entry Object. Defaults to null.
	 *
	 * @return string The List field HTML markup.
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {

		$form_id        = $form['id'];
		$is_form_editor = $this->is_form_editor();

		if ( ! empty( $value ) ) {
			$value = maybe_unserialize( $value );
		}

		if ( is_array( $value ) ) {
			if ( ! is_array( $value[0] ) ) {
				$value = $this->create_list_array( $value );
			}
		} else {
			$value = array( array() );
		}

		$has_columns = is_array( $this->choices );
		$columns     = $has_columns ? $this->choices : array( array() );

		$list = '';
		if ( ! self::$_style_block_printed ){
			// This style block needs to be inline so that the list field continues to work even if the option to turn off CSS output is activated.
			$list .= '<style type="text/css">

						body .ginput_container_list table.gfield_list tbody tr td.gfield_list_icons {
							vertical-align: middle !important;
						}

						body .ginput_container_list table.gfield_list tbody tr td.gfield_list_icons img {
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

						body .ginput_container_list table.gfield_list tbody tr td.gfield_list_icons a:hover img {
							opacity: 1.0;
						}

						</style>';

			self::$_style_block_printed = true;
		}

		$list .= "<div class='ginput_container ginput_container_list ginput_list'>" .
			"<table class='gfield_list gfield_list_container'>";

		if ( $has_columns ) {

			$list .= '<colgroup>';
			for ( $colnum = 1; $colnum <= count( $columns ) + 1; $colnum++ ) {
				$odd_even = ( $colnum % 2 ) == 0 ? 'even' : 'odd';
				$list .= sprintf( "<col id='gfield_list_%d_col_%d' class='gfield_list_col_%s' />", $this->id, $colnum, $odd_even );
			}
			$list .= '</colgroup>';

			$list .= '<thead><tr>';
			foreach ( $columns as $column ) {
				// a11y: scope="col"
				$list .= '<th scope="col">' . esc_html( $column['text'] ) . '</th>';
			}

			if ( $this->maxRows != 1 ) {
				// Using td instead of th because empty th tags break a11y.
				$list .= '<td>&nbsp;</td>';
			}

			$list .= '</tr></thead>';
		} else {
			$list .=
				'<colgroup>' .
					"<col id='gfield_list_{$this->id}_col1' class='gfield_list_col_odd' />" .
					"<col id='gfield_list_{$this->id}_col2' class='gfield_list_col_even' />" .
				'</colgroup>';
		}

		$delete_display      = count( $value ) == 1 ? 'style="visibility:hidden;"' : '';
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
				$list .= "   <a href='javascript:void(0);' class='add_list_item {$disabled_icon_class}' aria-label='" . esc_attr__( 'Add another row', 'gravityforms' ) . "' {$add_events}><img src='{$add_icon}' alt='' title='" . esc_attr__( 'Add a new row', 'gravityforms' ) . "' /></a>" .
				         "   <a href='javascript:void(0);' class='delete_list_item' aria-label='" . esc_attr__( 'Remove this row', 'gravityforms' ) . "' {$delete_events} {$delete_display}><img src='{$delete_icon}' alt='' title='" . esc_attr__( 'Remove this row', 'gravityforms' ) . "' /></a>";
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

		$column_text = rgar( $column, 'text' );

		$aria_label = isset( $column['text'] ) ? $column_text : $this->label;

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
		), $input_info, $this, $column_text, $value, $form_id );

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
				// a11y: inputs without a label must have the aria-label attribute set.
				$input = "<input aria-label='" . esc_attr( $aria_label ) . "' type='text' name='input_{$this->id}[]' value='" . esc_attr( $value ) . "' {$tabindex} {$disabled}/>";
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
	 * Whether this field expects an array during submission.
	 *
	 * @since 2.4
	 *
	 * @return bool
	 */
	public function is_value_submission_array() {
		return true;
	}

	/**
	 * Gets the value of te field from the form submission.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param array $field_values             The properties to search for.
	 * @param bool  $get_from_post_global_var If the global GET variable should be used to obtain the value. Defaults to true.
	 *
	 * @return array The submission value.
	 */
	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {
		$value = $this->get_input_value_submission( 'input_' . $this->id, $this->inputName, $field_values, $get_from_post_global_var );

		return $value;
	}

	/**
	 * Creates an array from the list items. Recurses if the field is inside a Repeater.
	 *
	 * @since 2.4
	 *
	 * @param $value
	 *
	 * @return array
	 */
	public function create_list_array_recursive( $value ) {
		if ( isset( $value[0] ) && is_array( $value[0] ) ) {
			$new_value = array();
			foreach ( $value  as $k => $v ) {
				$new_value[ $k ] = $this->create_list_array_recursive( $v );
			}
		} else {
			$new_value = $this->create_list_array( $value );
		}
		return $new_value;
	}

	/**
	 * Check if the submission value is empty.
	 *
	 * @since  Unknown
	 * @access public
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

		if( ! is_array( $value ) || ! isset( $value[0] ) ) {
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
							$list .= '<th scope="col">' . esc_html( $column ) . '</th>' . "\n";
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

		$modifiers = $this->get_modifiers();

		$allowed_modifiers = array( 'text', 'html', 'url' );

		if( $found_modifiers = array_intersect( $modifiers, $allowed_modifiers ) ) {
			$output_format = $found_modifiers[0];
		} else {
			$output_format = $format;
		}

		return GFCommon::get_lead_field_display( $this, $raw_value, $entry['currency'], true, $output_format );
	}

	/**
	 * Format the entry value for display on the entries list page.
	 *
	 * By default, the List field will not be available for selection on the entry list.
	 * Use the gform_display_field_select_columns_entry_list filter to make the list field available.
	 *
	 *
	 * @since 2.4
	 *
	 * @param string|array $value    The field value.
	 * @param array        $entry    The Entry Object currently being processed.
	 * @param string       $field_id The field or input ID currently being processed.
	 * @param array        $columns  The properties for the columns being displayed on the entry list page.
	 * @param array        $form     The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		return GFCommon::get_lead_field_display( $this, $value, $entry['currency'], true, 'html' );
	}

	/**
	 * Creates an array from the list items.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param array $value The pre-formatted list.
	 *
	 * @return array The list rows.
	 */
	function create_list_array( $value ) {
		if ( ! $this->enableColumns ) {
			return $value;
		} else {
			$value     = empty( $value ) ? array() : $value;
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
	 */
	public function sanitize_settings() {
		parent::sanitize_settings();
		$this->maxRows       = absint( $this->maxRows );
		$this->enableColumns = (bool) $this->enableColumns;
	}

	/**
	 * Gets the field value, formatted for exports. For CSV export return an array.
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
	 * @param bool   $is_csv   Is the value going to be used in the CSV export? Defaults to false.
	 *
	 * @return string|array
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
		$value = maybe_unserialize( $value );

		if ( empty( $value ) || $is_csv ) {
			return $value;
		}

		$list_values = $column_values = $value;

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

	// # FIELD FILTER UI HELPERS ---------------------------------------------------------------------------------------

	/**
	 * Returns the filter operators for the current field.
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public function get_filter_operators() {
		return array( 'contains' );
	}

}

// Register the list field.
GF_Fields::register( new GF_Field_List() );
