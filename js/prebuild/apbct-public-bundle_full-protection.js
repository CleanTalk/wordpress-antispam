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
        if (+ctPublic.bot_detector_enabled) {
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
            encodedEmailNodes[i].append(apbctTooltip);

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
                ctReplaceInTextNodes(
                    encodedNodes[i],
                    encodedEmail,
                    ctHtmlToText(currentResultData.decoded_email),
                );
                encodedNodes[i].href = linkTypePrefix + currentResultData.decoded_email;

                encodedNodes[i].querySelectorAll('span.apbct-email-encoder').forEach((el) => {
                    let encodedEmailTextInsideMailto = '';
                    decodingResult.data.forEach((row) => {
                        if (row.encoded_email === el.dataset.originalString) {
                            encodedEmailTextInsideMailto = row.decoded_email;
                        }
                    });
                    el.textContent = ctHtmlToText(encodedEmailTextInsideMailto);
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
 * Replace the first occurrence of search in element text only, never in markup or attributes.
 * @param {HTMLElement} element
 * @param {string} search
 * @param {string} replacement
 */
function ctReplaceInTextNodes(element, search, replacement) {
    if (!search) {
        return;
    }
    // Merge adjacent text nodes so a token split between them is still found.
    element.normalize();
    const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
    let node;
    while ((node = walker.nextNode())) {
        if (node.nodeValue.indexOf(search) !== -1) {
            node.nodeValue = node.nodeValue.replace(search, () => replacement);
            return;
        }
    }
}

/**
 * Convert server HTML to plain text without rendering it.
 * @param {string} html
 * @return {string}
 */
function ctHtmlToText(html) {
    if (!html) {
        return '';
    }
    // DOMParser documents are inert: no scripts run and no event handlers fire.
    return new DOMParser().parseFromString(String(html), 'text/html').documentElement.textContent;
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

let ctEmailDecoderIsInitialized = false;

// Listen clicks on encoded emails
document.addEventListener('DOMContentLoaded', function() {
    // Some plugins dispatch a synthetic DOMContentLoaded, listeners must not be attached twice
    if (ctEmailDecoderIsInitialized) {
        return;
    }
    ctEmailDecoderIsInitialized = true;

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
                content.innerHTML = this.sanitizeHtml(this.loaded);
            } else {
                content.innerHTML = this.sanitizeHtml(
                    this.loaded.replace(urlRegex, '<a href="$1" target="_blank">$1</a>'),
                );
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

    /**
     * Allowlist of HTML tags that may appear in the modal content.
     * Tag names are compared as they are reported by the parser, so only HTML elements
     * (always uppercase) can match here - SVG/MathML elements never do.
     */
    allowedTags: [
        'A', 'B', 'BLOCKQUOTE', 'BR', 'BUTTON', 'CODE', 'DD', 'DIV', 'DL', 'DT', 'EM', 'FIELDSET',
        'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'HR', 'I', 'IMG', 'INPUT', 'LABEL', 'LEGEND', 'LI',
        'OL', 'OPTGROUP', 'OPTION', 'P', 'PRE', 'SELECT', 'SMALL', 'SPAN', 'STRONG', 'SUB', 'SUP',
        'TABLE', 'TBODY', 'TD', 'TEXTAREA', 'TFOOT', 'TH', 'THEAD', 'TR', 'U', 'UL',
    ],

    /**
     * Allowlist of attributes. Everything else (including every on* handler,
     * form/formaction, srcset, namespaced attributes) is dropped.
     * Attributes prefixed with 'data-' are allowed too, see isAttributeAllowed().
     */
    allowedAttributes: [
        'alt', 'checked', 'class', 'cols', 'disabled', 'for', 'height', 'href', 'id', 'maxlength',
        'multiple', 'name', 'placeholder', 'readonly', 'rel', 'required', 'rows', 'selected',
        'size', 'src', 'style', 'target', 'title', 'type', 'value', 'width',
    ],

    // Attributes holding an URL - their value is additionally checked by isSafeUrl().
    allowedUrlAttributes: ['href', 'src'],

    allowedUrlSchemes: ['http', 'https', 'mailto'],

    /**
     * Sanitize an untrusted HTML string with a strict allowlist.
     *
     * @param {string} dirty Untrusted HTML.
     * @return {string} Sanitized HTML.
     */
    sanitizeHtml: function( dirty ) {
        if ( typeof dirty !== 'string' || dirty === '' ) {
            return '';
        }

        let template = document.createElement( 'template' );
        template.innerHTML = dirty;

        // Comments are never needed here and are a known mXSS vector.
        let walker = document.createTreeWalker( template.content, NodeFilter.SHOW_COMMENT );
        let comments = [];
        while ( walker.nextNode() ) {
            comments.push( walker.currentNode );
        }
        comments.forEach( function( comment ) {
            comment.remove();
        } );

        let self = this;
        template.content.querySelectorAll( '*' ).forEach( function( el ) {
            // Tag names are compared as the parser reports them - do not normalize the case here.
            // HTML elements are always uppercase, while foreign content (SVG, MathML) keeps its
            // lowercase names, so it never matches the allowlist and is dropped as a whole
            // together with its own script vectors: <svg><script>, <math><annotation-xml>, xlink:href.
            if ( self.allowedTags.indexOf( el.tagName ) === -1 ) {
                el.remove();
                return;
            }

            Array.prototype.slice.call( el.attributes ).forEach( function( attr ) {
                if ( ! self.isAttributeAllowed( attr ) ) {
                    el.removeAttribute( attr.name );
                }
            } );
        } );

        return template.innerHTML;
    },

    /**
     * Check an attribute against the allowlist.
     *
     * @param {Attr} attr Attribute to check.
     * @return {boolean} True if the attribute may be kept.
     */
    isAttributeAllowed: function( attr ) {
        if ( attr.namespaceURI !== null ) {
            return false;
        }

        let name = attr.name.toLowerCase();

        if ( name.indexOf( 'data-' ) === 0 ) {
            return true;
        }

        if ( this.allowedAttributes.indexOf( name ) === -1 ) {
            return false;
        }

        if ( this.allowedUrlAttributes.indexOf( name ) !== -1 ) {
            return this.isSafeUrl( attr.value );
        }

        if ( name === 'style' ) {
            return this.isSafeCss( attr.value );
        }

        return true;
    },

    /**
     * Check that an URL has no scheme or one of the allowed schemes.
     *
     * @param {string} value Attribute value.
     * @return {boolean} True if the URL may be kept.
     */
    isSafeUrl: function( value ) {
        let normalized = String( value ).replace( /[\u0000-\u0020\u007F-\u00A0]+/g, '' ).toLowerCase();
        let scheme = normalized.match( /^([a-z][a-z0-9+.\-]*):/ );

        if ( scheme === null ) {
            return true;
        }

        return this.allowedUrlSchemes.indexOf( scheme[1] ) !== -1;
    },

    /**
     * Check an inline style value for the constructs able to execute code or load remote content.
     *
     * @param {string} value Attribute value.
     * @return {boolean} True if the style may be kept.
     */
    isSafeCss: function( value ) {
        let normalized = String( value ).replace( /[\u0000-\u0020\u007F]+/g, '' ).toLowerCase();

        return ! /(expression\(|javascript:|vbscript:|url\(|@import|-moz-binding|behavior:)/.test( normalized );
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
        document.getElementById( 'cleantalk-modal-content' ).innerHTML =
            cleantalkModal.sanitizeHtml( cleantalkModal.loaded );
    }
});

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
 * @return {string}
 */
function getNoCookieData() { // eslint-disable-line no-unused-vars
    let noCookieDataLocal = apbctLocalStorage.getCleanTalkData();
    let noCookieDataSession = apbctSessionStorage.getCleanTalkData();
    let noCookieData = {...noCookieDataLocal, ...noCookieDataSession};

    // Bot detector browser state is transferred with the hidden field on the NoCookie mode
    if (typeof apbctGetBrowserStatePair === 'function') {
        const browserState = apbctGetBrowserStatePair();
        if (browserState) {
            noCookieData[browserState.key] = browserState.value;
        }
    }

    noCookieData = JSON.stringify(noCookieData);

    return '_ct_no_cookie_data_' + btoa(unescape(encodeURIComponent(noCookieData)));
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
        noCookieData = '_ct_no_cookie_data_' + btoa(unescape(encodeURIComponent(noCookieData)));
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
                if (!+ctPublic.bot_detector_enabled) {
                    const noCookieData = 'data%5Bct_no_cookie_hidden_field%5D=' + getNoCookieData() + '&';
                    defaultSend.call(this, noCookieData + data);
                } else {
                    const eventToken = window.apbctGetEventToken();
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
}

/**
 * Class for handling
 */
class ApbctHandler {
    /**
     * Cron forms handler
     * @param {number} cronStartTimeout
     * @return {void}
     */
    cronFormsHandler(cronStartTimeout = 2000) {
        setTimeout(function() {
            setInterval(function() {
                if (!+ctPublic.bot_detector_enabled && typeof ApbctGatheringData !== 'undefined') {
                    new ApbctGatheringData().restartFieldsListening();
                }
                new ApbctEventTokenTransport().restartBotDetectorEventTokenAttach();
            }, 2000);
        }, cronStartTimeout);
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
}

let apbctReadyIsDone = false;

/**
 * Ready function
 */
// eslint-disable-next-line camelcase,require-jsdoc
async function apbct_ready() {
    // Some plugins dispatch a synthetic DOMContentLoaded, so the whole init must not run twice
    if (apbctReadyIsDone) {
        return;
    }
    apbctReadyIsDone = true;

    const handler = new ApbctHandler();

    setTimeout(function() {
        // Attach data when bot detector is enabled
        if (+ctPublic.bot_detector_enabled) {
            const eventTokenTransport = new ApbctEventTokenTransport();
            eventTokenTransport.attachEventTokenToMultipageGravityForms();
            eventTokenTransport.attachEventTokenToWoocommerceGetRequestAddToCart();
        }
    }, 1000);

    if (ctPublic.settings__sfw__anti_crawler && +ctPublic.bot_detector_enabled) {
        handler.toolForAntiCrawlerCheckDuringBotDetector();
    }

    apbctLocalStorage.set('apbct_existing_visitor', 1);
}

const defaultSend = XMLHttpRequest.prototype.send;
let tokenCheckerIntervalId; // eslint-disable-line no-unused-vars

if (document.readyState !== 'loading') {
    apbct_ready();
} else {
    document.addEventListener('DOMContentLoaded', apbct_ready);
}

/**
 * Run cron jobs
 */
new ApbctHandler().cronFormsHandler(2000);

/**
 * Handle external forms
 */
function ctProtectExternal() {
    for (let i = 0; i < document.forms.length; i++) {
        if (document.forms[i].cleantalk_hidden_action === undefined &&
            document.forms[i].cleantalk_hidden_method === undefined) {
            // current form
            const currentForm = document.forms[i];

            // skip excluded forms
            if ( formIsExclusion(currentForm)) {
                continue;
            }

            // Ajax checking for the integrated forms - will be changed the whole form object to make protection
            if ( isIntegratedForm(currentForm) ) {
                apbctProcessExternalForm(currentForm, i, document);

                // Ajax checking for the integrated forms - will be changed only submit button to make protection
            } else if (
                // MooForm 3rd party service
                currentForm.dataset.mailingListId !== undefined ||
                (typeof(currentForm.action) == 'string' &&
                    (currentForm.action.indexOf('webto.salesforce.com') !== -1)) ||
                (typeof(currentForm.action) == 'string' &&
                currentForm.querySelector('[href*="activecampaign"]')) ||
                (
                    typeof(currentForm.action) == 'string' &&
                    currentForm.action.indexOf('hsforms.com') !== -1 &&
                    currentForm.getAttribute('data-hs-cf-bound')
                )
            ) {
                apbctProcessExternalFormByFakeButton(currentForm, i, document);
                // Common flow - modify form's action
            } else if (
                typeof(currentForm.action) == 'string' &&
                ( currentForm.action.indexOf('http://') !== -1 ||
                    currentForm.action.indexOf('https://') !== -1 )
            ) {
                let tmp = currentForm.action.split('//');
                tmp = tmp[1].split('/');
                const host = tmp[0].toLowerCase();

                if (host !== location.hostname.toLowerCase()) {
                    const ctAction = document.createElement('input');
                    ctAction.name = 'cleantalk_hidden_action';
                    ctAction.value = currentForm.action;
                    ctAction.type = 'hidden';
                    currentForm.appendChild(ctAction);

                    const ctMethod = document.createElement('input');
                    ctMethod.name = 'cleantalk_hidden_method';
                    ctMethod.value = currentForm.method;
                    ctMethod.type = 'hidden';

                    currentForm.method = 'POST';

                    currentForm.appendChild(ctMethod);

                    currentForm.action = document.location;
                }
            }
        }
    }
    // Trying to process external form into an iframe
    apbctProcessIframes();
    // if form is still not processed by fields listening, do it here
    if (ctPublic.bot_detector_enabled != 1 && typeof ApbctGatheringData !== 'undefined') {
        new ApbctGatheringData().startFieldsListening();
    }
}

/**
 * Exclusion forms
 * @param {HTMLElement} currentForm
 * @return {boolean}
 */
function formIsExclusion(currentForm) {
    const exclusionsById = [
        'give-form', // give form exclusion because of direct integration
        'give-next-gen', // give form exclusion because of direct integration
        'frmCalc', // nobletitle-calc
        'ihf-contact-request-form',
        'wpforms', // integration with wpforms
        'fast-cmp-form', // IFRAME of https://eu.fastcmp.com/#overview
    ];

    const exclusionsByRole = [
        'search', // search forms
    ];

    const exclusionsByClass = [
        'search-form', // search forms
        'hs-form', // integrated hubspot plugin through dynamicRenderedForms logic
        'ihc-form-create-edit', // integrated Ultimate Membership Pro plugin through dynamicRenderedForms logic
        'nf-form-content', // integration with Ninja Forms for js events
        'elementor-form', // integration with elementor-form
        'wpforms', // integration with wpforms
        'et_pb_searchform', // integration with elementor-search-form
    ];

    const exclusionsByAction = [
        'paypal.com/cgi-bin/webscr', // search forms
    ];

    let result = false;

    try {
        // mewto forms exclusion
        if (currentForm.parentElement &&
            currentForm.parentElement.classList.length > 0 &&
            currentForm.parentElement.classList[0].indexOf('mewtwo') !== -1) {
            result = true;
        }

        // bitrix24 forms exclusion, because it already protected in main.js by fetch request
        if (currentForm.parentElement &&
            currentForm.parentElement.classList.length > 0 &&
            currentForm.parentElement.classList[0].indexOf('b24-form-content') !== -1) {
            result = true;
        }

        if (currentForm.getAttribute('action') !== null) {
            exclusionsByAction.forEach(function(exclusionAction) {
                if (currentForm.getAttribute('action').indexOf(exclusionAction) !== -1) {
                    result = true;
                }
            });
        }

        exclusionsById.forEach(function(exclusionId) {
            const formId = currentForm.getAttribute('id');
            if ( formId !== null && typeof (formId) !== 'undefined' && formId.indexOf(exclusionId) !== -1 ) {
                result = true;
            }
        });

        exclusionsByClass.forEach(function(exclusionClass) {
            let foundClass = '';
            if (currentForm.getAttribute('class')) {
                foundClass = currentForm.getAttribute('class');
            } else {
                foundClass = apbctGetFormClass(currentForm, exclusionClass);
            }
            const formClass = foundClass;
            if ( formClass !== null && typeof formClass !== 'undefined' && formClass.indexOf(exclusionClass) !== -1 ) {
                if (currentForm.getAttribute('data-hs-cf-bound')) {
                    result = false;
                } else {
                    result = true;
                }
            }
        });

        exclusionsByRole.forEach(function(exclusionRole) {
            const formRole = currentForm.getAttribute('id');
            if ( formRole !== null && typeof formRole !== 'undefined'&& formRole.indexOf(exclusionRole) !== -1 ) {
                result = true;
            }
        });
    } catch (e) {
        console.table('APBCT ERROR: formIsExclusion() - ', e);
    }

    return result;
}

/**
 * Gets the form class if it is not in <form>
 * @param {HTMLElement} currentForm
 * @param {string} exclusionClass
 * @return {string}
 */
function apbctGetFormClass(currentForm, exclusionClass) {
    if (typeof(currentForm) == 'object' && currentForm.querySelector('.' + exclusionClass)) {
        return exclusionClass;
    }
}

/**
 * Handle external forms in iframes
 */
function apbctProcessIframes() {
    const frames = document.getElementsByTagName('iframe');

    if ( frames.length > 0 ) {
        for ( let j = 0; j < frames.length; j++ ) {
            if ( frames[j].contentDocument == null ) {
                continue;
            }

            const iframeForms = frames[j].contentDocument.forms;
            if ( iframeForms.length === 0 ) {
                continue;
            }

            for ( let y = 0; y < iframeForms.length; y++ ) {
                const currentForm = iframeForms[y];
                if ( formIsExclusion(currentForm)) {
                    continue;
                }
                if (currentForm.classList.contains('sender-form-box')) {
                    // todo Need to implement integration with @sender.net@ iframes,
                    // todo there is no current way to intercept custom events, onsumbit is null..
                    // todo however this could be start of new era of integrations
                    // todo if we do stop propagation, protection will work, but original form is broken
                    apbctProcessExternalFormByFakeButton(currentForm, y, frames[j].contentDocument);
                } else {
                    apbctProcessExternalForm(currentForm, y, frames[j].contentDocument);
                }
            }
        }
    }
}

/**
 * Process external forms
 * @param {HTMLElement} currentForm
 * @param {int} iterator
 * @param {HTMLElement} documentObject
 */
function apbctProcessExternalForm(currentForm, iterator, documentObject) {
    const cleantalkPlaceholder = document.createElement('i');
    cleantalkPlaceholder.className = 'cleantalk_placeholder';
    cleantalkPlaceholder.style = 'display: none';

    currentForm.parentElement.insertBefore(cleantalkPlaceholder, currentForm);

    // Deleting form to prevent submit event
    const prev = currentForm.previousSibling;

    // Exclude div.ml-form-recaptcha from cloning - will insert original into cloned form
    const recaptchaDiv = currentForm.querySelector('div.ml-form-recaptcha');
    if (recaptchaDiv) {
        recaptchaDiv.remove();
    }

    const formHtml = currentForm.outerHTML;
    const formOriginal = currentForm;
    const formContent = currentForm.querySelectorAll('input, textarea, select');

    // Remove the original form
    currentForm.parentElement.removeChild(currentForm);

    // Insert a clone
    const placeholder = document.createElement('div');
    placeholder.innerHTML = formHtml;
    const clonedForm = placeholder.firstElementChild;
    prev.after(clonedForm);

    // Insert original recaptcha div into cloned form
    if (recaptchaDiv) {
        const formContentDiv = clonedForm.querySelector('div.ml-form-formContent');
        if (formContentDiv) {
            formContentDiv.after(recaptchaDiv);
        } else {
            clonedForm.appendChild(recaptchaDiv);
        }
    }

    if (formContent && formContent.length > 0) {
        formContent.forEach(function(content) {
            if (content && content.name && content.type !== 'submit' && content.type !== 'button') {
                if (content.type === 'checkbox') {
                    const checkboxInput = clonedForm.querySelector(`input[name="${content.name}"]`);
                    if (checkboxInput) {
                        checkboxInput.checked = content.checked;
                    }
                } else {
                    const input = clonedForm.querySelector(
                        `input[name="${content.name}"], ` +
                        `textarea[name="${content.name}"], ` +
                        `select[name="${content.name}"]`,
                    );
                    if (input) {
                        input.value = content.value;
                    }
                }
            }
        });
    }

    const forceAction = document.createElement('input');
    forceAction.name = 'action';
    forceAction.value = 'cleantalk_force_ajax_check';
    forceAction.type = 'hidden';

    const reUseCurrentForm = documentObject.forms[iterator];

    reUseCurrentForm.appendChild(forceAction);
    reUseCurrentForm.apbctPrev = prev;
    reUseCurrentForm.apbctFormOriginal = formOriginal;

    // mailerlite integration - disable click on submit button
    let mailerliteDetectedClass = false;
    if (reUseCurrentForm.classList !== undefined) {
        // list there all the mailerlite classes
        const mailerliteClasses = ['newsletterform', 'ml-block-form'];
        mailerliteClasses.forEach(function(mailerliteClass) {
            if (reUseCurrentForm.classList.contains(mailerliteClass)) {
                mailerliteDetectedClass = mailerliteClass;
            }
        });
    }

    let mailerliteSubmitButton = null;
    if ( mailerliteDetectedClass ) {
        mailerliteSubmitButton = reUseCurrentForm.querySelector('button[type="submit"]');
        if ( mailerliteSubmitButton !== null && mailerliteSubmitButton !== undefined ) {
            mailerliteSubmitButton.addEventListener('click', function(event) {
                event.preventDefault();
                sendAjaxCheckingFormData(reUseCurrentForm);
            });
        }
        return;
    }

    documentObject.forms[iterator].onsubmit = function(event) {
        event.preventDefault();
        sendAjaxCheckingFormData(event.currentTarget);
    };
}

/**
 * Process external forms via fake button replacing
 * @param {HTMLElement} currentForm
 * @param {int} iterator
 * @param {HTMLElement} documentObject
 */
function apbctProcessExternalFormByFakeButton(currentForm, iterator, documentObject) {
    let submitButtonOriginal = currentForm.querySelector('[type="submit"]');
    if (!submitButtonOriginal) {
        // sender.com form button by class
        submitButtonOriginal = currentForm.querySelector('.submit-button');
    }
    // skip if no button found
    if ( ! submitButtonOriginal ) {
        return;
    }
    const onsubmitOriginal = submitButtonOriginal.form.onsubmit;
    // skip if no onsubmit found
    if ( ! onsubmitOriginal ) {
        return;
    }

    const parent = submitButtonOriginal.parentElement;
    const submitButtonHtml = submitButtonOriginal.outerHTML;

    // Remove the original submit button
    submitButtonOriginal.remove();

    // Insert a clone of the submit button
    const placeholder = document.createElement('div');
    placeholder.innerHTML = submitButtonHtml;
    parent.appendChild(placeholder.firstElementChild);

    const forceAction = document.createElement('input');
    forceAction.name = 'action';
    forceAction.value = 'cleantalk_force_ajax_check';
    forceAction.type = 'hidden';

    const reUseCurrentForm = documentObject.forms[iterator];

    reUseCurrentForm.appendChild(forceAction);
    reUseCurrentForm.apbctParent = parent;
    reUseCurrentForm.submitButtonOriginal = submitButtonOriginal;
    reUseCurrentForm.onsubmitOriginal = onsubmitOriginal;

    documentObject.forms[iterator].onsubmit = function(event) {
        event.preventDefault();

        // MooSend spinner activate
        apbctMoosendSpinnerToggle(event.currentTarget);

        sendAjaxCheckingFormData(event.currentTarget);
    };
}

/**
 * Activate or deactivate spinner for Moosend form during request checking
 * @param {HTMLElement} form
 */
function apbctMoosendSpinnerToggle(form) {
    const buttonElement = form.querySelector('button[type="submit"]');
    if ( buttonElement ) {
        const spinner = buttonElement.querySelector('i');
        const submitText = buttonElement.querySelector('span');
        if (spinner && submitText) {
            if ( spinner.style.zIndex == 1 ) {
                submitText.style.opacity = 1;
                spinner.style.zIndex = -1;
                spinner.style.opacity = 0;
            } else {
                submitText.style.opacity = 0;
                spinner.style.zIndex = 1;
                spinner.style.opacity = 1;
            }
        }
    }
}

/**
 * Process external forms
 * @param {HTMLElement} formSource
 * @param {HTMLElement} formTarget
 */
function apbctReplaceInputsValuesFromOtherForm(formSource, formTarget) {
    const inputsSource = formSource.querySelectorAll('button, input, textarea, select');
    const inputsTarget = formTarget.querySelectorAll('button, input, textarea, select');

    inputsSource.forEach((elemSource) => {
        inputsTarget.forEach((elemTarget) => {
            if (
                (
                    (
                        formSource.outerHTML.indexOf('list-manage.com/subscribe') !== -1
                    ) &&
                    elemSource.id === elemTarget.id // sequence by id
                ) ||
                (
                    (
                        formSource.outerHTML.indexOf('action="https://www.kulahub.net') !== -1 ||
                        isFormHasDiviRedirect(formSource) ||
                        formSource.outerHTML.indexOf('class="et_pb_contact_form') !== -1 ||
                        formSource.outerHTML.indexOf('action="https://api.kit.com') !== -1 ||
                        formSource.outerHTML.indexOf('activehosted.com') !== -1 ||
                        formSource.outerHTML.indexOf('aweber.com') !== -1 ||
                        formSource.outerHTML.indexOf('action="https://crm.zoho.com') !== -1
                    ) &&
                    elemSource.name !== '' &&
                    elemSource.name === elemTarget.name // sequence by name
                ) ||
                (
                    elemSource.outerHTML === elemTarget.outerHTML // sequence by outerHTML (all other cases)
                )
            ) {
                if (elemTarget.type === 'checkbox' || elemTarget.type === 'radio') {
                    elemTarget.checked = apbctVal(elemSource);
                } else {
                    elemTarget.value = apbctVal(elemSource);
                }
            }
        });
    });
}

// clear protected iframes list
ctProtectOutsideFunctionalClearLocalStorage();

window.addEventListener('load', function() {
    if ( ! +ctPublic.settings__forms__check_external ) {
        return;
    }

    if (typeof window.ctDymnamicRenderedFormHandlerInterval === 'number') {
        clearInterval(window.ctDymnamicRenderedFormHandlerInterval );
    }

    setTimeout(function() {
        ctProtectExternal();
        catchNextendSocialLoginForm();
        // run dynamic form catch first time
        catchDynamicRenderedForm();
        // run interval to rehandle form if current state is reset and has no cleantalk intervent
        window.ctDymnamicRenderedFormHandlerInterval = setInterval(
            catchDynamicRenderedForm,
            2000,
        );
        ctProtectOutsideFunctionalOnTagsType('div');
        ctProtectOutsideFunctionalOnTagsType('iframe');
    }, 2000);

    ctProtectKlaviyoForm();
});

let ctProtectOutsideFunctionalCheckPerformed;

/**
 * Protect outside functional. Tags div and iframe supported.
 * @param {string} tagType
 */
function ctProtectOutsideFunctionalOnTagsType(tagType) {
    let entityTagsAny = document.querySelectorAll(tagType);
    if (entityTagsAny.length > 0) {
        entityTagsAny.forEach(function(entity) {
            let protectedType = ctProtectOutsideFunctionalIsTagIntegrated(entity);
            if (false !== protectedType) {
                let lsStorageName = 'apbct_outside_functional_protected_tags__' + protectedType;
                let lsUniqueName = entity.id !== '' ? entity.id : false;
                lsUniqueName = false === lsUniqueName && entity.className !== '' ? entity.className : lsUniqueName;
                lsUniqueName = false === lsUniqueName && entity.src !== '' ? entity.src.substring(0, 50) : lsUniqueName;
                // todo we can not protect any entity that has no id or class or src :(
                // pass if is already protected
                if (
                    false === lsUniqueName ||
                    (
                        false !== apbctLocalStorage.get(lsStorageName) &&
                        apbctLocalStorage.get(lsStorageName).length > 0 &&
                        apbctLocalStorage.get(lsStorageName)[0].indexOf(lsUniqueName) !== -1
                    )
                ) {
                    return;
                }
                ctProtectOutsideFunctionalHandler(entity, lsStorageName, lsUniqueName);
            }
        });
    }
}

/**
 * Check if the entity must be protected
 *
 * @param {HTMLElement} entity
 * @return {string|false}
 */
function ctProtectOutsideFunctionalIsTagIntegrated(entity) {
    if (entity.tagName !== undefined) {
        if (entity.tagName === 'IFRAME') {
            if (entity.src.indexOf('form.typeform.com') !== -1 ||
                entity.src.indexOf('forms.zohopublic.com') !== -1 ||
                entity.src.indexOf('link.surepathconnect.com') !== -1 ||
                entity.src.indexOf('hello.dubsado.com') !== -1 ||
                (
                    // HubSpot modified the iframe layout
                    entity.classList.contains('hs-form-iframe') ||
                    entity.parentElement.classList.contains('hs-form-frame')
                ) ||
                ( entity.src.indexOf('facebook.com') !== -1 && entity.src.indexOf('plugins/comments.php') !== -1) ||
                entity.id.indexOf('chatway_widget_app') !== -1
            ) {
                return entity.tagName;
            }
        }
        if (entity.tagName === 'DIV') {
            if (
                entity.className.indexOf('Window__Content-sc') !== -1 || // all in one chat widget
                entity.className.indexOf('WidgetBackground__Content-sc') !== -1 // all in one contact form widget
            ) {
                return entity.tagName;
            }
        }
    }
    return false;
}

/**
 * Protect forms placed in iframe with outside src handler
 *
 * @param {HTMLElement} entity
 * @param {string} lsStorageName
 * @param {string|false} lsUniqueName
 */
function ctProtectOutsideFunctionalHandler(entity, lsStorageName, lsUniqueName) {
    let originParentPosition = null;
    let entityParent = null;

    if (entity.parentNode !== undefined) {
        entityParent = entity.parentNode;
    } else {
        return;
    }

    if (
        entityParent.style !== undefined &&
        entityParent.style !== null &&
        entityParent.style.position !== undefined &&
        entityParent.style.position !== ''
    ) {
        entityParent.style.position = originParentPosition;
    } else {
        const computedStyle = window.getComputedStyle(entityParent);
        if ( computedStyle.position !== undefined &&
            ( computedStyle.position === 'fixed' || computedStyle.position === 'relative' )
        ) {
            // There is already right position. Skip
        } else {
            entityParent.style.position = 'relative';
        }
    }
    ctAttachCoverCSSToHead();
    entityParent.appendChild(ctProtectOutsideFunctionalGenerateCover());
    let entitiesProtected = apbctLocalStorage.get(lsStorageName);
    if (false === entitiesProtected || !Array.isArray(entitiesProtected)) {
        entitiesProtected = [];
    }
    if (lsUniqueName) {
        entitiesProtected.push(lsUniqueName);
        apbctLocalStorage.set(lsStorageName, entitiesProtected);
    }
}

/**
 * Append cover styles to the head.
 */
function ctAttachCoverCSSToHead() {
    if (document.querySelector('#apbct-cover-stylesheet')) {
        return;
    }
    const styleElement = document.createElement('style');
    styleElement.setAttribute('type', 'text/css');
    styleElement.setAttribute('id', 'apbct-cover-stylesheet');
    styleElement.textContent = `
        .apbct-iframe-preloader {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            position: relative;
            left: calc(50% - 27px);
            top: calc(50% - 27px);
            animation: apbctIframePreloaderRotate 1s linear infinite
        }
        
        .apbct-iframe-preloader-spin {
            content: "";
            box-sizing: border-box;
            position: absolute;
            inset: 0px;
            border-radius: 50%;
            border: 6px solid #FFF;
            animation: apbctIframePreloaderPrixClipFix 2s linear infinite ;
        }
        
        .apbct-iframe-preloader-text {
            color: white;
            background: black;
            display: block;
            width: 100%;
            text-align: center;
            position: absolute;
            top: 60%;
        }
        
        @keyframes apbctIframePreloaderRotate {
            100%   {transform: rotate(360deg)}
        }
        
        @keyframes apbctIframePreloaderPrixClipFix {
            0%   {clip-path:polygon(50% 50%,0 0,0 0,0 0,0 0,0 0)}
            25%  {clip-path:polygon(50% 50%,0 0,100% 0,100% 0,100% 0,100% 0)}
            50%  {clip-path:polygon(50% 50%,0 0,100% 0,100% 100%,100% 100%,100% 100%)}
            75%  {clip-path:polygon(50% 50%,0 0,100% 0,100% 100%,0 100%,0 100%)}
            100% {clip-path:polygon(50% 50%,0 0,100% 0,100% 100%,0 100%,0 0)}
        }
    `;
    document.head.appendChild(styleElement);
}

/**
 * Clear local storage for OutsideFunctional protection
 *
 */
function ctProtectOutsideFunctionalClearLocalStorage() {
    apbctLocalStorage.set('apbct_outside_functional_protected_tags__DIV', []);
    apbctLocalStorage.set('apbct_outside_functional_protected_tags__IFRAME', []);
}

/**
 * Generate cover for outside functional protection
 *
 * @return {HTMLElement} cover
 */
function ctProtectOutsideFunctionalGenerateCover() {
    let cover = document.createElement('div');
    cover.style.width = '100%';
    cover.style.height = '100%';
    cover.style.background = 'black';
    cover.style.opacity = 0;
    cover.style.position = 'absolute';
    cover.style.top = 0;
    cover.style.zIndex = 9999;
    cover.setAttribute('name', 'apbct_cover');
    cover.onclick = function(e) {
        if (ctProtectOutsideFunctionalCheckPerformed === undefined) {
            let currentDiv = e.currentTarget;
            currentDiv.style.opacity = 0.5;
            let preloader = document.createElement('div');
            let preloaderSpin = document.createElement('div');
            let preloaderText = document.createElement('span');
            preloader.className = 'apbct-iframe-preloader';
            preloaderSpin.className = 'apbct-iframe-preloader-spin';
            preloaderText.innerText = 'CleanTalk Anti-Spam checking data..';
            preloaderText.className = 'apbct-iframe-preloader-text';
            preloader.appendChild(preloaderSpin);
            currentDiv.appendChild(preloader);
            currentDiv.appendChild(preloaderText);
            let botDetectorToken = null;

            if (!botDetectorToken) {
                botDetectorToken = apbctLocalStorage.get('bot_detector_event_token');
            }

            let data = {
                'action': 'cleantalk_outside_iframe_ajax_check',
                'ct_no_cookie_hidden_field': getNoCookieData(),
                'ct_bot_detector_event_token': botDetectorToken,
            };

            apbct_public_sendAJAX(
                data,
                {
                    async: false,
                    callback: function(result) {
                        ctProtectOutsideFunctionalCheckPerformed = true;
                        let callbackError = false;
                        if (typeof result !== 'object') {
                            console.warn('APBCT outside functional check error, skip check.');
                            callbackError = true;
                        }

                        let comment = 'Blocked by CleanTalk Anti-Spam';
                        if (result !== undefined && result.apbct !== undefined && result.apbct.comment !== undefined) {
                            comment = result.apbct.comment;
                        }

                        if (
                            (
                                typeof result === 'object' && result.apbct !== undefined &&
                                result.apbct.blocked === false
                            ) ||
                            (
                                typeof result === 'object' && result.success !== undefined &&
                                result.success === true
                            ) ||
                            callbackError
                        ) {
                            document.querySelectorAll('div[name="apbct_cover"]').forEach(function(el) {
                                el.remove();
                            });
                        } else {
                            document.querySelectorAll('.apbct-iframe-preloader-text').forEach((el) => {
                                el.innerText = comment;
                            });
                            document.querySelectorAll('div.apbct-iframe-preloader').forEach((el) => {
                                el.remove();
                            });
                        }
                    },
                },
            );
        }
    };
    return cover;
}

/**
 * Protect klaviyo forms
 */
function ctProtectKlaviyoForm() {
    if (!document.querySelector('link[rel="dns-prefetch"][href="//static.klaviyo.com"]')) {
        return;
    }

    let i = setInterval(() => {
        const klaviyoForms = document.querySelectorAll('form.klaviyo-form');
        if (klaviyoForms.length) {
            clearInterval(i);
            klaviyoForms.forEach((form, index) => {
                apbctProcessExternalFormKlaviyo(form, index, document);
            });
        }
    }, 500);
}

/**
 * Protect klaviyo forms
 * @param {HTMLElement} form
 * @param {int} iterator
 * @param {HTMLElement} documentObject
 */
function apbctProcessExternalFormKlaviyo(form, iterator, documentObject) {
    const btn = form.querySelector('button[type="button"].needsclick');
    if (!btn) {
        return;
    }

    const forceAction = document.createElement('input');
    forceAction.name = 'action';
    forceAction.value = 'cleantalk_force_ajax_check';
    forceAction.type = 'hidden';
    form.appendChild(forceAction);

    let cover = document.createElement('div');
    cover.id = 'apbct-klaviyo-cover';
    cover.style.width = '100%';
    cover.style.height = '100%';
    cover.style.background = 'black';
    cover.style.opacity = 0;
    cover.style.position = 'absolute';
    cover.style.top = 0;
    cover.style.cursor = 'pointer';
    cover.onclick = function(e) {
        sendAjaxCheckingFormData(form);
    };
    btn.parentNode.style.position = 'relative';
    btn.parentNode.appendChild(cover);
}

/**
 * Catch NSL form integration
 */
function catchNextendSocialLoginForm() {
    let blockNSL = document.getElementById('nsl-custom-login-form-main');
    if (blockNSL) {
        blockBtnNextendSocialLogin(blockNSL);
    }
}

/**
 * Blocking NSL plugin buttons
 * @param {HTMLElement} blockNSL
 */
function blockBtnNextendSocialLogin(blockNSL) {
    let parentBtnsNSL = blockNSL.querySelectorAll('.nsl-container-buttons a');
    let childBtnsNSL = blockNSL.querySelectorAll('a[data-plugin="nsl"] .nsl-button');
    parentBtnsNSL.forEach((el) => {
        el.setAttribute('data-oauth-login-blocked', 'true');
        el.addEventListener('click', (event) => {
            event.preventDefault();
        });
    });
    childBtnsNSL.forEach((el) => {
        el.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            ctCheckAjax(el);
        });
    });
}

/**
 * Unlocking the button and clicking on it after an ajax response
 * @param {HTMLElement} childBtn
 */
function allowAjaxNextendSocialLogin(childBtn) {
    childBtn.parentElement.setAttribute('data-oauth-login-blocked', 'false');
    childBtn.parentElement.click();
}

/**
 * Locking the button and entering a message after an ajax response
 * @param {HTMLElement} childBtn
 * @param {string} msg
 */
function forbiddenAjaxNextendSocialLogin(childBtn, msg) {
    let parentElement = childBtn.parentElement;
    if (parentElement.getAttribute('data-oauth-login-blocked') == 'false') {
        parentElement.setAttribute('data-oauth-login-blocked', 'true');
    }
    if (!document.querySelector('.ct-forbidden-msg')) {
        let elemForMsg = document.createElement('div');
        elemForMsg.className = 'ct-forbidden-msg';
        elemForMsg.style.background = 'red';
        elemForMsg.style.color = 'white';
        elemForMsg.style.padding = '5px';
        elemForMsg.innerHTML = msg;
        parentElement.insertAdjacentElement('beforebegin', elemForMsg);
    }
}

/**
 * User verification using user data and ajax
 * @param {HTMLElement} elem
 */
function ctCheckAjax(elem) {
    let data = {
        'action': 'cleantalk_nsl_ajax_check',
        'ct_no_cookie_hidden_field': document.getElementsByName('ct_no_cookie_hidden_field')[0].value,
    };

    apbct_public_sendAJAX(
        data,
        {
            async: false,
            callback: function(result) {
                if (result.apbct.blocked === false) {
                    allowAjaxNextendSocialLogin(elem);
                } else {
                    forbiddenAjaxNextendSocialLogin(elem, result.apbct.comment);
                }
            },
        },
    );
}

/**
 * Checking the form integration
 * @param {HTMLElement} formObj
 * @return {boolean}
 */
function isIntegratedForm(formObj) {
    const formAction = typeof(formObj.action) == 'string' ? formObj.action : '';
    const formId = formObj.getAttribute('id') !== null ? formObj.getAttribute('id') : '';
    const formClassName = typeof(formObj.className) == 'string' ? formObj.className : '';

    if (
        (
            formAction.indexOf('app.convertkit.com') !== -1 || // ConvertKit form
            formAction.indexOf('app.kit.com') !== -1 || // ConvertKit new form
            formAction.indexOf('api.kit.com') !== -1 // ConvertKit new form
        ) ||
        ( formObj.firstChild.classList !== undefined &&
            formObj.firstChild.classList.contains('cb-form-group') ) || // Convertbox form
        formAction.indexOf('mailerlite.com') !== -1 || // Mailerlite integration
        formAction.indexOf('colcolmail.co.uk') !== -1 || // colcolmail.co.uk integration
        formAction.indexOf('paypal.com') !== -1 ||
        formAction.indexOf('infusionsoft.com') !== -1 ||
        formAction.indexOf('secure2.convio.net') !== -1 ||
        formAction.indexOf('hookb.in') !== -1 ||
        formAction.indexOf('external.url') !== -1 ||
        formAction.indexOf('tp.media') !== -1 ||
        formAction.indexOf('flodesk.com') !== -1 ||
        formAction.indexOf('sendfox.com') !== -1 ||
        formAction.indexOf('aweber.com') !== -1 ||
        formAction.indexOf('secure.payu.com') !== -1 ||
        formAction.indexOf('mautic') !== -1 || formId.indexOf('mauticform_') !== -1 ||
        formId.indexOf('ihf-contact-request-form') !== -1 ||
        formAction.indexOf('crm.zoho.com') !== -1 ||
        formId.indexOf('delivra-external-form') !== -1 ||
        // todo Return to Hubspot for elementor in the future, disabled of reason https://doboard.com/1/task/9227
        // ( formObj.classList !== undefined &&
        //     !formObj.classList.contains('woocommerce-checkout') &&
        //     formObj.hasAttribute('data-hs-cf-bound')
        // ) || // Hubspot integration in Elementor form// Hubspot integration in Elementor form
        formAction.indexOf('eloqua.com') !== -1 || // Eloqua integration
        formAction.indexOf('kulahub.net') !== -1 || // Kulahub integration
        isFormHasDiviRedirect(formObj) || // Divi contact form
        formAction.indexOf('eocampaign1.com') !== -1 || // EmailOctopus Campaign form
        formAction.indexOf('wufoo.com') !== -1 || // Wufoo form
        formAction.indexOf('activehosted.com') !== -1 || // Activehosted form
        formAction.indexOf('publisher.copernica.com') !== -1 || // publisher.copernica
        (
            formAction.indexOf('whatsapp.com') !== -1 &&
            formClassName.indexOf('chaty') !== -1
        ) || // chaty plugin whatsapp form
        (
            formObj.classList !== undefined &&
            formObj.classList.contains('sp-element-container')
        ) || // Sendpulse form
        apbctIsFormInDiv(formObj, 'b24-form') || // Bitrix24 CRM external forms
        formAction.indexOf('list-manage.com') !== -1 // MailChimp
    ) {
        return true;
    }

    return false;
}

/**
 * This function detect if the form has DIVI redirect. If so, the form will work as external.
 * @param {HTMLElement} formObj
 * @return {boolean}
 */
function isFormHasDiviRedirect(formObj) {
    let result = false;
    const diviRedirectedSignSet = document.querySelector('div[id^="et_pb_contact_form"]');
    if (
        typeof formObj === 'object' && formObj !== null &&
        diviRedirectedSignSet !== null &&
        diviRedirectedSignSet.hasAttribute('data-redirect_url') &&
        diviRedirectedSignSet.getAttribute('data-redirect_url') !== '' &&
        diviRedirectedSignSet.querySelector('form[class^="et_pb_contact_form"]') !== null
    ) {
        result = formObj === diviRedirectedSignSet.querySelector('form[class^="et_pb_contact_form"]');
    }
    return result;
}

/**
 * Sending Ajax for checking form data
 * @param {HTMLElement} form
 * @param {HTMLElement} prev
 * @param {HTMLElement} formOriginal
 */
function sendAjaxCheckingFormData(form) {
    // Get visible fields and set cookie
    if (window.apbctVisibleFields) {
        window.apbctVisibleFields.setCookie({0: window.apbctVisibleFields.collect(form)});
    }

    const data = {
        'ct_bot_detector_event_token': apbctLocalStorage.get('bot_detector_event_token'),
    };
    let elems = form.elements;
    elems = Array.prototype.slice.call(elems);

    elems.forEach( function( elem, y ) {
        if ( elem.name === '' ) {
            data['input_' + y] = elem.value;
        } else {
            data[elem.name] = elem.value;
        }
    });

    apbct_public_sendAJAX(
        data,
        {
            async: false,
            callback: function( result, data, params, obj ) {
                // MooSend spinner deactivate
                apbctMoosendSpinnerToggle(form);
                // hubspot flag
                const isHubSpotEmbedForm = (
                    form.hasAttribute('action') &&
                    form.getAttribute('action').indexOf('hsforms') !== -1
                );
                if ((result.apbct === undefined && result.data === undefined) ||
                    (result.apbct !== undefined && ! +result.apbct.blocked)
                ) {
                    // Clear service fields
                    for (const el of form.querySelectorAll('input[name="apbct_visible_fields"]')) {
                        el.remove();
                    }
                    for (const el of form.querySelectorAll('input[value="cleantalk_force_ajax_check"]')) {
                        el.remove();
                    }
                    for (const el of form.querySelectorAll('input[name="ct_no_cookie_hidden_field"]')) {
                        el.remove();
                    }

                    // Klaviyo integration
                    if (form.classList !== undefined && form.classList.contains('klaviyo-form')) {
                        const cover = document.getElementById('apbct-klaviyo-cover');
                        if (cover) {
                            cover.remove();
                        }
                        const btn = form.querySelector('button[type="button"].needsclick');
                        if (btn) {
                            btn.disabled = false;
                            btn.click();
                        }
                        return;
                    }

                    // MooSend integration
                    if ( form.dataset.mailingListId !== undefined ) {
                        let submitButton = form.querySelector('[type="submit"]');
                        submitButton.remove();
                        const parent = form.apbctParent;
                        parent.appendChild(form.submitButtonOriginal);
                        submitButton = form.querySelector('[type="submit"]');
                        submitButton.click();
                        return;
                    }

                    // Salesforce integration
                    if (form.hasAttribute('action') &&
                        (form.getAttribute('action').indexOf('webto.salesforce.com') !== -1)
                    ) {
                        let submitButton = form.querySelector('[type="submit"]');
                        submitButton.remove();
                        const parent = form.apbctParent;
                        parent.appendChild(form.submitButtonOriginal);
                        form.onsubmit = form.onsubmitOriginal;
                        submitButton = form.querySelector('[type="submit"]');
                        submitButton.click();
                        return;
                    }

                    // Hubspot bounded integration
                    if (isHubSpotEmbedForm) {
                        let submitButton = form.querySelector('[type="submit"]');
                        submitButton.remove();
                        const parent = form.apbctParent;
                        parent.appendChild(form.submitButtonOriginal);
                        form.onsubmit = form.onsubmitOriginal;
                        submitButton = form.querySelector('[type="submit"]');
                        submitButton.click();
                        return;
                    }

                    const formNew = form;
                    form.parentElement.removeChild(form);
                    const prev = form.apbctPrev;
                    const formOriginal = form.apbctFormOriginal;
                    let isExternalFormsRestartRequired = apbctExternalFormsRestartRequired(formOriginal);
                    apbctReplaceInputsValuesFromOtherForm(formNew, formOriginal);

                    prev.after( formOriginal );

                    // Copy recaptcha div from cloned form to original form
                    const recaptchaDivFromCloned = formNew.querySelector('div.ml-form-recaptcha');
                    if (recaptchaDivFromCloned) {
                        const recaptchaClone = recaptchaDivFromCloned.cloneNode(true);
                        const formContentDivOriginal = formOriginal.querySelector('div.ml-form-formContent');
                        if (formContentDivOriginal) {
                            formContentDivOriginal.after(recaptchaClone);
                        } else {
                            formOriginal.appendChild(recaptchaClone);
                        }
                    }

                    // Clear visible_fields input
                    for (const el of formOriginal.querySelectorAll('input[name="apbct_visible_fields"]')) {
                        el.remove();
                    }

                    for (const el of formOriginal.querySelectorAll('input[value="cleantalk_force_ajax_check"]')) {
                        el.remove();
                    }

                    // Common click event
                    let submButton = formOriginal.querySelectorAll('button[type=submit]');
                    if ( submButton.length !== 0 ) {
                        submButton[0].click();
                        if (isExternalFormsRestartRequired) {
                            setTimeout(function() {
                                ctProtectExternal();
                            }, 1500);
                        }
                        return;
                    }

                    submButton = formOriginal.querySelectorAll('input[type=submit]');
                    if ( submButton.length !== 0 ) {
                        submButton[0].click();
                        if (isExternalFormsRestartRequired) {
                            setTimeout(function() {
                                ctProtectExternal();
                            }, 1500);
                        }
                        return;
                    }

                    // ConvertKit direct integration
                    submButton = formOriginal.querySelectorAll('button[data-element="submit"]');
                    if ( submButton.length !== 0 ) {
                        submButton[0].click();
                        return;
                    }
                    submButton = formOriginal.querySelectorAll('button#ck_subscribe_button');
                    if ( submButton.length !== 0 ) {
                        submButton[0].click();
                        return;
                    }

                    // Paypal integration
                    submButton = formOriginal.querySelectorAll('input[type="image"][name="submit"]');
                    if ( submButton.length !== 0 ) {
                        submButton[0].click();
                    }
                }
                if ((result.apbct !== undefined && +result.apbct.blocked) ||
                    (result.data !== undefined && result.data.message !== undefined)
                ) {
                    window.apbctShowBlockMessage(result);
                    // hubspot embed form needs to reload page to prevent forms mishandling
                    if (isHubSpotEmbedForm) {
                        setTimeout(function() {
                            document.location.reload();
                        }, 3000);
                    }
                }
            },
        });
}

/**
 * Checks if a form requires a restart due to external integration.
 * @param {object} formOriginal
 * @return {boolean}
 */
function apbctExternalFormsRestartRequired(formOriginal) {
    return formOriginal &&
        (// mautic forms integration
            typeof formOriginal.id === 'string' &&
            formOriginal.id.indexOf('mautic') !== -1
        ) ||
        (// mailchimp forms integration
            typeof formOriginal.action === 'string' &&
            formOriginal.action.indexOf('list-manage.com/subscribe') !== -1
        );
}


/**
 * Handle dynamic rendered form
 */
function catchDynamicRenderedForm() {
    const forms = document.getElementsByTagName('form');

    catchDynamicRenderedFormHandler(forms);

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

            catchDynamicRenderedFormHandler(iframeForms, frames[j].contentDocument);
        }
    }
}

/**
 * Handles dynamic rendered forms by attaching an onsubmit event handler to them.
 *
 * @param {HTMLCollection} forms - A collection of form elements to be processed.
 * @param {Document} [documentObject=document] - The document object to use for querying elements.
 */
function catchDynamicRenderedFormHandler(forms, documentObject = document) {
    const neededFormIds = [];
    for (const form of forms) {
        // should be checked to do not handle form again on interval
        if ( !form.hasOwnProperty('apbct_external_onsubmit_prev') ) {
            const formIdAttr = form.getAttribute('id');
            if (formIdAttr && formIdAttr.indexOf('hsForm') !== -1) {
                neededFormIds.push(formIdAttr);
            }
            if (formIdAttr && formIdAttr.indexOf('createuser') !== -1 &&
                (form.classList !== undefined && form.classList.contains('ihc-form-create-edit'))
            ) {
                neededFormIds.push(formIdAttr);
            }
        }
    }

    for (const formId of neededFormIds) {
        const form = documentObject.getElementById(formId);
        form.apbct_external_onsubmit_prev = form.onsubmit;
        form.onsubmit = sendAjaxCheckingDynamicFormData;
    }
}

/**
 * Sending Ajax for checking form data on dynamic rendered form
 * @param {HTMLElement} form
 */
function sendAjaxCheckingDynamicFormData(form) {
    form.preventDefault();
    form.stopImmediatePropagation();
    const formEvent = form;
    form = form.target;

    const forceAction = document.createElement('input');
    forceAction.name = 'action';
    forceAction.value = 'cleantalk_force_ajax_check';
    forceAction.type = 'hidden';
    form.appendChild(forceAction);

    // Get visible fields and set cookie
    if (window.apbctVisibleFields) {
        window.apbctVisibleFields.setCookie({0: window.apbctVisibleFields.collect(form)});
    }
    const attachData = new ApbctAttachData();
    form.append(attachData.constructNoCookieHiddenField('hidden'));

    const data = {};
    let elems = form.elements;
    elems = Array.prototype.slice.call(elems);

    elems.forEach( function( elem, y ) {
        if ( elem.name === '' ) {
            data['input_' + y] = elem.value;
        } else {
            data[elem.name] = elem.value;
        }
    });

    apbct_public_sendAJAX(
        data,
        {
            async: false,
            callback: function(result) {
                if ( result.apbct === undefined || ! +result.apbct.blocked ) {
                    form.onsubmit = null;

                    // Clear service fields
                    for (const el of form.querySelectorAll('input[name="apbct_visible_fields"]')) {
                        el.remove();
                    }
                    for (const el of form.querySelectorAll('input[value="cleantalk_force_ajax_check"]')) {
                        el.remove();
                    }
                    for (const el of form.querySelectorAll('input[name="ct_no_cookie_hidden_field"]')) {
                        el.remove();
                    }

                    // Call previous submit action
                    if (form.apbct_external_onsubmit_prev instanceof Function) {
                        let timerId = setTimeout(function() {
                            form.apbct_external_onsubmit_prev.call(form, formEvent);
                        }, 500);
                        clearTimeout(timerId);
                    }

                    const submButton = form.querySelector('input[type="submit"]');
                    if (submButton) {
                        submButton.click();
                        return;
                    }
                }

                if (result.apbct !== undefined && +result.apbct.blocked) {
                    window.apbctShowBlockMessage(result);
                }
            },
        });
}

/**
 * Implement jQuery val() function
 * @param {HTMLElement} el
 * @return {HTMLElements}
 */
function apbctVal(el) {
    if (el.options && el.multiple) {
        return el.options
            .filter((option) => option.selected)
            .map((option) => option.value);
    } else if (el.type === 'checkbox' || el.type === 'radio') {
        return el.checked ? el.checked : null;
    } else {
        return el.value;
    }
}

/**
 * Checks if a form object is inside a div with a specified class name.
 *
 * @param {HTMLElement} formObj - The form element to check.
 * @param {string} divClassName - The class name of the div to look for.
 * @return {boolean} - Returns true if the form is inside a div with the specified class name, false otherwise.
 */
function apbctIsFormInDiv(formObj, divClassName) {
    let parent = formObj.parentElement;
    while (parent) {
        if (parent.classList.contains(divClassName)) {
            return true;
        }
        parent = parent.parentElement;
    }
    return false;
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
            post_url: document.location.href,
            referrer: document.referrer,
        };
        if (+ctPublic.bot_detector_enabled) {
            data.event_token = apbctLocalStorage.get('bot_detector_event_token');
        } else {
            data.event_javascript_data = getJavascriptClientData();
        }

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
            document.dispatchEvent(new Event('apbctForceProtectionAllowed'));
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
                    const urlDecoded = decodeURIComponent(form);
                    wrapper.outerHTML = atob(urlDecoded);
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

let apbctForceProtectionIsInitialized = false;

/**
 * Force protection
 */
function apbctForceProtect() {
    // Some plugins dispatch a synthetic DOMContentLoaded, the check must be sent only once
    if (apbctForceProtectionIsInitialized) {
        return;
    }
    apbctForceProtectionIsInitialized = true;

    if (+ctPublic.settings__forms__force_protection && typeof ApbctForceProtection !== 'undefined') {
        new ApbctForceProtection();
    }
}

if (ctPublic.data__key_is_ok) {
    if (document.readyState !== 'loading') {
        apbctForceProtect();
    } else {
        document.addEventListener('DOMContentLoaded', apbctForceProtect);
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

let ctInternalFormsIsInitialized = false;

document.addEventListener('DOMContentLoaded', function() {
    // Some plugins dispatch a synthetic DOMContentLoaded, forms must not be re-wrapped
    if (ctInternalFormsIsInitialized) {
        return;
    }
    ctInternalFormsIsInitialized = true;

    if ( ! +ctPublic.settings__forms__check_internal ) {
        return;
    }

    setTimeout(() => {
        ctProtectInternalForms();
    }, 500);

    document.addEventListener('apbctForceProtectionAllowed', function() {
        ctProtectInternalForms();
    });
});

/**
 * Protect internal forms
 * @return {void}
 */
function ctProtectInternalForms() {
    let ctCurrAction = '';
    let ctCurrForm = '';

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
}

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
        'admin-ajax.php', // WordPress ajax
        'admin-post.php', // WordPress ajax too (Used in MailPoet -_-)
    ];

    return ctInternalScriptExclusions.some((item) => {
        return action.match(new RegExp(ctPublic.blog_home + '.*' + item)) !== null;
    });
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

let ctTrpIsInitialized = false;

document.addEventListener('DOMContentLoaded', function() {
    // Some plugins dispatch a synthetic DOMContentLoaded, badges must not be built twice
    if (ctTrpIsInitialized) {
        return;
    }
    ctTrpIsInitialized = true;

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

        let trpDescriptionContentFirstLine = document.createElement('div');
        trpDescriptionContentFirstLine.append(' ');
        trpDescriptionContentFirstLine.append(trpDescriptionHeading);
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
