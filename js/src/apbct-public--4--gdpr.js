const buttonsToHandle = [];

document.addEventListener('DOMContentLoaded', function() {
    if (
        typeof ctPublicGDPR === 'undefined' ||
        !ctPublicGDPR.gdpr_forms.length
    ) {
        return;
    }

    const gdprNoticeForButton = ctPublicGDPR.gdpr_title;

    if (typeof jQuery === 'undefined') {
        return;
    }
    try {
        ctPublicGDPR.gdpr_forms.forEach( function(item, i) {
            let elem = jQuery('#' + item + ', .' + item);

            // Filter forms
            if (!elem.is('form')) {
                // Caldera
                if (elem.find('form')[0]) {
                    elem = elem.children('form').first();
                } else if ( // Contact Form 7
                    jQuery('.wpcf7[role=form]')[0] && jQuery('.wpcf7[role=form]')
                        .attr('id')
                        .indexOf('wpcf7-f' + item) !== -1
                ) {
                    elem = jQuery('.wpcf7[role=form]').children('form');
                } else if ( // Formidable
                    jQuery('.frm_forms')[0] &&
                    jQuery('.frm_forms').first().attr('id').indexOf('frm_form_' + item) !== -1
                ) {
                    elem = jQuery('.frm_forms').first().children('form');
                } else if ( // WPForms
                    jQuery('.wpforms-form')[0] &&
                    jQuery('.wpforms-form').first().attr('id').indexOf('wpforms-form-' + item) !== -1
                ) {
                    elem = jQuery('.wpforms-form');
                }
            }

            // disable forms buttons
            let button = false;
            const buttonsCollection = elem.find('input[type|="submit"],button[type|="submit"]');

            if (!buttonsCollection.length) {
                return;
            } else {
                button = buttonsCollection[0];
            }

            if (button !== false) {
                button.disabled = true;
                const oldNotice = jQuery(button).prop('title') ? jQuery(button).prop('title') : '';
                buttonsToHandle.push({index: i, button: button, old_notice: oldNotice});
                jQuery(button).prop('title', gdprNoticeForButton);
            }

            // Adding notice and checkbox
            if (elem.is('form') || elem.attr('role') === 'form') {
                const selectorId = 'apbct_gdpr_' + i;
                elem.append(
                    '<input id="' + selectorId + '" type="checkbox" required="required" ' +
                    'style="margin-right: 10px;">',
                ).append(
                    '<label style=\'display: inline;\' for=\'apbct_gdpr_' +
                    i +
                    '\'>' +
                    ctPublicGDPR.gdpr_text +
                    '</label>',
                );
                jQuery(selectorId).prop('onchange', apbctGDPRHandleButtons);
            }
        });
    } catch (e) {
        console.info('APBCT GDPR JS ERROR: Can not add GDPR notice' + e);
    }
});

/**
 * Disable/enable form buttons if GDPR checkbox disabled/enabled.
 * @return {void}
 */
function apbctGDPRHandleButtons() {
    try {
        if (buttonsToHandle === []) {
            return;
        }

        buttonsToHandle.forEach((button) => {
            const selector = '[id=\'apbct_gdpr_' + button.index + '\']';
            const apbctGDPRItem = jQuery(selector);
            // chek if apbct_gdpr checkbox is set
            if (jQuery(apbctGDPRItem).prop('checked')) {
                button.button.disabled = false;
                jQuery(button.button).prop('title', button.old_notice);
            } else {
                button.button.disabled = true;
                jQuery(button.button).prop('title', gdpr_notice_for_button);
            }
        });
    } catch (e) {
        console.info('APBCT GDPR JS ERROR: Can not handle form buttons ' + e);
    }
}
