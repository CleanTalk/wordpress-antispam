<?php

namespace Cleantalk\ApbctWP;

class CleantalkRealPerson
{
    public function __construct()
    {
        add_filter('get_comment_author', [$this, 'getCommentAuthor'], 10, 3);
        add_filter('wp_list_comments_args', [$this, 'wpListCommentsArgs'], 10, 3);
    }

    public function getCommentAuthor($comment_author, $comment_id, $comment)
    {
        global $ct_comment_ids;

        if (is_admin()) {
            return $comment_author;
        }

        if ($comment->comment_type !== 'comment') {
            return $comment_author;
        }

        if (!$ct_comment_ids) {
            $ct_comment_ids = [];
        }

        if (in_array($comment_id, $ct_comment_ids)) {
            return $comment_author;
        }

        $ct_hash = get_comment_meta((int)$comment_id, 'ct_real_user_badge_hash', true);
        if (!$ct_hash && $comment->user_id == 0) {
            return $comment_author;
        }

        if (!$ct_hash && apbct_is_user_enable($comment->user_id)) {
            return $comment_author;
        }

        $author = $comment_author;
        $comment_author = '<div class="apbct-real-user-wrapper"><span>' . $comment_author . '</span>';
        $title = __('The Real Person (TRP)', 'cleantalk-spam-protect');

        $text = __(' acts as a real person and passed all tests against spambots. Anti-Spam by CleanTalk.', 'cleantalk-spam-protect');
        $author = '<span class="apbct-real-user-title">' . $author . $text . '</span>';
        $popup = '<div class="apbct-real-user-popup">' . $author . '</div>';

        $comment_author .= '<div class="apbct-real-user" title="' . $title . '">' . $popup . '</div>';

        $comment_author .= '</div>';

        $ct_comment_ids[] = $comment_id;

        return $comment_author;
    }

    public function wpListCommentsArgs($options)
    {
        if (is_admin()) {
            return $options;
        }

        $options['end-callback'] = function ($curr_comment) {
            if ($curr_comment->comment_type !== 'review') {
                return;
            }

            $id = $curr_comment->comment_ID;

            $ct_hash = get_comment_meta((int)$id, 'ct_real_user_badge_hash', true);

            if (!$ct_hash && $curr_comment->user_id == 0) {
                return;
            }

            if (!$ct_hash && apbct_is_user_enable($curr_comment->user_id)) {
                return;
            }

            $author = $curr_comment->comment_author;
            $title = __('The Real Person (TRP)', 'cleantalk-spam-protect');
            $text = __(' acts as a real person and passed all tests against spambots. Anti-Spam by CleanTalk.', 'cleantalk-spam-protect');

            echo "<script>apbctRealUserBadgeWoocommerce($id, '$author', '$title', '$text');</script>";
        };

        return $options;
    }
}
