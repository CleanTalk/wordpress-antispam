/**
 * Prepare block to intercept AJAX response
 */
function apbctPrepareBlockForAjaxForms() {
    // eslint-disable-next-line require-jsdoc
    function ctPrepareBlockMessage(xhr) {
        if (xhr.responseText &&
            xhr.responseText.indexOf('"apbct') !== -1 &&
            xhr.responseText.indexOf('DOCTYPE') === -1
        ) {
            try {
                ctParseBlockMessage(JSON.parse(xhr.responseText));
            } catch (e) {
                console.log(e.toString());
            }
        }
    }

    if (typeof jQuery !== 'undefined') {
        // Capturing responses and output block message for unknown AJAX forms
        if (typeof jQuery(document).ajaxComplete() !== 'function') {
            jQuery(document).on('ajaxComplete', function(event, xhr, settings) {
                ctPrepareBlockMessage(xhr);
            });
        } else {
            jQuery(document).ajaxComplete( function(event, xhr, settings) {
                ctPrepareBlockMessage(xhr);
            });
        }
    } else {
        // if Jquery is not avaliable try to use xhr
        if (typeof XMLHttpRequest !== 'undefined') {
            // Capturing responses and output block message for unknown AJAX forms
            document.addEventListener('readystatechange', function(event) {
                if (event.target.readyState === 4) {
                    ctPrepareBlockMessage(event.target);
                }
            });
        }
    }
}

// eslint-disable-next-line require-jsdoc
function ctParseBlockMessage(response) {
    let msg = '';
    if (typeof response.apbct !== 'undefined') {
        response = response.apbct;
        if (response.blocked) {
            msg = response.comment;
        }
    }
    if (typeof response.data !== 'undefined') {
        response = response.data;
        if (response.message !== undefined) {
            msg = response.message;
        }
    }

    if (msg) {
        document.dispatchEvent(
            new CustomEvent( 'apbctAjaxBockAlert', {
                bubbles: true,
                detail: {message: msg},
            } ),
        );

        // Show the result by modal
        cleantalkModal.loaded = msg;
        cleantalkModal.open();

        if (+response.stop_script === 1) {
            window.stop();
        }
    }
}


