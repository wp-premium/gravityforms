/*
Copyright 2008 by Marco van Hylckama Vlieg
web: http://www.i-marco.nl/weblog/
email: marco@i-marco.nl
Free for use
*/

function initMenus() {
	jQuery('ul.menu ul').hide();
	jQuery.each(jQuery('ul.menu'), function(){
		jQuery('#' + this.id + '.expandfirst ul:first').show();
	});
	jQuery('ul.menu li .button-title-link').click(
		function() {
			var checkElement = jQuery(this).next();
			var parent = this.parentNode.parentNode.id;

			if(jQuery('#' + parent).hasClass('noaccordion')) {
				jQuery(this).next().slideToggle('normal');
				return false;
			}
			if((checkElement.is('ul')) && (checkElement.is(':visible'))) {
				if(jQuery('#' + parent).hasClass('collapsible')) {
					jQuery('#' + parent + ' ul:visible').slideUp('normal', function(){jQuery(this).prev().removeClass('gf_button_title_active')});
				}
				return false;
			}
			if((checkElement.is('ul')) && (!checkElement.is(':visible'))) {
				jQuery('#' + parent + ' ul:visible').slideUp('normal', function(){jQuery(this).prev().removeClass('gf_button_title_active')});
				checkElement.slideDown('normal', function(){jQuery(this).prev().addClass('gf_button_title_active')});
				return false;
			}
		}
	);
}
jQuery(document).ready(function() {initMenus();});
jQuery(document).ready(function() {	
	jQuery('div.add-buttons-title').append('<span class="add-buttons-caret-down"><i class="fa fa-caret-down"></i></span>');
});