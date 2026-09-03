/**
 * Bot detector browser state.
 *
 * The state is passed to the site backend with the current JS->PHP transport and goes
 * to the moderate request as sender_info.bot_detector_frontend_data_log.
 */
class ApbctBrowserState {
    static STATE_KEY = 'apbct_browser_state';
    static LOG_KEY = 'ct_bot_detector_frontend_data_log';
    static SCRIPT_SIGNS = {
        botd_wrapper_loaded: 'ct-bot-detector-wrapper',
        botd_logic_loaded: 'ct-bot-detector.min',
    };
    static botdWrapperLoaded = 0;
    static botdLogicLoaded = 0;

    /**
     * Get the current browser state as a JSON string ready to be transferred.
     * @return {string} Empty string if the bot detector is disabled or on any collecting error.
     */
    static toJson() {
        try {
            if (!+ctPublicFunctions.bot_detector_enabled) {
                return '';
            }

            const log = localStorage.getItem(ApbctBrowserState.LOG_KEY);
            let logObject;
            try {
                logObject = typeof log === 'string' ? JSON.parse(log) : null;
            } catch (e) {
                logObject = null;
            }

            return JSON.stringify({
                botd_logic_loaded: ApbctBrowserState.botdLogicLoaded,
                botd_wrapper_loaded: ApbctBrowserState.botdWrapperLoaded,
                frontend_data_log: logObject,
            });
        } catch (e) {
            return '';
        }
    }

    /**
     * Look for the bot detector scripts in the page DOM.
     * @return {boolean} True if both scripts are found.
     */
    static detectScripts() {
        const scripts = document.getElementsByTagName('script');

        for (let i = 0; i < scripts.length; i++) {
            const src = scripts[i].getAttribute('src');
            if (!src) {
                continue;
            }
            if (src.indexOf(ApbctBrowserState.SCRIPT_SIGNS.botd_wrapper_loaded) !== -1) {
                ApbctBrowserState.botdWrapperLoaded = 1;
            } else if (src.indexOf(ApbctBrowserState.SCRIPT_SIGNS.botd_logic_loaded) !== -1) {
                ApbctBrowserState.botdLogicLoaded = 1;
            }
        }

        // The bot detector logic sets this flag on its own start

        return !!ApbctBrowserState.botdWrapperLoaded && !!ApbctBrowserState.botdLogicLoaded;
    }
}

/**
 * Get the browser state as a key/value pair to attach it to an intercepted request.
 * @return {{key: string, value: string}|false} False if there is nothing to attach.
 */
function apbctGetBrowserStatePair() { // eslint-disable-line no-unused-vars
    try {
        ApbctBrowserState.detectScripts();

        const state = ApbctBrowserState.toJson();
        return state ? {key: ApbctBrowserState.STATE_KEY, value: state} : false;
    } catch (e) {
        return false;
    }
}
