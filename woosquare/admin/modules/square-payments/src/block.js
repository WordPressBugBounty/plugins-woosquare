
import { __ } from '@wordpress/i18n';
// Shipping Block Component
export const GiftCardBlock = () => {
    return (
    <>
    <div id="woosquare_giftcard_div">
        <div class="" id="sq_amount_result"></div>
        <div class="add_woosquare_gift_card_form">

            <h4>Have a gift card?</h4>

            <div id="wc_woosquare_gc_cart_redeem_form">
                <div class="woowoosquare_gift_card_coupen_code_notices"></div>
                <label for="sq-gift-card-coupen">Enter your gift card code</label>
                
                <div id="sq-gift-card-coupen"></div>
                
                
                <br></br>
                <br></br>
                <button type="button" name="woosquare_get_cart_redeem_send"
                id="woosquare_get_cart_redeem_send">Apply</button>
            </div>
        </div>
        <br></br>
    </div>
    </>
    );
};
