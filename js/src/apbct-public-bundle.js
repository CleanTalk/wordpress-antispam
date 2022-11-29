class ApbctCore{

    ajax_parameters = {};
    rest_parameters = {};

    selector = null;
    elements = [];

    // Event properties
    eventCallback;
    eventSelector;
    event;

    /**
     * Default constructor
     */
    constructor(selector){
        this.select(selector);
    }

    /**
     * Get elements by CSS selector
     *
     * @param selector
     * @returns {*}
     */
    select(selector) {

        if(selector instanceof HTMLCollection){
            this.selector    = null;
            this.elements    = [];
            this.elements = Array.prototype.slice.call(selector);
        }else if( typeof selector === 'object' ){
            this.selector    = null;
            this.elements    = [];
            this.elements[0] = selector;
        }else if( typeof selector === 'string' ){
            this.selector = selector;
            this.elements = Array.prototype.slice.call(document.querySelectorAll(selector));
            // this.elements = document.querySelectorAll(selector)[0];
        }else{
            this.deselect();
        }

        return this;
    }

    addElement(elemToAdd){
        if( typeof elemToAdd === 'object' ){
            this.elements.push(elemToAdd);
        }else if( typeof elemToAdd === 'string' ){
            this.selector = elemToAdd;
            this.elements = Array.prototype.slice.call(document.querySelectorAll(elemToAdd));
        }else{
            this.deselect();
        }
    }

    push(elem){
        this.elements.push(elem);
    }

    reduce(){
        this.elements = this.elements.slice(0,-1);
    }

    deselect(){
        this.elements = [];
    }

    /**
     * Set or get CSS for/of currently selected element
     *
     * @param style
     * @param getRaw
     *
     * @returns {boolean|*}
     */
    css(style, getRaw){

        getRaw = getRaw | false;

        // Set style
        if(typeof style === "object"){

            const stringToCamelCase = str =>
                str.replace(/([-_][a-z])/g, group =>
                    group
                        .toUpperCase()
                        .replace('-', '')
                        .replace('_', '')
                );

            // Apply multiple styles
            for(let style_name in style){
                let DOM_style_name = stringToCamelCase(style_name);

                // Apply to multiple elements (currently selected)
                for(let i=0; i<this.elements.length; i++){
                    this.elements[i].style[DOM_style_name] = style[style_name];
                }
            }

            return this;
        }

        // Get style of first currently selected element
        if(typeof style === 'string'){

            let computedStyle = getComputedStyle(this.elements[0])[style];

            // Process
            if( typeof computedStyle !== 'undefined' && ! getRaw){
                computedStyle = computedStyle.replace(/(\d)(em|pt|%|px){1,2}$/, '$1');                           // Cut of units
                computedStyle = Number(computedStyle) == computedStyle ? Number(computedStyle) : computedStyle; // Cast to INT
                return computedStyle;
            }

            // Return unprocessed
            return computedStyle;
        }
    }

    hide(){
        this.prop('prev-display', this.css('display'));
        this.css({'display': 'none'});
    }

    show(){
        this.css({'display': this.prop('prev-display')});
    }

    addClass(){
        for(let i=0; i<this.elements.length; i++){
            this.elements[i].classList.add(className);
        }
    }

    removeClass(){
        for(let i=0; i<this.elements.length; i++){
            this.elements[i].classList.remove(className);
        }
    }

    toggleClass(className){
        for(let i=0; i<this.elements.length; i++){
            this.elements[i].classList.toggle(className);
        }
    }

    /**
     * Wrapper for apbctAJAX class
     *
     * @param ajax_parameters
     * @returns {ApbctAjax}
     */
    ajax(ajax_parameters){
        this.ajax_parameters = ajax_parameters;
        return new ApbctAjax(ajax_parameters);
    }

    /**
     * Wrapper for apbctREST class
     *
     * @param rest_parameters
     * @returns {ApbctRest}
     */
    rest(rest_parameters){
        this.rest_parameters = rest_parameters;
        return new ApbctRest(rest_parameters);
    }

    /************** EVENTS **************/

    /**
     *
     * Why the mess with arguments?
     *
     * Because we need to support the following function signatures:
     *      on('click',                   function(){ alert('some'); });
     *      on('click', 'inner_selector', function(){ alert('some'); });
     *
     * @param args
     */
    on(...args){

        this.event         = args[0];
        this.eventCallback = args[2] || args[1];
        this.eventSelector = typeof args[1] === "string" ? args[1] : null;

        for(let i=0; i<this.elements.length; i++){
            this.elements[i].addEventListener(
                this.event,
                this.eventSelector !== null
                    ? this.onChecker.bind(this)
                    : this.eventCallback
            );
        }
    }

    /**
     * Check if a selector of an event matches current target
     *
     * @param event
     * @returns {*}
     */
    onChecker(event){
        if(event.target === document.querySelector(this.eventSelector)){
            event.stopPropagation();
            return this.eventCallback(event);
        }
    }

    ready(callback){
        document.addEventListener('DOMContentLoaded', callback);
    }

    change(callback){
        this.on('change', callback);
    }

    /************** ATTRIBUTES **************/

    /**
     * Get an attribute or property of an element
     *
     * @param attrName
     * @returns {*|*[]}
     */
    attr(attrName){

        let outputValue = [];

        for(let i=0; i<this.elements.length; i++){

            // Use property instead of attribute if possible
            if(typeof this.elements[i][attrName] !== undefined){
                outputValue.push(this.elements[i][attrName]);
            }else{
                outputValue.push(this.elements[i].getAttribute(attrName));
            }
        }

        // Return a single value instead of array if only one value is present
        return outputValue.length === 1 ? outputValue[0] : outputValue;
    }

    prop(propName, value){

        // Setting values
        if(typeof value !== "undefined"){
            for(let i=0; i<this.elements.length; i++){
                this.elements[i][propName] = value;
            }

            return this;

            // Getting values
        }else{

            let outputValue = [];

            for(let i=0; i<this.elements.length; i++){
                outputValue.push(this.elements[i][propName]);
            }

            // Return a single value instead of array if only one value is present
            return outputValue.length === 1 ? outputValue[0] : outputValue;
        }
    }

    /**
     * Set or get inner HTML
     *
     * @param value
     * @returns {*|*[]}
     */
    html(value){
        return typeof value !== 'undefined'
            ? this.prop('innerHTML', value)
            : this.prop('innerHTML');
    }

    /**
     * Set or get value of input tags
     *
     * @param value
     * @returns {*|*[]|undefined}
     */
    val(value){
        return typeof value !== 'undefined'
            ? this.prop('value', value)
            : this.prop('value');
    }

    data(name, value){
        return typeof value !== 'undefined'
            ? this.prop('apbct-data', name, value)
            : this.prop('apbct-data');
    }

    /************** END OF ATTRIBUTES **************/

    /************** FILTERS **************/

    /**
     * Check if the current elements are corresponding to filter
     *
     * @param filter
     * @returns {boolean}
     */
    is(filter){

        let outputValue = false;

        for(let elem of this.elements){
            outputValue ||= this.isElem(elem, filter);
        }

        return outputValue;
    }

    isElem(elemToCheck, filter){

        let is = false;
        let isRegisteredTagName = function(name){
            let newlyCreatedElement = document.createElement(name).constructor;
            return ! Boolean( ~[HTMLElement, HTMLUnknownElement].indexOf(newlyCreatedElement) );
        };

        // Check for filter function
        if(typeof filter === 'function') {
            is ||= filter.call(this, elemToCheck);
        }

        // Check for filter function
        if(typeof filter === 'string') {

            // Filter is tag name
            if( filter.match(/^[a-z]/) && isRegisteredTagName(filter) ){
                is ||= elemToCheck.tagName.toLowerCase() === filter.toLowerCase();

                // Filter is property
            }else if( filter.match(/^[a-z]/) ){
                is ||= Boolean(elemToCheck[filter]);

                // Filter is CSS selector
            }else {
                is ||= this.selector !== null
                    ? document.querySelector(this.selector + filter) !== null // If possible
                    : this.isWithoutSelector(elemToCheck, filter);                    // Search through all elems with such selector
            }
        }

        return is;
    }

    isWithoutSelector(elemToCheck, filter){

        let elems       = document.querySelectorAll(filter);
        let outputValue = false;

        for(let elem of elems){
            outputValue ||= elemToCheck === elem;
        }

        return outputValue;
    }

    filter(filter){

        this.selector = null;

        for( let i = this.elements.length - 1; i >= 0; i-- ){
            if( ! this.isElem(this.elements[i], filter) ){
                this.elements.splice(Number(i), 1);
            }
        }

        return this;
    }

    /************** NODES **************/

    parent(filter){

        this.select(this.elements[0].parentElement);

        if( typeof filter !== 'undefined' && ! this.is(filter) ){
            this.deselect();
        }

        return this;
    }

    parents(filter){

        this.select(this.elements[0]);

        for ( ; this.elements[ this.elements.length - 1].parentElement !== null ; ) {
            this.push(this.elements[ this.elements.length - 1].parentElement);
        }

        this.elements.splice(0,1); // Deleting initial element from the set

        if( typeof filter !== 'undefined' ){
            this.filter(filter);
        }

        return this;
    }

    children(filter){

        this.select(this.elements[0].children);

        if( typeof filter !== 'undefined' ){
            this.filter(filter);
        }

        return this;
    }

    siblings(filter){

        let current = this.elements[0]; // Remember current to delete it later

        this.parent();
        this.children(filter);
        this.elements.splice(this.elements.indexOf(current), 1); // Remove current element

        return this;
    }

    /************** DOM MANIPULATIONS **************/
    remove(){
        for(let elem of this.elements){
            elem.remove();
        }
    }

    after(content){
        for(let elem of this.elements){
            elem.after(content);
        }
    }

    append(content){
        for(let elem of this.elements){
            elem.append(content);
        }
    }

    /**************  ANIMATION  **************/
    fadeIn(time) {
        for(let elem of this.elements){
            elem.style.opacity = 0;
            elem.style.display = 'block';

            let last = +new Date();
            const tick = function () {
                elem.style.opacity = +elem.style.opacity + (new Date() - last) / time;
                last = +new Date();

                if (+elem.style.opacity < 1) {
                    (window.requestAnimationFrame && requestAnimationFrame(tick)) || setTimeout(tick, 16);
                }
            };

            tick();
        }
    }

    fadeOut(time) {
        for(let elem of this.elements){
            elem.style.opacity = 1;

            let last = +new Date();
            const tick = function () {
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
 * Enter point to ApbctCore class
 *
 * @param params
 * @returns {*}
 */
function apbct(params){
    return new ApbctCore()
        .select(params);
}
class ApbctXhr{

    xhr = new XMLHttpRequest();

    // Base parameters
    method   = 'POST'; // HTTP-request type
    url      = ''; // URL to send the request
    async    = true;
    user     = null; // HTTP-authorization username
    password = null; // HTTP-authorization password
    data     = {};   // Data to send


    // Optional params
    button      = null; // Button that should be disabled when request is performing
    spinner     = null; // Spinner that should appear when request is in process
    progressbar = null; // Progress bar for the current request
    context     = this; // Context
    callback    = null;
    onErrorCallback = null;

    responseType = 'json'; // Expected data type from server
    headers      = {};
    timeout      = 15000; // Request timeout in milliseconds

    methods_to_convert_data_to_URL = [
        'GET',
        'HEAD',
    ];

    body        = null;
    http_code   = 0;
    status_text = '';

    constructor(parameters){

        console.log('%cXHR%c started', 'color: red; font-weight: bold;', 'color: grey; font-weight: normal;');

        // Set class properties
        for( let key in parameters ){
            if( typeof this[key] !== 'undefined' ){
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

        if( ! this.url ){
            console.log('%cXHR%c not URL provided', 'color: red; font-weight: bold;', 'color: grey; font-weight: normal;')
            return false;
        }

        // Configure the request
        this.xhr.open(this.method, this.url, this.async, this.user, this.password);
        this.setHeaders();

        this.xhr.responseType = this.responseType;
        this.xhr.timeout      = this.timeout;

        /* EVENTS */
        // Monitoring status
        this.xhr.onreadystatechange = function(){
            this.onReadyStateChange();
        }.bind(this);

        // Run callback
        this.xhr.onload = function(){
            this.onLoad();
        }.bind(this);

        // On progress
        this.xhr.onprogress = function(event){
            this.onProgress(event);
        }.bind(this);

        // On error
        this.xhr.onerror = function(){
            this.onError();
        }.bind(this);

        this.xhr.ontimeout = function(){
            this.onTimeout();
        }.bind(this);

        // Send the request
        this.xhr.send(this.body);
    }

    prepare(){

        // Disable button
        if(this.button){
            this.button.setAttribute('disabled', 'disabled');
            this.button.style.cursor = 'not-allowed';
        }

        // Enable spinner
        if(this.spinner) {
            this.spinner.style.display = 'inline';
        }
    }

    complete(){

        this.http_code   = this.xhr.status;
        this.status_text = this.xhr.statusText;

        // Disable button
        if(this.button){
            this.button.removeAttribute('disabled');
            this.button.style.cursor = 'auto';
        }

        // Enable spinner
        if(this.spinner) {
            this.spinner.style.display = 'none';
        }

        if( this.progressbar ) {
            this.progressbar.fadeOut('slow');
        }
    }

    onReadyStateChange(){
        if (this.on_ready_state_change !== null && typeof this.on_ready_state_change === 'function'){
            this.on_ready_state_change();
        }
    }

    onProgress(event) {
        if (this.on_progress !== null && typeof this.on_progress === 'function'){
            this.on_progress();
        }
    }

    onError(){

        console.log('error');

        this.complete();
        this.error(
            this.http_code,
            this.status_text
        );

        if (this.onErrorCallback !== null && typeof this.onErrorCallback === 'function'){
            this.onErrorCallback(this.status_text);
        }
    }

    onTimeout(){
        this.complete();
        this.error(
            0,
            'timeout'
        );

        if (this.onErrorCallback !== null && typeof this.onErrorCallback === 'function'){
            this.onErrorCallback('Timeout');
        }
    }

    onLoad(){

        this.complete();

        if (this.responseType === 'json' ){
            if(this.xhr.response === null){
                this.error(this.http_code, this.status_text, 'No response');
                return false;
            }else if( typeof this.xhr.response.error !== 'undefined') {
                this.error(this.http_code, this.status_text, this.xhr.response.error);
                return false;
            }
        }

        if (this.callback !== null && typeof this.callback === 'function') {
            this.callback.call(this.context, this.xhr.response, this.data);
        }
    }

    error(http_code, status_text, additional_msg){

        let error_string = '';

        if( status_text === 'timeout' ){
            error_string += 'Server response timeout'

        }else if( http_code === 200 ){

            if( status_text === 'parsererror' ){
                error_string += 'Unexpected response from server. See console for details.';
            }else {
                error_string += 'Unexpected error. Status: ' + status_text + '.';
                if( typeof additional_msg !== 'undefined' )
                    error_string += ' Additional error info: ' + additional_msg;
            }

        }else if(http_code === 500){
            error_string += 'Internal server error.';

        }else {
            error_string += 'Unexpected response code:' + http_code;
        }

        this.errorOutput( error_string );
    }

    errorOutput(error_msg){
        console.log( '%c ctXHR error: %c' + error_msg, 'color: red;', 'color: grey;' );
    }

    setHeaders(){
        // Set headers if passed
        for( let header_name in this.headers ){
            if( typeof this.headers[header_name] !== 'undefined' ){
                this.xhr.setRequestHeader(header_name, this.headers[header_name]);
            }
        }
    }

    convertData()
    {
        // GET, HEAD request-type
        if( ~this.methods_to_convert_data_to_URL.indexOf( this.method ) ){
            return this.convertDataToURL();

            // POST request-type
        }else{
            return this.convertDataToBody()
        }
    }

    convertDataToURL(){
        let params_appendix = new URLSearchParams(this.data).toString();
        let params_prefix   = this.url.match(/^(https?:\/{2})?[a-z0-9.]+\?/) ? '&' : '?';
        this.url += params_prefix + params_appendix;

        return this.url;
    }

    /**
     *
     * @returns {null}
     */
    convertDataToBody()
    {
        this.body = new FormData();

        for (let dataKey in this.data) {
            this.body.append(
                dataKey,
                typeof this.data[dataKey] === 'object'
                    ? JSON.stringify(this.data[dataKey])
                    : this.data[dataKey]
            );
        }

        return this.body;
    }

    /**
     * Recursive
     *
     * Recursively decode JSON-encoded properties
     *
     * @param object
     * @returns {*}
     */
    deleteDoubleJSONEncoding(object){

        if( typeof object === 'object'){

            for (let objectKey in object) {

                // Recursion
                if( typeof object[objectKey] === 'object'){
                    object[objectKey] = this.deleteDoubleJSONEncoding(object[objectKey]);
                }

                // Common case (out)
                if(
                    typeof object[objectKey] === 'string' &&
                    object[objectKey].match(/^[\[{].*?[\]}]$/) !== null // is like JSON
                ){
                    let parsedValue = JSON.parse(object[objectKey]);
                    if( typeof parsedValue === 'object' ){
                        object[objectKey] = parsedValue;
                    }
                }
            }
        }

        return object;
    }
}
class ApbctAjax extends ApbctXhr{

    constructor(...args) {
        super(args[0]);
    }
}
class ApbctRest extends ApbctXhr{

    static default_route = ctPublicFunctions._rest_url + 'cleantalk-antispam/v1/';
    route         = '';

    constructor(...args) {
        args = args[0];
        args.url = ApbctRest.default_route + args.route;
        args.headers = {
            "X-WP-Nonce": ctPublicFunctions._rest_nonce
        };
        super(args);
    }
}

function ctSetCookie( cookies, value, expires ){

    let force_alternative_method_for_cookies = [
        'ct_sfw_pass_key',
        'ct_sfw_passed',
        'wordpress_apbct_antibot',
        'apbct_anticrawler_passed',
        'apbct_antiflood_passed',
        'apbct_email_encoder_passed'
    ]

    if( typeof cookies === 'string' && typeof value === 'string' || typeof value === 'number'){
        var skip_alt = cookies === 'ct_pointer_data';
        cookies = [ [ cookies, value, expires ] ];
    }

    // Cookies disabled
    if( ctPublicFunctions.data__cookies_type === 'none' ){
        let forced_alt_cookies_set = []
        cookies.forEach( function (item, i, arr	) {
            if (force_alternative_method_for_cookies.indexOf(item[0]) !== -1) {
                forced_alt_cookies_set.push(item)
            } else {
                apbctLocalStorage.set(item[0], encodeURIComponent(item[1]))
            }
        });
        if ( forced_alt_cookies_set.length > 0 ){
            ctSetAlternativeCookie(forced_alt_cookies_set)
        }
        ctNoCookieAttachHiddenFieldsToForms()
        // Using traditional cookies
    }else if( ctPublicFunctions.data__cookies_type === 'native' ){
        cookies.forEach( function (item, i, arr	) {
            var expires = typeof item[2] !== 'undefined' ? "expires=" + expires + '; ' : '';
            var ctSecure = location.protocol === 'https:' ? '; secure' : '';
            document.cookie = ctPublicFunctions.cookiePrefix + item[0] + "=" + encodeURIComponent(item[1]) + "; " + expires + "path=/; samesite=lax" + ctSecure;
        });

        // Using alternative cookies
    }else if( ctPublicFunctions.data__cookies_type === 'alternative' && ! skip_alt ) {
        ctSetAlternativeCookie(cookies)
    }
}

function ctSetAlternativeCookie(cookies){
    if (typeof (getJavascriptClientData) === "function"){
        //reprocess already gained cookies data
        cookies = getJavascriptClientData(cookies);
    } else {
        console.log('APBCT ERROR: getJavascriptClientData() is not loaded')
    }

    try {
        JSON.parse(cookies)
    } catch (e){
        console.log('APBCT ERROR: JSON parse error:' + e)
        return
    }

    // Using REST API handler
    if( ctPublicFunctions.data__ajax_type === 'rest' ){
        apbct_public_sendREST(
            'alt_sessions',
            {
                method: 'POST',
                data: { cookies: cookies }
            }
        );

        // Using AJAX request and handler
    } else if( ctPublicFunctions.data__ajax_type === 'admin_ajax' ) {
        apbct_public_sendAJAX(
            {
                action: 'apbct_alt_session__save__AJAX',
                cookies: cookies,
            },
            {
                notJson: 1,
            }
        );
    }
}

/**
 * Get cookie by name
 * @param name
 * @returns {string|undefined}
 */
function ctGetCookie(name) {
    var matches = document.cookie.match(new RegExp(
        "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
    ));
    return matches ? decodeURIComponent(matches[1]) : undefined;
}

function ctDeleteCookie(cookieName) {
    // Cookies disabled
    if( ctPublicFunctions.data__cookies_type === 'none' ){
        return;

    // Using traditional cookies
    }else if( ctPublicFunctions.data__cookies_type === 'native' ){

        var ctSecure = location.protocol === 'https:' ? '; secure' : '';
        document.cookie = cookieName + "=\"\"; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; samesite=lax" + ctSecure;

    // Using alternative cookies
    }else if( ctPublicFunctions.data__cookies_type === 'alternative' ){
        // @ToDo implement this logic
    }
}

function apbct_public_sendAJAX(data, params, obj){

    // Default params
    let _params            = [];
    _params["callback"]    = params.callback    || null;
    _params["onErrorCallback"] = params.onErrorCallback    || null;
    _params["callback_context"] = params.callback_context || null;
    _params["callback_params"] = params.callback_params || null;
    _params["async"]        = params.async || true;
    _params["notJson"]     = params.notJson     || null;
    _params["timeout"]     = params.timeout     || 15000;
    _params["obj"]         = obj                || null;
    _params["button"]      = params.button      || null;
    _params["progressbar"] = params.progressbar || null;
    _params["silent"]      = params.silent      || null;
    _params["no_nonce"]    = params.no_nonce    || null;
    _params["data"]        = data;
    _params["url"]         = ctPublicFunctions._ajax_url;

    if(typeof (data) === 'string') {
        if( ! _params["no_nonce"] ) {
            _params["data"] = _params["data"] + '&_ajax_nonce=' + ctPublicFunctions._ajax_nonce;
        }
        _params["data"] = _params["data"] + '&no_cache=' + Math.random()
    } else {
        if( ! _params["no_nonce"] ) {
            _params["data"]._ajax_nonce = ctPublicFunctions._ajax_nonce;
        }
        _params["data"].no_cache = Math.random();
    }

    new ApbctCore().ajax(_params);
}

function apbct_public_sendREST( route, params ) {

    let _params         = [];
    _params["route"]    = route;
    _params["callback"] = params.callback || null;
    _params["onErrorCallback"] = params.onErrorCallback    || null;
    _params["data"]     = params.data     || [];
    _params["method"]   = params.method   || 'POST';

    new ApbctCore().rest(_params);
}

let apbctLocalStorage = {
    get : function(key, property) {
        if ( typeof property === 'undefined' ) {
            property = 'value';
        }
        const storageValue = localStorage.getItem(key);
        if ( storageValue !== null ) {
            try {
                const json = JSON.parse(storageValue);
                return json.hasOwnProperty(property) ? JSON.parse(json[property]) : json;
            } catch (e) {
                return storageValue;
            }
        }
        return false;
    },
    set : function(key, value, is_json = true) {
        if (is_json){
            let objToSave = {'value': JSON.stringify(value), 'timestamp': Math.floor(new Date().getTime() / 1000)};
            localStorage.setItem(key, JSON.stringify(objToSave));
        } else {
            localStorage.setItem(key, value);
        }
    },
    isAlive : function(key, maxLifetime) {
        if ( typeof maxLifetime === 'undefined' ) {
            maxLifetime = 86400;
        }
        const keyTimestamp = this.get(key, 'timestamp');
        return keyTimestamp + maxLifetime > Math.floor(new Date().getTime() / 1000);
    },
    isSet : function(key) {
        return localStorage.getItem(key) !== null;
    },
    delete : function (key) {
        localStorage.removeItem(key);
    },
    getCleanTalkData : function () {
        let data = {}
        for(let i=0; i<localStorage.length; i++) {
            let key = localStorage.key(i);
            if (key.indexOf('ct_') !==-1 || key.indexOf('apbct_') !==-1){
                data[key.toString()] = apbctLocalStorage.get(key)
            }
        }
        return data
    },

}
var ct_date = new Date(),
	ctTimeMs = new Date().getTime(),
	ctMouseEventTimerFlag = true, //Reading interval flag
	ctMouseData = [],
	ctMouseDataCounter = 0,
	ctCheckedEmails = {};

function apbct_attach_event_handler(elem, event, callback){
	if(typeof window.addEventListener === "function") elem.addEventListener(event, callback);
	else                                              elem.attachEvent(event, callback);
}

function apbct_remove_event_handler(elem, event, callback){
	if(typeof window.removeEventListener === "function") elem.removeEventListener(event, callback);
	else                                                 elem.detachEvent(event, callback);
}

//Writing first key press timestamp
var ctFunctionFirstKey = function output(event){
	var KeyTimestamp = Math.floor(new Date().getTime()/1000);
	ctSetCookie("ct_fkp_timestamp", KeyTimestamp);
	ctKeyStopStopListening();
};

if (ctPublic.data__key_is_ok) {
	//Reading interval
	var ctMouseReadInterval = setInterval(function(){
		ctMouseEventTimerFlag = true;
	}, 150);

	//Writting interval
	var ctMouseWriteDataInterval = setInterval(function(){
		ctSetCookie("ct_pointer_data", JSON.stringify(ctMouseData));
	}, 1200);
}


//Logging mouse position each 150 ms
var ctFunctionMouseMove = function output(event){
	ctSetMouseMoved();
	if(ctMouseEventTimerFlag === true){

		ctMouseData.push([
			Math.round(event.clientY),
			Math.round(event.clientX),
			Math.round(new Date().getTime() - ctTimeMs)
		]);

		ctMouseDataCounter++;
		ctMouseEventTimerFlag = false;
		if(ctMouseDataCounter >= 50){
			ctMouseStopData();
		}
	}
};

//Stop mouse observing function
function ctMouseStopData(){
	apbct_remove_event_handler(document, "mousemove", ctFunctionMouseMove);
	clearInterval(ctMouseReadInterval);
	clearInterval(ctMouseWriteDataInterval);
}

//Stop key listening function
function ctKeyStopStopListening(){
	apbct_remove_event_handler(document, "mousedown", ctFunctionFirstKey);
	apbct_remove_event_handler(document, "keydown", ctFunctionFirstKey);
}

function checkEmail(e) {
	var current_email = e.target.value;
	if (current_email && !(current_email in ctCheckedEmails)) {
		// Using REST API handler
		if( ctPublicFunctions.data__ajax_type === 'rest' ){
			apbct_public_sendREST(
				'check_email_before_post',
				{
					method: 'POST',
					data: {'email' : current_email},
					callback: function (result) {
						if (result.result) {
							ctCheckedEmails[current_email] = {'result' : result.result, 'timestamp': Date.now() / 1000 |0};
							ctSetCookie('ct_checked_emails', JSON.stringify(ctCheckedEmails));
						}
					},
				}
			);
			// Using AJAX request and handler
		} else if( ctPublicFunctions.data__ajax_type === 'admin_ajax' ) {
			apbct_public_sendAJAX(
				{
					action: 'apbct_email_check_before_post',
					email : current_email,
				},
				{
					callback: function (result) {
						if (result.result) {
							ctCheckedEmails[current_email] = {'result' : result.result, 'timestamp': Date.now() / 1000 |0};
							ctSetCookie('ct_checked_emails', JSON.stringify(ctCheckedEmails));
						}
					},
				}
			);
		}
	}
}

function ctSetPixelImg(pixelUrl) {
	ctSetCookie('apbct_pixel_url', pixelUrl);
	if( +ctPublic.pixel__enabled ){
		if( ! document.getElementById('apbct_pixel') ) {
			let insertedImg = document.createElement('img');
			insertedImg.setAttribute('alt', 'CleanTalk Pixel');
			insertedImg.setAttribute('id', 'apbct_pixel');
			insertedImg.setAttribute('style', 'display: none; left: 99999px;');
			insertedImg.setAttribute('src', pixelUrl);
			apbct('body').append(insertedImg);
		}
	}
}

function ctGetPixelUrl() {
	// Check if pixel is already in localstorage and is not outdated
	let local_storage_pixel_url = apbctLocalStorage.get('apbct_pixel_url');
	if ( local_storage_pixel_url !== false ) {
		if ( apbctLocalStorage.isAlive('apbct_pixel_url', 3600 * 3) ) {
			apbctLocalStorage.delete('apbct_pixel_url')
		} else {
			//if so - load pixel from localstorage and draw it skipping AJAX
			ctSetPixelImg(local_storage_pixel_url);
			return;
		}
	}
	// Using REST API handler
	if( ctPublicFunctions.data__ajax_type === 'rest' ){
		apbct_public_sendREST(
			'apbct_get_pixel_url',
			{
				method: 'POST',
				callback: function (result) {
					if (result) {
						//set  pixel url to localstorage
						if ( ! apbctLocalStorage.get('apbct_pixel_url') ){
							//set pixel to the storage
							apbctLocalStorage.set('apbct_pixel_url', result)
							//update pixel data in the hidden fields
							ctNoCookieAttachHiddenFieldsToForms()
						}
						//then run pixel drawing
						ctSetPixelImg(result);
					}
				},
			}
		);
		// Using AJAX request and handler
	}else{
		apbct_public_sendAJAX(
			{
				action: 'apbct_get_pixel_url',
			},
			{
				notJson: true,
				callback: function (result) {
					if (result) {
						//set  pixel url to localstorage
						if ( ! apbctLocalStorage.get('apbct_pixel_url') ){
							//set pixel to the storage
							apbctLocalStorage.set('apbct_pixel_url', result)
							//update pixel data in the hidden fields
							ctNoCookieAttachHiddenFieldsToForms()
						}
						//then run pixel drawing
						ctSetPixelImg(result);
					}
				},
			}
		);
	}
}

function ctSetHasScrolled() {
	if( ! apbctLocalStorage.isSet('ct_has_scrolled') || ! apbctLocalStorage.get('ct_has_scrolled') ) {
		ctSetCookie("ct_has_scrolled", 'true');
		apbctLocalStorage.set('ct_has_scrolled', true);
	}
}

function ctSetMouseMoved() {
	if( ! apbctLocalStorage.isSet('ct_mouse_moved') || ! apbctLocalStorage.get('ct_mouse_moved') ) {
		ctSetCookie("ct_mouse_moved", 'true');
		apbctLocalStorage.set('ct_mouse_moved', true);
	}
}

function ctPreloadLocalStorage(){
	if (ctPublic.data__to_local_storage){
		let data = Object.entries(ctPublic.data__to_local_storage)
		data.forEach(([key, value]) => {
			apbctLocalStorage.set(key,value)
		});
	}
}

if (ctPublic.data__key_is_ok) {
	apbct_attach_event_handler(document, "mousemove", ctFunctionMouseMove);
	apbct_attach_event_handler(document, "mousedown", ctFunctionFirstKey);
	apbct_attach_event_handler(document, "keydown", ctFunctionFirstKey);
	apbct_attach_event_handler(document, "scroll", ctSetHasScrolled);
}

// Ready function
function apbct_ready(){

	ctPreloadLocalStorage()

	let cookiesType = apbctLocalStorage.get('ct_cookies_type');
	if ( ! cookiesType || cookiesType !== ctPublic.data__cookies_type ) {
		apbctLocalStorage.set('ct_cookies_type', ctPublic.data__cookies_type);
		apbctLocalStorage.delete('ct_mouse_moved');
		apbctLocalStorage.delete('ct_has_scrolled');
	}

	// Collect scrolling info
	var initCookies = [
		["ct_ps_timestamp", Math.floor(new Date().getTime() / 1000)],
		["ct_fkp_timestamp", "0"],
		["ct_pointer_data", "0"],
		["ct_timezone", ct_date.getTimezoneOffset()/60*(-1) ],
		["ct_screen_info", apbctGetScreenInfo()],
		["apbct_headless", navigator.webdriver],
	];

	apbctLocalStorage.set('ct_ps_timestamp', Math.floor(new Date().getTime() / 1000));
	apbctLocalStorage.set('ct_fkp_timestamp', "0");
	apbctLocalStorage.set('ct_pointer_data', "0");
	apbctLocalStorage.set('ct_timezone', ct_date.getTimezoneOffset()/60*(-1) );
	apbctLocalStorage.set('ct_screen_info', apbctGetScreenInfo());
	apbctLocalStorage.set('apbct_headless', navigator.webdriver);

	if( ctPublic.data__cookies_type !== 'native' ) {
		initCookies.push(['apbct_visible_fields', '0']);
	} else {
		// Delete all visible fields cookies on load the page
		var cookiesArray = document.cookie.split(";");
		if( cookiesArray.length !== 0 ) {
			for ( var i = 0; i < cookiesArray.length; i++ ) {
				var currentCookie = cookiesArray[i].trim();
				var cookieName = currentCookie.split("=")[0];
				if( cookieName.indexOf("apbct_visible_fields_") === 0 ) {
					ctDeleteCookie(cookieName);
				}
			}
		}
	}

	if( +ctPublic.pixel__setting ){
		if( +ctPublic.pixel__enabled ){
			ctGetPixelUrl()
		} else {
			initCookies.push(['apbct_pixel_url', ctPublic.pixel__url]);
		}
	}

	if ( +ctPublic.data__email_check_before_post) {
		initCookies.push(['ct_checked_emails', '0']);
		apbct("input[type = 'email'], #email").on('blur', checkEmail);
	}

	if (apbctLocalStorage.isSet('ct_checkjs')) {
		initCookies.push(['ct_checkjs', apbctLocalStorage.get('ct_checkjs')]);
	} else {
		initCookies.push(['ct_checkjs', 0]);
	}

	ctSetCookie(initCookies);

	setTimeout(function(){

		ctNoCookieAttachHiddenFieldsToForms()

		for(var i = 0; i < document.forms.length; i++){
			var form = document.forms[i];

			//Exclusion for forms
			if (
				+ctPublic.data__visible_fields_required === 0 ||
				(form.method.toString().toLowerCase() === 'get' && form.querySelectorAll('.nf-form-content').length === 0) ||
				form.classList.contains('slp_search_form') || //StoreLocatorPlus form
				form.parentElement.classList.contains('mec-booking') ||
				form.action.toString().indexOf('activehosted.com') !== -1 || // Active Campaign
				(form.id && form.id === 'caspioform') || //Caspio Form
				(form.classList && form.classList.contains('tinkoffPayRow')) || // TinkoffPayForm
				(form.classList && form.classList.contains('give-form')) || // GiveWP
				(form.id && form.id === 'ult-forgot-password-form') || //ult forgot password
				(form.id && form.id.toString().indexOf('calculatedfields') !== -1) || // CalculatedFieldsForm
				(form.id && form.id.toString().indexOf('sac-form') !== -1) || // Simple Ajax Chat
				(form.id && form.id.toString().indexOf('cp_tslotsbooking_pform') !== -1) || // WP Time Slots Booking Form
				(form.name && form.name.toString().indexOf('cp_tslotsbooking_pform') !== -1)  || // WP Time Slots Booking Form
				form.action.toString() === 'https://epayment.epymtservice.com/epay.jhtml' || // Custom form
				(form.name && form.name.toString().indexOf('tribe-bar-form') !== -1)  // The Events Calendar
			) {
				continue;
			}

			var hiddenInput = document.createElement( 'input' );
			hiddenInput.setAttribute( 'type', 'hidden' );
			hiddenInput.setAttribute( 'id', 'apbct_visible_fields_' + i );
			hiddenInput.setAttribute( 'name', 'apbct_visible_fields');
			var visibleFieldsToInput = {};
			visibleFieldsToInput[0] = apbct_collect_visible_fields(form);
			hiddenInput.value = btoa(JSON.stringify(visibleFieldsToInput));
			form.append( hiddenInput );

			form.onsubmit_prev = form.onsubmit;

			form.ctFormIndex = i;
			form.onsubmit = function (event) {

				if ( ctPublic.data__cookies_type !== 'native' && typeof event.target.ctFormIndex !== 'undefined' ) {

					var visible_fields = {};
					visible_fields[0] = apbct_collect_visible_fields(this);
					apbct_visible_fields_set_cookie( visible_fields, event.target.ctFormIndex );
				}

				// Call previous submit action
				if (event.target.onsubmit_prev instanceof Function) {
					setTimeout(function () {
						event.target.onsubmit_prev.call(event.target, event);
					}, 500);
				}
			};
		}

	}, 1000);

	// Listen clicks on encoded emails
	let encodedEmailNodes = document.querySelectorAll("[data-original-string]");
	ctPublic.encodedEmailNodes = encodedEmailNodes
	if (encodedEmailNodes.length) {
		for (let i = 0; i < encodedEmailNodes.length; ++i) {
			if (
				encodedEmailNodes[i].parentElement.href ||
				encodedEmailNodes[i].parentElement.parentElement.href
			) {
				// Skip listening click on hyperlinks
				continue;
			}
			encodedEmailNodes[i].addEventListener('click', ctFillDecodedEmailHandler);
		}
	}
}
if (ctPublic.data__key_is_ok) {
	if (document.readyState !== 'loading') {
		apbct_ready();
	} else {
		apbct_attach_event_handler(document, "DOMContentLoaded", apbct_ready);
	}
}

function ctFillDecodedEmailHandler(event) {
	this.removeEventListener('click', ctFillDecodedEmailHandler);
	//remember click_source
	let click_source = this
	//globally remember if emails is mixed with mailto
	ctPublic.encodedEmailNodesIsMixed = false
	//get fade
	document.body.classList.add('apbct-popup-fade')
	//popup show
	let encoder_popup = document.getElementById('apbct_popup')
	if (!encoder_popup){
		let waiting_popup = document.createElement('div')
		waiting_popup.setAttribute('class', 'apbct-popup')
		waiting_popup.setAttribute('id', 'apbct_popup')
		let popup_text = document.createElement('p')
		popup_text.setAttribute('id', 'apbct_popup_text')
		popup_text.innerText = "Please wait while CleanTalk decoding email addresses.."
		waiting_popup.append(popup_text)
		document.body.append(waiting_popup)
	} else {
		encoder_popup.setAttribute('style','display: inherit')
		document.getElementById('apbct_popup_text').innerHTML = "Please wait while CleanTalk decoding email addresses.."
	}

	apbctAjaxEmailDecodeBulk(event,ctPublic.encodedEmailNodes,click_source)
}

function apbctAjaxEmailDecodeBulk(event, encodedEmailNodes, click_source){
	//collect data
	const javascriptClientData = getJavascriptClientData();
	let data = {
		event_javascript_data: javascriptClientData,
		post_url: document.location.href,
		referrer: document.referrer,
		encodedEmails: ''
	};
	let encoded_emails_collection = {}
	for (let i = 0; i < encodedEmailNodes.length; i++){
		//disable click for mailto
		if (typeof encodedEmailNodes[i].href !== 'undefined' && encodedEmailNodes[i].href.indexOf('mailto:') === 0) {
			event.preventDefault()
			ctPublic.encodedEmailNodesIsMixed = true;
		}

		// Adding a tooltip
		let apbctTooltip = document.createElement('div');
		apbctTooltip.setAttribute('class', 'apbct-tooltip');
		apbct(encodedEmailNodes[i]).append(apbctTooltip);

		//collect encoded email strings
		encoded_emails_collection[i] = encodedEmailNodes[i].dataset.originalString;
	}

	//JSONify encoded email strings
	data.encodedEmails = JSON.stringify(encoded_emails_collection)

	// Using REST API handler
	if( ctPublicFunctions.data__ajax_type === 'rest' ){
		apbct_public_sendREST(
			'apbct_decode_email',
			{
				data: data,
				method: 'POST',
				callback: function(result) {
					//set alternative cookie to skip next pages encoding
					ctSetCookie('apbct_email_encoder_passed','1')
					apbctEmailEncoderCallbackBulk(result, encodedEmailNodes, click_source);
				},
				onErrorCallback: function (res) {
					resetEncodedNodes()
					ctShowDecodeComment(res);
				},
			}
		);

		// Using AJAX request and handler
	}else{
		data.action = 'apbct_decode_email';
		apbct_public_sendAJAX(
			data,
			{
				notJson: false,
				callback: function(result) {
					//set alternative cookie to skip next pages encoding
					ctSetCookie('apbct_email_encoder_passed','1')
					apbctEmailEncoderCallbackBulk(result, encodedEmailNodes, click_source);
				},
				onErrorCallback: function (res) {
					resetEncodedNodes()
					ctShowDecodeComment(res);
				},
			}
		);
	}
}

function apbctEmailEncoderCallbackBulk(result, encodedEmailNodes, click_source){

	if (result.success && result.data[0].is_allowed === true){
		//start process of visual decoding
		setTimeout(function(){
			for (let i = 0; i < encodedEmailNodes.length; i++) {
				//chek what is what
				let current_result_data
				result.data.forEach((row) => {
					if (row.encoded_email === encodedEmailNodes[i].dataset.originalString){
						current_result_data = row
					}
				})
				//quit case on cloud block
				if (current_result_data.is_allowed === false){
					break;
				}
				//handler for mailto
				if (typeof encodedEmailNodes[i].href !== 'undefined' && encodedEmailNodes[i].href.indexOf('mailto:') === 0) {
					let encodedEmail = encodedEmailNodes[i].href.replace('mailto:', '');
					let baseElementContent = encodedEmailNodes[i].innerHTML;
					encodedEmailNodes[i].innerHTML = baseElementContent.replace(encodedEmail, current_result_data.decoded_email);
					encodedEmailNodes[i].href = 'mailto:' + current_result_data.decoded_email;
				}
				// fill the nodes
				ctProcessDecodedDataResult(current_result_data, encodedEmailNodes[i]);
				//remove listeners
				encodedEmailNodes[i].removeEventListener('click', ctFillDecodedEmailHandler)
			}
			//popup remove
			let popup = document.getElementById('apbct_popup')
			if (popup !== null){
				document.body.classList.remove('apbct-popup-fade')
				popup.setAttribute('style','display:none')
				//click on mailto if so
				if (ctPublic.encodedEmailNodesIsMixed){
					click_source.click();
				}
			}
		}, 3000);
	} else {
		if (result.success){
			resetEncodedNodes()
			ctShowDecodeComment('Blocked: ' + result.data[0].comment)
		} else {
			resetEncodedNodes()
			ctShowDecodeComment('Cannot connect with CleanTalk server: ' + result.data[0].comment)
		}
	}
}

function resetEncodedNodes(){
	if (typeof ctPublic.encodedEmailNodes !== 'undefined'){
		ctPublic.encodedEmailNodes.forEach(function (element){
			element.addEventListener('click', ctFillDecodedEmailHandler);
		})
	}
}

function getJavascriptClientData(common_cookies = []) {
	let resultDataJson = {};

	resultDataJson.apbct_headless = !!ctGetCookie(ctPublicFunctions.cookiePrefix + 'apbct_headless');
	resultDataJson.apbct_pixel_url = ctGetCookie(ctPublicFunctions.cookiePrefix + 'apbct_pixel_url');
	resultDataJson.ct_checked_emails = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_checked_emails');
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

	// collecting data from cookies
	const ctMouseMovedCookie = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_mouse_moved');
	const ctHasScrolledCookie = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_has_scrolled');
	const ctCookiesTypeCookie = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_cookies_type');

	resultDataJson.ct_mouse_moved = ctMouseMovedLocalStorage !== undefined ? ctMouseMovedLocalStorage : ctMouseMovedCookie;
	resultDataJson.ct_has_scrolled = ctHasScrolledLocalStorage !== undefined ? ctHasScrolledLocalStorage : ctHasScrolledCookie;
	resultDataJson.ct_cookies_type = ctCookiesTypeLocalStorage !== undefined ? ctCookiesTypeLocalStorage : ctCookiesTypeCookie;

	if (
		typeof (common_cookies) === "object"
		&& common_cookies !== []
	){
		for (let i = 0; i < common_cookies.length; ++i){
			if ( typeof (common_cookies[i][1]) === "object" ){
				//this is for handle SFW cookies
				resultDataJson[common_cookies[i][1][0]] = common_cookies[i][1][1]
			} else {
				resultDataJson[common_cookies[i][0]] = common_cookies[i][1]
			}
		}
	} else {
		console.log('APBCT JS ERROR: Collecting data type mismatch')
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
 * @param object
 * @returns {*}
 */
function removeDoubleJsonEncoding(object){

	if( typeof object === 'object'){

		for (let objectKey in object) {

			// Recursion
			if( typeof object[objectKey] === 'object'){
				object[objectKey] = removeDoubleJsonEncoding(object[objectKey]);
			}

			// Common case (out)
			if(
				typeof object[objectKey] === 'string' &&
				object[objectKey].match(/^[\[{].*?[\]}]$/) !== null // is like JSON
			){
				let parsedValue = JSON.parse(object[objectKey]);
				if( typeof parsedValue === 'object' ){
					object[objectKey] = parsedValue;
				}
			}
		}
	}

	return object;
}

function ctProcessDecodedDataResult(response, targetElement) {

	targetElement.setAttribute('title', '');
	targetElement.removeAttribute('style');
	ctFillDecodedEmail(targetElement, response.decoded_email);
}

function ctFillDecodedEmail(target, email){

	apbct(target).html(
		apbct(target)
			.html()
			.replace(/.+?(<div class=["']apbct-tooltip["'].+?<\/div>)/, email + "$1")
	);
}

function ctShowDecodeComment(comment){

	if( ! comment ){
		comment = 'Can not decode email. Unknown reason'
	}

	let popup = document.getElementById('apbct_popup')
	let popup_text = document.getElementById('apbct_popup_text')
	if (popup !== null){
		document.body.classList.remove('apbct-popup-fade')
		popup_text.innerText = "CleanTalk email decoder: " + comment
		setTimeout(function(){
			popup.setAttribute('style','display:none')
		},3000)
	}
}

function apbct_collect_visible_fields( form ) {

	// Get only fields
	var inputs = [],
		inputs_visible = '',
		inputs_visible_count = 0,
		inputs_invisible = '',
		inputs_invisible_count = 0,
		inputs_with_duplicate_names = [];

	for(var key in form.elements){
		if(!isNaN(+key))
			inputs[key] = form.elements[key];
	}

	// Filter fields
	inputs = inputs.filter(function(elem){

		// Filter already added fields
		if( inputs_with_duplicate_names.indexOf( elem.getAttribute('name') ) !== -1 ){
			return false;
		}
		// Filter inputs with same names for type == radio
		if( -1 !== ['radio', 'checkbox'].indexOf( elem.getAttribute("type") )){
			inputs_with_duplicate_names.push( elem.getAttribute('name') );
			return false;
		}
		return true;
	});

	// Visible fields
	inputs.forEach(function(elem, i, elements){
		// Unnecessary fields
		if(
			elem.getAttribute("type")         === "submit" || // type == submit
			elem.getAttribute('name')         === null     ||
			elem.getAttribute('name')         === 'ct_checkjs'
		) {
			return;
		}
		// Invisible fields
		if(
			getComputedStyle(elem).display    === "none" ||   // hidden
			getComputedStyle(elem).visibility === "hidden" || // hidden
			getComputedStyle(elem).opacity    === "0" ||      // hidden
			elem.getAttribute("type")         === "hidden" // type == hidden
		) {
			if( elem.classList.contains("wp-editor-area") ) {
				inputs_visible += " " + elem.getAttribute("name");
				inputs_visible_count++;
			} else {
				inputs_invisible += " " + elem.getAttribute("name");
				inputs_invisible_count++;
			}
		}
		// Visible fields
		else {
			inputs_visible += " " + elem.getAttribute("name");
			inputs_visible_count++;
		}

	});

	inputs_invisible = inputs_invisible.trim();
	inputs_visible = inputs_visible.trim();

	return {
		visible_fields : inputs_visible,
		visible_fields_count : inputs_visible_count,
		invisible_fields : inputs_invisible,
		invisible_fields_count : inputs_invisible_count,
	}

}

function apbct_visible_fields_set_cookie( visible_fields_collection, form_id ) {

	var collection = typeof visible_fields_collection === 'object' && visible_fields_collection !== null ?  visible_fields_collection : {};

	if( ctPublic.data__cookies_type === 'native' ) {
		for ( var i in collection ) {
			if ( i > 10 ) {
				// Do not generate more than 10 cookies
				return;
			}
			var collectionIndex = form_id !== undefined ? form_id : i;
			ctSetCookie("apbct_visible_fields_" + collectionIndex, JSON.stringify( collection[i] ) );
		}
	} else {
		if (ctPublic.data__cookies_type === 'none'){
			ctSetCookie("apbct_visible_fields", JSON.stringify( collection[0] ) );
		} else {
			ctSetCookie("apbct_visible_fields", JSON.stringify( collection ) );
		}

	}
}

function apbct_js_keys__set_input_value(result, data, params, obj){
	if( document.querySelectorAll('[name^=ct_checkjs]').length > 0 ) {
		var elements = document.querySelectorAll('[name^=ct_checkjs]');
		for ( var i = 0; i < elements.length; i++ ) {
			elements[i].value = result.js_key;
		}
	}
}

function apbctGetScreenInfo() {
	return JSON.stringify({
		fullWidth : document.documentElement.scrollWidth,
		fullHeight : Math.max(
			document.body.scrollHeight, document.documentElement.scrollHeight,
			document.body.offsetHeight, document.documentElement.offsetHeight,
			document.body.clientHeight, document.documentElement.clientHeight
		),
		visibleWidth : document.documentElement.clientWidth,
		visibleHeight : document.documentElement.clientHeight,
	});
}

if(typeof jQuery !== 'undefined') {

	// Capturing responses and output block message for unknown AJAX forms
	jQuery(document).ajaxComplete(function (event, xhr, settings) {
		if (xhr.responseText && xhr.responseText.indexOf('"apbct') !== -1) {
			try {
				var response = JSON.parse(xhr.responseText);
			} catch (e) {
				console.log(e.toString());
				return;
			}
			ctParseBlockMessage(response);
		}
	});
}

function ctParseBlockMessage(response) {

	if (typeof response.apbct !== 'undefined') {
		response = response.apbct;
		if (response.blocked) {
			document.dispatchEvent(
				new CustomEvent( "apbctAjaxBockAlert", {
					bubbles: true,
					detail: { message: response.comment }
				} )
			);

			// Show the result by modal
			cleantalkModal.loaded = response.comment;
			cleantalkModal.open();

			if(+response.stop_script == 1)
				window.stop();
		}
	}
}

function ctSetPixelUrlLocalstorage(ajax_pixel_url) {
	//set pixel to the storage
	ctSetCookie('apbct_pixel_url', ajax_pixel_url)
}

function ctNoCookieConstructHiddenField(){
	let field = ''
	let no_cookie_data = apbctLocalStorage.getCleanTalkData()
	no_cookie_data = JSON.stringify(no_cookie_data)
	no_cookie_data = btoa(no_cookie_data)
	field = document.createElement('input')
	field.setAttribute('id','ct_no_cookie_hidden_field')
	field.setAttribute('name','ct_no_cookie_hidden_field')
	field.setAttribute('value', no_cookie_data)
	field.setAttribute('type', 'hidden')
	return field
}

function ctNoCookieGetForms(){
	let forms = document.forms
	if (forms) {
		return forms
	}
	return false
}

function ctNoCookieAttachHiddenFieldsToForms(){

	if (ctPublic.data__cookies_type !== 'none'){
		return
	}

	let forms = ctNoCookieGetForms()

	if (forms){
		//clear previous hidden set
		let elements = document.getElementsByName('ct_no_cookie_hidden_field')
		if (elements){
			for (let j = 0; j < elements.length; j++) {
				elements[j].parentNode.removeChild(elements[j])
			}
		}
		for ( let i = 0; i < forms.length; i++ ){
			//ignore forms with get method @todo We need to think about this
			if (document.forms[i].getAttribute('method') === null ||
				document.forms[i].getAttribute('method').toLowerCase() === 'post'){
				// add new set
				document.forms[i].append(ctNoCookieConstructHiddenField())
			}
		}
	}

}
/* Cleantalk Modal object */
let cleantalkModal = {

    // Flags
    loaded: false,
    loading: false,
    opened: false,
    opening: false,

    // Methods
    load: function( action ) {
        if( ! this.loaded ) {
            this.loading = true;
            callback = function( result, data, params, obj ) {
                cleantalkModal.loading = false;
                cleantalkModal.loaded = result;
                document.dispatchEvent(
                    new CustomEvent( "cleantalkModalContentLoaded", {
                        bubbles: true,
                    } )
                );
            };
            if( typeof apbct_admin_sendAJAX === "function" ) {
                apbct_admin_sendAJAX( { 'action' : action }, { 'callback': callback, 'notJson': true } );
            } else {
                apbct_public_sendAJAX( { 'action' : action }, { 'callback': callback, 'notJson': true } );
            }

        }
    },

    open: function () {
        /* Cleantalk Modal CSS start */
        var renderCss = function () {
            var cssStr = '';
            for ( let key in this.styles ) {
                cssStr += key + ':' + this.styles[key] + ';';
            }
            return cssStr;
        };
        var overlayCss = {
            styles: {
                "z-index": "9999",
                "position": "fixed",
                "top": "0",
                "left": "0",
                "width": "100%",
                "height": "100%",
                "background": "rgba(0,0,0,0.5)",
                "display": "flex",
                "justify-content" : "center",
                "align-items" : "center",
            },
            toString: renderCss
        };
        var innerCss = {
            styles: {
                "position" : "relative",
                "padding" : "30px",
                "background" : "#FFF",
                "border" : "1px solid rgba(0,0,0,0.75)",
                "border-radius" : "4px",
                "box-shadow" : "7px 7px 5px 0px rgba(50,50,50,0.75)",
            },
            toString: renderCss
        };
        var closeCss = {
            styles: {
                "position" : "absolute",
                "background" : "#FFF",
                "width" : "20px",
                "height" : "20px",
                "border" : "2px solid rgba(0,0,0,0.75)",
                "border-radius" : "15px",
                "cursor" : "pointer",
                "top" : "-8px",
                "right" : "-8px",
                "box-sizing" : "content-box",
            },
            toString: renderCss
        };
        var closeCssBefore = {
            styles: {
                "content" : "\"\"",
                "display" : "block",
                "position" : "absolute",
                "background" : "#000",
                "border-radius" : "1px",
                "width" : "2px",
                "height" : "16px",
                "top" : "2px",
                "left" : "9px",
                "transform" : "rotate(45deg)",
            },
            toString: renderCss
        };
        var closeCssAfter = {
            styles: {
                "content" : "\"\"",
                "display" : "block",
                "position" : "absolute",
                "background" : "#000",
                "border-radius" : "1px",
                "width" : "2px",
                "height" : "16px",
                "top" : "2px",
                "left" : "9px",
                "transform" : "rotate(-45deg)",
            },
            toString: renderCss
        };
        var bodyCss = {
            styles: {
                "overflow" : "hidden",
            },
            toString: renderCss
        };
        var cleantalkModalStyle = document.createElement( 'style' );
        cleantalkModalStyle.setAttribute( 'id', 'cleantalk-modal-styles' );
        cleantalkModalStyle.innerHTML = 'body.cleantalk-modal-opened{' + bodyCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-overlay{' + overlayCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close{' + closeCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close:before{' + closeCssBefore + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close:after{' + closeCssAfter + '}';
        document.body.append( cleantalkModalStyle );
        /* Cleantalk Modal CSS end */

        var overlay = document.createElement( 'div' );
        overlay.setAttribute( 'id', 'cleantalk-modal-overlay' );
        document.body.append( overlay );

        document.body.classList.add( 'cleantalk-modal-opened' );

        var inner = document.createElement( 'div' );
        inner.setAttribute( 'id', 'cleantalk-modal-inner' );
        inner.setAttribute( 'style', innerCss );
        overlay.append( inner );

        var close = document.createElement( 'div' );
        close.setAttribute( 'id', 'cleantalk-modal-close' );
        inner.append( close );

        var content = document.createElement( 'div' );
        if ( this.loaded ) {
            var urlRegex = /(https?:\/\/[^\s]+)/g;
            var renderedMsg = this.loaded.replace(urlRegex, '<a href="$1" target="_blank">$1</a>');
            content.innerHTML = renderedMsg;
        } else {
            content.innerHTML = 'Loading...';
            // @ToDo Here is hardcoded parameter. Have to get this from a 'data-' attribute.
            this.load( 'get_options_template' );
        }
        content.setAttribute( 'id', 'cleantalk-modal-content' );
        inner.append( content );

        this.opened = true;
    },

    close: function () {
        document.body.classList.remove( 'cleantalk-modal-opened' );
        document.getElementById( 'cleantalk-modal-overlay' ).remove();
        document.getElementById( 'cleantalk-modal-styles' ).remove();
        document.dispatchEvent(
            new CustomEvent( "cleantalkModalClosed", {
                bubbles: true,
            } )
        );
    }

};

/* Cleantalk Modal helpers */
document.addEventListener('click',function( e ){
    if( e.target && e.target.id === 'cleantalk-modal-overlay' || e.target.id === 'cleantalk-modal-close' ){
        cleantalkModal.close();
    }
});
document.addEventListener("cleantalkModalContentLoaded", function( e ) {
    if( cleantalkModal.opened && cleantalkModal.loaded ) {
        document.getElementById( 'cleantalk-modal-content' ).innerHTML = cleantalkModal.loaded;
    }
});
let buttons_to_handle = [];

document.addEventListener('DOMContentLoaded', function(){

	if(
		typeof ctPublicGDPR === 'undefined' ||
		! ctPublicGDPR.gdpr_forms.length
	) {
		return;
	}

	let gdpr_notice_for_button = ctPublicGDPR.gdpr_title;

	if ( typeof jQuery === 'undefined' ) {
		return;
	}
	try {
		ctPublicGDPR.gdpr_forms.forEach(function(item, i){

			let elem = jQuery('#'+item+', .'+item);

			// Filter forms
			if (!elem.is('form')){
				// Caldera
				if (elem.find('form')[0])
					elem = elem.children('form').first();
				// Contact Form 7
				else if(
					jQuery('.wpcf7[role=form]')[0] && jQuery('.wpcf7[role=form]')
						.attr('id')
						.indexOf('wpcf7-f'+item) !== -1
				) {
					elem = jQuery('.wpcf7[role=form]').children('form');
				}

				// Formidable
				else if(jQuery('.frm_forms')[0] && jQuery('.frm_forms').first().attr('id').indexOf('frm_form_'+item) !== -1)
					elem = jQuery('.frm_forms').first().children('form');
				// WPForms
				else if(jQuery('.wpforms-form')[0] && jQuery('.wpforms-form').first().attr('id').indexOf('wpforms-form-'+item) !== -1)
					elem = jQuery('.wpforms-form');
			}

			//disable forms buttons
			let button = false
			let buttons_collection= elem.find('input[type|="submit"],button[type|="submit"]')

			if (!buttons_collection.length) {
				return
			} else {
				button = buttons_collection[0]
			}

			if (button !== false){
				button.disabled = true
				let old_notice = jQuery(button).prop('title') ? jQuery(button).prop('title') : ''
				buttons_to_handle.push({index:i,button:button,old_notice:old_notice})
				jQuery(button).prop('title', gdpr_notice_for_button)
			}

			// Adding notice and checkbox
			if(elem.is('form') || elem.attr('role') === 'form'){
				elem.append('<input id="apbct_gdpr_'+i+'" type="checkbox" required="required" style=" margin-right: 10px;" onchange="apbct_gdpr_handle_buttons()">')
					.append('<label style="display: inline;" for="apbct_gdpr_'+i+'">'+ctPublicGDPR.gdpr_text+'</label>');
			}
		});
	} catch (e) {
		console.info('APBCT GDPR JS ERROR: Can not add GDPR notice' + e)
	}
});

function apbct_gdpr_handle_buttons(){

	try {

		if (buttons_to_handle === []){
			return
		}

		buttons_to_handle.forEach((button) => {
			let selector = '[id="apbct_gdpr_' + button.index + '"]'
			let apbct_gdpr_item = jQuery(selector)
			//chek if apbct_gdpr checkbox is set
			if (jQuery(apbct_gdpr_item).prop("checked")){
				button.button.disabled = false
				jQuery(button.button).prop('title', button.old_notice)
			} else {
				button.button.disabled = true
				jQuery(button.button).prop('title', gdpr_notice_for_button)
			}
		})
	} catch (e) {
		console.info('APBCT GDPR JS ERROR: Can not handle form buttons ' + e)
	}
}
/**
 * Handle external forms
 */
function ct_protect_external() {
    for(var i = 0; i < document.forms.length; i++) {

        if (document.forms[i].cleantalk_hidden_action === undefined && document.forms[i].cleantalk_hidden_method === undefined) {

            // current form
            var currentForm = document.forms[i];

            if(typeof(currentForm.action) == 'string') {

                // Ajax checking for the integrated forms
                if(isIntegratedForm(currentForm)) {

                    apbctProcessExternalForm(currentForm, i, document);

                    // Common flow - modify form's action
                }else if(currentForm.action.indexOf('http://') !== -1 || currentForm.action.indexOf('https://') !== -1) {

                    var tmp = currentForm.action.split('//');
                    tmp = tmp[1].split('/');
                    var host = tmp[0].toLowerCase();

                    if(host !== location.hostname.toLowerCase()){

                        var ct_action = document.createElement("input");
                        ct_action.name = 'cleantalk_hidden_action';
                        ct_action.value = currentForm.action;
                        ct_action.type = 'hidden';
                        currentForm.appendChild(ct_action);

                        var ct_method = document.createElement("input");
                        ct_method.name = 'cleantalk_hidden_method';
                        ct_method.value = currentForm.method;
                        ct_method.type = 'hidden';

                        currentForm.method = 'POST'

                        currentForm.appendChild(ct_method);

                        currentForm.action = document.location;
                    }
                }
            }
        }

    }
    // Trying to process external form into an iframe
    apbctProcessIframes()
}

function formIsExclusion(currentForm)
{
    let exclusions_by_id = [
        'give-form' //give form exclusion because of direct integration
    ]

    let result = false

    //mewto forms exclusion
    if (currentForm.parentElement
        && currentForm.parentElement.classList.length > 0
        && currentForm.parentElement.classList[0].indexOf('mewtwo') !== -1) {
        result = true
    }

    exclusions_by_id.forEach(function (id) {
        if ( typeof (currentForm.id) !== 'undefined' && currentForm.id.indexOf(id) !== -1 ) {
            result = true
        }
    })

    return result
}

function apbctProcessIframes()
{
    const frames = document.getElementsByTagName('iframe');

    if ( frames.length > 0 ) {
        for ( let j = 0; j < frames.length; j++ ) {
            if ( frames[j].contentDocument == null ) {
                continue;
            }

            const iframeForms = frames[j].contentDocument.forms;

            if ( iframeForms.length === 0 ) {
                return;
            }

            for ( let y = 0; y < iframeForms.length; y++ ) {
                let currentForm = iframeForms[y];

                apbctProcessExternalForm(currentForm, y, frames[j].contentDocument);
            }
        }
    }
}

function apbctProcessExternalForm(currentForm, iterator, documentObject) {

    //process forms exclusions
    if ( formIsExclusion(currentForm)) {
        return
    }

    const cleantalk_placeholder = document.createElement("i");
    cleantalk_placeholder.className = 'cleantalk_placeholder';
    cleantalk_placeholder.style = 'display: none';

    currentForm.parentElement.insertBefore(cleantalk_placeholder, currentForm);

    // Deleting form to prevent submit event
    let prev = currentForm.previousSibling,
        form_html = currentForm.outerHTML,
        form_original = currentForm;

    // Remove the original form
    currentForm.parentElement.removeChild(currentForm);

    // Insert a clone
    const placeholder = document.createElement("div");
    placeholder.innerHTML = form_html;
    prev.after(placeholder.firstElementChild);

    var force_action = document.createElement("input");
    force_action.name = 'action';
    force_action.value = 'cleantalk_force_ajax_check';
    force_action.type = 'hidden';

    let reUseCurrentForm = documentObject.forms[iterator];

    reUseCurrentForm.appendChild(force_action);
    reUseCurrentForm.apbctPrev = prev;
    reUseCurrentForm.apbctFormOriginal = form_original;

    // mailerlite integration - disable click on submit button
    let mailerlite_detected_class = false
    if (reUseCurrentForm.classList !== undefined) {
        //list there all the mailerlite classes
        let mailerlite_classes = ['newsletterform', 'ml-block-form']
        mailerlite_classes.forEach(function(mailerlite_class) {
            if (reUseCurrentForm.classList.contains(mailerlite_class)){
                mailerlite_detected_class = mailerlite_class
            }
        });
    }
    if ( mailerlite_detected_class ) {
        let mailerliteSubmitButton = jQuery('form.' + mailerlite_detected_class).find('button[type="submit"]');
        if ( mailerliteSubmitButton !== undefined ) {
            mailerliteSubmitButton.click(function (event) {
                event.preventDefault();
                sendAjaxCheckingFormData(event.currentTarget);
            });
        }
    } else {
        documentObject.forms[iterator].onsubmit = function ( event ){
            event.preventDefault();

            //mautic integration
            if (documentObject.forms[iterator].id.indexOf('mauticform') !== -1) {
                let checkbox = jQuery(documentObject.forms[iterator]).find('input[id*="checkbox_rgpd"]')
                if (checkbox.length > 0){
                    if (checkbox.prop("checked") === true){
                        let placeholder = jQuery('.cleantalk_placeholder')
                        if (placeholder.length > 0) {
                            placeholder[0].setAttribute('mautic_hidden_gdpr_id', checkbox.prop("id"))
                        }
                    }
                }
            }

            const prev = jQuery(event.currentTarget).prev();
            const form_original = jQuery(event.currentTarget).clone();

            sendAjaxCheckingFormData(event.currentTarget);
        };
    }
}

function apbct_replace_inputs_values_from_other_form( form_source, form_target ){

    var	inputs_source = jQuery( form_source ).find( 'button, input, textarea, select' ),
        inputs_target = jQuery( form_target ).find( 'button, input, textarea, select' );

    inputs_source.each( function( index, elem_source ){

        var source = jQuery( elem_source );

        inputs_target.each( function( index2, elem_target ){

            var target = jQuery( elem_target );

            if( elem_source.outerHTML === elem_target.outerHTML ){

                target.val( source.val() );
            }
        });
    });

}
window.onload = function () {

    if( ! +ctPublic.settings__forms__check_external ) {
        return;
    }

    if ( typeof jQuery === 'undefined' ) {
        return;
    }

    setTimeout(function () {
        ct_protect_external()
    }, 1500);
};

/**
 * Checking the form integration
 */
function isIntegratedForm(formObj) {
    var formAction = formObj.action;
    let formId = formObj.id;

    if(
        formAction.indexOf('activehosted.com') !== -1 ||   // ActiveCampaign form
        formAction.indexOf('app.convertkit.com') !== -1 || // ConvertKit form
        ( formObj.firstChild.classList !== undefined && formObj.firstChild.classList.contains('cb-form-group') ) || // Convertbox form
        formAction.indexOf('mailerlite.com') !== -1 || // Mailerlite integration
        formAction.indexOf('colcolmail.co.uk') !== -1 || // colcolmail.co.uk integration
        formAction.indexOf('paypal.com') !== -1 ||
        formAction.indexOf('infusionsoft.com') !== -1 ||
        formAction.indexOf('webto.salesforce.com') !== -1 ||
        formAction.indexOf('secure2.convio.net') !== -1 ||
        formAction.indexOf('hookb.in') !== -1 ||
        formAction.indexOf('external.url') !== -1 ||
        formAction.indexOf('tp.media') !== -1 ||
        formAction.indexOf('flodesk.com') !== -1 ||
        formAction.indexOf('sendfox.com') !== -1 ||
        formAction.indexOf('aweber.com') !== -1 ||
        formAction.indexOf('secure.payu.com') !== -1 ||
        formAction.indexOf('mautic') !== -1 || formId.indexOf('mauticform_') !== -1
    ) {
        return true;
    }

    return false;
}

/**
 * Sending Ajax for checking form data
 */
function sendAjaxCheckingFormData(form, prev, formOriginal) {
    // Get visible fields and set cookie
    var visible_fields = {};
    visible_fields[0] = apbct_collect_visible_fields(form);
    apbct_visible_fields_set_cookie( visible_fields );

    var data = {};
    var elems = form.elements;
    elems = Array.prototype.slice.call(elems);

    elems.forEach( function( elem, y ) {
        if( elem.name === '' ) {
            data['input_' + y] = elem.value;
        } else {
            data[elem.name] = elem.value;
        }
    });

    apbct_public_sendAJAX(
        data,
        {
            async: false,
            callback: function( result, data, params, obj ){

                if( result.apbct === undefined || ! +result.apbct.blocked ) {

                    let form_new = jQuery(form).detach();
                    let prev = form.apbctPrev;
                    let formOriginal = form.apbctFormOriginal;
                    let mautic_integration = false;

                    apbct_replace_inputs_values_from_other_form(form_new, formOriginal);

                    //mautic forms integration
                    if (formOriginal.id.indexOf('mautic') !== -1) {
                        mautic_integration = true
                    }
                    let placeholders = document.getElementsByClassName('cleantalk_placeholder')
                    if (placeholders) {
                        for (let i = 0; i < placeholders.length; i++) {
                            let mautic_hidden_gdpr_id = placeholders[i].getAttribute("mautic_hidden_gdpr_id")
                            if (typeof(mautic_hidden_gdpr_id) !== 'undefined') {
                                let mautic_gdpr_radio = jQuery(formOriginal).find('#' + mautic_hidden_gdpr_id)
                                if (typeof(mautic_gdpr_radio) !== 'undefined') {
                                    mautic_gdpr_radio.prop("checked", true);
                                }
                            }
                        }
                    }

                    prev.after( formOriginal );

                    // Clear visible_fields input
                    jQuery(formOriginal).find('input[name="apbct_visible_fields"]').remove();
                    jQuery(formOriginal).find('input[value="cleantalk_force_ajax_check"]').remove();


                    //Common click event
                    var subm_button = jQuery(formOriginal).find('button[type=submit]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                        if (mautic_integration) {
                            setTimeout(function () {
                                ct_protect_external()
                            }, 1500);
                        }
                        return;
                    }

                    subm_button = jQuery(formOriginal).find('input[type=submit]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                        return;
                    }

                    // ConvertKit direct integration
                    subm_button = jQuery(formOriginal).find('button[data-element="submit"]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                        return;
                    }

                    // Paypal integration
                    subm_button = jQuery(formOriginal).find('input[type="image"][name="submit"]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                    }

                }
                if (result.apbct !== undefined && +result.apbct.blocked) {
                    ctParseBlockMessage(result);
                }
            }
        }
    );
}

function ct_check_internal(currForm){
    
//Gathering data
    var ct_data = {},
        elems = currForm.elements;

    for (var key in elems) {
        if(elems[key].type == 'submit' || elems[key].value == undefined || elems[key].value == '')
            continue;
        ct_data[elems[key].name] = currForm.elements[key].value;
    }
    ct_data['action'] = 'ct_check_internal';

    //AJAX Request
    apbct_public_sendAJAX(
        ct_data,
        {
            url: ctPublicFunctions._ajax_url,
            callback: function (data) {
                if(data.success === true){
                    currForm.submit();
                }else{
                    alert(data.data);
                    return false;
                }
            }
        }
    );
}

document.addEventListener('DOMContentLoaded',function(){
    let ct_currAction = '',
        ct_currForm = '';

    if( ! +ctPublic.settings__forms__check_internal ) {
        return;
    }

    let ctPrevHandler;
	for( let i=0; i<document.forms.length; i++ ){
		if ( typeof(document.forms[i].action) == 'string' ){
            ct_currForm = document.forms[i];
			ct_currAction = ct_currForm.action;
            if (
                ct_currAction.indexOf('https?://') !== null &&                        // The protocol is obligatory
                ct_currAction.match(ctPublic.blog_home + '.*?\.php') !== null && // Main check
                ! ct_check_internal__is_exclude_form(ct_currAction)                  // Exclude WordPress native scripts from processing
            ) {
                ctPrevHandler = ct_currForm.click;
                if ( typeof jQuery !== 'undefined' ) {
                    jQuery(ct_currForm).off('**');
                    jQuery(ct_currForm).off();
                    jQuery(ct_currForm).on('submit', function(event){
                        ct_check_internal(event.target);
                        return false;
                    });
                }
            }
		}
	}
});

/**
 * Check by action to exclude the form checking
 * @param action string
 * @return boolean
 */
function ct_check_internal__is_exclude_form(action) {
    // An array contains forms action need to be excluded.
    let ct_internal_script_exclusions = [
        'wp-login.php', // WordPress login page
        'wp-comments-post.php', // WordPress Comments Form
    ];

    return ct_internal_script_exclusions.some((item) => {
        return action.match(new RegExp(ctPublic.blog_home + '.*' + item)) !== null;
    });
}