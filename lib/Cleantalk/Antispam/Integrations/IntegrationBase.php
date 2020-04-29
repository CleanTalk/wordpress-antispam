<?php


namespace Cleantalk\Antispam\Integrations;


abstract class IntegrationBase
{
    abstract function getDataForChecking( $argument );
    abstract function doBlock( $message );
}