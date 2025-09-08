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

		$batch_size      = 5; // 10 items per batch .
		$total_items     = count( $square_items );
		$processed_items = 0;

		// Create a nonce.
		$nonce = wp_create_nonce( 'square_sync_nonce' );

		foreach ( array_chunk( $square_items, $batch_size ) as $batch ) {

			$args = array(
				'timeout'  => 10,
				'blocking' => true,
				'headers'  => array(
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw cookie header is required for session context.
					'Cookie' => $_SERVER['HTTP_COOKIE'],
				),
				'body'     => array(
					'action'            => 'square_sync_remote',
					'batch'             => wp_json_encode( $batch ),
					'square_sync_nonce' => $nonce,
				),
			);

			$response = wp_remote_post( admin_url( 'admin-ajax.php' ), $args );

			if ( 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {
				$result = json_decode( wp_remote_retrieve_body( $response ) );
				if ( ! get_option( 'disable_auto_delete' ) ) {
					foreach ( $result->data->processed as $res ) {
						$square_ids[] = $res->id;
					}
					global $wpdb;
					$get_results = 'get_results';
					$results     = $wpdb->$get_results(
						"
						SELECT * 
						FROM {$wpdb->prefix}postmeta 
						WHERE meta_key = 'square_id'
					",
						ARRAY_A
					);

					// Loop through the results and delete if meta_value not in square_ids.
					foreach ( $results as $row ) {
						if ( ! in_array( $row['meta_value'], $square_ids, true ) ) {
							$post_id = (int) $row['post_id'];
							// Delete post forcefully (bypassing trash).
							wp_delete_post( $post_id, true );
						}
					}
				}
			}

			if ( is_wp_error( $response ) ) {
				echo 'Error: ' . esc_html( $response->get_error_message() );
			}
			$processed_items += $batch_size;
			sleep( 2 ); // 2 sec delay taake server overload na ho.
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

			if ( 0 === $woo_square_sync_preference && 1 === $woo_square_auto_sync ) {
				if ( ! in_array( $cat->id, $woo_square_listsaved_categories_square, true ) ) {
					continue;
				}
			}

			if ( isset( $woo_square_cats[ $cat->id ] ) ) {  // update.

				// do not update if it is already updated ( its id was returned
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

		if ( ! empty( $term->term_id ) ) {
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

		if ( ! empty( $square_product->modifier_list_info ) ) {

			if ( count( $square_product->modifier_list_info ) >= 1 ) {

				woo_square_plugin_sync_square_modifier_to_woo( $id, $square_product );

			}
		}

		return $id;
	}

	/**
	 * Retrieves or creates the custom sale price attribute for WooSquare.
	 *
	 * This function first attempts to retrieve the custom sale price attribute from Square's API.
	 * If the custom sale price attribute does not exist, it creates a new one using the Square API.
	 * The function updates the option 'woosquare_sale_price_custom_attr' with the attribute key and returns it.
	 *
	 * @param array $options Array of existing Square options to search through.
	 * @return string The custom sale price attribute key.
	 */
	public function get_square_options( $options ) {

		$token   = get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) );
		$url     = esc_url( 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/object/' . $options['item_option_id'] );
		$headers = array(
			'Authorization'  => 'Bearer ' . $token, // Use verbose mode in cURL to determine the format you want for this header.
			'Content-Type'   => 'application/json',
			'Square-Version' => '2024-03-20',
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers' => $headers,
				'method'  => 'GET',
			),
		);

		$create_custom_attr = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $create_custom_attr['object'] ) ) {
			$square_options = $create_custom_attr['object']['item_option_data'];
			return $square_options;
		}
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
		// ################### Add size attributes to main product: ####################
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

					$var_keyss = array();
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
							$variatioskeys[ $varkeys[0] ][] = strtolower( $varkeys[1] );
						}
					}

					$var_keyss = array_unique( $var_keyss, SORT_REGULAR );
					if ( $variatioskeys ) {
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
			foreach ( $var_keys as $key => $attrkeys ) {
				if ( is_numeric( $key ) ) {
					global $wpdb;
					$get_results = 'get_results';
					$term_query  = $wpdb->$get_results( 'SELECT * FROM `' . $wpdb->prefix . "term_taxonomy` WHERE `taxonomy` = 'pa_" . strtolower( $attrkeys ) . "'" );
					$attr        = $wpdb->$get_results( 'SELECT * FROM `' . $wpdb->prefix . "woocommerce_attribute_taxonomies` WHERE `attribute_name` = '" . strtolower( $attrkeys ) . "'" );
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
						foreach ( $term_query as $key => $variations_value ) {
							$term_data = get_term_by( 'id', $variations_value->term_id, 'pa_' . strtolower( $attrkeys ) );
							if ( ! empty( $term_data ) ) {
								$terms_name[] = strtolower( $term_data->name );
							}
						}
						if ( isset( $variations_keys ) ) {
							foreach ( $variations_keys as $termname ) {
								$termname = strtolower( $termname );

								if ( ! empty( $terms_name ) ) {
									if ( ! in_array( $termname, $terms_name, true ) && ! empty( $termname ) ) {
										$term = wp_insert_term(
											$termname, // the term.
											'pa_' . strtolower( $attrkeys ), // the taxonomy.
											array(
												'description' => '',
												'slug'   => strtolower( $termname ),
												'parent' => '',
											)
										);
										if ( ! empty( $term ) ) {
											$terms_name[] = strtolower( $termname );
										}
										if ( ! is_wp_error( $term ) ) {
											$add_term_meta = add_term_meta( $term['term_id'], 'order_pa_' . strtolower( $attrkeys ), '', true );
										}
									}
								}
							}
						}
						$global_attr[] = $attrkeys;
						if ( ! empty( $variations_keys ) ) {
							foreach ( $variations_keys as $arry ) {
								$var_ontersect[] = strtolower( $arry );
							}
						}
						$terms_name = array_intersect( $terms_name, $var_ontersect );
						wp_set_object_terms( $new_prod_id, $terms_name, 'pa_' . strtolower( $attrkeys ) );
					} else {
						if ( isset( $var_keys['variations_keys'][ $attrkeys ] ) && ! empty( $var_keys['variations_keys'][ $attrkeys ] ) ) {
							$variations_keys = array_unique( $var_keys['variations_keys'][ $attrkeys ] );
						}
						$thedata[ $attrkeys ] = array(
							'name'         => $attrkeys,
							'value'        => implode( '|', $variations_keys ),
							'is_visible'   => 1,
							'is_variation' => 1,
							'position'     => '0',
							'is_taxonomy'  => 0,
						);
					}
				}
			}
		}
		if ( isset( $thedata ) && ! empty( $thedata ) ) {
			update_post_meta( $new_prod_id, '_product_attributes', $thedata );
		}
		// ########################## Done adding attributes to product #################
		// set product values.
		update_post_meta( $new_prod_id, '_stock_status', 'instock' );
		$woocmmerce_instance = new WC_Product( $new_prod_id );
		wc_update_product_stock( $woocmmerce_instance, $total_qty );
		update_post_meta( $new_prod_id, '_visibility', 'visible' );

		update_post_meta( $new_prod_id, '_default_attributes', array() );
		// ###################### Add Variation post types for sizes #############################
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
		foreach ( $variations as $variation ) {
			$variation_forsetobj = $variation;
			if ( isset( $variation['new_option_var'] ) && $this->is_enable_square_option_format( $square_product ) ) {
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
				'post_title'  => 'Variation #' . $i . ' of ' . count( $variations ) . ' for product#' . $new_prod_id,
				'post_name'   => 'product-' . $new_prod_id . '-variation-' . $i,
				'post_status' => 'publish',
				'post_parent' => $new_prod_id, // post is a child post of product post.
				'post_type'   => 'product_variation', // set post type to product_variation.
				'guid'        => home_url() . '/?product_variation=product-' . $new_prod_id . '-variation-' . $i,
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
			remove_action( 'save_post', 'woo_square_add_edit_product' );
			$att_id = wp_insert_post( $my_post );
			if ( is_wp_error( $att_id ) ) {
				$var_error[] = array(
					'status'     => false,
					'pro_status' => 'failed',
					'message'    => $att_id->get_error_message(),
				);

			}
			add_action( 'save_post', 'woo_square_add_edit_product', 10, 3 );
			// Create 2xl variation for ea product_variation.
			$variation_val = array();
			if ( isset( $variation_forsetobj['new_option_var'] ) && $this->is_enable_square_option_format( $square_product ) ) {
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
				if ( ! empty( $global_attr ) ) {
					if ( in_array( $getting_attr_n_variation_name[0], $global_attr, true ) ) {
						$pa = 'pa_';
					}
				}
				delete_post_meta( $att_id, 'attribute_' . $pa . preg_replace( '/-+/', '-', str_replace( ' ', '-', trim( strtolower( $getting_attr_n_variation_name[0] ) ) ) ) );
				update_post_meta(
					$att_id,
					'attribute_' . $pa . preg_replace( '/-+/', '-', str_replace( ' ', '-', trim( strtolower( $getting_attr_n_variation_name[0] ) ) ) ),
					preg_replace( '/-+/', '-', str_replace( ' ', '-', strtolower( trim( str_replace( ']', '', $getting_attr_n_variation_name[1] ) ) ) ) )
				);
			}

			update_post_meta( $att_id, '_regular_price', floatval( $variation['price'] ) );

			update_post_meta( $att_id, '_price', floatval( $variation['price'] ) );
			if ( isset( $variation['sale_price'] ) && $variation['sale_price'] < $variation['price'] && $variation['sale_price'] >= 0 ) {
				update_post_meta( $att_id, '_price', floatval( $variation['sale_price'] ) );
				update_post_meta( $att_id, '_sale_price', $variation['sale_price'] );
			} else {
				update_post_meta( $att_id, '_sale_price', '' );
			}
			update_post_meta( $att_id, '_global_unique_id', $variation['upc'] );
			if ( $i >= 1 ) {
				$var_prices[ $i - 1 ]['id']            = $att_id;
				$var_prices[ $i - 1 ]['regular_price'] = sanitize_title( $variation['price'] );
			}

			// add size attributes to this variation.
			wp_set_object_terms( $att_id, $var_keys, 'pa_' . sanitize_title( $variation['name'] ) );
			update_post_meta( $att_id, '_sku', $variation['sku'] );
			update_post_meta( $att_id, 'variation_square_id', $variation['variation_id'] );

			if ( isset( $variation['qty'] ) && $variation['qty'] > 0 ) {
				update_post_meta( $att_id, '_manage_stock', 'yes' );
				update_post_meta( $att_id, '_stock_status', 'instock' );
				update_post_meta( $att_id, '_stock', $variation['qty'] );

			} elseif ( isset( $variation['qty'] ) && $variation['qty'] <= 0 ) {
				update_post_meta( $att_id, '_manage_stock', 'yes' );
				update_post_meta( $att_id, '_stock_status', 'outofstock' );
				update_post_meta( $att_id, '_stock', $variation['qty'] );
			} elseif ( ! isset( $variation['qty'] ) && isset( $variation['track_inventory'] ) && 1 === $variation['track_inventory'] ) {
				update_post_meta( $att_id, '_manage_stock', 'yes' );
				update_post_meta( $att_id, '_stock_status', 'outofstock' );
			} else {
				update_post_meta( $att_id, '_manage_stock', 'no' );
				update_post_meta( $att_id, '_stock_status', 'instock' );
			}
			++$i;

			if ( isset( $variation['variation_id'] ) && ! empty( $variation['variation_id'] ) ) {
				$url            = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/object/' . $variation['variation_id'];
				$headers        = array(
					'Authorization'  => 'Bearer ' . get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), // Use verbose mode in cURL to determine the format you want for this header.
					'Content-Type'   => 'application/json;',
					'Square-Version' => '2020-12-16',
					'Accept'         => 'application/json',
				);
				$data           = array(
					'include_related_objects' => true,
				);
				$var_image_data = json_decode(
					wp_remote_retrieve_body(
						wp_remote_post(
							$url,
							array(
								'method'      => 'GET',
								'headers'     => $headers,
								'httpversion' => '1.0',
								'sslverify'   => false,
								'body'        => $data,
							)
						)
					)
				);

				if ( ! empty( $var_image_data->related_objects ) ) {
					foreach ( $var_image_data->related_objects as $var_image ) {

						if ( 'IMAGE' === $var_image->type ) {

								$variation['image_id']   = $var_image->id;
								$variation['image_data'] = $var_image->image_data;
								break;
						}
					}
				}

				if ( isset( $variation['image_data'] ) ) {
					if ( strcmp( get_post_meta( $att_id, 'square_var_img_id', true ), $variation['image_id'] ) ) {
							$this->upload_variation_image( $variation, $att_id );
					}
				}
			}
		}
		// delete those variation that delete from square..
		if ( ! get_option( 'disable_auto_delete' ) ) {
			if ( ! empty( $proid ) && ! empty( $variation_already_exist_arr ) ) {
				$inter = array_diff( $variation_already_exist_arr, $proid );
				if ( ! empty( $inter ) ) {
					foreach ( $inter as $key ) {
						wp_delete_post( $key, true );
					}
				}
			}
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

		$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );

		$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );

		$term_id = 0;
		if ( isset( $square_product->category ) ) {
			$wp_category = get_term_by( 'name', $square_product->category->name, 'product_cat' );
			$term_id     = isset( $wp_category->term_id ) ? $wp_category->term_id : 0;
		}

		// Try to get the product id from the SKU if set.
		$product_ids                = array();
		$product_id_with_sku_exists = false;

		foreach ( $square_product->variations as $variations_key => $variation ) {

			$sq_variation = (array) $square_product->variations[ $variations_key ];
			array_push( $sq_variation, (object) $variation->item_variation_data );
			if ( isset( $variation->item_variation_data->sku ) ) {
				$square_product_sku = $variation->item_variation_data->sku;
			}
			if ( isset( $square_product_sku ) ) {
				$product_id_with_sku_exists = $this->check_if_product_with_sku_exists( $square_product_sku, array( 'product', 'product_variation' ) );
				if ( ! empty( $product_id_with_sku_exists ) ) {
					$product_ids[ $square_product_sku ] = $product_id_with_sku_exists[0];
				}
			}
		}

		if ( ! empty( $product_ids ) ) {

			// SKU already exits.
			$product = get_post( reset( $product_ids ) );
			if ( is_object( $product ) ) {
				$parent_id = $product->post_parent;
			}
			$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
			if ( isset( $parent_id ) ) { // woo product is variable.
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
								$square_option      = $this->get_square_options( $item_option_values );
								foreach ( $square_option['values'] as $values ) {
									if ( $values['id'] === $item_option_values['item_option_value_id'] ) {
										$square_options[ $square_option['name'] ] = $values['item_option_value_data']['name'];
									}
								}
							}
							$data['new_option_var'] = $square_options;
						}
					}
					// put variation product id in variation data to be updated
					// instead of created.
					if ( isset( $product_ids[ $variation->item_variation_data->sku ] ) ) {
						$data['product_id'] = $product_ids[ $variation->item_variation_data->sku ];
					}

					if ( isset( $variation->item_variation_data->location_overrides ) && is_array( $variation->item_variation_data->location_overrides ) ) {
						foreach ( $variation->item_variation_data->location_overrides as $location_overrides ) {
							if ( $location_overrides->location_id === $woo_square_location_id ) {
								if ( isset( $location_overrides->track_inventory ) && $location_overrides->track_inventory ) {
									if ( isset( $square_inventory[ $variation->id ] ) ) {
										$data['qty'] = $square_inventory[ $variation->id ];
									}
								}
							}
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
					$custom_sale_price = (object) $variation->custom_attribute_values->custom_sale_price;
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

					foreach ( $variation->item_variation_data->location_overrides as $location_overrides ) {
						if ( $location_overrides->location_id === $woo_square_location_id ) {
							if ( isset( $location_overrides->track_inventory ) && $location_overrides->track_inventory ) {
								if ( isset( $square_inventory[ $variation->id ] ) ) {
									$data['qty'] = $square_inventory[ $variation->id ];
								}
							}
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
							$square_option = $this->get_square_options( $item_option_values );
							foreach ( $square_option['values'] as $values ) {
								if ( $values['id'] === $item_option_values['item_option_value_id'] ) {
									$square_options[ $square_option['name'] ] = $values['item_option_value_data']['name'];
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

		$term_id = 0;
		if ( isset( $square_product->category ) ) {
			$wp_category = get_term_by( 'name', $square_product->category->name, 'product_cat' );
			$term_id     = $wp_category->term_id ? $wp_category->term_id : 0;
		}

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
		} elseif ( ! empty( $is_attr_vari[0] ) ) {

			// for single global attribute.
			$attrexpl = explode( '[', $is_attr_vari[0] );
			global $wpdb;
			$attr = $wpdb->$get_results( 'SELECT * FROM `' . $wpdb->prefix . "woocommerce_attribute_taxonomies` WHERE `attribute_name` = '" . strtolower( $attrexpl[0] ) . "'" );
			if ( ! empty( $attr[0] ) ) {
				$thedata[ 'pa_' . $attr[0]->attribute_name ] = array(
					'name'         => 'pa_' . $attr[0]->attribute_name,
					'value'        => '',
					'is_visible'   => 1,
					'is_variation' => 1,
					'position'     => 1,
					'is_taxonomy'  => 1,
				);
				update_post_meta( $id, '_product_attributes', $thedata );
				$attrexprepla     = str_replace( ']', '', $attrexpl[1] );
				$square_variation = explode( '|', $attrexprepla );
				foreach ( $square_variation as $keys => $variation ) {
					$square_variation[ $keys ] = strtolower( trim( $variation ) );
				}
				$term_query = $wpdb->$get_results( 'SELECT * FROM `' . $wpdb->prefix . "term_taxonomy` WHERE `taxonomy` = 'pa_" . strtolower( $attr[0]->attribute_name ) . "'" );
				foreach ( $term_query as $key => $variations_value ) {
						$term_data = get_term_by( 'id', $variations_value->term_id, 'pa_' . strtolower( $attr[0]->attribute_name ) );
					if ( ! empty( $term_data->name ) ) {
						$site_exist_variations[] = strtolower( $term_data->name );
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

						if ( ! empty( $term ) ) {
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

			if ( isset( $variation->custom_attribute_values ) ) {
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

			$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );

			if ( ! empty( $square_product->variations[0]->item_variation_data->location_overrides ) ) {
				foreach ( $square_product->variations[0]->item_variation_data->location_overrides as $location_overrides ) {
					if ( $location_overrides->location_id === $woo_square_location_id ) {
						if ( isset( $location_overrides->track_inventory ) && $location_overrides->track_inventory ) {
							update_post_meta( $id, 'track_inventory_check', 'on' );
							update_post_meta( $id, '_manage_stock', 'yes' );
						} else {
							update_post_meta( $id, 'track_inventory_check', 'off' );
							update_post_meta( $id, '_manage_stock', 'no' );
						}
					}
				}
			} elseif ( $square_product->variations[0]->item_variation_data->track_inventory ) {
					update_post_meta( $id, 'track_inventory_check', 'on' );
					update_post_meta( $id, '_manage_stock', 'yes' );
			} else {
				update_post_meta( $id, 'track_inventory_check', 'off' );
				update_post_meta( $id, '_manage_stock', 'no' );
			}

			$this->add_inventory_to_woo( $id, $variation, $square_inventory );

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
		$square_item_id = $square_product->variations[0]->item_id;
		$url            = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/object/' . $square_item_id;

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
		$image_data = $var_image_data['related_objects'];

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

					if ( isset( $results[0] ) && ! empty( $results[0] ) ) {
						$attachment_id = $results[0];
					} else {
						$attachment_id = $this->upload_file_by_url( $data['image_data']['url'], $data['image_data']['name'] );
						update_post_meta( $attachment_id, 'square_master_img_id_' . $data['id'], $data['id'] );
					}

					if ( $square_product->master_image->id === $data['id'] ) {
						$return = set_post_thumbnail( $id, $attachment_id );
					} else {
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

		// Check if a failed upload exists.
		$existing_attachment = $this->get_failed_upload_by_name( $title );

		if ( $existing_attachment ) {
			return $existing_attachment; // If a failed upload exists, stop the process.
		}

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
	 * Check if a failed upload exists in the media library by filename.
	 *
	 * This function checks if a failed upload with the given filename exists in
	 * the media library by performing a query to the WordPress media attachment
	 * table using the provided filename.
	 *
	 * @param string $filename The filename of the attachment to check for in the media library.
	 *
	 * @return int|false The ID of the existing failed attachment if found, otherwise false.
	 */
	private function get_failed_upload_by_name( $filename ) {
		$meta_query = 'meta_query';
		$args       = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			$meta_query      => array(
				array(
					'key'     => '_wp_attachment_metadata',
					'compare' => 'EXISTS',
				),
			),
			'title'          => $filename,
			'posts_per_page' => 1,
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			return $query->posts[0]->ID; // Return the existing failed attachment ID.
		}

		return false;
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
	 * Check if a product with a specific SKU exists.
	 *
	 * This function checks whether a product with the given SKU exists in the WordPress database.
	 *
	 * @param string $square_product_sku The SKU of the product to check.
	 * @param string $product_type The type of the product to check.
	 * @return array|false If a product with the SKU exists, an array containing the product ID is returned. If not found, returns false.
	 */
	public function check_if_product_with_sku_exists( $square_product_sku, $product_type = 'product' ) { // phpcs:ignore
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

		// Add Featured Image to Post.
		$image = $master_image->url; // Define the image URL here.
		// magic sideload image returns an HTML image, not an ID.
		$media = media_sideload_image( $image, $product_id );

		// magic sideload image returns an HTML image, not an ID
		// therefore we must find it so we can set it as featured ID.
		if ( ! empty( $media ) && ! is_wp_error( $media ) ) {
			$args = array(
				'post_type'      => 'attachment',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'post_parent'    => $product_id,
			);

			$attachments = get_posts( $args );

			if ( isset( $attachments ) && is_array( $attachments ) ) {
				foreach ( $attachments as $attachment ) {
					// grab source of full size images (so no 300x150 nonsense in path).
					$image = wp_get_attachment_image_src( $attachment->ID, 'full' );
					// determine if in the $media image we created, the string of the URL exists.
					$return = set_post_thumbnail( $product_id, $attachment->ID );

					// update square img id to prevent downloading it again each synch.
					update_post_meta( $product_id, 'square_master_img_id', $master_image->id );
					// only want one image.
					if ( strpos( $media, $image[0] ) !== false ) {
						// if so, we found our image. set it as thumbnail.
						$return = set_post_thumbnail( $product_id, $attachment->ID );

						// update square img id to prevent downloading it again each synch.
						update_post_meta( $product_id, 'square_master_img_id', $master_image->id );
						// only want one image.
						break;
					}
				}
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

		// Add Featured Image to Post.
		$image = $var_image['image_data']->url; // Define the image URL here.
		// magic sideload image returns an HTML image, not an ID.
		$media = media_sideload_image( $image, $var_id );

		// magic sideload image returns an HTML image, not an ID
		// therefore we must find it so we can set it as featured ID.
		if ( ! empty( $media ) && ! is_wp_error( $media ) ) {
			$args = array(
				'post_type'      => 'attachment',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'post_parent'    => $var_id,
			);

			$attachments = get_posts( $args );

			if ( isset( $attachments ) && is_array( $attachments ) ) {
				foreach ( $attachments as $attachment ) {
					// grab source of full size images (so no 300x150 nonsense in path).
					$image = wp_get_attachment_image_src( $attachment->ID, 'full' );
					// determine if in the $media image we created, the string of the URL exists.
					$return = set_post_thumbnail( $var_id, $attachment->ID );
					// update square img id to prevent downloading it again each synch.
					update_post_meta( $var_id, 'square_var_img_id', $var_image['image_id'] );
					// only want one image.
					if ( strpos( $media, $image[0] ) !== false ) {
						// if so, we found our image. set it as thumbnail.
						$return = set_post_thumbnail( $var_id, $attachment->ID );

						// update square img id to prevent downloading it again each synch.
						update_post_meta( $var_id, 'square_var_img_id', $var_image['image_id'] );
						// only want one image.
						break;
					}
				}
			}
		}
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

		if ( isset( $inventory_array[ $variation->id ] ) ) {

			if ( get_post_meta( $product_id, 'track_inventory_check', true ) === 'off' ) {

				update_post_meta( $product_id, '_stock_status', 'instock' );
				wc_update_product_stock( $woocmmerce_instance, $inventory_array[ $variation->id ] );

			} elseif ( get_post_meta( $product_id, 'track_inventory_check', true ) === 'on' ) {

				if ( empty( $inventory_array[ $variation->id ] ) || $inventory_array[ $variation->id ] <= 0 ) {
					update_post_meta( $product_id, '_stock_status', 'outofstock' );
					wc_update_product_stock( $woocmmerce_instance, $inventory_array[ $variation->id ] );
				} elseif ( empty( $inventory_array[ $variation->id ] ) || $inventory_array[ $variation->id ] > 0 ) {
					update_post_meta( $product_id, '_stock_status', 'instock' );
					wc_update_product_stock( $woocmmerce_instance, $inventory_array[ $variation->id ] );
				}
			}
		} else { // phpcs:ignore

			if ( get_post_meta( $product_id, 'track_inventory_check', true ) === 'off' ) {

				update_post_meta( $product_id, '_stock_status', 'instock' );
				if ( isset( $inventory_array[ $variation->id ] ) ) {
					wc_update_product_stock( $woocmmerce_instance, $inventory_array[ $variation->id ] );
				}
			} elseif ( get_post_meta( $product_id, 'track_inventory_check', true ) === 'on' ) {
				if ( isset( $variation->id ) ) {
					if ( empty( $inventory_array[ $variation->id ] ) || $inventory_array[ $variation->id ] <= 0 ) {
						update_post_meta( $product_id, '_stock_status', 'outofstock' );
						if ( isset( $inventory_array[ $variation->id ] ) ) {
							wc_update_product_stock( $woocmmerce_instance, $inventory_array[ $variation->id ] );
						}
					} elseif ( empty( $inventory_array[ $variation->id ] ) || $inventory_array[ $variation->id ] > 0 ) {
						update_post_meta( $product_id, '_stock_status', 'instock' );
						if ( isset( $inventory_array[ $variation->id ] ) ) {
							wc_update_product_stock( $woocmmerce_instance, $inventory_array[ $variation->id ] );
						}
					}
				}
			}
		}
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
		$show_count     = 0;      // 1 for yes, 0 for no
		$pad_counts     = 0;      // 1 for yes, 0 for no
		$hierarchical   = 1;      // 1 for yes, 0 for no
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

				if ( in_array( $cats[0], $terms_id, false ) ) { // phpcs:ignore

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

		foreach ( $square_items as $square_product ) {
			// Simple square product.
			if ( isset( $square_product->variations ) ) {
				if ( count( $square_product->variations ) <= 1 ) {

					if ( isset( $square_product->variations[0] ) && isset( $square_product->variations[0]->sku ) && $square_product->variations[0]->sku ) {
						$square_product_sku         = $square_product->variations[0]->sku;
						$product_id_with_sku_exists = $this->check_if_product_with_sku_exists( $square_product_sku, array( 'product', 'product_variation' ) );
						if ( ! $product_id_with_sku_exists ) { // SKU already exists in other product.
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

					// if any sku was found linked to a woo product-> skip this product
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

							if ( $this->check_if_product_with_sku_exists( $variation->sku, array( 'product', 'product_variation' ) ) ) {
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

		$response   = array();
		$interval   = 0;
		$response   = $square->wp_remote_woosquare( $url, $args, $method, $headers, $response );
		$object_old = json_decode( $response['body'], true );

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

			if ( 'ITEM' === $vals['type'] && 'REGULAR' === $vals['item_data']['product_type'] && ( 1 === (int) $vals['present_at_all_locations'] || in_array( $woo_square_location_id, $vals['present_at_location_ids'] ?? array(), true ) ) ) {

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
							$vl['item_variation_data']['track_inventory']      = isset( $vl['item_variation_data']['location_overrides'][0]['track_inventory'] ) ? $vl['item_variation_data']['location_overrides'][0]['track_inventory'] : null;
							$vl['item_variation_data']['inventory_alert_type'] = isset( $vl['item_variation_data']['location_overrides'][0]['inventory_alert_type'] ) ? $vl['item_variation_data']['location_overrides'][0]['inventory_alert_type'] : null;

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
		$after_date             = gmdate( 'Y-m-d', strtotime( '-12 month' ) ) . 'T00:00:00Z';
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
