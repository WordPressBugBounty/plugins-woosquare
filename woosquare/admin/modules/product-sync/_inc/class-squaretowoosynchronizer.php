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

		require_once plugin_dir_path( __DIR__ ) . '_inc/class-helpers.php';
		$this->square = $square;
	}

	/**
	 * Sync All products, categories from Square to Woo-Commerce
	 */
	public function sync_from_square_to_woo() {

		$sync_type      = Helpers::SYNC_TYPE_AUTOMATIC;
		$sync_direction = Helpers::SYNC_DIRECTION_SQUARE_TO_WOO;
		// add start sync log record.
		$log_id = Helpers::sync_db_log(
			Helpers::ACTION_SYNC_START,
			gmdate( 'Y-m-d H:i:s' ),
			$sync_type,
			$sync_direction
		);

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

		// add/update square categories.
		foreach ( $square_categories as $cat ) {

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

			} else {          // add.
				$result = $this->add_category_to_woo( $cat );
				if ( false !== $result['status'] ) {
					update_option( "is_square_sync_{$result['id']}", 1 );
					$target_id = $result['id'];

				}
				$action = Helpers::ACTION_ADD;
			}

			$_SESSION['square_product_sync_log'][ $result['id'] ][ $result['pro_status'] ] = $result;

			$session_square_product_sync_log = isset( $_SESSION['square_product_sync_log'] ) ? sanitize_text_field( wp_unslash( $_SESSION['square_product_sync_log'] ) ) : '';
			$session_log_id                  = isset( $_SESSION['log_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['log_id'] ) ) : '';
			$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
			if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
				$woosquare_sync_log              = new WooSquare_Sync_Logs();
				$log_id                          = $woosquare_sync_log->log_data_request( $session_square_product_sync_log, $session_log_id, 'square_to_woo', 'category' );
				if ( ! empty( $log_id ) ) {
					$_SESSION['log_id'] = $log_id;
				}
			}
			// log category action.
			Helpers::sync_db_log(
				$action,
				gmdate( 'Y-m-d H:i:s' ),
				$sync_type,
				$sync_direction,
				$target_id,
				Helpers::TARGET_TYPE_CATEGORY,
				$result['status'] ? Helpers::TARGET_STATUS_SUCCESS : Helpers::TARGET_STATUS_FAILURE,
				$log_id,
				$cat->category_data->name,
				$cat->id
			);
		}

		// 2-Update WooCommerce with products from Square.

		if ( $square_items ) {
			foreach ( $square_items as $square_product ) {

				/* get Inventory of all items */

				$array                      = json_decode( wp_json_encode( $square_product->variations ), true );
					$square_inventory       = $this->get_square_inventory( $array );
					$square_inventory_array = array();
				if ( ! empty( $square_inventory ) ) {
					$square_inventory_array = $this->convert_square_inventory_to_associative( $square_inventory->counts );
				}

				$action = null;

				$id = $this->add_product_to_woo( $square_product, $square_inventory_array, $action );

				if ( is_null( $action ) ) {
					continue;
				}
				$result = ( false !== $id ) ? Helpers::TARGET_STATUS_SUCCESS : Helpers::TARGET_STATUS_FAILURE;

				if ( ! empty( $id ) && is_numeric( $id ) ) {
					update_post_meta( $id, 'is_square_sync', 1 );
				}

				// log.
				Helpers::sync_db_log(
					$action,
					gmdate( 'Y-m-d H:i:s' ),
					Helpers::SYNC_TYPE_MANUAL,
					Helpers::SYNC_DIRECTION_SQUARE_TO_WOO,
					is_numeric( $id ) ? $id : null,
					Helpers::TARGET_TYPE_PRODUCT,
					$result,
					$log_id,
					$square_product->name,
					$square_product->id
				);
			}
		}
	}

	/**
	 * Inserts a new category to WooCommerce.
	 *
	 * This function takes a Square category object as a parameter and creates a new WooCommerce category based on its information.
	 *
	 * @param object $category A Square category object.
	 *
	 * @return void
	 */
	public function insert_category_to_woo( $category ) {
		$product_categories = get_terms( 'product_cat' );
		foreach ( $product_categories as $categoryw ) {
			$woo_categories[] = array(
				'square_id' => get_option( 'category_square_id_' . $categoryw->term_id ),
				'name'      => $categoryw->name,
				'term_id'   => $categoryw->term_id,
			);
		}

		$woo_category = Helpers::search_in_multi_dimension_array( $woo_categories, 'square_id', $category->id );
		$slug         = $category->name;
		remove_action( 'edited_product_cat', 'woo_square_edit_category' );
		remove_action( 'create_product_cat', 'woo_square_add_category' );

		if ( $woo_category ) {
			wp_update_term(
				$woo_category['term_id'],
				'product_cat',
				array(
					'name' => $category->name,
					'slug' => $slug,
				)
			);
			update_option( 'category_square_id_' . $woo_category['term_id'], $category->id );
		} else {
			$result = wp_insert_term( $category->name, 'product_cat', array( 'slug' => $slug ) );
			if ( ! is_wp_error( $result ) && isset( $result['term_id'] ) ) {
				update_option( 'category_square_id_' . $result['term_id'], $category->id );
			}
		}
		add_action( 'edited_product_cat', 'woo_square_edit_category' );
		add_action( 'create_product_cat', 'woo_square_add_category' );
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
		}

		$ret_val = false;
		$slug    = $category->category_data->name;
		remove_action( 'edited_product_cat', 'woo_square_edit_category' );
		remove_action( 'create_product_cat', 'woo_square_add_category' );

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

		$slug = isset( $category->category_data->name ) ? $category->category_data->name : $category->name;
		remove_action( 'edited_product_cat', 'woo_square_edit_category' );
		remove_action( 'create_product_cat', 'woo_square_add_category' );

		$asasa = wp_update_term(
			$cat_id,
			'product_cat',
			array(
				'name' => $slug,
				'slug' => $slug,
			)
		);
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

		// Simple square product.
		if ( isset( $square_product->variations ) ) {
			if ( count( $square_product->variations ) <= 1 ) {
				if ( isset( $square_product->variations[0] ) && isset( $square_product->variations[0]->item_variation_data->sku ) && $square_product->variations[0]->item_variation_data->sku ) {
					$square_product_sku         = $square_product->variations[0]->item_variation_data->sku;
					$product_id_with_sku_exists = $this->check_if_product_with_sku_exists( $square_product_sku, array( 'product', 'product_variation' ) );

					if ( $product_id_with_sku_exists ) { // SKU already exists in other product.
						$product   = get_post( $product_id_with_sku_exists[0] );
						$parent_id = $product->post_parent;

						$id = $this->insert_simple_product_to_woo( $square_product, $square_inventory, $product_id_with_sku_exists[0] );

						if ( $parent_id ) {
							if ( get_option( 'disable_auto_delete' ) !== 1 ) {
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

				woo_square_plugin_sync_square_modifier_to_woo( $id['id'], $square_product );

			}
		}

		return $id;
	}

	/**
	 * Create a variable WooCommerce product.
	 *
	 * @param string      $title           The title of the product.
	 * @param string      $desc            The product description.
	 * @param array       $variations      An array of product variations.
	 * @param string      $variations_key  The key for variations.
	 * @param array       $cats            An array of product categories.
	 * @param int|null    $product_square_id The Square product ID.
	 * @param object|null $master_image    The master image for the product.
	 * @param int|null    $parent_id       The ID of the parent product (if this is a variation).
	 *
	 * @return int The ID of the newly created product.
	 */
	public function create_variable_woo_product( $title, $desc, $variations, $variations_key, $cats = array(), $product_square_id = null, $master_image = null, $parent_id = null ) {

		$varkey                 = explode( '[', $variations[0]['name'] );
		$variations_key         = $varkey[0];
		$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		$square                 = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );
		$woocommerce_currency   = get_option( 'woocommerce_currency' );
		$post                   = array(
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
		// Create product/post:.
		remove_action( 'save_post', 'woo_square_add_edit_product' );
		$new_prod_id = wp_insert_post( $post );
		add_action( 'save_post', 'woo_square_add_edit_product', 10, 3 );
		// make product type be variable:.
		wp_set_object_terms( $new_prod_id, 'variable', 'product_type' );
		// add category to product:.
		wp_set_object_terms( $new_prod_id, $cats, 'product_cat' );
		// ################### Add size attributes to main product: ####################
		// Array for setting attributes
		$var_keys  = array();
		$total_qty = 0;

		foreach ( $variations as $variation ) {

			$variation['name']  = $variation['name'];
			$variationsexploded = explode( ',', $variation['name'] );
			if ( is_array( $variationsexploded ) ) {

				foreach ( $variationsexploded as $attrnames ) {
					$varkeys           = explode( '[', $attrnames );
					$variation['name'] = $varkeys[1];
					$variation['name'] = str_replace( ']', '', $attrnames );
					$total_qty        += (int) isset( $variation['qty'] ) ? $variation['qty'] : 0;
					$varkeys           = explode( '[', $variation['name'] );

					$var_keyss[]                    = $varkeys[0];
					$variatioskeys[ $varkeys[0] ][] = $varkeys[1];
				}

				$var_keyss = array_unique( $var_keyss, SORT_REGULAR );

				$var_keyss['variations_keys'] = $variatioskeys;
				$var_keys                     = array();
				$var_keys                     = $var_keyss;
			} else {
				$varkeys           = explode( '[', $variation['name'] );
				$variation['name'] = $varkeys[1];
				$variation['name'] = str_replace( ']', '', $variation['name'] );
				$total_qty        += (int) isset( $variation['qty'] ) ? $variation['qty'] : 0;
				$var_keys[]        = $variation['name'];
			}
		}

		wp_set_object_terms( $new_prod_id, $var_keys, $variations_key );
		if ( isset( $var_keys ) && is_array( $var_keys ) ) {
			foreach ( $var_keys as $key => $attrkeys ) {
				if ( is_numeric( $key ) ) {
					global $wpdb;
					$get_result = 'get_results';
					$term_query = $wpdb->$get_result( 'SELECT * FROM `' . $wpdb->prefix . "term_taxonomy` WHERE `taxonomy` = 'pa_" . strtolower( $attrkeys ) . "'" );
					$attr       = $wpdb->$get_result( 'SELECT * FROM `' . $wpdb->prefix . "woocommerce_attribute_taxonomies` WHERE `attribute_name` = '" . strtolower( $attrkeys ) . "'" );
				}

				if ( ! empty( $attrkeys ) && ! is_array( $attrkeys ) ) {

					$variations_keys = array_unique( $var_keys['variations_keys'][ $attrkeys ] );
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
						foreach ( $variations_keys as $termname ) {
							$termname = strtolower( $termname );

							if ( ! empty( $terms_name ) ) {
								if ( ! in_array( $termname, $terms_name, true ) && ! empty( $termname ) ) {
									$term = wp_insert_term(
										$termname, // the term.
										'pa_' . strtolower( $attrkeys ), // the taxonomy.
										array(
											'description' => '',
											'slug'        => strtolower( $termname ),
											'parent'      => '',
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
						$global_attr[] = $attrkeys;
						if ( ! empty( $variations_keys ) ) {
							foreach ( $variations_keys as $arry ) {
								$var_ontersect[] = strtolower( $arry );
							}
						}
						$terms_name = array_intersect( $terms_name, $var_ontersect );
						wp_set_object_terms( $new_prod_id, $terms_name, 'pa_' . strtolower( $attrkeys ) );
					} else {
						$variations_keys      = array_unique( $var_keys['variations_keys'][ $attrkeys ] );
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
		update_post_meta( $new_prod_id, '_product_attributes', $thedata );

		// wp_set_object_terms( $new_prod_id, array(16,15,17), 'pa_color');
		// ########################## Done adding attributes to product #################
		// set product values:
		// update_post_meta($new_prod_id, '_stock_status', ( (int) $total_qty > 0) ? 'instock' : 'outofstock');.
		update_post_meta( $new_prod_id, '_stock_status', 'instock' );
		$woocmmerce_instance = new WC_Product( $new_prod_id );
		wc_update_product_stock( $woocmmerce_instance, $total_qty );
		update_post_meta( $new_prod_id, '_visibility', 'visible' );
		update_post_meta( $new_prod_id, 'square_id', $product_square_id );
		update_post_meta( $new_prod_id, '_default_attributes', array() );
		// ###################### Add Variation post types for sizes #############################
		$i          = 1;
		$var_prices = array();
		// set IDs for product_variation posts:.
		$args                    = array(
			'post_type'   => 'product_variation',
			'post_status' => array( 'private', 'publish' ),
			'numberposts' => -1,
			'orderby'     => 'menu_order',
			'order'       => 'asc',
			'post_parent' => $new_prod_id,
		);
		$variation_already_exist = get_posts( $args );
		if ( ! empty( $variation_already_exist ) ) {
			foreach ( $variation_already_exist as $variation_exi ) {
				$variation_already_exist_arr[] = $variation_exi->ID;
			}
		}
		foreach ( $variations as $variation ) {

			$variation_forsetobj = $variation;
			$variation['name']   = $variation['name'];
			$varkeys             = explode( '[', $variation['name'] );
			$variation['name']   = $varkeys[1];
			$variation['name']   = str_replace( ']', '', $variation['name'] );
			$my_post             = array(
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
			// Insert ea. post/variation into database:.
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
			// Create 2xl variation for ea product_variation:.
			$variation_forsetobj['name'] = $variation_forsetobj['name'];
			$variation_values            = explode( ',', $variation_forsetobj['name'] );
			foreach ( $variation_values as $values ) {
				$getting_attr_n_variation_name = explode( '[', $values );
				$pa                            = '';
				if ( ! empty( $global_attr ) ) {
					if ( in_array( $getting_attr_n_variation_name[0], $global_attr, true ) ) {
						$pa = 'pa_';
					}
				}
				update_post_meta( $att_id, 'attribute_' . $pa . $getting_attr_n_variation_name[0], sanitize_title( str_replace( ']', '', $getting_attr_n_variation_name[1] ) ) );
			}

			update_post_meta( $att_id, '_price', $square->format_amount( ( $variation['price'] ), 'sqtowo', $woocommerce_currency ) );
			update_post_meta( $att_id, '_regular_price', $square->format_amount( ( $variation['price'] ), 'sqtowo', $woocommerce_currency ) );

			$var_prices[ $i - 1 ]['id']            = $att_id;
			$var_prices[ $i - 1 ]['regular_price'] = sanitize_title( $square->format_amount( $variation['price'], 'sqtowo', $woocommerce_currency ) );

			// add size attributes to this variation:.
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
		}
		// delete those variation that delete from square..
		if ( ! empty( $proid ) && ! empty( $variation_already_exist_arr ) ) {
			$inter = array_diff( $variation_already_exist_arr, $proid );
			if ( ! empty( $inter ) ) {
				foreach ( $inter as $key ) {
					wp_delete_post( $key, true );
				}
			}
		}
		$i = 0;
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

		// for refreshing transient.
		$children_transient_name = 'wc_product_children_' . $new_prod_id;
		delete_transient( $children_transient_name );

		if ( isset( $master_image ) && ! empty( $master_image->url ) ) {
			// if square img id not found, download new image.
			if ( strcmp( get_post_meta( $new_prod_id, 'square_master_img_id', true ), $master_image->id ) ) {
				$this->upload_featured_image( $new_prod_id, $master_image );
			}
		}
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

		$term_id = 0;
		if ( isset( $square_product->category ) ) {
			$wp_category = get_term_by( 'name', $square_product->category->name, 'product_cat' );
			$term_id     = isset( $wp_category->term_id ) ? $wp_category->term_id : 0;
		}

		// Try to get the product id from the SKU if set.
		$product_ids                = array();
		$product_id_with_sku_exists = false;

		foreach ( $square_product->variations as $variation ) {
			$square_product_sku = $variation->item_variation_data->sku;
			if ( $square_product_sku ) {
				$product_id_with_sku_exists = $this->check_if_product_with_sku_exists( $square_product_sku, array( 'product', 'product_variation' ) );
			}
			if ( $product_id_with_sku_exists ) {
				$product_ids[ $square_product_sku ] = $product_id_with_sku_exists[0];
			}
		}

		if ( isset( $product_ids ) && ! empty( $product_ids ) ) {

			// SKU already exits.
			$product = get_post( reset( $product_ids ) );
			if ( is_object( $product ) ) {
				$parent_id = $product->post_parent;
			}
			$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
			if ( $parent_id ) { // woo product is variable.
				$variations = array();
				foreach ( $square_product->variations as $variation ) {

					// don't add product variaton that doesn't have SKU.
					if ( empty( $variation->item_variation_data->sku ) ) {
						continue;
					}
					$price = isset( $variation->item_variation_data->price_money->amount ) ? ( $variation->item_variation_data->price_money->amount ) : '';
					$data  = array(
						'variation_id'    => $variation->id,
						'sku'             => $variation->item_variation_data->sku,
						'name'            => $variation->item_variation_data->name,
						'price'           => $price,
						'track_inventory' => $variation->track_inventory,
					);

					// put variation product id in variation data to be updated.
					// instead of created.
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
				$prod_img         = isset( $square_product->master_image ) ? $square_product->master_image : null;
				$id               = $this->create_variable_woo_product( $square_product->name, $prod_description, $variations, 'variation', array( $term_id ), $square_product->id, $prod_img, $parent_id );
			} else { // woo product is simple.
				$variations = array();

				foreach ( $square_product->variations as $variation ) {

					// don't add product variaton that doesn't have SKU.
					if ( empty( $variation->item_variation_data->sku ) ) {
						continue;
					}
					$price = isset( $variation->item_variation_data->price_money->amount ) ? ( $variation->item_variation_data->price_money->amount ) : '';
					$data  = array(
						'variation_id'    => $variation->id,
						'sku'             => $variation->item_variation_data->sku,
						'name'            => $variation->item_variation_data->name,
						'price'           => $price,
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
				$prod_img         = isset( $square_product->image_data->image_data ) ? $square_product->image_data->image_data : null;
				$id               = $this->create_variable_woo_product( $square_product->name, $prod_description, $variations, 'variation', array( $term_id ), $square_product->id, $prod_img );
			}
			$action = Helpers::ACTION_UPDATE;
		} else { // SKU not exists.
			$variations   = array();
			$no_sku_count = 0;

			foreach ( $square_product->variations as $variation ) {

				// don't add product variaton that doesn't have SKU.
				if ( empty( $variation->sku ) ) {
					++$no_sku_count;
					continue;
				}
				$price = isset( $variation->price_money->amount ) ? ( $variation->price_money->amount ) : '';

				$data = array(
					'variation_id'    => $variation->id,
					'sku'             => $variation->sku,
					'name'            => $variation->name,
					'price'           => $price,
					'track_inventory' => $variation->track_inventory,
				);
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
			$prod_img         = isset( $square_product->image_data->image_data->url ) ? $square_product->image_data->image_data : null;
			$id               = $this->create_variable_woo_product( $square_product->name, $prod_description, $variations, 'variation', array( $term_id ), $square_product->id, $prod_img );
			$action           = Helpers::ACTION_ADD;
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
			$attribute['attribute_type'] = 'text';}
		if ( empty( $attribute['attribute_orderby'] ) ) {
			$attribute['attribute_orderby'] = 'menu_order';}

		if ( empty( $attribute['attribute_public'] ) ) {
			$attribute['attribute_public'] = 0;}
		// maybe error.
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

		$term_id = 0;

		if ( isset( $square_product->category ) ) {
			$wp_category = get_term_by( 'name', $square_product->category->name, 'product_cat' );
			if ( isset( $wp_category->term_id ) ) {
				$term_id = $wp_category->term_id;
			} else {
				$result = $this->add_category_to_woo( $square_product->category );
				if ( false !== $result['status'] ) {
					update_option( "is_square_sync_{$result['id']}", 1 );
					$term_id = $result['id'];

				}
				$_SESSION['square_product_sync_log'][ $term_id ][ $result['pro_status'] ] = $result;

			}
		}

		$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		$woocommerce_currency   = get_option( 'woocommerce_currency' );
		$square                 = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );
		$post_title             = $square_product->name;
		$post_content           = isset( $square_product->description ) ? $square_product->description : '';

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

		wp_set_object_terms( $id, $term_id, 'product_cat' );
		add_action( 'save_post', 'woo_square_add_edit_product', 10, 3 );

		$is_attr_vari = explode( ',', $square_product->variations[0]->item_variation_data->name );
		$get_result   = 'get_results';

		if ( is_array( $is_attr_vari ) && strpos( $square_product->variations[0]->item_variation_data->name, ',' ) !== false ) {
			foreach ( $is_attr_vari as $attrr ) {
				$attrname  = explode( '[', $attrr );
				$attrterms = str_replace( ']', '', $attrname[1] );
				$tername   = explode( '|', $attrterms );

				$attrexpl = explode( '[', $attrr );
				global $wpdb;
				$attr = $wpdb->$get_result( 'SELECT * FROM `' . $wpdb->prefix . "woocommerce_attribute_taxonomies` WHERE `attribute_name` = '" . strtolower( $attrexpl[0] ) . "'" );

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
						$get_resul = $wpdb->$get_result( 'SELECT * FROM `' . $wpdb->prefix . "terms` WHERE `slug` = '" . strtolower( $ternameval ) . "' ORDER BY `name` ASC", true );

						if ( ! empty( $get_resul[0] ) ) {
							// INSERT INTO wp_term_relationships (object_id,term_taxonomy_id) VALUES ([the_id_of_above_post],1).
							$pref                   = $wpdb->prefix;
							$get_term_relationships = $wpdb->$get_result( 'SELECT * FROM `' . $pref . "term_relationships` WHERE `object_id` = '" . $id . "' AND `term_taxonomy_id` = '" . $get_resul[0]->term_id . "' AND `term_order` = '0'", true );
							if ( empty( $get_term_relationships[0] ) ) {
								$insert = 'insert';
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
			$attr = $wpdb->$get_result( 'SELECT * FROM `' . $wpdb->prefix . "woocommerce_attribute_taxonomies` WHERE `attribute_name` = '" . strtolower( $attrexpl[0] ) . "'" );
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
				$term_query = $wpdb->$get_result( 'SELECT * FROM `' . $wpdb->prefix . "term_taxonomy` WHERE `taxonomy` = 'pa_" . strtolower( $attr[0]->attribute_name ) . "'" );
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
			$variation = $square_product->variations[0];
			$price     = isset( $variation->item_variation_data->price_money->amount ) ? ( $variation->item_variation_data->price_money->amount ) : '';
			update_post_meta( $id, '_visibility', 'visible' );
			update_post_meta( $id, '_stock_status', 'instock' );

			update_post_meta( $id, '_regular_price', $square->format_amount( $price, 'sqtowo', $woocommerce_currency ) );

			update_post_meta( $id, '_price', $square->format_amount( $price, 'sqtowo', $woocommerce_currency ) );
			update_post_meta( $id, '_sku', isset( $variation->item_variation_data->sku ) ? $variation->item_variation_data->sku : '' );

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
			if ( isset( $square_product->master_image ) && ! empty( $square_product->master_image->url ) ) {

				// if square img id not found, download new image.
				if ( strcmp( get_post_meta( $id, 'square_master_img_id', true ), $square_product->master_image->id ) ) {
					$this->upload_featured_image( $id, $square_product->master_image );
				}
			}
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

		$_SESSION['square_product_delete_sync_log'][ $product->ID ]['delete'] = $delt_pro_array;

		$session_square_product_delete_sync_log = isset( $_SESSION['square_product_delete_sync_log'] ) ? sanitize_text_field( wp_unslash( $_SESSION['square_product_delete_sync_log'] ) ) : '';
		$session_delete_product_log_id          = isset( $_SESSION['delete_product_log_id'] ) ? sanitize_text_field( wp_unslash( $_SESSION['delete_product_log_id'] ) ) : '';
		$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ) );
		if($activate_modules_woosquare_plus['items_sync_log']['module_activate'] == true){
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
	 * @return array|false If a product with the SKU exists, an array containing the product ID is returned. If not found, returns false.
	 */
	public function check_if_product_with_sku_exists( $square_product_sku ) {

		global $wpdb;
		$get_var    = 'get_var';
		$product_id = $wpdb->$get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value=%s LIMIT 1", $square_product_sku ) );
		$new[]      = $product_id;

		// $ids = $query->posts;
		// do something if the meta-key-value-pair exists in another post
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

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Add Featured Image to Post.
		$image = $master_image->url; // Define the image URL here.
		// magic sideload image returns an HTML image, not an ID.
		$media = media_sideload_image( $image, $product_id );

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
					if ( strpos( $media, $image[0] ) !== false ) {
						// if so, we found our image. set it as thumbnail.
						set_post_thumbnail( $product_id, $attachment->ID );

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
		} elseif ( get_post_meta( $product_id, 'track_inventory_check', true ) === 'off' ) {

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
	}

	/**
	 * Get Square categories from the Square API.
	 *
	 * @return array|false Array of Square categories or false on failure.
	 */
	public function get_square_categories() {
		/* get all categories */

		$url     = esc_url( 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/list' );
		$headers = array(
			'Authorization' => 'Bearer ' . $this->square->get_access_token(), // Use verbose mode in cURL to determine the format you want for this header.
			'Content-Type'  => 'application/json',
			'types'         => 'CATEGORY',
		);

		$method                 = 'GET';
		$args                   = array( 'types' => 'CATEGORY' );
		$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );

		$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );

		// check transient expire or not..
		// delete_transient(  $woo_square_location_id.'transient_'.__FUNCTION__ );.

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
		$get_result            = 'get_results';
		$prepare               = 'prepare';

		// return if empty square categories.
		if ( empty( $square_categories ) ) {
			return $woo_square_categories;
		}

		// get option keys for the given square id values.
		$option_values = '  ';
		foreach ( $square_categories as $square_category ) {

			$option_values .= "'{$square_category->id}',";
			$original_square_categories_array[ $square_category->id ] = $square_category->category_data->name;
		}
		$option_values               = substr( $option_values, 0, strlen( $option_values ) - 1 );
		//$option_values              .= '  ';
		//$categories_square_ids_query = ;
       
		$results = $wpdb->$get_result( $wpdb->$prepare( "SELECT option_name, option_value FROM {$wpdb->prefix}options WHERE option_value IN  (%s)",$option_values ), OBJECT );
     
		// select categories again to see if they need an update.
		$sync_query    = "SELECT term_id, name FROM {$wpdb->terms} WHERE term_id IN (";
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

		if ( ! empty( $all_categories ) ) {
			foreach ( $all_categories as $keyscategories => $catsterms ) {
				$terms_id[] = $catsterms->term_id;
			}

			foreach ( $woo_square_categories as $keys => $cats ) {

				if ( in_array( $cats[0], $terms_id, true ) ) {

					$returnarray[ $keys ] = $cats;
				}
			}
		}
		return $woo_square_categories;
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
			if ( isset($square_product->variations) AND count( $square_product->variations ) <= 1 ) {

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
				if ( 
				 isset($square_product->variations) AND 
				count( $square_product->variations ) === $no_sku_count ) {
					$skipped_products[] = $square_product->id;
				} elseif ( $add_flag ) {             // sku exists but not found in woo.
					$new_products[] = $square_product;
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

		// check transient expire or not..

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

		$url     = esc_url( 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/list' );
		$headers = array(
			'Authorization' => 'Bearer ' . $this->square->get_access_token(), // Use verbose mode in cURL to determine the format you want for this header.
			'Content-Type'  => 'application/json',
			'types'         => 'ITEM,MODIFIER_LIST,CATEGORY,IMAGE',
		);

		$method                 = 'GET';
		$args                   = array( 'types' => 'ITEM,MODIFIER_LIST,CATEGORY,IMAGE' );
		$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		$square                 = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );

		$response = array();
		$interval = 0;
		if ( get_option( '_transient_timeout_' . $woo_square_location_id . 'transient_' . __FUNCTION__ ) > time() ) {

			$response   = get_transient( $woo_square_location_id . 'transient_' . __FUNCTION__ );
			$object_old = json_decode( $response['body'], true );

		} else {

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

		}

			$object_new    = array();
			$modifier_list = array();
			$category_list = array();
			$image_list    = array();
			$ky            = 0;

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

			if ( 'ITEM' === $vals['type'] && 'REGULAR' === $vals['item_data']['product_type'] ) {

				$object_new[ $ky ] = (object) array(
					'fees' => array(),
				);

				foreach ( $vals['item_data']['variations'] as $kys => $vl ) {

					if ( isset( $vl['item_variation_data']['price_money'] ) && isset( $vl['item_variation_data']['price_money']['currency'] ) ) {
						$vl['item_variation_data']['price_money']['currency_code'] = $vl['item_variation_data']['price_money']['currency'];
					}
					$vl['item_variation_data']['track_inventory']      = isset( $vl['item_variation_data']['location_overrides'][0]['track_inventory'] ) ? $vl['item_variation_data']['location_overrides'][0]['track_inventory'] : null;
					$vl['item_variation_data']['inventory_alert_type'] = isset( $vl['item_variation_data']['location_overrides'][0]['inventory_alert_type'] ) ? $vl['item_variation_data']['location_overrides'][0]['inventory_alert_type'] : null;

					// pricing_type.
					unset( $vl['item_variation_data']['price_money']['currency'] );
					$object_new[ $ky ]->variations[ $kys ]                      = (object) $vl['item_variation_data'];
					$object_new[ $ky ]->variations[ $kys ]->item_variation_data = json_decode( wp_json_encode( $vl['item_variation_data'] ) );
					if ( isset( $vl['item_variation_data']['price_money'] ) ) {
						$object_new[ $ky ]->variations[ $kys ]->price_money = (object) $vl['item_variation_data']['price_money'];
					}
					$object_new[ $ky ]->variations[ $kys ]->version = $vl['version'];

					if ( isset( $vl['catalog_v1_ids'] ) ) {
						$object_new[ $ky ]->variations[ $kys ]->catalog_v1_ids = $vl['catalog_v1_ids'];
					}

					$object_new[ $ky ]->variations[ $kys ]->id = $vl['id'];

				}
				if ( ! empty( $vals['item_data']['modifier_list_info'] ) ) {
					foreach ( $vals['item_data']['modifier_list_info'] as $kys => $vl ) {

						$object_new[ $ky ]->modifier_list_info[ $kys ] = $vl;

					}
				}

				$object_new[ $ky ]->id      = $vals['id'];
				$object_new[ $ky ]->version = $vals['version'];

				if ( isset( $vals['catalog_v1_ids'] ) ) {
					$object_new[ $ky ]->catalog_v1_ids = $vals['catalog_v1_ids'];
				}

				$object_new[ $ky ]->name                 = $vals['item_data']['name'];
				$object_new[ $ky ]->description          = isset( $vals['item_data']['description'] ) ? $vals['item_data']['description'] : null;
				$object_new[ $ky ]->category_id          = isset( $vals['item_data']['category_id'] ) ? $vals['item_data']['category_id'] : null;
				$object_new[ $ky ]->visibility           = $vals['item_data']['visibility'];
				$object_new[ $ky ]->available_online     = isset( $vals['item_data']['available_online'] ) ? $vals['item_data']['available_online'] : null;
				$object_new[ $ky ]->available_for_pickup = isset( $vals['item_data']['available_for_pickup'] ) ? $vals['item_data']['available_for_pickup'] : null;

				if ( ! empty( $vals['image_id'] ) ) {
						$object_new[ $ky ]->master_image      = new \stdClass();
						$object_new[ $ky ]->master_image->id  = $vals['image_id'];
						$object_new[ $ky ]->master_image->url = isset( $vals['item_data']['ecom_image_uris'][0] ) ? $vals['item_data']['ecom_image_uris'][0] : null;
					if ( isset( $object_new[ $ky ] ) && is_object( $object_new[ $ky ] ) && property_exists( $object_new[ $ky ], 'image_data' ) ) {
						$object_new[ $ky ]->image_data->image_data->id  = $vals['image_id'];
						$object_new[ $ky ]->image_data->image_data->url = isset( $vals['item_data']['ecom_image_uris'][0] ) ? $vals['item_data']['ecom_image_uris'][0] : null;
					}
				}
			}

			++$ky;
		}
		foreach ( $object_new as $kym => $image ) {
			if ( ! empty( $image->master_image->id ) && empty( $image->master_image->url ) ) {
				foreach ( $image_list  as $imagelist ) {
					if ( $image->master_image->id === $imagelist['id'] ) {
						$object_new[ $kym ]->master_image->url = $imagelist['image_data']['url'];
						if ( isset( $object_new[ $kym ] ) && is_object( $object_new[ $kym ] ) && property_exists( $object_new[ $kym ], 'image_data' ) ) {
							$object_new[ $kym ]->image_data->image_data->url = $imagelist['image_data']['url'];
						}
					}
				}
			}
		}

		$keyyy = 0;
		foreach ( $object_new as $kym => $cat ) {
			if ( ! empty( $cat->category_id ) ) {

				foreach ( $category_list as $category ) {
					if ( ( isset( $category['catalog_v1_ids'][ $keyyy ]['catalog_v1_id'] ) && $category['catalog_v1_ids'][ $keyyy ]['catalog_v1_id'] === $cat->category_id ) || $category['id'] === $cat->category_id ) {
						if ( empty( $category['catalog_v1_ids'][ $keyyy ]['catalog_v1_id'] ) ) {
							$object_new[ $kym ]->category = (object) array(
								'id' => $cat->category_id,
							);
						} else {
							$object_new[ $kym ]->category = (object) $category['catalog_v1_ids'][ $keyyy ]['catalog_v1_id'];
						}
						$object_new[ $kym ]->category->name  = $category['category_data']['name'];
						$object_new[ $kym ]->category->v2_id = $cat->category_id;

					}
				}
			}
			++$keyyy;
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

		$variant_ids = array();
		if ( ! empty( $variations ) ) {
			foreach ( $variations as $variants ) {
				if ( ! empty( $variants['id'] ) ) {
					$variant_ids[] = $variants['id'];
				}
			}
		}

		/* get Inventory of all items */
		$url = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/inventory/batch-retrieve-counts';

		$headers = array(
			'Authorization' => 'Bearer ' . $this->square->get_access_token(), // Use verbose mode in cURL to determine the format you want for this header.
			'Content-Type'  => 'application/json;',
			'requesting'    => 'inventory',
		);

		$method = 'POST';

		$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
		$args                   = array(
			'catalog_object_ids' => $variant_ids,
			'location_ids'       =>
					array(
						0 => $woo_square_location_id,
					),
		);

		$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), $woo_square_location_id, WOOSQU_PLUS_APPID );

		$response = array();
		$interval = 0;

			$response = $square->wp_remote_woosquare( $url, $args, $method, $headers, $response );

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
