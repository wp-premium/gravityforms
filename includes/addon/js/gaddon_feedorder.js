( function( $ ) {
	
	GFFeedOrder = function( args ) {
		
		for ( var prop in args ) {
			if ( args.hasOwnProperty( prop ) ) {
				this[prop] = args[prop];
			}
		}

		this.init = function() {

			/* Get needed variables. */
			this.addon  = gaddon_feedorder_strings.addon;
			this.formId = this.getUrlParameter( 'id' );
			this.nonce  = gaddon_feedorder_strings.nonce;

			/* Enable sorting if feed list is for Add-On. */
			if ( this.addon == this.getUrlParameter( 'subview' ) && $( '.wp-list-table tbody tr' ).length > 1 ) {		
				this.enableSorting();
			}
			
		};
		
		/* Initialize the sortable jQuery UI function on the feed list. */
		this.enableSorting = function() {
			
			/* Add sort handle to table. */
			var sortHandleMarkup = '<td class="sort-column"><i class="fa fa-bars feed-sort-handle"></i></td>';
			$( '.wp-list-table thead tr, .wp-list-table tfoot tr' ).append( '<th class="sort-column"></th>' );
			$( '.wp-list-table tbody tr' ).append( sortHandleMarkup );
			
			$( '.wp-list-table tbody' ).sortable( {
				cursor:      'move',
				handle:      '.feed-sort-handle',
				placeholder: 'feed-placeholder',
				tolerance:   'pointer',
				create:      function() { $( '.wp-list-table' ).addClass( 'feed-list-sortable' ); },
				helper:      this.fixSortableColumnWidths,
				start:       this.setPlaceholderHeight,
				update:      this.updateFeedOrder				
			} );
			
		}
		
		/* Fix column widths on feed being sorted. */
		this.fixSortableColumnWidths = function( event, tr ) {
			var $originals = tr.children();
			var $helper = tr.clone();
			$helper.children().each( function( index ) {
				$( this ).width( $originals.eq( index ).width() );
			});
			return $helper;
		}
		
		/* Get the feed order. */
		this.getFeedOrder = function() {
			
			/* Get all the checkboxes from the feed list table. */
			var feed_checkboxes = $( '.wp-list-table tbody .check-column input[type="checkbox"]' );
			
			/* Map a function to the feed checkboxes array that returns the checkbox value and return said array. */
			return feed_checkboxes.map( function() {
				return $( this ).val();
			} ).get();
			
		}

		/* Helper function to get the value of a URL parameter. */
		this.getUrlParameter = function( name ) {
			
			var value = new RegExp( '[\?&]' + name + '=([^&#]*)' ).exec( window.location.href );   
    		return ( value === null ) ? null : ( value[1] || 0 );

		}

		/* Set the height of the feed placeholder. */
		this.setPlaceholderHeight = function( event, ui ) {
			
			/* Set the height of the placeholder to the height of the feed being moved. */
			$( '.wp-list-table .feed-placeholder' ).height( ui.item.height() );
			
		}
		
		/* Save the new feed order to the form meta. */
		this.updateFeedOrder = function( event, ui ) {
			
			$.ajax( ajaxurl, {
				method:   'POST',
				dataType: 'JSON',
				data:     {
					action:     'gf_save_feed_order',
					addon:      GFFeedOrderObj.addon,
					form_id:    GFFeedOrderObj.formId,
					feed_order: GFFeedOrderObj.getFeedOrder(),
					nonce:      GFFeedOrderObj.nonce
				}				
			} );
			
		}

		this.init();
		
	}
	
	$( document ).ready( function() { GFFeedOrderObj = new GFFeedOrder(); } );
	
} )( jQuery );