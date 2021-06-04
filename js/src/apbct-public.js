(function() {

	var ct_date = new Date(),
		ctTimeMs = new Date().getTime(),
		ctMouseEventTimerFlag = true, //Reading interval flag
		ctMouseData = [],
		ctMouseDataCounter = 0;

	function apbct_attach_event_handler(elem, event, callback){
		if(typeof window.addEventListener === "function") elem.addEventListener(event, callback);
		else                                              elem.attachEvent(event, callback);
	}

	function apbct_remove_event_handler(elem, event, callback){
		if(typeof window.removeEventListener === "function") elem.removeEventListener(event, callback);
		else                                                 elem.detachEvent(event, callback);
	}

	ctSetCookie(
		[
			[ "ct_ps_timestamp", Math.floor(new Date().getTime() / 1000) ],
			[ "ct_fkp_timestamp", "0" ],
			[ "ct_pointer_data", "0" ],
			[ "ct_timezone", ct_date.getTimezoneOffset()/60*(-1) ],
			[ "apbct_visible_fields", "0" ],
		]
	);

	//Writing first key press timestamp
	var ctFunctionFirstKey = function output(event){
		var KeyTimestamp = Math.floor(new Date().getTime()/1000);
		ctSetCookie("ct_fkp_timestamp", KeyTimestamp);
		ctKeyStopStopListening();
	};

	//Reading interval
	var ctMouseReadInterval = setInterval(function(){
		ctMouseEventTimerFlag = true;
	}, 150);

	//Writting interval
	var ctMouseWriteDataInterval = setInterval(function(){
		ctSetCookie("ct_pointer_data", JSON.stringify(ctMouseData));
	}, 1200);

	//Logging mouse position each 150 ms
	var ctFunctionMouseMove = function output(event){
		if(ctMouseEventTimerFlag === true){

			ctMouseData.push([
				Math.round(event.clientY),
				Math.round(event.clientX),
				Math.round(new Date().getTime() - ctTimeMs)
			]);

			ctMouseDataCounter++;
			ctMouseEventTimerFlag = false;
			if(ctMouseDataCounter >= 50){
				ctMouseStopData();
			}
		}
	};

	//Stop mouse observing function
	function ctMouseStopData(){
		apbct_remove_event_handler(window, "mousemove", ctFunctionMouseMove);
		clearInterval(ctMouseReadInterval);
		clearInterval(ctMouseWriteDataInterval);
	}

	//Stop key listening function
	function ctKeyStopStopListening(){
		apbct_remove_event_handler(window, "mousedown", ctFunctionFirstKey);
		apbct_remove_event_handler(window, "keydown", ctFunctionFirstKey);
	}

	apbct_attach_event_handler(window, "mousemove", ctFunctionMouseMove);
	apbct_attach_event_handler(window, "mousedown", ctFunctionFirstKey);
	apbct_attach_event_handler(window, "keydown", ctFunctionFirstKey);

	// Ready function
	function apbct_ready(){

		if( +ctPublic.pixel__setting ){
			ctSetCookie( 'apbct_pixel_url', ctPublic.pixel__url );
			if( +ctPublic.pixel__enabled ){
				jQuery('body').append( '<img style="display: none; left: 99999px;" src="' + ctPublic.pixel__url + '">' );
			}
		}

		setTimeout(function(){

			var visible_fields_collection = {};

			for(var i = 0; i < document.forms.length; i++){
				var form = document.forms[i];

				//Exclusion for forms
				if (
					form.classList.contains('slp_search_form') || //StoreLocatorPlus form
					form.parentElement.classList.contains('mec-booking') ||
					form.action.toString().indexOf('activehosted.com') !== -1 || // Active Campaign
					(form.id && form.id == 'caspioform') || //Caspio Form
					(form.name.classList && form.name.classList.contains('tinkoffPayRow')) || // TinkoffPayForm
					(form.name.classList && form.name.classList.contains('give-form ')) // GiveWP
				)
					continue;

				visible_fields_collection[i] = apbct_collect_visible_fields( form );

				form.onsubmit_prev = form.onsubmit;
				form.onsubmit = function (event) {

					var visible_fields = {};
					visible_fields[0] = apbct_collect_visible_fields(this);
					apbct_visible_fields_set_cookie( visible_fields );

					// Call previous submit action
					if (event.target.onsubmit_prev instanceof Function) {
						setTimeout(function () {
							event.target.onsubmit_prev.call(event.target, event);
						}, 500);
					}
				};
			}

			apbct_visible_fields_set_cookie( visible_fields_collection );

		}, 1000);
	}
	apbct_attach_event_handler(window, "DOMContentLoaded", apbct_ready);

}());

function ctSetCookie( cookies, value, expires ){

	if( typeof cookies === 'string' && typeof value === 'string' || typeof value === 'number'){
		var skip_alt = cookies === 'ct_pointer_data' || cookies === 'ct_user_info';
		cookies = [ [ cookies, value, expires ] ];
	}

	// Cookies disabled
	if( +ctPublic.data__set_cookies === 0 ){
		return;

	// Using traditional cookies
	}else if( +ctPublic.data__set_cookies === 1 ){
		cookies.forEach( function (item, i, arr	) {
			var expires = typeof item[2] !== 'undefined' ? "expires=" + expires + '; ' : '';
			var ctSecure = location.protocol === 'https:' ? '; secure' : '';
			document.cookie = item[0] + "=" + encodeURIComponent(item[1]) + "; " + expires + "path=/; samesite=lax" + ctSecure;
		});

	// Using alternative cookies
	}else if( +ctPublic.data__set_cookies === 2 && ! skip_alt ){

		// Using REST API handler
		if( +ctPublic.data__set_cookies__alt_sessions_type === 1 ){
			apbct_public_sendREST(
				'alt_sessions',
				{
					method: 'POST',
					data: { cookies: cookies }
				}
			);

		// Using AJAX request and handler
		}else if( +ctPublic.data__set_cookies__alt_sessions_type === 2 ) {
			apbct_public_sendAJAX(
				{
					action: 'apbct_alt_session__save__AJAX',
					cookies: cookies,
				},
				{}
			);
		}
	}
}

function apbct_collect_visible_fields( form ) {

	// Get only fields
	var inputs = [],
		inputs_visible = '',
		inputs_visible_count = 0,
		inputs_invisible = '',
		inputs_invisible_count = 0,
		inputs_with_duplicate_names = [];

	for(var key in form.elements){
		if(!isNaN(+key))
			inputs[key] = form.elements[key];
	}

	// Filter fields
	inputs = inputs.filter(function(elem){

		// Filter already added fields
		if( inputs_with_duplicate_names.indexOf( elem.getAttribute('name') ) !== -1 ){
			return false;
		}
		// Filter inputs with same names for type == radio
		if( -1 !== ['radio', 'checkbox'].indexOf( elem.getAttribute("type") )){
			inputs_with_duplicate_names.push( elem.getAttribute('name') );
			return false;
		}
		return true;
	});

	// Visible fields
	inputs.forEach(function(elem, i, elements){
		// Unnecessary fields
		if(
			elem.getAttribute("type")         === "submit" || // type == submit
			elem.getAttribute('name')         === null     ||
			elem.getAttribute('name')         === 'ct_checkjs'
		) {
			return;
		}
		// Invisible fields
		if(
			getComputedStyle(elem).display    === "none" ||   // hidden
			getComputedStyle(elem).visibility === "hidden" || // hidden
			getComputedStyle(elem).opacity    === "0" ||      // hidden
			elem.getAttribute("type")         === "hidden" // type == hidden
		) {
			if( elem.classList.contains("wp-editor-area") ) {
				inputs_visible += " " + elem.getAttribute("name");
				inputs_visible_count++;
			} else {
				inputs_invisible += " " + elem.getAttribute("name");
				inputs_invisible_count++;
			}
		}
		// Visible fields
		else {
			inputs_visible += " " + elem.getAttribute("name");
			inputs_visible_count++;
		}

	});

	inputs_invisible = inputs_invisible.trim();
	inputs_visible = inputs_visible.trim();

	return {
		visible_fields : inputs_visible,
		visible_fields_count : inputs_visible_count,
		invisible_fields : inputs_invisible,
		invisible_fields_count : inputs_invisible_count,
	}

}

function apbct_visible_fields_set_cookie( visible_fields_collection ) {

	var collection = typeof visible_fields_collection === 'object' && visible_fields_collection !== null ?  visible_fields_collection : {};

	ctSetCookie("apbct_visible_fields", JSON.stringify( collection ) );

}

function apbct_js_keys__set_input_value(result, data, params, obj){
	if( document.querySelectorAll('[name^=ct_checkjs]').length > 0 ) {
		var elements = document.querySelectorAll('[name^=ct_checkjs]');
		for ( var i = 0; i < elements.length; i++ ) {
			elements[i].value = result.js_key;
		}
	}
}

function apbct_public_sendAJAX(data, params, obj){

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
	var silent      = params.silent      || null;
	var no_nonce    = params.no_nonce    || null;
	var apbct_ajax  = params.apbct_ajax  || null;

	if(typeof (data) === 'string') {
		if( ! no_nonce )
			data = data + '&_ajax_nonce=' + ctPublic._ajax_nonce;
		data = data + '&no_cache=' + Math.random()
	} else {
		if( ! no_nonce )
			data._ajax_nonce = ctPublic._ajax_nonce;
		data.no_cache = Math.random();
	}
	// Button and spinner
	if(button)  {button.setAttribute('disabled', 'disabled'); button.style.cursor = 'not-allowed'; }
	if(spinner) jQuery(spinner).css('display', 'inline');

	jQuery.ajax({
		type: "POST",
		url: apbct_ajax ? ctPublic._apbct_ajax_url : ctPublic._ajax_url,
		data: data,
		async: async,
		success: function(result){
			if(button){  button.removeAttribute('disabled'); button.style.cursor = 'pointer'; }
			if(spinner)  jQuery(spinner).css('display', 'none');
			if(!notJson) result = JSON.parse(result);
			if(result.error){
				setTimeout(function(){ if(progressbar) progressbar.fadeOut('slow'); }, 1000);
				console.log('Error happens: ' + (result.error || 'Unkown'));
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
			if( errorThrown && ! silent ) {
				console.log('APBCT_AJAX_ERROR');
				console.log(jqXHR);
				console.log(textStatus);
				console.log('Anti-spam by Cleantalk plugin error: ' + errorThrown + 'Please, contact Cleantalk tech support https://wordpress.org/support/plugin/cleantalk-spam-protect/');
			}
		},
		timeout: timeout,
	});
}

function apbct_public_sendREST( route, params ) {

	var callback = params.callback || null;
	var data     = params.data || [];
	var method   = params.method || 'POST';

	jQuery.ajax({
		type: method,
		url: ctPublic._rest_url + 'cleantalk-antispam/v1/' + route,
		data: data,
		beforeSend : function ( xhr ) {
			xhr.setRequestHeader( 'X-WP-Nonce', ctPublic._rest_nonce );
		},
		success: function(result){
			if(result.error){
				console.log('Error happens: ' + (result.error || 'Unknown'));
			}else{
				if(callback) {
					var obj = null;
					callback(result, route, params, obj);
				}
			}
		},
		error: function(jqXHR, textStatus, errorThrown){
			if( errorThrown ) {
				console.log('APBCT_REST_ERROR');
				console.log(jqXHR);
				console.log(textStatus);
				console.log('Anti-spam by Cleantalk plugin REST API error: ' + errorThrown + ' Please, contact Cleantalk tech support https://wordpress.org/support/plugin/cleantalk-spam-protect/');
			}
		},
	});

}

if(typeof jQuery !== 'undefined') {

	// Capturing responses and output block message for unknown AJAX forms
	jQuery(document).ajaxComplete(function (event, xhr, settings) {
		if (xhr.responseText && xhr.responseText.indexOf('"apbct') !== -1) {
			var response = JSON.parse(xhr.responseText);
			if (typeof response.apbct !== 'undefined') {
				response = response.apbct;
				if (response.blocked) {
					document.dispatchEvent(
						new CustomEvent( "apbctAjaxBockAlert", {
							bubbles: true,
							detail: { message: response.comment }
						} )
					);

					// Show the result by modal
					cleantalkModal.loaded = response.comment;
					cleantalkModal.open();

					if(+response.stop_script == 1)
						window.stop();
				}
			}
		}
	});
}