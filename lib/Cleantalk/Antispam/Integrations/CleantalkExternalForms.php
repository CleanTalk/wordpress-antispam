<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Escape;
use Cleantalk\ApbctWP\Validate;
use Cleantalk\ApbctWP\Variables\Server;

class CleantalkExternalForms extends IntegrationBase
{
    private $action;
    private $method;

    public function doPrepareActions($argument)
    {
        if ( empty($_POST)
            || !apbct_is_post()
            || Post::getString('cleantalk_hidden_method') === ''
            || Post::getString('cleantalk_hidden_action') === ''
        ) {
            return false;
        }

        // Reject non-http(s) actions early (e.g. javascript:/data:) before any output.
        if ( ! $this->isAllowedExternalFormAction(Post::getString('cleantalk_hidden_action'))
            || ! $this->isAllowedExternalFormMethod(Post::getString('cleantalk_hidden_method'))
        ) {
            return false;
        }

        // The form has to be sent from this site or the action has to be recorded by the plugin
        if ( ! $this->isSameSiteRequest()
            && ! hash_equals(
                self::getActionSign(Post::getString('cleantalk_hidden_action')),
                Post::getString('cleantalk_hidden_sign')
            )
        ) {
            return false;
        }

        return true;
    }

    /**
     * Sign of the external form action recorded by the plugin.
     *
     * @param string $action
     *
     * @return string
     */
    public static function getActionSign($action)
    {
        global $apbct;

        return hash_hmac('sha256', $action, $apbct->api_key . $apbct->data['salt']);
    }

    /**
     * Checks if the request is sent from this site by Origin or Referer headers.
     *
     * @return bool
     */
    private function isSameSiteRequest()
    {
        $site_host = parse_url(get_home_url(), PHP_URL_HOST);
        $source    = Server::getString('HTTP_ORIGIN');
        if ( $source === '' || $source === 'null' ) {
            $source = Server::getString('HTTP_REFERER');
        }
        $source_host = parse_url($source, PHP_URL_HOST);

        return is_string($site_host) && is_string($source_host) && strcasecmp($site_host, $source_host) === 0;
    }

    public function getDataForChecking($argument)
    {
        if ( ! empty($_POST)
            && apbct_is_post()
            && Post::getString('cleantalk_hidden_method') !== ''
            && Post::getString('cleantalk_hidden_action') !== ''
        ) {
            $action = Post::getString('cleantalk_hidden_action');
            $method = Post::getString('cleantalk_hidden_method');

            if ( ! $this->isAllowedExternalFormAction($action)
                || ! $this->isAllowedExternalFormMethod($method)
            ) {
                return null;
            }

            // Keep a sanitized raw URL; escape for HTML only when rendering the form.
            // HTML escaping alone does not block javascript: URLs in action attributes.
            $this->action = Escape::escUrlRaw($action);
            $this->method = strtoupper($method);

            if ( empty($this->action) ) {
                return null;
            }

            unset($_POST['cleantalk_hidden_action'], $_POST['cleantalk_hidden_method'], $_POST['cleantalk_hidden_sign']);

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
            $key = $this->sanitizeExternalFormFieldName((string) $key);
            if ( $key === '' || $key === 'submit' ) {
                continue;
            }

            $field_name = $recursive_key === '' ? $key : $recursive_key . '[' . $key . ']';

            if ( ! is_array($value) ) {
                $return_form .= '<textarea
				name="' . esc_attr($field_name) . '"
				style="display:none;">' . esc_textarea(htmlspecialchars($value))
                    . '</textarea>';
            } else {
                $return_form .= $this->constructFormInnerElements($value, $field_name);
            }
        }

        return $return_form;
    }

    private function constructOriginExternalForm($action, $method)
    {
        if ( empty($action)
            || empty($method)
            || ! $this->isAllowedExternalFormAction($action)
            || ! $this->isAllowedExternalFormMethod($method)
        ) {
            return '';
        }

        // Restrict protocols at output: only http/https are allowed in form action.
        $action = esc_url($action, array('http', 'https'));
        $method = Escape::escHtml(strtoupper($method));

        if ( empty($action) ) {
            return '';
        }

        // Cookiebot chunk
        $bot_attrs = class_exists('Cookiebot_WP') ? array('data-cookieconsent' => 'ignore') : array();

        // HTML form clearing script
        $script = apbct_get_inline_script_tag(
            "let form = document.forms[0];
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
                form.submit();",
            $bot_attrs
        );

        return '
        <html lang="">
            <body>
                <form method="' . $method . '" action="' . $action . '">
                    ' . $this->constructFormInnerElements($_POST, '') . '
                </form>
                    ' . $script . '
            </body>
        </html>';
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function sanitizeExternalFormFieldName($name)
    {
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $name);

        return is_string($sanitized) ? $sanitized : '';
    }

    /**
     * External form action must be an absolute http/https URL.
     * javascript:, data:, vbscript: and other schemes are rejected.
     *
     * @param mixed $action
     *
     * @return bool
     */
    private function isAllowedExternalFormAction($action)
    {
        if ( ! is_string($action) || ! Validate::isUrl($action) ) {
            return false;
        }

        // External form could not be directed to this site
        $action_host = parse_url($action, PHP_URL_HOST);
        $site_host   = parse_url(get_home_url(), PHP_URL_HOST);
        if ( ! is_string($action_host) || (is_string($site_host) && strcasecmp($action_host, $site_host) === 0) ) {
            return false;
        }

        return ! preg_match('/%(?:FORM_ELEMENTS|ACTION|METHOD|SCRIPT)/', $action);
    }

    /**
     * HTML form method may only be GET or POST.
     *
     * @param mixed $method
     *
     * @return bool
     */
    private function isAllowedExternalFormMethod($method)
    {
        return is_string($method)
            && in_array(strtolower($method), array('get', 'post'), true);
    }
}
