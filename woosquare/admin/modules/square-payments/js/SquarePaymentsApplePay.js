(function($) {
    'use strict';
    const Apay_appId = squareapplepay_params.application_id;
    const Apay_location_id = squareapplepay_params.lid;

    function buildPaymentRequest(payments) {
        var id_of_div;
        var total;
        var total_price;
        if (jQuery('form.wc-block-checkout__form').length > 0) {
            id_of_div = jQuery('.wc-block-components-totals-footer-item-tax-value').html();
            total = id_of_div.split(squareapplepay_params.currency_sym)[1];
            total_price = parseFloat(total) * 100;
            var total = total.substring(0, total.length);
            var total_price = total.toString();
        } else if (jQuery('form.checkout.woocommerce-checkout').length > 0) {
            total = jQuery('div#order_review tr.order-total span.woocommerce-Price-amount bdi').html().replace(/<\/?[^>]+(>|$)/g, "").replace(squareapplepay_params.currency_sym, "");;

            //total_price = parseFloat(total) * 100;

            total_price = total.toString();

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
                        total_price = parseFloat(variation_price_element.first().text().replace(squareapplepay_params.currency_sym, '').replace(/[^\d.-]/g, '')).toFixed(2);
                    } else {
                        // Regular price
                        total_price = parseFloat(jQuery('.woocommerce-variation-price .woocommerce-Price-amount bdi').first().text().replace(squareapplepay_params.currency_sym, '').replace(/[^\d.-]/g, '')).toFixed(2);
                    }

                    // Multiply by quantity
                    total_price = (total_price * jQuery('.qty').val()).toFixed(2);
                    console.log('Variable product selected. Total price: ' + total_price);


                } else {
                    // Simple product page
                    total_price = (squareapplepay_params.get_price * jQuery('.qty').val()).toFixed(2);
                    console.log('Simple product. Total price: ' + total_price);
                }
            } else if (jQuery('body').hasClass('woocommerce-cart')) {
                // Cart page - Extract total price from the provided HTML structure
                var cart_total_element = jQuery('.wc-block-components-totals-footer-item-tax-value').first();

                if (cart_total_element.length > 0) {
                    // Extract the total price and clean up any unwanted characters
                    total_price = parseFloat(cart_total_element.text().replace(squareapplepay_params.currency_sym, '').replace(/[^\d.-]/g, '')).toFixed(2);
                    console.log('Cart page. Total price: ' + total_price);
                } else {
                    console.error('Total price element not found on the cart page.');
                }
            }
        }

		
		var total_price = total_price.replace(",", ""); 
        console.log('applepay. Total price: ' + total_price);
        //var id_of_div = jQuery('div#order_review tr.order-total span.woocommerce-Price-amount bdi').html();
        //var total = id_of_div.split("span")[2];
        //var total = total.substring(1, total.length);
        //var total_price = total.toString(); 
        return payments.paymentRequest({
            countryCode: squareapplepay_params.country_code,
            currencyCode: squareapplepay_params.currency_code,
            total: {
                amount: total_price,
                label: 'Total',
            },
        });
    }



    function express_checkout_init_apple(payments) {

        var style = 'style="-apple-pay-button-style:' + squareapplepay_params.ewallet_button_color + ';"'

        $('#apple-pay-button').remove();
        $('.apple-pay-button-psingle-product').html('<div id="apple-pay-button" ' + style + ' class="apple-pay-button-single-product"></div>');
        $('.wc-block-cart__payment-options.wp-block-woocommerce-cart-express-payment-block').after('<div id="apple-pay-button" ' + style + ' class="apple-pay-button-single-product"></div>');

        setTimeout(function() {
            console.log('Quantity message detected:', this.textContent);
            initializeApplePay(payments);

        }, 1500);

    }

    async function tokenize(paymentMethod) {
        const tokenResult = await paymentMethod.tokenize();
        // alert('tokenssss'+tokenResult.status+'tttt'+tokenResult.token);
        // alert(tokenResult.token); 
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
                        _payid: 'square_apple_pay'+squareapplepay_params.sandbox,
                        square_pay_nonce: squareapplepay_params.square_pay_nonce, // Pass the security nonce
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
                if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_apple_pay' + squareapplepay_params.sandbox) {
                    // pay_form.submit();
                    jQuery(".wc-block-components-checkout-place-order-button").trigger("click");
                } else {
                    $form.submit();
                }
                hideLoader();
            }

            // return tokenResult;
        } else {
            let errorMessage = tokenResult.status;
            if (tokenResult.errors) {
                errorMessage += tokenResult.errors;
            }
            throw new Error(errorMessage);
        }
    }


    async function initializeApplePay(payments) {



        const paymentRequest = buildPaymentRequest(payments)
        const applePay = await payments.applePay(paymentRequest);
        // Note: You do not need to `attach` applePay.


        setTimeout(function() {
            const cardButton = document.getElementById(
                'apple-pay-button'
            );

            function handlePaymentMethodSubmissionapplep(event, paymentMethod) {

                //debugger;
                event.preventDefault();


                try {
                    // disable the submit button as we await tokenization and make a
                    // payment request.
                    const token = tokenize(paymentMethod);


                    if (token.status === 'OK') {

                    } else {
                        // var html = '';
                        // html += '<ul class="woocommerce_error woocommerce-error">';
                        // $('#place_order').prop('disabled', false);
                        // html += '<li>' + token + '</li>';
                        // html += '</ul>';
                        // $( '.payment_method_square_plus fieldset' ).eq(0).prepend( html );
                        // var $form = jQuery( 'form.woocommerce-checkout, form#order_review' );
                        // $form.append( '<input type="hidden" class="square_submit_error" name="square_submit_error" value="' + html + '" />' );
                    }
                    console.debug('Payment Success', displayPaymentResults);
                } catch (e) {
                    console.error(e.message);
                }
            }

            //Checkpoint 2
            if (applePay !== undefined) {
                const applePayButton = document.getElementById('apple-pay-button');

                applePayButton.addEventListener('click', async function(event) {

                    handlePaymentMethodSubmissionapplep(event, applePay);
                });
            }


        }, 2000);
        return applePay;
    }

    function showLoaderapple() {
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
    function hideLoaderapple() {
        console.log('hideLoaderhideLoader');
        $('body').unblock(); // Unblock the loader
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

    document.addEventListener('DOMContentLoaded', async function() {


        if (!window.Square) {
            throw new Error('Square.js failed to load properly');
        }
        const payments = window.Square.payments(Apay_appId, Apay_location_id);

        let applePay;

        jQuery(document.body).on('updated_checkout', function() {
            try {
                applePay = initializeApplePay(payments);
            } catch (e) {
                jQuery("#browser_support_msg").text("Apple Pay is not available on this browser.");
                document.getElementById("apple-pay-button").style.display = "none";
                //console.log('Initializing Apple Pay failed', e);
            }
        });
        setTimeout(function() {
            if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_apple_pay' + squareapplepay_params.sandbox) {

                try {
                    if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_apple_pay' + squareapplepay_params.sandbox) {
                        applePay = initializeApplePay(payments);
                    }
                } catch (e) {
                    jQuery("#browser_support_msg").text("Apple Pay is not available on this browser.");
                    document.getElementById("apple-pay-button").style.display = "none";
                    return;
                }
            }
        }, 500);
        jQuery(document).on('change', 'body.woocommerce-checkout', function() {
            setTimeout(() => {
                if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_apple_pay' + squareapplepay_params.sandbox) {
                    setTimeout(function() {
                        try {
                            if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_apple_pay' + squareapplepay_params.sandbox) {
                                applePay = initializeApplePay(payments);
                            }
                        } catch (e) {
                            jQuery("#browser_support_msg").text("Apple Pay is not available on this browser.");
                            document.getElementById("apple-pay-button").style.display = "none";
                            return;
                        }
                    }, 500);
                }
            }, 1000);
        })
        jQuery('form.wc-block-checkout__form').on('change', "input[name=radio-control-wc-payment-method-options]", function() {
            if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_apple_pay' + squareapplepay_params.sandbox) {
                setTimeout(function() {
                    try {
                        if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_apple_pay' + squareapplepay_params.sandbox) {
                            applePay = initializeApplePay(payments);
                        }
                    } catch (e) {
                        jQuery("#browser_support_msg").text("Apple Pay is not available on this browser.");
                        document.getElementById("apple-pay-button").style.display = "none";
                        return;
                    }
                }, 500);
            }
        });

        // Check if we are on the single product page
        if ($('body').hasClass('single-product') || $('body').hasClass('woocommerce-cart')) {
            // Add Apple Pay button to single product page, for example below "Add to Cart" button
            if (!$('#apple-pay-button').length) {
                $('.single_add_to_cart_button').after('<div id="apple-pay-button"></div>');
            }

            // Call the initializeApplePay function with appropriate parameters (e.g., payments object)
            initializeApplePay(payments);

            // Flag to prevent multiple executions of the same event
            let isEventTriggered = false;

            // Function to handle the quantity change
            function handleQuantityChange() {
                var oldValueapl = $(this).data('oldValueaplcart');
                var newValueapl = $(this).val();

                // Only execute if the new value differs from the old value
                if (newValueapl !== oldValueapl && !isEventTriggered) {
                    isEventTriggered = true; // Set the flag to true to prevent further executions

                    console.log('Quantity changed: ' + newValueapl);

                    // Clear any previously set timeouts to avoid multiple inits
                    clearTimeout(window.expressCheckoutTimeoutapple);

                    // Reinitialize the Apple Pay button after a delay (800ms)
                    window.expressCheckoutTimeoutapple = setTimeout(function() {
                        console.log('express_checkout_init_apple');
                        express_checkout_init_apple(payments);

                        isEventTriggered = false; // Reset the flag after the event is processed
                    }, 800);
                }
            }

            // Store the old value when the input is focused
            $('body').on('focus', '.wc-block-components-quantity-selector__input', function() {
                $(this).data('oldValueaplcart', $(this).val());
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
                                        console.log('express_checkout_init_apple 2');
                                        express_checkout_init_apple(payments);
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
                var oldValueapl = $input.data('oldValueaplcart');
                var newValueapl = $input.val();

                // Only trigger the event if the value has actually changed
                if (newValueapl !== oldValueapl) {
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
                            var oldValueapl = $input.data('oldValueaplcart');
                            var newValueapl = $input.val();

                            // Only trigger the event if the value has actually changed
                            if (newValueapl !== oldValueapl) {
                                handleQuantityChange.call(this);
                            }
                        });
                    }
                });
            }, 2000); // Checks every 2 seconds (reduced from 4 seconds)

            $('body').on('focus', '.qty', function() {
                $(this).data('oldValueapl', $(this).val());
            });

            jQuery('.qty').prop('disabled', true);
            $('body').on('keyup paste input', '.qty', function() {
                console.log('Quantity changed apple: ' + $(this).val());
                var oldValueapl = $(this).data('oldValueapl');
                var newValueapl = $(this).val();
                if (newValueapl !== oldValueapl) {
                    // Clear any previously set timeouts to avoid multiple inits
                    clearTimeout(window.expressCheckoutTimeoutapple);

                    // Reinitialize the Apple Pay button after a delay (500ms)
                    window.expressCheckoutTimeoutapple = setTimeout(function() {
                        console.log('express_checkout_init_apple');
                        express_checkout_init_apple(payments);
                    }, 500);
                }
            });

            // Reinitialize Apple Pay when a variation is selected/changed
            $('form.variations_form').on('woocommerce_variation_has_changed', function() {
                setTimeout(function() {
                    express_checkout_init_apple(payments);
                }, 500);
            });
        }



    });
}(jQuery));