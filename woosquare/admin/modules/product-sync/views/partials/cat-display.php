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
		wp_die( esc_html( __( 'Cheatin&#8217; huh?', 'woosquare-square' ) ) );
	}
	if ( empty( $_REQUEST['optionsaved'] ) ) {
		$checked = 'checked';
	} elseif ( ! empty( $_REQUEST['from'] ) && 'square' === $_REQUEST['from'] ) {
			$prd = get_option( 'woo_square_listsaved_categories_square' );
	} else {
		$prd = get_option( 'woo_square_listsaved_categories_wooco' );
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
		if ( ! empty( $_REQUEST['optionsaved'] ) && ! empty( $prd ) ) {
			if ( in_array( $row['checkbox_val'], $prd, true ) ) {
				$checked = 'checked';
			} else {
				$checked = '';
			}
		} else {
			$checked = '';
		}
		?>
			<input name='woo_square_category' class="woo_square_category" type='checkbox' value='<?php echo esc_html( $row['checkbox_val'] ); ?>' <?php echo esc_html( $checked ); ?> />

		<?php if ( ! empty( $row['woo_id'] ) ) : ?>
				<a target='_blank' href='<?php echo esc_url( admin_url( 'edit-tags.php?action=edit&taxonomy=product_cat&tag_ID=' . $row['woo_id'] . '&post_type=product' ) ); ?>'><?php echo esc_html( $row['name'] ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $row['name'] ); ?>
			<?php endif; ?>

		</div>                        
	<?php endforeach; ?>
</div>
