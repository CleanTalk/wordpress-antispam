jQuery(document).ready(function($) {
    // Auto update banner close handler
    jQuery('.apbct_update_notice').on('click', 'button', function() {
        let ctDate = new Date(new Date().getTime() + 1000 * 86400 * 30 );
        let ctSecure = location.protocol === 'https:' ? '; secure' : '';
        document.cookie = 'apbct_update_banner_closed=1; path=/; expires=' +
        ctDate.toUTCString() + '; samesite=lax' + ctSecure;
    });

    jQuery('li a[href="options-general.php?page=cleantalk"]').css('white-space', 'nowrap')
        .css('display', 'inline-block');

    jQuery('body').on('click', '.apbct-notice .notice-dismiss-link', function(e) {
        jQuery(e.target).parent().siblings('.apbct-notice .notice-dismiss').click();
    });
    jQuery('body').on('click', '.apbct-notice .notice-dismiss', function(e) {
        let apbctNoticeName = jQuery(e.target).parent().attr('id');
        if ( apbctNoticeName ) {
            apbct_admin_sendAJAX(
                {
                    'action': 'cleantalk_dismiss_notice',
                    'notice_id': apbctNoticeName,
                },
                {
                    'callback': null,
                    'notJson': true,
                },
            );
            jQuery(e.target)
                .parent('.notice')
                .after('<div id="apbct-notice-dismiss-success" class="notice notice-success is-dismissible"><p>' +
                    ctAdminCommon.apbctNoticeDismissSuccess +
                    '</p></div>');
            setTimeout(function() {
                jQuery('#apbct-notice-dismiss-success').fadeOut();
            }, 2000);
        }
    });

    // Notice when deleting user
    jQuery('.ct_username .row-actions .delete a').on('click', function(e) {
        e.preventDefault();

        let result = confirm(ctAdminCommon.notice_when_deleting_user_text);

        if (result) {
            window.location = this.href;
        }
    });

    // Restore spam order
    $('.apbct-restore-spam-order-button').click(function() {
        const spmOrderId = $(this).data('spam-order-id');
        let data = {
            action: 'apbct_restore_spam_order',
            _ajax_nonce: ctAdminCommon._ajax_nonce,
            order_id: spmOrderId,
        };
        $.ajax({
            type: 'POST',
            url: ctAdminCommon._ajax_url,
            data: data,
            success: function(result) {
                if (result.success) {
                    window.location.reload();
                } else {
                    alert(result.data.message);
                }
            },
        });
    });

    // Deactivation banner
    jQuery('#deactivate-cleantalk-spam-protect').on('click', function(e) {
        e.preventDefault();
        let deactivationLink = this.getAttribute('href');
        if ( typeof cleantalkModal !== 'undefined' && ctAdminCommon.deactivation_banner_is_needed === '1') {
            // force replace raw link to the href - fix for https://doboard.com/1/task/10192
            bannertText = ctAdminCommon.deactivation_banner_text
                .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');
            const modalHTML = `
                <div class="ct-modal-message">
                    ${bannertText}
                </div>
                <div class="ct-modal-buttons">
                    <button class="button action" onclick="cleantalkModal.close();">Ok</button>
                    <a class="button action" href="${deactivationLink}">No, deactivate anyway</a>
                </div>
            `;
            // look ahead ^ deactivationLink in the href was broken after modal handler URL converison
            cleantalkModal.loaded = modalHTML;
            // ignore URL conversions due modal handler
            cleantalkModal.ignoreURLConvert = true;
            cleantalkModal.open();
        } else {
            window.location.href = deactivationLink;
        }
    });

    document.querySelectorAll('.apbct-real-user').forEach((el) => {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.currentTarget.querySelector('.apbct-real-user-popup').style.display = 'block';
        });
    });
    document.querySelector('body').addEventListener('click', function(e) {
        document.querySelectorAll('.apbct-real-user-popup').forEach((el) => {
            el.style.display = 'none';
        });
    });
    if (window.location.pathname.includes('wp-admin/edit-comments.php')) {
        const trashElements = document.querySelectorAll('.row-actions .trash');
        if (trashElements.length) {
            trashElements.forEach((el) => {
                el.addEventListener('click', (c) => {
                    const name = c.target.parentElement.parentElement.parentElement
                        .querySelector('.apbct-admin-real-user-author-name');
                    if (!name || !name.textContent) {
                        return;
                    }
                    setTimeout(() => {
                        const nameForUndo = document.querySelector('.untrash .trash-undo-inside');
                        if (!nameForUndo) {
                            return;
                        }
                        const nameUndo = nameForUndo.querySelector('strong');
                        if (nameUndo) {
                            nameUndo.textContent = name.textContent;
                        }
                    }, 10);
                });
            });
        }
    }
});

// eslint-disable-next-line camelcase,require-jsdoc,no-unused-vars
function apbct_admin_sendAJAX(data, params, obj) {
    // Default params
    let callback = params.callback || null;
    let callbackContext = params.callback_context || null;
    let callbackParams = params.callback_params || null;
    let async = params.async || true;
    let notJson = params.notJson || null;
    let timeout = params.timeout || 15000;
    var obj = obj || null; // eslint-disable-line no-var
    let button = params.button || null;
    let spinner = params.spinner || null;
    let progressbar = params.progressbar || null;

    if (typeof (data) === 'string') {
        data = data + '&_ajax_nonce=' + ctAdminCommon._ajax_nonce + '&no_cache=' + Math.random();
    } else {
        data._ajax_nonce = ctAdminCommon._ajax_nonce;
        data.no_cache = Math.random();
    }
    // Button and spinner
    if (button) {
        button.setAttribute('disabled', 'disabled'); button.style.cursor = 'not-allowed';
    }
    if (spinner) jQuery(spinner).css('display', 'inline');

    jQuery.ajax({
        type: 'POST',
        url: ctAdminCommon._ajax_url,
        data: data,
        async: async,
        success: function(result) {
            if (button) {
                button.removeAttribute('disabled'); button.style.cursor = 'pointer';
            }
            if (spinner) jQuery(spinner).css('display', 'none');
            if (!notJson) result = JSON.parse(result);
            if (result.error) {
                setTimeout(function() {
                    if (progressbar) progressbar.fadeOut('slow');
                }, 1000);
                if ( typeof cleantalkModal !== 'undefined' ) {
                    // Show the result by modal
                    cleantalkModal.loaded = 'Error:<br>' + result.error.toString();
                    cleantalkModal.open();
                } else {
                    alert('Error happens: ' + (result.error || 'Unkown'));
                }
            } else {
                if (callback) {
                    if (callbackParams) {
                        callback.apply( callbackContext, callbackParams.concat( result, data, params, obj ) );
                    } else {
                        callback(result, data, params, obj);
                    }
                }
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            if (button) {
                button.removeAttribute('disabled'); button.style.cursor = 'pointer';
            }
            if (spinner) jQuery(spinner).css('display', 'none');
            console.log('APBCT_AJAX_ERROR');
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        timeout: timeout,
    });
}
