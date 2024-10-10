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
 * Square payment logging class which saves important data to the log
 */
class WooSquare_Payment_Logger {

		/**
		 * The logger instance for logging purposes.
		 *
		 * @var Logger
		 */
	public static $logger;

	/**
	 * Log a message using WooCommerce's logger class.
	 *
	 * This method allows you to log messages using WooCommerce's built-in logger.
	 * It initializes the logger if it hasn't been already and logs the provided
	 * message with the specified logger context.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @param string $message The message to be logged.
	 */
	public static function log( $message ) {
		if ( empty( self::$logger ) ) {
			self::$logger = new WC_Logger();
		}

		self::$logger->add( 'woocommerce-gateway-square', $message );
	}
}
