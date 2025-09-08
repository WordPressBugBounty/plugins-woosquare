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
 * Settings page action
 */
function square_settings_page() {

	$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );

	$error_message   = '';
	$success_message = '';

	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'] && isset( $_GET['terminate_sync'] ) ) {

		// clear session variables if exists.
		$square_to_woo = get_transient( 'square_to_woo' );
		$woo_to_square = get_transient( 'woo_to_square' );
		if ( isset( $square_to_woo['square_to_woo'] ) ) {
			delete_transient( 'square_to_woo' );
		}
		if ( isset( $woo_to_square['woo_to_square'] ) ) {
			delete_transient( 'woo_to_square' );
		}

		update_option( 'woo_square_running_sync', false );
		update_option( 'woo_square_running_sync_time', 0 );

		$success_message = 'Sync terminated successfully!';
	}

	// check if the location is not setuped.
	if ( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) && ! get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ) ) {
		$square->authorize();
	}

	if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
		// setup account.
		if ( ! isset( $_POST['item_sync_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['item_sync_nonce'] ) ), 'item-sync-nonce-checker' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		// save settings.
		if ( isset( $_POST['woo_square_settings'] ) ) {

			if ( isset( $_POST['woo_square_auto_sync'] ) ) {
				update_option( 'woo_square_auto_sync', sanitize_text_field( wp_unslash( $_POST['woo_square_auto_sync'] ) ) );
				wp_clear_scheduled_hook( 'auto_sync_cron_job_hook' );
				if ( isset( $_POST['woo_square_auto_sync_duration'] ) ) {
					update_option( 'woo_square_auto_sync_duration', sanitize_text_field( wp_unslash( $_POST['woo_square_auto_sync_duration'] ) ) );
					switch ( $_POST['woo_square_auto_sync_duration'] ) {
						case 3:
							wp_schedule_event( time(), '3min', 'auto_sync_cron_job_hook' );
							break;
						case 60:
							wp_schedule_event( time(), 'hourly', 'auto_sync_cron_job_hook' );
							break;
						case 720:
							wp_schedule_event( time(), 'twicedaily', 'auto_sync_cron_job_hook' );
							break;
						case 1440:
							wp_schedule_event( time(), 'daily', 'auto_sync_cron_job_hook' );
							break;
					}
				}
			} else {
				wp_clear_scheduled_hook( 'auto_sync_cron_job_hook' );
			}
			update_option( 'woo_square_merging_option', isset( $_POST['woo_square_merging_option'] ) ? sanitize_text_field( wp_unslash( $_POST['woo_square_merging_option'] ) ) : null );
			update_option( 'woo_square_sync_preference', isset( $_POST['woo_square_sync_preference'] ) ? sanitize_text_field( wp_unslash( $_POST['woo_square_sync_preference'] ) ) : null );
			update_option( 'sync_on_add_edit', isset( $_POST['sync_on_add_edit'] ) ? sanitize_text_field( wp_unslash( $_POST['sync_on_add_edit'] ) ) : null );
			update_option( 'disable_auto_delete', isset( $_POST['disable_auto_delete'] ) ? sanitize_text_field( wp_unslash( $_POST['disable_auto_delete'] ) ) : 0 );
			if ( ! empty( $_POST['woosquare_pro_edit_fields'] ) ) {
				$edit_fields = array_map( 'sanitize_text_field', wp_unslash( $_POST['woosquare_pro_edit_fields'] ) );
				update_option( 'woosquare_pro_edit_fields', $edit_fields );
			} else {
				update_option( 'woosquare_pro_edit_fields', array() );
			}

			// update location id.
			if ( ! empty( $_POST[ 'woo_square_location_id' . get_transient( 'is_sandbox' ) ] ) ) {
				$location_id = sanitize_text_field( wp_unslash( $_POST[ 'woo_square_location_id' . get_transient( 'is_sandbox' ) ] ) );
				update_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ), $location_id );
				$square->set_location_id( $location_id );
				$square->get_currency_code();

			}

			update_option( 'html_sync_des', isset( $_POST['html_sync_des'] ) ? sanitize_text_field( wp_unslash( $_POST['html_sync_des'] ) ) : null );
			update_option( 'enable_woosquare_new_variation_format', isset( $_POST['enable_woosquare_new_variation_format'] ) ? sanitize_text_field( wp_unslash( $_POST['enable_woosquare_new_variation_format'] ) ) : null );
			update_option( 'woosquare_stocksync_webhook', isset( $_POST['woosquare_stocksync_webhook'] ) ? sanitize_text_field( wp_unslash( $_POST['woosquare_stocksync_webhook'] ) ) : null );
			$success_message = 'Settings updated successfully!';
		}
	}
	$woo_currency_code    = get_option( 'woocommerce_currency' );
	$square_currency_code = get_option( 'woo_square_account_currency_code' );

	if ( ! $square_currency_code ) {
		$square->get_currency_code();
		$square->getapp_id();
		$square_currency_code = get_option( 'woo_square_account_currency_code' );
	}
	$currency_mismatch_flag = ( $woo_currency_code !== $square_currency_code );
	include WOO_SQUARE_PLUGIN_PATH . 'views/settings.php';
}

/**
 * Settings customer sync page action
 */
function square_customer_sync_settings() {

	$square = new Square( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ), get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ), WOOSQU_PLUS_APPID );

	$error_message   = '';
	$success_message = '';

	// check if the location is not setuped.
	if ( get_option( 'woo_square_access_token' . get_transient( 'is_sandbox' ) ) && ! get_option( 'woo_square_location_id' . get_transient( 'is_sandbox' ) ) ) {
		$square->authorize();
	}

	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
		// setup account.
		if ( ! isset( $_POST['customer_sync_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['customer_sync_nonce'] ) ), 'customer-sync-nonce-checker' ) ) {
			wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
		}
		// save settings.
		if ( isset( $_POST['woo_square_customer_settings'] ) ) {
			if ( isset( $_POST['woo_square_customer_auto_sync'] ) ) {
				update_option( 'woo_square_customer_auto_sync', sanitize_text_field( wp_unslash( $_POST['woo_square_customer_auto_sync'] ) ) );
				wp_clear_scheduled_hook( 'auto_sync_customer_cron_job_hook' );
				if ( isset( $_POST['woo_square_customer_auto_sync_duration'] ) ) {
					update_option( 'woo_square_customer_auto_sync_duration', sanitize_text_field( wp_unslash( $_POST['woo_square_customer_auto_sync_duration'] ) ) );
					switch ( $_POST['woo_square_customer_auto_sync_duration'] ) {
						case 3:
							wp_schedule_event( time(), '3min', 'auto_sync_customer_cron_job_hook' );
							break;
						case 60:
							wp_schedule_event( time(), 'hourly', 'auto_sync_customer_cron_job_hook' );
							break;
						case 720:
							wp_schedule_event( time(), 'twicedaily', 'auto_sync_customer_cron_job_hook' );
							break;
						case 1440:
							wp_schedule_event( time(), 'daily', 'auto_sync_customer_cron_job_hook' );
							break;
					}
				}
			} else {
				wp_clear_scheduled_hook( 'auto_sync_customer_cron_job_hook' );
			}
			if ( ! empty( $_POST['woo_square_customer_merging_option'] ) ) {
				update_option( 'woo_square_customer_merging_option', sanitize_text_field( wp_unslash( $_POST['woo_square_customer_merging_option'] ) ) );
			}
			if ( ! empty( $_POST['woo_square_customer_sync_square_order_sync'] ) ) {
				update_option( 'woo_square_customer_sync_square_order_sync', sanitize_text_field( wp_unslash( $_POST['woo_square_customer_sync_square_order_sync'] ) ) );
			}
			if ( ! empty( $_POST['woo_square_create_customer_guest'] ) ) {
				update_option( 'woo_square_create_customer_guest', sanitize_text_field( wp_unslash( $_POST['woo_square_create_customer_guest'] ) ) );
			}
			if ( ! empty( $_POST['sync_on_customer_add_edit'] ) ) {
				update_option( 'sync_on_customer_add_edit', sanitize_text_field( wp_unslash( $_POST['sync_on_customer_add_edit'] ) ) );
			}
			$success_message = 'Settings updated successfully!';
		}
	}

	include SQUARE_CUSTOMER_SYNC_PLUGIN_PATH . '/admin/partials/customer-settings.php';
}
