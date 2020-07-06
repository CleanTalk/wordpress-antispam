<?php

require_once(CLEANTALK_PLUGIN_DIR . 'inc/find-spam/ClassCleantalkFindSpamPage.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/find-spam/ClassCleantalkFindSpamChecker.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/find-spam/ClassCleantalkFindSpamUsersChecker.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/find-spam/ClassCleantalkFindSpamCommentsChecker.php');

// Adding menu items for USERS and COMMENTS spam checking pages
add_action( 'admin_menu', 'ct_add_find_spam_pages' );
function ct_add_find_spam_pages(){

    // Check users pages
    $ct_check_users = add_users_page( __( "Check for spam", 'cleantalk-spam-protect'), __( "Find spam users", 'cleantalk-spam-protect'),    'activate_plugins', 'ct_check_users', array( 'ClassCleantalkFindSpamPage', 'showFindSpamPage' ) );
    $ct_check_users_total = add_users_page( __( "Previous scan results", 'cleantalk-spam-protect'), '', 'activate_plugins', 'ct_check_users_total', array( 'ClassCleantalkFindSpamPage', 'showFindSpamPage' ) );
    $ct_check_users_logs = add_users_page( __( "Scan logs", 'cleantalk-spam-protect'), '', 'activate_plugins', 'ct_check_users_logs', array( 'ClassCleantalkFindSpamPage', 'showFindSpamPage' ) );

    // Cheack comments pages
    $ct_check_spam  = add_comments_page( __( "Check for spam", 'cleantalk-spam-protect'), __( "Find spam comments", 'cleantalk-spam-protect'), 'activate_plugins', 'ct_check_spam',  array( 'ClassCleantalkFindSpamPage', 'showFindSpamPage' ) );
    $ct_check_spam_total  = add_comments_page( __( "Previous scan results", 'cleantalk-spam-protect'), '', 'activate_plugins', 'ct_check_spam_total',  array( 'ClassCleantalkFindSpamPage', 'showFindSpamPage' ) );
    $ct_check_spam_logs  = add_comments_page( __( "Scan logs", 'cleantalk-spam-protect'), '', 'activate_plugins', 'ct_check_spam_logs',  array( 'ClassCleantalkFindSpamPage', 'showFindSpamPage' ) );

    // Remove some pages from main menu
    remove_submenu_page( 'users.php',         'ct_check_users_total' );
    remove_submenu_page( 'users.php',         'ct_check_users_logs' );
    remove_submenu_page( 'edit-comments.php', 'ct_check_spam_total' );
    remove_submenu_page( 'edit-comments.php', 'ct_check_spam_logs' );

    // Set screen option for every pages
    add_action( "load-$ct_check_users",        array( 'ClassCleantalkFindSpamPage', 'setScreenOption' ) );
    add_action( "load-$ct_check_users_total",  array( 'ClassCleantalkFindSpamPage', 'setScreenOption' ) );
    add_action( "load-$ct_check_users_logs",   array( 'ClassCleantalkFindSpamPage', 'setScreenOption' ) );
    add_action( "load-$ct_check_spam",         array( 'ClassCleantalkFindSpamPage', 'setScreenOption' ) );
    add_action( "load-$ct_check_spam_total",   array( 'ClassCleantalkFindSpamPage', 'setScreenOption' ) );
    add_action( "load-$ct_check_spam_logs",    array( 'ClassCleantalkFindSpamPage', 'setScreenOption' ) );

}

// Set AJAX actions
add_action( 'wp_ajax_ajax_clear_users',       array( 'ClassCleantalkFindSpamUsersChecker', 'ct_ajax_clear_users' ) );
add_action( 'wp_ajax_ajax_check_users',       array( 'ClassCleantalkFindSpamUsersChecker', 'ct_ajax_check_users' ) );
add_action( 'wp_ajax_ajax_info_users',        array( 'ClassCleantalkFindSpamUsersChecker', 'ct_ajax_info' ) );
add_action( 'wp_ajax_ajax_ct_get_csv_file',   array( 'ClassCleantalkFindSpamUsersChecker', 'ct_get_csv_file' ) );
add_action( 'wp_ajax_ajax_delete_all_users',  array( 'ClassCleantalkFindSpamUsersChecker', 'ct_ajax_delete_all_users' ) );

add_action( 'wp_ajax_ajax_clear_comments',    array( 'ClassCleantalkFindSpamCommentsChecker', 'ct_ajax_clear_comments' ) );
add_action( 'wp_ajax_ajax_check_comments',    array( 'ClassCleantalkFindSpamCommentsChecker', 'ct_ajax_check_comments' ) );
add_action( 'wp_ajax_ajax_info_comments',     array( 'ClassCleantalkFindSpamCommentsChecker', 'ct_ajax_info' ) );
add_action( 'wp_ajax_ajax_delete_all',        array( 'ClassCleantalkFindSpamCommentsChecker', 'ct_ajax_delete_all' ) );

// Debug
add_action( 'wp_ajax_ajax_insert_users',  array( 'ClassCleantalkFindSpamUsersChecker', 'ct_ajax_insert_users' ) );

// Hook for saving "per_page" option
add_action( 'wp_loaded', 'ct_save_screen_option' );
function ct_save_screen_option() {

    // Saving screen option for the pagination (per page option)
    add_filter( 'set-screen-option', function( $status, $option, $value ){
        return ( $option == 'spam_per_page' ) ? (int) $value : $status;
    }, 10, 3 );

}

// Add checked icons into users table
add_filter( 'manage_users_columns', array( 'ClassCleantalkFindSpamUsersChecker', 'ct_manage_users_columns' ), 10, 1 );
add_filter( 'manage_users_custom_column', array( 'ClassCleantalkFindSpamUsersChecker', 'ct_manage_users_custom_column' ), 10, 3 );