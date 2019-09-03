function ct_is_email(str){
	return str.search(/.*@.*\..*/);
}
function ct_is_ip(str){
	return str.search(/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/);
}

jQuery(document).ready(function(){
	
	/* Shows "Find spam users" Buttons */
	jQuery('#changeit').after(' <a href="users.php?page=ct_check_users" class="button" style="margin:1px 0 0 0; display: inline-block;">'+ctAdminCommon.logo_small_colored+'&nbsp;'+ctUsersScreen.spambutton_text+'</a>&nbsp;');
	
	/* Shows link to blacklists near every email and IP address */
	if(parseInt(ctUsersScreen.ct_show_check_links))
		jQuery('.column-email a').each(function(){
			var ct_curr_str = jQuery(this).html();
			if(ct_is_email(ct_curr_str) != -1){
				jQuery(this).after('&nbsp;<a href="https://cleantalk.org/blacklists/'+ct_curr_str+'" target="_blank" title="https://cleantalk.org/blacklists/'+ct_curr_str+'" class="ct_link_new_tab"><img src="'+ctUsersScreen.ct_img_src_new_tab+'"></a>');
			}
		});
});