
class ApbctGatheringData {

}

class ApbctAttachData {
    attachHiddenFieldsToForms() {
        if (typeof ctPublic.force_alt_cookies == 'undefined' ||
            (ctPublic.force_alt_cookies !== 'undefined' && !ctPublic.force_alt_cookies)
        ) {
            ctNoCookieAttachHiddenFieldsToForms();
        }
    }

    attachNoCookie() {
        if (typeof ctPublic.data__cookies_type !== 'undefined' &&
            ctPublic.data__cookies_type === 'none'
        ) {
            ctAjaxSetupAddCleanTalkDataBeforeSendAjax();
            ctAddWCMiddlewares();
        }
    }

    attachVisibleFieldsToForm(form, index) {
        let hiddenInput = document.createElement( 'input');
        hiddenInput.setAttribute( 'type', 'hidden' );
        hiddenInput.setAttribute( 'id', 'apbct_visible_fields_' + index );
        hiddenInput.setAttribute( 'name', 'apbct_visible_fields');
        let visibleFieldsToInput = {};
        visibleFieldsToInput[0] = apbct_collect_visible_fields(form);
        hiddenInput.value = btoa(JSON.stringify(visibleFieldsToInput));
        form.append( hiddenInput );
    }

    attachVisibleFieldsDuringSubmit(event, form) {
        if ( ctPublic.data__cookies_type !== 'native' && typeof event.target.ctFormIndex !== 'undefined' ) {
            apbct_visible_fields_set_cookie( apbct_collect_visible_fields(form), event.target.ctFormIndex );
        }
    }

    attachNoCookieDuringSubmit(event) {
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
    }
}

class ApbctHandler {
    /**
     * Exclude form from handling
     * @param {HTMLFormElement} form
     * @return {boolean}
     */
    excludeForm(form) {
        if (ctCheckHiddenFieldsExclusions(form, 'visible_fields')) {
            return true;
        }

        if (form.querySelector('input[name="wspsc_add_cart_submit"]') ||
            form.querySelector('input[name="option"][value="com_vikrentcar"]') ||
            form.querySelector('input[name="option"][value="com_vikbooking"]')
        ) {
            return true;
        }

        if (form.elements.apbct_visible_fields !== undefined &&
            form.elements.apbct_visible_fields.length > 0
        ) {
            return true;
        }

        return false;
    }
}

class ApbctShowForbidden {

}


/**
 * Run cron jobs
 */
cronFormsHandler(2000);

/**
 * Ready function
 */
// eslint-disable-next-line camelcase,require-jsdoc
function apbct_ready() {
    apbctAttachEventTokenToMultipageGravityForms();
    apbctAttachEventTokenToWoocommerceGetRequestAddToCart();

    apbctPrepareBlockForAjaxForms();

    apbctSetSessionId();

    apbctWriteReferrersToSessionStorage();

    apbctSetCookiesType();

    apbctStartFieldsListening();

    apbctListenAutocomplete();

    apbctSetInitCookies();

    // detect integrated forms that need to be handled via alternative cookies
    ctDetectForcedAltCookiesForms();

    setTimeout(function() { 

        const attachData = new ApbctAttachData();
        attachData.attachHiddenFieldsToForms();
        attachData.attachNoCookie();

        const handler = new ApbctHandler();
        for (let i = 0; i < document.forms.length; i++) {
            let form = document.forms[i];

            if (handler.excludeForm(form)) {
                continue;
            }

            attachData.attachVisibleFieldsToForm(form, i);

            form.onsubmit_prev = form.onsubmit;

            form.ctFormIndex = i;
            form.onsubmit = function(event) {
                attachData.attachVisibleFieldsDuringSubmit(event, form);

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

    if (typeof ctPublic !== 'undefined' && + ctPublic.settings__forms__search_test === 1) {
        ctSearchFormProcessing();
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
