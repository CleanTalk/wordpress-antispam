<?php

namespace Cleantalk\ApbctWP\HTTP;

class HTTPRequestContract
{
    /**
     * @var string
     */
    public $url;
    /**
     * @var string
     */
    public $content = '';
    /**
     * @var bool
     */
    public $success = false;
    /**
     * @var null|string
     */
    public $error_msg = null;
    public function __construct(
        string $url
    ) {
        $this->url = $url;
    }
}
