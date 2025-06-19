/**
 * Insert no_cookies_data to rest request
 */
function ctAddWCMiddlewares() {
    const ctPinDataToRequest = (options, next) => {
        if (typeof options !== 'object' || options === null ||
            !options.hasOwnProperty('data') || !options.hasOwnProperty('path')
        ) {
            return next(options);
        }

        // add to cart
        if (options.data.hasOwnProperty('requests') &&
            options.data.requests.length > 0 &&
            options.data.requests[0].hasOwnProperty('path') &&
            options.data.requests[0].path === '/wc/store/v1/cart/add-item'
        ) {
            options.data.requests[0].data.ct_no_cookie_hidden_field = getNoCookieData();
            options.data.requests[0].data.event_token = localStorage.getItem('bot_detector_event_token');
        }

        // checkout
        if (options.path === '/wc/store/v1/checkout') {
            options.data.ct_no_cookie_hidden_field = getNoCookieData();
            options.data.event_token = localStorage.getItem('bot_detector_event_token');
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




