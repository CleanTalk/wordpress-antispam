<?php

namespace Cleantalk\ApbctWP\FindSpam\ListTable;

use Cleantalk\ApbctWP\Variables\Request;

class UsersScan extends Users
{
    public function prepare_items() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $current_screen = get_current_screen();
        $per_page_option = !is_null($current_screen)
            ? $current_screen->get_option('per_page', 'option')
            : '10';
        $per_page        = get_user_meta(get_current_user_id(), $per_page_option, true);
        if ( ! $per_page ) {
            $per_page = 10;
        }

        $orderby = Request::getString('orderby');
        $order = Request::getString('order', 'ASC');

        $current_page = $this->get_pagenum();

        $scanned_users = $this->getSpamNow($per_page, $current_page, $orderby, $order);

        $total_items = $this->getSpamNow(null, null, $orderby, $order);
        $total_items = isset($total_items[0]) && property_exists($total_items[0], 'cnt') ? $total_items[0]->cnt : 0;
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ));

        foreach ( $scanned_users as $user_data ) {
            $user_obj = get_userdata($user_data->user_id);
            if ( ! $user_obj  || !isset($user_data->ct_role) ) {
                continue;
            }
            $roles_prepare = implode(', ', array_keys(maybe_unserialize($user_data->ct_role)));
            $items = array(
                'ct_id'        => $user_data->user_id,
                'ct_username'  => $user_obj,
                'ct_name'      => $user_data->ct_name,
                'ct_email'     => $user_data->ct_email,
                'ct_signed_up' => $user_data->ct_signed_up,
                'ct_role'      => $roles_prepare,
                'ct_posts'     => $user_data->ct_posts,
            );

            if ( $this->wc_active ) {
                $items['ct_orders'] = $user_data->ct_orders;
            }

            $this->items[] = $items;
        }
    }

    public function extra_tablenav($which) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if (empty($this->items)) {
            return;
        }
        echo static::getExtraTableNav();
    }

    public static function getExtraTableNav()
    {
        //prepare sorting elements
        $users = new UsersScan();
        $columns = $users->get_columns();
        unset($columns['cb']);
        unset($columns['ct_id']);
        unset($columns['ct_username']);

        $new_out = '
            <div class="alignleft actions bulkactions apbct-table-actions-wrapper">            
            <button type="button" id="ct_delete_all_users" class="button action ct_delete_all_users" style="margin: 0 2px;">%s</button>
            <button type="button" id="ct_get_csv_file" class="button action ct_get_csv_file" style="margin: 0 2px;">%s</button>
            </div>
        ';
        $new_out = sprintf(
            $new_out,
            __('Delete all users from list', 'cleantalk-spam-protect'),
            __('Download results in CSV', 'cleantalk-spam-protect')
        );
        return $new_out;
    }

    public static function getExtraTableNavInsertDeleteUsers()
    {
        $out = '';
        if ( defined('APBCT_IS_LOCALHOST') && APBCT_IS_LOCALHOST ) {
            $out .= '<div class="ctlk---red bar" style="padding: 10px">';
            $out .= '<span>These actions available only for test purpose and buttons are visible only in local env:</span>';
            $out .= '<button type="button" class="button button-small action ct_insert_users" style="margin:0 5px">Insert 50 users</button>';
            $out .= '<button type="button" class="button button-small action ct_insert_users__delete" style="margin:0 5px">Delete inserted users</button>';
            $out .= '</div>';
        }
        return $out;
    }
}
