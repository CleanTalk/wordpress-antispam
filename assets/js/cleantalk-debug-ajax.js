jQuery(document).ready(function(){

	console.log('CT debug');

	// Debug. Console all AJAX requests.
	jQuery(document).ajaxComplete(function(e, xhr, settings, data) {
		console.log("Success:")
		console.log(e);
		console.log(xhr);
		console.log(settings);
		console.log(data);
	});
		
});