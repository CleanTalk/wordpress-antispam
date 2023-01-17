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

if (ctPublic.data__key_is_ok) {
	//Reading interval
	var ctMouseReadInterval = setInterval(function(){
		ctMouseEventTimerFlag = true;
	}, 150);

	//Writting interval
	var ctMouseWriteDataInterval = setInterval(function(){
		ctSetCookie("ct_pointer_data", JSON.stringify(ctMouseData));
	}, 1200);
}


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
	apbct_remove_event_handler(document, "mousemove", ctFunctionMouseMove);
	clearInterval(ctMouseReadInterval);
	clearInterval(ctMouseWriteDataInterval);
}

//Stop key listening function
function ctKeyStopStopListening(){
	apbct_remove_event_handler(document, "mousedown", ctFunctionFirstKey);
	apbct_remove_event_handler(document, "keydown", ctFunctionFirstKey);
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
			let insertedImg = document.createElement('img');
			insertedImg.setAttribute('alt', 'CleanTalk Pixel');
			insertedImg.setAttribute('id', 'apbct_pixel');
			insertedImg.setAttribute('style', 'display: none; left: 99999px;');
			insertedImg.setAttribute('src', pixelUrl);
			apbct('body').append(insertedImg);
		}
	}
}

function ctGetPixelUrl() {
	// Check if pixel is already in localstorage and is not outdated
	let local_storage_pixel_url = apbctLocalStorage.get('apbct_pixel_url');
	if ( local_storage_pixel_url !== false ) {
		if ( apbctLocalStorage.isAlive('apbct_pixel_url', 3600 * 3) ) {
			apbctLocalStorage.delete('apbct_pixel_url')
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
						if ( ! apbctLocalStorage.get('apbct_pixel_url') ){
							//set pixel to the storage
							apbctLocalStorage.set('apbct_pixel_url', result)
							//update pixel data in the hidden fields
							ctNoCookieAttachHiddenFieldsToForms()
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
						if ( ! apbctLocalStorage.get('apbct_pixel_url') ){
							//set pixel to the storage
							apbctLocalStorage.set('apbct_pixel_url', result)
							//update pixel data in the hidden fields
							ctNoCookieAttachHiddenFieldsToForms()
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
	if ( ! apbctLocalStorage.isSet('ct_has_scrolled') || ! apbctLocalStorage.get('ct_has_scrolled') ) {
		ctSetCookie("ct_has_scrolled", 'true');
		apbctLocalStorage.set('ct_has_scrolled', true);
	}
}

function ctSetMouseMoved() {
	if ( ! apbctLocalStorage.isSet('ct_mouse_moved') || ! apbctLocalStorage.get('ct_mouse_moved') ) {
		ctSetCookie("ct_mouse_moved", 'true');
		apbctLocalStorage.set('ct_mouse_moved', true);
	}
}

//init listeners for keyup and focus events
function ctStartFieldsListening() {

	if (
		(apbctLocalStorage.isSet('ct_has_key_up') || apbctLocalStorage.get('ct_has_key_up'))
		&&
		(apbctLocalStorage.isSet('ct_has_input_focused') || apbctLocalStorage.get('ct_has_input_focused'))
	) {
		//already set
		return
	}

	let forms = ctGetPageForms();
	ctPublic.handled_fields = []

	if (forms.length > 0) {
		for (let i = 0; i < forms.length; i++) {
			//handle only inputs and textareas
			let handled_form_fields = forms[i].querySelectorAll('input,textarea')
			for (let i = 0; i < handled_form_fields.length; i++) {
				if (handled_form_fields[i].type !== 'hidden') {
					//collect handled fields to remove handler in the future
					ctPublic.handled_fields.push(handled_form_fields[i])
					//do attach handlers
					apbct_attach_event_handler(handled_form_fields[i], "focus", ctFunctionHasInputFocused)
					apbct_attach_event_handler(handled_form_fields[i], "keyup", ctFunctionHasKeyUp)
				}
			}
		}
	}
}

//stop listening keyup and focus
function ctStopFieldsListening(event_name, function_name) {
	if (typeof ctPublic.handled_fields !== 'undefined' && ctPublic.handled_fields.length > 0) {
		for (let i = 0; i < ctPublic.handled_fields.length; i++) {
			apbct_remove_event_handler(ctPublic.handled_fields[i], event_name, function_name)
		}
	}
}


let ctFunctionHasInputFocused = function output(event){
	ctSetHasInputFocused()
	ctStopFieldsListening("focus",ctFunctionHasInputFocused)
}


let ctFunctionHasKeyUp = function output(event){
	ctSetHasKeyUp()
	ctStopFieldsListening("keyup",ctFunctionHasKeyUp)
}

//set ct_has_input_focused ct_has_key_up cookies on session period
function ctSetHasInputFocused() {
	if( ! apbctLocalStorage.isSet('ct_has_input_focused') || ! apbctLocalStorage.get('ct_has_input_focused') ) {
		ctSetCookie("ct_has_input_focused", 'true');
		apbctLocalStorage.set('ct_has_input_focused', true);
	}
}

function ctSetHasKeyUp() {
	if( ! apbctLocalStorage.isSet('ct_has_key_up') || ! apbctLocalStorage.get('ct_has_key_up') ) {
		ctSetCookie("ct_has_key_up", 'true');
		apbctLocalStorage.set('ct_has_key_up', true);
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

if (ctPublic.data__key_is_ok) {
	apbct_attach_event_handler(document, "mousemove", ctFunctionMouseMove);
	apbct_attach_event_handler(document, "mousedown", ctFunctionFirstKey);
	apbct_attach_event_handler(document, "keydown", ctFunctionFirstKey);
	apbct_attach_event_handler(document, "scroll", ctSetHasScrolled);
}

// Ready function
function apbct_ready(){

	ctPreloadLocalStorage()

	// set session ID
	if (!apbctSessionStorage.isSet('apbct_session_id')) {
		const sessionID = apbctGenerateUniqueID();
		apbctSessionStorage.set('apbct_session_id', sessionID, false);
		apbctLocalStorage.set('apbct_page_hits', 1);
		if (document.referrer) {
			let urlReferer = new URL(document.referrer);
			if (urlReferer.host !== location.host) {
				apbctSessionStorage.set('apbct_site_referer', document.referrer, false);
			}
		}
	} else {
		apbctLocalStorage.set('apbct_page_hits', Number(apbctLocalStorage.get('apbct_page_hits')) + 1);
	}

	// set apbct_prev_referer
	apbctSessionStorage.set('apbct_prev_referer', document.referrer, false);

	let cookiesType = apbctLocalStorage.get('ct_cookies_type');
	if ( ! cookiesType || cookiesType !== ctPublic.data__cookies_type ) {
		apbctLocalStorage.set('ct_cookies_type', ctPublic.data__cookies_type);
		apbctLocalStorage.delete('ct_mouse_moved');
		apbctLocalStorage.delete('ct_has_scrolled');
	}

	ctStartFieldsListening()

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
		apbct("input[type = 'email'], #email").on('blur', checkEmail);
	}

	if (apbctLocalStorage.isSet('ct_checkjs')) {
		initCookies.push(['ct_checkjs', apbctLocalStorage.get('ct_checkjs')]);
	} else {
		initCookies.push(['ct_checkjs', 0]);
	}

	//detect integrated forms that need to be handled via alternative cookies
	ctDetectForcedAltCookiesForms()

	ctSetCookie(initCookies);

	setTimeout(function(){

		if (typeof ctPublic.force_alt_cookies == 'undefined' || (ctPublic.force_alt_cookies !== 'undefined' && !ctPublic.force_alt_cookies)) {
			ctNoCookieAttachHiddenFieldsToForms()
		}


		for(var i = 0; i < document.forms.length; i++){
			var form = document.forms[i];

			//Exclusion for forms
			if (
				+ctPublic.data__visible_fields_required === 0 ||
				(form.method.toString().toLowerCase() === 'get' && form.querySelectorAll('.nf-form-content').length === 0) ||
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
				(form.name && form.name.toString().indexOf('tribe-bar-form') !== -1) ||  // The Events Calendar
				(form.id && form.id === 'ihf-login-form') || //Optima Express login
				(form.id && form.id === 'subscriberForm' && form.action.toString().indexOf('actionType=update') !== -1) //Optima Express update
			) {
				continue;
			}

			var hiddenInput = document.createElement( 'input' );
			hiddenInput.setAttribute( 'type', 'hidden' );
			hiddenInput.setAttribute( 'id', 'apbct_visible_fields_' + i );
			hiddenInput.setAttribute( 'name', 'apbct_visible_fields');
			var visibleFieldsToInput = {};
			visibleFieldsToInput[0] = apbct_collect_visible_fields(form);
			hiddenInput.value = btoa(JSON.stringify(visibleFieldsToInput));
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
	let encodedEmailNodes = document.querySelectorAll("[data-original-string]");
	ctPublic.encodedEmailNodes = encodedEmailNodes
	if (encodedEmailNodes.length) {
		for (let i = 0; i < encodedEmailNodes.length; ++i) {
			if (
				encodedEmailNodes[i].parentElement.href ||
				encodedEmailNodes[i].parentElement.parentElement.href
			) {
				// Skip listening click on hyperlinks
				continue;
			}
			encodedEmailNodes[i].addEventListener('click', ctFillDecodedEmailHandler);
		}
	}

	/**
	 * WordPress Search form processing
	 */
	for (const _form of document.forms) {
		if ( (
			_form.getAttribute('id') === 'searchform'
			|| _form.getAttribute('class') === 'elementor-search-form'
			|| _form.getAttribute('class') === 'search-form'
		) && ctPublic.data__cookies_type === 'none' ) {
			_form.apbctSearchPrevOnsubmit = _form.onsubmit;
			_form.onsubmit = (e) => {
				const noCookie = _form.querySelector('[name="ct_no_cookie_hidden_field"]');
				if ( noCookie !== null ) {
					e.preventDefault();
					const callBack = () => {
						if (_form.apbctSearchPrevOnsubmit instanceof Function) {
							_form.apbctSearchPrevOnsubmit();
						} else {
							HTMLFormElement.prototype.submit.call(_form);
						}
					}

					let parsedCookies = atob(noCookie.value);

					if ( parsedCookies.length !== 0 ) {
						ctSetAlternativeCookie(
							parsedCookies,
							{callback: callBack, onErrorCallback: callBack, forceAltCookies: true}
						);
					} else {
						callBack();
					}
				}
			}
		}
	}
}
if (ctPublic.data__key_is_ok) {
	if (document.readyState !== 'loading') {
		apbct_ready();
	} else {
		apbct_attach_event_handler(document, "DOMContentLoaded", apbct_ready);
	}
}

function ctFillDecodedEmailHandler(event) {
	this.removeEventListener('click', ctFillDecodedEmailHandler);
	//remember click_source
	let click_source = this
	//globally remember if emails is mixed with mailto
	ctPublic.encodedEmailNodesIsMixed = false
	//get fade
	document.body.classList.add('apbct-popup-fade')
	//popup show
	let encoder_popup = document.getElementById('apbct_popup')
	if (!encoder_popup){
		let waiting_popup = document.createElement('div')
		waiting_popup.setAttribute('class', 'apbct-popup')
		waiting_popup.setAttribute('id', 'apbct_popup')
		let popup_text = document.createElement('p')
		popup_text.setAttribute('id', 'apbct_popup_text')
		popup_text.innerText = "Please wait while CleanTalk decoding email addresses.."
		waiting_popup.append(popup_text)
		document.body.append(waiting_popup)
	} else {
		encoder_popup.setAttribute('style','display: inherit')
		document.getElementById('apbct_popup_text').innerHTML = "Please wait while CleanTalk decoding email addresses.."
	}

	apbctAjaxEmailDecodeBulk(event,ctPublic.encodedEmailNodes,click_source)
}

function apbctAjaxEmailDecodeBulk(event, encodedEmailNodes, click_source){
	//collect data
	const javascriptClientData = getJavascriptClientData();
	let data = {
		event_javascript_data: javascriptClientData,
		post_url: document.location.href,
		referrer: document.referrer,
		encodedEmails: ''
	};
	let encoded_emails_collection = {}
	for (let i = 0; i < encodedEmailNodes.length; i++){
		//disable click for mailto
		if (typeof encodedEmailNodes[i].href !== 'undefined' && encodedEmailNodes[i].href.indexOf('mailto:') === 0) {
			event.preventDefault()
			ctPublic.encodedEmailNodesIsMixed = true;
		}

		// Adding a tooltip
		let apbctTooltip = document.createElement('div');
		apbctTooltip.setAttribute('class', 'apbct-tooltip');
		apbct(encodedEmailNodes[i]).append(apbctTooltip);

		//collect encoded email strings
		encoded_emails_collection[i] = encodedEmailNodes[i].dataset.originalString;
	}

	//JSONify encoded email strings
	data.encodedEmails = JSON.stringify(encoded_emails_collection)

	// Using REST API handler
	if( ctPublicFunctions.data__ajax_type === 'rest' ){
		apbct_public_sendREST(
			'apbct_decode_email',
			{
				data: data,
				method: 'POST',
				callback: function(result) {
					//set alternative cookie to skip next pages encoding
					ctSetCookie('apbct_email_encoder_passed','1')
					apbctEmailEncoderCallbackBulk(result, encodedEmailNodes, click_source);
				},
				onErrorCallback: function (res) {
					resetEncodedNodes()
					ctShowDecodeComment(res);
				},
			}
		);

		// Using AJAX request and handler
	}else{
		data.action = 'apbct_decode_email';
		apbct_public_sendAJAX(
			data,
			{
				notJson: false,
				callback: function(result) {
					//set alternative cookie to skip next pages encoding
					ctSetCookie('apbct_email_encoder_passed','1')
					apbctEmailEncoderCallbackBulk(result, encodedEmailNodes, click_source);
				},
				onErrorCallback: function (res) {
					resetEncodedNodes()
					ctShowDecodeComment(res);
				},
			}
		);
	}
}

function apbctEmailEncoderCallbackBulk(result, encodedEmailNodes, click_source){

	if (result.success && result.data[0].is_allowed === true){
		//start process of visual decoding
		setTimeout(function(){
			for (let i = 0; i < encodedEmailNodes.length; i++) {
				//chek what is what
				let current_result_data
				result.data.forEach((row) => {
					if (row.encoded_email === encodedEmailNodes[i].dataset.originalString){
						current_result_data = row
					}
				})
				//quit case on cloud block
				if (current_result_data.is_allowed === false){
					break;
				}
				//handler for mailto
				if (typeof encodedEmailNodes[i].href !== 'undefined' && encodedEmailNodes[i].href.indexOf('mailto:') === 0) {
					let encodedEmail = encodedEmailNodes[i].href.replace('mailto:', '');
					let baseElementContent = encodedEmailNodes[i].innerHTML;
					encodedEmailNodes[i].innerHTML = baseElementContent.replace(encodedEmail, current_result_data.decoded_email);
					encodedEmailNodes[i].href = 'mailto:' + current_result_data.decoded_email;
				}
				// fill the nodes
				ctProcessDecodedDataResult(current_result_data, encodedEmailNodes[i]);
				//remove listeners
				encodedEmailNodes[i].removeEventListener('click', ctFillDecodedEmailHandler)
			}
			//popup remove
			let popup = document.getElementById('apbct_popup')
			if (popup !== null){
				document.body.classList.remove('apbct-popup-fade')
				popup.setAttribute('style','display:none')
				//click on mailto if so
				if (ctPublic.encodedEmailNodesIsMixed){
					click_source.click();
				}
			}
		}, 3000);
	} else {
		if (result.success){
			resetEncodedNodes()
			ctShowDecodeComment('Blocked: ' + result.data[0].comment)
		} else {
			resetEncodedNodes()
			ctShowDecodeComment('Cannot connect with CleanTalk server: ' + result.data[0].comment)
		}
	}
}

function resetEncodedNodes(){
	if (typeof ctPublic.encodedEmailNodes !== 'undefined'){
		ctPublic.encodedEmailNodes.forEach(function (element){
			element.addEventListener('click', ctFillDecodedEmailHandler);
		})
	}
}

function getJavascriptClientData(common_cookies = []) {
	let resultDataJson = {};

	resultDataJson.apbct_headless = !!ctGetCookie(ctPublicFunctions.cookiePrefix + 'apbct_headless');
	resultDataJson.apbct_pixel_url = ctGetCookie(ctPublicFunctions.cookiePrefix + 'apbct_pixel_url');
	resultDataJson.ct_checked_emails = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_checked_emails');
	resultDataJson.ct_checkjs = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_checkjs');
	resultDataJson.ct_fkp_timestamp = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_fkp_timestamp');
	resultDataJson.ct_pointer_data = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_pointer_data');
	resultDataJson.ct_ps_timestamp = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_ps_timestamp');
	resultDataJson.ct_screen_info = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_screen_info');
	resultDataJson.ct_timezone = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_timezone');

	// collecting data from localstorage
	const ctMouseMovedLocalStorage = apbctLocalStorage.get(ctPublicFunctions.cookiePrefix + 'ct_mouse_moved');
	const ctHasScrolledLocalStorage = apbctLocalStorage.get(ctPublicFunctions.cookiePrefix + 'ct_has_scrolled');
	const ctCookiesTypeLocalStorage = apbctLocalStorage.get(ctPublicFunctions.cookiePrefix + 'ct_cookies_type');
	const apbctPageHits = apbctLocalStorage.get('apbct_page_hits');
	const apbctPrevReferer = apbctSessionStorage.get('apbct_prev_referer');
	const apbctSiteReferer = apbctSessionStorage.get('apbct_site_referer');

	// collecting data from cookies
	const ctMouseMovedCookie = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_mouse_moved');
	const ctHasScrolledCookie = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_has_scrolled');
	const ctCookiesTypeCookie = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_cookies_type');

	resultDataJson.ct_mouse_moved = ctMouseMovedLocalStorage !== undefined ? ctMouseMovedLocalStorage : ctMouseMovedCookie;
	resultDataJson.ct_has_scrolled = ctHasScrolledLocalStorage !== undefined ? ctHasScrolledLocalStorage : ctHasScrolledCookie;
	resultDataJson.ct_cookies_type = ctCookiesTypeLocalStorage !== undefined ? ctCookiesTypeLocalStorage : ctCookiesTypeCookie;
	resultDataJson.apbct_page_hits = apbctPageHits;
	resultDataJson.apbct_prev_referer = apbctPrevReferer;
	resultDataJson.apbct_site_referer = apbctSiteReferer;

	if (
		typeof (common_cookies) === "object"
		&& common_cookies !== []
	){
		for (let i = 0; i < common_cookies.length; ++i){
			if ( typeof (common_cookies[i][1]) === "object" ){
				//this is for handle SFW cookies
				resultDataJson[common_cookies[i][1][0]] = common_cookies[i][1][1]
			} else {
				resultDataJson[common_cookies[i][0]] = common_cookies[i][1]
			}
		}
	} else {
		console.log('APBCT JS ERROR: Collecting data type mismatch')
	}

	// Parse JSON properties to prevent double JSON encoding
	resultDataJson = removeDoubleJsonEncoding(resultDataJson);

	return JSON.stringify(resultDataJson);
}

/**
 * Recursive
 *
 * Recursively decode JSON-encoded properties
 *
 * @param object
 * @returns {*}
 */
function removeDoubleJsonEncoding(object){

	if( typeof object === 'object'){

		for (let objectKey in object) {

			// Recursion
			if( typeof object[objectKey] === 'object'){
				object[objectKey] = removeDoubleJsonEncoding(object[objectKey]);
			}

			// Common case (out)
			if(
				typeof object[objectKey] === 'string' &&
				object[objectKey].match(/^[\[{].*?[\]}]$/) !== null // is like JSON
			){
				let parsedValue = JSON.parse(object[objectKey]);
				if( typeof parsedValue === 'object' ){
					object[objectKey] = parsedValue;
				}
			}
		}
	}

	return object;
}

function ctProcessDecodedDataResult(response, targetElement) {

	targetElement.setAttribute('title', '');
	targetElement.removeAttribute('style');
	ctFillDecodedEmail(targetElement, response.decoded_email);
}

function ctFillDecodedEmail(target, email){

	apbct(target).html(
		apbct(target)
			.html()
			.replace(/.+?(<div class=["']apbct-tooltip["'].+?<\/div>)/, email + "$1")
	);
}

function ctShowDecodeComment(comment){

	if( ! comment ){
		comment = 'Can not decode email. Unknown reason'
	}

	let popup = document.getElementById('apbct_popup')
	let popup_text = document.getElementById('apbct_popup_text')
	if (popup !== null){
		document.body.classList.remove('apbct-popup-fade')
		popup_text.innerText = "CleanTalk email decoder: " + comment
		setTimeout(function(){
			popup.setAttribute('style','display:none')
		},3000)
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
		if (ctPublic.data__cookies_type === 'none'){
			ctSetCookie("apbct_visible_fields", JSON.stringify( collection[0] ) );
		} else {
			ctSetCookie("apbct_visible_fields", JSON.stringify( collection ) );
		}

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
				console.log(e.toString());
				return;
			}
			ctParseBlockMessage(response);
		}
	});
}

function ctParseBlockMessage(response) {

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

function ctSetPixelUrlLocalstorage(ajax_pixel_url) {
	//set pixel to the storage
	ctSetCookie('apbct_pixel_url', ajax_pixel_url)
}

function ctNoCookieConstructHiddenField(type){
	let inputType = 'hidden';
	if (type === 'submit') {
		inputType = 'submit';
	}
	let field = ''
	let no_cookie_data_local = apbctLocalStorage.getCleanTalkData()
	let no_cookie_data_session = apbctSessionStorage.getCleanTalkData()
	let no_cookie_data = {...no_cookie_data_local, ...no_cookie_data_session};
	no_cookie_data = JSON.stringify(no_cookie_data)
	no_cookie_data = '_ct_no_cookie_data_' + btoa(no_cookie_data)
	field = document.createElement('input')
	field.setAttribute('id','ct_no_cookie_hidden_field')
	field.setAttribute('name','ct_no_cookie_hidden_field')
	field.setAttribute('value', no_cookie_data)
	field.setAttribute('type', inputType)
	field.classList.add('apbct_special_field');
	return field
}

function ctGetPageForms(){
	let forms = document.forms
	if (forms) {
		return forms
	}
	return false
}

function ctNoCookieAttachHiddenFieldsToForms(){

	if (ctPublic.data__cookies_type !== 'none'){
		return
	}

	let forms = ctGetPageForms()

	if (forms){
		//clear previous hidden set
		let elements = document.getElementsByName('ct_no_cookie_hidden_field')
		if (elements){
			for (let j = 0; j < elements.length; j++) {
				elements[j].parentNode.removeChild(elements[j])
			}
		}
		for ( let i = 0; i < forms.length; i++ ){
			//ignore forms with get method @todo We need to think about this
			if (document.forms[i].getAttribute('method') === null ||
				document.forms[i].getAttribute('method').toLowerCase() === 'post'){
				// add new set
				document.forms[i].append(ctNoCookieConstructHiddenField());
			}
			if ( (
				document.forms[i].getAttribute('id') === 'searchform'
				|| document.forms[i].getAttribute('class') === 'elementor-search-form'
				|| document.forms[i].getAttribute('class') === 'search-form'
			)){
				document.forms[i].append(ctNoCookieConstructHiddenField('submit'));
			}
		}
	}

}