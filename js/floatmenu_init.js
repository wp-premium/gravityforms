// change the menu position based on the scroll position
window.onscroll = function() {   
    
    var toolbar = jQuery( '#gf_form_toolbar' );
    var floatMenu = jQuery( '#floatMenu' );
    
    if( window.XMLHttpRequest && toolbar.length > 0 ) {
        
        var basePosition = toolbar.offset().top;
        
        if( document.documentElement.scrollTop > basePosition || self.pageYOffset > basePosition ) {
            floatMenu.css( { position: 'fixed', top: '40px' } );
        } else {
            floatMenu.css( { position: 'static', top: '40px' } );
        }
    }
    
}
