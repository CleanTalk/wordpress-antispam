jQuery(document).ready(function(){
	
	jQuery('.ct-trial-notice').on('click', 'button', function(){
		var ct_date = new Date(new Date().getTime() + 86400 * 1000);
		document.cookie = "ct_trial_banner_closed=1; path=/; expires=" + ct_date.toUTCString();
	});
	
});