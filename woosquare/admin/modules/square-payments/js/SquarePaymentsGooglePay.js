(function($) {
    'use strict';

    const gpay_appId = squaregpay_params.application_id;
    const gpay_location_id = squaregpay_params.lid;


    function buildPaymentRequest(payments) {
        if (jQuery('form.wc-block-checkout__form').length > 0) {
            // Block-based checkout
            var id_of_div = jQuery('.wc-block-components-totals-footer-item-tax-value').html();
            var total_price = id_of_div.split(squaregpay_params.currency_sym)[1];
            total_price = total_price.replace(" ", "");

        } else if (jQuery('form.checkout.woocommerce-checkout').length > 0) {
            // Classic checkout page
            var id_of_div = jQuery('div#order_review tr.order-total span.woocommerce-Price-amount bdi').html();
            var total = id_of_div.split("span")[2];
            total = total.substring(1, total.length);
            var total_price = total.toString();

        } else if (jQuery('body').hasClass('single-product') || jQuery('body').hasClass('woocommerce-cart')) {
            // Single product page
            var total_price;

            if (jQuery('body').hasClass('single-product')) {
                // Check if it's a variable product by looking for variation forms
                if (jQuery('.variations_form').length > 0) {
                    // Handle variable product: listen for variation changes and update price
                    // Get the variation price (inside <ins> tag for sale price, or <span> for regular price)
                    var variation_price_element = jQuery('.woocommerce-variation-price ins .woocommerce-Price-amount bdi');
                    if (variation_price_element.length > 0) {
                        // Sale price is available
                        total_price = parseFloat(variation_price_element.first().text().replace(squaregpay_params.currency_sym, '').replace(/[^\d.-]/g, '')).toFixed(2);
                    } else {
                        // Regular price
                        total_price = parseFloat(jQuery('.woocommerce-variation-price .woocommerce-Price-amount bdi').first().text().replace(squaregpay_params.currency_sym, '').replace(/[^\d.-]/g, '')).toFixed(2);
                    }

                    // Multiply by quantity
                    total_price = (total_price * jQuery('.qty').val()).toFixed(2);
                    console.log('Variable product selected. Total price: ' + total_price);


                } else {
                    // Simple product page
                    total_price = (squaregpay_params.get_price * jQuery('.qty').val()).toFixed(2);
                    console.log('Simple product. Total price: ' + total_price);
                }
            } else if (jQuery('body').hasClass('woocommerce-cart')) {
                // Cart page - Extract total price from the provided HTML structure
                var cart_total_element = jQuery('.wc-block-components-totals-footer-item-tax-value').first();

                if (cart_total_element.length > 0) {
                    // Extract the total price and clean up any unwanted characters
                    total_price = parseFloat(cart_total_element.text().replace(squaregpay_params.currency_sym, '').replace(/[^\d.-]/g, '')).toFixed(2);
                    console.log('Cart page. Total price: ' + total_price);
                } else {
                    console.error('Total price element not found on the cart page.');
                }
            }
        }
        showLoader();
		var total_price = total_price.replace(",", ""); 
        console.log('gpayyytotal_price');
        console.log(total_price);
        return payments.paymentRequest({
            countryCode: squaregpay_params.country_code,
            currencyCode: squaregpay_params.currency_code,
            total: {
                amount: total_price,
                label: 'Total',
            },
        });
    }

    let googlePay;

    async function initializeGooglePay(payments) {

        // if(jQuery('#google-pay-button').html().length > 1){
        //    googlePay.destroy();
        // }
        const paymentRequest = buildPaymentRequest(payments);
        googlePay = await payments.googlePay(paymentRequest);

        setTimeout(
            function() {

                //googlePay.attach('#google-pay-button');
                
                googlePay.attach('#google-pay-button', {
                  buttonColor: squaregpay_params.ewallet_button_color,
                  //buttonSizeMode: 'static',
                  //buttonType: 'long'
                });
                
                
                
                jQuery('.qty').prop('disabled', false);
                jQuery('#googlepay-initialization').hide();
                const googlePayButton = document.getElementById('google-pay-button');
                hideLoader();

                function handlePaymentMethodSubmissiongpay(event, paymentMethod, shouldVerify = false, payments) {
                    event.preventDefault();
                    try {
                        // disable the submit button as we await tokenization and make a
                        // payment request.
                        // cardButton.disabled = true;
                        jQuery('.woocommerce-error').remove();
                        const token = tokenize(paymentMethod, payments);
                    } catch (e) {
                        // cardButton.disabled = false;
                        console.error(e.message);
                    }
                }

                googlePayButton.addEventListener(
                    'click', async function(event) {
                        if (!jQuery('.square-nonce').val()) {
                            event.stopPropagation();
                            handlePaymentMethodSubmissiongpay(event, googlePay);
                        }
                    }
                )
                if (jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_google_pay' + squaregpay_params.sandbox) {

                    googlePayButton.addEventListener(
                        'click', async function(event) {
                            handlePaymentMethodSubmissiongpay(event, googlePay);
                        }
                    )
                }

            }, 600
        );
        hideLoader();
    }

    function showLoader() {
        console.log('showLoadershowLoader');
        $('body').block({
            message: null, // Use default spinner
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
    }

    // Function to hide loader
    function hideLoader() {
        console.log('hideLoaderhideLoader');
        $('body').unblock(); // Unblock the loader
    }

    function express_checkout_init(payments) {

        
        
        if (jQuery('#google-pay-button').html().length > 1) {
            googlePay.destroy();
        }
        $('#google-pay-button').remove();
        $('.single_add_to_cart_button').after('<div id="google-pay-button"></div>');
        $('.wc-block-cart__payment-options.wp-block-woocommerce-cart-express-payment-block').after('<div id="google-pay-button"></div>');

        setTimeout(function() {
            console.log('Quantity message detected:', this.textContent);
            initializeGooglePay(payments);

        }, 1500);


        hideLoader();

    }

    async function tokenize(paymentMethod) {

        const tokenResult = await
        paymentMethod.tokenize();
        console.log(tokenResult);
        if (tokenResult.status === 'OK') {
            showLoader();
            // Check if we are on the single product page or cart page
            if ($('body').hasClass('single-product') || $('body').hasClass('woocommerce-cart')) {
                var product_id = jQuery('.single_add_to_cart_button').val(); // Get product ID
                var variation_id = jQuery('input[name="variation_id"]').val(); // Get the selected variation ID
                var quantity = jQuery('.qty').val() || 1; // Get quantity or default to 1

                // Ensure variation_id is available (for variable products)
                if (variation_id) {
                    var product_id = jQuery('input[name="product_id"]').val(); // Get the main product ID
                }

                // Make the AJAX request to create the order and process payment
                jQuery.ajax({
                    url: '/wp-admin/admin-ajax.php',
                    method: 'POST',
                    data: {
                        action: 'create_order_and_process_payment',
                        square_nonce: tokenResult.token, // Square token
                        product_id: product_id,
                        variation_id: variation_id, // Pass the variation ID
                        quantity: quantity,
                        _payid: 'square_google_pay'+squaregpay_params.sandbox, 
                        square_pay_nonce: squaregpay_params.square_pay_nonce, // Pass the security nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            hideLoader();
                            // Correct way to access the redirect_url
                            window.location.href = response.data.redirect_url; // Redirect on success
                        } else {
                            hideLoader();
                            console.error(response.error);
                        }
                    },
                    error: function(err) {
                        hideLoader();
                        console.error('AJAX error:', err);
                    }
                });
            } else {
                var $form = jQuery('form.woocommerce-checkout, form.wc-block-checkout__form, form#order_review');
                // inject nonce to a hidden field to be submitted
                /*$form.append( '<input type="hidden" class="errors" name="errors" value="' + errors + '" />' );
                $form.append( '<input type="hidden" class="noncedatatype" name="noncedatatype" value="' + noncedatatype + '" />' );
                $form.append( '<input type="hidden" class="cardData" name="cardData" value="' + cardData + '" />' );
                */
                $form.append('<input type="hidden" class="square-nonce" name="square_nonce" value="' + tokenResult.token + '" />');
                if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_google_pay' + squaregpay_params.sandbox) {
                    // pay_form.submit();
                    jQuery(".wc-block-components-checkout-place-order-button").trigger("click");
                } else {
                    $form.submit();
                    
                }
                hideLoader();
            }
            
            // $form.submit();

            /*cardButton.disabled = true;

            const paymentResults = await createPayment(token);
            displayPaymentResults('SUCCESS');*/

            // console.debug('Payment Success', paymentResults);

            // return tokenResult.token;
        } else {
            let errorMessage = tokenResult.status;
            if (tokenResult.errors) {
                errorMessage += tokenResult.errors;
            }
            throw new Error(errorMessage);
        }
    }

    function initgp(googlePay, payments) {
        try {
            googlePay = initializeGooglePay(payments);
        } catch (e) {
            console.error('Initializing Google Pay failed', e);
            return;
        }
    }
    jQuery(window).on(
        "load",
        function() {
            if (!window.Square) {
                throw new Error('Square.js failed to load properly');
            }

            const payments = window.Square.payments(gpay_appId, gpay_location_id);
            // let googlePay;
            setTimeout(
                function() {
                    if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_google_pay' + squaregpay_params.sandbox) {
                        if (jQuery('#google-pay-button').html().length > 1) {
                            googlePay.destroy();
                        }
                        try {
                            if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_google_pay' + squaregpay_params.sandbox) {
                                jQuery('#googlepay-initialization').show();
                                googlePay = initializeGooglePay(payments);
                            }
                        } catch (e) {
                            console.error('Initializing Google Pay failed', e);
                            return;
                        }
                    }


                    // Check if we are on the single product page
                if (squaregpay_params.express_checkout_enabled == "yes") {
                    if ($('body').hasClass('single-product') || $('body').hasClass('woocommerce-cart')) {
                        // Add Google Pay button to single product page, for example below "Add to Cart" button
                        if (!$('#google-pay-button').length) {
                            $('.single_add_to_cart_button').after('<div id="google-pay-button"></div>');
                        }

                        // Call the initializeGooglePay function with appropriate parameters (e.g., payments object)
                        initializeGooglePay(payments);


                        // Flag to prevent multiple executions of the same event
                        let isEventTriggered = false;

                        // Function to handle the quantity change
                        function handleQuantityChange() {
                            var oldValue = $(this).data('oldValuecart');
                            var newValue = $(this).val();

                            // Only execute if the new value differs from the old value
                            if (newValue !== oldValue && !isEventTriggered) {
                                isEventTriggered = true; // Set the flag to true to prevent further executions

                                console.log('Quantity changed: ' + newValue);

                                // Clear any previously set timeouts to avoid multiple inits
                                clearTimeout(window.expressCheckoutTimeout);

                                // Reinitialize the Google Pay button after a delay (500ms)
                                window.expressCheckoutTimeout = setTimeout(function() {
                                    console.log('express_checkout_init');
                                    showLoader();
                                    express_checkout_init(payments);

                                    isEventTriggered = false; // Reset the flag after the event is processed
                                }, 800);
                            }
                        }

                        // Store the old value when the input is focused
                        $('body').on('focus', '.wc-block-components-quantity-selector__input', function() {
                            $(this).data('oldValuecart', $(this).val());
                        });

                        // Set up a MutationObserver to watch for changes in the DOM
                        let observerTimeout = null;
                        var observer = new MutationObserver(function(mutations) {
                            mutations.forEach(function(mutation) {
                                // Clear previous observer timeout if any
                                clearTimeout(observerTimeout);

                                observerTimeout = setTimeout(function() {
                                    if (mutation.addedNodes.length) {
                                        $(mutation.addedNodes).each(function() {
                                            if (typeof this.textContent === 'string' && this.textContent.match(/Quantity/)) {
                                                setTimeout(function() {
                                                    console.log('express_checkout_init 2');
                                                    showLoader();
                                                    express_checkout_init(payments);
                                                }, 1000); // Shortened timeout for express checkout

                                                // Reattach the event listener to the input field, only if not already attached
                                                $('.wc-block-components-quantity-selector__input').each(function() {
                                                    if (!$(this).data('event-attached')) {
                                                        $(this).data('event-attached', true);
                                                        $(this).off('input change').on('input change', handleQuantityChange);
                                                    }
                                                });
                                            }
                                        });
                                    }
                                }, 300); // Debounced the mutation to 300ms
                            });
                        });

                        // Start observing changes to the body
                        observer.observe(document.body, {
                            childList: true,
                            subtree: true
                        });

                        // Initial binding for quantity input (in case the element is already present)
                        $('.wc-block-components-quantity-selector__input').on('input change', function(event) {
                            // Ensure the event is triggered only if the user is interacting with the quantity input
                            var $input = $(event.target);
                            var oldValue = $input.data('oldValuecart');
                            var newValue = $input.val();

                            // Only trigger the event if the value has actually changed
                            if (newValue !== oldValue) {
                                handleQuantityChange.call(this);
                            }
                        });

                        // To ensure we're catching updates even if the input is added later
                        setInterval(function() {
                            $('.wc-block-components-quantity-selector__input').each(function() {
                                if (!$(this).data('event-attached')) {
                                    $(this).data('event-attached', true);
                                    console.log('Event attached via fallback check');
                                    $(this).on('input change', function(event) {
                                        var $input = $(event.target);
                                        var oldValue = $input.data('oldValuecart');
                                        var newValue = $input.val();

                                        // Only trigger the event if the value has actually changed
                                        if (newValue !== oldValue) {
                                            handleQuantityChange.call(this);
                                        }
                                    });
                                }
                            });
                        }, 2000); // Checks every 2 seconds (reduced from 4 seconds)




                        $('body').on('focus', '.qty', function() {
                            $(this).data('oldValue', $(this).val());
                        });

                        jQuery('.qty').prop('disabled', true);
                        $('body').on('keyup paste input', '.qty', function() {
                            console.log('Quantity changed: ' + $(this).val());
                            var oldValue = $(this).data('oldValue');
                            var newValue = $(this).val();
                            if (newValue !== oldValue) {
                                // Clear any previously set timeouts to avoid multiple inits
                                clearTimeout(window.expressCheckoutTimeout);

                                // Reinitialize the Google Pay button after a delay (500ms)
                                window.expressCheckoutTimeout = setTimeout(function() {
                                    console.log('express_checkout_init');
                                    showLoader();
                                    express_checkout_init(payments);
                                }, 500);
                            }
                        });

                        // Reinitialize Google Pay when a variation is selected/changed
                        $('form.variations_form').on('woocommerce_variation_has_changed', function() {
                            setTimeout(
                                function() {
                                    express_checkout_init(payments);
                                }, 500);
                        });
                    }
                }




                }, 500
            );
            if (jQuery('.payment_method_square_ach_payment' + squaregpay_params.sandbox).length == 0) {
                jQuery(document.body).on(
                    'updated_checkout',
                    function() {
                        if (jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_google_pay' + squaregpay_params.sandbox) {
                            if (jQuery('#google-pay-button').html().length > 1) {
                                googlePay.destroy();
                            }
                            try {
                                if (jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_google_pay' + squaregpay_params.sandbox) {
                                    jQuery('#googlepay-initialization').show();
                                    googlePay = initializeGooglePay(payments);
                                }
                            } catch (e) {
                                console.error('Initializing Google Pay failed', e);
                                return;
                            }
                        }
                    }
                )
            }
            jQuery(document).on(
                'change', 'body.woocommerce-checkout',
                function() {
                    setTimeout(
                        () => {
                            if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_google_pay' + squaregpay_params.sandbox) {
                                setTimeout(
                                    function() {
                                        try {
                                            if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_google_pay' + squaregpay_params.sandbox) {
                                                jQuery('#googlepay-initialization').show();
                                                googlePay = initializeGooglePay(payments);
                                            }
                                        } catch (e) {
                                            console.error('Initializing Google Pay failed', e);
                                            return;
                                        }
                                    }, 500
                                );
                            }
                        }, 1000
                    );
                }
            )
            jQuery('form.wc-block-checkout__form').on(
                'change', "input[name=radio-control-wc-payment-method-options]",
                function() {
                    if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_google_pay' + squaregpay_params.sandbox) {
                        setTimeout(
                            function() {
                                try {
                                    if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_google_pay' + squaregpay_params.sandbox) {
                                        jQuery('#googlepay-initialization').show();
                                        googlePay = initializeGooglePay(payments);
                                    }
                                } catch (e) {
                                    console.error('Initializing Google Pay failed', e);
                                    return;
                                }
                            }, 500
                        );
                    }
                }
            );
            $('form.checkout').on(
                'change', '.woocommerce-checkout-payment input',
                function() {
                    if (jQuery('#google-pay-button').html().length > 1) {
                        googlePay.destroy();
                    }
                    if (jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_google_pay' + squaregpay_params.sandbox) {
                        try {
                            if (jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_google_pay' + squaregpay_params.sandbox) {
                                jQuery('#googlepay-initialization').show();
                                googlePay = initializeGooglePay(payments);
                            }
                        } catch (e) {
                            console.error('Initializing Google Pay failed', e);
                            return;
                        }
                    }
                }
            );



        }
    );


}(jQuery));