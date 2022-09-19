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
    let _params            = [];
    _params["callback"]    = params.callback    || null;
    _params["onErrorCallback"] = params.onErrorCallback    || null;
    _params["callback_context"] = params.callback_context || null;
    _params["callback_params"] = params.callback_params || null;
    _params["async"]        = params.async || true;
    _params["notJson"]     = params.notJson     || null;
    _params["timeout"]     = params.timeout     || 15000;
    _params["obj"]         = obj                || null;
    _params["button"]      = params.button      || null;
    _params["progressbar"] = params.progressbar || null;
    _params["silent"]      = params.silent      || null;
    _params["no_nonce"]    = params.no_nonce    || null;
    _params["data"]        = data;
    _params["url"]         = ctPublicFunctions._ajax_url;

    if(typeof (data) === 'string') {
        if( ! _params["no_nonce"] ) {
            _params["data"] = _params["data"] + '&_ajax_nonce=' + ctPublicFunctions._ajax_nonce;
        }
        _params["data"] = _params["data"] + '&no_cache=' + Math.random()
    } else {
        if( ! _params["no_nonce"] ) {
            _params["data"]._ajax_nonce = ctPublicFunctions._ajax_nonce;
        }
        _params["data"].no_cache = Math.random();
    }

    new ApbctCore().ajax(_params);
}

function apbct_public_sendREST( route, params ) {

    let _params         = [];
    _params["route"]    = route;
    _params["callback"] = params.callback || null;
    _params["onErrorCallback"] = params.onErrorCallback    || null;
    _params["data"]     = params.data     || [];
    _params["method"]   = params.method   || 'POST';

    new ApbctCore().rest(_params);
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