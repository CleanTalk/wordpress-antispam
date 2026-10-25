/**
 * Base class for extracting visible fields from form plugins
 */
export class ApbctVisibleFieldsExtractor {
    /**
     * Factory method to create appropriate extractor instance
     * @param {string} sourceSignAction - Action identifier
     * @return {ApbctVisibleFieldsExtractor|null}
     */
    static createExtractor(sourceSignAction) {
        switch (sourceSignAction) {
        case 'action=nf_ajax_submit':
            return new ApbctNinjaFormsVisibleFields();
        case 'action=mailpoet':
            return new ApbctMailpoetVisibleFields();
        default:
            return null;
        }
    }
    /**
     * Extracts visible fields string from form AJAX data
     * @param {string} ajaxData - AJAX form data
     * @return {string|false} Visible fields value or false if not found
     */
    extract(ajaxData) {
        if (!ajaxData || typeof ajaxData !== 'string') {
            return false;
        }

        try {
            ajaxData = decodeURIComponent(ajaxData);
        } catch (e) {
            return false;
        }

        const forms = document.querySelectorAll('form');
        const formIdFromAjax = this.getIdFromAjax(ajaxData);
        if (!formIdFromAjax) {
            return false;
        }

        for (let form of forms) {
            const container = this.findParentContainer(form);
            if (!container) {
                continue;
            }

            const formIdFromHtml = this.getIdFromHTML(container);
            if (formIdFromHtml !== formIdFromAjax) {
                continue;
            }

            const visibleFields = container.querySelector('input[id^=apbct_visible_fields_]');
            if (visibleFields?.value) {
                return visibleFields.value;
            }
        }

        return false;
    }

    /**
     * Override in child classes to define specific extraction patterns
     * @param {string} ajaxData - Decoded AJAX data string
     * @return {number|null} Form ID or null if not found
     */
    getIdFromAjax(ajaxData) {
        console.warn('getIdFromAjax must be implemented by child class');
        return null;
    }

    /**
     * Override in child classes to define container search logic
     * @param {HTMLElement} form - Form element to start search from
     * @return {HTMLElement|null} Container element or null
     */
    findParentContainer(form) {
        console.warn('findParentContainer must be implemented by child class');
        return null;
    }

    /**
     * Override in child classes to define ID extraction from HTML
     * @param {HTMLElement} container - Container element
     * @return {number|null} Form ID or null if not found
     */
    getIdFromHTML(container) {
        console.warn('getIdFromHTML must be implemented by child class');
        return null;
    }
}

/**
 * Ninja Forms specific implementation
 */
class ApbctNinjaFormsVisibleFields extends ApbctVisibleFieldsExtractor {
    /**
     * @inheritDoc
     */
    getIdFromAjax(ajaxData) {
        const regexes = [
            /"id"\s*:\s*"?(\d+)"/, // {"id":"2"} or {"id":2}
            /form_id\s*[:\s]*"?(\d+)"/,
            /nf-form-(\d+)/,
            /"id":(\d+)/, // Fallback for simple cases
        ];

        for (let regex of regexes) {
            const match = ajaxData.match(regex);
            if (match && match[1]) {
                return parseInt(match[1], 10);
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    findParentContainer(form) {
        let el = form;
        while (el && el !== document.body) {
            if (el.id && /^nf-form-\d+-cont$/.test(el.id)) {
                return el;
            }
            el = el.parentElement;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    getIdFromHTML(container) {
        const match = container.id.match(/^nf-form-(\d+)-cont$/);
        return match ? parseInt(match[1], 10) : null;
    }
}

/**
 * Mailpoet specific implementation
 */
class ApbctMailpoetVisibleFields extends ApbctVisibleFieldsExtractor {
    /**
     * @inheritDoc
     */
    getIdFromAjax(ajaxData) {
        const regexes = [
            /form_id\s*[:\s]*"?(\d+)"/,
            /data\[form_id\]=(\d+)/,
            /form_id=(\d+)/,
        ];

        for (let regex of regexes) {
            const match = ajaxData.match(regex);
            if (match && match[1]) {
                return parseInt(match[1], 10);
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    findParentContainer(form) {
        // Mailpoet uses the form itself as container
        return form;
    }

    /**
     * @inheritDoc
     */
    getIdFromHTML(container) {
        if (!container.action) {
            return null;
        }

        const formMatch = container.action.match(/mailpoet_subscription_form/);
        if (!formMatch) {
            return null;
        }

        const hiddenFieldWithID = container.querySelector('input[type="hidden"][name="data[form_id]"]');
        if (!hiddenFieldWithID || !hiddenFieldWithID.value) {
            return null;
        }

        return parseInt(hiddenFieldWithID.value, 10);
    }
}
