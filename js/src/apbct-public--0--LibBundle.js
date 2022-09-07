class ApbctCore{

    ajax_parameters = {};
    rest_parameters = {};

    #selector = null;
    elements = [];

    // Event properties
    #eventCallback;
    #eventSelector;
    #event;

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
            this.#selector    = null;
            this.elements    = [];
            this.elements = Array.prototype.slice.call(selector);
        }else if( typeof selector === 'object' ){
            this.#selector    = null;
            this.elements    = [];
            this.elements[0] = selector;
        }else if( typeof selector === 'string' ){
            this.#selector = selector;
            this.elements = Array.prototype.slice.call(document.querySelectorAll(selector));
            // this.elements = document.querySelectorAll(selector)[0];
        }else{
            this.#deselect();
        }

        return this;
    }

    #addElement(elemToAdd){
        if( typeof elemToAdd === 'object' ){
            this.elements.push(elemToAdd);
        }else if( typeof elemToAdd === 'string' ){
            this.#selector = elemToAdd;
            this.elements = Array.prototype.slice.call(document.querySelectorAll(elemToAdd));
        }else{
            this.#deselect();
        }
    }

    #push(elem){
        this.elements.push(elem);
    }

    #reduce(){
        this.elements = this.elements.slice(0,-1);
    }

    #deselect(){
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

            console.log(computedStyle);

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

        this.#event         = args[0];
        this.#eventCallback = args[2] || args[1];
        this.#eventSelector = typeof args[1] === "string" ? args[1] : null;

        for(let i=0; i<this.elements.length; i++){
            this.elements[i].addEventListener(
                this.#event,
                this.#eventSelector !== null
                    ? this.#onChecker.bind(this)
                    : this.#eventCallback
            );
        }
    }

    /**
     * Check if a selector of an event matches current target
     *
     * @param event
     * @returns {*}
     */
    #onChecker(event){
        if(event.target === document.querySelector(this.#eventSelector)){
            event.stopPropagation();
            return this.#eventCallback(event);
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
            outputValue ||= this.#isElem(elem, filter);
        }

        return outputValue;
    }

    #isElem(elemToCheck, filter){

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
                is ||= this.#selector !== null
                    ? document.querySelector(this.#selector + filter) !== null // If possible
                    : this.#isWithoutSelector(elemToCheck, filter);                    // Search through all elems with such selector
            }
        }

        return is;
    }

    #isWithoutSelector(elemToCheck, filter){

        let elems       = document.querySelectorAll(filter);
        let outputValue = false;

        for(let elem of elems){
            outputValue ||= elemToCheck === elem;
        }

        return outputValue;
    }

    filter(filter){

        this.#selector = null;

        for( let i = this.elements.length - 1; i >= 0; i-- ){
            if( ! this.#isElem(this.elements[i], filter) ){
                this.elements.splice(Number(i), 1);
            }
        }

        return this;
    }

    /************** NODES **************/

    parent(filter){

        this.select(this.elements[0].parentElement);

        if( typeof filter !== 'undefined' && ! this.is(filter) ){
            this.#deselect();
        }

        return this;
    }

    parents(filter){

        this.select(this.elements[0]);

        for ( ; this.elements[ this.elements.length - 1].parentElement !== null ; ) {
            this.#push(this.elements[ this.elements.length - 1].parentElement);
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

    /**  ANIMATION  **/
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

    #xhr = new XMLHttpRequest();

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

    responseType = 'json'; // Expected data type from server
    headers      = {};
    timeout      = 15000; // Request timeout in milliseconds

    #methods_to_convert_data_to_URL = [
        'GET',
        'HEAD',
    ];

    #body        = null;
    #http_code   = 0;
    #status_text = '';

    constructor(parameters){

        console.log('%cXHR%c started', 'color: red; font-weight: bold;', 'color: grey; font-weight: normal;');

        // Set class properties
        for( let key in parameters ){
            if( typeof this[key] !== 'undefined' ){
                this[key] = parameters[key];
            }
        }

        // Modifying DOM-elements
        this.#prepare();

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
        this.#xhr.open(this.method, this.url, this.async, this.user, this.password);
        this.setHeaders();

        this.#xhr.responseType = this.responseType;
        this.#xhr.timeout      = this.timeout;

        /* EVENTS */
        // Monitoring status
        this.#xhr.onreadystatechange = function(){
            this.onReadyStateChange();
        }.bind(this);

        // Run callback
        this.#xhr.onload = function(){
            this.onLoad();
        }.bind(this);

        // On progress
        this.#xhr.onprogress = function(event){
            this.onProgress(event);
        }.bind(this);

        // On error
        this.#xhr.onerror = function(){
            this.onError();
        }.bind(this);

        this.#xhr.ontimeout = function(){
            this.onTimeout();
        }.bind(this);

        // Send the request
        this.#xhr.send(this.#body);
    }

    #prepare(){

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

    #complete(){

        this.#http_code   = this.#xhr.status;
        this.#status_text = this.#xhr.statusText;

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

        this.#complete();
        this.#error(
            this.#http_code,
            this.#status_text
        );

        if (this.on_error !== null && typeof this.on_error === 'function'){
            this.on_error();
        }
    }

    onTimeout(){
        this.#complete();
        this.#error(
            0,
            'timeout'
        );

        if (this.on_error !== null && typeof this.on_error === 'function'){
            this.on_error();
        }
    }

    onLoad(){

        this.#complete();

        if (this.responseType === 'json' ){
            if(this.#xhr.response === null){
                this.#error(this.#http_code, this.#status_text, 'No response');
                return false;
            }else if( typeof this.#xhr.response.error !== 'undefined') {
                this.#error(this.#http_code, this.#status_text, this.#xhr.response.error);
                return false;
            }
        }

        if (this.callback !== null && typeof this.callback === 'function') {
            this.callback.call(this.context, this.#xhr.response, this.data);
        }
    }

    #error(http_code, status_text, additional_msg){

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
                this.#xhr.setRequestHeader(header_name, this.headers[header_name]);
            }
        }
    }

    convertData()
    {
        // GET, HEAD request-type
        if( ~this.#methods_to_convert_data_to_URL.indexOf( this.method ) ){
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
        this.#body = new FormData();

        for (let dataKey in this.data) {
            this.#body.append(
                dataKey,
                typeof this.data[dataKey] === 'object'
                    ? JSON.stringify(this.data[dataKey])
                    : this.data[dataKey]
            );
        }

        return this.#body;
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
