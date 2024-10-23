<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Common\UniversalBanner\UniversalBanner;

class ApbctUniversalBanner extends UniversalBanner
{
    protected function sanitizeBodyOnEcho($body)
    {
        return Escape::escKsesPreset($body, 'apbct_settings__display__banner_template');
    }
}
