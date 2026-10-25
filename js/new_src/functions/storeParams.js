import {apbctLocalStorage} from '../utils/apbctLocalStorage';
import {sendAJAX, sendREST} from './ajaxHandlers';
import {ctNoCookieAttachHiddenFieldsToForms} from './noCookieHiddenField';

const PARAMS_TO_FORCE_ALT = [
    'ct_sfw_pass_key',
    'ct_sfw_passed',
    'apbct_antibot',
    'apbct_anticrawler_passed',
    'apbct_bot_detector_exist',
    'apbct_antiflood_passed',
    'apbct_email_encoder_passed',
];

const PARAMS_SKIP_ALT = [
    'ct_pointer_data'
];

/**
 * @param {array} params
 */
export function storeParams(params) {
    if (ctPublicFunctions.data__cookies_type === 'alternative') {
        return ctSetAltCookies(params);
    }
    params.forEach( function(param) {
        const paramName = param[0];
        const paramValue = param[1];

        if (PARAMS_TO_FORCE_ALT.includes(paramName)) {
            ctSetAltCookies([param]);
            return;
        }

        if (ctPublicFunctions.data__cookies_type === 'native') {
            ctSetCookie(paramName, paramValue);
        }

        if (ctPublicFunctions.data__cookies_type === 'none') {
            ctSetNoCookie(paramName, paramValue);
        }
    });
}

export function ctSetCookie(name, value, expires = '') {
    const _expires = typeof expires !== 'undefined' ? 'expires=' + expires + '; ' : '';
    const _secure = location.protocol === 'https:' ? '; secure' : '';
    let _cookieName = name;
    if ( ctPublicFunctions.cookiePrefix ) {
        _cookieName = ctPublicFunctions.cookiePrefix + name;
    }
    document.cookie =
        _cookieName + '=' + encodeURIComponent(value) + '; ' +
        _expires +
        'path=/; ' +
        'samesite=lax' +
        _secure;
}

/**
 * @param {array} params
 */
export function ctSetAltCookies(params) {
    let filteredParams = params.filter(([name]) => !PARAMS_SKIP_ALT.includes(name));

    // if cookies is array, convert it to object
    if (Array.isArray(filteredParams) && filteredParams[0] && filteredParams[0][0] === 'apbct_bot_detector_exist') {
        filteredParams = {apbct_bot_detector_exist: filteredParams[0][1]};
    }
    // Only try to parse if cookies is a string (JSON)
    if (typeof filteredParams === 'string') {
        try {
            filteredParams = JSON.parse(filteredParams);
        } catch (e) {
            console.log('APBCT ERROR: JSON parse error:' + e);
            return;
        }
    }

    const callback = params && params.callback || null;
    const onErrorCallback = params && params.onErrorCallback || null;

    if ( params && params.forceAltCookies ) {
        filteredParams.apbct_force_alt_cookies = true;
    }

    // Using REST API handler
    if ( ctPublicFunctions.data__ajax_type === 'rest' ) {
        // fix for url encoded cookie apbct_pixel_url on REST route
        if (typeof filteredParams.apbct_pixel_url === 'string' &&
            filteredParams.apbct_pixel_url.indexOf('%3A') !== -1
        ) {
            filteredParams.apbct_pixel_url = decodeURIComponent(filteredParams.apbct_pixel_url);
        }
        sendREST(
            'alt_sessions',
            {
                method: 'POST',
                data: {cookies: filteredParams},
                callback: callback,
                onErrorCallback: onErrorCallback,
            },
        );

        // Using AJAX request and handler
    } else if ( ctPublicFunctions.data__ajax_type === 'admin_ajax' ) {
        sendAJAX(
            {
                action: 'apbct_alt_session__save__AJAX',
                cookies: filteredParams,
            },
            {
                notJson: 1,
                callback: callback,
                onErrorCallback: onErrorCallback,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-Robots-Tag', 'noindex, nofollow');
                },
            },
        );
    }
}

export function ctSetNoCookie(name, value) {
    apbctLocalStorage.set(name, value);
    ctNoCookieAttachHiddenFieldsToForms();
}
