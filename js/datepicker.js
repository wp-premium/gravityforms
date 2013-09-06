jQuery(document).ready(gformInitDatepicker);

function gformInitDatepicker(){
    jQuery('.datepicker').each(
        function (){
            var element = jQuery(this);
            var format = "mm/dd/yy";

            if(element.hasClass("mdy"))
                format = "mm/dd/yy";
            else if(element.hasClass("dmy"))
                format = "dd/mm/yy";
            else if(element.hasClass("dmy_dash"))
                format = "dd-mm-yy";
            else if(element.hasClass("dmy_dot"))
                format = "dd.mm.yy";
            else if(element.hasClass("ymd_slash"))
                format = "yy/mm/dd";
            else if(element.hasClass("ymd_dash"))
                format = "yy-mm-dd";
            else if(element.hasClass("ymd_dot"))
                format = "yy.mm.dd";

            var image = "";
            var showOn = "focus";
            if(element.hasClass("datepicker_with_icon")){
                showOn = "both";
                image = jQuery('#gforms_calendar_icon_' + this.id).val();
            }

            element.datepicker({ yearRange: '-100:+20', showOn: showOn, buttonImage: image, buttonImageOnly: true, dateFormat: format, changeMonth: true, changeYear: true });
        }
    );
}

