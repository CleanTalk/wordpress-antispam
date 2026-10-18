import {initParams} from "./initParams";
import {setInputValue} from "./params/checkJs";
import {apbctLocalStorage} from './utils/apbctLocalStorage';
import {apbctSessionStorage} from './utils/apbctSessionStorage';
import {ctNoCookieAttachHiddenFieldsToForms} from './functions/noCookieHiddenField';
import {ctSetCookie, ctSetAltCookies} from './functions/storeParams';
import {sendAJAX, sendREST} from "./functions/ajaxHandlers";

if (document.readyState !== 'loading') {
    initParams();
} else {
    document.addEventListener('DOMContentLoaded', initParams);
}

// Hack - it have to be defined for the ability to use from inline JS code in the page
window.apbct_js_keys__set_input_value = setInputValue;

// Temporary solutions for the backward compatibility with the old version of the plugin. It will be removed in the future.

// ctSetCookies and ctSetAlternativeCookie are calling in the old JS files
window.ctSetCookie = ctSetCookie;
window.ctSetAlternativeCookie = ctSetAltCookies;

// apbct_js_keys__sendAJAX and apbct_js_keys__sendREST are calling in the old JS files
window.apbct_public_sendAJAX = sendAJAX;
window.apbct_public_sendREST = sendREST;

// apbctLocalStorage is calling in the old JS files
window.apbctLocalStorage = apbctLocalStorage;
// apbctSessionStorage is calling in the old JS files
window.apbctSessionStorage  = apbctSessionStorage;
// ctNoCookieAttachHiddenFieldsToForms is calling in the old JS files
window.ctNoCookieAttachHiddenFieldsToForms = ctNoCookieAttachHiddenFieldsToForms;

