<?php

namespace Cleantalk\ApbctWP\Antispam;

use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\RequestParameters\RequestParameters;
use Cleantalk\Variables\Post;
use Cleantalk\Antispam\CleantalkRequest;
use Cleantalk\Antispam\Cleantalk;
use Cleantalk\Templates\Singleton;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\Common\TT;

class ForceProtection
{
    use Singleton;

    /**
     * @return void
     */
    public function __construct()
    {
        global $apbct;

        if ( $apbct->settings['forms__force_protection'] ) {
            $hooks_to_encode = array(
                'the_content',
            );
            foreach ( $hooks_to_encode as $hook ) {
                add_filter($hook, array($this, 'modifyContent'));
            }
        }
    }

    /**
     * Modify content to replace internal, external and iframe forms
     * with encoded forms in data attributes and wrappers with loader.
     *
     * @param string $content
     */
    public function modifyContent($content)
    {
        if (apbct_is_user_logged_in()) {
            return $content;
        }

        $content = $this->checkInternalForms($content);
        $content = $this->checkExternalForms($content);
        $content = $this->checkIframeForms($content);

        return $content;
    }

    /**
     * Check bot by Cleantalk API.
     * Used in REST API and AJAX.
     */
    public function checkBot()
    {
        global $apbct;

        $event_javascript_data = Helper::isJson(TT::toString(Post::get('event_javascript_data')))
            ? Post::get('event_javascript_data')
            : stripslashes(TT::toString(Post::get('event_javascript_data')));

        $params = array(
            'auth_key'              => $apbct->api_key,
            'agent'                 => APBCT_AGENT,
            'event_javascript_data' => $event_javascript_data,
            'sender_ip'             => Helper::ipGet('real', false),
            'event_type'            => 'GENERAL_BOT_CHECK',
            'page_url'              => Post::get('post_url'),
            'sender_info'           => array(
                'site_referrer'         => Post::get('referrer'),
            ),
        );

        $ct_request = new CleantalkRequest($params);
        $ct = new Cleantalk();

        $config             = ct_get_server();
        $ct->server_url     = APBCT_MODERATE_URL;
        $ct->work_url       = isset($config['ct_work_url']) && preg_match('/https:\/\/.+/', $config['ct_work_url']) ? $config['ct_work_url'] : null;
        $ct->server_ttl     = isset($config['ct_server_ttl']) ? $config['ct_server_ttl'] : null;
        $ct->server_changed = isset($config['ct_server_changed']) ? $config['ct_server_changed'] : null;
        $api_response = $ct->checkBot($ct_request);

        // Allow to see forms if error occurred
        if ( ! empty($api_response->errstr)) {
            return json_encode(array(
                'allow' => 1,
                'success' => false,
                'error' => array(
                    'comment' => $api_response->errstr,
                ),
            ));
        }
        RequestParameters::set('apbct_force_protection_check', TT::toString($api_response->allow));

        return json_encode(array(
            'allow' => $api_response->allow,
            'message' => $api_response->comment,
            'success' => true,
        ));
    }

    /**
     * Check iframe forms.
     *
     * @param string $content
     * @return string
     */
    private function checkIframeForms($content)
    {
        $content = preg_replace_callback('#<iframe\b[^>]*>.*?<\/iframe>#is', function ($matches) {

            $iframe = isset($matches[0]) ? $matches[0] : '';

            if ( RequestParameters::get('apbct_force_protection_check') ) {
                return $iframe;
            }

            if (
                preg_match('#<iframe\s+[^>]*src="([^"]*https:\/\/form\.typeform\.com[^"]*)"[^>]*>#i', $iframe) ||
                preg_match('#<iframe\s+[^>]*src="([^"]*https:\/\/forms\.zohopublic\.com[^"]*)"[^>]*>#i', $iframe) ||
                preg_match('#<iframe\s+[^>]*src="([^"]*https:\/\/link\.surepathconnect\.com[^"]*)"[^>]*>#i', $iframe) ||
                preg_match('#hs-form-iframe#i', $iframe) ||
                (
                    preg_match('#<iframe\s+[^>]*src="([^"]*https:\/\/facebook\.com[^"]*)"[^>]*>#i', $iframe) &&
                    preg_match('#<iframe\s+[^>]*src="([^"]*plugins/comments\.php[^"]*)"[^>]*>#i', $iframe)
                )

            ) {
                return $this->generateWrapper($iframe);
            }

            return $iframe;
        }, $content);

        return $content;
    }

    /**
     * Check external forms.
     *
     * @param string $content
     * @return string
     */
    private function checkExternalForms($content)
    {
        $content = preg_replace_callback('#<form\b[^>]*>.*?<\/form>#is', function ($matches) {
            $form = isset($matches[0]) ? $matches[0] : '';

            $form_action = $this->getFormAction($form);
            if (!$form_action ||
                !preg_match('#^https?:\/\/#', $form_action) ||
                parse_url($form_action, PHP_URL_HOST) === parse_url(get_home_url(), PHP_URL_HOST) ||
                $this->isExternalExcludedForm($form)
            ) {
                return $form;
            }

            return $this->generateWrapper($form);
        }, $content);

        return $content;
    }

    /**
     * Check if the form action is external and excluded.
     *
     * @param string $form
     * @return bool
     */
    private function isExternalExcludedForm($form)
    {
        $exclusionsById = [
            'give-form', // give form exclusion because of direct integration
            'frmCalc', // nobletitle-calc
            'ihf-contact-request-form',
            'wpforms', // integration with wpforms
        ];

        $exclusionsByRole = [
            'search', // search forms
        ];

        $exclusionsByClass = [
            'search-form', // search forms
            'hs-form', // integrated hubspot plugin through dynamicRenderedForms logic
            'ihc-form-create-edit', // integrated Ultimate Membership Pro plugin through dynamicRenderedForms logic
            'nf-form-content', // integration with Ninja Forms for js events
            'elementor-form', // integration with elementor-form
            'wpforms', // integration with wpforms
            'et_pb_searchform', // integration with elementor-search-form
        ];

        $exclusionsByAction = [
            'paypal.com/cgi-bin/webscr', // search forms
        ];

        $exclusions = array_merge(
            $exclusionsById,
            $exclusionsByClass,
            $exclusionsByRole,
            $exclusionsByAction
        );

        foreach ($exclusions as $item) {
            if (preg_match('#' . $item . '#', $form)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Replace internal forms with encoded forms in data attributes and wrappers with loader.
     *
     * @param string $content
     * @return string
     */
    private function checkInternalForms($content)
    {
        $content = preg_replace_callback('#<form\b[^>]*>.*?<\/form>#is', function ($matches) {
            $form = isset($matches[0]) ? $matches[0] : '';

            $form_action = $this->getFormAction($form);
            if (!$form_action ||
                !preg_match('#^https?:\/\/#', $form_action) ||
                !preg_match('#' . get_home_url() . '\/.*?\.php#', $form_action) ||
                $this->isInternalExcludedForm($form_action)
            ) {
                return $form;
            }

            return $this->generateWrapper($form);
        }, $content);

        return $content;
    }

    /**
     * Check if the form action is internal and excluded.
     *
     * @param string $form_action
     * @return bool
     */
    private function isInternalExcludedForm($form_action)
    {
        $exclusions = [
            'wp-login.php', // WordPress login page
            'wp-comments-post.php', // WordPress Comments Form
        ];

        foreach ($exclusions as $item) {
            if (preg_match('#' . get_home_url() . '.*' . $item . '#', $form_action)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the form action from the form tag.
     *
     * @param string $form
     * @return string|null
     */
    private function getFormAction($form)
    {
        preg_match('#<form\s+[^>]*action="([^"]+)"[^>]*>#i', $form, $matches);

        return isset($matches[1]) ? $matches[1] : null;
    }

    /**
     * Generate a wrapper for the form.
     *
     * @param string $form
     * @return string
     */
    private function generateWrapper($form)
    {
        $encoded_form = urlencode(base64_encode($form));
        $wrapper = '<div class="ct-encoded-form-wrapper">'
            . '<div class="ct-encoded-form-loader"></div>'
            . '<div class="ct-encoded-form" data-encoded-form="' . $encoded_form . '"></div>'
            . '</div>';

        return $wrapper;
    }
}
