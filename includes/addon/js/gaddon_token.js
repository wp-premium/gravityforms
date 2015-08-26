window.GFToken = null;

( function( $ ) {
	
	GFToken = function( args ) {
		
		for ( var prop in args ) {
			if ( args.hasOwnProperty( prop ) )
				this[prop] = args[prop];
		}
		
		this.form = $( '#gform_' + this.formId );

		this.init = function() {
		
			var GFTokenObj = this;
			
			this.tokens = {};

			/* Initialize spinner. */
			if ( ! this.isAjax )
				gformInitSpinner( this.formId );

			/* If multipage form, run on gform_page_loaded. */
			if ( this.hasPages ) {

				$( document ).bind( 'gform_page_loaded', function( event, form_id, current_page ) {
				
					if ( form_id != GFTokenObj.formId)
						return;
					
					if ( current_page != GFTokenObj.pageCount)
						GFTokenObj.saveEntryData();
			
				} );

			}
			
			this.form.submit( function() {
				GFTokenObj.onSubmit();
			} );
			
		};

		this.onSubmit = function() {

			if ( this.form.data('gftokensubmitting') ) {
				return;
			} else {
				event.preventDefault();
				this.form.data( 'gftokensubmitting', true );
			}

			this.saveEntryData();
			this.processTokens();

		}
		
		this.processTokens = function() {
			
			/* Process feeds. */
			for ( var feed_id in this.feeds ) {
				
				this.active_feed = this.feeds[feed_id];
				
				/* Create new feed object so we can store the billing information. */
				var feed = {
					'billing_fields': {},
					'id': this.active_feed.id,
					'name': this.active_feed.name
				};
				
				/* Add billing information to feed object. */
				for ( var billing_field in this.active_feed.billing_fields ) {
					
					field_id = this.active_feed.billing_fields[ billing_field ];
					feed.billing_fields[ billing_field ] = this.entry_data[ field_id ];
					
				}
				
				/* Get credit card token response. */
				window[ this.callback ].createToken( feed, this );
				
			}
			
		}

		this.saveEntryData = function() {
			
			var GFPaymentObj = this,
				input_prefix = 'input_' + this.formId + '_';
				
			if ( ! this.entry_data )
				this.entry_data = {};
			
			this.form.find( 'input[id^="' + input_prefix + '"], select[id^="' + input_prefix + '"], textarea[id^="' + input_prefix + '"]' ).each( function() {
				
				var input_id = $( this ).attr( 'id' ).replace( input_prefix, '' ).replace( '_', '.' ); 
				
				if ( $.inArray( input_id, GFPaymentObj.fields ) >= 0 )				
					GFPaymentObj.entry_data[ input_id ] = $( this ).val();
				
			} );
		
		}
		
		this.saveToken = function( token ) {
			
			/* Add token response to tokens array. */
			this.tokens[ this.active_feed.id ] = {
				'feed_id': this.active_feed.id,
				'response': token
			};
			
			if ( this.tokens.length == this.feeds.length ) {
				
				/* Add tokens to form. */
				this.form.find( this.responseField ).val( $.toJSON( this.tokens ) );
				
				/* Submit the form. */
				this.form.submit();
				
			}
			
		}

		this.init();
		
	}
	
} )( jQuery );