// eslint-disable-next-line camelcase,require-jsdoc
function apbct_collect_visible_fields( form ) {
    // Get only fields
    let inputs = [];
    let inputsVisible = '';
    let inputsVisibleCount = 0;
    let inputsInvisible = '';
    let inputsInvisibleCount = 0;
    let inputsWithDuplicateNames = [];

    for (let key in form.elements) {
        if (!isNaN(+key)) {
            inputs[key] = form.elements[key];
        }
    }

    // Filter fields
    inputs = inputs.filter(function(elem) {
        // Filter already added fields
        if ( inputsWithDuplicateNames.indexOf( elem.getAttribute('name') ) !== -1 ) {
            return false;
        }
        // Filter inputs with same names for type == radio
        if ( -1 !== ['radio', 'checkbox'].indexOf( elem.getAttribute('type') )) {
            inputsWithDuplicateNames.push( elem.getAttribute('name') );
            return false;
        }
        return true;
    });

    // Visible fields
    inputs.forEach(function(elem, i, elements) {
        // Unnecessary fields
        if (
            elem.getAttribute('type') === 'submit' || // type == submit
            elem.getAttribute('name') === null ||
            elem.getAttribute('name') === 'ct_checkjs'
        ) {
            return;
        }
        // Invisible fields
        if (
            getComputedStyle(elem).display === 'none' || // hidden
            getComputedStyle(elem).visibility === 'hidden' || // hidden
            getComputedStyle(elem).opacity === '0' || // hidden
            elem.getAttribute('type') === 'hidden' // type == hidden
        ) {
            if ( elem.classList.contains('wp-editor-area') ) {
                inputsVisible += ' ' + elem.getAttribute('name');
                inputsVisibleCount++;
            } else {
                inputsInvisible += ' ' + elem.getAttribute('name');
                inputsInvisibleCount++;
            }
            // eslint-disable-next-line brace-style
        }
        // Visible fields
        else {
            inputsVisible += ' ' + elem.getAttribute('name');
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

// eslint-disable-next-line camelcase,require-jsdoc
function apbct_visible_fields_set_cookie( visibleFieldsCollection, formId ) {
    let collection = typeof visibleFieldsCollection === 'object' && visibleFieldsCollection !== null ?
        visibleFieldsCollection : {};

    if ( ctPublic.data__cookies_type === 'native' ) {
        // eslint-disable-next-line guard-for-in
        for ( let i in collection ) {
            if ( i > 10 ) {
                // Do not generate more than 10 cookies
                return;
            }
            let collectionIndex = formId !== undefined ? formId : i;
            ctSetCookie('apbct_visible_fields_' + collectionIndex, JSON.stringify( collection[i] ) );
        }
    } else {
        ctSetCookie('apbct_visible_fields', JSON.stringify( collection ) );
    }
}
