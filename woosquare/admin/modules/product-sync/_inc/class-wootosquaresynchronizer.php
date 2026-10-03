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
	 * Prepare image binary for Square catalog upload (JPEG, PNG, GIF only).
	 *
	 * @param string $image_file Absolute file path.
	 * @return array|false Array with keys body, mime, filename, label; false on failure.
	 */
	private function square_prepare_image_upload( $image_file ) {
		if ( empty( $image_file ) || ! is_readable( $image_file ) ) {
			return false;
		}

		$basename = basename( $image_file );
		$filetype = wp_check_filetype( $basename );
		$mime     = ! empty( $filetype['type'] ) ? $filetype['type'] : '';
		$label    = pathinfo( $basename, PATHINFO_FILENAME );

		$square_mimes = array(
			'image/jpeg',
			'image/png',
			'image/gif',
		);

		if ( in_array( $mime, $square_mimes, true ) ) {
			$get_content = 'file_get_contents';
			$body        = $get_content( $image_file );
			if ( empty( $body ) ) {
				return false;
			}

			return array(
				'body'     => $body,
				'mime'     => $mime,
				'filename' => $basename,
				'label'    => $label,
			);
		}

		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$editor = wp_get_image_editor( $image_file );
		if ( is_wp_error( $editor ) ) {
			return false;
		}

		$temp_file = wp_tempnam( $label . '.jpg' );
		if ( ! $temp_file ) {
			return false;
		}

		$saved = $editor->save( $temp_file, 'image/jpeg' );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! is_readable( $saved['path'] ) ) {
			wp_delete_file( $temp_file );
			return false;
		}

		$get_content = 'file_get_contents';
		$body        = $get_content( $saved['path'] );
		if ( file_exists( $saved['path'] ) ) {
			wp_delete_file( $saved['path'] );
		}

		if ( empty( $body ) ) {
			return false;
		}

		return array(
			'body'     => $body,
			'mime'     => 'image/jpeg',
			'filename' => $label . '.jpg',
			'label'    => $label,
		);
	}

	/**
	 * Syncs a WooCommerce product's payment reporting information with Square.
	 *
	 * This function checks if a product's SKU exists in Square. If it does not, the product is added to Square's catalog.
	 * If the SKU exists, the function returns the corresponding Square variation ID. Otherwise, it returns the response from the API.
	 *
	 * @param WC_Product $product The WooCommerce product object to sync.
	 *
	 * @return mixed The Square variation ID on success, or the API response on failure.
	 */
	public function payment_reporting_woo_to_square( $product ) {

		$check_sku = $product->get_sku();

		$check_type  = $product->get_type();
		$token       = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		$url         = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/search';
		$square      = new Square( $token, $location_id, WOOSQU_PLUS_APPID );

		$headers = array(
			'Authorization' => 'Bearer ' . $token, // Use verbose mode in cURL to determine the format you want for this header.
			'Content-Type'  => 'application/json;',
		);

		$method   = 'POST';
		$response = array();
		$args     = array(
			'object_types'            =>
			array(
				0 => 'ITEM',
				1 => 'ITEM_VARIATION',
			),
			'include_related_objects' => true,
			'include_deleted_objects' => false,
			'query'                   => array(
				'exact_query' => array(
					'attribute_name'  => 'sku',
					'attribute_value' => $check_sku,
				),
			),
		);

		$response = $square->wp_remote_woosquare_v2( $url, $args, $method, $headers, $response );

		$square_product = null;
		if ( is_array( $response ) && isset( $response['body'] ) ) {
			$square_product = json_decode( $response['body'], false );
		}

		if ( is_array( $response ) && ! empty( $response['response'] ) ) {
			if ( 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {

				if ( empty( $square_product[0]->id ) ) {

					$product_square_id = '';

					if ( $product->get_type() === 'variation' ) {
						$product_id = $product->get_parent_id();
					} else {
						$product_id = $product->get_id();
					}

					$args = array(
						'post_type'      => 'product',
						'posts_per_page' => -1,
						'include'        => $product_id,
					);

					$woocommerce_product = get_posts( $args );
					$_product            = wc_get_product( $woocommerce_product[0]->ID );

					$result = $this->add_product( $woocommerce_product[0], $product_square_id );
					if ( 200 === $result['response']['code'] && 'OK' === $result['response']['message'] ) {
						$result = json_decode( $result['body'], false );
						update_post_meta( $woocommerce_product[0]->ID, 'is_square_sync', 1 );

						return $result->catalog_object->item_data->variations[0]->id;

					} else {

						return $result;
					}
				} else {

					foreach ( $square_product[0]->item_data->variations as $varia ) {
						if ( $check_sku === $varia->item_variation_data->sku ) {

							return $varia->id;
						}
					}
				}
			} else {

				return $square_product;
			}
		} else {
			return $square_product;
		}
	}

	/**
	 * Automatic Sync All products, categories from Woo-Commerce to Square
	 */
	public function sync_from_woo_to_square() {

		$square                     = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
		$square_to_woo_synchronizer = new SquareToWooSynchronizer( $square );
		$square_items               = $square_to_woo_synchronizer->get_square_items();

		if ( $square_items ) {
			$square_items = $this->simplify_square_items_object( $square_items );
		} else {
			$square_items = array();
		}

		// 1-get unsynchronized categories (add/update).
		$categories                            = $this->get_unsynchronized_categories();
		$square_categories                     = $this->get_categories_square_ids( $categories );
		$woo_square_sync_preference            = (int) get_option( 'woo_square_sync_preference' );
		$woo_square_auto_sync                  = (int) get_option( 'woo_square_auto_sync' );
		$woo_square_listsaved_categories_wooco = get_option( 'woo_square_listsaved_categories_wooco' );
		foreach ( $categories as $cat ) {

			$square_id = null;
			$result    = array(
				'status'  => false,
				'message' => '',
			);

			if ( 0 === $woo_square_sync_preference && 1 === $woo_square_auto_sync ) {
				if ( in_array( $cat->term_id, $woo_square_listsaved_categories_wooco, true ) ) {
					if ( isset( $square_categories[ $cat->term_id ] ) ) {      // update.
						$square_id = $square_categories[ $cat->term_id ];
						$result    = $this->edit_category( $cat, $square_id );

					} else {                                         // add.
						$result = $this->add_category( $cat );

					}
				}
			} elseif ( isset( $square_categories[ $cat->term_id ] ) ) {

				// update.
					$square_id = $square_categories[ $cat->term_id ];
					$result    = $this->edit_category( $cat, $square_id );

			} else {                                         // add.
				$result = $this->add_category( $cat );
			}

			if ( true === $result['status'] ) {
				update_option( "is_square_sync_{$cat->term_id}", 1 );
			}

			// check if response returned is bool or error response message.
			$message = null;
			if ( true === $result['status'] ) {
				$message = $result['message'];
				$result  = false;
			}
		}

		// 2-get unsynchronized products (add/update).
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
		if ( 0 === $woo_square_sync_preference && 1 === $woo_square_auto_sync ) {
			$woo_square_listsaved_products_wooco = get_option( 'woo_square_listsaved_products_wooco' );
			$product_ids                         = $woo_square_listsaved_products_wooco;
		}

		/* get all products from woocommerce */
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $posts_per_page,
			'include'        => $product_ids,
		);

		$woocommerce_products = get_posts( $args );

		if ( 0 === $woo_square_sync_preference && 1 === $woo_square_auto_sync ) {
			if ( empty( $woo_square_listsaved_products_wooco ) ) {
				$woocommerce_products = array();
			}
		}

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
									'Authorization'  => 'Bearer ' . $token, // Use verbose mode in cURL to determine the format you want for this header.
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
										if ( 'ITEM' === $obj->type ) {
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
							$get_results  = 'get_results';
							$raw_modifier = $wpdb->$get_results( "SELECT * FROM {$wpdb->prefix}woosquare_modifier WHERE modifier_id = '$mod[2]'" );

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

		$action = Helpers::ACTION_DELETE;
		foreach ( $deleted_elms as $del_element ) {

			if ( $del_element->square_id ) {

				if ( (int) Helpers::TARGET_TYPE_CATEGORY === (int) $del_element->target_type ) {     // category.
					$result = $this->delete_category( $del_element->square_id );
				} elseif ( (int) Helpers::TARGET_TYPE_PRODUCT === (int) $del_element->target_type ) { // product.

					if ( ! get_option( 'disable_auto_delete' ) ) {

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
				$modifier_name     = ( explode( '_', $product_id ) );
				$modifier_set_name = str_replace( '-', ' ', $modifier_name[5] );

				if ( 1 === $modifier_name[3] ) {
					$selected_type = 'MULTIPLE';
				} else {
					$selected_type = 'SINGLE';
				}

				$dynamic_array = array();
				global $wpdb;

				if ( ! empty( $modifier_name[2] ) && ! empty( $modifier_set_name ) ) {
					$texonomy    = 'pm_' . strtolower( str_replace( ' ', '-', $modifier_set_name ) ) . '_' . ( $modifier_name[2] );
					$get_results = 'get_results';
					$term_query  = $wpdb->$get_results( ( 'SELECT term_id FROM ' . $wpdb->prefix . "term_taxonomy WHERE taxonomy = '$texonomy'" ) );

					if ( ! empty( $term_query ) ) {
						$term_query_key = 0;
						foreach ( $term_query as $key => $term ) {

									$object = get_term_by( 'id', $term->term_id, $texonomy );
									$amount = get_term_meta( $object->term_id, 'term_meta_price', true ) * 100;

							if ( empty( $object->description ) ) {
								if ( ! empty( $object->name ) ) {
									$dynamic_array[ $key ] = (object) array(
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

								$dynamic_array[ $key ] = (object) array(
									'type' => 'MODIFIER',
									'id'   => '#' . wp_rand(),
								);

							}

							++$term_query_key;
						}
					} else {

						$dynamic_array[0] = (object) array(
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
							'modifiers'      => $dynamic_array,

						),
					);

				}

				$tquery = $wpdb->$get_results( ( 'SELECT modifier_set_unique_id FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_id = '$modifier_name[2]'" ) );
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
					if ( 200 === $result['response']['code'] && 'OK' === $result['response']['message'] ) {

						$result = json_decode( $result['body'], true );

						if ( 'MODIFIER_LIST' === $result['catalog_object']['type'] ) {

							foreach ( $result['catalog_object']['modifier_list_data'] as $modifier ) {

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
												$term_query            = $wpdb->$get_results( ( 'SELECT * FROM ' . $wpdb->prefix . "term_taxonomy WHERE taxonomy = '$texonomy'" ) );

												foreach ( $term_query as $kgs => $term ) {

													$midd = $result['catalog_object']['modifier_list_data']['modifiers'][ $kgs ]['id'];
													$wpdb->query( // phpcs:ignore.
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
								$kkey_plus = 0;
								$mod_array = array();
								foreach ( $modifier_value as $mod ) {

											$mod = ( explode( '_', $mod ) );

									if ( ! empty( $mod ) ) {
										global $wpdb;
										$get_var                 = 'get_var';
										$rcount                  = $wpdb->$get_var( 'SELECT modifier_set_unique_id FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_id = '$mod[2]' " );
										$mod_array[ $kkey_plus ] = $rcount;
										++$kkey_plus;

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

								if ( ! is_wp_error( $result ) && is_array( $result ) && ! empty( $result['response'] ) && 200 === $result['response']['code'] && 'OK' === $result['response']['message'] ) {
									update_post_meta( $session_productid, 'product_sync_square_id' . $session_productid, $mod[2] );
								}
							}
						}
					}
				}
			}
		} elseif ( strpos( $product_id, '_modifier' ) ) {

				global $wpdb;
				$data              = array();
				$modifier_name     = ( explode( '_', $product_id ) );
				$get_row           = 'get_row';
				$modifier_checker  = $wpdb->$get_row( ( 'SELECT * FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_id = '$modifier_name[2]'" ) );
				$modifier_set_name = str_replace( '-', ' ', $modifier_name[5] );
				$mod_name          = str_replace( '-', ' ', $modifier_name[0] );

				$url     = esc_url( 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/list' );
				$headers = array(
					'Authorization' => 'Bearer ' . $this->square->get_access_token(), // Use verbose mode in cURL to determine the format you want for this header.
					'Content-Type'  => 'application/json',
					'types'         => 'MODIFIER_LIST',
				);

				$method                           = 'GET';
				$args                             = array( 'types' => 'MODIFIER_LIST' );
				$woo_square_location_id           = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
				$token                            = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
				$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

				$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );

				$modifier_object = array();
				$response        = false;
				$use_cached      = get_option( '_transient_timeout_' . $woo_square_location_id . 'modifier_transient_' . __FUNCTION__ ) > time();

				if ( $use_cached ) {
					$response = get_transient( $woo_square_location_id . 'modifier_transient_' . __FUNCTION__ );
					if ( is_array( $response ) && ! empty( $response['body'] ) ) {
						$modifier_object = json_decode( $response['body'], true );
					}
				}

				if ( empty( $modifier_object ) || ! is_array( $modifier_object ) ) {
					$response = array();
					$response = $square->wp_remote_woosquare( $url, $args, $method, $headers, $response );
					if ( is_array( $response ) && ! empty( $response['body'] ) ) {
						$modifier_object = json_decode( $response['body'], true );
					}
					$interval = 0;
					if ( ! empty( $modifier_object ) && is_array( $modifier_object ) && count( $modifier_object ) > 999 ) {
						$interval = 300;
					}
					if ( is_array( $response ) ) {
						set_transient( $woo_square_location_id . 'modifier_transient_' . __FUNCTION__, $response, $interval );
					}
				}

				if ( ! is_array( $modifier_object ) ) {
					$modifier_object = array();
				}

				foreach ( $modifier_object as $mod_object ) {

					if ( $mod_object['id'] === $modifier_name[1] ) {

						if ( ! empty( $modifier_name[1] ) && ! empty( $modifier_set_name ) ) {

							if ( 1 === $modifier_name[3] ) {
								$selected_type = 'MULTIPLE';
							} else {
								$selected_type = 'SINGLE';
							}

							$texonomy    = 'pm_' . strtolower( str_replace( ' ', '-', $modifier_name[5] ) ) . '_' . ( $modifier_name[2] );
							$get_results = 'get_results';
							$term_query  = $wpdb->$get_results( ( 'SELECT term_id FROM ' . $wpdb->prefix . "term_taxonomy WHERE taxonomy = '$texonomy'" ) );

							if ( ! empty( $term_query ) ) {

								$modifier_object_key = 0;
								$dynamic_array       = array();
								foreach ( $term_query as $key => $term ) {

									$object = get_term_by( 'id', $term->term_id, $texonomy );

									$amount = get_term_meta( $object->term_id, 'term_meta_price', true ) * 100;

									$version = get_term_meta( $object->term_id, 'term_meta_version', true );

									$modifier_items = array();
									if ( ! empty( $mod_object['modifier_list_data']['modifiers'] ) && is_array( $mod_object['modifier_list_data']['modifiers'] ) ) {
										$modifier_items = $mod_object['modifier_list_data']['modifiers'];
									}

									foreach ( $modifier_items as $term_obj ) {

										if ( $term_obj['id'] === $object->description ) {

											if ( ! empty( $object->description ) ) {

												if ( ! empty( $object->name ) ) {

													$dynamic_array[ $key ] = (object) array(

														'type' => 'MODIFIER',
														'id'   => $object->description,
														'version' => $term_obj['version'],
														'modifier_data' => (object) array(
															'name' => $object->name,
															'price_money' => (object) array(
																'amount' => (int) $amount,
																'currency' => get_option( 'woocommerce_currency' ),
															),
														),
													);

												} else {

													$dynamic_array[ $key ] = (object) array(
														'type' => 'MODIFIER',
														'id'   => $object->description,
													);

												}
											}
										}
									}
									if ( empty( $object->description ) ) {

										if ( ! empty( $object->name ) ) {

											$dynamic_array[ $key ] = (object) array(

												'type' => 'MODIFIER',
												'id'   => '#' . wp_rand(),
												'modifier_data' => (object) array(
													'name' => $object->name,
													'price_money' => (object) array(
														'amount'   => (int) $amount,
														'currency' => get_option( 'woocommerce_currency' ),
													),
												),
											);

										}
									}
								}++$modifier_object_key;
							}

							$data                    = array();
							$data['idempotency_key'] = uniqid();
							$data['object']          = (object) array(
								'type'               => 'MODIFIER_LIST',
								'id'                 => $modifier_name[1],
								'version'            => $mod_object['version'],
								'modifier_list_data' => (object) array(
									'name'           => $modifier_checker->modifier_set_name,
									'selection_type' => $selected_type,
									'modifiers'      => $dynamic_array,

								),

							);

						}
					}
				}

				if ( ! empty( $data ) ) {
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

					if ( ! is_wp_error( $result ) && is_array( $result ) && ! empty( $result['response'] ) && 200 === $result['response']['code'] && 'OK' === $result['response']['message'] ) {

						$result = json_decode( $result['body'], true );

						if ( 'MODIFIER_LIST' === $result['catalog_object']['type'] ) {

							foreach ( $result['catalog_object']['modifier_list_data'] as $modifier ) {

								$modifier_name = $result['catalog_object']['modifier_list_data']['name'];
								$mod_id        = $result['catalog_object']['id'];
								global $wpdb;
								$get_row     = 'get_row';
								$modifier_id = $wpdb->$get_row( 'SELECT * FROM ' . $wpdb->prefix . "woosquare_modifier WHERE  modifier_set_unique_id = '$mod_id'" );

								if ( ! empty( $modifier_id->modifier_id ) && ! empty( $modifier_id->modifier_set_name ) && ! empty( $modifier_id->modifier_set_unique_id ) && ! empty( $modifier_id->modifier_version ) ) {

									$mod_version = $result['catalog_object']['version'];
									$wpdb->query( // phpcs:ignore.
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
										$get_results           = 'get_results';
										$term_query            = $wpdb->$get_results( ( 'SELECT * FROM ' . $wpdb->prefix . "term_taxonomy WHERE taxonomy = '$texonomy'" ) );
										foreach ( $term_query as $kgs => $term ) {
											if ( empty( $term->description ) ) {
												$midd = $result['catalog_object']['modifier_list_data']['modifiers'][ $kgs ]['id'];
												$wpdb->query( // phpcs:ignore.
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
					}

					if ( ! empty( $_SESSION['productid'] ) ) {

						$session_productid = sanitize_text_field( wp_unslash( $_SESSION['productid'] ) );
						$square_id         = get_post_meta( $session_productid, 'square_id', true );

						$modifier_value = get_post_meta( $session_productid, 'product_modifier_group_name', true );
						if ( ! empty( $modifier_value ) ) {
							$kkey      = 0;
							$kkey_plus = 0;
							$mod_array = array();
							foreach ( $modifier_value as $mod ) {

								$mod = ( explode( '_', $mod ) );

								if ( ! empty( $mod ) ) {
									global $wpdb;
									$get_var        = 'get_var';
									$rcount         = $wpdb->$get_var( 'SELECT modifier_set_unique_id FROM ' . $wpdb->prefix . "woosquare_modifier WHERE modifier_id = '$mod[2]' " );
									$url            = $this->square->get_square_v2_url() . 'catalog/object/' . $rcount . '?include_related_objects=true';
									$headers        = array(
									'Authorization' => 'Bearer ' . $this->square->get_access_token(),
									'Content-Type'  => 'application/json',
									);
									$method         = 'GET';
									$response       = array();
									$args           = array( '' );
									$response_check = $square->wp_remote_woosquare( $url, $args, $method, $headers, $response );
									$response_check = json_decode( $response_check['body'], false );

									if ( 'MODIFIER_LIST' === $response_check->object->type ) {

										$mod_array[ $kkey_plus ] = $rcount;
										++$kkey_plus;
										delete_option( $mod[2] . '_' . $rcount );
									} else {
										update_option( $mod[2] . '_' . $rcount, 'This Modifier is not exist/deleted in square kindly delete it manually.' );
									}
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

							if ( 200 === $result['response']['code'] && 'OK' === $result['response']['message'] ) {
								update_post_meta( $session_productid, 'product_sync_square_id' . $session_productid, $mod[2] );
							}
						}
					}
				}
		}
	}

	/**
	 * Retrieves or creates the custom sale price attribute for WooSquare.
	 *
	 * This function first attempts to retrieve the custom sale price attribute from Square's API.
	 * If the custom sale price attribute does not exist, it creates a new one using the Square API.
	 * The function updates the option 'woosquare_sale_price_custom_attr' with the attribute key and returns it.
	 *
	 * @return string The custom sale price attribute key.
	 */
	public function get_woosquare_custom_sale_attr() {
		$token   = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$url     = esc_url( 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/list' );
		$headers = array(
			'Authorization'  => 'Bearer ' . $token, // Use verbose mode in cURL to determine the format you want for this header.
			'Content-Type'   => 'application/json',
			'Square-Version' => '2024-03-20',
			'types'          => 'CUSTOM_ATTRIBUTE_DEFINITION',
		);

		$method   = 'GET';
		$args     = array( 'types' => 'CUSTOM_ATTRIBUTE_DEFINITION' );
		$square   = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
		$response = array();
		$response = $square->wp_remote_woosquare( $url, $args, $method, $headers, $response );

		$custom_attr_list = json_decode( $response['body'], true );
		if ( is_array( $custom_attr_list ) ) {
			foreach ( $custom_attr_list as $key => $custom_attr ) {
				if ( isset( $custom_attr['custom_attribute_definition_data']['key'] ) && 'custom_sale_price' === $custom_attr['custom_attribute_definition_data']['key'] ) {
					$cust_attr = $custom_attr['custom_attribute_definition_data']['key'];
					update_option( 'woosquare_sale_price_custom_attr', $cust_attr );
					return $cust_attr;
				}
			}
		}
		if ( empty( $cust_attr ) ) {
			$url     = esc_url( 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/object' );
			$headers = array(
				'Authorization'  => 'Bearer ' . $token, // Use verbose mode in cURL to determine the format you want for this header.
				'Content-Type'   => 'application/json',
				'Square-Version' => '2024-03-20',
			);
			$data    = array(
				'idempotency_key' => uniqid(),
				'object'          => array(
					'id'                               => '#custom_sale_price',
					'type'                             => 'CUSTOM_ATTRIBUTE_DEFINITION',
					'custom_attribute_definition_data' => array(
						'allowed_object_types' => array(
							'ITEM_VARIATION',
						),
						'name'                 => 'Sale Price',
						'type'                 => 'NUMBER',
						'key'                  => 'custom_sale_price',
					),
				),
			);

			$method   = 'POST';
			$square   = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
			$response = array();
			$response = $square->wp_remote_woosquare( $url, $data, $method, $headers, $response );

			$create_custom_attr = json_decode( $response['body'], true );

			if ( ! empty( $create_custom_attr['catalog_object'] ) ) {
				$cust_attr = $create_custom_attr['catalog_object']['custom_attribute_definition_data']['key'];
				update_option( 'woosquare_sale_price_custom_attr', $cust_attr );
				return $cust_attr;
			}
		}
	}


	/**
	 * Retrieves or creates the custom sale price attribute for WooSquare.
	 *
	 * This function first attempts to retrieve the custom sale price attribute from Square's API.
	 * If the custom sale price attribute does not exist, it creates a new one using the Square API.
	 * The function updates the option 'woosquare_sale_price_custom_attr' with the attribute key and returns it.
	 *
	 * @param array $square_options_values Existing Square options retrieved via the API.
	 * @return string The custom sale price attribute key.
	 */
	public function create_woosquare_options( $square_options_values ) {

		$token    = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$base_url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog';
		$headers  = array(
			'Authorization'  => 'Bearer ' . $token, // Use verbose mode in cURL to determine the format you want for this header.
			'Content-Type'   => 'application/json',
			'Square-Version' => '2024-03-20',
		);
		$square   = new Square( $token, get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
		$method   = 'GET';
		$args     = array( 'types' => 'ITEM_OPTION' );
		$response = array();
		$response = $square->wp_remote_woosquare( $base_url . '/list', $args, $method, $headers, $response );

		$square_options_list = json_decode( $response['body'], true );

		$attr_name = explode( 'attribute_', key( $square_options_values ) )[1];

		if ( strpos( $attr_name, 'pa_' ) === 0 ) {
			$attr_name = str_replace( 'pa_', '', $attr_name );
		}
		$woo_option_values = $square_options_values[ key( $square_options_values ) ];

		$square_option_and_values = array();
		if ( ! empty( $square_options_list ) ) {
			foreach ( $square_options_list as $key => $square_option ) {
				if ( isset( $square_option['item_option_data']['name'] ) && strtolower( $square_option['item_option_data']['name'] ) === $attr_name ) {
					$square_option_and_values = $this->add_or_create_option_values( $square_option, $woo_option_values, $attr_name, $headers, $base_url . '/object', $square, $square_options_values, $square_option_and_values );
					return $square_option_and_values;
				} else {
					$square_option_and_values = $this->create_new_options( $woo_option_values, $attr_name, $headers, $base_url . '/object', $square, $square_options_values, $square_option_and_values );
				}
			}
		} else {
			$square_option_and_values = $this->create_new_options( $woo_option_values, $attr_name, $headers, $base_url . '/object', $square, $square_options_values, $square_option_and_values );
		}
		return $square_option_and_values;
	}
	/**
	 * Adds or creates option values by mapping Square options to WooCommerce attribute options.
	 *
	 * @param string $square_option             The option value from Square to be added or created.
	 * @param array  $woo_option_values         Existing WooCommerce option values.
	 * @param string $attr_name                 The attribute name to which the options belong.
	 * @param array  $headers                   Headers from the Square API response.
	 * @param string $base_url                  Base URL used to generate links or identifiers.
	 * @param object $square                    Square API client instance.
	 * @param array  $square_options_values     Full list of Square option values.
	 * @param array  $square_option_and_values  Mapped Square options and their corresponding values.
	 *
	 * @return array Updated WooCommerce option values after adding or creating new entries.
	 */
	public function add_or_create_option_values( $square_option, $woo_option_values, $attr_name, $headers, $base_url, $square, $square_options_values, $square_option_and_values ) {
		foreach ( $square_option['item_option_data']['values'] as $square_option_value ) {
			if ( ! in_array( $square_option_value['item_option_value_data']['name'], $woo_option_values, true ) ) {
				$values = array();
				foreach ( $woo_option_values as $woo_option_value ) {
					$data     = array(
						'idempotency_key' => uniqid(),
						'object'          => array(
							'id'                     => '#square_option_value_' . $woo_option_value,
							'type'                   => 'ITEM_OPTION_VAL',
							'item_option_value_data' => array(
								'item_option_id' => $square_option['id'],
								'name'           => $woo_option_value,
							),
						),
					);
					$response = array();

					$response = $square->wp_remote_woosquare( $base_url, $data, 'POST', $headers, $response );

					$square_options_vals = json_decode( $response['body'], true );
					if ( ! empty( $square_options_vals['catalog_object'] ) ) {
						$opt_name = explode( 'attribute_', key( $square_options_values ) )[1];
						if ( strpos( $opt_name, 'pa_' ) === 0 ) {
							$opt_name = 'pa_' . $square_option['item_option_data']['name'];
						}
						$square_option_and_values['attr'] = array(
							'name' => $opt_name,
							'id'   => $square_option['id'],
						);
						$square_option_and_values['values'][ $square_options_vals['catalog_object']['item_option_value_data']['name'] ] = array(
							'name' => $square_options_vals['catalog_object']['item_option_value_data']['name'],
							'id'   => $square_options_vals['catalog_object']['id'],
						);
					}
				}
			} else {
				$opt_name = explode( 'attribute_', key( $square_options_values ) )[1];
				if ( strpos( $opt_name, 'pa_' ) === 0 ) {
					$opt_name = 'pa_' . $square_option['item_option_data']['name'];
				}
				$square_option_and_values['attr'] = array(
					'name' => $opt_name,
					'id'   => $square_option['id'],
				);
				$square_option_and_values['values'][ $square_option_value['item_option_value_data']['name'] ] = array(
					'name' => $square_option_value['item_option_value_data']['name'],
					'id'   => $square_option_value['id'],
				);
			}
		}
		return $square_option_and_values;
	}
	/**
	 * Creates new Square option mappings based on WooCommerce attributes and headers.
	 *
	 * @param array  $woo_option_values         WooCommerce option values.
	 * @param string $attr_name                 Attribute name.
	 * @param array  $headers                   Headers from Square API response.
	 * @param string $base_url                  Base URL for constructing options.
	 * @param object $square                    Square API client object.
	 * @param array  $square_options_values     Values returned by Square options.
	 * @param array  $square_option_and_values  Combined Square option-value mapping.
	 *
	 * @return array Modified WooCommerce option values with new Square mappings.
	 */
	public function create_new_options( $woo_option_values, $attr_name, $headers, $base_url, $square, $square_options_values, $square_option_and_values ) {
		$values = array();

		foreach ( $woo_option_values as $woo_option_value ) {
			$values[] = array(
				'id'                     => '#square_option_value_' . $woo_option_value,
				'type'                   => 'ITEM_OPTION_VAL',
				'item_option_value_data' => array(
					'name' => $woo_option_value,
				),
			);
		}
		$data     = array(
			'idempotency_key' => uniqid(),
			'object'          => array(
				'id'               => '#square_option',
				'type'             => 'ITEM_OPTION',
				'item_option_data' => array(
					'name'   => $attr_name,
					'values' => $values,
				),
			),
		);
		$response = array();
		$response = $square->wp_remote_woosquare( $base_url, $data, 'POST', $headers, $response );

		$square_options_vals = json_decode( $response['body'], true );

		if ( ! empty( $square_options_vals['catalog_object'] ) ) {
			$opt_name = explode( 'attribute_', key( $square_options_values ) )[1];
			if ( strpos( $opt_name, 'pa_' ) === 0 ) {
				$opt_name = 'pa_' . $square_options_vals['catalog_object']['item_option_data']['name'];
			}
			$square_option_and_values['attr'] = array(
				'name' => $opt_name,
				'id'   => $square_options_vals['catalog_object']['id'],
			);
			foreach ( $square_options_vals['catalog_object']['item_option_data']['values'] as $value ) {
				$square_option_and_values['values'][ $value['item_option_value_data']['name'] ] = array(
					'name' => $value['item_option_value_data']['name'],
					'id'   => $value['id'],
				);
			}
		}
		return $square_option_and_values;
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

		$token       = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );

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
			'Authorization' => 'Bearer ' . $token, // Use verbose mode in cURL to determine the format you want for this header.
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

		$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );

		$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/object';

		$headers = array(
			'Authorization' => 'Bearer ' . $this->square->get_access_token(), // Use verbose mode in cURL to determine the format you want for this header.
			'Content-Type'  => 'application/json',
		);

		$method = 'POST';

		$response = array();
		$response = $square->wp_remote_woosquare( $url, $cat_json, $method, $headers, $response );

		$object_edit_category = json_decode( $response['body'], true );

		$resultobj = isset( $object_edit_category['catalog_object'] ) ? $object_edit_category['catalog_object'] : array();

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
		$token                  = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );

		try {
			$categories = get_the_terms( $product, 'product_cat' );
			if ( is_wp_error( $categories ) ) {
				$categories = array();
			}
		} catch ( Exception $e ) {
			$categories = array();
		}
		
		if ( ! $categories ) {
			$categories = array();
		}

		$category_square_id = null;

		try {
			$square                     = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );
			$square_to_woo_synchronizer = new SquareToWooSynchronizer( $square );
			$square_categories          = $square_to_woo_synchronizer->get_square_categories();
		} catch ( Exception $e ) {
			throw $e;
		}

		$is_enable_square_option_format = $square_to_woo_synchronizer->is_enable_square_option_format( $product_square_id );
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

		if ( strlen( $data['description'] ) >= 4095 ) {

			$data['description'] = substr( $data['description'], 0, 4096 );

		}

		$data['category_id'] = $category_square_id;
		$data['visibility']  = ( 'publish' === $product->post_status ) ? 'PUBLIC' : 'PRIVATE';

		// check if there are attributes.

		$_product = wc_get_product( $product->ID );

		$custom_sale_attr = $this->get_woosquare_custom_sale_attr();
		// Initialize custom_variation_attributes for all product types.
		$custom_variation_attributes = array();
		if ( $_product->is_type( 'variable' ) || $_product->is_type( 'booking' ) ) {   // Variable Product.
			$unserialize        = 'unserialize';
			$product_variations = $unserialize( $product_details['_product_attributes'][0] );
			foreach ( $product_variations as $product_variation ) {
				// check if there are variations with fees.
				$attr_name                               = sanitize_title( $product_variation['name'] );
				$attr_meta_key                           = 'attribute_' . $attr_name;
				$square_options_values                   = array();
				$square_options_values[ $attr_meta_key ] = array();
				if ( $product_variation['is_variation'] ) {

					$args           = array(
						'post_parent' => $product->ID,
						'post_type'   => 'product_variation',
					);
					$child_products = get_children( $args );

					$admin_msg = false;
					foreach ( $child_products as $child_product ) {
						$child_product_meta = get_post_meta( $child_product->ID );

						if ( $is_enable_square_option_format ) {
							$attr_value = isset( $child_product_meta[ $attr_meta_key ][0] ) ? $child_product_meta[ $attr_meta_key ][0] : '';
							if ( $attr_value && ! in_array( $attr_value, $square_options_values[ $attr_meta_key ], true ) ) {
								$square_options_values[ $attr_meta_key ][] = $attr_value;
							}
						}
						$variation_name = isset( $child_product_meta[ $attr_meta_key ][0] ) ? $child_product_meta[ $attr_meta_key ][0] : '';
						if ( empty( $child_product_meta['_sku'][0] ) ) {
							// admin msg that variation sku empty not sync in sqaure.
							$admin_msg = true;
						}
						if ( empty( $child_product_meta['_sku'][0] ) ) {
							// don't add product variaton that doesn't have SKU.
							continue;
						}

						$data['variations'][ $child_product_meta['_sku'][0] ][] = array(
							'name'            => $is_enable_square_option_format ? $variation_name : $attr_name . '[' . $variation_name . ']',
							'sku'             => $child_product_meta['_sku'][0],
							'upc'             => isset( $child_product_meta['_global_unique_id'][0] ) ? $child_product_meta['_global_unique_id'][0] : null,
							'track_inventory' => ( 'yes' === $child_product_meta['_manage_stock'][0] ) ? true : false,
							'price_money'     => array(
								'currency_code' => get_option( 'woocommerce_currency' ),
								'amount'        => $square->format_amount( ! empty( $child_product_meta['_price'][0] ) && 0 !== $child_product_meta['_price'][0] ? $child_product_meta['_price'][0] : ( $child_product_meta['_regular_price'][0] ?? 0 ), 'wotosq', get_option( 'woocommerce_currency' ) ),
							),
							'sale_price'      => isset( $child_product_meta['_sale_price'][0] ) ? $child_product_meta['_sale_price'][0] : null,
							$attr_name        => $is_enable_square_option_format ? $variation_name : '',
						);
					}
					if ( $is_enable_square_option_format ) {
						$custom_variation_attr         = $this->create_woosquare_options( $square_options_values );
						$custom_variation_attributes[] = $custom_variation_attr;
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
							'currency_code' => get_option( 'woocommerce_currency' ),
							'amount'        => $square->format_amount( $product_details['_price'][0], 'wotosq', get_option( 'woocommerce_currency' ) ),
						),
						'sale_price'      => isset( $product_details['_sale_price'][0] ) ? $product_details['_sale_price'][0] : null,
					);
				}
			}

			// [color:red,size:smal] sample than below for multiple attributes and variations.
			// color[black],size[smal] sample.
			$setvariationformultupleattr = $data['variations'];
			foreach ( $setvariationformultupleattr as $mult_attr ) {

				if ( $is_enable_square_option_format ) {
					$merged_attr    = array();
					$getingattrname = '';
					foreach ( $mult_attr as $attr ) {
						// Only merge if $attr is an array, otherwise skip or handle as string.
						if ( is_array( $attr ) ) {
							$merged_attr     = array_replace_recursive( $merged_attr, $attr );
							$getingattrname .= ( isset( $attr['name'] ) ? $attr['name'] : '' ) . ', ';
						} else {
							// If $attr is a string, just append it.
							$getingattrname .= $attr . ', ';
						}
					}
					if ( substr( $getingattrname, -2 ) === ', ' ) {
						$getingattrname = substr( $getingattrname, 0, -2 );
					}
					$merged_attr['name'] = $getingattrname;
					$datavariations[]    = $merged_attr;
				} else {
					$getingattrname = '';
					
					// Check if $mult_attr is an array of arrays (variable product) or a single array (simple product).
					$attrs_to_process = array();
					if ( is_array( $mult_attr ) && ! empty( $mult_attr ) ) {
						// Check if this looks like a variation array (has 'name', 'sku' keys) or an array of variation arrays.
						$is_variation_array = isset( $mult_attr['name'] ) || isset( $mult_attr['sku'] );
						if ( $is_variation_array ) {
							// Simple product: $mult_attr is already a single variation array.
							$attrs_to_process = array( $mult_attr );
						} else {
							// Variable product: $mult_attr is array of variation arrays.
							$attrs_to_process = $mult_attr;
						}
					}
					
					foreach ( $attrs_to_process as $attr ) {
						if ( is_array( $attr ) && isset( $attr['name'] ) ) {
							$getingattrnamedata = explode( '[', $attr['name'] );
							if ( isset( $getingattrnamedata[1] ) ) {
								$getingattrval   = explode( ']', $getingattrnamedata[1] );
								$getingattrname .= str_replace( 'pa_', '', $getingattrnamedata[0] ) . '[' . ( isset( $getingattrval[0] ) ? $getingattrval[0] : '' ) . '],';
							}
						}
					}

					$getingattrname   = rtrim( $getingattrname, ',' );
					$last_attr        = ! empty( $attrs_to_process ) ? end( $attrs_to_process ) : array();
					$datavariations[] = array(
						'name'            => $getingattrname,
						'sku'             => isset( $last_attr['sku'] ) ? $last_attr['sku'] : '',
						'upc'             => isset( $last_attr['upc'] ) ? $last_attr['upc'] : null,
						'track_inventory' => isset( $last_attr['track_inventory'] ) ? $last_attr['track_inventory'] : false,
						'price_money'     => array(
							'currency_code' => get_option( 'woocommerce_currency' ),
							'amount'        => isset( $last_attr['price_money']['amount'] ) ? $last_attr['price_money']['amount'] : 0,
						),
						'sale_price'      => isset( $last_attr['sale_price'] ) ? $last_attr['sale_price'] : null,
					);
				}
			}
			$data['variations'] = array();
			$data['variations'] = $datavariations;
		} elseif ( $_product->is_type( 'simple' ) ) {   // Simple Product.

			if ( empty( $product_details['_sku'][0] ) ) {
				update_post_meta( $product->ID, 'admin_notice_square', 'Product unable to sync to Square due to Sku missing ' );
				// don't add product that doesn't have SKU.
				$message = 'Product unable to sync to Square due to Sku missing';
				$dddd    = array(
					'id'         => $product->ID,
					'status'     => false,
					'pro_status' => 'failed',
					'message'    => $message,
				);
				return $dddd;
			} else {
				delete_post_meta( $product->ID, 'admin_notice_square', 'Product unable to sync to Square due to Sku missing ' );
			}
			// check if there are attributes.
			if ( ! empty( $product_details['_product_attributes'] ) ) {
				$unserialize        = 'unserialize';
				$product_variations = $unserialize( $product_details['_product_attributes'][0] );

				if ( ! empty( $product_variations ) ) {
					$pa = ''; // Initialize $pa variable
					foreach ( $product_variations as $variations ) {
						$variat = explode( '_', $variations['name'] );
						if ( 'pa' === $variat[0] ) {
							$variatio = ( wc_get_product_terms( $product->ID, $variations['name'], array( 'fields' => 'names' ) ) );
							// Check if $variatio is not empty before adding
							if ( isset( $variat[1] ) && ! empty( $variatio ) && is_array( $variatio ) ) {
								$pa .= $variat[1] . '[' . implode( '|', $variatio ) . '],';
							}
						} elseif ( isset( $variations['name'], $variations['value'] ) && ! empty( trim( $variations['value'] ) ) ) {
							// Check if both name and value exist AND value is not empty
							$pa .= $variations['name'] . '[' . $variations['value'] . '],';
						}
					}
					$pa = rtrim( $pa, ',' );
					// If $pa is empty after processing, set it to 'Regular'
					if ( empty( $pa ) ) {
						$pa = 'Regular';
					}
				} else {
					$pa = 'Regular';
				}

				$data['variations'][] = array(
					'name'            => $pa,
					'sku'             => $product_details['_sku'][0],
					'upc'             => $product_details['_global_unique_id'][0],
					'track_inventory' => ( 'yes' === $product_details['_manage_stock'][0] ) ? true : false,
					'price_money'     => array(
						'currency_code' => get_option( 'woocommerce_currency' ),
						'amount'        => $square->format_amount( $product_details['_price'][0], 'wotosq', get_option( 'woocommerce_currency' ) ),
					),
					'sale_price'      => isset( $product_details['_sale_price'][0] ) ? $product_details['_sale_price'][0] : null,
				);
			} else {
				$pa                   = 'Regular';
				$data['variations'][] = array(
					'name'            => $pa,
					'sku'             => $product_details['_sku'][0],
					'upc'             => $product_details['_global_unique_id'][0],
					'track_inventory' => ( 'yes' === $product_details['_manage_stock'][0] ) ? true : false,
					'price_money'     => array(
						'currency_code' => get_option( 'woocommerce_currency' ),
						'amount'        => $square->format_amount( $product_details['_price'][0], 'wotosq', get_option( 'woocommerce_currency' ) ),
					),
					'sale_price'      => isset( $product_details['_sale_price'] ) ? $product_details['_sale_price'][0] : null,
				);

			}
		}
		// Connect to Square to add this item.

		if ( function_exists( 'Manage_stock_from_square_function' ) ) {

			$square  = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
			$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v1/' . $woo_square_location_id . '/inventory';
			$method  = 'GET';
			$headers = array(
				'Authorization' => 'Bearer ' . get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), // Use verbose mode in cURL to determine the format you want for this header.
				'cache-control' => 'no-cache',
				'Content-Type'  => 'application/json',
			);

			$response = array();
			$response = $square->wp_remote_woosquare( $url, $card_details, $method, $headers, $response );

			if ( 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {
				$all_square_variation = json_decode( $response['body'], true );
			}
		}

		if ( ( $_product->is_type( 'variable' ) ) || ( $_product->is_type( 'simple' ) ) || ( $_product->is_type( 'booking' ) ) ) {
			// If this is a manual sync (via AJAX), disable sync_on_add_edit to allow full sync.
			// Check for manual sync flag or specific AJAX action with id parameter.
			$is_manual_sync = false;

			// Check if manual sync is running.
			if ( get_option( 'woo_square_running_sync' ) === 'manual' ) {
				// Verify nonce if this is an AJAX request.
				if ( wp_doing_ajax() && isset( $_POST['ajaxnonce'] ) ) {
					if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ajaxnonce'] ) ), 'my_woosquare_ajax_nonce' ) ) {
						return; // Nonce verification failed, exit early.
					}
				} elseif ( ! wp_doing_ajax() && isset( $_POST['_wpnonce'] ) ) {
					// Verify WordPress post edit nonce for admin edit page.
					// WordPress already verifies this before calling save_post hook, but we verify again for PHPCS compliance.
					$post_id = isset( $_POST['post_ID'] ) ? intval( $_POST['post_ID'] ) : ( isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0 );
					if ( $post_id > 0 ) {
						$nonce_action = 'update-post_' . $post_id;
						if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), $nonce_action ) ) {
							return; // Nonce verification failed, exit early.
						}
					}
				}
				$is_manual_sync = true;
			} elseif ( wp_doing_ajax() && isset( $_POST['action'] ) && 'sync_woo_product_to_square' === sanitize_text_field( wp_unslash( $_POST['action'] ) ) && isset( $_POST['id'] ) ) {
				// Check for specific manual sync AJAX action with id parameter.
				// Verify nonce before processing POST data.
				if ( ! isset( $_POST['ajaxnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ajaxnonce'] ) ), 'my_woosquare_ajax_nonce' ) ) {
					return; // Nonce verification failed, exit early.
				}
				$is_manual_sync = true;
			} elseif ( ! wp_doing_ajax() && isset( $_POST['_wpnonce'] ) ) {
				// Normal admin edit from wp-admin: verify WordPress post edit nonce.
				// WordPress already verifies this before calling save_post hook, but we verify again for PHPCS compliance.
				$post_id = isset( $_POST['post_ID'] ) ? intval( $_POST['post_ID'] ) : ( isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0 );
				if ( $post_id > 0 ) {
					$nonce_action = 'update-post_' . $post_id;
					if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), $nonce_action ) ) {
						return; // Nonce verification failed, exit early.
					}
				}
			}

			if ( $is_manual_sync ) {
				// Manual sync: disable sync_on_add_edit to allow full sync.
				$sync_on_add_edit = false;
			} else {
				// Normal edit: apply sync_on_add_edit settings.
				$sync_on_add_edit = get_option( 'sync_on_add_edit' );
			}

				$woosquare_pro_edit_fields           = get_option( 'woosquare_pro_edit_fields' );
				$woo_square_sync_preference          = (int) get_option( 'woo_square_sync_preference' );
				$woo_square_auto_sync                = (int) get_option( 'woo_square_auto_sync' );
				$woo_square_listsaved_products_wooco = get_option( 'woo_square_listsaved_products_wooco' );
				$update_inventory                    = true;
				$upload_image                        = true;

			if ( 0 === $woo_square_sync_preference && 1 === $woo_square_auto_sync ) {
				if ( is_array( $woo_square_listsaved_products_wooco ) && ! in_array( $product->ID, $woo_square_listsaved_products_wooco, true ) ) {
					return true;
				}
			}

			if ( '1' === $sync_on_add_edit ) {
				$woosquare_pro_edit_fields = get_option( 'woosquare_pro_edit_fields' );

				if ( is_array( $woosquare_pro_edit_fields ) ) {
					if ( ! in_array( 'title', $woosquare_pro_edit_fields, true ) ) {
						unset( $data['name'] );
						if ( ! empty( $product_square_id->name ) ) {
							$data['name'] = $product_square_id->name;
						} else {
							$data['name'] = '-';
						}
					}
					if ( ! in_array( 'description', $woosquare_pro_edit_fields, true ) ) {
						$data['description'] = $product_square_id->description;
					}
					if ( ! in_array( 'price', $woosquare_pro_edit_fields, true ) ) {

						if ( ! empty( $product_square_id->variations[0]->price_money->amount ) ) {
							$data['variations'][0]['price_money']['amount']        = $product_square_id->variations[0]->price_money->amount;
							$data['variations'][0]['price_money']['currency_code'] = $product_square_id->variations[0]->price_money->currency_code;
						} else {
							$data['variations'][0]['price_money']['amount']        = 0;
							$data['variations'][0]['price_money']['currency_code'] = get_option( 'woocommerce_currency' );
						}
					}
					if ( ! in_array( 'stock', $woosquare_pro_edit_fields, true ) ) {
						$update_inventory = false;
					}
					if ( ! in_array( 'category', $woosquare_pro_edit_fields, true ) ) {
						if ( ! empty( $product_square_id->category_id ) ) {
							$data['category_id'] = $product_square_id->category_id;
						}
					}

					if ( ! in_array( 'pro_image', $woosquare_pro_edit_fields, true ) ) {
						$upload_image = false;
						// Preserve existing image_ids from Square when image sync is disabled.
						$existing_image_ids = array();

						// Check item_data->image_ids first (v2 API structure).
						if ( ! empty( $product_square_id->item_data->image_ids ) && is_array( $product_square_id->item_data->image_ids ) ) {
							$existing_image_ids = $product_square_id->item_data->image_ids;
						} elseif ( ! empty( $product_square_id->image_ids ) && is_array( $product_square_id->image_ids ) ) {
							$existing_image_ids = $product_square_id->image_ids;
						} elseif ( ! empty( $product_square_id->master_image->id ) ) {
							// Fallback to master_image->id if image_ids array not available.
							$existing_image_ids = array( $product_square_id->master_image->id );
						}

						if ( ! empty( $existing_image_ids ) ) {
							$data['preserve_image_ids'] = $existing_image_ids;
						}
					}
				}
			}

			if ( $product_square_id ) {
				$exist_in_square = $product_square_id;

				if ( ! empty( $exist_in_square->id ) ) {
					if ( ( ! empty( $exist_in_square->item_data->variations )
					|| ! empty( $exist_in_square->variations ) )
					&& ! empty( $data['variations'] )
					) {

						if ( ! empty( $exist_in_square->item_data->variations ) ) {
							$exist_in_square->variations = $exist_in_square->item_data->variations;
						}

						foreach ( $exist_in_square->variations as $variation_upd ) {
							foreach ( $data['variations'] as $ky => $variation_data ) {
								if ( $variation_upd->sku === $variation_data['sku'] || $variation_upd->item_variation_data->sku === $variation_data['sku'] ) {

									$variation_ids[ $variation_data['sku'] ] = $variation_upd->id;

									if ( empty( $_SESSION ) ) {
										if ( 1 === $sync_on_add_edit ) {
											if ( ! empty( $woosquare_pro_edit_fields ) && ! in_array( 'price', $woosquare_pro_edit_fields, true ) ) {

												$variation_data['price_money']['amount']        = $variation_upd->price_money->amount;
												$variation_data['price_money']['currency_code'] = get_woocommerce_currency();
												$data['variations'][ $ky ]                      = $variation_data;
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
					$request = 'POST';
					// for temporary item id.
					$item_id  = '#' . $data['name'] . '-t';
					$prod_cri = 'add';
				}
			} else {
				$request = 'POST';
				// for temporary item id.
				$item_id  = '#' . $data['name'] . '-t';
				$prod_cri = 'add';
			}

				$data_json = array();

				$forversion = get_post_meta( $product->ID, 'log_woosquare_update_items_response', true );

				$data_json['idempotency_key'] = uniqid();
				$data_json['object']['type']  = 'ITEM';

				$data_json['object']['id'] = $item_id;

			if ( ! empty( $exist_in_square->version ) ) {
				$data_json['object']['version'] = (int) $exist_in_square->version;
			}

				$data_json['object']['item_data']['name']         = $data['name'];
				$data_json['object']['item_data']['product_type'] = 'REGULAR';
				$data_json['object']['item_data']['description']  = $data['description'];
				$data_json['object']['item_data']['visibility']   = $data['visibility'];

				// Assuming $exist_in_square is the object you're working with.
				$existing_location_ids = isset( $exist_in_square->present_at_location_ids ) ? $exist_in_square->present_at_location_ids : array();

				// Check if the new location ID is already present in the existing array.
			if ( ! in_array( $woo_square_location_id, $existing_location_ids, true ) ) {
				// If the location is not in the existing array, add it.
				$existing_location_ids[] = $woo_square_location_id;
			}

				$data_json['object']['present_at_location_ids'] = $existing_location_ids;

				// Set 'present_at_all_locations' to false.
				$data_json['object']['present_at_all_locations'] = false;
			if ( $is_enable_square_option_format && ! empty( $custom_variation_attributes ) && is_array( $custom_variation_attributes ) ) {
				$item_option_ids = array();
				foreach ( $custom_variation_attributes as $cus_var_attr ) {
					if ( isset( $cus_var_attr ) ) {
						$item_option_id = null;
						if ( isset( $cus_var_attr['attr']['name'] ) && isset( $cus_var_attr['attr']['id'] ) ) {
							$item_option_id = $cus_var_attr['attr']['id'];
						}
						// Only add if item_option_id is not empty
						if ( ! empty( $item_option_id ) ) {
							$item_option_ids[] = array(
								'item_option_id' => $item_option_id,
							);
						}
					}
				}
				// Only set item_options if we have valid option IDs
				if ( ! empty( $item_option_ids ) ) {
					$data_json['object']['item_data']['item_options'] = $item_option_ids;
				} elseif ( isset( $data_json['object']['item_data']['item_options'] ) ) {
					// Remove item_options if it exists and is empty.
					unset( $data_json['object']['item_data']['item_options'] );
				}

			}

			if ( ! empty( $data['category_id'] ) ) {
				$data_json['object']['item_data']['categories'][0]['id']      = $data['category_id'];
				$data_json['object']['item_data']['reporting_category']['id'] = $data['category_id'];
			}
			if ( ! empty( $product_square_id->tax_ids ) ) {
				$data_json['object']['item_data']['tax_ids'] = $product_square_id->tax_ids;
			}
			// Preserve existing image_ids when image sync is disabled to prevent image removal.
			if ( ! empty( $data['preserve_image_ids'] ) && is_array( $data['preserve_image_ids'] ) ) {
				$data_json['object']['item_data']['image_ids'] = $data['preserve_image_ids'];
			}
			foreach ( $data['variations'] as $key => $variant ) {
				// Skip variations with empty SKU (SKU is required)
				if ( empty( $variant['sku'] ) ) {
					continue; // Skip this variation
				}
				
				// If name is empty, use SKU as name (Square requires a name for variations)
				if ( empty( $variant['name'] ) ) {
					$variant['name'] = $variant['sku'];
				}

				if ( isset( $variant['sale_price'] ) && ! empty( $variant['sale_price'] ) ) {
					$sale_price[ $custom_sale_attr ] = array(
						'name'         => 'Sale Price',
						'number_value' => strval( $variant['sale_price'] ),
					);
				}
				$data_json['object']['item_data']['variations'][ $key ]['type'] = 'ITEM_VARIATION';

				$data_json['object']['item_data']['variations'][ $key ]['present_at_all_locations'] = false;
				$data_json['object']['item_data']['variations'][ $key ]['present_at_location_ids']  = array( $woo_square_location_id );

				if ( isset( $sale_price ) ) {
					$data_json['object']['item_data']['variations'][ $key ]['custom_attribute_values'] = $sale_price;
				}
				
				// Set variation ID - ensure it's not just '#' 
				if ( $_product->is_type( 'booking' ) ) {
					$variation_sku = $_product->get_sku();
					if ( ! empty( $variation_sku ) ) {
						$data_json['object']['item_data']['variations'][ $key ]['id'] = '#' . $variation_sku;
					} else {
						// Use product ID if SKU is empty
						$data_json['object']['item_data']['variations'][ $key ]['id'] = '#' . $product->ID . '-' . $key;
					}
				} elseif ( ! empty( $variation_ids[ $variant['sku'] ] ) ) {
					$data_json['object']['item_data']['variations'][ $key ]['id'] = $variation_ids[ $variant['sku'] ];
				} elseif ( ! empty( $variant['sku'] ) ) {
					// Ensure SKU is not empty before creating ID.
					$data_json['object']['item_data']['variations'][ $key ]['id'] = '#' . $variant['sku'];
				} else {
					// Use variation name or product ID + key as fallback.
					$fallback_id = ! empty( $variant['name'] ) ? sanitize_title( $variant['name'] ) : ( $product->ID . '-' . $key );
					$data_json['object']['item_data']['variations'][ $key ]['id'] = '#' . $fallback_id;
				}

				if ( ! empty( $exist_in_square->variations ) ) {
					foreach ( $exist_in_square->variations as $variatversion ) {

						if ( isset( $variation_ids[ $variant['sku'] ] ) && $variatversion->id === $variation_ids[ $variant['sku'] ] ) {
							$data_json['object']['item_data']['variations'][ $key ]['version'] = (int) $variatversion->version;

							if ( ! empty( $variant['upc'] ) ) {
								$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['upc'] = $variant['upc'];
							} else {
								$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['upc'] = isset( $variatversion->item_variation_data->upc ) ? $variatversion->item_variation_data->upc : '';
							}
						}
					}
				}
				if ( $is_enable_square_option_format && ! empty( $custom_variation_attributes ) && is_array( $custom_variation_attributes ) ) {
					$item_option_values = array();
					foreach ( $custom_variation_attributes as $cus_var_attr ) {
						if ( isset( $cus_var_attr ) && isset( $cus_var_attr['attr'] ) && isset( $cus_var_attr['attr']['name'] ) ) {
							$item_option_id       = null;
							$item_option_value_id = null;
							
							// Check if variant has this attribute
							if ( isset( $variant[ $cus_var_attr['attr']['name'] ] ) ) {
								$item_option_id     = $cus_var_attr['attr']['id'];
								$variant_attr_value = $variant[ $cus_var_attr['attr']['name'] ];
								
								// Find matching value in custom_variation_attributes
								if ( isset( $cus_var_attr['values'] ) && is_array( $cus_var_attr['values'] ) ) {
									foreach ( $cus_var_attr['values'] as $attr_key => $vals ) {
										// Try multiple matching strategies
										$match_found = false;
										
										// Strategy 1: Direct key match
										if ( $variant_attr_value === $attr_key ) {
											if ( isset( $vals['id'] ) ) {
												$item_option_value_id = $vals['id'];
												$match_found          = true;
											}
										}
										
										// Strategy 2: Match with name field if key doesn't match
										if ( ! $match_found && isset( $vals['name'] ) && $variant_attr_value === $vals['name'] ) {
											if ( isset( $vals['id'] ) ) {
												$item_option_value_id = $vals['id'];
												$match_found          = true;
											}
										}
										
										// Strategy 3: Type conversion match (string to string)
										if ( ! $match_found && (string) $variant_attr_value === (string) $attr_key ) {
											if ( isset( $vals['id'] ) ) {
												$item_option_value_id = $vals['id'];
												$match_found          = true;
											}
										}
										
										if ( $match_found ) {
											break; // Found match, exit loop
										}
									}
								}
								
								// If option value not found, create it in Square
								if ( ! empty( $item_option_id ) && empty( $item_option_value_id ) ) {
									// Create missing option value in Square
									$base_url = $square->get_square_v2_url() . 'catalog/object';
									$headers  = array(
										'Authorization'  => 'Bearer ' . $square->get_access_token(),
										'Content-Type'   => 'application/json',
										'Square-Version' => '2024-03-20',
									);
									
									$create_value_data = array(
										'idempotency_key' => uniqid(),
										'object'          => array(
											'id'                     => '#square_option_value_' . $variant_attr_value,
											'type'                   => 'ITEM_OPTION_VAL',
											'item_option_value_data' => array(
												'item_option_id' => $item_option_id,
												'name'           => $variant_attr_value,
											),
										),
									);
									
									$create_response = array();
									$create_response = $square->wp_remote_woosquare( $base_url, $create_value_data, 'POST', $headers, $create_response );
									
									if ( isset( $create_response['response']['code'] ) && 200 === $create_response['response']['code'] ) {
										$created_value = json_decode( $create_response['body'], true );
										if ( ! empty( $created_value['catalog_object'] ) && isset( $created_value['catalog_object']['id'] ) ) {
											$item_option_value_id = $created_value['catalog_object']['id'];
											// Add to custom_variation_attributes for future use
											$cus_var_attr['values'][ $variant_attr_value ] = array(
												'name' => $variant_attr_value,
												'id'   => $item_option_value_id,
											);
										}
									}
								}
								
								// Only add if both IDs are set
								if ( ! empty( $item_option_id ) && ! empty( $item_option_value_id ) ) {
									$item_option_values[] = array(
										'item_option_id'       => $item_option_id,
										'item_option_value_id' => $item_option_value_id,
									);
								}
							}
						}
					}
					
					// Only set item_option_values if we have valid values
					if ( ! empty( $item_option_values ) ) {
						$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['item_option_values'] = $item_option_values;
					} else {
						// If no valid option values, remove item_option_values to avoid error
						unset( $data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['item_option_values'] );
					}

				}
				if ( ! empty( $variant['upc'] ) && '1' === get_option( 'enable_woosquare_gtin_field' ) ) {
					$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['upc'] = $variant['upc'];
				}
				if ( $_product->is_type( 'booking' ) ) {
					$check_sku = $_product->get_sku();
					$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['sku']                     = $check_sku;
					$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['price_money']['amount']   = 0;
					$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['price_money']['currency'] = $variant['price_money']['currency_code'];
					$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['pricing_type']            = 'FIXED_PRICING';
				} else {
					if ( isset( $variant['name'] ) ) {
						$name = htmlspecialchars( $variant['name'], ENT_QUOTES, 'UTF-8' ); // Escape special characters.
						if ( strlen( $name ) > 255 ) {
							$variant['name'] = substr( $name, 0, 251 ) . '....'; // Trim and add "...".
						}
					}
					$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['name']                    = (string) $variant['name'];
					$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['sku']                     = $variant['sku'];
					$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['track_inventory']         = $variant['track_inventory'];
					$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['price_money']['amount']   = (int) $variant['price_money']['amount'];
					$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['pricing_type']            = 'FIXED_PRICING';
					$data_json['object']['item_data']['variations'][ $key ]['item_variation_data']['price_money']['currency'] = $variant['price_money']['currency_code'];
				}
			}
			
			// Re-index variations array to ensure sequential keys (Square API requires indexed array, not associative)
			if ( isset( $data_json['object']['item_data']['variations'] ) && is_array( $data_json['object']['item_data']['variations'] ) ) {
				$data_json['object']['item_data']['variations'] = array_values( $data_json['object']['item_data']['variations'] );
			}

				$data_json = ( $data_json );

				$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/object';

				$headers  = array(
					'Authorization'  => 'Bearer ' . $this->square->get_access_token(),
					'Content-Type'   => 'application/json',
					'Square-Version' => '2024-03-20',
				);
				$method   = 'POST';
				$response = array();

				$response     = $square->wp_remote_woosquare( $url, $data_json, $method, $headers, $response );
				$responsesync = $response;

				update_post_meta( $product->ID, 'log_woosquare_update_items_request', $data_json );

				$object_response = json_decode( $response['body'], true );

				if ( 200 !== $response['response']['code'] && 'OK' !== $response['response']['message'] ) {
					// some kind of an error happened.

					update_post_meta( $product->ID, 'log_woosquare_update_items_response_error', $object_response );
					$message = '';
					if ( isset( $object_response['errors'] ) && is_array( $object_response['errors'] ) ) {
						foreach ( $object_response['errors'] as $error ) {
							$error_detail    = isset( $error['detail'] ) ? $error['detail'] : '';
							$error_field_raw = $error['field'] ?? '';
							$error_field     = is_string( $error_field_raw ) ? str_replace( '_', ' ', $error_field_raw ) : '';
							if ( $error_detail ) {
								$message .= $error_detail . ( $error_field ? ' - ' . $error_field : '' ) . '; ';
							}
						}
						$message = rtrim( $message, '; ' );
					}
					if ( empty( $message ) ) {
						$message = 'Unknown error occurred';
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
						$square_inventory       = array();
						$square_inventory_count = 0;
						if ( 'PUT' === $request ) {
							$square       = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );
							$synchronizer = new SquareToWooSynchronizer( $square );

							$square_inventory_raw = $synchronizer->get_square_inventory( $response['item_data']['variations'] );
							if ( $square_inventory_raw ) {
								if ( isset( $square_inventory_raw->counts ) && is_array( $square_inventory_raw->counts ) ) {
									$square_inventory = $square_inventory_raw->counts;
								} elseif ( is_array( $square_inventory_raw ) ) {
									$square_inventory = $square_inventory_raw;
								}
							}

							$inventorycount         = count( $response['item_data']['variations'] );
							$square_inventory_count = is_countable( $square_inventory ) ? count( $square_inventory ) : 0;

						}
						// Update product variations ids with square ids.
						$args           = array(
							'post_parent' => $product->ID,
							'post_type'   => 'product_variation',
						);
						$child_products = get_children( $args );
						if ( isset( $child_products ) ) {
							if ( empty( $child_products ) ) {
								$child_products[] = get_post( $product->ID );
							}
							foreach ( $child_products as $child_product ) {
								$cn = 1;

								foreach ( $response['item_data']['variations'] as $variation ) {
									$d = new DateTime();

									$variation['updated_at'] = $d->format( 'Y-m-d\TH:i:s' ) . '.000Z';
									$child_product_meta      = get_post_meta( $child_product->ID );

									$variation_sku = isset( $child_product_meta['_sku'] ) ? $child_product_meta['_sku'][0] : null;
									if ( $variation['item_variation_data']['sku'] === $variation_sku ) {
										update_post_meta( $child_product->ID, 'variation_square_id', $variation['id'] );
										if ( $update_inventory ) {

											if ( 'yes' === $child_product_meta['_manage_stock'][0] ) {
												if ( ! empty( $square_inventory ) && 'PUT' === $request ) {
													foreach ( $square_inventory as $varid ) {

														if ( $varid->catalog_object_id === $variation['id'] ) {

															if ( $varid->quantity < $child_product_meta['_stock'][0] ) {
																$stock   = $child_product_meta['_stock'][0] - $varid->quantity;
																$adjtype = 'RECEIVE_STOCK';

																$this->update_inventory( $variation, $stock, $adjtype, $woo_square_location_id );
															} elseif ( $varid->quantity > $child_product_meta['_stock'][0] ) {
																$adjtype = 'SALE';
																$stock   = $varid->quantity - $child_product_meta['_stock'][0];

																$this->update_inventory( $variation, $stock, $adjtype, $woo_square_location_id );
															}
															$matched_variants[] = $varid->catalog_object_id;
														} else {
															$miss_matched_variants[]                               = $variation['id'];
															$miss_matched_variantions[ $variation['id'] ]          = $variation;
															$miss_matched_variantions[ $variation['id'] ]['stock'] = $child_product_meta['_stock'][0];
														}
													}

													if ( $inventorycount > $square_inventory_count && $inventorycount === $cn ) {
														$newly_variants = array_unique( array_diff( $miss_matched_variants, $matched_variants ) );

														foreach ( $newly_variants as $newvariat ) {
															$this->update_inventory( $miss_matched_variantions[ $newvariat ], $miss_matched_variantions[ $newvariat ]['stock'], 'RECEIVE_STOCK', $woo_square_location_id );
														}
													}
												} else {
													// for first time update stock.

													$this->update_inventory( $variation, $child_product_meta['_stock'][0], 'RECEIVE_STOCK', $woo_square_location_id );
												}
											}
										}

										if ( $upload_image ) {
											if ( has_post_thumbnail( $child_product->ID ) ) {
												$var_square_id  = $variation['id'];
												$var_image_file = get_attached_file( get_post_thumbnail_id( $child_product->ID ) );
												$var_image_name = basename( $var_image_file );

												if ( $_product->is_type( 'simple' ) ) {
													$var_result = $this->upload_var_image( $response['id'], $var_image_file, $var_image_name, $child_product->ID );
												} if ( $_product->is_type( 'variable' ) ) {
													$var_result = $this->upload_var_image( $var_square_id, $var_image_file, $var_image_name, $child_product->ID );
												}

												// make the response equal image response to be logged in error.
												// message field.
												if ( true !== $var_result ) {
													400 === $http_status;
													$var_response = $var_result;
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

										if ( ! empty( $square_inventory ) && 'PUT' === $request ) {
											$varid = $square_inventory;

											if ( isset( $varid[0] ) && isset( $varid[0]->catalog_object_id ) && $varid[0]->catalog_object_id === $variation['id'] ) {
												if ( $varid[0]->quantity < $product_stock ) {
													$stock   = $product_stock - $varid[0]->quantity;
													$adjtype = 'RECEIVE_STOCK';
													$this->update_inventory( $variation, $stock, $adjtype, $woo_square_location_id );
												} elseif ( $varid[0]->quantity > $product_stock ) {
													$adjtype = 'SALE';
													$stock   = $varid[0]->quantity - $product_stock;
													$this->update_inventory( $variation, $stock, $adjtype, $woo_square_location_id );
												}
											}
										} else {
											$adjtype = 'RECEIVE_STOCK';

											$this->update_inventory( $variation, $product_stock, $adjtype, $woo_square_location_id );
										}
									}
								}
							}
						}

						if ( $upload_image ) {
							if ( has_post_thumbnail( $product->ID ) ) {
								$product_square_id = $response['id'];
								$image_file        = get_attached_file( get_post_thumbnail_id( $product->ID ) );
								$image_name        = basename( $image_file );

								$image_upload_result = $this->upload_image( $product_square_id, $image_file, $image_name, $product->ID, true );
								// make the response equal image response to be logged in error.
								// message field.
								if ( true !== $image_upload_result ) {
									400 === $http_status;
								}
							}

							// Upload woocomerce gallery images.
							$gallery_images = get_post_meta( $product->ID, '_product_image_gallery', true );
							if ( ! empty( $gallery_images ) ) {
								$gallery_images = explode( ',', $gallery_images );
								foreach ( $gallery_images as $gallery_image ) {
									$gallery_image_file = get_attached_file( $gallery_image );
									$gallery_image_name = basename( $gallery_image_file );
									$this->upload_image( $product_square_id, $gallery_image_file, $gallery_image_name, $product->ID, false );
								}
							}
						}
					}

					if ( $_product->is_type( 'booking' ) ) {
						return $responsesync;
					} else {
						$dddd = array(
							'id'         => $product->ID,
							'status'     => true,
							'pro_status' => $prod_cri,
							'message'    => __( 'Successfully sync', 'woosquare' ),
						);

						return ( 200 === $responsesync['response']['code'] ) ? $dddd : $responsesync;
					}
				}
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

		$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/object/' . $product_square_id;

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
	 * Uploads an image to the Square API and associates it with a WooCommerce product.
	 *
	 * This function handles the upload of an image file to the Square API. It sets the appropriate
	 * headers and constructs a multipart form data request to send the image. Depending on whether
	 * the image is primary or not, it updates the corresponding WooCommerce product metadata with
	 * the Square image ID.
	 *
	 * @param string $product_square_id  The Square product ID to associate the image with.
	 * @param string $image_file         The path to the image file to upload.
	 * @param string $image_name         The path to the image name to upload.
	 * @param int    $product_woo_id     The WooCommerce product ID to update with the image data.
	 * @param bool   $primary            Optional. Whether the image is the primary product image. Default false.
	 *
	 * @return mixed  True on success, or the response array from the API on failure.
	 */
	public function upload_image( $product_square_id, $image_file, $image_name, $product_woo_id, $primary = false ) {

		$prepared = $this->square_prepare_image_upload( $image_file );
		if ( ! $prepared ) {
			return false;
		}

		$headers = array(
			'accept'         => 'application/json',
			'content-type'   => 'multipart/form-data; boundary="boundary"',
			'Square-Version' => '2024-11-20',
			'Authorization'  => 'Bearer ' . $this->square->get_access_token(),
		);

		$body  = '--boundary' . "\r\n";
		$body .= 'Content-Disposition: form-data; name="request"' . "\r\n";
		$body .= 'Content-Type: application/json' . "\r\n\r\n";

		$request = array(
			'idempotency_key' => uniqid(),
			'is_primary'      => $primary,
			'image'           => array(
				'type'       => 'IMAGE',
				'id'         => '#TEMP_ID',
				'image_data' => array(
					'caption' => '',
					'name'    => $prepared['label'],
				),
			),
		);
		if ( $product_square_id ) {
			$request['object_id'] = $product_square_id;
		}

		$body     .= wp_json_encode( $request );
		$body     .= "\r\n";
		$body     .= '--boundary' . "\r\n";
		$body     .= 'Content-Disposition: form-data; name="file"; filename="' . esc_attr( $prepared['filename'] ) . '"' . "\r\n";
		$body     .= 'Content-Type: ' . $prepared['mime'] . "\r\n\r\n";
		$body     .= $prepared['body'] . "\r\n";
		$body     .= '--boundary--';
		$url       = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/images';
		$responses = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $responses ) ) {
			return $responses;
		}

		$response  = json_decode( wp_remote_retrieve_body( $responses ), true );
		$http_code = wp_remote_retrieve_response_code( $responses );

		if ( isset( $response['image']['id'] ) ) {
			if ( $primary ) {
				update_post_meta( $product_woo_id, 'square_master_img_id', $response['image']['id'] );
				update_post_meta( $product_woo_id, '_woo_square_attachment_id', $response['image']['id'] );
			} else {
				$k = get_post_meta(
					$product_woo_id,
					'square_gallery_img_ids',
					true
				);

				if ( empty( $k ) ) {
					$k = array();
				}

				$k[] = $response['image']['id'];
				update_post_meta(
					$product_woo_id,
					'square_gallery_img_ids',
					$k
				);
			}
		}

		return 200 === $http_code ? true : $response;
	}

	/**
	 * Uploads a variation image to Square and updates WooCommerce with the image ID.
	 *
	 * This function handles the upload of an image file to the Square API. It constructs
	 * a multipart form-data request, sends it to Square, and updates the WooCommerce product
	 * with the returned image ID from Square.
	 *
	 * @param string $var_square_id The Square ID of the variation (if it exists).
	 * @param string $var_image_file The file path of the image to be uploaded.
	 * @param int    $var_image_name The WooCommerce product ID associated with the variation.
	 * @param int    $var_product_woo_id The WooCommerce product ID associated with the variation.
	 *
	 * @return mixed Returns true if the upload is successful, or the response array on failure.
	 */
	public function upload_var_image( $var_square_id, $var_image_file, $var_image_name, $var_product_woo_id ) {

		$prepared = $this->square_prepare_image_upload( $var_image_file );
		if ( ! $prepared ) {
			return false;
		}

		$headers = array(
			'accept'         => 'application/json',
			'content-type'   => 'multipart/form-data; boundary="boundary"',
			'Square-Version' => '2025-05-21',
			'Authorization'  => 'Bearer ' . get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ),
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
					'name'    => $prepared['label'],
				),
			),
		);
		if ( $var_square_id ) {
			$request['object_id']  = $var_square_id;
			$request['is_primary'] = true;
		}

		$body     .= wp_json_encode( $request );
		$body     .= "\r\n";
		$body     .= '--boundary' . "\r\n";
		$body     .= 'Content-Disposition: form-data; name="file"; filename="' . esc_attr( $prepared['filename'] ) . '"' . "\r\n";
		$body     .= 'Content-Type: ' . $prepared['mime'] . "\r\n\r\n";
		$body     .= $prepared['body'] . "\r\n";
		$body     .= '--boundary--';
		$url       = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/images';
		$responses = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $responses ) ) {
			return $responses;
		}

		$response  = json_decode( wp_remote_retrieve_body( $responses ), true );
		$http_code = wp_remote_retrieve_response_code( $responses );

		if ( isset( $response['image']['id'] ) ) {
			update_post_meta( $var_product_woo_id, 'square_var_img_id', $response['image']['id'] );
			update_post_meta( $var_product_woo_id, '_woo_square_attachment_id', $response['image']['id'] );
		}

		return 200 === $http_code ? true : $response;
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
	 * @param string $adjustment_type The adjustment type (RECEIVE_STOCK or SALE).
	 * @param string $woo_square_location_id The Square location ID.
	 *
	 * @return array The response from the Square API.
	 */
	public function update_inventory( $variations, $stock, $adjustment_type = 'RECEIVE_STOCK', $woo_square_location_id = null ) {

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
		$url         = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/inventory/batch-change';
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
		$query       = "
		SELECT tax.term_id AS term_id, term.name AS name, meta.option_value
		FROM {$wpdb->prefix}term_taxonomy as tax
		JOIN {$wpdb->prefix}terms as term ON (tax.term_id = term.term_id)
		LEFT JOIN {$wpdb->prefix}options AS meta ON (meta.option_name = concat('is_square_sync_',term.term_id))
		where tax.taxonomy = 'product_cat'
		AND ( (meta.option_value = '1') OR (meta.option_value is NULL) )
		GROUP BY tax.term_id";
		$get_results = 'get_results';
		return $wpdb->$get_results( $query, OBJECT );
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
		$get_results                 = 'get_results';
		$results                     = $wpdb->$get_results( $categories_square_ids_query, OBJECT );

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
		$query       = "
		SELECT *
		FROM {$wpdb->prefix}posts AS posts
		LEFT JOIN {$wpdb->prefix}postmeta AS meta ON (posts.ID = meta.post_id AND meta.meta_key = 'is_square_sync')
		where posts.post_type = 'product'
		AND posts.post_status = 'publish'
		GROUP BY posts.ID";
		$get_results = 'get_results';
		return $wpdb->$get_results( $query, OBJECT );
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
		$get_results            = 'get_results';
		$results                = $wpdb->$get_results( $posts_square_ids_query, OBJECT );
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
		$get_results  = 'get_results';
		$deleted_elms = $wpdb->$get_results( $query, OBJECT );
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
			if ( isset( $item->variations ) ) {
				foreach ( $item->variations as $variation ) {
					if ( isset( $variation->sku ) ) {
						$square_items_modified[ $variation->sku ] = $item;
					}
				}
			}
		}
		return $square_items_modified;
	}
}
