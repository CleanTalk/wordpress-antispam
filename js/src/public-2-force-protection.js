/**
 * ApbctForceProtection
 */
class ApbctForceProtection {
    wrappers = [];

    /**
     * Constructor
     */
    constructor() {
        this.wrappers = this.findWrappers();

        if (this.wrappers.length < 1) {
            return;
        }

        this.checkBot();
    }

    /**
     * Find wrappers
     * @return {HTMLElement[]}
     */
    findWrappers() {
        return document.querySelectorAll('div.ct-encoded-form-wrapper');
    }

    /**
     * Check bot
     * @return {void}
     */
    checkBot() {
        let data = {
            post_url: document.location.href,
            referrer: document.referrer,
        };
        if (ctPublic.settings__data__bot_detector_enabled == 1) {
            data.event_token = botDetectorLocalStorage.get('bot_detector_event_token');
        } else {
            data.event_javascript_data = getJavascriptClientData();
        }

        if (ctPublicFunctions.data__ajax_type === 'rest') {
            apbct_public_sendREST('force_protection_check_bot', {
                data,
                method: 'POST',
                callback: (result) => this.checkBotCallback(result),
            });
        } else if (ctPublicFunctions.data__ajax_type === 'admin_ajax') {
            data.action = 'apbct_force_protection_check_bot';
            apbct_public_sendAJAX(data, {callback: (result) => this.checkBotCallback(result)});
        }
    }

    /**
     * Check bot callback
     * @param {Object} result
     * @return {void}
     */
    checkBotCallback(result) {
        // if error occurred
        if (result.data && result.data.status && result.data.status !== 200) {
            console.log('ApbctForceProtection connection error occurred');
            this.decodeForms();
            return;
        }

        if (typeof result === 'string') {
            try {
                result = JSON.parse(result);
            } catch (e) {
                console.log('ApbctForceProtection decodeForms error', e);
                this.decodeForms();
                return;
            }
        }

        if (typeof result === 'object' && result.allow && result.allow === 1) {
            this.decodeForms();
            document.dispatchEvent(new Event('apbctForceProtectionAllowed'));
        } else {
            this.showMessageForBot(result.message);
        }
    }

    /**
     * Decode forms
     * @return {void}
     */
    decodeForms() {
        let form;

        this.wrappers.forEach((wrapper) => {
            form = wrapper.querySelector('div.ct-encoded-form').dataset.encodedForm;

            try {
                if (form && typeof(form) == 'string') {
                    const urlDecoded = decodeURIComponent(form);
                    wrapper.outerHTML = atob(urlDecoded);
                }
            } catch (error) {
                console.log(error);
            }
        });
    }

    /**
     * Show message for bot
     * @param {string} message
     * @return {void}
     */
    showMessageForBot(message) {
        let form;

        this.wrappers.forEach((wrapper) => {
            form = wrapper.querySelector('div.ct-encoded-form').dataset.encodedForm;
            if (form) {
                wrapper.outerHTML = '<div class="ct-encoded-form-forbidden">' + message + '</div>';
            }
        });
    }
}

/**
 * Force protection
 */
function apbctForceProtect() {
    if (+ctPublic.settings__forms__force_protection && typeof ApbctForceProtection !== 'undefined') {
        new ApbctForceProtection();
    }
}

if (ctPublic.data__key_is_ok) {
    if (document.readyState !== 'loading') {
        apbctForceProtect();
    } else {
        apbct_attach_event_handler(document, 'DOMContentLoaded', apbctForceProtect);
    }
}
