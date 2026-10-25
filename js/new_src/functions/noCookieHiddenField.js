import {ApbctBrowserState} from '../utils/botDetectorLog';
import {apbctLocalStorage} from '../utils/apbctLocalStorage';
import {apbctSessionStorage} from '../utils/apbctSessionStorage';

export function ctNoCookieAttachHiddenFieldsToForms() {
    if (ctPublicFunctions.data__cookies_type !== 'none') {
        return;
    }

    let forms = ctGetPageForms();

    if (forms) {
        for ( let i = 0; i < forms.length; i++ ) {
            if ( checkHiddenFieldsExclusions(document.forms[i], 'no_cookie') ) {
                continue;
            }

            // ignore forms with get method @todo We need to think about this
            if (document.forms[i].getAttribute('method') === null ||
                document.forms[i].getAttribute('method').toLowerCase() === 'post') {
                // remove old sets
                let fields = forms[i].querySelectorAll('.ct_no_cookie_hidden_field');
                for ( let j = 0; j < fields.length; j++ ) {
                    fields[j].outerHTML = '';
                }
                // add new set
                document.forms[i].append(constructNoCookieHiddenField());
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
 * Check if the form should be skipped from hidden field attach.
 * Return exclusion description if it is found, false otherwise.
 * @param {object} form Form dom object.
 * @param {string} hiddenFieldType Type of hidden field that needs to be checked.
 * Possible values: 'no_cookie'|'visible_fields'.
 * @return {boolean}
 */
function checkHiddenFieldsExclusions(form, hiddenFieldType) {
    const formAction = typeof(form.action) == 'string' ? form.action : '';
    // Ajax Search Lite
    if (Boolean(form.querySelector('fieldset.asl_sett_scroll'))) {
        return true;
    }
    // Super WooCommerce Product Filter
    if (form.classList.contains('swpf-instant-filtering')) {
        return true;
    }
    // PayU 3-rd party service forms
    if (formAction.indexOf('secure.payu.com') !== -1 ) {
        return true;
    }

    if (formAction.indexOf('hsforms') !== -1 ) {
        return true;
    }

    if (formAction.indexOf('secureinternetbank.com') !== -1 ) {
        return true;
    }

    if (typeof (hiddenFieldType) === 'string' &&
        ['visible_fields', 'no_cookie'].indexOf(hiddenFieldType) !== -1) {
        const exclusions = getHiddenFieldExclusionsType(form);
        return exclusions[hiddenFieldType] === 1;
    }

    return false;
}

/**
 * Get type of the field should be excluded. Return exclusion signs via object.
 * @param {object} form Form dom object.
 * @return {object} {'no_cookie': 1|0, 'visible_fields': 1|0}
 */
function getHiddenFieldExclusionsType(form) {
    // visible fields
    let result = {'no_cookie': 0, 'visible_fields': 0};
    if (
        +ctPublic.data__visible_fields_required === 0 ||
        (form.method.toString().toLowerCase() === 'get' &&
            form.querySelectorAll('.nf-form-content').length === 0 &&
            form.id !== 'twt_cc_signup') ||
        form.classList.contains('slp_search_form') || // StoreLocatorPlus form
        form.parentElement.classList.contains('mec-booking') ||
        form.action.toString().indexOf('activehosted.com') !== -1 || // Active Campaign
        (form.id && form.id === 'caspioform') || // Caspio Form
        (form.classList && form.classList.contains('tinkoffPayRow')) || // TinkoffPayForm
        (form.classList && form.classList.contains('give-form')) || // GiveWP
        (form.id && form.id === 'ult-forgot-password-form') || // ult forgot password
        (form.id && form.id.toString().indexOf('calculatedfields') !== -1) || // CalculatedFieldsForm
        (form.id && form.id.toString().indexOf('sac-form') !== -1) || // Simple Ajax Chat
        (form.id &&
            form.id.toString().indexOf('cp_tslotsbooking_pform') !== -1) || // WP Time Slots Booking Form
        (form.name &&
            form.name.toString().indexOf('cp_tslotsbooking_pform') !== -1) || // WP Time Slots Booking Form
        form.action.toString() === 'https://epayment.epymtservice.com/epay.jhtml' || // Custom form
        (form.name && form.name.toString().indexOf('tribe-bar-form') !== -1) || // The Events Calendar
        (form.id && form.id === 'ihf-login-form') || // Optima Express login
        (form.id &&
            form.id === 'subscriberForm' &&
            form.action.toString().indexOf('actionType=update') !== -1) || // Optima Express update
        (form.id && form.id === 'ihf-main-search-form') || // Optima Express search
        (form.id && form.id === 'frmCalc') || // nobletitle-calc
        form.action.toString().indexOf('property-organizer-delete-saved-search-submit') !== -1 ||
        form.querySelector('a[name="login"]') !== null // digimember login form
    ) {
        result.visible_fields = 1;
    }

    // ajax search pro exclusion
    let ncFieldExclusionsSign = form.parentNode;
    if (
        ncFieldExclusionsSign && ncFieldExclusionsSign.classList.contains('proinput') ||
        (form.name === 'options' && form.classList.contains('asp-fss-flex'))
    ) {
        result.no_cookie = 1;
    }

    // woocommerce login form
    if (
        form && form.classList.contains('woocommerce-form-login')
    ) {
        result.visible_fields = 1;
        result.no_cookie = 1;
    }

    // WordPress login form (wp-login.php is excluded from spam check in PHP)
    if (
        (form.id && form.id === 'loginform') ||
        form.action.toString().indexOf('wp-login.php') !== -1
    ) {
        result.visible_fields = 1;
        result.no_cookie = 1;
    }

    return result;
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
