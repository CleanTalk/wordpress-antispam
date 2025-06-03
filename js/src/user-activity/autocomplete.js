/**
 * Handler for -webkit based browser that listen for a custom
 * animation create using the :pseudo-selector in the stylesheet.
 * Works with Chrome, Safari
 *
 * @param {AnimationEvent} event
 */
// eslint-disable-next-line no-unused-vars,require-jsdoc
function apbctOnAnimationStart(event) {
    ('onautofillstart' === event.animationName) ?
        apbctAutocomplete(event.target) : apbctCancelAutocomplete(event.target);
}

/**
 * Handler for non-webkit based browser that listen for input
 * event to trigger the autocomplete-cancel process.
 * Works with Firefox, Edge, IE11
 *
 * @param {InputEvent} event
 */
// eslint-disable-next-line no-unused-vars,require-jsdoc
function apbctOnInput(event) {
    ('insertReplacementText' === event.inputType || !('data' in event)) ?
        apbctAutocomplete(event.target) : apbctCancelAutocomplete(event.target);
}

/**
 * Manage an input element when its value is autocompleted
 * by the browser in the following steps:
 * - add [autocompleted] attribute from event.target
 * - create 'onautocomplete' cancelable CustomEvent
 * - dispatch the Event
 *
 * @param {HtmlInputElement} element
 */
function apbctAutocomplete(element) {
    if (element.hasAttribute('autocompleted')) return;
    element.setAttribute('autocompleted', '');

    let event = new window.CustomEvent('onautocomplete', {
        bubbles: true, cancelable: true, detail: null,
    });

    // no autofill if preventDefault is called
    if (!element.dispatchEvent(event)) {
        element.value = '';
    }
}

/**
 * Manage an input element when its autocompleted value is
 * removed by the browser in the following steps:
 * - remove [autocompleted] attribute from event.target
 * - create 'onautocomplete' non-cancelable CustomEvent
 * - dispatch the Event
 *
 * @param {HtmlInputElement} element
 */
function apbctCancelAutocomplete(element) {
    if (!element.hasAttribute('autocompleted')) return;
    element.removeAttribute('autocompleted');

    // dispatch event
    element.dispatchEvent(new window.CustomEvent('onautocomplete', {
        bubbles: true, cancelable: false, detail: null,
    }));
}
