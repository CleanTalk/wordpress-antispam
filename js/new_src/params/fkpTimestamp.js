import {ctSetCookie} from '../functions/storeParams';

export const PARAM_NAME = 'ct_fkp_timestamp';

export function initFkpTimestamp() {
    if (ctPublic.data__key_is_ok) {
        document.addEventListener('mousedown', ctFunctionFirstKey);
        document.addEventListener('keydown', ctFunctionFirstKey);
    }

    return '0';
}

// Writing first key press timestamp
const ctFunctionFirstKey = function output(event) {
    let KeyTimestamp = Math.floor(new Date().getTime() / 1000);
    ctSetCookie(PARAM_NAME, KeyTimestamp);
    ctKeyStopStopListening();
};

/**
 * Stop key listening function
 */
function ctKeyStopStopListening() {
    document.removeEventListener('mousedown', ctFunctionFirstKey);
    document.removeEventListener('keydown', ctFunctionFirstKey);
}

