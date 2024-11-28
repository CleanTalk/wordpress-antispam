<?php

// WP Delicious integration
add_filter('delicious_recipes_process_registration_errors', 'apbct_wp_delicious', 10, 4);

// LearnPress
if (
    apbct_is_plugin_active('learnpress/learnpress.php') &&
    apbct_is_in_uri('lp-ajax=checkout') &&
    sizeof($_POST) > 0
) {
    apbct_form__learnpress__testSpam();
}

// OptimizePress
if (
    apbct_is_plugin_active('op-dashboard/op-dashboard.php') &&
    apbct_is_in_uri('/optin/submit') &&
    sizeof($_POST) > 0
) {
    apbct_form__optimizepress__testSpam();
}

// Mailoptin. Pass without action because url for ajax request is domain.com/any-page/?mailoptin-ajax=subscribe_to_email_list
if (
    apbct_is_plugin_active('mailoptin/mailoptin.php') &&
    sizeof($_POST) > 0 &&
    Get::get('mailoptin-ajax') === 'subscribe_to_email_list'
) {
    apbct_form__mo_subscribe_to_email_list__testSpam();
}

// Metform
if (
    apbct_is_plugin_active('metform/metform.php') &&
    apbct_is_in_uri('/wp-json/metform/') &&
    sizeof($_POST) > 0
) {
    apbct_form__metform_subscribe__testSpam();
}

// Memberpress integration
if (
    !empty($_POST) &&
    apbct_is_plugin_active('memberpress/memberpress.php') &&
    Post::hasString('mepr_process_signup_form', '1') &&
    (int)$apbct->settings['forms__registrations_test'] === 1
) {
    apbct_memberpress_signup_request_test();
}

// Ninja Forms. Making GET action to POST action
if (
    apbct_is_in_uri('admin-ajax.php') &&
    sizeof($_POST) > 0 &&
    Get::get('action') === 'ninja_forms_ajax_submit'
) {
    $_POST['action'] = 'ninja_forms_ajax_submit';
}

// GiveWP without ajax
if (
    !empty($_POST) &&
    (int)$apbct->settings['forms__contact_forms_test'] === 1 &&
    apbct_is_plugin_active('give/give.php') &&
    !empty($_POST['give-form-hash']) &&
    !empty($_POST['give-form-id'])
) {
    apbct_givewp_donate_request_test();
}

// JetformBuilder
if (
    !empty($_POST) &&
    apbct_is_plugin_active('jetformbuilder/jet-form-builder.php') &&
    Get::get('jet_form_builder_submit') === 'submit'
) {
    apbct_jetformbuilder_request_test();
}

// DHVC Form
if (
    !empty($_POST) &&
    apbct_is_plugin_active('dhvc-form/dhvc-form.php') &&
    Post::get('dhvc_form') && Post::get('_dhvc_form_nonce')
) {
    apbct_dhvcform_request_test();
}

add_action('wp_ajax_nopriv_ninja_forms_ajax_submit', 'apbct_form__ninjaForms__testSpam', 1);
add_action('wp_ajax_ninja_forms_ajax_submit', 'apbct_form__ninjaForms__testSpam', 1);
add_action('wp_ajax_nopriv_nf_ajax_submit', 'apbct_form__ninjaForms__testSpam', 1);
add_action('wp_ajax_nf_ajax_submit', 'apbct_form__ninjaForms__testSpam', 1);
add_action('ninja_forms_process', 'apbct_form__ninjaForms__testSpam', 1); // Depricated ?
add_action('ninja_forms_display_after_form', 'apbct_form__ninjaForms__addField', 1000, 10);

// SeedProd Coming Soon Page Pro integration
add_action('wp_ajax_seed_cspv5_subscribe_callback', 'apbct_form__seedprod_coming_soon__testSpam', 1);
add_action('wp_ajax_nopriv_seed_cspv5_subscribe_callback', 'apbct_form__seedprod_coming_soon__testSpam', 1);
add_action('wp_ajax_seed_cspv5_contactform_callback', 'apbct_form__seedprod_coming_soon__testSpam', 1);
add_action('wp_ajax_nopriv_seed_cspv5_contactform_callback', 'apbct_form__seedprod_coming_soon__testSpam', 1);

// The 7 theme contact form integration
add_action('wp_ajax_nopriv_dt_send_mail', 'apbct_form__the7_contact_form', 1);
add_action('wp_ajax_dt_send_mail', 'apbct_form__the7_contact_form', 1);

// Custom register form (ticket_id=13668)
add_action('website_neotrends_signup_fields_check', function ($username, $fields) {
    $ip        = Helper::ipGet('real', false);
    $ct_result = ct_test_registration($username, $fields['email'], $ip);
    if ( TT::getArrayValueAsInt($ct_result, 'allow') === 0 ) {
        ct_die_extended(TT::getArrayValueAsString($ct_result, 'comment'));
    }
}, 1, 2);

add_action('elementor/frontend/the_content', 'apbct_form__elementor_pro__addField', 10, 2);

// INEVIO theme integration
add_action('wp_ajax_contact_form_handler', 'apbct_form__inevio__testSpam', 1);
add_action('wp_ajax_nopriv_contact_form_handler', 'apbct_form__inevio__testSpam', 1);

// Enfold Theme contact form
add_filter('avf_form_send', 'apbct_form__enfold_contact_form__test_spam', 4, 10);

// Profile Builder integration
add_filter('wppb_output_field_errors_filter', 'apbct_form_profile_builder__check_register', 1, 3);

// Advanced Classifieds & Directory Pro
add_filter('acadp_is_spam', 'apbct_advanced_classifieds_directory_pro__check_register', 1, 2);

// WP Foro register system integration
add_filter('wpforo_create_profile', 'wpforo_create_profile__check_register', 1, 1);

// HappyForms integration
add_filter('happyforms_validate_submission', 'apbct_form_happyforms_test_spam', 1, 3);
add_filter('happyforms_use_hash_protection', '__return_false');

// WPForms
// Adding fields
add_action('wpforms_frontend_output', 'apbct_form__WPForms__addField', 1000, 5);
// Gathering data to validate
add_filter('wpforms_process_before_filter', 'apbct_from__WPForms__gatherData', 100, 2);
// Do spam check
add_filter('wpforms_process_initial_errors', 'apbct_form__WPForms__showResponse', 100, 2);

// Formidable
add_filter('frm_entries_before_create', 'apbct_form__formidable__testSpam', 999999, 2);
add_action('frm_entries_footer_scripts', 'apbct_form__formidable__footerScripts', 20, 2);


add_action('mec_booking_end_form_step_2', function () {
    echo "<script>
        if (typeof ctPublic.force_alt_cookies == 'undefined' || (ctPublic.force_alt_cookies !== 'undefined' && !ctPublic.force_alt_cookies)) {
			ctNoCookieAttachHiddenFieldsToForms();
		}
    </script>";
});
