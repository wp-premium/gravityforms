<script type="text/javascript">
var gforms_dragging = 0;
var gforms_original_json;

function DeleteCustomChoice(){
    if(!confirm("<?php _e("Delete this custom choice list? 'OK' to delete, 'Cancel' to abort.", "gravityforms") ?>"))
        return;

    //Sending AJAX request
    jQuery.post(ajaxurl, {action:"gf_delete_custom_choice", name: gform_selected_custom_choice , gf_delete_custom_choice: "<?php echo wp_create_nonce("gf_delete_custom_choice") ?>"});

    //Updating UI
    delete gform_custom_choices[gform_selected_custom_choice];
    gform_selected_custom_choice = '';

    CloseCustomChoicesPanel();
    jQuery("#gfield_bulk_add_input").val('');
    InitBulkCustomPanel();
    LoadCustomChoices();
    DisplayCustomMessage("<?php _e("Item has been deleted.", "gravityforms")?>");
}

function SaveCustomChoices(){

    var name = jQuery('#custom_choice_name').val();
    if(name.length == 0){
        alert("<?php _e("Please enter name.", "gravityforms") ?>");
        return;
    }
    else if(gform_custom_choices[name] && name != gform_selected_custom_choice){
        alert("<?php _e("This custom choice name is already in use. Please enter another name.", "gravityforms") ?>");
        return;
    }

    var choices = jQuery('#gfield_bulk_add_input').val().split('\n');

    //Sending AJAX request
    jQuery.post(ajaxurl, {action:"gf_save_custom_choice", previous_name: gform_selected_custom_choice , new_name: name, choices: jQuery.toJSON(choices), gf_save_custom_choice: "<?php echo wp_create_nonce("gf_save_custom_choice") ?>"});

    //deleting existing custom choice
    if(gform_selected_custom_choice.length > 0)
        delete gform_custom_choices[gform_selected_custom_choice];

    //saving new custom choice
    gform_custom_choices[name] = choices;

    InitBulkCustomPanel();
    LoadCustomChoices();

    DisplayCustomMessage("<?php _e("Item has been saved.", "gravityforms")?>");
}

function InitializeFormConditionalLogic(){
     var canHaveConditionalLogic = GetFirstRuleField() > 0;
    if(canHaveConditionalLogic){
        jQuery("#form_button_conditional_logic").removeAttr("disabled").attr("checked", form.button.conditionalLogic ? true : false);
        ToggleConditionalLogic(true, "form_button");
    }
    else{
        jQuery("#form_button_conditional_logic").attr("disabled", false).attr("checked", false);
        jQuery("#form_button_conditional_logic_container").show().html("<span class='instruction'><?php _e("To use conditional logic, please create a drop down, checkbox or radio button field.", "gravityforms") ?></span>");
    }
}

function InitPaginationOptions(isInit){
    var speed = isInit ? "" : "slow";

    var pages = GetFieldsByType(["page"]);
    pages.push(new Array());
    var str = "<ul class='gform_page_names'>";

    var pageNameFields = jQuery(".gform_page_names input");
    for(var i=0; i<pages.length; i++){
        var pageName = form["pagination"] && form["pagination"]["pages"] && form["pagination"]["pages"][i] ? form["pagination"]["pages"][i].replace("'", "&#39") : "";
        if(pageNameFields.length > i && pageNameFields[i].value)
            pageName = pageNameFields[i].value;

        str += "<li><label class='inline' for='gform_pagename_" + i + "' ><?php _e("Page", "gravityforms") ?> " + (i+1) + "</label> <input type='text' class='fieldwidth-4' id='gform_pagename_" + i + "' value='" + pageName + "' /></li>";
    }
    str+="</ul>";

    jQuery("#page_names_container").html(str);

    if(jQuery("#pagination_type_none").is(":checked")){
        jQuery(".gform_page_names input").val("");
        jQuery("#percentage_confirmation_page_name").val("");
        jQuery("#percentage_confirmation_display").attr("checked",false);

        jQuery("#page_names_setting").hide(speed);
        jQuery("#percentage_style_setting").hide(speed);
        jQuery("#percentage_confirmation_display_setting").hide(speed);
    }
    else if(jQuery("#pagination_type_percentage").is(":checked")){
        var style = form["pagination"] && form["pagination"]["style"] ? form["pagination"]["style"] : "blue";
        jQuery("#percentage_style").val(style);

        if(style == "custom" && form["pagination"]["backgroundColor"]){
            jQuery("#percentage_style_custom_bgcolor").val(form["pagination"]["backgroundColor"]);
            SetColorPickerColor("percentage_style_custom_bgcolor", form["pagination"]["backgroundColor"] , "");
        }
        if(style == "custom" && form["pagination"]["color"]){
            jQuery("#percentage_style_custom_color").val(form["pagination"]["color"]);
            SetColorPickerColor("percentage_style_custom_color", form["pagination"]["color"] , "");
        }

        jQuery("#page_names_setting").show(speed);
        jQuery("#percentage_style_setting").show(speed);
        jQuery("#percentage_confirmation_display_setting").show(speed);
        jQuery("#percentage_confirmation_page_name_setting").show(speed);

        jQuery("#percentage_confirmation_display").attr("checked", form["pagination"] && form["pagination"]["display_progressbar_on_confirmation"] ? true : false);
        //set default text to Completed when displaying progress bar on confirmation is NOT checked
        var completion_text = form["pagination"] && form["pagination"]["display_progressbar_on_confirmation"] ? form["pagination"]["progressbar_completion_text"] : "<?php _e("Completed","gravityforms") ?>";
        jQuery("#percentage_confirmation_page_name").val(completion_text);
    }
    else{
        jQuery("#percentage_style_setting").hide(speed);
        jQuery("#page_names_setting").show(speed);
        jQuery("#percentage_confirmation_display_setting").hide(speed);
        jQuery("#percentage_confirmation_page_name_setting").hide(speed);
        jQuery("percentage_confirmation_page_name").val("");
        jQuery("#percentage_confirmation_display").attr("checked",false);
    }

    TogglePercentageStyle(isInit);
    TogglePercentageConfirmationText(isInit);
}


function ShowSettings(element_id){
    jQuery(".field_selected .field_edit_icon, .field_selected .form_edit_icon").removeClass("edit_icon_collapsed").addClass("edit_icon_expanded").html('<?php _e("Close", "gravityforms") ?>');
    jQuery("#" + element_id).slideDown();
}

function HideSettings(element_id){
    jQuery(".field_edit_icon, .form_edit_icon").removeClass("edit_icon_expanded").addClass("edit_icon_collapsed").html('<?php _e("Edit", "gravityforms") ?>');
    jQuery("#" + element_id).hide();
}

function TogglePostCategoryInitialItem(isInit){
    var speed = isInit ? "" : "slow";

    if(jQuery("#gfield_post_category_initial_item_enabled").is(":checked")){
        jQuery("#gfield_post_category_initial_item_container").show(speed);

        if(!isInit){
            jQuery("#field_post_category_initial_item").val('<?php _e("Select a category")?>');
        }
    }
    else{
        jQuery("#gfield_post_category_initial_item_container").hide(speed);
        jQuery("#field_post_category_initial_item").val('');
    }

}

function CreateInputNames(field){
    var field_str = "";
    if(!field["inputs"] || GetInputType(field) == "checkbox"){
        field_str = "<label for='field_input_name' class='inline'><?php _e("Parameter Name:", "gravityforms"); ?> </label>";
        field_str += "<input type='text' value='" + field["inputName"] + "' id='field_input_name' onkeyup='SetInputName(this.value);'/>";
    }
    else{
        field_str = "<table><tr><td><strong>Field</strong></td><td><strong>Parameter Name</strong></td></tr>";
        for(var i=0; i<field["inputs"].length; i++){
            field_str += "<tr><td><label for='field_input_" + field["inputs"][i]["id"] + "' class='inline'>" + field["inputs"][i]["label"] + "</label></td>";
            field_str += "<td><input type='text' value='" + field["inputs"][i]["name"] + "' id='field_input_" + field["inputs"][i]["id"] + "' onkeyup=\"SetInputName(this.value, '" + field["inputs"][i]["id"] + "');\"/></td><tr>";
        }
    }

    jQuery("#field_input_name_container").html(field_str);
}

function SetProductField(field){
    var product_field_container = jQuery(".product_field_setting");

    //ignore product field if it is not configured for the current field
    if(!product_field_container.is(":visible"))
        return;

    var productFields = new Array();
    for(var i=0; i<form["fields"].length; i++){
        if(form["fields"][i]["type"] == "product")
            productFields.push(form["fields"][i]);
    }

    jQuery("#gform_no_product_field_message").remove();
    if(productFields.length < 1){
        jQuery("#product_field").hide().after("<div id='gform_no_product_field_message'><?php _e("This field is not associated with a product. Please add a Product Field to the form.", "gravityforms") ?></div>");
    }
    else{
        var product_field = jQuery("#product_field");
        product_field.show();
        product_field.html("");
        var is_selected = false;
        for(var i=0; i<productFields.length; i++){
            selected = "";
            if(productFields[i]["id"] == field["productField"]){
                selected = "selected='selected'";
                is_selected = true;
            }
            product_field.append("<option value='" + productFields[i]["id"] + "' " + selected + ">" + productFields[i]["label"] + "</option>");
        }

        //Adds existing product field if it is not found in the list (to prevent confusion)
        if(!is_selected && field["productField"] != ""){
            product_field.append("<option value='" + field["productField"] + "' selected='selected'>[<?php _e("Deleted Field", "gravityforms") ?>]</option>");
        }

    }
}

function LoadFieldConditionalLogic(isEnabled, objectType){
    var obj = GetConditionalObject(objectType);
    if(isEnabled){
        jQuery("#" + objectType + "_conditional_logic").attr("checked", obj.conditionalLogic ? true : false);
        jQuery("#" + objectType + "_conditional_logic").removeAttr("disabled");
        ToggleConditionalLogic(true, objectType);


    }
    else{
        jQuery("#" + objectType + "_conditional_logic").attr("disabled", true).attr("checked", false);
        jQuery("#" + objectType + "_conditional_logic_container").show().html("<span class='instruction'><?php _e("To use conditional logic, please create a drop down, checkbox or radio button field.", "gravityforms") ?></span>");
    }
}

function GetCurrentCurrency(){
    <?php
    require_once("currency.php");
    $current_currency = RGCurrency::get_currency(GFCommon::get_currency());
    ?>
    var currency = new Currency(<?php echo GFCommon::json_encode($current_currency)?>);
    return currency;
}

function ToggleColumns(isInit){
    var speed = isInit ? "" : "slow";
    var field = GetSelectedField();

    if(jQuery('#field_columns_enabled').is(":checked")){
        jQuery('#gfield_settings_columns_container').show(speed);

         if(!field.choices)
            field.choices = new Array(new Choice("<?php _e("Column 1", "gravityforms"); ?>"), new Choice("<?php _e("Column 2", "gravityforms"); ?>"), new Choice("<?php _e("Column 3", "gravityforms"); ?>"));

        LoadFieldChoices(field, true);
    }
    else
    {
        field.choices = null;
        jQuery('#gfield_settings_columns_container').hide(speed);
    }

    UpdateFieldChoices(GetInputType(field));

}

function DuplicateTitleMessage(){
    jQuery("#please_wait_container").hide();
    alert('<?php _e("The form title you have entered is already taken. Please enter an unique form title", "gravityforms"); ?>');
}

function ValidateForm(){
    var error = "";
    if(jQuery.trim(form.title).length == 0){
        error = "<?php _e("Please enter a Title for this form. When adding the form to a page or post, you will have the option to hide the title.", "gravityforms") ?>";
    }
    else{
        var last_page_break = -1;
        var has_option = false;
        var has_product = false;
        for(var i=0; i<form["fields"].length; i++){
            var field = form["fields"][i];
            switch(field["type"]){
                case "page" :
                    if(i == last_page_break + 1 || i == form["fields"].length-1)
                    error = "<?php _e("Your form currently has one ore more pages without any fields in it. Blank pages are a result of Page Breaks that are positioned as the first or last field in the form or right after to each other. Please adjust your Page Breaks and try again.", "gravityforms") ?>";

                    last_page_break = i;
                break;

                case "product" :
                    has_product = true;
                    if(jQuery.trim(field["label"]).length == 0)
                        error = "<?php _e("Your form currently has a product field with a blank label. \\nPlease enter a label for all product fields.", "gravityforms") ?>";
                break;

                case "option" :
                    has_option = true;
                break;
            }
        }
        if(has_option && !has_product){
            error = "<?php _e("Your form currently has an option field without a product field.\\nYou must add a product field to your form.", "gravityforms") ?>";
        }
    }
    if(error){
        jQuery("#please_wait_container").hide();
        alert(error);
        return false;
    }
    return true;
}

function SaveForm(isNew){

    UpdateFormObject();

    if(!ValidateForm()){
        return false;
    }

    //updating original json. used when verifying if there has been any changes unsaved changed before leaving the page
    var form_json = jQuery.toJSON(form);
    gforms_original_json = form_json;

    if(!isNew){
        jQuery("#gform_meta").val(form_json);
        jQuery("#gform_update").submit();
    }
    else{
        jQuery("#please_wait_container").show();
        var mysack = new sack("<?php echo admin_url("admin-ajax.php")?>" );
        mysack.execute = 1;
        mysack.method = 'POST';
        mysack.setVar( "action", "rg_save_form" );
        mysack.setVar( "rg_save_form", "<?php echo wp_create_nonce("rg_save_form") ?>" );
        mysack.setVar( "id", form.id );
        mysack.setVar( "form", form_json );
        mysack.onError = function() { alert('<?php echo esc_js(__("Ajax error while saving form", "gravityforms")) ?>' )};
        mysack.runAJAX();
    }

    return true;
}

function DeleteField(fieldId){

    if(form.id == 0 || confirm('<?php _e("Warning! Deleting this field will also delete all entry data associated with it. \'Cancel\' to stop. \'OK\' to delete", "gravityforms"); ?>')){

        jQuery('#gform_fields li#field_' + fieldId).addClass('gform_pending_delete');
        var mysack = new sack("<?php echo admin_url("admin-ajax.php")?>" );
        mysack.execute = 1;
        mysack.method = 'POST';
        mysack.setVar( "action", "rg_delete_field" );
        mysack.setVar( "rg_delete_field", "<?php echo wp_create_nonce("rg_delete_field") ?>" );
        mysack.setVar( "form_id", form.id );
        mysack.setVar( "field_id", fieldId );
        mysack.onError = function() { alert('<?php echo esc_js(__("Ajax error while deleting field.", "gravityforms")) ?>' )};
        mysack.runAJAX();

        return true;
    }
}

function SetDefaultValues(field){

    var inputType = GetInputType(field);
    switch(inputType){

        case "post_category" :
            field.label = "<?php _e("Post Category", "gravityforms"); ?>";
            field.inputs = null;
            field.choices = new Array();
            field.displayAllCategories = true;
            field.inputType = 'select';
            break;

        case "section" :
            field.label = "<?php _e("Section Break", "gravityforms"); ?>";
            field.inputs = null;
            field["displayOnly"] = true;
        break;

        case "page" :
            field.label = "";
            field.inputs = null;
            field["displayOnly"] = true;
            field["nextButton"] = new Button();
            field["nextButton"]["text"] = "<?php _e("Next", "gravityforms") ?>";
            field["previousButton"] = new Button();
            field["previousButton"]["text"] = "<?php _e("Previous", "gravityforms") ?>";
        break;

        case "html" :
            field.label = "<?php _e("HTML Block", "gravityforms"); ?>";;
            field.inputs = null;
            field["displayOnly"] = true;
        break;

        case "list" :
            if(!field.label)
                field.label = "<?php _e("List", "gravityforms"); ?>";

            field.inputs = null;

        break;

        case "name" :
            if(!field.label)
                field.label = "<?php _e("Name", "gravityforms"); ?>";

            field.id = parseFloat(field.id);
            switch(field.nameFormat)
            {
                case "extended" :
                    field.inputs = [new Input(field.id + 0.2, '<?php echo esc_js(apply_filters("gform_name_prefix_" . rgget("id"), apply_filters("gform_name_prefix", __("Prefix", "gravityforms"), rgget("id")), rgget("id"))); ?>'), new Input(field.id + 0.3, '<?php echo apply_filters("gform_name_first_" . rgget("id"),apply_filters("gform_name_first",__("First", "gravityforms"), rgget("id")), rgget("id")); ?>'), new Input(field.id + 0.6, '<?php echo apply_filters("gform_name_last_" . rgget("id"), apply_filters("gform_name_last",__("Last", "gravityforms"), rgget("id")), rgget("id")); ?>'), new Input(field.id + 0.8, '<?php echo apply_filters("gform_name_suffix_" . rgget("id"), apply_filters("gform_name_suffix",__("Suffix", "gravityforms"), rgget("id")), rgget("id")); ?>')];
                break;
                case "simple" :
                    field.inputs = null;
                break;
                default :
                    field.inputs = [new Input(field.id + 0.3, '<?php echo esc_js(apply_filters("gform_name_first_" . rgget("id"), apply_filters("gform_name_first",__("First", "gravityforms"), rgget("id")), rgget("id"))); ?>'), new Input(field.id + 0.6, '<?php echo apply_filters("gform_name_last_" . rgget("id"), apply_filters("gform_name_last",__("Last", "gravityforms"), rgget("id")), rgget("id")); ?>')];
                break;
            }
            break;

        case "checkbox" :
            if(!field.label)
                field.label = "<?php _e("Untitled", "gravityforms"); ?>";

            if(!field.choices)
                field.choices = new Array(new Choice("<?php _e("First Choice", "gravityforms"); ?>"), new Choice("<?php _e("Second Choice", "gravityforms"); ?>"), new Choice("<?php _e("Third Choice", "gravityforms"); ?>"));

            field.inputs = new Array();
            for(var i=1; i<=field.choices.length; i++) {
                field.inputs.push(new Input(field.id + (i/10), field.choices[i-1].text));
            }

            break;
        case "radio" :
            if(!field.label)
                field.label = "<?php _e("Untitled", "gravityforms"); ?>";

            field.inputs = null;
            if(!field.choices){
                field.choices = field["enablePrice"] ? new Array(new Choice("<?php _e("First Choice", "gravityforms"); ?>", "", "0.00"), new Choice("<?php _e("Second Choice", "gravityforms"); ?>", "", "0.00"), new Choice("<?php _e("Third Choice", "gravityforms"); ?>", "", "0.00"))
                                                     : new Array(new Choice("<?php _e("First Choice", "gravityforms"); ?>"), new Choice("<?php _e("Second Choice", "gravityforms"); ?>"), new Choice("<?php _e("Third Choice", "gravityforms"); ?>"));
            }
            break;

         case "multiselect" :
         case "select" :
            if(!field.label)
                field.label = "<?php _e("Untitled", "gravityforms"); ?>";

            field.inputs = null;
            if(!field.choices){
                field.choices = field["enablePrice"] ? new Array(new Choice("<?php _e("First Choice", "gravityforms"); ?>", "", "0.00"), new Choice("<?php _e("Second Choice", "gravityforms"); ?>", "", "0.00"), new Choice("<?php _e("Third Choice", "gravityforms"); ?>", "", "0.00"))
                                                     : new Array(new Choice("<?php _e("First Choice", "gravityforms"); ?>"), new Choice("<?php _e("Second Choice", "gravityforms"); ?>"), new Choice("<?php _e("Third Choice", "gravityforms"); ?>"));
            }
            break;
        case "address" :

            if(!field.label)
                field.label = "<?php _e("Address", "gravityforms"); ?>";
            field.inputs = [new Input(field.id + 0.1, '<?php echo esc_js(apply_filters("gform_address_street_" . rgget("id"), apply_filters("gform_address_street",__("Street Address", "gravityforms"), rgget("id")), rgget("id"))); ?>'), new Input(field.id + 0.2, '<?php echo apply_filters("gform_address_street2_" . rgget("id"), apply_filters("gform_address_street2",__("Address Line 2", "gravityforms"), rgget("id")), rgget("id")); ?>'), new Input(field.id + 0.3, '<?php echo apply_filters("gform_address_city_" . rgget("id"), apply_filters("gform_address_city",__("City", "gravityforms"), rgget("id")), rgget("id")); ?>'),
                            new Input(field.id + 0.4, '<?php echo esc_js(apply_filters("gform_address_state_" . rgget("id"), apply_filters("gform_address_state",__("State / Province", "gravityforms"), rgget("id")), rgget("id"))); ?>'), new Input(field.id + 0.5, '<?php echo apply_filters("gform_address_zip_" . rgget("id"), apply_filters("gform_address_zip",__("Zip / Postal Code", "gravityforms"), rgget("id")), rgget("id")); ?>'), new Input(field.id + 0.6, '<?php echo apply_filters("gform_address_country_" . rgget("id"), apply_filters("gform_address_country",__("Country", "gravityforms"), rgget("id")), rgget("id")); ?>')];
            break;
        case "creditcard" :

            if(!field.label)
                field.label = "<?php _e("Credit Card", "gravityforms"); ?>";

            field.inputs = [new Input(field.id + 0.1, '<?php echo esc_js(apply_filters("gform_card_number_" . rgget("id"), apply_filters("gform_card_number",__("Card Number", "gravityforms"), rgget("id")), rgget("id"))); ?>'),
                            new Input(field.id + 0.2, '<?php echo esc_js(apply_filters("gform_card_expiration_" . rgget("id"), apply_filters("gform_card_expiration",__("Expiration Date", "gravityforms"), rgget("id")), rgget("id"))); ?>'),
                            new Input(field.id + 0.3, '<?php echo esc_js(apply_filters("gform_card_security_code_" . rgget("id"), apply_filters("gform_card_security_code",__("Security Code", "gravityforms"), rgget("id")), rgget("id"))); ?>'),
                            new Input(field.id + 0.4, '<?php echo esc_js(apply_filters("gform_card_type_" . rgget("id"), apply_filters("gform_card_type",__("Card Type", "gravityforms"), rgget("id")), rgget("id"))); ?>'),
                            new Input(field.id + 0.5, '<?php echo esc_js(apply_filters("gform_card_name_" . rgget("id"), apply_filters("gform_card_name",__("Cardholder\'s Name", "gravityforms"), rgget("id")), rgget("id"))); ?>')];
            break;
        case "email" :
            field.inputs = null;

            if(!field.label)
                field.label = "<?php _e("Email", "gravityforms"); ?>";

            break;
        case "number" :
            field.inputs = null;

            if(!field.label)
                field.label = "<?php _e("Number", "gravityforms"); ?>";

            if(!field.numberFormat)
                field.numberFormat = "decimal_dot";

            break;
        case "phone" :
            field.inputs = null;
            if(!field.label)
                field.label = "<?php _e("Phone", "gravityforms"); ?>";
            field.phoneFormat = "standard";
            break;
        case "date" :
            field.inputs = null;
            if(!field.label)
                field.label = "<?php _e("Date", "gravityforms"); ?>";
            break;
        case "time" :
            field.inputs = null;
            if(!field.label)
                field.label = "<?php _e("Time", "gravityforms"); ?>";
            break;
        case "website" :
            field.inputs = null;
            if(!field.label)
                field.label = "<?php _e("Website", "gravityforms"); ?>";
            break;
        case "password" :
            field.inputs = null;
            field["displayOnly"] = true;
            if(!field.label)
                field.label = "<?php _e("Password", "gravityforms"); ?>";
            break;
        case "fileupload" :
            field.inputs = null;
            if(!field.label)
                field.label = "<?php _e("File", "gravityforms"); ?>";
            break;
        case "hidden" :
            field.inputs = null;
            if(!field.label)
                field.label = "<?php _e("Hidden Field", "gravityforms"); ?>";
            break;
        case "post_title" :
            field.inputs = null;
            field.label = "<?php _e("Post Title", "gravityforms"); ?>";
            break;
        case "post_content" :
            field.inputs = null;
            field.label = "<?php _e("Post Body", "gravityforms"); ?>";
            break;
        case "post_excerpt" :
            field.inputs = null;
            field.label = "<?php _e("Post Excerpt", "gravityforms"); ?>";
            field.size="small";
            break;
        case "post_tags" :
            field.inputs = null;
            field.label = "<?php _e("Post Tags", "gravityforms"); ?>";
            field.size = "large";
            break;
        case "post_custom_field" :
            field.inputs = null;
            if(!field.inputType)
                field.inputType = "text";
            field.label = "<?php _e("Post Custom Field", "gravityforms"); ?>";
            break;
        case "post_image" :
            field.label = "<?php _e("Post Image", "gravityforms"); ?>";
            field.inputs = null;
            field["allowedExtensions"] = "jpg, jpeg, png, gif";
            break;
        case "captcha" :
            field.inputs = null;
            field["displayOnly"] = true;

            field.label = "<?php _e("Captcha", "gravityforms"); ?>";

            break;
        case "calculation" :
            field.enableCalculation = true;
        case "singleproduct" :
        case "product" :
        case "hiddenproduct" :
            field.label = '<?php _e("Product Name", "gravityforms")?>';
            field.inputs = null;

            if(!field.inputType)
                field.inputType = "singleproduct";

            if(field.inputType == "singleproduct" || field.inputType == "hiddenproduct" || field.inputType == "calculation"){
                field.inputs = [new Input(field.id + 0.1, '<?php echo __("Name", "gravityforms"); ?>'), new Input(field.id + 0.2, '<?php echo __("Price", "gravityforms"); ?>'), new Input(field.id + 0.3, '<?php echo __("Quantity", "gravityforms"); ?>')];
                field.enablePrice = null;
            }

            productDependentFields = GetFieldsByType(["option", "quantity"]);
            for(var i=0; i<productDependentFields.length; i++){
                if(!productDependentFields[i]["productField"])
                    productDependentFields[i]["productField"] = field.id;
            }
            break;
        case "singleshipping" :
        case "shipping" :
            field.label = '<?php _e("Shipping", "gravityforms")?>';
            field.inputs = null;

            if(!field.inputType)
                field.inputType = "singleshipping";

            if(field.inputType == "singleshipping")
                field.enablePrice = null;

            break;
        case "total" :
            field.label = '<?php _e("Total", "gravityforms")?>';
            field.inputs = null;

            break;

        case "option" :
             field.label = '<?php _e("Option", "gravityforms")?>';

            if(!field.inputType)
                field.inputType = "select";

            if(!field.choices){
                field.choices = new Array(new Choice("<?php _e("First Option", "gravityforms"); ?>", "", "0.00"), new Choice("<?php _e("Second Option", "gravityforms"); ?>", "", "0.00"), new Choice("<?php _e("Third Option", "gravityforms"); ?>", "", "0.00"));
            }
            field["enablePrice"] = true;

            productFields = GetFieldsByType(["product"]);
            if(productFields.length > 0)
                field["productField"] = productFields[0]["id"];

            break;
        case "donation" :

            field.label = '<?php _e("Donation", "gravityforms")?>';

            if(!field.inputType)
                field.inputType = "donation";


            field.inputs = null;
            field.enablePrice = null;

            break;

        case "price" :

            field.label = '<?php _e("Price", "gravityforms")?>';

            if(!field.inputType)
                field.inputType = "price";

            field.inputs = null;
            field["enablePrice"] = null;

            break;

        case "quantity" :
             field.label = '<?php _e("Quantity", "gravityformspaypal")?>';

            if(!field.inputType)
                field.inputType = "number";

            productFields = GetFieldsByType(["product"]);
            if(productFields.length > 0)
                field["productField"] = productFields[0]["id"];

            if(!field.numberFormat)
                field.numberFormat = "decimal_dot";

            break;

        <?php do_action('gform_editor_js_set_default_values'); ?>

        default :
            field.inputs = null;
            if(!field.label)
                field.label = "<?php _e("Untitled", "gravityforms"); ?>";
            break;
        break;
     }

    if(window["SetDefaultValues_" + inputType])
        field = window["SetDefaultValues_" + inputType](field);
}

function CreateField(id, type){
     var field = new Field(id, type);
     SetDefaultValues(field);

     if(field.type == "captcha")
     {
            <?php
            $publickey = get_option("rg_gforms_captcha_public_key");
            $privatekey = get_option("rg_gforms_captcha_private_key");
            if(class_exists("ReallySimpleCaptcha") && (empty($publickey) || empty($privatekey))){
                ?>
                field.captchaType = "simple_captcha";
                <?php
            }
            ?>
     }
     return field;
}

function AddCaptchaField(){
    for(var i=0; i<form.fields.length; i++){
        if(form.fields[i].type == "captcha"){
            alert("<?php _e("Only one reCAPTCHA field can be added to the form.", "gravityforms"); ?>");
            return;
        }
    }
    StartAddField('captcha');
}

function CanFieldBeAdded(type){
    switch(type){
        case "shipping" :
            if(GetFieldsByType(["shipping"]).length > 0){
                alert("<?php _e("Only one Shipping field can be added to the form", "gravityforms") ?>");
                return false;
            }
        break;

        case "post_content" :
            if(GetFieldsByType(["post_content"]).length > 0){
                alert("<?php _e("Only one Post Content field can be added to the form", "gravityforms") ?>");
                return false;
            }
        break;
        case "post_title" :
            if(GetFieldsByType(["post_title"]).length > 0){
                alert("<?php _e("Only one Post Title field can be added to the form", "gravityforms") ?>");
                return false;
            }
        break;
        case "post_excerpt" :
            if(GetFieldsByType(["post_excerpt"]).length > 0){
                alert("<?php _e("Only one Post Excerpt field can be added to the form", "gravityforms") ?>");
                return false;
            }
        break;
        case "creditcard" :
            if(GetFieldsByType(["creditcard"]).length > 0){
                alert("<?php _e("Only one credit card field can be added to the form", "gravityforms") ?>");
                return false;
            }
        break;
        case "quantity" :
        case "option" :
            if(GetFieldsByType(["product"]).length <= 0){
                alert("<?php _e("You must add a product field to the form first", "gravityforms") ?>");
                return false;
            }
        break;
    }
    return true;
}

function StartAddField(type){

    if(! CanFieldBeAdded(type))
        return;

    var nextId = GetNextFieldId();
    var field = CreateField(nextId, type);

    var mysack = new sack("<?php echo admin_url("admin-ajax.php")?>?id=" + form.id);
    mysack.execute = 1;
    mysack.method = 'POST';
    mysack.setVar( "action", "rg_add_field" );
    mysack.setVar( "rg_add_field", "<?php echo wp_create_nonce("rg_add_field") ?>" );
    mysack.setVar( "field", jQuery.toJSON(field) );
    mysack.onError = function() { alert('<?php echo esc_js(__("Ajax error while adding field", "gravityforms")) ?>' )};
    mysack.runAJAX();

    return true;
}

function DuplicateField(field, sourceFieldId){

    var mysack = new sack("<?php echo admin_url("admin-ajax.php")?>?id=" + form.id);
    mysack.execute = 1;
    mysack.method = 'POST';
    mysack.setVar( "action", "rg_duplicate_field" );
    mysack.setVar( "rg_duplicate_field", "<?php echo wp_create_nonce("rg_duplicate_field") ?>" );
    mysack.setVar( "field", jQuery.toJSON(field) );
    mysack.setVar( "source_field_id", sourceFieldId);
    mysack.onError = function() { alert('<?php echo esc_js(__("Ajax error while duplicating field", "gravityforms")) ?>' )};
    mysack.runAJAX();

    return true;
}

function StartChangeInputType(type, field){
    if(type == "")
        return;

    jQuery("#field_settings").insertBefore("#gform_fields");

    if(!field)
        field = GetSelectedField();

    field["inputType"] = type;
    SetDefaultValues(field);

    var mysack = new sack("<?php echo admin_url("admin-ajax.php")?>" );
    mysack.execute = 1;
    mysack.method = 'POST';
    mysack.setVar( "action", "rg_change_input_type" );
    mysack.setVar( "rg_change_input_type", "<?php echo wp_create_nonce("rg_change_input_type") ?>" );
    mysack.setVar( "field", jQuery.toJSON(field));
    mysack.onError = function() { alert('<?php echo esc_js(__("Ajax error while changing input type", "gravityforms")) ?>' )};
    mysack.runAJAX();

    return true;
}

function CreateConditionalLogic(objectType, obj){
    if(!obj.conditionalLogic)
        obj.conditionalLogic = new ConditionalLogic();


    var hideSelected = obj.conditionalLogic.actionType == "hide" ? "selected='selected'" :"";
    var showSelected = obj.conditionalLogic.actionType == "show" ? "selected='selected'" :"";
    var allSelected = obj.conditionalLogic.logicType == "all" ? "selected='selected'" :"";
    var anySelected = obj.conditionalLogic.logicType == "any" ? "selected='selected'" :"";
    var imagesUrl = '<?php echo GFCommon::get_base_url() . "/images"?>';

    var objText;
    if(objectType == "field")
        objText = "<?php _e("this field if", "gravityforms") ?>";
    else if(objectType == "page")
        objText = "<?php _e("this page", "gravityforms") ?>";
    else
        objText = "<?php _e("this form button", "gravityforms") ?>";

    var str = "<select id='" + objectType + "_action_type' onchange='SetConditionalProperty(\"" + objectType + "\", \"actionType\", jQuery(this).val());'><option value='show' " + showSelected + "><?php _e("Show", "gravityforms") ?></option><option value='hide' " + hideSelected + "><?php _e("Hide", "gravityforms") ?></option></select>";
    str += objText;
    str += "<select id='" + objectType + "_logic_type' onchange='SetConditionalProperty(\"" + objectType + "\", \"logicType\", jQuery(this).val());'><option value='all' " + allSelected + "><?php _e("All", "gravityforms") ?></option><option value='any' " + anySelected + "><?php _e("Any", "gravityforms") ?></option></select>";
    str += " <?php _e("of the following match:", "gravityforms") ?> ";

    for(var i=0; i<obj.conditionalLogic.rules.length; i++){
        var isSelected = obj.conditionalLogic.rules[i].operator == "is" ? "selected='selected'" :"";
        var isNotSelected = obj.conditionalLogic.rules[i].operator == "isnot" ? "selected='selected'" :"";
        var greaterThanSelected = obj.conditionalLogic.rules[i].operator == ">" ? "selected='selected'" :"";
        var lessThanSelected = obj.conditionalLogic.rules[i].operator == "<" ? "selected='selected'" :"";
        var containsSelected = obj.conditionalLogic.rules[i].operator == "contains" ? "selected='selected'" :"";
        var startsWithSelected = obj.conditionalLogic.rules[i].operator == "starts_with" ? "selected='selected'" :"";
        var endsWithSelected = obj.conditionalLogic.rules[i].operator == "ends_with" ? "selected='selected'" :"";

        str += "<div width='100%'>" + GetRuleFields(objectType, i, obj.conditionalLogic.rules[i].fieldId);
        str += "<select id='" + objectType + "_rule_operator_" + i + "' onchange='SetRuleProperty(\"" + objectType + "\", " + i + ", \"operator\", jQuery(this).val());'><option value='is' " + isSelected + "><?php _e("is", "gravityforms") ?></option><option value='isnot' " + isNotSelected + "><?php _e("is not", "gravityforms") ?></option><option value='>' " + greaterThanSelected + "><?php _e("greater than", "gravityforms") ?></option><option value='<' " + lessThanSelected + "><?php _e("less than", "gravityforms") ?></option><option value='contains' " + containsSelected + "><?php _e("contains", "gravityforms") ?></option><option value='starts_with' " + startsWithSelected + "><?php _e("starts with", "gravityforms") ?></option><option value='ends_with' " + endsWithSelected + "><?php _e("ends with", "gravityforms") ?></option></select>";
        str += GetRuleValues(objectType, i, obj.conditionalLogic.rules[i].fieldId, obj.conditionalLogic.rules[i].value);
        str += "<img src='" + imagesUrl + "/add.png' class='add_field_choice' title='add another rule' alt='add another rule' style='cursor:pointer; margin:0 3px;' onclick=\"InsertRule('" + objectType + "', " + (i+1) + ");\" />";
        if(obj.conditionalLogic.rules.length > 1 )
            str += "<img src='" + imagesUrl + "/remove.png' title='remove this rule' alt='remove this rule' class='delete_field_choice' style='cursor:pointer;' onclick=\"DeleteRule('" + objectType + "', " + i + ");\" /></li>";

        str += "</div>";
    }

    jQuery("#" + objectType + "_conditional_logic_container").html(str);

    //initializing placeholder script
    jQuery.Placeholder.init();
}

function GetFieldChoices(field){
    var imagesUrl = '<?php echo GFCommon::get_base_url() . "/images"?>';
    if(field.choices == undefined)
        return "";

    var currency = GetCurrentCurrency();
    var str = "";
    for(var i=0; i<field.choices.length; i++){

        var checked = field.choices[i].isSelected ? "checked" : "";
        var inputType = GetInputType(field);
        var type = inputType == 'checkbox' ? 'checkbox' : 'radio';

        var value = field.enableChoiceValue ? field.choices[i].value : field.choices[i].text;
        var price = field.choices[i].price ? currency.toMoney(field.choices[i].price) : "";
        if(!price)
            price = "";

        str += "<li data-index='" + i + "'>";
        str += "<img src='" + imagesUrl + "/arrow-handle.png' class='field-choice-handle' alt='<?php _e("Drag to re-order", "gravityforms") ?>' /> ";
        str += "<input type='" + type + "' class='gfield_choice_" + type + "' name='choice_selected' id='" + inputType + "_choice_selected_" + i + "' " + checked + " onclick=\"SetFieldChoice('" + inputType + "', " + i + ");\" /> ";
        str += "<input type='text' id='" + inputType + "_choice_text_" + i + "' value=\"" + field.choices[i].text.replace(/"/g, "&quot;") + "\" onkeyup=\"SetFieldChoice('" + inputType + "', " + i + ");\" class='field-choice-input field-choice-text' />";
        str += "<input type='text' id='"+ inputType + "_choice_value_" + i + "' value=\"" + value.replace(/"/g, "&quot;") + "\" onkeyup=\"SetFieldChoice('" + inputType + "', " + i + ");\" class='field-choice-input field-choice-value' />";
        str += "<input type='text' id='"+ inputType + "_choice_price_" + i + "' value=\"" + price.replace(/"/g, "&quot;") + "\" onchange=\"SetFieldChoice('" + inputType + "', " + i + ");\" class='field-choice-input field-choice-price' />";

		if(window["gform_append_field_choice_option_" + field.type])
            str += window["gform_append_field_choice_option_" + field.type](field, i);

		str += "<img src='" + imagesUrl + "/add.png' class='add_field_choice' title='<?php _e("add another choice", "gravityforms") ?>' alt='<?php _e("add another choice", "gravityforms") ?>' style='cursor:pointer; margin:0 3px;' onclick=\"InsertFieldChoice(" + (i+1) + ");\" />";

        if(field.choices.length > 1 )
            str += "<img src='" + imagesUrl + "/remove.png' title='<?php _e("remove this choice", "gravityforms") ?>' alt='<?php _e("remove this choice", "gravityforms") ?>' class='delete_field_choice' style='cursor:pointer;' onclick=\"DeleteFieldChoice(" + i + ");\" />";

        str += "</li>";

    }
    return str;
}

function GetCaptchaUrl(pos){
    if(pos == undefined)
        pos = "";

    var field = GetSelectedField();
    var size = field.simpleCaptchaSize == undefined ? "medium" : field.simpleCaptchaSize;
    var fg = field.simpleCaptchaFontColor == undefined ? "" : field.simpleCaptchaFontColor;
    var bg = field.simpleCaptchaBackgroundColor == undefined ? "" : field.simpleCaptchaBackgroundColor;

    var url = "<?php echo admin_url("admin-ajax.php?action=rg_captcha_image")?>" + "&type=" + field.captchaType + "&pos=" + pos + "&size=" + size + "&fg=" + fg.replace("#", "%23") + "&bg=" + bg.replace("#", "%23");
    return url;
}

function SetFieldPhoneFormat(phoneFormat){
    var instruction = phoneFormat == "standard" ? "<?php _e("Phone format:", "gravityforms"); ?> (###)###-####" : "";
    var display = phoneFormat == "standard" ? "block" : "none";

    jQuery(".field_selected .instruction").css('display', display).html(instruction);

    SetFieldProperty('phoneFormat', phoneFormat);
}

function LoadMessageVariables(){
    var options = "<option><?php _e("Select a field", "gravityforms"); ?></option><option value='{form_title}'><?php _e("Form Title", "gravityforms"); ?></option><option value='{date_mdy}'><?php _e("Date", "gravityforms"); ?> (mm/dd/yyyy)</option><option value='{date_dmy}'><?php _e("Date", "gravityforms"); ?> (dd/mm/yyyy)</option><option value='{ip}'><?php _e("User IP Address", "gravityforms"); ?></option><option value='{all_fields}'><?php _e("All Submitted Fields", "gravityforms"); ?></option>";

    for(var i=0; i<form.fields.length; i++)
        options += "<option value='{" + form.fields[i].label + ":" + form.fields[i].id + "}'>" + form.fields[i].label + "</option>";

    jQuery("#form_autoresponder_variable").html(options);
}

</script>
<script type="text/javascript" src="<?php echo GFCommon::get_base_url() ?>/js/form_editor.js?version=<?php echo GFCommon::$version ?>"></script>

<?php
    do_action("gform_editor_js");
?>