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
 * Callbacks for FetchProxy integrations
 */
const ApbctFetchProxyCallbacks = { // eslint-disable-line no-unused-vars
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
 * Config for FetchProxy integrations
 */
const ApbctFetchProxyConfig = { // eslint-disable-line no-unused-vars
    'mailchimp': {
        selector: '.mcforms-wrapper',
        urlPattern: 'mcf-integrations-mcmktg.mlchmpcompprduse2.iks2.a.intuit.com/gateway/receive',
        externalForm: true,
        action: 'cleantalk_force_mailchimp_shadowroot_check',
        callbackAllow: false,
        callbackBlock: ApbctFetchProxyCallbacks.mailchimpBlock,
    },
    'otterform': {
        selector: '.otter-form__container',
        urlPattern: 'otter/v1/form/frontend',
        externalForm: false,
        action: 'cleantalk_force_otterform_check',
        callbackAllow: false,
        callbackBlock: false,
    },
    'elfsight': {
        selector: '[class*="elfsight-app"]',
        urlPattern: 'data.elfsight.com/public/resources/form-submissions',
        externalForm: true,
        action: 'cleantalk_force_elfsight_check',
        payloadKey: 'elfsight_payload',
        callbackAllow: false,
        callbackBlock: false,
    },
};

/**
 * Class for handling FetchProxy forms
 */
class ApbctFetchProxyProtection { // eslint-disable-line no-unused-vars
    /**
     * Constructor
     */
    constructor() {
        this.config = ApbctFetchProxyConfig;
    }

    /**
     * Find matching config for URL
     * @param {string|URL} url
     * @return {object|null} { formKey, config } or null
     */
    findMatchingConfig(url) {
        const urlStr = typeof url === 'string' ?
            url :
            (url != null && typeof url.href === 'string' ? url.href : '');

        for (const [formKey, config] of Object.entries(this.config)) {
            // FetchProxy can send both external and internal requests
            // If the form is external, then we check whether the setting is enabled.
            if (
                (!config.externalForm || +ctPublic.settings__forms__check_external) &&
                document.querySelectorAll(config.selector).length > 0 &&
                urlStr &&
                urlStr.includes(config.urlPattern)
            ) {
                return {formKey, config};
            }
        }
        return null;
    }

    /**
     * Check FetchProxy form request via CleanTalk AJAX
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
                if (config.payloadKey) {
                    data[config.payloadKey] = bodyText;
                } else {
                    for (const [key, value] of Object.entries(bodyObj)) {
                        data[key] = value;
                    }
                }
            } catch (e) {
                data.raw_body = bodyText;
            }

            if (+ctPublic.bot_detector_enabled) {
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
                        window.apbctShowBlockMessage(result);
                        if (typeof config.callbackBlock === 'function') {
                            config.callbackBlock(result);
                        }
                        resolve(true);
                        return;
                    }

                    resolve(false);
                },
                onErrorCallback: (error) => {
                    console.log('APBCT FetchProxy check error:', error);
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
     * Process fetch request for FetchProxy forms
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
     * Attach hidden fields to forms
     * @return {void}
     */
    attachHiddenFieldsToForms() {
        if (typeof ctPublic.force_alt_cookies == 'undefined' ||
            (ctPublic.force_alt_cookies !== 'undefined' && !ctPublic.force_alt_cookies)
        ) {
            ctNoCookieAttachHiddenFieldsToForms();
            document.addEventListener('gform_page_loaded', ctNoCookieAttachHiddenFieldsToForms);
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
     * Detect forms that need to be handled via alternative cookies
     * @return {void}
     */
    detectForcedAltCookiesForms() {
        let jetpackCommentsForm = document.querySelectorAll('iframe[name="jetpack_remote_comment"]').length > 0;
        ctPublic.force_alt_cookies = jetpackCommentsForm;

        /**
         * DO NOT ADD ANY NEW INTEGRATION IN THIS FLOW
         */

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
            document.querySelector('.et_pb_newsletter_form') !== null ||
            document.querySelector('form.mailpoet_form') !== null ||
            (
                // Back In Stock Notifier for WooCommerce | WooCommerce Waitlist Pro
                document.body.classList.contains('single-product') &&
                typeof cwginstock !== 'undefined'
            ) ||
            document.querySelector('div.fluent_booking_wrap') !== null || // Fluent Booking Pro
            document.querySelector('div.fcal_calendar_slot_wrap ') !== null || // Fluent Booking / Calendar Pro
            document.querySelectorAll('script[id*="smart-forms"]').length > 0 ||
            document.querySelector('[id^="amelia-app-booking"], .amelia-frontend, .amelia-v2-booking') !== null
        ) {
            const originalOpen = XMLHttpRequest.prototype.open;
            XMLHttpRequest.prototype.open = function(method, url, ...rest) {
                try {
                    this._apbctAjaxCallUrl = typeof url === 'string' ? url : '';
                } catch (e) {
                    // ignore
                }
                return originalOpen.call(this, method, url, ...rest);
            };

            const originalSend = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.send = function(body) {
                let isNeedToAddCleantalkDataCheckString = body && typeof body === 'string' &&
                    (
                        body.indexOf('action=wfu_ajax_action_ask_server') !== -1 ||
                        body.indexOf('action=booked_add_appt') !== -1 ||
                        body.indexOf('action=cwginstock_product_subscribe') !== -1 ||
                        body.indexOf('action=et_pb_submit_subscribe_form') !== -1 ||
                        body.indexOf('rednao_smart_forms_save_form_values') !== -1 ||
                        (
                            body.indexOf('action=mailpoet') !== -1 &&
                            body.indexOf('method=subscribe') !== -1
                        )
                    );
                const isDiviNewsletterRequest = body && typeof body === 'string' &&
                    body.indexOf('action=et_pb_submit_subscribe_form') !== -1;
                const isSmartFormsRequest = body && typeof body === 'string' &&
                    body.indexOf('rednao_smart_forms_save_form_values') !== -1;

                let isNeedToAddCleantalkDataCheckFormData = body && typeof body === 'object' &&
                    body instanceof FormData &&
                    (
                        body.has('action') && body.get('action') === 'fluent_cal_schedule_meeting'
                    );

                let isNeedToAddCleantalkDataCheckJsonData = body && typeof body === 'string' &&
                    (
                        // For Amelia booking integration
                        typeof this._apbctAjaxCallUrl === 'string' &&
                        this._apbctAjaxCallUrl.indexOf('action=wpamelia_api') !== -1
                    );

                if (isNeedToAddCleantalkDataCheckString) {
                    let addidionalCleantalkData = '';
                    const useUnwrappedCleantalkKeys = isDiviNewsletterRequest || isSmartFormsRequest;
                    const noCookieDataKey = useUnwrappedCleantalkKeys ?
                        'ct_no_cookie_hidden_field' :
                        'data%5Bct_no_cookie_hidden_field%5D';
                    const eventTokenKey = useUnwrappedCleantalkKeys ?
                        'ct_bot_detector_event_token' :
                        'data%5Bct_bot_detector_event_token%5D';
                    const browserStateKey = useUnwrappedCleantalkKeys ?
                        'apbct_browser_state' :
                        'data%5Bapbct_browser_state%5D';

                    if (!(
                        +ctPublic.bot_detector_enabled &&
                        apbctLocalStorage.get('bot_detector_event_token')
                    )) {
                        let noCookieData = getNoCookieData();
                        const hasNoCookieField =
                            body.indexOf('ct_no_cookie_hidden_field=') !== -1 ||
                            body.indexOf('data%5Bct_no_cookie_hidden_field%5D=') !== -1;
                        if (!hasNoCookieField) {
                            addidionalCleantalkData += '&' + noCookieDataKey + '=' + noCookieData;
                        }
                    } else {
                        const eventToken = new ApbctHandler().toolGetEventToken();
                        if (eventToken) {
                            const hasEventToken =
                                body.indexOf('ct_bot_detector_event_token=') !== -1 ||
                                body.indexOf('data%5Bct_bot_detector_event_token%5D=') !== -1;
                            if (!hasEventToken) {
                                addidionalCleantalkData += '&' + eventTokenKey + '=' + eventToken;
                            }
                        }
                    }

                    const browserState = apbctGetBrowserStatePair();
                    if (browserState && body.indexOf('apbct_browser_state') === -1) {
                        addidionalCleantalkData += '&' + browserStateKey + '=' +
                            encodeURIComponent(browserState.value);
                    }

                    body += addidionalCleantalkData;
                }

                if (isNeedToAddCleantalkDataCheckFormData) {
                    if (!(
                        +ctPublic.bot_detector_enabled &&
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

                    const browserState = apbctGetBrowserStatePair();
                    if (browserState && !body.has(browserState.key)) {
                        body.append(browserState.key, browserState.value);
                    }
                }

                if (isNeedToAddCleantalkDataCheckJsonData) {
                    if (!(
                        +ctPublic.bot_detector_enabled &&
                        apbctLocalStorage.get('bot_detector_event_token')
                    )) {
                        let noCookieData = getNoCookieData();
                        try {
                            const bodyObj = JSON.parse(body);
                            if (
                                bodyObj && typeof bodyObj === 'object' &&
                                !Object.prototype.hasOwnProperty.call(bodyObj, 'ct_no_cookie_hidden_field')
                            ) {
                                bodyObj.ct_no_cookie_hidden_field = noCookieData;
                                body = JSON.stringify(bodyObj);
                            }
                        } catch (e) {
                            // body is not JSON — leave as is
                        }
                    } else {
                        const eventToken = new ApbctHandler().toolGetEventToken();
                        if (eventToken) {
                            try {
                                const bodyObj = JSON.parse(body);
                                if (
                                    bodyObj && typeof bodyObj === 'object' &&
                                    !Object.prototype.hasOwnProperty.call(bodyObj, 'ct_bot_detector_event_token')
                                ) {
                                    bodyObj.ct_bot_detector_event_token = eventToken;
                                    body = JSON.stringify(bodyObj);
                                }
                            } catch (e) {
                                // body is not JSON — leave as is
                            }
                        }
                    }

                    const browserState = apbctGetBrowserStatePair();
                    if (browserState) {
                        try {
                            const bodyObj = JSON.parse(body);
                            if (
                                bodyObj && typeof bodyObj === 'object' &&
                                !Object.prototype.hasOwnProperty.call(bodyObj, browserState.key)
                            ) {
                                bodyObj[browserState.key] = browserState.value;
                                body = JSON.stringify(bodyObj);
                            }
                        } catch (e) {
                            // body is not JSON — leave as is
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
                                    +ctPublic.bot_detector_enabled &&
                                    apbctLocalStorage.get('bot_detector_event_token')
                                ) {
                                    args[1].body.append(
                                        'ct_bot_detector_event_token',
                                        apbctLocalStorage.get('bot_detector_event_token'),
                                    );
                                } else {
                                    args[1].body.append('ct_no_cookie_hidden_field', getNoCookieData());
                                }

                                const browserState = apbctGetBrowserStatePair();
                                if (browserState) {
                                    args[1].body.append(browserState.key, browserState.value);
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
                const handler = new ApbctHandler();
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
            const token = this.toolGetEventToken();
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
                const token = this.toolGetEventToken();
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

            // add to cart (batch uses body not data for request payload)
            if (options.data.hasOwnProperty('requests') &&
                options.data.requests.length > 0 &&
                options.data.requests[0].hasOwnProperty('path') &&
                options.data.requests[0].path === '/wc/store/v1/cart/add-item'
            ) {
                const reqBody = options.data.requests[0].body || (options.data.requests[0].body = {});
                if (
                    +ctPublic.bot_detector_enabled &&
                    apbctLocalStorage.get('bot_detector_event_token')
                ) {
                    reqBody.ct_bot_detector_event_token = localStorage.getItem('bot_detector_event_token');
                } else {
                    if (ctPublic.data__cookies_type === 'none') {
                        reqBody.ct_no_cookie_hidden_field = getNoCookieData();
                    }
                }
            }

            // checkout
            if (options.path.includes('/wc/store/v1/checkout')) {
                if (
                    +ctPublic.bot_detector_enabled &&
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
            const honeyPotField = targetForm.querySelector('[name*="apbct__email_id__"]');
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
    handler.detectForcedAltCookiesForms();

    setTimeout(function() {
        // Attach data when bot detector is enabled
        if (+ctPublic.bot_detector_enabled) {
            const eventTokenTransport = new ApbctEventTokenTransport();
            eventTokenTransport.attachEventTokenToMultipageGravityForms();
            eventTokenTransport.attachEventTokenToWoocommerceGetRequestAddToCart();
        }

        const attachData = new ApbctAttachData();

        // Attach data when bot detector is disabled or blocked
        if (!+ctPublic.bot_detector_enabled || window.apbctGatheringLoaded) {
            attachData.attachHiddenFieldsToForms();
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

    if (+ctPublic.bot_detector_enabled) {
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
