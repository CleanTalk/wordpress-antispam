<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Common\UniversalBanner\UniversalBanner;

class ApbctUniversalBanner extends UniversalBanner
{
    protected $banner_type = '';

    public function __construct($banner_data)
    {
        if (isset($banner_data->type)) {
            $this->banner_type = $banner_data->type;
        }
        parent::__construct($banner_data);
    }

    protected function sanitizeBodyOnEcho($body)
    {
        if ($this->banner_type === 'server_requirements') {
            return $body;
        }

        return Escape::escKsesPreset($body, 'apbct_settings__display__banner_template');
    }

    protected function showBannerButton()
    {
        if (apbct_is_in_uri('options-general.php?page=cleantalk')) {
            return false;
        }
        return true;
    }
}
