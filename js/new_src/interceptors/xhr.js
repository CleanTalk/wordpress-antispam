import {apbctLocalStorage} from '../utils/apbctLocalStorage';
import {getEventToken} from '../utils/eventToken';
import {getNoCookieData, apbctGetBrowserStatePair} from '../functions/requestData';

/**
 * Insert event_token and no_cookies_data to some ajax request
 * @return {void}
 */
export function catchXmlHttpRequest() {
    if (
        document.querySelector('div.wfu_container') !== null ||
        document.querySelector('#newAppointmentForm') !== null ||
        document.querySelector('.booked-calendar-shortcode-wrap') !== null ||
        document.querySelector('.et_pb_newsletter_form') !== null ||
        document.querySelector('form.mailpoet_form') !== null ||
        (
            // Back In Stock Notifier for WooCommerce | WooCommerce Waitlist Pro
            document.body.classList.contains('single-product') &&
            typeof cwginstock !== 'undefined'
        ) ||
        document.querySelector('div.fluent_booking_wrap') !== null || // Fluent Booking Pro
        document.querySelector('div.fcal_calendar_slot_wrap ') !== null || // Fluent Booking / Calendar Pro
        document.querySelectorAll('script[id*="smart-forms"]').length > 0 ||
        document.querySelector('[id^="amelia-app-booking"], .amelia-frontend, .amelia-v2-booking') !== null
    ) {
        const originalOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function(method, url, ...rest) {
            try {
                this._apbctAjaxCallUrl = typeof url === 'string' ? url : '';
            } catch (e) {
                // ignore
            }
            return originalOpen.call(this, method, url, ...rest);
        };

        const originalSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function(body) {
            let isNeedToAddCleantalkDataCheckString = body && typeof body === 'string' &&
                (
                    body.indexOf('action=wfu_ajax_action_ask_server') !== -1 ||
                    body.indexOf('action=booked_add_appt') !== -1 ||
                    body.indexOf('action=cwginstock_product_subscribe') !== -1 ||
                    body.indexOf('action=et_pb_submit_subscribe_form') !== -1 ||
                    body.indexOf('rednao_smart_forms_save_form_values') !== -1 ||
                    (
                        body.indexOf('action=mailpoet') !== -1 &&
                        body.indexOf('method=subscribe') !== -1
                    )
                );
            const isDiviNewsletterRequest = body && typeof body === 'string' &&
                body.indexOf('action=et_pb_submit_subscribe_form') !== -1;
            const isSmartFormsRequest = body && typeof body === 'string' &&
                body.indexOf('rednao_smart_forms_save_form_values') !== -1;

            let isNeedToAddCleantalkDataCheckFormData = body && typeof body === 'object' &&
                body instanceof FormData &&
                (
                    body.has('action') && body.get('action') === 'fluent_cal_schedule_meeting'
                );

            let isNeedToAddCleantalkDataCheckJsonData = body && typeof body === 'string' &&
                (
                    // For Amelia booking integration
                    typeof this._apbctAjaxCallUrl === 'string' &&
                    this._apbctAjaxCallUrl.indexOf('action=wpamelia_api') !== -1
                );

            if (isNeedToAddCleantalkDataCheckString) {
                let addidionalCleantalkData = '';
                const useUnwrappedCleantalkKeys = isDiviNewsletterRequest || isSmartFormsRequest;
                const noCookieDataKey = useUnwrappedCleantalkKeys ?
                    'ct_no_cookie_hidden_field' :
                    'data%5Bct_no_cookie_hidden_field%5D';
                const eventTokenKey = useUnwrappedCleantalkKeys ?
                    'ct_bot_detector_event_token' :
                    'data%5Bct_bot_detector_event_token%5D';
                const browserStateKey = useUnwrappedCleantalkKeys ?
                    'apbct_browser_state' :
                    'data%5Bapbct_browser_state%5D';

                if (!(
                    +ctPublic.bot_detector_enabled &&
                    apbctLocalStorage.get('bot_detector_event_token')
                )) {
                    let noCookieData = getNoCookieData();
                    const hasNoCookieField =
                        body.indexOf('ct_no_cookie_hidden_field=') !== -1 ||
                        body.indexOf('data%5Bct_no_cookie_hidden_field%5D=') !== -1;
                    if (!hasNoCookieField) {
                        addidionalCleantalkData += '&' + noCookieDataKey + '=' + noCookieData;
                    }
                } else {
                    const eventToken = getEventToken();
                    if (eventToken) {
                        const hasEventToken =
                            body.indexOf('ct_bot_detector_event_token=') !== -1 ||
                            body.indexOf('data%5Bct_bot_detector_event_token%5D=') !== -1;
                        if (!hasEventToken) {
                            addidionalCleantalkData += '&' + eventTokenKey + '=' + eventToken;
                        }
                    }
                }

                const browserState = apbctGetBrowserStatePair();
                if (browserState && body.indexOf('apbct_browser_state') === -1) {
                    addidionalCleantalkData += '&' + browserStateKey + '=' +
                        encodeURIComponent(browserState.value);
                }

                body += addidionalCleantalkData;
            }

            if (isNeedToAddCleantalkDataCheckFormData) {
                if (!(
                    +ctPublic.bot_detector_enabled &&
                    apbctLocalStorage.get('bot_detector_event_token')
                )) {
                    let noCookieData = getNoCookieData();
                    body.append('ct_no_cookie_hidden_field', noCookieData);
                } else {
                    const eventToken = getEventToken();
                    if (eventToken) {
                        body.append('ct_bot_detector_event_token', eventToken);
                    }
                }

                const browserState = apbctGetBrowserStatePair();
                if (browserState && !body.has(browserState.key)) {
                    body.append(browserState.key, browserState.value);
                }
            }

            if (isNeedToAddCleantalkDataCheckJsonData) {
                if (!(
                    +ctPublic.bot_detector_enabled &&
                    apbctLocalStorage.get('bot_detector_event_token')
                )) {
                    let noCookieData = getNoCookieData();
                    try {
                        const bodyObj = JSON.parse(body);
                        if (
                            bodyObj && typeof bodyObj === 'object' &&
                            !Object.prototype.hasOwnProperty.call(bodyObj, 'ct_no_cookie_hidden_field')
                        ) {
                            bodyObj.ct_no_cookie_hidden_field = noCookieData;
                            body = JSON.stringify(bodyObj);
                        }
                    } catch (e) {
                        // body is not JSON — leave as is
                    }
                } else {
                    const eventToken = getEventToken();
                    if (eventToken) {
                        try {
                            const bodyObj = JSON.parse(body);
                            if (
                                bodyObj && typeof bodyObj === 'object' &&
                                !Object.prototype.hasOwnProperty.call(bodyObj, 'ct_bot_detector_event_token')
                            ) {
                                bodyObj.ct_bot_detector_event_token = eventToken;
                                body = JSON.stringify(bodyObj);
                            }
                        } catch (e) {
                            // body is not JSON — leave as is
                        }
                    }
                }

                const browserState = apbctGetBrowserStatePair();
                if (browserState) {
                    try {
                        const bodyObj = JSON.parse(body);
                        if (
                            bodyObj && typeof bodyObj === 'object' &&
                            !Object.prototype.hasOwnProperty.call(bodyObj, browserState.key)
                        ) {
                            bodyObj[browserState.key] = browserState.value;
                            body = JSON.stringify(bodyObj);
                        }
                    } catch (e) {
                        // body is not JSON — leave as is
                    }
                }
            }

            return originalSend.apply(this, [body]);
        };
    }
}
