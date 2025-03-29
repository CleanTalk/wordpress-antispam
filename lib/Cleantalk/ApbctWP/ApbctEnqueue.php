<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Common\Enqueue\Enqueue;

class ApbctEnqueue extends Enqueue
{
    protected $plugin_version = APBCT_VERSION;
    protected $assets_path = APBCT_URL_PATH;
    protected $plugin_path = APBCT_DIR_PATH;
}
