jQuery(document).ready(gformInitDatepicker);

function gformInitDatepicker() {
    jQuery('.datepicker').each(function () {
    	var $element = jQuery(this);
	    gformInitSingleDatepicker( $element) ;
    });
}

function gformInitSingleDatepicker( $element ) {
	var inputId = $element.attr( 'id' ) ? $element.attr( 'id' ) : '',
		optionsObj = {
			yearRange: '-100:+20',
			showOn: 'focus',
			dateFormat: 'mm/dd/yy',
			changeMonth: true,
			changeYear: true,
			suppressDatePicker: false,
			onClose: function () {
				$element.focus();
				var self = this;
				this.suppressDatePicker = true;
				setTimeout( function() {
					self.suppressDatePicker = false;
				}, 200 );
			},
			beforeShow: function( input, inst ) {
				return ! this.suppressDatePicker;
			}
		};

	if ($element.hasClass('dmy')) {
		optionsObj.dateFormat = 'dd/mm/yy';
	} else if ($element.hasClass('dmy_dash')) {
		optionsObj.dateFormat = 'dd-mm-yy';
	} else if ($element.hasClass('dmy_dot')) {
		optionsObj.dateFormat = 'dd.mm.yy';
	} else if ($element.hasClass('ymd_slash')) {
		optionsObj.dateFormat = 'yy/mm/dd';
	} else if ($element.hasClass('ymd_dash')) {
		optionsObj.dateFormat = 'yy-mm-dd';
	} else if ($element.hasClass('ymd_dot')) {
		optionsObj.dateFormat = 'yy.mm.dd';
	}

	if ($element.hasClass('datepicker_with_icon')) {
		optionsObj.showOn = 'both';
		optionsObj.buttonImage = $element.parent().siblings("[id^='gforms_calendar_icon_input']").val();
		optionsObj.buttonImageOnly = true;
		optionsObj.buttonText = '';
	}

	inputId = inputId.split('_');

	// allow the user to override the datepicker options object
	optionsObj = gform.applyFilters('gform_datepicker_options_pre_init', optionsObj, inputId[1], inputId[2]);

	$element.datepicker(optionsObj);

	// We give the input focus after selecting a date which differs from default Datepicker behavior; this prevents
	// users from clicking on the input again to open the datepicker. Let's add a manual click event to handle this.
	if( $element.is( ':input' ) ) {
		$element.click( function() {
			$element.datepicker( 'show' );
		} );
	}

}