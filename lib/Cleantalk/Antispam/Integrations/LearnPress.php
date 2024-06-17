<?php

namespace Cleantalk\Antispam\Integrations;

class LearnPress extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( ! empty($_POST) ) {
            return ct_gfa(apply_filters('apbct__filter_post', $_POST));
        }

        return null;
    }

    /**
     * @param $message
     *
     * @throw Exception
     * @psalm-suppress UnusedVariable
     */
    public function doBlock($message)
    {
        throw new \Exception($message);
    }
}
