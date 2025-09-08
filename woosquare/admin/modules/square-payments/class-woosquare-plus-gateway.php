<?php
/**
 * Woosquare_Plus v1.0 by wpexperts.io
 *
 * @package Woosquare_Plus
 */

// don't load directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

#[AllowDynamicProperties]

/**
 * Class WooSquare_Plus_Gateway
 *
 * This class defines the WooCommerce payment gateway for Square.
 * It allows customers to make payments via the Square platform.
 *
 * @package  Woosquare_Plus
 */
class WooSquare_Plus_Gateway extends WC_Payment_Gateway {

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
		$this->id                 = 'square_plus' . get_transient( 'is_sandbox' );
		$this->method_title       = __( 'Square', 'woosquare' );
		$this->method_description = __( 'Square works by adding payments fields in an iframe and then sending the details to Square for verification and processing.', 'woosquare' );
		$this->has_fields         = true;
		$this->supports           = array(
			'subscriptions',
			'products',
			'subscription_cancellation',
			'tokenization',
			'subscription_suspension',
			'add_payment_method',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'pre-orders',
			// 'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			// 'subscription_payment_method_change_admin',
			'multiple_subscriptions',
			'refunds',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title           = $this->get_option( 'title' );
		$this->description     = $this->get_option( 'description' );
		$this->enabled         = $this->get_option( 'enabled' ) === 'yes' ? 'yes' : 'no';
		$this->capture         = $this->get_option( 'capture' ) === 'yes' ? true : false;
		$this->create_customer = $this->get_option( 'create_customer' ) === 'yes' ? true : false;
		$this->logging         = $this->get_option( 'logging' ) === 'yes' ? true : false;

		include_once __DIR__ . '/class-woosquare-payments-connect.php';
		$this->connect = new WooSquare_Payments_Connect(); // decouple in future when v2 is ready.
		$this->token   = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );

		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( get_transient( 'is_sandbox' ) ) {
			$this->description         .= ' ' . __( 'STAGING MODE IS ENABLED". For testing purpose use card number 4111111111111111 with any CVC and valid expiration date.', 'woosquare' );
			$this->view_transaction_url = 'https://squareupsandbox.com/dashboard/sales/transactions/%s';
		} else {
			$this->view_transaction_url = 'https://squareup.com/dashboard/sales/transactions/%s';
		}

		$this->description = trim( $this->description );
		$this->connect->set_access_token( $this->token );
		$sub = '';
		// Hooks
		// if cart having subscription type product disabled below script else work..
		if ( in_array( 'wc-square-recurring-premium/wc-square-recuring.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) && is_checkout() ) {
			$sub = false;
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$_product = wc_get_product( $cart_item['product_id'] );
				if ( $_product->is_type( 'subscription' ) || $_product->is_type( 'variable-subscription' ) ) {
					$sub = true;
				}
			}
		}
		if ( ! isset( $sub ) || ! $sub ) {
			$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
			if ( isset( $woocommerce_square_plus_settings['enabled'] ) && 'yes' === $woocommerce_square_plus_settings['enabled'] ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
			}
		}
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {

			add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_pre_order_release_payment_woosquare' ) );
		}
	}

	/**
	 * Get icons function.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon = '<img src="' . WOOSQUARE_PLUGIN_URL_PAYMENT . '/img/cc-icon/visa.svg" alt="Visa" width="32" style="margin-left: 0.3em" />' .
		'<img src="' . WOOSQUARE_PLUGIN_URL_PAYMENT . '/img/cc-icon/mastercard.svg" alt="Mastercard" width="32" style="margin-left: 0.3em" />' .
		'<img src="' . WOOSQUARE_PLUGIN_URL_PAYMENT . '/img/cc-icon/amex.svg" alt="Amex" width="32" style="margin-left: 0.3em" />' .
		'<img src="' . WOOSQUARE_PLUGIN_URL_PAYMENT . '/img/cc-icon/discover.svg" alt="Discover" width="32" style="margin-left: 0.3em" />';

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
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

			if ( ! get_option( 'woo_square_access_token_cauth' . get_transient( 'is_sandbox' ) ) ) {
				$is_available = false;
			}

			// Square only supports US, Canada and Australia for now.
			if ( ( 'US' !== WC()->countries->get_base_country()
				&& 'CA' !== WC()->countries->get_base_country()
				&& 'GB' !== WC()->countries->get_base_country()
				&& 'IE' !== WC()->countries->get_base_country()
				&& 'ES' !== WC()->countries->get_base_country()
				&& 'JP' !== WC()->countries->get_base_country()
				&& 'FR' !== WC()->countries->get_base_country()
				&& 'AU' !== WC()->countries->get_base_country() ) || ( 'USD' !== get_woocommerce_currency()
				&& 'CAD' !== get_woocommerce_currency()
				&& 'JPY' !== get_woocommerce_currency()
				&& 'EUR' !== get_woocommerce_currency()
				&& 'AUD' !== get_woocommerce_currency()
				&& 'GBP' !== get_woocommerce_currency() )
			) {
				$is_available = false;
			}

			// if enabled and sandbox credentials not setup.
			$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
			if ( get_transient( 'is_sandbox' ) ) {
				if ( empty( get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ) )
					|| empty( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) )
					|| empty( WOOSQU_PLUS_APPID )
				) {
					$is_available = false;
				}
			}
		} else {
			$is_available = false;
		}

		return apply_filters( 'woocommerce_square_payment_gateway_is_available', $is_available );
	}

	/**
	 * Initialize Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = apply_filters(
			'woocommerce_square_gateway_settings',
			array(
				'enabled'            => array(
					'title'       => __( 'Enable/Disable', 'woosquare' ),
					'label'       => __( 'Enable Square', 'woosquare' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'              => array(
					'title'       => __( 'Title', 'woosquare' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woosquare' ),
					'default'     => __( 'Credit card (Square)', 'woosquare' ),
				),
				'description'        => array(
					'title'       => __( 'Description', 'woosquare' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'woosquare' ),
					'default'     => __( 'Pay with your credit card via Square.', 'woosquare' ),
				),
				'capture'            => array(
					'title'       => __( 'Delay Capture', 'woosquare' ),
					'label'       => __( 'Enable Delay Capture', 'woosquare' ),
					'type'        => 'checkbox',
					'description' => __( 'When enabled, the request will only perform an Auth on the provided card. You can then later perform either a Capture or Void.', 'woosquare' ),
					'default'     => 'no',
				),
				'create_customer'    => array(
					'title'       => __( 'Create Customer', 'woosquare' ),
					'label'       => __( 'Enable Create Customer', 'woosquare' ),
					'type'        => 'checkbox',
					'description' => __( 'When enabled, processing a payment will create a customer profile on Square.', 'woosquare' ),
					'default'     => 'no',
				),
				'logging'            => array(
					'title'       => __( 'Logging', 'woosquare' ),
					'label'       => __( 'Log debug messages', 'woosquare' ),
					'type'        => 'checkbox',
					'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woosquare' ),
					'default'     => 'no',
				),
				'Send_customer_info' => array(
					'title'       => __( 'Send Customer Info', 'wpexpert-square' ),
					'label'       => __( 'Send First Name Last Name', 'wpexpert-square' ),
					'type'        => 'checkbox',
					'description' => __( 'Send First Name Last Name with order to square.', 'wpexpert-square' ),
					'default'     => 'no',
				),
				'enable_sandbox'     => array(
					'title'       => __( 'Enable/Disable', 'wpexpert-square' ),
					'label'       => __( 'Enable Sandbox', 'wpexpert-square' ),
					'type'        => 'checkbox',
					'description' => __( 'Test your transaction through sandbox mode.', 'wpexpert-square' ),
					'default'     => 'no',
				),
			)
		);
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );

		if ( $activate_modules_woosquare_plus['woosquare_card_on_file']['module_activate'] ) {
			$user = wp_get_current_user();

			$square_id = get_user_meta( $user->ID, '_square_customer_id', true );

			if ( ! empty( $square_id ) ) {
				$customers = $this->get_cus( $square_id );
			}
			if ( ! empty( $customers->customer->cards )
				&& is_array( $customers->customer->cards )
				&& ! is_add_payment_method_page()
			) {

				?>


			<div class="table-responsive cardWrap">

			<table id="square-credit-cards" class="cardWrapTable">
			<!-- This is for adding credit card... -->
			<strong>Square saved credit cards.</strong>
			<thead>
				<tr>
					<th>Brand</th>
					<th>Last four</th>
					<th>Exp. date</th>

				</tr>
				</thead>
				<tbody>
				<?php
				foreach ( $customers->customer->cards as $cards ) {
					?>
					<tr>
						<td>
							<label for="saved_cards" class="savecardlink">
							<input type='radio' value="<?php echo esc_attr( $cards->id ); ?>" id="saved_cards" class='saved_cards_squ' name="saved_cards" />
							<input type='hidden' value="<?php echo esc_attr( $customers->customer->id ); ?>" id="saved_cards_customer_id" class='saved_cards_squ_customer_id' name="saved_cards_customer_id" />
						<?php

						switch ( $cards->card_brand ) {
							case 'VISA':
								echo '<img src="' . esc_url( WOOSQUARE_PLUGIN_URL_PAYMENT . '/img/cc-icon/visa.svg' ) . '" alt="' . esc_attr( 'Visa' ) . '" width="32" style="margin-left: 0.3em" />';
								break;
							case 'MASTERCARD':
								echo '<img src="' . esc_url( WOOSQUARE_PLUGIN_URL_PAYMENT . '/img/cc-icon/mastercard.svg' ) . '" alt="' . esc_attr( 'MasterCard' ) . '" width="32" style="margin-left: 0.3em" />';
								break;
							case 'DISCOVER':
								echo '<img src="' . esc_url( WOOSQUARE_PLUGIN_URL_PAYMENT . '/img/cc-icon/discover.svg' ) . '" alt="' . esc_attr( 'Discover' ) . '" width="32" style="margin-left: 0.3em" />';
								break;
							case 'DISCOVER_DINERS':
								echo '<img src="' . esc_url( WOOSQUARE_PLUGIN_URL_PAYMENT . '/img/cc-icon/discover.svg' ) . '" alt="' . esc_attr( 'Discover Diners' ) . '" width="32" style="margin-left: 0.3em" />';
								break;
							case 'JCB':
								echo '<img src="' . esc_url( WOOSQUARE_PLUGIN_URL_PAYMENT . '/img/cc-icon/jcb.svg' ) . '" alt="' . esc_attr( 'JCB' ) . '" width="32" style="margin-left: 0.3em" />';
								break;
							case 'AMERICAN_EXPRESS':
								echo '<img src="' . esc_url( WOOSQUARE_PLUGIN_URL_PAYMENT . '/img/cc-icon/amex.svg' ) . '" alt="' . esc_attr( 'American Express' ) . '" width="32" style="margin-left: 0.3em" />';
								break;
							case 'CHINA_UNIONPAY':
								echo '<img src="' . esc_url( WOOSQUARE_PLUGIN_URL_PAYMENT . '/img/cc-icon/china_unionpay.png' ) . '" alt="' . esc_attr( 'China UnionPay' ) . '" width="32" style="margin-left: 0.3em" />';
								break;
							case 'OTHER_BRAND':
								echo '<img src="' . esc_url( WOOSQUARE_PLUGIN_URL_PAYMENT . '/img/cc-icon/china_unionpay.png' ) . '" alt="' . esc_attr( 'Other Brand' ) . '" width="32" style="margin-left: 0.3em" />';
								break;
							default:
								echo '';
						}

						?>
							</label>

						</td>
						<td><?php echo esc_html( $cards->last_4 ); ?></td>
						<td><?php echo esc_html( $cards->exp_month ) . '/' . esc_html( $cards->exp_year ); ?></td>
						<td></td>
					</tr>
					<?php } ?>
					<tr>
						<td colspan="5"><label class="savecardlink"><input type='radio' value="" id="saved_cards" class='new_cards_squ' name="saved_cards" /> Process with new Credit Card.</label></td>
					</tr>
				</tbody>
			</table>

			</div>

					<?php
			}
		}
		?>

			<fieldset class="wooSquare-checkout">
		<?php
		$allowed = array(
			'a'      => array(
				'href'  => array(),
				'title' => array(),
			),
			'br'     => array(),
			'em'     => array(),
			'strong' => array(),
			'span'   => array(
				'class' => array(),
			),
		);
		if ( $this->description ) {
			echo wp_kses_post( apply_filters( 'woocommerce_square_description', wpautop( wp_kses( $this->description, $allowed ) ) ) );
		}
		$checkpaymentform = false;
		$value            = '';
		$boolean          = '';
		$checkpaymentform = apply_filters( 'checkpaymentform', $value, $boolean );

		?>

					<div  id="payment-form">
						<div id="card-container_payment"></div>
					</div>
					<input type="hidden" id="square_pay_nonce" name="square_pay_nonce" value="<?php echo esc_attr( wp_create_nonce( 'square-pay-nonce' ) ); ?>">
		<?php

		if ( ! empty( $customers->customer ) ) {
			?>
						<input type='hidden' value="<?php echo esc_attr( $customers->customer->id ); ?>" id="saved_cards_customer_id" class='saved_cards_squ_customer_id' name="saved_cards_customer_id" />
				<?php
		}
		?>
					<div id="payment-status-container"></div>

		<?php

		$subs = false;
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			if ( WC_Subscriptions_Cart::cart_contains_subscription() ) {
				$subs = true;
			}
		}

		// checking is that order have pre order items.
		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			$cart_data                     = WC()->session->get( 'cart' );
			$_wc_pre_orders_enabled        = get_post_meta( $cart_data[ array_keys( $cart_data )[0] ]['product_id'], '_wc_pre_orders_enabled', true );
			$_wc_pre_orders_when_to_charge = get_post_meta( $cart_data[ array_keys( $cart_data )[0] ]['product_id'], '_wc_pre_orders_when_to_charge', true );
			?>
					<input type='hidden' name='is_preorder' class='is_preorder' value='1' /> 
			<?php
		}
		if ( $activate_modules_woosquare_plus['woosquare_card_on_file']['module_activate']
			&& is_user_logged_in()
			&& ! $subs
			&& ! isset( $_GET['wfacp_id'] ) // phpcs:ignore
			&& ! is_add_payment_method_page()
		) {
			?>
					<p class="form-row form-row-wide">
						<label for="sq-card-saved"><span class="required"></span></label>
						<label>&nbsp;&nbsp;<input id="sq-card-saved" type="checkbox" autocomplete="off" placeholder="<?php esc_attr_e( 'Card Postal Code', 'woosquare' ); ?>" name="<?php echo esc_attr( $this->id ); ?>sq-card-saved" /><?php esc_attr_e( 'Save this card for future use.', 'woosquare' ); ?></label>
					</p>
			<?php	} ?>
			</fieldset>

			<?php
	}

	/**
	 * Get customer information from Square.
	 *
	 * @param string $customer_id The Square customer ID.
	 *
	 * @return mixed|null Returns the customer data if successful, or null on failure.
	 */
	public function get_cus( $customer_id ) {

		if ( ! empty( $customer_id ) && ! empty( $this->token ) ) {
			$square     = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
			$url        = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers/' . $customer_id;
			$method     = 'GET';
			$headers    = array(
				'authorization' => 'Bearer ' . $this->token,
				'cache-control' => 'no-cache',
				'postman-token' => '51e3dc9d-a036-b635-9d1a-92fa490f2514',
			);
			$response   = array();
			$args       = array( '' );
			$response   = $square->wp_remote_woosquare( $url, $args, $method, $headers, $response );
			$object_cus = json_decode( $response['body'], false );
			if ( 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {
				return $object_cus;
			} else {
				return false;
			}
		}
	}

	/**
	 * Creates a new card for a Square customer.
	 *
	 * @param string $_square_customer_id The Square customer ID.
	 * @param array  $card_details        An array containing card details.
	 * @param int    $user_id             The user ID.
	 * @param string $token               The access token.
	 *
	 * @return mixed Returns the response data if successful, or null on failure.
	 */
	public function create_cus_card( $_square_customer_id, $card_details, $user_id, $token ) {

		$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );

		$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers/' . $_square_customer_id . '/cards';

		$method = 'POST';

		$headers = array(
			'Authorization' => 'Bearer ' . $token, // Use verbose mode in cURL to determine the format you want for this header.
			'cache-control' => 'no-cache',
			'Content-Type'  => 'application/json',
		);

		$response = array();
		$response = $square->wp_remote_woosquare( $url, $card_details, $method, $headers, $response );

		$object_response = json_decode( $response['body'], true );
		if ( 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {
			update_user_meta( $user_id, 'customers_card_create_response', $response );
			return $response;

		} else {
			update_user_meta( $user_id, 'customers_card_create_err', $response );
			return null;
		}
	}

	/**
	 * Get payment form input styles.
	 * This function is pass to the JS script in order to style the
	 * input fields within the iFrame.
	 *
	 * Possible styles are: mediaMinWidth, mediaMaxWidth, backgroundColor, boxShadow,
	 * color, fontFamily, fontSize, fontWeight, lineHeight and padding.
	 *
	 * @since   1.0.4
	 * @version 1.0.4
	 * @return  json $styles
	 */
	public function get_input_styles() {
		$styles = array(
			array(
				'fontSize'        => '1.2em',
				'padding'         => '.618em',
				'fontWeight'      => 400,
				'backgroundColor' => 'transparent',
				'lineHeight'      => 1.7,
			),
			array(
				'mediaMaxWidth' => '1200px',
				'fontSize'      => '1em',
			),
		);

		return apply_filters( 'woocommerce_square_payment_input_styles', wp_json_encode( $styles ) );
	}

	/**
	 * Payment_scripts function.
	 */
	public function payment_scripts() {
		if ( ! is_checkout() && ! is_add_payment_method_page() ) {
			return;
		}

		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
		$location                         = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		global $woocommerce;
		// Will get you cart object.
		$cart_total = $woocommerce->cart->get_totals();

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

		wp_register_script( 'square', '', '', '0.0.2', true );
		wp_register_script( 'woocommerce-square', WOOSQUARE_PLUGIN_URL_PAYMENT . '/js/SquarePayments.js', array( 'jquery', 'square' ), WOOSQUARE_VERSION, true );
		if ( get_transient( 'is_sandbox' ) ) {
			$env = 'development';
		} else {
			$env = 'production';
		}
		wp_localize_script(
			'woocommerce-square',
			'square_params',
			array(
				'application_id'               => WOOSQU_PLUS_APPID,
				'ajax_url'                     => admin_url( 'admin-ajax.php' ),
				'environment'                  => $env,
				'location_id'                  => apply_filters( 'modify_square_location_id', $location ),
				'cart_total'                   => $cart_total['total'],
				'get_woocommerce_currency'     => get_woocommerce_currency(),
				'placeholder_card_number'      => __( '•••• •••• •••• ••••', 'woosquare' ),
				'placeholder_card_expiration'  => __( 'MM / YY', 'woosquare' ),
				'placeholder_card_cvv'         => __( 'CVV', 'woosquare' ),
				'placeholder_card_postal_code' => __( 'Card Postal Code', 'woosquare' ),
				'payment_form_input_styles'    => esc_js( $this->get_input_styles() ),
				'custom_form_trigger_element'  => apply_filters( 'woocommerce_square_payment_form_trigger_element', esc_js( '' ) ),
				'subscription'                 => ( class_exists( 'WC_Subscriptions_Order' ) ? WC_Subscriptions_Cart::cart_contains_subscription() : false ),
				'sandbox'                      => get_transient( 'is_sandbox' ),
				'square_pay_nonce'             => wp_create_nonce( 'square-pay-nonce' ),
				'timeoutfilter'                => apply_filters( 'cc_field_delay', 10 ),
				'cardcontainer'                => '#card-container_payment',
			)
		);
		wp_enqueue_script( 'woocommerce-square' );

		wp_enqueue_style( 'woocommerce-square-styles', WOOSQUARE_PLUGIN_URL_PAYMENT . '/css/SquareFrontendStyles.css', array(), WOOSQUARE_VERSION );

		return true;
	}

	/**
	 * Process Square customer information for an order.
	 *
	 * This function checks if a Square customer needs to be created or processed for the given order.
	 * If a Square customer ID already exists, it is retrieved. If no customer exists, a search is
	 * performed using the customer's billing email. If still no customer is found, a new Square
	 * customer is created.
	 *
	 * The Square customer ID is then updated on the order.
	 *
	 * @param WC_Order $order The WooCommerce order object for the current order.
	 * @param int|null $parent_order_id The ID of the parent order if applicable, or null if not.
	 * @param int      $order_id The ID of the current order being processed.
	 *
	 * @return void
	 */
	public function process_square_customer( $order, $parent_order_id, $order_id ) {
		if ( $this->should_create_or_process_customer( $order_id ) ) {
			$customer_id        = $this->get_customer_id( $order );
			$square_customer_id = $this->get_square_customer_id( $customer_id, $parent_order_id, $order );

			// If no Square customer exists, search for one using the billing email.
			$square_customer_id = $this->search_square_customer_by_email( $order );

			// If still no Square customer, create a new one.
			if ( ! $square_customer_id ) {
				$square_customer_id = $this->create_square_customer( $order, $parent_order_id );

			}

			// Update order with the Square customer ID.
			if ( $square_customer_id ) {
				$this->update_order_with_square_customer_id( $order, $parent_order_id, $square_customer_id );
			}
		}
	}

	/**
	 * Check if a WooCommerce order contains a subscription.
	 *
	 * This function checks if the given order ID contains a subscription
	 * (either a parent or renewal subscription order). If it finds a subscription,
	 * it returns `true` and the parent order ID, if available.
	 *
	 * @param int $order_id The ID of the WooCommerce order to check.
	 *
	 * @return array Returns an array containing:
	 *               - bool $subscription Whether the order contains a subscription (true/false).
	 *               - int|null $parent_order_id The parent order ID, or null if no parent order is found.
	 */
	public function check_subscription( $order_id ) {
		$subscription    = false;
		$parent_order_id = null;

		if ( class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_subscription( $order_id, array( 'parent', 'renewal' ) ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => array( 'parent', 'renewal' ) ) );

			// Get the parent order ID.
			foreach ( $subscriptions as $subscription ) {
				if ( $subscription->get_parent_id() ) {
					$parent_order    = $subscription->get_parent();
					$parent_order_id = $parent_order->get_id();
				}
			}

			$subscription = true;
		}

		return $subscription;
	}

	/**
	 * Check if we should create or process a customer for the Square payment.
	 *
	 * @param int $order_id The ID of the WooCommerce order to check.
	 * @return bool True if we should create or process the customer.
	 */
	public function should_create_or_process_customer( $order_id ) {
		if ( ! isset( $_POST['square_pay_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['square_pay_nonce'] ) ), 'square-pay-nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		$is_subscription    = $this->check_subscription( $order_id ) && empty( $_POST['saved_cards'] );
		$is_card_saved      = isset( $_POST['square_plussq-card-saved'] ) && 'on' === $_POST['square_plussq-card-saved'];
		$is_payment_change  = isset( $_POST['woocommerce_change_payment'] ) && is_numeric( $_POST['woocommerce_change_payment'] );
		$is_wcf_checkout    = isset( $_POST['_wcf_flow_id'] ) && is_numeric( $_POST['_wcf_flow_id'] ) && isset( $_POST['_wcf_checkout_id'] ) && is_numeric( $_POST['_wcf_checkout_id'] ) && empty( $_POST['saved_cards'] );
		$is_pre_order       = $this->maybe_process_pre_orders( $order_id );
		$is_create_customer = $this->create_customer;
		$is_guest_customer  = ! empty( get_option( 'woo_square_create_customer_guest' ) ) && get_option( 'woo_square_create_customer_guest' ) === '1';

		return (
			$is_subscription ||
			$is_card_saved ||
			$is_payment_change ||
			$is_wcf_checkout ||
			$is_pre_order ||
			$is_create_customer ||
			$is_guest_customer
		);
	}

	/**
	 * Retrieve the customer ID from the WooCommerce order.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @return int The customer ID or a random ID if not found.
	 */
	public function get_customer_id( $order ) {
		return $order->get_customer_id() ? $order->get_customer_id() : wp_rand();
	}

	/**
	 * Get the Square customer ID from the meta data.
	 *
	 * @param int      $customer_id     The WooCommerce customer ID.
	 * @param int      $parent_order_id The parent order ID (if applicable).
	 * @param WC_Order $order           The WooCommerce order object.
	 * @return string|null The Square customer ID or null if not found.
	 */
	public function get_square_customer_id( $customer_id, $parent_order_id, $order ) {
		if ( $customer_id ) {
			return get_user_meta( $customer_id, '_square_customer_id', true );
		} elseif ( $parent_order_id ) {
			return $parent_order->get_meta( '_square_customer_id', true );
		} else {
			return $order->get_meta( '_square_customer_id', true );
		}
	}

	/**
	 * Search for a Square customer using the billing email address.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @return string|null The Square customer ID or null if not found.
	 */
	public function search_square_customer_by_email( $order ) {
		$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers/search';
		$headers = $this->get_square_api_headers();

		$customer_data = array(
			'query' => array(
				'filter' => array(
					'email_address' => array(
						'exact' => strtolower( $order->get_billing_email() ),
					),
				),
			),
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

		$search_customer = json_decode( wp_remote_retrieve_body( $response ), true );

		return ! empty( $search_customer['customers'][0]['id'] ) ? $search_customer['customers'][0]['id'] : null;
	}

	/**
	 * Create a new Square customer using the order details.
	 *
	 * @param WC_Order $order           The WooCommerce order object.
	 * @param int      $parent_order_id The parent order ID (if applicable).
	 * @return string|null The created Square customer ID or null if failed.
	 */
	public function create_square_customer( $order, $parent_order_id ) {
		$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers';
		$headers = $this->get_square_api_headers();

		$shipping_address = $this->get_order_billing_shipping_address( $order );

		$customer_data = array(
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
			return $response_body['customer']['id'];
		}
		$print_r = 'print_r';
		$this->log( 'Square API Error: ' . $print_r( $response_body, true ) );
		return null;
	}

	/**
	 * Update the WooCommerce order with the Square customer ID.
	 *
	 * @param WC_Order $order           The WooCommerce order object.
	 * @param int      $parent_order_id The parent order ID (if applicable).
	 * @param string   $square_customer_id The Square customer ID.
	 */
	public function update_order_with_square_customer_id( $order, $parent_order_id, $square_customer_id ) {
		$order->update_meta_data( '_square_customer_id', $square_customer_id );

		if ( $order->get_customer_id() ) {
			update_user_meta( $order->get_customer_id(), '_square_customer_id', $square_customer_id );
		} elseif ( $parent_order_id ) {
			$order->update_meta_data( '_square_customer_id', $square_customer_id );
		}
		// translators: %s is the customer id.
		$order->add_order_note( sprintf( __( 'Customer created or updated on Square: %s', 'woosquare' ), $square_customer_id ) );
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
	 * Retrieve Square API headers for making requests.
	 *
	 * @return array The headers for Square API requests.
	 */
	public function get_square_api_headers() {
		return array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $this->token,
			'Content-Type'  => 'application/json',
			'Cache-Control' => 'no-cache',
		);
	}


	/**
	 * Process the payment
	 *
	 * @param int $order_id The order ID.
	 *
	 * @throws Exception If the payment processing fails.
	 */
	public function process_payment( $order_id ) {

		if ( ! isset( $_POST['square_pay_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['square_pay_nonce'] ) ), 'square-pay-nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
		$location_id                      = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );

		$card_nonce               = isset( $_POST['square_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['square_nonce'] ) ) : '';
		$buyer_verification_token = isset( $_POST['buyerVerification_token'] ) ? sanitize_text_field( wp_unslash( $_POST['buyerVerification_token'] ) ) : '';

		if ( isset( $_POST['woocommerce_change_payment'] ) && is_numeric( $_POST['woocommerce_change_payment'] ) ) {
			$get_post = get_post( sanitize_text_field( wp_unslash( $_POST['woocommerce_change_payment'] ) ) );
			$order    = wc_get_order( $get_post->post_parent );
			$order_id = $get_post->post_parent;
		} else {
			$order = wc_get_order( $order_id );
		}

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

		$currency = $order->get_currency();

		// check if falid order manual pay.
		$parent_order_id = null;
		$subscription    = false;
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			if ( wcs_order_contains_subscription( $order_id, array( 'parent', 'renewal' ) ) ) {
				$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => array( 'parent', 'renewal' ) ) );
				// get parent order.
				foreach ( $subscriptions as $subscription ) {
					if ( $subscription->get_parent_id() ) {
						$parent_order    = $subscription->get_parent();
						$parent_order_id = $parent_order->get_id();
					}
				}
				$subscription = true;
			}
		}

		try {
			// shipping address.
			$shipping_address = $this->get_order_billing_shipping_address( $order );
			// billing address.
			$billing_address = $this->get_order_billing_shipping_address( $order );

			$this->process_square_customer( $order, $parent_order_id, $order_id );

			if ( isset( $_POST['wc-square-recurring-payment-token'] )
				&& is_numeric( $_POST['wc-square-recurring-payment-token'] )
			) {
				$token_id          = wc_clean( sanitize_text_field( wp_unslash( $_POST['wc-square-recurring-payment-token'] ) ) );
				$wc_payment_tokens = WC_Payment_Tokens::get( $token_id );
				$customer_card_id  = $wc_payment_tokens->get_token();

				if ( $parent_order_id ) {
					$parent_order->update_meta_data( '_woos_plus_customer_card_id', $customer_card_id );
				} else {
					$order->add_meta_data( '_woos_plus_customer_card_id', $customer_card_id );
				}
			} elseif ( ( empty( $_POST['saved_cards'] ) && ( isset( $_POST['square_plussq-card-saved'] ) && 'on' === $_POST['square_plussq-card-saved'] ) )
				|| $subscription
			) {

				$customer_card_id = null;
				if ( isset( $_POST['square_nonce'] ) ) {

					$customer_card_id = sanitize_text_field( wp_unslash( $_POST['square_nonce'] ) );

					if ( isset( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) && 'new' === $_POST[ 'wc-' . $this->id . '-payment-token' ] ) {
						$wc_payment_token = new WC_Payment_Token_CC();
						$wc_payment_token->set_token( $customer_card_id );
						$wc_payment_token->set_gateway_id( $this->id ); // `$this->id` references the gateway ID set in `__construct`
						$wc_payment_token->set_card_type( strtolower( isset( $_POST['woos_plus_1'] ) ? sanitize_text_field( wp_unslash( $_POST['woos_plus_1'] ) ) : '' ) );
						$wc_payment_token->set_last4( isset( $_POST['woos_plus_2'] ) ? sanitize_text_field( wp_unslash( $_POST['woos_plus_2'] ) ) : '' );
						$wc_payment_token->set_expiry_month( isset( $_POST['woos_plus_3'] ) ? sanitize_text_field( wp_unslash( $_POST['woos_plus_3'] ) ) : '' );
						$wc_payment_token->set_expiry_year( isset( $_POST['woos_plus_4'] ) ? sanitize_text_field( wp_unslash( $_POST['woos_plus_4'] ) ) : '' );
						$wc_payment_token->set_user_id( get_current_user_id() );
						$wc_payment_token->save();
					}

					if ( $parent_order_id ) {
						$parent_order->update_meta_data( '_woos_plus_customer_card_id', $customer_card_id );
					} else {
						$order->add_meta_data( '_woos_plus_customer_card_id', $customer_card_id );
					}
				}
			} elseif ( isset( $_POST['saved_cards'] ) && ! empty( $_POST['saved_cards'] ) ) {
				$saved_cards = ( isset( $_POST['saved_cards'] ) ? sanitize_text_field( wp_unslash( $_POST['saved_cards'] ) ) : '' );
				add_post_meta( $order_id, '_woos_plus_customer_card_id', $saved_cards );
			} elseif ( isset( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) && ! empty( $_POST['issavedtoken'] ) ) {
				$token_id          = wc_clean( sanitize_text_field( wp_unslash( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) ) );
				$wc_payment_tokens = WC_Payment_Tokens::get( $token_id );
				$customer_card_id  = $wc_payment_tokens->get_token();
				if ( $parent_order_id ) {
					$parent_order->update_meta_data( '_woos_plus_customer_card_id', $customer_card_id );
				} else {
					$order->add_meta_data( '_woos_plus_customer_card_id', $customer_card_id );
				}
			}

			// ToDo: `process_pre_order` saves the source to the order for a later payment.
			// This might not work well with PaymentIntents.
			if ( $this->maybe_process_pre_orders( $order_id ) ) {
				$parent_order->save();
				$order->save();
				return $this->process_pre_order( $order_id );
			}

			if ( isset( $_POST['woocommerce_change_payment'] ) && is_numeric( $_POST['woocommerce_change_payment'] ) ) {
				$woocommerce_change_payment = sanitize_text_field( wp_unslash( $_POST['woocommerce_change_payment'] ) );
				$parent_order->save();
				$order->save();
				return array(
					'result'   => 'success',
					'redirect' => site_url() . '/my-account/view-subscription/' . $woocommerce_change_payment . '/',
				);
			}

			if ( $order->get_total() === 0 ) {
				$card_nonce = '';
			}

			// charge customer.
			if ( $order->get_total() > 0
				&& ( isset( $customer_card_id )
				|| isset( $card_nonce ) )
			) {

				$idempotency_key = (string) $order_id . wp_rand( 10000, 200000 );

				if ( function_exists( 'square_order_sync_add_on' ) ) {
					$amount = (int) round( $this->format_amount( $order->get_total(), $currency ), 1 );
				} else {
					$amount = (int) $this->format_amount( $order->get_total(), $currency );
				}

				$fields = array();
				if ( isset( $_POST['square_customerid'] ) && ! empty( $_POST['square_customerid'] ) ) {
					// Unsanitize and sanitize as needed.
					$customer_id = sanitize_text_field( wp_unslash( $_POST['square_customerid'] ) );

					// Assign the sanitized customer_id to the fields array.
					$fields['customer_id'] = $customer_id;
				}

				if ( ! empty( $_POST['saved_cards'] ) && empty( $_POST[ 'square_plus' . get_transient( 'is_sandbox' ) . 'sq-card-saved' ] ) ) {
					$fields['source_id'] = isset( $_POST['saved_cards'] ) ? sanitize_text_field( wp_unslash( $_POST['saved_cards'] ) ) : '';
					$order->add_meta_data( '_woos_plus_customer_card_id', sanitize_text_field( wp_unslash( $_POST['saved_cards'] ) ) );
					$order->update_meta_data( '_woos_plus_source_id', sanitize_text_field( wp_unslash( $_POST['saved_cards'] ) ) );
					if ( $parent_order_id ) {
						$parent_order->update_meta_data( '_woos_plus_customer_card_id', sanitize_text_field( wp_unslash( $_POST['saved_cards'] ) ) );
					}
					$user_id               = get_current_user_id();
					$_square_customer_id   = get_user_meta( $user_id, '_square_customer_id', true );
					$fields['customer_id'] = $_square_customer_id;
				} else {
					$order->update_meta_data( '_woos_plus_source_id', $card_nonce );
					$fields['source_id'] = $card_nonce;
				}

				$fields['autocomplete']    = $this->capture ? false : true;
				$fields['idempotency_key'] = $idempotency_key;
				$fields['location_id']     = $location_id;
				$fields['amount_money']    = array(
					'amount'   => $amount,
					'currency' => $currency,
				);
				if ( $subscription
					|| ( isset( $customer_card_id ) && ! empty( $_POST['issavedtoken'] ) )
					|| ( isset( $_POST['_wcf_flow_id'] ) && is_numeric( $_POST['_wcf_flow_id'] ) && isset( $_POST['_wcf_checkout_id'] ) && is_numeric( $_POST['_wcf_checkout_id'] ) && empty( $_POST['saved_cards'] ) )
				) {

					$fields['source_id'] = $customer_card_id;
					$order->add_meta_data( '_woos_plus_customer_card_id', $customer_card_id );
					if ( $parent_order_id ) {
						$parent_order->update_meta_data( '_woos_plus_customer_card_id', $customer_card_id );
						$parent_order->save();
					}
					$fields['customer_id'] = $square_customer_id;
				}

				if ( ! empty( $shipping_address['country'] ) ) {
					$fields['shipping_address'] = $shipping_address;
				}
				$fields['billing_address']    = $billing_address;
				$fields['reference_id']       = (string) $order->get_order_number();
				$fields['note']               = apply_filters( 'woosquare_payment_order_note', 'WooCommerce: Order #' . (string) $order->get_order_number() . ' ' . $first_name . ' ' . $last_name, $order );
				$fields['verification_token'] = $buyer_verification_token;

				// need to add order creation function and get the order id.
				// order sync must be used in live environment ..
				if ( get_option( 'woo_square_customer_sync_square_order_sync' ) === '1' ) {
					$user_id = $order->get_customer_id();
					if ( empty( $user_id ) ) {
						$_square_customer_id = $order->get_meta( '_square_customer_id', true );
					} else {
						$_square_customer_id = get_user_meta( $user_id, '_square_customer_id', true );
					}
					$fields['customer_id'] = $_square_customer_id;
				}

				if ( ( function_exists( 'square_order_sync_add_on' ) && ! isset( $_POST['funnel_order'] ) ) ||
					( function_exists( 'square_order_sync_add_on' ) && isset( $_POST['_wcf_flow_id'] ) )
				) {

					$fields['order_id'] = square_order_sync_add_on( $order, $location_id, $currency, $idempotency_key, $this->token, 'squareup' . get_transient( 'is_sandbox' ), $fields['customer_id'] );
					if ( ! empty( get_transient( 'squresettotal' ) ) ) {
						$fields['amount_money']['amount'] = get_transient( 'squresettotal' );
						$forordernote                     = 'reset the payment total to the total calculated by Square to prevent errors';
						delete_transient( 'squresettotal' );
					}
				}

				if ( ! empty( $_POST['saved_cards'] ) || ( isset( $_POST[ 'square_plus' . get_transient( 'is_sandbox' ) . 'sq-card-saved' ] ) && 'on' === $_POST[ 'square_plus' . get_transient( 'is_sandbox' ) . 'sq-card-saved' ] ) ) {
					$fields['source_id']   = $card_nonce;
					$fields['customer_id'] = isset( $_POST['square_customerid'] ) ? sanitize_text_field( wp_unslash( $_POST['square_customerid'] ) ) : '';
				}

				if ( isset( $_POST['funnel_order'] ) && ! empty( $_POST['funnel_order'] ) ) {
					$fields['source_id']   = $card_nonce;
					$fields['customer_id'] = isset( $_POST['square_customerid'] ) ? sanitize_text_field( wp_unslash( $_POST['square_customerid'] ) ) : '';
				}
				$order->update_meta_data( '_woos_plus_source_id', $card_nonce );
				$order->update_meta_data( '_woos_plus_customer_id', isset( $_POST['square_customerid'] ) ? sanitize_text_field( wp_unslash( $_POST['square_customerid'] ) ) : '' );

				$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/payments';

				$headers          = array(
					'Accept'         => 'application/json',
					'Authorization'  => 'Bearer ' . $this->token,
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
								'body'        => wp_json_encode( apply_filters( 'payload_transaction_fields', $fields, $order_id, $location_id ) ),
							)
						)
					)
				);

				$order = new WC_Order( $order_id );
				if ( isset( $transaction_data->payment->id ) && 'CAPTURED' === $transaction_data->payment->card_details->status ) {
					$transaction_id = $transaction_data->payment->id;
					$order->add_meta_data( 'woosquare_transaction_id', $transaction_id );
					$order->add_meta_data( '_transaction_id', $transaction_id );
					$order->add_meta_data( 'woosquare_transaction_location_id', $location_id );
					if ( isset( $forordernote ) ) {
						$order->add_meta_data( 'squresettotal_forordernote', $forordernote );
					}
					// if sandbox enable add sandbox prefix.
					$sandbox_prefix = get_transient( 'is_sandbox' ) ? 'through sandbox' : '';
					$amount         = number_format( $transaction_data->payment->amount_money->amount / 100, 2 ); // Assuming amount is in cents.
					set_transient( 'squwfocu_order_id', $order_id, 2400 );

					// Mark as processing.

					// clear cart.
					WC()->cart->empty_cart();

					if ( isset( $_POST['funnel_order'] ) ) {
						if ( function_exists( 'WFOCU_Core' ) ) {
							$get_offer = WFOCU_Core()->offers->get_the_first_offer();
						}

						if ( ! empty( $get_offer ) ) {
							$get_return_url = $this->get_the_upsell_url( $get_offer );
						} else {
							$get_return_url = $this->get_return_url( $order );
						}
					} else {
						$get_return_url = $this->get_return_url( $order );
					}

					/* translators: 1: Sandbox prefix (e.g., '[SANDBOX]'), 2: Transaction ID, 3: Payment amount in dollars. */
					$message = sprintf( __( 'Square Credit Card Payment %1$s complete for $%3$s (Transaction ID: %2$s).', 'woosquare' ), $sandbox_prefix, $transaction_id, $amount );
					$order->update_status( apply_filters( 'square_order_status_woo_to_square', 'processing' ), $message );

					$order->payment_complete( $transaction_data->payment->id );
					// Return thank you page redirect.
					$order->save();
					return array(
						'result'   => 'success',
						'redirect' => $get_return_url,
					);

				} elseif ( isset( $transaction_data->payment->id ) && 'AUTHORIZED' === $transaction_data->payment->card_details->status ) {
					// Store captured value.
					$transaction_id = $transaction_data->payment->id;
					$order->update_meta_data( '_square_charge_captured', 'no' );
					$order->add_meta_data( 'woosquare_transaction_id', $transaction_id, true );
					$order->add_meta_data( '_transaction_id', $transaction_id );
					$order->add_meta_data( 'woosquare_transaction_location_id', $location_id );

					// Mark as on-hold.

					// translators: %s is the payment id.
					$authorized_message = sprintf( __( 'Square charge authorized (Authorized ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woosquare' ), $transaction_data->payment->id );
					$order->update_status( 'on-hold', $authorized_message );
					$order->add_order_note( $authorized_message );
					$this->log( "Success: $authorized_message" );

					// Reduce stock levels.
					version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->reduce_order_stock() : wc_reduce_stock_levels( $order_id );

					// clear cart.
					WC()->cart->empty_cart();
					$order->save();
					// Return thank you page redirect.

					return array(
						'result'   => 'success',
						'redirect' => $this->get_return_url( $order ),
					);

				} else {
					$message = '';
					if ( ! empty( $transaction_data->card_details->errors ) ) {
						foreach ( $transaction_data->card_details->errors as $error ) {
							$message .= $error->detail;
							if ( isset( $error->field ) ) {
								$message .= $error->field . ' - ' . $error->detail;
							}
						}
					} else {
						foreach ( $transaction_data->errors as $error ) {
							$message .= $error->code . ' - ' . $error->detail . ' - ' . $error->category;
						}
						$message .= '</br><a target="_blank" href="https://developer.squareup.com/docs/payments-api/error-codes#createpayment-errors"> ERROR CODE REFERENCES </a>';

					}

					if ( get_transient( 'square_fulfillments' ) ) {
						do_action( 'cancelled_orphened_order', get_transient( 'square_fulfillments' ) );
					}

					$order->save();
					wc_add_notice( $message, 'error' );
					// translators: %s is the square payment failed error message.
					$message = sprintf( __( 'Square Payment Failed  %s .', 'woosquare' ), $message );

					$order->update_status( 'failed', $message );
					return array(
						'result'   => 'failure',
						'messages' => $message,
					);
				}
			} elseif ( isset( $customer_card_id ) && $order->get_total() === 0 && wcs_order_contains_subscription( $order_id, array( 'parent', 'renewal' ) ) ) {
				$message = sprintf( __( 'Not charged as cart total is 0.', 'woosquare' ) );
				$order->update_status( apply_filters( 'square_order_status_woo_to_square', 'processing' ), $message );
				$parent_order->save();
				$order->save();
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			}
		} catch ( Exception $ex ) {
			$message = '';
			$errors  = $ex->getResponseBody()->errors;

			foreach ( $errors as $error ) {
				$message = $error->detail;
				if ( isset( $error->field ) ) {
					$message = $error->field . ' - ' . $error->detail;
				}
				wc_add_notice( $message, 'error' );
			}

			$order->update_status( 'failed', $ex->getMessage() );
			$parent_order->save();
			$order->save();
			return array(
				'result'   => 'failure',
				'messages' => $message,
			);
		}
	}

	/**
	 * Retrieves the URL for an upsell offer.
	 *
	 * This function generates the URL for a given upsell offer, handling custom page templates and
	 * checking the publication status of the custom page. If the custom page no longer exists, it
	 * retrieves the URL for the next available offer.
	 *
	 * @param int $offer The ID of the offer.
	 *
	 * @return string The URL for the upsell offer.
	 */
	public function get_the_upsell_url( $offer ) {

		$offer_data = WFOCU_Core()->offers->get_offer( $offer );
		$link       = WFOCU_Core()->offers->get_the_link( $offer );
		if ( 'custom-page' === $offer_data->template ) {
			$custom_page_id = get_post_meta( $offer, '_wfocu_custom_page', true );
			if ( ! empty( $custom_page_id ) ) {
				$get_custom_page_post = get_post( $custom_page_id );

				if ( null === $get_custom_page_post || ( is_object( $get_custom_page_post ) && 'publish' !== $get_custom_page_post->post_status ) ) {
					WFOCU_Core()->log->log( 'Order #' . $this->porder . ':: Skipping this offer# ' . $offer . ' as page ' . $custom_page_id . ' no longer exists.' );

					$get_offer = WFOCU_Core()->offers->get_the_next_offer( 'yes', $offer );

					$link = $this->get_the_upsell_url( $get_offer );
				}
				// The code snippet is 306 lines long; only the first 200 lines are shown. Click here to see the full code.

				return add_query_arg(
					array(
						'wfocu-key' => WFOCU_Core()->data->get_funnel_key(),
						'wfocu-si'  => WFOCU_Core()->data->get_transient_key(),
					),
					$link
				);
			}
		}
	}
	/**
	 * Checks if we need to process pre orders when
	 * pre orders is in the cart.
	 *
	 * @since  4.1.0
	 * @param  int $order_id The WooCommerce order id.
	 * @return bool
	 */
	public function maybe_process_pre_orders( $order_id ) {
		return (
			class_exists( 'WC_Pre_Orders_Order' ) &&
			WC_Pre_Orders_Order::order_contains_pre_order( $order_id ) &&
			WC_Pre_Orders_Order::order_requires_payment_tokenization( $order_id ) &&
			! is_wc_endpoint_url( 'order-pay' )
		);
	}

	/**
	 * Checks if we need to process pre orders when
	 * pre orders is in the cart.
	 *
	 * @since  4.1.0
	 * @param  int $order_id The WooCommerce order id.
	 * @return bool
	 */
	public function process_pre_order( $order_id ) {
		$order = wc_get_order( $order_id );
		// Setup the response early to allow later modifications.

		// Remove cart.
		WC()->cart->empty_cart();
		// Is pre ordered!
		WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
		// Return thank you page redirect.
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

		if ( gettype( $total ) === 'string' ) {
			$total = (float) $total; // In cents.
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
		$order = wc_get_order( $order_id );
		// Get the Order refunds (array of refunds).
		$order_refunds = $order->get_refunds();

		$trans_id          = $order->get_meta( 'woosquare_transaction_id', true );
		$upsell_trans_id   = $order->get_meta( 'upsell_square_transaction_id', true );
		$upsell_product_id = $order->get_meta( 'upsell_square_product_id', true );

		if ( ! $order || ! $trans_id ) {
			if ( $order->get_meta( '_cartflows_offer', true ) === 'yes' ) {
				$trans_id = $order->get_meta( '_transaction_id', true );
			} else {
				return false;
			}
		}
		if ( 'square_plus' . get_transient( 'is_sandbox' ) === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->payment_method : $order->get_payment_method() ) ) {
			try {
				$this->log( "Info: Begin refund for order {$order_id} for the amount of {$amount}" );
				$currency = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_order_currency() : $order->get_currency();

				$location                         = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
				$this->token                      = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
				$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
				if ( get_transient( 'is_sandbox' ) ) {
					$this->connect->set_access_token( $this->token );
				}
				foreach ( $order_refunds as $refund ) {

					// Loop through the order refund line items.
					foreach ( $refund->get_items() as $item_id => $item ) {

						if ( isset( $upsell_trans_id ) && ! empty( $upsell_trans_id ) ) {
							if ( $item->get_product_id() === $upsell_product_id ) {
								$upsell_trans_amount = $order->get_meta( 'upsell_square_transaction_amount', true );

								$transaction_status = $this->connect->get_transaction_status( $upsell_trans_id );
								if ( 'CAPTURED' === $transaction_status ) {

									$fields = array(
										'idempotency_key' => uniqid(),
										'payment_id'      => $upsell_trans_id,
										'reason'          => $reason,
										'amount_money'    => array(
											'amount'   => (int) $upsell_trans_amount,
											'currency' => $currency,
										),
									);

									$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/refunds';
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
													'method' => 'POST',
													'headers' => $headers,
													'httpversion' => '1.0',
													'sslverify' => false,
													'body' => wp_json_encode( $fields ),
												)
											)
										)
									);
									if ( is_wp_error( $result ) ) {
											throw new Exception( $result->get_error_message() );

									} elseif ( ! empty( $result->errors ) ) {
										throw new Exception( 'Error: ' . wp_json_encode( $result->errors ) );

									} elseif ( 'APPROVED' === $result->refund->status || 'PENDING' === $result->refund->status ) {
										$upsell_amount = $upsell_trans_amount / 100;
										$amount        = $amount - $upsell_amount;
										// translators: %1$s is the refunded amount, %2$s is the refund ID, %3$s is the reason for the refund.
										$refund_message = sprintf( __( 'Upsell Offer Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'woosquare' ), wc_price( $result->refund->amount_money->amount / 100 ), $result->refund->id, $reason );
										remove_action( 'woocommerce_order_refunded', 'woo_square_create_refund', 10 );
										$order->add_order_note( $refund_message );

										// Order Items were processed. We can now create a refund.

										$this->log( 'Success: ' . html_entity_decode( wp_strip_all_tags( $refund_message ) ) );

										if ( ( isset( $amount ) && 0 === $amount ) || empty( $amount ) ) {
											return true;
										}
									}
								}
							}
						}
					}
				}
				$transaction_status = $this->connect->get_transaction_status( $trans_id );
				$amount             = (int) $this->format_amount( $amount, $currency );

				if ( 'CAPTURED' === $transaction_status ) {

					$fields = array(
						'idempotency_key' => uniqid(),
						'payment_id'      => $trans_id,
						'reason'          => $reason,
						'amount_money'    => array(
							'amount'   => $amount,
							'currency' => $currency,
						),
					);

					$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/refunds';
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
									'method'      => 'POST',
									'headers'     => $headers,
									'httpversion' => '1.0',
									'sslverify'   => false,
									'body'        => wp_json_encode( apply_filters( 'modify_square_refund_fields', $fields ) ),
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
						$refund_message = sprintf( __( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'woosquare' ), wc_price( $result->refund->amount_money->amount / 100 ), $result->refund->id, $reason );
						remove_action( 'woocommerce_order_refunded', 'woo_square_create_refund', 10 );
						$order->add_order_note( $refund_message );

						// Order Items were processed. We can now create a refund.

						$this->log( 'Success: ' . html_entity_decode( wp_strip_all_tags( $refund_message ) ) );
						return true;
					}
				}
			} catch ( Exception $e ) {
				// translators: %s is the error message.
				$this->log( sprintf( __( 'Error: %s', 'woosquare' ), $e->getMessage() ) );
				return false;
			}
		}
	}

	/**
	 * Process a pre-order payment when the pre-order is released.
	 *
	 * @param WC_Order $order The pre-order to process.
	 * @param bool     $retry Whether to retry processing the pre-order payment.
	 *
	 * @return void
	 */
	public function process_pre_order_release_payment_woosquare( $order, $retry = true ) {

		$pre_order   = $order;
		$token       = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );

		try {
			$parent_order_id = null;
			if ( true ) { // phpcs:ignore
				// shipping address.
				$shipping_country = $pre_order->get_shipping_country() ? $pre_order->get_shipping_country() : $pre_order->get_billing_country();
				if ( ! empty( $shipping_country ) ) {
					$shipping_address = array(
						'address_line_1'                  => $pre_order->get_shipping_address_1() ? $pre_order->get_shipping_address_1() : $pre_order->get_billing_address_1(),
						'address_line_2'                  => $pre_order->get_shipping_address_2() ? $pre_order->get_shipping_address_2() : $pre_order->get_billing_address_2(),
						'locality'                        => $pre_order->get_shipping_city() ? $pre_order->get_shipping_city() : $pre_order->get_billing_city(),
						'administrative_district_level_1' => $pre_order->get_shipping_state() ? $pre_order->get_shipping_state() : $pre_order->get_billing_state(),
						'postal_code'                     => $pre_order->get_shipping_postcode() ? $pre_order->get_shipping_postcode() : $pre_order->get_billing_postcode(),
						'country'                         => $pre_order->get_shipping_country() ? $pre_order->get_shipping_country() : $pre_order->get_billing_country(),
					);
				}
				// billing address.
				$billing_address = array(
					'address_line_1'                  => $pre_order->get_billing_address_1(),
					'address_line_2'                  => $pre_order->get_billing_address_2(),
					'locality'                        => $pre_order->get_billing_city(),
					'administrative_district_level_1' => $pre_order->get_billing_state(),
					'postal_code'                     => $pre_order->get_billing_postcode(),
					'country'                         => $pre_order->get_billing_country() ? $pre_order->get_billing_country() : $pre_order->get_shipping_country(),
				);

				$parent_order_id    = $pre_order->get_id();
				$currency           = $pre_order->get_currency();
				$customer_card_id   = $pre_order->get_meta( '_woos_plus_customer_card_id', true );
				$square_customer_id = null;
				$customer_id        = $pre_order->get_customer_id();

				if ( empty( $square_customer_id ) ) {
					$square_customer_id = get_user_meta( $customer_id, '_square_customer_id', true );
				}

				if ( empty( $square_customer_id ) ) {
					$square_customer_id = $pre_order->get_meta( '_square_customer_id', true );
				}

				if ( $square_customer_id && $customer_card_id ) {

					$idempotency_key = (string) $parent_order_id;

					$fields = array(
						'idempotency_key'  => $idempotency_key,
						'location_id'      => $location_id,
						'amount_money'     => array(
							'amount'   => (int) $this->format_amount( $pre_order->get_total(), $currency ),
							'currency' => $currency,
						),
						'source_id'        => $customer_card_id,
						'customer_id'      => $square_customer_id,
						'shipping_address' => $shipping_address,
						'billing_address'  => $billing_address,
						'reference_id'     => (string) $pre_order->get_order_number(),
						'note'             => 'Order #' . (string) $pre_order->get_order_number(),
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
						$pre_order->add_meta_data( 'woosquare_transaction_id', $transaction_id );
						$pre_order->add_meta_data( '_transaction_id', $transaction_id );
						$pre_order->add_meta_data( 'woosquare_transaction_location_id', $location_id );

						// if sandbox enable add sandbox prefix.
						$sandbox_prefix = get_transient( 'is_sandbox' ) === 'sandbox' ? 'through sandbox' : '';
						// Mark as processing.
						// translators: %1$s is the prefix, %2$s is the transaction ID.
						$message = sprintf( __( 'Customer card successfully charged %1$s (Transaction ID: %2$s) For pre-order.', 'wcsrs-payment' ), $sandbox_prefix, $transaction_id );
						$pre_order->update_status( apply_filters( 'square_order_status_woo_to_square', 'processing' ), $message );
						$order_stock_reduced = $order->get_meta( '_order_stock_reduced', true );

						if ( ! $order_stock_reduced ) {
							wc_reduce_stock_levels( $parent_order_id );
						}

						$order->set_transaction_id( $transaction_id );
					} else {
						$pre_order->add_order_note( 'Errors: ' . wp_json_encode( $transaction_data->errors ) . ' </br><a target="_blank" href="https://developer.squareup.com/docs/payments-api/error-codes#createpayment-errors"> ERROR CODE REFERENCES </a>' );
						$pre_order->update_status( 'failed' );
					}
				}
			}
			// }
			$pre_order->save();
		} catch ( Exception $ex ) {
			$pre_order->save();
			$pre_order->update_status( 'failed', $ex->getMessage() );
		}
	}

	/**
	 * Logs
	 *
	 * @since   1.0.0
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
$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );
if ( isset( $activate_modules_woosquare_plus['woosquare_payment']['module_activate'] ) && true === $activate_modules_woosquare_plus['woosquare_payment']['module_activate'] ) {
	if ( ! isset( $_GET['wfacp_id'] ) ) { // phpcs:ignore
		include 'class-woosquaregooglepay-gateway.php';
		include 'class-woosquareafterpay-gateway.php';
		include 'class-woosquarecashapp-gateway.php';
		include 'squareplusgiftcardcoupen-class.php';
		include 'class-woosquareachpayment-gateway.php';
		include 'class-woosquareapplepay-gateway.php';
		include 'class-woosquarepos-gateway.php';
	}
}
