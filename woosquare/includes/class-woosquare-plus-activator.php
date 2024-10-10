<?php
/**
 * Fired during plugin activation
 *
 * @link       wpexperts.io
 * @since      1.0.0
 *
 * @package    Woosquare_Plus
 * @subpackage Woosquare_Plus/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Woosquare_Plus
 * @subpackage Woosquare_Plus/includes
 * @author     Wpexpertsio <support@wpexperts.io>
 */
class Woosquare_Plus_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

		$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );

		if ( empty( $activate_modules_woosquare_plus ) && ! empty( get_option( 'woo_square_access_token_cauth' . get_transient( 'is_sandbox' ) ) ) ) {
			delete_option( 'woo_square_access_token_cauth' . get_transient( 'is_sandbox' ) );
		}
		$plugin_modules = array(
			'items_sync'                  => array(
				'module_img'           => esc_url( plugin_dir_url( __FILE__ ) . '../admin/img/itemsyncnew.png' ),
				'module_title'         => __( 'Synchronization of Products', 'woosquare' ),
				'module_short_excerpt' => __( 'Helps you to synchronize products between Square and WooCommerce, in the direction of your preference.', 'woosquare' ),
				'module_redirect'      => esc_url( 'https://apiexperts.io/documentation/woosquare-plus/?utm_source=plugin&utm_medium=addons#syncing-of-products-6' ),
				'module_slug'          => 'syncing-of-products-6',
				'module_video'         => esc_url( 'https://www.youtube.com/embed/E-gVN51P9lk' ),
				'module_activate'      => ! empty( $activate_modules_woosquare_plus['items_sync']['module_activate'] ) ? true : false,
				'is_premium'           => false,
				'module_menu_details'  => array(
					'menu_title'        => __( 'Sync Products', 'woosquare' ),
					'parent_slug'       => 'square-settings',
					'page_title'        => __( 'WC Shop Sync Item Sync', 'woosquare' ),
					'capability'        => 'manage_options',
					'menu_slug'         => 'square-item-sync',
					'tab_html_class'    => 'fa fa-retweet',
					'function_callback' => 'square_item_sync_page',
				),
			),
			'items_sync_log'              => array(
				'module_img'           => esc_url( plugin_dir_url( __FILE__ ) . '../admin/img/logsyncnew.png' ),
				'module_title'         => __( 'Logs of Sync Products', 'woosquare' ),
				'module_short_excerpt' => __( 'Helps you to track logs of product synchronization.', 'woosquare' ),
				'module_redirect'      => esc_url( 'https://apiexperts.io/link/synchronization-of-products/' ),
				'module_slug'          => 'syncing-of-products-6',
				'module_video'         => esc_url( 'https://www.youtube.com/embed/E-gVN51P9lk' ),
				'module_activate'      => ! empty( $activate_modules_woosquare_plus['items_sync_log']['module_activate'] ) ? true : false,
				'is_premium'           => false,
				'module_menu_details'  => array(
					'menu_title'        => __( 'Sync Products logs', 'woosquare' ),
					'parent_slug'       => 'square-settings',
					'page_title'        => __( 'WC Shop Sync Item Sync log', 'woosquare' ),
					'capability'        => 'manage_options',
					'menu_slug'         => 'square-item-sync-log',
					'tab_html_class'    => 'fa fa-list',
					'function_callback' => 'square_item_sync_log_page',
				),
			),
			'woosquare_payment'           => array(
				'module_img'           => esc_url( plugin_dir_url( __FILE__ ) . '../admin/img/paymentsyncnew.png' ),
				'module_title'         => __( 'Square Payment Gateway', 'woosquare' ),
				'module_short_excerpt' => __( 'Collect payments with Square Payment processor at WooCommerce checkout and manage sales and refunds easily.', 'woosquare' ),
				'module_redirect'      => esc_url( 'https://apiexperts.io/documentation/woosquare-plus/?utm_source=plugin&utm_medium=addons#square-payment-gateway-8' ),
				'module_slug'          => 'square-payment-gateway-8',
				'module_video'         => esc_url( 'https://www.youtube.com/embed/-uYI_a-k9Eo' ),
				'module_activate'      => ! empty( $activate_modules_woosquare_plus['woosquare_payment']['module_activate'] ) ? true : false,
				'is_premium'           => false,
				'module_menu_details'  => array(
					'menu_title'        => __( 'Payment Settings', 'woosquare' ),
					'parent_slug'       => 'square-settings',
					'page_title'        => __( 'WooCommerce Square Up Payment Gateway', 'woosquare' ),
					'capability'        => 'manage_options',
					'menu_slug'         => 'square-payment-gateway',
					'tab_html_class'    => 'fa fa-square',
					'function_callback' => 'square_payment_sync_page',
				),
			),
			'sales_sync'                  => array(
				'module_img'           => esc_url( plugin_dir_url( __FILE__ ) . '../admin/img/ordersyncnew.png' ),
				'module_title'         => __( 'Order Synchronization', 'woosquare' ),
				'module_short_excerpt' => __( 'Automate the process to synchronize orders between WooCommerce and Square.', 'woosquare' ),
				'module_redirect'      => esc_url( 'https://apiexperts.io/documentation/woosquare-plus/?utm_source=plugin&utm_medium=addons#order-synchronization-8' ),
				'module_slug'          => 'order-synchronization-8',
				'module_video'         => esc_url( 'https://www.youtube.com/embed/bDzRLARmRzQ' ),
				'module_activate'      => ! empty( $activate_modules_woosquare_plus['sales_sync']['module_activate'] ) ? true : false,
				'is_premium'           => true,
				'module_menu_details'  => array(
					'menu_title'        => __( 'Order Sync', 'woosquare' ),
					'parent_slug'       => 'square-settings',
					'page_title'        => __( 'WooCommerce to Square Order Sync', 'woosquare' ),
					'capability'        => 'manage_options',
					'menu_slug'         => 'order-sync',
					'tab_html_class'    => 'fa fa-list-ul',
					'function_callback' => 'square_order_sync_page',
				),
			),
			'customer_sync'               => array(
				'module_img'           => esc_url( plugin_dir_url( __FILE__ ) . '../admin/img/customersyncnew.png' ),
				'module_title'         => __( 'Customers Synchronization', 'woosquare' ),
				'module_short_excerpt' => __( 'Easily keep your Square and WooCommerce customers in sync, and link them to the orders appearing in WooCommerce from Square.', 'woosquare' ),
				'module_redirect'      => esc_url( 'https://apiexperts.io/documentation/woosquare-plus/?utm_source=plugin&utm_medium=addons#customer-synchronisation-6' ),
				'module_slug'          => 'customer-synchronisation-6',
				'module_activate'      => ! empty( $activate_modules_woosquare_plus['customer_sync']['module_activate'] ) ? true : false,
				'is_premium'           => true,
				'module_menu_details'  => array(
					'menu_title'        => __( 'Customers Sync', 'woosquare' ),
					'parent_slug'       => 'square-settings',
					'page_title'        => __( 'WC Shop Sync Customer Sync', 'woosquare' ),
					'capability'        => 'manage_options',
					'menu_slug'         => 'square-customers',
					'tab_html_class'    => 'fa fa-users',
					'function_callback' => 'square_customer_sync_page',
				),
			),

			'woosquare_transaction_addon' => array(
				'module_img'           => esc_url( plugin_dir_url( __FILE__ ) . '../admin/img/transactionnotesyncnew.png' ),
				'module_title'         => __( 'Transaction notes', 'woosquare' ),
				'module_short_excerpt' => __( 'Manage information to be displayed in Square transaction notes for the payments made at WooCommerce checkout.', 'woosquare' ),
				'module_redirect'      => esc_url( 'https://apiexperts.io/documentation/woosquare-plus/?utm_source=plugin&utm_medium=addons#transaction-notes-7' ),
				'module_slug'          => 'transaction-notes-7',
				'module_video'         => esc_url( 'https://www.youtube.com/embed/s2inxilrncc' ),
				'module_activate'      => ! empty( $activate_modules_woosquare_plus['woosquare_transaction_addon']['module_activate'] ) ? true : false,
				'is_premium'           => true,
				'module_menu_details'  => array(
					'menu_title'        => __( 'Transaction Notes', 'woosquare' ),
					'parent_slug'       => 'square-settings',
					'page_title'        => __( 'WC Shop Sync Transaction Sync', 'woosquare' ),
					'capability'        => 'manage_options',
					'menu_slug'         => 'square-transaction-sync',
					'tab_html_class'    => 'fa fa-bell',
					'function_callback' => 'square_transaction_sync_page',
				),
			),
			'woosquare_card_on_file'      => array(
				'module_img'           => esc_url( plugin_dir_url( __FILE__ ) . '../admin/img/cardonfilenew.png' ),
				'module_title'         => __( 'Save cards at checkout', 'woosquare' ),
				'module_short_excerpt' => __( 'Users can save their cards at the time of checkout in WooCommerce, and can use them in future easily.', 'woosquare' ),
				'module_redirect'      => esc_url( 'https://apiexperts.io/documentation/woosquare-plus/?utm_source=plugin&utm_medium=addons#save-cards-at-checkout-6' ),
				'module_slug'          => 'save-cards-at-checkout-6',
				'module_video'         => esc_url( 'https://www.youtube.com/embed/YVnjPEUWg8U' ),
				'module_activate'      => ! empty( $activate_modules_woosquare_plus['woosquare_card_on_file']['module_activate'] ) ? true : false,
				'is_premium'           => true,
				'module_menu_details'  => array(
					'menu_title'        => __( 'Save cards', 'woosquare' ),
					'parent_slug'       => 'square-settings',
					'page_title'        => __( 'WC Shop Sync Payment With Card on File', 'woosquare' ),
					'capability'        => 'manage_options',
					'menu_slug'         => 'square-card-sync',
					'tab_html_class'    => 'fa fa-credit-card',
					'function_callback' => 'square_card_sync_page',
				),
			),

			'woosquare_modifiers'         => array(
				'module_img'           => esc_url( plugin_dir_url( __FILE__ ) . '../admin/img/modifiersyncnew.png' ),
				'module_title'         => __( 'Square Modifiers', 'woosquare' ),
				'module_short_excerpt' => __( 'Square Modifiers in WC Shop Sync allow you to sell items that are customizable or offer additional choices.', 'woosquare' ),
				'module_redirect'      => esc_url( 'https://apiexperts.io/documentation/woosquare-plus/?utm_source=plugin&utm_medium=addons#square-modifiers-4' ),
				'module_slug'          => 'square-modifiers-4',
				'module_video'         => esc_url( 'https://www.youtube.com/embed/XnC0cOoWx-k' ),
				'module_activate'      => ! empty( $activate_modules_woosquare_plus['woosquare_modifiers']['module_activate'] ) ? true : false,
				'is_premium'           => true,
				'module_menu_details'  => array(
					'menu_title'        => __( 'Square Modifiers', 'woosquare' ),
					'parent_slug'       => 'square-modifiers',
					'page_title'        => __( 'Square Modifiers', 'woosquare' ),
					'capability'        => 'manage_options',
					'menu_slug'         => 'square-modifiers',
					'tab_html_class'    => 'fa fa-credit-card',
					'function_callback' => 'square_modifiers_sync_page',
				),
			),
		);

			$plugin_modules['module_page'] = array(
				'module_activate'     => true,
				'module_menu_details' => array(
					'menu_title'        => __( 'Plugin Module', 'woosquare' ),
					'parent_slug'       => 'square-settings',
					'page_title'        => __( 'WC Shop Sync Module', 'woosquare' ),
					'capability'        => 'manage_options',
					'menu_slug'         => 'woosquare-plus-module',
					'tab_html_class'    => '',
					'function_callback' => 'woosquare_plus_module_page',
				),
			);

			update_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), $plugin_modules );

			/*
			* square activation
			*/

			$user_id = username_exists( 'square_user' );
			if ( ! $user_id ) {
				$random_password = wp_generate_password( 12 );
				$user_id         = wp_create_user( 'square_user', $random_password );
				wp_update_user(
					array(
						'ID'         => $user_id,
						'first_name' => 'Square',
						'last_name'  => 'User',
					)
				);
			}
			// check begin time exist for payment.
			if ( ! get_option( 'square_payment_begin_time' ) ) {
				update_option( 'square_payment_begin_time', gmdate( 'Y-m-d\TH:i:s\Z' ) );

			}

			deactivate_plugins( 'woosquare-pro/woocommerce-square-integration.php' );
			deactivate_plugins( 'woosquare-payment/woosquare-payment.php' );
			deactivate_plugins( 'wc-square-recurring-premium/wc-square-recuring.php' );
			$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
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
}
