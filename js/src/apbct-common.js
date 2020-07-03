function apbct_sendAJAX(data, params, obj){

	// Default params
	var callback    = params.callback    || null;
	var callback_context = params.callback_context || null;
	var callback_params = params.callback_params || null;
	var async = params.async || true;
	var notJson     = params.notJson     || null;
	var timeout     = params.timeout     || 15000;
	var obj         = obj                || null;
	var button      = params.button      || null;
	var spinner     = params.spinner     || null;
	var progressbar = params.progressbar || null;

	if(typeof (data) === 'string') {
		data = data + '&_ajax_nonce=' + ctCommon._ajax_nonce + '&no_cache=' + Math.random();
	} else {
		data._ajax_nonce = ctCommon._ajax_nonce;
		data.no_cache = Math.random();
	}
	// Button and spinner
	if(button)  {button.setAttribute('disabled', 'disabled'); button.style.cursor = 'not-allowed'; }
	if(spinner) jQuery(spinner).css('display', 'inline');

	jQuery.ajax({
		type: "POST",
		url: ctCommon._ajax_url,
		data: data,
		async: async,
		success: function(result){
			if(button){  button.removeAttribute('disabled'); button.style.cursor = 'pointer'; }
			if(spinner)  jQuery(spinner).css('display', 'none');
			if(!notJson) result = JSON.parse(result);
			if(result.error){
				setTimeout(function(){ if(progressbar) progressbar.fadeOut('slow'); }, 1000);
				alert('Error happens: ' + (result.error || 'Unkown'));
			}else{
				if(callback) {
					if (callback_params)
						callback.apply( callback_context, callback_params.concat( result, data, params, obj ) );
					else
						callback(result, data, params, obj);
				}
			}
		},
		error: function(jqXHR, textStatus, errorThrown){
			if(button){  button.removeAttribute('disabled'); button.style.cursor = 'pointer'; }
			if(spinner) jQuery(spinner).css('display', 'none');
			console.log('APBCT_AJAX_ERROR');
			console.log(jqXHR);
			console.log(textStatus);
			console.log(errorThrown);
			if(errorThrown)
				alert(errorThrown);
		},
		timeout: timeout,
	});
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