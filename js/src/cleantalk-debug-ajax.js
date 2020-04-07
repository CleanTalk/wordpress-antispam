jQuery(document).ready(function(){

	console.log('CT debug');

	// Debug. Console all AJAX requests.
	jQuery(document).ajaxComplete(function(event, xhr, settings, data) {
		console.log("Success!")
		console.log('Event:\n');
		console.log(event);
		console.log('Response:\n');
		console.log(xhr);
		console.log('Request settings:\n');
		console.log(settings);
		console.log('Data:\n');
		console.log(data);
	});
		
});