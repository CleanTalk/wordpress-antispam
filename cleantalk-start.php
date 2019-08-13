<?php

// Global ArrayObject with settings and other global varables
global $apbct;
$apbct = new CleantalkState('cleantalk', array('settings', 'data', 'debug', 'errors', 'remote_calls', 'stats'), is_multisite());

$apbct->white_label = defined('APBCT_WHITELABEL') && APBCT_WHITELABEL == true ? true : false;

// Account status
$apbct->base_name   = 'cleantalk-spam-protect/cleantalk.php';
$apbct->plugin_name = defined('APBCT_WHITELABEL_NAME') ? APBCT_WHITELABEL_NAME : APBCT_NAME; // For test purposes
$apbct->key_is_ok   = isset($apbct->data['testing_failed']) && $apbct->data['testing_failed'] == 0 ? 1 : $apbct->key_is_ok;

$apbct->logo                 = dirname(__FILE__) . '/inc/images/logo.png';
$apbct->logo__small          = dirname(__FILE__) . '/inc/images/logo_small.png';
$apbct->logo__small__colored = dirname(__FILE__) . '/inc/images/logo_color.png';
$apbct->settings_link        = is_network_admin() ? 'settings.php?page=cleantalk' : 'options-general.php?page=cleantalk';

$apbct->data['user_counter']['since']       = isset($apbct->data['user_counter']['since'])       ? $apbct->data['user_counter']['since'] : date('d M');
$apbct->data['connection_reports']['since'] = isset($apbct->data['connection_reports']['since']) ? $apbct->data['user_counter']['since'] : date('d M');

if(!$apbct->white_label){
	require_once( CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-widget.php');
	$apbct->settings['apikey'] = defined('CLEANTALK_ACCESS_KEY') ? CLEANTALK_ACCESS_KEY : $apbct->settings['apikey'];
}