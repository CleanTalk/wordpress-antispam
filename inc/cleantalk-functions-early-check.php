<?php

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'wpmlsubscribe') {
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-ajax.php');
    ct_ajax_hook();
}

// Iphorm
if (
    Post::get('iphorm_ajax') !== '' &&
    Post::get('iphorm_id') !== '' &&
    Post::get('iphorm_uid') !== ''
) {
    require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-ajax.php');
    ct_ajax_hook();
}

// Facebook
if ( $apbct->settings['forms__general_contact_forms_test'] == 1
     && ( Post::get('action') === 'fb_intialize')
     && ! empty(Post::get('FB_userdata'))
) {
    if ( apbct_is_user_enable() ) {
        ct_registration_errors(null);
    }
}
