<?php

Class GFNotification{
	private static $supported_fields = array("checkbox", "radio", "select", "text", "website", "textarea", "email", "hidden", "number", "phone", "multiselect", "post_title",
		                            "post_tags", "post_custom_field", "post_content", "post_excerpt");

    function media_buttons($editor_id = 'content') {
        $onchange = GFCommon::is_wp_version("3.3") ? "InsertEditorVariable('{$editor_id}');" : "";
		echo "&nbsp;&nbsp;";
        $form_id = rgget('id');
        $form = RGFormsModel::get_form_meta($form_id);
        GFCommon::insert_variables(rgar($form,"fields"), $editor_id, false, "", $onchange, 26, null, "", "gform_editor_merge_tags");
    }

    public static function notification_page($form_id){
        add_action( 'media_buttons', array('GFNotification', 'media_buttons'), 40);

        $form = RGFormsModel::get_form_meta($form_id);

        $invalid_tab = "";

        if(rgpost("save")){

            check_admin_referer('gforms_save_notification', 'gforms_save_notification');

            $form["notification"]["to"] = rgpost("form_notification_to");
            $form["notification"]["bcc"] = rgpost("form_notification_bcc");
            $form["notification"]["subject"] = rgpost("form_notification_subject");
            $form["notification"]["message"] = rgpost("form_notification_message");
            $form["notification"]["from"] = rgempty("form_notification_from_field") ? rgpost("form_notification_from") : "";
            $form["notification"]["fromField"] = rgpost("form_notification_from_field");
            $form["notification"]["fromName"] = rgempty("form_notification_from_name_field") ? rgpost("form_notification_from_name") : "";
            $form["notification"]["fromNameField"] = rgpost("form_notification_from_name_field");
            $form["notification"]["replyTo"] = rgempty("form_notification_reply_to_field") ? rgpost("form_notification_reply_to") : "";
            $form["notification"]["replyToField"] = rgpost("form_notification_reply_to_field");
            $form["notification"]["routing"] = !rgempty("gform_routing_meta") ? GFCommon::json_decode(rgpost("gform_routing_meta"), true) : null;
            $form["notification"]["disableAutoformat"] = rgpost("form_notification_disable_autoformat");

            $form["autoResponder"]["toField"] = rgpost("form_autoresponder_to");
            $form["autoResponder"]["bcc"] = rgpost("form_autoresponder_bcc");
            $form["autoResponder"]["fromName"] = rgpost("form_autoresponder_from_name");
            $form["autoResponder"]["from"] = rgpost("form_autoresponder_from");
            $form["autoResponder"]["replyTo"] = rgpost("form_autoresponder_reply_to");
            $form["autoResponder"]["subject"] = rgpost("form_autoresponder_subject");
            $form["autoResponder"]["message"] = rgpost("form_autoresponder_message");
            $form["autoResponder"]["disableAutoformat"] = rgpost("form_autoresponder_disable_autoformat");

            //validating input...
            $invalid_tab = self::validate_notification();
            if($invalid_tab == 0){
                //input valid, updating...

                //emptying notification email if it is supposed to be disabled
                if(empty($_POST["form_notification_enable_admin"]) || $_POST["notification_to"] == "routing")
                    $form["notification"]["to"] = "";

                //emptying notification routing if it is supposed to be disabled
                if(empty($_POST["form_notification_enable_admin"]) || $_POST["notification_to"] == "email")
                    $form["notification"]["routing"] = null;

                //emptying autoResponder settings if it is supposed to be disabled
                if(empty($_POST["form_notification_enable_user"]))
                    $form["autoResponder"]["toField"] = "";

                RGFormsModel::update_form_meta($form_id, $form);
            }
        }

        $wp_email = "{admin_email}";
        $email_fields = GFCommon::get_email_fields($form);
        $name_fields = GFCommon::get_fields_by_type($form, array("name"));

        $has_admin_notification_fields = GFCommon::has_admin_notification($form);
        $has_user_notification_fields = GFCommon::has_user_notification($form);

        $is_admin_notification_enabled = ($has_admin_notification_fields && empty($_POST["save"])) || !empty($_POST["form_notification_enable_admin"]);
        $is_user_notification_enabled =  ($has_user_notification_fields && empty($_POST["save"])) || !empty($_POST["form_notification_enable_user"]);

        $is_routing_enabled = !empty($form["notification"]["routing"]) && rgpost("notification_to") != "email";

        ?>
        <link rel="stylesheet" href="<?php echo GFCommon::get_base_url()?>/css/admin.css?ver=<?php echo GFCommon::$version ?>" />
        <script type="text/javascript" src="<?php echo GFCommon::get_base_url()?>/js/forms.js?ver=<?php echo GFCommon::$version ?>"></script>
        <script src="<?php echo GFCommon::get_base_url() ?>/js/jquery.json-1.3.js?ver=<?php echo GFCommon::$version ?>"></script>

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


            if(jQuery(document).on){
                jQuery(document).on('change', '.gfield_routing_value_dropdown', function(){
                        SetRoutingValueDropDown(jQuery(this));
                    });
            }
            else{
                jQuery('.gfield_routing_value_dropdown').live('change', function(){
                    SetRoutingValueDropDown(jQuery(this));
                });
            }
        });


        <?php
        if(empty($form["notification"]))
            $form["notification"] = array();
        ?>

        var form = <?php echo GFCommon::json_encode($form) ?>;

        function SetRoutingValueDropDown(element){
            //parsing ID to get routing Index
            var index = element.attr("id").replace("routing_value_", "");
            SetRouting(index);
        }

        function InsertVariable(element_id, callback, variable){
                if(!variable)
                    variable = jQuery('#' + element_id + '_variable_select').val();

                var messageElement = jQuery("#" + element_id);

                if(document.selection) {
                    // Go the IE way
                    messageElement[0].focus();
                    document.selection.createRange().text=variable;
                }
                else if(messageElement[0].selectionStart) {
                    // Go the Gecko way
                    obj = messageElement[0]
                    obj.value = obj.value.substr(0, obj.selectionStart) + variable + obj.value.substr(obj.selectionEnd, obj.value.length);
                }
                else {
                    messageElement.val(variable + messageElement.val());
                }

                jQuery('#' + element_id + '_variable_select')[0].selectedIndex = 0;


                if(callback && window[callback]){
                    window[callback].call(null, element_id, variable);
                }
        }

        function InsertEditorVariable(elementId){
            var select = jQuery("#" + elementId + "_variable_select");
            var variable = select.val();
            select[0].selectedIndex = 0;

            wpActiveEditor = elementId;
            window.send_to_editor(variable);
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
                str += "<select id='routing_operator_" + i + "' onchange='SetRouting(" + i + ");'>";
                str += "<option value='is' " + isSelected + "><?php _e("is", "gravityforms") ?></option>";
                str += "<option value='isnot' " + isNotSelected + "><?php _e("is not", "gravityforms") ?></option>";
                str += "<option value='>' " + greaterThanSelected + "><?php _e("greater than", "gravityforms") ?></option>";
                str += "<option value='<' " + lessThanSelected + "><?php _e("less than", "gravityforms") ?></option>";
                str += "<option value='contains' " + containsSelected + "><?php _e("contains", "gravityforms") ?></option>";
                str += "<option value='starts_with' " + startsWithSelected + "><?php _e("starts with", "gravityforms") ?></option>";
                str += "<option value='ends_with' " + endsWithSelected + "><?php _e("ends with", "gravityforms") ?></option>";
                str += "</select>";
                str += GetRoutingValues(i, routings[i].fieldId, routings[i].value);
                str += "<img src='<?php echo GFCommon::get_base_url() ?>/images/add.png' class='add_field_choice' title='add another rule' alt='add another rule' style='cursor:pointer; margin:0 3px;' onclick=\"InsertRouting(" + (i+1) + ");\" />";
                if(routings.length > 1 )
                    str += "<img src='<?php echo GFCommon::get_base_url() ?>/images/remove.png' title='remove this rule' alt='remove this rule' class='delete_field_choice' style='cursor:pointer;' onclick=\"DeleteRouting(" + i + ");\" /></li>";

                str += "</div>";
            }

            jQuery("#notification_to_routing_container").html(str);
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
            var routings = form.notification.routing;
            routings.splice(index, 0, new ConditionalRule());

            CreateRouting(routings);
            SetRouting(index);
        }

        function SetRouting(ruleIndex){
            if(!form.notification.routing && ruleIndex == 0)
                form.notification.routing = [new ConditionalRule()];

            form.notification.routing[ruleIndex]["email"] = jQuery("#routing_email_" + ruleIndex).val();
            form.notification.routing[ruleIndex]["fieldId"] = jQuery("#routing_field_id_" + ruleIndex).val();
            form.notification.routing[ruleIndex]["operator"] = jQuery("#routing_operator_" + ruleIndex).val();
            form.notification.routing[ruleIndex]["value"] =jQuery("#routing_value_" + ruleIndex).val();

            var json = jQuery.toJSON(form.notification.routing);
            jQuery('#gform_routing_meta').val(json);
        }

        function DeleteRouting(ruleIndex){
            form.notification.routing.splice(ruleIndex, 1);
            CreateRouting(form.notification.routing);
        }

        </script>
        <?php echo GFCommon::get_remote_message(); ?>

        <form method="post" id="entry_form" onsubmit="gform_has_unsaved_changes = false; jQuery('#gform_routing_meta').val(jQuery.toJSON(form.notification.routing));">
            <?php wp_nonce_field('gforms_save_notification', 'gforms_save_notification') ?>
            <input type="hidden" id="gform_routing_meta" name="gform_routing_meta" />
            <div class="wrap">

                <div class="icon32" id="gravity-notification-icon"><br></div>

                <h2><?php _e("Notifications", "gravityforms"); ?> : <?php echo esc_html($form["title"])?></h2>

                <?php RGForms::top_toolbar() ?>

                <div id="poststuff" class="metabox-holder">
                    <div id="submitdiv" class="stuffbox">
                        <h3><span class="hndle"><?php _e("Notification to Administrator", "gravityforms"); ?></span></h3>
                        <div class="inside">
                            <div id="submitcomment" class="submitbox">

                                <div id="minor-publishingx" style="padding:10px;">
                                    <input type="checkbox" name="form_notification_enable_admin" id="form_notification_enable_admin" value="1" <?php echo $is_admin_notification_enabled ? "checked='checked'" : "" ?> onclick="if(this.checked) {jQuery('#form_notification_admin_container').show('slow');} else {jQuery('#form_notification_to').val(''); jQuery('#form_notification_admin_container').hide('slow');}"/> <label for="form_notification_enable_admin"><?php _e("Enable email notification to administrators", "gravityforms") ?></label>
                                    <div id="form_notification_admin_container" style="display:<?php echo $is_admin_notification_enabled ? "block" : "none"?>;">
                                        <br/>
                                        <?php _e("Enter a message below to receive a notification email when users submit this form.", "gravityforms"); ?><br/><br/><br/>

                                        <ul id="form_notification_container">
                                            <?php
                                                $is_invalid_email_to = $invalid_tab == 1 && !self::is_valid_admin_to();
                                                $class = $is_invalid_email_to ? "class='gfield_error'" : "";
                                            ?>
                                            <li <?php echo $class ?>>
                                                <label for="notification_to_email">
                                                    <?php _e("Send To Email", "gravityforms"); ?><span class="gfield_required">*</span>
                                                    <?php gform_tooltip("notification_send_to_email") ?>
                                                </label>

                                                <input type="radio" id="notification_to_email" name="notification_to" <?php echo !$is_routing_enabled ? "checked='checked'" : ""?> value="email" onclick="jQuery('#notification_to_routing_container').hide(); jQuery('#notification_to_email_container').show('slow');"/>
                                                <label for="notification_to_email" class="inline">
                                                    <?php _e("Email", "gravityforms"); ?>
                                                </label>
                                                &nbsp;&nbsp;
                                                <input type="radio" id="notification_to_routing" name="notification_to" <?php echo $is_routing_enabled ? "checked='checked'" : ""?> value="routing" onclick="jQuery('#notification_to_email_container').hide(); jQuery('#notification_to_routing_container').show('slow');"/>
                                                <label for="form_button_image" class="inline">
                                                    <?php _e("Routing", "gravityforms"); ?>
                                                    <?php gform_tooltip("notification_send_to_routing") ?>
                                                </label>

                                                <div id="notification_to_email_container" style="margin-top:5px; display:<?php echo $is_routing_enabled ? "none" : "block"?>;">
                                                    <input type="text" name="form_notification_to" id="form_notification_to" value="<?php echo esc_attr($form["notification"]["to"]) ?>" class="fieldwidth-1" />

                                                    <?php if(rgpost("notification_to") == "email" && $is_invalid_email_to){ ?>
                                                        <span class="validation_message"><?php _e("Please enter a valid email address") ?></span>
                                                    <?php } ?>
                                                </div>

                                                <div id="notification_to_routing_container" style="margin-top:5px;  display:<?php echo $is_routing_enabled ? "block" : "none"?>;">
                                                    <div>
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
                                                            if(empty($form["notification"]["routing"]))
                                                                $form["notification"]["routing"] = array(array());

                                                            $count = sizeof($form["notification"]["routing"]);
                                                            $routing_list = ",";
                                                            for($i=0; $i<$count; $i++){
                                                                $routing_list .= $i . ",";
                                                                $routing = $form["notification"]["routing"][$i];

                                                                $is_invalid_rule = $invalid_tab == 1 && $_POST["notification_to"] == "routing" && !self::is_valid_notification_email($routing["email"]);
                                                                $class = $is_invalid_rule ? "class='grouting_rule_error'" : "";
                                                                ?>
                                                                <div style='width:99%' <?php echo $class ?>>
                                                                    <?php _e("Send to", "gravityforms") ?> <input type="text" id="routing_email_<?php echo $i?>" value="<?php echo rgar($routing,"email"); ?>" onkeyup="SetRouting(<?php echo $i ?>);"/>
                                                                    <?php _e("if", "gravityforms") ?> <select id="routing_field_id_<?php echo $i?>" class='gfield_routing_select' onchange='jQuery("#routing_value_<?php echo $i ?>").replaceWith(GetRoutingValues(<?php echo $i ?>, jQuery(this).val())); SetRouting(<?php echo $i ?>); '><?php echo self::get_routing_fields($form, rgar($routing,"fieldId")) ?></select>
                                                                    <select id="routing_operator_<?php echo $i?>" onchange="SetRouting(<?php echo $i ?>)" >
                                                                        <option value="is" <?php echo rgar($routing,"operator") == "is" ? "selected='selected'" : "" ?>><?php _e("is", "gravityforms") ?></option>
                                                                        <option value="isnot" <?php echo rgar($routing,"operator") == "isnot" ? "selected='selected'" : "" ?>><?php _e("is not", "gravityforms") ?></option>
                                                                        <option value=">" <?php echo rgar($routing,"operator") == ">" ? "selected='selected'" : "" ?>><?php _e("greater than", "gravityforms") ?></option>
                                                                        <option value="<" <?php echo rgar($routing,"operator") == "<" ? "selected='selected'" : "" ?>><?php _e("less than", "gravityforms") ?></option>
                                                                        <option value="contains" <?php echo rgar($routing,"operator") == "contains" ? "selected='selected'" : "" ?>><?php _e("contains", "gravityforms") ?></option>
                                                                        <option value="starts_with" <?php echo rgar($routing,"operator") == "starts_with" ? "selected='selected'" : "" ?>><?php _e("starts with", "gravityforms") ?></option>
                                                                        <option value="ends_with" <?php echo rgar($routing,"operator") == "ends_with" ? "selected='selected'" : "" ?>><?php _e("ends with", "gravityforms") ?></option>
                                                                    </select>
                                                                    <?php echo self::get_field_values($i, $form, rgar($routing,"fieldId"), rgar($routing,"value")) ?>
                                                                    <img src='<?php echo GFCommon::get_base_url()?>/images/add.png' class='add_field_choice' title='add another email routing' alt='add another email routing' style='cursor:pointer; margin:0 3px;' onclick='SetRouting(<?php echo $i ?>); InsertRouting(<?php echo $i + 1 ?>);' />
                                                                    <?php if($count > 1 ){ ?>
                                                                        <img src='<?php echo GFCommon::get_base_url()?>/images/remove.png' id='routing_delete_<?php echo $i?>' title='remove this email routing' alt='remove this email routing' class='delete_field_choice' style='cursor:pointer;' onclick='DeleteRouting(<?php echo $i ?>);' />
                                                                    <?php } ?>
                                                                </div>
                                                            <?php
															}

                                                            if($is_invalid_rule){ ?>
                                                                <span class="validation_message"><?php _e("Please enter a valid email address for all highlighted routing rules above.") ?></span>
                                                            <?php } ?>
                                                            <input type="hidden" name="routing_count" id="routing_count" value="<?php echo $routing_list ?>"/>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>

                                                </div>
                                            </li>
                                            <li>
                                                <label for="form_notification_from">
                                                    <?php _e("From Name", "gravityforms"); ?>
                                                    <?php gform_tooltip("notification_from_name") ?>
                                                </label>
                                                <input type="text" class="fieldwidth-2" name="form_notification_from_name" id="form_notification_from_name" onkeydown="jQuery('#form_notification_from_name_field').val('');" onchange="jQuery('#form_notification_from_name_field').val('');" value="<?php echo esc_attr(rgget("fromName", $form["notification"])) ?>"/>
                                                <?php
                                                if(!empty($name_fields)){
                                                ?>
                                                    <?php _e("OR", "gravityforms"); ?>
                                                    <select name="form_notification_from_name_field" id="form_notification_from_name_field" onchange="if(jQuery(this).val().length > 0 ) jQuery('#form_notification_from_name').val('');">
                                                        <option value=""><?php _e("Select a name field", "gravityforms"); ?></option>
                                                        <?php
                                                        foreach($name_fields as $field){
                                                            $selected = rgget("fromNameField", $form["notification"]) == $field["id"] ? "selected='selected'" : "";
                                                            ?>
                                                            <option value="<?php echo $field["id"]?>" <?php echo $selected ?>><?php echo GFCommon::get_label($field)?></option>
                                                            <?php
                                                        }
                                                        ?>
                                                    </select>
                                                <?php
                                                }
                                                ?>
                                            </li>
                                            <li>
                                                <label for="form_notification_from">
                                                    <?php _e("From Email", "gravityforms"); ?>
                                                    <?php gform_tooltip("notification_from_email") ?>
                                                </label>
                                                <input type="text" class="fieldwidth-2" name="form_notification_from" id="form_notification_from" onkeydown="jQuery('#form_notification_from_field').val('');" onchange="jQuery('#form_notification_from_field').val('');" value="<?php echo (rgempty("from", $form["notification"]) && rgempty("fromField", $form["notification"])) ? esc_attr($wp_email) : esc_attr(rgget("from", $form["notification"])) ?>"/>
                                                <?php
                                                if(!empty($email_fields)){
                                                ?>
                                                    <?php _e("OR", "gravityforms"); ?>
                                                    <select name="form_notification_from_field" id="form_notification_from_field" onchange="if(jQuery(this).val().length > 0 ) jQuery('#form_notification_from').val('');">
                                                        <option value=""><?php _e("Select an email field", "gravityforms"); ?></option>
                                                        <?php
                                                        foreach($email_fields as $field){
                                                            $selected = rgget("fromField", $form["notification"]) == $field["id"] ? "selected='selected'" : "";
                                                            ?>
                                                            <option value="<?php echo $field["id"]?>" <?php echo $selected ?>><?php echo GFCommon::get_label($field)?></option>
                                                            <?php
                                                        }
                                                        ?>
                                                    </select>
                                                <?php
                                                }
                                                ?>
                                            </li>

                                            <li>
                                                <label for="form_notification_reply_to">
                                                    <?php _e("Reply To", "gravityforms"); ?>
                                                    <?php gform_tooltip("notification_reply_to") ?>
                                                </label>
                                                <input type="text" name="form_notification_reply_to" id="form_notification_reply_to" onkeydown="jQuery('#form_notification_reply_to_field').val('');" onchange="jQuery('#form_notification_reply_to_field').val('');" value="<?php echo esc_attr(rgget("replyTo", $form["notification"])) ?>" class="fieldwidth-2" />
                                                <?php
                                                if(!empty($email_fields)){
                                                ?>
                                                    <?php _e("OR", "gravityforms"); ?>
                                                    <select name="form_notification_reply_to_field" id="form_notification_reply_to_field" onchange="if(jQuery(this).val().length > 0 ) jQuery('#form_notification_reply_to').val('');">
                                                        <option value=""><?php _e("Select an email field", "gravityforms"); ?></option>
                                                        <?php
                                                        foreach($email_fields as $field){
                                                            $selected = rgar($form["notification"],"replyToField") == $field["id"] ? "selected='selected'" : "";
                                                            ?>
                                                            <option value="<?php echo $field["id"]?>" <?php echo $selected ?>><?php echo GFCommon::get_label($field)?></option>
                                                            <?php
                                                        }
                                                        ?>
                                                    </select>
                                                <?php
                                                }
                                                ?>
                                            </li>
                                            <li>
                                                <label for="form_notification_bcc">
                                                    <?php _e("BCC", "gravityforms"); ?>
                                                    <?php gform_tooltip("notification_bcc") ?>
                                                </label>
                                                <input type="text" name="form_notification_bcc" id="form_notification_bcc" value="<?php echo esc_attr(rgget("bcc", $form["notification"])) ?>" class="fieldwidth-1" />
                                            </li>
                                            <?php
                                                $is_invalid_subject = $invalid_tab == 1 && empty($_POST["form_notification_subject"]);
                                                $class = $is_invalid_subject ? "class='gfield_error'" : "";
                                            ?>
                                            <li <?php echo $class ?>>
                                                <div>
                                                    <label for="form_notification_subject">
                                                        <?php _e("Subject", "gravityforms"); ?><span class="gfield_required">*</span>
                                                    </label>
                                                    <div>
                                                        <?php GFCommon::insert_variables($form["fields"], "form_notification_subject", true); ?>
                                                    </div>
                                                    <input type="text" name="form_notification_subject" id="form_notification_subject" value="<?php echo esc_attr($form["notification"]["subject"]) ?>" class="fieldwidth-1" />

                                                    <?php if($is_invalid_subject){ ?>
                                                        <span class="validation_message"><?php _e("Please enter a subject for the notification email") ?></span>
                                                    <?php } ?>
                                                </div>
                                            </li>
                                            <?php
                                                $is_invalid_message = $invalid_tab == 1 && empty($_POST["form_notification_message"]);
                                                $class = $is_invalid_message ? "class='gfield_error'" : "";
                                            ?>
                                            <li <?php echo $class ?>>
                                                <div>
                                                    <label for="form_notification_message">
                                                        <?php _e("Message", "gravityforms"); ?><span class="gfield_required">*</span>
                                                    </label>
                                                    <?php
                                                    if(GFCommon::is_wp_version("3.3")){
                                                        wp_editor($form["notification"]["message"], "form_notification_message", array("autop"=>false));
                                                    }
                                                    else{
                                                        ?>
                                                        <textarea name="form_notification_message" id="form_notification_message" class="fieldwidth-1 fieldheight-1" ><?php echo esc_html($form["notification"]["message"]) ?></textarea>
                                                        <?php
                                                    }
                                                    if($is_invalid_message){ ?>
                                                        <span class="validation_message"><?php _e("Please enter a message for the notification email") ?></span>
                                                    <?php } ?>
                                                </div>
                                            </li>
                                            <li>
                                                <div>
                                                    <input type="checkbox" name="form_notification_disable_autoformat" id="form_notification_disable_autoformat" value="1" <?php echo empty($form["notification"]["disableAutoformat"]) ? "" : "checked='checked'" ?>/>
                                                    <label for="form_notification_disable_autoformat" class="inline">
                                                        <?php _e("Disable Auto-formatting", "gravityforms"); ?>
                                                        <?php gform_tooltip("notification_autoformat") ?>
                                                    </label>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="submitdiv" class="stuffbox">
                        <h3><span class="hndle"><?php _e("Notification to User", "gravityforms"); ?></span></h3>
                        <div class="inside">
                            <div id="submitcomment" class="submitbox">
                                <div id="minor-publishingx" style="padding:10px;">
                                    <?php
                                    if(!isset($form["autoResponder"]))
                                        $form["autoResponder"] = array();

                                    if(empty($email_fields)){
                                        ?>
                                        <div class="gold_notice">
                                        <p><?php echo sprintf(__("Your form does not have any %semail%s field.", "gravityforms"), "<strong>", "</strong>"); ?></p>
                                        <p>
                                        <?php echo sprintf(__("Sending notifications to users require that the form has at least one email field. %sEdit your form%s", "gravityforms"),'<a href="?page=gf_edit_forms&id=' . absint($form_id) . '">', '</a>'); ?>
                                        </p>
                                        </div>
                                        <?php
                                    }
                                    else {?>

                                        <input type="checkbox" name="form_notification_enable_user" id="form_notification_enable_user" value="1" <?php echo $is_user_notification_enabled ? "checked='checked'" : "" ?> onclick="if(this.checked) {jQuery('#form_notification_user_container').show('slow');} else {jQuery('#form_notification_user_container').hide('slow');}"/> <label for="form_notification_enable_user"><?php _e("Enable email notification to users", "gravityforms") ?></label>

                                        <div id="form_notification_user_container" style="display:<?php echo $is_user_notification_enabled ? "block" : "none"?>;">
                                            <br/>
                                            <?php _e("Enter a message below to send users an automatic response when they submit this form.", "gravityforms"); ?><br/><br/><br/>
                                            <ul id="form_autoresponder_container">
                                                <li>
                                                    <label for="form_autoresponder_to">
                                                        <?php _e("Send To Field", "gravityforms"); ?><span class="gfield_required">*</span>
                                                        <?php gform_tooltip("autoresponder_send_to_email") ?>
                                                    </label>
                                                    <select name="form_autoresponder_to" id="form_autoresponder_to">
                                                        <?php
                                                        foreach($email_fields as $field){
                                                            $selected = rgget("toField", $form["autoResponder"]) == $field["id"] ? "selected='selected'" : "";
                                                            ?>
                                                            <option value="<?php echo $field["id"]?>" <?php echo $selected ?>><?php echo esc_html(GFCommon::get_label($field)) ?></option>
                                                            <?php
                                                        }
                                                        ?>
                                                    </select>
                                                </li>
                                                <li>
                                                    <label for="form_autoresponder_from_name">
                                                        <?php _e("From Name", "gravityforms"); ?>
                                                        <?php gform_tooltip("autoresponder_from_name") ?>
                                                    </label>
                                                    <input type="text" name="form_autoresponder_from_name" id="form_autoresponder_from_name" value="<?php echo esc_attr(rgget("fromName", $form["autoResponder"])) ?>" class="fieldwidth-2" />
                                                </li>
                                                <li>
                                                    <label for="form_autoresponder_from">
                                                        <?php _e("From Email", "gravityforms"); ?>
                                                        <?php gform_tooltip("autoresponder_from") ?>
                                                    </label>
                                                    <input type="text" name="form_autoresponder_from" id="form_autoresponder_from" value="<?php echo rgempty("from", $form["autoResponder"]) ? esc_attr($wp_email) : esc_attr(rgget("from", $form["autoResponder"])) ?>" class="fieldwidth-2" />
                                                </li>
                                                <li>
                                                    <label for="form_autoresponder_reply_to" style="display:block;">
                                                        <?php _e("Reply To (optional)", "gravityforms"); ?>
                                                        <?php gform_tooltip("autoresponder_reply_to") ?>
                                                    </label>
                                                    <input type="text" name="form_autoresponder_reply_to" id="form_autoresponder_reply_to" value="<?php echo esc_attr(rgget("replyTo", $form["autoResponder"])) ?>" class="fieldwidth-2" />
                                                </li>
                                                <li>
                                                    <label for="form_autoresponder_bcc">
                                                        <?php _e("BCC", "gravityforms"); ?>
                                                        <?php gform_tooltip("autoresponder_bcc") ?>
                                                    </label>
                                                    <input type="text" name="form_autoresponder_bcc" id="form_autoresponder_bcc" value="<?php echo esc_attr(rgget("bcc", $form["autoResponder"])) ?>" class="fieldwidth-1" />
                                                </li>

                                                <?php
                                                    $is_invalid_subject = $invalid_tab == 2 && rgempty("form_autoresponder_subject");
                                                    $class = $is_invalid_subject ? "class='gfield_error'" : "";
                                                ?>
                                                <li <?php echo $class ?>>

                                                    <label for="form_autoresponder_subject">
                                                        <?php _e("Subject", "gravityforms"); ?><span class="gfield_required">*</span>
                                                    </label>
                                                    <div>
                                                        <?php GFCommon::insert_variables($form["fields"], "form_autoresponder_subject", true); ?>
                                                    </div>
                                                    <input type="text" name="form_autoresponder_subject" id="form_autoresponder_subject" value="<?php echo esc_attr(rgget("subject", $form["autoResponder"])) ?>" class="fieldwidth-1" />

                                                    <?php if($is_invalid_subject){ ?>
                                                        <span class="validation_message"><?php _e("Please enter a subject for the user notification email") ?></span>
                                                    <?php } ?>

                                                 </li>
                                                 <?php
                                                    $is_invalid_message = $invalid_tab == 2 && rgempty("form_autoresponder_message");
                                                    $class = $is_invalid_message ? "class='gfield_error'" : "";
                                                ?>
                                                <li <?php echo $class ?>>
                                                    <div>
                                                        <label for="form_autoresponder_message">
                                                            <?php _e("Message", "gravityforms"); ?><span class="gfield_required">*</span>
                                                        </label>
                                                        <?php
                                                        if(GFCommon::is_wp_version("3.3")){
                                                            wp_editor(rgget("message", $form["autoResponder"]), "form_autoresponder_message", array("autop"=>false));
                                                        }
                                                        else{
                                                            ?>
                                                            <textarea name="form_autoresponder_message" id="form_autoresponder_message" class="fieldwidth-1 fieldheight-1"><?php echo esc_html(rgget("message", $form["autoResponder"])) ?></textarea>
                                                            <?php
                                                        }

                                                        if($is_invalid_message){ ?>
                                                            <span class="validation_message"><?php _e("Please enter a message for the user notification email") ?></span>
                                                        <?php } ?>
                                                    </div>
                                                </li>
                                                <li>
                                                    <div>
                                                        <input type="checkbox" name="form_autoresponder_disable_autoformat" id="form_autoresponder_disable_autoformat" value="1" <?php echo rgempty("disableAutoformat", $form["autoResponder"]) ? "" : "checked='checked'" ?>/>
                                                        <label for="form_notification_disable_autoformat" class="inline">
                                                            <?php _e("Disable Auto-formatting", "gravityforms"); ?>
                                                            <?php gform_tooltip("notification_autoformat") ?>
                                                        </label>
                                                    </div>
                                                </li>
                                            </ul>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <br class="clear" />
                    <div>
                        <?php
                            $notification_button = '<input class="button-primary" type="submit" value="' . __("Save Settings", "gravityforms") . '" name="save"/>';
                            echo apply_filters("gform_save_notification_button", $notification_button);
                        ?>
                    </div>
                </div>
            </div>
        </form>
        <?php

        if(rgpost("save")){
            if($invalid_tab == 0){
                ?>
                <div class="updated fade" style="padding:6px;">
                    <?php _e("Notification Updated.", "gravityforms"); ?>
                </div>
                <?php
            }
            else{
                ?>
                <div class="error" style="padding:6px;">
                    <?php _e("Notification could not be updated. Please enter all required information below.", "gravityforms"); ?>
                </div>
                <?php
            }
        }
    }

    private static function validate_notification(){

        $admin_tab_invalid = !empty($_POST["form_notification_enable_admin"]) && ( !self::is_valid_admin_to() || empty($_POST["form_notification_subject"]) || empty($_POST["form_notification_message"]) );
        $user_tab_invalid = !empty($_POST["form_notification_enable_user"]) && (empty($_POST["form_autoresponder_to"]) || empty($_POST["form_autoresponder_subject"]) || empty($_POST["form_autoresponder_message"]));

        if($admin_tab_invalid)
            return 1;
        else if($user_tab_invalid)
            return 2;
        else
            return 0;
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
            $invalid_email = GFCommon::is_invalid_or_empty_email($email);
            $invalid_variable = !preg_match('/^({[^{]*?:(\d+(\.\d+)?)(:(.*?))?},? *)+$/', $email);

            if($invalid_email && $invalid_variable)
                return false;
        }

        return true;
    }

    private static function is_valid_admin_to(){
        return ($_POST["notification_to"] == "routing" && self::is_valid_routing())
                ||
                ($_POST["notification_to"] == "email" && (self::is_valid_notification_email($_POST["form_notification_to"])) || $_POST["form_notification_to"] == "{admin_email}");
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
			    $str = "<input type='text' placeholder='" . __("Enter value") . "' class='gfield_routing_select' id='routing_value_" . $i . "' value='" . esc_attr($selected_value) . "' onchange='SetRouting(" . $i . ");' onkeyup='SetRouting(" . $i . ");'>";
			}

            return $str;
    }

    public static function get_post_category_values(){

        $id = "routing_value_" . rgpost("ruleIndex");
        $selected = rgempty("selectedValue") ? 0 : rgpost("selectedValue");

        $dropdown = wp_dropdown_categories(array("class"=>"gfield_routing_select gfield_routing_value_dropdown gfield_category_dropdown", "orderby"=> "name", "id"=> $id, "selected"=>$selected, "hierarchical"=>true, "hide_empty"=>0, "echo"=>false));
        die($dropdown);
    }
}
?>