var ct_adv_settings=null;
var ct_adv_settings_title=null;
var ct_adv_settings_show=false;
jQuery(document).ready(function(){
	var d = new Date();
	var n = d.getTimezoneOffset();
	var data = {
		'action': 'ajax_get_timezone',
		'security': ctSettingsPage.ct_ajax_nonce,
		'offset': n
	};
	jQuery.ajax({
		type: "POST",
		url: ajaxurl,
		data: data,
		success: function(msg){
			//
		}
	});
		
	if(cleantalk_good_key || ctSettingsPage.ip_license)
	{
		if(cleantalk_testing_failed !== true || ctSettingsPage.ip_license)
			jQuery('.form-table').first().find('tr').hide();
				
		banner_html="<div id='ct_stats_banner'>"+cleantalk_blocked_message;
		banner_html+=cleantalk_statistics_link+"&nbsp;&nbsp;"+cleantalk_support_link+'</div>';
		jQuery('.form-table').first().before(banner_html);
		if(!cleantalk_wpmu)
		{
//			jQuery('.form-table').first().before("<br /><a href='#' style='font-size:10pt;' id='cleantalk_access_key_link'>Show the access key</a>");
			jQuery('.form-table').first().before(cleantalk_support_links);
		}
	}else{
		jQuery('#ct_admin_timezone').val(d.getTimezoneOffset()/60*(-1));
	}
	
	jQuery('#cleantalk_access_key_link').click(function(){
		if(jQuery('.form-table').first().find('tr').eq(0).is(":visible"))
			jQuery('.form-table').first().find('tr').eq(0).hide();
		else
			jQuery('.form-table').first().find('tr').eq(0).show();
	});
	
	jQuery('#cleantalk_negative_report_link').click(function(){
		if(jQuery('.form-table').first().find('tr').eq(1).is(":visible")){
			jQuery('.form-table').first().find('tr').eq(1).hide();
		}else{
			jQuery('.form-table').first().find('tr').eq(1).show();
		}
	});
	
	// Adding subtitle
	jQuery("#ct_stats_banner").prev().after('<h4 style="color: gray; position: relative; margin: 0; top: -15px;">'+ctSettingsPage.ct_subtitle+'</h4>');
	
	ct_adv_settings=jQuery('#cleantalk_registrations_test1').parent().parent().parent().parent();
	ct_adv_settings.hide();
	ct_adv_settings_title=ct_adv_settings.prev();
	ct_adv_settings.wrap("<div id='ct_advsettings_hide'>");
	ct_adv_settings_title.append(" <span id='ct_adv_showhide' style='cursor:pointer'><b><a href='#' style='text-decoration:none;'></a></b></span>");
	ct_adv_settings_title.css('cursor','pointer');
	ct_adv_settings_title.click(function(){
		if(ct_adv_settings_show)
		{
			ct_adv_settings.hide();
			ct_adv_settings_show=false;
			jQuery('#ct_adv_showhide').html("<b><a href='#' style='text-decoration:none;'></a></b>");
		}
		else
		{
			ct_adv_settings.show();
			ct_adv_settings_show=true;
			jQuery('#ct_adv_showhide').html("<b><a href='#' style='text-decoration:none;'></a></b>");
		}
		
	});
	
	//For counters settings.
	jQuery('#ct_advsettings_hide').on('click', '#cleantalk_show_adminbar1', function(){
		jQuery('.ct-depends-of-show-adminbar').each(function(){
			jQuery(this).removeAttr('disabled');
		});
	});
	jQuery('#ct_advsettings_hide').on('click', '#cleantalk_show_adminbar0', function(){
		jQuery('.ct-depends-of-show-adminbar').each(function(){
			jQuery(this).attr('disabled', 'disabled');
		});
	});
});
