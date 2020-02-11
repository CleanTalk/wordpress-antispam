<?php


namespace Cleantalk\Antispam\Integrations;


abstract class IntegrationBase
{
    abstract function getDataForChecking();
    abstract function doBlock( $message );
}