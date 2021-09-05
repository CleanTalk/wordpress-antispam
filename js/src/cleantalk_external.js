function ct_protect_external(){

	for(var i = 0; i < document.forms.length; i++){

		if (document.forms[i].cleantalk_hidden_action == undefined && document.forms[i].cleantalk_hidden_method == undefined) {

			if(typeof(document.forms[i].action) == 'string'){

				var action = document.forms[i].action;

				if(
					action.indexOf('activehosted.com') !== -1 ||   // ActiveCampaign form
					action.indexOf('app.convertkit.com') !== -1 || // ConvertKit form
					( document.forms[i].firstChild.classList !== undefined && document.forms[i].firstChild.classList.contains('cb-form-group') ) || // Convertbox form
                    ( document.forms[i].classList !== undefined && document.forms[i].classList.contains('ml-block-form') )
				) {

					jQuery( document.forms[i] ).before('<i class="cleantalk_placeholder" style="display: none;"></i>');

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
						var visible_fields = {};
						visible_fields[0] = apbct_collect_visible_fields(this);
						apbct_visible_fields_set_cookie( visible_fields );

						var data = {};
						var elems = event.target.elements;
						elems = Array.prototype.slice.call(elems);

						elems.forEach( function( elem, y ) {
							if( elem.name === '' ) {
								data['input_' + y] = elem.value;
							} else {
								data[elem.name] = elem.value;
							}
						});

						apbct_public_sendAJAX(
							data,
							{
								async: false,
								callback: function( index, prev, form_original, result ){

									if( ! +result.apbct.blocked ) {

										var form_new = jQuery(event.target).detach();

										apbct_replace_inputs_values_from_other_form(form_new, form_original);

										prev.after( form_original );

										// Common click event
										var subm_button = jQuery(form_original).find('button[type=submit]');
										if( subm_button.length !== 0 ) {
											subm_button[0].click();
										}

										// ConvertKit direct integration
										subm_button = jQuery(form_original).find('button[data-element="submit"]');
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
function apbct_replace_inputs_values_from_other_form( form_source, form_target ){

	var	inputs_source = jQuery( form_source ).find( 'button, input, textarea, select' ),
		inputs_target = jQuery( form_target ).find( 'button, input, textarea, select' );

	inputs_source.each( function( index, elem_source ){

		var source = jQuery( elem_source );

		inputs_target.each( function( index2, elem_target ){

			var target = jQuery( elem_target );

			if( elem_source.outerHTML === elem_target.outerHTML ){

				target.val( source.val() );
			}
		});
	});

}
window.onload = function () {
    setTimeout(function () {
        ct_protect_external()
    }, 1500);
};
