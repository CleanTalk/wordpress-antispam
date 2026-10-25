/**
 * Imports the search form protection as a separate chunk and starts it, if the setting is enabled.
 */
export function initSearchForm() {
    if (+ctPublic.settings__forms__search_test !== 1) {
        return;
    }

    importSearchForm().then(startSearchForm);
}

/**
 * @param {object|null} searchForm imported module
 */
function startSearchForm(searchForm) {
    if (!searchForm) {
        return;
    }
    searchForm.searchFormMiddleware();
}

/**
 * @return {Promise<object|null>}
 */
async function importSearchForm() {
    try {
        return await import(/* webpackChunkName: "apbct_search_form" */ './functions/searchForm');
    } catch (e) {
        return null;
    }
}
