<?php

namespace Cleantalk\Antispam\Integrations;

class ApbctAmeliaPhpInputStub
{
    public static $data = '';
    public $context;
    private $position = 0;

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->position = 0;
        return true;
    }

    public function stream_read($count)
    {
        $chunk = substr(self::$data, $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }

    public function stream_eof()
    {
        return $this->position >= strlen(self::$data);
    }

    public function stream_stat()
    {
        return array();
    }

    public function url_stat($path, $flags)
    {
        return array();
    }

    public function stream_set_option($option, $arg1, $arg2)
    {
        return true;
    }
}
