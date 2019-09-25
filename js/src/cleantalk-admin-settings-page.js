jQuery(document).ready(function(){

    // Show/Hide access key
    jQuery('#apbct_showApiKey').on('click', function(){
        jQuery('.apbct_setting---apikey').val(jQuery('.apbct_setting---apikey').attr('key'));
        jQuery('.apbct_setting---apikey+div').show();
        jQuery(this).fadeOut(300);
    });

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

function apbct_get_elems(elems){
    elems = elems.split(',');
    for( var i=0, len = elems.length, tmp; i < len; i++){
        tmp = jQuery('#'+elems[i]);
        elems[i] = tmp.length === 0 ? jQuery('.'+elems[i]) : tmp;
    }
    return elems;

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
 * Settings dependences
 *
 * @param elems CSS selector
 */
function apbctSettingsDependencies(elems){
    elems = apbct_get_elems(elems);
    for( var i=0, len = elems.length; i < len; i++){
        elems[i].each(function (i, elem) {
            apbct_toggleAtrribute(jQuery(elem), 'disabled');
        });
    }
}

/**
 * Toggle attribute 'disabled' for elements
 *
 * @param elem
 * @param attribute
 * @param value
 */
function apbct_toggleAtrribute(elem, attribute, value){
    value = value || attribute;
    if(typeof elem.attr(attribute) === 'undefined')
        elem.attr(attribute, value);
    else
        elem.removeAttr(attribute);
}