<?php
/**
 * Woosquare_Plus v1.0 by wpexperts.io
 *
 * @package Woosquare_Plus
 */

?>

<div>
	<?php




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
		
			<input name='woo_square_category' class="woo_square_category" type='checkbox' value='<?php echo esc_html( $row['checkbox_val'] ); ?>' checked />

			<?php if ( ! empty( $row['woo_id'] ) ) : ?>
				<a target='_blank' href='<?php echo esc_url( admin_url( 'edit-tags.php?action=edit&taxonomy=product_cat&tag_ID=' . $row['woo_id'] . '&post_type=product' ) ); ?>'><?php echo esc_html( $row['name'] ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $row['name'] ); ?>
			<?php endif; ?>

		</div>                        
	<?php endforeach; ?>
</div>
