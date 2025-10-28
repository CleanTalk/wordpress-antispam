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
    console.log(e);
    console.log(state);
    console.log(textResult);
    
    let parentElement = e.target.parentElement;
    let inputEmail = parentElement.querySelector('[name*="email"]');

    console.log(parentElement);


    if (!inputEmail) {
        inputEmail = parentElement.querySelector('[type*="email"]');
    }
    console.log(inputEmail);

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
    try {
        let inputComputedStyle = window.getComputedStyle(inputEmail);
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
        envelope.style.cssText = `
            top: ${inputRect.top}px;
            left: ${inputRect.right - envelopeWidth}px;
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
