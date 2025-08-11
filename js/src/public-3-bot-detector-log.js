let botDetectorLogLastUpdate = 0;
let botDetectorLogEventTypesCollected = [];

// bot_detector frontend_data log alt session saving cron
if (
    ctPublicFunctions.hasOwnProperty('data__bot_detector_enabled') &&
    ctPublicFunctions.data__bot_detector_enabled == 1 &&
    ctPublicFunctions.hasOwnProperty('data__frontend_data_log_enabled') &&
    ctPublicFunctions.data__frontend_data_log_enabled == 1
) {
    sendBotDetectorLogToAltSessions(1000);
}

/**
 * Send BotDetector logs data to alternative sessions.
 * If log_last_update has changed and log contains new event types, the log will be sent to the alternative sessions.
 * @param {int} cronStartTimeout delay before cron start
 * @param {int} interval check fires on interval
 */
function sendBotDetectorLogToAltSessions(cronStartTimeout = 3000, interval = 1000) {
    setTimeout(function() {
        setInterval(function() {
            const currentLog = apbctLocalStorage.get('ct_bot_detector_frontend_data_log');
            if (needsSaveLogToAltSessions(currentLog)) {
                botDetectorLogLastUpdate = currentLog.log_last_update;
                // the log will be taken from javascriptclientdata
                ctSetAlternativeCookie([], {forceAltCookies: true});
            }
        }, interval);
    }, cronStartTimeout);
}

/**
 * Check if the log needs to be saved to the alt sessions. If the log has new event types, it will be saved.
 * @param {object} currentLog
 * @return {boolean}
 */
function needsSaveLogToAltSessions(currentLog) {
    if (
        currentLog && currentLog.hasOwnProperty('log_last_update') &&
        botDetectorLogLastUpdate !== currentLog.log_last_update
    ) {
        try {
            for (let i = 0; i < currentLog.records.length; i++) {
                const currentType = currentLog.records[i].frontend_data.js_event;
                // check if this event type was already collected
                if (currentType !== undefined && botDetectorLogEventTypesCollected.includes(currentType)) {
                    continue;
                }
                // add new event type to collection, this type will be sent to the alt sessions further
                botDetectorLogEventTypesCollected.push(currentType);
                return true;
            }
        } catch (e) {
            console.log('APBCT: bot detector log collection error: ' . e.toString());
        }
    }
    return false;
}


