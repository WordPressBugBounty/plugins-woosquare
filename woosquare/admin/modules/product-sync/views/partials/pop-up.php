<?php
/**
 * Woosquare_Plus v1.0 by wpexperts.io
 *
 * @package Woosquare_Plus
 */

?>
<div class="pop-up-content">
	<p><?php echo esc_html__( 'Choose items to synchronize:' ); ?><?php echo ($_GET['action'] == 'get_non_sync_woo_data') ? __( ' WooCommerce to Square' , 'woosquare' ) : (($_GET['action'] == 'get_non_sync_square_data') ? __( ' Square to WooCommerce', 'woosquare' ) : ''); ?></p>
	
	<div class="sync-data">
		<div class="sync-elements">
			<h2><?php echo esc_html__( 'Categories' ); ?> 
			<span class="checkuncheck">
					<input type="button" class="check button button-primary button-hero load-customize hide-if-no-customize extcheck extcat" value="Check / Uncheck All" />
			</span>
		</h2>
			
			<?php if ( ! empty( $target_categories ) ) : ?>
				<div class="scrollwrap">
					<div id="sync-category">
						<?php if ( ! empty( $add_categories ) ) : ?>
							<h3><?php echo esc_html__( 'CREATE' ); ?></h3>
							<div class="square-create ">
									<?php
									$target_object = 'add_categories';
									include 'cat-display.php';
									?>
							</div>
						<?php endif; ?>
							<?php if ( ! empty( $update_categories ) ) : ?>
							<h3><?php echo esc_html__( 'Sync/Update.' ); ?></h3>
							<div class="square-update ">
								<?php
									$target_object = 'update_categories';
									include 'cat-display.php';
								?>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $delete_categories ) ) : ?>
							<h3><?php echo esc_html__( 'DELETE' ); ?></h3>
							<div class="square-delete ">
								<?php
									$target_object = 'delete_categories';
									include 'cat-display.php';
								?>
							</div>
						<?php endif; ?>
	
	
					</div>
				</div>
			<?php else : ?>
				<?php echo esc_html__( 'No Categories found to synchronize' ); ?>
			<?php endif; ?>
		</div>

		<div class="sync-elements">   
			
			<h2><?php echo esc_html__( 'Products' ); ?>
			<span class="checkuncheck">
					<input type="button" class="check button button-primary button-hero load-customize hide-if-no-customize extcheck extpro" value="Check / Uncheck All" />
			</span>	
		</h2>   

		<div class="scrollwrap">
			<div id="sync-product">
				<?php if ( ! empty( $target_products ) || $one_products_update_checkbox ) : ?>
					<?php if ( ! empty( $add_products ) ) : ?>
						<h3><?php echo esc_html__( 'CREATE' ); ?></h3>
						<div class="square-create ">
							<?php
								$target_object = 'add_products';

								include 'prod-display.php';
							?>
						</div>
						<?php
					endif;
					?>
	
				
					<?php if ( $one_products_update_checkbox ) : ?>
						<h3><?php echo esc_html__( 'Sync/Update.' ); ?></h3>
						<div class="square-update ">
						<div class='square-action'>
							<input name='woo_square_product' type='checkbox' value='update_products' checked />Update other products
						</div>
						</div>
					<?php else : ?>           
						<?php if ( ! empty( $update_products ) ) : ?>
							<h3><?php echo esc_html__( 'Sync/Update.' ); ?></h3>
							<div class="square-update ">
								<?php
									$target_object = 'update_products';
									include 'prod-display.php';
								?>
	
							</div>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( ! empty( $delete_products ) ) : ?>
						<h3><?php echo esc_html__( 'DELETE' ); ?></h3>
						<div class="square-delete ">
							<?php
								$target_object = 'delete_products';
								include 'prod-display.php';
							?>
						</div>
					<?php endif; ?>
				<?php else : ?>
					<?php echo esc_html__( 'No Products found to synchronize' ); ?>
				<?php endif; ?>
				
				
				<?php if ( ! empty( $sku_missin_inside_product ) ) : ?>
				<h2><?php echo esc_html__( 'Sku Missing Products' ); ?></h2> 
						<div class="square-create ">
							<?php
								$target_object = 'sku_missin_inside_product';
								include 'prod-display.php';
							?>
						</div>
				<?php endif; ?>
				
				</div>
		</div>
					
			
		</div>
	</div>
</div>
