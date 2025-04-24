<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Honeypot;
use DOMDocument;

class WPSearchForm extends IntegrationBase
{
    public function getDataForChecking($form_html)
    {
        global $apbct;

        if (  $apbct->settings['forms__search_test'] == 1 && !empty($form_html) && !empty($_GET) && is_string($form_html)) {
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
            $result_html = str_replace('</form>', Honeypot::generateHoneypotField('search_form', $form_method) . '</form>', $form_html);

            return $result_html;
        }

        return null;
    }

    public function doBlock($message)
    {
        die($message);
    }
}
