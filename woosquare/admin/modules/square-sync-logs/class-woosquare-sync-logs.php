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
 * This class represents the logs functionality for WooCommerce using Square.
 *
 * It provides methods and properties for handling payments and related operations.
 */
class WooSquare_Sync_Logs {

	/**
	 * Constructor for the Square Product sync logs.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'sync_log_scripts' ) );
		add_action( 'wp_ajax_delete_sync_log', array( $this, 'woosquare_delete_sync_log' ) );
		add_action( 'wp_ajax_delete_all_sync_log', array( $this, 'woosquare_delete_all_sync_log' ) );
		add_action( 'wp_ajax_get_sync_log_detail', array( $this, 'woosquare_get_sync_log_detail' ) );
		add_action( 'wp_ajax_get_filter_sync_log', array( $this, 'woosquare_get_filter_sync_log' ) );
		add_action( 'wp_ajax_reset_filter_sync_log', array( $this, 'woosquare_reset_filter_sync_log' ) );
	}

	/**
	 * Sync logs scripts function.
	 *
	 * @access public
	 */
	public function sync_log_scripts() {
		wp_register_script( 'square-sync-log', WOOSQUARE_PLUGIN_URL_LOG . '/js/SquareLogs.js?rand=' . wp_rand(), array( 'jquery' ), WOOSQUARE_VERSION, true );
		wp_localize_script(
			'square-sync-log',
			'square_sync_log_params',
			array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'log_nonce' => wp_create_nonce( 'my_log_nonce' ),
			)
		);

		wp_enqueue_script( 'square-sync-log' );
	}

	/**
	 * Logs data about deleted products or categories, either creating a new log entry or updating an existing one.
	 *
	 * @since 1.0.0 (adjust the version number as needed)
	 *
	 * @uses $wpdb WordPress database abstraction object.
	 *
	 * @param array  $data An array of data about the deleted items, including product/category IDs and deletion messages.
	 * @param int    $log_id Optional. The ID of an existing log entry to update. If not provided, a new log entry will be created.
	 * @param string $item The type of item being deleted (e.g., 'product', 'category').
	 * @param string $direction The direction of the sync ('woo_to_square' or 'square_to_woo').
	 *
	 * @return int|void The ID of the created or updated log entry, or void if an existing log entry was updated.
	 */
	public function delete_product_log_data_request( $data, $log_id, $item, $direction ) {
		global $wpdb;

		if ( ! empty( $data ) ) {
			$delete_pro = array();
			if ( ! empty( $data ) ) {
				foreach ( $data as $kkkeyy => $sync_pro ) {

					if ( 'delete' === key( $sync_pro ) ) {
						$delete_pro[ $kkkeyy ] = $sync_pro[ key( $sync_pro ) ];
					}
				}
			}
			$delete_count = count( $delete_pro );

			$message = '';
			if ( isset( $delete_count ) && $delete_count > 0 ) {
				if ( 'category' === $item ) {
					$dd = $delete_count > 1 ? 'Categories, ' : 'Category, ';
				} else {
					$dd = $delete_count > 1 ? 'Products, ' : 'Product, ';
				}
				$message .= 'Deleted ' . $delete_count . ' ' . $dd;
			}
			$status = 'Deleted';

			$date = gmdate( 'Y-m-d H:i:s' );

			if ( empty( $log_id ) ) {

				$log_id = $this->woosquare_item_sync_logs(
					$date,
					$status,
					$message,
					$direction,
					'',
					wp_json_encode( $delete_pro ),
				);
				return $log_id;
			} else {
				$this->woosquare_item_sync_update_logs(
					$date,
					$status,
					$message,
					$direction,
					'',
					wp_json_encode( $delete_pro ),
					$log_id,
				);
				return;
			}
		}
	}

	/**
	 * Logs data about a sync operation, either creating a new log entry or updating an existing one.
	 *
	 * @since 1.0.0 (adjust the version number as needed)
	 *
	 * @uses $wpdb WordPress database abstraction object.
	 *
	 * @param array  $data An array of data about the synced items, including status, messages, and product/category information.
	 * @param int    $log_id Optional. The ID of an existing log entry to update. If not provided, a new log entry will be created.
	 * @param string $direction The direction of the sync ('woo_to_square' or 'square_to_woo').
	 * @param string $item The type of item being synced (e.g., 'product', 'category').
	 *
	 * @return int|void The ID of the created or updated log entry, or void if an existing log entry was updated.
	 */
	public function log_data_request( $data, $log_id, $direction, $item ) {
		global $wpdb;

		if ( ! empty( $data ) ) {
			$product_data        = array();
			$add_pro             = array();
			$category_add_pro    = array();
			$update_pro          = array();
			$category_update_pro = array();
			$failed_pro          = array();
			$category_failed_pro = array();
			if ( ! empty( $data ) ) {
				foreach ( $data as $kkkeyy => $sync_pro ) {
					$id = $sync_pro[ key( $sync_pro ) ]['id'];
					if ( isset( $id ) && ! empty( $id ) ) {
						if ( isset( $sync_pro[ key( $sync_pro ) ]['item'] ) && 'category' === $sync_pro[ key( $sync_pro ) ]['item'] ) {
							$category = get_term_by( 'id', $id, 'product_cat' );

							$product_data[] = array(
								'name'    => $category->name,
								'item'    => 'category',
								'status'  => key( $sync_pro ),
								'message' => $sync_pro[ key( $sync_pro ) ]['message'],
							);
							if ( 'add' === key( $sync_pro ) ) {
								$category_add_pro[ $id ] = key( $sync_pro );
							} elseif ( 'update' === key( $sync_pro ) ) {
								$category_update_pro[ $id ] = key( $sync_pro );
							} elseif ( 'failed' === key( $sync_pro ) ) {
								$category_failed_pro[ $id ] = key( $sync_pro );
							}
						} else {
							$product = wc_get_product( $id );

							if ( isset( $product ) && ! empty( $product ) ) {
								$sku         = $product->get_sku();
								$pro_message = $sync_pro[ key( $sync_pro ) ]['message'];
								if ( 'variable' === $product->get_type() ) {
									$product_variation_skus = '';
									$variations             = $product->get_available_variations();
									$variations_id          = wp_list_pluck( $variations, 'variation_id' );
									foreach ( $variations_id as $var_id ) {
										$product_var             = wc_get_product( $var_id );
										$product_variation_skus .= $product_var->get_sku() . ', ';
									}
									$sku = $product_variation_skus;
									if ( isset( $sync_pro[ key( $sync_pro ) ]['var_error'] ) ) {
										$pro_message = $sync_pro[ key( $sync_pro ) ]['message'] . ' - ' . $sync_pro[ key( $sync_pro ) ]['var_error']['message'];
									}
								}
								$product_data[] = array(
									'name'    => $product->get_name(),
									'sku'     => $sku,
									'status'  => key( $sync_pro ),
									'message' => $pro_message,
								);
								if ( 'add' === key( $sync_pro ) ) {
									$add_pro[ $id ] = key( $sync_pro );
								} elseif ( 'update' === key( $sync_pro ) ) {
									$update_pro[ $id ] = key( $sync_pro );
								} elseif ( 'failed' === key( $sync_pro ) ) {
									$failed_pro[ $id ] = key( $sync_pro );
								}
							}
						}
					}
				}
			}
			$add_count             = count( $add_pro );
			$category_add_count    = count( $category_add_pro );
			$update_count          = count( $update_pro );
			$category_update_count = count( $category_update_pro );
			$failed_count          = count( $failed_pro );
			$category_failed_count = count( $category_failed_pro );

			$message = '';
			if ( isset( $category_add_count ) && $category_add_count > 0 ) {
				$dd       = $category_add_count > 1 ? 'Categories, ' : 'Category, ';
				$message .= 'Added ' . $category_add_count . ' ' . $dd;
			}
			if ( isset( $category_update_count ) && $category_update_count > 0 ) {
				$dd       = $category_update_count > 1 ? 'Categories, ' : 'Category, ';
				$message .= 'Updated ' . $category_update_count . ' ' . $dd;
			}
			if ( isset( $category_failed_count ) && $category_failed_count > 0 ) {
				$dd       = $category_failed_count > 1 ? 'Categories ' : 'Category ';
				$message .= 'Failed ' . $category_failed_count . ' ' . $dd;
			}
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

			if ( $add_count > 0 && $update_count > 0 && $failed_count > 0
				&&
				$category_add_count > 0 && $category_update_count > 0 && $category_failed_count > 0
			) {
				$status = __( 'Sync Partially', 'woosquare' );
			} elseif ( 0 < $add_count && 0 === $update_count && 0 < $failed_count ) {
				$status = __( 'Sync Partially', 'woosquare' );
			} elseif ( 0 === $add_count && 0 < $update_count && 0 < $failed_count ) {
				$status = __( 'Sync Partially', 'woosquare' );
			} elseif ( 0 === $add_count && 0 === $update_count && 0 < $failed_count ) {
				$status = __( 'Sync Failed', 'woosquare' );
			} else {
				$status = __( 'Sync Successful', 'woosquare' );
			}

			$date = gmdate( 'Y-m-d H:i:s' );
			if ( empty( $log_id ) ) {

				$log_id = $this->woosquare_item_sync_logs(
					$date,
					$status,
					$message,
					$direction,
					$item,
					wp_json_encode( $product_data ),
				);
				return $log_id;
			} else {
				$this->woosquare_item_sync_update_logs(
					$date,
					$status,
					$message,
					$direction,
					$item,
					wp_json_encode( $product_data ),
					$log_id,
				);
				return;
			}
		}
	}

	/**
	 * Inserts a new sync log entry into the database.
	 *
	 * @since 1.0.0 (adjust the version number as needed)
	 *
	 * @uses $wpdb WordPress database abstraction object.
	 *
	 * @param string $time The timestamp of the sync event.
	 * @param string $status The status of the sync event (e.g., 'success', 'failed').
	 * @param string $message A detailed message about the sync event.
	 * @param string $direction The direction of the sync ('woo_to_square' or 'square_to_woo').
	 * @param string $item The item being synced (e.g., product ID, order ID).
	 * @param mixed  $data Additional data related to the sync event (can be an array or serialized string).
	 */
	public function woosquare_item_sync_logs( $time, $status, $message, $direction, $item, $data ) {
		$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
		if ( isset( $activate_modules_woosquare_plus['items_sync_log']['module_activate'] ) && true === $activate_modules_woosquare_plus['items_sync_log']['module_activate'] ) {
			global $wpdb;
			$insert = 'insert';
			if ( ! empty( $message ) ) {

				$result = $wpdb->$insert(
					$wpdb->prefix . WOO_SQUARE_ITEM_SYNC_LOGS_TABLE,
					array(
						'log_time'       => $time,
						'status'         => $status,
						'message'        => $message,
						'sync_direction' => $direction,
						'item'           => $item,
						'enviroment'     => get_transient( 'is_sandbox' ),
						'data'           => $data,
					)
				);

				return $wpdb->insert_id;
			}
		}
	}

	/**
	 * Updates an existing sync log in the database with new information.
	 *
	 * @since 1.0.0 (adjust the version number as needed)
	 *
	 * @uses $wpdb WordPress database abstraction object.
	 *
	 * @param string $time The timestamp of the sync event.
	 * @param string $status The status of the sync event (e.g., 'success', 'failed').
	 * @param string $message A detailed message about the sync event.
	 * @param string $direction The direction of the sync ('woo_to_square' or 'square_to_woo').
	 * @param string $item The item being synced (e.g., product ID, order ID).
	 * @param mixed  $data Additional data related to the sync event (can be an array or serialized string).
	 * @param int    $log_id The ID of the sync log to update.
	 */
	public function woosquare_item_sync_update_logs( $time, $status, $message, $direction, $item, $data, $log_id ) {
		$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
		if ( isset( $activate_modules_woosquare_plus['items_sync_log']['module_activate'] ) && true === $activate_modules_woosquare_plus['items_sync_log']['module_activate'] ) {
			global $wpdb;
			$update = 'update';
			if ( ! empty( $log_id ) ) {

				$result = $wpdb->$update(
					$wpdb->prefix . WOO_SQUARE_ITEM_SYNC_LOGS_TABLE,
					array(
						'log_time'       => $time,
						'status'         => $status,
						'message'        => $message,
						'sync_direction' => $direction,
						'item'           => $item,
						'enviroment'     => get_transient( 'is_sandbox' ),
						'data'           => $data,
					),
					array( 'id' => $log_id )
				);
			}
		}
	}

	/**
	 * Deletes a specific sync log from the database.
	 *
	 * @since 1.0.0 (adjust the version number as needed)
	 *
	 * @uses $wpdb WordPress database abstraction object.
	 */
	public function woosquare_delete_sync_log() {
		if ( ! isset( $_POST['log_nonce'] ) || wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['log_nonce'] ) ), 'my_log_nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare' ) ) );
		}
		if ( ! empty( $_POST['log_id'] ) ) {

			global $wpdb;
			$delete = 'delete';
			$result = $wpdb->$delete(
				$wpdb->prefix . WOO_SQUARE_ITEM_SYNC_LOGS_TABLE,
				array(
					'id' => sanitize_text_field( wp_unslash( $_POST['log_id'] ) ),
				)
			);

			echo esc_html( $html );
			die();

		}
	}

	/**
	 * Retrieves and displays filtered sync logs based on date range and sync direction.
	 *
	 * @since 1.0.0 (adjust the version number as needed)
	 *
	 * @uses $wpdb WordPress database abstraction object.
	 */
	public function woosquare_get_filter_sync_log() {
		if ( ! isset( $_POST['log_nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['log_nonce'] ) ), 'my_log_nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare' ) ) );
		}
		if ( isset( $_POST['fromDate'] ) && isset( $_POST['toDate'] ) ) {
			$formatted_date_range_from = gmdate( 'Y-m-d H:i:s', strtotime( sanitize_text_field( wp_unslash( $_POST['fromDate'] ) ) ) );
			$formatted_date_range_to   = gmdate( 'Y-m-d H:i:s', strtotime( sanitize_text_field( wp_unslash( $_POST['toDate'] ) ) . ' 23:59:59' ) );
		}
		if ( ! empty( $formatted_date_range_from ) && ! empty( $formatted_date_range_to ) ) {
			global $wpdb;
			$prepare        = 'prepare';
			$get_results    = 'get_results';
			$table_name     = $wpdb->prefix . WOO_SQUARE_ITEM_SYNC_LOGS_TABLE;
			$sync_direction = isset( $_POST['sync_direction'] ) ? sanitize_text_field( wp_unslash( $_POST['sync_direction'] ) ) : '';
			$query          = $wpdb->$prepare(
				"
				SELECT * FROM $table_name
				WHERE log_time BETWEEN %s AND %s
				AND sync_direction = %s
				AND enviroment = %s
				ORDER BY log_time DESC
				",
				$formatted_date_range_from,
				$formatted_date_range_to,
				$sync_direction,
				get_transient( 'is_sandbox' )
			);

			$event_query_results = $wpdb->$get_results( $query );

			$html = '';
			if ( isset( $event_query_results ) && ! empty( $event_query_results ) ) {
				foreach ( $event_query_results as $filter_result ) {
					if ( isset( $filter_result->sync_direction ) && 'woo_to_square' === $filter_result->sync_direction ) {
						$sync_status = strtolower( str_replace( ' ', '_', $filter_result->status ) );
						$html       .= '<tr class="product_list_table_body_row woo_to_square_table_body_row">
							<td class="product_list_table_data woo_to_square_table_data">' . esc_html( isset( $filter_result->log_time ) ? $filter_result->log_time : '' ) . '</td>
							<td class="product_list_table_data woo_to_square_table_data"><span class="' . $sync_status . '">' . esc_html( isset( $filter_result->status ) ? $filter_result->status : '' ) . '</span></td>
							<td class="product_list_table_data woo_to_square_table_data">' . esc_html( isset( $filter_result->message ) ? $filter_result->message : '' ) . '</td>
							<td class="product_list_table_data woo_to_square_table_data">
								<button id="woo-to-square-view-log" data-log-id="' . esc_html( isset( $filter_result->id ) ? $filter_result->id : '' ) . '" class="log_action_botton"><i class="far fa-eye-slash" style="color: #7460EE;"></i></button>
								<button id="woo-to-square-delete-log" data-log-id="' . esc_html( isset( $filter_result->id ) ? $filter_result->id : '' ) . '" class="log_delete_action_botton"><i class="far fa-trash-alt" style="color: #cb1515;"></i></button>
							</td>
						  </tr>';
					} else {
						$sync_status = strtolower( str_replace( ' ', '_', $filter_result->status ) );
						$html       .= '<tr class="product_list_table_body_row square_to_woo_table_body_row">
							<td class="product_list_table_data square_to_woo_table_data">' . esc_html( isset( $filter_result->log_time ) ? $filter_result->log_time : '' ) . '</td>
							<td class="product_list_table_data square_to_woo_table_data"><span class="' . $sync_status . '">' . esc_html( isset( $filter_result->status ) ? $filter_result->status : '' ) . '</span></td>
							<td class="product_list_table_data square_to_woo_table_data">' . esc_html( isset( $filter_result->message ) ? $filter_result->message : '' ) . '</td>
							<td class="product_list_table_data square_to_woo_table_data">
								<button id="woo-to-square-view-log" data-log-id="' . esc_html( isset( $filter_result->id ) ? $filter_result->id : '' ) . '" class="log_action_botton"><i class="far fa-eye-slash" style="color: #7460EE;"></i></button>
								<button id="woo-to-square-delete-log" data-log-id="' . esc_html( isset( $filter_result->id ) ? $filter_result->id : '' ) . '" class="log_delete_action_botton"><i class="far fa-trash-alt" style="color: #cb1515;"></i></button>
							</td>
						  </tr>';
					}
				}
			} else {
				$html .= '<tr class="empty-row"><td colspan="6">' . esc_html__( 'No logs found.', 'woosquare' ) . '</td></tr>';
			}

			echo wp_kses_post( $html );
			die();

		}
	}

	/**
	 * Retrieves and displays sync logs based on a filtered sync direction.
	 *
	 * @since 1.0.0 (adjust the version number as needed)
	 *
	 * @uses $wpdb WordPress database abstraction object.
	 */
	public function woosquare_reset_filter_sync_log() {
		if ( ! isset( $_POST['log_nonce'] ) || wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['log_nonce'] ) ), 'my_log_nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare' ) ) );
		}
		global $wpdb;
		$get_results    = 'get_results';
		$table_name     = $wpdb->prefix . WOO_SQUARE_ITEM_SYNC_LOGS_TABLE;
		$sync_direction = isset( $_POST['sync_direction'] ) ? sanitize_text_field( wp_unslash( $_POST['sync_direction'] ) ) : '';
		$results        = $wpdb->$get_results( "SELECT * FROM $table_name WHERE `sync_direction` = '$sync_direction' AND `enviroment` = '" . get_transient( 'is_sandbox' ) . "' ORDER BY `log_time` DESC" );

		if ( isset( $results ) ) {
			$html = '';
			foreach ( $results as $filter_result ) {
				if ( isset( $filter_result->sync_direction ) && 'woo_to_square' === $filter_result->sync_direction ) {
					$sync_status = strtolower( str_replace( ' ', '_', $filter_result->status ) );
					$html       .= '<tr class="product_list_table_body_row woo_to_square_table_body_row">
						<td class="product_list_table_data woo_to_square_table_data">' . esc_html( isset( $filter_result->log_time ) ? $filter_result->log_time : '' ) . '</td>
						<td class="product_list_table_data woo_to_square_table_data"><span class="' . $sync_status . '">' . esc_html( isset( $filter_result->status ) ? $filter_result->status : '' ) . '</span></td>
						<td class="product_list_table_data woo_to_square_table_data">' . esc_html( isset( $filter_result->message ) ? $filter_result->message : '' ) . '</td>
						<td class="product_list_table_data woo_to_square_table_data">
							<button id="woo-to-square-view-log" data-log-id="' . esc_html( isset( $filter_result->id ) ? $filter_result->id : '' ) . '" class="log_action_botton"><i class="far fa-eye-slash" style="color: #7460EE;"></i></button>
							<button id="woo-to-square-delete-log" data-log-id="' . esc_html( isset( $filter_result->id ) ? $filter_result->id : '' ) . '" class="log_delete_action_botton"><i class="far fa-trash-alt" style="color: #cb1515;"></i></button>
						</td>
					  </tr>';
				} else {
					$sync_status = strtolower( str_replace( ' ', '_', $filter_result->status ) );
					$html       .= '<tr class="product_list_table_body_row square_to_woo_table_body_row">
						<td class="product_list_table_data square_to_woo_table_data">' . esc_html( isset( $filter_result->log_time ) ? $filter_result->log_time : '' ) . '</td>
						<td class="product_list_table_data square_to_woo_table_data"><span class="' . $sync_status . '">' . esc_html( isset( $filter_result->status ) ? $filter_result->status : '' ) . '</span></td>
						<td class="product_list_table_data square_to_woo_table_data">' . esc_html( isset( $filter_result->message ) ? $filter_result->message : '' ) . '</td>
						<td class="product_list_table_data square_to_woo_table_data">
							<button id="woo-to-square-view-log" data-log-id="' . esc_html( isset( $filter_result->id ) ? $filter_result->id : '' ) . '" class="log_action_botton"><i class="far fa-eye-slash" style="color: #7460EE;"></i></button>
							<button id="woo-to-square-delete-log" data-log-id="' . esc_html( isset( $filter_result->id ) ? $filter_result->id : '' ) . '" class="log_delete_action_botton"><i class="far fa-trash-alt" style="color: #cb1515;"></i></button>
						</td>
					  </tr>';
				}
			}
		}

		echo wp_kses_post( $html );
		die();
	}

	/**
	 * Deletes all sync logs matching a specified sync direction.
	 *
	 * @since 1.0.0 (adjust the version number as needed)
	 *
	 * @uses $wpdb WordPress database abstraction object.
	 */
	public function woosquare_delete_all_sync_log() {
		if ( ! isset( $_POST['log_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['log_nonce'] ) ), 'my_log_nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare' ) ) );
		}
		if ( isset( $_POST['sync_direction'] ) ) {
			global $wpdb;
			$query          = 'query';
			$table_name     = $wpdb->prefix . WOO_SQUARE_ITEM_SYNC_LOGS_TABLE;
			$sync_direction = sanitize_text_field( wp_unslash( $_POST['sync_direction'] ) );
			$sql            = $wpdb->prepare(
				"DELETE FROM {$table_name} WHERE sync_direction = %s", // phpcs:ignore
				$sync_direction
			);
			// Execute the query.
			$result = $wpdb->$query( $sql );
			if ( false === $result ) {
				// There was an error.
				echo 'Error deleting rows: ' . esc_html( $wpdb->last_error );
			} else {
				// Rows deleted successfully.
				echo true;
			}
		}
		wp_die();
	}

	/**
	 * Retrieves detailed sync log information for a specific log ID.
	 *
	 * @since 1.0.0 (adjust the version number as needed)
	 *
	 * @uses $wpdb WordPress database abstraction object.
	 */
	public function woosquare_get_sync_log_detail() {
		if ( ! isset( $_POST['log_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['log_nonce'] ) ), 'my_log_nonce' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare' ) ) );
		}
		if ( ! empty( $_POST['log_id'] ) ) {

			global $wpdb;
			$get_results = 'get_results';
			$log_id      = (int) $_POST['log_id'];
			$table_name  = $wpdb->prefix . WOO_SQUARE_ITEM_SYNC_LOGS_TABLE;
			$results     = $wpdb->$get_results( "SELECT `data` FROM $table_name WHERE `id` = $log_id" );

			$product_html  = '';
			$category_html = '';
			if ( ! empty( $results[0] ) ) {
				$sync_data = json_decode( $results[0]->data );

				foreach ( $sync_data as $kk => $dd ) {

					if ( 'update' === $dd->status ) {
						$class  = '';
						$status = 'Updated';
					} elseif ( 'add' === $dd->status ) {
						$class  = '';
						$status = 'Added';
					} elseif ( 'failed' === $dd->status ) {
						$class  = 'sync_failed_pro';
						$status = 'Failed';
					} elseif ( 'deleted' === $dd->status ) {
						$class  = '';
						$status = 'Deleted';
					}
					$message = $dd->message;
					if ( isset( $message->errors ) && is_array( $message->errors ) ) {
						foreach ( $message->errors as $error ) {
							$message = $error->detail;
						}
					}
					if ( isset( $dd->item ) && 'category' === $dd->item ) {
						$category_html .= '<tr class="log_detail_table_category_body_row">
								<td class="log_detail_table_category_data"><span class="log_detail_table_category_data_text ' . $class . '">' . $dd->name . '</span></td>
								<td class="log_detail_table_category_data"><span class="log_detail_table_category_data_text ' . $class . '">' . $status . '</span></td>
								<td class="log_detail_table_category_data"><span class="log_detail_table_category_data_text ' . $class . '">' . $message . '</span></td>
							  </tr>';
					} else {
						$product_html .= '<tr class="log_detail_table_body_row">
								<td class="log_detail_table_data"><span class="log_detail_table_data_text ' . $class . '">' . $dd->name . '</span></td>
								<td class="log_detail_table_data"><span class="log_detail_table_data_text ' . $class . '">' . $dd->sku . '</span></td>
								<td class="log_detail_table_data"><span class="log_detail_table_data_text ' . $class . '">' . $status . '</span></td>
								<td class="log_detail_table_data"><span class="log_detail_table_data_text ' . $class . '">' . $dd->message . '</span></td>
							  </tr>';
					}
				}
			}
			$data = array(
				'category_html' => $category_html,
				'product_html'  => $product_html,
			);
			echo wp_json_encode( $data );
			die();

		}
	}
}
new WooSquare_Sync_Logs();
