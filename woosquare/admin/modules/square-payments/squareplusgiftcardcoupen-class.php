<?php
/**
 * Woosquare_Plus v1.0 by wpexperts.io
 *
 * @package Woosquare_Plus
 */

if ( empty( session_id() ) && ! headers_sent() ) {
	session_start();
}
$square_gift_card_id = 'square_gift_card_coupen_pay';
global $token;
$token = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
global $amount;

add_action( 'wp_enqueue_scripts', 'payment_scripts_giftcardpay' );
add_action( 'woocommerce_update_options_payment_gateways_' . $square_gift_card_id, 'process_admin_options' );

$woocommerce_square_gift_card_pay_enabled = get_option( 'woocommerce_square_gift_card_pay_enabled' . get_transient( 'is_sandbox' ) );
if ( 'yes' === $woocommerce_square_gift_card_pay_enabled ) {
	add_action( 'woocommerce_review_order_before_payment', 'woosquare_display_form', 3 );
	add_action( 'cfw_checkout_cart_summary', 'woosquare_display_form', 3 );
}


add_action( 'wp_ajax_sqaure_redeem_coupen_code', 'woosqaure_redeem_coupen_code', 5 );
add_action( 'wp_ajax_nopriv_sqaure_redeem_coupen_code', 'woosqaure_redeem_coupen_code', 5 );

add_action( 'wp_ajax_sqaure_redeem_coupen_code_cancel_payment', 'sqaure_redeem_coupen_code_cancel_payment' );
add_action( 'wp_ajax_nopriv_sqaure_redeem_coupen_code_cancel_payment', 'sqaure_redeem_coupen_code_cancel_payment' );

add_action( 'woocommerce_order_status_on-hold_to_cancelled', 'woosquare_gift_cancel_payment' );
add_action( 'woocommerce_order_status_processing_to_cancelled', 'woosquare_gift_cancel_payment' );
add_action( 'woocommerce_order_status_on-hold_to_refunded', 'woosquare_gift_refund_payment' );
add_action( 'woocommerce_order_status_processing_to_refunded', 'woosquare_gift_refund_payment' );
add_action( 'woocommerce_checkout_order_processed', 'woosquare_checkout_order_processed_square_capture', 10, 1 );
add_action( 'woocommerce_store_api_checkout_order_processed', 'woosquare_checkout_order_processed_square_capture', 10, 1 );

/**
 * Cancel a payment made with a Square gift card.
 *
 * This function handles the cancellation of a payment made using a Square gift card.
 * It retrieves the payment ID from the POST request or stored transients/options,
 * then makes an API call to Square to cancel the payment.
 * The result of the API call is stored in an option and relevant transients are deleted.
 *
 * @return void
 */
function sqaure_redeem_coupen_code_cancel_payment() {
	if ( ! isset( $_POST['square_pay_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['square_pay_nonce'] ) ), 'square-pay-nonce' ) ) {
		wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
	}
	if ( isset( $_POST['action'] ) && 'sqaure_redeem_coupen_code_cancel_payment' === $_POST['action'] ) {

		$sq_gift_card_data = get_transient( 'sq_gift_card_data' );
		if ( isset( $_POST['paymentID'] ) ) {
			$trans_id = sanitize_text_field( wp_unslash( $_POST['paymentID'] ) );
		} elseif ( $sq_gift_card_data['payment_id'] ) {
			$trans_id = $sq_gift_card_data['payment_id'];
		} elseif ( isset( $_POST['orderID'] ) ) {
			$trans_id = get_option( 'gift_card_create_order' . sanitize_text_field( wp_unslash( $_POST['orderID'] ) ) );
		}

		if ( ! empty( $trans_id ) ) {
			$token   = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
			$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . ".com/v2/payments/$trans_id/cancel";
			$headers = array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token,
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
							'body'        => '',
						)
					)
				)
			);

			if ( is_wp_error( $result ) ) {
				update_option( 'cancel_payment_gf_card' . $trans_id, $result->get_error_message() );
				$result = wp_json_encode( $result );
				delete_transient( 'sq_gift_card_data' );
				delete_transient( 'woosquare_giftcard_fees' );
				unset( $_SESSION['sq_square_gift_amount'] );
				echo esc_html( $result );

			} elseif ( ! empty( $result->errors ) ) {
				update_option( 'cancel_payment_gf_card' . $trans_id, $result->errors );
				$result = wp_json_encode( $result );
				delete_transient( 'sq_gift_card_data' );
				delete_transient( 'woosquare_giftcard_fees' );
				unset( $_SESSION['sq_square_gift_amount'] );
				echo esc_html( $result );
			} elseif ( 'VOIDED' === $result->payment->card_details->status
				||
				'FAILED' === $result->payment->card_details->status
			) {
				update_option( 'square_gift_card_charge' . $trans_id, $result );

				delete_transient( 'sq_gift_card_data' );
				delete_transient( 'woosquare_giftcard_fees' );
				delete_transient( 'squ_giftfee' );
				unset( $_SESSION['sq_square_gift_amount'] );
				wp_send_json_success( $result );

			}
		}
	}
	die();
}

/**
 * Redeem a coupon code with Square.
 *
 * This function handles the redemption of a coupon code using Square's API.
 * It processes the payment using the provided nonce, handles sandbox mode,
 * and updates various transients and options with the transaction data.
 *
 * @return void
 */
function woosqaure_redeem_coupen_code() {
	if ( ! isset( $_POST['square_pay_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['square_pay_nonce'] ) ), 'square-pay-nonce' ) ) {
		wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
	}
	if ( ! empty( $_POST['nonce'] ) ) {

		$token = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );

		global $woocommerce;
		$currency_code                    = isset( $_POST['currency_code'] ) ? sanitize_text_field( wp_unslash( $_POST['currency_code'] ) ) : '';
		$order_id                         = isset( $_POST['orderID'] ) ? sanitize_text_field( wp_unslash( $_POST['orderID'] ) ) : uniqid();
		$amount_to_pay                    = (int) format_amount( $woocommerce->cart->total, $currency_code );
		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( isset( $_POST['nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
		}

		if ( get_transient( 'is_sandbox' ) ) {
			$nonce = 'cnon:gift-card-nonce-ok';
		}
		$endpoint    = 'squareup';
		$location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		if ( get_transient( 'is_sandbox' ) ) {
			$msg      = ' via Sandbox ';
			$endpoint = 'squareupsandbox';
		}
		$idempotency_key = uniqid();

		$data = array(
			'idempotency_key'              => $idempotency_key,
			'amount_money'                 => array(
				'amount'   => $amount_to_pay,
				'currency' => $currency_code,
			),
			'reference_id'                 => (string) $order_id,
			'delay_duration'               => 'PT10M',
			'autocomplete'                 => false,
			'accept_partial_authorization' => true,
			'source_id'                    => $nonce,
			'location_id'                  => $location_id,
			'note'                         => 'Square Gift Card',
		);

		$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/payments';
		$headers = array(
			'Accept'         => 'application/json',
			'Authorization'  => 'Bearer ' . $token,
			'Square-Version' => '2021-11-17',
			'Content-Type'   => 'application/json',
			'Cache-Control'  => 'no-cache',
		);

		update_option( 'gift_card_request_' . gmdate( 'm/d/Y h:i:s a', time() ), $data );
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

		update_option( 'gift_card_response_' . gmdate( 'm/d/Y h:i:s a', time() ), $result );
		$balance = 0;

		$paidamount = ( $result->payment->amount_money->amount / 100 );

		$balance = ( $amount_to_pay / 100 ) - $paidamount;

		$result->payment->balance = $balance;

		if ( ! isset( $result->errors ) ) {
			$data = array(
				'orderID'     => $order_id,
				'payment_id'  => $result->payment->id,
				'balance'     => $balance,
				'amountToPay' => $amount_to_pay,
				'paidamount'  => $paidamount,
			);
			set_transient( 'sq_gift_card_data', $data, 600 );
		}

		set_transient( 'squ_giftfee_session', $result, 600 );
		set_transient( 'squ_add_gift_box', $result->payment->amount_money->amount, 600 );
		update_option( 'gift_card_create_order' . $order_id, $result->payment->id );

		echo wp_json_encode( $result );
	}

	die();
}

/**
 * Adds a Square gift box to the WooCommerce checkout page.
 *
 * This function adds a JavaScript script to the footer of the checkout page.
 * The script triggers an update of the checkout when the gift box is clicked.
 *
 * @return void
 */
function woocommerce_add_square_gift_box() {
	if ( is_checkout() ) {
		?>
		<script type="text/javascript">
			jQuery(document).ready(function ($) {
				$('#add_gift_box').click(function () {
					jQuery('body').trigger('update_checkout');
				});
			});
		</script>
		<?php
	}
}
add_action( 'wp_footer', 'woocommerce_add_square_gift_box' );

/**
 * Checks if WooSquare gift card fees are applied.
 *
 * This function parses the checkout data to check if WooSquare gift card fees are applied
 * and sets a transient to store the checkout fields for a limited time.
 *
 * @param string $post_data The serialized post data from the checkout form.
 * @return void
 */
function check_if_woosquare_giftcard_fees_applied( $post_data ) {
	parse_str( $post_data, $checkout_fields );
	set_transient( 'woosquare_giftcard_fees', $checkout_fields, 1000 );
}


/**
 * Adds a fee to the WooCommerce cart for Square gift card.
 *
 * This function checks for the presence of gift card data in transients and applies
 * the appropriate fee to the cart. It also handles the cleanup of transients if no gift
 * card data is found.
 *
 * @return void
 */
function woo_add_square_giftcard_cart_fee() {

	if ( ! empty( get_transient( 'woosquare_giftcard_fees' ) ) ) {
		$post_data = get_transient( 'woosquare_giftcard_fees' );
		if ( ! empty( $post_data['add_gift_box'] ) ) {
			$extracost = get_transient( 'squ_add_gift_box' );

			WC()->cart->add_fee( 'Square Gift Card', -$extracost / 100 );

			set_transient( 'sq_payment_id_box', $post_data['sq_payment_id_box'], 600 );
			set_transient( 'add_gift_box', $post_data['add_gift_box'], 600 );
			set_transient( 'squ_giftfee', $post_data, 600 );

		} else {
			delete_transient( 'squ_giftfee' );
			delete_transient( 'woosquare_giftcard_fees' );
		}
	} else {
		delete_transient( 'squ_giftfee' );
		delete_transient( 'woosquare_giftcard_fees' );
	}

	$is_block_checkout = WC_Blocks_Utils::has_block_in_page( wc_get_page_id( 'checkout' ), 'woocommerce/checkout' );

	if ( $is_block_checkout ) {
		if ( empty( get_transient( 'woosquare_giftcard_fees' ) ) || ! $_POST || ( is_admin() && ! is_ajax() ) || empty( get_transient( 'sq_gift_card_data' ) ) ) { // phpcs:ignore
			// return; this conflict with lagecy checkout and code for block checkout.
		}
	}

	if ( ! $_POST || ( is_admin() && ! is_ajax() ) || empty( get_transient( 'sq_gift_card_data' ) ) ) { // phpcs:ignore
		return;
	}

	if ( isset( $_POST['post_data'] ) ) { // phpcs:ignore
		$post_data_raw = sanitize_text_field( wp_unslash( $_POST['post_data'] ) ); // phpcs:ignore
		parse_str( $post_data_raw, $post_data );
	} else {
		$post_data = array_map( 'sanitize_text_field', wp_unslash( $_POST ) ); // phpcs:ignore
	}

	if ( isset( $post_data['add_gift_box'] ) ) {
		$extracost = get_transient( 'squ_add_gift_box' );

		WC()->cart->add_fee( 'Gift Card', -$extracost / 100 );

		set_transient( 'add_gift_box', $post_data['add_gift_box'], 600 );
		set_transient( 'sq_payment_id_box', $post_data['sq_payment_id_box'], 600 );
		set_transient( 'squ_giftfee', $post_data, 600 );

	} else {
		delete_transient( 'squ_giftfee' );
	}
}

add_action( 'woocommerce_cart_calculate_fees', 'woo_add_square_giftcard_cart_fee', 99 );

/**
 * Check if this gateway is enabled
 */
function is_available() {

	$is_available = true;

	if ( 'yes' === $enabled ) {
		if ( ! wc_checkout_is_https() ) {
			$is_available = false;
		}

		if ( empty( $token ) ) {
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
				'JP' !== WC()->countries->get_base_country() &&
				'AU' !== WC()->countries->get_base_country() ) || (
				'USD' !== get_woocommerce_currency() &&
				'CAD' !== get_woocommerce_currency() &&
				'JPY' !== get_woocommerce_currency() &&
				'EUR' !== get_woocommerce_currency() &&
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

	return apply_filters( 'woocommerce_square_payment_giftcardpay_gateway_is_available', $is_available );
}

/**
 * Displays the WooSquare gift card form on the checkout page.
 *
 * This function outputs the HTML for the WooSquare gift card form, allowing users
 * to enter and apply their gift card code during the checkout process.
 *
 * @return void
 */
function woosquare_display_form() {

	?>

	<div class="" id="sq_amount_result"></div>
	<div class="add_woosquare_gift_card_form">

		<h4><?php esc_html_e( 'Have a gift card?', 'woosquare-plus' ); ?></h4>

		<div id="wc_woosquare_gc_cart_redeem_form">
			<div class="woowoosquare_gift_card_coupen_code_notices"></div>
			<label for="sq-gift-card-coupen"><?php esc_html_e( 'Enter your gift card code&hellip;', 'woosquare' ); ?>
				<div id="sq-gift-card-coupen"></div>

				<button style="margin-top: 45px;" type="button" name="woosquare_get_cart_redeem_send"
						id="woosquare_get_cart_redeem_send"><?php esc_html_e( 'Apply', 'woosquare-plus' ); ?></button>

		</div>
	</div>
	<input type="hidden" name="square_giftcard_nonce" value="<?php echo esc_attr( wp_create_nonce( 'square-giftcard-nonce' ) ); ?>" />
	<br>
	<br>

	<?php
}

/**
 * Returns the country code for a given currency code.
 *
 * This function maps specific currency codes to their corresponding country codes.
 *
 * @param string $currency_code The currency code (e.g., 'USD', 'EUR', 'CAD', 'GBP').
 * @return string The corresponding country code (e.g., 'US', 'IE', 'CA', 'GB').
 */
function get_country_codes( $currency_code ) {

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
 * Enqueues the necessary scripts and styles for the WooSquare gift card payment.
 *
 * This function checks if the current page is the checkout page and, if so, enqueues the
 * necessary JavaScript and CSS files for handling WooSquare gift card payments.
 * It also localizes script parameters to be used in the JavaScript files.
 *
 * @return bool Returns true if scripts are enqueued, otherwise returns nothing.
 */
function payment_scripts_giftcardpay() {
	if ( ! is_checkout() ) {
		return;
	}
	$location                         = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
	$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

	global $woocommerce;
	$woocommerce_square_settings = get_option( 'woocommerce_square_settings' );
	// need to add condition square payment enable so disable below script.
	if ( get_transient( 'is_sandbox' ) ) {
		$endpoint    = 'squareupsandbox';
		$environment = 'development';
	} else {
		$endpoint    = 'squareup';
		$environment = 'production';
	}

	$woocommerce_square_settings = get_option( 'woocommerce_square_settings' );
	$currency_cod                = get_option( 'woocommerce_currency' );
	$country_code                = get_country_codes( $currency_cod );

	if ( empty( get_transient( 'squ_giftfee_session' ) ) ) {
		unset( $_SESSION['sq_square_gift_amount'] );
		delete_transient( 'sq_gift_card_data' );
	}
	$giftcardd_transient = get_transient( 'sq_gift_card_data' );
	if ( ! empty( get_transient( 'squ_giftfee' ) ) ) {
		$squ_giftfee = true;
	} else {
		$squ_giftfee = false;

	}
	if ( ! empty( $giftcardd_transient ) ) {
		$session_payment_id = $giftcardd_transient['payment_id'];
	} else {
		$session_payment_id = '';
	}

	if ( ! empty( $giftcardd_transient['paidamount'] ) ) {
		$session_payment = $giftcardd_transient['paidamount'];
	} else {
		$session_payment = '';
	}

	if ( get_transient( 'is_sandbox' ) ) {
		$endpoint = 'sandbox.web';

	} else {
		$endpoint = 'web';
	}
	$woocommerce_square_gift_card_pay_enabled = get_option( 'woocommerce_square_gift_card_pay_enabled' . get_transient( 'is_sandbox' ) );
	if ( 'yes' === $woocommerce_square_gift_card_pay_enabled ) {

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
		wp_register_script( 'woosquare-gift-coupen-card-pay', WOOSQUARE_PLUGIN_URL_PAYMENT . '/js/SquarePaymentsGiftCardCoupenPay.js', array( 'jquery', 'square' ), WOOSQUARE_VERSION, true );
		wp_localize_script(
			'woosquare-gift-coupen-card-pay',
			'squaregiftcardcoupenpay_params',
			array(
				'application_id'    => WOOSQU_PLUS_APPID,
				'lid'               => apply_filters( 'modify_square_location_id', $location ),
				'order_total'       => $woocommerce->cart->total,
				'environment'       => $environment,
				'currency_code'     => $currency_cod,
				'country_code'      => $country_code,
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'unique_id'         => uniqid(),
				'get_amount_store'  => $session_payment,
				'squ_giftfee'       => $squ_giftfee,
				'square_payment_id' => $session_payment_id,
				'currency_symbol'   => get_woocommerce_currency_symbol(),
				'sandbox'           => get_transient( 'is_sandbox' ),
				'square_pay_nonce'  => wp_create_nonce( 'square-pay-nonce' ),
			)
		);

		wp_enqueue_script( 'woosquare-gift-coupen-card-pay' );
		wp_enqueue_style( 'woocommerce-square-giftcardoupenpay-styles', WOOSQUARE_PLUGIN_URL_PAYMENT . '/css/SquareFrontendStyles_giftcardcoupen_pay.css', array(), WOOSQUARE_VERSION );
	}
	return true;
}


/**
 * Process amount to be passed to Square.
 *
 * @param float  $total    The total amount to be formatted.
 * @param string $currency The currency code (optional).
 *
 * @return float The formatted amount.
 */
function format_amount( $total, $currency = '' ) {

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
			$total = round( $total, 2 );
			$total = (int) round( $total * 100, 0 );
			break;
	}

	return $total;
}

/**
 * Captures the payment for an order processed with Square gift card.
 *
 * This function captures the payment for an order using the Square gift card.
 * It retrieves payment data from transients, updates order meta data, and makes
 * an API call to Square to complete the payment. It also handles errors and adds
 * order notes accordingly.
 *
 * @param int|WC_Order $order The order ID or order object.
 * @return void
 * @throws Exception If the payment capture fails.
 */
function woosquare_checkout_order_processed_square_capture( $order ) {

	if ( is_numeric( $order ) ) {
		$order = wc_get_order( $order );
	}
	$payment_id   = get_transient( 'sq_payment_id_box' );
	$add_gift_box = get_transient( 'add_gift_box' );
	if ( ! empty( $payment_id ) && ! empty( $add_gift_box ) ) {
		$order->update_meta_data( 'square_gift_card_coupen_payment_id', $payment_id );
		$order->update_meta_data( 'square_gift_card_coupen_payment_amount', $add_gift_box );

		$token = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );

		$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . ".com/v2/payments/$payment_id/complete";
		$headers = array(
			'Accept'         => 'application/json',
			'Authorization'  => 'Bearer ' . $token,
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
						'body'        => '',
					)
				)
			)
		);

		if ( is_wp_error( $result ) ) {
			$order->add_order_note( __( 'Unable to capture charge!', 'woosquare' ) . ' ' . $result->get_error_message() );

			throw new Exception( esc_html( $result->get_error_message() ) );
		} elseif ( ! empty( $result->errors ) ) {
			$print_r = 'print_r';
			$order->add_order_note( __( 'Unable to capture charge!', 'woosquare' ) . ' ' . $print_r( $result->errors, true ) );

			throw new Exception( esc_html( $result->errors ) );
		} else {

			$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

			if ( get_transient( 'is_sandbox' ) ) {
				$msg = ' via Sandbox ';
			} else {
				$msg = '';
			}

			unset( $_SESSION['sq_square_gift_amount'] );
			delete_transient( 'sq_gift_card_data' );

			delete_transient( 'squ_giftfee' );
			delete_transient( 'squ_giftfee_session' );
			delete_transient( 'sq_payment_id_box' );
			delete_transient( 'add_gift_box' );
			delete_transient( 'woosquare_giftcard_fees' );
			// Translators: %1$s is the message content, %2$s is the payment ID.
			$order->add_order_note( sprintf( __( 'Square Gift charge complete %1$s (Charge ID: %2$s)', 'woosquare' ), $msg, $payment_id ) );

			$order->update_meta_data( 'square_gift_card_charge_captured', 'yes' );

		}
		$order->save();

	}
}

/**
 * Cancels a Square gift payment for a given WooCommerce order.
 *
 * This function cancels the Square gift payment associated with the specified order ID.
 * It retrieves the transaction ID and captured status from the order metadata, sends a request
 * to Square to cancel the payment, and updates the order notes and metadata accordingly.
 *
 * @param int $order_id The ID of the WooCommerce order.
 *
 * @throws Exception If there is an error during the cancellation process.
 */
function woosquare_gift_cancel_payment( $order_id ) {

	$order    = wc_get_order( $order_id );
	$trans_id = $order->get_meta( 'square_gift_card_coupen_payment_id', true );
	$captured = $order->get_meta( 'square_gift_card_charge_captured', true );

	if ( ! empty( $trans_id ) && 'yes' !== $captured ) {
		$token   = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . ".com/v2/payments/$trans_id/cancel";
		$headers = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $token,
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
						'body'        => '',
					)
				)
			)
		);

		if ( is_wp_error( $result ) ) {
			$order->add_order_note( __( 'Unable to void charge!', 'woosquare' ) . ' ' . $result->get_error_message() );
			throw new Exception( esc_html( $result->get_error_message() ) );
		} elseif ( ! empty( $result->errors ) ) {
			$print_r = 'print_r';
			$order->add_order_note( __( 'Unable to void charge!', 'woosquare' ) . ' ' . $print_r( $result->errors, true ) );
			$error_message = $print_r( $errors, true );
			throw new Exception( esc_html( $error_message ) );
		} elseif ( 'VOIDED' === $result->payment->card_details->status ) {
			// Translators: %s is the transaction ID of the Square charge.
			$order->add_order_note( sprintf( __( 'Square charge voided! (Charge ID: %s)', 'woosquare' ), $trans_id ) );
			$order->delete_meta_data( 'square_gift_card_charge_captured' );
			$order->delete_meta_data( 'square_gift_card_coupen_payment_id' );
		}
	}
	$order->save();
}

/**
 * Refunds a Square gift payment for a given WooCommerce order.
 *
 * This function processes a refund for the Square gift payment associated with the specified order ID.
 * It retrieves the transaction ID, captured status, and amount from the order metadata, sends a refund request
 * to Square, and updates the order notes accordingly.
 *
 * @param int $order_id The ID of the WooCommerce order.
 *
 * @return bool True if the refund was processed successfully, false otherwise.
 *
 * @throws Exception If there is an error during the refund process.
 */
function woosquare_gift_refund_payment( $order_id ) {

	$order      = wc_get_order( $order_id );
	$trans_id   = $order->get_meta( 'square_gift_card_coupen_payment_id', true );
	$captured   = $order->get_meta( 'square_gift_card_charge_captured', true );
	$get_amount = ( $order->get_meta( 'square_gift_card_coupen_payment_amount', true ) );
	$token      = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );

	if ( ! empty( $order ) && ! empty( $trans_id ) && ! empty( $get_amount ) ) {

		$body                    = array();
		$currency                = $order->get_order_currency();
		$body['idempotency_key'] = uniqid();

		if ( ! is_null( $get_amount ) ) {
			$body['amount_money'] = array(
				'amount'   => (int) format_amount( $get_amount, $currency ),
				'currency' => $currency,
			);
			$body['payment_id']   = $trans_id;
		}

		$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/refunds';

		$headers = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Cache-Control' => 'no-cache',
		);
		update_option( 'gift_card_request_refundbody_' . $order_id . '_' . gmdate( 'm/d/Y h:i:s a', time() ), $body );
		$result = json_decode(
			wp_remote_retrieve_body(
				wp_remote_post(
					$url,
					array(
						'method'      => 'POST',
						'headers'     => $headers,
						'httpversion' => '1.0',
						'sslverify'   => false,
						'body'        => wp_json_encode( apply_filters( 'modify_square_refund_fields', $body ) ),
					)
				)
			)
		);

		update_option( 'gift_card_request_refund_' . $order_id . '_' . gmdate( 'm/d/Y h:i:s a', time() ), $result );
		if ( is_wp_error( $result ) ) {
			throw new Exception( esc_html( $result->get_error_message() ) );

		} elseif ( ! empty( $result->errors ) ) {
			$print_r       = 'print_r';
			$error_message = $print_r( $result->errors, true );
			throw new Exception( 'Error: ' . esc_html( $error_message ) );

		} elseif ( 'APPROVED' === $result->refund->status || 'PENDING' === $result->refund->status ) {
				// Translators: %1$s is the refunded amount, %2$s is the refund ID, %3$s is the reason for the refund.
				$refund_message = sprintf( __( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'wpexpert-square' ), wc_price( $result->refund->amount_money->amount / 100 ), $result->refund->id, $reason );

				$order->add_order_note( $refund_message );
				return true;
		}
	}
}
