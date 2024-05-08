<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Escape;

class CleantalkExternalForms extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( ! empty($_POST)
            && apbct_is_post()
            && Post::get('cleantalk_hidden_method') !== ''
            && Post::get('cleantalk_hidden_action') !== ''
        ) {
            $action = Escape::escHtml(Post::get('cleantalk_hidden_action'));
            $method = Escape::escHtml(Post::get('cleantalk_hidden_method'));
            unset($_POST['cleantalk_hidden_action']);
            unset($_POST['cleantalk_hidden_method']);

            $externalForm = self::getExternalForm($action, $method);
            ct_contact_form_validate();
            print $externalForm;
            die();
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
        wp_send_json_error(
            wp_kses(
                $message,
                array(
                    'a' => array(
                        'href'  => true,
                        'title' => true,
                    ),
                    'br'     => array(),
                    'p'     => array()
                )
            )
        );
    }
}
