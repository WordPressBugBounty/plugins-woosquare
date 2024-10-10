(function ( $ ) {
	'use strict';

	const cashapp_appId = square_cashapp_params.application_id;
	const cashapp_locationId = square_cashapp_params.lid;
	
	function buildPaymentRequest(payments) {

		if(jQuery('form.wc-block-checkout__form').length > 0){
			var id_of_div = jQuery('.wc-block-components-totals-item__value').html();
			var total_price = id_of_div.split(square_cashapp_params.currency_symbl)[1];
			// var total = total.substring(1, total.length);
			// var total_price = total.toString();
		}else{
			var id_of_div = jQuery('div#order_review tr.order-total span.woocommerce-Price-amount bdi').html();
			var total = id_of_div.split("span")[2];
			var total = total.substring(1, total.length);
			var total_price = total.toString();
		}
		const req = payments.paymentRequest({
			countryCode: square_cashapp_params.country_code,
			currencyCode: square_cashapp_params.currency_code,
			total: {
			amount: total_price,
			label: 'Total',
			},
		});
		return req;

	}

	async function tokenize(paymentMethod) {

		const tokenResult = await
		paymentMethod.tokenize();
		if (tokenResult.status === 'OK') {
			return tokenResult.token;
		} else {
			let errorMessage = tokenResult.status;
			if (tokenResult.errors) {
				errorMessage += tokenResult.errors;
			}
			throw new Error(errorMessage);
		}

	}

	let cashAppPay;
	async function initializeCashApp(payments) {
		
		if(cashAppPay != undefined){
			cashAppPay.destroy();
		}

		const paymentRequest = buildPaymentRequest(payments);
		const buttonOptions = {
			shape: 'semiround',
			width: 'full',
		};
		cashAppPay = await payments.cashAppPay(paymentRequest,{
			redirectURL: square_cashapp_params.checkout_url,
		});
		
		setTimeout(function(){
			jQuery('#rendering_cashapp_gateway').hide();
			cashAppPay.attach('#cash-app-pay', buttonOptions);
			
			jQuery('#cashapp-initialization').hide();
			cashAppPay.addEventListener('ontokenization', function (event) {
				const { tokenResult, error } = event.detail;
				if (error) {
					// developer handles error
					//console.log('error' + error);
				}
				else if (tokenResult.status === 'OK') {
					// developer passes token to backend for use with CreatePayment
					var $form = jQuery('form.woocommerce-checkout, form.wc-block-checkout__form, form#order_review');
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
		}, 1000);
		// return cashAppPay;

	}
/* 
	function init_cashapp(cashAppPay,payments){
		
		try {
			cashAppPay = initializeCashApp(payments);
		} catch (e) {
			console.error('Initializing Cash App Pay failed', e);
		}

	}
 */
	// document.addEventListener('DOMContentLoaded', async function () {
	jQuery( window  ).on("load", function() {

		if (!window.Square) {
			throw new Error('Square.js failed to load properly');
		}

		const payments = window.Square.payments(cashapp_appId, cashapp_locationId);
		if(jQuery('.payment_method_square_ach_payment'+square_cashapp_params.sandbox ).length == 0){ 
			jQuery( document.body ).on( 'updated_checkout', function() {
				// jQuery('input[type=radio][name=payment_method]').on('change', function(){
					console.log('dsadad');
					if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_cash_app_pay'+square_cashapp_params.sandbox ){
					try {
						if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_cash_app_pay'+square_cashapp_params.sandbox ){
							
								jQuery('#cashapp-initialization').show();
							cashAppPay = initializeCashApp(payments);
						}
					} catch (e) {
				jQuery('#cashapp-initialization').hide();
						console.error('Initializing Cash App Pay failed', e);
					}
					if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_cash_app_pay'+square_cashapp_params.sandbox ){
						if(jQuery('.woocommerce-error').length > 0 && jQuery('.cashapp-square-nonce').val()){ 
							jQuery('#cash-app-pay').html('CashApp payment already generated');
							var $form = jQuery('form.woocommerce-checkout, form.wc-block-checkout__form, form#order_review');
							// $form.append('<input type="hidden" class="square-nonce" name="square_nonce" value="' + tokenResult.token + '" />');
							if(jQuery('form.wc-block-checkout__form').length > 0){
								if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_cash_app_pay'+square_cashapp_params.sandbox){
									jQuery(".wc-block-components-checkout-place-order-button").trigger("click");
								}
							}else{
								$form.submit();
							}
						} 
					}

					/* init_cashapp(cashAppPay,payments);
					jQuery('input[type=radio][name=payment_method]').change(function() {
						// jQuery('body').trigger('update_checkout');
						// console.log(jQuery('#cash-app-pay').html().length);
						// console.log('CASHAPP' +jQuery("input[name='payment_method'][value='square_cash_app_pay']").prop("checked"));
						if(jQuery("input[name='payment_method'][value='square_cash_app_pay']").prop("checked")){
							init_cashapp(cashAppPay,payments);
						}
					}); */
					}
				// })
			})
		}
		if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_cash_app_pay'+square_cashapp_params.sandbox ){
			try {
				if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_cash_app_pay'+square_cashapp_params.sandbox ){
					
						jQuery('#cashapp-initialization').show();
					cashAppPay = initializeCashApp(payments);
				}
			} catch (e) {
				jQuery('#cashapp-initialization').hide();
				console.error('Initializing Cash App Pay failed', e);
			}
			if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_cash_app_pay'+square_cashapp_params.sandbox ){
				if(jQuery('.woocommerce-error').length > 0 && jQuery('.cashapp-square-nonce').val()){ 
					jQuery('#cash-app-pay').html('CashApp payment already generated');
					var $form = jQuery('form.woocommerce-checkout, form#order_review');
					// $form.append('<input type="hidden" class="square-nonce" name="square_nonce" value="' + tokenResult.token + '" />');
					$form.submit();
				} 
			}

		}
		$('form.checkout').on('change', '.woocommerce-checkout-payment input', function(){
			if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_cash_app_pay'+square_cashapp_params.sandbox ){
				try {
					if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_cash_app_pay'+square_cashapp_params.sandbox ){
						jQuery('#cashapp-initialization').show();
						cashAppPay = initializeCashApp(payments);
					}
				} catch (e) {
					jQuery('#cashapp-initialization').hide();
					console.error('Initializing Cash App Pay failed', e);
				}
				if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_cash_app_pay'+square_cashapp_params.sandbox ){
					if(jQuery('.woocommerce-error').length > 0 && jQuery('.cashapp-square-nonce').val()){ 
						jQuery('#cash-app-pay').html('CashApp payment already generated');
						var $form = jQuery('form.woocommerce-checkout, form#order_review');
						// $form.append('<input type="hidden" class="square-nonce" name="square_nonce" value="' + tokenResult.token + '" />');
						$form.submit();
					} 
				}

			}
		});
		jQuery('form.wc-block-checkout__form').on('change', "input[name=radio-control-wc-payment-method-options]", function(){
			if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_cash_app_pay'+square_cashapp_params.sandbox ){
				try {
					if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_cash_app_pay'+square_cashapp_params.sandbox ){
						jQuery('#cashapp-initialization').show();
						cashAppPay = initializeCashApp(payments);
					}
				} catch (e) {
					jQuery('#cashapp-initialization').hide();
					console.error('Initializing Cash App Pay failed', e);
				}
				if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_cash_app_pay'+square_cashapp_params.sandbox ){
					if(jQuery('.woocommerce-error').length > 0 && jQuery('.cashapp-square-nonce').val()){ 
						jQuery('#cash-app-pay').html('CashApp payment already generated');
						var $form = jQuery('form.woocommerce-checkout, form.wc-block-checkout__form, form#order_review');
						// $form.append('<input type="hidden" class="square-nonce" name="square_nonce" value="' + tokenResult.token + '" />');
						if(jQuery('form.wc-block-checkout__form').length > 0){
							if(jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_cash_app_pay'+square_cashapp_params.sandbox){
								jQuery(".wc-block-components-checkout-place-order-button").trigger("click");
							}
						}else{
							$form.submit();
						}
					} 
				}
			}
		})
	});

}( jQuery ) );