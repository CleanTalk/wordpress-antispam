/**
 * Shows the block message from the response of the blocked AJAX request.
 * @param {object} response
 * @return {void}
 */
export function parseBlockMessage(response) {
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

    if (!msg) {
        return;
    }

    document.dispatchEvent(
        new CustomEvent( 'apbctAjaxBockAlert', {
            bubbles: true,
            detail: {message: msg},
        } ),
    );

    // Show the result by modal
    cleantalkModal.loaded = msg;
    cleantalkModal.open();

    if (+response.stop_script !== 1) {
        return;
    }

    window.stop();
    if (response.integration && response.integration === 'NEXForms') {
        const btn = document.querySelector('form.submit-nex-form button.nex-submit');
        if (btn) {
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.style.cursor = 'not-allowed';
            btn.style.pointerEvents = 'none';
            btn.style.backgroundColor = '#ccc';
            btn.style.color = '#fff';
        }
        const successMessage = document.querySelector('div.nex_success_message');
        if (successMessage) {
            successMessage.style.display = 'none';
        }
    }
    if (response.integration && response.integration === 'ElfsightForm') {
        const docs = [document];
        try {
            document.querySelectorAll('iframe').forEach((f) => {
                try {
                    if (f.contentDocument) docs.push(f.contentDocument);
                } catch (e) {
                    /* same-origin only */
                }
            });
        } catch (e) {
            /* ignore */
        }
        for (const doc of docs) {
            const elfsightContainer = doc.querySelector('[class*="elfsight-app"]');
            if (elfsightContainer) {
                const submitBtn =
                    elfsightContainer.querySelector('button[type="submit"]') ||
                    Array.from(elfsightContainer.querySelectorAll('button, [role="button"]'))
                        .find((btn) =>
                            btn.textContent.trim() === 'Submit' ||
                            btn.getAttribute('aria-label') === 'Submit');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.removeAttribute('aria-busy');
                }
                const loaders = elfsightContainer.querySelectorAll('[class*="Loader__Spinner"]');
                loaders.forEach((el) => {
                    el.style.display = 'none';
                });
                let errEl = elfsightContainer.querySelector('.apbct-elfsight-forbidden-msg');
                if (!errEl) {
                    errEl = doc.createElement('div');
                    errEl.className = 'apbct-elfsight-forbidden-msg';
                    errEl.style.cssText =
                        'margin-top:12px;padding:10px;color:#c0392b;font-size:14px;line-height:1.4;';
                    if (submitBtn) {
                        submitBtn.insertAdjacentElement('afterend', errEl);
                    } else {
                        elfsightContainer.appendChild(errEl);
                    }
                }
                errEl.textContent = msg;
                errEl.style.display = '';
                break;
            }
        }
    }
}
