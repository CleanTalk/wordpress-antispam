function ct_is_email(str){
	return /^[^\s"<>]+@[^\s"<>]+\.[^\s"<>]+$/.test(str);
}
function ct_is_ipv4(str){
	return /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(str);
}

function ct_is_ipv6(str){
	if( typeof str !== 'string' || str.length < 2 || str.length > 45 ){
		return false;
	}
	if( ! /^[0-9a-fA-F:.]+$/.test(str) || str.indexOf(':') === -1 ){
		return false;
	}
	let double_colon = str.indexOf('::');
	if( double_colon !== -1 && str.indexOf('::', double_colon + 1) !== -1 ){
		return false;
	}
	return str.split(':').length <= 8;
}

function ct_is_ip(str){
	return ct_is_ipv4(str) || ct_is_ipv6(str);
}

function ct_build_blacklist_url(linkTemplate, target){
	return linkTemplate.replace('{TARGET}', encodeURIComponent(target));
}

function ct_append_blacklist_link(anchor, linkTemplate, imgSrc){
	let ct_curr_str = jQuery(anchor).text().trim();
	if( ! ct_is_email(ct_curr_str) && ! ct_is_ip(ct_curr_str) ){
		return;
	}
	let ct_url = ct_build_blacklist_url(linkTemplate, ct_curr_str);
	jQuery(anchor).after('\u00a0').after(
		jQuery('<a>', {
			href: ct_url,
			target: '_blank',
			title: ct_url,
			class: 'ct_link_new_tab',
		}).append(jQuery('<img>', {src: imgSrc}))
	);
}

jQuery(document).ready(function(){
	/* Shows link to blacklists near every email and IP address */
	if(parseInt(ctCommentsScreen.ct_show_check_links))
		jQuery('.column-author a, .comment-author a').each(function(){
			ct_append_blacklist_link(this, ctAdminCommon.links.comments_editscreen, ctCommentsScreen.ct_img_src_new_tab);
		});

	/* Feedback for comments */
	let ct_comment_id;

	// For approved
	jQuery('span.approve').on('click', function(){
		let result = jQuery(this).children('a').attr('href');
		result = result.match(/^comment\.php\?.*c=(\d*).*/);
		ct_comment_id = result[1];
		undo_comment_id = ct_comment_id;
		ct_send_feedback_request(ct_comment_id, 'approve', 0);
	});

	// For unapprove
	jQuery('span.unapprove').on('click', function(){
		let result = jQuery(this).children('a').attr('href');
		result = result.match(/^comment\.php\?.*c=(\d*).*/);
		ct_comment_id = result[1];
		undo_comment_id = ct_comment_id;
		ct_send_feedback_request(ct_comment_id, 'spam', 0);
	});

	// For spammed
	jQuery('span.spam').on('click', function(){
		let result = jQuery(this).children('a').attr('href');
		result = result.match(/^comment\.php\?.*c=(\d*).*/);
		ct_comment_id = result[1];
		undo_comment_id = ct_comment_id;
		ct_send_feedback_request(ct_comment_id, 'spam', 0);
		setTimeout(function(){
			jQuery('tr#undo-'+ct_comment_id+' span.unspam a').click(function(){
				let result = jQuery(this).attr('href');
				result = result.match(/^comment\.php\?.*&c=(\d*).*/);
				ct_comment_id = result[1];
				ct_send_feedback_request(ct_comment_id, 'approve', 1);
			});
		}, 202);

	});

	// For unspammed
	jQuery('span.unspam').on('click', function(){
		let result = jQuery(this).children('a').attr('href');
		result = result.match(/^comment\.php\?.*c=(\d*).*/);
		ct_comment_id = result[1];
		ct_send_feedback_request(ct_comment_id, 'approve', 0);
	});

	// For untrashed
	jQuery('span.untrash a').on('click', function(){
		let result = jQuery(this).attr('href');
		result = result.match(/^comment\.php\?.*c=(\d*).*/);
		ct_comment_id = result[1];
		feedback_result = ct_send_feedback_request(ct_comment_id, 'approve', 0);
	});
});

// Send feedback to backend
function ct_send_feedback_request(ct_comment_id, ct_comment_status, ct_undo) {
	let data = {
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
					.append("<div class='spam-undo-inside'>"+ctCommentsScreen.ct_feedback_msg+"</div>");
		}
		if(ct_comment_status == 'spam'){
			if(jQuery('tr').is('#undo-'+ct_comment_id)){
				jQuery('tr#undo-'+ct_comment_id)
					.css('background', 'rgba(240,110,110,0.7)');
				jQuery('tr#undo-'+ct_comment_id+' div.spam-undo-inside')
					.append(" "+ctCommentsScreen.ct_feedback_msg);
			}else{
				jQuery('tr#comment-'+ct_comment_id)
				.html('')
				.show()
				.css('background', 'rgba(240,110,110,0.7)')
				.append("<td colspan='5'></td>").children('td')
					.append("<div class='spam-undo-inside'>"+ctCommentsScreen.ct_feedback_msg+"</div>");
			}
		}
	}
	if(ct_result == 0){
		// Error occurred
	}if(ct_result == 'no_hash'){
		// No hash for this comment
	}
}
