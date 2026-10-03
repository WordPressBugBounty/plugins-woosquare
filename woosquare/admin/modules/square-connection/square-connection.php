<?php
/**
 * Square Connection Module Page
 *
 * Track API activity with Square Connection Logs and receive Email Alerts for any disconnections, ensuring smooth payment processing.
 *
 * @package Woosquare_Plus
 * @subpackage Square_Connection
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue scripts and styles.
 */
function woosquare_square_connection_enqueue_scripts() {
	wp_enqueue_style( 'woosquare-square-connection', plugin_dir_url( __FILE__ ) . 'css/square-connection.css', array(), '1.0.0' );
	wp_enqueue_script( 'woosquare-square-connection', plugin_dir_url( __FILE__ ) . 'js/square-connection.js', array( 'jquery' ), '1.0.0', true );

	wp_localize_script(
		'woosquare-square-connection',
		'woosquare_square_connection',
		array(
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'woosquare_square_connection_nonce' ),
			'clear_logs_nonce'  => wp_create_nonce( 'clear_woosquare_logs' ),
			'save_alerts_nonce' => wp_create_nonce( 'save_woosquare_alerts' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'woosquare_square_connection_enqueue_scripts' );

/**
 * AJAX handler to clear WooSquare logs.
 * Note: Duplicate handler removed - using the one in class-woosquare-plus-admin.php
 */

/**
 * AJAX handler to save WooSquare alert settings.
 */
function woosquare_square_connection_save_alerts() {
	// Verify nonce.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'save_woosquare_alerts' ) ) {
		wp_die( 'Security check failed' );
	}

	// Check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Insufficient permissions' );
	}

	// Get and sanitize data.
	$alerts_enabled = isset( $_POST['alerts_enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['alerts_enabled'] ) ) : 'false';
	$alert_email    = isset( $_POST['alert_email'] ) ? sanitize_email( wp_unslash( $_POST['alert_email'] ) ) : '';

	// Validate email.
	if ( ! empty( $alert_email ) && ! is_email( $alert_email ) ) {
		wp_send_json_error( 'Invalid email address' );
	}

	// Save settings.
	if ( 'true' === $alerts_enabled ) {
		update_option( 'woosquare_alerts_enabled', true );
	} else {
		update_option( 'woosquare_alerts_enabled', false );
	}
	update_option( 'woosquare_alert_email', $alert_email );

	wp_send_json_success( 'Alert settings saved successfully' );
}
add_action( 'wp_ajax_save_woosquare_alerts', 'woosquare_square_connection_save_alerts' );

// Display the admin page.
?>
<div class="bodycontainerWrap">

	<div class="bodycontainer">

		<div id="tabs" class="md-elevation-4dp bg-theme-primary">
			<?php
			$woosquare_plus = new woosquare_plus();
			echo wp_kses_post( $woosquare_plus->wooplus_get_toptabs() );
			?>
			
		</div>

		<?php
		$data = isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
		parse_str( $data, $query_params );
		?>
		<div class="welcome-panel <?php echo isset( $query_params['page'] ) ? esc_html( sanitize_text_field( wp_unslash( $query_params['page'] ) ) ) : ''; ?>">

			<h1 class="m-0"><svg height="20px" viewBox="0 0 512 511" width="20px" xmlns="http://www.w3.org/2000/svg">
					<path
							d="m405.332031 256.484375c-11.796875 0-21.332031 9.558594-21.332031 21.332031v170.667969c0 11.753906-9.558594 21.332031-21.332031 21.332031h-298.667969c-11.777344 0-21.332031-9.578125-21.332031-21.332031v-298.667969c0-11.753906 9.554687-21.332031 21.332031-21.332031h170.667969c11.796875 0 21.332031-9.558594 21.332031-21.332031 0-11.777344-9.535156-21.335938-21.332031-21.335938h-170.667969c-35.285156 0-64 28.714844-64 64v298.667969c0 35.285156 28.714844 64 64 64h298.667969c35.285156 0 64-28.714844 64-64v-170.667969c0-11.796875-9.539063-21.332031-21.335938-21.332031zm0 0" />
					<path
							d="m200.019531 237.050781c-1.492187 1.492188-2.496093 3.390625-2.921875 5.4375l-15.082031 75.4375c-.703125 3.496094.40625 7.101563 2.921875 9.640625 2.027344 2.027344 4.757812 3.113282 7.554688 3.113282.679687 0 1.386718-.0625 2.089843-.210938l75.414063-15.082031c2.089844-.429688 3.988281-1.429688 5.460937-2.925781l168.789063-168.789063-75.414063-75.410156zm0 0" />
					<path
							d="m496.382812 16.101562c-20.796874-20.800781-54.632812-20.800781-75.414062 0l-29.523438 29.523438 75.414063 75.414062 29.523437-29.527343c10.070313-10.046875 15.617188-23.445313 15.617188-37.695313s-5.546875-27.648437-15.617188-37.714844zm0 0" />
				</svg> Square Connection</h1>
					<div class="tabset">
			<!-- Tab 1 -->
			<input type="radio" name="tabset" id="tab1" aria-controls="connection-logs-section" checked>
			<label for="tab1">Connection Logs</label>
			<!-- Tab 2 -->
			<input type="radio" name="tabset" id="tab2" aria-controls="alerts-settings-section">
			<label for="tab2">Email Alerts</label>
		  
			<div class="tab-panels">
			<section id="connection-logs-section" class="tab-panel">
				<div class = "filter_section">
				<button id="log-clear" class="log_filter_botton"><i class="fas fa-trash-alt" aria-hidden="true"></i> Clear Logs</button>
				<button id="log-refresh" class="log_reset_botton"><i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh</button>
				</div>
				
				<div class="table-wrapper">
				<div class="Log-table">
					<table class="product_list_table">
						<thead class="product_list_table_head">
							<tr>
								<th class="product_list_table_heading"><span class="product_list_table_heading_text">Log No.</span></th>
								<th class="product_list_table_heading"><span class="product_list_table_heading_text">Square Mode</span></th>
								<th class="product_list_table_heading"><span class="product_list_table_heading_text">Date & Time</span></th>
								<th class="product_list_table_heading"><span class="product_list_table_heading_text">Request Body</span></th>
								<th class="product_list_table_heading"><span class="product_list_table_heading_text">Response</span></th>
								<th class="product_list_table_heading"><span class="product_list_table_heading_text">Status</span></th>
							</tr>
						</thead>
								
						<tbody class="product_list_table_body connection_logs_table_body">    
							<?php
							$logs = get_option( 'woosquare_connection_logs' . get_transient( 'is_sandbox' ), array() );
							if ( ! empty( $logs ) ) {
								foreach ( $logs as $index => $log ) {
									$status_class   = $log['success'] ? 'success' : 'error';
									$status_text    = $log['success'] ? 'Connected' : 'Disconnected';
									$formatted_time = gmdate( 'Y-m-d H:i:s', strtotime( $log['timestamp'] ) );
									$response_text  = $log['status_code'] >= 400 ? 'Bad request' : 'Success';

									// Format response data beautifully.
									$response_display = '';
									if ( ! empty( $log['response'] ) ) {
										$response_data = json_decode( $log['response'], true );
										if ( json_last_error() === JSON_ERROR_NONE && is_array( $response_data ) ) {
											$response_items = array();

											// Check if it's an error response (has 'message' or 'type' fields).
											$is_error_response = isset( $response_data['message'] ) || isset( $response_data['type'] );

											if ( $is_error_response ) {
												// Error response format.
												if ( isset( $response_data['message'] ) ) {
													$response_items[] = '<div style="margin-bottom: 8px;"><span style="color: #dc3545; font-weight: 500;">Message:</span> <span style="color: #721c24;">' . esc_html( $response_data['message'] ) . '</span></div>';
												}

												if ( isset( $response_data['type'] ) ) {
													$response_items[] = '<div style="margin-bottom: 8px;"><span style="color: #dc3545; font-weight: 500;">Type:</span> <span style="color: #721c24;">' . esc_html( $response_data['type'] ) . '</span></div>';
												}

												// Show any other error fields.
												foreach ( $response_data as $key => $value ) {
													if ( ! in_array( $key, array( 'message', 'type' ), true ) ) {
														$response_items[] = '<div style="margin-bottom: 8px;"><span style="color: #dc3545; font-weight: 500;">' . esc_html( ucfirst( str_replace( '_', ' ', $key ) ) ) . ':</span> <span style="color: #721c24;">' . esc_html( is_array( $value ) ? wp_json_encode( $value ) : $value ) . '</span></div>';
													}
												}

												if ( ! empty( $response_items ) ) {
													$response_display = '<div style="background: #fff5f5; padding: 12px; border-radius: 6px; border: 1px solid #feb2b2; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 13px; line-height: 1.5;">' . implode( '', $response_items ) . '</div>';
												}
											} else {
												// Success response format (token data).
												// Access token (masked).
												if ( isset( $response_data['access_token'] ) ) {
													$response_items[] = '<div style="margin-bottom: 8px;"><span style="color: #0066cc; font-weight: 500;">Access token:</span> <span style="color: #333;">***</span></div>';
												}

												// Token type.
												if ( isset( $response_data['token_type'] ) ) {
													$response_items[] = '<div style="margin-bottom: 8px;"><span style="color: #0066cc; font-weight: 500;">Token type:</span> <span style="color: #333;">' . esc_html( $response_data['token_type'] ) . '</span></div>';
												}

												// Expires at (formatted date).
												if ( isset( $response_data['expires_at'] ) ) {
													$expires_at = $response_data['expires_at'];
													// Convert ISO 8601 to readable format: DD-Mon-YYYY HH:MM:SS.
													$timestamp         = strtotime( $expires_at );
													$formatted_expires = gmdate( 'd-M-Y H:i:s', $timestamp );
													$response_items[]  = '<div style="margin-bottom: 8px;"><span style="color: #0066cc; font-weight: 500;">Expires at:</span> <span style="color: #333;">' . esc_html( $formatted_expires ) . '</span></div>';
												}

												// Merchant id (masked).
												if ( isset( $response_data['merchant_id'] ) ) {
													$response_items[] = '<div style="margin-bottom: 8px;"><span style="color: #0066cc; font-weight: 500;">Merchant id:</span> <span style="color: #333;">***</span></div>';
												}

												// Refresh token (masked).
												if ( isset( $response_data['refresh_token'] ) ) {
													$response_items[] = '<div style="margin-bottom: 8px;"><span style="color: #0066cc; font-weight: 500;">Refresh token:</span> <span style="color: #333;">***</span></div>';
												}

												// Short lived.
												if ( isset( $response_data['short_lived'] ) ) {
													$short_lived      = $response_data['short_lived'] ? 'true' : 'false';
													$response_items[] = '<div style="margin-bottom: 8px;"><span style="color: #0066cc; font-weight: 500;">Short lived:</span> <span style="color: #333;">' . esc_html( $short_lived ) . '</span></div>';
												}

												if ( ! empty( $response_items ) ) {
													$response_display = '<div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #e0e0e0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 13px; line-height: 1.5;">' . implode( '', $response_items ) . '</div>';
												}
											}

											if ( empty( $response_items ) ) {
												$response_display = '<div style="color: #999; font-style: italic;">No response data</div>';
											}
										} else {
											// If not JSON, show raw response (truncated).
											$response_display = '<div style="color: #555; font-family: monospace; font-size: 12px; word-break: break-all;">' . esc_html( substr( $log['response'], 0, 100 ) );
											if ( strlen( $log['response'] ) > 100 ) {
												$response_display .= '...';
											}
											$response_display .= '</div>';
										}
									} else {
										$response_display = '<div style="color: #999; font-style: italic;">-</div>';
									}

									echo '<tr class="product_list_table_body_row connection_logs_table_body_row">
											<td class="product_list_table_data connection_logs_table_data">' . esc_html( $index + 1 ) . '</td>
											<td class="product_list_table_data connection_logs_table_data">' . esc_html( $log['square_mode'] ) . '</td>
											<td class="product_list_table_data connection_logs_table_data">' . esc_html( $formatted_time ) . '</td>
											<td class="product_list_table_data connection_logs_table_data" style="max-width: 350px; word-wrap: break-word;">' . wp_kses_post( $response_display ) . '</td>
											<td class="product_list_table_data connection_logs_table_data">' . esc_html( $response_text ) . '</td>
											<td class="product_list_table_data connection_logs_table_data"><span class="' . esc_attr( $status_class ) . '">' . esc_html( $status_text ) . '</span></td>
										  </tr>';
								}
							} else {
								echo '<tr class="empty-row"><td colspan="6">' . esc_html__( 'No connection logs found.', 'woosquare' ) . '</td></tr>';
							}
							?>
								
						</tbody>
					</table>
				</div>
				</div>
			</section>
			<section id="alerts-settings-section" class="tab-panel">
				
				<div class="table-wrapper">
				<div class="Log-table">
					<div class="ws-info-box">
						<div class="ws-info-icon">i</div>
						<div class="ws-info-text">
						Get email notifications for Square connection updates.
						</div>
					</div>
					
					<form id="alerts-form" class="connection-alerts-form">
						<div class="ws-form-group">
							<label class="ws-form-label">Square Connection Alerts</label>
							<label class="ws-toggle-switch">
								<input type="checkbox" id="square-connection-toggle" <?php echo get_option( 'woosquare_alerts_enabled', false ) ? 'checked' : ''; ?>>
								<span class="ws-toggle-slider"></span>
							</label>
						</div>
						
						<div class="ws-form-group vertical">
							<label for="alert-email" class="ws-form-label">Email Address</label>
							<input type="email" id="alert-email" class="ws-form-input" placeholder="Enter Email Address" value="<?php echo esc_attr( get_option( 'woosquare_alert_email', get_option( 'admin_email' ) ) ); ?>">
						</div>
						
						<div class="ws-save-settings-container">
							<button type="button" id="save-alerts-btn" class="ws-save-btn">Save Settings</button>
						</div>
					</form>
				</div>
				</div>
			</section>
			</div>
		  
		</div>
	</div>
</div>

		<script>
			jQuery(document).ready(function($) {
				'use strict';
				
				// Tab functionality - Same as log-settings.php
				$('.tabset input[type="radio"]').on('change', function() {
					var target = $(this).attr('aria-controls');
					
					// Remove active class from all tab panels
					$('.tab-panel').removeClass('active');
					
					// Show target tab panel
					$('#' + target).addClass('active');
				});
				
				// Clear logs functionality
				$(document).on('click', '#log-clear', function(e) {
					e.preventDefault();
					
					if (confirm('Are you sure you want to clear all connection logs? This action cannot be undone.')) {
						$.ajax({
							url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
							type: 'POST',
							data: {
								action: 'clear_woosquare_logs',
								nonce: '<?php echo esc_js( wp_create_nonce( 'clear_woosquare_logs' ) ); ?>'
							},
							success: function(response) {
								if (response.success) {
									alert('Connection logs cleared successfully!');
									location.reload();
								} else {
									alert('Error clearing logs: ' + (response.data || 'Unknown error'));
								}
							},
						error: function(xhr, status, error) {
							alert('Error clearing logs. Please try again.');
						}
						});
					}
				});
				
				// Refresh logs functionality
				$(document).on('click', '#log-refresh', function(e) {
					e.preventDefault();
					location.reload();
				});
				
				// Save alerts functionality
				$(document).on('click', '#save-alerts-btn', function(e) {
					e.preventDefault();
					
				var alertsEnabled = $('#square-connection-toggle').is(':checked') ? 'true' : 'false';
				var alertEmail = $('#alert-email').val();
				
				if (!alertEmail) {
						alert('Please enter an email address.');
						return;
					}
					
					$.ajax({
						url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
						type: 'POST',
						data: {
							action: 'save_woosquare_alerts',
							nonce: '<?php echo esc_js( wp_create_nonce( 'save_woosquare_alerts' ) ); ?>',
							alerts_enabled: alertsEnabled,
							alert_email: alertEmail
						},
					success: function(response) {
						if (response.success) {
								alert('Alert settings saved successfully!');
							} else {
								alert('Error saving settings: ' + (response.data || 'Unknown error'));
							}
						},
					error: function(xhr, status, error) {
						alert('Error saving settings. Please try again.');
					}
					});
				});
				
				// Initialize first tab as active
				$('.tab-panel:first').addClass('active');
			});
		</script>
		<?php
