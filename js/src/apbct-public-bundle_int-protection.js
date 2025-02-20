/**
 * @return {HTMLElement} event
 */
function apbctSetEmailDecoderPopupAnimation() {
    const animationElements = ['apbct_dog_one', 'apbct_dog_two', 'apbct_dog_three'];
    const animationWrapper = document.createElement('div');
    animationWrapper.classList = 'apbct-ee-animation-wrapper';
    for (let i = 0; i < animationElements.length; i++) {
        const apbctEEAnimationDogOne = document.createElement('span');
        apbctEEAnimationDogOne.classList = 'apbct_dog ' + animationElements[i];
        apbctEEAnimationDogOne.innerText = '@';
        animationWrapper.append(apbctEEAnimationDogOne);
    }
    return animationWrapper;
}

/**
 * @param {mixed} event
 */
function ctFillDecodedEmailHandler(event = false) {
    let clickSource = false;
    let ctWlBrandname = '';
    let encodedEmail = '';
    if (typeof ctPublic !== 'undefined') {
        this.removeEventListener('click', ctFillDecodedEmailHandler);
        // remember clickSource
        clickSource = this;
        // globally remember if emails is mixed with mailto
        ctPublic.encodedEmailNodesIsMixed = false;
        ctWlBrandname = ctPublic.wl_brandname;
        encodedEmail = ctPublic.encodedEmailNodes;
    } else if (typeof ctAdminCommon !== 'undefined') {
        ctWlBrandname = ctAdminCommon.plugin_name;
        encodedEmail = ctAdminCommon.encodedEmailNode;
    }

    // get fade
    document.body.classList.add('apbct-popup-fade');
    // popup show
    let encoderPopup = document.getElementById('apbct_popup');
    if (!encoderPopup) {
        // construct popup
        let waitingPopup = document.createElement('div');
        waitingPopup.setAttribute('class', 'apbct-popup apbct-email-encoder-popup');
        waitingPopup.setAttribute('id', 'apbct_popup');

        // construct text header
        let popupHeaderWrapper = document.createElement('span');
        popupHeaderWrapper.classList = 'apbct-email-encoder-elements_center';
        let popupHeader = document.createElement('p');
        popupHeader.innerText = ctWlBrandname;
        popupHeader.setAttribute('class', 'apbct-email-encoder--popup-header');
        popupHeaderWrapper.append(popupHeader);

        // construct text wrapper
        let popupTextWrapper = document.createElement('div');
        popupTextWrapper.setAttribute('id', 'apbct_popup_text');
        popupTextWrapper.setAttribute('class', 'apbct-email-encoder-elements_center');
        popupTextWrapper.style.color = 'black';

        // construct text first node
        // todo make translatable
        let popupTextWaiting = document.createElement('p');
        popupTextWaiting.id = 'apbct_email_ecoder__popup_text_node_first';
        if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_wait_for_decoding) {
            popupTextWaiting.innerText = ctPublicFunctions.text__ee_wait_for_decoding;
        } else {
            popupTextWaiting.innerText = 'The magic is on the way, please wait for a few seconds!';
        }
        popupTextWaiting.setAttribute('class', 'apbct-email-encoder-elements_center');

        // construct text second node
        // todo make translatable
        let popupTextDecoding = document.createElement('p');
        popupTextDecoding.id = 'apbct_email_ecoder__popup_text_node_second';
        if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_decoding_process) {
            popupTextDecoding.innerText = ctPublicFunctions.text__ee_decoding_process;
        } else {
            popupTextDecoding.innerText = 'Decoding process to the original data.';
        }

        // appending
        popupTextWrapper.append(popupTextWaiting);
        popupTextWrapper.append(popupTextDecoding);
        waitingPopup.append(popupHeaderWrapper);
        waitingPopup.append(popupTextWrapper);
        waitingPopup.append(apbctSetEmailDecoderPopupAnimation());
        document.body.append(waitingPopup);
    } else {
        encoderPopup.setAttribute('style', 'display: inherit');
        if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_wait_for_decoding_2) {
            document.getElementById('apbct_popup_text').innerHTML = ctPublicFunctions.text__ee_wait_for_decoding_2;
        } else {
            document.getElementById('apbct_popup_text').innerHTML =
            'Please wait while ' + ctPublic.wl_brandname + ' is decoding the email addresses.';
        }
    }

    apbctAjaxEmailDecodeBulk(event, encodedEmail, clickSource);
}

/**
 * @param {mixed} event
 * @param {mixed} encodedEmailNodes
 * @param {mixed} clickSource
 */
function apbctAjaxEmailDecodeBulk(event, encodedEmailNodes, clickSource) {
    if (event && clickSource) {
        // collect data
        const javascriptClientData = getJavascriptClientData();
        let data = {
            event_javascript_data: javascriptClientData,
            post_url: document.location.href,
            referrer: document.referrer,
            encodedEmails: '',
        };
        let encodedEmailsCollection = {};
        for (let i = 0; i < encodedEmailNodes.length; i++) {
            // disable click for mailto
            if (
                typeof encodedEmailNodes[i].href !== 'undefined' &&
                encodedEmailNodes[i].href.indexOf('mailto:') === 0
            ) {
                event.preventDefault();
                ctPublic.encodedEmailNodesIsMixed = true;
            }

            // Adding a tooltip
            let apbctTooltip = document.createElement('div');
            apbctTooltip.setAttribute('class', 'apbct-tooltip');
            apbct(encodedEmailNodes[i]).append(apbctTooltip);

            // collect encoded email strings
            encodedEmailsCollection[i] = encodedEmailNodes[i].dataset.originalString;
        }

        // JSONify encoded email strings
        data.encodedEmails = JSON.stringify(encodedEmailsCollection);

        // Using REST API handler
        if ( ctPublicFunctions.data__ajax_type === 'rest' ) {
            apbct_public_sendREST(
                'apbct_decode_email',
                {
                    data: data,
                    method: 'POST',
                    callback: function(result) {
                        // set alternative cookie to skip next pages encoding
                        ctSetCookie('apbct_email_encoder_passed', ctPublic.emailEncoderPassKey, '');
                        apbctEmailEncoderCallbackBulk(result, encodedEmailNodes, clickSource);
                    },
                    onErrorCallback: function(res) {
                        resetEncodedNodes();
                        ctShowDecodeComment(res);
                    },
                },
            );

            // Using AJAX request and handler
        } else {
            data.action = 'apbct_decode_email';
            apbct_public_sendAJAX(
                data,
                {
                    notJson: false,
                    callback: function(result) {
                        console.log('result');
                        console.log(result);

                        // set alternative cookie to skip next pages encoding
                        ctSetCookie('apbct_email_encoder_passed', ctPublic.emailEncoderPassKey, '');
                        apbctEmailEncoderCallbackBulk(result, encodedEmailNodes, clickSource);
                    },
                    onErrorCallback: function(res) {
                        resetEncodedNodes();
                        ctShowDecodeComment(res);
                    },
                },
            );
        }
    } else {
        const encodedEmail = encodedEmailNodes.dataset.originalString;
        let data = {
            encodedEmails: JSON.stringify({0: encodedEmail}),
        };

        // Adding a tooltip
        let apbctTooltip = document.createElement('div');
        apbctTooltip.setAttribute('class', 'apbct-tooltip');
        jQuery(encodedEmailNodes).append(apbctTooltip);

        apbct_admin_sendAJAX(
            {
                'action': 'apbct_decode_email',
                'encodedEmails': data.encodedEmails,
            },
            {
                'callback': function(result) {
                    apbctEmailEncoderCallbackBulk(result, encodedEmailNodes, false);
                },
                'notJson': true,
            },
        );
    }
}

/**
 * @param {mixed} result
 * @param {mixed} encodedEmailNodes
 * @param {mixed} clickSource
 */
function apbctEmailEncoderCallbackBulk(result, encodedEmailNodes, clickSource = false) {
    if (result.success && result.data[0].is_allowed === true) {
        // start process of visual decoding
        setTimeout(function() {
            // popup remove
            let popup = document.getElementById('apbct_popup');
            if (popup !== null) {
                let email = '';
                if (clickSource) {
                    let currentResultData;
                    result.data.forEach((row) => {
                        if (row.encoded_email === clickSource.dataset.originalString) {
                            currentResultData = row;
                        }
                    });

                    email = currentResultData.decoded_email.split(/[&?]/)[0];
                } else {
                    email = result.data[0].decoded_email;
                }
                // handle first node
                let firstNode = popup.querySelector('#apbct_email_ecoder__popup_text_node_first');
                // get email selectable by click
                let selectableEmail = document.createElement('b');
                selectableEmail.setAttribute('class', 'apbct-email-encoder-select-whole-email');
                selectableEmail.innerText = email;
                if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_click_to_select) {
                    selectableEmail.title = ctPublicFunctions.text__ee_click_to_select;
                } else {
                    selectableEmail.title = 'Click to select the whole data';
                }
                // add email to the first node
                if (firstNode) {
                    if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_original_email) {
                        firstNode.innerHTML =
                        ctPublicFunctions.text__ee_original_email +
                        '&nbsp;' +
                        selectableEmail.outerHTML;
                    } else {
                        firstNode.innerHTML = 'The original one is&nbsp;' + selectableEmail.outerHTML;
                    }

                    firstNode.setAttribute('style', 'flex-direction: row;');
                }
                // remove animation
                let wrapper = popup.querySelector('.apbct-ee-animation-wrapper');
                if (wrapper) {
                    wrapper.remove();
                }
                // remove second node
                let secondNode = popup.querySelector('#apbct_email_ecoder__popup_text_node_second');
                if (secondNode) {
                    secondNode.remove();
                }
                // add button
                let buttonWrapper = document.createElement('span');
                buttonWrapper.classList = 'apbct-email-encoder-elements_center top-margin-long';
                if (!document.querySelector('.apbct-email-encoder-got-it-button')) {
                    let button = document.createElement('button');
                    if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_got_it) {
                        button.innerText = ctPublicFunctions.text__ee_got_it;
                    } else {
                        button.innerText = 'Got it';
                    }
                    button.classList = 'apbct-email-encoder-got-it-button';
                    button.addEventListener('click', function() {
                        document.body.classList.remove('apbct-popup-fade');
                        popup.setAttribute('style', 'display:none');
                        fillDecodedEmails(encodedEmailNodes, result);
                        // click on mailto if so
                        if (typeof ctPublic !== 'undefined' && ctPublic.encodedEmailNodesIsMixed && clickSource) {
                            clickSource.click();
                        }
                    });
                    buttonWrapper.append(button);
                    popup.append(buttonWrapper);
                }
            }
        }, 3000);
    } else {
        if (clickSource) {
            if (result.success) {
                resetEncodedNodes();
                if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_blocked) {
                    ctShowDecodeComment(ctPublicFunctions.text__ee_blocked + ': ' + result.data[0].comment);
                } else {
                    ctShowDecodeComment('Blocked: ' + result.data[0].comment);
                }
            } else {
                resetEncodedNodes();
                if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_cannot_connect) {
                    ctShowDecodeComment(ctPublicFunctions.text__ee_cannot_connect + ': ' + result.apbct.comment);
                } else {
                    ctShowDecodeComment('Cannot connect with CleanTalk server: ' + result.data[0].comment);
                }
            }
        } else {
            console.log('result', result);
        }
    }
}

/**
 * Reset click event for encoded email
 */
function resetEncodedNodes() {
    if (typeof ctPublic.encodedEmailNodes !== 'undefined') {
        ctPublic.encodedEmailNodes.forEach(function(element) {
            element.addEventListener('click', ctFillDecodedEmailHandler);
        });
    }
}

/**
 * Show Decode Comment
 * @param {string} comment
 */
function ctShowDecodeComment(comment) {
    if ( ! comment ) {
        if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_cannot_decode) {
            comment = ctPublicFunctions.text__ee_cannot_decode;
        } else {
            comment = 'Can not decode email. Unknown reason';
        }
    }

    let popup = document.getElementById('apbct_popup');
    let popupText = document.getElementById('apbct_popup_text');
    if (popup !== null) {
        document.body.classList.remove('apbct-popup-fade');
        if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_email_decoder) {
            popupText.innerText = ctPublicFunctions.text__ee_email_decoder + ': ' + comment;
        } else {
            popupText.innerText = 'CleanTalk email decoder: ' + comment;
        }
        setTimeout(function() {
            popup.setAttribute('style', 'display:none');
        }, 3000);
    }
}

/**
 * Run filling for every node with decoding result.
 * @param {mixed} encodedEmailNodes
 * @param {mixed} decodingResult
 */
function fillDecodedEmails(encodedEmailNodes, decodingResult) {
    if (encodedEmailNodes.length > 0) {
        for (let i = 0; i < encodedEmailNodes.length; i++) {
            // chek what is what
            let currentResultData;
            decodingResult.data.forEach((row) => {
                if (row.encoded_email === encodedEmailNodes[i].dataset.originalString) {
                    currentResultData = row;
                }
            });
            // quit case on cloud block
            if (currentResultData.is_allowed === false) {
                return;
            }
            // handler for mailto
            if (
                typeof encodedEmailNodes[i].href !== 'undefined' &&
                encodedEmailNodes[i].href.indexOf('mailto:') === 0
            ) {
                let encodedEmail = encodedEmailNodes[i].href.replace('mailto:', '');
                let baseElementContent = encodedEmailNodes[i].innerHTML;
                encodedEmailNodes[i].innerHTML = baseElementContent.replace(
                    encodedEmail,
                    currentResultData.decoded_email,
                );
                encodedEmailNodes[i].href = 'mailto:' + currentResultData.decoded_email;

                encodedEmailNodes[i].querySelectorAll('span.apbct-email-encoder').forEach((el) => {
                    let encodedEmailTextInsideMailto = '';
                    decodingResult.data.forEach((row) => {
                        if (row.encoded_email === el.dataset.originalString) {
                            encodedEmailTextInsideMailto = row.decoded_email;
                        }
                    });
                    el.innerHTML = encodedEmailTextInsideMailto;
                });
            } else {
                encodedEmailNodes[i].classList.add('no-blur');
                // fill the nodes
                setTimeout(() => {
                    ctProcessDecodedDataResult(currentResultData, encodedEmailNodes[i]);
                }, 2000);
            }
            // remove listeners
            encodedEmailNodes[i].removeEventListener('click', ctFillDecodedEmailHandler);
        }
    } else {
        let currentResultData = decodingResult.data[0];
        encodedEmailNodes.classList.add('no-blur');
        // fill the nodes
        setTimeout(() => {
            ctProcessDecodedDataResult(currentResultData, encodedEmailNodes);
        }, 2000);
        encodedEmailNodes.removeEventListener('click', ctFillDecodedEmailHandler);
    }
}

/**
 * @param {mixed} response
 * @param {mixed} targetElement
 */
function ctProcessDecodedDataResult(response, targetElement) {
    targetElement.setAttribute('title', '');
    targetElement.removeAttribute('style');
    ctFillDecodedEmail(targetElement, response.decoded_email);
}

/**
 * @param {mixed} target
 * @param {string} email
 */
function ctFillDecodedEmail(target, email) {
    jQuery(target).html(
        jQuery(target)
            .html()
            .replace(/.+?(<div class=["']apbct-tooltip["'].+?<\/div>)/, email + '$1'),
    );
}

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

        if (this.onErrorCallback !== null && typeof this.onErrorCallback === 'function') {
            this.onErrorCallback(this.status_text);
        }
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

// add hasOwn
if (!Object.prototype.hasOwn) {
    Object.defineProperty(Object.prototype, 'hasOwn', { // eslint-disable-line
        value: function(property) {
            return Object.prototype.hasOwnProperty.call(this, property);
        },
        enumerable: false,
        configurable: true,
        writable: true,
    });
}

/**
 * Form skin class
 *
 */
// eslint-disable-next-line no-unused-vars, require-jsdoc
class ApbctFormDecorator {
    elements = [];

    /**
     * Constructor
     */
    constructor() {
        this.getElements();
        this.setListeners();
    }

    /**
     * Get elements
     */
    getElements() {
        const elements = document.querySelectorAll('*');
        const regexId = /^apbct-trusted-text--label/;
        const regexClass = /apbct_form_decoration--/;

        this.setDecorationBackground();

        // Collect elements with id or class that contains apbct-trusted-text--label or apbct_form_decoration--
        // id
        let matchingElements = Array.from(elements).filter((element) => {
            return regexId.test(element.id);
        });
        matchingElements.forEach((element) => {
            this.elements.push(element);
        });

        // class
        matchingElements = Array.from(elements).filter((element) => {
            return regexClass.test(element.className);
        });

        matchingElements.forEach((element) => {
            this.elements.push(element);
        });

        const flagWrap = document.querySelector('.apbct_form_decoration');
        if (flagWrap) {
            const flagLeft = window.getComputedStyle(flagWrap, '::before');
            const flagRight = window.getComputedStyle(flagWrap, '::after');
            if (flagLeft && flagRight) {
                this.elements.push(flagWrap);
            }
        }
    }

    /**
     * Set decoration background
     */
    setDecorationBackground() {
        let blockForms = document.querySelectorAll('#respond');

        if (document.querySelector('[class*="apbct_form_decoration"]')) {
            let classHeaderWrapper = document.querySelector('[class*="apbct_form_decoration"]').getAttribute('class');
            let endPosition = classHeaderWrapper.indexOf('_header__wrapper');
            let classTemplate = classHeaderWrapper.substring(0, endPosition);

            blockForms.forEach((blockForm) => {
                blockForm.className += ' ' + classTemplate;
            });
        }
    }

    /**
     * Set listeners
     */
    setListeners() {
        this.elements.forEach((element) => {
            if (!element) {
                return;
            }

            element.addEventListener('click', (event) => {
                if (element.className.indexOf('apbct_form_decoration') !== -1) {
                    if (element.className.indexOf('header__wrapper') !== -1) {
                        this.addClicks();
                        return;
                    }

                    const clickX = event.offsetX;
                    const clickY = event.offsetY;
                    const flagLeftWidth = parseFloat(window.getComputedStyle(element, '::before').width) / 2;
                    const flagLeftHeight = parseFloat(window.getComputedStyle(element, '::before').height) / 2;
                    const flagRightWidth = parseFloat(window.getComputedStyle(element, '::after').width) / 2;
                    const flagRightHeight = parseFloat(window.getComputedStyle(element, '::after').height) / 2;

                    if (element.className.indexOf('christmas') !== -1) {
                        if (
                            clickY < flagLeftHeight / 3 && clickX < flagLeftWidth ||
                            clickY < flagRightHeight / 3 && clickX > flagRightWidth
                        ) {
                            this.addClicks();
                            return;
                        }
                    }

                    if (
                        (element.className.indexOf('new-year') !== -1) ||
                        (element.className.indexOf('fourth-july') !== -1)
                    ) {
                        if (
                            clickY > flagLeftHeight && clickX < flagLeftWidth ||
                            clickY > flagRightHeight && clickX > flagRightWidth
                        ) {
                            this.addClicks();
                        }
                    }

                    if (element.className.indexOf('signature')) {
                        this.addClicks();
                    }

                    return;
                }

                this.addClicks();
            });

            element.addEventListener('mouseup', (event) => {
                setTimeout(() => {
                    const selectedText = window.getSelection().toString();
                    if (selectedText) {
                        this.addSelected();
                    }
                }, 100);
            });

            element.addEventListener('mousemove', (event) => {
                if (element.className.indexOf('apbct_form_decoration') !== -1) {
                    const mouseX = event.offsetX;
                    const mouseY = event.offsetY;
                    const flagLeftWidth = parseFloat(window.getComputedStyle(element, '::before').width) / 2;
                    const flagLeftHeight = parseFloat(window.getComputedStyle(element, '::before').height) / 2;
                    const flagRightWidth = parseFloat(window.getComputedStyle(element, '::after').width) / 2;
                    const flagRightHeight = parseFloat(window.getComputedStyle(element, '::after').height) / 2;

                    if (mouseY > flagLeftHeight && mouseX < flagLeftWidth ||
                    mouseY > flagRightHeight && mouseX > flagRightWidth
                    ) {
                        this.trackMouseMovement();
                    }
                    return;
                }

                this.trackMouseMovement();
            });
        });
    }

    /**
     * Add clicks
     */
    addClicks() {
        if (document.ctFormDecorationMouseData) {
            if (document.ctFormDecorationMouseData.clicks) {
                document.ctFormDecorationMouseData.clicks++;
            } else {
                document.ctFormDecorationMouseData.clicks = 1;
            }
            return;
        }

        document.ctFormDecorationMouseData = {clicks: 1};
    }

    /**
     * Add selected
     */
    addSelected() {
        if (document.ctFormDecorationMouseData) {
            if (document.ctFormDecorationMouseData.selected) {
                document.ctFormDecorationMouseData.selected++;
            } else {
                document.ctFormDecorationMouseData.selected = 1;
            }
            return;
        }

        document.ctFormDecorationMouseData = {selected: 1};
    }

    /**
     * Track mouse movement
     */
    trackMouseMovement() {
        if (!document.ctFormDecorationMouseData) {
            document.ctFormDecorationMouseData = {};
        }
        if (!document.ctFormDecorationMouseData.mouseMovements) {
            document.ctFormDecorationMouseData.mouseMovements = [];
        }

        document.ctFormDecorationMouseData.mouseMovements.push({timestamp: Date.now()});

        if (document.ctFormDecorationMouseData.mouseMovements.length > 1) {
            const index = document.ctFormDecorationMouseData.mouseMovements.length - 1;
            const lastMovement = document.ctFormDecorationMouseData.mouseMovements[index];
            const firstMovement = document.ctFormDecorationMouseData.mouseMovements[0];
            const timeDiff = lastMovement.timestamp - firstMovement.timestamp;
            document.ctFormDecorationMouseData.hovering = timeDiff;
        }
    }
}

/**
 * @param {object|array|string} cookies
 * @param {object|array|string} value
 * @param {string|number} expires
 */
// eslint-disable-next-line no-unused-vars,require-jsdoc
function ctSetCookie( cookies, value, expires ) {
    let listOfCookieNamesToForceAlt = [
        'ct_sfw_pass_key',
        'ct_sfw_passed',
        'wordpress_apbct_antibot',
        'apbct_anticrawler_passed',
        'apbct_bot_detector_exist',
        'apbct_antiflood_passed',
        'apbct_email_encoder_passed',
    ];

    let skipAlt = false;

    if ( typeof cookies === 'string') {
        skipAlt = cookies === 'ct_pointer_data';
        if ( typeof value === 'string' || typeof value === 'number' ) {
            cookies = [[cookies, value, expires]];
        }
    }

    // Cookies disabled
    if ( ctPublicFunctions.data__cookies_type === 'none' ) {
        let forcedAltCookiesSet = [];
        cookies.forEach( function(item) {
            if (listOfCookieNamesToForceAlt.indexOf(item[0]) !== -1) {
                forcedAltCookiesSet.push(item);
            } else {
                apbctLocalStorage.set(item[0], item[1]);
            }
        });

        // if cookies from list found use alt cookies for this selection set
        if ( forcedAltCookiesSet.length > 0 ) {
            ctSetAlternativeCookie(forcedAltCookiesSet);
        }

        // If problem integration forms detected use alt cookies for whole cookies set
        if ( ctPublic.force_alt_cookies && !skipAlt) {
            // do it just once
            ctSetAlternativeCookie(cookies, {forceAltCookies: true});
        } else {
            ctNoCookieAttachHiddenFieldsToForms();
        }

        // Using traditional cookies
    } else if ( ctPublicFunctions.data__cookies_type === 'native' ) {
        // If problem integration forms detected use alt cookies for whole cookies set
        if ( ctPublic.force_alt_cookies && !skipAlt) {
            // do it just once
            ctSetAlternativeCookie(cookies, {forceAltCookies: true});
        }
        cookies.forEach( function(item) {
            const _expires = typeof item[2] !== 'undefined' ? 'expires=' + expires + '; ' : '';
            let ctSecure = location.protocol === 'https:' ? '; secure' : '';
            document.cookie = ctPublicFunctions.cookiePrefix +
                item[0] +
                '=' +
                encodeURIComponent(item[1]) +
                '; ' +
                _expires +
                'path=/; samesite=lax' +
                ctSecure;
        });

        // Using alternative cookies
    } else if ( ctPublicFunctions.data__cookies_type === 'alternative' && !skipAlt ) {
        ctSetAlternativeCookie(cookies);
    }
}

// eslint-disable-next-line no-unused-vars,require-jsdoc
function ctDetectForcedAltCookiesForms() {
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
        otterForm;

    setTimeout(function() {
        if (!ctPublic.force_alt_cookies) {
            let bookingPress = document.querySelectorAll('main[id^="bookingpress_booking_form"]').length > 0;
            ctPublic.force_alt_cookies = bookingPress;
        }
    }, 1000);
}

// eslint-disable-next-line require-jsdoc
function ctSetAlternativeCookie(cookies, params) {
    if (typeof (getJavascriptClientData) === 'function' ) {
        // reprocess already gained cookies data
        if (Array.isArray(cookies)) {
            cookies = getJavascriptClientData(cookies);
        }
    } else {
        console.log('APBCT ERROR: getJavascriptClientData() is not loaded');
    }

    try {
        cookies = JSON.parse(cookies);
    } catch (e) {
        console.log('APBCT ERROR: JSON parse error:' + e);
        return;
    }

    if (!cookies.apbct_site_referer) {
        cookies.apbct_site_referer = location.href;
    }

    const callback = params && params.callback || null;
    const onErrorCallback = params && params.onErrorCallback || null;

    if ( params && params.forceAltCookies ) {
        cookies.apbct_force_alt_cookies = true;
    }

    // Using REST API handler
    if ( ctPublicFunctions.data__ajax_type === 'rest' ) {
        // fix for url encoded cookie apbct_pixel_url on REST route
        if (typeof cookies.apbct_pixel_url === 'string' &&
            cookies.apbct_pixel_url.indexOf('%3A') !== -1
        ) {
            cookies.apbct_pixel_url = decodeURIComponent(cookies.apbct_pixel_url);
        }
        apbct_public_sendREST(
            'alt_sessions',
            {
                method: 'POST',
                data: {cookies: cookies},
                callback: callback,
                onErrorCallback: onErrorCallback,
            },
        );

        // Using AJAX request and handler
    } else if ( ctPublicFunctions.data__ajax_type === 'admin_ajax' ) {
        apbct_public_sendAJAX(
            {
                action: 'apbct_alt_session__save__AJAX',
                cookies: cookies,
            },
            {
                notJson: 1,
                callback: callback,
                onErrorCallback: onErrorCallback,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-Robots-Tag', 'noindex, nofollow');
                },
            },
        );
    }
}

/**
 * Get cookie by name
 * @param name
 * @return {string|undefined}
 */
// eslint-disable-next-line require-jsdoc,no-unused-vars
function ctGetCookie(name) {
    let matches = document.cookie.match(new RegExp(
        '(?:^|; )' + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)',
    ));
    return matches ? decodeURIComponent(matches[1]) : undefined;
}

// eslint-disable-next-line require-jsdoc,no-unused-vars
function ctDeleteCookie(cookieName) {
    // Cookies disabled
    if ( ctPublicFunctions.data__cookies_type === 'none' ) {
        return;

    // Using traditional cookies
    } else if ( ctPublicFunctions.data__cookies_type === 'native' ) {
        let ctSecure = location.protocol === 'https:' ? '; secure' : '';
        document.cookie = cookieName + '=""; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; samesite=lax' + ctSecure;

    // Using alternative cookies
    } else if ( ctPublicFunctions.data__cookies_type === 'alternative' ) {
        // @ToDo implement this logic
    }
}

// eslint-disable-next-line require-jsdoc,camelcase
function apbct_public_sendAJAX(data, params, obj) {
    // Default params
    let _params = [];
    _params['callback'] = params.callback || null;
    _params['onErrorCallback'] = params.onErrorCallback || null;
    _params['callback_context'] = params.callback_context || null;
    _params['callback_params'] = params.callback_params || null;
    _params['async'] = params.async || true;
    _params['notJson'] = params.notJson || null;
    _params['responseType']= params.notJson ? 'text' : 'json';
    _params['timeout'] = params.timeout || 15000;
    _params['obj'] = obj || null;
    _params['button'] = params.button || null;
    _params['spinner'] = params.spinner || null;
    _params['progressbar'] = params.progressbar || null;
    _params['silent'] = params.silent || null;
    _params['no_nonce'] = params.no_nonce || null;
    _params['data'] = data;
    _params['url'] = ctPublicFunctions._ajax_url;
    const nonce = selectActualNonce();

    if (typeof (data) === 'string') {
        if ( ! _params['no_nonce'] ) {
            _params['data'] = _params['data'] + '&_ajax_nonce=' + nonce;
        }
        _params['data'] = _params['data'] + '&no_cache=' + Math.random();
    } else {
        if ( ! _params['no_nonce'] ) {
            _params['data']._ajax_nonce = nonce;
        }
        _params['data'].no_cache = Math.random();
    }

    new ApbctCore().ajax(_params);
}

// eslint-disable-next-line require-jsdoc,camelcase
function apbct_public_sendREST( route, params ) {
    let _params = [];
    _params['route'] = route;
    _params['callback'] = params.callback || null;
    _params['onErrorCallback'] = params.onErrorCallback || null;
    _params['data'] = params.data || [];
    _params['method'] = params.method || 'POST';

    new ApbctCore().rest(_params);
}

/**
 * Generate unique ID
 * @return {string}
 */
// eslint-disable-next-line no-unused-vars,require-jsdoc
function apbctGenerateUniqueID() {
    return Math.random().toString(36).replace(/[^a-z]+/g, '').substr(2, 10);
}

let apbctLocalStorage = {
    get: function(key, property) {
        if ( typeof property === 'undefined' ) {
            property = 'value';
        }
        const storageValue = localStorage.getItem(key);
        if ( storageValue !== null ) {
            try {
                const json = JSON.parse(storageValue);
                if ( json.hasOwnProperty(property) ) {
                    try {
                        // if property can be parsed as JSON - do it
                        return JSON.parse( json[property] );
                    } catch (e) {
                        // if not - return string of value
                        return json[property].toString();
                    }
                } else {
                    return json;
                }
            } catch (e) {
                return storageValue;
            }
        }
        return false;
    },
    set: function(key, value, isJson = true) {
        if (isJson) {
            let objToSave = {'value': JSON.stringify(value), 'timestamp': Math.floor(new Date().getTime() / 1000)};
            localStorage.setItem(key, JSON.stringify(objToSave));
        } else {
            localStorage.setItem(key, value);
        }
    },
    isAlive: function(key, maxLifetime) {
        if ( typeof maxLifetime === 'undefined' ) {
            maxLifetime = 86400;
        }
        const keyTimestamp = this.get(key, 'timestamp');
        return keyTimestamp + maxLifetime > Math.floor(new Date().getTime() / 1000);
    },
    isSet: function(key) {
        return localStorage.getItem(key) !== null;
    },
    delete: function(key) {
        localStorage.removeItem(key);
    },
    getCleanTalkData: function() {
        let data = {};
        for (let i=0; i<localStorage.length; i++) {
            let key = localStorage.key(i);
            if (key.indexOf('ct_') !==-1 || key.indexOf('apbct_') !==-1) {
                data[key.toString()] = apbctLocalStorage.get(key);
            }
        }
        return data;
    },

};

let apbctSessionStorage = {
    get: function(key, property) {
        if ( typeof property === 'undefined' ) {
            property = 'value';
        }
        const storageValue = sessionStorage.getItem(key);
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
    set: function(key, value, isJson = true) {
        if (isJson) {
            let objToSave = {'value': JSON.stringify(value), 'timestamp': Math.floor(new Date().getTime() / 1000)};
            sessionStorage.setItem(key, JSON.stringify(objToSave));
        } else {
            sessionStorage.setItem(key, value);
        }
    },
    isSet: function(key) {
        return sessionStorage.getItem(key) !== null;
    },
    delete: function(key) {
        sessionStorage.removeItem(key);
    },
    getCleanTalkData: function() {
        let data = {};
        for (let i=0; i<sessionStorage.length; i++) {
            let key = sessionStorage.key(i);
            if (key.indexOf('ct_') !==-1 || key.indexOf('apbct_') !==-1) {
                data[key.toString()] = apbctSessionStorage.get(key);
            }
        }
        return data;
    },
};

/**
 * Handler for -webkit based browser that listen for a custom
 * animation create using the :pseudo-selector in the stylesheet.
 * Works with Chrome, Safari
 *
 * @param {AnimationEvent} event
 */
// eslint-disable-next-line no-unused-vars,require-jsdoc
function apbctOnAnimationStart(event) {
    ('onautofillstart' === event.animationName) ?
        apbctAutocomplete(event.target) : apbctCancelAutocomplete(event.target);
}

/**
 * Handler for non-webkit based browser that listen for input
 * event to trigger the autocomplete-cancel process.
 * Works with Firefox, Edge, IE11
 *
 * @param {InputEvent} event
 */
// eslint-disable-next-line no-unused-vars,require-jsdoc
function apbctOnInput(event) {
    ('insertReplacementText' === event.inputType || !('data' in event)) ?
        apbctAutocomplete(event.target) : apbctCancelAutocomplete(event.target);
}

/**
 * Manage an input element when its value is autocompleted
 * by the browser in the following steps:
 * - add [autocompleted] attribute from event.target
 * - create 'onautocomplete' cancelable CustomEvent
 * - dispatch the Event
 *
 * @param {HtmlInputElement} element
 */
function apbctAutocomplete(element) {
    if (element.hasAttribute('autocompleted')) return;
    element.setAttribute('autocompleted', '');

    let event = new window.CustomEvent('onautocomplete', {
        bubbles: true, cancelable: true, detail: null,
    });

    // no autofill if preventDefault is called
    if (!element.dispatchEvent(event)) {
        element.value = '';
    }
}

/**
 * Manage an input element when its autocompleted value is
 * removed by the browser in the following steps:
 * - remove [autocompleted] attribute from event.target
 * - create 'onautocomplete' non-cancelable CustomEvent
 * - dispatch the Event
 *
 * @param {HtmlInputElement} element
 */
function apbctCancelAutocomplete(element) {
    if (!element.hasAttribute('autocompleted')) return;
    element.removeAttribute('autocompleted');

    // dispatch event
    element.dispatchEvent(new window.CustomEvent('onautocomplete', {
        bubbles: true, cancelable: false, detail: null,
    }));
}

/**
 * ApbctForceProtection
 */
class ApbctForceProtection {
    wrappers = [];

    /**
     * Constructor
     */
    constructor() {
        this.wrappers = this.findWrappers();

        if (this.wrappers.length < 1) {
            return;
        }

        this.checkBot();
    }

    /**
     * Find wrappers
     * @return {HTMLElement[]}
     */
    findWrappers() {
        return document.querySelectorAll('div.ct-encoded-form-wrapper');
    }

    /**
     * Check bot
     * @return {void}
     */
    checkBot() {
        let data = {
            event_javascript_data: getJavascriptClientData(),
            post_url: document.location.href,
            referrer: document.referrer,
        };

        if (ctPublicFunctions.data__ajax_type === 'rest') {
            apbct_public_sendREST('force_protection_check_bot', {
                data,
                method: 'POST',
                callback: (result) => this.checkBotCallback(result),
            });
        } else if (ctPublicFunctions.data__ajax_type === 'admin_ajax') {
            data.action = 'apbct_force_protection_check_bot';
            apbct_public_sendAJAX(data, {callback: (result) => this.checkBotCallback(result)});
        }
    }

    /**
     * Check bot callback
     * @param {Object} result
     * @return {void}
     */
    checkBotCallback(result) {
        // if error occurred
        if (result.data && result.data.status && result.data.status !== 200) {
            console.log('ApbctForceProtection connection error occurred');
            this.decodeForms();
            return;
        }

        if (typeof result === 'string') {
            try {
                result = JSON.parse(result);
            } catch (e) {
                console.log('ApbctForceProtection decodeForms error', e);
                this.decodeForms();
                return;
            }
        }

        if (typeof result === 'object' && result.allow && result.allow === 1) {
            this.decodeForms();
        } else {
            this.showMessageForBot(result.message);
        }
    }

    /**
     * Decode forms
     * @return {void}
     */
    decodeForms() {
        let form;

        this.wrappers.forEach((wrapper) => {
            form = wrapper.querySelector('div.ct-encoded-form').dataset.encodedForm;

            try {
                if (form && typeof(form) == 'string') {
                    wrapper.outerHTML = atob(form);
                }
            } catch (error) {
                console.log(error);
            }
        });
    }

    /**
     * Show message for bot
     * @param {string} message
     * @return {void}
     */
    showMessageForBot(message) {
        let form;

        this.wrappers.forEach((wrapper) => {
            form = wrapper.querySelector('div.ct-encoded-form').dataset.encodedForm;
            if (form) {
                wrapper.outerHTML = '<div class="ct-encoded-form-forbidden">' + message + '</div>';
            }
        });
    }
}

/**
 * Force protection
 */
function apbctForceProtect() {
    if (ctPublic.settings__forms__force_protection && typeof ApbctForceProtection !== 'undefined') {
        new ApbctForceProtection();
    }
}

if (ctPublic.data__key_is_ok) {
    if (document.readyState !== 'loading') {
        apbctForceProtect();
    } else {
        apbct_attach_event_handler(document, 'DOMContentLoaded', apbctForceProtect);
    }
}

/**
 * Class for gathering data about user typing.
 *
 * ==============================
 * isAutoFill       - only person can use auto fill
 * isUseBuffer      - use buffer for fill current field
 * ==============================
 * lastKeyTimestamp - timestamp of last key press in current field
 * speedDelta       - change for each key press in current field,
 *                    as difference between current and previous key press timestamps,
 *                    robots in general have constant speed of typing.
 *                    If speedDelta is constant for each key press in current field,
 *                    so, speedDelta will be roughly to 0, then it is robot.
 * ==============================
 */
// eslint-disable-next-line no-unused-vars,require-jsdoc
class CTTypoData {
    fieldData = {
        isAutoFill: false,
        isUseBuffer: false,
        speedDelta: 0,
        firstKeyTimestamp: 0,
        lastKeyTimestamp: 0,
        lastDelta: 0,
        countOfKey: 0,
    };

    fields = document.querySelectorAll('textarea[name=comment]');

    data = [];

    /**
     * Gather fields.
     */
    gatheringFields() {
        let fieldSet = Array.prototype.slice.call(this.fields);
        fieldSet.forEach((field, i) => {
            this.data.push(Object.assign({}, this.fieldData));
        });
    }

    /**
     * Set listeners.
     */
    setListeners() {
        this.fields.forEach((field, i) => {
            field.addEventListener('paste', () => {
                this.data[i].isUseBuffer = true;
            });
        });

        this.fields.forEach((field, i) => {
            field.addEventListener('onautocomplete', () => {
                this.data[i].isAutoFill = true;
            });
        });

        this.fields.forEach((field, i) => {
            field.addEventListener('input', () => {
                this.data[i].countOfKey++;
                let time = + new Date();
                let currentDelta = 0;

                if (this.data[i].countOfKey === 1) {
                    this.data[i].lastKeyTimestamp = time;
                    this.data[i].firstKeyTimestamp = time;
                    return;
                }

                currentDelta = time - this.data[i].lastKeyTimestamp;
                if (this.data[i].countOfKey === 2) {
                    this.data[i].lastKeyTimestamp = time;
                    this.data[i].lastDelta = currentDelta;
                    return;
                }

                if (this.data[i].countOfKey > 2) {
                    this.data[i].speedDelta += Math.abs(this.data[i].lastDelta - currentDelta);
                    this.data[i].lastKeyTimestamp = time;
                    this.data[i].lastDelta = currentDelta;
                }
            });
        });
    }
}

// eslint-disable-next-line camelcase
const ctDate = new Date();
const ctTimeMs = new Date().getTime();
let ctMouseEventTimerFlag = true; // Reading interval flag
let ctMouseData = [];
let ctMouseDataCounter = 0;
let ctCheckedEmails = {};
let ctCheckedEmailsExist = {};
let ctMouseReadInterval;
let ctMouseWriteDataInterval;
let tokenCheckerIntervalId;
let botDetectorLogLastUpdate = 0;
let botDetectorLogEventTypesCollected = [];

// eslint-disable-next-line require-jsdoc,camelcase
function apbct_attach_event_handler(elem, event, callback) {
    if (typeof window.addEventListener === 'function') elem.addEventListener(event, callback);
    else elem.attachEvent(event, callback);
}
// eslint-disable-next-line require-jsdoc,camelcase
function apbct_remove_event_handler(elem, event, callback) {
    if (typeof window.removeEventListener === 'function') elem.removeEventListener(event, callback);
    else elem.detachEvent(event, callback);
}

// Writing first key press timestamp
const ctFunctionFirstKey = function output(event) {
    let KeyTimestamp = Math.floor(new Date().getTime() / 1000);
    ctSetCookie('ct_fkp_timestamp', KeyTimestamp);
    ctKeyStopStopListening();
};

/**
 * Run cron jobs
 */
// forms handler cron
cronFormsHandler(2000);

// bot_detector frontend_data log alt session saving cron
if (
    ctPublicFunctions.hasOwnProperty('data__bot_detector_enabled') &&
    ctPublicFunctions.data__bot_detector_enabled == 1 &&
    ctPublicFunctions.hasOwnProperty('data__frontend_data_log_enabled') &&
    ctPublicFunctions.data__frontend_data_log_enabled == 1
) {
    sendBotDetectorLogToAltSessions(1000);
}
/**
 * Cron jobs end.
 */

// mouse read
if (ctPublic.data__key_is_ok) {
    // Reading interval
    ctMouseReadInterval = setInterval(function() {
        ctMouseEventTimerFlag = true;
    }, 150);

    // Writting interval
    ctMouseWriteDataInterval = setInterval(function() {
        ctSetCookie('ct_pointer_data', JSON.stringify(ctMouseData));
    }, 1200);
}

// Logging mouse position each 150 ms
const ctFunctionMouseMove = function output(event) {
    ctSetMouseMoved();
    if (ctMouseEventTimerFlag === true) {
        ctMouseData.push([
            Math.round(event.clientY),
            Math.round(event.clientX),
            Math.round(new Date().getTime() - ctTimeMs),
        ]);

        ctMouseDataCounter++;
        ctMouseEventTimerFlag = false;
        if (ctMouseDataCounter >= 50) {
            ctMouseStopData();
        }
    }
};

/**
 * Do handle periodical actions.
 * @param {int} cronStartTimeout Time to go before cron start.
 */
function cronFormsHandler(cronStartTimeout = 2000) {
    setTimeout(function() {
        setInterval(function() {
            restartFieldsListening();
            restartBotDetectorEventTokenAttach();
        }, 2000);
    }, cronStartTimeout);
}

/**
 * Send BotDetector logs data to alternative sessions.
 * If log_last_update has changed and log contains new event types, the log will be sent to the alternative sessions.
 * @param {int} cronStartTimeout delay before cron start
 * @param {int} interval check fires on interval
 */
function sendBotDetectorLogToAltSessions(cronStartTimeout = 3000, interval = 1000) {
    setTimeout(function() {
        setInterval(function() {
            const currentLog = apbctLocalStorage.get('ct_bot_detector_frontend_data_log');
            if (needsSaveLogToAltSessions(currentLog)) {
                botDetectorLogLastUpdate = currentLog.log_last_update;
                // the log will be taken from javascriptclientdata
                ctSetAlternativeCookie([], {forceAltCookies: true});
            }
        }, interval);
    }, cronStartTimeout);
}

/**
 * Check if the log needs to be saved to the alt sessions. If the log has new event types, it will be saved.
 * @param {object} currentLog
 * @return {boolean}
 */
function needsSaveLogToAltSessions(currentLog) {
    if (
        currentLog && currentLog.hasOwnProperty('log_last_update') &&
        botDetectorLogLastUpdate !== currentLog.log_last_update
    ) {
        try {
            for (let i = 0; i < currentLog.records.length; i++) {
                const currentType = currentLog.records[i].frontend_data.js_event;
                // check if this event type was already collected
                if (currentType !== undefined && botDetectorLogEventTypesCollected.includes(currentType)) {
                    continue;
                }
                // add new event type to collection, this type will be sent to the alt sessions further
                botDetectorLogEventTypesCollected.push(currentType);
                return true;
            }
        } catch (e) {
            console.log('APBCT: bot detector log collection error: ' . e.toString());
        }
    }
    return false;
}

/**
 * Restart event_token attachment if some forms load after document ready.
 */
function restartBotDetectorEventTokenAttach() {
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

/**
 * Stop mouse observing function
 */
function ctMouseStopData() {
    apbct_remove_event_handler(document, 'mousemove', ctFunctionMouseMove);
    clearInterval(ctMouseReadInterval);
    clearInterval(ctMouseWriteDataInterval);
}

/**
 * Stop key listening function
 */
function ctKeyStopStopListening() {
    apbct_remove_event_handler(document, 'mousedown', ctFunctionFirstKey);
    apbct_remove_event_handler(document, 'keydown', ctFunctionFirstKey);
}

/**
 * @param {mixed} e
 */
function checkEmail(e) {
    let currentEmail = e.target.value;

    if (! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(currentEmail)) {
        return;
    }

    if (currentEmail && !(currentEmail in ctCheckedEmails)) {
        // Using REST API handler
        if ( ctPublicFunctions.data__ajax_type === 'rest' ) {
            apbct_public_sendREST(
                'check_email_before_post',
                {
                    method: 'POST',
                    data: {'email': currentEmail},
                    callback: function(result) {
                        if (result.result) {
                            ctCheckedEmails[currentEmail] = {
                                'result': result.result,
                                'timestamp': Date.now() / 1000 |0,
                            };
                            ctSetCookie('ct_checked_emails', JSON.stringify(ctCheckedEmails));
                        }
                    },
                },
            );
            // Using AJAX request and handler
        } else if ( ctPublicFunctions.data__ajax_type === 'admin_ajax' ) {
            apbct_public_sendAJAX(
                {
                    action: 'apbct_email_check_before_post',
                    email: currentEmail,
                },
                {
                    callback: function(result) {
                        if (result.result) {
                            ctCheckedEmails[currentEmail] = {
                                'result': result.result,
                                'timestamp': Date.now() / 1000 |0,
                            };
                            ctSetCookie('ct_checked_emails', JSON.stringify(ctCheckedEmails));
                        }
                    },
                },
            );
        }
    }
}

/**
 * @param {mixed} e
 */
function checkEmailExist(e) {
    let currentEmail = e.target.value;
    let result;

    if (!currentEmail || !currentEmail.length) {
        let envelope = document.getElementById('apbct-check_email_exist-block');
        if (envelope) {
            envelope.remove();
        }
        let hint = document.getElementById('apbct-check_email_exist-popup_description');
        if (hint) {
            hint.remove();
        }
        return;
    }

    if (! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(currentEmail)) {
        return;
    }

    if (currentEmail in ctCheckedEmailsExist) {
        result = ctCheckedEmailsExist[currentEmail];
        getResultCheckEmailExist(e, result, currentEmail);

        return;
    }

    viewCheckEmailExist(e, 'load');

    // Using REST API handler
    ctPublicFunctions.data__ajax_type = 'rest';
    if (ctPublicFunctions.data__ajax_type === 'rest') {
        apbct_public_sendREST(
            'check_email_exist_post',
            {
                method: 'POST',
                data: {'email': currentEmail},
                callback: function(result) {
                    getResultCheckEmailExist(e, result, currentEmail);
                },
            },
        );

        return;
    }

    if (ctPublicFunctions.data__ajax_type === 'admin_ajax') {
        apbct_public_sendAJAX(
            {
                action: 'apbct_email_check_exist_post',
                email: currentEmail,
            },
            {
                callback: function(result) {
                    getResultCheckEmailExist(e, result, currentEmail);
                },
            },
        );
    }
}

/**
 * @param {mixed} e
 * @param {mixed} result
 * @param {string} currentEmail
 */
function getResultCheckEmailExist(e, result, currentEmail) {
    if (!result || !result.result) {
        return;
    }

    result = result.result;

    ctCheckedEmailsExist[currentEmail] = {
        'result': result,
        'timestamp': Date.now() / 1000 |0,
    };

    if (result.result == 'EXISTS') {
        viewCheckEmailExist(e, 'good_email', result.text_result);
    } else {
        viewCheckEmailExist(e, 'bad_email', result.text_result);
    }

    ctSetCookie('ct_checked_emails_exist', JSON.stringify(ctCheckedEmailsExist));
}

/**
 * @param {mixed} e
 * @param {string} state
 * @param {string} textResult
 */
function viewCheckEmailExist(e, state, textResult) {
    let parentElement = e.target.parentElement;
    let inputEmail = parentElement.querySelector('[name*="email"]');

    if (!inputEmail) {
        return;
    }

    let envelope;
    let hint;

    // envelope
    if (document.getElementById('apbct-check_email_exist-block')) {
        envelope = document.getElementById('apbct-check_email_exist-block');
    } else {
        envelope = document.createElement('div');
        envelope.setAttribute('class', 'apbct-check_email_exist-block');
        envelope.setAttribute('id', 'apbct-check_email_exist-block');
        window.addEventListener('scroll', function() {
            envelope.style.top = inputEmail.getBoundingClientRect().top + 'px';
        });
        parentElement.after(envelope);
    }

    // hint
    if (document.getElementById('apbct-check_email_exist-popup_description')) {
        hint = document.getElementById('apbct-check_email_exist-popup_description');
    } else {
        hint = document.createElement('div');
        hint.setAttribute('class', 'apbct-check_email_exist-popup_description');
        hint.setAttribute('id', 'apbct-check_email_exist-popup_description');
        window.addEventListener('scroll', function() {
            hint.style.top = envelope.getBoundingClientRect().top + 'px';
        });

        envelope.after(hint);
    }

    ctEmailExistSetElementsPositions();

    window.addEventListener('resize', function(event) {
        ctEmailExistSetElementsPositions();
    });

    switch (state) {
    case 'load':
        envelope.classList.remove('apbct-check_email_exist-good_email', 'apbct-check_email_exist-bad_email');
        envelope.classList.add('apbct-check_email_exist-load');
        break;

    case 'good_email':
        envelope.classList.remove('apbct-check_email_exist-load', 'apbct-check_email_exist-bad_email');
        envelope.classList.add('apbct-check_email_exist-good_email');

        envelope.onmouseover = function() {
            hint.textContent = textResult;
            hint.style.display = 'block';
            hint.style.top = inputEmail.getBoundingClientRect().top - hint.getBoundingClientRect().height + 'px';
            hint.style.color = '#1C7129';
        };

        envelope.onmouseout = function() {
            hint.style.display = 'none';
        };

        break;

    case 'bad_email':
        envelope.classList.remove('apbct-check_email_exist-load', 'apbct-check_email_exist-good_email');
        envelope.classList.add('apbct-check_email_exist-bad_email');

        envelope.onmouseover = function() {
            hint.textContent = textResult;
            hint.style.display = 'block';
            hint.style.top = inputEmail.getBoundingClientRect().top - hint.getBoundingClientRect().height + 'px';
            hint.style.color = '#E01111';
        };

        envelope.onmouseout = function() {
            hint.style.display = 'none';
        };

        break;

    default:
        break;
    }
}

/**
 * Shift the envelope to the input field on resizing the window
 * @param {object} envelope
 * @param {object} inputEmail
 */
function ctEmailExistSetElementsPositions() {
    const envelopeWidth = 35;
    const inputEmail = document.querySelector('comment-form input[name*="email"], input#email');
    if (!inputEmail) {
        return;
    }
    const envelope = document.getElementById('apbct-check_email_exist-block');
    if (envelope) {
        envelope.style.top = inputEmail.getBoundingClientRect().top + 'px';
        envelope.style.left = inputEmail.getBoundingClientRect().right - envelopeWidth - 10 + 'px';
        envelope.style.height = inputEmail.offsetHeight + 'px';
        envelope.style.width = envelopeWidth + 'px';
    }

    const hint = document.getElementById('apbct-check_email_exist-popup_description');
    if (hint) {
        hint.style.width = inputEmail.offsetWidth + 'px';
        hint.style.left = inputEmail.getBoundingClientRect().left + 'px';
    }
}

/**
 * @return {bool}
 */
function ctIsDrawPixel() {
    return +ctPublic.pixel__enabled ||
        (ctPublic.data__cookies_type === 'none' && document.querySelectorAll('img#apbct_pixel').length === 0) ||
        (ctPublic.data__cookies_type === 'alternative' && document.querySelectorAll('img#apbct_pixel').length === 0);
}

/**
 * @param {string} pixelUrl
 */
function ctSetPixelImg(pixelUrl) {
    ctSetCookie('apbct_pixel_url', pixelUrl);
    if ( ctIsDrawPixel() ) {
        if ( ! document.getElementById('apbct_pixel') ) {
            let insertedImg = document.createElement('img');
            insertedImg.setAttribute('alt', 'CleanTalk Pixel');
            insertedImg.setAttribute('title', 'CleanTalk Pixel');
            insertedImg.setAttribute('id', 'apbct_pixel');
            insertedImg.setAttribute('style', 'display: none; left: 99999px;');
            insertedImg.setAttribute('src', pixelUrl);
            apbct('body').append(insertedImg);
        }
    }
}

/**
 * @param {string} pixelUrl
 */
function ctSetPixelImgFromLocalstorage(pixelUrl) {
    if ( ctIsDrawPixel() ) {
        if ( ! document.getElementById('apbct_pixel') ) {
            let insertedImg = document.createElement('img');
            insertedImg.setAttribute('alt', 'CleanTalk Pixel');
            insertedImg.setAttribute('title', 'CleanTalk Pixel');
            insertedImg.setAttribute('id', 'apbct_pixel');
            insertedImg.setAttribute('style', 'display: none; left: 99999px;');
            insertedImg.setAttribute('src', decodeURIComponent(pixelUrl));
            apbct('body').append(insertedImg);
        }
    }
}

/**
 * ctGetPixelUrl
 */
function ctGetPixelUrl() {
    // Check if pixel is already in localstorage and is not outdated
    let localStoragePixelUrl = apbctLocalStorage.get('apbct_pixel_url');
    if ( localStoragePixelUrl !== false ) {
        if ( ! apbctLocalStorage.isAlive('apbct_pixel_url', 3600 * 3) ) {
            apbctLocalStorage.delete('apbct_pixel_url');
        } else {
            // if so - load pixel from localstorage and draw it skipping AJAX
            ctSetPixelImgFromLocalstorage(localStoragePixelUrl);
            return;
        }
    }
    // Using REST API handler
    if ( ctPublicFunctions.data__ajax_type === 'rest' ) {
        apbct_public_sendREST(
            'apbct_get_pixel_url',
            {
                method: 'POST',
                callback: function(result) {
                    if (result &&
                        (typeof result === 'string' || result instanceof String) && result.indexOf('https') === 0) {
                        // set  pixel url to localstorage
                        if ( ! apbctLocalStorage.get('apbct_pixel_url') ) {
                            // set pixel to the storage
                            apbctLocalStorage.set('apbct_pixel_url', result);
                            // update pixel data in the hidden fields
                            ctNoCookieAttachHiddenFieldsToForms();
                        }
                        // then run pixel drawing
                        ctSetPixelImg(result);
                    }
                },
            },
        );
        // Using AJAX request and handler
    } else {
        apbct_public_sendAJAX(
            {
                action: 'apbct_get_pixel_url',
            },
            {
                notJson: true,
                callback: function(result) {
                    if (result &&
                        (typeof result === 'string' || result instanceof String) && result.indexOf('https') === 0) {
                        // set  pixel url to localstorage
                        if ( ! apbctLocalStorage.get('apbct_pixel_url') ) {
                            // set pixel to the storage
                            apbctLocalStorage.set('apbct_pixel_url', result);
                            // update pixel data in the hidden fields
                            ctNoCookieAttachHiddenFieldsToForms();
                        }
                        // then run pixel drawing
                        ctSetPixelImg(result);
                    }
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-Robots-Tag', 'noindex, nofollow');
                },
            },
        );
    }
}

/**
 * ctSetHasScrolled
 */
function ctSetHasScrolled() {
    if ( ! apbctLocalStorage.isSet('ct_has_scrolled') || ! apbctLocalStorage.get('ct_has_scrolled') ) {
        ctSetCookie('ct_has_scrolled', 'true');
        apbctLocalStorage.set('ct_has_scrolled', true);
    }
    if (
        ctPublic.data__cookies_type === 'native' &&
        ctGetCookie('ct_has_scrolled') === undefined
    ) {
        ctSetCookie('ct_has_scrolled', 'true');
    }
}

/**
 * ctSetMouseMoved
 */
function ctSetMouseMoved() {
    if ( ! apbctLocalStorage.isSet('ct_mouse_moved') || ! apbctLocalStorage.get('ct_mouse_moved') ) {
        ctSetCookie('ct_mouse_moved', 'true');
        apbctLocalStorage.set('ct_mouse_moved', true);
    }
    if (
        ctPublic.data__cookies_type === 'native' &&
        ctGetCookie('ct_mouse_moved') === undefined
    ) {
        ctSetCookie('ct_mouse_moved', 'true');
    }
}

/**
 * Restart listen fields to set ct_has_input_focused or ct_has_key_up
 */
function restartFieldsListening() {
    if (!apbctLocalStorage.isSet('ct_has_input_focused') && !apbctLocalStorage.isSet('ct_has_key_up')) {
        ctStartFieldsListening();
    }
}

/**
 * init listeners for keyup and focus events
 */
function ctStartFieldsListening() {
    if (
        (apbctLocalStorage.isSet('ct_has_key_up') || apbctLocalStorage.get('ct_has_key_up')) &&
        (apbctLocalStorage.isSet('ct_has_input_focused') || apbctLocalStorage.get('ct_has_input_focused')) &&
        (
            ctPublic.data__cookies_type === 'native' &&
            ctGetCookie('ct_has_input_focused') !== undefined &&
            ctGetCookie('ct_has_key_up') !== undefined
        )
    ) {
        // already set
        return;
    }

    let forms = ctGetPageForms();
    ctPublic.handled_fields = [];

    if (forms.length > 0) {
        for (let i = 0; i < forms.length; i++) {
            // handle only inputs and textareas
            const handledFormFields = forms[i].querySelectorAll('input,textarea');
            for (let i = 0; i < handledFormFields.length; i++) {
                if (handledFormFields[i].type !== 'hidden') {
                    // collect handled fields to remove handler in the future
                    ctPublic.handled_fields.push(handledFormFields[i]);
                    // do attach handlers
                    apbct_attach_event_handler(handledFormFields[i], 'focus', ctFunctionHasInputFocused);
                    apbct_attach_event_handler(handledFormFields[i], 'keyup', ctFunctionHasKeyUp);
                }
            }
        }
    }
}

/**
 * stop listening keyup and focus
 * @param {string} eventName
 * @param {string} functionName
 */
function ctStopFieldsListening(eventName, functionName) {
    if (typeof ctPublic.handled_fields !== 'undefined' && ctPublic.handled_fields.length > 0) {
        for (let i = 0; i < ctPublic.handled_fields.length; i++) {
            apbct_remove_event_handler(ctPublic.handled_fields[i], eventName, functionName);
        }
    }
}

let ctFunctionHasInputFocused = function output(event) {
    ctSetHasInputFocused();
    ctStopFieldsListening('focus', ctFunctionHasInputFocused);
};

let ctFunctionHasKeyUp = function output(event) {
    ctSetHasKeyUp();
    ctStopFieldsListening('keyup', ctFunctionHasKeyUp);
};

/**
 * set ct_has_input_focused ct_has_key_up cookies on session period
 */
function ctSetHasInputFocused() {
    if ( ! apbctLocalStorage.isSet('ct_has_input_focused') || ! apbctLocalStorage.get('ct_has_input_focused') ) {
        apbctLocalStorage.set('ct_has_input_focused', true);
    }
    if (
        (
            (
                ctPublic.data__cookies_type === 'native' &&
                ctGetCookie('ct_has_input_focused') === undefined
            ) ||
            ctPublic.data__cookies_type === 'alternative'
        ) ||
        (
            ctPublic.data__cookies_type === 'none' &&
            (
                typeof ctPublic.force_alt_cookies !== 'undefined' ||
                (ctPublic.force_alt_cookies !== undefined && ctPublic.force_alt_cookies)
            )
        )
    ) {
        ctSetCookie('ct_has_input_focused', 'true');
    }
}

/**
 * ctSetHasKeyUp
 */
function ctSetHasKeyUp() {
    if ( ! apbctLocalStorage.isSet('ct_has_key_up') || ! apbctLocalStorage.get('ct_has_key_up') ) {
        apbctLocalStorage.set('ct_has_key_up', true);
    }
    if (
        (
            (
                ctPublic.data__cookies_type === 'native' &&
                ctGetCookie('ct_has_key_up') === undefined
            ) ||
            ctPublic.data__cookies_type === 'alternative'
        ) ||
        (
            ctPublic.data__cookies_type === 'none' &&
            (
                typeof ctPublic.force_alt_cookies !== 'undefined' ||
                (ctPublic.force_alt_cookies !== undefined && ctPublic.force_alt_cookies)
            )
        )
    ) {
        ctSetCookie('ct_has_key_up', 'true');
    }
}

if (ctPublic.data__key_is_ok) {
    apbct_attach_event_handler(document, 'mousemove', ctFunctionMouseMove);
    apbct_attach_event_handler(document, 'mousedown', ctFunctionFirstKey);
    apbct_attach_event_handler(document, 'keydown', ctFunctionFirstKey);
    apbct_attach_event_handler(document, 'scroll', ctSetHasScrolled);
}

/**
 * Prepare block to intercept AJAX response
 */
function apbctPrepareBlockForAjaxForms() {
    // eslint-disable-next-line require-jsdoc
    function ctPrepareBlockMessage(xhr) {
        if (xhr.responseText &&
            xhr.responseText.indexOf('"apbct') !== -1 &&
            xhr.responseText.indexOf('DOCTYPE') === -1
        ) {
            try {
                ctParseBlockMessage(JSON.parse(xhr.responseText));
            } catch (e) {
                console.log(e.toString());
            }
        }
    }

    if (typeof jQuery !== 'undefined') {
        // Capturing responses and output block message for unknown AJAX forms
        if (typeof jQuery(document).ajaxComplete() !== 'function') {
            jQuery(document).on('ajaxComplete', function(event, xhr, settings) {
                ctPrepareBlockMessage(xhr);
            });
        } else {
            jQuery(document).ajaxComplete( function(event, xhr, settings) {
                ctPrepareBlockMessage(xhr);
            });
        }
    } else {
        // if Jquery is not avaliable try to use xhr
        if (typeof XMLHttpRequest !== 'undefined') {
            // Capturing responses and output block message for unknown AJAX forms
            document.addEventListener('readystatechange', function(event) {
                if (event.target.readyState === 4) {
                    ctPrepareBlockMessage(event.target);
                }
            });
        }
    }
}

/**
 * For forced alt cookies.
 * If token is not added to the LS on apbc_ready, check every second if so and send token to the alt sessions.
 */
function startForcedAltEventTokenChecker() {
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


/**
 * Ready function
 */
// eslint-disable-next-line camelcase,require-jsdoc
function apbct_ready() {
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
    if ( ! ctPublic.wc_ajax_add_to_cart ) {
        apbctCheckAddToCartByGet();
    }

    apbctPrepareBlockForAjaxForms();

    // set session ID
    if (!apbctSessionStorage.isSet('apbct_session_id')) {
        const sessionID = apbctGenerateUniqueID();
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

    if ( +ctPublic.pixel__setting ) {
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
                    setTimeout(function() {
                        event.target.onsubmit_prev.call(event.target, event);
                    }, 500);
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

            // this handles search forms onsubmit process
            _form.apbctSearchPrevOnsubmit = _form.onsubmit;
            _form.onsubmit = (e) => ctSearchFormOnSubmitHandler(e, _form);
        }
    }

    // Check any XMLHttpRequest connections
    apbctCatchXmlHttpRequest();

    // Init form skin
    if (ctPublic.settings__comments__form_decoration) {
        new ApbctFormDecorator();
    }

    // Set important paramaters via ajax if problematic cache solutions found
    // todo These AJAX calls removed untill we find a better solution, reason is a lot of requests to the server.
    // apbctAjaxSetImportantParametersOnCacheExist(ctPublic.advancedCacheExists || ctPublic.varnishCacheExists);

    // Checking that the bot detector has loaded and received the event token for Anti-Crawler
    if (ctPublic.settings__sfw__anti_crawler) {
        checkBotDetectorExist();
    }
}

/**
 * Checking that the bot detector has loaded and received the event token
 */
function checkBotDetectorExist() {
    if (ctPublic.settings__data__bot_detector_enabled) {
        const botDetectorIntervalSearch = setInterval(() => {
            let botDetectorEventToken = localStorage.bot_detector_event_token ? true : false;

            if (botDetectorEventToken) {
                ctSetCookie('apbct_bot_detector_exist', '1', '3600');
                clearInterval(botDetectorIntervalSearch);
            }
        }, 500);
    }
}

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
        }

        // checkout
        if (options.path === '/wc/store/v1/checkout') {
            options.data.ct_no_cookie_hidden_field = getNoCookieData();
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
 * Run AJAX to set important_parameters on the site backend if problematic cache solutions are defined.
 * @param {boolean} cacheExist
 */
function apbctAjaxSetImportantParametersOnCacheExist(cacheExist) { // eslint-disable-line no-unused-vars
    // Set important parameters via ajax
    if ( cacheExist ) {
        if ( ctPublicFunctions.data__ajax_type === 'rest' ) {
            apbct_public_sendREST('apbct_set_important_parameters', {});
        } else if ( ctPublicFunctions.data__ajax_type === 'admin_ajax' ) {
            apbct_public_sendAJAX({action: 'apbct_set_important_parameters'}, {});
        }
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

/**
 * @param {SubmitEvent} e
 * @param {object} targetForm
 */
function ctSearchFormOnSubmitHandler(e, targetForm) {
    try {
        // get honeypot field and it's value
        const honeyPotField = targetForm.querySelector('[id*="apbct__email_id__"]');
        let hpValue = null;
        let hpEventId = null;
        if (
            honeyPotField !== null &&
            honeyPotField.value !== null &&
            honeyPotField.getAttribute('apbct_event_id') !== null
        ) {
            hpValue = honeyPotField.value;
            hpEventId = honeyPotField.getAttribute('apbct_event_id');
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
            if ( hpValue !== null && hpEventId !== null ) {
                cookiesArray.apbct_search_form__honeypot_value = hpValue;
                cookiesArray.apbct_search_form__honeypot_id = hpEventId;
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
 * @param {mixed} commonCookies
 * @return {string}
 */
function getJavascriptClientData(commonCookies = []) { // eslint-disable-line no-unused-vars
    let resultDataJson = {};

    resultDataJson.ct_checked_emails = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_checked_emails');
    resultDataJson.ct_checked_emails_exist = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_checked_emails_exist');
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
    const apbctPageHits = apbctLocalStorage.get('apbct_page_hits');
    const apbctPrevReferer = apbctSessionStorage.get('apbct_prev_referer');
    const apbctSiteReferer = apbctSessionStorage.get('apbct_site_referer');
    const ctJsErrorsLocalStorage = apbctLocalStorage.get(ctPublicFunctions.cookiePrefix + 'ct_js_errors');
    const ctPixelUrl = apbctLocalStorage.get(ctPublicFunctions.cookiePrefix + 'apbct_pixel_url');
    const apbctHeadless = apbctLocalStorage.get(ctPublicFunctions.cookiePrefix + 'apbct_headless');
    const ctBotDetectorFrontendDataLog = apbctLocalStorage.get(
        ctPublicFunctions.cookiePrefix + 'ct_bot_detector_frontend_data_log',
    );

    // collecting data from cookies
    const ctMouseMovedCookie = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_mouse_moved');
    const ctHasScrolledCookie = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_has_scrolled');
    const ctCookiesTypeCookie = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_cookies_type');
    const ctCookiesPixelUrl = ctGetCookie(ctPublicFunctions.cookiePrefix + 'apbct_pixel_url');
    const apbctHeadlessNative = !!ctGetCookie(ctPublicFunctions.cookiePrefix + 'apbct_headless');


    resultDataJson.ct_mouse_moved = ctMouseMovedLocalStorage !== undefined ?
        ctMouseMovedLocalStorage : ctMouseMovedCookie;
    resultDataJson.ct_has_scrolled = ctHasScrolledLocalStorage !== undefined ?
        ctHasScrolledLocalStorage : ctHasScrolledCookie;
    resultDataJson.ct_cookies_type = ctCookiesTypeLocalStorage !== undefined ?
        ctCookiesTypeLocalStorage : ctCookiesTypeCookie;
    resultDataJson.apbct_pixel_url = ctPixelUrl !== undefined ?
        ctPixelUrl : ctCookiesPixelUrl;
    resultDataJson.apbct_headless = apbctHeadless !== undefined ?
        apbctHeadless : apbctHeadlessNative;
    resultDataJson.ct_bot_detector_frontend_data_log = ctBotDetectorFrontendDataLog !== undefined ?
        ctBotDetectorFrontendDataLog : '';
    if (resultDataJson.apbct_pixel_url && typeof(resultDataJson.apbct_pixel_url) == 'string') {
        if (resultDataJson.apbct_pixel_url.indexOf('%3A%2F')) {
            resultDataJson.apbct_pixel_url = decodeURIComponent(resultDataJson.apbct_pixel_url);
        }
    }

    resultDataJson.apbct_page_hits = apbctPageHits;
    resultDataJson.apbct_prev_referer = apbctPrevReferer;
    resultDataJson.apbct_site_referer = apbctSiteReferer;
    resultDataJson.apbct_ct_js_errors = ctJsErrorsLocalStorage;

    if (!resultDataJson.apbct_pixel_url) {
        resultDataJson.apbct_pixel_url = ctPublic.pixel__url;
    }

    if ( typeof (commonCookies) === 'object') {
        for (let i = 0; i < commonCookies.length; ++i) {
            if ( typeof (commonCookies[i][1]) === 'object' ) {
                // this is for handle SFW cookies
                resultDataJson[commonCookies[i][1][0]] = commonCookies[i][1][1];
            } else {
                resultDataJson[commonCookies[i][0]] = commonCookies[i][1];
            }
        }
    } else {
        console.log('APBCT JS ERROR: Collecting data type mismatch');
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
 * @param {mixed} object
 * @return {*}
 */
function removeDoubleJsonEncoding(object) {
    if ( typeof object === 'object') {
        // eslint-disable-next-line guard-for-in
        for (let objectKey in object) {
            // Recursion
            if ( typeof object[objectKey] === 'object') {
                object[objectKey] = removeDoubleJsonEncoding(object[objectKey]);
            }

            // Common case (out)
            if (
                typeof object[objectKey] === 'string' &&
                object[objectKey].match(/^[\[{].*?[\]}]$/) !== null // is like JSON
            ) {
                const parsedValue = JSON.parse(object[objectKey]);
                if ( typeof parsedValue === 'object' ) {
                    object[objectKey] = parsedValue;
                }
            }
        }
    }

    return object;
}

// eslint-disable-next-line camelcase,require-jsdoc
function apbct_collect_visible_fields( form ) {
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

// eslint-disable-next-line camelcase,require-jsdoc
function apbct_visible_fields_set_cookie( visibleFieldsCollection, formId ) {
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

// eslint-disable-next-line camelcase,require-jsdoc,no-unused-vars
function apbct_js_keys__set_input_value(result, data, params, obj) {
    if ( document.querySelectorAll('[name^=ct_checkjs]').length > 0 ) {
        let elements = document.querySelectorAll('[name^=ct_checkjs]');
        for ( let i = 0; i < elements.length; i++ ) {
            elements[i].value = result.js_key;
        }
    }
}

/**
 * @return {string}
 */
function apbctGetScreenInfo() {
    return JSON.stringify({
        fullWidth: document.documentElement.scrollWidth,
        fullHeight: Math.max(
            document.body.scrollHeight, document.documentElement.scrollHeight,
            document.body.offsetHeight, document.documentElement.offsetHeight,
            document.body.clientHeight, document.documentElement.clientHeight,
        ),
        visibleWidth: document.documentElement.clientWidth,
        visibleHeight: document.documentElement.clientHeight,
    });
}

// eslint-disable-next-line require-jsdoc
function ctParseBlockMessage(response) {
    if (typeof response.apbct !== 'undefined') {
        response = response.apbct;
        if (response.blocked) {
            document.dispatchEvent(
                new CustomEvent( 'apbctAjaxBockAlert', {
                    bubbles: true,
                    detail: {message: response.comment},
                } ),
            );

            // Show the result by modal
            cleantalkModal.loaded = response.comment;
            cleantalkModal.open();

            if (+response.stop_script === 1) {
                window.stop();
            }
        }
    }
}

// eslint-disable-next-line no-unused-vars,require-jsdoc
function ctSetPixelUrlLocalstorage(ajaxPixelUrl) {
    // set pixel to the storage
    ctSetCookie('apbct_pixel_url', ajaxPixelUrl);
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
 * Contains {...noCookieDataLocal, ...noCookieDataSession, ...noCookieDataTypo, ...noCookieDataFromDecoration}.
 * @return {string}
 */
function getCleanTalkStorageDataArray() {
    let noCookieDataLocal = apbctLocalStorage.getCleanTalkData();
    let noCookieDataSession = apbctSessionStorage.getCleanTalkData();

    let noCookieDataTypo = {typo: []};
    if (document.ctTypoData && document.ctTypoData.data) {
        noCookieDataTypo = {typo: document.ctTypoData.data};
    }

    let noCookieDataFromDecoration = {form_decoration_mouse_data: []};
    if (document.ctFormDecorationMouseData) {
        let formDecorationMouseData = JSON.parse(JSON.stringify(document.ctFormDecorationMouseData));
        if (formDecorationMouseData.mouseMovements) {
            delete formDecorationMouseData.mouseMovements;
        }
        noCookieDataFromDecoration = {form_decoration_mouse_data: formDecorationMouseData};
    }
    return {...noCookieDataLocal, ...noCookieDataSession, ...noCookieDataTypo, ...noCookieDataFromDecoration};
}

/**
 * @return {boolean|*}
 */
function ctGetPageForms() {
    let forms = document.forms;
    if (forms) {
        return forms;
    }
    return false;
}

/**
 * Get type of the field should be excluded. Return exclusion signs via object.
 * @param {object} form Form dom object.
 * @return {object} {'no_cookie': 1|0, 'visible_fields': 1|0}
 */
function ctGetHiddenFieldExclusionsType(form) {
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
 * Check if the form should be skipped from hidden field attach.
 * Return exclusion description if it is found, false otherwise.
 * @param {object} form Form dom object.
 * @param {string} hiddenFieldType Type of hidden field that needs to be checked.
 * Possible values: 'no_cookie'|'visible_fields'.
 * @return {boolean}
 */
function ctCheckHiddenFieldsExclusions(form, hiddenFieldType) {
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
        const exclusions = ctGetHiddenFieldExclusionsType(form);
        return exclusions[hiddenFieldType] === 1;
    }

    return false;
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

const defaultFetch = window.fetch;
const defaultSend = XMLHttpRequest.prototype.send;

if (document.readyState !== 'loading') {
    checkFormsExistForCatching();
} else {
    apbct_attach_event_handler(document, 'DOMContentLoaded', checkFormsExistForCatching);
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
 * Set three statements to the sessions storage: apbct_session_current_page, apbct_prev_referer.
 * @return {void}
 */
function apbctWriteReferrersToSessionStorage() {
    const sessionCurrentPage = apbctSessionStorage.get('apbct_session_current_page');

    // set session apbct_referer
    if (sessionCurrentPage!== false && document.location.href !== sessionCurrentPage) {
        apbctSessionStorage.set('apbct_prev_referer', sessionCurrentPage, false);
    }

    // set session current page to know referrer
    apbctSessionStorage.set('apbct_session_current_page', document.location.href, false);
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

/* Cleantalk Modal object */
let cleantalkModal = {

    // Flags
    loaded: false,
    loading: false,
    opened: false,
    opening: false,
    ignoreURLConvert: false,

    // Methods
    load: function( action ) {
        if ( ! this.loaded ) {
            this.loading = true;
            let callback = function( result, data, params, obj ) {
                cleantalkModal.loading = false;
                cleantalkModal.loaded = result;
                document.dispatchEvent(
                    new CustomEvent( 'cleantalkModalContentLoaded', {
                        bubbles: true,
                    } ),
                );
            };
            // eslint-disable-next-line camelcase
            if ( typeof apbct_admin_sendAJAX === 'function' ) {
                apbct_admin_sendAJAX( {'action': action}, {'callback': callback, 'notJson': true} );
            } else {
                apbct_public_sendAJAX( {'action': action}, {'callback': callback, 'notJson': true} );
            }
        }
    },

    open: function() {
        /* Cleantalk Modal CSS start */
        let renderCss = function() {
            let cssStr = '';
            // eslint-disable-next-line guard-for-in
            for ( const key in this.styles ) {
                cssStr += key + ':' + this.styles[key] + ';';
            }
            return cssStr;
        };
        let overlayCss = {
            styles: {
                'z-index': '9999999999',
                'position': 'fixed',
                'top': '0',
                'left': '0',
                'width': '100%',
                'height': '100%',
                'background': 'rgba(0,0,0,0.5)',
                'display': 'flex',
                'justify-content': 'center',
                'align-items': 'center',
            },
            toString: renderCss,
        };
        let innerCss = {
            styles: {
                'position': 'relative',
                'padding': '30px',
                'background': '#FFF',
                'border': '1px solid rgba(0,0,0,0.75)',
                'border-radius': '4px',
                'box-shadow': '7px 7px 5px 0px rgba(50,50,50,0.75)',
            },
            toString: renderCss,
        };
        let closeCss = {
            styles: {
                'position': 'absolute',
                'background': '#FFF',
                'width': '20px',
                'height': '20px',
                'border': '2px solid rgba(0,0,0,0.75)',
                'border-radius': '15px',
                'cursor': 'pointer',
                'top': '-8px',
                'right': '-8px',
                'box-sizing': 'content-box',
            },
            toString: renderCss,
        };
        let closeCssBefore = {
            styles: {
                'content': '""',
                'display': 'block',
                'position': 'absolute',
                'background': '#000',
                'border-radius': '1px',
                'width': '2px',
                'height': '16px',
                'top': '2px',
                'left': '9px',
                'transform': 'rotate(45deg)',
            },
            toString: renderCss,
        };
        let closeCssAfter = {
            styles: {
                'content': '""',
                'display': 'block',
                'position': 'absolute',
                'background': '#000',
                'border-radius': '1px',
                'width': '2px',
                'height': '16px',
                'top': '2px',
                'left': '9px',
                'transform': 'rotate(-45deg)',
            },
            toString: renderCss,
        };
        let bodyCss = {
            styles: {
                'overflow': 'hidden',
            },
            toString: renderCss,
        };
        let cleantalkModalStyle = document.createElement( 'style' );
        cleantalkModalStyle.setAttribute( 'id', 'cleantalk-modal-styles' );
        cleantalkModalStyle.innerHTML = 'body.cleantalk-modal-opened{' + bodyCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-overlay{' + overlayCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close{' + closeCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close:before{' + closeCssBefore + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close:after{' + closeCssAfter + '}';
        document.body.append( cleantalkModalStyle );
        /* Cleantalk Modal CSS end */

        let overlay = document.createElement( 'div' );
        overlay.setAttribute( 'id', 'cleantalk-modal-overlay' );
        document.body.append( overlay );

        document.body.classList.add( 'cleantalk-modal-opened' );

        let inner = document.createElement( 'div' );
        inner.setAttribute( 'id', 'cleantalk-modal-inner' );
        inner.setAttribute( 'style', innerCss );
        overlay.append( inner );

        let close = document.createElement( 'div' );
        close.setAttribute( 'id', 'cleantalk-modal-close' );
        inner.append( close );

        let content = document.createElement( 'div' );
        if ( this.loaded ) {
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            const serviceContentRegex = /.*\/inc/g;
            if (serviceContentRegex.test(this.loaded) || this.ignoreURLConvert) {
                content.innerHTML = this.loaded;
            } else {
                content.innerHTML = this.loaded.replace(urlRegex, '<a href="$1" target="_blank">$1</a>');
            }
        } else {
            content.innerHTML = 'Loading...';
            // @ToDo Here is hardcoded parameter. Have to get this from a 'data-' attribute.
            this.load( 'get_options_template' );
        }
        content.setAttribute( 'id', 'cleantalk-modal-content' );
        inner.append( content );

        this.opened = true;
    },

    close: function() {
        document.body.classList.remove( 'cleantalk-modal-opened' );
        document.getElementById( 'cleantalk-modal-overlay' ).remove();
        document.getElementById( 'cleantalk-modal-styles' ).remove();
        document.dispatchEvent(
            new CustomEvent( 'cleantalkModalClosed', {
                bubbles: true,
            } ),
        );
    },

};

/* Cleantalk Modal helpers */
document.addEventListener('click', function( e ) {
    if ( e.target && (e.target.id === 'cleantalk-modal-overlay' || e.target.id === 'cleantalk-modal-close') ) {
        cleantalkModal.close();
    }
});
document.addEventListener('cleantalkModalContentLoaded', function( e ) {
    if ( cleantalkModal.opened && cleantalkModal.loaded ) {
        document.getElementById( 'cleantalk-modal-content' ).innerHTML = cleantalkModal.loaded;
    }
});

document.addEventListener('DOMContentLoaded', function() {
    let ctTrpLocalize = undefined;
    let ctTrpIsAdminCommentsList = false;

    if ( typeof ctPublic !== 'undefined' || typeof ctTrpAdminLocalize !== 'undefined' ) {
        if ( typeof ctPublic !== 'undefined' && ctPublic.theRealPerson ) {
            ctTrpLocalize = ctPublic.theRealPerson;
        }
        if (
            typeof ctTrpLocalize === 'undefined' &&
            typeof ctTrpAdminLocalize !== 'undefined' &&
            ctTrpAdminLocalize.theRealPerson
        ) {
            ctTrpLocalize = ctTrpAdminLocalize.theRealPerson;
            ctTrpIsAdminCommentsList = true;
        }
    }

    if ( ! ctTrpLocalize ) {
        return;
    }

    // Selectors. Try to handle the WIDE range of themes.
    let themesCommentsSelector = '.apbct-trp *[class*="comment-author"]';
    if ( document.querySelector('.apbct-trp .comment-author .comment-author-link') ) {
        // For Spacious theme
        themesCommentsSelector = '.apbct-trp *[class*="comment-author-link"]';
    }
    let woocommerceReviewsSelector = '.apbct-trp *[class*="review__author"]';
    let adminCommentsListSelector = '.apbct-trp td[class*="column-author"] > strong';
    const trpComments = document.querySelectorAll(
        themesCommentsSelector + ',' +
        woocommerceReviewsSelector + ',' +
        adminCommentsListSelector);

    if ( trpComments.length === 0 ) {
        return;
    }

    trpComments.forEach(( element, index ) => {
        // Exceptions for items that are included in the selection
        if (
            typeof pagenow == 'undefined' &&
            element.parentElement.className.indexOf('group') < 0 &&
            element.tagName != 'DIV'
        ) {
            return;
        }

        let trpLayout = document.createElement('div');
        trpLayout.setAttribute('class', 'apbct-real-user-badge');

        let trpImage = document.createElement('img');
        trpImage.setAttribute('src', ctTrpLocalize.imgPersonUrl);
        trpImage.setAttribute('class', 'apbct-real-user-popup-img');

        let trpDescription = document.createElement('div');
        trpDescription.setAttribute('class', 'apbct-real-user-popup');

        let trpDescriptionHeading = document.createElement('p');
        trpDescriptionHeading.setAttribute('class', 'apbct-real-user-popup-header');
        trpDescriptionHeading.append(ctTrpLocalize.phrases.trpHeading);

        let trpDescriptionContent = document.createElement('div');
        trpDescriptionContent.setAttribute('class', 'apbct-real-user-popup-content_row');

        let trpDescriptionContentSpan = document.createElement('span');
        trpDescriptionContentSpan.append(ctTrpLocalize.phrases.trpContent1 + ' ');
        trpDescriptionContentSpan.append(ctTrpLocalize.phrases.trpContent2);

        if ( ctTrpIsAdminCommentsList ) {
            let learnMoreLink = document.createElement('a');
            learnMoreLink.setAttribute('href', ctTrpLocalize.trpContentLink);
            learnMoreLink.setAttribute('target', '_blank');
            learnMoreLink.text = ctTrpLocalize.phrases.trpContentLearnMore;
            trpDescriptionContentSpan.append(' '); // Need one space
            trpDescriptionContentSpan.append(learnMoreLink);
        }

        trpDescriptionContent.append(trpDescriptionContentSpan);
        trpDescription.append(trpDescriptionHeading, trpDescriptionContent);
        trpLayout.append(trpImage);
        element.append(trpLayout);
        element.append(trpDescription);
    });

    const badges = document.querySelectorAll('.apbct-real-user-badge');

    badges.forEach((badge) => {
        let hideTimeout = undefined;

        this.body.addEventListener('click', function(e) {
            if (
                e.target.className.indexOf('apbct-real-user') == -1 &&
                e.target.parentElement.className.indexOf('apbct-real-user') == -1
            ) {
                closeAllPopupTRP();
            }
        });

        badge.addEventListener('click', function() {
            const popup = this.nextElementSibling;
            if (popup && popup.classList.contains('apbct-real-user-popup')) {
                popup.classList.toggle('visible');
            }
        });

        badge.addEventListener('mouseenter', function() {
            closeAllPopupTRP();
            const popup = this.nextElementSibling;
            if (popup && popup.classList.contains('apbct-real-user-popup')) {
                popup.classList.add('visible');
            }
        });

        badge.addEventListener('mouseleave', function() {
            hideTimeout = setTimeout(() => {
                const popup = this.nextElementSibling;
                if (popup && popup.classList.contains('apbct-real-user-popup')) {
                    popup.classList.remove('visible');
                }
            }, 1000);
        });

        const popup = badge.nextElementSibling;
        popup.addEventListener('mouseenter', function() {
            clearTimeout(hideTimeout);
            popup.classList.add('visible');
        });

        popup.addEventListener('mouseleave', function() {
            hideTimeout = setTimeout(() => {
                if (popup.classList.contains('apbct-real-user-popup')) {
                    popup.classList.remove('visible');
                }
            }, 1000);
        });

        // For mobile devices
        badge.addEventListener('touchend', function() {
            hideTimeout = setTimeout(() => {
                const popup = this.nextElementSibling;
                const selection = window.getSelection();
                // Check if no text is selected
                if (popup && selection && popup.classList.contains('apbct-real-user-popup') &&
                    selection.toString().length === 0
                ) {
                    popup.classList.remove('visible');
                } else {
                    clearTimeout(hideTimeout);
                    document.addEventListener('selectionchange', function onSelectionChange() {
                        const selection = window.getSelection();
                        if (selection && selection.toString().length === 0) {
                            // Restart the hide timeout when selection is cleared
                            hideTimeout = setTimeout(() => {
                                const popup = badge.nextElementSibling;
                                if (popup && popup.classList.contains('apbct-real-user-popup')) {
                                    popup.classList.remove('visible');
                                }
                            }, 3000);
                            document.removeEventListener('selectionchange', onSelectionChange);
                        }
                    });
                }
            }, 3000);
        });
    });
});

/**
 * Closing all TRP popup
 */
function closeAllPopupTRP() {
    let allDisplayPopup = document.querySelectorAll('.apbct-real-user-popup.visible');
    if (allDisplayPopup.length > 0) {
        allDisplayPopup.forEach((element) => {
            element.classList.remove('visible');
        });
    }
}

/**
 * Check form as internal.
 * @param {int} currForm Current form.
 */
function ctCheckInternal(currForm) {
    //  Gathering data
    const ctData = {};
    const elems = currForm.elements;
    let key;

    for (key in elems) {
        if (elems[key].type !== 'submit' &&
            elems[key].value !== undefined &&
            elems[key].value !== '') {
            ctData[elems[key].name] = currForm.elements[key].value;
        }
    }
    ctData.action = 'ct_check_internal';

    //  AJAX Request
    apbct_public_sendAJAX(
        ctData,
        {
            url: ctPublicFunctions._ajax_url,
            callback: function(data) {
                if (data.success === true) {
                    currForm.origSubmit();
                } else {
                    alert(data.data);
                    return false;
                }
            },
        },
    );
}

document.addEventListener('DOMContentLoaded', function() {
    let ctCurrAction = '';
    let ctCurrForm = '';

    if ( ! +ctPublic.settings__forms__check_internal ) {
        return;
    }

    setTimeout(() => {
        for ( let i = 0; i < document.forms.length; i++ ) {
            if ( typeof(document.forms[i].action) == 'string' ) {
                ctCurrForm = document.forms[i];
                ctCurrAction = ctCurrForm.action;
                if (
                    ctCurrAction.indexOf('https?://') !== null && // The protocol is obligatory
                    ctCurrAction.match(ctPublic.blog_home + '.*?\.php') !== null && // Main check
                    ! ctCheckInternalIsExcludedForm(ctCurrAction) // Exclude WordPress native scripts from processing
                ) {
                    const formClone = ctCurrForm.cloneNode(true);
                    ctCurrForm.parentNode.replaceChild(formClone, ctCurrForm);

                    formClone.origSubmit = ctCurrForm.submit;
                    formClone.submit = null;

                    formClone.addEventListener('submit', function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                        event.stopImmediatePropagation();
                        ctCheckInternal(event.target);
                        return false;
                    });
                }
            }
        }
    }, 500);
});

/**
 * Check by action to exclude the form checking
 * @param {string} action
 * @return {boolean}
 */
function ctCheckInternalIsExcludedForm(action) {
    // An array contains forms action need to be excluded.
    const ctInternalScriptExclusions = [
        'wp-login.php', // WordPress login page
        'wp-comments-post.php', // WordPress Comments Form
    ];

    return ctInternalScriptExclusions.some((item) => {
        return action.match(new RegExp(ctPublic.blog_home + '.*' + item)) !== null;
    });
}
