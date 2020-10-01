<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Class GFNotification
 * Handles notifications within Gravity Forms
 */
Class GFNotification {

	/**
	 * Defines the fields that support notifications.
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @var array Array of field types.
	 */
	private static $supported_fields = array(
		'checkbox', 'radio', 'select', 'text', 'website', 'textarea', 'email', 'hidden', 'number', 'phone', 'multiselect', 'post_title',
		'post_tags', 'post_custom_field', 'post_content', 'post_excerpt',
	);

	/**
	 * Gets a notification based on a Form Object and a notification ID.
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @param array $form            The Form Object.
	 * @param int   $notification_id The notification ID.
	 *
	 * @return array The Notification Object.
	 */
	private static function get_notification( $form, $notification_id ) {
		foreach ( $form['notifications'] as $id => $notification ) {
			if ( $id == $notification_id ) {
				return $notification;
			}
		}

		return array();
	}

	/**
	 * Displays the Notification page.
	 *
	 * If the notification ID is passed, the Notification Edit page is displayed.
	 * Otherwise, the Notification List page is displayed.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFNotification::notification_edit_page()
	 * @uses GFNotification::notification_list_page()
	 *
	 * @return void
	 */
	public static function notification_page() {
		$form_id         = rgget( 'id' );
		$notification_id = rgget( 'nid' );
		if ( ! rgblank( $notification_id ) ) {
			self::notification_edit_page( $form_id, $notification_id );
		} else {
			self::notification_list_page( $form_id );
		}
	}

	/**
	 * Builds the Notification Edit page.
	 *
	 * @access public
	 *
	 * @used-by GFNotification::notification_page()
	 * @uses    GFFormsModel::get_form_meta()
	 * @uses    GFNotification::get_notification()
	 * @uses    GFNotification::validate_notification
	 * @uses    GFFormsModel::sanitize_conditional_logic()
	 * @uses    GFFormsModel::trim_conditional_logic_values_from_element()
	 * @uses    GFFormsModel::save_form_notifications()
	 * @uses    GFCommon::add_message()
	 * @uses    GFCommon::json_decode()
	 * @uses    GFCommon::add_error_message()
	 * @uses    GFFormSettings::page_header()
	 * @uses    GFNotification::get_notification_ui_settings()
	 * @uses    SCRIPT_DEBUG
	 * @uses    GFFormsModel::get_entry_meta()
	 * @uses    GFFormSettings::output_field_scripts()
	 * @uses    GFFormSettings::page_footer()
	 *
	 * @param int $form_id         The ID of the form that the notification belongs to
	 * @param int $notification_id The ID of the notification being edited
	 *
	 * @return void
	 */
	public static function notification_edit_page( $form_id, $notification_id ) {

		if ( ! rgempty( 'gform_notification_id' ) ) {
			$notification_id = rgpost( 'gform_notification_id' );
		}

		$form = RGFormsModel::get_form_meta( $form_id );

		/**
		 * Filters the form to be used in the notification page
		 *
		 * @since 1.8.6
		 *
		 * @param array $form            The Form Object
		 * @param int   $notification_id The notification ID
		 */
		$form = gf_apply_filters( array( 'gform_form_notification_page', $form_id ), $form, $notification_id );

		$notification = ! $notification_id ? array() : self::get_notification( $form, $notification_id );

		// Added second condition to account for new notifications with errors as notification ID will
		// Be available in $_POST but the notification has not actually been saved yet
		$is_new_notification = empty( $notification_id ) || empty( $notification );

		$is_valid  = true;
		$is_update = false;
		if ( ! empty( $_POST ) ) {

			check_admin_referer( 'gforms_save_notification', 'gforms_save_notification' );

			// Clear out notification because it could have legacy data populated
			$notification = array( 'isActive' => isset( $notification['isActive'] ) ? rgar( $notification, 'isActive' ) : true );

			$notification['name']              = sanitize_text_field( rgpost( 'gform_notification_name' ) );
			$notification['service']           = sanitize_text_field( rgpost( 'gform_notification_service' ) );
			$notification['event']             = sanitize_text_field( rgpost( 'gform_notification_event' ) );
			$notification['to']                = rgpost( 'gform_notification_to_type' ) == 'field' ? rgpost( 'gform_notification_to_field' ) : rgpost( 'gform_notification_to_email' );
			$to_type = rgpost( 'gform_notification_to_type' );
			if ( ! in_array( $to_type, array( 'email', 'field', 'routing', 'hidden' ) ) ) {
				$to_type = 'email';
			}
			$notification['toType']            = $to_type;

			$notification['cc']                = rgpost( 'gform_notification_cc' );
			$notification['bcc']               = rgpost( 'gform_notification_bcc' );
			$notification['subject']           = sanitize_text_field( rgpost( 'gform_notification_subject' ) );

			$notification['message']           = rgpost( 'gform_notification_message' );

			$notification['from']              = sanitize_text_field( rgpost( 'gform_notification_from' ) );
			$notification['fromName']          = sanitize_text_field( rgpost( 'gform_notification_from_name' ) );

			$notification['replyTo']           = rgpost( 'gform_notification_reply_to' );
			$routing          = ! rgempty( 'gform_routing_meta' ) ? GFCommon::json_decode( rgpost( 'gform_routing_meta' ), true ) : null;
			if ( ! empty ( $routing ) ) {
				$routing_logic = array( 'rules' => $routing );
				$routing_logic = GFFormsModel::sanitize_conditional_logic( $routing_logic );
				$notification['routing'] = $routing_logic['rules'];
			}

			$notification['routing'] = $routing;

			$conditional_logic  = ! rgempty( 'gform_conditional_logic_meta' ) ? GFCommon::json_decode( rgpost( 'gform_conditional_logic_meta' ), true ) : null;

			$notification['conditionalLogic'] = GFFormsModel::sanitize_conditional_logic( $conditional_logic );

			$notification['disableAutoformat'] = (bool) rgpost( 'gform_notification_disable_autoformat' );

			$notification['enableAttachments'] = (bool) rgpost( 'gform_notification_attachments' );

			if ( rgpost( 'save' ) ) {

				$is_update = true;

				if ( $is_new_notification ) {
					$notification_id    = uniqid();
					$notification['id'] = $notification_id;
				} else {
					$notification['id'] = $notification_id;
				}

				if ( rgpost( 'gform_is_default' ) ) {
					$notification['isDefault'] = true;
				}

				/**
				 * Filters the notification before it is saved
				 *
				 * @param array $notification The Notification Object.
				 * @param array $form The Form Object.
				 * @param bool  $is_new_notification True if it is a new notification.  False otherwise.
				 *
				 * @since 1.7
				 */
				$notification = gf_apply_filters( array( 'gform_pre_notification_save', $form_id ), $notification, $form, $is_new_notification );

				// Validating input...
				$is_valid = self::validate_notification();
				/**
				 * Allows overriding of if the notification passes validation
				 *
				 * @param bool  $is_valid     True if it is valid.  False otherwise.
				 * @param array $notification The Notification Object
				 * @param array $form         The Form Object
				 *
				 * @since 1.9.16
				 *
				 */
				$is_valid = gf_apply_filters( array( 'gform_notification_validation', $form_id ), $is_valid, $notification, $form );

				if ( $is_valid ) {
					// Input valid, updating...
					// Emptying notification email if it is supposed to be disabled
					if ( $_POST['gform_notification_to_type'] == 'routing' ) {
						$notification['to'] = '';
					} else {
						$notification['routing'] = null;
					}

					// Trim values
					$notification = GFFormsModel::trim_conditional_logic_values_from_element( $notification, $form );

					$form['notifications'][ $notification_id ] = $notification;

					RGFormsModel::save_form_notifications( $form_id, $form['notifications'] );
				}

			}

		}

		if ( $is_update && $is_valid ) {

			$url = remove_query_arg( 'nid' );

			GFCommon::add_message( sprintf( esc_html__( 'Notification saved successfully. %sBack to notifications.%s', 'gravityforms' ), '<a href="' . esc_url( $url ) . '">', '</a>' ) );
			/**
			 * Fires an action after a notification has been saved
			 *
			 * @since 1.9.16
			 *
			 * @param array $notification        The Notification Object
			 * @param array $form                The Form Object
			 * @param bool  $is_new_notification True if this is a new notification.  False otherwise.
			 */
			gf_do_action( array( 'gform_post_notification_save', $form_id ), $notification, $form, $is_new_notification );
		} else if ( $is_update && ! $is_valid ) {
			GFCommon::add_error_message( esc_html__( 'Notification could not be updated. Please enter all required information below.', 'gravityforms' ) );
		}

		// Moved page header loading here so the admin messages can be set upon saving and available for the header to print out.
		GFFormSettings::page_header( esc_html__( 'Notifications', 'gravityforms' ) );

		$notification_ui_settings = self::get_notification_ui_settings( $notification, $is_valid );

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		?>
		<link rel="stylesheet" href="<?php echo GFCommon::get_base_url() ?>/css/admin<?php echo $min; ?>.css?ver=<?php echo GFCommon::$version ?>" />

		<script type="text/javascript">

		var gform_has_unsaved_changes = false;
		jQuery(document).ready(function () {

			jQuery("#entry_form input, #entry_form textarea, #entry_form select").change(function () {
				gform_has_unsaved_changes = true;
			});

			window.onbeforeunload = function () {
				if (gform_has_unsaved_changes) {
					return "You have unsaved changes.";
				}
			};

			ToggleConditionalLogic(true, 'notification');

			jQuery(document).on('input propertychange', '.gfield_routing_email', function () {
				SetRoutingEmail(jQuery(this));
			});

			jQuery(document).on('change', '.gfield_routing_value_dropdown', function () {
				SetRoutingValueDropDown(jQuery(this));
			});

		});

		gform.addFilter("gform_merge_tags", "MaybeAddSaveLinkMergeTag");
		function MaybeAddSaveLinkMergeTag(mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option){
			var event = document.getElementById('gform_notification_event').value;
			if ( event == 'form_saved' || event == 'form_save_email_requested' ) {
				mergeTags["other"].tags.push({ tag: '{save_link}', label: <?php echo json_encode( esc_html__( 'Save & Continue Link', 'gravityforms' ) ); ?> });
				mergeTags["other"].tags.push({ tag: '{save_token}', label: <?php echo json_encode( esc_html__( 'Save & Continue Token', 'gravityforms' ) ); ?> });
			}

			return mergeTags;
		}

		<?php
		if ( empty( $form['notifications'] ) ) {
			$form['notifications'] = array();
		}

		$entry_meta = GFFormsModel::get_entry_meta( $form_id );
		/**
		 * Filters the entry meta when notification conditional logic is being edited
		 *
		 * @since 1.7.6
		 *
		 * @param array $entry_meta      The Entry meta
		 * @param array $form            The Form Object
		 * @param int   $notification_id The notification ID
		 */
		$entry_meta = apply_filters( 'gform_entry_meta_conditional_logic_notifications', $entry_meta, $form, $notification_id );

		?>

		var form = <?php echo json_encode( $form ) ?>;
		var current_notification = <?php echo GFCommon::json_encode( $notification ) ?>;
		var entry_meta = <?php echo GFCommon::json_encode( $entry_meta ) ?>;

		function SetRoutingEmail(element) {
			// Parsing ID to get routing Index
			var index = element.attr('id').replace('routing_email_', '');
			SetRouting(index);
		}

		function SetRoutingValueDropDown(element) {
			// Parsing ID to get routing Index
			var index = element.attr("id").replace("routing_value_", '');
			SetRouting(index);
		}

		function CreateRouting(routings) {
			var str = '';
			for (var i = 0; i < routings.length; i++) {

				var isSelected = routings[i].operator == 'is' ? "selected='selected'" : '';
				var isNotSelected = routings[i].operator == 'isnot' ? "selected='selected'" : '';
				var greaterThanSelected = routings[i].operator == '>' ? "selected='selected'" : '';
				var lessThanSelected = routings[i].operator == '<' ? "selected='selected'" : '';
				var containsSelected = routings[i].operator == 'contains' ? "selected='selected'" : '';
				var startsWithSelected = routings[i].operator == 'starts_with' ? "selected='selected'" : '';
				var endsWithSelected = routings[i].operator == 'ends_with' ? "selected='selected'" : '';
				var email = routings[i]["email"] ? routings[i]["email"] : '';

				str += "<div style='width:99%'>" + <?php echo json_encode( esc_html__( 'Send to', 'gravityforms' ) ); ?> + " <input type='text' id='routing_email_" + i + "' value='" + email + "' class='gfield_routing_email' />";
				str += " " + <?php echo json_encode( esc_html__( 'if', 'gravityforms' ) ); ?> + " " + GetRoutingFields(i, routings[i].fieldId) + "&nbsp;";
				str += "<select id='routing_operator_" + i + "' onchange='SetRouting(" + i + ");' class='gform_routing_operator'>";
				str += "<option value='is' " + isSelected + ">" + <?php echo json_encode( esc_html__( 'is', 'gravityforms' ) ); ?> + "</option>";
				str += "<option value='isnot' " + isNotSelected + ">" + <?php echo json_encode( esc_html__( 'is not', 'gravityforms' ) ); ?> + "</option>";
				str += "<option value='>' " + greaterThanSelected + ">" + <?php echo json_encode( esc_html__( 'greater than', 'gravityforms' ) ); ?> + "</option>";
				str += "<option value='<' " + lessThanSelected + ">" + <?php echo json_encode( esc_html__( 'less than', 'gravityforms' ) ); ?> + "</option>";
				str += "<option value='contains' " + containsSelected + ">" + <?php echo json_encode( esc_html__( 'contains', 'gravityforms' ) ); ?> + "</option>";
				str += "<option value='starts_with' " + startsWithSelected + ">" + <?php echo json_encode( esc_html__( 'starts with', 'gravityforms' ) ); ?> + "</option>";
				str += "<option value='ends_with' " + endsWithSelected + ">" + <?php echo json_encode( esc_html__( 'ends with', 'gravityforms' ) ); ?> + "</option>";
				str += "</select>&nbsp;";
				str += GetRoutingValues(i, routings[i].fieldId, routings[i].value) + "&nbsp;";
				str += "<a class='gf_insert_field_choice' title='add another rule' onclick=\"InsertRouting(" + (i + 1) + ");\" onkeypress=\"InsertRouting(" + (i + 1) + ");\"><i class='gficon-add'></i></a>";
				if (routings.length > 1)
					str += "<a class='gf_delete_field_choice' title='remove this rule' onclick=\"DeleteRouting(" + i + ");\" onkeypress=\"DeleteRouting(" + i + ");\"><i class='gficon-subtract'></i></a>";

				str += "</div>";
			}

			jQuery("#gform_notification_to_routing_rules").html(str);
		}

		function GetRoutingValues(index, fieldId, selectedValue) {
			var str = GetFieldValues(index, fieldId, selectedValue, 16);

			return str;
		}

		function GetRoutingFields(index, selectedItem) {
			var str = "<select id='routing_field_id_" + index + "' class='gfield_routing_select' onchange='jQuery(\"#routing_value_" + index + "\").replaceWith(GetRoutingValues(" + index + ", jQuery(this).val())); SetRouting(" + index + "); '>";
			str += GetSelectableFields(selectedItem, 16);
			str += "</select>";

			return str;
		}

		//---------------------- generic ---------------
		function GetSelectableFields(selectedFieldId, labelMaxCharacters) {
			var str = "";
			var inputType;
			for (var i = 0; i < form.fields.length; i++) {
				inputType = form.fields[i].inputType ? form.fields[i].inputType : form.fields[i].type;
				// See if this field type can be used for conditionals.
				if (IsNotificationConditionalLogicField(form.fields[i])) {
					var selected = form.fields[i].id == selectedFieldId ? "selected='selected'" : "";
					str += "<option value='" + form.fields[i].id + "' " + selected + ">" + GetLabel(form.fields[i]) + "</option>";
				}
			}
			return str;
		}

		function IsNotificationConditionalLogicField(field) {
			// This function is a duplicate of IsConditionalLogicField from form_editor.js
			inputType = field.inputType ? field.inputType : field.type;
			var supported_fields = <?php echo json_encode( self::get_routing_field_types() ); ?>;

			var index = jQuery.inArray(inputType, supported_fields);

			return index >= 0;
		}

		function GetFirstSelectableField() {
			var inputType;
			for (var i = 0; i < form.fields.length; i++) {
				inputType = form.fields[i].inputType ? form.fields[i].inputType : form.fields[i].type;
				if (IsNotificationConditionalLogicField(form.fields[i])) {
					return form.fields[i].id;
				}
			}

			return 0;
		}

		function TruncateMiddle(text, maxCharacters) {
			if (!text)
				return "";

			if (text.length <= maxCharacters)
				return text;
			var middle = parseInt(maxCharacters / 2);
			return text.substr(0, middle) + "..." + text.substr(text.length - middle, middle);

		}

		function GetFieldValues(index, fieldId, selectedValue, labelMaxCharacters) {
			if (!fieldId)
				fieldId = GetFirstSelectableField();

			if (!fieldId)
				return "";

			var str = '';
			var field = GetFieldById(fieldId);
			var isAnySelected = false;

			if (!field)
				return "";

			if (field["type"] == 'post_category' && field["displayAllCategories"]) {
				var dropdown_id = 'routing_value_' + index;
				var dropdown = jQuery('#' + dropdown_id + ".gfield_category_dropdown");

				// Don't load category drop down if it already exists (to avoid unecessary ajax requests).
				if (dropdown.length > 0) {

					var options = dropdown.html();
					options = options.replace("value=\"" + selectedValue + "\"", "value=\"" + selectedValue + "\" selected=\"selected\"");
					str = "<select id='" + dropdown_id + "' class='gfield_routing_select gfield_category_dropdown gfield_routing_value_dropdown'>" + options + "</select>";
				}
				else {
					// Loading categories via AJAX.
					jQuery.post(ajaxurl, {   action: "gf_get_notification_post_categories",
							ruleIndex              : index,
							selectedValue          : selectedValue},
						function (dropdown_string) {
							if (dropdown_string) {
								jQuery('#gfield_ajax_placeholder_' + index).replaceWith(dropdown_string.trim());
							}
						}
					);

					// Will be replaced by real drop down during the ajax callback.
					str = "<select id='gfield_ajax_placeholder_" + index + "' class='gfield_routing_select'><option>" + <?php json_encode( esc_html__( 'Loading...', 'gravityforms' ) ); ?> + "</option></select>";
				}
			}
			else if (field.choices) {
				// Create a drop down for fields that have choices (i.e. drop down, radio, checkboxes, etc...).
				str = "<select class='gfield_routing_select gfield_routing_value_dropdown' id='routing_value_" + index + "'>";

				if (field.placeholder) {
					str += "<option value=''>" + field.placeholder + "</option>";
				}

				for (var i = 0; i < field.choices.length; i++) {
					var choiceValue = field.choices[i].value ? field.choices[i].value : field.choices[i].text;
					var isSelected = choiceValue == selectedValue;
					var selected = isSelected ? "selected='selected'" : '';
					if (isSelected)
						isAnySelected = true;

					str += "<option value='" + choiceValue.replace(/'/g, "&#039;") + "' " + selected + ">" + field.choices[i].text + "</option>";
				}

				if (!isAnySelected && selectedValue) {
					str += "<option value='" + selectedValue.replace(/'/g, "&#039;") + "' selected='selected'>" + selectedValue + "</option>";
				}
				str += "</select>";
			}
			else {
				selectedValue = selectedValue ? selectedValue.replace(/'/g, "&#039;") : "";
				// Create a text field for fields that don't have choices (i.e text, textarea, number, email, etc...).
				str = "<input type='text' placeholder='" + <?php echo json_encode( esc_html__( 'Enter value', 'gravityforms' ) ); ?> +"' class='gfield_routing_select' id='routing_value_" + index + "' value='" + selectedValue.replace(/'/g, "&#039;") + "' onchange='SetRouting(" + index + ");' onkeyup='SetRouting(" + index + ");'>";
			}
			return str;
		}

		//---------------------------------------------------------------------------------

		function InsertRouting(index) {
			var routings = current_notification.routing;
			routings.splice(index, 0, new ConditionalRule());

			CreateRouting(routings);
			SetRouting(index);
		}

		function SetRouting(ruleIndex) {
			if (!current_notification.routing && ruleIndex == 0)
				current_notification.routing = [new ConditionalRule()];

			current_notification.routing[ruleIndex]["email"] = jQuery("#routing_email_" + ruleIndex).val();
			current_notification.routing[ruleIndex]["fieldId"] = jQuery("#routing_field_id_" + ruleIndex).val();
			current_notification.routing[ruleIndex]["operator"] = jQuery("#routing_operator_" + ruleIndex).val();
			current_notification.routing[ruleIndex]["value"] = jQuery("#routing_value_" + ruleIndex).val();

			var json = jQuery.toJSON(current_notification.routing);
			jQuery('#gform_routing_meta').val(json);
		}

		function DeleteRouting(ruleIndex) {
			current_notification.routing.splice(ruleIndex, 1);
			CreateRouting(current_notification.routing);
		}

		function SetConditionalLogic(isChecked) {
			current_notification.conditionalLogic = isChecked ? new ConditionalLogic() : null;
		}

		function SaveJSMeta() {
			jQuery('#gform_routing_meta').val(jQuery.toJSON(current_notification.routing));
			jQuery('#gform_conditional_logic_meta').val(jQuery.toJSON(current_notification.conditionalLogic));
		}

		<?php GFFormSettings::output_field_scripts() ?>

		</script>

		<form method="post" id="gform_notification_form" onsubmit="gform_has_unsaved_changes = false; SaveJSMeta();">

			<?php wp_nonce_field( 'gforms_save_notification', 'gforms_save_notification' ) ?>
			<?php
			if ( rgar( $notification, 'isDefault' ) ) {
				echo '<input type="hidden" id="gform_is_default" name="gform_is_default" value="1"/>';
			}

			?>
			<input type="hidden" id="gform_routing_meta" name="gform_routing_meta" />
			<input type="hidden" id="gform_conditional_logic_meta" name="gform_conditional_logic_meta" />
			<input type="hidden" id="gform_notification_id" name="gform_notification_id" value="<?php echo esc_attr( $notification_id ) ?>" />

			<table class="form-table gform_nofification_edit">
				<?php array_map( array( 'GFFormSettings', 'output' ), $notification_ui_settings ); ?>
			</table>

			<p class="submit">
				<?php
				$button_label = $is_new_notification ? __( 'Save Notification', 'gravityforms' ) : __( 'Update Notification', 'gravityforms' );
				$notification_button = '<input class="button-primary" type="submit" value="' . esc_attr( $button_label ) . '" name="save"/>';
				/**
				 * Filters the "Save Notification" button
				 *
				 * @since Unknown
				 *
				 * @param string $notification_button The notification button HTML
				 */
				echo apply_filters( 'gform_save_notification_button', $notification_button );
				?>
			</p>
		</form>

		<?php

		GFFormSettings::page_footer();

	}

	/**
	 * Displays the notification list page
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFNotification::notification_page()
	 * @uses    GFNotification::maybe_process_notification_list_action()
	 * @uses    GFFormsModel::get_form_meta()
	 * @uses    GFFormSettings::page_header()
	 * @uses    GFNotificationTable::__construct()
	 * @uses    GFNotificationTable::prepare_items()
	 * @uses    GFNotificationTable::display()
	 * @uses    GFFormSettings::page_footer()
	 *
	 * @param int $form_id The form ID to list notifications on.
	 *
	 * @return void
	 */
	public static function notification_list_page( $form_id ) {

		// Handle form actions
		self::maybe_process_notification_list_action();

		$form = RGFormsModel::get_form_meta( $form_id );

		GFFormSettings::page_header( esc_html__( 'Notifications', 'gravityforms' ) );
		$add_new_url = add_query_arg( array( 'nid' => 0 ) );
		?>

		<h3><span><i class="fa fa-envelope-o"></i> <?php esc_html_e( 'Notifications', 'gravityforms' ) ?>
				<a id="add-new-confirmation" class="add-new-h2" href="<?php echo esc_url( $add_new_url ) ?>"><?php esc_html_e( 'Add New', 'gravityforms' ) ?></a></span>
		</h3>

		<script type="text/javascript">
			function ToggleActive(img, notification_id) {
				var is_active = img.src.indexOf("active1.png") >= 0
				if (is_active) {
					img.src = img.src.replace("active1.png", "active0.png");
					jQuery(img).attr('title', <?php echo json_encode( esc_html__( 'Inactive', 'gravityforms' ) ); ?>).attr('alt', <?php echo json_encode( esc_html__( 'Inactive', 'gravityforms' ) );  ?>);
				}
				else {
					img.src = img.src.replace("active0.png", "active1.png");
					jQuery(img).attr('title', <?php echo json_encode( esc_html__( 'Active', 'gravityforms' ) ); ?>).attr('alt', <?php echo json_encode( esc_html__( 'Active', 'gravityforms' ) ); ?>);
				}

				var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' )?>");
				mysack.execute = 1;
				mysack.method = 'POST';
				mysack.setVar("action", "rg_update_notification_active");
				mysack.setVar("rg_update_notification_active", "<?php echo wp_create_nonce( 'rg_update_notification_active' ) ?>");
				mysack.setVar("form_id", <?php echo intval( $form_id ) ?>);
				mysack.setVar("notification_id", notification_id);
				mysack.setVar("is_active", is_active ? 0 : 1);
				mysack.onError = function () {
					alert(<?php echo json_encode( esc_html__( 'Ajax error while updating notification', 'gravityforms' ) ) ?>)
				};
				mysack.runAJAX();

				return true;
			}
		</script>
		<?php
		$notification_table = new GFNotificationTable( $form );
		$notification_table->prepare_items();
		?>

		<form id="notification_list_form" method="post">

			<?php $notification_table->display(); ?>

			<input id="action_argument" name="action_argument" type="hidden" />
			<input id="action" name="action" type="hidden" />

			<?php wp_nonce_field( 'gform_notification_list_action', 'gform_notification_list_action' ) ?>

		</form>

		<?php
		GFFormSettings::page_footer();
	}

	/**
	 * Processes a notification list action if needed.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFNotification::notification_list_page()
	 * @uses    GFNotification::delete_notification()
	 * @uses    GFNotification::duplicate_notification()
	 * @uses    GFCommon::add_message()
	 * @uses    GFCommon::add_error_message()
	 *
	 * @return void
	 */
	public static function maybe_process_notification_list_action() {

		if ( empty( $_POST ) || ! check_admin_referer( 'gform_notification_list_action', 'gform_notification_list_action' ) ) {
			return;
		}

		$action    = rgpost( 'action' );
		$object_id = rgpost( 'action_argument' );

		switch ( $action ) {
			case 'delete':
				$notification_deleted = GFNotification::delete_notification( $object_id, rgget( 'id' ) );
				if ( $notification_deleted ) {
					GFCommon::add_message( esc_html__( 'Notification deleted.', 'gravityforms' ) );
				} else {
					GFCommon::add_error_message( esc_html__( 'There was an issue deleting this notification.', 'gravityforms' ) );
				}
				break;
			case 'duplicate':
				$notification_duplicated = GFNotification::duplicate_notification( $object_id, rgget( 'id' ) );
				if ( $notification_duplicated ) {
					GFCommon::add_message( esc_html__( 'Notification duplicated.', 'gravityforms' ) );
				} else {
					GFCommon::add_error_message( esc_html__( 'There was an issue duplicating this notification.', 'gravityforms' ) );
				}
				break;
		}

	}

	/**
	 * Builds the Notification Settings page.
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @used-by GFNotification::notification_edit_page()
	 * @uses    GFFormsModel::get_form_meta()
	 * @uses    GFCommon::$errors
	 * @uses    gform_tooltip()
	 * @uses    GFNotification::get_notification_services()
	 * @uses    GFNotification::get_notification_events()
	 * @uses    GFNotification::is_valid_notification_to()
	 * @uses    GFCommon::get_email_fields()
	 * @uses    GFCommon::get_base_url()
	 *
	 * @param array $notification The Notification Object
	 * @param bool  $is_valid     Optional. Defines if this is a valid notification. Defaults to true.
	 *
	 * @return array $ui_settings Array of notification settings.
	 */
	private static function get_notification_ui_settings( $notification, $is_valid = true ) {

		// These variables are used to convenient "wrap" child form settings in the appropriate HTML.
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

		$ui_settings = array();
		$form_id     = rgget( 'id' );
		$form        = RGFormsModel::get_form_meta( $form_id );
		$form        = gf_apply_filters( array( 'gform_admin_pre_render', $form_id ), $form );
		$is_valid    = empty( GFCommon::$errors );

		ob_start(); ?>

		<tr valign="top" <?php echo rgar( $notification, 'isDefault' ) ? "style='display:none'" : '' ?> >
			<th scope="row">
				<label for="gform_notification_name">
					<?php esc_html_e( 'Name', 'gravityforms' ); ?>
					<?php gform_tooltip( 'notification_name' ) ?>
				</label>
			</th>
			<td>
				<input type="text" class="fieldwidth-2" name="gform_notification_name" id="gform_notification_name" value="<?php echo esc_attr( rgget( 'name', $notification ) ) ?>" />
			</td>
		</tr> <!-- / name -->
		<?php $ui_settings['notification_name'] = ob_get_contents();
		ob_clean(); ?>
		<?php
		
		$services = self::get_notification_services();
		$service_style = count( $services ) == 1 ? "style='display:none'" : '';
		
		$notification_service = ! rgempty( 'gform_notification_service' ) ? rgpost( 'gform_notification_service' ) : rgar( $notification, 'service' );
		if ( empty( $notification_service ) ) {
			$notification_service = key( $services );
		}
		
		ob_start(); ?>
		
		<tr valign="top" <?php echo $service_style; ?>>
			<th scope="row">
				<label for="gform_notification_send_via">
					<?php esc_html_e( 'Email Service', 'gravityforms' ); ?>
					<?php gform_tooltip( 'notification_service' ) ?>
				</label>
			</th>
			<td>
				<?php foreach ( $services as $service_name => $service ) { ?>
				<div id="gform-notification-service-<?php echo esc_attr( $service_name ); ?>" class="gform-notification-service<?php echo rgar( $service, 'disabled' ) && rgar( $service, 'disabled_message' ) ? ' gf_tooltip' : ''; ?>" <?php echo rgar( $service, 'disabled' ) && rgar( $service, 'disabled_message' ) ? 'title="' . esc_attr( $service['disabled_message'] ) . '"' : ''; ?>>
					<input type="radio" id="gform_notification_service_<?php echo esc_attr( $service_name ); ?>" name="gform_notification_service" <?php checked( $service_name, $notification_service ); ?> value="<?php echo esc_attr( $service_name ); ?>" onclick="jQuery(this).parents('form').submit();" onkeypress="jQuery(this).parents('form').submit();" <?php echo rgar( $service, 'disabled' ) ? 'disabled="disabled"' : ''; ?> />
					<label for="gform_notification_service_<?php echo esc_attr( $service_name ); ?>" class="inline">
						<span><img src="<?php echo esc_attr( rgar( $service, 'image' ) ); ?>" /><br /><?php echo esc_html( rgar( $service, 'label' ) ); ?></span>
					</label>
				</div>
				<?php } ?>
			</td>
		</tr> <!-- / send via -->
		<?php $ui_settings['notification_service'] = ob_get_contents();
		ob_clean(); ?>

		<?php
		$notification_events = self::get_notification_events( $form );
		$event_style         = count( $notification_events ) == 1 || rgar( $notification, 'isDefault' ) ? "style='display:none'" : '';
		?>
		<tr valign="top" <?php echo $event_style ?>>
			<th scope="row">
				<label for="gform_notification_event">
					<?php esc_html_e( 'Event', 'gravityforms' ); ?>
					<?php gform_tooltip( 'notification_event' ) ?>
				</label>

			</th>
			<td>
				<select name="gform_notification_event" id="gform_notification_event">
					<?php
					foreach ( $notification_events as $code => $label ) {
						?>
						<option value="<?php echo esc_attr( $code ) ?>" <?php selected( rgar( $notification, 'event' ), $code ) ?>><?php echo esc_html( $label ) ?></option>
					<?php
					}
					?>
				</select>
			</td>
		</tr> <!-- / event -->
		<?php $ui_settings['notification_event'] = ob_get_contents();
		ob_clean(); ?>

		<?php
		$notification_to_type = ! rgempty( 'gform_notification_to_type' ) ? rgpost( 'gform_notification_to_type' ) : rgar( $notification, 'toType' );
		if ( empty( $notification_to_type ) ) {
			$notification_to_type = 'email';
		}

		$is_invalid_email_to = ! $is_valid && ! self::is_valid_notification_to();
		$send_to_class       = $is_invalid_email_to ? 'gfield_error' : '';
		?>
		<tr valign="top" class='<?php echo esc_attr( $send_to_class ) ?>' <?php echo $notification_to_type == 'hidden' ? 'style="display:none;"': ''; ?>>
			<th scope="row">
				<label for="gform_notification_to_email">
					<?php esc_html_e( 'Send To', 'gravityforms' ); ?><span class="gfield_required">*</span>
					<?php gform_tooltip( 'notification_send_to_email' ) ?>
				</label>

			</th>
			<td>
				<input type="radio" id="gform_notification_to_type_email" name="gform_notification_to_type" <?php checked( 'email', $notification_to_type ); ?> value="email" onclick="jQuery('.notification_to_container').hide(); jQuery('#gform_notification_to_email_container').show('slow');" onkeypress="jQuery('.notification_to_container').hide(); jQuery('#gform_notification_to_email_container').show('slow');" />
				<label for="gform_notification_to_type_email" class="inline">
					<?php esc_html_e( 'Enter Email', 'gravityforms' ); ?>
				</label>
				&nbsp;&nbsp;
				<input type="radio" id="gform_notification_to_type_field" name="gform_notification_to_type" <?php checked( 'field', $notification_to_type ); ?> value="field" onclick="jQuery('.notification_to_container').hide(); jQuery('#gform_notification_to_field_container').show('slow');" onkeypress="jQuery('.notification_to_container').hide(); jQuery('#gform_notification_to_field_container').show('slow');" />
				<label for="gform_notification_to_type_field" class="inline">
					<?php esc_html_e( 'Select a Field', 'gravityforms' ); ?>
				</label>
				&nbsp;&nbsp;
				<input type="radio" id="gform_notification_to_type_routing" name="gform_notification_to_type" <?php checked( 'routing', $notification_to_type ); ?> value="routing" onclick="jQuery('.notification_to_container').hide(); jQuery('#gform_notification_to_routing_container').show('slow');" onkeypress="jQuery('.notification_to_container').hide(); jQuery('#gform_notification_to_routing_container').show('slow');" />
				<label for="gform_notification_to_type_routing" class="inline">
					<?php esc_html_e( 'Configure Routing', 'gravityforms' ); ?>
					<?php gform_tooltip( 'notification_send_to_routing' ) ?>
				</label>
			</td>
		</tr> <!-- / to email type -->
		<?php
		$ui_settings['notification_to_email_type'] = ob_get_contents();
		ob_clean();
		if ( $notification_to_type == 'hidden' ) {
			$ui_settings['notification_to_email_type'] = '<input type="hidden" name="gform_notification_to_type" value="hidden" />';
		}
		?>

		<tr id="gform_notification_to_email_container" class="notification_to_container <?php echo esc_attr( $send_to_class ) ?>" <?php echo $notification_to_type != 'email' ? "style='display:none';" : '' ?>>
			<?php echo $subsetting_open; ?>
			<th scope="row"><?php esc_html_e( 'Send to Email', 'gravityforms' ) ?></th>
			<td>
				<?php
				$to_email = rgget( 'toType', $notification ) == 'email' ? rgget( 'to', $notification ) : '';
				?>
				<input type="text" name="gform_notification_to_email" id="gform_notification_to_email" value="<?php echo esc_attr( $to_email ) ?>" class="fieldwidth-1" />

				<?php if ( rgpost( 'gform_notification_to_type' ) == 'email' && $is_invalid_email_to ) { ?>
					<span class="validation_message"><?php esc_html_e( 'Please enter a valid email address', 'gravityforms' ) ?>.</span>
				<?php } ?>
			</td>
			<?php echo $subsetting_close; ?>
		</tr> <!-- / to email -->
		<?php $ui_settings['notification_to_email'] = ob_get_contents();
		ob_clean(); ?>

		<?php $email_fields = gf_apply_filters( array( 'gform_email_fields_notification_admin', $form['id'] ), GFCommon::get_email_fields( $form ), $form ); ?>
		<tr id="gform_notification_to_field_container" class="notification_to_container <?php echo esc_attr( $send_to_class ) ?>" <?php echo $notification_to_type != 'field' ? "style='display:none';" : '' ?>>
			<?php echo $subsetting_open; ?>
			<th scope="row"><?php esc_html_e( 'Send to Field', 'gravityforms' ) ?></th>
			<td>
				<?php
				if ( ! empty( $email_fields ) ) {
					?>
					<select name="gform_notification_to_field" id="gform_notification_to_field">
						<option value=""><?php esc_html_e( 'Select an email field', 'gravityforms' ); ?></option>
						<?php
						$to_field = rgget( 'toType', $notification ) == 'field' ? rgget( 'to', $notification ) : '';
						foreach ( $email_fields as $field ) {
							?>
							<option value="<?php echo esc_attr( $field->id ) ?>" <?php echo selected( $field->id, $to_field ) ?>><?php echo GFCommon::get_label( $field ) ?></option>
						<?php
						}
						?>
					</select>
				<?php
				} else {
					?>
					<div class="error_base">
						<p><?php esc_html_e( 'Your form does not have an email field. Add an email field to your form and try again.', 'gravityforms' ) ?></p>
					</div>
				<?php
				}
				?>
			</td>
			<?php echo $subsetting_close; ?>
		</tr> <!-- / to email field -->
		<?php $ui_settings['notification_to_email_field'] = ob_get_contents();
		ob_clean(); ?>

		<tr id="gform_notification_to_routing_container" class="notification_to_container <?php echo esc_attr( $send_to_class ) ?>" <?php echo $notification_to_type != 'routing' ? "style='display:none';" : '' ?>>
			<?php echo $subsetting_open; ?>
			<td colspan="2">
				<div id="gform_notification_to_routing_rules">
					<?php
					$routing_fields = self::get_routing_fields( $form, '0' );
					if ( empty( $routing_fields ) ) {
						?>
						<div class="gold_notice">
							<p><?php esc_html_e( 'To use notification routing, your form must have a field supported by conditional logic.', 'gravityforms' ); ?></p>
						</div>
					<?php
					} else {
						if ( empty( $notification['routing'] ) ) {
							$notification['routing'] = array( array() );
						}

						$count        = sizeof( $notification['routing'] );
						$routing_list = ',';
						for ( $i = 0; $i < $count; $i ++ ) {
							$routing_list .= $i . ',';
							$routing = $notification['routing'][ $i ];

							$is_invalid_rule = ! $is_valid && $_POST['gform_notification_to_type'] == 'routing' && ! self::is_valid_notification_email( rgar( $routing, 'email' ) );
							$class           = $is_invalid_rule ? "class='grouting_rule_error'" : '';
							?>
							<div style='width:99%' <?php echo $class ?>>
								<?php esc_html_e( 'Send to', 'gravityforms' ) ?>
								<input type="text" id="routing_email_<?php echo $i ?>" value="<?php echo esc_attr( rgar( $routing, 'email' ) ); ?>" class='gfield_routing_email' />
								<?php esc_html_e( 'if', 'gravityforms' ) ?>
								<select id="routing_field_id_<?php echo $i ?>" class='gfield_routing_select' onchange='jQuery("#routing_value_<?php echo $i ?>").replaceWith(GetRoutingValues(<?php echo $i ?>, jQuery(this).val())); SetRouting(<?php echo $i ?>); '><?php echo self::get_routing_fields( $form, rgar( $routing, 'fieldId' ) ) ?></select>
								<select id="routing_operator_<?php echo $i ?>" onchange="SetRouting(<?php echo $i ?>)" class="gform_routing_operator">
									<option value="is" <?php echo rgar( $routing, 'operator' ) == 'is' ? "selected='selected'" : '' ?>><?php esc_html_e( 'is', 'gravityforms' ) ?></option>
									<option value="isnot" <?php echo rgar( $routing, 'operator' ) == 'isnot' ? "selected='selected'" : '' ?>><?php esc_html_e( 'is not', 'gravityforms' ) ?></option>
									<option value=">" <?php echo rgar( $routing, 'operator' ) == '>' ? "selected='selected'" : '' ?>><?php esc_html_e( 'greater than', 'gravityforms' ) ?></option>
									<option value="<" <?php echo rgar( $routing, 'operator' ) == '<' ? "selected='selected'" : '' ?>><?php esc_html_e( 'less than', 'gravityforms' ) ?></option>
									<option value="contains" <?php echo rgar( $routing, 'operator' ) == 'contains' ? "selected='selected'" : '' ?>><?php esc_html_e( 'contains', 'gravityforms' ) ?></option>
									<option value="starts_with" <?php echo rgar( $routing, 'operator' ) == 'starts_with' ? "selected='selected'" : '' ?>><?php esc_html_e( 'starts with', 'gravityforms' ) ?></option>
									<option value="ends_with" <?php echo rgar( $routing, 'operator' ) == 'ends_with' ? "selected='selected'" : '' ?>><?php esc_html_e( 'ends with', 'gravityforms' ) ?></option>
								</select>
								<?php echo self::get_field_values( $i, $form, rgar( $routing, 'fieldId' ), rgar( $routing, 'value' ) ) ?>

								<a class='gf_insert_field_choice' title='add another rule' onclick='SetRouting(<?php echo $i ?>); InsertRouting(<?php echo $i + 1 ?>);' onkeypress='SetRouting(<?php echo $i ?>); InsertRouting(<?php echo $i + 1 ?>);'><i class='gficon-add'></i></a>

								<?php if ( $count > 1 ) { ?>
									<img src='<?php echo GFCommon::get_base_url() ?>/images/remove.png' id='routing_delete_<?php echo $i ?>' title='remove this email routing' alt='remove this email routing' class='delete_field_choice' style='cursor:pointer;' onclick='DeleteRouting(<?php echo $i ?>);' onkeypress='DeleteRouting(<?php echo $i ?>);' />
								<?php } ?>
							</div>
						<?php
						}

						if ( $is_invalid_rule ) {
							?>
							<span class="validation_message"><?php esc_html_e( 'Please enter a valid email address for all highlighted routing rules above.', 'gravityforms' ) ?></span>
						<?php } ?>
						<input type="hidden" name="routing_count" id="routing_count" value="<?php echo $routing_list ?>" />
					<?php
					}
					?>
				</div>
			</td>
			<?php echo $subsetting_close; ?>
		</tr> <!-- / to routing -->
		<?php $ui_settings['notification_to_routing'] = ob_get_contents();
		ob_clean(); ?>

		<tr valign="top">
			<th scope="row">
				<label for="gform_notification_from_name">
					<?php esc_html_e( 'From Name', 'gravityforms' ); ?>
					<?php gform_tooltip( 'notification_from_name' ) ?>
				</label>
			</th>
			<td>
				<input type="text" class="fieldwidth-2 merge-tag-support mt-position-right mt-hide_all_fields" name="gform_notification_from_name" id="gform_notification_from_name" value="<?php echo esc_attr( rgget( 'fromName', $notification ) ) ?>" />
			</td>
		</tr> <!-- / from name -->
		<?php $ui_settings['notification_from_name'] = ob_get_contents();
		ob_clean(); ?>

		<?php
		$from_email_value      = rgar( $notification, 'from', '{admin_email}' );
		$is_invalid_from_email = ! $is_valid && $from_email_value && ! self::is_valid_notification_email( $from_email_value );
		$class                 = $is_invalid_from_email ? "class='gfield_error'" : '';
		?>
		<tr valign="top" <?php echo $class; ?>>
			<th scope="row">
				<label for="gform_notification_from">
					<?php esc_html_e( 'From Email', 'gravityforms' ); ?>
					<?php gform_tooltip( 'notification_from_email' ); ?>
				</label>
			</th>
			<td>
				<input type="text" class="fieldwidth-2 mt-position-right mt-hide_all_fields" name="gform_notification_from" id="gform_notification_from" value="<?php echo rgempty( 'from', $notification ) ? '{admin_email}' : esc_attr( rgget( 'from', $notification ) ); ?>" />
				<?php

				/**
				 * Disable the From Email warning.
				 *
				 * @since 2.4.13
				 *
				 * @param bool $disable_from_warning Should the From Email warning be disabled?
				 */
				$disable_from_warning = gf_apply_filters( array( 'gform_notification_disable_from_warning', $form['id'], rgar( $notification, 'id' ) ), false );

				// Display warning message if not using an email address containing the site domain or {admin_email}.
				if ( ! $disable_from_warning && rgar( $notification, 'service' ) === 'wordpress' && ! $is_invalid_from_email && ! self::is_site_domain_in_from( $from_email_value ) ) {
					echo '<div class="alert_yellow" style="padding:15px;margin-top:15px;">';
					$doc_page = 'https://docs.gravityforms.com/troubleshooting-notifications/#use-a-valid-from-address';
					echo sprintf( esc_html__( 'Warning! Using a third-party email in the From Email field may prevent your notification from being delivered. It is best to use an email with the same domain as your website. %sMore details in our documentation.%s', 'gravityforms' ), '<a href="' . esc_url( $doc_page ) . '" target="_blank" >', '</a>' );
					echo '</div>';
				}

				if ( $is_invalid_from_email ) {
					?>
					<br><span class="validation_message"><?php esc_html_e( 'Please enter a valid email address or {admin_email} merge tag in the From Email field.', 'gravityforms' ); ?></span>
					<?php
				}
				?>
			</td>
		</tr> <!-- / to from email -->
		<?php $ui_settings['notification_from'] = ob_get_contents();
		ob_clean(); ?>

		<?php
		$reply_to_value      = rgar( $notification, 'replyTo' );
		$is_invalid_reply_to = ! $is_valid && $reply_to_value && ! self::is_valid_notification_email( $reply_to_value );
		$class               = $is_invalid_reply_to ? "class='gfield_error'" : '';
		?>
		<tr valign="top" <?php echo $class ?>>
			<th scope="row">
				<label for="gform_notification_reply_to">
					<?php esc_html_e( 'Reply To', 'gravityforms' ); ?>
					<?php gform_tooltip( 'notification_reply_to' ) ?>
				</label>
			</th>
			<td>
				<input type="text" name="gform_notification_reply_to" id="gform_notification_reply_to" class="merge-tag-support mt-hide_all_fields fieldwidth-2" value="<?php echo esc_attr( $reply_to_value ) ?>" />
				<?php
				if ( $is_invalid_reply_to ) {
					?>
					<br><span class="validation_message"><?php esc_html_e( 'Please enter a valid email address or merge tag in the Reply To field.', 'gravityforms' ) ?></span><?php
				}
				?>
			</td>
		</tr> <!-- / reply to -->
		<?php $ui_settings['notification_reply_to'] = ob_get_contents();
		ob_clean(); ?>

		<?php

		/**
		 * Enable the CC Notification field.
		 *
		 * @since 2.3
		 *
		 * @param bool  $enable_cc     Should the CC field be enabled?
		 * @param array $notification The current notification object.
		 * @param array $from         The current form object.
		 */
		$enable_cc = gf_apply_filters( array( 'gform_notification_enable_cc', $form['id'], rgar( $notification, 'id' ) ), false, $notification, $form );

		$cc_value      = rgar( $notification, 'cc' );
		$is_invalid_cc = ! $is_valid && $cc_value && ! self::is_valid_notification_email( $cc_value );
		$class         = $is_invalid_cc ? "class='gfield_error'" : '';
		?>
		<tr valign="top" <?php echo $class ?>>
			<th scope="row">
				<label for="gform_notification_ccc">
					<?php esc_html_e( 'CC', 'gravityforms' ); ?>
					<?php gform_tooltip( 'notification_cc' ) ?>
				</label>
			</th>
			<td>
				<input type="text" name="gform_notification_cc" id="gform_notification_cc" value="<?php echo esc_attr( $cc_value ) ?>" class="merge-tag-support mt-hide_all_fields fieldwidth-2" />
				<?php
				if ( $is_invalid_cc ) {
					?>
					<br><span class="validation_message"><?php esc_html_e( 'Please enter a valid email address or merge tag in the CC field.', 'gravityforms' ) ?></span><?php
				}
				?>
			</td>
		</tr> <!-- / cc -->
		<?php
		if ( $enable_cc ) {
			$ui_settings['notification_cc'] = ob_get_contents();
		}
		ob_clean();
		?>

		<?php
		$bcc_value      = rgar( $notification, 'bcc' );
		$is_invalid_bcc = ! $is_valid && $bcc_value && ! self::is_valid_notification_email( $bcc_value );
		$class          = $is_invalid_bcc ? "class='gfield_error'" : '';
		?>
		<tr valign="top" <?php echo $class ?>>
			<th scope="row">
				<label for="gform_notification_bcc">
					<?php esc_html_e( 'BCC', 'gravityforms' ); ?>
					<?php gform_tooltip( 'notification_bcc' ) ?>
				</label>
			</th>
			<td>
				<input type="text" name="gform_notification_bcc" id="gform_notification_bcc" value="<?php echo esc_attr( $bcc_value ) ?>" class="merge-tag-support mt-hide_all_fields fieldwidth-2" />
				<?php
				if ( $is_invalid_bcc ) {
					?>
					<br><span class="validation_message"><?php esc_html_e( 'Please enter a valid email address or merge tag in the BCC field.', 'gravityforms' ) ?></span><?php
				}
				?>
			</td>
		</tr> <!-- / bcc -->
		<?php $ui_settings['notification_bcc'] = ob_get_contents();
		ob_clean(); ?>

		<?php
		$is_invalid_subject = ! $is_valid && empty( $_POST['gform_notification_subject'] );
		$subject_class      = $is_invalid_subject ? "class='gfield_error'" : '';
		?>
		<tr valign="top" <?php echo $subject_class ?>>
			<th scope="row">
				<label for="gform_notification_subject">
					<?php esc_html_e( 'Subject', 'gravityforms' ); ?><span class="gfield_required">*</span>
				</label>
			</th>
			<td>
				<input type="text" name="gform_notification_subject" id="gform_notification_subject" class="fieldwidth-1 merge-tag-support mt-hide_all_fields mt-position-right" value="<?php echo esc_attr( rgar( $notification, 'subject' ) ) ?>" />
				<?php
				if ( $is_invalid_subject ) {
					?>
					<span class="validation_message"><?php esc_html_e( 'Please enter a subject for the notification email', 'gravityforms' ) ?></span><?php
				}
				?>
			</td>
		</tr> <!-- / subject -->
		<?php $ui_settings['notification_subject'] = ob_get_contents();
		ob_clean(); ?>

		<?php
		$is_invalid_message = ! $is_valid && empty( $_POST['gform_notification_message'] );
		$message_class      = $is_invalid_message ? "class='gfield_error'" : '';
		?>
		<tr valign="top" <?php echo $message_class ?>>
			<th scope="row">
				<label for="gform_notification_message">
					<?php esc_html_e( 'Message', 'gravityforms' ); ?><span class="gfield_required">*</span>
				</label>
			</th>
			<td>

				<span class="mt-gform_notification_message"></span>

				<?php
				wp_editor( rgar( $notification, 'message' ), 'gform_notification_message', array( 'autop' => false, 'editor_class' => 'merge-tag-support mt-wp_editor mt-manual_position mt-position-right' ) );

				if ( $is_invalid_message ) {
					?>
					<span class="validation_message"><?php esc_html_e( 'Please enter a message for the notification email', 'gravityforms' ) ?></span><?php
				}
				?>
			</td>
		</tr> <!-- / message -->
		<?php $ui_settings['notification_message'] = ob_get_contents();
		ob_clean(); ?>

        <?php
        $upload_fields = GFCommon::get_fields_by_type( $form, array( 'fileupload' ) );
        if ( $upload_fields ) {
        ?>
        <tr valign="top">
            <th scope="row">
                <label for="gform_notification_attachments">
                    <?php esc_html_e( 'Attachments', 'gravityforms' ); ?>
                    <?php gform_tooltip( 'notification_attachments' ) ?>
                </label>
            </th>
            <td>
                <input type="checkbox" name="gform_notification_attachments" id="gform_notification_attachments" value="1" <?php checked( '1', rgar( $notification, 'enableAttachments' ) ) ?>/>
                <label for="gform_notification_attachments" class="inline">
					<?php esc_html_e( 'Attach uploaded files to notification', 'gravityforms' ); ?>
                </label>
            </td>
        </tr> <!-- / attachments -->
        <?php $ui_settings['notification_attachments'] = ob_get_contents();
        ob_clean();
        }
        ?>

        <tr valign="top">
			<th scope="row">
				<label for="gform_notification_disable_autoformat">
					<?php esc_html_e( 'Auto-formatting', 'gravityforms' ); ?>
					<?php gform_tooltip( 'notification_autoformat' ) ?>
				</label>
			</th>
			<td>
				<input type="checkbox" name="gform_notification_disable_autoformat" id="gform_notification_disable_autoformat" value="1" <?php echo empty( $notification['disableAutoformat'] ) ? '' : "checked='checked'" ?>/>
				<label for="gform_notification_disable_autoformat" class="inline">
					<?php esc_html_e( 'Disable auto-formatting', 'gravityforms' ); ?>
				</label>
			</td>
		</tr> <!-- / disable autoformat -->
		<?php $ui_settings['notification_disable_autoformat'] = ob_get_contents();
		ob_clean();

		?>

		<tr valign="top" <?php echo rgar( $notification, 'isDefault' ) ? 'style=display:none;' : ''; ?> >
			<th scope="row">
				<label for="gform_notification_conditional_logic">
					<?php esc_html_e( 'Conditional Logic', 'gravityforms' ) ?><?php gform_tooltip( 'notification_conditional_logic' ) ?>
				</label>
			</th>
			<td>
				<input type="checkbox" id="notification_conditional_logic" onclick="SetConditionalLogic(this.checked); ToggleConditionalLogic(false, 'notification');" onkeypress="SetConditionalLogic(this.checked); ToggleConditionalLogic(false, 'notification');" <?php checked( is_array( rgar( $notification, 'conditionalLogic' ) ), true ) ?> />
				<label for="notification_conditional_logic" class="inline"><?php esc_html_e( 'Enable conditional logic', 'gravityforms' ) ?><?php gform_tooltip( 'notification_conditional_logic' ) ?></label>
				<br />
			</td>
		</tr> <!-- / conditional logic -->
		<tr>
			<td colspan="2">
				<div id="notification_conditional_logic_container" class="gf_animate_sub_settings" style="padding-left:10px;">
					<!-- content dynamically created from form_admin.js -->
				</div>
			</td>
		</tr>

		<?php $ui_settings['notification_conditional_logic'] = ob_get_contents();
		ob_clean(); ?>


		<?php
		ob_end_clean();
		
		/**
		 * Add new or modify existing notification settings that display on the Notification Edit screen.
		 *
		 * @since Unknown
		 * 
		 * @param array $ui_settings  An array of settings for the notification UI.
		 * @param array $notification The current notification object being edited.
		 * @param array $form         The current form object to which the notification being edited belongs.
		 * @param bool  $is_valid     Whether or not the current notification has passed validation
		 */
		$ui_settings = gf_apply_filters( array( 'gform_notification_ui_settings', $form_id ), $ui_settings, $notification, $form, $is_valid );

		return $ui_settings;
	}

	/**
	 * Get list of notification services.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return array The notification services available.
	 */
	public static function get_notification_services() {
		
		$services = array(
			'wordpress' => array(
				'label' => esc_html__( 'WordPress', 'gravityforms' ),
				'image' => admin_url( 'images/wordpress-logo.svg' )
			)
		);

		/**
		 * Filters the list of notification services.
		 *
		 * @since 1.9.16
		 *
		 * @param array $services The services available.
		 */
		return gf_apply_filters( array( 'gform_notification_services' ), $services );
		
	}

	/**
	 * Get the notification events for the current form.
	 *
	 * @since  Unknown
	 * @access public
	 * 
	 * @param array $form The current Form Object.
	 *
	 * @return array Notification events available within the form.
	 */
	public static function get_notification_events( $form ) {
		$notification_events = array( 'form_submission' => esc_html__( 'Form is submitted', 'gravityforms' ) );
		if ( rgars( $form, 'save/enabled' ) ) {
			$notification_events['form_saved']                = esc_html__( 'Form is saved', 'gravityforms' );
			$notification_events['form_save_email_requested'] = esc_html__( 'Save and continue email is requested', 'gravityforms' );
		}

		/**
		 * Allow custom notification events to be added.
		 *
		 * @since Unknown
		 * 
		 * @param array $notification_events The notification events.
		 * @param array $form The current form.
		 */
		return apply_filters( 'gform_notification_events', $notification_events, $form );
	}

	/**
	 * Validates notifications.
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @uses GFNotification::is_valid_notification_to()
	 * @uses GFNotification::is_valid_notification_email()
	 *
	 * @return bool True if valid. Otherwise, false.
	 */
	private static function validate_notification() {
		$is_valid = self::is_valid_notification_to() && ! rgempty( 'gform_notification_subject' ) && ! rgempty( 'gform_notification_message' );

		$cc = rgpost( 'gform_notification_cc' );
		if ( ! empty( $cc ) && ! self::is_valid_notification_email( $cc ) ) {
			$is_valid = false;
		}

		$bcc = rgpost( 'gform_notification_bcc' );
		if ( ! empty( $bcc ) && ! self::is_valid_notification_email( $bcc ) ) {
			$is_valid = false;
		}

		$reply_to = rgpost( 'gform_notification_reply_to' );
		if ( ! empty( $reply_to ) && ! self::is_valid_notification_email( $reply_to ) ) {
			$is_valid = false;
		}

		$from_email = rgpost( 'gform_notification_from' );
		if ( ! empty( $from_email ) && ! self::is_valid_notification_email( $from_email ) ) {
			$is_valid = false;
		}

		return $is_valid;
	}

	/**
	 * Determines if the notification contains valid routing.
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @uses GFCommon::json_decode()
	 * @uses GFNotification::is_valid_notification_email()
	 *
	 * @see GFNotification::is_valid_notification_email
	 *
	 * @return bool True if valid, Otherwise, false.
	 */
	private static function is_valid_routing() {
		$routing = ! empty( $_POST['gform_routing_meta'] ) ? GFCommon::json_decode( stripslashes( $_POST['gform_routing_meta'] ), true ) : null;
		if ( empty( $routing ) ) {
			return false;
		}

		foreach ( $routing as $route ) {
			if ( ! self::is_valid_notification_email( $route['email'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validates email addresses within notifications.
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @uses GFCommon::is_invalid_or_empty_email()
	 *
	 * @param $text String containing comma-separated email addresses.
	 *
	 * @return bool True if valid. Otherwise, false.
	 */
	private static function is_valid_notification_email( $text ) {
		if ( empty( $text ) ) {
			return false;
		}

		$emails = explode( ',', $text );
		foreach ( $emails as $email ) {
			$email            = trim( $email );
			$invalid_email    = GFCommon::is_invalid_or_empty_email( $email );
			// this used to be more strict; updated to match any merge-tag-like string
			$invalid_variable = ! preg_match( '/^{.+}$/', $email );

			if ( $invalid_email && $invalid_variable ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validates the notification destination
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @uses GFNotification::is_valid_routing()
	 * @uses GFNotification::is_valid_notification_email()
	 *
	 * @return bool $is_valid True if valid. Otherwise, false.
	 */
	private static function is_valid_notification_to() {

		$notification_to_email = rgpost( 'gform_notification_to_email' );
		$is_valid = ( rgpost( 'gform_notification_to_type' ) == 'routing' && self::is_valid_routing() )
			||
			( rgpost( 'gform_notification_to_type' ) == 'email' && ( self::is_valid_notification_email( $notification_to_email ) ) )
			||
			( rgpost( 'gform_notification_to_type' ) == 'field' && ( ! rgempty( 'gform_notification_to_field' ) ) )
			|| rgpost( 'gform_notification_to_type' ) == 'hidden';

		/**
		 * Allows overriding of the notification destination validation
		 *
		 * @since Unknown
		 *
		 * @param bool   $is_valid                    True if valid. False, otherwise.
		 * @param string $gform_notification_to_type  The type of destination.
		 * @param string $gform_notification_to_email The destination email address, if available.
		 * @param string $gform_notification_to_field The field that is being used for the notification, if available.
		 */
		return $is_valid = apply_filters( 'gform_is_valid_notification_to', $is_valid, rgpost( 'gform_notification_to_type' ), rgpost( 'gform_notification_to_email' ), rgpost( 'gform_notification_to_field' ) );
	}

	/**
	 * Checks if notification from email is using the site domain.
	 *
	 * @since  2.4.12
	 *
	 * @param string $from_email Email address to check.
	 *
	 * @return bool
	 */
	private static function is_site_domain_in_from( $from_email ) {

		// If {admin_email} is used check email from WP settings.
		if ( strpos( $from_email, '{admin_email}' ) !== false ) {
			$from_email = get_bloginfo( 'admin_email' );
		}

		return GFCommon::email_domain_matches( $from_email );

	}

	/**
	 * Gets the first field that can be used for notification routing.
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @uses GFNotification::get_routing_field_types()
	 *
	 * @param array $form The Form Object to search through.
	 *
	 * @return int The field ID. Returns 0 if none found.
	 */
	private static function get_first_routing_field( $form ) {
		foreach ( $form['fields'] as $field ) {
			if ( in_array( $field->get_input_type(), self::get_routing_field_types() ) ) {
				return $field->id;
			}
		}

		return 0;
	}

	/**
	 * Gets all fields that can be used for notification routing and builds dropdowns.
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @uses \GFFormsModel::get_label()
	 * @uses GFNotification::get_routing_field_types()
	 *
	 * @param array $form              The Form Object to search through.
	 * @param int   $selected_field_id The currently selected field ID.
	 *
	 * @return string $str The option HTML markup.
	 */
	private static function get_routing_fields( $form, $selected_field_id ) {
		$str = '';
		foreach ( $form['fields'] as $field ) {
			$field_label = RGFormsModel::get_label( $field );
			if ( in_array( $field->get_input_type(), self::get_routing_field_types() ) ) {
				$selected = $field->id == $selected_field_id ? "selected='selected'" : '';
				$str .= "<option value='" . $field->id . "' " . $selected . '>' . $field_label . '</option>';
			}
		}

		return $str;
	}

	/**
	 * Gets supported routing field types.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFNotification::$supported_fields()
	 *
	 * @return array $field_types Supported field types.
	 */
	public static function get_routing_field_types() {
		/**
		 * Filters the field types supported by notification routing
		 *
		 * @since 1.9.6
		 *
		 * @param array GFNotification::$supported_fields Currently supported field types.
		 */
		$field_types = apply_filters( 'gform_routing_field_types', self::$supported_fields );
		return $field_types;
	}

	/**
	 * Gets field values to be used with routing
	 *
	 * @since  Unknown
	 * @access private
	 *
	 * @uses GFNotification::get_first_routing_field()
	 * @uses GFFormsModel::get_field()
	 *
	 * @param int    $i                The routing rule ID.
	 * @param array  $form             The Form Object.
	 * @param int    $field_id         The field ID.
	 * @param string $selected_value   The field value of the selected item.
	 * @param int    $max_field_length Not used. Defaults to 16.
	 *
	 * @return string $str The HTML string containing the value.
	 */
	private static function get_field_values( $i, $form, $field_id, $selected_value, $max_field_length = 16 ) {
		if ( empty( $field_id ) ) {
			$field_id = self::get_first_routing_field( $form );
		}

		if ( empty( $field_id ) ) {
			return '';
		}

		$field           = RGFormsModel::get_field( $form, $field_id );
		$is_any_selected = false;
		$str             = '';

		if ( ! $field ) {
			return '';
		}

		if ( $field->type == 'post_category' && $field->displayAllCategories == true ) {
			$str .= wp_dropdown_categories( array( 'class' => 'gfield_routing_select gfield_category_dropdown gfield_routing_value_dropdown', 'orderby' => 'name', 'id' => 'routing_value_' . $i, 'selected' => $selected_value, 'hierarchical' => true, 'hide_empty' => 0, 'echo' => false ) );
		} elseif ( $field->choices ) {
			$str .= "<select id='routing_value_" . $i . "' class='gfield_routing_select gfield_routing_value_dropdown'>";

			if ( $field->placeholder ) {
				$str .= "<option value=''>" . esc_html( $field->placeholder ) . '</option>';
			}

			foreach ( $field->choices as $choice ) {
				$is_selected = $choice['value'] == $selected_value;
				$selected    = $is_selected ? "selected='selected'" : '';
				if ( $is_selected ) {
					$is_any_selected = true;
				}

				$str .= "<option value='" . esc_attr( $choice['value'] ) . "' " . $selected . '>' . $choice['text'] . '</option>';
			}

			// Adding current selected field value to the list
			if ( ! $is_any_selected && ! empty( $selected_value ) ) {
				$str .= "<option value='" . esc_attr( $selected_value ) . "' selected='selected'>" . $selected_value . '</option>';
			}
			$str .= '</select>';
		} else {
			// Create a text field for fields that don't have choices (i.e text, textarea, number, email, etc...)
			$str = "<input type='text' placeholder='" . esc_html__( 'Enter value', 'gravityforms' ) . "' class='gfield_routing_select' id='routing_value_" . $i . "' value='" . esc_attr( $selected_value ) . "' onchange='SetRouting(" . $i . ");' onkeyup='SetRouting(" . $i . ");'>";
		}

		return $str;
	}

	/**
	 * Gets a dropdown list of available post categories
	 *
	 * @since  Unknown
	 * @access public
	 */
	public static function get_post_category_values() {

		$id       = 'routing_value_' . rgpost( 'ruleIndex' );
		$selected = rgempty( 'selectedValue' ) ? 0 : rgpost( 'selectedValue' );

		$dropdown = wp_dropdown_categories( array( 'class' => 'gfield_routing_select gfield_routing_value_dropdown gfield_category_dropdown', 'orderby' => 'name', 'id' => $id, 'selected' => $selected, 'hierarchical' => true, 'hide_empty' => 0, 'echo' => false ) );
		die( $dropdown );
	}

	/**
	 * Delete a form notification
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFFormsModel::get_form_meta()
	 * @uses GFFormsModel::flush_current_forms()
	 * @uses GFFormsModel::save_form_notifications()
	 *
	 * @param int       $notification_id The notification ID to delete
	 * @param int|array $form_id         Can pass a form ID or a form object
	 *
	 * @return int|false The result from $wpdb->query deletion
	 */
	public static function delete_notification( $notification_id, $form_id ) {

		if ( ! $form_id ) {
			return false;
		}

		$form = ! is_array( $form_id ) ? RGFormsModel::get_form_meta( $form_id ) : $form_id;

		/**
		 * Fires before a notification is deleted.
		 *
		 * @since Unknown
		 *
		 * @param array $form['notifications'][$notification_id] The notification being deleted.
		 * @param array $form                                    The Form Object that the notification is being deleted from.
		 */
		do_action( 'gform_pre_notification_deleted', $form['notifications'][ $notification_id ], $form );

		unset( $form['notifications'][ $notification_id ] );

		// Clear Form cache so next retrieval of form meta will reflect deleted notification
		RGFormsModel::flush_current_forms();

		return RGFormsModel::save_form_notifications( $form['id'], $form['notifications'] );
	}

	/**
	 * Duplicates a form notification.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFFormsModel::get_form_meta()
	 * @uses GFNotification::is_unique_name()
	 * @uses GFFormsModel::flush_current_forms()
	 * @uses GFFormsModel::save_form_notifications()
	 *
	 * @param int       $notification_id The notification ID to duplicate.
	 * @param int|array $form_id         The ID of the form or Form Object that contains the notification.
	 *
	 * @return int|false The result from $wpdb->query after duplication
	 */
	public static function duplicate_notification( $notification_id, $form_id ) {

		if ( ! $form_id ) {
			return false;
		}

		$form = ! is_array( $form_id ) ? RGFormsModel::get_form_meta( $form_id ) : $form_id;

		$new_notification = $form['notifications'][ $notification_id ];
		$name             = rgar( $new_notification, 'name' );
		$new_id           = uniqid();

		$count    = 2;
		$new_name = $name . ' - Copy 1';
		while ( ! self::is_unique_name( $new_name, $form['notifications'] ) ) {
			$new_name = $name . " - Copy $count";
			$count ++;
		}
		$new_notification['name'] = $new_name;
		$new_notification['id']   = $new_id;
		unset( $new_notification['isDefault'] );
		if ( $new_notification['toType'] == 'hidden' ) {
			$new_notification['toType'] = 'email';
		}

		$form['notifications'][ $new_id ] = $new_notification;

		// Clear form cache so next retrieval of form meta will return duplicated notification
		RGFormsModel::flush_current_forms();

		return RGFormsModel::save_form_notifications( $form['id'], $form['notifications'] );
	}

	/**
	 * Checks if a notification name is unique.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param string $name          The name to check.
	 * @param array  $notifications The notifications to check against.
	 *
	 * @return bool Returns true if unique.  Otherwise, false.
	 */
	public static function is_unique_name( $name, $notifications ) {

		foreach ( $notifications as $notification ) {
			if ( strtolower( rgar( $notification, 'name' ) ) == strtolower( $name ) ) {
				return false;
			}
		}

		return true;
	}

}

/**
 * Class GFNotificationTable.
 *
 * Extends WP_List_Table to display the notifications list.
 *
 * @uses WP_List_Table
 */
class GFNotificationTable extends WP_List_Table {

	/**
	 * Contains the Form Object.
	 *
	 * Passed when calling the class.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @var array
	 */
	public $form;

	/**
	 * Contains the notification events for the form.
	 *
	 * Generated in the constructor based on the passed Form Object.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @var array
	 */
	public $notification_events;

	/**
	 * Contains the notification services for the form.
	 *
	 * Generated in the constructor.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @var array
	 */
	public $notification_services;

	/**
	 * GFNotificationTable constructor.
	 *
	 * Sets required class properties and defines the list table columns.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFNotification::get_notification_events()
	 * @uses GFNotification::get_notification_services()
	 * @uses GFNotificationTable::$form
	 * @uses GFNotificationTable::$notification_events
	 * @uses GFNotificationTable::$notification_services
	 * @uses WP_List_Table::__construct()
	 *
	 * @param array $form The Form Object to use.
	 */
	function __construct( $form ) {

		$this->form                  = $form;
		$this->notification_events   = GFNotification::get_notification_events( $form );
		$this->notification_services = GFNotification::get_notification_services();

		$columns = array(
			'cb'      => '',
			'name'    => esc_html__( 'Name', 'gravityforms' ),
			'subject' => esc_html__( 'Subject', 'gravityforms' ),
		);

		if ( count( $this->notification_events ) > 1 ) {
			$columns['event'] = esc_html__( 'Event', 'gravityforms' );
		}

		if ( count( $this->notification_services ) > 1 ) {
			$columns['service'] = esc_html__( 'Service', 'gravityforms' );
		}
		
		$this->_column_headers = array(
			$columns,
			array(),
			array( 'name' => array( 'name', false ) ),
			'name',
		);

		parent::__construct();
	}

	/**
	 * Prepares the list items for displaying.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses WP_List_Table::$items
	 * @uses GFNotificationTable::$form
	 *
	 * @return void
	 */
	function prepare_items() {

		$this->items = $this->form['notifications'];

		switch ( rgget( 'orderby' ) ) {

			case 'name':

				// Sort notifications alphabetically.
				usort( $this->items, array( $this, 'sort_notifications' ) );

				// Reverse sort.
				if ( 'desc' === rgget( 'order' ) ) {
					$this->items = array_reverse( $this->items );
				}

				break;

			default:
				break;

		}

	}

	/**
	 * Sort notifications alphabetically.
	 *
	 * @since  2.4
	 * @access public
	 *
	 * @param array $a First notification to compare.
	 * @param array $b Second notification to compare.
	 *
	 * @return int
	 */
	function sort_notifications( $a = array(), $b = array() ) {

		return strcasecmp( $a['name'], $b['name'] );

	}

	/**
	 * Displays the list table.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses \WP_List_Table::get_table_classes()
	 * @uses \WP_List_Table::print_column_headers()
	 * @uses \WP_List_Table::display_rows_or_placeholder()
	 *
	 * @return void
	 */
	function display() {
		$singular = rgar( $this->_args, 'singular' );
		?>

		<table class="wp-list-table <?php echo esc_attr( implode( ' ', $this->get_table_classes() ) ); ?>" cellspacing="0">
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

			<tbody id="the-list"<?php if ( $singular ) {
				echo " class='list:$singular'";
			} ?>>

			<?php $this->display_rows_or_placeholder(); ?>

			</tbody>
		</table>

	<?php
	}

	/**
	 * Builds the single row content for the list table
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses WP_List_Table::single_row_columns()
	 *
	 * @param object $item The current view.
	 *
	 * @return void
	 */
	function single_row( $item ) {
		static $row_class = '';
		$row_class = ( $row_class == '' ? ' class="alternate"' : '' );

		echo '<tr id="notification-' . esc_attr( $item['id'] ) . '" ' . $row_class . '>';
		echo $this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Gets the column headers.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by Filter: manage_{$this->screen->id}_columns
	 * @uses    WP_List_Table::$_column_headers
	 *
	 * @return array The column headers.
	 */
	function get_columns() {
		return $this->_column_headers[0];
	}

	/**
	 * Defines the default values in a column.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param object $item   The content to display.
	 * @param string $column The column to apply to.
	 *
	 * @return void
	 */
	function column_default( $item, $column ) {
		echo rgar( $item, $column );
	}

	/**
	 * Defines a checkbox column.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFCommon::get_base_url()
	 *
	 * @param array $item The column data.
	 *
	 * @return void
	 */
	function column_cb( $item ) {
		if ( rgar( $item, 'isDefault' ) ) {
			return;
		}
		$is_active = isset( $item['isActive'] ) ? $item['isActive'] : true;
		?>
		<img src="<?php echo GFCommon::get_base_url() ?>/images/active<?php echo intval( $is_active ) ?>.png" style="cursor: pointer;margin:-5px 0 0 8px;" alt="<?php $is_active ? esc_attr__( 'Active', 'gravityforms' ) : esc_attr__( 'Inactive', 'gravityforms' ); ?>" title="<?php echo $is_active ? esc_attr__( 'Active', 'gravityforms' ) : esc_attr__( 'Inactive', 'gravityforms' ); ?>" onclick="ToggleActive(this, '<?php echo esc_js( $item['id'] ) ?>'); " onkeypress="ToggleActive(this, '<?php echo esc_js( $item['id'] ) ?>'); " />
	<?php
	}

	/**
	 * Sets the column name in the list table.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param array $item The column data.
	 *
	 * @return void
	 */
	function column_name( $item ) {
		$edit_url = add_query_arg( array( 'nid' => $item['id'] ) );
		/**
		 * Filters the row action links.
		 *
		 * @since Unknown
		 *
		 * @param array $actions The action links.
		 */
		$actions  = apply_filters(
			'gform_notification_actions', array(
				'edit'      => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'gravityforms' ) . '</a>',
				'duplicate' => '<a href="javascript:void(0);" onclick="javascript: DuplicateNotification(\'' . esc_js( $item['id'] ) . '\');" onkeypress="javascript: DuplicateNotification(\'' . esc_js( $item['id'] ) . '\');" style="cursor:pointer;">' . esc_html__( 'Duplicate', 'gravityforms' ) . '</a>',
				'delete'    => '<a href="javascript:void(0);" class="submitdelete" onclick="javascript: if(confirm(\'' . esc_js( esc_html__( 'WARNING: You are about to delete this notification.', 'gravityforms' ) ) . esc_js( esc_html__( "'Cancel' to stop, 'OK' to delete.", 'gravityforms' ) ) . '\')){ DeleteNotification(\'' . esc_js( $item['id'] ) . '\'); }" onkeypress="javascript: if(confirm(\'' . esc_js( esc_html__( 'WARNING: You are about to delete this notification.', 'gravityforms' ) ) . esc_js( esc_html__( "'Cancel' to stop, 'OK' to delete.", 'gravityforms' ) ) . '\')){ DeleteNotification(\'' . esc_js( $item['id'] ) . '\'); }" style="cursor:pointer;">' . esc_html__( 'Delete', 'gravityforms' ) . '</a>'
			)
		);

		if ( isset( $item['isDefault'] ) && $item['isDefault'] ) {
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

	/**
	 * Displays the content of the Service column.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFNotificationTable::$notification_services
	 *
	 * @param array $notification The Notification Object.
	 *
	 * @return void
	 */
	function column_service( $notification ) {
		
		$services = $this->notification_services;
		
		if ( ! rgar( $notification, 'service' ) ) {
			esc_html_e( 'WordPress', 'gravityforms' );
		} else if ( rgar( $services, $notification['service'] ) ) {
			$service = rgar( $services, $notification['service'] );
			echo rgar( $service, 'label' );
		} else {
			esc_html_e( 'Undefined Service', 'gravityforms' );
		}
		
	}

	/**
	 * Displays the content of the Event column.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFNotificationTable::$notification_events()
	 *
	 * @param array $notification The Notification Object.
	 *
	 * @return void
	 */
	function column_event( $notification ) {
		echo rgar( $this->notification_events, rgar( $notification, 'event' ) );
	}

	/**
	 * Content to display if the form does not have any notifications.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return void
	 */
	function no_items() {
		$url = add_query_arg( array( 'nid' => 0 ) );
		printf( esc_html__( "This form doesn't have any notifications. Let's go %screate one%s.", 'gravityforms' ), "<a href='" . esc_url( $url ) . "'>", '</a>' );
	}
}
