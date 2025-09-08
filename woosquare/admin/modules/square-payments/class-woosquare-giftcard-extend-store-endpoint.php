<?php
/**
 * Woosquare_Giftcard_Extend_Store_Endpoint class
 *
 * Extends the WooCommerce Store API to include gift card functionality in the checkout process.
 *
 * @package Woosquare
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;

/**
 * Class Woosquare_Giftcard_Extend_Store_Endpoint
 *
 * Extends the WooCommerce Store API to include gift card functionality in the checkout process.
 *
 * @package Woosquare
 */
class Woosquare_Giftcard_Extend_Store_Endpoint {



	/**
	 * Plugin Identifier, unique to each plugin.
	 *
	 * @var string
	 */
	const IDENTIFIER = 'deliveryestimate';

	/**
	 * Initialize.
	 */
	public static function init() {
	}

	/**
	 * Register myCred WooCommerce data into cart/checkout endpoint.
	 *
	 * @return array $item_data Registered data or empty array if condition is not satisfied.
	 */
	public static function extend_checkout_block_data() {
		$selected_shipping_method = DSFW_HELPERS::extract_shipping_method();
		$data                     = '';
		if ( empty( $selected_shipping_method ) || ! $selected_shipping_method ) {
			$text = __( 'Select shipping method first', 'dsfw' );
		} else {
			$data = DSFW_HELPERS::get_delivery_estimate( $selected_shipping_method );
			if ( is_array( $data ) ) {
				$from = isset( $data['from'] ) ? $data['from'] : '';
				$to   = isset( $data['to'] ) ? $data['to'] : '';
				$text = sprintf( "%s <span class='hypen'></span> %s", esc_html( $from ), esc_html( $to ) );
			} else {
				$text = esc_html( $data );
			}
		}

		$item_data = array(
			'estimate' => $text,
		);

		return $item_data;
	}

	/**
	 * Register myCred WooCommerce schema into cart/checkout endpoint.
	 *
	 * @return array Registered schema.
	 */
	public static function extend_checkout_block_schema() {
		return array(
			'estimate' => array(
				'description' => __( 'Calculate Delivery Estimate.', 'mycred-woocommerce' ),
				'type'        => array( 'string', 'null' ),
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		);
	}
}
