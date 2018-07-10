jQuery(document).ready(function(){
	
	// Auto update banner close handler
	jQuery('.apbct_update_notice').on('click', 'button', function(){
		var ct_date = new Date(new Date().getTime() + 1000 * 86400 * 30 );
		document.cookie = "apbct_update_banner_closed=1; path=/; expires=" + ct_date.toUTCString();
	});
	
	// GDPR modal window
	jQuery('#apbct_gdpr_open_modal').on('click', function(){
		jQuery('#gdpr_dialog').dialog({
			modal:true, 
			show: true,
			position: { my: "center", at: "center", of: window },
			width: +(jQuery('#wpbody').width() / 100 * 70), // 70% of #wpbody
			height: 'auto',
			title: 'GDPR compliance',
			draggable: false,
			resizable: false,
			closeText: "Close",
		});
	});
	
});

// Settings dependences
function spbcSettingsDependencies(spbcSettingSwitchId){
	var spbcSettingToSwitch = document.getElementById(spbcSettingSwitchId);
	if(spbcSettingToSwitch.getAttribute('disabled') === null)
		spbcSettingToSwitch.setAttribute('disabled', 'disabled');
	else
		spbcSettingToSwitch.removeAttribute('disabled');
}