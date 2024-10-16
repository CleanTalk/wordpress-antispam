/**
 * Base class
 */
class ApbctCore {
    ajax_parameters = {};
    restParameters = {};

    selector = null;
    elements = [];

    // Event properties
    eventCallback;
    eventSelector;
    event;

    /**
     * Default constructor
     * @param {string} selector
     */
    constructor(selector) {
        this.select(selector);
    }

    /**
     * Get elements by CSS selector
     *
     * @param {string} selector
     * @return {*}
     */
    select(selector) {
        if (selector instanceof HTMLCollection) {
            this.selector = null;
            this.elements = [];
            this.elements = Array.prototype.slice.call(selector);
        } else if ( typeof selector === 'object' ) {
            this.selector = null;
            this.elements = [];
            this.elements[0] = selector;
        } else if ( typeof selector === 'string' ) {
            this.selector = selector;
            this.elements = Array.prototype.slice.call(document.querySelectorAll(selector));
            // this.elements = document.querySelectorAll(selector)[0];
        } else {
            this.deselect();
        }

        return this;
    }

    /**
     * @param {object|string} elemToAdd
     */
    addElement(elemToAdd) {
        if ( typeof elemToAdd === 'object' ) {
            this.elements.push(elemToAdd);
        } else if ( typeof elemToAdd === 'string' ) {
            this.selector = elemToAdd;
            this.elements = Array.prototype.slice.call(document.querySelectorAll(elemToAdd));
        } else {
            this.deselect();
        }
    }

    /**
     * @param {object} elem
     */
    push(elem) {
        this.elements.push(elem);
    }

    /**
     * reduce
     */
    reduce() {
        this.elements = this.elements.slice(0, -1);
    }

    /**
     * deselect
     */
    deselect() {
        this.elements = [];
    }

    /**
     * Set or get CSS for/of currently selected element
     *
     * @param {object|string} style
     * @param {boolean} getRaw
     *
     * @return {boolean|*}
     */
    css(style, getRaw) {
        getRaw = getRaw || false;

        // Set style
        if (typeof style === 'object') {
            const stringToCamelCase = (str) =>
                str.replace(/([-_][a-z])/g, (group) =>
                    group
                        .toUpperCase()
                        .replace('-', '')
                        .replace('_', ''),
                );

            // Apply multiple styles
            for (const styleName in style) {
                if (Object.hasOwn(style, styleName)) {
                    const DomStyleName = stringToCamelCase(styleName);

                    // Apply to multiple elements (currently selected)
                    for (let i=0; i<this.elements.length; i++) {
                        this.elements[i].style[DomStyleName] = style[styleName];
                    }
                }
            }

            return this;
        }

        // Get style of first currently selected element
        if (typeof style === 'string') {
            let computedStyle = getComputedStyle(this.elements[0])[style];

            // Process
            if ( typeof computedStyle !== 'undefined' && ! getRaw) {
                // Cut of units
                computedStyle = computedStyle.replace(/(\d)(em|pt|%|px){1,2}$/, '$1');
                // Cast to INT
                computedStyle = Number(computedStyle) == computedStyle ? Number(computedStyle) : computedStyle;
                return computedStyle;
            }

            // Return unprocessed
            return computedStyle;
        }
    }

    /**
     * hide
     */
    hide() {
        this.prop('prev-display', this.css('display'));
        this.css({'display': 'none'});
    }

    /**
     * show
     */
    show() {
        this.css({'display': this.prop('prev-display')});
    }

    /**
     * addClass
     */
    addClass() {
        for (let i=0; i<this.elements.length; i++) {
            this.elements[i].classList.add(className);
        }
    }

    /**
     * removeClass
     */
    removeClass() {
        for (let i=0; i<this.elements.length; i++) {
            this.elements[i].classList.remove(className);
        }
    }

    /**
     * @param {string} className
     */
    toggleClass(className) {
        for (let i=0; i<this.elements.length; i++) {
            this.elements[i].classList.toggle(className);
        }
    }

    /**
     * Wrapper for apbctAJAX class
     *
     * @param {object|array} ajaxParameters
     * @return {ApbctAjax}
     */
    ajax(ajaxParameters) {
        this.ajax_parameters = ajaxParameters;
        return new ApbctAjax(ajaxParameters);
    }

    /**
     * Wrapper for apbctREST class
     *
     * @param {object|array} restParameters
     * @return {ApbctRest}
     */
    rest(restParameters) {
        this.restParameters = restParameters;
        return new ApbctRest(restParameters);
    }

    /**
     * ************ EVENTS *************
     */

    /**
     *
     * Why the mess with arguments?
     *
     * Because we need to support the following function signatures:
     *      on('click',                   function(){ alert('some'); });
     *      on('click', 'inner_selector', function(){ alert('some'); });
     *
     * @param {object|array} args
     */
    on(...args) {
        this.event = args[0];
        this.eventCallback = args[2] || args[1];
        this.eventSelector = typeof args[1] === 'string' ? args[1] : null;

        for (let i=0; i<this.elements.length; i++) {
            this.elements[i].addEventListener(
                this.event,
                this.eventSelector !== null ?
                    this.onChecker.bind(this) :
                    this.eventCallback,
            );
        }
    }

    /**
     * Check if a selector of an event matches current target
     *
     * @param {object} event
     * @return {*}
     */
    onChecker(event) {
        if (event.target === document.querySelector(this.eventSelector)) {
            event.stopPropagation();
            return this.eventCallback(event);
        }
    }

    /**
     * @param {object|function|string} callback
     */
    ready(callback) {
        document.addEventListener('DOMContentLoaded', callback);
    }

    /**
     * @param {object|function|string} callback
     */
    change(callback) {
        this.on('change', callback);
    }

    /**
     * ATTRIBUTES
     */

    /**
     * Get an attribute or property of an element
     *
     * @param {string} attrName
     * @return {*|*[]}
     */
    attr(attrName) {
        let outputValue = [];

        for (let i=0; i<this.elements.length; i++) {
            // Use property instead of attribute if possible
            if (typeof this.elements[i][attrName] !== 'undefined') {
                outputValue.push(this.elements[i][attrName]);
            } else {
                outputValue.push(this.elements[i].getAttribute(attrName));
            }
        }

        // Return a single value instead of array if only one value is present
        return outputValue.length === 1 ? outputValue[0] : outputValue;
    }

    /**
     * @param {string} propName
     * @param {mixed} value
     * @return {*|*[]|ApbctCore}
     */
    prop(propName, value) {
        // Setting values
        if (typeof value !== 'undefined') {
            for (let i=0; i<this.elements.length; i++) {
                this.elements[i][propName] = value;
            }

            return this;

            // Getting values
        } else {
            const outputValue = [];

            for (let i=0; i<this.elements.length; i++) {
                outputValue.push(this.elements[i][propName]);
            }

            // Return a single value instead of array if only one value is present
            return outputValue.length === 1 ? outputValue[0] : outputValue;
        }
    }

    /**
     * Set or get inner HTML
     *
     * @param {string} value
     * @return {*|*[]}
     */
    html(value) {
        return typeof value !== 'undefined' ?
            this.prop('innerHTML', value) :
            this.prop('innerHTML');
    }

    /**
     * Set or get value of input tags
     *
     * @param {mixed} value
     * @return {*|*[]|undefined}
     */
    val(value) {
        return typeof value !== 'undefined' ?
            this.prop('value', value) :
            this.prop('value');
    }

    /**
     * @param {string} name
     * @param {mixed} value
     * @return {*|*[]|ApbctCore}
     */
    data(name, value) {
        return typeof value !== 'undefined' ?
            this.prop('apbct-data', name, value) :
            this.prop('apbct-data');
    }

    /**
     * END OF ATTRIBUTES
     */

    /**
     * FILTERS
     */

    /**
     * Check if the current elements are corresponding to filter
     *
     * @param {mixed} filter
     * @return {boolean}
     */
    is(filter) {
        let outputValue = false;

        for (let elem of this.elements) {
            outputValue ||= this.isElem(elem, filter);
        }

        return outputValue;
    }

    /**
     * @param {string|object} elemToCheck
     * @param {mixed} filter
     * @return {boolean}
     */
    isElem(elemToCheck, filter) {
        let is = false;
        let isRegisteredTagName = function(name) {
            let newlyCreatedElement = document.createElement(name).constructor;
            return ! Boolean( ~[HTMLElement, HTMLUnknownElement].indexOf(newlyCreatedElement) );
        };

        // Check for filter function
        if (typeof filter === 'function') {
            is ||= filter.call(this, elemToCheck);
        }

        // Check for filter function
        if (typeof filter === 'string') {
            // Filter is tag name
            if ( filter.match(/^[a-z]/) && isRegisteredTagName(filter) ) {
                is ||= elemToCheck.tagName.toLowerCase() === filter.toLowerCase();

                // Filter is property
            } else if ( filter.match(/^[a-z]/) ) {
                is ||= Boolean(elemToCheck[filter]);

                // Filter is CSS selector
            } else {
                is ||= this.selector !== null ?
                    document.querySelector(this.selector + filter) !== null : // If possible
                    this.isWithoutSelector(elemToCheck, filter); // Search through all elems with such selector
            }
        }

        return is;
    }

    /**
     * @param {object|string} elemToCheck
     * @param {mixed} filter
     * @return {boolean}
     */
    isWithoutSelector(elemToCheck, filter) {
        const elems = document.querySelectorAll(filter);
        let outputValue = false;

        for (let elem of elems) {
            outputValue ||= elemToCheck === elem;
        }

        return outputValue;
    }

    /**
     * @param {mixed} filter
     * @return {ApbctCore}
     */
    filter(filter) {
        this.selector = null;

        for ( let i = this.elements.length - 1; i >= 0; i-- ) {
            if ( ! this.isElem(this.elements[i], filter) ) {
                this.elements.splice(Number(i), 1);
            }
        }

        return this;
    }

    /**
     * NODES
     */

    /**
     * @param {mixed} filter
     * @return {ApbctCore}
     */
    parent(filter) {
        this.select(this.elements[0].parentElement);

        if ( typeof filter !== 'undefined' && ! this.is(filter) ) {
            this.deselect();
        }

        return this;
    }

    /**
     * @param {mixed} filter
     * @return {ApbctCore}
     */
    parents(filter) {
        this.select(this.elements[0]);

        for ( ; this.elements[this.elements.length - 1].parentElement !== null; ) {
            this.push(this.elements[this.elements.length - 1].parentElement);
        }

        this.elements.splice(0, 1); // Deleting initial element from the set

        if ( typeof filter !== 'undefined' ) {
            this.filter(filter);
        }

        return this;
    }

    /**
     * @param {mixed} filter
     * @return {ApbctCore}
     */
    children(filter) {
        this.select(this.elements[0].children);

        if ( typeof filter !== 'undefined' ) {
            this.filter(filter);
        }

        return this;
    }

    /**
     * @param {mixed} filter
     * @return {ApbctCore}
     */
    siblings(filter) {
        let current = this.elements[0]; // Remember current to delete it later

        this.parent();
        this.children(filter);
        this.elements.splice(this.elements.indexOf(current), 1); // Remove current element

        return this;
    }

    /** ************ DOM MANIPULATIONS **************/
    remove() {
        for (let elem of this.elements) {
            elem.remove();
        }
    }

    /**
     * @param {string} content
     */
    after(content) {
        for (let elem of this.elements) {
            elem.after(content);
        }
    }

    /**
     * @param {string} content
     */
    append(content) {
        for (let elem of this.elements) {
            elem.append(content);
        }
    }

    /** ************  ANIMATION  **************/
    /**
     * @param {number} time
     */
    fadeIn(time) {
        for (let elem of this.elements) {
            elem.style.opacity = 0;
            elem.style.display = 'block';

            let last = +new Date();
            const tick = function() {
                elem.style.opacity = +elem.style.opacity + (new Date() - last) / time;
                last = +new Date();

                if (+elem.style.opacity < 1) {
                    (window.requestAnimationFrame && requestAnimationFrame(tick)) || setTimeout(tick, 16);
                }
            };

            tick();
        }
    }

    /**
     * @param {number} time
     */
    fadeOut(time) {
        for (let elem of this.elements) {
            elem.style.opacity = 1;

            let last = +new Date();
            const tick = function() {
                elem.style.opacity = +elem.style.opacity - (new Date() - last) / time;
                last = +new Date();

                if (+elem.style.opacity > 0) {
                    (window.requestAnimationFrame && requestAnimationFrame(tick)) || setTimeout(tick, 16);
                } else {
                    elem.style.display = 'none';
                }
            };

            tick();
        }
    }
}

/**
 * Hack
 *
 * Make a proxy to keep both properties and methods from:
 *  - the native object and
 *  - the new one from ApbctCore for selected element.
 *
 * For example:
 * apbct('#id).innerHTML = 'some';
 * apbct('#id).css({'backgorund-color': 'black'});
 */
// apbct = new Proxy(
//         apbct,
//         {
//             get(target, prop) {
//                 if (target.elements.length) {
//                     return target.elements[0][prop];
//                 } else {
//                     return null;
//                 }
//             },
//             set(target, prop, value){
//                 if (target.elements.length) {
//                     target.elements[0][prop] = value;
//                     return true;
//                 } else {
//                     return false;
//                 }
//             },
//             apply(target, thisArg, argArray) {
//
//             }
//         }
//     );

/**
 * @param {mixed} msg
 * @param {string} url
 */
function ctProcessError(msg, url) {
    let log = {};
    if (msg && msg.message) {
        log.err = {
            'msg': msg.message,
            'file': !!msg.fileName ? msg.fileName : false,
            'ln': !!msg.lineNumber ? msg.lineNumber : !!lineNo ? lineNo : false,
            'col': !!msg.columnNumber ? msg.columnNumber : !!columnNo ? columnNo : false,
            'stacktrace': !!msg.stack ? msg.stack : false,
            'cause': !!url ? JSON.stringify(url) : false,
            'errorObj': !!error ? error : false,
        };
    } else {
        log.err = {
            'msg': msg,
        };

        if (!!url) {
            log.err.file = url;
        }
    }

    log.url = window.location.href;
    log.userAgent = window.navigator.userAgent;

    let ctJsErrors = 'ct_js_errors';
    let errArray = localStorage.getItem(ctJsErrors);
    if (errArray === null) errArray = '[]';
    errArray = JSON.parse(errArray);
    for (let i = 0; i < errArray.length; i++) {
        if (errArray[i].err.msg === log.err.msg) {
            return;
        }
    }

    errArray.push(log);
    localStorage.setItem(ctJsErrors, JSON.stringify(errArray));
}

if (Math.floor(Math.random() * 100) === 1) {
    window.onerror = function(exception, url) {
        let filterWords = ['apbct', 'ctPublic'];
        let length = filterWords.length;
        while (length--) {
            if (exception.indexOf(filterWords[length]) !== -1) {
                ctProcessError(exception, url);
            }
        }

        return false;
    };
}

/**
 * Select actual WP nonce depending on the ajax type and the fresh nonce provided.
 * @return {string} url
 */
function selectActualNonce() {
    let defaultNonce = '';
    // return fresh nonce immediately if persists
    if (
        ctPublicFunctions.hasOwnProperty('_fresh_nonce') &&
        typeof ctPublicFunctions._fresh_nonce === 'string' &&
        ctPublicFunctions._fresh_nonce.length > 0
    ) {
        return ctPublicFunctions._fresh_nonce;
    }
    // select from default rest/ajax nonces
    if (
        ctPublicFunctions.data__ajax_type === 'admin_ajax' &&
        ctPublicFunctions.hasOwnProperty('_ajax_nonce') &&
        typeof ctPublicFunctions._ajax_nonce === 'string' &&
        ctPublicFunctions._ajax_nonce.length > 0
    ) {
        defaultNonce = ctPublicFunctions._ajax_nonce;
    }
    if (
        ctPublicFunctions.data__ajax_type === 'rest' &&
        ctPublicFunctions.hasOwnProperty('_rest_nonce') &&
        typeof ctPublicFunctions._rest_nonce === 'string' &&
        ctPublicFunctions._rest_nonce.length > 0
    ) {
        defaultNonce = ctPublicFunctions._rest_nonce;
    }

    return defaultNonce;
}

/**
 * Enter point to ApbctCore class
 *
 * @param {array|object} params
 * @return {*}
 */
// eslint-disable-next-line no-unused-vars, require-jsdoc
function apbct(params) {
    return new ApbctCore()
        .select(params);
}

/**
 * ApbctXhr
 */
class ApbctXhr {
    xhr = new XMLHttpRequest();

    // Base parameters
    method = 'POST'; // HTTP-request type
    url = ''; // URL to send the request
    async = true;
    user = null; // HTTP-authorization username
    password = null; // HTTP-authorization password
    data = {}; // Data to send

    // Optional params
    button = null; // Button that should be disabled when request is performing
    spinner = null; // Spinner that should appear when request is in process
    progressbar = null; // Progress bar for the current request
    context = this; // Context
    callback = null;
    onErrorCallback = null;

    responseType = 'json'; // Expected data type from server
    headers = {};
    timeout = 15000; // Request timeout in milliseconds

    methods_to_convert_data_to_URL = [
        'GET',
        'HEAD',
    ];

    body = null;
    http_code = 0;
    status_text = '';

    // eslint-disable-next-line require-jsdoc
    constructor(parameters) {
        // Set class properties
        for ( let key in parameters ) {
            if ( typeof this[key] !== 'undefined' ) {
                this[key] = parameters[key];
            }
        }

        // Modifying DOM-elements
        this.prepare();

        // Modify URL with data for GET and HEAD requests
        if ( Object.keys(this.data).length ) {
            this.deleteDoubleJSONEncoding(this.data);
            this.convertData();
        }

        if ( ! this.url ) {
            console.log('%cXHR%c not URL provided',
                'color: red; font-weight: bold;', 'color: grey; font-weight: normal;');
            return false;
        }

        // Configure the request
        this.xhr.open(this.method, this.url, this.async, this.user, this.password);
        this.setHeaders();

        this.xhr.responseType = this.responseType;
        this.xhr.timeout = this.timeout;

        /* EVENTS */
        // Monitoring status
        this.xhr.onreadystatechange = function() {
            if (this.isWpNonceError()) {
                this.getFreshNonceAndRerunXHR(parameters);
                return;
            }
            this.onReadyStateChange();
        }.bind(this);

        // Run callback
        this.xhr.onload = function() {
            this.onLoad();
        }.bind(this);

        // On progress
        this.xhr.onprogress = function(event) {
            this.onProgress(event);
        }.bind(this);

        // On error
        this.xhr.onerror = function() {
            this.onError();
        }.bind(this);

        this.xhr.ontimeout = function() {
            this.onTimeout();
        }.bind(this);

        // Send the request
        this.xhr.send(this.body);
    }

    /**
     * prepare
     */
    prepare() {
        // Disable button
        if (this.button) {
            this.button.setAttribute('disabled', 'disabled');
            this.button.style.cursor = 'not-allowed';
        }

        // Enable spinner
        if (this.spinner) {
            this.spinner.style.display = 'inline';
        }
    }

    /**
     * complete
     */
    complete() {
        this.http_code = this.xhr.status;
        this.status_text = this.xhr.statusText;

        // Disable button
        if (this.button) {
            this.button.removeAttribute('disabled');
            this.button.style.cursor = 'auto';
        }

        // Enable spinner
        if (this.spinner) {
            this.spinner.style.display = 'none';
        }

        if ( this.progressbar ) {
            this.progressbar.fadeOut('slow');
        }
    }

    /**
     * onReadyStateChange
     */
    onReadyStateChange() {
        if (this.on_ready_state_change !== null && typeof this.on_ready_state_change === 'function') {
            this.on_ready_state_change();
        }
    }

    /**
     * @param {object} event
     */
    onProgress(event) {
        if (this.on_progress !== null && typeof this.on_progress === 'function') {
            this.on_progress();
        }
    }

    /**
     * onError
     */
    onError() {
        console.log('error');

        this.complete();
        this.error(
            this.http_code,
            this.status_text,
        );

        if (this.onErrorCallback !== null && typeof this.onErrorCallback === 'function') {
            this.onErrorCallback(this.status_text);
        }
    }

    /**
     * onTimeout
     */
    onTimeout() {
        this.complete();
        this.error(
            0,
            'timeout',
        );

        if (this.onErrorCallback !== null && typeof this.onErrorCallback === 'function') {
            this.onErrorCallback('Timeout');
        }
    }

    /**
     * @return {boolean}
     */
    onLoad() {
        this.complete();

        if (this.responseType === 'json' ) {
            if (this.xhr.response === null) {
                this.error(this.http_code, this.status_text, 'No response');
                return false;
            } else if ( typeof this.xhr.response.error !== 'undefined') {
                this.error(this.http_code, this.status_text, this.xhr.response.error);
                return false;
            }
        }

        if (this.callback !== null && typeof this.callback === 'function') {
            this.callback.call(this.context, this.xhr.response, this.data);
        }
    }

    /**
     * Check if 403 code of WP nonce error
     * @return {bool}
     */
    isWpNonceError() {
        let restErrror = false;
        let ajaxErrror = false;
        // check rest error
        if (this.xhr.readyState == 4) {
            restErrror = (
                typeof this.xhr.response === 'object' && this.xhr.response !== null &&
                this.xhr.response.hasOwnProperty('data') &&
                this.xhr.response.data.hasOwnProperty('status') &&
                this.xhr.response.data.status === 403
            );
            ajaxErrror = this.xhr.response === '-1' && this.xhr.status === 403;
        }
        // todo check AJAX error
        return restErrror || ajaxErrror;
    }

    /**
     * Get the fresh nonce and rerun the initial XHR with params
     * @param {[]} initialRequestParams
     */
    getFreshNonceAndRerunXHR(initialRequestParams) {
        let noncePrev = '';

        // Check if initialRequestParams['headers']['X-WP-Nonce'] exists.
        if (
            initialRequestParams.hasOwnProperty('headers') &&
            initialRequestParams.headers.hasOwnProperty('X-WP-Nonce')
        ) {
            noncePrev = initialRequestParams['headers']['X-WP-Nonce'];
        }

        // Check if initialRequestParams['data']['_ajax_nonce'] exists.
        if (
            initialRequestParams.hasOwnProperty('data') &&
            initialRequestParams.data.hasOwnProperty('_ajax_nonce')
        ) {
            noncePrev = initialRequestParams['data']['_ajax_nonce'];
        }

        // Nonce is not provided. Exit.
        if ( noncePrev === '' ) {
            return;
        }

        // prepare params for refreshing nonce
        let params = {};
        params.method = 'POST';
        params.data = {
            'spbc_remote_call_action': 'get_fresh_wpnonce',
            'plugin_name': 'antispam',
            'nonce_prev': noncePrev,
            'initial_request_params': initialRequestParams,
        };
        params.notJson = true;
        params.url = ctPublicFunctions.host_url;
        // this callback will rerun the XHR with initial params
        params.callback = function(...args) {
            // the refresh result itself
            let freshNonceResult = args[0];
            let newRequestParams = false;
            // provided initial params
            if (args[1] !== undefined && args[1].hasOwnProperty('initial_request_params')) {
                newRequestParams = args[1].initial_request_params;
            }
            if (newRequestParams && freshNonceResult.hasOwnProperty('wpnonce')) {
                ctPublicFunctions._fresh_nonce = freshNonceResult.wpnonce;
                if (ctPublicFunctions.data__ajax_type === 'rest') {
                    new ApbctCore().rest(newRequestParams);
                } else {
                    new ApbctCore().ajax(newRequestParams);
                }
            }
        };
        // run the nonce refreshing call
        new ApbctXhr(params);
    }

    /**
     * @param {number} httpCode
     * @param {string} statusText
     * @param {string} additionalMsg
     */
    error(httpCode, statusText, additionalMsg) {
        let errorString = '';

        if ( statusText === 'timeout' ) {
            errorString += 'Server response timeout';
        } else if ( httpCode === 200 ) {
            if ( statusText === 'parsererror' ) {
                errorString += 'Unexpected response from server. See console for details.';
            } else {
                errorString += 'Unexpected error. Status: ' + statusText + '.';
                if ( typeof additionalMsg !== 'undefined' ) {
                    errorString += ' Additional error info: ' + additionalMsg;
                }
            }
        } else if (httpCode === 500) {
            errorString += 'Internal server error.';
        } else {
            errorString += 'Unexpected response code:' + httpCode;
        }

        this.errorOutput( errorString );
    }

    /**
     * @param {string} errorMsg
     */
    errorOutput(errorMsg) {
        console.log( '%c ctXHR error: %c' + errorMsg, 'color: red;', 'color: grey;' );
    }

    /**
     * setHeaders
     */
    setHeaders() {
        // Set headers if passed
        for ( let headerName in this.headers ) {
            if ( typeof this.headers[headerName] !== 'undefined' ) {
                this.xhr.setRequestHeader(headerName, this.headers[headerName]);
            }
        }
    }

    /**
     * @return {string|*}
     */
    convertData() {
        // GET, HEAD request-type
        if ( ~this.methods_to_convert_data_to_URL.indexOf( this.method ) ) {
            return this.convertDataToURL();

            // POST request-type
        } else {
            return this.convertDataToBody();
        }
    }

    /**
     * @return {string}
     */
    convertDataToURL() {
        let paramsAppendix = new URLSearchParams(this.data).toString();
        let paramsPrefix = this.url.match(/^(https?:\/{2})?[a-z0-9.]+\?/) ? '&' : '?';
        this.url += paramsPrefix + paramsAppendix;

        return this.url;
    }

    /**
     * @return {null}
     */
    convertDataToBody() {
        this.body = new FormData();
        for (let dataKey in this.data) {
            if (Object.hasOwn(this.data, dataKey)) {
                this.body.append(
                    dataKey,
                    typeof this.data[dataKey] === 'object' ?
                        JSON.stringify(this.data[dataKey]) :
                        this.data[dataKey],
                );
            }
        }

        return this.body;
    }

    /**
     * Recursive
     *
     * Recursively decode JSON-encoded properties
     *
     * @param {object} object
     * @return {*}
     */
    deleteDoubleJSONEncoding(object) {
        if ( typeof object === 'object') {
            for (let objectKey in object) {
                if (Object.hasOwn(object, objectKey)) {
                    // Recursion
                    if ( typeof object[objectKey] === 'object') {
                        object[objectKey] = this.deleteDoubleJSONEncoding(object[objectKey]);
                    }

                    // Common case (out)
                    if (
                        typeof object[objectKey] === 'string' &&
                        object[objectKey].match(/^[\[{].*?[\]}]$/) !== null // is like JSON
                    ) {
                        let parsedValue = JSON.parse(object[objectKey]);
                        if ( typeof parsedValue === 'object' ) {
                            object[objectKey] = parsedValue;
                        }
                    }
                }
            }
        }

        return object;
    }
}
// eslint-disable-next-line require-jsdoc
class ApbctAjax extends ApbctXhr {
    // eslint-disable-next-line require-jsdoc
    constructor(...args) {
        args = args[0];
        args.data._ajax_nonce = selectActualNonce();
        super(args);
    }
}
// eslint-disable-next-line require-jsdoc
class ApbctRest extends ApbctXhr {
    static default_route = ctPublicFunctions._rest_url + 'cleantalk-antispam/v1/';
    route = '';

    // eslint-disable-next-line require-jsdoc
    constructor(...args) {
        args = args[0];
        const nonce = selectActualNonce();
        args.url = ApbctRest.default_route + args.route;
        args.headers = {
            'X-WP-Nonce': nonce,
        };
        super(args);
    }
}
