// "prop" method fix for previous versions of jQuery
var originalPropMethod = jQuery.fn.prop;

jQuery.fn.prop = function() {
    if(typeof originalPropMethod == 'undefined') {
        return jQuery.fn.attr.apply(this, arguments);
    } else {
        return originalPropMethod.apply(this, arguments);
    }
}


//Formatting free form currency fields to currency
jQuery(document).ready(function(){
    jQuery(document).bind('gform_post_render', gformBindFormatPricingFields);
});

function gformBindFormatPricingFields(){
    jQuery(".ginput_amount, .ginput_donation_amount").bind("change", function(){
        gformFormatPricingField(this);
    });

    jQuery(".ginput_amount, .ginput_donation_amount").each(function(){
        gformFormatPricingField(this);
    });
}



//------------------------------------------------
//---------- CURRENCY ----------------------------
//------------------------------------------------
function Currency(currency){
    this.currency = currency;

    this.toNumber = function(text){
        if(this.isNumeric(text))
            return parseFloat(text);

        return gformCleanNumber(text, this.currency["symbol_right"], this.currency["symbol_left"], this.currency["decimal_separator"]);
    };

    this.toMoney = function(number){
        if(!this.isNumeric(number))
            number = this.toNumber(number);

        if(number === false)
            return "";

        number = number + "";
        negative = "";
        if(number[0] == "-"){
            negative = "-";
            number = parseFloat(number.substr(1));
        }
        money = this.numberFormat(number, this.currency["decimals"], this.currency["decimal_separator"], this.currency["thousand_separator"]);

        var symbol_left = this.currency["symbol_left"] ? this.currency["symbol_left"] + this.currency["symbol_padding"] : "";
        var symbol_right = this.currency["symbol_right"] ? this.currency["symbol_padding"] + this.currency["symbol_right"] : "";
        money =  negative + this.htmlDecode(symbol_left) + money + this.htmlDecode(symbol_right);
        return money;
    };

    this.numberFormat = function(number, decimals, dec_point, thousands_sep, padded){
        var padded = typeof padded == 'undefined';
        number = (number+'').replace(',', '').replace(' ', '');
        var n = !isFinite(+number) ? 0 : +number,
        prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
        sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep, dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
        s = '',

        toFixedFix = function (n, prec) {
            var k = Math.pow(10, prec);
            return '' + Math.round(n * k) / k;
        };

        if(decimals == '0') {
            s = ('' + Math.round(n)).split('.');
        } else
        if(decimals == -1) {
            s = ('' + n).split('.');
        } else {
            // Fix for IE parseFloat(0.55).toFixed(0) = 0;
            s = toFixedFix(n, prec).split('.');
        }

        if (s[0].length > 3) {
            s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        }

        if(padded) {
            if ((s[1] || '').length < prec) {
                s[1] = s[1] || '';
                s[1] += new Array(prec - s[1].length + 1).join('0');
            }
        }

        return s.join(dec);
    }

    this.isNumeric = function(number){
        return gformIsNumber(number);
    };

    this.htmlDecode = function(text) {
        var c,m,d = text;

        // look for numerical entities &#34;
        var arr=d.match(/&#[0-9]{1,5};/g);

        // if no matches found in string then skip
        if(arr!=null){
            for(var x=0;x<arr.length;x++){
                m = arr[x];
                c = m.substring(2,m.length-1); //get numeric part which is refernce to unicode character
                // if its a valid number we can decode
                if(c >= -32768 && c <= 65535){
                    // decode every single match within string
                    d = d.replace(m, String.fromCharCode(c));
                }else{
                    d = d.replace(m, ""); //invalid so replace with nada
                }
            }
        }
        return d;
    };
}

function gformCleanNumber(text, symbol_right, symbol_left, decimal_separator){
    //converting to a string if a number as passed
    text = text + " ";

    //Removing symbol in unicode format (i.e. &#4444;)
    text = text.replace(/&.*?;/, "", text);

    //Removing symbol from text
    text = text.replace(symbol_right, "");
    text = text.replace(symbol_left, "");


    //Removing all non-numeric characters
    var clean_number = "";
    var is_negative = false;
    for(var i=0; i<text.length; i++){
        var digit = text.substr(i,1);
        if( (parseInt(digit) >= 0 && parseInt(digit) <= 9) || digit == decimal_separator )
            clean_number += digit;
        else if(digit == '-')
            is_negative = true;
    }

    //Removing thousand separators but keeping decimal point
    var float_number = "";

    for(var i=0; i<clean_number.length; i++)
    {
        var char = clean_number.substr(i,1);
        if (char >= '0' && char <= '9')
            float_number += char;
        else if(char == decimal_separator){
            float_number += ".";
        }
    }

    if(is_negative)
        float_number = "-" + float_number;

    return gformIsNumber(float_number) ? parseFloat(float_number) : false;
}

function gformIsNumber(n) {
  return !isNaN(parseFloat(n)) && isFinite(n);
}

function gformIsNumeric(value, number_format){

    switch(number_format){
        case "decimal_dot" :
            var r = new RegExp("^(-?[0-9]{1,3}(?:,?[0-9]{3})*(?:\.[0-9]+)?)$");
            return r.test(value);
        break;

        case "decimal_comma" :
            var r = new RegExp("^(-?[0-9]{1,3}(?:\.?[0-9]{3})*(?:,[0-9]+)?)$");
            return r.test(value);
        break;
    }
    return false;
}

//------------------------------------------------
//---------- MULTI-PAGE --------------------------
//------------------------------------------------
function gformDeleteUploadedFile(formId, fieldId){
    var parent = jQuery("#field_" + formId + "_" + fieldId);

    //hiding preview
    parent.find(".ginput_preview").hide();

    //displaying file upload field
    parent.find("input[type=\"file\"]").removeClass("gform_hidden");

    //displaying post image label
    parent.find(".ginput_post_image_file").show();

    //clearing post image meta fields
    parent.find("input[type=\"text\"]").val('');

    //removing file from uploaded meta
    var files = jQuery.secureEvalJSON(jQuery('#gform_uploaded_files_' + formId).val());
    if(files){
        files["input_" + fieldId] = null;
        jQuery('#gform_uploaded_files_' + formId).val(jQuery.toJSON(files));
    }
}


//------------------------------------------------
//---------- PRICE -------------------------------
//------------------------------------------------
var _gformPriceFields = new Array();
var _anyProductSelected;

function gformIsHidden(element){
    return element.parents('.gfield').not(".gfield_hidden_product").css("display") == "none";
}

function gformCalculateTotalPrice(formId){

    if(!_gformPriceFields[formId])
        return;

    var price = 0;

    _anyProductSelected = false; //Will be used by gformCalculateProductPrice().
    for(var i=0; i<_gformPriceFields[formId].length; i++){
        price += gformCalculateProductPrice(formId, _gformPriceFields[formId][i]);
    }

    //add shipping price if a product has been selected
    if(_anyProductSelected){
        //shipping price
        var shipping = gformGetShippingPrice(formId)
        price += shipping;
    }

    //gform_product_total filter. Allows uers to perform custom price calculation
    if(window["gform_product_total"])
        price = window["gform_product_total"](formId, price);

    //updating total
    var totalElement = jQuery(".ginput_total_" + formId);
    if(totalElement.length > 0){
        totalElement.next().val(price);
        totalElement.html(gformFormatMoney(price));
    }
}

function gformGetShippingPrice(formId){
    var shippingField = jQuery(".gfield_shipping_" + formId + " input[type=\"hidden\"], .gfield_shipping_" + formId + " select, .gfield_shipping_" + formId + " input:checked");
    var shipping = 0;
    if(shippingField.length == 1 && !gformIsHidden(shippingField)){
        if(shippingField.attr("type") && shippingField.attr("type").toLowerCase() == "hidden")
            shipping = shippingField.val();
        else
            shipping = gformGetPrice(shippingField.val());
    }

    return gformToNumber(shipping);
}

function gformGetFieldId(element){
    var id = jQuery(element).attr("id");
    var pieces = id.split("_");
    if(pieces.length <=0)
        return 0;

    var fieldId = pieces[pieces.length-1];
    return fieldId;

}

function gformCalculateProductPrice(formId, productFieldId){
    var price = gformGetBasePrice(formId, productFieldId);

    var suffix = "_" + formId + "_" + productFieldId;

    //Drop down auto-calculating labels
    jQuery(".gfield_option" + suffix + ", .gfield_shipping_" + formId).find("select").each(function(){
        var selected_price = gformGetPrice(jQuery(this).val());
        var fieldId = gformGetFieldId(this);
        jQuery(this).children("option").each(function(){
            var label = gformGetOptionLabel(this, jQuery(this).val(), selected_price, formId, fieldId);
            jQuery(this).html(label);
        });
    });

    //Checkboxes labels with prices
    jQuery(".gfield_option" + suffix).find(".gfield_checkbox").find("input").each(function(){
        var fieldId = gformGetFieldId(jQuery(this).parents(".gfield_checkbox"));
        var element = jQuery(this).next();
        var label = gformGetOptionLabel(element, jQuery(this).val(), 0, formId, fieldId);
        element.html(label);
    });

    //Radio button auto-calculating lables
    jQuery(".gfield_option" + suffix + ", .gfield_shipping_" + formId).find(".gfield_radio").each(function(){
        var selected_price = 0;
        var selected_value = jQuery(this).find("input:checked").val();
        var fieldId = gformGetFieldId(this);
        if(selected_value)
            selected_price = gformGetPrice(selected_value);

        jQuery(this).find("input").each(function(){
            var label_element = jQuery(this).next();
            var label = gformGetOptionLabel(label_element, jQuery(this).val(), selected_price, formId, fieldId);
            label_element.html(label);
        });
    });

    jQuery(".gfield_option" + suffix).find("input:checked, select").each(function(){
        if(!gformIsHidden(jQuery(this)))
            price += gformGetPrice(jQuery(this).val());
    });

    var quantity;
    var quantityInput = jQuery("#ginput_quantity_" + formId + "_" + productFieldId);
    if(quantityInput.length > 0){
        quantity = !gformIsNumber(quantityInput.val()) ? 0 : quantityInput.val();
    }
    else{
        quantityElement = jQuery(".gfield_quantity_" + formId + "_" + productFieldId);

        quantity = 1;
        if (quantityElement.find("select").length > 0)
            quantity = quantityElement.find("select").val();
        else if(quantityElement.find("input").length > 0)
            quantity = quantityElement.find("input").val();

        if(!gformIsNumber(quantity))
            quantity = 0;

    }
    quantity = parseFloat(quantity);

    //setting global variable if quantity is more than 0 (a product was selected). Will be used when calculating total
    if(quantity > 0)
        _anyProductSelected = true;

    price = price * quantity;
    price = Math.round(price * 100) / 100;

    return price;
}

function gformGetBasePrice(formId, productFieldId){

    var suffix = "_" + formId + "_" + productFieldId;
    var price = 0;
    var productField = jQuery("#ginput_base_price" + suffix+ ", .gfield_donation" + suffix + " input[type=\"text\"], .gfield_product" + suffix + " .ginput_amount");
    if(productField.length > 0){
        price = productField.val();

        //If field is hidden by conditional logic, don't count it for the total
        if(gformIsHidden(productField)){
            price = 0;
        }
    }
    else
    {
        productField = jQuery(".gfield_product" + suffix + " select, .gfield_product" + suffix + " input:checked, .gfield_donation" + suffix + " select, .gfield_donation" + suffix + " input:checked");
        var val = productField.val();
        if(val){
            val = val.split("|");
            price = val.length > 1 ? val[1] : 0;
        }

        //If field is hidden by conditional logic, don't count it for the total
        if(gformIsHidden(productField))
            price = 0;

    }

    var c = new Currency(gf_global.gf_currency_config);
    price = c.toNumber(price);
    return price === false ? 0 : price;
}

function gformFormatMoney(text){
    if(!gf_global.gf_currency_config)
        return text;

    var currency = new Currency(gf_global.gf_currency_config);
    return currency.toMoney(text);
}

function gformFormatPricingField(element){
    if(gf_global.gf_currency_config){
        var currency = new Currency(gf_global.gf_currency_config);
        var price = currency.toMoney(jQuery(element).val());
        jQuery(element).val(price);
    }
}

function gformToNumber(text){
    var currency = new Currency(gf_global.gf_currency_config);
    return currency.toNumber(text);
}

function gformGetPriceDifference(currentPrice, newPrice){

    //getting price difference
    var diff = parseFloat(newPrice) - parseFloat(currentPrice);
    price = gformFormatMoney(diff);
    if(diff > 0)
        price = "+" + price;

    return price;
}

function gformGetOptionLabel(element, selected_value, current_price, form_id, field_id){
    element = jQuery(element);
    var price = gformGetPrice(selected_value);
    var current_diff = element.attr('price');
    var original_label = element.html().replace(/<span(.*)<\/span>/i, "").replace(current_diff, "");

    var diff = gformGetPriceDifference(current_price, price);
    diff = gformToNumber(diff) == 0 ? "" : " " + diff;
    element.attr('price', diff);

    //don't add <span> for drop down items (not supported)
    var price_label = element[0].tagName.toLowerCase() == "option" ? " " + diff : "<span class='ginput_price'>" + diff + "</span>";
    var label = original_label + price_label;

    //calling hook to allow for custom option formatting
    if(window["gform_format_option_label"])
        label = gform_format_option_label(label, original_label, price_label, current_price, price, form_id, field_id);

    return label;
}

function gformGetProductIds(parent_class, element){
    var classes = jQuery(element).hasClass(parent_class) ? jQuery(element).attr("class").split(" ") : jQuery(element).parents("." + parent_class).attr("class").split(" ");
    for(var i=0; i<classes.length; i++){
        if(classes[i].substr(0, parent_class.length) == parent_class && classes[i] != parent_class)
            return {formId: classes[i].split("_")[2], productFieldId: classes[i].split("_")[3]};
    }
    return {formId:0, fieldId:0};
}

function gformGetPrice(text){
    var val = text.split("|");
    var currency = new Currency(gf_global.gf_currency_config);

    if(val.length > 1 && currency.toNumber(val[1]) !== false)
         return currency.toNumber(val[1]);

    return 0;
}

function gformRegisterPriceField(item){

    if(!_gformPriceFields[item.formId])
        _gformPriceFields[item.formId] = new Array();

    //ignore price fields that have already been registered
    for(var i=0; i<_gformPriceFields[item.formId].length; i++)
        if(_gformPriceFields[item.formId][i] == item.productFieldId)
            return;

    //registering new price field
    _gformPriceFields[item.formId].push(item.productFieldId);
}

function gformInitPriceFields(){

    jQuery(".gfield_price").each(function(){

        var productIds = gformGetProductIds("gfield_price", this);
        gformRegisterPriceField(productIds);

       jQuery(this).find("input[type=\"text\"], input[type=\"number\"], select").change(function(){

           var productIds = gformGetProductIds("gfield_price", this);
           if(productIds.formId == 0)
                productIds = gformGetProductIds("gfield_shipping", this);

           jQuery(document).trigger('gform_price_change', [productIds, this]);
           gformCalculateTotalPrice(productIds.formId);
       });

       jQuery(this).find("input[type=\"radio\"], input[type=\"checkbox\"]").click(function(){
           var productIds = gformGetProductIds("gfield_price", this);
           if(productIds.formId == 0)
                productIds = gformGetProductIds("gfield_shipping", this);

           jQuery(document).trigger('gform_price_change', [productIds, this]);
           gformCalculateTotalPrice(productIds.formId);
       });

    });

    for(formId in _gformPriceFields)
        gformCalculateTotalPrice(formId);

}


//-------------------------------------------
//---------- PASSWORD -----------------------
//-------------------------------------------
function gformShowPasswordStrength(fieldId){
    var password = jQuery("#" + fieldId).val();
    var confirm = jQuery("#" + fieldId + "_2").val();

    var result = gformPasswordStrength(password, confirm);

    var text = window['gf_text']["password_" + result];

    jQuery("#" + fieldId + "_strength").val(result);
    jQuery("#" + fieldId + "_strength_indicator").removeClass("blank mismatch short good bad strong").addClass(result).html(text);
}

// Password strength meter
function gformPasswordStrength(password1, password2) {
    var shortPass = 1, badPass = 2, goodPass = 3, strongPass = 4, mismatch = 5, symbolSize = 0, natLog, score;

    if(password1.length <=0)
        return "blank";

    // password 1 != password 2
    if ( (password1 != password2) && password2.length > 0)
        return "mismatch";

    //password < 4
    if ( password1.length < 4 )
        return "short";

    if ( password1.match(/[0-9]/) )
        symbolSize +=10;
    if ( password1.match(/[a-z]/) )
        symbolSize +=26;
    if ( password1.match(/[A-Z]/) )
        symbolSize +=26;
    if ( password1.match(/[^a-zA-Z0-9]/) )
        symbolSize +=31;

    natLog = Math.log( Math.pow(symbolSize, password1.length) );
    score = natLog / Math.LN2;

    if (score < 40 )
        return "bad";

    if (score < 56 )
        return "good";

    return "strong";

}

//----------------------------
//------ LIST FIELD ----------
//----------------------------
var gfield_original_title = "";
function gformAddListItem(element, max){

    if(jQuery(element).hasClass("gfield_icon_disabled"))
        return;

    var tr = jQuery(element).parent().parent();
    var clone = tr.clone();
    clone.find("input, select").val("").attr("tabindex", clone.find('input:last').attr("tabindex"));
    tr.after(clone);
    gformToggleIcons(tr.parent(), max);
    gformAdjustClasses(tr.parent());
}

function gformDeleteListItem(element, max){
    var tr = jQuery(element).parent().parent();
    var parent = tr.parent();
    tr.remove();
    gformToggleIcons(parent, max);
    gformAdjustClasses(parent);
}

function gformAdjustClasses(table){
    var rows = table.children();
    for(var i=0; i<rows.length; i++){
        var odd_even_class = (i+1) % 2 == 0 ? "gfield_list_row_even" : "gfield_list_row_odd";
        jQuery(rows[i]).removeClass("gfield_list_row_odd").removeClass("gfield_list_row_even").addClass(odd_even_class);
    }
}

function gformToggleIcons(table, max){
    var rowCount = table.children().length;
    if(rowCount == 1){
        table.find(".delete_list_item").css("visibility", "hidden");
    }
    else{
        table.find(".delete_list_item").css("visibility", "visible");
    }

    if(max > 0 && rowCount >= max){
        gfield_original_title = table.find(".add_list_item:first").attr("title");
        table.find(".add_list_item").addClass("gfield_icon_disabled").attr("title", "");
    }
    else{
        var addIcons = table.find(".add_list_item");
        addIcons.removeClass("gfield_icon_disabled");
        if(gfield_original_title)
            addIcons.attr("title", gfield_original_title);
    }
}


//-----------------------------------
//------ CREDIT CARD FIELD ----------
//-----------------------------------
function gformMatchCard(id) {

    var cardType = gformFindCardType(jQuery('#' + id).val());
    var cardContainer = jQuery('#' + id).parents('.gfield').find('.gform_card_icon_container');

    if(!cardType) {

        jQuery(cardContainer).find('.gform_card_icon').removeClass('gform_card_icon_selected gform_card_icon_inactive');

    } else {

        jQuery(cardContainer).find('.gform_card_icon').removeClass('gform_card_icon_selected').addClass('gform_card_icon_inactive');
        jQuery(cardContainer).find('.gform_card_icon_' + cardType).removeClass('gform_card_icon_inactive').addClass('gform_card_icon_selected');
    }
}

function gformFindCardType(value) {

    if(value.length < 4)
        return false;

    var rules = window['gf_cc_rules'];
    var validCardTypes = new Array();

    for(type in rules) {
        for(i in rules[type]) {

            if(rules[type][i].indexOf(value.substring(0, rules[type][i].length)) === 0) {
                validCardTypes[validCardTypes.length] = type;
                break;
            }

        }
    }

    return validCardTypes.length == 1 ? validCardTypes[0].toLowerCase() : false;
}

function gformToggleCreditCard(){
    if(jQuery("#gform_payment_method_creditcard").is(":checked"))
        jQuery(".gform_card_fields_container").slideDown();
    else
        jQuery(".gform_card_fields_container").slideUp();
}


//----------------------------------------
//------ CHOSEN DROP DOWN FIELD ----------
//----------------------------------------

function gformInitChosenFields(fieldList, noResultsText){
    return jQuery(fieldList).each(function(){

        var element = jQuery(this);

        //only initialize once
        if( element.is(":visible") && element.siblings(".chzn-container").length == 0 ){
            var options = gform.applyFilters( 'gform_chosen_options', { no_results_text: noResultsText }, element );
            element.chosen( options );
        }

    });
}



//----------------------------------------
//------ CALCULATION FUNCTIONS -----------
//----------------------------------------

var GFCalc = function(formId, formulaFields){

    this.patt = /{[^{]*?:(\d+(\.\d+)?)(:(.*?))?}/i;
    this.exprPatt = /^[0-9 -/*\(\)]+$/i;
    this.isCalculating = {};

    this.init = function(formId, formulaFields) {
        var calc = this;
        jQuery(document).bind("gform_post_conditional_logic", function(){
            for(var i=0; i<formulaFields.length; i++) {
                var formulaField = jQuery.extend({}, formulaFields[i]);
                calc.runCalc(formulaField, formId);
            }
        });

        for(var i=0; i<formulaFields.length; i++) {
            var formulaField = jQuery.extend({}, formulaFields[i]);
            this.runCalc(formulaField, formId);
            this.bindCalcEvents(formulaField, formId);
        }

    }

    this.runCalc = function(formulaField, formId) {

        var calcObj = this;
        var formulaInput, expr;

        var field = jQuery('#field_' + formId + '_' + formulaField.field_id);
        formulaInput = jQuery('#input_' + formId + '_' + formulaField.field_id);
        var previous_val = formulaInput.val();

        expr = calcObj.replaceFieldTags(formId, formulaField.formula, formulaField.numberFormat);
        result = '';

        if(calcObj.exprPatt.test(expr)) {
            try {

                //run calculation
                result = eval(expr);

            } catch (e) {}
        }

        // allow users to modify result with their own function
        if(window["gform_calculation_result"])
            result = window["gform_calculation_result"](result, formulaField, formId, calcObj);

        //formatting number
        if(field.hasClass('gfield_price')) {
            result = gformFormatMoney(result ? result : 0);
        }
        else{

            var decimalSeparator, thousandSeparator;
            if(formulaField.numberFormat == "decimal_comma"){
                decimalSeparator = ",";
                thousandSeparator = ".";
            }
            else if(formulaField.numberFormat == "decimal_dot"){
                decimalSeparator = ".";
                thousandSeparator = ",";
            }
            result = gformFormatNumber(result, !gformIsNumber(formulaField.rounding) ? -1 : formulaField.rounding, decimalSeparator, thousandSeparator);
        }

        //If value doesn't change, abort.
        //This is needed to prevent an infinite loop condition with conditional logic
        if(result == previous_val)
            return;

        // if this is a calucation product, handle differently
        if(field.hasClass('gfield_price')) {

            formulaInput.text(result);
            jQuery('#ginput_base_price_' + formId + '_' + formulaField.field_id).val(result).trigger('change');
            gformCalculateTotalPrice(formId);
        } else {
            formulaInput.val(result).trigger('change');
        }

    }

    this.bindCalcEvents = function(formulaField, formId) {

        var calcObj = this;
        var formulaFieldId = formulaField.field_id;
        var matches = getMatchGroups(formulaField.formula, this.patt);

        calcObj.isCalculating[formulaFieldId] = false;

        for(i in matches) {

            var inputId = matches[i][1];
            var fieldId = parseInt(inputId);
            var input = jQuery('#field_' + formId + '_' + fieldId).find('input[name="input_' + inputId + '"], select[name="input_' + inputId + '"]');

            if(input.prop('type') == 'checkbox' || input.prop('type') == 'radio') {
                jQuery(input).click(function(){
                    calcObj.bindCalcEvent(inputId, formulaField, formId, 0);
                });
            } else
            if(input.is('select') || input.prop('type') == 'hidden') {
                jQuery(input).change(function(){
                    calcObj.bindCalcEvent(inputId, formulaField, formId, 0);
                });
            } else {
                jQuery(input).keydown(function(){
                    calcObj.bindCalcEvent(inputId, formulaField, formId);
                }).change(function(){
                    calcObj.bindCalcEvent(inputId, formulaField, formId, 0);
                });
            }

        }

    }

    this.bindCalcEvent = function(inputId, formulaField, formId, delay) {

        var calcObj = this;
        var formulaFieldId = formulaField.field_id;

        delay = delay == undefined ? 345 : delay;

        if(calcObj.isCalculating[formulaFieldId][inputId])
            clearTimeout(calcObj.isCalculating[formulaFieldId][inputId]);

        calcObj.isCalculating[formulaFieldId][inputId] = window.setTimeout(function() {
            calcObj.runCalc(formulaField, formId);
        }, delay);

    }

    this.replaceFieldTags = function(formId, expr, numberFormat) {

        var matches = getMatchGroups(expr, this.patt);
        var origExpr = expr;

        for(i in matches) {

            var inputId = matches[i][1];
            var fieldId = parseInt(inputId);
            var columnId = matches[i][3];
            var value = 0;

            var input = jQuery('#field_' + formId + '_' + fieldId).find('input[name="input_' + inputId + '"], select[name="input_' + inputId + '"]');

            // radio buttons will return multiple inputs, checkboxes will only return one but it may not be selected, filter out unselected inputs
            if(input.length > 1 || input.prop('type') == 'checkbox')
                input = input.filter(':checked');

            var isVisible = window["gf_check_field_rule"] ? gf_check_field_rule(formId, fieldId, true, "") == "show" : true;

            if(input.length > 0 && isVisible) {

                var val = input.val();
                val = val.split('|');

                if(val.length > 1) {
                    value = val[1];
                } else {
                    value = input.val();
                }
            }

            var decimalSeparator = ".";
            if(numberFormat == "decimal_comma"){
                decimalSeparator = ",";
            }
            else if(numberFormat == "decimal_dot"){
                decimalSeparator = ".";
            }
            else if(window['gf_global']){
                var inputType = input.attr("type");
                var isDropDown = jQuery('#field_' + formId + '_' + fieldId).find('select[name="input_' + inputId + '"]').length > 0;

                var isNumericFormat = inputType == "checkbox" || inputType == "radio" || isDropDown;

                //checkboxes, radio buttons and drop downs use the standard number notation and not the currency format
                if(!isNumericFormat){
                    var currency = new Currency(gf_global.gf_currency_config);
                    decimalSeparator = currency.currency["decimal_separator"];
                }
            }

            value = gformCleanNumber(value, "", "", decimalSeparator);
            if(!value)
                value = 0;

            expr = expr.replace(matches[i][0], value);
        }

        return expr;
    }

    this.init(formId, formulaFields);

}

function gformFormatNumber(number, rounding, decimalSeparator, thousandSeparator){

    if(typeof decimalSeparator == "undefined"){
        if(window['gf_global']){
            var currency = new Currency(gf_global.gf_currency_config);
            decimalSeparator = currency.currency["decimal_separator"];
        }
        else{
            decimalSeparator = ".";
        }
    }

    if(typeof thousandSeparator == "undefined"){
        if(window['gf_global']){
            var currency = new Currency(gf_global.gf_currency_config);
            thousandSeparator = currency.currency["thousand_separator"];
        }
        else{
            thousandSeparator = ",";
        }
    }

    var currency = new Currency();
    return currency.numberFormat(number, rounding, decimalSeparator, thousandSeparator, false)
}

function gformToNumber(text) {
    var currency = new Currency(gf_global.gf_currency_config);
    return currency.toNumber(text);
}

function getMatchGroups(expr, patt) {

    var matches = new Array();

    while(patt.test(expr)) {

        var i = matches.length;
        matches[i] = patt.exec(expr)
        expr = expr.replace('' + matches[i][0], '');

    }

    return matches;
}

//javascript hook functions
var gform = {
	hooks: { action: {}, filter: {} },
	addAction: function( action, callable, priority, tag ) {
		gform.addHook( 'action', action, callable, priority, tag );
	},
	addFilter: function( action, callable, priority, tag ) {
		gform.addHook( 'filter', action, callable, priority, tag );
	},
	doAction: function( action ) {
		gform.doHook( 'action', action, arguments );
	},
	applyFilters: function( action ) {
		return gform.doHook( 'filter', action, arguments );
	},
	removeAction: function( action, tag ) {
		gform.removeHook( 'action', action, tag );
	},
	removeFilter: function( action, priority, tag ) {
		gform.removeHook( 'filter', action, priority, tag );
	},
	addHook: function( hookType, action, callable, priority, tag ) {
		if ( undefined == gform.hooks[hookType][action] ) {
			gform.hooks[hookType][action] = [];
		}
		var hooks = gform.hooks[hookType][action];
		if ( undefined == tag ) {
			tag = action + '_' + hooks.length;
		}
		gform.hooks[hookType][action].push( { tag:tag, callable:callable, priority:priority } );
	},
	doHook: function( hookType, action, args ) {

        // splice args from object into array and remove first index which is the hook name
        args = Array.prototype.slice.call(args, 1);

		if ( undefined != gform.hooks[hookType][action] ) {
			var hooks = gform.hooks[hookType][action], hook;
			//sort by priority
			hooks.sort(function(a,b){return a["priority"]-b["priority"]});
			for( var i=0; i<hooks.length; i++) {
                hook = hooks[i].callable;
                if(typeof hook != 'function')
                    hook = window[hook];
				if ( 'action' == hookType ) {
                    hook.apply(null, args);
				} else {
                    args[0] = hook.apply(null, args);
				}
			}
		}
		if ( 'filter'==hookType ) {
			return args[0];
		}
	},
	removeHook: function( hookType, action, priority, tag ) {
		if ( undefined != gform.hooks[hookType][action] ) {
			var hooks = gform.hooks[hookType][action];
			for( var i=hooks.length-1; i>=0; i--) {
				if ((undefined==tag||tag==hooks[i].tag) && (undefined==priority||priority==hooks[i].priority)){
					hooks.splice(i,1);
				}
			}
		}
	}
};
//end of javascript hook functions