<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GFExport {

	private static $min_import_version = '1.3.12.3';

	/**
	 * Process the forms export request.
	 */
	public static function maybe_export() {
		if ( isset( $_POST['export_forms'] ) ) {
			check_admin_referer( 'gf_export_forms', 'gf_export_forms_nonce' );
			$selected_forms = rgpost( 'gf_form_id' );
			if ( empty( $selected_forms ) ) {
				GFCommon::add_error_message( __( 'Please select the forms to be exported', 'gravityforms' ) );

				return;
			}

			self::export_forms( $selected_forms );
		}
	}

	public static function export_forms( $form_ids ) {

		$forms = GFFormsModel::get_form_meta_by_id( $form_ids );
		$forms = self::prepare_forms_for_export( $forms );

		$forms['version'] = GFForms::$version;
		$forms_json       = json_encode( $forms );

		$filename = apply_filters( 'gform_form_export_filename', 'gravityforms-export-' . date( 'Y-m-d' ) ) . '.json';
		$filename = sanitize_file_name( $filename );
		header( 'Content-Description: File Transfer' );
		header( "Content-Disposition: attachment; filename=$filename" );
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ), true );
		echo $forms_json;
		die();
	}

	public static function export_page() {

		if ( ! GFCommon::ensure_wp_version() ) {
			return;
		}

		echo GFCommon::get_remote_message();

		$view = rgget( 'view' ) ? rgget( 'view' ) : 'export_entry';

		switch ( $view ) {

			case 'export_entry':
				self::export_lead_page();
				break;

			case 'import_form' :
				self::import_form_page();
				break;

			case 'export_form' :
				self::export_form_page();
				break;

			default:
				/**
				 * Fires when export pages are gathered
				 *
				 * Used to add additional export settings pages
				 *
				 * @param string $view Set when defining the action string.  Creates the name for the new page
				 */
				do_action( "gform_export_page_{$view}" );
				break;

		}

	}

	public static function import_file( $filepath, &$forms = null ) {
		$file_contents = file_get_contents( $filepath );

		if ( GFCommon::safe_substr( $file_contents, 0, 38 ) == '<?xml version="1.0" encoding="UTF-8"?>' ) {
			return self::import_xml( $file_contents, $forms );
		}

		return self::import_json( $file_contents, $forms );
	}

	public static function import_json( $forms_json, &$forms = null ) {

		// Remove any whitespace from before and after the JSON.
		$forms_json = trim( $forms_json );

		// Remove any extra characters from before the JSON.
		$json_start_position = strpos( $forms_json, '{' );
		if ( $json_start_position !== 0 ) {
			$forms_json = substr( $forms_json, $json_start_position );
		}

		$forms = json_decode( $forms_json, true );

		if ( ! $forms ) {
			GFCommon::log_debug( __METHOD__ . '(): Import Failed. Invalid form objects.' );

			return 0;
		} else if ( ! rgar( $forms, 'version' ) || version_compare( $forms['version'], self::$min_import_version, '<' ) ) {
			GFCommon::log_debug( __METHOD__ . '(): Import Failed. The JSON version is not compatible with the current Gravity Forms version.' );

			return - 1;
		} //Error. JSON version is not compatible with current Gravity Forms version

		unset( $forms['version'] );

		$form_ids = GFAPI::add_forms( $forms );

		if ( is_wp_error( $form_ids ) ) {
			GFCommon::log_debug( __METHOD__ . '(): Import Failed => ' . print_r( $form_ids, 1 ) );
			$form_ids = array();
		} else {
			foreach ( $form_ids as $key => $form_id ) {
				$forms[ $key ] = GFAPI::get_form( $form_id );
			}
			/**
			 * Fires after forms have been imported.
			 *
			 * Used to perform additional actions after import
			 *
			 * @param array $forms An array imported form objects.
			 *
			 */
			do_action( 'gform_forms_post_import', $forms );
		}

		return sizeof( $form_ids );
	}

	// This function is not deprecated as of 1.9 because it will still be needed for a while to import legacy XML files without generating deprecation notices.
	// However, XML is not used to export Forms so this function will soon be deprecated.
	public static function import_xml( $xmlstr, &$forms = null ) {

		require_once( 'xml.php' );

		$options = array(
			'page'         => array( 'unserialize_as_array' => true ),
			'form'         => array( 'unserialize_as_array' => true ),
			'field'        => array( 'unserialize_as_array' => true ),
			'rule'         => array( 'unserialize_as_array' => true ),
			'choice'       => array( 'unserialize_as_array' => true ),
			'input'        => array( 'unserialize_as_array' => true ),
			'routing_item' => array( 'unserialize_as_array' => true ),
			'creditCard'   => array( 'unserialize_as_array' => true ),
			'routin'       => array( 'unserialize_as_array' => true ), //routin is for backwards compatibility
			'confirmation' => array( 'unserialize_as_array' => true ),
			'notification' => array( 'unserialize_as_array' => true ),
		);
		$options = apply_filters( 'gform_import_form_xml_options', $options );
		$xml     = new RGXML( $options );
		$forms   = $xml->unserialize( $xmlstr );

		if ( ! $forms ) {
			return 0;
		} //Error. could not unserialize XML file
		else if ( version_compare( $forms['version'], self::$min_import_version, '<' ) ) {
			return - 1;
		} //Error. XML version is not compatible with current Gravity Forms version

		//cleaning up generated object
		self::cleanup( $forms );

		foreach ( $forms as $key => &$form ) {

			$title = $form['title'];
			$count = 2;
			while ( ! RGFormsModel::is_unique_title( $title ) ) {
				$title = $form['title'] . "($count)";
				$count ++;
			}

			//inserting form
			$form_id = RGFormsModel::insert_form( $title );

			//updating form meta
			$form['title'] = $title;
			$form['id']    = $form_id;

			$form = GFFormsModel::trim_form_meta_values( $form );

			if ( isset( $form['confirmations'] ) ) {
				$form['confirmations'] = self::set_property_as_key( $form['confirmations'], 'id' );
				$form['confirmations'] = GFFormsModel::trim_conditional_logic_values( $form['confirmations'], $form );
				GFFormsModel::update_form_meta( $form_id, $form['confirmations'], 'confirmations' );
				unset( $form['confirmations'] );
			}

			if ( isset( $form['notifications'] ) ) {
				$form['notifications'] = self::set_property_as_key( $form['notifications'], 'id' );
				$form['notifications'] = GFFormsModel::trim_conditional_logic_values( $form['notifications'], $form );
				GFFormsModel::update_form_meta( $form_id, $form['notifications'], 'notifications' );
				unset( $form['notifications'] );
			}

			RGFormsModel::update_form_meta( $form_id, $form );

		}

		return sizeof( $forms );
	}

	private static function cleanup( &$forms ) {
		unset( $forms['version'] );

		//adding checkboxes 'inputs' property based on 'choices'. (they were removed from the export
		//to provide a cleaner xml format
		foreach ( $forms as &$form ) {
			if ( ! is_array( $form['fields'] ) ) {
				continue;
			}
			$form = GFFormsModel::convert_field_objects( $form );

			foreach ( $form['fields'] as &$field ) {
				$input_type = RGFormsModel::get_input_type( $field );

				if ( in_array( $input_type, array( 'checkbox', 'radio', 'select', 'multiselect' ) ) ) {

					//creating inputs array for checkboxes
					if ( $input_type == 'checkbox' && ! isset( $field->inputs ) ) {
						$field->inputs = array();
					}

					$adjust_by = 0;
					for ( $i = 1, $count = sizeof( $field->choices ); $i <= $count; $i ++ ) {

						if ( ! $field->enableChoiceValue ) {
							$field->choices[ $i - 1 ]['value'] = $field->choices[ $i - 1 ]['text'];
						}

						if ( $input_type == 'checkbox' ) {
							if ( ( ( $i + $adjust_by ) % 10 ) == 0 ) {
								$adjust_by ++;
							}

							$id = $i + $adjust_by;

							$field->inputs[] = array( 'id' => $field->id . '.' . $id, 'label' => $field->choices[ $i - 1 ]['text'] );

						}
					}
				}
			}
		}
	}

	public static function set_property_as_key( $array, $property ) {
		$new_array = array();
		foreach ( $array as $item ) {
			$new_array[ $item[ $property ] ] = $item;
		}

		return $new_array;
	}

	public static function import_form_page() {

		if ( ! GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
			wp_die( 'You do not have permission to access this page' );
		}

		if ( isset( $_POST['import_forms'] ) ) {

			check_admin_referer( 'gf_import_forms', 'gf_import_forms_nonce' );

			if ( ! empty( $_FILES['gf_import_file']['tmp_name'][0] ) ) {

				// Set initial count to 0.
				$count = 0;

				// Loop through each uploaded file.
				foreach ( $_FILES['gf_import_file']['tmp_name'] as $import_file ) {
					$count += self::import_file( $import_file, $forms );
				}

				if ( $count == 0 ) {
					GFCommon::add_error_message( __( 'Forms could not be imported. Please make sure your export file is in the correct format.', 'gravityforms' ) );
				} else if ( $count == '-1' ) {
					GFCommon::add_error_message( __( 'Forms could not be imported. Your export file is not compatible with your current version of Gravity Forms.', 'gravityforms' ) );
				} else {
					$form_text = $count > 1 ? __( 'forms', 'gravityforms' ) : __( 'form', 'gravityforms' );
					$edit_link = $count == 1 ? "<a href='admin.php?page=gf_edit_forms&id={$forms[0]['id']}'>" . __( 'Edit Form', 'gravityforms' ) . '</a>' : '';
					GFCommon::add_message( sprintf( __( "Gravity Forms imported %d %s successfully", 'gravityforms' ), $count, $form_text ) . ". $edit_link" );
				}

			}

		}

		self::page_header( __( 'Import Forms', 'gravityforms' ) );

		?>

		<p class="textleft">
			<?php esc_html_e( 'Select the Gravity Forms export files you would like to import. When you click the import button below, Gravity Forms will import the forms.', 'gravityforms' ); ?>
		</p>

		<div class="hr-divider"></div>

		<form method="post" enctype="multipart/form-data" style="margin-top:10px;">
			<?php wp_nonce_field( 'gf_import_forms', 'gf_import_forms_nonce' ); ?>
			<table class="form-table">
				<tr valign="top">

					<th scope="row">
						<label for="gf_import_file"><?php esc_html_e( 'Select Files', 'gravityforms' ); ?></label> <?php gform_tooltip( 'import_select_file' ) ?>
					</th>
					<td><input type="file" name="gf_import_file[]" id="gf_import_file" multiple /></td>
				</tr>
			</table>
			<br /><br />
			<input type="submit" value="<?php esc_html_e( 'Import', 'gravityforms' ) ?>" name="import_forms" class="button button-large button-primary" />

		</form>

		<?php

		self::page_footer();

	}

	public static function export_form_page() {

		if ( ! GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
			wp_die( 'You do not have permission to access this page' );
		}

		self::page_header( __( 'Export Forms', 'gravityforms' ) );

		?>
		<script type="text/javascript">

			( function( $, window, undefined ) {

				$( document ).on( 'click keypress', '#gf_export_forms_all', function( e ) {
					
					var checked  = e.target.checked,
					    label    = $( 'label[for="gf_export_forms_all"]' ),
					    formList = $( '#export_form_list' );

					// Set label.
					label.find( 'strong' ).html( checked ? label.data( 'deselect' ) : label.data( 'select' ) );

					// Change checkbox status.
					$( 'input[name]', formList ).prop( 'checked', checked );

				} );

			}( jQuery, window ));

		</script>

		<p class="textleft"><?php esc_html_e( 'Select the forms you would like to export. When you click the download button below, Gravity Forms will create a JSON file for you to save to your computer. Once you\'ve saved the download file, you can use the Import tool to import the forms.', 'gravityforms' ); ?></p>
		<div class="hr-divider"></div>
		<form id="gform_export" method="post" style="margin-top:10px;">
			<?php wp_nonce_field( 'gf_export_forms', 'gf_export_forms_nonce' ); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<label for="export_fields"><?php esc_html_e( 'Select Forms', 'gravityforms' ); ?></label> <?php gform_tooltip( 'export_select_forms' ) ?>
					</th>
					<td>
						<ul id="export_form_list">
							<li>
								<input type="checkbox" id="gf_export_forms_all" />
								<label for="gf_export_forms_all" data-deselect="<?php esc_attr_e( 'Deselect All', 'gravityforms' ); ?>" data-select="<?php esc_attr_e( 'Select All', 'gravityforms' ); ?>"><strong><?php esc_html_e( 'Select All', 'gravityforms' ); ?></strong></label>
							</li>
							<?php
							$forms = RGFormsModel::get_forms( null, 'title' );

							/**
							 * Modify list of forms available for export.
							 *
							 * @since 2.4.7
							 *
							 * @param array $forms Forms to display on Export Forms page.
							 */
							$forms = apply_filters( 'gform_export_forms_forms', $forms );

							foreach ( $forms as $form ) {
								?>
								<li>
									<input type="checkbox" name="gf_form_id[]" id="gf_form_id_<?php echo absint( $form->id ) ?>" value="<?php echo absint( $form->id ) ?>" />
									<label for="gf_form_id_<?php echo absint( $form->id ) ?>"><?php echo esc_html( $form->title ) ?></label>
								</li>
							<?php
							}
							?>
						</ul>
					</td>
				</tr>
			</table>

			<br /><br />
			<input type="submit" value="<?php esc_attr_e( 'Download Export File', 'gravityforms' ) ?>" name="export_forms" class="button button-large button-primary" />
		</form>

		<?php

		self::page_footer();

	}

	public static function export_lead_page() {

		if ( ! GFCommon::current_user_can_any( 'gravityforms_export_entries' ) ) {
			wp_die( 'You do not have permission to access this page' );
		}

		self::page_header( __( 'Export Entries', 'gravityforms' ) );

		?>

		<script type="text/javascript">

			var gfSpinner;

			<?php GFCommon::gf_global(); ?>
			<?php GFCommon::gf_vars(); ?>

			function SelectExportForm(formId) {

				if (!formId)
					return;

				gfSpinner = new gfAjaxSpinner(jQuery('select#export_form'), gf_vars.baseUrl + '/images/spinner.gif', 'position: relative; top: 2px; left: 5px;');

				var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' )?>");
				mysack.execute = 1;
				mysack.method = 'POST';
				mysack.setVar("action", "rg_select_export_form");
				mysack.setVar("rg_select_export_form", "<?php echo wp_create_nonce( 'rg_select_export_form' ); ?>");
				mysack.setVar("form_id", formId);
				mysack.onError = function () {
					alert(<?php echo json_encode( __( 'Ajax error while selecting a form', 'gravityforms' ) ); ?>)
				};
				mysack.runAJAX();

				return true;
			}

			function EndSelectExportForm(aryFields, filterSettings) {

				gfSpinner.destroy();

				if (aryFields.length == 0) {
					jQuery("#export_field_container, #export_date_container, #export_submit_container").hide()
					return;
				}

				var fieldList = "<li><input id='select_all' type='checkbox' onclick=\"jQuery('.gform_export_field').attr('checked', this.checked); jQuery('#gform_export_check_all').html(this.checked ? '<strong><?php echo esc_js( __( 'Deselect All', 'gravityforms' ) ); ?></strong>' : '<strong><?php echo esc_js( __( 'Select All', 'gravityforms' ) ); ?></strong>'); \" onkeypress=\"jQuery('.gform_export_field').attr('checked', this.checked); jQuery('#gform_export_check_all').html(this.checked ? '<strong><?php echo esc_js( __( 'Deselect All', 'gravityforms' ) ); ?></strong>' : '<strong><?php echo esc_js( __( 'Select All', 'gravityforms' ) ); ?></strong>'); \"> <label id='gform_export_check_all' for='select_all'><strong><?php esc_html_e( 'Select All', 'gravityforms' ) ?></strong></label></li>";
				for (var i = 0; i < aryFields.length; i++) {
					fieldList += "<li><input type='checkbox' id='export_field_" + i + "' name='export_field[]' value='" + aryFields[i][0] + "' class='gform_export_field'> <label for='export_field_" + i + "'>" + aryFields[i][1] + "</label></li>";
				}
				jQuery("#export_field_list").html(fieldList);
				jQuery("#export_date_start, #export_date_end").datepicker({dateFormat: 'yy-mm-dd', changeMonth: true, changeYear: true});

				jQuery("#export_field_container, #export_filter_container, #export_date_container, #export_submit_container").hide().show();

				gf_vars.filterAndAny = <?php echo json_encode( esc_html__( 'Export entries if {0} of the following match:', 'gravityforms' ) ); ?>;
				jQuery("#export_filters").gfFilterUI(filterSettings);
			}

			( function( $, window, undefined ) {

				$(document).ready(function() {
					$("#submit_button").click(function () {
						if ($(".gform_export_field:checked").length == 0) {
							alert(<?php echo json_encode( __( 'Please select the fields to be exported', 'gravityforms' ) );  ?>);
							return false;
						}

						$(this).hide();
						$('#please_wait_container').show();
						process();

						return false;
					});
					
					$('#export_form').on('change', function() {
						SelectExportForm($(this).val());
					}).trigger('change');
				});

				function process( offset, exportId ) {

					if ( typeof offset == 'undefined' ) {
						offset = 0;
					}

					if ( typeof exportId == 'undefined' ) {
						exportId = 0;
					}

					var data = $('#gform_export').serialize();

					data += '&action=gf_process_export';
					data += '&offset=' + offset;
					data += '&exportId='+ exportId;
					$.ajax({
						type: 'POST',
						url: ajaxurl,
						data: data,
						dataType: 'json'
					}).done(function( response ){
							if ( response.status == 'in_progress' ) {
								$('#progress_container').text( response.progress );
								process( response.offset, response.exportId );
							} else if ( response.status == 'complete' ) {
								$('#progress_container').text('0%');
								$('#please_wait_container').hide();
								var formId = parseInt( $('#export_form').val() );
								var url = ajaxurl + '?action=gf_download_export&_wpnonce=<?php echo wp_create_nonce( 'gform_download_export' ); ?>&export-id=' + response.exportId + '&form-id=' + formId;
								$('#submit_button').fadeIn();
								document.location.href = url;
							}
						}
					);
				}

			}( jQuery, window ));


		</script>

		<p class="textleft"><?php esc_html_e( 'Select a form below to export entries. Once you have selected a form you may select the fields you would like to export and then define optional filters for field values and the date range. When you click the download button below, Gravity Forms will create a CSV file for you to save to your computer.', 'gravityforms' ); ?></p>
		<div class="hr-divider"></div>
		<form id="gform_export" method="post" style="margin-top:10px;">
			<?php echo wp_nonce_field( 'rg_start_export', 'rg_start_export_nonce' ); ?>
			<table class="form-table">
				<tr valign="top">

					<th scope="row">
						<label for="export_form"><?php esc_html_e( 'Select A Form', 'gravityforms' ); ?></label> <?php gform_tooltip( 'export_select_form' ) ?>
					</th>
					<td>

						<select id="export_form" name="export_form">
							<option value=""><?php esc_html_e( 'Select a form', 'gravityforms' ); ?></option>
							<?php
							$forms = RGFormsModel::get_forms( null, 'title' );

							/**
							 * Modify list of forms available to export entries from.
							 *
							 * @since 2.4.7
							 *
							 * @param array $forms Forms to display on Export Entries page.
							 */
							$forms = apply_filters( 'gform_export_entries_forms', $forms );

							foreach ( $forms as $form ) {
								?>
								<option value="<?php echo absint( $form->id ) ?>" <?php selected( rgget( 'id' ), $form->id ); ?>><?php echo esc_html( $form->title ) ?></option>
								<?php
							}
							?>
						</select>

					</td>
				</tr>
				<tr id="export_field_container" valign="top" style="display: none;">
					<th scope="row">
						<label for="export_fields"><?php esc_html_e( 'Select Fields', 'gravityforms' ); ?></label> <?php gform_tooltip( 'export_select_fields' ) ?>
					</th>
					<td>
						<ul id="export_field_list">
						</ul>
					</td>
				</tr>
				<tr id="export_filter_container" valign="top" style="display: none;">
					<th scope="row">
						<label><?php esc_html_e( 'Conditional Logic', 'gravityforms' ); ?></label> <?php gform_tooltip( 'export_conditional_logic' ) ?>
					</th>
					<td>
						<div id="export_filters">
							<!--placeholder-->
						</div>

					</td>
				</tr>
				<tr id="export_date_container" valign="top" style="display: none;">
					<th scope="row">
						<label for="export_date"><?php esc_html_e( 'Select Date Range', 'gravityforms' ); ?></label> <?php gform_tooltip( 'export_date_range' ) ?>
					</th>
					<td>
						<div>
                            <span style="width:150px; float:left; ">
                                <input type="text" id="export_date_start" name="export_date_start" style="width:90%" />
                                <strong><label for="export_date_start" style="display:block;"><?php esc_html_e( 'Start', 'gravityforms' ); ?></label></strong>
                            </span>

                            <span style="width:150px; float:left;">
                                <input type="text" id="export_date_end" name="export_date_end" style="width:90%" />
                                <strong><label for="export_date_end" style="display:block;"><?php esc_html_e( 'End', 'gravityforms' ); ?></label></strong>
                            </span>

							<div style="clear: both;"></div>
							<?php esc_html_e( 'Date Range is optional, if no date range is selected all entries will be exported.', 'gravityforms' ); ?>
						</div>
					</td>
				</tr>
			</table>
			<ul>
				<li id="export_submit_container" style="display:none; clear:both;">
					<br /><br />
					<button id="submit_button" class="button button-large button-primary"><?php esc_attr_e( 'Download Export File', 'gravityforms' ); ?></button>
                    <span id="please_wait_container" style="display:none; margin-left:15px;">
                        <i class='gficon-gravityforms-spinner-icon gficon-spin'></i> <?php esc_html_e( 'Exporting entries. Progress:', 'gravityforms' ); ?>
	                    <span id="progress_container">0%</span>
                    </span>
				</li>
			</ul>
		</form>

		<?php
		self::page_footer();
	}

	public static function get_field_row_count( $form, $exported_field_ids, $entry_count ) {
		$list_fields = GFAPI::get_fields_by_type( $form, array( 'list' ), true );

		//only getting fields that have been exported
		$field_ids = '';
		foreach ( $list_fields as $field ) {
			if ( in_array( $field->id, $exported_field_ids ) && $field->enableColumns ) {
				$field_ids .= $field->id . ',';
			}
		}

		if ( empty( $field_ids ) ) {
			return array();
		}

		$field_ids = substr( $field_ids, 0, strlen( $field_ids ) - 1 );

		$page_size = 200;
		$offset    = 0;

		$row_counts = array();
		global $wpdb;

		$go_to_next_page = true;

		while ( $go_to_next_page ) {

			if ( version_compare( GFFormsModel::get_database_version(), '2.3-dev-1', '<' ) ) {
				$sql = "SELECT d.field_number as field_id, d.value as value
                    FROM {$wpdb->prefix}rg_lead_detail d
                    WHERE d.form_id={$form['id']} AND cast(d.field_number as decimal) IN ({$field_ids})
                    LIMIT {$offset}, {$page_size}";
			} else {
				$sql = "SELECT d.meta_key as field_id, d.meta_value as value
                    FROM {$wpdb->prefix}gf_entry_meta d
                    WHERE d.form_id={$form['id']} AND d.meta_key IN ({$field_ids})
                    LIMIT {$offset}, {$page_size}";
			}


			$results = $wpdb->get_results( $sql, ARRAY_A );

			foreach ( $results as $result ) {
				$list              = unserialize( $result['value'] );
				$current_row_count = isset( $row_counts[ $result['field_id'] ] ) ? intval( $row_counts[ $result['field_id'] ] ) : 0;

				if ( is_array( $list ) && count( $list ) > $current_row_count ) {
					$row_counts[ $result['field_id'] ] = count( $list );
				}
			}

			$offset += $page_size;

			$go_to_next_page = count( $results ) == $page_size;
		}

		return $row_counts;
	}

	/**
	 * @deprecated No longer used.
	 */
	public static function get_gmt_timestamp( $local_timestamp ) {
		_deprecated_function( 'GFExport::get_gmt_timestamp', '2.0.7', 'GFCommon::get_gmt_timestamp' );

		return $local_timestamp - ( get_option( 'gmt_offset' ) * 3600 );
	}

	/**
	 * @deprecated No longer used.
	 */
	public static function get_gmt_date( $local_date ) {
		_deprecated_function( 'GFExport::get_gmt_date', '2.0.7' );

		$local_timestamp = strtotime( $local_date );
		$gmt_timestamp   = self::get_gmt_timestamp( $local_timestamp );
		$date            = gmdate( 'Y-m-d H:i:s', $gmt_timestamp );

		return $date;
	}

	public static function start_export( $form, $offset = 0, $export_id = '' ) {

		$time_start = microtime( true );

		/***
		 * Allows the export max execution time to be changed.
		 *
		 * When the max execution time is reached, the export routine stop briefly and submit another AJAX request to continue exporting entries from the point it stopped.
		 *
		 * @since 2.0.3.10
		 *
		 * @param int   20    The amount of time, in seconds, that each request should run for.  Defaults to 20 seconds.
		 * @param array $form The Form Object
		 */
		$max_execution_time = apply_filters( 'gform_export_max_execution_time', 20, $form ); // seconds
		$page_size          = 20;

		$form_id = $form['id'];
		$fields  = $_POST['export_field'];

		$start_date = rgpost( 'export_date_start' );
		$end_date   = rgpost( 'export_date_end' );

		$search_criteria['status']        = 'active';
		$search_criteria['field_filters'] = GFCommon::get_field_filters_from_post( $form );
		if ( ! empty( $start_date ) ) {
			$search_criteria['start_date'] = $start_date;
		}

		if ( ! empty( $end_date ) ) {
			$search_criteria['end_date'] = $end_date;
		}

		//$sorting = array( 'key' => 'date_created', 'direction' => 'DESC', 'type' => 'info' );
		$sorting = array( 'key' => 'id', 'direction' => 'DESC', 'type' => 'info' );

		$form = self::add_default_export_fields( $form );

		$total_entry_count     = GFAPI::count_entries( $form_id, $search_criteria );
		$remaining_entry_count = $offset == 0 ? $total_entry_count : $total_entry_count - $offset;

		$lines = '';

		// Set the separator
		$separator = gf_apply_filters( array( 'gform_export_separator', $form_id ), ',', $form_id );

		$field_rows = self::get_field_row_count( $form, $fields, $remaining_entry_count );

		if ( $offset == 0 ) {
			GFCommon::log_debug( __METHOD__ . '(): Processing request for form #' . $form_id );


			/**
			 * Allows the BOM character to be excluded from the beginning of entry export files.
			 * 
			 * @since 2.1.1.21
			 *
			 * @param bool  $include_bom Whether or not to include the BOM characters. Defaults to true.
			 * @param array $form        The Form Object.
			 */
			$include_bom = apply_filters( 'gform_include_bom_export_entries', true, $form );

			//Adding BOM marker for UTF-8
			$lines = $include_bom ? chr( 239 ) . chr( 187 ) . chr( 191 ) : '';

			//writing header
			$headers = array();
			foreach ( $fields as $field_id ) {
				$field = RGFormsModel::get_field( $form, $field_id );
				$label = gf_apply_filters( array( 'gform_entries_field_header_pre_export', $form_id, $field_id ), GFCommon::get_label( $field, $field_id ), $form, $field );
				$value = str_replace( '"', '""', $label );

				GFCommon::log_debug( "GFExport::start_export(): Header for field ID {$field_id}: {$value}" );

				if ( strpos( $value, '=' ) === 0 ) {
					// Prevent Excel formulas
					$value = "'" . $value;
				}

				$headers[ $field_id ] = $value;

				$subrow_count = isset( $field_rows[ $field_id ] ) ? intval( $field_rows[ $field_id ] ) : 0;
				if ( $subrow_count == 0 ) {
					$lines .= '"' . $value . '"' . $separator;
				} else {
					for ( $i = 1; $i <= $subrow_count; $i ++ ) {
						$lines .= '"' . $value . ' ' . $i . '"' . $separator;
					}
				}

				//GFCommon::log_debug( "GFExport::start_export(): Lines: {$lines}" );
			}
			$lines = substr( $lines, 0, strlen( $lines ) - 1 ) . "\n";

			if ( $remaining_entry_count == 0 ) {
				self::write_file( $lines, $export_id );
			}

			GFCommon::log_debug( __METHOD__ . '(): search criteria: ' . print_r( $search_criteria, true ) );
			GFCommon::log_debug( __METHOD__ . '(): sorting: ' . print_r( $sorting, true ) );
		}

		// Paging through results for memory issues
		while ( $remaining_entry_count > 0 ) {

			$paging = array(
				'offset'    => $offset,
				'page_size' => $page_size,
			);

			GFCommon::log_debug( __METHOD__ . '(): paging: ' . print_r( $paging, true ) );

			$leads = GFAPI::get_entries( $form_id, $search_criteria, $sorting, $paging );

			$leads = gf_apply_filters( array( 'gform_leads_before_export', $form_id ), $leads, $form, $paging );

			foreach ( $leads as $lead ) {
				$line = self::get_entry_export_line( $lead, $form, $fields, $field_rows, $separator );
				/**
				 * Filter the current line being exported.
				 *
				 * @since 2.4.11.5
				 *
				 * @param string   $line       The current line being exported.
				 * @param array    $form       The current form object.
				 * @param array    $fields     An array of field IDs to be exported.
				 * @param array    $field_rows An array of List fields
				 * @param array    $entry      The current entry.
				 * @param string   $separator  The separator
				 */
				$line = apply_filters( 'gform_export_line', $line, $form, $fields, $field_rows, $lead, $separator );
				$lines .= "$line\n";
			}

			$offset += $page_size;
			$remaining_entry_count -= $page_size;

			if ( ! seems_utf8( $lines ) ) {
				$lines = utf8_encode( $lines );
			}

			$lines = apply_filters( 'gform_export_lines', $lines );

			self::write_file( $lines, $export_id );

			$time_end       = microtime( true );
			$execution_time = ( $time_end - $time_start );

			if ( $execution_time >= $max_execution_time ) {
				break;
			}

			$lines = '';
		}

		$complete = $remaining_entry_count <= 0;

		if ( $complete ) {
			/**
			 * Fires after exporting all the entries in form
			 *
			 * @since 2.4.5.11 Added the $export_id param.
			 * @since 1.9.3
			 *
			 * @param array  $form       The Form object to get the entries from
			 * @param string $start_date The start date for when the export of entries should take place
			 * @param string $end_date   The end date for when the export of entries should stop
			 * @param array  $fields     The specified fields where the entries should be exported from
			 * @param string $export_id  A unique ID for the export.
			 */
			do_action( 'gform_post_export_entries', $form, $start_date, $end_date, $fields, $export_id );
		}

		$offset = $complete ? 0 : $offset;

		$status = array(
			'status'   => $complete ? 'complete' : 'in_progress',
			'offset'   => $offset,
			'exportId' => $export_id,
			'progress' => $remaining_entry_count > 0 ? intval( 100 - ( $remaining_entry_count / $total_entry_count ) * 100 ) . '%' : '',
		);

		GFCommon::log_debug( __METHOD__ . '(): Status: ' . print_r( $status, 1 ) );

		return $status;
	}

	/**
	 * Returns the content to be included in the export for the supplied entry.
	 *
	 * @since 2.4.5.11
	 *
	 * @param array  $entry      The entry being exported.
	 * @param array  $form       The form associated with the current entry.
	 * @param array  $fields     The IDs of the fields to be exported.
	 * @param array  $field_rows An array of List fields.
	 * @param string $separator  The character to be used as the column separator.
	 *
	 * @return string
	 */
	public static function get_entry_export_line( $entry, $form, $fields, $field_rows, $separator ) {
		GFCommon::log_debug( __METHOD__ . '(): Processing entry #' . $entry['id'] );

		$line = '';

		foreach ( $fields as $field_id ) {
			switch ( $field_id ) {
				case 'date_created' :
				case 'payment_date' :
					$value = $entry[ $field_id ];
					if ( $value ) {
						$lead_gmt_time   = mysql2date( 'G', $value );
						$lead_local_time = GFCommon::get_local_timestamp( $lead_gmt_time );
						$value           = date_i18n( 'Y-m-d H:i:s', $lead_local_time, true );
					}
					break;
				default :
					$field = GFAPI::get_field( $form, $field_id );

					$value = is_object( $field ) ? $field->get_value_export( $entry, $field_id, false, true ) : rgar( $entry, $field_id );
					$value = apply_filters( 'gform_export_field_value', $value, $form['id'], $field_id, $entry );
					break;
			}

			if ( isset( $field_rows[ $field_id ] ) ) {
				$list = empty( $value ) ? array() : $value;

				foreach ( $list as $row ) {
					if ( is_array( $row ) ) {
						// Entry from a multi-column list field.
						$row_values = array_values( $row );
						$row_str    = implode( '|', $row_values );
					} else {
						// Entry from a standard list field.
						$row_str = $row;
					}

					if ( strpos( $row_str, '=' ) === 0 ) {
						// Prevent Excel formulas
						$row_str = "'" . $row_str;
					}

					$line .= '"' . str_replace( '"', '""', $row_str ) . '"' . $separator;
				}

				//filling missing subrow columns (if any)
				$missing_count = intval( $field_rows[ $field_id ] ) - count( $list );
				for ( $i = 0; $i < $missing_count; $i ++ ) {
					$line .= '""' . $separator;
				}
			} else {
				if ( is_array( $value ) ) {
					if ( ! empty( $value[0] ) && is_array( $value[0] ) ) {
						// Entry from a multi-column list field.
						$values = array();
						foreach ( $value as $item ) {
							$values[] = implode( '|', array_values( $item ) );
						}

						$value = implode( ',', $values );
					} else {
						// Entry from a standard list field.
						$value = implode( '|', $value );
					}
				}

				if ( strpos( $value, '=' ) === 0 ) {
					// Prevent Excel formulas
					$value = "'" . $value;
				}

				$line .= '"' . str_replace( '"', '""', $value ) . '"' . $separator;
			}
		}

		$line = substr( $line, 0, strlen( $line ) - 1 );

		return $line;
	}

	public static function add_default_export_fields( $form ) {

		//adding default fields
		array_push( $form['fields'], array( 'id' => 'created_by', 'label' => __( 'Created By (User Id)', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'id', 'label' => __( 'Entry Id', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'date_created', 'label' => __( 'Entry Date', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'source_url', 'label' => __( 'Source Url', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'transaction_id', 'label' => __( 'Transaction Id', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'payment_amount', 'label' => __( 'Payment Amount', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'payment_date', 'label' => __( 'Payment Date', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'payment_status', 'label' => __( 'Payment Status', 'gravityforms' ) ) );
		//array_push($form['fields'],array('id' => 'payment_method' , 'label' => __('Payment Method', 'gravityforms'))); //wait until all payment gateways have been released
		array_push( $form['fields'], array( 'id' => 'post_id', 'label' => __( 'Post Id', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'user_agent', 'label' => __( 'User Agent', 'gravityforms' ) ) );
		array_push( $form['fields'], array( 'id' => 'ip', 'label' => __( 'User IP', 'gravityforms' ) ) );
		$form = self::get_entry_meta( $form );

		$form = apply_filters( 'gform_export_fields', $form );
		$form = GFFormsModel::convert_field_objects( $form );

		foreach ( $form['fields'] as $field ) {
			/* @var GF_Field $field */

			$field->set_context_property( 'use_admin_label', true );
		}

		return $form;
	}

	private static function get_entry_meta( $form ) {
		$entry_meta = GFFormsModel::get_entry_meta( $form['id'] );
		$keys       = array_keys( $entry_meta );
		foreach ( $keys as $key ) {
			array_push( $form['fields'], array( 'id' => $key, 'label' => $entry_meta[ $key ]['label'] ) );
		}

		return $form;
	}

	public static function page_header( $title = '' ) {

		// Print admin styles.
		wp_print_styles( array( 'jquery-ui-styles', 'gform_admin' ) );

		$current_tab  = rgempty( 'view', $_GET ) ? 'export_entry' : rgget( 'view' );
		$setting_tabs = self::get_tabs();

		// kind of boring having to pass the title, optionally get it from the settings tab
		if ( ! $title ) {
			foreach ( $setting_tabs as $tab ) {
				if ( $tab['name'] == $current_tab ) {
					$title = $tab['name'];
				}
			}
		}

		?>


		<div class="wrap <?php echo sanitize_html_class( $current_tab ); ?>">

		<h2><?php echo esc_html( $title ) ?></h2>

			<?php GFCommon::display_dismissible_message(); ?>

		<?php GFCommon::display_admin_message(); ?>

		<div id="gform_tab_group" class="gform_tab_group vertical_tabs">
		<ul id="gform_tabs" class="gform_tabs">
			<?php
			foreach ( $setting_tabs as $tab ) {

				$query = array( 'view' => $tab['name'] );
				if ( isset( $tab['query'] ) ) {
					$query = array_merge( $query, $tab['query'] );
				}

				$url = add_query_arg( $query );
				?>
				<li <?php echo $current_tab == $tab['name'] ? "class='active'" : '' ?>>
					<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $tab['label'] ) ?></a>
				</li>
			<?php
			}
			?>
		</ul>

		<div id="gform_tab_container" class="gform_tab_container">
		<div class="gform_tab_content" id="tab_<?php echo esc_attr( $current_tab ); ?>">

	<?php
	}

	public static function page_footer() {
		?>
		</div> <!-- / gform_tab_content -->
		</div> <!-- / gform_tab_container -->
		</div> <!-- / gform_tab_group -->

		<br class="clear" style="clear: both;" />

		</div> <!-- / wrap -->
	<?php
	}

	public static function get_tabs() {

		$setting_tabs = array();
		if ( GFCommon::current_user_can_any( 'gravityforms_export_entries' ) ) {
			$setting_tabs['10'] = array( 'name' => 'export_entry', 'label' => __( 'Export Entries', 'gravityforms' ) );
		}

		if ( GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
			$setting_tabs['20'] = array( 'name' => 'export_form', 'label' => __( 'Export Forms', 'gravityforms' ) );

			if ( GFCommon::current_user_can_any( 'gravityforms_create_form' ) ) {
				$setting_tabs['30'] = array( 'name' => 'import_form', 'label' => __( 'Import Forms', 'gravityforms' ) );
			}
		}



		$setting_tabs = apply_filters( 'gform_export_menu', $setting_tabs );
		ksort( $setting_tabs, SORT_NUMERIC );

		return $setting_tabs;
	}

	/**
	 * Handles the export request from the export entries page.
	 *
	 * @since 2.0.0
	 */
	public static function ajax_process_export() {
		check_admin_referer( 'rg_start_export', 'rg_start_export_nonce' );

		if ( ! GFCommon::current_user_can_any( 'gravityforms_export_entries' ) ) {
			die();
		}

		$offset = absint( rgpost( 'offset' ) );
		$export_id = sanitize_key( ( rgpost( 'exportId' ) ) );

		$form_id = $_POST['export_form'];
		$form    = RGFormsModel::get_form_meta( $form_id );

		if ( empty( $export_id ) ) {
			$export_id = wp_hash( uniqid( 'export', true ) );
			$export_id = sanitize_key( $export_id );
		}

		$status = self::start_export( $form, $offset, $export_id );

		echo json_encode( $status );
		die();
	}

	/**
	 * Appends lines to to the csv file for the given Export ID.
	 *
	 * @param string $lines
	 * @param string $export_id A unique ID for the export.
	 */
	public static function write_file( $lines, $export_id ) {

		$uploads_folder = RGFormsModel::get_upload_root();
		if ( ! is_dir( $uploads_folder ) ) {
			wp_mkdir_p( $uploads_folder );
		}

		$export_folder = $uploads_folder . 'export';
		if ( ! is_dir( $export_folder ) ) {
			wp_mkdir_p( $export_folder );
		}

		$export_folder = trailingslashit( $export_folder );

		self::maybe_create_htaccess_file( $export_folder );
		self::maybe_create_index_file( $export_folder );

		$file = $export_folder . sanitize_file_name( 'export-' . $export_id .'.csv' );

		GFCommon::log_debug( __METHOD__ . '(): Writing to file.' );
		$result = file_put_contents( $file, $lines, FILE_APPEND );
		if ( $result === false ) {
			GFCommon::log_error( __METHOD__ . '(): An issue occurred whilst writing to the file.' );
		} else {
			GFCommon::log_debug( __METHOD__ . '(): Number of bytes written to the file: ' . print_r( $result, 1 ) );
		}

	}

	/**
	 * Creates an .htaccess file in the given path which will disable access to all files on Apache Web Servers.
	 *
	 * @since 2.0.0
	 *
	 * @param $path
	 */
	public static function maybe_create_htaccess_file( $path ) {
		$htaccess_file = $path . '.htaccess';
		if ( file_exists( $htaccess_file ) ) {
			return;
		}
		$txt = '# Disable access to files via Apache web servers.
deny from all';
		$rules = explode( "\n", $txt );

		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/misc.php' );
		}
		insert_with_markers( $htaccess_file, 'Gravity Forms', $rules );
	}

	/**
	 * Adds an empty index file in the given path if it doesn't exist already.
	 *
	 * @since 2.0.0
	 *
	 * @param $path
	 */
	public static function maybe_create_index_file( $path ) {
		$path = untrailingslashit( $path );
		$index_file = $path . '/index.html';
		if ( file_exists( $index_file ) ) {
			return;
		}
		GFCommon::recursive_add_index_file( $path );
	}

	/**
	 * Handles the download request from the export entries page.
	 *
	 * @since 2.0.0
	 */
	public static function ajax_download_export() {
		check_admin_referer( 'gform_download_export' );

		if ( ! function_exists( 'readfile' ) ) {
			GFCommon::log_error( __METHOD__ . '(): Aborting. The PHP readfile function is not available.' );
			die( esc_html__( 'The PHP readfile function is not available, please contact the web host.', 'gravityforms' ) );
		}

		if ( ! GFCommon::current_user_can_any( 'gravityforms_export_entries' ) ) {
			die();
		}

		$form_id = absint( rgget( 'form-id' ) );

		if ( empty( $form_id ) ) {
			die();
		}

		$form = GFAPI::get_form( $form_id );

		if ( empty( $form ) ) {
			die();
		}

		$filename = sanitize_title_with_dashes( $form['title'] ) . '-' . gmdate( 'Y-m-d', GFCommon::get_local_timestamp( time() ) ) . '.csv';

		GFCommon::log_debug( __METHOD__ . '(): Starting download of file: ' . $filename );

		$charset = get_option( 'blog_charset' );
		header( 'Content-Description: File Transfer' );
		header( "Content-Disposition: attachment; filename=$filename" );
		header( 'Content-Type: text/csv; charset=' . $charset, true );
		$buffer_length = ob_get_length(); //length or false if no buffer
		if ( $buffer_length > 1 ) {
			ob_clean();
		}

		if ( has_filter( 'sanitize_file_name' ) ) {
			GFCommon::log_debug( __METHOD__ . '(): The WordPress sanitize_file_name filter has been detected.' );
		}

		$export_folder = RGFormsModel::get_upload_root() . 'export/';
		$export_id     = rgget( 'export-id' );
		$file          = $export_folder . sanitize_file_name( 'export-' . $export_id . '.csv' );
		$result        = readfile( $file );

		if ( $result === false ) {
			GFCommon::log_error( __METHOD__ . '(): An issue occurred whilst reading the file.' );
		} else {
			@unlink( $file );
			GFCommon::log_debug( __METHOD__ . '(): Number of bytes read from the file: ' . print_r( $result, 1 ) );
		}

		exit;
	}

	public static function prepare_forms_for_export( $forms ) {
		// clean up a bit before exporting
		foreach ( $forms as &$form ) {

			foreach ( $form['fields'] as &$field ) {
				$inputType = RGFormsModel::get_input_type( $field );

				if ( isset( $field->pageNumber ) ) {
					unset( $field->pageNumber );
				}

				if ( $inputType != 'address' ) {
					unset( $field->addressType );
				}

				if ( $inputType != 'date' ) {
					unset( $field->calendarIconType );
					unset( $field->dateType );
				}

				if ( $inputType != 'creditcard' ) {
					unset( $field->creditCards );
				}

				if ( $field->type == $field->inputType ) {
					unset( $field->inputType );
				}

				// convert associative array to indexed
				if ( isset( $form['confirmations'] ) ) {
					$form['confirmations'] = array_values( $form['confirmations'] );
				}

				if ( isset( $form['notifications'] ) ) {
					$form['notifications'] = array_values( $form['notifications'] );
				}
			}

			/**
			 * Allows you to filter and modify the Export Form
			 *
			 * @param array $form Assign which Gravity Form to change the export form for
			 */
			$form = gf_apply_filters( array( 'gform_export_form', $form['id'] ), $form );

		}

		$forms['version'] = GFForms::$version;

		return $forms;
	}
}

