<?php

namespace Cleantalk\Antispam\Integrations;

abstract class IntegrationBase
{
    abstract public function getDataForChecking($argument);

    abstract public function doBlock($message);
}
