import {sendAjaxRequestJson} from './Ajax';

/**
 * @param {string} email
 * @param {number|string} timezone
 * @return {Promise<{success: boolean, msg?: string, getTemplates?: string}>}
 */
export async function getKeyAuto(email, timezone) {
    return sendAjaxRequestJson({
        action: 'apbct_get_key_auto',
        email,
        ct_admin_timezone: timezone,
    });
}

/**
 * @param {string} apiKey
 * @return {Promise<{success: boolean, msg?: string}>}
 */
export async function saveAccessKey(apiKey) {
    return sendAjaxRequestJson({
        action: 'apbct_save_key',
        apiKey,
    });
}
