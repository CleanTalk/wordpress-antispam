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
