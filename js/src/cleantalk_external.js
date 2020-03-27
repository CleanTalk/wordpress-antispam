function ct_protect_external(){
			
	for(var i = 0; i < document.forms.length; i++){
		if (document.forms[i].cleantalk_hidden_action == undefined && document.forms[i].cleantalk_hidden_method == undefined) {
			if(typeof(document.forms[i].action) == 'string'){
				
				var action = document.forms[i].action;

				if(action.indexOf('http://') !== -1 || action.indexOf('https://') !== -1){
					
					var tmp = action.split('//');
					tmp = tmp[1].split('/');
					var host = tmp[0].toLowerCase();

					if(host !== location.hostname.toLowerCase()){

	                    var ct_action = document.createElement("input");
	                    ct_action.name = 'cleantalk_hidden_action';
						ct_action.value = action;
						ct_action.type = 'hidden';
						document.forms[i].appendChild(ct_action);

	                    var ct_method = document.createElement("input");
	                    ct_method.name = 'cleantalk_hidden_method';
						ct_method.value = document.forms[i].method;
						ct_method.type = 'hidden';
											
						document.forms[i].method = 'POST';
						document.forms[i].appendChild(ct_method);
						
						document.forms[i].action = document.location;
					}
				}
			}
		}
	}
}

window.onload = function () {
    setTimeout(function () {
        ct_protect_external()
    }, 1500);
};