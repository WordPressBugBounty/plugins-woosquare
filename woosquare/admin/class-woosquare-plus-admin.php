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
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		// Add AJAX actions.
		add_action( 'wp_ajax_clear_woosquare_logs', array( $this, 'clear_woosquare_logs' ) );
		add_action( 'wp_ajax_save_woosquare_alerts', array( $this, 'save_woosquare_alerts' ) );
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
		// Scrolling-tabs (local – plugin assets).
		wp_enqueue_style( 'wosquareplus_js_scrolltab', plugin_dir_url( __FILE__ ) . 'css/jquery.scrolling-tabs.min.css', array(), $this->version, 'all' );
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

		// Scrolltab JavaScript (local – plugin assets). 
		wp_enqueue_script( 'wosquareplus_scrolltab_js', plugin_dir_url( __FILE__ ) . 'js/jquery.scrolling-tabs.min.js', array( 'jquery' ), $this->version, false );
		// <!-- waves JavaScript -->
		wp_enqueue_script( 'wosquareplus_waves_js', plugin_dir_url( __FILE__ ) . 'js/waves.js', array( 'jquery' ), $this->version, false );

		// <!-- custom JavaScript -->
		wp_enqueue_script( 'wosquareplus_custom_js', plugin_dir_url( __FILE__ ) . 'js/custom.min.js', array( 'jquery' ), $this->version, false );
	}


	/**
	 * Check Square scope.
	 *
	 * @return void
	 */
	public function check_square_scope() {
		// Access token.
		$access_token = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		if ( ! empty( $access_token ) ) {
			// List of required scopes to check.
			$required_scopes = explode( ',', WOOSQU_PLUS_SCOPES );
			// Make the API request to Square to verify token scopes.
			$response = $this->verify_square_scopes( $access_token );

			// If the response is valid and required scopes are missing, show the notice.
			if ( $response ) {
				$missing_scopes = $this->check_required_scopes( $required_scopes, $response );
				add_action(
					'admin_notices',
					function () use ( $missing_scopes ) {
						$this->display_scope_notice( $missing_scopes );
					}
				);
			}
		}
	}

	/**
	 * Function to verify if all required scopes are present.
	 *
	 * @param array $required_scopes Required scopes.
	 * @param array $scopes          Available scopes.
	 * @return array Missing scopes.
	 */
	public function check_required_scopes( $required_scopes, $scopes ) {

		// Convert the scopes string into an array.
		// Check if each required scope exists in the available scopes.
		$required_scopes_notice = array();
		foreach ( $required_scopes as $scope ) {
			if ( ! in_array( $scope, $scopes, true ) ) {
				$required_scopes_notice[] = $scope;
				// Return false if any required scope is missing.
			}
		}

		return $required_scopes_notice; // Return true if all required scopes exist.
	}

	/**
	 * Display scope notice.
	 *
	 * @param array $missing_scopes Missing scopes.
	 * @return void
	 */
	public function display_scope_notice( $missing_scopes ) {
		// Remove underscores from the missing scopes and prepare a list.
		$missing_scopes_list = implode(
			', ',
			array_map(
				function ( $scope ) {
					return str_replace( '_', ' ', $scope );
				},
				$missing_scopes
			)
		);

		// If no missing scopes, do not display the notice.
		if ( empty( $missing_scopes_list ) ) {
			return;
		}
		// URL for the Square connect/disconnect page (you need to adjust this URL to your plugin's connect page).
		$connect_disconnect_url = admin_url( 'admin.php?page=square-settings' ); // Change this URL to the actual page.

		// Display a simple and user-friendly notice.
		echo '<div class="notice notice-error is-dismissible">';
			echo '<p><strong>Important:</strong> To enable the full functionality, the following permissions are required: ' . esc_html( $missing_scopes_list ) . '.</p>';
			echo '<p>Please <a href="' . esc_url( $connect_disconnect_url ) . '" target="_blank"><b>disconnect and reconnect Square</b></a> to grant these permissions.</p>';
		echo '</div>';
	}


	/**
	 * Verify Square scopes.
	 *
	 * @param string $access_token Access token.
	 * @return array|false Scopes array or false on failure.
	 */
	public function verify_square_scopes( $access_token ) {
		$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/oauth2/token/status';
		// Send POST request to Square's API.
		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Square-Version' => '2024-11-20',
					'Authorization'  => 'Bearer ' . $access_token,
					'Content-Type'   => 'application/json',
				),
			)
		);

		// Check for errors in the request.
		if ( is_wp_error( $response ) ) {
			return false;
		}

		// Parse the response body.
		$body          = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $body, true );

		// Return the scopes from the response.
		return isset( $response_data['scopes'] ) ? $response_data['scopes'] : '';
	}


	/**
	 * Register the Menus for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function woosquare_plus_menus() {

		$plugin_modules = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );

		add_menu_page( 'Woo Square Settings', WOOSQU_PLUS_LABEL, 'manage_options', 'square-settings', array( &$this, 'square_auth_page' ), plugin_dir_url( __FILE__ ) . 'img/square.png' );
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
						} else {
							add_submenu_page( $value['module_menu_details']['parent_slug'], $value['module_menu_details']['page_title'], $value['module_menu_details']['menu_title'], $value['module_menu_details']['capability'], $value['module_menu_details']['menu_slug'], array( &$this, $value['module_menu_details']['function_callback'] ) );
						}
					}
				}
			}
			add_submenu_page( 'square-settings', 'Documentation Plus', 'Documentation', 'manage_options', 'square-documentation', array( &$this, 'documentation_plugin_page' ) );
		}
		
		// Add Order Sync Logs page only when order-sync-debug=true in URL. Capability check is sufficient for admin menu.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['order-sync-debug'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['order-sync-debug'] ) ) ) {
			
			add_submenu_page( 'square-settings', 'Order Sync Logs', 'Order Sync Logs', 'manage_options', 'square-order-sync-logs', array( &$this, 'square_order_sync_logs_page' ) );
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

			if ( ! isset( $_GET['wc_woosquare_token_nonce'] ) ||
				( function_exists( 'wp_verify_nonce' ) &&
				! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['wc_woosquare_token_nonce'] ) ), 'connect_woosquare' ) )
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
			update_option( 'woo_square_update_msg_dissmiss' . get_transient( 'is_sandbox' ), 'connected' );
			delete_option( 'woo_square_auth_notice' . get_transient( 'is_sandbox' ) );

			$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );

			$results = $square->get_all_locations();

			if ( ! empty( $results['locations'] ) ) {
				foreach ( $results['locations'] as $result ) {
					$locations = $result;
					if ( ! empty( $locations['capabilities'] ) ) {
						$caps = ' | ' . implode( ',', $locations['capabilities'] ) . ' ENABLED';
					}
					$location_id = ( $locations['id'] );
					if ( 'ACTIVE' === $locations['status'] ) {
						$str[] = array(
							$location_id => $locations['name'] . ' ' . str_replace( '_', ' ', $caps ),
						);
					}
				}
				update_option( 'woo_square_locations' . get_transient( 'is_sandbox' ), $str );
				update_option( 'woo_square_business_name' . get_transient( 'is_sandbox' ), $locations['name'] );
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
			if ( ! isset( $_REQUEST['wc_woosquare_token_nonce'] ) ||
				( function_exists( 'wp_verify_nonce' ) &&
				! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['wc_woosquare_token_nonce'] ) ), 'disconnect_woosquare' ) )
			) {
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
			$site_url     = ( rawurlencode( $redirect_url ) );
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
	 * Creates the logs table for WooCommerce Square sync if it doesn't exist.
	 *
	 * This function checks if the table for storing sync logs exists in the database.
	 * If not, it creates the table with necessary columns such as log_time, status, message,
	 * sync_direction, item, environment, and data. The table is created with the proper character
	 * set and collation for the WordPress database.
	 *
	 * @return void
	 */
	public function wcsyn_create_logs_db() {
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

	/**
	 * Checks if the WooCommerce Square integration table exists and creates it if not.
	 *
	 * @return void
	 */
	public function wcsyn_create_integration_db() {
		// create tables.
		require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		global $wpdb;

		// deleted products table.
		$del_prod_table = $wpdb->prefix . WOO_SQUARE_TABLE_DELETED_DATA;
		$get_var        = 'get_var';
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
		if ( ! isset( $_POST['nonce'] ) ||
			( function_exists( 'wp_verify_nonce' ) &&
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'my_woosquare_ajax_nonce' ) )
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
				$plugin_modules[ $plugin_id ]['module_activate'] = true;

				// Force update using direct DB to ensure it returns true
				$option_key = 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' );
				update_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), $plugin_modules );
				wp_cache_delete( $option_key, 'options' );

			}
			// below condition for when payment gateway disabled sandbox condition also disabled so it will not conflicts with other features..
			if ( 'woosquare_payment' === $plugin_id ) {
				$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings', array() );

				if ( ! is_array( $woocommerce_square_plus_settings ) ) {
					$woocommerce_square_plus_settings = array();
				}

				$gateway_enabled = isset( $woocommerce_square_plus_settings['enabled'] ) ? $woocommerce_square_plus_settings['enabled'] : 'no';

				if ( 'yes' === $gateway_enabled ) {
					$woocommerce_square_plus_settings['enabled'] = 'no';
				}

				update_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings', $woocommerce_square_plus_settings );
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
				$plugin_modules[ $plugin_id ]['module_activate'] = false;
				
				// Force update using direct DB to ensure it returns true
				$option_key = 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' );
				update_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), $plugin_modules );
				wp_cache_delete( $option_key, 'options' );
				
			}
			$msg = wp_json_encode(
				array(
					'status' => true,
					'msg'    => 'Addon Successfully Enabled!',
				)
			);

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
		$class   = 'notice notice-' . $ss;
		$message = ( $woosquare_plus_notification->msg );
		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
		delete_transient( 'woosquare_plus_notification' );
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
				isset( $_POST['woo_square_settings'] ) && 1 !== sanitize_text_field( wp_unslash( $_POST['woo_square_settings'] ) ) // phpcs:ignore
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
		$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );

		$error_message   = '';
		$success_message = '';

		// check if the location is not setuped.
		if ( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) ) {
			if ( ! empty( get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ) ) ) {
				$square->get_currency_code();
			}
			if ( empty( get_option( 'woo_square_locations' . get_transient( 'is_sandbox' ) ) ) ) {
				$square->authorize();
			}
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

				}
				$success_message = 'Settings updated successfully!';
			}
		}
		$woo_currency_code    = get_option( 'woocommerce_currency' );
		$square_currency_code = get_option( 'woo_square_account_currency_code' );

		if ( ! $square_currency_code ) {
			$square->getapp_id();
			$square_currency_code = get_option( 'woo_square_account_currency_code' );
		}
		if ( $currency_mismatch_flag = ( $woo_currency_code != $square_currency_code ) ) { // phpcs:ignore
		}

		include WOO_SQUARE_PLUS_PLUGIN_PATH . 'admin/partials/settings.php';
	}

	/**
	 * Documentation_plugin_page
	 */
	public function documentation_plugin_page() {
		header( 'Location: https://apiexperts.io/woosquare-plus-documentation/?utm_source=WordPress&utm_medium=PluginSale&utm_campaign=InStore' );
		wp_die();
	}

	/**
	 * Square Order Sync Logs page - Display debug logs
	 */
	public function square_order_sync_logs_page() {
		// Ensure page opens with order-sync-debug=true (redirect if missing).
		if ( ! isset( $_GET['order-sync-debug'] ) || 'true' !== sanitize_text_field( wp_unslash( $_GET['order-sync-debug'] ) ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'              => 'square-order-sync-logs',
						'order-sync-debug' => 'true',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		// Order sync log path (separate file). 
		$order_sync_log_path = WP_CONTENT_DIR . '/woosquare-order-sync.log';
		$logging_enabled     = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
		?>
		<div class="wrap">
			<h1>Square Order Sync Debug Logs</h1>
			<style>
				.woosquare-log-container { background: white; padding: 20px; border-radius: 5px; max-width: 1400px; margin: 20px 0; }
				.woosquare-log-section { background: #f9f9f9; padding: 15px; margin: 10px 0; border-left: 4px solid #0073aa; }
				.woosquare-log-entry { padding: 5px; margin: 5px 0; font-family: monospace; font-size: 12px; }
				.woosquare-log-error { color: #d63638; }
				.woosquare-log-success { color: #00a32a; }
				.woosquare-log-info { color: #2271b1; }
				.woosquare-log-section h2 { color: #1d2327; margin-top: 0; }
				.woosquare-log-section pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; overflow-x: auto; border-radius: 3px; max-height: 600px; overflow-y: auto; }
				.refresh-btn { margin: 10px 0; }
				.copy-btn { margin-left: 6px; }
			</style>
			<div class="woosquare-log-container">
				<div class="refresh-btn">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=square-order-sync-logs&order-sync-debug=true' ) ); ?>" class="button button-primary">Refresh Logs</a>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=square-order-sync-logs&order-sync-debug=true&clear_logs=1' ), 'clear_square_logs', 'clear_logs_nonce' ) ); ?>" class="button button-secondary" onclick="return confirm('Are you sure you want to clear ALL debug logs? This action cannot be undone.');">Clear All Logs</a>
					<button type="button" class="button button-secondary copy-btn" id="woosquare-copy-logs">Copy Logs</button>
				</div>

				<?php
				// Handle log clearing. Nonce verified below.
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( isset( $_GET['clear_logs'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['clear_logs'] ) ) && current_user_can( 'manage_options' ) ) {
					// Verify nonce
					if ( isset( $_GET['clear_logs_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['clear_logs_nonce'] ) ), 'clear_square_logs' ) ) {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Admin log clear; single file, capability + nonce checked.
						if ( file_exists( $order_sync_log_path ) && is_writable( $order_sync_log_path ) ) {
							// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
							file_put_contents( $order_sync_log_path, '' );
							echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Order sync log cleared successfully!</p></div>';
						} else {
							echo '<div class="notice notice-error is-dismissible"><p><strong>Error!</strong> Could not clear order sync log. File may not exist or is not writable.</p></div>';
						}
					} else {
						echo '<div class="notice notice-error is-dismissible"><p><strong>Error!</strong> Security check failed. Please try again.</p></div>';
					}
				}

				// Check WordPress debug settings
				echo '<div class="woosquare-log-section">';
				echo '<h2>WordPress Debug Settings</h2>';
				echo '<div class="woosquare-log-entry">WP_DEBUG: ' . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? '<span class="woosquare-log-success">ENABLED</span>' : '<span class="woosquare-log-error">DISABLED</span>' ) . '</div>';
				echo '<div class="woosquare-log-entry">WP_DEBUG_LOG: ' . ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? '<span class="woosquare-log-success">ENABLED</span>' : '<span class="woosquare-log-error">DISABLED</span>' ) . '</div>';
				echo '<div class="woosquare-log-entry">Order Sync Logging: ' . ( $logging_enabled ? '<span class="woosquare-log-success">ENABLED</span>' : '<span class="woosquare-log-error">DISABLED</span>' ) . ' <span class="woosquare-log-info">(requires WP_DEBUG_LOG)</span></div>';
				echo '<div class="woosquare-log-entry">WP_DEBUG_DISPLAY: ' . ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ? '<span class="woosquare-log-success">ENABLED</span>' : '<span class="woosquare-log-error">DISABLED</span>' ) . '</div>';
				echo '</div>'; 

				// Check log file location
				echo '<div class="woosquare-log-section">';
				echo '<h2>Log File Location</h2>';
				echo '<div class="woosquare-log-entry">Path: <code>' . esc_html( $order_sync_log_path ) . '</code></div>';

				if ( $logging_enabled && ! file_exists( $order_sync_log_path ) ) {
					$log_dir = dirname( $order_sync_log_path );
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Log file bootstrap in known admin context.
					if ( is_dir( $log_dir ) && is_writable( $log_dir ) ) {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged -- Create log file if missing; suppress only when dir not writable.
						@file_put_contents( $order_sync_log_path, '' );
					}
				}

				if ( file_exists( $order_sync_log_path ) ) {
					$file_size = filesize( $order_sync_log_path );
					$readable  = is_readable( $order_sync_log_path );
					echo '<div class="woosquare-log-entry">Status: <span class="woosquare-log-success">EXISTS</span> | Size: ' . esc_html( size_format( $file_size, 2 ) ) . ' | Readable: ' . ( $readable ? '<span class="woosquare-log-success">YES</span>' : '<span class="woosquare-log-error">NO</span>' ) . '</div>';
				} else {
					echo '<div class="woosquare-log-entry">Status: <span class="woosquare-log-error">NOT FOUND</span></div>';
				}
				echo '</div>';

				// Display Square Order Sync logs
				if ( file_exists( $order_sync_log_path ) && is_readable( $order_sync_log_path ) ) {
					echo '<div class="woosquare-log-section">';
					echo '<h2>Square Order Sync Logs</h2>'; 

					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local log file for admin display, not a remote URL.
					$log_content = file_get_contents( $order_sync_log_path );
					$lines       = explode( "\n", $log_content );
					echo '<pre id="woosquare-order-sync-log">' . esc_html( implode( "\n", $lines ) ) . '</pre>';
					echo '</div>';
				} else {
					echo '<div class="woosquare-log-section">';
					echo '<h2>Square Order Sync Logs</h2>';
					echo '<div class="woosquare-log-entry woosquare-log-error">Order sync log file not found or not readable at: ' . esc_html( $order_sync_log_path ) . '</div>';
					echo '</div>';
				}

				// Instructions
				echo '<div class="woosquare-log-section">';
				echo '<h2>How to Enable Logging</h2>';
				echo '<ol>';
				echo '<li>Edit wp-config.php file</li>';
				echo '<li>Add or modify these lines:<br>';
				echo '<pre>define( \'WP_DEBUG\', true );';
				echo 'define( \'WP_DEBUG_LOG\', true );';
				echo 'define( \'WP_DEBUG_DISPLAY\', false );</pre></li>';
				echo '<li>Logs will be written to: <code>' . esc_html( WP_CONTENT_DIR ) . '/woosquare-order-sync.log</code></li>';
				echo '<li>After making a Square order, refresh this page to see the logs</li>';
				echo '</ol>';
				echo '</div>';
				?>
			</div>
		</div>
		<script>
			(function () {
				var copyBtn = document.getElementById('woosquare-copy-logs');
				var logPre = document.getElementById('woosquare-order-sync-log');
				if (!copyBtn || !logPre) {
					return;
				}
				copyBtn.addEventListener('click', function () {
					var text = logPre.textContent || '';
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(text).then(function () {
							copyBtn.textContent = 'Copied';
							setTimeout(function () {
								copyBtn.textContent = 'Copy Logs';
							}, 1200);
						});
					} else {
						var textarea = document.createElement('textarea');
						textarea.value = text;
						document.body.appendChild(textarea);
						textarea.select();
						try {
							document.execCommand('copy');
							copyBtn.textContent = 'Copied';
							setTimeout(function () {
								copyBtn.textContent = 'Copy Logs';
							}, 1200);
						} finally {
							document.body.removeChild(textarea);
						}
					}
				});
			})();
		</script>
		<?php
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

		// Check if module files exist (for free version compatibility).
		$base_path = plugin_dir_path( __FILE__ ) . 'modules/';

		if ( isset( $plugin_modules['items_sync'] ) ) {
			$items_sync_path = $base_path . 'product-sync/product-sync.php';
			if ( ! file_exists( $items_sync_path ) ) {
				$plugin_modules['items_sync']['is_premium'] = true;
			}
		}

		if ( isset( $plugin_modules['woosquare_payment'] ) ) {
			$payment_module_path = $base_path . 'square-payments/class-woosquare-payments.php';
			if ( ! file_exists( $payment_module_path ) ) {
				$plugin_modules['woosquare_payment']['is_premium'] = true;
			}
		}

		if ( isset( $plugin_modules['items_sync_log'] ) ) {
			$items_sync_log_path = $base_path . 'square-sync-logs/class-woosquare-sync-logs.php';
			if ( ! file_exists( $items_sync_log_path ) ) {
				$plugin_modules['items_sync_log']['is_premium'] = true;
			}
		}

		if ( isset( $plugin_modules['woosquare_loyalty'] ) ) {
			$loyalty_module_path = $base_path . 'woosquare-loyalty/wcs-loyalty.php';
			if ( ! file_exists( $loyalty_module_path ) ) {
				$plugin_modules['woosquare_loyalty']['is_premium'] = true;
			}
		}

		if ( isset( $plugin_modules['woosquare_modifiers'] ) ) {
			$modifiers_module_path = $base_path . 'woosquare-modifier/class-woosquare-modifier-admin.php';
			if ( ! file_exists( $modifiers_module_path ) ) {
				$plugin_modules['woosquare_modifiers']['is_premium'] = true;
			}
		}

		if ( isset( $plugin_modules['woosquare_card_on_file'] ) || isset( $plugin_modules['customer_sync'] ) ) {
			$customer_module_path = $base_path . 'square-customers/customersync-integration.php';
			if ( ! file_exists( $customer_module_path ) ) {
				if ( isset( $plugin_modules['woosquare_card_on_file'] ) ) {
					$plugin_modules['woosquare_card_on_file']['is_premium'] = true;
				}
				if ( isset( $plugin_modules['customer_sync'] ) ) {
					$plugin_modules['customer_sync']['is_premium'] = true;
				}
			}
		}

		if ( isset( $plugin_modules['woosquare_transaction_addon'] ) ) {
			$transaction_module_path = $base_path . 'transaction-notes/transaction-notes.php';
			if ( ! file_exists( $transaction_module_path ) ) {
				$plugin_modules['woosquare_transaction_addon']['is_premium'] = true;
			}
		}

		if ( isset( $plugin_modules['sales_sync'] ) ) {
			$sales_sync_path = $base_path . 'order-sync/order-sync.php';
			if ( ! file_exists( $sales_sync_path ) ) {
				$plugin_modules['sales_sync']['is_premium'] = true;
			}
		}

		// Sort modules: premium modules at the end.
		if ( ! empty( $plugin_modules ) && is_array( $plugin_modules ) ) {
			$free_modules    = array();
			$premium_modules = array();

			foreach ( $plugin_modules as $key => $module ) {
				if ( isset( $module['is_premium'] ) && $module['is_premium'] ) {
					$premium_modules[ $key ] = $module;
				} else {
					$free_modules[ $key ] = $module;
				}
			}

			// Merge: free modules first, then premium modules.
			$plugin_modules = array_merge( $free_modules, $premium_modules );
		}

		wp_enqueue_style( 'bootstrap', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css', array(), '4.4.1' );
		include WOO_SQUARE_PLUS_PLUGIN_PATH . 'admin/partials/module-views.php';
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
	public function square_loyalty_sync_page() {
		if ( function_exists( 'woo_square_script' ) ) {
			woo_square_script();
		}

		$this->square_loyalty_plugin_page();
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
	 * Callback Functions
	 */
	public function square_item_sync_log_page() {
		if ( function_exists( 'woo_square_script' ) ) {
			woo_square_script();
		}
		$this->square_sync_log_plugin_page();
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
		$square_payment_settin = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

		$square_payment_setting_google_pay        = get_option( 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		$woocommerce_square_gift_card_pay_enabled = get_option( 'woocommerce_square_gift_card_pay_enabled' . get_transient( 'is_sandbox' ) );
		$woocommerce_square_after_pay_settings    = get_option( 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		$woocommerce_square_cash_app_pay_settings = get_option( 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		$woocommerce_square_ach_payment_settings  = get_option( 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_settings' );
		$woocommerce_square_apple_pay_enabled     = get_option( 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		$woocommerce_square_terminal_pay          = get_option( 'woocommerce_square_terminal_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		$woocommerce_square_payment_reporting     = get_option( 'woocommerce_square_payment_reporting' );

		include plugin_dir_path( __FILE__ ) . 'modules/square-payments/views/payment-settings.php';
	}

	/**
	 * Square payment plugin page action
	 *
	 * @global type $wpdb
	 */
	public function square_loyalty_plugin_page() {
		$square_payment_settin = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

		$square_payment_setting_google_pay        = get_option( 'woocommerce_square_google_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		$woocommerce_square_gift_card_pay_enabled = get_option( 'woocommerce_square_gift_card_pay_enabled' . get_transient( 'is_sandbox' ) );
		$woocommerce_square_after_pay_settings    = get_option( 'woocommerce_square_after_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		$woocommerce_square_cash_app_pay_settings = get_option( 'woocommerce_square_cash_app_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		$woocommerce_square_ach_payment_settings  = get_option( 'woocommerce_square_ach_payment' . get_transient( 'is_sandbox' ) . '_settings' );
		$woocommerce_square_apple_pay_enabled     = get_option( 'woocommerce_square_apple_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		$woocommerce_square_terminal_pay          = get_option( 'woocommerce_square_terminal_pay' . get_transient( 'is_sandbox' ) . '_settings' );
		$woocommerce_square_payment_reporting     = get_option( 'woocommerce_square_payment_reporting' );

		include plugin_dir_path( __FILE__ ) . 'modules/woosquare-loyalty/views/loyalty-settings.php';
	}

	/**
	 * Handles the Square Order Sync page.
	 *
	 * This function enqueues the necessary styles and scripts, processes form submissions to update settings,
	 * and includes the view for the order sync settings page.
	 *
	 * @since 1.0.0
	 */
	public function square_order_sync_page() {
		$this->enqueue_styles();
		$this->enqueue_scripts();
		define( 'SQUARE_ORDER_SYNC_PLUGIN_URL', plugin_dir_path( __FILE__ ) . 'modules/order-sync' );
		$error_message   = '';
		$success_message = '';

		if ( isset( $_POST['woosquare_order_sync_setting_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woosquare_order_sync_setting_nonce'] ) ), 'woosquare-order-sync-setting-nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {

			// save settings.
			update_option(
				'squ_woo_order_sync',
				isset( $_POST['squ_woo_order_sync'] ) ? '1' : '0'
			);
			update_option(
				'sync_square_order_notify',
				isset( $_POST['sync_square_order_notify'] ) ? '1' : '0'
			);
			if ( isset( $_POST['woo_square_order_pickup_at'] ) ) {
				update_option( 'woo_square_order_pickup_at', sanitize_text_field( wp_unslash( $_POST['woo_square_order_pickup_at'] ) ) );
			}
			update_option(
				'woosquare_sync_shipping_only_on_fulfillment',
				isset( $_POST['woosquare_sync_shipping_only_on_fulfillment'] ) ? '1' : '0'
			);

			if ( isset( $_POST['woocommerce_square_application_id'] ) ) {
				update_option( 'woo_square_application_id_for_callback', sanitize_text_field( wp_unslash( $_POST['woocommerce_square_application_id'] ) ) );
			}
			if ( isset( $_POST['woocommerce_square_access_token'] ) ) {
				update_option( 'woo_square_access_token_for_callback', sanitize_text_field( wp_unslash( $_POST['woocommerce_square_access_token'] ) ) );
			}
			if ( isset( $_POST['woocommerce_square_location_id'] ) ) {
				update_option( 'woo_square_location_id_for_callback', sanitize_text_field( wp_unslash( $_POST['woocommerce_square_location_id'] ) ) );
			}
		}

		include SQUARE_ORDER_SYNC_PLUGIN_URL . '/view/order-sync-settings.php';
	}

	/**
	 * Handles the Square Customer Sync page.
	 *
	 * This function defines the plugin URL, enqueues the necessary scripts,
	 * and calls the function to handle customer sync settings.
	 *
	 * @since 1.0.0
	 */
	public function square_customer_sync_page() {
		define( 'SQUARE_CUSTOMER_SYNC_PLUGIN_URL', plugin_dir_url( __FILE__ ) . 'modules/square-customers' );
		wp_enqueue_script( 'woo_square_customer_script', SQUARE_CUSTOMER_SYNC_PLUGIN_URL . '/admin/js/customer-sync-integration-admin.js', array( 'jquery' ), WOOSQUARE_VERSION, true );
		square_customer_sync_settings();
	}

	/**
	 * Handles the Square Card Sync page.
	 *
	 * This function defines the plugin URL, processes form submissions to update settings,
	 * and includes the view for the card sync settings page.
	 *
	 * @since 1.0.0
	 */
	public function square_card_sync_page() {
		define( 'SQUARE_CUSTOMER_SYNC_PLUGIN_URL', plugin_dir_path( __FILE__ ) . 'modules/square-customers' );
		$error_message   = '';
		$success_message = '';
		if ( isset( $_POST['woosquare_customer_setting_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woosquare_customer_setting_nonce'] ) ), 'woosquare-customer-setting-nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			// save settings.
			if ( isset( $_POST['woo_square_card_settings'] ) ) {
				if ( isset( $_POST['cust_add_myaccount'] ) ) {
					update_option( 'cust_add_myaccount', sanitize_text_field( wp_unslash( $_POST['cust_add_myaccount'] ) ) );
					$success_message = 'Settings updated successfully!';
				} else {
					$error_message = 'Missing required field.';
				}
			}
		}
		include SQUARE_CUSTOMER_SYNC_PLUGIN_URL . '/admin/partials/card-on-file-settings.php';
	}

	/**
	 * Handles the Square Transaction Sync page.
	 *
	 * This function includes the necessary file, processes form submissions to update settings,
	 * and generates the content for the transaction sync settings page.
	 *
	 * @since 1.0.0
	 */
	public function square_transaction_sync_page() {
		require_once plugin_dir_path( __FILE__ ) . '../admin/modules/transaction-notes/transaction-notes.php';
		$error_message   = '';
		$success_message = '';
		if ( isset( $_POST['woosquare_transaction_setting_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woosquare_transaction_setting_nonce'] ) ), 'woosquare-transaction-setting-nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			// save settings.
			if ( isset( $_POST['selected_order_info'] ) ) {
				update_option( 'selected_order_info', sanitize_text_field( wp_unslash( $_POST['selected_order_info'] ) ) );
				$success_message = 'Settings updated successfully!';
			}
		}

		$countries = new WC_Countries();
		$billing   = $countries->get_address_fields( $countries->get_base_country(), 'billing_' );
		$keywords  = null;
		if ( ! empty( $billing ) && is_array( $billing ) ) {
			$keywords .= '{order_id} ';
			foreach ( $billing as $keys => $values ) {
				$keywords .= '{' . $keys . '} ';
			}
		}
		$selected_order_info = get_option( 'selected_order_info' );
		if ( $success_message ) {
			echo '<br/><div class="updated"><p>' . esc_html( $success_message ) . '</p></div>';
		}

		echo _get_transaction_note( $selected_order_info, $keywords ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * AJAX handler to clear WooSquare logs
	 */
	public function clear_woosquare_logs() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'clear_woosquare_logs' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		// Clear logs only for current environment (sandbox or production).
		$current_env = get_transient( 'is_sandbox' );
		$option_name = 'woosquare_connection_logs' . $current_env;

		delete_option( $option_name );

		wp_send_json_success( 'Logs cleared successfully for current environment' );
	}

	/**
	 * AJAX handler to save WooSquare alert settings
	 */
	public function save_woosquare_alerts() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'save_woosquare_alerts' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		// Get and sanitize data.
		$alerts_enabled = isset( $_POST['alerts_enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['alerts_enabled'] ) ) : 'false';
		$alert_email    = isset( $_POST['alert_email'] ) ? sanitize_email( wp_unslash( $_POST['alert_email'] ) ) : '';

		// Validate email.
		if ( ! empty( $alert_email ) && ! is_email( $alert_email ) ) {
			wp_send_json_error( 'Invalid email address' );
		}

		// Save settings.
		if ( 'true' === $alerts_enabled ) {
			$saved_alerts = update_option( 'woosquare_alerts_enabled', true );
		} else {
			$saved_alerts = update_option( 'woosquare_alerts_enabled', false );
		}
		$saved_email = update_option( 'woosquare_alert_email', $alert_email );

		wp_send_json_success( 'Alert settings saved successfully' );
	}

	/**
	 * Square Connection module page callback
	 */
	public function woosquare_square_connection_page() {
		// Include the Square Connection module.
		include plugin_dir_path( __FILE__ ) . 'modules/square-connection/square-connection.php';
	}
}
