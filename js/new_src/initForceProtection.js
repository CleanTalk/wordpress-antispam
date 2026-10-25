/**
 * Imports the force protection as a separate chunk and starts it, if the setting is enabled and the key is valid.
 */
export function initForceProtection() {
    if (!ctPublic.data__key_is_ok || !+ctPublic.settings__forms__force_protection) {
        return;
    }

    importForceProtection().then(startForceProtection);
}

/**
 * @param {object|null} forceProtection imported module
 */
function startForceProtection(forceProtection) {
    if (!forceProtection) {
        return;
    }
    forceProtection.startForceProtection();
}

/**
 * @return {Promise<object|null>}
 */
async function importForceProtection() {
    try {
        return await import(/* webpackChunkName: "apbct_force_protection" */ './functions/forceProtection');
    } catch (e) {
        return null;
    }
}
