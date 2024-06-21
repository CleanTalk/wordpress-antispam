(async function() {
    let apbctAmpState = await AMP.getState('apbctAmpState');
    console.log(apbctAmpState);
    // alert(1);

    //
    // apbctAmpState = JSON.parse(apbctAmpState);

    // const emailEncoderState = apbctAmpState.emailEncoderState;

    // Listen clicks on encoded emails
    let encodedEmailNodes = document.querySelectorAll('[data-original-string]');

    if (encodedEmailNodes.length) {
        for (let i = 0; i < encodedEmailNodes.length; ++i) {
            console.log(encodedEmailNodes[i]);
            if (
                encodedEmailNodes[i].parentElement !== undefined &&
                encodedEmailNodes[i].parentElement.hasAttribute('href') &&
                encodedEmailNodes[i].parentElement.href
            ) {
                continue;
            }
            if (
                encodedEmailNodes[i].parentElement !== undefined &&
                encodedEmailNodes[i].parentElement.parentElement !== undefined &&
                encodedEmailNodes[i].parentElement.parentElement.hasAttribute('href') &&
                encodedEmailNodes[i].parentElement.parentElement.href
            ) {
                continue;
            }
            encodedEmailNodes[i].addEventListener('click', ctFillDecodedEmailHandler);
        }
    }

    function ctFillDecodedEmailHandler(event) {
        this.removeEventListener('click', ctFillDecodedEmailHandler);
        console.table('CLIKC!',1);

        // @ToDo do email decoding here
    }
})();


/**
 * @param {mixed} event
 */
function ctFillDecodedEmailHandler(event) {
    this.removeEventListener('click', ctFillDecodedEmailHandler);
    // remember clickSource
    let clickSource = this;
    // globally remember if emails is mixed with mailto
    ctPublic.encodedEmailNodesIsMixed = false;
    // get fade
    document.body.classList.add('apbct-popup-fade');
    // popup show
    let encoderPopup = document.getElementById('apbct_popup');
    if (!encoderPopup) {
        let waitingPopup = document.createElement('div');
        waitingPopup.setAttribute('class', 'apbct-popup');
        waitingPopup.setAttribute('id', 'apbct_popup');
        let popupText = document.createElement('p');
        popupText.setAttribute('id', 'apbct_popup_text');
        popupText.style.color = 'black';
        popupText.innerText = 'Please wait while ' + ctPublic.wl_brandname + ' is decoding the email addresses.';
        waitingPopup.append(popupText);
        document.body.append(waitingPopup);
    } else {
        encoderPopup.setAttribute('style', 'display: inherit');
        document.getElementById('apbct_popup_text').innerHTML =
            'Please wait while ' + ctPublic.wl_brandname + ' is decoding the email addresses.';
    }

    apbctAjaxEmailDecodeBulk(event, ctPublic.encodedEmailNodes, clickSource);
}

/**
 * @param {mixed} event
 * @param {mixed} encodedEmailNodes
 * @param {mixed} clickSource
 */
function apbctAjaxEmailDecodeBulk(event, encodedEmailNodes, clickSource) {
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
        if (typeof encodedEmailNodes[i].href !== 'undefined' && encodedEmailNodes[i].href.indexOf('mailto:') === 0) {
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
                    ctSetCookie('apbct_email_encoder_passed', ctPublic.emailEncoderPassKey);
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
                    ctSetCookie('apbct_email_encoder_passed', ctPublic.emailEncoderPassKey);
                    apbctEmailEncoderCallbackBulk(result, encodedEmailNodes, clickSource);
                },
                onErrorCallback: function(res) {
                    resetEncodedNodes();
                    ctShowDecodeComment(res);
                },
            },
        );
    }
}

/**
 * @param {mixed} result
 * @param {mixed} encodedEmailNodes
 * @param {mixed} clickSource
 */
function apbctEmailEncoderCallbackBulk(result, encodedEmailNodes, clickSource) {
    if (result.success && result.data[0].is_allowed === true) {
        // start process of visual decoding
        setTimeout(function() {
            for (let i = 0; i < encodedEmailNodes.length; i++) {
                // chek what is what
                let currentResultData;
                result.data.forEach((row) => {
                    if (row.encoded_email === encodedEmailNodes[i].dataset.originalString) {
                        currentResultData = row;
                    }
                });
                // quit case on cloud block
                if (currentResultData.is_allowed === false) {
                    break;
                }
                // handler for mailto
                if (
                    typeof encodedEmailNodes[i].href !== 'undefined' &&
                    encodedEmailNodes[i].href.indexOf('mailto:') === 0) {
                    let encodedEmail = encodedEmailNodes[i].href.replace('mailto:', '');
                    let baseElementContent = encodedEmailNodes[i].innerHTML;
                    encodedEmailNodes[i].innerHTML =
                        baseElementContent.replace(encodedEmail, currentResultData.decoded_email);
                    encodedEmailNodes[i].href = 'mailto:' + currentResultData.decoded_email;
                } else {
                    // fill the nodes
                    ctProcessDecodedDataResult(currentResultData, encodedEmailNodes[i]);
                }
                // remove listeners
                encodedEmailNodes[i].removeEventListener('click', ctFillDecodedEmailHandler);
            }
            // popup remove
            let popup = document.getElementById('apbct_popup');
            if (popup !== null) {
                document.body.classList.remove('apbct-popup-fade');
                popup.setAttribute('style', 'display:none');
                // click on mailto if so
                if (ctPublic.encodedEmailNodesIsMixed) {
                    clickSource.click();
                }
            }
        }, 3000);
    } else {
        if (result.success) {
            resetEncodedNodes();
            ctShowDecodeComment('Blocked: ' + result.data[0].comment);
        } else {
            resetEncodedNodes();
            ctShowDecodeComment('Cannot connect with CleanTalk server: ' + result.data[0].comment);
        }
    }
}

/**
 * resetEncodedNodes
 */
function resetEncodedNodes() {
    if (typeof ctPublic.encodedEmailNodes !== 'undefined') {
        ctPublic.encodedEmailNodes.forEach(function(element) {
            element.addEventListener('click', ctFillDecodedEmailHandler);
        });
    }
}
