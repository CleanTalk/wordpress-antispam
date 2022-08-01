var ct_date = new Date(),
	ctTimeMs = new Date().getTime(),
	ctMouseEventTimerFlag = true, //Reading interval flag
	ctMouseData = [],
	ctMouseDataCounter = 0,
	ctCheckedEmails = {};

function apbct_attach_event_handler(elem, event, callback){
	if(typeof window.addEventListener === "function") elem.addEventListener(event, callback);
	else                                              elem.attachEvent(event, callback);
}

function apbct_remove_event_handler(elem, event, callback){
	if(typeof window.removeEventListener === "function") elem.removeEventListener(event, callback);
	else                                                 elem.detachEvent(event, callback);
}

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
	ctSetMouseMoved();
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

function checkEmail(e) {
	var current_email = e.target.value;
	if (current_email && !(current_email in ctCheckedEmails)) {
		// Using REST API handler
		if( ctPublicFunctions.data__ajax_type === 'rest' ){
			apbct_public_sendREST(
				'check_email_before_post',
				{
					method: 'POST',
					data: {'email' : current_email},
					callback: function (result) {
						if (result.result) {
							ctCheckedEmails[current_email] = {'result' : result.result, 'timestamp': Date.now() / 1000 |0};
							ctSetCookie('ct_checked_emails', JSON.stringify(ctCheckedEmails));
						}
					},
				}
			);
			// Using AJAX request and handler
		} else if( ctPublicFunctions.data__ajax_type === 'admin_ajax' ) {
			apbct_public_sendAJAX(
				{
					action: 'apbct_email_check_before_post',
					email : current_email,
				},
				{
					callback: function (result) {
						if (result.result) {
							ctCheckedEmails[current_email] = {'result' : result.result, 'timestamp': Date.now() / 1000 |0};
							ctSetCookie('ct_checked_emails', JSON.stringify(ctCheckedEmails));
						}
					},
				}
			);
		}
	}
}

function ctSetPixelImg(pixelUrl) {
	ctSetCookie('apbct_pixel_url', pixelUrl);
	if( +ctPublic.pixel__enabled ){
		if( ! document.getElementById('apbct_pixel') ) {
			jQuery('body').append( '<img alt="Cleantalk Pixel" id="apbct_pixel" style="display: none; left: 99999px;" src="' + pixelUrl + '">' );
		}
	}
}

function ctGetPixelUrl() {
	// Check if pixel is already in localstorage and is not outdated
	let local_storage_pixel_url = ctGetPixelUrlLocalstorage();
	if ( local_storage_pixel_url !== false ) {
		if ( ctIsOutdatedPixelUrlLocalstorage(local_storage_pixel_url) ) {
			ctCleaPixelUrlLocalstorage(local_storage_pixel_url)
		} else {
			//if so - load pixel from localstorage and draw it skipping AJAX
			ctSetPixelImg(local_storage_pixel_url);
			return;
		}
	}
	// Using REST API handler
	if( ctPublicFunctions.data__ajax_type === 'rest' ){
		apbct_public_sendREST(
			'apbct_get_pixel_url',
			{
				method: 'POST',
				callback: function (result) {
					if (result) {
						//set  pixel url to localstorage
						if ( ! ctGetPixelUrlLocalstorage() ){
							ctSetPixelUrlLocalstorage(result);
						}
						//then run pixel drawing
						ctSetPixelImg(result);
					}
				},
			}
		);
		// Using AJAX request and handler
	}else{
		apbct_public_sendAJAX(
			{
				action: 'apbct_get_pixel_url',
			},
			{
				notJson: true,
				callback: function (result) {
					if (result) {
						//set  pixel url to localstorage
						if ( ! ctGetPixelUrlLocalstorage() ){
							ctSetPixelUrlLocalstorage(result);
						}
						//then run pixel drawing
						ctSetPixelImg(result);
					}
				},
			}
		);
	}
}

function ctSetHasScrolled() {
	if( ! apbctLocalStorage.isSet('ct_has_scrolled') || ! apbctLocalStorage.get('ct_has_scrolled') ) {
		ctSetCookie("ct_has_scrolled", 'true');
	}
}

function ctSetMouseMoved() {
	if( ! apbctLocalStorage.isSet('ct_mouse_moved') || ! apbctLocalStorage.get('ct_mouse_moved') ) {
		ctSetCookie("ct_mouse_moved", 'true');
	}
}

function ctPreloadLocalStorage(){
	if (ctPublic.data__to_local_storage){
		let data = Object.entries(ctPublic.data__to_local_storage)
		data.forEach(([key, value]) => {
			apbctLocalStorage.set(key,value)
		});
	}
}

apbct_attach_event_handler(window, "mousemove", ctFunctionMouseMove);
apbct_attach_event_handler(window, "mousedown", ctFunctionFirstKey);
apbct_attach_event_handler(window, "keydown", ctFunctionFirstKey);
apbct_attach_event_handler(window, "scroll", ctSetHasScrolled);

// Ready function
function apbct_ready(){

	ctPreloadLocalStorage()

	let cookiesType = apbctLocalStorage.get('ct_cookies_type');
	if ( ! cookiesType || cookiesType !== ctPublic.data__cookies_type ) {
		apbctLocalStorage.set('ct_cookies_type', ctPublic.data__cookies_type);
		apbctLocalStorage.delete('ct_mouse_moved');
		apbctLocalStorage.delete('ct_has_scrolled');
	}

	// Collect scrolling info
	var initCookies = [
		["ct_ps_timestamp", Math.floor(new Date().getTime() / 1000)],
		["ct_fkp_timestamp", "0"],
		["ct_pointer_data", "0"],
		["ct_timezone", ct_date.getTimezoneOffset()/60*(-1) ],
		["ct_screen_info", apbctGetScreenInfo()],
		["apbct_headless", navigator.webdriver],
	];

	apbctLocalStorage.set('ct_ps_timestamp', Math.floor(new Date().getTime() / 1000));
	apbctLocalStorage.set('ct_fkp_timestamp', "0");
	apbctLocalStorage.set('ct_pointer_data', "0");
	apbctLocalStorage.set('ct_timezone', ct_date.getTimezoneOffset()/60*(-1) );
	apbctLocalStorage.set('ct_screen_info', apbctGetScreenInfo());
	apbctLocalStorage.set('apbct_headless', navigator.webdriver);

	if( ctPublic.data__cookies_type !== 'native' ) {
		initCookies.push(['apbct_visible_fields', '0']);
	} else {
		// Delete all visible fields cookies on load the page
		var cookiesArray = document.cookie.split(";");
		if( cookiesArray.length !== 0 ) {
			for ( var i = 0; i < cookiesArray.length; i++ ) {
				var currentCookie = cookiesArray[i].trim();
				var cookieName = currentCookie.split("=")[0];
				if( cookieName.indexOf("apbct_visible_fields_") === 0 ) {
					ctDeleteCookie(cookieName);
				}
			}
		}
	}

	if( +ctPublic.pixel__setting ){
		if( +ctPublic.pixel__enabled ){
			ctGetPixelUrl()
		} else {
			initCookies.push(['apbct_pixel_url', ctPublic.pixel__url]);
		}
	}

	if ( +ctPublic.data__email_check_before_post) {
		initCookies.push(['ct_checked_emails', '0']);
		jQuery("input[type = 'email'], #email").blur(checkEmail);
	}

	if (apbctLocalStorage.isSet('ct_checkjs')) {
		initCookies.push(['ct_checkjs', apbctLocalStorage.get('ct_checkjs')]);
	} else {
		initCookies.push(['ct_checkjs', 0]);
	}

	ctSetCookie(initCookies);

	setTimeout(function(){

		ctNoCookieAttachHiddenFieldsToForms()

		for(var i = 0; i < document.forms.length; i++){
			var form = document.forms[i];

			//Exclusion for forms
			if (
				+ctPublic.data__visible_fields_required === 0 ||
				form.method.toString().toLowerCase() === 'get' ||
				form.classList.contains('slp_search_form') || //StoreLocatorPlus form
				form.parentElement.classList.contains('mec-booking') ||
				form.action.toString().indexOf('activehosted.com') !== -1 || // Active Campaign
				(form.id && form.id === 'caspioform') || //Caspio Form
				(form.classList && form.classList.contains('tinkoffPayRow')) || // TinkoffPayForm
				(form.classList && form.classList.contains('give-form')) || // GiveWP
				(form.id && form.id === 'ult-forgot-password-form') || //ult forgot password
				(form.id && form.id.toString().indexOf('calculatedfields') !== -1) || // CalculatedFieldsForm
				(form.id && form.id.toString().indexOf('sac-form') !== -1) || // Simple Ajax Chat
				(form.id && form.id.toString().indexOf('cp_tslotsbooking_pform') !== -1) || // WP Time Slots Booking Form
				(form.name && form.name.toString().indexOf('cp_tslotsbooking_pform') !== -1)  || // WP Time Slots Booking Form
				form.action.toString() === 'https://epayment.epymtservice.com/epay.jhtml' || // Custom form
				(form.name && form.name.toString().indexOf('tribe-bar-form') !== -1)  // The Events Calendar
			) {
				continue;
			}

			var hiddenInput = document.createElement( 'input' );
			hiddenInput.setAttribute( 'type', 'hidden' );
			hiddenInput.setAttribute( 'id', 'apbct_visible_fields_' + i );
			hiddenInput.setAttribute( 'name', 'apbct_visible_fields');
			var visibleFieldsToInput = {};
			visibleFieldsToInput[0] = apbct_collect_visible_fields(form);
			hiddenInput.value = JSON.stringify(visibleFieldsToInput);
			form.append( hiddenInput );

			form.onsubmit_prev = form.onsubmit;

			form.ctFormIndex = i;
			form.onsubmit = function (event) {

				if ( ctPublic.data__cookies_type !== 'native' && typeof event.target.ctFormIndex !== 'undefined' ) {

					var visible_fields = {};
					visible_fields[0] = apbct_collect_visible_fields(this);
					apbct_visible_fields_set_cookie( visible_fields, event.target.ctFormIndex );
				}

				// Call previous submit action
				if (event.target.onsubmit_prev instanceof Function) {
					setTimeout(function () {
						event.target.onsubmit_prev.call(event.target, event);
					}, 500);
				}
			};
		}

	}, 1000);

	// Listen clicks on encoded emails
	let decodedEmailNodes = document.querySelectorAll("[data-original-string]");
	if (decodedEmailNodes.length) {
		for (let i = 0; i < decodedEmailNodes.length; ++i) {
			if (
				decodedEmailNodes[i].parentElement.href ||
				decodedEmailNodes[i].parentElement.parentElement.href
			) {
				// Skip listening click on hyperlinks
				continue;
			}
			decodedEmailNodes[i].addEventListener('click', function ctFillDecodedEmailHandler(event) {
				this.removeEventListener('click', ctFillDecodedEmailHandler);
				apbctAjaxEmailDecode(event);
			});
		}
	}
}
apbct_attach_event_handler(window, "DOMContentLoaded", apbct_ready);

function apbctAjaxEmailDecode(event){
	const element = event.target;
	element.setAttribute('title', ctPublicFunctions.text__wait_for_decoding);
	element.style.cursor = 'progress';
	// Using REST API handler
	if( ctPublicFunctions.data__ajax_type === 'rest' ){
		apbct_public_sendREST(
			'apbct_decode_email',
			{
				data: {encodedEmail: event.target.dataset.originalString},
				method: 'POST',
				callback: function (result) {
					if (result.success) {
						ctFillDecodedEmail(result.data, event.target);
						element.setAttribute('title', '');
						element.removeAttribute('style');
					}
				},
			}
		);
		// Using AJAX request and handler
	}else{
		apbct_public_sendAJAX(
			{
				action: 'apbct_decode_email',
				encodedEmail: event.target.dataset.originalString
			},
			{
				notJson: true,
				callback: function (result) {
					if (result.success) {
						ctFillDecodedEmail(result.data, event.target);
						element.setAttribute('title', '');
						element.removeAttribute('style');
					}
				},
			}
		);
	}
}

function ctFillDecodedEmail(result, targetElement){
	targetElement.innerHTML = result;
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

function apbct_visible_fields_set_cookie( visible_fields_collection, form_id ) {

	var collection = typeof visible_fields_collection === 'object' && visible_fields_collection !== null ?  visible_fields_collection : {};

	if( ctPublic.data__cookies_type === 'native' ) {
		for ( var i in collection ) {
			if ( i > 10 ) {
				// Do not generate more than 10 cookies
				return;
			}
			var collectionIndex = form_id !== undefined ? form_id : i;
			ctSetCookie("apbct_visible_fields_" + collectionIndex, JSON.stringify( collection[i] ) );
		}
	} else {
		ctSetCookie("apbct_visible_fields", JSON.stringify( collection ) );
		apbctLocalStorage.set('apbct_visible_fields', JSON.stringify( collection ));
	}
}

function apbct_js_keys__set_input_value(result, data, params, obj){
	if( document.querySelectorAll('[name^=ct_checkjs]').length > 0 ) {
		var elements = document.querySelectorAll('[name^=ct_checkjs]');
		for ( var i = 0; i < elements.length; i++ ) {
			elements[i].value = result.js_key;
		}
	}
}

function apbctGetScreenInfo() {
	return JSON.stringify({
		fullWidth : document.documentElement.scrollWidth,
		fullHeight : Math.max(
			document.body.scrollHeight, document.documentElement.scrollHeight,
			document.body.offsetHeight, document.documentElement.offsetHeight,
			document.body.clientHeight, document.documentElement.clientHeight
		),
		visibleWidth : document.documentElement.clientWidth,
		visibleHeight : document.documentElement.clientHeight,
	});
}

if(typeof jQuery !== 'undefined') {

	// Capturing responses and output block message for unknown AJAX forms
	jQuery(document).ajaxComplete(function (event, xhr, settings) {
		if (xhr.responseText && xhr.responseText.indexOf('"apbct') !== -1) {
			try {
				var response = JSON.parse(xhr.responseText);
			} catch (e) {
				console.log(e.toString())
			}
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

function ctSetPixelUrlLocalstorage(ajax_pixel_url) {
	//set pixel to the storage
	localStorage.setItem('session_pixel_url', ajax_pixel_url)
	//set pixel timestamp to the storage
	localStorage.setItem(ajax_pixel_url, Math.floor(Date.now() / 1000).toString())
}

function ctGetPixelUrlLocalstorage() {
	let local_storage_pixel = localStorage.getItem('session_pixel_url');
	if ( local_storage_pixel !== null ) {
		return local_storage_pixel;
	} else {
		return false;
	}
}

function ctIsOutdatedPixelUrlLocalstorage(local_storage_pixel_url) {
	let local_storage_pixel_timestamp = Number(localStorage.getItem(local_storage_pixel_url));
	let current_timestamp = Math.floor(Date.now() / 1000).toString()
	let timestamp_difference = current_timestamp - local_storage_pixel_timestamp;
	return timestamp_difference > 3600 * 3;
}

function ctCleaPixelUrlLocalstorage(local_storage_pixel_url) {
	//remove timestamp
	localStorage.removeItem(local_storage_pixel_url)
	//remove pixel itself
	localStorage.removeItem('session_pixel_url')
}


function ctSetNoCookieData(){

}

function ctGetNoCookieData(){
	let data = apbctLocalStorage.getCleanTalkData()
	// data.ct_mouse_moved = apbctLocalStorage.get('ct_mouse_moved')
	// data.ct_has_scrolled = apbctLocalStorage.get('ct_has_scrolled')
	return JSON.stringify(data)
}

function ctNoCookieConstructHiddenField(){
	let field = ''
	let no_cookie_data = ctGetNoCookieData()
	no_cookie_data = btoa(no_cookie_data)
	field = document.createElement('input')
	field.setAttribute('id','ct_no_cookie_hidden_field')
	field.setAttribute('name','ct_no_cookie_hidden_field')
	field.setAttribute('value', no_cookie_data)
	field.setAttribute('type', 'hidden')
	return field
}

function ctNoCookieGetForms(){
	let forms = document.forms
	if (forms) {
		return forms
	}
	return false
}

function ctNoCookieAttachHiddenFieldsToForms(){
	let forms = ctNoCookieGetForms()

	if (forms){
		for ( let i = 0; i < forms.length; i++ ){
			//ignore forms with get method @todo We need to think about this
			if (document.forms[i].method.toLowerCase() !== 'get'){
				document.forms[i].append(ctNoCookieConstructHiddenField())
			}
		}
	}

}