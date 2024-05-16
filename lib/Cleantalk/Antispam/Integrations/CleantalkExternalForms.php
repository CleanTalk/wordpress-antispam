<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Escape;

class CleantalkExternalForms extends IntegrationBase
{
    private $action;
    private $method;

    public function doPrepareActions($argument)
    {
        if ( empty($_POST)
            || !apbct_is_post()
            || Post::get('cleantalk_hidden_method') === ''
            || Post::get('cleantalk_hidden_action') === ''
        ) {
            return false;
        }

        return true;
    }

    public function getDataForChecking($argument)
    {
        if ( ! empty($_POST)
            && apbct_is_post()
            && Post::get('cleantalk_hidden_method') !== ''
            && Post::get('cleantalk_hidden_action') !== ''
        ) {
            $this->action = Escape::escHtml(Post::get('cleantalk_hidden_action'));
            $this->method = Escape::escHtml(Post::get('cleantalk_hidden_method'));
            unset($_POST['cleantalk_hidden_action'], $_POST['cleantalk_hidden_method']);

            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $_POST);

            return ct_gfa($input_array);
        }

        return null;
    }

    public function getExternalForm($action, $method)
    {
        if ( ! apbct_is_ajax() ) {
            $externalForm = "<html lang=''><body><form method='$method' action='$action'>";
            ct_print_form($_POST, '');
            $externalForm .= "</form></body></html>";
            $externalForm .= "<script " . (class_exists('Cookiebot_WP') ? 'data-cookieconsent="ignore"' : '') . ">
                if(document.forms[0].submit !== 'undefined'){
                    var objects = document.getElementsByName('submit');
                    if(objects.length > 0)
                        document.forms[0].removeChild(objects[0]);
                }
                document.forms[0].submit();
            </script>";
            return $externalForm;
        }

        return null;
    }

    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }

    public function doFinalActions($argument)
    {
        print $this->getExternalForm($this->action, $this->method);
        die();
    }
}
