<?php
/**
 * The product-sync functionality of the plugin.
 *
 * @package Woosquare_Plus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
define( 'WOO_SQUARE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WOO_SQUARE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

define( 'WOO_SQUARE_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );
if ( ! empty( get_transient( 'is_sandbox' ) ) ) {
	if ( ! defined( 'WC_SQUARE_ENABLE_STAGING' ) ) {
		define( 'WC_SQUARE_ENABLE_STAGING', true );
		define( 'WC_SQUARE_STAGING_URL', 'squareupsandbox' );
	}
} elseif ( ! defined( 'WC_SQUARE_ENABLE_STAGING' ) ) {
	define( 'WC_SQUARE_ENABLE_STAGING', false );
	define( 'WC_SQUARE_STAGING_URL', 'squareup' );
}

add_action( 'wp_ajax_manual_sync', 'woo_square_manual_sync' );


if ( ! get_option( 'v2_converted_cat' ) ) {
	add_action( 'plugins_loaded', 'woo_square_v2_converted_cat' );
}

	add_action( 'save_post', 'woo_square_add_edit_product', 10, 3 );

if ( get_option( 'disable_auto_delete' ) !== 1 ) {
	add_action( 'before_delete_post', 'woo_square_delete_product' );
}

	$sync_on_add_edit = get_option( 'sync_on_add_edit', $default = false );

if ( 1 === intval( $sync_on_add_edit ) ) {
	add_action( 'create_product_cat', 'woo_square_add_category' );
	add_action( 'edited_product_cat', 'woo_square_edit_category' );
	add_action( 'delete_product_cat', 'woo_square_delete_category', 10, 3 );
}
	add_action( 'woocommerce_order_refunded', 'woo_square_create_refund', 10, 2 );
	add_action( 'woocommerce_order_status_processing', 'woo_square_complete_order' );


	add_action( 'wp_loaded', 'post_savepage_load_admin_notice' );

// ADDED ACTION TO CATCH DUPLICATE PRODUCT AND REMOVE META DATA.
	add_action( 'woocommerce_product_duplicate_before_save', 'catch_duplicate_product', 1, 2 );
/**
 * Handle duplicate product and remove specific meta data.
 *
 * This function is used as a callback for the 'woocommerce_product_duplicate_before_save' action.
 * It takes the duplicate product object and the original product as parameters and removes
 * specific meta data from the duplicate product.
 *
 * @param WC_Product $duplicate The duplicated product object.
 * @param WC_Product $product   The original product object.
 */
function catch_duplicate_product( $duplicate, $product ) { // phpcs:ignore
	// Remove specific meta data from the duplicate product.
	$duplicate->delete_meta_data( 'square_id' );
	$duplicate->delete_meta_data( '_square_item_id' );
	$duplicate->delete_meta_data( '_square_item_variation_id' );
}

/**
 * Modify the recipient for new order emails in the admin.
 *
 * @param string   $recipient The email recipient.
 * @param WC_Order $order The WooCommerce order object.
 * @return string The modified email recipient.
 */
function wc_change_admin_new_order_email_recipient( $recipient, $order ) {
	if ( $order ) {
		$customer_id = get_post_meta( $order->get_id(), '_customer_user', true );
		$user_info   = get_userdata( $customer_id );
		update_option( 'square_new_email', $user_info->user_nicename );
		// check if product in order.
		if ( 'square_user' === $user_info->user_nicename ) {
			$recipient = '';
		} else {
			$recipient = $recipient;
		}
	}
	return $recipient;
}
if ( get_option( 'sync_square_order_notify' ) === 1 ) {
	add_filter( 'woocommerce_email_recipient_new_order', 'wc_change_admin_new_order_email_recipient', 1, 2 );
}

/**
 * Convert and update WooCommerce category options based on Square category information.
 *
 * This function checks if category options need to be converted and updated based on
 * Square category information. It retrieves Square categories, compares them to existing
 * options, and updates options as needed.
 */
function check_or_add_plugin_tables() {
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

	// logs table.
	$sync_logs_table = $wpdb->prefix . WOO_SQUARE_TABLE_SYNC_LOGS;
	$get_var         = 'get_var';
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
	 * Include script
	 */
function woo_square_script() {
	wp_enqueue_script( 'woo_square_script', WOO_SQUARE_PLUGIN_URL . '_inc/js/script.js', array( 'jquery' ), '1.0', true );
	$localize_array = array(
		'ajaxurl'   => admin_url( 'admin-ajax.php' ),
		'ajaxnonce' => wp_create_nonce( 'my_woosquare_ajax_nonce' ),
	);
	wp_localize_script( 'woo_square_script', 'myAjax', $localize_array );

	wp_enqueue_style( 'woo_square_pop-up', WOO_SQUARE_PLUGIN_URL . '_inc/css/pop-up.css', array(), '1.0', 'all' );
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
			$get_result        = 'get_results';
			$square_cat_option = $wpdb->$get_result( $sql, ARRAY_A );
			foreach ( $square_categories as $square_category ) {
				foreach ( $square_cat_option as $woocat ) {

					if ( ! empty( $square_category->catalog_v1_ids ) ) {
						if ( $square_category->catalog_v1_ids[0]->catalog_v1_id !== $woocat['option_value'] ) {
							$v2explodedform = explode( '-', $woocat['option_value'] );

							if ( count( $v2explodedform ) > 1 ) {
								delete_option( $woocat['option_name'] );
							}
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
		echo 'There is another Synchronization process running. Please try again later. Or <a href="' . esc_url( admin_url( 'admin.php?page=square-item-sync&terminate_sync=true' ) ) . '"> terminate now </a>';
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
				||
				! get_option( 'woosquare_module_updated_content1' ) ) {
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

			$product_square_id = get_post_meta( $post_id, 'square_id', true );
			$square            = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );

			$square_synchronizer = new WooToSquareSynchronizer( $square );

			$square                     = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
			$square_to_woo_synchronizer = new SquareToWooSynchronizer( $square );
			$square_items               = $square_to_woo_synchronizer->get_square_items();

			if ( $square_items ) {
				$square_items = $square_synchronizer->simplify_square_items_object( $square_items );
			} else {
				$square_items = array();
			}

			$product_square_id = $square_synchronizer->check_sku_in_square( $post, $square_items );

			if ( ! $product_square_id ) {
					// not exist in square so check in woo this product already updated.
					$product_square_id = get_post_meta( $post->ID, 'square_id', true );
				if ( $product_square_id ) {
					$exploded_product_square_id = explode( '-', $product_square_id );
					if ( count( $exploded_product_square_id ) === 5 ) {

						$product = wc_get_product( $post->ID );

						$response = array();

						$method = 'POST';
						$url    = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/search';

						$headers = array(
							'Authorization'  => 'Bearer ' . get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), // Use verbose mode in cURL to determine the format you want for this header.
							'Content-Type'   => 'application/json;',
							'Square-Version' => '2020-12-16',
						);

						$args     = array(
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
						if ( ! empty( $square_product->related_objects ) ) {
							foreach ( $square_product->related_objects as $obj ) {
								if ( 'ITEM' === $obj->type && $product_square_id === $obj->catalog_v1_ids[0]->catalog_v1_id ) {
									$product_square_id = $obj->id;
								}
							}
						}
					} else {
							$product_square_id = '';
					}
				}
			}

			$result = $square_synchronizer->add_product( $post, $product_square_id );
			$add_pro_data[ $post_id ][ $result['pro_status'] ] = $result;
			$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
			if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
				$woosquare_sync_log                                = new WooSquare_Sync_Logs();
				$log_id = $woosquare_sync_log->log_data_request( $add_pro_data, '', 'woo_to_square', 'product' );
			}
			$termid = get_post_meta( $post_id, '_termid', true );
			if ( '' === $termid ) {// new product.
				$termid = 'update';
			}
			update_post_meta( $post_id, '_termid', $termid );

			if ( true === $result ) {
				$product_data = array();
				$update_pro   = array();
				$add_pro      = array();
				$failed_pro   = array();
				$product      = wc_get_product( $post_id );
				$sku          = $product->get_sku();
				if ( isset( $product ) && 'variable' === $product->get_type() ) {
					$product_variation_skus = '';
					$variations             = $product->get_available_variations();
					$variations_id          = wp_list_pluck( $variations, 'variation_id' );
					foreach ( $variations_id as $var_id ) {
						$product_var             = wc_get_product( $var_id );
						$product_variation_skus .= $product_var->get_sku() . ', ';
					}
					$sku = $product_variation_skus;
				}
				$product_data[ $post_id ] = array(
					'name'    => $product->get_name(),
					'sku'     => $sku,
					'status'  => $sync_pro['pro_status'],
					'message' => $sync_pro['message'],
				);
				if ( 'update' === $sync_pro['pro_status'] ) {
					$update_pro[ $post_id ] = $sync_pro['pro_status'];
				} elseif ( 'add' === $sync_pro['pro_status'] ) {
					$add_pro[ $post_id ] = $sync_pro['pro_status'];
				} elseif ( 'failed' === $sync_pro['pro_status'] ) {
					$failed_pro[ $post_id ] = $sync_pro['pro_status'];
				}
				$update_count       = count( $update_pro );
				$add_count          = count( $add_pro );
				$failed_count       = count( $failed_pro );
				$woosquare_sync_log = new WooSquare_Sync_Logs();
				$message            = '';
				if ( isset( $add_count ) && $add_count > 0 ) {
					$dd       = $add_count > 1 ? 'Products, ' : 'Product, ';
					$message .= 'Added ' . $add_count . ' ' . $dd;
				}
				if ( isset( $update_count ) && $update_count > 0 ) {
					$dd       = $update_count > 1 ? 'Products, ' : 'Product, ';
					$message .= 'Updated ' . $update_count . ' ' . $dd;
				}
				if ( isset( $failed_count ) && $failed_count > 0 ) {
					$dd       = $failed_count > 1 ? 'Products ' : 'Product ';
					$message .= 'Failed ' . $failed_count . ' ' . $dd;
				}

				if ( $add_count > 0 && $update_count > 0 && $failed_count > 0 ) {
					$status = 'Sync Partially';
				} elseif ( 0 < $add_count && 0 === $update_count && 0 < $failed_count ) {
					$status = 'Sync Partially';
				} elseif ( 0 === $add_count && 0 < $update_count && 0 < $failed_count ) {
					$status = 'Sync Partially';
				} elseif ( 0 === $add_count && 0 === $update_count && 0 < $failed_count ) {
					$status = 'Sync Failed';
				} else {
					$status = 'Sync Successful';
				}

				$date = gmdate( 'Y-m-d H:i:s' );
				$woosquare_sync_log->woosquare_item_sync_logs(
					$date,
					$status,
					$message,
					'woo_to_square',
					'',
					wp_json_encode( $product_data ),
				);
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
	session_start();
	unset( $_SESSION['woo_product_delete_log'] );
	delete_transient( 'woo_product_delete_log' );
	delete_transient( 'woo_delete_product_log_id' );
	$sync_on_add_edit = get_option( 'sync_on_add_edit', $default = false );

	if ( 1 === intval( $sync_on_add_edit ) ) {
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
			if ( get_option( 'disable_auto_delete' ) !== 1 ) {
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

				$_SESSION['woo_product_delete_log'][ $delt_pro->get_id() ]['delete'] = $delt_pro_array;
				
				if ( isset( $_SESSION['woo_product_delete_log'] ) ) {
					set_transient( 'woo_product_delete_log', $_SESSION['woo_product_delete_log'], 300 ); // phpcs:ignore
				}
				$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
				if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
					$woosquare_sync_log = new WooSquare_Sync_Logs();
					$log_id             = $woosquare_sync_log->delete_product_log_data_request( get_transient( 'woo_product_delete_log' ), get_transient( 'woo_delete_product_log_id' ), 'product', 'woo_to_square' );
				}
				if ( ! empty( $log_id ) ) {
					set_transient( 'woo_delete_product_log_id', $log_id, 300 );
				}
	
				$delete = 'delete';
				$wpdb->$delete(
					$wpdb->prefix . WOO_SQUARE_TABLE_DELETED_DATA,
					array( 'square_id' => $product_square_id )
				);
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
	$_SESSION['woo_product_sync_log'][ $category_id ][ $result['pro_status'] ] = $result;
	if ( isset( $_SESSION['woo_product_sync_log'] ) ) {
		$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
		if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
			$woosquare_sync_log      = new WooSquare_Sync_Logs();
			$woo_product_sync_log    = sanitize_text_field( wp_unslash( $_SESSION['woo_product_sync_log'] ) );
			$woo_product_sync_log_id = isset( $_SESSION['woo_product_sync_log_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['woo_product_sync_log_id'] ) ) : '';
			$log_id                  = $woosquare_sync_log->log_data_request( $woo_product_sync_log, $woo_product_sync_log_id, 'woo_to_square', 'category' );
		}
	}
	if ( ! empty( $log_id ) ) {
		$_SESSION['woo_product_sync_log_id'] = $log_id;
	}
	if ( true === $result['status'] ) {
		update_option( "is_square_sync_{$category_id}", 1 );
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
	$_SESSION['woo_product_sync_log'][ $category_id ][ $result['pro_status'] ] = $result;
	if ( isset( $_SESSION['woo_product_sync_log'] ) ) {
		$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
		if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
			$woosquare_sync_log      = new WooSquare_Sync_Logs();
			$woo_product_sync_log    = sanitize_text_field( wp_unslash( $_SESSION['woo_product_sync_log'] ) );
			$woo_product_sync_log_id = isset( $_SESSION['woo_product_sync_log_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['woo_product_sync_log_id'] ) ) : '';
			$log_id                  = $woosquare_sync_log->log_data_request( $woo_product_sync_log, $woo_product_sync_log_id, 'woo_to_square', 'category' );
		}
	}
	if ( ! empty( $log_id ) ) {
		$_SESSION['woo_product_sync_log_id'] = $log_id;
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
	// Avoid auto save from calling Square APIs.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	$category_square_id = get_option( 'category_square_id_' . $category_id );

	// delete category options.
	delete_option( "is_square_sync_{$category_id}" );
	delete_option( "category_square_id_{$category_id}" );

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
	$result              = $square_synchronizer->delete_category( $category_square_id );

	// delete product from plugin delete table.
	if ( true === $result ) {

		$delt_pro_array = array(
			'name'    => esc_html( $deleted_category->name ), // Sanitize category name.
			'status'  => 'deleted',
			'item'    => 'category',
			'message' => __( 'Successfully Deleted', 'woosquare' ),
		);

		$woo_product_delete_log_transient[ $category_id ]['delete'] = $delt_pro_array;
		$woo_product_delete_log_transient                           = array_merge( $woo_product_delete_log_transientt, $woo_product_delete_log_transient );
		set_transient( 'woo_product_delete_log_transient', $woo_product_delete_log_transient, 300 );
		$woo_product_delete_log_id_transient = get_transient( 'woo_product_delete_log_id_transient' );
		$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
		if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
			$woosquare_sync_log                  = new WooSquare_Sync_Logs();
			$log_id                              = $woosquare_sync_log->delete_product_log_data_request( $woo_product_delete_log_transient, $woo_product_delete_log_id_transient, 'category', 'woo_to_square' );
			if ( ! empty( $log_id ) ) {
				set_transient( 'woo_product_delete_log_id_transient', $log_id, 300 );
			}
		}
		$delete = 'delete';
		$wpdb->$delete(
			$wpdb->prefix . WOO_SQUARE_TABLE_DELETED_DATA,
			array( 'square_id' => $category_square_id )
		);

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
	if ( ! get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) ) {
		return;
	}
	// Avoid auto save from calling Square APIs.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( get_post_meta( $order_id, 'woosquare_transaction_id', true ) ) {

		$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
		$square->refund( $order_id, $refund_id );
	}
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
	 * Check required environment
	 *
	 * @access public
	 * @since 1.0.10
	 * @version 1.0.10
	 * @return null
	 */
	add_action( 'admin_notices', 'check_environment' );

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
					. sprintf( esc_html__( 'To enable payment gateway Square requires that the <a href="%s">base country/region</a> is the United States, United Kingdom, Japan, Canada, or Australia.', 'woosquare' ), esc_url( admin_url( 'admin.php?page=' . $admin_page . '&tab=general' ) ) ) . '</p>
				</div>';
		} elseif ( in_array( 'mycred/mycred.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
			$admin_page = 'mycred-gateways';
			echo '<div class="error">
					
					<p>'
					// translators: Error message placeholder in a log entry. Placeholder: Error details.
					. sprintf( esc_html__( 'To enable payment gateway Square requires that the <a href="%s">base country/region</a> is the United States,United Kingdom,Japan, Canada or Australia.', 'woosquare' ), esc_url( admin_url( 'admin.php?page=' . $admin_page ) ) ) . '</p>
				</div>';
		}
	}

	if ( ! is_allowed_currencies() ) {
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
			$admin_page = 'wc-settings';
			echo '<div class="error">
					<p>'
					// translators: Error message placeholder in a log entry. Placeholder: Error details.
					. sprintf( esc_html__( 'To enable payment gateway Square requires that the <a href="%s">currency</a> is set to USD,GBP,JPY, CAD or AUD.', 'woosquare' ), esc_url( admin_url( 'admin.php?page=' . $admin_page . '&tab=general' ) ) ) . '</p>
				</div>';
		} elseif ( in_array( 'mycred/mycred.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
			$admin_page = 'mycred-gateways';
			echo '<div class="error">
					<p>'
					// translators: Error message placeholder in a log entry. Placeholder: Error details.
					. sprintf( esc_html__( 'To enable payment gateway Square requires that the <a href="%s">currency</a> is set to USD,GBP,JPY, CAD or AUD ', 'woosquare' ), esc_url( admin_url( 'admin.php?page=' . $admin_page ) ) ) . '</p>
				</div>';
		}
	}
}


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
		if (
				'US' !== WC()->countries->get_base_country() &&
				'CA' !== WC()->countries->get_base_country() &&
				'JP' !== WC()->countries->get_base_country() &&
				'IE' !== WC()->countries->get_base_country() &&
				'ES' !== WC()->countries->get_base_country() &&
				'AU' !== WC()->countries->get_base_country() &&
				'GB' !== WC()->countries->get_base_country()
		) {
			return false;
		}
	} elseif ( in_array( 'mycred/mycred.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
		$mycred_square_settings = get_option( 'mycred_pref_buycreds' );
		if ( $mycred_square_settings ) {

			if (
					'USD' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency'] &&
					'CAD' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency'] &&
					'JPY' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency'] &&
					'EUR' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency'] &&
					'AUD' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency'] &&
					'GBP' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency']
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
		if (
				'US' !== WC()->countries->get_base_country() &&
				'CA' !== WC()->countries->get_base_country() &&
				'JP' !== WC()->countries->get_base_country() &&
				'IE' !== WC()->countries->get_base_country() &&
				'ES' !== WC()->countries->get_base_country() &&
				'AU' !== WC()->countries->get_base_country() &&
				'GB' !== WC()->countries->get_base_country()
		) {
			return false;
		}
	} elseif ( in_array( 'mycred/mycred.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
		// get currency.
		$mycred_square_settings = get_option( 'mycred_pref_buycreds' );
		if ( $mycred_square_settings ) {
			if (
					'USD' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency'] &&
					'CAD' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency'] &&
					'JPY' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency'] &&
					'EUR' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency'] &&
					'AUD' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency'] &&
					'GBP' !== $mycred_square_settings['gateway_prefs']['mycred_square']['currency']
			) {
				return false;
			}
		}
	} else {
		$class   = 'notice notice-error';
		$message = __( 'To use Woosquare. WooCommerce OR MYCRED Currency must be USD,CAD,AUD', 'woosquare' );
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
	} elseif ( ! empty( get_transient( 'is_sandbox' ) ) ) {
		if ( current_user_can( 'activate_plugins' ) !== 1 ) {
			// user is an admin.
			unset( $available_gateways['square'] );
		}
	}

	return $available_gateways;
}

	add_filter( 'woocommerce_available_payment_gateways', 'payment_gateway_disable_country' );

// }
