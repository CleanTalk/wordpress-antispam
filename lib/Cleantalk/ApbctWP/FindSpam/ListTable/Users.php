<?php

namespace Cleantalk\ApbctWP\FindSpam\ListTable;

use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class Users extends \Cleantalk\ApbctWP\CleantalkListTable
{
    protected $apbct;

    protected $wc_active = false;

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'spam',
            'plural'   => 'spam',
            'screen'   => str_replace('users_page_', '', current_action())
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
     * @return array|array[]
     */
    protected function get_sortable_columns() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $columns = array(
            'ct_username'  => array('ct_username'),
            'ct_email'  => array('ct_email'),
            'ct_name'  => array('ct_name'),
            'ct_signed_up'  => array('ct_signed_up','desc'),
            'ct_role'  => array('ct_role'),
            'ct_posts'  => array('ct_posts'),
        );

        if ( $this->wc_active ) {
            $columns['ct_orders'] = array('ct_orders');
        }

        return $columns;
    }

    /**
     * CheckBox column
     *
     * @param object|array $item
     *
     * @psalm-suppress InvalidArrayAccess
     */
    public function column_cb($item) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $ct_id = TT::getArrayValueAsString($item, 'ct_id');
        echo '<input type="checkbox" name="spamids[]" id="cb-select-' . esc_html($ct_id) . '" value="' . esc_html($ct_id) . '" />';
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
        global $apbct;
        $user_obj       = $item['ct_username'];
        $email          = $user_obj->user_email;

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

        $page = htmlspecialchars(addslashes(TT::toString(Get::get('page'))));

        $actions = array(
            'approve' => sprintf(
                '<a href="?page=%s&action=%s&spam=%s">Approve</a>',
                $page,
                'approve',
                $user_obj->ID
            ),
            'delete' => sprintf(
                '<a href="?page=%s&action=%s&spam=%s">Delete</a>',
                $page,
                'delete',
                $user_obj->ID
            ),
        );

        return sprintf('%1$s %2$s', $column_content, $this->row_actions($actions));
    }

    /**
     * Rest of columns
     *
     * @param object|array $item
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
                return TT::getArrayValueAsString($item, $column_name);
            default:
                return print_r($item, true);
        }
    }

    /**
     * @inheritDoc
     */
    public function get_bulk_actions() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return array(
            'approve' => 'Approve',
            'delete' => 'Delete'
        );
    }

    /**
     * @inheritDoc
     */
    public function bulk_actions_handler() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( empty(Post::get('spamids')) || empty(Post::get('_wpnonce')) ) {
            return;
        }

        if ( ! $this->current_action() ) {
            return;
        }

        $awaited_action = 'bulk-' . TT::getArrayValueAsString($this->_args, 'plural');
        if ( ! wp_verify_nonce(TT::toString(Post::get('_wpnonce')), $awaited_action)) {
            wp_die('nonce error');
        }

        if ( $this->current_action() === 'approve' ) {
            $this->approveSpam(TT::toArray(Post::get('spamids')));
        }

        if ( $this->current_action() === 'delete' ) {
            $this->removeSpam(TT::toArray(Post::get('spamids')));
        }
    }

    /**
     * @return void
     */
    public function row_actions_handler() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( empty(Get::get('action')) ) {
            return;
        }

        if ( Get::get('action') === 'approve' ) {
            $id = filter_input(INPUT_GET, 'spam', FILTER_SANITIZE_NUMBER_INT);
            $this->approveSpam(array($id));
        }

        if ( Get::get('action') === 'delete' ) {
            $id = filter_input(INPUT_GET, 'spam', FILTER_SANITIZE_NUMBER_INT);
            $this->removeSpam(array($id));
        }
    }

    /**
     * @inheritDoc
     */
    public function no_items() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        esc_html_e('No spam found.', 'cleantalk-spam-protect');
    }

    //********************************************//
    //                  LOGIC                     //
    //********************************************//

    /**
     * @param array $ids
     *
     * @return void
     */
    public function approveSpam($ids)
    {
        foreach ( $ids as $id ) {
            $user_id = (int)sanitize_key($id);
            $user_meta = delete_user_meta((int)$id, 'ct_marked_as_spam');

            if ( $user_meta ) {
                update_user_meta($user_id, 'ct_bad', true);
            }
        }
    }

    /**
     * @param array $ids
     *
     * @return void
     */
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
        return TT::getArrayValueAsInt(count_users(), 'total_users');
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

    /**
     * Get spam users.
     * @param integer|null $per_page if null - then just count users
     * @param integer|null $current_page
     * @param string $orderby
     * @param string $order
     * @return array
     */
    public function getSpamNow($per_page, $current_page, $orderby = 'ct_signed_up', $order = 'ASC')
    {
        global $wpdb;
        //table names
        $wc_orders_table = $wpdb->prefix . 'wc_orders';
        $wp_usermeta_table = $wpdb->usermeta;
        $wp_posts_table = $wpdb->posts;
        $wp_users = $wpdb->users;
        $wp_capabilities = $wpdb->prefix . 'capabilities';

        $wc_exists = $this->wc_active && $wpdb->get_row("SHOW TABLES LIKE '$wc_orders_table'");

        // get ordering and sanitizing it
        $sortable_columns = array_keys(Users::get_sortable_columns());

        $orderby = !empty($orderby) ? $orderby : 'ct_signed_up';
        $orderby = is_string($orderby) && in_array($orderby, $sortable_columns) ? $orderby : false;

        $order = !empty($order) ? strtoupper($order) : 'ASC';
        $order = in_array($order, array('ASC', 'DESC')) ? $order : false;

        $order_by_chunk = $order && $orderby && !is_null($per_page) ? " ORDER BY $orderby $order " : '';

        // chunks

        //woo commerce orders
        $wc_sql_chunk_count = $wc_exists ? " COUNT( DISTINCT $wc_orders_table.ID ) AS ct_orders, " : '';
        $wc_sql_chunk_join = $wc_exists
            ? " LEFT JOIN $wc_orders_table ON 
                users.ID = $wc_orders_table.customer_id 
                AND $wc_orders_table.status LIKE '%wc_completed%' "
            : '';

        if (!isset($current_page)) {
            $current_page = 1;
        }

        if (is_null($per_page)) { // if null - just count users
            //limit is no limit
            $limit_sql_chunk = '';
            // global selector is count only
            $selectors_sql_chunk = ' COUNT(*) as cnt ';
            //ordering and group chunks empty
            $group_by_chunk = '';
            // drop wc_orders_join on count
            $wc_sql_chunk_join = '';
        } else { // else - get users select with limit, group and order
            //limit
            $limit_sql_chunk =  "LIMIT " . ($current_page - 1) * $per_page . ", " . $per_page;

            // global selector
            $selectors_sql_chunk = ' user_login AS ct_username,
            user_nicename AS ct_name,
            user_email AS ct_email,
            user_registered AS ct_signed_up,
            users.ID AS user_id,
            ' . $wc_sql_chunk_count . '
            ( SELECT meta_table.meta_value 
                    FROM ' . $wp_usermeta_table . ' as meta_table
                        WHERE meta_table.meta_key LIKE \'' . $wp_capabilities . '\'
                        AND meta_table.user_id = users.ID
                        LIMIT 1
                ) AS ct_role,
            ( SELECT COUNT( posts_table.ID ) 
                     FROM ' . $wp_posts_table . ' as posts_table
                        WHERE posts_table.post_author = users.ID
                        AND posts_table.post_type = \'post\'
                        AND posts_table.post_status = \'publish\'
                ) AS ct_posts';

            //ordering chunks
            $group_by_chunk = ' GROUP BY users.ID ';
        }

        $the_final_query = "
            SELECT 
              $selectors_sql_chunk
            FROM 
                $wp_users AS users
            $wc_sql_chunk_join
            LEFT JOIN 
                $wp_usermeta_table ON users.ID = $wp_usermeta_table.user_id
            LEFT JOIN $wp_posts_table ON users.ID = $wp_posts_table.post_author
            WHERE $wp_usermeta_table.meta_key LIKE '%ct_marked_as_spam%'
            $group_by_chunk
            $order_by_chunk
            $limit_sql_chunk;
        ";

        /** The final common SQL query looks LIKE
         * SELECT
         *  user_login AS ct_username,
         *  user_nicename AS ct_name,
         *  user_email AS ct_email,
         *  user_registered AS ct_signed_up,
         *  users.ID AS user_id,
         *  COUNT( DISTINCT wp_wc_orders.ID ) AS ct_orders,
         *  ( SELECT meta_table.meta_value
         *      FROM wp_usermeta as meta_table
         *      WHERE meta_table.meta_key LIKE \'wp_capabilities\'
         *      AND meta_table.user_id = users.ID
         *      LIMIT 1
         *  ) AS ct_role,
         *  ( SELECT COUNT( posts_table.ID )
         *      FROM wp_posts as posts_table
         *      WHERE posts_table.post_author = users.ID
         *      AND posts_table.post_type = \'post\'
         *      AND posts_table.post_status = \'publish\'
         *  ) AS ct_posts
         * FROM
         *  wp_users AS users
         * LEFT JOIN wp_wc_orders ON
         *      users.ID = wp_wc_orders.customer_id
         *      AND wp_wc_orders.status LIKE \'%wc_completed%\'
         * LEFT JOIN wp_usermeta ON
         *      users.ID = wp_usermeta.user_id
         * LEFT JOIN wp_posts ON
         *      users.ID = wp_posts.post_author
         * WHERE wp_usermeta.meta_key LIKE \'%ct_marked_as_spam%\'
         * GROUP BY users.ID
         * ORDER BY ct_posts desc
         * LIMIT 0, 10;
         */

        /**
         * The final count SQL looks like
         * SELECT
         *  COUNT(*) as cnt
         * FROM
         *  wp_users AS users
         * LEFT JOIN wp_usermeta ON
         *      users.ID = wp_usermeta.user_id
         * LEFT JOIN wp_posts ON
         *      users.ID = wp_posts.post_author
         * WHERE wp_usermeta.meta_key LIKE \'%ct_marked_as_spam%\'
         */

        $result = $wpdb->get_results($the_final_query);

        if ( !is_array($result) ) {
            $result = array();
        }

        return $result;
    }

    /**
     * @return \WP_User_Query
     */
    public static function getBad()
    {
        // Without IP and EMAIL
        $params_bad = array(
            'fields'      => 'ID',
            'meta_key'    => 'ct_bad',
            'count_total' => true,
        );

        return new \WP_User_Query($params_bad);
    }

    /**
     * Get the count of bad users.
     *
     * This method retrieves the total number of users marked as 'bad' by querying the user meta data.
     *
     * @return int The total number of bad users.
     */
    public static function getBadUsersCount()
    {
        return self::getBad()->get_total();
    }

    /**
     * @return array
     */
    public function getScansLogs()
    {
        global $wpdb;
        $query = "SELECT * FROM " . APBCT_SPAMSCAN_LOGS . " WHERE scan_type = 'users'";

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * @param array $ids
     *
     * @return void
     */
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
}
