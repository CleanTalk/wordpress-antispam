<?php

namespace Cleantalk\ApbctWP;

class Transaction extends \Cleantalk\Common\Transaction
{
    /**
     * @inheritDoc
     */
    protected function setOption($option_name, $value)
    {
        return update_option($option_name, $value, false);
    }

    /**
     * @inheritDoc
     */
    protected function getOption($option_name, $default)
    {
        return get_option($option_name, $default);
    }
}
