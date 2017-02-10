jQuery(document).ready(function(){
	jQuery('#changeit').after(' <a href="users.php?page=ct_check_users" class="button" style="margin:1px 0 0 0; display: inline-block;">'+ctUsersScreen.spambutton_users_text+'</a>' +
	' <a href="users.php?page=ct_check_users&ct_worked=1" class="button" style="margin:1px 0 0 0; display: inline-block;">'+ctUsersScreen.spambutton_users_text_show+'</a>');
});