jQuery(
	function( $ ) {
		/**
		 * Batch processing to migrate WPML data to Polylang.
		 */
		window.WPMLToPolylang = {
			/**
			 * Disables the submit button and fires the batch process.
			 */
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

			/**
			 * Transforms submitted form in a convenient array of data.
			 */
			formToArray: function( form ) {
				var ret = {};

				form = form.serializeArray();
				for ( i = 0; i < form.length; i++ ) {
					ret[ form[i].name ] = form[i].value;
				}

				return ret;
			},

			/**
			 * Sends the ajax request.
			 */
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

			/**
			 * Processes the ajax response.
			 */
			success: function( response, data, self ) {
				$( '#wpml-importer-status' ).text( response.message );

				if ( ! response.done ) {
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
