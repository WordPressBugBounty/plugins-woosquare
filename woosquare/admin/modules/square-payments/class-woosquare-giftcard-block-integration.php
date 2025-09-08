<?php
/**
 * Woosquare_Plus v1.0 by wpexperts.io
 *
 * @package Woosquare_Plus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Woosquare_Giftcard_Block_Integration handles the integration of the WooSquare gift card blocks.
 *
 * This class implements the IntegrationInterface and is responsible for managing the initialization
 * and script handling for the WooSquare gift card block integration in both the frontend and editor contexts.
 * It follows the singleton pattern to ensure only one instance of the class is loaded.
 *
 * @implements IntegrationInterface
 */
class Woosquare_Giftcard_Block_Integration implements IntegrationInterface {



	/**
	 * Whether the intregration has been initialized.
	 *
	 * @var boolean
	 */
	protected $is_initialized;

	/**
	 * The single instance of the class.
	 *
	 * @var Woosquare_Giftcard_Block_Integration
	 */
	protected static $instance = null;

	/**
	 * Main DSFW_Block_Integration instance. Ensures only one instance of DSFW_Block_Integration is loaded or can be loaded.
	 *
	 * @static
	 * @return Woosquare_Giftcard_Block_Integration
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'woosquare-giftcard';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {

		$script_asset_path = dirname( WOO_SQUARE_PLUS_PLUGIN_PATH ) . '/build/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
		? include $script_asset_path
		: array(
			'dependencies' => array(),
			'version'      => '1.0.0',
		);

		wp_register_script(
			'woosquare-giftcard-blocks-script',
			dirname( WOO_SQUARE_PLUGIN_URL_PLUS ) . '/build/index.js',
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		$this->is_initialized = true;
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'woosquare-giftcard-blocks-script' );
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array( 'woosquare-giftcard-blocks-script' );
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		$woocommerce_square_gift_card_pay_enabled = get_option( 'woocommerce_square_gift_card_pay_enabled' . get_transient( 'is_sandbox' ) );

		return array(
			'enabled' => isset( $woocommerce_square_gift_card_pay_enabled ) && 'yes' === $woocommerce_square_gift_card_pay_enabled,
		);
	}
}
