(function ( $ ) {
	'use strict';

	const afterpay_appId = square_afterpay_params.application_id;
	const afterpay_locationId = square_afterpay_params.lid;
	
	function buildPaymentRequest(payments) {
		if(jQuery('form.wc-block-checkout__form').length > 0){
			var id_of_div = jQuery('.wc-block-components-totals-item__value').html();
			var total_price = id_of_div.split(square_afterpay_params.currency_symbl)[1];
			// var total = total.substring(1, total.length);
			// var total_price = total.toString();
		}else{
			var id_of_div = jQuery('div#order_review tr.order-total span.woocommerce-Price-amount bdi').html();
			var total = id_of_div.split("span")[2];
			var total = total.substring(1, total.length);
			var total_price = total.toString();
		}
		var total_price = total_price.replace(",", ""); 
		//console.log(total_price);
		const req = payments.paymentRequest({
			countryCode: square_afterpay_params.country_code,
			currencyCode: square_afterpay_params.currency_code,
			total: {
			amount: total_price,
			label: 'Total',
			},
			requestShippingContact: true,
		});

		// Note how afterpay has its own listeners
		req.addEventListener('afterpay_shippingaddresschanged', function (_address) {
			return {
				shippingOptions: [{
					amount: '0.00',
					id: 'shipping-option-1',
					label: 'Flat rate',
					taxLineItems: [],
					total: {
						amount: total_price,
						label: 'total',
					}
				}]
			};
		});
		req.addEventListener('afterpay_shippingoptionchanged', function (_option) {
			// This event listener is for information purposes only.
			// Changes here (or values returned) will not affect the Afterpay/Clearpay PaymentRequest.
		});

		return req;
	}

	let afterpay;

	async function initializeAfterpay(payments) {
		const paymentRequest = buildPaymentRequest(payments);
		if(jQuery('#afterpay-button').html().length > 1){
			afterpay.destroy();
		}
		afterpay = await payments.afterpayClearpay(paymentRequest);

		setTimeout(function(){ 	 
			afterpay.attach('#afterpay-button');
			jQuery('#afterpay-initialization').hide();
			jQuery('#rendering_afterpay_gateway').hide();
			const afterpayButton = document.getElementById('afterpay-button');
			
			async function handlePaymentMethodSubmission(event, paymentMethod) {
				event.preventDefault();
				try {
					// disable the submit button as we await tokenization and make a
					// payment request.
					// cardButton.disabled = true;
					jQuery('.woocommerce-error').remove();
					const token =  tokenize(paymentMethod);
				} catch (e) {
					// cardButton.disabled = false;
					console.error(e.message);
				}
			}
			
			if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_after_pay'+square_afterpay_params.sandbox
				|| jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_after_pay'+square_afterpay_params.sandbox
			){
    			afterpayButton.addEventListener('click', async function (event) {
					await handlePaymentMethodSubmission(event, afterpay);
				});
			}
			
		}, 1000);
		// return afterpay;
	}
	
	async function tokenize(paymentMethod) {

		const tokenResult = await
		paymentMethod.tokenize();
		if (tokenResult.status === 'OK') {
			
			var $form = jQuery('form.woocommerce-checkout, form.wc-block-checkout__form, form#order_review');
			$form.append('<input type="hidden" class="square-nonce" name="square_nonce" value="' + tokenResult.token + '" />');

			if(jQuery('form.wc-block-checkout__form').length > 0){
				if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_after_pay'+square_afterpay_params.sandbox){
					jQuery(".wc-block-components-checkout-place-order-button").trigger("click");
				}
			}else{
				$form.submit();
			}
			
			// console.debug('Payment Success', displayPaymentResults);
		} else {
			let errorMessage = tokenResult.status;
			if (tokenResult.errors) {
				errorMessage += tokenResult.errors;
			}
			throw new Error(errorMessage);
		}
	}

// Helper method for displaying the Payment Status on the screen.
// status is either SUCCESS or FAILURE;
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
	function init_afterpay(afterpay,payments){
		try {
			afterpay = initializeAfterpay(payments);
			// return afterpay;
		} catch (e) {
			console.error('Initializing After Pay failed', e);
			return;
		}
	}
	// document.addEventListener('DOMContentLoaded', async function () {
	jQuery( window  ).on("load", function() {
		if (!window.Square) {
			throw new Error('Square.js failed to load properly');
		}
		const payments = window.Square.payments(afterpay_appId, afterpay_locationId);

		// let afterpay;
		if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_after_pay'+square_afterpay_params.sandbox ){
			if(jQuery('#afterpay-button').html().length > 1){
				afterpay.destroy();
			}
			try {
				if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_after_pay'+square_afterpay_params.sandbox ){
					jQuery('#afterpay-initialization').show();
					afterpay = initializeAfterpay(payments);
				}
				// return afterpay;
			} catch (e) {
			jQuery('#afterpay-initialization').hide();
				console.error('Initializing After Pay failed', e);
				return;
			}
		}
		if(jQuery('.payment_method_square_ach_payment'+square_afterpay_params.sandbox ).length == 0){ 
			jQuery( document.body ).on( 'updated_checkout', function() {
				if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_after_pay'+square_afterpay_params.sandbox ){
					if(jQuery('#afterpay-button').html().length > 1){
						afterpay.destroy();
					}
					try {
						if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_after_pay'+square_afterpay_params.sandbox ){
							jQuery('#afterpay-initialization').show();
							afterpay = initializeAfterpay(payments);
						}
						// return afterpay;
					} catch (e) {
			jQuery('#afterpay-initialization').hide();
						console.error('Initializing After Pay failed', e);
						return;
					}
				}
			})
		}
		$('form.checkout').on('change', '.woocommerce-checkout-payment input', function(){
			if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_after_pay'+square_afterpay_params.sandbox ){
				if(jQuery('#afterpay-button').html().length > 1){
					afterpay.destroy();
				}
				try {
					if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_after_pay'+square_afterpay_params.sandbox ){
							jQuery('#afterpay-initialization').show();
						afterpay = initializeAfterpay(payments);
					}
					// return afterpay;
				} catch (e) {
			jQuery('#afterpay-initialization').hide();
					console.error('Initializing After Pay failed', e);
					return;
				}
				/* init_afterpay(afterpay,payments);
				jQuery('input[type=radio][name=payment_method]').change(function() {
					// console.log(jQuery("input[name='payment_method'][value='square_after_pay']").prop("checked"));
					if(jQuery("input[name='payment_method'][value='square_after_pay']").prop("checked")){
						init_afterpay(afterpay,payments);
					}
				});	 */	
			}
		});
		jQuery('form.wc-block-checkout__form').on('change', "input[name=radio-control-wc-payment-method-options]", function(){
			if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_after_pay'+square_afterpay_params.sandbox ){
				if(jQuery('#afterpay-button').html().length > 1){
					afterpay.destroy();
				}
				try {
					if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_after_pay'+square_afterpay_params.sandbox ){
							jQuery('#afterpay-initialization').show();
						afterpay = initializeAfterpay(payments);
					}
					// return afterpay;
				} catch (e) {
				jQuery('#afterpay-initialization').hide();
					console.error('Initializing After Pay failed', e);
					return;
				}
			}
		})

	});


}( jQuery ) );
