<?php
/**
 * Woosquare_Plus v1.0 by wpexperts.io
 *
 * @package Woosquare_Plus
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WooSquarePOS_Gateway
 *
 * Handles the Square Terminal payment gateway integration for WooCommerce.
 *
 * @package Woosquare
 */
class WooSquarePOS_Gateway extends WC_Payment_Gateway {

	/**
	 * Connection to Square API.
	 *
	 * @var WooSquare_Payments_Connect
	 */
	protected $connect;

	/**
	 * Access token for Square API.
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * Logger instance.
	 *
	 * @var WooSquare_Payment_Logger
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
		$this->id                 = 'square_terminal_pay' . get_transient( 'is_sandbox' );
		$this->method_title       = __( 'Square terminal Pay', 'wpexpert-square' );
		$this->method_description = __( 'Square terminal Pay works by adding payments button in an woocommerce checkout and then sending the details to Square for verification and processing.', 'wpexpert-square' );
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
		$this->logging     = $this->get_option( 'logging' ) === 'yes' ? true : false;
		$this->connect     = new WooSquare_Payments_Connect(); // decouple in future when v2 is ready.
		$this->token       = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );

		$this->connect->set_access_token( $this->token );

		// Hooks.

		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts_terminalpay' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_payment_scripts_terminalpay' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {

		$is_available = true;

		if ( 'yes' === $this->enabled ) {
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
			if ( (
				'US' !== WC()->countries->get_base_country() &&
				'CA' !== WC()->countries->get_base_country() &&
				'GB' !== WC()->countries->get_base_country() &&
				'IE' !== WC()->countries->get_base_country() &&
				'ES' !== WC()->countries->get_base_country() &&
				'PK' !== WC()->countries->get_base_country() &&
				'JP' !== WC()->countries->get_base_country() &&
				'AU' !== WC()->countries->get_base_country() ) || (
				'USD' !== get_woocommerce_currency() &&
				'CAD' !== get_woocommerce_currency() &&
				'JPY' !== get_woocommerce_currency() &&
				'EUR' !== get_woocommerce_currency() &&
				'PKR' !== get_woocommerce_currency() &&
				'AUD' !== get_woocommerce_currency() &&
				'GBP' !== get_woocommerce_currency() )
				) {
				$is_available = false;
			}

			// if enabled and sandbox credentials not setup.
			$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
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

		return apply_filters( 'woocommerce_square_payment_terminalpay_gateway_is_available', $is_available );
	}


	/**
	 * Initialize Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$ordurl            = site_url() . '/wc-api/square_teminal_hit/';
		$this->form_fields = apply_filters(
			'woocommerce_squaregpay_gateway_settings',
			array(
				'enabled'       => array(
					'title'       => __( 'Enable/Disable', 'wpexpert-square' ),
					'label'       => __( 'Enable Square terminal Pay', 'wpexpert-square' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'         => array(
					'title'       => __( 'Title', 'wpexpert-square' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'wpexpert-square' ),
					'default'     => __( 'terminal Pay (Square)', 'wpexpert-square' ),
				),
				'description'   => array(
					'title'       => __( 'Description', 'wpexpert-square' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'wpexpert-square' ),
					'default'     => __( 'Pay with your credit card via Square.', 'wpexpert-square' ),
				),
				'device_code'   => array(
					'title'       => __( 'Device Code', 'woosquare' ),
					'type'        => 'text',
					'description' => __( 'Get generated device code for your terminal to signin' ),
					'default'     => '',
				),
				'device_id'     => array(
					'title'       => __( 'Device ID', 'woosquare' ),
					'type'        => 'text',
					'description' => __( 'Get generated device ID for your terminal' ),
					'default'     => '',
				),
				'generate_code' => array(
					'title'       => 'Create device code',
					'type'        => 'button',
					'description' => 'Create device code',
					'desc_tip'    => true,
					'default'     => __( 'Pay with your credit card via Square.', 'wpexpert-square' ),
					'value'       => 'sadasdasd',
				),

			)
		);
	}


	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		$payment = 'Click place order';
		if ( $this->description ) {
			$allowed = array(
				'a'      => array(
					'href'  => array(),
					'title' => array(),
				),
				'br'     => array(),
				'em'     => array(),
				'strong' => array(),
			);
			$payment = apply_filters( 'woocommerce_square_description', wpautop( wp_kses( $this->description, $allowed ) ) );
		}
		?>
		<div id="payment-form">
			<div>
			<button id="terminal-pay-button">
			<img class="terminal-pay-button-img" src="<?php echo esc_url( WOOSQUARE_PLUGIN_URL_PAYMENT . '/img/square.png' ); ?>" />
			<?php echo esc_html( $payment ); ?>
			</button>
			<div style="display:none" id="terminal-pay-button-loader">
			<input type="hidden" id="square_pay_nonce" name="square_pay_nonce" value="<?php echo esc_attr( wp_create_nonce( 'square-pay-nonce' ) ); ?>">

			</div>
			
		</div>
		</div>
		<div id="payment-status-container"></div>
		<?php
	}

	/**
	 * Get country codes.
	 *
	 * Returns the country code corresponding to the given currency code.
	 *
	 * @param string $currency_code The currency code for which to get the country code.
	 * @return string The country code corresponding to the currency code.
	 */
	public function get_country_codes( $currency_code ) {

			$currency_symbol = '';

		switch ( $currency_code ) {
			case 'USD':
				$currency_symbol = 'US';
				break;
			case 'EUR':
				$currency_symbol = 'IE';
				break;
			case 'CAD':
				$currency_symbol = 'CA';
				break;
			case 'GBP':
				$currency_symbol = 'GB';
				break;
		}

			return $currency_symbol;
	}


	/**
	 * Enqueues and localizes scripts for the terminal payment method on the checkout page.
	 *
	 * This function checks if the current page is the checkout page, retrieves various settings
	 * and options, determines the environment (sandbox or production), and then enqueues the
	 * necessary scripts and localizes script data for use in the Square terminal payment process.
	 *
	 * @return bool True if scripts are enqueued, false otherwise.
	 */
	public function payment_scripts_terminalpay() {

		if ( ! is_checkout() ) {
			return;
		}
		$location                         = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
		global $woocommerce;
		$woocommerce_square_settings = get_option( 'woocommerce_square_settings' );
		$currency_cod                = get_option( 'woocommerce_currency' );
		$country_code                = $this->get_country_codes( $currency_cod );
		$access_token                = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$location_id                 = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
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

		wp_register_script( 'woosquare-terminal-paycheckout', WOOSQUARE_PLUGIN_URL_PAYMENT . '/js/SquarePaymentsPOSPay.js', array( 'jquery', 'squareSDK' ), WOOSQUARE_VERSION, true );
		wp_localize_script(
			'woosquare-terminal-paycheckout',
			'squaretpay_params',
			array(
				'application_id'   => WOOSQU_PLUS_APPID,
				'lid'              => $location,
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'merchant_name'    => 'Square terminal Pay',
				'order_total'      => $woocommerce->cart->total,
				'environment'      => $environment,
				'currency_code'    => $currency_cod,
				'country_code'     => $country_code,
				'nonce'            => wp_create_nonce( 'squaretpay_params' ),
				'access_token'     => $access_token,
				'location_id'      => $location_id,
				'sandbox'          => get_transient( 'is_sandbox' ),
				'square_pay_nonce' => wp_create_nonce( 'square-pay-nonce' ),
			)
		);
		wp_enqueue_script( 'woosquare-terminal-paycheckout' );

		return true;
	}
	/**
	 * Enqueues and localizes scripts for the terminal payment method in the admin area.
	 *
	 * This function retrieves various settings and options, then enqueues the
	 * necessary scripts and localizes script data for use in the Square terminal
	 * payment process within the admin area.
	 *
	 * @return bool True if scripts are enqueued, false otherwise.
	 */
	public function admin_payment_scripts_terminalpay() {
		global $woocommerce;
		$woocommerce_square_settings = get_option( 'woocommerce_square_settings' );
		$currency_cod                = get_option( 'woocommerce_currency' );
		$country_code                = $this->get_country_codes( $currency_cod );
		$access_token                = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$location_id                 = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		// need to add condition square payment enable so disable below script.

		wp_register_script( 'woosquare-terminal-pay', WOOSQUARE_PLUGIN_URL_PAYMENT . '/js/SquarePaymentsPOSPay.js', array( 'jquery' ), WOOSQUARE_VERSION, true );
		wp_localize_script(
			'woosquare-terminal-pay',
			'POSTerminal',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'POSTerminal' ),
				'access_token'  => $access_token,
				'currency_code' => $currency_cod,
				'currency_sym'  => get_woocommerce_currency_symbol(),
				'country_code'  => $country_code,
				'location_id'   => $location_id,
				'sandbox'       => get_transient( 'is_sandbox' ),
			)
		);

		wp_enqueue_script( 'woosquare-terminal-pay' );

		return true;
	}

	/**
	 * Processes the payment for an order.
	 *
	 * This function handles the payment processing for an order using the Square terminal.
	 * It verifies the checkout ID, retrieves order details, and attempts to complete the payment
	 * via Square's API. The function updates the order status based on the payment result.
	 *
	 * @param int  $order_id The ID of the order being processed.
	 * @param bool $retry Whether to retry the payment in case of failure. Default is true.
	 * @return array|void An array containing the result and redirect URL if successful, or a failure message.
	 */
	public function process_payment( $order_id, $retry = true ) {
		if ( ! isset( $_POST['square_pay_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['square_pay_nonce'] ) ), 'square-pay-nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		if ( isset( $_POST['term_checkout_id'] ) && ! empty( $_POST['term_checkout_id'] ) ) {
			$order           = wc_get_order( $order_id );
			$access_token    = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
			$location_id     = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
			$idempotency_key = uniqid();
			$currency        = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_order_currency() : $order->get_currency();
			$this->log( "Info: Begin processing payment for order {$order_id} for the amount of {$order->get_total()}" );
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

			try {
				// Mark as processing.
				if ( true ) { // phpcs:ignore
					$checkout_id = sanitize_text_field( wp_unslash( $_POST['term_checkout_id'] ) );

					if ( function_exists( 'square_order_sync_add_on' ) ) {
						if ( isset( $_POST ['term_customer_id'] ) ) {
							$forder_id = square_order_sync_add_on( $order, $location_id, $currency, $idempotency_key, $access_token, 'squareup' . get_transient( 'is_sandbox' ), sanitize_text_field( wp_unslash( $_POST['term_customer_id'] ) ) );
						}
					}

					$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/terminals/checkouts/' . $checkout_id;
					$headers = array(
						'Accept'        => 'application/json',
						'Authorization' => 'Bearer ' . $this->token,
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

					$transaction_id = $result->checkout->payment_ids;
					$device_id      = $result->checkout->device_options->device_id;
					$amount         = $result->checkout->amount_money->amount;
					$currency       = $result->checkout->amount_money->currency;
					$paid_amount    = wc_price( $amount / 100 ) . ' ' . $currency;

					if ( empty( $transaction_id ) ) {
						$transaction_id = 'cnon:customer-card-id-ok';
					}

					$order->add_meta_data( 'woosquare_transaction_id', $transaction_id, true );
					$order->add_meta_data( 'transaction_device_id', $device_id, true );
					// Translators: %1$s is the amount paid, %2$s is the transaction ID.
					$message = sprintf( __( 'Customer card successfully paid %1$s (Transaction ID: %2$s).', 'woosquare' ), $paid_amount, $transaction_id );
					$order->update_status( apply_filters( 'square_order_status_woo_to_square', 'processing' ), $message );
					WC()->cart->empty_cart();
						// Return thank you page redirect.
					$order->save();

					return array(
						'result'   => 'success',
						'redirect' => $this->get_return_url( $order ),
					);
				}
				// payment_ids dhoondho.
				return array(
					'result' => 'Failed',
				);

			} catch ( Exception $e ) {
				// Translators: %s is the error message.
				$this->log( sprintf( __( 'Error: %s', 'wpexpert-square' ), $e->getMessage() ) );

				$order->update_status( 'failed', $e->getMessage() );

				return;
			}
		} else {
			return array(
				'message' => 'Invalid Checkout ID',
			);
		}
	}

	/**
	 * Process amount to be passed to Square.
	 *
	 * @param float  $total    The total amount to be formatted.
	 * @param string $currency The currency code (optional).
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
	 * @param int    $order_id The ID of the order to be refunded.
	 * @param float  $amount   The amount to be refunded.
	 * @param string $reason   The reason for the refund.
	 *
	 * @return bool True if the refund was successful, false otherwise.
	 *
	 * @throws Exception If an error occurs during the refund process.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order     = wc_get_order( $order_id );
		$trans_id  = $order->get_meta( 'woosquare_transaction_id', true );
		$device_id = $order->get_meta( 'transaction_device_id', true );
		if ( ! $order || ! $trans_id ) {
			return false;
		}

		if ( 'square_terminal_pay' === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->payment_method : $order->get_payment_method() ) ) {
			try {
				$this->log( "Info: Begin refund for order {$order_id} for the amount of {$amount}" );

				$transaction_status = $this->connect->get_transaction_status( $trans_id );

				if ( 'CAPTURED' === $transaction_status ) {
					if ( $reason ) {
						$reason = $reason;
					} else {
						$reason = 'Returning items';
					}
					$currency = $order->get_order_currency();
					$fields   = array(
						'idempotency_key' => uniqid(),
						'refund'          => array(
							'amount_money' => array(
								'amount'   => (int) $this->format_amount( $amount, $currency ),
								'currency' => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_order_currency() : $order->get_currency(),
							),
							'device_id'    => $device_id,
							'payment_id'   => $trans_id,
							'reason'       => $reason,
						),
					);

					$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/terminals/refunds';

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
						throw new Exception( 'Error: ' . wp_json_encode( $result->errors ) );

					} elseif ( 'APPROVED' === $result->refund->status || 'PENDING' === $result->refund->status ) {
							// translators: %1$s is the refunded amount, %2$s is the refund ID, %3$s is the reason for the refund.
							$refund_message = sprintf( __( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'wpexpert-square' ), wc_price( $result->refund->amount_money->amount / 100 ), $result->refund->id, $reason );

							$order->add_order_note( $refund_message );

							$this->log( 'Success: ' . html_entity_decode( wp_strip_all_tags( $refund_message ) ) );

							return true;
					}
				}
			} catch ( Exception $e ) {
				// Translators: %s is the error message.
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
	 * @param string $message The message to be logged.
	 */
	public function log( $message ) {
		if ( $this->logging ) {
			WooSquare_Payment_Logger::log( $message );
		}
	}
}

