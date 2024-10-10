<?php
/**
 * Woosquare_Plus v1.0 by wpexperts.io
 *
 * @package Woosquare_Plus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Check synchronization start conditions.
 *
 * This function checks if the conditions required to start a synchronization process are met.
 *
 * @return mixed Returns true if conditions are met, or a message if conditions are not met.
 */
function check_sync_start_conditions() {

	if ( ! get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) ) {
		return 'Invalid square access token';
	}

	if ( get_option( 'woo_square_running_sync' ) && ( time() - (int) get_option( 'woo_square_running_sync_time' ) ) < ( 20 * 60 ) ) {
		return 'There is another Synchronization process running. Please try again later. Or <a href="' . admin_url( 'admin.php?page=square-item-sync&terminate_sync=true' ) . '" > terminate now </a>';
	}

	return true;
}


/**
 * Gets non-synchronized WooCommerce data.
 *
 * This function gets all WooCommerce data that has not yet been synchronized with Square.
 */
function woo_square_plugin_get_non_sync_woo_data() {

	if ( ! isset( $_REQUEST['woosquare_popup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['woosquare_popup_nonce'] ) ), 'my_woosquare_ajax_nonce' ) ) {
		exit();
	}
	$check_flag  = check_sync_start_conditions();
	$total_pages = 0;
	$limit       = 0;
	if ( true !== $check_flag ) {
		die( wp_json_encode( array( 'error' => $check_flag ) ) ); }

	$square                     = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
	$synchronizer               = new WooToSquareSynchronizer( $square );
	$square_to_woo_synchronizer = new SquareToWooSynchronizer( $square );

	// for display.
	$add_products      = array();
	$update_products   = array();
	$delete_products   = array();
	$add_categories    = array();
	$update_categories = array();
	$delete_categories = array();
	// display all products in update.
	$one_products_update_checkbox = false;

	// 1-get un-syncronized categories ( having is_square_sync = 0 or key not exists )
	$categories        = $synchronizer->get_unsynchronized_categories();
	$square_categories = $synchronizer->get_categories_square_ids( $categories );

	$target_categories = array();
	$excluded_products = array();

	// merge add and update categories.
	foreach ( $categories as $cat ) {
		if ( ! isset( $square_categories[ $cat->term_id ] ) ) {      // add.
			$target_categories[ $cat->term_id ]['action']    = 'add';
			$target_categories[ $cat->term_id ]['square_id'] = null;
			$add_categories[]                                = array(
				'woo_id'       => $cat->term_id,
				'checkbox_val' => $cat->term_id,
				'name'         => $cat->name,
			);
		} else {                                          // update.
			$target_categories[ $cat->term_id ]['action']    = 'update';
			$target_categories[ $cat->term_id ]['square_id'] = $square_categories[ $cat->term_id ];
			$update_categories[]                             = array(
				'woo_id'       => $cat->term_id,
				'checkbox_val' => $cat->term_id,
				'name'         => $cat->name,
			);
		}

		$target_categories[ $cat->term_id ]['name'] = $cat->name;
	}
		$square_categories = $square_to_woo_synchronizer->get_square_categories();

		// check for new category that not exist in square.
	if ( ! empty( $target_categories ) ) {
		foreach ( $target_categories as $cats ) {
			if ( ! empty( $cats['square_id'] ) ) {
				$woosyncsquid[] = $cats['square_id'];
			}
		}
		if ( ! empty( $square_categories ) ) {
			foreach ( $square_categories as $squcat ) {
				$squsync[] = $squcat->id;
			}
		}
	}

	if ( ! empty( $woosyncsquid ) && ! empty( $squsync ) ) {
		foreach ( array_diff( $woosyncsquid, $squsync ) as $unique ) {
			foreach ( $target_categories as $ky => $trcat ) {
				if ( $trcat['square_id'] === $unique ) {
					$target_categories[ $ky ]['square_id'] = '';
					$target_categories[ $ky ]['action']    = 'add';
				}
			}
		}
	}

	// 2-get un-syncronized products ( having is_square_sync = 0 or key not exists )
	$products       = $synchronizer->get_unsynchronized_products();
	$square_poducts = $synchronizer->get_products_square_ids( $products, $excluded_products );

	$target_products = array();

	$total = count( $products );
	if ( ! isset( $_SESSION ) ) {
		session_start();
	}
	if ( empty( $_GET['page'] ) ) {
		$_SESSION = array();
	}
	if ( $total > 999 ) {

		$page        = ! empty( $_GET['page'] ) ? (int) $_GET['page'] : 1;
		$total       = count( $products ); // total items in array.
		$limit       = 999; // per page.
		$total_pages = ceil( $total / $limit ); // calculate total pages.
		$page        = max( $page, 1 ); // get 1 page when $_GET['page'] <= 0.
		$page        = min( $page, $total_pages ); // get last page when $_GET['page'] > $total_pages.
		$offset      = ( $page - 1 ) * $limit;
		if ( $offset < 0 ) {
			$offset = 0;
		}

		$products = array_slice( $products, $offset, $limit );
	}

	// merge add and update items.
	foreach ( $products as $product ) {

		// skip simple products with empty sku.

		$product_id = $product->ID; // the ID of the product to check.
		$_product   = wc_get_product( $product_id );
		
        if( WC_Product_Factory::get_product_type($product_id) == 'simple' ) {
			// do stuff for simple products.
			$sku = get_post_meta( $product->ID, '_sku', true );
			if ( empty( $sku ) ) {
				$sku_missin_inside_product[] = array(
					'woo_id'                    => $product->ID,
					'checkbox_val'              => $product->ID,
					'name'                      => $product->post_title,
					'sku_missin_inside_product' => 'sku_missin_inside_product',
				);

			}
        } else if(  WC_Product_Factory::get_product_type($product_id) == 'variable'  ) {
			$tickets   = new WC_Product_Variable( $product_id );
			$variables = $tickets->get_available_variations();

			if ( ! empty( $variables ) ) {
				foreach ( $variables as $var_checkin ) {

					if ( empty( $var_checkin['sku'] ) ) {
						$sku_missin_inside_product[] = array(
							'woo_id'                    => $product->ID,
							'checkbox_val'              => $product->ID,
							'name'                      => $product->post_title . ' variations of "' . $var_checkin['attributes']['attribute_var1'] . '" sku missing kindly click here update it.',
							'sku_missin_inside_product' => 'sku_missin_inside_product',
						);
						break;
					}
				}
			}

			// do stuff for variable.
		}

		if ( in_array( $product->ID, $excluded_products, true ) ) {
			continue;
		}

		$modifier_value = get_post_meta( $product->ID, 'product_modifier_group_name', true );

		$modifier_set_name = array();

		if ( ! empty( $modifier_value ) ) {

			if ( ! empty( $modifier_value ) ) {
				$kkey = 0;

				foreach ( $modifier_value as $mod ) {

					$mod = ( explode( '_', $mod ) );

					if ( ! empty( $mod[2] ) ) {

						global $wpdb;
						$get_var = 'get_var';
						$rcount  = $wpdb->$get_var( 'SELECT modifier_set_unique_id FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_id = '$mod[2]' " );

						$get_result   = 'get_results';
						$raw_modifier = $wpdb->$get_result( "SELECT * FROM {$wpdb->prefix}woosquare_modifier WHERE modifier_id = '$mod[2]';" );

						foreach ( $raw_modifier as $raw ) {
							$mod_ids = '';
							if ( ! empty( $raw->modifier_set_unique_id ) ) {
								$mod_ids = $raw->modifier_set_unique_id;
							} else {
								$mod_ids = $raw->modifier_id;
							}

							$modifier_set_name[ $kkey ] = $raw->modifier_set_name . '|' . $raw->modifier_set_unique_id . '|' . $raw->modifier_id . '|' . $raw->modifier_public . '|' . $raw->modifier_version . '|' . $raw->modifier_slug;
							++$kkey;

						}
					}
				}
			}
		} else {
			$modifier_set_name = array();
		}

		if ( isset( $square_poducts[ $product->ID ] ) ) {     // update.
			$target_products[ $product->ID ]['action']    = 'update';
			$target_products[ $product->ID ]['square_id'] = $square_poducts[ $product->ID ];
			$update_products[]                            = array(
				'woo_id'            => $product->ID,
				'checkbox_val'      => $product->ID,
				'name'              => $product->post_title,
				'modifier_set_name' => $modifier_set_name,
				'direction'         => 'woo_to_square',
			);

		} else {                                       // add.
			$target_products[ $product->ID ]['action']    = 'add';
			$target_products[ $product->ID ]['square_id'] = null;
			$add_products[]                               = array(
				'woo_id'            => $product->ID,
				'checkbox_val'      => $product->ID,
				'name'              => $product->post_title,
				'modifier_set_name' => $modifier_set_name,
				'direction'         => 'woo_to_square',
			);
		}

		$target_products[ $product->ID ]['name'] = $product->post_title;

	}

	// 3-get deleted elements failed to be synchronized.
	$deleted_elms = $synchronizer->get_unsynchronized_deleted_elements();

	// merge deleted items and categories with their corresponding arrays.
	foreach ( $deleted_elms as $elm ) {

		if ( Helpers::TARGET_TYPE_PRODUCT === $elm->target_type ) {   // PRODUCT.
			$target_products[ $elm->target_id ]['square_id'] = $elm->square_id;
			$target_products[ $elm->target_id ]['action']    = 'delete';
			$target_products[ $elm->target_id ]['name']      = $elm->name;

			// for display.
			$delete_products[] = array(
				'woo_id'       => null,
				'checkbox_val' => $elm->target_id,
				'name'         => $elm->name,
			);
		} else {                                                                  // CATEGORY.
			$target_categories[ $elm->target_id ]['square_id'] = $elm->square_id;
			$target_categories[ $elm->target_id ]['action']    = 'delete';
			$target_categories[ $elm->target_id ]['name']      = $elm->name;
			$delete_categories[]                               = array(
				'woo_id'       => null,
				'checkbox_val' => $elm->target_id,
				'name'         => $elm->name,
			);
		}
	}

	// 4-get all square items simplified.

	$square_to_woo_synchronizer = new SquareToWooSynchronizer( $square );
	$square_items               = $square_to_woo_synchronizer->get_square_items();

	$square_items_modified = array();
	if ( $square_items ) {
		$square_items_modified = $synchronizer->simplify_square_items_object( $square_items );
	}

	// construct session array.
	if ( ! isset( $_SESSION['woo_to_square']['target_products'] ) ) {
		$_SESSION['woo_to_square']['target_products'] = array();
	}

	if ( isset( $_SESSION['woo_to_square']['target_products'] ) ) {

		if ( ! empty( $_SESSION['woo_to_square']['target_products'] ) ) {
			$session_target_products = array_map( 'sanitize_text_field', $_SESSION['woo_to_square']['target_products'] );

			foreach ( $session_target_products as $kys => $ses ) {
				$target_products[ $kys ] = $session_target_products;
			}
		}

		$_SESSION['woo_to_square']['target_products'] = $target_products;
	}

	$_SESSION['woo_to_square']['target_categories'] = $target_categories;
	// add simplified object to session.
	set_transient( 'woo_to_square-square_items', $square_items_modified, 2000 );

	ob_start();
	include plugin_dir_path( __DIR__ ) . '../views/partials/pop-up.php';
	$data = ob_get_clean();
	if ( empty( $offset ) ) {
		$offset = 0;
	}
	echo wp_json_encode(
		array(
			'data'           => $data,
			'offset'         => $offset,
			'totalPages'     => $total_pages,
			'targetProducts' => ! empty( $_SESSION['woo_to_square']['target_products'] ) ? count( $_SESSION['woo_to_square']['target_products'] ) : '',
			'limit'          => $limit,
		)
	);
	die();
}

/**
 * Starts a manual WooCommerce to Square sync.
 *
 * This function starts a manual sync of all WooCommerce data to Square.
 *
 * @return void
 */
function woo_square_plugin_start_manual_woo_to_square_sync() {

	$check_flag = check_sync_start_conditions();
	if ( true !== $check_flag ) {
		die( esc_html( $check_flag ) ); }

	update_option( 'woo_square_running_sync', 'manual' );
	update_option( 'woo_square_running_sync_time', time() );

	unset( $_SESSION['woo_product_sync_log'] );
	unset( $_SESSION['woo_product_sync_log_id'] );
	unset( $_SESSION['woo_product_delete_log'] );
	unset( $_SESSION['woo_delete_product_log_id'] );
	delete_transient( 'woo_product_delete_log' );
	delete_transient( 'woo_delete_product_log_id' );
	delete_transient( 'woo_product_sync_log_transient' );
	delete_transient( 'woo_product_sync_log_id_transient' );
	delete_transient( 'woo_product_delete_log_transient' );
	delete_transient( 'woo_delete_product_log_id_transient' );
	session_start();

	$_SESSION['woo_to_square']['target_products']['parent_id'] = Helpers::sync_db_log(
		Helpers::ACTION_SYNC_START,
		gmdate( 'Y-m-d H:i:s' ),
		Helpers::SYNC_TYPE_MANUAL,
		Helpers::SYNC_DIRECTION_WOO_TO_SQUARE
	);
	if ( isset( $_SESSION['woo_to_square']['target_products']['parent_id'] ) ) {
		$_SESSION['woo_to_square']['target_categories']['parent_id'] = sanitize_text_field( wp_unslash( $_SESSION['woo_to_square']['target_products']['parent_id'] ) );
	}
	echo '1';
	die();
}

/**
 * Synchronize a WooCommerce category to Square.
 *
 * This function handles the synchronization of a WooCommerce category to Square based on the provided parameters.
 *
 * @return void Outputs the result of the synchronization process.
 */
function woo_square_plugin_sync_woo_category_to_square() {
	if ( ! isset( $_POST['ajaxnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ajaxnonce'] ) ), 'my_woosquare_ajax_nonce' ) ) {
		wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
	}
	$woo_product_sync_log_transientt = get_transient( 'woo_product_sync_log_transient' );
	if ( empty( $woo_product_sync_log_transientt ) ) {
		$arr = array();
		set_transient( 'woo_product_sync_log_transient', $arr, 300 );
	}

	$woo_product_sync_log_transientt = get_transient( 'woo_product_sync_log_transient' );

	session_start();
	if ( ! empty( $_POST['id'] ) ) {
		$cat_id = sanitize_text_field( wp_unslash( $_POST['id'] ) );
	}
	$session_action = isset( $_SESSION['woo_to_square']['target_categories'][ $cat_id ]['action'] ) ? sanitize_text_field( wp_unslash( $_SESSION['woo_to_square']['target_categories'][ $cat_id ]['action'] ) ) : '';
	$action_type    = $session_action;

	$square              = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
	$square_synchronizer = new WooToSquareSynchronizer( $square );
	$result              = false;

	switch ( $action_type ) {
		case 'add':
			$category = get_term_by( 'id', $cat_id, 'product_cat' );
			$result   = $square_synchronizer->add_category( $category );
			
			$woo_product_sync_log_transient[ $cat_id ][ $result['pro_status'] ] = $result;
			$woo_product_sync_log_transient                                     = array_merge( $woo_product_sync_log_transientt, $woo_product_sync_log_transient );
			set_transient( 'woo_product_sync_log_transient', $woo_product_sync_log_transient, 300 );
			$woo_product_sync_log_id_transient = get_transient( 'woo_product_sync_log_id_transient' );
			$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
			if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
				$woosquare_sync_log                = new WooSquare_Sync_Logs();
				$log_id                            = $woosquare_sync_log->log_data_request( $woo_product_sync_log_transient, $woo_product_sync_log_id_transient, 'woo_to_square', 'category' );
				if ( ! empty( $log_id ) ) {
					set_transient( 'woo_product_sync_log_id_transient', $log_id, 300 );
				}
			}
			if ( true === $result['status'] ) {
				update_option( "is_square_sync_{$cat_id}", 1 );
			}
			$action = Helpers::ACTION_ADD;
			break;

		case 'update':
			$category = get_term_by( 'id', $cat_id, 'product_cat' );

			$category->term_id = $cat_id;

			$session_square_id = isset( $_SESSION['woo_to_square']['target_categories'][ $cat_id ]['square_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['woo_to_square']['target_categories'][ $cat_id ]['square_id'] ) ) : '';

			$result = $square_synchronizer->edit_category( $category, $session_square_id );

			$woo_product_sync_log_transient[ $cat_id ][ $result['pro_status'] ] = $result;
			
			$woo_product_sync_log_transient                                     = array_merge( $woo_product_sync_log_transientt, $woo_product_sync_log_transient );
			
			set_transient( 'woo_product_sync_log_transient', $woo_product_sync_log_transient, 300 );
			$woo_product_sync_log_id_transient = get_transient( 'woo_product_sync_log_id_transient' );
			$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
			if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
				$woosquare_sync_log                = new WooSquare_Sync_Logs();
				$log_id                            = $woosquare_sync_log->log_data_request( $woo_product_sync_log_transient, $woo_product_sync_log_id_transient, 'woo_to_square', 'category' );
				if ( ! empty( $log_id ) ) {
					set_transient( 'woo_product_sync_log_id_transient', $log_id, 300 );
				}
			}
			if ( true === $result['status'] ) {
				update_option( "is_square_sync_{$cat_id}", 1 );
			}
			$action = Helpers::ACTION_UPDATE;
			break;

		case 'delete':
			$item_square_id                    = isset( $_SESSION['woo_to_square']['target_categories'][ $cat_id ]['square_id'] ) ?
			sanitize_text_field( wp_unslash( $_SESSION['woo_to_square']['target_categories'][ $cat_id ]['square_id'] ) ) : null;
			$woo_product_delete_log_transientt = get_transient( 'woo_product_delete_log_transient' );
			if ( empty( $woo_product_delete_log_transientt ) ) {
				$arr = array();
				set_transient( 'woo_product_delete_log_transient', $arr, 300 );
			}

			$woo_product_delete_log_transientt = get_transient( 'woo_product_delete_log_transient' );
			$category                          = get_term_by( 'id', $cat_id, 'product_cat' );
			if ( $item_square_id ) {
				$result = $square_synchronizer->delete_category( $item_square_id );

				// delete category from plugin delete table.
				if ( true === $result || ( 'NOT_FOUND' === $result['errors'][0]['code'] ) ) {
					global $wpdb;
					$session_category_name                                 = isset( $_SESSION['woo_to_square']['target_categories'][ $cat_id ]['name'] ) ? sanitize_text_field( wp_unslash( $_SESSION['woo_to_square']['target_categories'][ $cat_id ]['name'] ) ) : '';
					$delt_pro_array                                        = array(
						'name'    => $session_category_name,
						'status'  => 'deleted',
						'item'    => 'category',
						'message' => __( 'Successfully Deleted', 'woosquare' ),
					);
					$woo_product_delete_log_transient[ $cat_id ]['delete'] = $delt_pro_array;
					$woo_product_delete_log_transient                      = array_merge( $woo_product_delete_log_transientt, $woo_product_delete_log_transient );
					set_transient( 'woo_product_delete_log_transient', $woo_product_delete_log_transient, 300 );
					$woo_delete_product_log_id_transient = get_transient( 'woo_delete_product_log_id_transient' );
					$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
					if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
						$woosquare_sync_log = new WooSquare_Sync_Logs();

						$log_id = $woosquare_sync_log->delete_product_log_data_request( $woo_product_delete_log_transient, $woo_delete_product_log_id_transient, 'category', 'woo_to_square' );
						if ( ! empty( $log_id ) ) {
							set_transient( 'woo_delete_product_log_id_transient', $log_id, 300 );
						}
					}
					$delete = 'delete';
					$result = $wpdb->$delete(
						$wpdb->prefix . WOO_SQUARE_TABLE_DELETED_DATA,
						array( 'square_id' => $item_square_id )
					);

					if ( 1 === $result ) {
						$result['status'] = true;
					}
				}
			}

			$action = Helpers::ACTION_DELETE;
			break;
	}

	// log
	// check if response returned is bool or error response message.
	$message = null;
	if ( ! is_bool( $result['status'] ) ) {
		$message          = $result['message'];
		$result['status'] = false;

	}
	$session_category_name = isset( $_SESSION['woo_to_square']['target_categories'][ $cat_id ]['name'] ) ? sanitize_text_field( wp_unslash( $_SESSION['woo_to_square']['target_categories'][ $cat_id ]['name'] ) ) : '';
	$session_square_id     = isset( $_SESSION['woo_to_square']['target_categories'][ $cat_id ]['square_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['woo_to_square']['target_categories'][ $cat_id ]['square_id'] ) ) : '';
	$session_parent_id     = isset( $_SESSION['woo_to_square']['target_categories']['parent_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['woo_to_square']['target_categories']['parent_id'] ) ) : '';
	Helpers::sync_db_log(
		$action,
		gmdate( 'Y-m-d H:i:s' ),
		Helpers::SYNC_TYPE_MANUAL,
		Helpers::SYNC_DIRECTION_WOO_TO_SQUARE,
		$cat_id,
		Helpers::TARGET_TYPE_CATEGORY,
		$result ? Helpers::TARGET_STATUS_SUCCESS : Helpers::TARGET_STATUS_FAILURE,
		$session_parent_id,
		$session_category_name,
		$session_square_id,
		$message
	);
	echo esc_html( $result['status'] );
	die();
}

/**
 * Synchronize a WooCommerce product to Square.
 *
 * This function handles the synchronization of a WooCommerce product to Square based on the provided parameters.
 *
 * @return void Outputs the result of the synchronization process.
 */
function woo_square_plugin_sync_woo_product_to_square() {

	if ( ! isset( $_POST['ajaxnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ajaxnonce'] ) ), 'my_woosquare_ajax_nonce' ) ) {
		wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
	}
	session_start();
	if ( ! empty( $_POST['id'] ) ) {
		$product_id = sanitize_text_field( wp_unslash( $_POST['id'] ) );
	}
	$square              = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
	$square_synchronizer = new WooToSquareSynchronizer( $square );

	if ( ! strcmp( $product_id, 'modifier_set_end' ) ) {

		unset( $_SESSION['modifier_name_array'] );
		unset( $_SESSION['session_key_count'] );
		unset( $_SESSION['product_loop_id'] );
	}

	if ( strcmp( $product_id, 'modifier_set_end' ) ) {
		if ( strpos( $product_id, 'add_modifier' ) ) {
			// create modifier.

			$result = $square_synchronizer->woo_square_plugin_sync_woo_modifier_to_square_modifier( $product_id );

		} elseif ( strpos( $product_id, '_modifier' ) ) {
			// if update modifier.
			$result = $square_synchronizer->woo_square_plugin_sync_woo_modifier_to_square_modifier( $product_id );
		} else {

			if ( ! isset( $_SESSION['woo_to_square']['target_products'][ $product_id ] ) ) {
				$result = false;
			}
			$session_action = isset( $_SESSION['woo_to_square']['target_products'][ $product_id ]['action'] ) ? sanitize_text_field( wp_unslash( $_SESSION['woo_to_square']['target_products'][ $product_id ]['action'] ) ) : '';
			$action_type    = $session_action;

			$result = false;
			if ( ! strcmp( $action_type, 'delete' ) ) {

				// delete.
				$item_square_id = isset( $_SESSION['woo_to_square']['target_products'][ $product_id ]['square_id'] ) ?
				sanitize_text_field( wp_unslash( $_SESSION['woo_to_square']['target_products'][ $product_id ]['square_id'] ) ) : null;

				if ( $item_square_id ) {
					if ( get_option( 'disable_auto_delete' ) !== 1 ) {
						$result = $square_synchronizer->delete_product_or_get( $item_square_id, 'DELETE' );
					}
					// delete product from plugin delete table.
					if ( true === $result['status'] || 'NOT_FOUND' === $result['errors'][0]['code'] ) {
						global $wpdb;
						$delete = 'delete';
						$wpdb->$delete(
							$wpdb->prefix . WOO_SQUARE_TABLE_DELETED_DATA,
							array( 'square_id' => $item_square_id )
						);

						$_SESSION['product_sync_log'][ $product_id ] = $result;
						$result                                      = true;
					}
				}
				$_SESSION['productid']       = $product_id;
				$_SESSION['product_loop_id'] = $product_id;
				$action                      = Helpers::ACTION_DELETE;

			} else {   // add/update.
				$post = get_post( $product_id );

				$woo_product_sync_log_transientt = get_transient( 'woo_product_sync_log_transient' );
				if ( empty( $woo_product_sync_log_transientt ) ) {
					$arr = array();
					set_transient( 'woo_product_sync_log_transient', $arr, 300 );
				}

				$woo_product_sync_log_transientt = get_transient( 'woo_product_sync_log_transient' );

				if ( ! strpos( $product_id, 'modifier' ) && ! strpos( $product_id, 'add_modifier' ) ) {
					$product_square_id = $square_synchronizer->check_sku_in_square( $post, get_transient( 'woo_to_square-square_items' ) );

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
									'object_types' =>
									array(
										0 => 'ITEM',
										1 => 'ITEM_VARIATION',
									),
									'include_related_objects' => true,
									'query'        =>
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
					$woo_product_sync_log_transient[ $product_id ][ $result['pro_status'] ] = $result;
					$woo_product_sync_log_transient = array_merge( $woo_product_sync_log_transientt, $woo_product_sync_log_transient );
					set_transient( 'woo_product_sync_log_transient', $woo_product_sync_log_transient, 300 );
					$woo_product_sync_log_id_transient = get_transient( 'woo_product_sync_log_id_transient' );
					$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
					if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
						$woosquare_sync_log                = new WooSquare_Sync_Logs();
						$log_id                            = $woosquare_sync_log->log_data_request( $woo_product_sync_log_transient, $woo_product_sync_log_id_transient, 'woo_to_square', 'product' );
						if ( ! empty( $log_id ) ) {
							set_transient( 'woo_product_sync_log_id_transient', $log_id, 300 );
						}
					}
					// update post meta.
					if ( true === $result['status'] ) {
						update_post_meta( $product_id, 'is_square_sync', 1 );
					}
					$action = ( ! strcmp( $action_type, 'update' ) ) ? Helpers::ACTION_UPDATE :
						Helpers::ACTION_ADD;

					$_SESSION['productid']       = $product_id;
					$_SESSION['product_loop_id'] = $product_id;
				}
			}
		}

		// log the process.
		// check if response returned is bool or error response message.
		$message = null;
		if ( ! is_bool( $result['status'] ) ) {
			$message = $result['message'];
			$result  = false;
		}
		$session_product_name = isset( $_SESSION['woo_to_square']['target_products'][ $product_id ]['name'] ) ? sanitize_text_field( wp_unslash( $_SESSION['woo_to_square']['target_products'][ $product_id ]['name'] ) ) : '';
		$session_parent_id    = isset( $_SESSION['woo_to_square']['target_products']['parent_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['woo_to_square']['target_products']['parent_id'] ) ) : '';
		Helpers::sync_db_log(
			$action,
			gmdate( 'Y-m-d H:i:s' ),
			Helpers::SYNC_TYPE_MANUAL,
			Helpers::SYNC_DIRECTION_WOO_TO_SQUARE,
			$product_id,
			Helpers::TARGET_TYPE_PRODUCT,
			$result ? Helpers::TARGET_STATUS_SUCCESS : Helpers::TARGET_STATUS_FAILURE,
			$session_parent_id,
			$session_product_name,
			$product_square_id,
			$message
		);

	}
	echo esc_html( $result['status'] );
	die();
}

/**
 * Terminate a manual WooCommerce to Square synchronization process.
 *
 * This function stops a manual synchronization process from WooCommerce to Square if it was started manually.
 * It updates synchronization flags and session data accordingly to terminate the synchronization.
 *
 * @return void Outputs a result indicating the successful termination of the synchronization.
 */
function woo_square_plugin_terminate_manual_woo_sync() {

	// stop synchronization if only started manually.
	if ( ! strcmp( get_option( 'woo_square_running_sync' ), 'manual' ) ) {
		update_option( 'woo_square_running_sync', false );
		update_option( 'woo_square_running_sync_time', 0 );
	}

	session_start();
	// ensure function is not called twice.
	if ( ! isset( $_SESSION['woo_to_square'] ) ) {
		return;
	}
	unset( $_SESSION['woo_to_square'] );

	echo '1';
	die();
}

/**
 * Gets non-synchronized Square data.
 *
 * This function gets all Square data that has not yet been synchronized with WooCommerce.
 */
function woo_square_plugin_get_non_sync_square_data() {

	$check_flag = check_sync_start_conditions();
	if ( true !== $check_flag ) {
		die( wp_json_encode( array( 'error' => $check_flag ) ) ); }

	$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );

	$synchronizer = new SquareToWooSynchronizer( $square );

	// for display.
	$add_products      = array();
	$update_products   = array();
	$delete_products   = array();
	$add_categories    = array();
	$update_categories = array();
	$delete_categories = array();
	// display only one checkbox in update.
	$one_products_update_checkbox = true;

	// 1-get all square categories ( having is_square_sync = 0 or key not exists )
	$square_categories = $synchronizer->get_square_categories();

	$synch_square_ids = array();
	if ( ! empty( $square_categories ) ) {
		// get previously linked categories to woo.
		$woo_square_cats = $synchronizer->get_unsync_woo_square_categories_ids( $square_categories, $synch_square_ids );
	} else {
		$square_categories = array();
		$woo_square_cats   = array();
	}

	$target_categories = array();

	// merge add and update categories.
	$taxonomy       = 'product_cat';
	$orderby        = 'name';
	$show_count     = 0;   // 1 for yes, 0 for no.
	$pad_counts     = 0;   // 1 for yes, 0 for no.
	$hierarchical   = 1;   // 1 for yes, 0 for no.
	$title          = '';
	$empty          = 0;
	$args           = array(
		'taxonomy'     => $taxonomy,
		'orderby'      => $orderby,
		'show_count'   => $show_count,
		'pad_counts'   => $pad_counts,
		'hierarchical' => $hierarchical,
		'title_li'     => $title,
		'hide_empty'   => $empty,
	);
	$all_categories = get_categories( $args );
	
	foreach ( $all_categories as $keyscategories => $catsterms ) {
        
		$term_id = get_option( 'category_square_id_' . $catsterms->term_id );
        
		if ( empty( $term_id ) ) {
			$target_categories[ $catsterms->term_id ]['action']  = 'delete';
			$target_categories[ $catsterms->term_id ]['woo_id']  = $catsterms->term_id;
			$target_categories[ $catsterms->term_id ]['name']    = $catsterms->name;
			$target_categories[ $catsterms->term_id ]['version'] = null;
            
			// for display.
			if($catsterms->name != 'Uncategorized'){
			    $delete_categories[] = array(
    				'woo_id'       => $catsterms->term_id,
    				'checkbox_val' => $catsterms->term_id,
    				'name'         => $catsterms->name,
    			);
			}
			
		}else{
		    $squaretermexist[$term_id] = $catsterms->term_id;
		}
	}

	foreach ( $square_categories as $cat ) {
       
        
		if ( ! isset( $squaretermexist[ $cat->id ] ) ) {      // add.
			$target_categories[ $cat->id ]['action']  = 'add';
			$target_categories[ $cat->id ]['woo_id']  = null;
			$target_categories[ $cat->id ]['name']    = $cat->category_data->name;
			$target_categories[ $cat->id ]['version'] = $cat->version;

			// for display.
			$add_categories[] = array(
				'woo_id'       => null,
				'checkbox_val' => $cat->id,
				'name'         => $cat->category_data->name,
			);

		} else {                                       // update.
			// if category has square id but already synchronized, no need to synch again.
           
			
			$target_categories[ $cat->id ]['action']   = 'update';
			$target_categories[ $cat->id ]['woo_id']   = $woo_square_cats[ $cat->id ][0];
			$target_categories[ $cat->id ]['name']     = $woo_square_cats[ $cat->id ][1];
			$target_categories[ $cat->id ]['new_name'] = $cat->category_data->name;
			$target_categories[ $cat->id ]['version']  = $cat->version;

			// for display.

				if ( isset( $squaretermexist[ $cat->id ] ) ) {
				$update_categories[] = array(
					'woo_id'       => $squaretermexist[ $cat->id ],
					'checkbox_val' => $cat->id,
					'arrayyy'      => $woo_square_cats[ $cat->id ],
					'name'         => get_term($squaretermexist[ $cat->id ])->name,
				);
			
			}
		}
	}

	// 2-get square products.

	$target_products  = array();
	$session_products = array();
	$square_items     = $synchronizer->get_square_items();

	$skipped_products    = array();
	$new_square_products = array();
	if ( $square_items ) {
		// get new square products and an array of products skipped from add/update actions.
		$new_square_products = $synchronizer->get_new_products( $square_items, $skipped_products );
	}
	if ( isset( $_REQUEST['optionsaved'] ) ) { // phpcs:ignore
		$new_square_products = $square_items;
	}

	$session_products = array();
	if ( ! empty( $new_square_products['sku_misin_squ_woo_pro'] ) ) {
		foreach ( $new_square_products['sku_misin_squ_woo_pro'] as $sku_missin ) {
			$sku_missin_inside_product[] = array(
				'woo_id'                         => null,
				'name'                           => '"' . $sku_missin->name . '" from square',
				'sku_misin_squ_woo_pro_variable' => 'sku_misin_squ_woo_pro_variable',
				'checkbox_val'                   => $sku_missin->id,
			);
		}
		unset( $new_square_products['sku_misin_squ_woo_pro'] );

	}
	if ( ! empty( $new_square_products['sku_misin_squ_woo_pro_variable'] ) ) {
		foreach ( $new_square_products['sku_misin_squ_woo_pro_variable'] as $sku_missin ) {
			$sku_missin_inside_product[] = array(
				'woo_id'                         => null,
				'name'                           => '"' . $sku_missin->name . '" from square variations',
				'checkbox_val'                   => $sku_missin->id,
				'sku_misin_squ_woo_pro_variable' => 'sku_misin_squ_woo_pro_variable',
			);
		}
		unset( $new_square_products['sku_misin_squ_woo_pro_variable'] );
	}
	$variats_ids = isset( $new_square_products['variats_ids'] ) ? $new_square_products['variats_ids'] : null;
	unset( $new_square_products['variats_ids'] );

	foreach ( $new_square_products as $key => $product ) {

		$target_products[ $product->id ]['action'] = 'add';
		$target_products[ $product->id ]['woo_id'] = null;
		$target_products[ $product->id ]['name']   = $product->name;
		if ( ! empty( $product->modifier_list_info ) ) {
			foreach ( $product->modifier_list_info as $key => $mod_val ) {

				$target_products[ $product->id ]['modifier_set_name'][ $key ] = $mod_val['mod_sets']['name'];

			}
			// }
		}

		// store whole returned response in session.
		$session_products[ $product->id ] = $product;

		if ( ! empty( $product->modifier_list_info ) ) {
			$kkey              = 0;
			$modifier_set_name = array();
			foreach ( $product->modifier_list_info as  $mod_val ) {
				$modifier_set_name[ $kkey ] = $mod_val['mod_sets']['name'] . '|' . $mod_val['modifier_list_id'] . '|' . $mod_val['version'];
				++$kkey;

			}
		}

		if ( ! empty( $product->modifier_list_info ) ) {
			// for display.
			$add_products[] = array(
				'woo_id'            => null,
				'name'              => $product->name,
				'checkbox_val'      => $product->id,
				'modifier_set_name' => $modifier_set_name,

			);
		} else {
			$add_products[] = array(
				'woo_id'       => null,
				'name'         => $product->name,
				'checkbox_val' => $product->id,

			);
		}
	}

	// construct session array.
	if ( ! isset( $_SESSION ) ) {
		session_start();
	}
	$_SESSION['square_to_woo']                                        = array();
	$_SESSION['square_to_woo']['target_categories']                   = $target_categories;
	$_SESSION['square_to_woo']['target_products']                     = $session_products;
	$_SESSION['square_to_woo']['target_products']['skipped_products'] = $skipped_products;

	$square_inventory_array = array();
	$square_inventory       = new stdClass();

	$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
	$newvariations          = array();

	if ( is_array( $variats_ids ) ) {

			$variant_ids_chunks = array_chunk( $variats_ids, 499 );
			$square_inventories = array();
		foreach ( $variant_ids_chunks as $key => $value ) {
				$square_inventories[ $key ] = $synchronizer->get_square_inventory( $value );

			foreach ( $square_inventories[ $key ]->counts as $vari ) {

				if ( $vari->location_id === $woo_square_location_id ) {
					$newvariations[] = $vari;

				}
			}
		}
			set_transient( $woo_square_location_id . 'transient_' . __FUNCTION__, $newvariations, 300 );
	}

	$square_inventory->counts = $newvariations;

	if ( ! empty( $square_inventory->counts ) ) {
		$square_inventory_array = $synchronizer->convert_square_inventory_to_associative( $square_inventory->counts );
	}

	set_transient( 'square_inventory', $square_inventory_array, 2400 );

	ob_start();
	include plugin_dir_path( __DIR__ ) . '../views/partials/pop-up.php';
	$data = ob_get_clean();

	echo wp_json_encode( array( 'data' => $data ) );

	die();
}

/**
 * Synchronize Square categories to WooCommerce.
 *
 * This function is responsible for synchronizing Square categories to WooCommerce. It processes Square category information,
 * updates or creates corresponding entries in the WordPress database, and maintains session data.
 *
 * @return void Outputs the result of the synchronization process.
 */
function woo_square_plugin_start_manual_square_to_woo_sync() {

	$check_flag = check_sync_start_conditions();
	if ( true !== $check_flag ) {
		die( esc_html( $check_flag ) );
	}

	update_option( 'woo_square_running_sync', 'manual' );
	update_option( 'woo_square_running_sync_time', time() );

	session_start();

	unset( $_SESSION['square_product_sync_log'] );
	unset( $_SESSION['square_product_sync_add_log'] );
	unset( $_SESSION['square_product_sync_update_log'] );
	unset( $_SESSION['square_product_delete_sync_log'] );
	unset( $_SESSION['delete_product_log_id'] );
	unset( $_SESSION['woo_product_sync_log'] );
	unset( $_SESSION['woo_product_sync_log_id'] );
	unset( $_SESSION['woo_product_delete_log'] );
	unset( $_SESSION['woo_delete_product_log_id'] );
	unset( $_SESSION['log_id'] );
	delete_transient( 'woo_product_delete_log' );
	delete_transient( 'woo_product_delete_log_id_transient' );
	delete_transient( 'woo_product_delete_log_transient' );
	delete_transient( 'square_product_sync_log_transient' );
	delete_transient( 'square_product_sync_log_id_transient' );
	delete_transient( 'woo_delete_product_log_id' );
	$_SESSION['woo_to_square']['target_products']['parent_id']   = Helpers::sync_db_log(
		Helpers::ACTION_SYNC_START,
		gmdate( 'Y-m-d H:i:s' ),
		Helpers::SYNC_TYPE_MANUAL,
		Helpers::SYNC_DIRECTION_WOO_TO_SQUARE
	);
	$_SESSION['woo_to_square']['target_categories']['parent_id'] = isset( $_SESSION['woo_to_square']['target_products']['parent_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['woo_to_square']['target_products']['parent_id'] ) ) : '';

	echo '1';
	die();
}

/**
 * Synchronize Square products to WooCommerce.
 *
 * This function is responsible for synchronizing Square products to WooCommerce. It processes Square product information,
 * updates or creates corresponding entries in the WordPress database, and maintains session data.
 *
 * @return void Outputs the result of the synchronization process.
 */
function woo_square_plugin_sync_square_category_to_woo() {
	if ( ! isset( $_POST['ajaxnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ajaxnonce'] ) ), 'my_woosquare_ajax_nonce' ) ) {
		wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
	}

	$square_product_sync_log_transientt = get_transient( 'square_product_sync_log_transient' );

	if ( empty( $square_product_sync_log_transientt ) ) {
		$arr = array();
		set_transient( 'square_product_sync_log_transient', $arr, 300 );
	}

	$square_product_sync_log_transientt = get_transient( 'square_product_sync_log_transient' );

	session_start();
	if ( ! empty( $_POST['id'] ) ) {
		$cat_id = sanitize_text_field( wp_unslash( $_POST['id'] ) );
	}
	if ( ! isset( $_SESSION['square_to_woo']['target_categories'][ $cat_id ] ) ) {
		die();
	}
	$session_action      = isset( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['action'] ) ? sanitize_text_field( wp_unslash( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['action'] ) ) : '';
	$action_type         = $session_action;
	$square              = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
	$square_synchronizer = new SquareToWooSynchronizer( $square );
	$result              = false;

	switch ( $action_type ) {
		case 'add':
			$category          = new stdClass();
			$category->id      = $cat_id;
			$category->name    = isset( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['name'] ) ? sanitize_text_field( wp_unslash( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['name'] ) ) : '';
			$category->version = isset( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['version'] ) ? sanitize_text_field( wp_unslash( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['version'] ) ) : '';
			$result            = $square_synchronizer->add_category_to_woo( $category );
			if ( false !== $result['status'] ) {
				$category_id = $result['id'];
				update_option( "is_square_sync_{$category_id}", 1 );
				$target_id = $result['id'];

			}
			$square_product_sync_log_transient[ $result['id'] ][ $result['pro_status'] ] = $result;
			$square_product_sync_log_transient = array_merge( $square_product_sync_log_transientt, $square_product_sync_log_transient );
			set_transient( 'square_product_sync_log_transient', $square_product_sync_log_transient, 300 );
			$square_product_sync_log_id_transient = get_transient( 'square_product_sync_log_id_transient' );
			$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
			if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
				$woosquare_sync_log                   = new WooSquare_Sync_Logs();
				$log_id                               = $woosquare_sync_log->log_data_request( $square_product_sync_log_transient, $square_product_sync_log_id_transient, 'square_to_woo', 'category' );
				if ( ! empty( $log_id ) ) {
					set_transient( 'square_product_sync_log_id_transient', $log_id, 300 );
				}
			}
			$action = Helpers::ACTION_ADD;
			break;

		case 'update':
			$category          = new stdClass();
			$category->id      = $cat_id;
			$category->name    = isset( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['new_name'] ) ? sanitize_text_field( wp_unslash( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['new_name'] ) ) : '';
			$category->version = isset( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['version'] ) ? sanitize_text_field( wp_unslash( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['version'] ) ) : '';
			$category_woo_id   = isset( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['woo_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['woo_id'] ) ) : '';
			$result            = $square_synchronizer->update_woo_category(
				$category,
				$category_woo_id
			);
			if ( false !== $result['status'] ) {
				update_option( "is_square_sync_{$result['id']}", 1 );
			}
			$square_product_sync_log_transient[ $result['id'] ][ $result['pro_status'] ] = $result;
			$square_product_sync_log_transient = array_merge( $square_product_sync_log_transientt, $square_product_sync_log_transient );
			set_transient( 'square_product_sync_log_transient', $square_product_sync_log_transient, 300 );
			$square_product_sync_log_id_transient = get_transient( 'square_product_sync_log_id_transient' );
			$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
			if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
				$woosquare_sync_log                   = new WooSquare_Sync_Logs();
				$log_id                               = $woosquare_sync_log->log_data_request( $square_product_sync_log_transient, $square_product_sync_log_id_transient, 'square_to_woo', 'category' );
				if ( ! empty( $log_id ) ) {
					set_transient( 'square_product_sync_log_id_transient', $log_id, 300 );
				}
			}
			$target_id = isset( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['woo_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['woo_id'] ) ) : '';
			$action    = Helpers::ACTION_UPDATE;
			break;
		case 'delete':
			$woo_product_delete_log_transientt = get_transient( 'woo_product_delete_log_transient' );
			if ( empty( $woo_product_delete_log_transientt ) ) {
				$arr = array();
				set_transient( 'woo_product_delete_log_transient', $arr, 300 );
			}

			$woo_product_delete_log_transientt = get_transient( 'woo_product_delete_log_transient' );
			$category                          = new stdClass();
			$category->id                      = isset( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['woo_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['woo_id'] ) ) : '';
			$category->name                    = isset( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['name'] ) ? sanitize_text_field( wp_unslash( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['name'] ) ) : '';
			
			if ( get_option( 'disable_auto_delete' ) !== 1 ) {
			    $result = $square_synchronizer->delete_woo_category( $category );
			}
			$woo_category_id = isset( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['woo_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['woo_id'] ) ) : '';
			if ( false !== $result['status'] ) {
				$delt_pro_array = array(
					'name'    => $category->name,
					'status'  => 'deleted',
					'item'    => 'category',
					'message' => __( 'Successfully Deleted', 'woosquare' ),
				);

				$woo_product_delete_log_transient[ $woo_category_id ]['delete'] = $delt_pro_array;
				$woo_product_delete_log_transient                               = array_merge( $woo_product_delete_log_transientt, $woo_product_delete_log_transient );
				set_transient( 'woo_product_delete_log_transient', $woo_product_delete_log_transient, 300 );
				$woo_delete_product_log_id_transient = get_transient( 'woo_delete_product_log_id_transient' );
				$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
				if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
					$woosquare_sync_log = new WooSquare_Sync_Logs();

					$log_id = $woosquare_sync_log->delete_product_log_data_request( $woo_product_delete_log_transient, $woo_delete_product_log_id_transient, 'category', 'square_to_woo' );
					if ( ! empty( $log_id ) ) {
						set_transient( 'woo_delete_product_log_id_transient', $log_id, 300 );
					}
				}
			}
			$target_id = $woo_category_id;
			$action    = Helpers::ACTION_UPDATE;
			break;
	}
	$session_category_name = isset( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['name'] ) ? sanitize_text_field( wp_unslash( $_SESSION['square_to_woo']['target_categories'][ $cat_id ]['name'] ) ) : '';
	$session_parent_id     = isset( $_SESSION['square_to_woo']['target_categories']['parent_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['square_to_woo']['target_categories']['parent_id'] ) ) : '';

	// log.
	Helpers::sync_db_log(
		$action,
		gmdate( 'Y-m-d H:i:s' ),
		Helpers::SYNC_TYPE_MANUAL,
		Helpers::SYNC_DIRECTION_SQUARE_TO_WOO,
		isset( $target_id ) ? $target_id : null,
		Helpers::TARGET_TYPE_CATEGORY,
		$result['status'] ? Helpers::TARGET_STATUS_SUCCESS : Helpers::TARGET_STATUS_FAILURE,
		$session_category_name,
		$session_parent_id,
		$cat_id
	);

	echo esc_html( $result['status'] );
	die();
}

/**
 * Synchronize Square products to WooCommerce.
 *
 * This function is responsible for synchronizing Square products to WooCommerce. It processes Square product information,
 * updates or creates corresponding entries in the WordPress database, and maintains session data.
 *
 * @return void Outputs the result of the synchronization process.
 */
function woo_square_plugin_sync_square_product_to_woo() {
	if ( ! isset( $_POST['ajaxnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ajaxnonce'] ) ), 'my_woosquare_ajax_nonce' ) ) {
		wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
	}
	session_start();
	$result = false;  // default value for returned response.
	if ( ! empty( $_POST['id'] ) ) {
		$prod_square_id = sanitize_text_field( wp_unslash( $_POST['id'] ) );
	}
	if ( ! strcmp( $prod_square_id, 'modifier_set_end' ) ) {

		if ( ! empty( $_SESSION['session_key_count'] ) && ! empty( $_SESSION['modifier_name_array'] ) && ! empty( $_SESSION['product_loop_id'] ) ) {
			$session_product_loop_id     = isset( $_SESSION['product_loop_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['product_loop_id'] ) ) : '';
			$session_modifier_name_array = isset( $_SESSION['modifier_name_array'] ) ? sanitize_text_field( wp_unslash( $_SESSION['modifier_name_array'] ) ) : '';
			update_post_meta( $session_product_loop_id, 'product_modifier_group_name', $session_modifier_name_array );

		}
		unset( $_SESSION['modifier_name_array'] );
		unset( $_SESSION['session_key_count'] );
		unset( $_SESSION['product_loop_id'] );
		echo '1';
		die();
	}

	if ( strcmp( $prod_square_id, 'modifier_set_end' ) ) {

		if ( ! strcmp( $prod_square_id, 'update_products' ) ) {

			$square       = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
			$synchronizer = new SquareToWooSynchronizer( $square );
			$square_items = $synchronizer->get_square_items();

			// get all woocommerce products.
			$posts_per_page = -1;
			$args           = array(
				'post_type'      => 'product',
				'posts_per_page' => $posts_per_page,
			);
			// delete those product which is not exist square but exist in woocommerce.....
			$woocommerce_products = get_posts( $args );

			if ( $woocommerce_products ) {
				foreach ( $woocommerce_products as $product ) {

					$square_id = get_post_meta( $product->ID, 'square_id', true );

					if ( empty( session_id() ) && ! headers_sent() ) {
						session_start();
					}

					$_SESSION['productid'] = $product->ID;
					woo_square_plugin_sync_square_modifier_to_woo( $product->ID, $square_items );

					if ( ! empty( $square_id ) && ! empty( $square_items ) ) {

						$product->existinsquare = false;
						foreach ( $square_items as $square_item ) {
							if ( $square_id === $square_item->id ) {
								$product->existinsquare = true;
							}
						}
						if ( ! $product->existinsquare ) {
							if ( get_option( 'disable_auto_delete' ) !== 1 ) {
								$square_product_delete_sync_log_transientt = get_transient( 'square_product_delete_sync_log_transient' );
								if ( empty( $square_product_delete_sync_log_transientt ) ) {
									$arr = array();
									set_transient( 'square_product_delete_sync_log_transient', $arr, 300 );
								}

								$square_product_delete_sync_log_transientt = get_transient( 'square_product_delete_sync_log_transient' );
								$delt_pro                                  = wc_get_product( $product->ID );
								$sku                                       = $delt_pro->get_sku();
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
								wp_delete_post( $product->ID, true );

								$square_product_delete_sync_log_transient[ $product->ID ]['delete'] = $delt_pro_array;
								$square_product_delete_sync_log_transient                           = array_merge( $square_product_delete_sync_log_transientt, $square_product_delete_sync_log_transient );
								set_transient( 'square_product_delete_sync_log_transient', $square_product_delete_sync_log_transient, 300 );
								$square_product_delete_sync_log_id_transient = get_transient( 'square_product_delete_sync_log_id_transient' );
								$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
								if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
									$woosquare_sync_log = new WooSquare_Sync_Logs();

									$log_id = $woosquare_sync_log->delete_product_log_data_request( $square_product_delete_sync_log_transient, $square_product_delete_sync_log_id_transient, 'square_to_woo' );
									if ( ! empty( $log_id ) ) {
										set_transient( 'square_product_delete_sync_log_id_transient', $log_id, 300 );
									}
								}
							}
						}
					}
				}
			}

			if ( $square_items ) {

				$square_items_keys                                   = array_keys( $square_items );
				$square_items[ array_pop( $square_items_keys ) + 1 ] = get_transient( 'square_inventory' );
				$new_modifier                                        = array();

				foreach ( $square_items as $val ) {
					array_push( $new_modifier, $val );
				}
				echo ( ( wp_json_encode( $new_modifier ) ) );
				die();
			}
		}

		if ( ! strpos( $prod_square_id, 'modifier' ) ) {

			$square_product_sync_log_transientt = get_transient( 'square_product_sync_log_transient' );
			
			if ( empty( $square_product_sync_log_transientt ) ) {
				$arr = array();
				set_transient( 'square_product_sync_log_transient', $arr, 300 );
			}

			$square_product_sync_log_transientt = get_transient( 'square_product_sync_log_transient' );

			// add product action.
			if ( ! isset( $_SESSION['square_to_woo']['target_products'][ $prod_square_id ] ) ) {
				die();
			}

			$square                 = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
			$square_synchronizer    = new SquareToWooSynchronizer( $square );
			
			$session_prod_square_id = isset( $_SESSION['square_to_woo']['target_products'][ $prod_square_id ] ) ? $_SESSION['square_to_woo']['target_products'][ $prod_square_id ] : '';
			
			if ( count( $_SESSION['square_to_woo']['target_products'][ $prod_square_id ]->variations ) <= 1 ) {  // simple product.
				$id = $square_synchronizer->insert_simple_product_to_woo( $session_prod_square_id, get_transient( 'square_inventory' ) );

			} else {
				$id = $square_synchronizer->insert_variable_product_to_woo( $session_prod_square_id, get_transient( 'square_inventory' ) );
			}

			$action = Helpers::ACTION_ADD;
			$result = ( false !== $id['id'] ) ? Helpers::TARGET_STATUS_SUCCESS : Helpers::TARGET_STATUS_FAILURE;

			if ( ! empty( $id['id'] ) && is_numeric( $id['id'] ) ) {
				update_post_meta( $id['id'], 'is_square_sync', 1 );
			}
			if ( empty( session_id() ) && ! headers_sent() ) {
				session_start();
			}

			$square_product_sync_log_transient[ $id['id'] ][ $id['pro_status'] ] = $id;
			$square_product_sync_log_transient                                   = array_merge( $square_product_sync_log_transientt, $square_product_sync_log_transient );
			set_transient( 'square_product_sync_log_transient', $square_product_sync_log_transient, 300 );
			$square_product_sync_log_id_transient = get_transient( 'square_product_sync_log_id_transient' );
			$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
			if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
				$woosquare_sync_log                   = new WooSquare_Sync_Logs();
				$log_id                               = $woosquare_sync_log->log_data_request( $square_product_sync_log_transient, $square_product_sync_log_id_transient, 'square_to_woo', 'product' );
				if ( ! empty( $log_id ) ) {
					set_transient( 'square_product_sync_log_id_transient', $log_id, 300 );
				}
			}
			$_SESSION['productid']       = $id['id'];
			$_SESSION['product_loop_id'] = $id['id'];
			$session_product_name        = isset( $_SESSION['square_to_woo']['target_products'][ $prod_square_id ]->name ) ? sanitize_text_field( wp_unslash( $_SESSION['square_to_woo']['target_products'][ $prod_square_id ]->name ) ) : '';
			$session_parent_id           = isset( $_SESSION['square_to_woo']['target_categories']['parent_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['square_to_woo']['target_categories']['parent_id'] ) ) : '';

			// log.
			Helpers::sync_db_log(
				$action,
				gmdate( 'Y-m-d H:i:s' ),
				Helpers::SYNC_TYPE_MANUAL,
				Helpers::SYNC_DIRECTION_SQUARE_TO_WOO,
				is_numeric( $id['id'] ) ? $id['id'] : null,
				Helpers::TARGET_TYPE_PRODUCT,
				$result,
				$session_parent_id,
				$session_product_name,
				$prod_square_id
			);

		} else {

			session_start();
			$current_product_id = isset( $_SESSION['productid'] ) ? sanitize_text_field( wp_unslash( $_SESSION['productid'] ) ) : '';
			if ( ! empty( $current_product_id ) && ! empty( $prod_square_id ) ) {
				$id                          = woo_square_plugin_sync_square_modifier_to_woo( $current_product_id, $prod_square_id );
				$_SESSION['product_loop_id'] = $current_product_id;
			}

			$action = Helpers::ACTION_ADD;
			$result = '1';

		}
	}

	echo esc_html( $result );
	die();
}

/**
 * Synchronize Square modifiers to WooCommerce.
 *
 * This function is responsible for synchronizing Square modifiers to WooCommerce. It processes the Square modifiers
 * information, updates or creates corresponding entries in the WordPress database, and maintains session data.
 *
 * @param int    $current_product_id The ID of the current product.
 * @param object $prod_square_id     The Square product ID containing modifier information.
 *
 * @return bool Returns true after successfully synchronizing Square modifiers to WooCommerce.
 */
function woo_square_plugin_sync_square_modifier_to_woo( $current_product_id, $prod_square_id ) {

	global $wpdb;

	// Update Modifier.

	if ( ! isset( $_SESSION['modifier_name_array'] ) ) {

		if ( empty( session_id() ) && ! headers_sent() ) {
			session_start();
		}
		$_SESSION['modifier_name_array'] = array();
		$session_key_count               = 0;
	}
	if ( isset( $_SESSION['session_key_count'] ) && $_SESSION['session_key_count'] > 0 ) {
		$session_key_count = sanitize_text_field( wp_unslash( $_SESSION['session_key_count'] ) );
	}

	$kkey = 0;

	$get_row = 'get_row';
	$prepare = 'prepare';
	$get_var = 'get_var';
	$queryy  = 'query';

	if ( isset( $prod_square_id->modifier_list_info ) ) {
		if ( ( count( $prod_square_id->modifier_list_info ) >= 1 ) ) {

			$modifier_update = array();

			foreach ( $prod_square_id->modifier_list_info as $key => $mod ) {

				if ( gettype( $mod ) === 'array' ) {
					$mod = json_decode( wp_json_encode( $mod ) );
				}

				if ( ! empty( $mod ) ) {

					$rowcount = $wpdb->$get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_set_unique_id = '$mod->modifier_list_id '" );

					$modifier_public = '0';
					$modifier_option = '0';
					if ( ( $rowcount < 1 ) ) {
						$mod_name = $mod->mod_sets->name;

						$modifier = array(
							'modifier_set_name'      => $mod->mod_sets->name,
							'modifier_slug'          => $mod->mod_sets->name,
							'modifier_public'        => $modifier_public,
							'modifier_option'        => $modifier_option,
							'modifier_set_unique_id' => $mod->modifier_list_id,
							'modifier_version'       => $mod->version,
						);
						$insert   = 'insert';
						$wpdb->$insert( $wpdb->prefix . 'woosquare_modifier', $modifier );

						$lastid  = $wpdb->insert_id;
						$methode = 'inserted';

						woo_square_plugin_sync_square_modifier_child_to_woo( $lastid, $mod->mod_sets->name, $mod->modifier_list_id, $methode, $mod->mod_sets->name );
						$mod_name                 = str_replace( ' ', '-', strtolower( $mod->mod_sets->name ) );
						$modifier_update[ $kkey ] = 'pm' . _ . $mod_name . '_' . $lastid;
						$_SESSION['modifier_name_array'][ $session_key_count ] = 'pm' . _ . $mod_name . '_' . $lastid;

					} elseif ( $rowcount >= 1 ) {

						$modifer_change = $wpdb->$get_row( 'SELECT modifier_set_name,modifier_version  FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_set_unique_id = '$mod->modifier_list_id ' " );

						$mod_name    = $mod->mod_sets->name;
						$mod_version = $mod->version;

						if ( $mod_name !== $modifer_change->modifier_set_name ) {
							$query      = 'UPDATE ' . $wpdb->prefix . 'woosquare_modifier SET modifier_set_name = %s, modifier_version = %d WHERE modifier_set_unique_id = %d';
							$parameters = array( $mod_name, $mod_version, $mod->modifier_list_id );
							$wpdb->$queryy( $wpdb->$prepare( $query, $parameters ) );
						}

						$modifer_id = $wpdb->$get_row( 'SELECT modifier_id,modifier_slug FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_set_unique_id = '$mod->modifier_list_id' " );
						$methode    = 'insert_updated';

						woo_square_plugin_sync_square_modifier_child_to_woo( $modifer_id->modifier_id, $mod_name, $mod->modifier_list_id, $methode, $modifer_id->modifier_slug );

						$mod_name                 = str_replace( ' ', '-', strtolower( $modifer_id->modifier_slug ) );
						$modifier_update[ $kkey ] = 'pm' . _ . $mod_name . '_' . $modifer_id->modifier_id;
						$_SESSION['modifier_name_array'][ $session_key_count ] = 'pm' . _ . $mod_name . '_' . $modifer_id->modifier_id;
					}
				}

				++$kkey;
			}

			update_post_meta( $current_product_id, 'product_modifier_group_name', $modifier_update );

		} else {
			// Create Modifier.

			$modifier_name = ( explode( '_', $prod_square_id ) );

			$modifier_set_name = str_replace( '-', ' ', $modifier_name[0] );
			$modifier_set_id   = $modifier_name[1];

			if ( ! empty( $modifier_set_name ) && ! empty( $modifier_set_id ) ) {

				$rowcount        = $wpdb->$get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_set_unique_id = '$modifier_set_id' " );
				$modifier_public = '0';
				$modifier_option = '0';

				if ( ( $rowcount < 1 ) ) {

					$modifier = array(
						'modifier_set_name'      => $modifier_set_name,
						'modifier_slug'          => $modifier_set_name,
						'modifier_public'        => $modifier_public,
						'modifier_option'        => $modifier_option,
						'modifier_set_unique_id' => $modifier_set_id,
						'modifier_version'       => $modifier_name[2],
					);
					$insert   = 'insert';
					$wpdb->$insert( $wpdb->prefix . 'woosquare_modifier', $modifier );
					$lastid  = $wpdb->insert_id;
					$methode = 'inserted';

					woo_square_plugin_sync_square_modifier_child_to_woo( $lastid, $modifier_set_name, $modifier_set_id, $methode, $modifier_set_name );
					$_SESSION['modifier_name_array'][ $session_key_count ] = 'pm' . _ . str_replace( ' ', '-', strtolower( $modifier_set_name ) ) . '_' . $lastid;
				} elseif ( $rowcount >= 1 ) {

					$modifer_change = $wpdb->$get_row( 'SELECT modifier_set_name,modifier_version FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_set_unique_id = '$modifier_set_id' " );
					if ( $modifier_set_name !== $modifer_change->modifier_set_name ) {
						$query      = 'UPDATE ' . $wpdb->prefix . 'woosquare_modifier SET modifier_set_name=%s, modifier_version=%d WHERE modifier_set_unique_id=%d';
						$parameters = array( $modifier_set_name, $modifier_name[2], $modifier_set_id );
						$wpdb->$queryy( $wpdb->$prepare( $query, $parameters ) );
					}

					$modifer_id = $wpdb->$get_row( 'SELECT modifier_id,modifier_slug FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_set_unique_id = '$modifier_set_id' " );

					$methode = 'insert_updated';
					woo_square_plugin_sync_square_modifier_child_to_woo( $modifer_id->modifier_id, $modifier_set_name, $modifier_set_id, $methode, $modifer_id->modifier_slug );
					$_SESSION['modifier_name_array'][ $session_key_count ] = 'pm' . _ . str_replace( ' ', '-', strtolower( $modifer_id->modifier_slug ) ) . '_' . $modifer_id->modifier_id;
				}
			}
			++$kkey;
		}
	}

	if ( $session_key_count >= 0 ) {
		$_SESSION['session_key_count'] = $session_key_count + 1;
	}

	wp_schedule_single_event( time(), 'woocommerce_flush_rewrite_rules' );
	delete_transient( 'wsm_modifier' );
	WC_Cache_Helper::invalidate_cache_group( 'woosquare-modifier' );

	return true;
}

/**
 * Syncs a Square modifier child to WooCommerce.
 *
 * @param int    $lastid     The ID of the last synchronized Square modifier.
 * @param string $modifier_set_name The name of the Square modifier set.
 * @param int    $modifier_set_id The ID of the Square modifier set.
 * @param string $methode        The sync method (insert, update, or delete).
 * @param string $modifier_slug   The slug of the Square modifier.
 *
 * @return bool True if the sync was successful, false otherwise.
 */
function woo_square_plugin_sync_square_modifier_child_to_woo( $lastid, $modifier_set_name, $modifier_set_id, $methode, $modifier_slug ) {

	$square          = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
	$synchronizer    = new SquareToWooSynchronizer( $square );
	$square_modifier = $synchronizer->get_square_modifier();

	if ( ! empty( $square_modifier ) ) {
		if ( 'inserted' === $methode ) {

			foreach ( $square_modifier as $key => $modifier ) {

				// Condition check   ID come from same.
				if ( 'MODIFIER_LIST' === $modifier['type'] && $modifier_set_id === $modifier['id'] ) {

					foreach ( $modifier['modifier_list_data'] as $key => $modex ) {

						// update condition.

						foreach ( $modex as $mod ) {

							$texonomy    = 'pm_' . strtolower( str_replace( ' ', '-', $modifier_set_name ) ) . '_' . ( $lastid );
							$parent_term = term_exists( $modifier_set_name, $texonomy ); // array is returned if taxonomy is given.

							register_taxonomy( $texonomy, 'product', array( 'hierarchical' => false ) );
							$term   = wp_insert_term(
								$mod['modifier_data']['name'], // the term.
								$texonomy,
								array(
									'description' => $mod['id'],
								)
							);
							$amount = $mod['modifier_data']['price_money']['amount'] / 100;
							update_term_meta( $term['term_id'], 'term_meta_price', sanitize_text_field( $amount ) );
							update_term_meta( $term['term_id'], 'term_meta_version', sanitize_text_field( $mod['version'] ) );

						}
					}
				}
			}
		} elseif ( 'insert_updated' === $methode ) {

			global $wpdb;

			$texonomy = 'pm_' . strtolower( str_replace( ' ', '-', $modifier_slug ) ) . '_' . ( $lastid );

			$get_result = 'get_results';
			$term_query = $wpdb->$get_result( ( 'SELECT term_id FROM ' . $wpdb->prefix . "term_taxonomy WHERE taxonomy = '$texonomy'" ) );

			if ( ! empty( $term_query ) ) {

				foreach ( $term_query as $term ) {

					$old_object = get_term_by( 'id', $term->term_id, $texonomy );

					foreach ( $square_modifier as $key => $modifier ) {

						if ( 'MODIFIER_LIST' === $modifier['type'] && $modifier_set_id === $modifier['id'] ) {

							foreach ( $modifier['modifier_list_data'] as $key => $modex ) {

								foreach ( $modex as  $keyyy => $mod ) {

									$mod_str = strtolower( str_replace( ' ', '-', $mod['modifier_data']['name'] ) );

									if ( ! empty( $old_object ) ) {
										if ( $mod['id'] === $old_object->description ) { // check modifier id.
											if ( $mod_str !== $old_object->slug ) {
												register_taxonomy( $texonomy, 'product', array( 'hierarchical' => false ) );

												$args = array(
													'name' => $mod['modifier_data']['name'],
													'description' => $mod['id'],
												);

												$term = wp_update_term(
													$old_object->term_id,
													$texonomy,
													$args
												);

												$amount      = ( $mod['modifier_data']['price_money']['amount'] / 100 );
												$old_amount  = get_term_meta( $old_object->term_id, 'term_meta_price', true );
												$old_version = get_term_meta( $old_object->term_id, 'term_meta_version', true );

												if ( $old_amount !== $amount ) {
													update_term_meta( $old_object->term_id, 'term_meta_price', sanitize_text_field( $amount ) );
												}

												if ( $old_version !== $mod['version'] ) {
													update_term_meta( $old_object->term_id, 'term_meta_version', sanitize_text_field( $mod['version'] ) );
												}
											}
										} else {

											$mod_id         = $mod['id'];
											$get_var        = 'get_var';
											$rowcount_child = $wpdb->$get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . "term_taxonomy WHERE description = '$mod_id' " );
											$texnomy        = $wpdb->$get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . "term_taxonomy WHERE taxonomy = '$texonomy' " );
											if ( $texnomy >= 1 ) {

												register_taxonomy( $texonomy, 'product', array( 'hierarchical' => false ) );

												$args = array(
													'name' => $mod['modifier_data']['name'],
													'description' => $mod['id'],
												);

												$term = wp_update_term(
													$old_object->term_id,
													$texonomy,
													$args
												);

												$amount      = ( $mod['modifier_data']['price_money']['amount'] / 100 );
												$old_amount  = get_term_meta( $old_object->term_id, 'term_meta_price', true );
												$old_version = get_term_meta( $old_object->term_id, 'term_meta_version', true );

												if ( $old_amount !== $amount ) {
													update_term_meta( $old_object->term_id, 'term_meta_price', sanitize_text_field( $amount ) );
												}

												if ( $old_version !== $mod['version'] ) {
													update_term_meta( $old_object->term_id, 'term_meta_version', sanitize_text_field( $mod['version'] ) );
												}
											} elseif ( $rowcount_child < 1 ) {

												register_taxonomy( $texonomy, 'product', array( 'hierarchical' => false ) );
												$term = wp_insert_term(
													$mod['modifier_data']['name'], // the term.
													$texonomy,
													array(
														'description' => $mod['id'],
													)
												);

												$amount = $mod['modifier_data']['price_money']['amount'] / 100;
												update_term_meta( $term['term_id'], 'term_meta_price', sanitize_text_field( $amount ) );
												update_term_meta( $term['term_id'], 'term_meta_version', sanitize_text_field( $mod['version'] ) );

											}
										}
									}
								}
							}
						}
					} //insert if not exist.

				}
			}
		}

		return true;

	}
}

/**
 * Update the action for synchronizing Square items to WooCommerce.
 *
 * This function is responsible for updating the action for synchronizing Square items to WooCommerce.
 * It receives JSON data representing the items to import and their session targets. It processes
 * the items, checks if they are new or skipped products, and adds them to WooCommerce if needed.
 * Additionally, it updates relevant metadata and status.
 */
function update_square_to_woo_action() {
	if ( ! isset( $_POST['nonce'] ) || function_exists( 'wp_verify_nonce' ) && ! empty( $_POST['nonce'] ) &&  ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'my_woosquare_ajax_nonce' ) ) {
		wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare' ) ) );
	}

	$woo_product_sync_log_transientt = get_transient( 'woo_product_sync_log_transient' );
	if ( empty( $woo_product_sync_log_transientt ) ) {
		$arr = array();
		set_transient( 'woo_product_sync_log_transient', $arr, 300 );
	}

	$woo_product_sync_log_transientt = get_transient( 'woo_product_sync_log_transient' );

	session_start();

	if ( ! empty( $_POST['import_js_item'] ) ) {
		// Get the JSON data from the POST request.
		$json_items = sanitize_text_field( wp_unslash( $_POST['import_js_item'] ) );

		$json_items = json_decode( $json_items );

	}
	if ( ! empty( $_POST['session_targets'] ) ) {
		$session_targets = sanitize_text_field( wp_unslash( $_POST['session_targets'] ) );

		// Decode the JSON data.
		$session_targets = json_decode( $session_targets );
		$session_targets = json_decode( wp_json_encode( $session_targets ), true );
	}
	$square       = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
	$synchronizer = new SquareToWooSynchronizer( $square );
	// if not a new product or skipped product (has no skus).
	if ( ( ! isset( $session_targets['target_products'][ $json_items->id ] ) )
		&& ( ! isset( $session_targets['target_products']['skipped_products'] ) )
	) {

		$square_product_sync_log_transientt = get_transient( 'square_product_sync_log_transient' );
		if ( empty( $square_product_sync_log_transientt ) ) {
			$arr = array();
			set_transient( 'square_product_sync_log_transient', $arr, 300 );
		}

		$square_product_sync_log_transientt = get_transient( 'square_product_sync_log_transient' );

		$id = $synchronizer->add_product_to_woo( $json_items, get_transient( 'square_inventory' ) );
		$square_product_sync_log_transient[ $id['id'] ][ $id['pro_status'] ] = $id;
		$square_product_sync_log_transient                                   = array_merge( $square_product_sync_log_transientt, $square_product_sync_log_transient );
		set_transient( 'square_product_sync_log_transient', $square_product_sync_log_transient, 300 );
		$square_product_sync_log_id_transient = get_transient( 'square_product_sync_log_id_transient' );
		$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
		if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
			$woosquare_sync_log                   = new WooSquare_Sync_Logs();
			$log_id                               = $woosquare_sync_log->log_data_request( $square_product_sync_log_transient, $square_product_sync_log_id_transient, 'square_to_woo', 'product' );
			if ( ! empty( $log_id ) ) {
				set_transient( 'square_product_sync_log_id_transient', $log_id, 300 );
			}
		}
		echo esc_html( $id['id'] );
		if ( ! empty( $id['id'] ) && is_numeric( $id['id'] ) ) {
			update_post_meta( $id['id'], 'is_square_sync', 1 );
			$result_stat = Helpers::TARGET_STATUS_SUCCESS;
		} else {
			$result_stat = Helpers::TARGET_STATUS_FAILURE;
			$result      = false;
		}
	}

	die();
}

/**
 * Terminate a manually started Square synchronization process.
 *
 * This function is responsible for terminating a Square synchronization process that was started manually.
 * It checks if the synchronization was started manually, updates relevant options, and ensures that the
 * function is not called twice. It also clears the session variable used to track the synchronization.
 */
function woo_square_plugin_terminate_manual_square_sync() {

	// stop synchronization if only started manually.
	if ( ! strcmp( get_option( 'woo_square_running_sync' ), 'manual' ) ) {
		update_option( 'woo_square_running_sync', false );
		update_option( 'woo_square_running_sync_time', 0 );
	}

	session_start();

	// ensure function is not called twice.
	if ( ! isset( $_SESSION['square_to_woo'] ) ) {
		return;
	}

	unset( $_SESSION['square_to_woo'] );
	echo '1';
	die();
}

/**
 * Handle enabling or disabling sandbox mode for the WooCommerce Square integration.
 *
 * This function processes a request to enable or disable sandbox mode for the WooCommerce Square integration.
 * It verifies the nonce, updates the settings accordingly, and sets a notification message.
 * Additionally, it manages the 'is_sandbox' transient based on the selected mode.
 * This function is typically used in the plugin's settings page.
 */
function enable_mode_checker() {
	if ( ! isset( $_POST['mode_checker_nonce'] ) || function_exists( 'wp_verify_nonce' ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mode_checker_nonce'] ) ), 'sandbox-mode-checker' ) ) {
		wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare' ) ) );
	}
	$woocommerce_square_settings      = get_option( 'woocommerce_square_settings' . get_transient( 'is_sandbox' ) );
	$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

	if ( $woocommerce_square_plus_settings ) { // If we are using plus.
		if ( ! empty( $_POST['action'] ) && 'enable_mode_checker' === $_POST['action'] && ! empty( $_POST['status'] ) && 'enable_production' === $_POST['status'] ) {
			$woocommerce_square_plus_settings['enable_sandbox'] = 'no';
			$msg = wp_json_encode(
				array(
					'status' => true,
					'msg'    => esc_html__( 'Production Successfully Enabled!', 'woosquare' ),
				)
			);

			// Echo the message.
			echo wp_kses_post( $msg );
		} elseif ( ! empty( $_POST['action'] ) && 'enable_mode_checker' === $_POST['action'] && ! empty( $_POST['status'] ) && 'enable_sandbox' === $_POST['status'] ) {

			if ( 'no' === $woocommerce_square_plus_settings['enable_sandbox'] ) {
				$woocommerce_square_plus_settings['enable_sandbox'] = 'yes';
			}
			$msg = wp_json_encode(
				array(
					'status' => true,
					'msg'    => esc_html__( 'Sandbox Successfully Enabled!', 'woosquare' ),
				)
			);

			// Echo the message.
			echo wp_kses_post( $msg );
		}
		update_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings', $woocommerce_square_plus_settings );
		set_transient( 'woosquare_plus_notification', $msg, 12 * HOUR_IN_SECONDS );
	} elseif ( empty( $woocommerce_square_plus_settings ) ) {
		$woocommerce_square_plus_settings = array();
		if ( ! empty( $_POST['action'] ) && 'enable_mode_checker' === $_POST['action'] && ! empty( $_POST['status'] ) && 'enable_production' === $_POST['status'] ) {
			$woocommerce_square_plus_settings['enable_sandbox'] = 'no';
			$msg = wp_json_encode(
				array(
					'status' => true,
					'msg'    => esc_html__( 'Production Successfully Enabled!', 'woosquare' ),
				)
			);

			// Echo the message.
			echo wp_kses_post( $msg );
		} elseif ( ! empty( $_POST['action'] ) && 'enable_mode_checker' === $_POST['action'] && ! empty( $_POST['status'] ) && 'enable_sandbox' === $_POST['status'] ) {
			$woocommerce_square_plus_settings['enable_sandbox'] = 'yes';
			$msg = wp_json_encode(
				array(
					'status' => true,
					'msg'    => esc_html__( 'Sandbox Successfully Enabled!', 'woosquare' ),
				)
			);

			// Echo the message.
			echo wp_kses_post( $msg );
		}
		update_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings', $woocommerce_square_plus_settings );
		set_transient( 'woosquare_plus_notification', $msg, 12 * HOUR_IN_SECONDS );
	}

	if ( ! empty( $_POST['action'] ) && 'enable_mode_checker' === $_POST['action'] && ! empty( $_POST['status'] ) && 'enable_production' === $_POST['status'] ) {
		set_transient( 'is_sandbox', '', 50000000 );

	} elseif ( ! empty( $_POST['action'] ) && 'enable_mode_checker' === $_POST['action'] && ! empty( $_POST['status'] ) && 'enable_sandbox' === $_POST['status'] ) {
		set_transient( 'is_sandbox', 'sandbox', 50000000 );
	}
	$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

	die();
}
