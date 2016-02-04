<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class GF_Field_List extends GF_Field {

	public $type = 'list';

	public function get_form_editor_field_title() {
		return esc_attr__( 'List', 'gravityforms' );
	}

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

	public function get_first_input_id( $form ) {
		return ! $this->is_form_editor() ? sprintf( 'input_%s_%s_shim', $form['id'], $this->id ) : '';
	}

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

		$list = "<div class='ginput_container ginput_container_list ginput_list'>" .
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

		$add_icon    = ! empty( $this->addIconUrl ) ? $this->addIconUrl : GFCommon::get_base_url() . '/images/blankspace.png';
		$delete_icon = ! empty( $this->deleteIconUrl ) ? $this->deleteIconUrl : GFCommon::get_base_url() . '/images/blankspace.png';

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

				//getting value. taking into account columns being added/removed from form meta
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

				// can't replace these icons with the webfont versions since they appear on the front end.

				$list .= "<td class='gfield_list_icons'>";
				$list .= "   <img src='{$add_icon}' class='add_list_item {$disabled_icon_class}' {$disabled_text} title='" . esc_attr__( 'Add another row', 'gravityforms' ) . "' alt='" . esc_attr__( 'Add a row', 'gravityforms' ) . "' {$add_events} style='cursor:pointer; margin:0 3px;' " . $this->get_tabindex() . "/>" .
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
		$list .= $this->maxRows != 1 ? $this->get_svg_image_block() : '';
		$list .= '</table></div>';

		return $list;

	}

	public function get_svg_image_block() {
		global $_has_image_block;

		//return image block once per page load
		if ( ! $_has_image_block ) {

			$_has_image_block = true;
			return '
					<style type="text/css">

					/* add SVG background image support for retina devices -------------------------------*/

					img.add_list_item {
						background-image: url(data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz48IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9HcmFwaGljcy9TVkcvMS4xL0RURC9zdmcxMS5kdGQiPjxzdmcgdmVyc2lvbj0iMS4xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB3aWR0aD0iNTEyIiBoZWlnaHQ9IjUxMiIgdmlld0JveD0iMCAwIDUxMiA1MTIiPjxnIGlkPSJpY29tb29uLWlnbm9yZSI+PC9nPjxwYXRoIGQ9Ik0yNTYgNTEyYy0xNDEuMzc1IDAtMjU2LTExNC42MDktMjU2LTI1NnMxMTQuNjI1LTI1NiAyNTYtMjU2YzE0MS4zOTEgMCAyNTYgMTE0LjYwOSAyNTYgMjU2cy0xMTQuNjA5IDI1Ni0yNTYgMjU2ek0yNTYgNjRjLTEwNi4wMzEgMC0xOTIgODUuOTY5LTE5MiAxOTJzODUuOTY5IDE5MiAxOTIgMTkyYzEwNi4wNDcgMCAxOTItODUuOTY5IDE5Mi0xOTJzLTg1Ljk1My0xOTItMTkyLTE5MnpNMjg4IDM4NGgtNjR2LTk2aC05NnYtNjRoOTZ2LTk2aDY0djk2aDk2djY0aC05NnY5NnoiPjwvcGF0aD48L3N2Zz4=);
					}

					img.delete_list_item {
						background-image: url(data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz48IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9HcmFwaGljcy9TVkcvMS4xL0RURC9zdmcxMS5kdGQiPjxzdmcgdmVyc2lvbj0iMS4xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB3aWR0aD0iNTEyIiBoZWlnaHQ9IjUxMiIgdmlld0JveD0iMCAwIDUxMiA1MTIiPjxnIGlkPSJpY29tb29uLWlnbm9yZSI+PC9nPjxwYXRoIGQ9Ik0yNTYgMGMtMTQxLjM3NSAwLTI1NiAxMTQuNjI1LTI1NiAyNTYgMCAxNDEuMzkxIDExNC42MjUgMjU2IDI1NiAyNTYgMTQxLjM5MSAwIDI1Ni0xMTQuNjA5IDI1Ni0yNTYgMC0xNDEuMzc1LTExNC42MDktMjU2LTI1Ni0yNTZ6TTI1NiA0NDhjLTEwNi4wMzEgMC0xOTItODUuOTY5LTE5Mi0xOTJzODUuOTY5LTE5MiAxOTItMTkyYzEwNi4wNDcgMCAxOTIgODUuOTY5IDE5MiAxOTJzLTg1Ljk1MyAxOTItMTkyIDE5MnpNMTI4IDI4OGgyNTZ2LTY0aC0yNTZ2NjR6Ij48L3BhdGg+PC9zdmc+);
					}

					img.add_list_item,
					img.delete_list_item {
						width: 1em;
						height: 1em;
						background-size: 1em 1em;
						opacity: 0.5;
					}

					img.add_list_item:hover,
					img.add_list_item:active,
					img.delete_list_item:hover,
					img.delete_list_item:active {
						opacity: 1.0;
					}

					</style>
				';
		}

		return '';
	}

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

		return gf_apply_filters( array(
			'gform_column_input_content',
			$form_id,
			$this->id,
			$column_index
		), $input, $input_info, $this, rgar( $column, 'text' ), $value, $form_id );

	}

	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {
		$value = $this->get_input_value_submission( 'input_' . $this->id, $this->inputName, $field_values, $get_from_post_global_var );
		//allow the value to be an array of row arrays in addition to the array of rows
		//EX: new format allowed for pre-populating list field with hook - format generated by create_list_array
		//array(
			//array(
				//'Column 1' => 'row1col1',
				//'Column 2' => 'row1col2',
				//'Column 3' => 'row1col3',
			//),
			//array(
			//	'Column 1' => 'row2col1',
			//	'Column 2' => 'row2col2',
			//	'Column 3' => 'row2col3'
			//),
		//old format still checked for and re-formatted in create_list_array:
			//array(
				//'row 1 - col1', 'row 1 - col2', 'row 1 - col3',
				//'row 2 - col1', 'row 2 - col2', 'row 2 - col3',
				//'row 3 - col1', 'row 3 - col2', 'row 3 - col3'
			//);
		if ( is_array( rgar( $value, 0 ) ) ){
			//already in correct format, return value unchanged
			return $value;
		}

		//not already in the correct format
		$value = $this->create_list_array( $value );

		return $value;
	}

	public function is_value_submission_empty( $form_id ) {
		$value = rgpost( 'input_' . $this->id );
		if ( is_array( $value ) ) {
			//empty if all inputs are empty (for inputs with the same name)
			foreach ( $value as $input ) {
				if ( strlen( trim( $input ) ) > 0 ) {
					return false;
				}
			}
		}
		return true;
	}

	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		if ( empty( $value ) ) {
			return '';
		}
		$value = unserialize( $value );

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
				return substr( $items, 0, strlen( $items ) - 2 ); //removing last comma
			} elseif ( $format == 'url' ) {
				return substr( $items, 0, strlen( $items ) - 1 ); //removing last comma
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

						//reading columns from entry data
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

	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {

		if ( $this->adminOnly && $this->allowsPrepopulate ) {
			$value = json_decode( $value );
		}

		if ( GFCommon::is_empty_array( $value ) ) {
			$value = '';
		} else {
			$value = $this->create_list_array( $value );
			$value = serialize( $value );
		}

		return $value;
	}

	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		$output_format = in_array( $modifier, array( 'text', 'html', 'url' ) ) ? $modifier : $format;

		return GFCommon::get_lead_field_display( $this, $raw_value, $entry['currency'], true, $output_format );
	}


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

	public function sanitize_settings() {
		parent::sanitize_settings();
		$this->maxRows = absint( $this->maxRows );
	}

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

GF_Fields::register( new GF_Field_List() );