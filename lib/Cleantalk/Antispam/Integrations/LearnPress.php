<?php

namespace Cleantalk\Antispam\Integrations;

class LearnPress extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        // The e-mail is passed by the hook
        $email = is_string($argument) ? $argument : '';

        $data = ct_gfa(apply_filters('apbct__filter_post', $_POST), $email);
        $data['register'] = true;

        return $data;
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
