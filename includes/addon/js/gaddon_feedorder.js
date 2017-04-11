var GFFeedOrder = function( args ) {

	var self = this,
	    $    = jQuery;

	/**
	 * Initialize Feed Ordering
	 */
	self.init = function() {

		// Assign options to instance.
		self.options = args;

		// Prepare sorting handle.
		var sortHandleMarkup = '<td class="sort-column"><i class="fa fa-bars feed-sort-handle"></i></td>';

		// Add sorting handle to table.
		$( '.wp-list-table thead tr, .wp-list-table tfoot tr' ).append( '<th class="sort-column"></th>' );
		$( '.wp-list-table tbody tr' ).append( sortHandleMarkup );

		// Initialize sorting.
		self.initSorting();

	};

	/**
	 * Initialize jQuery UI Sortable.
	 */
	self.initSorting = function() {

		$( '.wp-list-table tbody' ).sortable(
			{
				cursor:      'move',
				handle:      '.feed-sort-handle',
				placeholder: 'feed-placeholder',
				tolerance:   'pointer',
				create:      function() { $( '.wp-list-table' ).addClass( 'feed-list-sortable' ); },
				helper:      self.fixSortableColumnWidths,
				start:       self.setPlaceholderHeight,
				update:      self.updateFeedOrder,
			}
		);

	}

	/**
	 * Fix table column widths.
	 */
	self.fixSortableColumnWidths = function( event, tr ) {

		var $originals = tr.children(),
		    $helper    = tr.clone();

		$helper.children().each( function( index ) {
			$( this ).width( $originals.eq( index ).width() );
		} );

		return $helper;

	}

	/**
	 * Get order of feeds.
	 */
	self.getFeedOrder = function() {

		// Get all the checkboxes from the feed list table.
		var feed_checkboxes = $( '.wp-list-table tbody .check-column input[type="checkbox"]' );

		// Map a function to the feed checkboxes array that returns the checkbox value.
		return feed_checkboxes.map( function() {
			return $( this ).val();
		} ).get();

	}

	/**
	 * Set height of the placeholder draggable feed.
	 */
	self.setPlaceholderHeight = function( event, ui ) {

		// Set the height of the placeholder to the height of the feed being moved.
		$( '.wp-list-table .feed-placeholder' ).height( ui.item.height() );

	}

	/**
	 * Save the feed ordering to the database.
	 */
	self.updateFeedOrder = function( event, ui ) {

		$.ajax(
			ajaxurl,
			{
				method:   'POST',
				dataType: 'JSON',
				data:     {
					action:     'gf_save_feed_order',
					addon:      self.options.addon,
					form_id:    self.options.formId,
					feed_order: self.getFeedOrder(),
					nonce:      self.options.nonce,
				}
			}
		);

	}

	this.init();

}
