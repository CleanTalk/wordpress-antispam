<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;

class PaidMembershipPro extends IntegrationBase
{
    private $is_spammer;

    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        /**
         * Login form exclusion - skip PMPro's own login form
         */
        if (Post::getString('pmpro_login_form_used') == 1) {
            return null;
        }

        /**
         * Exclude default WordPress login (wp-login.php) - PMPro hooks into wp_authenticate
         * but we only want to check PMPro registration/membership forms, not standard WP login
         */
        if (Server::hasString('REQUEST_URI', 'wp-login.php')) {
            return null;
        }

        $this->is_spammer = $argument;

        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        $output = ct_gfa($input_array);
        $output['register'] = true;

        if ( Post::get('ct_bot_detector_event_token') ) {
            $output['event_token'] = Post::get('ct_bot_detector_event_token');
        }

        return $output;
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        return $message;
    }

    public function allow()
    {
        return $this->is_spammer;
    }
}
