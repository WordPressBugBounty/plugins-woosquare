<?php
/**
 * Woosquare_Plus v1.0 by wpexperts.io
 *
 * @package Woosquare_Plus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

#[AllowDynamicProperties]

/**
 * Represents a custom Apple Pay payment gateway for WooCommerce using Square.
 *
 * This class extends the WC_Payment_Gateway class and provides custom functionality
 * for processing Apple Pay payments with Square integration.
 */
class WooSquareApplePay_Gateway extends WC_Payment_Gateway {

	/**
	 * The connection object for connecting to a database or external service.
	 *
	 * @var Connection
	 */
	protected $connect;

	/**
	 * The token used for authentication or authorization.
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * A flag indicating whether logging is enabled or not.
	 *
	 * @var bool
	 */
	public $log;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'square_apple_pay' . get_transient( 'is_sandbox' );
		$this->method_title       = __( 'Square Apple Pay', 'wpexpert-square' );
		$this->method_description = __( 'Square Apple pay works by adding domain in square. we will automatically verify domain  and then sending the details to Square for verification and processing.', 'wpexpert-square' );
		$this->has_fields         = true;
		$this->supports           = array(
			'products',
			'refunds',
		);

		// Load the form fields.
		$this->init_form_fields();
		$domain_name = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
		if ( get_option( 'woo_square_plus_apple_pay_domain_registered' . get_transient( 'is_sandbox' ) . '-' . $domain_name ) === 'yes' ) {
			$woocommerce_square_apple_pay_settings                    = get_option( 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_settings' );
			$woocommerce_square_apple_pay_settings['domain_verified'] = 'yes';
			update_option( 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_settings', $woocommerce_square_apple_pay_settings );
		}

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title           = $this->get_option( 'title' );
		$this->description     = $this->get_option( 'description' );
		$this->enabled         = $this->get_option( 'enabled' ) === 'yes' ? 'yes' : 'no';
		$this->capture         = $this->get_option( 'capture' ) === 'yes' ? false : true;
		$this->create_customer = $this->get_option( 'create_customer' ) === 'yes' ? true : false;
		$this->logging         = $this->get_option( 'logging' ) === 'yes' ? true : false;
		$this->connect         = new WooSquare_Payments_Connect(); // decouple in future when v2 is ready.
		$this->token           = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );

		$this->connect->set_access_token( $this->token );

		$this->connect->set_access_token( $this->token );

		$woocommerce_square_apple_pay_settings = get_option( 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_settings' );

		if ( 'yes' === $woocommerce_square_apple_pay_settings['enabled'] ?? null ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts_applepay' ) );
		}

		add_action( 'admin_notices', array( $this, 'admin_notices_applepay' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Check if required fields are set
	 */
	public function admin_notices_applepay() {
		$domain_name = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
		if ( get_option( 'woo_square_plus_apple_pay_domain_registered' . get_transient( 'is_sandbox' ) . '-' . $domain_name ) === 'no' ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . sprintf( esc_html__( 'Your Square Apple pay domain is not verified.', 'wpexpert-square' ), esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ) . '</p></div>';
		}

		if ( ! $this->enabled ) {
			return;
		}
	}


	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {
		$is_available = true;

		if ( 'yes' === $this->enabled ) {
			if ( ! WC_SQUARE_ENABLE_STAGING && ! wc_checkout_is_https() ) {
				$is_available = false;
			}

			if ( ! WC_SQUARE_ENABLE_STAGING && empty( $this->token ) ) {
				$is_available = true;
			}

			if ( ! get_option( 'woo_square_access_token_cauth' . get_transient( 'is_sandbox' ) ) ) {
				$is_available = false;
			}

			// Square only supports US, Canada and Australia for now.
			if ( (
				'US' !== WC()->countries->get_base_country() &&
				'CA' !== WC()->countries->get_base_country() &&
				'GB' !== WC()->countries->get_base_country() &&
				'ES' !== WC()->countries->get_base_country() &&
				'IE' !== WC()->countries->get_base_country() &&
				'FR' !== WC()->countries->get_base_country() &&
				'AU' !== WC()->countries->get_base_country() ) || (
				'USD' !== get_woocommerce_currency() &&
				'CAD' !== get_woocommerce_currency() &&
				'EUR' !== get_woocommerce_currency() &&
				'AUD' !== get_woocommerce_currency() &&
				'GBP' !== get_woocommerce_currency() )
				) {
				$is_available = false;
			}

			// if enabled and sandbox credentials not setup.
			if ( get_transient( 'is_sandbox' ) ) {
				if (
					empty( WOOSQU_PLUS_APPID )
					||
					empty( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) )
					||
					empty( get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ) )
				) {
					$is_available = false;
				}
			}
		} else {
			$is_available = false;
		}

		return apply_filters( 'woocommerce_square_payment_applepay_gateway_is_available', $is_available );
	}

	/**
	 * Initialize Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = apply_filters(
			'woocommerce_squareapay_gateway_settings',
			array(
				'enabled'         => array(
					'title'       => __( 'Enable/Disable', 'wpexpert-square' ),
					'label'       => __( 'Enable Square Apple Pay', 'wpexpert-square' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'           => array(
					'title'       => __( 'Title', 'wpexpert-square' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'wpexpert-square' ),
					'default'     => __( 'Apple Pay (Square)', 'wpexpert-square' ),
				),
				'description'     => array(
					'title'       => __( 'Description', 'wpexpert-square' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'wpexpert-square' ),
					'default'     => __( 'Pay with your credit card via Square.', 'wpexpert-square' ),
				),
				'domain_verified' => array(
					'title'       => __( 'Domain Verified ', 'wpexpert-square' ),
					'description' => __( 'This will automatically update when your domain is verfied.', 'wpexpert-square' ),
					'type'        => 'checkbox',
					'default'     => 'no',
					'name'        => __( 'Currency Position', 'woocommerce' ),
				),
				'capture'         => array(
					'title'       => __( 'Delay Capture', 'woosquare' ),
					'label'       => __( 'Enable Delay Capture', 'woosquare' ),
					'type'        => 'checkbox',
					'description' => __( 'When enabled, the request will only perform an Auth on the provided card. You can then later perform either a Capture or Void.', 'woosquare' ),
					'default'     => 'no',
				),
				'logging'         => array(
					'title'       => __( 'Logging', 'wpexpert-square' ),
					'label'       => __( 'Log debug messages', 'wpexpert-square' ),
					'type'        => 'checkbox',
					'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'wpexpert-square' ),
					'default'     => 'no',
				),
			)
		);
	}


	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		?>

		<div id="payment-form">
			<!-- Add the below element -->
			<div id="apple-pay-button"></div>
			<span id="browser_support_msg"></span>
			<input type="hidden" id="apple_pay_nonce" name="apple_pay_nonce" value="<?php echo esc_attr( wp_create_nonce( 'apple-pay-nonce' ) ); ?>">
		</div>
		<div id="payment-status-container"></div>
			
		<?php
	}

	/**
	 * Payment_scripts function.
	 *
	 * @access public
	 */
	public function payment_scripts_applepay() {
		if ( ! is_checkout() ) {
			return;
		}
		$location                         = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

		global $woocommerce;
		$woocommerce_square_settings = get_option( 'woocommerce_square_settings' . get_transient( 'is_sandbox' ) );
		$currency_cod                = get_option( 'woocommerce_currency' );
		$country_code                = WC()->countries->get_base_country();
		// need to add condition square payment enable so disable below script.
		if ( get_transient( 'is_sandbox' ) ) {
			$endpoint = 'sandbox.web';
		} else {
			$endpoint = 'web';
		}
		wp_enqueue_script( 'square-apple-pay', 'https://' . $endpoint . '.squarecdn.com/v1/square.js', array(), WOOSQUARE_VERSION, true );

		wp_register_script( 'woosquare-apple-pay', WOOSQUARE_PLUGIN_URL_PAYMENT . '/js/SquarePaymentsApplePay.js?wp_rand=' . wp_rand(), array(), WOOSQUARE_VERSION, true );
		wp_localize_script(
			'woosquare-apple-pay',
			'squareapplepay_params',
			array(
				'application_id' => WOOSQU_PLUS_APPID,
				'lid'            => $location,
				'merchant_name'  => 'Square Apple Pay',
				'order_total'    => $woocommerce->cart->total,
				'currency_code'  => $currency_cod,
				'country_code'   => $country_code,
				'sandbox'        => get_transient( 'is_sandbox' ),
				'square_pay_nonce'       	   => wp_create_nonce( 'square-pay-nonce' ),
			)
		);
		wp_enqueue_script( 'woosquare-apple-pay' );

		wp_enqueue_style( 'woocommerce-square-apple-pay-styles', WOOSQUARE_PLUGIN_URL_PAYMENT . '/css/SquareFrontendStyles_apple_pay.css', array(), '1.0', 'all' );

		return true;
	}

	/**
	 * Process a payment for an order.
	 *
	 * This method is responsible for processing a payment for a given order.
	 *
	 * @param int  $order_id The ID of the order to process the payment for.
	 * @param bool $retry    Whether to retry the payment if it fails initially (default is true).
	 *
	 * @throws Exception If there is an error during payment capture.
	 */
	public function process_payment( $order_id, $retry = true ) {

		if ( ! isset( $_POST['apple_pay_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apple_pay_nonce'] ) ), 'apple-pay-nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		$order              = wc_get_order( $order_id );
		$nonce              = isset( $_POST['square_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['square_nonce'] ) ) : '';
		$squhposupdate_meta = wc_get_order( $order->id );
		$squhposupdate_meta->update_meta_data( '_POST_requuest' . wp_rand( 1, 1000 ), $_POST );
		$squhposupdate_meta->update_meta_data( 'errors_apay', isset( $_POST['errors'] ) ? sanitize_text_field( wp_unslash( $_POST['errors'] ) ) : '' );
		$squhposupdate_meta->update_meta_data( 'errors_noncedatatype', isset( $_POST['noncedatatype'] ) ? sanitize_text_field( wp_unslash( $_POST['noncedatatype'] ) ) : '' );
		$squhposupdate_meta->update_meta_data( 'errors_cardData', isset( $_POST['cardData'] ) ? sanitize_text_field( wp_unslash( $_POST['cardData'] ) ) : '' );
		$currency = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_order_currency() : $order->get_currency();
		$this->log( "Info: Begin processing payment for order {$order_id} for the amount of {$order->get_total()}" );
		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( 'yes' === $woocommerce_square_plus_settings['Send_customer_info'] ) {
			$first_name = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_first_name : $order->get_billing_first_name();
			$last_name  = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_last_name : $order->get_billing_last_name();
			if ( empty( $first_name ) && empty( $last_name ) ) {
				$first_name = null;
				$last_name  = null;
			}
		} else {
			$first_name = null;
			$last_name  = null;
		}
		try {

			if ( function_exists( 'square_order_sync_add_on' ) ) {
				$amount = (int) round( $this->format_amount( $order->get_total(), $currency ), 1 );
			} else {
				$amount = (int) $this->format_amount( $order->get_total(), $currency );
			}

			$idempotency_key = uniqid();
			$data            = array(
				'idempotency_key'     => $idempotency_key,
				'amount_money'        => array(
					'amount'   => $amount,
					'currency' => $currency,
				),
				'reference_id'        => (string) $order->get_order_number(),
				'autocomplete'        => $this->capture,
				'source_id'           => $nonce,
				'buyer_email_address' => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_email : $order->get_billing_email(),
				'billing_address'     => array(
					'address_line_1'                  => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_address_1 : $order->get_billing_address_1(),
					'address_line_2'                  => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_address_2 : $order->get_billing_address_2(),
					'locality'                        => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_city : $order->get_billing_city(),
					'administrative_district_level_1' => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_state : $order->get_billing_state(),
					'postal_code'                     => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_postcode : $order->get_billing_postcode(),
					'country'                         => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_country : $order->get_billing_country(),
				),
				'note'                => apply_filters( 'woosquare_payment_order_note', 'WooCommerce: Order #' . (string) $order->get_order_number() . ' ' . $first_name . ' ' . $last_name, $order ),
			);

			if ( $order->needs_shipping_address() ) {
				$data['shipping_address'] = array(
					'address_line_1'                  => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->shipping_address_1 : $order->get_shipping_address_1(),
					'address_line_2'                  => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->shipping_address_2 : $order->get_shipping_address_2(),
					'locality'                        => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->shipping_city : $order->get_shipping_city(),
					'administrative_district_level_1' => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->shipping_state : $order->get_shipping_state(),
					'postal_code'                     => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->shipping_postcode : $order->get_shipping_postcode(),
					'country'                         => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->shipping_country : $order->get_shipping_country(),
				);
			}

			$msg         = '';
			$endpoint    = 'squareup';
			$location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
			if ( get_transient( 'is_sandbox' ) ) {
				$msg      = ' via Sandbox ';
				$endpoint = 'squareupsandbox';
			}

			if ( '1' === get_option( 'woo_square_customer_sync_square_order_sync' ) ) {
				$api_config = '';
				$api_client = '';
				$api_config = new \SquareConnect\Configuration();
				$api_config->setHost( 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com' );
				$api_config->set_access_token( $this->token );
				$api_client = new \SquareConnect\ApiClient( $api_config );

				// create customer.
				$square_customer_id = null;
				$customer_api       = new \SquareConnect\Api\CustomersApi( $api_client );
				// check if customer exist.
				$customer_id = $order->get_customer_id();

				if ( $customer_id ) {
						$square_customer_id = get_user_meta( $customer_id, '_square_customer_id', true );
				} else {
						$square_customer_id = $order->get_meta( '_square_customer_id', true );
				}

				// check if there is customer id and not exist in square account.
				if ( $square_customer_id ) {
					try {
						$customer = $customer_api->retrieveCustomer( $square_customer_id );

					} catch ( Exception $ex ) {
						// customer not exist.
						$square_customer_id = null;
					}
				}

				if ( ! $square_customer_id ) {

					$body = new \SquareConnect\Model\CreateCustomerRequest();

					$body->setGivenName( $order->get_shipping_first_name() ? $order->get_shipping_first_name() : $order->get_billing_first_name() );
					$body->setFamilyName( $order->get_shipping_last_name() ? $order->get_shipping_last_name() : $order->get_billing_last_name() );
					$body->setEmailAddress( $order->get_billing_email() );
					$body->setAddress( $shipping_address );
					$body->setPhoneNumber( $order->get_billing_phone() );
					$body->setReferenceId( $customer_id ? (string) $customer_id : __( 'Guest', 'woosquare' ) );
					$square_customer = $customer_api->createCustomer( $body );

					$square_customer = json_decode( $square_customer, true );

					if ( isset( $square_customer['customer']['id'] ) ) {
						$square_customer_id = $square_customer['customer']['id'];
						if ( $customer_id ) {
							update_user_meta( $customer_id, '_square_customer_id', $square_customer_id );
						} else {
							$squhposupdate_metaa = wc_get_product( $order_id );
							$squhposupdate_metaa->update_meta_data( '_square_customer_id', $square_customer_id );
							$squhposupdate_metaa->save();
						}
					}
				}
			} else {
				$square_customer_id = null;
			}

			if ( function_exists( 'square_order_sync_add_on' ) ) {
				$data['order_id'] = square_order_sync_add_on( $order, $location_id, $currency, $idempotency_key, $this->token, $endpoint, $square_customer_id );
			}

			$squhposupdate_meta->update_meta_data( 'request_Data' . wp_rand( 1, 1000 ), $data );

			$url     = 'https://connect.' . WC_SQUARE_STAGING_URL . '.com/v2/payments';
			$headers = array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $this->token,
				'Content-Type'  => 'application/json',
				'Cache-Control' => 'no-cache',
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

			$squhposupdate_meta->update_meta_data( 'woosquare_request_results_apay_' . wp_rand( 1, 1000 ), $result );

			if ( is_wp_error( $result ) ) {
				wc_add_notice( __( 'Error: Unable to complete your transaction with square due to some issue. For now you can try some other payment method or try again later.', 'wpexpert-square' ), 'error' );

				throw new Exception( $result->get_error_message() );
			}

			if ( ! empty( $result->errors ) ) {
				if ( 'INVALID_REQUEST_ERROR' === $result->errors[0]->category ) {
					wc_add_notice( __( 'Error: Unable to complete your transaction with square due to some issue. For now you can try some other payment method or try again later.', 'wpexpert-square' ), 'error' );
				}

				if ( 'PAYMENT_METHOD_ERROR' === $result->errors[0]->category || 'VALIDATION_ERROR' === $result->errors[0]->category ) {
					// format errors for display.
					$error_html  = __( 'Payment Error: ', 'wpexpert-square' );
					$error_html .= '<br />';
					$error_html .= '<ul>';

					foreach ( $result->errors as $error ) {
						$error_html .= '<li>' . $error->detail . '</li>';
					}

					$error_html .= '</ul>';

					wc_add_notice( $error_html, 'error' );
				}

				$print_r = 'print_r';
				$errors  = $print_r( $result->errors, true );

				throw new Exception( $errors );
			}

			if ( empty( $result ) ) {
				wc_add_notice( __( 'Error: Unable to complete your transaction with square due to some issue. For now you can try some other payment method or try again later.', 'wpexpert-square' ), 'error' );

				throw new Exception( 'Unknown Error' );
			}

			if ( isset( $result->payment->id ) && 'CAPTURED' === $result->payment->card_details->status ) {

				// Store captured value.
				$squhposupdate_meta->update_meta_data( '_square_charge_captured', 'yes' );

				// Payment complete.
				$order->payment_complete( $result->payment->id );
				$squhposupdate_meta->add_meta_data( 'woosquare_transaction_id', $result->payment->id, true );
				// translators: %1$s is the message, %2$s is the payment id.
				$complete_message = sprintf( __( 'Square charge complete %1$s (Charge ID: %2$s)', 'wpexpert-square' ), $msg, $result->payment->id );

				$order->add_order_note( $complete_message );
				$this->log( "Success: $complete_message" );

			} elseif ( isset( $result->payment->id ) && 'AUTHORIZED' === $result->payment->card_details->status ) {

				// Store captured value.
				$squhposupdate_meta->update_meta_data( '_square_charge_captured', 'no' );
				$squhposupdate_meta = wc_get_order( $order->id );
				$squhposupdate_meta->add_meta_data( 'woosquare_transaction_id', $result->payment->id, true );

				// Mark as on-hold.

				// translators: %1$s is the message, %2$s is the payment id.
				$authorized_message = sprintf( __( 'Square charge authorized %1$s (Authorized ID: %2$s). Process order to take payment, or cancel to remove the pre-authorization.', 'wpexpert-square' ), $msg, $result->payment->id );

				$order->update_status( 'on-hold', $authorized_message );
				$this->log( "Success: $authorized_message" );

				// Reduce stock levels.
				version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->reduce_order_stock() : wc_reduce_stock_levels( $order->id );
			}
				$squhposupdate_meta->save();
			// we got this far which means the payment went through.
			if ( $this->create_customer ) {
				$this->maybe_create_customer( $order );
			}

			// Remove cart.
			WC()->cart->empty_cart();

			// Return thank you page redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		} catch ( Exception $e ) {
			// translators: %s is the error message.
			$this->log( sprintf( __( 'Error: %s', 'wpexpert-square' ), $e->getMessage() ) );

			$order->update_status( 'failed', $e->getMessage() );

			return;
		}
	}

	/**
	 * Tries to create the customer on Square.
	 *
	 * @param object $order An object representing an order, likely from an e-commerce system.
	 */
	public function maybe_create_customer( $order ) {

		$user               = get_current_user_id();
		$square_customer_id = get_user_meta( $user, '_square_customer_id', true );

		$create_customer = true;

		$customer = array(
			'given_name'    => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_first_name : $order->get_billing_first_name(),
			'family_name'   => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_last_name : $order->get_billing_last_name(),
			'email_address' => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_email : $order->get_billing_email(),
			'address'       => array(
				'address_line_1'                  => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_address_1 : $order->get_billing_address_1(),
				'address_line_2'                  => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_address_2 : $order->get_billing_address_2(),
				'locality'                        => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_city : $order->get_billing_city(),
				'administrative_district_level_1' => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_state : $order->get_billing_state(),
				'postal_code'                     => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_postcode : $order->get_billing_postcode(),
				'country'                         => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_country : $order->get_billing_country(),
			),
			'phone_number'  => (string) version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_phone : $order->get_billing_phone(),
			'reference_id'  => ! empty( $user ) ? (string) $user : __( 'Guest', 'woosquare' ),
		);

		// to prevent creating duplicate customer
		// check to make sure this customer does not exist on Square.
		if ( ! empty( $square_customer_id ) ) {
			$square_customer = $this->connect->get_customer( $square_customer_id );

			if ( empty( $square_customer->errors ) ) {
				// customer already exist on Square.
				$create_customer = false;
			}
		}

		if ( $create_customer ) {
			$result = $this->connect->create_customer( $customer );

			// we don't want to halt any processes here just log it.
			if ( is_wp_error( $result ) ) {
				// translators: %s is the customer error.
				$this->log( sprintf( __( 'Error creating customer: %s', 'woosquare' ), $result->get_error_message() ) );
				// translators: %s is the customer error.
				$order->add_order_note( sprintf( __( 'Error creating customer: %s', 'woosquare' ), $result->get_error_message() ) );

			}

			// we don't want to halt any processes here just log it.
			if ( ! empty( $result->errors ) ) {
				// translators: %s is the customer error.
				$this->log( sprintf( __( 'Error creating customer: %s', 'woosquare' ), wp_json_encode( $result->errors ) ) );
				// translators: %s is the customer error.
				$order->add_order_note( sprintf( __( 'Error creating customer: %s', 'woosquare' ), wp_json_encode( $result->errors ) ) );

			}

			// if no errors save Square customer ID to user meta.
			if ( ! is_wp_error( $result ) && empty( $result->errors ) && ! empty( $user ) ) {
				update_user_meta( $user, '_square_customer_id', $result->customer->id );
				// translators: %s is the customer id.
				$order->add_order_note( sprintf( __( 'Customer created on Square: %s', 'woosquare' ), $result->customer->id ) );

			}
		}
	}

	/**
	 * Format an amount to be passed to Square.
	 *
	 * This method formats the given amount to be compatible with Square payment processing.
	 *
	 * @param float  $total    The total amount to be formatted.
	 * @param string $currency (Optional) The currency code for the amount (e.g., "USD").
	 *
	 * @return float The formatted amount.
	 */
	public function format_amount( $total, $currency = '' ) {
		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}

		switch ( strtoupper( $currency ) ) {
			// Zero decimal currencies.
			case 'BIF':
			case 'CLP':
			case 'DJF':
			case 'GNF':
			case 'JPY':
			case 'KMF':
			case 'KRW':
			case 'MGA':
			case 'PYG':
			case 'RWF':
			case 'VND':
			case 'VUV':
			case 'XAF':
			case 'XOF':
			case 'XPF':
				$total = absint( $total );
				break;
			default:
				$total = round( $total, 2 ) * 100; // In cents.
				break;
		}

		return $total;
	}

	/**
	 * Refund a charge.
	 *
	 * @param int    $order_id The ID of the order associated with the charge.
	 * @param float  $amount   The amount to refund. If null, it refunds the full amount.
	 * @param string $reason   Optional. A reason or note for the refund.
	 * @return bool            True on success, false on failure.
	 *
	 * @throws Exception If there is an error during payment capture.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order    = wc_get_order( $order_id );
		$trans_id = $order->get_meta( 'woosquare_transaction_id', true );
		if ( ! $order || ! $trans_id ) {
			return false;
		}

		if ( 'square_apple_pay' . get_transient( 'is_sandbox' ) === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->payment_method : $order->get_payment_method() ) ) {
			try {
				$this->log( "Info: Begin refund for order {$order_id} for the amount of {$amount}" );

				$captured = $order->get_meta( '_square_charge_captured', true );

				$transaction_status = $this->connect->get_transaction_status( $trans_id );

				if ( 'CAPTURED' === $transaction_status ) {

					$currency = $order->get_order_currency();
					$fields   = array(
						'idempotency_key' => uniqid(),
						'payment_id'      => $trans_id,
						'reason'          => $reason,
						'amount_money'    => array(
							'amount'   => (int) $this->format_amount( $amount, $currency ),
							'currency' => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_order_currency() : $order->get_currency(),
						),
					);

					$url     = 'https://connect.' . WC_SQUARE_STAGING_URL . '.com/v2/refunds';
					$headers = array(
						'Accept'        => 'application/json',
						'Authorization' => 'Bearer ' . $this->token,
						'Content-Type'  => 'application/json',
						'Cache-Control' => 'no-cache',
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
									'body'        => wp_json_encode( $fields ),
								)
							)
						)
					);

					if ( is_wp_error( $result ) ) {
						throw new Exception( $result->get_error_message() );

					} elseif ( ! empty( $result->errors ) ) {
						$print_r = 'print_r';
						throw new Exception( 'Error: ' . $print_r( $result->errors, true ) );

					} elseif ( 'APPROVED' === $result->refund->status || 'PENDING' === $result->refund->status ) {
							// translators: %1$s is the refunded amount, %2$s is the refund ID, %3$s is the reason for the refund.
							$refund_message = sprintf( __( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'wpexpert-square' ), wc_price( $result->refund->amount_money->amount / 100 ), $result->refund->id, $reason );
							$order->add_order_note( $refund_message );

							$this->log( 'Success: ' . html_entity_decode( wp_strip_all_tags( $refund_message ) ) );

							return true;
					}
				}
			} catch ( Exception $e ) {
				// translators: %s is the error message.
				$this->log( sprintf( __( 'Error: %s', 'wpexpert-square' ), $e->getMessage() ) );

				return false;
			}
		}
	}

	/**
	 * Logs
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @param string $message - The message.
	 */
	public function log( $message ) {
		if ( $this->logging ) {
			WooSquare_Payment_Logger::log( $message );
		}
	}
}

