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
    jQuery.ajax({
        type: 'POST',
        url: ct_blog_home,
        datatype : 'text',
        data: ct_data,
        success: function(data){
            if(data == 'true'){
                currForm.submit();
            }else{
                alert(data);
                return false;
            }
        },
        error: function(){
            currForm.submit();
        }
    });        
}
    
jQuery(document).ready( function(){
    var ct_currAction = '',
        ct_currForm = '';
	for(i=0;i<document.forms.length;i++){
		if(typeof(document.forms[i].action)=='string'){
            ct_currForm = document.forms[i];
			ct_currAction = ct_currForm.action;
			if(ct_currAction.indexOf('http://')!=-1||ct_currAction.indexOf('https://')!=-1){
                if(ct_currAction.search(/\/wp-content\/themes.*\.php$/) != (-1) && ct_currAction.indexOf(ct_blog_home) != (-1)){
                    ctPrevHandler = ct_currForm.click;
                    jQuery(ct_currForm).off('**');
                    jQuery(ct_currForm).off();
                    jQuery(ct_currForm).on('submit', function(){
                        ct_check_internal(ct_currForm);
                        return false;
                    });
                }
            }
		}
	}
});