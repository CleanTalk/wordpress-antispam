<?php

// Adding menu items for USERS and COMMENTS spam checking pages
use Cleantalk\ApbctWP\WcSpamOrdersFunctions;

add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        __("WooCommerce spam orders", 'cleantalk-spam-protect'),
        __("WooCommerce spam orders", 'cleantalk-spam-protect'),
        'activate_plugins',
        'apbct_wc_spam_orders',
        function () {
            ?>
            <div class="wrap">
                <form action="" method="POST">
                <?php
                $list_table = new \Cleantalk\ApbctWP\WcSpamOrdersListTable();
                $list_table->display();
                ?>
                </form>
            </div>
            <?php
        }
    );
});

// Restore Spam Order
add_action('wp_ajax_apbct_restore_spam_order', array(WcSpamOrdersFunctions::class, 'restoreOrderAction'));
