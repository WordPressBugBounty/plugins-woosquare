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
 * DSFW_Blocks
 */
class Woosquare_GIftCard_Blocks {



	/**
	 * Method __construct
	 * s
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'woocommerce_blocks_loaded', array( $this, 'init' ) );
	}

	/**
	 * Method init
	 *
	 * @return void
	 */
	public function init() {
		include_once WOO_SQUARE_PLUS_PLUGIN_PATH . '/admin/modules/square-payments/class-woosquare-giftcard-extend-store-endpoint.php';
		Woosquare_Giftcard_Extend_Store_Endpoint::init();
		add_action( 'woocommerce_blocks_checkout_block_registration', array( $this, 'woosquare_register_giftcard_block' ) );
	}


	/**
	 * Method dsfw_register_delviery_slot_block
	 *
	 * @param Integration_Interface $integration_registry aa.
	 *
	 * @return void
	 */
	public function woosquare_register_giftcard_block( $integration_registry ) {
		if (true ) { // phpcs:ignore
			include_once WOO_SQUARE_PLUS_PLUGIN_PATH . '/admin/modules/square-payments/class-woosquare-giftcard-block-integration.php';
			$integration_registry->register( Woosquare_Giftcard_Block_Integration::instance() );
		}
	}
}

( new Woosquare_GIftCard_Blocks() );
