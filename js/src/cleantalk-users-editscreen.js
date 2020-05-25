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

			var apbct_checking_status = jQuery(this).siblings('.apbct_status').children('span').attr('id');

			if( apbct_checking_status ==='apbct_not_checked' ) {
				jQuery(this).prepend('<i class="icon-ok"></i>').attr('title','CleanTalk: Not checked.');
			}
			if( apbct_checking_status ==='apbct_checked_not_spam' ) {
				jQuery(this).prepend('<i class="icon-ok"></i>').attr('style', 'color:green;').attr('title','CleanTalk: Not spam.');
			}
			if( apbct_checking_status ==='apbct_checked_spam' ) {
				jQuery(this).prepend('<i class="icon-cancel"></i>').attr('style', 'color:red;').attr('title','CleanTalk: Spam.');
			}

		});

	}

});