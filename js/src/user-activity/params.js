// eslint-disable-next-line camelcase
const ctDate = new Date();
const ctTimeMs = new Date().getTime();
let ctMouseEventTimerFlag = true; // Reading interval flag
let ctMouseData = [];
let ctMouseDataCounter = 0;
let ctCheckedEmails = {};
let ctCheckedEmailsExist = {};
let ctMouseReadInterval;
let ctMouseWriteDataInterval;
let tokenCheckerIntervalId;


// Writing first key press timestamp
const ctFunctionFirstKey = function output(event) {
    let KeyTimestamp = Math.floor(new Date().getTime() / 1000);
    ctSetCookie('ct_fkp_timestamp', KeyTimestamp);
    ctKeyStopStopListening();
};

/**
 * Stop mouse observing function
 */
function ctMouseStopData() {
    apbct_remove_event_handler(document, 'mousemove', ctFunctionMouseMove);
    clearInterval(ctMouseReadInterval);
    clearInterval(ctMouseWriteDataInterval);
}


// mouse read
if (ctPublic.data__key_is_ok) {
    // Reading interval
    ctMouseReadInterval = setInterval(function() {
        ctMouseEventTimerFlag = true;
    }, 150);

    // Writting interval
    ctMouseWriteDataInterval = setInterval(function() {
        ctSetCookie('ct_pointer_data', JSON.stringify(ctMouseData));
    }, 1200);
}

// Logging mouse position each 150 ms
const ctFunctionMouseMove = function output(event) {
    ctSetMouseMoved();
    if (ctMouseEventTimerFlag === true) {
        ctMouseData.push([
            Math.round(event.clientY),
            Math.round(event.clientX),
            Math.round(new Date().getTime() - ctTimeMs),
        ]);

        ctMouseDataCounter++;
        ctMouseEventTimerFlag = false;
        if (ctMouseDataCounter >= 50) {
            ctMouseStopData();
        }
    }
};

/**
 * Stop key listening function
 */
function ctKeyStopStopListening() {
    apbct_remove_event_handler(document, 'mousedown', ctFunctionFirstKey);
    apbct_remove_event_handler(document, 'keydown', ctFunctionFirstKey);
}

/**
 * ctSetHasScrolled
 */
function ctSetHasScrolled() {
    if ( ! apbctLocalStorage.isSet('ct_has_scrolled') || ! apbctLocalStorage.get('ct_has_scrolled') ) {
        ctSetCookie('ct_has_scrolled', 'true');
        apbctLocalStorage.set('ct_has_scrolled', true);
    }
    if (
        ctPublic.data__cookies_type === 'native' &&
        ctGetCookie('ct_has_scrolled') === undefined
    ) {
        ctSetCookie('ct_has_scrolled', 'true');
    }
}

/**
 * ctSetMouseMoved
 */
function ctSetMouseMoved() {
    if ( ! apbctLocalStorage.isSet('ct_mouse_moved') || ! apbctLocalStorage.get('ct_mouse_moved') ) {
        ctSetCookie('ct_mouse_moved', 'true');
        apbctLocalStorage.set('ct_mouse_moved', true);
    }
    if (
        ctPublic.data__cookies_type === 'native' &&
        ctGetCookie('ct_mouse_moved') === undefined
    ) {
        ctSetCookie('ct_mouse_moved', 'true');
    }
}

/**
 * init listeners for keyup and focus events
 */
function ctStartFieldsListening() {
    if (
        (apbctLocalStorage.isSet('ct_has_key_up') || apbctLocalStorage.get('ct_has_key_up')) &&
        (apbctLocalStorage.isSet('ct_has_input_focused') || apbctLocalStorage.get('ct_has_input_focused')) &&
        (
            ctPublic.data__cookies_type === 'native' &&
            ctGetCookie('ct_has_input_focused') !== undefined &&
            ctGetCookie('ct_has_key_up') !== undefined
        )
    ) {
        // already set
        return;
    }

    let forms = ctGetPageForms();
    ctPublic.handled_fields = [];

    if (forms.length > 0) {
        for (let i = 0; i < forms.length; i++) {
            // handle only inputs and textareas
            const handledFormFields = forms[i].querySelectorAll('input,textarea');
            for (let i = 0; i < handledFormFields.length; i++) {
                if (handledFormFields[i].type !== 'hidden') {
                    // collect handled fields to remove handler in the future
                    ctPublic.handled_fields.push(handledFormFields[i]);
                    // do attach handlers
                    apbct_attach_event_handler(handledFormFields[i], 'focus', ctFunctionHasInputFocused);
                    apbct_attach_event_handler(handledFormFields[i], 'keyup', ctFunctionHasKeyUp);
                }
            }
        }
    }
}

/**
 * Restart listen fields to set ct_has_input_focused or ct_has_key_up
 */
function restartFieldsListening() {
    if (!apbctLocalStorage.isSet('ct_has_input_focused') && !apbctLocalStorage.isSet('ct_has_key_up')) {
        ctStartFieldsListening();
    }
}

/**
 * stop listening keyup and focus
 * @param {string} eventName
 * @param {string} functionName
 */
function ctStopFieldsListening(eventName, functionName) {
    if (typeof ctPublic.handled_fields !== 'undefined' && ctPublic.handled_fields.length > 0) {
        for (let i = 0; i < ctPublic.handled_fields.length; i++) {
            apbct_remove_event_handler(ctPublic.handled_fields[i], eventName, functionName);
        }
    }
}

let ctFunctionHasInputFocused = function output(event) {
    ctSetHasInputFocused();
    ctStopFieldsListening('focus', ctFunctionHasInputFocused);
};

let ctFunctionHasKeyUp = function output(event) {
    ctSetHasKeyUp();
    ctStopFieldsListening('keyup', ctFunctionHasKeyUp);
};

/**
 * set ct_has_input_focused ct_has_key_up cookies on session period
 */
function ctSetHasInputFocused() {
    if ( ! apbctLocalStorage.isSet('ct_has_input_focused') || ! apbctLocalStorage.get('ct_has_input_focused') ) {
        apbctLocalStorage.set('ct_has_input_focused', true);
    }
    if (
        (
            (
                ctPublic.data__cookies_type === 'native' &&
                ctGetCookie('ct_has_input_focused') === undefined
            ) ||
            ctPublic.data__cookies_type === 'alternative'
        ) ||
        (
            ctPublic.data__cookies_type === 'none' &&
            (
                typeof ctPublic.force_alt_cookies !== 'undefined' ||
                (ctPublic.force_alt_cookies !== undefined && ctPublic.force_alt_cookies)
            )
        )
    ) {
        ctSetCookie('ct_has_input_focused', 'true');
    }
}

/**
 * ctSetHasKeyUp
 */
function ctSetHasKeyUp() {
    if ( ! apbctLocalStorage.isSet('ct_has_key_up') || ! apbctLocalStorage.get('ct_has_key_up') ) {
        apbctLocalStorage.set('ct_has_key_up', true);
    }
    if (
        (
            (
                ctPublic.data__cookies_type === 'native' &&
                ctGetCookie('ct_has_key_up') === undefined
            ) ||
            ctPublic.data__cookies_type === 'alternative'
        ) ||
        (
            ctPublic.data__cookies_type === 'none' &&
            (
                typeof ctPublic.force_alt_cookies !== 'undefined' ||
                (ctPublic.force_alt_cookies !== undefined && ctPublic.force_alt_cookies)
            )
        )
    ) {
        ctSetCookie('ct_has_key_up', 'true');
    }
}

if (ctPublic.data__key_is_ok) {
    apbct_attach_event_handler(document, 'mousemove', ctFunctionMouseMove);
    apbct_attach_event_handler(document, 'mousedown', ctFunctionFirstKey);
    apbct_attach_event_handler(document, 'keydown', ctFunctionFirstKey);
    apbct_attach_event_handler(document, 'scroll', ctSetHasScrolled);
}

/**
 * @param {mixed} commonCookies
 * @return {string}
 */
function getJavascriptClientData(commonCookies = []) { // eslint-disable-line no-unused-vars
    let resultDataJson = {};

    resultDataJson.ct_checked_emails = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_checked_emails');
    resultDataJson.ct_checked_emails_exist = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_checked_emails_exist');
    resultDataJson.ct_checkjs = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_checkjs');
    resultDataJson.ct_fkp_timestamp = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_fkp_timestamp');
    resultDataJson.ct_pointer_data = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_pointer_data');
    resultDataJson.ct_ps_timestamp = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_ps_timestamp');
    resultDataJson.ct_screen_info = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_screen_info');
    resultDataJson.ct_timezone = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_timezone');

    // collecting data from localstorage
    const ctMouseMovedLocalStorage = apbctLocalStorage.get(ctPublicFunctions.cookiePrefix + 'ct_mouse_moved');
    const ctHasScrolledLocalStorage = apbctLocalStorage.get(ctPublicFunctions.cookiePrefix + 'ct_has_scrolled');
    const ctCookiesTypeLocalStorage = apbctLocalStorage.get(ctPublicFunctions.cookiePrefix + 'ct_cookies_type');
    const apbctPageHits = apbctLocalStorage.get('apbct_page_hits');
    const apbctPrevReferer = apbctSessionStorage.get('apbct_prev_referer');
    const apbctSiteReferer = apbctSessionStorage.get('apbct_site_referer');
    const ctJsErrorsLocalStorage = apbctLocalStorage.get(ctPublicFunctions.cookiePrefix + 'ct_js_errors');
    const ctPixelUrl = apbctLocalStorage.get(ctPublicFunctions.cookiePrefix + 'apbct_pixel_url');
    const apbctHeadless = apbctLocalStorage.get(ctPublicFunctions.cookiePrefix + 'apbct_headless');
    const ctBotDetectorFrontendDataLog = apbctLocalStorage.get(
        ctPublicFunctions.cookiePrefix + 'ct_bot_detector_frontend_data_log',
    );

    // collecting data from cookies
    const ctMouseMovedCookie = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_mouse_moved');
    const ctHasScrolledCookie = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_has_scrolled');
    const ctCookiesTypeCookie = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_cookies_type');
    const ctCookiesPixelUrl = ctGetCookie(ctPublicFunctions.cookiePrefix + 'apbct_pixel_url');
    const apbctHeadlessNative = !!ctGetCookie(ctPublicFunctions.cookiePrefix + 'apbct_headless');


    resultDataJson.ct_mouse_moved = ctMouseMovedLocalStorage !== undefined ?
        ctMouseMovedLocalStorage : ctMouseMovedCookie;
    resultDataJson.ct_has_scrolled = ctHasScrolledLocalStorage !== undefined ?
        ctHasScrolledLocalStorage : ctHasScrolledCookie;
    resultDataJson.ct_cookies_type = ctCookiesTypeLocalStorage !== undefined ?
        ctCookiesTypeLocalStorage : ctCookiesTypeCookie;
    resultDataJson.apbct_pixel_url = ctPixelUrl !== undefined ?
        ctPixelUrl : ctCookiesPixelUrl;
    resultDataJson.apbct_headless = apbctHeadless !== undefined ?
        apbctHeadless : apbctHeadlessNative;
    resultDataJson.ct_bot_detector_frontend_data_log = ctBotDetectorFrontendDataLog !== undefined ?
        ctBotDetectorFrontendDataLog : '';
    if (resultDataJson.apbct_pixel_url && typeof(resultDataJson.apbct_pixel_url) == 'string') {
        if (resultDataJson.apbct_pixel_url.indexOf('%3A%2F')) {
            resultDataJson.apbct_pixel_url = decodeURIComponent(resultDataJson.apbct_pixel_url);
        }
    }

    resultDataJson.apbct_page_hits = apbctPageHits;
    resultDataJson.apbct_prev_referer = apbctPrevReferer;
    resultDataJson.apbct_site_referer = apbctSiteReferer;
    resultDataJson.apbct_ct_js_errors = ctJsErrorsLocalStorage;

    if (!resultDataJson.apbct_pixel_url) {
        resultDataJson.apbct_pixel_url = ctPublic.pixel__url;
    }

    if ( typeof (commonCookies) === 'object') {
        for (let i = 0; i < commonCookies.length; ++i) {
            if ( typeof (commonCookies[i][1]) === 'object' ) {
                // this is for handle SFW cookies
                resultDataJson[commonCookies[i][1][0]] = commonCookies[i][1][1];
            } else {
                resultDataJson[commonCookies[i][0]] = commonCookies[i][1];
            }
        }
    } else {
        console.log('APBCT JS ERROR: Collecting data type mismatch');
    }

    // Parse JSON properties to prevent double JSON encoding
    resultDataJson = removeDoubleJsonEncoding(resultDataJson);


    return JSON.stringify(resultDataJson);
}

/**
 * @return {string}
 */
function apbctGetScreenInfo() {
    return JSON.stringify({
        fullWidth: document.documentElement.scrollWidth,
        fullHeight: Math.max(
            document.body.scrollHeight, document.documentElement.scrollHeight,
            document.body.offsetHeight, document.documentElement.offsetHeight,
            document.body.clientHeight, document.documentElement.clientHeight,
        ),
        visibleWidth: document.documentElement.clientWidth,
        visibleHeight: document.documentElement.clientHeight,
    });
}

/**
 * Set three statements to the sessions storage: apbct_session_current_page, apbct_prev_referer.
 * @return {void}
 */
function apbctWriteReferrersToSessionStorage() {
    const sessionCurrentPage = apbctSessionStorage.get('apbct_session_current_page');

    // set session apbct_referer
    if (sessionCurrentPage!== false && document.location.href !== sessionCurrentPage) {
        apbctSessionStorage.set('apbct_prev_referer', sessionCurrentPage, false);
    }

    // set session current page to know referrer
    apbctSessionStorage.set('apbct_session_current_page', document.location.href, false);
}



