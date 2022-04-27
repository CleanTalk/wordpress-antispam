/**
 * Handle external forms
 */
function ct_protect_external() {
    for(var i = 0; i < document.forms.length; i++) {

        if (document.forms[i].cleantalk_hidden_action === undefined && document.forms[i].cleantalk_hidden_method === undefined) {

            // current form
            var currentForm = document.forms[i];

            if(typeof(currentForm.action) == 'string') {

                if(isIntegratedForm(currentForm)) {
                    jQuery( currentForm ).before('<i class="cleantalk_placeholder" style="display: none;"></i>');

                    // Deleting form to prevent submit event
                    var prev = jQuery(currentForm).prev(),
                        form_html = currentForm.outerHTML,
                        form_original = jQuery(currentForm).detach();

                    prev.after( form_html );

                    var force_action = document.createElement("input");
                    force_action.name = 'action';
                    force_action.value = 'cleantalk_force_ajax_check';
                    force_action.type = 'hidden';

                    var reUseCurrentForm = document.forms[i];

                    reUseCurrentForm.appendChild(force_action);

                    // mailerlite integration - disable click on submit button
                    if(reUseCurrentForm.classList !== undefined && reUseCurrentForm.classList.contains('ml-block-form')) {
                        var mailerliteSubmitButton = jQuery('form.ml-block-form').find('button[type="submit"]');

                        if(mailerliteSubmitButton !== undefined) {
                            mailerliteSubmitButton.click(function (event) {
                                event.preventDefault();
                                sendAjaxCheckingFormData(reUseCurrentForm, prev, form_original);
                            });
                        }
                    } else {
                        document.forms[i].onsubmit = function ( event ){
                            event.preventDefault();

                            const prev = jQuery(event.currentTarget).prev();
                            const form_original = jQuery(event.currentTarget).clone();

                            sendAjaxCheckingFormData(event.currentTarget, prev, form_original);
                        };
                    }

                    // Common flow
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

                        currentForm.method = 'POST';
                        currentForm.appendChild(ct_method);

                        currentForm.action = document.location;
                    }
                }
            }
        }

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
    setTimeout(function () {
        ct_protect_external()
    }, 1500);
};

/**
 * Checking the form integration
 */
function isIntegratedForm(formObj) {
    var formAction = formObj.action;

    if(
        formAction.indexOf('activehosted.com') !== -1 ||   // ActiveCampaign form
        formAction.indexOf('app.convertkit.com') !== -1 || // ConvertKit form
        ( formObj.firstChild.classList !== undefined && formObj.firstChild.classList.contains('cb-form-group') ) || // Convertbox form
        formAction.indexOf('mailerlite.com') !== -1 || // Mailerlite integration
        formAction.indexOf('colcolmail.co.uk') !== -1 || // colcolmail.co.uk integration
        formAction.indexOf('paypal.com') !== -1 ||
        formAction.indexOf('infusionsoft.com') !== -1
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
            callback: function( prev, formOriginal, result ){

                if( ! +result.apbct.blocked ) {

                    var form_new = jQuery(form).detach();

                    apbct_replace_inputs_values_from_other_form(form_new, formOriginal);

                    prev.after( formOriginal );

                    // Common click event
                    var subm_button = jQuery(formOriginal).find('button[type=submit]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                    }

                    // ConvertKit direct integration
                    subm_button = jQuery(formOriginal).find('button[data-element="submit"]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                    }

                    // Paypal integration
                    subm_button = jQuery(formOriginal).find('input[type="image"][name="submit"]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                    }

                }
            },
            callback_context: null,
            callback_params: [prev, formOriginal],
        }
    );
}
