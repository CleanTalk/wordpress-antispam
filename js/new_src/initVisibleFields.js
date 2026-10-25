/**
 * Imports the visible fields feature as a separate chunk and starts it, if there are forms on the page.
 */
export function initVisibleFields() {
    if (!document.forms.length) {
        return;
    }

    importVisibleFields().then(startVisibleFields);
}

/**
 * @param {object|null} visibleFields imported module
 */
function startVisibleFields(visibleFields) {
    if (!visibleFields) {
        return;
    }
    visibleFields.startVisibleFields();
}

/**
 * @return {Promise<object|null>}
 */
async function importVisibleFields() {
    try {
        return await import(/* webpackChunkName: "apbct_visible_fields" */ './functions/visibleFields');
    } catch (e) {
        return null;
    }
}
