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

					// Validate initial card ID - reject nonces (cnon:)
					if ( ! empty( $customer_card_id ) && strpos( $customer_card_id, 'cnon:' ) === 0 ) {
						$customer_card_id = ''; // Clear nonce, will try other sources
					}

					if ( empty( $customer_id ) ) {
						$customer_id = $renewal_order->get_customer_id();
					}

					// Try alternative meta keys
					if ( empty( $customer_card_id ) ) {
						$card_id_from_source = $parent_order->get_meta( '_woos_plus_source_id', true );
						if ( ! empty( $card_id_from_source ) && strpos( $card_id_from_source, 'ccof:' ) === 0 ) {
							$customer_card_id = $card_id_from_source;
						}
					}

					if ( empty( $customer_card_id ) ) {
						$card_id_from_post = get_post_meta( $parent_order_id, '_woos_plus_customer_card_id', true );
						if ( ! empty( $card_id_from_post ) && strpos( $card_id_from_post, 'ccof:' ) === 0 ) {
							$customer_card_id = $card_id_from_post;
						}
					}

					if ( empty( $customer_card_id ) ) {
						$card_id_from_post_source = get_post_meta( $parent_order_id, '_woos_plus_source_id', true );
						if ( ! empty( $card_id_from_post_source ) && strpos( $card_id_from_post_source, 'ccof:' ) === 0 ) {
							$customer_card_id = $card_id_from_post_source;
						}
					}

					// Legacy compatibility: older recurring plugin stored card ID in _wcsr_square_customer_card_id.
					if ( empty( $customer_card_id ) ) {
						$legacy_card_id = $parent_order->get_meta( '_wcsr_square_customer_card_id', true );
						if ( ! empty( $legacy_card_id ) && strpos( $legacy_card_id, 'ccof:' ) === 0 ) {
							$customer_card_id = $legacy_card_id;
						}
					}

					if ( empty( $customer_card_id ) ) {
						$legacy_card_id_from_post = get_post_meta( $parent_order_id, '_wcsr_square_customer_card_id', true );
						if ( ! empty( $legacy_card_id_from_post ) && strpos( $legacy_card_id_from_post, 'ccof:' ) === 0 ) {
							$customer_card_id = $legacy_card_id_from_post;
						}
					}

					// Try to get card ID from renewal order
					if ( empty( $customer_card_id ) ) {
						$card_id_from_renewal = $renewal_order->get_meta( '_woos_plus_customer_card_id', true );
						if ( ! empty( $card_id_from_renewal ) && strpos( $card_id_from_renewal, 'ccof:' ) === 0 ) {
							$customer_card_id = $card_id_from_renewal;
						}
					}

					if ( empty( $customer_card_id ) ) {
						$card_id_from_renewal_source = $renewal_order->get_meta( '_woos_plus_source_id', true );
						if ( ! empty( $card_id_from_renewal_source ) && strpos( $card_id_from_renewal_source, 'ccof:' ) === 0 ) {
							$customer_card_id = $card_id_from_renewal_source;
						}
					}

					// Try to get card ID from subscription
					if ( empty( $customer_card_id ) ) {
						foreach ( $subscriptions as $subscription ) {
							$card_id_from_sub = $subscription->get_meta( '_woos_plus_customer_card_id', true );
							if ( ! empty( $card_id_from_sub ) && strpos( $card_id_from_sub, 'ccof:' ) === 0 ) {
								$customer_card_id = $card_id_from_sub;
								break;
							}
						}
					}

					// Try to get card ID from user meta
					if ( empty( $customer_card_id ) && ! empty( $customer_id ) ) {
						$card_id_from_user = get_user_meta( $customer_id, '_woos_plus_customer_card_id', true );
						if ( ! empty( $card_id_from_user ) && strpos( $card_id_from_user, 'ccof:' ) === 0 ) {
							$customer_card_id = $card_id_from_user;
						}
					}

					if ( empty( $square_customer_id ) ) {
						$square_customer_id = get_user_meta( $customer_id, '_square_customer_id', true );
					}

					if ( empty( $square_customer_id ) ) {
						$square_customer_id = $parent_order->get_meta( '_square_customer_id', true );
					}
					if ( empty( $square_customer_id ) ) {
						$square_customer_id = get_post_meta( $parent_order_id, '_woos_plus_customer_id', true );
					}

					// Try to get from renewal order
					if ( empty( $square_customer_id ) ) {
						$square_customer_id = $renewal_order->get_meta( '_square_customer_id', true );
					}

					// Try to get from subscription
					if ( empty( $square_customer_id ) ) {
						foreach ( $subscriptions as $subscription ) {
							$square_customer_id_from_sub = $subscription->get_meta( '_square_customer_id', true );
							if ( ! empty( $square_customer_id_from_sub ) ) {
								$square_customer_id = $square_customer_id_from_sub;
								break;
							}
						}
					}

					// Try to search by email from Square API
					if ( empty( $square_customer_id ) && ! empty( $token ) ) {
						$billing_email = $parent_order->get_billing_email();
						if ( ! empty( $billing_email ) ) {
							$search_url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers/search';
							$search_headers = array(
								'Accept'        => 'application/json',
								'Authorization' => 'Bearer ' . $token,
								'Content-Type'  => 'application/json',
								'Cache-Control' => 'no-cache',
							);

							$email                = strtolower( $billing_email );
							$customer_search_data = array(
								'query' => array(
									'filter' => array(
										'email_address' => array(
											'exact' => $email,
										),
									),
								),
							);

							$search_response = wp_remote_post(
								$search_url,
								array(
									'method'      => 'POST',
									'headers'     => $search_headers,
									'httpversion' => '1.0',
									'sslverify'   => false,
									'body'        => wp_json_encode( $customer_search_data ),
									'timeout'     => 20,
								)
							);

							if ( ! is_wp_error( $search_response ) ) {
								$search_body = wp_remote_retrieve_body( $search_response );
								$search_data = json_decode( $search_body, true );

								if ( ! empty( $search_data['customers'][0]['id'] ) ) {
									$square_customer_id = $search_data['customers'][0]['id'];
									// Save it for future use
									if ( ! empty( $customer_id ) ) {
										update_user_meta( $customer_id, '_square_customer_id', $square_customer_id );
									}
									$parent_order->update_meta_data( '_square_customer_id', $square_customer_id );
									$renewal_order->add_order_note(
										sprintf(
											// Translators: %s is the masked Square Customer ID.
											__( 'Square Customer ID found via email search: %s', 'woosquare' ),
											substr( $square_customer_id, 0, 8 ) . '****' . substr( $square_customer_id, -4 )
										)
									);
								}
							}
						}
					}

					$parent_order->save();

					// Try WooCommerce Payment Tokens
					if ( empty( $customer_card_id ) && ! empty( $customer_id ) ) {
						$gateway_id = $this->id;

						// First try to get default token for this gateway
						$default_customer_card_obj = WC_Payment_Tokens::get_customer_default_token( $customer_id, $gateway_id );

						// If no default token for this gateway, try without gateway filter
						if ( is_null( $default_customer_card_obj ) ) {
							$default_customer_card_obj = WC_Payment_Tokens::get_customer_default_token( $customer_id );
						}

						// If still no default token, get all tokens and filter for Square gateway
						if ( is_null( $default_customer_card_obj ) ) {
							$all_tokens = WC_Payment_Tokens::get_customer_tokens( $customer_id );

							// Filter for Square gateway tokens
							$square_tokens = array();
							foreach ( $all_tokens as $token_obj ) {
								$token_gateway_id = $token_obj->get_gateway_id();
								if ( strpos( $token_gateway_id, 'square' ) !== false ) {
									$square_tokens[] = $token_obj;
								}
							}

							if ( ! empty( $square_tokens ) ) {
								$default_customer_card_obj = reset( $square_tokens );
							} elseif ( ! empty( $all_tokens ) ) {
								$default_customer_card_obj = reset( $all_tokens );
							}
						}

						// Extract token value
						if ( ! is_null( $default_customer_card_obj ) ) {
							$token_value = $default_customer_card_obj->get_token();
							if ( ! empty( $token_value ) ) {
								// Validate: Only use card IDs (ccof:), reject nonces (cnon:).
								if ( strpos( $token_value, 'ccof:' ) === 0 ) {
									$customer_card_id = $token_value;
								} elseif ( strpos( $token_value, 'cnon:' ) === 0 ) {
									// Log that we found a nonce but need a card ID
									$renewal_order->add_order_note( 'WARNING: Payment token contains nonce (cnon:) instead of card ID (ccof:). Skipping token.' );
								}
							}
						}
					}

					// Final validation: Ensure card ID is valid (ccof:) not a nonce (cnon:)
					if ( ! empty( $customer_card_id ) && strpos( $customer_card_id, 'cnon:' ) === 0 ) {
						$renewal_order->add_order_note(
							sprintf(
								// translators: %s is the masked card ID value.
								__( 'ERROR: Invalid card ID format detected. Found nonce (cnon:) instead of card ID (ccof:). Value: %s', 'woosquare' ),
								substr( $customer_card_id, 0, 15 ) . '****'
							)
						);
						$customer_card_id = ''; // Clear invalid card ID
					}

					// Try parent payment customer as fallback only.
					// Do not override a valid, current mapped customer_id from user/subscription/order metadata.
					$parent_transaction_id = $parent_order->get_meta( 'woosquare_transaction_id', true );
					if ( empty( $parent_transaction_id ) ) {
						$parent_transaction_id = $parent_order->get_transaction_id();
					}
					if ( empty( $parent_transaction_id ) ) {
						$parent_transaction_id = $parent_order->get_meta( '_transaction_id', true );
					}
					if ( empty( $parent_transaction_id ) ) {
						$parent_transaction_id = $parent_order->get_meta( '_wcsr_square_transaction_id', true );
					}

					if ( ! empty( $parent_transaction_id ) && ! empty( $token ) ) {
						$payment_url      = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/payments/' . $parent_transaction_id;
						$payment_response = wp_remote_get(
							$payment_url,
							array(
								'method'  => 'GET',
								'headers' => array(
									'Authorization'  => 'Bearer ' . $token,
									'Square-Version' => '2021-11-17',
									'Accept'         => 'application/json',
								),
								'timeout' => 20,
							)
						);

						if ( ! is_wp_error( $payment_response ) ) {
							$payment_body = wp_remote_retrieve_body( $payment_response );
							$payment_data = json_decode( $payment_body, true );

							// Extract customer_id from payment response.
							// Keep current mapped ID unless it is empty.
							if ( isset( $payment_data['payment']['customer_id'] ) && ! empty( $payment_data['payment']['customer_id'] ) ) {
								$payment_customer_id = $payment_data['payment']['customer_id'];

								if ( empty( $square_customer_id ) ) {
									$square_customer_id = $payment_customer_id;
								}
							}
						}
					}

					// Last resort: Try to get card ID from parent order transaction/payment data
					if ( empty( $customer_card_id ) && ! empty( $square_customer_id ) ) {
						// Check if parent order has transaction ID and try to get card from Square payment
						$parent_transaction_id = $parent_order->get_meta( 'woosquare_transaction_id', true );
						if ( empty( $parent_transaction_id ) ) {
							$parent_transaction_id = $parent_order->get_transaction_id();
						}
						if ( empty( $parent_transaction_id ) ) {
							$parent_transaction_id = $parent_order->get_meta( '_transaction_id', true );
						}
						if ( empty( $parent_transaction_id ) ) {
							$parent_transaction_id = $parent_order->get_meta( '_wcsr_square_transaction_id', true );
						}

						// Try to get source_id from parent order payment meta
						$parent_payment_source = $parent_order->get_meta( '_payment_source_id', true );
						if ( ! empty( $parent_payment_source ) && strpos( $parent_payment_source, 'ccof:' ) === 0 ) {
							$customer_card_id = $parent_payment_source;
						}

						// Last resort: Fetch card ID from Square API using transaction ID
						if ( empty( $customer_card_id ) && ! empty( $parent_transaction_id ) && ! empty( $token ) ) {
							$payment_url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/payments/' . $parent_transaction_id;

							$payment_response = wp_remote_get(
								$payment_url,
								array(
									'method'  => 'GET',
									'headers' => array(
										'Authorization'  => 'Bearer ' . $token,
										'Square-Version' => '2021-11-17',
										'Accept'         => 'application/json',
									),
									'timeout' => 20,
								)
							);

							if ( ! is_wp_error( $payment_response ) ) {
								$payment_body = wp_remote_retrieve_body( $payment_response );
								$payment_data = json_decode( $payment_body, true );

								// Extract customer_id from parent payment response as fallback only.
								if ( isset( $payment_data['payment']['customer_id'] ) && ! empty( $payment_data['payment']['customer_id'] ) ) {
									$payment_customer_id = $payment_data['payment']['customer_id'];

									if ( empty( $square_customer_id ) ) {
										$square_customer_id = $payment_customer_id;
									}
								}

								// Extract card ID from payment response
								if ( isset( $payment_data['payment']['card_details']['card']['id'] ) ) {
									$card_id_from_response = $payment_data['payment']['card_details']['card']['id'];
									// Validate: Only use card IDs (ccof:), reject nonces (cnon:).
									if ( strpos( $card_id_from_response, 'ccof:' ) === 0 ) {
										$customer_card_id = $card_id_from_response;
									}
								} elseif ( isset( $payment_data['payment']['source_id'] ) ) {
									$source_id_from_response = $payment_data['payment']['source_id'];
									// Validate: Only use card IDs (ccof:), reject nonces (cnon:).
									if ( strpos( $source_id_from_response, 'ccof:' ) === 0 ) {
										$customer_card_id = $source_id_from_response;
									}
								}

								// If still no valid card ID, try fetching from customer cards
								if ( empty( $customer_card_id ) ) {
									// Payment was made with "ON_FILE" card, need to fetch customer's cards
									if ( ! empty( $square_customer_id ) ) {
										$cards_url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/cards?customer_id=' . $square_customer_id;

										$cards_response = wp_remote_get(
											$cards_url,
											array(
												'method'  => 'GET',
												'headers' => array(
													'Authorization'  => 'Bearer ' . $token,
													'Square-Version' => '2025-10-16',
													'Accept'         => 'application/json',
												),
												'timeout' => 20,
											)
										);

										if ( ! is_wp_error( $cards_response ) ) {
											$cards_body = wp_remote_retrieve_body( $cards_response );
											$cards_data = json_decode( $cards_body, true );

											// Get the first card or match by last_4 digits
											if ( isset( $cards_data['cards'] ) && ! empty( $cards_data['cards'] ) ) {
												$payment_last_4 = isset( $payment_data['payment']['card_details']['card']['last_4'] )
													? $payment_data['payment']['card_details']['card']['last_4']
													: null;

												// Try to match card by last_4, or use first card
												// Only use card IDs that start with 'ccof:' (reject nonces 'cnon:')
												// Only use enabled cards
												foreach ( $cards_data['cards'] as $card ) {
													if ( ! isset( $card['id'] ) || strpos( $card['id'], 'ccof:' ) !== 0 ) {
														// Skip nonces and invalid card IDs
														continue;
													}

													// Skip disabled cards
													if ( isset( $card['enabled'] ) && ! $card['enabled'] ) {
														continue;
													}

													// Only match by last4 from the parent payment — never guess first enabled card.
													if ( ! empty( $payment_last_4 ) && isset( $card['last_4'] ) && $card['last_4'] === $payment_last_4 ) {
														$customer_card_id = $card['id'];
														break;
													}
												}
											}
										}
									}
								}
							}
						}
					}

					// Log card ID retrieval failure for monitoring (RECOMMENDATION 3: Monitoring)
					if ( empty( $customer_card_id ) ) {
						$renewal_order->add_order_note(
							sprintf(
								// translators: %1$d is the parent order ID, %2$d is the customer ID, %3$s is the masked Square customer ID.
								__( 'ERROR: Recurring Payment - Card ID not found. Parent Order: #%1$d, Customer ID: %2$d, Square Customer ID: %3$s. Check parent order metadata or Square API.', 'woosquare' ),
								$parent_order_id,
								$customer_id,
								! empty( $square_customer_id ) ? substr( $square_customer_id, 0, 8 ) . '****' : 'EMPTY'
							)
						);
					}

					if ( empty( $square_customer_id ) ) {
						$renewal_order->add_order_note(
							sprintf(
								/* translators: %1$d: Parent order ID, %2$d: Customer ID */
								__( 'ERROR: Recurring Payment - Square Customer ID not found. Parent Order: #%1$d, Customer ID: %2$d. Attempted retrieval from: user meta, parent order meta, post meta, renewal order meta, subscription meta, and Square API email search. Payment cannot proceed without Square Customer ID.', 'woosquare' ),
								$parent_order_id,
								$customer_id
							)
						);
						$renewal_order->update_status( 'failed', __( 'Recurring payment failed: Square Customer ID not found. Please contact support.', 'woosquare' ) );
						$renewal_order->save();
						return; // Exit early - cannot proceed without Square Customer ID
					}

					// Validate card ID - must be a card ID (ccof:) not a nonce (cnon:)
					if ( ! empty( $customer_card_id ) && strpos( $customer_card_id, 'cnon:' ) === 0 ) {
						$renewal_order->add_order_note(
							sprintf(
								// translators: %s is the masked card ID.
								__( 'ERROR: Recurring Payment - Invalid card ID format. Received nonce (cnon:) instead of card ID (ccof:). Card ID: %s', 'woosquare' ),
								substr( $customer_card_id, 0, 15 ) . '****'
							)
						);
						$customer_card_id = ''; // Clear invalid card ID
					}

					// Verify card belongs to current customer before payment
					if ( $square_customer_id && $customer_card_id && strpos( $customer_card_id, 'ccof:' ) === 0 && ! empty( $token ) ) {
						// First, try Customer API to get customer details with cards
						$customer_api_url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers/' . $square_customer_id;

						$customer_api_response = wp_remote_get(
							$customer_api_url,
							array(
								'method'  => 'GET',
								'headers' => array(
									'Authorization'  => 'Bearer ' . $token,
									'Square-Version' => '2025-10-16',
									'Accept'         => 'application/json',
								),
								'timeout' => 20,
							)
						);

						$customer_has_card  = false;
						$actual_customer_id = $square_customer_id;

						if ( ! is_wp_error( $customer_api_response ) ) {
							$customer_api_body = wp_remote_retrieve_body( $customer_api_response );
							$customer_api_data = json_decode( $customer_api_body, true );

							// Check if customer was merged/redirected
							if ( isset( $customer_api_data['customer']['id'] ) ) {
								$actual_customer_id = $customer_api_data['customer']['id'];
								if ( $actual_customer_id !== $square_customer_id ) {
									$square_customer_id = $actual_customer_id; // Update to actual customer ID
								}
							}

							// Check if card is in customer's cards array
							if ( isset( $customer_api_data['customer']['cards'] ) && is_array( $customer_api_data['customer']['cards'] ) ) {
								foreach ( $customer_api_data['customer']['cards'] as $card ) {
									if ( isset( $card['id'] ) && $card['id'] === $customer_card_id ) {
										$customer_has_card = true;
										break;
									}
								}
							}
						}

						$card_belongs_to_customer = $customer_has_card;

						// If card not found in Customer API, try Cards API
						if ( ! $customer_has_card ) {
							// Fetch current customer's cards to verify
							$verify_cards_url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/cards?customer_id=' . $square_customer_id;

							$verify_cards_response = wp_remote_get(
								$verify_cards_url,
								array(
									'method'  => 'GET',
									'headers' => array(
										'Authorization'  => 'Bearer ' . $token,
										'Square-Version' => '2025-10-16',
										'Accept'         => 'application/json',
									),
									'timeout' => 20,
								)
							);

							if ( ! is_wp_error( $verify_cards_response ) ) {
								$verify_cards_body = wp_remote_retrieve_body( $verify_cards_response );
								$verify_cards_data = json_decode( $verify_cards_body, true );

								if ( isset( $verify_cards_data['cards'] ) && ! empty( $verify_cards_data['cards'] ) ) {
									foreach ( $verify_cards_data['cards'] as $card ) {
										if ( isset( $card['id'] ) && $card['id'] === $customer_card_id ) {
											if ( ! isset( $card['enabled'] ) || $card['enabled'] ) {
												$card_belongs_to_customer = true;
												break;
											}
										}
									}
								} else {
									// Don't clear card ID if cards array is empty - proceed with payment attempt
									$card_belongs_to_customer = true; // Allow payment to proceed
								}

								// If card doesn't belong to customer AND we have cards, try deterministic replacement only.
								// Avoid selecting "first enabled card" because it can charge the wrong saved card.
								if ( ! $card_belongs_to_customer && isset( $verify_cards_data['cards'] ) && ! empty( $verify_cards_data['cards'] ) ) {
									$enabled_card_ids = array();
									foreach ( $verify_cards_data['cards'] as $card ) {
										if ( isset( $card['id'] ) && strpos( $card['id'], 'ccof:' ) === 0 ) {
											if ( ! isset( $card['enabled'] ) || $card['enabled'] ) {
												$enabled_card_ids[] = $card['id'];
											}
										}
									}

									if ( 1 === count( $enabled_card_ids ) ) {
										$customer_card_id         = $enabled_card_ids[0];
										$card_belongs_to_customer = true;
									} else {
										if ( count( $enabled_card_ids ) > 1 ) {
											$renewal_order->add_order_note( 'Recurring Payment: Multiple enabled cards found for customer; skipping automatic replacement to avoid wrong card charge.' );
										}
										$customer_card_id = ''; // Clear invalid card ID only if we have cards but none are valid

										// Safe fallback: Woo default token only when it belongs to this Square customer.
										$woo_default_card = $this->get_woo_default_square_ccof( $customer_id );
										if ( ! empty( $woo_default_card ) && in_array( $woo_default_card, $enabled_card_ids, true ) ) {
											$customer_card_id         = $woo_default_card;
											$card_belongs_to_customer = true;
											$renewal_order->add_order_note( 'Recurring Payment: Using WooCommerce default payment token that belongs to the Square customer.' );
										}
									}
								}
							} else {
								// On error, proceed with payment attempt
								$card_belongs_to_customer = true;
							}
						}
					}

					if ( $square_customer_id && $customer_card_id && strpos( $customer_card_id, 'ccof:' ) === 0 ) {
						// Generate unique idempotency key to avoid IDEMPOTENCY_KEY_REUSED error on retries
						// Use order ID + timestamp + random number to ensure uniqueness
						$idempotency_key = (string) $renewal_order_id . '_' . time() . '_' . wp_rand( 1000, 9999 );

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

						// Log successful card ID retrieval (RECOMMENDATION 3: Monitoring)
						$masked_card_id = ( strpos( $customer_card_id, 'ccof:' ) === 0 )
							? substr( $customer_card_id, 0, 8 ) . '****' . substr( $customer_card_id, -4 )
							: substr( $customer_card_id, 0, 8 ) . '****';
						/* translators: %s: Masked card ID */
						$renewal_order->add_order_note( sprintf( __( 'Recurring Payment: Card ID retrieved successfully (masked: %s) for renewal order.', 'woosquare' ), $masked_card_id ) );

						$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/payments';

						$headers = array(
							'Accept'         => 'application/json',
							'Authorization'  => 'Bearer ' . $token,
							'Square-Version' => '2021-11-17',
							'Content-Type'   => 'application/json',
							'Cache-Control'  => 'no-cache',
						);

						$payment_api_response = wp_remote_post(
							$url,
							array(
								'method'      => 'POST',
								'headers'     => $headers,
								'httpversion' => '1.0',
								'sslverify'   => false,
								'body'        => wp_json_encode( $fields ),
							)
						);

						$response_body    = wp_remote_retrieve_body( $payment_api_response );
						$transaction_data = json_decode( $response_body );

						if ( isset( $transaction_data->payment->id ) && 'CAPTURED' === $transaction_data->payment->card_details->status ) {
									$transaction_id = $transaction_data->payment->id;
									$renewal_order->add_meta_data( 'woosquare_transaction_id', $transaction_id );
									$renewal_order->add_meta_data( '_transaction_id', $transaction_id );
									$renewal_order->add_meta_data( 'woosquare_transaction_location_id', $location_id );
									// if sandbox enable add sandbox prefix.
									$sandbox_prefix = 'sandbox' === get_transient( 'is_sandbox' ) ? 'through sandbox' : '';
									// Mark as processing.
									// translators: %1$s is the prefix, %2$s is the transaction ID.
									$message = sprintf( __( 'Customer card successfully charged %1$s (Transaction ID: %2$s).', 'wcsrs-payment' ), $sandbox_prefix, $transaction_id );
									$renewal_order->update_status( 'processing', $message );
						} else {
							// Check for specific errors
							$payment_on_file_not_found = false;
							$transaction_limit_error   = false;
							$error_message             = '';

							if ( isset( $transaction_data->errors ) && is_array( $transaction_data->errors ) ) {
								foreach ( $transaction_data->errors as $error ) {
									// Check for TRANSACTION_LIMIT error
									if ( isset( $error->code ) && 'TRANSACTION_LIMIT' === $error->code ) {
										$transaction_limit_error = true;
										$error_message           = isset( $error->detail ) ? $error->detail : 'Transaction limit exceeded';
									}

									// Check for "Payment on file not found" or "NOT_FOUND" error
									if ( isset( $error->code ) && 'NOT_FOUND' === $error->code ) {
										if ( isset( $error->detail ) && (
											stripos( $error->detail, 'Payment on file not found' ) !== false ||
											stripos( $error->detail, 'Card nonce not found' ) !== false
										) ) {
											$payment_on_file_not_found = true;
										}
									}
								}
							}

							// Handle TRANSACTION_LIMIT error with specific message
							if ( $transaction_limit_error ) {
								$renewal_order->add_order_note(
									sprintf(
										// translators: %s is the error message (may be empty).
										__( 'ERROR: Recurring Payment Failed - Transaction Limit Error. %s The card has reached its transaction limit or has been declined by the card issuer. Please contact the customer to update their payment method or try again later.', 'woosquare' ),
										! empty( $error_message ) ? $error_message . '. ' : ''
									)
								);
								$renewal_order->add_order_note( 'Errors: ' . wp_json_encode( $transaction_data->errors ) . ' </br><a target="_blank" href="https://developer.squareup.com/docs/payments-api/error-codes#createpayment-errors"> ERROR CODE REFERENCES </a>' );
								$renewal_order->update_status( 'failed' );
								$renewal_order->save();
								return; // Exit early for transaction limit error
							}

							// If card not found, try to fetch fresh card ID from Square Cards API
							if ( $payment_on_file_not_found && ! empty( $square_customer_id ) && ! empty( $token ) ) {
								$renewal_order->add_order_note( 'Card ID not found in Square. Attempting to fetch fresh card ID from customer cards...' );

								$cards_url      = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/cards?customer_id=' . $square_customer_id;
								$cards_response = wp_remote_get(
									$cards_url,
									array(
										'method'  => 'GET',
										'headers' => array(
											'Authorization' => 'Bearer ' . $token,
											'Square-Version' => '2025-10-16',
											'Accept' => 'application/json',
										),
										'timeout' => 20,
									)
								);

								if ( ! is_wp_error( $cards_response ) ) {
									$cards_body = wp_remote_retrieve_body( $cards_response );
									$cards_data = json_decode( $cards_body, true );

									if ( isset( $cards_data['cards'] ) && ! empty( $cards_data['cards'] ) ) {
										$enabled_card_ids = array();
										// Gather enabled ccof cards only.
										foreach ( $cards_data['cards'] as $card ) {
											if ( isset( $card['id'] ) && strpos( $card['id'], 'ccof:' ) === 0 ) {
												if ( ! isset( $card['enabled'] ) || $card['enabled'] ) {
													$enabled_card_ids[] = $card['id'];
												}
											}
										}

										if ( 1 === count( $enabled_card_ids ) ) {
											$new_card_id = $enabled_card_ids[0];
										} else {
											$new_card_id = null;
											if ( count( $enabled_card_ids ) > 1 ) {
												$renewal_order->add_order_note( 'Recurring Payment: Multiple enabled cards found; skipping auto-retry with replacement card to avoid wrong charge.' );
												$woo_default_card = $this->get_woo_default_square_ccof( $customer_id );
												if ( ! empty( $woo_default_card ) && in_array( $woo_default_card, $enabled_card_ids, true ) ) {
													$new_card_id = $woo_default_card;
													$renewal_order->add_order_note( 'Recurring Payment: Retrying with WooCommerce default payment token that belongs to the Square customer.' );
												}
											}
										}

										if ( ! empty( $new_card_id ) && $new_card_id !== $customer_card_id ) {
											// Retry payment with new card ID
											$renewal_order->add_order_note(
												sprintf(
													// translators: %s is the masked card ID.
													__( 'Retrying payment with fresh card ID: %s', 'woosquare' ),
													substr( $new_card_id, 0, 8 ) . '****' . substr( $new_card_id, -4 )
												)
											);

											$fields['source_id']       = $new_card_id;
											$fields['idempotency_key'] = (string) $renewal_order_id . '_' . time() . '_' . wp_rand( 1000, 9999 );

											$retry_response = wp_remote_post(
												$url,
												array(
													'method' => 'POST',
													'headers' => $headers,
													'httpversion' => '1.0',
													'sslverify' => false,
													'body' => wp_json_encode( $fields ),
												)
											);

											$retry_response_body    = wp_remote_retrieve_body( $retry_response );
											$retry_transaction_data = json_decode( $retry_response_body );

											if ( isset( $retry_transaction_data->payment->id ) && 'CAPTURED' === $retry_transaction_data->payment->card_details->status ) {
												$transaction_id = $retry_transaction_data->payment->id;
												$renewal_order->add_meta_data( 'woosquare_transaction_id', $transaction_id );
												$renewal_order->add_meta_data( '_transaction_id', $transaction_id );
												$renewal_order->add_meta_data( 'woosquare_transaction_location_id', $location_id );
												$sandbox_prefix = 'sandbox' === get_transient( 'is_sandbox' ) ? 'through sandbox' : '';
												// translators: %1$s is the prefix, %2$s is the transaction ID.
												$message = sprintf( __( 'Customer card successfully charged %1$s (Transaction ID: %2$s) after retry with fresh card ID.', 'wcsrs-payment' ), $sandbox_prefix, $transaction_id );
												$renewal_order->update_status( 'processing', $message );
												$renewal_order->save();

												return; // Exit early on success
											}
										}
									}
								}
							}

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
