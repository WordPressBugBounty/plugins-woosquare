import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';
import { GiftCardBlock } from './block.js';
import metadata from './block.json';
const settings = square_index_params.woocommerce_square_gift_card_pay_enabled;
if (settings) {
    metadata.parent = [ "woocommerce/checkout-order-summary-block" ];

    registerCheckoutBlock(
        {
            metadata,
            component: GiftCardBlock,
        } 
    );
}