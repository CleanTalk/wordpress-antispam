/**
 * Class for handling FetchProxy forms
 */
class ApbctFetchProxyProtection {
    constructor() {
        this.config = ApbctFetchProxyConfig;
    }

    /**
     * Find matching config for URL
     * @param {string} url
     * @return {object|null} { formKey, config } or null
     */
    findMatchingConfig(url) {
        for (const [formKey, config] of Object.entries(this.config)) {
            // FetchProxy can send both external and internal requests
            // If the form is external, then we check whether the setting is enabled.
            if (
                (!config.externalForm || +ctPublic.settings__forms__check_external) && 
                document.querySelectorAll(config.selector).length > 0 &&
                url && url.includes(config.urlPattern)
            ) {
                return {formKey, config};
            }
        }
        return null;
    }

    /**
     * Check FetchProxy form request via CleanTalk AJAX
     * @param {string} formKey
     * @param {object} config
     * @param {string} bodyText
     * @return {Promise<boolean>} true = block, false = allow
     */
    async checkRequest(formKey, config, bodyText) {
        return new Promise((resolve) => {
            let data = {
                action: config.action,
            };

            try {
                const bodyObj = JSON.parse(bodyText);
                for (const [key, value] of Object.entries(bodyObj)) {
                    data[key] = value;
                }
            } catch (e) {
                data.raw_body = bodyText;
            }

            if (+ctPublic.settings__data__bot_detector_enabled) {
                const eventToken = new ApbctHandler().toolGetEventToken();
                if (eventToken) {
                    data.ct_bot_detector_event_token = eventToken;
                }
            } else {
                data.ct_no_cookie_hidden_field = getNoCookieData();
            }

            apbct_public_sendAJAX(data, {
                async: true,
                callback: (result) => {
                    // Allowed
                    if (
                        (result.apbct === undefined && result.data === undefined) ||
                        (result.apbct !== undefined && !+result.apbct.blocked)
                    ) {
                        if (typeof config.callbackAllow === 'function') {
                            config.callbackAllow(result);
                        }
                        resolve(false);
                        return;
                    }

                    // Blocked
                    if (
                        (result.apbct !== undefined && +result.apbct.blocked) ||
                        (result.data !== undefined && result.data.message !== undefined)
                    ) {
                        new ApbctShowForbidden().parseBlockMessage(result);
                        if (typeof config.callbackBlock === 'function') {
                            config.callbackBlock(result);
                        }
                        resolve(true);
                        return;
                    }

                    resolve(false);
                },
                onErrorCallback: (error) => {
                    console.log('APBCT FetchProxy check error:', error);
                    resolve(false);
                },
            });
        });
    }

    /**
     * Extract body text from fetch args
     * @param {array} args
     * @return {string}
     */
    extractBodyText(args) {
        let body = args[1] && args[1].body;
        let bodyText = '';

        if (body instanceof FormData) {
            let obj = {};
            for (let [key, value] of body.entries()) {
                obj[key] = value;
            }
            bodyText = JSON.stringify(obj);
        } else if (typeof body === 'string') {
            bodyText = body;
        }

        return bodyText;
    }

    /**
     * Process fetch request for FetchProxy forms
     * @param {array} args - fetch arguments
     * @return {Promise<boolean|null>} true = block, false = allow, null = not matched
     */
    async processFetch(args) {
        const url = typeof args[0] === 'string' ? args[0] : (args[0]?.url || '');
        const match = this.findMatchingConfig(url);

        if (!match) {
            return null;
        }

        const bodyText = this.extractBodyText(args);
        return await this.checkRequest(match.formKey, match.config, bodyText);
    }
}