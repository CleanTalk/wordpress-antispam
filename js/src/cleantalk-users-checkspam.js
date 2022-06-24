// Printf for JS
String.prototype.printf = function(){
    var formatted = this;
    for( var arg in arguments ) {
		var before_formatted = formatted.substring(0, formatted.indexOf("%s", 0));
		var after_formatted  = formatted.substring(formatted.indexOf("%s", 0)+2, formatted.length);
		formatted = before_formatted + arguments[arg] + after_formatted;
    }
    return formatted;
};

// Set deafult amount to check by request.
document.cookie = "ct_check_users__amount=" + 100 + "; path=/; samesite=lax";

// Flags
var ct_working = false,
	ct_new_check = true,
	ct_cooling_down_flag = false,
	ct_close_animate = true,
	ct_accurate_check = false,
	ct_pause = false,
	ct_prev_accurate = ctUsersCheck.ct_prev_accurate,
	ct_prev_from     = ctUsersCheck.ct_prev_from,	
	ct_prev_till     = ctUsersCheck.ct_prev_till;
// Settings
var ct_cool_down_time = 90000,
	ct_requests_counter = 0,
	ct_max_requests = 60;
// Variables
var ct_ajax_nonce = ctUsersCheck.ct_ajax_nonce,
	ct_users_total = 0,
	ct_users_checked = 0,
	ct_users_spam = 0,
	ct_users_bad = 0,
	ct_unchecked = 'unset',
	ct_date_from = 0,
	ct_date_till = 0;

/* Function: Reuturns cookie with prefix */
function apbct_cookie__get(names, prefixes){
	var cookie = {};
	names = names || null;
	if(typeof names == 'string') names = names.split();
	prefixes = prefixes || ['apbct_', 'ct_'];
	if(prefixes === 'none')          prefixes = null;
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

function apbct_get_cookie( name ){
	var cookie = apbct_cookie__get( name, name );
	if(typeof cookie === 'object' && typeof cookie[name] != 'undefined'){
		return cookie[name];
	}else
		return null;
}

function animate_comment(to,id){
	if(ct_close_animate){
		if(to === 0.3){
			jQuery('#comment-'+id).fadeTo(200,to,function(){
				animate_comment(1,id)
			});
		}else{
			jQuery('#comment-'+id).fadeTo(200,to,function(){
				animate_comment(0.3,id)
			});
		}
	}else{
		ct_close_animate=true;
	}
}

function ct_clear_users(){

	var from = 0, till = 0;
	if(jQuery('#ct_allow_date_range').is(':checked')) {
		from = jQuery('#ct_date_range_from').val();
		till = jQuery('#ct_date_range_till').val();
	}

	var ctSecure = location.protocol === 'https:' ? '; secure' : '';
	document.cookie = 'apbct_check_users_offset' + "=" + 0 + "; path=/; samesite=lax" + ctSecure;

	var data = {
		'action'   : 'ajax_clear_users',
		'security' : ct_ajax_nonce,
		'from'     : from,
		'till'     : till,
		'no_cache': Math.random()
	};

	jQuery.ajax({
		type: "POST",
		url: ajaxurl,
		data: data,
		success: function(msg){
			ct_show_users_info();
			ct_send_users();
		}
	});

}

//Continues the check after cooldown time
//Called by ct_send_users();
function ct_cooling_down_toggle(){
	ct_cooling_down_flag = false;
	ct_send_users();
	ct_show_users_info();
}

function ct_send_users(){
	
	if(ct_cooling_down_flag === true)
		return;
	
	if(ct_requests_counter >= ct_max_requests){
		setTimeout(ct_cooling_down_toggle, ct_cool_down_time);
		ct_requests_counter = 0;
		ct_cooling_down_flag = true;
		return;
	}else{
		ct_requests_counter++;
	}

	var check_amount = apbct_get_cookie('ct_check_users__amount');

	var data = {
		action: 'ajax_check_users',
		security: ct_ajax_nonce,
		new_check: ct_new_check,
		unchecked: ct_unchecked,
		amount: check_amount,
		'no_cache': Math.random(),
		'offset' : Number(getCookie('apbct_check_users_offset'))
	};
	
	if(ct_accurate_check)
		data['accurate_check'] = true;
	
	if(ct_date_from && ct_date_till){
		data['from'] = ct_date_from;
		data['till'] = ct_date_till;
	}
	
	jQuery.ajax({
		type: "POST",
		url: ajaxurl,
		data: data,
		success: function(msg){
			
			msg = jQuery.parseJSON(msg);
			
			if(parseInt(msg.error)){
				ct_working=false;
				if(!confirm(msg.error_message+". Do you want to proceed?")){
					var new_href = 'users.php?page=ct_check_users';
					if(ct_date_from != 0 && ct_date_till != 0)
						new_href+='&from='+ct_date_from+'&till='+ct_date_till;
					location.href = new_href;
				}else
					ct_send_users();
			}else{
				ct_new_check = false;
				if(parseInt(msg.end) == 1 || ct_pause == true){
					if(parseInt(msg.end) == 1)
						document.cookie = 'ct_paused_users_check=0; path=/; samesite=lax';
					ct_working=false;
					jQuery('#ct_working_message').hide();
					var new_href = 'users.php?page=ct_check_users&ct_worked=1';
					if(ct_date_from != 0 && ct_date_till != 0)
						new_href+='&from='+ct_date_from+'&till='+ct_date_till;
					location.href = new_href;
				}else if(parseInt(msg.end) == 0){
					ct_users_checked = parseInt( ct_users_checked ) + parseInt( msg.checked );
					ct_users_spam    = parseInt( ct_users_spam ) + parseInt (msg.spam );
					ct_users_bad     = parseInt( msg.bad );
					ct_unchecked     = ct_users_total - ct_users_checked - ct_users_bad;
					var status_string = String(ctUsersCheck.ct_status_string);
					var status_string = status_string.printf(ct_users_checked, ct_users_spam, ct_users_bad);
					if(parseInt(ct_users_spam) > 0)
						status_string += ctUsersCheck.ct_status_string_warning;
					jQuery('#ct_checking_status').html(status_string);
					jQuery('#ct_error_message').hide();

					var offset = Number(getCookie('apbct_check_users_offset')) + 100;
					var ctSecure = location.protocol === 'https:' ? '; secure' : '';
					document.cookie = 'apbct_check_users_offset' + "=" + offset + "; path=/; samesite=lax" + ctSecure;
					
					ct_send_users();
				}
			}
		},
        error: function(jqXHR, textStatus, errorThrown) {
			if(check_amount > 20){
				check_amount -= 20;
				document.cookie = "ct_check_users__amount=" + check_amount + "; path=/; samesite=lax";
			}
			jQuery('#ct_error_message').show();
			jQuery('#cleantalk_ajax_error').html(textStatus);
			jQuery('#cleantalk_js_func').html('Check users');
			setTimeout(ct_send_users(), 3000);
        },
        timeout: 25000
	});
}
function ct_show_users_info(){
	
	if( ct_working ){
		
		if(ct_cooling_down_flag === true){
			jQuery('#ct_cooling_notice').html('Waiting for API to cool down. (About a minute)').show();
			return;			
		}else{
			jQuery('#ct_cooling_notice').hide();
		}
		
		if( ! ct_users_total ){
			
			var data = {
				'action': 'ajax_info_users',
				'security': ct_ajax_nonce,
				'no_cache': Math.random()
			};
			
			if( ct_date_from && ct_date_till ){
				data['from'] = ct_date_from;
				data['till'] = ct_date_till;
			}
			
			jQuery.ajax({
				type: "POST",
				url: ajaxurl,
				data: data,
				success: function(msg){
					msg = jQuery.parseJSON(msg);
					jQuery('#ct_checking_status').html(msg.message);
					ct_users_spam    = msg.spam;
					ct_users_checked = msg.checked;
					ct_users_bad     = msg.bad;
				},
				error: function (jqXHR, textStatus, errorThrown){
					jQuery('#ct_error_message').show();
					jQuery('#cleantalk_ajax_error').html(textStatus);
					jQuery('#cleantalk_js_func').html('Show users');
					setTimeout(ct_show_users_info(), 3000);
				},
				timeout: 15000
			});
		}
	}
}
// Function to toggle dependences
function ct_toggle_depended(obj, secondary){

    secondary = secondary || null;

	var depended = jQuery(obj.data('depended')),
		state = obj.data('state');
		
	if(!state && !secondary){
		obj.data('state', true);
		depended.removeProp('disabled');
	}else{
		obj.data('state', false);
		depended.prop('disabled', true);
		depended.removeProp('checked');
		if(depended.data('depended'))
			ct_toggle_depended(depended, true);
	}
}

// Main function of checking
function ct_start_check( continue_check ){

	continue_check = continue_check || null;

	if(jQuery('#ct_allow_date_range').is(':checked')){

		ct_date_from = jQuery('#ct_date_range_from').val();
		ct_date_till = jQuery('#ct_date_range_till').val();

		if(!(ct_date_from !== '' && ct_date_till !== '')){
			alert(ctUsersCheck.ct_specify_date_range);
			return;
		}
	}

	if(jQuery('#ct_accurate_check').is(':checked')){
		ct_accurate_check = true;
	}

	//
	if (
		jQuery('#ct_accurate_check').is(':checked') &&
		! jQuery('#ct_allow_date_range').is(':checked')
	) {
		alert(ctUsersCheck.ct_select_date_range);
		return;
	}

	jQuery('.ct_to_hide').hide();
	jQuery('#ct_working_message').show();
	jQuery('#ct_preloader').show();
	jQuery('#ct_pause').show();

	ct_working = true;

	if( continue_check ){
		ct_show_users_info();
		ct_send_users();
	} else {
		ct_clear_users();
	}

}

function ct_delete_all_users( e ){

	var data = {
		'action': 'ajax_delete_all_users',
		'security': ct_ajax_nonce,
		'no_cache': Math.random()
	};

	jQuery('.' + e.target.id).addClass('disabled');
	jQuery('.spinner').css('visibility', 'visible');
	jQuery.ajax({
		type: "POST",
		url: ajaxurl,
		data: data,
		success: function( msg ){
			if( msg > 0 ){
				jQuery('#cleantalk_users_left').html(msg);
				ct_delete_all_users( e, data );
			}else{
				jQuery('.' + e.target.id).removeClass('disabled');
				jQuery('.spinner').css('visibility', 'hidden');
				location.href='users.php?page=ct_check_users';
			}
		},
		error: function(jqXHR, textStatus, errorThrown) {
			jQuery('#ct_error_message').show();
			jQuery('#cleantalk_ajax_error').html(textStatus);
			jQuery('#cleantalk_js_func').html('All users deleteion');
			setTimeout(ct_delete_all_users( e ), 3000);
		},
		timeout: 25000
	});
}

jQuery(document).ready(function(){

	// Setting dependences
	
	// Prev check parameters
	if(ct_prev_accurate){
		jQuery("#ct_accurate_check").prop('checked', true);
	}
	if(ct_prev_from){
		jQuery("#ct_allow_date_range").prop('checked', true).data('state', true);
		jQuery("#ct_date_range_from").removeProp('disabled').val(ct_prev_from);
		jQuery("#ct_date_range_till").removeProp('disabled').val(ct_prev_till);
	}
	
	// Toggle dependences
	jQuery("#ct_allow_date_range").on('change', function(){
		document.cookie = 'ct_users_dates_from='+ jQuery('#ct_date_range_from').val() +'; path=/; samesite=lax';
		document.cookie = 'ct_users_dates_till='+ jQuery('#ct_date_range_till').val() +'; path=/; samesite=lax';
		if( this.checked ) {
			document.cookie = 'ct_users_dates_allowed=1; path=/; samesite=lax';
			jQuery('.ct_date').prop('checked', true).attr('disabled',false);
		} else {
			document.cookie = 'ct_users_dates_allowed=0; path=/; samesite=lax';
			jQuery('.ct_date').prop('disabled', true).attr('disabled',true);
		}
	});

	jQuery.datepicker.setDefaults(jQuery.datepicker.regional['en']);
	var dates = jQuery('#ct_date_range_from, #ct_date_range_till').datepicker(
		{
			dateFormat: 'M d yy',
			maxDate:"+0D",
			changeMonth:true,
			changeYear:true,
			showAnim: 'slideDown',
			onSelect: function(selectedDate){
			var option = this.id == "ct_date_range_from" ? "minDate" : "maxDate",
				instance = jQuery( this ).data( "datepicker" ),
				date = jQuery.datepicker.parseDate(
					instance.settings.dateFormat || jQuery.datepicker._defaults.dateFormat,
					selectedDate, instance.settings);
				dates.not(this).datepicker("option", option, date);
				document.cookie = 'ct_users_dates_from='+ jQuery('#ct_date_range_from').val() +'; path=/; samesite=lax';
				document.cookie = 'ct_users_dates_till='+ jQuery('#ct_date_range_till').val() +'; path=/; samesite=lax';
			}
		}
	);
	
	// Check users
	jQuery("#ct_check_spam_button").click(function(){
		document.cookie = 'ct_paused_users_check=0; path=/; samesite=lax';

		//


		ct_start_check(false);
	});
	jQuery("#ct_proceed_check_button").click(function(){
		ct_start_check(true);
	});
	
	// Pause the check
	jQuery('#ct_pause').on('click', function(){
		ct_pause = true;
		var ct_check = {
			'accurate': ct_accurate_check,
			'from'    : ct_date_from,
			'till'    : ct_date_till
		};
		document.cookie = 'ct_paused_users_check=' + JSON.stringify(ct_check) + '; path=/; samesite=lax';
	});
		
	//Approve button
	jQuery(".cleantalk_delete_from_list_button").click(function(){
		ct_id = jQuery(this).attr("data-id");
		
		// Approving
		var data = {
			'action': 'ajax_ct_approve_user',
			'security': ct_ajax_nonce,
			'id': ct_id,
			'no_cache': Math.random()
		};
		jQuery.ajax({
			type: "POST",
			url: ajaxurl,
			data: data,
			success: function(msg){
				jQuery("#comment-"+ct_id).fadeOut('slow', function(){
					jQuery("#comment-"+ct_id).remove();
				});
			},
		});
		
		// Positive feedback
		var data = {
			'action': 'ct_feedback_user',
			'security': ct_ajax_nonce,
			'user_id': ct_id,
			'status': 'approve',
			'no_cache': Math.random()
		};
		jQuery.ajax({
			type: "POST",
			url: ajaxurl,
			data: data,
			success: function(msg){
				if(msg == 1){
					// Success
				}
				if(msg == 0){
					// Error occurred
				}
				if(msg == 'no_hash'){
					// No hash
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				
			},
			timeout: 5000
		});
		
	});
	
	// Request to Download CSV file.
	jQuery(".ct_get_csv_file").click(function( e ){
		var data = {
			'action': 'ajax_ct_get_csv_file',
			'security': ct_ajax_nonce,
			'filename': ctUsersCheck.ct_csv_filename,
			'no_cache': Math.random()
		};
		jQuery('.' + e.target.id).addClass('disabled');
		jQuery('.spinner').css('visibility', 'visible');
		jQuery.ajax({
			type: "POST",
			url: ajaxurl,
			data: data,
			success: function(msg){
				if( parseInt(msg) === 0 ) {
					alert(ctUsersCheck.ct_bad_csv);
				} else {
					var url = URL.createObjectURL(new Blob([msg]));

					var dummy = document.createElement('a');
					dummy.href = url;
					dummy.download = ctUsersCheck.ct_csv_filename + '.csv';

					document.body.appendChild(dummy);
					dummy.click();
				}
				jQuery('.' + e.target.id).removeClass('disabled');
				jQuery('.spinner').css('visibility', 'hidden');
			}
		});
	});

	// Delete inserted users
	jQuery(".ct_insert_users").click(function( e ){
		ct_insert_users();
	});

	// Insert users
	jQuery(".ct_insert_users__delete").click(function( e ){
		ct_insert_users( true );
	});

	// Delete all spam users
	jQuery(".ct_delete_all_users").click(function( e ){

		if ( ! confirm( ctUsersCheck.ct_confirm_deletion_all ) )
			return false;

		ct_delete_all_users( e );

	});

	function ct_insert_users(delete_accounts){

		delete_accounts = delete_accounts || null;

		var data = {
			'action': 'ajax_insert_users',
			'security': ct_ajax_nonce,
			'no_cache': Math.random()
		};

		if(delete_accounts)
			data['delete'] = true;

		jQuery.ajax({
			type: "POST",
			url: ajaxurl,
			data: data,
			success: function(msg){
				if(delete_accounts)
					alert('Deleted ' + msg + ' users');
				else
					alert('Inserted ' + msg + ' users');
			}
		});
	}


	/**
	 * Checked ct_accurate_check
	 */
	jQuery('#ct_accurate_check').change(function () {
		if(this.checked) {
			jQuery('#ct_allow_date_range').prop('checked', true);
			jQuery('.ct_date').prop('checked', true).attr('disabled',false);
		}
	});
});

/**
 * Get cookie by name
 * @param name
 * @returns {string|undefined}
 */
function getCookie(name) {
	let matches = document.cookie.match(new RegExp(
		"(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
	));
	return matches ? decodeURIComponent(matches[1]) : undefined;
}