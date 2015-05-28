jQuery( document ).ready( function( $ ){
	
	$( '.gaddon-setting-select-custom' ).on( 'change', function() {
		
		if ( $( this ).val() == 'gf_custom' )
			$( this ).hide().siblings( '.gaddon-setting-select-custom-container' ).show();
		
	} );
	
	$( '.gaddon-setting-select-custom-container .select-custom-reset' ).on( 'click', function() {
		
		$( this ).parent().siblings('select').show();
		$( this ).parent().hide();
		
	} );

} );