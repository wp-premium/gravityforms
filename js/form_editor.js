
//-------------------------------------------------
// INITIALIZING PAGE
//-------------------------------------------------

jQuery(document).ready(function() {
    gforms_original_json = jQuery.toJSON(form);

    window.onbeforeunload = function(){
        UpdateFormObject();
        if (gforms_original_json != jQuery.toJSON(form)){
            return "You have unsaved changes.";
        }
    }

    setTimeout("CloseStatus();", 5000)

    jQuery('#gform_fields').sortable({
        axis: 'y',
        cancel: '#field_settings',
        handle: '.gfield_admin_icons',
        start: function(event, ui){gforms_dragging = ui.item[0].id;}
    });

    jQuery('#field_choices').sortable({
        axis: 'y',
        handle: '.field-choice-handle',
        update: function(event, ui){
            var fromIndex = ui.item.data("index");
            var toIndex = ui.item.index();
            MoveFieldChoice(fromIndex, toIndex);
        }
    });



    if(jQuery(document).on){
    jQuery(document).on('change', '.gfield_rule_value_dropdown', function(){
            SetRuleValueDropDown(jQuery(this));
        });
    }
    else{
        jQuery('.gfield_rule_value_dropdown').live('change', function(){
            SetRuleValueDropDown(jQuery(this));
        });
    }
    InitializeForm(form);
});

function SetRuleValueDropDown(element){
        //parsing ID to get objectType and ruleIndex
        var ary = element.attr("id").split('_rule_value_');

        if(ary.length < 2)
            return;

        var objectType = ary[0];
        var ruleIndex = ary[1];

        SetRuleProperty(objectType, ruleIndex, "value", element.val());
}

function CloseStatus(){
    jQuery('.updated_base, .error_base').slideUp();
}

function InitializeForm(form){

    //initializing form settings
    jQuery("#form_title_input").val(form.title);
    jQuery("#gform_title").html(form.title);

    jQuery("#form_description_input").val(form.description);
    jQuery("#gform_description").html(form.description);

    jQuery("#form_label_placement").val(form.labelPlacement);
    jQuery("#form_description_placement").val(form.descriptionPlacement ? form.descriptionPlacement : "below");

    if(!form.confirmation)
        form.confirmation = new Confirmation();

    var isRedirect = (form.confirmation.type == "redirect" && form.confirmation.url.length > 0) ? true : false;
    var isPage = (form.confirmation.type == "page" || (form.confirmation.type == "redirect" && form.confirmation.url.length == 0 && form.confirmation.pageId > 0)) ? true : false;

    if(isRedirect)
        jQuery("#form_confirmation_redirect").attr("checked", true);
    else if(isPage)
        jQuery("#form_confirmation_show_page").attr("checked", true);
    else
        jQuery("#form_confirmation_show_message").attr("checked", true);

    jQuery("#form_confirmation_message").text(form.confirmation.message);
    jQuery("#form_confirmation_url").val(form.confirmation.url == "" ? "http://" : form.confirmation.url);
    jQuery("#form_confirmation_page").val(form.confirmation.pageId);
    jQuery("#form_disable_autoformatting").attr("checked", form.confirmation.disableAutoformat ? true : false);

    var hasQueryString = (form.confirmation.queryString != undefined && form.confirmation.queryString.length > 0);
    jQuery("#form_redirect_querystring").val(hasQueryString ? form.confirmation.queryString : "");
    jQuery("#form_redirect_use_querystring").attr("checked", hasQueryString);
    ToggleQueryString(true);

    if(!form["button"])
        form["button"] = new Button();

    if(form.button.type == "image")
        jQuery("#form_button_image").attr("checked", true);
    else
        jQuery("#form_button_text").attr("checked", true);

    jQuery("#form_button_text_input").val(form.button.text);
    jQuery("#form_button_image_url").val(form.button.imageUrl);
    jQuery("#form_css_class").val(form.cssClass);
    jQuery("#gform_enable_honeypot").attr("checked", form.enableHoneypot ? true : false);
    jQuery("#gform_enable_animation").attr("checked", form.enableAnimation ? true : false);
    jQuery("#gform_limit_entries").attr("checked", form.limitEntries ? true : false);
    jQuery("#gform_limit_entries_count").val(form.limitEntriesCount);
    jQuery("#gform_limit_entries_period").val(form.limitEntriesPeriod);
    jQuery("#form_limit_entries_message").val(form.limitEntriesMessage);
    jQuery("#gform_require_login").attr("checked", form.requireLogin ? true : false);
    jQuery("#gform_require_login_message").val(form.requireLoginMessage);
    jQuery("#gform_schedule_form").attr("checked", form.scheduleForm ? true : false);
    jQuery("#gform_schedule_start").val(form.scheduleStart);
    jQuery("#gform_schedule_end").val(form.scheduleEnd);
    jQuery("#gform_schedule_message").val(form.scheduleMessage);
    jQuery("#gform_schedule_start_hour").val(form.scheduleStartHour ? form.scheduleStartHour : "12");
    jQuery("#gform_schedule_start_minute").val(form.scheduleStartMinute ? form.scheduleStartMinute : "00");
    jQuery("#gform_schedule_start_ampm").val(form.scheduleStartAmpm ? form.scheduleStartAmpm : "am");
    jQuery("#gform_schedule_end_hour").val(form.scheduleEndHour ? form.scheduleEndHour : "12");
    jQuery("#gform_schedule_end_minute").val(form.scheduleEndMinute ? form.scheduleEndMinute : "00");
    jQuery("#gform_schedule_end_ampm").val(form.scheduleEndAmpm ? form.scheduleEndAmpm : "am");

    if(form.lastPageButton && form.lastPageButton.type == "image")
        jQuery("#last_page_button_image").attr("checked", true);
    else if(!form.lastPageButton || form.lastPageButton.type != "image")
        jQuery("#last_page_button_text").attr("checked", true);

    jQuery("#last_page_button_text_input").val(form.lastPageButton ? form.lastPageButton.text : gf_vars["previousLabel"]);
    jQuery("#last_page_button_image_url").val(form.lastPageButton ? form.lastPageButton.imageUrl : "");
    TogglePageButton('last_page', true);

    if(form.postStatus)
        jQuery('#field_post_status').val(form.postStatus);

    if(form.postAuthor)
        jQuery('#field_post_author').val(form.postAuthor);

    //default to checked
    if(form.useCurrentUserAsAuthor == undefined)
        form.useCurrentUserAsAuthor = true;

    jQuery('#gfield_current_user_as_author').attr('checked', form.useCurrentUserAsAuthor ? true : false);

    if(form.postCategory)
        jQuery('#field_post_category').val(form.postCategory);

    if(form.postFormat)
        jQuery('#field_post_format').val(form.postFormat);

    if(form.postContentTemplateEnabled){
        jQuery('#gfield_post_content_enabled').attr("checked", true);
        jQuery('#field_post_content_template').val(form.postContentTemplate);
    }
    else{
        jQuery('#gfield_post_content_enabled').attr("checked", false);
        jQuery('#field_post_content_template').val("");
    }
    TogglePostContentTemplate(true);

    if(form.postTitleTemplateEnabled){
        jQuery('#gfield_post_title_enabled').attr("checked", true);
        jQuery('#field_post_title_template').val(form.postTitleTemplate);
    }
    else{
        jQuery('#gfield_post_title_enabled').attr("checked", false);
        jQuery('#field_post_title_template').val("");
    }
    TogglePostTitleTemplate(true);

    jQuery("#gform_heading").bind("click", function(){FieldClick(this);});
    jQuery("#gform_last_page_settings").bind("click", function(){FieldClick(this);});
    jQuery("#gform_pagination").bind("click", function(){FieldClick(this);});
    jQuery(".gfield").bind("click", function(){FieldClick(this);});

    var paginationType = form["pagination"] && form["pagination"]["type"] ? form["pagination"]["type"] : "percentage";
    var paginationSteps = paginationType == "steps" ? true : false;
    var paginationPercentage = paginationType == "percentage" ? true : false;
    var paginationNone = paginationType == "none" ? true : false;

    if(paginationSteps)
        jQuery("#pagination_type_steps").attr("checked", true);
    else if(paginationPercentage)
        jQuery("#pagination_type_percentage").attr("checked", true);
    else if(paginationNone)
        jQuery("#pagination_type_none").attr("checked", true);

    jQuery("#first_page_css_class").val(form["firstPageCssClass"]);

    jQuery("#field_settings, #form_settings, #last_page_settings, #pagination_settings").tabs({selected:0});

    ToggleButton(true);
    ToggleConfirmation(true);
    ToggleSchedule(true);
    ToggleLimitEntry(true);
    ToggleRequireLogin(true);
    InitializeFormConditionalLogic();

    TogglePageBreakSettings();
    InitPaginationOptions(true);

    jQuery(document).trigger('gform_load_form_settings', [form]);

    InitializeFields();
}

function LoadFieldSettings(){

    //loads settings
    field = GetSelectedField();
    var inputType = GetInputType(field);

    jQuery("#field_label").val(field.label);
    if(field.type == "html"){
        jQuery(".tooltip_form_field_label_html").show();
        jQuery(".tooltip_form_field_label").hide();
    }
    else{
        jQuery(".tooltip_form_field_label_html").hide();
        jQuery(".tooltip_form_field_label").show();
    }

    jQuery("#field_admin_label").val(field.adminLabel);
    jQuery("#field_content").val(field["content"] == undefined ? "" : field["content"]);
    jQuery("#post_custom_field_type").val(field.inputType);
    jQuery("#post_tag_type").val(field.inputType);
    jQuery("#field_size").val(field.size);
    jQuery("#field_required").attr("checked", field.isRequired == true ? true : false);
    jQuery("#field_margins").attr("checked", field.disableMargins == true ? true : false);
    jQuery("#field_no_duplicates").attr("checked", field.noDuplicates == true ? true : false);
    jQuery("#field_default_value").val(field.defaultValue == undefined ? "" : field.defaultValue);
    jQuery("#field_default_value_textarea").val(field.defaultValue == undefined ? "" : field.defaultValue);
    jQuery("#field_description").val(field.description == undefined ? "" : field.description);
    jQuery("#field_css_class").val(field.cssClass == undefined ? "" : field.cssClass);
    jQuery("#field_range_min").val(field.rangeMin);
    jQuery("#field_range_max").val(field.rangeMax);
    jQuery("#field_name_format").val(field.nameFormat);
    jQuery('#field_force_ssl').attr('checked', field.forceSSL ? true : false);
    jQuery('#credit_card_style').val(field.creditCardStyle ? field.creditCardStyle : "style1");

    if(field.adminOnly)
        jQuery("#field_visibility_admin").attr("checked", true);
    else
        jQuery("#field_visibility_everyone").attr("checked", true);

    jQuery("#field_file_extension").val(field.allowedExtensions == undefined ? "" : field.allowedExtensions);
    jQuery("#field_phone_format").val(field.phoneFormat);
    jQuery("#field_error_message").val(field.errorMessage);
    jQuery('#field_captcha_theme').val(field.captchaTheme == undefined ? "red" : field.captchaTheme);
    jQuery('#field_captcha_language').val(field.captchaLanguage == undefined ? "en" : field.captchaLanguage);
    jQuery('#field_other_choice').attr('checked', field.enableOtherChoice ? true : false);
    jQuery('#field_add_icon_url').val(field.addIconUrl ? field.addIconUrl : "");
    jQuery('#field_delete_icon_url').val(field.deleteIconUrl ? field.deleteIconUrl : "");
    jQuery('#gfield_enable_enhanced_ui').attr('checked', field.enableEnhancedUI ? true : false);

    jQuery("#gfield_password_strength_enabled").attr("checked", field.passwordStrengthEnabled == true ? true : false);
    jQuery("#gfield_min_strength").val(field.minPasswordStrength == undefined ? "" : field.minPasswordStrength);
    TogglePasswordStrength(true);

    jQuery("#gfield_email_confirm_enabled").attr("checked", field.emailConfirmEnabled == true ? true : false);

    //Creating blank item for number format to existing number fields so that user is not force into a format (for backwards compatibility)
    if(!field.numberFormat){
        if(jQuery("#field_number_format #field_number_format_blank").length == 0){
            jQuery("#field_number_format").prepend("<option id='field_number_format_blank' value=''>" + gf_vars["selectFormat"] + "</option>");
        }
    }
    else
        jQuery("#field_number_format_blank").remove();

    jQuery("#field_number_format").val(field.numberFormat ? field.numberFormat : "");



    // Handle calculation options

    // hide rounding option for calculation product fields
    if(field.type == 'product' && field.inputType == 'calculation') {
        field.enableCalculation = true;
        jQuery('.field_calculation_rounding').hide();
        jQuery('.field_enable_calculation').hide();
    } else {
        jQuery('.field_calculation_rounding').show();
        jQuery('.field_enable_calculation').show();
    }

    jQuery('#field_enable_calculation').attr('checked', field.enableCalculation ? true : false);
    ToggleCalculationOptions(field.enableCalculation, field);

    jQuery('#field_calculation_formula').val(field.calculationFormula);
    var rounding = gformIsNumber(field.calculationRounding) ? field.calculationRounding : "norounding";
    jQuery('#field_calculation_rounding').val(rounding);



    jQuery("#option_field_type").val(field.inputType);
    var productFieldType = jQuery("#product_field_type");
    productFieldType.val(field.inputType);
    if(has_entry(field.id))
        productFieldType.attr("disabled", true);
    else
        productFieldType.removeAttr("disabled");

    jQuery("#donation_field_type").val(field.inputType);
    jQuery("#quantity_field_type").val(field.inputType);

    if(field["inputType"] == "hiddenproduct" || field["inputType"] == "singleproduct" || field["inputType"] == "singleshipping" || field["inputType"] == "calculation"){
        var basePrice = field.basePrice == undefined ? "" : field.basePrice;
        jQuery("#field_base_price").val(field.basePrice == undefined ? "" : field.basePrice);
        SetBasePrice(basePrice);
    }

    jQuery("#field_disable_quantity").attr("checked", field.disableQuantity == true ? true : false);
    SetDisableQuantity(field.disableQuantity == true);

    var isPassword = field.enablePasswordInput ? true : false
    jQuery("#field_password").attr("checked", isPassword ? true : false);

    jQuery("#field_maxlen").val(typeof field.maxLength == "undefined" ? "" : field.maxLength);
    jQuery("#field_maxrows").val(typeof field.maxRows == "undefined" ? "" : field.maxRows);

    var addressType = field.addressType == undefined ? "international" : field.addressType;
    jQuery('#field_address_type').val(addressType);
    jQuery("#field_address_hide_address2").attr("checked", field.hideAddress2 == true ? true : false);
    jQuery("#field_address_hide_state_" + addressType).attr("checked", field.hideState == true ? true : false);

    var defaultState = field.defaultState == undefined ? "" : field.defaultState;
    var defaultProvince = field.defaultProvince == undefined ? "" : field.defaultProvince; //for backwards compatibility
    var defaultStateProvince = addressType == "canadian" && defaultState == "" ? defaultProvince : defaultState;

    jQuery("#field_address_default_state_" + addressType).val(defaultStateProvince);
    jQuery("#field_address_default_country_" + addressType).val(field.defaultCountry == undefined ? "" : field.defaultCountry);
    jQuery("#field_address_hide_country_" + addressType).attr("checked", field.hideCountry == true ? true : false);

    SetAddressType(true);

    jQuery("#gfield_display_title").attr("checked", field.displayTitle == true ? true : false);
    jQuery("#gfield_display_caption").attr("checked", field.displayCaption == true ? true : false);
    jQuery("#gfield_display_description").attr("checked", field.displayDescription == true ? true : false);

    var customFieldExists = CustomFieldExists(field.postCustomFieldName);
    jQuery("#field_custom_field_name_select")[0].selectedIndex = 0;

    jQuery("#field_custom_field_name_text").val("");
    if(customFieldExists)
        jQuery("#field_custom_field_name_select").val(field.postCustomFieldName);
    else
        jQuery("#field_custom_field_name_text").val(field.postCustomFieldName);

    if(customFieldExists)
        jQuery("#field_custom_existing").attr("checked", true);
    else
        jQuery("#field_custom_new").attr("checked", true);

    ToggleCustomField(true);

    jQuery('#gfield_customfield_content_enabled').attr("checked", field.customFieldTemplateEnabled ? true : false);
    jQuery('#field_customfield_content_template').val(field.customFieldTemplateEnabled ? field.customFieldTemplate : "");
    ToggleCustomFieldTemplate(true);

    if(field.displayAllCategories)
        jQuery("#gfield_category_all").attr("checked", true);
    else
        jQuery("#gfield_category_select").attr("checked", true);

    ToggleCategory(true);

    jQuery('#gfield_post_category_initial_item_enabled').attr("checked", field.categoryInitialItemEnabled ? true : false);
    jQuery('#field_post_category_initial_item').val(field.categoryInitialItemEnabled ? field.categoryInitialItem : "");
    TogglePostCategoryInitialItem(true);

    var hasPostFeaturedImage = field.postFeaturedImage ? true : false;
    jQuery('#gfield_featured_image').attr('checked', hasPostFeaturedImage);

    var isStandardMask = IsStandardMask(field.inputMaskValue);

    jQuery("#field_input_mask").attr('checked', field.inputMask ? true : false);

    if(isStandardMask){
        jQuery("#field_mask_standard").attr("checked", true);
        jQuery("#field_mask_select").val(field.inputMaskValue);
    }
    else{
        jQuery("#field_mask_custom").attr("checked", true);
        jQuery("#field_mask_text").val(field.inputMaskValue);
    }

    ToggleInputMask(true);
    ToggleInputMaskOptions(true);

    if(inputType == "creditcard"){
        if(!field.creditCards || field.creditCards.length <= 0)
            field.creditCards = ['amex', 'visa', 'discover', 'mastercard'];

        for(i in field.creditCards) {
            jQuery('#field_credit_card_' + field.creditCards[i]).attr('checked', true);
        }
    }

    if(!field["dateType"] && inputType == "date")
        field["dateType"] = "datepicker";

    jQuery("#field_date_input_type").val(field["dateType"]);
    jQuery("#gfield_calendar_icon_url").val(field["calendarIconUrl"] == undefined ? "" : field["calendarIconUrl"]);
    jQuery('#field_date_format').val(field['dateFormat'] == "dmy" ? "dmy" : field['dateFormat']);
    jQuery('#field_time_format').val(field['timeFormat'] == "24" ? "24" : "12");

    SetCalendarIconType(field["calendarIconType"], true);

    ToggleDateCalendar(true);
    LoadDateInputs();
    LoadTimeInputs();

    field.allowsPrepopulate = field.allowsPrepopulate ? true : false; //needed when property is undefined

    jQuery("#field_prepopulate").attr("checked", field.allowsPrepopulate ? true : false);
    CreateInputNames(field);
    ToggleInputName(true);

    var canHaveConditionalLogic = GetFirstRuleField() > 0;
    if(field["type"] == "page"){
        LoadFieldConditionalLogic(canHaveConditionalLogic, "next_button");
        LoadFieldConditionalLogic(canHaveConditionalLogic, "page");
    }
    else{
        LoadFieldConditionalLogic(canHaveConditionalLogic, "field");
    }

    if(field.nextButton){

        if(field.nextButton.type == "image")
            jQuery("#next_button_image").attr("checked", true);
        else
            jQuery("#next_button_text").attr("checked", true);

        jQuery("#next_button_text_input").val(field.nextButton.text);
        jQuery("#next_button_image_url").val(field.nextButton.imageUrl);
    }

    if(field.previousButton){

        if(field.previousButton.type == "image")
            jQuery("#previous_button_image").attr("checked", true);
        else
            jQuery("#previous_button_text").attr("checked", true);

        jQuery("#previous_button_text_input").val(field.previousButton.text);
        jQuery("#previous_button_image_url").val(field.previousButton.imageUrl);
    }
    TogglePageButton("next", true);
    TogglePageButton("previous", true);

    jQuery(".gfield_category_checkbox").each(function(){
        if(field["choices"]){
            for(var i=0; i<field["choices"].length; i++){
                if(this.value == field["choices"][i].value){
                    this.checked = true;
                    return;
                }
            }
        }
        this.checked = false;
    });

    if(has_entry(field.id))
        jQuery("#field_type, #field_name_format").attr("disabled", true);
    else
        jQuery("#field_type, #field_name_format").attr("disabled", false);

    jQuery("#field_custom_field_name").val(field.postCustomFieldName);

    jQuery("#field_columns_enabled").attr("checked", field.enableColumns ? true : false);

    LoadFieldChoices(field);

    //displays appropriate settings
    jQuery(".field_setting").hide();

    var allSettings = fieldSettings[field.type];

    if(field.inputType && field.type != 'post_category')
        allSettings += "," + fieldSettings[field.inputType];

    jQuery(allSettings).show();

    //hide post category drop down if post category field is in the form
    for(var i=0; i<form.fields.length; i++){
        if(form.fields[i].type == "post_category"){
            jQuery(".post_category_setting").hide();
            break;
        }
    }

    // hide "Display placeholder" option for post category field if input type is not a select
    if(field.type == 'post_category' && inputType != 'select') {
        jQuery('.post_category_initial_item_setting').hide();
        jQuery('#gfield_post_category_initial_item_enabled').prop('checked', false);
        SetCategoryInitialItem();
    }

    //hide "Enable calculation" option for quantity fields
    if(field.type == 'quantity') {
        jQuery('.calculation_setting').hide();
    }

    jQuery("#post_category_field_type").val(field.inputType);

    jQuery("#field_captcha_type").val(field.captchaType == undefined ? "recaptcha" : field.captchaType);
    jQuery("#field_captcha_size").val(field.simpleCaptchaSize == undefined ? "medium" : field.simpleCaptchaSize);

    var fg = field.simpleCaptchaFontColor == undefined ? "" : field.simpleCaptchaFontColor;
    jQuery("#field_captcha_fg").val(fg);
    SetColorPickerColor("field_captcha_fg", fg);

    var bg = field.simpleCaptchaBackgroundColor == undefined ? "" : field.simpleCaptchaBackgroundColor;
    jQuery("#field_captcha_bg").val(bg);
    SetColorPickerColor("field_captcha_bg", bg);

    //controlling settings based on captcha type
    if(field["type"] == "captcha"){
        var recaptcha_settings = ".captcha_language_setting, .captcha_theme_setting";
        var simple_captcha_settings = ".captcha_size_setting, .captcha_fg_setting, .captcha_bg_setting";

        if(field["captchaType"] == "simple_captcha" || field["captchaType"] == "math"){
            jQuery(simple_captcha_settings).show();
            jQuery(recaptcha_settings).hide();
        }
        else{
            jQuery(simple_captcha_settings).hide();
            jQuery(recaptcha_settings).show();
        }
    }

    //Display custom field template for texareas and text fields
    if(field["type"] == "post_custom_field" && field["inputType"] == "textarea" || field["inputType"] == "text"){
        jQuery(".customfield_content_template_setting").show();
    }


    //Display default value setting and size setting for simple name field
    if(field["type"] == "name" && field["nameFormat"] == "simple"){
        jQuery(".default_value_setting").show();
        jQuery(".size_setting").show();
    }

    // if a product or option field, hide "other choice" setting
    if(jQuery.inArray(field['type'], ['product', 'option']) != -1) {
        jQuery(".other_choice_setting").hide();
    }

    // if calc enabled, hide range
    if(field.enableCalculation) {
        jQuery('li.range_setting').hide();
    }

    if(field.type == 'text') {
        if(field.inputMask) {
            jQuery(".maxlen_setting").hide();
        } else {
            jQuery(".maxlen_setting").show();
        }
    }

    jQuery(document).trigger('gform_load_field_settings', [field, form]);

    jQuery("#field_settings").appendTo(".field_selected");

    jQuery("#field_settings").tabs("select", 0);

    ShowSettings("field_settings");

    SetProductField(field);
}

function TogglePageBreakSettings(){
    if(HasPageBreak()){
        jQuery("#gform_last_page_settings").show();
        jQuery("#gform_pagination").show();
    }
    else
    {
        jQuery("#gform_last_page_settings").hide();
        jQuery("#gform_pagination").hide();
    }
}

function UpdateFormProperty(name, value){
    jQuery("#gform_" + name).html(value);
}

function UpdateLabelPlacement(isInit){
    var placement = jQuery("#form_label_placement").val();
    jQuery("#gform_fields").removeClass("top_label").removeClass("left_label").removeClass("right_label").addClass(placement);

    var speed = isInit ? "" : "slow";

    if(placement == 'top_label'){
        jQuery('#description_placement_setting').show(speed);
    }
    else{
        jQuery('#description_placement_setting').hide(speed);
        jQuery('#form_description_placement').val('below');
        UpdateDescriptionPlacement();
    }
}

function UpdateDescriptionPlacement(){
    var placement = jQuery("#form_description_placement").val();

    jQuery("#gform_fields").removeClass("description_below").removeClass("description_above").addClass("description_" + placement);

    jQuery(".gfield_description").each(function(){
        var prevElement = placement == "above" ?  ".gfield_label" : ".ginput_container:visible";
        jQuery(this).siblings(prevElement).after(jQuery(this).remove());
    });
}

function SetDisableQuantity(isChecked){
    SetFieldProperty('disableQuantity', isChecked);
    if(isChecked)
        jQuery(".field_selected .ginput_quantity_label, .field_selected .ginput_quantity").hide();
    else
        jQuery(".field_selected .ginput_quantity_label, .field_selected .ginput_quantity").show();
}

function SetBasePrice(number){
    if(!number)
        number = 0;

    var currency = GetCurrentCurrency();
    var price = currency.toMoney(number);
    if(price == false)
        price = 0;

    jQuery("#field_base_price").val(price);

    SetFieldProperty('basePrice', price);
    jQuery(".field_selected .ginput_product_price, .field_selected .ginput_shipping_price").html(price);
    jQuery(".field_selected .ginput_amount").val(price);
}

function SetAddressType(isInit){
    field = GetSelectedField();

    if(field["type"] != "address")
        return;

    SetAddressProperties();
    jQuery(".gfield_address_type_container").hide();
    var speed = isInit ? "" : "slow";
    jQuery("#address_type_container_" + jQuery("#field_address_type").val()).show(speed);
}

function UpdateAddressFields(){
    var addressType = jQuery("#field_address_type").val();
    field = GetSelectedField();

    //change zip label
    var zip_label = jQuery("#field_address_zip_label_" + addressType).val();
    jQuery(".field_selected #input_" + field["id"] + "_5_label").html(zip_label);

    //change state label
    var state_label = jQuery("#field_address_state_label_" + addressType).val();
    jQuery(".field_selected #input_" + field["id"] + "_4_label").html(state_label);

    //hide country drop down if this address type applies to a specific country
    var hide_country = jQuery("#field_address_country_" + addressType).val() != "" || jQuery("#field_address_hide_country_" + addressType).is(":checked");
    if(hide_country){
        //hides country drop down
        jQuery(".field_selected #input_" + field["id"] + "_6_container").hide();
    }
    else{
        //selects default country and displays drop down
        jQuery(".field_selected #input_" + field["id"] + "_6").val(jQuery("#field_address_default_country_" + addressType).val());
        jQuery(".field_selected #input_" + field["id"] + "_6_container").show();
    }

    var has_state_drop_down = jQuery("#field_address_has_states_" + addressType).val() != "";
    if(has_state_drop_down){
        jQuery(".field_selected .state_text").hide();
        var selected_state = jQuery("#field_address_default_state_" + addressType).val()
        var state_dropdown = jQuery(".field_selected .state_dropdown");
        state_dropdown.append(jQuery('<option></option>').val(selected_state).html(selected_state));
        state_dropdown.val(selected_state).show();
    }
    else{
        jQuery(".field_selected .state_dropdown").hide();
        jQuery(".field_selected .state_text").val("").show();
    }

    //hide/show address line 2
    if(jQuery("#field_address_hide_address2").is(":checked"))
        jQuery(".field_selected #input_" + field["id"] + "_2_container").hide();
    else
        jQuery(".field_selected #input_" + field["id"] + "_2_container").show();

    //hide/show state field
    if(jQuery("#field_address_hide_state_" + addressType).is(":checked"))
        jQuery(".field_selected #input_" + field["id"] + "_4_container").hide();
    else
        jQuery(".field_selected #input_" + field["id"] + "_4_container").show();
}

function SetAddressProperties(){
    var addressType = jQuery("#field_address_type").val();
    SetFieldProperty("addressType", addressType);
    SetFieldProperty("hideAddress2", jQuery("#field_address_hide_address2").is(":checked"));
    SetFieldProperty("hideState", jQuery("#field_address_hide_state_" + addressType).is(":checked"));
    SetFieldProperty("defaultState", jQuery("#field_address_default_state_" + addressType).val());
    SetFieldProperty("defaultProvince",""); //for backwards compatibility

    //Only save the hide country property for address types that have that option (ones with no country)
    var country = jQuery("#field_address_country_" + addressType).val();
    if(country == ""){
        SetFieldProperty("hideCountry",jQuery("#field_address_hide_country_" + addressType).is(":checked"));
        country = jQuery("#field_address_default_country_" + addressType).val();
    }

    SetFieldProperty("defaultCountry",country);

    UpdateAddressFields();
}

function ToggleLimitEntry(isInit){
    var speed = isInit ? "" : "slow";

    if(jQuery("#gform_limit_entries").is(":checked")){
        jQuery("#gform_limit_entries_container").show(speed);
    }
    else{
        jQuery("#gform_limit_entries_container").hide(speed);
    }
}

function ToggleRequireLogin(isInit){
    var speed = isInit ? "" : "slow";

    if(jQuery("#gform_require_login").is(":checked")){
        jQuery("#gform_require_login_container").show(speed);
    }
    else{
        jQuery("#gform_require_login_container").hide(speed);
    }
}

function TogglePasswordStrength(isInit){
    var speed = isInit ? "" : "slow";

    if(jQuery("#gfield_password_strength_enabled").is(":checked")){
        jQuery("#gfield_min_strength_container").show(speed);
    }
    else{
        jQuery("#gfield_min_strength_container").hide(speed);
    }
}

function ToggleSchedule(isInit){
    var speed = isInit ? "" : "slow";

    if(jQuery("#gform_schedule_form").is(":checked")){
        jQuery("#gform_schedule_form_container").show(speed);
    }
    else{
        jQuery("#gform_schedule_form_container").hide(speed);
    }
}

function ToggleCategory(isInit){
    var speed = isInit ? "" : "slow";

    if(jQuery("#gfield_category_all").is(":checked")){
        jQuery("#gfield_settings_category_container").hide(speed);
         SetFieldProperty("displayAllCategories", true);
         SetFieldProperty("choices", new Array()); //reset selected categories
    }
    else{
        jQuery("#gfield_settings_category_container").show(speed);
        SetFieldProperty("displayAllCategories", false);
    }
}

function SetCustomFieldTemplate(){
    var enabled = jQuery("#gfield_customfield_content_enabled").is(":checked");
    SetFieldProperty("customFieldTemplate", enabled ? jQuery("#field_customfield_content_template").val() : null);
    SetFieldProperty("customFieldTemplateEnabled", enabled );
}

function SetCategoryInitialItem(){
    var enabled = jQuery("#gfield_post_category_initial_item_enabled").is(":checked");
    SetFieldProperty("categoryInitialItem", enabled ? jQuery("#field_post_category_initial_item").val() : null);
    SetFieldProperty("categoryInitialItemEnabled", enabled );
}

function PopulateContentTemplate(fieldName){
    if(jQuery("#" + fieldName).val().length == 0){
        var field = GetSelectedField();
        jQuery("#" + fieldName).val("{" + field.label + ":" + field.id + "}");
    }
}

function TogglePostContentTemplate(isInit){
    var speed = isInit ? "" : "slow";

    if(jQuery("#gfield_post_content_enabled").is(":checked")){
        jQuery("#gfield_post_content_container").show(speed);
        if(!isInit){
            PopulateContentTemplate("field_post_content_template");
        }
    }
    else{
        jQuery("#gfield_post_content_container").hide(speed);
    }
}

function TogglePostTitleTemplate(isInit){
    var speed = isInit ? "" : "slow";
    if(jQuery("#gfield_post_title_enabled").is(":checked")){
        jQuery("#gfield_post_title_container").show(speed);
        if(!isInit)
            PopulateContentTemplate("field_post_title_template");

    }
    else{
        jQuery("#gfield_post_title_container").hide(speed);
    }
}

function ToggleCustomFieldTemplate(isInit){
    var speed = isInit ? "" : "slow";

    if(jQuery("#gfield_customfield_content_enabled").is(":checked")){
        jQuery("#gfield_customfield_content_container").show(speed);
        if(!isInit){
            PopulateContentTemplate("field_customfield_content_template");
        }
    }
    else{
        jQuery("#gfield_customfield_content_container").hide(speed);
    }
}

function ToggleQueryString(isInit){
    var speed = isInit ? "" : "slow";
    if(jQuery('#form_redirect_use_querystring').is(":checked")){
        jQuery('#form_redirect_querystring_container').show(speed);
    }
    else{
        jQuery('#form_redirect_querystring_container').hide(speed);
        jQuery("#form_redirect_querystring").val("");
    }

}

function ToggleInputName(isInit){
    var speed = isInit ? "" : "slow";
    if(jQuery('#field_prepopulate').is(":checked")){
        jQuery('#field_input_name_container').show(speed);
    }
    else{
        jQuery('#field_input_name_container').hide(speed);
        jQuery("#field_input_name").val("");
    }

}

function SetFieldColumns(){

    SetFieldChoices();
}

function ToggleChoiceValue(isInit){
    var speed = isInit ? "" : "slow";
    var field = GetSelectedField();
    var suffix = field.enablePrice ? "_and_price" : "";
    var container = jQuery('#gfield_settings_choices_container');

    //removing all classes
    container.removeClass("choice_with_price choice_with_value choice_with_value_and_price");

    var isShowValues = jQuery('#field_choice_values_enabled').is(":checked");
    if(isShowValues){
        container.addClass("choice_with_value" + suffix);
    }
    else if(field.enablePrice){
        container.addClass("choice_with_price");
    }

    /*var label = isShowValues ? gf_vars.hideValues : gf_vars.showValues;
    jQuery('.gfield_value_label').html(label);*/
}

function GetConditionalObject(objectType){
    switch(objectType){
        case "page" :
        case "field" :
            return GetSelectedField();
        break;

        case "next_button" :
            var field = GetSelectedField();
            return field["nextButton"];
        break;

        default:
            return form.button;
        break;
    }
}

function ToggleConditionalLogic(isInit, objectType){
    var speed = isInit ? "" : "slow";
    if(jQuery('#' + objectType + '_conditional_logic').is(":checked")){

        var obj = GetConditionalObject(objectType);

        CreateConditionalLogic(objectType, obj);

        //Initializing object so it has the default options set
        SetConditionalProperty(objectType, "actionType", jQuery("#" + objectType + "_action_type").val());
        SetConditionalProperty(objectType, "logicType", jQuery("#" + objectType + "_logic_type").val());
        SetRule(objectType, 0);

        jQuery('#' + objectType + '_conditional_logic_container').show(speed);
    }
    else{
        jQuery('#' + objectType + '_conditional_logic_container').hide(speed);
    }

}

function ToggleConfirmation(isInit){

    var isRedirect = jQuery("#form_confirmation_redirect").is(":checked");
    var isPage = jQuery("#form_confirmation_show_page").is(":checked");

    if(isRedirect){
        show_element = "#form_confirmation_redirect_container";
        hide_element = "#form_confirmation_message_container, #form_confirmation_page_container";
    }
    else if(isPage){
        show_element = "#form_confirmation_page_container";
        hide_element = "#form_confirmation_message_container, #form_confirmation_redirect_container";
    }
    else{
        show_element = "#form_confirmation_message_container";
        hide_element = "#form_confirmation_page_container, #form_confirmation_redirect_container";
    }

    var speed = isInit ? "" : "slow";

    jQuery(hide_element).hide(speed);
    jQuery(show_element).show(speed);

}

function ToggleButton(isInit){
    var isText = jQuery("#form_button_text").is(":checked");
    show_element = isText ? "#form_button_text_container" : "#form_button_image_container"
    hide_element = isText ? "#form_button_image_container"  : "#form_button_text_container";

    if(isInit){
        jQuery(hide_element).hide();
        jQuery(show_element).show();
    }
    else{
        jQuery(hide_element).hide();
        jQuery(show_element).fadeIn(800);
     }

}

function TogglePageButton(button_name, isInit){
    var isText = jQuery("#" + button_name + "_button_text").is(":checked");
    show_element = isText ? "#" + button_name + "_button_text_container" : "#" + button_name + "_button_image_container"
    hide_element = isText ? "#" + button_name + "_button_image_container"  : "#" + button_name + "_button_text_container";

    if(isInit){
        jQuery(hide_element).hide();
        jQuery(show_element).show();
    }
    else{
        jQuery(hide_element).hide();
        jQuery(show_element).fadeIn(800);
     }
}

function SetPageButton(button_name){
    field = GetSelectedField();
    var buttonType = jQuery("#" + button_name + "_button_image").is(":checked") ? "image" : "text";
    field[button_name + "Button"]["type"] = buttonType;
    if(buttonType == "image"){
        field[button_name + "Button"]["text"] = "";
        field[button_name + "Button"]["imageUrl"] = jQuery("#" + button_name + "_button_image_url").val();
    }
    else{
        field[button_name + "Button"]["text"] = jQuery("#" + button_name + "_button_text_input").val();
        field[button_name + "Button"]["imageUrl"] = "";
    }
}

function ToggleCustomField(isInit){

    var isExisting = jQuery("#field_custom_existing").is(":checked");
    show_element = isExisting ? "#field_custom_field_name_select" : "#field_custom_field_name_text"
    hide_element = isExisting ? "#field_custom_field_name_text"  : "#field_custom_field_name_select";

    var speed = isInit ? "" : "";

    jQuery(hide_element).hide(speed);
    jQuery(show_element).show(speed);

}

function ToggleInputMask(isInit){

    var speed = isInit ? "" : "slow";

    if(jQuery("#field_input_mask").is(":checked")){
        jQuery("#gform_input_mask").show(speed);
        jQuery(".maxlen_setting").hide();

        SetFieldProperty('inputMask', true);

        //setting max length to blank
        jQuery("#field_maxlen").val("");
        SetFieldProperty('maxLength', "");
    }
    else{
        jQuery("#gform_input_mask").hide(speed);
        jQuery(".maxlen_setting").show();
        SetFieldProperty('inputMask', false);
        SetFieldProperty('inputMaskValue', '');
    }
}

function ToggleInputMaskOptions(isInit){

    var isStandard = jQuery("#field_mask_standard").is(":checked");
    show_element = isStandard ? "#field_mask_select" : "#field_mask_text, .mask_text_description"
    hide_element = isStandard ? "#field_mask_text, .mask_text_description"  : "#field_mask_select";

    var speed = isInit ? "" : "";

    jQuery(hide_element).val('').hide(speed);
    jQuery(show_element).show(speed);

    if(!isInit)
        SetFieldProperty('inputMaskValue', '');
}

function ToggleAutoresponder(){
    if(jQuery("#form_autoresponder_enabled").is(":checked"))
        jQuery("#form_autoresponder_container").show("slow");
    else
        jQuery("#form_autoresponder_container").hide("slow");
}

function HasPostField(){
    for(var i=0; i<form.fields.length; i++){
        var type = form.fields[i].type;
        if(type == "post_title" || type == "post_content" || type == "post_excerpt")
            return true;
    }
    return false;
}

function HasPostContentField(){
    for(var i=0; i<form.fields.length; i++){
        var type = form.fields[i].type;
        if(type == "post_content")
            return true;
    }
    return false;
}

function HasPostTitleField(){
    for(var i=0; i<form.fields.length; i++){
        var type = form.fields[i].type;
        if(type == "post_title")
            return true;
    }
    return false;
}

function HasCustomField(){
    for(var i=0; i<form.fields.length; i++){
        var type = form.fields[i].type;
        if(type == "post_custom_field")
            return true;
    }
    return false;
}

function HasPageBreak(){
    for(var i=0; i<form.fields.length; i++){
        var type = form.fields[i].type;
        if(type == "page")
            return true;
    }
    return false;
}

function SetButtonConditionalLogic(isChecked){
    form.button.conditionalLogic = isChecked ? new ConditionalLogic() : null;
}

function SetNextButtonConditionalLogic(isChecked){
    var field = GetSelectedField();

    field.nextButton.conditionalLogic = isChecked ? new ConditionalLogic() : null;
}

function UpdateFormObject(){

    form.title = jQuery("#form_title_input").val();
    form.description = jQuery("#form_description_input").val();
    form.labelPlacement = jQuery("#form_label_placement").val();
    form.descriptionPlacement = jQuery("#form_description_placement").val();

    form.confirmation.message = jQuery("#form_confirmation_message").val();
    form.confirmation.url = jQuery("#form_confirmation_url").val() == "http://" ? "" : jQuery("#form_confirmation_url").val();
    form.confirmation.pageId = jQuery("#form_confirmation_page").val();
    form.confirmation.queryString = jQuery("#form_redirect_querystring").val();
    form.confirmation.disableAutoformat = jQuery("#form_disable_autoformatting").is(":checked");

    if(jQuery("#form_confirmation_redirect").is(":checked") && form.confirmation.url.length > 0){
        form.confirmation.type = "redirect";
        form.confirmation.pageId = "";
        form.confirmation.message = "";
        form.confirmation.disableAutoformat = false;
    }
    else if(jQuery("#form_confirmation_show_page").is(":checked") && form.confirmation.pageId != ""){
        form.confirmation.type = "page";
        form.confirmation.message = "";
        form.confirmation.url = "";
        form.confirmation.queryString = "";
        form.confirmation.disableAutoformat = false;
    }
    else{
        form.confirmation.type = "message";
        form.confirmation.url = "";
        form.confirmation.pageId = "";
        form.confirmation.queryString = "";
    }

    form.button.type = jQuery("#form_button_text").is(":checked") ? "text" : "image";
    form.button.text = jQuery("#form_button_text_input").val();
    form.button.imageUrl = jQuery("#form_button_image_url").val();
    form.cssClass = jQuery("#form_css_class").val();
    form.enableHoneypot = jQuery("#gform_enable_honeypot").is(":checked");
    form.enableAnimation = jQuery("#gform_enable_animation").is(":checked");

    form.postContentTemplateEnabled = false;
    form.postTitleTemplateEnabled = false;
    form.postTitleTemplate = "";
    form.postContentTemplate = "";

    if(HasPostField()){
        form.postAuthor = jQuery('#field_post_author').val() ? jQuery('#field_post_author').val() : "";
        form.useCurrentUserAsAuthor = jQuery('#gfield_current_user_as_author').is(":checked");
        form.postCategory = jQuery('#field_post_category').val();
        form.postFormat = jQuery('#field_post_format').length != 0 ? jQuery('#field_post_format').val() : 0;
        form.postStatus = jQuery('#field_post_status').val();
    }

    if(jQuery("#gfield_post_content_enabled").is(":checked") && HasPostContentField()){
        form.postContentTemplateEnabled = true;
        form.postContentTemplate = jQuery("#field_post_content_template").val();
    }

    if(jQuery("#gfield_post_title_enabled").is(":checked")  && HasPostTitleField()){
        form.postTitleTemplateEnabled = true;
        form.postTitleTemplate = jQuery("#field_post_title_template").val();
    }

    if(jQuery("#gform_last_page_settings").is(":visible")){
        form.lastPageButton = new Button();
        form.lastPageButton.type = jQuery("#last_page_button_text").is(":checked") ? "text" : "image";
        if(form.lastPageButton.type == "image"){
            form.lastPageButton.text = "";
            form.lastPageButton.imageUrl = jQuery("#last_page_button_image_url").val();
        }
        else{
            form.lastPageButton.text = jQuery("#last_page_button_text_input").val();
            form.lastPageButton.imageUrl = "";
        }
    }
    else{
        form.lastPageButton = null;
    }

    if(jQuery("#gform_pagination").is(":visible")){
        form["pagination"] = new Object();
        var type = jQuery("input[name=\"pagination_type\"]:checked").val();
        form["pagination"]["type"] = type;

        var pageNames = jQuery(".gform_page_names input");
        form["pagination"]["pages"] = new Array();
        for(var i=0; i<pageNames.length; i++){
            form["pagination"]["pages"].push(jQuery(pageNames[i]).val());
        }

        if(type == "percentage"){
            form["pagination"]["style"] = jQuery("#percentage_style").val();
            form["pagination"]["backgroundColor"] = form["pagination"]["style"] == "custom" ? jQuery("#percentage_style_custom_bgcolor").val() : null;
            form["pagination"]["color"] = form["pagination"]["style"] == "custom" ? jQuery("#percentage_style_custom_color").val() : null;
            form["pagination"]["display_progressbar_on_confirmation"] = jQuery("#percentage_confirmation_display").is(":checked");
            form["pagination"]["progressbar_completion_text"] = jQuery("#percentage_confirmation_display").is(":checked") ? jQuery("#percentage_confirmation_page_name").val() : null;
        }
        else{
            form["pagination"]["backgroundColor"] = null;
            form["pagination"]["color"] = null;
            form["pagination"]["display_progressbar_on_confirmation"] = null;
            form["pagination"]["progressbar_completion_text"] = null;
        }

        form["firstPageCssClass"] = jQuery("#first_page_css_class").val();
    }
    else{
        form["pagination"] = null;
        form["firstPageCssClass"] = null;
    }

    form.limitEntries = jQuery("#gform_limit_entries").is(":checked");
    if(form.limitEntries){
        form.limitEntriesCount = jQuery("#gform_limit_entries_count").val();
        form.limitEntriesMessage = jQuery("#form_limit_entries_message").val();
        form.limitEntriesPeriod = jQuery("#gform_limit_entries_period").val();
    }
    else{
        form.limitEntriesCount = "";
        form.limitEntriesMessage = "";
        form.limitEntriesPeriod = "";
    }

    form.requireLogin = jQuery("#gform_require_login").is(":checked");
    if(form.requireLogin){
        form.requireLoginMessage = jQuery("#gform_require_login_message").val();
    } else {
        form.requireLoginMessage = "";
    }

    form.scheduleForm = jQuery("#gform_schedule_form").is(":checked");
    if(form.scheduleForm){
        form.scheduleStart = jQuery("#gform_schedule_start").val();
        form.scheduleStartHour = jQuery("#gform_schedule_start_hour").val();
        form.scheduleStartMinute = jQuery("#gform_schedule_start_minute").val();
        form.scheduleStartAmpm = jQuery("#gform_schedule_start_ampm").val();
        form.scheduleEnd = jQuery("#gform_schedule_end").val();
        form.scheduleEndHour = jQuery("#gform_schedule_end_hour").val();
        form.scheduleEndMinute = jQuery("#gform_schedule_end_minute").val();
        form.scheduleEndAmpm = jQuery("#gform_schedule_end_ampm").val();
        form.scheduleMessage = jQuery("#gform_schedule_message").val();
    }
    else{
        form.scheduleStart = "";
        form.scheduleStartHour = "";
        form.scheduleStartMinute = "";
        form.scheduleStartAmpm = "";
        form.scheduleEnd = "";
        form.scheduleEndHour = "";
        form.scheduleEndMinute = "";
        form.scheduleEndAmpm = "";
        form.scheduleMessage = "";
    }

    SortFields();

    if(window["gform_before_update"]){
        form = window["gform_before_update"](form);
    }
}

function EndInsertForm(formId){
     jQuery("#please_wait_container").hide();

     jQuery("#edit_form_link").attr("href", "?page=gf_edit_forms&id=" + formId);
     jQuery("#notification_form_link").attr("href", "?page=gf_edit_forms&view=notification&id=" + formId);
     jQuery("#preview_form_link").attr("href", jQuery("#preview_form_link").attr("href").replace("{formid}",formId));

     jQuery("#after_insert_dialog").modal(
        {
        close:false,
        onOpen: function (dialog) {
          dialog.overlay.fadeIn('slow', function () {
            dialog.container.slideDown('slow', function () {
              dialog.data.fadeIn('slow');
            });
          });
        }});

}

function EndUpdateForm(formId){
    jQuery("#please_wait_container").hide();
    jQuery("#after_update_dialog").hide();
    jQuery("#after_update_error_dialog").hide();
    if(formId)
        jQuery("#after_update_dialog").slideDown();
    else
        jQuery("#after_update_error_dialog").slideDown();

    setTimeout(function(){jQuery('#after_update_dialog').slideUp();}, 5000);
}

function SortFields(){
    var fields = new Array();
    jQuery(".gfield").each(function(){
        id = this.id.substr(6);
        fields.push(GetFieldById(id));
    }
    );
    form.fields = fields;
}

function StartDeleteField(element){
    var fieldId = jQuery(element)[0].id.split("_")[2]
    DeleteField(fieldId);
}

function EndDeleteField(fieldId){

    var product_dependencies = new Array();
    var first_product = "";

    for(var i=0; i<form.fields.length; i++){

        //removing conditional logic rules that are based on the deleted field
        if(form.fields[i]["conditionalLogic"]){
            for(var j=0; j < form.fields[i]["conditionalLogic"]["rules"].length; j++){
                if(form.fields[i]["conditionalLogic"]["rules"][j]["fieldId"] == fieldId){
                    form.fields[i]["conditionalLogic"]["rules"].splice(j,1);
                }
            }

            if(form.fields[i]["conditionalLogic"]["rules"].length == 0)
                form.fields[i]["conditionalLogic"] = false;
        }

        //Getting first product and compiling a list of options and quantities dependent on this field
        if(form.fields[i]["type"] == "product" && form.fields[i]["id"] != fieldId && first_product == "")
            first_product = form.fields[i]["id"];

        if(form.fields[i]["productField"] == fieldId)
            product_dependencies.push(i);
    }

    //Updating all options and quantities that were linked to the deleted product so that the are linked to another product
    for(var i=0; i<product_dependencies.length; i++){
        form.fields[product_dependencies[i]]["productField"] = first_product;
    }

    //removing notification routing associated with this field
    if(form["notification"] && form["notification"]["routing"]){
        for(var j=0; j < form["notification"]["routing"].length; j++){
            if(form["notification"]["routing"][j]["fieldId"] == fieldId){
                form["notification"]["routing"].splice(j,1);
            }
        }

        if(form["notification"]["routing"].length == 0)
            form["notification"]["routing"] = null;
    }

    //removing field
    for(var i=0; i<form.fields.length; i++){
        if(form.fields[i].id == fieldId){

            //removing the field
            form.fields.splice(i, 1);

            //moving field_settings outside the field before it is deleted
            jQuery("#field_settings").insertBefore("#gform_fields");

            jQuery('#field_' + fieldId).fadeOut('slow',
                function(){
                    jQuery('#field_' + fieldId).remove();
                }
            );

            HideSettings("field_settings");
            break;
        }
    }
    TogglePageBreakSettings();
	
	jQuery(document).trigger('gform_field_deleted', [form, fieldId]);
}

function StartDuplicateField(element) {

    var sourcefieldId = jQuery(element)[0].id.split("_")[2];

    for(fieldIndex in form.fields){

        if(form.fields[fieldIndex].id == sourcefieldId) {

            // create a copy of the field
            var field = Copy(form.fields[fieldIndex]);
            field.id = GetNextFieldId();

            if(field.inputs != null) {

                var inputId = 1;

                for(inputIndex in field.inputs) {
                    var id = field.inputs[inputIndex]['id'] + "";
                    field.inputs[inputIndex]['id'] = id.replace(/(\d+\.)/, field.id + '.');
                    /*
                    if(inputId % 10 == 0)
                        inputId++;

                    field.inputs[inputIndex]['id'] = field.id + '.' + inputId;
                    inputId++;*/

                }
            }

            form.fields.splice(fieldIndex, 0, field);
            DuplicateField(field, sourcefieldId);
            return;
        }
    }
}

function EndDuplicateField(field, fieldString, sourceFieldId) {

    //sets up DOM for new field
    jQuery('#gform_fields li#field_' + sourceFieldId).after(fieldString);
    var newFieldElement = jQuery("#field_" + field.id);

    //highlighting animation
    newFieldElement.animate({ backgroundColor: '#FFFBCC' }, 'fast', function(){jQuery(this).animate({backgroundColor: '#FFF'}, 'fast', function(){jQuery(this).css('background-color', '');})})

    newFieldElement.bind("click", function(){FieldClick(this);});

    //Closing editors
    HideSettings("field_settings");
    HideSettings("form_settings");
    HideSettings("last_page_settings");

    TogglePageBreakSettings();

    InitializeFields();

}

function Copy(variable){

    if(!variable)
        return variable;
    else if(typeof variable != 'object')
        return variable;

    variable = jQuery.isArray(variable) ? variable.slice() : jQuery.extend({}, variable);

    for(i in variable) {
        variable[i] = Copy(variable[i]);
    }

    return variable;
}

function GetInputType(field){
    return field.inputType ? field.inputType : field.type;
}

function GetFieldsByType(types){
    var fields = new Array();
    for(var i=0; i<form["fields"].length; i++){
        if(IndexOf(types, form["fields"][i]["type"]) >= 0)
            fields.push(form["fields"][i]);
    }
    return fields;
}

function GetNextFieldId(){
    var max = 0;
    for(var i=0; i<form.fields.length; i++){
        if(parseFloat(form.fields[i].id) > max)
            max = parseFloat(form.fields[i].id);
    }
    return parseFloat(max) + 1;
}

function EndAddField(field, fieldString){

    //sets up DOM for new field
    jQuery("#gform_fields").append(fieldString);
    var newFieldElement = jQuery("#field_" + field.id);
    newFieldElement.animate({ backgroundColor: '#FFFBCC' }, 'fast', function(){jQuery(this).animate({backgroundColor: '#FFF'}, 'fast', function(){jQuery(this).css('background-color', '');})})
    newFieldElement.bind("click", function(){FieldClick(this);});

    //creates new javascript field
    form.fields.push(field);

    //Unselects all fields
    jQuery(".selectable").removeClass("field_selected");

    //Closing editors
    HideSettings("field_settings");
    HideSettings("form_settings");
    HideSettings("last_page_settings");

    //Select current field
    newFieldElement.addClass("field_selected");

    //initializes new field with default data
    SetFieldSize(field.size);

    TogglePageBreakSettings();

    InitializeFields();

    newFieldElement.removeClass("field_selected");

    UpdateDescriptionPlacement();
	
	jQuery(document).trigger('gform_field_added', [form, field]);
}

function StartChangeNameFormat(format){
    field = GetSelectedField();
    field["nameFormat"] = format;
    SetFieldProperty('nameFormat', format);
    jQuery("#field_settings").slideUp(function(){StartChangeInputType(field["type"], field);});
}

function StartChangeCaptchaType(captchaType){
    field = GetSelectedField();
    field["captchaType"] = captchaType;
    SetFieldProperty('captchaType', captchaType);
    jQuery("#field_settings").slideUp(function(){StartChangeInputType(field["type"], field);});
}

function StartChangeProductType(type){
    field = GetSelectedField();
    if(type == "singleproduct" || type == "hiddenproduct" || field["inputType"] == "calculation" )
        field["enablePrice"] = null;
    else
        field["enablePrice"] = true;

    return StartChangeInputType(type, field);
}

function StartChangeDonationType(type){
    field = GetSelectedField();
    if(type != "donation")
        field["enablePrice"] = true;
    else
        field["enablePrice"] = null;

    return StartChangeInputType(type, field);
}

function StartChangeShippingType(type){
    field = GetSelectedField();
    if(type != "singleshipping")
        field["enablePrice"] = true;

    return StartChangeInputType(type, field);
}

function StartChangePostCategoryType(type){

    if(type == 'dropdown') {

        jQuery('.post_category_initial_item_setting').hide();

    } else {

        jQuery('.post_category_initial_item_setting').show();

    }

    field = GetSelectedField();
    return StartChangeInputType(type, field);
}

function EndChangeInputType(fieldId, fieldType, fieldString){

    jQuery("#field_" + fieldId).html(fieldString);

    var field = GetFieldById(fieldId);

    //setting input type if different than field type
    field.inputType = field.type != fieldType ? fieldType : "";

    SetDefaultValues(field);

    SetFieldLabel(field.label);
    SetFieldSize(field.size);
    SetFieldDefaultValue(field.defaultValue);
    SetFieldDescription(field.description);
    SetFieldRequired(field.isRequired);
    InitializeFields();

    LoadFieldSettings();

    UpdateDescriptionPlacement();
}

function InitializeFields(){
    //Border on/off logic on mouse over
    jQuery(".selectable").hover(
      function () {
        jQuery(this).addClass("field_hover");
      },
      function () {
        jQuery(this).removeClass("field_hover");
      }
    );

    jQuery(".field_delete_icon, .field_duplicate_icon").bind("click", function(event){
        event.stopPropagation();
    });


    jQuery("#field_settings, #form_settings, #last_page_settings, #pagination_settings, .captcha_message, .form_delete_icon").bind("click", function(event){event.stopPropagation();});

    UpdateLabelPlacement(true);
}

function FieldClick(field){

    //disable click that happens right after dragging ends
    if(gforms_dragging == field.id){
        gforms_dragging = 0;
        return;
    }

    if(jQuery(field).hasClass("field_selected"))
    {
        var element_id = "";
        switch(field.id){
            case "gform_heading" :
                element_id = "#form_settings";
                jQuery('.gf_form_toolbar_settings a').removeClass("gf_toolbar_active");
            break;

            case "gform_last_page_settings" :
                element_id = "#last_page_settings";
            break;

            case "gform_pagination" :
                element_id = "#pagination_settings";
            break;

            default:
                element_id = "#field_settings";
        }
        jQuery(element_id).slideUp(function(){jQuery(field).removeClass("field_selected").addClass("field_hover"); HideSettings("field_settings");});

        return;
    }

    //unselects all fields
    jQuery(".selectable").removeClass("field_selected");

    //selects current field
    jQuery(field).removeClass("field_hover").addClass("field_selected");

    //if this is a field (not the form title), load appropriate field type settings
    if(field.id == "gform_heading"){

        //hide field settings
        HideSettings("field_settings");
        HideSettings("last_page_settings");
        HideSettings("pagination_settings");

        InitializeFormConditionalLogic();

        //Displaying form settings
        ShowSettings("form_settings");

        //highlighting toolbar item
        jQuery('.gf_form_toolbar_settings a').addClass("gf_toolbar_active");

    }
    else if(field.id == "gform_last_page_settings"){

        //hide field settings
        HideSettings("field_settings");
        HideSettings("form_settings");
        HideSettings("pagination_settings");

        //Displaying form settings
        ShowSettings("last_page_settings");
    }
    else if(field.id == "gform_pagination"){
               //hide field settings
        HideSettings("field_settings");
        HideSettings("form_settings");
        HideSettings("last_page_settings");

        InitPaginationOptions();

        //Displaying pagination settings
        ShowSettings("pagination_settings");

    }
    else{

        //hide form settings
        HideSettings("form_settings");
        HideSettings("last_page_settings");
        HideSettings("pagination_settings");

        //selects current field
        LoadFieldSettings();
    }
}

function TogglePercentageStyle(isInit){
    var speed = isInit ? "" : "slow";

    if(jQuery("#percentage_style").val() == 'custom'){
        jQuery('.percentage_custom_container').show(speed);
    }
    else{
        jQuery('.percentage_custom_container').hide(speed);
    }
}

function TogglePercentageConfirmationText(isInit){
    var speed = isInit ? "" : "slow";

    if(jQuery("#percentage_confirmation_display").is(":checked")){
        jQuery('.percentage_confirmation_page_name_setting').show(speed);
    }
    else{
        jQuery('.percentage_confirmation_page_name_setting').hide(speed);
    }
}

function CustomFieldExists(name){
    if(!name)
        return true;

    var options = jQuery("#field_custom_field_name_select option");
    for(var i=0; i<options.length; i++)
    {
        if(options[i].value == name)
            return true;
    }
    return false;
}

function IsStandardMask(value){
    if(!value)
        return true;

    var options = jQuery("#field_mask_select option");
    for(var i=0; i<options.length; i++)
    {
        if(options[i].value == value)
            return true;
    }
    return false;
}

function InsertRule(objectType, ruleIndex){
    var obj = GetConditionalObject(objectType);
    obj.conditionalLogic.rules.splice(ruleIndex, 0, new ConditionalRule());
    CreateConditionalLogic(objectType, obj);
    SetRule(objectType, ruleIndex);
}

function SetRule(objectType, ruleIndex){
    SetRuleProperty(objectType, ruleIndex, "fieldId", jQuery("#" + objectType + "_rule_field_" + ruleIndex).val());
    SetRuleProperty(objectType, ruleIndex, "operator", jQuery("#" + objectType + "_rule_operator_" + ruleIndex).val());
    SetRuleProperty(objectType, ruleIndex, "value", jQuery("#" + objectType + "_rule_value_" + ruleIndex).val());
}

function DeleteRule(objectType, ruleIndex){
    var obj = GetConditionalObject(objectType);
    obj.conditionalLogic.rules.splice(ruleIndex, 1);
    CreateConditionalLogic(objectType, obj);
}

function GetRuleFields(objectType, ruleIndex, selectedFieldId){
    var str = "<select id='" + objectType + "_rule_field_" + ruleIndex + "' class='gfield_rule_select' onchange='jQuery(\"#" + objectType + "_rule_value_" + ruleIndex + "\").replaceWith(GetRuleValues(\"" + objectType + "\", " + ruleIndex + ", jQuery(this).val())); SetRule(\"" + objectType + "\", " + ruleIndex + "); '>";
    var inputType;
    for(var i=0; i<form.fields.length; i++){
        if(IsConditionalLogicField(form.fields[i])){
            var selected = form.fields[i].id == selectedFieldId ? "selected='selected'" : "";
            var label = form.fields[i].adminLabel ? form.fields[i].adminLabel : form.fields[i].label
            str += "<option value='" + form.fields[i].id + "' " + selected + ">" + label + "</option>";
        }
    }
    str += "</select>";
    return str;
}

function IsConditionalLogicField(field){
    inputType = field.inputType ? field.inputType : field.type;
    var supported_fields = ["checkbox", "radio", "select", "text", "website", "textarea", "email", "hidden", "number", "phone", "multiselect", "post_title",
                            "post_tags", "post_custom_field", "post_content", "post_excerpt"];

    var index = jQuery.inArray(inputType, supported_fields);

    return index >= 0;
}

function TruncateRuleText(text){
    if(!text || text.length <= 18)
        return text;

    return text.substr(0, 9) + "..." + text.substr(text.length -8, 9);

}

function GetFirstRuleField(){
    var inputType;
    for(var i=0; i<form.fields.length; i++){
        if(IsConditionalLogicField(form.fields[i]))
            return form.fields[i].id;
    }

    return 0;
}

function GetRuleValues(objectType, ruleIndex, selectedFieldId, selectedValue){

    if(selectedFieldId == 0)
        selectedFieldId = GetFirstRuleField();

    if(selectedFieldId == 0)
        return "";

    var isAnySelected = false;
    var field = GetFieldById(selectedFieldId);

    if(!field)
        return "";

    var str = "";

    if(field["type"] == "post_category" && field["displayAllCategories"]){

        var dropdown_id = objectType + '_rule_value_' + ruleIndex;
        var dropdown = jQuery('#' + dropdown_id + ".gfield_category_dropdown");

        //don't load category drop down if it already exists (to avoid unecessary ajax requests)
        if(dropdown.length > 0){

            var options = dropdown.html();
            options = options.replace("value=\"" + selectedValue + "\"", "value=\"" + selectedValue + "\" selected=\"selected\"");
            str = "<select id='" + dropdown_id + "' class='gfield_rule_select gfield_rule_value_dropdown gfield_category_dropdown'>" + options + "</select>";
        }
        else{
            //loading categories via AJAX
            jQuery.post(ajaxurl,{   action:"gf_get_post_categories",
                                    objectType: objectType,
                                    ruleIndex: ruleIndex,
                                    selectedValue: selectedValue},
                                function(dropdown_string){
                                    if(dropdown_string){
                                        jQuery('#gfield_ajax_placeholder_' + ruleIndex).replaceWith(dropdown_string.trim());

                                        SetRuleProperty(objectType, ruleIndex, "value", jQuery("#" + dropdown_id).val());
                                    }
                                }
                        );

            //will be replaced by real drop down during the ajax callback
            str = "<select id='gfield_ajax_placeholder_" + ruleIndex + "' class='gfield_rule_select'><option>" + gf_vars["loading"] + "</option></select>";
        }
    }
    else if(field.choices){
        str = GetRuleValuesDropDown(field.choices, objectType, ruleIndex, selectedValue);
    }
    else{
        selectedValue = selectedValue ? selectedValue.replace(/'/g, "&#039;") : "";

        //create a text field for fields that don't have choices (i.e text, textarea, number, email, etc...)
        str = "<input type='text' placeholder='" + gf_vars["enterValue"] + "' class='gfield_rule_select' id='" + objectType + "_rule_value_" + ruleIndex + "' value='" + selectedValue.replace(/'/g, "&#039;") + "' onchange='SetRuleProperty(\"" + objectType + "\", " + ruleIndex + ", \"value\", jQuery(this).val());' onkeyup='SetRuleProperty(\"" + objectType + "\", " + ruleIndex + ", \"value\", jQuery(this).val());'>";
    }

    return str;
}

function GetRuleValuesDropDown(choices, objectType, ruleIndex, selectedValue){
    //create a drop down for fields that have choices (i.e. drop down, radio, checkboxes, etc...)
    str = "<select class='gfield_rule_select gfield_rule_value_dropdown' id='" + objectType + "_rule_value_" + ruleIndex + "'>";

    var isAnySelected = false;
    for(var i=0; i<choices.length; i++){
        var choiceValue = typeof choices[i].value == "undefined" || choices[i].value == null ? choices[i].text + '' : choices[i].value + '';
        var isSelected = choiceValue == selectedValue;
        var selected = isSelected ? "selected='selected'" : "";
        if(isSelected)
            isAnySelected = true;

        str += "<option value='" + choiceValue.replace(/'/g, "&#039;") + "' " + selected + ">" + choices[i].text + "</option>";
    }

    if(!isAnySelected && selectedValue && selectedValue != "")
        str += "<option value='" + selectedValue.replace(/'/g, "&#039;") + "' selected='selected'>" + selectedValue + "</option>";

    str += "</select>";

    return str;

}
function SetRuleProperty(objectType, ruleIndex, name, value){
    var obj = GetConditionalObject(objectType);
    obj.conditionalLogic.rules[ruleIndex][name] = value;
}

function SetConditionalProperty(objectType, name, value){
    var obj = GetConditionalObject(objectType);
    obj.conditionalLogic[name] = value;
}

function LoadFieldChoices(field){

    //loading ui
    jQuery('#field_choice_values_enabled').attr("checked", field.enableChoiceValue ? true : false);
    ToggleChoiceValue();
    var container_name = GetInputType(field) == "list" ? "field_columns" : "field_choices";
    jQuery("#" + container_name).html(GetFieldChoices(field));

    //loading bulk input
    LoadBulkChoices(field);

	jQuery(document).trigger('gform_load_field_choices', [field]);
}
function LoadBulkChoices(field){
    LoadCustomChoices();

    if(!field.choices)
        return;

    var choices = new Array();
    var choice;
    for(var i=0; i<field.choices.length; i++){
        choice = field.choices[i].text == field.choices[i].value ? field.choices[i].text : field.choices[i].text + "|" + field.choices[i].value;
        if(field.enablePrice && field.choices[i]["price"] != "")
            choice += "|:" + field.choices[i]["price"];

        choices.push(choice);
    }

    jQuery("#gfield_bulk_add_input").val(choices.join("\n"));
}

function DisplayCustomMessage(message){

    jQuery("#bulk_custom_message").html(message).slideDown();

    //slide up after 2 seconds
    setTimeout(
        function(){
            jQuery("#bulk_custom_message").slideUp();
        }, 2000
        );
}

function LoadCustomChoices(){

    jQuery(".choice_section_header, .bulk_custom_choice").remove();

    if(!IsEmpty(gform_custom_choices)){
        var str = "<li class='choice_section_header'>Custom Choices</li>";
        for(key in gform_custom_choices){
            str += "<li class='bulk_custom_choice'><a href='#' onclick='SelectCustomChoice(\"" + key + "\");' class='bulk-choice bulk_custom_choice'>" + key + "</a></li>";
        }
        str += "<li class='choice_section_header'>Predefined Choices</li>";
        jQuery("#bulk_items").prepend(str);
    }
}

function SelectCustomChoice(name){

    jQuery("#gfield_bulk_add_input").val(gform_custom_choices[name].join("\n"));
    gform_selected_custom_choice = name;
    InitBulkCustomPanel();
}

function SelectPredefinedChoice(name){
    jQuery('#gfield_bulk_add_input').val(gform_predefined_choices[name].join('\n'));
    gform_selected_custom_choice = "";
    InitBulkCustomPanel();
}

function InsertBulkChoices(choices){
    field = GetSelectedField();
    field.choices = new Array();

    var enableValue = false;
    for(var i=0; i<choices.length; i++){
        text_price = choices[i].split("|:");

        text_value = text_price[0];
        price = "";
        if(text_price.length > 1){
            var currency = GetCurrentCurrency();
            price = currency.toMoney(text_price[1]);
        }

        text_value = text_value.split("|");
        field.choices.push(new Choice(jQuery.trim(text_value[0]), jQuery.trim(text_value[text_value.length -1]), jQuery.trim(price)));

        if(text_value.length > 1)
            enableValue = true;
    }

    if(enableValue){
        field["enableChoiceValue"] = true;
        jQuery('#field_choice_values_enabled').attr("checked", true);
        ToggleChoiceValue();
    }

    LoadFieldChoices(field);
    UpdateFieldChoices(field.type);
}

function InitBulkCustomPanel(){
    if(gform_selected_custom_choice.length == 0){
        CloseCustomChoicesPanel();
    }
    else{
        LoadCustomChoicesPanel();
    }
}

function LoadCustomChoicesPanel(isNew, speed){
    if(isNew){
        jQuery("#custom_choice_name").val("");
        jQuery("#bulk_save_button").html(gf_vars.save);
        jQuery("#bulk_cancel_link").show();
        jQuery("#bulk_delete_link").hide();
    }
    else{
        jQuery("#custom_choice_name").val(gform_selected_custom_choice);
        jQuery("#bulk_save_button").html(gf_vars.update);
        jQuery("#bulk_cancel_link").hide();
        jQuery("#bulk_delete_link").show();
    }
    if(!speed)
        speed = '';

    jQuery("#bulk_save_as").hide(speed);
    jQuery("#bulk_custom_edit").show(speed);
}

function CloseCustomChoicesPanel(speed){
    if(!speed)
        speed = '';

    jQuery("#bulk_save_as").show(speed);
    jQuery("#bulk_custom_edit").hide(speed);
}

function IsEmpty(array){
    var key;
    for (key in array) {
        if (array.hasOwnProperty(key))
            return false;
    }
    return true;
}

function SetFieldChoice(inputType, index){
    text = jQuery("#" + inputType + "_choice_text_" + index).val();
    value = jQuery("#" + inputType + "_choice_value_" + index).val();
    price = jQuery("#" + inputType + "_choice_price_" + index).val();

    var element = jQuery("#" + inputType + "_choice_selected_" + index);
    isSelected = element.is(":checked");

    field = GetSelectedField();

    field.choices[index].text = text;
    field.choices[index].value = field.enableChoiceValue ? value : text;

    if(field.enablePrice){
        var currency = GetCurrentCurrency();
        var price = currency.toMoney(price);
        if(!price)
            price = "";

        field.choices[index]["price"] = price;
        jQuery("#" + inputType + "_choice_price_" + index).val(price);
    }

    //set field selections
    jQuery("#field_choices :radio, #field_choices :checkbox").each(function(index){
        field.choices[index].isSelected = this.checked;
    });

    LoadBulkChoices(field);

    UpdateFieldChoices(GetInputType(field));
}

function UpdateFieldChoices(fieldType){
    var choices = '';
    var selector = '';

    if(fieldType == "checkbox")
        field.inputs = new Array();

    var skip = 0;

    switch(GetInputType(field)){
        case "select" :
            for(var i=0; i<field.choices.length; i++)
            {
                selected = field.choices[i].isSelected ? "selected='selected'" : "";
                var choiceValue = field.choices[i].value ? field.choices[i].value : field.choices[i].text;
                choices += "<option value='" + choiceValue.replace(/'/g, "&#039;") + "' " + selected + ">" + field.choices[i].text + "</option>";
            }
        break;

        case "checkbox" :
            for(var i=0; i<field.choices.length; i++)
            {
                //Skipping ids that are multiple of ten to avoid conflicts with other fields (i.e. 5.1 and 5.10)
                if((i + 1 + skip) % 10 == 0){
                    skip++;
                }
                var field_number = field.id + '.' + (i + 1 + skip);
                field.inputs.push(new Input(field_number, field.choices[i].text));

                var id = 'choice_' + field.id + '_' + i;
                checked = field.choices[i].isSelected ? "checked" : "";

                if(i < 5)
                    choices += "<li><input type='" + fieldType + "' " + checked + " id='" + id +"' disabled='disabled'><label for='" + id + "'>" + field.choices[i].text + "</label></li>";
            }
            if(field.choices.length > 5)
                choices += "<li class='gchoice_total'>" + gf_vars["editToViewAll"].replace("%d", field.choices.length) + "</li>";
        break;

        case "radio" :
            for(var i=0; i<field.choices.length; i++)
            {
                var id = 'choice_' + field.id + '_' + i;
                checked = field.choices[i].isSelected ? "checked" : "";
                if(i < 5)
                    choices += "<li><input type='" + fieldType + "' " + checked + " id='" + id +"' disabled='disabled'><label for='" + id + "'>" + field.choices[i].text + "</label></li>";
            }

            choices += field.enableOtherChoice ? "<li><input type='" + fieldType + "' " + checked + " id='" + id +"' disabled='disabled'><input type='text' value='" + gf_vars.otherChoiceValue + "'  disabled='disabled' /></li>" : "";

            if(field.choices.length > 5)
                choices += "<li class='gchoice_total'>" + gf_vars["editToViewAll"].replace("%d", field.choices.length) + "</li>";

        break;

        case "list" :

            var has_columns = field["choices"] != null;
            columns = has_columns ? field["choices"] : [[]];
            var class_attr = "";
            if(has_columns){
                choices += "<thead><tr>";
                for(var i=0; i<columns.length; i++){
                    choices += "<th>" + columns[i]["text"] + "</th>";
                }
                choices += "<th>&nbsp;</th></tr></thead>";
            }
            else{
                class_attr = "class='gf_list_one_column'";
            }

            choices += "<tbody>";

            choices += "<tr>";
            for(var j=0; j<columns.length; j++){
                choices += "<td " + class_attr +"><input type='text' disabled='disabled'  /></td>";
            }
            choices +="<td><img src='" + gf_vars["baseUrl"] + "/images/add.png' class='add_list_item' style='cursor:pointer; margin:0 3px;' /></td>";
            choices +="</tr></tbody>";

        break;
    }

    selector = '.gfield_' + fieldType;
    jQuery(".field_selected " + selector).html(choices);
}

function InsertFieldChoice(index){
    field = GetSelectedField();

    var price = field["enablePrice"] ? "0.00" : "";
    var new_choice = new Choice("", "", price);
    if(window["gform_new_choice_" + field.type])
        new_choice = window["gform_new_choice_" + field.type](field, new_choice);

    field.choices.splice(index, 0, new_choice);

    LoadFieldChoices(field);
    UpdateFieldChoices(GetInputType(field));
}

function DeleteFieldChoice(index){
    field = GetSelectedField();
    field.choices.splice(index, 1);
    LoadFieldChoices(field);
    UpdateFieldChoices(GetInputType(field));
}

function MoveFieldChoice(fromIndex, toIndex){
    field = GetSelectedField();
    var choice = field.choices[fromIndex];

    //deleting from old position
    field.choices.splice(fromIndex, 1);

    //inserting into new position
    field.choices.splice(toIndex, 0, choice);

    LoadFieldChoices(field);
    UpdateFieldChoices(GetInputType(field));
}

function GetFieldType(fieldId){
    return fieldId.substr(0, fieldId.lastIndexOf("_"));
}

function GetSelectedField(){
    var id = jQuery(".field_selected")[0].id.substr(6);
    return GetFieldById(id);
}

function GetFieldById(id){
    for(var i=0; i<form.fields.length; i++){
        if(form.fields[i].id == id)
            return form.fields[i];
    }
    return null;
}

function SetPasswordProperty(isChecked){
    SetFieldProperty("enablePasswordInput", isChecked);
}

function ToggleDateCalendar(isInit){

    var speed = isInit ? "" : "slow";
    var dateType = jQuery("#field_date_input_type").val();
    if(dateType == "datefield" || dateType == "datedropdown"){
        jQuery("#date_picker_container").hide(speed);
        SetCalendarIconType("none");
    }
    else{
        jQuery("#date_picker_container").show(speed);
    }
}

function ToggleCalendarIconUrl(isInit){
    var speed = isInit ? "" : "slow";

    if(jQuery("#gsetting_icon_custom").is(":checked")){
        jQuery("#gfield_icon_url_container").show(speed);
    }
    else{
        jQuery("#gfield_icon_url_container").hide(speed);
        jQuery("#gfield_calendar_icon_url").val("");
        SetFieldProperty('calendarIconUrl', '');
    }
}

function SetTimeFormat(format){
    SetFieldProperty('timeFormat', format);
    LoadTimeInputs();
}

function LoadTimeInputs(){
    var format = jQuery("#field_time_format").val();
    if(format == "24")
        jQuery(".field_selected .gfield_time_ampm").hide();
    else
        jQuery(".field_selected .gfield_time_ampm").show();
}

function SetDateFormat(format){
    SetFieldProperty('dateFormat', format);
    LoadDateInputs();
}

function LoadDateInputs(){
    var type = jQuery("#field_date_input_type").val();
    var format = jQuery("#field_date_format").val();

    //setting up field positions
    var position = format ? format.substr(0,3) : "mdy";

    if(type == "datefield"){
        switch(position){
            case "ymd" :
                jQuery(".field_selected #gfield_input_date_month").remove().insertBefore(".field_selected #gfield_input_date_day");
                jQuery(".field_selected #gfield_input_date_year").remove().insertBefore(".field_selected #gfield_input_date_month");
            break;

            case "mdy" :
                jQuery(".field_selected #gfield_input_date_day").remove().insertBefore(".field_selected #gfield_input_date_year");
                jQuery(".field_selected #gfield_input_date_month").remove().insertBefore(".field_selected #gfield_input_date_day");
            break;

            case "dmy" :
                jQuery(".field_selected #gfield_input_date_month").remove().insertBefore(".field_selected #gfield_input_date_year");
                jQuery(".field_selected #gfield_input_date_day").remove().insertBefore(".field_selected #gfield_input_date_month");
            break;
        }

        jQuery(".field_selected .ginput_date").show();
        jQuery(".field_selected .ginput_date_dropdown").hide();
        jQuery(".field_selected #gfield_input_datepicker").hide();
        jQuery(".field_selected #gfield_input_datepicker_icon").hide();
    }
    else if(type == "datedropdown"){

        switch(position){
            case "ymd" :
                jQuery(".field_selected #gfield_dropdown_date_month").remove().insertBefore(".field_selected #gfield_dropdown_date_day");
                jQuery(".field_selected #gfield_dropdown_date_year").remove().insertBefore(".field_selected #gfield_dropdown_date_month");
            break;

            case "mdy" :
                jQuery(".field_selected #gfield_dropdown_date_day").remove().insertBefore(".field_selected #gfield_dropdown_date_year");
                jQuery(".field_selected #gfield_dropdown_date_month").remove().insertBefore(".field_selected #gfield_dropdown_date_day");
            break;

            case "dmy" :
                jQuery(".field_selected #gfield_dropdown_date_month").remove().insertBefore(".field_selected #gfield_dropdown_date_year");
                jQuery(".field_selected #gfield_dropdown_date_day").remove().insertBefore(".field_selected #gfield_dropdown_date_month");
            break;
        }

        jQuery(".field_selected .ginput_date_dropdown").css("display", "inline");
        jQuery(".field_selected .ginput_date").hide();
        jQuery(".field_selected #gfield_input_datepicker").hide();
        jQuery(".field_selected #gfield_input_datepicker_icon").hide();
    }
    else{
        jQuery(".field_selected .ginput_date").hide();
        jQuery(".field_selected .ginput_date_dropdown").hide();
        jQuery(".field_selected #gfield_input_datepicker").show();

        //Displaying or hiding the calendar icon
        if(jQuery("#gsetting_icon_calendar").is(":checked"))
            jQuery(".field_selected #gfield_input_datepicker_icon").show();
        else
            jQuery(".field_selected #gfield_input_datepicker_icon").hide();
    }


}

function SetCalendarIconType(iconType, isInit){
    field = GetSelectedField();
    if(GetInputType(field) != "date")
        return;

    if(iconType == undefined)
        iconType = "none";

    if(iconType == "none")
        jQuery("#gsetting_icon_none").attr("checked", true);
    else if(iconType == "calendar")
        jQuery("#gsetting_icon_calendar").attr("checked", true);
    else if(iconType == "custom")
        jQuery("#gsetting_icon_custom").attr("checked", true);

    SetFieldProperty('calendarIconType', iconType);
    ToggleCalendarIconUrl(isInit);
    LoadDateInputs();
}

function SetDateInputType(type){
    field = GetSelectedField();
    if(GetInputType(field) != "date")
        return;

    SetFieldProperty('dateType', type);
    ToggleDateCalendar();
    LoadDateInputs();
}

function SetPostImageMeta(){
    var displayTitle = jQuery('.field_selected #gfield_display_title').is(":checked");
    var displayCaption = jQuery('.field_selected #gfield_display_caption').is(":checked");
    var displayDescription = jQuery('.field_selected #gfield_display_description').is(":checked");
    var displayLabel = (displayTitle || displayCaption || displayDescription);

    //setting property
    SetFieldProperty('displayTitle', displayTitle);
    SetFieldProperty('displayCaption', displayCaption);
    SetFieldProperty('displayDescription', displayDescription);

    //updating UI
    jQuery('.field_selected .ginput_post_image_title').css("display", displayTitle ? "block" : "none");
    jQuery('.field_selected .ginput_post_image_caption').css("display", displayCaption ? "block" : "none");
    jQuery('.field_selected .ginput_post_image_description').css("display", displayDescription ? "block" : "none");
    jQuery('.field_selected .ginput_post_image_file').css("display", displayLabel ? "block" : "none");
}

function SetFeaturedImage() {

    var isChecked = jQuery('#gfield_featured_image').is(':checked');

    if(isChecked) {

        for(i in form.fields) {
            form.fields[i].postFeaturedImage = false;
        }

        SetFieldProperty('postFeaturedImage', true);
    }
    else{
        SetFieldProperty('postFeaturedImage', false);
    }
}




function SetFieldProperty(name, value){
    if(value == undefined)
        value = "";

    GetSelectedField()[name] = value;
}

function SetInputName(value, inputId){
    var field = GetSelectedField();

    if(value)
        value = value.trim();

    if(!inputId){
        field["inputName"] = value;
    }
    else{
        for(var i=0; i<field["inputs"].length; i++){
            if(field["inputs"][i]["id"] == inputId){
                field["inputs"][i]["name"] = value;
            }
        }
    }
}


function SetSelectedCategories(){
    var field = GetSelectedField();
    field["choices"] = new Array();

    jQuery(".gfield_category_checkbox").each(function(){
        if(this.checked)
            field["choices"].push(new Choice(this.name, this.value));
    });

    field["choices"].sort(function(a, b){return ( a["text"].toLowerCase() > b["text"].toLowerCase() );});
}

function SetFieldLabel(label){
    var requiredElement = jQuery(".field_selected .gfield_required")[0];
    jQuery(".field_selected .gfield_label, .field_selected .gsection_title").text(label).append(requiredElement);
    SetFieldProperty("label", label);
}

function SetCaptchaTheme(theme, thumbnailUrl){
    jQuery(".field_selected .gfield_captcha").attr("src", thumbnailUrl);
    SetFieldProperty("captchaTheme", theme);
}


function SetCaptchaSize(size){
    var type = jQuery("#field_captcha_type").val();
    SetFieldProperty("simpleCaptchaSize", size);
    RedrawCaptcha();
    jQuery(".field_selected .gfield_captcha_input_container").removeClass(type + "_small").removeClass(type + "_medium").removeClass(type + "_large").addClass(type + "_" + size);
}

function SetCaptchaFontColor(color){
    SetFieldProperty("simpleCaptchaFontColor", color);
    RedrawCaptcha();
}

function SetCaptchaBackgroundColor(color){
    SetFieldProperty("simpleCaptchaBackgroundColor", color);
    RedrawCaptcha();
}

function RedrawCaptcha(){
    var captchaType = jQuery("#field_captcha_type").val();

    if(captchaType == "math"){
        url_1 = GetCaptchaUrl(1);
        url_2 = GetCaptchaUrl(2);
        url_3 = GetCaptchaUrl(3);
        jQuery(".field_selected .gfield_captcha:eq(0)").attr("src", url_1);
        jQuery(".field_selected .gfield_captcha:eq(1)").attr("src", url_2);
        jQuery(".field_selected .gfield_captcha:eq(2)").attr("src", url_3);
    }
    else{
        url = GetCaptchaUrl();
        jQuery(".field_selected .gfield_captcha").attr("src", url);
    }
}

function SetFieldSize(size){
    jQuery(".field_selected .small, .field_selected .medium, .field_selected .large").removeClass("small").removeClass("medium").removeClass("large").addClass(size);
    SetFieldProperty("size", size);
}

function SetFieldAdminOnly(isAdminOnly){
    SetFieldProperty('adminOnly', isAdminOnly);
    if(isAdminOnly)
        jQuery(".field_selected").addClass("field_admin_only");
    else
        jQuery(".field_selected").removeClass("field_admin_only");
}


function SetFieldDefaultValue(defaultValue){
    jQuery(".field_selected > div > input, .field_selected > div > textarea").val(defaultValue);
    SetFieldProperty('defaultValue', defaultValue);
}

function SetFieldDescription(description){
    if(description == undefined)
        description = "";

    jQuery(".field_selected .gfield_description, .field_selected .gsection_description").html(description);

    SetFieldProperty('description', description);
}

function SetPasswordStrength(isEnabled){
    if(isEnabled){
        jQuery(".field_selected .gfield_password_strength").show();
    }
    else{
        jQuery(".field_selected .gfield_password_strength").hide();

        //resetting min strength
        jQuery("#gfield_min_strength").val("");
        SetFieldProperty('minPasswordStrength', "");
    }

    SetFieldProperty('passwordStrengthEnabled', isEnabled);
}

function SetEmailConfirmation(isEnabled){
    if(isEnabled){
        jQuery(".field_selected .ginput_single_email").hide();
        jQuery(".field_selected .ginput_confirm_email").show();
    }
    else{
        jQuery(".field_selected .ginput_confirm_email").hide();
        jQuery(".field_selected .ginput_single_email").show();
    }

    SetFieldProperty('emailConfirmEnabled', isEnabled);
    UpdateDescriptionPlacement();
}


function SetCardType(elem, value) {

    var cards = GetSelectedField()['creditCards'] ? GetSelectedField()['creditCards'] : new Array();

    if(jQuery(elem).is(':checked')) {

        if(jQuery.inArray(value, cards) == -1) {
            jQuery('.gform_card_icon_' + value).fadeIn();
            cards[cards.length] = value;
        }

    } else {

        var index = jQuery.inArray(value, cards);

        if(index != -1) {
            jQuery('.gform_card_icon_' + value).fadeOut();
            cards.splice(index, 1);
        }

    }

    SetFieldProperty('creditCards', cards);
}


function SetFieldRequired(isRequired){
    var required = isRequired ? "*" : "";
    jQuery(".field_selected .gfield_required").html(required);
    SetFieldProperty('isRequired', isRequired);
}

function IndexOf(ary, item){
    for(var i=0; i<ary.length; i++)
        if(ary[i] == item)
            return i;

    return -1;
}

function DefaultValueCallback(){
    SetFieldDefaultValue(jQuery('#field_default_value').val())
}

function HtmlContentCallback(){
    SetFieldProperty('content', jQuery('#field_content').val())
}



function ToggleCalculationOptions(isEnabled, field) {

    if(isEnabled) {

        jQuery('#calculation_options').gfSlide('down');
        if(field.type != 'product')
            jQuery('li.range_setting').gfSlide('up');

    } else {

        jQuery('#calculation_options').gfSlide('up');
        if(field.type != 'product')
            jQuery('li.range_setting').gfSlide('down');

        SetFieldProperty('calculationFormula', '');
        SetFieldProperty('calculationRounding', '');

    }

    SetFieldProperty('enableCalculation', isEnabled);
}

function FormulaContentCallback() {
    SetFieldProperty('calculationFormula', jQuery('#field_calculation_formula').val());
}


//------------------------------------------------------------------------------------------------------------------------
//Color Picker
function iColorShow(mouseX, mouseY, id, callback){
    jQuery("#iColorPicker").css({'top': (mouseY - 150) +"px",'left':mouseX +"px",'position':'absolute'}).fadeIn("fast");
    jQuery("#iColorPickerBg").css({'position':'absolute','top':0,'left':0,'width':'100%','height':'100%'}).fadeIn("fast");
    var def=jQuery("#"+id).val();
    jQuery('#colorPreview span').text(def);
    jQuery('#colorPreview').css('background',def);
    jQuery('#color').val(def);
    var hxs=jQuery('#iColorPicker');
    for(i=0;i<hxs.length;i++){
        var tbl=document.getElementById('hexSection'+i);
        var tblChilds=tbl.childNodes;
        for(j=0;j<tblChilds.length;j++){
            var tblCells=tblChilds[j].childNodes;
            for(k=0;k<tblCells.length;k++){
                jQuery(tblChilds[j].childNodes[k]).unbind().mouseover(
                    function(a){var aaa="#"+jQuery(this).attr('hx');jQuery('#colorPreview').css('background',aaa);jQuery('#colorPreview span').text(aaa)}
                ).click(function(){
                    var aaa="#"+jQuery(this).attr('hx');
                    jQuery("#"+id).val(aaa);
                    jQuery("#chip_"+id).css("background-color",aaa);
                    jQuery("#iColorPickerBg").hide();
                    jQuery("#iColorPicker").fadeOut();
                    if(callback)
                        window[callback](aaa);
                    jQuery(this)})
            }
        }
    }
}
this.iColorPicker=function(){
    jQuery("input.iColorPicker").each(function(i){if(i==0){jQuery(document.createElement("div")).attr("id","iColorPicker").css('display','none').html('<table class="pickerTable" id="pickerTable0"><thead id="hexSection0"><tr><td style="background:#f00;" hx="f00"></td><td style="background:#ff0" hx="ff0"></td><td style="background:#0f0" hx="0f0"></td><td style="background:#0ff" hx="0ff"></td><td style="background:#00f" hx="00f"></td><td style="background:#f0f" hx="f0f"></td><td style="background:#fff" hx="fff"></td><td style="background:#ebebeb" hx="ebebeb"></td><td style="background:#e1e1e1" hx="e1e1e1"></td><td style="background:#d7d7d7" hx="d7d7d7"></td><td style="background:#cccccc" hx="cccccc"></td><td style="background:#c2c2c2" hx="c2c2c2"></td><td style="background:#b7b7b7" hx="b7b7b7"></td><td style="background:#acacac" hx="acacac"></td><td style="background:#a0a0a0" hx="a0a0a0"></td><td style="background:#959595" hx="959595"></td></tr><tr><td style="background:#ee1d24" hx="ee1d24"></td><td style="background:#fff100" hx="fff100"></td><td style="background:#00a650" hx="00a650"></td><td style="background:#00aeef" hx="00aeef"></td><td style="background:#2f3192" hx="2f3192"></td><td style="background:#ed008c" hx="ed008c"></td><td style="background:#898989" hx="898989"></td><td style="background:#7d7d7d" hx="7d7d7d"></td><td style="background:#707070" hx="707070"></td><td style="background:#626262" hx="626262"></td><td style="background:#555" hx="555"></td><td style="background:#464646" hx="464646"></td><td style="background:#363636" hx="363636"></td><td style="background:#262626" hx="262626"></td><td style="background:#111" hx="111"></td><td style="background:#000" hx="000"></td></tr><tr><td style="background:#f7977a" hx="f7977a"></td><td style="background:#fbad82" hx="fbad82"></td><td style="background:#fdc68c" hx="fdc68c"></td><td style="background:#fff799" hx="fff799"></td><td style="background:#c6df9c" hx="c6df9c"></td><td style="background:#a4d49d" hx="a4d49d"></td><td style="background:#81ca9d" hx="81ca9d"></td><td style="background:#7bcdc9" hx="7bcdc9"></td><td style="background:#6ccff7" hx="6ccff7"></td><td style="background:#7ca6d8" hx="7ca6d8"></td><td style="background:#8293ca" hx="8293ca"></td><td style="background:#8881be" hx="8881be"></td><td style="background:#a286bd" hx="a286bd"></td><td style="background:#bc8cbf" hx="bc8cbf"></td><td style="background:#f49bc1" hx="f49bc1"></td><td style="background:#f5999d" hx="f5999d"></td></tr><tr><td style="background:#f16c4d" hx="f16c4d"></td><td style="background:#f68e54" hx="f68e54"></td><td style="background:#fbaf5a" hx="fbaf5a"></td><td style="background:#fff467" hx="fff467"></td><td style="background:#acd372" hx="acd372"></td><td style="background:#7dc473" hx="7dc473"></td><td style="background:#39b778" hx="39b778"></td><td style="background:#16bcb4" hx="16bcb4"></td><td style="background:#00bff3" hx="00bff3"></td><td style="background:#438ccb" hx="438ccb"></td><td style="background:#5573b7" hx="5573b7"></td><td style="background:#5e5ca7" hx="5e5ca7"></td><td style="background:#855fa8" hx="855fa8"></td><td style="background:#a763a9" hx="a763a9"></td><td style="background:#ef6ea8" hx="ef6ea8"></td><td style="background:#f16d7e" hx="f16d7e"></td></tr><tr><td style="background:#ee1d24" hx="ee1d24"></td><td style="background:#f16522" hx="f16522"></td><td style="background:#f7941d" hx="f7941d"></td><td style="background:#fff100" hx="fff100"></td><td style="background:#8fc63d" hx="8fc63d"></td><td style="background:#37b44a" hx="37b44a"></td><td style="background:#00a650" hx="00a650"></td><td style="background:#00a99e" hx="00a99e"></td><td style="background:#00aeef" hx="00aeef"></td><td style="background:#0072bc" hx="0072bc"></td><td style="background:#0054a5" hx="0054a5"></td><td style="background:#2f3192" hx="2f3192"></td><td style="background:#652c91" hx="652c91"></td><td style="background:#91278f" hx="91278f"></td><td style="background:#ed008c" hx="ed008c"></td><td style="background:#ee105a" hx="ee105a"></td></tr><tr><td style="background:#9d0a0f" hx="9d0a0f"></td><td style="background:#a1410d" hx="a1410d"></td><td style="background:#a36209" hx="a36209"></td><td style="background:#aba000" hx="aba000"></td><td style="background:#588528" hx="588528"></td><td style="background:#197b30" hx="197b30"></td><td style="background:#007236" hx="007236"></td><td style="background:#00736a" hx="00736a"></td><td style="background:#0076a4" hx="0076a4"></td><td style="background:#004a80" hx="004a80"></td><td style="background:#003370" hx="003370"></td><td style="background:#1d1363" hx="1d1363"></td><td style="background:#450e61" hx="450e61"></td><td style="background:#62055f" hx="62055f"></td><td style="background:#9e005c" hx="9e005c"></td><td style="background:#9d0039" hx="9d0039"></td></tr><tr><td style="background:#790000" hx="790000"></td><td style="background:#7b3000" hx="7b3000"></td><td style="background:#7c4900" hx="7c4900"></td><td style="background:#827a00" hx="827a00"></td><td style="background:#3e6617" hx="3e6617"></td><td style="background:#045f20" hx="045f20"></td><td style="background:#005824" hx="005824"></td><td style="background:#005951" hx="005951"></td><td style="background:#005b7e" hx="005b7e"></td><td style="background:#003562" hx="003562"></td><td style="background:#002056" hx="002056"></td><td style="background:#0c004b" hx="0c004b"></td><td style="background:#30004a" hx="30004a"></td><td style="background:#4b0048" hx="4b0048"></td><td style="background:#7a0045" hx="7a0045"></td><td style="background:#7a0026" hx="7a0026"></td></tr></thead><tbody><tr><td style="border:1px solid #000;background:#fff;cursor:pointer;height:60px;-moz-background-clip:-moz-initial;-moz-background-origin:-moz-initial;-moz-background-inline-policy:-moz-initial;" colspan="16" align="center" id="colorPreview"><span style="color:#000;border:1px solid rgb(0, 0, 0);padding:5px;background-color:#fff;font:11px Arial, Helvetica, sans-serif;"></span></td></tr></tbody></table><style>#iColorPicker input{margin:2px}</style>').appendTo("body");jQuery(document.createElement("div")).attr("id","iColorPickerBg").click(function(){jQuery("#iColorPickerBg").hide();jQuery("#iColorPicker").fadeOut()}).appendTo("body");jQuery('table.pickerTable td').css({'width':'12px','height':'14px','border':'1px solid #000','cursor':'pointer'});jQuery('#iColorPicker table.pickerTable').css({'border-collapse':'collapse'});jQuery('#iColorPicker').css({'border':'1px solid #ccc','background':'#333','padding':'5px','color':'#fff','z-index':9999})}
    jQuery('#colorPreview').css({'height':'50px'});
    })
};

jQuery(function(){iColorPicker()});

function SetColorPickerColor(field_name, color, callback){
    var chip = jQuery('#chip_' + field_name);
    chip.css("background-color", color);
    if(callback)
        window[callback](color);
}

function SetFieldChoices(){
    var field = GetSelectedField();
    for(var i=0; i<field.choices.length; i++){
        SetFieldChoice(i);
    }
}

/**
* Quick jQuery plugin that allows a variable to be passed which determins whether to
* instantly hide the element or slideUp instead.
*/
jQuery.fn.gfSlide = function(direction) {

    var isVisible = jQuery('#field_settings').is(':visible');

    if(direction == 'up') {
        if(!isVisible) {
            this.hide();
        } else {
            this.slideUp();
        }
    } else {
        if(!isVisible) {
            this.show();
        } else {
            this.slideDown();
        }
    }

    return this;
};