<?php

$apbct_active_rest_integrations = array(
    'SureForms'         => array(
        'rest_route'    => '/sureforms/v1/submit-form',
        'setting' => 'forms__contact_forms_test',
        'rest'       => true,
    ),
    'ReviewX'         => array(
        'rest_route'    => '/reviewx/api/v1/storefront/reviews',
        'setting' => 'forms__contact_forms_test',
        'rest'       => true,
    ),
    'WPRecipeMaker'         => array(
        'rest_route'    => '/wp-recipe-maker/v1/user-rating/',
        'setting' => 'forms__contact_forms_test',
        'rest'       => true,
    ),
    'HivePress'         => array(
        'rest_route'    => '/hivepress/v1/listings/',
        'setting' => 'forms__contact_forms_test',
        'rest'       => true,
    ),
    'HivePressRegistration'         => array(
        'rest_route'    => '/hivepress/v1/users',
        'setting' => 'forms__registrations_test',
        'rest'       => true,
    ),
    'BuddyNextRegistration'         => array(
        'rest_route'    => '/buddynext/v1/auth/register',
        'setting' => 'forms__registrations_test',
        'rest'       => true,
    ),
    'MetForm'         => array(
        'rest_route'    => '/metform/v1/entries/insert/',
        'setting' => 'forms__contact_forms_test',
        'rest'       => true,
    ),
);

add_filter('rest_pre_dispatch', function ($result, $_, $request) use ($apbct_active_rest_integrations) {
    global $apbct;
    $route = $request->get_route();
    foreach ($apbct_active_rest_integrations as $integration_name => $rest_data) {
        // Routes are case-insensitive
        if (isset($rest_data['rest_route']) && stripos($route, $rest_data['rest_route']) === 0) {
            $apbct_settings = isset($apbct->settings) && is_array($apbct->settings) ? $apbct->settings : array();
            $integrations = new \Cleantalk\Antispam\Integrations($apbct_active_rest_integrations, $apbct_settings);
            $params = $request->get_params();
            $response = $integrations->checkSpam($params, $integration_name);

            if ($response instanceof \WP_REST_Response || is_array($response)) {
                return $response;
            }
            break;
        }
    }
    return $result;
}, 999, 3);
