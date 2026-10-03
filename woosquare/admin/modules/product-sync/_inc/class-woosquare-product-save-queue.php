<?php
/**
 * Background product sync on WooCommerce edit/save (Sync on edit).
 *
 * @package Woosquare_Plus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queues and runs WooCommerce → Square product sync without blocking admin save.
 */
class WooSquare_Product_Save_Queue {

	const HOOK_SYNC_PRODUCT = 'woosquare_sync_product_on_edit';
	const GROUP             = 'woosquare';
	const DEBOUNCE_SECONDS  = 5;
	const META_DEFER_COUNT  = 'woosquare_edit_sync_defer_count';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( self::HOOK_SYNC_PRODUCT, array( __CLASS__, 'process' ), 10, 1 );
	}

	/**
	 * Whether another heavy sync is already running.
	 *
	 * @return bool
	 */
	public static function is_manual_bulk_sync_running() {
		if ( ! get_option( 'woo_square_running_sync' ) ) {
			return false;
		}
		$started = (int) get_option( 'woo_square_running_sync_time', 0 );
		if ( $started <= 0 ) {
			return false;
		}
		$global_max = defined( 'WOO_SQUARE_MAX_SYNC_TIME' ) ? (int) WOO_SQUARE_MAX_SYNC_TIME : ( 20 * MINUTE_IN_SECONDS );

		/**
		 * Cap the bulk-lock wait time for product edit sync. This prevents very large catalogs
		 * from blocking single-product edit sync jobs for hours.
		 *
		 * @param int $cap_seconds Default 180 seconds.
		 */
		$cap_seconds = (int) apply_filters( 'woosquare_product_edit_sync_bulk_lock_cap_seconds', 180 );
		$cap_seconds = max( 30, $cap_seconds );

		$max_wait = min( $global_max, $cap_seconds );
		$elapsed  = time() - $started;
		if ( $elapsed >= $max_wait ) {
			// Treat stale lock as cleared for edit-sync queue processing.
			update_option( 'woo_square_running_sync', false );
			update_option( 'woo_square_running_sync_time', 0 );
			return false;
		}
		return true;
	}

	/**
	 * Schedule background sync for a product.
	 *
	 * @param int $post_id Product post ID.
	 */
	public static function schedule( $post_id, $is_retry = false, $delay_seconds = null ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return;
		}

		/**
		 * Allow extensions to skip queueing sync for a product.
		 *
		 * @param bool $schedule Default true.
		 * @param int  $post_id  Product ID.
		 */
		if ( ! apply_filters( 'woosquare_should_queue_product_edit_sync', true, $post_id ) ) {
			return;
		}

		$args      = array( $post_id );
		$delay     = is_null( $delay_seconds ) ? self::DEBOUNCE_SECONDS : max( 1, (int) $delay_seconds );
		$run_at    = time() + $delay;
		$scheduled = false;

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK_SYNC_PRODUCT, $args, self::GROUP );
		}

		if ( function_exists( 'as_enqueue_async_action' ) && ! self::is_manual_bulk_sync_running() ) {
			as_enqueue_async_action( self::HOOK_SYNC_PRODUCT, $args, self::GROUP );
			$scheduled = true;
		} elseif ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $run_at, self::HOOK_SYNC_PRODUCT, $args, self::GROUP );
			$scheduled = true;
		}

		if ( ! $scheduled ) {
			$timestamp = wp_next_scheduled( self::HOOK_SYNC_PRODUCT, $args );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::HOOK_SYNC_PRODUCT, $args );
			}
			wp_schedule_single_event( $run_at, self::HOOK_SYNC_PRODUCT, $args );
		}

		if ( ! $is_retry ) {
			delete_post_meta( $post_id, self::META_DEFER_COUNT );
		}
		update_post_meta( $post_id, 'woosquare_edit_sync_status', 'queued' );
	}

	/**
	 * Run Square sync for one product (same outcome as legacy synchronous save).
	 *
	 * @param int $post_id Product post ID.
	 */
	public static function process( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return;
		}

		if ( self::is_manual_bulk_sync_running() ) {
			$defer_count = (int) get_post_meta( $post_id, self::META_DEFER_COUNT, true );

			/**
			 * Maximum number of defer attempts while bulk lock is active.
			 *
			 * @param int $max_defer_attempts Default 6 attempts.
			 * @param int $post_id            Product ID.
			 */
			$max_defer_attempts = (int) apply_filters( 'woosquare_product_edit_sync_max_defer_attempts', 6, $post_id );
			$max_defer_attempts = max( 1, $max_defer_attempts );

			if ( $defer_count >= $max_defer_attempts ) {
				$error_message = __( 'Product edit sync was deferred repeatedly due to an active bulk sync lock. Please retry once bulk sync is finished.', 'woosquare' );
				update_post_meta( $post_id, 'woosquare_edit_sync_status', 'failed' );
				update_post_meta( $post_id, 'woosquare_edit_sync_error', $error_message );
				delete_post_meta( $post_id, self::META_DEFER_COUNT );
				return;
			}

			update_post_meta( $post_id, self::META_DEFER_COUNT, $defer_count + 1 );
			/**
			 * Delay between deferred retries when bulk lock is active.
			 *
			 * @param int $seconds Default 30 seconds.
			 * @param int $post_id Product ID.
			 */
			$retry_delay_seconds = (int) apply_filters( 'woosquare_product_edit_sync_defer_delay_seconds', 30, $post_id );
			self::schedule( $post_id, true, $retry_delay_seconds );
			return;
		}

		if ( '1' !== get_option( 'sync_on_add_edit', '' ) ) {
			return;
		}

		if ( ! get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'product' !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}

		if ( class_exists( 'WooSquare_Utils' ) && WooSquare_Utils::skip_product_sync( $post_id ) ) {
			return;
		}

		$disabled = get_post_meta( $post_id, '_wcsquare_disable_sync', true );
		if ( 'yes' === $disabled ) {
			return;
		}

		update_post_meta( $post_id, 'woosquare_edit_sync_status', 'processing' );

		if ( ! class_exists( 'Square' ) || ! class_exists( 'WooToSquareSynchronizer' ) ) {
			update_post_meta( $post_id, 'woosquare_edit_sync_status', 'failed' );
			return;
		}

		$square = new Square(
			get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ),
			get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ),
			WOOSQU_PLUS_APPID
		);

		$square_synchronizer = new WooToSquareSynchronizer( $square );
		$product_square_id   = self::resolve_square_product_for_post( $post, $square, $square_synchronizer );

		/**
		 * Use full catalog list only when explicitly enabled (legacy fallback).
		 *
		 * @param bool $use_full_catalog Default false.
		 * @param int  $post_id          Product ID.
		 */
		if ( apply_filters( 'woosquare_product_edit_sync_use_full_catalog', false, $post_id ) ) {
			$square_to_woo = new SquareToWooSynchronizer( $square );
			$square_items  = $square_to_woo->get_square_items();
			if ( $square_items ) {
				$square_items = $square_synchronizer->simplify_square_items_object( $square_items );
			} else {
				$square_items = array();
			}
			$from_sku = $square_synchronizer->check_sku_in_square( $post, $square_items );
			if ( $from_sku ) {
				$product_square_id = $from_sku;
			}
		}

		$result = $square_synchronizer->add_product( $post, $product_square_id );

		if ( class_exists( 'WooSquare_Sync_Logs' ) && is_array( $result ) && isset( $result['pro_status'] ) ) {
			$woo_product_sync_log_transient                                      = array();
			$woo_product_sync_log_transient[ $post_id ][ $result['pro_status'] ] = $result;
			$woosquare_sync_log = new WooSquare_Sync_Logs();
			$woosquare_sync_log->log_data_request( $woo_product_sync_log_transient, '', 'woo_to_square', 'product' );
		}

		$termid = get_post_meta( $post_id, '_termid', true );
		if ( '' === $termid ) {
			$termid = 'update';
		}
		update_post_meta( $post_id, '_termid', $termid );

		if ( is_array( $result ) && true === $result['pro_status'] ) {
			update_post_meta( $post_id, 'is_square_sync', 1 );
			update_post_meta( $post_id, 'woosquare_edit_sync_status', 'completed' );
			delete_post_meta( $post_id, 'woosquare_edit_sync_error' );
			delete_post_meta( $post_id, self::META_DEFER_COUNT );
		} else {
			update_post_meta( $post_id, 'is_square_sync', 0 );
			update_post_meta( $post_id, 'woosquare_edit_sync_status', 'failed' );
			if ( is_array( $result ) && ! empty( $result['message'] ) ) {
				update_post_meta( $post_id, 'woosquare_edit_sync_error', $result['message'] );
			}
			delete_post_meta( $post_id, self::META_DEFER_COUNT );
		}

		/**
		 * Fires after a background edit sync attempt finishes.
		 *
		 * @param int   $post_id Product ID.
		 * @param array $result  Result from add_product().
		 */
		do_action( 'woosquare_product_edit_sync_completed', $post_id, $result );
	}

	/**
	 * Resolve Square catalog item for add_product() without downloading the full catalog.
	 *
	 * @param WP_Post                $post                Product post.
	 * @param Square                 $square              Square client.
	 * @param WooToSquareSynchronizer $square_synchronizer Sync helper.
	 * @return object|string|null Square item object when possible.
	 */
	public static function resolve_square_product_for_post( $post, $square, $square_synchronizer ) {
		$product_square_id = get_post_meta( $post->ID, 'square_id', true );

		if ( ! empty( $product_square_id ) && is_string( $product_square_id ) ) {
			$fetched = $square_synchronizer->delete_product_or_get( $product_square_id, 'GET' );
			if ( is_array( $fetched ) && ! empty( $fetched['object'] ) ) {
				$product_square_id = json_decode( wp_json_encode( $fetched['object'] ) );
			}
		}

		$product = wc_get_product( $post->ID );
		if ( ! $product || ! $product->get_sku() ) {
			return $product_square_id;
		}

		$response       = array();
		$method         = 'POST';
		$url            = 'https://connect.squareup' . get_transient( 'is_sandbox' ) . '.com/v2/catalog/search';
		$headers        = array(
			'Authorization'  => 'Bearer ' . get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ),
			'Content-Type'   => 'application/json;',
			'Square-Version' => '2020-12-16',
		);
		$args           = array(
			'object_types'            => array(
				'ITEM',
				'ITEM_VARIATION',
			),
			'include_related_objects' => true,
			'query'                   => array(
				'text_query' => array(
					'keywords' => array(
						$product->get_sku(),
					),
				),
			),
		);
		$response       = $square->wp_remote_woosquare( $url, $args, $method, $headers, $response );
		$square_product = null;

		if ( ! empty( $response['response'] ) && 200 === $response['response']['code'] && 'OK' === $response['response']['message'] ) {
			$square_product = json_decode( $response['body'], false );
		}

		if ( ! empty( $square_product ) && is_array( $square_product ) && ! empty( $product_square_id ) && is_object( $product_square_id ) && isset( $product_square_id->variations ) ) {
			foreach ( $square_product as $square_var ) {
				if ( isset( $square_var->item_variation_data->item_id ) && is_array( $product_square_id->variations ) ) {
					foreach ( $product_square_id->variations as $variation ) {
						if ( isset( $variation->item_id ) && $square_var->item_variation_data->item_id === $variation->item_id ) {
							$variation->present_at_location_ids = $square_var->present_at_location_ids;
						}
					}
				}
			}
		}

		if ( ! empty( $square_product ) && is_object( $square_product ) && isset( $square_product->related_objects ) ) {
			foreach ( $square_product->related_objects as $obj ) {
				if ( isset( $obj->type ) && 'ITEM' === $obj->type ) {
					$product_square_id = $obj;
				}
			}
		}

		return $product_square_id;
	}
}

WooSquare_Product_Save_Queue::init();
