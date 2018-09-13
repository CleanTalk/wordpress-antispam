jQuery(document).ready(function(){
	
	// Auto update banner close handler
	jQuery('.apbct_update_notice').on('click', 'button', function(){
		var ct_date = new Date(new Date().getTime() + 1000 * 86400 * 30 );
		document.cookie = "apbct_update_banner_closed=1; path=/; expires=" + ct_date.toUTCString();
	});
	
});