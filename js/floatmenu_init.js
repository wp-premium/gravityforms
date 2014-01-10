// change the menu position based on the scroll positon
window.onscroll = function()
{
    if( window.XMLHttpRequest ) {
                
        basePosition = jQuery('#gf_form_toolbar').offset().top;
        
        if (document.documentElement.scrollTop > basePosition || self.pageYOffset > basePosition) {
            jQuery('#floatMenu').css('position','fixed');
            jQuery('#floatMenu').css('top', '40px');
        } else {
            jQuery('#floatMenu').css('position','static');
        }
    }
}