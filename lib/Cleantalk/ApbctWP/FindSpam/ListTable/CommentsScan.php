<?php

namespace Cleantalk\ApbctWP\FindSpam\ListTable;

class CommentsScan extends Comments
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

        $scanned_comments = $this->getSpamNow($per_page, $current_page);
        $total_comments = $this->getScannedTotal()->get_comments();
        $this->set_pagination_args(array(
            'total_items' => is_array($total_comments) ? count($total_comments) : 0,
            'per_page'    => $per_page,
        ));

        $get_scanned_comments = $scanned_comments->get_comments();
        $get_scanned_comments = is_array($get_scanned_comments) ? $get_scanned_comments : array();

        foreach ( $get_scanned_comments as $comment ) {
            if ( !($comment instanceof \WP_Comment) ) {
                continue;
            }
            $this->items[] = array(
                'ct_id'          => $comment->comment_ID,
                'ct_author'      => $comment->comment_author,
                'ct_comment'     => $comment,
                'ct_response_to' => $comment->comment_post_ID,
            );
        }
    }

    public function extra_tablenav($which) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( ! $this->has_items() ) {
            return;
        }
        $button_id_spam  = ($which) ? "ct_spam_all_$which" : "ct_spam_all";
        $button_id_trash = ($which) ? "ct_trash_all_$which" : "ct_trash_all";
        ?>
        <div class="alignleft actions bulkactions apbct-table-actions-wrapper">
            <button type="button" id="<?php
            echo $button_id_spam; ?>" class="button action ct_spam_all"><?php
                esc_html_e('Mark as spam all comments from the list', 'cleantalk-spam-protect'); ?></button>
            <button type="button" id="<?php
            echo $button_id_trash; ?>" class="button action ct_trash_all"><?php
                esc_html_e('Move to trash all comments from the list', 'cleantalk-spam-protect'); ?></button>
        </div>
        <span class="spinner" style="float: left"></span>
        <?php
    }
}
