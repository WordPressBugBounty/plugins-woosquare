<?php
/**
 * Woosquare_Payment_Block class
 *
 * This class extends the `AbstractPaymentMethodType` class from WooCommerce Blocks and manages Square and related payment methods within the WooCommerce platform.
 *
 * @since   1.0.0
 * @package WooCommerce\Payments\Square
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;
#[AllowDynamicProperties]

/**
 * Class Woosquare_Payment_Block
 *
 * This class represents a payment method for Square integration in WooCommerce.
 *
 * @package Woosquare_Plus
 */
class Woosquare_Payment_Block extends AbstractPaymentMethodType {


	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * The Payment Request configuration class used for Shortcode PRBs. We use it here to retrieve
	 * the same configurations.
	 *
	 * @var WC_Stripe_Payment_Request payment request configuration.
	 */
	private $payment_request_configuration;

	/**
	 * Constructor
	 *
	 * @param WC_Stripe_Payment_Request|null $payment_request_configuration The Stripe Payment Request configuration used for Payment Request buttons.
	 */
	public function __construct( $payment_request_configuration = null ) {
		$this->name  = 'square_plus' . get_transient( 'is_sandbox' );
		$this->token = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', array( $this, 'add_stripe_intents' ), 9999, 2 );
	}

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {

		$this->settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
		include_once plugin_dir_path( __DIR__ ) . 'square-payments/class-woosquare-plus-gateway.php';

		include_once plugin_dir_path( __DIR__ ) . 'square-payments/class-woosquarepos-gateway.php';
		$woosquare_plus_gateway                 = new WooSquare_Plus_Gateway();
		$this->description                      = $woosquare_plus_gateway->description;
		$woocommerce_square_google_pay_settings = get_option( 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( isset( $woocommerce_square_google_pay_settings['enabled'] ) && 'yes' === $woocommerce_square_google_pay_settings['enabled'] ) {
			$woosquare_plus_google_gateway = new WooSquareGooglePay_Gateway();

			$this->google_method_enabled = $woosquare_plus_google_gateway->enabled;
			$this->google_method_title   = $woosquare_plus_google_gateway->method_title;
		}

		$woocommerce_square_after_pay_settings = get_option( 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( isset( $woocommerce_square_after_pay_settings['enabled'] ) && 'yes' === $woocommerce_square_after_pay_settings['enabled'] ) {
			$woosquare_plus_afterpay_gateway = new WooSquareAfterPay_Gateway();
			$this->afterpay_method_enabled   = $woosquare_plus_afterpay_gateway->enabled;
			$this->afterpay_method_title     = $woosquare_plus_afterpay_gateway->method_title;
		}

		$woocommerce_square_cash_app_pay_settings = get_option( 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( isset( $woocommerce_square_cash_app_pay_settings['enabled'] ) && 'yes' === $woocommerce_square_cash_app_pay_settings['enabled'] ) {
			$woosquare_plus_cashapp_gateway = new WooSquareCashApp_Gateway();
			$this->cashapp_method_enabled   = $woosquare_plus_cashapp_gateway->enabled;
			$this->cashapp_method_title     = $woosquare_plus_cashapp_gateway->method_title;
		}

		$woocommerce_square_ach_payment_settings = get_option( 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( isset( $woocommerce_square_ach_payment_settings['enabled'] ) && 'yes' === $woocommerce_square_ach_payment_settings['enabled'] ) {
			$woosquare_plus_ach_gateway = new WooSquareACHPayment_Gateway();
			$this->ach_method_enabled   = $woosquare_plus_ach_gateway->enabled;
			$this->ach_method_title     = $woosquare_plus_ach_gateway->method_title;
		}

		$woocommerce_square_apple_pay_enabled = get_option( 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( isset( $woocommerce_square_apple_pay_enabled['enabled'] ) && 'yes' === $woocommerce_square_apple_pay_enabled['enabled'] ) {
			$woosquare_plus_apple_gateway = new WooSquareApplePay_Gateway();
			$this->apple_method_enabled   = $woosquare_plus_apple_gateway->enabled;
			$this->apple_method_title     = $woosquare_plus_apple_gateway->method_title;
		}

		$woocommerce_square_pos_enabled = get_option( 'woocommerce_square_terminal_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( isset( $woocommerce_square_pos_enabled['enabled'] ) && 'yes' === $woocommerce_square_pos_enabled['enabled'] ) {
			$woosquare_plus_pos_gateway = new WooSquarePOS_Gateway();
			$this->pos_method_enabled   = $woosquare_plus_pos_gateway->enabled;
			$this->pos_method_title     = $woosquare_plus_pos_gateway->method_title;
		}
	}
	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		$woosquare_plus_gateway = new WooSquare_Plus_Gateway();
		$is_active              = $woosquare_plus_gateway->is_available();
		return $is_active;
	}

	/**
	 * Register scripts
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {

		$asset_path = WOO_SQUARE_PLUS_PLUGIN_PATH . 'admin/modules/square-payments/build/index.asset.php';

		$version      = WOOSQUARE_VERSION;
		$dependencies = array();
		$location     = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		if ( file_exists( $asset_path ) ) {
			$asset = include $asset_path;

			$version      = is_array( $asset ) && isset( $asset['version'] ) ? $asset['version'] : $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] ) ? $asset['dependencies'] : $dependencies;
		}
		wp_register_script(
			'woosquare-credit-card-blocks-integration',
			WOO_SQUARE_PLUGIN_URL_PLUS . 'admin/modules/square-payments/build/index.js',
			$dependencies,
			$version,
			true
		);

		$woocommerce_square_gift_card_pay_enabled = get_option( 'woocommerce_square_gift_card_pay_enabled' . get_transient( 'is_sandbox' ) );

		wp_register_script( 'woosquare_index_script', WOO_SQUARE_PLUGIN_URL_PLUS . 'admin/modules/square-payments/src/index-script.js?apprand=' . wp_rand(), array( 'jquery' ), '1.0', true );
		wp_localize_script(
			'woosquare_index_script',
			'square_index_params',
			array(
				'application_id'                           => WOOSQU_PLUS_APPID,
				'ajax_url'                                 => admin_url( 'admin-ajax.php' ),
				'sandbox'                                  => get_transient( 'is_sandbox' ),
				'location_id'                              => apply_filters( 'modify_square_location_id', $location ),
				'method_name'                              => $this->name,
				'square_pay_nonce'                         => wp_create_nonce( 'square-pay-nonce' ),
				'description'                              => $this->description,
				'woocommerce_square_gift_card_pay_enabled' => isset( $woocommerce_square_gift_card_pay_enabled ) && 'yes' === $woocommerce_square_gift_card_pay_enabled,
			)
		);
		wp_enqueue_script( 'woosquare_index_script' );

		return array( 'woosquare-credit-card-blocks-integration' );
	}

	/**
	 * Generates the HTML for the WooSquare Terminal payment button.
	 *
	 * This function returns the HTML markup for displaying a payment button that
	 * integrates with WooSquare Terminal. The button allows users to initiate a
	 * payment via Square Terminal when placing an order.
	 *
	 * @return string The HTML for the Terminal payment button.
	 */
	public function get_woosquare_terminal_pay_button() {

		include_once plugin_dir_path( __DIR__ ) . 'square-payments/class-woosquarepos-gateway.php';
		$woosquare_plus_terminal_gateway = new WooSquarePOS_Gateway();
		$payment                         = __( 'Click place order', 'woosquare' );
		$allowed                         = array(
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
			$payment = apply_filters( 'woocommerce_square_description', wpautop( wp_kses( $woosquare_plus_terminal_gateway->description, $allowed ) ) );
		}
		return '<div id="payment-form">
				<div>
				<button id="terminal-pay-button">
				<img class="terminal-pay-button-img" src="' . WOOSQUARE_PLUGIN_URL_PAYMENT . '/img/square.png" />
				<p>' . $payment . '</p>
				</button>
				<div style="display:none" id="terminal-pay-button-loader">

				</div>
				
			</div>
			</div>
			<div id="payment-status-container"></div>';
	}

	/**
	 * Generates the HTML for the WooSquare save cards form during checkout.
	 *
	 * This function builds and returns the HTML for displaying the save cards option
	 * during WooSquare checkout. It includes the card payment form and the option
	 * for users to save their cards for future use, depending on the activated modules.
	 *
	 * @return string The HTML for the save cards form.
	 */
	public function get_woosquare_save_cards() {

		include_once plugin_dir_path( __DIR__ ) . 'square-payments/class-woosquare-plus-gateway.php';
		$woosquare_plus_gateway          = new WooSquare_Plus_Gateway();
		$html                            = '';
		$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );

		if ( $activate_modules_woosquare_plus['woosquare_card_on_file']['module_activate'] ) {
			$user = wp_get_current_user();

			$square_id = get_user_meta( $user->ID, '_square_customer_id', true );

			if ( ! empty( $square_id ) ) {
				$customers = $woosquare_plus_gateway->get_cus( $square_id );
			}
		}
		$html   .= '<fieldset class="wooSquare-checkout">';
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
			$html .= '<p>' . apply_filters( 'woocommerce_square_description', wpautop( wp_kses( $this->description, $allowed ) ) ) . '</p>';
		}
		$checkpaymentform = false;
		$value            = '';
		$boolean          = '';
		$checkpaymentform = apply_filters( 'checkpaymentform', $value, $boolean );

		$html .= '<div  id="payment-form">
					<div id="card-container_payment"></div>
				</div>';

		if ( ! empty( $customers->customer ) ) {
			$html .= '<input type="hidden" value="' . $customers->customer->id . '" id="saved_cards_customer_id" class="saved_cards_squ_customer_id" name="saved_cards_customer_id" />';

		}
		$html .= '<div id="payment-status-container"></div>';

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
			$html                         .= '<input type="hidden" name="is_preorder" class="is_preorder" value="1" />';
		}
		if ( $activate_modules_woosquare_plus['woosquare_card_on_file']['module_activate']
			&& is_user_logged_in()
			&& ! $subs
			&& !isset($_GET['wfacp_id']) // phpcs:ignore
		) {
			$html .= '<p class="form-row form-row-wide">
					<label for="sq-card-saved"><span class="required"></span></label>
					<label><input id="sq-card-saved" type="checkbox" autocomplete="off" placeholder="Card Postal Code" name="' . $this->name . 'sq-card-saved" />Save this card for future use</label>
				</p>';
		}
			$html .= '</fieldset>';
			return $html;
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @since  2.5
	 * @return array
	 */
	public function get_payment_method_data() {

		return array(
			'application_id'           => WOOSQU_PLUS_APPID,
			'method_title'             => $this->settings['title'],
			'sandbox'                  => get_transient( 'is_sandbox' ),
			'square_google_pay_id'     => 'square_google_pay' . get_transient( 'is_sandbox' ),
			'google_method_enabled'    => isset( $this->google_method_enabled ) && 'yes' === $this->google_method_enabled,
			'google_method_title'      => isset( $this->google_method_title ) ? $this->google_method_title : '',
			'square_after_pay_enabled' => isset( $this->afterpay_method_enabled ) && 'yes' === $this->afterpay_method_enabled,
			'square_after_pay_id'      => 'square_after_pay' . get_transient( 'is_sandbox' ),
			'afterpay_method_title'    => isset( $this->afterpay_method_title ) ? $this->afterpay_method_title : '',
			'square_cash_app_enabled'  => isset( $this->cashapp_method_enabled ) && 'yes' === $this->cashapp_method_enabled,
			'square_cash_app_id'       => 'square_cash_app_pay' . get_transient( 'is_sandbox' ),
			'cashapp_method_title'     => isset( $this->cashapp_method_title ) ? $this->cashapp_method_title : '',
			'square_ach_pay_enabled'   => isset( $this->ach_method_enabled ) && 'yes' === $this->ach_method_enabled,
			'square_ach_pay_id'        => 'square_ach_payment' . get_transient( 'is_sandbox' ),
			'ach_method_title'         => isset( $this->ach_method_title ) ? $this->ach_method_title : '',
			'square_apple_pay_enabled' => isset( $this->apple_method_enabled ) && 'yes' === $this->apple_method_enabled,
			'square_apple_pay_id'      => 'square_apple_pay' . get_transient( 'is_sandbox' ),
			'apple_method_title'       => isset( $this->apple_method_title ) ? $this->apple_method_title : '',
			'square_pos_enabled'       => isset( $this->pos_method_enabled ) && 'yes' === $this->pos_method_enabled,
			'square_pos_id'            => 'square_terminal_pay' . get_transient( 'is_sandbox' ),
			'pos_method_title'         => isset( $this->pos_method_title ) ? $this->pos_method_title : '',
			'saved_cards'              => $this->get_woosquare_save_cards(),
			'terminal_button'          => $this->get_woosquare_terminal_pay_button(),
		);
	}

	/**
	 * Handles any potential stripe intents on the order that need handled.
	 *
	 * This is configured to execute after legacy payment processing has
	 * happened on the woocommerce_rest_checkout_process_payment_with_context
	 * action hook.
	 *
	 * @param PaymentContext $context Holds context for the payment.
	 * @param PaymentResult  $result  Result object for the payment.
	 */
	public function add_stripe_intents( PaymentContext $context, PaymentResult &$result ) {

		if ( 'square_plus' . get_transient( 'is_sandbox' ) === $context->payment_method ) {
			$payment_details = $result->payment_details;

			$result->set_payment_details( $payment_details );
			$result->set_status( 'success' );
		}
	}
}
