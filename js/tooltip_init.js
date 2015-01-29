jQuery(document).ready(function() {
    gform_initialize_tooltips();
});

function gform_initialize_tooltips(){
    jQuery( ".gf_tooltip" ).tooltip({
        show: 500,
        hide: 1000,
        content: function () {
            return jQuery(this).prop('title');
        }
    });
}