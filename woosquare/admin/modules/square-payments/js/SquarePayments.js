(function ($) {
    'use strict';
    const appId = square_params.application_id;
    const location_id = square_params.location_id;
    const timeoutfilter = square_params.timeoutfilter;
    const cardcontainer = square_params.cardcontainer;
    let cardButton;
    let verificationToken;

    let card;
    let payments; // Global payments object
    let isInitializing = false; // Prevent duplicate initialization
    let cardPromise = null; // Shared promise for initialization
    let previousPaymentMethod = null; // Track previous payment method
    // WSSS-404: after a failed charge, Square rejects reused card nonces (CARD_TOKEN_USED).
    let squareNonceSubmitted = false;

    /**
     * Remove Square card nonce / buyer-verification fields so the next Place Order re-tokenizes.
     */
    function clearSquareCardPaymentTokens() {
        jQuery('input.square-nonce, input[name="square_nonce"]').remove();
        jQuery('input.buyerVerification-token, input[name="buyerverification_token"], input[name="buyerVerification_token"]').remove();
        squareNonceSubmitted = false;
    }

    /**
     * Reuse an existing square_nonce only for the intentional tokenize → re-submit click.
     * After that submit (success or fail), the next Place Order must re-tokenize (WSSS-404).
     */
    function shouldReuseSquareNonce() {
        if (!jQuery('.square-nonce').length || !jQuery('.square-nonce').val()) {
            return false;
        }
        // Nonce already sent with a payment attempt — do not reuse (CARD_TOKEN_USED).
        if (squareNonceSubmitted) {
            clearSquareCardPaymentTokens();
            return false;
        }
        squareNonceSubmitted = true;
        return true;
    }

    // Function to get or create payments object
    function getPayments() {
        if (payments) return payments;
        if (typeof window.Square !== 'undefined' && window.Square.payments) {
            if (!appId) {
                console.error('âŒ [getPayments] Application ID is missing. Check plugin settings.');
                return null;
            }
            if (!location_id) {
                console.error('âŒ [getPayments] Location ID is missing. Check Square settings and select a location.');
                return null;
            }
            try {
                payments = window.Square.payments(appId, location_id);
                return payments;
            } catch (e) {
                console.error('âŒ [getPayments] Failed to create payments object:', e);
                // Check for 401-like auth errors in the message
                if (e.message && (e.message.includes('401') || e.message.includes('unauthorized'))) {
                    console.error('âŒ [getPayments] Authorization error. Please re-connect your Square account.');
                }
            }
        } else {
            console.error('âŒ [getPayments] window.Square not found. SDK may have failed to load.');
        }
        return null;
    }

    async function initializeCard(paymentsValue) {
        // --- Smart Polling / Intelligent Wait ---
        const startTime = Date.now();
        const maxWait = 10000; // upper bound; resolves sooner when ready

        async function waitForReady() {
            return new Promise((resolve, reject) => {
                const interval = setInterval(() => {
                    const elapsed = Date.now() - startTime;
                    const isSdkReady = typeof window.Square !== 'undefined' && typeof window.Square.payments === 'function';
                    const $container = jQuery(cardcontainer);
                    // Relaxed visibility check for Gutenberg block checkouts
                    const isContainerVisible = $container.length > 0;

                    if (isSdkReady && isContainerVisible) {
                        clearInterval(interval);
                        resolve(true);
                    } else if (elapsed > maxWait) {
                        clearInterval(interval);
                        console.error('âŒ [initializeCard] Timeout waiting for Square SDK or Container. SDK Ready:', isSdkReady, 'Container Visible:', isContainerVisible, 'Wait elapsed:', elapsed);
                        reject(new Error('Square SDK or Container not ready in time'));
                    }
                }, 50); // Check every 50ms
            });
        }

        try {
            await waitForReady();
            // Short buffer for Squareâ€™s internal Web Payments SDK to finish booting
            await new Promise(function (r) { setTimeout(r, 800); });
        } catch (e) {
            return null;
        }

        let paymentsToUse = paymentsValue || getPayments();
        if (!paymentsToUse) {
            return null;
        }

        // Guard against parallel init
        if (isInitializing || cardPromise) {
            return cardPromise;
        }

        // If card is already attached and working, return it
        const $container = jQuery(cardcontainer);
        const hasIframe = $container.find('iframe.sq-card-component').length > 0;
        if (hasIframe && card && typeof card.tokenize === 'function') {
            return card;
        }

        cardPromise = (async () => {
            isInitializing = true;
            const cardContainers = jQuery(cardcontainer);

            // Destroy card if already attached to clean up
            if (cardContainers.find('iframe.sq-card-component').length > 0 && card) {
                try {
                    const detachPromise = card.detach();
                    const detachTimeout = new Promise((_, reject) => setTimeout(() => reject(new Error('Detach timeout')), 1000));
                    await Promise.race([detachPromise, detachTimeout]);
                } catch (err) {
                    console.warn('âš ï¸ [initializeCard] Detach warning:', err);
                }
            }

            // Force clean container to ensure a clean slate even if detach failed
            cardContainers.find('.sq-card-wrapper, .sq-card-iframe-container, iframe').remove();

            const maxAttempts = 2;
            for (let attempt = 1; attempt <= maxAttempts; attempt++) {
                try {
                    card = await paymentsToUse.card();

                    // Use Promise.race to handle timeout
                    // Re-grab container each attempt to avoid stale references in block checkout rerenders
                    const $attachContainer = jQuery(cardcontainer);
                    if ($attachContainer.length === 0) {
                        throw new Error('Card container not found');
                    }

                    const attachPromise = card.attach(cardcontainer);
                    const timeoutPromise = new Promise((_, reject) =>
                        setTimeout(() => reject(new Error('Card attach timeout')), 8000)
                    );

                    await Promise.race([attachPromise, timeoutPromise]);

                    // Ensure container and parents are visible
                    setTimeout(function () {
                        const $checkContainer = jQuery(cardcontainer);
                        const iframe = $checkContainer.find('iframe.sq-card-component');
                        if (iframe.length > 0) {
                            $checkContainer.show();
                            $checkContainer.parent().show();
                            $checkContainer.closest('.wooSquare-checkout').show();
                        }
                    }, 200);

                    jQuery('#card-initialization').hide();

                    // Ensure payment form is visible
                    const $wooSquareCheckout = jQuery('.wooSquare-checkout');
                    if ($wooSquareCheckout.length > 0) {
                        $wooSquareCheckout.show();
                        $container.show();
                        $container.parent().show();
                    }

                    isInitializing = false;
                    cardPromise = null; // Clear so subsequent calls create a new promise
                    return card;
                } catch (error) {
                    const errMsg = (error && error.message) ? error.message : String(error);
                    const isSdkTimeout = errMsg.indexOf('initialized in time') !== -1 || errMsg.indexOf('unable to be initialized') !== -1;

                    if (attempt < maxAttempts && isSdkTimeout) {
                        // Back off and retry once
                        await new Promise(function (r) { setTimeout(r, 1000); });

                        // Recreate the Square payments object if it is stuck internally
                        if (typeof window.Square !== 'undefined' && window.Square.payments) {
                            try {
                                paymentsToUse = window.Square.payments(appId, location_id);
                                payments = paymentsToUse;
                            } catch (e) {
                                console.error('Failed to recreate Square payments object:', e);
                            }
                        }

                        continue;
                    }

                    if (attempt >= maxAttempts) {
                        isInitializing = false;
                        cardPromise = null; // Allow retry on error
                        card = null;
                        console.error('âŒ [initializeCard] Final Error:', error);

                        const $errorContainer = jQuery(cardcontainer);
                        if ($errorContainer.length > 0) {
                            $errorContainer.show();
                            $errorContainer.closest('.wooSquare-checkout').show();
                        }
                        jQuery('#card-initialization').hide();
                        throw error;
                    }
                }
            }
        })();

        cardPromise.catch(e => {
            isInitializing = false;
            cardPromise = null;
            card = null;
        });

        return cardPromise;
    }

    // Also expose on window object so it's accessible from outside the IIFE
    if (typeof window !== 'undefined') {
        window.woosquareInitializeCard = initializeCard;
    }


    async function handlePaymentMethodSubmissioncc(event, paymentMethod, shouldVerify = false, payments) {
        if (event && event.preventDefault) {
            event.preventDefault();
        }

        // Check if payment method is valid
        if (typeof paymentMethod === 'undefined' || paymentMethod === null) {
            if (cardButton) cardButton.disabled = false;
            jQuery('#place_order').prop('disabled', false);
            const errorHtml = '<ul class="woocommerce-error" role="alert"><li>Card form is not ready. Please wait a moment and try again.</li></ul>';
            jQuery('.woocommerce-checkout').prepend(errorHtml);
            return;
        }

        // Ensure square_pay_nonce is in form before tokenization
        const pay_form = jQuery('form.woocommerce-checkout, form.wc-block-checkout__form, form#order_review');
        if (!pay_form.find('input[name="square_pay_nonce"]').length && square_params.square_pay_nonce) {
            pay_form.append('<input type="hidden" name="square_pay_nonce" value="' + square_params.square_pay_nonce + '" />');
        }

        // Dynamically determine intent
        let intent = 'CHARGE';
        if (
            jQuery('#sq-card-saved').is(":checked") ||
            square_params.subscription ||
            jQuery('._wcf_flow_id').val() ||
            jQuery('._wcf_checkout_id').val() ||
            jQuery('#add_payment_method').length > 0 ||
            jQuery('.is_preorder').val()
        ) {
            intent = 'STORE';
        }
        try {
            jQuery('.woocommerce-error').remove();
            const paymentDetails = {
                amount: square_params.cart_total,
                currencyCode: square_params.get_woocommerce_currency,
                billingContact: {},
                intent: intent,
                customerInitiated: true,
                sellerKeyedIn: false,
            };

            await tokenize(paymentMethod, paymentDetails);
        } catch (e) {
            if (cardButton) cardButton.disabled = false;
            jQuery('#place_order').prop('disabled', false);
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

                    // Ensure square_pay_nonce is present for legacy checkout
                    if (!pay_form.find('input[name="square_pay_nonce"]').length && square_params.square_pay_nonce) {
                        pay_form.append(`<input type="hidden" name="square_pay_nonce" value="${square_params.square_pay_nonce}" />`);
                    }

                    if (jQuery('#wfacp_checkout_form').html() != undefined) {
                        pay_form.append(`<input type="hidden" name="funnel_order" value="1" />`);
                    }
                    if (jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_plus' + square_params.sandbox) {
                        // Block checkout - don't interfere
                        jQuery(".wc-block-components-checkout-place-order-button").prop('disabled', false);
                        jQuery(".wc-block-components-checkout-place-order-button").trigger("click");
                    } else {
                        // Legacy checkout
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
            pay_form.find('input, select, textarea').each(function () {
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
                success: function (response) {
                    const responsee = JSON.parse(response);
                    if (responsee.card_id) {

                        if (
                            jQuery('#add_payment_method').length > 0 &&
                            jQuery('.wooSquare-checkout').length > 0) {
                            window.location.href = window.location.origin + '/my-account/payment-methods/';
                        }
                        handlePaymentWithCardOnFileMethodSubmission(event, responsee.card_id, responsee.customer_id);
                    } else {
                        console.error('Card id error: ', responsee.message);
                        jQuery('form.checkout,#add_payment_method').prepend(`<ul class="woocommerce-error"><li>${responsee.message}</li></ul>`);
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

        jQuery('.wc-block-components-checkout-place-order-button').on('click', function (event) {

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
                            success: function (response) {
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
    // The ÃƒÂ¢Ã¢â€šÂ¬Ã‹Å“errorÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ thrown from this async function denotes a failed tokenization,
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

                    // Ensure square_pay_nonce is present for legacy checkout
                    const nonceField = pay_form.find('input[name="square_pay_nonce"]');
                    if (!nonceField.length && square_params.square_pay_nonce) {
                        pay_form.append('<input type="hidden" name="square_pay_nonce" value="' + square_params.square_pay_nonce + '" />');
                    }

                    if (document.getElementsByClassName('woocommerce-error')) {
                        jQuery('#place_order').prop('disabled', false);
                    }

                    const isBlockCheckout = jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_plus' + square_params.sandbox;
                    if (isBlockCheckout) {
                        // Block checkout - don't interfere
                        jQuery(".wc-block-components-checkout-place-order-button").trigger("click");

                    } else {
                        // Legacy checkout
                        // Double check nonce is present before submission
                        if (!pay_form.find('input[name="square_pay_nonce"]').length && square_params.square_pay_nonce) {
                            pay_form.append('<input type="hidden" name="square_pay_nonce" value="' + square_params.square_pay_nonce + '" />');
                        }

                        pay_form.submit();
                    }
                }
            } else {
                let errorMessage = `Tokenization failed-status: ${tokenResult.status}`;
                if (tokenResult.errors) {
                    errorMessage += ` and errors: ${JSON.stringify(tokenResult.errors)}`;
                }
                if (cardButton) cardButton.disabled = false;
                jQuery('#place_order').prop('disabled', false);

                // Show error to user
                const errorHtml = '<ul class="woocommerce-error" role="alert"><li>' + errorMessage + '</li></ul>';
                jQuery('.woocommerce-checkout').prepend(errorHtml);
                jQuery('html, body').animate({ scrollTop: 0 }, 500);

                throw new Error(errorMessage);
            }
        } catch (error) {
            if (cardButton) cardButton.disabled = false;
            jQuery('#place_order').prop('disabled', false);

            // Show error to user
            let userErrorMessage = 'Payment processing failed. Please check your card details and try again.';
            if (error.message) {
                userErrorMessage = error.message;
            }
            const errorHtml = '<ul class="woocommerce-error" role="alert"><li>' + userErrorMessage + '</li></ul>';
            jQuery('.woocommerce-checkout').prepend(errorHtml);
            jQuery('html, body').animate({ scrollTop: 0 }, 500);
        }

    }



    // document.addEventListener('DOMContentLoaded', async function () {
    jQuery(window).on("load", function () {


        if (!window.Square) {
            console.error('âŒ [Window Load] Square.js not loaded');
            throw new Error('Square.js failed to load properly');
        }

        payments = window.Square.payments(appId, location_id);


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

        function submission_isblock(isSquare, event, payments) {
            jQuery('.woocommerce-error').remove();
            if (cardButton) {
                cardButton.disabled = true;
            }

            // Re-check isSquare at click time (caller may have passed stale value).
            // Block checkout uses radio-control-wc-payment-method-options; legacy uses payment_method / .input-radio.
            var expectedSquarePayment = 'square_plus' + square_params.sandbox;
            if (!isSquare) {
                var selectedPayment = jQuery('input[name="radio-control-wc-payment-method-options"]:checked').val() ||
                    jQuery('input[name="payment_method"]:checked').val() ||
                    jQuery('.woocommerce-checkout-payment .input-radio:checked').val() ||
                    jQuery('.woocommerce-PaymentMethod .input-radio:checked').val();
                isSquare = selectedPayment === expectedSquarePayment;
            }

            const hasCardContainer = jQuery('.wooSquare-checkout ' + cardcontainer).length > 0 || jQuery(cardcontainer).length > 0;

            if (isSquare && hasCardContainer) {
                const isBlockCheckout = jQuery('.wc-block-checkout').length > 0;
                const isLegacyCheckout = jQuery('form.woocommerce-checkout').length > 0 && !isBlockCheckout;

                // Check if card is initialized
                if (typeof card === 'undefined' || card === null) {
                    if (cardButton) cardButton.disabled = false;
                    return;
                }

                if (!jQuery('.saved_cards_squ').is(":checked") &&
                    !jQuery('#sq-card-saved').is(":checked") &&
                    !square_params.subscription &&
                    jQuery('#wfacp_checkout_form').html() == undefined &&
                    !jQuery('._wcf_checkout_id').val() && !jQuery('#add_payment_method').length > 0) {

                    // for legacy checkout
                    if (event) {
                        handlePaymentMethodSubmissioncc(event, card, true, payments);
                    } else {
                        // Create a synthetic event if not provided
                        const syntheticEvent = { preventDefault: function () { } };
                        handlePaymentMethodSubmissioncc(syntheticEvent, card, true, payments);
                    }
                } else if (jQuery('.saved_cards_squ').is(":checked")) {
                    if (event) {
                        handlePaymentWithCardOnFileMethodSubmission(event, cardId, customerId);
                    }
                } else if (jQuery('#sq-card-saved').is(":checked") || square_params.subscription == 1 || jQuery('#wfacp_checkout_form').html() != undefined || jQuery('._wcf_checkout_id').val() || jQuery('#add_payment_method').length > 0 || jQuery('.wooSquare-checkout').length > 0) {
                    if (event) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    handleStoreCardMethodSubmission(payments, card);
                }
            } else {
                if (cardButton) cardButton.disabled = false;
            }

        }

        function checkSquareCondition() {
            let val1 = $("input[name=radio-control-wc-payment-method-options]:checked").val();
            let val2 = $(".woocommerce-checkout-payment .input-radio:checked").val();
            let isAddPaymentForm = $('#add_payment_method').length > 0;

            // Only initialize if Square is actually selected, or if it's the add payment method page
            let finalResult = (
                (val1 == 'square_plus' + square_params.sandbox ||
                    val2 == 'square_plus' + square_params.sandbox) ||
                isAddPaymentForm
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

            // Helper function to check if container is visible and ready
            function isContainerReady() {
                const $container = jQuery(cardcontainer);
                if ($container.length === 0) {
                    return false;
                }

                // Check if container is visible (not hidden by display:none or visibility:hidden)
                const containerElement = $container[0];
                if (containerElement) {
                    const style = window.getComputedStyle(containerElement);
                    const isVisible = style.display !== 'none' &&
                        style.visibility !== 'hidden' &&
                        style.opacity !== '0';

                    // For block checkout, also check if parent payment method panel is visible
                    if (jQuery('.wc-block-checkout').length > 0) {
                        const $paymentPanel = $container.closest('.wc-block-components-payment-method');
                        if ($paymentPanel.length > 0) {
                            const panelStyle = window.getComputedStyle($paymentPanel[0]);
                            const panelVisible = panelStyle.display !== 'none' &&
                                panelStyle.visibility !== 'hidden';
                            return isVisible && panelVisible;
                        }
                    }

                    return isVisible;
                }
                return false;
            }

            // Function to initialize card with retry mechanism
            function initializeCardWithRetry(maxRetries = 10, delay = 300) {
                let attempts = 0;

                const tryInitialize = () => {
                    // Avoid parallel init
                    if (isInitializing || cardPromise) {
                        return;
                    }
                    attempts++;

                    if (isContainerReady()) {
                        // Container is ready, initialize
                        initializeCard(payments).then(function (returnedCard) {
                            card = returnedCard;
                        }).catch(function (e) {
                            console.error('Initializing Card failed:', e);
                            jQuery('#card-initialization').hide();
                        });
                        return;
                    } else if (attempts < maxRetries) {
                        // Container not ready yet, retry
                        setTimeout(tryInitialize, delay);
                    } else {
                        // Max retries reached, try anyway
                        initializeCard(payments).then(function (returnedCard) {
                            card = returnedCard;
                        }).catch(function (e) {
                            console.error('Initializing Card failed after retries:', e);
                            jQuery('#card-initialization').hide();
                        });
                    }
                };

                tryInitialize();
            }

            let debounceTimeout;

            function handleCardInitDebounced(e) {
                const target = jQuery(e.target);
                const value = target.val();
                const isSquare = jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() === 'square_plus' + square_params.sandbox;
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
                                // Check if container is ready before initializing
                                jQuery('#card-initialization').show();
                                initializeCard(payments).then(function (returnedCard) {
                                    card = returnedCard;
                                }).catch(function (e) {
                                    console.error('Initializing Card failed:', e);
                                    jQuery('#card-initialization').hide();
                                });
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

            // Listen for when payment method content becomes visible in block checkout
            if (jQuery('.wc-block-checkout').length > 0) {
                // Use MutationObserver to watch for when payment method panel becomes visible
                const observer = new MutationObserver(function (mutations) {
                    const selectedPayment = jQuery("input[name=radio-control-wc-payment-method-options]:checked").val();
                    if (selectedPayment === 'square_plus' + square_params.sandbox) {
                        const $container = jQuery(cardcontainer);
                        const hasIframe = $container.find('iframe.sq-card-component').length > 0;

                        if (isContainerReady() && !hasIframe && (typeof card === 'undefined' || card === null)) {
                            // Container just became visible and card not initialized
                            try {
                                jQuery('#card-initialization').show();
                                initializeCard(payments).then(function (returnedCard) {
                                    card = returnedCard;
                                }).catch(function (e) {
                                    console.error('Initializing Card failed on visibility change:', e);
                                    jQuery('#card-initialization').hide();
                                });
                            } catch (e) {
                                jQuery('#card-initialization').hide();
                                console.error('Initializing Card failed on visibility change:', e);
                            }
                        }
                    }
                });

                // Observe the checkout form for changes
                const checkoutForm = document.querySelector('form.wc-block-checkout__form');
                if (checkoutForm) {
                    observer.observe(checkoutForm, {
                        childList: true,
                        subtree: true,
                        attributes: true,
                        attributeFilter: ['style', 'class']
                    });
                }
            }

            // Only initialize if Square is actually selected, or if it's the add payment method page
            if ((jQuery("input[name=radio-control-wc-payment-method-options]:checked").val() == 'square_plus' + square_params.sandbox ||
                jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_plus' + square_params.sandbox) ||
                jQuery('#add_payment_method').length > 0 // Check if form with id "add_payment_method" exists
            ) {
                // Condition met - initialize card with retry mechanism for block checkout
                if (jQuery('.wc-block-checkout').length > 0) {
                    // Block checkout - use retry mechanism
                    initializeCardWithRetry();
                } else {
                    // Legacy checkout - initialize immediately
                    jQuery('#card-initialization').show();
                    initializeCard(payments).then(function (returnedCard) {
                        card = returnedCard;
                    }).catch(function (e) {
                        if (e) console.warn('Legacy checkout initialization suppressed error:', e.message || e);
                        jQuery('#card-initialization').hide();
                    });
                }

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
            if (jQuery('.wc-block-checkout').length > 0) {
                const buttons = document.getElementsByClassName('wc-block-components-checkout-place-order-button');
                if (buttons.length > 0) {
                    cardButton = buttons[0];
                }
                if (cardButton) {
                    cardButton.addEventListener('click', async function (event) {
                        // WSSS-404: do not reuse a nonce after a failed payment attempt.
                        if (shouldReuseSquareNonce()) {
                            return true;
                        }
                        // Use current selection at click time (Block: radio-control-wc-payment-method-options)
                        var isSquareNow = jQuery('input[name="radio-control-wc-payment-method-options"]:checked').val() === 'square_plus' + square_params.sandbox;
                        submission_isblock(isSquareNow, event, payments);
                    });
                }
            } else {
                cardButton = document.getElementById('place_order');
                if (cardButton) {
                    jQuery(document).on('click', '#' + jQuery(cardButton).attr('id'), function (event) {
                        // Legacy checkout
                        let selectedPayment = jQuery('input[name="payment_method"]:checked').val() ||
                            jQuery('.woocommerce-checkout-payment .input-radio:checked').val();

                        const expectedSquarePayment = 'square_plus' + square_params.sandbox;
                        const isSquareSelected = selectedPayment === expectedSquarePayment;

                        // Ensure square_pay_nonce is in the form before submission
                        const pay_form = jQuery('form.woocommerce-checkout, form#order_review');
                        if (!pay_form.find('input[name="square_pay_nonce"]').length && square_params.square_pay_nonce) {
                            pay_form.append('<input type="hidden" name="square_pay_nonce" value="' + square_params.square_pay_nonce + '" />');
                        }

                        // WSSS-404: do not reuse a nonce after a failed payment attempt.
                        if (shouldReuseSquareNonce()) {
                            return true;
                        }

                        // Only process if Square payment is selected
                        if (!isSquareSelected) {
                            return true;
                        }

                        submission_isblock(isSquareSelected, event, payments);
                    });
                }
            }
        }

        const intervalId = setInterval(function () {
            const $placeOrderBtn = jQuery('.wc-block-components-checkout-place-order-button, #place_order');
            const $checkoutForm = jQuery('form.checkout');

            if ($placeOrderBtn.length > 0 || $checkoutForm.length > 0) {
                clearInterval(intervalId);
                checkSquareCondition();

                // Legacy checkout fallback
                const $cardContainer = jQuery(cardcontainer);
                const hasIframe = $cardContainer.find('iframe.sq-card-component').length > 0;
                const $selectedPayment = jQuery('.woocommerce-checkout-payment .input-radio:checked').val();
                if ($selectedPayment === 'square_plus' + square_params.sandbox &&
                    $cardContainer.length > 0 &&
                    !hasIframe &&
                    (typeof card === 'undefined' || card === null)) {
                    setTimeout(function () {
                        if (typeof payments !== 'undefined' && payments !== null) {
                            jQuery('#card-initialization').show();
                            initializeCard(payments).then(function (returnedCard) {
                                card = returnedCard;
                            }).catch(function (e) {
                                if (e) console.warn('Legacy fallback initialization suppressed error:', e.message || e);
                                jQuery('#card-initialization').hide();
                            });
                        }
                    }, 1000);
                }
            }
        }, 500);

        // Elementor / late-loading checkout: when #card-container_payment appears after load, init card (client Elementor fix)
        function tryInitCardIfContainerReady() {
            var $cont = jQuery(cardcontainer);
            if (!$cont.length || $cont.find('iframe.sq-card-component').length > 0) return;
            if (typeof card !== 'undefined' && card !== null) return;
            if (typeof payments === 'undefined' || !payments) return;
            var isSquare = jQuery('input[name=radio-control-wc-payment-method-options]:checked').val() === 'square_plus' + square_params.sandbox ||
                jQuery('.woocommerce-checkout-payment .input-radio:checked').val() === 'square_plus' + square_params.sandbox;
            var hasCheckout = jQuery('.wooSquare-checkout').length > 0 || jQuery('#add_payment_method').length > 0;
            if (!isSquare && !hasCheckout) return;
            var style = $cont[0] && window.getComputedStyle($cont[0]);
            if (style && style.display === 'none') return;
            jQuery('#card-initialization').show();
            initializeCard(payments).then(function (returnedCard) {
                card = returnedCard;
            }).catch(function (e) {
                jQuery('#card-initialization').hide();
            });
        }
        var lateInitAttempts = 0;
        var lateInitInterval = setInterval(function () {
            lateInitAttempts++;
            if (jQuery(cardcontainer).length && !jQuery(cardcontainer).find('iframe.sq-card-component').length && (typeof card === 'undefined' || card === null)) {
                tryInitCardIfContainerReady();
            }
            if (lateInitAttempts >= 15) clearInterval(lateInitInterval);
        }, 1000);
        if (document.body) {
            var lateObserver = new MutationObserver(function () {
                if (jQuery(cardcontainer).length && !jQuery(cardcontainer).find('iframe.sq-card-component').length && (typeof card === 'undefined' || card === null)) {
                    setTimeout(tryInitCardIfContainerReady, 300);
                }
            });
            lateObserver.observe(document.body, { childList: true, subtree: true });
        }

        // Ensure square_pay_nonce is present when checkout is updated via AJAX
        jQuery(document.body).on('updated_checkout', function () {
            if (jQuery('.wc-block-checkout').length === 0) {
                const pay_form = jQuery('form.woocommerce-checkout, form#order_review');
                const isSquareSelected = jQuery('.woocommerce-checkout-payment .input-radio:checked').val() === 'square_plus' + square_params.sandbox;
                if (isSquareSelected && pay_form.length > 0) {
                    if (!pay_form.find('input[name="square_pay_nonce"]').length && square_params.square_pay_nonce) {
                        pay_form.append('<input type="hidden" name="square_pay_nonce" value="' + square_params.square_pay_nonce + '" />');
                    }
                }
            }
        });

        // WSSS-404: clear consumed Square card nonce when checkout payment fails (classic AJAX).
        jQuery(document.body).on('checkout_error', function () {
            clearSquareCardPaymentTokens();
        });
        jQuery(document).ajaxComplete(function (event, xhr, settings) {
            if (!settings || !settings.url || settings.url.indexOf('wc-ajax=checkout') === -1) {
                return;
            }
            try {
                var data = typeof xhr.responseJSON !== 'undefined' ? xhr.responseJSON : JSON.parse(xhr.responseText);
                if (data && data.result === 'failure') {
                    clearSquareCardPaymentTokens();
                }
            } catch (e) {
                // ignore non-JSON responses
            }
        });

        jQuery(document).on('click', '.new_cards_squ', function () {
            jQuery('.wooSquare-checkout').show();
            initializeCard(payments).then(function (returnedCard) {
                card = returnedCard;
            }).catch(function (e) {
                console.error('Initializing Card failed', e);
            });
        });
    });

    jQuery(window).on("load", function () {
        setTimeout(function () {
            hideunhide();
        }, 600);
        jQuery('form.wc-block-checkout__form').on('change', "input[name=radio-control-wc-payment-method-options]", function () {
            hideunhide();
        });

        if (typeof wcSettings !== 'undefined' && wcSettings.customerPaymentMethods && Array.isArray(wcSettings.customerPaymentMethods.cc) && wcSettings.customerPaymentMethods.cc.length > 0) {
            const methods = wcSettings.customerPaymentMethods.cc;
            const defaultMethod = methods.find(method => method.is_default === true);
            if (defaultMethod) {
                const tokenId = defaultMethod.tokenId;
                const radioButtons = document.querySelectorAll('input[type="radio"][name="radio-control-wc-payment-method-saved-tokens"]');
                radioButtons.forEach(radio => { radio.checked = false; });
                const input = document.querySelector(`input[type="radio"][value="${tokenId}"]`);
                if (input) { input.checked = true; }
            }
        }
    });

    jQuery(function ($) {
        const initialPayment = jQuery('.woocommerce-checkout-payment .input-radio:checked').val();
        if (initialPayment) {
            previousPaymentMethod = initialPayment;
        }

        $('form.checkout').on('change', '.woocommerce-checkout-payment input', function () {
            const newPaymentMethod = jQuery(this).val();
            previousPaymentMethod = newPaymentMethod;
            hideunhide();

            if (jQuery(this).attr('type') === 'radio' && jQuery(this).val() === 'square_plus' + square_params.sandbox) {
                const $cardContainer = jQuery(cardcontainer);
                if ($cardContainer.length > 0) {
                    const hasIframe = $cardContainer.find('iframe.sq-card-component').length > 0;
                    if (!hasIframe && (typeof card === 'undefined' || card === null)) {
                        setTimeout(function () {
                            if (typeof payments !== 'undefined' && payments !== null) {
                                jQuery('#card-initialization').show();
                                initializeCard(payments).then(function (returnedCard) {
                                    card = returnedCard;
                                });
                            }
                        }, 300);
                    }
                }
            }
        });

        jQuery(document.body).on('updated_checkout', function () {
            const selectedPayment = jQuery('.woocommerce-checkout-payment .input-radio:checked').val();
            const squarePaymentValue = 'square_plus' + square_params.sandbox;

            if (previousPaymentMethod === squarePaymentValue && selectedPayment === 'ppcp-gateway') {
                const $squareRadio = jQuery('.woocommerce-checkout-payment .input-radio[value="' + squarePaymentValue + '"]');
                if ($squareRadio.length > 0) {
                    $squareRadio.prop('checked', true).trigger('change');
                    previousPaymentMethod = squarePaymentValue;
                    return;
                }
            }

            previousPaymentMethod = selectedPayment;

            if (selectedPayment === squarePaymentValue) {
                const $cardContainer = jQuery(cardcontainer);
                const hasIframe = $cardContainer.find('iframe.sq-card-component').length > 0;
                const containerIsEmpty = $cardContainer.html().trim() === '';

                if ($cardContainer.length > 0 && (!hasIframe || containerIsEmpty)) {
                    if (containerIsEmpty) card = null;
                    setTimeout(function () {
                        if (typeof payments !== 'undefined' && payments !== null) {
                            jQuery('#card-initialization').show();
                            initializeCard(payments).then(function (returnedCard) {
                                card = returnedCard;
                            });
                        }
                    }, 500);
                }
            }
        });

        jQuery(document).ajaxError(function (event, jqXHR, ajaxSettings, thrownError) {
            if (jqXHR.status === 401) {
                console.error('âŒ [Security] 401 Unauthorized detected:', ajaxSettings.url);
            }
        });
    });

    function hideunhide() {
        const val = jQuery('.woocommerce-checkout-payment .input-radio:checked').val() ||
            jQuery("input[name=radio-control-wc-payment-method-options]:checked").val();
        const sqBase = 'square_plus' + square_params.sandbox;
        const gPay = 'square_google_pay' + square_params.sandbox;
        const aPay = 'square_apple_pay' + square_params.sandbox;
        const ach = 'square_ach_payment' + square_params.sandbox;
        const afterpay = 'square_after_pay' + square_params.sandbox;
        const cashapp = 'square_cash_app_pay' + square_params.sandbox;

        if (val === sqBase) {
            jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'flex');
        } else if ([gPay, aPay, ach, afterpay, cashapp].indexOf(val) !== -1) {
            jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'none');
        } else {
            jQuery('#place_order, .wc-block-components-checkout-place-order-button').css('display', 'flex');
        }
    }

})(jQuery);