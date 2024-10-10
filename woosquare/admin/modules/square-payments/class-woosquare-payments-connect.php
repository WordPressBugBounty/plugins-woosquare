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
 * Represents a connection to the Square payment system for WooCommerce.
 *
 * This class extends the WooSquare_Client class and provides additional functionality
 * for connecting to and interacting with Square payments.
 */
class WooSquare_Payments_Connect extends WooSquare_Client {
	const LOCATIONS_CACHE_KEY = 'WooSquare_payments_locations';

	/**
	 * The API version.
	 *
	 * @var string
	 */
	protected $api_version = 'v2';

	/**
	 * Checks to see if token is valid.
	 *
	 * There is no formal way to check this other than to
	 * retrieve the merchant account details and if it comes back
	 * with a code 200, we assume it is valid.
	 *
	 * @access public
	 * @since 1.0.0
	 * @version 1.0.0
	 * @return bool
	 */
	public function is_valid_token() {

		$merchant = $this->request( 'Retrieving Merchant', 'locations' );

		if ( is_wp_error( $merchant ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Charges the card nonce.
	 *
	 * @param array $data        An array of data containing payment details.
	 *
	 * @return object An object containing the result of the payment processing.
	 */
	public function charge_card_nonce( $data ) {
		$path = '/payments';

		return $this->request( 'Charge Card Nonce', $path, 'POST', $data );
	}

	/**
	 * Retrieves a transaction from Square.
	 *
	 * @param string $transaction_id The ID of the transaction to retrieve.
	 *
	 * @return object An object containing the transaction details.
	 */
	public function get_transaction( $transaction_id ) {
		$path = '/payments/' . $transaction_id;

		return $this->request( 'Get Transaction', $path );
	}

	/**
	 * Gets the transaction status.
	 *
	 * @param string $transaction_id The ID of the transaction to retrieve the status for.
	 *
	 * @return string|null The status of the transaction, or null if there was an error.
	 */
	public function get_transaction_status( $transaction_id ) {
		$result = $this->get_transaction( $transaction_id );

		if ( is_wp_error( $result ) ) {
			return null;
		}

		return $result->payment->card_details->status;
	}

	/**
	 * Voids a previously authorized transaction (delay/capture).
	 *
	 * @param string $location_id    The location ID where the transaction was processed.
	 * @param string $transaction_id The ID of the transaction to void.
	 *
	 * @return object The response object from the API request.
	 */
	public function void_transaction( $location_id, $transaction_id ) {
		$path = '/payments/' . $transaction_id . '/cancel';

		return $this->request( 'Void Authorized Transaction', $path, 'POST' );
	}

	/**
	 * Refunds a transaction.
	 *
	 * @param string $transaction_id The ID of the transaction to refund.
	 * @param array  $data           Additional data for the refund request.
	 *
	 * @return object The response object from the API request.
	 */
	public function refund_transaction( $transaction_id, $data ) {
		$path = '/refunds';

		return $this->request( 'Refund Transaction', $path, 'POST', $data );
	}

	/**
	 * Create a customer.
	 *
	 * @param array $data An array containing customer data for creating a new customer.
	 *
	 * @return object The response object from the API request.
	 */
	public function create_customer( $data ) {
		$path = '/customers';

		return $this->request( 'Create Customer', $path, 'POST', $data );
	}

	/**
	 * Get customer information.
	 *
	 * @param string $customer_id The unique ID of the customer you want to retrieve.
	 *
	 * @return object The response object from the API request containing customer details.
	 */
	public function get_customer( $customer_id = null ) {
		if ( null === $customer_id ) {
			return false;
		}

		$path = '/customers/' . $customer_id;

		return $this->request( 'Get Customer', $path, 'GET' );
	}

	/**
	 * Get the complete request URL for the API based on the provided path.
	 *
	 * @param string $path The path to be appended to the base API URL.
	 *
	 * @return string The complete request URL.
	 */
	protected function get_request_url( $path ) {
		$api_url_base = trailingslashit( $this->get_api_url() );

		$request_path = ltrim( $path, '/' );
		$request_url  = untrailingslashit( $api_url_base . $request_path );

		return $request_url;
	}
}
