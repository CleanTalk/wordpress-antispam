<?php

/**
 * Init functions
 * @return 	mixed[] Array of options
 */
function apbct_init() {

    global $ct_wplp_result_label, $ct_jp_comments, $ct_post_data_label, $ct_post_data_authnet_label, $apbct, $test_external_forms, $cleantalk_executed, $wpdb;

    //Check internal forms with such "action" http://wordpress.loc/contact-us/some_script.php
    if((isset($_POST['action']) && $_POST['action'] == 'ct_check_internal') &&
        $apbct->settings['check_internal']
    ){
        $ct_result = ct_contact_form_validate();
        if($ct_result == null){
            echo 'true';
            die();
        }else{
            echo $ct_result;
            die();
        }
    }

    //fix for EPM registration form
    if(isset($_POST) && isset($_POST['reg_email']) && shortcode_exists( 'epm_registration_form' ))
    {
    	unset($_POST['ct_checkjs_register_form']);
    }

    if(isset($_POST['_wpnonce-et-pb-contact-form-submitted']))
    {
    	add_shortcode( 'et_pb_contact_form', 'ct_contact_form_validate' );
    }

	if($apbct->settings['check_external']){

		// Fixing form and directs it this site
		if($apbct->settings['check_external__capture_buffer'] && !is_admin() && !apbct_is_ajax() && !apbct_is_post() && apbct_is_user_enable() && !(defined('DOING_CRON') && DOING_CRON) && !(defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)){
			
			if (defined('CLEANTALK_CAPTURE_BUFFER_SPECIFIC_URL') && is_string(CLEANTALK_CAPTURE_BUFFER_SPECIFIC_URL)) {
				$catch_buffer = false;
				$urls = explode(',', CLEANTALK_CAPTURE_BUFFER_SPECIFIC_URL);
				foreach ($urls as $url) {
					if (apbct_is_in_uri($url))
						$catch_buffer = true;
				}
			}else{
				$catch_buffer = true;
			}
			
			if( $catch_buffer ){
				add_action('wp', 'apbct_buffer__start');
				add_action('shutdown', 'apbct_buffer__end', 0);
				add_action('shutdown', 'apbct_buffer__output', 2);
			}
		}

		// Check and redirecct
		if( apbct_is_post()
			&& isset($_POST['cleantalk_hidden_method'])
			&& isset($_POST['cleantalk_hidden_action'])
		){
			$action = htmlspecialchars($_POST['cleantalk_hidden_action']);
			$method = htmlspecialchars($_POST['cleantalk_hidden_method']);
			unset($_POST['cleantalk_hidden_action']);
			unset($_POST['cleantalk_hidden_method']);
			ct_contact_form_validate();
			if(!apbct_is_ajax()){
				print "<html><body><form method='$method' action='$action'>";
				ct_print_form($_POST, '');
				print "</form></body></html>";
				print "<script " . ( class_exists('Cookiebot_WP') ? 'data-cookieconsent="ignore"' : '' ) . ">
					if(document.forms[0].submit !== 'undefined'){
						var objects = document.getElementsByName('submit');
						if(objects.length > 0)
							document.forms[0].removeChild(objects[0]);
					}
					document.forms[0].submit();
				</script>";
				die();
			}
		}
	}

	if(isset($_POST['quform_ajax'], $_POST['quform_csrf_token'], $_POST['quform_form_id'])){
		require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-ajax.php');
		ct_ajax_hook();
	}

	/**hooks for cm answers pro */
	if(defined('CMA_PLUGIN_FILE')){
		add_action( 'wp', 'ct_ajax_hook',1 );
	}

	//hook for Anonymous Post
    if($apbct->settings['general_postdata_test'] == 1 && empty($_POST['ct_checkjs_cf7']))
    	add_action('wp', 'ct_contact_form_validate_postdata',1);

    if($apbct->settings['general_contact_forms_test'] == 1 && empty($_POST['ct_checkjs_cf7'])){
		add_action('CMA_custom_post_type_nav', 'ct_contact_form_validate_postdata',1);
		//add_action('init','ct_contact_form_validate',1);
		ct_contact_form_validate();
		if(isset($_POST['reg_redirect_link'])&&isset($_POST['tmpl_registration_nonce_field']))
		{
			unset($_POST['ct_checkjs_register_form']);
			ct_contact_form_validate();
		}
		/*if(isset($_GET['ait-action'])&&$_GET['ait-action']=='register')
		{
			$tmp=$_POST['redirect_to'];
			unset($_POST['redirect_to']);
			ct_contact_form_validate();
			$_POST['redirect_to']=$tmp;
		}*/
	}

    if($apbct->settings['general_postdata_test'] == 1 && empty($_POST['ct_checkjs_cf7']))
    	add_action('CMA_custom_post_type_nav', 'ct_contact_form_validate_postdata',1);

	//add_action('wp_footer','ct_ajaxurl');

    // Fast Secure contact form
		if(defined('FSCF_VERSION')){
			add_filter('si_contact_display_after_fields', 'ct_si_contact_display_after_fields');
			add_filter('si_contact_form_validate', 'ct_si_contact_form_validate');
		}

    // WooCommerce registration
    if(class_exists('WooCommerce')){
        add_filter( 'woocommerce_registration_errors', 'ct_registration_errors', 1, 3 );
        if ($apbct->settings['wc_checkout_test'] == 1) {
	        add_filter('woocommerce_checkout_process', 'ct_woocommerce_checkout_check', 1, 3);
        }
        if( isset($_REQUEST['wc-ajax']) && $_REQUEST['wc-ajax'] == 'checkout' && empty( $apbct->settings['wc_register_from_order'] ) ){
            remove_filter( 'woocommerce_registration_errors', 'ct_registration_errors', 1 );
        }
    }

	// WooCommerce whishlist
	if(class_exists('WC_Wishlists_Wishlist'))
		add_filter('wc_wishlists_create_list_args', 'ct_woocommerce_wishlist_check', 1, 1);


    // JetPack Contact form
    $jetpack_active_modules = false;
    if(defined('JETPACK__VERSION'))
    {
        // Checking Jetpack contact form
        if(isset($_POST['action']) && $_POST['action'] == 'grunion-contact-form' ){
            if(JETPACK__VERSION=='3.4-beta')
            {
                add_filter('contact_form_is_spam', 'ct_contact_form_is_spam');
            }
            else if(JETPACK__VERSION=='3.4-beta2'||JETPACK__VERSION>='3.4')
            {
                add_filter('jetpack_contact_form_is_spam', 'ct_contact_form_is_spam_jetpack',50,2);
            }
            else
            {
                add_filter('contact_form_is_spam', 'ct_contact_form_is_spam');
            }

        }else {
            add_filter('grunion_contact_form_field_html', 'ct_grunion_contact_form_field_html', 10, 2);
        }

        // Checking Jetpack comments form
        $jetpack_active_modules = get_option('jetpack_active_modules');
        if ((class_exists( 'Jetpack', false) && $jetpack_active_modules && in_array('comments', $jetpack_active_modules)))
        {
            $ct_jp_comments = true;
        }

    }

	// WP Maintenance Mode (wpmm)
		add_action('wpmm_head', 'apbct_form__wpmm__addField', 1);

    // Contact Form7
		if(defined('WPCF7_VERSION')){
			add_filter('wpcf7_form_elements', 'apbct_form__contactForm7__addField');
			add_filter('wpcf7_validate', 'apbct_form__contactForm7__tesSpam__before_validate', 999, 2);
			add_filter(WPCF7_VERSION >= '3.0.0' ? 'wpcf7_spam' : 'wpcf7_acceptance', 'apbct_form__contactForm7__testSpam', 9999, 2);
		}

    // Formidable
		add_filter( 'frm_entries_before_create', 'apbct_rorm__formidable__testSpam', 10, 2 );
		add_action( 'frm_entries_footer_scripts', 'apbct_rorm__formidable__footerScripts', 20, 2 );

    // BuddyPress
		if(class_exists('BuddyPress')){
			add_action('bp_before_registration_submit_buttons','ct_register_form',1);
			add_action('messages_message_before_save',    'apbct_integration__buddyPres__private_msg_check', 1);
			add_filter('bp_signup_validate', 'ct_registration_errors',1);
			add_filter('bp_signup_validate', 'ct_check_registration_erros', 999999);
		}

	if(defined('PROFILEPRESS_SYSTEM_FILE_PATH')){
		add_filter('pp_registration_validation', 'ct_registration_errors_ppress', 11, 2);
	}


    // bbPress
		if(class_exists('bbPress')){
			add_filter('bbp_new_topic_pre_title', 'ct_bbp_get_topic', 1);
			add_filter('bbp_new_topic_pre_content', 'ct_bbp_new_pre_content', 1);
			add_filter('bbp_new_reply_pre_content', 'ct_bbp_new_pre_content', 1);
			add_action('bbp_theme_before_topic_form_content', 'ct_comment_form');
			add_action('bbp_theme_before_reply_form_content', 'ct_comment_form');
		}

	//Custom Contact Forms
		if(defined('CCF_VERSION'))
			add_filter('ccf_field_validator', 'ct_ccf', 1, 4);

    add_action('comment_form', 'ct_comment_form');

    // intercept WordPress Landing Pages POST
    if (defined('LANDINGPAGES_CURRENT_VERSION') && !empty($_POST)){
        if(array_key_exists('action', $_POST) && $_POST['action'] === 'inbound_store_lead'){ // AJAX action(s)
            ct_check_wplp();
        }else if(array_key_exists('inbound_submitted', $_POST) && $_POST['inbound_submitted'] == '1'){ // Final submit
            ct_check_wplp();
        }
    }

    // S2member. intercept POST
		if (defined('WS_PLUGIN__S2MEMBER_PRO_VERSION')){
			$post_keys = array_keys($_POST);
			foreach($post_keys as $post_key){

				// Detect POST keys like /s2member_pro.*registration/
				if(strpos($post_key, 's2member') !== false && strpos($post_key, 'registration') !== false){
					ct_s2member_registration_test($post_key);
					break;
				}
			}
		}

    // New user approve hack
    // https://wordpress.org/plugins/new-user-approve/
    if (ct_plugin_active('new-user-approve/new-user-approve.php')) {
        add_action('register_post', 'ct_register_post', 1, 3);
    }

	// Wilcity theme registration validation fix
	add_filter( 'wilcity/filter/wiloke-listing-tools/validate-before-insert-account', 'apbct_wilcity_reg_validation', 10, 2 );


    // Gravity forms
		if (defined('GF_MIN_WP_VERSION')) {
			add_filter('gform_get_form_filter', 'apbct_form__gravityForms__addField', 10, 2);
			add_filter('gform_entry_is_spam', 'apbct_form__gravityForms__testSpam', 999, 3);
			add_filter('gform_confirmation', 'apbct_form__gravityForms__showResponse', 999, 4 );
		}

	//Pirate forms
		if(defined('PIRATE_FORMS_VERSION')){
			if(isset($_POST['pirate-forms-contact-name']) && $_POST['pirate-forms-contact-name'] && isset($_POST['pirate-forms-contact-email']) && $_POST['pirate-forms-contact-email'])
				apbct_form__piratesForm__testSpam();
		}

	// WPForms
		// Adding fields
		 add_action('wpforms_frontend_output', 'apbct_form__WPForms__addField', 1000, 5);
		// Gathering data to validate
		add_filter('wpforms_process_before_filter', 'apbct_from__WPForms__gatherData', 100, 2);
		// Do spam check
		add_filter('wpforms_process_initial_errors', 'apbct_form__WPForms__showResponse', 100, 2);

	// QForms integration
	add_filter( 'quform_post_validate',  'ct_quform_post_validate', 10, 2 );

	// Ultimate Members
	if (class_exists('UM')) {
		add_action('um_main_register_fields','ct_register_form',100); // Add hidden fileds
		add_action( 'um_submit_form_register', 'apbct_registration__UltimateMembers__check', 9, 1 ); // Check submition
	}

	// Paid Memberships Pro integration
    add_filter( 'pmpro_required_user_fields', function( $pmpro_required_user_fields ){

        if(
            ! empty( $pmpro_required_user_fields['username'] ) &&
            ! empty( $pmpro_required_user_fields['bemail'] ) &&
            ! empty( $pmpro_required_user_fields['bconfirmemail'] ) &&
            $pmpro_required_user_fields['bemail'] == $pmpro_required_user_fields['bconfirmemail']
        ) {
	        $check = ct_test_registration( $pmpro_required_user_fields['username'], $pmpro_required_user_fields['bemail'] );
            if( $check['allow'] == 0 ) {
                pmpro_setMessage( $check['comment'], 'pmpro_error' );
            }
        }

        return $pmpro_required_user_fields;

    } );

    //
    // Load JS code to website footer
    //
    if (!(defined( 'DOING_AJAX' ) && DOING_AJAX)) {
        add_action('wp_head',   'apbct_hook__wp_head__set_cookie__ct_checkjs', 1);
        add_action('wp_footer', 'apbct_hook__wp_footer', 1);
    }

    if ($apbct->settings['protect_logged_in'] != 1 && is_user_logged_in()) {
        ct_contact_form_validate();
    }

    if (apbct_is_user_enable()) {

        if ($apbct->settings['general_contact_forms_test'] == 1 && !isset($_POST['comment_post_ID']) && !isset($_GET['for'])){
            add_action( 'init', 'ct_contact_form_validate', 999 );
        }
        if( apbct_is_post() &&
			$apbct->settings['general_postdata_test'] == 1 &&
			!isset($_POST['ct_checkjs_cf7']) &&
			!is_admin() &&
			!apbct_is_user_role_in(array('administrator', 'moderator'))
		){
	    	ct_contact_form_validate_postdata();
	    }
    }
}

function apbct_buffer__start(){
	ob_start();
}

function apbct_buffer__end(){

	if(!ob_get_level())
		return;

	global $apbct;
	$apbct->buffer = ob_get_contents();
	ob_end_clean();
}

/**
 * Outputs changed buffer
 *
 * @global $apbct
 */
function apbct_buffer__output(){

	global $apbct, $wp;

	if(empty($apbct->buffer))
		return;

	$site_url   = get_option('siteurl');
	$site__host = parse_url($site_url,  PHP_URL_HOST);

	$dom = new DOMDocument();
	@$dom->loadHTML($apbct->buffer);

	$forms = $dom->getElementsByTagName('form');

	foreach($forms as $form){

		$action = $form->getAttribute('action');
		$action = $action ? $action : $site_url;
		$action__host = parse_url($action,  PHP_URL_HOST);

		// Check if the form directed to the third party site
		if($site__host != $action__host){

			$method = $form->getAttribute('method');
			$method = $method ? $method : 'get';
			// Directs form to our site
			$form->setAttribute('method', 'POST');
			$form->setAttribute('action', home_url(add_query_arg(array(), $wp->request)));

			// Add cleantalk_hidden_action
			$new_input = $dom->createElement('input');
			$new_input->setAttribute('type', 'hidden');
			$new_input->setAttribute('name', 'cleantalk_hidden_action');
			$new_input->setAttribute('value', $action);
			$form->appendChild($new_input);

			// Add cleantalk_hidden_method
			$new_input = $dom->createElement('input');
			$new_input->setAttribute('type', 'hidden');
			$new_input->setAttribute('name', 'cleantalk_hidden_method');
			$new_input->setAttribute('value', $method);
			$form->appendChild($new_input);

		}

	} unset($form);
	
	$html = $dom->getElementsByTagName('html');
	
	$output = gettype($html) == 'object' && isset($html[0], $html[0]->childNodes, $html[0]->childNodes[0]) && $dom->getElementsByTagName('rss')->length == 0
		? $dom->saveHTML()
		: $apbct->buffer;
	
	echo $output;
	die();
}

// MailChimp Premium for Wordpress
function ct_add_mc4wp_error_message($messages){

	$messages['ct_mc4wp_response'] = array(
		'type' => 'error',
		'text' => 'Your message looks like spam.'
	);
	return $messages;
}
add_filter( 'mc4wp_form_messages', 'ct_add_mc4wp_error_message' );

/*
 * Function to set validate fucntion for CCF form
 * Input - Сonsistently each form field
 * Returns - String. Validate function
*/
function ct_ccf($callback, $value, $field_id, $type){
	/*
	if($type == 'name')
		$ct_global_temporary_data['name'] = $value;
	elseif($type == 'email')
		$ct_global_temporary_data['email'] = $value;
	else
		$ct_global_temporary_data[] = $value;
	//*/
	return 'ct_validate_ccf_submission';
}
/*
 * Validate function for CCF form. Gatheering data. Multiple calls.
 * Input - void. Global $ct_global_temporary_data
 * Returns - String. CleanTalk comment.
*/
$ct_global_temporary_data = array();
function ct_validate_ccf_submission($value, $field_id, $required){
	global $ct_global_temporary_data, $apbct;



	//If the check for contact forms enabled
	if(!$apbct->settings['contact_forms_test']) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return true;
    }

	//If the check for logged in users enabled
	if($apbct->settings['protect_logged_in'] == 1 && is_user_logged_in()) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return true;
    }


	//Accumulate data
	$ct_global_temporary_data[] = $value;

	//If it's the last field of the form
	(!isset($ct_global_temporary_data['count']) ? $ct_global_temporary_data['count'] = 1 : $ct_global_temporary_data['count']++);
	$form_id = $_POST['form_id'];
	if($ct_global_temporary_data['count'] != count(get_post_meta( $form_id, 'ccf_attached_fields', true ))) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return true;
    }

	unset($ct_global_temporary_data['count']);

	//Getting request params
	$ct_temp_msg_data = ct_get_fields_any($_POST);

	unset($ct_global_temporary_data);

    $sender_email    = ($ct_temp_msg_data['email']    ? $ct_temp_msg_data['email']    : '');
    $sender_nickname = ($ct_temp_msg_data['nickname'] ? $ct_temp_msg_data['nickname'] : '');
    $subject         = ($ct_temp_msg_data['subject']  ? $ct_temp_msg_data['subject']  : '');
    $contact_form    = ($ct_temp_msg_data['contact']  ? $ct_temp_msg_data['contact']  : true);
    $message         = ($ct_temp_msg_data['message']  ? $ct_temp_msg_data['message']  : array());

	if ($subject != '')
        $message['subject'] = $subject;

	$post_info['comment_type'] = 'feedback_custom_contact_forms';
    $post_info['post_url'] = apbct_get_server_variable( 'HTTP_REFERER' );

	$checkjs = apbct_js_test('ct_checkjs', $_COOKIE)
		? apbct_js_test('ct_checkjs', $_COOKIE)
		: apbct_js_test('ct_checkjs', $_POST);

	//Making a call
	$base_call_result = apbct_base_call(
		array(
			'message'         => $message,
			'sender_email'    => $sender_email,
			'sender_nickname' => $sender_nickname,
			'post_info'       => $post_info,
			'js_on'           => $checkjs,
			'sender_info'     => array('sender_url' => null),
		)
	);

    $ct_result = $base_call_result['ct_result'];

	return $ct_result->allow == 0 ? $ct_result->comment : true;;
}

function ct_woocommerce_wishlist_check($args){
	global $apbct;



	//Protect logged in users
	if($args['wishlist_status'])
		if($apbct->settings['protect_logged_in'] == 0) {
            do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
            return $args;
        }


	//If the IP is a Google bot
	$hostname = gethostbyaddr( apbct_get_server_variable( 'REMOTE_ADDR' ) );
	if(!strpos($hostname, 'googlebot.com')) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return $args;
    }


	//Getting request params
	$message = '';
	$subject = '';
	$email = $args['wishlist_owner_email'];
	if($args['wishlist_first_name']!='' || $args['wishlist_last_name']!='')
		$nickname = trim($args['wishlist_first_name']." ".$args['wishlist_last_name']);
	else
		$nickname = '';

	$post_info['comment_type'] = 'feedback';
    $post_info['post_url'] = apbct_get_server_variable( 'HTTP_REFERER' );

	$checkjs = apbct_js_test('ct_checkjs', $_COOKIE)
		? apbct_js_test('ct_checkjs', $_COOKIE)
		: apbct_js_test('ct_checkjs', $_POST);

	//Making a call
	$base_call_result = apbct_base_call(
		array(
			'message'         => $subject." ".$message,
			'sender_email'    => $email,
			'sender_nickname' => $nickname,
			'post_info'       => $post_info,
			'js_on'           => $checkjs,
			'sender_info'     => array('sender_url' => null),
		)
	);

    $ct_result = $base_call_result['ct_result'];

    if ($ct_result->allow == 0)
		wp_die("<h1>".__('Spam protection by CleanTalk', 'cleantalk-spam-protect')."</h1><h2>".$ct_result->comment."</h2>", '', array('response' => 403, "back_link" => true, "text_direction" => 'ltr'));
	else
		return $args;
}

function apbct_integration__buddyPres__getTemplateName( $located, $template_name, $template_names, $template_locations, $load, $require_once ) {
	global $apbct;
	preg_match("/\/([a-z-_]+)\/buddypress-functions\.php$/", $located, $matches);
	$apbct->buddy_press_tmpl = isset($matches[1]) ? $matches[1] : 'unknown';
}

/**
 * Test BuddyPress activity for spam (post update only)
 *
 * @global SpbcState $apbct
 * @param bool $is_spam
 * @param BP_Activity_Activity $activity_obj Activity object (\plugins\buddypress\bp-activity\classes\class-bp-activity-activity.php)
 * @return boolean Spam flag
 */
function apbct_integration__buddyPres__activityWall( $is_spam, $activity_obj = null ){

	global $apbct;

	$allowed_post_actions = array('post_update', 'new_activity_comment');

	if( ! in_array(\Cleantalk\Variables\Post::get('action'), $allowed_post_actions) ||
		$activity_obj === null ||
	    ! \Cleantalk\Variables\Post::get('action') ||
	    $activity_obj->privacy == 'media' ||
	    apbct_exclusions_check()
	) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return false;
    }

  	$curr_user = get_user_by('id', $activity_obj->user_id);

	//Making a call
	$base_call_result = apbct_base_call(
		array(
			'message'         => is_string($activity_obj->content) ? $activity_obj->content : '',
			'sender_email'    => $curr_user->data->user_email,
			'sender_nickname' => $curr_user->data->user_login,
			'post_info'       => array(
				'post_url'     => apbct_get_server_variable( 'HTTP_REFERER' ),
				'comment_type' => 'buddypress_activitywall',
			),
			'js_on'           => apbct_js_test('ct_checkjs', $_COOKIE),
			'sender_info'     => array('sender_url' => null),
		)
	);

    $ct_result = $base_call_result['ct_result'];

    if ($ct_result->allow == 0){
		add_action('bp_activity_after_save', 'apbct_integration__buddyPres__activityWall_showResponse', 1, 1);
		$apbct->spam_notification = $ct_result->comment;
		return true;
	}else
		return $is_spam;
}

/**
 * Outputs message to AJAX frontend handler
 *
 * @global SpbcState $apbct
 * @param BP_Activity_Activity $activity_obj Activity object (\plugins\buddypress\bp-activity\classes\class-bp-activity-activity.php)
 */
function apbct_integration__buddyPres__activityWall_showResponse( $activity_obj ){

	global $apbct;

	// Legacy template
	if($apbct->buddy_press_tmpl === 'bp-legacy'){
		die('<div id="message" class="error bp-ajax-message"><p>'. $apbct->spam_notification .'</p></div>');
	// Nouveau tamplate and others
	}else{
		@header( 'Content-Type: application/json; charset=' . get_option('blog_charset'));
		die(json_encode(array(
			'success' => false,
			'data' => array('message' => $apbct->spam_notification),
		)));
	}
}

/**
 * Public function - Tests new private messages (dialogs)
 *
 * @global SpbcState $apbct
 * @param type $bp_message_obj
 * @return void|array with errors if spam has found
 */
function apbct_integration__buddyPres__private_msg_check( $bp_message_obj){

	global $apbct;

	//Check for enabled option
	if(
		$apbct->settings['bp_private_messages'] == 0 ||
		apbct_exclusions_check()
	) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
	    return;
    }


	//Check for quantity of comments
	$comments_check_number = defined('CLEANTALK_CHECK_COMMENTS_NUMBER')
		? CLEANTALK_CHECK_COMMENTS_NUMBER
		: 3;

    if($apbct->settings['check_comments_number']){
		$args = array(
			'user_id' => $bp_message_obj->sender_id,
			'box' => 'sentbox',
			'type' => 'all',
			'limit' => $comments_check_number,
			'page' => null,
			'search_terms' => '',
			'meta_query' => array()
		);
    	$sentbox_msgs = BP_Messages_Thread::get_current_threads_for_user($args);
		$cnt_sentbox_msgs = $sentbox_msgs['total'];
		$args['box'] = 'inbox';
		$inbox_msgs = BP_Messages_Thread::get_current_threads_for_user($args);
		$cnt_inbox_msgs = $inbox_msgs['total'];

    	if(($cnt_inbox_msgs + $cnt_sentbox_msgs) >= $comments_check_number)
    		$is_max_comments = true;
    }

	if(!empty($is_max_comments)) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return;
    }


	$sender_user_obj = get_user_by('id', $bp_message_obj->sender_id);

	//Making a call
	$base_call_result = apbct_base_call(
		array(
			'message'         => $bp_message_obj->subject." ".$bp_message_obj->message,
			'sender_email'    => $sender_user_obj->data->user_email,
			'sender_nickname' => $sender_user_obj->data->user_login,
			'post_info'       => array(
				'comment_type' => 'buddypress_comment',
				'post_url'     => apbct_get_server_variable( 'HTTP_REFERER' ),
			),
			'js_on'   => apbct_js_test('ct_checkjs', $_COOKIE)
				? apbct_js_test('ct_checkjs', $_COOKIE)
				: apbct_js_test('ct_checkjs', $_POST),
			'sender_info'     => array('sender_url' => null),
		)
	);

    $ct_result = $base_call_result['ct_result'];

    if ($ct_result->allow == 0)
		wp_die("<h1>".__('Spam protection by CleanTalk', 'cleantalk-spam-protect')."</h1><h2>".$ct_result->comment."</h2>", '', array('response' => 403, "back_link" => true, "text_direction" => 'ltr'));
}

/**
 * Adds hiden filed to deafualt serach form
 *
 * @param $form string
 * @return string
 */
function apbct_forms__search__addField( $form ){
	global $apbct;
	if($apbct->settings['search_test'] == 1){
		$js_filed = ct_add_hidden_fields('ct_checkjs_search_default', true, false, false, false);
		$form = str_replace('</form>', $js_filed, $form);
	}
	return $form;
}

/**
 * Test default search string for spam
 *
 * @param $search string
 * @return string
 */
function apbct_forms__search__testSpam( $search ){

	global $apbct, $cleantalk_executed;

	if(
		empty($search) ||
		$cleantalk_executed ||
		$apbct->settings['search_test'] == 0 ||
		$apbct->settings['protect_logged_in'] != 1 && is_user_logged_in() // Skip processing for logged in users.
	){
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
		return $search;
	}

	if(apbct_is_user_logged_in())
		$user = wp_get_current_user();

	$base_call_result = apbct_base_call(
		array(
			'message'         => $search,
			'sender_email'    => !empty($user) ? $user->user_email : null,
			'sender_nickname' => !empty($user) ? $user->user_login : null,
			'post_info'       => array('comment_type' => 'site_search_wordpress'),
			//'js_on'           => apbct_js_test('ct_checkjs_search_default', $_GET, true),
		)
	);
	$ct_result = $base_call_result['ct_result'];

	$cleantalk_executed = true;

	if ($ct_result->allow == 0){
		die($ct_result->comment);
	}

	return $search;
}

function apbct_search_add_noindex() {

    global $apbct;

    if(
        ! is_search() || // If it is search results
        $apbct->settings['search_test'] == 0 ||
        $apbct->settings['protect_logged_in'] != 1 && is_user_logged_in() // Skip processing for logged in users.
    ){
        return ;
    }

    echo '<!-- meta by Cleantalk AntiSpam Protection plugin -->' . "\n";
    echo '<meta name="robots" content="noindex,nofollow" />' . "\n";

}

/**
 * Test woocommerce checkout form for spam
 *
 */
function ct_woocommerce_checkout_check() {

    global $apbct, $cleantalk_executed;

	//Getting request params
	$ct_temp_msg_data = ct_get_fields_any($_POST);

    $sender_email    = ($ct_temp_msg_data['email']    ? $ct_temp_msg_data['email']    : '');
    $sender_nickname = ($ct_temp_msg_data['nickname'] ? $ct_temp_msg_data['nickname'] : '');
    $subject         = ($ct_temp_msg_data['subject']  ? $ct_temp_msg_data['subject']  : '');
    $contact_form    = ($ct_temp_msg_data['contact']  ? $ct_temp_msg_data['contact']  : true);
    $message         = ($ct_temp_msg_data['message']  ? $ct_temp_msg_data['message']  : array());

    if($subject != '')
		$message = array_merge(array('subject' => $subject), $message);

	$post_info['comment_type'] = 'order';
    $post_info['post_url'] = apbct_get_server_variable( 'HTTP_REFERER' );

	//Making a call
	$base_call_result = apbct_base_call(
		array(
			'message'         => $message,
			'sender_email'    => $sender_email,
			'sender_nickname' => $sender_nickname,
			'post_info'       => $post_info,
			'js_on'           => apbct_js_test('ct_checkjs', $_COOKIE),
			'sender_info'     => array('sender_url' => null),
		)
	);

	if( $apbct->settings['wc_register_from_order'] ) {
        $cleantalk_executed = false;
    }

    $ct_result = $base_call_result['ct_result'];

    if ($ct_result->allow == 0) {
		wp_send_json(array(
			'result' => 'failure',
			'messages' => "<ul class=\"woocommerce-error\"><li>".$ct_result->comment."</li></ul>",
			'refresh' => 'false',
			'reload' => 'false'
		));
    }
}

/**
* Public function - Tests for Pirate contact froms
* return NULL
*/
function apbct_form__piratesForm__testSpam(){

	global $apbct;

	//Check for enabled option
	if( !$apbct->settings['contact_forms_test']) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return;
    }


	//Getting request params
	$ct_temp_msg_data = ct_get_fields_any($_POST);

    $sender_email    = ($ct_temp_msg_data['email']    ? $ct_temp_msg_data['email']    : '');
    $sender_nickname = ($ct_temp_msg_data['nickname'] ? $ct_temp_msg_data['nickname'] : '');
    $subject         = ($ct_temp_msg_data['subject']  ? $ct_temp_msg_data['subject']  : '');
    $contact_form    = ($ct_temp_msg_data['contact']  ? $ct_temp_msg_data['contact']  : true);
    $message         = ($ct_temp_msg_data['message']  ? $ct_temp_msg_data['message']  : array());

    if($subject != '')
		$message = array_merge(array('subject' => $subject), $message);

	$post_info['comment_type'] = 'contact_form_wordpress_feedback_pirate';
    $post_info['post_url'] = apbct_get_server_variable( 'HTTP_REFERER' );

	//Making a call
	$base_call_result = apbct_base_call(
		array(
			'message'         => $message,
			'sender_email'    => $sender_email,
			'sender_nickname' => $sender_nickname,
			'post_info'       => $post_info,
			'js_on'           => apbct_js_test('ct_checkjs', $_COOKIE),
			'sender_info'     => array('sender_url' => null),
		)
	);

    $ct_result = $base_call_result['ct_result'];

    if ($ct_result->allow == 0)
		wp_die("<h1>".__('Spam protection by CleanTalk', 'cleantalk-spam-protect')."</h1><h2>".$ct_result->comment."</h2>", '', array('response' => 403, "back_link" => true, "text_direction" => 'ltr'));
}

/**
 * Adds hidden filed to comment form
 */
function ct_comment_form($post_id){

    global $apbct;

    if (apbct_is_user_enable() === false) {
        return false;
    }

    if ( !$apbct->settings['comments_test']) {
        return false;
    }

    ct_add_hidden_fields('ct_checkjs', false, false);

    return null;
}

/**
 * Adds cookie script filed to head
 */
function apbct_hook__wp_head__set_cookie__ct_checkjs() {

    ct_add_hidden_fields('ct_checkjs', false, true, true);

    return null;
}

/**
 * Adds cookie script filed to footer
 */
function apbct_hook__wp_footer() {

    //ct_add_hidden_fields(true, 'ct_checkjs', false, true, true);

    return null;
}

/**
 * Adds hidden filed to define avaialbility of client's JavaScript
 * @param 	bool $random_key switch on generation random key for every page load
 */
function ct_add_hidden_fields($field_name = 'ct_checkjs', $return_string = false, $cookie_check = false, $no_print = false, $ajax = true) {

    global $ct_checkjs_def, $apbct;

    $ct_checkjs_key = ct_get_checkjs_value();
    $field_id_hash = md5(rand(0, 1000));

	// Using only cookies
    if ($cookie_check && $apbct->settings['set_cookies'] == 1) {
	    
		$html =	"<script type=\"text/javascript\" " . ( class_exists('Cookiebot_WP') ? 'data-cookieconsent="ignore"' : '' ) . ">
			function ctSetCookie___from_backend(c_name, value) {
				document.cookie = c_name + \"=\" + encodeURIComponent(value) + \"; path=/; samesite=lax\";
			}
			ctSetCookie___from_backend('{$field_name}', '{$ct_checkjs_key}', '{$ct_checkjs_def}');
		</script>";

	// Using AJAX to get key
    }elseif($apbct->settings['use_ajax'] && $ajax){

		// Fix only for wp_footer -> apbct_hook__wp_head__set_cookie__ct_checkjs()
		if($no_print)
			return;

        $ct_input_challenge = sprintf("'%s'", $ct_checkjs_key);
    	$field_id = $field_name . '_' . $field_id_hash;
		$html = "<input type=\"hidden\" id=\"{$field_id}\" name=\"{$field_name}\" value=\"{$ct_checkjs_def}\" />
		<script type=\"text/javascript\" " . ( class_exists('Cookiebot_WP') ? 'data-cookieconsent="ignore"' : '' ) . ">
			window.addEventListener(\"DOMContentLoaded\", function () {
				setTimeout(function(){
                    apbct_public_sendAJAX(
                        {action: \"apbct_js_keys__get\"},
                        {callback: apbct_js_keys__set_input_value, input_name: \"{$field_id}\",silent: true, no_nonce: true}
                    );
                }, 1000);
			});
		</script>";

	// Set KEY from backend
    }else{
		// Fix only for wp_footer -> apbct_hook__wp_head__set_cookie__ct_checkjs()
		if($no_print)
			return;

        $ct_input_challenge = sprintf("'%s'", $ct_checkjs_key);
    	$field_id = $field_name . '_' . $field_id_hash;
		$html = "<input type=\"hidden\" id=\"{$field_id}\" name=\"{$field_name}\" value=\"{$ct_checkjs_def}\" />
		<script type=\"text/javascript\" " . ( class_exists('Cookiebot_WP') ? 'data-cookieconsent="ignore"' : '' ) . ">
			setTimeout(function(){
				var ct_input_name = \"{$field_id}\";
				if (document.getElementById(ct_input_name) !== null) {
					var ct_input_value = document.getElementById(ct_input_name).value;
					document.getElementById(ct_input_name).value = document.getElementById(ct_input_name).value.replace(ct_input_value, {$ct_input_challenge});
				}
			}, 1000);
		</script>";
	}

    // Simplify JS code and Fixing issue with wpautop()
    $html = str_replace(array("\n","\r","\t"),'', $html);

    if ($return_string === true) {
        return $html;
    } else {
        echo $html;
    }
}

/**
* Public function - Insert JS code for spam tests
* return null;
*/
function apbct_rorm__formidable__footerScripts($fields, $form) {

    global $apbct, $ct_checkjs_frm;

    if ( !$apbct->settings['contact_forms_test'])
        return false;

    $ct_checkjs_key = ct_get_checkjs_value();
    $ct_frm_base_name = 'form_';
    $ct_frm_name = $ct_frm_base_name . $form->form_key;

    echo "var input = document.createElement('input');
    input.setAttribute('type', 'hidden');
    input.setAttribute('name', '$ct_checkjs_frm');
    input.setAttribute('value', '$ct_checkjs_key');
    for (i = 0; i < document.forms.length; i++) {
        if (typeof document.forms[i].id == 'string'){
			if(document.forms[i].id.search('$ct_frm_name') != -1) {
            document.forms[i].appendChild(input);
			}
        }
    }";

	/* Excessive cookie set
    $js_code = ct_add_hidden_fields(true, 'ct_checkjs', true, true);
    $js_code = strip_tags($js_code); // Removing <script> tag
    echo $js_code;
	//*/
}

/**
 * Public function - Test Formidable data for spam activity
 * @param $errors
 * @param $form
 *
 * @return array with errors if spam has found
 */
function apbct_rorm__formidable__testSpam ( $errors, $form ) {

    global $apbct;

    if ( !$apbct->settings['contact_forms_test']) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return $errors;
    }

    // Skip processing for logged in users.
    if ( !$apbct->settings['protect_logged_in'] && is_user_logged_in()) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return $errors;
    }

	$ct_temp_msg_data = ct_get_fields_any($_POST['item_meta']);

    $sender_email    = $ct_temp_msg_data['email']    ?: '';
    $sender_nickname = $ct_temp_msg_data['nickname'] ?: '';
    $subject         = $ct_temp_msg_data['subject']  ?: '';
    $contact_form    = $ct_temp_msg_data['contact']  ?: true;
    $message         = $ct_temp_msg_data['message']  ?: array();
    
	// Adding 'input_meta[]' to every field /Formidable fix/
    // because filed names is 'input_meta[NUM]'
    // Get all scalar values
    $tmp_message = array();
    $tmp_message2 = array();
    foreach( $message as $key => $value ){
        if( is_scalar( $value ) ){
            $tmp_message[ $key ] = $value;
        }else{
            $tmp_message2[ $key ] = $value;
        }
    }
    // Replacing key to input_meta[NUM] for scalar values
    $tmp_message = array_flip($tmp_message);
	foreach($tmp_message as &$value){
		$value = 'item_meta['.$value.']';
	} unset($value);
    $tmp_message = array_flip($tmp_message);
    // Combine it with non-scalar values
    $message = array_merge( $tmp_message, $tmp_message2 );
    
    $checkjs = apbct_js_test('ct_checkjs', $_COOKIE)
		? apbct_js_test('ct_checkjs', $_COOKIE)
		: apbct_js_test('ct_checkjs', $_POST);

    $base_call_result = apbct_base_call(
		array(
			'message'         => $message,
			'sender_email'    => $sender_email,
			'sender_nickname' => $sender_nickname,
			'post_info'       => array('comment_type' => 'contact_form_wordpress_formidable'),
			'js_on'           => $checkjs
		)
	);
    $ct_result = $base_call_result['ct_result'];

    if ($ct_result->allow == 0) {
        $errors['ct_error'] = '<br /><b>' . $ct_result->comment . '</b><br /><br />';
    }

    return $errors;
}

/**
 * Public filter 'bbp_*' - Get new topic name to global $ct_bbp_topic
 * @param 	mixed[] $comment Comment string
 * @return  mixed[] $comment Comment string
 */
function ct_bbp_get_topic($topic){
	global $ct_bbp_topic;

	$ct_bbp_topic=$topic;

	return $topic;
}

/**
 * Public filter 'bbp_*' - Checks topics, replies by cleantalk
 * @param 	mixed[] $comment Comment string
 * @return  mixed[] $comment Comment string
 */
function ct_bbp_new_pre_content ($comment) {

    global $apbct, $current_user;

    if ( !$apbct->settings['comments_test']) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return $comment;
    }

    // Skip processing for logged in users and admin.
    if ( !$apbct->settings['protect_logged_in'] && is_user_logged_in() ||
        apbct_exclusions_check()
    ) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return $comment;
    }


	$checkjs = apbct_js_test('ct_checkjs', $_COOKIE)
		? apbct_js_test('ct_checkjs', $_COOKIE)
		: apbct_js_test('ct_checkjs', $_POST);

    $post_info['comment_type'] = 'bbpress_comment';
    $post_info['post_url'] = bbp_get_topic_permalink();

	if( is_user_logged_in() ) {
		$sender_email    = $current_user->user_email;
		$sender_nickname = $current_user->display_name;
	} else {
		$sender_email    = isset($_POST['bbp_anonymous_email']) ? $_POST['bbp_anonymous_email'] : null;
		$sender_nickname = isset($_POST['bbp_anonymous_name'])  ? $_POST['bbp_anonymous_name']  : null;
	}

    $base_call_result = apbct_base_call(
		array(
			'message'         => $comment,
			'sender_email'    => $sender_email,
			'sender_nickname' => $sender_nickname,
			'post_info'       => $post_info,
			'js_on'           => $checkjs,
			'sender_info'     => array('sender_url' => isset($_POST['bbp_anonymous_website']) ? $_POST['bbp_anonymous_website'] : null),
		)
	);
    $ct_result = $base_call_result['ct_result'];

    if ($ct_result->allow == 0) {
        bbp_add_error('bbp_reply_content', $ct_result->comment);
    }

    return $comment;
}

function apbct_comment__sanitize_data__before_wp_die($function){

	global $apbct;

	$comment_data = wp_unslash($_POST);

	$user_ID              = 0;

	$comment_type         = '';

	$comment_content      = isset($comment_data['comment'])         ? (string) $comment_data['comment']                  : null;
	$comment_parent       = isset($comment_data['comment_parent'])  ? (int) absint($comment_data['comment_parent'])      : null;

	$comment_author       = isset($comment_data['author'])          ? (string) trim(strip_tags($comment_data['author'])) : null;
	$comment_author_email = isset($comment_data['email'])           ? (string) trim($comment_data['email'])              : null;
	$comment_author_url   = isset($comment_data['url'])             ? (string) trim($comment_data['url'])                : null;
	$comment_post_ID      = isset($comment_data['comment_post_ID']) ? (int) $comment_data['comment_post_ID']             : null;

	if(isset($comment_content, $comment_parent)){

		$user = function_exists('apbct_wp_get_current_user') ? apbct_wp_get_current_user() : null;

		if($user && $user->exists()){
			$comment_author       = empty($user->display_name) ? $user->user_login : $user->display_name;
			$comment_author_email = $user->user_email;
			$comment_author_url   = $user->user_url;
			$user_ID              = $user->ID;
		}

		$apbct->comment_data = compact(
			'comment_post_ID',
			'comment_author',
			'comment_author_email',
			'comment_author_url',
			'comment_content',
			'comment_type',
			'comment_parent',
			'user_ID'
		);

		$function = 'apbct_comment__check_via_wp_die';

	}

	return $function;
}

function apbct_comment__check_via_wp_die($message, $title, $args){
	if($title == __('Comment Submission Failure')){
		global $apbct;
		$apbct->validation_error = $message;
		ct_preprocess_comment($apbct->comment_data);
	}
	_default_wp_die_handler($message, $title, $args);
}

/**
 * Public filter 'preprocess_comment' - Checks comment by cleantalk server
 * @param 	mixed[] $comment Comment data array
 * @return 	mixed[] New data array of comment
 */
function ct_preprocess_comment($comment) {
    // this action is called just when WP process POST request (adds new comment)
    // this action is called by wp-comments-post.php
    // after processing WP makes redirect to post page with comment's form by GET request (see above)
    global $current_user, $comment_post_id, $ct_comment_done, $ct_jp_comments, $apbct;

	// Send email notification for chosen groups of users
	if($apbct->settings['comment_notify'] && !empty($apbct->settings['comment_notify__roles']) && $apbct->data['moderate']){

		add_filter('notify_post_author', 'apbct_comment__Wordpress__doNotify', 100, 2);

		$users = get_users(array(
			'role__in' => $apbct->settings['comment_notify__roles'],
			'fileds' => array('user_email')
		));

		if($users){
			add_filter('comment_notification_text',       'apbct_comment__Wordpress__changeMailNotificationGroups',     100, 2);
			add_filter('comment_notification_recipients', 'apbct_comment__Wordpress__changeMailNotificationRecipients', 100, 2);
			foreach($users as $user){
				$emails[] = $user->user_email;
			}
			$apbct->comment_notification_recipients = json_encode($emails);
		}
	}

	// Skip processing admin.
    if (in_array("administrator", $current_user->roles)){
	    do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
	    return $comment;
    }


   	$comments_check_number = defined('CLEANTALK_CHECK_COMMENTS_NUMBER')  ? CLEANTALK_CHECK_COMMENTS_NUMBER : 3;
   	
    if($apbct->settings['check_comments_number'] && $comment['comment_author_email']){
	   	$args = array(
			'author_email' => $comment['comment_author_email'],
    		'status' => 'approve',
    		'count' => false,
    		'number' => $comments_check_number,
    	);
    	$cnt = count(get_comments($args));
   		$is_max_comments = $cnt >= $comments_check_number ? true : false;
    }

    if (
		($comment['comment_type']!='trackback') &&
		(
			apbct_is_user_enable() === false ||
			$apbct->settings['comments_test'] == 0 ||
			$ct_comment_done ||
			(isset($_SERVER['HTTP_REFERER']) && stripos($_SERVER['HTTP_REFERER'],'page=wysija_campaigns&action=editTemplate')!==false) ||
			(isset($is_max_comments) && $is_max_comments) ||
			(isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['REQUEST_URI'],'/wp-admin/')!==false)
		)
	)
	{
		do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return $comment;
    }

    $local_blacklists = apbct_wp_blacklist_check(
        $comment['comment_author'],
        $comment['comment_author_email'],
        $comment['comment_author_url'],
        $comment['comment_content'],
        apbct_get_server_variable( 'REMOTE_ADDR' ),
        apbct_get_server_variable( 'HTTP_USER_AGENT' )
    );

    // Go out if author in local blacklists
    if ($comment['comment_type']!='trackback' && $local_blacklists === true) {
	    do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return $comment;
    }

    // Skip pingback anti-spam test
    /*if ($comment['comment_type'] == 'pingback') {
        return $comment;
    }*/

    $ct_comment_done = true;

    $comment_post_id = $comment['comment_post_ID'];

    // JetPack comments logic
    $post_info['comment_type'] = $ct_jp_comments ? 'jetpack_comment' : $comment['comment_type'];
    $post_info['post_url'] = ct_post_url(null, $comment_post_id);

	// Comment type
	$post_info['comment_type'] = empty($post_info['comment_type']) ? 'general_comment' : $post_info['comment_type'];

	$checkjs = apbct_js_test('ct_checkjs', $_COOKIE)
		? apbct_js_test('ct_checkjs', $_COOKIE)
		: apbct_js_test('ct_checkjs', $_POST);


    $example = null;
    if ($apbct->data['relevance_test']) {
        $post = get_post($comment_post_id);
        if ($post !== null){
            $example['title'] = $post->post_title;
            $example['body'] = $post->post_content;
            $example['comments'] = null;

            $last_comments = get_comments(array('status' => 'approve', 'number' => 10, 'post_id' => $comment_post_id));
            foreach ($last_comments as $post_comment){
                $example['comments'] .= "\n\n" . $post_comment->comment_content;
            }

            $example = json_encode($example);
        }

        // Use plain string format if've failed with JSON
        if ($example === false || $example === null){
            $example = ($post->post_title !== null) ? $post->post_title : '';
            $example .= ($post->post_content !== null) ? "\n\n" . $post->post_content : '';
        }
    }

    $base_call_result = apbct_base_call(
		array(
			'message'         => $comment['comment_content'],
			'example'         => $example,
			'sender_email'    => $comment['comment_author_email'],
			'sender_nickname' => $comment['comment_author'],
			'post_info'       => $post_info,
			'js_on'           => $checkjs,
			'sender_info'     => array(
				'sender_url' => @$comment['comment_author_url'],
				'form_validation' => !isset($apbct->validation_error)
					? null
					: json_encode(array(
						'validation_notice' => $apbct->validation_error,
						'page_url' => apbct_get_server_variable( 'HTTP_HOST' ) . apbct_get_server_variable( 'REQUEST_URI' ),
					))
			),
		)
	);
    $ct_result = $base_call_result['ct_result'];

	ct_hash($ct_result->id);

	//Don't check trusted users
	if (isset($comment['comment_author_email'])){
		$approved_comments = get_comments(array('status' => 'approve', 'count' => true, 'author_email' => $comment['comment_author_email']));
		$new_user = $approved_comments == 0 ? true : false;
	}

    // Change comment flow only for new authors
	if (!empty($new_user) || $ct_result->stop_words !== null || $ct_result->spam == 1)
		add_action('comment_post', 'ct_set_meta', 10, 2);

	if($ct_result->allow){ // Pass if allowed
		if(get_option('comment_moderation') === '1') // Wordpress moderation flag
			add_filter('pre_comment_approved', 'ct_set_not_approved', 999, 2);
		else
			add_filter('pre_comment_approved', 'ct_set_approved', 999, 2);
		// Modify the email notification
        add_filter('comment_notification_text', 'apbct_comment__wordpress__show_blacklists', 100, 2); // Add two blacklist links: by email and IP
	}else{

		global $ct_comment, $ct_stop_words;

		$ct_comment = $ct_result->comment;
		$ct_stop_words = $ct_result->stop_words;

		$err_text = '<center>' . ((defined('CLEANTALK_DISABLE_BLOCKING_TITLE') && CLEANTALK_DISABLE_BLOCKING_TITLE == true) ? '' : '<b style="color: #49C73B;">Clean</b><b style="color: #349ebf;">Talk.</b> ') . __('Spam protection', 'cleantalk-spam-protect') . "</center><br><br>\n" . $ct_result->comment;
		if( ! $ct_jp_comments ) {
            $err_text .= '<script>setTimeout("history.back()", 5000);</script>';
        }

		// Terminate. Definitely spam.
		if($ct_result->stop_queue == 1)
			wp_die($err_text, 'Blacklisted', array('response' => 200, 'back_link' => ! $ct_jp_comments ));

		// Terminate by user's setting.
		if($ct_result->spam == 3)
			wp_die($err_text, 'Blacklisted', array('response' => 200, 'back_link' => ! $ct_jp_comments));

		// Trash comment.
		if($ct_result->spam == 2){
			add_filter('pre_comment_approved', 'ct_set_comment_spam', 997, 2);
			add_action('comment_post', 'ct_wp_trash_comment', 997, 2);
		}

		// Spam comment
		if($ct_result->spam == 1)
			add_filter('pre_comment_approved', 'ct_set_comment_spam', 997, 2);

		// Move to pending folder. Contains stop_words.
		if($ct_result->stop_words){
			add_filter('pre_comment_approved', 'ct_set_not_approved', 998, 2);
			add_action('comment_post', 'ct_mark_red', 998, 2);
		}

		add_action('comment_post', 'ct_die', 999, 2);
	}

	if($apbct->settings['remove_comments_links'] == 1){
		$comment['comment_content'] = preg_replace("~(http|https|ftp|ftps)://(.*?)(\s|\n|[,.?!](\s|\n)|$)~", '[Link deleted]', $comment['comment_content']);
	}

	// Change mail notification if license is out of date
	if($apbct->data['moderate'] == 0){
		$apbct->sender_email = $comment['comment_author_email'];
		$apbct->sender_ip    = \Cleantalk\ApbctWP\Helper::ip__get('real');
		add_filter('comment_moderation_text',   'apbct_comment__Wordpress__changeMailNotification', 100, 2); // Comment sent to moderation
		add_filter('comment_notification_text', 'apbct_comment__Wordpress__changeMailNotification', 100, 2); // Comment approved
	}

    return $comment;
}

/**
 * Changes whether notify admin/athor or not.
 *
 * @param bool $maybe_notify notify flag
 * @param int $comment_ID Comment id
 * @return bool flag
 */
function apbct_comment__Wordpress__doNotify($maybe_notify, $comment_ID){
	return true;
}

/**
 * Add notification setting link
 *
 * @param string  $notify_message
 * @param integer $comment_id
 *
 * @return string
 */
function apbct_comment__Wordpress__changeMailNotificationGroups($notify_message, $comment_id){
	return $notify_message
		.PHP_EOL
		.'---'.PHP_EOL
		.'Manage notifications settings: '.get_site_url().'/wp-admin/options-general.php?page=cleantalk';
}

/**
 * Change email notification recipients
 *
 * @param array      $emails
 * @param integer    $comment_id
 *
 * @return array
 * @global SpbcState $apbct
 */
function apbct_comment__Wordpress__changeMailNotificationRecipients($emails, $comment_id){
	global $apbct;
	return array_unique(array_merge($emails, (array)json_decode($apbct->comment_notification_recipients, true)));
}

/**
 * Changes email notification for spam comment for native Wordpress comment system
 *
 * @param string $notify_message Body of email notification
 * @param int $comment_id Comment id
 * @return string Body for email notification
 */
function apbct_comment__Wordpress__changeMailNotification($notify_message, $comment_id){

	global $apbct;

	$notify_message =
		PHP_EOL
		.__('CleanTalk AntiSpam: This message is possible spam.', 'cleantalk-spam-protect')
		."\n".__('You could check it in CleanTalk\'s anti-spam database:', 'cleantalk-spam-protect')
		."\n".'IP: https://cleantalk.org/blacklists/' . $apbct->sender_ip
		."\n".'Email: https://cleantalk.org/blacklists/' . $apbct->sender_email
		."\n".PHP_EOL . sprintf(
			__('Activate protection in your Anti-Spam Dashboard: %s.', 'clentalk'),
			'https://cleantalk.org/my/?cp_mode=antispam&utm_source=newsletter&utm_medium=email&utm_campaign=wp_spam_comment_passed'
			.($apbct->data['user_token']
				? '&iser_token='.$apbct->data['user_token']
				: ''
			)
		)
		.PHP_EOL . '---'
		.PHP_EOL
		.PHP_EOL
		.$notify_message;

	return $notify_message;

}

function apbct_comment__wordpress__show_blacklists( $notify_message, $comment_id ) {

    $comment_details = get_comments( array( 'comment__in' => $comment_id ) );
    $comment_details = $comment_details[0];

    if( isset( $comment_details->comment_author_email ) ) {

        $black_list_link = 'https://cleantalk.org/blacklists/';

        $links = PHP_EOL;
        $links .= esc_html__( 'Check for spam:', 'cleantalk-spam-protect');
        $links .= PHP_EOL;
        $links .= $black_list_link . $comment_details->comment_author_email;
        $links .= PHP_EOL;
        if( ! empty( $comment_details->comment_author_IP ) ) {
            $links .= $black_list_link . $comment_details->comment_author_IP;
            $links .= PHP_EOL;
        }

        return $notify_message . $links;

    }

    return $notify_message;

}

/**
 * Set die page with Cleantalk comment.
 * @global array $ct_comment
    $err_text = '<center><b style="color: #49C73B;">Clean</b><b style="color: #349ebf;">Talk.</b> ' . __('Spam protection', 'cleantalk-spam-protect') . "</center><br><br>\n" . $ct_comment;
 * @param type $comment_status
 */
function ct_die($comment_id, $comment_status) {

    global $ct_comment, $ct_jp_comments;

    do_action( 'apbct_pre_block_page', $ct_comment );

	$err_text = '<center>' . ((defined('CLEANTALK_DISABLE_BLOCKING_TITLE') && CLEANTALK_DISABLE_BLOCKING_TITLE == true) ? '' : '<b style="color: #49C73B;">Clean</b><b style="color: #349ebf;">Talk.</b> ') . __('Spam protection', 'cleantalk-spam-protect') . "</center><br><br>\n" . $ct_comment;
        if( ! $ct_jp_comments ) {
            $err_text .= '<script>setTimeout("history.back()", 5000);</script>';
        }
        if(isset($_POST['et_pb_contact_email']))
        {
        	$mes='<div id="et_pb_contact_form_1" class="et_pb_contact_form_container clearfix"><h1 class="et_pb_contact_main_title">Blacklisted</h1><div class="et-pb-contact-message"><p>'.$ct_comment.'</p></div></div>';
        	wp_die($mes, 'Blacklisted', array('back_link' => true,'response'=>200));
        }
        else
        {
        	wp_die($err_text, 'Blacklisted', array('response' => 200, 'back_link' => ! $ct_jp_comments));
        }
}

/**
 * Set die page with Cleantalk comment from parameter.
 * @param type $comment_body
 */
function ct_die_extended($comment_body) {

    global $ct_jp_comments;

	$err_text = '<center>' . ((defined('CLEANTALK_DISABLE_BLOCKING_TITLE') && CLEANTALK_DISABLE_BLOCKING_TITLE == true) ? '' : '<b style="color: #49C73B;">Clean</b><b style="color: #349ebf;">Talk.</b> ') . __('Spam protection', 'cleantalk-spam-protect') . "</center><br><br>\n" . $comment_body;
    if( ! $ct_jp_comments ) {
        $err_text .= '<script>setTimeout("history.back()", 5000);</script>';
    }
    wp_die($err_text, 'Blacklisted', array('response' => 200, 'back_link' => ! $ct_jp_comments));
}

/**
 * Validates JavaScript anti-spam test
 *
 * @param string $field_name filed to serach in data
 * @param null   $data Data to search in
 * @param bool   $random_key
 *
 * @return int|null
 */
function apbct_js_test($field_name = 'ct_checkjs', $data = null) {

    global $apbct;

    $out = null;

    if($data && isset($data[$field_name])){

	    $js_key = trim($data[$field_name]);

	    // Check static key
	    if(
		    $apbct->settings['use_static_js_key'] == 1 ||
		    ( $apbct->settings['use_static_js_key'] == - 1 &&
		      ( apbct_is_cache_plugins_exists() ||
		        ( apbct_is_post() && isset($apbct->data['cache_detected']) && $apbct->data['cache_detected'] == 1 )
		      )
		    )
	    ){
		    $out = ct_get_checkjs_value() === $js_key ? 1 : 0;

	    // Random key check
	    }else{
		    $out = array_key_exists( $js_key, $apbct->js_keys ) ? 1 : 0;
	    }
    }

    return $out;
}

/**
 * Get post url
 * @param int $comment_id
 * @param int $comment_post_id
 * @return string|bool
 */
function ct_post_url($comment_id = null, $comment_post_id) {

    if (empty($comment_post_id))
	return null;

    if ($comment_id === null) {
	    $last_comment = get_comments('number=1');
	    $comment_id = isset($last_comment[0]->comment_ID) ? (int) $last_comment[0]->comment_ID + 1 : 1;
    }
    $permalink = get_permalink($comment_post_id);

    $post_url = null;
    if ($permalink !== null)
	$post_url = $permalink . '#comment-' . $comment_id;

    return $post_url;
}

/**
 * Public filter 'pre_comment_approved' - Mark comment unapproved always
 * @return 	int Zero
 */
function ct_set_not_approved() {
    return 0;
}

/**
 * @author Artem Leontiev
 * Public filter 'pre_comment_approved' - Mark comment approved if it's not 'spam' only
 * @return 	int 1
 */
function ct_set_approved($approved, $comment) {
    if ($approved == 'spam'){
        return $approved;
    } else {
        return 1;
    }
}

/**
 * Public filter 'pre_comment_approved' - Mark comment unapproved always
 * @return 	int Zero
 */
function ct_set_comment_spam() {
    return 'spam';
}

/**
 * Public action 'comment_post' - Store cleantalk hash in comment meta 'ct_hash'
 * @param	int $comment_id Comment ID
 * @param	mixed $comment_status Approval status ("spam", or 0/1), not used
 */
function ct_set_meta($comment_id, $comment_status) {
    global $comment_post_id;
    $hash1 = ct_hash();
    if (!empty($hash1)) {
        update_comment_meta($comment_id, 'ct_hash', $hash1);
        if (function_exists('base64_encode') && isset($comment_status) && $comment_status != 'spam') {
			$post_url = ct_post_url($comment_id, $comment_post_id);
			$post_url = base64_encode($post_url);
			if ($post_url === false)
			return false;
			// 01 - URL to approved comment
			$feedback_request = $hash1 . ':' . '01' . ':' . $post_url . ';';
			ct_send_feedback($feedback_request);
		}
    }
    return true;
}

/**
 * Mark bad words
 * @global string $ct_stop_words
 * @param int $comment_id
 * @param int $comment_status Not use
 */
function ct_mark_red($comment_id, $comment_status) {
    global $ct_stop_words;

    $comment = get_comment($comment_id, 'ARRAY_A');
    $message = $comment['comment_content'];
    foreach (explode(':', $ct_stop_words) as $word) {
        $message = preg_replace("/($word)/ui", '<font rel="cleantalk" color="#FF1000">' . "$1" . '</font>', $message);

    }
    $comment['comment_content'] = $message;
    kses_remove_filters();
    wp_update_comment($comment);
}

//
//Send post to trash
//
function ct_wp_trash_comment($comment_id, $comment_status){
	wp_trash_comment($comment_id);
}

/**
	* Tests plugin activation status
	* @return bool
*/
function ct_plugin_active($plugin_name){
	foreach (get_option('active_plugins') as $k => $v) {
	    if ($plugin_name == $v)
		    return true;
	}
	return false;
}

/**
 * Insert a hidden field to registration form
 * @return null
 */
function ct_register_form() {

	global $ct_checkjs_register_form, $apbct;

    if ($apbct->settings['registrations_test'] == 0) {
        return false;
    }

    ct_add_hidden_fields($ct_checkjs_register_form, false, false, false, false);

    return null;
}

function apbct_login__scripts(){
    global $apbct;

    // Differnt JS params
    wp_enqueue_script( 'ct_public', APBCT_URL_PATH . '/js/apbct-public.min.js', array( 'jquery' ), APBCT_VERSION, false /*in header*/ );
	wp_enqueue_script('cleantalk-modal', plugins_url( '/cleantalk-spam-protect/js/cleantalk-modal.min.js' ),   array(),     APBCT_VERSION, false );

    wp_localize_script('ct_public', 'ctPublic', array(
        '_ajax_nonce' => wp_create_nonce('ct_secret_stuff'),
        '_ajax_url'   => admin_url('admin-ajax.php'),
    ));

    $apbct->public_script_loaded = true;
}

/**
 * Adds notification text to login form - to inform about approved registration
 * @return null
 */
function ct_login_message($message) {

    global $errors, $apbct, $apbct_cookie_register_ok_label;
    
    if ($apbct->settings['registrations_test'] != 0){
        if( isset($_GET['checkemail']) && 'registered' == $_GET['checkemail'] ){
			if (isset($_COOKIE[$apbct_cookie_register_ok_label])){
				if(is_wp_error($errors)){
					$errors->add('ct_message',sprintf(__('Registration approved by %s.', 'cleantalk-spam-protect'), '<b style="color: #49C73B;">Clean</b><b style="color: #349ebf;">Talk</b>'), 'message');
				}
			}
        }
    }
    return $message;
}

/**
 * Test users registration for pPress
 * @return array with errors
 */
function ct_registration_errors_ppress($reg_errors, $form_id) {

	$email = $_POST['reg_email'];
	$login = $_POST['reg_username'];

	$reg_errors = ct_registration_errors($reg_errors, $login, $email);

    return $reg_errors;
}

/**
 * Test users registration for multisite enviroment
 * @return array with errors
 */
function ct_registration_errors_wpmu($errors) {
    global $ct_signup_done;

    //
    // Multisite actions
    //
    $sanitized_user_login = null;
    if (isset($errors['user_name'])) {
        $sanitized_user_login = $errors['user_name'];
        $wpmu = true;
    }
    $user_email = null;
    if (isset($errors['user_email'])) {
        $user_email = $errors['user_email'];
        $wpmu = true;
    }

    if ($wpmu && isset($errors['errors']->errors) && count($errors['errors']->errors) > 0) {
        return $errors;
    }

    $errors['errors'] = ct_registration_errors($errors['errors'], $sanitized_user_login, $user_email);

    // Show CleanTalk errors in user_name field
    if (isset($errors['errors']->errors['ct_error'])) {
        $errors['errors']->errors['user_name'] = $errors['errors']->errors['ct_error'];
        unset($errors['errors']->errors['ct_error']);
     }

    return $errors;
}

/**
 *  Shell for action register_post
 * @return array with errors
 */
function ct_register_post($sanitized_user_login = null, $user_email = null, $errors) {
    return ct_registration_errors($errors, $sanitized_user_login, $user_email);
}

/**
 * Check messages for external plugins
 * @return array with checking result;
 */

function ct_test_message($nickname, $email, $ip, $text){

    $base_call_result = apbct_base_call(
		array(
			'message'         => $text,
			'sender_email'    => $email,
			'sender_nickname' => $nickname,
			'post_info'       => array('comment_type' => 'feedback_plugin_check'),
			'js_on'           => apbct_js_test('ct_checkjs', $_COOKIE),
		)
	);

    $ct_result = $base_call_result['ct_result'];

    $result=Array(
        'allow' => $ct_result->allow,
        'comment' => $ct_result->comment,
    );
    return $result;
}

/**
 * Check registrations for external plugins
 * @return array with checking result;
 */
function ct_test_registration($nickname, $email, $ip = null){

    global $ct_checkjs_register_form, $apbct;

    if(apbct_js_test($ct_checkjs_register_form, $_POST)){
		$checkjs =  apbct_js_test($ct_checkjs_register_form, $_POST);
		$sender_info['post_checkjs_passed'] = $checkjs;
	}else{
		$checkjs =  $checkjs = apbct_js_test('ct_checkjs', $_COOKIE);
        $sender_info['cookie_checkjs_passed'] = $checkjs;
	}

	//Making a call
	$base_call_result = apbct_base_call(
		array(
			'sender_ip'       => $ip,
			'sender_email'    => $email,
			'sender_nickname' => $nickname,
			'sender_info'     => $sender_info,
			'js_on'           => $checkjs,
		),
		true
	);
	$ct_result = $base_call_result['ct_result'];

    $result = array(
        'allow' => $ct_result->allow,
        'comment' => $ct_result->comment,
    );
    return $result;
}

/**
 * Test users registration
 *
 * @param      $errors
 * @param null $sanitized_user_login
 * @param null $user_email
 *
 * @return void with errors
 */
function ct_registration_errors($errors, $sanitized_user_login = null, $user_email = null) {

    global $ct_checkjs_register_form, $apbct_cookie_request_id_label, $apbct_cookie_register_ok_label, $apbct_cookie_request_id, $bp, $ct_signup_done, $ct_negative_comment, $apbct, $ct_registration_error_comment, $cleantalk_executed;

    // Go out if a registrered user action
    if (apbct_is_user_enable() === false) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return $errors;
    }

    if ($apbct->settings['registrations_test'] == 0) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return $errors;
    }

    // The function already executed
    // It happens when used ct_register_post();
    if ($ct_signup_done && is_object($errors) && count($errors->errors) > 0) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return $errors;
    }

	// Facebook registration
    if ($sanitized_user_login === null && isset($_POST['FB_userdata'])){
        $sanitized_user_login = $_POST['FB_userdata']['name'];
        $facebook = true;
    }
    if ($user_email === null && isset($_POST['FB_userdata'])){
    	$user_email = $_POST['FB_userdata']['email'];
    	$facebook = true;
    }

    // BuddyPress actions
    $buddypress = false;
    if ($sanitized_user_login === null && isset($_POST['signup_username'])) {
        $sanitized_user_login = $_POST['signup_username'];
        $buddypress = true;
    }
    if ($user_email === null && isset($_POST['signup_email'])) {
        $user_email = $_POST['signup_email'];
        $buddypress = true;
    }

    //
    // Break tests because we already have servers response
    //
    if ($buddypress && $ct_signup_done) {
        if ($ct_negative_comment) {
            $bp->signup->errors['signup_username'] = $ct_negative_comment;
        }
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return $errors;
    }


    if(current_filter() == 'woocommerce_registration_errors'){
	    $checkjs = apbct_js_test('ct_checkjs', $_COOKIE);
	    $checkjs_post   = null;
	    $checkjs_cookie = $checkjs;
    }else{
	    // This hack can be helpfull when plugin uses with untested themes&signups plugins.
	    $checkjs_post   = apbct_js_test($ct_checkjs_register_form, $_POST);
	    $checkjs_cookie = apbct_js_test('ct_checkjs', $_COOKIE);
	    $checkjs = $checkjs_cookie ? $checkjs_cookie : $checkjs_post;
    }

	$sender_info = array(
		'post_checkjs_passed'   => $checkjs_post,
		'cookie_checkjs_passed' => $checkjs_cookie,
		'form_validation'       => ! empty( $errors )
			? json_encode( array(
				'validation_notice' => $errors->get_error_message(),
				'page_url'          => apbct_get_server_variable( 'HTTP_HOST' ) . apbct_get_server_variable( 'REQUEST_URI' ),
			) )
			: null,
	);

	$base_call_result = apbct_base_call(
		array(
			'sender_email'    => $user_email,
			'sender_nickname' => $sanitized_user_login,
			'sender_info'     => $sender_info,
			'js_on'           => $checkjs,
		),
		true
	);
	$ct_result = $base_call_result['ct_result'];

	// Change mail notification if license is out of date
	if($apbct->data['moderate'] == 0 &&
		($ct_result->fast_submit == 1 || $ct_result->blacklisted == 1 || $ct_result->js_disabled == 1)
	){
		$apbct->sender_email = $user_email;
		$apbct->sender_ip    = \Cleantalk\ApbctWP\Helper::ip__get('real');
		add_filter('wp_new_user_notification_email_admin', 'apbct_registration__Wordpress__changeMailNotification', 100, 3);
	}

    $ct_signup_done = true;

    $ct_result = ct_change_plugin_resonse($ct_result, $checkjs);

	$cleantalk_executed = true;

    if ($ct_result->inactive != 0) {
        ct_send_error_notice($ct_result->comment);
        return $errors;
    }

    if ($ct_result->allow == 0) {

        if ($buddypress === true) {
            $bp->signup->errors['signup_username'] = $ct_result->comment;
		}elseif(!empty($facebook)){
        	$_POST['FB_userdata']['email'] = '';
        	$_POST['FB_userdata']['name'] = '';
        	return;
        }elseif(defined('MGM_PLUGIN_NAME')) {
        	ct_die_extended($ct_result->comment);
        }else{
			if(is_wp_error($errors))
				$errors->add('ct_error', $ct_result->comment);
				$ct_negative_comment = $ct_result->comment;
        }

		$ct_registration_error_comment = $ct_result->comment;

    } else {
        if ($ct_result->id !== null) {
	        $apbct_cookie_request_id = $ct_result->id;
            \Cleantalk\Common\Helper::apbct_cookie__set($apbct_cookie_register_ok_label, $ct_result->id, time()+10, '/');
            \Cleantalk\Common\Helper::apbct_cookie__set($apbct_cookie_request_id_label,  $ct_result->id, time()+10, '/');
        }
    }

    return $errors;
}

/**
 * Changes email notification for newly registred user
 *
 * @param string $wp_new_user_notification_email_admin Body of email notification
 * @param array $user User inof
 * @param string $blogname Blog name
 * @return string Body for email notification
 */
function apbct_registration__Wordpress__changeMailNotification($wp_new_user_notification_email_admin, $user, $blogname){

	global $apbct;

	$wp_new_user_notification_email_admin['message'] = PHP_EOL
		.__('CleanTalk AntiSpam: This registration is spam.', 'cleantalk-spam-protect')
		."\n" . __('CleanTalk\'s anti-spam database:', 'cleantalk-spam-protect')
		."\n" . 'IP: '    . $apbct->sender_ip
		."\n" . 'Email: ' . $apbct->sender_email
		.PHP_EOL . PHP_EOL .
			__('Activate protection in your Anti-Spam Dashboard: ', 'clentalk')
			.'https://cleantalk.org/my/?cp_mode=antispam&utm_source=newsletter&utm_medium=email&utm_campaign=wp_spam_registration_passed'
			.($apbct->data['user_token']
				? '&iser_token='.$apbct->data['user_token']
				: ''
			)
		.PHP_EOL . '---'
		.PHP_EOL
		.$wp_new_user_notification_email_admin['message'];

	return $wp_new_user_notification_email_admin;


}

/**
 * Checks Ultimate Members registration for spam
 *
 * @param $args forms arguments with names and values
 *
 * @return mixed
 *
 */
function apbct_registration__UltimateMembers__check( $args ){

    if ( isset( UM()->form()->errors ) ) {
        $sender_info['previous_form_validation'] = true;
        $sender_info['validation_notice'] = json_encode( UM()->form()->errors );
    }

	global $apbct, $cleantalk_executed;

	if ($apbct->settings['registrations_test'] == 0) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return $args;
    }


	$checkjs = apbct_js_test('ct_checkjs_register_form', $args);
	$sender_info['post_checkjs_passed'] = $checkjs;

	// This hack can be helpfull when plugin uses with untested themes&signups plugins.
	if ($checkjs == 0) {
		$checkjs = apbct_js_test('ct_checkjs', $_COOKIE);
		$sender_info['cookie_checkjs_passed'] = $checkjs;
	}

	$base_call_result = apbct_base_call(
		array(
			'sender_email' => $args['user_email'],
			'sender_nickname' => $args['user_login'],
			'sender_info' => $sender_info,
			'js_on'   => $checkjs,
		),
		true
	);
	$ct_result = $base_call_result['ct_result'];

	$cleantalk_executed = true;

	if ($ct_result->inactive != 0) {
		ct_send_error_notice($ct_result->comment);
		return $args;
	}

	if ($ct_result->allow == 0)
		UM()->form()->add_error('user_password', $ct_result->comment );

	return $args;
}

/**
 * Checks registration error and set it if it was dropped
 * @return errors
 */
function ct_check_registration_erros($errors, $sanitized_user_login = null, $user_email = null) {
	global $bp, $ct_registration_error_comment;

	if($ct_registration_error_comment){

		if(isset($bp))
			if(method_exists($bp, 'signup'))
				if(method_exists($bp->signup, 'errors'))
					if(isset($bp->signup->errors['signup_username']))
						if($bp->signup->errors['signup_username'] != $ct_registration_error_comment)
							$bp->signup->errors['signup_username'] = $ct_registration_error_comment;

		if(isset($errors))
			if(method_exists($errors, 'errors'))
				if(isset($errors->errors['ct_error']))
					if($errors->errors['ct_error'][0] != $ct_registration_error_comment)
						$errors->add('ct_error', $ct_registration_error_comment);

	}
	return $errors;
}

/**
 * Set user meta (ct_hash) for successed registration
 * @return null
 */
function apbct_user_register($user_id) {

    global $apbct_cookie_request_id_label, $apbct_cookie_request_id;

	if ( ! empty( $apbct_cookie_request_id ) ) {
		update_user_meta($user_id, 'ct_hash', $apbct_cookie_request_id);
		return;
	}

	if ( isset($_COOKIE[$apbct_cookie_request_id_label]) ) {
	    if(update_user_meta($user_id, 'ct_hash', $_COOKIE[$apbct_cookie_request_id_label])){
	        \Cleantalk\Common\Helper::apbct_cookie__set($apbct_cookie_request_id_label, '0', 1, '/');
			}
	    return;
	}

}


/**
 * Test for JetPack contact form
 */
function ct_grunion_contact_form_field_html($r, $field_label) {
	
    global $ct_checkjs_jpcf, $ct_jpcf_patched, $ct_jpcf_fields, $apbct;
    
    if ($apbct->settings['contact_forms_test'] == 1 && $ct_jpcf_patched === false && preg_match( "/(text|email)/i", $r)) {

        // Looking for element name prefix
        $name_patched = false;
        foreach ($ct_jpcf_fields as $v) {
            if ($name_patched === false && preg_match("/(g\d-)$v/", $r, $matches)) {
                $ct_checkjs_jpcf = $matches[1] . $ct_checkjs_jpcf;
                $name_patched = true;
            }
        }

        $r .= ct_add_hidden_fields($ct_checkjs_jpcf, true);
        $ct_jpcf_patched = true;
    }

    return $r;
}
/**
 * Test for JetPack contact form
 */
function ct_contact_form_is_spam($form) {

	global $ct_checkjs_jpcf, $apbct;

    if ($apbct->settings['contact_forms_test'] == 0) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return null;
    }

    $js_field_name = $ct_checkjs_jpcf;
    foreach ($_POST as $k => $v) {
        if (preg_match("/^.+$ct_checkjs_jpcf$/", $k))
           $js_field_name = $k;
    }

    $sender_email = null;
    $sender_nickname = null;
    $message = '';
    if (isset($form['comment_author_email']))
        $sender_email = $form['comment_author_email'];

    if (isset($form['comment_author']))
        $sender_nickname = $form['comment_author'];

    if (isset($form['comment_content']))
        $message = $form['comment_content'];

    $base_call_result = apbct_base_call(
		array(
			'message'         => $message,
			'sender_email'    => $sender_email,
			'sender_nickname' => $sender_nickname,
			'post_info'       => array('comment_type' => 'contact_form_wordpress_grunion'),
			'sender_info'     => array('sender_url' => @$form['comment_author_url']),
			'js_on'           => apbct_js_test($js_field_name, $_POST),
		)
	);
    $ct_result = $base_call_result['ct_result'];

    if ($ct_result->allow == 0) {
        global $ct_comment;
        $ct_comment = $ct_result->comment;
        ct_die(null, null);
        exit;
    }

    return (bool) !$ct_result->allow;
}

function ct_contact_form_is_spam_jetpack($is_spam,$form) {
    global $ct_checkjs_jpcf, $apbct;

    if ($apbct->settings['contact_forms_test'] == 0) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return null;
    }

    $js_field_name = $ct_checkjs_jpcf;
    foreach ($_POST as $k => $v) {
        if (preg_match("/^.+$ct_checkjs_jpcf$/", $k))
           $js_field_name = $k;
    }

    $base_call_result = apbct_base_call(
		array(
			'message'         => isset($form['comment_content'])      ? $form['comment_content']       : '',
			'sender_email'    => isset($form['comment_author_email']) ? $form['comment_author_email']  : null,
			'sender_nickname' => isset($form['comment_author'])       ? $form['comment_author']        : null,
			'post_info'       => array('comment_type' => 'contact_form_wordpress_grunion'),
			'sender_info'     => array('sender_url' => @$form['comment_author_url']),
		)
	);
    $ct_result = $base_call_result['ct_result'];

    if ($ct_result->allow == 0) {
        global $ct_comment;
        $ct_comment = $ct_result->comment;
        ct_die(null, null);
        exit;
    }

    return (bool) !$ct_result->allow;
}

/**
 * Inserts anti-spam hidden to WP Maintenance Mode (wpmm)
 */
function apbct_form__wpmm__addField(){
	ct_add_hidden_fields('ct_checkjs', false, true, true);
}

/**
 * Inserts anti-spam hidden to CF7
 */
function apbct_form__contactForm7__addField($html) {
    global $ct_checkjs_cf7, $apbct;



    if ($apbct->settings['contact_forms_test'] == 0) {
        return $html;
    }

    $html .= ct_add_hidden_fields($ct_checkjs_cf7, true);

    return $html;
}

/**
 * Test spam for Contact Fomr 7 (CF7) right before validation
 *
 * @global SpbcState $apbct
 * @param type $result
 * @param type $tags
 * @return type
 */
function apbct_form__contactForm7__tesSpam__before_validate($result = null, $tags = null) {
	global $apbct;

	if ($result && method_exists($result, 'get_invalid_fields')){
		$invalid_fields = $result->get_invalid_fields();
		if(!empty($invalid_fields) && is_array($invalid_fields)){
			$apbct->validation_error = $invalid_fields[key($invalid_fields)]['reason'];
            apbct_form__contactForm7__testSpam( false, null );
		}
	}

	return $result;
}

/**
 * Test CF7 message for spam
 */
function apbct_form__contactForm7__testSpam($spam, $submission ) {

    global $ct_checkjs_cf7, $apbct;

	if(
		$apbct->settings['contact_forms_test'] == 0 ||
		$spam == false && WPCF7_VERSION < '3.0.0'  ||
		$spam === true && WPCF7_VERSION >= '3.0.0' ||
		$apbct->settings['protect_logged_in'] != 1 && is_user_logged_in() || // Skip processing for logged in users.
		apbct_exclusions_check__url() ||
		apbct_exclusions_check__ip() ||
		isset($apbct->cf7_checked)
	){
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
		return $spam;
	}

	$checkjs = apbct_js_test($ct_checkjs_cf7, $_POST)
		? apbct_js_test($ct_checkjs_cf7, $_POST)
		: apbct_js_test('ct_checkjs', $_COOKIE);

	$ct_temp_msg_data = ct_get_fields_any($_POST);

    $sender_email    = ($ct_temp_msg_data['email']    ? $ct_temp_msg_data['email']    : '');
    $sender_nickname = ($ct_temp_msg_data['nickname'] ? $ct_temp_msg_data['nickname'] : '');
    $subject         = ($ct_temp_msg_data['subject']  ? $ct_temp_msg_data['subject']  : '');
    $contact_form    = ($ct_temp_msg_data['contact']  ? $ct_temp_msg_data['contact']  : true);
    $message         = ($ct_temp_msg_data['message']  ? $ct_temp_msg_data['message']  : array());
    if ($subject != '') {
        $message = array_merge(array('subject' => $subject), $message);
    }

    $base_call_result = apbct_base_call(
		array(
			'message'         => $message,
			'sender_email'    => $sender_email,
			'sender_nickname' => $sender_nickname,
			'js_on'           => $checkjs,
			'post_info'       => array('comment_type' => 'contact_form_wordpress_cf7'),
			'sender_info'     => array(
				'form_validation' => !isset($apbct->validation_error)
					? null
					: json_encode(array(
						'validation_notice' => $apbct->validation_error,
						'page_url' => apbct_get_server_variable( 'HTTP_HOST' ) . apbct_get_server_variable( 'REQUEST_URI' ),
					))
			),
		)
	);

    $ct_result = $base_call_result['ct_result'];

	// Change mail notification if license is out of date
	if($apbct->data['moderate'] == 0 &&
		($ct_result->fast_submit == 1 || $ct_result->blacklisted == 1 || $ct_result->js_disabled == 1)
	){
		$apbct->sender_email = $sender_email;
		$apbct->sender_ip    = \Cleantalk\ApbctWP\Helper::ip__get('real');
		add_filter('wpcf7_mail_components', 'apbct_form__contactForm7__changeMailNotification');
	}

    if ($ct_result->allow == 0) {

		global $ct_cf7_comment;
			$ct_cf7_comment = $ct_result->comment;

			add_filter('wpcf7_display_message', 'apbct_form__contactForm7__showResponse', 10, 2);

		$spam = WPCF7_VERSION >= '3.0.0' ? true : false;

    }

	$apbct->cf7_checked = true;

    return $spam;
}

/**
 * Changes CF7 status message
 * @param 	string $hook URL of hooked page
 */
function apbct_form__contactForm7__showResponse($message, $status = 'spam') {
    global $ct_cf7_comment;

    if ($status == 'spam') {
        $message = $ct_cf7_comment;
    }

    return $message;
}

/**
 * Changes email notification for succes subscription for Contact Form 7
 *
 * @param array $component Arguments for email notification
 * @return array Arguments for email notification
 */
function apbct_form__contactForm7__changeMailNotification($component){

	global $apbct;

	$component['body'] =
		__('CleanTalk AntiSpam: This message is spam.', 'cleantalk-spam-protect')
		.PHP_EOL . __('CleanTalk\'s anti-spam database:', 'cleantalk-spam-protect')
		.PHP_EOL . 'IP: '    . $apbct->sender_ip
		.PHP_EOL . 'Email: ' . $apbct->sender_email
		.PHP_EOL . sprintf(
			__('Activate protection in your Anti-Spam Dashboard: %s.', 'clentalk'),
			'https://cleantalk.org/my/?cp_mode=antispam&utm_source=newsletter&utm_medium=email&utm_campaign=cf7_activate_antispam&user_token='.$apbct->user_token
		)
		.PHP_EOL . '---' . PHP_EOL . PHP_EOL
		.$component['body'];

	return (array) $component;
}

/**
 * Test Ninja Forms message for spam
 *
 * @global SpbcState $apbct
 * @return void
 */
function apbct_form__ninjaForms__testSpam() {

    global $apbct, $cleantalk_executed;

    if( $cleantalk_executed ){
	    do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
	    return;
    }
 
	if(
			$apbct->settings['contact_forms_test'] == 0
		|| ($apbct->settings['protect_logged_in'] != 1 && is_user_logged_in()) // Skip processing for logged in users.
		|| apbct_exclusions_check__url()
	){
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
		return;
	}

	$checkjs = apbct_js_test('ct_checkjs', $_COOKIE);

	// Choosing between POST and GET
	$params = ct_get_fields_any(isset($_GET['ninja_forms_ajax_submit']) || isset($_GET['nf_ajax_submit']) ? $_GET : $_POST);

    $sender_email    = ($params['email']    ? $params['email']    : '');
    $sender_nickname = ($params['nickname'] ? $params['nickname'] : '');
    $subject         = ($params['subject']  ? $params['subject']  : '');
    $message         = ($params['message']  ? $params['message']  : array());
    if ($subject != '') {
        $message = array_merge(array('subject' => $subject), $message);
    }

	//Ninja Forms xml fix
	foreach ($message as $key => $value){
		if (strpos($value, '<xml>') !== false)
			unset($message[$key]);
	}

    $base_call_result = apbct_base_call(
		array(
			'message'         => $message,
			'sender_email'    => $sender_email,
			'sender_nickname' => $sender_nickname,
			'post_info'       => array('comment_type' => 'contact_form_wordpress_ninja_froms'),
			'js_on'           => $checkjs,
		)
	);
    $ct_result = $base_call_result['ct_result'];

	// Change mail notification if license is out of date
	if($apbct->data['moderate'] == 0 &&
		($ct_result->fast_submit == 1 || $ct_result->blacklisted == 1 || $ct_result->js_disabled == 1)
	){
		$apbct->sender_email = $sender_email;
		$apbct->sender_ip    = \Cleantalk\ApbctWP\Helper::ip__get('real');
		add_filter('ninja_forms_action_email_message', 'apbct_form__ninjaForms__changeMailNotification', 1, 3);
	}

    if ($ct_result->allow == 0) {

		// We have to use GLOBAL variable to transfer the comment to apbct_form__ninjaForms__changeResponse() function :(
	    $apbct->response = $ct_result->comment;
	    add_action( 'ninja_forms_before_response', 'apbct_form__ninjaForms__changeResponse', 10, 1 );
	    add_action( 'ninja_forms_action_email_send', 'apbct_form__ninjaForms__stopEmail', 1, 5 ); // Prevent mail notification
	    add_action( 'ninja_forms_save_submission', 'apbct_form__ninjaForms__preventSubmission', 1, 2 ); // Prevent mail notification
    }
}

function apbct_form__ninjaForms__preventSubmission($some, $form_id){
	return false;
}

function apbct_form__ninjaForms__stopEmail($some, $action_settings, $message, $headers, $attachments){
	global $apbct;
	throw new Exception($apbct->response);
}

function apbct_form__ninjaForms__changeResponse( $data ) {

	global $apbct;

	// Show error message below field found by ID
	if(array_key_exists('email', $data['fields_by_key'])){
		// Find ID of EMAIL field
		$nf_field_id = $data['fields_by_key']['email']['id'];
	}else{
		// Find ID of last field (usually SUBMIT)
        $fields_keys = array_keys($data['fields']);
		$nf_field_id = array_pop($fields_keys);
	}

	// Below is modified NJ logic
	$error = array(
		'fields' => array(
			$nf_field_id => $apbct->response,
		),
	);

	$response = array( 'data' => $data, 'errors' => $error, 'debug' => '' );

	die(wp_json_encode( $response, JSON_FORCE_OBJECT ));

}

function apbct_form__seedprod_coming_soon__testSpam() {

    global $apbct;

    if(
        $apbct->settings['contact_forms_test'] == 0
        || ($apbct->settings['protect_logged_in'] != 1 && is_user_logged_in()) // Skip processing for logged in users.
        || apbct_exclusions_check__url()
    ){
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return;
    }

    $ct_temp_msg_data = ct_get_fields_any($_REQUEST);

    $sender_email    = ($ct_temp_msg_data['email']    ? $ct_temp_msg_data['email']    : '');
    $sender_nickname = ($ct_temp_msg_data['nickname'] ? $ct_temp_msg_data['nickname'] : '');
    $subject         = ($ct_temp_msg_data['subject']  ? $ct_temp_msg_data['subject']  : '');
    $message         = ($ct_temp_msg_data['message']  ? $ct_temp_msg_data['message']  : array());
    if ($subject != '') {
        $message = array_merge(array('subject' => $subject), $message);
    }

    $post_info['comment_type'] = 'contact_form_wordpress_seedprod_coming_soon';

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => $post_info,
        )
    );

    $ct_result = $base_call_result['ct_result'];
    if ($ct_result->allow == 0) {
        global $ct_comment;
        $ct_comment = $ct_result->comment;

        $response = array(
            'status' => 200,
            'html'   => "<h1>".__('Spam protection by CleanTalk', 'cleantalk-spam-protect')."</h1><h2>".$ct_result->comment."</h2>"
        );

        echo sanitize_text_field($_GET['callback']) . '(' . json_encode($response) . ')';
        exit();
    }

}

/**
 * Changes email notification for succes subscription for Ninja Forms
 *
 * @param string $message Body of email notification
 * @return string Body for email notification
 */
function apbct_form__ninjaForms__changeMailNotification($message, $data, $action_settings){

	global $apbct;

	if($action_settings['to'] !== $apbct->sender_email){

		$message .= wpautop(PHP_EOL . '---'
		.PHP_EOL
		.__('CleanTalk AntiSpam: This message is spam.', 'cleantalk-spam-protect')
		.PHP_EOL . __('CleanTalk\'s anti-spam database:', 'cleantalk-spam-protect')
		.PHP_EOL . 'IP: '    . $apbct->sender_ip
		.PHP_EOL . 'Email: ' . $apbct->sender_email
		.PHP_EOL .
			__('Activate protection in your Anti-Spam Dashboard: ', 'clentalk').
			'https://cleantalk.org/my/?cp_mode=antispam&utm_source=newsletter&utm_medium=email&utm_campaign=ninjaform_activate_antispam'.$apbct->user_token
		);
	}

	return $message;
}

/**
 * Inserts anti-spam hidden to WPForms
 *
 * @global SpbcState $apbct
 * @return void
 */
function apbct_form__WPForms__addField($form_data, $some, $title, $description, $errors) {

	global $apbct;

    if($apbct->settings['contact_forms_test'] == 1)
		ct_add_hidden_fields('checkjs_wpforms', false);

}

/**
 * Gather fields data from submission and store it
 *
 * @param array      $entry
 * @param            $form
 *
 * @return array
 * @global SpbcState $apbct
 */
function apbct_from__WPForms__gatherData($entry, $form){

	global $apbct;

	$data = array();
	foreach($entry['fields'] as $key => $val){
		$true_key = strtolower(str_replace(' ', '_', $form['fields'][$key]['label']));
		$true_key = $true_key ? $true_key : $key;
		$data[$true_key] = $val;
	} unset($key, $val);

	$apbct->form_data = $data;

	return $entry;
}

/**
 * Adding error to form entry if message is spam
 * Call spam test from here
 *
 * @param array $errors
 * @param array $form_data
 * @return array
 */
function apbct_form__WPForms__showResponse($errors, $form_data) {

	if(empty($errors) || ( isset($form_data['id'], $errors[$form_data['id']]) && !count($errors[$form_data['id']]) ) ){

		$spam_comment = apbct_form__WPForms__testSpam();

		$filed_id = $form_data && !empty($form_data['fields']) && is_array($form_data['fields'])
			? key($form_data['fields'])
			: 0;

		if($spam_comment)
			$errors[ $form_data['id'] ][ $filed_id ] = $spam_comment;

	}

	return $errors;
}

/**
 * Test WPForms message for spam
 * Doesn't hooked anywhere.
 * Called directly from apbct_form__WPForms__showResponse()
 *
 * @global SpbcState $apbct
 * @global array $apbct->form_data Contains form data
 * @param array $errors Array of errors to write false result in
 * @return void|array|null
 */
function apbct_form__WPForms__testSpam() {

    global $apbct;

	if(
		$apbct->settings['contact_forms_test'] == 0 ||
		$apbct->settings['protect_logged_in'] != 1 && is_user_logged_in() // Skip processing for logged in users.
	){
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
		return;
	}

	$checkjs = apbct_js_test('checkjs_wpforms', $_POST);

	$params = ct_get_fields_any($apbct->form_data);

    $sender_email    = ($params['email']    ? $params['email']    : '');
    $sender_nickname = ($params['nickname'] ? $params['nickname'] : '');
    $subject         = ($params['subject']  ? $params['subject']  : '');
    $message         = ($params['message']  ? $params['message']  : array());
    if ($subject != '') {
        $message = array_merge(array('subject' => $subject), $message);
    }

    $base_call_result = apbct_base_call(
		array(
			'message'         => $message,
			'sender_email'    => $sender_email,
			'sender_nickname' => $sender_nickname,
			'post_info'       => array('comment_type' => 'contact_form_wordpress_wp_forms'),
			'js_on'           => $checkjs,
		)
	);
    $ct_result = $base_call_result['ct_result'];

	// Change mail notification if license is out of date
	if($apbct->data['moderate'] == 0 &&
		($ct_result->fast_submit == 1 || $ct_result->blacklisted == 1 || $ct_result->js_disabled == 1)
	){
		$apbct->sender_email = $sender_email;
		$apbct->sender_ip    = \Cleantalk\ApbctWP\Helper::ip__get('real');
		add_filter('wpforms_email_message', 'apbct_form__WPForms__changeMailNotification', 100, 2);
	}

    if ($ct_result->allow == 0){
		return $ct_result->comment;
    }

	return null;

}

/**
 * Changes email notification for succes subscription for Ninja Forms
 *
 * @param string $message Body of email notification
 * @param WPForms_WP_Emails $wpforms_email WPForms email class object
 * @return string Body for email notification
 */
function apbct_form__WPForms__changeMailNotification($message, $wpforms_email){

	global $apbct;

	$message = str_replace('</html>', '', $message);
	$message = str_replace('</body>', '', $message);
	$message .= wpautop(PHP_EOL . '---'
		.PHP_EOL
		.__('CleanTalk AntiSpam: This message is spam.', 'cleantalk-spam-protect')
		.PHP_EOL . __('CleanTalk\'s anti-spam database:', 'cleantalk-spam-protect')
		.PHP_EOL . 'IP: '    . '<a href="https://cleantalk.org/blacklists/' . $apbct->sender_ip    . '?utm_source=newsletter&utm_medium=email&utm_campaign=wpforms_spam_passed" target="_blank">' . $apbct->sender_ip    . '</a>'
		.PHP_EOL . 'Email: ' . '<a href="https://cleantalk.org/blacklists/' . $apbct->sender_email . '?utm_source=newsletter&utm_medium=email&utm_campaign=wpforms_spam_passed" target="_blank">' . $apbct->sender_email . '</a>'
		.PHP_EOL . sprintf(
			__('Activate protection in your %sAnti-Spam Dashboard%s.', 'clentalk'),
			'<a href="https://cleantalk.org/my/?cp_mode=antispam&utm_source=newsletter&utm_medium=email&utm_campaign=wpforms_activate_antispam" target="_blank">',
			'</a>'
		))
		.'</body></html>';

	return $message;

}

/*
*  QuForms check spam
*	works with singl-paged forms
*	and with multi-paged forms - check only last step of the forms
*/
function ct_quform_post_validate($result, $form) {

	if ( $form->hasPages() ) {
		$comment_type = 'contact_form_wordpress_quforms_multipage';
	} else {
		$comment_type = 'contact_form_wordpress_quforms_singlepage';
	}

	$ct_temp_msg_data = ct_get_fields_any( $form->getValues() );
	// @ToDo If we have several emails at the form - will be used only the first detected!
	$sender_email    = ($ct_temp_msg_data['email']    ? $ct_temp_msg_data['email']    : '');

	$checkjs = apbct_js_test('ct_checkjs', $_COOKIE);
	$base_call_result = apbct_base_call(
		array(
			'message'         => $form->getValues(),
			'sender_email'    => $sender_email,
			'post_info'       => array('comment_type' => $comment_type),
			'js_on'           => $checkjs,
		)
	);

	$ct_result = $base_call_result['ct_result'];
	if ($ct_result->allow == 0) {
		die(json_encode(array('type' => 'error', 'apbct' => array('blocked' => true, 'comment' => $ct_result->comment))));
	} else {
		return $result;
	}

	return $result;

}

/**
 * Inserts anti-spam hidden to Fast Secure contact form
 */
function ct_si_contact_display_after_fields($string = '', $style = '', $form_errors = array(), $form_id_num = 0) {
    $string .= ct_add_hidden_fields('ct_checkjs', true);
    return $string;
}

/**
 * Test for Fast Secure contact form
 */
function ct_si_contact_form_validate($form_errors = array(), $form_id_num = 0) {
    global $apbct, $cleantalk_executed;

    if (!empty($form_errors)) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return $form_errors;
    }


    if ($apbct->settings['contact_forms_test'] == 0) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return $form_errors;
    }

    // Skip processing because data already processed.
    if ($cleantalk_executed) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
	    return $form_errors;
    }

	//getting info from custom fields
	$ct_temp_msg_data = ct_get_fields_any($_POST);

    $sender_email    = ($ct_temp_msg_data['email']    ? $ct_temp_msg_data['email'] : '');
    $sender_nickname = ($ct_temp_msg_data['nickname'] ? $ct_temp_msg_data['nickname'] : '');
    $subject         = ($ct_temp_msg_data['subject']  ? $ct_temp_msg_data['subject'] : '');
    $contact_form    = ($ct_temp_msg_data['contact']  ? $ct_temp_msg_data['contact'] : true);
    $message         = ($ct_temp_msg_data['message']  ? $ct_temp_msg_data['message'] : array());
	if($subject != '') {
        $message['subject'] = $subject;
    }

    $base_call_result = apbct_base_call(
		array(
			'message'         => $message,
			'sender_email'    => $sender_email,
			'sender_nickname' => $sender_nickname,
			'post_info'       => array('comment_type' => 'contact_form_wordpress_fscf'),
			'js_on'           => apbct_js_test('ct_checkjs', $_POST),
		)
	);

    $ct_result = $base_call_result['ct_result'];

    $cleantalk_executed = true;

    if ($ct_result->allow == 0) {
        global $ct_comment;
        $ct_comment = $ct_result->comment;
        ct_die(null, null);
        exit;
    }

    return $form_errors;
}

/**
 * Notice for commentators which comment has automatically approved by plugin
 * @param 	string $hook URL of hooked page
 */
function ct_comment_text($comment_text) {
    global $comment, $ct_approved_request_id_label;

    if (isset($_COOKIE[$ct_approved_request_id_label]) && isset($comment->comment_ID)) {
        $ct_hash = get_comment_meta($comment->comment_ID, 'ct_hash', true);

        if ($ct_hash !== '' && $_COOKIE[$ct_approved_request_id_label] == $ct_hash) {
            $comment_text .= '<br /><br /> <em class="comment-awaiting-moderation">' . __('Comment approved. Anti-spam by CleanTalk.', 'cleantalk-spam-protect') . '</em>';
        }
    }

    return $comment_text;
}


/**
 * Checks WordPress Landing Pages raw $_POST values
*/
function ct_check_wplp(){

    global $ct_wplp_result_label, $apbct;

    if (!isset($_COOKIE[$ct_wplp_result_label])) {
        // First AJAX submit of WPLP form
        if ($apbct->settings['contact_forms_test'] == 0) {
            do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
            return;
        }

        $post_info['comment_type'] = 'feedback';
        $post_info = json_encode($post_info);
        if ($post_info === false)
            $post_info = '';

        $sender_email = '';
        foreach ($_POST as $v) {
            if (preg_match("/^\S+@\S+\.\S+$/", $v)) {
                $sender_email = $v;
                break;
            }
        }

        $message = '';
        if(array_key_exists('form_input_values', $_POST)){
            $form_input_values = json_decode(stripslashes($_POST['form_input_values']), true);
            if (is_array($form_input_values) && array_key_exists('null', $form_input_values))
                $message = $form_input_values['null'];
        } else if (array_key_exists('null', $_POST)) {
            $message = $_POST['null'];
        }

        $base_call_result = apbct_base_call(
			array(
                'message'      => $message,
                'sender_email' => $sender_email,
                'post_info'    => array('comment_type' => 'contact_form_wordpress_wplp'),
			)
		);

        $ct_result = $base_call_result['ct_result'];

        if ($ct_result->allow == 0) {
            $cleantalk_comment = $ct_result->comment;
        } else {
            $cleantalk_comment = 'OK';
        }

        \Cleantalk\Common\Helper::apbct_cookie__set($ct_wplp_result_label, $cleantalk_comment, strtotime("+5 seconds"), '/');
    } else {
        // Next POST/AJAX submit(s) of same WPLP form
        $cleantalk_comment = $_COOKIE[$ct_wplp_result_label];
    }
    if ($cleantalk_comment !== 'OK')
        ct_die_extended($cleantalk_comment);
}

/**
 * Places a hidding field to Gravity forms.
 * @return string
 */
function apbct_form__gravityForms__addField($form_string, $form){
    $ct_hidden_field = 'ct_checkjs';

    // Do not add a hidden field twice.
    if (preg_match("/$ct_hidden_field/", $form_string)) {
        return $form_string;
    }

    $search = "</form>";

	// Adding JS code
    $js_code = ct_add_hidden_fields($ct_hidden_field, true, false);
    $form_string = str_replace($search, $js_code . $search, $form_string);

	// Adding field for multipage form. Look for cleantalk.php -> apbct_cookie();
	$append_string = isset($form['lastPageButton']) ? "<input type='hidden' name='ct_multipage_form' value='yes'>" : '';
	$form_string = str_replace($search, $append_string.$search, $form_string);

    return $form_string;
}

/**
 * Gravity forms anti-spam test.
 * @return boolean
 */
function apbct_form__gravityForms__testSpam($is_spam, $form, $entry) {

    global $apbct, $cleantalk_executed, $ct_gform_is_spam, $ct_gform_response;

    if (
		$apbct->settings['contact_forms_test'] == 0 ||
		$cleantalk_executed // Return unchanged result if the submission was already tested.
	) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return $is_spam;
    }

	$ct_temp = array();
	foreach($entry as $key => $value){
		if(is_numeric($key))
			$ct_temp[$key]=$value;
	} unset($key, $value);

	$ct_temp_msg_data = ct_get_fields_any($ct_temp);
    $sender_email    = ($ct_temp_msg_data['email']    ? $ct_temp_msg_data['email']    : '');
    $sender_nickname = ($ct_temp_msg_data['nickname'] ? $ct_temp_msg_data['nickname'] : '');
    $subject         = ($ct_temp_msg_data['subject']  ? $ct_temp_msg_data['subject']  : '');
    $contact_form    = ($ct_temp_msg_data['contact']  ? $ct_temp_msg_data['contact']  : true);
    $message         = ($ct_temp_msg_data['message']  ? $ct_temp_msg_data['message']  : array());
	
	// Adding 'input_' to every field /Gravity Forms fix/
	$tmp = $message;
    $message = array();
	foreach($tmp as $key => $value){
		$message[ 'input_' . $key] = $value;
	} unset( $key, $value, $tmp );

    if($subject != '')
        $message['subject'] = $subject;

	$checkjs = apbct_js_test('ct_checkjs', $_POST)
		? apbct_js_test('ct_checkjs', $_POST)
		: apbct_js_test('ct_checkjs', $_COOKIE);

    $base_call_result = apbct_base_call(
		array(
			'message'         => $message,
			'sender_email'    => $sender_email,
			'sender_nickname' => $sender_nickname,
			'post_info'       => array('comment_type' => 'contact_form_wordpress_gravity_forms'),
			'js_on'           => $checkjs,
		)
	);

    $ct_result = $base_call_result['ct_result'];
    if ($ct_result->allow == 0) {
        $is_spam = true;
		$ct_gform_is_spam = true;
		$ct_gform_response = $ct_result->comment;
    }

    return $is_spam;
}

function apbct_form__gravityForms__showResponse( $confirmation, $form, $entry, $ajax ){

	global $ct_gform_is_spam, $ct_gform_response;

	if(!empty($ct_gform_is_spam)){
		$confirmation = '<a id="gf_'.$form['id'].'" class="gform_anchor" ></a><div id="gform_confirmation_wrapper_'.$form['id'].'" class="gform_confirmation_wrapper "><div id="gform_confirmation_message_'.$form['id'].'" class="gform_confirmation_message_'.$form['id'].' gform_confirmation_message"><font style="color: red">'.$ct_gform_response.'</font></div></div>';
	}

	return $confirmation;
}

/**
 * Test S2member registration
 * @return array with errors
 */
function ct_s2member_registration_test($post_key) {

    global $apbct;

    if ($apbct->settings['registrations_test'] == 0) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return null;
    }

	$sender_email    = isset($_POST[$post_key]['email'])    ? sanitize_email($_POST[$post_key]['email'])    : null;
	$sender_nickname = isset($_POST[$post_key]['username']) ? sanitize_email($_POST[$post_key]['username']) : null;

	//Making a call
	$base_call_result = apbct_base_call(
		array(
			'sender_email'    => $sender_email,
			'sender_nickname' => $sender_nickname,
		),
		true
	);
	$ct_result = $base_call_result['ct_result'];

    if ($ct_result->allow == 0) {
        ct_die_extended($ct_result->comment);
    }

    return true;
}

function apbct_form__the7_contact_form() {

    global $cleantalk_executed;

    if ( check_ajax_referer( 'dt_contact_form', 'nonce', false ) && isset($_POST) ) {

        $post_info['comment_type'] = 'contact_the7_theme_contact_form';

        $ct_temp_msg_data = ct_get_fields_any($_POST);

        $sender_email    = ($ct_temp_msg_data['email']    ? $ct_temp_msg_data['email']    : '');
        $sender_nickname = ($ct_temp_msg_data['nickname'] ? $ct_temp_msg_data['nickname'] : '');
        $subject         = ($ct_temp_msg_data['subject']  ? $ct_temp_msg_data['subject']  : '');
        $contact_form    = ($ct_temp_msg_data['contact']  ? $ct_temp_msg_data['contact']  : true);
        $message         = ($ct_temp_msg_data['message']  ? $ct_temp_msg_data['message']  : array());
        if ($subject != '') {
            $message = array_merge(array('subject' => $subject), $message);
        }

        // Skip submission if no data found
        if ($sender_email === ''|| !$contact_form) {
            do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
            return false;
        }
        $cleantalk_executed = true;

        $base_call_result = apbct_base_call(
            array(
                'message'         => $message,
                'sender_email'    => $sender_email,
                'sender_nickname' => $sender_nickname,
                'post_info'       => $post_info,
            )
        );

        $ct_result = $base_call_result['ct_result'];
        if ($ct_result->allow == 0) {

            $response = json_encode(
                array(
                    'success'		=> false ,
                    'errors'        => $ct_result->comment,
                    'nonce'         => wp_create_nonce( 'dt_contact_form' )
                )
            );

            // response output
            header( "Content-Type: application/json" );
            echo $response;

            // IMPORTANT: don't forget to "exit"
            exit;

        }

    }

}

function apbct_form__elementor_pro__testSpam() {

    global $apbct, $cleantalk_executed;

    if(
        $apbct->settings['contact_forms_test'] == 0
        || ($apbct->settings['protect_logged_in'] != 1 && is_user_logged_in()) // Skip processing for logged in users.
        || apbct_exclusions_check__url()
    ){
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return;
    }

    $ct_temp_msg_data = ct_get_fields_any($_POST);

    $sender_email    = ($ct_temp_msg_data['email']    ? $ct_temp_msg_data['email']    : '');
    $sender_nickname = ($ct_temp_msg_data['nickname'] ? $ct_temp_msg_data['nickname'] : '');
    $subject         = ($ct_temp_msg_data['subject']  ? $ct_temp_msg_data['subject']  : '');
    $message         = ($ct_temp_msg_data['message']  ? $ct_temp_msg_data['message']  : array());
    if ($subject != '') {
        $message = array_merge(array('subject' => $subject), $message);
    }

    $post_info['comment_type'] = 'contact_form_wordpress_elementor_pro';

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $sender_email,
            'sender_nickname' => $sender_nickname,
            'post_info'       => $post_info,
        )
    );

    $ct_result = $base_call_result['ct_result'];

    if ($ct_result->allow == 0) {

        wp_send_json_error( array(
            'message' => $ct_result->comment,
            'data' => array()
        ) );

    }

}

// INEVIO theme integration
function apbct_form__inevio__testSpam() {

    global $apbct, $cleantalk_executed;

    $theme = wp_get_theme();
    if(
        stripos( $theme->get( 'Name' ), 'INEVIO' ) === false ||
        $apbct->settings['contact_forms_test'] == 0 ||
        ($apbct->settings['protect_logged_in'] != 1 && is_user_logged_in()) || // Skip processing for logged in users.
        apbct_exclusions_check__url()
    ) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return false;
    }
    $form_data = array();
    parse_str($_POST['data'], $form_data);

    $name    = isset($form_data['name']) ?  $form_data['name'] : '';
    $email   = isset($form_data['email']) ?  $form_data['email'] : '';
    $message = isset($form_data['message']) ?  $form_data['message'] : '';

    $post_info['comment_type'] = 'contact_form_wordpress_inevio_theme';

    $base_call_result = apbct_base_call(
        array(
            'message'         => $message,
            'sender_email'    => $email,
            'sender_nickname' => $name,
            'post_info'       => $post_info,
        )
    );

    $ct_result = $base_call_result['ct_result'];

    if ( $ct_result->allow == 0 ) {
        die(json_encode(array('apbct' => array('blocked' => true, 'comment' => $ct_result->comment,))));
    }

    return true;

}

/**
 * General test for any contact form
 */
function ct_contact_form_validate() {

	global $pagenow,$cleantalk_executed ,$apbct, $ct_checkjs_frm;

	// Exclusios common function
	if ( apbct_exclusions_check(__FUNCTION__) ) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return null;
    }

    if (@sizeof($_POST)==0 ||
    	(isset($_POST['signup_username']) && isset($_POST['signup_email']) && isset($_POST['signup_password'])) ||
        (isset($pagenow) && $pagenow == 'wp-login.php') || // WordPress log in form
        (isset($pagenow) && $pagenow == 'wp-login.php' && isset($_GET['action']) && $_GET['action']=='lostpassword') ||
		apbct_is_in_referer( 'lostpassword' ) ||
        apbct_is_in_referer( 'lost-password' ) || //Skip lost-password form check
        (apbct_is_in_uri('/wp-admin/') && (empty($_POST['your-phone']) && empty($_POST['your-email']) && empty($_POST['your-message']))) || //Bitrix24 Contact
        apbct_is_in_uri('wp-login.php') ||
        apbct_is_in_uri('wp-comments-post.php') ||
        apbct_is_in_uri('?provider=facebook&') ||
        apbct_is_in_uri('reset-password/') || // Ticket #13668. Password reset.
        apbct_is_in_referer( '/wp-admin/') ||
        apbct_is_in_uri('/login/') ||
        apbct_is_in_uri( '/my-account/edit-account/') ||   // WooCommerce edit account page
        apbct_is_in_uri( '/my-account/edit-address/') ||   // WooCommerce edit account page
        (isset($_POST['action']) && $_POST['action'] == 'save_account_details') ||       // WooCommerce edit account action
        apbct_is_in_uri( '/peepsoajax/profilefieldsajax.validate_register') ||
        isset($_GET['ptype']) && $_GET['ptype']=='login' ||
        isset($_POST['ct_checkjs_register_form']) ||
        (isset($_POST['signup_username']) && isset($_POST['signup_password_confirm']) && isset($_POST['signup_submit']) ) ||
        $apbct->settings['general_contact_forms_test'] == 0 ||
        isset($_POST['bbp_topic_content']) ||
        isset($_POST['bbp_reply_content']) ||
        isset($_POST['fscf_submitted']) ||
        apbct_is_in_uri('/wc-api/') ||
        isset($_POST['log']) && isset($_POST['pwd']) && isset($_POST['wp-submit']) ||
        isset($_POST[$ct_checkjs_frm]) && $apbct->settings['contact_forms_test'] == 1 ||// Formidable forms
        ( isset($_POST['comment_post_ID']) && ! isset($_POST['comment-submit'] ) ) || // The comment form && ! DW Question & Answer
        isset($_GET['for']) ||
		(isset($_POST['log'], $_POST['pwd'])) || //WooCommerce Sensei login form fix
		(isset($_POST['wc_reset_password'], $_POST['_wpnonce'], $_POST['_wp_http_referer'])) || // WooCommerce recovery password form
		((isset($_POST['woocommerce-login-nonce']) || isset($_POST['_wpnonce'])) && isset($_POST['login'], $_POST['password'], $_POST['_wp_http_referer'])) || // WooCommerce login form
		(isset($_POST['wc-api']) && strtolower($_POST['wc-api']) == 'wc_gateway_systempay') || // Woo Systempay payment plugin
        apbct_is_in_uri( 'wc-api=WC_Gateway_Realex_Redirect') || // Woo Realex payment Gateway plugin
        (isset($_POST['_wpcf7'], $_POST['_wpcf7_version'], $_POST['_wpcf7_locale'])) || //CF7 fix)
		(isset($_POST['hash'], $_POST['device_unique_id'], $_POST['device_name'])) ||//Mobile Assistant Connector fix
		isset($_POST['gform_submit']) || //Gravity form
        apbct_is_in_uri( 'wc-ajax=get_refreshed_fragments') ||
		(isset($_POST['ccf_form']) && intval($_POST['ccf_form']) == 1) ||
		(isset($_POST['contact_tags']) && strpos($_POST['contact_tags'], 'MBR:') !== false) ||
		(apbct_is_in_uri( 'bizuno.php') && !empty($_POST['bizPass'])) ||
        apbct_is_in_referer( 'my-dashboard/' ) || // ticket_id=7885
		isset($_POST['slm_action'], $_POST['license_key'], $_POST['secret_key'], $_POST['registered_domain']) || // ticket_id=9122
		(isset($_POST['wpforms']['submit']) && $_POST['wpforms']['submit'] == 'wpforms-submit') || // WPForms
		(isset($_POST['action']) && $_POST['action'] == 'grunion-contact-form') || // JetPack
		(isset($_POST['action']) && $_POST['action'] == 'bbp-update-user') || //BBP update user info page
        apbct_is_in_referer( '?wc-api=WC_Gateway_Transferuj' ) || //WC Gateway
		(isset($_GET['mbr'], $_GET['amp;appname'], $_GET['amp;master'])) || //  ticket_id=10773
		(isset($_POST['call_function']) && $_POST['call_function'] == 'push_notification_settings') || // Skip mobile requests (push settings)
		apbct_is_in_uri('membership-login') || // Skip login form
		(isset($_GET['cookie-state-change'])) || //skip GDPR plugin
		( apbct_get_server_variable( 'HTTP_USER_AGENT' ) == 'MailChimp' && apbct_is_in_uri( 'mc4wp-sync-api/webhook-listener') ) || // Mailchimp webhook skip
        apbct_is_in_uri('researcher-log-in') || // Skip login form
        apbct_is_in_uri('admin_aspcms/_system/AspCms_SiteSetting.asp?action=saves') || // Skip admin save callback
        apbct_is_in_uri('?profile_tab=postjobs') || // Skip post vacancies
		(isset($_POST['btn_insert_post_type_hotel']) && $_POST['btn_insert_post_type_hotel'] == 'SUBMIT HOTEL') || // Skip adding hotel
		(isset($_POST['action']) && $_POST['action'] == 'updraft_savesettings') || // Updraft save settings
		isset($_POST['quform_submit']) || //QForms multi-paged form skip
		(isset($_POST['wpum_form']) && $_POST['wpum_form'] == 'login') || //WPUM login skip
		isset($_POST['password']) || // Exception for login form. From Analysis uid=406596
        (isset($_POST['action']) && $_POST['action'] == 'wilcity_reset_password') || // Exception for reset password form. From Analysis uid=430898
        (isset($_POST['action']) && $_POST['action'] == 'wilcity_login') || // Exception for login form. From Analysis uid=430898
        (isset($_POST['qcfsubmit'])) || //Exception for submit quick forms - duplicates with qcfvalidate
        apbct_is_in_uri('tin-canny-learndash-reporting/src/h5p-xapi/process-xapi-statement.php?v=asd') || //Skip Tin Canny plugin
        ( isset( $_POST['na'], $_POST['ts'], $_POST['nhr'] ) && !apbct_is_in_uri( '?na=s' ) ) ||  // The Newsletter Plugin double requests fix. Ticket #14772
        (isset($_POST['spl_action']) && $_POST['spl_action'] == 'register') || //Skip interal action with empty params
        (isset($_POST['action']) && $_POST['action'] == 'bwfan_insert_abandoned_cart' && apbct_is_in_uri( 'my-account/edit-address' )) || //Skip edit account
        apbct_is_in_uri('login-1') || //Skip login form
        apbct_is_in_uri('recuperacao-de-senha-2') || //Skip form reset password
        apbct_is_in_uri('membermouse/api/request.php') && isset($_POST['membership_level_id'],$_POST['apikey'],$_POST['apisecret']) || // Membermouse API
        ( isset( $_POST['AppKey'] ) && ( isset( $_POST['cbAP'] ) && $_POST['cbAP'] == 'Caspio' ) ) ||  // Caspio exclusion (ticket #16444)
        isset($_POST['wpforms_id'], $_POST['wpforms_author']) || //Skip wpforms
        ( isset( $_POST['somfrp_action'], $_POST['submitted'] ) && $_POST['somfrp_action'] == 'somfrp_lost_pass' ) || // Frontend Reset Password exclusion
        ( isset( $_POST['action'] ) && $_POST['action'] == 'dokan_save_account_details' ) ||
        \Cleantalk\Variables\Post::get('action') === 'frm_get_lookup_text_value' || // Exception for Formidable multilevel form
        ( isset( $_POST['ihcaction'] ) && $_POST['ihcaction'] == 'reset_pass') || //Reset pass exclusion
        ( isset( $_POST['action'],  $_POST['register_unspecified_nonce_field'] ) && $_POST['action'] == 'register' ) || // Profile Builder have a direct integration
        ( isset( $_POST['_wpmem_register_nonce'] ) && wp_verify_nonce( $_POST['_wpmem_register_nonce'], 'wpmem_longform_nonce' ) ) // WP Members have a direct integration
        /* !! Do not add actions here. Use apbct_is_skip_request() function below !! */
		) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return null;
    }

    //Skip woocommerce checkout
    if (apbct_is_in_uri('wc-ajax=update_order_review') ||
        apbct_is_in_uri('wc-ajax=checkout') ||
        !empty($_POST['woocommerce_checkout_place_order']) ||
        apbct_is_in_uri('wc-ajax=wc_ppec_start_checkout') ||
        apbct_is_in_referer('wc-ajax=update_order_review')
    )
    {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
    	return null;
    }
    
    //Skip woocommerce add_to_cart
	if( ! empty( $_POST['add-to-cart'] ) )
    {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
    	return null;
    }
	
    // Do not execute anti-spam test for logged in users.
    if (isset($_COOKIE[LOGGED_IN_COOKIE]) && $apbct->settings['protect_logged_in'] != 1) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return null;
    }
    //Skip WP Fusion web hooks
    if ( apbct_is_in_uri('wpf_action') && apbct_is_in_uri('access_key') && isset( $_GET['access_key'] ) ) {
        if( function_exists( 'wp_fusion' ) ) {
            $key = wp_fusion()->settings->get('access_key');
            if ( $key == $_GET['access_key'] ) {
                do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
                return null;
            }
        }
    }
    //Skip system fields for divi
	if (strpos( \Cleantalk\Variables\Post::get('action'), 'et_pb_contactform_submit') === 0) {
		foreach ($_POST as $key => $value) {
			if (strpos($key, 'et_pb_contact_email_fields') === 0) {
				unset($_POST[$key]);
			}
		}
	}

    if( apbct_is_skip_request( false ) ) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__ . '(' . apbct_is_skip_request() . ')', $_POST );
        return false;
    }

    $post_info['comment_type'] = 'feedback_general_contact_form';

	$ct_temp_msg_data = ct_get_fields_any($_POST);

    $sender_email    = ($ct_temp_msg_data['email']    ? $ct_temp_msg_data['email']    : '');
    $sender_nickname = ($ct_temp_msg_data['nickname'] ? $ct_temp_msg_data['nickname'] : '');
    $subject         = ($ct_temp_msg_data['subject']  ? $ct_temp_msg_data['subject']  : '');
    $contact_form    = ($ct_temp_msg_data['contact']  ? $ct_temp_msg_data['contact']  : true);
    $message         = ($ct_temp_msg_data['message']  ? $ct_temp_msg_data['message']  : array());
    if ($subject != '') {
        $message = array_merge(array('subject' => $subject), $message);
    }

    // Skip submission if no data found
    if ($sender_email === ''|| !$contact_form) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return false;
    }

    if(isset($_POST['TellAFriend_Link'])){
    	$tmp = $_POST['TellAFriend_Link'];
    	unset($_POST['TellAFriend_Link']);
    }

    $base_call_result = apbct_base_call(
		array(
			'message'         => $message,
			'sender_email'    => $sender_email,
			'sender_nickname' => $sender_nickname,
			'post_info'       => $post_info,
			'sender_info'     => array( 'sender_email' => urlencode( $sender_email ) ),
		)
	);

    if(isset($_POST['TellAFriend_Link'])){
    	$_POST['TellAFriend_Link']=$tmp;
    }

    $ct_result = $base_call_result['ct_result'];
    if ($ct_result->allow == 0) {

		// Recognize contact form an set it's name to $contact_form to use later
		$contact_form = null;
		foreach($_POST as $param => $value){
			if(strpos($param, 'et_pb_contactform_submit') === 0){
				$contact_form = 'contact_form_divi_theme';
				$contact_form_additional = str_replace('et_pb_contactform_submit', '', $param);
			}
			if(strpos($param, 'avia_generated_form') === 0){
				$contact_form = 'contact_form_enfold_theme';
				$contact_form_additional = str_replace('avia_generated_form', '', $param);
			}
			if(!empty($contact_form))
				break;
		}

        $ajax_call = false;
        if ((defined( 'DOING_AJAX' ) && DOING_AJAX)
            ) {
            $ajax_call = true;
		}
        if ($ajax_call) {
            echo $ct_result->comment;
        } else {

            global $ct_comment;
            $ct_comment = $ct_result->comment;
            if(isset($_POST['cma-action'])&&$_POST['cma-action']=='add'){
            	$result=Array('success'=>0, 'thread_id'=>null,'messages'=>Array($ct_result->comment));
            	header("Content-Type: application/json");
				print json_encode($result);
				die();

            }else if(isset($_POST['TellAFriend_email'])){
            	echo $ct_result->comment;
            	die();

            }else if(isset($_POST['gform_submit'])){ // Gravity forms submission
                $response = sprintf("<!DOCTYPE html><html><head><meta charset='UTF-8' /></head><body class='GF_AJAX_POSTBACK'><div id='gform_confirmation_wrapper_1' class='gform_confirmation_wrapper '><div id='gform_confirmation_message_1' class='gform_confirmation_message_1
 gform_confirmation_message'>%s</div></div></body></html>",
                    $ct_result->comment
                );
                echo $response;
            	die();

            }elseif(isset($_POST['action']) && $_POST['action'] == 'ct_check_internal'){
                return $ct_result->comment;

            }elseif(isset($_POST['vfb-submit']) && defined('VFB_VERSION')){
				wp_die("<h1>".__('Spam protection by CleanTalk', 'cleantalk-spam-protect')."</h1><h2>".$ct_result->comment."</h2>", '', array('response' => 403, "back_link" => true, "text_direction" => 'ltr'));
            // Caldera Contact Forms
			}elseif(isset($_POST['action']) && $_POST['action'] == 'cf_process_ajax_submit'){
				print "<h3 style='color: red;'><red>".$ct_result->comment."</red></h3>";
				die();
			// Mailster
			}elseif(isset($_POST['_referer'], $_POST['formid'], $_POST['email'])){
				$return = array(
					'success' => false,
					'html' => '<p>' .  $ct_result->comment  . '</p>',
				);
				print json_encode($return);
				die();
			// Divi Theme Contact Form. Using $contact_form
			}elseif(!empty($contact_form) && $contact_form == 'contact_form_divi_theme'){
				echo "<div id='et_pb_contact_form{$contact_form_additional}'><h1>Your request looks like spam.</h1><div><p>{$ct_result->comment}</p></div></div>";
				die();
			// Enfold Theme Contact Form. Using $contact_form
			}elseif(!empty($contact_form) && $contact_form == 'contact_form_enfold_theme'){
				echo "<div id='ajaxresponse_1' class='ajaxresponse ajaxresponse_1' style='display: block;'><div id='ajaxresponse_1' class='ajaxresponse ajaxresponse_1'><h3 class='avia-form-success'>Antispam by CleanTalk: ".$ct_result->comment."</h3><a href='.'><-Back</a></div></div>";
				die();
            }else{
				ct_die(null, null);
			}
        }
        exit;
    }

    return null;
}

/**
 * General test for any post data
 */
function ct_contact_form_validate_postdata() {

	global $apbct, $pagenow,$cleantalk_executed;

	// Exclusios common function
	if ( apbct_exclusions_check(__FUNCTION__) ) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return null;
    }

    if (@sizeof($_POST)==0 ||
    	(isset($_POST['signup_username']) && isset($_POST['signup_email']) && isset($_POST['signup_password'])) ||
        (isset($pagenow) && $pagenow == 'wp-login.php') || // WordPress log in form
        (isset($pagenow) && $pagenow == 'wp-login.php' && isset($_GET['action']) && $_GET['action']=='lostpassword') ||
        apbct_is_in_uri('/checkout/') ||
		/* WooCommerce Service Requests - skip them */
        isset($_GET['wc-ajax']) && (
	        $_GET['wc-ajax']=='checkout' ||
	        $_GET['wc-ajax']=='get_refreshed_fragments' ||
	        $_GET['wc-ajax']=='apply_coupon' ||
	        $_GET['wc-ajax']=='remove_coupon' ||
	        $_GET['wc-ajax']=='update_shipping_method' ||
	        $_GET['wc-ajax']=='get_cart_totals' ||
	        $_GET['wc-ajax']=='update_order_review' ||
	        $_GET['wc-ajax']=='add_to_cart' ||
	        $_GET['wc-ajax']=='remove_from_cart' ||
	        $_GET['wc-ajax']=='get_variation' ||
	        $_GET['wc-ajax']=='get_customer_location'
        ) ||
        /* END: WooCommerce Service Requests  */
        apbct_is_in_uri('/wp-admin/') ||
        apbct_is_in_uri('wp-login.php') ||
        apbct_is_in_uri('wp-comments-post.php') ||
        apbct_is_in_referer('/wp-admin/') ||
        apbct_is_in_uri('/login/') ||
        apbct_is_in_uri('?provider=facebook&') ||
        isset($_GET['ptype']) && $_GET['ptype']=='login' ||
        isset($_POST['ct_checkjs_register_form']) ||
        (isset($_POST['signup_username']) && isset($_POST['signup_password_confirm']) && isset($_POST['signup_submit']) ) ||
        $apbct->settings['general_contact_forms_test']==0 ||
        isset($_POST['bbp_topic_content']) ||
        isset($_POST['bbp_reply_content']) ||
        isset($_POST['fscf_submitted']) ||
        isset($_POST['log']) && isset($_POST['pwd']) && isset($_POST['wp-submit'])||
        apbct_is_in_uri('/wc-api/') ||
		(isset($_POST['wc_reset_password'], $_POST['_wpnonce'], $_POST['_wp_http_referer'])) || //WooCommerce recovery password form
		(isset($_POST['woocommerce-login-nonce'], $_POST['login'], $_POST['password'], $_POST['_wp_http_referer'])) || //WooCommerce login form
		(isset($_POST['provider'], $_POST['authcode']) && $_POST['provider'] == 'Two_Factor_Totp') || //TwoFactor authorization
		(isset($_GET['wc-ajax']) && $_GET['wc-ajax'] == 'sa_wc_buy_now_get_ajax_buy_now_button') || //BuyNow add to cart
        apbct_is_in_uri('/wp-json/wpstatistics/v1/hit') || //WPStatistics
		(isset($_POST['ihcaction']) && $_POST['ihcaction'] == 'login') || //Skip login form
		(isset($_POST['action']) && $_POST['action'] == 'infinite_scroll') || //Scroll
		isset($_POST['gform_submit']) || //Skip gravity checking because of direct integration
		(isset($_POST['lrm_action']) && $_POST['lrm_action'] == 'login') || //Skip login form
        apbct_is_in_uri( 'xmlrpc.php?for=jetpack' ) ||
        apbct_is_in_uri( 'connector=bridge&task=put_sql' )
        ) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return null;
    }

    $message = ct_get_fields_any_postdata($_POST);

	// ???
    if(strlen(json_encode($message))<10) {
        do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return null;
    }


	// Skip if request contains params
    $skip_params = array(
	    'ipn_track_id',   // PayPal IPN #
	    'txn_type',       // PayPal transaction type
	    'payment_status', // PayPal payment status
    );
    foreach($skip_params as $key=>$value){
   		if(@array_key_exists($value,$_GET)||@array_key_exists($value,$_POST)) {
            do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
            return null;
        }
   	}

    $base_call_result = apbct_base_call(
		array(
			'message'         => $message,
			'post_info'       => array('comment_type' => 'feedback_general_postdata'),
		)
	);

    $cleantalk_executed=true;

    $ct_result = $base_call_result['ct_result'];

    if ($ct_result->allow == 0) {

        if (!(defined( 'DOING_AJAX' ) && DOING_AJAX)) {
            global $ct_comment;
            $ct_comment = $ct_result->comment;
            if(isset($_POST['cma-action'])&&$_POST['cma-action']=='add')
            {
            	$result=Array('success'=>0, 'thread_id'=>null,'messages'=>Array($ct_result->comment));
            	header("Content-Type: application/json");
				print json_encode($result);
				die();
            }
            else
            {
            	ct_die(null, null);
            }
        } else {
            echo $ct_result->comment;
        }
        exit;
    }

    return null;
}


/**
 * Inner function - Finds and returns pattern in string
 * @return null|bool
 */
function ct_get_data_from_submit($value = null, $field_name = null) {
    if (!$value || !$field_name || !is_string($value)) {
        return false;
    }
    if (preg_match("/[a-z0-9_\-]*" . $field_name. "[a-z0-9_\-]*$/", $value)) {
        return true;
    }
}

/**
 * Sends error notice to admin
 * @return null
 */
function ct_send_error_notice ($comment = '') {
    global $ct_admin_notoice_period, $apbct;

    $timelabel_reg = intval( get_option('cleantalk_timelabel_reg') );
    if(time() - $ct_admin_notoice_period > $timelabel_reg){
        update_option('cleantalk_timelabel_reg', time());

        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $message  = __('Attention, please!', 'cleantalk-spam-protect') . "\r\n\r\n";
        $message .= sprintf(__('"%s" plugin error on your site "%s":', 'cleantalk-spam-protect'), $apbct->plugin_name, $blogname) . "\r\n\r\n";
        $message .= preg_replace('/^(.*?)<a.*?"(.*?)".*?>(.*?)<.a>(.*)$/', '$1. $3: $2?user_token='. $apbct->user_token .' $4', $comment) . "\r\n\r\n";
        @wp_mail(ct_get_admin_email(), sprintf(__('[%s] "%s" error!', 'cleantalk-spam-protect'), $apbct->plugin_name, $blogname), $message);
    }

    return null;
}

/**
 * Prints form for "protect externals
 *
 * @param $arr
 * @param $k
 */
function ct_print_form( $arr, $k ){
	
	// Fix for pages04.net forms
	if( isset( $arr['formSourceName'] ) ){
		$tmp = array();
		foreach( $arr as $key => $val ){
			$tmp_key = str_replace( '_', '+', $key );
			$tmp[$tmp_key] = $val;
		}
		$arr = $tmp;
		unset( $tmp, $key, $tmp_key, $val );
	}
	
	foreach( $arr as $key => $value ){
		
		if( ! is_array( $value ) ){
			print '<textarea
				name="' . ( $k == '' ? $key : $k . '[' . $key . ']' ) . '"
				style="display:none;">' . htmlspecialchars( $value )
	        . '</textarea>';
		}else{
			ct_print_form( $value, $k == '' ? $key : $k . '[' . $key . ']' );
		}
		
	}
	
}

/**
 * Attaches public scripts and styles.
 */
function ct_enqueue_scripts_public($hook){

	global $current_user, $apbct;

	if (apbct_exclusions_check__url()) {
		return;
	}
	
	if($apbct->settings['registrations_test'] || $apbct->settings['comments_test'] || $apbct->settings['contact_forms_test'] || $apbct->settings['general_contact_forms_test'] || $apbct->settings['wc_checkout_test'] || $apbct->settings['check_external'] || $apbct->settings['check_internal'] || $apbct->settings['bp_private_messages'] || $apbct->settings['general_postdata_test']){

		if( ! $apbct->public_script_loaded ) {
			
			// Differnt JS params
			wp_enqueue_script( 'ct_public', APBCT_URL_PATH . '/js/apbct-public.min.js', array( 'jquery' ), APBCT_VERSION, false /*in header*/ );
			wp_enqueue_script('cleantalk-modal', plugins_url( '/cleantalk-spam-protect/js/cleantalk-modal.min.js' ),   array(),     APBCT_VERSION, false );
			
			wp_localize_script('ct_public', 'ctPublic', array(
				'_ajax_nonce' => wp_create_nonce('ct_secret_stuff'),
				'_ajax_url'   => admin_url('admin-ajax.php'),
			));
		}
		
		// GDPR script
		if($apbct->settings['gdpr_enabled']){

			wp_enqueue_script('ct_public_gdpr', APBCT_URL_PATH.'/js/apbct-public--gdpr.min.js', array('jquery', 'ct_public'), APBCT_VERSION, false /*in header*/);

			wp_localize_script('ct_public_gdpr', 'ctPublicGDPR', array(
				'gdpr_forms' => array(),
				'gdpr_text'  => $apbct->settings['gdpr_text'] ? $apbct->settings['gdpr_text'] : __('By using this form you agree with the storage and processing of your data by using the Privacy Policy on this website.', 'cleantalk-spam-protect'),
			));
		}

	}

	if(!defined('CLEANTALK_AJAX_USE_FOOTER_HEADER') || (defined('CLEANTALK_AJAX_USE_FOOTER_HEADER') && CLEANTALK_AJAX_USE_FOOTER_HEADER)){
		if($apbct->settings['use_ajax'] && ! apbct_is_in_uri('.xml') && ! apbct_is_in_uri('.xsl')){
			if( ! apbct_is_in_uri('jm-ajax') ){

				// Use AJAX for JavaScript check
				if($apbct->settings['use_ajax']){

					wp_enqueue_script('ct_nocache',  plugins_url('/cleantalk-spam-protect/js/cleantalk_nocache.min.js'),  array(),         APBCT_VERSION, false /*in header*/);

					wp_localize_script('ct_nocache', 'ctNocache', array(
						'ajaxurl'                  => admin_url('admin-ajax.php'),
						'info_flag'                => $apbct->settings['collect_details'] && $apbct->settings['set_cookies'] ? true : false,
						'set_cookies_flag'         => $apbct->settings['set_cookies'] ? false : true,
						'blog_home'                => get_home_url().'/',
					));
				}

				// External forms check
				if($apbct->settings['check_external'])
					wp_enqueue_script('ct_external',  plugins_url('/cleantalk-spam-protect/js/cleantalk_external.min.js'), array('jquery'), APBCT_VERSION, false /*in header*/);

				// Internal forms check
				if($apbct->settings['check_internal'])
					wp_enqueue_script('ct_internal',  plugins_url('/cleantalk-spam-protect/js/cleantalk_internal.min.js'), array('jquery'), APBCT_VERSION, false /*in header*/);

			}
		}
	}

	// Show controls for commentaries
	if(in_array("administrator", $current_user->roles)){

		if($apbct->settings['manage_comments_on_public_page']){

			$ajax_nonce = wp_create_nonce( "ct_secret_nonce" );

			wp_enqueue_style ('ct_public_admin_css', plugins_url('/cleantalk-spam-protect/css/cleantalk-public-admin.min.css'), array(),         APBCT_VERSION, 'all');
			wp_enqueue_script('ct_public_admin_js',  plugins_url('/cleantalk-spam-protect/js/cleantalk-public-admin.min.js'),   array('jquery'), APBCT_VERSION, false /*in header*/);

			wp_localize_script('ct_public_admin_js', 'ctPublicAdmin', array(
				'ct_ajax_nonce'               => $ajax_nonce,
				'ajaxurl'                     => admin_url('admin-ajax.php'),
				'ct_feedback_error'           => __('Error occurred while sending feedback.', 'cleantalk-spam-protect'),
				'ct_feedback_no_hash'         => __('Feedback wasn\'t sent. There is no associated request.', 'cleantalk-spam-protect'),
				'ct_feedback_msg'             => sprintf(__("Feedback has been sent to %sCleanTalk Dashboard%s.", 'cleantalk-spam-protect'), $apbct->user_token ? "<a target='_blank' href=https://cleantalk.org/my/show_requests?user_token={$apbct->user_token}&cp_mode=antispam>" : '', $apbct->user_token ? "</a>" : ''),
			));

		}
	}

	// Debug
	if($apbct->settings['debug_ajax']){
		wp_enqueue_script('ct_debug_js',  plugins_url('/cleantalk-spam-protect/js/cleantalk-debug-ajax.min.js'), array('jquery'), APBCT_VERSION, false /*in header*/);

		wp_localize_script('ct_debug_js', 'apbctDebug', array(
			'reload'                  => false,
			'reload_time'             => 10000,
		));
	}
}

/**
 * Reassign callbackback function for the bootom of comment output.
 */
function ct_wp_list_comments_args($options){

	global $current_user, $apbct;
	
	if(in_array("administrator", $current_user->roles)){
		if($apbct->settings['manage_comments_on_public_page']) {
			$theme = wp_get_theme();
			$apbct->active_theme = $theme->get( 'Name' );
			$options['end-callback'] = 'ct_comments_output';
		}
	}
	
	return $options;
}

/**
 * Callback function for the bootom comment output.
 */
function ct_comments_output($curr_comment, $param2, $wp_list_comments_args){
	
	global $apbct;
	
	$email   = $curr_comment->comment_author_email;
	$ip      = $curr_comment->comment_author_IP;
	$id      = $curr_comment->comment_ID;

	$settings_link = '/wp-admin/'.(is_network_admin() ? "settings.php?page=cleantalk" : "options-general.php?page=cleantalk");

	echo "<div class='ct_comment_info'><div class ='ct_comment_titles'>";
		echo "<p class='ct_comment_info_title'>".__('Sender info', 'cleantalk-spam-protect')."</p>";

		echo "<p class='ct_comment_logo_title'>
				".__('by', 'cleantalk-spam-protect')
				." <a href='{$settings_link}' target='_blank'><img class='ct_comment_logo_img' src='".plugins_url()."/cleantalk-spam-protect/inc/images/logo_color.png'></a>"
				." <a href='{$settings_link}' target='_blank'>CleanTalk</a>"
			."</p></div>";
		// Outputs email if exists
		if($email)
			echo "<a href='https://cleantalk.org/blacklists/$email' target='_blank' title='https://cleantalk.org/blacklists/$email'>"
			."$email"
				."&nbsp;<img src='".plugins_url()."/cleantalk-spam-protect/inc/images/new_window.gif' border='0' style='float:none; box-shadow: transparent 0 0 0 !important;'/>"
			."</a>";
		else
			echo __('No email', 'cleantalk-spam-protect');
		echo "&nbsp;|&nbsp;";

		// Outputs IP if exists
		if($ip)
			echo "<a href='https://cleantalk.org/blacklists/$ip' target='_blank' title='https://cleantalk.org/blacklists/$ip'>"
			."$ip"
				."&nbsp;<img src='".plugins_url()."/cleantalk-spam-protect/inc/images/new_window.gif' border='0' style='float:none; box-shadow: transparent 0 0 0 !important;'/>"
			."</a>";
		else
			echo __('No IP', 'cleantalk-spam-protect');
		echo '&nbsp;|&nbsp;';

		echo "<span commentid='$id' class='ct_this_is ct_this_is_spam' href='#'>".__('Mark as spam', 'cleantalk-spam-protect')."</span>";
		echo "<span commentid='$id' class='ct_this_is ct_this_is_not_spam ct_hidden' href='#'>".__('Unspam', 'cleantalk-spam-protect')."</span>";
		echo "<p class='ct_feedback_wrap'>";
			echo "<span class='ct_feedback_result ct_feedback_result_spam'>".__('Marked as spam.', 'cleantalk-spam-protect')."</span>";
			echo "<span class='ct_feedback_result ct_feedback_result_not_spam'>".__('Marked as not spam.', 'cleantalk-spam-protect')."</span>";
			echo "&nbsp;<span class='ct_feedback_msg'><span>";
		echo "</p>";

	echo "</div>";
	
	// @todo research what such themes and make exception for them
	$ending_tag = $wp_list_comments_args['style'];
	if( in_array( $apbct->active_theme, array( 'Paperio', 'Twenty Twenty' ) ) ){
		$ending_tag = is_null($wp_list_comments_args['style']) ? 'div' : $wp_list_comments_args['style'];
	};
	
	// Ending comment output
	echo "</{$ending_tag}>";
}

/**
 * Callback function for the bootom comment output.
 *
 * attrs = array()
 */
function apbct_shrotcode_handler__GDPR_public_notice__form( $attrs ){

	$out = '';

	if(isset($attrs['id']))
		$out .= 'ctPublicGDPR.gdpr_forms.push("'.$attrs['id'].'");';

	if(isset($attrs['text']))
		$out .= 'ctPublicGDPR.gdpr_text = "'.$attrs['text'].'";';

	$out = '<script ' . ( class_exists('Cookiebot_WP') ? 'data-cookieconsent="ignore"' : '' ) . '>'.$out.'</script>';
	return $out;
}

/**
 *  Filters the 'status' array before register the user
 *  using only by WICITY theme
 *
 * @param $success    array            array( 'status' => 'success' )
 * @param $data       array            ['username'] ['password'] ['email']
 * @return            array            array( 'status' => 'error' ) or array( 'status' => 'success' ) by default
 */
function apbct_wilcity_reg_validation( $success, $data ) {
	$check = ct_test_registration( $data['username'], $data['email'], '' );
	if( $check['allow'] == 0 ) {
		return array( 'status' => 'error' );
	}
	return $success;
}

// Enfold Theme contact form
function apbct_form__enfold_contact_form__test_spam( $send, $new_post, $form_params, $obj ){

	global $cleantalk_executed;

	$url_decoded_data = array();
	foreach( $new_post as $key => $value ) {
		$url_decoded_data[$key] = urldecode($value);
	}

	$data = ct_get_fields_any( $url_decoded_data );

	$base_call_result = apbct_base_call(
		array(
			'message'         => !empty( $data['message'] )  ? json_encode( $data['message'] ) : '',
			'sender_email'    => !empty( $data['email'] )    ? $data['email']                  : '',
			'sender_nickname' => !empty( $data['nickname'] ) ? $data['nickname']               : '',
			'post_info'       => array(
				'comment_type' => 'contact_form_wordpress_enfold'
			),
		)
	);

	$ct_result = $base_call_result['ct_result'];

	$cleantalk_executed = true;

	if( $ct_result->allow == 0 ) {
		$obj->submit_error = $ct_result->comment;
		return null;
	}

	return $send;

}

// Profile Builder integration
function apbct_form_profile_builder__check_register ( $errors, $fields, $global_request ){

    if( isset( $global_request['action'] ) && $global_request['action'] == 'register' ) {

        global $cleantalk_executed;

        $data = ct_get_fields_any( $global_request );

        $base_call_result = apbct_base_call(
            array(
                'message'         => !empty( $data['message'] )  ? json_encode( $data['message'] ) : '',
                'sender_email'    => !empty( $data['email'] )    ? $data['email']                  : '',
                'sender_nickname' => !empty( $data['nickname'] ) ? $data['nickname']               : '',
                'post_info'       => array(
                    'comment_type' => 'register_profile_builder'
                ),
            ), true
        );

        $ct_result = $base_call_result['ct_result'];

        $cleantalk_executed = true;

        if( $ct_result->allow == 0 ) {
            $errors['error'] = $ct_result->comment;
        }

    }
    return $errors;

}
