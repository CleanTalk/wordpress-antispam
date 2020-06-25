<?php
global $cleantalk_hooked_actions;

/*
AJAX functions
*/

//$cleantalk_ajax_actions_to_check - array for POST 'actions' we should check.

$cleantalk_ajax_actions_to_check[] = 'qcf_validate_form';			//Quick Contact Form
$cleantalk_ajax_actions_to_check[] = 'amoforms_submit';			//amoForms

//cleantalk_hooked_actions[] - array for POST 'actions' which were direct hooked.

$cleantalk_hooked_actions[] = 'rwp_ajax_action_rating'; //Don't check Reviewer plugin

$cleantalk_hooked_actions[] = 'ct_feedback_comment';

/* MailChimp Premium*/
add_filter('mc4wp_form_errors', 'ct_mc4wp_ajax_hook');

/*hooks for Usernoise Form*/
add_action('un_feedback_form_body', 'ct_add_hidden_fields',1);
add_filter('un_validate_feedback', 'ct_ajax_hook', 1, 2);

/*hooks for AJAX Login & Register email validation*/
add_action( 'wp_ajax_nopriv_validate_email', 'ct_validate_email_ajaxlogin',1 );
add_action( 'wp_ajax_validate_email', 'ct_validate_email_ajaxlogin',1 );
$cleantalk_hooked_actions[]='validate_email';

/*hooks for user registration*/
add_action( 'user_register', 'ct_user_register_ajaxlogin',1 );

/*hooks for WPUF pro */
//add_action( 'wp_ajax_nopriv_wpuf_submit_register', 'ct_wpuf_submit_register',1 );
//add_action( 'wp_ajax_wpuf_submit_register', 'ct_wpuf_submit_register',1 );
add_action( 'wp_ajax_nopriv_wpuf_submit_register', 'ct_ajax_hook',1 );
add_action( 'wp_ajax_wpuf_submit_register', 'ct_ajax_hook',1 );
$cleantalk_hooked_actions[]='submit_register';

/*hooks for MyMail */
//add_action( 'wp_ajax_nopriv_mymail_form_submit', 'ct_mymail_form_submit',1 );
//add_action( 'wp_ajax_mymail_form_submit', 'ct_mymail_form_submit',1 );
add_action( 'wp_ajax_nopriv_mymail_form_submit', 'ct_ajax_hook',1 );
add_action( 'wp_ajax_mymail_form_submit', 'ct_ajax_hook',1 );
$cleantalk_hooked_actions[]='form_submit';

/*hooks for MailPoet */
//add_action( 'wp_ajax_nopriv_wysija_ajax', 'ct_wysija_ajax',1 );
//add_action( 'wp_ajax_wysija_ajax', 'ct_wysija_ajax',1 );
add_action( 'wp_ajax_nopriv_wysija_ajax', 'ct_ajax_hook',1 );
add_action( 'wp_ajax_wysija_ajax', 'ct_ajax_hook',1 );
$cleantalk_hooked_actions[]='wysija_ajax';

/*hooks for cs_registration_validation */
//add_action( 'wp_ajax_nopriv_cs_registration_validation', 'ct_cs_registration_validation',1 );
//add_action( 'wp_ajax_cs_registration_validation', 'ct_cs_registration_validation',1 );
add_action( 'wp_ajax_nopriv_cs_registration_validation', 'ct_ajax_hook',1 );
add_action( 'wp_ajax_cs_registration_validation', 'ct_ajax_hook',1 );
$cleantalk_hooked_actions[]='cs_registration_validation';

/*hooks for send_message and request_appointment */
//add_action( 'wp_ajax_nopriv_send_message', 'ct_sm_ra',1 );
//add_action( 'wp_ajax_send_message', 'ct_sm_ra',1 );
//add_action( 'wp_ajax_nopriv_request_appointment', 'ct_sm_ra',1 );
//add_action( 'wp_ajax_request_appointment', 'ct_sm_ra',1 );
add_action( 'wp_ajax_nopriv_send_message', 'ct_ajax_hook',1 );
add_action( 'wp_ajax_send_message', 'ct_ajax_hook',1 );
add_action( 'wp_ajax_nopriv_request_appointment', 'ct_ajax_hook',1 );
add_action( 'wp_ajax_request_appointment', 'ct_ajax_hook',1 );
$cleantalk_hooked_actions[]='send_message';
$cleantalk_hooked_actions[]='request_appointment';

/*hooks for zn_do_login */
//add_action( 'wp_ajax_nopriv_zn_do_login', 'ct_zn_do_login',1 );
//add_action( 'wp_ajax_zn_do_login', 'ct_zn_do_login',1 );
add_action( 'wp_ajax_nopriv_zn_do_login', 'ct_ajax_hook',1 );
add_action( 'wp_ajax_zn_do_login', 'ct_ajax_hook',1 );
$cleantalk_hooked_actions[]='zn_do_login';

/*hooks for zn_do_login */
//add_action( 'wp_ajax_nopriv_cscf-submitform', 'ct_cscf_submitform',1 );
//add_action( 'wp_ajax_cscf-submitform', 'ct_cscf_submitform',1 );
if(isset($_POST['action']) && $_POST['action'] == 'cscf-submitform'){
	add_filter('preprocess_comment', 'ct_ajax_hook', 1);
	//add_action( 'wp_ajax_nopriv_cscf-submitform', 'ct_ajax_hook',1 );
	//add_action( 'wp_ajax_cscf-submitform', 'ct_ajax_hook',1 );
	$cleantalk_hooked_actions[]='cscf-submitform';
}


/*hooks for visual form builder */
//add_action( 'wp_ajax_nopriv_vfb_submit', 'ct_vfb_submit',1 );
//add_action( 'wp_ajax_vfb_submit', 'ct_vfb_submit',1 );
add_action( 'wp_ajax_nopriv_vfb_submit', 'ct_ajax_hook',1 );
add_action( 'wp_ajax_vfb_submit', 'ct_ajax_hook',1 );
$cleantalk_hooked_actions[]='vfb_submit';

/*hooks for woocommerce_checkout*/
add_action( 'wp_ajax_nopriv_woocommerce_checkout', 'ct_ajax_hook',1 );
add_action( 'wp_ajax_woocommerce_checkout', 'ct_ajax_hook',1 );
$cleantalk_hooked_actions[]='woocommerce_checkout';
$cleantalk_hooked_actions[]='wcfm_ajax_controller';

/*hooks for frm_action*/
add_action( 'wp_ajax_nopriv_frm_entries_create', 'ct_ajax_hook',1 );
add_action( 'wp_ajax_frm_entries_create', 'ct_ajax_hook',1 );
$cleantalk_hooked_actions[]='frm_entries_create';

add_action( 'wp_ajax_nopriv_td_mod_register', 'ct_ajax_hook',1  );
add_action( 'wp_ajax_td_mod_register', 'ct_ajax_hook',1  );
$cleantalk_hooked_actions[]='td_mod_register';

/*hooks for tevolution theme*/
add_action( 'wp_ajax_nopriv_tmpl_ajax_check_user_email', 'ct_ajax_hook',1  );
add_action( 'wp_ajax_tmpl_ajax_check_user_email', 'ct_ajax_hook',1  );
add_action( 'wp_ajax_nopriv_tevolution_submit_from_preview', 'ct_ajax_hook',1  );
add_action( 'wp_ajax_tevolution_submit_from_preview', 'ct_ajax_hook',1  );
add_action( 'wp_ajax_nopriv_submit_form_recaptcha_validation', 'ct_ajax_hook',1  );
add_action( 'wp_ajax_tmpl_submit_form_recaptcha_validation', 'ct_ajax_hook',1  );
$cleantalk_hooked_actions[]='tmpl_ajax_check_user_email';
$cleantalk_hooked_actions[]='tevolution_submit_from_preview';
$cleantalk_hooked_actions[]='submit_form_recaptcha_validation';

/* hooks for contact forms by web settler ajax*/
add_action( 'wp_ajax_nopriv_smuzform-storage', 'ct_ajax_hook',1  );
$cleantalk_hooked_actions[]='smuzform_form_submit';

/* hooks for reviewer plugin*/
add_action( 'wp_ajax_nopriv_rwp_ajax_action_rating', 'ct_ajax_hook',1 );
$cleantalk_hooked_actions[]='rwp-submit-wrap';

$cleantalk_hooked_actions[]='post_update';

/* Ninja Forms hoocked actions */
$cleantalk_hooked_actions[]='ninja_forms_ajax_submit';
$cleantalk_hooked_actions[]='nf_ajax_submit';
$cleantalk_hooked_actions[]='ninja_forms_process'; // Depricated ?

/* Follow-Up Emails */
$cleantalk_hooked_actions[] = 'fue_wc_set_cart_email';  // Don't check email via this plugin

/* Follow-Up Emails */
$cleantalk_hooked_actions[] = 'fue_wc_set_cart_email';  // Don't check email via this plugin

/* The Fluent Form have the direct integration */
$cleantalk_hooked_actions[] = 'fluentform_submit';

function ct_validate_email_ajaxlogin($email=null, $is_ajax=true){
	
	$email = is_null( $email ) ? $email : $_POST['email'];
	$email = sanitize_email($email);
	$is_good = !filter_var($email, FILTER_VALIDATE_EMAIL) || email_exists($email) ? false : true;
	
	if(class_exists('AjaxLogin')&&isset($_POST['action'])&&$_POST['action']=='validate_email'){
		
		$checkjs = apbct_js_test('ct_checkjs', $_POST);
	    $sender_info['post_checkjs_passed'] = $checkjs;
		if ($checkjs === null){
			$checkjs = apbct_js_test('ct_checkjs', $_COOKIE);
			$sender_info['cookie_checkjs_passed'] = $checkjs;
		}
		
		//Making a call
		$base_call_result = apbct_base_call(
			array(
				'sender_email'    => $email,
				'sender_nickname' => '',
				'sender_info'     => $sender_info,
				'js_on'           => $checkjs,
			),
			true
		);
		
		$ct_result = $base_call_result['ct_result'];
		
		if ($ct_result->allow===0){
			$is_good=false;
		}
	}
	
	if($is_good){
		$ajaxresult=array(
            'description' => null,
            'cssClass' => 'noon',
            'code' => 'success'
            );
	}else{
		$ajaxresult=array(
            'description' => 'Invalid Email',
            'cssClass' => 'error-container',
            'code' => 'error'
            );
	}
	
	$ajaxresult = json_encode($ajaxresult);
	print $ajaxresult;
	wp_die();
}

function ct_user_register_ajaxlogin($user_id)
{
	if(class_exists('AjaxLogin')&&isset($_POST['action'])&&$_POST['action']=='register_submit')
	{
	    
		$checkjs = apbct_js_test('ct_checkjs', $_POST);
	    $sender_info['post_checkjs_passed'] = $checkjs;
		if ($checkjs === null){
			$checkjs = apbct_js_test('ct_checkjs', $_COOKIE);
			$sender_info['cookie_checkjs_passed'] = $checkjs;
		}
		
		//Making a call
		$base_call_result = apbct_base_call(
			array(
				'sender_email'    => sanitize_email($_POST['email']),
				'sender_nickname' => sanitize_email($_POST['login']),
				'sender_info'     => $sender_info,
				'js_on'           => $checkjs,
			),
			true
		);
		
		$ct_result = $base_call_result['ct_result'];
		
		if ($ct_result->allow === 0)
		{
			wp_delete_user($user_id);
		}
	}
	return $user_id;
}

/**
 * Hook into MailChimp for WordPress `mc4wp_form_errors` filter.
 *
 * @param array $errors
 * @return array
 */
function ct_mc4wp_ajax_hook( array $errors )
{
	$result = ct_ajax_hook();

	// only return modified errors array when function returned a string value (the message key)
	if( is_string( $result ) ) {
		$errors[] = $result;
	}

	return $errors;
}

function ct_ajax_hook($message_obj = false, $additional = false)
{
	global $apbct, $current_user;
	
	$message_obj = (array)$message_obj;
	
	// Get current_user and set it globaly
	apbct_wp_set_current_user($current_user instanceof WP_User ? $current_user	: apbct_wp_get_current_user() );
	
    // $_REQUEST['action'] to skip. Go out because of not spam data
    $skip_post = array(
        'apbct_js_keys__get',  // Our service code
        'gmaps_display_info_window',  // Geo My WP pop-up windows.
        'gmw_ps_display_info_window',  // Geo My WP pop-up windows.
        'the_champ_user_auth',  // Super Socializer
        'simbatfa-init-otp', //Two-Factor Auth
        'wppb_msf_check_required_fields', //ProfileBuilder skip step checking
        'boss_we_login', //Login form
        'sidebar_login_process', // Login CF7
		'cp_update_style_settings', // Convert Pro. Saving settings
		'updraft_savesettings', // UpdraftPlus
		'wpdUpdateAutomatically', //Comments update
		'upload-attachment', // Skip ulpload attachments
		'iwj_update_profile', //Skip profile page checker
		'st_partner_create_service', //Skip add hotel via admin
		'vp_ajax_vpt_option_save', // https://themeforest.net/item/motor-vehicles-parts-equipments-accessories-wordpress-woocommerce-theme/16829946
		'mailster_send_test', //Mailster send test admin
		'acf/validate_save_post', //ACF validate post admin
		'admin:saveThemeOptions', //Ait-theme admin checking
		'save_tourmaster_option', //Tourmaster admin save
	    'validate_register_email', // Service id #313320
	    'elementor_pro_forms_send_form', //Elementor Pro
	    'phone-orders-for-woocommerce', //Phone orders for woocommerce backend
	    'ihc_check_reg_field_ajax', //Ajax check required fields
	    'OSTC_lostPassword', //Lost password ajax form
		'check_retina_image_availability', //There are too many ajax requests from mobile
	    'uap_check_reg_field_ajax', // Ultimate Affiliate Pro. Form validation.
	    'edit-comment', // Edit comments by admin ??? that shouldn't happen
	    'formcraft3_save_form_progress', // FormCraft – Contact Form Builder for WordPress. Save progress.
	    'wpdmpp_save_settings', // PayPal save settings.
	    'iwj_login', // Fix for unknown plugin for user #133315
	    'custom_user_login', // Fix for unknown plugin for user #466875
	    'wordfence_ls_authenticate', //Fix for wordfence auth
	    'frm_strp_amount', //Admin stripe form
	    'wouCheckOnlineUsers', //Skip updraft admin checking users
	    'et_fb_get_shortcode_from_fb_object', //Skip generate shortcode
	    'pp_lf_process_login', //Skip login form
	    'check_email', //Ajax email checking
	    'dflg_do_sign_in_user', // Unknown plugin
	    'cartflows_save_cart_abandonment_data', // WooCommerce cartflow
	    'rcp_process_register_form', // WordPress Membership Plugin – Restrict Content
	    'give_process_donation', // GiveWP
	    'apus_ajax_login', // ???? plugin authorization
        'bookly_save_customer', //bookly
        'postmark_test', //Avocet
        'postmark_save', //Avocet
        'ck_get_subscriber', //ConvertKit checking the subscriber
    );
    
    // Skip test if
    if( !$apbct->settings['general_contact_forms_test'] || // Test disabled
        !apbct_is_user_enable($apbct->user) || // User is admin, editor, author
	    // (function_exists('get_current_user_id') && get_current_user_id() != 0) || // Check with default wp_* function if it's admin
	    (!$apbct->settings['protect_logged_in'] && ($apbct->user instanceof WP_User) && $apbct->user->ID !== 0 ) || // Logged in user
        apbct_exclusions_check__url() || // url exclusions
        (isset($_POST['action']) && in_array($_POST['action'], $skip_post)) || // Special params
	    (isset($_GET['action'])  && in_array($_GET['action'], $skip_post)) ||  // Special params
		isset($_POST['quform_submit']) || //QForms multi-paged form skip
        // QAEngine Theme fix
        ( strval(current_action()) != 'et_pre_insert_answer' &&
	        (
		        (isset($message_obj['author']) && intval($message_obj['author']) == 0) ||
		        (isset($message_obj['post_author']) && intval($message_obj['post_author']) == 0)
	        )
        ) ||
        (isset($_POST['action'], $_POST['arm_action']) && $_POST['action'] == 'arm_shortcode_form_ajax_action' && $_POST['arm_action'] == 'please-login') //arm forms skip login
    )
    {
	    do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
        return false;
    }

    //General post_info for all ajax calls
	$post_info = array(
	    'comment_type' => 'feedback_ajax',
        'post_url' => apbct_get_server_variable( 'HTTP_REFERER' ), // Page URL must be an previous page
    );

	$checkjs = apbct_js_test('ct_checkjs', $_COOKIE);
		
    if(isset($_POST['user_login']))
		$sender_nickname = $_POST['user_login'];
	else
		$sender_nickname = '';
	
	//QAEngine Theme answers
    if( !empty($message_obj) && isset($message_obj['post_type'], $message_obj['post_content']) ){
		$curr_user = get_user_by('id', $message_obj['author']);
		if (!$curr_user)
			$curr_user = get_user_by('id', $message_obj['post_author']);			
		$ct_post_temp['comment'] = $message_obj['post_content'];
        $ct_post_temp['email'] = $curr_user->data->user_email;
		$ct_post_temp['name'] = $curr_user->data->user_login;
    }
	
    //CSCF fix
    if(isset($_POST['action']) && $_POST['action']== 'cscf-submitform'){
		$ct_post_temp[] = $message_obj['comment_author'];
        $ct_post_temp[] = $message_obj['comment_author_email'];
		$ct_post_temp[] = $message_obj['comment_content'];
    }
		
	//??? fix
    if(isset($_POST['action'], $_POST['target']) && ($_POST['action']=='request_appointment'||$_POST['action']=='send_message')){
		$ct_post_temp=$_POST;
    	$ct_post_temp['target']=1;
    }
  	
	//UserPro fix
	if(isset($_POST['action'], $_POST['template']) && $_POST['action']=='userpro_process_form' && $_POST['template']=='register'){
		$ct_post_temp = $_POST;
		$ct_post_temp['shortcode'] = '';
	}
	//Reviewer fix
	if(isset($_POST['action']) && $_POST['action'] == 'rwp_ajax_action_rating')
	{
		$ct_post_temp['name'] = $_POST['user_name'];
		$ct_post_temp['email'] = $_POST['user_email'];
		$ct_post_temp['comment'] = $_POST['comment'];
	}
	//Woocommerce checkout
	if(isset($_POST['action']) && $_POST['action']=='woocommerce_checkout'){
		$post_info['comment_type'] = 'order';
	}
	//Easy Forms for Mailchimp
	if( \Cleantalk\Common\Post::get('action') == 'process_form_submission' ){
		$post_info['comment_type'] = 'contact_enquire_wordpress_easy_forms_for_mailchimp';
		if( \Cleantalk\Common\Post::get('form_data') ) {
			$form_data = explode( '&', urldecode( \Cleantalk\Common\Post::get('form_data') ) );
			$form_data_arr = array();
			foreach ( $form_data as $val ) {
				$form_data_element = explode( '=', $val );
				$form_data_arr[$form_data_element[0]] = @$form_data_element[1];
			}
			if( isset( $form_data_arr['EMAIL'] ) )
				$ct_post_temp['email'] = $form_data_arr['EMAIL'];
			if( isset( $form_data_arr['FNAME'] ) )
				$ct_post_temp['nickname'] = $form_data_arr['FNAME'];
		}
	}
	if (isset($_POST['action']) && $_POST['action'] == 'ufbl_front_form_action'){
		foreach ($ct_post_temp as $key => $value) {
			if (preg_match('/form_data_\d_name/', $key)) 
				unset($ct_post_temp[$key]);
		}
	}
	
	$ct_temp_msg_data = isset($ct_post_temp)
		? ct_get_fields_any($ct_post_temp)
		: ct_get_fields_any($_POST);

	$sender_email    = ($ct_temp_msg_data['email']    ? $ct_temp_msg_data['email']    : '');
	$sender_nickname = ($ct_temp_msg_data['nickname'] ? $ct_temp_msg_data['nickname'] : '');
	$subject         = ($ct_temp_msg_data['subject']  ? $ct_temp_msg_data['subject']  : '');
	$contact_form    = ($ct_temp_msg_data['contact']  ? $ct_temp_msg_data['contact']  : true);
	$message         = ($ct_temp_msg_data['message']  ? $ct_temp_msg_data['message']  : array());
    if($subject != '') {
        $message['subject'] = $subject;
    }
    
    // Skip submission if no data found
    if ($sender_email === ''|| !$contact_form) {
	    do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
	    return false;
    }

	
	 // Mailpoet fix
    if (isset($message['wysijaData'], $message['wysijaplugin'], $message['task'], $message['controller']) && $message['wysijaplugin'] == 'wysija-newsletters' && $message['controller'] == 'campaigns') {
	    do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
    	return false;
    }

    // Mailpoet3 admin skip fix
    if (isset($_POST['action'], $_POST['method']) && $_POST['action'] == 'mailpoet' && $_POST['method'] =='save') {
	    do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
    	return false;
    }

	
	// WP Foto Vote Fix
	if (!empty($_FILES)){
		foreach($message as $key => $value){
			if(strpos($key, 'oje') !== false) {
				do_action( 'apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST );
				return false;
			}
		} unset($key ,$value);
	}
	
	/**
	 * @todo Contact form detect
	 */
	// Detect contact form an set it's name to $contact_form to use later
	$contact_form = null;
	foreach($_POST as $param => $value){
		if(strpos($param, 'et_pb_contactform_submit') === 0){
			$contact_form = 'contact_form_divi_theme';
			$contact_form_additional = str_replace($param, '', $param);
		}
		if(strpos($param, 'avia_generated_form') === 0){
			$contact_form = 'contact_form_enfold_theme';
			$contact_form_additional = str_replace('avia_generated_form', '', $param);
		}
		if(!empty($contact_form))
			break;
	}
	
	$base_call_result = apbct_base_call(
		array(
			'message'         => $message,
			'sender_email'    => $sender_email,
			'sender_nickname' => $sender_nickname,
			'sender_info'     => array('post_checkjs_passed' => $checkjs),
			'post_info'       => $post_info,
			'js_on'           => $checkjs,
		)
	);
	$ct_result = $base_call_result['ct_result'];
	
	if ($ct_result->allow == 0)
	{
		if(isset($_POST['action']) && $_POST['action']=='wpuf_submit_register'){
			$result=Array('success'=>false,'error'=>$ct_result->comment);
			@header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
			print json_encode($result);
			die();
		}
		else if(isset($_POST['action']) && $_POST['action']=='mymail_form_submit')
		{
			$result=Array('success'=>false,'html'=>$ct_result->comment);
			@header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
			print json_encode($result);
			die();
		}
		else if(isset($_POST['action'], $_POST['task']) && $_POST['action'] == 'wysija_ajax' && $_POST['task'] != 'send_preview' && $_POST['task'] != 'send_test_mail')
		{
			$result=Array('result'=>false,'msgs'=>Array('updated'=>Array($ct_result->comment)));
			//@header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
			print $_GET['callback'].'('.json_encode($result).');';
			die();
		}
		else if(isset($_POST['action']) && $_POST['action']=='cs_registration_validation')
		{
			$result=Array("type"=>"error","message"=>$ct_result->comment);
			print json_encode($result);
			die();
		}
		else if(isset($_POST['action']) && ($_POST['action']=='request_appointment' || $_POST['action']=='send_message'))
		{
			print $ct_result->comment;
			die();
		}
		else if(isset($_POST['action']) && $_POST['action']=='zn_do_login')
		{
			print '<div id="login_error">'.$ct_result->comment.'</div>';
			die();
		}
		else if(isset($_POST['action']) && $_POST['action']=='vfb_submit')
		{
			$result=Array('result'=>false,'message'=>$ct_result->comment);
			@header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
			print json_encode($result);
			die();
		}
		else if(isset($_POST['action']) && $_POST['action']=='woocommerce_checkout')
		{
			print $ct_result->comment;
			die();
		}
		else if(isset($_POST['action']) && $_POST['action']=='frm_entries_create')
		{
			$result=Array('112'=>$ct_result->comment);
			print json_encode($result);
			die();
		}
		else if(isset($_POST['cma-action']) &&  $_POST['cma-action']=='add')
		{
			$result=Array('success'=>0, 'thread_id'=>null,'messages'=>Array($ct_result->comment));
			print json_encode($result);
			die();
		}
		else if(isset($_POST['action']) && $_POST['action']=='td_mod_register')
		{
			print json_encode(array('register', 0, $ct_result->comment));
			die();
		}
		else if(isset($_POST['action']) && $_POST['action']=='tmpl_ajax_check_user_email')
		{
			print "17,email";
			die();
		}
		else if(isset($_POST['action']) && ($_POST['action']=='tevolution_submit_from_preview' || $_POST['action']=='submit_form_recaptcha_validation'))
		{
			print $ct_result->comment;
			die();
		}
		// WooWaitList
		// http://codecanyon.net/item/woowaitlist-woocommerce-back-in-stock-notifier/7103373
		else if(isset($_POST['action']) && $_POST['action']=='wew_save_to_db_callback')
		{
			$result = array();
			$result['error'] = 1;
			$result['message'] = $ct_result->comment;
			$result['code'] = 5; // Unused code number in WooWaitlist
			print json_encode($result);
			die();
		}
		// UserPro
		else if(isset($_POST['action'], $_POST['template']) && $_POST['action']=='userpro_process_form' && $_POST['template']=='register')
		{
			foreach($_POST as $key => $value){
				$output[$key]=$value;
			}unset($key, $value);
			$output['template'] = $ct_result->comment;
			$output=json_encode($output);
			print_r($output);
			die;
		}
		// Quick event manager
		else if(isset($_POST['action']) && $_POST['action']=='qem_validate_form'){
			$errors[] = 'registration_forbidden';
			$result = Array(
				'success' => 'false',
				'errors' => $errors,
				'title' => $ct_result->comment
			);
			print json_encode($result);
			die();
		}
		// Quick Contact Form
		elseif(isset($_POST['action']) && $_POST['action'] == 'qcf_validate_form')
		{
			$result = Array(
				'blurb' => "<h1>".$ct_result->comment."</h1>",
				'display' => "Oops, got a few problems here",
				'errors' => array(
					0 => array(
						'error' => 'error',
						'name' => 'name'
					),
				),
				'success' => 'false',
			);
			print json_encode($result);
			die();
		}
		// Usernoise Contact Form
		elseif(isset($_POST['title'], $_POST['email'], $_POST['type'], $_POST['ct_checkjs']))
		{
			return array($ct_result->comment);
			die();
		}
		// amoForms
		elseif(isset($_POST['action']) && $_POST['action'] == 'amoforms_submit')
		{
			$result = Array(
				'result' => true,
				'type' => "html",
				'value' => "<h1 style='font-size: 25px; color: red;'>".$ct_result->comment."</h1>",
				'fast' => false
			);
			print json_encode($result);
			die();
		}
		// MailChimp for Wordpress Premium
		elseif(!empty($_POST['_mc4wp_form_id']))
		{
			return 'ct_mc4wp_response';
		}
		// QAEngine Theme answers
		elseif ( !empty($message_obj) && isset($message_obj['post_type'], $message_obj['post_content']) ){
			throw new Exception($ct_result->comment);
		}
		//ES Add subscriber
		elseif(isset($_POST['action']) && $_POST['action'] == 'es_add_subscriber')
		{
			$result = Array(
				'error' => 'unexpected-error',
			);
			print json_encode($result);
			die();
		}
		//Convertplug. Strpos because action value dynamically changes and depends on mailing service
		elseif (isset($_POST['action']) && strpos($_POST['action'], '_add_subscriber') !== false){
			$result = Array(
				'action' => "message",
				'detailed_msg' => "",
				'email_status' => false,
				'message' => "<h1 style='font-size: 25px; color: red;'>".$ct_result->comment."</h1>",
				'status' => "error",
				'url' => "none"
			);
			print json_encode($result);
			die();
		}
		// Ultimate Form Builder
		elseif (isset($_POST['action']) && $_POST['action'] == 'ufbl_front_form_action'){
			$result = Array(
				'error_keys' => array(),
				'error_flag' => 1,
				'response_message' => $ct_result->comment
			);
			print json_encode($result);
			die();
		}
		// Smart Forms
		elseif (isset($_POST['action']) && $_POST['action'] == 'rednao_smart_forms_save_form_values'){
			$result = Array(
				'message' => $ct_result->comment,
				'refreshCaptcha' => 'n',
				'success' => 'n'
			);
			print json_encode($result);
			die();
		}
		//cFormsII
		elseif(isset($_POST['action']) && $_POST['action'] == 'submitcform')
		{
			header('Content-Type: application/json');
			$result = Array(
				'no' => isset($_POST['cforms_id']) ? $_POST['cforms_id'] : '',
				'result' => 'failure',
				'html' =>$ct_result->comment,
				'hide' => false,
				'redirection' => null
			);
			print json_encode($result);
			die();
		}
		//Contact Form by Web-Settler
		elseif(isset($_POST['smFieldData']))
		{
			$result = Array(
				'signal' => true,
				'code' => 0,
				'thanksMsg' => $ct_result->comment,
				'errors' => array(),
				'isMsg' => true,
				'redirectUrl' => null
			);
			print json_encode($result);
			die();
		}
		//Reviewer
		elseif(isset($_POST['action']) && $_POST['action'] == 'rwp_ajax_action_rating')
		{
			$result = Array(
				'success' => false,
				'data' => array(0=>$ct_result->comment)
			);
			print json_encode($result);
			die();
		}
		// CouponXXL Theme
		elseif(isset($_POST['_wp_http_referer'], $_POST['register_field'], $_POST['action']) && strpos($_POST['_wp_http_referer'],'/register/account') !== false && $_POST['action'] == 'register'){
			$result = array(
				'message' => '<div class="alert alert-error">'.$ct_result->comment.'</div>',
			);
			die(json_encode($result));
		}
		//ConvertPro
		elseif(isset($_POST['action']) && $_POST['action'] == 'cp_v2_notify_admin' || $_POST['action'] == 'cpro_notify_via_email')
		{
			$result = Array(
				'success' => false,
				'data' => array('error'=>$ct_result->comment,'style_slug'=>'convertprot-form'),
			);
			print json_encode($result);
			die();
		}
		//Easy Forms for Mailchimp
		elseif( isset($_POST['action']) && $_POST['action']=='process_form_submission' ) {
			wp_send_json_error(
				array(
					'error'    => 1,
					'response' => $ct_result->comment
				)
			);
		}
		//Optin wheel
		elseif( isset($_POST['action']) && ($_POST['action'] == 'wof-lite-email-optin' || $_POST['action'] == 'wof-email-optin')) {
			wp_send_json_error(__($ct_result->comment, 'wp-optin-wheel'));
		}
		// Forminator
		elseif( isset($_POST['action']) && strpos($_POST['action'], 'forminator_submit') !== false ){
			wp_send_json_error(
				array(
					'message' => $ct_result->comment,
					'success' => false,
					'errors'  => array(),
					'behav'   => 'behaviour-thankyou',
				)
			);
		}
		else
		{
			die(json_encode(array( 'apbct' => array(
					'blocked' => true,
					'comment' => $ct_result->comment,
					'stop_script' => \Cleantalk\Common\Post::has_string('action', 'tve_leads_ajax_')
						? 1
						: 0
			))));
		}
	}
	//Allow == 1
	else{
		//QAEngine Theme answers
		if ( !empty($message_obj) && isset($message_obj['post_type'], $message_obj['post_content']) ){
			return $message_obj;
		}
		// Force AJAX check
		if( \Cleantalk\Common\Post::get('action') == 'cleantalk_force_ajax_check' ){
			die(json_encode(array( 'apbct' => array(
				'blocked' => false,
				'allow' => true,
			))));
		}
	}
}
