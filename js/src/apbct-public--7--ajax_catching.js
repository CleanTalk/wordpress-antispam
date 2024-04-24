/* global ctPublic */

// 1) Check the page need to be doing ajax catching
// 2) Check the form inside the ajax catching
// 3) Append the cleantalk service data to the caught ajax request data

const apbctDefaultFetch = window.fetch;
const apbctDefaultSend = XMLHttpRequest.prototype.send;

if (document.readyState !== 'loading') {
    checkFormsExistForCatching();
} else {
    apbct_attach_event_handler(document, 'DOMContentLoaded', checkFormsExistForCatching);
}

/**
 * Checking if the form exists on the page to be catching
 */
function checkFormsExistForCatching() {
    // Only for cookies:none
    if (
        typeof ctPublic.data__cookies_type !== 'undefined' &&
        ctPublic.data__cookies_type === 'none'
    ) {

        ctAjaxSetupAddCleanTalkDataBeforeSendAjax();



    }
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
    let eventToken = false;
    if ( typeof jQuery !== 'undefined' ) {
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

