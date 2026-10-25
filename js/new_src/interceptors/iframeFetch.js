import {apbctLocalStorage} from '../utils/apbctLocalStorage';
import {getNoCookieData, apbctGetBrowserStatePair} from '../functions/requestData';

/**
 * Catch Iframe fetch request
 * @return {void}
 */
export function catchIframeFetchRequest() {
    setTimeout(function() {
        try {
            // Give next gen iframe
            const foundIframe = Array.from(document.querySelectorAll('iframe')).find(
                (iframe) => iframe.src?.includes('givewp-route'),
            );

            if (!foundIframe) {
                return;
            }

            // Cross origin access check
            let contentWindow;
            try {
                contentWindow = foundIframe.contentWindow;
                if (!contentWindow || !contentWindow.fetch) {
                    return;
                }
            } catch (securityError) {
                // access denied
                return;
            }

            // Save original iframe fetch
            const originalIframeFetch = contentWindow.fetch;

            // Intercept and add fields to body
            contentWindow.fetch = async function(...args) {
                try {
                    // is body and boddy has append func
                    if (args && args[1] && args[1].body) {
                        if (
                            args[1].body instanceof FormData || (typeof args[1].body.append === 'function')
                        ) {
                            if (
                                +ctPublic.bot_detector_enabled &&
                                apbctLocalStorage.get('bot_detector_event_token')
                            ) {
                                args[1].body.append(
                                    'ct_bot_detector_event_token',
                                    apbctLocalStorage.get('bot_detector_event_token'),
                                );
                            } else {
                                args[1].body.append('ct_no_cookie_hidden_field', getNoCookieData());
                            }

                            const browserState = apbctGetBrowserStatePair();
                            if (browserState) {
                                args[1].body.append(browserState.key, browserState.value);
                            }
                        }
                    }
                } catch (e) {
                    // do nothing due fields add error
                }

                // run origin fetch
                return originalIframeFetch.apply(contentWindow, args);
            };
        } catch (error) {
            // do nothing on unexpected error
        }
    }, 1000);
}
