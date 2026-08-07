/**
 * @return {{url: string, nonce: string}}
 */
function getAjaxConfig() {
    if (typeof ctAdminCommon === 'undefined') {
        throw new Error('ctAdminCommon is not defined. Enqueue cleantalk-admin.js before the React bundle.');
    }

    return {
        url: ctAdminCommon._ajax_url,
        nonce: ctAdminCommon._ajax_nonce,
    };
}

/**
 * @param {Record<string, string|number|boolean>} data
 * @param {boolean} isJson
 * @param {boolean} wizardRequest
 * @return {Promise<unknown>}
 */
export async function sendAjaxRequest(data, isJson = true, wizardRequest = true) {
    const {url, nonce} = getAjaxConfig();
    const payload = {
        ...data,
        _ajax_nonce: nonce,
        no_cache: String(Math.random()),
    };

    if (wizardRequest) {
        payload.apbct_wizard_request = 1;
    }

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(payload),
    });

    const body = await response.text();

    if (!isJson) {
        return body;
    }

    try {
        return JSON.parse(body);
    } catch (error) {
        throw new Error('Invalid JSON response from server');
    }
}

/**
 * @param {Record<string, string|number|boolean>} data
 * @return {Promise<Record<string, unknown>>}
 */
export async function sendAjaxRequestJson(data) {
    const result = await sendAjaxRequest(data, true, true);

    if (typeof result !== 'object' || result === null) {
        throw new Error('Unexpected AJAX response');
    }

    return result;
}
