/**
 * Imports the internal forms protection as a separate chunk and starts it, if the setting is enabled.
 * The `apbctForceProtectionAllowed` listener is added right away, so the event is not missed while the chunk loads.
 */
export function initInternalForms() {
    if (!+ctPublic.settings__forms__check_internal) {
        return;
    }

    const internalForms = importInternalForms();

    setTimeout(() => protectInternalForms(internalForms), 500);

    document.addEventListener('apbctForceProtectionAllowed', () => protectInternalForms(internalForms));
}

/**
 * @param {Promise<object|null>} internalForms
 */
function protectInternalForms(internalForms) {
    internalForms.then((module) => {
        if (module) {
            module.ctProtectInternalForms();
        }
    });
}

/**
 * @return {Promise<object|null>}
 */
async function importInternalForms() {
    try {
        return await import(/* webpackChunkName: "apbct_internal_forms" */ './functions/internalForms');
    } catch (e) {
        return null;
    }
}
