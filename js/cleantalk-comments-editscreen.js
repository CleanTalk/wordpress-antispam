function ct_is_email(str){
	return str.search(/.*@.*\..*/);
}
function ct_is_ip(str){
	return str.search(/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/);
}

jQuery(document).ready(function(){
	
	/* Shows "Find spam users" button */
	jQuery('#post-query-submit').after('<a href="edit-comments.php?page=ct_check_spam" class="button" style="margin:1px 0 0 0; display: inline-block;">'+ctAdminCommon.logo_small_colored+'&nbsp;'+ctCommentsScreen.spambutton_text+'</a>&nbsp;');

	/* Shows link to blacklists near every email and IP address */
	if(parseInt(ctCommentsScreen.ct_show_check_links))
		jQuery('.column-author a, .comment-author a').each(function(){
			var ct_curr_str = jQuery(this).html();
			if(ct_is_email(ct_curr_str) != -1 || ct_is_ip(ct_curr_str) != -1){
				jQuery(this).after('&nbsp;<a href="https://cleantalk.org/blacklists/'+ct_curr_str+'" target="_blank" title="https://cleantalk.org/blacklists/'+ct_curr_str+'" class="ct_link_new_tab"><img src="'+ctCommentsScreen.ct_img_src_new_tab+'"></a>');
			}
		});
		
	/* Feedback for comments */
	var ct_comment_id;
	
	// For approved
	jQuery('span.approve').on('click', function(){
		var result = jQuery(this).children('a').attr('href');
		result = result.match(/^comment\.php\?.*c=(\d*).*/);
		ct_comment_id = result[1];
		undo_comment_id = ct_comment_id;
		ct_send_feedback_request(ct_comment_id, 'approve', 0);		
	});
	
	// For unapprove
	jQuery('span.unapprove').on('click', function(){
		var result = jQuery(this).children('a').attr('href');
		result = result.match(/^comment\.php\?.*c=(\d*).*/);
		ct_comment_id = result[1];
		undo_comment_id = ct_comment_id;
		ct_send_feedback_request(ct_comment_id, 'spam', 0);		
	});
	
	// For spammed
	jQuery('span.spam').on('click', function(){
		var result = jQuery(this).children('a').attr('href');
		result = result.match(/^comment\.php\?.*c=(\d*).*/);
		ct_comment_id = result[1];
		undo_comment_id = ct_comment_id;
		ct_send_feedback_request(ct_comment_id, 'spam', 0);
		
		setTimeout(function(){
			jQuery('tr#undo-'+ct_comment_id+' span.unspam a').click(function(){
				var result = jQuery(this).attr('href');
				result = result.match(/^comment\.php\?.*&c=(\d*).*/);
				ct_comment_id = result[1];
				ct_send_feedback_request(ct_comment_id, 'approve', 1);
			});
		}, 202);
		
	});
	
	// For unspammed
	jQuery('span.unspam').on('click', function(){
		var result = jQuery(this).children('a').attr('href');
		result = result.match(/^comment\.php\?.*c=(\d*).*/);
		ct_comment_id = result[1];
		ct_send_feedback_request(ct_comment_id, 'approve', 0);
	});
	
	// For untrashed
	jQuery('span.untrash a').on('click', function(){
		var result = jQuery(this).attr('href');
		result = result.match(/^comment\.php\?.*c=(\d*).*/);
		ct_comment_id = result[1];
		feedback_result = ct_send_feedback_request(ct_comment_id, 'approve', 0);		
	});	
});

// Send feedback to backend
function ct_send_feedback_request(ct_comment_id, ct_comment_status, ct_undo){
	
	var data = {
		'action': 'ct_feedback_comment',
		'security': ctCommentsScreen.ct_ajax_nonce,
		'comment_id': ct_comment_id,
		'comment_status': ct_comment_status
	};
	
	jQuery.ajax({
		type: "POST",
		url: ajaxurl,
		data: data,
		success: function(msg){
			ct_feedback_message_output(ct_comment_id, ct_comment_status, msg, ct_undo);
		},
        error: function(jqXHR, textStatus, errorThrown) {
			console.log(jqXHR);
			console.log(textStatus);
			console.log(errorThrown);
		},
        timeout: 5000
	});
}

// Outputs CT message about feedback
function ct_feedback_message_output(ct_comment_id, ct_comment_status, ct_result, ct_undo){
	if(ct_result == 1){
		if(ct_comment_status == 'approve' && !ct_undo){
			jQuery('tr#comment-'+ct_comment_id)
				.html('')
				.show()
				.append("<td colspan='5'></td>").children('td')
					.css('background', 'rgba(110,240,110,0.7)')
					.append("<div class='spam-undo-inside'>"+ctCommentsScreen.ct_feedback_msg_whitelisted+" "+ctCommentsScreen.ct_feedback_msg+"</div>");						
		}
		if(ct_comment_status == 'spam'){
			if(jQuery('tr').is('#undo-'+ct_comment_id)){
				jQuery('tr#undo-'+ct_comment_id)
					.css('background', 'rgba(240,110,110,0.7)');
				jQuery('tr#undo-'+ct_comment_id+' div.spam-undo-inside')
					.append(" "+ctCommentsScreen.ct_feedback_msg_blacklisted+" "+ctCommentsScreen.ct_feedback_msg);
			}else{
				jQuery('tr#comment-'+ct_comment_id)
				.html('')
				.show()
				.css('background', 'rgba(240,110,110,0.7)')
				.append("<td colspan='5'></td>").children('td')
					.append("<div class='spam-undo-inside'>"+ctCommentsScreen.ct_feedback_msg_blacklisted+" "+ctCommentsScreen.ct_feedback_msg+"</div>");						
			}
		}
	}
	if(ct_result == 0){
		// Error occurred
	}if(ct_result == 'no_hash'){
		// No hash for this comment
	}
}
