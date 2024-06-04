<?php

add_filter('get_comment_author', function ($comment_author, $comment_id, $comment) {
    if (is_admin()) {
        return $comment_author;
    }

    $ct_hash = get_comment_meta((int)$comment_id, 'ct_hash', true);
    if (!$ct_hash) {
        return $comment_author;
    }

    if ($comment->comment_type !== 'comment') {
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

    return $comment_author;
}, 10, 3);


add_filter('wp_list_comments_args', function ($options) {
    if (is_admin()) {
        return $options;
    }

    $options['end-callback'] = function ($curr_comment) {
        $id    = $curr_comment->comment_ID;

        $ct_hash = get_comment_meta((int)$id, 'ct_hash', true);
        if (!$ct_hash) {
            return;
        }

        if ($curr_comment->comment_type !== 'review') {
            return;
        }

        $author = $curr_comment->comment_author;
        $title = __('The Real Person (TRP)', 'cleantalk-spam-protect');
        $text = __(' acts as a real person and passed all tests against spambots. Anti-Spam by CleanTalk.', 'cleantalk-spam-protect');

        echo "<script>apbctRealUserBadgeWoocommerce($id, '$author', '$title', '$text');</script>";
    };

    return $options;
});
