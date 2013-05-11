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
jQuery(document).ready(function()
{

    // Notice the use of the each() method to acquire access to each elements attributes
   jQuery('.tooltip').each(function()
   {
        gform_apply_tooltip(this, "gformsstyle", "topRight", "bottomLeft");
   });

   // Notice the use of the each() method to acquire access to each elements attributes
   jQuery('.tooltip_left').each(function()
   {
       gform_apply_tooltip(this, "gformsstyle_left", "topLeft", "bottomRight");
   });

   // Notice the use of the each() method to acquire access to each elements attributes
   jQuery('.tooltip_bottomleft').each(function()
   {
        gform_apply_tooltip(this, "gformsstyle_bottomleft", "bottomLeft", "topRight");
   });

});

function gform_apply_tooltip(element, style_name, target_corner, tooltip_corner){
    jQuery(element).qtip({
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
      });
}


