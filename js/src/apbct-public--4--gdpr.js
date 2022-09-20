let buttons_to_handle = []
let gdpr_notice_for_button = 'Please, apply the GDPR agreement.'

document.addEventListener('DOMContentLoaded', function(){
	buttons_to_handle = []
	if(
		typeof ctPublicGDPR === 'undefined' ||
		! ctPublicGDPR.gdpr_forms.length
	) {
		return;
	}

	if ( typeof jQuery === 'undefined' ) {
		return;
	}
	try {
		ctPublicGDPR.gdpr_forms.forEach(function(item, i){

			let elem = jQuery('#'+item+', .'+item);

			// Filter forms
			if (!elem.is('form')){
				// Caldera
				if (elem.find('form')[0])
					elem = elem.children('form').first();
				// Contact Form 7
				else if(
					jQuery('.wpcf7[role=form]')[0] && jQuery('.wpcf7[role=form]')
						.attr('id')
						.indexOf('wpcf7-f'+item) !== -1
				) {
					elem = jQuery('.wpcf7[role=form]').children('form');
				}

				// Formidable
				else if(jQuery('.frm_forms')[0] && jQuery('.frm_forms').first().attr('id').indexOf('frm_form_'+item) !== -1)
					elem = jQuery('.frm_forms').first().children('form');
				// WPForms
				else if(jQuery('.wpforms-form')[0] && jQuery('.wpforms-form').first().attr('id').indexOf('wpforms-form-'+item) !== -1)
					elem = jQuery('.wpforms-form');
			}

			//disable forms buttons
			let button = false
			let buttons_collection= elem.find('input[type|="submit"]')

			if (!buttons_collection.length) {
				return
			} else {
				button = buttons_collection[0]
			}

			if (button !== false){
				console.log(buttons_collection)
				button.disabled = true
				let old_notice = jQuery(button).prop('title') ? jQuery(button).prop('title') : ''
				buttons_to_handle.push({index:i,button:button,old_notice:old_notice})
				jQuery(button).prop('title', gdpr_notice_for_button)
			}

			// Adding notice and checkbox
			if(elem.is('form') || elem.attr('role') === 'form'){
				elem.append('<input id="apbct_gdpr_'+i+'" type="checkbox" required="required" style=" margin-right: 10px;" onchange="apbct_gdpr_handle_buttons()">')
					.append('<label style="display: inline;" for="apbct_gdpr_'+i+'">'+ctPublicGDPR.gdpr_text+'</label>');
			}
		});
	} catch (e) {
		console.info('APBCT GDPR JS ERROR: Can not add GDPR notice' + e)
	}
});

function apbct_gdpr_handle_buttons(){

	try {

		if (buttons_to_handle === []){
			return
		}

		buttons_to_handle.forEach((button) => {
			let selector = '[id="apbct_gdpr_' + button.index + '"]'
			let apbct_gdpr_item = jQuery(selector)
			//chek if apbct_gdpr checkbox is set
			if (jQuery(apbct_gdpr_item).prop("checked")){
				button.button.disabled = false
				jQuery(button.button).prop('title', button.old_notice)
			} else {
				button.button.disabled = true
				jQuery(button.button).prop('title', gdpr_notice_for_button)
			}
		})
	} catch (e) {
		console.info('APBCT GDPR JS ERROR: Can not handle form buttons ' + e)
	}
}