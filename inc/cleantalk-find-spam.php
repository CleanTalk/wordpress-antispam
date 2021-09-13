<?php

// Adding menu items for USERS and COMMENTS spam checking pages
add_action('admin_menu', 'ct_add_find_spam_pages');
function ct_add_find_spam_pages()
{
    // Check users pages
    $ct_check_users      = add_users_page(
        __("Check for spam", 'cleantalk-spam-protect'),
        __("Find spam users", 'cleantalk-spam-protect'),
        'activate_plugins',
        'ct_check_users',
        array('\Cleantalk\ApbctWP\FindSpam\Page', 'showFindSpamPage')
    );
    $ct_check_users_logs = add_users_page(
        __("Scan logs", 'cleantalk-spam-protect'),
        '',
        'activate_plugins',
        'ct_check_users_logs',
        array('\Cleantalk\ApbctWP\FindSpam\Page', 'showFindSpamPage')
    );
    $ct_bad_users        = add_users_page(
        __("Non-checkable users", 'cleantalk-spam-protect'),
        '',
        'activate_plugins',
        'ct_check_users_bad',
        array('\Cleantalk\ApbctWP\FindSpam\Page', 'showFindSpamPage')
    );

    // Check comments pages
    $ct_check_spam      = add_comments_page(
        __("Check for spam", 'cleantalk-spam-protect'),
        __("Find spam comments", 'cleantalk-spam-protect'),
        'activate_plugins',
        'ct_check_spam',
        array('\Cleantalk\ApbctWP\FindSpam\Page', 'showFindSpamPage')
    );
    $ct_check_spam_logs = add_comments_page(
        __("Scan logs", 'cleantalk-spam-protect'),
        '',
        'activate_plugins',
        'ct_check_spam_logs',
        array('\Cleantalk\ApbctWP\FindSpam\Page', 'showFindSpamPage')
    );

    // Remove some pages from main menu
    remove_submenu_page('users.php', 'ct_check_users_logs');
    remove_submenu_page('users.php', 'ct_check_users_bad');
    remove_submenu_page('edit-comments.php', 'ct_check_spam_logs');

    // Set screen option for every pages
    add_action("load-$ct_check_users", array('\Cleantalk\ApbctWP\FindSpam\Page', 'setScreenOption'));
    add_action("load-$ct_check_users_logs", array('\Cleantalk\ApbctWP\FindSpam\Page', 'setScreenOption'));
    add_action("load-$ct_check_spam", array('\Cleantalk\ApbctWP\FindSpam\Page', 'setScreenOption'));
    add_action("load-$ct_check_spam_logs", array('\Cleantalk\ApbctWP\FindSpam\Page', 'setScreenOption'));
    add_action("load-$ct_bad_users", array('\Cleantalk\ApbctWP\FindSpam\Page', 'setScreenOption'));
}

// Set AJAX actions
add_action('wp_ajax_ajax_clear_users', array('\Cleantalk\ApbctWP\FindSpam\UsersChecker', 'ctAjaxClearUsers'));
add_action('wp_ajax_ajax_check_users', array('\Cleantalk\ApbctWP\FindSpam\UsersChecker', 'ctAjaxCheckUsers'));
add_action('wp_ajax_ajax_info_users', array('\Cleantalk\ApbctWP\FindSpam\UsersChecker', 'ctAjaxInfo'));
add_action('wp_ajax_ajax_ct_get_csv_file', array('\Cleantalk\ApbctWP\FindSpam\UsersChecker', 'ctGetCsvFile'));
add_action('wp_ajax_ajax_delete_all_users', array('\Cleantalk\ApbctWP\FindSpam\UsersChecker', 'ctAjaxDeleteAllUsers'));

add_action('wp_ajax_ajax_clear_comments', array(
    '\Cleantalk\ApbctWP\FindSpam\CommentsChecker',
    'ctAjaxClearComments'
));
add_action('wp_ajax_ajax_check_comments', array(
    '\Cleantalk\ApbctWP\FindSpam\CommentsChecker',
    'ctAjaxCheckComments'
));
add_action('wp_ajax_ajax_info_comments', array('\Cleantalk\ApbctWP\FindSpam\CommentsChecker', 'ctAjaxInfo'));
add_action('wp_ajax_ajax_trash_all', array('\Cleantalk\ApbctWP\FindSpam\CommentsChecker', 'ctAjaxTrashAll'));
add_action('wp_ajax_ajax_spam_all', array('\Cleantalk\ApbctWP\FindSpam\CommentsChecker', 'ctAjaxSpamAll'));

// Debug
add_action('wp_ajax_ajax_insert_users', array('\Cleantalk\ApbctWP\FindSpam\UsersChecker', 'ctAjaxInsertUsers'));

// Hook for saving "per_page" option
add_action('wp_loaded', 'ct_save_screen_option');
function ct_save_screen_option()
{
    // Saving screen option for the pagination (per page option)
    add_filter('set-screen-option', function ($status, $option, $value) {
        return ($option === 'spam_per_page') ? (int)$value : $status;
    }, 10, 3);
}
