import {apbctLocalStorage} from '../utils/apbctLocalStorage';
import {getEventToken} from '../utils/eventToken';
import {getNoCookieData, apbctGetBrowserStatePair} from '../functions/requestData';
import {ApbctVisibleFieldsExtractor} from '../functions/visibleFieldsExtractors';

/**
 * Add CleanTalk data to jQuery ajax requests.
 * @return {void}
 */
export function catchJqueryAjaxRequest() {
    new ApbctJqueryAjax().catchJqueryAjax();
}

/**
 * Class for handling jQuery ajax requests
 */
class ApbctJqueryAjax {
    /**
     * Prepare jQuery.ajaxPrefilter to add CleanTalk data to jQuery ajax requests.
     * Notes:
     * - ajaxSetup.beforeSend is not used: a request can supply its own beforeSend and drop the default.
     * - ajaxPrefilter runs for every jQuery ajax call after options are merged, so injection is guaranteed.
     * - Form signs are checked on the request data/url at send time.
     * - Any sign of the form HTML of the caller is insignificant in this process.
     * - This is the only place where we can found hard dependency on jQuery, if the form use it - the script
     * will work independing if jQuery is loaded by CleanTalk or not
     * @return {void}
     */
    catchJqueryAjax() {
        if ( typeof jQuery !== 'undefined' && typeof jQuery.ajaxPrefilter === 'function' ) {
            jQuery.ajaxPrefilter(function(options, originalOptions, jqXHR) {
                const handler = new ApbctJqueryAjax();
                const sourceSign = handler.searchSignsForJQAjaxInjection(options, originalOptions);
                if (sourceSign.found !== false) {
                    options.data = handler.injectCleantalkDataToJQAjax(
                        sourceSign,
                        handler.getJQAjaxPayloadForInject(options, originalOptions),
                        options,
                    );
                }
            });
        }
    }

    /**
     * jQuery.param() on URLSearchParams/FormData can collapse options.data to "".
     * Sign search already falls back to originalOptions; inject must use that payload too.
     * @param {object} options
     * @param {object=} originalOptions
     * @return {*}
     */
    getJQAjaxPayloadForInject(options, originalOptions) {
        const current = options && options.data;
        const original = originalOptions && originalOptions.data;
        if ( this.jqAjaxPayloadWasCollapsed(current, original) ) {
            return original;
        }
        return current;
    }

    /**
     * @param {*} current
     * @param {*} original
     * @return {boolean}
     */
    jqAjaxPayloadWasCollapsed(current, original) {
        if ( original === null || typeof original === 'undefined' ) {
            return false;
        }
        if ( current === original ) {
            return false;
        }
        const currentEmpty = current === '' || current === null || typeof current === 'undefined';
        if ( !currentEmpty ) {
            return false;
        }
        if ( typeof original === 'string' ) {
            return original !== '';
        }
        return this.isJQAjaxFormData(original) ||
            this.isJQAjaxURLSearchParams(original) ||
            this.isJQAjaxPlainObjectOrArray(original);
    }

    /**
     * Normalize jQuery ajax data to a query string for sign search.
     * Objects and serializeArray are read structurally so search never calls jQuery.param.
     * Also reads known sign fields from the URL (IE11 FormData cannot be enumerated).
     * @param {object} ajaxObject Ajax options object.
     * @return {string}
     */
    getJQAjaxDataAsString(ajaxObject) {
        if ( !ajaxObject || typeof ajaxObject !== 'object' ) {
            return '';
        }
        let fromData = '';
        if ( typeof ajaxObject.data === 'string' ) {
            fromData = ajaxObject.data;
        } else if ( this.isJQAjaxPlainObjectOrArray(ajaxObject.data) ) {
            fromData = this.getStructuredJQAjaxSignString(ajaxObject.data);
        } else if ( this.isJQAjaxURLSearchParams(ajaxObject.data) ) {
            fromData = this.collectKnownSignStringFromKeyValueBag(ajaxObject.data);
        } else if ( this.isJQAjaxFormData(ajaxObject.data) ) {
            fromData = this.collectKnownSignStringFromKeyValueBag(ajaxObject.data);
        }
        // Native IE11 FormData is write-only (no get/forEach/entries). Signs then come from the URL.
        const fromUrl = this.collectKnownSignStringFromUrl(ajaxObject.url);
        if ( fromData && fromUrl ) {
            return fromData + '&' + fromUrl;
        }
        return fromData || fromUrl;
    }

    /**
     * Build a sign string from top-level fields without serializing the whole payload.
     * @param {object|Array} data
     * @return {string}
     */
    getStructuredJQAjaxSignString(data) {
        if ( Object.prototype.toString.call(data) === '[object Array]' ) {
            return this.getSerializeArraySignString(data);
        }
        const parts = [];
        const action = this.getPlainObjectSignField(data, 'action');
        if ( typeof action === 'string' && action !== '' ) {
            parts.push('action=' + action);
        }
        const nonce = this.getPlainObjectSignField(data, 'ur_frontend_form_nonce');
        if ( nonce !== null ) {
            parts.push('ur_frontend_form_nonce=' + nonce);
        }
        if ( this.plainObjectHasTwtCcSignup(data) ) {
            parts.push('twt_cc_signup=1');
        }
        return parts.join('&');
    }

    /**
     * Read action/nonce from top-level, data[key], or nested data.key.
     * @param {object} data
     * @param {string} key
     * @return {string|number|null}
     */
    getPlainObjectSignField(data, key) {
        if ( !data || typeof data !== 'object' ) {
            return null;
        }
        if ( this.isJQAjaxScalarSignValue(data[key]) ) {
            return data[key];
        }
        const bracketKey = 'data[' + key + ']';
        if ( this.isJQAjaxScalarSignValue(data[bracketKey]) ) {
            return data[bracketKey];
        }
        if (
            data.data &&
            typeof data.data === 'object' &&
            Object.prototype.toString.call(data.data) !== '[object Array]' &&
            this.isJQAjaxScalarSignValue(data.data[key])
        ) {
            return data.data[key];
        }
        return null;
    }

    /**
     * @param {*} value
     * @return {boolean}
     */
    isJQAjaxScalarSignValue(value) {
        return typeof value === 'string' || typeof value === 'number';
    }

    /**
     * @param {object} data
     * @param {number=} depth
     * @return {boolean}
     */
    plainObjectHasTwtCcSignup(data, depth) {
        if ( !data || typeof data !== 'object' ) {
            return false;
        }
        if ( typeof depth === 'undefined' ) {
            depth = 0;
        }
        if ( depth > 2 ) {
            return false;
        }
        for ( const key in data ) {
            if ( !Object.prototype.hasOwnProperty.call(data, key) ) {
                continue;
            }
            const normalizedKey = this.unwrapJQAjaxFormDataKey(key);
            if ( normalizedKey === 'twt_cc_signup' || key.indexOf('twt_cc_signup') !== -1 ) {
                return true;
            }
            const value = data[key];
            if ( typeof value === 'string' && value.indexOf('twt_cc_signup') !== -1 ) {
                return true;
            }
            if (
                value &&
                typeof value === 'object' &&
                Object.prototype.toString.call(value) !== '[object Array]'
            ) {
                if ( this.plainObjectHasTwtCcSignup(value, depth + 1) ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param {Array} entries
     * @return {string}
     */
    getSerializeArraySignString(entries) {
        const parts = [];
        for ( let i = 0; i < entries.length; i++ ) {
            const entry = entries[i];
            if ( !entry || typeof entry !== 'object' || typeof entry.name !== 'string' ) {
                continue;
            }
            const value = entry.value;
            const normalizedKey = this.unwrapJQAjaxFormDataKey(entry.name);
            if ( this.isJQAjaxKnownSignField(normalizedKey, value) ) {
                parts.push(normalizedKey + '=' + value);
            }
        }
        return parts.join('&');
    }

    /**
     * True for serializeArray payloads, including an empty array.
     * @param {Array} data
     * @return {boolean}
     */
    isJQAjaxSerializeArray(data) {
        if ( Object.prototype.toString.call(data) !== '[object Array]' ) {
            return false;
        }
        for ( let i = 0; i < data.length; i++ ) {
            const entry = data[i];
            if ( !entry || typeof entry !== 'object' || typeof entry.name !== 'string' ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Cross-realm URLSearchParams (instanceof fails across windows).
     * @param {*} data
     * @return {boolean}
     */
    isJQAjaxURLSearchParams(data) {
        if ( !data || typeof data !== 'object' || typeof data.append !== 'function' ) {
            return false;
        }
        if ( this.isJQAjaxPlainObjectOrArray(data) ) {
            return false;
        }
        const tag = Object.prototype.toString.call(data);
        if ( tag === '[object URLSearchParams]' ) {
            return true;
        }
        if ( tag === '[object FormData]' ) {
            return false;
        }
        return typeof data.sort === 'function' && typeof data.get === 'function';
    }

    /**
     * Cross-realm FormData, including common polyfills.
     * @param {*} data
     * @return {boolean}
     */
    isJQAjaxFormData(data) {
        if ( !data || typeof data !== 'object' || typeof data.append !== 'function' ) {
            return false;
        }
        if ( this.isJQAjaxPlainObjectOrArray(data) || this.isJQAjaxURLSearchParams(data) ) {
            return false;
        }
        const tag = Object.prototype.toString.call(data);
        if ( tag === '[object FormData]' ) {
            return true;
        }
        if ( tag === '[object Headers]' || typeof data.getSetCookie === 'function' ) {
            return false;
        }
        return typeof data.forEach === 'function' ||
            typeof data.get === 'function' ||
            typeof data.entries === 'function';
    }

    /**
     * Collect only known sign fields from FormData / URLSearchParams.
     * Unwrap data[action] so later action=... checks match.
     * @param {FormData|URLSearchParams} bag
     * @return {string}
     */
    collectKnownSignStringFromKeyValueBag(bag) {
        const knownKeys = this.getJQAjaxKnownSignKeys();
        const unwrapKey = this.unwrapJQAjaxFormDataKey;
        const parts = [];
        const collected = this.forEachJQAjaxKeyValueBag(bag, function(value, key) {
            if ( typeof value !== 'string' && typeof value !== 'number' ) {
                return;
            }
            const valueAsString = String(value);
            const normalizedKey = unwrapKey(key);
            if (
                knownKeys[normalizedKey] ||
                valueAsString.indexOf('twt_cc_signup') !== -1 ||
                normalizedKey.indexOf('twt_cc_signup') !== -1
            ) {
                parts.push(normalizedKey + '=' + valueAsString);
            }
        });
        if ( collected ) {
            return parts.join('&');
        }
        if ( typeof bag.get !== 'function' ) {
            return '';
        }
        const keysToProbe = [
            'action',
            'ur_frontend_form_nonce',
            'twt_cc_signup',
        ];
        const getParts = [];
        for ( let i = 0; i < keysToProbe.length; i++ ) {
            const key = keysToProbe[i];
            const value = this.getFormDataScalarValue(bag, [key, 'data[' + key + ']']);
            if ( value !== null ) {
                getParts.push(key + '=' + value);
            }
        }
        return getParts.join('&');
    }

    /**
     * Known sign fields from a request URL query. IE11-safe (no URLSearchParams).
     * @param {string} url
     * @return {string}
     */
    collectKnownSignStringFromUrl(url) {
        if ( typeof url !== 'string' || url === '' ) {
            return '';
        }
        const queryIndex = url.indexOf('?');
        if ( queryIndex === -1 || queryIndex === url.length - 1 ) {
            return '';
        }
        const hashIndex = url.indexOf('#', queryIndex);
        const query = hashIndex === -1 ?
            url.slice(queryIndex + 1) :
            url.slice(queryIndex + 1, hashIndex);
        const knownKeys = this.getJQAjaxKnownSignKeys();
        const unwrapKey = this.unwrapJQAjaxFormDataKey;
        const parts = [];
        const pairs = query.split('&');
        for ( let i = 0; i < pairs.length; i++ ) {
            const pair = pairs[i];
            if ( !pair ) {
                continue;
            }
            const eq = pair.indexOf('=');
            let rawKey = eq === -1 ? pair : pair.slice(0, eq);
            let rawValue = eq === -1 ? '' : pair.slice(eq + 1);
            try {
                rawKey = decodeURIComponent(rawKey.replace(/\+/g, ' '));
                rawValue = decodeURIComponent(rawValue.replace(/\+/g, ' '));
            } catch (e) {
                continue;
            }
            const normalizedKey = unwrapKey(rawKey);
            if (
                knownKeys[normalizedKey] ||
                rawValue.indexOf('twt_cc_signup') !== -1 ||
                normalizedKey.indexOf('twt_cc_signup') !== -1
            ) {
                parts.push(normalizedKey + '=' + rawValue);
            }
        }
        return parts.join('&');
    }

    /**
     * Walk FormData/URLSearchParams via forEach, then entries() for polyfills that only have an iterator.
     * @param {FormData|URLSearchParams} bag
     * @param {Function} callback
     * @return {boolean}
     */
    forEachJQAjaxKeyValueBag(bag, callback) {
        if ( typeof bag.forEach === 'function' ) {
            try {
                bag.forEach(callback);
                return true;
            } catch (e) {
                // Fall through to entries().
            }
        }
        if ( typeof bag.entries !== 'function' ) {
            return false;
        }
        try {
            const iterator = bag.entries();
            if ( !iterator || typeof iterator.next !== 'function' ) {
                return false;
            }
            let step = iterator.next();
            while ( !step.done ) {
                const pair = step.value;
                if ( pair ) {
                    callback(pair[1], pair[0]);
                }
                step = iterator.next();
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    /**
     * @return {Object}
     */
    getJQAjaxKnownSignKeys() {
        const knownKeys = Object.create(null);
        knownKeys.action = true;
        knownKeys.ur_frontend_form_nonce = true;
        knownKeys.twt_cc_signup = true;
        return knownKeys;
    }

    /**
     * @param {string} normalizedKey
     * @param {*} value
     * @return {boolean}
     */
    isJQAjaxKnownSignField(normalizedKey, value) {
        if (
            normalizedKey === 'action' ||
            normalizedKey === 'ur_frontend_form_nonce' ||
            normalizedKey === 'twt_cc_signup' ||
            normalizedKey.indexOf('twt_cc_signup') !== -1
        ) {
            return true;
        }
        return typeof value === 'string' && value.indexOf('twt_cc_signup') !== -1;
    }

    /**
     * Treat data[action] as action for WP-style payloads.
     * @param {string} key
     * @return {string}
     */
    unwrapJQAjaxFormDataKey(key) {
        if ( typeof key !== 'string' ) {
            return '';
        }
        if ( key.length > 6 && key.indexOf('data[') === 0 && key.charAt(key.length - 1) === ']' ) {
            return key.slice(5, -1);
        }
        return key;
    }

    /**
     * @param {FormData} formData
     * @param {Array.<string>} keys
     * @return {string|number|null}
     */
    getFormDataScalarValue(formData, keys) {
        for ( let i = 0; i < keys.length; i++ ) {
            const value = formData.get(keys[i]);
            if ( value !== null && (typeof value === 'string' || typeof value === 'number') ) {
                return value;
            }
        }
        return null;
    }

    /**
     * True for arrays and plain objects that jQuery.param can serialize safely.
     * @param {*} data
     * @return {boolean}
     */
    isJQAjaxPlainObjectOrArray(data) {
        if ( data === null || typeof data !== 'object' ) {
            return false;
        }
        if ( Object.prototype.toString.call(data) === '[object Array]' ) {
            return true;
        }
        if ( typeof jQuery !== 'undefined' && typeof jQuery.isPlainObject === 'function' ) {
            return jQuery.isPlainObject(data);
        }
        const proto = Object.getPrototypeOf(data);
        return proto === Object.prototype || proto === null;
    }

    /**
     * Search for sign within AJAX data to do inject CleanTalk data.
     * @param {object} ajaxObject Ajax object.
     * @param {object=} originalOptions Original jQuery ajax options before merge.
     * @return {{found: boolean, keepUnwrapped: boolean, attachVisibleFieldsData: boolean}}
     */
    searchSignsForJQAjaxInjection(ajaxObject, originalOptions) {
        let sourceSign = {
            'found': false,
            'keepUnwrapped': false,
            'attachVisibleFieldsData': false,
        };
        let dataString = this.getJQAjaxDataAsString(ajaxObject);
        if ( !dataString && originalOptions && originalOptions !== ajaxObject ) {
            dataString = this.getJQAjaxDataAsString(originalOptions);
        }

        if ( dataString.indexOf('action=fl_builder_subscribe_form_submit') !== -1 ) {
            sourceSign.found = 'fl_builder_subscribe_form_submit';
        }
        if ( dataString.indexOf('twt_cc_signup') !== -1 ) {
            sourceSign.found = 'twt_cc_signup';
        }
        if ( dataString.indexOf('action=mailpoet') !== -1 ) {
            sourceSign.found = 'action=mailpoet';
            sourceSign.attachVisibleFieldsData = true;
        }
        if (
            dataString.indexOf('action=user_registration') !== -1 &&
            dataString.indexOf('ur_frontend_form_nonce') !== -1
        ) {
            sourceSign.found = 'action=user_registration';
        }
        if ( dataString.indexOf('action=happyforms_message') !== -1 ) {
            sourceSign.found = 'action=happyforms_message';
        }
        if ( dataString.indexOf('action=new_activity_comment') !== -1 ) {
            sourceSign.found = 'action=new_activity_comment';
        }
        if ( dataString.indexOf('action=wwlc_create_user') !== -1 ) {
            sourceSign.found = 'action=wwlc_create_user';
        }
        if ( dataString.indexOf('action=WPBC_AJX_BOOKING__CREATE') !== -1 ) {
            sourceSign.found = 'action=WPBC_AJX_BOOKING__CREATE';
            sourceSign.keepUnwrapped = true;
            sourceSign.attachVisibleFieldsData = true;
        }
        if ( dataString.indexOf('action=drplus_signup') !== -1 ) {
            sourceSign.found = 'action=drplus_signup';
            sourceSign.keepUnwrapped = true;
        }
        if ( dataString.indexOf('action=bt_cc') !== -1 ) {
            sourceSign.found = 'action=bt_cc';
            sourceSign.keepUnwrapped = true;
        }
        if ( dataString.indexOf('action=wpr_form_builder_email') !== -1 ) {
            sourceSign.found = 'action=wpr_form_builder_email';
            sourceSign.keepUnwrapped = true;
        }
        if ( dataString.indexOf('action=nf_ajax_submit') !== -1 ) {
            sourceSign.found = 'action=nf_ajax_submit';
            sourceSign.keepUnwrapped = true;
            sourceSign.attachVisibleFieldsData = true;
        }
        if (
            dataString.indexOf('action=uael_register_user') !== -1 &&
            ctPublic.data__cookies_type === 'none'
        ) {
            sourceSign.found = 'action=uael_register_user';
            sourceSign.keepUnwrapped = true;
        }
        if ( dataString.indexOf('action=SQBSubmitQuizAjax') !== -1 ) {
            sourceSign.found = 'action=SQBSubmitQuizAjax';
            sourceSign.keepUnwrapped = true;
        }
        if ( dataString.indexOf('action=user_registration_user_form_submit') !== -1 ) {
            sourceSign.found = 'action=user_registration_user_form_submit';
            sourceSign.keepUnwrapped = true;
            sourceSign.attachVisibleFieldsData = true;
        }
        if ( dataString.indexOf('action=bloom_subscribe') !== -1 ) {
            sourceSign.found = 'action=bloom_subscribe';
            sourceSign.keepUnwrapped = true;
        }
        if (
            this.isJQAjaxFormData(ajaxObject.data) &&
            typeof ajaxObject.data.get === 'function' &&
            ajaxObject.data.get('action') === 'pafe_ajax_form_builder'
        ) {
            sourceSign.found = 'action=pafe_ajax_form_builder';
            sourceSign.keepUnwrapped = true;
        } else if ( dataString.indexOf('action=pafe_ajax_form_builder') !== -1 ) {
            sourceSign.found = 'action=pafe_ajax_form_builder';
            sourceSign.keepUnwrapped = true;
        }

        // WooCommerce Stripe UPE confirms the card on Add payment method before the form is posted.
        // Keep the token unwrapped: PHP reads the top-level POST field.
        if ( dataString.indexOf('action=wc_stripe_create_and_confirm_setup_intent') !== -1 ) {
            sourceSign.found = 'action=wc_stripe_create_and_confirm_setup_intent';
            sourceSign.keepUnwrapped = true;
        }

        // woocommerce add to cart is based on URL
        if ( typeof ajaxObject.url === 'string' && ajaxObject.url.indexOf('wc-ajax=add_to_cart') !== -1 ) {
            sourceSign.found = 'wc-ajax=add_to_cart';
            // GET/HEAD: PHP reads Get::get('ct_bot_detector_event_token'), not data[...].
            if ( this.isJQAjaxNoContentMethod(ajaxObject) ) {
                sourceSign.keepUnwrapped = true;
            }
        }

        return sourceSign;
    }

    /**
     * Inject CleanTalk data into jQuery ajax payload of any supported type.
     * @param {object} sourceSign
     * @param {*} ajaxData
     * @param {object=} ajaxOptions jQuery ajax options (traditional, processData, contentType).
     * Keep URLSearchParams/objects when processData or contentType is false.
     * URLSearchParams must also disable processData, or jQuery.param() later yields "".
     * GET/HEAD never keep the original type: jQuery only puts string data on the URL.
     * @return {*}
     */
    injectCleantalkDataToJQAjax(sourceSign, ajaxData, ajaxOptions) {
        const traditional = ajaxOptions && ajaxOptions.traditional;
        const keepOriginalType = !this.isJQAjaxNoContentMethod(ajaxOptions) && !!(ajaxOptions && (
            ajaxOptions.processData === false ||
            ajaxOptions.contentType === false
        ));

        if ( ajaxData === null || typeof ajaxData === 'undefined' ) {
            return this.injectCleantalkDataToJQAjaxString(sourceSign, '');
        }
        if ( typeof ajaxData === 'string' ) {
            return this.injectCleantalkDataToJQAjaxString(sourceSign, ajaxData);
        }
        if ( this.isJQAjaxPlainObjectOrArray(ajaxData) ) {
            if ( keepOriginalType ) {
                if ( Object.prototype.toString.call(ajaxData) === '[object Array]' ) {
                    if ( this.isJQAjaxSerializeArray(ajaxData) ) {
                        return this.injectCleantalkDataToJQAjaxSerializeArray(sourceSign, ajaxData);
                    }
                    return ajaxData;
                }
                return this.injectCleantalkDataToJQAjaxPlainObject(sourceSign, ajaxData);
            }
            try {
                if ( typeof jQuery !== 'undefined' && typeof jQuery.param === 'function' ) {
                    return this.injectCleantalkDataToJQAjaxString(
                        sourceSign,
                        jQuery.param(ajaxData, traditional),
                    );
                }
            } catch (e) {
                return ajaxData;
            }
            return ajaxData;
        }
        if ( this.isJQAjaxURLSearchParams(ajaxData) ) {
            if ( keepOriginalType ) {
                this.preserveJQAjaxURLSearchParamsOptions(ajaxOptions);
                return this.injectCleantalkDataToJQAjaxKeyValue(
                    sourceSign,
                    this.cloneURLSearchParams(ajaxData),
                );
            }
            return this.injectCleantalkDataToJQAjaxString(sourceSign, ajaxData.toString());
        }
        if ( this.isJQAjaxFormData(ajaxData) ) {
            if ( this.isJQAjaxNoContentMethod(ajaxOptions) ) {
                return this.injectCleantalkDataToJQAjaxString(
                    sourceSign,
                    this.serializeJQAjaxKeyValueBag(ajaxData),
                );
            }
            this.preserveJQAjaxFormDataOptions(ajaxOptions);
            return this.injectCleantalkDataToJQAjaxFormData(
                sourceSign,
                this.cloneFormData(ajaxData),
            );
        }
        return ajaxData;
    }

    /**
     * GET/HEAD have no body. Missing type is GET (jQuery default).
     * @param {object=} ajaxOptions
     * @return {boolean}
     */
    isJQAjaxNoContentMethod(ajaxOptions) {
        if ( !ajaxOptions || typeof ajaxOptions !== 'object' ) {
            return false;
        }
        const type = ajaxOptions.type || ajaxOptions.method;
        if ( typeof type !== 'string' || type === '' ) {
            return true;
        }
        const method = type.toUpperCase();
        return method === 'GET' || method === 'HEAD';
    }

    /**
     * Collect CleanTalk fields to append to a key/value payload.
     * @param {object} sourceSign
     * @return {Array.<Array.<string>>}
     */
    getCleantalkJQAjaxFieldPairs(sourceSign) {
        const pairs = [];
        const wrapKey = function(key) {
            return sourceSign.keepUnwrapped ? key : 'data[' + key + ']';
        };

        if (
            +ctPublic.bot_detector_enabled &&
            apbctLocalStorage.get('bot_detector_event_token')
        ) {
            const token = getEventToken();
            if ( token ) {
                pairs.push([wrapKey('ct_bot_detector_event_token'), token]);
            }
        } else {
            const noCookieData = getNoCookieData();
            if ( noCookieData ) {
                pairs.push([wrapKey('ct_no_cookie_hidden_field'), noCookieData]);
            }
        }

        const browserState = apbctGetBrowserStatePair();
        if ( browserState ) {
            const browserStateKey = sourceSign.keepUnwrapped ?
                browserState.key :
                'data[' + browserState.key + ']';
            pairs.push([browserStateKey, browserState.value]);
        }

        return pairs;
    }

    /**
     * jQuery.param(FormData) after the prefilter yields "". Keep the body as FormData.
     * @param {object=} ajaxOptions
     * @return {void}
     */
    preserveJQAjaxFormDataOptions(ajaxOptions) {
        if ( !ajaxOptions || typeof ajaxOptions !== 'object' ) {
            return;
        }
        ajaxOptions.processData = false;
        ajaxOptions.contentType = false;
    }

    /**
     * jQuery.param(URLSearchParams) after the prefilter yields "". Keep the body as URLSearchParams.
     * @param {object=} ajaxOptions
     * @return {void}
     */
    preserveJQAjaxURLSearchParamsOptions(ajaxOptions) {
        if ( !ajaxOptions || typeof ajaxOptions !== 'object' ) {
            return;
        }
        ajaxOptions.processData = false;
    }

    /**
     * Read a FormData field via get(), forEach(), or entries().
     * Native IE11 FormData is write-only and cannot be read.
     * @param {FormData} formData
     * @param {Array.<string>} keys
     * @return {*}
     */
    getFormDataField(formData, keys) {
        if ( typeof formData.get === 'function' ) {
            for ( let i = 0; i < keys.length; i++ ) {
                const value = formData.get(keys[i]);
                if ( value !== null && typeof value !== 'undefined' ) {
                    return value;
                }
            }
        }
        if ( typeof formData.forEach === 'function' ) {
            let found = null;
            formData.forEach(function(value, key) {
                if ( found !== null ) {
                    return;
                }
                for ( let i = 0; i < keys.length; i++ ) {
                    if ( key === keys[i] ) {
                        found = value;
                        return;
                    }
                }
            });
            if ( found !== null ) {
                return found;
            }
        }
        if ( typeof formData.entries === 'function' ) {
            try {
                const iterator = formData.entries();
                if ( iterator && typeof iterator.next === 'function' ) {
                    let step = iterator.next();
                    while ( !step.done ) {
                        const pair = step.value;
                        if ( pair ) {
                            for ( let i = 0; i < keys.length; i++ ) {
                                if ( pair[0] === keys[i] ) {
                                    return pair[1];
                                }
                            }
                        }
                        step = iterator.next();
                    }
                }
            } catch (e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Clone URLSearchParams so the caller's instance is not mutated.
     * @param {URLSearchParams} params
     * @return {URLSearchParams}
     */
    cloneURLSearchParams(params) {
        const serialized = typeof params.toString === 'function' ? params.toString() : '';
        if ( typeof params.constructor === 'function' && params.constructor !== Object ) {
            try {
                return new params.constructor(serialized);
            } catch (e) {
                // Fall through to the current-window constructor.
            }
        }
        if ( typeof URLSearchParams === 'function' ) {
            try {
                return new URLSearchParams(serialized);
            } catch (e) {
                return params;
            }
        }
        return params;
    }

    /**
     * Clone FormData when it can be enumerated (forEach or entries); otherwise reuse.
     * @param {FormData} formData
     * @return {FormData}
     */
    cloneFormData(formData) {
        try {
            const clone = new FormData();
            const copied = this.forEachJQAjaxKeyValueBag(formData, function(value, key) {
                clone.append(key, value);
            });
            if ( copied ) {
                return clone;
            }
        } catch (e) {
            return formData;
        }
        return formData;
    }

    /**
     * Form-urlencoded string from FormData/URLSearchParams. Skip File/Blob values.
     * @param {FormData|URLSearchParams} bag
     * @return {string}
     */
    serializeJQAjaxKeyValueBag(bag) {
        const parts = [];
        const collected = this.forEachJQAjaxKeyValueBag(bag, function(value, key) {
            if ( typeof value !== 'string' && typeof value !== 'number' && typeof value !== 'boolean' ) {
                return;
            }
            parts.push(encodeURIComponent(String(key)) + '=' + encodeURIComponent(String(value)));
        });
        if ( collected ) {
            return parts.join('&');
        }
        return '';
    }

    /**
     * Inject CleanTalk fields into URLSearchParams without changing the payload type.
     * @param {object} sourceSign
     * @param {URLSearchParams} params
     * @return {URLSearchParams}
     */
    injectCleantalkDataToJQAjaxKeyValue(sourceSign, params) {
        const pairs = this.getCleantalkJQAjaxFieldPairs(sourceSign);
        for ( let i = 0; i < pairs.length; i++ ) {
            params.append(pairs[i][0], pairs[i][1]);
        }
        if ( sourceSign.attachVisibleFieldsData ) {
            const extractor = ApbctVisibleFieldsExtractor.createExtractor(sourceSign.found);
            if ( extractor ) {
                const extracted = extractor.extract(params.toString());
                if ( typeof extracted === 'string' ) {
                    params.append('apbct_visible_fields', extracted);
                }
            }
        }
        return params;
    }

    /**
     * Inject CleanTalk fields into a jQuery serializeArray payload.
     * @param {object} sourceSign
     * @param {Array} ajaxData
     * @return {Array}
     */
    injectCleantalkDataToJQAjaxSerializeArray(sourceSign, ajaxData) {
        const result = ajaxData.slice();
        const pairs = this.getCleantalkJQAjaxFieldPairs(sourceSign);
        for ( let i = 0; i < pairs.length; i++ ) {
            result.push({
                name: pairs[i][0],
                value: pairs[i][1],
            });
        }
        if ( sourceSign.attachVisibleFieldsData ) {
            const extractor = ApbctVisibleFieldsExtractor.createExtractor(sourceSign.found);
            if (
                extractor &&
                typeof jQuery !== 'undefined' &&
                typeof jQuery.param === 'function'
            ) {
                const extracted = extractor.extract(jQuery.param(ajaxData));
                if ( typeof extracted === 'string' ) {
                    result.push({
                        name: 'apbct_visible_fields',
                        value: extracted,
                    });
                }
            }
        }
        return result;
    }

    /**
     * Inject CleanTalk fields into a plain object without changing the payload type.
     * @param {object} sourceSign
     * @param {object} ajaxData
     * @return {object}
     */
    injectCleantalkDataToJQAjaxPlainObject(sourceSign, ajaxData) {
        const result = typeof jQuery !== 'undefined' && typeof jQuery.extend === 'function' ?
            jQuery.extend({}, ajaxData) :
            this.clonePlainObject(ajaxData);
        const pairs = this.getCleantalkJQAjaxFieldPairs(sourceSign);
        for ( let i = 0; i < pairs.length; i++ ) {
            result[pairs[i][0]] = pairs[i][1];
        }
        if ( sourceSign.attachVisibleFieldsData ) {
            const extractor = ApbctVisibleFieldsExtractor.createExtractor(sourceSign.found);
            if (
                extractor &&
                typeof jQuery !== 'undefined' &&
                typeof jQuery.param === 'function'
            ) {
                const extracted = extractor.extract(jQuery.param(ajaxData));
                if ( typeof extracted === 'string' ) {
                    result.apbct_visible_fields = extracted;
                }
            }
        }
        return result;
    }

    /**
     * IE11-safe shallow copy of a plain object.
     * @param {object} ajaxData
     * @return {object}
     */
    clonePlainObject(ajaxData) {
        const result = {};
        for ( const key in ajaxData ) {
            if ( Object.prototype.hasOwnProperty.call(ajaxData, key) ) {
                result[key] = ajaxData[key];
            }
        }
        return result;
    }

    /**
     * Injecting CleanTalk data to ajax data FormData object.
     * @param {object} sourceSign
     * @param {object} ajaxDataFormData
     * @return {object}
     */
    injectCleantalkDataToJQAjaxFormData(sourceSign, ajaxDataFormData) {
        if (
            typeof sourceSign !== 'object' ||
            !this.isJQAjaxFormData(ajaxDataFormData)
        ) {
            return ajaxDataFormData;
        }

        try {
            // Event token
            if (
                +ctPublic.bot_detector_enabled &&
                apbctLocalStorage.get('bot_detector_event_token')
            ) {
                const token = getEventToken();
                if (token) {
                    if (sourceSign.keepUnwrapped) {
                        ajaxDataFormData.append('ct_bot_detector_event_token', token);
                    } else {
                        ajaxDataFormData.append('data[ct_bot_detector_event_token]', token);
                    }
                }
            } else {
                // No cookie data
                let noCookieData = getNoCookieData();
                if (noCookieData) {
                    if (sourceSign.keepUnwrapped) {
                        ajaxDataFormData.append('ct_no_cookie_hidden_field', noCookieData);
                    } else {
                        ajaxDataFormData.append('data[ct_no_cookie_hidden_field]', noCookieData);
                    }
                }
            }

            // Bot detector browser state
            const browserState = apbctGetBrowserStatePair();
            if (browserState) {
                if (sourceSign.keepUnwrapped) {
                    ajaxDataFormData.append(browserState.key, browserState.value);
                } else {
                    ajaxDataFormData.append('data[' + browserState.key + ']', browserState.value);
                }
            }

            // Visible fields
            if (sourceSign.attachVisibleFieldsData) {
                let visibleFieldsSearchResult = false;
                const extractor = ApbctVisibleFieldsExtractor.createExtractor(sourceSign.found);
                if (extractor) {
                    // Try to find form_id in FormData to find form container and
                    // collect visible fields only inside it
                    const formId = this.getFormDataField(
                        ajaxDataFormData,
                        ['form_id', 'data[form_id]'],
                    );
                    let container = null;
                    if (formId && typeof formId === 'string') {
                        // Sanitize formId to prevent selector injection
                        const sanitizedFormId = formId.replace(/["\\]/g, '');
                        // First, try to find by id
                        container = document.getElementById(sanitizedFormId);
                        // If not found, try to find by data-id
                        if (!container) {
                            container = document.querySelector('[data-id="' + sanitizedFormId + '"]');
                        }
                    }
                    if (container) {
                        // Collect all input, select, textarea inside the container
                        const fields = container.querySelectorAll('input, select, textarea');
                        // Use collectVisibleFields, passing an object with elements
                        const visibleFieldsObj = window.apbctVisibleFields?.collect({elements: fields});
                        visibleFieldsSearchResult = JSON.stringify(visibleFieldsObj);
                    }
                }
                if (typeof visibleFieldsSearchResult === 'string' && visibleFieldsSearchResult.length > 0) {
                    ajaxDataFormData.append('apbct_visible_fields', visibleFieldsSearchResult);
                }
            }
        } catch (e) {
            // Silently fail to not break the original request
        }

        return ajaxDataFormData;
    }

    /**
     * Injecting CleanTalk data to ajax data string.
     * @param {object} sourceSign Object of found sign to intercept.
     * @param {string} ajaxDataString Original data string from ajax object.
     * @return {string}
     */
    injectCleantalkDataToJQAjaxString(sourceSign, ajaxDataString) {
        // something wrong with incoming data
        if (typeof sourceSign !== 'object' || typeof ajaxDataString !== 'string') {
            return ajaxDataString;
        }
        let eventToken = '';
        let noCookieData = '';
        let visibleFieldsString = '';
        let browserStateString = '';

        const browserState = apbctGetBrowserStatePair();
        if (browserState) {
            const browserStateKey = sourceSign.keepUnwrapped ?
                'apbct_browser_state' :
                'data%5Bapbct_browser_state%5D';
            browserStateString = browserStateKey + '=' + encodeURIComponent(browserState.value) + '&';
        }

        if (
            +ctPublic.bot_detector_enabled &&
            apbctLocalStorage.get('bot_detector_event_token')
        ) {
            const token = getEventToken();
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
                noCookieData = 'ct_no_cookie_hidden_field=' + encodeURIComponent(noCookieData) + '&';
            } else {
                noCookieData = 'data%5Bct_no_cookie_hidden_field%5D=' + encodeURIComponent(noCookieData) + '&';
            }
        }

        if (sourceSign.attachVisibleFieldsData) {
            let visibleFieldsSearchResult = false;

            const extractor = ApbctVisibleFieldsExtractor.createExtractor(sourceSign.found);
            if (extractor) { // Check if extractor was created
                visibleFieldsSearchResult = extractor.extract(ajaxDataString);
            }

            if (typeof visibleFieldsSearchResult === 'string') {
                let encoded = null;
                try {
                    encoded = encodeURIComponent(visibleFieldsSearchResult);
                    visibleFieldsString = 'apbct_visible_fields=' + encoded + '&';
                } catch (e) {
                    // do nothing
                }
            }
        }

        return noCookieData + eventToken + browserStateString + visibleFieldsString + ajaxDataString;
    }
}
