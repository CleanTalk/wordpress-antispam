<?php

namespace Cleantalk\ApbctWP\FormDecorator\Decorations;

use Cleantalk\ApbctWP\FormDecorator\DecorationSet;

class DecorationSetHolidayDefault extends DecorationSet
{
    public $text;

    public $color;

    protected $css_class_name = 'apbct_form_decoration--default';

    public function __construct()
    {
        $this->localized_name = __('Default Theme', 'cleantalk-spam-protect');
        parent::__construct();
    }

    protected function getHeaderSVG()
    {
        return '';
    }
}
