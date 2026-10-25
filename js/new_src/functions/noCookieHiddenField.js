import {ApbctBrowserState} from '../utils/botDetectorLog';
import {apbctLocalStorage} from '../utils/apbctLocalStorage';
import {apbctSessionStorage} from '../utils/apbctSessionStorage';
import {checkHiddenFieldsExclusions} from './formExclusions';

export function ctNoCookieAttachHiddenFieldsToForms() {
    if (ctPublicFunctions.data__cookies_type !== 'none') {
        return;
    }

    let forms = ctGetPageForms();

    if (forms) {
        for ( let i = 0; i < forms.length; i++ ) {
            if ( checkHiddenFieldsExclusions(forms[i], 'no_cookie') ) {
                continue;
            }

            // ignore forms with get method @todo We need to think about this
            if (forms[i].getAttribute('method') === null ||
                forms[i].getAttribute('method').toLowerCase() === 'post') {
                // remove old sets
                let fields = forms[i].querySelectorAll('.ct_no_cookie_hidden_field');
                for ( let j = 0; j < fields.length; j++ ) {
                    fields[j].outerHTML = '';
                }
                // add new set
                forms[i].append(constructNoCookieHiddenField());
            }
        }
    }
}

/**
 * @return {boolean|*}
 */
function ctGetPageForms() {
    let forms = document.forms;
    if (forms) {
        return forms;
    }
    return false;
}

/**
 * Construct hidden field for no cookie
 * @param {string} type
 * @return {HTMLInputElement}
 */
function constructNoCookieHiddenField(type) {
    let inputType = 'hidden';
    if (type === 'submit') {
        inputType = 'submit';
    }
    let field = '';

    let noCookieData = getCleanTalkStorageDataArray();
    noCookieData = JSON.stringify(noCookieData);
    noCookieData = '_ct_no_cookie_data_' + btoa(unescape(encodeURIComponent(noCookieData)));
    field = document.createElement('input');
    field.setAttribute('name', 'ct_no_cookie_hidden_field');
    field.setAttribute('value', noCookieData);
    field.setAttribute('type', inputType);
    field.classList.add('apbct_special_field');
    field.classList.add('ct_no_cookie_hidden_field');
    return field;
}

/**
 * Retrieves the clentalk "cookie" data from starages.
 * Contains {...noCookieDataLocal, ...noCookieDataSession, ...noCookieDataTypo, ...noCookieDataFromUserActivity}.
 * @return {string}
 */
function getCleanTalkStorageDataArray() { // eslint-disable-line no-unused-vars
    let noCookieDataLocal = apbctLocalStorage.getCleanTalkData();
    let noCookieDataSession = apbctSessionStorage.getCleanTalkData();

    let noCookieDataTypo = {typo: []};
    if (document.ctTypoData && document.ctTypoData.data) {
        noCookieDataTypo = {typo: document.ctTypoData.data};
    }

    let noCookieDataFromUserActivity = {collecting_user_activity_data: []};

    if (document.ctCollectingUserActivityData) {
        let collectingUserActivityData = JSON.parse(JSON.stringify(document.ctCollectingUserActivityData));
        noCookieDataFromUserActivity = {collecting_user_activity_data: collectingUserActivityData};
    }

    let browserStateData = {};
    if (typeof apbctGetBrowserStatePair === 'function') {
        const browserState = apbctGetBrowserStatePair();
        if (browserState) {
            browserStateData[browserState.key] = browserState.value;
        }
    }

    return {
        ...noCookieDataLocal,
        ...noCookieDataSession,
        ...noCookieDataTypo,
        ...noCookieDataFromUserActivity,
        ...browserStateData,
    };
}

/**
 * Get the browser state as a key/value pair to attach it to an intercepted request.
 * @return {{key: string, value: string}|false} False if there is nothing to attach.
 */
function apbctGetBrowserStatePair() { // eslint-disable-line no-unused-vars
    try {
        ApbctBrowserState.detectScripts();
        let state = ApbctBrowserState.asDTO();
        if ( ApbctBrowserState.botdLogicLoaded ) {
            const transportEnabled = localStorage.getItem(ApbctBrowserState.TRANSPORT_ENABLED_SIGN_KEY) === '1';
            if (transportEnabled) {
                state.frontend_data_log = ApbctBrowserState.getFrontendDataLog();
            } else {
                state.transport_enabled = 0;
            }
        } else {
            state.frontend_data_log = ApbctBrowserState.getFrontendDataLog();
        }
        return {key: ApbctBrowserState.STATE_KEY, value: JSON.stringify(state)};
    } catch (e) {
        return false;
    }
}
