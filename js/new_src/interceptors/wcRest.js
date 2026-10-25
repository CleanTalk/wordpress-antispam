import {apbctLocalStorage} from '../utils/apbctLocalStorage';
import {getNoCookieData} from '../functions/requestData';

/**
 * Insert no_cookies_data to rest request
 * @return {void}
 */
export function catchWCRestRequest() {
    const ctPinDataToRequest = (options, next) => {
        if (
            typeof options !== 'object' ||
            options === null ||
            !options.hasOwnProperty('data') ||
            typeof options.data === 'undefined' ||
            !options.hasOwnProperty('path') ||
            typeof options.path === 'undefined'
        ) {
            return next(options);
        }

        // add to cart (batch uses body not data for request payload)
        if (options.data.hasOwnProperty('requests') &&
            options.data.requests.length > 0 &&
            options.data.requests[0].hasOwnProperty('path') &&
            options.data.requests[0].path === '/wc/store/v1/cart/add-item'
        ) {
            const reqBody = options.data.requests[0].body || (options.data.requests[0].body = {});
            if (
                +ctPublic.bot_detector_enabled &&
                apbctLocalStorage.get('bot_detector_event_token')
            ) {
                reqBody.ct_bot_detector_event_token = localStorage.getItem('bot_detector_event_token');
            } else {
                if (ctPublic.data__cookies_type === 'none') {
                    reqBody.ct_no_cookie_hidden_field = getNoCookieData();
                }
            }
        }

        // checkout
        if (options.path.includes('/wc/store/v1/checkout')) {
            if (
                +ctPublic.bot_detector_enabled &&
                apbctLocalStorage.get('bot_detector_event_token')
            ) {
                options.data.ct_bot_detector_event_token = localStorage.getItem('bot_detector_event_token');
            } else {
                if (ctPublic.data__cookies_type === 'none') {
                    options.data.ct_no_cookie_hidden_field = getNoCookieData();
                }
            }
        }

        return next(options);
    };

    if (window.hasOwnProperty('wp') &&
        window.wp.hasOwnProperty('apiFetch') &&
        typeof window.wp.apiFetch.use === 'function'
    ) {
        window.wp.apiFetch.use(ctPinDataToRequest);
    }
}
