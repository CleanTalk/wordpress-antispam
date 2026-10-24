/**
 * Set init params
 */
// eslint-disable-next-line no-unused-vars,require-jsdoc
function initLegacyParams(gatheringLoaded) {
    const screenInfo = (
        typeof ApbctGatheringData !== 'undefined' &&
        typeof ApbctGatheringData.prototype.getScreenInfo === 'function'
    ) ? new ApbctGatheringData().getScreenInfo() : '';

    const initCookies = [];

    apbctLocalStorage.set('ct_fkp_timestamp', '0');
    apbctLocalStorage.set('ct_pointer_data', '0');
    apbctLocalStorage.set('ct_screen_info', screenInfo);

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
        document.querySelectorAll('input[type = "email"], #email').forEach((elem) => {
            elem.addEventListener('blur', checkEmail);
        });
    }

    if ( +ctPublic.data__email_check_exist_post) {
        initCookies.push(['ct_checked_emails_exist', '0']);
        document.querySelectorAll('.comment-form input[name = "email"], input#email').forEach((elem) => {
            elem.addEventListener('blur', checkEmailExist);
        });
        document.querySelectorAll('.frm-fluent-form input[name = "email"], input#email').forEach((elem) => {
            elem.addEventListener('blur', checkEmailExist);
        });
        document.querySelectorAll('#registerform input[name = "user_email"]').forEach((elem) => {
            elem.addEventListener('blur', checkEmailExist);
        });
        document.querySelectorAll('form.wc-block-checkout__form input[type = "email"]').forEach((elem) => {
            elem.addEventListener('blur', checkEmailExist);
        });
        document.querySelectorAll('form.checkout input[type = "email"]').forEach((elem) => {
            elem.addEventListener('blur', checkEmailExist);
        });
        document.querySelectorAll('form.wpcf7-form input[type = "email"]').forEach((elem) => {
            elem.addEventListener('blur', ctDebounceFuncExec(checkEmailExist, 300));
        });
        document.querySelectorAll('form.wpforms-form input[type = "email"]').forEach((elem) => {
            elem.addEventListener('blur', checkEmailExist);
        });
        document.querySelectorAll('form[id^="gform_"] input[type = "email"]').forEach((elem) => {
            elem.addEventListener('blur', checkEmailExist);
        });
        apbctIntegrateDynamicEmailCheck({
            formSelector: '.nf-form-content',
            emailSelector: 'input[type="email"], input[type="email"].ninja-forms-field',
            handler: checkEmailExist,
            debounce: 300,
        });
    }

    if (gatheringLoaded) {
        initCookies.push(['ct_gathering_loaded', gatheringLoaded]);
        initCookies.push(['ct_fkp_timestamp', '0']);
        initCookies.push(['ct_pointer_data', '0']);
        initCookies.push(['ct_screen_info', screenInfo]);
    }

    ctSetCookie(initCookies);
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
 * @return {string}
 */
function getNoCookieData() { // eslint-disable-line no-unused-vars
    let noCookieDataLocal = apbctLocalStorage.getCleanTalkData();
    let noCookieDataSession = apbctSessionStorage.getCleanTalkData();
    let noCookieData = {...noCookieDataLocal, ...noCookieDataSession};

    // Bot detector browser state is transferred with the hidden field on the NoCookie mode
    if (typeof apbctGetBrowserStatePair === 'function') {
        const browserState = apbctGetBrowserStatePair();
        if (browserState) {
            noCookieData[browserState.key] = browserState.value;
        }
    }

    noCookieData = JSON.stringify(noCookieData);

    return '_ct_no_cookie_data_' + btoa(unescape(encodeURIComponent(noCookieData)));
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

