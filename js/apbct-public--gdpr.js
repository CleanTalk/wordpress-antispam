function apbct_gdpr__form_append_notice(){
	
	if(!ctPublic.gdpr_forms) return;
	
	ctPublic.gdpr_forms.forEach(function(item, i, arr){
		
		var elem = jQuery('#'+item+', .'+item);
		
		// Filter forms
		if(!elem.is('form')){
			// Caldera
			if(elem.find('form')[0])
				elem = elem.children('form').first();
			// Contact Form 7
			else if(jQuery('.wpcf7[role=form]')[0] && jQuery('.wpcf7[role=form]').attr('id').indexOf('wpcf7-f'+item) !== -1)
				elem = jQuery('.wpcf7[role=form]');
			// Formidable
			else if(jQuery('.frm_forms')[0] && jQuery('.frm_forms').first().attr('id').indexOf('frm_form_'+item) !== -1)
				elem = jQuery('.frm_forms').first().children('form');
		}
		
		// Adding notice
		if(elem.is('form') || elem.attr('role') == 'form'){
			elem.append('<input id="apbct_gdpr_'+i+'" type="checkbox" required="required " style="margin-right: 10px;">')
				.append('<label for="apbct_gdpr_'+i+'">'+ctPublic.gdpr_text+'</label>');
		}
	});
	
}

jQuery(document).ready(function(){
	apbct_gdpr__form_append_notice();
});