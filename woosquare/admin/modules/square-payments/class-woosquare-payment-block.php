<?php
/**
 * Woosquare_Payment_Block class
 *
 * This class extends the `AbstractPaymentMethodType` class from WooCommerce Blocks and manages Square and related payment methods within the WooCommerce platform.
 *
 * @since 1.0.0
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
		$woosquare_plus_gateway                 = new WooSquare_Plus_Gateway();
		$this->description                      = $woosquare_plus_gateway->description;
		$woocommerce_square_google_pay_settings = get_option( 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( isset( $woocommerce_square_google_pay_settings['enabled'] ) && 'yes' === $woocommerce_square_google_pay_settings['enabled'] ) {
			$woosquare_plus_google_gateway = new WooSquareGooglePay_Gateway();
			$this->google_method_title     = $woosquare_plus_google_gateway->method_title;
		}

		$woocommerce_square_after_pay_settings = get_option( 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( isset( $woocommerce_square_after_pay_settings['enabled'] ) && 'yes' === $woocommerce_square_after_pay_settings['enabled'] ) {
			$woosquare_plus_afterpay_gateway = new WooSquareAfterPay_Gateway();
			$this->afterpay_method_title     = $woosquare_plus_afterpay_gateway->method_title;
		}

		$woocommerce_square_cash_app_pay_settings = get_option( 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( isset( $woocommerce_square_cash_app_pay_settings['enabled'] ) && 'yes' === $woocommerce_square_cash_app_pay_settings['enabled'] ) {
			$woosquare_plus_cashapp_gateway = new WooSquareCashApp_Gateway();
			$this->cashapp_method_title     = $woosquare_plus_cashapp_gateway->method_title;
		}

		$woocommerce_square_ach_payment_settings = get_option( 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( isset( $woocommerce_square_ach_payment_settings['enabled'] ) && 'yes' === $woocommerce_square_ach_payment_settings['enabled'] ) {
			$woosquare_plus_ach_gateway = new WooSquareACHPayment_Gateway();
			$this->ach_method_title     = $woosquare_plus_ach_gateway->method_title;
		}

		$woocommerce_square_apple_pay_enabled = get_option( 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( isset( $woocommerce_square_apple_pay_enabled['enabled'] ) && 'yes' === $woocommerce_square_apple_pay_enabled['enabled'] ) {
			$woosquare_plus_apple_gateway = new WooSquareApplePay_Gateway();
			$this->apple_method_title     = $woosquare_plus_apple_gateway->method_title;
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

		$asset_path = WOO_SQUARE_PLUS_PLUGIN_PATH . 'build/index.asset.php';

		$version      = WOOSQUARE_VERSION;
		$dependencies = array();
		$location     = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		if ( file_exists( $asset_path ) ) {
			$asset = require $asset_path;

			$version      = is_array( $asset ) && isset( $asset['version'] ) ? $asset['version'] : $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] ) ? $asset['dependencies'] : $dependencies;
		}

		wp_register_script(
			'woosquare-credit-card-blocks-integration',
			WOO_SQUARE_PLUGIN_URL_PLUS . 'build/index.js',
			$dependencies,
			$version,
			true
		);
		wp_register_script( 'woosquare_index_script', WOO_SQUARE_PLUGIN_URL_PLUS . 'src/index-script.js?apprand=' . wp_rand(), array( 'jquery' ), '1.0', true );
		wp_localize_script(
			'woosquare_index_script',
			'square_index_params',
			array(
				'application_id'   => WOOSQU_PLUS_APPID,
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'locationId'       => $location,
				'method_name'      => $this->name,
				'square_pay_nonce' => wp_create_nonce( 'square-pay-nonce' ),
				'description'      => $this->description,
			)
		);
		wp_enqueue_script( 'woosquare_index_script' );

		return array( 'woosquare-credit-card-blocks-integration' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @since 2.5
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'application_id'        => WOOSQU_PLUS_APPID,
			'method_title'          => $this->settings['title'],
			'sandbox'               => get_transient( 'is_sandbox' ),
			'square_google_pay_id'  => 'square_google_pay' . get_transient( 'is_sandbox' ),
			'google_method_title'   => isset( $this->google_method_title ) ? $this->google_method_title : '',
			'square_after_pay_id'   => 'square_after_pay' . get_transient( 'is_sandbox' ),
			'afterpay_method_title' => isset( $this->afterpay_method_title ) ? $this->afterpay_method_title : '',
			'square_cash_app_id'    => 'square_cash_app_pay' . get_transient( 'is_sandbox' ),
			'cashapp_method_title'  => isset( $this->cashapp_method_title ) ? $this->cashapp_method_title : '',
			'square_ach_pay_id'     => 'square_ach_payment' . get_transient( 'is_sandbox' ),
			'ach_method_title'      => isset( $this->ach_method_title ) ? $this->ach_method_title : '',
			'square_apple_pay_id'   => 'square_apple_pay' . get_transient( 'is_sandbox' ),
			'apple_method_title'    => isset( $this->apple_method_title ) ? $this->apple_method_title : '',
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
