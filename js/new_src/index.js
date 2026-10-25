import {initParams} from "./initParams";
import {initInterceptors} from './initInterceptors';
import {initSearchForm} from './initSearchForm';
import {initGathering} from './initGathering';
import {initVisibleFields} from './initVisibleFields';
import {initEmailCheckBeforePost} from './initEmailCheckBeforePost';
import {initEmailExist} from './initEmailExist';
import {initBlockMessage} from './initBlockMessage';
import {initBotDetectorEventToken} from './params/botDetectorEventToken';
import {initBotDetectorExist} from './params/botDetectorExist';
import {initExistingVisitor} from './params/existingVisitor';
import {initCronFormsHandler} from './initCronFormsHandler';
import {initExternalForms} from './initExternalForms';
import {initInternalForms} from './initInternalForms';
import {initForceProtection} from './initForceProtection';
import {initTrp} from './initTrp';
import {initForceAltCookies} from './params/forceAltCookies';
import {setInputValue} from "./params/checkJs";
import {apbctLocalStorage} from './utils/apbctLocalStorage';
import {ctNoCookieAttachHiddenFieldsToForms, initNoCookieHiddenFields} from './functions/noCookieHiddenField';
import {ctSetCookie} from './functions/storeParams';
import {sendAJAX, sendREST} from "./functions/ajaxHandlers";
import {initJsErrors} from './functions/jsErrors';

// Errors must be caught from the very start, not only after DOMContentLoaded
initJsErrors();

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

    initInterceptors();
    initSearchForm();
    initForceAltCookies();
    initParams();
    initNoCookieHiddenFields();
    initBotDetectorEventToken();
    initBotDetectorExist();
    initGathering();
    initVisibleFields();
    initEmailCheckBeforePost();
    initEmailExist();
    initBlockMessage();
    initExternalForms();
    initInternalForms();
    initForceProtection();
    initTrp();
    initExistingVisitor();
    initCronFormsHandler();
}

// Hack - it have to be defined for the ability to use from inline JS code in the page
window.apbct_js_keys__set_input_value = setInputValue;

// Used from inline JS (PHP-generated scripts, firewall die pages) and from the old common-decoder.js / common-cleantalk-modal.js
window.ctSetCookie = ctSetCookie;
window.apbct_public_sendAJAX = sendAJAX;
window.apbct_public_sendREST = sendREST;
window.apbctLocalStorage = apbctLocalStorage;
window.ctNoCookieAttachHiddenFieldsToForms = ctNoCookieAttachHiddenFieldsToForms;

