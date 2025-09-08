import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { registerBlockType } from '@wordpress/blocks';
import { Icon, box } from '@wordpress/icons';
import { Edit, Save } from './edit';
import metadata from './block.json';
import * as React from 'react';
import { useEffect, useCallback, useState } from '@wordpress/element';
import { CreditCard, PaymentForm } from 'react-square-web-payments-sdk';
import './frontend';
// import './blockData';
const settings = square_index_params.woocommerce_square_gift_card_pay_enabled;
if (settings) {
    registerBlockType(
        metadata, {
            icon: {
                src: <Icon icon={ box } />, 
            },
            edit: Edit,
            save: Save,
        } 
    );
}
export const SquareCreditCard = ( props ) => {
    return (
    <div dangerouslySetInnerHTML={{ __html: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).saved_cards }} />
        
    );
};
export const SquareACH = ( props ) => {

    return (
        <div  id="ach-payment-form">
            <div  id="ach-initialization" class="method-initialization">Initializing...</div>
            <div class = "ach-button-div"></div>
            <input type="hidden" id="card_nonce" name="card_nonce" />
        </div>
    );
};
export const SquareGooglePay = ( props ) => {

    return (
    <div id="google-payment-form">
    <div  id="googlepay-initialization" class="method-initialization">Initializing...</div>
    <div id="google-pay-button"></div>
    </div>
    );
};
export const SquareApplePay = ( props ) => {

    return (
    <div id="apple-payment-form">
    <div id="apple-pay-button"></div>
    <span id="browser_support_msg"></span>
    </div>
    );
};
export const SquareAfterPay = ( props ) => {

    return (
    <div id="payment-form">
    <div  id="afterpay-initialization" class="method-initialization">Initializing...</div>
    <div id="afterpay-button"></div>
    </div>
    );
};
export const SquareCashApp = ( props ) => {

    return (
    <div id="payment-form">
    <div  id="cashapp-initialization" class="method-initialization">Initializing...</div>
    <div id="cash-app-pay"></div>
    </div>
    );
};
export const SquarePOS = ( props ) => {
    return (
    <div dangerouslySetInnerHTML={{ __html: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).terminal_button }} />
    );
};
const Content = ({RenderedComponent,  ...props}) => {
    
    const { eventRegistration, emitResponse } = props;
    const { onPaymentSetup } = eventRegistration;
    useEffect(
        () => {
        const unsubscribe = onPaymentSetup(
                async() => {
                // Here we can do any processing we need, and then emit a response.
                    // For example, we might validate a custom field, or perform an AJAX request, and then emit a response indicating it is valid or not.
                    const square_nonce = jQuery('.square-nonce').val();
                const square_customerId = jQuery('.square-customerId').val() || '';
                const term_checkout_id = jQuery('.term_checkout_id').val() || '';
                const saved_cards = jQuery('#saved_cards').val() || '';
                const buyerVerification_token = jQuery('.buyerVerification-token').val() || '';
                const funnel_order = jQuery('.funnel_order').val() || '';
                const _wcf_flow_id = jQuery('._wcf_flow_id').val() || '';
                const _wcf_checkout_id = jQuery('._wcf_checkout_id').val() || '';
                const square_pay_nonce = square_index_params.square_pay_nonce;
                const customDataIsValid = square_nonce ? !! square_nonce.length : false;
                const customTerminalDataIsValid = term_checkout_id ? !! term_checkout_id.length : false;
                if (customDataIsValid || customTerminalDataIsValid) {
                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        meta: {
                            paymentMethodData: {
                                square_nonce,
                                square_customerId,
                                saved_cards,
                                buyerVerification_token,
                                square_pay_nonce,
                                funnel_order,
                                _wcf_checkout_id,
                                _wcf_flow_id,
                                term_checkout_id,
                            }
                        }
                    };
                }
                return {
                        type: emitResponse.responseTypes.ERROR,
                        message: 'There was an error',
                };
                } 
            );
        // Unsubscribes when this component is unmounted.
        return () => {
        unsubscribe();
        };
        }, [
        emitResponse.responseTypes.ERROR,
        emitResponse.responseTypes.SUCCESS,
        onPaymentSetup,
        ] 
    );
    // return decodeEntities( settings.description || '' );
    return <RenderedComponent square={ SquareCreditCard } { ...props } />;
};
// export default MyPaymentForm;
const woosquarePaymentMethod = {
    name: square_index_params.method_name,
    label: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).method_title,
    content: <Content RenderedComponent={ SquareCreditCard } />,
    edit: <div></div>,
    canMakePayment: () => true,
    ariaLabel: 'Square Credit Card payment method',
    paymentMethodId: square_index_params.method_name,
    supports: {
        features: undefined,
    },
};

const woosquareGooglePaymentMethod = {
    name: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_google_pay_id,
    label: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).google_method_title,
    content: <Content RenderedComponent={ SquareGooglePay } />,
    edit: <div></div>,
    canMakePayment: () => true,
    ariaLabel: 'Square Google Pay payment method',
    paymentMethodId: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_google_pay_id,
    supports: {
        features: undefined,
    },
};

const woosquareApplePaymentMethod = {
    name: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_apple_pay_id,
    label: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).apple_method_title,
    content: <Content RenderedComponent={ SquareApplePay } />,
    edit: <div></div>,
    canMakePayment: () => true,
    ariaLabel: 'Square Apple Pay payment method',
    paymentMethodId: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_apple_pay_id,
    supports: {
        features: undefined,
    },
};

const woosquareACHPaymentMethod = {
    name: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_ach_pay_id,
    label: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).ach_method_title,
    content: <Content RenderedComponent={ SquareACH } />,
    edit: <div></div>,
    canMakePayment: () => true,
    ariaLabel: 'Square ACH payment method',
    paymentMethodId: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_ach_pay_id,
    supports: {
        features: undefined,
    },
};

const woosquareAfterPaymentMethod = {
    name: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_after_pay_id,
    label: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).afterpay_method_title,
    content: <Content RenderedComponent={ SquareAfterPay } />,
    edit: <div></div>,
    canMakePayment: () => true,
    ariaLabel: 'Square AfterPay payment method',
    paymentMethodId: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_after_pay_id,
    supports: {
        features: undefined,
    },
};

const woosquareCashAppPaymentMethod = {
    name: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_cash_app_id,
    label: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).cashapp_method_title,
    content: <Content RenderedComponent={ SquareCashApp } />,
    edit: <div></div>,
    canMakePayment: () => true,
    ariaLabel: 'Square CashApp payment method',
    paymentMethodId: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_cash_app_id,
    supports: {
        features: undefined,
    },
};

const woosquarePOSPaymentMethod = {
    name: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_pos_id,
    label: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).pos_method_title,
    content: <Content RenderedComponent={ SquarePOS } />,
    edit: <div></div>,
    canMakePayment: () => true,
    ariaLabel: 'Square POS payment method',
    paymentMethodId: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_pos_id,
    supports: {
        features: undefined,
    },
};

registerPaymentMethod(woosquarePaymentMethod);
if(wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_ach_pay_enabled) {
    registerPaymentMethod(woosquareACHPaymentMethod);
}
if(wc.wcSettings.getPaymentMethodData(square_index_params.method_name).google_method_enabled) {
    registerPaymentMethod(woosquareGooglePaymentMethod);
}
if(wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_apple_pay_enabled) {
    registerPaymentMethod(woosquareApplePaymentMethod);
}
if(wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_after_pay_enabled) {
    registerPaymentMethod(woosquareAfterPaymentMethod);
}
if(wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_cash_app_enabled) {
    registerPaymentMethod(woosquareCashAppPaymentMethod);
}
if(wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_pos_enabled) {
    registerPaymentMethod(woosquarePOSPaymentMethod);
}
