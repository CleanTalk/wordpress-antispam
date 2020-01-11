<?php

require_once(CLEANTALK_PLUGIN_DIR . 'inc/find-spam/ClassCleantalkFindSpamPage.php');
require_once(CLEANTALK_PLUGIN_DIR . 'inc/ClassApbctListTable.php');

// Adding menu items for USERS and COMMENTS spam checking pages
add_action( 'admin_menu', 'ct_add_find_spam_menus' );

function ct_add_find_spam_menus(){

    if( current_user_can( 'activate_plugins' ) ) {
        $ct_check_users =    add_users_page( __( "Check for spam", 'cleantalk' ), __( "Find spam users", 'cleantalk' ),    'read', 'ct_check_users', array( 'ClassCleantalkFindSpamPage', 'showFindSpamPage' ) );
        $ct_check_spam  = add_comments_page( __( "Check for spam", 'cleantalk' ), __( "Find spam comments", 'cleantalk' ), 'read', 'ct_check_spam',  array( 'ClassCleantalkFindSpamPage', 'showFindSpamPage' ) );
    }

    add_action( "load-$ct_check_users", array( 'ClassCleantalkFindSpamPage', 'generate_check_users_page' ) );
    add_action( "load-$ct_check_spam",  array( 'ClassCleantalkFindSpamPage', 'generate_check_spam_page' ) );

    add_filter( 'set-screen-option', function( $status, $option, $value ){
        return ( $option == 'spam_per_page' ) ? (int) $value : $status;
    }, 10, 3 );

}
