/**
 * Class for event token transport
 */
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
                // @ToDo need to be refactored? maybe just ctNoCookieAttachHiddenFieldsToForms(); need calling here?
                // @ToDo function `setEventTokenField`, `botDetectorLocalStorage`
                // are never defined, this condition is everytime false
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
            document.querySelectorAll('a.add_to_cart_button:not(.product_type_variable):not(.wc-interactive)')
                .forEach((el) => {
                    el.addEventListener('click', function(e) {
                        let href = el.getAttribute('href');
                        // 2) Add to href attribute additional parameter
                        // ct_bot_detector_event_token gathered from apbctLocalStorage
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

/**
 * Class for attaching data
 */
class ApbctAttachData {
    /**
     * Attach hidden fields to forms
     * @return {void}
     */
    attachHiddenFieldsToForms() {
        if (typeof ctPublic.force_alt_cookies == 'undefined' ||
            (ctPublic.force_alt_cookies !== 'undefined' && !ctPublic.force_alt_cookies)
        ) {
            if (!+ctPublic.settings__data__bot_detector_enabled) {
                ctNoCookieAttachHiddenFieldsToForms();
                document.addEventListener('gform_page_loaded', ctNoCookieAttachHiddenFieldsToForms);
            }
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
                if (!+ctPublic.settings__data__bot_detector_enabled) {
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

        // Batch all style computations to avoid forced layouts
        const styleChecks = inputs.map(function(elem) {
            // Unnecessary fields
            if (
                elem.getAttribute('type') === 'submit' || // type == submit
                elem.getAttribute('name') === null ||
                elem.getAttribute('name') === 'ct_checkjs'
            ) {
                return {elem: elem, skip: true};
            }

            // Check for hidden type first (no layout required)
            if (elem.getAttribute('type') === 'hidden') {
                return {elem: elem, isVisible: false, isWpEditor: elem.classList.contains('wp-editor-area')};
            }

            // Batch getComputedStyle calls to avoid multiple layout thrashing
            const computedStyle = getComputedStyle(elem);
            const isHidden = computedStyle.display === 'none' ||
                           computedStyle.visibility === 'hidden' ||
                           computedStyle.opacity === '0';

            return {
                elem: elem,
                isVisible: !isHidden,
                isWpEditor: elem.classList.contains('wp-editor-area'),
            };
        });

        // Process the results
        styleChecks.forEach(function(check) {
            if (check.skip) {
                return;
            }

            if (!check.isVisible) {
                if (check.isWpEditor) {
                    inputsVisible += ' ' + check.elem.getAttribute('name');
                    inputsVisibleCount++;
                } else {
                    inputsInvisible += ' ' + check.elem.getAttribute('name');
                    inputsInvisibleCount++;
                }
            } else {
                inputsVisible += ' ' + check.elem.getAttribute('name');
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

/**
 * Class for handling
 */
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

        if (formAction.indexOf('secureinternetbank.com') !== -1 ) {
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

    /**
     * Exclude form from prevCallExclude
     * @param {object} form
     * @return {boolean}
     */
    prevCallExclude(form) {
        if (form.classList.contains('hb-booking-search-form')) {
            return true;
        }

        return false;
    }

    /**
     * Catch main
     * @param {object} form
     * @param {number} index
     * @return {void}
     */
    catchMain(form, index) {
        form.onsubmit_prev = form.onsubmit;
        form.ctFormIndex = index;

        const handler = this;

        form.onsubmit = function(event) {
            new ApbctAttachData().attachVisibleFieldsDuringSubmit(event, form);

            if (event.target.onsubmit_prev instanceof Function && !handler.prevCallExclude(event.target)) {
                if (event.target.classList !== undefined && event.target.classList.contains('brave_form_form')) {
                    event.preventDefault();
                }
                setTimeout(function() {
                    event.target.onsubmit_prev.call(event.target, event);
                }, 0);
            }
        };
    }

    /**
     * Cron forms handler
     * @param {number} cronStartTimeout
     * @return {void}
     */
    cronFormsHandler(cronStartTimeout = 2000) {
        setTimeout(function() {
            setInterval(function() {
                if (!+ctPublic.settings__data__bot_detector_enabled) {
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
        let smartFormsSign = document.querySelectorAll('script[id*="smart-forms"]').length > 0;
        let jetpackCommentsForm = document.querySelectorAll('iframe[name="jetpack_remote_comment"]').length > 0;
        let userRegistrationProForm = document.querySelectorAll('div[id^="user-registration-form"]').length > 0;
        let etPbDiviSubscriptionForm = document.querySelectorAll('div[class^="et_pb_newsletter_form"]').length > 0;
        let fluentBookingApp = document.querySelectorAll('div[class^="fluent_booking_app"]').length > 0;
        let bloomPopup = document.querySelectorAll('div[class^="et_bloom_form_container"]').length > 0;
        let pafeFormsFormElementor = document.querySelectorAll('div[class*="pafe-form"]').length > 0;
        let otterForm = document.querySelectorAll('div [class*="otter-form"]').length > 0;
        let smartQuizBuilder = document.querySelectorAll('form .sqbform, .fields_reorder_enabled').length > 0;
        ctPublic.force_alt_cookies = smartFormsSign ||
            jetpackCommentsForm ||
            userRegistrationProForm ||
            etPbDiviSubscriptionForm ||
            fluentBookingApp ||
            pafeFormsFormElementor ||
            bloomPopup ||
            otterForm ||
            smartQuizBuilder;

        setTimeout(function() {
            if (!ctPublic.force_alt_cookies) {
                const bookingPress =
                    (
                        document.querySelectorAll('main[id^="bookingpress_booking_form"]').length > 0 ||
                        document.querySelectorAll('.bpa-frontend-main-container').length > 0
                    );
                ctPublic.force_alt_cookies = bookingPress;
            }
        }, 1000);
    }

    /**
     * Insert event_token and no_cookies_data to some ajax request
     * @return {void}
     */
    catchXmlHttpRequest() {
        if (
            document.querySelector('div.wfu_container') !== null ||
            document.querySelector('#newAppointmentForm') !== null ||
            document.querySelector('.booked-calendar-shortcode-wrap') !== null ||
            (
                // Back In Stock Notifier for WooCommerce | WooCommerce Waitlist Pro
                document.body.classList.contains('single-product') &&
                typeof cwginstock !== 'undefined'
            ) ||
            document.querySelector('div.fluent_booking_wrap') !== null // Fluent Booking Pro
        ) {
            const originalSend = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.send = function(body) {
                let isNeedToAddCleantalkDataCheckString = body && typeof body === 'string' &&
                    (
                        body.indexOf('action=wfu_ajax_action_ask_server') !== -1 ||
                        body.indexOf('action=booked_add_appt') !== -1 ||
                        body.indexOf('action=cwginstock_product_subscribe') !== -1
                    );

                let isNeedToAddCleantalkDataCheckFormData = body && typeof body === 'object' &&
                    body instanceof FormData &&
                    (
                        body.has('action') && body.get('action') === 'fluent_cal_schedule_meeting'
                    );

                if (isNeedToAddCleantalkDataCheckString) {
                    let addidionalCleantalkData = '';

                    if (!+ctPublic.settings__data__bot_detector_enabled) {
                        let noCookieData = getNoCookieData();
                        addidionalCleantalkData += '&' + 'data%5Bct_no_cookie_hidden_field%5D=' + noCookieData;
                    } else {
                        const eventToken = new ApbctHandler().toolGetEventToken();
                        if (eventToken) {
                            addidionalCleantalkData += '&' + 'data%5Bct_bot_detector_event_token%5D=' + eventToken;
                        }
                    }

                    body += addidionalCleantalkData;
                }

                if (isNeedToAddCleantalkDataCheckFormData) {
                    if (!+ctPublic.settings__data__bot_detector_enabled) {
                        let noCookieData = getNoCookieData();
                        body.append('ct_no_cookie_hidden_field', noCookieData);
                    } else {
                        const eventToken = new ApbctHandler().toolGetEventToken();
                        if (eventToken) {
                            body.append('ct_bot_detector_event_token', eventToken);
                        }
                    }
                }

                return originalSend.apply(this, [body]);
            };
        }
    }

    /**
     * Catch fetch request
     * @return {void}
     */
    catchFetchRequest() {
        setTimeout(function() {
            if (
                (
                    document.forms && document.forms.length > 0 &&
                    (
                        Array.from(document.forms).some((form) =>
                            form.classList.contains('metform-form-content')) ||
                        Array.from(document.forms).some((form) =>
                            form.classList.contains('wprm-user-ratings-modal-stars-container')) ||
                        Array.from(document.forms).some((form) => {
                            if (form.parentElement &&
                                form.parentElement.classList.length > 0 &&
                                form.parentElement.classList[0].indexOf('b24-form-content') !== -1
                            ) {
                                return true;
                            }
                        })
                    )
                ) ||
                (
                    document.querySelectorAll('button').length > 0 &&
                    Array.from(document.querySelectorAll('button')).some((button) => {
                        return button.classList.contains('add_to_cart_button') ||
                        button.classList.contains('ajax_add_to_cart') ||
                        button.classList.contains('single_add_to_cart_button');
                    })
                ) ||
                (
                    document.links && document.links.length > 0 &&
                    Array.from(document.links).some((link) => {
                        return link.classList.contains('add_to_cart_button');
                    })
                )
            ) {
                let preventOriginalFetch = false;

                window.fetch = async function(...args) {
                    // Metform block
                    if (
                        Array.from(document.forms).some((form) => form.classList.contains('metform-form-content')) &&
                        args &&
                        args[0] &&
                        typeof args[0].includes === 'function' &&
                        (args[0].includes('/wp-json/metform/') ||
                            (ctPublicFunctions._rest_url && (() => {
                                try {
                                    return args[0].includes(new URL(ctPublicFunctions._rest_url).pathname + 'metform/');
                                } catch (e) {
                                    return false;
                                }
                            })())
                        )
                    ) {
                        if (args && args[1] && args[1].body) {
                            if (+ctPublic.settings__data__bot_detector_enabled) {
                                args[1].body.append(
                                    'ct_bot_detector_event_token',
                                    apbctLocalStorage.get('bot_detector_event_token'),
                                );
                            } else {
                                args[1].body.append('ct_no_cookie_hidden_field', getNoCookieData());
                            }
                        }
                    }
                    // WP Recipe Maker block
                    if (
                        Array.from(document.forms).some(
                            (form) => form.classList.contains('wprm-user-ratings-modal-stars-container'),
                        ) &&
                        args &&
                        args[0] &&
                        typeof args[0].includes === 'function' &&
                        args[0].includes('/wp-json/wp-recipe-maker/')
                    ) {
                        if (args[1] && args[1].body) {
                            if (typeof args[1].body === 'string') {
                                let bodyObj;
                                try {
                                    bodyObj = JSON.parse(args[1].body);
                                } catch (e) {
                                    bodyObj = {};
                                }
                                if (+ctPublic.settings__data__bot_detector_enabled) {
                                    bodyObj.ct_bot_detector_event_token =
                                        apbctLocalStorage.get('bot_detector_event_token');
                                } else {
                                    bodyObj.ct_no_cookie_hidden_field = getNoCookieData();
                                }
                                args[1].body = JSON.stringify(bodyObj);
                            }
                        }
                    }

                    // WooCommerce add to cart request, like:
                    // /index.php?rest_route=/wc/store/v1/cart/add-item
                    if (args && args[0] &&
                        args[0].includes('/wc/store/v1/cart/add-item') &&
                        args && args[1] && args[1].body
                    ) {
                        if (
                            +ctPublic.settings__data__bot_detector_enabled &&
                            +ctPublic.settings__forms__wc_add_to_cart
                        ) {
                            try {
                                let bodyObj = JSON.parse(args[1].body);
                                if (!bodyObj.hasOwnProperty('ct_bot_detector_event_token')) {
                                    bodyObj.ct_bot_detector_event_token =
                                        apbctLocalStorage.get('bot_detector_event_token');
                                    args[1].body = JSON.stringify(bodyObj);
                                }
                            } catch (e) {
                                return false;
                            }
                        } else {
                            args[1].body.append('ct_no_cookie_hidden_field', getNoCookieData());
                        }
                    }

                    // bitrix24 EXTERNAL form
                    if (+ctPublic.settings__forms__check_external &&
                        args && args[0] &&
                        args[0].includes('bitrix/services/main/ajax.php?action=crm.site.form.fill') &&
                        args[1] && args[1].body && args[1].body instanceof FormData
                    ) {
                        const currentTargetForm = document.querySelector('.b24-form form');
                        let data = {
                            action: 'cleantalk_force_ajax_check',
                        };
                        for (const field of currentTargetForm.elements) {
                            data[field.name] = field.value;
                        }

                        // check form request - wrap in Promise to wait for completion
                        await new Promise((resolve, reject) => {
                            apbct_public_sendAJAX(
                                data,
                                {
                                    async: true,
                                    callback: function( result, data, params, obj ) {
                                        // allowed
                                        if ((result.apbct === undefined && result.data === undefined) ||
                                            (result.apbct !== undefined && ! +result.apbct.blocked)
                                        ) {
                                            preventOriginalFetch = false;
                                        }

                                        // blocked
                                        if ((result.apbct !== undefined && +result.apbct.blocked) ||
                                            (result.data !== undefined && result.data.message !== undefined)
                                        ) {
                                            preventOriginalFetch = true;
                                            new ApbctShowForbidden().parseBlockMessage(result);
                                        }

                                        resolve(result);
                                    },
                                    onErrorCallback: function( error ) {
                                        console.log('AJAX error:', error);
                                        reject(error);
                                    },
                                },
                            );
                        });
                    }

                    if (!preventOriginalFetch) {
                        return defaultFetch.apply(window, args);
                    }
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
                    let sourceSign = {
                        'found': false,
                        'keepUnwrapped': false,
                    };
                    // settings data is string (important!)
                    if ( typeof settings.data === 'string' ) {
                        if (settings.data.indexOf('action=fl_builder_subscribe_form_submit') !== -1) {
                            sourceSign.found = 'fl_builder_subscribe_form_submit';
                        }
                        if (settings.data.indexOf('twt_cc_signup') !== -1) {
                            sourceSign.found = 'twt_cc_signup';
                        }

                        if (settings.data.indexOf('action=mailpoet') !== -1) {
                            sourceSign.found = 'action=mailpoet';
                        }

                        if (
                            settings.data.indexOf('action=user_registration') !== -1 &&
                            settings.data.indexOf('ur_frontend_form_nonce') !== -1
                        ) {
                            sourceSign.found = 'action=user_registration';
                        }

                        if (settings.data.indexOf('action=happyforms_message') !== -1) {
                            sourceSign.found = 'action=happyforms_message';
                        }

                        if (settings.data.indexOf('action=new_activity_comment') !== -1) {
                            sourceSign.found = 'action=new_activity_comment';
                        }
                        if (settings.data.indexOf('action=wwlc_create_user') !== -1) {
                            sourceSign.found = 'action=wwlc_create_user';
                        }
                        if (settings.data.indexOf('action=drplus_signup') !== -1) {
                            sourceSign.found = 'action=drplus_signup';
                            sourceSign.keepUnwrapped = true;
                        }
                        if (settings.data.indexOf('action=bt_cc') !== -1) {
                            sourceSign.found = 'action=bt_cc';
                            sourceSign.keepUnwrapped = true;
                        }
                        if (
                            settings.data.indexOf('action=nf_ajax_submit') !== -1 &&
                            ctPublic.data__cookies_type === 'none'
                        ) {
                            sourceSign.found = 'action=nf_ajax_submit';
                            sourceSign.keepUnwrapped = true;
                        }
                        if (
                            settings.data.indexOf('action=uael_register_user') !== -1 &&
                            ctPublic.data__cookies_type === 'none'
                        ) {
                            sourceSign.found = 'action=uael_register_user';
                            sourceSign.keepUnwrapped = true;
                        }
                    }
                    if ( typeof settings.url === 'string' ) {
                        if (settings.url.indexOf('wc-ajax=add_to_cart') !== -1) {
                            sourceSign.found = 'wc-ajax=add_to_cart';
                        }
                    }

                    if (sourceSign.found !== false) {
                        let eventToken = '';
                        let noCookieData = '';
                        if (+ctPublic.settings__data__bot_detector_enabled) {
                            const token = new ApbctHandler().toolGetEventToken();
                            if (token) {
                                if (sourceSign.keepUnwrapped) {
                                    eventToken = 'ct_bot_detector_event_token=' + token + '&';
                                } else {
                                    eventToken = 'data%5Bct_bot_detector_event_token%5D=' + token + '&';
                                }
                            }
                        } else {
                            noCookieData = getNoCookieData();
                            if (sourceSign.keepUnwrapped) {
                                noCookieData = 'ct_no_cookie_hidden_field=' + noCookieData + '&';
                            } else {
                                noCookieData = 'data%5Bct_no_cookie_hidden_field%5D=' + noCookieData + '&';
                            }
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
            if (
                typeof options !== 'object' ||
                options === null ||
                !options.hasOwnProperty('data') ||
                typeof options.data === 'undefined' ||
                !options.hasOwnProperty('path') ||
                typeof options.path === 'undefined'
            ) {
                return next(options);
            }

            // add to cart
            if (options.data.hasOwnProperty('requests') &&
                options.data.requests.length > 0 &&
                options.data.requests[0].hasOwnProperty('path') &&
                options.data.requests[0].path === '/wc/store/v1/cart/add-item'
            ) {
                if (+ctPublic.settings__data__bot_detector_enabled) {
                    let token = localStorage.getItem('bot_detector_event_token');
                    options.data.requests[0].data.ct_bot_detector_event_token = token;
                } else {
                    if (ctPublic.data__cookies_type === 'none') {
                        options.data.requests[0].data.ct_no_cookie_hidden_field = getNoCookieData();
                    }
                }
            }

            // checkout
            if (options.path.includes('/wc/store/v1/checkout')) {
                if (+ctPublic.settings__data__bot_detector_enabled) {
                    options.data.ct_bot_detector_event_token = localStorage.getItem('bot_detector_event_token');
                } else {
                    if (ctPublic.data__cookies_type === 'none') {
                        options.data.ct_no_cookie_hidden_field = getNoCookieData();
                    }
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
     * Handle search forms middleware
     * @return {void}
     */
    searchFormMiddleware() {
        for (const _form of document.forms) {
            if (
                typeof ctPublic !== 'undefined' &&
                + ctPublic.settings__forms__search_test === 1 &&
                _form.getAttribute('apbct-form-sign') !== null &&
                _form.getAttribute('apbct-form-sign') === 'native_search'
            ) {
                // this handles search forms onsubmit process
                _form.apbctSearchPrevOnsubmit = _form.onsubmit;
                _form.onsubmit = (e) => this.searchFormHandler(e, _form);
            }
        }
    }

    /**
     * Handle search forms
     * @param {SubmitEvent} e
     * @param {object} targetForm
     * @return {void}
     */
    searchFormHandler(e, targetForm) {
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

/**
 * Class for showing forbidden
 */
class ApbctShowForbidden {
    /**
     * Prepare block to intercept AJAX response
     * @return {void}
     */
    prepareBlockForAjaxForms() {
        /**
         * Prepare block message
         * @param {object} xhr
         * @return {void}
         */
        const prepareBlockMessage = function(xhr) {
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
        }.bind(this);

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
                if (response.integration && response.integration === 'NEXForms') {
                    const btn = document.querySelector('form.submit-nex-form button.nex-submit');
                    if (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.5';
                        btn.style.cursor = 'not-allowed';
                        btn.style.pointerEvents = 'none';
                        btn.style.backgroundColor = '#ccc';
                        btn.style.color = '#fff';
                    }
                    const successMessage = document.querySelector('div.nex_success_message');
                    if (successMessage) {
                        successMessage.style.display = 'none';
                    }
                }
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
    if (!+ctPublic.settings__data__bot_detector_enabled) {
        const gatheringData = new ApbctGatheringData();
        gatheringData.setSessionId();
        gatheringData.writeReferrersToSessionStorage();
        gatheringData.setCookiesType();
        gatheringData.startFieldsListening();
        gatheringData.listenAutocomplete();
        gatheringData.gatheringTypoData();
    }
    // Always call initParams to set cookies and parameters
    if (typeof initParams === 'function') {
        try {
            initParams();
        } catch (e) {
            console.log('initParams error:', e);
        }
    }

    setTimeout(function() {
        // Attach data when bot detector is enabled
        if (+ctPublic.settings__data__bot_detector_enabled) {
            const eventTokenTransport = new ApbctEventTokenTransport();
            eventTokenTransport.attachEventTokenToMultipageGravityForms();
            eventTokenTransport.attachEventTokenToWoocommerceGetRequestAddToCart();
        }

        const attachData = new ApbctAttachData();

        // Attach data when bot detector is disabled
        if (!+ctPublic.settings__data__bot_detector_enabled) {
            attachData.attachHiddenFieldsToForms();
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
        handler.searchFormMiddleware();
    }

    handler.catchXmlHttpRequest();
    handler.catchFetchRequest();
    handler.catchJqueryAjax();
    handler.catchWCRestRequestAsMiddleware();

    if (+ctPublic.settings__data__bot_detector_enabled) {
        let botDetectorEventTokenStored = false;
        window.addEventListener('botDetectorEventTokenUpdated', (event) => {
            const botDetectorEventToken = event.detail?.eventToken;
            if ( botDetectorEventToken && ! botDetectorEventTokenStored ) {
                ctSetCookie([
                    ['ct_bot_detector_event_token', botDetectorEventToken],
                ]);
                botDetectorEventTokenStored = true;
                // @ToDo remove this block afret force_alt_cookies removed
                if (typeof ctPublic.force_alt_cookies !== 'undefined' && ctPublic.force_alt_cookies) {
                    ctSetAlternativeCookie(
                        JSON.stringify({'ct_bot_detector_event_token': botDetectorEventToken}),
                        {forceAltCookies: true},
                    );
                }
            }
        });
    }

    if (ctPublic.settings__sfw__anti_crawler && +ctPublic.settings__data__bot_detector_enabled) {
        handler.toolForAntiCrawlerCheckDuringBotDetector();
    }
}

if (ctPublic.data__key_is_ok) {
    if (document.readyState !== 'loading') {
        apbct_ready();
    } else {
        apbct_attach_event_handler(document, 'DOMContentLoaded', apbct_ready);
    }

    apbctLocalStorage.set('ct_checkjs', ctPublic.ct_checkjs_key, true);
    if (ctPublic.data__cookies_type === 'native') {
        ctSetCookie('ct_checkjs', ctPublic.ct_checkjs_key, true);
    }
}

const defaultFetch = window.fetch;
const defaultSend = XMLHttpRequest.prototype.send;

let tokenCheckerIntervalId; // eslint-disable-line no-unused-vars

/**
 * Run cron jobs
 */
new ApbctHandler().cronFormsHandler(2000);
