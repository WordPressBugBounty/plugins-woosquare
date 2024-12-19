<?php
/**
 * Woosquare_Plus v1.0 by wpexperts.io
 *
 * @package Woosquare_Plus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-woosquare-payments-connect.php';

/**
 * This class represents the payment processing functionality for WooCommerce using Square.
 *
 * It provides methods and properties for handling payments and related operations.
 */
class WooSquare_Payments {

	/**
	 * The connection object for connecting to a database or external service.
	 *
	 * @var Connection
	 */
	protected $connect;

	/**
	 * A flag indicating whether logging is enabled or not.
	 *
	 * @var bool
	 */
	public $logging;

	/**
	 * Constructor for the Square Payment Gateway.
	 *
	 * @param WooSquare_Payments_Connect $connect An instance of the WooSquare_Payments_Connect class.
	 */
	public function __construct( WooSquare_Payments_Connect $connect ) {
		add_action( 'init', array( $this, 'init' ), 999 );
		$this->connect = $connect;

		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );

		add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'cancel_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_refunded', array( $this, 'cancel_payment' ) );

		$woocommerce_square_apple_pay_enabled = get_option( 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( isset( $woocommerce_square_apple_pay_enabled['enabled'] ) && 'yes' === $woocommerce_square_apple_pay_enabled['enabled'] ) {
			add_action( 'admin_init', array( $this, 'wooplus_apple_pay_domain_verification' ) );
		}

		if ( is_admin() ) {
			add_filter( 'woocommerce_order_actions', array( $this, 'add_capture_charge_order_action' ), 10, 2 );
			add_action( 'woocommerce_order_action_square_capture_charge', array( $this, 'maybe_capture_charge' ) );
			add_action( 'admin_post_add_foobar', array( $this, 'prefix_admin_square_payment_settings_save' ) );
			add_action( 'admin_post_nopriv_add_foobar', array( $this, 'prefix_admin_square_payment_settings_save' ) );
		}

		$gateway_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

		$this->logging = ! empty( $gateway_settings['logging'] ) ? true : false;
	}

	/**
	 * Init
	 */
	public function init() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		// live/production app id from Square account.

		$tokenn = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );

		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

		if ( ! empty( $tokenn ) && ! empty( get_transient( 'is_sandbox' ) ) ) {
			if ( ! defined( 'SQUARE_APPLICATION_ID' ) ) {
				define( 'SQUARE_APPLICATION_ID', WOOSQU_PLUS_APPID );
			}
			if ( ! defined( 'WC_SQUARE_ENABLE_STAGING' ) ) {
				define( 'WC_SQUARE_ENABLE_STAGING', true );
			}
		} elseif ( isset( $woocommerce_square_plus_settings['enable_sandbox'] ) && 'no' === $woocommerce_square_plus_settings['enable_sandbox'] ) {
			if ( ! defined( 'SQUARE_APPLICATION_ID' ) ) {
				define( 'SQUARE_APPLICATION_ID', WOOSQU_PLUS_APPID );
			}
			if ( ! defined( 'WC_SQUARE_ENABLE_STAGING' ) ) {
				define( 'WC_SQUARE_ENABLE_STAGING', false );
			}
		}

		// Includes.
		include_once __DIR__ . '/class-woosquare-plus-gateway.php';

		return true;
	}

	/**
	 * Register payment gateways for use in WooCommerce.
	 *
	 * @param array $methods An array of payment methods.
	 * @return array Updated array of payment methods with registered gateways.
	 */
	public function register_gateway( $methods ) {
		$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );
		$domain_name                     = ! empty( $_SERVER['HTTP_HOST'] ) ? wc_clean( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
		if ( true === $activate_modules_woosquare_plus['woosquare_payment']['module_activate'] ) {
			$methods[] = 'WooSquare_Plus_Gateway';
			$methods[] = 'WooSquareGooglePay_Gateway';
			$methods[] = 'WooSquareACHPayment_Gateway';
			$methods[] = 'WooSquareAfterPay_Gateway';
			$methods[] = 'WooSquareCashApp_Gateway';
			if ( ( get_option( 'woo_square_plus_apple_pay_domain_registered' . get_transient( 'is_sandbox' ) . '-' . $domain_name ) === 'yes' ) ) {
				$methods[] = 'WooSquareApplePay_Gateway';
			}
		}
		return $methods;
	}

	/**
	 * Verify a domain with Apple Pay using the Square API.
	 *
	 * This function checks and registers a domain for Apple Pay payments
	 * with the Square API. It sends a verification request to Square and
	 * updates WordPress options accordingly upon successful verification.
	 *
	 * @throws \Exception If unable to verify the domain or missing domain in $_SERVER['HTTP_HOST'].
	 *
	 * @return bool True on successful domain verification, false otherwise.
	 */
	public function wooplus_apple_pay_domain_verification() {

		$token = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		if(!empty($token)){
				$domain_name = ! empty( $_SERVER['HTTP_HOST'] ) ? wc_clean( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
			if ( empty( $domain_name ) ) {
				throw new \Exception( 'Unable to verify domain with Apple Pay - no domain found in $_SERVER[\'HTTP_HOST\'].' );
			}

			if ( ! $this->woo_square_check_apple_pay_verification_file() ) {
				update_option( 'woo_square_plus_apple_pay_domain_registered' . get_transient( 'is_sandbox' ) . '-' . $domain_name, 'no' );
				delete_option( 'woo_square_plus_apple_pay_domain_registered_url' . get_transient( 'is_sandbox' ) . '-' . $domain_name );
				return false;
			}

			$recently_registered = get_transient( 'woo_square_check_apple_pay_domain_registration' . get_transient( 'is_sandbox' ) . '-' . $domain_name );
			if ( ! $recently_registered ) {
				$url = 'https://connect.' . WC_SQUARE_STAGING_URL . '.com/v2/apple-pay/domains';

				$response = wp_remote_post(
					$url,
					array(
						'headers' => array(
							'Authorization' => 'Bearer ' . $token,
							'Content-Type'  => 'application/json',
						),
						'body'    => wp_json_encode(
							array(
								'domain_name' => $domain_name,
							)
						),
					)
				);

				if ( is_wp_error( $response ) ) {
					throw new \Exception( sprintf( 'Unable to verify domain %s - %s', esc_html( $domain_name ), esc_html( $response ) ) );
				}

				$parsed_response = json_decode( $response['body'], true );
				if ( 200 === $response['response']['code'] || ! empty( $parsed_response['status'] ?? null ) || 'VERIFIED' === ($parsed_response['status'] ?? null) ) {

					update_option( 'woo_square_plus_apple_pay_domain_registered' . get_transient( 'is_sandbox' ) . '-' . $domain_name, 'yes' );
					update_option( 'woo_square_plus_apple_pay_domain_registered_url' . get_transient( 'is_sandbox' ) . '-' . $domain_name, $domain_name );
					$this->log( 'Your domain has been verified with Apple Pay!' );
					set_transient( 'woo_square_check_apple_pay_domain_registration' . get_transient( 'is_sandbox' ) . '-' . $domain_name, true, HOUR_IN_SECONDS );
				}
			}
		}
		
	}

	/**
	 * Check and update the Apple Pay domain association verification file.
	 *
	 * This function checks if the Apple Pay domain association verification file exists in the server's document root.
	 * If it doesn't exist or is different from the plugin's copy, it updates the file.
	 *
	 * @return bool True if the file is successfully checked and updated, false otherwise.
	 */
	public function woo_square_check_apple_pay_verification_file() {
		if ( empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
			return false;
		}

		$path              = untrailingslashit( wc_clean( sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) ) );
		$dir               = '.well-known';
		$file              = 'apple-developer-merchantid-domain-association';
		$fullpath          = $path . '/' . $dir . '/' . $file;
		$plugin_path       = WOO_SQUARE_PLUS_PLUGIN_PATH . '/admin/modules/square-payments/verification';
		$get_content       = 'file_get_contents';
		$existing_contents = $get_content( $fullpath );
		$new_contents      = $get_content( $plugin_path . '/' . $file );

		if ( false !== $existing_contents && $new_contents === $existing_contents ) {
			return true;
		}
		if ( ! file_exists( $path . '/' . $dir ) ) {
			if ( ! wp_mkdir_p( $path . '/' . $dir ) ) {
				$this->log( 'Unable to create domain association folder to domain root.' );
				return false;
			}
		}

		if ( ! copy( $plugin_path . '/' . $file, $fullpath ) ) {
			$this->log( 'Unable to copy domain association file to domain root.' );
			return false;
		}

		$this->log( 'Apple Pay Domain association file updated.' );
		return true;
	}

	/**
	 * Add the "Capture Charge" action to order actions if conditions are met.
	 *
	 * @param array    $actions List of existing order actions.
	 * @param WC_Order $order The WooCommerce order object.
	 * @return array Modified list of order actions.
	 */
	public function add_capture_charge_order_action( $actions, $order ) {

		if ( in_array( ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->payment_method : $order->get_payment_method() ), array( 'square_plus' . get_transient( 'is_sandbox' ), 'square' ), true ) ) {
			return $actions;
		}

		if ( 'yes' === wc_get_order( $order->get_id() )->get_meta( '_square_charge_captured', true ) ) {
			return $actions;
		}

		$actions['square_capture_charge'] = esc_html__( 'Capture Charge', 'woosquare' );
		return $actions;
	}

	/**
	 * Form submit to save data of payment settings
	 */
	public function prefix_admin_square_payment_settings_save() {
			// Handle request then generate response using echo or leaving PHP and using HTML.

		if ( ! isset( $_POST['woosquare_setting'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woosquare_setting'] ) ), 'woosquare_setting_nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
			$woocommerce_square_enabled = isset( $_POST['woocommerce_square_enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_square_enabled'] ) ) : '';
			$arraytosave                = array(
				'enabled'            => ( ! empty( $woocommerce_square_enabled ) && '1' === trim( $woocommerce_square_enabled ) ? 'yes' : 'no' ),
				'title'              => ( ! empty( $_POST['woocommerce_square_title'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_square_title'] ) ) : '' ),
				'description'        => ( ! empty( $_POST['woocommerce_square_description'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_square_description'] ) ) : '' ),
				'capture'            => ( ! empty( $_POST['woocommerce_square_capture'] ) && '1' === $_POST['woocommerce_square_capture'] ? 'yes' : 'no' ),
				'create_customer'    => ( ! empty( $_POST['woocommerce_square_create_customer'] ) && '1' === $_POST['woocommerce_square_create_customer'] ? 'yes' : 'no' ),
				'google_pay' . get_transient( 'is_sandbox' ) . '_enabled' => ( ! empty( $_POST[ 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ? 'yes' : 'no' ),
				'ach_payment' . get_transient( 'is_sandbox' ) . '_enabled' => ( ! empty( $_POST[ 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_enabled' ] ? 'yes' : 'no' ),
				'after_pay' . get_transient( 'is_sandbox' ) . '_enabled' => ( ! empty( $_POST[ 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ? 'yes' : 'no' ),
				'cash_app_pay' . get_transient( 'is_sandbox' ) . '_enabled' => ( ! empty( $_POST[ 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ? 'yes' : 'no' ),
				'gift_card_enabled'  => ( ! empty( $_POST['woocommerce_square_gift_card_pay_enabled'] ) && '1' === $_POST['woocommerce_square_gift_card_pay_enabled'] ? 'yes' : 'no' ),
				'logging'            => ( ! empty( $_POST['woocommerce_square_logging'] ) && '1' === $_POST['woocommerce_square_logging'] ? 'yes' : 'no' ),
				'Send_customer_info' => ( ! empty( $_POST['Send_customer_info'] ) && '1' === $_POST['Send_customer_info'] ? 'yes' : 'no' ),
			);

			$arraytosave_serialize = ( $arraytosave );

			update_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings', $arraytosave_serialize );

			$woocommerce_square_google_pay_settings = get_option( 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_settings' );
			if ( ! empty( $_POST[ 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) {
				$woocommerce_square_google_pay_settings['enabled'] = 'yes';

			} elseif ( empty( $_POST[ 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) ) {
				$woocommerce_square_google_pay_settings['enabled'] = 'no';
			}
			update_option( 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_settings', $woocommerce_square_google_pay_settings );

			$woocommerce_square_ach_payment_settings = get_option( 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_settings' );
			if ( ! empty( $_POST[ 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_enabled' ] ) {
				$woocommerce_square_ach_payment_settings['enabled'] = 'yes';

			} elseif ( empty( $_POST[ 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_enabled' ] ) ) {
				$woocommerce_square_ach_payment_settings['enabled'] = 'no';
			}
			update_option( 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_settings', $woocommerce_square_ach_payment_settings );

			$woocommerce_square_after_pay_settings = get_option( 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_settings' );
			if ( ! empty( $_POST[ 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) {
				$woocommerce_square_after_pay_settings['enabled'] = 'yes';

			} elseif ( empty( $_POST[ 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) ) {
				$woocommerce_square_after_pay_settings['enabled'] = 'no';
			}
			update_option( 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_settings', $woocommerce_square_after_pay_settings );

			$woocommerce_square_cash_app_pay_settings = get_option( 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_settings' );

			if ( ! empty( $_POST[ 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) {
				$woocommerce_square_cash_app_pay_settings['enabled'] = 'yes';

			} elseif ( empty( $_POST[ 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) ) {
				$woocommerce_square_cash_app_pay_settings['enabled'] = 'no';
			}
			update_option( 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_settings', $woocommerce_square_cash_app_pay_settings );

			if ( ! empty( $_POST[ 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) && '1' === $_POST[ 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) {
				$woocommerce_square_apple_pay_settings['enabled'] = 'yes';

			} elseif ( empty( $_POST[ 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_enabled' ] ) ) {
				$woocommerce_square_apple_pay_settings['enabled'] = 'no';
			}
			update_option( 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_settings ', $woocommerce_square_apple_pay_settings );

			$msg = wp_json_encode(
				array(
					'status' => true,

					'msg'    => 'Settings updated successfully!',
				)
			);
			set_transient( 'woosquare_plus_notification', $msg, 12 * HOUR_IN_SECONDS );
			wp_safe_redirect( get_admin_url() . 'admin.php?page=square-payment-gateway' );
	}

	/**
	 * Maybe capture a payment for an order.
	 *
	 * This function is responsible for capturing a payment for a given order.
	 *
	 * @param int|WC_Order $order Order ID or order object to capture payment for.
	 *
	 * @return bool True if the payment capture was attempted.
	 */
	public function maybe_capture_charge( $order ) {
		if ( ! is_object( $order ) ) {
			$order = wc_get_order( $order );
		}

		$this->capture_payment( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id() );

		return true;
	}

	/**
	 * Capture payment when the order is changed from on-hold to complete or processing.
	 *
	 * @param int $order_id The ID of the order to capture payment for.
	 *
	 * @throws Exception If there is an error during payment capture.
	 */
	public function capture_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( in_array( ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->payment_method : $order->get_payment_method() ), array( 'square_plus' . get_transient( 'is_sandbox' ), 'square', 'square_google_pay' . get_transient( 'is_sandbox' ), 'square_gift_card_pay' ), true ) ) {
			try {
				$this->log( "Info: Begin capture for order {$order_id}" );

				$trans_id = get_post_meta( $order_id, 'woosquare_transaction_id', true );

				$token = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );

				$this->connect->set_access_token( $token );

				$transaction_status = $this->connect->get_transaction_status( $trans_id );

				if ( 'AUTHORIZED' === $transaction_status ) {

					$url     = 'https://connect.' . WC_SQUARE_STAGING_URL . ".com/v2/payments/$trans_id/complete";
					$headers = array(
						'Accept'        => 'application/json',
						'Authorization' => 'Bearer ' . $token,
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
									'body'        => '',
								)
							)
						)
					);
					$print_r = 'print_r';
					if ( is_wp_error( $result ) ) {
						$order->add_order_note( __( 'Unable to capture charge!', 'woosquare' ) . ' ' . $print_r( $result->errors, true ) );
						throw new Exception( $print_r( $result->errors, true ) );
					} elseif ( ! empty( $result->errors ) ) {
						$order->add_order_note( __( 'Unable to void charge!', 'woosquare' ) . ' ' . $print_r( $result->errors, true ) );
						throw new Exception( $print_r( $result->errors, true ) );
					} else {
						if ( ! empty( get_transient( 'is_sandbox' ) ) ) {
							$msg = ' via Sandbox ';
						} else {
							$msg = '';
						}
						// translators: %1$s is the message, %2$s is the payment ID.
						$order->add_order_note( sprintf( __( 'Square charge complete %1$s (Charge ID: %2$s)', 'woosquare' ), $msg, $trans_id ) );
						$squhposupdate_meta = wc_get_order( $order->id );
						$squhposupdate_meta->update_meta_data( '_square_charge_captured', 'yes' );
						$squhposupdate_meta->save();
						$this->log( "Info: Capture successful for {$order_id}" );
					}
				}
			} catch ( Exception $e ) {
				// translators: Error message placeholder in a log entry. Placeholder: Error details.
				$this->log( sprintf( __( 'Error unable to capture charge: %s', 'woosquare' ), $e->getMessage() ) );
			}
		}
	}

	/**
	 * Cancel payment authorization for an order.
	 *
	 * @param int $order_id The ID of the order for which to cancel payment authorization.
	 * @throws Exception If there is an error during payment capture.
	 */
	public function cancel_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( in_array( ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->payment_method : $order->get_payment_method() ), array( 'square_plus' . get_transient( 'is_sandbox' ), 'square' ), true ) ) {

			try {
				$this->log( "Info: Cancel payment for order {$order_id}" );
				$trans_id = $order->get_meta( 'woosquare_transaction_id', true );
				$captured = $order->get_meta( '_square_charge_captured', true );

				$token = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );

				$this->connect->set_access_token( $token );

				$transaction_status = $this->connect->get_transaction_status( $trans_id );

				if ( 'AUTHORIZED' === $transaction_status ) {
					$url     = 'https://connect.' . WC_SQUARE_STAGING_URL . ".com/v2/payments/$trans_id/cancel";
					$headers = array(
						'Accept'        => 'application/json',
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
						'Cache-Control' => 'no-cache',
					);

					$result             = json_decode(
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
					$transaction_status = $this->connect->get_transaction_status( $trans_id );
					if ( is_wp_error( $result ) ) {
						$order->add_order_note( __( 'Unable to void charge!', 'woosquare' ) . ' ' . $result->get_error_message() );
						throw new Exception( $result->get_error_message() );
					} elseif ( ! empty( $result->errors ) ) {
						$order->add_order_note( __( 'Unable to capture charge!', 'woosquare' ) . ' ' . wp_json_encode( $result->errors ) );
						throw new Exception( 'Error: ' . wp_json_encode( $result->errors ) );
					} elseif ( 'VOIDED' === $transaction_status ) {
						// translators: Error message placeholder in a log entry. Placeholder: Error details.
						$order->add_order_note( sprintf( __( 'Square charge voided! (Charge ID: %s)', 'woosquare' ), $trans_id ) );
						delete_post_meta( $order_id, '_square_charge_captured' );
						delete_post_meta( $order_id, 'woosquare_transaction_id' );
					}
				}
			} catch ( Exception $e ) {
				// translators: Error message placeholder in a log entry. Placeholder: Error details.
				$this->log( sprintf( __( 'Unable to void charge!: %s', 'woosquare' ), $e->getMessage() ) );
			}
		}
	}

	/**
	 * Logs a message if logging is enabled.
	 *
	 * @param string $message The message to log.
	 */
	public function log( $message ) {
		if ( $this->logging ) {
			WooSquare_Payment_Logger::log( $message );
		}
	}
}

new WooSquare_Payments( new WooSquare_Payments_Connect() );
