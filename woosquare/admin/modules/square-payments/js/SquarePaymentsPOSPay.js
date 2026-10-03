jQuery(document).ready(function() {
	// jQuery('#spinner').hide();
	jQuery('#woocommerce_square_terminal_pay_generate_code').val('Click here to generate code!');
		jQuery(document).on('click', '#woocommerce_square_terminal_pay_generate_code', function(e) {
			e.preventDefault();
			jQuery('#woocommerce_square_terminal_pay_device_code').val('');
			jQuery('#woocommerce_square_terminal_pay_device_id').val('');
			var formData = {
				action: 'my_ajax_get_pos_action',
				nonce: POSTerminal.nonce,
				type: 'send',
				country_code: POSTerminal.country_code,
				currency_code: POSTerminal.currency_code,
				location_id: POSTerminal.location_id
			}
			
			jQuery.ajax({
				'url' : POSTerminal.ajax_url,
				'type' : 'POST',
				'data' : formData,
				'success' : function(data) {
					var response_json = JSON.parse( data );
					jQuery('#woocommerce_square_terminal_pay_device_code').val(function() {
						return this.value + response_json.device_code.code;
					});
					jQuery('#woocommerce_square_terminal_pay_device_id').val(function() {
						return this.value + response_json.device_code.id;
					});
				},
			})
		});
		
		function isBlockCheckout() {
			return jQuery('.wc-block-checkout').length > 0 || jQuery('form.wc-block-checkout__form').length > 0;
		}

		function isTerminalPaySelected() {
			var selected = jQuery('input[name="radio-control-wc-payment-method-options"]:checked').val()
				|| jQuery('input[name="payment_method"]:checked').val()
				|| '';
			return selected.indexOf('square_terminal_pay') === 0;
		}

		function completepym(){
			// jQuery('#spinner').hide();
			var $form = jQuery('form.woocommerce-checkout, form.wc-block-checkout__form, form#order_review');
			// WSSS-416: after terminal checkout completes, submit block/classic checkout with term_checkout_id set.
			if (isTerminalPaySelected() && isBlockCheckout()) {
				jQuery('.wc-block-components-checkout-place-order-button').trigger('click');
			} else {
				$form.submit();
			}
		}
		jQuery(document).on('click', '#terminal-pay-button', function(e) {
			e.preventDefault();
			// WSSS-416: on block checkout, run terminal flow first (do not Place Order before term_checkout_id exists).
			if (isBlockCheckout()) {
				terminal_pay_process(e);
				return;
			}
			jQuery('#place_order').click();
			jQuery(document).ajaxComplete(function(event, xhr, settings) {
				if (xhr.responseJSON && xhr.responseJSON.messages == '' && settings.url && settings.url.indexOf('wc-ajax=checkout') !== -1) {
					terminal_pay_process(e);
				}
			});
		});
		function terminal_payment_process(e){
			
		}
		function terminal_pay_process(e){
			jQuery('.woocommerce-error').remove();
			e.preventDefault();
			this.disabled = true;	
			if(jQuery('form.wc-block-checkout__form').length > 0){
				// WSSS-416: checkout localizes squaretpay_params only (POSTerminal is admin-only).
				var total_price = String(squaretpay_params.order_total || '0');
			}else{
				var id_of_div = jQuery('div#order_review tr.order-total span.woocommerce-Price-amount bdi').html();
				var total = id_of_div.split("span")[2];
				var total = total.substring(1, total.length);
				var total_price = total.toString();
			}
			var total_price = total_price.replace(",", ""); 
			const pay_form = jQuery( 'form.woocommerce-checkout, form.wc-block-checkout__form, form#order_review' );
			var formmmm = pay_form.serialize();
			jQuery('#terminal-pay-button-loader').show();
			var formData = {
				action: 'terminal_pay_process',
				nonce: squaretpay_params.nonce,
				country_code: squaretpay_params.country_code,
				currency_code: squaretpay_params.currency_code,
				location_id: squaretpay_params.location_id,
				square_pay_nonce: squaretpay_params.square_pay_nonce,
				total_price: total_price,
				pay_form: formmmm,
			}
			
			jQuery.ajax({
			'url' : squaretpay_params.ajax_url,
			'type' : 'POST',
			'data' : formData,
			'success' : function(response) {
					this.disabled = true;
					// jQuery('#terminal-pay-button').append('<div id="spinner"></div>');
					jQuery('#wooc_square_tmethod').remove();
					// jQuery('#spinner').show();
					var formData = {
						action: 'terminal_pay_process_checkout',
						square_pay_nonce: squaretpay_params.square_pay_nonce,
					}
					var  aj_status = 0;
					var refreshId = setInterval(function() {
						aj_status = aj_status+1;
						jQuery.ajax({
							'url' : squaretpay_params.ajax_url,
							'type' : 'GET',
							'data' : formData,
						'success' : function(response) {
								var response_json = JSON.parse(response);
								const cardButton = document.getElementById('place_order');
									/* cardButton.addEventListener('click', function (event) {
										alert('sssssss');
									}) */
									
								if(response_json.result_info.checkout.status == 'COMPLETED'){
									clearInterval(refreshId);
									jQuery('form.woocommerce-checkout, form.wc-block-checkout__form, form#order_review').append( '<input type="hidden" class="term_checkout_status" name="term_checkout_status" value="' + response_json.result_info.checkout.status + '" />' );
										jQuery('form.woocommerce-checkout, form.wc-block-checkout__form, form#order_review').append( '<input type="hidden" class="term_checkout_app_id" name="term_checkout_app_id" value="' + response_json.result_info.checkout.app_id + '" />' );
										jQuery('form.woocommerce-checkout, form.wc-block-checkout__form, form#order_review').append( '<input type="hidden" class="term_checkout_id" name="term_checkout_id" value="' + response_json.result_info.checkout.id + '" />' );
									jQuery('form.woocommerce-checkout, form.wc-block-checkout__form, form#order_review').append( '<input type="hidden" class="term_customer_id" name="term_customer_id" value="' + response_json.result_info.checkout.customer_id + '" />' );
								completepym(refreshId);
								}else{
									this.disabled = false;
										aj_status = 0;
									}
								},
							})
						},5000)
					
				},
			})
		}
	});
