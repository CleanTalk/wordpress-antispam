<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Common\UniversalBanner\UniversalBanner;

class ApbctUniversalBanner extends UniversalBanner
{
    /**
     * @param $banner_name
     * @param $user_token
     * @param $api_text
     * @param $api_button_text
     * @param $api_url_template
     * @param $attention_level
     *
     * @throws \Exception
     */
    public function __construct($banner_name, $user_token, $api_text, $api_button_text, $api_url_template, $attention_level)
    {
        parent::__construct($banner_name, $user_token, $api_text, $api_button_text, $api_url_template, $attention_level);
    }

    /**
     * @throws \Exception
     */
    protected function customizeTagReplacements()
    {
        $this->tag_banner_wrapper->setTagAttribute('class', 'notice notice-info');
        $this->tag_banner_wrapper->setTagAttribute('style', '');
    }

    protected function sanitizeBodyOnEcho($body)
    {
        return Escape::escKsesPreset($body, 'apbct_settings__display__groups');
    }
}
