<?php

namespace Cleantalk\Common\Enqueue;

/**
 * @psalm-suppress InvalidClass
 */
class EnqueueDataDTO extends \Cleantalk\Templates\DTO
{
    /**
     * @var string
     */
    public $web_path = '';
    /**
     * @var string
     */
    public $handle = '';
    /**
     * @var string
     */
    public $version = '';
    /**
     * @var array
     */
    public $deps = array();
    /**
     * @var bool|array
     */
    public $args = array();
    /**
     * @var string
     */
    public $media = '';

    protected $obligatory_properties = array(
        'web_path',
        'handle',
        'version',
        'deps',
        'args',
        'media'
    );

    /**
     * @param $params
     * @throws \Exception
     */
    public function __construct($params = array())
    {
        parent::__construct($params);
        // trick for args
        $this->args = isset($params['args']) ? $params['args'] : false;
    }
}
