<?php
/**
 * Plugin Name: WC Shop Sync - Connect Square with WooCommerce
 * Requires Plugins: woocommerce
 * Plugin URI: https://wcshopsync.com/
 * Requires at least: 6.7	
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * PHP tested up to: 8.3
 * Description: WC Shop Sync purpose is to migrate & synchronize data (sales customers-invoices-products inventory) between Square system point of sale & WooCommerce plug-in.
 * Version: 4.6.0
 * Author: Wpexpertsio
 * Author URI: https://wpexperts.io/
 * License: GPLv2 or later
 * Text Domain: woosquare
 *
 * @package Woosquare_Plus
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */

define( 'WOO_SQUARE_TABLE_DELETED_DATA', 'woo_square_integration_deleted_data' );
define( 'WOO_SQUARE_TABLE_SYNC_LOGS', 'woo_square_integration_logs' );
define( 'WOO_SQUARE_PLUGIN_URL_PLUS', plugin_dir_url( __FILE__ ) );
define( 'WOO_SQUARE_PLUS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOO_SQUARE_ITEM_SYNC_LOGS_TABLE', 'woo_square_item_sync_logs' );



$plugin_file             = __FILE__;
$plugin_data             = get_file_data(
	$plugin_file,
	array(
		'Name'        => 'Plugin Name',
		'Version'     => 'Version',
		'Description' => 'Description',
		'Author'      => 'Author',
		'Plugin URI'  => 'Plugin URI',
	)
);
$woosqu_plus_plugin_name = $plugin_data['Name'];
if ( ! defined( 'WOOSQU_PLUS_PLUGIN_NAME' ) ) {
	define( 'WOOSQU_PLUS_PLUGIN_NAME', $woosqu_plus_plugin_name );
}
if ( ! defined( 'WOOSQUARE_VERSION' ) ) {
	define( 'WOOSQUARE_VERSION', $plugin_data['Version'] );
}
define( 'PLUGIN_NAME_VERSION_WOOSQUARE_PLUS', $plugin_data['Version'] );
if ( ! defined( 'WOO_SQUARE_PLUS_PLUGIN_BASENAME' ) ) {
	define( 'WOO_SQUARE_PLUS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}



if ( ! defined( 'WOO_SQUARE_PLUGIN_URL' ) ) {
	define( 'WOO_SQUARE_PLUGIN_URL', plugin_dir_url( __FILE__ ) . 'admin/modules/product-sync/' );
}

// inc freemius.
require_once plugin_dir_path( __FILE__ ) . 'includes/square-freemius.php';


// connection auth credentials.

if ( ! defined( 'WOOSQU_PLUS_CONNECTURL' ) ) {
	define( 'WOOSQU_PLUS_CONNECTURL', 'https://connect.apiexperts.io' );
}

	$vendor_check = '';
	$vendor_check = isset( $vendor_check ) ? apply_filters( 'square_vendor_check', $vendor_check ) : $vendor_check;
	// needfiltercond if vender site.
if ( $vendor_check ) {
	if ( ! defined( 'SQUARE_VENDOR_COMISSION' ) ) {
		define( 'SQUARE_VENDOR_COMISSION', VENDOR_SQUARE_VENDOR_COMISSION );
	}
	if ( ! defined( 'SQUARE_VENDOR_COMISSION_INC_ITEMS' ) ) {
		define( 'SQUARE_VENDOR_COMISSION_INC_ITEMS', VENDOR_SQUARE_VENDOR_COMISSION_INC_ITEMS );
	}
	if ( ! defined( 'WOOSQU_PLUS_APPNAME' ) ) {
		define( 'WOOSQU_PLUS_APPNAME', VENDOR_WOOSQU_PLUS_APPNAME );
	}
	if ( ! defined( 'WOOSQU_PLUS_APPID' ) ) {
		define( 'WOOSQU_PLUS_APPID', VENDOR_WOOSQU_PLUS_APPID );
	}
	if ( ! defined( 'SQUARE_SECRET_ID' ) ) {
		define( 'SQUARE_SECRET_ID', VENDOR_SQUARE_SECRET_ID );
	}
	if ( ! defined( 'SQUARE_HOSTURL' ) ) {
		define( 'SQUARE_HOSTURL', VENDOR_SQUARE_HOSTURL );
	}
} else {
	if ( ! defined( 'WOOSQU_PLUS_APPNAME' ) ) {
		define( 'WOOSQU_PLUS_APPNAME', 'API Experts' );
	}
	if ( get_transient( 'is_sandbox' ) ) {
		if ( ! defined( 'WOOSQU_PLUS_APPID' ) ) {
			define( 'WOOSQU_PLUS_APPID', 'sandbox-sq0idb-5riA7nOR3jTV9gsuuHPQwA' );
		}
	} elseif ( ! defined( 'WOOSQU_PLUS_APPID' ) ) {
		define( 'WOOSQU_PLUS_APPID', 'sq0idp-OkzqrnM_vuWKYJUvDnwT-g' );
	}

	$scopes = apply_filters( 'woosqu_custom_scopes_filter', 'MERCHANT_PROFILE_READ,ITEMS_READ,ITEMS_WRITE,PAYMENTS_READ,PAYMENTS_WRITE,INVENTORY_WRITE,ORDERS_WRITE,CUSTOMERS_READ,CUSTOMERS_WRITE,INVENTORY_READ,LOYALTY_READ,LOYALTY_WRITE,ORDERS_READ' );
	if ( ! defined( 'WOOSQU_PLUS_SCOPES' ) ) {
		define( 'WOOSQU_PLUS_SCOPES', $scopes );
	}
}

if ( ! defined( 'WOO_SQUARE_MAX_SYNC_TIME' ) ) {
	// max sync running time
	// numofpro*60.
	if ( get_option( '_transient_timeout_transient_get_products' ) > time() ) {
		$total_productcount = get_transient( 'transient_get_products' );
	} else {
		$args               = array(
			'post_type'      => 'product',
			'posts_per_page' => -1,
		);
		$products           = get_posts( $args );
		$total_productcount = count( $products );
		set_transient( 'transient_get_products', $total_productcount, 720 );

	}
	if ( $total_productcount > 1 ) {
		define( 'WOO_SQUARE_MAX_SYNC_TIME', $total_productcount * 60 );
	} else {
		define( 'WOO_SQUARE_MAX_SYNC_TIME', 10 * 60 );
	}
}

if ( get_transient( 'is_sandbox' ) ) {
	if ( ! defined( 'WC_SQUARE_ENABLE_STAGING' ) ) {
		define( 'WC_SQUARE_ENABLE_STAGING', true );
		define( 'WC_SQUARE_STAGING_URL', 'squareupsandbox' );
	}
} elseif ( ! defined( 'WC_SQUARE_ENABLE_STAGING' ) ) {
		define( 'WC_SQUARE_ENABLE_STAGING', false );
		define( 'WC_SQUARE_STAGING_URL', 'squareup' );
}


/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-woosquare-plus-activator.php
 */
function activate_woosquare_plus() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woosquare-plus-activator.php';
	Woosquare_Plus_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-woosquare-plus-deactivator.php
 */
function deactivate_woosquare_plus() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woosquare-plus-deactivator.php';
	Woosquare_Plus_Deactivator::deactivate();
}

add_action( 'init', 'activate_woosquare_plus', 0 );
add_action( 'init', 'deactivate_woosquare_plus' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-woosquare-plus.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_woosquare_plus() {

	$plugin = new Woosquare_Plus();
	$plugin->run();
}

add_action( 'init', 'run_woosquare_plus', 0 );

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );
if ( isset( $activate_modules_woosquare_plus['woosquare_payment']['module_activate'] ) && true === $activate_modules_woosquare_plus['woosquare_payment']['module_activate'] ) {
	add_action( 'woocommerce_blocks_loaded', 'woosquare_premium_woocommerce_blocks_support' );
}
/**
 * Adds support for WooCommerce Blocks payments in the premium version of WC Shop Sync.
 *
 * Checks for the existence of WooCommerce Blocks and registers a custom payment method if the required class exists.
 *
 * @since 1.0.0
 */
function woosquare_premium_woocommerce_blocks_support() {

	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once WOO_SQUARE_PLUS_PLUGIN_PATH . 'admin/modules/square-payments/class-woosquare-payment-block.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new Woosquare_Payment_Block() );
			}
		);
	}
}
