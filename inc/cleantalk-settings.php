<?php

/**
 * Admin action 'admin_menu' - Add the admin options page
 */
function apbct_settings__add_page() {
	
	global $apbct;
	
	// Adding settings page
	if(is_network_admin())
		add_submenu_page("settings.php", __('CleanTalk settings', 'cleantalk'), APBCT_NAME, 'manage_options', 'cleantalk', 'apbct_settings_page');
	else
		add_options_page(__('CleanTalk settings', 'cleantalk'), APBCT_NAME, 'manage_options', 'cleantalk', 'apbct_settings_page');
	
	register_setting('cleantalk_settings', 'cleantalk_settings', 'apbct_settings__validate');
		
	add_settings_section('cleantalk_section_settings_main',  '',                                     'apbct_section__settings_main',  'cleantalk');
	add_settings_section('cleantalk_section_debug',          '',                                     'apbct_section__debug',          'cleantalk');
	add_settings_section('cleantalk_section_state',          '',                                     'apbct_section__settings_state', 'cleantalk');
	add_settings_section('cleantalk_settings_banner',        '<hr>',                                 '',                               'cleantalk');
	
	// DEBUG
	add_settings_field('apbct_debug_field', '', 'apbct_settings__field__debug', 'cleantalk', 'cleantalk_section_debug');
	
	// STATE
	add_settings_field('apbct_state_field', '', 'apbct_settings__field__state', 'cleantalk', 'cleantalk_section_state');
	
	// KEY
	add_settings_field('apbct_action_butons', '', 'apbct_settings__field__action_buttons', 'cleantalk', 'cleantalk_section_settings_main');
	add_settings_field('cleantalk_api_key',   '', 'apbct_settings__field__api_key',        'cleantalk', 'cleantalk_section_settings_main');
	if(apbct_api_key__is_correct())
		add_settings_field('cleantalk_connection_reports', '', 'apbct_settings__field__connection_reports', 'cleantalk', 'cleantalk_section_settings_main');
		
	$field_default_params = array(
		'callback'    => 'apbct_settings__field__draw',
		'type'        => 'radio',
		'def_class'   => 'apbct_settings-field_wrapper',
		'class'       => '',
		'parent'      => '',
		'childrens'   => '',
		'title'       => 'Default title',
		'description' => 'Default description',
	);
	
	$apbct->settings_fields_in_groups = array(
		
		// Different
		'different' => array(
			'title'          => '',	
			'default_params' => array(),
			'description'    => '',
			'html_before'    => '<hr>',
			'html_after'     => '',
			'fields'         => array(
				'show_link' => array(
					'type'        => 'checkbox',
					'title'       => __('Tell others about CleanTalk', 'cleantalk'),
					'description' => __("Checking this box places a small link under the comment form that lets others know what anti-spam tool protects your site.", 'cleantalk'),
				),
				'spam_firewall' => array(
					'type'        => 'checkbox',
					'title'       => __('SpamFireWall', 'cleantalk'),
					'description' => __("This option allows to filter spam bots before they access website. Also reduces CPU usage on hosting server and accelerates pages load time.", 'cleantalk'),
				),
			),
		),
		
		// Forms protection
		'forms_protection' => array(
			'title'          => __('Forms to protect', 'cleantalk'),
			'default_params' => array(),
			'description'    => '',
			'html_before'    => '<hr><br><span id="ct_adv_showhide">'
					.'<a href="#" style="color: gray;" onclick="event.preventDefault(); apbct_show_hide_elem(\'#apbct_settings__davanced_settings\');">'
						.__('Advanced settings', 'cleantalk')
					.'</a>'
				.'</span>'
				.'<div id="apbct_settings__davanced_settings" style="display: none;">',
			'html_after'     => '',
			'fields'         => array(
				'registrations_test' => array(
					'title'       => __('Registration Forms', 'cleantalk'),
					'description' => __('WordPress, BuddyPress, bbPress, S2Member, WooCommerce.', 'cleantalk'),
				),
				'comments_test' => array(
					'title'       => __('Comments form', 'cleantalk'),
					'description' => __('WordPress, JetPack, WooCommerce.', 'cleantalk'),
				),
				'contact_forms_test' => array(
					'title'       => __('Contact forms', 'cleantalk'),
					'description' => __('Contact Form 7, Formidable forms, JetPack, Fast Secure Contact Form, WordPress Landing Pages, Gravity Forms.', 'cleantalk'),
				),
				'general_contact_forms_test' => array(
					'title'       => __('Custom contact forms', 'cleantalk'),
					'description' => __('Anti spam test for any WordPress themes or contacts forms.', 'cleantalk'),
				),
				'wc_checkout_test' => array(
					'title'       => __('WooCommerce checkout form', 'cleantalk'),
					'description' => __('Anti spam test for WooCommerce checkout form.', 'cleantalk'),
				),
				'check_external' => array(
					'title'       => __('Protect external forms', 'cleantalk'),
					'description' => __('Turn this option on to protect forms on your WordPress that send data to third-part servers (like MailChimp).', 'cleantalk'),
				),
				'check_internal' => array(
					'title'       => __('Protect internal forms', 'cleantalk'),
					'description' => __('This option will enable protection for custom (hand-made) AJAX forms with PHP scripts handlers on your WordPress.', 'cleantalk'),
				),
			),
		),
		
		// Comments and Messages
		'comments_and_messages' => array(
			'title'          => __('Comments and Messages', 'cleantalk'),
			'fields'         => array(
				'bp_private_messages' => array(
					'title'       => __('BuddyPress Private Messages', 'cleantalk'),
					'description' => __('Check buddyPress private messages.', 'cleantalk'),
				),
				'check_comments_number' => array(
					'title'       => __("Don't check trusted user's comments", 'cleantalk'),
					'description' => sprintf(__("Dont't check comments for users with above % comments.", 'cleantalk'), defined('CLEANTALK_CHECK_COMMENTS_NUMBER') ? CLEANTALK_CHECK_COMMENTS_NUMBER : 3),
				),
				'remove_old_spam' => array(
					'title'       => __('Automatically delete spam comments', 'cleantalk'),
					'description' => sprintf(__('Delete spam comments older than %d days.', 'cleantalk'),  $apbct->settings['spam_store_days']),
				),
				'remove_comments_links' => array(
					'title'       => __('Remove links from approved comments', 'cleantalk'),
					'description' => __('Remove links from approved comments. Replace it with "[Link deleted]"', 'cleantalk'),
				),
				'show_check_links' => array(
					'title'       => __('Show links to check Emails, IPs for spam.', 'cleantalk'),
					'description' => __('Shows little icon near IP addresses and Emails allowing you to check it via CleanTalk\'s database. Also allowing you to manage comments from the public post\'s page.', 'cleantalk'),
				),
			),
		),
		
		// Data Processing
		'data_processing' => array(
			'title'          => __('Data Processing', 'cleantalk'),
			'fields'         => array(
				'protect_logged_in' => array(
					'title'       => __("Protect logged in Users", 'cleantalk'),
					'description' => __('Turn this option on to check for spam any submissions (comments, contact forms and etc.) from registered Users.', 'cleantalk'),
				),
				'use_ajax' => array(
					'title'       => __('Use AJAX for JavaScript check', 'cleantalk'),
					'description' => __('Options helps protect WordPress against spam with any caching plugins. Turn this option on to avoid issues with caching plugins.', 'cleantalk')."<strong> ".__('Attention! Incompatible with AMP plugins!', 'cleantalk')."</strong>",
				),
				'general_postdata_test' => array(
					'title'       => __('Check all post data', 'cleantalk'),
					'description' => __('Check all POST submissions from website visitors. Enable this option if you have spam misses on website or you don`t have records about missed spam here:', 'cleantalk') . '&nbsp;' . '<a href="https://cleantalk.org/my/?user_token='.$apbct->user_token.'&utm_source=wp-backend&utm_medium=admin-bar&cp_mode=antispam" target="_blank">' . __('CleanTalk dashboard', 'cleantalk') . '</a>.<br />' . __('СAUTION! Option can catch POST requests in WordPress backend', 'cleantalk'),
				),
				'set_cookies' => array(
					'title'       => __("Set cookies", 'cleantalk'),
					'description' => __('Turn this option off to deny plugin generates any cookies on website front-end. This option is helpful if you use Varnish. But most of contact forms will not be protected by CleanTalk if the option is turned off! <b>Warning: We strongly recommend you to enable this otherwise it could cause false positives spam detection.</b>', 'cleantalk'),
				),
				'ssl_on' => array(
					'title'       => __("Use SSL", 'cleantalk'),
					'description' => __('Turn this option on to use encrypted (SSL) connection with CleanTalk servers.', 'cleantalk'),
				),
			),
		),
		
		// Admin bar
		'admin_bar' => array(
			'title'          => __('Admin bar', 'cleantalk'),
			'default_params' => array(),
			'description'    => '',
			'html_before'    => '',
			'html_after'     => '',
			'fields'         => array(
				'show_adminbar' => array(
					'title'       => __('Show statistics in admin bar', 'cleantalk'),
					'description' => __('Show/hide CleanTalk icon in top level menu in WordPress backend. The number of submissions is being counted for past 24 hours.', 'cleantalk'),
					'childrens' => array('all_time_counter','daily_counter','sfw_counter'),
				),
				'all_time_counter' => array(
					'title'       => __('Show All-time counter', 'cleantalk'),
					'description' => __('Display all-time requests counter in the admin bar. Counter displays number of requests since plugin installation.', 'cleantalk'),
					'parent' => 'show_adminbar',
					'class' => 'apbct_settings-field_wrapper--sub',
				),
				'daily_counter' => array(
					'title'       => __('Show 24 hours counter', 'cleantalk'),
					'description' => __('Display daily requests counter in the admin bar. Counter displays number of requests of the past 24 hours.', 'cleantalk'),
					'parent' => 'show_adminbar',
					'class' => 'apbct_settings-field_wrapper--sub',
				),
				'sfw_counter' => array(
					'title'       => __('SpamFireWall counter', 'cleantalk'),
					'description' => __('Display SpamFireWall requests in the admin bar. Counter displays number of requests since plugin installation.', 'cleantalk'),
					'parent' => 'show_adminbar',
					'class' => 'apbct_settings-field_wrapper--sub',
				),
			),
		),

		// Misc
		'misc' => array(
			'html_after'     => '</div><br>',
			'fields'         => array(
				'collect_details' => array(
					'type'        => 'checkbox',
					'title'       => __('Collect details about browsers', 'cleantalk'),
					'description' => __("Checking this box you allow plugin store information about screen size and browser plugins of website visitors. The option in a beta state.", 'cleantalk'),
				),
				'send_connection_reports' => array(
					'type'        => 'checkbox',
					'title'       => __('Send connection reports', 'cleantalk'),
					'description' => __("Checking this box you allow plugin to send the information about your connection. The option in a beta state.", 'cleantalk'),
				),
				'async_js' => array(
					'type'        => 'checkbox',
					'title'       => __('Async JavaScript loading', 'cleantalk'),
					'description' => __('Use async loading for CleanTalk\'s scripts. Warning: This could reduce filtration quality.', 'cleantalk'),
				),
			),
		),
	);
	
	foreach($apbct->settings_fields_in_groups as $group_name => $group){
		
		add_settings_section('apbct_section__'.$group_name, '', 'apbct_section__'.$group_name, 'cleantalk');
		
		foreach($group['fields'] as $field_name => $field){
			
			$params = !empty($group['default_params']) 
				? array_merge($group['default_params'], $field)
				: array_merge($field_default_params, $field);
			
			$params['name'] = $field_name;
			
			add_settings_field(
				'apbct_field__'.$field_name,
				'',
				$params['callback'],
				'cleantalk',
				'apbct_section__'.$group_name,
				$params
			);
			
		}
		
	}
	
	// GDPR
	// add_settings_field('cleantalk_collect_details', __('Collect details about browsers', 'cleantalk'), 'ct_input_collect_details', 'cleantalk', 'apbct_secton_antispam');
	// add_settings_field('cleantalk_connection_reports', __('Send connection reports', 'cleantalk'), 'ct_send_connection_reports', 'cleantalk', 'apbct_secton_antispam');
}

/**
 * Admin callback function - Displays plugin options page
 */
function apbct_settings_page() {
	
	global $apbct;		
		
		// Title
		echo '<h2 class="apbct_settings-title">'.__($apbct->plugin_name, 'cleantalk').'</h2>';
		// Subtitle for IP license
		if($apbct->moderate_ip)
			echo '<h4 class="apbct_settings-subtitle gray">'. __('Hosting AntiSpam', 'cleantalk').'</h4>';
		
		apbct_settings__error__output();
		
		// Top info
		echo '<div style="float: right; padding: 15px 15px 0 15px; font-size: 13px;">';

			echo __('CleanTalk\'s tech support:', 'cleantalk')
				.'&nbsp;'
				.'<a target="_blank" href="https://wordpress.org/support/plugin/cleantalk-spam-protect">Wordpress.org</a>.'
			// .' <a href="https://community.cleantalk.org/viewforum.php?f=25" target="_blank">'.__("Tech forum", 'cleantalk').'</a>'
			// .($user_token ? ", <a href='https://cleantalk.org/my/support?user_token=$user_token&cp_mode=antispam' target='_blank'>".__("Service support ", 'cleantalk').'</a>' : '').
				.'<br>';
			echo __('Plugin Homepage at', 'cleantalk').' <a href="http://cleantalk.org" target="_blank">cleantalk.org</a>.<br/>';
			echo '<span id="apbct_gdpr_open_modal" style="text-decoration: underline;">'.__('GDPR compliance', 'cleantalk').'</span><br/>';
			echo __('Use s@cleantalk.org to test plugin in any WordPress form.', 'cleantalk').'<br>';
			echo __('CleanTalk is registered Trademark. All rights reserved.', 'cleantalk').'<br/>';
			echo '<b style="display: inline-block; margin-top: 10px;">'.sprintf(__('Do you like CleanTalk? %sPost your feedback here%s.', 'cleantalk'), '<a href="https://wordpress.org/support/plugin/cleantalk-spam-protect/reviews/#new-post" target="_blank">', '</a>').'</b><br />';
			apbct_admin__badge__get_premium();
			echo '<div id="gdpr_dialog" style="display: none; padding: 7px;">';
				apbct_gdpr__show_text();
			echo '</div>';
		echo '</div>';
		
		echo '<form action="options.php" method="post">';
			
			// If it's network admin dashboard
			if(is_network_admin()){
				if(defined('CLEANTALK_ACCESS_KEY')){
					print '<br />'
					.sprintf(__('Your CleanTalk access key is: <b>%s</b>.', 'cleantalk'), CLEANTALK_ACCESS_KEY)
						.'<br />'
						.'You can change it in your wp-config.php file.'
						.'<br />';
				}else{
					print '<br />'
					.__('To set up global CleanTalk access key for all websites, define constant in your wp-config.php file before defining database constants: <br/><pre>define("CLEANTALK_ACCESS_KEY", "place your key here");</pre>', 'cleantalk');
				}
				return;
			}
			
			// Output spam count
			if($apbct->key_is_ok && apbct_api_key__is_correct()){
				if($apbct->spam_count > 0){
					echo '<div class="apbct_settings-subtitle" style="top: 0; margin-bottom: 10px; width: 200px;">'
						.'<br>'
						.'<span>'
							.sprintf(
								__( 'CleanTalk  has blocked <b>%s</b> spam.', 'cleantalk' ),
								number_format($apbct->spam_count, 0, ',', ' ')
							)
						.'</span>'
						.'<br>'
						.'<br>'
					.'</div>';
				}
				// CP button
				echo '<a class="cleantalk_manual_link" target="__blank" href="https://cleantalk.org/my?user_token='.$apbct->user_token.'&cp_mode=antispam">'.__('Click here to get anti-spam statistics', 'cleantalk').'</a>';
				echo '&nbsp;&nbsp;';
				// Support button
				echo '<a class="cleantalk_auto_link" target="__blank" href="https://wordpress.org/support/plugin/cleantalk-spam-protect">'.__('Support', 'cleantalk').'</a>';
				echo '<br>'
					.'<br>';
			}
			
			settings_fields('cleantalk_settings');
			do_settings_fields('cleantalk', 'cleantalk_section_settings_main');
			if($apbct->debug){
				echo '<hr>';
				do_settings_fields('cleantalk', 'cleantalk_section_debug');
			}
			echo '<hr>';
			do_settings_fields('cleantalk', 'cleantalk_section_state');
				
			foreach($apbct->settings_fields_in_groups as $group_name => $group){
				
				echo !empty($group['html_before']) ? $group['html_before']                                      : '';
				echo !empty($group['title'])       ? '<h3 style="margin-left: 220px;">'.$group['title'].'</h3>' : '';
				
				do_settings_fields('cleantalk', 'apbct_section__'.$group_name);
				
				echo !empty($group['html_after'])  ? $group['html_after'] : '';
				
			}
			
			echo '<br>';
			echo '<button name="submit" class="cleantalk_manual_link" value="save_changes">'.__('Save Changes').'</button>';
		
		echo "</form>";
		
	// Translate banner for non EN locale
	if(substr(get_locale(), 0, 2) != 'en'){
		require_once(CLEANTALK_PLUGIN_DIR.'templates/translate_banner.php');
		printf($ct_translate_banner_template, substr(get_locale(), 0, 2));
	}
}

function apbct_settings__error__output($return = false){
	
	global $apbct;
	
	// If have error message output error block.
	
	if(!empty($apbct->errors)){
		
		$errors = $apbct->errors;
		
		$error_texts = array(
			// Misc
			'key_invalid' => __('Error occured while API key validating. Error: ', 'security-malware-firewall'),
			'key_get' => __('Error occured while automatically gettings access key. Error: ', 'security-malware-firewall'),
			'sfw_send_logs' => __('Error occured while sending sending SpamFireWall logs. Error: ', 'security-malware-firewall'),
			'sfw_update' => __('Error occured while updating SpamFireWall local base. Error: '            , 'security-malware-firewall'),
			'account_check' => __('Error occured while checking account status. Error: ', 'security-malware-firewall'),
			'api' => __('Error occured while excuting API call. Error: ', 'security-malware-firewall'),
			// Unknown
			'unknown' => __('Unknown error. Error: ', 'security-malware-firewall'),
		);
		
		$errors_out = array();
		
		foreach($errors as $type => $error){
			
			if(!empty($error)){
				
				if(is_array(current($error))){
					
					foreach($error as $sub_type => $sub_error){
						$errors_out[$sub_type] = '';
						if(isset($sub_error['error_time']))
							$errors_out[$sub_type] .= date('Y-m-d H:i:s', $sub_error['error_time']) . ': ';
						$errors_out[$sub_type] .= ucfirst($type).': ';
						$errors_out[$sub_type] .= (isset($error_texts[$sub_type]) ? $error_texts[$sub_type] : $error_texts['unknown']) . $sub_error['error_string'];
					}
					continue;
				}
				
				$errors_out[$type] = '';
				if(isset($error['error_time'])) 
					$errors_out[$type] .= date('Y-m-d H:i:s', $error['error_time']) . ': ';
				$errors_out[$type] .= (isset($error_texts[$type]) ? $error_texts[$type] : $error_texts['unknown']) . (isset($error['error_string']) ? $error['error_string'] : '');
				
			}
		}
		
		$out = '';
		if(!empty($errors_out)){
			$out .= '<div id="apbctTopWarning" class="error" style="position: relative;">'
				.'<h3 style="display: inline-block;">'.__('Errors:', 'security-malware-firewall').'</h3>';
				foreach($errors_out as $value)
					$out .= '<h4>'.$value.'</h4>';
				$out .= '<h4 style="text-align: none;">'.sprintf(__('You can get support any time here: %s.', 'cleantalk'), '<a target="blank" href="https://wordpress.org/support/plugin/cleantalk-spam-protect">https://wordpress.org/support/plugin/cleantalk-spam-protect</a>').'</h4>';
			$out .= '</div>';
		}
	}
	
	if($return) return $out; else echo $out;
}

function apbct_settings__field__debug(){
	
	global $apbct;
	
	echo '<hr /><h2>Debug:</h2>';
	echo '<h4>Constants:</h4>';
	echo 'CLEANTALK_AJAX_USE_BUFFER '.		 	(defined('CLEANTALK_AJAX_USE_BUFFER') ? 		(CLEANTALK_AJAX_USE_BUFFER ? 		'true' : 'flase') : 'NOT_DEFINED')."<br>";
	echo 'CLEANTALK_AJAX_USE_FOOTER_HEADER '.	(defined('CLEANTALK_AJAX_USE_FOOTER_HEADER') ? 	(CLEANTALK_AJAX_USE_FOOTER_HEADER ? 'true' : 'flase') : 'NOT_DEFINED')."<br>";
	echo 'CLEANTALK_ACCESS_KEY '.				(defined('CLEANTALK_ACCESS_KEY') ? 				(CLEANTALK_ACCESS_KEY ? 			CLEANTALK_ACCESS_KEY : 'flase') : 'NOT_DEFINED')."<br>";
	echo 'CLEANTALK_CHECK_COMMENTS_NUMBER '.	(defined('CLEANTALK_CHECK_COMMENTS_NUMBER') ? 	(CLEANTALK_CHECK_COMMENTS_NUMBER ? 	CLEANTALK_CHECK_COMMENTS_NUMBER : 0) : 'NOT_DEFINED')."<br>";
	echo 'CLEANTALK_CHECK_MESSAGES_NUMBER '.	(defined('CLEANTALK_CHECK_MESSAGES_NUMBER') ? 	(CLEANTALK_CHECK_MESSAGES_NUMBER ? 	CLEANTALK_CHECK_MESSAGES_NUMBER : 0) : 'NOT_DEFINED')."<br>";
	echo 'CLEANTALK_PLUGIN_DIR '.				(defined('CLEANTALK_PLUGIN_DIR') ? 				(CLEANTALK_PLUGIN_DIR ? 			CLEANTALK_PLUGIN_DIR : 'flase') : 'NOT_DEFINED')."<br>";
	echo 'WP_ALLOW_MULTISITE '.					(defined('WP_ALLOW_MULTISITE') ? 				(WP_ALLOW_MULTISITE ?				'true' : 'flase') : 'NOT_DEFINED');
	
	echo "<h4>Debug log: <button type='submit' value='debug_drop' name='submit' style='font-size: 11px; padding: 1px;'>Drop debug data</button></h4>";
	echo "<div style='height: 500px; width: 80%; overflow: auto;'>";
		
		$output = print_r($apbct->debug, true);
		$output = str_replace("\n", "<br>", $output);
		$output = preg_replace("/[^\S]{4}/", "&nbsp;&nbsp;&nbsp;&nbsp;", $output);
		echo "$output";
		
	echo "</div>";
}

function apbct_settings__field__state(){
	
	global $apbct, $wpdb;
	
	$path_to_img = plugin_dir_url(__FILE__) . "images/";
	
	$img = $path_to_img."yes.png";
	$img_no = $path_to_img."no.png";
	$img_no_gray = $path_to_img."no_gray.png";
	$color="black";
	
	if(!$apbct->key_is_ok){
		$img=$path_to_img."no.png";
		$img_no=$path_to_img."no.png";
		$color="black";
	}
	
	if(!apbct_api_key__is_correct($apbct->api_key)){
		$img = $path_to_img."yes_gray.png";
		$img_no = $path_to_img."no_gray.png";
		$color="gray";
	}
	
	if($apbct->moderate_ip)
	{
		$img = $path_to_img."yes.png";
		$img_no = $path_to_img."no.png";
		$color="black";
	}
	
	print '<div class="apbct_settings-field_wrapper" style="color:'.$color.'">';
	
		print '<h2>'.__('Protection is active', 'cleantalk').'</h2>';
		
		echo '<img class="apbct_status_icon" src="'.($apbct->settings['registrations_test'] == 1       || $apbct->moderate_ip ? $img : $img_no).'"/>'
			.__('Registration forms', 'cleantalk');
		echo '<img class="apbct_status_icon" src="'.($apbct->settings['comments_test']==1              || $apbct->moderate_ip ? $img : $img_no).'"/>'
			.__('Comments forms', 'cleantalk');
		echo '<img class="apbct_status_icon" src="'.($apbct->settings['contact_forms_test']==1         || $apbct->moderate_ip ? $img : $img_no).'"/>'
			.__('Contact forms', 'cleantalk');
		echo '<img class="apbct_status_icon" src="'.($apbct->settings['general_contact_forms_test']==1 || $apbct->moderate_ip ? $img : $img_no).'"/>'
			.__('Custom contact forms', 'cleantalk');
		
		// SFW + current network count
		/*
		$sfw_netwoks_amount = $wpdb->get_results("SELECT count(*) AS cnt FROM `".$wpdb->base_prefix."cleantalk_sfw`", ARRAY_A);
		$alt_for_sfw = sprintf(__('Networks in database: %d.', 'cleantalk'), $sfw_netwoks_amount[0]['cnt']); 
		echo '<img class="apbct_status_icon" src="'.($apbct->settings['spam_firewall']==1              || $apbct->moderate_ip ? $img : $img_no).'"  title="'.($apbct->settings['spam_firewall']==1 || $apbct->moderate_ip ? $alt_for_sfw : '').'"/>'.__('SpamFireWall', 'cleantalk');
		*/
		
		// Autoupdate status
		if($apbct->notice_auto_update){
			echo '<img class="apbct_status_icon" src="'.($apbct->auto_update == 1 ? $img : ($apbct->auto_update == -1 ? $img_no : $img_no_gray)).'"/>'.__('Auto update', 'cleantalk')
				.' <sup><a href="http://cleantalk.org/help/cleantalk-auto-update" target="_blank">?</a></sup>';
		}
		
		// WooCommerce
		if(class_exists('WooCommerce'))
			echo '<img class="apbct_status_icon" src="'.($apbct->options['wc_checkout_test']==1     || $apbct->moderate_ip ? $img : $img_no).'"/>'.__('WooCommerce checkout form', 'cleantalk');
		
		if($apbct->moderate_ip)
			print "<br /><br />The anti-spam service is paid by your hosting provider. License #".$apbct->data['ip_license'].".<br />";
	
	print "</div>";
}

/**
 * Admin callback function - Displays inputs of 'apikey' plugin parameter
 */
function apbct_settings__field__api_key(){
	
	global $apbct;
	
	echo '<div id="cleantalk_apkey_wrapper" class="apbct_settings-field_wrapper" '.(apbct_api_key__is_correct($apbct->api_key) && $apbct->key_is_ok ? 'style="display: none"' : '').'>';
	
		if(!is_multisite()){
			
			echo '<label class="apbct_settings__label" for="cleantalk_apkey">'.__('Access key', 'cleantalk').'</label>'
			.'<input class="apbct_settings__text_feld" name="cleantalk_settings[apikey]" size="20" type="text" value="'.$apbct->api_key.'" style=\"font-size: 14pt;\" placeholder="' . __('Enter the key', 'cleantalk') . '" />';
			
			// Key is correct
			if(!apbct_api_key__is_correct($apbct->api_key) || !$apbct->key_is_ok){
				echo '<script>var cleantalk_good_key=false;</script>';
				echo '<br /><br />';
				
				// Auto get key
				if(!$apbct->ip_license){
					echo '<button id="apbct_setting_get_key_auto" name="submit" type="submit" class="cleantalk_manual_link" value="get_key_auto">'
						.__('Get access key automatically', 'cleantalk')
					.'</button>';
					echo '<input type="hidden" id="ct_admin_timezone" name="ct_admin_timezone" value="null" />';
					echo '&nbsp;' .  __('or') . '&nbsp;';
				}
				
				// Manual get key
				echo '<a style="color: gray;" target="__blank" href="https://cleantalk.org/register?platform=wordpress&email='.urlencode(ct_get_admin_email()).'&website='.urlencode(parse_url(get_option('siteurl'),PHP_URL_HOST)).'">'.__('Get access key manually', 'cleantalk').'</a>';
				echo '<br />';
				echo '<br />';
				
				// Warnings and GDPR
				printf(__('Admin e-mail (%s) will be used for registration', 'cleantalk'), ct_get_admin_email());
				echo '<div>';
					echo '<input checked type="checkbox" id="license_agreed" onclick="apbctSettingsDependencies(\'get_key_auto\');"/>';
					echo '<label for="spbc_license_agreed">';
						printf(
							__('I agree with %sPrivacy Policy%s of %sLicense Agreement%s', 'security-malware-firewall'),
							'<a href="https://cleantalk.org/publicoffer#privacy" target="_blank" style="color:#66b;">', '</a>',
							'<a href="https://cleantalk.org/publicoffer"         target="_blank" style="color:#66b;">', '</a>'
						);
					echo "</label>";
				echo '</div>';
			}
			
		}else{
			_e('<h3>Key is provided by Super Admin.<h3>', 'cleantalk');
		}
	
	echo '</div>';
	
	if($apbct->ip_license){
		$cleantalk_support_links = "<br /><div>";
        $cleantalk_support_links .= "<a href='#' class='ct_support_link'>" . __("Show the access key", 'cleantalk') . "</a>";
        $cleantalk_support_links .= "</div>";
		echo "<script type=\"text/javascript\">var cleantalk_good_key=true; var cleantalk_support_links = \"$cleantalk_support_links\";</script>";
	}
}

function apbct_settings__field__action_buttons(){
	
	global $apbct;
	
	echo '<div class="apbct_settings-field_wrapper">';
	
		if(apbct_api_key__is_correct($apbct->api_key) && $apbct->key_is_ok){
			echo '<div>'
				.'<a href="#" class="ct_support_link" onclick="apbct_show_hide_elem(\'#cleantalk_apkey_wrapper\')">' . __('Show the access key', 'cleantalk') . '</a>'
				.'&nbsp;&nbsp;'
				.'&nbsp;&nbsp;'
				.'<a href="edit-comments.php?page=ct_check_spam" class="ct_support_link">' . __('Check comments for spam', 'cleantalk') . '</a>'
				.'&nbsp;&nbsp;'
				.'&nbsp;&nbsp;'
				.'<a href="users.php?page=ct_check_users" class="ct_support_link">' . __('Check users for spam', 'cleantalk') . '</a>'
				.'&nbsp;&nbsp;'
				.'&nbsp;&nbsp;'
				.'<a href="#" class="ct_support_link" onclick="apbct_show_hide_elem(\'#apbct_connection_reports\')">' . __('Negative report', 'cleantalk') . '</a>'
			.'</div>';
		
		}
		
	echo '</div>';
}

function apbct_settings__field__connection_reports() {

	global $apbct;
	
	echo '<div id="apbct_connection_reports" class="apbct_settings-field_wrapper" style="display: none;">';
	
		if ($apbct->connection_reports){
			
			if ($apbct->connection_reports['negative'] == 0){
				_e('There are no failed connections to CleanTalk servers.', 'cleantalk');
			}else{
				echo "<table id='negative_reports_table' style='display: none;'>
					<tr>
						<td>#</td>
						<td><b>Date</b></td>
						<td><b>Page URL</b></td>
						<td><b>Report</b></td>
					</tr>";
				foreach($apbct->connection_reports['negative_report'] as $key => $report){
					echo "<tr><td>".($key+1).".</td><td>".$report['date']."</td><td>".$report['page_url']."</td><td>".$report['lib_report']."</td></tr>";
				}
				echo "</table>";
				echo '<br/>'
				.'<button name="submit" class="cleantalk_manual_link" value="ct_send_connection_report">'.__('Send report', 'cleantalk').'</button>';
			}

		}
		
	echo '</div>';
}

function apbct_settings__field__draw($params = array()){
	
	global $apbct;
	
	echo '<div class="'.$params['def_class'].(isset($params['class']) ? ' '.$params['class'] : '').'">';
		switch($params['type']){
			case 'checkbox':
				echo '<input type="checkbox" id="apbct_setting_'.$params['name'].'" name="cleantalk_settings['.$params['name'].']" value="1" '
					.($apbct->settings[$params['name']] == '1' ? ' checked' : '')
					.($params['parent'] && !$apbct->settings[$params['parent']] ? ' disabled="disabled"' : '')
					.(!$params['childrens'] ? '' : ' onchange="apbctSettingsDependencies([\''.implode("','",$params['children']).'\'])"')
					.' />'
				.'<label for="apbct_setting_'.$params['name'].'" class="apbct_setting-field_title--'.$params['type'].'">'
					.$params['title']
				.'</label>';
				echo '<div class="apbct_settings-field_description">'
					.$params['description']
				.'</div>';				
				break;
			case 'radio':
				echo '<h4 class="apbct_settings-field_title apbct_settings-field_title--'.$params['type'].'">'
					.$params['title']
				.'</h4>';
				
				echo '<div class="apbct_settings-field_content apbct_settings-field_content--'.$params['type'].'">';
				
					echo '<input type="radio" id="apbct_setting_'.$params['name'].'_yes" name="cleantalk_settings['.$params['name'].']" value="1" '
						.($params['parent'] && !$apbct->settings[$params['parent']] ? ' disabled="disabled"' : '')
						.(!$params['childrens'] ? '' : ' onchange="apbctSettingsDependencies([\''.implode("','",$params['childrens']).'\'])"')
						.($apbct->settings[$params['name']] ? ' checked' : '').' />'
						.'<label for="apbct_setting_'.$params['name'].'_yes"> ' . __('Yes') . '</label>';
						
					echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
					
					echo '<input type="radio" id="apbct_setting_'.$params['name'].'_no" name="cleantalk_settings['.$params['name'].']" value="0" '
						.($params['parent'] && !$apbct->settings[$params['parent']] ? ' disabled="disabled"' : '')
						.(!$params['childrens'] ? '' : ' onchange="apbctSettingsDependencies([\''.implode("','",$params['childrens']).'\'])"')
						.(!$apbct->settings[$params['name']] ? ' checked' : '').' />'
						.'<label for="apbct_setting_'.$params['name'].'_no">'. __('No') . '</label>';
					
					echo '<div class="apbct_settings-field_description">'
						.$params['description']
					.'</div>';
					
				echo '</div>';
				break;
		}
		
	echo '</div>';
}

/**
 * Admin callback function - Plugin parameters validator
 */
function apbct_settings__validate($settings) {
	
	global $apbct;
	
	// Drop debug data
	if (isset($_POST['submit']) && $_POST['submit'] == 'debug_drop'){
		$apbct->debug = false;
		delete_option('cleantalk_debug');
		return $settings;
	}
	
	// Send connection reports
	if (isset($_POST['submit']) && $_POST['submit'] == 'ct_send_connection_report'){
		ct_mail_send_connection_report();
		return $settings;
	}
	
	// Auto getting key
	if (isset($_POST['submit']) && $_POST['submit'] == 'get_key_auto')
	{
		$website = parse_url(get_option('siteurl'),PHP_URL_HOST);
		$platform = 'wordpress';
		$timezone = $_POST['ct_admin_timezone'];
		
		$result = CleantalkHelper::api_method__get_api_key(ct_get_admin_email(), $website, $platform, $timezone);
		
		if(empty($result['error'])){
			
			if(isset($result['user_token'])){
				$apbct->data['user_token'] = $result['user_token'];
				$apbct->saveData();
			}
			
			if(!empty($result['auth_key'])){
				$settings['apikey'] = $result['auth_key'];
			}
			
		}else{
			$apbct->error_add('key_get', $result);
		}
	}
	
	$settings['apikey'] = isset($settings['apikey']) ? trim($settings['apikey']) : '';
	
	// Key is good by default
	$apbct->data['key_is_ok'] = true;
	
	// Is key correct?
	if(apbct_api_key__is_correct($settings['apikey'])){
		
		$result = CleantalkHelper::api_method__notice_validate_key($settings['apikey'], preg_replace('/http[s]?:\/\//', '', get_option('siteurl'), 1));
		
		// Is key valid?
		if (empty($result['error'])){
			
			if($result['valid'] == 1){
				
				// Deleting errors about invalid key
				$apbct->error_delete('key_invalid', 'save');
				
// TO DO		// Feedback with app_agent
				ct_send_feedback('0:' . APBCT_AGENT); // 0 - request_id, agent version.
				
				// Check account status
				ct_account_status_check($settings['apikey']);
				
			// Key is not valid
			}else{
				$apbct->data['key_is_ok'] = false;
				$apbct->error_add('key_invalid', __('Testing is failed. Please check the Access key.', 'cleantalk'));
			}
			
		// Server error when notice_validate_key
		}else{
			$apbct->error_add('key_invalid', $result);
		}
	
	// Key is not correct
	}else{
		if(empty($settings['apikey'])){
			$apbct->error_delete('key_invalid account_check', 'save');
		}else
			$apbct->error_add('key_invalid', __('Key is not correct', 'cleantalk'));
	}
	
	// A-B test with SFW
	if(!$apcbt->data['ab_test']['sfw_enabled']){
		if($apbct->service_id % 2 == 1)
			$settings['spam_firewall'] = 0;
		else
			$settings['spam_firewall'] = 1;
		$apcbt->data['ab_test']['sfw_enabled'] = true;
	}
		
	$apbct->saveData();
	
	
	return $settings;
}

function apbct_gdpr__show_text(){
?>
	<p>The notice requirements remain and are expanded. They must include the retention time for personal data, and contact information for data controller and data protection officer has to be provided.</p>

	<p>Automated individual decision-making, including profiling (Article 22) is contestable, similarly to the Data Protection Directive (Article 15). Citizens have rights to question and fight significant decisions that affect them that have been made on a solely-algorithmic basis. Many media outlets have commented on the introduction of a "right to explanation" of algorithmic decisions, but legal scholars have since argued that the existence of such a right is highly unclear without judicial tests and is limited at best.</p>

	<p>To be able to demonstrate compliance with the GDPR, the data controller should implement measures, which meet the principles of data protection by design and data protection by default. Privacy by design and by default (Article 25) require data protection measures to be designed into the development of business processes for products and services. Such measures include pseudonymising personal data, by the controller, as soon as possible (Recital 78).</p>

	<p>It is the responsibility and the liability of the data controller to implement effective measures and be able to demonstrate the compliance of processing activities even if the processing is carried out by a data processor on behalf of the controller (Recital 74).</p>

	<p>Data Protection Impact Assessments (Article 35) have to be conducted when specific risks occur to the rights and freedoms of data subjects. Risk assessment and mitigation is required and prior approval of the national data protection authorities (DPAs) is required for high risks. Data protection officers (Articles 37–39) are required to ensure compliance within organisations.</p>

	<p>They have to be appointed:</p>
	<ul style="padding: 0px 25px; list-style: disc;">
		<li>for all public authorities, except for courts acting in their judicial capacity</li>
		<li>if the core activities of the controller or the processor are:</li>
			<ul style="padding: 0px 25px; list-style: disc;">
				<li>processing operations, which, by virtue of their nature, their scope and/or their purposes, require regular and systematic monitoring of data subjects on a large scale</li>
				<li>processing on a large scale of special categories of data pursuant to Article 9 and personal data relating to criminal convictions and offences referred to in Article 10';</li>
			</ul>
		</li>
	</ul>
	<?php
}