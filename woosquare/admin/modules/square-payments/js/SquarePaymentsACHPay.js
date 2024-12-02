(function ( $ ) {
	'use strict';
	
	const appId = square_ach_params.application_id;
	const locationId = square_ach_params.lid;
	const redirectURI = square_ach_params.redirectURL;
	const transactionId = square_ach_params.transactionId;
	
	async function initializeACH(payments) {
		const ach = await payments.ach({ redirectURI, transactionId });
		// Note: ACH does not have an .attach(...) method
		// the ACH auth flow is triggered by .tokenize(...)
		jQuery('.ach-button-div').append('<button id="ach-button">Pay with Bank Account</button>');
		
			jQuery('#ach-initialization').hide();
		const achButton = document.getElementById('ach-button');
		// setTimeout(function(){ 	 
			
			// achButton.disabled = false;
			ach.addEventListener(`ontokenization`, function (event) {
				const { tokenResult, error } = event.detail;
				if (error) {
					achButton.disabled = false; // Add this line.
					// add code here to handle errors
				}
				else if (tokenResult.status === `OK`) {
					var $form = jQuery('form.woocommerce-checkout, form.wc-block-checkout__form, form#order_review');
					$form.append('<input type="hidden" class="square-nonce" name="square_nonce" value="' + tokenResult.token + '" />');
					if(jQuery('form.wc-block-checkout__form').length > 0 ){
						if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_ach_payment'+square_ach_params.sandbox){
							jQuery(".wc-block-components-checkout-place-order-button").trigger("click");
						}
					}else{
						$form.submit();
					}
				}
			});

			function handleACHPaymentMethodSubmission(event, paymentMethod, options, payments ) {
				event.preventDefault();

				try {
					// disable the submit button as we await tokenization and make a
					// payment request.
					if ( document.getElementsByClassName('woocommerce-error')){
						document.getElementById('card_nonce').value = '';
					}
					achButton.disabled = true;
					jQuery('.woocommerce-error').remove();
					const token = ACH_tokenize(paymentMethod, options, payments);
					//const paymentResults = createPayment(token);
					//displayPaymentResults('SUCCESS');
					console.log('token token', token);

				} catch (e) {

					displayPaymentResults('FAILURE');
					achButton.disabled = false;
					console.error(e.message);
				}
			}
			
			if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_ach_payment'+square_ach_params.sandbox || jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_ach_payment'+square_ach_params.sandbox ){
				achButton.addEventListener('click', async function (event) {
					// jQuery('.woocommerce-error').remove();
					
						if(jQuery('form.wc-block-checkout__form').length > 0){
                			var paymentForm = document.getElementsByClassName('form.wc-block-checkout__form')[0];
                		} else {
                			var paymentForm = document.getElementsByClassName('woocommerce-checkout')[1];
                		}
					
					const achOptions = getACHOptions(paymentForm);
					await handleACHPaymentMethodSubmission(event, ach, achOptions,payments);
				});
			}
			
		// }, 1000);
		
		return ach;
	}
	
	// Call this function to send a payment token, buyer name, and other details
    // to the project server code so that a payment can be created with 
    // Payments API
	
	/* async function createPayment(token) {
		if ( document.getElementsByClassName('woocommerce-error')){
			jQuery('#ach-button').prop('disabled', false);		
		} 
			console.log('NONCE: ' + token);
			document.getElementById('card_nonce').value = token;
		jQuery('form.woocommerce-checkout').submit();
		
    }
	 */
	// This function tokenizes a payment method. 
    // The ‘error’ thrown from this async function denotes a failed tokenization,
    // which is due to buyer error (such as an expired card). It is up to the
    // developer to handle the error and provide the buyer the chance to fix
    // their mistakes.
   async function ACH_tokenize(paymentMethod, options = {}, payments) {
        const tokenResult = await paymentMethod.tokenize(options);
        if(tokenResult != undefined){
            if (tokenResult.status === 'OK') {
			    return tokenResult.token;
    		} else {
    			jQuery('#ach-button').prop('disabled', false);
    			let errorMessage = tokenResult.status;
    			if (tokenResult.errors) {
    				errorMessage += tokenResult.errors;
    			}
    			throw new Error(errorMessage);
    		}
        } else{
            jQuery('.ach-button-div').html('');
            let ach;
            ach = initializeACH(payments);
        }
    }
	
	  function getBillingContact(form) {
        const formData = new FormData(form);
        // It is expected that the developer performs form field validation
        // which does not occur in this example.
		if(jQuery('form.wc-block-checkout__form').length > 0){
			return {
			  givenName: jQuery('form.wc-block-checkout__form')[0][3].value,
			  familyName: jQuery('form.wc-block-checkout__form')[0][4].value,
			};
		} else {
			return {
			  givenName: formData.get('billing_first_name'),
			  familyName: formData.get('billing_last_name'),
			};
		}
      }

	function getACHOptions(form) {
		const billingContact = getBillingContact(form);
		console.log(billingContact);
		const accountHolderName = `${billingContact.givenName} ${billingContact.familyName}`;
		if(jQuery('form.wc-block-checkout__form').length > 0){
			var id_of_div = jQuery('.wc-block-components-totals-item__value').html();
			var total = id_of_div.split(square_ach_params.currency_symbl)[1];
			var total_price = parseFloat(total) * 100;
			// var total = total.substring(1, total.length);
			// var total_price = total.toString();
		}else{
			var id_of_div = jQuery('div#order_review tr.order-total span.woocommerce-Price-amount bdi').html();
			var total = id_of_div.split("span")[2];
			var total = total.substring(1, total.length);
			var total_price = total.toString();
			var total_price = parseFloat(total_price) * 100;
		}
		var total_price = total_price.replace(",", ""); 
		return { 
			accountHolderName,
			intent: 'CHARGE',
			total: {
			  amount: total_price,
			  currencyCode: square_ach_params.currency_code
			},
		};
    }
	
	function displayPaymentResults(status) {
     const statusContainer = document.getElementById(
        'payment-status-container'
      );
      if (status === 'SUCCESS') {
        statusContainer.classList.remove('is-failure');
        statusContainer.classList.add('is-success');
      } else {
        statusContainer.classList.remove('is-success');
        statusContainer.classList.add('is-failure');
      }

      statusContainer.style.visibility = 'visible';
    }
    
	
	// document.addEventListener('DOMContentLoaded', async function () {
	jQuery(window).on("load", function() {
    if (!window.Square) {
        throw new Error('Square.js failed to load properly');
    }

    let payments;
    try {
        payments = window.Square.payments(appId, locationId);
    } catch {
        const statusContainer = document.getElementById('payment-status-container');
        statusContainer.className = 'missing-credentials';
        statusContainer.style.visibility = 'visible';
        return;
    }

    let ach;
    let isACHInitialized = false;

    function initializeACHWrapper() {
        if (isACHInitialized) {
            console.log('ACH already initialized.');
            return;
        }
        if (jQuery('.ach-button-div').length > 1) {
            jQuery('.ach-button-div').html('');
        }
        if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_ach_payment' + square_ach_params.sandbox ||
            jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_ach_payment' + square_ach_params.sandbox) {
            try {
                console.log('Initializing ACH...');
                jQuery('#ach-initialization').show();
                ach = initializeACH(payments);
                isACHInitialized = true;
                console.log('ACH initialized successfully.');
            } catch (e) {
                jQuery('#ach-initialization').hide();
                console.error('Initializing ACH failed', e);
            }
        }
    }

    setTimeout(() => {
        initializeACHWrapper();
    }, 500);

    $('form.checkout').on('change', '.woocommerce-checkout-payment input', function() {
        
        initializeACHWrapper();
    });

    jQuery('form.wc-block-checkout__form').on('change', "input[name=radio-control-wc-payment-method-options]", function() {
        setTimeout(() => {
            isACHInitialized = false;
            initializeACHWrapper();
        }, 500);
    });
});


}( jQuery ) );


