<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;

class SupsysticNoAjax extends Supsystic
{
    /**
     * The form sent without ajax is processed by the plugin on init in any context.
     *
     * @param $argument
     *
     * @return bool
     */
    public function doPrepareActions($argument)
    {
        if ( ! apbct_is_plugin_active('contact-form-by-supsystic/cfs.php') ) {
            return false;
        }

        $mod = self::getVar('mod');
        if ( $mod === '' ) {
            $mod = self::getVar('page');
        }

        return self::getVar('pl') === 'cfs' &&
            $mod === 'forms' &&
            self::getVar('action') === 'contact' &&
            self::getVar('reqType') !== 'ajax';
    }

    /**
     * @param $message
     *
     * @return void
     * @psalm-suppress UnusedVariable
     */
    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }

    /**
     * Read the request var: GET first, then POST.
     *
     * @param string $name
     *
     * @return string
     */
    private static function getVar($name)
    {
        return isset($_GET[$name]) ? Get::getString($name) : Post::getString($name);
    }
}
