<?php
/**
 * WooSquare Plus Gateway Recurring Renew
 *
 * This file contains the WooSquare_Plus_Gateway_Recurring_Renew class, which handles the recurring payment renewals for WooSquare Plus.
 *
 * @package WooSquarePlus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooSquare_Plus_Gateway_Recurring_Renew Class
 *
 * Handles the recurring payment renewals for WooSquare Plus.
 *
 * @package WooSquarePlus
 */
class WooSquare_Plus_Gateway_Recurring_Renew extends WooSquare_Plus_Gateway {


	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
		}
	}

	/**
	 * Scheduled subscription payment function.
	 *
	 * Processes the payment for a subscription renewal order.
	 *
	 * @param float    $amount_to_charge The amount to charge.
	 * @param WC_Order $renewal_order    A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$renewal_order_id = $renewal_order->get_id();
		$renewal_order    = wc_get_order( $renewal_order_id );
		$token            = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$location_id      = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		try {
			// get subscription.
			if ( wcs_order_contains_subscription( $renewal_order_id, array( 'parent', 'renewal', 'switch' ) ) ) {
				$subscriptions = wcs_get_subscriptions_for_order( $renewal_order_id, array( 'order_type' => array( 'parent', 'renewal', 'switch' ) ) );
				// get parent order.
				$parent_order_id = null;
				$parent_order    = null;
				foreach ( $subscriptions as $subscription ) {
					if ( $subscription->get_parent_id() ) {
						$parent_order = $subscription->get_parent();
					}
				}

				if ( $parent_order ) {
					// shipping address.
					$shipping_address = array(
						'address_line_1'                  => $renewal_order->get_shipping_address_1() ? $renewal_order->get_shipping_address_1() : $renewal_order->get_billing_address_1(),
						'address_line_2'                  => $renewal_order->get_shipping_address_2() ? $renewal_order->get_shipping_address_2() : $renewal_order->get_billing_address_2(),
						'locality'                        => $renewal_order->get_shipping_city() ? $renewal_order->get_shipping_city() : $renewal_order->get_billing_city(),
						'administrative_district_level_1' => $renewal_order->get_shipping_state() ? $renewal_order->get_shipping_state() : $renewal_order->get_billing_state(),
						'postal_code'                     => $renewal_order->get_shipping_postcode() ? $renewal_order->get_shipping_postcode() : $renewal_order->get_billing_postcode(),
						'country'                         => $renewal_order->get_shipping_country() ? $renewal_order->get_shipping_country() : $renewal_order->get_billing_country(),
					);

					// billing address.
					$billing_address = array(
						'address_line_1'                  => $renewal_order->get_billing_address_1(),
						'address_line_2'                  => $renewal_order->get_billing_address_2(),
						'locality'                        => $renewal_order->get_billing_city(),
						'administrative_district_level_1' => $renewal_order->get_billing_state(),
						'postal_code'                     => $renewal_order->get_billing_postcode(),
						'country'                         => $renewal_order->get_billing_country() ? $renewal_order->get_billing_country() : $renewal_order->get_shipping_country(),
					);

					$parent_order_id    = $parent_order->get_id();
					$currency           = $parent_order->get_currency();
					$customer_card_id   = $parent_order->get_meta( '_woos_plus_customer_card_id', true );
					$square_customer_id = null;
					$customer_id        = $parent_order->get_customer_id();

					if ( empty( $square_customer_id ) ) {
						$square_customer_id = get_user_meta( $customer_id, '_square_customer_id', true );
					}

					if ( empty( $square_customer_id ) ) {
						$square_customer_id = $parent_order->get_meta( '_square_customer_id', true );
					}
					if ( empty( $square_customer_id ) ) {
						$square_customer_id = get_post_meta( $parent_order_id, '_woos_plus_customer_id', true );
					}
					$parent_order->save();
					if ( ! empty( $customer_id ) ) {
						$default_customer_card_obj = WC_Payment_Tokens::get_customer_default_token( $customer_id );
					}
					if ( ! is_null( $default_customer_card_obj ) ) {
						$customer_card_id = $default_customer_card_obj->get_token();
					}
					if ( empty( $square_customer_id ) ) {
						// getting from square.
						$response = wp_remote_post(
							'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers/search',
							array(
								'method'  => 'POST',
								'headers' => array(
									'Authorization'  => 'Bearer ' . $token,
									'Square-Version' => '2025-07-16',
									'Content-Type'   => 'application/json',
								),
								'body'    => wp_json_encode(
									array(
										'query' => array(
											'filter' => array(
												'email_address' => array(
													'exact' => $renewal_order->get_billing_email(),
												),
											),
										),
									)
								),
								'timeout' => 20,
							)
						);

						// Handle response.
						if ( ! is_wp_error( $response ) ) {
							$body               = wp_remote_retrieve_body( $response );
							$data               = json_decode( $body, true );
							$square_customer_id = $data['customers'][0]['id'];
						}
					}
					if ( $square_customer_id && $customer_card_id ) {

						$idempotency_key = (string) $renewal_order_id;

						$fields = array(
							'idempotency_key'  => $idempotency_key,
							'location_id'      => $location_id,
							'amount_money'     => array(
								'amount'   => (int) $this->format_amount( $amount_to_charge, $currency ),
								'currency' => $currency,
							),
							'source_id'        => $customer_card_id,
							'customer_id'      => $square_customer_id,
							'shipping_address' => $shipping_address,
							'billing_address'  => $billing_address,
							'reference_id'     => (string) $renewal_order->get_order_number(),
							'note'             => 'Order #' . (string) $renewal_order->get_order_number(),
						);

						$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/payments';

						$headers = array(
							'Accept'         => 'application/json',
							'Authorization'  => 'Bearer ' . $token,
							'Square-Version' => '2021-11-17',
							'Content-Type'   => 'application/json',
							'Cache-Control'  => 'no-cache',
						);

						$transaction_data = json_decode(
							wp_remote_retrieve_body(
								wp_remote_post(
									$url,
									array(
										'method'      => 'POST',
										'headers'     => $headers,
										'httpversion' => '1.0',
										'sslverify'   => false,
										'body'        => wp_json_encode( $fields ),
									)
								)
							)
						);

						if ( isset( $transaction_data->payment->id ) && 'CAPTURED' === $transaction_data->payment->card_details->status ) {
									$transaction_id = $transaction_data->payment->id;
									$renewal_order->add_meta_data( 'woosquare_transaction_id', $transaction_id );
									$renewal_order->add_meta_data( '_transaction_id', $transaction_id );
									$renewal_order->add_meta_data( 'woosquare_transaction_location_id', $location_id );
									// if sandbox enable add sandbox prefix.
									$sandbox_prefix = get_transient( 'is_sandbox' ) === 'sandbox' ? 'through sandbox' : '';
									// Mark as processing.
									// translators: %1$s is the prefix, %2$s is the transaction ID.
									$message = sprintf( __( 'Customer card successfully charged %1$s (Transaction ID: %2$s).', 'wcsrs-payment' ), $sandbox_prefix, $transaction_id );
									$renewal_order->update_status( 'processing', $message );
						} else {
							$renewal_order->add_order_note( 'Errors: ' . wp_json_encode( $transaction_data->errors ) . ' </br><a target="_blank" href="https://developer.squareup.com/docs/payments-api/error-codes#createpayment-errors"> ERROR CODE REFERENCES </a>' );
							$renewal_order->update_status( 'failed' );
						}
						$renewal_order->save();
					}
				}
			}
		} catch ( Exception $ex ) {
			$renewal_order->update_status( 'failed', $ex->getMessage() );
		}
	}
}

$instance = new WooSquare_Plus_Gateway_Recurring_Renew();
