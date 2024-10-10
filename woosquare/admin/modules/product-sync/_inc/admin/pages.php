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
		if ( isset( $_SESSION['square_to_woo'] ) ) {
			unset( $_SESSION['square_to_woo'] );
		}
		if ( isset( $_SESSION['woo_to_square'] ) ) {
			unset( $_SESSION['woo_to_square'] );
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

			if ( isset( $_POST['sync_on_add_edit'] ) ) {
				update_option( 'sync_on_add_edit', intval( sanitize_text_field( wp_unslash( $_POST['sync_on_add_edit'] ) ) ) );
			}
			if ( isset( $_POST['disable_auto_delete'] ) ) {
				update_option( 'disable_auto_delete', sanitize_text_field( wp_unslash( $_POST['disable_auto_delete'] ) ) );
			} else {
				update_option( 'disable_auto_delete', '' );
			}

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
			if ( isset( $_POST['html_sync_des'] ) ) {
				update_option( 'html_sync_des', isset( $_POST['html_sync_des'] ) ? sanitize_text_field( wp_unslash( $_POST['html_sync_des'] ) ) : null );

			} else {
				update_option( 'html_sync_des', '' );
			}
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
