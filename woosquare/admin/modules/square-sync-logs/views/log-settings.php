<?php
	/**
	 * Square Payment Gateway Settings Configuration.
	 *
	 * This file contains the settings configuration for the Square Payment Gateway integration.
	 * It defines various options for enabling/disabling payment methods, capturing payments, etc.
	 *
	 * @package Woosquare_Plus
	 */

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
				</svg> Product Syncing Logs</h1>
						<div class="tabset">
				<!-- Tab 1 -->
				<input type="radio" data-sync-dyrection="woo_to_square" name="tabset" id="tab1" aria-controls="woo-to-square-log-section" checked>
				<label for="tab1">Woo to Square Sync Logs</label>
				<!-- Tab 2 -->
				<input type="radio" data-sync-dyrection="square_to_woo" name="tabset" id="tab2" aria-controls="square-to-woo-log-section">
				<label for="tab2">Square to Woo Sync Logs</label>
			  
				<div class = "filter_section">
				<label for="fromDate">From</label>
				<input type="date" id="fromDate" class="sync_log_datepicker">

				<label for="toDate">To</label>
				<input type="date" id="toDate" class="sync_log_datepicker">
				
				<button id="log-filter" class="log_filter_botton"><i class="fas fa-filter" aria-hidden="true"></i> Filter</button>
				
				<button id="log-reset" data-sync-dyrection="woo_to_square" class="log_reset_botton"><i class="fas fa-sync-alt" aria-hidden="true"></i> Reset</button>
				<button id="log-delete-all" class="log_delete_all_botton"><i class="fas fa-trash-alt" aria-hidden="true"></i> Delete All</button>
					
				</div>
				<div class="tab-panels">
				<section id="woo-to-square-log-section" class="tab-panel">
					
					<div class="table-wrapper">
					<div class="Log-table">
						<table class="product_list_table">
							<thead class="product_list_table_head">
								<tr>
									<th class="product_list_table_heading"><span class="product_list_table_heading_text">Time</span></th>
									<th class="product_list_table_heading"><span class="product_list_table_heading_text">Status</span></th>
									<th class="product_list_table_heading"><span class="product_list_table_heading_text">Message</span></th>
									<th class="product_list_table_heading"><span class="product_list_table_heading_text">Actions</span></th>
								</tr>
							</thead>
									
							<tbody class="product_list_table_body woo_to_square_table_body">	
								<?php
								global $wpdb;
								$table_name            = $wpdb->prefix . WOO_SQUARE_ITEM_SYNC_LOGS_TABLE;
								$get_results           = 'get_results';
								$woo_to_square_results = $wpdb->$get_results( "SELECT * FROM $table_name WHERE `sync_direction` = 'woo_to_square' AND `enviroment` = '" . get_transient( 'is_sandbox' ) . "' ORDER BY `log_time` DESC" );

								if ( isset( $woo_to_square_results ) && ! empty( $woo_to_square_results ) ) {
									// Display the logs.
									foreach ( $woo_to_square_results as $result ) {
										if ( isset( $result->sync_direction ) && 'woo_to_square' === $result->sync_direction ) {
											$sync_status = strtolower( str_replace( ' ', '_', $result->status ) );
											echo '<tr class="product_list_table_body_row woo_to_square_table_body_row">
													<td class="product_list_table_data woo_to_square_table_data">' . esc_html( isset( $result->log_time ) ? $result->log_time : '' ) . '</td>
													<td class="product_list_table_data woo_to_square_table_data"><span class="' . esc_attr( $sync_status ) . '">' . esc_html( isset( $result->status ) ? $result->status : '' ) . '</span></td>
													<td class="product_list_table_data woo_to_square_table_data">' . esc_html( isset( $result->message ) ? $result->message : '' ) . '</td>
													<td class="product_list_table_data woo_to_square_table_data">
														<button id="woo-to-square-view-log" data-log-id="' . esc_html( isset( $result->id ) ? $result->id : '' ) . '" class="log_action_botton"><i class="far fa-eye-slash" style="color: #7460EE;"></i></button>
														<button id="woo-to-square-delete-log" data-log-id="' . esc_html( isset( $result->id ) ? $result->id : '' ) . '" class="log_delete_action_botton"><i class="far fa-trash-alt" style="color: #cb1515;"></i></button>
													</td>
												  </tr>';
										}
									}
								} else {
									echo '<tr class="empty-row"><td colspan="6">' . esc_html__( 'No logs found.', 'woosquare' ) . '</td></tr>';
								}

								?>
									
							</tbody>
							<!-- Add more rows as needed -->
						</table>
						<div id="filter-sync-loader" style="display:none">
							<img width=30%; height=30% src="<?php echo esc_url( plugins_url( 'views/images/ring.gif', __DIR__ ) ); ?>"
								alt="loading">
						</div>
					</div>
					</div>
				</section>
				<section id="square-to-woo-log-section" class="tab-panel">
					
					<div class="table-wrapper">
					<div class="Log-table">
						<table class="product_list_table">
							<thead class="product_list_table_head">
								<tr>
									<th class="product_list_table_heading"><span class="product_list_table_heading_text">Time</span></th>
									<th class="product_list_table_heading"><span class="product_list_table_heading_text">Status</span></th>
									<th class="product_list_table_heading"><span class="product_list_table_heading_text">Message</span></th>
									<th class="product_list_table_heading"><span class="product_list_table_heading_text">Actions</span></th>
								</tr>
							</thead>
							<tbody class="product_list_table_bod square_to_woo_table_body">
								<?php
								$get_results           = 'get_results';
								$square_to_woo_results = $wpdb->$get_results( "SELECT * FROM $table_name WHERE `sync_direction` = 'square_to_woo' AND `enviroment` = '" . get_transient( 'is_sandbox' ) . "' ORDER BY `log_time` DESC" );

								if ( isset( $square_to_woo_results ) && ! empty( $square_to_woo_results ) ) {
									// Display the logs.
									foreach ( $square_to_woo_results as $result ) {
										if ( isset( $result->sync_direction ) && 'square_to_woo' === $result->sync_direction ) {
											$sync_status = strtolower( str_replace( ' ', '_', $result->status ) );
											echo '<tr class="product_list_table_body_row square_to_woo_table_body_row">
													<td class="product_list_table_data square_to_woo_table_data">' . esc_html( isset( $result->log_time ) ? $result->log_time : '' ) . '</td>
													<td class="product_list_table_data square_to_woo_table_data"><span class="' . esc_attr( $sync_status ) . '">' . esc_html( isset( $result->status ) ? $result->status : '' ) . '</span></td>
													<td class="product_list_table_data square_to_woo_table_data">' . esc_html( isset( $result->message ) ? $result->message : '' ) . '</td>
													<td class="product_list_table_data square_to_woo_table_data">
														<button id="square-to-woo-view-log" data-log-id="' . esc_html( isset( $result->id ) ? $result->id : '' ) . '" class="log_action_botton"><i class="far fa-eye-slash" style="color: #7460EE;"></i></button>
														<button id="square-to-woo-delete-log" data-log-id="' . esc_html( isset( $result->id ) ? $result->id : '' ) . '" class="log_delete_action_botton"><i class="far fa-trash-alt" style="color: #cb1515;"></i></button>
													</td>
												  </tr>';
										}
									}
								} else {
									echo '<tr class="empty-row"><td colspan="6">' . esc_html__( 'No logs found.', 'woosquare' ) . '</td></tr>';
								}

								?>
							</tbody>
							<!-- Add more rows as needed -->
						</table>
						<div id="filter-sync-loader" style="display:none">
							<img width=30%; height=30% src="<?php echo esc_url( plugins_url( 'views/images/ring.gif', __DIR__ ) ); ?>"
								alt="loading">
						</div>
					</div>
					</div>
				</section>
				</div>
			  
			</div>
		</div>
		
	</div>


</div>
<div class="cd-popup" role="alert" style="display:none;">
	<div class="cd-popup-container">
		<div id="cd-popup-heading">
			<h1>Log Details</h1>
		</div>
		<div id="sync-loader">
			<img width=50%; height=50% src="<?php echo esc_url( plugins_url( 'views/images/ring.gif', __DIR__ ) ); ?>"
				alt="loading">
		</div>
		<div id="log-sync-content" style="display:none">
			<div class="detail_tabset">

				<!-- Tab 1 -->
				<input type="radio" name="detail_tabset" id="synced_products" aria-controls="products-log-detail-section" checked>
				<label for="synced_products">Products</label>
				<!-- Tab 2 -->
				<input type="radio" name="detail_tabset" id="synced_categories" aria-controls="categories-log-detail-section">
				<label for="synced_categories">Categories</label>

				<div class="detail_tab-panels">
					<section id="products-log-detail-section" class="detail_tab-panel">
						<div class="log_detail_wrapper">
							<div class="log-de-table">
								<table class="log_detail_table">
									<thead class="log_detail_table_head">
										<tr>
											<th class="log_detail_table_heading"><span class="log_detail_table_heading_text">Product</span></th>
											<th class="log_detail_table_heading"><span class="log_detail_table_heading_text">SKU</span></th>
											<th class="log_detail_table_heading"><span class="log_detail_table_heading_text">Sync Type</span></th>
											<th class="log_detail_table_heading"><span class="log_detail_table_heading_text">Error Details</span></th>
										</tr>
									</thead>
									<tbody class="log_detail_table_body">
										
									</tbody>
									<!-- Add more rows as needed -->
								</table>
							</div>
						</div>
					</section>
					<section id="categories-log-detail-section" class="detail_tab-panel">
						<div class="log_detail_wrapper">
							<div class="log-de-table">
								<table class="log_detail_table">
									<thead class="log_detail_table_category_head">
										<tr>
											<th class="log_detail_table_category_heading"><span class="log_detail_table_category_heading_text">Category</span></th>
											<th class="log_detail_table_category_heading"><span class="log_detail_table_category_heading_text">Sync Type</span></th>
											<th class="log_detail_table_category_heading"><span class="log_detail_table_category_heading_text">Error Details</span></th>
										</tr>
									</thead>
									<tbody class="log_detail_table_category_body">
										
									</tbody>
									<!-- Add more rows as needed -->
								</table>
							</div>
						</div>
					</section>
				</div>

			</div>
		</div>
		<ul class="cd-buttons end">
			<li><button id="sync-processing" href="#0" class="btn btn-rounded btn-warning">Close</button></li>
		</ul>
		<a href="#0" class="cd-popup-close img-replace"></a>
	</div> <!-- cd-popup-container -->
</div> <!-- cd-popup -->
