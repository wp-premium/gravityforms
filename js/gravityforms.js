
// "prop" method fix for previous versions of jQuery (1.5 and below)
if( typeof jQuery.fn.prop === 'undefined' ) {
    jQuery.fn.prop = jQuery.fn.attr;
}

jQuery(document).ready(function(){
    //Formatting free form currency fields to currency
    jQuery(document).bind('gform_post_render', gformBindFormatPricingFields);
});

function gformBindFormatPricingFields(){
	// Namespace the event and remove before adding to prevent double binding.
    jQuery(".ginput_amount, .ginput_donation_amount").off('change.gform').on("change.gform", function(){
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

        if(this.isNumeric(text)) {
			return parseFloat(text);
		}

        return gformCleanNumber(text, this.currency["symbol_right"], this.currency["symbol_left"], this.currency["decimal_separator"]);
    };

	/**
	 * Attempts to clean the specified number and formats it as currency.
	 *
	 * @since 2.1.1.16 Allow the overriding of numerical checks.
	 *
	 * @param number    int  Number to be formatted. It can be a clean number, or an already formatted number.
	 * @param isNumeric bool Whether or not the number is guaranteed to be a clean, unformatted number.
	 *                       When false the function will attempt to clean the number. Defaults to false.
	 *
	 * @return string A number formatted as currency.
	 */
	this.toMoney = function(number, isNumeric){

		isNumeric = isNumeric || false; //isNumeric is an optional parameter. Defaults to false

		if( ! isNumeric ) {
			//Cleaning number, removing all formatting
			number = gformCleanNumber(number, this.currency["symbol_right"], this.currency["symbol_left"], this.currency["decimal_separator"]);
		}

		if(number === false) {
			return "";
		}

        number = number + "";
        negative = "";
        if(number[0] == "-"){

            number = parseFloat(number.substr(1));
			negative = '-';
		}

        money = this.numberFormat(number, this.currency["decimals"], this.currency["decimal_separator"], this.currency["thousand_separator"]);

		if ( money == '0.00' ){
			negative = '';
		}

        var symbol_left = this.currency["symbol_left"] ? this.currency["symbol_left"] + this.currency["symbol_padding"] : "";
        var symbol_right = this.currency["symbol_right"] ? this.currency["symbol_padding"] + this.currency["symbol_right"] : "";

		money =  negative + this.htmlDecode(symbol_left) + money + this.htmlDecode(symbol_right);

		return money;
    };


	/**
	 * Formats a number given the specified parameters.
	 *
	 * @since Unknown
	 *
	 * @param number        int    Number to be formatted. Must be a clean, unformatted  format.
	 * @param decimals      int    Number of decimals that the output should contain.
	 * @param dec_point     string Character to use as the decimal separator. Defaults to ".".
	 * @param thousands_sep string Character to use as the thousand separator. Defaults to ",".
	 * @param padded        bool   Pads output with zeroes if the number is exact. For example, 1.200.
	 *
	 * @return string The formatted number.
	 */
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

            n = n + 0.0000000001; // getting around floating point arithmetic issue when rounding. ( i.e. 4.005 is represented as 4.004999999999 and gets rounded to 4.00 instead of 4.01 )

            s = ('' + Math.round(n)).split('.');
        } else
        if(decimals == -1) {
            s = ('' + n).split('.');
        } else {

            n = n + 0.0000000001; // getting around floating point arithmetic issue when rounding. ( i.e. 4.005 is represented as 4.004999999999 and gets rounded to 4.00 instead of 4.01 )

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

/**
 * Gets a formatted number and returns a clean "decimal dot" number.
 *
 * Note: Input must be formatted according to the specified parameters (symbol_right, symbol_left, decimal_separator).
 * @example input -> $1.20, output -> 1.2
 *
 * @since 2.1.1.16 Modified to support additional param in Currency.toMoney.
 *
 * @param text              string The currency-formatted number.
 * @param symbol_right      string The symbol used on the right.
 * @param symbol_left       string The symbol used on the left.
 * @param decimal_separator string The decimal separator being used.
 *
 * @return float The unformatted numerical value.
 */
function gformCleanNumber(text, symbol_right, symbol_left, decimal_separator){
    var clean_number = '',
        float_number = '',
        digit = '',
        is_negative = false;

    //converting to a string if a number as passed
    text = text + " ";

    //Removing symbol in unicode format (i.e. &#4444;)
    text = text.replace(/&.*?;/g, "");

    //Removing symbol from text
    text = text.replace(symbol_right, "");
    text = text.replace(symbol_left, "");

    //Removing all non-numeric characters
    for(var i=0; i<text.length; i++){
        digit = text.substr(i,1);
        if( (parseInt(digit,10) >= 0 && parseInt(digit,10) <= 9) || digit == decimal_separator )
            clean_number += digit;
        else if(digit == '-')
            is_negative = true;
    }

    //Removing thousand separators but keeping decimal point
    for(var i=0; i<clean_number.length; i++) {
        digit = clean_number.substr(i,1);
        if (digit >= '0' && digit <= '9')
            float_number += digit;
        else if(digit == decimal_separator){
            float_number += ".";
        }
    }

    if(is_negative)
        float_number = "-" + float_number;

    return gformIsNumber(float_number) ? parseFloat(float_number) : false;
}

function gformGetDecimalSeparator(numberFormat){
    var s;
    switch (numberFormat){
        case 'currency' :
            var currency = new Currency(gf_global.gf_currency_config);
            s = currency.currency["decimal_separator"];
            break;
        case 'decimal_comma' :
            s = ',';
            break;
        default :
            s = "."
     }
    return s;
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
function gformDeleteUploadedFile(formId, fieldId, deleteButton){
    var parent = jQuery("#field_" + formId + "_" + fieldId);

    var fileIndex = jQuery(deleteButton).parent().index();

    parent.find(".ginput_preview").eq(fileIndex).remove();

    //displaying single file upload field
    parent.find('input[type="file"],.validation_message,#extensions_message_' + formId + '_' + fieldId).removeClass("gform_hidden");

    //displaying post image label
    parent.find(".ginput_post_image_file").show();

    //clearing post image meta fields
    parent.find("input[type=\"text\"]").val('');

    //removing file from uploaded meta
    var filesJson = jQuery('#gform_uploaded_files_' + formId).val();

    if(filesJson){
        var files = jQuery.secureEvalJSON(filesJson);
        if(files) {
            var inputName = "input_" + fieldId;
            var $multfile = parent.find("#gform_multifile_upload_" + formId + "_" + fieldId );
            if( $multfile.length > 0 ) {
                files[inputName].splice(fileIndex, 1);
                var settings = $multfile.data('settings');
                var max = settings.gf_vars.max_files;
                jQuery("#" + settings.gf_vars.message_id).html('');
                if(files[inputName].length < max)
                    gfMultiFileUploader.toggleDisabled(settings, false);

            } else {
                files[inputName] = null;
            }

            jQuery('#gform_uploaded_files_' + formId).val(jQuery.toJSON(files));
        }
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


    price = gform.applyFilters('gform_product_total', price, formId);

    //updating total
    var totalElement = jQuery(".ginput_total_" + formId);
    if( totalElement.length > 0 ) {

        var currentTotal = totalElement.next().val(),
            formattedTotal = gformFormatMoney(price, true);

        if (currentTotal != price) {
            totalElement.next().val(price).change();
        }

        if (formattedTotal != totalElement.first().text()) {
            totalElement.html(formattedTotal);
        }

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

function gformCalculateProductPrice(form_id, productFieldId){

    var suffix = '_' + form_id + '_' + productFieldId;


    //Drop down auto-calculating labels
    jQuery('.gfield_option' + suffix + ', .gfield_shipping_' + form_id).find('select').each(function(){

        var dropdown_field = jQuery(this);
        var selected_price = gformGetPrice(dropdown_field.val());
        var field_id = dropdown_field.attr('id').split('_')[2];
        dropdown_field.children('option').each(function(){
            var choice_element = jQuery(this);
            var label = gformGetOptionLabel(choice_element, choice_element.val(), selected_price, form_id, field_id);
            choice_element.html(label);
        });
    });


    //Checkboxes labels with prices
    jQuery('.gfield_option' + suffix).find('.gfield_checkbox').find('input:checkbox').each(function(){
        var checkbox_item = jQuery(this);
        var id = checkbox_item.attr('id');
        var field_id = id.split('_')[2];
        var label_id = id.replace('choice_', '#label_');
        var label_element = jQuery(label_id);
        var label = gformGetOptionLabel(label_element, checkbox_item.val(), 0, form_id, field_id);
        label_element.html(label);
    });


    //Radio button auto-calculating lables
    jQuery('.gfield_option' + suffix + ', .gfield_shipping_' + form_id).find('.gfield_radio').each(function(){
        var selected_price = 0;
        var radio_field = jQuery(this);
        var id = radio_field.attr('id');
        var fieldId = id.split('_')[2];
        var selected_value = radio_field.find('input:radio:checked').val();

        if(selected_value)
            selected_price = gformGetPrice(selected_value);

        radio_field.find('input:radio').each(function(){
            var radio_item = jQuery(this);
            var label_id = radio_item.attr('id').replace('choice_', '#label_');
            var label_element = jQuery(label_id);
            if ( label_element ) {
                var label = gformGetOptionLabel(label_element, radio_item.val(), selected_price, form_id, fieldId);
                label_element.html(label);
            }
        });
    });

	var price = gformGetBasePrice(form_id, productFieldId);
	var quantity = gformGetProductQuantity( form_id, productFieldId );

	//calculating options if quantity is more than 0 (a product was selected).
	if( quantity > 0 ) {

		jQuery('.gfield_option' + suffix).find('input:checked, select').each(function(){
			if(!gformIsHidden(jQuery(this)))
				price += gformGetPrice(jQuery(this).val());
		});

		//setting global variable if quantity is more than 0 (a product was selected). Will be used when calculating total
		_anyProductSelected = true;
	}

    price = price * quantity;

	price = gformRoundPrice(price) ;


    return price;
}


function gformGetProductQuantity(formId, productFieldId) {

    //If product is not selected
    if (!gformIsProductSelected(formId, productFieldId)) {
        return 0;
    }

    var quantity,
        quantityInput = jQuery('#ginput_quantity_' + formId + '_' + productFieldId),
        numberFormat;

    if (gformIsHidden(quantityInput)) {
        return 0;
    }

    if (quantityInput.length > 0) {

        quantity = quantityInput.val();

    } else {

        quantityInput = jQuery('.gfield_quantity_' + formId + '_' + productFieldId + ' :input');
        quantity = 1;

        if (quantityInput.length > 0) {
            quantity = quantityInput.val();

            var htmlId = quantityInput.attr('id'),
                fieldId = gf_get_input_id_by_html_id(htmlId);

            numberFormat = gf_get_field_number_format( fieldId, formId, 'value' );
        }

    }

    if (!numberFormat)
        numberFormat = 'currency';

    var decimalSeparator = gformGetDecimalSeparator(numberFormat);

    quantity = gformCleanNumber(quantity, '', '', decimalSeparator);
    if (!quantity)
        quantity = 0;

    return quantity;
}


function gformIsProductSelected( formId, productFieldId ) {

	var suffix = "_" + formId + "_" + productFieldId;

	var productField = jQuery("#ginput_base_price" + suffix + ", .gfield_donation" + suffix + " input[type=\"text\"], .gfield_product" + suffix + " .ginput_amount");
	if( productField.val() && ! gformIsHidden(productField) ){
		return true;
	}
	else
	{
		productField = jQuery(".gfield_product" + suffix + " select, .gfield_product" + suffix + " input:checked, .gfield_donation" + suffix + " select, .gfield_donation" + suffix + " input:checked");
		if( productField.val() && ! gformIsHidden(productField) ){
			return true;
		}
	}

	return false;
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

function gformFormatMoney(text, isNumeric){
    if(!gf_global.gf_currency_config)
        return text;

    var currency = new Currency(gf_global.gf_currency_config);
    return currency.toMoney(text, isNumeric);
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
    price = gformFormatMoney(diff, true);
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

function gformRoundPrice(price){

	var currency = new Currency(gf_global.gf_currency_config);
    var roundedPrice = currency.numberFormat( price, currency.currency['decimals'], '.', '' );

    return parseFloat( roundedPrice );
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

       jQuery( this ).on( 'change', 'input[type="text"], input[type="number"], select', function() {

           var productIds = gformGetProductIds("gfield_price", this);
           if(productIds.formId == 0)
                productIds = gformGetProductIds("gfield_shipping", this);

           jQuery(document).trigger('gform_price_change', [productIds, this]);
           gformCalculateTotalPrice(productIds.formId);
       });

       jQuery( this ).on( 'click', 'input[type="radio"], input[type="checkbox"]', function() {

           var productIds = gformGetProductIds("gfield_price", this);
           if(productIds.formId == 0)
                productIds = gformGetProductIds("gfield_shipping", this);

           jQuery(document).trigger('gform_price_change', [productIds, this]);
           gformCalculateTotalPrice(productIds.formId);
       });

    });

    for(formId in _gformPriceFields){

        //needed when implementing for in loops
        if(!_gformPriceFields.hasOwnProperty(formId))
            continue;

        gformCalculateTotalPrice(formId);
    }

}


//-------------------------------------------
//---------- PASSWORD -----------------------
//-------------------------------------------
function gformShowPasswordStrength(fieldId){
    var password = document.getElementById( fieldId ).value,
        confirm = document.getElementById( fieldId + '_2' ) ? document.getElementById( fieldId + '_2' ).value : '';

    var result = gformPasswordStrength( password, confirm ),
        text = window[ 'gf_text' ][ "password_" + result ],
        resultClass = result === 'unknown' ? 'blank' : result;

    jQuery("#" + fieldId + "_strength").val(result);
    jQuery("#" + fieldId + "_strength_indicator").removeClass("blank mismatch short good bad strong").addClass(resultClass).html(text);
}

// Password strength meter
function gformPasswordStrength( password1, password2 ) {

    if ( password1.length <= 0 ) {
        return 'blank';
    }

    var strength = wp.passwordStrength.meter( password1, wp.passwordStrength.userInputBlacklist(), password2 );

    switch ( strength ) {

        case -1:
            return 'unknown';

        case 2:
            return 'bad';

        case 3:
            return 'good';

        case 4:
            return 'strong';

        case 5:
            return 'mismatch';

        default:
            return 'short';

    }

}

function gformToggleShowPassword( fieldId ) {
    var $password = jQuery( '#' + fieldId ),
        $button = $password.parent().find( 'button' ),
        $icon = $button.find( 'span' ),
        currentType = $password.attr( 'type' );

    switch ( currentType ) {
        case 'password':
            $password.attr( 'type', 'text' );
            $button.attr( 'label', $button.attr( 'data-label-hide' ) );
            $icon.removeClass( 'dashicons-hidden' ).addClass( 'dashicons-visibility' );
            break;
        case 'text':
            $password.attr( 'type', 'password' );
            $button.attr( 'label', $button.attr( 'data-label-show' ) );
            $icon.removeClass( 'dashicons-visibility' ).addClass( 'dashicons-hidden' );
            break;
    }
}

//----------------------------
//------ CHECKBOX FIELD ------
//----------------------------

function gformToggleCheckboxes( toggleCheckbox ) {

	var $toggle      = jQuery( toggleCheckbox ).parent(),
	    $toggleLabel = $toggle.find( 'label' );
	    $checkboxes  = $toggle.parent().find( 'li:not( .gchoice_select_all )' );

	// Set checkboxes state.
	$checkboxes.each( function() {

		// Set checkbox checked state.
		jQuery( 'input[type="checkbox"]', this ).prop( 'checked', toggleCheckbox.checked ).trigger( 'change' );

		// Execute onclick event.
		if ( typeof jQuery( 'input[type="checkbox"]', this )[0].onclick === 'function' ) {
			jQuery( 'input[type="checkbox"]', this )[0].onclick();
		}

	} );

	// Change toggle label.
	if ( toggleCheckbox.checked ) {
		$toggleLabel.html( $toggleLabel.data( 'label-deselect' ) );
	} else {
		$toggleLabel.html( $toggleLabel.data( 'label-select' ) );
	}

}


//----------------------------
//------ LIST FIELD ----------
//----------------------------

function gformAddListItem( addButton, max ) {

    var $addButton = jQuery( addButton );

    if( $addButton.hasClass( 'gfield_icon_disabled' ) ) {
        return;
    }

    var $group     = $addButton.parents( '.gfield_list_group' ),
        $clone     = $group.clone(),
        $container = $group.parents( '.gfield_list_container' ),
        tabindex   = $clone.find( ':input:last' ).attr( 'tabindex' );

    // reset all inputs to empty state
    $clone
        .find( 'input, select, textarea' ).attr( 'tabindex', tabindex )
        .not( ':checkbox, :radio' ).val( '' );
    $clone.find( ':checkbox, :radio' ).prop( 'checked', false );

    $clone = gform.applyFilters( 'gform_list_item_pre_add', $clone, $group );

    $group.after( $clone );

    gformToggleIcons( $container, max );
    gformAdjustClasses( $container );

    gform.doAction( 'gform_list_post_item_add', $clone, $container );

}

function gformDeleteListItem( deleteButton, max ) {

    var $deleteButton = jQuery( deleteButton ),
        $group        = $deleteButton.parents( '.gfield_list_group' ),
        $container    = $group.parents( '.gfield_list_container' );

    $group.remove();

    gformToggleIcons( $container, max );
    gformAdjustClasses( $container );

    gform.doAction( 'gform_list_post_item_delete', $container );

}

function gformAdjustClasses( $container ) {

    var $groups = $container.find( '.gfield_list_group' );

    $groups.each( function( i ) {

        var $group       = jQuery( this ),
            oddEvenClass = ( i + 1 ) % 2 == 0 ? 'gfield_list_row_even' : 'gfield_list_row_odd';

        $group.removeClass( 'gfield_list_row_odd gfield_list_row_even' ).addClass( oddEvenClass );

    } );

}

function gformToggleIcons( $container, max ) {

    var groupCount  = $container.find( '.gfield_list_group' ).length,
        $addButtons = $container.find( '.add_list_item' );

    $container.find( '.delete_list_item' ).css( 'visibility', groupCount == 1 ? 'hidden' : 'visible' );

    if ( max > 0 && groupCount >= max ) {

        // store original title in the add button
        $addButtons.data( 'title', $container.find( '.add_list_item' ).attr( 'title' ) );
        $addButtons.addClass( 'gfield_icon_disabled' ).attr( 'title', '' );

    } else if( max > 0 ) {

        $addButtons.removeClass( 'gfield_icon_disabled' );

        if( $addButtons.data( 'title' ) )   {
            $addButtons.attr( 'title', $addButtons.data( 'title' ) );
        }

    }
}

//-----------------------------------
//--------- REPEATER FIELD ----------
//-----------------------------------

function gformAddRepeaterItem( addButton, max ) {

	var $addButton = jQuery( addButton );

	if( $addButton.hasClass( 'gfield_icon_disabled' ) ) {
		return;
	}

	var $item     = $addButton.closest( '.gfield_repeater_item' ),
		$clone     = $item.clone(),
		$container = $item.closest( '.gfield_repeater_container' ),
		tabindex   = $clone.find( ':input:last' ).attr( 'tabindex' );

	// reset all inputs to empty state
	$clone
		.find( 'input[type!="hidden"], select, textarea' ).attr( 'tabindex', tabindex )
		.not( ':checkbox, :radio' ).val( '' );
	$clone.find( ':checkbox, :radio' ).prop( 'checked', false );
	$clone.find('.validation_message').remove();

	$clone = gform.applyFilters( 'gform_repeater_item_pre_add', $clone, $item );

	$item.after( $clone );

	var $cells = $clone.children('.gfield_repeater_cell');
	$cells.each(function () {
		var $subContainer = jQuery(this).find('.gfield_repeater_container').first();
		if ($subContainer.length > 0) {
			resetContainerItems = function ($c) {
				$c.children('.gfield_repeater_items').children('.gfield_repeater_item').each(function (i) {
					var $children = jQuery(this).children('.gfield_repeater_cell');
					$children.each(function () {
						var $subSubContainer = jQuery(this).find('.gfield_repeater_container').first();
						if ($subSubContainer.length > 0) {
							resetContainerItems($subSubContainer);
						}
					})
				})
				$c.children('.gfield_repeater_items').children('.gfield_repeater_item').not(':first').remove();
			}
			resetContainerItems($subContainer);
		}
	})

	gformResetRepeaterAttributes($container);

	if ( typeof gformInitDatepicker == 'function' ) {
		$container.find('.ui-datepicker-trigger').remove();
		$container.find('.hasDatepicker').removeClass('hasDatepicker');
		gformInitDatepicker();
	}

	gformBindFormatPricingFields();

	gformToggleRepeaterButtons( $container, max );

	gform.doAction('gform_repeater_post_item_add', $clone, $container);

}

function gformDeleteRepeaterItem(deleteButton, max) {

	var $deleteButton = jQuery(deleteButton),
		$group = $deleteButton.closest('.gfield_repeater_item'),
		$container = $group.closest('.gfield_repeater_container');

	$group.remove();

	gformResetRepeaterAttributes($container);
	gformToggleRepeaterButtons($container, max);

	gform.doAction('gform_repeater_post_item_delete', $container);

}

function gformResetRepeaterAttributes($container, depth, row) {

	var cachedRadioSelection = null;

	if (typeof depth === 'undefined') {
		depth = 0;
	}

	if (typeof row === 'undefined') {
		row = 0;
	}

	$container.children('.gfield_repeater_items').children('.gfield_repeater_item').each(function () {
		var $children = jQuery(this).children('.gfield_repeater_cell');
		$children.each(function () {
			var $cell = jQuery(this);
			var $subContainer = jQuery(this).find('.gfield_repeater_container').first();

			if ($subContainer.length > 0) {
				var newDepth = depth + 1;
				gformResetRepeaterAttributes($subContainer, newDepth, row);
				return;
			}

			jQuery(this).find('input, select, textarea, :checkbox, :radio').each(function () {
				var $this = jQuery(this);
				var name = $this.attr('name');

				if ( typeof name == 'undefined' ) {
					return;
				}

				var regEx = /^(input_[^\[]*)((\[[0-9]+\])+)/,
					parts = regEx.exec(name);

				if (!parts) {
					return;
				}
				var inputName = parts[1],
					arayParts = parts[2],
					regExIndex = /\[([0-9]+)\]/g,
					indexes = [],
					match = regExIndex.exec(arayParts);

				while (match != null) {
					indexes.push(match[1]);
					match = regExIndex.exec(arayParts);
				}
				var newNameIndex = parts[1];
				indexes = indexes.reverse();
				var newId = '';
				for (var n = indexes.length - 1; n >= 0; n--) {
					if (n == depth) {
						newNameIndex += '[' + row + ']';
						newId += '-' + row;
					} else {
						newNameIndex += '[' + indexes[n] + ']';
						newId += '-' + indexes[n];
					}
				}

				var currentId = $this.attr('id');
				var $label = $cell.find("label[for='" + currentId + "']");

				if ( currentId ) {
					var matches = currentId.match(/((choice|input)_[0-9|_]*)-/);
					if ( matches && matches[2] ) {
						newId = matches[1] + newId;
						$label.attr('for', newId);
						$this.attr('id', newId);
					}
				}
				var newName = name.replace(parts[0], newNameIndex),
					newNameIsChecked = jQuery('input[name="'+ newName +'"]').is(':checked');

				if ( $this.is(':radio') && $this.is(':checked') && name !== newName && newNameIsChecked ) {
					if ( cachedRadioSelection !== null ) {
						cachedRadioSelection.prop('checked', true);
					}

					$this.prop('checked', false);
					cachedRadioSelection = $this;
				}

				$this.attr('name', newName);
			});
		});
		if (depth === 0) {
			row++;
		}
	});

	if ( cachedRadioSelection !== null ) {
		cachedRadioSelection.prop('checked', true);
		cachedRadioSelection = null;
	}

}

function gformToggleRepeaterButtons($container) {

	var max = $container.closest('.gfield_repeater_wrapper').data('max_items'),
		groupCount = $container.children('.gfield_repeater_items').children('.gfield_repeater_item').length,
		$buttonsContainer = $container.children('.gfield_repeater_items').children('.gfield_repeater_item').children('.gfield_repeater_buttons'),
		$addButtons = $buttonsContainer.children('.add_repeater_item');

	$buttonsContainer.children('.remove_repeater_item').css('visibility', groupCount == 1 ? 'hidden' : 'visible');

	if (max > 0 && groupCount >= max) {

		// store original title in the add button
		$addButtons.data('title', $buttonsContainer.children('.add_repeater_item').attr('title'));
		$addButtons.addClass('gfield_icon_disabled').attr('title', '');

	} else if (max > 0) {

		$addButtons.removeClass('gfield_icon_disabled');

		if ($addButtons.data('title')) {
			$addButtons.attr('title', $addButtons.data('title'));
		}
	}

	$container
		.children('.gfield_repeater_items')
		.children('.gfield_repeater_item')
		.children( '.gfield_repeater_cell').each(function (i) {
			var $subContainer = jQuery(this).find('.gfield_repeater_container').first();
			if ($subContainer.length > 0) {
				gformToggleRepeaterButtons($subContainer);
			}
		});
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

        //needed when implementing for in loops
        if(!rules.hasOwnProperty(type))
            continue;


        for(i in rules[type]) {

            if(!rules[type].hasOwnProperty(i))
                continue;

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

        var element = jQuery( this );

        // RTL support
        if( jQuery( 'html' ).attr( 'dir' ) == 'rtl' ) {
            element.addClass( 'chosen-rtl chzn-rtl' );
        }

        // only initialize once
        if( element.is(":visible") && element.siblings(".chosen-container").length == 0 ){
            var options = gform.applyFilters( 'gform_chosen_options', { no_results_text: noResultsText }, element );
            element.chosen( options );
        }

    });
}

//----------------------------------------
//--- CURRENCY FORMAT NUMBER FIELD -------
//----------------------------------------

function gformInitCurrencyFormatFields(fieldList){
    jQuery(fieldList).each(function(){
        var $this = jQuery(this);
        $this.val( gformFormatMoney( jQuery(this).val() ) );
    }).change( function( event ) {
            jQuery(this).val( gformFormatMoney( jQuery(this).val() ) );
        });
}



//----------------------------------------
//------ JS MERGE TAGS -------------------
//----------------------------------------

var GFMergeTag = function() {

	/**
     * Gets the merge tag value for the specified input Id
	 * @param formId    The current form Id
	 * @param inputId   The input Id to get the merge tag from. This could be a field id (i.e. 1) or a specific input Id for multi-input fields (i.e. 1.2)
	 * @param modifier  The merge tag modifier to be used. i.e. value, currency, price, etc...
	 * @returns         Returns a string containg the merge tag value for the spcified input Id
	 */
	GFMergeTag.getMergeTagValue = function( formId, inputId, modifier ) {

		if ( modifier === undefined ) {
			modifier = '';
		}
		modifier = modifier.replace(":", "");

		var fieldId = parseInt(inputId,10);
		var field = jQuery('#field_' + formId + '_' + fieldId);

		var inputSelector = fieldId == inputId ? 'input[name^="input_' + fieldId + '"]' : 'input[name="input_' + inputId + '"]';
		var input = field.find( inputSelector + ', select[name^="input_' + inputId + '"], textarea[name="input_' + inputId + '"]');

		// checking conditional logic
		var isVisible = window['gf_check_field_rule'] ? gf_check_field_rule( formId, fieldId, true, '' ) == 'show' : true,
			val;

		if ( ! isVisible ) {
			return '';
		}

		//If value has been filtered, use it. Otherwise use default logic
		var value = gform.applyFilters( 'gform_value_merge_tag_' + formId + '_' + fieldId, false, input, modifier );
		if ( value !== false ){
			return value;
		}

		value = ''; //Reset value to blank

		switch ( modifier ) {
			case 'label':
				var label = field.find('.gfield_label').text();
				return label;
			    break;
			case 'qty':
				if ( field.hasClass('gfield_price') ){
					val = gformGetProductQuantity( formId, fieldId );
					return val === false || val === '' ? 0 : val;
				}
				break;

		}

		// Filter out unselected checkboxes and radio buttons
		if ( input.prop('type') === 'checkbox' || input.prop('type') === 'radio' ) {
			input = input.filter(':checked');
		}

		if ( input.length === 1 ) {
			if ( ( input.is('select') || input.prop('type') === 'radio' || input.prop('type') === 'checkbox' ) && modifier === '' ) {
				val = ( input.is('select') ) ? input.find('option:selected') : input.next('label').clone();
                val.find('span').remove();

				if ( val.length === 1 ) {
					val = val.text();
				} else {
					var option = [];
					for(var i=0; i<val.length; i++) {
						option[i] = jQuery(val[i]).text();
					}

					val = option;
				}
			} else if ( val === undefined ) {
				val = input.val();
			}

			if ( jQuery.isArray( val ) ) {
				// multiple select
				value = val.join(', ');
			} else if ( typeof val === 'string' ) {

			    value = GFMergeTag.formatValue( val, modifier );

			} else {
				// empty multiple select returns null, set it to ''
				value = '';
            }
		} else if ( input.length > 1 ) {
			val = [];
			for(var i=0; i<input.length; i++) {
				if( ( input.prop('type') === 'checkbox' ) && modifier === '' ) {

				    var clone = jQuery(input[i]).next('label').clone();
					clone.find('span').remove()
					val[i] = GFMergeTag.formatValue( clone.text(), modifier );

					clone.remove();

				} else {
					val[i] = GFMergeTag.formatValue( jQuery(input[i]).val(), modifier );
				}
			}

			value = val.join(', ');
		}

		return value;
	}

	/**
     * Parses the specified text for merge tags, and replaces all of them with the appropriate merge tag values. Returns the resulting string
	 * @param formId    The current form Id
	 * @param text      The text containing merge tags
	 * @returns         Retuns the original "text" strings with all merge tags replaced with the appropriate merge tag values
	 */
	GFMergeTag.replaceMergeTags = function( formId, text ) {

		var mergeTags = GFMergeTag.parseMergeTags( text );

		for(i in mergeTags) {

			if(! mergeTags.hasOwnProperty(i)) {
				continue;
			}

			var inputId = mergeTags[i][1];
			var fieldId = parseInt(inputId,10);
			var modifier = mergeTags[i][3] == undefined ? '' : mergeTags[i][3].replace(":", "");

			var value = GFMergeTag.getMergeTagValue( formId, inputId, modifier );

			text = text.replace( mergeTags[i][0], value );
		}

		return text;
	}

	GFMergeTag.formatValue = function( value, modifier ) {

		value = value.split( '|' );
		var val = '';
		if( value.length > 1 ) {
			val = modifier === 'price' || modifier === 'currency' ? gformToNumber( value[1] ) : value[0];
		} else {
			val = value[0];
		}

		switch ( modifier ) {

			case 'price':
				val = gformToNumber( val );
				val = val === false ? '' : val;
				break;

			case 'currency':
				val = gformFormatMoney( val, false );
				val = val === false ? '' : val;
				break;

			case 'numeric':
				val = gformToNumber( val );
				return val === false ? 0 : val;
				break;
		}

		return val;
	}

	/**
     * Parses the merge tags in the specified text and returns an array of all the matched merge tags
	 *
	 * @param text  The text with merge tags to be parsed
	 * @param regEx The regular expression to be used to parse for merge tags.
	 *
	 * @returns Returns an array with all the merge tags that were matched in the original text
	 */
	GFMergeTag.parseMergeTags = function( text, regEx ) {

		if( typeof regEx === 'undefined' ) {
			regEx = /{[^{]*?:(\d+(\.\d+)?)(:(.*?))?}/i;
		}

		var matches = [];

		while( regEx.test( text ) ) {
			var i = matches.length;
			matches[i] = regEx.exec( text );
			text = text.replace( '' + matches[i][0], '' );
		}

		return matches;
	}
}

new GFMergeTag();


//----------------------------------------
//------ CALCULATION FUNCTIONS -----------
//----------------------------------------

var GFCalc = function(formId, formulaFields){

	this.formId = formId;
	this.formulaFields = formulaFields;

    this.exprPatt = /^[0-9 -/*\(\)]+$/i;
    this.isCalculating = {};

    this.init = function(formId, formulaFields) {

        var calc = this;
        jQuery(document).bind("gform_post_conditional_logic", function(){
            calc.runCalcs( formId, formulaFields );
        } );

        for(var i=0; i<formulaFields.length; i++) {
            var formulaField = jQuery.extend({}, formulaFields[i]);
            this.runCalc(formulaField, formId);
            this.bindCalcEvents(formulaField, formId);
        }

    }

    this.runCalc = function(formulaField, formId) {

        var calcObj      = this,
            field        = jQuery('#field_' + formId + '_' + formulaField.field_id),
            formulaInput = field.hasClass( 'gfield_price' ) ? jQuery( '#ginput_base_price_' + formId + '_' + formulaField.field_id ) : jQuery( '#input_' + formId + '_' + formulaField.field_id ),
            previous_val = formulaInput.val(),
            formula      = gform.applyFilters( 'gform_calculation_formula', formulaField.formula, formulaField, formId, calcObj ),
            expr         = calcObj.replaceFieldTags( formId, formula, formulaField ).replace(/(\r\n|\n|\r)/gm,""),
            result       = '';

        if(calcObj.exprPatt.test(expr)) {
            try {

                //run calculation
                result = eval(expr);

            } catch( e ) { }
        }

        // if result is positive infinity, negative infinity or a NaN, defaults to 0
        if( ! isFinite( result ) )
            result = 0;

        // allow users to modify result with their own function
        if( window["gform_calculation_result"] ) {
            result = window["gform_calculation_result"](result, formulaField, formId, calcObj);
            if( window.console )
                console.log( '"gform_calculation_result" function is deprecated since version 1.8! Use "gform_calculation_result" JS hook instead.' );
        }

        // allow users to modify result with their own function
        result = gform.applyFilters( 'gform_calculation_result', result, formulaField, formId, calcObj );

        // allow result to be custom formatted
        var formattedResult = gform.applyFilters( 'gform_calculation_format_result', false, result, formulaField, formId, calcObj );

        var numberFormat = gf_get_field_number_format(formulaField.field_id, formId);

        //formatting number
        if( formattedResult !== false) {
            result = formattedResult;
        }
        else if( field.hasClass( 'gfield_price' ) || numberFormat == "currency") {

            result = gformFormatMoney(result ? result : 0, true);
        }
        else {

            var decimalSeparator = ".";
            var thousandSeparator = ",";

            if(numberFormat == "decimal_comma"){
                decimalSeparator = ",";
                thousandSeparator = ".";
            }

            result = gformFormatNumber(result, !gformIsNumber(formulaField.rounding) ? -1 : formulaField.rounding, decimalSeparator, thousandSeparator);
        }

        //If value doesn't change, abort.
        //This is needed to prevent an infinite loop condition with conditional logic
        if( result == previous_val )
            return;

        // if this is a calculation product, handle differently
        if(field.hasClass('gfield_price')) {
            jQuery('#input_' + formId + '_' + formulaField.field_id).text(result);
            formulaInput.val(result).trigger('change');
            gformCalculateTotalPrice(formId);
        } else {
            formulaInput.val(result).trigger('change');
        }

    };

    this.runCalcs = function( formId, formulaFields ) {
	    for(var i=0; i<formulaFields.length; i++) {
		    var formulaField = jQuery.extend({}, formulaFields[i]);
		    this.runCalc( formulaField, formId );
	    }
    }

    this.bindCalcEvents = function(formulaField, formId) {

        var calcObj = this;
        var formulaFieldId = formulaField.field_id;
        var matches = GFMergeTag.parseMergeTags( formulaField.formula );

        calcObj.isCalculating[formulaFieldId] = false;

        for(var i in matches) {

            if(! matches.hasOwnProperty(i))
                continue;

            var inputId = matches[i][1];
            var fieldId = parseInt(inputId,10);
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

            // allow users to add custom methods for triggering calculations
            gform.doAction( 'gform_post_calculation_events', matches[i], formulaField, formId, calcObj );

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

    this.replaceFieldTags = function( formId, expr, formulaField ) {

        var matches = GFMergeTag.parseMergeTags( expr );

        for(i in matches) {

            if(! matches.hasOwnProperty(i))
                continue;

            var inputId = matches[i][1];
            var fieldId = parseInt(inputId,10);

            var modifier = 'value';
			if( matches[i][3] ){
				modifier = matches[i][3];
			}
			else {
				var is_product_radio =  jQuery('.gfield_price input[name=input_' + fieldId + ']').is('input[type=radio]');
                var is_product_dropdown = jQuery('.gfield_price select[name=input_' + fieldId + ']').length > 0;
                var is_option_checkbox = jQuery('.gfield_price input[name="input_' + inputId + '"]').is('input[type=checkbox]');

                if( is_product_dropdown || is_product_radio || is_option_checkbox ) {
					modifier = 'price';
				}
			}

			var isVisible = window['gf_check_field_rule'] ? gf_check_field_rule( formId, fieldId, true, '' ) == 'show' : true;

			var value = isVisible ? GFMergeTag.getMergeTagValue( formId, inputId, modifier ) : 0;

            // allow users to modify value with their own function
            value = gform.applyFilters( 'gform_merge_tag_value_pre_calculation', value, matches[i], isVisible, formulaField, formId );

            value = this.cleanNumber( value, formId, fieldId, formulaField );

            expr = expr.replace( matches[i][0], value );
        }

        return expr;
    }

	this.cleanNumber = function ( value, formId, fieldId, formulaField ) {

		var numberFormat = gf_get_field_number_format( fieldId, formId );

		if( ! numberFormat ) {
			numberFormat = gf_get_field_number_format(formulaField.field_id, formId);
		}

		var decimalSeparator = gformGetDecimalSeparator(numberFormat);

		value = gformCleanNumber( value, '', '', decimalSeparator );
		if( ! value )
			value = 0;

		return value;
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

/**
 * @deprecated. Use GFMergeTags.parseMergeTag() instead
 */
function getMatchGroups(expr, patt) {

    var matches = new Array();

    while(patt.test(expr)) {

        var i = matches.length;
        matches[i] = patt.exec(expr)
        expr = expr.replace('' + matches[i][0], '');

    }

    return matches;
}

function gf_get_field_number_format(fieldId, formId, context) {

    var fieldNumberFormats = rgars(window, 'gf_global/number_formats/{0}/{1}'.format(formId, fieldId)),
        format = false;

    if (fieldNumberFormats === '') {
        return format;
    }

    if (typeof context == 'undefined') {
        format = fieldNumberFormats.price !== false ? fieldNumberFormats.price : fieldNumberFormats.value;
    } else {
        format = fieldNumberFormats[context];
    }

    return format;
}


//----------------------------------------
//------ JAVASCRIPT HOOK FUNCTIONS -------
//----------------------------------------

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
        if( priority == undefined ){
            priority = 10;
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



//----------------------------------------
//------ reCAPTCHA FUNCTIONS -------------
//----------------------------------------

/**
 * Callback function on the reCAPTCAH API script.
 *
 * @see GF_Field_CAPTCHA::get_field_input() in /includes/fields/class-gf-field-catpcha.php
 */
function renderRecaptcha() {

    jQuery( '.ginput_recaptcha' ).each( function() {

        var $elem      = jQuery( this ),
            parameters = {
                'sitekey':  $elem.data( 'sitekey' ),
                'theme':    $elem.data( 'theme' ),
	            'tabindex': $elem.data( 'tabindex' )
            };

        if ( ! $elem.is( ':empty' ) ) {
            return;
        }

        if ( $elem.data( 'stoken' ) ) {
            parameters.stoken = $elem.data( 'stoken' );
        }

        var callback = false;

        if ( $elem.data( 'size' ) == 'invisible' ) {
            callback = function( token ) {
                if ( token ) {
                    $elem.closest('form').submit();
                }
            }
        }

        /**
         * Allows a custom callback function to be executed when the user successfully submits the captcha.
         *
         * @since 2.4.x     The callback will be a function if reCAPTCHA v2 Invisible is used.
         * @since 2.2.5.20
         *
         * @param string|false|object   The name of the callback function or the function object itself to be executed when the user successfully submits the captcha.
         * @param object       $elem    The jQuery object containing the div element with the ginput_recaptcha class for the current reCaptcha field.
         */
	    callback = gform.applyFilters( 'gform_recaptcha_callback', callback, $elem );
	    if ( callback ) {
		    parameters.callback = callback;
	    }

        $elem.data( 'widget-id', grecaptcha.render( this.id, parameters ) );

	    if ( parameters.tabindex ) {
		    $elem.find( 'iframe' ).attr( 'tabindex', parameters.tabindex );
	    }

        gform.doAction( 'gform_post_recaptcha_render', $elem );


    } );

}

//----------------------------------------
//----- SINGLE FILE UPLOAD FUNCTIONS -----
//----------------------------------------

function gformValidateFileSize( field, max_file_size ) {
	var validation_element;

	// Get validation message element.
	if ( jQuery( field ).closest( 'div' ).siblings( '.validation_message' ).length > 0 ) {
		validation_element = jQuery( field ).closest( 'div' ).siblings( '.validation_message' );
	} else {
		validation_element = jQuery( field ).siblings( '.validation_message' );
	}


	// If file API is not supported within browser, return.
	if ( ! window.FileReader || ! window.File || ! window.FileList || ! window.Blob ) {
		return;
	}

	// Get selected file.
	var file = field.files[0];

	// If selected file is larger than maximum file size, set validation message and unset file selection.
	if ( file && file.size > max_file_size ) {

		// Set validation message.
		validation_element.text(file.name + " - " + gform_gravityforms.strings.file_exceeds_limit);

    } else {

		// Reset validation message.
		validation_element.text( '' );

	}

}

//----------------------------------------
//------ MULTIFILE UPLOAD FUNCTIONS ------
//----------------------------------------

(function (gfMultiFileUploader, $) {
    gfMultiFileUploader.uploaders = {};
    var strings = typeof gform_gravityforms != 'undefined' ? gform_gravityforms.strings : {};
    var imagesUrl = typeof gform_gravityforms != 'undefined' ? gform_gravityforms.vars.images_url : "";


	$(document).bind('gform_post_render', function(e, formID){

		$("form#gform_" + formID + " .gform_fileupload_multifile").each(function(){
			setup(this);
		});
		var $form = $("form#gform_" + formID);
		if($form.length > 0){
			$form.submit(function(){
				var pendingUploads = false;
				$.each(gfMultiFileUploader.uploaders, function(i, uploader){
					if(uploader.total.queued>0){
						pendingUploads = true;
						return false;
					}
				});
				if(pendingUploads){
					alert(strings.currently_uploading);
					window["gf_submitting_" + formID] = false;
					$('#gform_ajax_spinner_' + formID).remove();
					return false;
				}
			});
		}

	});

	$(document).bind("gform_post_conditional_logic", function(e,formID, fields, isInit){
		if(!isInit){
			$.each(gfMultiFileUploader.uploaders, function(i, uploader){
				uploader.refresh();
			});
		}
	});

    $(document).ready(function () {
        if((typeof adminpage !== 'undefined' && adminpage === 'toplevel_page_gf_edit_forms')|| typeof plupload == 'undefined'){
            $(".gform_button_select_files").prop("disabled", true);
        } else if (typeof adminpage !== 'undefined' && adminpage.indexOf('_page_gf_entries') > -1) {
            $(".gform_fileupload_multifile").each(function(){
                setup(this);
            });
        }
    });

    gfMultiFileUploader.setup = function (uploadElement){
        setup( uploadElement );
    };

    function setup(uploadElement){
        var settings = $(uploadElement).data('settings');

        var uploader = new plupload.Uploader(settings);
        formID = uploader.settings.multipart_params.form_id;
        gfMultiFileUploader.uploaders[settings.container] = uploader;
        var formID;
        var uniqueID;

        uploader.bind('Init', function(up, params) {
            if(!up.features.dragdrop)
                $(".gform_drop_instructions").hide();
            var fieldID = up.settings.multipart_params.field_id;
            var maxFiles = parseInt(up.settings.gf_vars.max_files,10);
            var initFileCount = countFiles(fieldID);
            if(maxFiles > 0 && initFileCount >= maxFiles){
                gfMultiFileUploader.toggleDisabled(up.settings, true);
            }

        });

        gfMultiFileUploader.toggleDisabled = function (settings, disabled){

            var button = typeof settings.browse_button == "string" ? $("#" + settings.browse_button) : $(settings.browse_button);
            button.prop("disabled", disabled);
        };

        function addMessage(messagesID, message){
            $("#" + messagesID).prepend("<li>" + htmlEncode(message) + "</li>");
        }

        uploader.init();

		uploader.bind('BeforeUpload', function(up, file){
			up.settings.multipart_params.original_filename = file.name;
		});

        uploader.bind('FilesAdded', function(up, files) {
            var max = parseInt(up.settings.gf_vars.max_files,10),
                fieldID = up.settings.multipart_params.field_id,
                totalCount = countFiles(fieldID),
                disallowed = up.settings.gf_vars.disallowed_extensions,
                extension;

            if( max > 0 && totalCount >= max){
                $.each(files, function(i, file) {
                    up.removeFile(file);
                    return;
                });
                return;
            }
            $.each(files, function(i, file) {

                extension = file.name.split('.').pop();

                if($.inArray(extension, disallowed) > -1){
                    addMessage(up.settings.gf_vars.message_id, file.name + " - " + strings.illegal_extension);
                    up.removeFile(file);
                    return;
                }

                if ((file.status == plupload.FAILED) || (max > 0 && totalCount >= max)){
                    up.removeFile(file);
                    return;
                }

                var size         = typeof file.size !== 'undefined' ? plupload.formatSize(file.size) : strings.in_progress,
                    removeFileJs = '$this=jQuery(this); var uploader = gfMultiFileUploader.uploaders.' + up.settings.container.id + ';uploader.stop();uploader.removeFile(uploader.getFile(\'' + file.id +'\'));$this.after(\'' + strings.cancelled + '\'); uploader.start();$this.remove();',
                    statusMarkup = '<div id="{0}" class="ginput_preview">{1} ({2}) <b></b> <a href="javascript:void(0)" title="{3}" onclick="{4}" onkeypress="{4}">{5}</a></div>';

                /**
                 *  Filer the file upload markup as it is being uploaded.
                 *
                 *  @param {string}            statusMarkup Markup template used to render the status of the file being uploaded.
                 *  @param {plupload.File}     file         Instance of File being uploaded. See: https://www.plupload.com/docs/v2/File.
                 *  @param {int|string}        size         File size.
                 *  @param {object}            strings      Array of localized strings relating to the file upload UI.
                 *  @param {string}            removeFileJs JS used to remove the file when the "Cancel" link is click/pressed.
                 *  @param {plupload.Uploader} up           Instance of Uploader responsible for uploading current file. See: https://www.plupload.com/docs/v2/Uploader.
                 */
                statusMarkup = gform.applyFilters( 'gform_file_upload_status_markup', statusMarkup, file, size, strings, removeFileJs, up )
                    .format( file.id, htmlEncode( file.name ), size, strings.cancel_upload, removeFileJs, strings.cancel );

                $( '#' + up.settings.filelist ).prepend( statusMarkup );

                totalCount++;

            });

            up.refresh(); // Reposition Flash

            var formElementID = "form#gform_" + formID;
            var uidElementID = "input:hidden[name='gform_unique_id']";
            var uidSelector = formElementID + " " + uidElementID;
            var $uid = $(uidSelector);
            if($uid.length==0){
                $uid = $(uidElementID);
            }

            uniqueID = $uid.val();
            if('' === uniqueID){
                uniqueID = generateUniqueID();
                $uid.val(uniqueID);
            }


            if(max > 0 && totalCount >= max){
                gfMultiFileUploader.toggleDisabled(up.settings, true);
                addMessage(up.settings.gf_vars.message_id, strings.max_reached)
            }


            up.settings.multipart_params.gform_unique_id = uniqueID;
            up.start();

        });

        uploader.bind('UploadProgress', function(up, file) {
            var html = file.percent + "%";
            $('#' + file.id + " b").html(html);
        });

        uploader.bind('Error', function(up, err) {
            if(err.code === plupload.FILE_EXTENSION_ERROR){
                var extensions = typeof up.settings.filters.mime_types != 'undefined' ? up.settings.filters.mime_types[0].extensions /* plupoad 2 */ : up.settings.filters[0].extensions;
                addMessage(up.settings.gf_vars.message_id, err.file.name + " - " + strings.invalid_file_extension + " " + extensions);
            } else if (err.code === plupload.FILE_SIZE_ERROR) {
                addMessage(up.settings.gf_vars.message_id, err.file.name + " - " + strings.file_exceeds_limit);
            } else {
                var m = "Error: " + err.code +
                    ", Message: " + err.message +
                    (err.file ? ", File: " + err.file.name : "");

                addMessage(up.settings.gf_vars.message_id, m);
            }
            $('#' + err.file.id ).html('');

            up.refresh(); // Reposition Flash
        });

		uploader.bind('ChunkUploaded', function(up, file, result) {
			var response = $.secureEvalJSON(result.response);
			if(response.status == "error"){
				up.removeFile(file);
				addMessage(up.settings.gf_vars.message_id, file.name + " - " + response.error.message);
				$('#' + file.id ).html('');
			}
		});

        uploader.bind('FileUploaded', function(up, file, result) {
			if( ! up.getFile(file.id) ) {
				// The file has been removed from the queue.
				return;
			}
            var response = $.secureEvalJSON(result.response);
            if(response.status == "error"){
                addMessage(up.settings.gf_vars.message_id, file.name + " - " + response.error.message);
                $('#' + file.id ).html('');
                return;
            }

            var html = '<strong>' + htmlEncode(file.name) + '</strong>';
            var formId = up.settings.multipart_params.form_id;
            var fieldId = up.settings.multipart_params.field_id;
            html = "<img "
                + "class='gform_delete' "
                + "src='" + imagesUrl + "/delete.png' "
                + "onclick='gformDeleteUploadedFile(" + formId + "," + fieldId + ", this);' "
                + "onkeypress='gformDeleteUploadedFile(" + formId + "," + fieldId + ", this);' "
                + "alt='"+ strings.delete_file + "' "
                + "title='" + strings.delete_file
                + "' /> "
                + html;

            html = gform.applyFilters( 'gform_file_upload_markup', html, file, up, strings, imagesUrl );

            $( '#' + file.id ).html( html );

            if(file.percent == 100){
                if(response.status && response.status == 'ok'){
                    addFile(fieldId, response.data);
                }  else {
                    addMessage(up.settings.gf_vars.message_id, strings.unknown_error + ': ' + file.name);
                }
            }



        });

		function getAllFiles(){
			var selector = '#gform_uploaded_files_' + formID,
				$uploadedFiles = $(selector), files;

			files = $uploadedFiles.val();
			files = (typeof files === "undefined") || files === '' ? {} : $.parseJSON(files);

			return files;
		}

        function getFiles(fieldID){
            var allFiles = getAllFiles();
            var inputName = getInputName(fieldID);

            if(typeof allFiles[inputName] == 'undefined')
                allFiles[inputName] = [];
            return allFiles[inputName];
        }

        function countFiles(fieldID){
            var files = getFiles(fieldID);
            return files.length;
        }

        function addFile(fieldID, fileInfo){

            var files = getFiles(fieldID);

            files.unshift(fileInfo);
            setUploadedFiles(fieldID, files);
        }

        function setUploadedFiles(fieldID, files){
            var allFiles = getAllFiles();
            var $uploadedFiles = $('#gform_uploaded_files_' + formID);
            var inputName = getInputName(fieldID);
            allFiles[inputName] = files;
            $uploadedFiles.val($.toJSON(allFiles));
        }

        function getInputName(fieldID){
            return "input_" + fieldID;
        }

        // fixes drag and drop in IE10
        $("#" + settings.drop_element).on({
            "dragenter": ignoreDrag,
            "dragover": ignoreDrag
        });

        function ignoreDrag( e ) {
            e.preventDefault();
        }
    }


    function generateUniqueID() {
        return 'xxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0, v = c == 'x' ? r : r & 0x3 | 0x8;
            return v.toString(16);
        });
    }

	function htmlEncode(value){
		return $('<div/>').text(value).html();
	}

}(window.gfMultiFileUploader = window.gfMultiFileUploader || {}, jQuery));


//----------------------------------------
//------ GENERAL FUNCTIONS -------
//----------------------------------------

function gformInitSpinner(formId, spinnerUrl) {

	jQuery('#gform_' + formId).submit(function () {
		gformAddSpinner(formId, spinnerUrl);
	});

}

function gformAddSpinner(formId, spinnerUrl) {

	if (typeof spinnerUrl == 'undefined' || !spinnerUrl) {
		spinnerUrl = gform.applyFilters('gform_spinner_url', gf_global.spinnerUrl, formId);
	}

	if (jQuery('#gform_ajax_spinner_' + formId).length == 0) {
		/**
		 * Filter the element after which the AJAX spinner will be inserted.
		 *
		 * @since 2.0
		 *
		 * @param object $targetElem jQuery object containing all of the elements after which the AJAX spinner will be inserted.
		 * @param int    formId      ID of the current form.
		 */
		var $spinnerTarget = gform.applyFilters('gform_spinner_target_elem', jQuery('#gform_submit_button_' + formId + ', #gform_wrapper_' + formId + ' .gform_next_button, #gform_send_resume_link_button_' + formId), formId);
		$spinnerTarget.after('<img id="gform_ajax_spinner_' + formId + '"  class="gform_ajax_spinner" src="' + spinnerUrl + '" alt="" />');
	}

}

//----------------------------------------
//------ EVENT FUNCTIONS -----------------
//----------------------------------------

var __gf_keyup_timeout;

jQuery( document ).on( 'change keyup', '.gfield input, .gfield select, .gfield textarea', function( event ) {
    gf_raw_input_change( event, this );
} );

function gf_raw_input_change( event, elem ) {

    // clear regardless of event type for maximum efficiency ;)
    clearTimeout( __gf_keyup_timeout );

    var $input    = jQuery( elem ),
        htmlId    = $input.attr( 'id' ),
        fieldId   = gf_get_input_id_by_html_id( htmlId ),
        formId    = gf_get_form_id_by_html_id( htmlId ),
	    /**
	     * Filter the field meta generated by a raw input change.
	     *
	     * @since 2.4.1
	     *
	     * @param object fieldMeta An object containing the field ID and form ID of the triggering Gravity Forms field.
	     * @param object $input    The jQuery object for the triggering field element.
	     * @param object event     The raw JS event.
	     */
        fieldMeta = gform.applyFilters( 'gform_field_meta_raw_input_change', { fieldId: fieldId, formId: formId }, $input, event );

    fieldId = fieldMeta.fieldId;
    formId = fieldMeta.formId;

    if( ! fieldId ) {
        return;
    }

    var isChangeElem = $input.is( ':checkbox' ) || $input.is( ':radio' ) || $input.is( 'select' ),
        isKeyupElem  = ! isChangeElem || $input.is( 'textarea' );

    if( event.type == 'keyup' && ! isKeyupElem ) {
        return;
    } else if( event.type == 'change' && ! isChangeElem && ! isKeyupElem ) {
        return;
    }

    if( event.type == 'keyup' ) {
        __gf_keyup_timeout = setTimeout( function() {
            gf_input_change( elem, formId, fieldId );
        }, 300 );
    } else {
        gf_input_change( elem, formId, fieldId );
    }

}

/**
 * Get the input id from a form element's HTML id.
 *
 * @param {string} htmlId The HTML id of a form element.
 *
 * @returns {string} inputId The input id.
 */
function gf_get_input_id_by_html_id( htmlId ) {

    var ids = gf_get_ids_by_html_id( htmlId ),
        id  = ids[ ids.length - 1 ];

    if ( ids.length == 3 ) {
        ids.shift();
        id = ids.join( '.' );
    }

    return id;
}

/**
 * Get the form id from a form element's HTML id.
 *
 * @param {string} htmlId The HTML id of a form element.
 *
 * @returns {string} formId The form id.
 */
function gf_get_form_id_by_html_id( htmlId ) {
    var ids = gf_get_ids_by_html_id( htmlId );
    return ids[0];
}

/**
 * Get the form, field, and input id by a form elements HTML id.
 *
 * Note: Only multi-input fields will be return an input ID.
 *
 * @param {string} htmlId The HTML id of a form element.
 *
 * @returns {array} ids An array contain the form, field and input id.
 */
function gf_get_ids_by_html_id( htmlId ) {
    var ids = htmlId ? htmlId.split( '_' ) : [];
    for( var i = ids.length - 1; i >= 0; i-- ) {
        if ( ! gformIsNumber( ids[ i ] ) ) {
            ids.splice( i, 1 );
        }
    }
    return ids;
}

function gf_input_change( elem, formId, fieldId ) {
    gform.doAction( 'gform_input_change', elem, formId, fieldId );
}

function gformExtractFieldId( inputId ) {
    var fieldId = parseInt( inputId.toString().split( '.' )[0],10 );
    return ! fieldId ? inputId : fieldId;
}

function gformExtractInputIndex( inputId ) {
    var inputIndex = parseInt( inputId.toString().split( '.' )[1],10 );
    return ! inputIndex ? false : inputIndex;
}

jQuery( document ).on( 'submit.gravityforms', '.gform_wrapper form', function( event ) {

	var formWrapper = jQuery( this ).closest( '.gform_wrapper' ),
		formID = formWrapper.attr( 'id' ).split( '_' )[ 2 ],
		hasPages = formWrapper.find( '.gform_page' ).length > 0,
		sourcePage = parseInt( formWrapper.find( 'input[name^="gform_source_page_number_"]' ).val(), 10 ),
		targetPage = parseInt( formWrapper.find( 'input[name^="gform_target_page_number_"]' ).val(), 10 ),
		isSubmit = targetPage === 0,
		isNextSubmit = ! isSubmit && ( targetPage > sourcePage ),
		isSave = jQuery( '#gform_save_' + formID ).val() === '1',
		submitButton;

	// Get the next or submit button.
	if ( hasPages ) {
		// Get the visible page.
		var visiblePage = formWrapper.find( '.gform_page:visible' ),
			buttonType = isNextSubmit ? 'next' : 'submit';

		submitButton = visiblePage.find( '.gform_page_footer [id^="gform_' + buttonType + '_button_"]' );
	} else {
		submitButton = formWrapper.find( '#gform_submit_button_' + formID );
	}

	var isButtonHidden = ! submitButton.is(':visible'),
		abortSubmission = ! isSave && ( isSubmit || isNextSubmit ) && isButtonHidden;

	// If we are not saving or returning to an earlier page and the next/submit button is hidden abort the submission.
	if ( abortSubmission ) {
		window[ 'gf_submitting_' + formID ] = false;
		formWrapper.find( '.gform_ajax_spinner' ).remove();
		event.preventDefault();
	} else if ( isSubmit || isSubmit ) {
        var $reCaptcha = formWrapper.find( '.ginput_recaptcha' );

        if ( $reCaptcha.length !== 0 && $reCaptcha.data( 'size' ) === 'invisible' ) {
            // Check for the verified invisible captcha token first.
            var $reCaptchaResponse = formWrapper.find( 'input[name="g-recaptcha-response"]' );
            if ( $reCaptchaResponse.length === 0 ) {
                $reCaptchaResponse = $reCaptcha.find( '.g-recaptcha-response' );
            }
            var token = $reCaptchaResponse.val();
            if ( ! token ) {
                // Execute the invisible captcha.
                grecaptcha.execute($reCaptcha.data('widget-id'));
                // Once the reCaptcha is triggered, set gf_submitting to true, so the form could be submitted if the
                // reCaptcha modal is closed (by clicking on the area out of the modal or the reCaptcha response expires)
                window['gf_submitting_' + formID] = false;
                event.preventDefault();
            }
        }
    }

} );



//----------------------------------------
//------ HELPER FUNCTIONS ----------------
//----------------------------------------

if( ! window['rgars'] ) {
    function rgars( array, prop ) {

        var props = prop.split( '/' ),
            value = array;

        for( var i = 0; i < props.length; i++ ) {
            value = rgar( value, props[ i ] );
        }

        return value;
    }
}

if( ! window['rgar'] ) {
    function rgar( array, prop ) {
        if ( typeof array[ prop ] != 'undefined' ) {
            return array[ prop ];
        }
        return '';
    }
}

String.prototype.format = function () {
    var args = arguments;
    return this.replace(/{(\d+)}/g, function (match, number) {
        return typeof args[number] != 'undefined' ? args[number] : match;
    });
};
