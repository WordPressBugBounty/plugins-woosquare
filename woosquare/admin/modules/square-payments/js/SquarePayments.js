(function ( $ ) {
	'use strict';
	const appId = square_params.application_id;
	const locationId = square_params.locationId; 
	let card;
	async function initializeCard(payments) {
		
		// if(jQuery('#card-container').html().length > 1){
		// 	card.destroy();
		// }
		card = await payments.card();
		 
		setTimeout(function(){ 	
			
			card.attach('#card-container');
			const cardButton = document.getElementById(
				'place_order'
			);
			
			jQuery('#card-initialization').hide();
			function handlePaymentMethodSubmission(event, paymentMethod, shouldVerify = false,payments) {
				event.preventDefault();
				event.stopPropagation();
				try {
					// disable the submit button as we await tokenization and make a
					// payment request.
					// cardButton.disabled = true;
					jQuery('.woocommerce-error').remove();
					const token =  cc_tokenize(paymentMethod,payments);
				} catch (e) {
					// cardButton.disabled = false;
					console.error(e.message);
				}
			}
			jQuery('.wc-block-components-checkout-place-order-button').on('click', function(event){
				if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_plus'+square_params.sandbox || jQuery('.wooSquare-checkout #card-container').length > 0){
					if(!jQuery('.square-nonce').val()){
						event.stopPropagation();
						handlePaymentMethodSubmission(event, card, true,payments);
					}
				}
			})
			cardButton.addEventListener('click', async function (event) {
				if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() =='square_plus'+square_params.sandbox){
					handlePaymentMethodSubmission(event, card, true,payments);
				}
			});
		}, 500);
		return card;
    }


	// This function tokenizes a payment method. 
	// The â€˜errorâ€™ thrown from this async function denotes a failed tokenization,
	// which is due to buyer error (such as an expired card). It is up to the
	// developer to handle the error and provide the buyer the chance to fix
	// their mistakes.
	async function cc_tokenize(paymentMethod,payments) {
		const tokenResult = await paymentMethod.tokenize();
		
		if (tokenResult.status === 'OK') {
		    if(jQuery( '#sq-card-saved' ).is(":checked")){
    			var intten = 'STORE';
    		} else if(square_params.subscription) {
    			var intten = 'STORE';
    		} else if(
    		jQuery( '._wcf_flow_id' ).val() != null ||  
    		jQuery( '._wcf_flow_id' ).val() != undefined || 
    		
    		jQuery( '._wcf_checkout_id' ).val() != null ||  
    		jQuery( '._wcf_checkout_id' ).val() != undefined 
    		) {
    			var intten = 'STORE';
    		} else if(jQuery( '.is_preorder' ).val()) {
    			var intten = 'STORE';
    		} else {
    			var intten = 'CHARGE';
    		}
    		const verificationDetails = {
    			intent: intten, 
    			amount: square_params.cart_total, 
    			currencyCode: square_params.get_woocommerce_currency, 
    			billingContact: {}
    		};
    		const verificationResults = await payments.verifyBuyer(
    			tokenResult.token,
    			verificationDetails
    
    		);
    		console.log(verificationResults);console.log(tokenResult);
            if (verificationResults !== undefined && tokenResult.token !== undefined) {
					const pay_form = jQuery( 'form.wc-block-checkout__form, form#order_review, form.woocommerce-checkout' );
					pay_form.append( '<input type="hidden" class="buyerVerification-token" name="buyerVerification_token" value="'+ verificationResults.token +'"  />' );
        			if ( document.getElementsByClassName('woocommerce-error')){
            			jQuery('#place_order').prop('disabled', false);		
            		} 
            		// inject nonce to a hidden field to be submitted
            		pay_form.append( '<input type="hidden" class="square-nonce" name="square_nonce" value="' + tokenResult.token + '" />' );
            		
            		    var pay_for_order = getUrlParameter('pay_for_order');
            			if(pay_for_order){
                		    jQuery('form#order_review').submit(); 
                	    }
            		
            		
            		// jQuery('form.woocommerce-checkout').submit();
					if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_plus'+square_params.sandbox ){
						// pay_form.submit();
						
						if(jQuery(".wc-block-components-checkout-place-order-button").is(":visible")){
						    jQuery(".wc-block-components-checkout-place-order-button").trigger("click");
						} else if (jQuery(".square-nonce").val()){
    					    pay_form.submit();
    					}
						
					} else if (jQuery(".square-nonce").val()) {
					    pay_form.submit();
					}
			} else {
				jQuery('#place_order').prop('disabled', false);
			}
		} else {
			let errorMessage = `Tokenization failed-status: ${tokenResult.status}`;
			if (tokenResult.errors) {
				errorMessage += ` and errors: ${JSON.stringify(
					tokenResult.errors
				)}`;
				jQuery('#place_order').prop('disabled', false);
			}	
			throw new Error(errorMessage);
		}
	}
	var getUrlParameter = function getUrlParameter(sParam) {
        var sPageURL = window.location.search.substring(1),
            sURLVariables = sPageURL.split('&'),
            sParameterName,
            i;
    
        for (i = 0; i < sURLVariables.length; i++) {
            sParameterName = sURLVariables[i].split('=');
    
            if (sParameterName[0] === sParam) {
                return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
            }
        }
        return false;
    };

	// document.addEventListener('DOMContentLoaded', async function () {
		
	jQuery( window  ).on("load", function() {
		if (!window.Square) {
			throw new Error('Square.js failed to load properly');
		}
		const payments = window.Square.payments(appId, locationId);
		// let card;
        /*try {
			card = await initializeCard(payments);
		} catch (e) {
			console.error('Initializing Card failed', e);
			return;
		}*/
		
		var pay_for_order = getUrlParameter('pay_for_order');
		// console.log(pay_for_order);
		if(pay_for_order){
			jQuery('#card-initialization').show();
		    card =  initializeCard(payments);
	    }
		// if(jQuery('.payment_method_square_ach_payment'+square_params.sandbox ).length == 0){ 
			// jQuery( document.body ).on( 'updated_checkout', function() {
				// alert('123213');
				// if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_plus'+square_params.sandbox ){
					// let card;
					setTimeout(() => {
						try {
							if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_plus'+square_params.sandbox
							||
							jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_plus'+square_params.sandbox
							){
								jQuery('#card-initialization').show();
								card =  initializeCard(payments);
							}
							return card;
						} catch (e) {
							
							jQuery('#card-initialization').hide();
							console.error('Initializing Card failed', e);
							return;
						}
					}, 150);
					
				// }
			// })
		// }
		
		$('form.checkout').on('change', '.woocommerce-checkout-payment input', function(){
			if(jQuery(this).attr('name') != 'terms'){
				if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_plus'+square_params.sandbox ){
				// let card;
				try {
					if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_plus'+square_params.sandbox ){
                						
                		 if(jQuery('#card-container').html().length > 1){
                		 	card.destroy();
                		 }
							jQuery('#card-initialization').show();
						card =  initializeCard(payments);
					}
					return card;
				} catch (e) {
					
			jQuery('#card-initialization').hide();
					console.error('Initializing Card failed', e);
					return;
				}
			}
			}
		});
		jQuery('form.wc-block-checkout__form').on('change', "input[name=radio-control-wc-payment-method-options]", function(){
			if(jQuery(this).attr('name') != 'terms'){
				if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_plus'+square_params.sandbox ){
				// let card;
				try {
					if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_plus'+square_params.sandbox ){
						
							jQuery('#card-initialization').show();
						card =  initializeCard(payments);
					}
					return card;
				} catch (e) {
					
			jQuery('#card-initialization').hide();
					console.error('Initializing Card failed', e);
					return;
				}
			}
			}
		});
		
		
		
	});
}( jQuery ) );

jQuery( window  ).on("load", function() {
	setTimeout(function(){ 	
		hideunhide();
	}, 600);
	jQuery('form.wc-block-checkout__form').on('change', "input[name=radio-control-wc-payment-method-options]", function(){
		hideunhide();
	})
});

jQuery( function($){
	$('form.checkout').on('change', '.woocommerce-checkout-payment input', function(){
		hideunhide();
	});
});
function hideunhide(){
	console.log(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val());
	if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_plus'+square_params.sandbox
		|| jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_plus'+square_params.sandbox
	){
		jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'flex');
	}else if( jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_google_pay'+square_params.sandbox
		|| jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_google_pay'+square_params.sandbox
	){
		jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'none');
	} else if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_apple_pay'+square_params.sandbox
		|| jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_apple_pay'+square_params.sandbox
	){
		jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'none');
	}else if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_ach_payment'+square_params.sandbox
		|| jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_ach_payment'+square_params.sandbox
	){
		jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'none');
	}else if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_after_pay'+square_params.sandbox
		|| jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_after_pay'+square_params.sandbox
	){
		jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'none');
	}else if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_cash_app_pay'+square_params.sandbox
		|| jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_cash_app_pay'+square_params.sandbox
	){
		jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'none');
	} else {
		jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'flex');
	}
}