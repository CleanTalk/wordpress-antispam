/**
 * Callbacks for FetchProxy integrations
 */
const ApbctFetchProxyCallbacks = {
    /**
     * Mailchimp block callback - clears localStorage by mcforms mask
     * @param {object} result
     */
    mailchimpBlock: function(result) {
        try {
            for (let i = localStorage.length - 1; i >= 0; i--) {
                const key = localStorage.key(i);
                if (key && key.indexOf('mcforms') !== -1) {
                    localStorage.removeItem(key);
                }
            }
        } catch (e) {
            console.warn('Error clearing localStorage by mcforms mask:', e);
        }
    },

    // /**
    //  * Next integration block callback
    //  * @param {object} result
    //  */
    // nextIntegrationBlock: function(result) {
    //     // Custom logic
    // },
};