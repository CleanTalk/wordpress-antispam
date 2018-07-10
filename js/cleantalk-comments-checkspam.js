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

// Flags
var ct_working = false,
	ct_new_check = true,
	ct_cooling_down_flag = false,
	ct_close_animate = true,
	ct_accurate_check = false,
	ct_pause = false,
	ct_prev_accurate = ctCommentsCheck.ct_prev_accurate,
	ct_prev_from = ctCommentsCheck.ct_prev_from,	
	ct_prev_till = ctCommentsCheck.ct_prev_till;
// Settings
var ct_cool_down_time = 90000,
	ct_requests_counter = 0,
	ct_max_requests = 60;
// Variables
var ct_ajax_nonce = ctCommentsCheck.ct_ajax_nonce,
	ct_comments_total = 0,
	ct_comments_checked = 0,
	ct_comments_spam = 0,
	ct_comments_bad = 0,
	ct_unchecked = 'unset',
	ct_date_from = 0,
	ct_date_till = 0;

function animate_comment(to,id){
	if(ct_close_animate){
		if(to==0.3){
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

function ct_clear_comments(){
	
	var data = {
		'action': 'ajax_clear_comments',
		'security': ct_ajax_nonce
	};
	
	jQuery.ajax({
		type: "POST",
		url: ajaxurl,
		data: data,
		success: function(msg){
			ct_show_info();
			ct_send_comments();
		}
	});
}

//Continues the check after cooldown time
//Called by ct_send_users();
function ct_cooling_down_toggle(){
	ct_cooling_down_flag = false;
	ct_send_comments();
	ct_show_info();
}

function ct_send_comments(){
	
	if(ct_cooling_down_flag == true)
		return;
	
	if(ct_requests_counter >= ct_max_requests){
		setTimeout(ct_cooling_down_toggle, ct_cool_down_time);
		ct_requests_counter = 0;
		ct_cooling_down_flag = true;
		return;
	}else{
		ct_requests_counter++;
	}
	
	var data = {
		'action': 'ajax_check_comments',
		'security': ct_ajax_nonce,
		'new_check': ct_new_check,
		'unchecked': ct_unchecked
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
					var new_href = 'edit-comments.php?page=ct_check_spam';
					if(ct_date_from != 0 && ct_date_till != 0)
						new_href+='&from='+ct_date_from+'&till='+ct_date_till;
					location.href = new_href;
				}else
					ct_send_comments();
			}else{
				ct_new_check = false;
				if(parseInt(msg.end) == 1 || ct_pause === true){
					if(parseInt(msg.end) == 1)
						document.cookie = 'ct_paused_comments_check=0; path=/';
					ct_working=false;
					jQuery('#ct_working_message').hide();
					var new_href = 'edit-comments.php?page=ct_check_spam';
					if(ct_date_from != 0 && ct_date_till != 0)
						new_href+='&from='+ct_date_from+'&till='+ct_date_till;
					location.href = new_href;
				}else if(parseInt(msg.end) == 0){
					ct_comments_checked = +ct_comments_checked + +msg.checked;
					ct_comments_spam = +ct_comments_spam + +msg.spam;
					ct_comments_bad = +ct_comments_bad + +msg.bad;
					ct_unchecked = ct_comments_total - ct_comments_checked - ct_comments_bad;
					var status_string = String(ctCommentsCheck.ct_status_string);
					var status_string = status_string.printf(ct_comments_total, ct_comments_checked, ct_comments_spam, ct_comments_bad);
					if(parseInt(ct_comments_spam) > 0)
						status_string += ctCommentsCheck.ct_status_string_warning;
					jQuery('#ct_checking_status').html(status_string);
					jQuery('#ct_error_message').hide();
					// If DB woks not properly
					if(+ct_comments_total < ct_comments_checked + ct_comments_bad){
						document.cookie = 'ct_comments_start_check=1; path=/';
						document.cookie = 'ct_comments_safe_check=1; path=/';
						location.href = 'edit-comments.php?page=ct_check_spam';
					}
					ct_send_comments();
				}
			}
		},
        error: function(jqXHR, textStatus, errorThrown) {
			jQuery('#ct_error_message').show();
			jQuery('#cleantalk_ajax_error').html(textStatus);
			jQuery('#cleantalk_js_func').html('Check comments');
			setTimeout(ct_send_comments(), 3000);   
        },
        timeout: 25000
	});
}
function ct_show_info(){
	
	if(ct_working){
		
		if(ct_cooling_down_flag == true){
			jQuery('#ct_cooling_notice').html('Waiting for API to cool down. (About a minute)');
			jQuery('#ct_cooling_notice').show();
			return;			
		}else{
			jQuery('#ct_cooling_notice').hide();
		}	
		
		setTimeout(ct_show_info, 3000);
		
		if(!ct_comments_total){
			
			var data = {
				'action': 'ajax_info_comments',
				'security': ct_ajax_nonce
			};
			
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
					jQuery('#ct_checking_status').html(msg.message);
					ct_comments_total   = msg.total;
					ct_comments_spam    = msg.spam;
					ct_comments_checked = msg.checked;
					ct_comments_bad     = msg.bad;
				},
				error: function(jqXHR, textStatus, errorThrown) {
					jQuery('#ct_error_message').show();
					jQuery('#cleantalk_ajax_error').html(textStatus);
					jQuery('#cleantalk_js_func').html('Check comments');
					setTimeout(ct_show_info(), 3000);   
				},
				timeout: 15000
			});
		}
	}
}
function ct_insert_comments(delete_comments = false){
	
	var data = {
		'action': 'ajax_insert_comments',
		'security': ct_ajax_nonce
	};
	
	if(delete_comments)
		data['delete'] = true;
	
	jQuery.ajax({
		type: "POST",
		url: ajaxurl,
		data: data,
		success: function(msg){
			if(delete_comments)
				alert(ctCommentsCheck.ct_comments_deleted + ' ' + msg + ' ' + ctCommentsCheck.ct_comments_added_after);
			else
				alert(ctCommentsCheck.ct_comments_added   + ' ' + msg + ' ' + ctCommentsCheck.ct_comments_added_after);
		}
	});
}
function ct_delete_all(){
	
	var data = {
		'action': 'ajax_delete_all',
		'security': ct_ajax_nonce
	};
	
	jQuery.ajax({
		type: "POST",
		url: ajaxurl,
		data: data,
		success: function(msg){
			if(msg>0){
				jQuery('#cleantalk_comments_left').html(msg);
				ct_delete_all();
			}else{
				location.href='edit-comments.php?page=ct_check_spam';
			}
		},			
		error: function(jqXHR, textStatus, errorThrown) {
			jQuery('#ct_error_message').show();
			jQuery('#cleantalk_ajax_error').html(textStatus);
			jQuery('#cleantalk_js_func').html('Check comments');
			setTimeout(ct_delete_all(), 3000);   
		},
		timeout: 25000
	});
}
function ct_delete_checked(){
	
	ids=Array();
	var cnt=0;
	jQuery('input[id^=cb-select-][id!=cb-select-all-1]').each(function(){
		if(jQuery(this).prop('checked')){
			ids[cnt]=jQuery(this).attr('id').substring(10);
			cnt++;
		}
	});
	var data = {
		'action': 'ajax_delete_checked',
		'security': ct_ajax_nonce,
		'ids':ids
	};
	
	jQuery.ajax({
		type: "POST",
		url: ajaxurl,
		data: data,
		success: function(msg){
			location.href='edit-comments.php?page=ct_check_spam';
		},
		error: function(jqXHR, textStatus, errorThrown) {
			jQuery('#ct_error_message').show();
			jQuery('#cleantalk_ajax_error').html(textStatus);
			jQuery('#cleantalk_js_func').html('Check comments');
			setTimeout(ct_delete_checked(), 3000);   
		},
		timeout: 15000
	});
}

// Function to toggle dependences
function ct_toggle_depended(obj, secondary = false){
	
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

jQuery(document).ready(function(){
	
	// Setting dependences
	// jQuery('#ct_accurate_check')  .data({'depended': '#ct_allow_date_range', 'state': false});
	jQuery('#ct_allow_date_range').data({'depended': '.ct_date', 'state': false});
	
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
	jQuery("#ct_allow_date_range, #ct_accurate_check").on('change', function(){
		ct_toggle_depended(jQuery(this));
	});
			
	var dates = jQuery('#ct_date_range_from, #ct_date_range_till').datepicker(
		{
			dateFormat: 'yy-mm-dd',
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
			}
		}
	);
	
	function ct_start_check(continue_check = false){
		
		if(jQuery('#ct_allow_date_range').is(':checked')){
			
			ct_date_from = jQuery('#ct_date_range_from').val(),
			ct_date_till = jQuery('#ct_date_range_till').val();
						
			if(!(ct_date_from != '' && ct_date_till != '')){
				alert('Please, specify a date range.');
				return;
			}
		}
		
		if(jQuery('#ct_accurate_check').is(':checked')){
			ct_accurate_check = true;
		}
		
		jQuery('.ct_to_hide').hide();
		jQuery('#ct_working_message').show();
		jQuery('#ct_preloader').show();
		jQuery('#ct_pause').show();

		ct_working=true;
		
		if(continue_check){
			ct_show_info();
			ct_send_comments();
		}else
			ct_clear_comments();
		
	}
	
	// Check comments
	jQuery("#ct_check_spam_button").click(function(){
		document.cookie = 'ct_paused_comments_check=0; path=/';
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
		document.cookie = 'ct_paused_comments_check=' + JSON.stringify(ct_check) + '; path=/';
	});
	
	jQuery("#ct_insert_comments").click(function(){
		ct_insert_comments();
	});
	
	jQuery("#ct_delete_comments").click(function(){
		ct_insert_comments(true);
	});
	
	// Delete all spam comments
	jQuery("#ct_delete_all").click(function(){
		
		if (!confirm(ctCommentsCheck.ct_confirm_deletion_all))
			return false;
		
		jQuery('.ct_to_hide').hide();
		jQuery('#ct_checking_status').hide();
		jQuery('#ct_search_info').hide();
		jQuery('#ct_preloader').show();
		jQuery('#ct_deleting_message').show();
		jQuery('#ct_stop_deletion').show();
		jQuery("html, body").animate({ scrollTop: 0 }, "slow");
		ct_delete_all();
	});
	jQuery("#ct_delete_checked").click(function(){
		if (!confirm(ctCommentsCheck.ct_confirm_deletion_checked))
			return false;
		ct_delete_checked();
	});
	
	jQuery("#ct_stop_deletion").click(function(){
		location.href='edit-comments.php?page=ct_check_spam';
	});
	
	jQuery(".cleantalk_delete_button").click(function(){
		id = jQuery(this).attr("data-id");
		ids=Array();
		ids[0]=id;
		var data = {
			'action': 'ajax_delete_checked',
			'security': ct_ajax_nonce,
			'ids':ids
		};
		jQuery.ajax({
			type: "POST",
			url: ajaxurl,
			data: data,
			success: function(msg){
				ct_close_animate=false;
				jQuery("#comment-"+id).hide();
				jQuery("#comment-"+id).remove();
				ct_close_animate=true;
			}
		});
	});
	
	jQuery(".cleantalk_delete_button").click(function(){
		id = jQuery(this).attr("data-id");
		animate_comment(0.3, id);
	});
	
	//Show/hide action on mouse over/out
	jQuery(".cleantalk_comment").mouseover(function(){
		id = jQuery(this).attr("data-id");
		jQuery("#cleantalk_button_set_"+id).show();
	});
	jQuery(".cleantalk_comment").mouseout(function(){
		id = jQuery(this).attr("data-id");
		jQuery("#cleantalk_button_set_"+id).hide();
	});
	
	//Approve button	
	jQuery(".cleantalk_delete_from_list_button").click(function(){
		var ct_id = jQuery(this).attr("data-id");
		
		// Approving
		var data = {
			'action': 'ajax_ct_approve_comment',
			'security': ct_ajax_nonce,
			'id': ct_id
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
			'action': 'ct_feedback_comment',
			'security': ct_ajax_nonce,
			'comment_id': ct_id,
			'comment_status': 'approve'
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
	if(ctCommentsCheck.start === '1'){
		document.cookie = 'ct_comments_start_check=0; expires=' + new Date(0).toUTCString() + '; path=/';
		jQuery('#ct_check_spam_button').click();	
	}
});