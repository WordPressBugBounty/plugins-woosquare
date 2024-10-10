<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       wpexperts.io
 * @since      1.0.0
 *
 * @package    Woosquare_Plus
 * @subpackage Woosquare_Plus/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Woosquare_Plus
 * @subpackage Woosquare_Plus/admin
 * @author     Wpexpertsio <support@wpexperts.io>
 */
class Woosquare_Plus_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Woosquare_Plus_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Woosquare_Plus_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/woosquare-plus-admin.css', array(), $this->version, 'all' );

		// <!-- Font Awesome -->
		wp_enqueue_style( 'wosquareplus_font_awesome', 'https://use.fontawesome.com/releases/v5.8.2/css/all.css', array(), $this->version, 'all' );
		// <!-- Bootstrap core CSS -->
		wp_enqueue_style( 'wosquareplus_bootstrap', plugin_dir_url( __FILE__ ) . 'css/material/css/bootstrap.min.css', array(), $this->version, 'all' );
		// material style.
		wp_enqueue_style( 'wosquareplus_js_scrolltab', 'https://rawgit.com/mikejacobson/jquery-bootstrap-scrolling-tabs/master/dist/jquery.scrolling-tabs.min.css', array(), $this->version, 'all' );
		// Custom css for admin.
		wp_enqueue_style( 'woosquare_plus_admin_custom', plugin_dir_url( __FILE__ ) . 'css/woosquare-plus-admin-custom.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Woosquare_Plus_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Woosquare_Plus_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/woosquare-plus-admin.js', array( 'jquery' ), $this->version, false );
		$localize_array = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'my_woosquare_ajax_nonce' ),
		);
		wp_localize_script( $this->plugin_name, 'my_ajax_backend_scripts', $localize_array );

		// <!-- Bootstrap tooltips -->
		wp_enqueue_script( 'wosquareplus_bootstrap_tooltips_js', 'https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.4/umd/popper.min.js', array( 'jquery' ), $this->version, false );
		// <!-- Bootstrap core JavaScript -->
		wp_enqueue_script( 'wosquareplus_bootstrap_js', 'https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/js/bootstrap.min.js', array( 'jquery' ), $this->version, false );
		// <!-- MDB core JavaScript -->

		// <!-- Scrolltab JavaScript -->
		wp_enqueue_script( 'wosquareplus_scrolltab_js', 'https://rawgit.com/mikejacobson/jquery-bootstrap-scrolling-tabs/master/dist/jquery.scrolling-tabs.min.js', array( 'jquery' ), $this->version, false );

		// <!-- waves JavaScript -->
		wp_enqueue_script( 'wosquareplus_waves_js', plugin_dir_url( __FILE__ ) . 'js/waves.js', array( 'jquery' ), $this->version, false );

		// <!-- custom JavaScript -->
		wp_enqueue_script( 'wosquareplus_custom_js', plugin_dir_url( __FILE__ ) . 'js/custom.min.js', array( 'jquery' ), $this->version, false );
	}

	/**
	 * Register the Menus for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function woosquare_plus_menus() {

		$plugin_modules = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );

		add_menu_page( 'WC Shop Sync Settings', 'WC Shop Sync Settings', 'manage_options', 'square-settings', array( &$this, 'square_auth_page' ), plugin_dir_url( __FILE__ ) . '/img/square.png' );
		$this->check_for_auth();
		
		if ( ! empty( $plugin_modules['module_page'] ) ) {
			foreach ( $plugin_modules as $key => $value ) {
				if ( $value['module_activate'] ) {

					if ( ! empty( get_option( 'woo_square_access_token_cauth' . get_transient( 'is_sandbox' ) ) ) && ! empty( get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ) ) ) {
						if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
							$active_option = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
							if ( $active_option['module_page'] ) {
								do_action( 'delete_option', $active_option['module_page'] );
							}
						} elseif ( empty( $value['is_premium'] ) ) {
								add_submenu_page( $value['module_menu_details']['parent_slug'], $value['module_menu_details']['page_title'], $value['module_menu_details']['menu_title'], $value['module_menu_details']['capability'], $value['module_menu_details']['menu_slug'], array( &$this, $value['module_menu_details']['function_callback'] ) );
						}
					}
				}
			}
			add_submenu_page( 'square-settings', 'Documentation Plus', 'Documentation', 'manage_options', 'square-documentation', array( &$this, 'documentation_plugin_page' ) );
		}
	}


	/**
	 * Check if the user is authenticated.
	 *
	 * This function checks whether the user is authenticated and returns a boolean
	 * value indicating their authentication status.
	 */
	public function check_for_auth() {

		if (
				! empty( $_REQUEST['access_token'] ) &&
				! empty( $_REQUEST['token_type'] ) &&
				sanitize_text_field( wp_unslash( $_REQUEST['token_type'] ) ) === 'bearer'
		) {

			if ( ! isset( $_GET['wc_woosquare_token_nonce'] ) || function_exists( 'wp_verify_nonce' ) &&
				! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['wc_woosquare_token_nonce'] ) ), 'connect_woosquare' )
			) {
				wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
			}

			$existing_token = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
			// if token already exists, don't continue.

			update_option( 'woo_square_auth_response' . get_transient( 'is_sandbox' ), array_map( 'sanitize_text_field', $_REQUEST ) );
			update_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ), sanitize_text_field( wp_unslash( $_REQUEST['access_token'] ) ) );
			update_option( 'woosquare_plus_reauth_notification' . get_transient( 'is_sandbox' ), sanitize_text_field( wp_unslash( $_REQUEST['access_token'] ) ) );
			if ( isset( $_REQUEST['refresh_token'] ) ) {
				update_option( 'woo_square_refresh_token' . get_transient( 'is_sandbox' ), sanitize_text_field( wp_unslash( $_REQUEST['refresh_token'] ) ) );
			}
			update_option( 'woo_square_access_token_cauth' . get_transient( 'is_sandbox' ), sanitize_text_field( wp_unslash( $_REQUEST['access_token'] ) ) );
			$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );

			$results = $square->get_all_locations();

			if ( ! empty( $results['locations'] ) ) {
				foreach ( $results['locations'] as $result ) {
					$locations = $result;
					if ( ! empty( $locations['capabilities'] ) ) {
						$caps = ' | ' . implode( ',', $locations['capabilities'] ) . ' ENABLED';
					}
					$location_id = ( $locations['id'] );
					$str[]       = array(
						$location_id => $locations['name'] . ' ' . str_replace( '_', ' ', $caps ),
					);
				}
				update_option( 'woo_square_locations' . get_transient( 'is_sandbox' ), $str );
				update_option( 'woo_square_business_name', $locations['name'] );
				if ( count( $results['locations'] ) === 1 ) {
					update_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ), $location_id );

				}
			}

			$square->authorize();
			wp_safe_redirect(
				add_query_arg(
					array(
						'page' => 'square-settings',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		if (
				! empty( $_REQUEST['disconnect_woosquare'] ) &&
				! empty( $_REQUEST['wc_woosquare_token_nonce'] )
		) {
			if ( ! isset( $_REQUEST['wc_woosquare_token_nonce'] ) || function_exists( 'wp_verify_nonce' ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['wc_woosquare_token_nonce'] ) ), 'disconnect_woosquare' ) ) {
				wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woocommerce-square' ) ) );
			}

			// revoke token.
			$oauth_connect_url = WOOSQU_PLUS_CONNECTURL;
			$headers           = array(
				'Authorization' => 'Bearer ' . get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), // Use verbose mode in cURL to determine the format you want for this header.
				'Content-Type'  => 'application/json;',
			);
			$redirect_url      = add_query_arg(
				array(
					'page'     => 'wc-settings',
					'tab'      => 'checkout',
					'section'  => 'square-recurring',
					'app_name' => WOOSQU_PLUS_APPNAME,
					'plug'     => WOOSQU_PLUS_PLUGIN_NAME,
				),
				admin_url( 'admin.php' )
			);

			$redirect_url = wp_nonce_url( $redirect_url, 'connect_wcsrs', 'wc_wcsrs_token_nonce' );
			$site_url     = rawurlencode( $redirect_url );
			$args_renew   = array(
				'body'      => array(
					'header'   => $headers,
					'action'   => 'revoke_token',
					'site_url' => $site_url,
				),
				'timeout'   => 45,
				'sslverify' => false,
			);

			$oauth_response = wp_remote_post( $oauth_connect_url, $args_renew );

			$decoded_oauth_response = json_decode( wp_remote_retrieve_body( $oauth_response ) );

			delete_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
			delete_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
			delete_option( 'woo_square_location_id_free' . get_transient( 'is_sandbox' ) );
			delete_option( 'woo_square_access_token_cauth' . get_transient( 'is_sandbox' ) );
			delete_option( 'woo_square_locations_free' . get_transient( 'is_sandbox' ) );
			delete_option( 'woo_square_business_name_free' . get_transient( 'is_sandbox' ) );
			wp_safe_redirect(
				add_query_arg(
					array(
						'page' => 'square-settings',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
	}

	/**
	 * Perform an action in the English plugin.
	 *
	 * This function is responsible for performing a specific action related to the
	 * English plugin. Provide a brief description of the action being performed
	 * and any relevant details about the function's behavior.
	 *
	 * @return void
	 */
	public function en_plugin_act() {

		$plugin_modules = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );
		if ( ! isset( $_POST['nonce'] ) || function_exists( 'wp_verify_nonce' ) &&
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'my_woosquare_ajax_nonce' )
		) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		if (
			! empty( $_POST['action'] ) && ! empty( $_POST['status'] )
			&& 'en_plugin' === sanitize_text_field( wp_unslash( $_POST['action'] ) )
			&& ! empty( $plugin_modules )
			&& 'enab' === sanitize_text_field( wp_unslash( $_POST['status'] ) )
		) {
			if ( isset( $_POST['pluginid'] ) && sanitize_text_field( wp_unslash( $_POST['pluginid'] ) ) ) {
				$plugin_id                                       = str_replace( 'myonoffswitch_', '', sanitize_text_field( wp_unslash( $_POST['pluginid'] ) ) );
				$plugin_modules[ $plugin_id ]['module_activate'] = false;
				update_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), $plugin_modules );
			}

			// below condition for when payment gateway disabled sandbox condition also disabled so it will not conflicts with other features..
			if ( 'woosquare_payment' === $plugin_id ) {
				$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
				if ( 'yes' === $woocommerce_square_plus_settings['enabled'] ) {
					$woocommerce_square_plus_settings['enabled'] = 'no';
				}

				update_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings', $woocommerce_square_plus_settings );
			}
			if ( 'items_sync' === $plugin_id ) {
				if ( isset( $plugin_modules['items_sync_log']['module_activate'] ) && true === $plugin_modules['items_sync_log']['module_activate'] ) {
					$plugin_id = str_replace( 'myonoffswitch_', '', sanitize_text_field( wp_unslash( $_POST['pluginid'] ) ) );
					$plugin_modules['items_sync_log']['module_activate'] = false;
					update_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), $plugin_modules );
				}
			}
			$msg = wp_json_encode(
				array(
					'status' => true,
					'msg'    => 'Addon Successfully Disabled!',
				)
			);

		} elseif (
				! empty( $_POST['action'] ) && ! empty( $_POST['status'] )
				&& 'en_plugin' === sanitize_text_field( wp_unslash( $_POST['action'] ) )
				&& ! empty( $plugin_modules )
				&& 'disab' === sanitize_text_field( wp_unslash( $_POST['status'] ) )
			) {

			if ( isset( $_POST['pluginid'] ) && sanitize_text_field( wp_unslash( $_POST['pluginid'] ) ) ) {
				$plugin_id                                       = str_replace( 'myonoffswitch_', '', sanitize_text_field( wp_unslash( $_POST['pluginid'] ) ) );
				$plugin_modules[ $plugin_id ]['module_activate'] = true;
				update_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), $plugin_modules );
			}
			$msg = wp_json_encode(
				array(
					'status' => true,
					'msg'    => 'Addon Successfully Enabled!',
				)
			);
			if ( 'items_sync_log' === $plugin_id ) {
				if ( isset( $plugin_modules['items_sync']['module_activate'] ) && true !== $plugin_modules['items_sync']['module_activate'] ) {
					$plugin_id                                       = str_replace( 'myonoffswitch_', '', sanitize_text_field( wp_unslash( $_POST['pluginid'] ) ) );
					$plugin_modules[ $plugin_id ]['module_activate'] = false;
					update_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), $plugin_modules );

					$msg = wp_json_encode(
						array(
							'status' => false,
							'msg'    => __( 'To use "Logs of Sync Products" module "Synchronization of Products" module must be enabled first!', 'woosquare' ),
						)
					);
				}
			}
		}

		echo wp_kses_post( $msg );
		set_transient( 'woosquare_plus_notification', $msg, 12 * HOUR_IN_SECONDS );
		die();
	}

	/**
	 * Notify WooCommerce Square Plus integration.
	 *
	 * This function is responsible for sending a notification related to the
	 * WooCommerce Square Plus integration. You can provide a brief description
	 * of the notification being sent and any relevant details about the function's behavior.
	 *
	 * @return void
	 */
	public function woosquare_plus_notify() {
		$woosquare_plus_notification = json_decode( get_transient( 'woosquare_plus_notification' ) );
		if ( $woosquare_plus_notification->status ) {
			$ss = 'success';
		} else {
			$ss = 'error';
		}
		$class        = 'notice notice-' . $ss;
		$message      = ( $woosquare_plus_notification->msg );
		$allowed_html = array(
			'a'      => array(
				'a'     => true,
				'href'  => true,
				'class' => true,
			),
			'strong' => array(),
		);
		printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_html( $ss ), wp_kses( $message, $allowed_html ) );

		$woo_square_auth_response = get_option( 'woo_square_auth_response' . get_transient( 'is_sandbox' ) );
		if ( is_object( $woo_square_auth_response ) ) {
			$woo_square_auth_response = (array) $woo_square_auth_response;
		}

		if ( isset( $woo_square_auth_response['expires_at'] ) && ( ( strtotime( $woo_square_auth_response['expires_at'] ) + 6000 ) <= time() ) ) {
			// delete oauth account to avoid refresh request.
			delete_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
			delete_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
			delete_option( 'woo_square_location_id_free' . get_transient( 'is_sandbox' ) );
			delete_option( 'woo_square_access_token_cauth' . get_transient( 'is_sandbox' ) );
			delete_option( 'woo_square_locations_free' . get_transient( 'is_sandbox' ) );
			delete_option( 'woo_square_business_name_free' . get_transient( 'is_sandbox' ) );
		} else {
			delete_transient( 'woosquare_plus_notification' );
		}
	}

	/**
	 * Check if an order requires payment processing for WooCommerce Square Plus integration.
	 *
	 * This function is responsible for determining whether an order placed through
	 * WooCommerce needs to be processed for payment using the Square Plus integration.
	 * It checks various conditions and returns a boolean value indicating whether payment
	 * processing is necessary.
	 */
	public function woosquare_plus_payment_order_check() {
		 
		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
		$activate_modules_woosquare_plus  = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );

		if (
				empty( get_option( 'woo_square_access_token_cauth' . get_transient( 'is_sandbox' ) ) ) || empty( get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ) )
		) {
			if (
				isset( $_POST['woo_square_settings'] ) && 1 !== sanitize_text_field( wp_unslash( $_POST['woo_square_settings'] ) )
			) {
				$class       = 'notice notice-error';
				$connectlink = get_admin_url() . 'admin.php?page=square-settings';

				printf( '<div class="notice notice-error"><p>%1$s <a href="%2$s">%3$s</a> %4$s</p></div>', esc_html__( 'You must', 'woosquare-square' ), esc_url( $connectlink ), esc_html__( 'Connect your Square account', 'woosquare-square' ), esc_html__( 'and select location in order to use WC Shop Sync functionality', 'woosquare-square' ) );

			}
		}
	}


	/**
	 * Settings page action
	 */
	public function square_auth_page() {

		if ( isset( $_POST['woosquare_setting_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woosquare_setting_nonce'] ) ), 'woosquare-setting-nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		$this->check_or_add_plugin_tables();
		$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );

		$error_message   = '';
		$success_message = '';

		// check if the location is not setuped.
		if ( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) && ! get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ) ) {
			$square->authorize();
		}
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) {

			// setup account.
			if ( isset( $_POST[ 'woo_square_access_token' . get_transient( 'is_sandbox' ) ] ) ) {

				$woo_square_access_token = sanitize_text_field( wp_unslash( $_POST[ 'woo_square_access_token' . get_transient( 'is_sandbox' ) ] ) );
				$woo_square_app_id       = ( isset( $_POST['woo_square_app_id'] ) ? sanitize_text_field( wp_unslash( $_POST['woo_square_app_id'] ) ) : '' );
				$square->set_access_token( $woo_square_access_token );
				$square->setapp_id( $woo_square_app_id );
				if ( $square->authorize() ) {
					$success_message = __( 'Settings updated successfully!' );
				} else {
					$error_message = __( 'Square Account Not Authorized' );
				}
			}

			// save settings.
			if ( isset( $_POST['woo_square_settings'] ) ) {
				// update location id.
				if ( ! empty( $_POST[ 'woo_square_location_id' . get_transient( 'is_sandbox' ) ] ) ) {

					$location_id       = sanitize_text_field( wp_unslash( $_POST[ 'woo_square_location_id' . get_transient( 'is_sandbox' ) ] ) );
					$woo_square_app_id = defined( 'WOOSQU_PLUS_APPID' ) ? sanitize_text_field( WOOSQU_PLUS_APPID ) : '';
					update_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ), $location_id );
					$square->set_location_id( $location_id );

				}
				$success_message = 'Settings updated successfully!';
			}
		}
		$woo_currency_code    = get_option( 'woocommerce_currency' );
		$square_currency_code = get_option( 'woo_square_account_currency_code' );

		if ( ! $square_currency_code ) {
			$square->get_currency_code();
			$square->getapp_id();
			$square_currency_code = get_option( 'woo_square_account_currency_code' );
		}

		$currency_mismatch_flag = ( $woo_currency_code !== $square_currency_code );

		include WOO_SQUARE_PLUS_PLUGIN_PATH . 'admin/partials/settings.php';
	}

	/**
	 * Documentation_plugin_page
	 */
	public function documentation_plugin_page() {
		header( 'Location: https://apiexperts.io/documentation/apiexperts-square-for-woocommerce/' );
		wp_die();
	}

	/**
	 * Display the WC Shop Sync Plus module page.
	 *
	 * This function retrieves the activated modules, enqueues necessary styles, and includes the module views.
	 *
	 * @since 1.0.0
	 */
	public function woosquare_plus_module_page() {
		$plugin_modules = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );
		unset( $plugin_modules['module_page'] );
		wp_enqueue_style( 'bootstrap', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css', array(), '4.4.1' );
		include WOO_SQUARE_PLUS_PLUGIN_PATH . 'admin/partials/module-views.php';
	}
	/**
	 * Check_or_add_plugin_tables
	 */
	public function check_or_add_plugin_tables() {
		// create tables.
		require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		global $wpdb;
		$get_var = 'get_var';
		// deleted products table.
		$del_prod_table = $wpdb->prefix . WOO_SQUARE_TABLE_DELETED_DATA;
		if ( $wpdb->$get_var( "SHOW TABLES LIKE '$del_prod_table'" ) !== $del_prod_table ) {

			if ( ! empty( $wpdb->charset ) ) {
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			}
			if ( ! empty( $wpdb->collate ) ) {
				$charset_collate .= " COLLATE $wpdb->collate";
			}

			$sql = 'CREATE TABLE ' . $del_prod_table . " (
				`square_id` varchar(50) NOT NULL,
							`target_id` bigint(20) NOT NULL,
							`target_type` tinyint(2) NULL,
							`name` varchar(255) NULL,
				PRIMARY KEY (`square_id`)
			) $charset_collate;";
			dbDelta( $sql );
		}

		// logs table.
		$sync_logs_table = $wpdb->prefix . WOO_SQUARE_TABLE_SYNC_LOGS;

		if ( $wpdb->$get_var( "SHOW TABLES LIKE '$sync_logs_table'" ) !== $sync_logs_table ) {

			if ( ! empty( $wpdb->charset ) ) {
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			}
			if ( ! empty( $wpdb->collate ) ) {
				$charset_collate .= " COLLATE $wpdb->collate";
			}

			$sql = 'CREATE TABLE ' . $sync_logs_table . " (
						`id` bigint(20) auto_increment NOT NULL,
						`target_id` bigint(20) NULL,
						`target_type` tinyint(2) NULL,
						`target_status` tinyint(1) NULL,
						`parent_id` bigint(20) NOT NULL default '0',
						`square_id` varchar(50) NULL,
						`action`  tinyint(3) NOT NULL,
						`date` TIMESTAMP NOT NULL,
						`sync_type` tinyint(1) NULL,
						`sync_direction` tinyint(1) NULL,
						`name` varchar(255) NULL,
						`message` text NULL,
						PRIMARY KEY (`id`)
				) $charset_collate;";
			dbDelta( $sql );
		}
	}

	/**
	 * Callback Functions
	 */
	public function square_item_sync_page() {
		if ( function_exists( 'woo_square_script' ) ) {
			woo_square_script();
		}

		if ( function_exists( 'square_settings_page' ) ) {
			square_settings_page();
		}
	}

	/**
	 * Callback Functions
	 */
	public function square_item_sync_log_page() {
		if ( function_exists( 'woo_square_script' ) ) {
			woo_square_script();
		}
		$this->square_sync_log_plugin_page();
	}

	/**
	 * Callback Functions
	 */
	public function square_payment_sync_page() {

		if ( function_exists( 'woo_square_script' ) ) {
			woo_square_script();
		}
		$this->square_payment_plugin_page();
	}

	/**
	 * Renders the plugin settings page for Square sync logs.
	 *
	 * @since 1.0.0
	 *
	 * @global $wpdb WordPress database abstraction object.
	 *
	 * @return void
	 */
	public function square_sync_log_plugin_page() {
		include plugin_dir_path( __FILE__ ) . 'modules/square-sync-logs/views/log-settings.php';
	}

	/**
	 * Square payment plugin page action
	 *
	 * @global type $wpdb
	 */
	public function square_payment_plugin_page() {
		$square_payment_settin             = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
		$square_payment_setting_google_pay = get_option( 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_settings' );

		if ( ! empty( $square_payment_setting_google_pay ) && 'yes' === $square_payment_setting_google_pay['enabled'] ) {
			$square_payment_setting_google_pay['enabled'] = 'yes';
		} else {
			$square_payment_setting_google_pay            = array();
			$square_payment_setting_google_pay['enabled'] = 'no';
		}

		$woocommerce_square_apple_pay_enabled = get_option( 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( ! empty( $woocommerce_square_apple_pay_enabled ) && 'yes' === $woocommerce_square_apple_pay_enabled['enabled'] ) {
			$woocommerce_square_apple_pay_enabled['enabled'] = 'yes';
		} else {
			$woocommerce_square_apple_pay_enabled            = array();
			$woocommerce_square_apple_pay_enabled['enabled'] = 'no';
		}

		$woocommerce_square_ach_payment_settings = get_option( 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( ! empty( $woocommerce_square_ach_payment_settings ) && 'yes' === $woocommerce_square_ach_payment_settings['enabled'] ) {
			$woocommerce_square_ach_payment_settings['enabled'] = 'yes';
		} else {
			$woocommerce_square_ach_payment_settings            = array();
			$woocommerce_square_ach_payment_settings['enabled'] = 'no';
		}

		$woocommerce_square_cash_app_pay_settings = get_option( 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( ! empty( $woocommerce_square_cash_app_pay_settings ) && 'yes' === $woocommerce_square_cash_app_pay_settings['enabled'] ) {
			$woocommerce_square_cash_app_pay_settings['enabled'] = 'yes';
		} else {
			$woocommerce_square_cash_app_pay_settings            = array();
			$woocommerce_square_cash_app_pay_settings['enabled'] = 'no';
		}

		$woocommerce_square_after_pay_settings = get_option( 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( ! empty( $woocommerce_square_after_pay_settings ) && 'yes' === $woocommerce_square_after_pay_settings['enabled'] ) {
			$woocommerce_square_after_pay_settings['enabled'] = 'yes';
		} else {
			$woocommerce_square_after_pay_settings            = array();
			$woocommerce_square_after_pay_settings['enabled'] = 'no';
		}

		$woocommerce_square_payment_reporting = get_option( 'woocommerce_square_payment_reporting' );

			include plugin_dir_path( __FILE__ ) . 'modules/square-payments/views/payment-settings.php';
	}
}
