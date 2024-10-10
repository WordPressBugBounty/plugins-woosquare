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
 * Synchronize From WooCommerce To Square Class
 */
class WooToSquareSynchronizer {

	/**
	 * Square class instance.
	 *
	 * @var square square class instance
	 */
	protected $square;

	/**
	 * Square class object.
	 *
	 * @param object $square object of square class.
	 */
	public function __construct( $square ) {

		$this->square = $square;
	}

	/**
	 * Automatic Sync All products, categories from Woo-Commerce to Square
	 */
	public function sync_from_woo_to_square() {

		session_start();
		$square                     = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
		$square_to_woo_synchronizer = new SquareToWooSynchronizer( $square );
		$square_items               = $square_to_woo_synchronizer->get_square_items();

		if ( $square_items ) {
			$square_items = $this->simplify_square_items_object( $square_items );
		} else {
			$square_items = array();
		}

		// 1-get unsynchronized categories (add/update)
		$categories        = $this->get_unsynchronized_categories();
		$square_categories = $this->get_categories_square_ids( $categories );

		foreach ( $categories as $cat ) {

			$square_id = null;

			if ( isset( $square_categories[ $cat->term_id ] ) ) {      // update.
				$square_id = $square_categories[ $cat->term_id ];
				$result    = $this->edit_category( $cat, $square_id );

			} else {                                         // add.
				$result = $this->add_category( $cat );

			}

			if ( true === $result['status'] ) {
				update_option( "is_square_sync_{$cat->term_id}", 1 );
			}
			$_SESSION['woo_product_sync_log'][ $cat->term_id ][ $result['pro_status'] ] = $result;

			$session_woo_product_sync_log    = isset( $_SESSION['woo_product_sync_log'] ) ? sanitize_text_field( wp_unslash( $_SESSION['woo_product_sync_log'] ) ) : '';
			$session_woo_product_sync_log_id = isset( $_SESSION['woo_product_sync_log_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['woo_product_sync_log_id'] ) ) : '';
			$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
			if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
				$woosquare_sync_log              = new WooSquare_Sync_Logs();
				$log_id                          = $woosquare_sync_log->log_data_request( $session_woo_product_sync_log, $session_woo_product_sync_log_id, 'woo_to_square', 'category' );
				if ( ! empty( $log_id ) ) {
					$_SESSION['woo_product_sync_log_id'] = $log_id;
				}
			}
			// check if response returned is bool or error response message.
			$message = null;
			if ( ! is_bool( $result['status'] ) ) {
				$message          = $result['message'];
				$result['status'] = false;
			}
		}

		// 2-get unsynchronized products (add/update)
		$unsync_products = $this->get_unsynchronized_products();
		$this->get_products_square_ids( $unsync_products, $excluded_products );
		$product_ids = array( 0 );

		foreach ( $unsync_products as $product ) {
			if ( in_array( $product->ID, $excluded_products, true ) ) {
				continue;
			}
			$product_ids[] = $product->ID;
		}

		$posts_per_page = -1;

		/* get all products from woocommerce */
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $posts_per_page,
			'include'        => $product_ids,
		);

		$woocommerce_products = get_posts( $args );

		// Update Square with products from WooCommerce.
		if ( $woocommerce_products ) {

			foreach ( $woocommerce_products as $woocommerce_product ) {
				// sleep(2);.
				// check if woocommerce product sku is exists in square product sku.
				$product_square_id = $this->check_sku_in_square( $woocommerce_product, $square_items );

				if ( ! $product_square_id ) {
						// not exist in square so check in woo this product already updated.
						$product_square_id = get_post_meta( $woocommerce_product->ID, 'square_id', true );
					if ( $product_square_id ) {
						$exploded_product_square_id = explode( '-', $product_square_id );
						if ( count( $exploded_product_square_id ) === 5 ) {

							$product = wc_get_product( $woocommerce_product->ID );

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

					$result = $this->add_product( $woocommerce_product, $product_square_id );

						// Sync modifier  woo into square.

				$modifier_value = get_post_meta( $woocommerce_product->ID, 'product_modifier_group_name', true );

				$modifier_set_name = array();

				if ( ! empty( $modifier_value ) ) {

						session_start();

					$_SESSION['productid'] = $woocommerce_product->ID;

					$_SESSION['product_loop_id'] = $woocommerce_product->ID;

					$kkey = 0;

					foreach ( $modifier_value as $mod ) {

						$mod = ( explode( '_', $mod ) );

						if ( ! empty( $mod[2] ) ) {

							global $wpdb;
							$get_var      = 'get_var';
							$rcount       = $wpdb->$get_var( 'SELECT modifier_set_unique_id FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_id = '$mod[2]' " );
							$get_result   = 'get_results';
							$raw_modifier = $wpdb->$get_result( "SELECT * FROM {$wpdb->prefix}woosquare_modifier WHERE modifier_id = '$mod[2]';" );

							foreach ( $raw_modifier as $raw ) {
								$mod_ids = '';
								if ( ! empty( $raw->modifier_set_unique_id ) ) {
									$mod_ids = $raw->modifier_set_unique_id;
								} else {
									$mod_ids = $raw->modifier_id;
								}

								if ( empty( $raw->modifier_set_unique_id ) ) {
									$modifier_set_name = $raw->modifier_set_name . '_' . $raw->modifier_set_unique_id . '_' . $raw->modifier_id . '_' . $raw->modifier_public . '_' . $raw->modifier_version . '_' . $raw->modifier_slug . '_add_modifier';
								} else {
									$modifier_set_name = $raw->modifier_set_name . '_' . $raw->modifier_set_unique_id . '_' . $raw->modifier_id . '_' . $raw->modifier_public . '_' . $raw->modifier_version . '_' . $raw->modifier_slug . '_modifier';
								}
							}
								$modifier_result = $this->woo_square_plugin_sync_woo_modifier_to_square_modifier( $modifier_set_name );

						}
					}
						unset( $_SESSION['session_key_count'] );
					unset( $_SESSION['product_loop_id'] );
				}

				// update square sync post meta.
				if ( true === $result ) {
					update_post_meta( $woocommerce_product->ID, 'is_square_sync', 1 );
				}

				// log the process.
				// check if response returned is bool or error response message.
				$message = null;
				if ( ! is_bool( $result ) ) {
					$message = $result['message'];
					$result  = false;
				}
			}
		}

		// 3-get deleted categories/products.
		$deleted_elms = $this->get_unsynchronized_deleted_elements();
		$action       = Helpers::ACTION_DELETE;
		foreach ( $deleted_elms as $del_element ) {

			if ( $del_element->square_id ) {

				if ( Helpers::TARGET_TYPE_CATEGORY === $del_element->target_type ) {     // category.
					$result = $this->delete_category( $del_element->square_id );
				} elseif ( Helpers::TARGET_TYPE_PRODUCT === $del_element->target_type ) { // product.
					if ( get_option( 'disable_auto_delete' ) !== 1 ) {
						$result = $this->delete_product_or_get( $del_element->square_id, 'DELETE' );
					}
				}

				// delete category from plugin delete table.
				if ( true === $result ) {
					global $wpdb;
					$delete = 'delete';
					$wpdb->$delete(
						$wpdb->prefix . WOO_SQUARE_TABLE_DELETED_DATA,
						array( 'square_id' => $del_element->square_id )
					);
				}
				// log the process.
				// check if response returned is bool or error response message.
				$message = null;
				if ( ! is_bool( $result ) ) {
					$message = $result['message'];
					$result  = false;
				}
				+ Helpers::sync_db_log(
					$action,
					gmdate( 'Y-m-d H:i:s' ),
					$sync_type,
					$sync_direction,
					$del_element->target_id,
					$del_element->target_type,
					$result ? Helpers::TARGET_STATUS_SUCCESS : Helpers::TARGET_STATUS_FAILURE,
					$log_id,
					$del_element->name,
					$del_element->square_id,
					$message
				);
			}
		}
	}

	/**
	 * Synchronize WooCommerce product modifiers with Square modifiers.
	 *
	 * This function handles the creation and update of modifiers in Square
	 * based on WooCommerce product modifiers.
	 *
	 * @param string $product_id The product identifier.
	 * @return void
	 */
	public function woo_square_plugin_sync_woo_modifier_to_square_modifier( $product_id ) {

		global $wpdb;
		$modifier_check   = ( explode( '_', $product_id ) );
		$get_row          = 'get_row';
		$modifier_checker = $wpdb->$get_row( ( 'SELECT * FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_id = '$modifier_check[2]'" ) );

		if ( empty( $modifier_checker->modifier_set_unique_id ) ) {
			if ( strpos( $product_id, 'add_modifier' ) ) {

				// create.
				$prepare           = '';
				$modifier_name     = ( explode( '_', $product_id ) );
				$modifier_set_name = str_replace( '-', ' ', $modifier_name[5] );

				if ( 1 === $modifier_name[3] ) {
					$selected_type = 'MULTIPLE';
				} else {
					$selected_type = 'SINGLE';
				}

				$dynamic_arr = array();
				global $wpdb;

				if ( ! empty( $modifier_name[2] ) && ! empty( $modifier_set_name ) ) {
					$texonomy   = 'pm_' . strtolower( str_replace( ' ', '-', $modifier_set_name ) ) . '_' . ( $modifier_name[2] );
					$get_result = 'get_results';
					$term_query = $wpdb->$get_result( ( 'SELECT term_id FROM ' . $wpdb->prefix . "term_taxonomy WHERE taxonomy = '$texonomy'" ) );

					if ( ! empty( $term_query ) ) {
						$keyy = 0;
						foreach ( $term_query as $key => $term ) {

							$object = get_term_by( 'id', $term->term_id, $texonomy );
							$amount = get_term_meta( $object->term_id, 'term_meta_price', true ) * 100;

							if ( empty( $object->description ) ) {
								if ( ! empty( $object->name ) ) {
									$dynamic_arr[ $key ] = (object) array(
										'type'          => 'MODIFIER',
										'id'            => '#' . wp_rand(),
										'modifier_data' => (object) array(
											'name'        => $object->name,
											'price_money' => (object) array(
												'amount'   => (int) $amount,
												'currency' => get_option( 'woocommerce_currency' ),
											),
										),
									);
								}
							} else {

								$dynamic_arr[ $key ] = (object) array(
									'type' => 'MODIFIER',
									'id'   => '#' . wp_rand(),
								);

							}

							++$keyy;
						}
					} else {

						$dynamic_arr[0] = (object) array(
							'type' => 'MODIFIER',
							'id'   => '#' . wp_rand(),
						);

					}

					$data                    = array();
					$data['idempotency_key'] = uniqid();
					$data['object']          = (object) array(
						'type'               => 'MODIFIER_LIST',
						'id'                 => '#' . wp_rand(),
						'modifier_list_data' => (object) array(
							'name'           => $modifier_checker->modifier_set_name,
							'selection_type' => $selected_type,
							'modifiers'      => $dynamic_arr,

						),
					);

				}

				$tquery = $wpdb->$get_result( ( 'SELECT modifier_set_unique_id FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_id = '$modifier_name[2]'" ) );
				if ( empty( $tquery->modifier_set_unique_id ) ) {
					$data_json = wp_json_encode( $data );
					$url       = $this->square->get_square_v2_url() . 'catalog/object';
					$result    = wp_remote_post(
						$url,
						array(
							'method'      => 'POST',
							'headers'     => array(
								'Authorization'  => 'Bearer ' . $this->square->get_access_token(),
								'Content-Type'   => 'application/json',
								'Content-Length' => strlen( $data_json ),
							),
							'httpversion' => '1.0',
							'sslverify'   => true,
							'body'        => $data_json,
						)
					);

					if ( '200' === $result['response']['code'] && 'OK' === $result['response']['message'] ) {

						$result = json_decode( $result['body'], true );

						if ( 'MODIFIER_LIST' === $result['catalog_object']['type'] ) {

							foreach ( $result['catalog_object']['modifier_list_data'] as $keyy => $modifier ) {

								foreach ( $result['id_mappings'] as $key => $map_id ) {

									foreach ( $result['catalog_object']['modifier_list_data']['modifiers'] as $kk => $mod ) {

										if ( $map_id['object_id'] === $result['catalog_object']['id'] ) {

											$modifier_name = $result['catalog_object']['modifier_list_data']['name'];
											global $wpdb;
											$get_row     = 'get_row';
											$modifier_id = $wpdb->$get_row( 'SELECT * FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_set_name ='$modifier_name' OR modifier_slug = '$modifier_name'  AND modifier_set_unique_id IS NULL" );

											if ( ! empty( $modifier_id->modifier_id ) && ! empty( $modifier_id->modifier_set_name ) && empty( $modifier_id->modifier_set_unique_id ) && empty( $modifier_id->modifier_version ) ) {
												$format = array( '%s', '%d' );
												$data   = array(
													'modifier_set_unique_id' => $result['catalog_object']['id'],
													'modifier_version' => $result['catalog_object']['version'],

												);
												$update = 'update';
												$wpdb->$update( $wpdb->prefix . 'woosquare_modifier', $data, array( 'modifier_id' => $modifier_id->modifier_id ), $format, array( '%d' ) );
												session_start();
												$_SESSION['modifier_id']   = $modifier_id->modifier_id;
												$_SESSION['modifier_slug'] = $modifier_id->modifier_slug;
											}
										}

										if ( $map_id['object_id'] === $mod['id'] ) {
											global $wpdb;
											if ( ! empty( $_SESSION['modifier_slug'] ) && ! empty( $_SESSION['modifier_id'] ) ) {

												// new code.
												$session_modifier_slug = sanitize_text_field( wp_unslash( $_SESSION['modifier_slug'] ) );
												$session_modifier_id   = sanitize_text_field( wp_unslash( $_SESSION['modifier_id'] ) );
												$texonomy              = 'pm_' . strtolower( str_replace( ' ', '-', $session_modifier_slug ) ) . '_' . ( $session_modifier_id );

												$term_query = $wpdb->$get_result( ( 'SELECT * FROM ' . $wpdb->prefix . "term_taxonomy WHERE taxonomy = '$texonomy'" ) );

												foreach ( $term_query as $kgs => $term ) {

													$midd = $result['catalog_object']['modifier_list_data']['modifiers'][ $kgs ]['id'];
													$wpdb->query( // phpcs:ignore
														$wpdb->prepare(
															"UPDATE {$wpdb->prefix}term_taxonomy SET description = %s WHERE term_id = %d",
															$midd,
															$term->term_id
														)
													);
													update_term_meta( $term->term_id, 'term_meta_version', sanitize_text_field( $mod['version'] ) );

												}
											}
										}
									}
								}
							}
						}

						if ( ! empty( $_SESSION['productid'] ) ) {
							$session_productid = sanitize_text_field( wp_unslash( $_SESSION['productid'] ) );
							$square_id         = get_post_meta( $session_productid, 'square_id', true );

							$modifier_value = get_post_meta( $session_productid, 'product_modifier_group_name', true );

							if ( ! empty( $modifier_value ) ) {
								$kkey      = 0;
								$mod_array = array();
								foreach ( $modifier_value as $keyy => $mod ) {

									$mod = ( explode( '_', $mod ) );

									if ( ! empty( $mod ) ) {
										global $wpdb;
										$get_var            = 'get_var';
										$rcount             = $wpdb->$get_var( 'SELECT modifier_set_unique_id FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_id = '$mod[2]' " );
										$mod_array[ $keyy ] = $rcount;

									}
								}

								$data = array(
									'item_ids' => array(
										$square_id,
									),
									'modifier_lists_to_enable' => $mod_array,
								);

								$data_json = wp_json_encode( $data );
								$url       = $this->square->get_square_v2_url() . 'catalog/update-item-modifier-lists';
								$result    = wp_remote_post(
									$url,
									array(
										'method'      => 'POST',
										'headers'     => array(
											'Authorization' => 'Bearer ' . $this->square->get_access_token(),
											'Content-Type' => 'application/json',
											'Content-Length' => strlen( $data_json ),
										),
										'httpversion' => '1.0',
										'sslverify'   => true,
										'body'        => $data_json,
									)
								);

								if ( '200' === $result['response']['code'] && 'OK' === $result['response']['message'] ) {

									update_post_meta( $session_productid, 'product_sync_square_id' . $session_productid, $mod[2] );
								}
							}
						}
					}
				}
			}
		} elseif ( strpos( $product_id, '_modifier' ) ) {

				global $wpdb;
				$modifier_name     = ( explode( '_', $product_id ) );
				$get_row           = 'get_row';
				$modifier_checker  = $wpdb->$get_row( ( 'SELECT * FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_id = '$modifier_name[2]'" ) );
				$modifier_set_name = str_replace( '-', ' ', $modifier_name[5] );
				$mod_name          = str_replace( '-', ' ', $modifier_name[0] );

					$url         = esc_url( 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/list' );
						$headers = array(
							'Authorization' => 'Bearer ' . $this->square->get_access_token(), // Use verbose mode in cURL to determine the format you want for this header.
							'Content-Type'  => 'application/json',
							'types'         => 'MODIFIER_LIST',
						);

						$method                 = 'GET';
						$args                   = array( 'types' => 'MODIFIER_LIST' );
						$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
						$square                 = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );

						if ( get_option( '_transient_timeout_' . $woo_square_location_id . 'modifier_transient_' . __FUNCTION__ ) > time() ) {

								$response        = get_transient( $woo_square_location_id . 'modifier_transient_' . __FUNCTION__ );
								$modifier_object = json_decode( $response['body'], true );

						} else {

							$response        = $square->wp_remote_woosquare( $url, $args, $method, $headers, $response );
							$modifier_object = json_decode( $response['body'], true );
							if ( ! empty( $modifier_object ) ) {
								if ( count( $modifier_object ) > 999 ) {
										$interval = 300;
								} else {
										$interval = 0;
								}
							}
							set_transient( $woo_square_location_id . 'modifier_transient_' . __FUNCTION__, $response, $interval );

						}

						foreach ( $modifier_object as $mod_object ) {

							if ( $mod_object['id'] === $modifier_name[1] ) {

								if ( ! empty( $modifier_name[1] ) && ! empty( $modifier_set_name ) ) {

									if ( 1 === $modifier_name[3] ) {
										$selected_type = 'MULTIPLE';
									} else {
										$selected_type = 'SINGLE';
									}

									$texonomy   = 'pm_' . strtolower( str_replace( ' ', '-', $modifier_name[5] ) ) . '_' . ( $modifier_name[2] );
									$get_result = 'get_results';
									$term_query = $wpdb->$get_result( ( 'SELECT term_id FROM ' . $wpdb->prefix . "term_taxonomy WHERE taxonomy = '$texonomy'" ) );

									if ( ! empty( $term_query ) ) {

										$keyy        = 0;
										$dynamic_arr = array();
										foreach ( $term_query as $key => $term ) {

											$object = get_term_by( 'id', $term->term_id, $texonomy );

											$amount = get_term_meta( $object->term_id, 'term_meta_price', true ) * 100;

											$version = get_term_meta( $object->term_id, 'term_meta_version', true );

											foreach ( $mod_object['modifier_list_data']['modifiers'] as $term_obj ) {

												if ( $term_obj['id'] === $object->description ) {

													if ( ! empty( $object->description ) ) {

														if ( ! empty( $object->name ) ) {

															$dynamic_arr[ $key ] = (object) array(

																'type'          => 'MODIFIER',
																'id'            => $object->description,
																'version'       => $term_obj['version'],
																'modifier_data' => (object) array(
																	'name'        => $object->name,
																	'price_money' => (object) array(
																		'amount'   => (int) $amount,
																		'currency' => get_option( 'woocommerce_currency' ),
																	),
																),
															);

														} else {

															$dynamic_arr[ $key ] = (object) array(
																'type' => 'MODIFIER',
																'id'   => $object->description,
															);

														}
													}
												}
											}
											if ( empty( $object->description ) ) {

												if ( ! empty( $object->name ) ) {

													$dynamic_arr[ $key ] = (object) array(

														'type'          => 'MODIFIER',
														'id'        => '#' . wp_rand(),
														'modifier_data' => (object) array(
															'name'        => $object->name,
															'price_money' => (object) array(
																'amount'   => (int) $amount,
																'currency' => get_option( 'woocommerce_currency' ),
															),
														),
													);

												}
											}
										}++$keyy;
									}

									$data                    = array();
									$data['idempotency_key'] = uniqid();
									$data['object']          = (object) array(
										'type'    => 'MODIFIER_LIST',
										'id'      => $modifier_name[1],
										'version' => $mod_object['version'],
										'modifier_list_data' => (object) array(
											'name'      => $modifier_checker->modifier_set_name,
											'selection_type' => $selected_type,
											'modifiers' => $dynamic_arr,

										),

									);

								}
							}
						}

						$data_json = wp_json_encode( $data );
						$url       = $this->square->get_square_v2_url() . 'catalog/object';
						$result    = wp_remote_post(
							$url,
							array(
								'method'      => 'POST',
								'headers'     => array(
									'Authorization'  => 'Bearer ' . $this->square->get_access_token(),
									'Content-Type'   => 'application/json',
									'Content-Length' => strlen( $data_json ),
								),
								'httpversion' => '1.0',
								'sslverify'   => true,
								'body'        => $data_json,
							)
						);

			if ( '200' === $result['response']['code'] && 'OK' === $result['response']['message'] ) {

				$result = json_decode( $result['body'], true );

				if ( 'MODIFIER_LIST' === $result['catalog_object']['type'] ) {

					foreach ( $result['catalog_object']['modifier_list_data'] as $keyy => $modifier ) {

						$modifier_name = $result['catalog_object']['modifier_list_data']['name'];
						$mod_id        = $result['catalog_object']['id'];
						global $wpdb;
						$get_row     = 'get_row';
						$modifier_id = $wpdb->$get_row( 'SELECT * FROM ' . $wpdb->prefix . "woosquare_modifier WHERE  modifier_set_unique_id = '$mod_id'" );

						if ( ! empty( $modifier_id->modifier_id ) && ! empty( $modifier_id->modifier_set_name ) && ! empty( $modifier_id->modifier_set_unique_id ) && ! empty( $modifier_id->modifier_version ) ) {

							$mod_version = $result['catalog_object']['version'];
							$wpdb->query( // phpcs:ignore
								$wpdb->prepare(
									"UPDATE {$wpdb->prefix}woosquare_modifier SET modifier_version = %s WHERE modifier_id = %d",
									$mod_version,
									$modifier_id->modifier_id
								)
							);
							session_start();
							$_SESSION['modifier_id']   = $modifier_id->modifier_id;
							$_SESSION['modifier_slug'] = $modifier_id->modifier_slug;

						}

						foreach ( $modifier as $mod ) {

							global $wpdb;
							if ( ! empty( $_SESSION['modifier_slug'] ) && ! empty( $_SESSION['modifier_id'] ) ) {
								$session_modifier_slug = sanitize_text_field( wp_unslash( $_SESSION['modifier_slug'] ) );
								$session_modifier_id   = sanitize_text_field( wp_unslash( $_SESSION['modifier_id'] ) );
								$texonomy              = 'pm_' . strtolower( str_replace( ' ', '-', $session_modifier_slug ) ) . '_' . ( $session_modifier_id );
								$get_result            = 'get_results';
								$term_query            = $wpdb->$get_result( ( 'SELECT * FROM ' . $wpdb->prefix . "term_taxonomy WHERE taxonomy = '$texonomy'" ) );
								foreach ( $term_query as $kgs => $term ) {
									if ( empty( $term->description ) ) {
										$midd = $result['catalog_object']['modifier_list_data']['modifiers'][ $kgs ]['id'];
										$wpdb->query( // phpcs:ignore
											$wpdb->prepare(
												"UPDATE {$wpdb->prefix}term_taxonomy SET description = %s WHERE term_id = %d",
												$midd,
												$term->term_id
											)
										);
									}
									update_term_meta( $term->term_id, 'term_meta_version', sanitize_text_field( $mod['version'] ) );

								}
							}
						}
					}
				}

				if ( ! empty( $_SESSION['productid'] ) ) {
					$session_productid = sanitize_text_field( wp_unslash( $_SESSION['productid'] ) );
					$square_id         = get_post_meta( $session_productid, 'square_id', true );

					$modifier_value = get_post_meta( $session_productid, 'product_modifier_group_name', true );

					if ( ! empty( $modifier_value ) ) {
						$kkey      = 0;
						$mod_array = array();
						foreach ( $modifier_value as $keyy => $mod ) {

							$mod = ( explode( '_', $mod ) );

							if ( ! empty( $mod ) ) {
								global $wpdb;
								$get_var            = 'get_var';
								$rcount             = $wpdb->$get_var( 'SELECT modifier_set_unique_id FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_id = '$mod[2]' " );
								$mod_array[ $keyy ] = $rcount;

							}
						}

						$data = array(
							'item_ids'                 => array(
								$square_id,
							),
							'modifier_lists_to_enable' => $mod_array,
						);

						$data_json = wp_json_encode( $data );
						$url       = $this->square->get_square_v2_url() . 'catalog/update-item-modifier-lists';
						$result    = wp_remote_post(
							$url,
							array(
								'method'      => 'POST',
								'headers'     => array(
									'Authorization'  => 'Bearer ' . $this->square->get_access_token(),
									'Content-Type'   => 'application/json',
									'Content-Length' => strlen( $data_json ),
								),
								'httpversion' => '1.0',
								'sslverify'   => true,
								'body'        => $data_json,
							)
						);

						if ( '200' === $result['response']['code'] && 'OK' === $result['response']['message'] ) {
							update_post_meta( $session_productid, 'product_sync_square_id' . $session_productid, $mod[2] );
						}
					}
				}
			}
		}
	}

	/**
	 * Adds a new Square category.
	 *
	 * This function takes a WooCommerce category as a parameter and creates a new Square category with the information from the WooCommerce category.
	 *
	 * @param object $category The WooCommerce category.
	 *
	 * @return bool True if the category was successfully added, false otherwise.
	 */
	public function add_category( $category ) {
		$cat_json = ( array(
			'idempotency_key' => uniqid(),
			'object'          => array(
				'id'            => '#' . $category->name,
				'type'          => 'CATEGORY',
				'category_data' => array(
					'name' => $category->name,
				),
			),
		)
		);

		$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/object';

		$method  = 'POST';
		$square  = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
		$headers = array(
			'Authorization' => 'Bearer ' . $this->square->get_access_token(), // Use verbose mode in cURL to determine the format you want for this header.
			'cache-control' => 'no-cache',
			'Content-Type'  => 'application/json',
		);

		$response = array();
		$response = $square->wp_remote_woosquare( $url, $cat_json, $method, $headers, $response );

		$object_add_category = json_decode( $response['body'], true );

		if ( ! empty( $object_add_category['catalog_object'] ) ) {
			update_option( 'category_square_id_' . $category->term_id, $object_add_category['catalog_object']['id'] );
			update_option( 'category_square_version_' . $category->term_id, $object_add_category['catalog_object']['version'] );

		}
		if ( 200 === $response['response']['code'] ) {
			$dddd = array(
				'id'         => $category->term_id,
				'item'       => 'category',
				'status'     => true,
				'pro_status' => 'add',
				'message'    => __( 'Successfully sync', 'woosquare' ),
			);
		} else {
			$dddd = array(
				'id'         => $category->term_id,
				'item'       => 'category',
				'status'     => false,
				'pro_status' => 'failed',
				'message'    => $object_add_category,
			);
		}
		return $dddd;
	}

	/**
	 * Edits a Square category.
	 *
	 * This function takes a WooCommerce category and a Square category ID as parameters and updates the Square category with the information from the WooCommerce category.
	 *
	 * @param object $category The WooCommerce category.
	 * @param int    $category_square_id The Square category ID.
	 *
	 * @return bool True if the category was successfully updated, false otherwise.
	 */
	public function edit_category( $category, $category_square_id ) {

		$category_square_version_ = get_option( 'category_square_version_' . $category->term_id );

		$cat_json = ( array(
			'idempotency_key' => uniqid(),
			'object'          => array(
				'id'            => $category_square_id,
				'version'       => (int) $category_square_version_,
				'type'          => 'CATEGORY',
				'category_data' => array(
					'name' => $category->name,
				),
			),
		) );
		$square   = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );

		$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/object';

		$headers = array(
			'Authorization' => 'Bearer ' . $this->square->get_access_token(), // Use verbose mode in cURL to determine the format you want for this header.
			'Content-Type'  => 'application/json',
		);

		$method = 'POST';

		$response = array();
		$response = $square->wp_remote_woosquare( $url, $cat_json, $method, $headers, $response );

		$object_edit_category = json_decode( $response['body'], true );

		$resultobj = $object_edit_category['catalog_object'];

		if ( ! empty( $resultobj['id'] ) ) {
			update_option( 'category_square_id_' . $category->term_id, $resultobj['id'] );
			update_option( 'category_square_version_' . $category->term_id, $resultobj['version'] );
		}
		if ( 200 === $response['response']['code'] ) {
			$dddd = array(
				'id'         => $category->term_id,
				'item'       => 'category',
				'status'     => true,
				'pro_status' => 'update',
				'message'    => __( 'Successfully sync', 'woosquare' ),
			);
		} else {
			$dddd = array(
				'id'         => $category->term_id,
				'item'       => 'category',
				'status'     => false,
				'pro_status' => 'failed',
				'message'    => $object_edit_category,
			);
		}
		return $dddd;
	}

	/**
	 * Deletes a category from Square.
	 *
	 * This function takes the category's Square ID as a parameter.
	 * It sends a DELETE request to the Square API using the wp_remote_woosquare() method.
	 * If the category is deleted successfully, it returns true. Otherwise, it returns an array of errors.
	 *
	 * @param string $category_square_id The category's Square ID.
	 *
	 * @return bool|array True if the category is deleted successfully, an array of errors otherwise.
	 */
	public function delete_category( $category_square_id ) {

		$square          = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
		$url             = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/object/' . $category_square_id;
		$method          = 'DELETE';
		$headers         = array(
			'Authorization' => 'Bearer ' . $this->square->get_access_token(),
		);
		$args            = array();
		$response        = array();
		$response        = $square->wp_remote_woosquare( $url, $args, $method, $headers, $response );
		$object_response = json_decode( $response['body'], true );
		if ( 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {
			return true;
		} else {
			return $object_response;
		}
	}

	/**
	 * Creates a new product in Square or updates an existing one.
	 *
	 * This function takes a WooCommerce product object and a Square product ID (if updating) as parameters.
	 * It retrieves the product details, constructs a JSON request containing the details, and sends it to the Square API using the wp_remote_woosquare() method.
	 * If the product is created or updated successfully, it returns the product's Square ID. Otherwise, it returns an array of errors.
	 *
	 * @param WP_Post     $product The WooCommerce product object.
	 * @param string|null $product_square_id The Square product ID (if updating).
	 *
	 * @return string|array The product's Square ID or an array of errors.
	 */
	public function add_product( $product, $product_square_id ) {

		$data                   = array();
		$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		$categories             = get_the_terms( $product, 'product_cat' );
		if ( ! $categories ) {
			$categories = array();
		}
		$category_square_id         = null;
		$woocommerce_currency       = get_option( 'woocommerce_currency' );
		$square                     = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );
		$square_to_woo_synchronizer = new SquareToWooSynchronizer( $square );
		$square_categories          = $square_to_woo_synchronizer->get_square_categories();
		// need to take version.
		$squcats = array();
		if ( ! empty( $square_categories ) ) {
			foreach ( $square_categories as $square_category ) {
				$squcats[] = $square_category->id;
			}
		}

		foreach ( $categories as $category ) {
			// check if category not added to Square .. then will add this category.
			$cat_square_id = get_option( 'category_square_id_' . $category->term_id );

			if ( ! $cat_square_id || ! in_array( $cat_square_id, $squcats, true ) ) {
				$category_square_id = $this->add_category( $category );
				$cat_square_id      = get_option( 'category_square_id_' . $category->term_id );
				$_SESSION['woo_product_sync_log'][ $category->term_id ][ $category_square_id['pro_status'] ] = $category_square_id;
			}

			$category_square_id = $cat_square_id;
		}
		$product_details = get_post_meta( $product->ID );

		if ( $product_square_id ) {
			$data['id'] = $product_square_id->id;
		}
		$data['name'] = $product->post_title;
		if ( get_option( 'html_sync_des' ) === '1' ) {
			$data['description'] = $product->post_content;
		} else {
			$data['description'] = wp_strip_all_tags( $product->post_content );
		}
		$data['category_id'] = $category_square_id;
		$data['visibility']  = ( 'publish' === $product->post_status ) ? 'PUBLIC' : 'PRIVATE';

		// check if there are attributes.

		$_product    = wc_get_product( $product->ID );
		$unserialize = 'unserialize';
		if ( $_product->is_type( 'variable' ) ) {   // Variable Product.
			$product_variations = $unserialize( $product_details['_product_attributes'][0] );

			foreach ( $product_variations as $product_variation ) {
				// check if there are variations with fees.
				if ( $product_variation['is_variation'] ) {

					$args           = array(
						'post_parent' => $product->ID,
						'post_type'   => 'product_variation',
					);
					$child_products = get_children( $args );

					$admin_msg = false;
					foreach ( $child_products as $child_product ) {
						$child_product_meta = get_post_meta( $child_product->ID );

						$variation_name = $child_product_meta[ 'attribute_' . strtolower( $product_variation['name'] ) ][0];
						if ( empty( $child_product_meta['_sku'][0] ) ) {
							// admin msg that variation sku empty not sync in sqaure.
							$admin_msg = true;
						}
						if ( empty( $child_product_meta['_sku'][0] ) ) {
							// don't add product variaton that doesn't have SKU.
							continue;
						}
						$data['variations'][ $child_product_meta['_sku'][0] ][] = array(
							'name'            => $product_variation['name'] . '[' . $variation_name . ']',
							'sku'             => $child_product_meta['_sku'][0],
							'track_inventory' => ( 'yes' === $child_product_meta['_manage_stock'][0] ) ? true : false,
							'price_money'     => array(
								'currency_code' => $woocommerce_currency,
								'amount'        => $square->format_amount( $child_product_meta['_price'][0], 'wotosq', $woocommerce_currency ),
							),
						);
					}
					if ( $admin_msg ) {
						update_post_meta( $product->ID, 'admin_notice_square', 'Product unable to sync to Square due to Sku missing ' );
					} else {
						delete_post_meta( $product->ID, 'admin_notice_square', 'Product unable to sync to Square due to Sku missing ' );
					}
				} else {

					$data['variations'][] = array(
						'name'            => 'Regular',
						'sku'             => $product_details['_sku'][0],
						'track_inventory' => ( 'yes' === $product_details['_manage_stock'][0] ) ? true : false,
						'price_money'     => array(
							'currency_code' => $woocommerce_currency,
							'amount'        => $square->format_amount( $product_details['_price'][0], 'wotosq', $woocommerce_currency ),
						),
					);
				}
			}
			// [color:red,size:smal] sample than below for multiple attributes and variations
			// color[black],size[smal] sample
			$setvariationformultupleattr = $data['variations'];
			foreach ( $setvariationformultupleattr as $mult_attr ) {
				$getingattrname = '';
				foreach ( $mult_attr as $attr ) {
					$getingattrnamedata = explode( '[', $attr['name'] );
					$getingattrval      = explode( ']', $getingattrnamedata[1] );
					$getingattrname    .= str_replace( 'pa_', '', $getingattrnamedata[0] ) . '[' . $getingattrval[0] . '],';
				}
				$getingattrname   = rtrim( $getingattrname, ',' );
				$datavariations[] = array(
					'name'            => $getingattrname,
					'sku'             => $attr['sku'],
					'track_inventory' => $attr['track_inventory'],
					'price_money'     => array(
						'currency_code' => $woocommerce_currency,
						'amount'        => $attr['price_money']['amount'],
					),
				);
			}
			$data['variations'] = array();
			$data['variations'] = $datavariations;
		} elseif ( $_product->is_type( 'simple' ) ) {   // Simple Product.

			if ( empty( $product_details['_sku'][0] ) ) {
				update_post_meta( $product->ID, 'admin_notice_square', 'Product unable to sync to Square due to Sku missing ' );
				// don't add product that doesn't have SKU.
				$result = array(
					'id'         => $product->ID,
					'status'     => false,
					'pro_status' => 'failed',
					'message'    => __( 'Product unable to sync to Square due to Sku missing', 'woosquare' ),
				);
				return $result;
			} else {
				delete_post_meta( $product->ID, 'admin_notice_square', 'Product unable to sync to Square due to Sku missing ' );
			}
			// check if there are attributes.
			if ( ! empty( $product_details['_product_attributes'] ) ) {

				$product_variations = $unserialize( $product_details['_product_attributes'][0] );

				if ( ! empty( $product_variations ) ) {
					foreach ( $product_variations as $variations ) {
						$variat = explode( '_', $variations['name'] );
						if ( 'pa' === $variat[0] ) {
							$variatio = ( wc_get_product_terms( $product->ID, $variations['name'], array( 'fields' => 'names' ) ) );
							$pa      .= isset( $variat[1], $variatio ) ? $variat[1] . '[' . implode( '|', $variatio ) . '],' : '';

						} else {
							$pa .= ( isset( $variations['name'], $variations['value'] ) ) ? $variations['name'] . '[' . $variations['value'] . '],' : '';

						}
					}
					$pa = rtrim( $pa, ',' );
				} else {
					$pa = 'Regular';
				}

				$data['variations'][] = array(
					'name'            => $pa,
					'sku'             => $product_details['_sku'][0],
					'track_inventory' => ( 'yes' === $product_details['_manage_stock'][0] ) ? true : false,
					'price_money'     => array(
						'currency_code' => $woocommerce_currency,
						'amount'        => $square->format_amount( $product_details['_price'][0], 'wotosq', $woocommerce_currency ),
					),
				);
			} else {
				$pa                   = 'Regular';
				$data['variations'][] = array(
					'name'            => $pa,
					'sku'             => $product_details['_sku'][0],
					'track_inventory' => ( 'yes' === $product_details['_manage_stock'][0] ) ? true : false,
					'price_money'     => array(
						'currency_code' => $woocommerce_currency,
						'amount'        => $square->format_amount( $product_details['_price'][0], 'wotosq', $woocommerce_currency ),
					),
				);

			}
		}
		// Connect to Square to add this item.

		if ( function_exists( 'manage_stock_from_square_function' ) ) {

			$square  = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
			$url     = 'https://connect.' . WC_SQUARE_STAGING_URL . '.com/v1/' . $woo_square_location_id . '/inventory';
			$method  = 'GET';
			$headers = array(
				'Authorization' => 'Bearer ' . et_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), // Use verbose mode in cURL to determine the format you want for this header.
				'cache-control' => 'no-cache',
				'Content-Type'  => 'application/json',
			);

			$response     = array();
				$response = $square->wp_remote_woosquare( $url, $card_details, $method, $headers, $response );

			if ( 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {
				$all_square_variation = json_decode( $response['body'], true );
			}
		}
		$sync_on_add_edit          = get_option( 'sync_on_add_edit' );
		$woosquare_pro_edit_fields = get_option( 'woosquare_pro_edit_fields' );
		$update_inventory          = true;
		$upload_image              = true;

		if ( $product_square_id ) {
			$exist_in_square = $product_square_id;

			if ( ! empty( $exist_in_square->id ) ) {
				if ( ! empty( $exist_in_square->variations ) && ! empty( $data['variations'] ) ) {
					foreach ( $exist_in_square->variations as $variation_upd ) {

						foreach ( $data['variations'] as $variation_data ) {
							if ( $variation_upd->sku === $variation_data['sku'] ) {

								$variation_ids[ $variation_upd->sku ] = $variation_upd->id;
								if ( empty( $_SESSION ) ) {
									if ( 1 === $sync_on_add_edit ) {
										if ( ! in_array( 'price', $woosquare_pro_edit_fields, true ) ) {
											unset( $variation_data['price_money'] );
										}
									}
								}
							}
						}
					}
				}
				$request  = 'PUT';
				$item_id  = $product_square_id->id;
				$prod_cri = 'update';
			} else {
				$request  = 'POST';
				$item_id  = '#' . $data['name'];
				$prod_cri = 'add';
			}
		} else {
			$request  = 'POST';
			$item_id  = '#' . $data['name'];
			$prod_cri = 'add';
		}

		if ( empty( $_SESSION ) ) {
			if ( 1 === $sync_on_add_edit ) {
				$woosquare_pro_edit_fields = get_option( 'woosquare_pro_edit_fields' );

				if ( is_array( $woosquare_pro_edit_fields ) && ! in_array( 'title', $woosquare_pro_edit_fields, true ) ) {
					unset( $data['name'] );
					if ( ! empty( $product_square_id->name ) ) {
						$data['name'] = $product_square_id->name;
					}
				}
				if ( is_array( $woosquare_pro_edit_fields ) && ! in_array( 'description', $woosquare_pro_edit_fields, true ) ) {
					unset( $data['description'] );
				}
				if ( is_array( $woosquare_pro_edit_fields ) && ! in_array( 'price', $woosquare_pro_edit_fields, true ) ) {
					unset( $data['variations'][0]['price_money'] );
				}
				if ( is_array( $woosquare_pro_edit_fields ) && ! in_array( 'stock', $woosquare_pro_edit_fields, true ) ) {
					$update_inventory = false;
				}
				if ( is_array( $woosquare_pro_edit_fields ) && ! in_array( 'category', $woosquare_pro_edit_fields, true ) ) {
					unset( $data['category_id'] );
				}
				if ( is_array( $woosquare_pro_edit_fields ) && ! in_array( 'pro_image', $woosquare_pro_edit_fields, true ) ) {
					$upload_image = false;
				}
			}
		}

		$data_json = array();

		$forversion = get_post_meta( $product->ID, 'log_woosquare_update_items_response', true );

		$data_json['idempotency_key'] = uniqid();
		$data_json['object']['type']  = 'ITEM';

		$data_json['object']['id']       = $item_id;
		$data_json['object']['image_id'] = '';

		if ( ! empty( $exist_in_square->version ) ) {
			$data_json['object']['version'] = (int) $exist_in_square->version;
		}
		$data_json['object']['item_data']['name']         = $data['name'];
		$data_json['object']['item_data']['product_type'] = 'REGULAR';
		$data_json['object']['item_data']['description']  = $data['description'];
		$data_json['object']['item_data']['visibility']   = $data['visibility'];
		$data_json['object']['item_data']['category_id']  = $data['category_id'];

		foreach ( $data['variations'] as $key => $variant ) {
			$data_json['object']['item_data']['variations'][ $key ]['type'] = 'ITEM_VARIATION';

			if ( ! empty( $variation_ids[ $variant['sku'] ] ) ) {
				$data_json['object']['item_data']['variations'][ $key ]['id'] = $variation_ids[ $variant['sku'] ];
			} else {
				$data_json['object']['item_data']['variations'][ $key ]['id'] = '#' . $variant['sku'];
			}

			if ( ! empty( $exist_in_square->variations ) ) {
				foreach ( $exist_in_square->variations as $variatversion ) {
					if ( $variatversion->id === $variation_ids[ $variant['sku'] ] ) {
						$data_json['object']['item_data']['variations'][ $key ]['version'] = (int) $variatversion->version;

					}
				}
			}
			$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['name']                    = $variant['name'];
			$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['sku']                     = $variant['sku'];
			$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['track_inventory']         = $variant['track_inventory'];
			$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['price_money']['amount']   = (int) $variant['price_money']['amount'];
			$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['pricing_type']            = 'FIXED_PRICING';
			$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['price_money']['currency'] = $variant['price_money']['currency_code'];
		}

		$data_json = ( $data_json );

		$url = 'https://connect.' . WC_SQUARE_STAGING_URL . '.com/v2/catalog/object';

		$headers  = array(
			'Authorization' => 'Bearer ' . $this->square->get_access_token(),
			'Content-Type'  => 'application/json',
		);
		$method   = 'POST';
		$response = array();

		$response     = $square->wp_remote_woosquare( $url, $data_json, $method, $headers, $response );
		$responsesync = $response;

		update_post_meta( $product->ID, 'log_woosquare_update_items_request', $data );

		$object_response = json_decode( $response['body'], true );

		if ( 200 !== $response['response']['code'] && 'OK' !== $response['response']['message'] ) {
			// some kind of an error happened.

			update_post_meta( $product->ID, 'log_woosquare_update_items_response_error', $object_response );
			$message = '';
			foreach ( $object_response['errors'] as $error ) {
					$message .= $error['detail'] . ' - ' . str_replace( '_', ' ', $error['field'] );
			}
			$dddd = array(
				'id'         => $product->ID,
				'status'     => false,
				'pro_status' => 'failed',
				'message'    => $message,
			);
			return $dddd;
		} else {

			if ( 200 === $response['response']['code'] ) {
				update_post_meta( $product->ID, 'log_woosquare_update_items_response', $object_response );
			}

			$response = $object_response['catalog_object'];

			// Update product id with square id.
			if ( isset( $response['id'] ) ) {
				update_post_meta( $product->ID, 'square_id', $response['id'] );
				do_action( 'manage_stock_from_square', $response['item_data']['variations'], $product->ID, isset( $all_square_variation ) ? $all_square_variation : null );
				if ( 'PUT' === $request ) {
					$square       = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );
					$synchronizer = new SquareToWooSynchronizer( $square );

					$square_inventory = $synchronizer->get_square_inventory( $response['item_data']['variations'] );
					$inventorycount   = count( $response['item_data']['variations'] );
				}

				// Update product variations ids with square ids.
				if ( isset( $child_products ) ) {

					foreach ( $child_products as $child_product ) {
						$cn = 1;
						foreach ( $response['item_data']['variations'] as $variation ) {
							$d                       = new DateTime();
							$variation['updated_at'] = $d->format( 'Y-m-d\TH:i:s' ) . '.000Z';
							$child_product_meta      = get_post_meta( $child_product->ID );

							$variation_sku = $child_product_meta['_sku'][0];
							if ( $variation['item_variation_data']['sku'] === $variation_sku ) {
								update_post_meta( $child_product->ID, 'variation_square_id', $variation['id'] );

								if ( $update_inventory ) {
									if ( 'yes' === $child_product_meta['_manage_stock'][0] ) {

										if ( ! empty( $square_inventory->counts ) && 'PUT' === $request ) {
											foreach ( $square_inventory->counts as $varid ) {

												if ( $varid->catalog_object_id === $variation['id'] ) {

													if ( $varid->quantity < $child_product_meta['_stock'][0] ) {
														$stock   = $child_product_meta['_stock'][0] - $varid->quantity;
														$adjtype = 'RECEIVE_STOCK';

														$this->update_inventory( $variation, $stock, $woo_square_location_id, $adjtype );
													} elseif ( $varid->quantity > $child_product_meta['_stock'][0] ) {
														$adjtype = 'SALE';
														$stock   = $varid->quantity - $child_product_meta['_stock'][0];

														$this->update_inventory( $variation, $stock, $woo_square_location_id, $adjtype );
													}
													$matched_variants[] = $varid->catalog_object_id;
												} else {
													$miss_matched_variants[]                               = $variation['id'];
													$miss_matched_variantions[ $variation['id'] ]          = $variation;
													$miss_matched_variantions[ $variation['id'] ]['stock'] = $child_product_meta['_stock'][0];
												}
											}

											if ( $inventorycount > count( $square_inventory->counts ) && $inventorycount === $cn ) {
												$newly_variants = array_unique( array_diff( $miss_matched_variants, $matched_variants ) );

												foreach ( $newly_variants as $newvariat ) {
													$this->update_inventory( $miss_matched_variantions[ $newvariat ], $miss_matched_variantions[ $newvariat ]['stock'], 'RECEIVE_STOCK', $woo_square_location_id, 'RECEIVE_STOCK' );
												}
											}
										} else {
											// for first time update stock.

											$this->update_inventory( $variation, $child_product_meta['_stock'][0], $woo_square_location_id, 'RECEIVE_STOCK' );
										}
									}
								}
							}
							++$cn;
						}
					}
				} else {
					// update simple product.

					foreach ( $response['item_data']['variations'] as $variation ) {

						$d                       = new DateTime();
						$variation['updated_at'] = $d->format( 'Y-m-d\TH:i:s' ) . '.000Z';
						update_post_meta( $product->ID, 'variation_square_id', $variation['id'] );
						$product_details = get_post_meta( $product->ID );
						$product_obj     = wc_get_product( $product->ID );
						$product_stock   = $product_obj->get_stock_quantity();

						if ( $update_inventory ) {
							if ( 'yes' === $product_details['_manage_stock'][0] ) {

								if ( ! empty( $square_inventory->counts ) && 'PUT' === $request ) {
										$varid = $square_inventory->counts;

									if ( $varid[0]->catalog_object_id === $variation['id'] ) {
										if ( $varid[0]->quantity < $product_stock ) {
											$stock   = $product_stock - $varid[0]->quantity;
											$adjtype = 'RECEIVE_STOCK';
											$this->update_inventory( $variation, $stock, $woo_square_location_id, $adjtype );
										} elseif ( $varid[0]->quantity > $product_stock ) {
											$adjtype = 'SALE';
											$stock   = $varid[0]->quantity - $product_stock;
											$this->update_inventory( $variation, $stock, $woo_square_location_id, $adjtype );
										}
									}
								} else {
									$adjtype = 'RECEIVE_STOCK';

									$this->update_inventory( $variation, $product_stock, $woo_square_location_id, $adjtype );
								}
							}
						}
					}
				}

				if ( $upload_image ) {
					if ( has_post_thumbnail( $product->ID ) ) {
						$product_square_id = $response['id'];
						$image_file        = get_attached_file( get_post_thumbnail_id( $product->ID ) );

						$result = $this->upload_image( $product_square_id, $image_file, $product->ID );
						// make the response equal image response to be logged in error.
						// message field.
						if ( true !== $result ) {
							400 === $http_status;
							$response = $result;
						}
					}
				}
			}
			$dddd = array(
				'id'         => $product->ID,
				'status'     => true,
				'pro_status' => $prod_cri,
				'message'    => __( 'Successfully sync', 'woosquare' ),
			);
			return ( 200 === $responsesync['response']['code'] ) ? $dddd : $responsesync;
		}
	}

	/**
	 * Updates a product variation in Square.
	 *
	 * This function takes the product's item ID, variation ID, and an array of post fields as parameters.
	 * It constructs a JSON request containing the post fields and sends it to the Square API using the wp_remote_woosquare() method.
	 * If the update is successful, it returns true. Otherwise, it returns an array of errors.
	 *
	 * @param string $item_id The product's item ID.
	 * @param string $variation_id The variation ID.
	 * @param array  $post_fields The post fields to update.
	 *
	 * @return bool|array True if the update is successful, an array of errors otherwise.
	 */
	public function update_variation( $item_id, $variation_id, $post_fields ) {

		$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
		$url    = $this->square->get_square_url() . '/items/' . $item_id . '/variations/' . $variation_id;

		$method = 'PUT';

		$headers = array(
			'Authorization' => 'Bearer ' . $this->square->get_access_token(), // Use verbose mode in cURL to determine the format you want for this header.
			'cache-control' => 'no-cache',
			'Content-Type'  => 'application/json',
		);

		$response                = array();
		$response                = $square->wp_remote_woosquare( $url, $post_fields, $method, $headers, $response );
		$object_update_variation = json_decode( $response['body'], true );
		if ( 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {
			return true;
		} else {
			return $object_update_variation;
		}
	}

	/**
	 * Deletes a product from Square or retrieves its details.
	 *
	 * This function takes the product's Square ID and the request method ('GET' or 'DELETE') as parameters.
	 * It sends a request to the Square API using the wp_remote_woosquare() method.
	 * If the request is successful, it returns the product details for 'GET' requests or true for 'DELETE' requests.
	 * Otherwise, it returns an array of errors.
	 *
	 * @param string $product_square_id The product's Square ID.
	 * @param string $req The request method ('GET' or 'DELETE').
	 *
	 * @return mixed The product details for 'GET' requests, true for 'DELETE' requests, or an array of errors.
	 */
	public function delete_product_or_get( $product_square_id, $req ) {

		$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );

		$url = 'https://connect.' . WC_SQUARE_STAGING_URL . '.com/v2/catalog/object/' . $product_square_id;

		$method = $req;

		$headers  = array(
			'Authorization' => 'Bearer ' . $this->square->get_access_token(), // Use verbose mode in cURL to determine the format you want for this header.
		);
		$response = array();
		$args     = array();
		$response = $square->wp_remote_woosquare( $url, $args, $method, $headers, $response );

		$object_delete_product_or_get = json_decode( $response['body'], true );

		if ( 'GET' === $req ) {
			return $object_delete_product_or_get;
		} else {
			return ( 200 === $response['response']['code'] ) ? true : $object_delete_product_or_get;
		}
	}

	/**
	 * Uploads an image to Square and associates it with a product.
	 *
	 * This function takes the product's Square ID, image file, and product's WooCommerce ID as parameters.
	 * It constructs a multipart form-data request containing the image data and sends it to the Square API using the wp_remote_post() method.
	 * If the upload is successful, it updates the product's WooCommerce meta data with the Square image ID.
	 *
	 * @param string $product_square_id The product's Square ID.
	 * @param string $image_file The image file path.
	 * @param int    $product_woo_id The product's WooCommerce ID.
	 *
	 * @return bool|array True if the upload is successful, an array of errors otherwise.
	 */
	public function upload_image( $product_square_id, $image_file, $product_woo_id ) {

		$get_content = 'file_get_contents';
		$image       = $get_content( $image_file );

		$headers = array(
			'accept'         => 'application/json',
			'content-type'   => 'multipart/form-data; boundary="boundary"',
			'Square-Version' => '2019-05-08',
			'Authorization'  => 'Bearer ' . $this->square->get_access_token(),
		);

		$body  = '--boundary' . "\r\n";
		$body .= 'Content-Disposition: form-data; name="request"' . "\r\n";
		$body .= 'Content-Type: application/json' . "\r\n\r\n";

		$request = array(
			'idempotency_key' => uniqid(),
			'image'           => array(
				'type'       => 'IMAGE',
				'id'         => '#TEMP_ID',
				'image_data' => array(
					'caption' => '',
				),
			),
		);
		if ( $product_square_id ) {
			$request['object_id'] = $product_square_id;
		}
		$body     .= wp_json_encode( $request );
		$body     .= "\r\n";
		$body     .= '--boundary' . "\r\n";
		$body     .= 'Content-Disposition: form-data; name="file"; filename="' . esc_attr( basename( $image_path ) ) . '"' . "\r\n";
		$body     .= 'Content-Type: image/jpeg' . "\r\n\r\n";
		$body     .= $image . "\r\n";
		$body     .= '--boundary--';
		$url       = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/images';
		$responses = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => $body,
			)
		);
		$response  = json_decode( $responses['body'], true );

		if ( isset( $response['image']['id'] ) ) {
			update_post_meta( $product_woo_id, 'square_master_img_id', $response['image']['id'] );
		}
		return 200 === $responses['response']['code'] ? true : $response;
	}

	/**
	 * Check if a product SKU exists in Square items.
	 *
	 * This function checks if a product SKU exists in a list of Square items.
	 *
	 * @param WP_Post $woocommerce_product The WooCommerce product to check.
	 * @param array   $square_items        An array of Square items where keys are SKUs and values are item IDs.
	 *
	 * @return false|string|null If the SKU is found in the Square items, returns the corresponding item ID. Otherwise, returns false.
	 */
	public function check_sku_in_square( $woocommerce_product, $square_items ) {
		/* get all products from woocommerce */
		$posts_per_page = 999999;
		$args           = array(
			'post_type'      => 'product_variation',
			'post_parent'    => $woocommerce_product->ID,
			'posts_per_page' => $posts_per_page,
		);
		$child_products = get_posts( $args );

		if ( $child_products ) { // variable.
			foreach ( $child_products as $product ) {
				$sku = get_post_meta( $product->ID, '_sku', true );
				if ( $sku ) {
					if ( isset( $square_items[ $sku ] ) ) {
						// value is the item id.
						return $square_items[ $sku ];

					}
				}
			}
			return false;
		} else { // simple.
			$sku = get_post_meta( $woocommerce_product->ID, '_sku', true );

			if ( ! $sku ) {
				return false;
			}

			if ( isset( $square_items[ $sku ] ) ) {
				// value is the item id.
				return $square_items[ $sku ];

			}
			return false;
		}
	}

	/**
	 * Updates the inventory for a product variation using the Square API.
	 *
	 * This function takes a product variation ID, stock quantity, Square location ID, and adjustment type as parameters.
	 * It constructs a data string containing the adjustment details and sends it to the Square API using the wp_remote_woosquare() method.
	 * The function returns the response from the Square API.
	 *
	 * @param int    $variations The product variation ID.
	 * @param int    $stock The stock quantity.
	 * @param string $woo_square_location_id The Square location ID.
	 * @param string $adjustment_type The adjustment type (RECEIVE_STOCK or SALE).
	 *
	 * @return array The response from the Square API.
	 */
	public function update_inventory( $variations, $stock, $woo_square_location_id, $adjustment_type = 'RECEIVE_STOCK' ) {

		$data_string = array(
			'idempotency_key' => uniqid(),
			'changes'         =>
			array(
				0 =>
				array(
					'adjustment' =>
					array(
						'catalog_object_id' => $variations['id'],
						'quantity'          => (string) $stock,
						'location_id'       => $woo_square_location_id,
						'occurred_at'       => $variations['updated_at'],
					),
					'type'       => 'ADJUSTMENT',
				),
			),
		);
		if ( 'RECEIVE_STOCK' === $adjustment_type ) {
			$data_string['changes'][0]['adjustment']['from_state'] = 'NONE';
			$data_string['changes'][0]['adjustment']['to_state']   = 'IN_STOCK';
		} elseif ( 'SALE' === $adjustment_type ) {
			$data_string['changes'][0]['adjustment']['from_state'] = 'IN_STOCK';
			$data_string['changes'][0]['adjustment']['to_state']   = 'SOLD';
		}

		$data_string = ( $data_string );
		$square      = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
		$method      = 'POST';
		$url         = 'https://connect.' . WC_SQUARE_STAGING_URL . '.com/v2/inventory/batch-change';
		$headers     = array(
			'Authorization' => 'Bearer ' . $this->square->get_access_token(), // Use verbose mode in cURL to determine the format you want for this header.
			'Content-Type'  => 'application/json',
		);
		$response    = array();
		$response    = $square->wp_remote_woosquare( $url, $data_string, $method, $headers, $response );

		return $response;
	}


	/**
	 * Get unsynchronized categories having is_square_sync flag = 0 or
	 * doesn't have it
	 *
	 * @return object wpdb object having id and name and is_square_sync meta
	 *                value for each category
	 */
	public function get_unsynchronized_categories() {

		global $wpdb;

		// 1-get un-synchronized categories ( having is_square_sync = 0 or key not exists ).
		$query      = "
		SELECT tax.term_id AS term_id, term.name AS name, meta.option_value
		FROM {$wpdb->prefix}term_taxonomy as tax
		JOIN {$wpdb->prefix}terms as term ON (tax.term_id = term.term_id)
		LEFT JOIN {$wpdb->prefix}options AS meta ON (meta.option_name = concat('is_square_sync_',term.term_id))
		where tax.taxonomy = 'product_cat'
		AND ( (meta.option_value = '1') OR (meta.option_value is NULL) )
		GROUP BY tax.term_id";
		$get_result = 'get_results';
		return $wpdb->$get_result( $query, OBJECT );
	}

	/**
	 * Get square ids of the given categories if found
	 *
	 * @global object $wpdb
	 * @param object $categories wpdb categories object.
	 * @return array Associative array with key: category id, value: category square id
	 */
	public function get_categories_square_ids( $categories ) {

		if ( empty( $categories ) ) {
			return array();
		}
		global $wpdb;

		// get square ids.
		$option_keys = ' (';
		// get category ids and add category_square_id_ to it to form its key in.
		// the options table.
		foreach ( $categories as $category ) {
			$option_keys .= "'category_square_id_{$category->term_id}',";
		}

		$option_keys  = substr( $option_keys, 0, strlen( $option_keys ) - 1 );
		$option_keys .= ' ) ';

		$categories_square_ids_query = "
			SELECT option_name, option_value
			FROM {$wpdb->prefix}options 
			WHERE option_name in {$option_keys}";
		$get_result                  = 'get_results';
		$results                     = $wpdb->$get_result( $categories_square_ids_query, OBJECT );

		$square_categories = array();

		// item with square id.
		foreach ( $results as $row ) {

			// get id from string.
			preg_match( '#category_square_id_(\d+)#is', $row->option_name, $matches );
			if ( ! isset( $matches[1] ) ) {
				continue;
			}
			// add square id to array.
			$square_categories[ $matches[1] ] = $row->option_value;

		}
		return $square_categories;
	}


	/**
	 * Get the un-syncronized products which have is_square_sync = 0 or
	 * key not exists
	 *
	 * @global object $wpdb
	 * @return object wpdb object having id and name and is_square_sync meta
	 *                value for each product
	 */
	public function get_unsynchronized_products() {

		global  $wpdb;
		$query      = "
		SELECT *
		FROM {$wpdb->prefix}posts AS posts
		LEFT JOIN {$wpdb->prefix}postmeta AS meta ON (posts.ID = meta.post_id AND meta.meta_key = 'is_square_sync')
		where posts.post_type = 'product'
		AND posts.post_status = 'publish'
		AND ( (meta.meta_value = '0') OR (meta.meta_value = '1') OR (meta.meta_value is NULL) )
		GROUP BY posts.ID";
		$get_result = 'get_results';
		return $wpdb->$get_result( $query, OBJECT );
	}

	/**
	 * Get Square IDs for the given products and optionally return IDs of simple products with empty SKUs.
	 *
	 * @global object $wpdb
	 * @param array $products Wpdb products object.
	 * @param array $empty_sku_simple_products_ids Array to store IDs of simple products with empty SKUs.
	 * @return array Associative array with key: product ID, value: product Square ID.
	 */
	public function get_products_square_ids( $products, &$empty_sku_simple_products_ids = array() ) {

		if ( empty( $products ) ) {
			return array();
		}
		global $wpdb;

		// get square ids.
		$ids = ' ( ';
		// get post ids.
		foreach ( $products as $product ) {
			$ids .= $product->ID . ',';
		}

		$ids  = substr( $ids, 0, strlen( $ids ) - 1 );
		$ids .= ' ) ';

		$posts_square_ids_query = "
			SELECT post_id, meta_key, meta_value
			FROM {$wpdb->prefix}postmeta 
			WHERE post_id in {$ids}
			and meta_key in ('square_id', '_product_attributes','_sku')";
		$get_result             = 'get_results';
		$results                = $wpdb->$get_result( $posts_square_ids_query, OBJECT );
		$square_ids_array       = array();
		$empty_sku_array        = array();
		$empty_attributes_array = array();

		// exclude simple products (empty _product_attributes) that have an empty sku.
		foreach ( $results as $row ) {

			switch ( $row->meta_key ) {
				case '_sku':
					if ( empty( $row->meta_value ) ) {
						$empty_sku_array[] = $row->post_id;
					}
					break;

				case '_product_attributes':
					// check if empty attributes after unserialization.
					$unserialize = 'unserialize';
					$testvar     = $unserialize( $row->meta_value );
					if ( empty( $testvar ) ) {
						$empty_attributes_array[] = $row->post_id;
					}
					break;

				case 'square_id':
					// put all square_ids in asociative array with key= post_id.
					$square_ids_array[ $row->post_id ] = $row->meta_value;
					break;
			}
		}

		// get array of products having both empty sku and empty _product_variations.
		$empty_sku_simple_products_ids = array_intersect( $empty_attributes_array, $empty_sku_array );
		return $square_ids_array;
	}

	/**
	 * Get unsynchronized deleted categories and products from deleted data
	 * table
	 *
	 * @global object $wpdb
	 * @return object wpdb object
	 */
	public function get_unsynchronized_deleted_elements() {

		global $wpdb;
		$query        = 'SELECT * FROM ' . $wpdb->prefix . WOO_SQUARE_TABLE_DELETED_DATA;
		$get_result   = 'get_results';
		$deleted_elms = $wpdb->$get_result( $query, OBJECT );
		return $deleted_elms;
	}

	/**
	 * Simplify Square items object into an associative array.
	 *
	 * This function takes an array of Square items objects and simplifies it into
	 * an associative array where the key is the SKU ID, and the value is the item
	 * Square ID.
	 *
	 * @param array $square_items An array of Square items objects.
	 * @return array An associative array where the key is the SKU ID and the value is
	 *              the item Square ID.
	 */
	public function simplify_square_items_object( $square_items ) {

		$square_items_modified = array();
		foreach ( $square_items as $item ) {
			foreach ( $item->variations as $variation ) {
				if ( isset( $variation->sku ) ) {
					$square_items_modified[ $variation->sku ] = $item;
				}
			}
		}
		return $square_items_modified;
	}
}
