/**
 * Run cron jobs
 */
cronFormsHandler(2000);

function apbctAttachEventTokenToMultipageGravityForms() {
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
}

function apbctAttachEventTokenToWoocommerceGetRequestAddToCart() {
    if ( ! ctPublic.wc_ajax_add_to_cart ) {
        apbctCheckAddToCartByGet();
    }
}

/**
 * Ready function
 */
// eslint-disable-next-line camelcase,require-jsdoc
function apbct_ready() {
    apbctAttachEventTokenToMultipageGravityForms();
    apbctAttachEventTokenToWoocommerceGetRequestAddToCart();

    apbctPrepareBlockForAjaxForms();

    // set session ID
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


const defaultFetch = window.fetch;
const defaultSend = XMLHttpRequest.prototype.send;

if (document.readyState !== 'loading') {
    checkFormsExistForCatching();
} else {
    apbct_attach_event_handler(document, 'DOMContentLoaded', checkFormsExistForCatching);
}
