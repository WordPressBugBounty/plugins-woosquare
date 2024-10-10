(function ( $ ) {
	'use strict';
	
	jQuery(document).ready(function(){
		
		jQuery('.log_filter_botton').on('click', function(){
			var sync_direction = jQuery('input[name=tabset]:checked').attr("data-sync-dyrection");
			var fromDate = jQuery('#fromDate').val();
			var toDate = jQuery('#toDate').val();
			if( fromDate != '' ){
				if(sync_direction == 'square_to_woo'){
					jQuery('.square_to_woo_table_body').html(''); 
					jQuery('#filter-sync-loader').show(); 
					jQuery.ajax({
						type: "POST",
						url: square_sync_log_params.ajax_url,
						data: 'action=get_filter_sync_log&fromDate='+fromDate+'&toDate='+toDate+'&sync_direction='+sync_direction+'&log_nonce='+square_sync_log_params.log_nonce,
						success: function (html)
						{	
							jQuery('#filter-sync-loader').hide(); 
							jQuery('.square_to_woo_table_body').append(html);         
						}
					});
				} else {
					jQuery('.woo_to_square_table_body').html(''); 
					jQuery('#filter-sync-loader').show(); 
					jQuery.ajax({
						type: "POST",
						url: square_sync_log_params.ajax_url,
						data: 'action=get_filter_sync_log&fromDate='+fromDate+'&toDate='+toDate+'&sync_direction='+sync_direction+'&log_nonce='+square_sync_log_params.log_nonce,
						success: function (html)
						{	
							jQuery('#filter-sync-loader').hide(); 
							jQuery('.woo_to_square_table_body').append(html);         
						}
					});
				}
			}
		})
		
		jQuery('.log_reset_botton').on('click', function(){
			var sync_direction = jQuery('input[name=tabset]:checked').attr("data-sync-dyrection");
			jQuery('#fromDate').val('');
			jQuery('#toDate').val('');
			if(sync_direction == 'woo_to_square'){
				jQuery('.woo_to_square_table_body').html(''); 
				jQuery('#filter-sync-loader').show(); 
				jQuery.ajax({
					type: "POST",
					url: square_sync_log_params.ajax_url,
					data: 'action=reset_filter_sync_log&sync_direction='+sync_direction+'&log_nonce='+square_sync_log_params.log_nonce,
					success: function (html)
					{
						jQuery('#filter-sync-loader').hide(); 
						jQuery('.woo_to_square_table_body').append(html);  
					}
				});
			} else {
				jQuery('.square_to_woo_table_body').html(''); 
				jQuery('#filter-sync-loader').show(); 
				jQuery.ajax({
					type: "POST",
					url: square_sync_log_params.ajax_url,
					data: 'action=reset_filter_sync_log&sync_direction='+sync_direction,
					success: function (html)
					{
						jQuery('#filter-sync-loader').hide(); 
						jQuery('.square_to_woo_table_body').append(html);  
					}
				});
			}
		})
		
		jQuery('.log_delete_all_botton').on('click', function(){
			var sync_direction = jQuery('input[name=tabset]:checked').attr("data-sync-dyrection");
			if (confirm("Are you sure?") == true) {
				jQuery.ajax({
					type: "POST",
					url: square_sync_log_params.ajax_url,
					data: 'action=delete_all_sync_log&sync_direction='+sync_direction+'&log_nonce='+square_sync_log_params.log_nonce,
					success: function (response)
					{
						if(response == 1){
							window.location.reload();
						}           
					}
				});
			}
		})
		jQuery(document).on('click', '.log_delete_action_botton', function(){
			if (confirm("Are you sure?") == true) {
				var log_id = this.getAttribute("data-log-id");
				jQuery.ajax({
					type: "POST",
					url: square_sync_log_params.ajax_url,
					data: 'action=delete_sync_log&log_id='+log_id+'&log_nonce='+square_sync_log_params.log_nonce,
					success: function (response)
					{
						if(response == 1){
							window.location.reload();
						}           
					}
				});
			}
		})
		
		jQuery(document).on('click', '.log_action_botton', function(){

			jQuery('#sync-loader').show();
			jQuery('#log-sync-content').hide();
			jQuery('.log_detail_table_body').html('');
			jQuery('.log_detail_table_category_body').html('');
			var log_id = this.getAttribute("data-log-id");
			jQuery('.cd-popup').addClass('is-visible');
			jQuery('.cd-popup').css("display", "block");
			jQuery.ajax({
					type: "POST",
					url: square_sync_log_params.ajax_url,
					data: 'action=get_sync_log_detail&log_id='+log_id+'&log_nonce='+square_sync_log_params.log_nonce,
					success: function (response)
					{
						response = JSON.parse(response);
						jQuery('#sync-loader').hide();
						jQuery('#log-sync-content').show();
						
						if(response.category_html){
							jQuery('.log_detail_table_category_body').append(response.category_html);
						}
						if (response.product_html){
							jQuery('.log_detail_table_body').append(response.product_html);
						}
					}
				});
		})
	})
	
}( jQuery ) );