/**
 * Catches responses of the blocked AJAX requests and shows the block message.
 * The message itself is a separate chunk, loaded only when there is something to show.
 */
export function initBlockMessage() {
    const prepareBlockMessage = function(xhr) {
        if (xhr.responseText &&
            xhr.responseText.indexOf('"apbct') !== -1 &&
            xhr.responseText.indexOf('DOCTYPE') === -1
        ) {
            try {
                showBlockMessage(JSON.parse(xhr.responseText));
            } catch (e) {
                console.log(e.toString());
            }
        }
    };

    if (typeof jQuery !== 'undefined') {
        // Capturing responses and output block message for unknown AJAX forms
        if (typeof jQuery(document).ajaxComplete() !== 'function') {
            jQuery(document).on('ajaxComplete', function(event, xhr) {
                prepareBlockMessage(xhr);
            });
        } else {
            jQuery(document).ajaxComplete( function(event, xhr) {
                prepareBlockMessage(xhr);
            });
        }
    } else if (typeof XMLHttpRequest !== 'undefined') {
        // if jQuery is not available try to use xhr
        document.addEventListener('readystatechange', function(event) {
            if (event.target.readyState === 4) {
                prepareBlockMessage(event.target);
            }
        });
    }
}

/**
 * @param {object} response
 * @return {Promise<void>}
 */
export async function showBlockMessage(response) {
    try {
        const blockMessage = await import(/* webpackChunkName: "apbct_block_message" */ './functions/blockMessage');
        blockMessage.parseBlockMessage(response);
    } catch (e) {
        console.log(e.toString());
    }
}
