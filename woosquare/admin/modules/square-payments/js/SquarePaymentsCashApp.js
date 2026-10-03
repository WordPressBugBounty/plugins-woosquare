(function ( $ ) {
	'use strict';

	const cashapp_appId = square_cashapp_params.application_id;
	const cashapp_location_id = square_cashapp_params.lid;
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
			const instance = window.Square.payments(cashapp_appId, cashapp_location_id);
			window.woosquare_payments_instance = instance;
			return instance;
		} catch (e) {
			console.error('Failed to create Square payments instance:', e);
			return null;
		}
	}

	function buildPaymentRequest(payments) {
		const total_price = Number(square_cashapp_params.order_total).toFixed(2);
		
		const req = payments.paymentRequest({
			countryCode: square_cashapp_params.country_code.toUpperCase(),
			currencyCode: square_cashapp_params.currency_code.toUpperCase(),
			total: {
				amount: total_price,
				label: square_cashapp_params.merchant_name || 'Total',
			},
		});
		return req;
	}

	let cashAppPay;
	
	async function initializeCashApp(payments) {
		if (cashAppPay && typeof cashAppPay.destroy === 'function') {
			try {
				await cashAppPay.destroy();
			} catch (e) {
				console.warn('Error destroying cashAppPay:', e);
			}
		}
		
		const paymentRequest = buildPaymentRequest(payments);
		try {
			const options = {
				redirectURL: square_cashapp_params.checkout_url,
			};
			
			cashAppPay = await payments.cashAppPay(paymentRequest, options);
			
			await cashAppPay.attach('#cash-app-pay');
			jQuery('#cashapp-initialization').hide();

			cashAppPay.addEventListener('ontokenization', function (event) {
				const { tokenResult, error } = event.detail;
				if (tokenResult.status === 'OK') {
					var $form = jQuery('form.woocommerce-checkout, form.wc-block-checkout__form, form#order_review');
					$form.find('.square-nonce').remove();
					$form.append('<input type="hidden" class="square-nonce" name="square_nonce" value="' + tokenResult.token + '" />');
					if(jQuery('form.wc-block-checkout__form').length > 0){
						if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_cash_app_pay'+square_cashapp_params.sandbox){
							jQuery(".wc-block-components-checkout-place-order-button").trigger("click");
						}
					}else{
						$form.submit();
					}
				}
			});
		} catch (e) {
			console.error('Cash App Pay initialization failed:', e);
			jQuery('#cashapp-initialization').html('Cash App Pay unavailable. ' + (e.message || '')).show();
		}
		
		return cashAppPay;
	}

	jQuery( window  ).on("load", async function() {
		const payments = await getSharedPayments();
		if (!payments) return;
		
		let isInitializing = false;
		async function safeInit() {
			if (isInitializing) return;
			
			const isCashAppSelected = 
				jQuery('.woocommerce-checkout-payment .input-radio:checked').val() === 'square_cash_app_pay' + square_cashapp_params.sandbox ||
				jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() === 'square_cash_app_pay' + square_cashapp_params.sandbox;
			
			if (isCashAppSelected && jQuery('#cash-app-pay').length > 0) {
				isInitializing = true;
				try {
					jQuery('#cashapp-initialization').show();
					await initializeCashApp(payments);
				} finally {
					isInitializing = false;
				}
			}
		}

		jQuery( document.body ).on( 'updated_checkout', function() {
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
			if (checkCount > 10 || (cashAppPay && jQuery('#cash-app-pay iframe').length > 0)) {
				clearInterval(initPoll);
			}
		}, 500);
	});
}( jQuery ) );


