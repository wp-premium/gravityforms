jQuery(document).ready(function() {
    jQuery( ".gf_tooltip" ).tooltip({
	show: 500,
	hide: 1000,
	content: function () {
              return jQuery(this).prop('title');
          }
	});
});