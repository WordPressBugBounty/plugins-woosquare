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
 * Represents a custom ACH payment gateway for WooCommerce using Square.
 *
 * This class extends the WC_Payment_Gateway class and provides custom functionality
 * for processing ACH payments with Square.
 */
class WooSquareACHPayment_Gateway extends WC_Payment_Gateway {

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
	 * Flag to enable logging.
	 *
	 * @var bool
	 */
	public $logging;

	/**
	 * Flag to capture payment immediately.
	 *
	 * @var bool
	 */
	protected $capture;

	/**
	 * Indicates whether to create a new customer.
	 *
	 * @var bool
	 */
	public $create_customer;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'square_ach_payment' . get_transient( 'is_sandbox' );
		$this->method_title       = __( 'Square ACH Payment', 'wpexpert-square' );
		$this->method_description = __( 'Square ACH Payment works by adding payments button in a WooCommerce checkout and then sending the details to Square for verification and processing.', 'wpexpert-square' );
		$this->has_fields         = true;
		$this->supports           = array(
			'products',
			'refunds',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' ) === 'yes' ? 'yes' : 'no';
		$this->capture     = $this->get_option( 'capture' ) === 'yes' ? false : true;
		$settings_key      = 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings';
		$settings          = get_option( $settings_key );

		$this->create_customer = ( is_array( $settings ) && isset( $settings['create_customer'] ) && 'yes' === $settings['create_customer'] );
		$this->logging         = $this->get_option( 'logging' ) === 'yes' ? true : false;
		$this->connect         = new WooSquare_Payments_Connect(); // Decouple in future when v2 is ready.
		$this->token           = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );

		$this->connect->set_access_token( $this->token );

		// Hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts_ach_payment' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Check if this gateway is available.
	 */
	public function is_available() {
		$is_available = true;

		if ( 'yes' === $this->enabled ) {
			if ( ! wc_checkout_is_https() ) {
				$is_available = false;
			}

			if ( empty( $this->token ) ) {
				$is_available = true;
			}

			if ( ! $this->token ) {
				$is_available = false;
			}

			// Square only supports US, Canada, and Australia for now.
			if ( ( 'US' !== WC()->countries->get_base_country() ) || ( 'USD' !== get_woocommerce_currency() ) ) {
				$is_available = false;
			}
		} else {
			$is_available = false;
		}

		return apply_filters( 'woocommerce_square_payment_ach_gateway_is_available', $is_available );
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = apply_filters(
			'woocommerce_square_ach_gateway_settings',
			array(
				'enabled'     => array(
					'title'       => __( 'Enable/Disable', 'wpexpert-square' ),
					'label'       => __( 'Enable Square ACH Payment', 'wpexpert-square' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'wpexpert-square' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'wpexpert-square' ),
					'default'     => __( 'ACH Payment (Square)', 'wpexpert-square' ),
				),
				'description' => array(
					'title'       => __( 'Description', 'wpexpert-square' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'wpexpert-square' ),
					'default'     => __( 'Pay with your Bank Account via Square.', 'wpexpert-square' ),
				),
				'capture'     => array(
					'title'       => __( 'Delay Capture', 'woosquare' ),
					'label'       => __( 'Enable Delay Capture', 'woosquare' ),
					'type'        => 'checkbox',
					'description' => __( 'When enabled, the request will only perform an Auth on the provided card. You can then later perform either a Capture or Void.', 'woosquare' ),
					'default'     => 'no',
				),
				'logging'     => array(
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
	 * Payment form on checkout page.
	 */
	public function payment_fields() {
		?>
		<fieldset class="wooSquare-ach-payment">
			<p class="form-row form-row-wide">
				<label for="ach-button"><?php esc_html_e( 'ACH Payment', 'woosquare' ); ?><span class="required">*</span></label>
				<div class="ach-button-div"></div>
				<input type="hidden" id="card_nonce" name="card_nonce" />
				<input type="hidden" id="square_pay_nonce" name="square_pay_nonce" value="<?php echo esc_attr( wp_create_nonce( 'square-pay-nonce' ) ); ?>">
			</p>
			<p>
				<div id="payment-status-container"></div>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Payment_scripts function.
	 */
	public function payment_scripts_ach_payment() {
		if ( ! is_checkout() ) {
			return;
		}
		$location = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );

		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

		global $woocommerce;
		$woocommerce_square_settings = get_option( 'woocommerce_square_settings' );
		$currency_cod                = get_option( 'woocommerce_currency' );
		$country_code                = WC()->countries->get_base_country();
		// Need to add condition square payment enable so disable below script.
		if ( get_transient( 'is_sandbox' ) ) {
			$endpoint    = 'squareupsandbox';
			$environment = 'sandbox';
		} else {
			$endpoint    = 'squareup';
			$environment = 'production';
		}

		if ( get_transient( 'is_sandbox' ) ) {
			$endpoint = 'sandbox.web';
		} else {
			$endpoint = 'web';
		}

		if ( ! wp_script_is( 'squareSDK', 'enqueued' ) && ! wp_script_is( 'squareSDK', 'registered' ) ) {
			wp_enqueue_script(
				'squareSDK',
				'https://' . $endpoint . '.squarecdn.com/v1/square.js',
				array(),
				WOOSQUARE_VERSION,
				true
			);
		}

		wp_enqueue_script( 'woosquare-ach-payment', WOOSQUARE_PLUGIN_URL_PAYMENT . '/js/SquarePaymentsACHPay.js?apprand=' . wp_rand(), array(), WOOSQUARE_VERSION, true );
		wp_localize_script(
			'woosquare-ach-payment',
			'square_ach_params',
			array(
				'application_id' => WOOSQU_PLUS_APPID,
				'lid'            => apply_filters( 'modify_square_location_id', $location ),
				'merchant_name'  => 'Square ACH Payment',
				'order_total'    => $woocommerce->cart->total,
				'environment'    => $environment,
				'currency_code'  => $currency_cod,
				'country_code'   => $country_code,
				'currency_sym'   => get_woocommerce_currency_symbol(),
				'redirectURL'    => wc_get_checkout_url(),
				'transactionId'  => wp_rand(),
				'sandbox'        => get_transient( 'is_sandbox' ),
			)
		);
		wp_enqueue_script( 'woosquare-ach-payment' );

		wp_enqueue_style( 'woocommerce-square-ach-styles', WOOSQUARE_PLUGIN_URL_PAYMENT . '/css/SquareFrontendStyles_ach_payment.css', array(), '1.0', 'all' );

		return true;
	}

	/**
	 * Handles Square customer creation and synchronization with WooCommerce orders.
	 *
	 * This function checks if a customer exists in Square, and if not, it creates a new customer.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 */
	public function handle_square_customer_creation( $order ) {
		// Check if we need to sync the customer with Square.

		if ( get_option( 'woo_square_customer_sync_square_order_sync' ) === '1' || $this->create_customer ) {

			// Initialize Square customer ID.
			$square_customer_id = null;

			// Get the customer ID from WooCommerce.
			$customer_id = $order->get_customer_id();

			// Fetch the Square customer ID based on the customer or parent order.
			if ( $customer_id ) {
				$square_customer_id = get_user_meta( $customer_id, '_square_customer_id', true );
			} else {
				$square_customer_id = $order->get_meta( '_square_customer_id', true );
			}

			// Check if the customer exists in Square.
			if ( $square_customer_id ) {
				$response = $this->fetch_square_customer( $square_customer_id );

				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();

					$square_customer_id = null; // Customer does not exist.
				} else {
					$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

					if ( isset( $response_body['customer'] ) ) {
						$customer = $response_body['customer'];
						$order->update_meta_data( 'retrieveCustomer', wp_json_encode( $customer ) );
					} else {

						$square_customer_id = null; // Customer does not exist.
					}
				}
			}

			// If the customer doesn't exist in Square, try to search by email.
			$search_customer = $this->search_square_customer_by_email( $order->get_billing_email() );

			$order->update_meta_data( 'retrievesearchCustomer', wp_json_encode( $search_customer ) );

			if ( empty( $search_customer->customers[0]->id ) ) {
				// Check if we need to create a new customer in Square.
				if ( empty( $square_customer_id ) || get_option( 'woo_square_create_customer_guest' ) === '1' || ! is_user_logged_in() || $this->create_customer ) {
					$order->update_meta_data( '_createcustomer', '1' );

					// Ensure the customer object is valid.
					if ( empty( get_object_vars( $search_customer ) ) ) {
						$order->update_meta_data( 'get_object_vars_searchCustomer', empty( get_object_vars( $search_customer ) ) );

						// Prepare to create a new customer in Square.
						$square_customer_id = $this->create_new_square_customer( $order );

					}
				}
			} else {
				$square_customer_id = $search_customer->customers[0]->id;
				$order->update_meta_data( '_square_customer_id', $square_customer_id );
				// translators: %s is the customer id.
				$order->add_order_note( sprintf( __( 'Customer created or updated on Square: %s', 'woosquare' ), $square_customer_id ) );
			}
		}
	}

	/**
	 * Fetch a Square customer by ID.
	 *
	 * @param string $square_customer_id The Square customer ID.
	 * @return array|WP_Error The response from Square API or WP_Error if the request failed.
	 */
	public function fetch_square_customer( $square_customer_id ) {
		$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers/' . $square_customer_id;
		$headers = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $this->token,
			'Content-Type'  => 'application/json',
			'Cache-Control' => 'no-cache',
		);

		return wp_remote_get(
			$url,
			array(
				'headers'     => $headers,
				'httpversion' => '1.0',
				'sslverify'   => false,
			)
		);
	}

	/**
	 * Search a Square customer by email.
	 *
	 * @param string $email The customer's email address.
	 * @return array|WP_Error The response from Square API or WP_Error if the request failed.
	 */
	public function search_square_customer_by_email( $email ) {
		$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers/search';
		$headers = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $this->token,
			'Content-Type'  => 'application/json',
			'Cache-Control' => 'no-cache',
		);

		$customer_data = array(
			'query' => array(
				'filter' => array(
					'email_address' => array(
						'exact' => strtolower( sanitize_email( $email ) ),
					),
				),
			),
		);

		return json_decode(
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
	}

	/**
	 * Retrieve the shipping address from the WooCommerce order.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @return array The shipping address details.
	 */
	public function get_order_billing_shipping_address( $order ) {
		$shipping_country = sanitize_text_field( $order->get_shipping_country() ) ? sanitize_text_field( $order->get_shipping_country() ) : sanitize_text_field( $order->get_billing_country() );

		if ( ! empty( $shipping_country ) ) {
			return array(
				'address_line_1'                  => sanitize_text_field( $order->get_shipping_address_1() ) ? sanitize_text_field( $order->get_shipping_address_1() ) : sanitize_text_field( $order->get_billing_address_1() ),
				'address_line_2'                  => sanitize_text_field( $order->get_shipping_address_2() ) ? sanitize_text_field( $order->get_shipping_address_2() ) : sanitize_text_field( $order->get_billing_address_2() ),
				'locality'                        => sanitize_text_field( $order->get_shipping_city() ) ? sanitize_text_field( $order->get_shipping_city() ) : sanitize_text_field( $order->get_billing_city() ),
				'administrative_district_level_1' => sanitize_text_field( $order->get_shipping_state() ) ? sanitize_text_field( $order->get_shipping_state() ) : sanitize_text_field( $order->get_billing_state() ),
				'postal_code'                     => sanitize_text_field( $order->get_shipping_postcode() ) ? sanitize_text_field( $order->get_shipping_postcode() ) : sanitize_text_field( $order->get_billing_postcode() ),
				'country'                         => sanitize_text_field( $order->get_shipping_country() ) ? sanitize_text_field( $order->get_shipping_country() ) : sanitize_text_field( $order->get_billing_country() ),
			);
		}

		return array();
	}

	/**
	 * Create a new customer in Square.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @return string|null The Square customer ID or null if failed.
	 */
	public function create_new_square_customer( $order ) {
		$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers';
		$headers = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $this->token,
			'Content-Type'  => 'application/json',
			'Cache-Control' => 'no-cache',
		);

		$shipping_address = $this->get_order_billing_shipping_address( $order );
		$customer_data    = array(
			'given_name'    => null !== $order->get_shipping_first_name() ? sanitize_text_field( $order->get_shipping_first_name() ) : sanitize_text_field( $order->get_billing_first_name() ),
			'family_name'   => null !== $order->get_shipping_last_name() ? sanitize_text_field( $order->get_shipping_last_name() ) : sanitize_text_field( $order->get_billing_last_name() ),
			'email_address' => sanitize_email( $order->get_billing_email() ),
			'address'       => $shipping_address,
			'phone_number'  => sanitize_text_field( $order->get_billing_phone() ),
			'reference_id'  => $order->get_customer_id() ? (string) $order->get_customer_id() : sanitize_text_field( __( 'Guest', 'woosquare' ) ),
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

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $response_body['customer']['id'] ) ) {
			$square_customer_id = $response_body['customer']['id'];
			$order->update_meta_data( '_square_customer', $square_customer_id );
			// translators: %s is the customer id.
			$order->add_order_note( sprintf( __( 'Customer created or updated on Square: %s', 'woosquare' ), $square_customer_id ) );
			return $square_customer_id;
		}

		return null;
	}


	/**
	 * Process the payment.
	 *
	 * @param  int  $order_id The order ID.
	 * @param  bool $retry    Whether to retry the payment.
	 * @throws Exception If an error occurs during payment processing.
	 */
	public function process_payment( $order_id, $retry = true ) {
		if ( ! isset( $_POST['square_pay_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['square_pay_nonce'] ) ), 'square-pay-nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		$order         = wc_get_order( $order_id );
		$nonce         = isset( $_POST['square_nonce'] ) ? wc_clean( sanitize_text_field( wp_unslash( $_POST['square_nonce'] ) ) ) : '';
		$errors        = isset( $_POST['errors'] ) ? sanitize_text_field( wp_unslash( $_POST['errors'] ) ) : '';
		$noncedatatype = isset( $_POST['noncedatatype'] ) ? sanitize_text_field( wp_unslash( $_POST['noncedatatype'] ) ) : '';
		$card_data     = isset( $_POST['cardData'] ) ? sanitize_text_field( wp_unslash( $_POST['cardData'] ) ) : '';
		$order->update_meta_data( '_POST_requuest' . wp_rand( 1, 1000 ), sanitize_text_field( $_POST ) );
		$order->update_meta_data( 'errors_ach', $errors );
		$order->update_meta_data( 'errors_noncedatatype', $noncedatatype );
		$order->update_meta_data( '_POST_requuest', $card_data );

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

			$sandbox_prefix = '';
			$endpoint       = 'squareup';
			$location_id    = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
			if ( ! empty( get_transient( 'is_sandbox' ) ) ) {
				$sandbox_prefix = ' via Sandbox ';
				$endpoint       = 'squareupsandbox';
				$nonce          = 'bnon:bank-nonce-ok';
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

			$this->handle_square_customer_creation( $order );

			if ( function_exists( 'square_order_sync_add_on' ) ) {
				$data['order_id'] = square_order_sync_add_on( $order, $location_id, $currency, $idempotency_key, $this->token, $endpoint, $square_customer_id );
			} elseif ( ! empty( $square_customer_id ) ) {
				$data['customer_id'] = $square_customer_id;
			}
			$order->update_meta_data( 'request_Data' . time(), $data );

			$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/payments';
			$headers = array(
				'Accept'         => 'application/json',
				'Authorization'  => 'Bearer ' . $this->token,
				'Square-Version' => '2021-11-17',
				'Content-Type'   => 'application/json',
				'Cache-Control'  => 'no-cache',
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
			$order->update_meta_data( 'woosquare_request_results_ach_' . wp_rand( 1, 1000 ), $result );

			if ( isset( $result->payment->id ) && get_transient( 'is_sandbox' ) && 'PENDING' === $result->payment->status ) {

				// Payment complete.
				$order->add_meta_data( 'woosquare_transaction_id', $result->payment->id, true );
				$amount = number_format( $result->payment->amount_money->amount / 100, 2 ); // Assuming amount is in cents.
				// Mark as on-hold.
				// Translators: %1$s is the message, %2$s is the payment ID.
				$authorized_message = sprintf( __( 'Square ACH Payment %1$s complete for $%3$s (Transaction ID: %2$s). Will be charged after 1 minute.', 'woosquare' ), $sandbox_prefix, $result->payment->id, $amount );
				$order->update_status( 'on-hold', $authorized_message );
				$order->add_order_note( $authorized_message );
				$this->log( "Success: $authorized_message" );
				$order->save();
				// Reduce stock levels.
				version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->reduce_order_stock() : wc_reduce_stock_levels( $order->id );

			} elseif ( isset( $result->payment->id ) && 'CAPTURED' === $result->payment->card_details->status ) {

				// Store captured value.
				$order->update_meta_data( '_square_charge_captured', 'yes' );

				// Payment complete.
				$order->payment_complete( $result->payment->id );
				$order->add_meta_data( 'woosquare_transaction_id', $result->payment->id, true );
				// Translators: %1$s is the message, %2$s is the payment ID.
				$complete_message = sprintf( __( 'Square charge complete %1$s (Charge ID: %2$s)', 'wpexpert-square' ), $msg, $result->payment->id );
				$order->add_order_note( $complete_message );
				$order->update_status( 'on-hold', $complete_message );
				$order->save();
				$this->log( "Success: $complete_message" );

			} else {
				$order->add_meta_data( 'woosquare_transaction_id', $result->payment->id, true );

				// Mark as on-hold.
				// Translators: %1$s is the message, %2$s is the payment ID.
				$authorized_message = sprintf( __( 'ACH Payment %1$s (Payment ID: %2$s). Will be charged within three to five days.', 'woosquare' ), $msg, $result->payment->id );

				$order->add_order_note( $authorized_message );
				$this->log( "Success: $authorized_message" );

				// Reduce stock levels.
				version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->reduce_order_stock() : wc_reduce_stock_levels( $order->id );

			}

			if ( ! isset( $result->payment->id ) ) {
				$message = '';
				if ( ! empty( $result->card_details->errors ) ) {
					foreach ( $result->card_details->errors as $error ) {
						$message .= $error->detail;
						if ( isset( $error->field ) ) {
							$message .= $error->field . ' - ' . $error->detail;
						}
					}
				} else {
					foreach ( $result->errors as $error ) {
						$message .= $error->code . ' - ' . $error->detail . ' - ' . $error->category;
					}
					$message .= '</br><a target="_blank" href="https://developer.squareup.com/docs/payments-api/error-codes#createpayment-errors"> ERROR CODE REFERENCES </a>';
				}

				$order->save();
				wc_add_notice( $message, 'error' );
				// Translators: %s is the error message explaining why the Square payment failed.
				$message = sprintf( __( 'Square Payment Failed  %s .', 'woosquare' ), $message );

				$order->update_status( 'failed', $message );
				return;
			}

			// Remove cart.
			WC()->cart->empty_cart();

			$order->save();
			// Return thank you page redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		} catch ( Exception $e ) {
			// Translators: Error message placeholder in a log entry. Placeholder: Error details.
			$this->log( sprintf( __( 'Error: %s', 'wpexpert-square' ), $e->getMessage() ) );

			$order->update_status( 'failed', $e->getMessage() );
			$order->save();
			return;
		}
	}

	/**
	 * Tries to create the customer on Square.
	 *
	 * @param object $order An object representing an order. It likely contains information about the customer.
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

		// To prevent creating duplicate customer, check to make sure this customer does not exist on Square.
		if ( ! empty( $square_customer_id ) ) {
			$square_customer = $this->connect->get_customer( $square_customer_id );

			if ( empty( $square_customer->errors ) ) {
				// Customer already exists on Square.
				$create_customer = false;
			}
		}

		if ( $create_customer ) {
			$result = $this->connect->create_customer( $customer );

			// We don't want to halt any processes here, just log it.
			if ( is_wp_error( $result ) ) {
				// Translators: Error message placeholder in a log entry. Placeholder: Error details.
				$this->log( sprintf( __( 'Error creating customer: %s', 'woosquare' ), $result->get_error_message() ) );
				// Translators: Error message placeholder in a log entry. Placeholder: Error details.
				$order->add_order_note( sprintf( __( 'Error creating customer: %s', 'woosquare' ), $result->get_error_message() ) );
			}

			// We don't want to halt any processes here, just log it.
			if ( ! empty( $result->errors ) ) {
				$print_r = 'print_r';
				// Translators: Error message placeholder in a log entry. Placeholder: Error details.
				$this->log( sprintf( __( 'Error creating customer: %s', 'woosquare' ), $print_r( $result->errors, true ) ) );
				// Translators: Error message placeholder in a log entry. Placeholder: Error details.
				$order->add_order_note( sprintf( __( 'Error creating customer: %s', 'woosquare' ), $print_r( $result->errors, true ) ) );
			}

			// If no errors, save Square customer ID to user meta.
			if ( ! is_wp_error( $result ) && empty( $result->errors ) && ! empty( $user ) ) {
				update_user_meta( $user, '_square_customer_id', $result->customer->id );
				// Translators: Error message placeholder in a log entry. Placeholder: Error details.
				$order->add_order_note( sprintf( __( 'Customer created on Square: %s', 'woosquare' ), $result->customer->id ) );
			}
		}
	}

	/**
	 * Process amount to be passed to Square.
	 *
	 * @param  float  $total    The total amount to be formatted.
	 * @param  string $currency The currency code for the amount (optional).
	 * @return float The formatted amount in the required format for Square.
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
	 * @param  int    $order_id The ID of the order for which the refund is being processed.
	 * @param  float  $amount   The amount to be refunded (optional). If not specified, it may refund the full amount.
	 * @param  string $reason   The reason for the refund (optional).
	 * @return bool True on successful refund, false on failure.
	 * @throws Exception If there is an error during payment capture.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		$trans_id = $order->get_meta( 'woosquare_transaction_id', true );

		if ( ! $order || ! $trans_id ) {
			return false;
		}

		if ( 'square_ach_payment' . get_transient( 'is_sandbox' ) === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->payment_method : $order->get_payment_method() ) ) {
			try {
				$this->log( "Info: Begin refund for order {$order_id} for the amount of {$amount}" );

				$captured           = $order->get_meta( '_square_charge_captured', true );
				$transaction_status = $order->data['status'];

				if ( 'processing' === $transaction_status || 'completed' === $transaction_status ) {

					$body                    = array();
					$currency                = $order->get_order_currency();
					$body['idempotency_key'] = uniqid();

					if ( ! is_null( $amount ) ) {
						$body['amount_money'] = array(
							'amount'   => (int) $this->format_amount( $amount, $currency ),
							'currency' => $currency,
						);
						$body['payment_id']   = $trans_id;
					}

					if ( $reason ) {
						$body['reason'] = $reason;
					}

					$result = $this->connect->refund_transaction( $trans_id, $body );

					if ( is_wp_error( $result ) ) {
						throw new Exception( $result->get_error_message() );

					} elseif ( ! empty( $result->errors ) ) {
						$print_r = 'print_r';
						throw new Exception( 'Error: ' . $print_r( $result->errors, true ) );

					} elseif ( 'APPROVED' === $result->refund->status || 'PENDING' === $result->refund->status ) {
						// Translators: Error message placeholder in a log entry. Placeholder: Error details.
						$refund_message = sprintf( __( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'wpexpert-square' ), wc_price( $result->refund->amount_money->amount / 100 ), $result->refund->id, $reason );

						$order->add_order_note( $refund_message );

						$this->log( 'Success: ' . html_entity_decode( wp_strip_all_tags( $refund_message ) ) );

						return true;
					}
				}
			} catch ( Exception $e ) {
				// Translators: Error message placeholder in a log entry. Placeholder: Error details.
				$this->log( sprintf( __( 'Error: %s', 'wpexpert-square' ), $e->getMessage() ) );

				return false;
			}
		}
	}

	/**
	 * Logs a message.
	 *
	 * @param string $message The message to log.
	 */
	public function log( $message ) {
		if ( $this->logging ) {
			WooSquare_Payment_Logger::log( $message );
		}
	}
}
