/**
 * Checking that the bot detector has loaded and received the event token
 */
function checkBotDetectorExist() {
    if (ctPublic.settings__data__bot_detector_enabled) {
        const botDetectorIntervalSearch = setInterval(() => {
            let botDetectorEventToken = localStorage.bot_detector_event_token ? true : false;

            if (botDetectorEventToken) {
                ctSetCookie('apbct_bot_detector_exist', '1', '3600');
                clearInterval(botDetectorIntervalSearch);
            }
        }, 500);
    }
}

/**
 * For forced alt cookies.
 * If token is not added to the LS on apbc_ready, check every second if so and send token to the alt sessions.
 */
function startForcedAltEventTokenChecker() {
    tokenCheckerIntervalId = setInterval( function() {
        if (apbctLocalStorage.get('event_token_forced_set') === '1') {
            clearInterval(tokenCheckerIntervalId);
            return;
        }
        let eventToken = apbctLocalStorage.get('bot_detector_event_token');
        if (eventToken) {
            ctSetAlternativeCookie([['ct_bot_detector_event_token', eventToken]], {forceAltCookies: true});
            apbctLocalStorage.set('event_token_forced_set', '1');
            clearInterval(tokenCheckerIntervalId);
        } else {
        }
    }, 1000);
}

/**
 * Restart event_token attachment if some forms load after document ready.
 */
function restartBotDetectorEventTokenAttach() {
    // List there any new conditions, right now it works only for LatePoint forms.
    // Probably, we can remove this condition at all, because setEventTokenField()
    // checks all the forms without the field
    const doAttach = (
        document.getElementsByClassName('latepoint-form').length > 0 ||
        document.getElementsByClassName('mec-booking-form-container').length > 0 ||
        document.getElementById('login-form-popup') !== null
    );

    try {
        if ( doAttach ) {
            // get token from LS
            const token = apbctLocalStorage.get('bot_detector_event_token');
            if (typeof setEventTokenField === 'function' && token !== undefined && token.length === 64) {
                setEventTokenField(token);
            }
            // probably there we could use a new botDetectorInit if token is not found
        }
    } catch (e) {
        console.log(e.toString());
    }
}
