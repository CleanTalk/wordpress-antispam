<?php

namespace Cleantalk\ApbctWP\FindSpam\ListTable;

use Cleantalk\Variables\Server;

class UsersScan extends Users
{
    public function prepare_items() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $columns               = $this->get_columns();
        $this->_column_headers = array($columns, array(), array());

        $per_page_option = get_current_screen()->get_option('per_page', 'option');
        $per_page        = get_user_meta(get_current_user_id(), $per_page_option, true);
        if ( ! $per_page ) {
            $per_page = 10;
        }

        $current_page = $this->get_pagenum();

        $scanned_users = $this->getSpamNow($per_page, $current_page);

        $this->set_pagination_args(array(
            'total_items' => $scanned_users->get_total(),
            'per_page'    => $per_page,
        ));

        foreach ( $scanned_users->get_results() as $user_id ) {
            $user_obj = get_userdata($user_id);

            $items = array(
                'ct_id'        => $user_obj->ID,
                'ct_username'  => $user_obj,
                'ct_name'      => $user_obj->display_name,
                'ct_email'     => $user_obj->user_email,
                'ct_signed_up' => $user_obj->user_registered,
                'ct_role'      => implode(', ', $user_obj->roles),
                'ct_posts'     => count_user_posts($user_id),
            );

            if ( $this->wc_active ) {
                $items['ct_orders'] = $this->getWcOrdersCount($user_obj->ID);
            }

            $this->items[] = $items;
        }
    }

    public function extra_tablenav($which) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if (
            isset($_SERVER['SERVER_ADDR']) &&
            $_SERVER['SERVER_ADDR'] === '127.0.0.1' &&
            in_array(Server::getDomain(), array('lc', 'loc', 'lh'))
        ) {
            ?>
            <button type="button" class="button action ct_insert_users">Insert users</button>
            <button type="button" class="button action ct_insert_users__delete">Delete inserted</button>
            <?php
        }
        if ( ! $this->has_items() ) {
            return;
        }
        ?>
        <div class="alignleft actions bulkactions">
            <button type="button" id="ct_delete_all_users" class="button action ct_delete_all_users"><?php
                esc_html_e('Delete all users from list', 'cleantalk-spam-protect'); ?></button>
            <button type="button" id="ct_get_csv_file" class="button action ct_get_csv_file"><?php
                esc_html_e('Download results in CSV', 'cleantalk-spam-protect') ?></button>
            <span class="spinner"></span>
        </div>
        <?php
    }
}
