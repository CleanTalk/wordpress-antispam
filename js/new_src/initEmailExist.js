/**
 * Imports the email exist check feature as a separate chunk and starts it, if the setting is enabled.
 */
export function initEmailExist() {
    if (!+ctPublic.data__email_check_exist_post) {
        return;
    }

    importEmailExist().then(startEmailExist);
}

/**
 * @param {object|null} emailExist imported module
 */
function startEmailExist(emailExist) {
    if (!emailExist) {
        return;
    }
    emailExist.startEmailExist();
}

/**
 * @return {Promise<object|null>}
 */
async function importEmailExist() {
    try {
        return await import(/* webpackChunkName: "apbct_email_exist" */ './functions/emailExist');
    } catch (e) {
        return null;
    }
}
