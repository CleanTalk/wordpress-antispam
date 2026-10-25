import {storeParams, ctSetAltCookies} from '../functions/storeParams';

export const PARAM_NAME = 'ct_bot_detector_event_token';

/**
 * The Bot-Detector generates the event token asynchronously, so the param is stored on the first update event.
 */
export function initBotDetectorEventToken() {
    if (!+ctPublic.bot_detector_enabled) {
        return;
    }

    const onTokenUpdated = (event) => {
        const eventToken = event.detail?.eventToken;
        if (!eventToken) {
            return;
        }
        window.removeEventListener('botDetectorEventTokenUpdated', onTokenUpdated);
        storeParams([[PARAM_NAME, eventToken]]);

        // @ToDo remove this block after force_alt_cookies removed
        if (typeof ctPublic.force_alt_cookies !== 'undefined' && ctPublic.force_alt_cookies) {
            ctSetAltCookies({[PARAM_NAME]: eventToken}, {forceAltCookies: true});
        }
    };
    window.addEventListener('botDetectorEventTokenUpdated', onTokenUpdated);
}
