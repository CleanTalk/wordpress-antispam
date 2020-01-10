<?php

require_once(CLEANTALK_PLUGIN_DIR . 'inc/find-spam/ClassCleantalkFindSpamPage.php');

// Adding menu items for USERS and COMMENTS spam checking pages
add_action( 'admin_menu', 'ct_add_find_spam_menus' );

function ct_add_find_spam_menus(){

    if( current_user_can( 'activate_plugins' ) ) {
        add_users_page(    __( "Check for spam", 'cleantalk' ), __( "Find spam users", 'cleantalk' ),    'read', 'ct_check_users', array( 'ClassCleantalkFindSpamPage', 'showFindSpamPage' ) );
        add_comments_page( __( "Check for spam", 'cleantalk' ), __( "Find spam comments", 'cleantalk' ), 'read', 'ct_check_spam',  array( 'ClassCleantalkFindSpamPage', 'showFindSpamPage' ) );
    }

    $apbct_current_page = filter_input( INPUT_GET, 'page' );
    if( 'ct_check_users' == $apbct_current_page || 'ct_check_spam' == $apbct_current_page ) {

        require_once(CLEANTALK_PLUGIN_DIR . 'inc/find-spam/ClassCleantalkFindSpamChecker.php');

        switch ( $apbct_current_page ) {

            case 'ct_check_users' :

                require_once(CLEANTALK_PLUGIN_DIR . 'inc/find-spam/ClassCleantalkFindSpamUsersChecker.php');
                $apbct_spam_checker = new ClassCleantalkFindSpamUsersChecker();
                break;

            case 'ct_check_spam' :

                require_once(CLEANTALK_PLUGIN_DIR . 'inc/find-spam/ClassCleantalkFindSpamCommentsChecker.php');
                $apbct_spam_checker = new ClassCleantalkFindSpamCommentsChecker();
                break;

        }

        // Generate page
        new ClassCleantalkFindSpamPage( $apbct_spam_checker );

    }

}
