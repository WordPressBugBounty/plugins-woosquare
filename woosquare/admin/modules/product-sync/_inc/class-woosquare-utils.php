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
 * Class WooSquare_Utils
 *
 * Static helper methods for the WC <-> Square integration, used in multiple
 * places throughout the extension, with no dependencies of their own.
 *
 * Mostly data formatting and entity retrieval methods.
 */
class WooSquare_Utils {

	const WC_TERM_SQUARE_ID          = 'square_cat_id';
	const WC_PRODUCT_SQUARE_ID       = '_square_item_id';
	const WC_VARIATION_SQUARE_ID     = '_square_item_variation_id';
	const WC_PRODUCT_IMAGE_SQUARE_ID = '_square_item_image_id';

	/**
	 * Convert a WooCommerce (WC) Product or Variation into a Square ItemVariation.
	 *
	 * This method takes a WC Product or Variation and formats it as a Square ItemVariation
	 * for use in Square API requests.
	 *
	 * @param WC_Product|WC_Product_Variation $variation The WooCommerce Product or Variation to convert.
	 * @param bool                            $include_inventory Whether to include inventory details (default is false).
	 * @return array Formatted as a Square ItemVariation.
	 */
	public static function format_wc_variation_for_square_api( $variation, $include_inventory = false ) {

		$formatted = array(
			'name'                      => null,
			'pricing_type'              => null,
			'price_money'               => null,
			'sku'                       => null,
			'track_inventory'           => null,
			'inventory_alert_type'      => null,
			'inventory_alert_threshold' => null,
			'user_data'                 => null,
		);

		if ( $variation instanceof WC_Product ) {

			$formatted['name']        = __( 'Regular', 'woosquare' );
			$formatted['price_money'] = array(
				'currency_code' => apply_filters( 'woocommerce_square_currency', get_woocommerce_currency() ),
				'amount'        => $variation->get_display_price() * 100,
			);
			$formatted['sku']         = $variation->get_sku();

			if ( $include_inventory && $variation->managing_stock() ) {

				$formatted['track_inventory'] = true;

			}
		}

		if ( $variation instanceof WC_Product_Variation ) {

			$formatted['name'] = implode( ', ', $variation->get_variation_attributes() );

		}

		return array_filter( $formatted );
	}

	/**
	 * Convert a WooCommerce (WC) Product into a Square Item for update.
	 *
	 * When updating a Square Item, the parameters (e.g., variations) accepted may differ from creation.
	 * This method formats a WC Product for use in updating a Square Item via the Square API.
	 *
	 * @param WC_Product $wc_product The WooCommerce Product to convert for updating a Square Item.
	 * @param bool       $include_category Whether to include category information (default is false).
	 * @return array Formatted as a Square Item for update.
	 */
	public static function format_wc_product_update_for_square_api( WC_Product $wc_product, $include_category = false ) {

		$formatted = array(
			'name'        => $wc_product->get_title(),
			'description' => wp_strip_all_tags( $wc_product->post->post_content ),
			'visibility'  => 'PUBLIC',
		);

		if ( $include_category ) {

			$formatted['category_id'] = self::get_square_category_id_for_wc_product( $wc_product );

		}

		return array_filter( $formatted );
	}

	/**
	 * Convert a WooCommerce (WC) Product into a Square Item for creation.
	 *
	 * When creating a Square Item, more parameters (including variations) are allowed compared to updating.
	 * This method formats a WC Product for use in creating a Square Item via the Square API.
	 *
	 * @param WC_Product $wc_product The WooCommerce Product to convert for creating a Square Item.
	 * @param bool       $include_category Whether to include category information (default is false).
	 * @param bool       $include_inventory Whether to include inventory details (default is false).
	 * @return array Formatted as a Square Item for creation.
	 */
	public static function format_wc_product_create_for_square_api( WC_Product $wc_product, $include_category = false, $include_inventory = false ) {

		$formatted = self::format_wc_product_update_for_square_api( $wc_product, $include_category );

		// check product type.
		if ( 'simple' === $wc_product->product_type ) {

			$formatted['variations'] = array(
				self::format_wc_variation_for_square_api( $wc_product, $include_inventory ),
			);

		} elseif ( 'variable' === $wc_product->product_type ) {

			$wc_variations = self::get_wc_product_variations( $wc_product );

			foreach ( (array) $wc_variations as $wc_variation ) {

				$formatted['variations'][] = self::format_wc_variation_for_square_api( $wc_variation, $include_inventory );

			}
		}

		return array_filter( $formatted );
	}

	/**
	 * Map existing WooCommerce (WC) Variation IDs to a formatted product update array.
	 *
	 * This method is used to map existing WC Variation IDs to a formatted product update array,
	 * typically used for updating Square Items. The mapping is done based on SKUs.
	 *
	 * @param WC_Product $wc_product The WooCommerce Product containing variations.
	 * @param array      $product_update The formatted product update array.
	 * @return array The WC Product formatted for update, with Variation IDs mapped.
	 */
	public static function set_wc_variation_ids_for_update( WC_Product $wc_product, $product_update ) {

		if ( ( 'variable' === $wc_product->product_type ) && isset( $product_update['variations'] ) ) {

			$wc_variations = self::get_wc_product_variations( $wc_product );

			$wc_variation_sku_id_map = array();

			foreach ( $wc_variations as $wc_variation ) {

				$wc_variation_sku = $wc_variation->get_sku();

				if ( ! empty( $wc_variation_sku ) && ! empty( $wc_variation->variation_id ) ) {

					$wc_variation_sku_id_map[ $wc_variation_sku ] = $wc_variation->variation_id;

				}
			}

			foreach ( (array) $product_update['variations'] as $idx => $variation ) {

				if ( ! empty( $variation['sku'] ) && isset( $wc_variation_sku_id_map[ $variation['sku'] ] ) ) {

					$product_update['variations'][ $idx ]['id'] = $wc_variation_sku_id_map[ $variation['sku'] ];

				}
			}
		}

		return $product_update;
	}

	/**
	 * Format a Square Item for an update through the WooCommerce (WC) Product API.
	 *
	 * This method formats a Square Item for updating a WooCommerce Product via the WC Product API.
	 * It allows customization of included information such as category, inventory, and image.
	 *
	 * @param object     $square_item The Square Item to be formatted for update.
	 * @param WC_Product $wc_product The WooCommerce Product associated with the Square Item.
	 * @param bool       $include_category Whether to include category information (default is false).
	 * @param bool       $include_inventory Whether to include inventory details (default is false).
	 * @param bool       $include_image Whether to include image information (default is false).
	 * @return array Formatted as a Square Item for update through the WC Product API.
	 */
	public static function format_square_item_for_wc_api_update( $square_item, WC_Product $wc_product, $include_category = false, $include_inventory = false, $include_image = false ) {

		$formatted = self::format_square_item_for_wc_api_create( $square_item, $include_category, $include_inventory, $include_image );

		return self::set_wc_variation_ids_for_update( $wc_product, $formatted );
	}

	/**
	 * Format a Square Item for creation through the WooCommerce (WC) Product API.
	 *
	 * This method formats a Square Item for creating a WooCommerce Product via the WC Product API.
	 * It allows customization of included information such as category, inventory, and image.
	 *
	 * @param object $square_item The Square Item to be formatted for creation.
	 * @param bool   $include_category Whether to include category information (default is false).
	 * @param bool   $include_inventory Whether to include inventory details (default is false).
	 * @param bool   $include_image Whether to include image information (default is false).
	 * @return array Formatted as a Square Item for creation through the WC Product API.
	 */
	public static function format_square_item_for_wc_api_create( $square_item, $include_category = false, $include_inventory = false, $include_image = false ) {

		$formatted = array(
			'title' => $square_item->name,
		);

		if ( apply_filters( 'woocommerce_square_sync_from_square_description', false ) ) {
			$description              = ! empty( $square_item->description ) ? $square_item->description : '';
			$formatted['description'] = $description;
		}

		if ( $include_image && isset( $square_item->master_image->url ) ) {

			$formatted['images'] = array(
				array(
					'position' => 0,
					'src'      => $square_item->master_image->url,
				),
			);

		}

		if ( $include_category && isset( $square_item->category->id ) ) {

			$wc_cat_id               = self::get_wc_category_id_for_square_category_id( $square_item->category->id ) ? array( $wc_cat_id ) : array();
			$formatted['categories'] = $wc_cat_id;

		}

		if ( count( $square_item->variations ) > 1 ) {

			$formatted['type']       = 'variable';
			$formatted['variations'] = array();

			foreach ( $square_item->variations as $square_item_variation ) {

				$formatted['variations'][] = self::format_square_item_variation_for_wc_api( $square_item_variation, $include_inventory );

			}

			$formatted['attributes'] = array(
				array(
					'name'      => 'Attribute',
					'slug'      => 'attribute',
					'position'  => 0,
					'visible'   => true,
					'variation' => true,
					'options'   => wp_list_pluck( $square_item->variations, 'name' ),
				),
			);

		} else {

			$variation = self::format_square_item_variation_for_wc_api( $square_item->variations[0], $include_inventory );

			$formatted['type']           = 'simple';
			$formatted['sku']            = isset( $variation['sku'] ) ? $variation['sku'] : null;
			$formatted['regular_price']  = isset( $variation['regular_price'] ) ? $variation['regular_price'] : null;
			$formatted['stock_quantity'] = isset( $variation['stock_quantity'] ) ? $variation['stock_quantity'] : null;
			$formatted['managing_stock'] = isset( $variation['managing_stock'] ) ? $variation['managing_stock'] : null;

		}

		return array_filter( $formatted );
	}

	/**
	 * Convert a Square ItemVariation for use in the WooCommerce (WC) Product API.
	 *
	 * This method formats a Square ItemVariation for use in the WC Product API.
	 * It allows customization of included information such as inventory.
	 *
	 * @param object $square_item_variation The Square ItemVariation to be formatted.
	 * @param bool   $include_inventory Whether to include inventory details (default is false).
	 * @return array Formatted as a Square ItemVariation for the WC Product API.
	 */
	public static function format_square_item_variation_for_wc_api( $square_item_variation, $include_inventory = false ) {

		$formatted = array(
			'sku'            => ! empty( $square_item_variation->sku ) ? $square_item_variation->sku : '',
			'regular_price'  => self::format_square_price_for_wc( $square_item_variation->price_money->amount ),
			'stock_quantity' => null,
			'attributes'     => array(
				array(
					'name'   => 'Attribute',
					'option' => ! empty( $square_item_variation->name ) ? $square_item_variation->name : '',
				),
			),
		);

		if ( $include_inventory ) {

			$formatted['managing_stock'] = $square_item_variation->track_inventory ? true : null;

		}

		return array_filter( $formatted );
	}

	/**
	 * Format the price from Square, which uses the lowest denomination (e.g., cents).
	 *
	 * This method formats the price received from Square, which is typically in the lowest denomination (e.g., cents),
	 * into a more human-readable format for WooCommerce.
	 *
	 * @param int $price The price value in the lowest denomination (e.g., cents).
	 * @return int The formatted price suitable for WooCommerce.
	 */
	public static function format_square_price_for_wc( $price = 0 ) {

		return apply_filters( 'woocommerce_square_format_price', wc_format_decimal( absint( $price ) / 100 ) );
	}

	/**
	 * Retrieve the Square ID associated with a WooCommerce Term.
	 *
	 * This method retrieves the Square ID associated with a WooCommerce Term using
	 * the WooCommerce term metadata.
	 *
	 * @param int $wc_term_id The ID of the WooCommerce Term.
	 * @return mixed The Square ID associated with the WooCommerce Term, or see get_woocommerce_term_meta().
	 */
	public static function get_wc_term_square_id( $wc_term_id ) {

		return get_woocommerce_term_meta( $wc_term_id, self::WC_TERM_SQUARE_ID );
	}

	/**
	 * Update the Square ID associated with a WooCommerce Term.
	 *
	 * This method updates the Square ID associated with a WooCommerce Term using
	 * the WooCommerce term metadata.
	 *
	 * @param int    $wc_term_id The ID of the WooCommerce Term.
	 * @param string $square_id The Square ID to associate with the WooCommerce Term.
	 * @return bool True on success, false on failure. See update_woocommerce_term_meta().
	 */
	public static function update_wc_term_square_id( $wc_term_id, $square_id ) {

		return update_woocommerce_term_meta( $wc_term_id, self::WC_TERM_SQUARE_ID, $square_id );
	}

	/**
	 * Retrieve the Square ID associated with a WooCommerce Product.
	 *
	 * This method retrieves the Square ID associated with a WooCommerce Product using
	 * WordPress post metadata.
	 *
	 * @param int $wc_product_id The ID of the WooCommerce Product.
	 * @return array|mixed The Square ID associated with the WooCommerce Product, or see get_post_meta().
	 */
	public static function get_wc_product_square_id( $wc_product_id ) {

		return get_post_meta( $wc_product_id, self::WC_PRODUCT_SQUARE_ID, true );
	}

	/**
	 * Update the Square ID associated with a WooCommerce Product.
	 *
	 * This method updates the Square ID associated with a WooCommerce Product using
	 * WordPress post metadata.
	 *
	 * @param int    $wc_product_id The ID of the WooCommerce Product.
	 * @param string $square_id The Square ID to associate with the WooCommerce Product.
	 * @return bool|int True on success, false on failure. See update_post_meta().
	 */
	public static function update_wc_product_square_id( $wc_product_id, $square_id ) {

		return update_post_meta( $wc_product_id, self::WC_PRODUCT_SQUARE_ID, $square_id );
	}

	/**
	 * Retrieve the Square ID associated with a WooCommerce Product Variation.
	 *
	 * This method retrieves the Square ID associated with a WooCommerce Product Variation
	 * using WordPress post metadata.
	 *
	 * @param int $wc_variation_id The ID of the WooCommerce Product Variation.
	 * @return array|mixed The Square ID associated with the WooCommerce Product Variation, or see get_post_meta().
	 */
	public static function get_wc_variation_square_id( $wc_variation_id ) {

		return get_post_meta( $wc_variation_id, self::WC_VARIATION_SQUARE_ID, true );
	}

	/**
	 * Update the Square ID associated with a WooCommerce Product Variation.
	 *
	 * This method updates the Square ID associated with a WooCommerce Product Variation
	 * using WordPress post metadata.
	 *
	 * @param int    $wc_variation_id The ID of the WooCommerce Product Variation.
	 * @param string $square_id The Square ID to associate with the WooCommerce Product Variation.
	 * @return bool|int True on success, false on failure. See update_post_meta().
	 */
	public static function update_wc_variation_square_id( $wc_variation_id, $square_id ) {

		return update_post_meta( $wc_variation_id, self::WC_VARIATION_SQUARE_ID, $square_id );
	}

	/**
	 * Get all SKUs associated with a WooCommerce Product (could be many, if variable).
	 *
	 * This method retrieves all SKUs associated with a WooCommerce Product.
	 * If the product is variable, it collects SKUs from its variations.
	 *
	 * @param WC_Product $wc_product The WooCommerce Product.
	 * @return array An array of SKUs associated with the WooCommerce Product.
	 */
	public static function get_wc_product_skus( WC_Product $wc_product ) {

		$wc_product_skus = array();

		if ( 'simple' === $wc_product->product_type ) {

			$wc_product_skus[] = $wc_product->get_sku();

		} elseif ( 'variable' === $wc_product->product_type ) {

			$wc_variations   = self::get_wc_product_variations( $wc_product );
			$wc_product_skus = wp_list_pluck( $wc_variations, 'sku' );

		}

		// SKUs are optional, so let's only return ones that have values.
		return array_filter( $wc_product_skus );
	}

	/**
	 * Determine which WooCommerce Product Category to send to Square.
	 *
	 * Returns the first top-level Category that has an associated Square ID.
	 *
	 * @param WC_Product $wc_product The WooCommerce Product.
	 * @return int|null The Square Category ID associated with the WooCommerce Product, or null if not found.
	 */
	public static function get_square_category_id_for_wc_product( WC_Product $wc_product ) {

		$wc_categories = wp_get_post_terms( $wc_product->id, 'product_cat' );

		if ( is_wp_error( $wc_categories ) && empty( $wc_categories ) ) {

			return false;

		}

		foreach ( $wc_categories as $category ) {

			if ( $category->parent ) {

				$ancestors    = get_ancestors( $category->term_id, 'product_cat', 'taxonomy' );
				$top_level_id = end( $ancestors );

			} else {

				$top_level_id = $category->term_id;

			}
			$square_cat_id = self::get_wc_term_square_id( $top_level_id );
			if ( $square_cat_id ) {
				return $square_cat_id;
			} else {
				return null;
			}
		}

		return false;
	}

	/**
	 * Retrieve the Square Item Image ID associated with a WooCommerce Product.
	 *
	 * This method retrieves the Square Item Image ID associated with a WooCommerce Product
	 * using WordPress post metadata.
	 *
	 * @param int $wc_product_id The ID of the WooCommerce Product.
	 * @return array|mixed The Square Item Image ID associated with the WooCommerce Product, or see get_post_meta().
	 */
	public static function get_wc_product_image_square_id( $wc_product_id ) {

		return get_post_meta( $wc_product_id, self::WC_PRODUCT_IMAGE_SQUARE_ID, true );
	}

	/**
	 * Update the Square Item Image ID associated with a WooCommerce Product.
	 *
	 * This method updates the Square Item Image ID associated with a WooCommerce Product
	 * using WordPress post metadata.
	 *
	 * @param int    $wc_product_id The ID of the WooCommerce Product.
	 * @param string $square_image_id The Square Item Image ID to associate with the WooCommerce Product.
	 * @return bool|int True on success, false on failure. See update_post_meta().
	 */
	public static function update_wc_product_image_square_id( $wc_product_id, $square_image_id ) {

		return update_post_meta( $wc_product_id, self::WC_PRODUCT_IMAGE_SQUARE_ID, $square_image_id );
	}

	/**
	 * Retrieve the WooCommerce Category ID that corresponds to a given Square Category ID.
	 *
	 * This method searches for a WooCommerce Category ID that matches the provided Square Category ID.
	 * It checks the top-level categories for a match.
	 *
	 * @param string $square_cat_id The Square Category ID to match.
	 * @return int|false The WooCommerce Category ID on a successful match, or false if no match is found.
	 */
	public static function get_wc_category_id_for_square_category_id( $square_cat_id ) {

		$categories = get_terms(
			'product_cat',
			array(
				'parent'     => 0,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $categories ) ) {

			WooSquare_Sync_Logger::log( sprintf( '%s::%s - Taxonomy "product_cat" not found. Make sure WooCommerce is enabled.', __CLASS__, __FUNCTION__ ) );
			return false;

		}

		foreach ( $categories as $wc_category ) {

			$woo_square_cat_id = self::get_wc_term_square_id( $wc_category );

			if ( $woo_square_cat_id && ( $square_cat_id === $woo_square_cat_id ) ) {

				return $wc_category;

			}
		}

		return false;
	}

	/**
	 * Attempt to find a WooCommerce Product that corresponds to a given Square Item.
	 *
	 * This function first queries for a WooCommerce Product already associated with the
	 * Square Item's ID. If none are found, it queries all WooCommerce Products (including variations)
	 * using the SKUs present in the Square Item's Variations. If a match is found, the parent
	 * (non-variant) WooCommerce Product is returned.
	 *
	 * @param object $square_item The Square Item to find a corresponding WooCommerce Product for.
	 * @return WC_Product|false Corresponding WooCommerce Product on successful match, false otherwise.
	 */
	public static function get_wc_product_for_square_item( $square_item ) {
		$meta_query     = 'meta_query';
		$wc_product_ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish', // this is ignored.
				$meta_query      => array(
					array(
						'key'     => self::WC_PRODUCT_SQUARE_ID,
						'compare' => '=',
						'value'   => $square_item->id,
					),
				),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $wc_product_ids ) ) {
			$wc_product = wc_get_product( $wc_product_ids[0] );

			// only return publish products.
			if ( 'publish' === $wc_product->post->post_status ) {
				return $wc_product;
			}
		}

		$square_item_skus = self::get_square_item_skus( $square_item );
		$meta_query       = 'meta_query';
		$wc_product_ids   = get_posts(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				'post_status'    => 'publish', // this is ignored.
				$meta_query      => array(
					array(
						'key'     => '_sku',
						'compare' => 'IN',
						'value'   => $square_item_skus,
					),
				),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $wc_product_ids ) ) {

			$wc_product = wc_get_product( $wc_product_ids[0] );

			if ( 'publish' === $wc_product->post->post_status ) {
				if ( 'simple' === $wc_product->product_type ) {

					return $wc_product;

				}

				return $wc_product->parent;
			}
		}

		return false;
	}

	/**
	 * Attempt to find a WooCommerce Product that corresponds to a given Square Item Variation.
	 *
	 * This function queries for a WooCommerce Product (including variations) that has a matching
	 * Square Variation ID. If a match is found, the WooCommerce Product is returned.
	 *
	 * @param string $square_variation_id The Square Variation ID to find a corresponding WooCommerce Product for.
	 * @return WC_Product|false Corresponding WooCommerce Product on successful match, false otherwise.
	 */
	public static function get_wc_product_for_square_item_variation_id( $square_variation_id ) {
		$meta_query     = 'meta_query';
		$wc_product_ids = get_posts(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				'post_status'    => 'publish', // this is ignored.
				$meta_query      => array(
					array(
						'key'     => self::WC_VARIATION_SQUARE_ID,
						'compare' => '=',
						'value'   => $square_variation_id,
					),
				),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $wc_product_ids ) ) {
			$product = wc_get_product( $wc_product_ids[0] );

			// only return publish products.
			if ( 'publish' === $product->post->post_status ) {
				return $product;
			}
		}

		return false;
	}

	/**
	 * Retrieve an array of SKUs from all variations of a Square Item.
	 *
	 * @param object $square_item The Square Item object containing variations.
	 * @return array An array of SKUs from the Square Item's variations.
	 */
	public static function get_square_item_skus( $square_item ) {

		$item_skus = array();

		if ( empty( $square_item->variations ) ) {

			return $item_skus;

		}

		foreach ( $square_item->variations as $item_variation ) {

			if ( ! empty( $item_variation->sku ) ) {

				$item_skus[] = $item_variation->sku;

			}
		}

		return $item_skus;
	}

	/**
	 * Store Square Item ID and ItemVariation IDs on a WooCommerce (WC) Product and its variations.
	 * Matching is done using the SKU value.
	 *
	 * This function is most useful for operations involving synchronization between WC and Square.
	 *
	 * @param WC_Product $wc_product The WC product to store Square IDs for.
	 * @param object     $square_item The Square Item object containing IDs.
	 */
	public static function set_square_ids_on_wc_product_by_sku( WC_Product $wc_product, $square_item ) {

		self::update_wc_product_square_id( $wc_product->id, $square_item->id );

		if ( 'simple' === $wc_product->product_type ) {

			self::update_wc_variation_square_id( $wc_product->id, $square_item->variations[0]->id );

		} elseif ( 'variable' === $wc_product->product_type ) {

			// Create mapping of Square ItemVariation SKU => ID.
			$square_variations = array();

			foreach ( $square_item->variations as $square_variation ) {

				if ( ! empty( $square_variation->sku ) ) {

					$square_variations[ $square_variation->sku ] = $square_variation->id;

				}
			}

			// Create mapping of WC Variation SKU => ID.
			$wc_item_variations = self::get_wc_product_variations( $wc_product );
			$wc_variations      = array();

			foreach ( $wc_item_variations as $wc_item_variation ) {

				$wc_variation_sku = $wc_item_variation->get_sku();

				if ( ! empty( $wc_variation_sku ) ) {

					$wc_variations[ $wc_variation_sku ] = $wc_item_variation->variation_id;

				}
			}

			// Map the WC Variations to their Square ItemVariation counterparts.
			foreach ( $wc_variations as $sku => $wc_variation_id ) {

				if ( array_key_exists( $sku, $square_variations ) ) {

					self::update_wc_variation_square_id( $wc_variation_id, $square_variations[ $sku ] );

				}
			}
		}
	}

	/**
	 * Retrieve WC Variation IDs for a given WC Product that are managed for stock.
	 *
	 * @param WC_Product $wc_product The WC product for which to retrieve variation IDs.
	 * @return array An array of variation IDs managed for stock.
	 */
	public static function get_stock_managed_wc_variation_ids( WC_Product $wc_product ) {

		$wc_variation_ids = array();

		if ( 'simple' === $wc_product->product_type ) {

			if ( $wc_product->managing_stock() ) {

				$wc_variation_ids = array( $wc_product->id );

			}
		} elseif ( 'variable' === $wc_product->product_type ) {

			$variations = self::get_wc_product_variations( $wc_product );

			foreach ( (array) $variations as $variation ) {

				if ( $variation->managing_stock() ) {

					$wc_variation_ids[] = $variation->variation_id;

				}
			}
		}

		return $wc_variation_ids;
	}

	/**
	 * Get all variations of a given WC_Product_Variable.
	 *
	 * @param WC_Product_Variable $wc_variable_product The variable product for which to retrieve variations.
	 * @return array Array of WC_Product_Variation objects.
	 */
	public static function get_wc_product_variations( WC_Product_Variable $wc_variable_product ) {

		$variations = array();

		foreach ( $wc_variable_product->get_children() as $child_id ) {

			$variation = $wc_variable_product->get_child( $child_id );

			if ( empty( $variation->variation_id ) ) {
				continue;
			}

			$variations[] = $variation;

		}

		return $variations;
	}

	/**
	 * Check if the Square item is found.
	 *
	 * @param object $square_item The Square item to check.
	 * @return bool True if the Square item is found, false otherwise.
	 */
	public static function is_square_item_found( $square_item ) {
		if ( is_object( $square_item ) && 'not_found' !== $square_item->type ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if product sync is disabled.
	 *
	 * @param int|null $product_id Parent product ID.
	 * @return bool True if sync is disabled, false otherwise.
	 */
	public static function skip_product_sync( $product_id = null ) {
		if ( null === $product_id ) {
			return false;
		}

		$skip_sync = get_post_meta( $product_id, '_wcsquare_disable_sync', true );

		if ( 'yes' === $skip_sync ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the environment is a staging environment for testing.
	 *
	 * @param string|null $environment The environment string.
	 * @return bool True if the environment is staging and WP_DEBUG is enabled, false otherwise.
	 */
	public static function is_staging( $environment = null ) {
		if ( ! empty( $environment ) && 'staging' === $environment && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return true;
		}

		return false;
	}
}
