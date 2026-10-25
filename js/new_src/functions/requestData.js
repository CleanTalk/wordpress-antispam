import {apbctLocalStorage} from '../utils/apbctLocalStorage';
import {apbctSessionStorage} from '../utils/apbctSessionStorage';
import {apbctGetBrowserStatePair} from './noCookieHiddenField';

export {apbctGetBrowserStatePair};

/**
 * @return {string}
 */
export function getNoCookieData() {
    let noCookieDataLocal = apbctLocalStorage.getCleanTalkData();
    let noCookieDataSession = apbctSessionStorage.getCleanTalkData();
    let noCookieData = {...noCookieDataLocal, ...noCookieDataSession};

    // Bot detector browser state is transferred with the hidden field on the NoCookie mode
    const browserState = apbctGetBrowserStatePair();
    if (browserState) {
        noCookieData[browserState.key] = browserState.value;
    }

    noCookieData = JSON.stringify(noCookieData);

    return '_ct_no_cookie_data_' + btoa(unescape(encodeURIComponent(noCookieData)));
}
