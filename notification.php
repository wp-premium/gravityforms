<?php

if(!class_exists('GFForms')){
    die();
}

Class GFNotification {

	private static $supported_fields = array("checkbox", "radio", "select", "text", "website", "textarea", "email", "hidden", "number", "phone", "multiselect", "post_title",
		                            "post_tags", "post_custom_field", "post_content", "post_excerpt");

    private static function get_notification($form, $notification_id){
        foreach($form["notifications"] as $id => $notification){
            if($id == $notification_id){
                return $notification;
            }
        }
        return array();
    }

    public static function notification_page() {
        $form_id = rgget('id');
        $notification_id = rgget("nid");
        if(!rgblank($notification_id))
            self::notification_edit_page($form_id, $notification_id);
        else
            self::notification_list_page($form_id);
    }

    public static function notification_edit_page($form_id, $notification_id) {

        if(!rgempty("gform_notification_id"))
            $notification_id = rgpost("gform_notification_id");

        $form = RGFormsModel::get_form_meta($form_id);

        $form = apply_filters("gform_form_notification_page_{$form_id}", apply_filters("gform_form_notification_page", $form, $notification_id), $notification_id);

        $notification = !$notification_id ? array() : self::get_notification($form, $notification_id);

        // added second condition to account for new notifications with errors as notification ID will
        // be available in $_POST but the notification has not actually been saved yet
        $is_new_notification = empty($notification_id) || empty($notification);

        $is_valid = true;
        $is_update = false;
        if(rgpost("save")){

            check_admin_referer('gforms_save_notification', 'gforms_save_notification');
            
            //clear out notification because it could have legacy data populated
            $notification = array( 'isActive' => isset( $notification['isActive'] ) ? rgar( $notification, 'isActive') : true );

            $is_update = true;

            if($is_new_notification){
                $notification_id = uniqid();
                $notification["id"] = $notification_id;
            }
            else {
				$notification["id"] = $notification_id;
            }

            $notification["name"] = rgpost("gform_notification_name");
            $notification["event"] = rgpost("gform_notification_event");
            $notification["to"] = rgpost("gform_notification_to_type") == "field" ? rgpost("gform_notification_to_field") : rgpost("gform_notification_to_email");
            $notification["toType"] = rgpost("gform_notification_to_type");
            $notification["bcc"] = rgpost("gform_notification_bcc");
            $notification["subject"] = rgpost("gform_notification_subject");
            $notification["message"] = rgpost("gform_notification_message");
            $notification["from"] = rgpost("gform_notification_from");
            $notification["fromName"] = rgpost("gform_notification_from_name");
            $notification["replyTo"] = rgpost("gform_notification_reply_to");
            $notification["routing"] = !rgempty("gform_routing_meta") ? GFCommon::json_decode(rgpost("gform_routing_meta"), true) : null;
            $notification["conditionalLogic"] = !rgempty("gform_conditional_logic_meta") ? GFCommon::json_decode(rgpost("gform_conditional_logic_meta"), true) : null;
            $notification["disableAutoformat"] = rgpost("gform_notification_disable_autoformat");

            $notification = apply_filters( 'gform_pre_notification_save', apply_filters( "gform_pre_notification_save{$form['id']}", $notification, $form ), $form );

            //validating input...
            $is_valid = self::validate_notification();
            if($is_valid){
                //input valid, updating...
                //emptying notification email if it is supposed to be disabled
                if($_POST["gform_notification_to_type"] == "routing")
                    $notification["to"] = "";
                else
                    $notification["routing"] = null;

                // trim values
                $notification = GFFormsModel::trim_conditional_logic_values_from_element($notification, $form);

                $form["notifications"][$notification_id] = $notification;

                RGFormsModel::save_form_notifications($form_id, $form['notifications']);
            }
        }

        if($is_update && $is_valid){
        	GFCommon::add_message( sprintf( __('Notification saved successfully. %sBack to notifications.%s', 'gravityforms'), '<a href="' . remove_query_arg('nid') . '">', '</a>') );
        }
        else if($is_update && !$is_valid){
        	GFCommon::add_error_message(__('Notification could not be updated. Please enter all required information below.', 'gravityforms'));
        }

        // moved page header loading here so the admin messages can be set upon saving and available for the header to print out
        GFFormSettings::page_header(__('Notifications', 'gravityforms'));

        $notification_ui_settings = self::get_notification_ui_settings($notification);

        ?>
        <link rel="stylesheet" href="<?php echo GFCommon::get_base_url()?>/css/admin.css?ver=<?php echo GFCommon::$version ?>" />

        <script type="text/javascript">

        var gform_has_unsaved_changes = false;
        jQuery(document).ready(function(){

            jQuery("#entry_form input, #entry_form textarea, #entry_form select").change(function(){
                gform_has_unsaved_changes = true;
            });

            window.onbeforeunload = function(){
                if (gform_has_unsaved_changes){
                    return "You have unsaved changes.";
                }
            }

            ToggleConditionalLogic(true, 'notification');

            jQuery(document).on('change', '.gfield_routing_value_dropdown', function(){
                SetRoutingValueDropDown(jQuery(this));
            });

        });

        <?php
        if(empty($form["notifications"]))
            $form["notifications"] = array();

        $entry_meta = GFFormsModel::get_entry_meta($form_id);
        $entry_meta = apply_filters("gform_entry_meta_conditional_logic_notifications", $entry_meta, $form, $notification_id);

        ?>

        var form = <?php echo GFCommon::json_encode($form) ?>;
        var current_notification = <?php echo GFCommon::json_encode($notification) ?>;
        var entry_meta = <?php echo GFCommon::json_encode($entry_meta) ?>;

        function SetRoutingValueDropDown(element){
            //parsing ID to get routing Index
            var index = element.attr("id").replace("routing_value_", "");
            SetRouting(index);
        }

        function CreateRouting(routings){
            var str = "";
            for(var i=0; i< routings.length; i++){

                var isSelected = routings[i].operator == "is" ? "selected='selected'" :"";
                var isNotSelected = routings[i].operator == "isnot" ? "selected='selected'" :"";
                var greaterThanSelected = routings[i].operator == ">" ? "selected='selected'" :"";
                var lessThanSelected = routings[i].operator == "<" ? "selected='selected'" :"";
                var containsSelected = routings[i].operator == "contains" ? "selected='selected'" :"";
                var startsWithSelected = routings[i].operator == "starts_with" ? "selected='selected'" :"";
                var endsWithSelected = routings[i].operator == "ends_with" ? "selected='selected'" :"";
                var email = routings[i]["email"] ? routings[i]["email"] : '';

                str += "<div style='width:99%'><?php _e("Send to", "gravityforms") ?> <input type='text' id='routing_email_" + i +"' value='" + email + "' onkeyup='SetRouting(" + i + ");'/>";
                str += " <?php _e("if", "gravityforms") ?> " + GetRoutingFields(i, routings[i].fieldId);
                str += "<select id='routing_operator_" + i + "' onchange='SetRouting(" + i + ");' class='gform_routing_operator'>";
                str += "<option value='is' " + isSelected + "><?php _e("is", "gravityforms") ?></option>";
                str += "<option value='isnot' " + isNotSelected + "><?php _e("is not", "gravityforms") ?></option>";
                str += "<option value='>' " + greaterThanSelected + "><?php _e("greater than", "gravityforms") ?></option>";
                str += "<option value='<' " + lessThanSelected + "><?php _e("less than", "gravityforms") ?></option>";
                str += "<option value='contains' " + containsSelected + "><?php _e("contains", "gravityforms") ?></option>";
                str += "<option value='starts_with' " + startsWithSelected + "><?php _e("starts with", "gravityforms") ?></option>";
                str += "<option value='ends_with' " + endsWithSelected + "><?php _e("ends with", "gravityforms") ?></option>";
                str += "</select>";
                str += GetRoutingValues(i, routings[i].fieldId, routings[i].value);
                str += "<a class='gf_insert_field_choice' title='add another rule' onclick=\"InsertRouting(" + (i+1) + ");\"><i class='fa fa-plus-square'></i></a>";
                if(routings.length > 1 )
                    str += "<a class='gf_delete_field_choice' title='remove this rule' onclick=\"DeleteRouting(" + i + ");\"><i class='fa fa-minus-square'></i></a>";

                str += "</div>";
            }

            jQuery("#gform_notification_to_routing_rules").html(str);
        }

        function GetRoutingValues(index, fieldId, selectedValue){
            str = GetFieldValues(index, fieldId, selectedValue, 16);

            return str;
        }

        function GetRoutingFields(index, selectedItem){
            var str = "<select id='routing_field_id_" + index + "' class='gfield_routing_select' onchange='jQuery(\"#routing_value_" + index + "\").replaceWith(GetRoutingValues(" + index + ", jQuery(this).val())); SetRouting(" + index + "); '>";
            str += GetSelectableFields(selectedItem, 16);
            str += "</select>";

            return str;
        }

        //---------------------- generic ---------------
        function GetSelectableFields(selectedFieldId, labelMaxCharacters){
            var str = "";
            var inputType;
            for(var i=0; i<form.fields.length; i++){
                inputType = form.fields[i].inputType ? form.fields[i].inputType : form.fields[i].type;
                //see if this field type can be used for conditionals
                if (IsNotificationConditionalLogicField(form.fields[i])) {
                    var selected = form.fields[i].id == selectedFieldId ? "selected='selected'" : "";
                    str += "<option value='" + form.fields[i].id + "' " + selected + ">" + form.fields[i].label + "</option>";
                }
            }
            return str;
        }

        function IsNotificationConditionalLogicField(field){
        	//this function is a duplicate of IsConditionalLogicField from form_editor.js
		    inputType = field.inputType ? field.inputType : field.type;
		    var supported_fields = ["checkbox", "radio", "select", "text", "website", "textarea", "email", "hidden", "number", "phone", "multiselect", "post_title",
		                            "post_tags", "post_custom_field", "post_content", "post_excerpt"];

		    var index = jQuery.inArray(inputType, supported_fields);

		    return index >= 0;
		}

        function GetFirstSelectableField(){
            var inputType;
            for(var i=0; i<form.fields.length; i++){
                inputType = form.fields[i].inputType ? form.fields[i].inputType : form.fields[i].type;
                if (IsNotificationConditionalLogicField(form.fields[i])){
                    return form.fields[i].id;
				}
            }

            return 0;
        }

        function TruncateMiddle(text, maxCharacters){
            if(!text)
                return "";

            if(text.length <= maxCharacters)
                return text;
            var middle = parseInt(maxCharacters / 2);
            return text.substr(0, middle) + "..." + text.substr(text.length - middle, middle);

        }

        function GetFieldValues(index, fieldId, selectedValue, labelMaxCharacters){
            if(!fieldId)
                fieldId = GetFirstSelectableField();

            if(!fieldId)
                return "";

            var str = "";
            var field = GetFieldById(fieldId);
            var isAnySelected = false;

            if(!field)
        		return "";

            if(field["type"] == "post_category" && field["displayAllCategories"]){
            	var dropdown_id = "routing_value_" + index;
		        var dropdown = jQuery('#' + dropdown_id + ".gfield_category_dropdown");

		        //don't load category drop down if it already exists (to avoid unecessary ajax requests)
		        if(dropdown.length > 0){

		            var options = dropdown.html();
		            options = options.replace("value=\"" + selectedValue + "\"", "value=\"" + selectedValue + "\" selected=\"selected\"");
		            str = "<select id='" + dropdown_id + "' class='gfield_routing_select gfield_category_dropdown gfield_routing_value_dropdown'>" + options + "</select>";
		        }
		        else{
		            //loading categories via AJAX
		            jQuery.post(ajaxurl,{   action:"gf_get_notification_post_categories",
		                                    ruleIndex: index,
		                                    selectedValue: selectedValue},
		                                function(dropdown_string){
		                                    if(dropdown_string){
		                                        jQuery('#gfield_ajax_placeholder_' + index).replaceWith(dropdown_string.trim());
		                                    }
		                                }
		                        );

		            //will be replaced by real drop down during the ajax callback
		            str = "<select id='gfield_ajax_placeholder_" + index + "' class='gfield_routing_select'><option><?php _e("Loading...", "gravityforms"); ?></option></select>";
		        }
			}
            else if(field.choices){
            	//create a drop down for fields that have choices (i.e. drop down, radio, checkboxes, etc...)
	            str = "<select class='gfield_routing_select gfield_routing_value_dropdown' id='routing_value_" + index + "'>";
	            for(var i=0; i<field.choices.length; i++){
	                var choiceValue = field.choices[i].value ? field.choices[i].value : field.choices[i].text;
	                var isSelected = choiceValue == selectedValue;
	                var selected = isSelected ? "selected='selected'" : "";
	                if(isSelected)
	                    isAnySelected = true;

	                str += "<option value='" + choiceValue.replace(/'/g, "&#039;") + "' " + selected + ">" + field.choices[i].text + "</option>";
	            }

	            if(!isAnySelected && selectedValue){
	                str += "<option value='" + selectedValue.replace(/'/g, "&#039;") + "' selected='selected'>" + selectedValue + "</option>";
	            }
	            str += "</select>";
			}
			else
			{
			    selectedValue = selectedValue ? selectedValue.replace(/'/g, "&#039;") : "";
			    //create a text field for fields that don't have choices (i.e text, textarea, number, email, etc...)
			    str = "<input type='text' placeholder='<?php _e("Enter value", "gravityforms"); ?>' class='gfield_routing_select' id='routing_value_" + index + "' value='" + selectedValue.replace(/'/g, "&#039;") + "' onchange='SetRouting(" + index + ");' onkeyup='SetRouting(" + index + ");'>";
			}
            return str;
        }

        function GetFieldById(fieldId){
            for(var i=0; i<form.fields.length; i++){
                if(form.fields[i].id == fieldId)
                    return form.fields[i];
            }
            return null;
        }
        //---------------------------------------------------------------------------------

        function InsertRouting(index){
            var routings = current_notification.routing;
            routings.splice(index, 0, new ConditionalRule());

            CreateRouting(routings);
            SetRouting(index);
        }

        function SetRouting(ruleIndex){
            if(!current_notification.routing && ruleIndex == 0)
                current_notification.routing = [new ConditionalRule()];

            current_notification.routing[ruleIndex]["email"] = jQuery("#routing_email_" + ruleIndex).val();
            current_notification.routing[ruleIndex]["fieldId"] = jQuery("#routing_field_id_" + ruleIndex).val();
            current_notification.routing[ruleIndex]["operator"] = jQuery("#routing_operator_" + ruleIndex).val();
            current_notification.routing[ruleIndex]["value"] =jQuery("#routing_value_" + ruleIndex).val();

            var json = jQuery.toJSON(current_notification.routing);
            jQuery('#gform_routing_meta').val(json);
        }

        function DeleteRouting(ruleIndex){
            current_notification.routing.splice(ruleIndex, 1);
            CreateRouting(current_notification.routing);
        }

        function SetConditionalLogic(isChecked){
            current_notification.conditionalLogic = isChecked ? new ConditionalLogic() : null;
        }

        function SaveJSMeta(){
            jQuery('#gform_routing_meta').val(jQuery.toJSON(current_notification.routing));
            jQuery('#gform_conditional_logic_meta').val(jQuery.toJSON(current_notification.conditionalLogic));
        }

        </script>

        <form method="post" id="gform_notification_form" onsubmit="gform_has_unsaved_changes = false; SaveJSMeta();">

            <?php wp_nonce_field('gforms_save_notification', 'gforms_save_notification') ?>
            <input type="hidden" id="gform_routing_meta" name="gform_routing_meta" />
            <input type="hidden" id="gform_conditional_logic_meta" name="gform_conditional_logic_meta" />
            <input type="hidden" id="gform_notification_id" name="gform_notification_id" value="<?php echo $notification_id ?>" />

            <table class="form-table gform_nofification_edit">
                <?php array_map(array('GFFormSettings', 'output'), $notification_ui_settings); ?>
            </table>

            <p class="submit">
                <?php
                    $button_label = $is_new_notification ? __("Save Notification", "gravityforms") : __("Update Notification", "gravityforms");
                    $notification_button = '<input class="button-primary" type="submit" value="' . $button_label . '" name="save"/>';
                    echo apply_filters("gform_save_notification_button", $notification_button);
                ?>
            </p>
        </form>

        <?php

        GFFormSettings::page_footer();

    }

    public static function notification_list_page($form_id) {

        // handle form actions
        self::maybe_process_notification_list_action();

        $form = RGFormsModel::get_form_meta($form_id);

        GFFormSettings::page_header(__('Notifications', 'gravityforms'));
        $add_new_url = add_query_arg(array("nid" => 0));
        ?>

        <h3><span><i class="fa fa-envelope-o"></i> <?php _e("Notifications", "gravityforms") ?><a id="add-new-confirmation" class="add-new-h2" href="<?php echo $add_new_url?>"><?php _e("Add New", "gravityforms") ?></a></span></h3>

        <script type="text/javascript">
        function ToggleActive(img, notification_id){
            var is_active = img.src.indexOf("active1.png") >=0
            if(is_active){
            img.src = img.src.replace("active1.png", "active0.png");
            jQuery(img).attr('title','<?php _e("Inactive", "gravityforms") ?>').attr('alt', '<?php _e("Inactive", "gravityforms") ?>');
            }
            else{
            img.src = img.src.replace("active0.png", "active1.png");
            jQuery(img).attr('title','<?php _e("Active", "gravityforms") ?>').attr('alt', '<?php _e("Active", "gravityforms") ?>');
            }

            var mysack = new sack("<?php echo admin_url("admin-ajax.php")?>" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( "action", "rg_update_notification_active" );
            mysack.setVar( "rg_update_notification_active", "<?php echo wp_create_nonce("rg_update_notification_active") ?>" );
            mysack.setVar( "form_id", <?php echo intval($form_id) ?>);
            mysack.setVar( "notification_id", notification_id);
            mysack.setVar( "is_active", is_active ? 0 : 1);
            mysack.onError = function() { alert('<?php echo esc_js(__("Ajax error while updating notification", "gravityforms")) ?>' )};
            mysack.runAJAX();

            return true;
        }
        </script>
    <?php
        $notification_table = new GFNotificationTable($form);
        $notification_table->prepare_items();
        ?>

    <form id="notification_list_form" method="post">

        <?php $notification_table->display(); ?>

        <input id="action_argument" name="action_argument" type="hidden" />
        <input id="action" name="action" type="hidden" />

        <?php wp_nonce_field('gform_notification_list_action', 'gform_notification_list_action') ?>

    </form>

    <?php
        GFFormSettings::page_footer();
    }

    public static function maybe_process_notification_list_action() {

        if( empty($_POST) || !check_admin_referer('gform_notification_list_action', 'gform_notification_list_action') )
            return;

        $action = rgpost('action');
        $object_id = rgpost('action_argument');

        switch($action) {
            case 'delete':
                $notification_deleted = GFNotification::delete_notification($object_id, rgget('id'));
                if($notification_deleted) {
                    GFCommon::add_message( __('Notification deleted.', 'gravityforms') );
                } else {
                    GFCommon::add_error_message( __('There was an issue deleting this notification.', 'gravityforms') );
                }
                break;
            case 'duplicate':
                $notification_duplicated = GFNotification::duplicate_notification($object_id, rgget('id'));
                if($notification_duplicated) {
                    GFCommon::add_message( __('Notification duplicates.', 'gravityforms') );
                } else {
                    GFCommon::add_error_message( __('There was an issue duplicating this notification.', 'gravityforms') );
                }
                break;
        }

    }

    private static function get_notification_ui_settings($notification) {

        /**
        * These variables are used to convenient "wrap" child form settings in the appropriate HTML.
        */
        $subsetting_open = '
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
        $form_id = rgget('id');
        $form = RGFormsModel::get_form_meta($form_id);
        $form = apply_filters("gform_admin_pre_render_" . $form_id, apply_filters("gform_admin_pre_render", $form));
        $is_valid = empty(GFCommon::$errors);

        ob_start(); ?>

        <tr valign="top">
            <th scope="row">
                <label for="gform_notification_name">
                    <?php _e("Name", "gravityforms"); ?>
                    <?php gform_tooltip("notification_name") ?>
                </label>
            </th>
            <td>
                <input type="text" class="fieldwidth-2" name="gform_notification_name" id="gform_notification_name" value="<?php echo esc_attr(rgget("name", $notification)) ?>"/>
            </td>
        </tr> <!-- / name -->
        <?php $ui_settings['notification_name'] = ob_get_contents(); ob_clean(); ?>

        <?php
        $notification_events = apply_filters("gform_notification_events", array("form_submission" => __("Form is submitted", "gravityforms")));
        $event_style = count($notification_events) == 1 ? "style='display:none'" : "";
        ?>
        <tr valign="top" <?php echo $event_style ?>>
            <th scope="row">
                <label for="gform_notification_event">
                    <?php _e("Event", "gravityforms"); ?>
                    <?php gform_tooltip("notification_event") ?>
                </label>

            </th>
            <td>
                <select name="gform_notification_event" id="gform_notification_event">
                <?php
                foreach($notification_events as $code => $label){
                    ?>
                    <option value="<?php echo esc_attr($code) ?>" <?php selected( rgar( $notification, 'event' ), $code)?>><?php echo esc_html($label) ?></option>
                    <?php
                }
                ?>
                </select>
            </td>
        </tr> <!-- / event -->
        <?php $ui_settings['notification_event'] = ob_get_contents(); ob_clean(); ?>

        <?php
        $notification_to_type = !rgempty("gform_notification_to_type") ? rgpost("gform_notification_to_type") : rgar($notification,"toType");
        if(empty($notification_to_type))
            $notification_to_type = "email";

        $is_invalid_email_to = !$is_valid && !self::is_valid_notification_to();
        $send_to_class = $is_invalid_email_to ? "gfield_error" : "";
        ?>
        <tr valign="top" class='<?php echo $send_to_class ?>'>
            <th scope="row">
                <label for="gform_notification_to_email">
                    <?php _e("Send To", "gravityforms"); ?><span class="gfield_required">*</span>
                    <?php gform_tooltip("notification_send_to_email") ?>
                </label>

            </th>
            <td>
                <input type="radio" id="gform_notification_to_type_email" name="gform_notification_to_type" <?php checked("email", $notification_to_type); ?> value="email" onclick="jQuery('.notification_to_container').hide(); jQuery('#gform_notification_to_email_container').show('slow');"/>
                <label for="gform_notification_to_type_email" class="inline">
                    <?php _e("Enter Email", "gravityforms"); ?>
                </label>
                &nbsp;&nbsp;
                <input type="radio" id="gform_notification_to_type_field" name="gform_notification_to_type" <?php checked("field", $notification_to_type); ?> value="field" onclick="jQuery('.notification_to_container').hide(); jQuery('#gform_notification_to_field_container').show('slow');"/>
                <label for="gform_notification_to_type_field" class="inline">
                    <?php _e("Select a Field", "gravityforms"); ?>
                </label>
                &nbsp;&nbsp;
                <input type="radio" id="gform_notification_to_type_routing" name="gform_notification_to_type" <?php checked("routing", $notification_to_type); ?> value="routing" onclick="jQuery('.notification_to_container').hide(); jQuery('#gform_notification_to_routing_container').show('slow');"/>
                <label for="gform_notification_to_type_routing" class="inline">
                    <?php _e("Configure Routing", "gravityforms"); ?>
                    <?php gform_tooltip("notification_send_to_routing") ?>
                </label>
            </td>
        </tr> <!-- / to email type -->
        <?php $ui_settings['notification_to_email_type'] = ob_get_contents(); ob_clean(); ?>

        <tr id="gform_notification_to_email_container" class="notification_to_container <?php echo $send_to_class ?>" <?php echo $notification_to_type != "email" ? "style='display:none';" : ""?>>
            <?php echo $subsetting_open; ?>
            <th scope="row"><?php _e("Send to Email", "gravityforms") ?></th>
            <td>
                <?php
                $to_email = rgget("toType", $notification) == "email" ? rgget("to", $notification) : "";
                ?>
                <input type="text" name="gform_notification_to_email" id="gform_notification_to_email" value="<?php echo esc_attr($to_email) ?>" class="fieldwidth-1" />

                <?php if(rgpost("gform_notification_to_type") == "email" && $is_invalid_email_to){ ?>
                    <span class="validation_message"><?php _e("Please enter a valid email address", "gravityforms") ?></span>
                <?php } ?>
            </td>
            <?php echo $subsetting_close; ?>
        </tr> <!-- / to email -->
        <?php $ui_settings['notification_to_email'] = ob_get_contents(); ob_clean(); ?>

        <?php $email_fields = apply_filters("gform_email_fields_notification_admin_{$form["id"]}", apply_filters("gform_email_fields_notification_admin", GFCommon::get_email_fields($form), $form), $form); ?>
        <tr id="gform_notification_to_field_container" class="notification_to_container <?php echo $send_to_class ?>" <?php echo $notification_to_type != "field" ? "style='display:none';" : ""?>>
            <?php echo $subsetting_open; ?>
            <th scope="row"><?php _e("Send to Field", "gravityforms") ?></th>
            <td>
                <?php
                if(!empty($email_fields)){
                ?>
                    <select name="gform_notification_to_field" id="gform_notification_to_field">
                        <option value=""><?php _e("Select an email field", "gravityforms"); ?></option>
                        <?php
                        $to_field = rgget("toType", $notification) == "field" ? rgget("to", $notification) : "";
                        foreach($email_fields as $field){
                            ?>
                            <option value="<?php echo $field["id"]?>" <?php echo selected($field["id"], $to_field) ?>><?php echo GFCommon::get_label($field)?></option>
                            <?php
                        }
                        ?>
                    </select>
                <?php
                }
                else{ ?>
                    <div class="error_base"><p><?php _e("Your form does not have an email field. Add an email field to your form and try again.", "gravityforms") ?></p></div>
                <?php
                }
                ?>
            </td>
            <?php echo $subsetting_close; ?>
        </tr> <!-- / to email field -->
        <?php $ui_settings['notification_to_email_field'] = ob_get_contents(); ob_clean(); ?>

        <tr id="gform_notification_to_routing_container" class="notification_to_container <?php echo $send_to_class ?>" <?php echo $notification_to_type != "routing" ? "style='display:none';" : ""?>>
            <?php echo $subsetting_open; ?>
            <td colspan="2">
                <div id="gform_notification_to_routing_rules">
                    <?php
                    $routing_fields = self::get_routing_fields($form,"0");
                    if(empty($routing_fields)){//if(empty(){
                        ?>
                        <div class="gold_notice">
                            <p><?php _e("To use notification routing, your form must have a field supported by conditional logic.", "gravityforms"); ?></p>
                        </div>
                        <?php
                    }
                    else {
                        if(empty($notification["routing"]))
                            $notification["routing"] = array(array());

                        $count = sizeof($notification["routing"]);
                        $routing_list = ",";
                        for($i=0; $i<$count; $i++){
                            $routing_list .= $i . ",";
                            $routing = $notification["routing"][$i];

                            $is_invalid_rule = !$is_valid && $_POST["gform_notification_to_type"] == "routing" && !self::is_valid_notification_email( rgar( $routing, 'email' ) );
                            $class = $is_invalid_rule ? "class='grouting_rule_error'" : "";
                            ?>
                            <div style='width:99%' <?php echo $class ?>>
                                <?php _e("Send to", "gravityforms") ?> <input type="text" id="routing_email_<?php echo $i?>" value="<?php echo rgar($routing,"email"); ?>" onkeyup="SetRouting(<?php echo $i ?>);"/>
                                <?php _e("if", "gravityforms") ?> <select id="routing_field_id_<?php echo $i?>" class='gfield_routing_select' onchange='jQuery("#routing_value_<?php echo $i ?>").replaceWith(GetRoutingValues(<?php echo $i ?>, jQuery(this).val())); SetRouting(<?php echo $i ?>); '><?php echo self::get_routing_fields($form, rgar($routing,"fieldId")) ?></select>
                                <select id="routing_operator_<?php echo $i?>" onchange="SetRouting(<?php echo $i ?>)" class="gform_routing_operator">
                                    <option value="is" <?php echo rgar($routing,"operator") == "is" ? "selected='selected'" : "" ?>><?php _e("is", "gravityforms") ?></option>
                                    <option value="isnot" <?php echo rgar($routing,"operator") == "isnot" ? "selected='selected'" : "" ?>><?php _e("is not", "gravityforms") ?></option>
                                    <option value=">" <?php echo rgar($routing,"operator") == ">" ? "selected='selected'" : "" ?>><?php _e("greater than", "gravityforms") ?></option>
                                    <option value="<" <?php echo rgar($routing,"operator") == "<" ? "selected='selected'" : "" ?>><?php _e("less than", "gravityforms") ?></option>
                                    <option value="contains" <?php echo rgar($routing,"operator") == "contains" ? "selected='selected'" : "" ?>><?php _e("contains", "gravityforms") ?></option>
                                    <option value="starts_with" <?php echo rgar($routing,"operator") == "starts_with" ? "selected='selected'" : "" ?>><?php _e("starts with", "gravityforms") ?></option>
                                    <option value="ends_with" <?php echo rgar($routing,"operator") == "ends_with" ? "selected='selected'" : "" ?>><?php _e("ends with", "gravityforms") ?></option>
                                </select>
                                <?php echo self::get_field_values($i, $form, rgar($routing,"fieldId"), rgar($routing,"value")) ?>

                                <a class='gf_insert_field_choice' title='add another rule' onclick='SetRouting(<?php echo $i ?>); InsertRouting(<?php echo $i + 1 ?>);'><i class='fa fa-plus-square'></i></a>

                                <?php if($count > 1 ){ ?>
                                    <img src='<?php echo GFCommon::get_base_url()?>/images/remove.png' id='routing_delete_<?php echo $i?>' title='remove this email routing' alt='remove this email routing' class='delete_field_choice' style='cursor:pointer;' onclick='DeleteRouting(<?php echo $i ?>);' />
                                <?php } ?>
                            </div>
                        <?php
                        }

                        if($is_invalid_rule){ ?>
                            <span class="validation_message"><?php _e("Please enter a valid email address for all highlighted routing rules above.", "gravityforms") ?></span>
                        <?php } ?>
                        <input type="hidden" name="routing_count" id="routing_count" value="<?php echo $routing_list ?>"/>
                    <?php
                    }
                    ?>
                </div>
            </td>
            <?php echo $subsetting_close; ?>
        </tr> <!-- / to routing -->
        <?php $ui_settings['notification_to_routing'] = ob_get_contents(); ob_clean(); ?>

        <tr valign="top">
            <th scope="row">
                <label for="gform_notification_from_name">
                    <?php _e("From Name", "gravityforms"); ?>
                    <?php gform_tooltip("notification_from_name") ?>
                </label>
            </th>
            <td>
                <input type="text" class="fieldwidth-2 merge-tag-support mt-position-right mt-hide_all_fields" name="gform_notification_from_name" id="gform_notification_from_name" value="<?php echo esc_attr(rgget("fromName", $notification)) ?>"/>
            </td>
        </tr> <!-- / from name -->
        <?php $ui_settings['notification_from_name'] = ob_get_contents(); ob_clean(); ?>

        <tr valign="top">
            <th scope="row">
                <label for="gform_notification_from">
                    <?php _e("From Email", "gravityforms"); ?>
                    <?php gform_tooltip("notification_from_email") ?>
                </label>
            </th>
            <td>
                <input type="text" class="fieldwidth-2 merge-tag-support mt-position-right mt-hide_all_fields" name="gform_notification_from" id="gform_notification_from" value="<?php echo rgempty("from", $notification) ? "{admin_email}" : esc_attr(rgget("from", $notification)) ?>"/>
            </td>
        </tr> <!-- / to from email -->
        <?php $ui_settings['notification_from'] = ob_get_contents(); ob_clean(); ?>

        <tr valign="top">
            <th scope="row">
                <label for="gform_notification_reply_to">
                    <?php _e("Reply To", "gravityforms"); ?>
                    <?php gform_tooltip("notification_reply_to") ?>
                </label>
            </th>
            <td>
                <input type="text" name="gform_notification_reply_to" id="gform_notification_reply_to" class="merge-tag-support mt-hide_all_fields" value="<?php echo esc_attr(rgget("replyTo", $notification)) ?>" class="fieldwidth-2" />
            </td>
        </tr> <!-- / reply to -->
        <?php $ui_settings['notification_reply_to'] = ob_get_contents(); ob_clean(); ?>

        <tr valign="top">
            <th scope="row">
                <label for="gform_notification_bcc">
                    <?php _e("BCC", "gravityforms"); ?>
                    <?php gform_tooltip("notification_bcc") ?>
                </label>
            </th>
            <td>
                <input type="text" name="gform_notification_bcc" id="gform_notification_bcc" value="<?php echo esc_attr(rgget("bcc", $notification)) ?>" class="fieldwidth-1" />
            </td>
        </tr> <!-- / bcc -->
        <?php $ui_settings['notification_bcc'] = ob_get_contents(); ob_clean(); ?>

        <?php
        $is_invalid_subject = !$is_valid && empty($_POST["gform_notification_subject"]);
        $subject_class = $is_invalid_subject ? "class='gfield_error'" : "";
        ?>
        <tr valign="top" <?php echo $subject_class ?>>
            <th scope="row">
                <label for="gform_notification_subject">
                    <?php _e("Subject", "gravityforms"); ?><span class="gfield_required">*</span>
                </label>
            </th>
            <td>
                <input type="text" name="gform_notification_subject" id="gform_notification_subject" class="fieldwidth-1 merge-tag-support mt-hide_all_fields mt-position-right" value="<?php echo esc_attr(rgar($notification,"subject")) ?>" />
                <?php
                if($is_invalid_subject){?>
                    <span class="validation_message"><?php _e("Please enter a subject for the notification email", "gravityforms") ?></span><?php
                }
                ?>
            </td>
        </tr> <!-- / subject -->
        <?php $ui_settings['notification_subject'] = ob_get_contents(); ob_clean(); ?>

        <?php
        $is_invalid_message = !$is_valid && empty($_POST["gform_notification_message"]);
        $message_class = $is_invalid_message ? "class='gfield_error'" : "";
        ?>
        <tr valign="top" <?php echo $message_class ?>>
            <th scope="row">
                <label for="gform_notification_message">
                    <?php _e("Message", "gravityforms"); ?><span class="gfield_required">*</span>
                </label>
            </th>
            <td>

                <span class="mt-gform_notification_message"></span>

                <?php
                if(GFCommon::is_wp_version("3.3")){
                    wp_editor( rgar( $notification, "message" ), "gform_notification_message", array( "autop" => false, "editor_class" => "merge-tag-support mt-wp_editor mt-manual_position mt-position-right" ) );
                }
                else{?>
                    <textarea name="gform_notification_message" id="gform_notification_message" class="fieldwidth-1 fieldheight-1" ><?php echo esc_html($notification["message"]) ?></textarea><?php
                }

                if($is_invalid_message){ ?>
                    <span class="validation_message"><?php _e("Please enter a message for the notification email", "gravityforms") ?></span><?php
                }
                ?>
            </td>
        </tr> <!-- / message -->
        <?php $ui_settings['notification_message'] = ob_get_contents(); ob_clean(); ?>

        <tr valign="top">
            <th scope="row">
                <label for="gform_notification_disable_autoformat">
                    <?php _e("Auto-formatting", "gravityforms"); ?>
                    <?php gform_tooltip("notification_autoformat") ?>
                </label>
            </th>
            <td>
                <input type="checkbox" name="gform_notification_disable_autoformat" id="gform_notification_disable_autoformat" value="1" <?php echo empty($notification["disableAutoformat"]) ? "" : "checked='checked'" ?>/>
                <label for="form_notification_disable_autoformat" class="inline">
                    <?php _e("Disable auto-formatting", "gravityforms"); ?>
                    <?php gform_tooltip("notification_autoformat") ?>
                </label>
            </td>
        </tr> <!-- / disable autoformat -->
        <?php $ui_settings['notification_disable_autoformat'] = ob_get_contents(); ob_clean(); ?>

        <tr valign="top">
            <th scope="row">
                <label for="gform_notification_conditional_logic">
                    <?php _e("Conditional Logic", "gravityforms") ?><?php gform_tooltip("notification_conditional_logic") ?>
                </label>
            </th>
            <td>
                <input type="checkbox" id="notification_conditional_logic" onclick="SetConditionalLogic(this.checked); ToggleConditionalLogic(false, 'notification');" <?php checked(is_array(rgar($notification,"conditionalLogic")), true) ?> />
                <label for="notification_conditional_logic" class="inline"><?php _e("Enable conditional logic", "gravityforms") ?><?php gform_tooltip("notification_conditional_logic") ?></label>
                <br/>
            </td>
        </tr> <!-- / conditional logic -->
        <tr>
            <td colspan="2">
                <div id="notification_conditional_logic_container" class="gf_animate_sub_settings" style="padding-left:10px;">
                    <!-- content dynamically created from form_admin.js -->
                </div>
            </td>
        </tr>

        <?php $ui_settings['notification_conditional_logic'] = ob_get_contents(); ob_clean(); ?>

        <?php
        ob_end_clean();
        $ui_settings = apply_filters("gform_notification_ui_settings_{$form_id}", apply_filters('gform_notification_ui_settings', $ui_settings, $notification, $form), $notification, $form );
        return $ui_settings;
    }

    private static function validate_notification() {
        $is_valid = self::is_valid_notification_to() && !rgempty("gform_notification_subject") && !rgempty("gform_notification_message");
        return $is_valid;
    }

    private static function is_valid_routing(){
        $routing = !empty($_POST["gform_routing_meta"]) ? GFCommon::json_decode(stripslashes($_POST["gform_routing_meta"]), true) : null;
        if(empty($routing))
            return false;

        foreach($routing as $route){
            if(!self::is_valid_notification_email($route["email"]))
                return false;
        }

        return true;
    }

    private static function is_valid_notification_email($text){
        if(empty($text))
            return false;

        $emails = explode(",", $text);
        foreach($emails as $email){
            $email = trim($email);
            $invalid_email = GFCommon::is_invalid_or_empty_email( $email );
            $invalid_variable = !preg_match('/^({[^{]*?:(\d+(\.\d+)?)(:(.*?))?},? *)+$/', $email);

            if($invalid_email && $invalid_variable)
                return false;
        }

        return true;
    }

    private static function is_valid_notification_to(){
        $is_valid =  (rgpost('gform_notification_to_type') == "routing" && self::is_valid_routing())
                            ||
                            (rgpost('gform_notification_to_type') == "email" && (self::is_valid_notification_email($_POST["gform_notification_to_email"])) || $_POST["gform_notification_to_email"] == "{admin_email}")
                            ||
                            (rgpost('gform_notification_to_type') == "field" && (!rgempty("gform_notification_to_field")));

        return $is_valid = apply_filters("gform_is_valid_notification_to", $is_valid, rgpost('gform_notification_to_type'), rgpost("gform_notification_to_email"), rgpost("gform_notification_to_field"));
    }

    private static function get_first_routing_field($form){
        foreach($form["fields"] as $field){
            $input_type = RGFormsModel::get_input_type($field);
            if(in_array($input_type, self::$supported_fields))
                return $field["id"];
        }

        return 0;
    }

    private static function get_routing_fields($form, $selected_field_id){
        $str = "";
        foreach($form["fields"] as $field){
            $input_type = RGFormsModel::get_input_type($field);
            $field_label = RGFormsModel::get_label($field);
            if (in_array($input_type, self::$supported_fields)){
                $selected = $field["id"] == $selected_field_id ? "selected='selected'" : "";
                $str .= "<option value='" . $field["id"] . "' " . $selected . ">" . $field_label . "</option>";
            }
        }
        return $str;
    }

    private static function get_field_values($i, $form, $field_id, $selected_value, $max_field_length = 16){
         if(empty($field_id))
                $field_id = self::get_first_routing_field($form);

            if(empty($field_id))
                return "";

            $field = RGFormsModel::get_field($form, $field_id);
            $is_any_selected = false;
            $str = "";

            if (!$field)
            	return "";

			if ($field["type"] == "post_category" && rgar($field, "displayAllCategories") == true)
			{
				$str .= wp_dropdown_categories(array("class"=>"gfield_routing_select gfield_category_dropdown gfield_routing_value_dropdown", "orderby"=> "name", "id"=> "routing_value_" . $i, "selected"=>$selected_value, "hierarchical"=>true, "hide_empty"=>0, "echo"=>false));
			}
            elseif (rgar($field,"choices")) {
            	$str .= "<select id='routing_value_" . $i . "' class='gfield_routing_select gfield_routing_value_dropdown'>";
	            foreach($field["choices"] as $choice){
	                $is_selected = $choice["value"] == $selected_value;
	                $selected = $is_selected ? "selected='selected'" : "";
	                if($is_selected)
	                    $is_any_selected = true;

	                $str .= "<option value='" . esc_attr($choice["value"]) . "' " . $selected . ">" . $choice["text"] . "</option>";
	            }

	            //adding current selected field value to the list
	            if(!$is_any_selected && !empty($selected_value))
	            {
	                $str .= "<option value='" . esc_attr($selected_value) . "' selected='selected'>" . $selected_value . "</option>";
				}
	            $str .= "</select>";
			}
			else
			{
			    //create a text field for fields that don't have choices (i.e text, textarea, number, email, etc...)
			    $str = "<input type='text' placeholder='" . __("Enter value", "gravityforms") . "' class='gfield_routing_select' id='routing_value_" . $i . "' value='" . esc_attr($selected_value) . "' onchange='SetRouting(" . $i . ");' onkeyup='SetRouting(" . $i . ");'>";
			}

            return $str;
    }

    public static function get_post_category_values(){

        $id = "routing_value_" . rgpost("ruleIndex");
        $selected = rgempty("selectedValue") ? 0 : rgpost("selectedValue");

        $dropdown = wp_dropdown_categories(array("class"=>"gfield_routing_select gfield_routing_value_dropdown gfield_category_dropdown", "orderby"=> "name", "id"=> $id, "selected"=>$selected, "hierarchical"=>true, "hide_empty"=>0, "echo"=>false));
        die($dropdown);
    }

    /**
    * Delete a form notification by ID.
    *
    * @param mixed $notification_id
    * @param mixed $form_id Can pass a form ID or a form object
    */
    public static function delete_notification($notification_id, $form_id) {

        if(!$form_id)
            return false;

        $form = !is_array($form_id) ? RGFormsModel::get_form_meta($form_id) : $form_id;
        unset($form['notifications'][$notification_id]);

        // clear Form cache so next retrieval of form meta will reflect deleted notification
        RGFormsModel::flush_current_forms();

        return RGFormsModel::save_form_notifications($form['id'], $form['notifications']);
    }

    public static function duplicate_notification($notification_id, $form_id) {

        if(!$form_id)
            return false;

        $form = !is_array($form_id) ? RGFormsModel::get_form_meta($form_id) : $form_id;

        $new_notification = $form['notifications'][$notification_id];
        $name = rgar($new_notification, "name");
        $new_id = uniqid();

        $count = 2;
        $new_name =  $name . " - Copy 1";
        while(!self::is_unique_name($new_name,  $form['notifications'])){
            $new_name = $name . " - Copy $count";
            $count++;
        }
        $new_notification["name"] = $new_name;
        $new_notification["id"] = $new_id;
        $form['notifications'][$new_id] = $new_notification;

        // clear Form cache so next retrieval of form meta will return duplicated notification
        RGFormsModel::flush_current_forms();

        return RGFormsModel::save_form_notifications($form['id'], $form['notifications']);
    }

    public static function is_unique_name($name, $notifications){

        foreach ($notifications as $notification){
            if(strtolower(rgar($notification, "name")) == strtolower($name))
                return false;
        }

        return true;
    }

}



class GFNotificationTable extends WP_List_Table {

    public $form;

    function __construct($form) {

        $this->form = $form;

        $this->_column_headers = array(
            array(
                'cb' => '',
                'name' => __('Name', 'gravityforms'),
                'subject' => __('Subject', 'gravityforms')
                ),
                array(),
                array()
            );

        parent::__construct();
    }

    function prepare_items() {
        $this->items = $this->form['notifications'];
    }

    function display() {

        // ...causing issue: Notice: Indirect modification of overloaded property GFNotificationTable::$_args has no effect
        //extract( $this->_args ); // gives us $plural, $singular, $ajax, $screen

        $singular = $this->_args['singular'];

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

            <tbody id="the-list"<?php if ( $singular ) echo " class='list:$singular'"; ?>>

                <?php $this->display_rows_or_placeholder(); ?>

            </tbody>
        </table>

        <?php
    }

    function single_row( $item ) {
        static $row_class = '';
        $row_class = ( $row_class == '' ? ' class="alternate"' : '' );

        echo '<tr id="notification-' . $item['id'] . '" ' . $row_class . '>';
        echo $this->single_row_columns( $item );
        echo '</tr>';
    }

    function column_default($item, $column) {
        echo rgar($item, $column);
    }

    function column_cb($item) {
        $is_active = isset($item["isActive"]) ? $item["isActive"] : true;
        ?>
        <img src="<?php echo GFCommon::get_base_url() ?>/images/active<?php echo intval($is_active) ?>.png" style="cursor: pointer;margin:-5px 0 0 8px;" alt="<?php $is_active ? __("Active", "gravityforms") : __("Inactive", "gravityforms");?>" title="<?php echo $is_active ? __("Active", "gravityforms") : __("Inactive", "gravityforms");?>" onclick="ToggleActive(this, '<?php echo $item["id"] ?>'); " />
        <?php
    }

    function column_name($item) {
        $edit_url = add_query_arg(array("nid" => $item["id"]));
        $actions = apply_filters('gform_notification_actions', array(
            'edit' => '<a title="' . __('Edit this item', 'gravityforms') . '" href="' . $edit_url . '">' . __('Edit', 'gravityforms') . '</a>',
            'duplicate' => '<a title="' . __('Duplicate this notification', 'gravityforms') . '" onclick="javascript: DuplicateNotification(\'' . $item["id"] . '\');" style="cursor:pointer;">' . __('Duplicate', 'gravityforms') . '</a>',
            'delete' => '<a title="' . __('Delete this notification', 'gravityforms') . '" class="submitdelete" onclick="javascript: if(confirm(\'' . __("WARNING: You are about to delete this notification.", "gravityforms") . __("\'Cancel\' to stop, \'OK\' to delete.", "gravityforms") . '\')){ DeleteNotification(\'' . $item["id"] . '\'); }" style="cursor:pointer;">' . __('Delete', 'gravityforms') . '</a>'
            ));

        if(isset($item['isDefault']) && $item['isDefault'])
            unset($actions['delete']);

        ?>

        <a href="<?php echo $edit_url; ?>"><strong><?php echo rgar($item, 'name'); ?></strong></a>
        <div class="row-actions">

            <?php
            if(is_array($actions) && !empty($actions)) {
                $keys = array_keys($actions);
                $last_key = array_pop($keys);
                foreach($actions as $key => $html) {
                    $divider = $key == $last_key ? '' : " | ";
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

    function no_items(){

        printf(__("This form doesn't have any notifications. Let's go %screate one%s.", "gravityforms"), "<a href='" . add_query_arg(array("nid" => 0)) . "'>", "</a>");
    }
}

?>