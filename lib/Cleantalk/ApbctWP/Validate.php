<?php

namespace Cleantalk\ApbctWP;

class Validate extends \Cleantalk\Common\Validate
{
    public static function isEmail($variable)
    {
        return is_email($variable);
    }

    public static function isValidFilePath($variable)
    {
        return validate_file($variable);
    }
}
