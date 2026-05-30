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

jQuery(document).ready(function() {
	if(parseInt(ctUsersScreen.ct_show_check_links)) {

		/* Shows link to blacklists near every email and IP address */
		jQuery('.column-email a').each(function() {
			ct_append_blacklist_link(this, ctAdminCommon.links.users_editscreen, ctUsersScreen.ct_img_src_new_tab);
		});

		/* Show checked ico near avatar */
		jQuery('.username.column-username').each(function() {
			let apbct_checking_el = jQuery(this).siblings('.apbct_status').children('span');
			let apbct_checking_status = apbct_checking_el.attr('id');
			let apbct_checking_status_text = apbct_checking_el.text();

			let apbct_add_text_element = jQuery('<span>', {
				text : apbct_checking_status_text
			});
			let apbct_add_ico_ok = jQuery('<i>', {
				class :  'apbct-icon-ok'
			});
			let apbct_add_ico_cancel = jQuery('<i>', {
				class :  'apbct-icon-cancel',
				css : {
					color : 'red'
				}
			});

			if( apbct_checking_status ==='apbct_not_checked' ) {
				jQuery(this).children('.row-actions').before(apbct_add_ico_ok).before(apbct_add_text_element);
			}
			if( apbct_checking_status ==='apbct_checked_not_spam' ) {
				apbct_add_ico_ok.attr('style', 'color:green;');
				jQuery(this).children('.row-actions').before(apbct_add_ico_ok).before(apbct_add_text_element);
			}
			if( apbct_checking_status ==='apbct_checked_spam' ) {
				jQuery(this).children('.row-actions').before(apbct_add_ico_cancel).before(apbct_add_text_element);
			}
		});
	}
});