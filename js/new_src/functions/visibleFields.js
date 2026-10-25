import {ctSetCookie, storeParams} from './storeParams';
import {checkHiddenFieldsExclusions} from './formExclusions';

const PARAM_NAME = 'apbct_visible_fields';

/**
 * Initial value, cleanup and attaching to the forms. Runs once per page load.
 */
export function startVisibleFields() {
    if (ctPublic.data__cookies_type !== 'native') {
        storeParams([[PARAM_NAME, '0']]);
    } else {
        // Delete all visible fields cookies on load the page
        const cookiesArray = document.cookie.split(';');
        for (let i = 0; i < cookiesArray.length; i++) {
            const cookieName = cookiesArray[i].trim().split('=')[0];
            if (cookieName.indexOf(PARAM_NAME + '_') === 0) {
                ctDeleteCookie(cookieName);
            }
        }
    }

    // Forms may be rendered by JS, so give them a time
    setTimeout(attachToForms, 1000);
}

/**
 * Attach visible fields to all forms except excluded ones and catch the submit as a fallback.
 * @return {void}
 */
function attachToForms() {
    for (let i = 0; i < document.forms.length; i++) {
        const form = document.forms[i];

        if (excludeForm(form)) {
            continue;
        }

        attachVisibleFieldsToForm(form, i);

        catchMain(form, i);
    }
}

/**
 * Exclude form from collecting visible fields
 * @param {HTMLFormElement} form
 * @return {boolean}
 */
function excludeForm(form) {
    if (checkHiddenFieldsExclusions(form, 'visible_fields')) {
        return true;
    }

    if (form.querySelector('input[name="wspsc_add_cart_submit"]') ||
        form.querySelector('input[name="option"][value="com_vikrentcar"]') ||
        form.querySelector('input[name="option"][value="com_vikbooking"]')
    ) {
        return true;
    }

    return form.elements.apbct_visible_fields !== undefined &&
        form.elements.apbct_visible_fields.length > 0;
}

/**
 * Exclude form from prevCallExclude
 * @param {object} form
 * @return {boolean}
 */
function prevCallExclude(form) {
    return form.classList.contains('hb-booking-search-form');
}

/**
 * Forms with an inline onsubmit whose returned value controls the submit.
 * A deferred call loses that value and lets the form submit natively.
 * @param {object} form
 * @return {boolean}
 */
function prevCallSyncRequired(form) {
    if (form.classList === undefined) {
        return false;
    }

    // Pagelayer contact form: onsubmit="return pagelayer_contact_submit(this, event)"
    return form.classList.contains('pagelayer-contact-form');
}

/**
 * Collect visible fields on submit as a fallback
 * @param {object} form
 * @param {number} index
 * @return {void}
 */
function catchMain(form, index) {
    // Third-party plugins may re-fire DOMContentLoaded. Double wrapping makes onsubmit_prev recursive.
    if (form.apbctCatchMainAttached) {
        return;
    }
    form.apbctCatchMainAttached = true;

    form.onsubmit_prev = form.onsubmit;
    form.ctFormIndex = index;

    form.onsubmit = function(event) {
        attachVisibleFieldsDuringSubmit(event, form);

        if (event.target.onsubmit_prev instanceof Function && !prevCallExclude(event.target)) {
            if (event.target.classList !== undefined && event.target.classList.contains('brave_form_form')) {
                event.preventDefault();
            }
            if (prevCallSyncRequired(event.target)) {
                return event.target.onsubmit_prev.call(event.target, event);
            }
            setTimeout(function() {
                event.target.onsubmit_prev.call(event.target, event);
            }, 0);
        }
    };
}

/**
 * Attach visible fields to form
 * @param {HTMLFormElement} form
 * @param {number} index
 * @return {void}
 */
export function attachVisibleFieldsToForm(form, index) {
    const hiddenInput = document.createElement('input');
    hiddenInput.setAttribute('type', 'hidden');
    hiddenInput.setAttribute('id', PARAM_NAME + '_' + index);
    hiddenInput.setAttribute('name', PARAM_NAME);
    const visibleFieldsToInput = {};
    visibleFieldsToInput[0] = collectVisibleFields(form);
    hiddenInput.value = btoa(unescape(encodeURIComponent(JSON.stringify(visibleFieldsToInput))));
    form.append(hiddenInput);
}

/**
 * Attach visible fields during submit
 * @param {Event} event
 * @param {HTMLFormElement} form
 * @return {void}
 */
export function attachVisibleFieldsDuringSubmit(event, form) {
    if (ctPublic.data__cookies_type !== 'native' && typeof event.target.ctFormIndex !== 'undefined') {
        setVisibleFieldsCookie(collectVisibleFields(form), event.target.ctFormIndex);
    }
}

/**
 * Set visible fields cookie
 * @param {object} visibleFieldsCollection
 * @param {number} formId
 * @return {void}
 */
export function setVisibleFieldsCookie(visibleFieldsCollection, formId) {
    const collection = typeof visibleFieldsCollection === 'object' && visibleFieldsCollection !== null ?
        visibleFieldsCollection : {};

    if (ctPublic.data__cookies_type === 'native') {
        // eslint-disable-next-line guard-for-in
        for (const i in collection) {
            if (i > 10) {
                // Do not generate more than 10 cookies
                return;
            }
            const collectionIndex = formId !== undefined ? formId : i;
            ctSetCookie(PARAM_NAME + '_' + collectionIndex, JSON.stringify(collection[i]));
        }
    } else {
        ctSetCookie(PARAM_NAME, JSON.stringify(collection));
    }
}

/**
 * Collect visible fields
 * @param {HTMLFormElement} form
 * @return {object}
 */
export function collectVisibleFields(form) {
    // Get only fields
    let inputs = [];
    let inputsVisible = '';
    let inputsVisibleCount = 0;
    let inputsInvisible = '';
    let inputsInvisibleCount = 0;
    const inputsWithDuplicateNames = [];

    for (const key in form.elements) {
        if (!isNaN(+key)) {
            inputs[key] = form.elements[key];
        }
    }

    // Filter fields
    inputs = inputs.filter(function(elem) {
        // Filter already added fields
        if (inputsWithDuplicateNames.indexOf(elem.getAttribute('name')) !== -1) {
            return false;
        }
        // Filter inputs with same names for type == radio
        if (-1 !== ['radio', 'checkbox'].indexOf(elem.getAttribute('type'))) {
            inputsWithDuplicateNames.push(elem.getAttribute('name'));
            return false;
        }
        return true;
    });

    // Batch all style computations to avoid forced layouts
    const styleChecks = inputs.map(function(elem) {
        // Unnecessary fields
        if (
            elem.getAttribute('type') === 'submit' || // type == submit
            elem.getAttribute('name') === null ||
            elem.getAttribute('name') === 'ct_checkjs'
        ) {
            return {elem: elem, skip: true};
        }

        // Check for hidden type first (no layout required)
        if (elem.getAttribute('type') === 'hidden') {
            return {elem: elem, isVisible: false, isWpEditor: elem.classList.contains('wp-editor-area')};
        }

        // Batch getComputedStyle calls to avoid multiple layout thrashing
        const computedStyle = getComputedStyle(elem);
        const isHidden = computedStyle.display === 'none' ||
            computedStyle.visibility === 'hidden' ||
            computedStyle.opacity === '0';

        return {
            elem: elem,
            isVisible: !isHidden,
            isWpEditor: elem.classList.contains('wp-editor-area'),
        };
    });

    // Process the results
    styleChecks.forEach(function(check) {
        if (check.skip) {
            return;
        }

        if (!check.isVisible) {
            if (check.isWpEditor) {
                inputsVisible += ' ' + check.elem.getAttribute('name');
                inputsVisibleCount++;
            } else {
                inputsInvisible += ' ' + check.elem.getAttribute('name');
                inputsInvisibleCount++;
            }
        } else {
            inputsVisible += ' ' + check.elem.getAttribute('name');
            inputsVisibleCount++;
        }
    });

    inputsInvisible = inputsInvisible.trim();
    inputsVisible = inputsVisible.trim();

    return {
        visible_fields: inputsVisible,
        visible_fields_count: inputsVisibleCount,
        invisible_fields: inputsInvisible,
        invisible_fields_count: inputsInvisibleCount,
    };
}

// Old code uses the feature only through this object
window.apbctVisibleFields = {
    collect: collectVisibleFields,
    setCookie: setVisibleFieldsCookie,
};
