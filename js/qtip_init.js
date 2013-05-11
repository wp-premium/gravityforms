jQuery.fn.qtip.styles.gformsstyle = { // Last part is the name of the style
   width: 275,
   background: '#F1F1F1',
   color: '#424242',
   textAlign: 'left',
   border: { width: 3, radius: 6, color: '#AAAAAA' },
   tip: 'bottomLeft'
}

jQuery.fn.qtip.styles.gformsstyle_left = { // Last part is the name of the style
   width: 275,
   background: '#F1F1F1',
   color: '#424242',
   textAlign: 'left',
   border: { width: 3, radius: 6, color: '#AAAAAA' },
   tip: 'bottomRight'
}

jQuery.fn.qtip.styles.gformsstyle_bottomleft = { // Last part is the name of the style
   width: 275,
   background: '#F1F1F1',
   color: '#424242',
   textAlign: 'left',
   border: { width: 3, radius: 6, color: '#AAAAAA' },
   tip: 'topRight'
}


// Create the tooltips only on document load
jQuery(document).ready(function() {

    gform_initialize_tooltips();

});

function gform_initialize_tooltips( ) {

    // Notice the use of the each() method to acquire access to each elements attributes
    jQuery('.tooltip').each(function() {
        if( jQuery(this).data('qtip') == undefined )
            gform_apply_tooltip(this, "gformsstyle", "topRight", "bottomLeft");
    });

    // Notice the use of the each() method to acquire access to each elements attributes
    jQuery('.tooltip_left').each(function() {
        if( jQuery(this).data('qtip') == undefined )
            gform_apply_tooltip(this, "gformsstyle_left", "topLeft", "bottomRight");
    });

    // Notice the use of the each() method to acquire access to each elements attributes
    jQuery('.tooltip_bottomleft').each(function() {
        if( jQuery(this).data('qtip') == undefined )
            gform_apply_tooltip(this, "gformsstyle_bottomleft", "bottomLeft", "topRight");
    });

}

/**
 * Added support for providing an object of options. If parameter count is less than 4,
 * assume that the first parameter is the element to which the tooltip is being applied
 * and that the second parameter is an options object.
 *
 * @param element
 * @param style_name
 * @param target_corner
 * @param tooltip_corner
 */
function gform_apply_tooltip( element, style_name, target_corner, tooltip_corner ){

    var options = style_name;

    if( arguments.length >= 4 )
        options = gform_get_tooltip_options( element, style_name, target_corner, tooltip_corner );

    jQuery(element).qtip( options );

}

function gform_get_tooltip_options( element, style_name, target_corner, tooltip_corner ) {
    return {
        content: jQuery(element).attr('tooltip'), // Use the tooltip attribute of the element for the content
        show: { delay: 500, solo: true },
        hide: { when: 'mouseout', fixed: true, delay: 200, effect: 'fade' },
        style: style_name,
        position: {
            corner: {
                target: target_corner,
                tooltip: tooltip_corner
            }
        }
    }
}