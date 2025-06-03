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
