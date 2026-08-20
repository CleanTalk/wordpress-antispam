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
    static lastSentState = null;

    /**
     * Get the current browser state as a JSON string ready to be transferred.
     * @return {string} Empty string if the bot detector is disabled or on any collecting error.
     */
    static toJson() {
        try {
            if (!+ctPublicFunctions.bot_detector_enabled) {
                return '';
            }

            const log = +ctPublicFunctions.data__frontend_data_log_enabled === 1 ?
                localStorage.getItem(ApbctBrowserState.LOG_KEY) :
                '';

            return JSON.stringify({
                botd_logic_loaded: ApbctBrowserState.botdLogicLoaded,
                botd_wrapper_loaded: ApbctBrowserState.botdWrapperLoaded,
                frontend_data_log: typeof log === 'string' ? log : '',
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
        if (window.BOT_DETECTOR_LOADED) {
            ApbctBrowserState.botdLogicLoaded = 1;
        }

        return !!ApbctBrowserState.botdWrapperLoaded && !!ApbctBrowserState.botdLogicLoaded;
    }

    /**
     * Send the state to the alternative sessions.
     * @param {boolean} force Send even if the state has not been changed.
     */
    static sendToAltSessions(force = false) {
        const state = ApbctBrowserState.toJson();

        if (!state || (!force && state === ApbctBrowserState.lastSentState)) {
            return;
        }

        ApbctBrowserState.lastSentState = state;

        ctSetAlternativeCookie(JSON.stringify({[ApbctBrowserState.STATE_KEY]: state}));
    }

    /**
     * Entry point. Called from apbct_ready().
     */
    static init() {
        if (!+ctPublicFunctions.bot_detector_enabled) {
            return;
        }

        // Scripts detection, the interval is stopped as soon as both scripts are found
        if (!ApbctBrowserState.detectScripts()) {
            let attempts = 0;
            const intervalId = setInterval(function() {
                if (ApbctBrowserState.detectScripts() || ++attempts >= 30) {
                    clearInterval(intervalId);
                }
            }, 500);
        }

        // Alt sessions is the only transport that needs its own sending routine.
        // NoCookie and the XHR interception attach the state to the outgoing request itself.
        // Native cookies do not transfer the state at all - the cookie size limit risk is too high.
        if (ctPublicFunctions.data__cookies_type === 'alternative') {
            setInterval(function() {
                ApbctBrowserState.sendToAltSessions();
            }, 1000);

            for (let i = 0; i < document.forms.length; i++) {
                document.forms[i].addEventListener('submit', function() {
                    ApbctBrowserState.sendToAltSessions(true);
                });
            }
        }
    }
}

/**
 * Start the browser state collecting.
 * Function declaration is used to make the call reachable from the other bundle parts,
 * no matter in what order they are concatenated.
 */
function apbctBrowserStateInit() { // eslint-disable-line no-unused-vars
    try {
        ApbctBrowserState.init();
    } catch (e) {
        // the class is not evaluated yet, nothing to collect
    }
}

/**
 * Get the browser state as a key/value pair to attach it to an intercepted request.
 * @return {{key: string, value: string}|false} False if there is nothing to attach.
 */
function apbctGetBrowserStatePair() { // eslint-disable-line no-unused-vars
    try {
        const state = ApbctBrowserState.toJson();
        return state ? {key: ApbctBrowserState.STATE_KEY, value: state} : false;
    } catch (e) {
        return false;
    }
}
