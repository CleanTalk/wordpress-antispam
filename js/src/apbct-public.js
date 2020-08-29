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

	ctSetCookie("ct_ps_timestamp", Math.floor(new Date().getTime()/1000));
	ctSetCookie("ct_fkp_timestamp", "0");
	ctSetCookie("ct_pointer_data", "0");
	ctSetCookie("ct_timezone", "0");

	setTimeout(function(){
		ctSetCookie("ct_timezone", ct_date.getTimezoneOffset()/60*(-1));
	},1000);

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

		ctSetCookie("apbct_visible_fields", 0);
		ctSetCookie("apbct_visible_fields_count", 0);

		setTimeout(function(){

			for(var i = 0; i < document.forms.length; i++){
				var form = document.forms[i];

				//Exclusion for forms
				if (
					form.classList.contains('slp_search_form') || //StoreLocatorPlus form
					form.parentElement.classList.contains('mec-booking') ||
					form.action.toString().indexOf('activehosted.com') || // Active Campaign
					(form.id && form.id == 'caspioform') //Caspio Form
				)
					continue;

				if(
					action.indexOf('activehosted.com') !== -1 // Exclusion for ActiveCampaign

				) {

					form.onsubmit_prev = form.onsubmit;
					form.onsubmit = function (event) {

						event.preventDefault();

						apbct_collect_visible_fields_and_set_cookie(this);

						// Call previous submit action
						if (event.target.onsubmit_prev instanceof Function) {
							setTimeout(function () {
								event.target.onsubmit_prev.call(event.target, event);
							}, 500);
						}
					};
				}
			}
		}, 1000);
	}
	apbct_attach_event_handler(window, "DOMContentLoaded", apbct_ready);

}());

function ctSetCookie(c_name, value) {
	document.cookie = c_name + "=" + encodeURIComponent(value) + "; path=/; samesite=lax";
}

function apbct_collect_visible_fields_and_set_cookie(form ) {

	// Get only fields
	var inputs = [],
		inputs_visible = '',
		inputs_visible_count = 0,
		inputs_with_duplicate_names = [];

	for(var key in form.elements){
		if(!isNaN(+key))
			inputs[key] = form.elements[key];
	}

	// Filter fields
	inputs = inputs.filter(function(elem){

		// Filter fields
		if( getComputedStyle(elem).display    === "none" ||   // hidden
			getComputedStyle(elem).visibility === "hidden" || // hidden
			getComputedStyle(elem).opacity    === "0" ||      // hidden
			elem.getAttribute("type")         === "hidden" || // type == hidden
			elem.getAttribute("type")         === "submit" || // type == submit
			elem.value                        === ""       || // empty value
			elem.getAttribute('name')         === null ||
			inputs_with_duplicate_names.indexOf( elem.getAttribute('name') ) !== -1 // name already added
		){
			return false;
		}

		// Visible fields count
		inputs_visible_count++;

		// Filter inputs with same names for type == radio
		if( -1 !== ['radio', 'checkbox'].indexOf( elem.getAttribute("type") )){
			inputs_with_duplicate_names.push( elem.getAttribute('name') );
			return false;
		}

		return true;
	});

	// Visible fields
	inputs.forEach(function(elem, i, elements){
		inputs_visible += " " + elem.getAttribute("name");
	});
	inputs_visible = inputs_visible.trim();

	ctSetCookie("apbct_visible_fields", inputs_visible);
	ctSetCookie("apbct_visible_fields_count", inputs_visible_count);

}

function apbct_js_keys__set_input_value(result, data, params, obj){
	if (document.getElementById(params.input_name) !== null) {
		var ct_input_value = document.getElementById(params.input_name).value;
		document.getElementById(params.input_name).value = document.getElementById(params.input_name).value.replace(ct_input_value, result.js_key);
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
		url: ctPublic._ajax_url,
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
			if(errorThrown && ! silent )
				alert('Anti-spam by Cleantalk plugin error: ' + errorThrown + 'Please, contact Cleantalk tech support https://wordpress.org/support/plugin/cleantalk-spam-protect/');
		},
		timeout: timeout,
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
					alert(response.comment);
					if(+response.stop_script == 1)
						window.stop();
				}
			}
		}
	});
}