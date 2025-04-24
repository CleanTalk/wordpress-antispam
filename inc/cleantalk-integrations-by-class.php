<?php

$apbct_integrations_by_class = array(
    'BuddyPress' => array(
        'plugin_path' => 'buddypress/bp-loader.php',
        'plugin_class' => 'BuddyPress',
    ),
    'Woocommerce' => array(
        'plugin_path' => 'woocommerce/woocommerce.php',
        'plugin_class' => 'WooCommerce',
    ),
    'WPSearchForm' => array(
        'plugin_path' => '',
        'plugin_class' => '',
        'wp_includes' => true,
    ),
);

add_action('plugins_loaded', function () use ($apbct_integrations_by_class) {
    new  \Cleantalk\Antispam\IntegrationsByClass($apbct_integrations_by_class);
});
