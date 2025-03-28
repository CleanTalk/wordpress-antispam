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
            return $this->constructOriginExternalForm($action, $method);
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

    private function constructFormInnerElements($arr, $recursive_key)
    {
        $return_form = '';
        // Fix for pages04.net forms
        if ( isset($arr['formSourceName']) ) {
            $tmp = array();
            foreach ( $arr as $key => $val ) {
                $tmp_key       = str_replace('_', '+', $key);
                $tmp[$tmp_key] = $val;
            }
            $arr = $tmp;
            unset($tmp, $key, $tmp_key, $val);
        }

        // Fix for zoho forms with space in input attribute name
        if ( isset($arr['actionType'], $arr['returnURL']) ) {
            $tmp = array();
            foreach ( $arr as $key => $val ) {
                $tmp_key       = str_replace('_', ' ', $key);
                $tmp[$tmp_key] = $val;
            }
            $arr = $tmp;
            unset($tmp, $key, $tmp_key, $val);
        }

        foreach ( $arr as $key => $value ) {
            if ($key === 'submit') {
                continue;
            }
            if ( ! is_array($value) ) {
                $return_form .= '<textarea
				name="' . esc_attr($recursive_key === '' ? $key : $recursive_key . '[' . $key . ']') . '"
				style="display:none;">' . esc_textarea(htmlspecialchars($value))
                    . '</textarea>';
            } else {
                $return_form .= $this->constructFormInnerElements($value, $recursive_key === '' ? $key : $recursive_key . '[' . $key . ']');
            }
        }

        return $return_form;
    }

    private function constructOriginExternalForm($action, $method)
    {
        // HTML form template
        $form_template = '
        <html lang="">
            <body>
                <form method="%METHOD" action="%ACTION">
                    %FORM_ELEMENTS
                </form>
                    %SCRIPT
            </body>
        </html>';

        // Cookiebot chunk
        $bot_chunk = class_exists('Cookiebot_WP') ? 'data-cookieconsent="ignore"' : '';

        // HTML form clearing script
        $script = "<script " . $bot_chunk . ">
                let form = document.forms[0];
                let availabilitySubmit = false;
                for (let i = 0; i < form.length; i++) {
                    let typeElem = form[i].getAttribute('type');
                    if (typeElem == 'submit') {
                        availabilitySubmit = true;
                    }
                }

                if(form.submit !== 'undefined' && availabilitySubmit){
                    let objects = form.getElementsByName('submit');
                    if(objects.length > 0) {
                        form.removeChild(objects[0]);
                    }
                }
                form.submit();
                </script>
        ";

        $form_template = str_replace('%METHOD', $method, $form_template);
        $form_template = str_replace('%ACTION', $action, $form_template);
        $form_template = str_replace('%SCRIPT', $script, $form_template);
        $form_template = str_replace('%FORM_ELEMENTS', $this->constructFormInnerElements($_POST, ''), $form_template);

        return $form_template;
    }
}
