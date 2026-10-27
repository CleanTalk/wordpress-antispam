<?php

// Prevent direct call
if ( ! defined('ABSPATH') ) {
    die('Not allowed!');
}

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
        'hook'    => array('preprocess_comment', 'rest_pre_insert_comment'),
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
    'Pagelayer'           => array(
        'hook'    => 'pagelayer_contact_submit_start',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
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
        'hook'    => 'swpm_validate_registration_form_submission',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'WpMembers'           => array(
        'hook'    => 'wpmem_pre_register_data',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'DWQuestionAnswer'    => array(
        'hook'    => 'dwqa_insert_comment_args',
        'setting' => 'forms__comments_test',
        'ajax'    => false
    ),
    'Wpdiscuz'            => array(
        'hook'    => array('wpdAddComment', 'wpdAddInlineComment', 'wpdSaveEditedComment'),
        'setting' => 'forms__comments_test',
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
        'hook'    => array('cpcff_process_data', 'cpcff_free_process_data_before_insert'),
        'setting' => 'forms__general_contact_forms_test',
        'ajax'    => false
    ),
    'OvaLogin' => array(
        'hook'    => 'login_form_register',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'GiveWP' => array(
        'hook'    => ['give_checkout_error_checks','givewp_donation_form_fields_validated'],
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
    'WPUserFrontend' => array(
        'hook'    => 'wpuf_process_registration_errors',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'LeakyPaywallListBuilder' => array(
        'hook'    => 'lp_list_builder_signup_validation',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'MemberPress' => array(
        'hook'    => 'mepr-validate-signup',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'EasyDigitalDownloads' => array(
        'hook'    => array(
            'edd_pre_process_register_form',
            'edd_insert_user_args',
            'edd_customer_pre_create',
            'edd_checkout_user_error_checks'
        ),
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'WpForo' => array(
        'hook'    => array(
            'wpforo_action_topic_add',
            'wpforo_action_post_add',
            'wpforo_action_topic_edit',
            'wpforo_action_post_edit'
        ),
        'setting' => 'data__protect_logged_in',
        'ajax'    => false
    ),
    'StrongTestimonials' => array(
        'hook'    => array('wpmtst_form_submission','wpmtst_form2'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true,
        'ajax_and_post' => true
    ),
    // Site Reviews validates the review of the form, AJAX and REST submits here
    'SiteReviews' => array(
        'hook'    => 'site-reviews/validate/custom',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
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
    // Contact Form by Supsystic processes the form sent without ajax on init:5
    'SupsysticNoAjax' => array(
        'hook'    => 'init',
        'setting' => 'forms__contact_forms_test',
        'priority' => 1,
        'ajax'    => false
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
        'hook'    => array('latepoint_route_call', 'latepoint_process_step'),
        'setting' => 'forms__contact_forms_test',
        'priority' => 5,
        'ajax'    => true,
        'ajax_and_post' => true
    ),
    'MailPoet' => array(
        'hook'    => array('mailpoet_subscription_before_subscribe'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
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
        'hook'    => 'pafe_ajax_form_builder',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'UserRegistrationPro'           => array(
        'hook'    => array('user_registration_before_register_user_action'),
        'setting' => 'forms__registrations_test',
        // important!
        'ajax'    => false
    ),
    // The plugin fires the action for the AJAX and REST API subscriptions
    'BackInStockNotifier'           => array(
        'hook'    => array('cwginstock_ajax_data'),
        'setting' => 'forms__contact_forms_test',
        'priority' => 1,
        'ajax'    => false
    ),
    'ProductEnquiryPro'           => array(
        'hook'    => array('mgc_pe_sender_email'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'WpGeoDirectory' => array(
        'hook'    => array('geodir_validate_ajax_save_post_data', 'geodir_validate_auto_save_post_data'),
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
    // aMember Pro signup (/am/signup) — detected by POST fingerprint; aMember bootstraps WP
    'AmemberRegister' => array(
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
    'BookingCalendar' => array(
        'hook'    => [
            'WPBC_AJX_BOOKING__CREATE',
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
    'ContactFormToEmail' => array(
        'hook'    => 'cfte_process_data_before_insert',
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
        'hook'    => array('wfu_before_file_check', 'wfu_before_data_submit'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
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
    'MailChimpShadowRoot'         => array(
        'hook'    => 'cleantalk_force_mailchimp_shadowroot_check',
        'setting' => 'forms__check_external',
        'ajax'    => true
    ),
    'OtterBlocksForm'         => array(
        'hook'    => array('cleantalk_force_otterform_check', 'otter_form_anti_spam_validation'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true,
        'ajax_and_post' => true
    ),
    'ElfsightForm'            => array(
        'hook'    => 'cleantalk_force_elfsight_check',
        'setting' => 'forms__check_external',
        'ajax'    => true
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
        'hook'    => array('wpas_open_ticket_before_assigned', 'wpas_do_submit_new_reply'),
        'setting' => 'forms__contact_forms_test',
        'priority' => 9,
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
        'hook'    => array('admin_post_nopriv_wpzf_submit', 'admin_post_wpzf_submit'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'WebMagicStudio' => array(
        'hook'    => array(
            'admin_post_nopriv_wms_contact',
            'admin_post_wms_contact',
        ),
        'setting' => 'forms__general_contact_forms_test',
        'priority' => 1,
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
    // Newsletters (Tribulant) - the subscription form with and without AJAX
    'NewslettersTribulant' => array(
        'hook'    => 'wpml_subscriber_add_new',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
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
    'BuddyBossAppRestAPI'         => array(
        'hook'    => 'bbapp_auth_rest_user_registration_pre_check',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'AsgarosForum'         => array(
        'hook'    => array(
            'asgarosforum_filter_before_topic_submit',
            'asgarosforum_filter_before_post_submit',
            'asgarosforum_filter_before_edit_post_submit',
        ),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'WPFunnels'         => array(
        'hook'    => ['wpfnl_gutenberg_optin_submission','wffn_submit_custom_optin_form'],
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'WooCommerceWholesaleLeadCapture' => array(
        'hook'    => 'wwlc_action_before_create_wholesale_lead',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'LifterLMS' => array(
        'hook'    => 'lifterlms_user_registration_data',
        'setting' => 'forms__registrations_test',
        'ajax'    => false
    ),
    'NEXForms' => array(
        'hook'    => 'submit_nex_form',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'GwolleGuestbook' => array(
        'hook'    => array('gwolle_gb_new_entry_frontend', 'gwolle_gb_entry_edit'),
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true,
        'ajax_and_post' => true
    ),
    'FLBuilderForms' => array(
        'hook'    => 'fl_builder_subscribe_form_submit',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'BitForm' => array(
        'hook'    => 'bitform_filter_form_validation',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => false
    ),
    'NinjaForms' => array(
        'hook'    => ['ninja_forms_ajax_submit', 'nf_ajax_submit', 'forms_process', 'ninja_forms_display_after_form'],
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'Amelia' => array(
        'hook'    => 'wpamelia_api',
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true
    ),
    'EmailSubscribers' => array(
        'hook'    => ['es_add_subscriber', 'ig_es_validate_subscription'],
        'setting' => 'forms__contact_forms_test',
        'ajax'    => true,
        'ajax_and_post' => true
    ),
    'EventPrime' => array(
        'hook'    => ['ep_submit_register_form', 'ep_save_event_booking'],
        'setting' => 'forms__registrations_test',
        'ajax'    => true,
        'ajax_and_post' => true
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
