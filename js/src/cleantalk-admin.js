jQuery(document).ready(function(){
	
	// Auto update banner close handler
	jQuery('.apbct_update_notice').on('click', 'button', function(){
		var ct_date = new Date(new Date().getTime() + 1000 * 86400 * 30 );
		var ctSecure = location.protocol === 'https:' ? '; secure' : '';
		document.cookie = "apbct_update_banner_closed=1; path=/; expires=" + ct_date.toUTCString() + "; samesite=lax" + ctSecure;
	});
	
	jQuery('li a[href="options-general.php?page=cleantalk"]').css('white-space','nowrap').css('display','inline-block');

	jQuery('body').on('click', '.apbct-notice .notice-dismiss', function(e){
		var apbct_notice_name = jQuery(e.target).parent().attr('id');
		if( apbct_notice_name ) {
			apbct_admin_sendAJAX( { 'action' : 'cleantalk_dismiss_notice', 'notice_id' : apbct_notice_name }, { 'callback' : null } );
		}
	});

	// Notice when deleting user
	jQuery('.ct_username .row-actions .delete a').on('click', function (e) {
		e.preventDefault();

		let result = confirm('Warning! Users are deleted without the possibility of restoring them, you can only restore them from a site backup.');

		if (result) {
			window.location = this.href
		}
	});

});
function apbct_admin_sendAJAX(data, params, obj){

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
		data = data + '&_ajax_nonce=' + ctAdminCommon._ajax_nonce + '&no_cache=' + Math.random();
	} else {
		data._ajax_nonce = ctAdminCommon._ajax_nonce;
		data.no_cache = Math.random();
	}
	// Button and spinner
	if(button)  {button.setAttribute('disabled', 'disabled'); button.style.cursor = 'not-allowed'; }
	if(spinner) jQuery(spinner).css('display', 'inline');

	jQuery.ajax({
		type: "POST",
		url: ctAdminCommon._ajax_url,
		data: data,
		async: async,
		success: function(result){
			if(button){  button.removeAttribute('disabled'); button.style.cursor = 'pointer'; }
			if(spinner)  jQuery(spinner).css('display', 'none');
			if(!notJson) result = JSON.parse(result);
			if(result.error){
				setTimeout(function(){ if(progressbar) progressbar.fadeOut('slow'); }, 1000);
				if( typeof cleantalkModal !== 'undefined' ) {
					// Show the result by modal
					cleantalkModal.loaded = 'Error:<br>' + result.error.toString();
					cleantalkModal.open();
				} else {
					alert('Error happens: ' + (result.error || 'Unkown'));
				}

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
		},
		timeout: timeout,
	});
}