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
 * @author     Wpexpertsio <support@wpexperts.io>
 */
class Woosquare_Plus {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Woosquare_Plus_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
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
		$this->load_plugin_app_creds();
		$this->square_fr_key_handler();
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
	 * @access   private
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
		// import WC Shop Sync classes.
		require_once plugin_dir_path( __DIR__ ) . 'admin/modules/product-sync/_inc/class-helpers.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/modules/product-sync/_inc/class-square.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/modules/product-sync/_inc/class-squaretowoosynchronizer.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/modules/product-sync/_inc/class-wootosquaresynchronizer.php';
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
	 * Handle the callback for the 'square_fr_key' parameter.
	 *
	 * This function processes the callback when the 'square_fr_key' parameter matches the expected value.
	 * It provides specific application data in JSON format for the callback response.
	 */
	public function square_fr_key_handler() {

		if ( isset( $_GET['square_fr_key'] ) && 'Veo0FDkKA7kRb' === $_GET['square_fr_key'] ) { // phpcs:ignore

			echo ( wp_json_encode(
				array(
					'SQUARE_APPLICATION_ID' => WOOSQU_PLUS_APPID,
					'SQUARE_SECRET_ID'      => SQUARE_SECRET_ID,
					'SQUARE_HOSTURL'        => SQUARE_HOSTURL,
					'is_sandbox'            => get_transient( 'is_sandbox' ),
				)
			) );

			die();
		}
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Woosquare_Plus_I18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Woosquare_Plus_I18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
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

		$this->loader->add_action( 'admin_menu', $plugin_admin, 'woosquare_plus_menus' );
		$this->loader->add_action( 'wp_ajax_en_plugin', $plugin_admin, 'en_plugin_act' );
		$this->loader->add_action( 'wp_ajax_nopriv_en_plugin', $plugin_admin, 'en_plugin_act' );

		$woo_square_auth_response = get_option( 'woo_square_auth_response' . get_transient( 'is_sandbox' ) );
		if ( is_object( $woo_square_auth_response ) ) {
			$woo_square_auth_response = (array) $woo_square_auth_response;
		}

		if (
		(
		! get_option( 'woosquare_plus_reauth_notification' . get_transient( 'is_sandbox' ) )
		&&
		get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) )
		)
		||
		isset( $woo_square_auth_response['expires_at'] ) && ( time() >= ( strtotime( $woo_square_auth_response['expires_at'] ) + 9000 ) )
		) {
			$connectlink = get_admin_url() . 'admin.php?page=square-settings';
			$msg         = wp_json_encode(
				array(
					'status' => false,
					'msg'    => 'Having connection issues? Your access token may be expired or unable to refresh. Please <a class="moduleslink" href=' . $connectlink . ' >reconnect with Square</a> to make sure your plugin works smoothly.
If the issue still persists, please contact the <a href="mailto:support@wpexperts.io">WPExperts support team</a> immediately. ',
				)
			);
			set_transient( 'woosquare_plus_notification', $msg, 12 * HOUR_IN_SECONDS );
		}

		$woosquare_plus_notification = get_transient( 'woosquare_plus_notification' );

		if ( ! empty( json_decode( $woosquare_plus_notification ) ) ) {
			$this->loader->add_action( 'admin_notices', $plugin_admin, 'woosquare_plus_notify' );
		}
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'woosquare_plus_payment_order_check', 999 );

		$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );
		// square sync module.

		if ( ! empty( $activate_modules_woosquare_plus['items_sync']['module_activate'] ) ) {

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

			// square->woo.
			add_action( 'wp_ajax_get_non_sync_square_data', 'woo_square_plugin_get_non_sync_square_data' );
			add_action( 'wp_ajax_start_manual_square_to_woo_sync', 'woo_square_plugin_start_manual_square_to_woo_sync' );
			add_action( 'wp_ajax_sync_square_category_to_woo', 'woo_square_plugin_sync_square_category_to_woo' );
			add_action( 'wp_ajax_sync_square_product_to_woo', 'woo_square_plugin_sync_square_product_to_woo' );
			add_action( 'wp_ajax_update_square_to_woo', 'update_square_to_woo_action' );
			add_action( 'wp_ajax_terminate_manual_square_sync', 'woo_square_plugin_terminate_manual_square_sync' );

		}
		add_action( 'wp_ajax_enable_mode_checker', 'enable_mode_checker' );
		add_action( 'wp_ajax_nopriv_enable_mode_checker', 'enable_mode_checker' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
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
	 * Load plugin application credentials based on vendor check.
	 *
	 * This function loads the necessary application credentials based on a vendor check.
	 * If the vendor check is positive, vendor-specific credentials are defined.
	 * Otherwise, default credentials are defined.
	 */
	public function load_plugin_app_creds() {

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
		}
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

			if (
			! empty( $woo_square_auth_response )
			&&
			( strtotime( $woo_square_auth_response['expires_at'] ) - ( WEEK_IN_SECONDS * 2 ) ) <= time()
			) {

				$headers           = array(
					'refresh_token' => $woo_square_auth_response['refresh_token'], // Use verbose mode in cURL to determine the format you want for this header.
					'Content-Type'  => 'application/json;',
				);
				$oauth_connect_url = WOOSQU_PLUS_CONNECTURL;
				if ( get_transient( 'is_sandbox' ) === 'sandbox' ) {
					$sndbox = true;
				} else {
					$sndbox = false;
				}
				$redirect_url = add_query_arg(
					array(
						'app_name'          => WOOSQU_PLUS_APPNAME,
						'woosquare_sandbox' => $sndbox,
						'plug'              => WOOSQU_PLUS_PLUGIN_NAME,
						'WOOSQU_PLUS_APPID' => WOOSQU_PLUS_APPID,
						'access_token'      => get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ),
					),
					admin_url( 'admin.php' )
				);

				$redirect_url = wp_nonce_url( $redirect_url, 'connect_wooplus', 'wc_wooplus_token_nonce' );
				$site_url     = rawurlencode( $redirect_url );
				$args_renew   = array(
					'body'    => array(
						'header'   => $headers,
						'action'   => 'renew_token',
						'site_url' => $site_url,
					),
					'timeout' => 45,
				);

				$oauth_response = wp_remote_post( $oauth_connect_url, $args_renew );

				$decoded_oauth_response = json_decode( wp_remote_retrieve_body( $oauth_response ) );

				if ( ! empty( $decoded_oauth_response->access_token ) ) {
					$woo_square_auth_response['expires_at']   = $decoded_oauth_response->expires_at;
					$woo_square_auth_response['access_token'] = $decoded_oauth_response->access_token;
					update_option( 'woo_square_auth_response' . get_transient( 'is_sandbox' ), $woo_square_auth_response );
					update_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ), $woo_square_auth_response['access_token'] );
					update_option( 'woo_square_access_token_cauth' . get_transient( 'is_sandbox' ), $woo_square_auth_response['access_token'] );

				} else {
					$connectlink = get_admin_url() . 'admin.php?page=square-settings';
					$msg         = wp_json_encode(
						array(
							'status' => false,
							'msg'    => 'Having connection issues? Your access token may be expired or unable to refresh. Please <a class="moduleslink" href=' . $connectlink . ' >reconnect with Square</a> to make sure your plugin works smoothly.
			If the issue still persists, please contact the <a href="mailto:support@wpexperts.io">WPExperts support team</a> immediately. ',
						)
					);
					set_transient( 'woosquare_plus_notification', $msg, 12 * HOUR_IN_SECONDS );
				}
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
						if ( 'square-modifiers' !== $value['module_menu_details']['menu_slug'] && ! empty( $value['module_menu_details']['menu_slug'] ) ) :
							if ( empty( $value['is_premium'] ) ) :
								$tablist .= '<li class="nav-item">
										<a class="nav-link ' . $navactive . '" href="' . get_admin_url() . 'admin.php?page=' . $value['module_menu_details']['menu_slug'] . '" role="tab">
											<i class="' . $value['module_menu_details']['tab_html_class'] . '" aria-hidden="true"></i> ' . $value['module_menu_details']['menu_title'] . '
										</a>
									</li>';
								endif;
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
}
