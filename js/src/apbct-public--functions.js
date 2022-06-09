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

(function() {

    var ct_date = new Date(),
        ctTimeMs = new Date().getTime(),
        ctMouseEventTimerFlag = true, //Reading interval flag
        ctMouseData = [],
        ctMouseDataCounter = 0,
        ctCheckedEmails = {},
        ctScrollCollected = false,
        ctMouseMovedCollected = false;

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
        if( ! ctScrollCollected ) {
            ctSetCookie("ct_has_scrolled", 'true');
            ctScrollCollected = true;
        }
    }

    function ctSetMouseMoved() {
        if( ! ctMouseMovedCollected ) {
            ctSetCookie("ct_mouse_moved", 'true');
            ctMouseMovedCollected = true;
        }
    }

    apbct_attach_event_handler(window, "mousemove", ctFunctionMouseMove);
    apbct_attach_event_handler(window, "mousedown", ctFunctionFirstKey);
    apbct_attach_event_handler(window, "keydown", ctFunctionFirstKey);
    apbct_attach_event_handler(window, "scroll", ctSetHasScrolled);

    // Ready function
    function apbct_ready(){

        // Collect scrolling info
        var initCookies = [
            ["ct_ps_timestamp", Math.floor(new Date().getTime() / 1000)],
            ["ct_fkp_timestamp", "0"],
            ["ct_pointer_data", "0"],
            ["ct_timezone", ct_date.getTimezoneOffset()/60*(-1) ],
            ["ct_screen_info", apbctGetScreenInfo()],
            ["ct_has_scrolled", 'false'],
            ["ct_mouse_moved", 'false'],
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

}());

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
    targetElement.innerText = result;
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
