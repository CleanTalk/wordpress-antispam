/**
 * Set init params
 */
// eslint-disable-next-line no-unused-vars,require-jsdoc
function initParams() {
    const ctDate = new Date();
    const headless = navigator.webdriver;
    const screenInfo = (
        typeof ApbctGatheringData !== 'undefined' &&
        typeof ApbctGatheringData.prototype.getScreenInfo === 'function'
    ) ? new ApbctGatheringData().getScreenInfo() : '';
    const initCookies = [
        ['ct_ps_timestamp', Math.floor(new Date().getTime() / 1000)],
        ['ct_fkp_timestamp', '0'],
        ['ct_pointer_data', '0'],
        ['ct_timezone', ctDate.getTimezoneOffset()/60*(-1)],
        ['ct_screen_info', screenInfo],
        ['apbct_headless', headless],
    ];

    apbctLocalStorage.set('ct_ps_timestamp', Math.floor(new Date().getTime() / 1000));
    apbctLocalStorage.set('ct_fkp_timestamp', '0');
    apbctLocalStorage.set('ct_pointer_data', '0');
    apbctLocalStorage.set('ct_timezone', ctDate.getTimezoneOffset()/60*(-1));
    apbctLocalStorage.set('ct_screen_info', screenInfo);
    apbctLocalStorage.set('apbct_headless', headless);

    if ( ctPublic.data__cookies_type !== 'native' ) {
        initCookies.push(['apbct_visible_fields', '0']);
    } else {
        // Delete all visible fields cookies on load the page
        let cookiesArray = document.cookie.split(';');
        if ( cookiesArray.length !== 0 ) {
            for ( let i = 0; i < cookiesArray.length; i++ ) {
                let currentCookie = cookiesArray[i].trim();
                let cookieName = currentCookie.split('=')[0];
                if ( cookieName.indexOf('apbct_visible_fields_') === 0 ) {
                    ctDeleteCookie(cookieName);
                }
            }
        }
    }

    if (+ctPublic.pixel__setting && +ctPublic.pixel__setting !== 3) {
        if (typeof ctIsDrawPixel === 'function' && ctIsDrawPixel()) {
            if (typeof ctGetPixelUrl === 'function') ctGetPixelUrl();
        } else {
            initCookies.push(['apbct_pixel_url', ctPublic.pixel__url]);
        }
    }

    if ( +ctPublic.data__email_check_before_post) {
        initCookies.push(['ct_checked_emails', '0']);
        if (typeof apbct === 'function') apbct('input[type = "email"], #email').on('blur', checkEmail);
    }

    if ( +ctPublic.data__email_check_exist_post) {
        initCookies.push(['ct_checked_emails_exist', '0']);
        if (typeof apbct === 'function') {
            apbct('.comment-form input[name = "email"], input#email').on('blur', checkEmailExist);
            apbct('.frm-fluent-form input[name = "email"], input#email').on('blur', checkEmailExist);
            apbct('#registerform input[name = "user_email"]').on('blur', checkEmailExist);
            apbct('form.wc-block-checkout__form input[type = "email"]').on('blur', checkEmailExist);
            apbct('form.checkout input[type = "email"]').on('blur', checkEmailExist);
            apbct('form.wpcf7-form input[type = "email"]')
                .on('blur', ctDebounceFuncExec(checkEmailExist, 300));
            // Ninja Forms
            const ninjaFormsEmailObserver = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            // Check if it's an email input inside Ninja Forms
                            if (
                                node.matches &&
                                node.matches(
                                    '.nf-form-content input[type="email"], input[type="email"].ninja-forms-field',
                                )
                            ) {
                                if (!node.hasAttribute('data-apbct-email-exist')) {
                                    node.addEventListener('blur', ctDebounceFuncExec(checkEmailExist, 300));
                                    node.setAttribute('data-apbct-email-exist', '1');
                                }
                            }
                            // If a whole form or part of it is added
                            node.querySelectorAll &&
                            node.querySelectorAll(
                                '.nf-form-content input[type="email"], input[type="email"].ninja-forms-field',
                            ).forEach(function(input) {
                                if (!input.hasAttribute('data-apbct-email-exist')) {
                                    input.addEventListener('blur', ctDebounceFuncExec(checkEmailExist, 300));
                                    input.setAttribute('data-apbct-email-exist', '1');
                                }
                            });
                        }
                    });
                });
            });
            // Start observing the entire document
            ninjaFormsEmailObserver.observe(document.body, {childList: true, subtree: true});
            // For already existing email fields
            document.querySelectorAll(
                '.nf-form-content input[type="email"], input[type="email"].ninja-forms-field',
            ).forEach(function(input) {
                if (!input.hasAttribute('data-apbct-email-exist')) {
                    input.addEventListener('blur', ctDebounceFuncExec(checkEmailExist, 300));
                    input.setAttribute('data-apbct-email-exist', '1');
                }
            });
        }
    }

    if (apbctLocalStorage.isSet('ct_checkjs')) {
        initCookies.push(['ct_checkjs', apbctLocalStorage.get('ct_checkjs')]);
    } else {
        initCookies.push(['ct_checkjs', 0]);
    }

    ctSetCookie(initCookies);
}

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
        'apbct_bot_detector_exist',
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
                apbctLocalStorage.set(item[0], item[1]);
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
            if (!+ctPublic.settings__data__bot_detector_enabled) {
                ctNoCookieAttachHiddenFieldsToForms();
            }
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

// eslint-disable-next-line require-jsdoc
function ctSetAlternativeCookie(cookies, params) {
    if (typeof (getJavascriptClientData) === 'function' ) {
        // reprocess already gained cookies data
        if (Array.isArray(cookies)) {
            cookies = getJavascriptClientData(cookies);
        }
    } else if (!+ctPublic.settings__data__bot_detector_enabled) {
        console.log('APBCT ERROR: getJavascriptClientData() is not loaded');
    }

    // if cookies is array, convert it to object
    if (Array.isArray(cookies) && cookies[0] && cookies[0][0] === 'apbct_bot_detector_exist') {
        cookies = {apbct_bot_detector_exist: cookies[0][1]};
    }
    // Only try to parse if cookies is a string (JSON)
    if (typeof cookies === 'string') {
        try {
            cookies = JSON.parse(cookies);
        } catch (e) {
            console.log('APBCT ERROR: JSON parse error:' + e);
            return;
        }
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

// eslint-disable-next-line require-jsdoc,camelcase,no-unused-vars
function apbct_attach_event_handler(elem, event, callback) {
    if (typeof window.addEventListener === 'function') elem.addEventListener(event, callback);
    else elem.attachEvent(event, callback);
}

// eslint-disable-next-line require-jsdoc,camelcase,no-unused-vars
function apbct_remove_event_handler(elem, event, callback) {
    if (typeof window.removeEventListener === 'function') elem.removeEventListener(event, callback);
    else elem.detachEvent(event, callback);
}

/**
 * Recursively decode JSON-encoded properties
 *
 * @param {mixed} object
 * @return {*}
 */
function removeDoubleJsonEncoding(object) { // eslint-disable-line no-unused-vars
    if ( typeof object === 'object') {
        // eslint-disable-next-line guard-for-in
        for (let objectKey in object) {
            // Recursion
            if ( typeof object[objectKey] === 'object') {
                object[objectKey] = removeDoubleJsonEncoding(object[objectKey]);
            }

            // Common case (out)
            if (
                typeof object[objectKey] === 'string' &&
                object[objectKey].match(/^[\[{].*?[\]}]$/) !== null // is like JSON
            ) {
                const parsedValue = JSON.parse(object[objectKey]);
                if ( typeof parsedValue === 'object' ) {
                    object[objectKey] = parsedValue;
                }
            }
        }
    }

    return object;
}

/**
 * @return {boolean|*}
 */
function ctGetPageForms() { // eslint-disable-line no-unused-vars
    let forms = document.forms;
    if (forms) {
        return forms;
    }
    return false;
}

// eslint-disable-next-line camelcase,require-jsdoc,no-unused-vars
function apbct_js_keys__set_input_value(result, data, params, obj) {
    if ( document.querySelectorAll('[name^=ct_checkjs]').length > 0 ) {
        let elements = document.querySelectorAll('[name^=ct_checkjs]');
        for ( let i = 0; i < elements.length; i++ ) {
            elements[i].value = result.js_key;
        }
    }
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
 * @return {string}
 */
function getNoCookieData() { // eslint-disable-line no-unused-vars
    let noCookieDataLocal = apbctLocalStorage.getCleanTalkData();
    let noCookieDataSession = apbctSessionStorage.getCleanTalkData();
    let noCookieData = {...noCookieDataLocal, ...noCookieDataSession};
    noCookieData = JSON.stringify(noCookieData);

    return '_ct_no_cookie_data_' + btoa(noCookieData);
}


/**
 * Retrieves the clentalk "cookie" data from starages.
 * Contains {...noCookieDataLocal, ...noCookieDataSession, ...noCookieDataTypo, ...noCookieDataFromUserActivity}.
 * @return {string}
 */
function getCleanTalkStorageDataArray() { // eslint-disable-line no-unused-vars
    let noCookieDataLocal = apbctLocalStorage.getCleanTalkData();
    let noCookieDataSession = apbctSessionStorage.getCleanTalkData();

    let noCookieDataTypo = {typo: []};
    if (document.ctTypoData && document.ctTypoData.data) {
        noCookieDataTypo = {typo: document.ctTypoData.data};
    }

    let noCookieDataFromUserActivity = {collecting_user_activity_data: []};

    if (document.ctCollectingUserActivityData) {
        let collectingUserActivityData = JSON.parse(JSON.stringify(document.ctCollectingUserActivityData));
        noCookieDataFromUserActivity = {collecting_user_activity_data: collectingUserActivityData};
    }

    return {...noCookieDataLocal, ...noCookieDataSession, ...noCookieDataTypo, ...noCookieDataFromUserActivity};
}

/**
 * Execute function with timeout, listening incoming args from context.
 * Could be useful if something needs to wait other actions and no there is no known event to bind for.
 * @param {function} func Function to call
 * @param {number} wait Seconds to delay
 * @return {(function(...[*]): void)|*}
 */
function ctDebounceFuncExec(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const context = this;
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            func.apply(context, args);
        }, wait);
    };
}

