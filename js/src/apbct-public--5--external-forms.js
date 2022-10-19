/**
 * Handle external forms
 */
function ct_protect_external() {
    for(var i = 0; i < document.forms.length; i++) {

        if (document.forms[i].cleantalk_hidden_action === undefined && document.forms[i].cleantalk_hidden_method === undefined) {

            // current form
            var currentForm = document.forms[i];

            if (currentForm.parentElement && currentForm.parentElement.classList.length > 0 && currentForm.parentElement.classList[0].indexOf('mewtwo') !== -1){
                return
            }

            if(typeof(currentForm.action) == 'string') {

                // Ajax checking for the integrated forms
                if(isIntegratedForm(currentForm)) {

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
    const frames = document.getElementsByTagName('iframe');
    if ( frames.length > 0 ) {
        for ( let j = 0; j < frames.length; j++ ) {
            if ( frames[j].contentDocument == null ) { continue; }

            const iframeForms = frames[j].contentDocument.forms;
            if ( iframeForms.length === 0 ) { return; }

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
        let mailerliteSubmitButton = jQuery('form.' + mailerlite_detected_class).find('button[type="submit"]');
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
                let checkbox = jQuery(documentObject.forms[iterator]).find('input[id*="checkbox_rgpd"]')
                if (checkbox.length > 0){
                    if (checkbox.prop("checked") === true){
                        let placeholder = jQuery('.cleantalk_placeholder')
                        if (placeholder.length > 0) {
                            placeholder[0].setAttribute('mautic_hidden_gdpr_id', checkbox.prop("id"))
                        }
                    }
                }
            }

            const prev = jQuery(event.currentTarget).prev();
            const form_original = jQuery(event.currentTarget).clone();

            sendAjaxCheckingFormData(event.currentTarget);
        };
    }
}

function apbct_replace_inputs_values_from_other_form( form_source, form_target ){

    var	inputs_source = jQuery( form_source ).find( 'button, input, textarea, select' ),
        inputs_target = jQuery( form_target ).find( 'button, input, textarea, select' );

    inputs_source.each( function( index, elem_source ){

        var source = jQuery( elem_source );

        inputs_target.each( function( index2, elem_target ){

            var target = jQuery( elem_target );

            if( elem_source.outerHTML === elem_target.outerHTML ){

                target.val( source.val() );
            }
        });
    });

}
window.onload = function () {

    if( ! +ctPublic.settings__forms__check_external ) {
        return;
    }

    if ( typeof jQuery === 'undefined' ) {
        return;
    }

    setTimeout(function () {
        ct_protect_external()
    }, 1500);
};

/**
 * Checking the form integration
 */
function isIntegratedForm(formObj) {
    var formAction = formObj.action;
    let formId = formObj.id;

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

                    let form_new = jQuery(form).detach();
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
                                let mautic_gdpr_radio = jQuery(formOriginal).find('#' + mautic_hidden_gdpr_id)
                                if (typeof(mautic_gdpr_radio) !== 'undefined') {
                                    mautic_gdpr_radio.prop("checked", true);
                                }
                            }
                        }
                    }

                    prev.after( formOriginal );

                    // Clear visible_fields input
                    jQuery(formOriginal).find('input[name="apbct_visible_fields"]').remove();
                    jQuery(formOriginal).find('input[value="cleantalk_force_ajax_check"]').remove();


                    //Common click event
                    var subm_button = jQuery(formOriginal).find('button[type=submit]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                        if (mautic_integration) {
                            setTimeout(function () {
                                ct_protect_external()
                            }, 1500);
                        }
                        return;
                    }

                    subm_button = jQuery(formOriginal).find('input[type=submit]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                        return;
                    }

                    // ConvertKit direct integration
                    subm_button = jQuery(formOriginal).find('button[data-element="submit"]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                        return;
                    }

                    // Paypal integration
                    subm_button = jQuery(formOriginal).find('input[type="image"][name="submit"]');
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
