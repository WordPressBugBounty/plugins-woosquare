/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ }),

/***/ "@woocommerce/blocks-registry":
/*!******************************************!*\
  !*** external ["wc","wcBlocksRegistry"] ***!
  \******************************************/
/***/ ((module) => {

module.exports = window["wc"]["wcBlocksRegistry"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

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
/* harmony export */   SquareGooglePay: () => (/* binding */ SquareGooglePay)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @woocommerce/blocks-registry */ "@woocommerce/blocks-registry");
/* harmony import */ var _woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);


// import { registerExpressPaymentMethod } from '@woocommerce/blocks-registry';



const SquareCreditCard = props => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "payment-form"
  }, square_index_params.description, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "card-initialization",
    class: "method-initialization"
  }, "Initializing..."), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "card-container"
  }));
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
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    const unsubscribe = onPaymentSetup(async () => {
      // Here we can do any processing we need, and then emit a response.
      // For example, we might validate a custom field, or perform an AJAX request, and then emit a response indicating it is valid or not.
      const square_nonce = jQuery('.square-nonce').val();
      const buyerVerification_token = jQuery('.buyerVerification-token').val();
      const square_pay_nonce = square_index_params.square_pay_nonce;
      const customDataIsValid = !!square_nonce.length;
      if (customDataIsValid) {
        return {
          type: emitResponse.responseTypes.SUCCESS,
          meta: {
            paymentMethodData: {
              square_nonce,
              buyerVerification_token,
              square_pay_nonce
            }
          }
        };
      }
console.log(emitResponse);
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
  edit: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, "Hello"),
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
  edit: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, "Hello"),
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
  edit: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, "Hello"),
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
  edit: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, "Hello"),
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
  edit: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, "Hello"),
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
  edit: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, "Hello"),
  canMakePayment: () => true,
  ariaLabel: 'Square CashApp payment method',
  paymentMethodId: wc.wcSettings.getPaymentMethodData(square_index_params.method_name).square_cash_app_id,
  supports: {
    features: undefined
  }
};
(0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__.registerPaymentMethod)(woosquarePaymentMethod);
(0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__.registerPaymentMethod)(woosquareACHPaymentMethod);
(0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__.registerPaymentMethod)(woosquareGooglePaymentMethod);
(0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__.registerPaymentMethod)(woosquareApplePaymentMethod);
(0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__.registerPaymentMethod)(woosquareAfterPaymentMethod);
(0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__.registerPaymentMethod)(woosquareCashAppPaymentMethod);
})();

/******/ })()
;
//# sourceMappingURL=index.js.map