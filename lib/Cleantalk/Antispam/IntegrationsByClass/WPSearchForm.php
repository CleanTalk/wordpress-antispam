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
}
