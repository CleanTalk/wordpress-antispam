/**
 * @return {bool}
 */
function ctIsDrawPixel() {
    if (ctPublic.pixel__setting == '3' && ctPublic.settings__data__bot_detector_enabled == '1') {
        return false;
    }

    return +ctPublic.pixel__enabled ||
        (ctPublic.data__cookies_type === 'none' && document.querySelectorAll('img#apbct_pixel').length === 0) ||
        (ctPublic.data__cookies_type === 'alternative' && document.querySelectorAll('img#apbct_pixel').length === 0);
}

/**
 * @param {string} pixelUrl
 * @return {bool}
 */
function ctSetPixelImg(pixelUrl) {
    if (ctPublic.pixel__setting == '3' && ctPublic.settings__data__bot_detector_enabled == '1') {
        return false;
    }
    ctSetCookie('apbct_pixel_url', pixelUrl);
    if ( ctIsDrawPixel() ) {
        if ( ! document.getElementById('apbct_pixel') ) {
            let insertedImg = document.createElement('img');
            insertedImg.setAttribute('alt', 'CleanTalk Pixel');
            insertedImg.setAttribute('title', 'CleanTalk Pixel');
            insertedImg.setAttribute('id', 'apbct_pixel');
            insertedImg.setAttribute('style', 'display: none; left: 99999px;');
            insertedImg.setAttribute('src', pixelUrl);
            apbct('body').append(insertedImg);
        }
    }
}

/**
 * @param {string} pixelUrl
 * @return {bool}
 */
function ctSetPixelImgFromLocalstorage(pixelUrl) {
    if (ctPublic.pixel__setting == '3' && ctPublic.settings__data__bot_detector_enabled == '1') {
        return false;
    }
    if ( ctIsDrawPixel() ) {
        if ( ! document.getElementById('apbct_pixel') ) {
            let insertedImg = document.createElement('img');
            insertedImg.setAttribute('alt', 'CleanTalk Pixel');
            insertedImg.setAttribute('title', 'CleanTalk Pixel');
            insertedImg.setAttribute('id', 'apbct_pixel');
            insertedImg.setAttribute('style', 'display: none; left: 99999px;');
            insertedImg.setAttribute('src', decodeURIComponent(pixelUrl));
            apbct('body').append(insertedImg);
        }
    }
}

/**
 * ctGetPixelUrl
 * @return {bool}
 */
function ctGetPixelUrl() {
    if (ctPublic.pixel__setting == '3' && ctPublic.settings__data__bot_detector_enabled == '1') {
        return false;
    }

    // Check if pixel is already in localstorage and is not outdated
    let localStoragePixelUrl = apbctLocalStorage.get('apbct_pixel_url');
    if ( localStoragePixelUrl !== false ) {
        if ( ! apbctLocalStorage.isAlive('apbct_pixel_url', 3600 * 3) ) {
            apbctLocalStorage.delete('apbct_pixel_url');
        } else {
            // if so - load pixel from localstorage and draw it skipping AJAX
            ctSetPixelImgFromLocalstorage(localStoragePixelUrl);
            return;
        }
    }
    // Using REST API handler
    if ( ctPublicFunctions.data__ajax_type === 'rest' ) {
        apbct_public_sendREST(
            'apbct_get_pixel_url',
            {
                method: 'POST',
                callback: function(result) {
                    if (result &&
                        (typeof result === 'string' || result instanceof String) && result.indexOf('https') === 0) {
                        // set  pixel url to localstorage
                        if ( ! apbctLocalStorage.get('apbct_pixel_url') ) {
                            // set pixel to the storage
                            apbctLocalStorage.set('apbct_pixel_url', result);
                            // update pixel data in the hidden fields
                            ctNoCookieAttachHiddenFieldsToForms();
                        }
                        // then run pixel drawing
                        ctSetPixelImg(result);
                    }
                },
            },
        );
        // Using AJAX request and handler
    } else {
        apbct_public_sendAJAX(
            {
                action: 'apbct_get_pixel_url',
            },
            {
                notJson: true,
                callback: function(result) {
                    if (result &&
                        (typeof result === 'string' || result instanceof String) && result.indexOf('https') === 0) {
                        // set  pixel url to localstorage
                        if ( ! apbctLocalStorage.get('apbct_pixel_url') ) {
                            // set pixel to the storage
                            apbctLocalStorage.set('apbct_pixel_url', result);
                            // update pixel data in the hidden fields
                            ctNoCookieAttachHiddenFieldsToForms();
                        }
                        // then run pixel drawing
                        ctSetPixelImg(result);
                    }
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-Robots-Tag', 'noindex, nofollow');
                },
            },
        );
    }
}

// eslint-disable-next-line no-unused-vars,require-jsdoc
function ctSetPixelUrlLocalstorage(ajaxPixelUrl) {
    // set pixel to the storage
    ctSetCookie('apbct_pixel_url', ajaxPixelUrl);
}
