<?php

namespace Cleantalk\Antispam\IntegrationsByClass;

abstract class IntegrationByClassBase
{
    /**
     * Do not apply actions on hooks if true;
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function isSkipIntegration()
    {
        return false;
    }

    /**
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function doAdminWork()
    {
        return;
    }

    /**
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function doAjaxWork()
    {
        return;
    }

    /**
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    abstract public function doPublicWork();
}
