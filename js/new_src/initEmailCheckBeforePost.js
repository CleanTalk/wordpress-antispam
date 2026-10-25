/**
 * Imports the email check before post feature as a separate chunk and starts it, if the setting is enabled.
 */
export function initEmailCheckBeforePost() {
    if (!+ctPublic.data__email_check_before_post) {
        return;
    }

    importEmailCheckBeforePost().then(startEmailCheckBeforePost);
}

/**
 * @param {object|null} emailCheck imported module
 */
function startEmailCheckBeforePost(emailCheck) {
    if (!emailCheck) {
        return;
    }
    emailCheck.startEmailCheckBeforePost();
}

/**
 * @return {Promise<object|null>}
 */
async function importEmailCheckBeforePost() {
    try {
        return await import(/* webpackChunkName: "apbct_email_check_before_post" */ './functions/emailCheckBeforePost');
    } catch (e) {
        return null;
    }
}
