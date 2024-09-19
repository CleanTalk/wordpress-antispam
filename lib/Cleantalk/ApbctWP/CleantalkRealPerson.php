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

        if (get_template() === 'twentytwenty') {
            return $comment_author;
        }

        if ($comment->comment_type !== 'comment') {
            return $comment_author;
        }

        if (!$ct_comment_ids) {
            $ct_comment_ids = [];
        }

        if (in_array($comment_id, $ct_comment_ids) && !is_admin()) {
            return $comment_author;
        }

        if (isset($comment->comment_author_email)) {
            $user = get_user_by('email', $comment->comment_author_email);
            if ($user) {
                return $comment_author;
            }
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
        $trp_popup_text_person = __('Author %s acts as a real person and verified as not a bot.', 'cleantalk-spam-protect');
        $trp_popup_text_person = sprintf($trp_popup_text_person, $trp_author_bold);
        $trp_popup_text_shield = __('Passed all tests against spam bots. Anti-Spam by CleanTalk.', 'cleantalk-spam-protect');

        $trp_comment_id = 'apbct_trp_comment_id_' . $comment_id;
        $trp_title_popup_open_script = "apbctRealUserBadgeViewPopup('$trp_comment_id');";
        $trp_title_popup_close_script = "apbctRealUserBadgeClosePopup(event);";
        $trp_link_img_person = APBCT_URL_PATH . '/css/images/real_user.svg';
        $trp_link_img_shield = APBCT_URL_PATH . '/css/images/shield.svg';

        $trp_style_class_admin = '';
        $trp_style_class_admin_img = '';
        $trp_admin_popup_promo_page_text = '';
        if (is_admin()) {
            $trp_style_class_admin = '-admin';
            $trp_style_class_admin_img = '-admin-size';
            $trp_admin_popup_promo_page_text = __('Learn more', 'cleantalk-spam-protect');
        }

        $template = str_replace('{{TRP_POPUP_OPEN_SCRIPT}}', $trp_title_popup_open_script, $template);
        $template = str_replace('{{TRP_POPUP_CLOSE_SCRIPT}}', $trp_title_popup_close_script, $template);
        $template = str_replace('{{TRP_POPUP_COMMENT_ID}}', $trp_comment_id, $template);
        $template = str_replace('{{TRP_POPUP_HEADER}}', $trp_popup_header, $template);
        $template = str_replace('{{TRP_POPUP_TEXT_PERSON}}', $trp_popup_text_person, $template);
        $template = str_replace('{{TRP_POPUP_TEXT_SHIELD}}', $trp_popup_text_shield, $template);
        $template = str_replace('{{TRP_COMMENT_AUTHOR_NAME}}', $trp_author, $template);
        $template = str_replace('{{TRP_LINK_IMG_PERSON}}', $trp_link_img_person, $template);
        $template = str_replace('{{TRP_LINK_IMG_SHIELD}}', $trp_link_img_shield, $template);
        $template = str_replace('{{TRP_STYLE_CLASS_ADMIN}}', $trp_style_class_admin, $template);
        $template = str_replace('{{TRP_STYLE_CLASS_ADMIN_IMG}}', $trp_style_class_admin_img, $template);
        $template = str_replace('{{TRP_ADMIN_PROMO_PAGE_TEXT}}', $trp_admin_popup_promo_page_text, $template);

        $ct_comment_ids[] = $comment_id;

        if (is_admin()) {
            echo $template;
            return '';
        } else {
            return $template;
        }
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

            if (isset($curr_comment->user_id)) {
                $user = get_user_by('id', $curr_comment->user_id);
                if ($user) {
                    return;
                }
            }

            $template_file_path = APBCT_DIR_PATH . 'templates/real-user-badge/real-user-badge-native-comments.html';
            if (file_exists($template_file_path) && is_readable($template_file_path)) {
                $template = @file_get_contents($template_file_path);
            }

            if (empty($template)) {
                return;
            }

            $comment_author = $curr_comment->comment_author;

            $trp_popup_header = __('The Real Person!', 'cleantalk-spam-protect');
            $trp_author_bold = '<b>' . $comment_author . '</b>';
            $trp_popup_text_person = __('Author %s acts as a real person and verified as not a bot.', 'cleantalk-spam-protect');
            $trp_popup_text_person = sprintf($trp_popup_text_person, $trp_author_bold);
            $trp_popup_text_shield = __('Passed all tests against spam bots. Anti-Spam by CleanTalk.', 'cleantalk-spam-protect');

            $trp_comment_id = 'apbct_trp_comment_id_' . $curr_comment->comment_ID;
            $trp_title_popup_open_script = "apbctRealUserBadgeViewPopup('$trp_comment_id');";
            $trp_title_popup_close_script = "apbctRealUserBadgeClosePopup(event);";
            $trp_link_img_person = APBCT_URL_PATH . '/css/images/real_user.svg';
            $trp_link_img_shield = APBCT_URL_PATH . '/css/images/shield.svg';

            $trp_style_class_admin = '';
            $trp_style_class_admin_img = '';
            $trp_admin_popup_promo_page_text = '';
            if (is_admin()) {
                $trp_style_class_admin = '-admin';
                $trp_style_class_admin_img = '-admin-size';
                $trp_admin_popup_promo_page_text = __('Learn more', 'cleantalk-spam-protect');
            }

            $template = str_replace('{{TRP_POPUP_OPEN_SCRIPT}}', $trp_title_popup_open_script, $template);
            $template = str_replace('{{TRP_POPUP_CLOSE_SCRIPT}}', $trp_title_popup_close_script, $template);
            $template = str_replace('{{TRP_POPUP_COMMENT_ID}}', $trp_comment_id, $template);
            $template = str_replace('{{TRP_POPUP_HEADER}}', $trp_popup_header, $template);
            $template = str_replace('{{TRP_POPUP_TEXT_PERSON}}', $trp_popup_text_person, $template);
            $template = str_replace('{{TRP_POPUP_TEXT_SHIELD}}', $trp_popup_text_shield, $template);
            $template = str_replace('{{TRP_COMMENT_AUTHOR_NAME}}', '', $template);
            $template = str_replace('{{TRP_LINK_IMG_PERSON}}', $trp_link_img_person, $template);
            $template = str_replace('{{TRP_LINK_IMG_SHIELD}}', $trp_link_img_shield, $template);
            $template = str_replace('{{TRP_STYLE_CLASS_ADMIN}}', $trp_style_class_admin, $template);
            $template = str_replace('{{TRP_STYLE_CLASS_ADMIN_IMG}}', $trp_style_class_admin_img, $template);
            $template = str_replace('{{TRP_ADMIN_PROMO_PAGE_TEXT}}', $trp_admin_popup_promo_page_text, $template);

            $template = base64_encode($template);

            echo "<script>apbctRealUserBadgeWoocommerce('$template', '$id');</script>";
        };
        return $options;
    }
}
