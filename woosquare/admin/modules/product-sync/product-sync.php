<?php
/**
 * The product-sync functionality of the plugin.
 *
 * @package Woosquare_Plus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Displays an error message and deactivates the WooCommerce Square Integration plugin.
 *
 * This function checks for the required plugins (WooCommerce and MyCRED) and the PHP version needed
 * to run the WooCommerce Square Integration plugin. If any of these requirements are not met,
 * it displays an error message, redirects the user to the plugins page, and deactivates the plugin.
 *
 * @return void
 */
function report_error_pro() {
	$class = 'notice notice-error';
	if (
	! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) &&
	! in_array( 'mycred/mycred.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true )
	) {
		$message = sprintf(
			// Translators: %s is the label for WooCommerce Square Integration.
			__(
				'To use "%s WooCommerce Square Integration", WooCommerce or MYCRED must be activated or installed!',
				'woosquare'
			),
			WOOSQU_PLUS_LABEL
		);
		printf(
			'<br><div class="%1$s"><p>%2$s</p></div><script>setTimeout(function () {
				window.location.href = "plugins.php";
			}, 2500);</script>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}
	if ( version_compare( PHP_VERSION, '5.5.0', '<' ) ) {
		$message = sprintf(
			// Translators: %1$s is the label for WooCommerce Square Integration, and %2$s is the current PHP version.
			__(
				'To use "%1$s WooCommerce Square Integration" PHP version must be 5.5.0+. Current version is: %2$s. Contact your hosting provider to upgrade your server PHP version.',
				'woosquare'
			),
			WOOSQU_PLUS_LABEL,
			PHP_VERSION
		);
		printf(
			'<br><div class="%1$s"><p>%2$s</p></div><script>setTimeout(function () {
				window.location.href = "plugins.php";
			}, 2500);</script>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}
	deactivate_plugins( 'woosquare-premium/woocommerce-square-integration.php' );
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
 * Enqueues custom admin scripts for WooCommerce product pages.
 *
 * This function checks if the current admin page is a WooCommerce product editing page.
 * If so, it enqueues a custom JavaScript file that is needed for product-related functionality.
 *
 * @param string $hook The current admin page hook.
 *
 * @return void
 */
function add_admin_scripts( $hook ) {

	global $post;

	if ( 'post-new.php' === $hook || 'post.php' === $hook ) {
		if ( 'product' === $post->post_type ) {
			wp_enqueue_script( 'woo_square_product_script', WOO_SQUARE_PLUGIN_URL . '_inc/js/product-script.js', array( 'jquery' ), '1.0', true );
		}
	}
}
add_action( 'admin_enqueue_scripts', 'add_admin_scripts', 10, 1 );

if ( ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true )
	&& ! in_array( 'mycred/mycred.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) )
	|| version_compare( PHP_VERSION, '5.5.0', '<' )
) {
	add_action( 'admin_notices', 'report_error_pro' );
} else {

	if ( ! defined( 'WOO_SQUARE_PLUGIN_URL' ) ) {
		define( 'WOO_SQUARE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	}

	define( 'WOO_SQUARE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
	define( 'WOO_SQUARE_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );


	$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
	if ( get_transient( 'is_sandbox' ) ) {
		if ( ! defined( 'WOOSQU_ENABLE_STAGING' ) ) {
			define( 'WOOSQU_ENABLE_STAGING', true );
		}
	} elseif ( ! defined( 'WOOSQU_ENABLE_STAGING' ) ) {
			define( 'WOOSQU_ENABLE_STAGING', false );
	}

	add_action( 'wp_ajax_manual_sync', 'woo_square_manual_sync' );


	if ( ! get_option( 'v2_converted_cat' ) ) {
		add_action( 'plugins_loaded', 'woo_square_v2_converted_cat' );
	}

	add_action( 'save_post', 'woo_square_add_edit_product', 10, 3 );


	if ( ! get_option( 'disable_auto_delete' ) ) {
		add_action( 'before_delete_post', 'woo_square_delete_product' );
	}
	add_action( 'create_product_cat', 'woo_square_add_category' );
	add_action( 'edited_product_cat', 'woo_square_edit_category' );
	add_action( 'delete_product_cat', 'woo_square_delete_category', 10, 3 );
	add_action( 'woocommerce_order_refunded', 'woo_square_create_refund', 10, 2 );
	if ( isset( $activate_modules_woosquare_plus['sales_sync']['module_activate'] ) && false === $activate_modules_woosquare_plus['sales_sync']['module_activate'] ) {
		add_action( 'woocommerce_order_status_processing', 'woo_square_complete_order' );
	}

	add_action( 'wp_loaded', 'post_savepage_load_admin_notice' );

	/**
	 * Extends the timeout duration for a specific process.
	 *
	 * This function extends the timeout duration from the default value to 120 seconds.
	 * It can be used to increase the time allowed for certain operations that require
	 * more time to complete.
	 *
	 * @param int $time The original timeout value.
	 *
	 * @return int The extended timeout value (120 seconds).
	 */
	function wp9838c_timeout_extend( $time ) { // phpcs:ignore
		// Default timeout is 5.
		return 120;
	}
	add_filter( 'http_request_timeout', 'wp9838c_timeout_extend' );

	/**
	 * Checks and retrieves the custom sale price attribute from Square.
	 *
	 * This function runs during the admin initialization process. It checks if the WooSquare Plus
	 * item synchronization module is activated and whether a custom sale price attribute exists.
	 * If the custom sale price attribute is present, it retrieves the attribute from Square using
	 * the WooToSquareSynchronizer class.
	 *
	 * @return void
	 */
	function check_sale_price_custom_attr() {
		$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
		if ( isset( $activate_modules_woosquare_plus['items_sync']['module_activate'] ) && true === $activate_modules_woosquare_plus['items_sync']['module_activate'] ) {
			if ( ! empty( get_option( 'woosquare_sale_price_custom_attr' ) ) ) {
				$square              = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
				$square_synchronizer = new WooToSquareSynchronizer( $square );
				$square_synchronizer->get_woosquare_custom_sale_attr();

			}
		}
	}
	add_action( 'admin_init', 'check_sale_price_custom_attr' );

	/**
	 * Removes Square-related metadata when duplicating a WooCommerce product.
	 *
	 * This function is hooked into the WooCommerce product duplication process and is triggered
	 * before the duplicated product is saved. It removes Square-related metadata to ensure that
	 * the duplicated product does not retain any references to Square items or variations.
	 *
	 * @param WC_Product $duplicate The duplicated WooCommerce product object.
	 * @param WC_Product $product   The original WooCommerce product object being duplicated.
	 *
	 * @return void
	 */
	function catch_duplicate_product( $duplicate, $product ) { // phpcs:ignore
		$duplicate->delete_meta_data( 'square_id' );
		$duplicate->delete_meta_data( '_square_item_id' );
		$duplicate->delete_meta_data( '_square_item_variation_id' );
	}
	add_action( 'woocommerce_product_duplicate_before_save', 'catch_duplicate_product', 1, 2 );



	/**
	 * Include script
	 */
	function woo_square_script() {

		wp_enqueue_script( 'woo_square_script', WOO_SQUARE_PLUGIN_URL . '_inc/js/script.js', array( 'jquery' ), '1.0', true );
		$localize_array = array(
			'ajaxurl'   => admin_url( 'admin-ajax.php' ),
			'ajaxnonce' => wp_create_nonce( 'my_woosquare_ajax_nonce' ),
		);
		wp_localize_script( 'woo_square_script', 'myAjax', $localize_array );

		wp_enqueue_style( 'woo_square_pop-up', WOO_SQUARE_PLUGIN_URL . '_inc/css/pop-up.css', '1.0', 'all' );
		wp_enqueue_style( 'woo_square_synchronization', WOO_SQUARE_PLUGIN_URL . '_inc/css/synchronization.css', array(), '1.0', 'all' );
	}

	/**
	 * Convert and update WooCommerce category options based on Square category information.
	 *
	 * This function checks if category options need to be converted and updated based on
	 * Square category information. It retrieves Square categories, compares them to existing
	 * options, and updates options as needed.
	 */
	function woo_square_v2_converted_cat() {
		if ( ! get_option( 'v2_converted_cat' ) ) {
			$square              = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
			$square_synchronizer = new SquareToWooSynchronizer( $square );
			$square_categories   = $square_synchronizer->get_square_categories();

			if ( ! empty( $square_categories ) ) {
				global $wpdb;
				$sql               = $wpdb->prepare(
					"SELECT
						*
					FROM
						`{$wpdb->base_prefix}options`
					WHERE
						option_name LIKE %s;",
					'%category_square_id_%'
				);
				$get_results       = 'get_results';
				$square_cat_option = $wpdb->$get_results( $sql, ARRAY_A );
				foreach ( $square_categories as $square_category ) {
					foreach ( $square_cat_option as $woocat ) {

						if ( ! empty( $square_category->catalog_v1_ids ) ) {
							$v2explodedform = explode( '-', $woocat['option_value'] );

							if ( count( $v2explodedform ) > 1 ) {
								delete_option( $woocat['option_name'] );
							}
						}
					}
				}
				update_option( 'v2_converted_cat', true );
			}
		}
	}

	/**
	 * Manually syncs WooCommerce and Square.
	 *
	 * This function manually syncs WooCommerce and Square by calling the Square API.
	 *
	 * @return void
	 */
	function woo_square_manual_sync() {

		set_time_limit( 0 );

		if ( ! get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) ) {
			return;
		}

		if ( get_option( 'woo_square_running_sync' ) && ( time() - (int) get_option( 'woo_square_running_sync_time' ) ) < ( WOO_SQUARE_MAX_SYNC_TIME ) ) {
			echo 'There is another Synchronization process running. Please try again later. Or <a href="' . esc_url( admin_url( 'admin.php?page=square-item-sync&terminate_sync=true' ) ) . '" > terminate now </a>';
			die();
		}

		update_option( 'woo_square_running_sync', true );
		update_option( 'woo_square_running_sync_time', time() );
		if ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) ) {
			$http_x_requested_with_sanitized = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REQUESTED_WITH'] ) );
		}
		if ( 'xmlhttprequest' === strtolower( $http_x_requested_with_sanitized ) ) {

			if ( isset( $_GET['way'] ) ) { // phpcs:ignore
				$sync_direction = sanitize_text_field( wp_unslash( $_GET['way'] ) ); // phpcs:ignore

				$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
				if ( 'wootosqu' === $sync_direction ) {
					$square_synchronizer = new WooToSquareSynchronizer( $square );
					$square_synchronizer->sync_from_woo_to_square();
				} elseif ( 'squtowoo' === $sync_direction ) {
					$square_synchronizer = new SquareToWooSynchronizer( $square );
					$square_synchronizer->sync_from_square_to_woo();
				}
			}
		}
		update_option( 'woo_square_running_sync', false );
		update_option( 'woo_square_running_sync_time', 0 );
		die();
	}

	/**
	 * Display admin notices based on specific conditions.
	 *
	 * This function checks various conditions and displays admin notices in the WordPress
	 * admin area. It checks for the existence of a post meta value and displays an error notice
	 * if it exists. Additionally, it checks and updates options related to Woosquare Plus modules.
	 */
	function post_savepage_load_admin_notice() {
		// Use html_compress($html) function to minify html codes.

		if ( ! empty( $_GET['post'] ) ) { // phpcs:ignore
			$admin_notice_square = get_post_meta( sanitize_text_field( wp_unslash( $_GET['post'] ) ), 'admin_notice_square', true ); // phpcs:ignore

			if ( ! empty( $admin_notice_square ) ) {
				$admin_notice_square_esc = esc_attr( $admin_notice_square );
				ob_start();
				?>
				<div id="message" class="notice notice-error is-dismissible">
					<p><?php echo esc_html( $admin_notice_square_esc ); ?></p>
				</div>
				<?php
				delete_post_meta( sanitize_text_field( wp_unslash( $_GET['post'] ) ), 'admin_notice_square', 'Product unable to sync to Square due to Sku missing ' ); // phpcs:ignore
			}
		}

		if ( ! empty( get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) ) ) ) {
			$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );

			if ( ! array_key_exists( 'woosquare_modifiers', $activate_modules_woosquare_plus )
				|| ! get_option( 'woosquare_module_updated_content1' )
			) {
				$activate_modules_woosquare_plus['woosquare_modifiers'] = array(
					'module_img'           => plugin_dir_url( __FILE__ ) . '../admin/img/woomodifires.png',
					'module_title'         => 'Square Modifiers',
					'module_short_excerpt' => 'Square Modifiers in WC Shop Sync allow you to sell items that are customizable or offer additional choices.',
					'module_redirect'      => 'https://apiexperts.io/documentation/woosquare-plus/#square-modifiers',
					'module_video'         => 'https://www.youtube.com/embed/XnC0cOoWx-k',
					'module_activate'      => isset( $activate_modules_woosquare_plus['woosquare_modifiers']['module_activate'] ),
					'module_menu_details'  => array(
						'menu_title'        => 'Square Modifiers',
						'parent_slug'       => 'square-modifiers',
						'page_title'        => 'Square Modifiers',
						'capability'        => 'manage_options',
						'menu_slug'         => 'square-modifiers',
						'tab_html_class'    => 'fa fa-credit-card',
						'function_callback' => 'square_modifiers_sync_page',
					),
				);
				delete_option( 'woosquare_module_updated_content' );
				update_option( 'woosquare_module_updated_content1', 'updated1' );
				update_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), $activate_modules_woosquare_plus );
			}
		}
	}

	/**
	 * Adds or edits a Square product.
	 *
	 * This function adds or edits a Square product by calling the Square API.
	 *
	 * @param int    $post_id The WooCommerce product ID.
	 * @param object $post The WooCommerce product object.
	 * @param bool   $update Whether the product is being updated.
	 *
	 * @return void
	 */
	function woo_square_add_edit_product( $post_id, $post, $update ) {
		// checking Would you like to synchronize your product on every product edit or update ?

		if ( $update ) {
			// The product has been updated.
			// Add your custom code here.
			$gtin_product = wc_get_product( $post_id );
			if ( $product && $product->is_type( 'variable' ) ) {
				// Get all variations of the variable product.
				$variations = $product->get_children(); // This returns an array of variation IDs.

				// Output variation IDs.
				if ( ! empty( $variations ) ) {
					foreach ( $variations as $variation_id ) {
						if ( empty( get_post_meta( $variation_id, '_global_unique_id', true ) ) ) {
							$old_gtin = get_post_meta( $variation_id, 'woosquare_gtin_code', true );
							update_post_meta( $variation_id, '_global_unique_id', $old_gtin );
							$new_gtin = get_post_meta( $variation_id, '_global_unique_id', true );
						}
					}
				}
			} elseif ( empty( get_post_meta( $post_id, '_global_unique_id', true ) ) ) {
				$old_gtin = get_post_meta( $post_id, 'woosquare_gtin_code', true );
				update_post_meta( $post_id, '_global_unique_id', $old_gtin );
				$new_gtin = get_post_meta( $post_id, '_global_unique_id', true );
			}
		}
		$sync_on_add_edit = get_option( 'sync_on_add_edit', $default = false );

		if ( '1' === $sync_on_add_edit ) {

			// Avoid auto save from calling Square APIs.
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			if ( $update && 'product' === $post->post_type && 'publish' === $post->post_status ) {

				update_post_meta( $post_id, 'is_square_sync', 0 );

				if ( ! get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) ) {
					return;
				}

				$product_square_id = '';
				$product_square_id = get_post_meta( $post_id, 'square_id', true );
				$square            = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );

				$square_synchronizer = new WooToSquareSynchronizer( $square );

				$square_to_woo_synchronizer = new SquareToWooSynchronizer( $square );
				$square_items               = $square_to_woo_synchronizer->get_square_items();

				if ( $square_items ) {
					$square_items = $square_synchronizer->simplify_square_items_object( $square_items );
				} else {
					$square_items = array();
				}

				$product_square_id = $square_synchronizer->check_sku_in_square( $post, $square_items );

				$product = wc_get_product( $post->ID );

				$response = array();

				$method = 'POST';
				$url    = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/search';

				$headers = array(
					'Authorization'  => 'Bearer ' . get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), // Use verbose mode in cURL to determine the format you want for this header.
					'Content-Type'   => 'application/json;',
					'Square-Version' => '2020-12-16',
				);

				$args = array(
					'object_types'            =>
					array(
						0 => 'ITEM',
						1 => 'ITEM_VARIATION',
					),
					'include_related_objects' => true,
					'query'                   =>
					array(
						'text_query' =>
						array(
							'keywords' =>
							array(
								0 => $product->get_sku(),
							),
						),
					),
				);

				$response = $square->wp_remote_woosquare( $url, $args, $method, $headers, $response );
				if ( ! empty( $response['response'] ) ) {
					if ( 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {
						$square_product = json_decode( $response['body'], false );
					}
				}

				foreach ( $square_product as $product ) {
					foreach ( $product_square_id->variations as $variation ) {
						// Check if item_id matches.
						if ( $product->item_variation_data->item_id === $variation->item_id ) {
							// Merge present_at_location_ids into the variation.
							$variation->present_at_location_ids = $product->present_at_location_ids;
						}
					}
				}

				if ( ! empty( $square_product->related_objects ) ) {
					foreach ( $square_product->related_objects as $obj ) {
						if ( 'ITEM' === $obj->type ) {
							$product_square_id = $obj;
						}
					}
				}

				$result = $square_synchronizer->add_product( $post, $product_square_id );
				if ( class_exists( 'WooSquare_Sync_Logs' ) ) {
					$woo_product_sync_log_transient[ $post_id ][ $result['pro_status'] ] = $result;
					$woosquare_sync_log = new WooSquare_Sync_Logs();
					$log_id             = $woosquare_sync_log->log_data_request( $woo_product_sync_log_transient, '', 'woo_to_square', 'product' );
				}

				$termid = get_post_meta( $post_id, '_termid', true );
				if ( '' === $termid ) { // new product.
					$termid = 'update';
				}
				update_post_meta( $post_id, '_termid', $termid );

				if ( true === $result['pro_status'] ) {
					update_post_meta( $post_id, 'is_square_sync', 1 );
				}
			}
		} else {
			update_post_meta( $post_id, 'is_square_sync', 0 );
		}
	}

	/**
	 * Deletes a Square product.
	 *
	 * This function deletes a Square product by calling the Square API.
	 *
	 * @param int $post_id The WooCommerce product ID.
	 *
	 * @return void
	 */
	function woo_square_delete_product( $post_id ) {

		unset( $_SESSION['woo_product_delete_log'] );
		delete_transient( 'woo_product_delete_log' );
		delete_transient( 'woo_delete_product_log_id' );
		$sync_on_add_edit = get_option( 'sync_on_add_edit', $default = false );

			// Avoid auto save from calling Square APIs.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
			$product_square_id = get_post_meta( $post_id, 'square_id', true );
			$product           = get_post( $post_id );
		if ( 'product' === $product->post_type && ! empty( $product_square_id ) ) {

			global $wpdb;
			$insert = 'insert';
			$wpdb->$insert(
				$wpdb->prefix . WOO_SQUARE_TABLE_DELETED_DATA,
				array(
					'square_id'   => $product_square_id,
					'target_id'   => $post_id,
					'target_type' => Helpers::TARGET_TYPE_PRODUCT,
					'name'        => $product->post_title,
				)
			);

			if ( ! get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) ) {
					return;
			}

			$square              = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
			$square_synchronizer = new WooToSquareSynchronizer( $square );
			if ( ! get_option( 'disable_auto_delete' ) ) {
				$result = $square_synchronizer->delete_product_or_get( $product_square_id, 'DELETE' );
			}

			// delete product from plugin delete table.
			if ( true === $result ) {
				$delt_pro = wc_get_product( $post_id );
				$sku      = $delt_pro->get_sku();
				if ( isset( $delt_pro ) && 'variable' === $delt_pro->get_type() ) {
					$product_variation_skus = '';
					$variations             = $delt_pro->get_available_variations();
					$variations_id          = wp_list_pluck( $variations, 'variation_id' );
					foreach ( $variations_id as $var_id ) {
						$product_var             = wc_get_product( $var_id );
						$product_variation_skus .= $product_var->get_sku() . ', ';
					}
					$sku = $product_variation_skus;
				}
				$delt_pro_array = array(
					'name'    => $delt_pro->get_name(),
					'sku'     => $sku,
					'status'  => 'deleted',
					'message' => __( 'Successfully Deleted', 'woosquare' ),
				);
				if ( class_exists( 'WooSquare_Sync_Logs' ) ) {

					$_SESSION['woo_product_delete_log'][ $delt_pro->get_id() ]['delete'] = $delt_pro_array;

					if ( isset( $_SESSION['woo_product_delete_log'] ) ) {
						set_transient( 'woo_product_delete_log', $_SESSION['woo_product_delete_log'], 300 ); // phpcs:ignore
					}

					$woosquare_sync_log = new WooSquare_Sync_Logs();
					$log_id             = $woosquare_sync_log->delete_product_log_data_request( get_transient( 'woo_product_delete_log' ), get_transient( 'woo_delete_product_log_id' ), 'product', 'woo_to_square' );
					if ( ! empty( $log_id ) ) {
						set_transient( 'woo_delete_product_log_id', $log_id, 300 );
					}
				}
			}
		}
	}

	/**
	 * Adds a Square category.
	 *
	 * This function adds a Square category by calling the Square API.
	 *
	 * @param int $category_id The WooCommerce category ID.
	 *
	 * @return void
	 */
	function woo_square_add_category( $category_id ) {
		session_start();
		$sync_on_add_edit = get_option( 'sync_on_add_edit', $default = false );
		if ( '1' === $sync_on_add_edit ) {
			// Avoid auto save from calling Square APIs.
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			$category = get_term_by( 'id', $category_id, 'product_cat' );
			update_option( "is_square_sync_{$category_id}", 0 );

			if ( ! get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) ) {
				return;
			}

			$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );

			$square_synchronizer = new WooToSquareSynchronizer( $square );
			$result              = $square_synchronizer->add_category( $category );

			if ( class_exists( 'WooSquare_Sync_Logs' ) ) {
				$_SESSION['woo_product_sync_log'][ $category_id ][ $result['pro_status'] ] = $result;
				if ( isset( $_SESSION['woo_product_sync_log'] ) ) {
					$woosquare_sync_log      = new WooSquare_Sync_Logs();
					$woo_product_sync_log    = sanitize_text_field( wp_unslash( $_SESSION['woo_product_sync_log'] ) );
					$woo_product_sync_log_id = isset( $_SESSION['woo_product_sync_log_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['woo_product_sync_log_id'] ) ) : '';
					$log_id                  = $woosquare_sync_log->log_data_request( $woo_product_sync_log, $woo_product_sync_log_id, 'woo_to_square', 'category' );
				}
				if ( ! empty( $log_id ) ) {
					$_SESSION['woo_product_sync_log_id'] = $log_id;
				}
			}
			if ( true === $result['status'] ) {
				update_option( "is_square_sync_{$category_id}", 1 );
			}
		}
	}

	/**
	 * Edits a Square category.
	 *
	 * This function edits a Square category by calling the Square API.
	 *
	 * @param int $category_id The WooCommerce category ID.
	 *
	 * @return void
	 */
	function woo_square_edit_category( $category_id ) {
		session_start();
		$sync_on_add_edit = get_option( 'sync_on_add_edit', $default = false );
		if ( '1' === $sync_on_add_edit ) {
			// Avoid auto save from calling Square APIs.
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			update_option( "is_square_sync_{$category_id}", 0 );

			if ( ! get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) ) {
				return;
			}
			$category           = get_term_by( 'id', $category_id, 'product_cat' );
			$category->term_id  = $category_id;
			$category_square_id = get_option( 'category_square_id_' . $category->term_id );

			$square              = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
			$square_synchronizer = new WooToSquareSynchronizer( $square );

			// add category if not already linked to square, else update.
			if ( empty( $category_square_id ) ) {
				$result = $square_synchronizer->add_category( $category );
			} else {
				$result = $square_synchronizer->edit_category( $category, $category_square_id );
			}

			if ( true === $result['status'] ) {
				update_option( "is_square_sync_{$category_id}", 1 );
			}
			if ( class_exists( 'WooSquare_Sync_Logs' ) ) {
				$_SESSION['woo_product_sync_log'][ $category_id ][ $result['pro_status'] ] = $result;
				if ( isset( $_SESSION['woo_product_sync_log'] ) ) {
					$woosquare_sync_log      = new WooSquare_Sync_Logs();
					$woo_product_sync_log    = sanitize_text_field( wp_unslash( $_SESSION['woo_product_sync_log'] ) );
					$woo_product_sync_log_id = isset( $_SESSION['woo_product_sync_log_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['woo_product_sync_log_id'] ) ) : '';
					$log_id                  = $woosquare_sync_log->log_data_request( $woo_product_sync_log, $woo_product_sync_log_id, 'woo_to_square', 'category' );
				}
				if ( ! empty( $log_id ) ) {
					$_SESSION['woo_product_sync_log_id'] = $log_id;
				}
			}
		}
	}

	/**
	 * Deletes a Square category.
	 *
	 * This function deletes a Square category by calling the Square API.
	 *
	 * @param int     $category_id The WooCommerce category ID.
	 * @param int     $term_taxonomy_id The WooCommerce term taxonomy ID.
	 * @param WP_Term $deleted_category The deleted WooCommerce category.
	 *
	 * @return void
	 */
	function woo_square_delete_category( $category_id, $term_taxonomy_id, $deleted_category ) {
		$woo_product_delete_log_transientt = get_transient( 'woo_product_delete_log_transient' );
		if ( empty( $woo_product_delete_log_transientt ) ) {
			$arr = array();
			set_transient( 'woo_product_delete_log_transient', $arr, 300 );
		}

		$woo_product_delete_log_transientt = get_transient( 'woo_product_delete_log_transient' );

		session_start();
		$sync_on_add_edit = get_option( 'sync_on_add_edit', $default = false );
		delete_option( "is_square_sync_{$category_id}" );
		delete_option( "category_square_id_{$category_id}" );

		if ( '1' === $sync_on_add_edit ) {
			// Avoid auto save from calling Square APIs.
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}
			$category_square_id = get_option( 'category_square_id_' . $category_id );

			// no need to call square.
			if ( empty( $category_square_id ) ) {
				return;
			}

			global $wpdb;

			$insert = 'insert';
			$wpdb->$insert(
				$wpdb->prefix . WOO_SQUARE_TABLE_DELETED_DATA,
				array(
					'square_id'   => $category_square_id,
					'target_id'   => $category_id,
					'target_type' => Helpers::TARGET_TYPE_CATEGORY,
					'name'        => $deleted_category->name,
				)
			);

			if ( ! get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) ) {
				return;
			}

			$square              = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
			$square_synchronizer = new WooToSquareSynchronizer( $square );
			$result              = $square_synchronizer->deleteCategory( $category_square_id );

			// delete product from plugin delete table.
			if ( true === $result ) {
				$delt_pro_array = array(
					'name'    => esc_html( $deleted_category->name ), // Sanitize category name.
					'status'  => 'deleted',
					'item'    => 'category',
					'message' => __( 'Successfully Deleted', 'woosquare' ),
				);

				if ( class_exists( 'WooSquare_Sync_Logs' ) ) {
					$woo_product_delete_log_transient[ $category_id ]['delete'] = $delt_pro_array;
					$woo_product_delete_log_transient                           = array_merge( $woo_product_delete_log_transientt, $woo_product_delete_log_transient );
					set_transient( 'woo_product_delete_log_transient', $woo_product_delete_log_transient, 300 );
					$woo_product_delete_log_id_transient = get_transient( 'woo_product_delete_log_id_transient' );
					$woosquare_sync_log                  = new WooSquare_Sync_Logs();
					$log_id                              = $woosquare_sync_log->delete_product_log_data_request( $woo_product_delete_log_transient, $woo_product_delete_log_id_transient, 'category', 'woo_to_square' );
					if ( ! empty( $log_id ) ) {
						set_transient( 'woo_product_delete_log_id_transient', $log_id, 300 );
					}
				}
			}
		}
	}

	/**
	 * Creates a Square refund.
	 *
	 * This function creates a Square refund by calling the Square API.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 * @param int $refund_id The WooCommerce refund ID.
	 *
	 * @return void
	 */
	function woo_square_create_refund( $order_id, $refund_id ) {
		$order = wc_get_order( $order_id );
		if ( ! get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) ) {
			return;
		}
		// Avoid auto save from calling Square APIs.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( $order->get_meta( 'woosquare_transaction_id', true ) || $order->get_meta( 'square_payment_id', true ) ) {

			$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );

			$square->refund( $order_id, $refund_id );
		}
	}

	/**
	 * Update square inventory on complete order.
	 *
	 * This function syncs a square product's inventory with WooCommerce by
	 * fetching the square inventory and updating the WooCommerce product.
	 *
	 * @param array $square_product The product information from Square, including variations and product ID.
	 */
	function process_single_square_product( $square_product ) {
		$woo_square_listsaved_products_square = get_option( 'woo_square_listsaved_products_square' );
		$woo_square_sync_preference           = get_option( 'woo_square_sync_preference' );
		$woo_square_auto_sync                 = get_option( 'woo_square_auto_sync' );
		// Sync preference check karein.
		if ( 0 === $woo_square_sync_preference && 1 === $woo_square_auto_sync ) {
			if ( ! in_array( $square_product['id'], $woo_square_listsaved_products_square, true ) ) {
				return;
			}
		}

		$square              = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
		$square_synchronizer = new SquareToWooSynchronizer( $square );

		// Square Inventory fetch karein.
		$array = $square_product['variations'];

		$square_inventory = $square_synchronizer->get_square_inventory( $array );

		$square_inventory_array = ! empty( $square_inventory ) ? $square_synchronizer->convert_square_inventory_to_associative( $square_inventory ) : array();

		// WooCommerce me product add/update karein.
		$action         = null;
		$square_product = ( ( $square_product ) );
		$id             = $square_synchronizer->add_product_to_woo( $square_product, $square_inventory_array, $action );
		// Debugging ke liye output show karein.

		// Agar action null hai toh return karein.
		if ( is_null( $action ) ) {
			return;
		}

		if ( ! empty( $id ) && is_numeric( $id ) ) {
			update_post_meta( $id, 'is_square_sync', 1 );
		}

		// Server overload avoid karne ke liye ek chhota delay.
		usleep( 500000 ); // 0.5 second delay.
	}

	/**
	 * Completes a Square order.
	 *
	 * This function completes a Square order by calling the Square API.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 *
	 * @return void
	 */
	function woo_square_complete_order( $order_id ) {
		if ( ! get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) ) {
			return;
		}
		// Avoid auto save from calling Square APIs.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
		$square->complete_order( $order_id );
	}

	/**
	 * Handles the synchronization of Square products with WooCommerce.
	 *
	 * This function processes a batch of Square product data, updates the inventory
	 * in WooCommerce, and returns the status of the processed products.
	 *
	 * @return void
	 */
	function handle_square_sync() {

		// Ensure the nonce is unslashed before use and then sanitize it.
		if ( ! isset( $_POST['square_sync_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['square_sync_nonce'] ) ), 'square_sync_nonce' ) ) {
			wp_send_json_error( array( 'error' => 'Nonce verification failed!' ) );
			wp_die();
		}

		if ( ! isset( $_POST['batch'] ) ) {
			wp_send_json_error( array( 'error' => 'Batch data missing!' ) );
			wp_die();
		}

		// Sanitize and decode the batch input data.
		$batch_data = sanitize_text_field( wp_unslash( $_POST['batch'] ) );
		$batch      = json_decode( $batch_data, true );

		if ( empty( $batch ) ) {
			wp_send_json_error( array( 'error' => 'Invalid batch data!' ) );
			wp_die();
		}

		$processed = array();

		foreach ( $batch as $square_product ) {
			$result      = process_single_square_product( $square_product );
			$processed[] = array(
				'id'     => $square_product['id'],
				'status' => $result,
			);
		}

		// Return success response.
		wp_send_json_success( array( 'processed' => $processed ) );
		wp_die();
	}

	/**
	 * Executes the automatic synchronization between WooCommerce and Square.
	 *
	 * This function is designed to run as a cron job, synchronizing data between WooCommerce and Square
	 * based on the selected merging option. It handles both Woo-to-Square and Square-to-Woo synchronization.
	 * The function ensures that the synchronization process is managed with appropriate time limits and updates
	 * relevant status options during the process.
	 *
	 * @return void
	 */
	function auto_sync_cron_job() {
		if ( '1' === get_option( 'woo_square_auto_sync' ) ) {
			update_option( 'woo_square_auto_sync_' . gmdate( 'Y-m-d H:i:s' ), '' );
			set_time_limit( 0 );

			if ( ! get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) ) {
				return;
			}

			update_option( 'woo_square_running_sync', true );
			update_option( 'woo_square_running_sync_time', time() );

			$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
			delete_transient( $woo_square_location_id . 'transient_getSquareItems' );

			$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );

			if ( get_option( 'woo_square_merging_option' ) === '1' ) { // From Woo To Square.
				$square_synchronizer = new WooToSquareSynchronizer( $square );
				$square_synchronizer->sync_from_woo_to_square();

			} elseif ( get_option( 'woo_square_merging_option' ) === '2' ) { // From Square To Woo.

				$square_synchronizer = new SquareToWooSynchronizer( $square );
				$square_synchronizer->sync_from_square_to_woo();

			}
			update_option( 'woo_square_running_sync', false );
			update_option( 'woo_square_running_sync_time', 0 );
		}
	}

	/**
	 * Adds custom cron schedules to WordPress.
	 *
	 * This function adds two custom cron schedules: one that runs every three minutes
	 * and one that runs weekly. These schedules can be used to trigger WordPress cron jobs
	 * at the specified intervals.
	 *
	 * @param array $schedules An array of existing cron schedules.
	 *
	 * @return array The modified array of cron schedules with the custom intervals added.
	 */
	function cron_add_3min( $schedules ) {

		$schedules['3min']   = array(
			'interval' => 3 * 60,
			'display'  => __( 'Once every three minutes' ),
		);
		$schedules['weekly'] = array(
			'interval' => 60 * 60 * 24 * 7, // 604,800, seconds in a week.
			'display'  => __( 'Weekly' ),
		);
		return $schedules;
	}
	// Adding the custom cron schedules to WordPress.
	add_filter( 'cron_schedules', 'cron_add_3min' ); // phpcs:ignore

	/**
	 * Check the environment settings required for enabling the Square payment gateway.
	 *
	 * This function checks if the environment settings meet the requirements for enabling
	 * the Square payment gateway. It verifies the base country/region and currency settings
	 * depending on whether WooCommerce or mycred is active. If the settings do not meet the
	 * requirements, it displays an error message.
	 */
	function check_environment() {
		if ( ! is_allowed_countries() ) {
			if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
				$admin_page = 'wc-settings';
				echo '<div class="error">
					<p>'
					// translators: Error message placeholder in a log entry. Placeholder: Error details.
					. sprintf( esc_html__( 'To enable payment gateway Square requires that the <a href="%s">base country/region</a> is the United States,United Kingdom,Japan,Europe, Canada or Australia.', 'woosquare' ), esc_url( admin_url( 'admin.php?page=' . $admin_page . '&tab=general' ) ) ) . '</p>
				</div>';
			} elseif ( ( in_array( 'mycred/mycred.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) ) {
				$admin_page = 'mycred-gateways';
				echo '<div class="error">
					<p>'
					// translators: Error message placeholder in a log entry. Placeholder: Error details.
					. sprintf( esc_html__( 'To enable payment gateway Square requires that the <a href="%s">base country/region</a> is the United States,United Kingdom,Japan,Europe, Canada or Australia.', 'woosquare' ), esc_url( admin_url( 'admin.php?page=' . $admin_page ) ) ) . '</p>
				</div>';
			}
		}

		if ( ! is_allowed_currencies() ) {
			if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
				$admin_page = 'wc-settings';
				echo '<div class="error">
					<p>'
					// translators: Error message placeholder in a log entry. Placeholder: Error details.
					. sprintf( esc_html__( 'To enable payment gateway Square requires that the <a href="%s">currency</a> is set to USD,GBP,JPY,EUR, CAD or AUD.', 'woosquare' ), esc_url( admin_url( 'admin.php?page=' . $admin_page . '&tab=general' ) ) ) . '</p>
				</div>';
			} elseif ( ( in_array( 'mycred/mycred.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) ) {
				$admin_page = 'mycred-gateways';
				echo '<div class="error">
					<p>'
					// translators: Error message placeholder in a log entry. Placeholder: Error details.
					. sprintf( esc_html__( 'To enable payment gateway Square requires that the <a href="%s">currency</a> is set to USD,GBP,JPY,EUR, CAD or AUD ', 'woosquare' ), esc_url( admin_url( 'admin.php?page=' . $admin_page ) ) ) . '</p>
				</div>';
			}
		}
	}

	add_action( 'admin_notices', 'check_environment' );

	/**
	 * Check if allowed countries or country-related settings are set for WooCommerce or mycred plugin.
	 *
	 * This function checks if the base country or currency settings for WooCommerce or the
	 * mycred plugin are allowed. If they are not allowed, the function returns false. If neither
	 * WooCommerce nor mycred is active, it deactivates the plugin and displays an error message.
	 *
	 * @return bool True if allowed countries or settings are set, false otherwise.
	 */
	function is_allowed_countries() {

		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
			if ( 'US' !== WC()->countries->get_base_country()
				&& 'CA' !== WC()->countries->get_base_country()
				&& 'JP' !== WC()->countries->get_base_country()
				&& 'ES' !== WC()->countries->get_base_country()
				&& 'IE' !== WC()->countries->get_base_country()
				&& 'AU' !== WC()->countries->get_base_country()
				&& 'FR' !== WC()->countries->get_base_country()
				&& 'GB' !== WC()->countries->get_base_country()
			) {
				return false;
			}
		} elseif ( ( in_array( 'mycred/mycred.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) ) {
			$mycred_square_settings = get_option( 'mycred_pref_buycreds' );
			if ( $mycred_square_settings ) {

				if ( 'USD' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency']
					&& 'CAD' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency']
					&& 'JPY' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency']
					&& 'EUR' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency']
					&& 'AUD' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency']
					&& 'GBP' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency']
				) {
					return false;
				}
			}
		} else {
			$class   = 'notice notice-error';
			$message = __( 'To use WC Shop Sync WooCommerce or MYCRED must be installed and activated!', 'woosquare' );

			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}

		return true;
	}

	/**
	 * Handles the Square stock synchronization callback.
	 *
	 * This function processes incoming webhook notifications from Square, specifically to synchronize
	 * inventory data between Square and WooCommerce. It handles GET requests for initial testing,
	 * processes incoming POST data to update product and inventory information, and makes several
	 * API requests to Square to retrieve and update product information in WooCommerce.
	 *
	 * The function expects JSON data in the POST request body and processes it to update the corresponding
	 * WooCommerce products' stock levels, categories, images, and modifiers based on the Square inventory.
	 *
	 * @return void
	 */
	function square_stock_sync_handler() {
		global $wpdb;
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'] ) {
			echo die( 'Callback request working!' );
		}
		$post_data = json_decode( file_get_contents( 'php://input' ) );
		if ( ! $post_data ) {
			echo die( 'Callback request working with no post data' );
		}
		if ( isset( $post_data->event_type ) && 'TEST_NOTIFICATION' === $post_data->event_type ) {
			header( 'HTTP/1.1 200 OK' );
			die();
		}
		$get_var = 'get_var';
		$prepare = 'prepare';
		if ( ! empty( $post_data->data ) ) {
			$catalog_object_id = array();
			foreach ( $post_data->data->object->inventory_counts as $key => $inventory_counts ) {
				// Query the postmeta table to check if the meta_value exists.
				$result = $wpdb->$get_var( $wpdb->$prepare( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_value = %s", $inventory_counts->catalog_object_id ) );
				if ( isset( $result ) && $result > 0 ) {
					$catalog_object_id[] = $inventory_counts->catalog_object_id;
				}
			}
			$square   = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
			$response = array();
			$method   = 'POST';
			$url      = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/batch-retrieve';
			$headers  = array(
				'Authorization'  => 'Bearer ' . get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), // Use verbose mode in cURL to determine the format you want for this header.
				'Content-Type'   => 'application/json;',
				'Square-Version' => '2020-12-16',
			);
			$args     = array(
				'object_ids'              => $catalog_object_id,
				'include_related_objects' => true,
			);
			$response = $square->wp_remote_woosquare_v2( $url, $args, $method, $headers, $response );
			if ( ! empty( $response['response'] ) ) {
				if ( 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {
					$catalog_object = json_decode( $response['body'], false );

					foreach ( $catalog_object as $object ) {
						if ( isset( $object->type ) && 'ITEM' === $object->type && isset( $object->item_data->variations ) ) {
							foreach ( $object->item_data->variations as $variation ) {
								if ( isset( $variation->item_variation_data->sku ) ) {
									$sku      = $variation->item_variation_data->sku;
									$id       = $variation->id;
									$quantity = null;
									if ( isset( $post_data->data->object->inventory_counts ) && is_array( $post_data->data->object->inventory_counts ) ) {
										foreach ( $post_data->data->object->inventory_counts as $inventory ) {
											if ( isset( $inventory->catalog_object_id ) && $inventory->catalog_object_id === $id ) {
												$quantity = $inventory->quantity;
												break;
											}
										}
									}
									// SKU se product ID log.
									$product_id = $wpdb->$get_var(
										$wpdb->$prepare(
											"
										SELECT post_id 
										FROM $wpdb->postmeta 
										WHERE meta_key = '_sku' 
										AND meta_value = %s
										LIMIT 1
									",
											$sku
										)
									);
									$product_id = $product_id ? intval( $product_id ) : null;
									if ( ! empty( $product_id ) && ! empty( $quantity ) ) {
										$product = wc_get_product( $product_id );
										$product->set_stock_quantity( $quantity );
										$product->set_manage_stock( true );
										$product->save();
									}
								}
							}
						}
					}
				}
			}
		}
		die();
	}

	/**
	 * Check if allowed currencies are set for WooCommerce or mycred plugin.
	 *
	 * This function checks if the base currency for WooCommerce or the currency setting
	 * for the mycred plugin is allowed. If the currency is not in the allowed list,
	 * the function returns false. If neither WooCommerce nor mycred is active, it deactivates
	 * the plugin and displays an error message.
	 *
	 * @return bool True if allowed currencies are set, false otherwise.
	 */
	function is_allowed_currencies() {

		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
			if ( 'US' !== WC()->countries->get_base_country()
				&& 'CA' !== WC()->countries->get_base_country()
				&& 'JP' !== WC()->countries->get_base_country()
				&& 'ES' !== WC()->countries->get_base_country()
				&& 'IE' !== WC()->countries->get_base_country()
				&& 'AU' !== WC()->countries->get_base_country()
				&& 'FR' !== WC()->countries->get_base_country()
				&& 'GB' !== WC()->countries->get_base_country()
			) {
				return false;
			}
		} elseif ( ( in_array( 'mycred/mycred.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) ) {

			// get currency.
			$mycred_square_settings = get_option( 'mycred_pref_buycreds' );
			if ( $mycred_square_settings ) {
				if ( 'USD' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency']
					&& 'CAD' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency']
					&& 'JPY' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency']
					&& 'EUR' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency']
					&& 'AUD' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency']
					&& 'GBP' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency']
				) {
					return false;
				}
			}
		} else {
			$class   = 'notice notice-error';
			$message = __( 'To use Woosquare. WooCommerce OR MYCRED Currency must be USD,CAD,AUD,EUR', 'woosquare' );
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}

		return true;
	}

	/**
	 * Disable the Square payment gateway under certain conditions.
	 *
	 * This function is used to filter and modify the list of available payment gateways
	 * based on specific conditions. It checks for the presence of the 'square' gateway
	 * and various conditions like SSL usage, Square Plus settings, and user roles to
	 * determine whether to disable the 'square' gateway.
	 *
	 * @param array $available_gateways An array of available payment gateways.
	 * @return array The modified array of available payment gateways.
	 */
	function payment_gateway_disable_country( $available_gateways ) {
		global $woocommerce;

		if ( isset( $available_gateways['square'] ) && ! is_ssl() ) {
			unset( $available_gateways['square'] );
		}

		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
		if ( isset( $woocommerce_square_plus_settings['enabled'] ) && 'no' === $woocommerce_square_plus_settings['enabled'] ) {
			unset( $available_gateways['square'] );
		} elseif ( get_transient( 'is_sandbox' ) ) {
			if ( current_user_can( 'activate_plugins' ) !== 1 ) {
				// user is an admin.
				unset( $available_gateways['square'] );
			}
		}

		return $available_gateways;
	}

	add_filter( 'woocommerce_available_payment_gateways', 'payment_gateway_disable_country' );

}
