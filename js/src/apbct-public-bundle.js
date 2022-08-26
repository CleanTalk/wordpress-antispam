function ctSetCookie( cookies, value, expires ){

    if( typeof cookies === 'string' && typeof value === 'string' || typeof value === 'number'){
        var skip_alt = cookies === 'ct_pointer_data';
        cookies = [ [ cookies, value, expires ] ];
    }

    // Cookies disabled
    if( ctPublicFunctions.data__cookies_type === 'none' ){
        return;

        // Using traditional cookies
    }else if( ctPublicFunctions.data__cookies_type === 'native' ){
        cookies.forEach( function (item, i, arr	) {
            var expires = typeof item[2] !== 'undefined' ? "expires=" + expires + '; ' : '';
            var ctSecure = location.protocol === 'https:' ? '; secure' : '';
            document.cookie = ctPublicFunctions.cookiePrefix + item[0] + "=" + encodeURIComponent(item[1]) + "; " + expires + "path=/; samesite=lax" + ctSecure;
        });

        // Using alternative cookies
    }else if( ctPublicFunctions.data__cookies_type === 'alternative' && ! skip_alt ){

        // Using REST API handler
        if( ctPublicFunctions.data__ajax_type === 'rest' ){
            apbct_public_sendREST(
                'alt_sessions',
                {
                    method: 'POST',
                    data: { cookies: cookies }
                }
            );

        // Using AJAX request and handler
        } else if( ctPublicFunctions.data__ajax_type === 'admin_ajax' ) {
            apbct_public_sendAJAX(
                {
                    action: 'apbct_alt_session__save__AJAX',
                    cookies: cookies,
                },
                {
                    notJson: 1,
                }
            );
        }
    }
}

/**
 * Get cookie by name
 * @param name
 * @returns {string|undefined}
 */
function ctGetCookie(name) {
    var matches = document.cookie.match(new RegExp(
        "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
    ));
    return matches ? decodeURIComponent(matches[1]) : undefined;
}

function ctDeleteCookie(cookieName) {
    // Cookies disabled
    if( ctPublicFunctions.data__cookies_type === 'none' ){
        return;

    // Using traditional cookies
    }else if( ctPublicFunctions.data__cookies_type === 'native' ){

        var ctSecure = location.protocol === 'https:' ? '; secure' : '';
        document.cookie = cookieName + "=\"\"; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; samesite=lax" + ctSecure;

    // Using alternative cookies
    }else if( ctPublicFunctions.data__cookies_type === 'alternative' ){
        // @ToDo implement this logic
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
            data = data + '&_ajax_nonce=' + ctPublicFunctions._ajax_nonce;
        data = data + '&no_cache=' + Math.random()
    } else {
        if( ! no_nonce )
            data._ajax_nonce = ctPublicFunctions._ajax_nonce;
        data.no_cache = Math.random();
    }
    // Button and spinner
    if(button)  {button.setAttribute('disabled', 'disabled'); button.style.cursor = 'not-allowed'; }
    if(spinner) jQuery(spinner).css('display', 'inline');

    jQuery.ajax({
        type: "POST",
        url: ctPublicFunctions._ajax_url,
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
                        callback.apply( callback_context, [ result, data, params, obj ].concat(callback_params) );
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
        url: ctPublicFunctions._rest_url + 'cleantalk-antispam/v1/' + route,
        data: data,
        beforeSend : function ( xhr ) {
            xhr.setRequestHeader( 'X-WP-Nonce', ctPublicFunctions._rest_nonce );
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

apbctLocalStorage = {
    get : function(key, property) {
        if ( typeof property === 'undefined' ) {
            property = 'value';
        }
        const storageValue = localStorage.getItem(key);
        if ( storageValue !== null ) {
            try {
                const json = JSON.parse(storageValue);
                return json.hasOwnProperty(property) ? JSON.parse(json[property]) : json;
            } catch (e) {
                return new Error(e);
            }
        }
        return false;
    },
    set : function(key, value, is_json = true) {
        if (is_json){
            let objToSave = {'value': JSON.stringify(value), 'timestamp': Math.floor(new Date().getTime() / 1000)};
            localStorage.setItem(key, JSON.stringify(objToSave));
        } else {
            localStorage.setItem(key, value);
        }
    },
    isAlive : function(key, maxLifetime) {
        if ( typeof maxLifetime === 'undefined' ) {
            maxLifetime = 86400;
        }
        const keyTimestamp = this.get(key, 'timestamp');
        return keyTimestamp + maxLifetime > Math.floor(new Date().getTime() / 1000);
    },
    isSet : function(key) {
        return localStorage.getItem(key) !== null;
    },
    delete : function (key) {
        localStorage.removeItem(key);
    }
}
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
		apbctLocalStorage.set('ct_has_scrolled', true);
	}
}

function ctSetMouseMoved() {
	if( ! apbctLocalStorage.isSet('ct_mouse_moved') || ! apbctLocalStorage.get('ct_mouse_moved') ) {
		ctSetCookie("ct_mouse_moved", 'true');
		apbctLocalStorage.set('ct_mouse_moved', true);
	}
}

apbct_attach_event_handler(window, "mousemove", ctFunctionMouseMove);
apbct_attach_event_handler(window, "mousedown", ctFunctionFirstKey);
apbct_attach_event_handler(window, "keydown", ctFunctionFirstKey);
apbct_attach_event_handler(window, "scroll", ctSetHasScrolled);

// Ready function
function apbct_ready(){

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

	// Adding a tooltip
	jQuery(element).append(
		'<div class="apbct-tooltip">\n' +
			'<div class="apbct-tooltip--text"></div>\n' +
			'<div class="apbct-tooltip--arrow"></div>\n' +
		'</div>'
	);
	ctShowDecodeComment(element, ctPublicFunctions.text__wait_for_decoding);

	const javascriptClientData = getJavascriptClientData();

	// Using REST API handler
	if( ctPublicFunctions.data__ajax_type === 'rest' ){
		apbct_public_sendREST(
			'apbct_decode_email',
			{
				data: {
					encodedEmail:             event.target.dataset.originalString,
					event_javascript_data:    javascriptClientData,
				},
				method: 'POST',
				callback: function (result) {
					if (result.success) {
						ctProcessDecodedDataResult(result.data, event.target);
					}
					setTimeout(function () {
						jQuery(element)
							.children('.apbct-tooltip')
							.fadeOut(700);
					}, 4000);
				},
			}
		);
		// Using AJAX request and handler
	}else{
		apbct_public_sendAJAX(
			{
				action: 'apbct_decode_email',
				encodedEmail: event.target.dataset.originalString,
				event_javascript_data:    javascriptClientData,
			},
			{
				notJson: true,
				callback: function (result) {
					if (result.success) {
						ctProcessDecodedDataResult(result.data, event.target);
					}
					setTimeout(function () {
						jQuery(element)
							.children('.apbct-tooltip')
							.fadeOut(700);
					}, 4000);
				},
			}
		);
	}
}

function getJavascriptClientData() {
	let resultDataJson = {};

	resultDataJson.apbct_headless = ctGetCookie(ctPublicFunctions.cookiePrefix + 'apbct_headless');
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

	// collecting data from cookies
	const ctMouseMovedCookie = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_mouse_moved');
	const ctHasScrolledCookie = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_has_scrolled');
	const ctCookiesTypeCookie = ctGetCookie(ctPublicFunctions.cookiePrefix + 'ct_cookies_type');

	resultDataJson.ct_mouse_moved = ctMouseMovedLocalStorage !== undefined ? ctMouseMovedLocalStorage : ctMouseMovedCookie;
	resultDataJson.ct_has_scrolled = ctHasScrolledLocalStorage !== undefined ? ctHasScrolledLocalStorage : ctHasScrolledCookie;
	resultDataJson.ct_cookies_type = ctCookiesTypeLocalStorage !== undefined ? ctCookiesTypeLocalStorage : ctCookiesTypeCookie;

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

	if( !! response.is_allowed) {
		ctFillDecodedEmail(targetElement, response.decoded_email);
	}

	if( !! response.show_comment ){
		ctShowDecodeComment(targetElement, response.comment);
	}
}

function ctFillDecodedEmail(target, email){
	let tooltip = jQuery(target).children('.apbct-tooltip');
    jQuery(target).html(email)
				  .append(tooltip);
}

function ctShowDecodeComment(target, comment){
	jQuery(target).find('.apbct-tooltip')
		.show()
		.find('.apbct-tooltip--text').html(comment);
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
/* Cleantalk Modal object */
cleantalkModal = {

    // Flags
    loaded: false,
    loading: false,
    opened: false,
    opening: false,

    // Methods
    load: function( action ) {
        if( ! this.loaded ) {
            this.loading = true;
            callback = function( result, data, params, obj ) {
                cleantalkModal.loading = false;
                cleantalkModal.loaded = result;
                document.dispatchEvent(
                    new CustomEvent( "cleantalkModalContentLoaded", {
                        bubbles: true,
                    } )
                );
            };
            if( typeof apbct_admin_sendAJAX === "function" ) {
                apbct_admin_sendAJAX( { 'action' : action }, { 'callback': callback, 'notJson': true } );
            } else {
                apbct_public_sendAJAX( { 'action' : action }, { 'callback': callback, 'notJson': true } );
            }

        }
    },

    open: function () {
        /* Cleantalk Modal CSS start */
        var renderCss = function () {
            var cssStr = '';
            for ( key in this.styles ) {
                cssStr += key + ':' + this.styles[key] + ';';
            }
            return cssStr;
        };
        var overlayCss = {
            styles: {
                "z-index": "9999",
                "position": "fixed",
                "top": "0",
                "left": "0",
                "width": "100%",
                "height": "100%",
                "background": "rgba(0,0,0,0.5)",
                "display": "flex",
                "justify-content" : "center",
                "align-items" : "center",
            },
            toString: renderCss
        };
        var innerCss = {
            styles: {
                "position" : "relative",
                "padding" : "30px",
                "background" : "#FFF",
                "border" : "1px solid rgba(0,0,0,0.75)",
                "border-radius" : "4px",
                "box-shadow" : "7px 7px 5px 0px rgba(50,50,50,0.75)",
            },
            toString: renderCss
        };
        var closeCss = {
            styles: {
                "position" : "absolute",
                "background" : "#FFF",
                "width" : "20px",
                "height" : "20px",
                "border" : "2px solid rgba(0,0,0,0.75)",
                "border-radius" : "15px",
                "cursor" : "pointer",
                "top" : "-8px",
                "right" : "-8px",
                "box-sizing" : "content-box",
            },
            toString: renderCss
        };
        var closeCssBefore = {
            styles: {
                "content" : "\"\"",
                "display" : "block",
                "position" : "absolute",
                "background" : "#000",
                "border-radius" : "1px",
                "width" : "2px",
                "height" : "16px",
                "top" : "2px",
                "left" : "9px",
                "transform" : "rotate(45deg)",
            },
            toString: renderCss
        };
        var closeCssAfter = {
            styles: {
                "content" : "\"\"",
                "display" : "block",
                "position" : "absolute",
                "background" : "#000",
                "border-radius" : "1px",
                "width" : "2px",
                "height" : "16px",
                "top" : "2px",
                "left" : "9px",
                "transform" : "rotate(-45deg)",
            },
            toString: renderCss
        };
        var bodyCss = {
            styles: {
                "overflow" : "hidden",
            },
            toString: renderCss
        };
        var cleantalkModalStyle = document.createElement( 'style' );
        cleantalkModalStyle.setAttribute( 'id', 'cleantalk-modal-styles' );
        cleantalkModalStyle.innerHTML = 'body.cleantalk-modal-opened{' + bodyCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-overlay{' + overlayCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close{' + closeCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close:before{' + closeCssBefore + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close:after{' + closeCssAfter + '}';
        document.body.append( cleantalkModalStyle );
        /* Cleantalk Modal CSS end */

        var overlay = document.createElement( 'div' );
        overlay.setAttribute( 'id', 'cleantalk-modal-overlay' );
        document.body.append( overlay );

        document.body.classList.add( 'cleantalk-modal-opened' );

        var inner = document.createElement( 'div' );
        inner.setAttribute( 'id', 'cleantalk-modal-inner' );
        inner.setAttribute( 'style', innerCss );
        overlay.append( inner );

        var close = document.createElement( 'div' );
        close.setAttribute( 'id', 'cleantalk-modal-close' );
        inner.append( close );

        var content = document.createElement( 'div' );
        if ( this.loaded ) {
            content.innerHTML = this.loaded;
        } else {
            content.innerHTML = 'Loading...';
            // @ToDo Here is hardcoded parameter. Have to get this from a 'data-' attribute.
            this.load( 'get_options_template' );
        }
        content.setAttribute( 'id', 'cleantalk-modal-content' );
        inner.append( content );

        this.opened = true;
    },

    close: function () {
        document.body.classList.remove( 'cleantalk-modal-opened' );
        document.getElementById( 'cleantalk-modal-overlay' ).remove();
        document.getElementById( 'cleantalk-modal-styles' ).remove();
        document.dispatchEvent(
            new CustomEvent( "cleantalkModalClosed", {
                bubbles: true,
            } )
        );
    }

};

/* Cleantalk Modal helpers */
document.addEventListener('click',function( e ){
    if( e.target && e.target.id === 'cleantalk-modal-overlay' || e.target.id === 'cleantalk-modal-close' ){
        cleantalkModal.close();
    }
});
document.addEventListener("cleantalkModalContentLoaded", function( e ) {
    if( cleantalkModal.opened && cleantalkModal.loaded ) {
        document.getElementById( 'cleantalk-modal-content' ).innerHTML = cleantalkModal.loaded;
    }
});
jQuery(document).ready(function(){

	if(
		typeof ctPublicGDPR === 'undefined' ||
		! ctPublicGDPR.gdpr_forms.length
	) {
		return;
	}
	
	ctPublicGDPR.gdpr_forms.forEach(function(item, i, arr){
		
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
			// WPForms
			else if(jQuery('.wpforms-form')[0] && jQuery('.wpforms-form').first().attr('id').indexOf('wpforms-form-'+item) !== -1)
				elem = jQuery('.wpforms-form');
		}
		
		// Adding notice
		if(elem.is('form') || elem.attr('role') === 'form'){
			elem.append('<input id="apbct_gdpr_'+i+'" type="checkbox" required="required " style="display: inline; margin-right: 10px;">')
				.append('<label style="display: inline;" for="apbct_gdpr_'+i+'">'+ctPublicGDPR.gdpr_text+'</label>');
		}
	});
	
});
/**
 * Handle external forms
 */
function ct_protect_external() {
    for(var i = 0; i < document.forms.length; i++) {

        if (document.forms[i].cleantalk_hidden_action === undefined && document.forms[i].cleantalk_hidden_method === undefined) {

            // current form
            var currentForm = document.forms[i];

            if (currentForm.parentElement && currentForm.parentElement.classList.length > 0 && currentForm.parentElement.classList[0].indexOf('mewtwo') !== -1){
                return
            }

            if(typeof(currentForm.action) == 'string') {

                if(isIntegratedForm(currentForm)) {

                    jQuery( currentForm ).before('<i class="cleantalk_placeholder" style="display: none;"></i>');

                    // Deleting form to prevent submit event
                    var prev = jQuery(currentForm).prev(),
                        form_html = currentForm.outerHTML,
                        form_original = jQuery(currentForm).detach();

                    prev.after( form_html );

                    var force_action = document.createElement("input");
                    force_action.name = 'action';
                    force_action.value = 'cleantalk_force_ajax_check';
                    force_action.type = 'hidden';

                    var reUseCurrentForm = document.forms[i];

                    reUseCurrentForm.appendChild(force_action);

                    // mailerlite integration - disable click on submit button
                    let mailerlite_detected_class = false
                    if (reUseCurrentForm.classList !== undefined) {
                        //list there all the mailerlite classes
                        let mailerlite_classes = ['newsletterform', 'ml-block-form']
                        mailerlite_classes.forEach(function(mailerlite_class) {
                            if (reUseCurrentForm.classList.contains(mailerlite_class)){
                                mailerlite_detected_class = mailerlite_class
                            }
                        });
                    }
                    if ( mailerlite_detected_class ) {
                        let mailerliteSubmitButton = jQuery('form.' + mailerlite_detected_class).find('button[type="submit"]');
                        if ( mailerliteSubmitButton !== undefined ) {
                            mailerliteSubmitButton.click(function (event) {
                                event.preventDefault();
                                sendAjaxCheckingFormData(reUseCurrentForm, prev, form_original);
                            });
                        }
                    } else {
                        document.forms[i].onsubmit = function ( event ){
                            event.preventDefault();

                            const prev = jQuery(event.currentTarget).prev();
                            const form_original = jQuery(event.currentTarget).clone();

                            sendAjaxCheckingFormData(event.currentTarget, prev, form_original);
                        };
                    }

                    // Common flow
                }else if(currentForm.action.indexOf('http://') !== -1 || currentForm.action.indexOf('https://') !== -1) {

                    var tmp = currentForm.action.split('//');
                    tmp = tmp[1].split('/');
                    var host = tmp[0].toLowerCase();

                    if(host !== location.hostname.toLowerCase()){

                        var ct_action = document.createElement("input");
                        ct_action.name = 'cleantalk_hidden_action';
                        ct_action.value = currentForm.action;
                        ct_action.type = 'hidden';
                        currentForm.appendChild(ct_action);

                        var ct_method = document.createElement("input");
                        ct_method.name = 'cleantalk_hidden_method';
                        ct_method.value = currentForm.method;
                        ct_method.type = 'hidden';

                        currentForm.method = 'POST'

                        currentForm.appendChild(ct_method);

                        currentForm.action = document.location;
                    }
                }
            }
        }

    }
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
window.onload = function () {

    if( ! +ctPublic.settings__forms__check_external ) {
        return;
    }

    setTimeout(function () {
        ct_protect_external()
    }, 1500);
};

/**
 * Checking the form integration
 */
function isIntegratedForm(formObj) {
    var formAction = formObj.action;

    if(
        formAction.indexOf('activehosted.com') !== -1 ||   // ActiveCampaign form
        formAction.indexOf('app.convertkit.com') !== -1 || // ConvertKit form
        ( formObj.firstChild.classList !== undefined && formObj.firstChild.classList.contains('cb-form-group') ) || // Convertbox form
        formAction.indexOf('mailerlite.com') !== -1 || // Mailerlite integration
        formAction.indexOf('colcolmail.co.uk') !== -1 || // colcolmail.co.uk integration
        formAction.indexOf('paypal.com') !== -1 ||
        formAction.indexOf('infusionsoft.com') !== -1 ||
        formAction.indexOf('webto.salesforce.com') !== -1 ||
        formAction.indexOf('secure2.convio.net') !== -1 ||
        formAction.indexOf('hookb.in') !== -1 ||
        formAction.indexOf('external.url') !== -1 ||
        formAction.indexOf('tp.media') !== -1 ||
        formAction.indexOf('flodesk.com') !== -1 ||
        formAction.indexOf('sendfox.com') !== -1 ||
        formAction.indexOf('aweber.com') !== -1

    ) {
        return true;
    }

    return false;
}

/**
 * Sending Ajax for checking form data
 */
function sendAjaxCheckingFormData(form, prev, formOriginal) {
    // Get visible fields and set cookie
    var visible_fields = {};
    visible_fields[0] = apbct_collect_visible_fields(form);
    apbct_visible_fields_set_cookie( visible_fields );

    var data = {};
    var elems = form.elements;
    elems = Array.prototype.slice.call(elems);

    elems.forEach( function( elem, y ) {
        if( elem.name === '' ) {
            data['input_' + y] = elem.value;
        } else {
            data[elem.name] = elem.value;
        }
    });

    apbct_public_sendAJAX(
        data,
        {
            async: false,
            callback: function( result, data, params, obj, prev, formOriginal ){

                if( result.apbct === undefined || ! +result.apbct.blocked ) {

                    var form_new = jQuery(form).detach();

                    apbct_replace_inputs_values_from_other_form(form_new, formOriginal);

                    prev.after( formOriginal );

                    // Common click event
                    var subm_button = jQuery(formOriginal).find('button[type=submit]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                        return;
                    }

                    subm_button = jQuery(formOriginal).find('input[type=submit]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                        return;
                    }

                    // ConvertKit direct integration
                    subm_button = jQuery(formOriginal).find('button[data-element="submit"]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                        return;
                    }

                    // Paypal integration
                    subm_button = jQuery(formOriginal).find('input[type="image"][name="submit"]');
                    if( subm_button.length !== 0 ) {
                        subm_button[0].click();
                    }

                }
            },
            callback_context: null,
            callback_params: [prev, formOriginal],
        }
    );
}

function ct_check_internal(currForm){
    
//Gathering data
    var ct_data = {},
        elems = currForm.elements;

    for (var key in elems) {
        if(elems[key].type == 'submit' || elems[key].value == undefined || elems[key].value == '')
            continue;
        ct_data[elems[key].name] = currForm.elements[key].value;
    }
    ct_data['action'] = 'ct_check_internal';

//AJAX Request
    jQuery.ajax({
        type: 'POST',
        url: ctPublicFunctions._ajax_url,
        datatype : 'text',
        data: ct_data,
        success: function(data){
            if(data == 'true'){
                currForm.submit();
            }else{
                alert(data);
                return false;
            }
        },
        error: function(){
            currForm.submit();
        }
    });        
}
    
jQuery(document).ready( function(){
    let ct_currAction = '',
        ct_currForm = '';

    if( ! +ctPublic.settings__forms__check_internal ) {
        return;
    }

	for( let i=0; i<document.forms.length; i++ ){
		if ( typeof(document.forms[i].action) == 'string' ){
            ct_currForm = document.forms[i];
			ct_currAction = ct_currForm.action;
            if (
                ct_currAction.indexOf('https?://') !== null &&                        // The protocol is obligatory
                ct_currAction.match(ctPublic.blog_home + '.*?\.php') !== null && // Main check
                ! ct_check_internal__is_exclude_form(ct_currAction)                  // Exclude WordPress native scripts from processing
            ) {
                ctPrevHandler = ct_currForm.click;
                jQuery(ct_currForm).off('**');
                jQuery(ct_currForm).off();
                jQuery(ct_currForm).on('submit', function(event){
                    ct_check_internal(event.target);
                    return false;
                });
            }
		}
	}
});

/**
 * Check by action to exclude the form checking
 * @param action string
 * @return boolean
 */
function ct_check_internal__is_exclude_form(action) {
    // An array contains forms action need to be excluded.
    let ct_internal_script_exclusions = [
        ctPublic.blog_home + 'wp-login.php', // WordPress login page
        ctPublic.blog_home + 'wp-comments-post.php', // WordPress Comments Form
    ];

    return ct_internal_script_exclusions.some((item) => {
        return action.match(new RegExp('^' + item)) !== null;
    });
}