/**
* Common JS functions for form settings and form editor pages.
*/

jQuery(document).ready(function($){

    gaddon.init();

    $(document).on('change', '.gfield_rule_value_dropdown', function(){
        SetRuleValueDropDown($(this));
    });

    // init merge tag auto complete
	if ( typeof form != 'undefined' && jQuery( '.merge-tag-support' ).length >= 0 ) {

		jQuery( '.merge-tag-support' ).each( function() {

			new gfMergeTagsObj( form, jQuery( this ) );

		} );

	}

	// For backwards compat.
	if( window.form ) {
		window.gfMergeTags = new gfMergeTagsObj( form );
	}

	$(document).ready(function(){
		$(".gform_currency").bind("change", function(){
			FormatCurrency(this);
		}).each(function(){
			FormatCurrency(this);
		});
	});
});

function FormatCurrency(element){
	if(gf_vars.gf_currency_config){
		var currency = new Currency(gf_vars.gf_currency_config);
		var price = currency.toMoney(jQuery(element).val());
		jQuery(element).val(price);
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

function GetConditionalObject(objectType){

    var object = false;

    switch(objectType){
    case "page":
    case "field":
        object = GetSelectedField();
        break;

    case "next_button" :
        var field = GetSelectedField();
        object = field["nextButton"];
        break;

    case "confirmation":
        object = confirmation;
        break;

    case "notification":
        object = current_notification;
        break;

    default:
        object = typeof form != 'undefined' ? form.button : false;
        break;
    }

    object = gform.applyFilters( 'gform_conditional_object', object, objectType )

    return object;
}

function CreateConditionalLogic(objectType, obj){

    if(!obj.conditionalLogic)
        obj.conditionalLogic = new ConditionalLogic();

    var hideSelected = obj.conditionalLogic.actionType == "hide" ? "selected='selected'" :"";
    var showSelected = obj.conditionalLogic.actionType == "show" ? "selected='selected'" :"";
    var allSelected = obj.conditionalLogic.logicType == "all" ? "selected='selected'" :"";
    var anySelected = obj.conditionalLogic.logicType == "any" ? "selected='selected'" :"";

    var objText;
    if (obj['type'] == "section")
        objText = gf_vars.thisSectionIf;
    else if(objectType == "field")
        objText = gf_vars.thisFieldIf;
    else if(objectType == "page")
        objText = gf_vars.thisPage;
    else if(objectType == "confirmation")
        objText = gf_vars.thisConfirmation;
    else if(objectType == "notification")
        objText = gf_vars.thisNotification;
    else
        objText = gf_vars.thisFormButton;

    var descPieces = {};
    descPieces.actionType = "<select id='" + objectType + "_action_type' onchange='SetConditionalProperty(\"" + objectType + "\", \"actionType\", jQuery(this).val());'><option value='show' " + showSelected + ">" + gf_vars.show + "</option><option value='hide' " + hideSelected + ">" + gf_vars.hide + "</option></select>";
    descPieces.objectDescription = objText;
    descPieces.logicType = "<select id='" + objectType + "_logic_type' onchange='SetConditionalProperty(\"" + objectType + "\", \"logicType\", jQuery(this).val());'><option value='all' " + allSelected + ">" + gf_vars.all + "</option><option value='any' " + anySelected + ">" + gf_vars.any + "</option></select>";
    descPieces.ofTheFollowingMatch = gf_vars.ofTheFollowingMatch;

    var descPiecesArr = makeArray( descPieces );

    var str = descPiecesArr.join(' ');
    str = gform.applyFilters( 'gform_conditional_logic_description', str, descPieces, objectType, obj );
    var i, rule;
    for(i=0; i < obj.conditionalLogic.rules.length; i++){
        rule = obj.conditionalLogic.rules[i];
        str += "<div width='100%' class='gf_conditional_logic_rules_container'>";
        str += GetRuleFields(objectType, i, rule.fieldId);
        str += GetRuleOperators(objectType, i, rule.fieldId, rule.operator);
        str += GetRuleValues(objectType, i, rule.fieldId, rule.value);
        str += "<a class='add_field_choice' title='add another rule' onclick=\"InsertRule('" + objectType + "', " + (i+1) + ");\" onkeypress=\"InsertRule('" + objectType + "', " + (i+1) + ");\" ><i class='gficon-add'></i></a>";
        if(obj.conditionalLogic.rules.length > 1 )
            str += "<a class='delete_field_choice' title='remove this rule' onclick=\"DeleteRule('" + objectType + "', " + i + ");\" onkeypress=\"DeleteRule('" + objectType + "', " + i + ");\" ><i class='gficon-subtract'></i></a></li>";

        str += "</div>";
    }

    jQuery("#" + objectType + "_conditional_logic_container").html(str);

    //initializing placeholder script
    Placeholders.enable();
}

function GetRuleOperators( objectType, i, fieldId, selectedOperator ) {
    var str, supportedOperators, operators, selected;
    supportedOperators = {"is":"is","isnot":"isNot", ">":"greaterThan", "<":"lessThan", "contains":"contains", "starts_with":"startsWith", "ends_with":"endsWith"};
    str = "<select id='" + objectType + "_rule_operator_" + i + "' class='gfield_rule_select' onchange='SetRuleProperty(\"" + objectType + "\", " + i + ", \"operator\", jQuery(this).val());var valueSelector=\"#" + objectType + "_rule_value_" + i + "\"; jQuery(valueSelector).replaceWith(GetRuleValues(\"" + objectType + "\", " + i + ",\"" + fieldId + "\", \"\"));jQuery(valueSelector).change();'>";
    operators = IsEntryMeta(fieldId) ? GetOperatorsForMeta(supportedOperators, fieldId) : supportedOperators;

    operators = gform.applyFilters( 'gform_conditional_logic_operators', operators, objectType, fieldId );

    jQuery.each(operators,function(operator, stringKey){
        selected = selectedOperator == operator ? "selected='selected'" : "";
        str += "<option value='" + operator + "' " + selected + ">" + gf_vars[stringKey] + "</option>"
    });
    str +="</select>";
    return str;
}

function GetOperatorsForMeta(supportedOperators, key){
    var operators = {};
    if(entry_meta[key] && entry_meta[key].filter && entry_meta[key].filter.operators ){

        jQuery.each(supportedOperators,function(operator, stringKey){
            if(jQuery.inArray(operator, entry_meta[key].filter.operators) >= 0)
                operators[operator] = stringKey;
        });
    } else {
        operators = supportedOperators;
    }
    return operators;
}

function GetRuleFields( objectType, ruleIndex, selectedFieldId ) {

    var str = "<select id='" + objectType + "_rule_field_" + ruleIndex + "' class='gfield_rule_select' onchange='jQuery(\"#" + objectType + "_rule_operator_" + ruleIndex + "\").replaceWith(GetRuleOperators(\"" + objectType + "\", " + ruleIndex + ", jQuery(this).val()));jQuery(\"#" + objectType + "_rule_value_" + ruleIndex + "\").replaceWith(GetRuleValues(\"" + objectType + "\", " + ruleIndex + ", jQuery(this).val())); SetRule(\"" + objectType + "\", " + ruleIndex + "); '>";
    var options = [];

    for( var i = 0; i < form.fields.length; i++ ) {

        var field = form.fields[i];

        if( IsConditionalLogicField( field ) ) {

            // @todo: the inputType check will likely go away once we've figured out how we're going to manage inputs moving forward
            if( field.inputs && jQuery.inArray( GetInputType( field ), [ 'checkbox', 'email', 'consent' ] ) == -1 ) {
                for( var j = 0; j < field.inputs.length; j++ ) {
                    var input = field.inputs[j];
                    if( ! input.isHidden ) {
                        options.push( {
                            label: GetLabel( field, input.id ),
                            value: input.id
                        } );
                    }
                }
            } else {
                options.push( {
                    label: GetLabel( field ),
                    value: field.id
                } );
            }

        }

    }

    // get entry meta fields and append to existing fields
    jQuery.merge(options, GetEntryMetaFields( selectedFieldId ) );

    options = gform.applyFilters( 'gform_conditional_logic_fields', options, form, selectedFieldId );

    str += GetRuleFieldsOptions( options, selectedFieldId );

    str += "</select>";
    return str;
}

function GetRuleFieldsOptions( options, selectedFieldId ){
    var str = '';
    for( var i = 0; i < options.length; i++ ) {

        var option = options[i];
        if ( typeof option.options !== 'undefined' ) {
            str += '<optgroup label=" ' + option.label + '">';
            str += GetRuleFieldsOptions( option.options, selectedFieldId );
            str += '</optgroup>';
        } else {
            var selected = option.value == selectedFieldId ? "selected='selected'" : '';

            str += "<option value='" + option.value + "' " + selected + ">" + option.label + "</option>";
        }
    }
    return str;
}

function GetEntryMetaFields( selectedFieldId ) {

    var options = [], selected, label;

    if(typeof entry_meta == 'undefined')
        return options;

    jQuery.each( entry_meta, function( key, meta ) {

        if(typeof meta.filter == 'undefined')
            return;

       options.push( {
            label: meta.label,
            value: key,
            isSelected: selectedFieldId == key ? "selected='selected'" : ""
        } );

    });

    return options;
}

function IsConditionalLogicField(field){
    var inputType = field.inputType ? field.inputType : field.type;
    var supported_fields = GetConditionalLogicFields();

    var index = jQuery.inArray(inputType, supported_fields);
    var isConditionalLogicField = index >= 0 ? true : false;
    isConditionalLogicField = gform.applyFilters( 'gform_is_conditional_logic_field', isConditionalLogicField, field );
    return isConditionalLogicField;
}

function IsEntryMeta(key){

    return typeof entry_meta != 'undefined' && typeof entry_meta[key] != 'undefined';
}

function GetRuleValues(objectType, ruleIndex, selectedFieldId, selectedValue, inputName){

    if(!inputName)
        inputName = false;

    var dropdownId = inputName == false ? objectType + '_rule_value_' + ruleIndex : inputName;

    if(selectedFieldId == 0)
        selectedFieldId = GetFirstRuleField();

    if(selectedFieldId == 0)
        return "";

    var field = GetFieldById(selectedFieldId),
        isEntryMeta = IsEntryMeta(selectedFieldId),
        obj = GetConditionalObject(objectType),
        rule = obj["conditionalLogic"]["rules"][ruleIndex],
        operator = rule.operator,
        str = "";

    if(field && field["type"] == "post_category" && field["displayAllCategories"]){

        var dropdown = jQuery('#' + dropdownId + ".gfield_category_dropdown");

        //don't load category drop down if it already exists (to avoid unnecessary ajax requests)
        if(dropdown.length > 0){

	        var options = dropdown.html();
	        options = options.replace(/ selected="selected"/g, '');
	        options = options.replace("value=\"" + selectedValue + "\"", "value=\"" + selectedValue + "\" selected=\"selected\"");
	        str = "<select id='" + dropdownId + "' class='gfield_rule_select gfield_rule_value_dropdown gfield_category_dropdown'>" + options + "</select>";
        }
        else{
            var placeholderName = inputName == false ? "gfield_ajax_placeholder_" + ruleIndex : inputName + "_placeholder";

            //loading categories via AJAX
            jQuery.post(ajaxurl,{   action:"gf_get_post_categories",
                                    objectType: objectType,
                                    ruleIndex: ruleIndex,
                                    inputName: inputName,
                                    selectedValue: selectedValue},
                                function(dropdown_string){
                                    if(dropdown_string){
                                        jQuery('#' + placeholderName).replaceWith(dropdown_string.trim());

                                        SetRuleProperty(objectType, ruleIndex, "value", jQuery("#" + dropdownId).val());
                                    }
                                }
                        );

            //will be replaced by real drop down during the ajax callback
            str = "<select id='" + placeholderName + "' class='gfield_rule_select'><option>" + gf_vars["loading"] + "</option></select>";
        }
    }
    else if(field && field.choices && jQuery.inArray(operator, ["is", "isnot"]) > -1){
        var ruleChoices = field.placeholder ? [{
            text: field.placeholder,
            value: ''
        }].concat(field.choices) : field.choices;
        str = GetRuleValuesDropDown(ruleChoices, objectType, ruleIndex, selectedValue, inputName);
    }
    else if( IsAddressSelect( selectedFieldId, field )  ) {

        //loading categories via AJAX
        jQuery.post( ajaxurl, {
            action:       'gf_get_address_rule_values_select',
            address_type: field.addressType ? field.addressType : gf_vars.defaultAddressType,
            value:        selectedValue,
            id:           dropdownId,
            form_id:      field.formId
        }, function( selectMarkup ) {
            if( selectMarkup ) {
                $select = jQuery( selectMarkup.trim() );
                $placeholder = jQuery( '#' + dropdownId );
                $placeholder.replaceWith( $select );
                SetRuleProperty( objectType, ruleIndex, 'value', $select.val() );
            }
        } );

        // will be replaced by real drop down during the ajax callback
        str = "<select id='" + dropdownId + "' class='gfield_rule_select'><option>" + gf_vars['loading'] + "</option></select>";

    }
    else if (isEntryMeta && entry_meta && entry_meta[selectedFieldId] &&  entry_meta[selectedFieldId].filter && typeof entry_meta[selectedFieldId].filter.choices != 'undefined') {
        str = GetRuleValuesDropDown(entry_meta[selectedFieldId].filter.choices, objectType, ruleIndex, selectedValue, inputName);
    }
    else{
        selectedValue = selectedValue ? selectedValue.replace(/'/g, "&#039;") : "";

        //create a text field for fields that don't have choices (i.e text, textarea, number, email, etc...)
        str = "<input type='text' placeholder='" + gf_vars["enterValue"] + "' class='gfield_rule_select gfield_rule_input' id='" + dropdownId + "' name='" + dropdownId + "' value='" + selectedValue.replace(/'/g, "&#039;") + "' onchange='SetRuleProperty(\"" + objectType + "\", " + ruleIndex + ", \"value\", jQuery(this).val());' onkeyup='SetRuleProperty(\"" + objectType + "\", " + ruleIndex + ", \"value\", jQuery(this).val());'>";
    }

    str = gform.applyFilters( 'gform_conditional_logic_values_input', str, objectType, ruleIndex, selectedFieldId, selectedValue )

    return str;
}
/**
 * Determine if current Address field input ID is a select (i.e. US => State, International => Country)
 * @param inputId string Address field input ID
 * @param field object Address field
 * @returns {boolean}
 * @constructor
 */
function IsAddressSelect( inputId, field ) {

    if( ! field || GetInputType( field ) != 'address' ) {
        return false;
    }

    var addressType = field.addressType ? field.addressType : gf_vars.defaultAddressType;

    if( ! gf_vars.addressTypes[ addressType ] ) {
        return false;
    }

    var addressTypeObj = gf_vars.addressTypes[ addressType ],
        isCountryInput = inputId == field.id + '.6',
        isStateInput   = inputId == field.id + '.4';

    return ( isCountryInput && addressType == 'international' ) || ( isStateInput && typeof addressTypeObj.states == 'object' );
}

function GetFirstRuleField(){
    for(var i=0; i<form.fields.length; i++){
        if(IsConditionalLogicField(form.fields[i]))
            return form.fields[i].id;
    }

    return 0;
}

function GetRuleValuesDropDown(choices, objectType, ruleIndex, selectedValue, inputName){

    var dropdown_id = inputName == false ? objectType + '_rule_value_' + ruleIndex : inputName;

    //create a drop down for fields that have choices (i.e. drop down, radio, checkboxes, etc...)
    var str = "<select class='gfield_rule_select gfield_rule_value_dropdown' id='" + dropdown_id + "' name='" + dropdown_id + "'>";

    var isAnySelected = false;
    for(var i=0; i<choices.length; i++){
        var choiceValue = typeof choices[i].value == "undefined" || choices[i].value == null ? choices[i].text + '' : choices[i].value + '';
        var isSelected = choiceValue == selectedValue;
        var selected = isSelected ? "selected='selected'" : "";
        if(isSelected)
            isAnySelected = true;
		choiceValue = choiceValue.replace(/'/g, "&#039;");
		var choiceText = jQuery.trim(jQuery('<div>'+choices[i].text+'</div>').text()) === '' ? choiceValue : choices[i].text;
        str += "<option value='" + choiceValue.replace(/'/g, "&#039;") + "' " + selected + ">" + choiceText + "</option>";
    }

    if(!isAnySelected && selectedValue && selectedValue != "")
        str += "<option value='" + selectedValue.replace(/'/g, "&#039;") + "' selected='selected'>" + selectedValue + "</option>";

    str += "</select>";

    return str;

}
function isEmpty(str){
	return
}


function SetRuleProperty(objectType, ruleIndex, name, value){
    var obj = GetConditionalObject(objectType);
    obj.conditionalLogic.rules[ruleIndex][name] = value;
}

function GetFieldById( id ) {
    id = parseInt( id );
    for(var i=0; i<form.fields.length; i++){
        if(form.fields[i].id == id)
            return form.fields[i];
    }
    return null;
}

function SetConditionalProperty(objectType, name, value){
    var obj = GetConditionalObject(objectType);
    obj.conditionalLogic[name] = value;
}

function SetRuleValueDropDown(element){
    //parsing ID to get objectType and ruleIndex
    var ary = element.attr("id").split('_rule_value_');

    if(ary.length < 2)
        return;

    var objectType = ary[0];
    var ruleIndex = ary[1];

    SetRuleProperty(objectType, ruleIndex, "value", element.val());
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

function TruncateRuleText(text){
    if(!text || text.length <= 18)
        return text;

    return text.substr(0, 9) + "..." + text.substr(text.length -8, 9);

}

function gfAjaxSpinner(elem, imageSrc, inlineStyles) {

    imageSrc     = typeof imageSrc == 'undefined' || ! imageSrc ? gf_vars.baseUrl + '/images/spinner.gif': imageSrc;
    inlineStyles = typeof inlineStyles != 'undefined' ? inlineStyles : '';

    this.elem = elem;
    this.image = '<img class="gfspinner" src="' + imageSrc + '" style="' + inlineStyles + '" />';

    this.init = function() {
        this.spinner = jQuery(this.image);
        jQuery(this.elem).after(this.spinner);
        return this;
    };

    this.destroy = function() {
        jQuery(this.spinner).remove();
    };

    return this.init();
}

function InsertVariable(element_id, callback, variable) {

    if(!variable)
        variable = jQuery('#' + element_id + '_variable_select').val();

    var input = document.getElementById (element_id);
    var $input = jQuery(input);

    if(document.selection) {
        // Go the IE way
        $input[0].focus();
        document.selection.createRange().text=variable;
    }
    else if('selectionStart' in input) {
        var startPos = input.selectionStart;
        input.value = input.value.substr(0, startPos) + variable + input.value.substr(input.selectionEnd, input.value.length);
        input.selectionStart = startPos + input.value.length;
        input.selectionEnd = startPos + input.value.length;
    } else {
        $input.val(variable + messageElement.val());
    }

    var variableSelect = jQuery('#' + element_id + '_variable_select');
    if(variableSelect.length > 0)
        variableSelect[0].selectedIndex = 0;

    if(callback && window[callback]){
        window[callback].call(null, element_id, variable);
    }

}

function InsertEditorVariable( elementId, value ) {

    if( !value ) {
        var select = jQuery("#" + elementId + "_variable_select");
        select[0].selectedIndex = 0;
        value = select.val();
    }

    wpActiveEditor = elementId;
    window.send_to_editor( value );

}

function GetInputType(field){
    return field.inputType ? field.inputType : field.type;
}

function HasPostField(){

    for(var i=0; i<form.fields.length; i++){
        var type = form.fields[i].type;
        if(type == "post_title" || type == "post_content" || type == "post_excerpt")
            return true;
    }
    return false;
}

function GetInput(field, id){

    if( typeof field['inputs'] != 'undefined' && jQuery.isArray(field['inputs']) ) {

        for(i in field['inputs']) {

            if(!field['inputs'].hasOwnProperty(i))
                continue;

            var input = field['inputs'][i];
            if(input.id == id)
                return input;
        }

    }

    return null;
}

function IsPricingField(fieldType) {
    return IsProductField(fieldType) || fieldType == 'donation';
}

function IsProductField(fieldType) {
    return jQuery.inArray(fieldType, ["option", "quantity", "product", "total", "shipping", "calculation"]) != -1;
}

function GetLabel(field, inputId, inputOnly) {
    if(typeof inputId == 'undefined')
        inputId = 0;

    if(typeof inputOnly == 'undefined')
        inputOnly = false;

    var input = GetInput(field, inputId);
    var displayLabel = "";

    if (field.adminLabel != undefined && field.adminLabel.length > 0){
		//use admin label
		displayLabel = field.adminLabel;
    }
    else{
    	//use regular label
		displayLabel = field.label;
    }

    if(input != null) {
        return inputOnly ? input.label : displayLabel + ' (' + input.label + ')';
    }
    else {
    	return displayLabel;
    }

}

function DeleteNotification(notificationId) {
    jQuery('#action_argument').val(notificationId);
    jQuery('#action').val('delete');
    jQuery('#notification_list_form')[0].submit();
}
function DuplicateNotification(notificationId) {
    jQuery('#action_argument').val(notificationId);
    jQuery('#action').val('duplicate');
    jQuery('#notification_list_form')[0].submit();
}

function DeleteConfirmation(confirmationId) {
    jQuery('#action_argument').val(confirmationId);
    jQuery('#action').val('delete');
    jQuery('#confirmation_list_form')[0].submit();
}

function DuplicateConfirmation(confirmationId) {
    jQuery('#action_argument').val(confirmationId);
    jQuery('#action').val('duplicate');
    jQuery('#confirmation_list_form')[0].submit();
}

function SetConfirmationConditionalLogic() {
   confirmation['conditionalLogic'] = jQuery('#conditional_logic').val() ? jQuery.parseJSON(jQuery('#conditional_logic').val()) : new ConditionalLogic();
}

function ToggleConfirmation() {

    var showElement, hideElement = '';
    var isRedirect = jQuery("#form_confirmation_redirect").is(":checked");
    var isPage = jQuery("#form_confirmation_show_page").is(":checked");

    if(isRedirect){
        showElement = ".form_confirmation_redirect_container";
        hideElement = "#form_confirmation_message_container, .form_confirmation_page_container";
        ClearConfirmationSettings(['text', 'page']);
    }
    else if(isPage){
        showElement = ".form_confirmation_page_container";
        hideElement = "#form_confirmation_message_container, .form_confirmation_redirect_container";
        ClearConfirmationSettings(['text', 'redirect']);
    }
    else{
        showElement = "#form_confirmation_message_container";
        hideElement = ".form_confirmation_page_container, .form_confirmation_redirect_container";
        ClearConfirmationSettings(['page', 'redirect']);
    }

    ToggleQueryString();
    TogglePageQueryString()

    jQuery(hideElement).hide();
    jQuery(showElement).show();

}

function ToggleQueryString() {
    if(jQuery('#form_redirect_use_querystring').is(":checked")){
        jQuery('#form_redirect_querystring_container').show();
    }
    else{
        jQuery('#form_redirect_querystring_container').hide();
        jQuery("#form_redirect_querystring").val('');
        jQuery("#form_redirect_use_querystring").val('');
    }
}

function TogglePageQueryString() {
    if(jQuery('#form_page_use_querystring').is(":checked")){
        jQuery('#form_page_querystring_container').show();
    }
    else{
        jQuery('#form_page_querystring_container').hide();
        jQuery("#form_page_querystring").val('');
        jQuery("#form_page_use_querystring").val('');
    }
}

function ClearConfirmationSettings(type) {

    var types = jQuery.isArray(type) ? type : [type];

    for(i in types) {

        if(!types.hasOwnProperty(i))
            continue;

        switch(types[i]) {
        case 'text':
            jQuery('#form_confirmation_message').val('');
            jQuery('#form_disable_autoformatting').prop('checked', false);
            break;
        case 'page':
            jQuery('#form_confirmation_page').val('');
            jQuery('#form_page_querystring').val('');
            jQuery('#form_page_use_querystring').prop('checked', false);
            break;
        case 'redirect':
            jQuery('#form_confirmation_url').val('');
            jQuery('#form_redirect_querystring').val('');
            jQuery('#form_redirect_use_querystring').prop('checked', false);
            break;
        }
    }

}

function StashConditionalLogic() {
    var string = JSON.stringify(confirmation['conditionalLogic']);
    jQuery('#conditional_logic').val(string);
}

function ConfirmationObj() {
    this.id = false;
    this.name = gf_vars.confirmationDefaultName;
    this.type = 'message';
    this.message = gf_vars.confirmationDefaultMessage;
    this.isDefault = 0;
}

(function (gaddon, $, undefined) {

    gaddon.init = function () {

        var defaultVal, valueExists, value;

        f = window.form;
        var id = 0;
        if(isSet(f)){
            id = f.id
        }

    };

    gaddon.toggleFeedSwitch = function(img, is_active) {
        if (is_active) {
            img.src = img.src.replace("spinner.gif", "active1.png");
            jQuery(img).attr('title',gf_vars.inactive).attr('alt', gf_vars.inactive);
        } else{
            img.src = img.src.replace("spinner.gif", "active0.png");
            jQuery(img).attr('title',gf_vars.active).attr('alt', gf_vars.active);
        }
    };

    gaddon.toggleFeedActive = function(img, addon_slug, feed_id){
        var is_active = img.src.indexOf("active1.png") >=0 ? 0 : 1;
        img.src = img.src.replace("active1.png", "spinner.gif");
        img.src = img.src.replace("active0.png", "spinner.gif");

        jQuery.post(ajaxurl, {
            action: "gf_feed_is_active_" + addon_slug,
            feed_id: feed_id,
            is_active: is_active,
            nonce: jQuery('#feed_list').val()
            },
            function(response){
                if (response.success) {
                    gaddon.toggleFeedSwitch(img, is_active);
                } else {
                    gaddon.toggleFeedSwitch(img, ! is_active);
                    alert(response.data.message);
                }
            }
        ).fail(function(jqXHR, textStatus, error) {
            gaddon.toggleFeedSwitch(img, ! is_active);
            alert(error);
        });

        return true;
    };

    gaddon.deleteFeed = function (id) {
        $("#single_action").val("delete");
        $("#single_action_argument").val(id);
        $("#gform-settings").submit();
    };

    gaddon.duplicateFeed = function (id) {
        $("#single_action").val("duplicate");
        $("#single_action_argument").val(id);
        $("#gform-settings").submit();
    };

    function isValidJson(str) {
        try {
            JSON.parse(str);
        } catch (e) {
            return false;
        }
        return true;
    }

    function isSet($var) {
        if (typeof $var != 'undefined')
            return true
        return false
    }

    function rgar(array, name) {
        if (typeof array[name] != 'undefined')
            return array[name];
        return '';
    }

}(window.gaddon = window.gaddon || {}, jQuery));

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

var gfMergeTagsObj = function( form, element ) {

	var self      = this;
		self.form = form;
		self.elem = element;

	/**
	* Initialize a merge tag object.
	*/
	self.init = function() {

		// If merge tags are already initialized for object, exit.
		if ( self.elem.data( 'mergeTags' ) ) {
			return;
		}

		// Get merge tag list element.
		self.mergeTagList      = jQuery( '<ul id="gf_merge_tag_list" class=""></ul>' );
		self.mergeTagListHover = false;

		// Bind keydown event.
		self.bindKeyDown();

		// Initialize autocomplete.
		self.initAutocomplete();

		self.addMergeTagIcon();

		self.mergeTagIcon.find( 'a.open-list' ).on( 'click.gravityforms', function() {

			var trigger = jQuery(this);

			var input = self.getTargetElement( trigger );
			self.mergeTagList.html( '' );
			self.mergeTagList.append( self.getMergeTagListItems( input ) );
			self.mergeTagList.insertAfter( trigger ).show();

		} );


		// Hide merge tag list on off click.
		self.mergeTagList.hover(
			function() {
				self.mergeTagListHover = true;
			},
			function(){
				self.mergeTagListHover = false;
			}
		);

		jQuery( 'body' ).mouseup( function() {
			if( ! self.mergeTagListHover ) {
				self.mergeTagList.hide();
			}
		} );

		// Assign gfMergeTagsObj to element.
		self.elem.data( 'mergeTags', self );

	};

	/**
	* Destroy a merge tag object.
	*/
	self.destroy = function( element ) {

		// Get element.
		element = self.elem ? self.elem : element;

		element.next( '.all-merge-tags' ).remove();
		element.off( 'keydown.gravityforms' );
		element.autocomplete( 'destroy' );
		element.data( 'mergeTags', null );

	};





	// # MERGE TAG INITIALIZATION --------------------------------------------------------------------------------------

	/**
	* Bind keydown event to element.
	*/
	self.bindKeyDown = function() {

		self.elem.on( 'keydown.gravityforms', function( event ) {

			var menuActive = self.elem.data( 'autocomplete' ) && self.elem.data( 'autocomplete' ).menu ? self.elem.data( 'autocomplete' ).menu.active : false;

			if ( event.keyCode === jQuery.ui.keyCode.TAB && menuActive ) {
				event.preventDefault();
			}

		} );

	}

	/**
	* Initialize autocomplete for element.
	*/
	self.initAutocomplete = function() {

		self.elem.autocomplete( {
			minLength: 1,
			focus:     function() {

				// Prevent value inserted on focus.
				return false;

			},
			source:    function( request, response ) {

				// Delegate back to autocomplete, but extract the last term.
				var term = self.extractLast( request.term );

				if ( term.length < self.elem.autocomplete( 'option', 'minLength' ) ) {
					response( [] );
					return;
				}

				var tags = jQuery.map( self.getAutoCompleteMergeTags( self.elem ), function( item ) {
					return self.startsWith( item, term ) ? item : null;
				} );

				response( tags );
			},
			select:    function( event, ui ) {

				var terms = this.value.split( ' ' );

				// Remove the current input.
				terms.pop();

				// Add the selected item.
				terms.push( ui.item.value );

				this.value = terms.join( ' ' );

				self.elem.trigger( 'input' ).trigger( 'propertychange' );

				return false;

			}
		} );

	}

	/**
	* Add merge tag drop down icon next to element.
	*/
	self.addMergeTagIcon = function() {

		var inputType     = self.elem.is( 'input' ) ? 'input' : 'textarea',
		    positionClass = self.getClassProperty( self.elem, 'position' );

		self.mergeTagIcon  = jQuery( '<span class="all-merge-tags ' + positionClass + ' ' + inputType + '"><a class="open-list tooltip-merge-tag" title="' + gf_vars.mergeTagsTooltip + '"></a></span>' );

		// Add the target element to the merge tag icon data for reference later when determining where the selected merge tag should be inserted.
		self.mergeTagIcon.data( 'targetElement', self.elem.attr( 'id' ) );

		// If "mt-manual_position" class prop is set, look for manual elem with correct class.
		if ( self.getClassProperty( self.elem, 'manual_position' ) ) {

			var manualClass = '.mt-' + self.elem.attr('id');

			jQuery( manualClass ).append( self.mergeTagIcon );

		} else {

			self.elem.after( self.mergeTagIcon );

		}

		self.mergeTagIcon.find( '.tooltip-merge-tag' ).tooltip( {
			show:    { delay:1250 },
			content: function () { return jQuery( this ).prop( 'title' ); }
		} );

	}

	/**
	* Bind click event when selecting merge tag from drop down list.
	*/
	self.bindMergeTagListClick = function( event ) {

		self.mergeTagList.hide();

		var value = jQuery( event.target ).data('value');
		var input = self.getTargetElement( event.target );

		// If input has "mt-wp_editor" class, use WP Editor insert function.
		if( self.isWpEditor( input ) ) {
			InsertEditorVariable( input.attr('id'), value );
		} else {
			InsertVariable( input.attr('id'), null, value );
		}

		input.trigger( 'input' ).trigger( 'propertychange' );

		self.mergeTagList.hide();

	}




	// # MERGE TAG MANAGEMENT ------------------------------------------------------------------------------------------


	this.getMergeTags = function(fields, elementId, hideAllFields, excludeFieldTypes, isPrepop, option) {

		if(typeof fields == 'undefined')
			fields = [];

		if(typeof excludeFieldTypes == 'undefined')
			excludeFieldTypes = [];

		var requiredFields = [], optionalFields = [], pricingFields = [];
		var ungrouped = [], requiredGroup = [], optionalGroup = [], pricingGroup = [], otherGroup = [], customGroup = [];

		if(!hideAllFields)
			ungrouped.push({ tag: '{all_fields}', 'label': this.getMergeTagLabel('{all_fields}') });

		if(!isPrepop) {

			// group fields by required, optional and pricing
			for(i in fields) {

				if(!fields.hasOwnProperty(i))
					continue;

				var field = fields[i];

				if(field['displayOnly'])
					continue;

				var inputType = GetInputType(field);
				if(jQuery.inArray(inputType, excludeFieldTypes) != -1)
					continue;

				if(field.isRequired) {

					switch(inputType) {

					case 'name':

						var requiredField = Copy(field);
						var prefix, middle, suffix, optionalField;

						if(field['nameFormat'] == 'extended') {

							prefix = GetInput(field, field.id + '.2');
							suffix = GetInput(field, field.id + '.8');

							optionalField = Copy(field);
							optionalField['inputs'] = [prefix, suffix];

							// add optional name fields to optional list
							optionalFields.push(optionalField);

							// remove optional name fields from required list
							delete requiredField.inputs[0];
							delete requiredField.inputs[3];
						} else if(field['nameFormat'] == 'advanced') {

							prefix = GetInput(field, field.id + '.2');
							middle = GetInput(field, field.id + '.4');
							suffix = GetInput(field, field.id + '.8');

							optionalField = Copy(field);
							optionalField['inputs'] = [prefix, middle, suffix];

							// add optional name fields to optional list
							optionalFields.push(optionalField);

							// remove optional name fields from required list
							delete requiredField.inputs[0];
							delete requiredField.inputs[2];
							delete requiredField.inputs[4];
						}

						requiredFields.push(requiredField);
						break;

					default:
						requiredFields.push(field);
					}

				} else {

					optionalFields.push(field);

				}

				if(IsPricingField(field.type)) {
					pricingFields.push(field);
				}

			}

			if(requiredFields.length > 0) {
				for(i in requiredFields) {
					if(! requiredFields.hasOwnProperty(i))
						continue;

					requiredGroup = requiredGroup.concat(this.getFieldMergeTags(requiredFields[i], option));
				}
			}

			if(optionalFields.length > 0) {
				for(i in optionalFields) {

					if(!optionalFields.hasOwnProperty(i))
						continue;

					optionalGroup = optionalGroup.concat(this.getFieldMergeTags(optionalFields[i], option));
				}
			}

			if(pricingFields.length > 0) {

				if(!hideAllFields)
					pricingGroup.push({ tag: '{pricing_fields}', 'label': this.getMergeTagLabel('{pricing_fields}') });

				for(i in pricingFields) {
					if(!pricingFields.hasOwnProperty(i))
						continue;

					pricingGroup.concat(this.getFieldMergeTags(pricingFields[i], option));
				}

			}

		}

        var otherTags = [
            'ip', 'date_mdy', 'date_dmy', 'embed_post:ID', 'embed_post:post_title', 'embed_url', 'entry_id', 'entry_url', 'form_id', 'form_title', 'user_agent', 'referer', 'post_id', 'post_edit_url', 'user:display_name', 'user:user_email', 'user:user_login'
        ];

        // the form and entry objects are not available during replacement of pre-population merge tags
        if (isPrepop) {
            otherTags.splice(otherTags.indexOf('entry_id'), 1);
            otherTags.splice(otherTags.indexOf('entry_url'), 1);
            otherTags.splice(otherTags.indexOf('form_id'), 1);
            otherTags.splice(otherTags.indexOf('form_title'), 1);
        }

        if(!HasPostField() || isPrepop) { // TODO: consider adding support for passing form object or fields array
            otherTags.splice(otherTags.indexOf('post_id'), 1);
            otherTags.splice(otherTags.indexOf('post_edit_url'), 1);
        }

        for(var i in otherTags) {
            if(jQuery.inArray(otherTags[i], excludeFieldTypes) != -1)
                continue;

            otherGroup.push( { tag: '{'+ otherTags[i] +'}', label: this.getMergeTagLabel('{'+ otherTags[i] +'}') });
        }

		var customMergeTags = this.getCustomMergeTags();
		if( customMergeTags.tags.length > 0 ) {
			for( i in customMergeTags.tags ) {

				if(! customMergeTags.tags.hasOwnProperty(i))
					continue;

				var customMergeTag = customMergeTags.tags[i];
				customGroup.push( { tag: customMergeTag.tag, label: customMergeTag.label } );
			}
		}

		var mergeTags = {
			ungrouped: {
				label: this.getMergeGroupLabel('ungrouped'),
				tags: ungrouped
			},
			required: {
				label: this.getMergeGroupLabel('required'),
				tags: requiredGroup
			},
			optional: {
				label: this.getMergeGroupLabel('optional'),
				tags: optionalGroup
			},
			pricing: {
				label: this.getMergeGroupLabel('pricing'),
				tags: pricingGroup
			},
			other: {
				label: this.getMergeGroupLabel('other'),
				tags: otherGroup
			},
			custom: {
				label: this.getMergeGroupLabel('custom'),
				tags: customGroup
			}
		};

		mergeTags = gform.applyFilters('gform_merge_tags', mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option, this );

		return mergeTags;
	};

	this.getMergeTagLabel = function(tag) {

		for(groupName in gf_vars.mergeTags) {

			if(!gf_vars.mergeTags.hasOwnProperty(groupName))
				continue;

			var tags = gf_vars.mergeTags[groupName].tags;
			for(i in tags) {

				if(!tags.hasOwnProperty(i))
					continue;

				if(tags[i].tag == tag)
					return tags[i].label;
			}
		}

		return '';
	};

	this.getMergeGroupLabel = function(group) {
		return gf_vars.mergeTags[group].label;
	};

	this.getFieldMergeTags = function(field, option) {

		if(typeof option == 'undefined')
			option = '';

		var mergeTags = [];
		var inputType = GetInputType(field);
		var tagArgs = inputType == "list" ? ":" + option : ""; //option currently only supported by list field
		var value = '', label = '';

		if(jQuery.inArray(inputType, ['date', 'email', 'time', 'password'])>-1){
			field['inputs'] = null;
		}

		if( typeof field['inputs'] != 'undefined' && jQuery.isArray(field['inputs']) ) {

			if(inputType == 'checkbox') {
				label = GetLabel(field, field.id).replace("'", "\\'");
				value = "{" + label + ":" + field.id + tagArgs + "}";
				mergeTags.push( { tag: value, label: label } );
			}

			for(i in field.inputs) {

				if(!field.inputs.hasOwnProperty(i))
					continue;

				var input = field.inputs[i];
				if(inputType == "creditcard" && jQuery.inArray(parseFloat(input.id),[parseFloat(field.id + ".2"), parseFloat(field.id + ".3"), parseFloat(field.id + ".5")]) > -1)
					continue;
				label = GetLabel(field, input.id).replace("'", "\\'");
				value = "{" + label + ":" + input.id + tagArgs + "}";
				mergeTags.push( { tag: value, label: label } );
			}

		}
		else {
			label = GetLabel(field).replace("'", "\\'");
			value = "{" + label + ":" + field.id + tagArgs + "}";
			mergeTags.push( { tag: value, label: label } );
		}

		return mergeTags;
	};

	/**
	* Retrieve list of custom merge tags.
	*/
	self.getCustomMergeTags = function() {

		for ( groupName in gf_vars.mergeTags ) {

			if ( ! gf_vars.mergeTags.hasOwnProperty( groupName ) ) {
				continue;
			}

			if ( groupName == 'custom' ) {
				return gf_vars.mergeTags[ groupName ];
			}

		}

		return [];

	};

	this.getAutoCompleteMergeTags = function(elem) {

		var fields = this.form.fields;
		var elementId = elem.attr('id');
		var hideAllFields = this.getClassProperty(elem, 'hide_all_fields') == true;
		var excludeFieldTypes = this.getClassProperty(elem, 'exclude');
		var option = this.getClassProperty(elem, 'option');
		var isPrepop = this.getClassProperty(elem, 'prepopulate');

		if(isPrepop) {
			hideAllFields = true;
		}
		var mergeTags = this.getMergeTags(fields, elementId, hideAllFields, excludeFieldTypes, isPrepop, option);

		var autoCompleteTags = [];
		for(group in mergeTags) {

			if(! mergeTags.hasOwnProperty(group))
				continue;

			var tags = mergeTags[group].tags;
			for(i in tags) {

				if(!tags.hasOwnProperty(i))
					continue;

				autoCompleteTags.push(tags[i].tag);
			}
		}

		return autoCompleteTags;
	};

	this.getMergeTagListItems = function(elem) {

		var fields = this.form.fields;
		var elementId = elem.attr('id');
		var hideAllFields = this.getClassProperty(elem, 'hide_all_fields') == true;
		var excludeFieldTypes = this.getClassProperty(elem, 'exclude');
		var isPrepop = this.getClassProperty(elem, 'prepopulate');
		var option = this.getClassProperty(elem, 'option');

		if(isPrepop) {
			hideAllFields = true;
		}
		var mergeTags = this.getMergeTags(fields, elementId, hideAllFields, excludeFieldTypes, isPrepop, option);
		var hasMultipleGroups = this.hasMultipleGroups(mergeTags);
		var optionsHTML = [];

		for(group in mergeTags) {

			if(! mergeTags.hasOwnProperty(group))
				continue;

			var label = mergeTags[group].label
			var tags = mergeTags[group].tags;

			// skip groups without any tags
			if(tags.length <= 0)
				continue;

			// if group name provided
			if(label && hasMultipleGroups)
				optionsHTML.push( jQuery( '<li class="group-header">' + label + '</li>' ) );

			for(i in tags) {

				if(!tags.hasOwnProperty(i))
					continue;

				var tag = tags[i];

				var tagHTML = jQuery( '<a class="" data-value="' + escapeAttr( tag.tag ) + '">' + escapeHtml( tag.label ) + '</a>' );
				tagHTML.on( 'click.gravityforms', self.bindMergeTagListClick );

				optionsHTML.push( jQuery( '<li></li>' ).html( tagHTML ) );

			}

		}

		return optionsHTML;
	};

	this.hasMultipleGroups = function(mergeTags) {
		var count = 0;
		for(group in mergeTags) {

			if(!mergeTags.hasOwnProperty(group))
				continue;

			if(mergeTags[group].tags.length > 0)
				count++;
		}
		return count > 1;
	};





	// # HELPER METHODS ------------------------------------------------------------------------------------------------

	/**
	* Merge Tag inputs support a system for setting various properties for the merge tags via classes.
	*	e.g. mt-{property}-{value}
	*
	* You can pass multiple values for a property like so:
	*	e.g. mt-{property}-{value1}-{value2}-{value3}
	*
    * Use the following values to support JS merge tags (because they are not available in front end forms):
    *	mt-exclude-entry_id-entry_url-form_id-form_title
    *
	* Current classes:
	*	mt-hide_all_fields
	*	mt-exclude-{field_type}			e.g. mt-exlude-paragraph
	*	mt-option-{option_value}		e.g. mt-option-url
	*	mt-position-{position_value}	e.g. mt-position-right
	*
	*/
	self.getClassProperty = function( elem, property ) {

		var elem	 = jQuery( elem ),
			classStr = elem.attr( 'class' );

		// If no classes are defined, return empty string.
		if ( ! classStr ) {
			return '';
		}

		// Split CSS classes.
		var classes = classStr.split( ' ' );

		// Loop through CSS classes.
		for ( i in classes ) {

			// If property does not exist, skip it.
			if ( ! classes.hasOwnProperty( i ) ) {
				continue;
			}

			// Split class into pieces.
			var pieces = classes[i].split('-');

			// If this is not a merge tag class or not the property we are looking for, skip.
			if ( pieces[0] != 'mt' || pieces[1] != property ) {
				continue;
			}

			// If more than one value passed, return all values.
			if ( pieces.length > 3 ) {

				delete pieces[0];
				delete pieces[1];
				return pieces;

			} else if( pieces.length == 2 ) {

				// If only a property is passed, assume we are looking for boolean, return true.
				return true;
			} else {

				// Otherwise, return the value.
				return pieces[2];

			}

		}

		return '';

	};

	self.getTargetElement = function( elem ) {
		var elem = jQuery( elem );
		var selector = elem.parents('span.all-merge-tags').data('targetElement')

		/* escape any meta-characters with a double back clash as per jQuery Spec http://api.jquery.com/category/selectors/ */
		return jQuery( '#' + selector.replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, "\\$&") );
	}

	/**
	* Determine if merge tag element is for a WP editor instance.
	*/
	self.isWpEditor = function( mergeTagIcon ) {

		// Get merge tag icon element.
		var mergeTagIcon = jQuery( mergeTagIcon );

		return this.getClassProperty( mergeTagIcon, 'wp_editor' ) == true;

	};

	/**
	* Split a string at every space.
	*/
	self.split = function( string ) {

		return string.split( ' ' );

	};

	/**
	* Extract last item from string.
	*/
	self.extractLast = function( term ) {

		return this.split( term ).pop();

	};

	/**
	* Check if string starts with a specific value.
	*/
	self.startsWith = function( string, value ) {

		return string.indexOf( value ) === 0;

	};

	// If element is defined, initialize.
	if ( self.elem ) {
		self.init();
	}



};

var FeedConditionObj = function( args ) {

    this.strings = isSet( args.strings ) ? args.strings : {};
    this.logicObject = args.logicObject;

    this.init = function() {

        var fcobj = this;

        gform.addFilter( 'gform_conditional_object', 'FeedConditionConditionalObject' );
        gform.addFilter( 'gform_conditional_logic_description', 'FeedConditionConditionalDescription' );

        jQuery(document).ready(function(){
            ToggleConditionalLogic( true, "feed_condition" );
        });

        jQuery('input#feed_condition_conditional_logic').parents('form').on('submit', function(){
            jQuery('input#feed_condition_conditional_logic_object').val( JSON.stringify( fcobj.logicObject ) );
        });

    };

    this.init();

};

function SimpleConditionObject( object, objectType ) {

	if( objectType.indexOf('simple_condition') < 0 )
		return object;

	var objectName = objectType.substring(17) + "_object";

	return window[objectName];
}

function FeedConditionConditionalObject( object, objectType ) {

    if( objectType != 'feed_condition' )
        return object;

    return feedCondition.logicObject;
}

function FeedConditionConditionalDescription( description, descPieces, objectType, obj ) {

    if( objectType != 'feed_condition' )
        return description;

    descPieces.actionType = descPieces.actionType.replace('<select', '<select style="display:none;"');
    descPieces.objectDescription = feedCondition.strings.objectDescription;
    var descPiecesArr = makeArray( descPieces );

    return descPiecesArr.join(' ');
}

function makeArray( object ) {
    var array = [];
    for( i in object ) {
        array.push( object[i] );
    }
    return array;
}

function isSet( $var ) {
    return typeof $var != 'undefined';
}

/**
 * The entity mappings used by the escaping helper functions.
 *
 * Also used in form_editor.js
 *
 * @since 2.4.13
 *
 * @type {object}
 */
var entityMap = {
	'&': '&amp;',
	'<': '&lt;',
	'>': '&gt;',
	'"': '&quot;',
	'\'': '&#39;',
	'/': '&#x2F;',
	'`': '&#x60;',
	'=': '&#x3D;'
};

/**
 * Escapes the given string string ready to be output as the value of an HTML attribute.
 *
 * Also used in form_editor.js
 *
 * @since 2.4.13
 *
 * @param {string} string The string to escape.
 * @returns {string}
 */
function escapeAttr( string ) {
	return String( string ).replace( /["']/g, function ( s ) {
		return entityMap[s];
	} );
}

/**
 * Escapes the given string string ready to be output to the page as HTML.
 *
 * Also used in form_editor.js
 *
 * @since 2.4.13
 *
 * @param {string} string The string to escape.
 * @returns {string}
 */
function escapeHtml( string ) {
	return String( string ).replace( /[&<>"'`=\/]/g, function ( s ) {
		return entityMap[s];
	} );
}