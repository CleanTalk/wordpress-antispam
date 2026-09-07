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
    static lastSentOnTS = null;

    /**
     * Get the current browser state as a JSON string ready to be transferred.
     * @return {string} Empty string if the bot detector is disabled or on any collecting error.
     */
    static getFrontendDataLog() {
        const prefix = (typeof ctPublicFunctions !== 'undefined' && ctPublicFunctions.cookiePrefix) ?
            ctPublicFunctions.cookiePrefix :
            '';
        const logKey = prefix + ApbctBrowserState.LOG_KEY;
        const noPrefixLogKey = ApbctBrowserState.LOG_KEY;

        if (typeof apbctLocalStorage !== 'undefined' && apbctLocalStorage.get) {
            const logObject = apbctLocalStorage.get(logKey) || apbctLocalStorage.get(noPrefixLogKey) || null;
            if (logObject && typeof logObject === 'string') {
                try {
                    return JSON.parse(logObject);
                } catch (e) {
                    return '';
                }
            }
            return logObject;
        }

        let rawLog = localStorage.getItem(logKey);
        if (!rawLog) {
            rawLog = localStorage.getItem(noPrefixLogKey) || null;
        }

        try {
            return typeof rawLog === 'string' ? JSON.parse(rawLog) : rawLog;
        } catch (e) {
            return '';
        }
    }

    /**
     * Convert to JSON string for sending to the backend.
     * @return {string|false}
     */
    static asJSON() {
        try {
            return JSON.stringify({
                botd_logic_loaded: ApbctBrowserState.botdLogicLoaded,
                botd_wrapper_loaded: ApbctBrowserState.botdWrapperLoaded,
                frontend_data_log: ApbctBrowserState.getFrontendDataLog(),
            });
        } catch (e) {
            return false;
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

    /**
     * Sync browser state to the cookie each second when bot detector is enabled.
     */
    static startCookieSyncPolling() {
        if (ctPublicFunctions.data__cookies_type === 'native') {
            return;
        }
        ApbctBrowserState.lastSentOnTS = null;

        if (ctPublicFunctions.data__cookies_type === 'alternative') {
            const forms = document.getElementsByTagName('form');
            for (let i = 0; i < forms.length; i++) {
                forms[i].addEventListener('submit', () => {
                    ApbctBrowserState.cookieSync();
                });
            }
        }
        setInterval(() => {
            ApbctBrowserState.cookieSync();
        }, 1000);
    }

    /**
     * Sync data to cookie
     */
    static cookieSync() {
        const fdLog = ApbctBrowserState.getFrontendDataLog();

        if (!Array.isArray(fdLog) || fdLog.length <= 1) {
            return;
        }

        const lastValue = fdLog[fdLog.length - 1];
        const lastTS = lastValue && lastValue[2];

        if (lastTS !== undefined && ApbctBrowserState.lastSentOnTS !== lastTS) {
            ApbctBrowserState.lastSentOnTS = lastTS;
            const browserState = apbctGetBrowserStatePair();
            const json = ApbctBrowserState.asJSON();
            if (json && browserState && browserState.key && browserState.value) {
                if ( ctPublicFunctions.data__cookies_type === 'alternative') {
                    ctSetAlternativeCookie([[browserState.key, json]]);
                } else if (ctPublicFunctions.data__cookies_type === 'none') {
                    ctNoCookieAttachHiddenFieldsToForms();
                }
            }
        }
    }
}

/**
 * Get the browser state as a key/value pair to attach it to an intercepted request.
 * @return {{key: string, value: string}|false} False if there is nothing to attach.
 */
function apbctGetBrowserStatePair() { // eslint-disable-line no-unused-vars
    try {
        ApbctBrowserState.detectScripts();
        const state = ApbctBrowserState.asJSON();
        return state ? {key: ApbctBrowserState.STATE_KEY, value: state} : false;
    } catch (e) {
        return false;
    }
}
