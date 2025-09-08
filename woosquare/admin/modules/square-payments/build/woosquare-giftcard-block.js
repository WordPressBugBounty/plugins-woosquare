/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./node_modules/@wordpress/icons/build-module/icon/index.js":
/*!******************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/icon/index.js ***!
  \******************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/**
 * WordPress dependencies
 */


/** @typedef {{icon: JSX.Element, size?: number} & import('@wordpress/primitives').SVGProps} IconProps */

/**
 * Return an SVG icon.
 *
 * @param {IconProps}                                 props icon is the SVG component to render
 *                                                          size is a number specifiying the icon size in pixels
 *                                                          Other props will be passed to wrapped SVG component
 * @param {import('react').ForwardedRef<HTMLElement>} ref   The forwarded ref to the SVG element.
 *
 * @return {JSX.Element}  Icon component
 */
function Icon({
  icon,
  size = 24,
  ...props
}, ref) {
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.cloneElement)(icon, {
    width: size,
    height: size,
    ...props,
    ref
  });
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ((0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.forwardRef)(Icon));
//# sourceMappingURL=index.js.map

/***/ }),

/***/ "./node_modules/@wordpress/icons/build-module/library/box.js":
/*!*******************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/box.js ***!
  \*******************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_1__);

/**
 * WordPress dependencies
 */

const box = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_1__.SVG, {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 24 24"
}, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_1__.Path, {
  fillRule: "evenodd",
  d: "M5 5.5h14a.5.5 0 01.5.5v1.5a.5.5 0 01-.5.5H5a.5.5 0 01-.5-.5V6a.5.5 0 01.5-.5zM4 9.232A2 2 0 013 7.5V6a2 2 0 012-2h14a2 2 0 012 2v1.5a2 2 0 01-1 1.732V18a2 2 0 01-2 2H6a2 2 0 01-2-2V9.232zm1.5.268V18a.5.5 0 00.5.5h12a.5.5 0 00.5-.5V9.5h-13z",
  clipRule: "evenodd"
}));
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (box);
//# sourceMappingURL=box.js.map

/***/ }),

/***/ "./src/block.js":
/*!**********************!*\
  !*** ./src/block.js ***!
  \**********************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   GiftCardBlock: () => (/* binding */ GiftCardBlock)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);


// Shipping Block Component
const GiftCardBlock = () => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "woosquare_giftcard_div"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    class: "",
    id: "sq_amount_result"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    class: "add_woosquare_gift_card_form"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h4", null, "Have a gift card?"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "wc_woosquare_gc_cart_redeem_form"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    class: "woowoosquare_gift_card_coupen_code_notices"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    for: "sq-gift-card-coupen"
  }, "Enter your gift card code"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "sq-gift-card-coupen"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("br", null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("br", null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
    type: "button",
    name: "woosquare_get_cart_redeem_send",
    id: "woosquare_get_cart_redeem_send"
  }, "Apply"))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("br", null)));
};

/***/ }),

/***/ "./src/edit.js":
/*!*********************!*\
  !*** ./src/edit.js ***!
  \*********************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Edit: () => (/* binding */ Edit),
/* harmony export */   Save: () => (/* binding */ Save)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__);




const Edit = () => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    class: "add_woosquare_gift_card_form"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h4", null, "Have a Square gift card?"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "wc_woosquare_gc_cart_redeem_form"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    class: "woowoosquare_gift_card_coupen_code_notices"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    for: "sq-gift-card-coupen"
  }, "Enter your square gift card code"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "sq-gift-card-coupen"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("br", null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("br", null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
    type: "button",
    name: "woosquare_get_cart_redeem_send",
    id: "woosquare_get_cart_redeem_send"
  }, "Apply")))));
};
const Save = () => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ..._wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__.useBlockProps.save()
  });
};

/***/ }),

/***/ "./src/frontend.js":
/*!*************************!*\
  !*** ./src/frontend.js ***!
  \*************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @woocommerce/blocks-checkout */ "@woocommerce/blocks-checkout");
/* harmony import */ var _woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _block_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./block.js */ "./src/block.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./block.json */ "./src/block.json");


const settings = square_index_params.woocommerce_square_gift_card_pay_enabled;
if (settings) {
  _block_json__WEBPACK_IMPORTED_MODULE_2__.parent = ["woocommerce/checkout-order-summary-block"];
  (0,_woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_0__.registerCheckoutBlock)({
    metadata: _block_json__WEBPACK_IMPORTED_MODULE_2__,
    component: _block_js__WEBPACK_IMPORTED_MODULE_1__.GiftCardBlock
  });
}

/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ }),

/***/ "@woocommerce/blocks-checkout":
/*!****************************************!*\
  !*** external ["wc","blocksCheckout"] ***!
  \****************************************/
/***/ ((module) => {

module.exports = window["wc"]["blocksCheckout"];

/***/ }),

/***/ "@woocommerce/blocks-registry":
/*!******************************************!*\
  !*** external ["wc","wcBlocksRegistry"] ***!
  \******************************************/
/***/ ((module) => {

module.exports = window["wc"]["wcBlocksRegistry"];

/***/ }),

/***/ "@wordpress/block-editor":
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
/***/ ((module) => {

module.exports = window["wp"]["blockEditor"];

/***/ }),

/***/ "@wordpress/blocks":
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
/***/ ((module) => {

module.exports = window["wp"]["blocks"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "@wordpress/primitives":
/*!************************************!*\
  !*** external ["wp","primitives"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["primitives"];

/***/ }),

/***/ "./src/block.json":
/*!************************!*\
  !*** ./src/block.json ***!
  \************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"apiVersion":2,"name":"woocommerce/woosquare-giftcard-block","version":"1.0.0","title":"Woosquare GiftCard","category":"media","description":"Adds a select field to let the shopper choose alternative shipping instructions.","supports":{"html":false,"align":false,"multiple":false,"reusable":false},"parent":["woocommerce/cart-order-summary-block"],"attributes":{"lock":{"type":"object","default":{"remove":true,"move":false}},"text":{"type":"string","default":""}},"textdomain":"woosquare-giftcard"}');

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   SquareACH: () => (/* binding */ SquareACH),
/* harmony export */   SquareAfterPay: () => (/* binding */ SquareAfterPay),
/* harmony export */   SquareApplePay: () => (/* binding */ SquareApplePay),
/* harmony export */   SquareCashApp: () => (/* binding */ SquareCashApp),
/* harmony export */   SquareCreditCard: () => (/* binding */ SquareCreditCard),
/* harmony export */   SquareGooglePay: () => (/* binding */ SquareGooglePay),
/* harmony export */   SquarePOS: () => (/* binding */ SquarePOS)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @woocommerce/blocks-registry */ "@woocommerce/blocks-registry");
/* harmony import */ var _woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/icon/index.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/box.js");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./edit */ "./src/edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./block.json */ "./src/block.json");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _frontend__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./frontend */ "./src/frontend.js");









const settings = square_index_params.woocommerce_square_gift_card_pay_enabled;
if (settings) {
  (0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_2__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_4__, {
    icon: {
      src: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_icons__WEBPACK_IMPORTED_MODULE_7__["default"], {
        icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_8__["default"]
      })
    },
    edit: _edit__WEBPACK_IMPORTED_MODULE_3__.Edit,
    save: _edit__WEBPACK_IMPORTED_MODULE_3__.Save
  });
}
const SquareCreditCard = props => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    dangerouslySetInnerHTML: {
      __html: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).saved_cards
    }
  });
};
const SquareACH = props => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "ach-payment-form"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "ach-initialization",
    class: "method-initialization"
  }, "Initializing..."), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    class: "ach-button-div"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("input", {
    type: "hidden",
    id: "card_nonce",
    name: "card_nonce"
  }));
};
const SquareGooglePay = props => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "google-payment-form"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "googlepay-initialization",
    class: "method-initialization"
  }, "Initializing..."), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "google-pay-button"
  }));
};
const SquareApplePay = props => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "apple-payment-form"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "apple-pay-button"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    id: "browser_support_msg"
  }));
};
const SquareAfterPay = props => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "payment-form"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "afterpay-initialization",
    class: "method-initialization"
  }, "Initializing..."), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "afterpay-button"
  }));
};
const SquareCashApp = props => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "payment-form"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "cashapp-initialization",
    class: "method-initialization"
  }, "Initializing..."), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "cash-app-pay"
  }));
};
const SquarePOS = props => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    dangerouslySetInnerHTML: {
      __html: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).terminal_button
    }
  });
};
const Content = ({
  RenderedComponent,
  ...props
}) => {
  const {
    eventRegistration,
    emitResponse
  } = props;
  const {
    onPaymentSetup
  } = eventRegistration;
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_5__.useEffect)(() => {
    const unsubscribe = onPaymentSetup(async () => {
      // Here we can do any processing we need, and then emit a response.
      // For example, we might validate a custom field, or perform an AJAX request, and then emit a response indicating it is valid or not.
      const square_nonce = jQuery('.square-nonce').val();
      const square_customerId = jQuery('.square-customerId').val();
      const term_checkout_id = jQuery('.term_checkout_id').val();
      const saved_cards = jQuery('#saved_cards').val();
      const buyerVerification_token = jQuery('.buyerVerification-token').val();
      const funnel_order = jQuery('.funnel_order').val();
      const _wcf_flow_id = jQuery('._wcf_flow_id').val();
      const _wcf_checkout_id = jQuery('._wcf_checkout_id').val();
      const square_pay_nonce = square_index_params.square_pay_nonce;
      const customDataIsValid = square_nonce ? !!square_nonce.length : false;
      const customTerminalDataIsValid = term_checkout_id ? !!term_checkout_id.length : false;
      if (customDataIsValid || customTerminalDataIsValid) {
        return {
          type: emitResponse.responseTypes.SUCCESS,
          meta: {
            paymentMethodData: {
              square_nonce,
              square_customerId,
              saved_cards,
              buyerVerification_token,
              square_pay_nonce,
              funnel_order,
              _wcf_checkout_id,
              _wcf_flow_id,
              term_checkout_id
            }
          }
        };
      }
      return {
        type: emitResponse.responseTypes.ERROR,
        message: 'There was an error'
      };
    });
    // Unsubscribes when this component is unmounted.
    return () => {
      unsubscribe();
    };
  }, [emitResponse.responseTypes.ERROR, emitResponse.responseTypes.SUCCESS, onPaymentSetup]);
  // return decodeEntities( settings.description || '' );
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(RenderedComponent, {
    square: SquareCreditCard,
    ...props
  });
};
// export default MyPaymentForm;
const woosquarePaymentMethod = {
  name: square_index_params.method_name,
  label: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).method_title,
  content: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Content, {
    RenderedComponent: SquareCreditCard
  }),
  edit: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null),
  canMakePayment: () => true,
  ariaLabel: 'Square Credit Card payment method',
  paymentMethodId: square_index_params.method_name,
  supports: {
    features: undefined
  }
};
const woosquareGooglePaymentMethod = {
  name: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_google_pay_id,
  label: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).google_method_title,
  content: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Content, {
    RenderedComponent: SquareGooglePay
  }),
  edit: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null),
  canMakePayment: () => true,
  ariaLabel: 'Square Google Pay payment method',
  paymentMethodId: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_google_pay_id,
  supports: {
    features: undefined
  }
};
const woosquareApplePaymentMethod = {
  name: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_apple_pay_id,
  label: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).apple_method_title,
  content: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Content, {
    RenderedComponent: SquareApplePay
  }),
  edit: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null),
  canMakePayment: () => true,
  ariaLabel: 'Square Apple Pay payment method',
  paymentMethodId: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_apple_pay_id,
  supports: {
    features: undefined
  }
};
const woosquareACHPaymentMethod = {
  name: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_ach_pay_id,
  label: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).ach_method_title,
  content: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Content, {
    RenderedComponent: SquareACH
  }),
  edit: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null),
  canMakePayment: () => true,
  ariaLabel: 'Square ACH payment method',
  paymentMethodId: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_ach_pay_id,
  supports: {
    features: undefined
  }
};
const woosquareAfterPaymentMethod = {
  name: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_after_pay_id,
  label: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).afterpay_method_title,
  content: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Content, {
    RenderedComponent: SquareAfterPay
  }),
  edit: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null),
  canMakePayment: () => true,
  ariaLabel: 'Square AfterPay payment method',
  paymentMethodId: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_after_pay_id,
  supports: {
    features: undefined
  }
};
const woosquareCashAppPaymentMethod = {
  name: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_cash_app_id,
  label: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).cashapp_method_title,
  content: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Content, {
    RenderedComponent: SquareCashApp
  }),
  edit: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null),
  canMakePayment: () => true,
  ariaLabel: 'Square CashApp payment method',
  paymentMethodId: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_cash_app_id,
  supports: {
    features: undefined
  }
};
const woosquarePOSPaymentMethod = {
  name: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_pos_id,
  label: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).pos_method_title,
  content: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Content, {
    RenderedComponent: SquarePOS
  }),
  edit: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null),
  canMakePayment: () => true,
  ariaLabel: 'Square POS payment method',
  paymentMethodId: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_pos_id,
  supports: {
    features: undefined
  }
};
(0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__.registerPaymentMethod)(woosquarePaymentMethod);
if (wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_ach_pay_enabled) {
  (0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__.registerPaymentMethod)(woosquareACHPaymentMethod);
}
if (wc.wcSettings.getPaymentMethodData(square_index_params.method_name).google_method_enabled) {
  (0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__.registerPaymentMethod)(woosquareGooglePaymentMethod);
}
if (wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_apple_pay_enabled) {
  (0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__.registerPaymentMethod)(woosquareApplePaymentMethod);
}
if (wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_after_pay_enabled) {
  (0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__.registerPaymentMethod)(woosquareAfterPaymentMethod);
}
if (wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_cash_app_enabled) {
  (0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__.registerPaymentMethod)(woosquareCashAppPaymentMethod);
}
if (wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_pos_enabled) {
  (0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__.registerPaymentMethod)(woosquarePOSPaymentMethod);
}
})();

/******/ })()
;
//# sourceMappingURL=woosquare-giftcard-block.js.map