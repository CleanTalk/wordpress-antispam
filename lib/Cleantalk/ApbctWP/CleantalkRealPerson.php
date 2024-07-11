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

        $template_file_path = APBCT_DIR_PATH . 'templates/real-user-badge/real-user-badge-native-comments.html';
        if (file_exists($template_file_path) && is_readable($template_file_path)) {
            $template = @file_get_contents($template_file_path);
        }

        if (empty($template)) {
            return $comment_author;
        }

        $trp_popup_header = __('The Real Person!', 'cleantalk-spam-protect');
        $trp_author = $comment_author;
        $trp_author_bold = '<b>' . $comment_author . '</b>';
        $trp_popup_text = __('Author %s acts as a real person and passed all tests against spambots. Anti-Spam by CleanTalk.', 'cleantalk-spam-protect');
        $trp_popup_text = sprintf($trp_popup_text, $trp_author_bold);
        $trp_comment_id = 'apbct_trp_comment_id_' . $comment_id;
        $trp_title_popup_open_script = "
            let popup = document.getElementById('$trp_comment_id');
            popup.style.display = 'inline-flex';
        ";

        $template = str_replace('{{TRP_POPUP_OPEN_SCRIPT}}', $trp_title_popup_open_script, $template);
        $template = str_replace('{{TRP_POPUP_COMMENT_ID}}', $trp_comment_id, $template);
        $template = str_replace('{{TRP_POPUP_HEADER}}', $trp_popup_header, $template);
        $template = str_replace('{{TRP_POPUP_TEXT}}', $trp_popup_text, $template);
        $template = str_replace('{{TRP_COMMENT_AUTHOR_NAME}}', $trp_author, $template);

        $ct_comment_ids[] = $comment_id;

        return $template;
    }

    public function wpListCommentsArgs($options)
    {
        if (is_admin()) {
            return $options;
        }

        $options['end-callback'] = function ($curr_comment) {
            if ( $curr_comment->comment_type !== 'review' ) {
                return;
            }

            $id = $curr_comment->comment_ID;

            $ct_hash = get_comment_meta((int)$id, 'ct_real_user_badge_hash', true);

            if ( !$ct_hash && $curr_comment->user_id == 0 ) {
                return;
            }

            if ( !$ct_hash && apbct_is_user_enable($curr_comment->user_id) ) {
                return;
            }

            $template_file_path = APBCT_DIR_PATH . 'templates/real-user-badge/real-user-badge-woocommerce.html';
            if (file_exists($template_file_path) && is_readable($template_file_path)) {
                $template = @file_get_contents($template_file_path);
            }

            if (empty($template)) {
                return;
            }

            $trp_popup_header = __('The Real Person!', 'cleantalk-spam-protect');
            $trp_author = $curr_comment->comment_author;
            $trp_author_bold = '<b>' . $curr_comment->comment_author . '</b>';
            $trp_popup_text = __('Author %s acts as a real person and passed all tests against spambots. Anti-Spam by CleanTalk.', 'cleantalk-spam-protect');
            $trp_popup_text = sprintf($trp_popup_text, $trp_author_bold);
            $trp_comment_id = 'apbct_trp_comment_id_' . $curr_comment->comment_ID;
            $trp_title_popup_open_script = "
                let popup = document.getElementById('$trp_comment_id');
                popup.style.display = 'inline-flex';
            ";

            $template = str_replace('{{TRP_POPUP_OPEN_SCRIPT}}', $trp_title_popup_open_script, $template);
            $template = str_replace('{{TRP_POPUP_COMMENT_ID}}', $trp_comment_id, $template);
            $template = str_replace('{{TRP_POPUP_HEADER}}', $trp_popup_header, $template);
            $template = str_replace('{{TRP_POPUP_TEXT}}', $trp_popup_text, $template);
            $template = str_replace('{{TRP_COMMENT_AUTHOR_NAME}}', $trp_author, $template);

            $template = base64_encode($template);

            echo "<script>apbctRealUserBadgeWoocommerce('$template', '$id');</script>";
        };
        return $options;
    }
}
