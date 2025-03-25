<?php

// Adding menu items for USERS and COMMENTS spam checking pages
use Cleantalk\ApbctWP\FindSpam\ListTable\Users;

add_action('admin_menu', 'ct_add_find_spam_pages');
function ct_add_find_spam_pages()
{
    // Check users pages
    $ct_check_users      = add_users_page(
        __("Check for spam", 'cleantalk-spam-protect'),
        __("Find spam users", 'cleantalk-spam-protect'),
        'activate_plugins',
        'ct_check_users',
        [\Cleantalk\ApbctWP\FindSpam\Page::class, 'showFindSpamPage']
    );
    $ct_check_users_logs = add_users_page(
        __("Scan logs", 'cleantalk-spam-protect'),
        '',
        'activate_plugins',
        'ct_check_users_logs',
        [\Cleantalk\ApbctWP\FindSpam\Page::class, 'showFindSpamPage']
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
    remove_submenu_page('edit-comments.php', 'ct_check_spam_logs');
    /**
     * PHP 8.1 fix. The title is null by defaults if page is removed from menu list. So this will call deprecated notice on strip_tags
     */
    global $title;
    if (null === $title) {
        $title = '';
    }

    // Adding settings page

    // Set screen option for every pages
    add_action("load-$ct_check_users", array('\Cleantalk\ApbctWP\FindSpam\Page', 'setScreenOption'));
    add_action("load-$ct_check_users_logs", array('\Cleantalk\ApbctWP\FindSpam\Page', 'setScreenOption'));
    add_action("load-$ct_check_spam", array('\Cleantalk\ApbctWP\FindSpam\Page', 'setScreenOption'));
    add_action("load-$ct_check_spam_logs", array('\Cleantalk\ApbctWP\FindSpam\Page', 'setScreenOption'));

    // Without IP and EMAIL
    if ( Users::getBadUsersCount() > 0 ) {
        $ct_bad_users        = add_users_page(
            __("Non-checkable users", 'cleantalk-spam-protect'),
            '',
            'activate_plugins',
            'ct_check_users_bad',
            array('\Cleantalk\ApbctWP\FindSpam\Page', 'showFindSpamPage')
        );
        remove_submenu_page('users.php', 'ct_check_users_bad');
        add_action("load-$ct_bad_users", array('\Cleantalk\ApbctWP\FindSpam\Page', 'setScreenOption'));
    }
}

// Set AJAX actions
add_action('wp_ajax_ajax_clear_users', [\Cleantalk\ApbctWP\FindSpam\UsersChecker::class, 'ctAjaxClearUsers']);
add_action('wp_ajax_ajax_check_users', [\Cleantalk\ApbctWP\FindSpam\UsersChecker::class, 'ctAjaxCheckUsers']);
add_action('wp_ajax_ajax_info_users', [\Cleantalk\ApbctWP\FindSpam\UsersChecker::class, 'ctAjaxInfo']);
add_action('wp_ajax_ajax_ct_get_csv_file', [\Cleantalk\ApbctWP\FindSpam\UsersChecker::class, 'ctGetCsvFile']);
add_action('wp_ajax_ajax_delete_all_users', [\Cleantalk\ApbctWP\FindSpam\UsersChecker::class, 'ctAjaxDeleteAllUsers']);

add_action('wp_ajax_ajax_clear_comments', [\Cleantalk\ApbctWP\FindSpam\CommentsChecker::class, 'ctAjaxClearComments']);
add_action('wp_ajax_ajax_check_comments', [\Cleantalk\ApbctWP\FindSpam\CommentsChecker::class, 'ctAjaxCheckComments']);
add_action('wp_ajax_ajax_info_comments', [\Cleantalk\ApbctWP\FindSpam\CommentsChecker::class, 'ctAjaxInfo']);
add_action('wp_ajax_ajax_trash_all', [\Cleantalk\ApbctWP\FindSpam\CommentsChecker::class, 'ctAjaxTrashAll']);
add_action('wp_ajax_ajax_spam_all', [\Cleantalk\ApbctWP\FindSpam\CommentsChecker::class, 'ctAjaxSpamAll']);

// Debug
add_action('wp_ajax_ajax_trash_all', [\Cleantalk\ApbctWP\FindSpam\CommentsChecker::class, 'ctAjaxTrashAll']);
add_action('wp_ajax_ajax_spam_all', [\Cleantalk\ApbctWP\FindSpam\CommentsChecker::class, 'ctAjaxSpamAll']);

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
