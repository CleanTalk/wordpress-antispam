const CRON_START_TIMEOUT = 2000;
const CRON_INTERVAL = 2000;

/**
 * Restarts the fields listening to catch the forms loaded after the document ready.
 * Needed only for the gathering data, the Bot-Detector does not use it.
 */
export function initCronFormsHandler() {
    if (+ctPublic.bot_detector_enabled) {
        return;
    }

    setTimeout(() => {
        setInterval(() => {
            if (typeof window.ApbctGatheringData !== 'undefined') {
                new window.ApbctGatheringData().restartFieldsListening();
            }
        }, CRON_INTERVAL);
    }, CRON_START_TIMEOUT);
}
