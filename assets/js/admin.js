/**
 * Simbe AI Website Care — admin scripts.
 */
( function ( $ ) {
	'use strict';

	// Expandable plugin detail rows.
	$( document ).on( 'click', '.simbe-care-details-toggle', function ( event ) {
		event.preventDefault();

		var button = $( this );
		var row    = button.closest( 'tr' ).next( '.simbe-care-details-row' );
		var isOpen = button.attr( 'aria-expanded' ) === 'true';

		row.find( '.simbe-care-details' ).toggleClass( 'is-open', ! isOpen );
		button.attr( 'aria-expanded', ! isOpen );
	} );

	// Manual rescan.
	$( document ).on( 'click', '.simbe-care-rescan', function () {
		var button = $( this );

		if ( button.prop( 'disabled' ) ) {
			return;
		}

		button.prop( 'disabled', true ).text( SimbeCare.i18n.scanning ).addClass( 'is-busy' );

		$.ajax( {
			url: SimbeCare.ajax_url,
			method: 'POST',
			timeout: 0,
			data: {
				action: 'simbe_care_rescan',
				nonce: SimbeCare.nonce
			},
			success: function ( response ) {
				if ( response && response.success ) {
					window.location.reload();
				} else {
					window.alert( SimbeCare.i18n.scan_failed );
				}
			},
			error: function () {
				window.alert( SimbeCare.i18n.scan_failed );
			},
			complete: function () {
				button.prop( 'disabled', false );
			}
		} );
	} );
} )( jQuery );
