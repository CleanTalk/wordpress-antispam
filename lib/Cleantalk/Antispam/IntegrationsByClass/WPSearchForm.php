<?php

namespace Cleantalk\Antispam\IntegrationsByClass;

use Cleantalk\ApbctWP\Escape;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;
use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Honeypot;
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
        if ( $apbct->settings['forms__search_test'] ) {
            add_filter('get_search_form', array($this, 'apbctFormSearchAddFields'), 999);
        }
        if ( ! is_admin() && ! apbct_is_ajax() && ! apbct_is_customize_preview() ) {
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

            $resalt = str_replace('</form>', Honeypot::generateHoneypotField('search_form', $form_method) . '</form>', $form_html);
            return $resalt;
        }

        return $form_html;
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

        $user = apbct_is_user_logged_in() ? wp_get_current_user() : null;

        $data = array(
            'message'         => $search,
            'sender_email'    => $user !== null ? $user->user_email : null,
            'sender_nickname' => $user !== null ? $user->user_login : null,
            'post_info'       => array('comment_type' => 'site_search_wordpress'),
            'exception_action' => 0,
        );

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
}
