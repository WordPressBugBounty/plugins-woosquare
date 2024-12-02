<?php
/**
 * Woosquare_Plus v1.0 by wpexperts.io
 *
 * @package Woosquare_Plus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Represents a square shape.
 *
 * This class provides methods and properties for working with square shapes.
 */
class Square {

	// Class properties.
	/**
	 * The access token for Square API authentication.
	 *
	 * @var string
	 */
	protected $access_token;

	/**
	 * The Square application ID.
	 *
	 * @var string
	 */
	protected $app_id;

	/**
	 * The base URL for Square API.
	 *
	 * @var string
	 */
	protected $square_url;

	/**
	 * The base URL for Square API version 2.
	 *
	 * @var string
	 */
	protected $square_v2_url;

	/**
	 * The location ID for the Square location.
	 *
	 * @var string
	 */
	protected $location_id;

	/**
	 * The main Square URL (if needed).
	 *
	 * @var string
	 */
	protected $main_square_url;

	/**
	 * Constructor for initializing a Square API client.
	 *
	 * @param object $access_token The access token used for Square API authentication.
	 * @param string $app_id The Square application ID.
	 * @param string $location_id The location ID (default is 'me').
	 */
	public function __construct( $access_token, $app_id, $location_id = 'me' ) {
		$this->access_token = $access_token;
		$this->app_id       = $app_id;
		if ( empty( $location_id ) ) {
			$location_id = 'me'; }
		$this->location_id     = $location_id;
		$this->square_url      = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v1/' . $this->location_id;
		$this->square_v2_url   = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/';
		$this->main_square_url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v1/me';
	}


	/**
	 * Get the access token used for Square API requests.
	 *
	 * @return string The current access token.
	 */
	public function get_access_token() {
		return $this->access_token;
	}

	/**
	 * Set the access token to be used for Square API requests.
	 *
	 * @param string $access_token The new access token.
	 */
	public function set_access_token( $access_token ) {
		$this->access_token = $access_token;
	}

	/**
	 * Get the Square application ID.
	 *
	 * @return string The current application ID.
	 */
	public function getapp_id() {
		return $this->app_id;
	}

	/**
	 * Set the Square application ID.
	 *
	 * @param string $app_id The new application ID.
	 */
	public function setapp_id( $app_id ) {
		$this->app_id = $app_id;
	}

	/**
	 * Get the Square URL used for API requests.
	 *
	 * @return string The current Square URL.
	 */
	public function get_square_url() {
		return $this->square_url;
	}

	/**
	 * Get the Square version 2 URL used for API requests.
	 *
	 * @return string The current Square version 2 URL.
	 */
	public function get_square_v2_url() {
		return $this->square_v2_url;
	}

	/**
	 * Set the location ID and update the Square URL for API requests.
	 *
	 * @param string $location_id The new location ID.
	 */
	public function set_location_id( $location_id ) {
		$this->location_id = $location_id;
		$this->square_url  = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v1/' . $location_id;
	}

	/**
	 * Get the current location ID used for Square API requests.
	 *
	 * @return string The current location ID.
	 */
	public function get_location_id() {
		return $this->location_id;
	}

	/**
	 * Authorizes the Square app and sets up the WooCommerce Square settings.
	 *
	 * @return bool True if the authorization was successful, false otherwise.
	 */
	public function authorize() {
		$access_token = explode( '-', $this->access_token );

		delete_option( 'woo_square_account_type' );
		delete_option( 'woo_square_account_currency_code' );
		delete_option( 'wc_square_version', '1.0.11', 'yes' );
		delete_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		delete_option( 'woo_square_app_id' );
		delete_option( 'woo_square_locations' . get_transient( 'is_sandbox' ) );
		delete_option( 'woo_square_business_name' );

		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( ! empty( $woocommerce_square_plus_settings['enable_sandbox'] ) && 'yes' !== $woocommerce_square_plus_settings['enable_sandbox'] ) {

			// live/production app id from Square account.
			if ( ! defined( 'SQUARE_APPLICATION_ID' ) ) {
				define( 'SQUARE_APPLICATION_ID', $this->app_id );
			}
			if ( ! defined( 'WC_SQUARE_ENABLE_STAGING' ) ) {
				define( 'WC_SQUARE_ENABLE_STAGING', false );
			}
		} else {
			// live/production app id from Square account.
			if ( ! defined( 'SQUARE_APPLICATION_ID' ) ) {
				define( 'SQUARE_APPLICATION_ID', $this->app_id );
			}
			if ( ! defined( 'WC_SQUARE_ENABLE_STAGING' ) ) {
				define( 'WC_SQUARE_ENABLE_STAGING', true );
			}
			update_option( 'woo_square_account_type', 'BUSINESS' );
			update_option( 'woo_square_account_currency_code', get_option( 'woocommerce_currency' ) );
		}

		$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/locations';
		$headers = array(
			'Authorization' => 'Bearer ' . $this->access_token, // Use verbose mode in cURL to determine the format you want for this header.
			'cache-control' => 'no-cache',
			'postman-token' => 'f39c2840-20f3-c3ba-554c-a1474cc80f12',
		);
		$method  = 'GET';

		$response = array();
		$args     = array( '' );
		$response = $this->wp_remote_woosquare( $url, $args, $method, $headers, $response );
		if ( 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {
			$response = json_decode( $response['body'], true );
			$response = isset( $response['locations'][0] ) ? $response['locations'][0] : null;
		}
		if ( isset( $response['id'] ) ) {
			update_option( 'wc_square_version', '1.0.11', 'yes' );
			update_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ), $this->access_token );
			update_option( 'woo_square_app_id', WOOSQU_PLUS_APPID );
			update_option( 'woo_square_account_type', isset( $response['type'] ) ? $response['type'] : null );
			update_option( 'woo_square_account_currency_code', isset( $response['currency'] ) ? $response['currency'] : null );

			$result = $this->get_all_locations();
			if ( ! empty( $result['locations'] ) && is_array( $result['locations'] ) ) {

				foreach ( $result['locations'] as $key => $value ) {
					if ( ! empty( $value['capabilities'] )
						&& 'ACTIVE' === $value['status']
						&& 'sandbox' === $access_token[0]
					) {
						$accurate_result['locations'][] = $result['locations'][ $key ];
					} elseif ( 'sandbox' !== $access_token[0] ) {
						$accurate_result['locations'][] = $result['locations'][ $key ];
					}
				}
			}
			$results = $accurate_result['locations'];
			$caps    = null;
			if ( ! empty( $results ) ) {
				foreach ( $results as $result ) {
					$locations = $result;
					if ( ! empty( $locations['capabilities'] ) ) {
						$caps = ' | ' . implode( ',', $locations['capabilities'] ) . ' ENABLED';
					}
					$location_id = ( $locations['id'] );
					$str[]       = array(
						$location_id => $locations['name'] . ' ' . str_replace('_', ' ', $caps ?? ''),
					);
				}
				update_option( 'woo_square_locations' . get_transient( 'is_sandbox' ), $str );
				update_option( 'woo_square_business_name', $locations['name'] );

			}

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Sends an HTTP request to the Square API and handles the response.
	 *
	 * This function can be used to make GET requests, process paginated responses, and handle batch token-based responses.
	 *
	 * @param string $url          The URL to send the request to.
	 * @param array  $args         An array of request parameters.
	 * @param string $method       The HTTP request method (e.g., 'GET', 'POST').
	 * @param array  $headers      An array of HTTP headers.
	 * @param array  $respons_body An array to store response bodies.
	 *
	 * @return mixed|array|false   The response object or false if an error occurs.
	 */
	public function wp_remote_woosquare( $url, $args, $method, $headers, $respons_body ) {

		$request = array(
			'headers' => $headers,
			'method'  => $method,
		);
		if ( 'GET' === $method && ! empty( $args ) && is_array( $args ) ) {
			$url = add_query_arg( $args, $url );
		} elseif ( ! empty( $args ) ) {
				$request['body'] = wp_json_encode( $args );
		}
		$response = wp_remote_request( $url, $request );

		$decoded_response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! empty( json_decode( wp_remote_retrieve_body( $response ) )->cursor ) ) {

			$respons_body[] = wp_json_encode( json_decode( wp_remote_retrieve_body( $response ) )->objects );

		} elseif ( ! empty( json_decode( wp_remote_retrieve_body( $response ) )->objects ) ) {

			$respons_body[] = wp_json_encode( json_decode( wp_remote_retrieve_body( $response ) )->objects );

		} elseif ( ! empty( wp_remote_retrieve_body( $response ) ) ) {

			$respons_body[] = wp_remote_retrieve_body( $response );

		}

		if ( 'GET' === $method ) {
			$postheaders               = '';
			$wp_remote_retrieve_header = wp_remote_retrieve_headers( $response );
			foreach ( $wp_remote_retrieve_header as $w_header ) {
				$postheaders .= esc_html( $w_header );
			}

			if ( ! empty( json_decode( wp_remote_retrieve_body( $response ) )->cursor ) ) {

				$args = array(
					'cursor' => json_decode( wp_remote_retrieve_body( $response ) )->cursor,
				);

				if ( ! empty( $args ) ) {
					$response = $this->wp_remote_woosquare( $url, $args, $method, $headers, $respons_body );

				}
			} elseif ( false !== strpos( $postheaders, 'batch_token' ) ) {
				$batch_token = explode( 'batch_token', $postheaders );
				$batch_token = explode( '>', $batch_token[1] );
				$batch_token = str_replace( '=', '', $batch_token[0] );
				$args        = array(
					'batch_token' => $batch_token,
				);

				if ( ! empty( $batch_token ) ) {
					$response = $this->wp_remote_woosquare( $url, $args, $method, $headers, $respons_body );
				}
			} else {
					$merge = array();

				foreach ( $respons_body as $formerge ) {
					if ( ! empty( $merge ) ) {
						$merge = array_merge( json_decode( $formerge ), $merge );
					} else {
						$merge = json_decode( ( $formerge ) );
					}
				}

				if ( ! is_wp_error( $response ) ) {
						$response['body'] = wp_json_encode( $merge );

						return $response;
				} else {
					update_option( 'wp_remote_woosquare_get_error_message_' . gmdate( 'Y-m-d H:i:s' ), $response->get_error_message() );
					return false;
				}
			}
		}
		if ( ! is_wp_error( $response ) ) {
				return $response;
		} else {
			update_option( 'wp_remote_woosquare_get_error_message_' . gmdate( 'Y-m-d H:i:s' ), $response->get_error_message() );
			return false;
		}
	}

	/**
	 * Gets the currency code for the given Square location ID.
	 */
	public function get_currency_code() {

		$url      = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/locations/' . $this->location_id;
		$method   = 'GET';
		$headers  = array(
			'Authorization' => 'Bearer ' . $this->access_token,
			'Content-Type'  => 'application/json',
		);
		$response = array();
		$args     = array( '' );
		$response = $this->wp_remote_woosquare( $url, $args, $method, $headers, $response );
		$response = json_decode( $response['body'], true );
		if ( isset( $response['location']['id'] ) ) {
			update_option( 'woo_square_account_currency_code', $response['location']['currency'] );
		}
	}

	/**
	 * Gets all of the Square locations associated with the current access token.
	 *
	 * @return array An array of Square location objects.
	 */
	public function get_all_locations() {

		$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/locations';
		$method  = 'GET';
		$headers = array(
			'Authorization' => 'Bearer ' . $this->access_token, // Use verbose mode in cURL to determine the format you want for this header.
			'cache-control' => 'no-cache',
			'postman-token' => 'f39c2840-20f3-c3ba-554c-a1474cc80f12',
		);

		$response = array();
		$args     = array( '' );
		$response = $this->wp_remote_woosquare( $url, $args, $method, $headers, $response );
		$response = json_decode( $response['body'], true );

		return $response;
	}

	/**
	 * Sets up a webhook for the given type of Square notification.
	 *
	 * @param string $type The type of Square notification to listen for.
	 * @param string $access_token The Square access token.
	 * @param string $woocommerce_square_location_id The WooCommerce Square location ID.
	 *
	 * @return bool True if the webhook was set up successfully, false otherwise.
	 */
	public function setup_webhook( $type, $access_token, $woocommerce_square_location_id ) {
		// setup notifications.

		$data_json = wp_json_encode( array( $type ) );
		$url       = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v1/' . $woocommerce_square_location_id . '/webhooks';
		$method    = 'PUT';
		$headers   = array(
			'Authorization'  => 'Bearer ' . $access_token, // Use verbose mode in cURL to determine the format you want for this header.
			'Content-Length' => strlen( $data_json ),
			'Content-Type'   => 'application/json',
		);

		$response        = array();
		$response        = $this->wp_remote_woosquare( $url, $data_json, $method, $headers, $response );
		$object_response = json_decode( $response['body'], true );
		if ( 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {
			update_option( 'Woosquare_webhook_response', wp_json_encode( $object_response ) . ' : ' . get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ) );
		} else {
			update_option( 'Woosquare_webhook_response_error', wp_json_encode( $object_response ) . ' : ' . get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ) );
		}

		return true;
	}

	/**
	 * Completes a WooCommerce order and updates the corresponding inventory in Square.
	 *
	 * @param int $order_id The ID of the WooCommerce order to complete.
	 *
	 * @return void
	 */
	public function complete_order( $order_id ) {

		$order = new WC_Order( $order_id );
		$items = $order->get_items();

		if ( $order->get_created_via() === 'Square' ) {
			return;
		}
		$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		$square_synchronizer    = new WooToSquareSynchronizer( $this );

		foreach ( $items as $item ) {
			if ( $item['variation_id'] ) {
				if ( get_post_meta( $item['variation_id'], '_manage_stock', true ) === 'yes' ) {
					$product_variation_id    = get_post_meta( $item['variation_id'], 'variation_square_id', true );
					$variation['id']         = $product_variation_id;
					$variation['updated_at'] = gmdate( 'Y-m-d' ) . 'T' . gmdate( 'H:i:s' ) . '.' . gmdate( 'v' ) . 'Z';

					$current_stock = get_post_meta( $item['variation_id'], '_stock', true );
					$total_stock   = $current_stock + $item['qty'];
					$_stock        = $total_stock - $current_stock;
					$square_synchronizer->update_inventory( $variation, $_stock, 'SALE', $woo_square_location_id );
				}
			} elseif ( get_post_meta( $item['product_id'], '_manage_stock', true ) === 'yes' ) {
					$product_variation_id = get_post_meta( $item['product_id'], 'variation_square_id', true );

					$variation['id']         = $product_variation_id;
					$variation['updated_at'] = gmdate( 'Y-m-d' ) . 'T' . gmdate( 'H:i:s' ) . '.' . gmdate( 'v' ) . 'Z';

					$current_stock = get_post_meta( $item['product_id'], '_stock', true );
					$total_stock   = $current_stock + $item['qty'];
					$_stock        = $total_stock - $current_stock;
					$square_synchronizer->update_inventory( $variation, $_stock, 'SALE', $woo_square_location_id );
			}
		}
	}

	/**
	 * Refunds a WooCommerce order using the Square API.
	 *
	 * @param int $order_id The ID of the WooCommerce order to refund.
	 *
	 * @return mixed The result of the refund operation.
	 */
	public function refund( $order_id ) {

		$order                  = new WC_Order( $order_id );
		$items                  = $order->get_items();
		$square_synchronizer    = new WooToSquareSynchronizer( $this );
		$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		foreach ( $items as $item ) {
			if ( $item['variation_id'] ) {
				if ( get_post_meta( $item['variation_id'], '_manage_stock', true ) === 'yes' ) {
					$product_variation_id = get_post_meta( $item['variation_id'], 'variation_square_id', true );

					$variation['id']         = $product_variation_id;
					$variation['updated_at'] = gmdate( 'Y-m-d' ) . 'T' . gmdate( 'H:i:s' ) . '.' . gmdate( 'v' ) . 'Z';

					$current_stock = get_post_meta( $item['variation_id'], '_stock', true );
					$total_stock   = $current_stock + $item['qty'];

					$square_synchronizer->update_inventory( $variation, 1 * $item['qty'], 'RECEIVE_STOCK', $woo_square_location_id );
					$product = wc_get_product( $item['variation_id'] );
					wc_update_product_stock( $product, $total_stock );
				}
			} elseif ( get_post_meta( $item['product_id'], '_manage_stock', true ) === 'yes' ) {
					$product_variation_id    = get_post_meta( $item['product_id'], 'variation_square_id', true );
					$variation['id']         = $product_variation_id;
					$variation['updated_at'] = gmdate( 'Y-m-d' ) . 'T' . gmdate( 'H:i:s' ) . '.' . gmdate( 'v' ) . 'Z';
					$square_synchronizer->update_inventory( $variation, 1 * $item['qty'], 'RECEIVE_STOCK', $woo_square_location_id );

					$current_stock = get_post_meta( $item['product_id'], '_stock', true );
					$total_stock   = $current_stock + $item['qty'];

					$product = wc_get_product( $item['product_id'] );
					wc_update_product_stock( $product, $total_stock );
			}
		}
	}

	/**
	 * Process amount to be passed to Square.
	 *
	 * @param  float  $total     The amount to be processed.
	 * @param  string $direc    Direction indicator ('wotosq' or 'sqtowo').
	 * @param  string $currency (Optional) Currency code. If not provided, WooCommerce currency is used.
	 *
	 * @return float            The processed amount.
	 */
	public function format_amount( $total, $direc, $currency = '' ) {
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
				if ( 'wotosq' === $direc ) {
					$total = round( $total, 2 ) * 100; // In cents.
				} elseif ( 'sqtowo' === $direc ) {
					$total = round( $total, 2 ) / 100; // In cents.
				}

				break;
		}

		return $total;
	}
}
