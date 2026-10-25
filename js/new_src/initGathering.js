import {apbctLocalStorage} from './utils/apbctLocalStorage';
import {storeParams} from './functions/storeParams';

/**
 * Imports gathering as a separate chunk and starts it. Obligatory params are not waiting for it.
 */
export function initGathering() {
    const gatheringLoaded = !!(
        apbctLocalStorage.get('apbct_existing_visitor') && // Not for the first hit
        +ctPublic.bot_detector_enabled && // If Bot-Detector is active
        !apbctLocalStorage.get('bot_detector_event_token') && // and no `event_token` generated
        typeof window.ApbctGatheringData === 'undefined' // and no `gathering` loaded yet
    );

    // Bot-Detector is disabled: gathering is the only source of data
    if (gatheringLoaded || !+ctPublic.bot_detector_enabled) {
        window.apbctGatheringLoaded = gatheringLoaded;
        window.apbctGatheringReady = importGatheringData().then((gathering) => startGathering(gathering, gatheringLoaded));
    }
}

/**
 * @param {object|null} gathering imported module
 * @param {boolean} gatheringLoaded
 */
function startGathering(gathering, gatheringLoaded) {
    if (!gathering || typeof window.ApbctGatheringData === 'undefined') {
        return;
    }
    const params = gathering.getGatheringParams(gatheringLoaded);
    if (params.length) {
        storeParams(params);
    }
    const gatheringData = new window.ApbctGatheringData();
    gatheringData.setSessionId();
    gatheringData.writeReferrersToSessionStorage();
    gatheringData.setCookiesType();
    gatheringData.startFieldsListening();
    gatheringData.listenAutocomplete();
    gatheringData.gatheringTypoData();
}

/**
 * @return {Promise<object|null>}
 */
async function importGatheringData() {
    try {
        return await import(/* webpackChunkName: "apbct_gathering_data" */ './functions/gatheringData');
    } catch (e) {
        return null;
    }
}
