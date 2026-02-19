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
            let comment = 'unknown_error';
            if (
                result.hasOwnProperty('data') &&
                result.data.length > 0 &&
                typeof result.data[0] === 'object' &&
                typeof result.data[0].comment === 'string'
            ) {
                comment = result.data[0].comment;
            }
            if (result.success) {
                resetEncodedNodes();
                if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_blocked) {
                    ctShowDecodeComment(ctPublicFunctions.text__ee_blocked + ': ' + comment);
                } else {
                    ctShowDecodeComment(ctAdminCommon.text__ee_blocked + ': ' + comment);
                }
            } else {
                resetEncodedNodes();
                if (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.text__ee_cannot_connect) {
                    ctShowDecodeComment(ctPublicFunctions.text__ee_cannot_connect + ': ' + comment);
                } else {
                    ctShowDecodeComment(ctAdminCommon.text__ee_cannot_connect + ': ' + comment);
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
            const node = encodedEmailNodes[i];
            if (
                node.parentNode &&
                node.parentNode.tagName === 'A' &&
                node.parentNode.getAttribute('href')?.includes('mailto:') &&
                node.parentNode.hasAttribute('data-original-string')
            ) {
                // This node was skipped from listeners
                continue;
            }
            node.addEventListener('click', ctFillDecodedEmailHandler);
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

    /**
     * Open modal
     * @param {boolean|string} actionCallbackName
     */
    open: function(actionCallbackName = 'get_options_template') {
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
            if (actionCallbackName) {
                this.load( actionCallbackName );
            }
        }
        content.setAttribute( 'id', 'cleantalk-modal-content' );
        inner.append( content );

        this.opened = true;
    },

    confirm: function(header, text = '', filePath = '', callback, yesButtonText = 'Yes', noButtonText = 'No') {
        cleantalkModal.loading = false;
        let contentBlock = document.getElementById('cleantalk-modal-content');
        if (contentBlock) {
            contentBlock.innerHTML = '';

            const headerBlock = document.createElement('div');
            headerBlock.className = 'cleantalk-confirm-modal_header';
            headerBlock.textContent = header;
            contentBlock.append(headerBlock);

            // Create text block
            const textBlock = document.createElement('div');
            textBlock.className = 'cleantalk-confirm-modal_text-block';
            contentBlock.append(textBlock);

            if (filePath && filePath.length > 60) {
                filePath = '...' + filePath.slice(filePath.length - 60);
            }

            const textElem = document.createElement('div');
            textElem.className = 'cleantalk-confirm-modal_text';
            textElem.textContent = text;
            textBlock.append(textElem);

            // Create buttons block
            const buttonsBlock = document.createElement('div');
            buttonsBlock.className = 'cleantalk-confirm-modal_buttons-block';
            contentBlock.append(buttonsBlock);

            const yesButton = document.createElement('button');
            yesButton.className = 'cleantalk_link cleantalk_link-auto';
            yesButton.textContent = yesButtonText;
            yesButton.onclick = function() {
                callback(true);
                cleantalkModal.close();
            };
            buttonsBlock.append(yesButton);

            const noButton = document.createElement('button');
            noButton.className = 'cleantalk_link cleantalk_link-auto';
            noButton.textContent = noButtonText;
            noButton.onclick = function() {
                cleantalkModal.close();
            };
            buttonsBlock.append(noButton);
        }
        document.dispatchEvent(
            new CustomEvent( 'cleantalkModalContentLoaded', {
                bubbles: true,
            } ),
        );
    },

    close: function() {
        document.body.classList.remove( 'cleantalk-modal-opened' );
        const overlay = document.getElementById( 'cleantalk-modal-overlay' );
        const styles = document.getElementById( 'cleantalk-modal-styles' );
        overlay !== null && overlay.remove();
        styles !== null && styles.remove();
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
 * Callbacks for ShadowRoot integrations
 */
const ApbctShadowRootCallbacks = {
    /**
     * Mailchimp block callback - clears localStorage by mcforms mask
     * @param {object} result
     */
    mailchimpBlock: function(result) {
        try {
            for (let i = localStorage.length - 1; i >= 0; i--) {
                const key = localStorage.key(i);
                if (key && key.indexOf('mcforms') !== -1) {
                    localStorage.removeItem(key);
                }
            }
        } catch (e) {
            console.warn('Error clearing localStorage by mcforms mask:', e);
        }
    },

    // /**
    //  * Next integration block callback
    //  * @param {object} result
    //  */
    // nextIntegrationBlock: function(result) {
    //     // Custom logic
    // },
};
/**
 * Config for ShadowRoot integrations
 */
const ApbctShadowRootConfig = {
    'mailchimp': {
        selector: '.mcforms-wrapper',
        urlPattern: 'mcf-integrations-mcmktg.mlchmpcompprduse2.iks2.a.intuit.com/gateway/receive',
        externalForm: true,
        action: 'cleantalk_force_mailchimp_shadowroot_check',
        callbackAllow: false,
        callbackBlock: ApbctShadowRootCallbacks.mailchimpBlock,
    },
};
/**
 * Class for handling ShadowRoot forms
 */
class ApbctShadowRootProtection {
    constructor() {
        this.config = ApbctShadowRootConfig;
    }

    /**
     * Find matching config for URL
     * @param {string} url
     * @return {object|null} { formKey, config } or null
     */
    findMatchingConfig(url) {
        for (const [formKey, config] of Object.entries(this.config)) {
            // Shadowroot can send both external and internal requests
            // If the form is external, then we check whether the setting is enabled.
            if (
                (!config.externalForm || +ctPublic.settings__forms__check_external) && 
                document.querySelectorAll(config.selector).length > 0 &&
                url && url.includes(config.urlPattern)
            ) {
                return {formKey, config};
            }
        }
        return null;
    }

    /**
     * Check ShadowRoot form request via CleanTalk AJAX
     * @param {string} formKey
     * @param {object} config
     * @param {string} bodyText
     * @return {Promise<boolean>} true = block, false = allow
     */
    async checkRequest(formKey, config, bodyText) {
        return new Promise((resolve) => {
            let data = {
                action: config.action,
            };

            try {
                const bodyObj = JSON.parse(bodyText);
                for (const [key, value] of Object.entries(bodyObj)) {
                    data[key] = value;
                }
            } catch (e) {
                data.raw_body = bodyText;
            }

            if (+ctPublic.settings__data__bot_detector_enabled) {
                const eventToken = new ApbctHandler().toolGetEventToken();
                if (eventToken) {
                    data.ct_bot_detector_event_token = eventToken;
                }
            } else {
                data.ct_no_cookie_hidden_field = getNoCookieData();
            }

            apbct_public_sendAJAX(data, {
                async: true,
                callback: (result) => {
                    // Allowed
                    if (
                        (result.apbct === undefined && result.data === undefined) ||
                        (result.apbct !== undefined && !+result.apbct.blocked)
                    ) {
                        if (typeof config.callbackAllow === 'function') {
                            config.callbackAllow(result);
                        }
                        resolve(false);
                        return;
                    }

                    // Blocked
                    if (
                        (result.apbct !== undefined && +result.apbct.blocked) ||
                        (result.data !== undefined && result.data.message !== undefined)
                    ) {
                        new ApbctShowForbidden().parseBlockMessage(result);
                        if (typeof config.callbackBlock === 'function') {
                            config.callbackBlock(result);
                        }
                        resolve(true);
                        return;
                    }

                    resolve(false);
                },
                onErrorCallback: (error) => {
                    console.log('APBCT ShadowRoot check error:', error);
                    resolve(false);
                },
            });
        });
    }

    /**
     * Extract body text from fetch args
     * @param {array} args
     * @return {string}
     */
    extractBodyText(args) {
        let body = args[1] && args[1].body;
        let bodyText = '';

        if (body instanceof FormData) {
            let obj = {};
            for (let [key, value] of body.entries()) {
                obj[key] = value;
            }
            bodyText = JSON.stringify(obj);
        } else if (typeof body === 'string') {
            bodyText = body;
        }

        return bodyText;
    }

    /**
     * Process fetch request for ShadowRoot forms
     * @param {array} args - fetch arguments
     * @return {Promise<boolean|null>} true = block, false = allow, null = not matched
     */
    async processFetch(args) {
        const url = typeof args[0] === 'string' ? args[0] : (args[0]?.url || '');
        const match = this.findMatchingConfig(url);

        if (!match) {
            return null;
        }

        const bodyText = this.extractBodyText(args);
        return await this.checkRequest(match.formKey, match.config, bodyText);
    }
}
/**
 * Set init params
 */
// eslint-disable-next-line no-unused-vars,require-jsdoc
function initParams(gatheringLoaded) {
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
            apbct('form.wc-block-checkout__form input[type = "email"]').on('blur', checkEmailExist);
            apbct('form.checkout input[type = "email"]').on('blur', checkEmailExist);
            apbct('form.wpcf7-form input[type = "email"]')
                .on('blur', ctDebounceFuncExec(checkEmailExist, 300) );
            apbct('form.wpforms-form input[type = "email"]').on('blur', checkEmailExist);
            apbct('form[id^="gform_"] input[type = "email"]').on('blur', checkEmailExist);
            apbctIntegrateDynamicEmailCheck({
                formSelector: '.nf-form-content',
                emailSelector: 'input[type="email"], input[type="email"].ninja-forms-field',
                handler: checkEmailExist,
                debounce: 300,
            });
        }
    }

    if (apbctLocalStorage.isSet('ct_checkjs')) {
        initCookies.push(['ct_checkjs', apbctLocalStorage.get('ct_checkjs')]);
    } else {
        initCookies.push(['ct_checkjs', 0]);
    }

    if (gatheringLoaded) {
        initCookies.push(['ct_gathering_loaded', gatheringLoaded]);
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
 * Execute function with timeout, listening incoming args from context.
 * Could be useful if something needs to wait other actions and no there is no known event to bind for.
 * @param {function} func Function to call
 * @param {number} wait Seconds to delay
 * @return {(function(...[*]): void)|*}
 */
function ctDebounceFuncExec(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const context = this;
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            func.apply(context, args);
        }, wait);
    };
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
     * @param {bool} gatheringLoaded
     * @return {void}
     */
    attachHiddenFieldsToForms(gatheringLoaded) {
        if (typeof ctPublic.force_alt_cookies == 'undefined' ||
            (ctPublic.force_alt_cookies !== 'undefined' && !ctPublic.force_alt_cookies)
        ) {
            if (!+ctPublic.settings__data__bot_detector_enabled || gatheringLoaded) {
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
                if (!+ctPublic.settings__data__bot_detector_enabled && typeof ApbctGatheringData !== 'undefined') {
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
        ctPublic.force_alt_cookies = smartFormsSign ||
            jetpackCommentsForm ||
            userRegistrationProForm ||
            etPbDiviSubscriptionForm ||
            fluentBookingApp ||
            pafeFormsFormElementor ||
            bloomPopup ||
            otterForm;

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

                    if (!(
                        +ctPublic.settings__data__bot_detector_enabled &&
                        apbctLocalStorage.get('bot_detector_event_token')
                    )) {
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
                    if (!(
                        +ctPublic.settings__data__bot_detector_enabled &&
                        apbctLocalStorage.get('bot_detector_event_token')
                    )) {
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
     * Catch Iframe fetch request
     * @return {void}
     */
    catchIframeFetchRequest() {
        setTimeout(function() {
            try {
                // Give next gen iframe
                const foundIframe = Array.from(document.querySelectorAll('iframe')).find(
                    (iframe) => iframe.src?.includes('givewp-route'),
                );

                if (!foundIframe) {
                    return;
                }

                // Cross origin access check
                let contentWindow;
                try {
                    contentWindow = foundIframe.contentWindow;
                    if (!contentWindow || !contentWindow.fetch) {
                        return;
                    }
                } catch (securityError) {
                    // access denied
                    return;
                }

                // Save original iframe fetch
                const originalIframeFetch = contentWindow.fetch;

                // Intercept and add fields to body
                contentWindow.fetch = async function(...args) {
                    try {
                        // is body and boddy has append func
                        if (args && args[1] && args[1].body) {
                            if (
                                args[1].body instanceof FormData || (typeof args[1].body.append === 'function')
                            ) {
                                if (
                                    +ctPublic.settings__data__bot_detector_enabled &&
                                    apbctLocalStorage.get('bot_detector_event_token')
                                ) {
                                    args[1].body.append(
                                        'ct_bot_detector_event_token',
                                        apbctLocalStorage.get('bot_detector_event_token'),
                                    );
                                } else {
                                    args[1].body.append('ct_no_cookie_hidden_field', getNoCookieData());
                                }
                            }
                        }
                    } catch (e) {
                        // do nothing due fields add error
                    }

                    // run origin fetch
                    return originalIframeFetch.apply(contentWindow, args);
                };
            } catch (error) {
                // do nothing on unexpected error
            }
        }, 1000);
    }

    /**
     * Catch fetch request
     * @return {void}
     */
    catchFetchRequest() {
        const shadowRootProtection = new ApbctShadowRootProtection();
        let preventOriginalFetch = false;

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
            };
            return result.key && result.value ? result : false;
        };

        /**
         *
         * @param {string} body Fetch request data body.
         * @param {object|bool} fieldPair Key value to inject.
         * @return {string} Modified body.
         */
        const attachFieldsToBody = function(body, fieldPair = false) {
            if (fieldPair) {
                if (body instanceof FormData || typeof body.append === 'function') {
                    body.append(fieldPair.key, fieldPair.value);
                } else {
                    let bodyObj = JSON.parse(body);
                    if (!bodyObj.hasOwnProperty(fieldPair.key)) {
                        bodyObj[fieldPair.key] = fieldPair.value;
                        body = JSON.stringify(bodyObj);
                    }
                }
            }
            return body;
        };

        window.fetch = async function(...args) {
            // Reset flag for each new request
            preventOriginalFetch = false;

            // if no data set provided - exit
            if (
                !args ||
                !args[0] ||
                !args[1] ||
                !args[1].body
            ) {
                return defaultFetch.apply(window, args);
            }

            // === ShadowRoot forms ===
            const shadowRootResult = await shadowRootProtection.processFetch(args);
            if (shadowRootResult === true) {
                // Return a "blank" response that never completes
                return new Promise(() => {});
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
                            return defaultFetch.apply(window, args);
                        }
                    })())
                )
            ) {
                try {
                    args[1].body = attachFieldsToBody(
                        args[1].body,
                        selectFieldsData(+ctPublic.settings__data__bot_detector_enabled),
                    );
                } catch (e) {
                    return defaultFetch.apply(window, args);
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
                        selectFieldsData(+ctPublic.settings__data__bot_detector_enabled),
                    );
                } catch (e) {
                    return defaultFetch.apply(window, args);
                }
            }

            // === WooCommerce add to cart ===
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
                    if (
                        +ctPublic.settings__forms__wc_add_to_cart
                    ) {
                        args[1].body = attachFieldsToBody(
                            args[1].body,
                            selectFieldsData(+ctPublic.settings__data__bot_detector_enabled),
                        );
                    }
                } catch (e) {
                    return defaultFetch.apply(window, args);
                }
            }

            // === Bitrix24 external form ===
            if (
                +ctPublic.settings__forms__check_external &&
                document.querySelectorAll('.b24-form').length > 0 &&
                args[0].includes('bitrix/services/main/ajax.php?action=crm.site.form.fill') &&
                args[1].body instanceof FormData
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
                        'attachVisibleFieldsData': false,
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
                            sourceSign.attachVisibleFieldsData = true;
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
                        if (settings.data.indexOf('action=wpr_form_builder_email') !== -1) {
                            sourceSign.found = 'action=wpr_form_builder_email';
                            sourceSign.keepUnwrapped = true;
                        }
                        if (
                            settings.data.indexOf('action=nf_ajax_submit') !== -1
                        ) {
                            sourceSign.found = 'action=nf_ajax_submit';
                            sourceSign.keepUnwrapped = true;
                            sourceSign.attachVisibleFieldsData = true;
                        }
                        if (
                            settings.data.indexOf('action=uael_register_user') !== -1 &&
                            ctPublic.data__cookies_type === 'none'
                        ) {
                            sourceSign.found = 'action=uael_register_user';
                            sourceSign.keepUnwrapped = true;
                        }
                        if (
                            settings.data.indexOf('action=SQBSubmitQuizAjax') !== -1
                        ) {
                            sourceSign.found = 'action=SQBSubmitQuizAjax';
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
                        let visibleFieldsString = '';
                        if (
                            +ctPublic.settings__data__bot_detector_enabled &&
                            apbctLocalStorage.get('bot_detector_event_token')
                        ) {
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

                        if (sourceSign.attachVisibleFieldsData) {
                            let visibleFieldsSearchResult = false;

                            const extractor = ApbctVisibleFieldsExtractor.createExtractor(sourceSign.found);
                            if (extractor) { // Check if extractor was created
                                visibleFieldsSearchResult = extractor.extract(settings.data);
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

                        settings.data = noCookieData + eventToken + visibleFieldsString + settings.data;
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
                if (
                    +ctPublic.settings__data__bot_detector_enabled &&
                    apbctLocalStorage.get('bot_detector_event_token')
                ) {
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
                if (
                    +ctPublic.settings__data__bot_detector_enabled &&
                    apbctLocalStorage.get('bot_detector_event_token')
                ) {
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
 * Base class for extracting visible fields from form plugins
 */
class ApbctVisibleFieldsExtractor {
    /**
     * Factory method to create appropriate extractor instance
     * @param {string} sourceSignAction - Action identifier
     * @return {ApbctVisibleFieldsExtractor|null}
     */
    static createExtractor(sourceSignAction) {
        switch (sourceSignAction) {
        case 'action=nf_ajax_submit':
            return new ApbctNinjaFormsVisibleFields();
        case 'action=mailpoet':
            return new ApbctMailpoetVisibleFields();
        default:
            return null;
        }
    }
    /**
     * Extracts visible fields string from form AJAX data
     * @param {string} ajaxData - AJAX form data
     * @return {string|false} Visible fields value or false if not found
     */
    extract(ajaxData) {
        if (!ajaxData || typeof ajaxData !== 'string') {
            return false;
        }

        try {
            ajaxData = decodeURIComponent(ajaxData);
        } catch (e) {
            return false;
        }

        const forms = document.querySelectorAll('form');
        const formIdFromAjax = this.getIdFromAjax(ajaxData);
        if (!formIdFromAjax) {
            return false;
        }

        for (let form of forms) {
            const container = this.findParentContainer(form);
            if (!container) {
                continue;
            }

            const formIdFromHtml = this.getIdFromHTML(container);
            if (formIdFromHtml !== formIdFromAjax) {
                continue;
            }

            const visibleFields = container.querySelector('input[id^=apbct_visible_fields_]');
            if (visibleFields?.value) {
                return visibleFields.value;
            }
        }

        return false;
    }

    /**
     * Override in child classes to define specific extraction patterns
     * @param {string} ajaxData - Decoded AJAX data string
     * @return {number|null} Form ID or null if not found
     */
    getIdFromAjax(ajaxData) {
        console.warn('getIdFromAjax must be implemented by child class');
        return null;
    }

    /**
     * Override in child classes to define container search logic
     * @param {HTMLElement} form - Form element to start search from
     * @return {HTMLElement|null} Container element or null
     */
    findParentContainer(form) {
        console.warn('findParentContainer must be implemented by child class');
        return null;
    }

    /**
     * Override in child classes to define ID extraction from HTML
     * @param {HTMLElement} container - Container element
     * @return {number|null} Form ID or null if not found
     */
    getIdFromHTML(container) {
        console.warn('getIdFromHTML must be implemented by child class');
        return null;
    }
}

/**
 * Ninja Forms specific implementation
 */
class ApbctNinjaFormsVisibleFields extends ApbctVisibleFieldsExtractor {
    /**
     * @inheritDoc
     */
    getIdFromAjax(ajaxData) {
        const regexes = [
            /"id"\s*:\s*"?(\d+)"/, // {"id":"2"} or {"id":2}
            /form_id\s*[:\s]*"?(\d+)"/,
            /nf-form-(\d+)/,
            /"id":(\d+)/, // Fallback for simple cases
        ];

        for (let regex of regexes) {
            const match = ajaxData.match(regex);
            if (match && match[1]) {
                return parseInt(match[1], 10);
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    findParentContainer(form) {
        let el = form;
        while (el && el !== document.body) {
            if (el.id && /^nf-form-\d+-cont$/.test(el.id)) {
                return el;
            }
            el = el.parentElement;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    getIdFromHTML(container) {
        const match = container.id.match(/^nf-form-(\d+)-cont$/);
        return match ? parseInt(match[1], 10) : null;
    }
}

/**
 * Mailpoet specific implementation
 */
class ApbctMailpoetVisibleFields extends ApbctVisibleFieldsExtractor {
    /**
     * @inheritDoc
     */
    getIdFromAjax(ajaxData) {
        const regexes = [
            /form_id\s*[:\s]*"?(\d+)"/,
            /data\[form_id\]=(\d+)/,
            /form_id=(\d+)/,
        ];

        for (let regex of regexes) {
            const match = ajaxData.match(regex);
            if (match && match[1]) {
                return parseInt(match[1], 10);
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    findParentContainer(form) {
        // Mailpoet uses the form itself as container
        return form;
    }

    /**
     * @inheritDoc
     */
    getIdFromHTML(container) {
        if (!container.action) {
            return null;
        }

        const formMatch = container.action.match(/mailpoet_subscription_form/);
        if (!formMatch) {
            return null;
        }

        const hiddenFieldWithID = container.querySelector('input[type="hidden"][name="data[form_id]"]');
        if (!hiddenFieldWithID || !hiddenFieldWithID.value) {
            return null;
        }

        return parseInt(hiddenFieldWithID.value, 10);
    }
}

/**
 * Additional function to calculate realpath of cleantalk's scripts
 * @return {*|null}
 */
function getApbctBasePath() {
    // Find apbct-public-bundle in scripts names
    const scripts = document.getElementsByTagName('script');

    for (let script of scripts) {
        if (script.src && script.src.includes('apbct-public-bundle')) {
            // Get path from `src` js
            const match = script.src.match(/^(.*\/js\/)/);
            if (match && match[1]) {
                return match[1]; // Path exists, return this
            }
        }
    }

    return null; // cleantalk's scripts not found :(
}

/**
 * Load any script into the DOM (i.e. `import()`)
 * @param {string} scriptAbsolutePath
 * @return {Promise<*|boolean>}
 */
async function apbctImportScript(scriptAbsolutePath) {
    // Check it this scripti already is in DOM
    const normalizedPath = scriptAbsolutePath.replace(/\/$/, ''); // Replace ending slashes
    const scripts = document.querySelectorAll('script[src]');
    for (const script of scripts) {
        const scriptSrc = script.src.replace(/\/$/, '');
        if (scriptSrc === normalizedPath) {
            // Script already loaded, skipping
            return true;
        }
    }
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');

        script.src = scriptAbsolutePath;
        script.async = true;

        script.onload = function() {
            // Gathering data script loaded successfully
            resolve(true);
        };

        script.onerror = function() {
            // Failed to load Gathering data script from `scriptAbsolutePath`
            reject(new Error('Script loading failed: ' + scriptAbsolutePath));
        };

        document.head.appendChild(script);
    }).catch((error) => {
        // Gathering data script loading failed, continuing without it
        return false;
    });
}

/**
 * Ready function
 */
// eslint-disable-next-line camelcase,require-jsdoc
async function apbct_ready() {
    new ApbctShowForbidden().prepareBlockForAjaxForms();

    // Try to get gathering if no worked bot-detector
    let gatheringLoaded = false;

    if (
        apbctLocalStorage.get('apbct_existing_visitor') && // Not for the first hit
        +ctPublic.settings__data__bot_detector_enabled && // If Bot-Detector is active
        !apbctLocalStorage.get('bot_detector_event_token') && // and no `event_token` generated
        typeof ApbctGatheringData === 'undefined' // and no `gathering` loaded yet
    ) {
        const basePath = getApbctBasePath();
        if ( ! basePath ) {
            // We are here because NO any cleantalk bundle script are in frontend: Todo nothing
        } else {
            const gatheringScriptName = 'public-2-gathering-data.min.js';
            const gatheringFullPath = basePath + gatheringScriptName;
            gatheringLoaded = await apbctImportScript(gatheringFullPath);
        }
    }

    const handler = new ApbctHandler();
    handler.detectForcedAltCookiesForms();

    // Gathering data when bot detector is disabled
    if (
        ( ! +ctPublic.settings__data__bot_detector_enabled || gatheringLoaded ) &&
        typeof ApbctGatheringData !== 'undefined'
    ) {
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
            initParams(gatheringLoaded);
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

        // Attach data when bot detector is disabled or blocked
        if (!+ctPublic.settings__data__bot_detector_enabled || gatheringLoaded) {
            attachData.attachHiddenFieldsToForms(gatheringLoaded);
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
    handler.catchIframeFetchRequest();
    handler.catchJqueryAjax();
    handler.catchWCRestRequestAsMiddleware();

    if (+ctPublic.settings__data__bot_detector_enabled) {
        let botDetectorEventTokenStored = false;
        window.addEventListener('botDetectorEventTokenUpdated', (event) => {
            const botDetectorEventToken = event.detail?.eventToken;
            if (botDetectorEventToken && !botDetectorEventTokenStored) {
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

    apbctLocalStorage.set('apbct_existing_visitor', 1);
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

/**
 * Class for gathering data
 */
class ApbctGatheringData { // eslint-disable-line no-unused-vars
    /**
     * Set session ID
     * @return {void}
     */
    setSessionId() {
        if (!apbctSessionStorage.isSet('apbct_session_id')) {
            const sessionID = Math.random().toString(36).replace(/[^a-z]+/g, '').substr(2, 10);
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
    }

    /**
     * Set three statements to the sessions storage: apbct_session_current_page, apbct_prev_referer.
     * @return {void}
     */
    writeReferrersToSessionStorage() {
        const sessionCurrentPage = apbctSessionStorage.get('apbct_session_current_page');
        // set session apbct_referer
        if (sessionCurrentPage!== false && document.location.href !== sessionCurrentPage) {
            apbctSessionStorage.set('apbct_prev_referer', sessionCurrentPage, false);
        }
        // set session current page to know referrer
        apbctSessionStorage.set('apbct_session_current_page', document.location.href, false);
    }

    /**
     * Set cookies type.
     * If it's not set or not equal to ctPublic.data__cookies_type, clear ct_mouse_moved and ct_has_scrolled values.
     * @return {void}
     */
    setCookiesType() {
        const cookiesType = apbctLocalStorage.get('ct_cookies_type');
        if ( ! cookiesType || cookiesType !== ctPublic.data__cookies_type ) {
            apbctLocalStorage.set('ct_cookies_type', ctPublic.data__cookies_type);
            apbctLocalStorage.delete('ct_mouse_moved');
            apbctLocalStorage.delete('ct_has_scrolled');
        }
    }

    /**
     * Start fields listening
     * @return {void}
     */
    startFieldsListening() {
        if (ctPublic.data__cookies_type !== 'alternative') {
            this.startFieldsListening();
            // 2nd try to add listeners for delayed appears forms
            setTimeout(this.startFieldsListening, 1000);
        }
    }

    /**
     * Listen autocomplete
     * @return {void}
     */
    listenAutocomplete() {
        window.addEventListener('animationstart', this.apbctOnAnimationStart, true);
        window.addEventListener('input', this.apbctOnInput, true);
    }

    /**
     * Gathering typo data
     * @return {void}
     */
    gatheringTypoData() {
        document.ctTypoData = new CTTypoData();
        document.ctTypoData.gatheringFields();
        document.ctTypoData.setListeners();
    }

    /**
     * Gathering mouse data
     * @return {void}
     */
    gatheringMouseData() {
        new ApbctCollectingUserMouseActivity();
    }

    /**
     * Get screen info
     * @return {string}
     */
    getScreenInfo() {
        // Batch all layout-triggering property reads to avoid forced synchronous layouts
        const docEl = document.documentElement;
        const body = document.body;

        // Read all layout properties in one batch
        const layoutData = {
            scrollWidth: docEl.scrollWidth,
            bodyScrollHeight: body.scrollHeight,
            docScrollHeight: docEl.scrollHeight,
            bodyOffsetHeight: body.offsetHeight,
            docOffsetHeight: docEl.offsetHeight,
            bodyClientHeight: body.clientHeight,
            docClientHeight: docEl.clientHeight,
            docClientWidth: docEl.clientWidth,
        };

        return JSON.stringify({
            fullWidth: layoutData.scrollWidth,
            fullHeight: Math.max(
                layoutData.bodyScrollHeight, layoutData.docScrollHeight,
                layoutData.bodyOffsetHeight, layoutData.docOffsetHeight,
                layoutData.bodyClientHeight, layoutData.docClientHeight,
            ),
            visibleWidth: layoutData.docClientWidth,
            visibleHeight: layoutData.docClientHeight,
        });
    }

    /**
     * Restart listen fields to set ct_has_input_focused or ct_has_key_up
     * @return {void}
     */
    restartFieldsListening() {
        if (!apbctLocalStorage.isSet('ct_has_input_focused') && !apbctLocalStorage.isSet('ct_has_key_up')) {
            this.startFieldsListening();
        }
    }

    /**
     * Init listeners for keyup and focus events
     * @return {void}
     */
    startFieldsListening() {
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
}

/**
 * Class collecting user mouse activity data
 *
 */
// eslint-disable-next-line no-unused-vars, require-jsdoc
class ApbctCollectingUserMouseActivity {
    elementBody = document.querySelector('body');
    collectionForms = document.forms;
    /**
     * Constructor
     */
    constructor() {
        this.setListeners();
    }

    /**
     * Set listeners
     */
    setListeners() {
        this.elementBody.addEventListener('click', (event) => {
            this.checkElementInForms(event, 'addClicks');
        });

        this.elementBody.addEventListener('mouseup', (event) => {
            const selectedType = document.getSelection().type.toString();
            if (selectedType == 'Range') {
                this.addSelected();
            }
        });

        this.elementBody.addEventListener('mousemove', (event) => {
            this.checkElementInForms(event, 'trackMouseMovement');
        });
    }

    /**
     * Checking if there is an element in the form
     * @param {object} event
     * @param {string} addTarget
     */
    checkElementInForms(event, addTarget) {
        let resultCheck;
        for (let i = 0; i < this.collectionForms.length; i++) {
            if (
                event.target.outerHTML.length > 0 &&
                this.collectionForms[i].innerHTML.length > 0
            ) {
                resultCheck = this.collectionForms[i].innerHTML.indexOf(event.target.outerHTML);
            } else {
                resultCheck = -1;
            }
        }

        switch (addTarget) {
        case 'addClicks':
            if (resultCheck < 0) {
                this.addClicks();
            }
            break;
        case 'trackMouseMovement':
            if (resultCheck > -1) {
                this.trackMouseMovement();
            }
            break;
        default:
            break;
        }
    }

    /**
     * Add clicks
     */
    addClicks() {
        if (document.ctCollectingUserActivityData) {
            if (document.ctCollectingUserActivityData.clicks) {
                document.ctCollectingUserActivityData.clicks++;
            } else {
                document.ctCollectingUserActivityData.clicks = 1;
            }
            return;
        }

        document.ctCollectingUserActivityData = {clicks: 1};
    }

    /**
     * Add selected
     */
    addSelected() {
        if (document.ctCollectingUserActivityData) {
            if (document.ctCollectingUserActivityData.selected) {
                document.ctCollectingUserActivityData.selected++;
            } else {
                document.ctCollectingUserActivityData.selected = 1;
            }
            return;
        }

        document.ctCollectingUserActivityData = {selected: 1};
    }

    /**
     * Track mouse movement
     */
    trackMouseMovement() {
        if (!document.ctCollectingUserActivityData) {
            document.ctCollectingUserActivityData = {};
        }
        if (!document.ctCollectingUserActivityData.mouseMovementsInsideForm) {
            document.ctCollectingUserActivityData.mouseMovementsInsideForm = false;
        }

        document.ctCollectingUserActivityData.mouseMovementsInsideForm = true;
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
const ctTimeMs = new Date().getTime();
let ctMouseEventTimerFlag = true; // Reading interval flag
let ctMouseData = [];
let ctMouseDataCounter = 0;
let ctMouseReadInterval;
let ctMouseWriteDataInterval;


// Writing first key press timestamp
const ctFunctionFirstKey = function output(event) {
    let KeyTimestamp = Math.floor(new Date().getTime() / 1000);
    ctSetCookie('ct_fkp_timestamp', KeyTimestamp);
    ctKeyStopStopListening();
};

/**
 * Stop mouse observing function
 */
function ctMouseStopData() {
    apbct_remove_event_handler(document, 'mousemove', ctFunctionMouseMove);
    clearInterval(ctMouseReadInterval);
    clearInterval(ctMouseWriteDataInterval);
}


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
 * Stop key listening function
 */
function ctKeyStopStopListening() {
    apbct_remove_event_handler(document, 'mousedown', ctFunctionFirstKey);
    apbct_remove_event_handler(document, 'keydown', ctFunctionFirstKey);
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

    if (typeof (commonCookies) === 'object') {
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
 * @return {bool}
 */
function ctIsDrawPixel() {
    if (ctPublic.pixel__setting == '3' && ctPublic.settings__data__bot_detector_enabled == '1') {
        return false;
    }

    return +ctPublic.pixel__enabled ||
        (ctPublic.data__cookies_type === 'none' && document.querySelectorAll('img#apbct_pixel').length === 0) ||
        (ctPublic.data__cookies_type === 'alternative' && document.querySelectorAll('img#apbct_pixel').length === 0);
}

/**
 * @param {string} pixelUrl
 * @return {bool}
 */
function ctSetPixelImg(pixelUrl) {
    if (ctPublic.pixel__setting == '3' && ctPublic.settings__data__bot_detector_enabled == '1') {
        return false;
    }
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
 * @return {bool}
 */
function ctSetPixelImgFromLocalstorage(pixelUrl) {
    if (ctPublic.pixel__setting == '3' && ctPublic.settings__data__bot_detector_enabled == '1') {
        return false;
    }
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
 * @return {bool}
 */
// eslint-disable-next-line no-unused-vars, require-jsdoc
function ctGetPixelUrl() {
    if (ctPublic.pixel__setting == '3' && ctPublic.settings__data__bot_detector_enabled == '1') {
        return false;
    }

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

// eslint-disable-next-line no-unused-vars,require-jsdoc
function ctSetPixelUrlLocalstorage(ajaxPixelUrl) {
    // set pixel to the storage
    ctSetCookie('apbct_pixel_url', ajaxPixelUrl);
}

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
 * ctNoCookieAttachHiddenFieldsToForms
 */
function ctNoCookieAttachHiddenFieldsToForms() {
    if (ctPublic.data__cookies_type !== 'none') {
        return;
    }

    let forms = ctGetPageForms();

    if (forms) {
        for ( let i = 0; i < forms.length; i++ ) {
            if ( new ApbctHandler().checkHiddenFieldsExclusions(document.forms[i], 'no_cookie') ) {
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
                document.forms[i].append(new ApbctAttachData().constructNoCookieHiddenField());
            }
        }
    }
}

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
        inputEmail = parentElement.querySelector('[type*="email"]');
    }

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

    // draw on init
    ctEmailExistSetElementsPositions(inputEmail);

    // redraw on events
    ctListenRequiredRedrawing(inputEmail);

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
 * @param {HTMLInputElement} inputEmail
 */
function ctEmailExistSetElementsPositions(inputEmail) {
    if (!inputEmail instanceof HTMLInputElement) {
        return;
    }
    /** @type {DOMRect} */
    const inputRect = inputEmail.getBoundingClientRect();
    const inputHeight = inputEmail.offsetHeight;
    const inputWidth = inputEmail.offsetWidth;
    const envelopeWidth = inputHeight * 1.2;
    let backgroundSize;
    let fontSizeOrWidthAfterStyle = 0;
    let useAfterSize = false;
    try {
        let inputComputedStyle = window.getComputedStyle(inputEmail);

        const targetForAfter = inputEmail.parentElement ? inputEmail.parentElement : inputEmail;
        const afterStyle = window.getComputedStyle(targetForAfter, '::after');
        const afterContent = afterStyle.getPropertyValue('content');
        fontSizeOrWidthAfterStyle = afterStyle.getPropertyValue('font-size') || afterStyle.getPropertyValue('width');
        if (
            afterContent && afterContent !== 'none' &&
            parseFloat(fontSizeOrWidthAfterStyle) > 0
        ) {
            useAfterSize = true;
        }

        let inputTextSize = (
            typeof inputComputedStyle.fontSize === 'string' ?
                inputComputedStyle.fontSize :
                false
        );
        backgroundSize = inputTextSize ? inputTextSize : 'inherit';
    } catch (e) {
        backgroundSize = 'inherit';
    }
    const envelope = document.getElementById('apbct-check_email_exist-block');

    if (envelope) {
        let offsetAfterSize = 0;
        if (useAfterSize) {
            offsetAfterSize = parseFloat(fontSizeOrWidthAfterStyle);
        }
        envelope.style.cssText = `
            top: ${inputRect.top}px;
            left: ${(inputRect.right - envelopeWidth) - offsetAfterSize}px;
            height: ${inputHeight}px;
            width: ${envelopeWidth}px;
            background-size: ${backgroundSize};
            background-position: center;
        `;
    }

    const hint = document.getElementById('apbct-check_email_exist-popup_description');
    if (hint) {
        hint.style.width = `${inputWidth}px`;
        hint.style.left = `${inputRect.left}px`;
    }
}

/**
 * Define when the email badge positions should be redrawn.
 * @param {HTMLInputElement} inputEmail - Dom element to change
 */
function ctListenRequiredRedrawing(inputEmail) {
    window.addEventListener('resize', function(event) {
        ctEmailExistSetElementsPositions(inputEmail);
    });

    const formChangesToListen = [
        {
            'selector': 'form.wpcf7-form',
            'observerConfig': {
                childList: true,
                subtree: true,
            },
            'emailElement': inputEmail,
        },
    ];

    formChangesToListen.forEach((aForm) => {
        ctWatchFormChanges(
            aForm.selector,
            aForm.observerConfig,
            () => {
                ctEmailExistSetElementsPositions(aForm.emailElement);
            });
    });
}

/**
 * Watch for changes in form, using MutationObserver and its config.
 * @see MutationObserver
 * @param {string} formSelector
 * @param {object} observerConfig
 * @param {function} callback
 * @return {MutationObserver|false}
 */
function ctWatchFormChanges(formSelector = '', observerConfig = null, callback) {
    const form = document.querySelector(formSelector);

    if (!form || !observerConfig) {
        return false;
    }

    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList' || mutation.type === 'attributes') {
                callback(form, mutation);
            }
        });
    });

    observer.observe(form, observerConfig);
    // you can listen what is changed reading this
    return observer;
}

/**
 * Integrate dynamic email check to forms with dynamically added email inputs
 * @param {*} formSelector
 * @param {*} emailSelector
 * @param {*} handler
 * @param {number} debounce
 * @param {string} attribute
 */
function apbctIntegrateDynamicEmailCheck({ // eslint-disable-line no-unused-vars
    formSelector,
    emailSelector,
    handler,
    debounce = 300,
    attribute = 'data-apbct-email-exist',
}) {
    // Init for existing email inputs
    document.querySelectorAll(formSelector + ' ' + emailSelector)
        .forEach(function(input) {
            if (!input.hasAttribute(attribute)) {
                input.addEventListener('blur', ctDebounceFuncExec(handler, debounce));
                input.setAttribute(attribute, '1');
            }
        });

    // Global MutationObserver
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) {
                    // If it is an email input
                    if (node.matches && node.matches(formSelector + ' ' + emailSelector)) {
                        if (!node.hasAttribute(attribute)) {
                            node.addEventListener('blur', ctDebounceFuncExec(handler, debounce));
                            node.setAttribute(attribute, '1');
                        }
                    }
                    // If there are email inputs inside the added node
                    node.querySelectorAll &&
                    node.querySelectorAll(emailSelector).forEach(function(input) {
                        if (!input.hasAttribute(attribute)) {
                            input.addEventListener('blur', ctDebounceFuncExec(handler, debounce));
                            input.setAttribute(attribute, '1');
                        }
                    });
                }
            });
        });
    });
    observer.observe(document.body, {childList: true, subtree: true});
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
    // For Twenty Twenty-Five theme
    let twentyTwentyFiveCommentsSelector = '.apbct-trp > .wp-block-group *[class*="comment-author"]';
    if ( document.querySelector('.apbct-trp .comment-author .comment-author-link') ) {
        // For Spacious theme
        themesCommentsSelector = '.apbct-trp *[class*="comment-author-link"]';
    }
    let woocommerceReviewsSelector = '.apbct-trp *[class*="review__author"]';
    let adminCommentsListSelector = '.apbct-trp td[class*="column-author"] > strong';
    const trpComments = document.querySelectorAll(
        themesCommentsSelector + ',' +
        twentyTwentyFiveCommentsSelector + ',' +
        woocommerceReviewsSelector + ',' +
        adminCommentsListSelector);

    if ( trpComments.length === 0 ) {
        return;
    }

    trpComments.forEach(( element, index ) => {
        // Exceptions for items that are included in the selection
        if (
            element.className.indexOf('review') < 0 &&
            typeof pagenow == 'undefined' &&
            element.parentElement.className.indexOf('group') < 0 &&
            element.tagName != 'DIV'
        ) {
            return;
        }

        // Do not add a badge if there is one inside the element .comment-metadata
        if (element.querySelector('.comment-metadata')) return;

        let trpLayout = document.createElement('div');
        trpLayout.setAttribute('class', 'apbct-real-user-badge');

        let trpImage = document.createElement('img');
        trpImage.setAttribute('src', ctTrpLocalize.imgPersonUrl);
        trpImage.setAttribute('class', 'apbct-real-user-popup-img');

        let trpDescription = document.createElement('div');
        trpDescription.setAttribute('class', 'apbct-real-user-popup');

        let trpDescriptionHeading = document.createElement('strong');
        trpDescriptionHeading.append(ctTrpLocalize.phrases.trpHeading);

        let trpDescriptionContent = document.createElement('div');
        trpDescriptionContent.setAttribute('class', 'apbct-real-user-popup-content_row');
        trpDescriptionContent.setAttribute('style', 'white-space: nowrap');

        let trpDescriptionContentFirstLine = document.createElement('div');
        trpDescriptionContentFirstLine.append(trpDescriptionHeading);
        trpDescriptionContentFirstLine.append(' ');
        trpDescriptionContentFirstLine.append(ctTrpLocalize.phrases.trpContent1);

        let trpDescriptionContentSecondLine = document.createElement('div');
        trpDescriptionContentSecondLine.style.display = 'flex';
        trpDescriptionContentSecondLine.style.gap = '5px';
        let trpDescriptionContentSecondLineTxt = document.createElement('div');
        trpDescriptionContentSecondLineTxt.append(ctTrpLocalize.phrases.trpContent2);
        trpDescriptionContentSecondLine.append(trpDescriptionContentSecondLineTxt);

        if (ctTrpIsAdminCommentsList) {
            let learnMoreLinkWrap = document.createElement('div');
            let learnMoreLink = document.createElement('a');
            learnMoreLink.setAttribute('href', ctTrpLocalize.trpContentLink);
            learnMoreLink.setAttribute('target', '_blank');
            let learnMoreLinkImg = document.createElement('img');
            learnMoreLinkImg.setAttribute('src', ctAdminCommon.new_window_gif);
            learnMoreLinkImg.setAttribute('alt', 'New window');
            learnMoreLinkImg.setAttribute('style', 'padding-top:3px');
            learnMoreLink.append(learnMoreLinkImg);
            learnMoreLinkWrap.append(learnMoreLink);
            trpDescriptionContentSecondLine.append(learnMoreLinkWrap);
        }

        trpDescriptionContent.append(trpDescriptionContentFirstLine, trpDescriptionContentSecondLine);

        trpDescription.append(trpDescriptionContent);
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
