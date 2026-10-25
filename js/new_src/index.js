import {initParams} from "./initParams";
import {initGathering} from './initGathering';
import {initVisibleFields} from './initVisibleFields';
import {initEmailCheckBeforePost} from './initEmailCheckBeforePost';
import {initEmailExist} from './initEmailExist';
import {initBlockMessage, showBlockMessage} from './initBlockMessage';
import {setInputValue} from "./params/checkJs";
import {apbctLocalStorage} from './utils/apbctLocalStorage';
import {apbctSessionStorage} from './utils/apbctSessionStorage';
import {ctNoCookieAttachHiddenFieldsToForms} from './functions/noCookieHiddenField';
import {ctSetCookie, ctSetAltCookies} from './functions/storeParams';
import {sendAJAX, sendREST} from "./functions/ajaxHandlers";

if (document.readyState !== 'loading') {
    apbctReady();
} else {
    document.addEventListener('DOMContentLoaded', apbctReady);
}

let apbctReadyIsDone = false;

/**
 * Runs once even if DOMContentLoaded is dispatched again by third-party code.
 */
async function apbctReady() {
    if (apbctReadyIsDone) {
        return;
    }
    apbctReadyIsDone = true;

    initParams();
    initGathering();
    initVisibleFields();
    initEmailCheckBeforePost();
    initEmailExist();
    initBlockMessage();
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
// apbctShowBlockMessage is calling in the old JS files
window.apbctShowBlockMessage = showBlockMessage;

