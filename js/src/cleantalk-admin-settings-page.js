jQuery(document).ready(function(){
	var d = new Date();
	jQuery('#ct_admin_timezone').val(d.getTimezoneOffset()/60*(-1));
	
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

function apbct_show_hide_elem(elem){
	elem = jQuery(elem);
	var label = elem.next('label') || elem.prev('label') || null;
	if(elem.is(":visible")){
		elem.hide();
		if(label) label.hide();
	}else{
		elem.show();
		if(label) label.show();
	}
}

// Settings dependences
function apbctSettingsDependencies(settingsIDs){
	console.log(settingsIDs);
	if(typeof settingsIDs === 'string'){
		tmp = [];
		tmp.push(settingsIDs);
		settingsIDs = tmp;
	}
	console.log(settingsIDs);
	settingsIDs.forEach(function(settingID, i, arr){
		console.log(settingID);
		var elem = document.getElementById('apbct_setting_'+settingID) || null;
		console.log(elem);
		if(elem){
			apbctSwitchDisabledAttr(elem);
		}else{
			apbctSwitchDisabledAttr(document.getElementById('apbct_setting_'+settingID+'_yes'));
			apbctSwitchDisabledAttr(document.getElementById('apbct_setting_'+settingID+'_no'));
		}
	});
}

function apbctSwitchDisabledAttr(elem){
	if(elem.getAttribute('disabled') === null)
		elem.setAttribute('disabled', 'disabled');
	else
		elem.removeAttribute('disabled');
}