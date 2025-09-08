(function ( $ ) {
    'use strict';

    /**
     * All of the code for your public-facing JavaScript source
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
     
     
     
    jQuery(window).on('load', function() {
            function hideunhide(pgetway)
            {
                if(pgetway == 'square_apple_pay' 
                    || pgetway == 'square_google_pay' 
                    || pgetway == 'square_cash_app_pay' 
                    || pgetway == 'square_after_pay' 
                    || pgetway == 'square_ach_payment'
                ) {
                    jQuery('#place_order').css("display", "none");
                    jQuery('#place_order').addClass('placeordernone');
                } else {
                    jQuery('#place_order').removeClass('placeordernone');
                }
            }
            function explode()
            {
                const pgetway = jQuery('.woocommerce-checkout-payment .input-radio:checked').val()
            
                hideunhide(pgetway);
                jQuery(".woocommerce-checkout-payment").on(
                    'change', '.input-radio', function () {
                        const pgetway = jQuery('.woocommerce-checkout-payment .input-radio:checked').val()
                        hideunhide(pgetway);
                    }
                );
            }
        
            setTimeout(explode, 600);
        }
    );

})(jQuery);
