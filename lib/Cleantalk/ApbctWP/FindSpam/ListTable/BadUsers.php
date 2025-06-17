<?php

namespace Cleantalk\ApbctWP\FindSpam\ListTable;

use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\Common\TT;

class BadUsers extends Users
{
    /**
     * @inheritDoc
     */
    public function prepare_items()  // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        /**
         * This code chunk disables sorting, to make it sortable, remove it.
         * Do not forget adapt getBadUsers() method like Users->getSpamNow() to handle sorting.
         **/
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
            if ( ! $user_obj ) {
                continue;
            }
            $this->items[] = array(
                'ct_id'        => $user_obj->ID,
                'ct_user_obj'  => $user_obj,
                'ct_name'      => $user_obj->display_name,
                'ct_email'     => $user_obj->user_email,
                'ct_signed_up' => $user_obj->user_registered,
                'ct_role'      => implode(', ', $user_obj->roles),
                'ct_posts'     => count_user_posts($user_id),
            );
        }
    }

    /**
     * Username (first) column
     *
     * @param array $item
     *
     * @return string
     */
    public function column_ct_username($item) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        global $apbct;
        $user_obj       = isset($item['ct_user_obj']) ? $item['ct_user_obj'] : null;
        if (is_null($user_obj)) {
            return '';
        }
        $email          = TT::toString($user_obj->user_email);

        // Avatar, nickname
        $avatar = TT::toString(get_avatar($user_obj->ID, 32));
        $column_content = '<strong>' . $avatar . '&nbsp;' . TT::toString($user_obj->user_login) . '</strong>';
        $column_content .= '<br /><br />';

        // Email
        if ( ! empty($email) ) {
            //HANDLE LINK
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
        $ip_from_keeper = $apbct->login_ip_keeper->getIP($user_obj->ID);
        $ip_from_keeper = null !== $ip_from_keeper
            ? $ip_from_keeper
            : null;
        if ( !empty($ip_from_keeper) ) {
            $column_content .= "<a href='user-edit.php?user_id=$user_obj->ID'>$ip_from_keeper</a>"
                               . (! $this->apbct->white_label
                    ? "<a href='https://cleantalk.org/blacklists/$ip_from_keeper ' target='_blank'>"
                      . "&nbsp;<img src='" . APBCT_URL_PATH . "/inc/images/new_window.gif' alt='Ico: open in new window' border='0' style='float:none' />"
                      . "</a>"
                    : '');
        } else {
            $column_content .= esc_html__('No IP adress', 'cleantalk-spam-protect');
        }

        $actions = array(
            'delete' => sprintf(
                '<a href="?page=%s&action=%s&spam=%s">Delete</a>',
                htmlspecialchars(addslashes(TT::toString(Get::get('page')))),
                'delete',
                $user_obj->ID
            )
        );

        return sprintf('%1$s %2$s', $column_content, $this->row_actions($actions));
    }

    /**
     * @return \WP_User_Query
     */
    public function getBadUsers()
    {
        return self::getBad();
    }

    /**
     * @inheritDoc
     */
    public function get_bulk_actions() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return array(
            'delete' => 'Delete'
        );
    }

    /**
     * @inheritDoc
     */
    public function no_items() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        esc_html_e('No non-checkable users found.', 'cleantalk-spam-protect');
    }
}
