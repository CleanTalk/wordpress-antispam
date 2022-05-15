<?php

namespace Cleantalk\ApbctWP\FindSpam\ListTable;

use Cleantalk\Variables\Get;
use Cleantalk\Variables\Post;

class Comments extends \Cleantalk\ApbctWP\CleantalkListTable
{
    protected $apbct;

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'spam',
            'plural'   => 'spam'
        ));

        $this->bulk_actions_handler();

        $this->row_actions_handler();

        $this->prepare_items();

        global $apbct;
        $this->apbct = $apbct;
    }

    /**
     * Set columns
     *
     * @return array
     */
    public function get_columns() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return array(
            'cb'             => '<input type="checkbox" />',
            'ct_author'      => esc_html__('Author', 'cleantalk-spam-protect'),
            'ct_comment'     => esc_html__('Comment', 'cleantalk-spam-protect'),
            'ct_response_to' => esc_html__('In Response To', 'cleantalk-spam-protect'),
        );
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
     * Author (first) column
     *
     * @param $item
     *
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function column_ct_author($item) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $column_content = '';
        $email          = $item['ct_comment']->comment_author_email;
        $ip             = $item['ct_comment']->comment_author_IP;

        // Avatar, nickname
        $column_content .= '<strong>' . $item['ct_comment']->comment_author . '</strong>';
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
        if ( ! empty($ip) ) {
            $column_content .= "<a href='edit-comments.php?s=$ip&mode=detail'>$ip</a>"
                               . (! $this->apbct->white_label
                    ? "<a href='https://cleantalk.org/blacklists/$ip ' target='_blank'>"
                      . "&nbsp;<img src='" . APBCT_URL_PATH . "/inc/images/new_window.gif' alt='Ico: open in new window' border='0' style='float:none' />"
                      . "</a>"
                    : '');
        } else {
            $column_content .= esc_html__('No IP adress', 'cleantalk-spam-protect');
        }

        return $column_content;
    }

    /**
     * @param $item
     *
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function column_ct_comment($item) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $id             = $item['ct_id'];
        $column_content = '';

        $column_content .= '<div class="column-comment">';

        $column_content .= '<div class="submitted-on">';

        $column_content .= sprintf(
            __('Submitted on <a href="%1$s">%2$s at %3$s</a>'),
            get_comment_link($id),
            get_comment_date(__('Y/m/d'), $id),
            get_comment_date(get_option('time_format'), $id)
        );

        $column_content .= '</div>';

        $column_content .= '<p>' . $item['ct_comment']->comment_content . '</p>';

        $column_content .= '</div>';

        $actions = array(
            'approve' => sprintf(
                '<span class="approve"><a href="?page=%s&action=%s&spam=%s">Approve</a></span>',
                htmlspecialchars(addslashes(Get::get('page'))),
                'approve',
                $id
            ),
            'spam'    => sprintf(
                '<span class="spam"><a href="?page=%s&action=%s&spam=%s">Spam</a></span>',
                htmlspecialchars(addslashes(Get::get('page'))),
                'spam',
                $id
            ),
            'trash'   => sprintf('<a href="?page=%s&action=%s&spam=%s">Trash</a>', htmlspecialchars(addslashes(Get::get('page'))), 'trash', $id),
        );

        return sprintf('%1$s %2$s', $column_content, $this->row_actions($actions));
    }

    /**
     * @param $item
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function column_ct_response_to($item) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $post_id = $item['ct_response_to'];
        ?>
        <div>
            <span>
                <a href="/wp-admin/post.php?post=<?php
                echo $post_id; ?>&action=edit"><?php
                    print get_the_title($post_id); ?></a>
                <br/>
                <a href="/wp-admin/edit-comments.php?p=<?php
                echo $post_id; ?>" class="post-com-count">
                    <span class="comment-count"><?php
                        $p_cnt = wp_count_comments($post_id);
                        echo $p_cnt->total_comments;
                    ?></span>
                </a>
            </span>
            <a href="<?php
            print get_permalink($post_id); ?>"><?php
                _e('View Post'); ?></a>
        </div>
        <?php
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
            case 'ct_author':
            case 'ct_comment':
            case 'ct_response_to':
            case 'ct_start':
            case 'ct_checked':
            case 'ct_spam':
            case 'ct_bad':
                return $item[$column_name];
            default:
                return print_r($item, true);
        }
    }

    public function get_bulk_actions() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return array(
            'spam'  => esc_html__('Mark as spam', 'cleantalk-spam-protect'),
            'trash' => esc_html__('Move to trash', 'cleantalk-spam-protect'),
        );
    }

    public function bulk_actions_handler() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( empty(Post::get('spamids')) || empty(Post::get('_wpnonce')) ) {
            return;
        }

        if ( ! $action = $this->current_action() ) {
            return;
        }

        if ( ! wp_verify_nonce(Post::get('_wpnonce'), 'bulk-' . $this->_args['plural']) ) {
            wp_die('nonce error');
        }

        if ( 'trash' === $action ) {
            $this->moveToTrash(Post::get('spamids'));
        }

        if ( 'spam' === $action ) {
            $this->moveToSpam(Post::get('spamids'));
        }
    }

    public function row_actions_handler() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( empty(Get::get('action')) ) {
            return;
        }

        if ( Get::get('action') === 'approve' ) {
            $id = filter_input(INPUT_GET, 'spam', FILTER_SANITIZE_NUMBER_INT);
            $this->approveSpam($id);
        }

        if ( Get::get('action') === 'trash' ) {
            $id = filter_input(INPUT_GET, 'spam', FILTER_SANITIZE_NUMBER_INT);
            $this->moveToTrash(array($id));
        }

        if ( Get::get('action') === 'spam' ) {
            $id = filter_input(INPUT_GET, 'spam', FILTER_SANITIZE_NUMBER_INT);
            $this->moveToSpam(array($id));
        }
    }

    public function no_items() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        esc_html_e('No spam found.', 'cleantalk-spam-protect');
    }

    //********************************************//
    //                 LOGIC                     //
    //*******************************************//

    public function approveSpam($id)
    {
        $comment_meta = delete_comment_meta((int)$id, 'ct_marked_as_spam');

        if ( $comment_meta ) {
            wp_set_comment_status((int)$id, '1');
            apbct_comment__send_feedback((int)$id, 'approve', false, true);
        }
    }

    public function moveToTrash($ids)
    {
        if ( ! empty($ids) ) {
            foreach ( $ids as $id ) {
                delete_comment_meta((int)$id, 'ct_marked_as_spam');
                $comment = get_comment((int)$id);
                wp_trash_comment($comment);
            }
        }
    }

    public function moveToSpam($ids)
    {
        if ( ! empty($ids) ) {
            foreach ( $ids as $id ) {
                delete_comment_meta((int)$id, 'ct_marked_as_spam');
                $comment = get_comment((int)$id);
                wp_spam_comment($comment);
            }
        }
    }

    /**
     * @return int
     * @psalm-suppress PossiblyUnusedMethod, InvalidReturnStatement, InvalidReturnType
     */
    public function getTotal()
    {
        $params_total = array(
            'count'   => true,
        );

        return get_comments($params_total);
    }

    /**
     * @return int
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getChecked()
    {
        return $this->apbct->data['count_checked_comments'];
    }

    /**
     * Spam comments
     *
     * @return \WP_Comment_Query
     */
    public function getSpamNow($per_page, $current_page)
    {
        $params_spam = array(
            'number'   => $per_page,
            'offset'   => ( $current_page - 1 ) * $per_page,
            'meta_key' => 'ct_marked_as_spam',
        );

        return new \WP_Comment_Query($params_spam);
    }

    /**
     * Spam comments
     *
     * @return \WP_Comment_Query
     */
    public function getScannedTotal()
    {
        $params_spam = array(
            'meta_key' => 'ct_marked_as_spam',
        );

        return new \WP_Comment_Query($params_spam);
    }

    /**
     * Without IP and EMAIL
     *
     * @return \WP_Comment_Query
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getBad()
    {
        return $this->apbct->data['count_bad_comments'];
    }

    public function getScansLogs()
    {
        global $wpdb;
        $query = "SELECT * FROM " . APBCT_SPAMSCAN_LOGS . " WHERE scan_type = 'comments'";

        return $wpdb->get_results($query, ARRAY_A);
    }

    protected function removeLogs($ids)
    {
        $ids_string = implode(', ', $ids);
        global $wpdb;

        $wpdb->query(
            "DELETE FROM " . APBCT_SPAMSCAN_LOGS . " WHERE 
                ID IN ($ids_string)"
        );
    }
}
