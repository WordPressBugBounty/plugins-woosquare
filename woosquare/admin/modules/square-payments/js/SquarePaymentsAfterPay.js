(function ( $ ) {
	'use strict';

	const afterpay_appId = square_afterpay_params.application_id;
	const afterpay_location_id = square_afterpay_params.lid;
	
	// Helper to get or create a shared payments instance
	async function getSharedPayments() {
		if (window.woosquare_payments_instance) {
			return window.woosquare_payments_instance;
		}
		
		if (!window.Square) {
			console.error('Square.js not loaded');
			return null;
		}
		
		try {
			const instance = window.Square.payments(afterpay_appId, afterpay_location_id);
			window.woosquare_payments_instance = instance;
			return instance;
		} catch (e) {
			console.error('Failed to create Square payments instance:', e);
			return null;
		}
	}

	function buildPaymentRequest(payments) {
		const total_price = Number(square_afterpay_params.order_total).toFixed(2);

		const req = payments.paymentRequest({
			countryCode: square_afterpay_params.country_code.toUpperCase(),
			currencyCode: square_afterpay_params.currency_code.toUpperCase(),
			total: {
				amount: total_price,
				label: square_afterpay_params.merchant_name || 'Total',
			},
			requestShippingContact: true,
		});

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

		return req;
	}

	let afterpay;

	async function initializeAfterpay(payments) {
		if (afterpay && typeof afterpay.destroy === 'function') {
			try {
				await afterpay.destroy();
			} catch (e) {
				console.warn('Error destroying afterpay:', e);
			}
		}

		const paymentRequest = buildPaymentRequest(payments);
		
		try {
			afterpay = await payments.afterpayClearpay(paymentRequest);
			await afterpay.attach('#afterpay-button');
			jQuery('#afterpay-initialization').hide();

			const afterpayButton = document.getElementById('afterpay-button');
			if (afterpayButton) {
				afterpayButton.addEventListener('click', async function (event) {
					event.preventDefault();
					try {
						jQuery('.woocommerce-error').remove();
						await tokenize(afterpay);
					} catch (e) {
						console.error('Afterpay tokenization failed:', e);
					}
				});
			}
		} catch (error) {
			console.error('After Pay initialization error:', error);
			jQuery('#afterpay-initialization').html('After Pay unavailable. ' + (error.message || '')).show();
			afterpay = null;
		}
		
		return afterpay;
	}
	
	async function tokenize(paymentMethod) {
		const tokenResult = await paymentMethod.tokenize();
		if (tokenResult.status === 'OK') {
			var $form = jQuery('form.woocommerce-checkout, form.wc-block-checkout__form, form#order_review');
			$form.find('.square-nonce').remove();
			$form.append('<input type="hidden" class="square-nonce" name="square_nonce" value="' + tokenResult.token + '" />');

			if( jQuery('form.wc-block-checkout__form').length > 0 ){
				if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_after_pay'+square_afterpay_params.sandbox){
					jQuery(".wc-block-components-checkout-place-order-button").trigger("click");
				}
			}else{
				$form.submit();
			}
		} else {
			let errorMessage = tokenResult.status;
			if (tokenResult.errors) {
				errorMessage += JSON.stringify(tokenResult.errors);
			}
			throw new Error(errorMessage);
		}
	}

	jQuery( window ).on("load", async function() {
		const payments = await getSharedPayments();
		if (!payments) return;
		
		let isInitializing = false;
		async function safeInit() {
			if (isInitializing) return;
			
			const isAfterpaySelected = 
				jQuery('.woocommerce-checkout-payment .input-radio:checked').val() === 'square_after_pay' + square_afterpay_params.sandbox ||
				jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() === 'square_after_pay' + square_afterpay_params.sandbox;
			
			if (isAfterpaySelected && jQuery('#afterpay-button').length > 0) {
				isInitializing = true;
				try {
					jQuery('#afterpay-initialization').show().html('Initializing...');
					await initializeAfterpay(payments);
				} finally {
					isInitializing = false;
				}
			}
		}

		jQuery(document.body).on('updated_checkout', function() {
			safeInit();
		});

		jQuery(document).on('change', 'form.checkout, form.wc-block-checkout__form', function() {
			setTimeout(safeInit, 500);
		});

		// Robust initial check for reloads (especially for Blocks Checkout)
		let checkCount = 0;
		const initPoll = setInterval(function() {
			checkCount++;
			safeInit();
			// Stop polling after 10 attempts (5 seconds) or if already initialized
			if (checkCount > 10 || (afterpay && jQuery('#afterpay-button iframe').length > 0)) {
				clearInterval(initPoll);
			}
		}, 500);
	});
}( jQuery ) );


