<?php

// Adding menu items for USERS and COMMENTS spam checking pages
add_action('admin_menu', function() {
    add_submenu_page(
        null,
        esc_html__('WooCommerce spam orders', 'cleantalk-spam-protect'),
        'apbct_wc_spam_orders',
        'activate_plugins',
        'apbct_wc_spam_orders',
        function() {
            echo 'WooCommerce spam orders';
            $list_table = new \Cleantalk\ApbctWP\WcSpamOrdersListTable();
            $list_table->display();
        }
    );
});
