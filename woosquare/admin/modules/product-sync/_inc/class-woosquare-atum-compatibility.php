<?php
/**
 * ATUM Compatibility for WooSquare Premium
 * 
 * This file handles compatibility between ATUM Inventory Management and WooSquare Premium
 * for stock synchronization to Square.
 *
 * @package Woosquare_Plus
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WooSquare_ATUM_Compatibility
 * 
 * Handles stock sync to Square when ATUM updates stock
 */
class WooSquare_ATUM_Compatibility {

	/**
	 * Initialize compatibility hooks
	 */
	public static function init() {
		
		// Only proceed if ATUM is active
		if ( ! class_exists( 'Atum\Inc\Helpers' ) ) {
			return;
		}

		// Hook into ATUM Stock Central updates
		add_action( 'atum/ajax/after_update_list_data', array( __CLASS__, 'sync_stock_after_stock_central_update' ), 20, 1 );
		
		// Hook into ATUM Purchase Orders "Add all into stock" button - Direct hook from Delivery model
		add_action( 'atum/purchase_orders_pro/delivery/after_stock_change', array( __CLASS__, 'sync_stock_after_po_delivery_stock_change' ), 20, 4 );
		
		// Hook into ATUM Purchase Orders "Add all into stock" button (legacy hook - fallback)
		add_action( 'atum/ajax/increase_atum_order_stock', array( __CLASS__, 'sync_stock_after_po_add_to_stock' ), 20, 2 );
		
		// Hook into WooCommerce stock updates from Purchase Orders (fallback)
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'sync_stock_on_po_stock_update' ), 100 );
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'sync_stock_on_po_stock_update' ), 100 );
	}

	/**
	 * Sync stock to Square after ATUM Stock Central update
	 * Sets exact stock value (not increment)
	 *
	 * @param array $data Updated product data
	 */
	public static function sync_stock_after_stock_central_update( $data ) {
		
		if ( empty( $data ) || ! is_array( $data ) ) {
			return;
		}

		// Check if woosquare sync is enabled
		$activate_modules = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );
		
		if ( empty( $activate_modules['items_sync']['module_activate'] ) ) {
			return;
		}

		foreach ( $data as $product_id => $product_meta ) {
			
			if ( isset( $product_meta['stock'] ) ) {
				self::sync_product_stock_to_square_exact( $product_id );
			}
		}
	}

	/**
	 * Sync stock to Square after Purchase Orders delivery stock change
	 * This is the main hook that fires when "Add all into stock" button is clicked
	 * Increments stock by delivered quantity
	 *
	 * @param object $delivery_item Delivery item object
	 * @param float  $quantity Quantity changed
	 * @param string $action Action type ('increase' or 'decrease')
	 * @param object $delivery Delivery object (required by hook signature, unused).
	 */
	public static function sync_stock_after_po_delivery_stock_change( $delivery_item, $quantity, $action, $delivery ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Hook signature.
		
		// Only process increase actions (when adding to stock)
		if ( 'increase' !== $action || $quantity <= 0 ) {
			return;
		}

		// Check if woosquare sync is enabled
		$activate_modules = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );
		
		if ( empty( $activate_modules['items_sync']['module_activate'] ) ) {
			return;
		}

		// Get product from delivery item
		if ( ! method_exists( $delivery_item, 'get_product' ) ) {
			return;
		}

		$product = $delivery_item->get_product();
		
		if ( ! $product || ! $product->managing_stock() ) {
			return;
		}

		$product_id = $product->get_id();
		
		// Check if product has Square ID
		$square_id = get_post_meta( $product_id, 'square_id', true );
		
		if ( empty( $square_id ) ) {
			return; // Product not synced to Square yet
		}

		// Sync increment to Square
		self::increment_product_stock_to_square( $product_id, (int) $quantity );
	}

	/**
	 * Sync stock to Square after Purchase Orders "Add all into stock" button click (legacy hook)
	 * Increments stock by delivered quantity
	 *
	 * @param \Atum\PurchaseOrders\Models\PurchaseOrder $atum_order
	 * @param string                                     $mode Mode (required by hook signature, unused).
	 */
	public static function sync_stock_after_po_add_to_stock( $atum_order, $mode ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Hook signature.
		
		if ( ! $atum_order || ! method_exists( $atum_order, 'get_items' ) ) {
			return;
		}

		// Check if woosquare sync is enabled
		$activate_modules = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );
		
		if ( empty( $activate_modules['items_sync']['module_activate'] ) ) {
			return;
		}

		// Get quantities from POST (these are the delivered quantities). Nonce verified by ATUM before this hook.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['quantities'] ) || ! isset( $_POST['atum_order_item_ids'] ) ) {
			// If POST data is not available, set flag for fallback hook
			// Store previous stock for each product before update
			$items = $atum_order->get_items();
			
			if ( ! empty( $items ) ) {
				foreach ( $items as $item_id => $item ) {
					if ( method_exists( $item, 'get_product' ) ) {
						$product = $item->get_product();
						
						if ( $product && $product->managing_stock() ) {
							$product_id    = $product->get_id();
							$current_stock = (int) $product->get_stock_quantity();
							
							// Store previous stock in transient for fallback hook
							$transient_key = 'woosquare_po_prev_stock_' . $product_id;
							set_transient( $transient_key, $current_stock, 60 ); // 60 seconds expiry
						}
					}
				}
			}
			
			return; // Let fallback hook handle it
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- ATUM verifies nonce before this hook.
		$atum_order_item_ids = isset( $_POST['atum_order_item_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['atum_order_item_ids'] ) ) : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ATUM verifies nonce; values sanitized with wc_stock_amount below.
		$raw_quantities = ( isset( $_POST['quantities'] ) && is_array( $_POST['quantities'] ) ) ? wp_unslash( $_POST['quantities'] ) : array();
		$quantities     = array_map( 'wc_stock_amount', $raw_quantities );
		
		$items = $atum_order->get_items();
		
		if ( empty( $items ) ) {
			return;
		}

		foreach ( $items as $item_id => $item ) {
			
			// Only process items that were selected and have delivered quantity
			if ( ! in_array( $item_id, $atum_order_item_ids, true ) || ! isset( $quantities[ $item_id ] ) || $quantities[ $item_id ] <= 0 ) {
				continue;
			}
			
			if ( method_exists( $item, 'get_product' ) ) {
				$product = $item->get_product();
				
				if ( $product && $product->managing_stock() ) {
					// Get delivered quantity (this is the increment amount)
					$delivered_quantity = $quantities[ $item_id ];
					
					// Sync increment to Square
					self::increment_product_stock_to_square( $product->get_id(), $delivered_quantity );
				}
			}
		}
	}

	/**
	 * Sync stock to Square when updated from Purchase Orders (fallback hook)
	 * This catches stock updates from "Add all into stock" button
	 *
	 * @param WC_Product $product Product object
	 */
	public static function sync_stock_on_po_stock_update( $product ) {
		
		// Only process if it's from ATUM Purchase Orders context
		if ( ! self::is_atum_po_stock_update() ) {
			return;
		}

		// Check if woosquare sync is enabled
		$activate_modules = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );
		
		if ( empty( $activate_modules['items_sync']['module_activate'] ) ) {
			return;
		}

		if ( ! $product || ! $product->managing_stock() ) {
			return;
		}

		$product_id = $product->get_id();
		
		// Check if product has Square ID
		$square_id = get_post_meta( $product_id, 'square_id', true );
		
		if ( empty( $square_id ) ) {
			return; // Product not synced to Square yet
		}

		// Get current stock
		$current_stock = (int) $product->get_stock_quantity();
		
		// Get previous stock from transient (set before stock update)
		$transient_key  = 'woosquare_po_prev_stock_' . $product_id;
		$previous_stock = get_transient( $transient_key );
		
		// If we don't have previous stock, try to get it from product meta
		if ( false === $previous_stock ) {
			$previous_stock = (int) get_post_meta( $product_id, '_stock', true );
		}
		
		// Calculate increment (difference)
		$stock_increment = $current_stock - $previous_stock;
		
		// Only sync if stock increased (positive increment)
		if ( $stock_increment > 0 ) {
			// Sync increment to Square
			self::increment_product_stock_to_square( $product_id, $stock_increment );
		}
		
		// Clean up transient
		delete_transient( $transient_key );
	}

	/**
	 * Sync product stock to Square with exact value (not increment)
	 * Used for ATUM Stock Central updates
	 * This calculates the difference between WooCommerce stock and Square stock
	 * and syncs accordingly to set exact value
	 *
	 * @param int $product_id Product ID
	 */
	private static function sync_product_stock_to_square_exact( $product_id ) {
		
		if ( ! $product_id ) {
			return;
		}

		// Check if woosquare sync is enabled
		$activate_modules = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );
		
		if ( empty( $activate_modules['items_sync']['module_activate'] ) ) {
			return;
		}

		$product = wc_get_product( $product_id );
		
		if ( ! $product || ! $product->managing_stock() ) {
			return;
		}

		// Check if product has Square ID
		$square_id = get_post_meta( $product_id, 'square_id', true );
		
		if ( empty( $square_id ) ) {
			return; // Product not synced to Square yet
		}

		// Use woosquare's proven WooToSquareSynchronizer class
		if ( ! class_exists( 'WooToSquareSynchronizer' ) || ! class_exists( 'Square' ) ) {
			return;
		}

		try {
			$square = new Square( 
				get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), 
				get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), 
				WOOSQU_PLUS_APPID 
			);
			
			$synchronizer               = new WooToSquareSynchronizer( $square );
			$square_to_woo_synchronizer = new SquareToWooSynchronizer( $square );
			$woo_square_location_id     = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
			
			// Handle variable products
			if ( $product->is_type( 'variable' ) ) {
				$variations = $product->get_available_variations();
				
				foreach ( $variations as $variation_data ) {
					$variation_id = $variation_data['variation_id'];
					$variation    = wc_get_product( $variation_id );
					
					if ( $variation && $variation->managing_stock() ) {
						$variation_square_id = get_post_meta( $variation_id, 'variation_square_id', true );
						
						if ( ! empty( $variation_square_id ) ) {
							$wc_stock = (int) $variation->get_stock_quantity();
							
							if ( ! is_null( $wc_stock ) ) {
								// Get current Square stock by fetching inventory for this variation
								$variation_array           = array( array( 'id' => $variation_square_id ) );
								$square_inventory_response = $square_to_woo_synchronizer->get_square_inventory( $variation_array );
								
								$square_stock = 0;
								if ( ! empty( $square_inventory_response ) ) {
									if ( isset( $square_inventory_response->counts ) && is_array( $square_inventory_response->counts ) ) {
										foreach ( $square_inventory_response->counts as $inv_item ) {
											if ( isset( $inv_item->catalog_object_id ) && $inv_item->catalog_object_id === $variation_square_id ) {
												$square_stock = (int) $inv_item->quantity;
												break;
											}
										}
									} elseif ( is_array( $square_inventory_response ) ) {
										foreach ( $square_inventory_response as $inv_item ) {
											if ( isset( $inv_item->catalog_object_id ) && $inv_item->catalog_object_id === $variation_square_id ) {
												$square_stock = (int) $inv_item->quantity;
												break;
											}
										}
									}
								}
								
								// Calculate difference to set exact value
								$stock_difference = $wc_stock - $square_stock;
								
								if ( 0 !== $stock_difference ) {
									$variation_data_for_square = array(
										'id'         => $variation_square_id,
										'updated_at' => gmdate( 'Y-m-d\TH:i:s' ) . '.000Z',
									);
									
									$adjustment_type     = ( $stock_difference > 0 ) ? 'RECEIVE_STOCK' : 'SALE';
									$adjustment_quantity = abs( $stock_difference );
									
									$synchronizer->update_inventory( 
										$variation_data_for_square, 
										$adjustment_quantity, 
										$adjustment_type, 
										$woo_square_location_id 
									);
								}
							}
						}
					}
				}
			} else {
				// Simple product
				$variation_square_id = get_post_meta( $product_id, 'variation_square_id', true );
				
				if ( empty( $variation_square_id ) ) {
					$variation_square_id = $square_id;
				}
				
				if ( ! empty( $variation_square_id ) ) {
					$wc_stock = (int) $product->get_stock_quantity();
					
					if ( ! is_null( $wc_stock ) ) {
						// Get current Square stock by fetching inventory for this variation
						$variation_array           = array( array( 'id' => $variation_square_id ) );
						$square_inventory_response = $square_to_woo_synchronizer->get_square_inventory( $variation_array );
						
						$square_stock = 0;
						if ( ! empty( $square_inventory_response ) ) {
							if ( isset( $square_inventory_response->counts ) && is_array( $square_inventory_response->counts ) ) {
								foreach ( $square_inventory_response->counts as $inv_item ) {
									if ( isset( $inv_item->catalog_object_id ) && $inv_item->catalog_object_id === $variation_square_id ) {
										$square_stock = (int) $inv_item->quantity;
										break;
									}
								}
							} elseif ( is_array( $square_inventory_response ) ) {
								foreach ( $square_inventory_response as $inv_item ) {
									if ( isset( $inv_item->catalog_object_id ) && $inv_item->catalog_object_id === $variation_square_id ) {
										$square_stock = (int) $inv_item->quantity;
										break;
									}
								}
							}
						}
						
						// Calculate difference to set exact value
						$stock_difference = $wc_stock - $square_stock;
						
						if ( 0 !== $stock_difference ) {
							$variation_data_for_square = array(
								'id'         => $variation_square_id,
								'updated_at' => gmdate( 'Y-m-d\TH:i:s' ) . '.000Z',
							);
							
							$adjustment_type     = ( $stock_difference > 0 ) ? 'RECEIVE_STOCK' : 'SALE';
							$adjustment_quantity = abs( $stock_difference );
							
							$synchronizer->update_inventory( 
								$variation_data_for_square, 
								$adjustment_quantity, 
								$adjustment_type, 
								$woo_square_location_id 
							);
						}
					}
				}
			}
		} catch ( Exception $e ) {
			// Log error if needed
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is on.
				error_log( 'WooSquare ATUM Sync Error: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Increment product stock in Square (for Purchase Orders delivered quantities)
	 * This increments stock instead of setting exact value
	 *
	 * @param int $product_id Product ID
	 * @param int $increment_quantity Quantity to increment
	 */
	private static function increment_product_stock_to_square( $product_id, $increment_quantity ) {
		
		if ( ! $product_id || ! $increment_quantity || $increment_quantity <= 0 ) {
			return;
		}

		// Check if woosquare sync is enabled
		$activate_modules = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );
		
		if ( empty( $activate_modules['items_sync']['module_activate'] ) ) {
			return;
		}

		$product = wc_get_product( $product_id );
		
		if ( ! $product || ! $product->managing_stock() ) {
			return;
		}

		// Check if product has Square ID
		$square_id = get_post_meta( $product_id, 'square_id', true );
		
		if ( empty( $square_id ) ) {
			return; // Product not synced to Square yet
		}

		// Use woosquare's proven WooToSquareSynchronizer class
		if ( ! class_exists( 'WooToSquareSynchronizer' ) || ! class_exists( 'Square' ) ) {
			return;
		}

		try {
			$square = new Square( 
				get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), 
				get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), 
				WOOSQU_PLUS_APPID 
			);
			
			$synchronizer           = new WooToSquareSynchronizer( $square );
			$woo_square_location_id = get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) );
			
			// Handle variable products
			if ( $product->is_type( 'variable' ) ) {
				$variations = $product->get_available_variations();
				
				foreach ( $variations as $variation_data ) {
					$variation_id = $variation_data['variation_id'];
					$variation    = wc_get_product( $variation_id );
					
					if ( $variation && $variation->managing_stock() ) {
						$variation_square_id = get_post_meta( $variation_id, 'variation_square_id', true );
						
						if ( ! empty( $variation_square_id ) ) {
							$variation_data_for_square = array(
								'id'         => $variation_square_id,
								'updated_at' => gmdate( 'Y-m-d\TH:i:s' ) . '.000Z',
							);
							
							// Increment stock (RECEIVE_STOCK with increment quantity)
							$synchronizer->update_inventory( 
								$variation_data_for_square, 
								$increment_quantity, 
								'RECEIVE_STOCK', 
								$woo_square_location_id 
							);
						}
					}
				}
			} else {
				// Simple product
				$variation_square_id = get_post_meta( $product_id, 'variation_square_id', true );
				
				if ( empty( $variation_square_id ) ) {
					$variation_square_id = $square_id;
				}
				
				if ( ! empty( $variation_square_id ) ) {
					$variation_data_for_square = array(
						'id'         => $variation_square_id,
						'updated_at' => gmdate( 'Y-m-d\TH:i:s' ) . '.000Z',
					);
					
					// Increment stock (RECEIVE_STOCK with increment quantity)
					$synchronizer->update_inventory( 
						$variation_data_for_square, 
						$increment_quantity, 
						'RECEIVE_STOCK', 
						$woo_square_location_id 
					);
				}
			}
		} catch ( Exception $e ) {
			// Log error if needed
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is on.
				error_log( 'WooSquare ATUM PO Sync Error: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Check if current stock update is from ATUM Purchase Orders
	 *
	 * @return bool
	 */
	private static function is_atum_po_stock_update() {
		
		// Check if it's an AJAX request from ATUM Purchase Orders. POST is validated by ATUM.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			
			// Check for ATUM Purchase Order related actions
			if ( in_array( $action, array(
				'increase_atum_order_items_stock',
				'decrease_atum_order_items_stock',
				'atum_increase_order_items_stock',
				'atum_decrease_order_items_stock',
			), true ) ) {
				return true;
			}
			
			// Check if POST contains ATUM order item IDs. Nonce verified by ATUM.
			if ( isset( $_POST['atum_order_item_ids'] ) || isset( $_POST['order_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				
				if ( $order_id > 0 ) {
					$post_type = get_post_type( $order_id );
					if ( 'atum_purchase_order' === $post_type ) {
						return true;
					}
				}
			}
		}

		// Check if it's from ATUM Purchase Orders admin page. Reading GET for context only; capability checked by caller.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( 'atum_purchase_order' === $post_type ) {
			return true;
		}
		
		// Check if current screen is ATUM Purchase Order
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && 'atum_purchase_order' === $screen->post_type ) {
				return true;
			}
		}

		return false;
	}
}
