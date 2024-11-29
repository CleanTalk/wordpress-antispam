<?php

namespace Cleantalk\ApbctWP\FindSpam\ListTable;

use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;

class UsersScan extends Users
{
    public function prepare_items() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $columns               = $this->get_columns();
        $this->_column_headers = array($columns, array(), array());

        $current_screen = get_current_screen();
        $per_page_option = !is_null($current_screen)
            ? $current_screen->get_option('per_page', 'option')
            : '10';
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
            if ( ! $user_obj ) {
                continue;
            }
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

        $this->items = static::sortItems($this->items);
    }

    /**
     * @param array $items
     *
     * @return array
     */
    private static function sortItems($items)
    {
        try {
            if (empty($items)) {
                return $items;
            }
            if (isset($_COOKIE['ct_users_order_by'])) {
                $order_by = Sanitize::cleanWord($_COOKIE['ct_users_order_by']);
                if ( !in_array(
                    $order_by,
                    array('ct_id', 'ct_name', 'ct_email', 'ct_signed_up', 'ct_role', 'ct_posts', 'ct_orders')
                ) ) {
                    $order_by = 'ct_id';
                }
            } else {
                $order_by = 'ct_id';
            }

            if (isset($_COOKIE['ct_users_order_direction'])) {
                $order_direction = Sanitize::cleanWord($_COOKIE['ct_users_order_direction']);
                $order_direction = $order_direction === 'desc' ? $order_direction : 'asc';
            } else {
                $order_direction = 'asc';
            }

            $order_direction = $order_direction === 'asc' ? 1 : -1;

            usort($items, function ($a, $b) use ($order_by, $order_direction) {
                return is_array($a) && is_array($b) && isset($a[$order_by], $b[$order_by])
                    ? $order_direction * strcmp(TT::toString($a[$order_by]), TT::toString($b[$order_by]))
                    : 0;
            });
        } catch (\Exception $e) {
            return $items;
        }
        return $items;
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

        $options = '';
        foreach ($columns as $key => $value) {
            $options .= '<option value="' . htmlspecialchars($key) . '">' . htmlspecialchars($value) . '</option>';
        }

        $out = '';

        $out .= '<div class="alignleft actions bulkactions apbct-table-actions-wrapper">';
        $out .= '<span class="displaying-num">' . __('Order users by', 'cleantalk-spam-protect') . '</span>';
        $out .= '<select id="ct_users_order_by" style="float: none; margin: 0 3px 0 3px;">';
        $out .= $options;
        $out .= '</select>';

        $out .= '<select id="ct_users_order_direction" style="float: none; margin: 0 3px 0 0;">';
        $out .= '<option value="desc">Descend</option>';
        $out .= '<option value="asc">Ascend</option>';
        $out .= '</select>';
        $out .= '<button type="button" id="ct_users_ordering" class="button">';
        $out .= __('Apply', 'cleantalk-spam-protect');
        $out .= '</button>';
        $out .= '</div>';

        $out .= '<div class="alignleft actions bulkactions apbct-table-actions-wrapper">';
        $out .= '<button type="button" id="ct_delete_all_users" class="button action ct_delete_all_users" style="margin: 0 2px;">';
        $out .= __('Delete all users from list', 'cleantalk-spam-protect');
        $out .= '</button>';

        $out .= '<button type="button" id="ct_get_csv_file" class="button action ct_get_csv_file" style="margin: 0 2px;">';
        $out .= __('Download results in CSV', 'cleantalk-spam-protect');
        $out .= '</button>';
        $out .= '</div>';
        return $out;
    }

    public static function getExtraTableNavInsertDeleteUsers()
    {
        $out = '';
        if (
            isset($_SERVER['SERVER_ADDR']) &&
            $_SERVER['SERVER_ADDR'] === '127.0.0.1' &&
            in_array(Server::getDomain(), array('lc', 'loc', 'lh'))
        ) {
            $out .= '<div class="ctlk---red bar" style="padding: 10px">';
            $out .= '<span>These actions available only for test purpose and buttons are visible only in local env:</span>';
            $out .= '<button type="button" class="button button-small action ct_insert_users" style="margin:0 5px">Insert 500 users</button>';
            $out .= '<button type="button" class="button button-small action ct_insert_users__delete" style="margin:0 5px">Delete inserted users</button>';
            $out .= '</div>';
        }
        return $out;
    }
}
