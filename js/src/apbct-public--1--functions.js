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