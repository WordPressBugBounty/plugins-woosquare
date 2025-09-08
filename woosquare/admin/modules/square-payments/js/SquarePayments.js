(function($) {
    'use strict';
    const appId = square_params.application_id;
    const location_id = square_params.location_id;
    const timeoutfilter = square_params.timeoutfilter;
    const cardcontainer = square_params.cardcontainer;
    let cardButton;
    let verificationToken; 

    let card;
    async function initializeCard(payments) {
        setTimeout( async function() {
        const cardContainers = jQuery(cardcontainer);

        // Destroy card if already attached

        if (cardContainers.html().length > 1 && typeof card !== 'undefined') {

            card.detach(cardcontainer).then(() => {

                card = initializeCard(payments);
            }).catch(err => {
                console.error('Error detaching:', err);
            });
        }

        const cardId = jQuery('input[name="saved_cards"]:checked').val();
        const customerId = jQuery('.saved_cards_squ_customer_id').val();

        card = await payments.card();

        await card.attach(cardcontainer);

        jQuery('#card-initialization').hide();

        // Saved card switcher
        jQuery(document).on('click', '.saved_cards_squ', function() {
            card.destroy();
            jQuery('.wooSquare-checkout').hide();
            jQuery('#sq-card-saved').removeAttr('checked');
        });

        return card;
        }, timeoutfilter);
    }


    async function handlePaymentMethodSubmissioncc(event, paymentMethod, shouldVerify = false, payments) {
        event.preventDefault();
        // Dynamically determine intent
        let intent = 'CHARGE';
        if (
            jQuery('#sq-card-saved').is(":checked") ||
            square_params.subscription ||
            jQuery('._wcf_flow_id').val() ||
            jQuery('._wcf_checkout_id').val() ||
            jQuery('.is_preorder').val()
        ) {
            intent = 'STORE';
        }
        try {
            jQuery('.woocommerce-error').remove();
            const payments = {
                amount: square_params.cart_total,
                currencyCode: square_params.get_woocommerce_currency,
                billingContact: {},
                intent: intent,
                customerInitiated: true,
                sellerKeyedIn: false,
            };

            await tokenize(paymentMethod, payments);
        } catch (e) {
            console.error(e.message);
            if (cardButton) cardButton.disabled = false;
        }
    }

    async function handlePaymentWithCardOnFileMethodSubmission(event, cardId, customerId) {
        try {
            if (cardId) {
                //let verificationToken = await verifyBuyer(payments, cardId, 'CHARGE');
                if (true) {
                    
                    const pay_form = jQuery('form.wc-block-checkout__form, form.woocommerce-checkout, form#order_review');
                    pay_form.append(`<input type="hidden" class="square-nonce" name="square_nonce" value="${cardId}" />`);
                    pay_form.append(`<input type="hidden" class="square-customerId" name="square_customerid" value="${customerId}" />`);
                    if (jQuery('#wfacp_checkout_form').html() != undefined) {
                        pay_form.append(`<input type="hidden" name="funnel_order" value="1" />`);
                    }
                    if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_plus' + square_params.sandbox) {
                         console.log('triggertrigger');
                        jQuery(".wc-block-components-checkout-place-order-button").prop('disabled', false);
                        jQuery(".wc-block-components-checkout-place-order-button").trigger("click");
                    } else {
                        pay_form.submit();
                    }
                } else {
                    if (cardButton) cardButton.disabled = false;
                }
            }
        } catch (e) {
            console.error(e.message);
            if (cardButton) cardButton.disabled = false;
        }
    }

    async function handleStoreCardMethodSubmission(payments, paymentMethod) {
        
            const verificationDetails = {
                amount: square_params.cart_total, // for CHARGE_AND_STORE store need amount
                currencyCode: square_params.get_woocommerce_currency,
                billingContact: {},
                intent: 'CHARGE_AND_STORE',
                customerInitiated: true,
                sellerKeyedIn: false,
            };
            const token = await tokenize(paymentMethod, verificationDetails);
            //verificationToken = await verifyBuyer(payments, token, 'STORE');
           
            if (true) {
                const pay_form = jQuery('form.wc-block-checkout__form, form.woocommerce-checkout, form#order_review');
                let formDataArray = [];
                pay_form.find('input, select, textarea').each(function() {
                    let input = jQuery(this); 
                    let name = input.attr('id');
                    if (name === 'email') name = 'billing_email';
                    if (name) {
                        formDataArray.push({
                            name: name.replace('-', '_'),
                            value: input.val()
                        });
                    }
                });

                let serialized = jQuery.param(formDataArray);

                jQuery.ajax({
                    url: square_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'saved_card_charge',
                        card_nonce: token,
                        verification_token: '',
                        pay_form: serialized,
                        subscription: square_params.subscription,
                        square_pay_nonce: square_params.square_pay_nonce,
                    },
                    success: function(response) {
                        const responsee = JSON.parse(response);
                        if (responsee.card_id) {
                            handlePaymentWithCardOnFileMethodSubmission(event, responsee.card_id, responsee.customer_id);
                        } else {
                            console.error('Card id error: ', responsee.message);
                            jQuery('form.checkout').prepend(`<ul class="woocommerce-error"><li>${responsee.message}</li></ul>`);
                            jQuery('html, body').animate({
                                scrollTop: 0
                            }, 500);
                            if (cardButton) cardButton.disabled = false;
                        }
                    }
                });
            }
        
    }
    async function checkoutBlockSavedCardPayment(payments) {

        async function handlePaymentWithBlockCardOnFileMethodSubmission(event, cardId, customerId) {
            // event.preventDefault();
            try {
                // disable the submit button as we await tokenization and make a
                // payment request.
                if (cardId) {

                    verificationToken = await verifyBuyer(payments, cardId, 'CHARGE');
                    if (verificationToken) {
                        const pay_form = jQuery('form.wc-block-checkout__form, form.woocommerce-checkout, form#order_review');
                        pay_form.append('<input type="hidden" class="buyerVerification-token" name="buyerverification_token" value="' + verificationToken + '"  />');
                        if (jQuery('#wfacp_checkout_form').html() != undefined) {
                            pay_form.append('<input type="hidden" class="funnel_order" name="funnel_order" value="1" />');
                        }
                        // inject nonce to a hidden field to be submitted
                        pay_form.append('<input type="hidden" class="square-nonce" name="square_nonce" value="' + cardId + '" />');
                        pay_form.append('<input type="hidden" class="saved_cards" name="saved_cards" value="' + cardId + '" />');
                        pay_form.append('<input type="hidden" class="square-customerId" name="square_customerid" value="' + customerId + '" />');
                        // if(jQuery(cardcontainer).html().length > 1){
                        // pay_form.submit();
                        // }
                        if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_plus' + square_params.sandbox || jQuery("input[name=radio-control-wc-payment-method-saved-tokens]").is(":checked")) {
                            // pay_form.submit();
                            jQuery(".wc-block-components-checkout-place-order-button").trigger("click");
                        } else {
                            pay_form.submit();
                        }

                    }
                }
            } catch (e) {
                console.error(e.message);
            }

        }

        jQuery('.wc-block-components-checkout-place-order-button').on('click', function(event) {

            if (!jQuery('.square-nonce').val()) {
                event.stopPropagation();
                if (jQuery("input[name=radio-control-wc-payment-method-saved-tokens]").is(":checked")) {
                    if (jQuery("input[name=radio-control-wc-payment-method-saved-tokens]:checked").val()) {
                        jQuery.ajax({
                            url: square_params.ajax_url,
                            data: {
                                'action': 'get_saved_token_card_id',
                                'saved_token': jQuery("input[name=radio-control-wc-payment-method-saved-tokens]:checked").val(),
                                'square_pay_nonce': square_params.square_pay_nonce,
                            },
                            type: 'POST',
                            success: function(response) {
                                var responsee = JSON.parse(response);
                                if (responsee.card_id != null) {
                                    var customerId = responsee.customer_id;
                                    var cardId = responsee.card_id;
                                    if (
                                        jQuery('#add_payment_method').length > 0 &&
                                        jQuery('.wooSquare-checkout').length > 0) {
                                        window.location.href = window.location.href;
                                    }
                                    handlePaymentWithBlockCardOnFileMethodSubmission(event, cardId, customerId);
                                } else {
                                    console.error('Card id error: ', response.message);
                                }

                            }
                        })
                    }
                }
            }
        })
    }

    async function verifyBuyer(payments, sourceId, intten = null) {


        const verificationDetails = {
            amount: square_params.cart_total,
            intent: intten,
            currencyCode: square_params.get_woocommerce_currency,
            billingContact: {}
        };


        const verificationResults = await payments.verifyBuyer(
            sourceId,
            verificationDetails
        );

        return verificationResults.token;
    }

    // This function tokenizes a payment method. 
    // The Ã¢â‚¬ËœerrorÃ¢â‚¬â„¢ thrown from this async function denotes a failed tokenization,
    // which is due to buyer error (such as an expired card). It is up to the
    // developer to handle the error and provide the buyer the chance to fix
    // their mistakes.
    async function tokenize(paymentMethod, verificationDetails = null) {
        /*if (verificationDetails.amount == null) {
            const verificationDetails = {
                currencyCode: square_params.get_woocommerce_currency,
                billingContact: {},
                intent: intent,
                customerInitiated: true,
                sellerKeyedIn: false,
            };

        }*/

        try {
            const tokenResult = await paymentMethod.tokenize(verificationDetails);
            if (tokenResult.status === 'OK') {
                const token = tokenResult.token;

                if (
                    jQuery('#sq-card-saved').is(":checked") ||
                    square_params.subscription == 1 ||
                    jQuery('#wfacp_checkout_form').html() != undefined ||
                    jQuery('._wcf_checkout_id').val() ||
                    jQuery('#add_payment_method').length > 0
                ) {
                    return token;
                } else {
                    const pay_form = jQuery('form.woocommerce-checkout, form.wc-block-checkout__form, form#order_review');
                    pay_form.append('<input type="hidden" class="square-nonce" name="square_nonce" value="' + token + '" />');

                    if (document.getElementsByClassName('woocommerce-error')) {
                        jQuery('#place_order').prop('disabled', false);
                    }

                    if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_plus' + square_params.sandbox) {
                        jQuery(".wc-block-components-checkout-place-order-button").trigger("click");

                    } else {
                        pay_form.submit();
                    }
                }
            } else {
                let errorMessage = `Tokenization failed-status: ${tokenResult.status}`;
                if (tokenResult.errors) {
                    errorMessage += ` and errors: ${JSON.stringify(tokenResult.errors)}`;
                    jQuery('#place_order').prop('disabled', false);
                }
                throw new Error(errorMessage);
            } 
        } catch (error) {

            console.error("Tokenization error:", error);
        }
        
    }



    // document.addEventListener('DOMContentLoaded', async function () {
    jQuery(window).on("load", function() {

        if (!window.Square) {
            throw new Error('Square.js failed to load properly');
        }

        const payments = window.Square.payments(appId, location_id);

        if (jQuery("input[name=radio-control-wc-payment-method-saved-tokens]").is(":checked")) {
            checkoutBlockSavedCardPayment(payments);
        }
        /*if (jQuery('.payment_method_square_ach_payment_' + square_params.sandbox).length == 0) {
            jQuery(document.body).on('updated_checkout', function() {


                if (jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_plus' + square_params.sandbox) {
                    // let card;
                    try {
                        card = initializeCard(payments);
                        return card;
                    } catch (e) {
                        console.error('Initializing Card failed', e);
                        return;
                    }
                }
            })
        }*/
       
                function submission_isblock(isSquare) {
                    jQuery('.woocommerce-error').remove();
                    cardButton.disabled = true;

                    const hasCardContainer = jQuery('.wooSquare-checkout ' + cardcontainer).length > 0;
                    if (isSquare && hasCardContainer) {

                       
                        if (!jQuery('.saved_cards_squ').is(":checked") &&
                            !jQuery('#sq-card-saved').is(":checked") &&
                            !square_params.subscription &&
                            jQuery('#wfacp_checkout_form').html() == undefined &&
                            !jQuery('._wcf_checkout_id').val()) {

                            // for lagecy checkout// && !jQuery('#add_payment_method').length > 0
                            handlePaymentMethodSubmissioncc(event, card, true, payments);
                        } else if (jQuery('.saved_cards_squ').is(":checked")) {
                            handlePaymentWithCardOnFileMethodSubmission(event, cardId, customerId);
                        } else if (jQuery('#sq-card-saved').is(":checked") || square_params.subscription == 1 || jQuery('#wfacp_checkout_form').html() != undefined || jQuery('._wcf_checkout_id').val() || jQuery('#add_payment_method').length > 0 || jQuery('.wooSquare-checkout').length > 0) {
                            event.preventDefault();
							event.stopPropagation();
                            handleStoreCardMethodSubmission(payments, card);
                        }
                    }

                }

                function checkSquareCondition() {
                    let val1 = $("input[name=radio-control-wc-payment-method-options]:checked").val();
                    let val2 = $(".woocommerce-checkout-payment .input-radio:checked").val();
                    let isAddPaymentForm = $('#add_payment_method').length > 0;
                    let isWooSquareCheckout = $('.wooSquare-checkout').length > 0;

                    let finalResult = (
                        (val1 == 'square_plus' + square_params.sandbox ||
                            val2 == 'square_plus' + square_params.sandbox) ||
                        isAddPaymentForm ||
                        isWooSquareCheckout
                    );

                    // Array of IDs you want to check
                        var excludedIds = [
                            'email',
                            'billing-country',
                            'billing-first_name',
                            'billing-last_name',
                            'billing-address_1',
                            'billing-city',
                            'billing-state',
                            'billing-phone',
                            'billing-postcode',
                            'wc-block-components-totals-coupon__input-0'
                        ];

                        // Array of names you want to check
                        var excludedNames = [
                            'terms',
                            'square_plus' + square_params.sandbox + 'sq-card-saved'
                        ];

                        // Array of classes you want to check
                        var excludedClasses = [
                            'wc-block-components-checkbox__input',
                            'wc-block-components-textarea'
                        ];
                    let debounceTimeout;

                        function handleCardInitDebounced(e) {
                            const target = jQuery(e.target);
                            const value = target.val();
                            isSquare = jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() === 'square_plus' + square_params.sandbox;
                            if (
                                excludedNames.indexOf(target.attr('name')) === -1 &&
                                excludedIds.indexOf(target.attr('id')) === -1 &&
                                excludedClasses.indexOf(target.attr('class')) === -1 &&
                                jQuery('.wfob_bump_product').attr('class') != 'wfob_checkbox wfob_bump_product'
                            ) {
                                clearTimeout(debounceTimeout);

                                debounceTimeout = setTimeout(() => {
                                    try {
                                        const selectedPayment =
                                            jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() ||
                                            jQuery('.woocommerce-checkout-payment .input-radio:checked').val();

                                        if (selectedPayment === 'square_plus' + square_params.sandbox) {
                                            jQuery('#card-initialization').show();
                                            card = initializeCard(payments);
                                        }
                                    } catch (e) {
                                        jQuery('#card-initialization').hide();
                                        console.error('Initializing Card failed:', e);
                                    }
                                }, 500); // Adjust debounce delay here for checkout form change
                            }
                        }
                    // Listen to key/value changes only (like typing)
                    jQuery(document).on('change', 'form.wc-block-checkout__form input[name=radio-control-wc-payment-method-options]', handleCardInitDebounced);
                    if ((jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_plus' + square_params.sandbox ||
                            jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_plus' + square_params.sandbox) ||
                        jQuery('#add_payment_method').length > 0 || // Check if form with id "add_payment_method" exists
                        jQuery('.wooSquare-checkout').length > 0 // Check if field tag with class "wooSquare-checkout" exists
                    ) {
                        jQuery('#card-initialization').show();
                        card = initializeCard(payments);
                        
                        /*$('form.checkout').on('change', '.woocommerce-checkout-payment input', function() {
                                if (jQuery(this).attr('name') != 'terms' && jQuery(this).attr('name') != 'square_plus' + square_params.sandbox + 'sq-card-saved' && jQuery('.wfob_bump_product').attr('class') != 'wfob_checkbox wfob_bump_product') {
                                    if (jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_plus' + square_params.sandbox) {
                                        // let card;
                                        try {
                                            card = initializeCard(payments);
                                            return card;
                                        } catch (e) {
                                            console.error('Initializing Card failed', e);
                                            return;
                                        }
                                    }
                                }
                            });*/
                        

                        
                        
                    }
                    let isSquare;
                    if (jQuery('.wc-block-checkout').length > 0) {
                        const buttons = document.getElementsByClassName('wc-block-components-checkout-place-order-button');
                        
                        if (buttons.length > 0) {
                            cardButton = buttons[0]; // pick the first one
                        }
                        isSquare = jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() === 'square_plus' + square_params.sandbox;
                        if (cardButton) {
                            cardButton.addEventListener('click', async function(event) {
                                //block checkout
                                if(jQuery('.square-nonce').length > 0){
                                    return true;
                                }
                                submission_isblock(isSquare);
                            });
                        }
                    } else {
                        cardButton = document.getElementById('place_order');
                        isSquare = jQuery('.woocommerce-checkout-payment .input-radio:checked').val() === 'square_plus' + square_params.sandbox;
                        if (cardButton) {

                            jQuery(document).on('click', '#' + jQuery(cardButton).attr('id'), function(event) {
                                //non block checkout
                                if(jQuery('.square-nonce').length > 0){
                                    return true; 
                                }
                                submission_isblock(isSquare);
                            });
                        }
                    }
                }

                const intervalId = setInterval(function () {
                const $radioInput = jQuery("input[name=radio-control-wc-payment-method-options], input[name=payment_method]");
                const $selectedPayment = jQuery('.woocommerce-checkout-payment .input-radio:checked').val();
                const $placeOrderBtn = jQuery('.wc-block-components-checkout-place-order-button, #place_order');
                    console.log($radioInput.length);
                     console.log($placeOrderBtn.length);
                if (
                    $radioInput.length > 0 &&
                    $placeOrderBtn.length > 0
                ) {
                    console.log("✅ All elements loaded. Stopping interval.");

                    clearInterval(intervalId); // ❌ Stop checking

                    checkSquareCondition();    // ✅ Run your function
                }
            }, 500); // Check every 500ms
            


        var pay_for_order = new URLSearchParams(window.location.search);
        var pay_for_order = pay_for_order.get('pay_for_order');
        if (pay_for_order) {
            card = initializeCard(payments);
        }
        jQuery(document).on('click', '.new_cards_squ', function() {
            // jQuery.WooSquare_payments.loadForm();

            jQuery('.wooSquare-checkout').show();
            try {
                card = initializeCard(payments);
            } catch (e) {
                console.error('Initializing Card failed', e);
                return;
            }
            // $( '.payment_box.payment_method_square_plus' ).css( { 'display': 'block', 'visibility': 'visible', 'height': 'auto' } );	
        });

    });
}(jQuery));


jQuery(window).on("load", function() {
    setTimeout(function() {
        hideunhide();
    }, 600);
    jQuery('form.wc-block-checkout__form').on('change', "input[name=radio-control-wc-payment-method-options]", function() {
        hideunhide();
    });
    // Check if the object and its property exist and is a non-empty array
      if (
        typeof wcSettings !== 'undefined' &&
        wcSettings.customerPaymentMethods &&
        Array.isArray(wcSettings.customerPaymentMethods.cc) &&
        wcSettings.customerPaymentMethods.cc.length > 0
      ) {
        const methods = wcSettings.customerPaymentMethods.cc;
    
        const defaultMethod = methods.find(method => method.is_default === true);
    
        if (defaultMethod) {
          const tokenId = defaultMethod.tokenId;
    
          // Uncheck all radio buttons first
          const radioButtons = document.querySelectorAll('input[type="radio"][name="radio-control-wc-payment-method-saved-tokens"]');
          radioButtons.forEach(radio => {
            radio.checked = false;
          });
    
          // Check the default one
          const input = document.querySelector(`input[type="radio"][value="${tokenId}"]`);
          if (input) {
            input.checked = true;
          }
        }
      } else {
        console.warn('No saved customer payment methods found.');
      }
});

jQuery(function($) {
    $('form.checkout').on('change', '.woocommerce-checkout-payment input', function() {
        hideunhide();
    });
});

function hideunhide() {
    if (jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_plus' + square_params.sandbox ||
        jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_plus' + square_params.sandbox
    ) {
        jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'flex');
    } else if (jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_google_pay' + square_params.sandbox ||
        jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_google_pay' + square_params.sandbox
    ) {
        jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'none');
    } else if (jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_apple_pay' + square_params.sandbox ||
        jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_apple_pay' + square_params.sandbox
    ) {
        jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'none');
    } else if (jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_ach_payment' + square_params.sandbox ||
        jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_ach_payment' + square_params.sandbox
    ) {
        jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'none');
    } else if (jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_after_pay' + square_params.sandbox ||
        jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_after_pay' + square_params.sandbox
    ) {
        jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'none');
    } else if (jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_cash_app_pay' + square_params.sandbox ||
        jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_cash_app_pay' + square_params.sandbox
    ) {
        jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'none');
    } else {
        jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'flex');
    }
}