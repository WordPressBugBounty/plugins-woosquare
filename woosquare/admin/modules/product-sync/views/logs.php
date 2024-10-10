<?php
/**
 * Woosquare_Plus v1.0 by wpexperts.io
 *
 * @package Woosquare_Plus
 */

?>

<div class="wrap">
	<div class="welcome-panel">

		<form method="post">

			<?php echo esc_html__( 'Synchronization type:', 'woosquare' ); ?>
			<select name="log_sync_type">
				<option 
				<?php
				if ( is_null( $sync_type ) ) {
					echo 'selected';}
				?>
										value="any">Any</option>
				<option 
				<?php
				if ( $sync_type === $helper::SYNC_TYPE_MANUAL ) {
					echo 'selected';}
				?>
					value="<?php echo esc_html( $helper::SYNC_TYPE_MANUAL ); ?>"> <?php echo esc_html( $helper->get_sync_type( $helper::SYNC_TYPE_MANUAL ) ); ?> </option>
				<option 
				<?php
				if ( $sync_type === $helper::SYNC_TYPE_AUTOMATIC ) {
					echo 'selected';}
				?>
				value="<?php echo esc_html( $helper::SYNC_TYPE_AUTOMATIC ); ?>"> <?php echo esc_html( $helper->get_sync_type( $helper::SYNC_TYPE_AUTOMATIC ) ); ?> </option>
			</select> 

			<?php echo esc_html__( 'Synchronization direction:' ); ?>
			<select name="log_sync_direction">
				<option 
				<?php
				if ( is_null( $sync_direction ) ) {
					echo 'selected';}
				?>
														value="any">Any</option>
				<option 
				<?php
				if ( $sync_direction === $helper::SYNC_DIRECTION_WOO_TO_SQUARE ) {
					echo 'selected';}
				?>
				value="<?php echo esc_html( $helper::SYNC_DIRECTION_WOO_TO_SQUARE ); ?>"> <?php echo esc_html( $helper->get_sync_direction( $helper::SYNC_DIRECTION_WOO_TO_SQUARE ) ); ?> </option>
				<option 
				<?php
				if ( $sync_direction === $helper::SYNC_DIRECTION_SQUARE_TO_WOO ) {
					echo 'selected';}
				?>
				value="<?php echo esc_html( $helper::SYNC_DIRECTION_SQUARE_TO_WOO ); ?>"> <?php echo esc_html( $helper->get_sync_direction( $helper::SYNC_DIRECTION_SQUARE_TO_WOO ) ); ?> </option>
			</select>

			<?php echo esc_html__( 'From:' ); ?>
			<select name="log_sync_date">
				<option 
				<?php
				if ( 1 === $sync_date ) {
					echo 'selected';}
				?>
				value="1">Last day</option>
				<option 
				<?php
				if ( 7 === $sync_date ) {
					echo 'selected';}
				?>
				value="7">Last Week</option>
				<option 
				<?php
				if ( 30 === $sync_date ) {
					echo 'selected';}
				?>
				value="30">Last Month</option>
				<option 
				<?php
				if ( is_null( $sync_date ) ) {
					echo 'selected';}
				?>
				value="any">All</option>
			</select>
			<input type="submit" value="Get Logs" class="button button-primary">
		</form>

		<?php
		$rows             = 0;
		$last_rows        = $rows;
		$last_sync_log_id = -1;
		?>
		<?php if ( empty( $results ) ) : ?>
			<div class='empty-logs-data'>
				<?php echo esc_html__( 'No logs found' ); ?>
			</div>
		<?php else : ?>
			<?php foreach ( $results as $result ) : ?>

				<?php
				if ( ( $last_sync_log_id !== $result->log_id ) && ( $result->log_action === $helper::ACTION_SYNC_START ) ) :  // new sync process.
					$last_sync_log_id = $result->log_id;                                   // START new synchronization row.
					$last_rows        = $rows; // last sync rows number.
					$rows             = 0;         // new sync rows number.
					if ( $last_rows > 0 ) :
						?>
						</tbody>
						</table></div>
						<?php
					endif;
					?>
						
					<?php if ( -1 !== $last_sync_log_id ) : // at least 1 row displayed. ?>
						</div> <!-- log data -->
					<?php endif; ?>




					<div class="log-data">
						<a class="collapse" href="javascript:void(0);">
							<span class="dashicons dashicons-arrow-right-alt2"></span>
							<span class="log-title-text">
								<?php
								echo esc_html( $helper->get_sync_type( $result->log_type ) ) . ' ' . esc_html( $helper->get_sync_direction( $result->log_direction ) );
								?>
							</span>
							<span class="log-title-date"> 
								<?php
								echo esc_html( $result->log_date );
								?>
							</span>
						</a>
						<?php

				endif;
				if ( $result->parent_id ) :                                       // CHILD data row.
					if ( 0 === $rows ) :  // first row in table.
						?>
						
						<div class='hidden grid-div'><table class='gridtable'>
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Synchronized Object' ); ?></th>
									<th><?php echo esc_html__( 'Action' ); ?></th>
									<th><?php echo esc_html__( 'Status' ); ?></th>
									<th><?php echo esc_html__( 'Message' ); ?></th>

								</tr>
							</thead>
							<tbody>

								<?php
							endif;
							++$rows;
					?>
							<tr>
								<td> 
									<?php if ( $result->action !== $helper::ACTION_DELETE ) :  // not delete. ?>
										
										<?php echo esc_html( $helper->get_target_type( $result->target_type ) ); ?> - 
										<a target='_blank' href="
										<?php
										if ( $result->target_type !== $helper::TARGET_TYPE_CATEGORY ) :  // product.
											echo esc_url( admin_url( 'post.php?post=' . $result->target_id . '&action=edit' ) );
										else :               // category.
											echo esc_url( admin_url( 'edit-tags.php?action=edit&taxonomy=product_cat&tag_ID=' . $result->target_id . '&post_type=product' ) );
										endif;
										?>
										"><?php echo esc_html( $result->name ); ?></a>
											<?php
										else :
											echo esc_html( $result->name );
										endif;
										?>
								</td>
								<td><?php echo esc_html( $helper->get_action( $result->action ) ); ?></td>    
								<td class='center'>
									<?php
									$target_status = esc_attr( $helper->get_target_status( $result->target_status ) );
									?>
									<span class="dashicons <?php echo $result->target_status === $helper::TARGET_STATUS_SUCCESS ? 'dashicons-yes' : 'dashicons-no-alt'; ?>" title="<?php echo esc_html( $target_status ); ?>"></span>
								</td>
								<td><?php echo esc_html( $result->message ); ?></td> 

							</tr>

					<?php
				else :
					?>
					<div class="hidden grid-div">
						<?php echo esc_html__( 'No entries found' ); ?>
				<?php endif; ?>
				
			<?php endforeach; ?>
				<?php if ( $last_rows > 0 ) : ?>
					</tbody>
				</table></div>
				<?php endif; ?>    
				<?php if ( -1 !== $last_sync_log_id ) : // at least 1 row displayed. ?>
					</div> <!-- log data -->
				<?php endif; ?>
		<?php endif; ?>
  


</div>


