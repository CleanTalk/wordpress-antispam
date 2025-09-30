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
            popupTextWaiting.innerText = ctAdminCommon.text__ee_wait_for_decoding;
        }
        popupTextWaiting.setAttribute('class', 'apbct-email-encoder-elements_center');

        // construct text second node
        // todo make translatable
        let popupTextDecoding = document.createElement('p');
        popupTextDecoding.id = 'apbct_email_ecoder__popup_text_node_second';
        if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_decoding_process) {
            popupTextDecoding.innerText = ctPublicFunctions.text__ee_decoding_process;
        } else {
            popupTextDecoding.innerText = ctAdminCommon.text__ee_decoding_process;
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
        if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_wait_for_decoding) {
            document.getElementById('apbct_popup_text').innerHTML = ctPublicFunctions.text__ee_wait_for_decoding;
        } else {
            document.getElementById('apbct_popup_text').innerHTML = ctAdminCommon.text__ee_wait_for_decoding;
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
        let data = {
            post_url: document.location.href,
            referrer: document.referrer,
            encodedEmails: '',
        };
        if (ctPublic.settings__data__bot_detector_enabled == 1) {
            data.event_token = apbctLocalStorage.get('bot_detector_event_token');
        } else {
            data.event_javascript_data = getJavascriptClientData();
        }

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
        encodedEmailNodes.appendChild(apbctTooltip);

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
                    selectableEmail.title = ctAdminCommon.text__ee_click_to_select;
                }
                // add email to the first node
                if (firstNode) {
                    if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_original_email) {
                        firstNode.innerHTML = ctPublicFunctions.text__ee_original_email +
                            '&nbsp;' + selectableEmail.outerHTML;
                    } else {
                        firstNode.innerHTML = ctAdminCommon.text__ee_original_email +
                            '&nbsp;' + selectableEmail.outerHTML;
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
                        button.innerText = ctAdminCommon.text__ee_got_it;
                    }
                    button.classList = 'apbct-email-encoder-got-it-button';
                    button.addEventListener('click', function() {
                        document.body.classList.remove('apbct-popup-fade');
                        popup.setAttribute('style', 'display:none');
                        fillDecodedNodes(encodedEmailNodes, result);
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
                    ctShowDecodeComment(ctAdminCommon.text__ee_blocked + ': ' + result.data[0].comment);
                }
            } else {
                resetEncodedNodes();
                if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_cannot_connect) {
                    ctShowDecodeComment(ctPublicFunctions.text__ee_cannot_connect + ': ' + result.apbct.comment);
                } else {
                    ctShowDecodeComment(ctAdminCommon.text__ee_cannot_connect + ': ' + result.data[0].comment);
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
            comment = ctAdminCommon.text__ee_cannot_decode;
        }
    }

    let popup = document.getElementById('apbct_popup');
    let popupText = document.getElementById('apbct_popup_text');
    if (popup !== null) {
        document.body.classList.remove('apbct-popup-fade');
        if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_email_decoder) {
            popupText.innerText = ctPublicFunctions.text__ee_email_decoder + ': ' + comment;
        } else {
            popupText.innerText = ctAdminCommon.text__ee_email_decoder + ': ' + comment;
        }
        setTimeout(function() {
            popup.setAttribute('style', 'display:none');
        }, 3000);
    }
}

/**
 * Run filling for every node with decoding result.
 * @param {mixed} encodedNodes
 * @param {mixed} decodingResult
 */
function fillDecodedNodes(encodedNodes, decodingResult) {
    if (encodedNodes.length > 0) {
        for (let i = 0; i < encodedNodes.length; i++) {
            // chek what is what
            let currentResultData;
            decodingResult.data.forEach((row) => {
                if (row.encoded_email === encodedNodes[i].dataset.originalString) {
                    currentResultData = row;
                }
            });
            // quit case on cloud block
            if (currentResultData.is_allowed === false) {
                return;
            }
            // handler for mailto
            if (
                typeof encodedNodes[i].href !== 'undefined' &&
                (
                    encodedNodes[i].href.indexOf('mailto:') === 0 ||
                    encodedNodes[i].href.indexOf('tel:') === 0
                )
            ) {
                let linkTypePrefix;
                if (encodedNodes[i].href.indexOf('mailto:') === 0) {
                    linkTypePrefix = 'mailto:';
                } else if (encodedNodes[i].href.indexOf('tel:') === 0) {
                    linkTypePrefix = 'tel:';
                } else {
                    continue;
                }
                let encodedEmail = encodedNodes[i].href.replace(linkTypePrefix, '');
                let baseElementContent = encodedNodes[i].innerHTML;
                encodedNodes[i].innerHTML = baseElementContent.replace(
                    encodedEmail,
                    currentResultData.decoded_email,
                );
                encodedNodes[i].href = linkTypePrefix + currentResultData.decoded_email;

                encodedNodes[i].querySelectorAll('span.apbct-email-encoder').forEach((el) => {
                    let encodedEmailTextInsideMailto = '';
                    decodingResult.data.forEach((row) => {
                        if (row.encoded_email === el.dataset.originalString) {
                            encodedEmailTextInsideMailto = row.decoded_email;
                        }
                    });
                    el.innerHTML = encodedEmailTextInsideMailto;
                });
            } else {
                encodedNodes[i].classList.add('no-blur');
                // fill the nodes
                setTimeout(() => {
                    ctProcessDecodedDataResult(currentResultData, encodedNodes[i]);
                }, 2000);
            }
            // remove listeners
            encodedNodes[i].removeEventListener('click', ctFillDecodedEmailHandler);
        }
    } else {
        let currentResultData = decodingResult.data[0];
        encodedNodes.classList.add('no-blur');
        // fill the nodes
        setTimeout(() => {
            ctProcessDecodedDataResult(currentResultData, encodedNodes);
        }, 2000);
        encodedNodes.removeEventListener('click', ctFillDecodedEmailHandler);
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
    target.innerHTML = target.innerHTML.replace(/.+?(<div class=["']apbct-tooltip["'].+?<\/div>)/, email + '$1');
}

// Listen clicks on encoded emails
document.addEventListener('DOMContentLoaded', function() {
    let encodedEmailNodes = document.querySelectorAll('[data-original-string]');
    if (typeof ctPublic !== 'undefined') {
        ctPublic.encodedEmailNodes = encodedEmailNodes;
    }
    if (encodedEmailNodes.length) {
        for (let i = 0; i < encodedEmailNodes.length; ++i) {
            encodedEmailNodes[i].addEventListener('click', ctFillDecodedEmailHandler);
        }
    }
});

/* Cleantalk Modal object */
var cleantalkModal = cleantalkModal || { // eslint-disable-line no-var

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
 * Set init params
 */
// eslint-disable-next-line no-unused-vars,require-jsdoc
function initParams() {
    const ctDate = new Date();
    const headless = navigator.webdriver;
    const screenInfo = (
        typeof ApbctGatheringData !== 'undefined' &&
        typeof ApbctGatheringData.prototype.getScreenInfo === 'function'
    ) ? new ApbctGatheringData().getScreenInfo() : '';
    const initCookies = [
        ['ct_ps_timestamp', Math.floor(new Date().getTime() / 1000)],
        ['ct_fkp_timestamp', '0'],
        ['ct_pointer_data', '0'],
        ['ct_timezone', ctDate.getTimezoneOffset()/60*(-1)],
        ['ct_screen_info', screenInfo],
        ['apbct_headless', headless],
    ];

    apbctLocalStorage.set('ct_ps_timestamp', Math.floor(new Date().getTime() / 1000));
    apbctLocalStorage.set('ct_fkp_timestamp', '0');
    apbctLocalStorage.set('ct_pointer_data', '0');
    apbctLocalStorage.set('ct_timezone', ctDate.getTimezoneOffset()/60*(-1));
    apbctLocalStorage.set('ct_screen_info', screenInfo);
    apbctLocalStorage.set('apbct_headless', headless);

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

    if (+ctPublic.pixel__setting && +ctPublic.pixel__setting !== 3) {
        if (typeof ctIsDrawPixel === 'function' && ctIsDrawPixel()) {
            if (typeof ctGetPixelUrl === 'function') ctGetPixelUrl();
        } else {
            initCookies.push(['apbct_pixel_url', ctPublic.pixel__url]);
        }
    }

    if ( +ctPublic.data__email_check_before_post) {
        initCookies.push(['ct_checked_emails', '0']);
        if (typeof apbct === 'function') apbct('input[type = "email"], #email').on('blur', checkEmail);
    }

    if ( +ctPublic.data__email_check_exist_post) {
        initCookies.push(['ct_checked_emails_exist', '0']);
        if (typeof apbct === 'function') {
            apbct('.comment-form input[name = "email"], input#email').on('blur', checkEmailExist);
            apbct('.frm-fluent-form input[name = "email"], input#email').on('blur', checkEmailExist);
            apbct('#registerform input[name = "user_email"]').on('blur', checkEmailExist);
        }
    }

    if (apbctLocalStorage.isSet('ct_checkjs')) {
        initCookies.push(['ct_checkjs', apbctLocalStorage.get('ct_checkjs')]);
    } else {
        initCookies.push(['ct_checkjs', 0]);
    }

    ctSetCookie(initCookies);
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
            if (!+ctPublic.settings__data__bot_detector_enabled) {
                ctNoCookieAttachHiddenFieldsToForms();
            }
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

// eslint-disable-next-line require-jsdoc
function ctSetAlternativeCookie(cookies, params) {
    if (typeof (getJavascriptClientData) === 'function' ) {
        // reprocess already gained cookies data
        if (Array.isArray(cookies)) {
            cookies = getJavascriptClientData(cookies);
        }
    } else if (!+ctPublic.settings__data__bot_detector_enabled) {
        console.log('APBCT ERROR: getJavascriptClientData() is not loaded');
    }

    // if cookies is array, convert it to object
    if (Array.isArray(cookies) && cookies[0] && cookies[0][0] === 'apbct_bot_detector_exist') {
        cookies = {apbct_bot_detector_exist: cookies[0][1]};
    }
    // Only try to parse if cookies is a string (JSON)
    if (typeof cookies === 'string') {
        try {
            cookies = JSON.parse(cookies);
        } catch (e) {
            console.log('APBCT ERROR: JSON parse error:' + e);
            return;
        }
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

// eslint-disable-next-line require-jsdoc,camelcase,no-unused-vars
function apbct_attach_event_handler(elem, event, callback) {
    if (typeof window.addEventListener === 'function') elem.addEventListener(event, callback);
    else elem.attachEvent(event, callback);
}

// eslint-disable-next-line require-jsdoc,camelcase,no-unused-vars
function apbct_remove_event_handler(elem, event, callback) {
    if (typeof window.removeEventListener === 'function') elem.removeEventListener(event, callback);
    else elem.detachEvent(event, callback);
}

/**
 * Recursively decode JSON-encoded properties
 *
 * @param {mixed} object
 * @return {*}
 */
function removeDoubleJsonEncoding(object) { // eslint-disable-line no-unused-vars
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

/**
 * @return {boolean|*}
 */
function ctGetPageForms() { // eslint-disable-line no-unused-vars
    let forms = document.forms;
    if (forms) {
        return forms;
    }
    return false;
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
 * @return {string}
 */
function getNoCookieData() { // eslint-disable-line no-unused-vars
    let noCookieDataLocal = apbctLocalStorage.getCleanTalkData();
    let noCookieDataSession = apbctSessionStorage.getCleanTalkData();
    let noCookieData = {...noCookieDataLocal, ...noCookieDataSession};
    noCookieData = JSON.stringify(noCookieData);

    return '_ct_no_cookie_data_' + btoa(noCookieData);
}


/**
 * Retrieves the clentalk "cookie" data from starages.
 * Contains {...noCookieDataLocal, ...noCookieDataSession, ...noCookieDataTypo, ...noCookieDataFromUserActivity}.
 * @return {string}
 */
function getCleanTalkStorageDataArray() { // eslint-disable-line no-unused-vars
    let noCookieDataLocal = apbctLocalStorage.getCleanTalkData();
    let noCookieDataSession = apbctSessionStorage.getCleanTalkData();

    let noCookieDataTypo = {typo: []};
    if (document.ctTypoData && document.ctTypoData.data) {
        noCookieDataTypo = {typo: document.ctTypoData.data};
    }

    let noCookieDataFromUserActivity = {collecting_user_activity_data: []};

    if (document.ctCollectingUserActivityData) {
        let collectingUserActivityData = JSON.parse(JSON.stringify(document.ctCollectingUserActivityData));
        noCookieDataFromUserActivity = {collecting_user_activity_data: collectingUserActivityData};
    }

    return {...noCookieDataLocal, ...noCookieDataSession, ...noCookieDataTypo, ...noCookieDataFromUserActivity};
}

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
     * Send bot detector event token to alt cookies on problem forms
     * @return {void}
     */
    setEventTokenToAltCookies() {
        if (typeof ctPublic.force_alt_cookies !== 'undefined' && ctPublic.force_alt_cookies) {
            tokenCheckerIntervalId = setInterval( function() {
                let eventToken = apbctLocalStorage.get('bot_detector_event_token');
                if (eventToken) {
                    ctSetAlternativeCookie(
                        JSON.stringify({'ct_bot_detector_event_token': eventToken}),
                        {forceAltCookies: true},
                    );
                    clearInterval(tokenCheckerIntervalId);
                }
            }, 1000);
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
        let ninjaFormsSign = document.querySelectorAll('#tmpl-nf-layout').length > 0;
        let elementorUltimateAddonsRegister = document.querySelectorAll('.uael-registration-form-wrapper').length > 0;
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
            ninjaFormsSign ||
            jetpackCommentsForm ||
            elementorUltimateAddonsRegister ||
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
        if (
            document.querySelector('div.wfu_container') !== null ||
            document.querySelector('#newAppointmentForm') !== null ||
            document.querySelector('.booked-calendar-shortcode-wrap') !== null ||
            (
                // Back In Stock Notifier for WooCommerce | WooCommerce Waitlist Pro
                document.body.classList.contains('single-product') &&
                cwginstock !== undefined
            )
        ) {
            const originalSend = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.send = function(body) {
                if (body && typeof body === 'string' &&
                    (
                        body.indexOf('action=wfu_ajax_action_ask_server') !== -1 ||
                        body.indexOf('action=booked_add_appt') !== -1 ||
                        body.indexOf('action=cwginstock_product_subscribe') !== -1
                    )
                ) {
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

                    return originalSend.apply(this, [body]);
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
            if (document.forms.length > 0 &&
                Array.from(document.forms).map((form) => form.classList.contains('metform-form-content')).length > 0
            ) {
                window.fetch = function(...args) {
                    if (args &&
                        args[0] &&
                        typeof args[0].includes === 'function' &&
                        (args[0].includes('/wp-json/metform/') ||
                        (ctPublicFunctions._rest_url && (() => {
                            try {
                                return args[0].includes(new URL(ctPublicFunctions._rest_url).pathname + 'metform/');
                            } catch (e) {
                                return false;
                            }
                        })()))
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
                        if (settings.data.indexOf('action=wwlc_create_user') !== -1) {
                            sourceSign = 'action=wwlc_create_user';
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
                        if (+ctPublic.settings__data__bot_detector_enabled) {
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
                if (+ctPublic.settings__data__bot_detector_enabled) {
                    options.data.requests[0].data.event_token = localStorage.getItem('bot_detector_event_token');
                } else {
                    if (ctPublic.data__cookies_type === 'none') {
                        options.data.requests[0].data.ct_no_cookie_hidden_field = getNoCookieData();
                    }
                }
            }

            // checkout
            if (options.path === '/wc/store/v1/checkout') {
                if (+ctPublic.settings__data__bot_detector_enabled) {
                    options.data.event_token = localStorage.getItem('bot_detector_event_token');
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
        const isExclusion = (form) => {
            let className = form.getAttribute('class');
            if (typeof className !== 'string') {
                className = '';
            }

            return (
                // fibosearch integration
                form.querySelector('input.dgwt-wcas-search-input') ||
                // hero search skip
                form.getAttribute('id') === 'hero-search-form' ||
                // hb booking search skip
                className === 'hb-booking-search-form' ||
                // events calendar skip
                (className.indexOf('tribe-events') !== -1 && className.indexOf('search') !== -1)
            );
        };

        for (const _form of document.forms) {
            if (
                typeof ctPublic !== 'undefined' &&
                + ctPublic.settings__forms__search_test === 1 &&
                (
                    _form.getAttribute('id') === 'searchform' ||
                    (
                        _form.getAttribute('class') !== null &&
                        _form.getAttribute('class').indexOf('search-form') !== -1
                    ) ||
                    (_form.getAttribute('role') !== null && _form.getAttribute('role').indexOf('search') !== -1)
                ) &&
                !isExclusion(_form)
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
        new ApbctEventTokenTransport().setEventTokenToAltCookies();
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

/**
 * Run cron jobs
 */
new ApbctHandler().cronFormsHandler(2000);

let botDetectorLogLastUpdate = 0;
let botDetectorLogEventTypesCollected = [];

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



let ctCheckedEmails = {};
let ctCheckedEmailsExist = {};

/**
 * @param {mixed} e
 */
function checkEmail(e) { // eslint-disable-line no-unused-vars
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
function checkEmailExist(e) { // eslint-disable-line no-unused-vars
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

    ctEmailExistSetElementsPositions(inputEmail);

    window.addEventListener('resize', function(event) {
        ctEmailExistSetElementsPositions(inputEmail);
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
 * @param {mixed} inputEmail
 */
function ctEmailExistSetElementsPositions(inputEmail) {
    const envelopeWidth = 35;

    if (!inputEmail) {
        return;
    }

    const inputRect = inputEmail.getBoundingClientRect();
    const inputHeight = inputEmail.offsetHeight;
    const inputWidth = inputEmail.offsetWidth;

    const envelope = document.getElementById('apbct-check_email_exist-block');
    if (envelope) {
        envelope.style.cssText = `
            top: ${inputRect.top}px;
            left: ${inputRect.right - envelopeWidth - 10}px;
            height: ${inputHeight}px;
            width: ${envelopeWidth}px;
        `;
    }

    const hint = document.getElementById('apbct-check_email_exist-popup_description');
    if (hint) {
        hint.style.cssText = `
            width: ${inputWidth}px;
            left: ${inputRect.left}px;
        `;
    }
}

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
    let themesCommentsSelector = '.apbct-trp > .comment-body *[class*="comment-author"]';
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
