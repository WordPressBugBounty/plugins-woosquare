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
 * Synchronize From Square To WooCommerce Class
 */
class SquareToWooSynchronizer {

	/**
	 * Instance of square class.
	 *
	 * @var square square class instance
	 */
	protected $square;

	/**
	 * Object of square class.
	 *
	 * @param object $square object of square class.
	 */
	public function __construct( $square ) {

		include_once plugin_dir_path( __DIR__ ) . '_inc/class-helpers.php';
		$this->square = $square;
	}

	/**
	 * Sync Square products to WooCommerce.
	 *
	 * This function retrieves products from Square and syncs them with WooCommerce.
	 * It processes the products in batches and makes asynchronous HTTP requests to
	 * the admin-ajax.php endpoint to handle the synchronization.
	 *
	 * @return void
	 */
	public function sync_square_products_to_woo() {
		$square_items = $this->get_square_items();

		if ( ! $square_items ) {
			return;
		}

		$woo_square_listsaved_products_square = get_option( 'woo_square_listsaved_products_square' );
		$woo_square_sync_preference           = get_option( 'woo_square_sync_preference' );
		$woo_square_auto_sync                 = get_option( 'woo_square_auto_sync' );

		$filtered_items = array();

		if ( is_array( $square_items ) ) {
			foreach ( $square_items as $square_product ) {

				// Apply sync preference condition.
				if ( 0 === (int) $woo_square_sync_preference && 1 === (int) $woo_square_auto_sync ) {
					if ( ! in_array( $square_product->id, $woo_square_listsaved_products_square, true ) ) {

						continue; // Skip this item.
					}
				}

				// Passed all checks — include it.
				$filtered_items[] = $square_product;
				$square_items     = $filtered_items;
			}
		}

		$batch_size      = 7; // 10 items per batch.
		$total_items     = count( $square_items );
		$processed_items = 0;

		// Create a nonce.
		$nonce = wp_create_nonce( 'square_sync_nonce' );

		foreach ( array_chunk( $square_items, $batch_size ) as $batch ) {

			$args = array(
				'timeout'  => 30,
				'blocking' => true,
				'headers'  => array(
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw cookie header is required for session context.
					'Cookie' => isset( $_SERVER['HTTP_COOKIE'] ) ? wp_unslash( $_SERVER['HTTP_COOKIE'] ) : '',
				),
				'body'     => array(
					'action'            => 'square_sync_remote',
					'batch'             => wp_json_encode( $batch ),
					'square_sync_nonce' => $nonce,
				),
			);

			$response = wp_remote_post( admin_url( 'admin-ajax.php' ), $args );
			if ( is_wp_error( $response ) ) {
				$processed_items += $batch_size;
				sleep( 2 ); // 2 sec delay taake server overload na ho.
				continue;
			}

			$processed_items += $batch_size;
			sleep( 2 ); // 2 sec delay taake server overload na ho.
		}

		if ( ! get_option( 'disable_auto_delete' ) ) {
			$this->delete_woocommerce_products_not_in_square( $this->get_square_items() );
		}
	}

	/**
	 * Sync All products, categories from Square to Woo-Commerce
	 */
	public function sync_from_square_to_woo() {

		$sync_type      = Helpers::SYNC_TYPE_AUTOMATIC;
		$sync_direction = Helpers::SYNC_DIRECTION_SQUARE_TO_WOO;

		/* get all categories */
		$square_categories = $this->get_square_categories();

		/* get all items */
		$square_items = $this->get_square_items();

		// 1- Update WooCommerce with categories from Square.
		$synch_square_ids = array();
		if ( ! empty( $square_categories ) ) {
			// get previously linked categories to woo.
			$woo_square_cats = $this->get_unsync_woo_square_categories_ids( $square_categories, $synch_square_ids );
		} else {
			$square_categories = array();
			$woo_square_cats   = array();
		}

		$woo_square_sync_preference = get_option( 'woo_square_sync_preference' );
		$woo_square_auto_sync       = get_option( 'woo_square_auto_sync' );

		$woo_square_listsaved_categories_square = get_option( 'woo_square_listsaved_categories_square' );

		if ( empty( $woo_square_listsaved_categories_square ) ) {
			$woo_square_listsaved_categories_square = array();
		}

		// add/update square categories.
		foreach ( $square_categories as $cat ) {

			if ( 0 === (int) $woo_square_sync_preference && 1 === (int) $woo_square_auto_sync ) {
				if ( ! in_array( $cat->id, $woo_square_listsaved_categories_square, true ) ) {
					continue;
				}
			}

			if ( isset( $woo_square_cats[ $cat->id ] ) ) {  // update.

				// do not update if it is already updated ( its id was returned.
				// in $synch_square_ids array ).
				if ( in_array( $woo_square_cats[ $cat->id ][0], $synch_square_ids, true ) ) {
					continue;
				}

				$result = $this->update_woo_category(
					$cat,
					$woo_square_cats[ $cat->id ][0]
				);
				if ( false !== $result['status'] ) {
					update_option( "is_square_sync_{$result['id']}", 1 );
				}
				$target_id = $woo_square_cats[ $cat->id ][0];
				$action    = Helpers::ACTION_UPDATE;

			} else { // add.
				$result = $this->add_category_to_woo( $cat );
				if ( false !== $result['status'] ) {
					update_option( "is_square_sync_{$result['id']}", 1 );
					$target_id = $result['id'];
					$result    = true;

				}
				$action = Helpers::ACTION_ADD;
			}
		}

		$woo_square_listsaved_products_square = get_option( 'woo_square_listsaved_products_square' );

		if ( empty( $woo_square_listsaved_products_square ) ) {
			$woo_square_listsaved_products_square = array();
		}
		// 2-Update WooCommerce with products from Square.

		$this->sync_square_products_to_woo();
	}

	/**
	 * Add WooCommerce category from Square
	 *
	 * @param object $category category square object.
	 * @return int|false created category id, false in case of error
	 */
	public function add_category_to_woo( $category ) {

		if ( empty( $category->category_data ) && ! empty( $category->name ) ) {
			$category->category_data          = (object) array(
				'id' => $category->id,
			);
			$category->category_data->name    = $category->name;
			$category->category_data->version = $category->version;
		} else {
			$category->category_data->id      = $category->id;
			$category->category_data->version = $category->version;
		}

		$ret_val = false;
		$slug    = $category->category_data->name;
		remove_action( 'edited_product_cat', 'woo_square_edit_category' );
		remove_action( 'create_product_cat', 'woo_square_add_category' );
		$term = get_term_by( 'name', $category->category_data->name, 'product_cat' );

		if ( $term && ! is_wp_error( $term ) && ! empty( $term->term_id ) ) {
			$ret_val = $term->term_id;

			update_option( 'category_square_id_' . $term->term_id, $category->category_data->id );
			update_option( 'category_square_version_' . $term->term_id, $category->category_data->version );
			$ret_val = $term->term_id;
			$dddd    = array(
				'id'         => $ret_val,
				'item'       => 'category',
				'status'     => true,
				'pro_status' => 'add',
				'message'    => __( 'Successfully updated', 'woosquare' ),
			);
		} else {
			$result = wp_insert_term( $category->category_data->name, 'product_cat', array( 'slug' => $slug ) );

			if ( ! is_wp_error( $result ) && isset( $result['term_id'] ) ) {
				if ( ! empty( $category->id ) && ! empty( $category->version ) ) {
					update_option( 'category_square_id_' . $result['term_id'], $category->id );
					update_option( 'category_square_version_' . $result['term_id'], $category->version );
					$ret_val = $result['term_id'];
				} else {
					update_option( 'category_square_id_' . $result['term_id'], $category->category_data->id );
					update_option( 'category_square_version_' . $result['term_id'], $category->category_data->version );
					$ret_val = $result['term_id'];
				}
				$dddd = array(
					'id'         => $ret_val,
					'item'       => 'category',
					'status'     => true,
					'pro_status' => 'add',
					'message'    => __( 'Successfully sync', 'woosquare' ),
				);
			} elseif ( is_numeric( $result->error_data['term_exists'] ) ) {
					$ret_val = $result->error_data['term_exists'];
				if ( ! empty( $category->id ) && ! empty( $category->version ) ) {
					update_option( 'category_square_id_' . $ret_val, $category->id );
					update_option( 'category_square_version_' . $ret_val, $category->version );
				} else {
					update_option( 'category_square_id_' . $ret_val, $category->category_data->id );
					update_option( 'category_square_version_' . $ret_val, $category->category_data->version );
				}
					$dddd = array(
						'id'         => $ret_val,
						'item'       => 'category',
						'status'     => false,
						'pro_status' => 'failed',
						'message'    => $result->errors['term_exists'][0],
					);
			}
		}

		add_action( 'edited_product_cat', 'woo_square_edit_category' );
		add_action( 'create_product_cat', 'woo_square_add_category' );

		return $dddd;
	}

	/**
	 * Resolve Woo product_cat term for a Square product category.
	 *
	 * WP-944: Product Sync only must still assign the Square category on the Woo product.
	 * If the category term does not exist yet (Category Sync unchecked), create/map it here.
	 *
	 * @param object $square_product Square product object.
	 * @return int WooCommerce term ID, or 0 when unavailable.
	 */
	public function resolve_woo_category_term_id_for_square_product( $square_product ) {
		if ( empty( $square_product->category ) || empty( $square_product->category->name ) ) {
			return 0;
		}

		$square_cat_id = '';
		if ( ! empty( $square_product->category->v2_id ) ) {
			$square_cat_id = $square_product->category->v2_id;
		} elseif ( ! empty( $square_product->category->id ) && is_string( $square_product->category->id ) ) {
			$square_cat_id = $square_product->category->id;
		} elseif ( ! empty( $square_product->category_id ) ) {
			$square_cat_id = $square_product->category_id;
		}

		$wp_category = get_term_by( 'name', $square_product->category->name, 'product_cat' );
		if ( $wp_category && ! is_wp_error( $wp_category ) && ! empty( $wp_category->term_id ) ) {
			if ( ! empty( $square_cat_id ) ) {
				update_option( 'category_square_id_' . (int) $wp_category->term_id, $square_cat_id );
			}
			return (int) $wp_category->term_id;
		}

		$category_obj = (object) array(
			'id'      => $square_cat_id,
			'name'    => $square_product->category->name,
			'version' => ! empty( $square_product->category->version ) ? $square_product->category->version : '',
		);

		$result = $this->add_category_to_woo( $category_obj );
		if ( is_array( $result ) && ! empty( $result['id'] ) ) {
			return (int) $result['id'];
		}

		return 0;
	}

	/**
	 * Updates a WooCommerce category.
	 *
	 * This function takes a Square category object and a WooCommerce category ID as parameters.
	 * It then updates the WooCommerce category with the new information from Square.
	 *
	 * @param object $category A Square category object.
	 * @param int    $cat_id A WooCommerce category ID.
	 *
	 * @return bool True if the category was updated successfully, false otherwise.
	 */
	public function update_woo_category( $category, $cat_id ) {

		remove_action( 'edited_product_cat', 'woo_square_edit_category' );
		remove_action( 'create_product_cat', 'woo_square_add_category' );
		if ( isset( $category->category_data->name ) ) {
			$slug = $category->category_data->name;
			wp_update_term(
				$cat_id,
				'product_cat',
				array(
					'name' => $category->category_data->name,
					'slug' => $slug,
				)
			);
		} else {
			$slug = $category->name;
			wp_update_term(
				$cat_id,
				'product_cat',
				array(
					'name' => $category->name,
					'slug' => $slug,
				)
			);
		}
		update_option( 'category_square_id_' . $cat_id, $category->id );

		update_option( 'category_square_version_' . $cat_id, $category->version );

		add_action( 'edited_product_cat', 'woo_square_edit_category' );
		add_action( 'create_product_cat', 'woo_square_add_category' );

		$dddd = array(
			'id'         => $cat_id,
			'item'       => 'category',
			'status'     => true,
			'pro_status' => 'update',
			'message'    => __( 'Successfully sync', 'woosquare' ),
		);
		return $dddd;
	}

	/**
	 * Deletes a WooCommerce category.
	 *
	 * This function takes a Square category object and a WooCommerce category ID as parameters.
	 * It then updates the WooCommerce category with the new information from Square.
	 *
	 * @param object $category A Square category object.
	 *
	 * @return bool True if the category was updated successfully, false otherwise.
	 */
	public function delete_woo_category( $category ) {

		$cat_id = $category->id;

		$result = wp_delete_term( $cat_id, 'product_cat' );

		if ( true === $result ) {
			// delete category options.
			delete_option( "is_square_sync_{$cat_id}" );
			delete_option( "category_square_id_{$cat_id}" );
		}
		$dddd = array(
			'id'         => $cat_id,
			'item'       => 'category',
			'status'     => true,
			'pro_status' => 'delete',
			'message'    => __( 'Successfully Deleted', 'woosquare' ),
		);
		return $dddd;
	}

	/**
	 * Adds a product to WooCommerce.
	 *
	 * This function takes a Square product object, a Square inventory object, and an action variable as parameters.
	 * It then checks to see if the product already exists in WooCommerce based on its SKU. If it does, the function updates the product with the new information from Square.
	 * If the product does not exist, the function creates a new product in WooCommerce.
	 *
	 * @param object $square_product A Square product object.
	 * @param object $square_inventory A Square inventory object.
	 * @param string $action The action to take, either "add" or "update".
	 *
	 * @return int The WooCommerce product ID, or false if the product could not be inserted.
	 */
	public function add_product_to_woo( $square_product, $square_inventory, &$action = false ) {
		$id = false;

		if ( is_array( $square_product ) ) {
			$square_product = json_decode( wp_json_encode( $square_product ) );
		}

		if ( ! empty( $square_product->variations ) ) {
			if ( count( $square_product->variations ) <= 1 ) {
				if ( isset( $square_product->variations[0] ) && isset( $square_product->variations[0]->item_variation_data->sku ) && $square_product->variations[0]->item_variation_data->sku ) {
					$square_product_sku         = $square_product->variations[0]->item_variation_data->sku;
					$product_id_with_sku_exists = $this->check_if_product_with_sku_exists( $square_product_sku, array( 'product', 'product_variation' ) );

					if ( $product_id_with_sku_exists ) { // SKU already exists in other product.
						$product   = get_post( $product_id_with_sku_exists[0] );
						$parent_id = $product->post_parent;

						$id = $this->insert_simple_product_to_woo( $square_product, $square_inventory, $product_id_with_sku_exists[0] );

						if ( $parent_id ) {
							if ( ! get_option( 'disable_auto_delete' ) ) {
									$this->delete_product_from_woo( $product->post_parent );
							}
						}
						$action = Helpers::ACTION_UPDATE;
					} else {
						$id     = $this->insert_simple_product_to_woo( $square_product, $square_inventory );
						$action = Helpers::ACTION_ADD;
					}
				} else {

					$id     = false;
					$action = null;

				}
			} else {
				// Variable square product.
				$id = $this->insert_variable_product_to_woo( $square_product, $square_inventory, $action );
			}
		}

		if ( ! empty( $square_product->modifier_list_info ) && ! empty( $id ) ) {

			if ( count( $square_product->modifier_list_info ) >= 1 ) {

				// FIX: Extract product ID from array if $id is array
				$product_id = is_array( $id ) && isset( $id['id'] ) ? $id['id'] : $id;
				woo_square_plugin_sync_square_modifier_to_woo( $product_id, $square_product );

			}
		}

		return $id;
	}

	/**
	 * Get all custom attribute definitions from Square API.
	 *
	 * @return array Array of custom attribute definitions with key => name mapping.
	 */
	public function get_square_custom_attribute_definitions() {
		$token   = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$url     = esc_url( 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/list' );
		$headers = array(
			'Authorization'  => 'Bearer ' . $token,
			'Content-Type'   => 'application/json',
			'Square-Version' => '2024-03-20',
		);

		$method   = 'GET';
		$args     = array( 'types' => 'CUSTOM_ATTRIBUTE_DEFINITION' );
		$square   = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
		$response = array();
		$response = $square->wp_remote_woosquare( $url, $args, $method, $headers, $response );

		$custom_attr_list = json_decode( $response['body'], true );

		$attribute_definitions = array();
		$selection_mappings    = array(); // Store selection_uid => name mappings
		
		if ( ! empty( $custom_attr_list ) && is_array( $custom_attr_list ) ) {
			foreach ( $custom_attr_list as $custom_attr ) {
				if ( isset( $custom_attr['custom_attribute_definition_data'] ) ) {
					$key  = isset( $custom_attr['custom_attribute_definition_data']['key'] ) ? $custom_attr['custom_attribute_definition_data']['key'] : '';
					$name = isset( $custom_attr['custom_attribute_definition_data']['name'] ) ? $custom_attr['custom_attribute_definition_data']['name'] : $key;
					$type = isset( $custom_attr['custom_attribute_definition_data']['type'] ) ? $custom_attr['custom_attribute_definition_data']['type'] : '';
					
					// Skip custom_sale_price as it's handled separately
					if ( 'custom_sale_price' !== $key && ! empty( $key ) ) {
						$attribute_definitions[ $key ] = array(
							'name' => $name,
							'type' => $type,
						);
						
						// For SELECTION type, store selection mappings
						if ( 'SELECTION' === $type && isset( $custom_attr['custom_attribute_definition_data']['selection_config']['allowed_selections'] ) ) {
							$selection_mappings[ $key ] = array();
							foreach ( $custom_attr['custom_attribute_definition_data']['selection_config']['allowed_selections'] as $selection ) {
								$uid            = isset( $selection['uid'] ) ? $selection['uid'] : '';
								$selection_name = isset( $selection['name'] ) ? $selection['name'] : '';
								if ( ! empty( $uid ) && ! empty( $selection_name ) ) {
									$selection_mappings[ $key ][ $uid ] = $selection_name;
								}
							}
						}
					}
				}
			}
		}

		// Store selection mappings in a transient or return with definitions
		set_transient( 'square_custom_attr_selections', $selection_mappings, 3600 );

		return $attribute_definitions;
	}

	/**
	 * Process custom attributes from Square product variations and convert to WooCommerce format.
	 *
	 * @param object $square_product Square product object.
	 * @param int    $product_id WooCommerce product ID.
	 * @return array WooCommerce product attributes array.
	 */
	/**
	 * Fetch Square item with custom attributes included.
	 *
	 * @param string $square_item_id Square item ID.
	 * @return object|false Square item object with custom attributes or false on failure.
	 */
	private function fetch_square_item_with_custom_attributes( $square_item_id ) {
		$token   = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$url     = esc_url( 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/object/' . $square_item_id );
		$headers = array(
			'Authorization'  => 'Bearer ' . $token,
			'Content-Type'   => 'application/json',
			'Square-Version' => '2024-03-20',
		);

		$args = array(
			'include_related_objects' => true,
		);

		$square   = new Square( $token, get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
		$method   = 'GET';
		$response = array();
		$response = $square->wp_remote_woosquare( $url, $args, $method, $headers, $response );

		if ( ! empty( $response['body'] ) ) {
			$item_data = json_decode( $response['body'], true );

			if ( ! empty( $item_data['object'] ) ) {
				$item_object = json_decode( wp_json_encode( $item_data['object'] ) );
				return $item_object;
			}
		}

		return false;
	}

	public function process_square_custom_attributes( $square_product, $_product_id ) {
		unset( $_product_id );
		// Try to fetch full item data with custom attributes if not present
		$square_item_id = isset( $square_product->id ) ? $square_product->id : '';
		if ( ! empty( $square_item_id ) ) {
			$full_item_data = $this->fetch_square_item_with_custom_attributes( $square_item_id );
			if ( $full_item_data ) {
				// Merge custom attributes from fetched data - check object level first
				if ( isset( $full_item_data->custom_attribute_values ) ) {
					$square_product->custom_attribute_values = $full_item_data->custom_attribute_values;
				} elseif ( isset( $full_item_data->item_data ) && isset( $full_item_data->item_data->custom_attribute_values ) ) {
					$square_product->custom_attribute_values = $full_item_data->item_data->custom_attribute_values;
				}
				// Also check variations
				if ( isset( $full_item_data->item_data ) && isset( $full_item_data->item_data->variations ) && is_array( $full_item_data->item_data->variations ) ) {
					foreach ( $full_item_data->item_data->variations as $idx => $fetched_var ) {
						if ( isset( $square_product->variations[ $idx ] ) && isset( $fetched_var->item_variation_data->custom_attribute_values ) ) {
							if ( ! isset( $square_product->variations[ $idx ]->item_variation_data ) ) {
								$square_product->variations[ $idx ]->item_variation_data = new stdClass();
							}
							$square_product->variations[ $idx ]->item_variation_data->custom_attribute_values = $fetched_var->item_variation_data->custom_attribute_values;
						}
					}
				}
			}
		}

		$woo_attributes            = array();
		$attribute_definitions_raw = $this->get_square_custom_attribute_definitions();
		$selection_mappings        = get_transient( 'square_custom_attr_selections' );
		if ( false === $selection_mappings ) {
			$selection_mappings = array();
		}

		// Process item level custom attributes first
		// Check multiple possible locations
		$item_custom_attrs = null;
		if ( isset( $square_product->custom_attribute_values ) ) {
			$item_custom_attrs = $square_product->custom_attribute_values;
		} elseif ( isset( $square_product->item_data ) && isset( $square_product->item_data->custom_attribute_values ) ) {
			$item_custom_attrs = $square_product->item_data->custom_attribute_values;
		}

		if ( ! empty( $item_custom_attrs ) ) {
			
			// Handle both object and array formats
			if ( is_object( $item_custom_attrs ) ) {
				$item_custom_attrs = (array) $item_custom_attrs;
			}

			foreach ( $item_custom_attrs as $attr_key => $attr_value ) {
				// Skip custom_sale_price as it's handled separately
				if ( strpos( $attr_key, 'custom_sale_price' ) !== false ) {
					continue;
				}

				// Extract actual key from format like "Square:3d7b174e-11d6-4b71-b532-4f3c0d388069"
				$actual_key = $attr_key;
				if ( strpos( $attr_key, ':' ) !== false ) {
					$key_parts  = explode( ':', $attr_key );
					$actual_key = end( $key_parts );
				}

				// Get attribute name from definitions or from value itself
				$attr_name = isset( $attribute_definitions_raw[ $actual_key ]['name'] ) ? $attribute_definitions_raw[ $actual_key ]['name'] : ( isset( $attr_value['name'] ) ? $attr_value['name'] : ucfirst( str_replace( array( 'custom_', '_' ), array( '', ' ' ), $actual_key ) ) );
				$attr_type = isset( $attribute_definitions_raw[ $actual_key ]['type'] ) ? $attribute_definitions_raw[ $actual_key ]['type'] : ( isset( $attr_value['type'] ) ? $attr_value['type'] : '' );

				// Extract value based on type
				$attr_value_str = $this->extract_custom_attribute_value( $attr_value, $actual_key, $attr_type, $selection_mappings );

				if ( ! empty( $attr_value_str ) ) {
					$woo_attributes[ $attr_name ] = array(
						'name'         => $attr_name,
						'value'        => $attr_value_str,
						'is_visible'   => 1,
						'is_variation' => 0,
						'position'     => count( $woo_attributes ),
						'is_taxonomy'  => 0,
					);
				}
			}
		}

		// Process variations to collect custom attributes
		if ( ! empty( $square_product->variations ) && is_array( $square_product->variations ) ) {
			foreach ( $square_product->variations as $variation_index => $variation ) {
				// Check multiple possible locations for custom attributes
				$custom_attrs = null;
				if ( isset( $variation->custom_attribute_values ) ) {
					$custom_attrs = $variation->custom_attribute_values;
				} elseif ( isset( $variation->item_variation_data ) && isset( $variation->item_variation_data->custom_attribute_values ) ) {
					$custom_attrs = $variation->item_variation_data->custom_attribute_values;
				}

				if ( ! empty( $custom_attrs ) ) {
					// Handle both object and array formats
					if ( is_object( $custom_attrs ) ) {
						$custom_attrs = (array) $custom_attrs;
					}

					foreach ( $custom_attrs as $attr_key => $attr_value ) {
						// Skip custom_sale_price as it's handled separately
						if ( 'custom_sale_price' === $attr_key ) {
							continue;
						}

						// Extract actual key from format like "Square:key"
						$actual_key = $attr_key;
						if ( strpos( $attr_key, ':' ) !== false ) {
							$key_parts  = explode( ':', $attr_key );
							$actual_key = end( $key_parts );
						}

						// Get attribute name from definitions
						$attr_name = isset( $attribute_definitions_raw[ $actual_key ]['name'] ) ? $attribute_definitions_raw[ $actual_key ]['name'] : ( isset( $attr_value['name'] ) ? $attr_value['name'] : ucfirst( str_replace( array( 'custom_', '_' ), array( '', ' ' ), $actual_key ) ) );
						$attr_type = isset( $attribute_definitions_raw[ $actual_key ]['type'] ) ? $attribute_definitions_raw[ $actual_key ]['type'] : ( isset( $attr_value['type'] ) ? $attr_value['type'] : '' );

						// Extract value based on type
						$attr_value_str = $this->extract_custom_attribute_value( $attr_value, $actual_key, $attr_type, $selection_mappings );

						if ( ! empty( $attr_value_str ) ) {
							// Check if attribute already exists in array (for multiple variations)
							if ( isset( $woo_attributes[ $attr_name ] ) ) {
								// Append value if different
								$existing_values = explode( ' | ', $woo_attributes[ $attr_name ]['value'] );
								if ( ! in_array( $attr_value_str, $existing_values, true ) ) {
									$woo_attributes[ $attr_name ]['value'] .= ' | ' . $attr_value_str;
								}
							} else {
								// Create new attribute entry
								$woo_attributes[ $attr_name ] = array(
									'name'         => $attr_name,
									'value'        => $attr_value_str,
									'is_visible'   => 1,
									'is_variation' => 0,
									'position'     => count( $woo_attributes ),
									'is_taxonomy'  => 0,
								);
							}
						}
					}
				}
			}
		}

		return $woo_attributes;
	}

	/**
	 * Extract custom attribute value from Square format.
	 *
	 * @param mixed  $attr_value Attribute value from Square.
	 * @param string $attr_key Attribute key.
	 * @param string $attr_type Attribute type (STRING, NUMBER, BOOLEAN, SELECTION).
	 * @param array  $selection_mappings Selection UID to name mappings.
	 * @return string Extracted attribute value as string.
	 */
	private function extract_custom_attribute_value( $attr_value, $attr_key, $attr_type, $selection_mappings ) {
		$attr_value_str = '';

		// Handle SELECTION type - map selection_uid to name
		if ( 'SELECTION' === $attr_type ) {
			$selection_uids = array();
			
			// Check for selection_uid_values array (multiple selections)
			if ( is_object( $attr_value ) ) {
				if ( isset( $attr_value->selection_uid_values ) && is_array( $attr_value->selection_uid_values ) ) {
					$selection_uids = $attr_value->selection_uid_values;
				} elseif ( isset( $attr_value->selection_uid ) ) {
					$selection_uids = array( $attr_value->selection_uid );
				}
			} elseif ( is_array( $attr_value ) ) {
				if ( isset( $attr_value['selection_uid_values'] ) && is_array( $attr_value['selection_uid_values'] ) ) {
					$selection_uids = $attr_value['selection_uid_values'];
				} elseif ( isset( $attr_value['selection_uid'] ) ) {
					$selection_uids = array( $attr_value['selection_uid'] );
				}
			}

			if ( ! empty( $selection_uids ) && isset( $selection_mappings[ $attr_key ] ) ) {
				$mapped_names = array();
				foreach ( $selection_uids as $selection_uid ) {
					if ( isset( $selection_mappings[ $attr_key ][ $selection_uid ] ) ) {
						$mapped_names[] = $selection_mappings[ $attr_key ][ $selection_uid ];
					}
				}
				
				if ( ! empty( $mapped_names ) ) {
					$attr_value_str = implode( ' | ', $mapped_names );
				}
			}
		} elseif ( is_object( $attr_value ) ) {
			// Handle other types (STRING, NUMBER, BOOLEAN)
			if ( isset( $attr_value->string_value ) ) {
				$attr_value_str = $attr_value->string_value;
			} elseif ( isset( $attr_value->number_value ) ) {
				$attr_value_str = (string) $attr_value->number_value;
			} elseif ( isset( $attr_value->boolean_value ) ) {
				$attr_value_str = $attr_value->boolean_value ? 'Yes' : 'No';
			}
		} elseif ( is_array( $attr_value ) ) {
			if ( isset( $attr_value['string_value'] ) ) {
				$attr_value_str = $attr_value['string_value'];
			} elseif ( isset( $attr_value['number_value'] ) ) {
				$attr_value_str = (string) $attr_value['number_value'];
			} elseif ( isset( $attr_value['boolean_value'] ) ) {
				$attr_value_str = $attr_value['boolean_value'] ? 'Yes' : 'No';
			}
		} else {
			$attr_value_str = (string) $attr_value;
		}

		return $attr_value_str;
	}

	/**
	 * Get Square options with caching support.
	 *
	 * @param array $options Options array with item_option_id.
	 * @return array|false Square options data or false on failure.
	 */
	public function get_square_options( $options ) {
		if ( ! isset( $options['item_option_id'] ) || empty( $options['item_option_id'] ) ) {
			return false;
		}

		$option_id = $options['item_option_id'];

		// Check cache first (static cache for current request)
		static $options_cache = array();
		if ( isset( $options_cache[ $option_id ] ) ) {
			return $options_cache[ $option_id ];
		}

		$token   = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$url     = esc_url( 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/object/' . $option_id );
		$headers = array(
			'Authorization'  => 'Bearer ' . $token,
			'Content-Type'   => 'application/json',
			'Square-Version' => '2024-03-20',
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers' => $headers,
				'method'  => 'GET',
				'timeout' => 10,
			),
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$create_custom_attr = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $create_custom_attr['object'] ) ) {
			$square_options = $create_custom_attr['object']['item_option_data'];
			// Cache the result
			$options_cache[ $option_id ] = $square_options;
			return $square_options;
		}

		return false;
	}

	/**
	 * Batch fetch Square options for multiple option IDs.
	 * Uses static caching and sequential requests (optimized for WordPress).
	 *
	 * @param array $option_ids Array of option IDs to fetch.
	 * @return array Associative array with option_id as key and option data as value.
	 */
	public function get_square_options_batch( $option_ids ) {
		if ( empty( $option_ids ) || ! is_array( $option_ids ) ) {
			return array();
		}

		// Remove duplicates and filter empty values
		$option_ids = array_filter( array_unique( array_map( 'trim', $option_ids ) ) );

		if ( empty( $option_ids ) ) {
			return array();
		}

		// Check static cache first (for current request)
		static $batch_cache = array();
		$cached_results     = array();
		$uncached_ids       = array();

		foreach ( $option_ids as $option_id ) {
			if ( isset( $batch_cache[ $option_id ] ) ) {
				$cached_results[ $option_id ] = $batch_cache[ $option_id ];
			} else {
				$uncached_ids[] = $option_id;
			}
		}

		// If all are cached, return immediately
		if ( empty( $uncached_ids ) ) {
			return $cached_results;
		}

		$token   = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$headers = array(
			'Authorization'  => 'Bearer ' . $token,
			'Content-Type'   => 'application/json',
			'Square-Version' => '2024-03-20',
		);

		// Fetch uncached options sequentially (but with reduced overhead)
		$results = array();
		foreach ( $uncached_ids as $option_id ) {
			$url      = esc_url( 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/object/' . $option_id );
			$response = wp_remote_get(
				$url,
				array(
					'headers' => $headers,
					'method'  => 'GET',
					'timeout' => 10,
				),
			);

			if ( ! is_wp_error( $response ) ) {
				$create_custom_attr = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( ! empty( $create_custom_attr['object'] ) && isset( $create_custom_attr['object']['item_option_data'] ) ) {
					$square_options            = $create_custom_attr['object']['item_option_data'];
					$results[ $option_id ]     = $square_options;
					$batch_cache[ $option_id ] = $square_options;
				}
			}
		}

		// Merge cached and new results
		return array_merge( $cached_results, $results );
	}

	/**
	 * Retrieves or creates the custom sale price attribute for WooSquare.
	 *
	 * This function first attempts to retrieve the custom sale price attribute from Square's API.
	 * If the custom sale price attribute does not exist, it creates a new one using the Square API.
	 * The function updates the option 'woosquare_sale_price_custom_attr' with the attribute key and returns it.
	 *
	 * @param string $option_value The option value key to retrieve or create from Square.
	 * @return string The custom sale price attribute key.
	 */
	public function get_square_option_value( $option_value ) {
		$token   = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$url     = esc_url( 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/object/' . $option_value );
		$headers = array(
			'Authorization'  => 'Bearer ' . $token, // Use verbose mode in cURL to determine the format you want for this header.
			'Content-Type'   => 'application/json',
			'Square-Version' => '2024-03-20',
		);

		$method   = 'GET';
		$square   = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );
		$response = array();
		$response = $square->wp_remote_woosquare( $url, $data, $method, $headers, $response );

		$create_custom_attr = json_decode( $response['body'], true );

		if ( ! empty( $create_custom_attr['object'] ) ) {
			$square_option_name = $create_custom_attr['object']['item_option_value_data']['name'];
			return $square_option_name;
		}
	}
	/**
	 * Checks whether the Square option format feature is enabled.
	 *
	 * @param mixed $square_product The Square product object or data to check.
	 * @return bool True if the Square option format is enabled, false otherwise.
	 */
	public function is_enable_square_option_format( $square_product ) {

		// If version is 4.7.1 or later, always return true.
		if ( version_compare( WOOSQUARE_VERSION, '4.7.1', '>=' ) ) {
			return true;
		}

		if (
			isset( $square_product->variations[0]->item_option_values[0] ) &&
			is_object( $square_product->variations[0]->item_option_values[0] )
		) {
			foreach ( $square_product->variations as &$variation ) {
				if ( isset( $variation->item_option_values ) && is_array( $variation->item_option_values ) ) {
					foreach ( $variation->item_option_values as &$iov ) {
						if ( is_object( $iov ) ) {
							$iov = (array) $iov;
						}
					}
				}
			}
		}
		$is_enable_square_option_format = false;

		if ( get_option( 'enable_woosquare_new_variation_format' ) === false || get_option( 'enable_woosquare_new_variation_format' ) === '1' ) {
				return true;
		} else {

			$existing = null;

			if (
				isset( $square_product->variations[0]->item_option_values[0] ) &&
				is_array( $square_product->variations[0]->item_option_values[0] ) &&
				isset( $square_product->variations[0]->item_option_values[0]['item_option_id'] )
			) {
				$existing = $square_product->variations[0]->item_option_values[0]['item_option_id'];
			}
			return ! empty( $existing );
		}
	}

	/**
	 * Create a variable WooCommerce product.
	 *
	 * @param string      $square_product  The object of the product.
	 * @param string      $desc            The product description.
	 * @param array       $cats            An array of product categories.
	 * @param array       $variations      An array of product variations.
	 * @param string      $variations_key  The key for variations.
	 * @param int|null    $product_square_id The Square product ID.
	 * @param object|null $master_image    The master image for the product.
	 * @param int|null    $parent_id       The ID of the parent product (if this is a variation).
	 *
	 * @return int The ID of the newly created product.
	 */
	public function create_variable_woo_product( $square_product, $desc, $cats = array(), $variations = null, $variations_key = null, $product_square_id = null, $master_image = null, $parent_id = null ) {
		$title = $square_product->name;
		if ( is_array( $variations ) && isset( $variations[0] ) && ! empty( $variations[0] ) ) {
			if ( isset( $variations[0]['new_option_var'] ) && $this->is_enable_square_option_format( $square_product ) ) {
				foreach ( $variations[0]['new_option_var'] as $var_key => $new_custom_var ) {
					$variations_key = $var_key;
				}
			} else {
				$varkey         = explode( '[', $variations[0]['name'] );
				$variations_key = $varkey[0];
			}
		}
		$post = array(
			'post_title'   => $title,
			'post_content' => $desc,
			'post_status'  => 'publish',
			'post_name'    => sanitize_title( $title ), // name/slug.
			'post_type'    => 'product',
		);

		$prod_cri = 'add';
		if ( $parent_id ) {
			$post['ID']         = $parent_id;
			$post['menu_order'] = get_post( $parent_id )->menu_order;
			$prod_cri           = 'update';
		}
		// Create product/post.
		remove_action( 'save_post', 'woo_square_add_edit_product' );
		$new_prod_id = wp_insert_post( $post );

		$data        = $this->insert_product_images( $new_prod_id, $square_product );
		$new_product = new WC_Product_Variable( $new_prod_id );
		$new_product->save();
		add_action( 'save_post', 'woo_square_add_edit_product', 10, 3 );
		// make product type be variable.
		wp_set_object_terms( $new_prod_id, 'variable', 'product_type' );
		// add category to product.
		wp_set_object_terms( $new_prod_id, $cats, 'product_cat' );
		// ################### Add size attributes to main product: ####################.
		// Array for setting attributes.
		$var_keys  = array();
		$total_qty = 0;

		foreach ( $variations as $variation ) {
			if ( isset( $variation['new_option_var'] ) && $this->is_enable_square_option_format( $square_product ) ) {

				$var_keyss = array();
				foreach ( $variation['new_option_var'] as $var_key => $new_option_var ) {
					$variation['name']           = $new_option_var;
					$var_keyss[]                 = $var_key;
					$variatioskeys[ $var_key ][] = strtolower( $new_option_var );
					$var_keyss                   = array_unique( $var_keyss, SORT_REGULAR );
					if ( $variatioskeys ) {
						$var_keyss['variations_keys'] = $variatioskeys;
					}
				}
				$total_qty += (int) isset( $variation['qty'] ) ? $variation['qty'] : 0;
				$var_keys   = array();
				$var_keys   = $var_keyss;
			} else {
				$variation['name']   = $variation['name'];
				$variations_exploded = explode( ',', $variation['name'] );
				if ( is_array( $variations_exploded ) ) {

					$var_keyss     = array();
					$variatioskeys = array();
					foreach ( $variations_exploded as $attr_names ) {
						$varkeys = explode( '[', $attr_names );
						if ( isset( $varkeys[1] ) ) {
							$variation['name'] = $varkeys[1];
						}
						$variation['name'] = str_replace( ']', '', $attr_names );
						$total_qty        += (int) isset( $variation['qty'] ) ? $variation['qty'] : 0;
						$varkeys           = explode( '[', $variation['name'] );

						$var_keyss[] = $varkeys[0];
						if ( isset( $varkeys[1] ) ) {
							if ( ! isset( $variatioskeys[ $varkeys[0] ] ) ) {
								$variatioskeys[ $varkeys[0] ] = array();
							}
							$variatioskeys[ $varkeys[0] ][] = strtolower( $varkeys[1] );
						}
					}

					$var_keyss = array_unique( $var_keyss, SORT_REGULAR );
					if ( ! empty( $variatioskeys ) ) {
						$var_keyss['variations_keys'] = $variatioskeys;
					}
					$var_keys = array();
					$var_keys = $var_keyss;
				} else {
					$varkeys           = explode( '[', $variation['name'] );
					$variation['name'] = $varkeys[1];
					$variation['name'] = str_replace( ']', '', $variation['name'] );
					$total_qty        += (int) isset( $variation['qty'] ) ? $variation['qty'] : 0;
					$var_keys[]        = strtolower( $variation['name'] );
				}
			}
		}

		wp_set_object_terms( $new_prod_id, $var_keys, $variations_key );

		if ( isset( $var_keys ) && is_array( $var_keys ) ) {
			global $wpdb;
			$get_results = 'get_results';

			// OPTIMIZATION: Collect all attribute names first for batch querying
			$attribute_names = array();
			foreach ( $var_keys as $key => $attrkeys ) {
				if ( is_numeric( $key ) && ! empty( $attrkeys ) && ! is_array( $attrkeys ) ) {
					$attribute_names[] = strtolower( $attrkeys );
				}
			}
			$attribute_names = array_unique( $attribute_names );

			// Batch fetch all attribute taxonomies in one query
			$all_attrs = array();
			if ( ! empty( $attribute_names ) ) {
				$prepare          = 'prepare';
				$get_results      = 'get_results';
				$placeholders     = implode( ',', array_fill( 0, count( $attribute_names ), '%s' ) );
				$query            = $wpdb->$prepare(
					"SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name IN ($placeholders)",
					...$attribute_names
				);
				$all_attrs_result = $wpdb->$get_results( $query );
				foreach ( $all_attrs_result as $attr_row ) {
					$all_attrs[ $attr_row->attribute_name ] = $attr_row;
				}
			}

			// Batch fetch all term taxonomies in one query
			$all_term_taxonomies = array();
			if ( ! empty( $attribute_names ) ) {
				$prepare               = 'prepare';
				$get_results           = 'get_results';
				$taxonomy_placeholders = array();
				foreach ( $attribute_names as $attr_name ) {
					$taxonomy_placeholders[] = 'pa_' . $attr_name;
				}
				$taxonomy_placeholders_str  = implode( ',', array_fill( 0, count( $taxonomy_placeholders ), '%s' ) );
				$term_query                 = $wpdb->$prepare(
					"SELECT * FROM {$wpdb->prefix}term_taxonomy WHERE taxonomy IN ($taxonomy_placeholders_str)",
					...$taxonomy_placeholders
				);
				$all_term_taxonomies_result = $wpdb->$get_results( $term_query );
				foreach ( $all_term_taxonomies_result as $term_tax ) {
					$all_term_taxonomies[ $term_tax->taxonomy ][] = $term_tax;
				}
			}

			// Batch fetch all terms in one query
			$all_terms_data = array();
			if ( ! empty( $all_term_taxonomies_result ) ) {
				$term_ids = array();
				foreach ( $all_term_taxonomies_result as $term_tax ) {
					$term_ids[] = $term_tax->term_id;
				}
				if ( ! empty( $term_ids ) ) {
					$prepare               = 'prepare';
					$get_results           = 'get_results';
					$term_ids_placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
					$terms_query           = $wpdb->$prepare(
						"SELECT t.term_id, t.name, tt.taxonomy FROM {$wpdb->prefix}terms t 
						INNER JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id 
						WHERE t.term_id IN ($term_ids_placeholders)",
						...$term_ids
					);
					$all_terms_result      = $wpdb->$get_results( $terms_query );
					foreach ( $all_terms_result as $term_row ) {
						$all_terms_data[ $term_row->taxonomy ][ $term_row->term_id ] = $term_row;
					}
				}
			}

			// Collect all terms that need to be inserted
			$terms_to_insert        = array();
			$terms_name_by_taxonomy = array();
			$var_ontersect          = array();
			$global_attr            = array();

			foreach ( $var_keys as $key => $attrkeys ) {
				if ( is_numeric( $key ) ) {
					$attr_lower = strtolower( $attrkeys );
					$taxonomy   = 'pa_' . $attr_lower;
					$term_query = isset( $all_term_taxonomies[ $taxonomy ] ) ? $all_term_taxonomies[ $taxonomy ] : array();
					$attr       = isset( $all_attrs[ $attr_lower ] ) ? array( $all_attrs[ $attr_lower ] ) : array();
				}

				if ( ! empty( $attrkeys ) && ! is_array( $attrkeys ) ) {

					if ( isset( $var_keys['variations_keys'][ $attrkeys ] ) && ! empty( $var_keys['variations_keys'][ $attrkeys ] ) ) {
						$variations_keys = array_unique( $var_keys['variations_keys'][ $attrkeys ] );
					}

					if ( ! empty( $term_query ) && ! empty( $attr ) && is_numeric( $key ) ) {
						$thedata[ 'pa_' . $attrkeys ] = array(
							'name'         => 'pa_' . $attrkeys,
							'value'        => '',
							'is_visible'   => 1,
							'is_variation' => 1,
							'position'     => 1,
							'is_taxonomy'  => 1,
						);

						$terms_name = array();
						// Use pre-fetched terms data instead of get_term_by() calls
						if ( isset( $all_terms_data[ $taxonomy ] ) ) {
							foreach ( $term_query as $term_tax_row ) {
								if ( isset( $all_terms_data[ $taxonomy ][ $term_tax_row->term_id ] ) ) {
									$term_data    = $all_terms_data[ $taxonomy ][ $term_tax_row->term_id ];
									$terms_name[] = strtolower( $term_data->name );
								}
							}
						}

						if ( isset( $variations_keys ) ) {
							foreach ( $variations_keys as $termname ) {
								$termname = strtolower( $termname );

								if ( ! empty( $terms_name ) ) {
									if ( ! in_array( $termname, $terms_name, true ) && ! empty( $termname ) ) {
										// Collect terms to insert instead of inserting immediately
										if ( ! isset( $terms_to_insert[ $taxonomy ] ) ) {
											$terms_to_insert[ $taxonomy ] = array();
										}
										$terms_to_insert[ $taxonomy ][] = $termname;
									} elseif ( ! in_array( $termname, $terms_name, true ) ) {
										// Term exists, add to terms_name if not already there
										$terms_name[] = $termname;
									}
								} else {
									// If no existing terms, also collect for batch insert
									if ( ! isset( $terms_to_insert[ $taxonomy ] ) ) {
										$terms_to_insert[ $taxonomy ] = array();
									}
									$terms_to_insert[ $taxonomy ][] = $termname;
								}
							}
						}
						$global_attr[] = $attrkeys;
						if ( ! empty( $variations_keys ) ) {
							if ( ! isset( $var_ontersect ) ) {
								$var_ontersect = array();
							}
							foreach ( $variations_keys as $arry ) {
								$var_ontersect[] = strtolower( $arry );
							}
						}

						// Store terms_name for this taxonomy for later use
						if ( ! isset( $terms_name_by_taxonomy ) ) {
							$terms_name_by_taxonomy = array();
						}
						$terms_name_by_taxonomy[ $taxonomy ] = $terms_name;
					} else {
						if ( isset( $var_keys['variations_keys'][ $attrkeys ] ) && ! empty( $var_keys['variations_keys'][ $attrkeys ] ) ) {
							$variations_keys = array_unique( $var_keys['variations_keys'][ $attrkeys ] );
						}
						$thedata[ $attrkeys ] = array(
							'name'         => $attrkeys,
							'value'        => ! empty( $variations_keys ) ? implode( '|', $variations_keys ) : '',
							'is_visible'   => 1,
							'is_variation' => 1,
							'position'     => '0',
							'is_taxonomy'  => 0,
						);
					}
				}
			}
		}

		// Batch insert all collected terms
		if ( ! empty( $terms_to_insert ) ) {
			foreach ( $terms_to_insert as $taxonomy => $term_names ) {
				$term_names = array_unique( $term_names );
				foreach ( $term_names as $termname ) {
					if ( ! empty( $termname ) ) {
						$term = wp_insert_term(
							$termname,
							$taxonomy,
							array(
								'description' => '',
								'slug'        => strtolower( $termname ),
								'parent'      => '',
							)
						);
						if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
							$attr_name = str_replace( 'pa_', '', $taxonomy );
							add_term_meta( $term['term_id'], 'order_pa_' . strtolower( $attr_name ), '', true );
							// Update terms_name array for this taxonomy
							if ( ! isset( $terms_name_by_taxonomy[ $taxonomy ] ) ) {
								$terms_name_by_taxonomy[ $taxonomy ] = array();
							}
							$terms_name_by_taxonomy[ $taxonomy ][] = strtolower( $termname );
						}
					}
				}
			}
		}

		// Update terms_name arrays with newly inserted terms and set object terms
		if ( isset( $var_keys ) && is_array( $var_keys ) && isset( $terms_name_by_taxonomy ) ) {
			foreach ( $var_keys as $key => $attrkeys ) {
				if ( is_numeric( $key ) && ! empty( $attrkeys ) && ! is_array( $attrkeys ) ) {
					$taxonomy = 'pa_' . strtolower( $attrkeys );
					if ( isset( $terms_name_by_taxonomy[ $taxonomy ] ) ) {
						$terms_name = $terms_name_by_taxonomy[ $taxonomy ];
						if ( isset( $var_ontersect ) && ! empty( $var_ontersect ) ) {
							$terms_name = array_intersect( $terms_name, $var_ontersect );
						}
						if ( ! empty( $terms_name ) ) {
							wp_set_object_terms( $new_prod_id, $terms_name, $taxonomy );
						}
					}
				}
			}
		}

		if ( isset( $thedata ) && ! empty( $thedata ) ) {
			update_post_meta( $new_prod_id, '_product_attributes', $thedata );
		}

		// Process custom attributes from Square
		$custom_attributes = $this->process_square_custom_attributes( $square_product, $new_prod_id );

		if ( ! empty( $custom_attributes ) ) {
			// Get existing product attributes
			$existing_attributes = get_post_meta( $new_prod_id, '_product_attributes', true );
			if ( empty( $existing_attributes ) || ! is_array( $existing_attributes ) ) {
				$existing_attributes = array();
			}

			// Merge custom attributes with existing attributes
			$merged_attributes = array_merge( $existing_attributes, $custom_attributes );

			// Save merged attributes
			update_post_meta( $new_prod_id, '_product_attributes', $merged_attributes );
		}

		// ########################## Done adding attributes to product #################.
		// set product values.
		update_post_meta( $new_prod_id, '_stock_status', $total_qty > 0 ? 'instock' : 'outofstock' );
		$woocmmerce_instance = new WC_Product( $new_prod_id );
		wc_update_product_stock( $woocmmerce_instance, $total_qty );
		update_post_meta( $new_prod_id, '_visibility', 'visible' );

		update_post_meta( $new_prod_id, '_default_attributes', array() );
		// ###################### Add Variation post types for sizes #############################.
		$i          = 1;
		$var_prices = array();
		// set IDs for product_variation posts.
		$args                    = array(
			'post_type'   => 'product_variation',
			'post_status' => array( 'private', 'publish' ),
			'numberposts' => -1,
			'orderby'     => 'menu_order',
			'order'       => 'asc',
			'post_parent' => $new_prod_id, // product id.
		);
		$variation_already_exist = get_posts( $args );

		if ( ! empty( $variation_already_exist ) ) {
			foreach ( $variation_already_exist as $variation_exi ) {
				$variation_already_exist_arr[] = $variation_exi->ID;
			}
		}

		// BATCH OPTIMIZATION: Collect all variation IDs first for batch image fetching
		$variation_ids_for_images = array();
		foreach ( $variations as $variation ) {
			if ( isset( $variation['variation_id'] ) && ! empty( $variation['variation_id'] ) ) {
				$variation_ids_for_images[] = $variation['variation_id'];
			}
		}

		// Batch fetch all variation images at once
		$batch_variation_images = array();
		if ( ! empty( $variation_ids_for_images ) ) {
			$batch_variation_images = $this->get_variation_images_batch( $variation_ids_for_images );
		}

		// OPTIMIZATION: Call these once before the loop
		$is_enable_square_option_format_result = $this->is_enable_square_option_format( $square_product );
		$variations_count                      = count( $variations );
		$home_url_value                        = home_url();
		$remove_action_done                    = false;

		foreach ( $variations as $variation ) {
			$variation_forsetobj = $variation;
			if ( isset( $variation['new_option_var'] ) && $is_enable_square_option_format_result ) {
				foreach ( $variation['new_option_var'] as $var_key => $new_option_var ) {
					if ( 'custom_sale_price' === $var_key ) {
						continue;
					}
					$variation['name'] = $new_option_var;
				}
			} else {
				$variation['name'] = $variation['name'];
				$varkeys           = explode( '[', $variation['name'] );
				if ( isset( $varkeys[1] ) ) {
					$variation['name'] = $varkeys[1];
				}
				$variation['name'] = str_replace( ']', '', $variation['name'] );
			}
			$my_post = array(
				'post_title'  => 'Variation #' . $i . ' of ' . $variations_count . ' for product#' . $new_prod_id,
				'post_name'   => 'product-' . $new_prod_id . '-variation-' . $i,
				'post_status' => 'publish',
				'post_parent' => $new_prod_id, // post is a child post of product post.
				'post_type'   => 'product_variation', // set post type to product_variation.
				'guid'        => $home_url_value . '/?product_variation=product-' . $new_prod_id . '-variation-' . $i,
			);

			if ( isset( $variation['product_id'] ) ) {
				$my_post['ID'] = $variation['product_id'];
			}
			if ( ! empty( $variation_already_exist_arr ) ) {
				if ( ! empty( $variation['product_id'] ) ) {
					$proid[] = $variation['product_id'];
				}
			}
			// Insert ea. post/variation into database.
			// OPTIMIZATION: Remove action only once before first post insert
			if ( ! $remove_action_done ) {
				remove_action( 'save_post', 'woo_square_add_edit_product' );
				$remove_action_done = true;
			}
			$att_id = wp_insert_post( $my_post );
			if ( is_wp_error( $att_id ) ) {
				$var_error[] = array(
					'status'     => false,
					'pro_status' => 'failed',
					'message'    => $att_id->get_error_message(),
				);

			}
			// Create 2xl variation for ea product_variation.
			$variation_val = array();
			if ( isset( $variation_forsetobj['new_option_var'] ) && $is_enable_square_option_format_result ) {
				foreach ( $variation_forsetobj['new_option_var'] as $var_key => $new_option_var ) {
					if ( 'custom_sale_price' === $var_key ) {
						continue;
					}
					$variation_forsetobj['name'] = $new_option_var;
					$variation_val[]             = $var_key . '[' . $variation_forsetobj['name'] . ']';
					$variation_values            = $variation_val;
				}
			} else {
				$variation_forsetobj['name'] = $variation_forsetobj['name'];
				$variation_values            = explode( ',', $variation_forsetobj['name'] );
			}
			foreach ( $variation_values as $values ) {
				$getting_attr_n_variation_name = explode( '[', $values );
				$pa                            = '';
				$is_taxonomy                   = false;
				if ( ! empty( $global_attr ) ) {
					if ( in_array( $getting_attr_n_variation_name[0], $global_attr, true ) ) {
						$pa          = 'pa_';
						$is_taxonomy = true;
					}
				}

				// Attribute name/key: always use hyphenated format
				$attr_key = 'attribute_' . $pa . preg_replace( '/-+/', '-', str_replace( ' ', '-', trim( strtolower( $getting_attr_n_variation_name[0] ) ) ) );

				// Attribute value: for taxonomy use term slug (hyphenated), for custom preserve original format
				$attr_value = str_replace( ']', '', $getting_attr_n_variation_name[1] );
				$attr_value = trim( $attr_value );

				if ( $is_taxonomy ) {
					// For taxonomy attributes, use term slug format (lowercase, hyphenated)
					$attr_value = preg_replace( '/-+/', '-', str_replace( ' ', '-', strtolower( $attr_value ) ) );
				} else {
					// For custom attributes, preserve original format (keep spaces as they are in dropdown)
					$attr_value = strtolower( $attr_value );
				}

				delete_post_meta( $att_id, $attr_key );
				update_post_meta( $att_id, $attr_key, $attr_value );
			}

			// OPTIMIZATION: Batch all post meta updates together to reduce database calls
			$meta_updates                   = array();
			$price                          = floatval( $variation['price'] );
			$meta_updates['_regular_price'] = $price;
			$meta_updates['_price']         = $price;

			if ( isset( $variation['sale_price'] ) && $variation['sale_price'] < $variation['price'] && $variation['sale_price'] >= 0 ) {
				$meta_updates['_price']      = floatval( $variation['sale_price'] );
				$meta_updates['_sale_price'] = $variation['sale_price'];
			} else {
				$meta_updates['_sale_price'] = '';
			}

			$meta_updates['_global_unique_id']   = isset( $variation['upc'] ) ? $variation['upc'] : '';
			$meta_updates['_sku']                = isset( $variation['sku'] ) ? $variation['sku'] : '';
			$meta_updates['variation_square_id'] = isset( $variation['variation_id'] ) ? $variation['variation_id'] : '';

			// Stock management (track_inventory may be bool true, not integer 1).
			if ( isset( $variation['qty'] ) && (float) $variation['qty'] > 0 ) {
				$meta_updates['_manage_stock'] = 'yes';
				$meta_updates['_stock_status'] = 'instock';
				$meta_updates['_stock']        = $variation['qty'];
			} elseif ( isset( $variation['qty'] ) && (float) $variation['qty'] <= 0 ) {
				$meta_updates['_manage_stock'] = 'yes';
				$meta_updates['_stock_status'] = 'outofstock';
				$meta_updates['_stock']        = $variation['qty'];
			} elseif ( ! isset( $variation['qty'] ) && ! empty( $variation['track_inventory'] ) ) {
				$meta_updates['_manage_stock'] = 'yes';
				$meta_updates['_stock_status'] = 'outofstock';
				$meta_updates['_stock']        = 0;
			} else {
				$meta_updates['_manage_stock'] = 'no';
				$meta_updates['_stock_status'] = 'instock';
			}

			// Batch update all meta at once
			global $wpdb;
			foreach ( $meta_updates as $meta_key => $meta_value ) {
				update_post_meta( $att_id, $meta_key, $meta_value );
			}

			if ( $i >= 1 ) {
				$var_prices[ $i - 1 ]['id']            = $att_id;
				$var_prices[ $i - 1 ]['regular_price'] = sanitize_title( $variation['price'] );
			}

			// add size attributes to this variation.
			$sanitized_name = sanitize_title( $variation['name'] );
			wp_set_object_terms( $att_id, $var_keys, 'pa_' . $sanitized_name );

			// Save variation using WC_Product_Variation to ensure attributes are properly registered
			$variation_obj = wc_get_product( $att_id );
			if ( $variation_obj && is_a( $variation_obj, 'WC_Product_Variation' ) ) {
				$variation_obj->save();
			}

			++$i;

			// Use batch fetched variation images instead of individual API calls
			if ( isset( $variation['variation_id'] ) && ! empty( $variation['variation_id'] ) ) {
				if ( isset( $batch_variation_images[ $variation['variation_id'] ] ) ) {
					$variation['image_id']   = $batch_variation_images[ $variation['variation_id'] ]['image_id'];
					$variation['image_data'] = $batch_variation_images[ $variation['variation_id'] ]['image_data'];

					if ( isset( $variation['image_data'] ) ) {
						$existing_img_id = get_post_meta( $att_id, 'square_var_img_id', true );
						if ( strcmp( $existing_img_id, $variation['image_id'] ) ) {
							$this->upload_variation_image( $variation, $att_id );
						}
					}
				}
			}
		}

		// OPTIMIZATION: Re-add action only once after all posts are inserted
		if ( $remove_action_done ) {
			add_action( 'save_post', 'woo_square_add_edit_product', 10, 3 );
		}

		$i = 0;
		if ( isset( $var_prices ) && ! empty( $var_prices ) ) {
			$regular_prices = array();
			$sale_prices    = array();
			foreach ( $var_prices as $var_price ) {
				$regular_prices[] = $var_price['regular_price'];
				$sale_prices[]    = $var_price['regular_price'];
			}
			update_post_meta( $new_prod_id, '_price', min( $sale_prices ) );
			update_post_meta( $new_prod_id, '_min_variation_price', min( $sale_prices ) );
			update_post_meta( $new_prod_id, '_max_variation_price', max( $sale_prices ) );
			update_post_meta( $new_prod_id, '_min_variation_regular_price', min( $regular_prices ) );
			update_post_meta( $new_prod_id, '_max_variation_regular_price', max( $regular_prices ) );
			update_post_meta( $new_prod_id, '_min_price_variation_id', $var_prices[ array_search( min( $regular_prices ), $regular_prices, true ) ]['id'] );
			update_post_meta( $new_prod_id, '_max_price_variation_id', $var_prices[ array_search( max( $regular_prices ), $regular_prices, true ) ]['id'] );
			update_post_meta( $new_prod_id, '_min_regular_price_variation_id', $var_prices[ array_search( min( $regular_prices ), $regular_prices, true ) ]['id'] );
			update_post_meta( $new_prod_id, '_max_regular_price_variation_id', $var_prices[ array_search( max( $regular_prices ), $regular_prices, true ) ]['id'] );
		}
			update_post_meta( $new_prod_id, 'square_id', $product_square_id );
			$product = wc_get_product( $new_prod_id );
		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( ! $product->get_manage_stock() ) {
				$child->save();
			}
		}

		// for refreshing transient.
		$children_transient_name = 'wc_product_children_' . $new_prod_id;
		delete_transient( $children_transient_name );
		$var_error = isset( $var_error ) ? $var_error : array();
		if ( is_wp_error( $new_prod_id ) ) {
			$dddd = array(
				'id'         => $parent_id,
				'status'     => false,
				'pro_status' => 'failed',
				'var_error'  => $var_error,
				'message'    => $new_prod_id->get_error_message(),
			);
		} else {
			$dddd = array(
				'id'         => $new_prod_id,
				'status'     => true,
				'pro_status' => $prod_cri,
				'var_error'  => $var_error,
				'message'    => __( 'Successfully sync', 'woosquare' ),
			);
		}

		return $dddd;
	}

	/**
	 * Inserts a variable product to WooCommerce.
	 *
	 * This function takes a Square product object, a Square inventory object, and an action variable as parameters.
	 * It then checks to see if the product already exists in WooCommerce based on its SKU. If it does, the function updates the product with the new information from Square.
	 * If the product does not exist, the function creates a new product in WooCommerce.
	 *
	 * @param object $square_product A Square product object.
	 * @param object $square_inventory A Square inventory object.
	 * @param string $action The action to take, either "add" or "update".
	 *
	 * @return int The WooCommerce product ID, or false if the product could not be inserted.
	 */
	public function insert_variable_product_to_woo( $square_product, $square_inventory, &$action = false ) {

		$is_sandbox              = get_transient( 'is_sandbox' );
		$woo_square_location_id  = get_option( 'woo_square_location_id' . $is_sandbox );
		$woo_square_access_token = get_option( 'woo_square_access_token' . $is_sandbox );
		$square                  = new Square( $woo_square_access_token, $woo_square_location_id, WOOSQU_PLUS_APPID );

		// WP-944: create/map Square category during Product Sync when Category Sync was not selected.
		$term_id = $this->resolve_woo_category_term_id_for_square_product( $square_product );

		// Try to get the product id from the SKU if set - BATCH OPTIMIZED
		$product_ids                = array();
		$product_id_with_sku_exists = false;

		// Collect all SKUs first for batch checking
		$all_skus = array();
		foreach ( $square_product->variations as $variations_key => $variation ) {
			if ( isset( $variation->item_variation_data->sku ) && ! empty( $variation->item_variation_data->sku ) ) {
				$all_skus[] = $variation->item_variation_data->sku;
			}
		}

		// Batch check all SKUs at once
		if ( ! empty( $all_skus ) ) {
			$product_ids = $this->check_if_products_with_skus_exist_batch( $all_skus );
		}

		if ( ! empty( $product_ids ) ) {

			// SKU already exits.
			$product = get_post( reset( $product_ids ) );
			if ( is_object( $product ) ) {
				$parent_id = $product->post_parent;
			}
			if ( isset( $parent_id ) ) { // woo product is variable.
				$variations = array();

				// Batch fetch: Collect all option IDs first
				$all_option_ids = array();
				if ( $this->is_enable_square_option_format( $square_product ) ) {
					foreach ( $square_product->variations as $variation ) {
						if ( ! empty( $variation->item_variation_data->sku ) && isset( $variation->item_option_values ) ) {
							foreach ( $variation->item_option_values as $item_option_values ) {
								$item_option_values = (array) $item_option_values;
								if ( isset( $item_option_values['item_option_id'] ) ) {
									$all_option_ids[] = $item_option_values['item_option_id'];
								}
							}
						}
					}
					// Batch fetch all options at once
					$batch_options = $this->get_square_options_batch( $all_option_ids );
				}

				foreach ( $square_product->variations as $variation ) {

					// don't add product variaton that doesn't have SKU.
					if ( empty( $variation->item_variation_data->sku ) ) {
						continue;
					}
					$custom_sale_price = ( isset( $variation->custom_attribute_values ) && isset( $variation->custom_attribute_values->custom_sale_price ) )
						? (object) $variation->custom_attribute_values->custom_sale_price
						: (object) array();
					$price             = isset( $variation->item_variation_data->price_money->amount ) ? $variation->item_variation_data->price_money->amount : '';
					$price             = $square->format_amount( $price, 'sqtowo', $variation->item_variation_data->price_money->currency_code );
					$sale_price        = isset( $custom_sale_price->number_value ) ? ( $custom_sale_price->number_value ) : '';
					$data              = array(
						'variation_id'    => $variation->id,
						'upc'             => isset( $variation->item_variation_data->upc )
								? $variation->item_variation_data->upc
								: '',
						'sku'             => $variation->item_variation_data->sku,
						'name'            => $variation->item_variation_data->name,
						'price'           => $price,
						'sale_price'      => $sale_price,
						'track_inventory' => $variation->track_inventory,
					);
					if ( $this->is_enable_square_option_format( $square_product ) ) {
						if ( isset( $variation->item_option_values ) ) {
							$square_options = array();
							foreach ( $variation->item_option_values as $item_option_values ) {
								$item_option_values = (array) $item_option_values;
								// Use batch fetched options instead of individual API calls
								if ( isset( $item_option_values['item_option_id'] ) && isset( $batch_options[ $item_option_values['item_option_id'] ] ) ) {
									$square_option = $batch_options[ $item_option_values['item_option_id'] ];
									if ( isset( $square_option['values'] ) && is_array( $square_option['values'] ) ) {
										foreach ( $square_option['values'] as $values ) {
											if ( isset( $values['id'] ) && $values['id'] === $item_option_values['item_option_value_id'] ) {
												$square_options[ $square_option['name'] ] = $values['item_option_value_data']['name'];
											}
										}
									}
								}
							}
							$data['new_option_var'] = $square_options;
						}
					}
					// put variation product id in variation data to be updated.
					// instead of created.
					if ( isset( $product_ids[ $variation->item_variation_data->sku ] ) ) {
						$data['product_id'] = $product_ids[ $variation->item_variation_data->sku ];
					}

					// Set stock quantity if track_inventory is true and square_inventory has data.
					if ( isset( $variation->track_inventory ) && $variation->track_inventory ) {
						if ( isset( $square_inventory[ $variation->id ] ) ) {
							$data['qty'] = $square_inventory[ $variation->id ];
						}
					}
					$variations[] = $data;
				}
				$prod_description = isset( $square_product->description ) ? $square_product->description : ' ';
				if ( ! empty( $square_product->master_image->url ) ) {
					$prod_img = isset( $square_product->master_image->url ) ? $square_product->master_image : null;
				} else {
					$prod_img = isset( $square_product->image_data->image_data->url ) ? $square_product->image_data->image_data : null;
				}
				$id = $this->create_variable_woo_product( $square_product, $prod_description, array( $term_id ), $variations, 'variation', $square_product->id, $prod_img, $parent_id );

			} else { // woo product is simple.
				$variations = array();

				foreach ( $square_product->variations as $variation ) {

					// don't add product variaton that doesn't have SKU.
					if ( empty( $variation->item_variation_data->sku ) ) {
						continue;
					}
					$custom_sale_price = ( isset( $variation->custom_attribute_values ) && isset( $variation->custom_attribute_values->custom_sale_price ) )
						? (object) $variation->custom_attribute_values->custom_sale_price
						: (object) array();
					$price             = isset( $variation->item_variation_data->price_money->amount ) ? $variation->item_variation_data->price_money->amount : '';
					$price             = $square->format_amount( $price, 'sqtowo', $variation->item_variation_data->price_money->currency_code );
					$sale_price        = isset( $custom_sale_price->number_value ) ? ( $custom_sale_price->number_value ) : '';
					$data              = array(
						'variation_id'    => $variation->id,
						'upc'             => $variation->item_variation_data->upc,
						'sku'             => $variation->item_variation_data->sku,
						'name'            => $variation->item_variation_data->name,
						'price'           => $price,
						'sale_price'      => $sale_price,
						'track_inventory' => $variation->track_inventory,
					);
					if ( isset( $product_ids[ $variation->item_variation_data->sku ] ) ) {
						$data['product_id'] = $product_ids[ $variation->item_variation_data->sku ];
					}

					// Set stock quantity if track_inventory is true and square_inventory has data.
					if ( isset( $variation->track_inventory ) && $variation->track_inventory ) {
						if ( isset( $square_inventory[ $variation->id ] ) ) {
							$data['qty'] = $square_inventory[ $variation->id ];
						}
					}
					$variations[] = $data;
				}
				$prod_description = isset( $square_product->description ) ? $square_product->description : ' ';

				if ( ! empty( $square_product->master_image->url ) ) {
					$prod_img = isset( $square_product->master_image->url ) ? $square_product->master_image : null;
				} else {
					$prod_img = isset( $square_product->image_data->image_data->url ) ? $square_product->image_data->image_data : null;
				}

				$id = $this->create_variable_woo_product( $square_product, $prod_description, array( $term_id ), $variations, 'variation', $square_product->id, $prod_img );

			}
			$action = Helpers::ACTION_UPDATE;
		} else { // SKU not exists.
			$variations   = array();
			$no_sku_count = 0;

			// Batch fetch: Collect all option IDs first
			$all_option_ids = array();
			if ( $this->is_enable_square_option_format( $square_product ) ) {
				foreach ( $square_product->variations as $variation ) {
					if ( ! empty( $variation->sku ) && isset( $variation->item_option_values ) ) {
						foreach ( $variation->item_option_values as $item_option_values ) {
							$item_option_values = (array) $item_option_values;
							if ( isset( $item_option_values['item_option_id'] ) ) {
								$all_option_ids[] = $item_option_values['item_option_id'];
							}
						}
					}
				}
				// Batch fetch all options at once
				$batch_options = $this->get_square_options_batch( $all_option_ids );
			}

			foreach ( $square_product->variations as $variation ) {

				$custom_sale_price = (object) array();

				if ( is_object( $variation ) && property_exists( $variation, 'custom_attribute_values' ) ) {
					$custom_attrs = $variation->custom_attribute_values;

					if ( is_object( $custom_attrs ) && property_exists( $custom_attrs, 'custom_sale_price' ) ) {
						$custom_sale_price = (object) $custom_attrs->custom_sale_price;
					}
				}

				// don't add product variaton that doesn't have SKU.
				if ( empty( $variation->sku ) ) {
					++$no_sku_count;
					continue;
				}
				$price      = isset( $variation->price_money->amount ) ? $variation->price_money->amount : '';
				$price      = $square->format_amount(
					$price,
					'sqtowo',
					isset( $variation->price_money->currency_code ) ? $variation->price_money->currency_code : 'USD'
				);
				$sale_price = isset( $custom_sale_price->number_value ) ? ( $custom_sale_price->number_value ) : '';
				$data       = array(
					'variation_id'    => $variation->id,
					'upc'             => isset( $variation->upc ) ? $variation->upc : '',
					'sku'             => $variation->sku,
					'name'            => $variation->name,
					'price'           => $price,
					'sale_price'      => $sale_price,
					'track_inventory' => $variation->track_inventory,
				);
				if ( $this->is_enable_square_option_format( $square_product ) ) {
					if ( isset( $variation->item_option_values ) ) {
						$square_options = array();
						foreach ( $variation->item_option_values as $item_option_values ) {
							$item_option_values = (array) $item_option_values;
							// Use batch fetched options instead of individual API calls
							if ( isset( $item_option_values['item_option_id'] ) && isset( $batch_options[ $item_option_values['item_option_id'] ] ) ) {
								$square_option = $batch_options[ $item_option_values['item_option_id'] ];
								if ( isset( $square_option['values'] ) && is_array( $square_option['values'] ) ) {
									foreach ( $square_option['values'] as $values ) {
										if ( isset( $values['id'] ) && $values['id'] === $item_option_values['item_option_value_id'] ) {
											$square_options[ $square_option['name'] ] = $values['item_option_value_data']['name'];
										}
									}
								}
							}
						}
						$data['new_option_var'] = $square_options;
					}
				}
				if ( isset( $variation->track_inventory ) && $variation->track_inventory ) {
					if ( isset( $square_inventory[ $variation->id ] ) ) {
						$data['qty'] = $square_inventory[ $variation->id ];
					}
				}
				$variations[] = $data;
			}
			if ( count( $square_product->variations ) === $no_sku_count ) {
				return false;
			}
			$prod_description = isset( $square_product->description ) ? $square_product->description : ' ';
			if ( ! empty( $square_product->master_image->url ) ) {
				$prod_img = isset( $square_product->master_image->url ) ? $square_product->master_image : null;
			} else {
				$prod_img = isset( $square_product->image_data->image_data->url ) ? $square_product->image_data->image_data : null;
			}
			$id = $this->create_variable_woo_product( $square_product, $prod_description, array( $term_id ), $variations, 'variation', $square_product->id, $prod_img );

			$action = Helpers::ACTION_ADD;
		}

		return $id;
	}

	/**
	 * Adds a new attribute to WordPress.
	 *
	 * This function takes an attribute array as a parameter and inserts it into the WordPress database.
	 * It also flushes the rewrite rules and deletes the attribute taxonomy transient.
	 *
	 * @param array $attribute An attribute array.
	 *
	 * @return bool True if the attribute is added successfully, false otherwise.
	 */
	public function process_add_attribute( $attribute ) {

		global $wpdb;

		if ( empty( $attribute['attribute_type'] ) ) {
			$attribute['attribute_type'] = 'text';
		}
		if ( empty( $attribute['attribute_orderby'] ) ) {
			$attribute['attribute_orderby'] = 'menu_order';
		}

		if ( empty( $attribute['attribute_public'] ) ) {
			$attribute['attribute_public'] = 0;
		}
		$valid_attribute_name = $this->valid_attribute_name( $attribute['attribute_name'] );
		if ( empty( $attribute['attribute_name'] ) || empty( $attribute['attribute_label'] ) ) {
			return new WP_Error( 'error', __( 'Please, provide an attribute name and slug.', 'woocommerce' ) );
		} elseif ( ! empty( $valid_attribute_name ) && is_wp_error( $valid_attribute_name ) ) {
			return $valid_attribute_name;
		} elseif ( taxonomy_exists( wc_attribute_taxonomy_name( $attribute['attribute_name'] ) ) ) {
			// translators: Error message placeholder in a log entry. Placeholder: Error details.
			return new WP_Error( 'error', sprintf( __( 'Slug "%s" is already in use. Change it, please.', 'woocommerce' ), sanitize_title( $attribute['attribute_name'] ) ) );
		}
		$insert = 'insert';
		$wpdb->$insert( $wpdb->prefix . 'woocommerce_attribute_taxonomies', $attribute );

		do_action( 'woocommerce_attribute_added', $wpdb->insert_id, $attribute );

		flush_rewrite_rules();
		delete_transient( 'wc_attribute_taxonomies' );

		return true;
	}

	/**
	 * Validate an attribute name for use in WooCommerce.
	 *
	 * This function checks if an attribute name is valid for use in WooCommerce.
	 * It checks the length of the attribute name and if it's a reserved term.
	 *
	 * @param string $attribute_name The attribute name to validate.
	 *
	 * @return true|WP_Error If the attribute name is valid, returns true. If it's invalid, returns a WP_Error with an appropriate error message.
	 */
	public function valid_attribute_name( $attribute_name ) {
		if ( strlen( $attribute_name ) >= 28 ) {
			// translators: Error message placeholder in a log entry. Placeholder: Error details.
			return new WP_Error( 'error', sprintf( __( 'Slug "%s" is too long (28 characters max). Shorten it, please.', 'woocommerce' ), sanitize_title( $attribute_name ) ) );
		} elseif ( wc_check_if_attribute_name_is_reserved( $attribute_name ) ) {
			// translators: Error message placeholder in a log entry. Placeholder: Error details.
			return new WP_Error( 'error', sprintf( __( 'Slug "%s" is not allowed because it is a reserved term. Change it, please.', 'woocommerce' ), sanitize_title( $attribute_name ) ) );
		}

		return true;
	}

	/**
	 * Insert a simple product into WooCommerce.
	 *
	 * This function inserts a simple product into WooCommerce based on Square product data.
	 *
	 * @param object $square_product  The Square product data.
	 * @param int    $square_inventory The Square inventory data.
	 * @param int    $product_id       (Optional) The ID of the product to be updated. If provided, it updates an existing product.
	 * @return int|false If the product is inserted or updated successfully, returns the product's post ID. Otherwise, returns false.
	 */
	public function insert_simple_product_to_woo( $square_product, $square_inventory, $product_id = null ) {

		$suqare_item_id_for_image = $square_product->variations[0]->item_id;

		// WP-944: create/map Square category during Product Sync when Category Sync was not selected.
		$term_id = $this->resolve_woo_category_term_id_for_square_product( $square_product );

		$post_title   = $square_product->name;
		$post_content = isset( $square_product->description ) ? $square_product->description : '';

		$my_post  = array(
			'post_title'   => $post_title,
			'post_content' => $post_content,
			'post_status'  => 'publish',
			'post_author'  => 1,
			'post_type'    => 'product',
		);
		$prod_cri = 'add';

		// check if product id provided to the function.
		if ( $product_id ) {
			$my_post['ID']         = $product_id;
			$my_post['menu_order'] = get_post( $product_id )->menu_order;
			$prod_cri              = 'update';
		}

		// Insert the post into the database.

		remove_action( 'save_post', 'woo_square_add_edit_product' );
		$id = wp_insert_post( $my_post, true );

		$data = $this->insert_product_images( $id, $square_product );
		wp_set_object_terms( $id, $term_id, 'product_cat' );
		add_action( 'save_post', 'woo_square_add_edit_product', 10, 3 );

		$is_attr_vari = explode( ',', $square_product->variations[0]->item_variation_data->name );
		$get_results  = 'get_results';

		if ( is_array( $is_attr_vari ) && strpos( $square_product->variations[0]->item_variation_data->name, ',' ) !== false ) {
			foreach ( $is_attr_vari as $attrr ) {
				$attrname  = explode( '[', $attrr );
				$attrterms = str_replace( ']', '', $attrname[1] );
				$tername   = explode( '|', $attrterms );

				$attrexpl = explode( '[', $attrr );
				global $wpdb;
				$attr = $wpdb->$get_results( 'SELECT * FROM `' . $wpdb->prefix . "woocommerce_attribute_taxonomies` WHERE `attribute_name` = '" . strtolower( $attrexpl[0] ) . "'" );

				if ( ! empty( $attr[0] ) ) {

					$insert = $this->process_add_attribute(
						array(
							'attribute_name'    => strtolower( $attrname[0] ),
							'attribute_label'   => strtolower( $attrname[0] ),
							'attribute_type'    => 'select',
							'attribute_orderby' => 'menu_order',
							'attribute_public'  => 1,
						)
					);
						sleep( 1 );
						$varis = array();
					foreach ( $tername as $ternameval ) {
						$varis[] = strtolower( $ternameval );
						wp_insert_term(
							strtolower( $ternameval ),  // the term.
							'pa_' . strtolower( $attrname[0] ),  // the taxonomy.
							array(
								'description' => '',
								'slug'        => strtolower( $ternameval ),
							)
						);
							$thedata[ 'pa_' . strtolower( $attrname[0] ) ] = array(
								'name'         => 'pa_' . strtolower( $attrname[0] ),
								'value'        => '',
								'is_visible'   => 1,
								'is_variation' => 0,
								'position'     => '0',
								'is_taxonomy'  => 1,
							);

							global $wpdb;
							$get_resul = $wpdb->$get_results( 'SELECT * FROM `' . $wpdb->prefix . "terms` WHERE `slug` = '" . strtolower( $ternameval ) . "' ORDER BY `name` ASC", true );

							if ( ! empty( $get_resul[0] ) ) {
								// INSERT INTO wp_term_relationships (object_id,term_taxonomy_id) VALUES ([the_id_of_above_post],1).
								$pref                   = $wpdb->prefix;
								$get_term_relationships = $wpdb->$get_results( 'SELECT * FROM `' . $pref . "term_relationships` WHERE `object_id` = '" . $id . "' AND `term_taxonomy_id` = '" . $get_resul[0]->term_id . "' AND `term_order` = '0'", true );
								if ( empty( $get_term_relationships[0] ) ) {
											$wpdb->$insert(
												$pref . 'term_relationships',
												array(
													'object_id'        => $id,
													'term_taxonomy_id' => $get_resul[0]->term_id,
													'term_order'       => '0',
												)
											);
								}
							}
					}
						wp_set_object_terms( $id, $varis, 'pa_' . strtolower( $attrname[0] ) );
						update_post_meta( $id, '_product_attributes', $thedata );
				} else {
					$varis                                 = array();
					$varis[]                               = strtolower( $ternameval );
					$thedata[ strtolower( $attrname[0] ) ] = array(
						'name'         => strtolower( $attrname[0] ),
						'value'        => $attrterms,
						'is_visible'   => 1,
						'is_variation' => 0,
						'position'     => '0',
						'is_taxonomy'  => 0,
					);
					wp_set_object_terms( $id, $varis, strtolower( $attrname[0] ) );
					update_post_meta( $id, '_product_attributes', $thedata );
				}
			}
		} elseif ( ! empty( $is_attr_vari[0] ) && false !== strpos( $is_attr_vari[0], '[' ) ) {

			// Single global attribute only for Square encoded names: Attribute[Value|Value2].
			// Plain labels like "Regular" must skip this branch (avoids clash with attr "regular").
			$attrexpl = explode( '[', $is_attr_vari[0] );
			global $wpdb;
			$attr = $wpdb->$get_results( 'SELECT * FROM `' . $wpdb->prefix . "woocommerce_attribute_taxonomies` WHERE `attribute_name` = '" . strtolower( $attrexpl[0] ) . "'" );
			if ( ! empty( $attr[0] ) && isset( $attrexpl[1] ) ) {
				$thedata[ 'pa_' . $attr[0]->attribute_name ] = array(
					'name'         => 'pa_' . $attr[0]->attribute_name,
					'value'        => '',
					'is_visible'   => 1,
					'is_variation' => 1,
					'position'     => 1,
					'is_taxonomy'  => 1,
				);
				update_post_meta( $id, '_product_attributes', $thedata );
				$attrexprepla          = str_replace( ']', '', $attrexpl[1] );
				$square_variation      = explode( '|', $attrexprepla );
				$site_exist_variations = array();
				$simple_variations     = array();
				foreach ( $square_variation as $keys => $variation ) {
					$square_variation[ $keys ] = strtolower( trim( $variation ) );
				}
				$term_query = $wpdb->$get_results( 'SELECT * FROM `' . $wpdb->prefix . "term_taxonomy` WHERE `taxonomy` = 'pa_" . strtolower( $attr[0]->attribute_name ) . "'" );
				if ( ! empty( $term_query ) ) {
					foreach ( $term_query as $key => $variations_value ) {
							$term_data = get_term_by( 'id', $variations_value->term_id, 'pa_' . strtolower( $attr[0]->attribute_name ) );
						if ( ! empty( $term_data->name ) ) {
							$site_exist_variations[] = strtolower( $term_data->name );
						}
					}
				}

				foreach ( $square_variation as $keys => $variation ) {
					if ( in_array( $variation, $site_exist_variations, true ) ) {
						$simple_variations[] = $variation;
					} else {
						$simple_variations[] = $variation;
						$term                = wp_insert_term(
							$variation, // the term.
							'pa_' . strtolower( $attr[0]->attribute_name ), // the taxonomy.
							array(
								'description' => '',
								'slug'        => strtolower( $variation ),
								'parent'      => '',
							)
						);

						if ( ! is_wp_error( $term ) && ! empty( $term['term_id'] ) ) {
							$add_term_meta = add_term_meta( $term['term_id'], 'order_pa_' . strtolower( $attr[0]->attribute_name ), '', true );
						}
					}
				}
				wp_set_object_terms( $id, $simple_variations, 'pa_' . strtolower( $attr[0]->attribute_name ) );
			} else {

				$attrexplsing = explode( '[', $is_attr_vari[0] );
				if ( ! empty( $attrexplsing[1] ) ) {
					$variaarry                                 = str_replace( ']', '', $attrexplsing[1] );
					$variaarryimpl                             = explode( '|', $variaarry );
					$thedata[ strtolower( $attrexplsing[0] ) ] = array(
						'name'         => strtolower( $attrexplsing[0] ),
						'value'        => str_replace( ']', '', $attrexplsing[1] ),
						'is_visible'   => 1,
						'is_variation' => 0,
						'position'     => '0',
						'is_taxonomy'  => 0,
					);
					wp_set_object_terms( $id, $variaarryimpl, strtolower( $attrexplsing[0] ) );
					update_post_meta( $id, '_product_attributes', $thedata );
				}
			}
		}

		if ( $id ) {
			$variation              = $square_product->variations[0];
			$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );

			$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );

			$price = isset( $variation->item_variation_data->price_money->amount ) ? $variation->item_variation_data->price_money->amount : '';
			$price = $square->format_amount( $price, 'sqtowo', $variation->item_variation_data->price_money->currency_code );
			update_post_meta( $id, '_visibility', 'visible' );
			$price = wc_format_decimal( $price, wc_get_price_decimals() );
			update_post_meta( $id, '_regular_price', $price );
			update_post_meta( $id, '_price', $price );

			if ( isset( $variation->custom_attribute_values ) && isset( $variation->custom_attribute_values->custom_sale_price ) ) {
				if ( is_array( $variation->custom_attribute_values->custom_sale_price ) ) {
					$square_sale_price = $variation->custom_attribute_values->custom_sale_price['number_value'];
				} else {
					$square_sale_price = $variation->custom_attribute_values->custom_sale_price->number_value;
				}
				if ( ! empty( $square_sale_price ) && $square_sale_price < $price && $square_sale_price >= 0 ) {
					update_post_meta( $id, '_sale_price', $square_sale_price );
					update_post_meta( $id, '_price', $square_sale_price );
				} else {
					update_post_meta( $id, '_sale_price', '' );
				}
			}
			update_post_meta( $id, '_sku', isset( $variation->item_variation_data->sku ) ? $variation->item_variation_data->sku : '' );
			update_post_meta( $id, '_global_unique_id', isset( $variation->item_variation_data->upc ) ? $variation->item_variation_data->upc : '' );

			// Process custom attributes from Square
			$custom_attributes = $this->process_square_custom_attributes( $square_product, $id );

			if ( ! empty( $custom_attributes ) ) {
				// Get existing product attributes
				$existing_attributes = get_post_meta( $id, '_product_attributes', true );
				if ( empty( $existing_attributes ) || ! is_array( $existing_attributes ) ) {
					$existing_attributes = array();
				}

				// Merge custom attributes with existing attributes
				$merged_attributes = array_merge( $existing_attributes, $custom_attributes );

				// Save merged attributes
				update_post_meta( $id, '_product_attributes', $merged_attributes );
			}

			$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
			$variation_obj          = $square_product->variations[0];
			$item_variation_data    = isset( $variation_obj->item_variation_data )
				? $variation_obj->item_variation_data
				: null;

			// Prefer item-level track_inventory; only override when location explicitly sets it.
			// Square often returns location_overrides with sold_out only (no track_inventory key).
			$track_inventory = ! empty( $item_variation_data->track_inventory )
				|| ! empty( $variation_obj->track_inventory );
			$is_sold_out     = false;

			$location_overrides_list = array();
			if ( ! empty( $item_variation_data->location_overrides ) ) {
				$location_overrides_list = $item_variation_data->location_overrides;
			} elseif ( ! empty( $variation_obj->location_overrides ) ) {
				$location_overrides_list = $variation_obj->location_overrides;
			}

			foreach ( $location_overrides_list as $location_overrides ) {
				if ( $location_overrides->location_id !== $woo_square_location_id ) {
					continue;
				}
				if ( isset( $location_overrides->track_inventory ) ) {
					$track_inventory = (bool) $location_overrides->track_inventory;
				}
				if ( ! empty( $location_overrides->sold_out ) ) {
					$is_sold_out = true;
				}
				break;
			}

			if ( $track_inventory ) {
				update_post_meta( $id, 'track_inventory_check', 'on' );
				update_post_meta( $id, '_manage_stock', 'yes' );
			} else {
				update_post_meta( $id, 'track_inventory_check', 'off' );
				update_post_meta( $id, '_manage_stock', 'no' );
			}

			$this->add_inventory_to_woo( $id, $variation, $square_inventory );

			// Catalog sold_out at this location must mark Woo out of stock when tracking.
			if ( $track_inventory && $is_sold_out ) {
				update_post_meta( $id, '_stock_status', 'outofstock' );
				if ( ! isset( $square_inventory[ $variation->id ] ) ) {
					wc_update_product_stock( new WC_Product( $id ), 0 );
				}
			}

			update_post_meta( $id, 'square_id', $square_product->id );
			update_post_meta( $id, 'variation_square_id', $variation->id );
			update_post_meta( $id, '_termid', 'update' );

			$dddd = array(
				'id'         => $id,
				'status'     => true,
				'pro_status' => $prod_cri,
				'message'    => __( 'Successfully sync', 'woosquare' ),
			);
			return $dddd;
		}
		if ( is_wp_error( $id ) ) {
			$dddd = array(
				'id'         => $product_id,
				'status'     => false,
				'pro_status' => 'failed',
				'message'    => $id->get_error_message(),
			);
			return $dddd;
		}
	}

	/**
	 * Fetches and inserts product images from Square into a WooCommerce product.
	 *
	 * This function retrieves images associated with a Square item, uploads them to the WordPress
	 * media library, and associates them with the specified WooCommerce product. The first image is
	 * set as the featured image, and any additional images are added to the product's image gallery.
	 *
	 * @param int    $id             The ID of the WooCommerce product to associate the images with.
	 * @param string $square_product The Square item from which to retrieve images.
	 *
	 * @return void
	 */
	private function insert_product_images( $id, $square_product ) {
		$square_item_id = '';
		if ( isset( $square_product->variations[0]->item_id ) ) {
			$square_item_id = $square_product->variations[0]->item_id;
		} elseif ( isset( $square_product->variations[0]->item_variation_data->item_id ) ) {
			// Order sync may provide item_id under item_variation_data.
			$square_item_id = $square_product->variations[0]->item_variation_data->item_id;
		}

		if ( empty( $square_item_id ) ) {
			return;
		}

		$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/object/' . $square_item_id;

		$headers = array(
			'Authorization'  => 'Bearer ' . get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), // Use verbose mode in cURL to determine the format you want for this header.
			'Square-Version' => '2024-09-19',
			'Content-Type'   => 'application/json',
		);

		$data     = array(
			'include_related_objects' => true,
		);
		$response = wp_remote_get(
			$url,
			array(
				'headers' => $headers,
				'body'    => $data,
				'method'  => 'GET',
			),
		);

		$var_image_data = json_decode( wp_remote_retrieve_body( $response ), true );

		global $wpdb;
		$image_data = isset( $var_image_data['related_objects'] ) && is_array( $var_image_data['related_objects'] )
			? $var_image_data['related_objects']
			: array();

		$image = array();
		if ( is_array( $image_data ) ) {
			foreach ( $image_data as $k => $data ) {

				if ( isset( $data['type'] ) && 'IMAGE' === $data['type'] ) {
					$prepare = 'prepare';
					$query   = $wpdb->$prepare(
						"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
						'square_master_img_id_' . $data['id'],
						$data['id']
					);
					$get_col = 'get_col';
					$results = $wpdb->$get_col( $query );

					$attachment_id = 0;
					if ( isset( $results[0] ) && ! empty( $results[0] ) ) {
						$attachment_id = $results[0];
					} else {
						$image_payload = isset( $data['image_data'] ) && is_array( $data['image_data'] ) ? $data['image_data'] : array();
						$image_url     = isset( $image_payload['url'] ) ? $image_payload['url'] : '';
						$image_title   = isset( $image_payload['name'] ) ? $image_payload['name'] : $data['id'];

						if ( ! empty( $image_url ) ) {
							$attachment_id = $this->upload_file_by_url( $image_url, $image_title );
							update_post_meta( $attachment_id, 'square_master_img_id_' . $data['id'], $data['id'] );
						}
					}

					if ( empty( $attachment_id ) ) {
						continue;
					}

					$master_image_id = isset( $square_product->master_image->id ) ? $square_product->master_image->id : '';
					if ( $master_image_id === $data['id'] ) {
						$return = set_post_thumbnail( $id, $attachment_id );
					} else {

						// Map image to WooCommerce variation by matching Square image_id to variation image_ids.
						$image_to_find            = $data['id'];
						$woocommerce_variation_id = null;

						if ( isset( $square_product->variations ) && is_array( $square_product->variations ) ) {
							foreach ( $square_product->variations as $variation ) {
								if (
									isset( $variation->item_variation_data->image_ids[0] ) &&
									$variation->item_variation_data->image_ids[0] === $image_to_find
								) {

									$sku = isset( $variation->item_variation_data->sku ) ? $variation->item_variation_data->sku : '';
									// Get WooCommerce product variation ID by SKU.
									// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Using get_posts() with meta query is standard WP pattern.
									$args  = array(
										'post_type'   => 'product_variation',
										'meta_query'  => array(
											array(
												'key'     => '_sku',
												'value'   => $sku,
												'compare' => '=',
											),
										),
										'fields'      => 'ids',
										'post_status' => 'publish',
									);
									$posts = get_posts( $args );
									if ( ! empty( $posts ) ) {
										$woocommerce_variation_id = $posts[0]; // The first matching variation.
										$return                   = set_post_thumbnail( $woocommerce_variation_id, $attachment_id );
									}

									break;
								}
							}
						}

						if ( empty( get_post_meta( $id, 'square_master_img_data', true ) ) ) {
							$image_data = (object) array(
								'id'  => $data['id'],
								'url' => $data['image_data']['url'],
							);
							update_post_meta( $id, 'square_master_img_data', $image_data );
						}
						$image[] = $attachment_id;
					}
				}
			}
		}
		if ( ! empty( $image ) ) {
			update_post_meta( $id, '_product_image_gallery', implode( ',', $image ) );
		}
	}

	/**
	 * Downloads a file from a URL and uploads it to the WordPress media library.
	 *
	 * This function handles the process of downloading a file from a given URL, determining its file type,
	 * and uploading it to the WordPress media library. The function also cleans up temporary files and
	 * returns the attachment ID of the uploaded file.
	 *
	 * @param string $url   The URL of the file to download and upload.
	 * @param string $title The title to assign to the uploaded media.
	 *
	 * @return int|false The attachment ID on success, or false on failure.
	 */
	private function upload_file_by_url( $url, $title ) {
		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/media.php';
		include_once ABSPATH . 'wp-admin/includes/image.php';

		$filename  = pathinfo( $url, PATHINFO_FILENAME );
		$extension = pathinfo( $url, PATHINFO_EXTENSION );

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return false;
		}

		if ( ! $extension ) {
			$mime = mime_content_type( $tmp );
			$mime = is_string( $mime ) ? sanitize_mime_type( $mime ) : false;

			$mime_extensions = array(
				'text/plain'         => 'txt',
				'text/csv'           => 'csv',
				'application/msword' => 'doc',
				'image/jpg'          => 'jpg',
				'image/jpeg'         => 'jpeg',
				'image/gif'          => 'gif',
				'image/png'          => 'png',
				'video/mp4'          => 'mp4',
			);

			if ( isset( $mime_extensions[ $mime ] ) ) {
				$extension = $mime_extensions[ $mime ];
			} else {
				wp_delete_file( $tmp );
				return false;
			}
		}

		$args = array(
			'name'     => $filename . '.' . $extension,
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $args, 0, $title );
		wp_delete_file( $tmp );

		if ( is_wp_error( $attachment_id ) ) {
			return false;
		}

		return $attachment_id;
	}


	/**
	 * Delete WooCommerce products that no longer exist in Square.
	 *
	 * Uses the same reconciliation logic as manual sync (update_products step).
	 *
	 * @param array|false $square_items Square catalog items from get_square_items().
	 * @return void
	 */
	public function delete_woocommerce_products_not_in_square( $square_items ) {
		if ( get_option( 'disable_auto_delete' ) || empty( $square_items ) || ! is_array( $square_items ) ) {
			return;
		}

		$woocommerce_products = get_posts(
			array(
				'post_type'      => 'product',
				'posts_per_page' => -1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'square_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( ! $woocommerce_products ) {
			return;
		}

		foreach ( $woocommerce_products as $product ) {
			$square_id = get_post_meta( $product->ID, 'square_id', true );
			if ( empty( $square_id ) ) {
				continue;
			}

			$exists_in_square = false;
			foreach ( $square_items as $square_item ) {
				if ( $square_id === $square_item->id ) {
					$exists_in_square = true;
					break;
				}
			}

			if ( $exists_in_square ) {
				continue;
			}

			$delt_pro = wc_get_product( $product->ID );
			if ( ! $delt_pro ) {
				continue;
			}

			$sku = $delt_pro->get_sku();
			if ( 'variable' === $delt_pro->get_type() ) {
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

			if ( class_exists( 'WooSquare_Sync_Logs' ) ) {
				$square_product_delete_sync_log_transientt = get_transient( 'square_product_delete_sync_log_transient' );
				if ( empty( $square_product_delete_sync_log_transientt ) ) {
					$square_product_delete_sync_log_transientt = array();
					set_transient( 'square_product_delete_sync_log_transient', $square_product_delete_sync_log_transientt, 300 );
				}

				$square_product_delete_sync_log_transient                           = array();
				$square_product_delete_sync_log_transient[ $product->ID ]['delete'] = $delt_pro_array;
				$square_product_delete_sync_log_transient                           = array_merge( $square_product_delete_sync_log_transientt, $square_product_delete_sync_log_transient );
				set_transient( 'square_product_delete_sync_log_transient', $square_product_delete_sync_log_transient, 300 );

				$square_product_delete_sync_log_id_transient = get_transient( 'square_product_delete_sync_log_id_transient' );
				$woosquare_sync_log                          = new WooSquare_Sync_Logs();
				$log_id                                      = $woosquare_sync_log->delete_product_log_data_request( $square_product_delete_sync_log_transient, $square_product_delete_sync_log_id_transient, 'product', 'square_to_woo' );
				if ( ! empty( $log_id ) ) {
					set_transient( 'square_product_delete_sync_log_id_transient', $log_id, 300 );
				}
			}
		}
	}

	/**
	 * Delete a product from WooCommerce.
	 *
	 * This function removes an action hook, deletes a product post from WooCommerce, and then re-adds the action hook.
	 *
	 * @param int $product_id The ID of the product to be deleted.
	 */
	public function delete_product_from_woo( $product_id ) {
		remove_action( 'before_delete_post', 'woo_square_delete_product' );
		$delt_pro = wc_get_product( $product_id );
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
		wp_delete_post( $product_id, true );

		if ( class_exists( 'WooSquare_Sync_Logs' ) ) {
			// Your original code.
			$_SESSION['square_product_delete_sync_log'][ $product->ID ]['delete'] = $delt_pro_array;

			$session_square_product_delete_sync_log = isset( $_SESSION['square_product_delete_sync_log'] ) ? sanitize_text_field( wp_unslash( $_SESSION['square_product_delete_sync_log'] ) ) : '';
			$session_delete_product_log_id          = isset( $_SESSION['delete_product_log_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['delete_product_log_id'] ) ) : '';
			$woosquare_sync_log                     = new WooSquare_Sync_Logs();
			$log_id                                 = $woosquare_sync_log->delete_product_log_data_request( $session_square_product_delete_sync_log, $session_delete_product_log_id, 'square_to_woo', 'product' );

			if ( ! empty( $log_id ) ) {
				$_SESSION['delete_product_log_id'] = $log_id;
			}
		}

		add_action( 'before_delete_post', 'woo_square_delete_product' );
	}

	/**
	 * Check if a product with given SKU exists (single SKU).
	 *
	 * @param string $square_product_sku SKU to check.
	 * @param string $product_type Product type.
	 * @return array|false Product ID array or false.
	 */
	public function check_if_product_with_sku_exists( $square_product_sku, $product_type = 'product' ) { // phpcs:ignore.
		global $wpdb;
		$get_var    = 'get_var';
		$prepare    = 'prepare';
		$product_id = $wpdb->$get_var( $wpdb->$prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $square_product_sku ) );
		$new[]      = $product_id;

		// do something if the meta-key-value-pair exists in another post.
		if ( ! empty( $new[0] ) ) {
			return $new;
		} else {
			return false;
		}
	}

	/**
	 * Batch check if products with given SKUs exist.
	 * Optimized to check multiple SKUs in a single query.
	 *
	 * @param array $skus Array of SKUs to check.
	 * @return array Associative array with SKU as key and product ID as value.
	 */
	public function check_if_products_with_skus_exist_batch( $skus ) {
		if ( empty( $skus ) || ! is_array( $skus ) ) {
			return array();
		}

		// Remove empty SKUs and duplicates
		$skus = array_filter( array_unique( array_map( 'trim', $skus ) ) );

		if ( empty( $skus ) ) {
			return array();
		}

		global $wpdb;
		$prepare     = 'prepare';
		$get_results = 'get_results';

		// Create placeholders for IN clause
		$placeholders = implode( ',', array_fill( 0, count( $skus ), '%s' ) );
		$query        = $wpdb->$prepare(
			"SELECT meta_value, post_id FROM {$wpdb->postmeta} 
			WHERE meta_key='_sku' AND meta_value IN ($placeholders)",
			...$skus
		);

		$results = $wpdb->$get_results( $query );

		$product_ids = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				$product_ids[ $result->meta_value ] = $result->post_id;
			}
		}

		return $product_ids;
	}

	/**
	 * Batch fetch variation images from Square API.
	 *
	 * @param array $variation_ids Array of variation IDs to fetch images for.
	 * @return array Associative array with variation_id as key and image data as value.
	 */
	public function get_variation_images_batch( $variation_ids ) {
		if ( empty( $variation_ids ) || ! is_array( $variation_ids ) ) {
			return array();
		}

		// Remove duplicates and empty values
		$variation_ids = array_filter( array_unique( $variation_ids ) );

		if ( empty( $variation_ids ) ) {
			return array();
		}

		// Check static cache first
		static $image_cache = array();
		$cached_results     = array();
		$uncached_ids       = array();

		foreach ( $variation_ids as $variation_id ) {
			if ( isset( $image_cache[ $variation_id ] ) ) {
				$cached_results[ $variation_id ] = $image_cache[ $variation_id ];
			} else {
				$uncached_ids[] = $variation_id;
			}
		}

		if ( empty( $uncached_ids ) ) {
			return $cached_results;
		}

		$token   = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$headers = array(
			'Authorization'  => 'Bearer ' . $token,
			'Content-Type'   => 'application/json;',
			'Square-Version' => '2020-12-16',
			'Accept'         => 'application/json',
		);

		$results = array();
		foreach ( $uncached_ids as $variation_id ) {
			$url      = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/object/' . $variation_id;
			$response = wp_remote_get(
				$url,
				array(
					'headers'     => $headers,
					'method'      => 'GET',
					'timeout'     => 10,
					'httpversion' => '1.0',
					'sslverify'   => false,
					'body'        => array( 'include_related_objects' => true ),
				)
			);

			if ( ! is_wp_error( $response ) ) {
				$var_image_data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( ! empty( $var_image_data['related_objects'] ) ) {
					foreach ( $var_image_data['related_objects'] as $var_image ) {
						if ( 'IMAGE' === $var_image['type'] ) {
							$results[ $variation_id ]     = array(
								'image_id'   => $var_image['id'],
								'image_data' => $var_image['image_data'],
							);
							$image_cache[ $variation_id ] = $results[ $variation_id ];
							break;
						}
					}
				}
			}
		}

		return array_merge( $cached_results, $results );
	}

	/**
	 * Uploads and sets a featured image for a product.
	 *
	 * This function sideloads an image, attaches it to a product post,
	 * and sets it as the featured image if found.
	 *
	 * @param int    $product_id   The ID of the product post.
	 * @param object $master_image The master image object.
	 *
	 * @return void
	 */
	public function upload_featured_image( $product_id, $master_image ) {

		include_once ABSPATH . 'wp-admin/includes/media.php';
		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/image.php';

		// Handle both object and array formats
		if ( is_array( $master_image ) ) {
			$image_url = isset( $master_image['url'] ) ? $master_image['url'] : ( isset( $master_image[0]['url'] ) ? $master_image[0]['url'] : '' );
		} else {
			$image_url = isset( $master_image->url ) ? $master_image->url : '';
		}

		if ( empty( $image_url ) ) {
			return;
		}

		// Download the image file first
		$tmp_file = download_url( $image_url );

		if ( is_wp_error( $tmp_file ) ) {
			return;
		}

		// Get file name and extension
		$parsed_url = wp_parse_url( $image_url );
		$file_name  = basename( isset( $parsed_url['path'] ) ? $parsed_url['path'] : '' );
		if ( empty( $file_name ) ) {
			$file_name = 'square-image-' . time() . '.jpg';
		}

		// Prepare file array for wp_handle_upload
		$file_array = array(
			'name'     => $file_name,
			'tmp_name' => $tmp_file,
		);

		// Use wp_handle_upload to handle the file
		$file = wp_handle_upload( $file_array, array( 'test_form' => false ) );

		if ( isset( $file['error'] ) ) {
			wp_delete_file( $tmp_file );
			return;
		}

		// Create attachment
		$attachment = array(
			'post_mime_type' => $file['type'],
			'post_title'     => sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $file['file'], $product_id );

		if ( ! is_wp_error( $attach_id ) ) {
			// Generate attachment metadata
			$attach_data = wp_generate_attachment_metadata( $attach_id, $file['file'] );
			wp_update_attachment_metadata( $attach_id, $attach_data );

			// Set as featured image
			set_post_thumbnail( $product_id, $attach_id );

			// Update square img id to prevent downloading it again each synch.
			$master_image_id = is_array( $master_image ) ? ( isset( $master_image['id'] ) ? $master_image['id'] : ( isset( $master_image[0]['id'] ) ? $master_image[0]['id'] : '' ) ) : ( isset( $master_image->id ) ? $master_image->id : '' );
			if ( ! empty( $master_image_id ) ) {
				update_post_meta( $product_id, 'square_master_img_id', $master_image_id );
			}
		}
	}

	/**
	 * Uploads and sets a variation image in WooCommerce using a given image URL.
	 *
	 * This function handles the process of downloading an image from a URL, associating it with a
	 * WooCommerce product variation, and setting it as the featured image for that variation. It also
	 * updates the variation's metadata with the corresponding Square image ID to prevent redundant downloads.
	 *
	 * @param array $var_image An array containing image data, including the 'image_data' URL and 'image_id'.
	 * @param int   $var_id    The ID of the WooCommerce product variation to which the image is to be attached.
	 *
	 * @return void
	 */
	public function upload_variation_image( $var_image, $var_id ) {

		include_once ABSPATH . 'wp-admin/includes/media.php';
		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/image.php';

		// Handle both object and array formats for image_data
		$image_data = isset( $var_image['image_data'] ) ? $var_image['image_data'] : null;
		if ( is_array( $image_data ) ) {
			$image_url = isset( $image_data['url'] ) ? $image_data['url'] : '';
		} elseif ( is_object( $image_data ) ) {
			$image_url = isset( $image_data->url ) ? $image_data->url : '';
		} else {
			$image_url = '';
		}

		if ( empty( $image_url ) ) {
			return;
		}

		// Download image from URL
		$tmp = download_url( $image_url );
		if ( is_wp_error( $tmp ) ) {
			return;
		}

		// Get filename and extension from URL
		$filename  = pathinfo( $image_url, PATHINFO_FILENAME );
		$extension = pathinfo( $image_url, PATHINFO_EXTENSION );

		// If no extension, try to detect from MIME type
		if ( ! $extension ) {
			$mime = mime_content_type( $tmp );
			$mime = is_string( $mime ) ? sanitize_mime_type( $mime ) : false;

			$mime_extensions = array(
				'image/jpg'  => 'jpg',
				'image/jpeg' => 'jpeg',
				'image/gif'  => 'gif',
				'image/png'  => 'png',
				'image/webp' => 'webp',
			);

			if ( isset( $mime_extensions[ $mime ] ) ) {
				$extension = $mime_extensions[ $mime ];
			} else {
				wp_delete_file( $tmp );
				return;
			}
		}

		// Prepare file array for wp_handle_upload (similar to $_FILES)
		$file_array = array(
			'name'     => $filename . '.' . $extension,
			'tmp_name' => $tmp,
			'size'     => filesize( $tmp ),
			'type'     => wp_check_filetype( $filename . '.' . $extension )['type'],
		);

		// Upload file using wp_handle_upload
		$upload = wp_handle_upload( $file_array, array( 'test_form' => false ) );

		// Check if upload was successful
		if ( isset( $upload['error'] ) && ! empty( $upload['error'] ) ) {
			wp_delete_file( $tmp );
			return;
		}

		// Create attachment post
		$attachment_data = array(
			'post_mime_type' => $upload['type'],
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => $var_id,
		);

		$attachment_id = wp_insert_attachment( $attachment_data, $upload['file'], $var_id );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $upload['file'] );
			return;
		}

		// Generate attachment metadata (thumbnails, etc.)
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		// Set as featured image
		set_post_thumbnail( $var_id, $attachment_id );

		// Update square img id to prevent downloading it again each sync
		update_post_meta( $var_id, 'square_var_img_id', $var_image['image_id'] );
	}

	/**
	 * Add inventory information to a WooCommerce product.
	 *
	 * @param int    $product_id      The ID of the WooCommerce product.
	 * @param object $variation       The Square product variation object.
	 * @param array  $inventory_array An array containing inventory information.
	 */
	public function add_inventory_to_woo( $product_id, $variation, $inventory_array ) {

		$woocmmerce_instance = new WC_Product( $product_id );
		$track_inventory     = get_post_meta( $product_id, 'track_inventory_check', true ) === 'on';
		$has_qty             = isset( $variation->id ) && isset( $inventory_array[ $variation->id ] );
		$qty                 = $has_qty ? (float) $inventory_array[ $variation->id ] : 0;

		if ( ! $track_inventory ) {
			update_post_meta( $product_id, '_stock_status', 'instock' );
			if ( $has_qty ) {
				wc_update_product_stock( $woocmmerce_instance, $qty );
			}
			return;
		}

		// Tracked: 0 / missing inventory count => out of stock.
		if ( ! $has_qty || $qty <= 0 ) {
			update_post_meta( $product_id, '_stock_status', 'outofstock' );
			wc_update_product_stock( $woocmmerce_instance, $qty );
			return;
		}

		update_post_meta( $product_id, '_stock_status', 'instock' );
		wc_update_product_stock( $woocmmerce_instance, $qty );
	}

	/**
	 * Get Square categories from the Square API.
	 *
	 * @return array|false Array of Square categories or false on failure.
	 */
	public function get_square_categories() {
		/* get all categories */

		$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		$token                  = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );

		$woocommerce_square_plus_settings = get_option( 'woocommerce_square_plus' . get_transient( 'is_sandbox' ) . '_settings' );

		$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/list';
		$headers = array(
			'Authorization' => 'Bearer ' . $token, // Use verbose mode in cURL to determine the format you want for this header.
			'Content-Type'  => 'application/json',
			'types'         => 'CATEGORY',
		);

		$method                 = 'GET';
		$args                   = array( 'types' => 'CATEGORY' );
		$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );

		$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );

		$response = array();
		$interval = 0;
		if ( get_option( '_transient_timeout_' . $woo_square_location_id . 'transient_' . __FUNCTION__ ) > time() ) {
			$response = get_transient( $woo_square_location_id . 'transient_' . __FUNCTION__ );
		} else {
			$response = $square->wp_remote_woosquare( $url, $args, $method, $headers, $response );
			// if elements upto 1000 take delay 5 min.

			$decoded_response = json_decode( $response['body'] );

			if ( ! isset( $decoded_response ) ) {
				$count = count( $decoded_response );
				if ( $count > 999 ) {
					$interval = 300;
				} else {
					$interval = 0;
				}
			}

			set_transient( $woo_square_location_id . 'transient_' . __FUNCTION__, $response, $interval );
		}

		if ( ! empty( $response['response'] ) ) {
			if ( 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {
				return json_decode( $response['body'], false );
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Get categories ids linked to square if found from the given square
	 * categories, and an array of the synchronized ones from those linked
	 * categories
	 *
	 * @global object $wpdb
	 * @param object $square_categories square categories object.
	 * @param array  $sync_square_cats synchronized category ids.
	 * @return array Associative array with key: category square id ,
	 *               value: array(category_id, category old name), and the
	 *               square synchronized categories ids in the passed array
	 */
	public function get_unsync_woo_square_categories_ids( $square_categories, &$sync_square_cats ) {

		global $wpdb;
		$woo_square_categories = array();
		$get_results           = 'get_results';
		$prepare               = 'prepare';
		// return if empty square categories.
		if ( empty( $square_categories ) ) {
			return $woo_square_categories;
		}
		// get all square ids.
		$opt_array = array();
		foreach ( $square_categories as $square_category ) {
			$opt_array[] = $square_category->id;
			$original_square_categories_array[ $square_category->id ] = $square_category->category_data->name;
		}

		// Sanitize option values.
		$option_values = array_map( 'sanitize_text_field', $opt_array );

		$placeholders = implode( ',', array_fill( 0, count( $option_values ), '%s' ) );

		// Prepare query.
		$query = $wpdb->$prepare(
			"SELECT option_name, option_value FROM {$wpdb->prefix}options WHERE option_value IN ($placeholders)",
			...$option_values
		);

		// Run query.
		$results = $wpdb->$get_results( $query, OBJECT );

		// select categories again to see if they need update.
		$sync_query    = "
            SELECT term_id, name
            FROM {$wpdb->terms}
            WHERE term_id in ( ";
		$parameters    = array();
		$add_condition = ' %d ,';

		if ( ! is_wp_error( $results ) ) {
			foreach ( $results as $row ) {

				// get id from string.
				preg_match( '#category_square_id_(\d+)#is', $row->option_name, $matches );
				if ( ! isset( $matches[1] ) ) {
					continue;
				}
				// add square id to array.
				$woo_square_categories[ $row->option_value ] = $matches[1];

			}
			if ( ! empty( $woo_square_categories ) ) {
				foreach ( $square_categories as $sq_cat ) {

					if ( isset( $woo_square_categories[ $sq_cat->id ] ) ) {
						// add id and name to be used in select synchronized categries query.
						$sync_query  .= $add_condition;
						$parameters[] = $woo_square_categories[ $sq_cat->id ];
					}
				}
			}

			if ( ! empty( $parameters ) ) {

				$sync_query  = substr( $sync_query, 0, strlen( $sync_query ) - 1 );
				$sync_query .= ')';
				$prepare     = 'prepare';
				$sql         = $wpdb->$prepare( $sync_query, $parameters );
				$get_results = 'get_results';
				$results     = $wpdb->$get_results( $sql );
				foreach ( $results as $row ) {

					$key = array_search( $row->term_id, $woo_square_categories, true );

					if ( $key ) {
						$woo_square_categories[ $key ] = array( $row->term_id, $row->name );
						if ( ! strcmp( $row->name, $original_square_categories_array[ $key ] ) ) {
							$sync_square_cats[] = $row->term_id;
						}
					}
				}
			}
		}

		// if category deleted but square id already added in option meta.
		$taxonomy       = 'product_cat';
		$orderby        = 'name';
		$show_count     = 0;      // 1 for yes, 0 for no.
		$pad_counts     = 0;      // 1 for yes, 0 for no.
		$hierarchical   = 1;      // 1 for yes, 0 for no.
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
		$returnarray    = array();
		if ( ! empty( $all_categories ) ) {
			foreach ( $all_categories as $keyscategories => $catsterms ) {
				$terms_id[] = $catsterms->term_id;
			}
			foreach ( $woo_square_categories as $keys => $cats ) {

				if ( in_array( $cats[0], $terms_id, false ) ) { // phpcs:ignore.

					$returnarray[ $keys ] = $cats;

				}
			}
		}
		return $returnarray;
	}

	/**
	 * Get new Square products that are not already in WooCommerce.
	 *
	 * @param array $square_items      Square items to check for new products.
	 * @param array $skipped_products  Array of skipped product IDs.
	 * @return array                   New Square products not found in WooCommerce.
	 */
	public function get_new_products( $square_items, $skipped_products ) {

		$new_products = array();

		// OPTIMIZATION: Collect all SKUs first for batch checking
		$all_skus        = array();
		$product_sku_map = array(); // Map to track which product has which SKU

		foreach ( $square_items as $square_product ) {
			if ( isset( $square_product->variations ) ) {
				if ( count( $square_product->variations ) <= 1 ) {
					// Simple product
					if ( isset( $square_product->variations[0] ) && isset( $square_product->variations[0]->sku ) && ! empty( $square_product->variations[0]->sku ) ) {
						$sku                     = $square_product->variations[0]->sku;
						$all_skus[]              = $sku;
						$product_sku_map[ $sku ] = array(
							'product' => $square_product,
							'type'    => 'simple',
						);
					}
				} else {
					// Variable product
					foreach ( $square_product->variations as $variation ) {
						if ( isset( $variation->sku ) && ! empty( $variation->sku ) ) {
							$sku        = $variation->sku;
							$all_skus[] = $sku;
							if ( ! isset( $product_sku_map[ $sku ] ) ) {
								$product_sku_map[ $sku ] = array(
									'product'    => $square_product,
									'type'       => 'variable',
									'variations' => array(),
								);
							}
							$product_sku_map[ $sku ]['variations'][] = $variation;
						}
					}
				}
			}
		}

		// Batch check all SKUs at once
		$existing_skus = array();
		if ( ! empty( $all_skus ) ) {
			$existing_skus = $this->check_if_products_with_skus_exist_batch( $all_skus );
		}

		// Now process products using batch results
		foreach ( $square_items as $square_product ) {
			// Simple square product.
			if ( isset( $square_product->variations ) ) {
				if ( count( $square_product->variations ) <= 1 ) {

					if ( isset( $square_product->variations[0] ) && isset( $square_product->variations[0]->sku ) && $square_product->variations[0]->sku ) {
						$square_product_sku = $square_product->variations[0]->sku;
						// Use batch result instead of individual query
						$product_id_with_sku_exists = isset( $existing_skus[ $square_product_sku ] ) ? array( $existing_skus[ $square_product_sku ] ) : false;
						if ( ! $product_id_with_sku_exists ) { // SKU not exists in WooCommerce.
							$new_products[] = $square_product;
						}
					} else {

						$new_products['sku_misin_squ_woo_pro'][] = $square_product;
						$skipped_products[]                      = $square_product->id;
					}

					if ( ! empty( $square_product->variations[0]->id ) ) {
						$new_products['variats_ids'][]['id'] = $square_product->variations[0]->id;
					}
				} else { // Variable square product.

					// if any sku was found linked to a woo product-> skip this product.
					// as it's considered old.
					$add_flag     = true;
					$no_sku_count = 0;

					foreach ( $square_product->variations as $variation ) {
						if ( ! empty( $variation->id ) ) {
							$new_products['variats_ids'][]['id'] = $variation->id;
						}
					}

					foreach ( $square_product->variations as $variation ) {

						if ( isset( $variation->sku ) && ( ! empty( $variation->sku ) ) ) {

							// Use batch result instead of individual query
							$sku_exists = isset( $existing_skus[ $variation->sku ] );
							if ( $sku_exists ) {
								// break loop as this product is not new.
								$add_flag = false;
								break;
							}
						} else {
							++$no_sku_count;
						}
					}

					// return skipped product array.
					foreach ( $square_product->variations as $variation ) {
						if ( ( empty( $variation->sku ) ) ) {
							$new_products['sku_misin_squ_woo_pro_variable'][] = $square_product;
							// if one sku missing break the loop.
							break;
						}
					}

					// skip whole product if none of the variation has sku.
					if ( count( $square_product->variations ) === $no_sku_count ) {
						$skipped_products[] = $square_product->id;
					} elseif ( $add_flag ) { // sku exists but not found in woo.
						$new_products[] = $square_product;
					}
				}
			}
		}

		return $new_products;
	}

	/**
	 * Get square modifier object.
	 *
	 * @return object|false the square response object, false if error occurs
	 */
	public function get_square_modifier() {

		$url     = esc_url( 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/list' );
		$headers = array(
			'Authorization' => 'Bearer ' . $this->square->get_access_token(),
			'Content-Type'  => 'application/json;',
			'types'         => 'MODIFIER_LIST',
		);

		$method                 = 'GET';
		$args                   = array( 'types' => 'MODIFIER_LIST' );
		$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		$square                 = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );

		$response        = array();
		$response        = $square->wp_remote_woosquare( $url, $args, $method, $headers, $response );
		$object_modifier = json_decode( $response['body'], true );
		if ( 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {
			return $object_modifier;
		} else {
			return false;
		}
	}

	/**
	 * Get Square items, modifier lists, categories, and images.
	 *
	 * @return mixed|array|false Square items and related information, or false on failure.
	 */
	public function get_square_items() {
		/* get all items from square */

		$url = esc_url( 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/list' );

		$token                  = $this->square->get_access_token();
		$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		$headers                = array(
			'Authorization'  => 'Bearer ' . $token, // Use verbose mode in cURL to determine the format you want for this header.
			'Content-Type'   => 'application/json',
			'types'          => 'ITEM,MODIFIER_LIST,CATEGORY,IMAGE,TAX',
			'Square-Version' => '2024-03-20',
		);

		$method = 'GET';
		$args   = array( 'types' => 'ITEM,MODIFIER_LIST,CATEGORY,IMAGE,TAX' );
		$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );

		$interval    = 0;
		$base_url    = $url;
		$all_objects = array();
		$cursor      = null;

		// Loop through all pages using pagination
		do {
			// Build URL with query parameters
			$current_url  = $base_url;
			$current_args = $args;

			// Add cursor if available (for pagination)
			if ( ! empty( $cursor ) ) {
				$current_args['cursor'] = $cursor;
			}

			$current_url = add_query_arg( $current_args, $current_url );

			// Make direct wp_remote_request call
			$request = array(
				'headers' => $headers,
				'method'  => $method,
			);

			$response = wp_remote_request( $current_url, $request );

			// Check for errors
			if ( is_wp_error( $response ) ) {
				wp_send_json_error( array( 'message' => $response->get_error_message() ) );
				return;
			}

			$response_body = wp_remote_retrieve_body( $response );
			$response_data = json_decode( $response_body, true );

			// Check if response has objects
			if ( ! empty( $response_data['objects'] ) && is_array( $response_data['objects'] ) ) {
				$all_objects = array_merge( $all_objects, $response_data['objects'] );
			}

			// Get cursor for next page
			$cursor = ! empty( $response_data['cursor'] ) ? $response_data['cursor'] : null;

		} while ( ! empty( $cursor ) );

		$object_old = $all_objects;
		$response   = array(
			'body'     => wp_json_encode( $object_old ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
		);

		if ( ! empty( $object_old ) ) {
			if ( count( $object_old ) > 999 ) {
				$interval = 300;
			} else {
				$interval = 0;
			}
		}
		set_transient( $woo_square_location_id . 'transient_' . __FUNCTION__, $response, $interval );

		$object_new     = array();
		$modifier_list  = array();
		$category_list  = array();
		$image_list     = array();
		$object_old_key = 0;

		foreach ( $object_old as $vals ) {

			if ( 'MODIFIER_LIST' === $vals['type'] ) {
				$modifier_list[] = $vals;
			}

			if ( 'CATEGORY' === $vals['type'] ) {
				$category_list[] = $vals;
			}

			if ( 'IMAGE' === $vals['type'] ) {
				$image_list[] = $vals;
			}

			if (
				'ITEM' === $vals['type']
				&& in_array(
					$vals['item_data']['product_type'],
					array( 'REGULAR', 'FOOD_AND_BEV' ),
					true
				)
				&& ( 1 === (int) $vals['present_at_all_locations'] || in_array( $woo_square_location_id, $vals['present_at_location_ids'] ?? array(), true ) )
				&& empty( $vals['item_data']['is_archived'] )
			) {

				$object_new[ $object_old_key ] = (object) array(
					'fees' => array(),
				);

				// Check if 'present_at_location_ids' exists and add it to the new object.
				if ( isset( $vals['present_at_location_ids'] ) ) {
					$object_new[ $object_old_key ]->present_at_location_ids = $vals['present_at_location_ids'];
				}
				if ( ! empty( $vals['item_data']['variations'] ) ) {
					foreach ( $vals['item_data']['variations'] as $item_data_key => $vl ) {

						if ( isset( $vl['item_variation_data'] ) ) {
							if ( isset( $vl['item_variation_data']['price_money'] ) && isset( $vl['item_variation_data']['price_money']['currency'] ) ) {
								$vl['item_variation_data']['price_money']['currency_code'] = $vl['item_variation_data']['price_money']['currency'];
							}
							$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );

							// Check location_overrides for matching location_id, not just [0].
							$track_inventory_found      = false;
							$inventory_alert_type_found = false;

							if ( isset( $vl['item_variation_data']['location_overrides'] ) && is_array( $vl['item_variation_data']['location_overrides'] ) ) {
								// First, try to find matching location_id.
								foreach ( $vl['item_variation_data']['location_overrides'] as $loc_override ) {
									if ( isset( $loc_override['location_id'] ) && $loc_override['location_id'] === $woo_square_location_id ) {
										if ( isset( $loc_override['track_inventory'] ) ) {
											// Check if track_inventory is actually set (not empty boolean).
											$track_inv_value = $loc_override['track_inventory'];
											// If it's empty/false but stockable is true, use stockable as indicator.
											if ( empty( $track_inv_value ) && isset( $vl['item_variation_data']['stockable'] ) && $vl['item_variation_data']['stockable'] ) {
												$vl['item_variation_data']['track_inventory'] = true;
											} else {
												$vl['item_variation_data']['track_inventory'] = $track_inv_value;
											}
											$track_inventory_found = true;
										}
										if ( isset( $loc_override['inventory_alert_type'] ) ) {
											$vl['item_variation_data']['inventory_alert_type'] = $loc_override['inventory_alert_type'];
											$inventory_alert_type_found                        = true;
										}
										break; // Found matching location, exit loop.
									}
								}

								// If no matching location found, use first one as fallback.
								if ( ! $track_inventory_found && isset( $vl['item_variation_data']['location_overrides'][0]['track_inventory'] ) ) {
									$track_inv_value = $vl['item_variation_data']['location_overrides'][0]['track_inventory'];
									// If it's empty/false but stockable is true, use stockable as indicator.
									if ( empty( $track_inv_value ) && isset( $vl['item_variation_data']['stockable'] ) && $vl['item_variation_data']['stockable'] ) {
										$vl['item_variation_data']['track_inventory'] = true;
									} else {
										$vl['item_variation_data']['track_inventory'] = $track_inv_value;
									}
									$track_inventory_found = true;
								}
								if ( ! $inventory_alert_type_found && isset( $vl['item_variation_data']['location_overrides'][0]['inventory_alert_type'] ) ) {
									$vl['item_variation_data']['inventory_alert_type'] = $vl['item_variation_data']['location_overrides'][0]['inventory_alert_type'];
									$inventory_alert_type_found                        = true;
								}
							}

							// If still not found, check present_at_all_locations and present_at_location_ids.
							if ( ! $track_inventory_found ) {
								$is_present_at_location = false;

								// Check if variation is present at all locations.
								if ( isset( $vl['present_at_all_locations'] ) && ( 1 === (int) $vl['present_at_all_locations'] || true === $vl['present_at_all_locations'] ) ) {
									$is_present_at_location = true; // Check if variation is present at the specific location.
								} elseif ( isset( $vl['present_at_location_ids'] ) && is_array( $vl['present_at_location_ids'] ) && in_array( $woo_square_location_id, $vl['present_at_location_ids'], true ) ) {
									$is_present_at_location = true;
								}

								// If variation is present at location and stockable, set track_inventory to true.
								if ( $is_present_at_location && isset( $vl['item_variation_data']['stockable'] ) && $vl['item_variation_data']['stockable'] ) {
									$vl['item_variation_data']['track_inventory'] = true;
									$track_inventory_found                        = true;
								}
							}

							// Final fallback: check if stockable is true (inventory can be tracked).
							if ( ! $track_inventory_found ) {
								// If location_overrides doesn't exist but item is stockable, default to true.
								// This allows stock sync to work even when location_overrides is not available.
								if ( isset( $vl['item_variation_data']['stockable'] ) && $vl['item_variation_data']['stockable'] ) {
									$vl['item_variation_data']['track_inventory'] = true;
								} else {
									$vl['item_variation_data']['track_inventory'] = null;
								}
							}
							if ( ! $inventory_alert_type_found ) {
								$vl['item_variation_data']['inventory_alert_type'] = null;
							}
							// pricing_type.
							unset( $vl['item_variation_data']['price_money']['currency'] );

							$object_new[ $object_old_key ]->variations[ $item_data_key ]                      = (object) $vl['item_variation_data'];
							$object_new[ $object_old_key ]->variations[ $item_data_key ]->item_variation_data = json_decode( wp_json_encode( $vl['item_variation_data'] ) );
							if ( isset( $vl['item_variation_data']['price_money'] ) ) {
								$object_new[ $object_old_key ]->variations[ $item_data_key ]->price_money = (object) $vl['item_variation_data']['price_money'];
							}
							if ( isset( $vl['custom_attribute_values'] ) ) {
								$object_new[ $object_old_key ]->variations[ $item_data_key ]->custom_attribute_values = (object) $vl['custom_attribute_values'];
							}
							// Set track_inventory on variation object as well (not just in item_variation_data).
							if ( isset( $vl['item_variation_data']['track_inventory'] ) ) {
								$object_new[ $object_old_key ]->variations[ $item_data_key ]->track_inventory = $vl['item_variation_data']['track_inventory'];
							}

							$object_new[ $object_old_key ]->variations[ $item_data_key ]->version = $vl['version'];
						}
						if ( isset( $vl['catalog_v1_ids'] ) ) {
							$object_new[ $object_old_key ]->variations[ $item_data_key ]->catalog_v1_ids = $vl['catalog_v1_ids'];
						}

						$object_new[ $object_old_key ]->variations[ $item_data_key ]->id = $vl['id'];

					}
				}

				if ( ! empty( $vals['item_data']['modifier_list_info'] ) ) {
					foreach ( $vals['item_data']['modifier_list_info'] as $mod_key => $vl ) {

						$object_new[ $object_old_key ]->modifier_list_info[ $mod_key ] = $vl;

					}
				}

				$object_new[ $object_old_key ]->id      = $vals['id'];
				$object_new[ $object_old_key ]->version = $vals['version'];

				if ( isset( $vals['catalog_v1_ids'] ) ) {
					$object_new[ $object_old_key ]->catalog_v1_ids = $vals['catalog_v1_ids'];
				}
				$object_new[ $object_old_key ]->name        = $vals['item_data']['name'];
				$object_new[ $object_old_key ]->description = isset( $vals['item_data']['description'] ) ? $vals['item_data']['description'] : null;

				if ( ! empty( $vals['item_data']['categories'][0]['id'] ) ) {
					$object_new[ $object_old_key ]->category_id = $vals['item_data']['categories'][0]['id'];
				}
				if ( ! empty( $vals['item_data']['tax_ids'] ) ) {
					$object_new[ $object_old_key ]->tax_ids = $vals['item_data']['tax_ids'];
				}
				if ( isset( $vals['item_data']['visibility'] ) ) {
					$object_new[ $object_old_key ]->visibility = $vals['item_data']['visibility'];
				}
				$object_new[ $object_old_key ]->available_online     = isset( $vals['item_data']['available_online'] ) ? $vals['item_data']['available_online'] : null;
				$object_new[ $object_old_key ]->available_for_pickup = isset( $vals['item_data']['available_for_pickup'] ) ? $vals['item_data']['available_for_pickup'] : null;

				if ( ! empty( $vals['item_data']['image_ids'][0] ) ) {

					$object_new[ $object_old_key ]->master_image     = new \stdClass();
					$object_new[ $object_old_key ]->master_image->id = $vals['item_data']['image_ids'][0];
					if ( ! empty( $vals['item_data']['ecom_image_uris'] ) ) {
						$object_new[ $object_old_key ]->master_image->url = $vals['item_data']['ecom_image_uris'][0];
					}
				}
			}

			++$object_old_key;
		}

		foreach ( $object_new as $kym => $image ) {
			if ( ! empty( $image->master_image->id ) && empty( $image->master_image->url ) ) {
				foreach ( $image_list  as $imagelist ) {
					if ( $image->master_image->id === $imagelist['id'] ) {
						$object_new[ $kym ]->master_image->url = $imagelist['image_data']['url'];
					}
				}
			}
		}

		$catalog_key = 0;
		foreach ( $object_new as $kym => $cat ) {
			if ( ! empty( $cat->category_id ) ) {

				foreach ( $category_list as $category ) {

					if ( ( isset( $category['catalog_v1_ids'][ $catalog_key ]['catalog_v1_id'] ) && $category['catalog_v1_ids'][ $catalog_key ]['catalog_v1_id'] === $cat->category_id ) || $category['id'] === $cat->category_id ) {
						if ( empty( $category['catalog_v1_ids'][ $catalog_key ]['catalog_v1_id'] ) ) {
								$object_new[ $kym ]->category = (object) array(
									'id' => $cat->category_id,
								);
						} else {
							$object_new[ $kym ]->category = (object) $category['catalog_v1_ids'][ $catalog_key ]['catalog_v1_id'];
						}
						$object_new[ $kym ]->category->name  = $category['category_data']['name'];
						$object_new[ $kym ]->category->v2_id = $cat->category_id;

					}
				}
			}
			++$catalog_key;
		}

		foreach ( $object_new as $kym => $modadd ) {
			if ( ! empty( $modadd->modifier_list_info ) ) {
				foreach ( $modadd->modifier_list_info as $keym => $modifier_list_info ) {
					foreach ( $modifier_list as $modex ) {
						if ( $modifier_list_info['modifier_list_id'] === $modex['id'] ) {
							$object_new[ $kym ]->modifier_list_info[ $keym ]['mod_sets'] = $modex['modifier_list_data'];
							$object_new[ $kym ]->modifier_list_info[ $keym ]['version']  = $modex['version'];
						}
					}
				}
			}
		}

		if ( ! empty( $object_new ) ) {
			if ( 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {

				return $object_new;

			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Get inventory counts for Square product variations.
	 *
	 * @param array $variations An array of Square product variations.
	 *
	 * @return mixed|array|false The inventory counts for the variations or false on failure.
	 */
	public function get_square_inventory( $variations ) {
		/* get Inventory of all items */

		$variant_ids = array();
		if ( ! empty( $variations ) ) {
			foreach ( $variations as $variants ) {
				if ( ! empty( $variants['id'] ) ) {
					$variant_ids[] = $variants['id'];
				}
			}
		}

		/* get Inventory of all items */
		$url     = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/inventory/counts/batch-retrieve';
		$headers = array(
			'Authorization' => 'Bearer ' . $this->square->get_access_token(), // Use verbose mode in cURL to determine the format you want for this header.
			'Content-Type'  => 'application/json;',
			'requesting'    => 'inventory',
		);

		$method = 'POST';

		$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		$after_date             = gmdate( 'Y-m-d', strtotime( '-06 month' ) ) . 'T00:00:00Z';
		$args                   = array(
			'catalog_object_ids' => $variant_ids,
			'states'             => array( 'IN_STOCK' ),
			'updated_after'      => $after_date,
			'location_ids'       =>
			array(
				0 => $woo_square_location_id,
			),
		);

		$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );

		$response = array();
		$interval = 0;

		$response = $square->wp_remote_woosquare_v2( $url, $args, $method, $headers, $response );

		// if elements upto 1000 take delay 5 min.
		if ( ! empty( $response['body'] ) ) {
			$response_count = json_decode( $response['body'] );
			if ( isset( $response_count ) && ( is_array( $response_count ) || $response_count instanceof Countable ) && count( $response_count ) > 999 ) {
					$interval = 300;
			} else {
				$interval = 0;
			}
		}

		set_transient( $woo_square_location_id . 'transient_' . __FUNCTION__, $response, $interval );
		if ( ! empty( $response['response'] ) ) {
			if ( 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {
				return json_decode( $response['body'], false );
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Convert Square inventory objects to an associative array.
	 *
	 * @param array $square_inventory An array of Square inventory objects.
	 * @return array An associative array where the key is the inventory variation ID
	 *              and the value is the quantity on hand.
	 */
	public function convert_square_inventory_to_associative( $square_inventory ) {

		$square_inventory_array = array();
		foreach ( $square_inventory as $inventory ) {
			$square_inventory_array[ $inventory->catalog_object_id ]
			= $inventory->quantity;
		}

		return $square_inventory_array;
	}
}
