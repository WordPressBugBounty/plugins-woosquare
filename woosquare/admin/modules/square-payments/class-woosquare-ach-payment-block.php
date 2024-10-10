<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;

class woosquare_payment_block extends AbstractPaymentMethodType
{
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
	 * @var WC_Stripe_Payment_Request
	 */
	private $payment_request_configuration;

	/**
	 * Constructor
	 *
	 * @param WC_Stripe_Payment_Request  The Stripe Payment Request configuration used for Payment
	 *                                   Request buttons.
	 */
	public function __construct( $payment_request_configuration = null ) {

		$this->name		            = 'square_ach_payment'.get_transient('is_sandbox');
		$this->token           = get_option( 'woo_square_access_token'.get_transient('is_sandbox'));
		// add_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this, 'add_payment_request_order_meta' ], 8, 2 );
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this, 'add_stripe_intents' ], 9999, 2 );
		// $this->payment_request_configuration = null !== $payment_request_configuration ? $payment_request_configuration : new WC_Stripe_Payment_Request();
	}

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option('woocommerce_square_plus'.get_transient('is_sandbox').'_settings');
		
	}
	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {

		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Register scripts
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		
		$asset_path   = WP_PLUGIN_DIR .'/woosquare-free/build/index.asset.php';
		
		$version      = WOOSQUARE_VERSION;
		$dependencies = array();
		$location = get_option('woo_square_location_id'.get_transient('is_sandbox'));
		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
            
			$version      = is_array( $asset ) && isset( $asset['version'] ) ? $asset['version'] : $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] ) ? $asset['dependencies'] : $dependencies;
		}
		
		// wp_enqueue_style( 'woosquare-cart-checkout-block', $this->plugin->get_plugin_url() . '/assets/css/frontend/wc-square-cart-checkout-blocks.min.css', array(), Plugin::VERSION );
		wp_register_script(
			'woosquare-credit-card-blocks-integration',
			get_site_url().'/wp-content/plugins/woosquare-free/build/index.js',
			$dependencies,
			$version,
			true
		);
		wp_register_script( 'woosquare_index_script', get_site_url().'/wp-content/plugins/woosquare-free/src/index-script.js?apprand='.rand(), array( 'jquery' ), '', true );
		wp_localize_script( 'woosquare_index_script', 'square_index_params', array(
			'application_id'               => WOOSQU_PLUS_APPID,
			'ajax_url'                     => admin_url('admin-ajax.php'),
			// 'environment'                  =>  $env ,
			'locationId'                   =>  $location,
			'method_name'					=> $this->name,
			'square_pay_nonce'					=> wp_create_nonce( 'square-pay-nonce' ),
		) );
		wp_enqueue_script( 'woosquare_index_script' );
		// wp_set_script_translations( 'woosquare-credit-card-blocks-integration', 'woosquare' );

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
			'application_id'               => WOOSQU_PLUS_APPID,
			'method_title'					=> $this->settings['title'],
			'sandbox'						=>	get_transient('is_sandbox'),
			// 'woosquare_card'				=> $this->get_woosquare_saved_cards(),
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
		
		if ( 'square_plus'.get_transient('is_sandbox') === $context->payment_method ) {
			$payment_details       = $result->payment_details;
			
			$result->set_payment_details( $payment_details );
			$result->set_status( 'success' );
		}
	}
}

?>