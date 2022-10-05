function ct_check_internal(currForm){
    
//Gathering data
    var ct_data = {},
        elems = currForm.elements;

    for (var key in elems) {
        if(elems[key].type == 'submit' || elems[key].value == undefined || elems[key].value == '')
            continue;
        ct_data[elems[key].name] = currForm.elements[key].value;
    }
    ct_data['action'] = 'ct_check_internal';

    //AJAX Request
    apbct_public_sendAJAX(
        ct_data,
        {
            url: ctPublicFunctions._ajax_url,
            callback: function (data) {
                if(data.success === true){
                    currForm.submit();
                }else{
                    alert(data.data);
                    return false;
                }
            }
        }
    );
}

document.addEventListener('DOMContentLoaded',function(){
    let ct_currAction = '',
        ct_currForm = '';

    if( ! +ctPublic.settings__forms__check_internal ) {
        return;
    }

    let ctPrevHandler;
	for( let i=0; i<document.forms.length; i++ ){
		if ( typeof(document.forms[i].action) == 'string' ){
            ct_currForm = document.forms[i];
			ct_currAction = ct_currForm.action;
            if (
                ct_currAction.indexOf('https?://') !== null &&                        // The protocol is obligatory
                ct_currAction.match(ctPublic.blog_home + '.*?\.php') !== null && // Main check
                ! ct_check_internal__is_exclude_form(ct_currAction)                  // Exclude WordPress native scripts from processing
            ) {
                ctPrevHandler = ct_currForm.click;
                if ( typeof jQuery !== 'undefined' ) {
                    jQuery(ct_currForm).off('**');
                    jQuery(ct_currForm).off();
                    jQuery(ct_currForm).on('submit', function(event){
                        ct_check_internal(event.target);
                        return false;
                    });
                }
            }
		}
	}
});

/**
 * Check by action to exclude the form checking
 * @param action string
 * @return boolean
 */
function ct_check_internal__is_exclude_form(action) {
    // An array contains forms action need to be excluded.
    let ct_internal_script_exclusions = [
        'wp-login.php', // WordPress login page
        'wp-comments-post.php', // WordPress Comments Form
    ];

    return ct_internal_script_exclusions.some((item) => {
        return action.match(new RegExp(ctPublic.blog_home + '.*' + item)) !== null;
    });
}