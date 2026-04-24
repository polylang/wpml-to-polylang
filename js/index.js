jQuery( function ( $ ) {
	/**
	 * WPML → Polylang migration toolkit.
	 *
	 * Each form on the page is independent: it has its own submit button and its
	 * own status div (identified by the form's data-tool attribute).
	 */

	$( '.wpml-pll-form' ).each( function () {
		var form   = $( this );
		var toolId = form.data( 'tool' );
		var status = $( '#wpml-status-' + toolId );
		var btn    = form.find( 'button[type="submit"]' );

		form.on( 'submit', function ( e ) {
			e.preventDefault();

			btn.prop( 'disabled', true ).text( btn.text().trim() );
			status.removeClass( 'is-done' ).text( '' );

			var data = serialiseForm( form );
			sendRequest( data, status, btn );
		} );
	} );

	function serialiseForm( form ) {
		var out = {};
		$.each( form.serializeArray(), function ( _, field ) {
			out[ field.name ] = field.value;
		} );
		return out;
	}

	function sendRequest( data, status, btn ) {
		$.post( {
			url:      ajaxurl,
			data:     data,
			dataType: 'json',
			success: function ( response ) {
				handleResponse( response, data, status, btn );
			},
			error: function () {
				status.text( 'An unexpected error occurred. Please try again.' );
				btn.prop( 'disabled', false );
			}
		} );
	}

	function handleResponse( response, data, status, btn ) {
		if ( response.done ) {
			status.addClass( 'is-done' ).text( 'Done!' );
			btn.prop( 'disabled', false );
			return;
		}

		if ( response.message ) {
			status.text( response.message );
		}

		// Chain to the next step.
		data['action'] = response.action;
		data['step']   = response.step;
		sendRequest( data, status, btn );
	}
} );
