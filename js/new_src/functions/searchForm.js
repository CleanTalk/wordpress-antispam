import {apbctLocalStorage} from '../utils/apbctLocalStorage';
import {getCleanTalkStorageDataArray} from './noCookieHiddenField';
import {ctSetAltCookies} from './storeParams';

/**
 * Handle search forms middleware
 * @return {void}
 */
export function searchFormMiddleware() {
    for (const _form of document.forms) {
        if (
            typeof ctPublic !== 'undefined' &&
            + ctPublic.settings__forms__search_test === 1 &&
            _form.getAttribute('apbct-form-sign') !== null &&
            _form.getAttribute('apbct-form-sign') === 'native_search'
        ) {
            // this handles search forms onsubmit process
            _form.apbctSearchPrevOnsubmit = _form.onsubmit;
            _form.onsubmit = (e) => searchFormHandler(e, _form);
        }
    }
}

/**
 * Handle search forms
 * @param {SubmitEvent} e
 * @param {object} targetForm
 * @return {void}
 */
function searchFormHandler(e, targetForm) {
    try {
        // get honeypot field and it's value
        const honeyPotField = targetForm.querySelector('[name*="apbct__email_id__"]');
        let hpValue = null;
        if (
            honeyPotField !== null &&
            honeyPotField.value !== null
        ) {
            hpValue = honeyPotField.value;
        }

        // get cookie data from storages
        let cleantalkStorageDataArray = getCleanTalkStorageDataArray();

        // get event token from storage
        let eventTokenLocalStorage = apbctLocalStorage.get('bot_detector_event_token');

        // if noCookie data or honeypot data is set, proceed handling
        if ( cleantalkStorageDataArray !== null || honeyPotField !== null || eventTokenLocalStorage !== null ) {
            e.preventDefault();
            const callBack = () => {
                if (honeyPotField !== null) {
                    honeyPotField.parentNode.removeChild(honeyPotField);
                }
                if (typeof targetForm.apbctSearchPrevOnsubmit === 'function') {
                    targetForm.apbctSearchPrevOnsubmit();
                } else {
                    HTMLFormElement.prototype.submit.call(targetForm);
                }
            };

            let cookiesArray = cleantalkStorageDataArray;

            // if honeypot data provided add the fields to the parsed data
            if ( hpValue !== null ) {
                cookiesArray.apbct_search_form__honeypot_value = hpValue;
            }

            // set event token
            cookiesArray.ct_bot_detector_event_token = eventTokenLocalStorage;

            // if the pixel needs to be decoded
            if (
                typeof cookiesArray.apbct_pixel_url === 'string' &&
                cookiesArray.apbct_pixel_url.indexOf('%3A') !== -1
            ) {
                cookiesArray.apbct_pixel_url = decodeURIComponent(cookiesArray.apbct_pixel_url);
            }

            // data to JSON
            const parsedCookies = JSON.stringify(cookiesArray);

            // if any data provided, proceed data to xhr
            if ( typeof parsedCookies !== 'undefined' && parsedCookies.length !== 0 ) {
                ctSetAltCookies(
                    parsedCookies,
                    {callback: callBack, onErrorCallback: callBack, forceAltCookies: true},
                );
            } else {
                callBack();
            }
        }
    } catch (error) {
        console.warn('APBCT search form onsubmit handler error. ' + error);
    }
}
