const {__} = wp.i18n;
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
        jQuery(e.target).parent()
            .parent('.notice')
            .after('<div id="apbct-notice-dismiss-success" class="notice notice-success is-dismissible"><p>' +
                ctAdminCommon.apbctNoticeDismissSuccess +
                '</p></div>');
        setTimeout(function() {
            jQuery('#apbct-notice-dismiss-success').fadeOut();
        }, 2000);
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

    let btnForceProtectionOn = document.querySelector('#apbct_setting_forms__force_protection__On');
    if (btnForceProtectionOn) {
        btnForceProtectionOn.addEventListener('click', function(e) {
            if (btnForceProtectionOn.checked) {
                let result = confirm(ctAdminCommon.apbctNoticeForceProtectionOn);

                if (!result) {
                    e.preventDefault();
                }
            }
        });
    }
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

    // Extended spam order details show via modal
    $('.apbct-details-spam-order-button').click(function() {
        const spmOrderId = $(this).data('spam-order-id');
        let data = {
            action: 'apbct_details_spam_order',
            _ajax_nonce: ctAdminCommon._ajax_nonce,
            order_id: spmOrderId,
        };
        if (typeof cleantalkModal !== 'undefined') {
            cleantalkModal.loaded = false;
            cleantalkModal.open(false);
            $.ajax({
                type: 'POST',
                url: ctAdminCommon._ajax_url,
                data: data,
                success: function(result) {
                    const modalContent = $('#cleantalk-modal-content');
                    modalContent.empty();
                    if (result.success && result.data) {
                        const container = apbctGetWCOrderDetailsModalContainer(result.data);
                        const containerHeader = document.createElement('h3');
                        containerHeader.className = 'apbct_wc_details__table-container_header';
                        containerHeader.textContent = __('WooCommerce spam order details', 'cleantalk-spam-protect');
                        modalContent.append(containerHeader);
                        modalContent.append(container);
                        modalContent.append(containerHeader);
                        modalContent.append(container);
                    } else {
                        const error = result.data.error || 'Unknown error occurred';
                        modalContent.text(error);
                    }
                    cleantalkModal.loaded = true;
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    alert('An error occurred while processing your request, see console for details.');
                },
            });
        } else {
            alert('Can not initialize CleanTalk modal window.');
        }
    });

    // Email decoder example
    if (window.location.href.includes('options-general.php?page=cleantalk')) {
        let encodedEmailNode = document.querySelector('[data-original-string]');
        if (encodedEmailNode) {
            ctAdminCommon.encodedEmailNode = encodedEmailNode;
            encodedEmailNode.style.cursor = 'pointer';
            encodedEmailNode.addEventListener('click', ctFillDecodedEmailHandler);
        }
    }
});

/**
 * apbctGetWCOrderDetailsModalContainer
 * @param {object} wcOrderData
 * @return {HTMLDivElement}
 */
function apbctGetWCOrderDetailsModalContainer(wcOrderData) {
    const container = document.createElement('div');
    container.className = 'apbct_wc_details__wrapper';

    /**
     * createTableFromObject
     * @param {object} obj
     * @param {string} title
     * @return {HTMLDivElement}
     */
    function createTableFromObject(obj, title) {
        const tableWrapperInner = document.createElement('div');
        tableWrapperInner.className = 'apbct_wc_details__table-wrapper-inner';

        const header = document.createElement('h4');
        header.textContent = title;
        tableWrapperInner.appendChild(header);

        const table = document.createElement('table');
        table.className = 'apbct_wc_details__table';

        const tbody = document.createElement('tbody');

        for (const [key, value] of Object.entries(obj)) {
            const row = document.createElement('tr');

            const keyCell = document.createElement('td');
            keyCell.className = 'apbct_wc_details__table-key-cell';
            keyCell.textContent = key;

            const valueCell = document.createElement('td');
            valueCell.className = 'apbct_wc_details__table-value-cell';

            if (typeof value === 'object' && value !== null) {
                valueCell.textContent = JSON.stringify(value, null, 2);
                valueCell.classList.add('apbct_wc_details__table-value-cell--json');
            } else {
                valueCell.textContent = value !== null && value !== undefined ? value : '—';
            }

            row.appendChild(keyCell);
            row.appendChild(valueCell);
            tbody.appendChild(row);
        }

        table.appendChild(tbody);
        tableWrapperInner.appendChild(table);
        return tableWrapperInner;
    }

    if (wcOrderData.order_details) {
        const orderDetails = wcOrderData.order_details;
        for (const [key, value] of Object.entries(orderDetails)) {
            const productId = orderDetails[key] && typeof orderDetails[key]['product_id'] === 'number' ?
                orderDetails[key]['product_id'] :
                'Unknown';
            const header = __('Order Details for product ID', 'cleantalk-spam-protect');
            container.appendChild(createTableFromObject(value, `${header} ${productId}`));
        }
    }

    if (wcOrderData.customer_details) {
        container.appendChild(
            createTableFromObject(wcOrderData.customer_details, 'Customer Details'),
        );
    }

    return container;
}
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
