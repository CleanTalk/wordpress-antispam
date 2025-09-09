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
    if (ctPublic.settings__data__bot_detector_enabled != 1) {
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
        'frmCalc', // nobletitle-calc
        'ihf-contact-request-form',
        'wpforms', // integration with wpforms
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
    const cleantalkPlaceholder = document.createElement('i');
    cleantalkPlaceholder.className = 'cleantalk_placeholder';
    cleantalkPlaceholder.style = 'display: none';

    currentForm.parentElement.insertBefore(cleantalkPlaceholder, currentForm);

    // Deleting form to prevent submit event
    const prev = currentForm.previousSibling;
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
    const submitButtonOriginal = currentForm.querySelector('[type="submit"]');
    const onsubmitOriginal = currentForm.querySelector('[type="submit"]').form.onsubmit;

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
                        formSource.outerHTML.indexOf('action="https://crm.zoho.com') !== -1
                    ) &&
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

    setTimeout(function() {
        ctProtectExternal();
        catchDynamicRenderedForm();
        catchNextendSocialLoginForm();
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
                entity.classList.contains('hs-form-iframe') ||
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
    entityParent.appendChild(ctProtectOutsideFunctionalGenerateCover());
    let entitiesProtected = apbctLocalStorage.get(lsStorageName);
    if (false === entitiesProtected) {
        entitiesProtected = [];
    }
    if (lsUniqueName) {
        entitiesProtected.push(lsUniqueName);
        apbctLocalStorage.set(lsStorageName, entitiesProtected);
    }
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
    btn.disabled = true;

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
    const visibleFields = {};
    const attachData = new ApbctAttachData();
    visibleFields[0] = attachData.collectVisibleFields(form);
    attachData.setVisibleFieldsCookie(visibleFields);

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
                    new ApbctHandler().parseBlockMessage(result);
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
    const attachData = new ApbctAttachData();
    visibleFields[0] = attachData.collectVisibleFields(form);
    attachData.setVisibleFieldsCookie(visibleFields);
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
                    new ApbctHandler().parseBlockMessage(result);
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
