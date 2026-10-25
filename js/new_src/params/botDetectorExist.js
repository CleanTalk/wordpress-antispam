import {storeParams} from '../functions/storeParams';
import {apbctLocalStorage} from '../utils/apbctLocalStorage';

const PARAM_NAME = 'apbct_bot_detector_exist';

/**
 * Anti-Crawler needs to know that the Bot-Detector is alive: the param is stored as soon as the event token exists.
 */
export function initBotDetectorExist() {
    if (!ctPublic.settings__sfw__anti_crawler || !+ctPublic.bot_detector_enabled) {
        return;
    }

    if (apbctLocalStorage.get('bot_detector_event_token')) {
        storeParams([[PARAM_NAME, '1']]);
        return;
    }

    const onTokenUpdated = (event) => {
        if (!event.detail?.eventToken) {
            return;
        }
        window.removeEventListener('botDetectorEventTokenUpdated', onTokenUpdated);
        storeParams([[PARAM_NAME, '1']]);
    };
    window.addEventListener('botDetectorEventTokenUpdated', onTokenUpdated);
}
