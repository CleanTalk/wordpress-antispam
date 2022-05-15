<?php

namespace Cleantalk\ApbctWP\FindSpam\ListTable;

class CommentsScan extends Comments
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

        $scanned_comments = $this->getSpamNow($per_page, $current_page);

        $this->set_pagination_args(array(
            'total_items' => count($this->getScannedTotal()->get_comments()),
            'per_page'    => $per_page,
        ));

        foreach ( $scanned_comments->get_comments() as $comment ) {
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
        <div class="alignleft actions bulkactions">
            <button type="button" id="<?php
            echo $button_id_spam; ?>" class="button action ct_spam_all"><?php
                esc_html_e('Mark as spam all comments from the list', 'cleantalk-spam-protect'); ?></button>
            <button type="button" id="<?php
            echo $button_id_trash; ?>" class="button action ct_trash_all"><?php
                esc_html_e('Move to trash all comments from the list', 'cleantalk-spam-protect'); ?></button>
            <span class="spinner"></span>
        </div>
        <?php
    }
}
