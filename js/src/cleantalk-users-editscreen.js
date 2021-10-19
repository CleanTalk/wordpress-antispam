function ct_is_email(str){
	return str.search(/.*@.*\..*/);
}
function ct_is_ip(str){
	return str.search(/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/);
}

jQuery(document).ready(function(){


	if(parseInt(ctUsersScreen.ct_show_check_links)) {

		/* Shows link to blacklists near every email and IP address */
		jQuery('.column-email a').each(function(){
			var ct_curr_str = jQuery(this).html();
			if( ct_is_email(ct_curr_str) !== -1 ){
				jQuery(this).after('&nbsp;<a href="https://cleantalk.org/blacklists/'+ct_curr_str+'" target="_blank" title="https://cleantalk.org/blacklists/'+ct_curr_str+'" class="ct_link_new_tab"><img src="'+ctUsersScreen.ct_img_src_new_tab+'"></a>');
			}
		});

		/* Show checked ico near avatar */
		jQuery('.username.column-username').each(function(){

			var apbct_checking_el = jQuery(this).siblings('.apbct_status').children('span');
			var apbct_checking_status = apbct_checking_el.attr('id');
			var apbct_checking_status_text = apbct_checking_el.text();

			var apbct_add_text_element = jQuery('<span>', {
				text : apbct_checking_status_text
			});
			var apbct_add_ico_ok = jQuery('<i>', {
				class :  'apbct-icon-ok'
			});
			var apbct_add_ico_cancel = jQuery('<i>', {
				class :  'apbct-icon-cancel',
				css : {
					color : 'red'
				}
			});

			if( apbct_checking_status ==='apbct_not_checked' ) {
				jQuery(this).children('.row-actions').before(apbct_add_ico_ok).before(apbct_add_text_element);
			}
			if( apbct_checking_status ==='apbct_checked_not_spam' ) {
				apbct_add_ico_ok.attr('style', 'color:green;');
				jQuery(this).children('.row-actions').before(apbct_add_ico_ok).before(apbct_add_text_element);
			}
			if( apbct_checking_status ==='apbct_checked_spam' ) {
				jQuery(this).children('.row-actions').before(apbct_add_ico_cancel).before(apbct_add_text_element);
			}

		});

	}

});