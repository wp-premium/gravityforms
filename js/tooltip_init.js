jQuery(document).ready(function() {
	gform_initialize_tooltips();
});

function gform_initialize_tooltips() {
	jQuery('.gf_tooltip').tooltip({
		show: 500,
		content: function () {
			return jQuery(this).prop('title');
		},
		open: function (event, ui) {
			if (typeof(event.originalEvent) === 'undefined') {
				return false;
			}

			var $id = jQuery(ui.tooltip).attr('id');
			jQuery('div.ui-tooltip').not('#' + $id).remove();
		},
		close: function (event, ui) {
			ui.tooltip.hover(function () {
					jQuery(this).stop(true).fadeTo(400, 1);
				},
				function () {
					jQuery(this).fadeOut('500', function () {
						jQuery(this).remove();
					});
				});
		}
	});
}
