
class ApbctEventTokenTransport {
    /**
     * Attach event token to multipage Gravity Forms
     * @return {void}
     */
    attachEventTokenToMultipageGravityForms() {
        document.addEventListener('gform_page_loaded', function() {
            if (
                typeof ctPublic.force_alt_cookies === 'undefined' ||
                (ctPublic.force_alt_cookies !== 'undefined' && !ctPublic.force_alt_cookies)
            ) {
                if (typeof setEventTokenField === 'function' && typeof botDetectorLocalStorage === 'function') {
                    setEventTokenField(botDetectorLocalStorage.get('bot_detector_event_token'));
                }
            }
        });
    }

    /**
     * Attach event token to WooCommerce get request add to cart
     * @return {void}
     */
    attachEventTokenToWoocommerceGetRequestAddToCart() {
        if ( ! ctPublic.wc_ajax_add_to_cart ) {
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
    }

    /**
     * Send bot detector event token to alt cookies on problem forms
     * @return {void}
     */
    setEventTokenToAltCookies() {
        let tokenForForceAlt = apbctLocalStorage.get('bot_detector_event_token');
        if (typeof ctPublic.force_alt_cookies !== 'undefined' && ctPublic.force_alt_cookies) {
            apbctLocalStorage.set('event_token_forced_set', '0');
            if (tokenForForceAlt) {
                initCookies.push(['ct_bot_detector_event_token', tokenForForceAlt]);
                apbctLocalStorage.set('event_token_forced_set', '1');
            } else {
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
        }
    }

    /**
     * Restart event_token attachment if some forms load after document ready.
     * @return {void}
     */
    restartBotDetectorEventTokenAttach() {
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
}

class ApbctAttachData {
    /**
     * Attach hidden fields to forms
     * @return {void}
     */
    attachHiddenFieldsToForms() {
        if (typeof ctPublic.force_alt_cookies == 'undefined' ||
            (ctPublic.force_alt_cookies !== 'undefined' && !ctPublic.force_alt_cookies)
        ) {
            ctNoCookieAttachHiddenFieldsToForms();
            document.addEventListener('gform_page_loaded', ctNoCookieAttachHiddenFieldsToForms);
        }
    }

    /**
     * Attach no cookie
     * @return {void}
     */
    attachNoCookie() {
        if (typeof ctPublic.data__cookies_type !== 'undefined' &&
            ctPublic.data__cookies_type === 'none'
        ) {
            ctAjaxSetupAddCleanTalkDataBeforeSendAjax();
            ctAddWCMiddlewares();
        }
    }

    /**
     * Attach visible fields to form
     * @param {HTMLFormElement} form
     * @param {number} index
     * @return {void}
     */
    attachVisibleFieldsToForm(form, index) {
        let hiddenInput = document.createElement( 'input');
        hiddenInput.setAttribute( 'type', 'hidden' );
        hiddenInput.setAttribute( 'id', 'apbct_visible_fields_' + index );
        hiddenInput.setAttribute( 'name', 'apbct_visible_fields');
        let visibleFieldsToInput = {};
        visibleFieldsToInput[0] = this.collectVisibleFields(form);
        hiddenInput.value = btoa(JSON.stringify(visibleFieldsToInput));
        form.append( hiddenInput );
    }

    /**
     * Attach visible fields during submit
     * @param {Event} event
     * @param {HTMLFormElement} form
     * @return {void}
     */
    attachVisibleFieldsDuringSubmit(event, form) {
        if ( ctPublic.data__cookies_type !== 'native' && typeof event.target.ctFormIndex !== 'undefined' ) {
            this.setVisibleFieldsCookie(this.collectVisibleFields(form), event.target.ctFormIndex);
        }
    }

    /**
     * Construct hidden field for no cookie
     * @param {string} type
     * @return {HTMLInputElement}
     */
    constructNoCookieHiddenField(type) {
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
     * Attach no cookie during submit
     * @param {Event} event
     * @return {void}
     */
    attachNoCookieDuringSubmit(event) {
        if (ctPublic.data__cookies_type === 'none' && event.target &&
            event.target.action && event.target.action.toString().indexOf('mailpoet_subscription_form') !== -1
        ) {
            window.XMLHttpRequest.prototype.send = function(data) {
                if (!ctPublic.settings__data__bot_detector_enabled) {
                    const noCookieData = 'data%5Bct_no_cookie_hidden_field%5D=' + getNoCookieData() + '&';
                    defaultSend.call(this, noCookieData + data);
                } else {
                    const eventToken = new ApbctHandler().toolGetEventToken();
                    if (eventToken) {
                        const noCookieData = 'data%5Bct_no_cookie_hidden_field%5D=' + getNoCookieData() + '&';
                        const eventTokenData = 'data%5Bct_bot_detector_event_token%5D=' + eventToken + '&';
                        defaultSend.call(this, noCookieData + eventTokenData + data);
                    }
                }
                setTimeout(() => {
                    window.XMLHttpRequest.prototype.send = defaultSend;
                }, 0);
            };
        }
    }

    /**
     * Set visible fields cookie
     * @param {object} visibleFieldsCollection
     * @param {number} formId
     * @return {void}
     */
    setVisibleFieldsCookie( visibleFieldsCollection, formId ) {
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

    /**
     * Collect visible fields
     * @param {HTMLFormElement} form
     * @return {object}
     */
    collectVisibleFields( form ) {
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
}

class ApbctHandler {
    /**
     * Exclude form from handling
     * @param {HTMLFormElement} form
     * @return {boolean}
     */
    excludeForm(form) {
        if (this.checkHiddenFieldsExclusions(form, 'visible_fields')) {
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

    /**
     * Check if the form should be skipped from hidden field attach.
     * Return exclusion description if it is found, false otherwise.
     * @param {object} form Form dom object.
     * @param {string} hiddenFieldType Type of hidden field that needs to be checked.
     * Possible values: 'no_cookie'|'visible_fields'.
     * @return {boolean}
     */
    checkHiddenFieldsExclusions(form, hiddenFieldType) {
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

        if (typeof (hiddenFieldType) === 'string' &&
            ['visible_fields', 'no_cookie'].indexOf(hiddenFieldType) !== -1) {
            const exclusions = this.getHiddenFieldExclusionsType(form);
            return exclusions[hiddenFieldType] === 1;
        }

        return false;
    }

    /**
     * Get type of the field should be excluded. Return exclusion signs via object.
     * @param {object} form Form dom object.
     * @return {object} {'no_cookie': 1|0, 'visible_fields': 1|0}
     */
    getHiddenFieldExclusionsType(form) {
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

    prevCallExclude(form) {
        if (form.classList.contains('hb-booking-search-form')) {
            return true;
        }

        return false;
    }

    catchMain(form, index) {
        form.onsubmit_prev = form.onsubmit;

        form.ctFormIndex = index;
        form.onsubmit = function(event) {
            attachData.attachVisibleFieldsDuringSubmit(event, form);

            // Call previous submit action
            if (event.target.onsubmit_prev instanceof Function && !this.prevCallExclude(event.target)) {
                if (event.target.classList !== undefined && event.target.classList.contains('brave_form_form')) {
                    event.preventDefault();
                }
                setTimeout(function() {
                    event.target.onsubmit_prev.call(event.target, event);
                }, 0);
            }
        };
    }

    cronFormsHandler(cronStartTimeout = 2000) {
        setTimeout(function() {
            setInterval(function() {
                if (!ctPublic.settings__data__bot_detector_enabled) {
                    new ApbctGatheringData().restartFieldsListening();
                }
                new ApbctEventTokenTransport().restartBotDetectorEventTokenAttach();
            }, 2000);
        }, cronStartTimeout);
    }

    /**
     * Detect forms that need to be handled via alternative cookies
     * @return {void}
     */
    detectForcedAltCookiesForms() {
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
        let smartQuizBuilder = document.querySelectorAll('form .sqbform, .fields_reorder_enabled').length > 0;
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
            otterForm ||
            smartQuizBuilder;

        setTimeout(function() {
            if (!ctPublic.force_alt_cookies) {
                let bookingPress = document.querySelectorAll('main[id^="bookingpress_booking_form"]').length > 0;
                ctPublic.force_alt_cookies = bookingPress;
            }
        }, 1000);
    }

    /**
     * Insert event_token and no_cookies_data to some ajax request
     * @return {void}
     */
    catchXmlHttpRequest() {
        if ( document.querySelector('div.wfu_container') !== null ) {
            const originalSend = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.send = function(body) {
                if (body && typeof body === 'string' && (body.indexOf('action=wfu_ajax_action_ask_server') !== -1)) {
                    let addidionalCleantalkData = '';

                    if (!ctPublic.settings__data__bot_detector_enabled) {
                        let noCookieData = getNoCookieData();
                        addidionalCleantalkData += '&' + 'data%5Bct_no_cookie_hidden_field%5D=' + noCookieData;
                    } else {
                        const eventToken = new ApbctHandler().toolGetEventToken();
                        if (eventToken) {
                            addidionalCleantalkData += '&' + 'data%5Bct_bot_detector_event_token%5D=' + eventToken;
                        }
                    }

                    body += addidionalCleantalkData;

                    return originalSend.apply(this, [body]);
                }
                return originalSend.apply(this, [body]);
            };
        }
    }

    catchFetchRequest() {
        setTimeout(function() {
            if (document.forms.map((form) => form.classList.contains('metform-form-content')).length > 0) {
                window.fetch = function(...args) {
                    if (args &&
                        args[0] &&
                        typeof args[0].includes === 'function' &&
                        args[0].includes('/wp-json/metform/')
                    ) {
                        if (args && args[1] && args[1].body) {
                            if (!ctPublic.settings__data__bot_detector_enabled) {
                                args[1].body.append('ct_no_cookie_hidden_field', getNoCookieData());
                            }
                            if (ctPublic.settings__data__bot_detector_enabled) {
                                args[1].body.append('ct_bot_detector_event_token', apbctLocalStorage.get('bot_detector_event_token'));
                            }
                        }
                    }

                    return defaultFetch.apply(window, args);
                };
            }
        }, 1000);
    }

    /**
     * Prepare jQuery.ajaxSetup to add nocookie data to the jQuery ajax request.
     * Notes:
     * - Do it just once, the ajaxSetup.beforeSend will be overwritten for any calls.
     * - Signs of forms need to be caught will be checked during ajaxSetup.settings.data process on send.
     * - Any sign of the form HTML of the caller is insignificant in this process.
     * - This is the only place where we can found hard dependency on jQuery, if the form use it - the script
     * will work independing if jQuery is loaded by CleanTalk or not
     * @return {void}
     */
    catchJqueryAjax() {
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
                        }
                    }

                    if (sourceSign) {
                        let eventToken = '';
                        let noCookieData = '';
                        if (ctPublic.settings__data__bot_detector_enabled) {
                            const token = new ApbctHandler().toolGetEventToken();
                            if (token) {
                                eventToken = 'data%5Bct_bot_detector_event_token%5D=' + token + '&';
                            }
                        } else {
                            noCookieData = getNoCookieData();
                            noCookieData = 'data%5Bct_no_cookie_hidden_field%5D=' + noCookieData + '&';
                        }

                        settings.data = noCookieData + eventToken + settings.data;
                    }
                },
            });
        }
    }

    /**
     * Insert no_cookies_data to rest request
     * @return {void}
     */
    catchWCRestRequestAsMiddleware() {
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
                if (ctPublic.settings__data__bot_detector_enabled) {
                    options.data.requests[0].data.event_token = localStorage.getItem('bot_detector_event_token');
                } else {
                    options.data.requests[0].data.ct_no_cookie_hidden_field = getNoCookieData();
                }
            }

            // checkout
            if (options.path === '/wc/store/v1/checkout') {
                if (ctPublic.settings__data__bot_detector_enabled) {
                    options.data.event_token = localStorage.getItem('bot_detector_event_token');
                } else {
                    options.data.ct_no_cookie_hidden_field = getNoCookieData();
                }
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
     * Handle search forms
     * @return {void}
     */
    searchFormHandler() {
        for (const _form of document.forms) {
            // fibosearch integration
            if (_form.querySelector('input.dgwt-wcas-search-input')) {
                continue;
            }

            if (_form.getAttribute('id') === 'hero-search-form' ||
                _form.getAttribute('class') === 'hb-booking-search-form'
            ) {
                continue;
            }

            if (_form.getAttribute('id') === 'searchform' ||
                (_form.getAttribute('class') !== null && _form.getAttribute('class').indexOf('search-form') !== -1) ||
                (_form.getAttribute('role') !== null && _form.getAttribute('role').indexOf('search') !== -1)
            ) {
                // this handles search forms onsubmit process
                _form.apbctSearchPrevOnsubmit = _form.onsubmit;
                const handler = function (e, targetForm) {
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
                _form.onsubmit = (e) => handler(e, _form);
            }
        }
    }

    /**
     * Checking that the bot detector has loaded and received the event token for Anti-Crawler
     * @return {void}
     */
    toolForAntiCrawlerCheckDuringBotDetector() {
        const botDetectorIntervalSearch = setInterval(() => {
            if (localStorage.bot_detector_event_token) {
                ctSetCookie('apbct_bot_detector_exist', '1', '3600');
                clearInterval(botDetectorIntervalSearch);
            }
        }, 500);
    }

    /**
     * Get event token
     * @return {string|boolean}
     */
    toolGetEventToken() {
        let eventToken = localStorage.getItem('bot_detector_event_token');

        try {
            eventToken = JSON.parse(eventToken);
        } catch {
            eventToken = false;
        }

        if (eventToken !== null && eventToken !== false &&
            eventToken.hasOwnProperty('value') && eventToken.value !== ''
        ) {
            return eventToken.value;
        }

        return false;
    }
}

class ApbctShowForbidden {
    /**
     * Prepare block to intercept AJAX response
     * @return {void}
     */
    prepareBlockForAjaxForms() {
        function prepareBlockMessage(xhr) {
            if (xhr.responseText &&
                xhr.responseText.indexOf('"apbct') !== -1 &&
                xhr.responseText.indexOf('DOCTYPE') === -1
            ) {
                try {
                    this.parseBlockMessage(JSON.parse(xhr.responseText));
                } catch (e) {
                    console.log(e.toString());
                }
            }
        }

        if (typeof jQuery !== 'undefined') {
            // Capturing responses and output block message for unknown AJAX forms
            if (typeof jQuery(document).ajaxComplete() !== 'function') {
                jQuery(document).on('ajaxComplete', function(event, xhr, settings) {
                    prepareBlockMessage(xhr);
                });
            } else {
                jQuery(document).ajaxComplete( function(event, xhr, settings) {
                    prepareBlockMessage(xhr);
                });
            }
        } else {
            // if Jquery is not avaliable try to use xhr
            if (typeof XMLHttpRequest !== 'undefined') {
                // Capturing responses and output block message for unknown AJAX forms
                document.addEventListener('readystatechange', function(event) {
                    if (event.target.readyState === 4) {
                        prepareBlockMessage(event.target);
                    }
                });
            }
        }
    }

    /**
     * Parse block message
     * @param {object} response
     * @return {void}
     */
    parseBlockMessage(response) {
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
}

/**
 * Ready function
 */
// eslint-disable-next-line camelcase,require-jsdoc
function apbct_ready() {
    new ApbctShowForbidden().prepareBlockForAjaxForms();

    const handler = new ApbctHandler();
    handler.detectForcedAltCookiesForms();

    // Gathering data when bot detector is disabled
    if (!ctPublic.settings__data__bot_detector_enabled) {
        const gatheringData = new ApbctGatheringData();
        gatheringData.setSessionId();
        gatheringData.writeReferrersToSessionStorage();
        gatheringData.setCookiesType();
        gatheringData.startFieldsListening();
        gatheringData.listenAutocomplete();
        gatheringData.gatheringTypoData();
        gatheringData.initParams();
        gatheringData.gatheringMouseData();
    }

    setTimeout(function() { 
        // Attach data when bot detector is enabled
        if (ctPublic.settings__data__bot_detector_enabled) {
            const eventTokenTransport = new ApbctEventTokenTransport();
            eventTokenTransport.attachEventTokenToMultipageGravityForms();
            eventTokenTransport.attachEventTokenToWoocommerceGetRequestAddToCart();
        }

        const attachData = new ApbctAttachData();

        // Attach data when bot detector is disabled
        if (!ctPublic.settings__data__bot_detector_enabled) {
            attachData.attachHiddenFieldsToForms();
            attachData.attachNoCookie();
        }

        for (let i = 0; i < document.forms.length; i++) {
            const form = document.forms[i];

            if (handler.excludeForm(form)) {
                continue;
            }

            attachData.attachVisibleFieldsToForm(form, i);

            handler.catchMain(form, i);
        }
    }, 1000);

    if (+ctPublic.settings__forms__search_test === 1) {
        handler.searchFormHandler();
    }

    handler.catchXmlHttpRequest();
    handler.catchFetchRequest();
    handler.catchJqueryAjax();
    handler.catchWCRestRequestAsMiddleware();

    if (ctPublic.settings__sfw__anti_crawler && ctPublic.settings__data__bot_detector_enabled) {
        handler.toolForAntiCrawlerCheckDuringBotDetector();
    }
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

/**
 * Run cron jobs
 */
new ApbctHandler().cronFormsHandler(2000);
