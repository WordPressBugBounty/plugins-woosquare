jQuery( document ).ready(function() {



	function verify_apple_domain() {
        jQuery('.apple_domain_error').text('');
        jQuery('.apple_verify_domain').prop('disabled', true);
        jQuery('.apple_verify_domain').text('Verifying');
        var data = {
            'action' : 'verify_apple_domain',
            'verify_apple_nonce' : jQuery('#apple_domain_verification').val(),
        }
        jQuery.ajax({
            url: my_ajax_backend_scripts.ajax_url, // Replace with your server-side URL
            type: 'POST', // Or 'GET' depending on your backend
            data: data, // Send the input value to the server
            success: function (response) {
                var response = JSON.parse(response);
                if(response.verification_result){
                    jQuery('.apple_verify_domain').html('<i class="fa fa-check-square" aria-hidden="true"></i> Verified');
                    jQuery('.apple_verify_domain').prop('disabled', true);
                } else {
                    jQuery('.apple_verify_domain').text('Verify domain');
                    jQuery('.apple_domain_error').text(response.message);
                    jQuery('.apple_verify_domain').prop('disabled', false);
                }
            },
            error: function (xhr, status, error) {
                // Handle errors
                console.error('AJAX Error:', error);
            }
        });
    }
	if(jQuery('#apple_pay'+square_index_params.sandbox+'_enabled:checked').length){

		if (jQuery('#apple_pay'+square_index_params.sandbox+'_enabled').is(':checked')) {
            // Show the Verify domain button if checked
            jQuery('.apple_verify_domain').show();
			verify_apple_domain();
        } else {
            // Hide the Verify domain button if unchecked
            jQuery('.apple_verify_domain').hide();
        }
	}
	jQuery('#apple_pay'+square_index_params.sandbox+'_enabled').on('change', function() {
        // Check if the checkbox is checked
        if (jQuery(this).is(':checked')) {
            // Show the Verify domain button if checked
            jQuery('.apple_verify_domain').show();
        } else {
            // Hide the Verify domain button if unchecked
            jQuery('.apple_verify_domain').hide();
        }
    });
	jQuery(".apple_verify_domain").click(function (event) {
		 
        event.preventDefault();
		verify_apple_domain();
    });
	if(jQuery('.applePayEnable:checked').length){
		if (jQuery('.applePayEnable').is(':checked')) {
			 
            // Show the Verify domain button if checked
            jQuery('.apple_verify_domain').show();
			verify_apple_domain();
        } else {
            // Hide the Verify domain button if unchecked
            jQuery('.apple_verify_domain').hide();
        }
	}
	jQuery('.applePayEnable').on('change', function() {
        // Check if the checkbox is checked
        if (jQuery(this).is(':checked')) {
            // Show the Verify domain button if checked
            jQuery('.apple_verify_domain').show();
        } else {
            // Hide the Verify domain button if unchecked
            jQuery('.apple_verify_domain').hide();
        }
    });
	jQuery(".apple_verify_domain").click(function (event) {
		 
        event.preventDefault();
		verify_apple_domain();
    });
    	
}); 