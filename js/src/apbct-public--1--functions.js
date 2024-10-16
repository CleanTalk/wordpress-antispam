/**
 * @param {object|array|string} cookies
 * @param {object|array|string} value
 * @param {string|number} expires
 */
// eslint-disable-next-line no-unused-vars,require-jsdoc
function ctSetCookie( cookies, value, expires ) {
    let listOfCookieNamesToForceAlt = [
        'ct_sfw_pass_key',
        'ct_sfw_passed',
        'wordpress_apbct_antibot',
        'apbct_anticrawler_passed',
        'apbct_antiflood_passed',
        'apbct_email_encoder_passed',
    ];

    let skipAlt = false;

    if ( typeof cookies === 'string') {
        skipAlt = cookies === 'ct_pointer_data';
        if ( typeof value === 'string' || typeof value === 'number' ) {
            cookies = [[cookies, value, expires]];
        }
    }

    // Cookies disabled
    if ( ctPublicFunctions.data__cookies_type === 'none' ) {
        let forcedAltCookiesSet = [];
        cookies.forEach( function(item) {
            if (listOfCookieNamesToForceAlt.indexOf(item[0]) !== -1) {
                forcedAltCookiesSet.push(item);
            } else {
                apbctLocalStorage.set(item[0], encodeURIComponent(item[1]));
            }
        });
        // if cookies from list found use alt cookies for this selection set
        if ( forcedAltCookiesSet.length > 0 ) {
            ctSetAlternativeCookie(forcedAltCookiesSet);
        }

        // If problem integration forms detected use alt cookies for whole cookies set
        if ( ctPublic.force_alt_cookies && !skipAlt) {
            // do it just once
            ctSetAlternativeCookie(cookies, {forceAltCookies: true});
        } else {
            ctNoCookieAttachHiddenFieldsToForms();
        }

        // Using traditional cookies
    } else if ( ctPublicFunctions.data__cookies_type === 'native' ) {
        // If problem integration forms detected use alt cookies for whole cookies set
        if ( ctPublic.force_alt_cookies && !skipAlt) {
            // do it just once
            ctSetAlternativeCookie(cookies, {forceAltCookies: true});
        }
        cookies.forEach( function(item) {
            const _expires = typeof item[2] !== 'undefined' ? 'expires=' + expires + '; ' : '';
            let ctSecure = location.protocol === 'https:' ? '; secure' : '';
            document.cookie = ctPublicFunctions.cookiePrefix +
                item[0] +
                '=' +
                encodeURIComponent(item[1]) +
                '; ' +
                _expires +
                'path=/; samesite=lax' +
                ctSecure;
        });

        // Using alternative cookies
    } else if ( ctPublicFunctions.data__cookies_type === 'alternative' && !skipAlt ) {
        ctSetAlternativeCookie(cookies);
    }
}

// eslint-disable-next-line no-unused-vars,require-jsdoc
function ctDetectForcedAltCookiesForms() {
    let ninjaFormsSign = document.querySelectorAll('#tmpl-nf-layout').length > 0;
    let elementorUltimateAddonsRegister = document.querySelectorAll('.uael-registration-form-wrapper').length > 0;
    let smartFormsSign = document.querySelectorAll('script[id*="smart-forms"]').length > 0;
    let jetpackCommentsForm = document.querySelectorAll('iframe[name="jetpack_remote_comment"]').length > 0;
    let cwginstockForm = document.querySelectorAll('.cwginstock-subscribe-form').length > 0;
    let userRegistrationProForm = document.querySelectorAll('div[id^="user-registration-form"]').length > 0;
    let etPbDiviSubscriptionForm = document.querySelectorAll('div[class^="et_pb_newsletter_form"]').length > 0;
    let fluentBookingApp = document.querySelectorAll('div[class^="fluent_booking_app"]').length > 0;
    let bloomPopup = document.querySelectorAll('div[class^="et_bloom_form_container"]').length > 0;
    let pafeFormsFormElementor = document.querySelectorAll('div[class*="pafe-form"]').length > 0;
    let otterForm = document.querySelectorAll('div [class*="otter-form"]').length > 0;
    ctPublic.force_alt_cookies = smartFormsSign ||
        ninjaFormsSign ||
        jetpackCommentsForm ||
        elementorUltimateAddonsRegister ||
        cwginstockForm ||
        userRegistrationProForm ||
        etPbDiviSubscriptionForm ||
        fluentBookingApp ||
        pafeFormsFormElementor ||
        bloomPopup ||
        otterForm;

    setTimeout(function() {
        if (!ctPublic.force_alt_cookies) {
            let bookingPress = document.querySelectorAll('main[id^="bookingpress_booking_form"]').length > 0;
            ctPublic.force_alt_cookies = bookingPress;
        }
    }, 1000);
}

// eslint-disable-next-line require-jsdoc
function ctSetAlternativeCookie(cookies, params) {
    if (typeof (getJavascriptClientData) === 'function' ) {
        // reprocess already gained cookies data
        if (Array.isArray(cookies)) {
            cookies = getJavascriptClientData(cookies);
        }
    } else {
        console.log('APBCT ERROR: getJavascriptClientData() is not loaded');
    }

    try {
        cookies = JSON.parse(cookies);
    } catch (e) {
        console.log('APBCT ERROR: JSON parse error:' + e);
        return;
    }

    if (!cookies.apbct_site_referer) {
        cookies.apbct_site_referer = location.href;
    }

    const callback = params && params.callback || null;
    const onErrorCallback = params && params.onErrorCallback || null;

    if ( params && params.forceAltCookies ) {
        cookies.apbct_force_alt_cookies = true;
    }

    // Using REST API handler
    if ( ctPublicFunctions.data__ajax_type === 'rest' ) {
        // fix for url encoded cookie apbct_pixel_url on REST route
        if (typeof cookies.apbct_pixel_url === 'string' &&
            cookies.apbct_pixel_url.indexOf('%3A') !== -1
        ) {
            cookies.apbct_pixel_url = decodeURIComponent(cookies.apbct_pixel_url);
        }
        apbct_public_sendREST(
            'alt_sessions',
            {
                method: 'POST',
                data: {cookies: cookies},
                callback: callback,
                onErrorCallback: onErrorCallback,
            },
        );

        // Using AJAX request and handler
    } else if ( ctPublicFunctions.data__ajax_type === 'admin_ajax' ) {
        apbct_public_sendAJAX(
            {
                action: 'apbct_alt_session__save__AJAX',
                cookies: cookies,
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

/**
 * Get cookie by name
 * @param name
 * @return {string|undefined}
 */
// eslint-disable-next-line require-jsdoc,no-unused-vars
function ctGetCookie(name) {
    let matches = document.cookie.match(new RegExp(
        '(?:^|; )' + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)',
    ));
    return matches ? decodeURIComponent(matches[1]) : undefined;
}

// eslint-disable-next-line require-jsdoc,no-unused-vars
function ctDeleteCookie(cookieName) {
    // Cookies disabled
    if ( ctPublicFunctions.data__cookies_type === 'none' ) {
        return;

    // Using traditional cookies
    } else if ( ctPublicFunctions.data__cookies_type === 'native' ) {
        let ctSecure = location.protocol === 'https:' ? '; secure' : '';
        document.cookie = cookieName + '=""; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; samesite=lax' + ctSecure;

    // Using alternative cookies
    } else if ( ctPublicFunctions.data__cookies_type === 'alternative' ) {
        // @ToDo implement this logic
    }
}

// eslint-disable-next-line require-jsdoc,camelcase
function apbct_public_sendAJAX(data, params, obj) {
    // Default params
    let _params = [];
    _params['callback'] = params.callback || null;
    _params['onErrorCallback'] = params.onErrorCallback || null;
    _params['callback_context'] = params.callback_context || null;
    _params['callback_params'] = params.callback_params || null;
    _params['async'] = params.async || true;
    _params['notJson'] = params.notJson || null;
    _params['responseType']= params.notJson ? 'text' : 'json';
    _params['timeout'] = params.timeout || 15000;
    _params['obj'] = obj || null;
    _params['button'] = params.button || null;
    _params['spinner'] = params.spinner || null;
    _params['progressbar'] = params.progressbar || null;
    _params['silent'] = params.silent || null;
    _params['no_nonce'] = params.no_nonce || null;
    _params['data'] = data;
    _params['url'] = ctPublicFunctions._ajax_url;
    const nonce = selectActualNonce();

    if (typeof (data) === 'string') {
        if ( ! _params['no_nonce'] ) {
            _params['data'] = _params['data'] + '&_ajax_nonce=' + nonce;
        }
        _params['data'] = _params['data'] + '&no_cache=' + Math.random();
    } else {
        if ( ! _params['no_nonce'] ) {
            _params['data']._ajax_nonce = nonce;
        }
        _params['data'].no_cache = Math.random();
    }

    new ApbctCore().ajax(_params);
}

// eslint-disable-next-line require-jsdoc,camelcase
function apbct_public_sendREST( route, params ) {
    let _params = [];
    _params['route'] = route;
    _params['callback'] = params.callback || null;
    _params['onErrorCallback'] = params.onErrorCallback || null;
    _params['data'] = params.data || [];
    _params['method'] = params.method || 'POST';

    new ApbctCore().rest(_params);
}

/**
 * Generate unique ID
 * @return {string}
 */
// eslint-disable-next-line no-unused-vars,require-jsdoc
function apbctGenerateUniqueID() {
    return Math.random().toString(36).replace(/[^a-z]+/g, '').substr(2, 10);
}

let apbctLocalStorage = {
    get: function(key, property) {
        if ( typeof property === 'undefined' ) {
            property = 'value';
        }
        const storageValue = localStorage.getItem(key);
        if ( storageValue !== null ) {
            try {
                const json = JSON.parse(storageValue);
                if ( json.hasOwnProperty(property) ) {
                    try {
                        // if property can be parsed as JSON - do it
                        return JSON.parse( json[property] );
                    } catch (e) {
                        // if not - return string of value
                        return json[property].toString();
                    }
                } else {
                    return json;
                }
            } catch (e) {
                return storageValue;
            }
        }
        return false;
    },
    set: function(key, value, isJson = true) {
        if (isJson) {
            let objToSave = {'value': JSON.stringify(value), 'timestamp': Math.floor(new Date().getTime() / 1000)};
            localStorage.setItem(key, JSON.stringify(objToSave));
        } else {
            localStorage.setItem(key, value);
        }
    },
    isAlive: function(key, maxLifetime) {
        if ( typeof maxLifetime === 'undefined' ) {
            maxLifetime = 86400;
        }
        const keyTimestamp = this.get(key, 'timestamp');
        return keyTimestamp + maxLifetime > Math.floor(new Date().getTime() / 1000);
    },
    isSet: function(key) {
        return localStorage.getItem(key) !== null;
    },
    delete: function(key) {
        localStorage.removeItem(key);
    },
    getCleanTalkData: function() {
        let data = {};
        for (let i=0; i<localStorage.length; i++) {
            let key = localStorage.key(i);
            if (key.indexOf('ct_') !==-1 || key.indexOf('apbct_') !==-1) {
                data[key.toString()] = apbctLocalStorage.get(key);
            }
        }
        return data;
    },

};

let apbctSessionStorage = {
    get: function(key, property) {
        if ( typeof property === 'undefined' ) {
            property = 'value';
        }
        const storageValue = sessionStorage.getItem(key);
        if ( storageValue !== null ) {
            try {
                const json = JSON.parse(storageValue);
                return json.hasOwnProperty(property) ? JSON.parse(json[property]) : json;
            } catch (e) {
                return storageValue;
            }
        }
        return false;
    },
    set: function(key, value, isJson = true) {
        if (isJson) {
            let objToSave = {'value': JSON.stringify(value), 'timestamp': Math.floor(new Date().getTime() / 1000)};
            sessionStorage.setItem(key, JSON.stringify(objToSave));
        } else {
            sessionStorage.setItem(key, value);
        }
    },
    isSet: function(key) {
        return sessionStorage.getItem(key) !== null;
    },
    delete: function(key) {
        sessionStorage.removeItem(key);
    },
    getCleanTalkData: function() {
        let data = {};
        for (let i=0; i<sessionStorage.length; i++) {
            let key = sessionStorage.key(i);
            if (key.indexOf('ct_') !==-1 || key.indexOf('apbct_') !==-1) {
                data[key.toString()] = apbctSessionStorage.get(key);
            }
        }
        return data;
    },
};

/**
 * Handler for -webkit based browser that listen for a custom
 * animation create using the :pseudo-selector in the stylesheet.
 * Works with Chrome, Safari
 *
 * @param {AnimationEvent} event
 */
// eslint-disable-next-line no-unused-vars,require-jsdoc
function apbctOnAnimationStart(event) {
    ('onautofillstart' === event.animationName) ?
        apbctAutocomplete(event.target) : apbctCancelAutocomplete(event.target);
}

/**
 * Handler for non-webkit based browser that listen for input
 * event to trigger the autocomplete-cancel process.
 * Works with Firefox, Edge, IE11
 *
 * @param {InputEvent} event
 */
// eslint-disable-next-line no-unused-vars,require-jsdoc
function apbctOnInput(event) {
    ('insertReplacementText' === event.inputType || !('data' in event)) ?
        apbctAutocomplete(event.target) : apbctCancelAutocomplete(event.target);
}

/**
 * Manage an input element when its value is autocompleted
 * by the browser in the following steps:
 * - add [autocompleted] attribute from event.target
 * - create 'onautocomplete' cancelable CustomEvent
 * - dispatch the Event
 *
 * @param {HtmlInputElement} element
 */
function apbctAutocomplete(element) {
    if (element.hasAttribute('autocompleted')) return;
    element.setAttribute('autocompleted', '');

    let event = new window.CustomEvent('onautocomplete', {
        bubbles: true, cancelable: true, detail: null,
    });

    // no autofill if preventDefault is called
    if (!element.dispatchEvent(event)) {
        element.value = '';
    }
}

/**
 * Manage an input element when its autocompleted value is
 * removed by the browser in the following steps:
 * - remove [autocompleted] attribute from event.target
 * - create 'onautocomplete' non-cancelable CustomEvent
 * - dispatch the Event
 *
 * @param {HtmlInputElement} element
 */
function apbctCancelAutocomplete(element) {
    if (!element.hasAttribute('autocompleted')) return;
    element.removeAttribute('autocompleted');

    // dispatch event
    element.dispatchEvent(new window.CustomEvent('onautocomplete', {
        bubbles: true, cancelable: false, detail: null,
    }));
}
