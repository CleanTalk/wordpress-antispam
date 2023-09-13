<?php

namespace Cleantalk\ApbctWP\Localize;

class LocalizeHandler
{
    public static function handle()
    {
        if (apbct_is_amp_request()) {
            return;
        }
        echo CtPublicFunctionsLocalize::getCode();
        echo CtPublicLocalize::getCode();
    }
}
