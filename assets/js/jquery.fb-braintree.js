/**
 * Braintree gravity forms integration.
 *
 * @package fatbeehive
 */

jQuery( document ).ready( function( $ ) {
	var braintrees = $( '.gravityformsfbgfbraintree' );
	if ( braintrees.length > 0 ) {
		braintrees.each( function( index, element ){
			$( this ).removeClass( 'gform_hidden' ).show();
			$( this ).find( 'input' ).remove();

			// Create drop in UI.
			braintree.dropin.create( {
				authorization: fb_gf_braintree_settings.tokenization_key,
				container: '#' + $( this ).attr( 'id' ),
				paypal: {
					flow: 'checkout',
					amount: $( '#amount' ).val(),
					currency: 'GBP',
					buttonStyle: {
						color: 'blue',
						shape: 'rect',
						size: 'medium'
					}
				}
				},
				function (createErr, instance) {
					if ( createErr ) {
						console.log( 'Braintree create drop-in UI error:' );
						console.log( createErr );
					}

					// Block form submission and get token.
					var _form = $( '#' + instance['_dropinWrapper'].id ).parents( 'form' );
					_form.on( 'submit', function (event) {
						if ( ! _form.find( '.payment_nonce input' ).val() ) {
							event.preventDefault();

							_form.find( 'input[type="submit"]' ).slideUp( 'fast' );

							instance.requestPaymentMethod(function (err, payload) {
								if (err) {
									console.log( 'Request Payment Method Error', err );
									return;
								}

								// Add the nonce to the form and submit.
								var _form = $( '#' + instance['_dropinWrapper'].id ).parents( 'form' );
								_form.find( '.payment_nonce input' ).val( payload.nonce );
								_form.submit();

								// Add loading gif.
								_form.append( '<img class="payment-loading" src="/wp-admin/images/loading.gif" alt="loading" />' );
							});
						}
					});
				}
			);
		});
	} // End if().
});
