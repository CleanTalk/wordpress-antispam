import {ctSetCookie, storeParams} from './storeParams';
import {sendAJAX, sendREST} from './ajaxHandlers';

const checkedEmails = {};

export function startEmailCheckBeforePost() {
    storeParams([['ct_checked_emails', '0']]);
    document.querySelectorAll('input[type = "email"], #email').forEach((elem) => {
        elem.addEventListener('blur', checkEmail);
    });
}

/**
 * @param {Event} e
 */
function checkEmail(e) {
    const currentEmail = e.target.value;

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(currentEmail)) {
        return;
    }

    if (!currentEmail || currentEmail in checkedEmails) {
        return;
    }

    const callback = function(result) {
        if (result.result) {
            checkedEmails[currentEmail] = {
                'result': result.result,
                'timestamp': Date.now() / 1000 | 0,
            };
            ctSetCookie('ct_checked_emails', JSON.stringify(checkedEmails));
        }
    };

    if (ctPublicFunctions.data__ajax_type === 'rest') {
        sendREST(
            'check_email_before_post',
            {
                method: 'POST',
                data: {'email': currentEmail},
                callback: callback,
            },
        );
    } else if (ctPublicFunctions.data__ajax_type === 'admin_ajax') {
        sendAJAX(
            {
                action: 'apbct_email_check_before_post',
                email: currentEmail,
            },
            {
                callback: callback,
            },
        );
    }
}
