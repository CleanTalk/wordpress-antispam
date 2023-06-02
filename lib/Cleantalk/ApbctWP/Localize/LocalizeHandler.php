<?php

namespace Cleantalk\ApbctWP\Localize;

class LocalizeHandler
{
    /**
     * Common scripts localization process.
     * @return void
     */
    public static function handle()
    {
        echo CtPublicFunctionsLocalize::getCode();
        echo CtPublicLocalize::getCode();
    }

    /**
     * Localize custom data for apbctLocalStorage object.
     * @param array $data Data needs to add to LS object
     * @return void
     */
    public static function handleCustomData($data)
    {
        echo CtPublicLocalize::getCode($data);
    }
}
