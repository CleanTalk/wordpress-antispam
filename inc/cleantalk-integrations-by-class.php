<?php

$apbct_integrations_by_class = array(
    'BuddyPress' => array(
        'plugin_path' => 'buddypress/bp-loader.php',
        'plugin_class' => 'BuddyPress',
    ),
);

add_action('plugins_loaded', function () use ($apbct_integrations_by_class) {
    new  \Cleantalk\Antispam\IntegrationsByClass($apbct_integrations_by_class);
});
