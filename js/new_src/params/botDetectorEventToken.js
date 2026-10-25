import {storeParams, ctSetAltCookies} from '../functions/storeParams';
import {apbctLocalStorage} from '../utils/apbctLocalStorage';

export const PARAM_NAME = 'ct_bot_detector_event_token';

/**
 * The Bot-Detector generates the event token asynchronously, so the param is stored on the first update event.
 */
export function initBotDetectorEventToken() {
    if (!+ctPublic.bot_detector_enabled) {
        return;
    }

    setTimeout(attachEventTokenToWoocommerceAddToCart, 1000);

    const onTokenUpdated = (event) => {
        const eventToken = event.detail?.eventToken;
        if (!eventToken) {
            return;
        }
        window.removeEventListener('botDetectorEventTokenUpdated', onTokenUpdated);
        storeParams([[PARAM_NAME, eventToken]]);

        // @ToDo remove this block after force_alt_cookies removed
        if (typeof ctPublic.force_alt_cookies !== 'undefined' && ctPublic.force_alt_cookies) {
            ctSetAltCookies({[PARAM_NAME]: eventToken}, {forceAltCookies: true});
        }
    };
    window.addEventListener('botDetectorEventTokenUpdated', onTokenUpdated);
}

/**
 * WooCommerce add-to-cart links are plain GET requests, so the token is appended to the href on click.
 */
function attachEventTokenToWoocommerceAddToCart() {
    if (ctPublic.wc_ajax_add_to_cart) {
        return;
    }

    document.querySelectorAll('a.add_to_cart_button:not(.product_type_variable):not(.wc-interactive)')
        .forEach((el) => {
            el.addEventListener('click', () => {
                const eventToken = apbctLocalStorage.get('bot_detector_event_token');
                if (!eventToken) {
                    return;
                }
                const href = el.getAttribute('href');
                const separator = href.indexOf('?') === -1 ? '?' : '&';
                el.setAttribute('href', href + separator + 'ct_bot_detector_event_token=' + eventToken);
            });
        });
}
