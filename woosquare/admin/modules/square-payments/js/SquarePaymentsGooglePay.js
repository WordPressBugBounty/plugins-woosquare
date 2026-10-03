(function($) {
    'use strict';

    const gpay_appId = squaregpay_params.application_id;
    const gpay_location_id = squaregpay_params.lid;


    function parseMoneyAmount(rawValue) {
        if (!rawValue) {
            return null;
        }
        var numeric = parseFloat(rawValue.toString().replace(/[^\d.-]/g, ''));
        if (!isFinite(numeric)) {
            return null;
        }
        return numeric.toFixed(2);
    }

    function getGooglePayTotalAmount() {
        var total_price = null;

        if (jQuery('form.wc-block-checkout__form').length > 0) {
            // Block-based checkout
            var blockTotalText = jQuery('.wc-block-components-totals-item__value .wc-block-formatted-money-amount, .wc-block-components-totals-footer-item-tax-value, .wc-block-formatted-money-amount').first().text();
            total_price = parseMoneyAmount(blockTotalText);
        } else if (jQuery('form.checkout.woocommerce-checkout').length > 0) {
            // Classic checkout page
            var classicTotalText = jQuery('div#order_review tr.order-total span.woocommerce-Price-amount bdi').first().text();
            total_price = parseMoneyAmount(classicTotalText);
        } else if (jQuery('body').hasClass('single-product') || jQuery('body').hasClass('woocommerce-cart')) {
            // Single product / cart
            if (jQuery('body').hasClass('single-product')) {
                if (jQuery('.variations_form').length > 0) {
                    var variation_price_element = jQuery('.woocommerce-variation-price ins .woocommerce-Price-amount bdi');
                    if (variation_price_element.length > 0) {
                        total_price = parseMoneyAmount(variation_price_element.first().text());
                    } else {
                        total_price = parseMoneyAmount(jQuery('.woocommerce-variation-price .woocommerce-Price-amount bdi').first().text());
                    }
                    if (total_price !== null) {
                        total_price = (parseFloat(total_price) * (parseFloat(jQuery('.qty').val()) || 1)).toFixed(2);
                    }
                } else {
                    total_price = ((parseFloat(squaregpay_params.get_price) || 0) * (parseFloat(jQuery('.qty').val()) || 1)).toFixed(2);
                }
            } else if (jQuery('body').hasClass('woocommerce-cart')) {
                var cart_total_element = jQuery('.wc-block-components-totals-item__value .wc-block-components-totals-footer-item-tax-value, .wc-block-components-totals-item__value .wc-block-formatted-money-amount, .wc-block-components-totals-footer-item-tax-value').first();
                if (cart_total_element.length > 0) {
                    total_price = parseMoneyAmount(cart_total_element.text());
                }
            }
        }

        return total_price;
    }

    function buildPaymentRequest(payments) {
        var total_price = getGooglePayTotalAmount();
        if (total_price === null) {
            throw new Error('Checkout total not ready for Google Pay initialization');
        }

        showLoader();
        total_price = total_price.toString().replace(/,/g, '');
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
    let googlePayInitializing = false;
    let googlePayInitPending = false;
    let googlePayAttachTimer = null;
    let googlePayBootTimer = null;
    let googlePayBootStartedAt = 0;
    const GOOGLE_PAY_CONTAINER_SELECTOR = '#google-pay-button';
    const GOOGLE_PAY_BLOCK_SELECTOR = "input[name=radio-control-wc-payment-method-options]:checked";
    const GOOGLE_PAY_CLASSIC_SELECTOR = '.woocommerce-checkout-payment .input-radio:checked';

    function isGooglePaySelected() {
        var selectedMethod = jQuery(GOOGLE_PAY_BLOCK_SELECTOR).val() || jQuery(GOOGLE_PAY_CLASSIC_SELECTOR).val();
        return selectedMethod === ('square_google_pay' + squaregpay_params.sandbox);
    }

    function scheduleGooglePayInit(payments, options) {
        var opts = options || {};
        var minDelay = opts.minDelay || 100;
        var retryEvery = opts.retryEvery || 250;
        var maxWait = opts.maxWait || 10000;
        var requireSelection = opts.requireSelection !== false;
        var requireContainer = opts.requireContainer !== false;

        if (googlePayBootTimer) {
            clearTimeout(googlePayBootTimer);
            googlePayBootTimer = null;
        }

        googlePayBootStartedAt = Date.now();

        function tick() {
            var elapsed = Date.now() - googlePayBootStartedAt;
            var hasContainer = !!document.querySelector(GOOGLE_PAY_CONTAINER_SELECTOR);
            var hasSelection = !requireSelection || isGooglePaySelected();
            var hasReadyContainer = !requireContainer || hasContainer;
            var hasTotalAmount = getGooglePayTotalAmount() !== null;

            if (hasSelection && hasReadyContainer && hasTotalAmount && window.Square && payments) {
                initializeGooglePay(payments);
                return;
            }

            if (elapsed >= maxWait) {
                return;
            }

            googlePayBootTimer = setTimeout(tick, retryEvery);
        }

        googlePayBootTimer = setTimeout(tick, minDelay);
    }

    function clearGooglePayContainer() {
        const container = document.querySelector(GOOGLE_PAY_CONTAINER_SELECTOR);
        if (container) {
            container.innerHTML = '';
            delete container.dataset.woosquareGpayBound;
        }
    }

    async function destroyGooglePayInstance() {
        if (googlePayAttachTimer) {
            clearTimeout(googlePayAttachTimer);
            googlePayAttachTimer = null;
        }
        if (googlePay && typeof googlePay.destroy === 'function') {
            try {
                await googlePay.destroy();
            } catch (e) {
                // ignore destroy errors
            }
        }
        googlePay = null;
        clearGooglePayContainer();
    }

    function waitForGooglePayContainer(timeoutMs) {
        return new Promise(function(resolve) {
            var start = Date.now();
            (function poll() {
                var el = document.querySelector(GOOGLE_PAY_CONTAINER_SELECTOR);
                if (el) {
                    resolve(el);
                    return;
                }
                if (Date.now() - start >= timeoutMs) {
                    resolve(null);
                    return;
                }
                setTimeout(poll, 100);
            })();
        });
    }

    function handlePaymentMethodSubmissiongpay(event, paymentMethod) {
        event.preventDefault();
        try {
            jQuery('.woocommerce-error').remove();
            tokenize(paymentMethod);
        } catch (e) {
            // keep place-order usable
        }
    }

    // WP-1008: safe init for block checkout reload when Google Pay is pre-selected.
    // Do not assign initializeGooglePay() return value to googlePay (it is a Promise).
    async function initializeGooglePay(payments) {
        if (googlePayInitializing) {
            googlePayInitPending = true;
            return;
        }
        googlePayInitializing = true;
        jQuery('#googlepay-initialization').show();

        try {
            await destroyGooglePayInstance();

            var container = await waitForGooglePayContainer(5000);
            if (!container) {
                return;
            }

            var paymentRequest = buildPaymentRequest(payments);
            googlePay = await payments.googlePay(paymentRequest);

            await new Promise(function(resolve) {
                googlePayAttachTimer = setTimeout(resolve, 200);
            });
            googlePayAttachTimer = null;

            if (!googlePay || typeof googlePay.attach !== 'function') {
                throw new Error('Google Pay instance not ready to attach');
            }

            await googlePay.attach('#google-pay-button', {
                buttonColor: squaregpay_params.ewallet_button_color,
            });

            jQuery('.qty').prop('disabled', false);
            jQuery('#googlepay-initialization').hide();
            hideLoader();

            var googlePayButton = document.getElementById('google-pay-button');
            if (googlePayButton && !googlePayButton.dataset.woosquareGpayBound) {
                googlePayButton.dataset.woosquareGpayBound = '1';
                googlePayButton.addEventListener('click', async function(event) {
                    if (!jQuery('.square-nonce').val()) {
                        event.stopPropagation();
                        handlePaymentMethodSubmissiongpay(event, googlePay);
                    }
                });
            }
        } catch (e) {
            console.error('Initializing Google Pay failed', e);
            await destroyGooglePayInstance();
        } finally {
            jQuery('#googlepay-initialization').hide();
            hideLoader();
            googlePayInitializing = false;
            if (googlePayInitPending) {
                googlePayInitPending = false;
                initializeGooglePay(payments);
            }
        }
    }

    function showLoader() {
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
        $('body').unblock(); // Unblock the loader
    }

    function express_checkout_init(payments) {

        
        
        destroyGooglePayInstance();
        $('#google-pay-button').remove();
        $('.single_add_to_cart_button').after('<div id="google-pay-button"></div>');
        $('.wc-block-cart__payment-options.wp-block-woocommerce-cart-express-payment-block').after('<div id="google-pay-button"></div>');

        setTimeout(function() {
            initializeGooglePay(payments);

        }, 1500);


        hideLoader();

    }

    async function tokenize(paymentMethod) {

        const tokenResult = await
        paymentMethod.tokenize();
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

    function initgp(payments) {
        try {
            initializeGooglePay(payments);
        } catch (e) {
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
                    if (jQuery('form.wc-block-checkout__form, form.checkout.woocommerce-checkout').length > 0) {
                        scheduleGooglePayInit(payments, {
                            minDelay: 100,
                            retryEvery: 250,
                            maxWait: 12000
                        });
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

                                // Clear any previously set timeouts to avoid multiple inits
                                clearTimeout(window.expressCheckoutTimeout);

                                // Reinitialize the Google Pay button after a delay (500ms)
                                window.expressCheckoutTimeout = setTimeout(function() {
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
                            var oldValue = $(this).data('oldValue');
                            var newValue = $(this).val();
                            if (newValue !== oldValue) {
                                // Clear any previously set timeouts to avoid multiple inits
                                clearTimeout(window.expressCheckoutTimeout);

                                // Reinitialize the Google Pay button after a delay (500ms)
                                window.expressCheckoutTimeout = setTimeout(function() {
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
                        if (isGooglePaySelected()) {
                            try {
                                scheduleGooglePayInit(payments, {
                                    minDelay: 100,
                                    retryEvery: 250,
                                    maxWait: 10000
                                });
                            } catch (e) {
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
                            if (isGooglePaySelected()) {
                                setTimeout(
                                    function() {
                                        try {
                                            if (isGooglePaySelected()) {
                                                scheduleGooglePayInit(payments, {
                                                    minDelay: 100,
                                                    retryEvery: 250,
                                                    maxWait: 10000
                                                });
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
                    if (isGooglePaySelected()) {
                        setTimeout(
                            function() {
                                try {
                                    if (isGooglePaySelected()) {
                                        scheduleGooglePayInit(payments, {
                                            minDelay: 100,
                                            retryEvery: 250,
                                            maxWait: 10000
                                        });
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
                    if (isGooglePaySelected()) {
                        try {
                            scheduleGooglePayInit(payments, {
                                minDelay: 100,
                                retryEvery: 250,
                                maxWait: 10000
                            });
                        } catch (e) {
                            return;
                        }
                    }
                }
            );



        }
    );


}(jQuery));