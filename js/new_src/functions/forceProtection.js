import {apbctLocalStorage} from '../utils/apbctLocalStorage';
import {sendAJAX, sendREST} from './ajaxHandlers';

/**
 * Starts the force protection: forms are decoded only after the bot check is passed.
 */
export function startForceProtection() {
    new ApbctForceProtection();
}

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
    async checkBot() {
        let data = {
            post_url: document.location.href,
            referrer: document.referrer,
        };
        if (+ctPublic.bot_detector_enabled) {
            data.event_token = apbctLocalStorage.get('bot_detector_event_token');
        } else {
            try {
                const gathering = await import(
                    /* webpackChunkName: "apbct_gathering_data" */ './gatheringData'
                );
                data.event_javascript_data = gathering.getJavascriptClientData();
            } catch (e) {
                console.log('ApbctForceProtection gathering data error', e);
            }
        }

        if (ctPublicFunctions.data__ajax_type === 'rest') {
            sendREST('force_protection_check_bot', {
                data,
                method: 'POST',
                callback: (result) => this.checkBotCallback(result),
            });
        } else if (ctPublicFunctions.data__ajax_type === 'admin_ajax') {
            data.action = 'apbct_force_protection_check_bot';
            sendAJAX(data, {callback: (result) => this.checkBotCallback(result)});
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
