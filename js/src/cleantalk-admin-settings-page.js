jQuery(document).ready(function() {
    // Crunch for Right to Left direction languages
    if (document.getElementsByClassName('apbct_settings-title')[0]) {
        if (getComputedStyle(document.getElementsByClassName('apbct_settings-title')[0]).direction === 'rtl') {
            jQuery('.apbct_switchers').css('text-align', 'right');
        }
    }

    // Show/Hide access key
    jQuery('#apbct_showApiKey').on('click', function(e) {
        e.preventDefault();
        jQuery(this).hide();
        jQuery('.apbct_settings-field--api_key').val(jQuery('.apbct_settings-field--api_key').attr('key'));
        jQuery('.apbct_settings-field--api_key+div').css('display', 'inline');
    });

    let d = new Date();
    let timezone = d.getTimezoneOffset()/60*(-1);
    jQuery('#ct_admin_timezone').val(timezone);

    // Key KEY automatically
    jQuery('#apbct_button__get_key_auto').on('click', function() {
        if (!jQuery('#apbct_license_agreed').is(':checked')) {
            jQuery('#apbct_settings__no_agreement_notice').show();
            apbctHighlightElement('apbct_license_agreed', 3);
            return;
        }
        apbct_admin_sendAJAX(
            {action: 'apbct_get_key_auto', ct_admin_timezone: timezone},
            {
                timeout: 25000,
                button: document.getElementById('apbct_button__get_key_auto' ),
                spinner: jQuery('#apbct_button__get_key_auto .apbct_preloader_button' ),
                callback: function(result, data, params, obj) {
                    jQuery('#apbct_button__get_key_auto .apbct_success').show(300);
                    setTimeout(function() {
                        jQuery('#apbct_button__get_key_auto .apbct_success').hide(300);
                    }, 2000);
                    if (result.reload) {
                        document.location.reload();
                    }
                    if (result.getTemplates) {
                        cleantalkModal.loaded = result.getTemplates;
                        cleantalkModal.open();
                        document.addEventListener('cleantalkModalClosed', function( e ) {
                            document.location.reload();
                        });
                    }
                },
            },
        );
    });

    // Import settings
    jQuery( document ).on('click', '#apbct_settings_templates_import_button', function() {
        jQuery('#apbct-ajax-result').remove();
        let optionSelected = jQuery('option:selected', jQuery('#apbct_settings_templates_import'));
        let templateNameInput = jQuery('#apbct_settings_templates_import_name');
        templateNameInput.css('border-color', 'inherit');
        if ( typeof optionSelected.data('id') === 'undefined' ) {
            console.log( 'Attribute "data-id" not set for the option.' );
            return;
        }
        let data = {
            'template_id': optionSelected.data('id'),
            'template_name': optionSelected.data('name'),
            'settings': optionSelected.data('settings'),
        };
        let button = this;
        apbct_admin_sendAJAX(
            {action: 'settings_templates_import', data: data},
            {
                timeout: 25000,
                button: button,
                spinner: jQuery('#apbct_settings_templates_import_button .apbct_preloader_button' ),
                notJson: true,
                callback: function(result, data, params, obj) {
                    if (result.success) {
                        jQuery( '<p id=\'apbct-ajax-result\' class=\'success\'>' + result.data + '</p>' )
                            .insertAfter( jQuery(button) );
                        jQuery('#apbct_settings_templates_import_button .apbct_success').show(300);
                        setTimeout(function() {
                            jQuery('#apbct_settings_templates_import_button .apbct_success').hide(300);
                        }, 2000);
                        document.addEventListener('cleantalkModalClosed', function( e ) {
                            document.location.reload();
                        });
                        setTimeout(function() {
                            cleantalkModal.close();
                        }, 2000);
                    } else {
                        jQuery( '<p id=\'apbct-ajax-result\' class=\'error\'>' + result.data + '</p>' )
                            .insertAfter( jQuery(button) );
                    }
                },
            },
        );
    });

    // Export settings
    jQuery( document ).on('click', '#apbct_settings_templates_export_button', function() {
        jQuery('#apbct-ajax-result').remove();
        let optionSelected = jQuery('option:selected', jQuery('#apbct_settings_templates_export'));
        let templateNameInput = jQuery('#apbct_settings_templates_export_name');
        let data = {};
        templateNameInput.css('border-color', 'inherit');
        if ( typeof optionSelected.data('id') === 'undefined' ) {
            console.log( 'Attribute "data-id" not set for the option.' );
            return;
        }
        if ( optionSelected.data('id') === 'new_template' ) {
            let templateName = templateNameInput.val();
            if ( templateName === '' ) {
                templateNameInput.css('border-color', 'red');
                return;
            }
            data = {
                'template_name': templateName,
            };
        } else {
            data = {
                'template_id': optionSelected.data('id'),
            };
        }
        let button = this;
        apbct_admin_sendAJAX(
            {action: 'settings_templates_export', data: data},
            {
                timeout: 25000,
                button: button,
                spinner: jQuery('#apbct_settings_templates_export_button .apbct_preloader_button' ),
                notJson: true,
                callback: function(result, data, params, obj) {
                    if (result.success) {
                        jQuery( '<p id=\'apbct-ajax-result\' class=\'success\'>' + result.data + '</p>' )
                            .insertAfter( jQuery(button) );
                        jQuery('#apbct_settings_templates_export_button .apbct_success').show(300);
                        setTimeout(function() {
                            jQuery('#apbct_settings_templates_export_button .apbct_success').hide(300);
                        }, 2000);
                        document.addEventListener('cleantalkModalClosed', function( e ) {
                            document.location.reload();
                        });
                        setTimeout(function() {
                            cleantalkModal.close();
                        }, 2000);
                    } else {
                        jQuery( '<p id=\'apbct-ajax-result\' class=\'error\'>' + result.data + '</p>' )
                            .insertAfter( jQuery(button) );
                    }
                },
            },
        );
    });

    // Reset settings
    jQuery( document ).on('click', '#apbct_settings_templates_reset_button', function() {
        let button = this;
        apbct_admin_sendAJAX(
            {action: 'settings_templates_reset'},
            {
                timeout: 25000,
                button: button,
                spinner: jQuery('#apbct_settings_templates_reset_button .apbct_preloader_button' ),
                notJson: true,
                callback: function(result, data, params, obj) {
                    if (result.success) {
                        jQuery( '<p id=\'apbct-ajax-result\' class=\'success\'>' + result.data + '</p>' )
                            .insertAfter( jQuery(button) );
                        jQuery('#apbct_settings_templates_reset_button .apbct_success').show(300);
                        setTimeout(function() {
                            jQuery('#apbct_settings_templates_reset_button .apbct_success').hide(300);
                        }, 2000);
                        document.addEventListener('cleantalkModalClosed', function( e ) {
                            document.location.reload();
                        });
                        setTimeout(function() {
                            cleantalkModal.close();
                        }, 2000);
                    } else {
                        jQuery( '<p id=\'apbct-ajax-result\' class=\'error\'>' + result.data + '</p>' )
                            .insertAfter( jQuery(button) );
                    }
                },
            },
        );
    });

    // Sync button
    jQuery('#apbct_button__sync').on('click', function() {
        apbct_admin_sendAJAX(
            {action: 'apbct_sync'},
            {
                timeout: 25000,
                button: document.getElementById('apbct_button__sync' ),
                spinner: jQuery('#apbct_button__sync .apbct_preloader_button' ),
                callback: function(result, data, params, obj) {
                    jQuery('#apbct_button__sync .apbct_success').show(300);
                    setTimeout(function() {
                        jQuery('#apbct_button__sync .apbct_success').hide(300);
                    }, 2000);
                    if (result.reload) {
                        if ( ctSettingsPage.key_changed ) {
                            jQuery('.key_changed_sync').hide(300);
                            jQuery('.key_changed_success').show(300);
                            setTimeout(function() {
                                document.location.reload();
                            }, 3000);
                        } else {
                            document.location.reload();
                        }
                    }
                },
            },
        );
    });

    if ( ctSettingsPage.key_changed ) {
        jQuery('#apbct_button__sync').click();
    }

    jQuery(document).on('click', '.apbct_settings-long_description---show', function() {
        self = jQuery(this);
        apbctSettingsShowDescription(self, self.attr('setting'));
    });

    if (jQuery('#cleantalk_notice_renew').length || jQuery('#cleantalk_notice_trial').length) {
        apbctBannerCheck();
    }

    jQuery(document).on('change', '#apbct_settings_templates_export', function() {
        let optionSelected = jQuery('option:selected', this);
        if ( optionSelected.data('id') === 'new_template' ) {
            jQuery(this).parent().parent().find('#apbct_settings_templates_export_name').show();
        } else {
            jQuery(this).parent().parent().find('#apbct_settings_templates_export_name').hide();
        }
    });

    apbctSaveButtonPosition();
    let debounceTimer;
    window.addEventListener('scroll', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            apbctSaveButtonPosition();
        }, 50);
        apbctNavigationMenuPosition();
    });
    jQuery('#ct_adv_showhide a').on('click', apbctSaveButtonPosition);


    /**
     * Change cleantalk account email
     */
    jQuery('#apbct-change-account-email').on('click', function(e) {
        e.preventDefault();

        let $this = jQuery(this);
        let accountEmailField = jQuery('#apbct-account-email');
        let accountEmail = accountEmailField.text();

        $this.toggleClass('active');

        if ($this.hasClass('active')) {
            $this.text($this.data('save-text'));
            accountEmailField.attr('contenteditable', 'true');
            accountEmailField.on('keydown', function(e) {
                if (e.code === 'Enter') {
                    e.preventDefault();
                }
            });
            accountEmailField.on('input', function(e) {
                if (e.inputType === 'insertParagraph') {
                    e.preventDefault();
                }
            });
        } else {
            apbct_admin_sendAJAX(
                {
                    action: 'apbct_update_account_email',
                    accountEmail: accountEmail,
                },
                {
                    timeout: 5000,
                    callback: function(result, data, params, obj) {
                        if (result.success !== undefined && result.success === 'ok') {
                            if (result.manuallyLink !== undefined) {
                                jQuery('#apbct-key-manually-link').attr('href', result.manuallyLink);
                            }
                        }

                        if (result.error !== undefined) {
                            jQuery('#apbct-account-email').css('border-color', 'red');
                        }
                    },
                },
            );

            accountEmailField.attr('contenteditable', 'false');
            $this.text($this.data('default-text'));
        }
    });

    /**
     * Validate apkikey and hide get auto btn
     */
    jQuery('#apbct_setting_apikey').on('input', function() {
        let enteredValue = jQuery(this).val();
        jQuery('#apbct_settings__key_line__save_settings').off('click');
        let keyBad = enteredValue !== '' && enteredValue.match(/^[a-z\d]{8,30}\s*$/) === null;
        jQuery('#apbct_settings__key_is_bad').hide();
        jQuery('#apbct_showApiKey').hide();
        jQuery('#apbct_settings__account_name_ob').hide();
        jQuery('#apbct_settings__no_agreement_notice').hide();
        if (enteredValue === '') {
            jQuery('#apbct_button__key_line__save_changes_wrapper').hide();
            jQuery('#apbct_button__get_key_auto__wrapper').show();
            jQuery('#apbct_button__get_key_manual_chunk').show();
        } else {
            jQuery('#apbct_button__key_line__save_changes_wrapper').show();
            jQuery('#apbct_button__get_key_auto__wrapper').hide();
            jQuery('#apbct_button__get_key_manual_chunk').hide();
            if (keyBad) {
                jQuery('#apbct_settings__key_line__save_settings').on('click',
                    function(e) {
                        e.preventDefault();
                        jQuery('#apbct_settings__key_is_bad').show();
                        apbctHighlightElement('apbct_setting_apikey', 3);
                    },
                );
            }
        }
    });

    if ( jQuery('#apbct_setting_apikey').val() && ctSettingsPage.key_is_ok) {
        jQuery('#apbct_button__get_key_auto__wrapper').hide();
    }

    /**
     * Handle synchronization errors when key is no ok to force user check the key and restart the sync
     */
    if ( !ctSettingsPage.key_is_ok && !ctSettingsPage.ip_license ) {
        jQuery('button.cleantalk_link[value="save_changes"]').on('click',
            function(e) {
                e.preventDefault();
                if (!jQuery('#sync_required_notice').length) {
                    jQuery( '<div class=\'apbct_notice_inner error\'><h4 id=\'sync_required_notice\'>' +
                        'Synchronization process failed. Please, check the acces key and restart the synch.' +
                        '<h4></div>' ).insertAfter( jQuery('#apbct_button__sync') );
                }
                apbctHighlightElement('apbct_setting_apikey', 3);
                apbctHighlightElement('apbct_button__sync', 3);
                jQuery('#apbct_button__get_key_auto__wrapper').show();
            },
        );
    }

    /**
     * Open WP gallery for adding custom logo
     */
    jQuery('#apbct-custom-logo-open-gallery').click(function(e) {
        e.preventDefault();

        const button = jQuery(this);

        const customUploader = wp.media({
            library: {
                type: 'image',
            },
            multiple: false,
        });

        customUploader.on('select', function() {
            const image = customUploader.state().get('selection').first().toJSON();

            button.parent().prev().attr( 'src', image.url );
            jQuery('#cleantalk_custom_logo').val( image.id );
        });

        customUploader.open();
    });

    /**
     * Remove selected logo
     */
    jQuery('#apbct-custom-logo-remove-image').click(function(e) {
        e.preventDefault();

        if ( true === confirm( 'Sure?' ) ) {
            const src = jQuery(this).parent().prev().data('src');
            jQuery(this).parent().prev().attr('src', src);
            jQuery(this).prev().prev().val('');
        }
    });

    jQuery('button[id*="apbct-action-adjust-change-"]').click(function(e) {
        e.preventDefault();

        let data = {};
        data.action = 'apbct_action_adjust_change';
        data.adjust = jQuery(this).data('adjust');

        let params = {};
        params.button = document.getElementById('apbct-action-adjust-change-' + data.adjust);
        params.notJson = true;

        params.callback = function() {
            document.location.reload();
        };

        apbct_admin_sendAJAX(data, params);
    });

    jQuery('button[id*="apbct-action-adjust-reverse-"]').click(function(e) {
        e.preventDefault();

        let data = {};
        data.action = 'apbct_action_adjust_reverse';
        data.adjust = jQuery(this).data('adjust');

        let params = {};
        params.button = document.getElementById('apbct-action-adjust-reverse-' + data.adjust);
        params.notJson = true;

        params.callback = function() {
            document.location.reload();
        };

        apbct_admin_sendAJAX(data, params);
    });

    document.querySelector('.apbct_hidden_section_nav_mob_btn').addEventListener('click', () => {
        document.querySelector('#apbct_hidden_section_nav ul').style.display = 'block';
        document.querySelector('.apbct_hidden_section_nav_mob_btn').style.display = 'none';
    });

    document.querySelector('.apbct_hidden_section_nav_mob_btn-close').addEventListener('click', () => {
        document.querySelector('#apbct_hidden_section_nav ul').style.display = 'none';
        document.querySelector('.apbct_hidden_section_nav_mob_btn').style.display = 'block';
    });

    // Hide/show EmailEncoder replacing text textarea
    apbctManageEmailEncoderCustomTextField();

    if (window.location.hash) {
        const anchor = window.location.hash.substring(1);
        handleAnchorDetection(anchor);
    }
});

/**
 * Detect ancors and open advanced settings before scroll
 * @param {string} anchor
 */
function handleAnchorDetection(anchor) {
    let advSettings = document.querySelector('#apbct_settings__advanced_settings');
    if ( 'none' === advSettings.style.display ) {
        apbctExceptedShowHide('apbct_settings__advanced_settings');
    }
    scrollToAnchor('#' + anchor);
}

/**
 * Scroll to the target element ID
 * @param {string} anchorId Anchor target element ID
 */
function scrollToAnchor(anchorId) {
    const targetElement = document.querySelector(anchorId);
    if (targetElement) {
        targetElement.scrollIntoView({
            block: 'end',
        });
    }
}

/**
 * Hide/show EmailEncoder replacing text textarea
 */
function apbctManageEmailEncoderCustomTextField() {
    const replacingText = document
        .querySelector('#apbct_setting_data__email_decoder_obfuscation_custom_text');
    let replacingTextWrapperSub;
    if (replacingText !== null) {
        replacingTextWrapperSub = typeof replacingText.parentElement !== 'undefined' ?
            replacingText.parentElement :
            null;
    }
    document.querySelectorAll('.apbct_setting---data__email_decoder_obfuscation_mode').forEach((elem) => {
        // visibility set on saved settings
        if (replacingTextWrapperSub && elem.checked && elem.value !== 'replace') {
            replacingTextWrapperSub.classList.add('hidden');
        }
        // visibility set on change
        elem.addEventListener('click', (event) => {
            if (typeof replacingTextWrapperSub !== 'undefined') {
                if (event.target.value === 'replace') {
                    replacingTextWrapperSub.classList.remove('hidden');
                } else {
                    replacingTextWrapperSub.classList.add('hidden');
                }
            }
        });
    });
}

/**
 * Checking current account status for renew notice
 */
function apbctBannerCheck() {
    let bannerChecker = setInterval( function() {
        apbct_admin_sendAJAX(
            {action: 'apbct_settings__check_renew_banner'},
            {
                callback: function(result, data, params, obj) {
                    if (result.close_renew_banner) {
                        if (jQuery('#cleantalk_notice_renew').length) {
                            jQuery('#cleantalk_notice_renew').hide('slow');
                        }
                        if (jQuery('#cleantalk_notice_trial').length) {
                            jQuery('#cleantalk_notice_trial').hide('slow');
                        }
                        clearInterval(bannerChecker);
                    }
                },
            },
        );
    }, 900000);
}

/**
 * Select elems like #{selector} or .{selector}
 * Selector passed in string separated by ,
 *
 * @param {string|array} elems
 * @return {*}
 */
function apbctGetElems(elems) {
    elems = elems.split(',');
    for ( let i=0, len = elems.length, tmp; i < len; i++) {
        tmp = jQuery('#'+elems[i]);
        elems[i] = tmp.length === 0 ? jQuery('.'+elems[i]) : tmp;
    }
    return elems;
}

/**
 * Select elems like #{selector} or .{selector}
 * Selector could be passed in a string ( separated by comma ) or in array ( [ elem1, elem2, ... ] )
 *
 * @param {string|array} elems
 * @return {array}
 */
function apbctGetElemsNative(elems) {
    // Make array from a string
    if (typeof elems === 'string') {
        elems = elems.split(',');
    }

    let out = [];

    elems.forEach(function(elem, i, arr) {
        // try to get elements with such IDs
        let tmp = document.getElementById(elem);
        if (tmp !== null) {
            out.push( tmp[key] );
            return;
        }

        // try to get elements with such class name
        // write each elem from collection to new element of output array
        tmp = document.getElementsByClassName(elem);
        if (tmp !== null && tmp.length !==0 ) {
            for (key in tmp) {
                if ( +key >= 0 ) {
                    out.push( tmp[key] );
                }
            }
        }
    });

    return out;
}

/**
 * @param {string|array} elems
 */
function apbctShowHideElem(elems) {
    elems = apbctGetElems(elems);
    for ( let i=0, len = elems.length; i < len; i++) {
        elems[i].each(function(i, elem) {
            elem = jQuery(elem);
            let label = elem.next('label') || elem.prev('label') || null;
            if (elem.is(':visible')) {
                elem.hide();
                if (label) label.hide();
            } else {
                elem.show();
                if (label) label.show();
            }
        });
    }
}

/**
 * @param {string|array} element
 */
function apbctExceptedShowHide(element) { // eslint-disable-line no-unused-vars
    let toHide = [
        'apbct_settings__dwpms_settings',
        'apbct_settings__advanced_settings',
        'trusted_and_affiliate__special_span',
    ];
    let index = toHide.indexOf(element);
    if (index !== -1) {
        toHide.splice(index, 1);
    }
    apbctShowHideElem(element);
    toHide.forEach((toHideElem) => {
        if (document.getElementById(toHideElem) && document.getElementById(toHideElem).style.display !== 'none') {
            apbctShowHideElem(toHideElem);
        }
    });
}

/**
 * @param {mixed} event
 * @param {string} id
 */
function apbctShowRequiredGroups(event, id) { // eslint-disable-line no-unused-vars
    let required = document.getElementById('apbct_settings__dwpms_settings');
    if (required && required.style.display === 'none') {
        let originEvent = event;
        event.preventDefault();
        apbctShowHideElem('apbct_settings__dwpms_settings');
        document.getElementById(id).dispatchEvent(new originEvent.constructor(originEvent.type, originEvent));
    }
}

/**
 * Settings dependences. Switch|toggle depended elements state (disabled|enabled)
 * Recieve list of selectors ( without class mark (.) or id mark (#) )
 *
 * @param {string|array} ids
 * @param {int} enable
 */
function apbctSettingsDependencies(ids, enable) { // eslint-disable-line no-unused-vars
    enable = ! isNaN(enable) ? enable : null;

    // Get elements
    let elems = apbctGetElemsNative( ids );

    elems.forEach(function(elem, i, arr) {
        let doDisable = function() {
            elem.setAttribute('disabled', 'disabled');
        };
        let doEnable = function() {
            elem.removeAttribute('disabled');
        };

        // Set defined state
        if (enable === null) {
            enable = elem.getAttribute('disabled') === null ? 0 : 1;
        }

        enable === 1 ? doEnable() : doDisable();

        if ( elem.getAttribute('apbct_children') !== null) {
            let state = apbctSettingsDependenciesGetState( elem ) && enable;
            if ( state !== null ) {
                apbctSettingsDependencies( elem.getAttribute('apbct_children'), state );
            }
        }
    });
}

/**
 * @param {HTMLElement} elem
 * @return {int|null}
 */
function apbctSettingsDependenciesGetState(elem) {
    let state;

    switch ( elem.getAttribute( 'type' ) ) {
    case 'checkbox':
        state = +elem.checked;
        break;
    case 'radio':
        state = +(+elem.getAttribute('value') === 1);
        break;
    default:
        state = null;
    }

    return state;
}

/**
 * @param {HTMLElement} label
 * @param {string} settingId
 */
function apbctSettingsShowDescription(label, settingId) {
    let removeDescFunc = function(e) {
        const callerIsPopup = jQuery(e.target).parent('.apbct_long_desc').length != 0;
        const callerIsHideCross = jQuery(e.target).hasClass('apbct_long_desc__cancel');
        const descIsShown = jQuery('.apbct_long_desc__title').length > 0;
        if (descIsShown && !callerIsPopup || callerIsHideCross) {
            jQuery('.apbct_long_desc').remove();
            jQuery(document).off('click', removeDescFunc);
        }
    };

    label.after('<div id=\'apbct_long_desc__'+settingId+'\' class=\'apbct_long_desc\'></div>');
    let obj = jQuery('#apbct_long_desc__'+settingId);
    obj.append('<i class= \'apbct-icon-spin1 animate-spin\'></i>')
        .append('<div class=\'apbct_long_desc__angle\'></div>')
        .css({
            top: label.position().top - 5,
            left: label.position().left + 25,
        });


    apbct_admin_sendAJAX(
        {action: 'apbct_settings__get__long_description', setting_id: settingId},
        {
            spinner: obj.children('img'),
            callback: function(result, data, params, obj) {
                obj.empty()
                    .append('<div class=\'apbct_long_desc__angle\'></div>')
                    .append('<i class=\'apbct_long_desc__cancel apbct-icon-cancel\'></i>')
                    .append('<h3 class=\'apbct_long_desc__title\'>'+result.title+'</h3>')
                    .append('<p>'+result.desc+'</p>');

                jQuery(document).on('click', removeDescFunc);
            },
        },
        obj,
    );
}

/**
 * Set position for navigation menu
 * @return {void}
 */
function apbctNavigationMenuPosition() {
    const navBlock = document.querySelector('#apbct_hidden_section_nav ul');
    const rightBtnSave = document.querySelector('#apbct_settings__button_section');
    if (!navBlock || !rightBtnSave) {
        return;
    }
    const scrollPosition = window.scrollY;
    const windowWidth = window.innerWidth;
    if (scrollPosition > 1000) {
        navBlock.style.position = 'fixed';
        rightBtnSave.style.position = 'fixed';
    } else {
        navBlock.style.position = 'static';
        rightBtnSave.style.position = 'static';
    }

    if (windowWidth < 768) {
        rightBtnSave.style.position = 'fixed';
    }
}

/**
 * Set position for save button, hide it if scrolled to the bottom
 * @return {void}
 */
function apbctSaveButtonPosition() {
    if (
        document.getElementById('apbct_settings__before_advanced_settings') === null ||
        document.getElementById('apbct_settings__after_advanced_settings') === null ||
        document.getElementById('apbct_settings__button_section') === null ||
        document.getElementById('apbct_settings__advanced_settings') === null ||
        document.getElementById('apbct_hidden_section_nav') === null
    ) {
        return;
    }

    if (!ctSettingsPage.key_is_ok && !ctSettingsPage.ip_license) {
        jQuery('#apbct_settings__main_save_button').hide();
        return;
    }

    const additionalSaveButton =
        document.querySelector('#apbct_settings__button_section, cleantalk_link[value="save_changes"]');
    if (!additionalSaveButton) {
        return;
    }

    const scrollPosition = window.scrollY;
    const documentHeight = document.documentElement.scrollHeight;
    const windowHeight = window.innerHeight;
    const threshold = 800;
    if (scrollPosition + windowHeight >= documentHeight - threshold) {
        additionalSaveButton.style.display = 'none';
    } else {
        additionalSaveButton.style.display = 'block';
    }

    const advSettingsBlock = document.getElementById('apbct_settings__advanced_settings');
    const mainSaveButton = document.getElementById('apbct_settings__block_main_save_button');
    if (!advSettingsBlock || !mainSaveButton) {
        return;
    }

    if (advSettingsBlock.style.display == 'none') {
        mainSaveButton.classList.remove('apbct_settings__position_main_save_button');
    } else {
        mainSaveButton.classList.add('apbct_settings__position_main_save_button');
    }
}

/**
 * Hightlights element
 *
 * @param {string} id
 * @param {int} times
 */
function apbctHighlightElement(id, times) {
    times = times-1 || 0;
    let keyField = jQuery('#'+id);
    jQuery('html, body').animate({scrollTop: keyField.offset().top - 100}, 'slow');
    keyField.addClass('apbct_highlighted');
    keyField.animate({opacity: 0}, 400, 'linear', function() {
        keyField.animate({opacity: 1}, 400, 'linear', function() {
            if (times>0) {
                apbctHighlightElement(id, times);
            } else {
                keyField.removeClass('apbct_highlighted');
            }
        });
    });
}

/**
 * Open external link in a new tab
 * @param {string} url
 */
function apbctStatisticsOpenExternalLink(url) { // eslint-disable-line no-unused-vars
    window.open(url, '_blank');
}

/**
 * Open modal to create support user
 */
function apbctCreateSupportUser() { // eslint-disable-line no-unused-vars
    const localTextArray = ctSettingsPage.support_user_creation_msg_array;
    cleantalkModal.loaded = false;
    cleantalkModal.open(false);
    cleantalkModal.confirm(
        localTextArray.confirm_header,
        localTextArray.confirm_text,
        '',
        apbctCreateSupportUserCallback,
    );
}

/**
 * Create support user
 */
function apbctCreateSupportUserCallback() {
    const preloader = jQuery('#apbct_summary_and_support-create_user_button_preloader')
    preloader.css('display', 'block');
    apbct_admin_sendAJAX(
        {
            action: 'apbct_action__create_support_user',
        },
        {
            timeout: 10000,
            notJson: 1,
            callback: function(result, data, params, obj) {
                let localTextArray = ctSettingsPage.support_user_creation_msg_array;
                let popupMsg = localTextArray.default_error;
                const responseValid = (
                    typeof result === 'object' &&
                    result.hasOwnProperty('success') &&
                    result.hasOwnProperty('user_created') &&
                    result.hasOwnProperty('mail_sent') &&
                    result.hasOwnProperty('cron_updated') &&
                    result.hasOwnProperty('user_data') &&
                    result.hasOwnProperty('result_code') &&
                    typeof result.user_data === 'object' &&
                    result.user_data.hasOwnProperty('username') &&
                    result.user_data.hasOwnProperty('email') &&
                    result.user_data.hasOwnProperty('password')
                );
                if (responseValid && result.success) {
                    if (result.user_created) {
                        let mailSentMsg = '';
                        let successCreationMsg = '';
                        let cronUpdatedMsg = localTextArray.cron_updated;

                        if (result.mail_sent) {
                            mailSentMsg = localTextArray.mail_sent_success;
                        } else {
                            mailSentMsg = localTextArray.mail_sent_error;
                        }

                        if (result.result_code === 0) {
                            successCreationMsg = localTextArray.user_updated;
                        } else {
                            successCreationMsg = localTextArray.user_created;
                        }

                        jQuery('#apbct_summary_and_support-user_creation_username').text(result.user_data.username);
                        jQuery('#apbct_summary_and_support-user_creation_email').text(result.user_data.email);
                        jQuery('#apbct_summary_and_support-user_creation_password').text(result.user_data.password);
                        jQuery('#apbct_summary_and_support-user_creation_mail_sent').text(mailSentMsg);
                        jQuery('#apbct_summary_and_support-user_creation_title').text(successCreationMsg);
                        jQuery('#apbct_summary_and_support-user_creation_cron_updated').text(cronUpdatedMsg);
                        jQuery('.apbct_summary_and_support-user_creation_result').css('display', 'block');
                        const createUserButton = jQuery('#apbct_summary_and_support-create_user_button');
                        createUserButton.attr('disabled', true);
                        createUserButton.css('color', 'rgba(93,89,86,0.55)');
                        createUserButton.css('background', '#cccccc');
                        preloader.css('display', 'none');
                        return;
                    } else {
                        if (result.result_code === -2) {
                            popupMsg = localTextArray.invalid_permission;
                        } else if (result.result_code === -1) {
                            popupMsg = localTextArray.unknown_creation_error;
                        } else if (result.result_code === -4) {
                            popupMsg = localTextArray.on_cooldown;
                        } else if (result.result_code === -5) {
                            popupMsg = localTextArray.email_is_busy;
                        }
                    }
                }
                preloader.css('display', 'none');
                cleantalkModal.loaded = popupMsg;
                cleantalkModal.open();
            },
            errorOutput: function(msg) {
                preloader.css('display', 'none');
                cleantalkModal.loaded = msg;
                cleantalkModal.open();
            },
        },
    );
}
