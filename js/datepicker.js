jQuery(document).ready(gformInitDatepicker);

function gformInitDatepicker() {
    jQuery('.datepicker').each(function () {
        var element = jQuery(this),
            inputId = this.id,
            optionsObj = {
                yearRange: '-100:+20',
                showOn: 'focus',
                dateFormat: 'mm/dd/yy',
                changeMonth: true,
                changeYear: true,
                onClose: function () {
                    element.focus();
                }
            };

        if (element.hasClass('dmy')) {
            optionsObj.dateFormat = 'dd/mm/yy';
        } else if (element.hasClass('dmy_dash')) {
            optionsObj.dateFormat = 'dd-mm-yy';
        } else if (element.hasClass('dmy_dot')) {
            optionsObj.dateFormat = 'dd.mm.yy';
        } else if (element.hasClass('ymd_slash')) {
            optionsObj.dateFormat = 'yy/mm/dd';
        } else if (element.hasClass('ymd_dash')) {
            optionsObj.dateFormat = 'yy-mm-dd';
        } else if (element.hasClass('ymd_dot')) {
            optionsObj.dateFormat = 'yy.mm.dd';
        }

        if (element.hasClass('datepicker_with_icon')) {
            optionsObj.showOn = 'both';
            optionsObj.buttonImage = jQuery('#gforms_calendar_icon_' + inputId).val();
            optionsObj.buttonImageOnly = true;
        }

        inputId = inputId.split('_');

        // allow the user to override the datepicker options object
        optionsObj = gform.applyFilters('gform_datepicker_options_pre_init', optionsObj, inputId[1], inputId[2]);

        element.datepicker(optionsObj);
    });
}