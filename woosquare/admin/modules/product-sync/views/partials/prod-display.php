<?php
/**
 * Woosquare_Plus v1.0 by wpexperts.io
 *
 * @package Woosquare_Plus
 */

?>
<div>
	<?php

	if ( ! isset( $_REQUEST['woosquare_popup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['woosquare_popup_nonce'] ) ), 'my_woosquare_ajax_nonce' ) ) {
		exit;
	}
	if ( empty( $_REQUEST['optionsaved'] ) ) {
		$checked = 'checked';
	} elseif ( ! empty( $_REQUEST['from'] ) && 'square' === $_REQUEST['from'] ) {
			$prd = get_option( 'woo_square_listsaved_products_square' );
	} else {
		$prd = get_option( 'woo_square_listsaved_products_wooco' );
	}

	if ( is_array( $$target_object ) ) {
		uasort(
			$$target_object,
			function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);
	}


	foreach ( $$target_object as $row ) :
		?>
												  
		<div class='square-action'>
		
		<?php
		if (
				( ! isset( $row['sku_missin_inside_product'] ) && 'sku_missin_inside_product' !== @$row['sku_missin_inside_product'] ) &&
				( ! isset( $row['sku_misin_squ_woo_pro_variable'] ) && 'sku_misin_squ_woo_pro_variable' !== @$row['sku_misin_squ_woo_pro_variable'] )
			) {
			if ( isset( $_REQUEST['optionsaved'] ) && ! empty( sanitize_text_field( wp_unslash( $_REQUEST['optionsaved'] ) ) ) ) {
				if ( ! empty( $prd ) ) {
					if ( in_array( $row['checkbox_val'], $prd, true ) ) {
						$checked = 'checked';
					} else {
						$checked = '';
					}
				}
			}

			?>
			<input name='woo_square_product' class="woo_square_product modifier_update" type='checkbox' value='<?php echo isset( $row['checkbox_val'] ) ? esc_attr( $row['checkbox_val'] ) : ''; ?>' <?php echo isset( $checked ) ? esc_attr( $checked ) : ''; ?> />
			<?php
		}


		?>
			<?php if ( ! empty( $row['woo_id'] ) ) : ?>
				<a target='_blank' href='<?php echo esc_url( admin_url( "post.php?post={$row['woo_id']}&action=edit" ) ); ?>'><?php echo esc_html( $row['name'] ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $row['name'] ); ?>
			<?php endif; ?>
			<br>
			<?php if ( ! empty( $row['modifier_set_name'] ) ) : ?>
				<?php
				foreach ( $row['modifier_set_name'] as $modifier_name ) :
					$modifier_name = ( explode( '|', $modifier_name ) );

					?>
					<?php if ( empty( $modifier_name[1] ) ) { ?>
					<input name='woo_square_product' id='woo_square_product' class='modifier_set_name modifier_update' type='checkbox' value='<?php echo esc_attr( str_replace( ' ', '-', $modifier_name[0] ) . '_' . $modifier_name[1] . '_' . $modifier_name[2] . '_' . $modifier_name[3] . '_' . $modifier_name[4] . '_' . str_replace( ' ', '-', strtolower( $modifier_name[5] ) ) . '_add_modifier' ); ?>' <?php echo isset( $checked ) ? 'checked' : ''; ?> />
					<?php } else { ?>
					<input name='woo_square_product' id='woo_square_product' class='modifier_set_name modifier_update' type='checkbox' value='<?php echo esc_attr( str_replace( ' ', '-', $modifier_name[0] ) ) . '_' . esc_attr( $modifier_name[1] ) . '_' . esc_attr( $modifier_name[2] ) . '_' . esc_attr( $modifier_name[3] ) . '_' . esc_attr( $modifier_name[4] ) . '_' . esc_attr( str_replace( ' ', '-', strtolower( $modifier_name[5] ) ) ) . '_modifier'; ?>' <?php echo isset( $checked ) && 'checked' === $checked ? 'checked' : ''; ?> />
				<?php } ?>
				
						<?php if ( ! empty( $row['woo_id'] ) ) : ?>
					<a target='_blank' href='<?php echo esc_url( admin_url( "post.php?post={$row['woo_id']}&action=edit" ) ); ?>'><?php echo esc_html( $modifier_name[0] ); ?></a><br>
				<?php else : ?>
					<?php echo esc_html( $modifier_name[0] ); ?> <br>
				<?php endif; ?>
				<?php endforeach; ?>

			<?php endif; ?>

		<?php if ( ! array_key_exists( 'direction', $row ) && ! empty( $row['modifier_set_name'] ) ) { ?>

			<input name='woo_square_product' class="modifier_end" type='checkbox' style="display: none" value='modifier_set_end' checked="checked" disabled />

<?php } ?>
		</div>                        
	<?php endforeach; ?>
</div>
