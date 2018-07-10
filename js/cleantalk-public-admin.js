jQuery(document).ready(function(){
	
	// Set background-color similar to parents
	var ct_current_wrapper = jQuery('.ct_comment_info').parent();
	
	for(var i=0; i < 30; i++){		
		if(ct_current_wrapper.css('background-color') == 'rgba(0, 0, 0, 0)' || i == 29)
			ct_current_wrapper = ct_current_wrapper.parent();
		else{
			jQuery('.ct_comment_info').css('background', ct_current_wrapper.css('background-color'));
			break;
		}
	}
	
	// Set same float style with article
	var ct_post_float = jQuery('.ct_comment_info').prev().last().css('float');
	jQuery('.ct_comment_info').css('float', ct_post_float);
	
	var ct_posts = jQuery('.ct_comment_info');
	
	jQuery(ct_posts).each(function(indx, elem){
		curr_elem = jQuery(elem);
		curr_elem.prev().before(curr_elem);
	});
	
	// Handler for buttons
	jQuery('.ct_this_is').on('click', function(){
		
		var ct_current_button = jQuery(this),
			ct_feedback_wrap = jQuery(this).siblings('.ct_feedback_wrap'),
			ct_feedback_msg = jQuery('.ct_feedback_msg'),
			ct_comment_status;
			
		if(ct_current_button.hasClass('ct_this_is_spam'))
			ct_comment_status = 'spam';
		else
			ct_comment_status = 'approve';
		
		var data = {
			'action': 'ct_feedback_comment',
			'security': ctPublicAdmin.ct_ajax_nonce,
			'comment_id': ct_current_button.attr('commentid'),
			'comment_status': ct_comment_status,
			'change_status': 1
		};
		
		jQuery.ajax({
			type: "POST",
			url: ctPublicAdmin.ajaxurl,
			data: data,
			success: function(msg){
				ct_current_button.hide();
				ct_current_button.siblings('span.ct_this_is').show();
				
				jQuery('.ct_feedback_result').hide();
				if(ct_comment_status == 'approve')
					jQuery('.ct_feedback_result_not_spam').show();
				else
					jQuery('.ct_feedback_result_spam').show();
				
				if(msg == 1){
					ct_feedback_msg.addClass('ct_feedback_success');
					ct_feedback_msg.html(ctPublicAdmin.ct_feedback_msg);
				}else if(msg == 0){
					// Error occurred
					ct_feedback_msg.addClass('ct_feedback_error');
					ct_feedback_msg.html(ctPublicAdmin.ct_feedback_error);
				}else if(msg == 'no_hash'){
					// No hash for this comment
					ct_feedback_msg.addClass('ct_feedback_no_hash');
					ct_feedback_msg.html(ctPublicAdmin.ct_feedback_no_hash);
				}
				// Hidding feedback message for every message type
				ct_feedback_wrap.show();
				ct_feedback_wrap.css('display', 'inline-block');
				
				var ct_timeout_id = ct_feedback_wrap.data('interval_id');
				clearInterval(ct_timeout_id);
				ct_timeout_id = setTimeout(function(){
					ct_feedback_wrap.fadeOut(1000);
				}, 5000);
				ct_feedback_wrap.data('interval_id', ct_timeout_id);
			},
			error: function(jqXHR, textStatus, errorThrown) {
				console.log(jqXHR);
				console.log(textStatus);
				console.log(errorThrown);
			},
			timeout: 5000
		});
	});
	
});