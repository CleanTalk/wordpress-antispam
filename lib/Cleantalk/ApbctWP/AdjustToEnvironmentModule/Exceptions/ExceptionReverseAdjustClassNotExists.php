<?php

namespace Cleantalk\ApbctWP\AdjustToEnvironmentModule\Exceptions;

class ExceptionReverseAdjustClassNotExists extends \Exception
{
    public function __construct($message = 'Reverse adjust class does not exist')
    {
        parent::__construct($message);
    }
}
