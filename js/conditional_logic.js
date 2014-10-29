
var __gf_timeout_handle;

function gf_apply_rules(formId, fields, isInit){
    var rule_applied = 0;
    for(var i=0; i < fields.length; i++){
        gf_apply_field_rule(formId, fields[i], isInit, function(){
            rule_applied++;
            if(rule_applied == fields.length){
                jQuery(document).trigger('gform_post_conditional_logic', [formId, fields, isInit]);
                if(window["gformCalculateTotalPrice"])
                    window["gformCalculateTotalPrice"](formId);
            }
        });
    }
}

function gf_check_field_rule(formId, fieldId, isInit, callback){

    //if conditional logic is not specified for that field, it is supposed to be displayed
    if(!window["gf_form_conditional_logic"] || !window["gf_form_conditional_logic"][formId] || !window["gf_form_conditional_logic"][formId]["logic"][fieldId])
        return "show";

    var conditionalLogic = window["gf_form_conditional_logic"][formId]["logic"][fieldId];
    var action = gf_get_field_action(formId, conditionalLogic["section"]);

    //If section is hidden, always hide field. If section is displayed, see if field is supposed to be displayed or hidden
    if(action != "hide")
        action = gf_get_field_action(formId, conditionalLogic["field"]);

    return action;
}

function gf_apply_field_rule(formId, fieldId, isInit, callback){

    var action = gf_check_field_rule(formId, fieldId, isInit, callback);

    gf_do_field_action(formId, action, fieldId, isInit, callback);

    var conditionalLogic = window["gf_form_conditional_logic"][formId]["logic"][fieldId];
    //perform conditional logic for the next button
    if(conditionalLogic["nextButton"]){
        action = gf_get_field_action(formId, conditionalLogic["nextButton"]);
        gf_do_next_button_action(formId, action, fieldId, isInit);
    }

}

function gf_get_field_action(formId, conditionalLogic){
    if(!conditionalLogic)
        return "show";

    var matches = 0;
    for(var i = 0; i < conditionalLogic["rules"].length; i++){
        var rule = conditionalLogic["rules"][i];
        if(gf_is_match(formId, rule))
            matches++;
    }

    var action;
    if( (conditionalLogic["logicType"] == "all" && matches == conditionalLogic["rules"].length) || (conditionalLogic["logicType"] == "any"  && matches > 0) )
        action = conditionalLogic["actionType"];
    else
        action = conditionalLogic["actionType"] == "show" ? "hide" : "show";

    return action;
}

function gf_is_match(formId, rule){

    var isMatch = false;
    var inputs = jQuery("#input_" + formId + "_" + rule["fieldId"] + " input");
    var fieldValue;
    if(inputs.length > 0){
    	//handling checkboxes/radio

        for(var i=0; i< inputs.length; i++){
            fieldValue = gf_get_value(jQuery(inputs[i]).val());

            //find specific checkbox/radio item. Skip if this is not the specific item and the operator is not one that targets a range of values (i.e. greater than and less than)
            var isRangeOperator = jQuery.inArray(rule["operator"], ["<", ">", "contains", "starts_with", "ends_with"]) >= 0;
            if(fieldValue != rule["value"] && !isRangeOperator) {
                continue;
			}

            //blank value if item isn't checked
            if(!jQuery(inputs[i]).is(":checked")) {
                fieldValue = "";
			}
			else if (fieldValue == "gf_other_choice"){
				//get the value from the associated text box
				fieldValue = jQuery("#input_" + formId + "_" + rule["fieldId"] + "_other").val();
			}

            if(gf_matches_operation(fieldValue, rule["value"], rule["operator"]))
                isMatch = true;
        }
    }
    else{
        //handling all other fields (non-checkboxes)
        var val = jQuery("#input_" + formId + "_" + rule["fieldId"]).val();

        //transform regular value into array to support multi-select (which returns an array of selected items)
        var values = (val instanceof Array) ? val : [val];

        var matchCount = 0;

        var fieldNumberFormat = window['gf_global'] && gf_global.number_formats && gf_global.number_formats[formId] && gf_global.number_formats[formId][rule["fieldId"]] ? gf_global.number_formats[formId][rule["fieldId"]] : false;

        for(var i=0; i < values.length; i++){

            //fields with pipes in the value will use the label for conditional logic comparison
            var hasLabel = values[i] ? values[i].indexOf("|") >= 0 : true;

            fieldValue = gf_get_value(values[i]);

            var decimalSeparator = ".";
            if( fieldNumberFormat && !hasLabel){

                if( fieldNumberFormat == "currency" )
                    decimalSeparator = gformGetDecimalSeparator('currency');
                else if( fieldNumberFormat == "decimal_comma")
                    decimalSeparator = ",";
                else if( fieldNumberFormat == "decimal_dot")
                    decimalSeparator = ".";

                //transform to a decimal dot number
                fieldValue = gformCleanNumber( fieldValue, '', '', decimalSeparator);

                //now transform to number specified by locale
                if(window['gf_number_format'] && window['gf_number_format'] == "decimal_comma")
                    fieldValue = gformFormatNumber(fieldValue, -1, ",", ".");

                if( ! fieldValue )
                    fieldValue = 0;

                fieldValue = fieldValue.toString();
            }



            if(gf_matches_operation(fieldValue, rule["value"], rule["operator"])){
                matchCount++;
            }
        }
        //If operator is Is Not, none of the value can match
        isMatch = rule["operator"] == "isnot" ? matchCount == values.length : matchCount > 0;
    }

    return gform.applyFilters( 'gform_is_value_match', isMatch, formId, rule );
}

function gf_try_convert_float(text){
    var format = window["gf_number_format"] == "decimal_comma" ? "decimal_comma" : "decimal_dot";

    if(gformIsNumeric(text, format)){
        var decimal_separator = format == "decimal_comma" ? "," : ".";
        return gformCleanNumber(text, "", "", decimal_separator);
    }

    return text;
}

function gf_matches_operation(val1, val2, operation){
    val1 = val1 ? val1.toLowerCase() : "";
    val2 = val2 ? val2.toLowerCase() : "";

    switch(operation){
        case "is" :
            return val1 == val2;
        break;

        case "isnot" :
            return val1 != val2;
        break;

        case ">" :
            val1 = gf_try_convert_float(val1);
            val2 = gf_try_convert_float(val2);

            return gformIsNumber(val1) && gformIsNumber(val2) ? val1 > val2 : false;
        break;

        case "<" :
            val1 = gf_try_convert_float(val1);
            val2 = gf_try_convert_float(val2);

            return gformIsNumber(val1) && gformIsNumber(val2) ? val1 < val2 : false;
        break;

        case "contains" :
            return val1.indexOf(val2) >=0;
        break;

        case "starts_with" :
            return val1.indexOf(val2) ==0;
        break;

        case "ends_with" :
            var start = val1.length - val2.length;
            if(start < 0)
                return false;

            var tail = val1.substring(start);
            return val2 == tail;
        break;
    }
    return false;
}

function gf_get_value(val){
    if(!val)
        return "";

    val = val.split("|");
    return val[0];
}

function gf_do_field_action(formId, action, fieldId, isInit, callback){
    var conditional_logic = window["gf_form_conditional_logic"][formId];
    var dependent_fields = conditional_logic["dependents"][fieldId];

    for(var i=0; i < dependent_fields.length; i++){
        var targetId = fieldId == 0 ? "#gform_submit_button_" + formId : "#field_" + formId + "_" + dependent_fields[i];

        //calling callback function on the last dependent field, to make sure it is only called once
        do_callback = (i+1) == dependent_fields.length ? callback : null;

        gf_do_action(action, targetId, conditional_logic["animation"], conditional_logic["defaults"][dependent_fields[i]], isInit, do_callback);
    }
}

function gf_do_next_button_action(formId, action, fieldId, isInit){
    var conditional_logic = window["gf_form_conditional_logic"][formId];
    var targetId = "#gform_next_button_" + formId + "_" + fieldId;

    gf_do_action(action, targetId, conditional_logic["animation"], null, isInit);
}

function gf_do_action(action, targetId, useAnimation, defaultValues, isInit, callback){
	var $target = jQuery(targetId);
	if(action == "show"){
		if(useAnimation && !isInit){
			if($target.length > 0){
				$target.slideDown(callback);
			} else if(callback){
				callback();
			}
		}
		else{
			//$target.show();
			//Getting around an issue with Chrome on Android. Does not like jQuery('xx').show() ...
			$target.css('display', 'block');

			if(callback){
				callback();
			}
		}
	}
	else{
		//if field is not already hidden, reset its values to the default
		var child = $target.children().first();
		if (child.length > 0){
			if(!gformIsHidden(child)){
				gf_reset_to_default(targetId, defaultValues);
			}
		}

		if(useAnimation && !isInit){
			if($target.length > 0 && $target.is(":visible")) {
				$target.slideUp(callback);
			} else if(callback) {
				callback();
			}
		} else{
			$target.hide();
			if(callback){
				callback();
			}
		}
	}
}

function gf_reset_to_default(targetId, defaultValue){

    var dateFields = jQuery(targetId).find('.gfield_date_month input[type="text"], .gfield_date_day input[type="text"], .gfield_date_year input[type="text"], .gfield_date_dropdown_month select, .gfield_date_dropdown_day select, .gfield_date_dropdown_year select');
    var dateIndex = 0;
    if(dateFields.length > 0){
        dateFields.each(function(){
            if(defaultValue){
                val = defaultValue.split(/[\.\/-]+/)[dateIndex];
                dateIndex++;
            }
            else{
                val = "";
            }

            var element = jQuery(this);
            if(element.prop("tagName") == "SELECT")
                val = parseInt(val);


            if(element.val() != val)
                element.val(val).trigger("change");
            else
                element.val(val);

        });

        return;
    }

    //cascading down conditional logic to children to suppport nested conditions
    //text fields and drop downs
    var target = jQuery(targetId).find('select, input[type="text"], input[type="number"], textarea');

    var target_index = 0;

    target.each(function(){
        var val = "";

        var element = jQuery(this);
        if(element.is('select:not([multiple])')){
            val = element.find('option' ).not( ':disabled' ).eq(0).val();
        }

        //get name of previous input field to see if it is the radio button which goes with the "Other" text box
        //otherwise field is populated with input field name
        var radio_button_name = element.prev("input").attr("value");
        if(radio_button_name == "gf_other_choice"){
        	val = element.attr("value");
        }
        else if(jQuery.isArray(defaultValue)){
            val = defaultValue[target_index];
        }
        else if(jQuery.isPlainObject(defaultValue)){
            val = defaultValue[element.attr("name")];
        }
        else if(defaultValue){

            val = defaultValue;

        }

        if(element.val() != val)
            element.val(val).trigger('change');
        else
            element.val(val);

        target_index++;
    });

    //checkboxes and radio buttons
    var elements = jQuery(targetId).find('input[type="radio"], input[type="checkbox"]');

    elements.each(function(){

        //is input currently checked?
        var isChecked = jQuery(this).is(':checked') ? true : false;

        //does input need to be marked as checked or unchecked?
        var doCheck = defaultValue ? jQuery.inArray(jQuery(this).attr('id'), defaultValue) > -1 : false;

        //if value changed, trigger click event
        if(isChecked != doCheck){
            //setting input as checked or unchecked appropriately

            if(jQuery(this).attr("type") == "checkbox"){
                jQuery(this).trigger('click');
            }
            else{
                jQuery(this).prop("checked", doCheck);

                //need to set the prop again after the click is triggered
                jQuery(this).trigger('click').prop('checked', doCheck);
            }

        }
    });

}
