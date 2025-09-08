jQuery( document ).ready( function( $ ) {
	'use strict';
	
	jQuery('input.woosquare_gtin_code_field').on('input',function(e){
		if(jQuery(this).val().replace(/ /g,'').length < 8){
			jQuery('.woosquare_gtin_field_validation').html('GTIN must be atleast 8 digits');
		}else if(jQuery(this).val().replace(/ /g,'').length > 14){
			jQuery('.woosquare_gtin_field_validation').html('8, 12, 13 or 14 digits only');
		}else if(jQuery(this).val().replace(/ /g,'').length > 8 && jQuery(this).val().replace(/ /g,'').length < 12){
			jQuery('.woosquare_gtin_field_validation').html('8, 12, 13 or 14 digits only');
		}else if(jQuery(this).val().replace(/ /g,'').length == 12){
			jQuery('.woosquare_gtin_field_validation').html('');
		}else if(jQuery(this).val().replace(/ /g,'').length == 13){
			jQuery('.woosquare_gtin_field_validation').html('');
		}else if(jQuery(this).val().replace(/ /g,'').length == 14){
			jQuery('.woosquare_gtin_field_validation').html('');
		}else{
			jQuery('.woosquare_gtin_field_validation').html('');
		}
	});
	$(document).on('woocommerce_variations_loaded', function(event) {
		jQuery('input.woosquare_variation_gtin_code_field').on('input',function(e){
			if(jQuery(this).val().replace(/ /g,'').length < 8){
				jQuery('.woosquare_variation_gtin_field_validation').html('GTIN must be atleast 8 digits');
			}else if(jQuery(this).val().replace(/ /g,'').length > 14){
				jQuery('.woosquare_variation_gtin_field_validation').html('8, 12, 13 or 14 digits only');
			}else if(jQuery(this).val().replace(/ /g,'').length > 8 && jQuery(this).val().replace(/ /g,'').length < 12){
				jQuery('.woosquare_variation_gtin_field_validation').html('8, 12, 13 or 14 digits only');
			}else if(jQuery(this).val().replace(/ /g,'').length == 12){
				jQuery('.woosquare_variation_gtin_field_validation').html('');
			}else if(jQuery(this).val().replace(/ /g,'').length == 13){
				jQuery('.woosquare_variation_gtin_field_validation').html('');
			}else if(jQuery(this).val().replace(/ /g,'').length == 14){
				jQuery('.woosquare_variation_gtin_field_validation').html('');
			}else{
				jQuery('.woosquare_variation_gtin_field_validation').html('');
			}
		});
	});
	
	
});