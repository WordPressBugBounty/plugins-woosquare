
	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	 function cons(res){
	}
		
	 jQuery.noConflict();
	 jQuery(document).ready(function ($) {
		 

	
		
		 jQuery('.enable_plugin').click(function(e) {
			 e.preventDefault();
			if (!jQuery(this).is(':checked')) {
				cons('checked');
				var data = {
					action: 'en_plugin',
					status: 'enab',
					pluginid: jQuery(this).attr('id'),
					nonce: my_ajax_backend_scripts.nonce,
				};
			} else {
				var data = {
					action: 'en_plugin',
					status: 'disab',
					pluginid: jQuery(this).attr('id'),
					nonce: my_ajax_backend_scripts.nonce,
				};
			}
			cons(data);
			jQuery.post(my_ajax_backend_scripts.ajax_url, data, function(response) {
				var response = JSON.parse(response);
				// if(response.status){
					window.location.replace(window.location.href);
				// }
			}); 
		});
		
		
		jQuery('.gpayred').click(function(e) {
			e.preventDefault();
			window.location.replace(jQuery(this).parent().attr('href'));
			
		});
		
	 });
	 
	 
	
	jQuery.noConflict();
	jQuery(document).ready(function ($) {
		
		jQuery('.enable_mode_check').click(function(e) {
			e.preventDefault();
			if (jQuery(this).val() == 'production') {
				var data = {
					action: 'enable_mode_checker',
					status: 'enable_production',
					mode_checker_nonce: jQuery('.mode_checker_nonce').val(),
				};
			
			} else {
				var data = {
					action: 'enable_mode_checker',
					status: 'enable_sandbox',
					mode_checker_nonce: jQuery('.mode_checker_nonce').val(),
				};
			}
		
			jQuery.post(my_ajax_backend_scripts.ajax_url, data, function(response) { 			
				var response = JSON.parse(response);  
				if(response.status){
					window.location.replace(window.location.href);
				}
			}); 
	   
		});
	   
	});