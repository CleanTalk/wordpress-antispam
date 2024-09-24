jQuery(document).ready(function() {
    // Top level settings
    jQuery('.apbct_setting---data__email_decoder').on('click', (event) => {
        if ( event.target.type === 'checkbox' ) {
            let currentTarget = event.target.checked ? 1 : 2;
            document.querySelectorAll('input[id^=apbct_setting_data__email_decoder]')[currentTarget].checked = true;
        } else {
            document.getElementById('apbct_setting_data__email_decoder').checked = parseInt(event.target.value) === 1;
        }
    });

    // Crunch for Right to Left direction languages
    if (document.getElementsByClassName('apbct_settings-title')[0]) {
        if (getComputedStyle(document.getElementsByClassName('apbct_settings-title')[0]).direction === 'rtl') {
            jQuery('.apbct_switchers').css('text-align', 'right');
        }
    }

    // Show/Hide access key
    jQuery('#apbct_showApiKey').on('click', function() {
        jQuery('.apbct_setting---apikey').val(jQuery('.apbct_setting---apikey').attr('key'));
        jQuery('.apbct_setting---apikey+div').show();
        jQuery(this).fadeOut(300);
    });

    let d = new Date();
    let timezone = d.getTimezoneOffset()/60*(-1);
    jQuery('#ct_admin_timezone').val(timezone);

    // Key KEY automatically
    jQuery('#apbct_button__get_key_auto').on('click', function() {
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
    window.addEventListener('scroll', apbctSaveButtonPosition);
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
        jQuery('button.cleantalk_link[value="save_changes"]').off('click');
        if (enteredValue !== '' && enteredValue.match(/^[a-z\d]{3,30}\s*$/) === null) {
            jQuery('#apbct_button__get_key_auto__wrapper').show();
            jQuery('button.cleantalk_link[value="save_changes"]').on('click',
                function(e) {
                    e.preventDefault();
                    if (!jQuery('#apbct_bad_key_notice').length) {
                        jQuery( '<div class=\'apbct_notice_inner error\'><h4 id=\'apbct_bad_key_notice\'>' +
                            'Please, insert a correct access key before saving changes!' +
                            '</h4></div>' ).insertAfter( jQuery('#apbct_setting_apikey') );
                    }
                    apbctHighlightElement('apbct_setting_apikey', 3);
                },
            );
            return;
        }
    });

    if ( jQuery('#apbct_setting_apikey').val() && ctSettingsPage.key_is_ok) {
        jQuery('#apbct_button__get_key_auto__wrapper').hide();
    }

    /**
     * Handle synchronization errors when key is no ok to force user check the key and restart the sync
     */
    if ( !ctSettingsPage.key_is_ok ) {
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
});

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
        if (typeof e === 'undefined' || (
            (jQuery(e.target).parent('.apbct_long_desc').length == 0 ||
            jQuery(e.target).hasClass('apbct_long_desc__cancel')
            ) &&
            !jQuery(e.target).hasClass('apbct_long_description__show'))
        ) {
            jQuery('.apbct_long_desc').remove();
            jQuery(document).off('click', removeDescFunc);
        }
    };

    removeDescFunc();

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
 * save button, navigation menu, navigation button position
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
    let docInnerHeight = window.innerHeight;
    let advSettingsBlock = document.getElementById('apbct_settings__advanced_settings');
    let advSettingsOffset = advSettingsBlock.getBoundingClientRect().top;
    let buttonBlock = document.getElementById('apbct_settings__button_section');
    let buttonHeight = buttonBlock.getBoundingClientRect().height;
    let navBlock = document.getElementById('apbct_hidden_section_nav');
    let navBlockOffset = navBlock.getBoundingClientRect().top;
    let navBlockHeight = navBlock.getBoundingClientRect().height;

    // Set Save button position
    if ( getComputedStyle(advSettingsBlock).display !== 'none' ) {
        jQuery('#apbct_settings__main_save_button').hide();
        if ( docInnerHeight < navBlockOffset + navBlockHeight + buttonHeight ) {
            buttonBlock.style.bottom = '';
            buttonBlock.style.top = navBlockOffset + navBlockHeight + 20 + 'px';
        } else {
            buttonBlock.style.bottom = 0;
            buttonBlock.style.top = '';
        }
    } else {
        jQuery('#apbct_settings__main_save_button').show();
    }

    if (window.innerWidth <= 768 && advSettingsOffset < 0) {
        document.querySelector('#apbct_hidden_section_nav').style.display = 'grid';
        document.querySelector('#apbct_hidden_section_nav').style.top = docInnerHeight + 'px';
    } else if (window.innerWidth <= 768) {
        document.querySelector('#apbct_hidden_section_nav').style.display = 'none';
    }

    // Set nav position
    if ( advSettingsOffset <= 0 ) {
        navBlock.style.top = - advSettingsOffset + 30 + 'px';
    } else {
        navBlock.style.top = 0;
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
