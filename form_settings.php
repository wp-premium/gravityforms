<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GFFormSettings {

	public static function form_settings_page() {

		$subview = rgget( 'subview' ) ? rgget( 'subview' ) : 'settings';

		switch ( $subview ) {
			case 'settings':
				self::form_settings_ui();
				break;
			case 'confirmation':
				self::confirmations_page();
				break;
			case 'notification':
				self::notification_page();
				break;
			default:
				do_action( "gform_form_settings_page_{$subview}" );
		}

	}

	public static function form_settings_ui() {

		require_once( GFCommon::get_base_path() . '/form_detail.php' );
		require_once( GFCommon::get_base_path() . '/currency.php' );

		$form_id       = rgget( 'id' );
		$form          = RGFormsModel::get_form_meta( $form_id );
		$update_result = array();

		if ( rgpost( 'gform_meta' ) ) {

			// die if not posted from correct page
			check_admin_referer( "gform_save_form_settings_{$form_id}", 'gform_save_form_settings' );

			$updated_form           = json_decode( rgpost( 'gform_meta' ), true );
			$updated_form['fields'] = $form['fields'];

			// -- standard form settings --

			$updated_form['title']                = rgpost( 'form_title_input' );
			$updated_form['description']          = rgpost( 'form_description_input' );
			$updated_form['labelPlacement']       = rgpost( 'form_label_placement' );
			$updated_form['descriptionPlacement'] = rgpost( 'form_description_placement' );
			$updated_form['subLabelPlacement']    = rgpost( 'form_sub_label_placement' );

			// -- advanced form settings --

			$updated_form['cssClass']        = rgpost( 'form_css_class' );
			$updated_form['enableHoneypot']  = rgpost( 'form_enable_honeypot' );
			$updated_form['enableAnimation'] = rgpost( 'form_enable_animation' );

			// form button settings
			$updated_form['button']['type']     = rgpost( 'form_button' );
			$updated_form['button']['text']     = rgpost( 'form_button' ) == 'text' ? rgpost( 'form_button_text_input' ) : '';
			$updated_form['button']['imageUrl'] = rgpost( 'form_button' ) == 'image' ? rgpost( 'form_button_image_url' ) : '';

			// Save and Continue settings
			$updated_form['save']['enabled']        = rgpost( 'form_save_enabled' );
			$updated_form['save']['button']['type'] = 'link';
			$updated_form['save']['button']['text'] = rgpost( 'form_save_button_text' );


			// limit entries settings
			$updated_form['limitEntries']        = rgpost( 'form_limit_entries' );
			$updated_form['limitEntriesCount']   = $updated_form['limitEntries'] ? rgpost( 'form_limit_entries_count' ) : '';
			$updated_form['limitEntriesPeriod']  = $updated_form['limitEntries'] ? rgpost( 'form_limit_entries_period' ) : '';
			$updated_form['limitEntriesMessage'] = $updated_form['limitEntries'] ? rgpost( 'form_limit_entries_message' ) : '';

			// form scheduling settings
			$updated_form['scheduleForm']           = rgpost( 'form_schedule_form' );
			$updated_form['scheduleStart']          = $updated_form['scheduleForm'] ? rgpost( 'gform_schedule_start' ) : '';
			$updated_form['scheduleStartHour']      = $updated_form['scheduleForm'] ? rgpost( 'form_schedule_start_hour' ) : '';
			$updated_form['scheduleStartMinute']    = $updated_form['scheduleForm'] ? rgpost( 'form_schedule_start_minute' ) : '';
			$updated_form['scheduleStartAmpm']      = $updated_form['scheduleForm'] ? rgpost( 'form_schedule_start_ampm' ) : '';
			$updated_form['scheduleEnd']            = $updated_form['scheduleForm'] ? rgpost( 'form_schedule_end' ) : '';
			$updated_form['scheduleEndHour']        = $updated_form['scheduleForm'] ? rgpost( 'form_schedule_end_hour' ) : '';
			$updated_form['scheduleEndMinute']      = $updated_form['scheduleForm'] ? rgpost( 'form_schedule_end_minute' ) : '';
			$updated_form['scheduleEndAmpm']        = $updated_form['scheduleForm'] ? rgpost( 'form_schedule_end_ampm' ) : '';
			$updated_form['schedulePendingMessage'] = $updated_form['scheduleForm'] ? rgpost( 'form_schedule_pending_message' ) : '';
			$updated_form['scheduleMessage']        = $updated_form['scheduleForm'] ? rgpost( 'form_schedule_message' ) : '';

			// require login settings
			$updated_form['requireLogin']        = rgpost( 'form_require_login' );
			$updated_form['requireLoginMessage'] = $updated_form['requireLogin'] ? rgpost( 'form_require_login_message' ) : '';

			$updated_form = GFFormsModel::maybe_sanitize_form_settings( $updated_form );

			if ( $updated_form['save']['enabled'] ) {
				$updated_form = self::activate_save( $updated_form );
			} else {
				$updated_form = self::deactivate_save( $updated_form );
			}

			$updated_form = apply_filters( 'gform_pre_form_settings_save', $updated_form );

			$update_result = GFFormDetail::save_form_info( $form_id, addslashes( json_encode( $updated_form ) ) );

			// update working form object with updated form object
			$form = $updated_form;
		}

		$form = apply_filters( 'gform_admin_pre_render_' . $form_id, apply_filters( 'gform_admin_pre_render', $form ) );

		self::page_header( __( 'Form Settings', 'gravityforms' ) );

		?>

		<script type="text/javascript">

			<?php GFCommon::gf_global(); ?>

			var form = <?php echo json_encode( $form ); ?>;
			var fieldSettings = [];

			jQuery(document).ready(function ($) {

				HandleUnsavedChanges('#gform_form_settings');

				jQuery('.datepicker').datepicker({showOn: 'both', changeMonth: true, changeYear: true, buttonImage: "<?php echo GFCommon::get_base_url() ?>/images/calendar.png", buttonImageOnly: true});

				ToggleConditionalLogic(true, 'form_button');

				jQuery('tr:hidden .gf_animate_sub_settings').hide();

				jQuery(document).trigger('gform_load_form_settings', [form]);

			});

			/**
			 * New Form Settings Functions
			 */

			function SaveFormSettings() {

				hasUnsavedChanges = false;

				// allow users to update form with custom function before save
				if (window['gform_before_update']) {
					form = window['gform_before_update'](form);
					if (window.console)
						console.log('"gform_before_update" is deprecated since version 1.7! Use "gform_pre_form_settings_save" php hook instead.');
				}

				// set fields to empty array to avoid issues with post data being too long
				form.fields = [];

				jQuery("#gform_meta").val(jQuery.toJSON(form));
				jQuery("form#gform_form_settings").submit();

			}

			function UpdateLabelPlacement() {
				var placement = jQuery("#form_label_placement").val();

				if (placement == 'top_label') {
					jQuery('#description_placement_setting').show('slow');
				}
				else {
					jQuery('#description_placement_setting').hide('slow');
					jQuery('#form_description_placement').val('below');
					UpdateDescriptionPlacement();
				}
			}

			function UpdateDescriptionPlacement() {
				var placement = jQuery("#form_description_placement").val();

				//jQuery("#gform_fields").removeClass("description_below").removeClass("description_above").addClass("description_" + placement);

				jQuery(".gfield_description").each(function () {
					var prevElement = placement == 'above' ? '.gfield_label' : '.ginput_container:visible';
					jQuery(this).siblings(prevElement).after(jQuery(this).remove());
				});
			}

			function ToggleButton() {

				var isText = jQuery("#form_button_text").is(":checked");
				var show_element = isText ? '#form_button_text_setting' : '#form_button_image_path_setting';
				var hide_element = isText ? '#form_button_image_path_setting' : '#form_button_text_setting';

				jQuery(hide_element).hide();
				jQuery(show_element).fadeIn();

			}

			function ToggleEnableSave() {

				if (jQuery("#form_save_enabled").is(":checked")) {
					ShowSettingRow('#form_save_button_text_setting');
					ShowSettingRow('#form_save_warning');
				} else {
					HideSettingRow('#form_save_warning');
					HideSettingRow('#form_save_button_text_setting');
				}

			}

			function ToggleLimitEntry() {

				if (jQuery("#gform_limit_entries").is(":checked")) {
					ShowSettingRow('#limit_entries_count_setting');
					ShowSettingRow('#limit_entries_message_setting');
				}
				else {
					HideSettingRow('#limit_entries_count_setting');
					HideSettingRow('#limit_entries_message_setting');
				}
			}

			function ShowSettingRow(elemId) {
				jQuery(elemId).show().find('.gf_animate_sub_settings').slideDown();
			}

			function HideSettingRow(elemId) {
				var elem = jQuery(elemId);
				elem.find('.gf_animate_sub_settings').slideUp(function () {
					elem.hide();
				});
			}

			function ToggleSchedule() {

				if (jQuery("#gform_schedule_form").is(":checked")) {
					ShowSettingRow('#schedule_start_setting');
					ShowSettingRow('#schedule_end_setting');
					ShowSettingRow('#schedule_pending_message_setting');
					ShowSettingRow('#schedule_message_setting');
				}
				else {
					HideSettingRow('#schedule_start_setting');
					HideSettingRow('#schedule_end_setting');
					HideSettingRow('#schedule_pending_message_setting');
					HideSettingRow('#schedule_message_setting');
				}

			}

			function ToggleRequireLogin() {

				if (jQuery("#gform_require_login").is(":checked")) {
					ShowSettingRow('#require_login_message_setting');
				}
				else {
					HideSettingRow('#require_login_message_setting');
				}
			}

			function SetButtonConditionalLogic(isChecked) {
				form.button.conditionalLogic = isChecked ? new ConditionalLogic() : null;
			}

			function HandleUnsavedChanges(elemId) {

				hasUnsavedChanges = false;

				jQuery(elemId).find('input, select, textarea').change(function () {
					hasUnsavedChanges = true;
				});

				window.onbeforeunload = function () {
					if (hasUnsavedChanges){
						return '<?php echo esc_js( 'You have unsaved changes.', 'gravityforms' ); ?>';
					}

				}

			}

			function ShowAdvancedFormSettings() {
				jQuery('#form_setting_advanced').slideDown();
				jQuery('.show_advanced_settings_container').slideUp();
			}

			<?php self::output_field_scripts() ?>

		</script>

		<?php
		switch ( rgar( $update_result, 'status' ) ) {
			case 'invalid_json' :
				?>
				<div class="error below-h2" id="after_update_error_dialog">
					<p>
						<?php _e( 'There was an error while saving your form.', 'gravityforms' ) ?>
						<?php printf( __( 'Please %scontact our support team%s.', 'gravityforms' ), '<a href="http://www.gravityhelp.com">', '</a>' ) ?>
					</p>
				</div>
				<?php
				break;

			case 'duplicate_title':
				?>
				<div class="error below-h2" id="after_update_error_dialog">
					<p>
						<?php _e( 'The form title you have entered has already been used. Please enter a unique form title.', 'gravityforms' ) ?>
					</p>
				</div>
				<?php
				break;

			default:
				if ( ! empty( $update_result ) ) {
					?>
					<div class="updated below-h2" id="after_update_dialog">
						<p>
							<strong><?php _e( 'Form settings updated successfully.', 'gravityforms' ); ?></strong>
						</p>
					</div>
				<?php
				}
				break;
		}


		/**
		 * These variables are used to convenient "wrap" child form settings in the appropriate HTML.
		 */
		$subsetting_open  = '
            <td colspan="2" class="gf_sub_settings_cell">
                <div class="gf_animate_sub_settings">
                    <table>
                        <tr>';
		$subsetting_close = '
                        </tr>
                    </table>
                </div>
            </td>';


		//create form settings table rows and put them into an array
		//form title
		$tr_form_title = '
        <tr>
            <th>
                ' .
			__( 'Form title', 'gravityforms' ) . ' ' .
			gform_tooltip( 'form_title', '', true ) .
			'
		</th>
		<td>
			<input type="text" id="form_title_input" name="form_title_input" class="fieldwidth-3" value="' . esc_attr( $form['title'] ) . '" />
            </td>
        </tr>';

		//form description
		$tr_form_description = '
        <tr>
            <th>
                ' .
			__( 'Form description', 'gravityforms' ) . ' ' .
			gform_tooltip( 'form_description', '', true ) .
			'
		</th>
		<td>
			<textarea id="form_description_input" name="form_description_input" class="fieldwidth-3 fieldheight-2">' . esc_html( rgar( $form, 'description' ) ) . '</textarea>
            </td>
        </tr>';

		//form label placement
		$alignment_options = array(
			'top_label'   => __( 'Top aligned', 'gravityforms' ),
			'left_label'  => __( 'Left aligned', 'gravityforms' ),
			'right_label' => __( 'Right aligned', 'gravityforms' )
		);

		$label_dd = '';
		foreach ( $alignment_options as $value => $label ) {
			$selected = $form['labelPlacement'] == $value ? 'selected="selected"' : '';

			$label_dd .= '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
		}
		$tr_form_label_placement = '
        <tr>
            <th>
                ' .
			__( 'Label placement', 'gravityforms' ) . ' ' .
			gform_tooltip( 'form_label_placement', '', true ) .
			'
		</th>
		<td>
			<select id="form_label_placement" name="form_label_placement" onchange="UpdateLabelPlacement();">' .
			$label_dd .
			'</select>
		</td>
	</tr>';

		//form description placement
		$style               = $form['labelPlacement'] != 'top_label' ? 'display:none;' : '';
		$description_dd      = '';
		$description_options = array(
			'below' => __( 'Below inputs', 'gravityforms' ),
			'above' => __( 'Above inputs', 'gravityforms' )
		);
		foreach ( $description_options as $value => $label ) {
			$selected = rgar( $form, 'descriptionPlacement' ) == $value ? 'selected="selected"' : '';

			$description_dd .= '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
		}
		$tr_form_description_placement = '
        <tr id="description_placement_setting" style="' . $style . '">
            <th>
                ' .
			__( 'Description placement', 'gravityforms' ) . ' ' .
			gform_tooltip( 'form_description_placement', '', true ) .
			'
		</th>
		<td>
			<select id="form_description_placement" name="form_description_placement">' .
			$description_dd .
			'</select>
		</td>
	</tr>';


		//Sub Label placement
		$sub_label_placement_dd      = '';
		$sub_label_placement_options = array(
			'below' => __( 'Below inputs', 'gravityforms' ),
			'above' => __( 'Above inputs', 'gravityforms' )
		);
		foreach ( $sub_label_placement_options as $value => $label ) {
			$selected = rgar( $form, 'subLabelPlacement' ) == $value ? 'selected="selected"' : '';

			$sub_label_placement_dd .= '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
		}
		$tr_sub_label_placement = '
        <tr id="sub_label_placement_setting">
            <th>
                ' .
			__( 'Sub-label placement', 'gravityforms' ) . ' ' .
			gform_tooltip( 'form_sub_label_placement', '', true ) .
			'
			</th>
			<td>
				<select id="form_sub_label_placement" name="form_sub_label_placement">' .
			$sub_label_placement_dd .
			'</select>
		</td>
	</tr>';


		//css class name
		$tr_css_class_name = '
        <tr>
            <th>
                <label for="form_css_class" style="display:block;">' .
			__( 'CSS Class Name', 'gravityforms' ) . ' ' .
			gform_tooltip( 'form_css_class', '', true ) .
			'</label>
		</th>
		<td>
			<input type="text" id="form_css_class" name="form_css_class" class="fieldwidth-3" value="' . esc_attr( rgar( $form, 'cssClass' ) ) . '" />
            </td>
        </tr>';


		//create form advanced settings table rows
		//create form button rows
		$form_button_type     = rgars( $form, 'button/type' );
		$text_button_checked  = '';
		$image_button_checked = '';
		$text_style_display   = '';
		$image_style_display  = '';
		if ( $form_button_type == 'text' ) {
			$text_button_checked = 'checked="checked"';
			$image_style_display = 'display:none;';
		} else if ( $form_button_type == 'image' ) {
			$image_button_checked = 'checked="checked"';
			$text_style_display   = 'display:none;';
		}
		//form button
		$tr_form_button = '
        <tr>
            <th>
                ' . __( 'Input type', 'gravityforms' ) . '
            </th>
            <td>

                <input type="radio" id="form_button_text" name="form_button" value="text" onclick="ToggleButton();" ' . $text_button_checked . ' />
                <label for="form_button_text" class="inline">' .
			__( 'Text', 'gravityforms' ) .
			'</label>

			&nbsp;&nbsp;

			<input type="radio" id="form_button_image" name="form_button" value="image" onclick="ToggleButton();" ' . $image_button_checked . ' />
                <label for="form_button_image" class="inline">' .
			__( 'Image', 'gravityforms' ) . '</label>


            </td>
        </tr>';

		//form button text
		$tr_form_button_text = $subsetting_open . '
        <tr id="form_button_text_setting" class="child_setting_row" style="' . $text_style_display . '">
            <th>
                ' .
			__( 'Button text', 'gravityforms' ) . ' ' .
			gform_tooltip( 'form_button_text', '', true ) .
			'
		</th>
		<td>
			<input type="text" id="form_button_text_input" name="form_button_text_input" class="fieldwidth-3" value="' . esc_attr( rgars( $form, 'button/text' ) ) . '" />
            </td>
        </tr>';

		//form button image path
		$tr_form_button_image_path = '
        <tr id="form_button_image_path_setting" class="child_setting_row" style="' . $image_style_display . '">
            <th>
                ' .
			__( 'Button image path', 'gravityforms' ) . '  ' .
			gform_tooltip( 'form_button_image', '', true ) .
			'
		</th>
		<td>
			<input type="text" id="form_button_image_url" name="form_button_image_url" class="fieldwidth-3" value="' . esc_attr( rgars( $form, 'button/imageUrl' ) ) . '" />
            </td>
        </tr>' . $subsetting_close;

		//form button conditional logic
		$button_conditional_checked = '';
		if ( rgars( $form, 'button/conditionalLogic' ) ) {
			$button_conditional_checked = 'checked="checked"';
		}

		$tr_form_button_conditional = '
        <tr>
            <th>
                ' . __( 'Button conditional logic', 'gravityforms' ) . ' ' . gform_tooltip( 'form_button_conditional_logic', '', true ) . '
            </th>
            <td>
                <input type="checkbox" id="form_button_conditional_logic" onclick="SetButtonConditionalLogic(this.checked); ToggleConditionalLogic(false, \'form_button\');"' . $button_conditional_checked . ' />
                <label for="form_button_conditional_logic" class="inline">' . ' ' . __( 'Enable Conditional Logic', 'gravityforms' ) . '</label>
            </td>
         </tr>
         <tr>
            <td colspan="2">

	            <div id="form_button_conditional_logic_container" class="gf_animate_sub_settings" style="display:none;">
	                    <!-- content dynamically created from js.php -->
	             </div>

            </td>
        </tr>';

		//create save and continue rows

		$save_enabled_checked = '';
		$save_enabled_style = '';

		if ( rgars( $form, 'save/enabled' ) ) {
			$save_enabled_checked = 'checked="checked"';
		} else {
			$save_enabled_style = 'style="display:none;"';
		}

		$save_button_text = isset( $form['save']['button']['text'] ) ? esc_attr( rgars( $form, 'save/button/text' ) ) : __( 'Save and Continue Later', 'gravityforms' );

		$tr_enable_save = '
        <tr>
            <th>
                ' . __( 'Save and Continue', 'gravityforms' ) . ' ' . gform_tooltip( 'form_enable_save', '', true ) . '
            </th>
            <td>
                <input type="checkbox" id="form_save_enabled" name="form_save_enabled" onclick="ToggleEnableSave();" value="1" ' . $save_enabled_checked . ' />
                <label for="form_save_enabled">' . __( 'Enable Save and Continue', 'gravityforms' ) . '</label>
            </td>
        </tr>';

		// Warning
		$tr_save_warning = '
        <tr id="form_save_warning" class="child_setting_row" ' . $save_enabled_style . '>
            ' . $subsetting_open . '
            <th>
            </th>
            <td>
                <div class="gforms_help_alert fieldwidth-3">
                    <div>
                    <i class="fa fa-warning"></i>
                    '. __('This feature stores potentially private and sensitive data on this server and protects it with a unique link which is displayed to the user on the page in plain, unencrypted text. The link is similar to a password so it\'s strongly advisable to ensure that the page enforces a secure connection (HTTPS) before activating this setting.', 'gravityforms').
		            '</div>
		            <br />
		            <div>
		            <i class="fa fa-warning"></i>
                    '. __('When this setting is activated two confirmations and one notification are automatically generated and can be modified in their respective editors. When this setting is deactivated the confirmations and the notification will be deleted automatically and any modifications will be lost.', 'gravityforms').
		            '</div>
                </div>
            </td>

        </tr>';

		//save button text
		$tr_save_button_text = '
        <tr id="form_save_button_text_setting" class="child_setting_row" ' . $save_enabled_style . '>
            <th>
                ' .
			__( 'Link text', 'gravityforms' ) . ' ' .
			gform_tooltip( 'form_save_button_text', '', true ) .
			'
		</th>
		<td>
			<input type="text" id="form_save_button_text" name="form_save_button_text" class="fieldwidth-3" value="' . $save_button_text . '" />
            </td>
            ' . $subsetting_close . '
        </tr>';

		//limit entries
		$limit_entry_checked = '';
		$limit_entry_style   = '';
		$limit_entries_dd    = '';
		if ( rgar( $form, 'limitEntries' ) ) {
			$limit_entry_checked = 'checked="checked"';

		} else {
			$limit_entry_style = 'display:none';
		}

		$limit_periods = array(
			''      => __( 'total entries', 'gravityforms' ),
			'day'   => __( 'per day', 'gravityforms' ),
			'week'  => __( 'per week', 'gravityforms' ),
			'month' => __( 'per month', 'gravityforms' ),
			'year'  => __( 'per year', 'gravityforms' )
		);
		foreach ( $limit_periods as $value => $label ) {
			$selected = rgar( $form, 'limitEntriesPeriod' ) == $value ? 'selected="selected"' : '';
			$limit_entries_dd .= '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
		}

		$tr_limit_entries = '
        <tr>
            <th>
                ' . __( 'Limit number of entries', 'gravityforms' ) . ' ' . gform_tooltip( 'form_limit_entries', '', true ) . '
            </th>
            <td>
                <input type="checkbox" id="gform_limit_entries" name="form_limit_entries" onclick="ToggleLimitEntry();" value="1" ' . $limit_entry_checked . ' />
                <label for="gform_limit_entries">' . __( 'Enable entry limit', 'gravityforms' ) . '</label>
            </td>
        </tr>';

		//limit entries count
		$tr_limit_entries_count = '
        <tr id="limit_entries_count_setting" class="child_setting_row" style="' . esc_attr( $limit_entry_style ) . '">
            ' . $subsetting_open . '
            <th>
                ' .
			__( 'Number of Entries', 'gravityforms' ) .
			'
		</th>
		<td>
			<input type="text" id="gform_limit_entries_count" name="form_limit_entries_count" style="width:70px;" value="' . esc_attr( rgar( $form, 'limitEntriesCount' ) ) . '" />
                &nbsp;
                <select id="gform_limit_entries_period" name="form_limit_entries_period" style="height:22px;">' .
			$limit_entries_dd .
			'</select>
		</td>
		' . $subsetting_close . '
        </tr>';

		//limit entries message
		$tr_limit_entries_message = '
        <tr id="limit_entries_message_setting" class="child_setting_row" style="' . $limit_entry_style . '">
            ' . $subsetting_open . '
            <th>
                <label for="form_limit_entries_message">' .
			__( 'Entry Limit Reached Message', 'gravityforms' ) .
			'</label>
		</th>
		<td>
			<textarea id="form_limit_entries_message" name="form_limit_entries_message" class="fieldwidth-3">' . esc_html( rgar( $form, 'limitEntriesMessage' ) ) . '</textarea>
            </td>
            ' . $subsetting_close . '
		</tr>
        ';

		//schedule form
		$schedule_form_checked = '';
		$schedule_form_style   = '';
		$start_hour_dd         = '';
		$start_minute_dd       = '';
		$start_am_selected     = '';
		$start_pm_selected     = '';
		$end_hour_dd           = '';
		$end_minute_dd         = '';
		$end_am_selected       = '';
		$end_pm_selected       = '';

		if ( rgar( $form, 'scheduleForm' ) ) {
			$schedule_form_checked = 'checked="checked"';
		} else {
			$schedule_form_style = 'display:none';
		}
		//create start hour dd options
		for ( $i = 1; $i <= 12; $i ++ ) {
			$selected = rgar( $form, 'scheduleStartHour' ) == $i ? 'selected="selected"' : '';
			$start_hour_dd .= '<option value="' . $i . '" ' . $selected . '>' . $i . '</option>';
		}
		//create start minute dd options
		foreach ( array( '00', '15', '30', '45' ) as $value ) {
			$selected = rgar( $form, 'scheduleStartMinute' ) == $value ? 'selected="selected"' : '';
			$start_minute_dd .= '<option value="' . $value . '" ' . $selected . '>' . $value . '</option>';
		}
		//set start am/pm
		if ( rgar( $form, 'scheduleStartAmpm' ) == 'am' ) {
			$start_am_selected = 'selected="selected"';
		} elseif ( rgar( $form, 'scheduleStartAmpm' ) == 'pm' ) {
			$start_pm_selected = 'selected="selected"';
		}
		//create end hour dd options
		for ( $i = 1; $i <= 12; $i ++ ) {
			$selected = rgar( $form, 'scheduleEndHour' ) == $i ? 'selected="selected"' : '';
			$end_hour_dd .= '<option value="' . $i . ' "' . $selected . '>' . $i . '</option>';
		}
		//create end minute dd options
		foreach ( array( '00', '15', '30', '45' ) as $value ) {
			$selected = rgar( $form, 'scheduleEndMinute' ) == $value ? 'selected="selected"' : '';
			$end_minute_dd .= '<option value="' . $value . '" ' . $selected . '>' . $value . '</option>';
		}
		//set end am/pm
		if ( rgar( $form, 'scheduleEndAmpm' ) == 'am' ) {
			$end_am_selected = 'selected="selected"';
		} elseif ( rgar( $form, 'scheduleEndAmpm' ) == 'pm' ) {
			$end_pm_selected = 'selected="selected"';
		}

		//schedule form
		$tr_schedule_form = '
        <tr>
            <th>
                ' . __( 'Schedule form', 'gravityforms' ) . ' ' . gform_tooltip( 'form_schedule_form', '', true ) . '
            </th>
            <td>
                <input type="checkbox" id="gform_schedule_form" name="form_schedule_form" value="1" onclick="ToggleSchedule();"' . $schedule_form_checked . '/>
                <label for="gform_schedule_form">' . __( 'Schedule form', 'gravityforms' ) . '</label>
            </td>
        </tr>';

		//schedule start
		$tr_schedule_start = '
        <tr id="schedule_start_setting" class="child_setting_row" style="' . $schedule_form_style . '">
            ' . $subsetting_open . '
            <th>
                <label for="gform_schedule_start">' . __( 'Schedule Start Date/Time', 'gravityforms' ) . '</label>
            </th>
            <td>
                <input type="text" id="gform_schedule_start" name="gform_schedule_start" class="datepicker" value="' . esc_attr( rgar( $form, 'scheduleStart' ) ) . '" />
                &nbsp;&nbsp;
                <select id="gform_schedule_start_hour" name="form_schedule_start_hour">' .
			$start_hour_dd .
			'</select>
			:
			<select id="gform_schedule_start_minute" name="form_schedule_start_minute">' .
			$start_minute_dd .
			'</select>
			<select id="gform_schedule_start_ampm" name="form_schedule_start_ampm">
				<option value="am" ' . $start_am_selected . '>AM</option>
                    <option value="pm" ' . $start_pm_selected . '>PM</option>
                </select>
            </td>
            ' . $subsetting_close . '
        </tr>';

		//schedule end
		$tr_schedule_end = '
        <tr id="schedule_end_setting" class="child_setting_row" style="' . esc_attr( $schedule_form_style ) . '">
            ' . $subsetting_open . '
            <th>
                ' . __( 'Schedule Form End Date/Time', 'gravityforms' ) . '
            </th>
            <td>
                <input type="text" id="gform_schedule_end" name="form_schedule_end" class="datepicker" value="' . esc_attr( rgar( $form, 'scheduleEnd' ) ) . '" />
                &nbsp;&nbsp;
                <select id="gform_schedule_end_hour" name="form_schedule_end_hour">' .
			$end_hour_dd .
			'</select>
			:
			<select id="gform_schedule_end_minute" name="form_schedule_end_minute">' .
			$end_minute_dd .
			'</select>
			<select id="gform_schedule_end_ampm" name="form_schedule_end_ampm">
				<option value="am" ' . $end_am_selected . '>AM</option>
                    <option value="pm" ' . $end_pm_selected . '>PM</option>
                </select>
            </td>
            ' . $subsetting_close . '
        </tr>';

		//schedule message
		$tr_schedule_pending_message = '
        <tr id="schedule_pending_message_setting" class="child_setting_row" style="' . esc_attr( $schedule_form_style ) . '">
            ' . $subsetting_open . '
            <th>
                ' . __( 'Form Pending Message', 'gravityforms' ) . '
            </th>
            <td>
                <textarea id="gform_schedule_pending_message" name="form_schedule_pending_message" class="fieldwidth-3">' . esc_html( rgar( $form, 'schedulePendingMessage' ) ) . '</textarea>
            </td>
            ' . $subsetting_close . '
        </td>';

		//schedule message
		$tr_schedule_message = '
        <tr id="schedule_message_setting" class="child_setting_row" style="' . esc_attr( $schedule_form_style ) . '">
            ' . $subsetting_open . '
            <th>
                ' . __( 'Form Expired Message', 'gravityforms' ) . '
            </th>
            <td>
                <textarea id="gform_schedule_message" name="form_schedule_message" class="fieldwidth-3">' . esc_html( rgar( $form, 'scheduleMessage' ) ) . '</textarea>
            </td>
            ' . $subsetting_close . '
        </td>';

		//honey pot
		$honey_pot_checked = '';
		if ( rgar( $form, 'enableHoneypot' ) ) {
			$honey_pot_checked = 'checked="checked"';
		}
		$tr_honey_pot = '
        <tr>
            <th>
                ' . __( 'Anti-spam honeypot', 'gravityforms' ) . ' ' . gform_tooltip( 'form_honeypot', '', true ) . '
            </th>
            <td>
                <input type="checkbox" id="gform_enable_honeypot" name="form_enable_honeypot" value="1" ' . $honey_pot_checked . '/>
                <label for="gform_enable_honeypot">' . __( 'Enable anti-spam honeypot', 'gravityforms' ) . '</label>
            </td>
        </tr>';

		//enable animation
		$enable_animation_checked = '';
		if ( rgar( $form, 'enableAnimation' ) ) {
			$enable_animation_checked = 'checked="checked"';
		}
		$tr_enable_animation = '
        <tr>
            <th>
                ' . __( 'Animated transitions', 'gravityforms' ) . ' ' . gform_tooltip( 'form_animation', '', true ) . '
            </th>
            <td>
                <input type="checkbox" id="gform_enable_animation" name="form_enable_animation" value="1" ' . $enable_animation_checked . ' />
                <label for="gform_enable_animation">' . __( 'Enable animations', 'gravityforms' ) . '</label>
            </td>
        </tr>';

		//require login
		$require_login_checked = '';
		$require_login_style   = '';
		if ( rgar( $form, 'requireLogin' ) ) {
			$require_login_checked = 'checked="checked"';
		} else {
			$require_login_style = 'display:none';
		}
		$tr_requires_login = '
        <tr>
            <th>
                ' . __( 'Require user to be logged in', 'gravityforms' ) . ' ' . gform_tooltip( 'form_require_login', '', true ) . '
            </th>
            <td>
                <input type="checkbox" id="gform_require_login" name="form_require_login" value="1" onclick="ToggleRequireLogin();"' . $require_login_checked . ' />
                <label for="gform_require_login">' . __( 'Require user to be logged in', 'gravityforms' ) . '</label>
            </td>
        </tr>';

		//require login message
		$tr_requires_login_message = '
        <tr id="require_login_message_setting" class="child_setting_row" style="' . esc_attr( $require_login_style ) . '">
            ' . $subsetting_open . '
            <th>
                ' . __( 'Require Login Message', 'gravityforms' ) . ' ' . gform_tooltip( 'form_require_login_message', '', true ) . '
            </th>
            <td>
                <textarea id="gform_require_login_message" name="form_require_login_message" class="fieldwidth-3">' . esc_html( rgar( $form, 'requireLoginMessage' ) ) . '</textarea>
            </td>
            ' . $subsetting_close . '
        </td>';

		//populate arrays with table rows
		$form_basics       = array( 'form_title' => $tr_form_title, 'form_description' => $tr_form_description );
		$form_layout       = array( 'form_label_placement' => $tr_form_label_placement, 'form_description_placement' => $tr_form_description_placement, 'form_sub_label_placement' => $tr_sub_label_placement, 'css_class_name' => $tr_css_class_name );
		$form_button       = array( 'form_button_type' => $tr_form_button, 'form_button_text' => $tr_form_button_text, 'form_button_image_path' => $tr_form_button_image_path, 'form_button_conditional' => $tr_form_button_conditional );
		$save_button       = array( 'save_enabled' => $tr_enable_save, 'save_warning' => $tr_save_warning, 'save_button_text' => $tr_save_button_text );
		$form_restrictions = array( 'limit_entries' => $tr_limit_entries, 'number_of_entries' => $tr_limit_entries_count, 'entry_limit_message' => $tr_limit_entries_message, 'schedule_form' => $tr_schedule_form, 'schedule_start' => $tr_schedule_start, 'schedule_end' => $tr_schedule_end, 'schedule_pending_message' => $tr_schedule_pending_message, 'schedule_message' => $tr_schedule_message, 'requires_login' => $tr_requires_login, 'requires_login_message' => $tr_requires_login_message );
		$form_options      = array( 'honey_pot' => $tr_honey_pot, 'enable_animation' => $tr_enable_animation );

		$form_settings = array(
			__( 'Form Basics', 'gravityforms' )       => $form_basics,
			__( 'Form Layout', 'gravityforms' )       => $form_layout,
			__( 'Form Button', 'gravityforms' )       => $form_button,
			__( 'Save and Continue', 'gravityforms' ) => $save_button,
			__( 'Restrictions', 'gravityforms' )      => $form_restrictions,
			__( 'Form Options', 'gravityforms' )      => $form_options,
		);

		$form_settings = apply_filters( 'gform_form_settings', $form_settings, $form );
		?>

		<div class="gform_panel gform_panel_form_settings" id="form_settings">

			<h3><span><i class="fa fa-cogs"></i> <?php _e( 'Form Settings', 'gravityforms' ) ?></span></h3>

			<form action="" method="post" id="gform_form_settings">

				<table class="gforms_form_settings" cellspacing="0" cellpadding="0">
					<?php
					//write out array of table rows
					if ( is_array( $form_settings ) ) {
						//foreach($form_settings as $row) {
						foreach ( $form_settings as $key => $value ) {
							?>
							<tr>
								<td colspan="2">
									<h4 class="gf_settings_subgroup_title"><?php _e( $key, 'gravityforms' ); ?></h4>
								</td>
							</tr>
							<?php
							if ( is_array( $value ) ) {
								foreach ( $value as $tr ) {
									echo $tr;
								}
							}
						}
					}
					?>
				</table>


				<div id="gform_custom_settings">
					<!--form settings-->
					<?php do_action( 'gform_properties_settings', 100, $form_id ); ?>
					<?php do_action( 'gform_properties_settings', 200, $form_id ); ?>
					<?php do_action( 'gform_properties_settings', 300, $form_id ); ?>
					<?php do_action( 'gform_properties_settings', 400, $form_id ); ?>
					<?php do_action( 'gform_properties_settings', 500, $form_id ); ?>

					<!--advanced settings-->
					<?php do_action( 'gform_advanced_settings', 100, $form_id ); ?>
					<?php do_action( 'gform_advanced_settings', 200, $form_id ); ?>
					<?php do_action( 'gform_advanced_settings', 300, $form_id ); ?>
					<?php do_action( 'gform_advanced_settings', 400, $form_id ); ?>
					<?php do_action( 'gform_advanced_settings', 500, $form_id ); ?>
					<?php do_action( 'gform_advanced_settings', 600, $form_id ); ?>
					<?php do_action( 'gform_advanced_settings', 700, $form_id ); ?>
					<?php do_action( 'gform_advanced_settings', 800, $form_id ); ?>

				</div>

				<?php wp_nonce_field( "gform_save_form_settings_{$form_id}", 'gform_save_form_settings' ); ?>
				<input type="hidden" id="gform_meta" name="gform_meta" />
				<input type="button" id="gform_save_settings" name="gform_save_settings" value="<?php _e( 'Update Form Settings', 'gravityforms' ); ?>" class="button-primary gfbutton" onclick="SaveFormSettings();" />

			</form>

		</div> <!-- / gform_panel_form_settings -->



		<?php

		self::page_footer();
	}

	public static function confirmations_page() {
		$form_id         = rgget( 'id' );
		$confirmation_id = rgget( 'cid' );
		if ( ! rgblank( $confirmation_id ) ) {
			self::confirmations_edit_page( $form_id, $confirmation_id );
		} else {
			self::confirmations_list_page( $form_id );
		}
	}

	public static function confirmations_list_page( $form_id ) {

		self::maybe_process_confirmation_list_action();

		self::page_header( __( 'Confirmations', 'gravityforms' ) );

		$add_new_url = add_query_arg( array( 'cid' => 0 ) );
		?>

		<h3><span><i class="fa fa-envelope-o"></i> <?php _e( 'Confirmations', 'gravityforms' ) ?>
				<a id="add-new-confirmation" class="add-new-h2" href="<?php echo esc_url( $add_new_url ) ?>"><?php _e( 'Add New', 'gravityforms' ) ?></a></span>
		</h3>

		<?php $form = GFFormsModel::get_form_meta( $form_id ); ?>

		<script type="text/javascript">
			var form = <?php echo json_encode( $form ); ?>;

			function ToggleActive(img, confirmation_id) {
				var is_active = img.src.indexOf("active1.png") >= 0
				if (is_active) {
					img.src = img.src.replace("active1.png", 'active0.png');
					jQuery(img).attr('title', '<?php _e( 'Inactive', 'gravityforms' ) ?>').attr('alt', '<?php _e( 'Inactive', 'gravityforms' ) ?>');
				}
				else {
					img.src = img.src.replace("active0.png", 'active1.png');
					jQuery(img).attr('title', '<?php _e( 'Active', 'gravityforms' ) ?>').attr('alt', '<?php _e( 'Active', 'gravityforms' ) ?>');
				}

				var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' )?>");
				mysack.execute = 1;
				mysack.method = 'POST';
				mysack.setVar("action", "rg_update_confirmation_active");
				mysack.setVar("rg_update_confirmation_active", "<?php echo wp_create_nonce( 'rg_update_confirmation_active' ) ?>");
				mysack.setVar("form_id", <?php echo intval( $form_id ) ?>);
				mysack.setVar("confirmation_id", confirmation_id);
				mysack.setVar("is_active", is_active ? 0 : 1);
				mysack.onError = function () {
					alert('<?php echo esc_js( __( 'Ajax error while updating confirmation', 'gravityforms' ) ) ?>')
				};
				mysack.runAJAX();

				return true;
			}
		</script>

		<?php
		$confirmation_table = new GFConfirmationTable( $form );
		$confirmation_table->prepare_items();
		?>

		<form id="confirmation_list_form" method="post">

			<?php $confirmation_table->display(); ?>

			<input id="action_argument" name="action_argument" type="hidden" />
			<input id="action" name="action" type="hidden" />

			<?php wp_nonce_field( 'gform_confirmation_list_action', 'gform_confirmation_list_action' ) ?>

		</form>

		<?php
		self::page_footer();
	}

	public static function confirmations_edit_page( $form_id, $confirmation_id ) {


		$form = apply_filters( "gform_admin_pre_render_{$form_id}", apply_filters( 'gform_admin_pre_render', GFFormsModel::get_form_meta( $form_id ) ) );

		$duplicated_cid = rgget( 'duplicatedcid' );
		$is_duplicate   = empty( $_POST ) && ! empty( $duplicated_cid );
		if ( $is_duplicate ) {
			$confirmation_id = $duplicated_cid;
		}

		$confirmation = self::handle_confirmation_edit_submission( rgar( $form['confirmations'], $confirmation_id ), $form );


		if ( $is_duplicate ) {
			$count    = 2;
			$name     = $confirmation['name'];
			$new_name = $name . ' - Copy 1';
			while ( ! self::is_unique_name( $new_name, $form['confirmations'] ) ) {
				$new_name = $name . " - Copy $count";
				$count ++;
			}
			$confirmation['name'] = $new_name;
			$confirmation['id']   = 'new';
			if ( $confirmation['isDefault'] ) {
				$confirmation['isDefault']        = false;
				$confirmation['conditionalLogic'] = '';
			}
		}

		$confirmation_ui_settings = self::get_confirmation_ui_settings( $confirmation );

		$entry_meta = GFFormsModel::get_entry_meta( $form_id );
		$entry_meta = apply_filters( 'gform_entry_meta_conditional_logic_confirmations', $entry_meta, $form, $confirmation_id );

		self::page_header( __( 'Confirmations', 'gravityforms' ) );

		?>

		<script type="text/javascript">

			var confirmation = <?php echo $confirmation ? json_encode( $confirmation ) : 'new ConfirmationObj()' ?>;
			var form = <?php echo json_encode( $form ); ?>;
			var entry_meta = <?php echo GFCommon::json_encode( $entry_meta ) ?>;

			jQuery(document).ready(function ($) {

				if ( confirmation.event == 'form_saved' || confirmation.event == 'form_save_email_sent' ) {
					$('#form_confirmation_redirect, #form_confirmation_show_page').attr('disabled', true);
				}

				SetConfirmationConditionalLogic();
				<?php if ( ! rgar( $confirmation, 'isDefault' ) ) : ?>
				ToggleConditionalLogic(true, 'confirmation');
				<?php endif; ?>
				ToggleConfirmation();

				<?php if ( $is_duplicate ) :?>
				$('#confirmation_conditional_logic_container').pointer({
					content     : '<h3><?php _e( 'Important', 'gravityforms' ) ?></h3><p><?php _e( 'Ensure that the conditional logic for this confirmation is different from all the other confirmations for this form and then press save to create the new confirmation.', 'gravityforms' ) ?></p>',
					position    : {
						edge : 'bottom', // arrow direction
						align: 'center' // vertical alignment
					},
					pointerWidth: 300
				}).pointer('open');
				<?php endif; ?>
			});


			gform.addFilter("gform_merge_tags", "MaybeAddSaveMergeTags");
			function MaybeAddSaveMergeTags(mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option){
				var event = confirmation.event;
				if ( event == 'form_saved' || event == 'form_save_email_sent' ) {
					mergeTags["other"].tags.push({ tag: '{save_link}', label: '<?php _e( 'Save &amp; Continue Link', 'gravityforms' ) ?>' });
					mergeTags["other"].tags.push({ tag: '{save_token}', label: '<?php _e( 'Save &amp; Continue Token', 'gravityforms' ) ?>' });
				}

				if( event == 'form_saved' ) {
					mergeTags["other"].tags.push({ tag: '{save_email_input}', label: '<?php _e( 'Save &amp; Continue Email Input', 'gravityforms' ) ?>' });
				}

				return mergeTags;
			}

			<?php self::output_field_scripts() ?>

		</script>

		<style type="text/css">
			#confirmation_action_type {
				display: none;
			}
		</style>

		<div id="confirmation-editor">

			<form id="confirmation_edit_form" method="post">

				<table class="form-table gforms_form_settings">
					<?php array_map( array( __class__, 'output' ), $confirmation_ui_settings ); ?>
				</table>

				<?php
				//DEPRECATED SINCE 1.7 - use gform_confirmation_ui_settings instead
				do_action( 'gform_confirmation_settings', 100, $form_id );
				do_action( 'gform_confirmation_settings', 200, $form_id );
				?>

				<input type="hidden" id="confirmation_id" name="confirmation_id" value="<?php echo $confirmation_id; ?>" />
				<input type="hidden" id="form_id" name="form_id" value="<?php echo $form_id; ?>" />
				<input type="hidden" id="is_default" name="is_default" value="<?php echo rgget( 'isDefault', $confirmation ) ?>" />
				<input type="hidden" id="conditional_logic" name="conditional_logic" value="<?php echo htmlentities( json_encode( rgget( 'conditionalLogic', $confirmation ) ) ); ?>" />

				<p class="submit">
					<input type="submit" name="save" value="<?php _e( 'Save Confirmation', 'gravityforms' ); ?>" onclick="StashConditionalLogic(event);" class="button-primary">
				</p>

				<?php wp_nonce_field( 'gform_confirmation_edit', 'gform_confirmation_edit' ); ?>

			</form>

		</div> <!-- / confirmation-editor -->

		<?php

		self::page_footer();
	}

	public static function get_confirmation_ui_settings( $confirmation ) {

		/**
		 * These variables are used to convenient "wrap" child form settings in the appropriate HTML.
		 */
		$subsetting_open  = '
            <td colspan="2" class="gf_sub_settings_cell">
                <div class="gf_animate_sub_settings">
                    <table style="width:100%">
                        <tr>';
		$subsetting_close = '
                        </tr>
                    </table>
                </div>
            </td>';

		$ui_settings       = array();
		$confirmation_type = rgar( $confirmation, 'type' ) ? rgar( $confirmation, 'type' ) : 'message';
		$is_valid          = ! empty( GFCommon::$errors );
		$is_default        = rgar( $confirmation, 'isDefault' );

		$form_id = rgget( 'id' );
		$form    = RGFormsModel::get_form_meta( $form_id );

		ob_start(); ?>


		<?php $class = ! $is_default && ! $is_valid && $confirmation_type == 'page' && ! rgar( $confirmation, 'name' ) ? 'gfield_error' : ''; ?>
		<tr <?php echo $is_default ? 'style="display:none;"' : ''; ?> class="<?php echo $class; ?>">
			<th><?php _e( 'Confirmation Name', 'gravityforms' ); ?></th>
			<td>
				<input type="text" id="form_confirmation_name" name="form_confirmation_name" value="<?php echo rgar( $confirmation, 'name' ); ?>" />
			</td>
		</tr> <!-- / confirmation name -->
		<?php $ui_settings['confirmation_name'] = ob_get_contents();
		ob_clean(); ?>


		<tr>
			<th><?php _e( 'Confirmation Type', 'gravityforms' ); ?></th>
			<td>
				<input type="radio" id="form_confirmation_show_message" name="form_confirmation" <?php checked( 'message', $confirmation_type ); ?> value="message" onclick="ToggleConfirmation();" />
				<label for="form_confirmation_show_message" class="inline">
					<?php _e( 'Text', 'gravityforms' ); ?>
					<?php gform_tooltip( 'form_confirmation_message' ) ?>
				</label>
				&nbsp;&nbsp;
				<input type="radio" id="form_confirmation_show_page" name="form_confirmation" <?php checked( 'page', $confirmation_type ); ?> value="page" onclick="ToggleConfirmation();" />
				<label for="form_confirmation_show_page" class="inline">
					<?php _e( 'Page', 'gravityforms' ); ?>
					<?php gform_tooltip( 'form_redirect_to_webpage' ) ?>
				</label>
				&nbsp;&nbsp;
				<input type="radio" id="form_confirmation_redirect" name="form_confirmation" <?php checked( 'redirect', $confirmation_type ); ?> value="redirect" onclick="ToggleConfirmation();" />
				<label for="form_confirmation_redirect" class="inline">
					<?php _e( 'Redirect', 'gravityforms' ); ?>
					<?php gform_tooltip( 'form_redirect_to_url' ) ?>
				</label>
			</td>
		</tr> <!-- / confirmation type -->
		<?php $ui_settings['confirmation_type'] = ob_get_contents();
		ob_clean(); ?>


		<tr id="form_confirmation_message_container" <?php echo $confirmation_type != 'message' ? 'style="display:none;"' : ''; ?> >
			<?php echo $subsetting_open; ?>
			<th><?php _e( 'Message', 'gravityforms' ); ?></th>
			<td>
				<span class="mt-form_confirmation_message"></span>
				<?php
				if ( GFCommon::is_wp_version( '3.3' ) ) {
					wp_editor( rgar( $confirmation, 'message' ), 'form_confirmation_message', array( 'autop' => false, 'editor_class' => 'merge-tag-support mt-wp_editor mt-manual_position mt-position-right' ) );
				} else {
					?>
					<textarea name="form_confirmation_message" id="form_confirmation_message" class="fieldwidth-1 fieldheight-1"><?php echo esc_html( $confirmation['message'] ) ?></textarea><?php
				}
				?>
				<div style="margin-top:5px;">
					<input type="checkbox" id="form_disable_autoformatting" name="form_disable_autoformatting" value="1" <?php echo empty( $confirmation['disableAutoformat'] ) ? '' : "checked='checked'" ?> />
					<label for="form_disable_autoformatting"><?php _e( 'Disable Auto-formatting', 'gravityforms' ) ?> <?php gform_tooltip( 'form_confirmation_autoformat' ) ?></label>
				</div>
			</td>
			<?php echo $subsetting_close; ?>
		</tr> <!-- / confirmation message -->
		<?php $ui_settings['confirmation_message'] = ob_get_contents();
		ob_clean(); ?>


		<?php $class = ! $is_valid && $confirmation_type == 'page' && ! rgar( $confirmation, 'pageId' ) ? 'gfield_error' : ''; ?>
		<tr class="form_confirmation_page_container" <?php echo $confirmation_type != 'page' ? 'style="display:none;"' : '' ?> class="<?php echo $class; ?>">
			<?php echo $subsetting_open; ?>
			<th><?php _e( 'Page', 'gravityforms' ); ?></th>
			<td>
				<?php wp_dropdown_pages( array( 'name' => 'form_confirmation_page', 'selected' => rgar( $confirmation, 'pageId' ), 'show_option_none' => __( 'Select a page', 'gravityforms' ) ) ); ?>
			</td>
			<?php echo $subsetting_close; ?>
		</tr> <!-- / confirmation page -->
		<?php $ui_settings['confirmation_page'] = ob_get_contents();
		ob_clean(); ?>

		<tr class="form_confirmation_page_container" <?php echo $confirmation_type != 'page' ? 'style="display:none;"' : '' ?> class="<?php echo $class; ?>">
			<?php echo $subsetting_open; ?>
			<th><?php _e( 'Redirect Query String', 'gravityforms' ); ?> <?php gform_tooltip( 'form_redirect_querystring' ) ?></th>
			<td>
				<input type="checkbox" id="form_page_use_querystring" name="form_page_use_querystring" <?php echo empty( $confirmation['queryString'] ) ? '' : "checked='checked'" ?> onclick="TogglePageQueryString()" />
				<label for="form_page_use_querystring"><?php _e( 'Pass Field Data Via Query String', 'gravityforms' ) ?></label>

				<div id="form_page_querystring_container" <?php echo empty( $confirmation['queryString'] ) ? 'style="display:none;"' : ''; ?> >
					<?php
					$query_string = rgget( 'queryString', $confirmation );
					?>
					<textarea name="form_page_querystring" id="form_page_querystring" class="merge-tag-support mt-position-right mt-hide_all_fields mt-option-url" style="width:98%; height:100px;"><?php echo esc_html( $query_string ); ?></textarea><br />

					<div class="instruction"><?php _e( 'Sample: phone={Phone:1}&email={Email:2}', 'gravityforms' ); ?></div>
				</div>
			</td>
			<?php echo $subsetting_close; ?>
		</tr> <!-- / confirmation page use querystring -->
		<?php $ui_settings['confirmation_page_querystring'] = ob_get_contents();
		ob_clean(); ?>

		<?php $class = ! $is_valid && $confirmation_type == 'redirect' && ! rgar( $confirmation, 'url' ) ? 'gfield_error' : ''; ?>
		<tr class="form_confirmation_redirect_container <?php echo $class; ?>" <?php echo $confirmation_type != 'redirect' ? 'style="display:none;"' : '' ?> >
			<?php echo $subsetting_open; ?>
			<th><?php _e( 'Redirect URL', 'gravityforms' ); ?></th>
			<td>
				<input type="text" id="form_confirmation_url" name="form_confirmation_url" value="<?php echo esc_attr( rgget( 'url', $confirmation ) ); ?>" style="width:98%;" />
			</td>
			<?php echo $subsetting_close; ?>
		</tr> <!-- / confirmation url -->
		<?php $ui_settings['confirmation_url'] = ob_get_contents();
		ob_clean(); ?>


		<tr class="form_confirmation_redirect_container" <?php echo $confirmation_type != 'redirect' ? 'style="display:none;"' : '' ?> >
			<?php echo $subsetting_open; ?>
			<th><?php _e( 'Redirect Query String', 'gravityforms' ); ?> <?php gform_tooltip( 'form_redirect_querystring' ) ?></th>
			<td>
				<input type="checkbox" id="form_redirect_use_querystring" name="form_redirect_use_querystring" <?php echo empty( $confirmation['queryString'] ) ? '' : "checked='checked'" ?> onclick="ToggleQueryString()" />
				<label for="form_redirect_use_querystring"><?php _e( 'Pass Field Data Via Query String', 'gravityforms' ) ?></label>

				<div id="form_redirect_querystring_container" <?php echo empty( $confirmation['queryString'] ) ? 'style="display:none;"' : ''; ?> >

					<?php
					$query_string = rgget( 'queryString', $confirmation );
					?>

					<textarea name="form_redirect_querystring" id="form_redirect_querystring" class="merge-tag-support mt-position-right mt-hide_all_fields mt-option-url" style="width:98%; height:100px;"><?php echo esc_html( $query_string ); ?></textarea><br />

					<div class="instruction"><?php _e( 'Sample: phone={Phone:1}&email={Email:2}', 'gravityforms' ); ?></div>
				</div>
			</td>
			<?php echo $subsetting_close; ?>
		</tr> <!-- / confirmation use querystring -->
		<?php $ui_settings['confirmation_querystring'] = ob_get_contents();
		ob_clean(); ?>


		<tr <?php echo rgget( 'isDefault', $confirmation ) ? 'style="display:none;"' : ''; ?> >
			<th><?php _e( 'Conditional Logic', 'gravityforms' ); ?></th>
			<td>
				<input type="checkbox" id="confirmation_conditional_logic" name="confirmation_conditional_logic" style="display:none;" checked="checked" />

				<div id="confirmation_conditional_logic_container">
					<!-- content populated dynamically by form_admin.js -->
				</div>
			</td>
		</tr> <!-- conditional logic -->
		<?php $ui_settings['confirmation_conditional_logic'] = ob_get_contents();
		ob_clean(); ?>


		<?php
		ob_end_clean();
		$ui_settings = apply_filters( "gform_confirmation_ui_settings_{$form_id}", apply_filters( 'gform_confirmation_ui_settings', $ui_settings, $confirmation, $form ), $confirmation, $form );

		return $ui_settings;
	}

	public static function notification_page() {
		require_once( 'notification.php' );

		//page header loaded in below function because admin messages were not yet available to the header to display
		GFNotification::notification_page();

	}

	public static function page_header( $title = '' ) {

		// register admin styles
		wp_register_style( 'gform_admin', GFCommon::get_base_url() . '/css/admin.css' );
		wp_print_styles( array( 'jquery-ui-styles', 'gform_admin', 'wp-pointer' ) );

		$form         = GFFormsModel::get_form_meta( rgget( 'id' ) );
		$current_tab  = rgempty( 'subview', $_GET ) ? 'settings' : rgget( 'subview' );
		$setting_tabs = GFFormSettings::get_tabs( $form['id'] );

		// kind of boring having to pass the title, optionally get it from the settings tab
		if ( ! $title ) {
			foreach ( $setting_tabs as $tab ) {
				if ( $tab['name'] == $current_tab ) {
					$title = $tab['label'];
				}
			}
		}

		?>

		<div class="wrap gforms_edit_form <?php echo GFCommon::get_browser_class() ?>">
			<h2 class="gf_admin_page_title">
				<span><?php echo $title ?></span><span class="gf_admin_page_subtitle"><span class="gf_admin_page_formid">ID: <?php echo $form['id']; ?></span><span class="gf_admin_page_formname"><?php _e( 'Form Name', 'gravityforms' ) ?>: <?php echo $form['title']; ?></span></span>
			</h2>

			<?php GFCommon::display_admin_message(); ?>

			<?php RGForms::top_toolbar(); ?>

			<div id="gform_tab_group" class="gform_tab_group vertical_tabs">
				<ul id="gform_tabs" class="gform_tabs">
				<?php
				foreach ( $setting_tabs as $tab ) {
					$query = array( 'subview' => $tab['name'] );
					if ( isset( $tab['query'] ) )
						$query = array_merge( $query, $tab['query'] );

					$url = add_query_arg( $query );
					?>
					<li <?php echo $current_tab == $tab['name'] ? "class='active'" : '' ?>>
						<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $tab['label'] ) ?></a><span></span>
					</li>
				<?php
					}
					?>
				</ul>

				<div id="gform_tab_container_1" class="gform_tab_container">
					<div class="gform_tab_content" id="tab_<?php echo $current_tab ?>">

	<?php
	}

	public static function page_footer(){
						?>
					</div>
					<!-- / gform_tab_content -->
				</div>
				<!-- / gform_tab_container -->
			</div>
			<!-- / gform_tab_group -->

			<br class="clear" style="clear: both;" />

		</div> <!-- / wrap -->

		<script type="text/javascript">
			jQuery(document).ready(function ($) {
				$('.gform_tab_container').css('minHeight', jQuery('#gform_tabs').height() + 100);
			});
		</script>

	<?php
	}

	public static function get_tabs( $form_id ) {

		$setting_tabs = array(
			'10' => array( 'name' => 'settings', 'label' => __( 'Form Settings', 'gravityforms' ) ),
			'20' => array( 'name' => 'confirmation', 'label' => __( 'Confirmations', 'gravityforms' ), 'query' => array( 'cid' => null, 'duplicatedcid' => null ) ),
			'30' => array( 'name' => 'notification', 'label' => __( 'Notifications', 'gravityforms' ), 'query' => array( 'nid' => null ) )
		);

		$setting_tabs = apply_filters( 'gform_form_settings_menu', $setting_tabs, $form_id );
		ksort( $setting_tabs, SORT_NUMERIC );

		return $setting_tabs;
	}


	/* Ajax Functions */

	public static function handle_confirmation_edit_submission( $confirmation, $form ) {

		if ( empty( $_POST ) || ! check_admin_referer( 'gform_confirmation_edit', 'gform_confirmation_edit' ) )
			return $confirmation;

		$is_new_confirmation = ! $confirmation;

		if ( $is_new_confirmation )
			$confirmation['id'] = uniqid();

		$confirmation['name']              = rgpost( 'form_confirmation_name' );
		$confirmation['type']              = rgpost( 'form_confirmation' );
		$confirmation['message']           = rgpost( 'form_confirmation_message' );
		$confirmation['disableAutoformat'] = rgpost( 'form_disable_autoformatting' );
		$confirmation['pageId']            = rgpost( 'form_confirmation_page' );
		$confirmation['url']               = rgpost( 'form_confirmation_url' );
		$confirmation['queryString']       = '' != rgpost( 'form_redirect_querystring' ) ? rgpost( 'form_redirect_querystring' ) : rgpost( 'form_page_querystring' );
		$confirmation['isDefault']         = rgpost( 'is_default' );

		// if is default confirmation, override any submitted conditional logic with empty array
		$confirmation['conditionalLogic'] = $confirmation['isDefault'] ? array() : json_decode( rgpost( 'conditional_logic' ), ARRAY_A );

		$failed_validation = false;

		if ( ! $confirmation['name'] ) {
			$failed_validation = true;
			GFCommon::add_error_message( __( 'You must specify a Confirmation Name.', 'gravityforms' ) );
		}

		switch ( $confirmation['type'] ) {
			case 'page':
				if ( empty( $confirmation['pageId'] ) ) {
					$failed_validation = true;
					GFCommon::add_error_message( __( 'You must select a Confirmation Page.', 'gravityforms' ) );
				}
				break;
			case 'redirect':
				if ( ( empty( $confirmation['url'] ) || ! GFCommon::is_valid_url( $confirmation['url'] ) ) && ! GFCommon::has_merge_tag( $confirmation['url'] ) ) {
					$failed_validation = true;
					GFCommon::add_error_message( __( 'You must specify a valid Redirect URL.', 'gravityforms' ) );
				}
				break;
		}

		if ( $failed_validation )
			return $confirmation;

		// allow user to filter confirmation before save
		$confirmation = apply_filters( "gform_pre_confirmation_save_{$form['id']}", apply_filters( 'gform_pre_confirmation_save', $confirmation, $form, $is_new_confirmation ), $form, $is_new_confirmation );

		// trim values
		$confirmation = GFFormsModel::trim_conditional_logic_values_from_element( $confirmation, $form );

		// add current confirmation to confirmations array
		$form['confirmations'][ $confirmation['id'] ] = $confirmation;

		// save updated confirmations array
		$result = GFFormsModel::save_form_confirmations( $form['id'], $form['confirmations'] );

		if ( $result !== false ) {
			$url = remove_query_arg( array( 'cid', 'duplicatedcid' ) );
			GFCommon::add_message( sprintf( __( 'Confirmation saved successfully. %sBack to confirmations.%s', 'gravityforms' ), '<a href="' . esc_url( $url ) . '">', '</a>' ) );
		} else {
			GFCommon::add_error_message( __( 'There was an issue saving this confirmation.', 'gravityforms' ) );
		}

		return $confirmation;
	}

	public static function maybe_process_confirmation_list_action() {

		if ( empty( $_POST ) || ! check_admin_referer( 'gform_confirmation_list_action', 'gform_confirmation_list_action' ) )
			return;

		$action    = rgpost( 'action' );
		$object_id = rgpost( 'action_argument' );

		switch ( $action ) {
			case 'delete':
				$confirmation_deleted = self::delete_confirmation( $object_id, rgget( 'id' ) );
				if ( $confirmation_deleted ) {
					GFCommon::add_message( __( 'Confirmation deleted.', 'gravityforms' ) );
				} else {
					GFCommon::add_error_message( __( 'There was an issue deleting this confirmation.', 'gravityforms' ) );
				}
				break;
		}

	}

	/**
	 * Delete a form confirmation by ID.
	 *
	 * @param mixed $confirmation_id
	 * @param mixed $form_id Can pass a form ID or a form object
	 *
	 * @return mixed The result of the database operation
	 */
	public static function delete_confirmation( $confirmation_id, $form_id ) {

		if ( ! $form_id )
			return false;

		$form = ! is_array( $form_id ) ? RGFormsModel::get_form_meta( $form_id ) : $form_id;

		do_action( 'gform_pre_confirmation_deleted', $form['confirmations'][ $confirmation_id ], $form );

		unset( $form['confirmations'][ $confirmation_id ] );

		// clear form cache so next retrieval of form meta will reflect deleted notification
		RGFormsModel::flush_current_forms();

		return RGFormsModel::save_form_confirmations( $form['id'], $form['confirmations'] );
	}

	public static function output( $a ) {
		echo $a;
	}

	public static function is_unique_name( $name, $confirmations ) {

		foreach ( $confirmations as $confirmation ) {
			if ( strtolower( rgar( $confirmation, 'name' ) ) == strtolower( $name ) )
				return false;
		}

		return true;
	}

	public static function output_field_scripts( $echo = true ){
		$script_str = '';
		$conditional_logic_fields = array();

		foreach ( GF_Fields::get_all() as $gf_field ) {
			if ( $gf_field->is_conditional_logic_supported() ) {
				$conditional_logic_fields[] = $gf_field->type;
			}

		}

		$script_str .= sprintf( 'function GetConditionalLogicFields(){return %s;}', json_encode( $conditional_logic_fields ) ) . PHP_EOL;

		if ( ! empty( $script_str ) && $echo ){
			echo $script_str;
		}

		return $script_str;
	}

	public static function activate_save( $form ) {

		$form_id = $form['id'];

		$has_save_notification = false;
		foreach ( $form['notifications'] as $notification ) {
			if ( rgar( $notification, 'event' ) == 'form_save_email_requested' ) {
				$has_save_notification = true;
				break;
			}
		}
		if ( ! $has_save_notification ) {
			$notification_id = uniqid();
			$form['notifications'][ $notification_id ] = array(
				'id'      => $notification_id,
				'isDefault' => true,
				'name'    => __( 'Save and Continue Email', 'gravityforms' ),
				'event'   => 'form_save_email_requested',
				'toType'  => 'hidden',
				'from' => '{admin_email}',
				'subject' => __( 'Link to continue {form_title}' ),
				'message' => __( 'Thank you for saving {form_title}. Please use the unique link below to return to the form from any computer. <br /><br /> {save_link} <br /><br /> Remember that the link will expire after 30 days so please return via the provided link to complete your form submission.', 'gravityforms' ),
			);
			GFFormsModel::save_form_notifications( $form_id, $form['notifications'] );
		}


		$has_save_confirmation = false;
		foreach ( $form['confirmations'] as $confirmation ) {
			if ( rgar( $confirmation, 'event' ) == 'form_saved' ) {
				$has_save_confirmation = true;
				break;
			}
		}

		if ( ! $has_save_confirmation ) {
			$confirmation_id = uniqid( 'sc1' );
			$form['confirmations'][ $confirmation_id ] = array(
				'id'          => $confirmation_id,
				'event'       => 'form_saved',
				'name'        => __( 'Save and Continue Confirmation', 'gravityforms' ),
				'isDefault'   => true,
				'type'        => 'message',
				'message'     => __( 'Please use the following link to return to your form from any computer. <br /> {save_link} <br /> This link will expire after 30 days. <br />Enter your email address to send the link by email. <br /> {save_email_input}', 'gravityforms' ),
				'url'         => '',
				'pageId'      => '',
				'queryString' => '',
			);
			$confirmation_id = uniqid( 'sc2' );
			$form['confirmations'][ $confirmation_id ] = array(
				'id'          => $confirmation_id,
				'event'       => 'form_save_email_sent',
				'name'        => __( 'Save and Continue Email Sent Confirmation', 'gravityforms' ),
				'isDefault'   => true,
				'type'        => 'message',
				'message'     => __( 'The link was sent to the following email address: {save_email}', 'gravityforms' ),
				'url'         => '',
				'pageId'      => '',
				'queryString' => '',
			);
			GFFormsModel::save_form_confirmations( $form_id, $form['confirmations'] );
		}
		return $form;
	}

	public static function deactivate_save( $form ) {

		$form_id = $form['id'];

		foreach ( $form['notifications'] as $notification_id => $notification ) {
			if ( rgar( $notification, 'isDefault' ) && rgar( $notification, 'event' ) == 'form_save_email_requested' ) {
				unset( $form['notifications'][ $notification_id ] );
				GFFormsModel::save_form_notifications( $form_id, $form['notifications'] );
				break;
			}
		}

		$changed = false;
		foreach ( $form['confirmations'] as $confirmation_id => $confirmation ) {
			$event = rgar( $confirmation, 'event' );
			if ( rgar( $confirmation, 'isDefault' ) && ( $event == 'form_saved' || $event == 'form_save_email_sent' ) ) {
				unset( $form['confirmations'][ $confirmation_id ] );
				$changed = true;
			}
		}
		if ( $changed ) {
			GFFormsModel::save_form_confirmations( $form_id, $form['confirmations'] );
		}

		return $form;
	}


}


require_once( ABSPATH . '/wp-admin/includes/class-wp-list-table.php' );

class GFConfirmationTable extends WP_List_Table {

	public $form;

	function __construct( $form ) {

		$this->form = $form;

		$this->_column_headers = array(
			array(
				'cb'      => '',
				'name'    => __( 'Name', 'gravityforms' ),
				'type'    => __( 'Type', 'gravityforms' ),
				'content' => __( 'Content', 'gravityforms' )
			),
			array(),
			array(),
		);

		parent::__construct();
	}

	function prepare_items() {
		$this->items = $this->form['confirmations'];
	}

	function display() {
		$singular = rgar( $this->_args, 'singular' );
		?>
		<table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>" cellspacing="0">
			<thead>
			<tr>
				<?php $this->print_column_headers(); ?>
			</tr>
			</thead>

			<tfoot>
			<tr>
				<?php $this->print_column_headers( false ); ?>
			</tr>
			</tfoot>

			<tbody id="the-list"<?php if ( $singular )
				echo " class='list:$singular'"; ?>>

			<?php $this->display_rows_or_placeholder(); ?>

			</tbody>
		</table>

	<?php
	}

	function single_row( $item ) {
		static $row_class = '';
		$row_class = ( $row_class == '' ? ' class="alternate"' : '' );

		echo '<tr id="confirmation-' . $item['id'] . '" ' . $row_class . '>';
		echo $this->single_row_columns( $item );
		echo '</tr>';
	}

	function column_content( $item ) {
		return self::get_column_content( $item );
	}

	function column_default( $item, $column ) {
		echo rgar( $item, $column );
	}

	function column_type( $item ) {
		return self::get_column_type( $item );
	}

	function column_cb( $item ) {
		if ( isset( $item['isDefault'] ) && $item['isDefault'] )
			return;

		$is_active = isset( $item['isActive'] ) ? $item['isActive'] : true;
		?>
		<img src="<?php echo GFCommon::get_base_url() ?>/images/active<?php echo intval( $is_active ) ?>.png" style="cursor: pointer;margin:-5px 0 0 8px;" alt="<?php $is_active ? __( 'Active', 'gravityforms' ) : __( 'Inactive', 'gravityforms' ); ?>" title="<?php echo $is_active ? __( 'Active', 'gravityforms' ) : __( 'Inactive', 'gravityforms' ); ?>" onclick="ToggleActive(this, '<?php echo $item['id'] ?>'); " />
	<?php
	}

	function column_name( $item ) {
		$edit_url      = add_query_arg( array( 'cid' => $item['id'] ) );
		$duplicate_url = add_query_arg( array( 'cid' => 0, 'duplicatedcid' => $item['id'] ) );
		$actions       = apply_filters(
			'gform_confirmation_actions', array(
				'edit'      => '<a title="' . __( 'Edit this item', 'gravityforms' ) . '" href="' . esc_url( $edit_url ) . '">' . __( 'Edit', 'gravityforms' ) . '</a>',
				'duplicate' => '<a title="' . __( 'Duplicate this confirmation', 'gravityforms' ) . '" href="' . esc_url( $duplicate_url ) . '">' . __( 'Duplicate', 'gravityforms' ) . '</a>',
				'delete'    => '<a title="' . __( 'Delete this item', 'gravityforms' ) . '" class="submitdelete" onclick="javascript: if(confirm(\'' . __( 'WARNING: You are about to delete this confirmation.', 'gravityforms' ) . __( "\'Cancel\' to stop, \'OK\' to delete.", 'gravityforms' ) . '\')){ DeleteConfirmation(\'' . esc_js( $item['id'] ) . '\'); }" style="cursor:pointer;">' . __( 'Delete', 'gravityforms' ) . '</a>'
			)
		);

		if ( isset( $item['isDefault'] ) && $item['isDefault'] ){
			unset( $actions['delete'] );
		}


		?>

		<a href="<?php echo esc_url( $edit_url ); ?>"><strong><?php echo esc_html( rgar( $item, 'name' ) ); ?></strong></a>
		<div class="row-actions">

			<?php
			if ( is_array( $actions ) && ! empty( $actions ) ) {
				$keys     = array_keys( $actions );
				$last_key = array_pop( $keys );
				foreach ( $actions as $key => $html ) {
					$divider = $key == $last_key ? '' : ' | ';
					?>
					<span class="<?php echo $key; ?>">
                        <?php echo $html . $divider; ?>
                    </span>
				<?php
				}
			}
			?>

		</div>

	<?php
	}

	public static function get_column_content( $item ) {
		switch ( rgar( $item, 'type' ) ) {

			case 'message':
				return '<a class="limit-text" title="' . strip_tags( $item['message'] ) . '">' . strip_tags( $item['message'] ) . '</a>';

			case 'page':

				$page = get_post( $item['pageId'] );
				if ( empty( $page ) )
					return __( '<em>This page does not exist.</em>', 'gravityforms' );

				return '<a href="' . get_permalink( $item['pageId'] ) . '">' . $page->post_title . '</a>';

			case 'redirect':
				$url_pieces    = parse_url( $item['url'] );
				$url_connector = rgar( $url_pieces, 'query' ) ? '&' : '?';
				$url           = rgar( $item, 'queryString' ) ? "{$item['url']}{$url_connector}{$item['queryString']}" : $item['url'];

				return '<a class="limit-text" title="' . $url . '">' . $url . '</a>';
		}

		return '';
	}

	public static function get_column_type( $item ) {
		switch ( $item['type'] ) {
			case 'message':
				$type = __( 'Text', 'gravityforms' );
				break;
			case 'page':
				$type = __( 'Page', 'gravityforms' );
				break;
			case 'redirect':
				$type = __( 'Redirect', 'gravityforms' );
				break;
		}
		return $type;
	}

}