<?php
/**
 * Woosquare_Plus v1.0 by wpexperts.io
 *
 * @package Woosquare_Plus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-woosquare-payments-connect.php';

/**
 * This class represents the payment processing functionality for WooCommerce using Square.
 *
 * It provides methods and properties for handling payments and related operations.
 */
class WooSquare_Payments {

	/**
	 * The connection object for connecting to a database or external service.
	 *
	 * @var Connection
	 */
	protected $connect;

	/**
	 * A flag indicating whether logging is enabled or not.
	 *
	 * @var bool
	 */
	public $logging;

	/**
	 * Constructor for the Square Payment Gateway.
	 *
	 * @param WooSquare_Payments_Connect $connect An instance of the WooSquare_Payments_Connect class.
	 */
	public function __construct( WooSquare_Payments_Connect $connect ) {
		$this->init();
		$this->connect = $connect;

		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );

		add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'cancel_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_refunded', array( $this, 'cancel_payment' ) );
		add_action( 'wp_ajax_verify_apple_domain', array( $this, 'wooplus_apple_pay_domain_verification' ) );
		add_action( 'wp_ajax_saved_card_charge', array( $this, 'saved_card_charge' ) );
		add_action( 'wp_ajax_nopriv_saved_card_charge', array( $this, 'saved_card_charge' ) );
		add_action( 'wp_ajax_get_saved_token_card_id', array( $this, 'get_saved_token_card_id' ) );
		add_action( 'wp_ajax_nopriv_get_saved_token_card_id', array( $this, 'get_saved_token_card_id' ) );
		add_action( 'cancelled_orphened_order', array( $this, 'cancelled_orphened_order_callback' ) );
		add_action( 'wp_ajax_my_ajax_get_pos_action', array( $this, 'my_ajax_get_pos_action_callback' ) );
		add_action( 'wp_ajax_nopriv_terminal_pay_process', array( $this, 'funct_terminal_pay_process' ) );
		add_action( 'wp_ajax_terminal_pay_process', array( $this, 'funct_terminal_pay_process' ) );
		add_action( 'wp_ajax_nopriv_terminal_pay_process_checkout', array( $this, 'funct_terminal_pay_process_checkout' ) );
		add_action( 'wp_ajax_terminal_pay_process_checkout', array( $this, 'funct_terminal_pay_process_checkout' ) );
		add_action( 'wp_ajax_terminal_pay_process_cancel_checkout', array( $this, 'funct_terminal_pay_process_cancel_checkout' ) );
		add_action( 'wp_ajax_terminal_pay_process_cancel_checkout', array( $this, 'funct_terminal_pay_process_cancel_checkout' ) );

		if ( is_admin() ) {
			add_filter( 'woocommerce_order_actions', array( $this, 'add_capture_charge_order_action' ), 10, 2 );
			add_action( 'woocommerce_order_action_square_capture_charge', array( $this, 'maybe_capture_charge' ) );
			add_action( 'admin_post_add_foobar', array( $this, 'prefix_admin_square_payment_settings_save' ) );
			add_action( 'admin_post_nopriv_add_foobar', array( $this, 'prefix_admin_square_payment_settings_save' ) );
		}

		$gateway_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

		$this->logging                          = ! empty( $gateway_settings['logging'] ) ? true : false;
		$woocommerce_square_google_pay_settings = get_option( 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_settings' );

		// Check if Google Pay or Express Checkout is enabled.
		if (
		isset( $gateway_settings['express_checkout_enabled'] )
		&& 'yes' === $gateway_settings['express_checkout_enabled']
		&& isset( $woocommerce_square_google_pay_settings['enabled'] )
		&& 'yes' === $woocommerce_square_google_pay_settings['enabled']
		) {
			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_google_pay_button_to_product_page' ) );
			add_filter( 'render_block', array( $this, 'bbloomer_woocommerce_cart_block_do_actions' ), 9999, 2 );
			add_action( 'bbloomer_before_woocommerce/proceed-to-checkout-block', array( $this, 'add_custom_block_to_cart_page' ) );
			add_action( 'wp_ajax_create_order_and_process_payment', array( $this, 'create_order_and_process_payment' ) );
			add_action( 'wp_ajax_nopriv_create_order_and_process_payment', array( $this, 'create_order_and_process_payment' ) );
		}
	}

	/**
	 * Init
	 */
	public function init() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		// live/production app id from Square account.

		$tokenn                           = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

		if ( ! empty( $tokenn ) && ! empty( get_transient( 'is_sandbox' ) ) ) {
			if ( ! defined( 'SQUARE_APPLICATION_ID' ) ) {
				define( 'SQUARE_APPLICATION_ID', WOOSQU_PLUS_APPID );
			}
			if ( ! defined( 'WOOSQU_ENABLE_STAGING' ) ) {
				define( 'WOOSQU_ENABLE_STAGING', true );
			}
		} else {
			if ( ! defined( 'SQUARE_APPLICATION_ID' ) ) {
				define( 'SQUARE_APPLICATION_ID', WOOSQU_PLUS_APPID );
			}
			if ( ! defined( 'WOOSQU_ENABLE_STAGING' ) ) {
				define( 'WOOSQU_ENABLE_STAGING', false );
			}
		}

		// include square lib.

		$asset_path = __DIR__ . '/../square-customers/vendor/square/connect/autoload.php';
		if ( file_exists( $asset_path ) ) {
			include_once $asset_path;
		}
		// Includes.
		include_once __DIR__ . '/class-woosquare-plus-gateway.php';
		// Includes.
		include_once __DIR__ . '/class-woosquare-plus-gateway-recurring-renew.php';

		return true;
	}

	/**
	 * Register payment gateways for use in WooCommerce.
	 *
	 * @param array $methods An array of payment methods.
	 * @return array Updated array of payment methods with registered gateways.
	 */
	public function register_gateway( $methods ) {
		$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );
		if ( true === $activate_modules_woosquare_plus['woosquare_payment']['module_activate'] ) {
			$methods[] = 'WooSquare_Plus_Gateway';
			$methods[] = 'WooSquareACHPayment_Gateway';
			$methods[] = 'WooSquareGooglePay_Gateway';
			$methods[] = 'WooSquareAfterPay_Gateway';
			$methods[] = 'WooSquareCashApp_Gateway';
			$methods[] = 'WooSquarePOS_Gateway';

			$domain_name = ! empty( $_SERVER['HTTP_HOST'] ) ? wc_clean( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';

					// Check if the slug is correct.

			if ( ( get_option( 'woo_square_plus_apple_pay_domain_registered' . get_transient( 'is_sandbox' ) . '-' . $domain_name ) === 'yes' ) ) {
				$methods[] = 'WooSquareApplePay_Gateway';
			}
		}
		return $methods;
	}

	/**
	 * Verify a domain with Apple Pay using the Square API.
	 *
	 * This function checks and registers a domain for Apple Pay payments
	 * with the Square API. It sends a verification request to Square and
	 * updates WordPress options accordingly upon successful verification.
	 *
	 * @throws \Exception If unable to verify the domain or missing domain in $_SERVER['HTTP_HOST'].
	 *
	 * @return bool True on successful domain verification, false otherwise.
	 */
	public function wooplus_apple_pay_domain_verification() {

		if ( isset( $_POST['verify_apple_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['verify_apple_nonce'] ) ), 'apple-domain-verification-nonce' ) ) {
			exit;
		}

		$token = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );

		$domain_name = ! empty( $_SERVER['HTTP_HOST'] ) ? wc_clean( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
		if ( empty( $domain_name ) ) {
			throw new \Exception( 'Unable to verify domain with Apple Pay - no domain found in $_SERVER[\'HTTP_HOST\'].' );
		}

		if ( ! $this->woo_square_check_apple_pay_verification_file() ) {
			update_option( 'woo_square_plus_apple_pay_domain_registered' . get_transient( 'is_sandbox' ) . '-' . $domain_name, 'no' );
			delete_option( 'woo_square_plus_apple_pay_domain_registered_url' );
			return false;
		}

		$recently_registered = get_transient( 'woo_square_check_apple_pay_domain_registration' );
		if ( get_option( 'woo_square_plus_apple_pay_domain_registered' . get_transient( 'is_sandbox' ) . '-' . $domain_name ) === 'no' || empty( get_option( 'woo_square_plus_apple_pay_domain_registered' . get_transient( 'is_sandbox' ) . '-' . $domain_name ) ) ) {
				$url             = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/apple-pay/domains';
				$response        = wp_remote_post(
					$url,
					array(
						'headers' => array(
							'Authorization' => 'Bearer ' . $token,
							'Content-Type'  => 'application/json',
						),
						'body'    => wp_json_encode(
							array(
								'domain_name' => $domain_name,
							)
						),
					)
				);
				$parsed_response = json_decode( $response['body'], true );
			if ( isset( $parsed_response['errors'] ) ) {
				foreach ( $parsed_response['errors'] as $key => $error ) {
					$message             = esc_html( sprintf( 'Unable to verify domain %s - %s', $domain_name, $error['detail'] ) );
					$verification_result = false;
				}
			}
			if (
			200 === $response['response']['code'] ||
			! empty( $parsed_response['status'] ) ||
			( isset( $parsed_response['status'] ) && ( $parsed_response['status'] ?? null ) === 'VERIFIED' )
			) {
				update_option( 'woo_square_plus_apple_pay_domain_registered' . get_transient( 'is_sandbox' ) . '-' . $domain_name, 'yes' );
				update_option( 'woo_square_plus_apple_pay_domain_registered_url', $domain_name );
				$this->log( 'Your domain has been verified with Apple Pay!' );
				set_transient( 'woo_square_check_apple_pay_domain_registration', true, HOUR_IN_SECONDS );
				$message ='Your domain has been verified with Apple Pay!'; // phpcs:ignore
				$verification_result = true;
			}
				$result = array(
					'message'             => $message,
					'verification_result' => $verification_result,
				);
		} else {
			$result = array(
				'message'             => 'Apple Pay already verified',
				'verification_result' => true,
			);
		}
		echo wp_json_encode( $result );
		wp_die();
	}

	/**
	 * Processes a payment transaction through the Square terminal.
	 *
	 * This function handles the payment process by communicating with the Square API.
	 * It retrieves device information, customer data, and initiates a payment through
	 * the terminal.
	 *
	 * @return void
	 */
	public function funct_terminal_pay_process() {
		if ( ! isset( $_POST['square_pay_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['square_pay_nonce'] ) ), 'square-pay-nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		$woocommerce_square_terminal_pay_settings = get_option( 'woocommerce_square_terminal_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		$devicecode                               = $woocommerce_square_terminal_pay_settings['device_id'];
		$woo_currency_code                        = get_option( 'woocommerce_currency' );
		$token                                    = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$url                                      = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . ".com/v2/devices/codes/$devicecode";
		$headers                                  = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Cache-Control' => 'no-cache',
		);

		$device_id = json_decode(
			wp_remote_retrieve_body(
				wp_remote_get(
					$url,
					array(
						'method'      => 'GET',
						'headers'     => $headers,
						'httpversion' => '1.0',
						'sslverify'   => false,
					)
				)
			)
		);
		if ( isset( $_POST['pay_form'] ) ) {
			$pay_form_raw = array_map( 'sanitize_text_field', wp_unslash( $_POST['pay_form'] ) );
			parse_str( $pay_form_raw, $output );
		}

		$location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );

		$shipping_address = array(
			'address_line_1'                  => ( isset( $output['shipping_address_1'] ) ) ? $output['shipping_address_1'] : $output['billing_address_1'],
			'address_line_2'                  => ( isset( $output['shipping_address_2'] ) ) ? $output['shipping_address_2'] : $output['billing_address_2'],
			'locality'                        => ( isset( $output['shipping_city'] ) ) ? $output['shipping_city'] : $output['billing_city'],
			'administrative_district_level_1' => ( isset( $output['shipping_state'] ) ) ? $output['shipping_state'] : $output['billing_state'],
			'postal_code'                     => ( isset( $output['shipping_postcode'] ) ) ? $output['shipping_postcode'] : $output['billing_postcode'],
			'country'                         => ( isset( $output['shipping_country'] ) ) ? $output['shipping_country'] : $output['billing_country'],
		);

		$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers/search';
		$headers = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Cache-Control' => 'no-cache',
		);

		$customer_data = array(
			'query' => array(
				'filter' => array(
					'email_address' => array(
						'exact' => $output['billing_email'],
					),
				),
			),
		);

		$search_customer = json_decode(
			wp_remote_retrieve_body(
				wp_remote_post(
					$url,
					array(
						'method'      => 'POST',
						'headers'     => $headers,
						'httpversion' => '1.0',
						'sslverify'   => false,
						'body'        => wp_json_encode( $customer_data ),
					)
				)
			)
		);

		$user = wp_get_current_user();
		if ( ! empty( $search_customer->customers[0]->id ) ) {
			$square_customer_id = $search_customer->customers[0]->id;
			update_user_meta( $user->ID, '_square_customer_id', $square_customer_id );
		} else {

			$customer_id = wp_rand();

			$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers';
			$headers = array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Cache-Control' => 'no-cache',
			);

			$customer_data = array(
				'given_name'    => isset( $output['shipping_first_name'] ) ? $output['shipping_first_name'] : $output['billing_first_name'],
				'family_name'   => isset( $output['shipping_last_name'] ) ? $output['shipping_last_name'] : $output['billing_last_name'],
				'email_address' => $output['billing_email'],
				'address'       => $shipping_address,
				'phone_number'  => $output['billing_phone'],
				'reference_id'  => $customer_id ? (string) $customer_id : __( 'Guest', 'woosquare' ),
			);

			$response = wp_remote_post(
				$url,
				array(
					'method'      => 'POST',
					'headers'     => $headers,
					'httpversion' => '1.0',
					'sslverify'   => false,
					'body'        => wp_json_encode( $customer_data ),
				)
			);

			if ( is_wp_error( $response ) ) {
				// Handle error.
				$error_message = $response->get_error_message();
				$this->log( 'Square API Error: ' . $error_message );
			} else {
				$square_customer = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( isset( $square_customer['customer']['id'] ) ) {
					$square_customer_id = $square_customer['customer']['id'];
					if ( ! empty( $square_customer_id ) ) {
						update_user_meta( $user->ID, '_square_customer_id', $square_customer_id );
					}
				}
			}
		}

		if ( isset( $device_id ) && 'PAIRED' === $device_id->device_code->status ) {
			$total_price     = isset( $_POST['total_price'] ) ? sanitize_text_field( wp_unslash( $_POST['total_price'] ) ) : 0;
			$idempotency_key = uniqid();
			$data            = array(
				'idempotency_key' => $idempotency_key,
				'checkout'        => array(
					'amount_money'   => array(
						'amount'   => $total_price * 100, // $amount,
						'currency' => $woo_currency_code, // $currency,
					),
					'device_options' => array(
						'device_id' => $device_id->device_code->device_id,
					),

					'note'           => 'sample',
					'payment_type'   => 'CARD_PRESENT',
					'reference_id'   => 'sdsdd54',
					'customer_id'    => $square_customer_id,
				),
			);
			$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/terminals/checkouts';

			$checkout_result = json_decode(
				wp_remote_retrieve_body(
					wp_remote_post(
						$url,
						array(
							'method'      => 'POST',
							'headers'     => $headers,
							'httpversion' => '1.0',
							'sslverify'   => false,
							'body'        => wp_json_encode( $data ),
						)
					)
				)
			);

			$checkout_id = $checkout_result->checkout->id;
			update_option( 'terminal_checkout_id', $checkout_id );

		}
		wp_die();
	}

	/**
	 * Processes the checkout for a terminal payment using Square's API.
	 *
	 * This function retrieves the status of a terminal checkout using Square's API
	 * and returns the result in JSON format. It uses the token provided in the GET
	 * parameters for authorization and fetches the checkout ID from the WordPress
	 * options.
	 *
	 * @return void
	 */
	public function funct_terminal_pay_process_checkout() {
		if ( ! isset( $_GET['square_pay_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['square_pay_nonce'] ) ), 'square-pay-nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		if ( ! isset( $_GET['token'] ) ) {
			$token   = sanitize_text_field( wp_unslash( $_GET['token'] ) );
			$headers = array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Cache-Control' => 'no-cache',
			);

			$checkout_id = get_option( 'terminal_checkout_id' );
			$url         = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/terminals/checkouts/' . $checkout_id;

			$result = json_decode(
				wp_remote_retrieve_body(
					wp_remote_get(
						$url,
						array(
							'method'      => 'GET',
							'headers'     => $headers,
							'httpversion' => '1.0',
							'sslverify'   => false,
						)
					)
				)
			);
			echo wp_json_encode(
				array(
					'result'      => 'Result_Status',
					'result_info' => $result,
				)
			);

		}

		wp_die();
	}

	/**
	 * Handles the AJAX request to get POS action callback.
	 *
	 * This function is used to create a new device code for Square's Terminal API.
	 * It verifies the nonce for security, constructs the request headers and data,
	 * sends the request to Square's API, and returns the result in JSON format.
	 *
	 * @return void
	 */
	public function my_ajax_get_pos_action_callback() {

		if ( ! isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'POSTerminal' ) ) {
			wp_die( esc_html__( 'Unauthorized Request', 'woosquare' ) );
		}
		$token           = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$idempotency_key = uniqid();
		$url             = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/devices/codes';
		$headers         =
		array(
			'Accept'         => 'application/json',
			'Authorization'  => 'Bearer ' . $token,
			'Content-Type'   => 'application/json',
			'Square-Version' => '2021-03-17',
			'Cache-Control'  => 'no-cache',
		);
		$location_id     = isset( $_POST['location_id'] ) ? sanitize_text_field( wp_unslash( $_POST['location_id'] ) ) : '';
		$data            = array(
			'device_code'     => array(
				'product_type' => 'TERMINAL_API',
				'location_id'  => $location_id,
				'name'         => 'Objects Terminal Pay',
			),
			'idempotency_key' => (string) $idempotency_key,
		);

		$result = json_decode(
			wp_remote_retrieve_body(
				wp_remote_post(
					$url,
					array(
						'method'      => 'POST',
						'headers'     => $headers,
						'httpversion' => '1.0',
						'sslverify'   => false,
						'body'        => wp_json_encode( $data ),
					)
				)
			)
		);

		if ( ! is_wp_error( $result ) ) {
			echo wp_json_encode( $result );
		}

		wp_die();
	}

	/**
	 * Hook into WooCommerce Cart Blocks to add actions before and after specific blocks.
	 *
	 * @param string $block_content The current block content.
	 * @param array  $block The block data, including the block name.
	 * @return string The updated block content.
	 */
	public function bbloomer_woocommerce_cart_block_do_actions( $block_content, $block ) {

		// Define the list of WooCommerce cart-related blocks to hook into.
		$blocks = array(
			'woocommerce/cart',
			'woocommerce/filled-cart-block',
			'woocommerce/cart-items-block',
			'woocommerce/cart-line-items-block',
			'woocommerce/cart-cross-sells-block',
			'woocommerce/cart-cross-sells-products-block',
			'woocommerce/cart-totals-block',
			'woocommerce/cart-order-summary-block',
			'woocommerce/cart-order-summary-heading-block',
			'woocommerce/cart-order-summary-coupon-form-block',
			'woocommerce/cart-order-summary-subtotal-block',
			'woocommerce/cart-order-summary-fee-block',
			'woocommerce/cart-order-summary-discount-block',
			'woocommerce/cart-order-summary-shipping-block',
			'woocommerce/cart-order-summary-taxes-block',
			'woocommerce/cart-express-payment-block',
			'woocommerce/proceed-to-checkout-block',
			'woocommerce/cart-accepted-payment-methods-block',
		);

		// Check if the current block is in the list of targeted blocks.
		if ( in_array( $block['blockName'], $blocks, true ) ) {

			// Start output buffering.
			ob_start();

			// Trigger custom actions before the block content.
			do_action( 'bbloomer_before_' . $block['blockName'] );

			// Output the block content safely.
			echo wp_kses_post( $block_content );  // Escape output for security.

			// Capture the output buffer content.
			$block_content = ob_get_clean();
		}

		// Return the updated block content.
		return $block_content;
	}


	/**
	 * Create an order and process payment.
	 *
	 * @return void
	 */
	public function create_order_and_process_payment() {
		// Verify nonce for security.
		if ( ! isset( $_POST['square_pay_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['square_pay_nonce'] ) ), 'square-pay-nonce' ) ) {
			wp_send_json_error( array( 'error' => 'Nonce verification failed.' ) );
			wp_die();
		}

		// Sanitize incoming data with checks.
		$square_token = isset( $_POST['square_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['square_nonce'] ) ) : '';
		$product_id   = isset( $_POST['product_id'] ) ? intval( wp_unslash( $_POST['product_id'] ) ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? intval( wp_unslash( $_POST['variation_id'] ) ) : 0;
		$quantity     = isset( $_POST['quantity'] ) ? intval( wp_unslash( $_POST['quantity'] ) ) : 1;
		$_payid       = isset( $_POST['_payid'] ) ? sanitize_text_field( wp_unslash( $_POST['_payid'] ) ) : 0;

		// Create a new WooCommerce order programmatically.
		$order = wc_create_order();

		// Check if product exists.
		if ( $product_id && $quantity ) {
			// Add the product to the order.
			$order->add_product( wc_get_product( $product_id ), $quantity );
		} else {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$product = wc_get_product( $cart_item['product_id'] );

				// Check if the item is a product variation.
				if ( isset( $cart_item['variation_id'] ) && 0 !== $cart_item['variation_id'] ) {
					$variation_id         = $cart_item['variation_id'];
					$variation_attributes = $cart_item['variation'];

					$order->add_product(
						wc_get_product( $variation_id ),
						$cart_item['quantity'],
						array( 'variation' => $variation_attributes )
					);
				} else {
					$order->add_product( $product, $cart_item['quantity'] );
				}
			}
		}

		// Calculate totals and set status.
		$order->calculate_totals();
		$order->update_status( 'pending' );
		$order->set_payment_method( $_payid );

		$gateway = WC()->payment_gateways->payment_gateways()[ $_payid ] ?? null;

		if ( $gateway ) {
			$result = $gateway->process_payment( $order->get_id() );

			if ( isset( $result['result'] ) && 'success' === $result['result'] ) {
				$order->save();
				wp_send_json_success( array( 'redirect_url' => $result['redirect'] ) );
			} else {
				wp_send_json_error( array( 'error' => 'Payment failed.' ) );
			}
		} else {
			wp_send_json_error( array( 'error' => 'Payment gateway not found.' ) );
		}

		wp_die();
	}

	/**
	 * Adds a custom block with Google Pay and Apple Pay buttons to the cart page.
	 *
	 * @return void
	 */
	public function add_custom_block_to_cart_page() {
		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
		$clsapple                         = ( isset( $woocommerce_square_plus_settings['button_color'] ) && 'black' === $woocommerce_square_plus_settings['button_color'] ) ? 'appleblack' : 'applewhite';

		if ( is_cart() ) {
			// Output the payment buttons.
			echo '<div id="google-pay-button" style="margin-top: 10px;"></div>';
			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
			if ( stripos( $user_agent, 'Safari' ) !== false && stripos( $user_agent, 'Chrome' ) === false && stripos( $user_agent, 'Chromium' ) === false ) {
				echo '<div class="apple-pay-button-pcartpage-product"><div id="apple-pay-button" class="apple-pay-button-cart-page ' . esc_attr( $clsapple ) . '"></div></div>';
			}
			echo '<div class="wc-block-components-express-payment-continue-rule wc-block-components-express-payment-continue-rule--cart">Or</div>';
		}
	}

	/**
	 * Adds a Google Pay button to the product page.
	 *
	 * @return void
	 */
	public function add_google_pay_button_to_product_page() {
		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

		?>
	<div id="google-pay-button" style="margin-top: 10px;"></div>
	<input type="hidden" id="square_pay_nonce" name="square_pay_nonce" value="<?php echo esc_attr( wp_create_nonce( 'square-pay-nonce' ) ); ?>">
	<div class="apple-pay-button-psingle-product" style="max-width: 242px;">
		<div id="apple-pay-button" class="apple-pay-button-single-product" style="-apple-pay-button-style:<?php echo esc_attr( $woocommerce_square_plus_settings['button_color'] ); ?>;"></div>
	</div>
		<?php
	}




	/**
	 * Cancels the checkout process for a terminal payment using Square's API.
	 *
	 * This function sends a request to cancel a terminal checkout using Square's API.
	 * It constructs the request headers and data, sends the request to Square's API,
	 * and handles the response.
	 *
	 * @return void
	 */
	public function funct_terminal_pay_process_cancel_checkout() {
		if ( ! isset( $_POST['cancel_terminal_checkout_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cancel_terminal_checkout_nonce'] ) ), 'cancel-terminal-checkout' ) ) {
			wp_die( esc_html__( 'Unauthorized Request', 'woosquare' ) );
		}
		if ( ! isset( $_POST['token'] ) ) {
			$token           = sanitize_text_field( wp_unslash( $_POST['token'] ) );
			$idempotency_key = time();
			$checkout_id     = get_option( 'terminal_checkout_id' );
			$url             = 'https://connect.squareup.com/v2/terminals/checkouts/' . $checkout_id . '/cancel';

			$headers =
			array(
				'Accept'         => 'application/json',
				'Authorization'  => 'Bearer ' . $token,
				'Content-Type'   => 'application/json',
				'Square-Version' => '2021-03-17',
				'Cache-Control'  => 'no-cache',
			);

			$checkout_cancel = json_decode(
				wp_remote_retrieve_body(
					wp_remote_post(
						$url,
						array(
							'method'      => 'POST',
							'headers'     => $headers,
							'httpversion' => '1.0',
							'sslverify'   => false,
							'body'        => $checkout_cancel,
						)
					)
				)
			);
		}
		wp_die();
	}

	/**
	 * Check and update the Apple Pay domain association verification file.
	 *
	 * This function checks if the Apple Pay domain association verification file exists in the server's document root.
	 * If it doesn't exist or is different from the plugin's copy, it updates the file.
	 *
	 * @return bool True if the file is successfully checked and updated, false otherwise.
	 */
	public function woo_square_check_apple_pay_verification_file() {
		if ( empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
			return false;
		}

		$path              = untrailingslashit( wc_clean( sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) ) );
		$dir               = '.well-known';
		$file              = 'apple-developer-merchantid-domain-association';
		$fullpath          = $path . '/' . $dir . '/' . $file;
		$plugin_path       = WOO_SQUARE_PLUS_PLUGIN_PATH . '/admin/modules/square-payments/verification';
		$get_content       = 'file_get_contents';
		$existing_contents = $get_content( $fullpath );
		$new_contents      = $get_content( $plugin_path . '/' . $file );

		if ( false !== $existing_contents && $new_contents === $existing_contents ) {
			return true;
		}

		if ( ! file_exists( $path . '/' . $dir ) ) {
			if ( ! wp_mkdir_p( $path . '/' . $dir, 0755 ) ) {
				$this->log( 'Unable to create domain association folder to domain root.' );
				return false;
			}
		}

		if ( ! copy( $plugin_path . '/' . $file, $fullpath ) ) {
			$this->log( 'Unable to copy domain association file to domain root.' );
			return false;
		}

		$this->log( 'Apple Pay Domain association file updated.' );
		return true;
	}

	/**
	 * Add the "Capture Charge" action to order actions if conditions are met.
	 *
	 * @param array    $actions List of existing order actions.
	 * @param WC_Order $order The WooCommerce order object.
	 * @return array Modified list of order actions.
	 */
	public function add_capture_charge_order_action( $actions, $order ) {

		// bail if the order wasn't paid for with this gateway.
		if ( 'square_plus' . get_transient( 'is_sandbox' ) !== ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->payment_method : $order->get_payment_method() ) ) {
			return $actions;
		}

		// bail if charge was already captured.
		if ( 'yes' === get_post_meta( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id(), '_square_charge_captured', true ) ) {
			return $actions;
		}

		$actions['square_capture_charge'] = esc_html__( 'Capture Charge', 'woosquare' );

		return $actions;
	}

	/**
	 * Form submit to save data of payment settings
	 */
	public function prefix_admin_square_payment_settings_save() {
		// Handle request then generate response using echo or leaving PHP and using HTML.
		if ( ! isset( $_POST['woosquare_setting'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woosquare_setting'] ) ), 'woosquare_setting_nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		$woocommerce_square_enabled          = isset( $_POST['woocommerce_square_enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_square_enabled'] ) ) : '';
		$woocommerce_button_color            = isset( $_POST['woocommerce_square_button_color'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_square_button_color'] ) ) : ''; // New field.
		$woocommerce_enable_express_checkout = isset( $_POST['woocommerce_square_apple_pay_expressch_enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_square_apple_pay_expressch_enabled'] ) ) : ''; // New field.

		// Prepare the array to save settings.
		$arraytosave = array(
			'enabled'                  => ( ! empty( $woocommerce_square_enabled ) && '1' === trim( $woocommerce_square_enabled ) ? 'yes' : 'no' ),
			'title'                    => ( ! empty( $_POST['woocommerce_square_title'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_square_title'] ) ) : '' ),
			'description'              => ( ! empty( $_POST['woocommerce_square_description'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_square_description'] ) ) : '' ),
			'capture'                  => ( ! empty( $_POST['woocommerce_square_capture'] ) && '1' === $_POST['woocommerce_square_capture'] ? 'yes' : 'no' ),
			'create_customer'          => ( ! empty( $_POST['woocommerce_square_create_customer'] ) && '1' === $_POST['woocommerce_square_create_customer'] ? 'yes' : 'no' ),
			'google_pay' . get_transient( 'is_sandbox' ) . '_enabled' => ( ! empty( $_POST[ 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ? 'yes' : 'no' ),
			'apple_pay' . get_transient( 'is_sandbox' ) . '_enabled' => ( ! empty( $_POST[ 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ? 'yes' : 'no' ),
			'after_pay' . get_transient( 'is_sandbox' ) . '_enabled' => ( ! empty( $_POST[ 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ? 'yes' : 'no' ),
			'gift_card_enabled'        => ( ! empty( $_POST[ 'woocommerce_square_gift_card_pay_enabled' . get_transient( 'is_sandbox' ) ] ) && '1' === $_POST[ 'woocommerce_square_gift_card_pay_enabled' . get_transient( 'is_sandbox' ) ] ? 'yes' : 'no' ),
			'cash_app_pay' . get_transient( 'is_sandbox' ) . '_enabled' => ( ! empty( $_POST[ 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ? 'yes' : 'no' ),
			'ach_payment' . get_transient( 'is_sandbox' ) . '_enabled' => ( ! empty( $_POST[ 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_enabled' ] ? 'yes' : 'no' ),
			'terminal_pay' . get_transient( 'is_sandbox' ) . '_enabled' => ( ! empty( $_POST[ 'woocommerce_square_pos' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_pos' . get_transient( 'is_sandbox' ) . '_enabled' ] ? 'yes' : 'no' ),
			'logging'                  => ( ! empty( $_POST['woocommerce_square_logging'] ) && '1' === $_POST['woocommerce_square_logging'] ? 'yes' : 'no' ),
			'Send_customer_info'       => ( ! empty( $_POST['Send_customer_info'] ) && '1' === $_POST['Send_customer_info'] ? 'yes' : 'no' ),

			// Save new fields.
			'button_color'             => ( ! empty( $woocommerce_button_color ) ? $woocommerce_button_color : 'black' ), // Defaults to black if empty.
			'express_checkout_enabled' => ( ! empty( $woocommerce_enable_express_checkout ) && '1' === $woocommerce_enable_express_checkout ? 'yes' : 'no' ), // Express Checkout.
		);

		// Serialize and save the array.
		$arraytosave_serialize = $arraytosave;
		update_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings', $arraytosave_serialize );

		$woocommerce_square_google_pay_settings = get_option( 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( ! empty( $_POST[ 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) {
			$woocommerce_square_google_pay_settings['enabled'] = 'yes';

		} elseif ( empty( $_POST[ 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) ) {
			$woocommerce_square_google_pay_settings['enabled'] = 'no';
		}
		update_option( 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_settings', $woocommerce_square_google_pay_settings );

		$woocommerce_square_after_pay_settings = get_option( 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( ! empty( $_POST[ 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) {
			$woocommerce_square_after_pay_settings['enabled'] = 'yes';

		} elseif ( empty( $_POST[ 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) ) {
			$woocommerce_square_after_pay_settings['enabled'] = 'no';
		}
		update_option( 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_settings', $woocommerce_square_after_pay_settings );

		$woocommerce_square_cash_app_pay_settings = get_option( 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( ! empty( $_POST[ 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) {
			$woocommerce_square_cash_app_pay_settings['enabled'] = 'yes';

		} elseif ( empty( $_POST[ 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) ) {
			$woocommerce_square_cash_app_pay_settings['enabled'] = 'no';
		}
		update_option( 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_settings', $woocommerce_square_cash_app_pay_settings );

		$woocommerce_square_ach_payment_settings = get_option( 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( ! empty( $_POST[ 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_enabled' ] ) {
			$woocommerce_square_ach_payment_settings['enabled'] = 'yes';

		} elseif ( empty( $_POST[ 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_enabled' ] ) ) {
			$woocommerce_square_ach_payment_settings['enabled'] = 'no';
		}
		update_option( 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_settings', $woocommerce_square_ach_payment_settings );

		$woocommerce_square_pos_setting = get_option( 'woocommerce_square_terminal_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( ! empty( $_POST[ 'woocommerce_square_pos' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_pos' . get_transient( 'is_sandbox' ) . '_enabled' ] ) {
			$woocommerce_square_pos_setting['enabled'] = 'yes';

		} elseif ( empty( $_POST[ 'woocommerce_square_pos' . get_transient( 'is_sandbox' ) . '_enabled' ] ) ) {
			$woocommerce_square_pos_setting['enabled'] = 'no';
		}
		update_option( 'woocommerce_square_terminal_pay' . get_transient( 'is_sandbox' ) . '_settings', $woocommerce_square_pos_setting );

		if ( ! empty( $_POST[ 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) {
			$woocommerce_square_apple_pay_settings['enabled'] = 'yes';

		} elseif ( empty( $_POST[ 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) ) {
			$woocommerce_square_apple_pay_settings['enabled'] = 'no';
		}
		update_option( 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_settings ', $woocommerce_square_apple_pay_settings );

		$woocommerce_square_gift_card_pay_enabled = get_option( 'woocommerce_square_gift_card_pay_enabled' . get_transient( 'is_sandbox' ) );
		if ( ! empty( $_POST[ 'woocommerce_square_gift_card_pay_enabled' . get_transient( 'is_sandbox' ) ] ) && '1' === $_POST[ 'woocommerce_square_gift_card_pay_enabled' . get_transient( 'is_sandbox' ) ] ) {
			$woocommerce_square_gift_card_pay_enabled = 'yes';
		} elseif ( empty( $_POST[ 'woocommerce_square_gift_card_pay_enabled' . get_transient( 'is_sandbox' ) ] ) ) {
			$woocommerce_square_gift_card_pay_enabled = 'no';
		}
		update_option( 'woocommerce_square_gift_card_pay_enabled' . get_transient( 'is_sandbox' ), $woocommerce_square_gift_card_pay_enabled );

		$woocommerce_square_payment_reporting = ! empty( $_POST['woocommerce_square_payment_reporting'] ) && '1' === $_POST['woocommerce_square_payment_reporting'] ? 'yes' : 'no';
		update_option( 'woocommerce_square_payment_reporting', $woocommerce_square_payment_reporting );

		$msg = wp_json_encode(
			array(
				'status' => true,

				'msg'    => 'Settings updated successfully!',
			)
		);
		set_transient( 'woosquare_plus_notification', $msg, 12 * HOUR_IN_SECONDS );
		wp_safe_redirect( get_admin_url() . 'admin.php?page=square-payment-gateway' );
	}

	/**
	 * Maybe capture a payment for an order.
	 *
	 * This function is responsible for capturing a payment for a given order.
	 *
	 * @param int|WC_Order $order Order ID or order object to capture payment for.
	 *
	 * @return bool True if the payment capture was attempted.
	 */
	public function maybe_capture_charge( $order ) {
		if ( ! is_object( $order ) ) {
			$order = wc_get_order( $order );
		}

		$this->capture_payment( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id() );

		return true;
	}

	/**
	 * Capture payment when the order is changed from on-hold to complete or processing.
	 *
	 * @param int $order_id The ID of the order to capture payment for.
	 *
	 * @throws Exception If there is an error during payment capture.
	 */
	public function capture_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( in_array( ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->payment_method : $order->get_payment_method() ), array( 'square_plus' . get_transient( 'is_sandbox' ), 'square_google_pay' . get_transient( 'is_sandbox' ), 'square_gift_card_pay' ), true ) ) {
			try {
				$this->log( "Info: Begin capture for order {$order_id}" );

				$trans_id = $order->get_meta( 'woosquare_transaction_id', true );

				$token = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );

				$this->connect->set_access_token( $token );

				$transaction_status = $this->connect->get_transaction_status( $trans_id );

				if ( 'AUTHORIZED' === $transaction_status ) {

					$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . ".com/v2/payments/$trans_id/complete";
					$headers = array(
						'Accept'        => 'application/json',
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
						'Cache-Control' => 'no-cache',
					);

					$result  = json_decode(
						wp_remote_retrieve_body(
							wp_remote_post(
								$url,
								array(
									'method'      => 'POST',
									'headers'     => $headers,
									'httpversion' => '1.0',
									'sslverify'   => false,
									'body'        => '',
								)
							)
						)
					);
					$print_r = 'print_r';
					if ( is_wp_error( $result ) ) {
								$order->add_order_note( __( 'Unable to capture charge!', 'woosquare' ) . ' ' . $result->get_error_message() );

								throw new Exception( $result->get_error_message() );
					} elseif ( ! empty( $result->errors ) ) {
						$order->add_order_note( __( 'Unable to capture charge!', 'woosquare' ) . ' ' . $print_r( $result->errors, true ) );

						throw new Exception( $print_r( $result->errors, true ) );
					} else {

						$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

						if ( get_transient( 'is_sandbox' ) ) {
								$msg = ' via Sandbox ';
						} else {
								$msg = '';
						}

						// translators: %1$s is the message, %2$s is the payment ID.
						$order->add_order_note( sprintf( __( 'Square charge complete %1$s (Charge ID: %2$s)', 'woosquare' ), $msg, $trans_id ) );
						$order->update_meta_data( '_square_charge_captured', 'yes' );
						$this->log( "Info: Capture successful for {$order_id}" );
					}
				}
			} catch ( Exception $e ) {
				// translators: Error message placeholder in a log entry. Placeholder: Error details.
				$this->log( sprintf( __( 'Error unable to capture charge: %s', 'woosquare' ), $e->getMessage() ) );
			}
		}
		$order->save();
	}

	/**
	 * Cancel payment authorization for an order.
	 *
	 * @param int $order_id The ID of the order for which to cancel payment authorization.
	 * @throws Exception If there is an error during payment capture.
	 */
	public function cancel_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'square_plus' . get_transient( 'is_sandbox' ) === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->payment_method : $order->get_payment_method() ) ) {
			try {
				$this->log( "Info: Cancel payment for order {$order_id}" );
				$trans_id = $order->get_meta( 'woosquare_transaction_id', true );
				$captured = $order->get_meta( '_square_charge_captured', true );

				$token = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
				$this->connect->set_access_token( $token );

				$transaction_status = $this->connect->get_transaction_status( $trans_id );

				if ( 'AUTHORIZED' === $transaction_status ) {
					$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . ".com/v2/payments/$trans_id/cancel";
					$headers = array(
						'Accept'        => 'application/json',
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
						'Cache-Control' => 'no-cache',
					);

					$result             = json_decode(
						wp_remote_retrieve_body(
							wp_remote_post(
								$url,
								array(
									'method'      => 'POST',
									'headers'     => $headers,
									'httpversion' => '1.0',
									'sslverify'   => false,
									'body'        => '',
								)
							)
						)
					);
					$transaction_status = $this->connect->get_transaction_status( $trans_id );
					if ( is_wp_error( $result ) ) {
								$order->add_order_note( __( 'Unable to void charge!', 'woosquare' ) . ' ' . $result->get_error_message() );
								throw new Exception( $result->get_error_message() );
					} elseif ( ! empty( $result->errors ) ) {
						$order->add_order_note( __( 'Unable to void charge!', 'woosquare' ) . ' ' . wp_json_encode( $result->errors, true ) );
						throw new Exception( wp_json_encode( $result->errors, true ) );
					} elseif ( 'VOIDED' === $transaction_status ) {
						// translators: Error message placeholder in a log entry. Placeholder: Error details.
						$order->add_order_note( sprintf( __( 'Square charge voided! (Charge ID: %s)', 'woosquare' ), $trans_id ) );
						$order->delete_meta_data( '_square_charge_captured' );
						$order->delete_meta_data( 'woosquare_transaction_id' );
					}
				}
			} catch ( Exception $e ) {
				// translators: Error message placeholder in a log entry. Placeholder: Error details.
				$this->log( sprintf( __( 'Unable to void charge!: %s', 'woosquare' ), $e->getMessage() ) );
			}
		}
		$order->save();
	}

	/**
	 * Retrieves the saved Square card ID and customer ID using a payment token.
	 *
	 * This function handles the retrieval of a saved Square card ID and its associated customer ID
	 * based on the provided payment token. It verifies the nonce, checks if a saved token is provided,
	 * and then uses the Square API to retrieve the card details.
	 *
	 * @return void
	 */
	public function get_saved_token_card_id() {
		if ( ! isset( $_POST['square_pay_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['square_pay_nonce'] ) ), 'square-pay-nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		if ( isset( $_POST['saved_token'] ) ) {
			$token             = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
			$token_id          = wc_clean( sanitize_text_field( wp_unslash( $_POST['saved_token'] ) ) );
			$wc_payment_tokens = WC_Payment_Tokens::get( $token_id );
			if ( isset( $wc_payment_tokens ) ) {
				$customer_card_id = $wc_payment_tokens->get_token();
			}
			if ( isset( $customer_card_id ) ) {
				$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/cards/' . $customer_card_id;

				$headers = array(
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
					'Cache-Control' => 'no-cache',
				);
				$result  = json_decode(
					wp_remote_retrieve_body(
						wp_remote_post(
							$url,
							array(
								'method'      => 'GET',
								'headers'     => $headers,
								'httpversion' => '1.0',
								'sslverify'   => false,
							)
						)
					)
				);

				if ( isset( $result->card->id ) ) {
							$customer_id = $result->card->customer_id;
				}
			}
			$data = array(
				'customer_id' => $customer_id,
				'card_id'     => $result->card->id,
			);
			echo wp_json_encode( $data );
		}
		die();
	}

	/**
	 * Processes a saved card charge through Square.
	 *
	 * This function handles the processing of a saved card charge using Square's API.
	 * It verifies the nonce, retrieves or creates the customer in Square, and handles
	 * the card processing, including saving the card details if required.
	 *
	 * @return void
	 */
	public function saved_card_charge() {
		if ( ! isset( $_POST['square_pay_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['square_pay_nonce'] ) ), 'square-pay-nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		$token = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		if ( ! empty( $_POST['pay_form'] ) ) {
			$pay_form_raw = array_map( 'sanitize_text_field', wp_unslash( $_POST['pay_form'] ) ); // phpcs:ignore
			parse_str( $pay_form_raw, $output );
		}
		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
		$location_id                      = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );

		$card_nonce               = isset( $_POST['card_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['card_nonce'] ) ) : '';
		$buyer_verification_token = isset( $_POST['verification_token'] ) ? sanitize_text_field( wp_unslash( $_POST['verification_token'] ) ) : '';

		$billing_address_1 = ( isset( $output['billing_address_1'] ) ) ? $output['billing_address_1'] : '';
		$billing_address_2 = ( isset( $output['billing_address_2'] ) ) ? $output['billing_address_2'] : '';
		$billing_city      = ( isset( $output['billing_city'] ) ) ? $output['billing_city'] : '';
		$billing_state     = ( isset( $output['billing_state'] ) ) ? $output['billing_state'] : '';
		$billing_postcode  = ( isset( $output['billing_postcode'] ) ) ? $output['billing_postcode'] : '';
		$billing_country   = ( isset( $output['billing_country'] ) ) ? $output['billing_country'] : '';

		$shipping_address = array(
			'address_line_1'                  => ( isset( $output['shipping_address_1'] ) ) ? $output['shipping_address_1'] : $billing_address_1,
			'address_line_2'                  => ( isset( $output['shipping_address_2'] ) ) ? $output['shipping_address_2'] : $billing_address_2,
			'locality'                        => ( isset( $output['shipping_city'] ) ) ? $output['shipping_city'] : $billing_city,
			'administrative_district_level_1' => ( isset( $output['shipping_state'] ) ) ? $output['shipping_state'] : $billing_state,
			'postal_code'                     => ( isset( $output['shipping_postcode'] ) ) ? $output['shipping_postcode'] : $billing_postcode,
		);
		$url              = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers/search';
		$headers          = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Cache-Control' => 'no-cache',
		);

		$customer_data = array(
			'query' => array(
				'filter' => array(
					'email_address' => array(
						'exact' => isset( $output['billing_email'] ) ? $output['billing_email'] : '',
					),
				),
			),
		);

		$search_customer = json_decode(
			wp_remote_retrieve_body(
				wp_remote_post(
					$url,
					array(
						'method'      => 'POST',
						'headers'     => $headers,
						'httpversion' => '1.0',
						'sslverify'   => false,
						'body'        => wp_json_encode( $customer_data ),
					)
				)
			)
		);

		$user = wp_get_current_user();
		if ( ! empty( $search_customer->customers[0]->id ) ) {
			$square_customer_id = $search_customer->customers[0]->id;
			update_user_meta( $user->ID, '_square_customer_id', $square_customer_id );
		} else {

			$customer_id = wp_rand();
			$body        = array(
				'given_name'    => isset( $output['shipping_first_name'] ) ? $output['shipping_first_name'] : $output['billing_first_name'],
				'family_name'   => isset( $output['shipping_last_name'] ) ? $output['shipping_last_name'] : $output['billing_last_name'],
				'email_address' => $output['billing_email'],
				'address'       => $shipping_address,
				'phone_number'  => $output['billing_phone'],
				'reference_id'  => $customer_id ? (string) $customer_id : __( 'Guest', 'woosquare' ),
			);

			$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers';
			$headers = array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token, // Replace with your token.
				'Content-Type'  => 'application/json',
				'Cache-Control' => 'no-cache',
			);

			$response = wp_remote_post(
				$url,
				array(
					'method'      => 'POST',
					'headers'     => $headers,
					'httpversion' => '1.0',
					'sslverify'   => false,
					'body'        => wp_json_encode( $body ),
				)
			);

			$square_customer = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $square_customer['customer']['id'] ) ) {
				$square_customer_id = $square_customer['customer']['id'];
				if ( ! empty( $square_customer_id ) ) {
					update_user_meta( $user->ID, '_square_customer_id', $square_customer_id );
				}
			}
		}
		$billing_first_name = isset( $output['billing_first_name'] ) ? $output['billing_first_name'] : '';
		$billing_last_name  = isset( $output['billing_last_name'] ) ? $output['billing_last_name'] : '';
		if ( ( ! isset( $output['saved_cards'] ) && isset( $output['square_plussq-card-saved'] ) && 'on' === $output['square_plussq-card-saved'] && ! empty( $square_customer_id ) )
			|| isset( $_POST['subscription'] )
		) {

			$idempotency_key = uniqid();
			$body            = array(
				'card'               => array(
					'cardholder_name' => $billing_first_name . ' ' . $billing_last_name,
					'customer_id'     => $square_customer_id,
				),
				'idempotency_key'    => (string) $idempotency_key,
				'source_id'          => $card_nonce,
				'verification_token' => $buyer_verification_token,

			);

			$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/cards';

			$headers = array(
				'Accept'         => 'application/json',
				'Authorization'  => 'Bearer ' . $token,
				'Content-Type'   => 'application/json',
				'Square-Version' => '2021-12-15',
				'Cache-Control'  => 'no-cache',
			);
			$result  = json_decode(
				wp_remote_retrieve_body(
					wp_remote_post(
						$url,
						array(
							'method'      => 'POST',
							'headers'     => $headers,
							'httpversion' => '1.0',
							'sslverify'   => false,
							'body'        => wp_json_encode( $body ),
						)
					)
				)
			);
			if ( isset( $result->card->id ) ) {
				$card_id = $result->card->id;

				$wc_payment_token = new WC_Payment_Token_CC();
				$wc_payment_token->set_token( $card_id );
				$wc_payment_token->set_gateway_id( 'square_plus' . get_transient( 'is_sandbox' ) ); // `$this->id` references the gateway ID set in `__construct`
				$wc_payment_token->set_card_type( $result->card->card_brand );
				$wc_payment_token->set_last4( $result->card->last_4 );
				$wc_payment_token->set_expiry_month( $result->card->exp_month );
				$wc_payment_token->set_expiry_year( $result->card->exp_year );
				$wc_payment_token->set_user_id( get_current_user_id() );
				$wc_payment_token->save();

			} else {
				$message = '';
				if ( ! empty( $result->errors ) ) {
					foreach ( $result->errors as $error ) {
						$message .= $error->code . ' - ' . $error->detail;
					}
				}
			}

			$data = array(
				'customer_id' => $square_customer_id,
				'card_id'     => $card_id,
				'message'     => isset( $message ) ? $message : '',
			);
		}
		echo wp_json_encode( $data );
		die();
	}

	/**
	 * Handles the cancellation of orphaned orders and their fulfillments in Square.
	 *
	 * This function is triggered when an orphaned WooCommerce order needs to be cancelled.
	 * It updates the order and its fulfillments in Square by setting their states to "CANCELED".
	 * The function uses Square's API to perform these updates, ensuring that the order status
	 * is correctly reflected in both WooCommerce and Square.
	 *
	 * @param object $order The order object containing order details and fulfillments.
	 *
	 * @return void
	 */
	public function cancelled_orphened_order_callback( $order ) {
		$token = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		if ( isset( $order->id ) && isset( $order->fulfillments ) ) {
			$fulfillments_array = array();
			$url                = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/orders/' . $order->id;
			foreach ( $order->fulfillments as $fulfillment ) {
				$fulfillments[] = array(
					'state' => 'CANCELED',
					'uid'   => $fulfillment->uid,
				);
			}
			$body = array(
				'idempotency_key' => uniqid(),
				'order'           => array(
					'location_id'  => $order->location_id,
					'version'      => $order->version,
					'fulfillments' => $fulfillments,

				),
			);
			$headers = array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Cache-Control' => 'no-cache',
			);

			$canceled_order_fulfillments = json_decode(
				wp_remote_retrieve_body(
					wp_remote_post(
						$url,
						array(
							'method'      => 'PUT',
							'headers'     => $headers,
							'httpversion' => '1.0',
							'sslverify'   => false,
							'body'        => wp_json_encode( $body ),
						)
					)
				)
			);
		}
		if ( isset( $canceled_order_fulfillments->order->id ) && ! empty( $canceled_order_fulfillments->order->fulfillments ) ) {
			$fulfillments_array = array();
			$url                = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/orders/' . $canceled_order_fulfillments->order->id;

			$bodyy = array(
				'idempotency_key' => uniqid(),
				'order'           => array(
					'location_id' => $canceled_order_fulfillments->order->location_id,
					'version'     => $canceled_order_fulfillments->order->version,
					'state'       => 'CANCELED',

				),
			);
			$headers = array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Cache-Control' => 'no-cache',
			);

			$canceled_order = json_decode(
				wp_remote_retrieve_body(
					wp_remote_post(
						$url,
						array(
							'method'      => 'PUT',
							'headers'     => $headers,
							'httpversion' => '1.0',
							'sslverify'   => false,
							'body'        => wp_json_encode( $bodyy ),
						)
					)
				)
			);
		}
	}

	/**
	 * Logs a message if logging is enabled.
	 *
	 * @param string $message The message to log.
	 */
	public function log( $message ) {
		if ( $this->logging ) {
			WooSquare_Payment_Logger::log( $message );
		}
	}
}

new WooSquare_Payments( new WooSquare_Payments_Connect() );
