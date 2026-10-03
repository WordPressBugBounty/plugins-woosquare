
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

function cons(res)
{
}
        
     jQuery.noConflict();
    jQuery(document).ready(
        function ($) {
         

            hide_unhide_squ_sandbox();
            jQuery("#woocommerce_square_enable_sandbox:checkbox").change(
                function () {
                    hide_unhide_squ_sandbox();
                }
            );

            jQuery('.enable_plugin').click(
                function (e) { 
                    e.preventDefault();
                    
                    var isChecked = jQuery(this).is(':checked');
                    var pluginId = jQuery(this).attr('id');
                    
                    console.log('Toggle clicked - Checked:', isChecked);
                    
                    // If checkbox is CHECKED, we want to ENABLE (send 'enab')
                    // If checkbox is UNCHECKED, we want to DISABLE (send 'disab')
                    var status = isChecked ? 'enab' : 'disab';
                    var actionText = isChecked ? 'ENABLE' : 'DISABLE';
                    
                    var data = {
                        action: 'en_plugin',
                        status: status,
                        pluginid: pluginId,
                        nonce: my_ajax_backend_scripts.nonce,
                    };
                    
                    console.log('Sending AJAX - Action:', actionText, 'Status:', status);
                    console.log('Data:', data);
                    
                    jQuery.post(
                        my_ajax_backend_scripts.ajax_url, 
                        data, 
                        function (response) {
                            console.log('Raw Response:', response);
                            
                            // Try to parse JSON (response might have HTML debug output)
                            try {
                                // Extract JSON from response if HTML is present
                                var jsonStart = response.indexOf('{');
                                var jsonEnd = response.lastIndexOf('}') + 1;
                                
                                var parsedResponse;
                                if (jsonStart !== -1 && jsonEnd > jsonStart) {
                                    var jsonString = response.substring(jsonStart, jsonEnd);
                                    parsedResponse = JSON.parse(jsonString);
                                } else {
                                    parsedResponse = JSON.parse(response);
                                }
                                
                                console.log('Parsed Response:', parsedResponse);
                                
                                if(parsedResponse.status) {
                                    console.log('Success! Reloading page...');
                                    window.location.replace(window.location.href);
                                } else {
                                    console.error('Failed:', parsedResponse.msg);
                                    alert('Error: ' + parsedResponse.msg);
                                }
                            } catch(e) {
                                console.error('JSON Parse Error:', e);
                                console.error('Response was:', response);
                                alert('Error parsing response. Check console for details.');
                            }
                        }
                    ).fail(function(xhr, status, error) {
                        console.error('AJAX Failed:', status, error);
                        console.error('Response:', xhr.responseText);
                        alert('AJAX Error: ' + error);
                    }); 
                }
            );
        
        
            jQuery('.gpayred').click(
                function (e) {
                    e.preventDefault();
                    window.location.replace(jQuery(this).parent().attr('href'));
            
                }
            );
        
        }
    );
     
     
    function hide_unhide_squ_sandbox()
    {
        var ischecked= jQuery("#woocommerce_square_enable_sandbox:checkbox").is(':checked');
        if(ischecked) {
            jQuery("#sandbox_application_id").parents("tr").fadeIn();
            jQuery("#woocommerce_square_sandbox_application_id").parents("tr").fadeIn();
            jQuery("#sandbox_access_token").parents("tr").fadeIn();
            jQuery("#woocommerce_square_sandbox_access_token").parents("tr").fadeIn();
            jQuery("#sandbox_location_id").parents("tr").fadeIn();
            jQuery("#woocommerce_square_sandbox_location_id").parents("tr").fadeIn();
            jQuery(".squ-sandbox-description").fadeIn();
            jQuery("#woocommerce_square_api_details").fadeIn();
        } else {
            jQuery("#sandbox_application_id").parents("tr").fadeOut();
            jQuery("#woocommerce_square_sandbox_application_id").parents("tr").fadeOut();
            jQuery("#sandbox_access_token").parents("tr").fadeOut();
            jQuery("#woocommerce_square_sandbox_access_token").parents("tr").fadeOut();
            jQuery("#sandbox_location_id").parents("tr").fadeOut();
            jQuery("#woocommerce_square_sandbox_location_id").parents("tr").fadeOut();
            jQuery(".squ-sandbox-description").fadeOut();
            jQuery("#woocommerce_square_api_details").fadeOut();
        }

    }

    
     jQuery.noConflict();
    jQuery(document).ready(
        function ($) {
    
            jQuery('.enable_mode_check').click(
                function (e) {
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
    
                    jQuery.post(
                        my_ajax_backend_scripts.ajax_url, data, function (response) {
                            var response = JSON.parse(response);      
                            if(response.status) {
                                window.location.replace(window.location.href);
                            }
                        }
                    ); 
   
                }
            );
   
        }
    );