<?php

namespace Cleantalk\ApbctWP\FindSpam\ListTable;

use Cleantalk\Variables\Get;
use Cleantalk\Variables\Post;

class Users extends \Cleantalk\ApbctWP\CleantalkListTable
{
    protected $apbct;

    protected $wc_active = false;

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'spam',
            'plural'   => 'spam'
        ));

        $this->bulk_actions_handler();

        $this->row_actions_handler();

        if ( in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ) {
            $this->wc_active = true;
        }

        $this->prepare_items();

        global $apbct;
        $this->apbct = $apbct;
    }

    // Set columns
    public function get_columns() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $columns = array(
            'cb'           => '<input type="checkbox" />',
            'ct_username'  => esc_html__('Username', 'cleantalk-spam-protect'),
            'ct_name'      => esc_html__('Name', 'cleantalk-spam-protect'),
            'ct_email'     => esc_html__('E-mail', 'cleantalk-spam-protect'),
            'ct_signed_up' => esc_html__('Signed up', 'cleantalk-spam-protect'),
            'ct_role'      => esc_html__('Role', 'cleantalk-spam-protect'),
            'ct_posts'     => esc_html__('Posts', 'cleantalk-spam-protect'),
        );
        if ( $this->wc_active ) {
            $columns['ct_orders'] = esc_html__('Completed WC orders', 'cleantalk-spam-protect');
        }

        return $columns;
    }

    /**
     * CheckBox column
     *
     * @param object $item
     *
     * @psalm-suppress InvalidArrayAccess
     */
    public function column_cb($item) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        echo '<input type="checkbox" name="spamids[]" id="cb-select-' . $item['ct_id'] . '" value="' . $item['ct_id'] . '" />';
    }

    /**
     * Username (first) column
     *
     * @param $item
     *
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
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
                $column_content .= esc_html__('No IP adress', 'cleantalk-spam-protect');
            }
        } else {
            $column_content .= esc_html__('No IP adress', 'cleantalk-spam-protect');
        }

        $actions = array(
            'delete' => sprintf(
                '<a href="?page=%s&action=%s&spam=%s">Delete</a>',
                htmlspecialchars(addslashes(Get::get('page'))),
                'delete',
                $user_obj->ID
            ),
        );

        return sprintf('%1$s %2$s', $column_content, $this->row_actions($actions));
    }

    /**
     * Rest of columns
     *
     * @param object $item
     * @param string $column_name
     *
     * @return bool|string|void
     * @psalm-suppress InvalidArrayAccess
     */
    public function column_default($item, $column_name) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        switch ( $column_name ) {
            case 'ct_name':
            case 'ct_email':
            case 'ct_signed_up':
            case 'ct_role':
            case 'ct_posts':
            case 'ct_start':
            case 'ct_checked':
            case 'ct_spam':
            case 'ct_bad':
            case 'ct_orders':
                return $item[$column_name];
            default:
                return print_r($item, true);
        }
    }

    public function get_bulk_actions() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return array(
            'delete' => 'Delete'
        );
    }

    public function bulk_actions_handler() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( empty(Post::get('spamids')) || empty(Post::get('_wpnonce')) ) {
            return;
        }

        if ( ! $this->current_action() ) {
            return;
        }

        if ( ! wp_verify_nonce(Post::get('_wpnonce'), 'bulk-' . $this->_args['plural']) ) {
            wp_die('nonce error');
        }

        $this->removeSpam(Post::get('spamids'));
    }

    public function row_actions_handler() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( empty(Get::get('action')) ) {
            return;
        }

        if ( Get::get('action') === 'delete' ) {
            $id = filter_input(INPUT_GET, 'spam', FILTER_SANITIZE_NUMBER_INT);
            $this->removeSpam(array($id));
        }
    }

    public function no_items() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        esc_html_e('No spam found.', 'cleantalk-spam-protect');
    }

    //********************************************//
    //                  LOGIC                     //
    //********************************************//

    public function removeSpam($ids)
    {
        foreach ( $ids as $id ) {
            $user_id = (int)sanitize_key($id);

            //Send feedback
            $hash = get_user_meta($user_id, 'ct_hash', true);
            if ( $hash ) {
                ct_feedback($hash, 0);
            }

            //Delete user and posts
            wp_delete_user($user_id);
        }
    }

    /**
     * @return integer
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getTotal()
    {
        return count_users()['total_users'];
    }

    /**
     * @return integer
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getChecked()
    {
        return $this->apbct->data['count_checked_users'];
    }

    /**
     * @return \WP_User_Query
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getCheckedNow()
    {
        $params_spam = array(
            'fields'      => 'ID',
            'meta_key'    => 'ct_checked_now',
            'count_total' => true,
        );

        return new \WP_User_Query($params_spam);
    }

    public function getSpamNow($per_page, $current_page)
    {
        $params_spam = array(
            'number'   => $per_page,
            'offset'   => ( $current_page - 1 ) * $per_page,
            'fields'      => 'ID',
            'meta_key' => 'ct_marked_as_spam',
            'count_total' => true,
        );

        return new \WP_User_Query($params_spam);
    }

    public function getBad()
    {
        // Without IP and EMAIL
        $params_bad = array(
            'fields'      => 'ID',
            'meta_key'    => 'ct_bad',
            'count_total' => true,
        );

        return new \WP_User_Query($params_bad);
    }

    public function getScansLogs()
    {
        global $wpdb;
        $query = "SELECT * FROM " . APBCT_SPAMSCAN_LOGS . " WHERE scan_type = 'users'";

        return $wpdb->get_results($query, ARRAY_A);
    }

    protected function removeLogs($ids)
    {
        $sanitized_ids = array();
        foreach ( $ids as $id ) {
            $sanitized_ids[] = sanitize_key($id);
        }
        $ids_string = implode(', ', $sanitized_ids);
        global $wpdb;

        $wpdb->query(
            "DELETE FROM " . APBCT_SPAMSCAN_LOGS . " WHERE 
                ID IN ($ids_string)"
        );
    }

    protected function getWcOrdersCount($user_id)
    {
        $args = array(
            'post_type'   => 'shop_order',
            'post_status' => 'wc-completed',
            'numberposts' => -1,
            'meta_key'    => '_customer_user',
            'meta_value'  => $user_id,
        );

        $description = '';
        if ( $count = count(get_posts($args)) ) {
            $description = esc_html__('Do "accurate check" to skip checking this user', 'cleantalk-spam-protect');
        }

        return '<p>' . $count . '</p><i>' . $description . '</i>';
    }
}
