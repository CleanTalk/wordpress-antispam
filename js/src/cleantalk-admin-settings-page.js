jQuery(document).ready(function(){

	// Top level settings
	jQuery('.apbct_setting---data__email_decoder').on('click', (event) => {
		if ( event.target.type === 'checkbox' ) {
			const postFix = event.target.checked ? '__On' : '__Off';
			document.getElementById('apbct_setting_data__email_decoder' + postFix).checked = true;
		} else {
			document.getElementById('apbct_setting_data__email_decoder').checked = parseInt(event.target.value) === 1;
		}
	});

	// Crunch for Right to Left direction languages
	if(document.getElementsByClassName('apbct_settings-title')[0]) {
		if(getComputedStyle(document.getElementsByClassName('apbct_settings-title')[0]).direction === 'rtl'){
			jQuery('.apbct_switchers').css('text-align', 'right');
		}
	}

	// Show/Hide access key
    jQuery('#apbct_showApiKey').on('click', function(){
        jQuery('.apbct_setting---apikey').val(jQuery('.apbct_setting---apikey').attr('key'));
        jQuery('.apbct_setting---apikey+div').show();
        jQuery(this).fadeOut(300);
    });

	var d = new Date();
	jQuery('#ct_admin_timezone').val(d.getTimezoneOffset()/60*(-1));

	// Key KEY automatically
	jQuery('#apbct_button__get_key_auto').on('click', function(){
		apbct_admin_sendAJAX(
			{action: 'apbct_get_key_auto'},
			{
				timeout: 25000,
				button: document.getElementById('apbct_button__get_key_auto' ),
				spinner: jQuery('#apbct_button__get_key_auto .apbct_preloader_button' ),
				callback: function(result, data, params, obj){
					jQuery('#apbct_button__get_key_auto .apbct_success').show(300);
					setTimeout(function(){jQuery('#apbct_button__get_key_auto .apbct_success').hide(300);}, 2000);
					if(result.reload)
						document.location.reload();
					if(result.getTemplates) {
						cleantalkModal.loaded = result.getTemplates;
						cleantalkModal.open();
						document.addEventListener("cleantalkModalClosed", function( e ) {
							document.location.reload();
						});
					}
				}
			}
		);
	});

	// Import settings
	jQuery( document ).on('click', '#apbct_settings_templates_import_button', function(){
		jQuery('#apbct-ajax-result').remove();
		var optionSelected = jQuery('option:selected', jQuery('#apbct_settings_templates_import'));
		var templateNameInput = jQuery('#apbct_settings_templates_import_name');
		templateNameInput.css('border-color', 'inherit');
		if( typeof optionSelected.data('id') === "undefined" ) {
			console.log( 'Attribute "data-id" not set for the option.' );
			return;
		}
		var data = {
			'template_id' : optionSelected.data('id'),
			'template_name' : optionSelected.data('name'),
			'settings' : optionSelected.data('settings')
		};
		var button = this;
		apbct_admin_sendAJAX(
			{action: 'settings_templates_import', data: data},
			{
				timeout: 25000,
				button: button,
				spinner: jQuery('#apbct_settings_templates_import_button .apbct_preloader_button' ),
				notJson: true,
				callback: function(result, data, params, obj){
					if(result.success) {
						jQuery( "<p id='apbct-ajax-result' class='success'>" + result.data + "</p>" ).insertAfter( jQuery(button) );
						jQuery('#apbct_settings_templates_import_button .apbct_success').show(300);
						setTimeout(function(){jQuery('#apbct_settings_templates_import_button .apbct_success').hide(300);}, 2000);
						document.addEventListener("cleantalkModalClosed", function( e ) {
							document.location.reload();
						});
						setTimeout(function(){cleantalkModal.close()}, 2000);
					} else {
						jQuery( "<p id='apbct-ajax-result' class='error'>" + result.data + "</p>" ).insertAfter( jQuery(button) );
					}
				}
			}
		);
	});

	// Export settings
	jQuery( document ).on('click', '#apbct_settings_templates_export_button', function(){
		jQuery('#apbct-ajax-result').remove();
		var optionSelected = jQuery('option:selected', jQuery('#apbct_settings_templates_export'));
		var templateNameInput = jQuery('#apbct_settings_templates_export_name');
		templateNameInput.css('border-color', 'inherit');
		if( typeof optionSelected.data('id') === "undefined" ) {
			console.log( 'Attribute "data-id" not set for the option.' );
			return;
		}
		if( optionSelected.data('id') === 'new_template' ) {
			var templateName = templateNameInput.val();
			if( templateName === '' ) {
				templateNameInput.css('border-color', 'red');
				return;
			}
			var data = {
				'template_name' : templateName
			}
		} else {
			var data = {
				'template_id' : optionSelected.data('id')
			}
		}
		var button = this;
		apbct_admin_sendAJAX(
			{action: 'settings_templates_export', data: data},
			{
				timeout: 25000,
				button: button,
				spinner: jQuery('#apbct_settings_templates_export_button .apbct_preloader_button' ),
				notJson: true,
				callback: function(result, data, params, obj){
					if(result.success) {
						jQuery( "<p id='apbct-ajax-result' class='success'>" + result.data + "</p>" ).insertAfter( jQuery(button) );
						jQuery('#apbct_settings_templates_export_button .apbct_success').show(300);
						setTimeout(function(){jQuery('#apbct_settings_templates_export_button .apbct_success').hide(300);}, 2000);
						document.addEventListener("cleantalkModalClosed", function( e ) {
							document.location.reload();
						});
						setTimeout(function(){cleantalkModal.close()}, 2000);
					} else {
						jQuery( "<p id='apbct-ajax-result' class='error'>" + result.data + "</p>" ).insertAfter( jQuery(button) );
					}
				}
			}
		);
	});

	// Reset settings
	jQuery( document ).on('click', '#apbct_settings_templates_reset_button', function(){
		var button = this;
		apbct_admin_sendAJAX(
			{action: 'settings_templates_reset'},
			{
				timeout: 25000,
				button: button,
				spinner: jQuery('#apbct_settings_templates_reset_button .apbct_preloader_button' ),
				notJson: true,
				callback: function(result, data, params, obj){
					if(result.success) {
						jQuery( "<p id='apbct-ajax-result' class='success'>" + result.data + "</p>" ).insertAfter( jQuery(button) );
						jQuery('#apbct_settings_templates_reset_button .apbct_success').show(300);
						setTimeout(function(){jQuery('#apbct_settings_templates_reset_button .apbct_success').hide(300);}, 2000);
						document.addEventListener("cleantalkModalClosed", function( e ) {
							document.location.reload();
						});
						setTimeout(function(){cleantalkModal.close()}, 2000);
					} else {
						jQuery( "<p id='apbct-ajax-result' class='error'>" + result.data + "</p>" ).insertAfter( jQuery(button) );
					}
				}
			}
		);
	});

	// Sync button
	jQuery('#apbct_button__sync').on('click', function(){
		apbct_admin_sendAJAX(
			{action: 'apbct_sync'},
			{
				timeout: 25000,
				button: document.getElementById('apbct_button__sync' ),
				spinner: jQuery('#apbct_button__sync .apbct_preloader_button' ),
				callback: function(result, data, params, obj){
					jQuery('#apbct_button__sync .apbct_success').show(300);
					setTimeout(function(){jQuery('#apbct_button__sync .apbct_success').hide(300);}, 2000);
					if(result.reload)
						document.location.reload();
				}
			}
		);
	});

	if( ctSettingsPage.key_changed )
		jQuery('#apbct_button__sync').click();

	jQuery(document).on('click', '.apbct_settings-long_description---show', function(){
		self = jQuery(this);
		apbct_settings__showDescription(self, self.attr('setting'));
	});

	if (jQuery('#cleantalk_notice_renew').length || jQuery('#cleantalk_notice_trial').length)
		apbct_banner_check();

	jQuery(document).on('change', '#apbct_settings_templates_export',function(){
		var optionSelected = jQuery("option:selected", this);
		if ( optionSelected.data("id") === 'new_template' ) {
			jQuery(this).parent().parent().find('#apbct_settings_templates_export_name').show();
		} else {
			jQuery(this).parent().parent().find('#apbct_settings_templates_export_name').hide();
		}
	});

	apbct_save_button_position();
	window.addEventListener('scroll', apbct_save_button_position);
	jQuery('#ct_adv_showhide a').on('click', apbct_save_button_position);


	/**
	 * Change cleantalk account email
	 */
	jQuery('#apbct-change-account-email').on('click', function (e) {
		e.preventDefault();

		var $this = jQuery(this);
		var accountEmailField = jQuery('#apbct-account-email');
		var accountEmail = accountEmailField.text();

		$this.toggleClass('active');

		if ($this.hasClass('active')) {
			$this.text($this.data('save-text'));
			accountEmailField.attr('contenteditable', 'true');
			accountEmailField.on('keydown', function (e) {
				if (e.code === 'Enter') {
					e.preventDefault()
				}
			})
			accountEmailField.on('input', function (e) {
				if (e.inputType === 'insertParagraph') {
					e.preventDefault()
				}
			})
		} else {
			apbct_admin_sendAJAX(
				{
					action: 'apbct_update_account_email',
					accountEmail: accountEmail
				},
				{
					timeout: 5000,
					callback: function(result, data, params, obj){
						if (result.success !== undefined && result.success === 'ok') {
							if (result.manuallyLink !== undefined) {
								jQuery('#apbct-key-manually-link').attr('href', result.manuallyLink);
							}
						}

						if (result.error !== undefined) {
							jQuery('#apbct-account-email').css('border-color', 'red');
						}
					}
				}
			);

			accountEmailField.attr('contenteditable', 'false');
			$this.text($this.data('default-text'));
		}
	});

	/**
	 * Validate apkikey and hide get auto btn
	 */
	jQuery('#apbct_setting_apikey').on('input', function () {
		var enteredValue = jQuery(this).val();
		jQuery('button.cleantalk_link[value="save_changes"]').off('click')
		if (enteredValue !== '' && enteredValue.match(/^[a-z\d]{3,30}\s*$/) === null) {
			jQuery('#apbct_button__get_key_auto__wrapper').show();
			jQuery('button.cleantalk_link[value="save_changes"]').on('click',
					function (e) {
						e.preventDefault()
						if (!jQuery('#apbct_bad_key_notice').length){
							jQuery( "<div class='apbct_notice_inner error'><h4 id='apbct_bad_key_notice'>" +
								"Please, insert a correct access key before saving changes!" +
								"</h4></div>" ).insertAfter( jQuery('#apbct_setting_apikey') );
						}
						apbct_highlight_element('apbct_setting_apikey',3)

					}
				)
			return;
		}

	});

	if ( jQuery('#apbct_setting_apikey').val() && ctSettingsPage.key_is_ok) {
		jQuery('#apbct_button__get_key_auto__wrapper').hide();
	}

	/**
	 * Handle synchronization errors when key is no ok to force user check the key and restart the sync
	 */
	if( !ctSettingsPage.key_is_ok ){
		jQuery('button.cleantalk_link[value="save_changes"]').on('click',
			function (e) {
				e.preventDefault()
				if (!jQuery('#sync_required_notice').length){
					jQuery( "<div class='apbct_notice_inner error'><h4 id='sync_required_notice'>" +
						"Synchronization process failed. Please, check the acces key and restart the synch." +
						"<h4></div>" ).insertAfter( jQuery('#apbct_button__sync') );
				}
				apbct_highlight_element('apbct_setting_apikey',3)
				apbct_highlight_element('apbct_button__sync',3)
				jQuery('#apbct_button__get_key_auto__wrapper').show();
			}
		)
	}

});

/**
 * Checking current account status for renew notice
 */
function apbct_banner_check() {
	var bannerChecker = setInterval( function() {
		apbct_admin_sendAJAX(
			{action: 'apbct_settings__check_renew_banner'},
			{
				callback: function(result, data, params, obj){
					if (result.close_renew_banner) {
						if (jQuery('#cleantalk_notice_renew').length)
							jQuery('#cleantalk_notice_renew').hide('slow');
						if (jQuery('#cleantalk_notice_trial').length)
							jQuery('#cleantalk_notice_trial').hide('slow');
						clearInterval(bannerChecker);
					}
				}
			}
		);
	}, 900000);
}

/**
 * Select elems like #{selector} or .{selector}
 * Selector passed in string separated by ,
 *
 * @param elems
 * @returns {*}
 */
function apbct_get_elems(elems){
    elems = elems.split(',');
    for( var i=0, len = elems.length, tmp; i < len; i++){
        tmp = jQuery('#'+elems[i]);
        elems[i] = tmp.length === 0 ? jQuery('.'+elems[i]) : tmp;
    }
    return elems;
}

/**
 * Select elems like #{selector} or .{selector}
 * Selector could be passed in a string ( separated by comma ) or in array ( [ elem1, elem2, ... ] )
 *
 * @param elems string|array
 * @returns array
 */
function apbct_get_elems__native(elems){

	// Make array from a string
	if(typeof elems === 'string')
		elems = elems.split(',');

	var out = [];

	elems.forEach(function(elem, i, arr) {

		// try to get elements with such IDs
		var tmp = document.getElementById(elem);
		if (tmp !== null){
			out.push( tmp[key] );
			return;
		}

		// try to get elements with such class name
		// write each elem from collection to new element of output array
		tmp = document.getElementsByClassName(elem);
		if (tmp !== null && tmp.length !==0 ){
			for(key in tmp){
				if( +key >= 0 ){
					out.push( tmp[key] );
				}
			}
		}
	});

	return out;
}

function apbct_show_hide_elem(elems){
	elems = apbct_get_elems(elems);
    for( var i=0, len = elems.length; i < len; i++){
        elems[i].each(function (i, elem) {
            elem = jQuery(elem);
            var label = elem.next('label') || elem.prev('label') || null;
            if (elem.is(":visible")) {
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
 * Settings dependences. Switch|toggle depended elements state (disabled|enabled)
 * Recieve list of selectors ( without class mark (.) or id mark (#) )
 *
 * @param ids string|array Selectors
 * @param enable
 */
function apbctSettingsDependencies(ids, enable){


	enable = ! isNaN(enable) ? enable : null;

	// Get elements
	var elems = apbct_get_elems__native( ids );

	elems.forEach(function(elem, i, arr){

		var do_disable = function(){elem.setAttribute('disabled', 'disabled');},
			do_enable  = function(){elem.removeAttribute('disabled');};

		// Set defined state
		if(enable === null) // Set
			enable = elem.getAttribute('disabled') === null ? 0 : 1;

		enable === 1 ? do_enable() : do_disable();

		if( elem.getAttribute('apbct_children') !== null){
			var state = apbctSettingsDependencies_getState( elem ) && enable;
			if( state !== null ) {
				apbctSettingsDependencies( elem.getAttribute('apbct_children'), state  );
			}
		}

	});
}

function apbctSettingsDependencies_getState( elem ){

	var state;

	switch ( elem.getAttribute( 'type' ) ){
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

function apbct_settings__showDescription(label, setting_id){

	var remove_desc_func = function(e){
		if(typeof e === 'undefined' || ((jQuery(e.target).parent('.apbct_long_desc').length == 0 || jQuery(e.target).hasClass('apbct_long_desc__cancel')) && !jQuery(e.target).hasClass('apbct_long_description__show'))){
			jQuery('.apbct_long_desc').remove();
			jQuery(document).off('click', remove_desc_func);
		}
	};

	remove_desc_func();

	label.after("<div id='apbct_long_desc__"+setting_id+"' class='apbct_long_desc'></div>");
	var obj = jQuery('#apbct_long_desc__'+setting_id);
	obj.append("<i class= 'apbct-icon-spin1 animate-spin'></i>")
		.append("<div class='apbct_long_desc__angle'></div>")
		.css({
			top: label.position().top - 5,
			left: label.position().left + 25
		});


	apbct_admin_sendAJAX(
		{action: 'apbct_settings__get__long_description', setting_id: setting_id},
		{
			spinner: obj.children('img'),
			callback: function(result, data, params, obj){

				obj.empty()
					.append("<div class='apbct_long_desc__angle'></div>")
					.append("<i class='apbct_long_desc__cancel apbct-icon-cancel'></i>")
					.append("<h3 class='apbct_long_desc__title'>"+result.title+"</h3>")
					.append("<p>"+result.desc+"</p>");

				jQuery(document).on('click', remove_desc_func);
			}
		},
		obj
	);
}

function apbct_save_button_position() {
	if (
		document.getElementById('apbct_settings__before_advanced_settings') === null ||
		document.getElementById('apbct_settings__after_advanced_settings') === null ||
		document.getElementById('apbct_settings__button_section') === null ||
		document.getElementById('apbct_settings__advanced_settings') === null ||
		document.getElementById('apbct_hidden_section_nav') === null
	) {
		return;
	}
	var docInnerHeight = window.innerHeight;
	var advSettingsBlock = document.getElementById('apbct_settings__advanced_settings');
	var advSettingsOffset = advSettingsBlock.getBoundingClientRect().top;
	var buttonBlock = document.getElementById('apbct_settings__button_section');
	var buttonHeight = buttonBlock.getBoundingClientRect().height;
	var navBlock = document.getElementById('apbct_hidden_section_nav');
	var navBlockOffset = navBlock.getBoundingClientRect().top;
	var navBlockHeight = navBlock.getBoundingClientRect().height;

	// Set Save button position
	if ( getComputedStyle(advSettingsBlock).display !== "none" ) {
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

	// Set nav position
	if ( advSettingsOffset <= 0 ) {
		navBlock.style.top = - advSettingsOffset + 30 + 'px';
	} else {
		navBlock.style.top = 0;
	}
}

// Hightlights element
function apbct_highlight_element(id, times){
	times = times-1 || 0;
	let key_field = jQuery('#'+id)
	jQuery("html, body").animate({ scrollTop: key_field.offset().top - 100 }, "slow");
	key_field.addClass('apbct_highlighted');
	key_field.animate({opacity: 0 }, 400, 'linear', function(){
		key_field.animate({opacity: 1 }, 400, 'linear', function(){
			if(times>0){
				apbct_highlight_element(id, times);
			}else{
				key_field.removeClass('apbct_highlighted');
			}
		});
	});
}