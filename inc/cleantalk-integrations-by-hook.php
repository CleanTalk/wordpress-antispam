<?php

$apbct_active_integrations = array(
    'CleantalkInternalForms'         => array(
        'hook'    => 'ct_check_internal',
        'setting' => 'forms__check_internal',
        'ajax'    => true
    ),
    'CleantalkExternalForms'         => array(
        'hook'    => 'init',
        'setting' => 'forms__check_external',
        'ajax'    => false
    ),
    'CleantalkExternalFormsForceAjax'         => array(
        'hook'    => 'cleantalk_force_ajax_check',
        'setting' => 'forms__check_external',
        'ajax'    => true
    ),
    'CleantalkPreprocessComment'         => array(
        'hook'    => 'preprocess_comment',
        'setting' => 'forms__comments_test',
        'ajax'    => true,
        'ajax_and_post' => true
    ),
    'CleantalkWpDieOnComment'         => array(
        'hook'    => 'wp_die_handler',
        'setting' => 'forms__comments_test',
        'ajax'    => false,
    ),
    'ContactBank'         => array(
        'hook'    => 'contact_bank_frontend_ajax_call',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'FluentForm'          => array(
        'hook' => array('fluentform/before_insert_submission', 'fluentform_before_insert_submission'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'ElfsightContactForm' => array(
        'hook'    => 'elfsight_contact_form_mail',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'EstimationForm'      => array('hook' => 'send_email', 'setting' => 'forms__contact_forms_test', 'ajax' => true),
    'LandingPageBuilder'  => array(
        'hook'    => 'ulpb_formBuilderEmail_ajax',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'Rafflepress'         => array(
        'hook'    => 'rafflepress_lite_giveaway_api',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'SimpleMembership'    => array(
        'hook'    => 'swpm_front_end_registration_complete_user_data',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'WpMembers'           => array(
        'hook'    => 'wpmem_pre_register_data',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'Wpdiscuz'            => array(
        'hook'    => array('wpdAddComment', 'wpdAddInlineComment', 'wpdSaveEditedComment'),
        'setting' => array('forms__comments_test', 'data__protect_logged_in'),
        'ajax'    => true
    ),
    'Forminator'          => array(
        'hook'    => array('forminator_submit_form_custom-forms', 'forminator_spam_protection'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true,
        'ajax_and_post' => true
    ),
    'EaelLoginRegister'   => array(
        'hook'    => array(
            'eael/login-register/before-register',
            'wp_ajax_nopriv_eael/login-register/before-register',
            'wp_ajax_eael/login-register/before-register'
        ),
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'CalculatedFieldsForm' => array(
        'hook'    => 'cpcff_process_data',
        'setting' => 'forms__general_contact_forms_test',
        'ajax'    => false
    ),
    'OvaLogin' => array(
        'hook'    => 'login_form_register',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'GiveWP' => array(
        'hook'    => 'give_checkout_error_checks',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'VisualFormBuilder' => array(
        'hook'    => array('vfb_isbot','vfb_isBot'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'EventsManager' => array(
        'hook'    => array('em_booking_validate_after', 'em_booking_add', 'booking_add'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false,
        'ajax_and_post' => true
    ),
    'PlansoFormBuilder' => array(
        'hook'    => 'psfb_validate_form_request',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'NextendSocialLogin' => array(
        'hook'    => 'nsl_before_register',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'WPUserMeta' => array(
        'hook'    => 'user_meta_pre_user_register',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'MemberPress' => array(
        'hook'    => 'mepr-validate-signup',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'EasyDigitalDownloads' => array(
        'hook'    => array('edd_pre_process_register_form', 'edd_insert_user_args'),
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'WpForo' => array(
        'hook'    => array('wpforo_action_topic_add', 'wpforo_action_post_add'),
        'setting' => 'data__protect_logged_in',
        'ajax'    => false
    ),
    'StrongTestimonials' => array(
        'hook'    => array('wpmtst_form_submission','wpmtst_form2'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true,
        'ajax_and_post' => true
    ),
    'NewUserApprove' => array(
        'hook'    => 'nua_pass_create_new_user',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'SmartForms' => array(
        'hook'    => array('rednao_smart_forms_save_form_values'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'UlitmateFormBuilder' => array(
        'hook'    => array('ufbl_front_form_action', 'ufb_front_form_action'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'Hustle' => array(
        'hook'    => 'hustle_module_form_submit',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'WpBookingSystem' => array(
        'hook'    => 'wpbs_submit_form',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'Supsystic' => array(
        'hook'    => 'contact',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'LeadFormBuilder' => array(
        'hook'    => 'Save_Form_Data',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'ModernEventsCalendar' => array(
        'hook'    => 'mec_book_form',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'IndeedUltimateMembershipPro' => array(
        'hook'    => 'ump_before_register_new_user',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'SendyWidgetPro' => array(
        'hook'    => 'swp_form_submit',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'CleantalkRegisterWidget' => array(
        'hook'    => 'cleantalk_register_widget__get_api_key',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'LatePoint' => array(
        'hook'    => array('latepoint_route_call'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'MailPoet' => array(
        'hook'    => array('mailpoet'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'MailPoet2' => array(
        'hook'    => array('wysija_ajax'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'ElementorUltimateAddonsRegister' => array(
        'hook'    => array('uael_register_user'),
        'setting' => 'forms__registrations_test',
        'ajax'    => true
    ),
    'PiotnetAddonsForElementorPro' => array(
        'hook'    => array('pafe_ajax_form_builder'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'UserRegistrationPro'           => array(
        'hook'    => array('user_registration_before_register_user_action'),
        'setting' => 'forms__registrations_test',
        // important!
        'ajax'    => false
    ),
    'BackInStockNotifier'           => array(
        'hook'    => array('cwginstock_product_subscribe'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'ProductEnquiryPro'           => array(
        'hook'    => array('mgc_pe_sender_email'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'WpGeoDirectory' => array(
        'hook'    => array('geodir_validate_ajax_save_post_data'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'KaliForms'   => array(
        'hook'    => array('kaliforms_form_process'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'ClassifiedListingRegister' => array(
        'hook'    => 'wp_loaded',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    //elementor_pro_forms_send_form
    'ElementorPro' => array(
        'hook'    => 'elementor_pro_forms_send_form',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true,
        'ajax_and_post' => true
    ),
    'AvadaBuilderFusionForm' => array(
        'hook'    => [
            'fusion_form_submit_form_to_database_email',
            'fusion_form_submit_form_to_email',
            'fusion_form_submit_ajax'
        ],
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'FluentBookingPro' => array(
        'hook'    => [
            'fluent_cal_schedule_meeting',
        ],
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'BookingPress' => array(
        'hook'    => [
            'bookingpress_book_appointment_booking',
        ],
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'JobstackThemeRegistration' => array(
        'hook'    => 'wp_loaded',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'ContactFormPlugin' => array(
        'hook'    => 'cntctfrm_check_form',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'KadenceBlocks' => array(
        'hook'    => 'kb_process_ajax_submit',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'KadenceBlocksAdvanced' => array(
        'hook'    => 'kb_process_advanced_form_submit',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'WordpressFileUpload' => array(
        'hook'    => 'wfu_before_upload',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true,
        'ajax_and_post' => true
    ),
    'LearnPress' => array(
        'hook'    => 'lp/before_create_new_customer',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'PaidMembershipPro' => array(
        'hook'    => 'pmpro_is_spammer',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'PaidMemberSubscription' => array(
        'hook'    => 'pms_register_form_validation',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    // Mail chimp integration works with ajax and POST via the same hook
    'MailChimp' => array(
        'hook'    => 'mc4wp_form_errors',
        'setting' => 'forms__registrations_test',
        'ajax' => false
    ),
    'BloomForms' => array(
        'hook'    => 'bloom_subscribe',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    // Integration Contact Form Clean and Simple
    'CSCF' => array(
        'hook'    => array('cscf-submitform', 'cscf_spamfilter'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true,
        'ajax_and_post' => true
    ),
    'ThriveLeads' => array(
        'hook'    => 'tve_leads_ajax_conversion',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'OtterBlocksForm' => array(
        'hook'    => 'otter_form_anti_spam_validation',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'TourMasterRegister' => array(
        'hook'    => 'wp_pre_insert_user_data',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'TourMasterOrder' => array(
        'hook'    => 'tourmaster_payment_template',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'CoBlocks' => array(
        'hook'    => 'coblocks_before_form_submit',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'Tevolution' => array(
        'hook'    => 'register_post',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'AwesomeSupportRegistration' => array(
        'hook'    => 'wpas_register_account_before',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'AwesomeSupportTickets' => array(
        'hook'    => 'wpas_open_ticket_before_assigned',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'Listeo' => array(
        'hook'    => 'listeoajaxregister',
        'setting' => 'forms__registrations_test',
        'ajax'    => true
    ),
    'BravePopUpPro' => array(
        'hook'    => 'bravepop_form_submission',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'SmartQuizBuilder' => array(
        'hook'    => 'SQBSubmitQuizAjax',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'WPZOOMForms' => array(
        'hook'    => 'admin_post_nopriv_wpzf_submit',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'Newsletter' => array(
        'hook'    => 'newsletter_action',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'ChatyContactForm'         => array(
        'hook'    => 'chaty_front_form_save_data',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'LoginSignupPopup'         => array(
        'hook'    => 'xoo_el_form_action',
        'setting' => 'forms__registrations_test',
        'ajax'    => true
    ),
    'QuickCal'         => array(
        'hook'    => 'booked_add_appt',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'RegistrationMagic'         => array(
        'hook'    => 'rm_validate_before_form_submit',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
);

add_action('plugins_loaded', function () use ($apbct_active_integrations) {
    global $apbct;

    if ( defined('FLUENTFORM_VERSION') ) {
        $apbct_active_integrations['FluentForm']['hook'] = version_compare(FLUENTFORM_VERSION, '4.3.22') > 0
            ? 'fluentform/before_insert_submission'
            : 'fluentform_before_insert_submission';
    }

    new  \Cleantalk\Antispam\Integrations($apbct_active_integrations, (array)$apbct->settings);
});
