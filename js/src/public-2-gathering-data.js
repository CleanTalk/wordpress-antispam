/**
 * Class for gathering data
 */
class ApbctGatheringData { // eslint-disable-line no-unused-vars
    /**
     * Set session ID
     * @return {void}
     */
    setSessionId() {
        if (!apbctSessionStorage.isSet('apbct_session_id')) {
            const sessionID = Math.random().toString(36).replace(/[^a-z]+/g, '').substr(2, 10);
            apbctSessionStorage.set('apbct_session_id', sessionID, false);
            apbctLocalStorage.set('apbct_page_hits', 1);
            if (document.referrer) {
                let urlReferer = new URL(document.referrer);
                if (urlReferer.host !== location.host) {
                    apbctSessionStorage.set('apbct_site_referer', document.referrer, false);
                }
            }
        } else {
            apbctLocalStorage.set('apbct_page_hits', Number(apbctLocalStorage.get('apbct_page_hits')) + 1);
        }
    }

    /**
     * Set three statements to the sessions storage: apbct_session_current_page, apbct_prev_referer.
     * @return {void}
     */
    writeReferrersToSessionStorage() {
        const sessionCurrentPage = apbctSessionStorage.get('apbct_session_current_page');
        // set session apbct_referer
        if (sessionCurrentPage!== false && document.location.href !== sessionCurrentPage) {
            apbctSessionStorage.set('apbct_prev_referer', sessionCurrentPage, false);
        }
        // set session current page to know referrer
        apbctSessionStorage.set('apbct_session_current_page', document.location.href, false);
    }

    /**
     * Set cookies type.
     * If it's not set or not equal to ctPublic.data__cookies_type, clear ct_mouse_moved and ct_has_scrolled values.
     * @return {void}
     */
    setCookiesType() {
        const cookiesType = apbctLocalStorage.get('ct_cookies_type');
        if ( ! cookiesType || cookiesType !== ctPublic.data__cookies_type ) {
            apbctLocalStorage.set('ct_cookies_type', ctPublic.data__cookies_type);
            apbctLocalStorage.delete('ct_mouse_moved');
            apbctLocalStorage.delete('ct_has_scrolled');
        }
    }

    /**
     * Start fields listening
     * @return {void}
     */
    startFieldsListening() {
        if (ctPublic.data__cookies_type !== 'alternative') {
            this.startFieldsListening();
            // 2nd try to add listeners for delayed appears forms
            setTimeout(this.startFieldsListening, 1000);
        }
    }

    /**
     * Listen autocomplete
     * @return {void}
     */
    listenAutocomplete() {
        window.addEventListener('animationstart', this.apbctOnAnimationStart, true);
        window.addEventListener('input', this.apbctOnInput, true);
    }

    /**
     * Gathering typo data
     * @return {void}
     */
    gatheringTypoData() {
        document.ctTypoData = new CTTypoData();
        document.ctTypoData.gatheringFields();
        document.ctTypoData.setListeners();
    }

    /**
     * Gathering mouse data
     * @return {void}
     */
    gatheringMouseData() {
        new ApbctCollectingUserMouseActivity();
    }

    /**
     * Get screen info
     * @return {string}
     */
    getScreenInfo() {
        // Batch all layout-triggering property reads to avoid forced synchronous layouts
        const docEl = document.documentElement;
        const body = document.body;

        // Read all layout properties in one batch
        const layoutData = {
            scrollWidth: docEl.scrollWidth,
            bodyScrollHeight: body.scrollHeight,
            docScrollHeight: docEl.scrollHeight,
            bodyOffsetHeight: body.offsetHeight,
            docOffsetHeight: docEl.offsetHeight,
            bodyClientHeight: body.clientHeight,
            docClientHeight: docEl.clientHeight,
            docClientWidth: docEl.clientWidth,
        };

        return JSON.stringify({
            fullWidth: layoutData.scrollWidth,
            fullHeight: Math.max(
                layoutData.bodyScrollHeight, layoutData.docScrollHeight,
                layoutData.bodyOffsetHeight, layoutData.docOffsetHeight,
                layoutData.bodyClientHeight, layoutData.docClientHeight,
            ),
            visibleWidth: layoutData.docClientWidth,
            visibleHeight: layoutData.docClientHeight,
        });
    }

    /**
     * Restart listen fields to set ct_has_input_focused or ct_has_key_up
     * @return {void}
     */
    restartFieldsListening() {
        if (!apbctLocalStorage.isSet('ct_has_input_focused') && !apbctLocalStorage.isSet('ct_has_key_up')) {
            this.startFieldsListening();
        }
    }

    /**
     * Init listeners for keyup and focus events
     * @return {void}
     */
    startFieldsListening() {
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
}

/**
 * Class collecting user mouse activity data
 *
 */
// eslint-disable-next-line no-unused-vars, require-jsdoc
class ApbctCollectingUserMouseActivity {
    elementBody = document.querySelector('body');
    collectionForms = document.forms;
    /**
     * Constructor
     */
    constructor() {
        this.setListeners();
    }

    /**
     * Set listeners
     */
    setListeners() {
        this.elementBody.addEventListener('click', (event) => {
            this.checkElementInForms(event, 'addClicks');
        });

        this.elementBody.addEventListener('mouseup', (event) => {
            const selectedType = document.getSelection().type.toString();
            if (selectedType == 'Range') {
                this.addSelected();
            }
        });

        this.elementBody.addEventListener('mousemove', (event) => {
            this.checkElementInForms(event, 'trackMouseMovement');
        });
    }

    /**
     * Checking if there is an element in the form
     * @param {object} event
     * @param {string} addTarget
     */
    checkElementInForms(event, addTarget) {
        let resultCheck;
        for (let i = 0; i < this.collectionForms.length; i++) {
            if (
                event.target.outerHTML.length > 0 &&
                this.collectionForms[i].innerHTML.length > 0
            ) {
                resultCheck = this.collectionForms[i].innerHTML.indexOf(event.target.outerHTML);
            } else {
                resultCheck = -1;
            }
        }

        switch (addTarget) {
        case 'addClicks':
            if (resultCheck < 0) {
                this.addClicks();
            }
            break;
        case 'trackMouseMovement':
            if (resultCheck > -1) {
                this.trackMouseMovement();
            }
            break;
        default:
            break;
        }
    }

    /**
     * Add clicks
     */
    addClicks() {
        if (document.ctCollectingUserActivityData) {
            if (document.ctCollectingUserActivityData.clicks) {
                document.ctCollectingUserActivityData.clicks++;
            } else {
                document.ctCollectingUserActivityData.clicks = 1;
            }
            return;
        }

        document.ctCollectingUserActivityData = {clicks: 1};
    }

    /**
     * Add selected
     */
    addSelected() {
        if (document.ctCollectingUserActivityData) {
            if (document.ctCollectingUserActivityData.selected) {
                document.ctCollectingUserActivityData.selected++;
            } else {
                document.ctCollectingUserActivityData.selected = 1;
            }
            return;
        }

        document.ctCollectingUserActivityData = {selected: 1};
    }

    /**
     * Track mouse movement
     */
    trackMouseMovement() {
        if (!document.ctCollectingUserActivityData) {
            document.ctCollectingUserActivityData = {};
        }
        if (!document.ctCollectingUserActivityData.mouseMovementsInsideForm) {
            document.ctCollectingUserActivityData.mouseMovementsInsideForm = false;
        }

        document.ctCollectingUserActivityData.mouseMovementsInsideForm = true;
    }
}

/**
 * Class for gathering data about user typing.
 *
 * ==============================
 * isAutoFill       - only person can use auto fill
 * isUseBuffer      - use buffer for fill current field
 * ==============================
 * lastKeyTimestamp - timestamp of last key press in current field
 * speedDelta       - change for each key press in current field,
 *                    as difference between current and previous key press timestamps,
 *                    robots in general have constant speed of typing.
 *                    If speedDelta is constant for each key press in current field,
 *                    so, speedDelta will be roughly to 0, then it is robot.
 * ==============================
 */
// eslint-disable-next-line no-unused-vars,require-jsdoc
class CTTypoData {
    fieldData = {
        isAutoFill: false,
        isUseBuffer: false,
        speedDelta: 0,
        firstKeyTimestamp: 0,
        lastKeyTimestamp: 0,
        lastDelta: 0,
        countOfKey: 0,
    };

    fields = document.querySelectorAll('textarea[name=comment]');

    data = [];

    /**
     * Gather fields.
     */
    gatheringFields() {
        let fieldSet = Array.prototype.slice.call(this.fields);
        fieldSet.forEach((field, i) => {
            this.data.push(Object.assign({}, this.fieldData));
        });
    }

    /**
     * Set listeners.
     */
    setListeners() {
        this.fields.forEach((field, i) => {
            field.addEventListener('paste', () => {
                this.data[i].isUseBuffer = true;
            });
        });

        this.fields.forEach((field, i) => {
            field.addEventListener('onautocomplete', () => {
                this.data[i].isAutoFill = true;
            });
        });

        this.fields.forEach((field, i) => {
            field.addEventListener('input', () => {
                this.data[i].countOfKey++;
                let time = + new Date();
                let currentDelta = 0;

                if (this.data[i].countOfKey === 1) {
                    this.data[i].lastKeyTimestamp = time;
                    this.data[i].firstKeyTimestamp = time;
                    return;
                }

                currentDelta = time - this.data[i].lastKeyTimestamp;
                if (this.data[i].countOfKey === 2) {
                    this.data[i].lastKeyTimestamp = time;
                    this.data[i].lastDelta = currentDelta;
                    return;
                }

                if (this.data[i].countOfKey > 2) {
                    this.data[i].speedDelta += Math.abs(this.data[i].lastDelta - currentDelta);
                    this.data[i].lastKeyTimestamp = time;
                    this.data[i].lastDelta = currentDelta;
                }
            });
        });
    }
}

// eslint-disable-next-line camelcase
const ctTimeMs = new Date().getTime();
let ctMouseEventTimerFlag = true; // Reading interval flag
let ctMouseData = [];
let ctMouseDataCounter = 0;
let ctMouseReadInterval;
let ctMouseWriteDataInterval;


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

    if (typeof (commonCookies) === 'object') {
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
 * @return {bool}
 */
function ctIsDrawPixel() {
    if (ctPublic.pixel__setting == '3' && ctPublic.settings__data__bot_detector_enabled == '1') {
        return false;
    }

    return +ctPublic.pixel__enabled ||
        (ctPublic.data__cookies_type === 'none' && document.querySelectorAll('img#apbct_pixel').length === 0) ||
        (ctPublic.data__cookies_type === 'alternative' && document.querySelectorAll('img#apbct_pixel').length === 0);
}

/**
 * @param {string} pixelUrl
 * @return {bool}
 */
function ctSetPixelImg(pixelUrl) {
    if (ctPublic.pixel__setting == '3' && ctPublic.settings__data__bot_detector_enabled == '1') {
        return false;
    }
    ctSetCookie('apbct_pixel_url', pixelUrl);
    if ( ctIsDrawPixel() ) {
        if ( ! document.getElementById('apbct_pixel') ) {
            let insertedImg = document.createElement('img');
            insertedImg.setAttribute('alt', 'CleanTalk Pixel');
            insertedImg.setAttribute('title', 'CleanTalk Pixel');
            insertedImg.setAttribute('id', 'apbct_pixel');
            insertedImg.setAttribute('style', 'display: none; left: 99999px;');
            insertedImg.setAttribute('src', pixelUrl);
            apbct('body').append(insertedImg);
        }
    }
}

/**
 * @param {string} pixelUrl
 * @return {bool}
 */
function ctSetPixelImgFromLocalstorage(pixelUrl) {
    if (ctPublic.pixel__setting == '3' && ctPublic.settings__data__bot_detector_enabled == '1') {
        return false;
    }
    if ( ctIsDrawPixel() ) {
        if ( ! document.getElementById('apbct_pixel') ) {
            let insertedImg = document.createElement('img');
            insertedImg.setAttribute('alt', 'CleanTalk Pixel');
            insertedImg.setAttribute('title', 'CleanTalk Pixel');
            insertedImg.setAttribute('id', 'apbct_pixel');
            insertedImg.setAttribute('style', 'display: none; left: 99999px;');
            insertedImg.setAttribute('src', decodeURIComponent(pixelUrl));
            apbct('body').append(insertedImg);
        }
    }
}

/**
 * ctGetPixelUrl
 * @return {bool}
 */
// eslint-disable-next-line no-unused-vars, require-jsdoc
function ctGetPixelUrl() {
    if (ctPublic.pixel__setting == '3' && ctPublic.settings__data__bot_detector_enabled == '1') {
        return false;
    }

    // Check if pixel is already in localstorage and is not outdated
    let localStoragePixelUrl = apbctLocalStorage.get('apbct_pixel_url');
    if ( localStoragePixelUrl !== false ) {
        if ( ! apbctLocalStorage.isAlive('apbct_pixel_url', 3600 * 3) ) {
            apbctLocalStorage.delete('apbct_pixel_url');
        } else {
            // if so - load pixel from localstorage and draw it skipping AJAX
            ctSetPixelImgFromLocalstorage(localStoragePixelUrl);
            return;
        }
    }
    // Using REST API handler
    if ( ctPublicFunctions.data__ajax_type === 'rest' ) {
        apbct_public_sendREST(
            'apbct_get_pixel_url',
            {
                method: 'POST',
                callback: function(result) {
                    if (result &&
                        (typeof result === 'string' || result instanceof String) && result.indexOf('https') === 0) {
                        // set  pixel url to localstorage
                        if ( ! apbctLocalStorage.get('apbct_pixel_url') ) {
                            // set pixel to the storage
                            apbctLocalStorage.set('apbct_pixel_url', result);
                            // update pixel data in the hidden fields
                            ctNoCookieAttachHiddenFieldsToForms();
                        }
                        // then run pixel drawing
                        ctSetPixelImg(result);
                    }
                },
            },
        );
        // Using AJAX request and handler
    } else {
        apbct_public_sendAJAX(
            {
                action: 'apbct_get_pixel_url',
            },
            {
                notJson: true,
                callback: function(result) {
                    if (result &&
                        (typeof result === 'string' || result instanceof String) && result.indexOf('https') === 0) {
                        // set  pixel url to localstorage
                        if ( ! apbctLocalStorage.get('apbct_pixel_url') ) {
                            // set pixel to the storage
                            apbctLocalStorage.set('apbct_pixel_url', result);
                            // update pixel data in the hidden fields
                            ctNoCookieAttachHiddenFieldsToForms();
                        }
                        // then run pixel drawing
                        ctSetPixelImg(result);
                    }
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-Robots-Tag', 'noindex, nofollow');
                },
            },
        );
    }
}

// eslint-disable-next-line no-unused-vars,require-jsdoc
function ctSetPixelUrlLocalstorage(ajaxPixelUrl) {
    // set pixel to the storage
    ctSetCookie('apbct_pixel_url', ajaxPixelUrl);
}

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

/**
 * ctNoCookieAttachHiddenFieldsToForms
 */
function ctNoCookieAttachHiddenFieldsToForms() {
    if (ctPublic.data__cookies_type !== 'none') {
        return;
    }

    let forms = ctGetPageForms();

    if (forms) {
        for ( let i = 0; i < forms.length; i++ ) {
            if ( new ApbctHandler().checkHiddenFieldsExclusions(document.forms[i], 'no_cookie') ) {
                continue;
            }

            // ignore forms with get method @todo We need to think about this
            if (document.forms[i].getAttribute('method') === null ||
                document.forms[i].getAttribute('method').toLowerCase() === 'post') {
                // remove old sets
                let fields = forms[i].querySelectorAll('.ct_no_cookie_hidden_field');
                for ( let j = 0; j < fields.length; j++ ) {
                    fields[j].outerHTML = '';
                }
                // add new set
                document.forms[i].append(new ApbctAttachData().constructNoCookieHiddenField());
            }
        }
    }
}
