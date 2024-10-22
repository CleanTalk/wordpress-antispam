<?php

namespace Cleantalk\Common\UniversalBanner;

class BannerDataDto
{
    /**
     * @var string
     */
    public $type = 'universal';

    /**
     * @var string
     */
    public $level = 'info';

    /**
     * @var string
     */
    public $text = '';

    /**
     * @var string
     */
    public $secondary_text = '';

    /**
     * @var string
     */
    public $button_url = '';

    /**
     * @var string
     */
    public $button_text = '';

    /**
     * @var string
     */
    public $additional_text = '';

    /**
     * @var bool
     */
    public $is_dismissible = true;

    /**
     * @var bool
     */
    public $is_show_button = true;
}
