// Fingerprint
var apbct_fingerprint = new Fingerprint({canvas: true, ie_activex: true, hasher: apbct_md5}).get();

/* Function: Reuturns cookie with prefix */
function apbct_cookie__get(names, prefixes){
	var cookie = {};
	names = names || null;
	if(typeof names == 'string') names = names.split();
	prefixes = prefixes || ['apbct_', 'ct_'];
	if(prefixes == 'none')          prefixes = null;
	if(typeof prefixes == 'string') prefixes = prefixes.split();
	document.cookie.split(';').forEach(function(item, i, arr){
		var curr = item.trim().split('=');
		// Detect by full cookie name
		if(names){
			names.forEach(function(name, i, all){
				if(curr[0] === name)
					cookie[curr[0]] = (curr[1]);
			});
		}
		// Detect by name prefix
		if(prefixes){
			prefixes.forEach(function(prefix, i, all){
				if(curr[0].indexOf(prefix) === 0)
					cookie[curr[0]] = (curr[1]);
			});
		}
	});
	return cookie;
}

/* Function: Deletes cookie with prefix */
function apbct_cookie__delete(names, prefixes){
	var date = new Date(0);
	names = names || null;
	if(typeof names == 'string') names = names.split();
	prefixes = prefixes || ['apbct_', 'ct_'];
	if(prefixes == 'none')          prefixes = null;
	if(typeof prefixes == 'string') prefixes = prefixes.split();	
	document.cookie.split(';').forEach(function(item, i, arr){
		var curr = item.trim().split('=');
		// Detect by full cookie name
		if(names){
			names.forEach(function(name, i, all){
				if(curr[0] === name)
					document.cookie = curr[0] + "=; path=/; expires=" + date.toUTCString();
			});
		}
		// Detect by name prefix
		if(prefixes){
			prefixes.forEach(function(prefix, i, all){
				if(curr[0].indexOf(prefix) === 0)
					document.cookie = curr[0] + "=; path=/; expires=" + date.toUTCString();
			});
		}
	});
}

jQuery(document).ready(function(){
	
	 jQuery.ajax({
		 type: "POST",
		 url: apbctPublicAlt.ajax_url,
		 data: {
			 apbct_action: 'get_sessions',
			 apbct_secret: apbctPublicAlt.nonce,
			 session_id: apbct_fingerprint,
		 },
		 async: true,
		 success: function(msg){
			 console.log('success');
			 msg = JSON.parse(msg);
			
			 if(msg.result){
				 console.log(msg);
				 for(cookie in msg.cookies){
					 console.log(cookie);
//					 console.log(msg.cookies[cookie]);
					 document.cookie = cookie + "=" + msg.cookies[cookie] + "; path=/;";
				 };
			 }else{
				 console.log(msg);
				 console.log('APBCT SESSIONS GET ERROR');
			 }
		 },
		 error: function(err){
			 console.log('err');
			 console.log(err);
		 }
	 });
	
	window.onunload = function(){
		
		// Getting ct_ and apbct_ cookies
		 cookies = apbct_cookie__get();
		
		console.log('leave');
		
		 jQuery.ajax({
			type: "POST",
			url: apbctPublicAlt.ajax_url,
			data: {
				apbct_action: 'set_sessions',
				apbct_secret: apbctPublicAlt.nonce,
				session_id: apbct_fingerprint,
				data: cookies,
			},
			async: false,
			success: function(msg){
				msg = JSON.parse(msg);
				if(msg.result){
					console.log('success');
					console.log(msg);
					// Deleting ct_ and apbct_ cookies on success
					apbct_cookie__delete();
					console.log('cookie DELETED');
				}else{
					console.log('APBCT SESSIONS GET ERROR');
					console.log(msg);
				}
			},
			error: function(err){
				console.log('err');
				console.log(err);
			}
		}); 
	}
});