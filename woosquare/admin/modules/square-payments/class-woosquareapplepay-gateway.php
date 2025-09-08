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

		if ( get_option( 'woo_square_plus_apple_pay_domain_registered' ) === 'yes' ) {
			$woocommerce_square_apple_pay_settings                    = get_option( 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_settings' );
			$woocommerce_square_apple_pay_settings['domain_verified'] = 'yes';
			update_option( 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_settings', $woocommerce_square_apple_pay_settings );
		}

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' ) === 'yes' ? 'yes' : 'no';
		$this->capture     = $this->get_option( 'capture' ) === 'yes' ? false : true;
		$this->logging     = $this->get_option( 'logging' ) === 'yes' ? true : false;
		$this->connect     = new WooSquare_Payments_Connect(); // decouple in future when v2 is ready.
		$this->token       = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );

		$this
			->connect
			->set_access_token( $this->token );

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( stripos( $user_agent, 'Safari' ) !== false && stripos( $user_agent, 'Chrome' ) === false && stripos( $user_agent, 'Chromium' ) === false ) {
			add_action(
				'wp_enqueue_scripts',
				array(
					$this,
					'payment_scripts_applepay',
				)
			);
		}

		add_action(
			'admin_notices',
			array(
				$this,
				'admin_notices_applepay',
			)
		);
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);
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

			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
			if ( stripos( $user_agent, 'Chrome' ) !== false ) {
				$is_available = false;
			} elseif ( stripos( $user_agent, 'Safari' ) !== false ) {
				$is_available = true;
			}
			if ( ! WOOSQU_ENABLE_STAGING && ! wc_checkout_is_https() ) {
				$is_available = false;
			}

			if ( ! WOOSQU_ENABLE_STAGING && empty( $this->token ) ) {
				$is_available = true;
			}

			if ( ! $this->token ) {
				$is_available = false;
			}

			// Square only supports US, Canada and Australia for now.
			if ( ( 'US' !== WC()->countries->get_base_country()
				&& 'CA' !== WC()->countries->get_base_country()
				&& 'GB' !== WC()->countries->get_base_country()
				&& 'ES' !== WC()->countries->get_base_country()
				&& 'IE' !== WC()->countries->get_base_country()
				&& 'FR' !== WC()->countries->get_base_country()
				&& 'AU' !== WC()->countries->get_base_country() ) || ( 'USD' !== get_woocommerce_currency()
				&& 'CAD' !== get_woocommerce_currency()
				&& 'EUR' !== get_woocommerce_currency()
				&& 'AUD' !== get_woocommerce_currency()
				&& 'GBP' !== get_woocommerce_currency() )
			) {
				$is_available = false;
			}
		} else {
			$is_available = false;
		}
		// enabled for chrome for debug.
		return apply_filters( 'woocommerce_square_payment_applepay_gateway_is_available', $is_available );
	}

	/**
	 * Initialize Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = apply_filters(
			'woocommerce_squaregpay_gateway_settings',
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
			<div id="card-container"></div>
			<span id="browser_support_msg"></span>
			<input type="hidden" id="square_pay_nonce" name="square_pay_nonce" value="<?php echo esc_attr( wp_create_nonce( 'square-pay-nonce' ) ); ?>">
		</div>
		<div id="payment-status-container"></div>

		<?php
	}

	/**
	 * Payment_scripts function.
	 */
	public function payment_scripts_applepay() {
		if ( ! is_checkout() && ! is_product() && ! is_cart() ) {
			return;
		}
		$location                         = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( 'no' === $woocommerce_square_plus_settings['express_checkout_enabled'] && ! is_checkout() ) {
			return;
		}
		global $woocommerce;
		$woocommerce_square_settings = get_option( 'woocommerce_square_settings' );

		$currency_cod = get_option( 'woocommerce_currency' );
		$country_code = WC()->countries->get_base_country();
		// need to add condition square payment enable so disable below script.
		if ( get_transient( 'is_sandbox' ) ) {
			$endpoint     = 'squareupsandbox';
			$environment  = 'development';
			$web_endpoint = 'sandbox.web';
		} else {
			$endpoint     = 'squareup';
			$environment  = 'production';
			$web_endpoint = 'web';
		}
		if ( ! wp_script_is( 'squareSDK', 'enqueued' ) && ! wp_script_is( 'squareSDK', 'registered' ) ) {
			wp_enqueue_script(
				'squareSDK',
				'https://' . $web_endpoint . '.squarecdn.com/v1/square.js',
				array(),
				WOOSQUARE_VERSION,
				true
			);
		}
		global $product;
		if ( ! empty( $product ) ) {
			$amount = number_format( 1 * $product->get_price(), 2, '.', '' );
		}
		wp_register_script( 'woosquare-apple-pay', plugin_dir_url( __FILE__ ) . 'js/SquarePaymentsApplePay.js?apprand=' . wp_rand(), array(), WOOSQUARE_VERSION, true );
		wp_localize_script(
			'woosquare-apple-pay',
			'squareapplepay_params',
			array(
				'application_id'       => WOOSQU_PLUS_APPID,
				'lid'                  => apply_filters( 'modify_square_location_id', $location ),
				'merchant_name'        => 'Square Apple Pay',
				'order_total'          => $woocommerce->cart->total,
				'currency_sym'         => get_woocommerce_currency_symbol(),
				'environment'          => $environment,
				'currency_code'        => $currency_cod,
				'country_code'         => $country_code,
				'sandbox'              => get_transient( 'is_sandbox' ),
				'ewallet_button_color' => $woocommerce_square_plus_settings['button_color'],
				'square_pay_nonce'     => wp_create_nonce( 'square-pay-nonce' ),
				'get_price'            => isset( $amount ) ? $amount : 0,
			)
		);
		wp_enqueue_script( 'woosquare-apple-pay' );

		wp_enqueue_style( 'woocommerce-square-apple-pay-styles', WOOSQUARE_PLUGIN_URL_PAYMENT . '/css/SquareFrontendStyles_apple_pay.css?apprand=' . wp_rand(), array(), WOOSQUARE_VERSION );

		return true;
	}

	/**
	 * Helper function to get order data based on WooCommerce version.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @param string   $legacy_field The legacy field name.
	 * @param string   $new_field_getter The getter method for the new field.
	 * @return mixed The value of the order field.
	 */
	public function get_order_field( $order, $legacy_field, $new_field_getter ) {
		return version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->$legacy_field : $order->$new_field_getter();
	}

	/**
	 * Helper function to add non-empty fields to an array.
	 *
	 * @param array  $data_array The array to add to.
	 * @param string $key The key for the value.
	 * @param mixed  $value The value to add if not empty.
	 */
	public function add_if_not_empty( &$data_array, $key, $value ) {
		if ( ! empty( $value ) ) {
			$data_array[ $key ] = $value;
		}
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
		$order = wc_get_order( $order_id );
		$nonce = isset( $_POST['square_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['square_nonce'] ) ) : '';

		$order->update_meta_data( '_POST_requuest' . wp_rand( 1, 1000 ), $_POST );
		$order->update_meta_data( 'errors_apay', isset( $_POST['errors'] ) ? sanitize_text_field( wp_unslash( $_POST['errors'] ) ) : '' );
		$order->update_meta_data( 'errors_noncedatatype', isset( $_POST['noncedatatype'] ) ? sanitize_text_field( wp_unslash( $_POST['noncedatatype'] ) ) : '' );
		$order->update_meta_data( 'errors_cardData', isset( $_POST['cardData'] ) ? sanitize_text_field( wp_unslash( $_POST['cardData'] ) ) : '' );

		$currency = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_order_currency() : $order->get_currency();
		$this->log( "Info: Begin processing payment for order {$order_id} for the amount of {$order->get_total() }" );
		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( isset( $woocommerce_square_plus_settings['Send_customer_info'] ) && 'yes' === $woocommerce_square_plus_settings['Send_customer_info'] ) {
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

		if ( function_exists( 'square_order_sync_add_on' ) ) {
			$amount = (int) round( $this->format_amount( $order->get_total(), $currency ), 1 );
		} else {
			$amount = (int) $this->format_amount( $order->get_total(), $currency );
		}

		// Get buyer email.
		$buyer_email = $this->get_order_field( $order, 'billing_email', 'get_billing_email' );

		// Initialize billing_address array and dynamically populate it.
		$billing_address = array();
		$this->add_if_not_empty( $billing_address, 'address_line_1', $this->get_order_field( $order, 'billing_address_1', 'get_billing_address_1' ) );
		$this->add_if_not_empty( $billing_address, 'address_line_2', $this->get_order_field( $order, 'billing_address_2', 'get_billing_address_2' ) );
		$this->add_if_not_empty( $billing_address, 'locality', $this->get_order_field( $order, 'billing_city', 'get_billing_city' ) );
		$this->add_if_not_empty( $billing_address, 'administrative_district_level_1', $this->get_order_field( $order, 'billing_state', 'get_billing_state' ) );
		$this->add_if_not_empty( $billing_address, 'postal_code', $this->get_order_field( $order, 'billing_postcode', 'get_billing_postcode' ) );
		$this->add_if_not_empty( $billing_address, 'country', $this->get_order_field( $order, 'billing_country', 'get_billing_country' ) );

		$idempotency_key = uniqid();

		$data = array(
			'idempotency_key' => $idempotency_key,
			'amount_money'    => array(
				'amount'   => $amount,
				'currency' => $currency,
			),
			'reference_id'    => (string) $order->get_order_number(),
			'autocomplete'    => $this->capture,
			'source_id'       => $nonce,
			'note'            => apply_filters( 'woosquare_payment_order_note', 'WooCommerce: Order #' . (string) $order->get_order_number(), $order ),
		);

		// Conditionally add buyer_email_address if it's not empty.
		$this->add_if_not_empty( $data, 'buyer_email_address', $buyer_email );

		// Conditionally add billing_address if not empty.
		if ( ! empty( $billing_address ) ) {
			$data['billing_address'] = $billing_address;
		}

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

		// create customer.
		$square_customer_id = null;
		$parent_order_id    = null;

		if ( ! empty( get_option( 'woo_square_create_customer_guest' ) ) && ( get_option( 'woo_square_create_customer_guest' ) === '1' ) ) {
			// check if customer exist.
			if ( empty( $order->get_customer_id() ) ) {
				$customer_id = wp_rand();
			} else {
				$customer_id = $order->get_customer_id();
			}

			if ( $customer_id ) {
				$square_customer_id = get_user_meta( $customer_id, '_square_customer_id', true );
			} elseif ( $parent_order_id ) {
				$parent_order       = wc_get_order( $parent_order_id );
				$square_customer_id = $parent_order->get_meta( '_square_customer_id', true );
			} else {
				$square_customer_id = $order->get_meta( '_square_customer_id', true );

			}

			// check if there is customer id and not exist in square account.
			if ( $square_customer_id ) {
				try {
					$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers/' . $square_customer_id;
					$headers = array(
						'Accept'        => 'application/json',
						'Authorization' => 'Bearer ' . $this->token,
						'Content-Type'  => 'application/json',
						'Cache-Control' => 'no-cache',
					);

					$response = wp_remote_get(
						$url,
						array(
							'headers'     => $headers,
							'httpversion' => '1.0',
							'sslverify'   => false,
						)
					);

					if ( is_wp_error( $response ) ) {
								// Handle error.
								$error_message = $response->get_error_message();
								$this->log( 'Square API Error: ' . $error_message );
								$square_customer_id = null; // customer not exist.
					} else {
						$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

						if ( isset( $response_body['customer'] ) ) {
							$customer = $response_body['customer'];
							$order->update_meta_data( 'retrieveCustomer', wp_json_encode( $customer ) );
						} else {
							$print_r = 'print_r';
							// Handle API error.
							$this->log( 'Square API Error: ' . $print_r( $response_body, true ) );
							$square_customer_id = null; // customer not exist.
						}
					}
				} catch ( Exception $ex ) {
					// customer not exist.
					$square_customer_id = null;
				}
			}
			$url     = 'https://connect.' . $endpoint . '.com/v2/customers/search';
			$headers = array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $this->token,
				'Content-Type'  => 'application/json',
				'Cache-Control' => 'no-cache',
			);

			$customer_data   = array(
				'query' => array(
					'filter' => array(
						'email_address' => array(
							'exact' => $order->get_billing_email(),
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

			if ( ! empty( get_option( 'woo_square_create_customer_guest' ) ) && ( get_option( 'woo_square_create_customer_guest' ) === '1' ) && ( ! is_user_logged_in() ) ) {

				if ( empty( get_object_vars( $search_customer ) ) ) {

					if ( ! $square_customer_id ) {

						$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers';
						$headers = array(
							'Accept'        => 'application/json',
							'Authorization' => 'Bearer ' . $this->token,
							'Content-Type'  => 'application/json',
							'Cache-Control' => 'no-cache',
						);

						$shipping_address = array(
							'address_line_1' => sanitize_text_field( $order->get_shipping_address_1() ),
							'address_line_2' => sanitize_text_field( $order->get_shipping_address_2() ),
							'locality'       => sanitize_text_field( $order->get_shipping_city() ),
							'postal_code'    => sanitize_text_field( $order->get_shipping_postcode() ),
							'country'        => sanitize_text_field( $order->get_shipping_country() ),
							'administrative_district_level_1' => sanitize_text_field( $order->get_shipping_state() ),
						);

						$customer_data = array(
							'given_name'    => $order->get_shipping_first_name() ? $order->get_shipping_first_name() : $order->get_billing_first_name(),
							'family_name'   => $order->get_shipping_last_name() ? $order->get_shipping_last_name() : $order->get_billing_last_name(),
							'email_address' => $order->get_billing_email(),
							'address'       => $shipping_address,
							'phone_number'  => $order->get_billing_phone(),
							'reference_id'  => $customer_id ? (string) $customer_id : __( 'Guest', 'woosquare' ),
						);

						if ( empty( $_POST['square_customerid'] ) ) {
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
								$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

								if ( isset( $response_body['customer']['id'] ) ) {
										$square_customer_id = $response_body['customer']['id'];
										$order->update_meta_data( '_square_customer', $square_customer_id );

									if ( $customer_id ) {
										update_user_meta( $customer_id, '_square_customer_id', $square_customer_id );
									} elseif ( $parent_order_id ) {
										$order->update_meta_data( '_square_customer_id', $square_customer_id );
									} else {
										$order->update_meta_data( '_square_customer_id', $square_customer_id );
									}

									if ( empty( $order->get_customer_id() ) ) {
										$order->update_meta_data( '_square_customer_id', $square_customer_id );
									}
								} else {
																$print_r = 'print_r';
																// Handle API error.
																$this->log( 'Square API Error: ' . $print_r( $response_body, true ) );
								}
							}
						} else {
							$square_customer_id = sanitize_text_field( wp_unslash( $_POST['square_customerid'] ) );
							$order->update_meta_data( '_square_customer', $square_customer_id );
						}
					}
				} elseif ( $order->get_billing_email() === $search_customer->customers[0]->email_address ) {

					$square_customer_id = $search_customer->customers[0]->id;
					$order->update_meta_data( '_square_customer_id', $square_customer_id );

				}
			} elseif ( empty( get_object_vars( $search_customer ) ) ) {
				if ( ! $square_customer_id ) {

					$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers';
					$headers = array(
						'Accept'        => 'application/json',
						'Authorization' => 'Bearer ' . $this->token,
						'Content-Type'  => 'application/json',
						'Cache-Control' => 'no-cache',
					);

					$shipping_country = sanitize_text_field( $order->get_shipping_country() );
					if ( ! empty( $shipping_country ) ) {
						$shipping_address = array(
							'address_line_1' => sanitize_text_field( $order->get_shipping_address_1() ),
							'address_line_2' => sanitize_text_field( $order->get_shipping_address_2() ),
							'locality'       => sanitize_text_field( $order->get_shipping_city() ),
							'postal_code'    => sanitize_text_field( $order->get_shipping_postcode() ),
							'country'        => sanitize_text_field( $order->get_shipping_country() ),
							'administrative_district_level_1' => sanitize_text_field( $order->get_shipping_state() ),
						);
					}

					$customer_data = array(
						'given_name'    => $order->get_shipping_first_name() ? $order->get_shipping_first_name() : $order->get_billing_first_name(),
						'family_name'   => $order->get_shipping_last_name() ? $order->get_shipping_last_name() : $order->get_billing_last_name(),
						'email_address' => $order->get_billing_email(),
						'address'       => $shipping_address,
						'phone_number'  => $order->get_billing_phone(),
						'reference_id'  => $customer_id ? (string) $customer_id : __( 'Guest', 'woosquare' ),
					);

					if ( empty( $_POST['square_customerid'] ) ) {
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
							$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

							if ( isset( $response_body['customer']['id'] ) ) {
										$square_customer_id = $response_body['customer']['id'];
										$order->update_meta_data( '_square_customer', $square_customer_id );

								if ( $customer_id ) {
									update_user_meta( $customer_id, '_square_customer_id', $square_customer_id );
								} elseif ( $parent_order_id ) {
									$order->update_meta_data( '_square_customer_id', $square_customer_id );
								} else {
									$order->update_meta_data( '_square_customer_id', $square_customer_id );
								}

								if ( empty( $order->get_customer_id() ) ) {
									$order->update_meta_data( '_square_customer_id', $square_customer_id );
								}
							} else {
														$print_r = 'print_r';
														// Handle API error.
														$this->log( 'Square API Error: ' . $print_r( $response_body, true ) );
							}
						}
					} else {
						$square_customer_id = sanitize_text_field( wp_unslash( $_POST['square_customerid'] ) );
						$order->update_meta_data( '_square_customer', $square_customer_id );
					}
				}
			} elseif ( $order->get_billing_email() === $search_customer->customers[0]->email_address ) {

				$square_customer_id = $search_customer->customers[0]->id;
				$order->update_meta_data( '_square_customer_id', $square_customer_id );

			}
		}
		$user_id = $order->get_customer_id();
		if ( empty( $user_id ) ) {
			$_square_customer_id = $order->get_meta( '_square_customer_id', true );
		} else {
			$_square_customer_id = get_user_meta( $user_id, '_square_customer_id', true );
		}
		$fields['customer_id'] = $_square_customer_id;
		if ( function_exists( 'square_order_sync_add_on' ) ) {
			$data['order_id'] = square_order_sync_add_on( $order, $location_id, $currency, $idempotency_key, $this->token, $endpoint, $square_customer_id );
		}

		$order->update_meta_data( 'request_Data' . wp_rand( 1, 1000 ), $data );

		$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/payments';

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

		$order->update_meta_data( 'woosquare_request_results_gpay_' . wp_rand( 1, 1000 ), $result );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( __( 'Error: Unable to complete your transaction with square due to some issue. For now you can try some other payment method or try again later.', 'wpexpert-square' ), 'error' );

			throw new Exception( esc_html( $result->get_error_message() ) );
		}

		if ( ! empty( $result->errors ) ) {
			if ( 'INVALID_REQUEST_ERROR' === $result->errors[0]->category
			) {
				wc_add_notice( __( 'Error: Unable to complete your transaction with square due to some issue. For now you can try some other payment method or try again later.', 'wpexpert-square' ), 'error' );
			}

			if ( 'PAYMENT_METHOD_ERROR' === $result->errors[0]->category || 'VALIDATION_ERROR' === $result->errors[0]->category
			) {
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

			if ( get_transient( 'square_fulfillments' ) ) {
				do_action( 'cancelled_orphened_order', get_transient( 'square_fulfillments' ) );
			}

			throw new Exception( esc_html( $errors ) );
		}

		if ( empty( $result ) ) {
			wc_add_notice( __( 'Error: Unable to complete your transaction with square due to some issue. For now you can try some other payment method or try again later.', 'wpexpert-square' ), 'error' );

			throw new Exception( 'Unknown Error' );
		}

		if ( isset( $result->payment->id ) && 'CAPTURED' === $result->payment->card_details->status ) {

			// Store captured value.
			$order->update_meta_data( '_square_charge_captured', 'yes' );

			// Payment complete.
			$order->payment_complete( $result->payment->id );
			$order->add_meta_data( 'woosquare_transaction_id', $result->payment->id, true );

			// translators: %1$s is the message, %2$s is the payment id.
			$complete_message = sprintf( __( 'Square charge complete %1$s (Charge ID: %2$s)', 'wpexpert-square' ), $msg, $result->payment->id );
			$order->add_order_note( $complete_message );
			$this->log( "Success: $complete_message" );

		} elseif ( isset( $result->payment->id ) && 'AUTHORIZED' === $result->payment->card_details->status ) {

			// Store captured value.
			$order->update_meta_data( '_square_charge_captured', 'no' );

			$order->add_meta_data( 'woosquare_transaction_id', $result->payment->id, true );

			// Mark as on-hold.
			// translators: %1$s is the message, %2$s is the payment id.
			$authorized_message = sprintf( __( 'Square charge authorized %1$s (Authorized ID: %2$s). Process order to take payment, or cancel to remove the pre-authorization.', 'wpexpert-square' ), $msg, $result->payment->id );

			$order->update_status( 'on-hold', $authorized_message );
			$this->log( "Success: $authorized_message" );

			// Reduce stock levels.
			version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->reduce_order_stock() : wc_reduce_stock_levels( $order->id );
		} else {
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

			wc_add_notice( $message, 'error' );
			// translators: %s is the error message.
			$message = sprintf( __( 'Square Payment Failed  %s .', 'woosquare' ), $message );

			$order->update_status( 'failed', $message );
		}

		// Remove cart.
		WC()->cart->empty_cart();
		$order->save();
		// Return thank you page redirect.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
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
	 * @param  int    $order_id The ID of the order associated with the charge.
	 * @param  float  $amount   The amount to refund. If null, it refunds the full amount.
	 * @param  string $reason   Optional. A reason or note for the refund.
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

				$transaction_status = $this
					->connect
					->get_transaction_status( $trans_id );

				if ( 'CAPTURED' === $transaction_status ) {

					$currency = $order->get_order_currency();
					$fields   = array(
						'amount_money'    => array(
							'amount'   => (int) $this->format_amount( $amount, $currency ),
							'currency' => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_order_currency() : $order->get_currency(),
						),
						'idempotency_key' => uniqid(),
						'payment_id'      => $trans_id,
						'reason'          => $reason,
					);

					$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/refunds';
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
	 * @since   1.0.0
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

