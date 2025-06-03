/**
 * Get type of the field should be excluded. Return exclusion signs via object.
 * @param {object} form Form dom object.
 * @return {object} {'no_cookie': 1|0, 'visible_fields': 1|0}
 */
function ctGetHiddenFieldExclusionsType(form) {
    // visible fields
    let result = {'no_cookie': 0, 'visible_fields': 0};
    if (
        +ctPublic.data__visible_fields_required === 0 ||
        (form.method.toString().toLowerCase() === 'get' &&
        form.querySelectorAll('.nf-form-content').length === 0 &&
        form.id !== 'twt_cc_signup') ||
        form.classList.contains('slp_search_form') || // StoreLocatorPlus form
        form.parentElement.classList.contains('mec-booking') ||
        form.action.toString().indexOf('activehosted.com') !== -1 || // Active Campaign
        (form.id && form.id === 'caspioform') || // Caspio Form
        (form.classList && form.classList.contains('tinkoffPayRow')) || // TinkoffPayForm
        (form.classList && form.classList.contains('give-form')) || // GiveWP
        (form.id && form.id === 'ult-forgot-password-form') || // ult forgot password
        (form.id && form.id.toString().indexOf('calculatedfields') !== -1) || // CalculatedFieldsForm
        (form.id && form.id.toString().indexOf('sac-form') !== -1) || // Simple Ajax Chat
        (form.id &&
            form.id.toString().indexOf('cp_tslotsbooking_pform') !== -1) || // WP Time Slots Booking Form
        (form.name &&
            form.name.toString().indexOf('cp_tslotsbooking_pform') !== -1) || // WP Time Slots Booking Form
        form.action.toString() === 'https://epayment.epymtservice.com/epay.jhtml' || // Custom form
        (form.name && form.name.toString().indexOf('tribe-bar-form') !== -1) || // The Events Calendar
        (form.id && form.id === 'ihf-login-form') || // Optima Express login
        (form.id &&
            form.id === 'subscriberForm' &&
            form.action.toString().indexOf('actionType=update') !== -1) || // Optima Express update
        (form.id && form.id === 'ihf-main-search-form') || // Optima Express search
        (form.id && form.id === 'frmCalc') || // nobletitle-calc
        form.action.toString().indexOf('property-organizer-delete-saved-search-submit') !== -1 ||
        form.querySelector('a[name="login"]') !== null // digimember login form
    ) {
        result.visible_fields = 1;
    }

    // ajax search pro exclusion
    let ncFieldExclusionsSign = form.parentNode;
    if (
        ncFieldExclusionsSign && ncFieldExclusionsSign.classList.contains('proinput') ||
        (form.name === 'options' && form.classList.contains('asp-fss-flex'))
    ) {
        result.no_cookie = 1;
    }

    // woocommerce login form
    if (
        form && form.classList.contains('woocommerce-form-login')
    ) {
        result.visible_fields = 1;
        result.no_cookie = 1;
    }

    return result;
}

/**
 * Check if the form should be skipped from hidden field attach.
 * Return exclusion description if it is found, false otherwise.
 * @param {object} form Form dom object.
 * @param {string} hiddenFieldType Type of hidden field that needs to be checked.
 * Possible values: 'no_cookie'|'visible_fields'.
 * @return {boolean}
 */
function ctCheckHiddenFieldsExclusions(form, hiddenFieldType) {
    const formAction = typeof(form.action) == 'string' ? form.action : '';
    // Ajax Search Lite
    if (Boolean(form.querySelector('fieldset.asl_sett_scroll'))) {
        return true;
    }
    // Super WooCommerce Product Filter
    if (form.classList.contains('swpf-instant-filtering')) {
        return true;
    }
    // PayU 3-rd party service forms
    if (formAction.indexOf('secure.payu.com') !== -1 ) {
        return true;
    }

    if (formAction.indexOf('hsforms') !== -1 ) {
        return true;
    }

    if (typeof (hiddenFieldType) === 'string' &&
        ['visible_fields', 'no_cookie'].indexOf(hiddenFieldType) !== -1) {
        const exclusions = ctGetHiddenFieldExclusionsType(form);
        return exclusions[hiddenFieldType] === 1;
    }

    return false;
}
