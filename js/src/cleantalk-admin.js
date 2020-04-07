jQuery(document).ready(function(){
	
	// Auto update banner close handler
	jQuery('.apbct_update_notice').on('click', 'button', function(){
		var ct_date = new Date(new Date().getTime() + 1000 * 86400 * 30 );
		document.cookie = "apbct_update_banner_closed=1; path=/; expires=" + ct_date.toUTCString();
	});
	
	jQuery('li a[href="options-general.php?page=cleantalk"]').css('white-space','nowrap');
	
});

function apbct_sendAJAX(data, params, obj){

	// Default params
	var callback    = params.callback    || null;
	var notJson     = params.notJson     || null;
	var timeout     = params.timeout     || 15000;
	var obj         = obj                || null;
	var button      = params.button      || null;
	var spinner     = params.spinner     || null;
	var progressbar = params.progressbar || null;

	// Button and spinner
	if(button)  {button.setAttribute('disabled', 'disabled'); button.style.cursor = 'not-allowed'; }
	if(spinner) jQuery(spinner).css('display', 'inline');

	// Adding security code
	data._ajax_nonce = ctAdminCommon._ajax_nonce;

	jQuery.ajax({
		type: "POST",
		url: ctAdminCommon._ajax_url,
		data: data,
		success: function(result){
			if(button){  button.removeAttribute('disabled'); button.style.cursor = 'pointer'; }
			if(spinner)  jQuery(spinner).css('display', 'none');
			if(!notJson) result = JSON.parse(result);
			if(result.error){
				setTimeout(function(){ if(progressbar) progressbar.fadeOut('slow'); }, 1000);
				alert('Error happens: ' + (result.error || 'Unkown'));
			}else{
				if(callback)
					callback(result, data, params, obj);
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