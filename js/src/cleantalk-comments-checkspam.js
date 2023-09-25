// Printf for JS
String.prototype.printf = function() { // eslint-disable-line no-extend-native
    let formatted = this;
    for ( let arg in arguments ) { // eslint-disable-line guard-for-in,prefer-rest-params
        let beforeFormatted = formatted.substring(0, formatted.indexOf('%s', 0));
        let afterFormatted = formatted.substring(formatted.indexOf('%s', 0)+2, formatted.length);
        // eslint-disable-next-line guard-for-in,prefer-rest-params
        formatted = beforeFormatted + arguments[arg] + afterFormatted;
    }
    return formatted;
};

// Flags
let ctWorking = false;
let ctNewCheck = true;
let ctCoolingDownFlag = false;
let ctCloseAnimate = true;
let ctAccurateCheck = false;
let ctPause = false;
let ctPrevAccurate = ctCommentsCheck.ct_prev_accurate;
let ctPrevFrom = ctCommentsCheck.ct_prev_from;
let ctPrevTill = ctCommentsCheck.ct_prev_till;
// Settings
let ctCoolDownTime = 90000;
let ctRequestsCounter = 0;
let ctMaxRequests = 60;
// Variables
let ctAjaxNonce = ctCommentsCheck.ct_ajax_nonce;
let ctCommentsTotal = 0;
let ctCommentsChecked = 0;
let ctCommentsSpam = 0;
let ctCommentsBad = 0;
let ctUnchecked = 'unset';
let ctDateFrom = 0;
let ctDateTill = 0;

/**
 * @param {mixed} to
 * @param {string} id
 */
function animateComment(to, id) { // eslint-disable-line no-unused-vars
    if (ctCloseAnimate) {
        if (to==0.3) {
            jQuery('#comment-'+id).fadeTo(200, to, function() {
                animateComment(1, id);
            });
        } else {
            jQuery('#comment-'+id).fadeTo(200, to, function() {
                animateComment(0.3, id);
            });
        }
    } else {
        ctCloseAnimate =true;
    }
}

/**
 * clear comments
 */
function ctClearComments() {
    let from = 0; let till = 0;
    if (jQuery('#ct_allow_date_range').is(':checked')) {
        from = jQuery('#ct_date_range_from').val();
        till = jQuery('#ct_date_range_till').val();
    }
    let ctSecure = location.protocol === 'https:' ? '; secure' : '';
    document.cookie = 'apbct_check_comments_offset' + '=' + 0 + '; path=/; samesite=lax' + ctSecure;

    let data = {
        'action': 'ajax_clear_comments',
        'security': ctAjaxNonce,
        'from': from,
        'till': till,
    };

    jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        data: data,
        success: function(msg) {
            ctShowInfo();
            ctSendComments();
        },
    });
}

/**
 * Continues the check after cooldown time
 * Called by ct_send_users();
 */
function ctCoolingDownToggle() {
    ctCoolingDownFlag = false;
    ctSendComments();
    ctShowInfo();
}

/**
 * send comments
 */
function ctSendComments() {
    if (ctCoolingDownFlag === true) {
        return;
    }

    if (ctRequestsCounter >= ctMaxRequests) {
        setTimeout(ctCoolingDownToggle, ctCoolDownTime);
        ctRequestsCounter = 0;
        ctCoolingDownFlag = true;
        return;
    } else {
        ctRequestsCounter++;
    }

    let data = {
        'action': 'ajax_check_comments',
        'security': ctAjaxNonce,
        'new_check': ctNewCheck,
        'unchecked': ctUnchecked,
        'offset': Number(ctGetCookie('apbct_check_comments_offset')),
    };

    if (ctAccurateCheck) {
        data['accurate_check'] = true;
    }

    if (ctDateFrom && ctDateTill) {
        data['from'] = ctDateFrom;
        data['till'] = ctDateTill;
    }

    jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        data: data,
        success: function(msg) {
            msg = jQuery.parseJSON(msg);

            if (parseInt(msg.error)) {
                ctWorking = false;
                if (!confirm(msg.error_message+'. Do you want to proceed?')) {
                    let newHref = 'edit-comments.php?page=ct_check_spam';
                    if (ctDateFrom != 0 && ctDateTill != 0) {
                        newHref+='&from='+ctDateFrom+'&till='+ctDateTill;
                    }
                    location.href = newHref;
                } else {
                    ctSendComments();
                }
            } else {
                ctNewCheck = false;
                let offset = Number(ctGetCookie('apbct_check_comments_offset')) + 100;

                if (parseInt(msg.end) == 1 || ctPause === true) {
                    if (parseInt(msg.end) == 1) {
                        document.cookie = 'ct_paused_spam_check=0; path=/; samesite=lax';
                    }
                    ctWorking = false;
                    jQuery('#ct_working_message').hide();
                    let newHref = 'edit-comments.php?page=ct_check_spam';
                    if (ctDateFrom != 0 && ctDateTill != 0) {
                        newHref+='&from='+ctDateFrom+'&till='+ctDateTill;
                    }

                    document.cookie = 'apbct_check_comments_offset' + '=' + offset + '; path=/; samesite=lax'+ctSecure;

                    location.href = newHref;
                } else if (parseInt(msg.end) == 0) {
                    ctCommentsChecked += msg.checked;
                    ctCommentsSpam += msg.spam;
                    ctCommentsBad += msg.bad;
                    ctCommentsTotal += msg.total;
                    ctUnchecked = ctCommentsTotal - ctCommentsChecked - ctCommentsBad;
                    let statusString = String(ctCommentsCheck.ct_status_string);
                    stastatusStringtusString = statusString.printf(ctCommentsChecked, ctCommentsSpam, ctCommentsBad);
                    if (parseInt(ctCommentsSpam) > 0) {
                        stastatusStringtusString += ctCommentsCheck.ct_status_string_warning;
                    }
                    jQuery('#ct_checking_status').html(stastatusStringtusString);
                    jQuery('#ct_error_message').hide();
                    // If DB woks not properly
                    if (+ctCommentsTotal < ctCommentsChecked + ctCommentsBad) {
                        document.cookie = 'ct_comments_start_check=1; path=/; samesite=lax';
                        location.href = 'edit-comments.php?page=ct_check_spam';
                    }

                    document.cookie = 'apbct_check_comments_offset' + '=' + offset + '; path=/; samesite=lax'+ctSecure;

                    ctSendComments();
                }
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            jQuery('#ct_error_message').show();
            jQuery('#cleantalk_ajax_error').html(textStatus);
            jQuery('#cleantalk_js_func').html('Check comments');
            setTimeout(ctSendComments(), 3000);
        },
        timeout: 25000,
    });
}

/**
 * show info
 */
function ctShowInfo() {
    if (ctWorking) {
        if (ctCoolingDownFlag == true) {
            jQuery('#ct_cooling_notice').html('Waiting for API to cool down. (About a minute)');
            jQuery('#ct_cooling_notice').show();
            return;
        } else {
            jQuery('#ct_cooling_notice').hide();
        }

        if (!ctCommentsTotal) {
            let data = {
                'action': 'ajax_info_comments',
                'security': ctAjaxNonce,
            };

            if (ctDateFrom && ctDateTill) {
                data['from'] = ctDateFrom;
                data['till'] = ctDateTill;
            }

            jQuery.ajax({
                type: 'POST',
                url: ajaxurl,
                data: data,
                success: function(msg) {
                    msg = jQuery.parseJSON(msg);
                    jQuery('#ct_checking_status').html(msg.message);
                    ctCommentsTotal = msg.total;
                    ctCommentsSpam = msg.spam;
                    ctCommentsChecked = msg.checked;
                    ctCommentsBad = msg.bad;
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    jQuery('#ct_error_message').show();
                    jQuery('#cleantalk_ajax_error').html(textStatus);
                    jQuery('#cleantalk_js_func').html('Check comments');
                    setTimeout(ctShowInfo(), 3000);
                },
                timeout: 15000,
            });
        }
    }
}

/**
 * Function to toggle dependences
 *
 * @param {object} obj
 * @param {mixed} secondary
 */
function ctToggleDepended(obj, secondary) { // eslint-disable-line no-unused-vars
    secondary = secondary || null;

    let depended = jQuery(obj.data('depended'));
    let state = obj.data('state');

    if (!state && !secondary) {
        obj.data('state', true);
        depended.removeProp('disabled');
    } else {
        obj.data('state', false);
        depended.prop('disabled', true);
        depended.removeProp('checked');
        if (depended.data('depended')) {
            ctToggleDepended(depended, true);
        }
    }
}

/**
 * trash all
 *
 * @param {object} e
 */
function ctTrashAll( e ) {
    let data = {
        'action': 'ajax_trash_all',
        'security': ctAjaxNonce,
    };

    jQuery('.' + e.target.id).addClass('disabled');
    jQuery('.spinner').css('visibility', 'visible');
    jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        data: data,
        success: function( msg ) {
            if ( msg > 0 ) {
                jQuery('#cleantalk_comments_left').html(msg);
                ctTrashAll( e );
            } else {
                jQuery('.' + e.target.id).removeClass('disabled');
                jQuery('.spinner').css('visibility', 'hidden');
                location.href='edit-comments.php?page=ct_check_spam';
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            jQuery('#ct_error_message').show();
            jQuery('#cleantalk_ajax_error').html(textStatus);
            jQuery('#cleantalk_js_func').html('Check comments');
            setTimeout(ctTrashAll( e ), 3000);
        },
        timeout: 25000,
    });
}

/**
 * spam all
 *
 * @param {object} e
 */
function ctSpamAll( e ) {
    let data = {
        'action': 'ajax_spam_all',
        'security': ctAjaxNonce,
    };

    jQuery('.' + e.target.id).addClass('disabled');
    jQuery('.spinner').css('visibility', 'visible');
    jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        data: data,
        success: function( msg ) {
            if ( msg > 0 ) {
                jQuery('#cleantalk_comments_left').html(msg);
                ctSpamAll( e );
            } else {
                jQuery('.' + e.target.id).removeClass('disabled');
                jQuery('.spinner').css('visibility', 'hidden');
                location.href='edit-comments.php?page=ct_check_spam';
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            jQuery('#ct_error_message').show();
            jQuery('#cleantalk_ajax_error').html(textStatus);
            jQuery('#cleantalk_js_func').html('Check comments');
            setTimeout(ctSpamAll( e ), 3000);
        },
        timeout: 25000,
    });
}

jQuery(document).ready(function() {
    // Prev check parameters
    if (ctPrevAccurate) {
        jQuery('#ct_accurate_check').prop('checked', true);
    }
    if (ctPrevFrom) {
        jQuery('#ct_allow_date_range').prop('checked', true).data('state', true);
        jQuery('#ct_date_range_from').removeProp('disabled').val(ctPrevFrom);
        jQuery('#ct_date_range_till').removeProp('disabled').val(ctPrevTill);
    }

    // Toggle dependences
    jQuery('#ct_allow_date_range').on('change', function() {
        document.cookie = 'ct_spam_dates_from='+ jQuery('#ct_date_range_from').val() +'; path=/; samesite=lax';
        document.cookie = 'ct_spam_dates_till='+ jQuery('#ct_date_range_till').val() +'; path=/; samesite=lax';
        if ( this.checked ) {
            document.cookie = 'ct_spam_dates_allowed=1; path=/; samesite=lax';
            jQuery('.ct_date').prop('checked', true).attr('disabled', false);
        } else {
            document.cookie = 'ct_spam_dates_allowed=0; path=/; samesite=lax';
            jQuery('.ct_date').prop('disabled', true).attr('disabled', true);
        }
    });

    jQuery.datepicker.setDefaults(jQuery.datepicker.regional['en']);
    var dates = jQuery('#ct_date_range_from, #ct_date_range_till').datepicker( // eslint-disable-line no-var
        {
            dateFormat: 'M d yy',
            maxDate: '+0D',
            changeMonth: true,
            changeYear: true,
            showAnim: 'slideDown',
            onSelect: function(selectedDate) {
                let option = this.id == 'ct_date_range_from' ? 'minDate' : 'maxDate';
                let instance = jQuery( this ).data( 'datepicker' );
                let date = jQuery.datepicker.parseDate(
                    instance.settings.dateFormat || jQuery.datepicker._defaults.dateFormat,
                    selectedDate, instance.settings);
                dates.not(this).datepicker('option', option, date);
                document.cookie = 'ct_spam_dates_from='+ jQuery('#ct_date_range_from').val() +'; path=/; samesite=lax';
                document.cookie = 'ct_spam_dates_till='+ jQuery('#ct_date_range_till').val() +'; path=/; samesite=lax';
            },
        },
    );

    // eslint-disable-next-line require-jsdoc
    function ctStartCheck(continueCheck) {
        continueCheck = continueCheck || null;

        if (jQuery('#ct_allow_date_range').is(':checked')) {
            ctDateFrom = jQuery('#ct_date_range_from').val();
            ctDateTill = jQuery('#ct_date_range_till').val();

            if (!(ctDateFrom != '' && ctDateTill != '')) {
                alert('Please, specify a date range.');
                return;
            }
        }

        if (jQuery('#ct_accurate_check').is(':checked')) {
            ctAccurateCheck = true;
        }

        if (
            jQuery('#ct_accurate_check').is(':checked') &&
            ! jQuery('#ct_allow_date_range').is(':checked')
        ) {
            alert('Please, select a date range.');
            return;
        }

        jQuery('.ct_to_hide').hide();
        jQuery('#ct_working_message').show();
        jQuery('#ct_preloader').show();
        jQuery('#ct_pause').show();

        ctWorking = true;

        if (continueCheck) {
            ctShowInfo();
            ctSendComments();
        } else {
            ctClearComments();
        }
    }

    // Check comments
    jQuery('#ct_check_spam_button').click(function() {
        document.cookie = 'ct_paused_spam_check=0; path=/; samesite=lax';
        ctStartCheck(false);
    });
    jQuery('#ct_proceed_check_button').click(function() {
        ctStartCheck(true);
    });

    // Pause the check
    jQuery('#ct_pause').on('click', function() {
        ctPause = true;
        let ctCheck = {
            'accurate': ctAccurateCheck,
            'from': ctDateFrom,
            'till': ctDateTill,
        };
        document.cookie = 'ct_paused_spam_check=' + JSON.stringify(ctCheck) + '; path=/; samesite=lax';
    });


    if (ctCommentsCheck.start === '1') {
        document.cookie = 'ct_comments_start_check=0; expires=' + new Date(0).toUTCString() + '; path=/; samesite=lax';
        jQuery('#ct_check_spam_button').click();
    }

    // Delete all spam comments
    jQuery('.ct_trash_all').click(function( e ) {
        if (!confirm(ctCommentsCheck.ct_confirm_trash_all)) {
            return false;
        }

        ctTrashAll( e );
    });

    // Mark as spam all spam comments
    jQuery('.ct_spam_all').click(function( e ) {
        if (!confirm(ctCommentsCheck.ct_confirm_spam_all)) {
            return false;
        }

        ctSpamAll( e );
    });

    /**
     * Checked ct_accurate_check
     */
    jQuery('#ct_accurate_check').change(function() {
        if (this.checked) {
            jQuery('#ct_allow_date_range').prop('checked', true);
            jQuery('.ct_date').prop('checked', true).attr('disabled', false);
        }
    });
});


/**
 * Get cookie by name
 * @param {string} name
 * @return {string|undefined}
 */
function ctGetCookie(name) {
    let matches = document.cookie.match(new RegExp(
        '(?:^|; )' + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)',
    ));
    return matches ? decodeURIComponent(matches[1]) : undefined;
}
