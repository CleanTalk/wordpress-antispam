<?php

namespace Cleantalk\ApbctWP\Localize;

class LocalizeHandler
{
    public static function handle()
    {
        echo CtPublicFunctionsLocalize::getCode();
        echo CtPublicLocalize::getCode();
    }
}
