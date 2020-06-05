(function() {

	var ct_date = new Date(),
		ctTimeMs = new Date().getTime(),
		ctMouseEventTimerFlag = true, //Reading interval flag
		ctMouseData = [],
		ctMouseDataCounter = 0;

	function ctSetCookieSec(c_name, value) {
		document.cookie = c_name + "=" + encodeURIComponent(value) + "; path=/; samesite=lax";
	}

	function apbct_attach_event_handler(elem, event, callback){
		if(typeof window.addEventListener === "function") elem.addEventListener(event, callback);
		else                                              elem.attachEvent(event, callback);
	}

	function apbct_remove_event_handler(elem, event, callback){
		if(typeof window.removeEventListener === "function") elem.removeEventListener(event, callback);
		else                                                 elem.detachEvent(event, callback);
	}

	ctSetCookieSec("ct_ps_timestamp", Math.floor(new Date().getTime()/1000));
	ctSetCookieSec("ct_fkp_timestamp", "0");
	ctSetCookieSec("ct_pointer_data", "0");
	ctSetCookieSec("ct_timezone", "0");

	setTimeout(function(){
		ctSetCookieSec("ct_timezone", ct_date.getTimezoneOffset()/60*(-1));
	},1000);

	//Writing first key press timestamp
	var ctFunctionFirstKey = function output(event){
		var KeyTimestamp = Math.floor(new Date().getTime()/1000);
		ctSetCookieSec("ct_fkp_timestamp", KeyTimestamp);
		ctKeyStopStopListening();
	};

	//Reading interval
	var ctMouseReadInterval = setInterval(function(){
		ctMouseEventTimerFlag = true;
	}, 150);

	//Writting interval
	var ctMouseWriteDataInterval = setInterval(function(){
		ctSetCookieSec("ct_pointer_data", JSON.stringify(ctMouseData));
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

		ctSetCookieSec("apbct_visible_fields", 0);
		ctSetCookieSec("apbct_visible_fields_count", 0);

		setTimeout(function(){

			for(var i = 0; i < document.forms.length; i++){
				var form = document.forms[i];

				//Exclusion for StoreLocatorPlus form
				if (form.classList.contains('slp_search_form'))
					continue;

				form.onsubmit_prev = form.onsubmit;
				form.onsubmit = function(event){

					// Get only fields
					var inputs = [],
						inputs_visible = '',
						inputs_visible_count = 0,
						inputs_with_duplicate_names = [];

					for(var key in this.elements){
						if(!isNaN(+key))
							inputs[key] = this.elements[key];
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

					ctSetCookieSec("apbct_visible_fields", inputs_visible);
					ctSetCookieSec("apbct_visible_fields_count", inputs_visible_count);

					// Call previous submit action
					if(event.target.onsubmit_prev instanceof Function){
						setTimeout(function(){
							event.target.onsubmit_prev.call(event.target, event);
						}, 500);
					}
				};
			}
		}, 1000);
	}
	apbct_attach_event_handler(window, "DOMContentLoaded", apbct_ready);

}());

function apbct_js_keys__set_input_value(result, data, params, obj){
	if (document.getElementById(params.input_name) !== null) {
		var ct_input_value = document.getElementById(params.input_name).value;
		document.getElementById(params.input_name).value = document.getElementById(params.input_name).value.replace(ct_input_value, result.js_key);
	}
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

	function apbct_sendAJAXRequest(data, params, obj) {

		// Default params
		var callback = params.callback || null;
		var notJson = params.notJson || null;
		var timeout = params.timeout || 15000;
		var async = params.async || true;
		var obj = obj || null;

		if(typeof (data) === 'string') {
			data = data + '&_ajax_nonce=' + ctPublic._ajax_nonce + '&no_cache=' + Math.random();
		}else{
			data._ajax_nonce = ctPublic._ajax_nonce;
			data.no_cache = Math.random();
		}

		jQuery.ajax({
			type: "POST",
			url: ctPublic._ajax_url,
			data: data,
			async: async,
			success: function (result) {
				if (!notJson) result = JSON.parse(result);
				if (result.error) {

				} else {
					if (callback)
						callback(result, data, params, obj);
				}
			},
			error: function (jqXHR, textStatus, errorThrown) {
				console.log('APBCT_AJAX_ERROR');
				console.log(data);
				console.log(jqXHR);
				console.log(textStatus);
				console.log(errorThrown);
			},
			timeout: timeout
		});
	}
}