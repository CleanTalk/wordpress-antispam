<?php

$apbct_active_rest_integrations = array(
    'SureForms'         => array(
        'rest_route'    => '/sureforms/v1/submit-form',
        'setting' => 'forms__contact_forms_test',
        'rest'       => true,
    )
);

add_filter('rest_pre_dispatch', function ($result, $_, $request) use ($apbct_active_rest_integrations) {
    global $apbct;
    $route = $request->get_route();
    foreach ($apbct_active_rest_integrations as $integration_name => $rest_data) {
        if (isset($rest_data['rest_route']) && $rest_data['rest_route'] === $route) {
            $apbct_settings = isset($apbct->settings) && is_array($apbct->settings) ? $apbct->settings : array();
            $integrations = new \Cleantalk\Antispam\Integrations($apbct_active_rest_integrations, $apbct_settings);
            $params = $request->get_params();
            if (isset($params['POST']) && is_array($params['POST'])) {
                $params = $params['POST'];
            }
            $response = $integrations->checkSpam($params, $integration_name);

            if ($response instanceof \WP_REST_Response || is_array($response)) {
                return $response;
            }
            break;
        }
    }
    return $result;
}, 999, 3);
