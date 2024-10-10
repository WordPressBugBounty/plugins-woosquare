import { registerPaymentMethod } from '@woocommerce/blocks-registry';
// import { registerExpressPaymentMethod } from '@woocommerce/blocks-registry';
import * as React from 'react';
import { useEffect, useCallback, useState } from '@wordpress/element';
import { CreditCard, PaymentForm } from 'react-square-web-payments-sdk';
export const SquareCreditCard = ( props ) => {
	return (
		<div  id="payment-form">
		{square_index_params.description}
        <div  id="card-initialization" class="method-initialization">Initializing...</div>
            <div id="card-container"></div>
        </div>
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
const Content = ({RenderedComponent,  ...props}) => {
	
	const { eventRegistration, emitResponse } = props;
	const { onPaymentSetup } = eventRegistration;
	useEffect( () => {
		const unsubscribe = onPaymentSetup( async () => {
			// Here we can do any processing we need, and then emit a response.
			// For example, we might validate a custom field, or perform an AJAX request, and then emit a response indicating it is valid or not.
			const square_nonce = jQuery('.square-nonce').val();
			const buyerVerification_token = jQuery('.buyerVerification-token').val();
			const square_pay_nonce = square_index_params.square_pay_nonce;
			const customDataIsValid = !! square_nonce.length;
			if (customDataIsValid) {
				return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
					square_nonce,
					buyerVerification_token,
                    square_pay_nonce
					}
				}
				};
			}
			return {
				type: emitResponse.responseTypes.ERROR,
				message: 'There was an error',
			};
		} );
		// Unsubscribes when this component is unmounted.
		return () => {
			unsubscribe();
		};
	}, [
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS,
		onPaymentSetup,
	] );
	// return decodeEntities( settings.description || '' );
	return <RenderedComponent square={ SquareCreditCard } { ...props } />;
};
// export default MyPaymentForm;
const woosquarePaymentMethod = {
	name: square_index_params.method_name,
	label: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).method_title,
	content: <Content RenderedComponent={ SquareCreditCard } />,
	edit: <div>Hello</div>,
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
	edit: <div>Hello</div>,
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
	edit: <div>Hello</div>,
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
	edit: <div>Hello</div>,
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
	edit: <div>Hello</div>,
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
	edit: <div>Hello</div>,
	canMakePayment: () => true,
	ariaLabel: 'Square CashApp payment method',
	paymentMethodId: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_cash_app_id,
	supports: {
		features: undefined,
	},
};


registerPaymentMethod( woosquarePaymentMethod );
registerPaymentMethod( woosquareACHPaymentMethod );
registerPaymentMethod( woosquareGooglePaymentMethod );
registerPaymentMethod( woosquareApplePaymentMethod );
registerPaymentMethod( woosquareAfterPaymentMethod );
registerPaymentMethod( woosquareCashAppPaymentMethod );
