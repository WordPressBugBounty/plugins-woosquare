<?php
/**
 * Freemius Initialization
 *
 * @package Woosquare_Plus
 */

if ( ! function_exists( 'woosquare_fs' ) ) {
	/**
	 * Create a helper function for easy SDK access.
	 */
	function woosquare_fs() {
		global $woosquare_fs;

		if ( ! isset( $woosquare_fs ) ) {
			// Include Freemius SDK.
			require_once __DIR__ . '/freemius/start.php';

			$woosquare_fs = fs_dynamic_init(
				array(
					'id'                  => '1378',
					'slug'                => 'woosquare',
					'type'                => 'plugin',
					'public_key'          => 'pk_823382e5b579047e3a8bb6fa6790d',
					'is_premium'          => false,
					// If your plugin is a serviceware, set this option to false.
					'has_premium_version' => true,
					'has_addons'          => false,
					'has_paid_plans'      => true,
					'menu'                => array(
						'slug'    => 'square-settings',
						'contact' => false,
						'support' => false,
					),

				)
			);
		}

		return $woosquare_fs;
	}

	// Init Freemius.
	woosquare_fs();
	// Signal that SDK was initiated.
	do_action( 'woosquare_fs_loaded' );
}
