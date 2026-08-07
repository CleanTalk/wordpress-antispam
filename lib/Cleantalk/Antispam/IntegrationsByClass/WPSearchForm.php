<?php

namespace Cleantalk\Antispam\IntegrationsByClass;

use Cleantalk\ApbctWP\Honeypot;
use Cleantalk\ApbctWP\Variables\AltSessions;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Server;
use DOMDocument;

/**
 * @psalm-suppress UnusedClass
 */
class WPSearchForm extends IntegrationByClassBase
{
    /**
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function doAjaxWork()
    {
    }

    /**
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function doPublicWork()
    {
        global $apbct;
        if ($apbct->settings['forms__search_test']) {
            add_filter('get_search_form', array($this, 'apbctFormSearchAddFields'), 999);
        }
        if ($this->isNativeSearchFormRequest()) {
            // Default search
            add_filter('get_search_query', array($this, 'testSpam'));
            add_action('wp_head', array($this, 'addNoindex'), 1);
        }
    }

    /**
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function doAdminWork()
    {
    }

    /**
     * Prepare data to add honeypot to the WordPress default search form.
     * Fires ct_add_honeypot_field() on hook get_search_form when:
     * - method of the form is post
     * - spam test of search form is enabled
     *
     * @param string $form_html
     * @return string
     */
    public function apbctFormSearchAddFields($form_html)
    {
        global $apbct;

        if ( !empty($form_html) && is_string($form_html) && $apbct->settings['forms__search_test'] == 1 ) {
            // extract method of the form with DOMDocument
            if ( class_exists('DOMDocument') ) {
                libxml_use_internal_errors(true);
                $dom = new DOMDocument();
                if ( @$dom->loadHTML($form_html) ) {
                    $search_form_dom = $dom->getElementById('searchform');
                    if ( !empty($search_form_dom) ) {
                        $method = empty($search_form_dom->getAttribute('method'))
                            //default method is get for any form if no method specified
                            ? 'get'
                            : $search_form_dom->getAttribute('method');
                    }
                }
                libxml_clear_errors();
                unset($dom);
            }

            // retry extract method of the form with regex
            if ( empty($method) ) {
                preg_match('/form.*method="(.*?)"/', $form_html, $matches);
                $method = empty($matches[1])
                    ? 'get'
                    : trim($matches[1]);
            }
            $form_method = strtolower($method);
            $form_sign = sprintf(
                '%s="%s"',
                'apbct-form-sign',
                esc_attr('native_search')
            );

            $result = str_replace('<form', '<form ' . $form_sign, $form_html);
            $result = str_replace('</form>', Honeypot::generateHoneypotField('search_form', $form_method) . '</form>', $result);
            self::setSearchFormDrawn();
            return $result;
        }

        return $form_html;
    }

    /**
     * Marks a protected native WordPress search form as submitted for the given URI.
     *
     * Removes the URI from the alternative-session storage after the search request is
     * received, so the same rendered form state cannot be reused for repeated checks.
     * If no tracked forms remain, stores false because alternative sessions do not
     * support an empty array value.
     *
     * @param string $drawn_for_uri URI path where the protected search form was rendered.
     * @return void
     */
    public static function setSearchFormSent($drawn_for_uri)
    {
        $current = AltSessions::get('search_form_ready');
        $current = is_string($current) ? json_decode($current, true) : $current;
        if (!empty($current) && is_array($current) && isset($current[$drawn_for_uri])) {
            unset($current[$drawn_for_uri]);
        }
        if (empty($current)) {
            // prepare for alt sessions, empty array is restricted :(
            $current = false;
        }
        AltSessions::set('search_form_ready', $current);
    }

    /**
     * Stores the current URI as having a protected native WordPress search form rendered.
     *
     * The stored URI is later used to verify that an incoming native search request
     * came from a page where CleanTalk added protection fields to the search form.
     *
     * @return void
     */
    public static function setSearchFormDrawn()
    {
        $drawn_for_uri = parse_url(Server::getString('REQUEST_URI'), PHP_URL_PATH);
        if (!is_string($drawn_for_uri) || $drawn_for_uri === '') {
            return;
        }

        $current = AltSessions::get('search_form_ready');
        $current = is_string($current) ? json_decode($current, true) : $current;
        $current = is_array($current) ? $current : [];

        if (!isset($current[$drawn_for_uri])) {
            $current[$drawn_for_uri] = 1;
            AltSessions::set('search_form_ready', $current);
        }
    }

    /**
     * Checks whether the current request refers to a previously rendered protected search form.
     *
     * Reads the stored search form URI list from alternative sessions and compares each
     * stored URI with the current HTTP referer. Returns the matched URI path when found,
     * otherwise returns false.
     *
     * @return false|string URI path where the protected form was rendered, or false if no match exists.
     */
    public static function isSearchFormDrawn()
    {
        $current = AltSessions::get('search_form_ready');
        $current = is_string($current) ? json_decode($current, true) : $current;
        if (!empty($current) && is_array($current)) {
            foreach ($current as $drawn_for_uri => $_val) {
                if (is_string($drawn_for_uri) && apbct_is_in_referer($drawn_for_uri)) {
                    return $drawn_for_uri;
                }
            }
        }
        return false;
    }

    /**
     * Test default search string for spam
     *
     * @param string $search
     *
     * @return string
     */
    public function testSpam($search)
    {
        global $apbct, $cleantalk_executed;

        if (
            empty($search) ||
            $cleantalk_executed ||
            $apbct->settings['forms__search_test'] == 0 ||
            ($apbct->settings['data__protect_logged_in'] != 1 && is_user_logged_in()) // Skip processing for logged in users.
        ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
            return $search;
        }

        // do checks only if the form was built via apbct for the visitor on the uri
        $form_is_ready_for_uri = self::isSearchFormDrawn();
        if (false !== $form_is_ready_for_uri) {
            self::setSearchFormSent($form_is_ready_for_uri);
        } else {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '(): native form has not been drawn ' . __LINE__, $_GET);
            return $search;
        }

        $user = apbct_is_user_logged_in() ? wp_get_current_user() : null;

        $data = array(
            'message'         => $search,
            'sender_email'    => $user !== null ? $user->user_email : null,
            'sender_nickname' => $user !== null ? $user->user_login : null,
            'post_info'       => array('comment_type' => 'site_search_wordpress'),
            'exception_action' => 0,
        );

        // Honeypot, same approach as CF7 (see apbct_form__contactForm7__testSpam): when the honeypot field is
        // enabled the search form always renders it, so read its value and set the status directly -
        // empty is clean (1), filled is spam (0). With JS the value travels via alt-sessions (the field is
        // stripped from the GET URL); without JS the field stays in the GET request.
        if ( $apbct->settings['data__honeypot_field'] ) {
            $honeypot_value = Get::getString('apbct__email_id__search_form')
                ?: Cookie::getString('apbct_search_form__honeypot_value')
                ?: AltSessions::get('apbct_search_form__honeypot_value');
            $honeypot_value = (string)$honeypot_value;
            $data['honeypot_field'] = ( $honeypot_value === '' ) ? 1 : 0;
        }

        $base_call_result = apbct_base_call($data);

        if ( isset($base_call_result['ct_result']) ) {
            $ct_result = $base_call_result['ct_result'];

            $cleantalk_executed = true;

            if ( $ct_result->allow == 0 ) {
                die($ct_result->comment);
            }
        }

        return $search;
    }

    /**
     * Add no-index meta to the page of search results
     * @return void
     */
    public function addNoindex()
    {
        global $apbct;

        if (
            ! is_search() || // If it is search results
            $apbct->settings['forms__search_test'] == 0 ||
            ($apbct->settings['data__protect_logged_in'] != 1 && is_user_logged_in()) // Skip processing for logged in users.
        ) {
            return;
        }

        echo '<!-- meta by CleanTalk Anti-Spam Protection plugin -->' . "\n";
        echo '<meta name="robots" content="noindex,nofollow" />' . "\n";
    }

    /**
     * Process signs for the default search request.
     * - not an admin page
     * - not an ajax call
     * - not a preview
     * - has a 's' param in the GET array
     * @return bool
     */
    public function isNativeSearchFormRequest()
    {
        return (
            !is_admin() &&
            !apbct_is_ajax() &&
            !apbct_is_customize_preview() &&
            isset($_GET['s']) // https://app.doboard.com/1/task/47523#comment_305493
        );
    }
}
