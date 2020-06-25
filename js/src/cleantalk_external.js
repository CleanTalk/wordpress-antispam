function ct_protect_external(){

	for(var i = 0; i < document.forms.length; i++){

		if (document.forms[i].cleantalk_hidden_action == undefined && document.forms[i].cleantalk_hidden_method == undefined) {

			if(typeof(document.forms[i].action) == 'string'){

				var action = document.forms[i].action;

				// Fix for ActiveCampaign form
				if(
					action.indexOf('activehosted.com') !== -1 ||
					action.indexOf('app.convertkit.com') !== -1
				) {

					jQuery( document.forms[i] ).before('<i class="cleantalk_placeholder" style="display: none;"></i>')

					// Deleting form to prevent submit event
					var prev = jQuery(document.forms[i]).prev(),
						form_html = document.forms[i].outerHTML,
						form_original = jQuery(document.forms[i]).detach(),
						index = i;

					prev.after( form_html );

					var force_action = document.createElement("input");
					force_action.name = 'action';
					force_action.value = 'cleantalk_force_ajax_check';
					force_action.type = 'hidden';
					document.forms[i].appendChild(force_action);

					document.forms[i].onsubmit = function ( event ){

						event.preventDefault();

						// Get visible fields and set cookie
						apbct_collect_visible_fields_and_set_cookie(this);

						var data = jQuery(event.target).serialize();

						apbct_sendAJAXRequest(
							data,
							{
								async: true,
								callback: function( index, prev, form_original, result ){

									if( ! +result.apbct.blocked ) {

										var form_new = jQuery(document.forms[index]).detach();

										apbct_replace_inputs_values_from_other_form(form_new, form_original);

										prev.after(form_original);

										// Common click event
										var subm_button = jQuery(document.forms[index]).find('button[type=submit]');
										if( subm_button.length !== 0 ) {
											subm_button[0].click();
										}

										// ConvertKit direct integration
										subm_button = jQuery(document.forms[index]).find('button[data-element="submit"]');
										if( subm_button.length !== 0 ) {
											subm_button[0].click();
										}

									}
								},
								callback_context: null,
								callback_params: [index, prev, form_original],
							}
						);

					};

				// Common flow
				}else if(action.indexOf('http://') !== -1 || action.indexOf('https://') !== -1){

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