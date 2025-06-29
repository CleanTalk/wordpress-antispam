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
let botDetectorLogLastUpdate = 0;
let botDetectorLogEventTypesCollected = [];

// eslint-disable-next-line require-jsdoc,camelcase
function apbct_attach_event_handler(elem, event, callback) {
    if (typeof window.addEventListener === 'function') elem.addEventListener(event, callback);
    else elem.attachEvent(event, callback);
}
// eslint-disable-next-line require-jsdoc,camelcase
function apbct_remove_event_handler(elem, event, callback) {
    if (typeof window.removeEventListener === 'function') elem.removeEventListener(event, callback);
    else elem.detachEvent(event, callback);
}

// Writing first key press timestamp
const ctFunctionFirstKey = function output(event) {
    let KeyTimestamp = Math.floor(new Date().getTime() / 1000);
    ctSetCookie('ct_fkp_timestamp', KeyTimestamp);
    ctKeyStopStopListening();
};

/**
 * Run cron jobs
 */
// forms handler cron
cronFormsHandler(2000);

// bot_detector frontend_data log alt session saving cron
if (
    ctPublicFunctions.hasOwnProperty('data__bot_detector_enabled') &&
    ctPublicFunctions.data__bot_detector_enabled == 1 &&
    ctPublicFunctions.hasOwnProperty('data__frontend_data_log_enabled') &&
    ctPublicFunctions.data__frontend_data_log_enabled == 1
) {
    sendBotDetectorLogToAltSessions(1000);
}
/**
 * Cron jobs end.
 */

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
 * Do handle periodical actions.
 * @param {int} cronStartTimeout Time to go before cron start.
 */
function cronFormsHandler(cronStartTimeout = 2000) {
    setTimeout(function() {
        setInterval(function() {
            restartFieldsListening();
            restartBotDetectorEventTokenAttach();
        }, 2000);
    }, cronStartTimeout);
}

/**
 * Send BotDetector logs data to alternative sessions.
 * If log_last_update has changed and log contains new event types, the log will be sent to the alternative sessions.
 * @param {int} cronStartTimeout delay before cron start
 * @param {int} interval check fires on interval
 */
function sendBotDetectorLogToAltSessions(cronStartTimeout = 3000, interval = 1000) {
    setTimeout(function() {
        setInterval(function() {
            const currentLog = apbctLocalStorage.get('ct_bot_detector_frontend_data_log');
            if (needsSaveLogToAltSessions(currentLog)) {
                botDetectorLogLastUpdate = currentLog.log_last_update;
                // the log will be taken from javascriptclientdata
                ctSetAlternativeCookie([], {forceAltCookies: true});
            }
        }, interval);
    }, cronStartTimeout);
}

/**
 * Check if the log needs to be saved to the alt sessions. If the log has new event types, it will be saved.
 * @param {object} currentLog
 * @return {boolean}
 */
function needsSaveLogToAltSessions(currentLog) {
    if (
        currentLog && currentLog.hasOwnProperty('log_last_update') &&
        botDetectorLogLastUpdate !== currentLog.log_last_update
    ) {
        try {
            for (let i = 0; i < currentLog.records.length; i++) {
                const currentType = currentLog.records[i].frontend_data.js_event;
                // check if this event type was already collected
                if (currentType !== undefined && botDetectorLogEventTypesCollected.includes(currentType)) {
                    continue;
                }
                // add new event type to collection, this type will be sent to the alt sessions further
                botDetectorLogEventTypesCollected.push(currentType);
                return true;
            }
        } catch (e) {
            console.log('APBCT: bot detector log collection error: ' . e.toString());
        }
    }
    return false;
}

/**
 * Restart event_token attachment if some forms load after document ready.
 */
function restartBotDetectorEventTokenAttach() {
    // List there any new conditions, right now it works only for LatePoint forms.
    // Probably, we can remove this condition at all, because setEventTokenField()
    // checks all the forms without the field
    const doAttach = (
        document.getElementsByClassName('latepoint-form').length > 0 ||
        document.getElementsByClassName('mec-booking-form-container').length > 0 ||
        document.getElementById('login-form-popup') !== null
    );

    try {
        if ( doAttach ) {
            // get token from LS
            const token = apbctLocalStorage.get('bot_detector_event_token');
            if (typeof setEventTokenField === 'function' && token !== undefined && token.length === 64) {
                setEventTokenField(token);
            }
            // probably there we could use a new botDetectorInit if token is not found
        }
    } catch (e) {
        console.log(e.toString());
    }
}

/**
 * Stop mouse observing function
 */
function ctMouseStopData() {
    apbct_remove_event_handler(document, 'mousemove', ctFunctionMouseMove);
    clearInterval(ctMouseReadInterval);
    clearInterval(ctMouseWriteDataInterval);
}

/**
 * Stop key listening function
 */
function ctKeyStopStopListening() {
    apbct_remove_event_handler(document, 'mousedown', ctFunctionFirstKey);
    apbct_remove_event_handler(document, 'keydown', ctFunctionFirstKey);
}

/**
 * @param {mixed} e
 */
function checkEmail(e) {
    let currentEmail = e.target.value;

    if (! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(currentEmail)) {
        return;
    }

    if (currentEmail && !(currentEmail in ctCheckedEmails)) {
        // Using REST API handler
        if ( ctPublicFunctions.data__ajax_type === 'rest' ) {
            apbct_public_sendREST(
                'check_email_before_post',
                {
                    method: 'POST',
                    data: {'email': currentEmail},
                    callback: function(result) {
                        if (result.result) {
                            ctCheckedEmails[currentEmail] = {
                                'result': result.result,
                                'timestamp': Date.now() / 1000 |0,
                            };
                            ctSetCookie('ct_checked_emails', JSON.stringify(ctCheckedEmails));
                        }
                    },
                },
            );
            // Using AJAX request and handler
        } else if ( ctPublicFunctions.data__ajax_type === 'admin_ajax' ) {
            apbct_public_sendAJAX(
                {
                    action: 'apbct_email_check_before_post',
                    email: currentEmail,
                },
                {
                    callback: function(result) {
                        if (result.result) {
                            ctCheckedEmails[currentEmail] = {
                                'result': result.result,
                                'timestamp': Date.now() / 1000 |0,
                            };
                            ctSetCookie('ct_checked_emails', JSON.stringify(ctCheckedEmails));
                        }
                    },
                },
            );
        }
    }
}

/**
 * @param {mixed} e
 */
function checkEmailExist(e) {
    let currentEmail = e.target.value;
    let result;

    if (!currentEmail || !currentEmail.length) {
        let envelope = document.getElementById('apbct-check_email_exist-block');
        if (envelope) {
            envelope.remove();
        }
        let hint = document.getElementById('apbct-check_email_exist-popup_description');
        if (hint) {
            hint.remove();
        }
        return;
    }

    if (! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(currentEmail)) {
        return;
    }

    if (currentEmail in ctCheckedEmailsExist) {
        result = ctCheckedEmailsExist[currentEmail];
        getResultCheckEmailExist(e, result, currentEmail);

        return;
    }

    viewCheckEmailExist(e, 'load');

    // Using REST API handler
    ctPublicFunctions.data__ajax_type = 'rest';
    if (ctPublicFunctions.data__ajax_type === 'rest') {
        apbct_public_sendREST(
            'check_email_exist_post',
            {
                method: 'POST',
                data: {'email': currentEmail},
                callback: function(result) {
                    getResultCheckEmailExist(e, result, currentEmail);
                },
            },
        );

        return;
    }

    if (ctPublicFunctions.data__ajax_type === 'admin_ajax') {
        apbct_public_sendAJAX(
            {
                action: 'apbct_email_check_exist_post',
                email: currentEmail,
            },
            {
                callback: function(result) {
                    getResultCheckEmailExist(e, result, currentEmail);
                },
            },
        );
    }
}

/**
 * @param {mixed} e
 * @param {mixed} result
 * @param {string} currentEmail
 */
function getResultCheckEmailExist(e, result, currentEmail) {
    if (!result || !result.result) {
        return;
    }

    result = result.result;

    ctCheckedEmailsExist[currentEmail] = {
        'result': result,
        'timestamp': Date.now() / 1000 |0,
    };

    if (result.result == 'EXISTS') {
        viewCheckEmailExist(e, 'good_email', result.text_result);
    } else {
        viewCheckEmailExist(e, 'bad_email', result.text_result);
    }

    ctSetCookie('ct_checked_emails_exist', JSON.stringify(ctCheckedEmailsExist));
}

/**
 * @param {mixed} e
 * @param {string} state
 * @param {string} textResult
 */
function viewCheckEmailExist(e, state, textResult) {
    let parentElement = e.target.parentElement;
    let inputEmail = parentElement.querySelector('[name*="email"]');

    if (!inputEmail) {
        return;
    }

    let envelope;
    let hint;

    // envelope
    if (document.getElementById('apbct-check_email_exist-block')) {
        envelope = document.getElementById('apbct-check_email_exist-block');
    } else {
        envelope = document.createElement('div');
        envelope.setAttribute('class', 'apbct-check_email_exist-block');
        envelope.setAttribute('id', 'apbct-check_email_exist-block');
        window.addEventListener('scroll', function() {
            envelope.style.top = inputEmail.getBoundingClientRect().top + 'px';
        });
        parentElement.after(envelope);
    }

    // hint
    if (document.getElementById('apbct-check_email_exist-popup_description')) {
        hint = document.getElementById('apbct-check_email_exist-popup_description');
    } else {
        hint = document.createElement('div');
        hint.setAttribute('class', 'apbct-check_email_exist-popup_description');
        hint.setAttribute('id', 'apbct-check_email_exist-popup_description');
        window.addEventListener('scroll', function() {
            hint.style.top = envelope.getBoundingClientRect().top + 'px';
        });

        envelope.after(hint);
    }

    ctEmailExistSetElementsPositions();

    window.addEventListener('resize', function(event) {
        ctEmailExistSetElementsPositions();
    });

    switch (state) {
    case 'load':
        envelope.classList.remove('apbct-check_email_exist-good_email', 'apbct-check_email_exist-bad_email');
        envelope.classList.add('apbct-check_email_exist-load');
        break;

    case 'good_email':
        envelope.classList.remove('apbct-check_email_exist-load', 'apbct-check_email_exist-bad_email');
        envelope.classList.add('apbct-check_email_exist-good_email');

        envelope.onmouseover = function() {
            hint.textContent = textResult;
            hint.style.display = 'block';
            hint.style.top = inputEmail.getBoundingClientRect().top - hint.getBoundingClientRect().height + 'px';
            hint.style.color = '#1C7129';
        };

        envelope.onmouseout = function() {
            hint.style.display = 'none';
        };

        break;

    case 'bad_email':
        envelope.classList.remove('apbct-check_email_exist-load', 'apbct-check_email_exist-good_email');
        envelope.classList.add('apbct-check_email_exist-bad_email');

        envelope.onmouseover = function() {
            hint.textContent = textResult;
            hint.style.display = 'block';
            hint.style.top = inputEmail.getBoundingClientRect().top - hint.getBoundingClientRect().height + 'px';
            hint.style.color = '#E01111';
        };

        envelope.onmouseout = function() {
            hint.style.display = 'none';
        };

        break;

    default:
        break;
    }
}

/**
 * Shift the envelope to the input field on resizing the window
 * @param {object} envelope
 * @param {object} inputEmail
 */
function ctEmailExistSetElementsPositions() {
    const envelopeWidth = 35;
    const inputEmail = document.querySelector('comment-form input[name*="email"], input#email');
    if (!inputEmail) {
        return;
    }
    const envelope = document.getElementById('apbct-check_email_exist-block');
    if (envelope) {
        envelope.style.top = inputEmail.getBoundingClientRect().top + 'px';
        envelope.style.left = inputEmail.getBoundingClientRect().right - envelopeWidth - 10 + 'px';
        envelope.style.height = inputEmail.offsetHeight + 'px';
        envelope.style.width = envelopeWidth + 'px';
    }

    const hint = document.getElementById('apbct-check_email_exist-popup_description');
    if (hint) {
        hint.style.width = inputEmail.offsetWidth + 'px';
        hint.style.left = inputEmail.getBoundingClientRect().left + 'px';
    }
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
 * Restart listen fields to set ct_has_input_focused or ct_has_key_up
 */
function restartFieldsListening() {
    if (!apbctLocalStorage.isSet('ct_has_input_focused') && !apbctLocalStorage.isSet('ct_has_key_up')) {
        ctStartFieldsListening();
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
 * Prepare block to intercept AJAX response
 */
function apbctPrepareBlockForAjaxForms() {
    // eslint-disable-next-line require-jsdoc
    function ctPrepareBlockMessage(xhr) {
        if (xhr.responseText &&
            xhr.responseText.indexOf('"apbct') !== -1 &&
            xhr.responseText.indexOf('DOCTYPE') === -1
        ) {
            try {
                ctParseBlockMessage(JSON.parse(xhr.responseText));
            } catch (e) {
                console.log(e.toString());
            }
        }
    }

    if (typeof jQuery !== 'undefined') {
        // Capturing responses and output block message for unknown AJAX forms
        if (typeof jQuery(document).ajaxComplete() !== 'function') {
            jQuery(document).on('ajaxComplete', function(event, xhr, settings) {
                ctPrepareBlockMessage(xhr);
            });
        } else {
            jQuery(document).ajaxComplete( function(event, xhr, settings) {
                ctPrepareBlockMessage(xhr);
            });
        }
    } else {
        // if Jquery is not avaliable try to use xhr
        if (typeof XMLHttpRequest !== 'undefined') {
            // Capturing responses and output block message for unknown AJAX forms
            document.addEventListener('readystatechange', function(event) {
                if (event.target.readyState === 4) {
                    ctPrepareBlockMessage(event.target);
                }
            });
        }
    }
}

/**
 * For forced alt cookies.
 * If token is not added to the LS on apbc_ready, check every second if so and send token to the alt sessions.
 */
function startForcedAltEventTokenChecker() {
    tokenCheckerIntervalId = setInterval( function() {
        if (apbctLocalStorage.get('event_token_forced_set') === '1') {
            clearInterval(tokenCheckerIntervalId);
            return;
        }
        let eventToken = apbctLocalStorage.get('bot_detector_event_token');
        if (eventToken) {
            ctSetAlternativeCookie([['ct_bot_detector_event_token', eventToken]], {forceAltCookies: true});
            apbctLocalStorage.set('event_token_forced_set', '1');
            clearInterval(tokenCheckerIntervalId);
        } else {
        }
    }, 1000);
}


/**
 * Ready function
 */
// eslint-disable-next-line camelcase,require-jsdoc
function apbct_ready() {
    document.addEventListener('gform_page_loaded', function() {
        if (
            typeof ctPublic.force_alt_cookies === 'undefined' ||
            (ctPublic.force_alt_cookies !== 'undefined' && !ctPublic.force_alt_cookies)
        ) {
            ctNoCookieAttachHiddenFieldsToForms();
            if (typeof setEventTokenField === 'function' && typeof botDetectorLocalStorage === 'function') {
                setEventTokenField(botDetectorLocalStorage.get('bot_detector_event_token'));
            }
        }
    });
    if ( ! ctPublic.wc_ajax_add_to_cart ) {
        apbctCheckAddToCartByGet();
    }

    apbctPrepareBlockForAjaxForms();

    // set session ID
    if (!apbctSessionStorage.isSet('apbct_session_id')) {
        const sessionID = apbctGenerateUniqueID();
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

    apbctWriteReferrersToSessionStorage();

    const cookiesType = apbctLocalStorage.get('ct_cookies_type');
    if ( ! cookiesType || cookiesType !== ctPublic.data__cookies_type ) {
        apbctLocalStorage.set('ct_cookies_type', ctPublic.data__cookies_type);
        apbctLocalStorage.delete('ct_mouse_moved');
        apbctLocalStorage.delete('ct_has_scrolled');
    }

    if (ctPublic.data__cookies_type !== 'alternative') {
        ctStartFieldsListening();
        // 2nd try to add listeners for delayed appears forms
        setTimeout(ctStartFieldsListening, 1000);
    }

    window.addEventListener('animationstart', apbctOnAnimationStart, true);
    window.addEventListener('input', apbctOnInput, true);
    document.ctTypoData = new CTTypoData();
    document.ctTypoData.gatheringFields();
    document.ctTypoData.setListeners();

    // Collect scrolling info
    const initCookies = [
        ['ct_ps_timestamp', Math.floor(new Date().getTime() / 1000)],
        ['ct_fkp_timestamp', '0'],
        ['ct_pointer_data', '0'],
        // eslint-disable-next-line camelcase
        ['ct_timezone', ctDate.getTimezoneOffset()/60*(-1)],
        ['ct_screen_info', apbctGetScreenInfo()],
        ['apbct_headless', navigator.webdriver],
    ];

    apbctLocalStorage.set('ct_ps_timestamp', Math.floor(new Date().getTime() / 1000));
    apbctLocalStorage.set('ct_fkp_timestamp', '0');
    apbctLocalStorage.set('ct_pointer_data', '0');
    // eslint-disable-next-line camelcase
    apbctLocalStorage.set('ct_timezone', ctDate.getTimezoneOffset()/60*(-1) );
    apbctLocalStorage.set('ct_screen_info', apbctGetScreenInfo());
    apbctLocalStorage.set('apbct_headless', navigator.webdriver);

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

    if (
        +ctPublic.pixel__setting &&
        !(+ctPublic.pixel__setting == 3 && ctPublic.settings__data__bot_detector_enabled == 1)
    ) {
        if ( ctIsDrawPixel() ) {
            ctGetPixelUrl();
        } else {
            initCookies.push(['apbct_pixel_url', ctPublic.pixel__url]);
        }
    }

    if ( +ctPublic.data__email_check_before_post) {
        initCookies.push(['ct_checked_emails', '0']);
        apbct('input[type = \'email\'], #email').on('blur', checkEmail);
    }

    if ( +ctPublic.data__email_check_exist_post) {
        initCookies.push(['ct_checked_emails_exist', '0']);
        apbct('comment-form input[name = \'email\'], input#email').on('blur', checkEmailExist);
    }

    if (apbctLocalStorage.isSet('ct_checkjs')) {
        initCookies.push(['ct_checkjs', apbctLocalStorage.get('ct_checkjs')]);
    } else {
        initCookies.push(['ct_checkjs', 0]);
    }

    // detect integrated forms that need to be handled via alternative cookies
    ctDetectForcedAltCookiesForms();

    // send bot detector event token to alt cookies on problem forms
    let tokenForForceAlt = apbctLocalStorage.get('bot_detector_event_token');
    if (typeof ctPublic.force_alt_cookies !== 'undefined' &&
        ctPublic.force_alt_cookies &&
        ctPublic.settings__data__bot_detector_enabled
    ) {
        apbctLocalStorage.set('event_token_forced_set', '0');
        if (tokenForForceAlt) {
            initCookies.push(['ct_bot_detector_event_token', tokenForForceAlt]);
            apbctLocalStorage.set('event_token_forced_set', '1');
        } else {
            startForcedAltEventTokenChecker();
        }
    }

    ctSetCookie(initCookies);

    setTimeout(function() {
        if (
            typeof ctPublic.force_alt_cookies == 'undefined' ||
            (ctPublic.force_alt_cookies !== 'undefined' && !ctPublic.force_alt_cookies)
        ) {
            ctNoCookieAttachHiddenFieldsToForms();
        }

        if (
            typeof ctPublic.data__cookies_type !== 'undefined' &&
            ctPublic.data__cookies_type === 'none'
        ) {
            ctAjaxSetupAddCleanTalkDataBeforeSendAjax();
            ctAddWCMiddlewares();
        }

        for (let i = 0; i < document.forms.length; i++) {
            let form = document.forms[i];

            // Exclusion for forms
            if (ctCheckHiddenFieldsExclusions(document.forms[i], 'visible_fields')) {
                continue;
            }
            if (form.querySelector('input[name="wspsc_add_cart_submit"]') ||
                form.querySelector('input[name="option"][value="com_vikrentcar"]') ||
                form.querySelector('input[name="option"][value="com_vikbooking"]')
            ) {
                continue;
            }

            // The Form has hidden field like apbct_visible_fields
            if (
                document.forms[i].elements.apbct_visible_fields !== undefined &&
                document.forms[i].elements.apbct_visible_fields.length > 0
            ) {
                continue;
            }

            if (form.querySelector('input[name="apbct_visible_fields"]')) {
                let visibleFields = form.querySelector('input[name="apbct_visible_fields"]');
                form.removeChild(visibleFields);
            }

            let hiddenInput = document.createElement( 'input' );
            hiddenInput.setAttribute( 'type', 'hidden' );
            hiddenInput.setAttribute( 'id', 'apbct_visible_fields_' + i );
            hiddenInput.setAttribute( 'name', 'apbct_visible_fields');
            let visibleFieldsToInput = {};
            visibleFieldsToInput[0] = apbct_collect_visible_fields(form);
            hiddenInput.value = btoa(JSON.stringify(visibleFieldsToInput));
            form.append( hiddenInput );

            form.onsubmit_prev = form.onsubmit;

            form.ctFormIndex = i;
            form.onsubmit = function(event) {
                if ( ctPublic.data__cookies_type !== 'native' && typeof event.target.ctFormIndex !== 'undefined' ) {
                    apbct_visible_fields_set_cookie( apbct_collect_visible_fields(this), event.target.ctFormIndex );
                }

                if (ctPublic.data__cookies_type === 'none' && isFormThatNeedCatchXhr(event.target)) {
                    window.XMLHttpRequest.prototype.send = function(data) {
                        let noCookieData = getNoCookieData();
                        noCookieData = 'data%5Bct_no_cookie_hidden_field%5D=' + noCookieData + '&';
                        defaultSend.call(this, noCookieData + data);
                        setTimeout(() => {
                            window.XMLHttpRequest.prototype.send = defaultSend;
                        }, 0);
                    };
                }

                // Call previous submit action
                if (event.target.onsubmit_prev instanceof Function && !ctOnsubmitPrevCallExclude(event.target)) {
                    if (event.target.classList !== undefined && event.target.classList.contains('brave_form_form')) {
                        event.preventDefault();
                    }
                    setTimeout(function() {
                        event.target.onsubmit_prev.call(event.target, event);
                    }, 0);
                }
            };
        }
    }, 1000);

    // Listen clicks on encoded emails
    let encodedEmailNodes = document.querySelectorAll('[data-original-string]');
    ctPublic.encodedEmailNodes = encodedEmailNodes;
    if (encodedEmailNodes.length) {
        for (let i = 0; i < encodedEmailNodes.length; ++i) {
            encodedEmailNodes[i].addEventListener('click', ctFillDecodedEmailHandler);
        }
    }

    // WordPress Search form processing
    for (const _form of document.forms) {
        if (
            typeof ctPublic !== 'undefined' &&
            + ctPublic.settings__forms__search_test === 1 &&
            (
                _form.getAttribute('id') === 'searchform' ||
                (_form.getAttribute('class') !== null && _form.getAttribute('class').indexOf('search-form') !== -1) ||
                (_form.getAttribute('role') !== null && _form.getAttribute('role').indexOf('search') !== -1)
            )
        ) {
            // fibosearch integration
            if (_form.querySelector('input.dgwt-wcas-search-input')) {
                continue;
            }

            if (
                _form.getAttribute('id') === 'hero-search-form' ||
                _form.getAttribute('class') === 'hb-booking-search-form'
            ) {
                continue;
            }

            // this handles search forms onsubmit process
            _form.apbctSearchPrevOnsubmit = _form.onsubmit;
            _form.onsubmit = (e) => ctSearchFormOnSubmitHandler(e, _form);
        }
    }

    // Check any XMLHttpRequest connections
    apbctCatchXmlHttpRequest();

    // Initializing the collection of user activity
    new ApbctCollectingUserActivity();

    // Set important paramaters via ajax if problematic cache solutions found
    // todo These AJAX calls removed untill we find a better solution, reason is a lot of requests to the server.
    // apbctAjaxSetImportantParametersOnCacheExist(ctPublic.advancedCacheExists || ctPublic.varnishCacheExists);

    // Checking that the bot detector has loaded and received the event token for Anti-Crawler
    if (ctPublic.settings__sfw__anti_crawler) {
        checkBotDetectorExist();
    }
}

/**
 * Checking that the bot detector has loaded and received the event token
 */
function checkBotDetectorExist() {
    if (ctPublic.settings__data__bot_detector_enabled) {
        const botDetectorIntervalSearch = setInterval(() => {
            let botDetectorEventToken = localStorage.bot_detector_event_token ? true : false;

            if (botDetectorEventToken) {
                ctSetCookie('apbct_bot_detector_exist', '1', '3600');
                clearInterval(botDetectorIntervalSearch);
            }
        }, 500);
    }
}

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

/**
 * Insert event_token and no_cookies_data to some ajax request
 */
function apbctCatchXmlHttpRequest() {
    // 1) Check the page if it needed to catch XHR
    if ( document.querySelector('div.wfu_container') !== null ) {
        const originalSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function(body) {
            // 2) Check the caught request fi it needed to modify
            if (
                body &&
                typeof body === 'string' &&
                (
                    body.indexOf('action=wfu_ajax_action_ask_server') !== -1
                )
            ) {
                let addidionalCleantalkData = '';
                let eventToken = localStorage.getItem('bot_detector_event_token');
                try {
                    eventToken = JSON.parse(eventToken);
                } catch {
                    eventToken = false;
                }
                if (
                    eventToken !== null &&
                    eventToken !== false &&
                    eventToken.hasOwnProperty('value') &&
                    eventToken.value !== ''
                ) {
                    eventToken = eventToken.value;
                    addidionalCleantalkData += '&' + 'data%5Bct_bot_detector_event_token%5D=' + eventToken;
                }

                let noCookieData = getNoCookieData();
                addidionalCleantalkData += '&' + 'data%5Bct_no_cookie_hidden_field%5D=' + noCookieData;

                body += addidionalCleantalkData;

                return originalSend.apply(this, [body]);
            }
            return originalSend.apply(this, [body]);
        };
    }
}

/**
 * Run AJAX to set important_parameters on the site backend if problematic cache solutions are defined.
 * @param {boolean} cacheExist
 */
function apbctAjaxSetImportantParametersOnCacheExist(cacheExist) { // eslint-disable-line no-unused-vars
    // Set important parameters via ajax
    if ( cacheExist ) {
        if ( ctPublicFunctions.data__ajax_type === 'rest' ) {
            apbct_public_sendREST('apbct_set_important_parameters', {});
        } else if ( ctPublicFunctions.data__ajax_type === 'admin_ajax' ) {
            apbct_public_sendAJAX({action: 'apbct_set_important_parameters'}, {});
        }
    }
}

/**
 * Prepare jQuery.ajaxSetup to add nocookie data to the jQuery ajax request.
 * Notes:
 * - Do it just once, the ajaxSetup.beforeSend will be overwritten for any calls.
 * - Signs of forms need to be caught will be checked during ajaxSetup.settings.data process on send.
 * - Any sign of the form HTML of the caller is insignificant in this process.
 * @return {void}
 */
function ctAjaxSetupAddCleanTalkDataBeforeSendAjax() {
    // jquery ajax call intercept
    // this is the only place where we can found hard dependency on jQuery, if the form use it - the script
    // will work independing if jQuery is loaded by CleanTalk or not
    let eventToken = false;
    if ( typeof jQuery !== 'undefined' && typeof jQuery.ajaxSetup === 'function') {
        jQuery.ajaxSetup({
            beforeSend: function(xhr, settings) {
                let sourceSign = false;
                // settings data is string (important!)
                if ( typeof settings.data === 'string' ) {
                    if (settings.data.indexOf('twt_cc_signup') !== -1) {
                        sourceSign = 'twt_cc_signup';
                    }

                    if (settings.data.indexOf('action=mailpoet') !== -1) {
                        sourceSign = 'action=mailpoet';
                    }

                    if (
                        settings.data.indexOf('action=user_registration') !== -1 &&
                        settings.data.indexOf('ur_frontend_form_nonce') !== -1
                    ) {
                        sourceSign = 'action=user_registration';
                    }

                    if (settings.data.indexOf('action=happyforms_message') !== -1) {
                        sourceSign = 'action=happyforms_message';
                    }

                    if (settings.data.indexOf('action=new_activity_comment') !== -1) {
                        sourceSign = 'action=new_activity_comment';
                    }
                }
                if ( typeof settings.url === 'string' ) {
                    if (settings.url.indexOf('wc-ajax=add_to_cart') !== -1) {
                        sourceSign = 'wc-ajax=add_to_cart';
                        if (localStorage.getItem('bot_detector_event_token') !== null) {
                            eventToken = localStorage.getItem('bot_detector_event_token');
                            try {
                                eventToken = JSON.parse(eventToken);
                            } catch {
                                eventToken = false;
                            }
                            if (eventToken !== false && eventToken.hasOwnProperty('value') && eventToken.value !== '') {
                                eventToken = eventToken.value;
                            }
                        }
                    }
                }

                if (sourceSign) {
                    let noCookieData = getNoCookieData();
                    if (typeof eventToken === 'string') {
                        eventToken = 'data%5Bct_bot_detector_event_token%5D=' + eventToken + '&';
                    } else {
                        eventToken = '';
                    }
                    noCookieData = 'data%5Bct_no_cookie_hidden_field%5D=' + noCookieData + '&';

                    settings.data = noCookieData + eventToken + settings.data;
                }
            },
        });
    }
}

// eslint-disable-next-line require-jsdoc
function ctOnsubmitPrevCallExclude(form) {
    if (form.classList.contains('hb-booking-search-form')) {
        return true;
    }

    return false;
}

if (ctPublic.data__key_is_ok) {
    if (document.readyState !== 'loading') {
        apbct_ready();
    } else {
        apbct_attach_event_handler(document, 'DOMContentLoaded', apbct_ready);
    }

    apbctLocalStorage.set('ct_checkjs', ctPublic.ct_checkjs_key, true );
}

/**
 * @param {SubmitEvent} e
 * @param {object} targetForm
 */
function ctSearchFormOnSubmitHandler(e, targetForm) {
    try {
        // get honeypot field and it's value
        const honeyPotField = targetForm.querySelector('[name*="apbct_email_id__"]');
        let hpValue = null;
        if (
            honeyPotField !== null &&
            honeyPotField.value !== null
        ) {
            hpValue = honeyPotField.value;
        }

        // get cookie data from storages
        let cleantalkStorageDataArray = getCleanTalkStorageDataArray();

        // get event token from storage
        let eventTokenLocalStorage = apbctLocalStorage.get('bot_detector_event_token');

        // if noCookie data or honeypot data is set, proceed handling
        if ( cleantalkStorageDataArray !== null || honeyPotField !== null || eventTokenLocalStorage !== null ) {
            e.preventDefault();
            const callBack = () => {
                if (honeyPotField !== null) {
                    honeyPotField.parentNode.removeChild(honeyPotField);
                }
                if (typeof targetForm.apbctSearchPrevOnsubmit === 'function') {
                    targetForm.apbctSearchPrevOnsubmit();
                } else {
                    HTMLFormElement.prototype.submit.call(targetForm);
                }
            };

            let cookiesArray = cleantalkStorageDataArray;

            // if honeypot data provided add the fields to the parsed data
            if ( hpValue !== null ) {
                cookiesArray.apbct_search_form__honeypot_value = hpValue;
            }

            // set event token
            cookiesArray.ct_bot_detector_event_token = eventTokenLocalStorage;

            // if the pixel needs to be decoded
            if (
                typeof cookiesArray.apbct_pixel_url === 'string' &&
                cookiesArray.apbct_pixel_url.indexOf('%3A') !== -1
            ) {
                cookiesArray.apbct_pixel_url = decodeURIComponent(cookiesArray.apbct_pixel_url);
            }

            // data to JSON
            const parsedCookies = JSON.stringify(cookiesArray);

            // if any data provided, proceed data to xhr
            if ( typeof parsedCookies !== 'undefined' && parsedCookies.length !== 0 ) {
                ctSetAlternativeCookie(
                    parsedCookies,
                    {callback: callBack, onErrorCallback: callBack, forceAltCookies: true},
                );
            } else {
                callBack();
            }
        }
    } catch (error) {
        console.warn('APBCT search form onsubmit handler error. ' + error);
    }
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
 * Recursive
 *
 * Recursively decode JSON-encoded properties
 *
 * @param {mixed} object
 * @return {*}
 */
function removeDoubleJsonEncoding(object) {
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

// eslint-disable-next-line camelcase,require-jsdoc
function apbct_collect_visible_fields( form ) {
    // Get only fields
    let inputs = [];
    let inputsVisible = '';
    let inputsVisibleCount = 0;
    let inputsInvisible = '';
    let inputsInvisibleCount = 0;
    let inputsWithDuplicateNames = [];

    for (let key in form.elements) {
        if (!isNaN(+key)) {
            inputs[key] = form.elements[key];
        }
    }

    // Filter fields
    inputs = inputs.filter(function(elem) {
        // Filter already added fields
        if ( inputsWithDuplicateNames.indexOf( elem.getAttribute('name') ) !== -1 ) {
            return false;
        }
        // Filter inputs with same names for type == radio
        if ( -1 !== ['radio', 'checkbox'].indexOf( elem.getAttribute('type') )) {
            inputsWithDuplicateNames.push( elem.getAttribute('name') );
            return false;
        }
        return true;
    });

    // Visible fields
    inputs.forEach(function(elem, i, elements) {
        // Unnecessary fields
        if (
            elem.getAttribute('type') === 'submit' || // type == submit
            elem.getAttribute('name') === null ||
            elem.getAttribute('name') === 'ct_checkjs'
        ) {
            return;
        }
        // Invisible fields
        if (
            getComputedStyle(elem).display === 'none' || // hidden
            getComputedStyle(elem).visibility === 'hidden' || // hidden
            getComputedStyle(elem).opacity === '0' || // hidden
            elem.getAttribute('type') === 'hidden' // type == hidden
        ) {
            if ( elem.classList.contains('wp-editor-area') ) {
                inputsVisible += ' ' + elem.getAttribute('name');
                inputsVisibleCount++;
            } else {
                inputsInvisible += ' ' + elem.getAttribute('name');
                inputsInvisibleCount++;
            }
            // eslint-disable-next-line brace-style
        }
        // Visible fields
        else {
            inputsVisible += ' ' + elem.getAttribute('name');
            inputsVisibleCount++;
        }
    });

    inputsInvisible = inputsInvisible.trim();
    inputsVisible = inputsVisible.trim();

    return {
        visible_fields: inputsVisible,
        visible_fields_count: inputsVisibleCount,
        invisible_fields: inputsInvisible,
        invisible_fields_count: inputsInvisibleCount,
    };
}

// eslint-disable-next-line camelcase,require-jsdoc
function apbct_visible_fields_set_cookie( visibleFieldsCollection, formId ) {
    let collection = typeof visibleFieldsCollection === 'object' && visibleFieldsCollection !== null ?
        visibleFieldsCollection : {};

    if ( ctPublic.data__cookies_type === 'native' ) {
        // eslint-disable-next-line guard-for-in
        for ( let i in collection ) {
            if ( i > 10 ) {
                // Do not generate more than 10 cookies
                return;
            }
            let collectionIndex = formId !== undefined ? formId : i;
            ctSetCookie('apbct_visible_fields_' + collectionIndex, JSON.stringify( collection[i] ) );
        }
    } else {
        ctSetCookie('apbct_visible_fields', JSON.stringify( collection ) );
    }
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

// eslint-disable-next-line require-jsdoc
function ctParseBlockMessage(response) {
    let msg = '';
    if (typeof response.apbct !== 'undefined') {
        response = response.apbct;
        if (response.blocked) {
            msg = response.comment;
        }
    }
    if (typeof response.data !== 'undefined') {
        response = response.data;
        if (response.message !== undefined) {
            msg = response.message;
        }
    }

    if (msg) {
        document.dispatchEvent(
            new CustomEvent( 'apbctAjaxBockAlert', {
                bubbles: true,
                detail: {message: msg},
            } ),
        );

        // Show the result by modal
        cleantalkModal.loaded = msg;
        cleantalkModal.open();

        if (+response.stop_script === 1) {
            window.stop();
        }
    }
}

// eslint-disable-next-line no-unused-vars,require-jsdoc
function ctSetPixelUrlLocalstorage(ajaxPixelUrl) {
    // set pixel to the storage
    ctSetCookie('apbct_pixel_url', ajaxPixelUrl);
}

// eslint-disable-next-line require-jsdoc
function ctNoCookieConstructHiddenField(type) {
    let inputType = 'hidden';
    if (type === 'submit') {
        inputType = 'submit';
    }
    let field = '';

    let noCookieData = getCleanTalkStorageDataArray();
    noCookieData = JSON.stringify(noCookieData);
    noCookieData = '_ct_no_cookie_data_' + btoa(noCookieData);
    field = document.createElement('input');
    field.setAttribute('name', 'ct_no_cookie_hidden_field');
    field.setAttribute('value', noCookieData);
    field.setAttribute('type', inputType);
    field.classList.add('apbct_special_field');
    field.classList.add('ct_no_cookie_hidden_field');
    return field;
}

/**
 * Retrieves the clentalk "cookie" data from starages.
 * Contains {...noCookieDataLocal, ...noCookieDataSession, ...noCookieDataTypo, ...noCookieDataFromUserActivity}.
 * @return {string}
 */
function getCleanTalkStorageDataArray() {
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
 * @return {boolean|*}
 */
function ctGetPageForms() {
    let forms = document.forms;
    if (forms) {
        return forms;
    }
    return false;
}

/**
 * Get type of the field should be excluded. Return exclusion signs via object.
 * @param {object} form Form dom object.
 * @return {object} {'no_cookie': 1|0, 'visible_fields': 1|0}
 */
function ctGetHiddenFieldExclusionsType(form) {
    // visible fields
    let result = {'no_cookie': 0, 'visible_fields': 0};
    if (
        +ctPublic.data__visible_fields_required === 0 ||
        (form.method.toString().toLowerCase() === 'get' &&
        form.querySelectorAll('.nf-form-content').length === 0 &&
        form.id !== 'twt_cc_signup') ||
        form.classList.contains('slp_search_form') || // StoreLocatorPlus form
        form.parentElement.classList.contains('mec-booking') ||
        form.action.toString().indexOf('activehosted.com') !== -1 || // Active Campaign
        (form.id && form.id === 'caspioform') || // Caspio Form
        (form.classList && form.classList.contains('tinkoffPayRow')) || // TinkoffPayForm
        (form.classList && form.classList.contains('give-form')) || // GiveWP
        (form.id && form.id === 'ult-forgot-password-form') || // ult forgot password
        (form.id && form.id.toString().indexOf('calculatedfields') !== -1) || // CalculatedFieldsForm
        (form.id && form.id.toString().indexOf('sac-form') !== -1) || // Simple Ajax Chat
        (form.id &&
            form.id.toString().indexOf('cp_tslotsbooking_pform') !== -1) || // WP Time Slots Booking Form
        (form.name &&
            form.name.toString().indexOf('cp_tslotsbooking_pform') !== -1) || // WP Time Slots Booking Form
        form.action.toString() === 'https://epayment.epymtservice.com/epay.jhtml' || // Custom form
        (form.name && form.name.toString().indexOf('tribe-bar-form') !== -1) || // The Events Calendar
        (form.id && form.id === 'ihf-login-form') || // Optima Express login
        (form.id &&
            form.id === 'subscriberForm' &&
            form.action.toString().indexOf('actionType=update') !== -1) || // Optima Express update
        (form.id && form.id === 'ihf-main-search-form') || // Optima Express search
        (form.id && form.id === 'frmCalc') || // nobletitle-calc
        form.action.toString().indexOf('property-organizer-delete-saved-search-submit') !== -1 ||
        form.querySelector('a[name="login"]') !== null // digimember login form
    ) {
        result.visible_fields = 1;
    }

    // ajax search pro exclusion
    let ncFieldExclusionsSign = form.parentNode;
    if (
        ncFieldExclusionsSign && ncFieldExclusionsSign.classList.contains('proinput') ||
        (form.name === 'options' && form.classList.contains('asp-fss-flex'))
    ) {
        result.no_cookie = 1;
    }

    // woocommerce login form
    if (
        form && form.classList.contains('woocommerce-form-login')
    ) {
        result.visible_fields = 1;
        result.no_cookie = 1;
    }

    return result;
}

/**
 * Check if the form should be skipped from hidden field attach.
 * Return exclusion description if it is found, false otherwise.
 * @param {object} form Form dom object.
 * @param {string} hiddenFieldType Type of hidden field that needs to be checked.
 * Possible values: 'no_cookie'|'visible_fields'.
 * @return {boolean}
 */
function ctCheckHiddenFieldsExclusions(form, hiddenFieldType) {
    const formAction = typeof(form.action) == 'string' ? form.action : '';
    // Ajax Search Lite
    if (Boolean(form.querySelector('fieldset.asl_sett_scroll'))) {
        return true;
    }
    // Super WooCommerce Product Filter
    if (form.classList.contains('swpf-instant-filtering')) {
        return true;
    }
    // PayU 3-rd party service forms
    if (formAction.indexOf('secure.payu.com') !== -1 ) {
        return true;
    }

    if (formAction.indexOf('hsforms') !== -1 ) {
        return true;
    }

    if (formAction.indexOf('secureinternetbank.com') !== -1 ) {
        return true;
    }

    if (typeof (hiddenFieldType) === 'string' &&
        ['visible_fields', 'no_cookie'].indexOf(hiddenFieldType) !== -1) {
        const exclusions = ctGetHiddenFieldExclusionsType(form);
        return exclusions[hiddenFieldType] === 1;
    }

    return false;
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
            if ( ctCheckHiddenFieldsExclusions(document.forms[i], 'no_cookie') ) {
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
                document.forms[i].append(ctNoCookieConstructHiddenField());
            }
        }
    }
}

const defaultFetch = window.fetch;
const defaultSend = XMLHttpRequest.prototype.send;

if (document.readyState !== 'loading') {
    checkFormsExistForCatching();
} else {
    apbct_attach_event_handler(document, 'DOMContentLoaded', checkFormsExistForCatching);
}

/**
 * checkFormsExistForCatching
 */
function checkFormsExistForCatching() {
    setTimeout(function() {
        if (isFormThatNeedCatch()) {
            window.fetch = function(...args) {
                if (args &&
                    args[0] &&
                    typeof args[0].includes === 'function' &&
                    args[0].includes('/wp-json/metform/')
                ) {
                    let noCookieData = getNoCookieData();

                    if (args && args[1] && args[1].body) {
                        args[1].body.append('ct_no_cookie_hidden_field', noCookieData);
                    }
                }

                return defaultFetch.apply(window, args);
            };
        }
    }, 1000);
}

/**
 * @return {boolean}
 */
function isFormThatNeedCatch() {
    const formClasses = [
        'metform-form-content',
    ];
    let classExists = false;

    const forms = document.forms;
    for (let form of forms) {
        formClasses.forEach(function(classForm) {
            if (form.classList.contains(classForm)) {
                classExists = true;
            }
        });
    }

    return classExists;
}

/**
 * @param {HTMLElement} form
 * @return {boolean}
 */
function isFormThatNeedCatchXhr(form) {
    if (document.querySelector('div.elementor-widget[title=\'Login/Signup\']') != null) {
        return false;
    }
    if (form && form.action && form.action.toString().indexOf('mailpoet_subscription_form') !== -1) {
        return true;
    }

    return false;
}

/**
 * @return {string}
 */
function getNoCookieData() {
    let noCookieDataLocal = apbctLocalStorage.getCleanTalkData();
    let noCookieDataSession = apbctSessionStorage.getCleanTalkData();
    let noCookieData = {...noCookieDataLocal, ...noCookieDataSession};
    noCookieData = JSON.stringify(noCookieData);

    return '_ct_no_cookie_data_' + btoa(noCookieData);
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

/**
 * WooCommerce add to cart by GET request params collecting
 */
function apbctCheckAddToCartByGet() {
    // 1) Collect all links with add_to_cart_button class
    document.querySelectorAll('a.add_to_cart_button:not(.product_type_variable):not(.wc-interactive)').forEach((el) => {
        el.addEventListener('click', function(e) {
            let href = el.getAttribute('href');
            // 2) Add to href attribute additional parameter ct_bot_detector_event_token gathered from apbctLocalStorage
            let eventToken = apbctLocalStorage.get('bot_detector_event_token');
            if ( eventToken ) {
                if ( href.indexOf('?') === -1 ) {
                    href += '?';
                } else {
                    href += '&';
                }
                href += 'ct_bot_detector_event_token=' + eventToken;
                el.setAttribute('href', href);
            }
        });
    });
}
