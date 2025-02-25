jQuery(document).ready(function($) {
    // Auto update banner close handler
    jQuery('.apbct_update_notice').on('click', 'button', function() {
        let ctDate = new Date(new Date().getTime() + 1000 * 86400 * 30 );
        let ctSecure = location.protocol === 'https:' ? '; secure' : '';
        document.cookie = 'apbct_update_banner_closed=1; path=/; expires=' +
        ctDate.toUTCString() + '; samesite=lax' + ctSecure;
    });

    jQuery('li a[href="options-general.php?page=cleantalk"]').css('white-space', 'nowrap')
        .css('display', 'inline-block');

    jQuery('body').on('click', '.apbct-notice .notice-dismiss-link', function(e) {
        jQuery(e.target).parent()
            .parent('.notice')
            .after('<div id="apbct-notice-dismiss-success" class="notice notice-success is-dismissible"><p>' +
                ctAdminCommon.apbctNoticeDismissSuccess +
                '</p></div>');
        setTimeout(function() {
            jQuery('#apbct-notice-dismiss-success').fadeOut();
        }, 2000);
        jQuery(e.target).parent().siblings('.apbct-notice .notice-dismiss').click();
    });
    jQuery('body').on('click', '.apbct-notice .notice-dismiss', function(e) {
        let apbctNoticeName = jQuery(e.target).parent().attr('id');
        if ( apbctNoticeName ) {
            apbct_admin_sendAJAX(
                {
                    'action': 'cleantalk_dismiss_notice',
                    'notice_id': apbctNoticeName,
                },
                {
                    'callback': null,
                    'notJson': true,
                },
            );
        }
    });

    // Notice when deleting user
    jQuery('.ct_username .row-actions .delete a').on('click', function(e) {
        e.preventDefault();

        let result = confirm(ctAdminCommon.notice_when_deleting_user_text);

        if (result) {
            window.location = this.href;
        }
    });

    let btnForceProtectionOn = document.querySelector('#apbct_setting_forms__force_protection__On');
    if (btnForceProtectionOn) {
        btnForceProtectionOn.addEventListener('click', function(e) {
            if (btnForceProtectionOn.checked) {
                let result = confirm(ctAdminCommon.apbctNoticeForceProtectionOn);

                if (!result) {
                    e.preventDefault();
                }
            }
        });
    }
    // Restore spam order
    $('.apbct-restore-spam-order-button').click(function() {
        const spmOrderId = $(this).data('spam-order-id');
        let data = {
            action: 'apbct_restore_spam_order',
            _ajax_nonce: ctAdminCommon._ajax_nonce,
            order_id: spmOrderId,
        };
        $.ajax({
            type: 'POST',
            url: ctAdminCommon._ajax_url,
            data: data,
            success: function(result) {
                if (result.success) {
                    window.location.reload();
                } else {
                    alert(result.data.message);
                }
            },
        });
    });

    // Email decoder example
    if (window.location.href.includes('options-general.php?page=cleantalk')) {
        let encodedEmailNode = document.querySelector('[data-original-string]');
        if (encodedEmailNode) {
            ctAdminCommon.encodedEmailNode = encodedEmailNode;
            encodedEmailNode.style.cursor = 'pointer';
            encodedEmailNode.addEventListener('click', ctFillDecodedEmailHandler);
        }
    }

    ctDecorationSelectorActions();
});

/**
 * @param {mixed} event
 */
function ctFillDecodedEmailHandler() {
    document.body.classList.add('apbct-popup-fade');
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
        popupHeader.innerText = ctAdminCommon.plugin_name;
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
        popupTextWaiting.innerText = 'The magic is on the way, please wait for a few seconds!';
        popupTextWaiting.setAttribute('class', 'apbct-email-encoder-elements_center');

        // construct text second node
        // todo make translatable
        let popupTextDecoding = document.createElement('p');
        popupTextDecoding.id = 'apbct_email_ecoder__popup_text_node_second';
        popupTextDecoding.innerText = 'Decoding process to the original data.';

        // appending
        popupTextWrapper.append(popupTextWaiting);
        popupTextWrapper.append(popupTextDecoding);
        waitingPopup.append(popupHeaderWrapper);
        waitingPopup.append(popupTextWrapper);
        waitingPopup.append(apbctSetEmailDecoderPopupAnimation());
        document.body.append(waitingPopup);
    } else {
        encoderPopup.setAttribute('style', 'display: inherit');
        document.getElementById('apbct_popup_text').innerHTML =
            'Please wait while ' + ctAdminCommon.plugin_name + ' is decoding the email addresses.';
    }

    apbctAjaxEmailDecodeBulk(ctAdminCommon.encodedEmailNode);
}
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
 * @param {HTMLElement} encodedEmailNode
 */
function apbctAjaxEmailDecodeBulk(encodedEmailNode) {
    const encodedEmail = encodedEmailNode.dataset.originalString;
    let data = {
        encodedEmails: JSON.stringify({0: encodedEmail}),
    };

    // Adding a tooltip
    let apbctTooltip = document.createElement('div');
    apbctTooltip.setAttribute('class', 'apbct-tooltip');
    jQuery(encodedEmailNode).append(apbctTooltip);

    apbct_admin_sendAJAX(
        {
            'action': 'apbct_decode_email',
            'encodedEmails': data.encodedEmails,
        },
        {
            'callback': function(result) {
                apbctEmailEncoderCallbackBulk(result, encodedEmailNode);
            },
            'notJson': true,
        },
    );
}

/**
 * @param {mixed} result
 * @param {mixed} encodedEmailNode
 */
function apbctEmailEncoderCallbackBulk(result, encodedEmailNode) {
    if (result.success && result.data[0].is_allowed === true) {
        // start process of visual decoding
        setTimeout(function() {
            let email = result.data[0].decoded_email;

            // change text
            let popup = document.getElementById('apbct_popup');
            if (popup !== null) {
                // handle first node
                let firstNode = popup.querySelector('#apbct_email_ecoder__popup_text_node_first');
                // get email selectable by click
                let selectableEmail = document.createElement('b');
                selectableEmail.setAttribute('class', 'apbct-email-encoder-select-whole-email');
                selectableEmail.innerText = email;
                selectableEmail.title = 'Click to select the whole data';
                // add email to the first node
                firstNode.innerHTML = 'The original one is&nbsp;' + selectableEmail.outerHTML;
                firstNode.setAttribute('style', 'flex-direction: row;');
                // handle second node
                let secondNode = popup.querySelector('#apbct_email_ecoder__popup_text_node_second');
                secondNode.innerText = 'Happy conversations!';
                // remove animation
                popup.querySelector('.apbct-ee-animation-wrapper').remove();
                // remove second node
                popup.querySelector('#apbct_email_ecoder__popup_text_node_second').remove();
                // add button
                let buttonWrapper = document.createElement('span');
                buttonWrapper.classList = 'apbct-email-encoder-elements_center top-margin-long';
                let button = document.createElement('button');
                button.innerText = 'Got it';
                button.classList = 'apbct-email-encoder-got-it-button';
                button.addEventListener('click', function() {
                    document.body.classList.remove('apbct-popup-fade');
                    popup.setAttribute('style', 'display:none');
                    fillDecodedEmails(encodedEmailNode, result);
                });
                buttonWrapper.append(button);
                popup.append(buttonWrapper);
            }
        }, 3000);
    } else {
        console.log('result', result);
    }
}

/**
 * Run filling for every node with decoding result.
 * @param {mixed} encodedEmailNode
 * @param {mixed} decodingResult
 */
function fillDecodedEmails(encodedEmailNode, decodingResult) {
    let currentResultData = decodingResult.data[0].decoded_email;
    encodedEmailNode.classList.add('no-blur');
    // fill the nodes
    setTimeout(() => {
        encodedEmailNode.setAttribute('title', '');
        encodedEmailNode.removeAttribute('style');
        ctFillDecodedEmail(encodedEmailNode, currentResultData);
    }, 2000);
    encodedEmailNode.removeEventListener('click', ctFillDecodedEmailHandler);
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

// eslint-disable-next-line camelcase,require-jsdoc,no-unused-vars
function apbct_admin_sendAJAX(data, params, obj) {
    // Default params
    let callback = params.callback || null;
    let callbackContext = params.callback_context || null;
    let callbackParams = params.callback_params || null;
    let async = params.async || true;
    let notJson = params.notJson || null;
    let timeout = params.timeout || 15000;
    var obj = obj || null; // eslint-disable-line no-var
    let button = params.button || null;
    let spinner = params.spinner || null;
    let progressbar = params.progressbar || null;

    if (typeof (data) === 'string') {
        data = data + '&_ajax_nonce=' + ctAdminCommon._ajax_nonce + '&no_cache=' + Math.random();
    } else {
        data._ajax_nonce = ctAdminCommon._ajax_nonce;
        data.no_cache = Math.random();
    }
    // Button and spinner
    if (button) {
        button.setAttribute('disabled', 'disabled'); button.style.cursor = 'not-allowed';
    }
    if (spinner) jQuery(spinner).css('display', 'inline');

    jQuery.ajax({
        type: 'POST',
        url: ctAdminCommon._ajax_url,
        data: data,
        async: async,
        success: function(result) {
            if (button) {
                button.removeAttribute('disabled'); button.style.cursor = 'pointer';
            }
            if (spinner) jQuery(spinner).css('display', 'none');
            if (!notJson) result = JSON.parse(result);
            if (result.error) {
                setTimeout(function() {
                    if (progressbar) progressbar.fadeOut('slow');
                }, 1000);
                if ( typeof cleantalkModal !== 'undefined' ) {
                    // Show the result by modal
                    cleantalkModal.loaded = 'Error:<br>' + result.error.toString();
                    cleantalkModal.open();
                } else {
                    alert('Error happens: ' + (result.error || 'Unkown'));
                }
            } else {
                if (callback) {
                    if (callbackParams) {
                        callback.apply( callbackContext, callbackParams.concat( result, data, params, obj ) );
                    } else {
                        callback(result, data, params, obj);
                    }
                }
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            if (button) {
                button.removeAttribute('disabled'); button.style.cursor = 'pointer';
            }
            if (spinner) jQuery(spinner).css('display', 'none');
            console.log('APBCT_AJAX_ERROR');
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        timeout: timeout,
    });
}
/**
* @return {void}
 */
function ctDecorationSelectorActions() {
    const selector = document.querySelector('#apbct_setting_comments__form_decoration_selector');
    const colorPicker = document.querySelector('#apbct_setting_comments__form_decoration_color');
    const headingText = document.querySelector('#apbct_setting_comments__form_decoration_text');
    const defaultThemeExpectedValue = 'Default Theme';
    if (colorPicker && headingText && selector) {
        if (selector.value === defaultThemeExpectedValue) {
            colorPicker.setAttribute('disabled', 'disabled');
            headingText.setAttribute('disabled', 'disabled');
        }
        selector.addEventListener('change', function(event) {
            const selectedValue = event.target.value;
            if (selectedValue && selectedValue.length > 0 && selectedValue === defaultThemeExpectedValue) {
                colorPicker.setAttribute('disabled', 'disabled');
                headingText.setAttribute('disabled', 'disabled');
            } else {
                colorPicker.removeAttribute('disabled');
                headingText.removeAttribute('disabled');
            }
        });
    }
}
