<?php

namespace Cleantalk\ApbctWP\FindSpam\ListTable;

class BadUsers extends Users
{
    public function prepare_items()  // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $columns               = $this->get_columns();
        $this->_column_headers = array($columns, array(), array());

        $per_page_option = ! is_null(get_current_screen()) ? get_current_screen()->get_option(
            'per_page',
            'option'
        ) : '10';
        $per_page        = get_user_meta(get_current_user_id(), $per_page_option, true);
        if ( ! $per_page ) {
            $per_page = 10;
        }

        $scanned_users = $this->getBadUsers();

        $this->set_pagination_args(array(
            'total_items' => $scanned_users->get_total(),
            'per_page'    => $per_page,
        ));

        $current_page = $this->get_pagenum();

        $scanned_users_to_show = array_slice(
            $scanned_users->get_results(),
            (($current_page - 1) * $per_page),
            $per_page
        );

        foreach ( $scanned_users_to_show as $user_id ) {
            $user_obj = get_userdata($user_id);

            $this->items[] = array(
                'ct_id'        => $user_obj->ID,
                'ct_username'  => $user_obj,
                'ct_name'      => $user_obj->display_name,
                'ct_email'     => $user_obj->user_email,
                'ct_signed_up' => $user_obj->user_registered,
                'ct_role'      => implode(', ', $user_obj->roles),
                'ct_posts'     => count_user_posts($user_id),
            );
        }
    }

    // Username (first) column
    public function column_ct_username($item) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $user_obj       = $item['ct_username'];
        $email          = $user_obj->user_email;
        $column_content = '';

        // Avatar, nickname
        $column_content .= '<strong>' . get_avatar($user_obj->ID, 32) . '&nbsp;' . $user_obj->user_login . '</strong>';
        $column_content .= '<br /><br />';

        // Email
        if ( ! empty($email) ) {
            $column_content .= "<a href='mailto:$email'>$email</a>"
                               . (! $this->apbct->white_label
                    ? "<a href='https://cleantalk.org/blacklists/$email' target='_blank'>"
                      . "&nbsp;<img src='" . APBCT_URL_PATH . "/inc/images/new_window.gif' alt='Ico: open in new window' border='0' style='float:none' />"
                      . "</a>"
                    : '');
        } else {
            $column_content .= esc_html__('No email', 'cleantalk-spam-protect');
        }
        $column_content .= '<br/>';

        // IP
        $user_meta = get_user_meta($user_obj->ID, 'session_tokens', true);
        if ( ! empty($user_meta) && is_array($user_meta) ) {
            $user_meta = array_values($user_meta);
            if ( ! empty($user_meta[0]['ip']) ) {
                $ip             = $user_meta[0]['ip'];
                $column_content .= "<a href='user-edit.php?user_id=$user_obj->ID'>$ip</a>"
                                   . (! $this->apbct->white_label
                        ? "<a href='https://cleantalk.org/blacklists/$ip ' target='_blank'>"
                          . "&nbsp;<img src='" . APBCT_URL_PATH . "/inc/images/new_window.gif' alt='Ico: open in new window' border='0' style='float:none' />"
                          . "</a>"
                        : '');
            } else {
                $column_content .= esc_html__('No IP address', 'cleantalk-spam-protect');
            }
        } else {
            $column_content .= esc_html__('No IP address', 'cleantalk-spam-protect');
        }

        return $column_content;
    }

    public function getBadUsers()
    {
        return $this->getBad();
    }

    public function get_bulk_actions()  // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return array();
    }

    public function no_items() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        esc_html_e('No non-checkable users found.', 'cleantalk-spam-protect');
    }
}
