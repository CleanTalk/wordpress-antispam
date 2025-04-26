/**
 * Check form as internal.
 * @param {int} currForm Current form.
 */
function ctCheckInternal(currForm) {
    //  Gathering data
    const ctData = {};
    const elems = currForm.elements;
    let key;

    for (key in elems) {
        if (elems[key].type !== 'submit' &&
            elems[key].value !== undefined &&
            elems[key].value !== '') {
            ctData[elems[key].name] = currForm.elements[key].value;
        }
    }
    ctData.action = 'ct_check_internal';

    //  AJAX Request
    apbct_public_sendAJAX(
        ctData,
        {
            url: ctPublicFunctions._ajax_url,
            callback: function(data) {
                if (data.success === true) {
                    currForm.origSubmit();
                } else {
                    alert(data.data);
                    return false;
                }
            },
        },
    );
}

document.addEventListener('DOMContentLoaded', function() {
    if ( ! +ctPublic.settings__forms__check_internal ) {
        return;
    }

    setTimeout(() => {
        ctProtectInternalForms();
    }, 500);

    document.addEventListener('apbctForceProtectionAllowed', function() {
        ctProtectInternalForms();
    });
});

/**
 * Protect internal forms
 * @return {void}
 */
function ctProtectInternalForms() {
    let ctCurrAction = '';
    let ctCurrForm = '';

    for ( let i = 0; i < document.forms.length; i++ ) {
        if ( typeof(document.forms[i].action) == 'string' ) {
            ctCurrForm = document.forms[i];
            ctCurrAction = ctCurrForm.action;
            if (
                ctCurrAction.indexOf('https?://') !== null && // The protocol is obligatory
                ctCurrAction.match(ctPublic.blog_home + '.*?\.php') !== null && // Main check
                ! ctCheckInternalIsExcludedForm(ctCurrAction) // Exclude WordPress native scripts from processing
            ) {
                const formClone = ctCurrForm.cloneNode(true);
                ctCurrForm.parentNode.replaceChild(formClone, ctCurrForm);

                formClone.origSubmit = ctCurrForm.submit;
                formClone.submit = null;

                formClone.addEventListener('submit', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    ctCheckInternal(event.target);
                    return false;
                });
            }
        }
    }
}

/**
 * Check by action to exclude the form checking
 * @param {string} action
 * @return {boolean}
 */
function ctCheckInternalIsExcludedForm(action) {
    // An array contains forms action need to be excluded.
    const ctInternalScriptExclusions = [
        'wp-login.php', // WordPress login page
        'wp-comments-post.php', // WordPress Comments Form
    ];

    return ctInternalScriptExclusions.some((item) => {
        return action.match(new RegExp(ctPublic.blog_home + '.*' + item)) !== null;
    });
}
