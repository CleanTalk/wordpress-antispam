<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Escape;
use Cleantalk\ApbctWP\ExternalForms\Submission;
use Cleantalk\ApbctWP\Validate;

/**
 * Share spam checking and HTML relay output without sharing each flow's security policy.
 */
abstract class ExternalFormsRelayBase extends IntegrationBase
{
    /** @var string */
    protected $action_field;
    /** @var string */
    protected $method_field;

    /** @var string|null Validated destination retained after request metadata is removed. */
    private $action;
    /** @var string|null */
    private $method;

    /**
     * Apply the flow policy before deciding whether this request belongs to the relay.
     *
     * @param mixed $argument
     * @return bool
     */
    public function doPrepareActions($argument)
    {
        if ( ! $this->validateSubmission() ) {
            return false;
        }

        if ( empty(Post::getRawArray())
            || !apbct_is_post()
            || $this->getMethodFromRequest() === ''
            || $this->getActionFromRequest() === ''
        ) {
            return false;
        }

        // Reject non-http(s) actions early (e.g. javascript:/data:) before any output.
        if ( ! $this->isAllowedExternalFormAction($this->getActionFromRequest())
            || ! $this->isAllowedExternalFormMethod($this->getMethodFromRequest())
        ) {
            return false;
        }

        return true;
    }

    /**
     * Retain the checked destination and collect payload fields without relay metadata.
     *
     * Validation is repeated because this method can be called without preparation.
     *
     * @param mixed $argument
     * @return array|null
     */
    public function getDataForChecking($argument)
    {
        if ( ! $this->validateSubmission() ) {
            return null;
        }

        if ( ! empty(Post::getRawArray())
            && apbct_is_post()
            && $this->getMethodFromRequest() !== ''
            && $this->getActionFromRequest() !== ''
        ) {
            $action = $this->getActionFromRequest();
            $method = $this->getMethodFromRequest();

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

            $this->removeServiceFields();

            $input_array = apply_filters('apbct__filter_post', Post::getRawArray());

            return ct_gfa($input_array);
        }

        return null;
    }

    /**
     * Flow-specific policy hook; the default only rejects mixed routing metadata.
     *
     * @return bool
     */
    protected function validateSubmission()
    {
        return Submission::validate();
    }

    /**
     * Read the legacy destination; signed flows may override how bytes are obtained.
     *
     * @return string
     */
    protected function getActionFromRequest()
    {
        return Post::getString($this->action_field);
    }

    /**
     * Read the submitted method before the GET/POST eligibility check.
     *
     * @return string
     */
    protected function getMethodFromRequest()
    {
        return Post::getString($this->method_field);
    }

    /**
     * Keep relay metadata out of the spam payload and the external submission.
     *
     * @return void
     */
    protected function removeServiceFields()
    {
        unset($_POST[$this->action_field], $_POST[$this->method_field]);
    }

    /**
     * Build the browser relay document for non-AJAX submissions.
     *
     * @param string|null $action
     * @param string|null $method
     * @return string|null
     */
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

    /**
     * Emit the relay document after the spam check allows the submission.
     *
     * @param mixed $argument
     * @return void
     */
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
                    ' . $this->constructFormInnerElements(Post::getRawArray(), '') . '
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
    protected function isAllowedExternalFormAction($action)
    {
        if ( ! is_string($action) || ! Validate::isUrl($action) ) {
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
    protected function isAllowedExternalFormMethod($method)
    {
        return is_string($method)
            && in_array(strtolower($method), array('get', 'post'), true);
    }

    /**
     * @param string $code
     * @return string
     */
    protected function getValidationFailReason(string $code)
    {
        $msg_template = __(
            'External form verification failed. Reason code: %s. Please reload the form and try again.',
            'cleantalk-spam-protect'
        );
        return sprintf($msg_template, $code);
    }
}
