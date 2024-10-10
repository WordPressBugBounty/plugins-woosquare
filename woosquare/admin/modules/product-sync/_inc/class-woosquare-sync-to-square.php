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
 * Class WooSquare_Sync_To_Square
 *
 * Methods to Sync from WC to Square. Organized as "sync" methods that
 * determine if "create" or "update" actions should be taken on the entities
 * involved.
 */
class WooSquare_Sync_To_Square {

	/**
	 * Woosquare connect class object.
	 *
	 * @var WooSquare_Connect
	 */
	protected $connect;

	/**
	 * Constructor for the WooSquare_Sync_To_Square class.
	 *
	 * @param WooSquare_Connect $connect An instance of the WooSquare_Connect class used for connecting to Square.
	 */
	public function __construct( WooSquare_Connect $connect ) {
		add_filter( 'woocommerce_duplicate_product_exclude_meta', array( $this, 'duplicate_product_remove_meta' ) );

		$this->connect = $connect;
	}

	/**
	 * Removes certain product meta when a product is duplicated in WC to
	 * prevent overwriting the original item on Square.
	 *
	 * @access public
	 * @since 1.0.4
	 * @version 1.0.4
	 * @param array $metas The array of product meta to be modified.
	 * @return array The modified array of product meta.
	 */
	public function duplicate_product_remove_meta( $metas ) {
		$metas[] = '_square_item_id';
		$metas[] = '_square_item_variation_id';

		return $metas;
	}

	/**
	 * Sync WooCommerce categories to Square.
	 *
	 * Looks for category names that don't exist in Square, and creates them.
	 */
	public function sync_categories() {

		$wc_category_objects = $this->connect->wc->get_product_categories();
		$wc_categories       = array();

		if ( is_wp_error( $wc_category_objects ) || empty( $wc_category_objects['product_categories'] ) ) {
			return;
		}

		foreach ( $wc_category_objects['product_categories'] as $wc_category ) {

			if ( empty( $wc_category['name'] ) || empty( $wc_category['id'] ) || ( 0 !== $wc_category['parent'] ) ) {
				continue;
			}

			$wc_categories[ $wc_category['name'] ] = $wc_category['id'];

		}

		$square_category_objects = $this->connect->get_square_categories();
		$square_categories       = array();
		$processed_categories    = array();

		foreach ( $square_category_objects as $square_category ) {
			// Square list endpoints may return dups so we need to check for that.
			if ( in_array( $square_category->id, $processed_categories, true ) ) {
				continue;
			}

			if ( is_object( $square_category ) && ! empty( $square_category->name ) && ! empty( $square_category->id ) ) {
				$square_categories[ $square_category->name ] = $square_category->id;
				$processed_categories[]                      = $square_category->id;
			}
		}

		foreach ( $wc_categories as $wc_cat_name => $wc_cat_id ) {

			$square_cat_id   = WooSquare_Utils::get_wc_term_square_id( $wc_cat_id );
			$square_cat_name = array_search( $square_cat_id, $square_categories, true );
			if ( $square_cat_id && false !== $square_cat_name ) {
				// Update a known Square Category whose name has changed in WC.
				if ( $wc_cat_name !== $square_cat_name ) {

					$this->connect->update_square_category( $square_cat_id, $wc_cat_name );

				}
			} elseif ( isset( $square_categories[ $wc_cat_name ] ) ) {

				// Store the Square Category ID on a WC term that matches.
				$square_category_id = $square_categories[ $wc_cat_name ];

				WooSquare_Utils::update_wc_term_square_id( $wc_cat_id, $square_category_id );

			} else {

				// Create a new Square Category for a WC term that doesn't yet exist.
				$response = $this->connect->create_square_category( $wc_cat_name );

				if ( ! empty( $response->id ) ) {

					$square_category_id = $response->id;

					WooSquare_Utils::update_wc_term_square_id( $wc_cat_id, $square_category_id );

				}
			}
		}
	}

	/**
	 * Find the Square Item that corresponds to the given WooCommerce (WC) Product.
	 *
	 * This method first searches for a Square Item ID in the WC Product metadata.
	 * If a Square Item ID is found, it retrieves and returns the Square Item object.
	 * If no Square Item ID is found, it compares the WC Product SKUs against Square Items
	 * to find a matching Square Item.
	 *
	 * @param WC_Product $wc_product The WooCommerce Product to find the corresponding Square Item for.
	 * @return object|bool The Square Item object on success, boolean false if no Square Item is found.
	 */
	public function get_square_item_for_wc_product( WC_Product $wc_product ) {

		$product_id = $wc_product->id;

		if ( 'variation' === $wc_product->product_type ) {

			$product_id = $wc_product->variation_id;

		}
		$square_item_id = WooSquare_Utils::get_wc_product_square_id( $product_id );
		if ( false !== $square_item_id ) {
			return $this->connect->get_square_product( $square_item_id );
		}

		$wc_product_skus = WooSquare_Utils::get_wc_product_skus( $wc_product );

		return $this->connect->square_product_exists( $wc_product_skus );
	}

	/**
	 * Sync a WooCommerce (WC) Product to Square, optionally including Categories and Inventory.
	 *
	 * This method allows you to synchronize a WC Product with Square.
	 *
	 * @param WC_Product $wc_product The WooCommerce Product to be synchronized with Square.
	 * @param bool       $include_category Whether to include Categories during synchronization (default is false).
	 * @param bool       $include_inventory Whether to include Inventory information during synchronization (default is false).
	 * @param bool       $include_image Whether to include Images during synchronization (default is false).
	 */
	public function sync_product( WC_Product $wc_product, $include_category = false, $include_inventory = false, $include_image = false ) {

		// Only sync products with a SKU.
		$wc_product_skus = WooSquare_Utils::get_wc_product_skus( $wc_product );

		if ( empty( $wc_product_skus ) ) {

			WooSquare_Sync_Logger::log( sprintf( '[WC -> Square] Skipping WC Product %d since it has no SKUs.', $wc_product->id ) );
			return;

		}

		// Look for a Square Item with a matching SKU.
		$square_item = $this->get_square_item_for_wc_product( $wc_product );

		// SKU found, update Item.
		if ( WooSquare_Utils::is_square_item_found( $square_item ) ) {

			$result = $this->update_product( $wc_product, $square_item, $include_category, $include_inventory );

			// No SKU found, create new Item.
		} else {

			$result = $this->create_product( $wc_product, $include_category, $include_inventory );

		}

		// Sync inventory if create/update was successful.
		// TODO: consider whether or not this should be part of sync_product()..
		if ( $result ) {

			if ( $include_inventory ) {

				$this->sync_inventory( $wc_product );

			}

			if ( $include_image ) {

				$this->sync_product_image( $wc_product, $result );

			}
		} else {

			WooSquare_Sync_Logger::log( sprintf( '[WC -> Square] Error syncing WC Product %d.', $wc_product->id ) );

		}
	}

	/**
	 * Sync the inventory of a WooCommerce (WC) Product with Square.
	 *
	 * This method synchronizes the inventory of a WC Product with Square. It checks for
	 * managed variations and updates the inventory quantities accordingly.
	 *
	 * @param WC_Product $wc_product The WooCommerce Product whose inventory will be synchronized with Square.
	 */
	public function sync_inventory( WC_Product $wc_product ) {

		$wc_variation_ids = WooSquare_Utils::get_stock_managed_wc_variation_ids( $wc_product );
		$square_inventory = $this->connect->get_square_inventory();

		foreach ( $wc_variation_ids as $wc_variation_id ) {

			$square_variation_id = WooSquare_Utils::get_wc_variation_square_id( $wc_variation_id );

			if ( $square_variation_id && isset( $square_inventory[ $square_variation_id ] ) ) {

				$wc_stock = (int) get_post_meta( $wc_variation_id, '_stock', true );

				$square_stock = (int) $square_inventory[ $square_variation_id ];

				$delta = $wc_stock - $square_stock;

				$result = $this->connect->update_square_inventory( $square_variation_id, $delta );

				if ( ! $result ) {

					WooSquare_Sync_Logger::log( sprintf( '[WC -> Square] Error syncing inventory for WC Product %d.', $wc_product->id ) );

				}
			}
		}
	}

	/**
	 * Create a Square Item for a WooCommerce (WC) Product.
	 *
	 * This method creates a Square Item that corresponds to the given WC Product.
	 *
	 * @param WC_Product $wc_product The WooCommerce Product for which to create a Square Item.
	 * @param bool       $include_category Whether to include Categories during creation (default is false).
	 * @param bool       $include_inventory Whether to include Inventory information during creation (default is false).
	 * @return object|bool The created Square Item object on success, boolean False on failure.
	 */
	public function create_product( WC_Product $wc_product, $include_category = false, $include_inventory = false ) {

		$square_item = $this->connect->create_square_product( $wc_product, $include_category, $include_inventory );

		if ( $square_item ) {

			WooSquare_Utils::set_square_ids_on_wc_product_by_sku( $wc_product, $square_item );

			return $square_item;

		}

		WooSquare_Sync_Logger::log( sprintf( '[WC -> Square] Error creating Square Item for WC Product %d.', $wc_product->id ) );

		return false;
	}

	/**
	 * Update a Square Item for a WooCommerce (WC) Product.
	 *
	 * This method updates the Square Item that corresponds to the given WC Product.
	 *
	 * @param WC_Product $wc_product The WooCommerce Product for which to update the Square Item.
	 * @param object     $square_item The existing Square Item object to be updated.
	 * @param bool       $include_category Whether to include Categories during the update (default is false).
	 * @param bool       $include_inventory Whether to include Inventory information during the update (default is false).
	 * @return object|bool The updated Square Item object on success, boolean False on failure.
	 */
	public function update_product( WC_Product $wc_product, $square_item, $include_category = false, $include_inventory = false ) {

		$square_item = $this->connect->update_square_product( $wc_product, $square_item->id, $include_category, $include_inventory );

		if ( ! $square_item ) {

			WooSquare_Sync_Logger::log( sprintf( '[WC -> Square] Error updating Square Item ID %s (WC Product %d).', $square_item->id, $wc_product->id ) );
			return false;

		}

		WooSquare_Utils::update_wc_product_square_id( $wc_product->id, $square_item->id );

		if ( 'simple' === $wc_product->product_type ) {

			$wc_variations = array( $wc_product );

		} elseif ( 'variable' === $wc_product->product_type ) {

			$wc_variations = WooSquare_Utils::get_wc_product_variations( $wc_product );

		}

		foreach ( $wc_variations as $wc_variation ) {

			$variation_data      = WooSquare_Utils::format_wc_variation_for_square_api( $wc_variation, $include_inventory );
			$wc_variation_id     = ( 'variation' === $wc_variation->product_type ) ? $wc_variation->variation_id : $wc_variation->id;
			$square_variation_id = WooSquare_Utils::get_wc_variation_square_id( $wc_variation_id );
			if ( false !== $square_variation_id ) {

				$result = $this->connect->update_square_variation( $square_item->id, $square_variation_id, $variation_data );

			} else {

				$result = $this->connect->create_square_variation( $square_item->id, $variation_data );

				if ( $result && isset( $result->id ) ) {

					WooSquare_Utils::update_wc_variation_square_id( $wc_variation_id, $result->id );

				}
			}

			if ( ! $result ) {

				if ( $square_variation_id ) {

					WooSquare_Sync_Logger::log( sprintf( '[WC -> Square] Error updating Square ItemVariation %s for WC Variation %d.', $square_variation_id, $wc_variation_id ) );

				} else {

					WooSquare_Sync_Logger::log( sprintf( '[WC -> Square] Error creating Square ItemVariation for WC Variation %d.', $wc_variation_id ) );

				}
			}
		}

		return $square_item;
	}

	/**
	 * Sync a WC Product's Image to Square
	 *
	 * @param WC_Product $wc_product WC Product to sync Item Image for.
	 * @param object     $square_item Optional. Corresponding Square Item object for $wc_product.
	 *
	 * @return bool Success.
	 */
	public function sync_product_image( WC_Product $wc_product, $square_item = null ) {

		if ( is_null( $square_item ) ) {

			$square_item = $this->get_square_item_for_wc_product( $wc_product );

		}

		if ( ! $square_item ) {

			WooSquare_Sync_Logger::log( sprintf( '[WC -> Square] Image Sync: No Square Item found for WC Product %d.', $wc_product->id ) );
			return false;

		}

		if ( ! has_post_thumbnail( $wc_product->id ) ) {

			WooSquare_Sync_Logger::log( sprintf( '[WC -> Square] Image Sync: Skipping WC Product %d (no post thumbnail set).', $wc_product->id ) );
			return true;

		}

		return $this->update_product_image( $wc_product, $square_item->id );
	}

	/**
	 * Update a Square Item Image for a WooCommerce (WC) Product.
	 *
	 * This method updates the image of a Square Item that corresponds to the given WC Product.
	 *
	 * @param WC_Product $wc_product The WooCommerce Product for which to update the Square Item image.
	 * @param string     $square_item_id The ID of the Square Item to be updated with the new image.
	 * @return bool True on success, false on failure.
	 */
	public function update_product_image( WC_Product $wc_product, $square_item_id ) {

		$image_id = get_post_thumbnail_id( $wc_product->id );

		if ( empty( $image_id ) ) {

			WooSquare_Sync_Logger::log( sprintf( '[WC -> Square] Update Product Image: No thumbnail ID for WC Product %d.', $wc_product->id ) );
			return true;

		}

		$mime_type  = get_post_field( 'post_mime_type', $image_id, 'raw' );
		$image_path = get_attached_file( $image_id );

		$result = $this->connect->update_square_product_image( $square_item_id, $mime_type, $image_path );

		if ( $result && isset( $result->id ) ) {

			WooSquare_Utils::update_wc_product_image_square_id( $wc_product->id, $result->id );

			return true;

		} else {

			WooSquare_Sync_Logger::log( sprintf( '[WC -> Square] Error updating Product Image for WC Product %d.', $wc_product->id ) );
			return false;

		}
	}
}
