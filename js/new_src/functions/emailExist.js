import {ctSetCookie, storeParams} from './storeParams';
import {sendAJAX, sendREST} from './ajaxHandlers';

const checkedEmailsExist = {};

export function startEmailExist() {
    storeParams([['ct_checked_emails_exist', '0']]);
    document.querySelectorAll('.comment-form input[name = "email"], input#email').forEach((elem) => {
        elem.addEventListener('blur', checkEmailExist);
    });
    document.querySelectorAll('.frm-fluent-form input[name = "email"], input#email').forEach((elem) => {
        elem.addEventListener('blur', checkEmailExist);
    });
    document.querySelectorAll('#registerform input[name = "user_email"]').forEach((elem) => {
        elem.addEventListener('blur', checkEmailExist);
    });
    document.querySelectorAll('form.wc-block-checkout__form input[type = "email"]').forEach((elem) => {
        elem.addEventListener('blur', checkEmailExist);
    });
    document.querySelectorAll('form.checkout input[type = "email"]').forEach((elem) => {
        elem.addEventListener('blur', checkEmailExist);
    });
    document.querySelectorAll('form.wpcf7-form input[type = "email"]').forEach((elem) => {
        elem.addEventListener('blur', debounceFuncExec(checkEmailExist, 300));
    });
    document.querySelectorAll('form.wpforms-form input[type = "email"]').forEach((elem) => {
        elem.addEventListener('blur', checkEmailExist);
    });
    document.querySelectorAll('form[id^="gform_"] input[type = "email"]').forEach((elem) => {
        elem.addEventListener('blur', checkEmailExist);
    });
    integrateDynamicEmailCheck({
        formSelector: '.nf-form-content',
        emailSelector: 'input[type="email"], input[type="email"].ninja-forms-field',
        handler: checkEmailExist,
        debounce: 300,
    });
}

/**
 * Execute function with timeout, listening incoming args from context.
 * @param {function} func Function to call
 * @param {number} wait Milliseconds to delay
 * @return {function}
 */
function debounceFuncExec(func, wait) {
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
 * @param {Event} e
 */
function checkEmailExist(e) {
    const currentEmail = e.target.value;

    if (!currentEmail || !currentEmail.length) {
        const envelope = document.getElementById('apbct-check_email_exist-block');
        if (envelope) {
            envelope.remove();
        }
        const hint = document.getElementById('apbct-check_email_exist-popup_description');
        if (hint) {
            hint.remove();
        }
        return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(currentEmail)) {
        return;
    }

    if (currentEmail in checkedEmailsExist) {
        getResultCheckEmailExist(e, checkedEmailsExist[currentEmail], currentEmail);

        return;
    }

    viewCheckEmailExist(e, 'load');

    const callback = function(result) {
        getResultCheckEmailExist(e, result, currentEmail);
    };

    if (ctPublicFunctions.data__ajax_type === 'rest') {
        sendREST(
            'check_email_exist_post',
            {
                method: 'POST',
                data: {'email': currentEmail},
                callback: callback,
            },
        );

        return;
    }

    if (ctPublicFunctions.data__ajax_type === 'admin_ajax') {
        sendAJAX(
            {
                action: 'apbct_email_check_exist_post',
                email: currentEmail,
            },
            {
                callback: callback,
            },
        );
    }
}

/**
 * @param {Event} e
 * @param {mixed} result
 * @param {string} currentEmail
 */
function getResultCheckEmailExist(e, result, currentEmail) {
    if (!result || !result.result) {
        return;
    }

    result = result.result;

    checkedEmailsExist[currentEmail] = {
        'result': result,
        'timestamp': Date.now() / 1000 | 0,
    };

    if (result.result == 'EXISTS') {
        viewCheckEmailExist(e, 'good_email', result.text_result);
    } else {
        viewCheckEmailExist(e, 'bad_email', result.text_result);
    }

    ctSetCookie('ct_checked_emails_exist', JSON.stringify(checkedEmailsExist));
}

/**
 * @param {Event} e
 * @param {string} state
 * @param {string} textResult
 */
function viewCheckEmailExist(e, state, textResult) {
    const parentElement = e.target.parentElement;
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
    setElementsPositions(inputEmail);

    // redraw on events
    listenRequiredRedrawing(inputEmail);

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
function setElementsPositions(inputEmail) {
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
        const inputComputedStyle = window.getComputedStyle(inputEmail);

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

        const inputTextSize = (
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
function listenRequiredRedrawing(inputEmail) {
    window.addEventListener('resize', function() {
        setElementsPositions(inputEmail);
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
        {
            'selector': 'form.hp-form--user-register',
            'observerConfig': {
                childList: true,
                subtree: true,
            },
            'emailElement': inputEmail,
        },
    ];

    formChangesToListen.forEach((aForm) => {
        watchFormChanges(
            aForm.selector,
            aForm.observerConfig,
            () => {
                setElementsPositions(aForm.emailElement);
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
function watchFormChanges(formSelector = '', observerConfig = null, callback) {
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
    return observer;
}

/**
 * Integrate dynamic email check to forms with dynamically added email inputs
 * @param {object} params
 * @param {string} params.formSelector
 * @param {string} params.emailSelector
 * @param {function} params.handler
 * @param {number} params.debounce
 * @param {string} params.attribute
 */
function integrateDynamicEmailCheck({
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
                input.addEventListener('blur', debounceFuncExec(handler, debounce));
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
                            node.addEventListener('blur', debounceFuncExec(handler, debounce));
                            node.setAttribute(attribute, '1');
                        }
                    }
                    // If there are email inputs inside the added node
                    node.querySelectorAll &&
                    node.querySelectorAll(emailSelector).forEach(function(input) {
                        // do not touch inputs outside of the integrated form
                        if (!input.closest(formSelector)) {
                            return;
                        }
                        if (!input.hasAttribute(attribute)) {
                            input.addEventListener('blur', debounceFuncExec(handler, debounce));
                            input.setAttribute(attribute, '1');
                        }
                    });
                }
            });
        });
    });
    observer.observe(document.body, {childList: true, subtree: true});
}
