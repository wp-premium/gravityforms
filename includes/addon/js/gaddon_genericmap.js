var GFGenericMap = function( options ) {

	var self = this;

	self.options = options;
	self.UI = jQuery( '#gaddon-setting-row-'+ self.options.fieldName );

	self.init = function() {

		self.bindEvents();

		self.setupData();

		self.setupRepeater();

	};

	self.bindEvents = function() {

		self.UI.on( 'change', 'select[name="_gaddon_setting_'+ self.options.keyFieldName +'"]', function() {

			var $select    = jQuery( this ),
				$selectElm = $select.data( 'chosen' ) ? $select.siblings( '.chosen-container' ) : ( $select.data( 'select2' ) ? $select.siblings( '.select2-container' ) : $select ),
				$input     = $select.siblings( '.custom-key-container' );

			if( $select.val() != 'gf_custom' ) {
				return;
			}

			$selectElm.fadeOut( function() {
				$input.fadeIn().focus();
			} );

		} );

		self.UI.on( 'change', 'select[name="_gaddon_setting_'+ self.options.valueFieldName +'"]', function() {

			var $select    = jQuery( this ),
				$selectElm = $select.data( 'chosen' ) ? $select.siblings( '.chosen-container' ) : ( $select.data( 'select2' ) ? $select.siblings( '.select2-container' ) : $select ),
				$input     = $select.siblings( '.custom-value-container' );

			if ( $select.val() != 'gf_custom' ) {
				return;
			}

			$selectElm.fadeOut( function() {
				$input.fadeIn().focus();
			} );

		} );

		self.UI.on( 'click', 'a.custom-key-reset', function( event ) {

			event.preventDefault();

			var $reset     = jQuery( this ),
				$input     = $reset.parents( '.custom-key-container' ),
				$select    = $input.siblings( 'select.key' ),
				$selectElm = $select.data( 'chosen' ) ? $select.siblings( '.chosen-container' ) : ( $select.data( 'select2' ) ? $select.siblings( '.select2-container' ) : $select );

			$input.fadeOut( function() {
				$input.find( 'input' ).val( '' ).change();
				$select.val( '' ).trigger( 'change' );
				$selectElm.fadeIn().focus();
			} );

		} );

		self.UI.on( 'click', 'a.custom-value-reset', function( event ) {

			event.preventDefault();

			var $reset     = jQuery( this ),
				$input     = $reset.parents( '.custom-value-container' ),
				$select    = $input.siblings( 'select.value' ),
				$selectElm = $select.data( 'chosen' ) ? $select.siblings( '.chosen-container' ) : ( $select.data( 'select2' ) ? $select.siblings( '.select2-container' ) : $select );

			$input.fadeOut( function() {
				$input.find( 'input' ).val( '' ).change();
				$select.val( '' ).trigger( 'change' );
				$selectElm.fadeIn().focus();
			} );

		} );

		self.UI.closest( 'form' ).on( 'submit', function( event ) {

			jQuery( '[name^="_gaddon_setting_'+ self.options.fieldName +'_"]' ).each( function( i ) {

				jQuery( this ).removeAttr( 'name' );

			} );

		} );

	};

	self.setupData = function() {

		var data = jQuery( '#' + self.options.fieldId ).val();
		self.data = data ? jQuery.parseJSON( data ) : null;

		if ( ! self.data ) {
			self.data = [ {
				key: '',
				value: '',
				custom_key: '',
				custom_value: ''
			} ];
		}

	}

	self.setupRepeater = function() {

		var limit = self.options.limit > 0 ? self.options.limit : 0;

		self.UI.find( 'tbody.repeater' ).repeater( {

			limit:              limit,
			items:              self.data,
			addButtonMarkup:    '<i class="gficon-add"></i>',
			removeButtonMarkup: '<i class="gficon-subtract"></i>',
			callbacks:          {
				add:  function( obj, $elem, item ) {

					var key_select = $elem.find( 'select[name="_gaddon_setting_'+ self.options.keyFieldName +'"]' );

					if ( ! item.custom_key && ( key_select.length > 0 && key_select.val() !== 'gf_custom' ) ) {
						$elem.find( '.custom-key-container' ).hide();
					} else {
						$elem.find( '.key' ).hide();
					}

					var value_select = $elem.find( 'select[name="_gaddon_setting_'+ self.options.valueFieldName +'"]' );

					if ( ! item.custom_value && ( value_select.length > 0 && value_select.val() !== 'gf_custom' ) ) {
						$elem.find( '.custom-value-container' ).hide();
					} else {
						$elem.find( '.value' ).hide();
					}

					if ( self.options.mergeTags ) {
						new gfMergeTagsObj( form, $elem.find( '.custom-value-container input' ) );
						$elem.find( '.custom-value-container' ).addClass( 'supports-merge-tags' );
					}

					if ( window.hasOwnProperty( 'gform' ) ) {
						gform.doAction( 'gform_fieldmap_add_row', obj, $elem, item );
					}

				},
				save: function( obj, data ) {

					jQuery( '#'+ self.options.fieldId ).val( JSON.stringify( data ) );

				}
			}

		} );

	}

	return self.init();

};