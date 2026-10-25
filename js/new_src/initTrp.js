/**
 * Imports the real person badges as a separate chunk, if the data for them is localized.
 */
export function initTrp() {
    if (!ctPublic.theRealPerson) {
        return;
    }

    importTrp().then((trp) => {
        if (trp) {
            trp.startTrp(ctPublic.theRealPerson);
        }
    });
}

/**
 * @return {Promise<object|null>}
 */
async function importTrp() {
    try {
        return await import(/* webpackChunkName: "apbct_trp" */ './functions/trp');
    } catch (e) {
        return null;
    }
}
