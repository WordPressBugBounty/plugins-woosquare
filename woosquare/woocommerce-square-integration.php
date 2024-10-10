<?php
/**
 * Plugin Name: WC Shop Sync - Connect Square with WooCommerce
 * Requires Plugins: woocommerce
 * Plugin URI: https://wpexperts.io/products/woosquare/
 * Description: WC Shop Sync purpose is to migrate & synchronize data (sales customers-invoices-products inventory) between Square system point of sale & WooCommerce plug-in.
 * Version: 4.4.9
 * Author: Wpexpertsio
 * Author URI: https://wpexperts.io/
 * License: GPLv2 or later
 * Text Domain: woosquare
 *
 * @package Woosquare_Plus
 */

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) && ( ! in_array( 'mycred/mycred.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) || version_compare( PHP_VERSION, '5.5.0', '<' ) ) {
	add_action( 'admin_notices', 'report_error_pro', 0 );
} else {
	require_once plugin_dir_path( __FILE__ ) . 'includes/square-freemius.php';
	add_action( 'plugins_loaded', 'run_woosquare_plus', 0 );
}
if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
wp_using_ext_object_cache( false );
$plugin_data = get_plugin_data( __FILE__ );

	$woosqu_plus_plugin_name = $plugin_data['Name'];
if ( ! defined( 'WOOSQU_PLUS_PLUGIN_NAME' ) ) {
	define( 'WOOSQU_PLUS_PLUGIN_NAME', $woosqu_plus_plugin_name );
}

	define( 'WOOSQUARE_VERSION', $plugin_data['Version'] );
/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'PLUGIN_NAME_VERSION_WOOSQUARE_PLUS', '4.4.8' );
define( 'WOO_SQUARE_TABLE_DELETED_DATA', 'woo_square_integration_deleted_data' );
define( 'WOO_SQUARE_TABLE_SYNC_LOGS', 'woo_square_integration_logs' );
define( 'WOO_SQUARE_PLUGIN_URL_PLUS', plugin_dir_url( __FILE__ ) );
define( 'WOO_SQUARE_PLUS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOO_SQUARE_ITEM_SYNC_LOGS_TABLE', 'woo_square_item_sync_logs' );

// connection auth credentials.
if ( ! defined( 'WOOSQU_PLUS_CONNECTURL' ) ) {
	define( 'WOOSQU_PLUS_CONNECTURL', 'https://connect.apiexperts.io' );
}

$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

if ( ! defined( 'WOO_SQUARE_MAX_SYNC_TIME' ) ) {
	// max sync running time.
	// numofpro*60.
	if ( get_option( '_transient_timeout_transient_get_products' ) > time() ) {
		$total_productcount = get_transient( 'transient_get_products' );
	} else {
		$args               = array(
			'post_type'      => 'product',
			'posts_per_page' => - 1,
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

add_action( 'plugins_loaded', 'activate_woosquare_plus' );
add_action( 'plugins_loaded', 'deactivate_woosquare_plus' );

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
function report_error_pro() {
	$class = 'notice notice-error';
	if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true )
	&& ( ! in_array( 'mycred/mycred.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) ) {
		$message = __( 'To use "WC Shop Sync WooCommerce Square Integration" WooCommerce Or MYCRED must be activated or installed!', 'woosquare' );
		printf(
			'<br><div class="%1$s"><p>%2$s</p></div><script>setTimeout(function () {
		   window.location.href = "plugins.php"; //will redirect to your blog page (an ex: blog.html)
		}, 2500);</script>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}
	if ( version_compare( PHP_VERSION, '5.5.0', '<' ) ) {
		/* translators: %s: Current PHP version */
		$message = sprintf( __( 'To use "WC Shop Sync WooCommerce Square Integration" PHP version must be 5.5.0+, Current version is: %s. Contact your hosting provider to upgrade your server PHP version.', 'woosquare' ), PHP_VERSION );
		printf(
			'<br><div class="%1$s"><p>%2$s</p></div><script>setTimeout(function () {
       window.location.href = "plugins.php"; //will redirect to your blog page (an ex: blog.html)
    }, 2500);</script>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}
	deactivate_plugins( 'woosquare/woocommerce-square-integration.php' );
	wp_die(
		'',
		'Plugin Activation Error',
		array(
			'response'  => 200,
			'back_link' => true,
		)
	);
}

/**
 * Initializes and runs the Woosquare Plus plugin.
 */
function run_woosquare_plus() {
	$plugin = new Woosquare_Plus();
	$plugin->run();
}

/**
 * Initializes the Woosquare plugin by migrating data from the free version.
 */
function woosquare_init() {
	// these key exist in WC Shop Sync free so need to migrate in WC Shop Sync option..
	$woo_square_access_token_free    = get_option( 'woo_square_access_token_free' . get_transient( 'is_sandbox' ) );
	$woocommerce_square_settings     = get_option( 'woocommerce_square_settings' . get_transient( 'is_sandbox' ) );
	$woo_square_location_id_free     = get_option( 'woo_square_location_id_free' . get_transient( 'is_sandbox' ) );
	$is_moved_from_free              = get_option( 'is_moved_from_free' );
	$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
	if ( ! empty( $woo_square_access_token_free ) && empty( $is_moved_from_free ) ) {
		$activate_modules_woosquare_plus                                  = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
		$activate_modules_woosquare_plus['items_sync']['module_activate'] = true;
		$activate_modules_woosquare_plus['woosquare_payment']['module_activate'] = true;
		update_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ), $woo_square_access_token_free );
		update_option( 'woo_square_access_token_cauth' . get_transient( 'is_sandbox' ), $woo_square_access_token_free );
		update_option( 'woosquare_plus_reauth_notification' . get_transient( 'is_sandbox' ), $woo_square_access_token_free );
		update_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' . get_transient( 'is_sandbox' ), $woocommerce_square_settings );
		if ( ! empty( $woo_square_location_id_free ) ) {
			update_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ), $woo_square_location_id_free );
		}

		update_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), $activate_modules_woosquare_plus );
		update_option( 'is_moved_from_free', true );
	}
	if ( isset( $activate_modules_woosquare_plus['items_sync_log']['module_activate'] ) && true === $activate_modules_woosquare_plus['items_sync_log']['module_activate'] ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$sync_logs_table = $wpdb->prefix . WOO_SQUARE_ITEM_SYNC_LOGS_TABLE;
		$get_var         = 'get_var';
		if ( $wpdb->$get_var( "SHOW TABLES LIKE '$sync_logs_table'" ) !== $sync_logs_table ) {
			if ( ! empty( $wpdb->charset ) ) {
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			}
			if ( ! empty( $wpdb->collate ) ) {
				$charset_collate .= " COLLATE $wpdb->collate";
			}

			$sql = "CREATE TABLE IF NOT EXISTS $sync_logs_table (
				id INT(11) NOT NULL AUTO_INCREMENT,
				log_time DATETIME NOT NULL,
				status TEXT NOT NULL,
				message TEXT NOT NULL,
				sync_direction TEXT NOT NULL,
				item TEXT NOT NULL,
				enviroment TEXT NOT NULL,
				data TEXT NOT NULL,
				PRIMARY KEY (id)
			) $charset_collate;";

			dbDelta( $sql );
		}
	}
}

add_action( 'init', 'woosquare_init', 0 );

/**
 * Redirects to the Pro version page if the 'square-settings-pricing' page is accessed.
 *
 * @since 1.0.0
 */
function woosquare_freemius_redirect() {
	add_filter( 'gettext', 'change_addon_to_pro', 1, 3 );

	if ( isset( $_GET['page'] ) && 'square-settings-pricing' === $_GET['page'] ) { // phpcs:ignore
		header( 'Location: https://apiexperts.io/solutions/woosquare-plus/?utm_source=plugin&utm_medium=submenu' );
		exit;
	}
}

/**
 * Callback function to change the Freemius Upgrade text to 'Get Plus Now'.
 *
 * This function is used as a callback for the gettext filter to modify text in the 'freemius' domain.
 *
 * @since 1.0.0
 *
 * @param string $translated_text The translated text.
 * @param string $text            The original text.
 * @param string $domain          The text domain.
 *
 * @return string The modified or original translated text.
 */
function change_addon_to_pro( $translated_text, $text, $domain ) {

	if ( 'freemius' === $domain ) {
		if ( 'Upgrade' === $text ) {
			return __( 'Get Plus Now' );
		}
	}

	return $translated_text;
}

add_action( 'init', 'woosquare_freemius_redirect', 0 );
$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );
if( true === @$activate_modules_woosquare_plus['woosquare_payment']['module_activate'] ) {
	add_action( 'woocommerce_blocks_loaded', 'woosquare_free_woocommerce_blocks_support' );
}
/**
 * Adds support for WooCommerce Blocks payments in the free version of WC Shop Sync.
 *
 * Checks for the existence of WooCommerce Blocks and registers a custom payment method if the required class exists.
 *
 * @since 1.0.0
 */
function woosquare_free_woocommerce_blocks_support() {

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
