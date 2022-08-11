jQuery(
	function( $ ) {
		window.WPMLToPolylang = {
			init: function() {
				const self = this;

				$( 'form' ).on(
					'submit',
					function( event ) {
						event.preventDefault();

						const form   = $( this );
						const data   = self.formToArray( form );
						const submit = form.find( 'input[type="submit"]' );

						submit.attr( 'disabled', true );
						self.process( data, self );
					}
				);
			},

			formToArray: function( form ) {
				var ret = {};

				form = form.serializeArray();
				for ( i = 0; i < form.length; i++ ) {
					ret[ form[i].name ] = form[i].value;
				}

				return ret;
			},

			process: function( data, self ) {
				$.post(
					{
						url: ajaxurl,
						data: data,
						dataType: 'json',
						success: function ( response ) {
							self.success( response, data, self );
						}
					}
				);
			},

			success: function( response, data, self ) {
				if ( response.done ) {
				} else {
					data['action'] = response.action;
					data['step']   = response.step;
					self.process( data, self );
				}
			}
		}
	}
);

jQuery(
	function( $ ) {
		window.WPMLToPolylang && window.WPMLToPolylang.init();
	}
);
