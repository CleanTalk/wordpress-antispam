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
            } else if ( currentForm.dataset.mailingListId !== undefined ) { // MooForm 3rd party service
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
    ctStartFieldsListening();
}

/**
 * Exclusion forms
 * @param {HTMLElement} currentForm
 * @return {boolean}
 */
function formIsExclusion(currentForm) {
    const exclusionsById = [
        'give-form', // give form exclusion because of direct integration
        'frmCalc', // nobletitle-calc
        'ihf-contact-request-form',
    ];

    const exclusionsByRole = [
        'search', // search forms
    ];

    const exclusionsByClass = [
        'search-form', // search forms
        'hs-form', // integrated hubspot plugin through dynamicRenderedForms logic
        'ihc-form-create-edit', // integrated Ultimate Membership Pro plugin through dynamicRenderedForms logic
    ];

    let result = false;

    try {
        // mewto forms exclusion
        if (currentForm.parentElement &&
            currentForm.parentElement.classList.length > 0 &&
            currentForm.parentElement.classList[0].indexOf('mewtwo') !== -1) {
            result = true;
        }

        exclusionsById.forEach(function(exclusionId) {
            const formId = currentForm.getAttribute('id');
            if ( formId !== null && typeof (formId) !== 'undefined' && formId.indexOf(exclusionId) !== -1 ) {
                result = true;
            }
        });

        exclusionsByClass.forEach(function(exclusionClass) {
            const formClass = currentForm.getAttribute('class');
            if ( formClass !== null && typeof formClass !== 'undefined' && formClass.indexOf(exclusionClass) !== -1 ) {
                result = true;
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
                apbctProcessExternalForm(currentForm, y, frames[j].contentDocument);
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
    // skip excluded forms
    if ( formIsExclusion(currentForm)) {
        return;
    }

    const cleantalkPlaceholder = document.createElement('i');
    cleantalkPlaceholder.className = 'cleantalk_placeholder';
    cleantalkPlaceholder.style = 'display: none';

    currentForm.parentElement.insertBefore(cleantalkPlaceholder, currentForm);

    // Deleting form to prevent submit event
    const prev = currentForm.previousSibling;
    const formHtml = currentForm.outerHTML;
    const formOriginal = currentForm;

    // Remove the original form
    currentForm.parentElement.removeChild(currentForm);

    // Insert a clone
    const placeholder = document.createElement('div');
    placeholder.innerHTML = formHtml;
    prev.after(placeholder.firstElementChild);

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
    if ( mailerliteDetectedClass ) {
        const mailerliteSubmitButton = documentObject.querySelector('form.' + mailerliteDetectedClass)
            .querySelector('button[type="submit"]');
        if ( mailerliteSubmitButton !== undefined ) {
            mailerliteSubmitButton.addEventListener('click', function(event) {
                event.preventDefault();
                sendAjaxCheckingFormData(reUseCurrentForm);
            });
        }
    } else {
        documentObject.forms[iterator].onsubmit = function(event) {
            event.preventDefault();
            sendAjaxCheckingFormData(event.currentTarget);
        };
    }
}

/**
 * Process external forms via fake button replacing
 * @param {HTMLElement} currentForm
 * @param {int} iterator
 * @param {HTMLElement} documentObject
 */
function apbctProcessExternalFormByFakeButton(currentForm, iterator, documentObject) {
    // skip excluded forms
    if ( formIsExclusion(currentForm)) {
        return;
    }

    const submitButtonOriginal = currentForm.querySelector('[type="submit"]');

    if ( ! submitButtonOriginal ) {
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

    documentObject.forms[iterator].onsubmit = function(event) {
        event.preventDefault();
        sendAjaxCheckingFormData(event.currentTarget);
    };
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
            if (elemSource.outerHTML === elemTarget.outerHTML) {
                if (elemTarget.type === 'checkbox' || elemTarget.type === 'radio') {
                    elemTarget.checked = apbctVal(elemSource);
                } else {
                    elemTarget.value = apbctVal(elemSource);
                }
            }
        });
    });
}

window.onload = function() {

    if ( ! +ctPublic.settings__forms__check_external ) {
        return;
    }

    setTimeout(function() {
        ctProtectExternal();
        catchDynamicRenderedForm();
        catchNslForm();
    }, 2000);
};

/**
 * Catch Nsl form integration
 */
function catchNslForm() {
    let blockNSL = document.getElementById("nsl-custom-login-form-main");
    if (blockNSL){
        checkNextendSocialLogin(blockNSL);
    }
}

/**
 * Blocking NSL buttons and callback functions
 * @param {HTMLElement} blockNSL 
 */
function checkNextendSocialLogin(blockNSL) {
    let btnsNSL = blockNSL.querySelectorAll('.nsl-container-buttons a');
    btnsNSL.forEach(el => {
        el.setAttribute('data-oauth-login-blocked', 'true');
        el.style.pointerEvents = 'none';

        el.parentElement.onclick = function(e) {
            e.preventDefault();

            let allow = function(target) {
                if (document.querySelector('.ct-forbidden-msg')){
                    document.querySelector('.ct-forbidden-msg').remove();
                }

                if (target.getAttribute('data-oauth-login-blocked') == 'true') {
                    target.setAttribute('data-oauth-login-blocked', 'false');
                    el.setAttribute('style', 'pointer-events: all;');
                    el.click();
                    el.style.pointerEvents = 'none';
                }
            };

            let forbidden = function(target, msg) {
                if (target.getAttribute('data-oauth-login-blocked') == 'false') {
                    target.setAttribute('data-oauth-login-blocked', 'true');
                    el.setAttribute('style', 'pointer-events: none;');
                }
                if (!document.querySelector('.ct-forbidden-msg')){
                    let el = document.createElement('div');
                    el.className = 'ct-forbidden-msg';
                    el.style.background = 'red';
                    el.style.color = 'white';
                    el.style.padding = '5px';
                    el.innerHTML = msg;
                    target.insertAdjacentElement('beforebegin', el);
                }
            };

            ctCheckAjax(el, allow, forbidden);
        };
    });
}

/**
 * User verification using user data and ajax
 * @param {HTMLElement} elem 
 * @param {Function} allow 
 * @param {Function} forbidden 
 */
function ctCheckAjax(elem, allow, forbidden) {
    let data = {
        'action': 'cleantalk_nsl_ajax_check',
        'ct_no_cookie_hidden_field': document.getElementsByName('ct_no_cookie_hidden_field')[0].value,
    };

    apbct_public_sendAJAX(
        data,
        {
            async: false,
            callback: function(result) {
                if (result.apbct.blocked === false){
                    allow(elem);
                } else {
                    forbidden(elem, result.apbct.comment);
                }
            },
        }
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

    if (
        formAction.indexOf('activehosted.com') !== -1 || // ActiveCampaign form
        formAction.indexOf('app.convertkit.com') !== -1 || // ConvertKit form
        ( formObj.firstChild.classList !== undefined &&
        formObj.firstChild.classList.contains('cb-form-group') ) || // Convertbox form
        formAction.indexOf('mailerlite.com') !== -1 || // Mailerlite integration
        formAction.indexOf('colcolmail.co.uk') !== -1 || // colcolmail.co.uk integration
        formAction.indexOf('paypal.com') !== -1 ||
        formAction.indexOf('infusionsoft.com') !== -1 ||
        formAction.indexOf('webto.salesforce.com') !== -1 ||
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
        formObj.hasAttribute('data-hs-cf-bound') // Hubspot integration in Elementor form
    ) {
        return true;
    }

    return false;
}

/**
 * Sending Ajax for checking form data
 * @param {HTMLElement} form
 * @param {HTMLElement} prev
 * @param {HTMLElement} formOriginal
 */
function sendAjaxCheckingFormData(form) {
    // Get visible fields and set cookie
    const visibleFields = {};
    visibleFields[0] = apbct_collect_visible_fields(form);
    apbct_visible_fields_set_cookie( visibleFields );

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
            callback: function( result, data, params, obj ) {
                if ( result.apbct === undefined || ! +result.apbct.blocked ) {
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

                    const formNew = form;
                    form.parentElement.removeChild(form);
                    const prev = form.apbctPrev;
                    const formOriginal = form.apbctFormOriginal;
                    let mauticIntegration = false;

                    apbctReplaceInputsValuesFromOtherForm(formNew, formOriginal);

                    // mautic forms integration
                    if (formOriginal.id.indexOf('mautic') !== -1) {
                        mauticIntegration = true;
                    }

                    prev.after( formOriginal );

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
                        if (mauticIntegration) {
                            setTimeout(function() {
                                ctProtectExternal();
                            }, 1500);
                        }
                        return;
                    }

                    submButton = formOriginal.querySelectorAll('input[type=submit]');
                    if ( submButton.length !== 0 ) {
                        submButton[0].click();
                        return;
                    }

                    // ConvertKit direct integration
                    submButton = formOriginal.querySelectorAll('button[data-element="submit"]');
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
                if (result.apbct !== undefined && +result.apbct.blocked) {
                    ctParseBlockMessage(result);
                }
            },
        });
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
 * Process dynamic rendered form
 * @param {HTMLElements} forms
 * @param {HTMLElement} documentObject
 */
function catchDynamicRenderedFormHandler(forms, documentObject = document) {
    const neededFormIds = [];
    for (const form of forms) {
        if (form.id.indexOf('hsForm') !== -1) {
            neededFormIds.push(form.id);
        }
        if (form.id.indexOf('createuser') !== -1 &&
        (form.classList !== undefined && form.classList.contains('ihc-form-create-edit'))
        ) {
            neededFormIds.push(form.id);
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
    const visibleFields = {};
    visibleFields[0] = apbct_collect_visible_fields(form);
    apbct_visible_fields_set_cookie(visibleFields);
    form.append(ctNoCookieConstructHiddenField('hidden'));

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
                        setTimeout(function() {
                            form.apbct_external_onsubmit_prev.call(form, formEvent);
                        }, 500);
                    }

                    const submButton = form.querySelector('input[type="submit"]');
                    if (submButton) {
                        submButton.click();
                        return;
                    }
                }

                if (result.apbct !== undefined && +result.apbct.blocked) {
                    ctParseBlockMessage(result);
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
