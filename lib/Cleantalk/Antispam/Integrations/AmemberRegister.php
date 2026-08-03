<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class AmemberRegister extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( ! self::isRequest() ) {
            return null;
        }

        $form_data = array();
        $form_data['email'] = isset($_POST['email']) && is_string($_POST['email'])
            ? trim($_POST['email'])
            : '';
        $form_data['username'] = isset($_POST['login']) && is_string($_POST['login'])
            ? trim($_POST['login'])
            : '';
        if ( ! empty($_POST['name_f']) && is_string($_POST['name_f']) ) {
            $form_data['first_name'] = trim($_POST['name_f']);
        }
        if ( ! empty($_POST['name_l']) && is_string($_POST['name_l']) ) {
            $form_data['last_name'] = trim($_POST['name_l']);
        }

        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $form_data);

        $output = ct_gfa($input_array);
        $output['register'] = true;

        if ( Post::get('ct_bot_detector_event_token') ) {
            $output['event_token'] = Post::get('ct_bot_detector_event_token');
        }

        return $output;
    }

    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }

    /**
     * aMember signup POST fingerprint (QuickForm2 / signup fields).
     *
     * @return bool
     */
    private static function isRequest()
    {
        if ( ! apbct_is_plugin_active('amember4/amember4.php') ) {
            return false;
        }

        if ( empty($_POST['email']) || empty($_POST['name_f']) || empty($_POST['name_l']) ) {
            return false;
        }

        foreach ( array_keys($_POST) as $key ) {
            if ( is_string($key) && strpos($key, '_qf_') === 0 && strpos($key, '_next') !== false ) {
                return true;
            }
        }

        return isset($_POST['_save_']) || (isset($_POST['pass']) && isset($_POST['_pass']));
    }
}
