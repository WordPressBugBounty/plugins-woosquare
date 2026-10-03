<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       wpexperts.io
 * @since      1.0.0
 *
 * @package    Woosquare_Plus
 * @subpackage Woosquare_Plus/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Woosquare_Plus
 * @subpackage Woosquare_Plus/includes
 */
class Woosquare_Plus {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @var      Woosquare_Plus_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'PLUGIN_NAME_VERSION_WOOSQUARE_PLUS' ) ) {
			$this->version = PLUGIN_NAME_VERSION_WOOSQUARE_PLUS;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'woosquare-plus';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->get_access_token_woosquare_plus();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Woosquare_Plus_Loader. Orchestrates the hooks of the plugin.
	 * - Woosquare_Plus_I18n. Defines internationalization functionality.
	 * - Woosquare_Plus_Admin. Defines all hooks for the admin area.
	 * - Woosquare_Plus_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-woosquare-plus-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-woosquare-plus-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-woosquare-plus-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		// import woosquare classes.
		require_once plugin_dir_path( __DIR__ ) . 'admin/modules/product-sync/_inc/class-helpers.php';
		
		// Load ATUM compatibility if ATUM is active
		if ( class_exists( 'Atum\Inc\Helpers' ) ) {
			require_once plugin_dir_path( __DIR__ ) . 'admin/modules/product-sync/_inc/class-woosquare-atum-compatibility.php';
			// Initialize compatibility immediately after loading
			if ( class_exists( 'WooSquare_ATUM_Compatibility' ) ) {
				WooSquare_ATUM_Compatibility::init();
			}
		}
		require_once plugin_dir_path( __DIR__ ) . 'admin/modules/product-sync/_inc/class-square.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/modules/product-sync/_inc/class-squaretowoosynchronizer.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/modules/product-sync/_inc/class-wootosquaresynchronizer.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/modules/product-sync/_inc/class-woosquare-product-save-queue.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/modules/product-sync/_inc/admin/ajax.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/modules/product-sync/_inc/admin/pages.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/modules/product-sync/_inc/class-woosquare-client.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/modules/product-sync/_inc/class-woosquare-sync-logger.php';
		$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );
		if ( ! empty( $activate_modules_woosquare_plus['woosquare_payment']['module_activate'] ) ) {
			if ( ! defined( 'WOOSQUARE_PLUGIN_URL_PAYMENT' ) ) {
				define( 'WOOSQUARE_PLUGIN_URL_PAYMENT', untrailingslashit( plugins_url( 'admin/modules/square-payments', __DIR__ ) ) );
			}
			require_once plugin_dir_path( __DIR__ ) . 'admin/modules/square-payments/class-woosquare-payment-logger.php';
			require_once plugin_dir_path( __DIR__ ) . 'admin/modules/square-payments/class-woosquare-payments.php';
		}

		if ( ! empty( $activate_modules_woosquare_plus['customer_sync']['module_activate'] ) ) {

			require_once plugin_dir_path( __DIR__ ) . 'admin/modules/square-customers/customersync-integration.php';
		}
		if ( ! empty( $activate_modules_woosquare_plus['items_sync_log']['module_activate'] ) ) {
			if ( ! defined( 'WOOSQUARE_PLUGIN_URL_LOG' ) ) {
				define( 'WOOSQUARE_PLUGIN_URL_LOG', untrailingslashit( plugins_url( 'admin/modules/square-sync-logs', __DIR__ ) ) );
			}
			require_once plugin_dir_path( __DIR__ ) . 'admin/modules/square-sync-logs/class-woosquare-sync-logs.php';
		}

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'public/class-woosquare-plus-public.php';

		$this->loader = new Woosquare_Plus_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Woosquare_Plus_I18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 */
	private function set_locale() {

		$plugin_i18n = new Woosquare_Plus_I18n();

		$this->loader->add_action( 'init', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Woosquare_Plus_Admin( $this->get_plugin_name(), $this->get_version() );
		$data         = isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
		parse_str( $data, $query_params );
		if ( isset( $query_params['page'] ) ) {
			$page = isset( $query_params['page'] ) ? sanitize_text_field( wp_unslash( $query_params['page'] ) ) : '';
		}
		if ( ! empty( $page ) ) {
			$explode = explode( '-', $page );
			if ( 'woosquare' === $explode[0] || 'square' === $explode[0] ) {

				$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
				$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
			}
		}
		$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'check_square_scope' );

		if ( ! empty( $activate_modules_woosquare_plus['woosquare_loyalty']['module_activate'] ) && ! isset( $_GET['wfacp_id'] ) ) { // phpcs:ignore
			// Product sync module is disabled.
			require_once plugin_dir_path( __FILE__ ) . '../admin/modules/woosquare-loyalty/wcs-loyalty.php';
			if ( ! empty( $_GET['page'] ) && ( 'square-loyalty' === $_GET['page'] ) ) {  // phpcs:ignore
				add_action( 'admin_enqueue_scripts', 'enqueue_loyalty_points_script' );
			}
			add_action( 'wp_ajax_woosquare_fetch_loyalty_programs', 'woosquare_fetch_loyalty_programs' );
			add_action( 'wp_ajax_nopriv_woosquare_fetch_loyalty_programs', 'woosquare_fetch_loyalty_programs' );

			add_action( 'woocommerce_order_status_changed', 'create_loyalty_account', 99, 4 );

			add_filter( 'woocommerce_account_menu_items', 'add_loyalty_program_to_account_menu' );
			add_filter( 'document_title_parts', 'custom_account_document_title' );
			add_action( 'init', 'loyalty_program_add_endpoint' );
			add_action( 'woocommerce_account_loyalty-program_endpoint', 'loyalty_program_endpoint_content' );

			// Flush rewrite rules on plugin activation.
			register_activation_hook( __FILE__, 'flush_rewrite_rules' );
			add_action( 'init', 'maybe_flush_rewrite_rules' );

			add_action( 'wp_ajax_wcs_loyalty_handle_settings', 'wcs_loyalty_handle_settings' );
			add_action( 'wp_ajax_nopriv_wcs_loyalty_handle_settings', 'wcs_loyalty_handle_settings' );

			add_action( 'wp_enqueue_scripts', 'enqueue_loyalty_points_script' );

			add_action( 'woocommerce_cart_calculate_fees', 'apply_loyalty_discount_on_cart' );

			add_action( 'wp_ajax_apply_loyalty_ajax', 'apply_loyalty_discount_ajax' );
			add_action( 'wp_ajax_nopriv_apply_loyalty_ajax', 'apply_loyalty_discount_ajax' );

			add_action( 'wp_ajax_remove_loyalty_discount', 'remove_loyalty_discount' );
			add_action( 'wp_ajax_nopriv_remove_loyalty_discount', 'remove_loyalty_discount' );

		}

		$this->loader->add_action( 'admin_menu', $plugin_admin, 'woosquare_plus_menus' );
		$this->loader->add_action( 'wp_ajax_en_plugin', $plugin_admin, 'en_plugin_act' );
		$this->loader->add_action( 'wp_ajax_nopriv_en_plugin', $plugin_admin, 'en_plugin_act' );
		$this->loader->add_action( 'wp_ajax_nopriv_en_plugin', $plugin_admin, 'en_plugin_act' );

		if ( ! get_option( 'woosquare_plus_reauth_notification' . get_transient( 'is_sandbox' ) ) && get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) ) {
			$msg = wp_json_encode(
				array(
					'status' => false,
					'msg'    => 'ReConnect through auth square to make system more smooth.!',
				)
			);
			set_transient( 'woosquare_plus_notification', $msg, 12 * HOUR_IN_SECONDS );
		}

		$woosquare_plus_notification = get_transient( 'woosquare_plus_notification' );

		if ( ! empty( json_decode( $woosquare_plus_notification ) ) ) {
			$this->loader->add_action( 'admin_notices', $plugin_admin, 'woosquare_plus_notify' );
		}
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'woosquare_plus_payment_order_check', 999 );

		// square sync module.

		if ( ! empty( $activate_modules_woosquare_plus['items_sync']['module_activate'] ) || ! empty( $activate_modules_woosquare_plus['items_sync_log']['module_activate'] ) ) {
			require_once plugin_dir_path( __FILE__ ) . '../admin/modules/product-sync/product-sync.php';
			// register ajax actions.
			// woo->square.
			require_once plugin_dir_path( __FILE__ ) . '../admin/modules/product-sync/_inc/admin/ajax.php';
			add_action( 'wp_ajax_get_non_sync_woo_data', 'woo_square_plugin_get_non_sync_woo_data' );
			add_action( 'wp_ajax_start_manual_woo_to_square_sync', 'woo_square_plugin_start_manual_woo_to_square_sync' );
			add_action( 'wp_ajax_listsaved', 'woo_square_listsaved' );
			add_action( 'wp_ajax_sync_woo_category_to_square', 'woo_square_plugin_sync_woo_category_to_square' );
			add_action( 'wp_ajax_sync_woo_product_to_square', 'woo_square_plugin_sync_woo_product_to_square' );
			add_action( 'wp_ajax_terminate_manual_woo_sync', 'woo_square_plugin_terminate_manual_woo_sync' );
			add_action( 'wp_ajax_get_data_by_category', 'woo_square_get_data_by_category' );

			// square->woo.
			add_action( 'wp_ajax_get_non_sync_square_data', 'woo_square_plugin_get_non_sync_square_data' );
			add_action( 'wp_ajax_start_manual_square_to_woo_sync', 'woo_square_plugin_start_manual_square_to_woo_sync' );
			add_action( 'wp_ajax_sync_square_category_to_woo', 'woo_square_plugin_sync_square_category_to_woo' );
			add_action( 'wp_ajax_sync_square_product_to_woo', 'woo_square_plugin_sync_square_product_to_woo' );
			add_action( 'wp_ajax_update_square_to_woo', 'update_square_to_woo_action' );
			add_action( 'wp_ajax_terminate_manual_square_sync', 'woo_square_plugin_terminate_manual_square_sync' );
			add_action( 'wp_ajax_delete_manual_woo_sync_transients', 'delete_manual_woo_sync_transients' );
			add_action( 'wp_ajax_delete_manual_square_sync_transients', 'delete_manual_square_sync_transients' );
			add_action( 'auto_sync_cron_job_hook', 'auto_sync_cron_job' );
			add_action( 'wp_ajax_nopriv_square_sync_remote', 'handle_square_sync' );
			add_action( 'wp_ajax_square_sync_remote', 'handle_square_sync' );
			$this->loader->add_action( 'admin_init', $plugin_admin, 'wcsyn_create_integration_db' );
		}

		if ( isset( $activate_modules_woosquare_plus['items_sync_log']['module_activate'] ) && true === $activate_modules_woosquare_plus['items_sync_log']['module_activate'] ) {
			$this->loader->add_action( 'admin_init', $plugin_admin, 'wcsyn_create_logs_db' );
		}

		if (
			! empty( $activate_modules_woosquare_plus['customer_sync']['module_activate'] )
				||
			! empty( $activate_modules_woosquare_plus['woosquare_card_on_file']['module_activate'] )
			) {
			// square customer sync module.
			require_once plugin_dir_path( __FILE__ ) . '../admin/modules/square-customers/customersync-integration.php';
			require_once plugin_dir_path( __FILE__ ) . '../admin/modules/square-customers/admin/class-customer-sync-integration-admin.php';
			if ( ! empty( $activate_modules_woosquare_plus['customer_sync']['module_activate'] ) ) {
				$plugin_admin = new Customer_Sync_Integration_Admin( $this->get_plugin_name(), $this->get_version() );
				if ( get_option( 'woo_square_customer_merging_option' ) === '1' ) {
					// Woo commerce customer Override square customer.
					$this->loader->add_action( 'auto_sync_customer_cron_job_hook', $plugin_admin, 'sync_all_customer_to_square' );
				} elseif ( get_option( 'woo_square_customer_merging_option' ) === '2' ) {
					// Square customer Override Woo commerce customer.
					$this->loader->add_action( 'auto_sync_customer_cron_job_hook', $plugin_admin, 'sync_customer_data_from_square' );
				}
			}
		}

		if ( ! empty( $activate_modules_woosquare_plus['sales_sync']['module_activate'] ) ) {
			require_once plugin_dir_path( __FILE__ ) . '../admin/modules/order-sync/order-sync.php';
			add_action( 'woocommerce_api_square_order_sync', 'square_order_sync_handler' );
		}

		if ( ! empty( $activate_modules_woosquare_plus['woosquare_transaction_addon']['module_activate'] ) ) {
			require_once plugin_dir_path( __FILE__ ) . '../admin/modules/transaction-notes/transaction-notes.php';
			add_filter( 'woosquare_payment_order_note', 'woosquare_transaction_note_modified', 10, 2 );
		}

		if ( ! empty( $activate_modules_woosquare_plus['woosquare_modifiers']['module_activate'] ) && ! isset( $_GET['wfacp_id'] ) ) { // phpcs:ignore
			$path = plugin_dir_path( __FILE__ ) . '../admin/modules/woosquare-modifier/class-woosquare-modifier-admin.php';
			if ( file_exists( $path ) ) {
				require_once $path;
				global $wpdb;

				$db_table_name   = $wpdb->prefix . 'woosquare_modifier';  // table name.
				$charset_collate = $wpdb->get_charset_collate();
				$get_var         = 'get_var';
				// Check to see if the table exists already, if not, then create it.
				if ( $wpdb->$get_var( "show tables like '$db_table_name'" ) !== $db_table_name ) {
					$sql = "CREATE TABLE $db_table_name (
                    modifier_id BIGINT UNSIGNED NOT NULL auto_increment,
                    modifier_set_name varchar(200) NOT NULL,
                    modifier_slug varchar(200) NOT NULL,
                    modifier_option varchar(200) NOT NULL,
                    modifier_is_required_public int(1) NOT NULL DEFAULT 1,
                    modifier_public int(1) NOT NULL DEFAULT 1,
                    modifier_set_unique_id varchar(200),
                    modifier_version varchar(200),
                    PRIMARY KEY  (modifier_id),
                    KEY modifier_set_name (modifier_set_name(20))
                    ) $charset_collate;";

					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
					dbDelta( $sql );

				}
				$get_results = 'get_results';
				$query       = 'query';
				$row         = $wpdb->$get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '$db_table_name' AND column_name = 'modifier_is_required_public'" );
				if ( empty( $row ) ) {
					$wpdb->$query( "ALTER TABLE $db_table_name ADD modifier_is_required_public int(1) NOT NULL DEFAULT 1" );
				}
					$db_table_product_set_required = $wpdb->prefix . 'woosquare_modifier_required';  // table name.
					$charset_collate               = $wpdb->get_charset_collate();
			}
		}

		// Sandbox and Production Enable and disable.
		add_action( 'wp_ajax_enable_mode_checker', 'enable_mode_checker' );
		add_action( 'wp_ajax_nopriv_enable_mode_checker', 'enable_mode_checker' );
		if ( get_option( 'woosquare_stocksync_webhook' ) === '1' ) {
			add_action( 'woocommerce_api_square_stock_sync', 'square_stock_sync_handler' );
		}

		// ATUM Compatibility is handled in separate file: class-woosquare-atum-compatibility.php
		// It will auto-initialize if ATUM is active
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 */
	private function define_public_hooks() {

		$plugin_public = new Woosquare_Plus_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Woosquare_Plus_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Retrieve and refresh the access token for WC Shop Sync Plus.
	 *
	 * This function retrieves the access token and refreshes it if necessary.
	 * It performs checks on the token's expiration and updates the token if required.
	 * If there are connection issues or token problems, appropriate actions are taken.
	 */
	public function get_access_token_woosquare_plus() {
		// get it from where it save and check is expired than provide.

		if ( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) ) {
			$woo_square_auth_response = get_option( 'woo_square_auth_response' . get_transient( 'is_sandbox' ) );
			if ( is_object( $woo_square_auth_response ) ) {
				$woo_square_auth_response = (array) $woo_square_auth_response;
			}

			// Validate expires_at exists and is valid.
			if ( empty( $woo_square_auth_response ) || empty( $woo_square_auth_response['expires_at'] ) ) {
				return;
			}

			// Parse expires_at date and validate.
			$token_expires_at = strtotime( $woo_square_auth_response['expires_at'] );
			if ( false === $token_expires_at ) {
				return;
			}

			// Calculate time until expiry.
			$current_time      = time();
			$time_until_expiry = $token_expires_at - $current_time;

			// Only renew if token is expired or expires in less than 5 minutes (300 seconds).
			if ( $time_until_expiry <= 300 ) {
				// Prevent duplicate token renewal attempts using a transient lock.
				$renewal_lock_key = 'woosquare_token_renewal_lock' . get_transient( 'is_sandbox' );
				if ( get_transient( $renewal_lock_key ) ) {
					// Token renewal already in progress, skip to avoid duplicate logs.
					return;
				}

				// Check if token was recently renewed (within last 5 minutes).
				$last_renewal_key  = 'woosquare_last_token_renewal' . get_transient( 'is_sandbox' );
				$last_renewal_time = get_transient( $last_renewal_key );
				if ( $last_renewal_time && ( $current_time - $last_renewal_time ) < 300 ) {
					// Token was recently renewed, skip to prevent unnecessary renewals.
					return;
				}

				// Set lock for 5 minutes (300 seconds) to prevent concurrent renewals.
				// This matches API timeout and prevents lock expiry during long API calls.
				set_transient( $renewal_lock_key, true, 300 );

				$headers           = array(
					'refresh_token' => $woo_square_auth_response['refresh_token'], // Use verbose mode in cURL to determine the format you want for this header.
					'Content-Type'  => 'application/json;',
				);
				$oauth_connect_url = WOOSQU_PLUS_CONNECTURL;

				$uri = array(
					'app_name' => WOOSQU_PLUS_APPNAME,
					'plug'     => WOOSQU_PLUS_PLUGIN_NAME,
				);
				if ( get_transient( 'is_sandbox' ) ) {
					$uri = array_merge( $uri, array( 'woosquare_sandbox' => true ) );
				}
				$redirect_url           = add_query_arg(
					$uri,
					admin_url( 'admin.php' )
				);
				$redirect_url           = wp_nonce_url( $redirect_url, 'connect_wooplus', 'wc_wooplus_token_nonce' );
				$site_url               = ( rawurlencode( $redirect_url ) );
				$args_renew             = array(
					'body'    => array(
						'header'   => $headers,
						'action'   => 'renew_token',
						'site_url' => $site_url,
					),
					'timeout' => 45,
				);
				$oauth_response         = wp_remote_post( $oauth_connect_url, $args_renew );
				$decoded_oauth_response = json_decode( wp_remote_retrieve_body( $oauth_response ) );

				// Log the token renewal attempt.
				$this->log_connection_event(
					array(
						'action'       => 'token_renewal',
						'request_body' => wp_json_encode( $args_renew['body'] ),
						'response'     => wp_remote_retrieve_body( $oauth_response ),
						'status_code'  => wp_remote_retrieve_response_code( $oauth_response ),
						'success'      => ! empty( $decoded_oauth_response->access_token ),
					)
				);

				if ( ! empty( $decoded_oauth_response->access_token ) ) {
					$old_access_token                         = isset( $woo_square_auth_response['access_token'] ) ? $woo_square_auth_response['access_token'] : '';
					$woo_square_auth_response['expires_at']   = $decoded_oauth_response->expires_at;
					$woo_square_auth_response['access_token'] = $decoded_oauth_response->access_token;

					// Only update if token actually changed.
					if ( $old_access_token !== $woo_square_auth_response['access_token'] ) {
						update_option( 'woo_square_auth_response' . get_transient( 'is_sandbox' ), $woo_square_auth_response );
						update_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ), $woo_square_auth_response['access_token'] );
						update_option( 'woo_square_access_token_cauth' . get_transient( 'is_sandbox' ), $woo_square_auth_response['access_token'] );

						// Store last renewal time to prevent repeated renewals.
						set_transient( $last_renewal_key, $current_time, 600 ); // Store for 10 minutes.

						// Clear lock immediately after successful renewal.
						delete_transient( $renewal_lock_key );
					} else {
						// Token didn't change, clear lock anyway.
						delete_transient( $renewal_lock_key );
					}
				}
				// Note: If API call failed, lock will expire naturally after 5 minutes to prevent rapid retries.
			}
		}
	}

	/**
	 * Generate the top tabs HTML for the plugin.
	 *
	 * @return string The generated HTML for the top tabs.
	 */
	public function wooplus_get_toptabs() {
		$tablist        = '';
		$plugin_modules = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );

		if ( ! empty( $plugin_modules['module_page'] ) ) {
			foreach ( $plugin_modules as $key => $value ) {
				if ( $value['module_activate'] ) {
					if ( ! empty( get_option( 'woo_square_access_token_cauth' . get_transient( 'is_sandbox' ) ) ) && ! empty( get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ) ) ) {
						$navactive = '';
						if ( isset( $_GET['page'] ) && $_GET['page'] === $value['module_menu_details']['menu_slug'] ) { // phpcs:ignore
							$navactive = 'active';
						}
						if ( ! empty( $value['module_menu_details']['menu_slug'] ) && 'square-modifiers' !== $value['module_menu_details']['menu_slug'] ) :
							$tablist .= '<li class="nav-item">
										<a class="nav-link ' . $navactive . '" href="' . get_admin_url() . 'admin.php?page=' . $value['module_menu_details']['menu_slug'] . '" role="tab">
											<i class="' . $value['module_menu_details']['tab_html_class'] . '" aria-hidden="true"></i> ' . $value['module_menu_details']['menu_title'] . '
										</a>
									</li>';
								endif;
					}
				}
			}
		}

		$tabs_html = '
						<ul class="nav nav-tabs" role="tablist">
							' . $tablist . '
						</ul>';
		return $tabs_html;
	}

	/**
	 * Log connection events for debugging and monitoring.
	 *
	 * @param array $event_data Event data to log.
	 */
	private function log_connection_event( $event_data ) {
		$logs = get_option( 'woosquare_connection_logs' . get_transient( 'is_sandbox' ), array() );

		// Check if this is a failed connection.
		$is_failed = ! ( $event_data['success'] ?? false );

		// Create a unique key based on action, status_code, and response hash to prevent duplicates.
		$request_body_hash = substr( md5( $event_data['request_body'] ?? '' ), 0, 8 );
		$response_hash     = substr( md5( $event_data['response'] ?? '' ), 0, 8 );
		$log_key           = $event_data['action'] . '_' . ( $event_data['status_code'] ?? 0 ) . '_' . $request_body_hash . '_' . $response_hash;
		$duplicate_key     = 'woosquare_log_' . $log_key . '_' . get_transient( 'is_sandbox' );

		// For failed logs, use longer time window (30 minutes) to prevent spam.
		// For success logs, use shorter time window (10 seconds).
		$time_window = $is_failed ? ( 30 * MINUTE_IN_SECONDS ) : 10;

		// Check if similar log was created recently (prevent duplicates).
		if ( get_transient( $duplicate_key ) ) {
			return;
		}

		// For failed logs, check if same error pattern is already suppressed.
		if ( $is_failed ) {
			$error_pattern   = $event_data['status_code'] . '_' . $response_hash;
			$suppression_key = 'woosquare_error_suppressed_' . $error_pattern . '_' . get_transient( 'is_sandbox' );

			// Check if this error pattern is currently suppressed (waiting for success response).
			$is_suppressed = get_transient( $suppression_key );
			if ( $is_suppressed ) {
				// Error pattern is suppressed, skip adding new entry until success response comes.
				return;
			}

			// Check if same error occurred multiple times recently.
			$recent_failed_count = 0;
			$two_hours_ago       = strtotime( '-2 hours' );

			foreach ( $logs as $existing_log ) {
				if ( ! $existing_log['success'] && $existing_log['status_code'] === $event_data['status_code'] ) {
					$log_timestamp = strtotime( $existing_log['timestamp'] );
					// Only count logs from last 2 hours.
					if ( $log_timestamp >= $two_hours_ago ) {
						$existing_response_hash = substr( md5( $existing_log['response'] ?? '' ), 0, 8 );
						if ( $existing_response_hash === $response_hash ) {
							++$recent_failed_count;
						}
					}
				}
			}

			// If same error occurred 3+ times in last 2 hours, suppress it completely.
			// Suppression will only be cleared when a success response is received.
			if ( $recent_failed_count >= 3 ) {
				// Set suppression flag (no expiration - will be cleared only on success).
				set_transient( $suppression_key, true, 0 ); // 0 = no expiration, but we'll clear it manually on success.
				return;
			}
		} else {
			// Success response received - clear all suppression flags for this action.
			// This ensures that if error was suppressed, it gets cleared on success.
			$action_pattern = $event_data['action'] ?? 'unknown';
			$logs           = get_option( 'woosquare_connection_logs' . get_transient( 'is_sandbox' ), array() );

			// Check recent failed logs and clear their suppression flags.
			$two_hours_ago = strtotime( '-2 hours' );
			foreach ( $logs as $existing_log ) {
				if ( ! $existing_log['success'] && $existing_log['action'] === $action_pattern ) {
					$log_timestamp = strtotime( $existing_log['timestamp'] );
					if ( $log_timestamp >= $two_hours_ago ) {
						$existing_response_hash = substr( md5( $existing_log['response'] ?? '' ), 0, 8 );
						$error_pattern          = $existing_log['status_code'] . '_' . $existing_response_hash;
						$suppression_key        = 'woosquare_error_suppressed_' . $error_pattern . '_' . get_transient( 'is_sandbox' );

						// Clear suppression for this error pattern since we got success.
						delete_transient( $suppression_key );
					}
				}
			}
		}

		// Set transient to prevent duplicate logs.
		set_transient( $duplicate_key, true, $time_window );

		$log_entry = array(
			'id'           => uniqid(),
			'timestamp'    => current_time( 'mysql' ),
			'square_mode'  => get_transient( 'is_sandbox' ) ? 'Sandbox' : 'Production',
			'action'       => $event_data['action'] ?? 'unknown',
			'request_body' => $event_data['request_body'] ?? '',
			'response'     => $event_data['response'] ?? '',
			'status_code'  => $event_data['status_code'] ?? 0,
			'success'      => $event_data['success'] ?? false,
		);

		// Add to beginning of array (newest first).
		array_unshift( $logs, $log_entry );

		// Keep only last 50 logs.
		$logs = array_slice( $logs, 0, 50 );

		update_option( 'woosquare_connection_logs' . get_transient( 'is_sandbox' ), $logs );

		// Send email alert if enabled (with additional checks for failed logs).
		$this->send_log_alert_email( $log_entry );
	}

	/**
	 * Send email alert for connection logs.
	 *
	 * @param array $log_entry Log entry data.
	 */
	private function send_log_alert_email( $log_entry ) {

		// Check if alerts are enabled.
		$alerts_enabled = get_option( 'woosquare_alerts_enabled', false );
		if ( ! $alerts_enabled ) {
			return;
		}

		// Get alert email address.
		$alert_email = get_option( 'woosquare_alert_email', get_option( 'admin_email' ) );
		if ( empty( $alert_email ) || ! is_email( $alert_email ) ) {
			return;
		}

		// Check if this is a failed connection.
		$is_failed = ! $log_entry['success'];

		// For failed logs, create a more specific key based on error pattern.
		if ( $is_failed ) {
			$response_hash = substr( md5( $log_entry['response'] ?? '' ), 0, 8 );
			$error_pattern = $log_entry['status_code'] . '_' . $response_hash;
			$email_key     = 'woosquare_email_failed_' . $error_pattern . '_' . get_transient( 'is_sandbox' );

			// Check if this error pattern is suppressed (waiting for success response).
			$suppression_key = 'woosquare_error_suppressed_' . $error_pattern . '_' . get_transient( 'is_sandbox' );
			$is_suppressed   = get_transient( $suppression_key );

			// If error pattern is suppressed, don't send email.
			if ( $is_suppressed ) {
				return;
			}

			// Check how many times this error occurred in last 2 hours.
			$logs                = get_option( 'woosquare_connection_logs' . get_transient( 'is_sandbox' ), array() );
			$two_hours_ago       = strtotime( '-2 hours' );
			$error_count_last_2h = 0;

			foreach ( $logs as $existing_log ) {
				if ( ! $existing_log['success'] && $existing_log['status_code'] === $log_entry['status_code'] ) {
					$log_timestamp = strtotime( $existing_log['timestamp'] );
					if ( $log_timestamp >= $two_hours_ago ) {
						$existing_response_hash = substr( md5( $existing_log['response'] ?? '' ), 0, 8 );
						if ( $existing_response_hash === $response_hash ) {
							++$error_count_last_2h;
						}
					}
				}
			}

			// If same error occurred 3+ times in last 2 hours, suppress emails completely.
			// Emails will resume only when success response is received.
			if ( $error_count_last_2h >= 3 ) {
				return;
			}

			// For occasional errors, use 60 minutes window.
			$email_time_window = 60 * MINUTE_IN_SECONDS;
		} else {
			// Success response received - clear email suppression for this action's errors.
			$action_pattern = $log_entry['action'] ?? 'unknown';
			$logs           = get_option( 'woosquare_connection_logs' . get_transient( 'is_sandbox' ), array() );

			// Clear email suppression flags for recent failed logs of same action.
			$two_hours_ago = strtotime( '-2 hours' );
			foreach ( $logs as $existing_log ) {
				if ( ! $existing_log['success'] && $existing_log['action'] === $action_pattern ) {
					$log_timestamp = strtotime( $existing_log['timestamp'] );
					if ( $log_timestamp >= $two_hours_ago ) {
						$existing_response_hash = substr( md5( $existing_log['response'] ?? '' ), 0, 8 );
						$error_pattern          = $existing_log['status_code'] . '_' . $existing_response_hash;
						$email_suppression_key  = 'woosquare_email_failed_' . $error_pattern . '_' . get_transient( 'is_sandbox' );

						// Clear email suppression for this error pattern.
						delete_transient( $email_suppression_key );
					}
				}
			}

			// For success logs, use log entry ID.
			$email_key         = 'woosquare_email_sent_' . $log_entry['id'];
			$email_time_window = 5 * MINUTE_IN_SECONDS; // 5 minutes for success logs.
		}

		// Prevent duplicate emails.
		if ( get_transient( $email_key ) ) {
			return;
		}

		// Set transient to prevent duplicate emails.
		set_transient( $email_key, true, $email_time_window );

		// Prepare email content.
		$subject = 'WooSquare Connection Alert - ' . $log_entry['square_mode'];

		// Format timestamp.
		$formatted_timestamp = gmdate( 'g:i A - n/j/Y', strtotime( $log_entry['timestamp'] ) );

		// Template file paths - read from local files.
		$base_path        = plugin_dir_path( __FILE__ ) . '../admin/modules/square-connection/';
		$success_template = $base_path . 'sucess.php';
		$failed_template  = $base_path . 'failed.php';

		// Function to extract HTML body from PHP file.
		$extract_html_body = function ( $file_path ) {
			if ( ! file_exists( $file_path ) ) {
				return '';
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template file, not remote URL.
			$file_content = file_get_contents( $file_path );

			// Extract the $body variable assignment - handle multi-line strings.
			// Pattern: Find $body = '[content]'; or $body = "[content]";
			// Match from opening quote to closing quote before semicolon.

			// Match pattern: $body = '...'; or $body = "...";
			// Use non-greedy matching but ensure we capture everything including nested quotes.
			if ( preg_match( '/\$body\s*=\s*[\'"](.+?)[\'"]\s*;/s', $file_content, $matches ) ) {
				$html_string = $matches[1];
			} else {
				return '';
			}

			// Unescape escaped quotes if any.
			$html_string = str_replace( "\\'", "'", $html_string );
			$html_string = str_replace( '\\"', '"', $html_string );
			// Unescape newlines if any.
			$html_string = str_replace( '\\n', "\n", $html_string );

			return $html_string;
		};

		// Get site URL and images path.
		$images_path = plugin_dir_url( __FILE__ ) . '../admin/modules/square-connection/images/';

		// Load appropriate template.
		if ( $log_entry['success'] ) {
			$html_content = $extract_html_body( $success_template );
			if ( empty( $html_content ) ) {
				return;
			}
			// Replace placeholders - $images_path appears as ' . $images_path . ' in the template.
			// The extracted HTML contains literal string ' . $images_path . ' (with quotes and spaces) so replace it.
			// Try multiple patterns to ensure replacement works.
			$html_content = preg_replace( '/\'?\s*\.\s*\$images_path\s*\.\s*\'?/', $images_path, $html_content );
			// Fallback: try with str_replace in case regex doesn't match.
			$html_content = str_replace( "' . \$images_path . '", $images_path, $html_content );
			$html_content = str_replace( "'.$images_path.'", $images_path, $html_content );
			$html_content = str_replace( 'Sandbox', esc_html( $log_entry['square_mode'] ), $html_content );
			$html_content = str_replace( '6:22pm - 8/27/2025', esc_html( $formatted_timestamp ), $html_content );

			// Remove promotion div for premium users - only show for free users.
			$is_free_user = true;
			if ( function_exists( 'woosquare_fs' ) ) {
				$woosquare_fs = woosquare_fs();
				if ( is_object( $woosquare_fs ) && method_exists( $woosquare_fs, 'is_free_plan' ) ) {
					$is_free_user = $woosquare_fs->is_free_plan();
				}
			}

			// If user is not on free plan (has premium), remove the promotion section.
			if ( ! $is_free_user ) {
				// Remove the entire promotion tr section (from background #F1F5F9 to end of that tr).
				$html_content = preg_replace( '/<tr style="background: #F1F5F9[^>]*>.*?<\/tr>\s*/s', '', $html_content );
			}
		} else {
			$html_content = $extract_html_body( $failed_template );
			if ( empty( $html_content ) ) {
				return;
			}
			// Replace placeholders - $images_path appears as ' . $images_path . ' in the template.
			// The extracted HTML contains literal string ' . $images_path . ' (with quotes and spaces) so replace it.
			// Try multiple patterns to ensure replacement works.
			$html_content = preg_replace( '/\'?\s*\.\s*\$images_path\s*\.\s*\'?/', $images_path, $html_content );
			// Fallback: try with str_replace in case regex doesn't match.
			$html_content = str_replace( "' . \$images_path . '", $images_path, $html_content );
			$html_content = str_replace( "'.$images_path.'", $images_path, $html_content );
			$html_content = str_replace( 'Sandbox', esc_html( $log_entry['square_mode'] ), $html_content );
			$html_content = str_replace( '6:22pm - 8/27/2025', esc_html( $formatted_timestamp ), $html_content );
			$html_content = str_replace( 'Connection Successful', 'Connection Failed', $html_content );

			if ( ! empty( $log_entry['request_body'] ) ) {
				$html_content = str_replace(
					'Request body',
					esc_html( substr( $log_entry['request_body'], 0, 200 ) ) . '...',
					$html_content
				);
			}
		}

		$message = $html_content;

		// Email headers.
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: WooSquare Alerts <' . get_option( 'admin_email' ) . '>',
		);

		// Send email.
		$email_result = wp_mail( $alert_email, $subject, $message, $headers );
	}
}
