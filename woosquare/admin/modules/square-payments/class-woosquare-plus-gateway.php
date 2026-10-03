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
			if ( ! get_transient( 'is_sandbox' ) && ! wc_checkout_is_https() ) {
				$is_available = false;
			}

			if ( ! get_transient( 'is_sandbox' ) && empty( $this->token ) ) {
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
				// Dedupe by fingerprint (fallback to card ID) so the same physical card is shown once.
				$unique_cards      = array();
				$seen_cards_lookup = array();

				foreach ( $customers->customer->cards as $card_item ) {
					$fingerprint = isset( $card_item->fingerprint ) ? $card_item->fingerprint : '';
					$brand       = isset( $card_item->card_brand ) ? $card_item->card_brand : '';
					$last4       = isset( $card_item->last_4 ) ? $card_item->last_4 : '';
					$exp_month   = isset( $card_item->exp_month ) ? $card_item->exp_month : '';
					$exp_year    = isset( $card_item->exp_year ) ? $card_item->exp_year : '';

					$dedupe_key = $this->get_card_dedupe_key( $fingerprint, $brand, $last4, $exp_month, $exp_year );

					if ( empty( $dedupe_key ) ) {
						continue;
					}

					if ( isset( $seen_cards_lookup[ $dedupe_key ] ) ) {
						continue;
					}

					$seen_cards_lookup[ $dedupe_key ] = true;
					$unique_cards[]                   = $card_item;
				}

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
				foreach ( $unique_cards as $cards ) {
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
	 * Retrieve Square card details (including fingerprint) by card ID.
	 *
	 * @param string $card_id Card ID returned by Square (e.g. ccof:...).
	 * @return object|null    Decoded response or null on failure.
	 */
	private function get_square_card_details( $card_id ) {
		if ( empty( $card_id ) ) {
			return null;
		}

		$token = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );

		if ( empty( $token ) ) {
			return null;
		}

		$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/cards/' . $card_id;

		$headers = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Cache-Control' => 'no-cache',
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers'     => $headers,
				'httpversion' => '1.0',
				'sslverify'   => false,
				'timeout'     => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$result = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $result->card ) ) {
			return null;
		}

		return $result;
	}

	/**
	 * Checks whether a value is a Square card-on-file ID.
	 *
	 * @param string $value Candidate payment source value.
	 * @return bool
	 */
	private function is_square_card_on_file_id( $value ) {
		return ! empty( $value ) && 0 === strpos( (string) $value, 'ccof:' );
	}

	/**
	 * Extract persistent card ID from Square payment response.
	 *
	 * @param object $transaction_data Decoded payment response object.
	 * @return string
	 */
	private function extract_persistent_card_id_from_payment_response( $transaction_data ) {
		if ( ! is_object( $transaction_data ) || empty( $transaction_data->payment ) ) {
			return '';
		}

		if ( ! empty( $transaction_data->payment->card_details->card->id ) && $this->is_square_card_on_file_id( $transaction_data->payment->card_details->card->id ) ) {
			return $transaction_data->payment->card_details->card->id;
		}

		if ( ! empty( $transaction_data->payment->source_id ) && $this->is_square_card_on_file_id( $transaction_data->payment->source_id ) ) {
			return $transaction_data->payment->source_id;
		}

		return '';
	}

	/**
	 * Resolve a persistent card ID for recurring use.
	 *
	 * If checkout source is a nonce (cnon:), this tries to find an enabled ccof card
	 * for the Square customer, preferring a last4/expiry match from checkout payload.
	 *
	 * @param string $candidate_card_id Candidate card value (ccof or cnon).
	 * @param string $square_customer_id Square customer ID.
	 * @return string Persistent ccof card ID or empty string.
	 */
	private function resolve_persistent_recurring_card_id( $candidate_card_id, $square_customer_id ) {
		if ( $this->is_square_card_on_file_id( $candidate_card_id ) ) {
			return $candidate_card_id;
		}

		if ( empty( $square_customer_id ) ) {
			return '';
		}

		$preferred_last4 = isset( $_POST['woos_plus_2'] ) ? sanitize_text_field( wp_unslash( $_POST['woos_plus_2'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$preferred_exp_m = isset( $_POST['woos_plus_3'] ) ? sanitize_text_field( wp_unslash( $_POST['woos_plus_3'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$preferred_exp_y = isset( $_POST['woos_plus_4'] ) ? sanitize_text_field( wp_unslash( $_POST['woos_plus_4'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Avoid blind fallback to the first card when checkout metadata is unavailable.
		if ( empty( $preferred_last4 ) && empty( $preferred_exp_m ) && empty( $preferred_exp_y ) ) {
			return '';
		}

		$customer_object = $this->get_cus( $square_customer_id );
		if ( empty( $customer_object->customer->cards ) || ! is_array( $customer_object->customer->cards ) ) {
			return '';
		}

		foreach ( $customer_object->customer->cards as $card ) {
			if ( ! isset( $card->id ) || ! $this->is_square_card_on_file_id( $card->id ) ) {
				continue;
			}

			if ( isset( $card->enabled ) && ! $card->enabled ) {
				continue;
			}

			$last4_match = ! empty( $preferred_last4 ) && isset( $card->last_4 ) && $preferred_last4 === (string) $card->last_4;
			$exp_m_match = ! empty( $preferred_exp_m ) && isset( $card->exp_month ) && (string) $preferred_exp_m === (string) $card->exp_month;
			$exp_y_match = ! empty( $preferred_exp_y ) && isset( $card->exp_year ) && (string) $preferred_exp_y === (string) $card->exp_year;

			if ( $last4_match && ( empty( $preferred_exp_m ) || $exp_m_match ) && ( empty( $preferred_exp_y ) || $exp_y_match ) ) {
				return $card->id;
			}
		}

		return '';
	}

	/**
	 * Persist recurring card/customer identifiers to subscription chain.
	 *
	 * Also syncs active/on-hold/pending Square subscriptions for the Woo user so
	 * Change Payment / Add Payment Method updates renewals (not only Woo tokens).
	 *
	 * @param WC_Order $order Current order object.
	 * @param int|null $parent_order_id Parent order ID if any.
	 * @param array    $subscriptions Subscription objects associated with order.
	 * @param string   $persistent_card_id Resolved persistent card ID.
	 * @param string   $square_customer_id Square customer ID.
	 * @return void
	 */
	private function sync_recurring_payment_meta( $order, $parent_order_id, $subscriptions, $persistent_card_id, $square_customer_id ) {
		if ( ! is_array( $subscriptions ) ) {
			$subscriptions = array();
		}

		$order_customer_id = 0;
		if ( $order && is_object( $order ) && method_exists( $order, 'get_customer_id' ) ) {
			$order_customer_id = (int) $order->get_customer_id();
		}
		if ( empty( $order_customer_id ) && is_user_logged_in() ) {
			$order_customer_id = (int) get_current_user_id();
		}

		// Include all relevant subscriptions for this customer (Change Payment coverage).
		if ( ! empty( $order_customer_id ) && function_exists( 'wcs_get_users_subscriptions' ) ) {
			$user_subscriptions = wcs_get_users_subscriptions( $order_customer_id );
			if ( ! empty( $user_subscriptions ) && is_array( $user_subscriptions ) ) {
				foreach ( $user_subscriptions as $user_sub ) {
					if ( ! is_object( $user_sub ) || ! method_exists( $user_sub, 'get_id' ) ) {
						continue;
					}
					$status = method_exists( $user_sub, 'get_status' ) ? $user_sub->get_status() : '';
					if ( ! in_array( $status, array( 'active', 'on-hold', 'pending' ), true ) ) {
						continue;
					}
					$payment_method = method_exists( $user_sub, 'get_payment_method' ) ? (string) $user_sub->get_payment_method() : '';
					if ( ! empty( $payment_method )
						&& $payment_method !== $this->id
						&& false === strpos( $payment_method, 'square' )
					) {
						continue;
					}
					$subscriptions[ $user_sub->get_id() ] = $user_sub;
				}
			}
		}

		if ( $order && is_object( $order ) ) {
			if ( ! empty( $square_customer_id ) ) {
				$order->update_meta_data( '_square_customer_id', $square_customer_id );
			}

			if ( $this->is_square_card_on_file_id( $persistent_card_id ) ) {
				$order->update_meta_data( '_woos_plus_customer_card_id', $persistent_card_id );
				$order->update_meta_data( '_woos_plus_source_id', $persistent_card_id );
			}
		}

		if ( ! empty( $parent_order_id ) ) {
			$parent_order = wc_get_order( $parent_order_id );
			if ( $parent_order ) {
				if ( ! empty( $square_customer_id ) ) {
					$parent_order->update_meta_data( '_square_customer_id', $square_customer_id );
				}
				if ( $this->is_square_card_on_file_id( $persistent_card_id ) ) {
					$parent_order->update_meta_data( '_woos_plus_customer_card_id', $persistent_card_id );
					$parent_order->update_meta_data( '_woos_plus_source_id', $persistent_card_id );
				}
				$parent_order->save();
			}
		}

		foreach ( $subscriptions as $subscription_item ) {
			if ( ! is_object( $subscription_item ) ) {
				continue;
			}
			if ( ! empty( $square_customer_id ) ) {
				$subscription_item->update_meta_data( '_square_customer_id', $square_customer_id );
			}
			if ( $this->is_square_card_on_file_id( $persistent_card_id ) ) {
				$subscription_item->update_meta_data( '_woos_plus_customer_card_id', $persistent_card_id );
				$subscription_item->update_meta_data( '_woos_plus_source_id', $persistent_card_id );
			}
			$subscription_item->save();

			// Keep each subscription parent order aligned when Change Payment hits one sub.
			if ( method_exists( $subscription_item, 'get_parent_id' ) ) {
				$sub_parent_id = $subscription_item->get_parent_id();
				if ( ! empty( $sub_parent_id ) && (int) $sub_parent_id !== (int) $parent_order_id ) {
					$sub_parent = wc_get_order( $sub_parent_id );
					if ( $sub_parent ) {
						if ( ! empty( $square_customer_id ) ) {
							$sub_parent->update_meta_data( '_square_customer_id', $square_customer_id );
						}
						if ( $this->is_square_card_on_file_id( $persistent_card_id ) ) {
							$sub_parent->update_meta_data( '_woos_plus_customer_card_id', $persistent_card_id );
							$sub_parent->update_meta_data( '_woos_plus_source_id', $persistent_card_id );
						}
						$sub_parent->save();
					}
				}
			}
		}

		if ( ! empty( $order_customer_id ) && ! empty( $square_customer_id ) ) {
			update_user_meta( $order_customer_id, '_square_customer_id', $square_customer_id );
		}
		if ( ! empty( $order_customer_id ) && $this->is_square_card_on_file_id( $persistent_card_id ) ) {
			update_user_meta( $order_customer_id, '_woos_plus_customer_card_id', $persistent_card_id );
		}
	}

	/**
	 * Resolve default WooCommerce Square card-on-file token (ccof:) for a user.
	 *
	 * @param int $customer_id WooCommerce user ID.
	 * @return string
	 */
	protected function get_woo_default_square_ccof( $customer_id ) {
		if ( empty( $customer_id ) || ! class_exists( 'WC_Payment_Tokens' ) ) {
			return '';
		}

		$default_token = WC_Payment_Tokens::get_customer_default_token( $customer_id, $this->id );
		if ( is_null( $default_token ) ) {
			$default_token = WC_Payment_Tokens::get_customer_default_token( $customer_id );
		}
		if ( is_null( $default_token ) ) {
			$all_tokens    = WC_Payment_Tokens::get_customer_tokens( $customer_id );
			$square_tokens = array();
			foreach ( $all_tokens as $token_obj ) {
				$token_gateway_id = $token_obj->get_gateway_id();
				if ( false !== strpos( (string) $token_gateway_id, 'square' ) ) {
					$square_tokens[] = $token_obj;
				}
			}
			if ( ! empty( $square_tokens ) ) {
				$default_token = reset( $square_tokens );
			}
		}

		if ( is_null( $default_token ) ) {
			return '';
		}

		$token_value = $default_token->get_token();
		return $this->is_square_card_on_file_id( $token_value ) ? $token_value : '';
	}

	/**
	 * Whether a stored Square customer identity matches the Woo order customer.
	 * Rejects Instant Profiles (no email) unless reference_id matches the Woo user ID.
	 *
	 * @param string   $square_customer_id Square customer ID.
	 * @param WC_Order $order              Order object.
	 * @return bool
	 */
	protected function square_customer_matches_order( $square_customer_id, $order ) {
		if ( empty( $square_customer_id ) || ! $order ) {
			return false;
		}

		$customer_object = $this->get_cus( $square_customer_id );
		if ( empty( $customer_object->customer ) ) {
			return false;
		}

		$sq_customer = $customer_object->customer;
		$order_email = strtolower( sanitize_email( $order->get_billing_email() ) );
		$sq_email    = ! empty( $sq_customer->email_address ) ? strtolower( (string) $sq_customer->email_address ) : '';

		if ( ! empty( $sq_email ) && ! empty( $order_email ) ) {
			return $sq_email === $order_email;
		}

		$woo_customer_id = (int) $order->get_customer_id();
		$reference_id    = ! empty( $sq_customer->reference_id ) ? (string) $sq_customer->reference_id : '';
		if ( $woo_customer_id && $reference_id === (string) $woo_customer_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Build a stable dedupe key for a card.
	 * Priority: fingerprint; fallback to brand|last4|exp.
	 *
	 * @param string $fingerprint Square fingerprint if available.
	 * @param string $brand       Card brand.
	 * @param string $last4       Last 4 digits.
	 * @param string $exp_month   Expiry month.
	 * @param string $exp_year    Expiry year.
	 *
	 * @return string
	 */
	private function get_card_dedupe_key( $fingerprint = '', $brand = '', $last4 = '', $exp_month = '', $exp_year = '' ) {
		if ( ! empty( $fingerprint ) ) {
			return $fingerprint;
		}

		return strtolower( (string) $brand ) . '|' . (string) $last4 . '|' . (string) $exp_month . '|' . (string) $exp_year;
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
		wp_register_script( 'woocommerce-square', WOOSQUARE_PLUGIN_URL_PAYMENT . '/js/SquarePayments.js', array( 'jquery', 'squareSDK' ), WOOSQUARE_VERSION, true );
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

			// Do not reuse Instant Profiles / mismatched Square customers across Woo users.
			if ( $square_customer_id && ! $this->square_customer_matches_order( $square_customer_id, $order ) ) {
				$this->log(
					sprintf(
						'Square customer %s does not match order identity; ignoring stored ID and resolving by email/create.',
						substr( (string) $square_customer_id, 0, 8 ) . '****'
					)
				);
				$square_customer_id = null;
			}

			// Search by email only if we don't already have a Square customer ID.
			if ( ! $square_customer_id ) {
				$square_customer_id = $this->search_square_customer_by_email( $order );
			}

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
		// Skip nonce verification - handled by process_payment and WooCommerce checkout.
		// Nonce can expire during AJAX updates, causing false failures.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification handled by process_payment and WooCommerce checkout.
		if ( ! isset( $_POST['square_pay_nonce'] ) ) {
			return false; // Don't process if nonce is completely missing.
		}
		$is_subscription    = $this->check_subscription( $order_id ) && empty( $_POST['saved_cards'] );
		$is_card_saved      = isset( $_POST['square_plussq-card-saved'] ) && 'on' === $_POST['square_plussq-card-saved'];
		$is_payment_change  = isset( $_POST['woocommerce_change_payment'] ) && is_numeric( $_POST['woocommerce_change_payment'] );
		$is_wcf_checkout    = isset( $_POST['_wcf_flow_id'] ) && is_numeric( $_POST['_wcf_flow_id'] ) && isset( $_POST['_wcf_checkout_id'] ) && is_numeric( $_POST['_wcf_checkout_id'] ) && empty( $_POST['saved_cards'] );
		$is_pre_order       = $this->maybe_process_pre_orders( $order_id );
		$is_create_customer = $this->create_customer;

		return (
			$is_subscription ||
			$is_card_saved ||
			$is_payment_change ||
			$is_wcf_checkout ||
			$is_pre_order ||
			$is_create_customer
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
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
		$order_email = strtolower( sanitize_email( $order->get_billing_email() ) );
		if ( empty( $order_email ) ) {
			return null;
		}

		$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/customers/search';
		$headers = $this->get_square_api_headers();

		$customer_data = array(
			'query' => array(
				'filter' => array(
					'email_address' => array(
						'exact' => $order_email,
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
		if ( empty( $search_customer['customers'] ) || ! is_array( $search_customer['customers'] ) ) {
			return null;
		}

		// Require an exact email on the Square record — skip Instant Profiles without email.
		foreach ( $search_customer['customers'] as $candidate ) {
			if ( empty( $candidate['id'] ) ) {
				continue;
			}
			$candidate_email = ! empty( $candidate['email_address'] ) ? strtolower( (string) $candidate['email_address'] ) : '';
			if ( empty( $candidate_email ) || $candidate_email !== $order_email ) {
				continue;
			}
			return $candidate['id'];
		}

		return null;
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
		$shipping_first   = sanitize_text_field( $order->get_shipping_first_name() );
		$shipping_last    = sanitize_text_field( $order->get_shipping_last_name() );
		$billing_first    = sanitize_text_field( $order->get_billing_first_name() );
		$billing_last     = sanitize_text_field( $order->get_billing_last_name() );

		$customer_data = array(
			'given_name'    => ! empty( $shipping_first ) ? $shipping_first : $billing_first,
			'family_name'   => ! empty( $shipping_last ) ? $shipping_last : $billing_last,
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

		// Security: Verify WooCommerce checkout process.
		// The 'woocommerce-process-checkout-nonce' is already verified by WooCommerce
		// during checkout submission, so we don't need to verify it again here.
		// This prevents guest checkout failures due to expired nonces during AJAX updates.
		// phpcs:disable WordPress.Security.NonceVerification -- Nonce verification handled by WooCommerce checkout process.
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

		// Check if failed order manual pay.
		$parent_order_id = null;
		$subscription    = false;
		$subscriptions   = array();
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			if ( wcs_order_contains_subscription( $order_id, array( 'parent', 'renewal' ) ) ) {
				$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => array( 'parent', 'renewal' ) ) );
				// Get parent order.
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
			// Shipping address.
			$shipping_address = $this->get_order_billing_shipping_address( $order );
			// Billing address.
			$billing_address = $this->get_order_billing_shipping_address( $order );

			$this->process_square_customer( $order, $parent_order_id, $order_id );
			// Ensure we have a Square customer ID for the payment.
			$square_customer_id = $order->get_meta( '_square_customer_id', true );
			if ( ! empty( $square_customer_id ) && ! $this->square_customer_matches_order( $square_customer_id, $order ) ) {
				$square_customer_id = '';
			}
			if ( empty( $square_customer_id ) ) {
				// Resolve existing Square customer only (user meta / email).
				$customer_id = $order->get_customer_id();
				if ( $customer_id ) {
					$square_customer_id = get_user_meta( $customer_id, '_square_customer_id', true );
					if ( ! empty( $square_customer_id ) && ! $this->square_customer_matches_order( $square_customer_id, $order ) ) {
						$square_customer_id = '';
					}
				}
				if ( empty( $square_customer_id ) ) {
					$square_customer_id = $this->search_square_customer_by_email( $order );
				}
				// WP-1033: create only when Create Customer is enabled, or subscription/card-on-file exceptions apply.
				// Creation is also handled inside process_square_customer(); keep this path guarded too.
				if ( empty( $square_customer_id ) && $this->should_create_or_process_customer( $order_id ) ) {
					$square_customer_id = $this->create_square_customer( $order, $parent_order_id );
				}
				if ( $square_customer_id ) {
					$this->update_order_with_square_customer_id( $order, $parent_order_id, $square_customer_id );
					$order->save();
				}
			}
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

					$allow_new_token  = true;
					$existing_card_id = null;
					$new_card_fp      = '';
					$new_card_brand   = isset( $_POST['woos_plus_1'] ) ? sanitize_text_field( wp_unslash( $_POST['woos_plus_1'] ) ) : '';
					$new_card_last4   = isset( $_POST['woos_plus_2'] ) ? sanitize_text_field( wp_unslash( $_POST['woos_plus_2'] ) ) : '';
					$new_card_exp_m   = isset( $_POST['woos_plus_3'] ) ? sanitize_text_field( wp_unslash( $_POST['woos_plus_3'] ) ) : '';
					$new_card_exp_y   = isset( $_POST['woos_plus_4'] ) ? sanitize_text_field( wp_unslash( $_POST['woos_plus_4'] ) ) : '';

					// Fetch fingerprint for the newly created card.
					$new_card_details = $this->get_square_card_details( $customer_card_id );
					if ( ! empty( $new_card_details->card->fingerprint ) ) {
						$new_card_fp = $new_card_details->card->fingerprint;
					}

					$new_card_key = $this->get_card_dedupe_key( $new_card_fp, $new_card_brand, $new_card_last4, $new_card_exp_m, $new_card_exp_y );

					// Check existing cards for the same customer to avoid duplicates.
					if ( ! empty( $square_customer_id ) ) {
						$existing_customer_cards = $this->get_cus( $square_customer_id );

						if ( ! empty( $existing_customer_cards->customer->cards ) && is_array( $existing_customer_cards->customer->cards ) ) {
							foreach ( $existing_customer_cards->customer->cards as $existing_card ) {
								$existing_fp    = isset( $existing_card->fingerprint ) ? $existing_card->fingerprint : '';
								$existing_brand = isset( $existing_card->card_brand ) ? $existing_card->card_brand : '';
								$existing_last4 = isset( $existing_card->last_4 ) ? $existing_card->last_4 : '';
								$existing_exp_m = isset( $existing_card->exp_month ) ? $existing_card->exp_month : '';
								$existing_exp_y = isset( $existing_card->exp_year ) ? $existing_card->exp_year : '';

								$existing_key = $this->get_card_dedupe_key( $existing_fp, $existing_brand, $existing_last4, $existing_exp_m, $existing_exp_y );

								if ( $existing_key && $new_card_key && $existing_key === $new_card_key && $existing_card->id !== $customer_card_id ) {
									$existing_card_id = $existing_card->id;
									break;
								}
							}
						}
					}

					// If duplicate found, reuse the existing card ID and skip saving a new WC token.
					if ( ! empty( $existing_card_id ) ) {
						$customer_card_id = $existing_card_id;
						$allow_new_token  = false;
					}

					if ( isset( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) && 'new' === $_POST[ 'wc-' . $this->id . '-payment-token' ] ) {
						if ( $allow_new_token ) {
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
					}

					$persistent_card_id = $this->resolve_persistent_recurring_card_id( $customer_card_id, $square_customer_id );
					if ( $this->is_square_card_on_file_id( $persistent_card_id ) ) {
						if ( $parent_order_id ) {
							$parent_order->update_meta_data( '_woos_plus_customer_card_id', $persistent_card_id );
							$parent_order->update_meta_data( '_woos_plus_source_id', $persistent_card_id );
						}
						$order->update_meta_data( '_woos_plus_customer_card_id', $persistent_card_id );
						$order->update_meta_data( '_woos_plus_source_id', $persistent_card_id );
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

				// Change Payment previously saved Woo tokens only — force sync onto all user Square subscriptions.
				$persistent_card_id = '';
				if ( isset( $customer_card_id ) ) {
					$persistent_card_id = $this->resolve_persistent_recurring_card_id( $customer_card_id, $square_customer_id );
					if ( ! $this->is_square_card_on_file_id( $persistent_card_id ) && $this->is_square_card_on_file_id( $customer_card_id ) ) {
						$persistent_card_id = $customer_card_id;
					}
				}
				if ( ! $this->is_square_card_on_file_id( $persistent_card_id ) && ! empty( $_POST['saved_cards'] ) ) {
					$persistent_card_id = sanitize_text_field( wp_unslash( $_POST['saved_cards'] ) );
				}

				$change_subs   = is_array( $subscriptions ) ? $subscriptions : array();
				$change_sub_id = absint( $woocommerce_change_payment );
				if ( $change_sub_id && function_exists( 'wcs_get_subscription' ) ) {
					$change_sub = wcs_get_subscription( $change_sub_id );
					if ( $change_sub ) {
						$change_subs[ $change_sub_id ] = $change_sub;
						if ( empty( $parent_order_id ) && $change_sub->get_parent_id() ) {
							$parent_order_id = $change_sub->get_parent_id();
						}
					}
				}

				$sync_parent_id = ! empty( $parent_order_id ) ? $parent_order_id : ( $order ? $order->get_id() : null );
				$this->sync_recurring_payment_meta( $order, $sync_parent_id, $change_subs, $persistent_card_id, $square_customer_id );

				if ( ! empty( $parent_order ) && is_object( $parent_order ) ) {
					$parent_order->save();
				}
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

				// WP-1033: Square customer ID is required only for subscription/card-on-file/saved-card flows.
				// Normal one-time nonce payments can proceed without creating a Square customer.
				$requires_square_customer = $this->should_create_or_process_customer( $order_id )
					|| ( ! empty( $_POST['saved_cards'] ) )
					|| $subscription;
				if ( empty( $square_customer_id ) && $requires_square_customer ) {
					$order->update_status( 'failed', __( 'Square customer ID is required for payment processing.', 'woosquare' ) );
					return array(
						'result'   => 'failure',
						'messages' => __( 'Unable to process payment. Please try again.', 'woosquare' ),
					);
				}

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

				// One-time payments: attach already-resolved Square customer so Square "Paid by" matches Woo.
				// Does not create customers here — only sends ID resolved earlier in process_payment.
				if ( empty( $fields['customer_id'] ) && ! empty( $square_customer_id ) ) {
					$fields['customer_id'] = $square_customer_id;
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
					$persistent_card_id  = $this->resolve_persistent_recurring_card_id( $customer_card_id, $square_customer_id );
					if ( $this->is_square_card_on_file_id( $persistent_card_id ) ) {
						$order->update_meta_data( '_woos_plus_customer_card_id', $persistent_card_id );
						$order->update_meta_data( '_woos_plus_source_id', $persistent_card_id );
						if ( $parent_order_id ) {
							$parent_order->update_meta_data( '_woos_plus_customer_card_id', $persistent_card_id );
							$parent_order->update_meta_data( '_woos_plus_source_id', $persistent_card_id );
							$parent_order->save();
						}
					}
					// Get the Square customer ID from the order meta data.
					$square_customer_id    = $order->get_meta( '_square_customer_id', true );
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
					$user_id                     = $order->get_customer_id();
					$resolved_square_customer_id = $square_customer_id;
					if ( empty( $user_id ) ) {
						// For guest checkout, use the Square customer ID we already have.
						$_square_customer_id = $resolved_square_customer_id;
					} else {
						$_square_customer_id = get_user_meta( $user_id, '_square_customer_id', true );
						if ( ! empty( $_square_customer_id ) ) {
							$resolved_square_customer_id = $_square_customer_id;
						}
					}
					if ( ! empty( $resolved_square_customer_id ) ) {
						$fields['customer_id'] = $resolved_square_customer_id;
					}
				}

				if ( ( function_exists( 'square_order_sync_add_on' ) && ! isset( $_POST['funnel_order'] ) ) ||
					( function_exists( 'square_order_sync_add_on' ) && isset( $_POST['_wcf_flow_id'] ) )
				) {

					$customer_id        = isset( $fields['customer_id'] ) ? $fields['customer_id'] : '';
					$fields['order_id'] = square_order_sync_add_on( $order, $location_id, $currency, $idempotency_key, $this->token, 'squareup' . get_transient( 'is_sandbox' ), $customer_id );
					if ( ! empty( $fields['order_id'] ) ) {
						$this->persist_square_order_id( $order, $fields['order_id'] );
					}
					if ( ! empty( get_transient( 'squresettotal' ) ) ) {
						$fields['amount_money']['amount'] = get_transient( 'squresettotal' );
						$forordernote                     = 'reset the payment total to the total calculated by Square to prevent errors';
						delete_transient( 'squresettotal' );
					}
				}

				$selected_saved_card_id = isset( $_POST['saved_cards'] ) ? sanitize_text_field( wp_unslash( $_POST['saved_cards'] ) ) : '';
				if ( ! empty( $selected_saved_card_id ) ) {
					$fields['source_id'] = $selected_saved_card_id;
					if ( $this->is_square_card_on_file_id( $selected_saved_card_id ) ) {
						$order->update_meta_data( '_woos_plus_customer_card_id', $selected_saved_card_id );
						$order->update_meta_data( '_woos_plus_source_id', $selected_saved_card_id );
						if ( $parent_order_id ) {
							$parent_order->update_meta_data( '_woos_plus_customer_card_id', $selected_saved_card_id );
							$parent_order->update_meta_data( '_woos_plus_source_id', $selected_saved_card_id );
						}
					}
					$fields['customer_id'] = isset( $_POST['square_customerid'] ) ? sanitize_text_field( wp_unslash( $_POST['square_customerid'] ) ) : $square_customer_id;
				}

				if ( isset( $_POST['funnel_order'] ) && ! empty( $_POST['funnel_order'] ) ) {
					$fields['source_id']   = $card_nonce;
					$fields['customer_id'] = isset( $_POST['square_customerid'] ) ? sanitize_text_field( wp_unslash( $_POST['square_customerid'] ) ) : $square_customer_id;
				}
				if ( isset( $fields['source_id'] ) && ! empty( $fields['source_id'] ) ) {
					$order->update_meta_data( '_woos_plus_source_id', sanitize_text_field( (string) $fields['source_id'] ) );
				}
				$order->update_meta_data( '_woos_plus_customer_id', isset( $_POST['square_customerid'] ) ? sanitize_text_field( wp_unslash( $_POST['square_customerid'] ) ) : $square_customer_id );

				if ( function_exists( 'redeem_loyalty_reward' ) ) {
					/**
					 * ----------------
					 * LOYALTY logic
					 * ----------------
					 */
					$loyalty_account_id = WC()->session->get( 'loyalty_account_id' );
					$reward_tier_id     = WC()->session->get( 'reward_tier_id' );
					$redeeming_rewards  = false;
					$cart_fees          = WC()->cart->get_fees();

					foreach ( $cart_fees as $fee ) {
						if ( isset( $fee->name ) && 'Loyalty Discount' === $fee->name ) {
							$redeeming_rewards = true;
							break;
						}
					}
					if ( ! $redeeming_rewards && WC()->session ) {
						$redeeming_rewards = (bool) WC()->session->get( 'applied_reward_name' );
					}

					// If the loyalty discount fee is applied and we have account + tier.
					if ( $redeeming_rewards && $loyalty_account_id && $reward_tier_id && ! empty( $fields['order_id'] ) ) {

							// Redeem loyalty points.
							$redeemed = redeem_loyalty_reward(
								$fields['order_id'],
								$loyalty_account_id,
								$reward_tier_id,
								$order_id
							);

						if ( 'square-redemption' === get_option( 'wcs_loyalty_redemption_option', '' ) && function_exists( 'woosquare_loyalty_fetch_square_order_total_amount' ) ) {
							$square_total = woosquare_loyalty_fetch_square_order_total_amount( $fields['order_id'] );
							if ( $square_total > 0 ) {
								$fields['amount_money']['amount'] = $square_total;
								$forordernote                     = 'reset the payment total to the total calculated by Square after loyalty redeem';
							}
						}

					}
				}

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

				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					if ( function_exists( 'woosquare_loyalty_restore_points_after_failed_payment' ) ) {
						woosquare_loyalty_restore_points_after_failed_payment( $order_id );
					}
					wc_add_notice( __( 'Unable to process payment. Please try again.', 'woosquare' ), 'error' );
					return array(
						'result'   => 'failure',
						'messages' => __( 'Unable to process payment. Please try again.', 'woosquare' ),
					);
				}

				if ( ! empty( $fields['order_id'] ) ) {
					$this->persist_square_order_id( $order, $fields['order_id'] );
				}

				if ( isset( $transaction_data->payment->id ) && 'CAPTURED' === $transaction_data->payment->card_details->status ) {
					$transaction_id = $transaction_data->payment->id;
					$order->add_meta_data( 'woosquare_transaction_id', $transaction_id );
					$order->add_meta_data( '_transaction_id', $transaction_id );
					$order->add_meta_data( 'woosquare_transaction_location_id', $location_id );
					if ( ! empty( $transaction_data->payment->order_id ) ) {
						$this->persist_square_order_id( $order, $transaction_data->payment->order_id );
					}
					if ( isset( $forordernote ) ) {
						$order->add_meta_data( 'squresettotal_forordernote', $forordernote );
					}
					// If sandbox enable add sandbox prefix.
					$sandbox_prefix = get_transient( 'is_sandbox' ) ? 'through sandbox' : '';
					$amount         = number_format( $transaction_data->payment->amount_money->amount / 100, 2 ); // Assuming amount is in cents.
					set_transient( 'squwfocu_order_id', $order_id, 2400 );

					// Mark as processing.

					// Clear cart.
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
					$order->save();
					$order->update_status( apply_filters( 'square_order_status_woo_to_square', 'processing' ), $message );

					if ( ! empty( $transaction_data->payment->customer_id ) ) {
						$square_customer_id = $transaction_data->payment->customer_id;
					}
					$persistent_card_id = $this->extract_persistent_card_id_from_payment_response( $transaction_data );
					if ( ! $this->is_square_card_on_file_id( $persistent_card_id ) && isset( $customer_card_id ) ) {
						$persistent_card_id = $this->resolve_persistent_recurring_card_id( $customer_card_id, $square_customer_id );
					}
					$this->sync_recurring_payment_meta( $order, $parent_order_id, $subscriptions, $persistent_card_id, $square_customer_id );

					$order->payment_complete( $transaction_data->payment->id );
					if ( function_exists( 'woosquare_loyalty_mark_redeem_committed' ) ) {
						woosquare_loyalty_mark_redeem_committed( $order );
					}
					// Return thank you page redirect.

					if ( WC()->session && is_object( WC()->session ) ) {
						// Clear all session values related to the loyalty reward.
						WC()->session->__unset( 'applied_reward_name' );
						WC()->session->__unset( 'applied_reward_points' );
						WC()->session->__unset( 'loyalty_account_id' );
						WC()->session->__unset( 'reward_tier_id' );
						WC()->session->__unset( 'store_credit_discount_type' );
						WC()->session->__unset( 'store_credit_discount_amount' );
					}

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

					if ( function_exists( 'woosquare_loyalty_mark_redeem_committed' ) ) {
						woosquare_loyalty_mark_redeem_committed( $order );
					}
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

					// WSSS-412: restore loyalty points when payment fails after redeem.
					if ( function_exists( 'woosquare_loyalty_restore_points_after_failed_payment' ) ) {
						woosquare_loyalty_restore_points_after_failed_payment( $order );
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

			$fail_order = isset( $order ) && $order ? $order : ( isset( $order_id ) ? wc_get_order( $order_id ) : null );
			if ( function_exists( 'woosquare_loyalty_restore_points_after_failed_payment' ) ) {
				woosquare_loyalty_restore_points_after_failed_payment( $fail_order ? $fail_order : ( isset( $order_id ) ? $order_id : 0 ) );
			}

			if ( $fail_order ) {
				$fail_order->update_status( 'failed', $ex->getMessage() );
				$fail_order->save();
			}
			if ( isset( $parent_order ) && $parent_order ) {
				$parent_order->save();
			}
			return array(
				'result'   => 'failure',
				'messages' => $message,
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification
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
				$total = round( $total, 2 );
				$total = (int) round( $total * 100, 0 );
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
	 * Persist Square order id on the Woo order for loyalty/order-sync consumers.
	 *
	 * @param WC_Order $order           Woo order.
	 * @param string   $square_order_id Square order id.
	 * @return void
	 */
	private function persist_square_order_id( $order, $square_order_id ) {
		if ( function_exists( 'woosquare_loyalty_persist_square_order_id' ) ) {
			woosquare_loyalty_persist_square_order_id( $order, $square_order_id );
			return;
		}

		$square_order_id = sanitize_text_field( (string) $square_order_id );
		if ( ! $order || '' === $square_order_id ) {
			return;
		}

		$existing = sanitize_text_field( (string) $order->get_meta( 'square_order_id', true ) );
		if ( $existing === $square_order_id ) {
			return;
		}

		$order->update_meta_data( 'square_order_id', $square_order_id );
		$order_id = $order->get_id();
		if ( $order_id ) {
			set_transient( 'square_order_sync_add_on_id_' . absint( $order_id ), $square_order_id, 20 * MINUTE_IN_SECONDS );
		}
		set_transient( 'square_order_sync_add_on_id', $square_order_id, 20 * MINUTE_IN_SECONDS );
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