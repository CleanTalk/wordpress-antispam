/**
 * Handle external forms
 */
function ct_protect_external() {
    for(var i = 0; i < document.forms.length; i++) {

        if (document.forms[i].cleantalk_hidden_action === undefined && document.forms[i].cleantalk_hidden_method === undefined) {

            // current form
            var currentForm = document.forms[i];

            if(typeof(currentForm.action) == 'string') {

                //skip excluded forms
                if ( formIsExclusion(currentForm)) {
                    continue;
                }

                // Ajax checking for the integrated forms
                if ( isIntegratedForm(currentForm) ) {

                    apbctProcessExternalForm(currentForm, i, document);

                    // Common flow - modify form's action
                }else if(currentForm.action.indexOf('http://') !== -1 || currentForm.action.indexOf('https://') !== -1) {

                    var tmp = currentForm.action.split('//');
                    tmp = tmp[1].split('/');
                    var host = tmp[0].toLowerCase();

                    if(host !== location.hostname.toLowerCase()){

                        var ct_action = document.createElement("input");
                        ct_action.name = 'cleantalk_hidden_action';
                        ct_action.value = currentForm.action;
                        ct_action.type = 'hidden';
                        currentForm.appendChild(ct_action);

                        var ct_method = document.createElement("input");
                        ct_method.name = 'cleantalk_hidden_method';
                        ct_method.value = currentForm.method;
                        ct_method.type = 'hidden';

                        currentForm.method = 'POST'

                        currentForm.appendChild(ct_method);

                        currentForm.action = document.location;
                    }
                }
            }
        }

    }
    // Trying to process external form into an iframe
    apbctProcessIframes()
}

function formIsExclusion(currentForm)
{
    let exclusions_by_id = [
        'give-form', //give form exclusion because of direct integration
        'frmCalc' //nobletitle-calc
    ]

    let exclusions_by_role = [
        'search' //search forms
    ]

    let exclusions_by_class = [
        'search-form', //search forms
        'hs-form', // integrated hubspot plugin through dinamicRenderedForms logic
        'ihc-form-create-edit' // integrated Ultimate Membership Pro plugin through dinamicRenderedForms logic
    ]

    let result = false

    try {
        //mewto forms exclusion
        if (currentForm.parentElement
            && currentForm.parentElement.classList.length > 0
            && currentForm.parentElement.classList[0].indexOf('mewtwo') !== -1) {
            result = true
        }

        exclusions_by_id.forEach(function (exclusion_id) {
            const form_id = currentForm.getAttribute('id')
            if ( form_id !== null && typeof (form_id) !== 'undefined' && form_id.indexOf(exclusion_id) !== -1 ) {
                result = true
            }
        })

        exclusions_by_class.forEach(function (exclusion_class) {
            const form_class = currentForm.getAttribute('class')
            if ( form_class !== null && typeof form_class !== 'undefined' && form_class.indexOf(exclusion_class) !== -1 ) {
                result = true
            }
        })

        exclusions_by_role.forEach(function (exclusion_role) {
            const form_role = currentForm.getAttribute('id')
            if ( form_role !== null && typeof form_role !== 'undefined'&& form_role.indexOf(exclusion_role) !== -1 ) {
                result = true
            }
        })
    } catch (e) {
        console.table('APBCT ERROR: formIsExclusion() - ',e)
    }

    return result
}

function apbctProcessIframes()
{
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

            for ( let y = 0; y < iframeForms.length; y++ ) {
                let currentForm = iframeForms[y];

                apbctProcessExternalForm(currentForm, y, frames[j].contentDocument);
            }
        }
    }
}

function apbctProcessExternalForm(currentForm, iterator, documentObject) {

    const cleantalk_placeholder = document.createElement("i");
    cleantalk_placeholder.className = 'cleantalk_placeholder';
    cleantalk_placeholder.style = 'display: none';

    currentForm.parentElement.insertBefore(cleantalk_placeholder, currentForm);

    // Deleting form to prevent submit event
    let prev = currentForm.previousSibling,
        form_html = currentForm.outerHTML,
        form_original = currentForm;

    // Remove the original form
    currentForm.parentElement.removeChild(currentForm);

    // Insert a clone
    const placeholder = document.createElement("div");
    placeholder.innerHTML = form_html;
    prev.after(placeholder.firstElementChild);

    var force_action = document.createElement("input");
    force_action.name = 'action';
    force_action.value = 'cleantalk_force_ajax_check';
    force_action.type = 'hidden';

    let reUseCurrentForm = documentObject.forms[iterator];

    reUseCurrentForm.appendChild(force_action);
    reUseCurrentForm.apbctPrev = prev;
    reUseCurrentForm.apbctFormOriginal = form_original;

    // mailerlite integration - disable click on submit button
    let mailerlite_detected_class = false
    if (reUseCurrentForm.classList !== undefined) {
        //list there all the mailerlite classes
        let mailerlite_classes = ['newsletterform', 'ml-block-form']
        mailerlite_classes.forEach(function(mailerlite_class) {
            if (reUseCurrentForm.classList.contains(mailerlite_class)){
                mailerlite_detected_class = mailerlite_class
            }
        });
    }
    if ( mailerlite_detected_class ) {
        let mailerliteSubmitButton = documentObject.querySelector('form.' + mailerlite_detected_class).querySelector('button[type="submit"]');
        if ( mailerliteSubmitButton !== undefined ) {
            mailerliteSubmitButton.click(function (event) {
                event.preventDefault();
                sendAjaxCheckingFormData(event.currentTarget);
            });
        }
    } else {
        documentObject.forms[iterator].onsubmit = function ( event ){
            event.preventDefault();

            //mautic integration
            if (documentObject.forms[iterator].id.indexOf('mauticform') !== -1) {
                let checkbox = documentObject.forms[iterator].querySelectorAll('input[id*="checkbox_rgpd"]')
                if (checkbox.length > 0){
                    if (checkbox.prop("checked") === true){
                        let placeholder = documentObject.querySelectorAll('.cleantalk_placeholder')
                        if (placeholder.length > 0) {
                            placeholder[0].setAttribute('mautic_hidden_gdpr_id', checkbox.prop("id"))
                        }
                    }
                }
            }

            const prev = apbct_prev(event.currentTarget);
            const form_original = event.currentTarget.cloneNode(true);

            sendAjaxCheckingFormData(event.currentTarget);
        };
    }
}

function apbct_replace_inputs_values_from_other_form( form_source, form_target ){

    var	inputs_source = form_source.querySelectorAll('button, input, textarea, select'),
        inputs_target = form_target.querySelectorAll('button, input, textarea, select');

    inputs_source.forEach((index, elem_source) => {

        inputs_target.forEach((index2, elem_target) => {

            if( elem_source.outerHTML === elem_target.outerHTML ){

                elem_target.apbct_val(elem_source.apbct_val());
            }
        });
    });

}
window.onload = function () {

    if( ! +ctPublic.settings__forms__check_external ) {
        return;
    }

    setTimeout(function () {
        ct_protect_external()
        catchDinamicRenderedForm()
    }, 1500);
};

/**
 * Checking the form integration
 */
function isIntegratedForm(formObj) {
    var formAction = formObj.action;
    var formId = formObj.getAttribute('id') !== null ? formObj.getAttribute('id') : '';

    if(
        formAction.indexOf('activehosted.com') !== -1 ||   // ActiveCampaign form
        formAction.indexOf('app.convertkit.com') !== -1 || // ConvertKit form
        ( formObj.firstChild.classList !== undefined && formObj.firstChild.classList.contains('cb-form-group') ) || // Convertbox form
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
        formAction.indexOf('mautic') !== -1 || formId.indexOf('mauticform_') !== -1
    ) {
        return true;
    }

    return false;
}

/**
 * Sending Ajax for checking form data
 */
function sendAjaxCheckingFormData(form, prev, formOriginal) {
    // Get visible fields and set cookie
    var visible_fields = {};
    visible_fields[0] = apbct_collect_visible_fields(form);
    apbct_visible_fields_set_cookie( visible_fields );

    var data = {};
    var elems = form.elements;
    elems = Array.prototype.slice.call(elems);

    elems.forEach( function( elem, y ) {
        if( elem.name === '' ) {
            data['input_' + y] = elem.value;
        } else {
            data[elem.name] = elem.value;
        }
    });

    apbct_public_sendAJAX(
        data,
        {
            async: false,
            callback: function( result, data, params, obj ){

                if( result.apbct === undefined || ! +result.apbct.blocked ) {

                    let form_new = form;
                    form.parentElement.removeChild(form);
                    let prev = form.apbctPrev;
                    let formOriginal = form.apbctFormOriginal;
                    let mautic_integration = false;

                    apbct_replace_inputs_values_from_other_form(form_new, formOriginal);

                    //mautic forms integration
                    if (formOriginal.id.indexOf('mautic') !== -1) {
                        mautic_integration = true
                    }
                    let placeholders = document.getElementsByClassName('cleantalk_placeholder')
                    if (placeholders) {
                        for (let i = 0; i < placeholders.length; i++) {
                            let mautic_hidden_gdpr_id = placeholders[i].getAttribute("mautic_hidden_gdpr_id")
                            if (typeof(mautic_hidden_gdpr_id) !== 'undefined') {
                                let mautic_gdpr_radio = formOriginal.querySelector('#' + mautic_hidden_gdpr_id)
                                if (typeof(mautic_gdpr_radio) !== 'undefined') {
                                    mautic_gdpr_radio.prop("checked", true);
                                }
                            }
                        }
                    }

                    prev.after( formOriginal );

                    // Clear visible_fields input
                    for (const el of formOriginal.querySelectorAll('input[name="apbct_visible_fields"]')) {
                        el.remove();
                    }

                    for (const el of formOriginal.querySelectorAll('input[value="cleantalk_force_ajax_check"]')) {
                        el.remove();
                    }

                    //Common click event
                    var subm_button = formOriginal.querySelectorAll('button[type=submit]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                        if (mautic_integration) {
                            setTimeout(function () {
                                ct_protect_external()
                            }, 1500);
                        }
                        return;
                    }

                    subm_button = formOriginal.querySelectorAll('input[type=submit]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                        return;
                    }

                    // ConvertKit direct integration
                    subm_button = formOriginal.querySelectorAll('button[data-element="submit"]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                        return;
                    }

                    // Paypal integration
                    subm_button = formOriginal.querySelectorAll('input[type="image"][name="submit"]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                    }

                }
                if (result.apbct !== undefined && +result.apbct.blocked) {
                    ctParseBlockMessage(result);
                }
            }
        }
    );
}

function catchDinamicRenderedForm() {
    let forms = document.getElementsByTagName('form')

    catchDinamicRenderedFormHandler(forms)

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

            catchDinamicRenderedFormHandler(iframeForms, frames[j].contentDocument)
        }
    }
}

function catchDinamicRenderedFormHandler(forms, documentObject = document) {
    let neededFormIds = [];
    for (let form of forms) {
        if (form.id.indexOf('hsForm') !== -1) {
            neededFormIds.push(form.id)
        }
        if (form.id.indexOf('createuser') !== -1 
            && (form.classList !== undefined && form.classList.contains('ihc-form-create-edit'))
        ) {
            neededFormIds.push(form.id)
        }
    }

    for (let formId of neededFormIds) {
        let form = documentObject.getElementById(formId);
        form.apbct_external_onsubmit_prev = form.onsubmit;
        form.onsubmit = sendAjaxCheckingDinamicFormData;
    }
}

/**
 * Sending Ajax for checking form data on dinamic rendered form
 */
function sendAjaxCheckingDinamicFormData(form) {
    form.preventDefault();
    form.stopImmediatePropagation();
    var formEvent = form;
    form = form.target;

    var force_action = document.createElement("input");
    force_action.name = 'action';
    force_action.value = 'cleantalk_force_ajax_check';
    force_action.type = 'hidden';
    form.appendChild(force_action);

    // Get visible fields and set cookie
    var visible_fields = {};
    visible_fields[0] = apbct_collect_visible_fields(form);
    apbct_visible_fields_set_cookie( visible_fields );

    var data = {};
    var elems = form.elements;
    elems = Array.prototype.slice.call(elems);

    elems.forEach( function( elem, y ) {
        if( elem.name === '' ) {
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
                if( result.apbct === undefined || ! +result.apbct.blocked ) {
                    form.onsubmit = null;

                    // Call previous submit action
                    if (form.apbct_external_onsubmit_prev instanceof Function) {
                        setTimeout(function () {
                            form.apbct_external_onsubmit_prev.call(form, formEvent);
                        }, 500);
                    }

                    let subm_button = form.querySelector('input[type="submit"]');
                    if(subm_button) {
                        subm_button.click();
                        return;
                    }
                }

                if (result.apbct !== undefined && +result.apbct.blocked) {
                    ctParseBlockMessage(result);
                }
            }
        }
    );
}

function apbct_prev(el, selector) {
    if (selector) {
        const prev = el.previousElementSibling;
        if (prev && prev.matches(selector)) {
        return prev;
    }
        return undefined;
    } else {
        return el.previousElementSibling;
    }
}

function apbct_val(el) {
    if (el.options && el.multiple) {
        return el.options
        .filter((option) => option.selected)
        .map((option) => option.value);
    } else {
        return el.value;
    }
}