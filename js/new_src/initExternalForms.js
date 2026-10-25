/**
 * Imports the external forms protection as a separate chunk and starts it, if the setting is enabled.
 */
export function initExternalForms() {
    if (!+ctPublic.settings__forms__check_external) {
        return;
    }

    importExternalForms().then(startExternalForms);
}

/**
 * @param {object|null} externalForms imported module
 */
function startExternalForms(externalForms) {
    if (!externalForms) {
        return;
    }
    externalForms.startExternalForms();
}

/**
 * @return {Promise<object|null>}
 */
async function importExternalForms() {
    try {
        return await import(/* webpackChunkName: "apbct_external_forms" */ './functions/externalForms');
    } catch (e) {
        return null;
    }
}
