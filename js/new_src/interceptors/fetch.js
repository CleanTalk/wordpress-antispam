import {apbctLocalStorage} from '../utils/apbctLocalStorage';
import {getNoCookieData, apbctGetBrowserStatePair} from '../functions/requestData';
import {ApbctFetchProxyProtection} from '../functions/fetchProxy/ApbctFetchProxyProtection';

/**
 * Catch fetch request
 * @return {void}
 */
export function catchFetchRequest() {
    const fetchProxyProtection = new ApbctFetchProxyProtection();
    let preventOriginalFetch = false;

    // Flag to prevent recursive calls
    let isInternalCall = false;

    // Save original fetch in closure
    const originalFetch = window.fetch;

    /**
     * Select key/value pair depending on botDetectorEnabled flag
     * @param {bool} botDetectorEnabled
     * @return {{key: string, value: string}|false} False on empty gained data.
     */
    const selectFieldsData = function(botDetectorEnabled) {
        const result = {
            'key': null,
            'value': null,
        };
        if (botDetectorEnabled) {
            result.key = 'ct_bot_detector_event_token';
            result.value = apbctLocalStorage.get('bot_detector_event_token');
        } else {
            result.key = 'ct_no_cookie_hidden_field';
            result.value = getNoCookieData();
        }
        return result.key && result.value ? result : false;
    };

    /**
     * Attach fields to fetch request body
     * @param {string|FormData} body Fetch request data body.
     * @param {object|bool} fieldPair Key value to inject.
     * @return {string|FormData} Modified body.
     */
    const attachFieldsToBody = function(body, fieldPair = false) {
        const fieldPairs = [fieldPair, apbctGetBrowserStatePair()];

        for (const pair of fieldPairs) {
            body = attachFieldPairToBody(body, pair);
        }

        return body;
    };

    /**
     * Attach a single key/value pair to fetch request body
     * @param {string|FormData} body Fetch request data body.
     * @param {object|bool} fieldPair Key value to inject.
     * @return {string|FormData} Modified body.
     */
    const attachFieldPairToBody = function(body, fieldPair = false) {
        if (fieldPair) {
            if (body instanceof FormData || typeof body.append === 'function') {
                body.append(fieldPair.key, fieldPair.value);
            } else {
                try {
                    let bodyObj = JSON.parse(body);
                    if (!bodyObj.hasOwnProperty(fieldPair.key)) {
                        bodyObj[fieldPair.key] = fieldPair.value;
                        body = JSON.stringify(bodyObj);
                    }
                } catch (e) {
                    // If body is not valid JSON, leave it unchanged
                }
            }
        }
        return body;
    };

    /**
     * Check if URL should be skipped from interception
     * @param {string} url
     * @return {boolean}
     */
    const shouldSkipUrl = function(url) {
        if (!url || typeof url !== 'string') return false;

        // Whitelist of URLs we should NOT intercept
        const skipUrls = [
            'google.com/recaptcha',
            'gstatic.com/recaptcha',
            'recaptcha.google.com',
            'recaptcha.net',
            'www.google.com/recaptcha',
            'apis.google.com',
            'paypal.com',
            'api.paypal.com',
            'www.paypal.com',
            'js.stripe.com',
            'api.stripe.com',
            'checkout.stripe.com',
            'api.doboard.com',
        ];

        return skipUrls.some((skipUrl) => url.includes(skipUrl));
    };

    // Logic for iframes that use common general window fetch

    /**
     * Run the matching external form handler(s), if any, and return
     * whether the original fetch request should be prevented.
     * @param {string} url
     * @param {Array} args Original fetch() arguments.
     * @return {Promise<boolean>}
     */
    const processIframeCommonFetch = async function(url, args) {
        if (!+ctPublic.settings__forms__check_external) {
            return false;
        }

        for (const handler of iframeCommonFetchHandlers) {
            if (handler.matches(url, args)) {
                const form = handler.getForm();
                const blocked = await runIframeCommonFetchCheck(form);
                if (blocked) {
                    return true;
                }
            }
        }

        return false;
    };

    /**
     * Config-driven list of external form providers.
     * Adding a new provider only requires a new entry here — no need
     * to duplicate the Promise/AJAX/blocked-check boilerplate above.
     */
    const iframeCommonFetchHandlers = [
        {
            name: 'bitrix24',
            matches: (url, args) =>
                document.querySelector('.b24-form') &&
                url.includes('bitrix/services/main/ajax.php?action=crm.site.form.fill') &&
                args[1].body instanceof FormData,
            getForm: () => document.querySelector('.b24-form form'),
        },
        {
            name: 'sender',
            matches: (url, args) =>
                url.includes('stats.sender.net/forms') &&
                typeof args[1].body === 'string',
            getForm: () => {
                const iframes = document.querySelectorAll('iframe');
                for (let i = 0; i < iframes.length; i++) {
                    try {
                        // contentDocument access can throw on cross-origin iframes
                        const doc = iframes[i].contentDocument;
                        if (doc && doc.querySelectorAll('#sender-form-content').length > 0) {
                            return doc.querySelector('#sender-form-content');
                        }
                    } catch (e) {
                        // Ignore inaccessible (cross-origin) iframes
                    }
                }
                return null;
            },
        },
    ];

    /**
     * Run the CleanTalk ajax check for a given external form and decide
     * whether the original fetch request should be blocked.
     * Centralizes the isInternalCall flag handling, the sendAJAX call,
     * and the "blocked" detection so providers don't duplicate this logic.
     * @param {HTMLFormElement|null} form
     * @return {Promise<boolean>} True if the request should be blocked.
     */
    const runIframeCommonFetchCheck = async function(form) {
        if (!form) {
            return false;
        }

        const data = collectIframeFormFields(form);

        // Set internal call flag before making our own AJAX request
        isInternalCall = true;
        try {
            // Check form request - wrap in Promise to wait for completion
            const result = await new Promise((resolve, reject) => {
                apbct_public_sendAJAX(
                    data,
                    {
                        async: true,
                        callback: resolve,
                        onErrorCallback: reject,
                    },
                );
            });

            // blocked
            const isBlocked =
                (result.apbct !== undefined && +result.apbct.blocked) ||
                (result.data !== undefined && result.data.message !== undefined);

            if (isBlocked) {
                window.apbctShowBlockMessage(result);
            }

            return isBlocked;
        } catch (e) {
            return false;
        } finally {
            isInternalCall = false;
        }
    };

    // === Shared logic for external form providers (Bitrix24, sender.com, ...) ===

    /**
     * Collect form field values into a payload for the ajax check request.
     * Shared by all external form providers so each handler only needs
     * to know how to locate its own form element.
     * @param {HTMLFormElement} form
     * @return {object}
     */
    const collectIframeFormFields = function(form) {
        const data = {
            action: 'cleantalk_force_ajax_check',
        };

        for (const field of form.elements) {
            if (field.name) {
                data[field.name] = field.value;
            }
        }

        if (+ctPublic.bot_detector_enabled) {
            data['ct_bot_detector_event_token'] = apbctLocalStorage.get('bot_detector_event_token');
        } else {
            data['ct_no_cookie_hidden_field'] = getNoCookieData();
        }

        return data;
    };

    // MAIN FETCH INTERCEPT LOGIC - OVERRIDE WINDOW.FETCH

    window.fetch = async function(...args) {
        // Prevent recursion - if this is our internal call, pass through without processing
        if (isInternalCall) {
            return originalFetch.apply(this, args);
        }

        try {
            // Get URL from args
            const url = typeof args[0] === 'string' ? args[0] : (args[0]?.url || '');

            // Skip critical services (reCAPTCHA, PayPal, Stripe, etc.)
            if (shouldSkipUrl(url)) {
                return originalFetch.apply(this, args);
            }

            // Reset flag for each new request
            preventOriginalFetch = false;

            // If no data to process, exit early
            if (!args || !args[0] || !args[1] || !args[1].body) {
                return originalFetch.apply(this, args);
            }

            // === Apbct FetchProxy forms ===
            const fetchProxyResult = await fetchProxyProtection.processFetch(args);
            if (fetchProxyResult === true) {
                // Reject so form's error handler runs and stops the loading spinner
                return Promise.reject(new Error('Forbidden'));
            }

            // === Metform ===
            if (
                document.querySelectorAll('form.metform-form-content').length > 0 &&
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
                try {
                    args[1].body = attachFieldsToBody(
                        args[1].body,
                        selectFieldsData(+ctPublic.bot_detector_enabled),
                    );
                } catch (e) {
                    // Continue even if error
                }
            }

            // === WP Recipe Maker ===
            if (
                document.querySelectorAll('form.wprm-user-ratings-modal-stars-container').length > 0 &&
                typeof args[0].includes === 'function' &&
                args[0].includes('/wp-json/wp-recipe-maker/')
            ) {
                try {
                    args[1].body = attachFieldsToBody(
                        args[1].body,
                        selectFieldsData(+ctPublic.bot_detector_enabled),
                    );
                } catch (e) {
                    // Continue even if error
                }
            }

            // === BuddyNext register===
            if (
                document.querySelectorAll('form.bn-auth-form').length > 0 &&
                typeof args[0].includes === 'function' &&
                args[0].includes('/wp-json/buddynext/v1/auth/register')
            ) {
                try {
                    args[1].body = attachFieldsToBody(
                        args[1].body,
                        selectFieldsData(+ctPublic.bot_detector_enabled),
                    );
                } catch (e) {
                    // Continue even if error
                }
            }

            // === WooCommerce add to cart (direct add-item URL) ===
            if (
                (
                    document.querySelectorAll(
                        'button.add_to_cart_button, button.ajax_add_to_cart, button.single_add_to_cart_button',
                    ).length > 0 ||
                    document.querySelectorAll('a.add_to_cart_button').length > 0
                ) &&
                args[0].includes('/wc/store/v1/cart/add-item')
            ) {
                try {
                    if (+ctPublic.settings__forms__wc_add_to_cart) {
                        args[1].body = attachFieldsToBody(
                            args[1].body,
                            selectFieldsData(+ctPublic.bot_detector_enabled),
                        );
                    }
                } catch (e) {
                    // Continue even if error
                }
            }

            // === WooCommerce add to cart (batch API - /wc/store/v1/batch) ===
            if (
                +ctPublic.settings__forms__wc_add_to_cart &&
                (
                    document.querySelectorAll(
                        'button.add_to_cart_button, button.ajax_add_to_cart, button.single_add_to_cart_button',
                    ).length > 0 ||
                    document.querySelectorAll('a.add_to_cart_button').length > 0
                ) &&
                args[0].includes('/wc/store/v1/batch') &&
                typeof args[1].body === 'string'
            ) {
                try {
                    const batchPayload = JSON.parse(args[1].body);
                    if (batchPayload.requests && Array.isArray(batchPayload.requests)) {
                        const fieldPair = selectFieldsData(+ctPublic.bot_detector_enabled);
                        const browserState = apbctGetBrowserStatePair();
                        for (const req of batchPayload.requests) {
                            const isAddItem = req.path === '/wc/store/v1/cart/add-item';
                            if (isAddItem && req.body && fieldPair && fieldPair.key) {
                                req.body[fieldPair.key] = fieldPair.value;
                            }
                            if (isAddItem && req.body && browserState) {
                                req.body[browserState.key] = browserState.value;
                            }
                        }
                        args[1].body = JSON.stringify(batchPayload);
                    }
                } catch (e) {
                    // Continue even if error
                }
            }

            // === Bitrix24 / sender.com external forms (config-driven, see externalFormHandlers) ===
            preventOriginalFetch = await processIframeCommonFetch(url, args);

            // Return original fetch result if not prevented
            if (!preventOriginalFetch) {
                return originalFetch.apply(this, args);
            }
        } catch (error) {
            // In case of any error in our interceptor, call original fetch
            return originalFetch.apply(this, args);
        }
    };
}
