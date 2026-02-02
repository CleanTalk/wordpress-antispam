<?php

namespace Cleantalk\Antispam\Integrations;

abstract class IntegrationBase
{
    public $base_call_result;
    public $visible_fields_data;

    /**
     * Legacy old way to collect data.
     * @param $argument
     * @return mixed
     */
    abstract public function getDataForChecking($argument);

    /**
     * How to handle CleanTalk forbidden result.
     * @param $message
     * @return mixed|void
     */
    abstract public function doBlock($message);

    /**
     * Prepare actions
     * @param $argument
     * @return mixed|bool
     */
    public function doPrepareActions($argument)
    {
        return true;
    }

    /**
     * Collect base call data.
     * @return array
     */
    public function collectBaseCallData()
    {
        return array();
    }

    /**
     * Actions before base call run.
     * @param $argument
     * @return void
     */
    public function doActionsBeforeBaseCall($argument)
    {
    }

    /**
     * Actions before allow/deny.
     * @param $argument
     * @return void
     */
    public function doActionsBeforeAllowDeny($argument)
    {
    }

    /**
     * Do all the action needs to be performed independent of CleanTalk result
     * @param $argument
     * @return mixed
     */
    public function doFinalActions($argument)
    {
        return $argument;
    }

    public function getVisibleFieldsData()
    {
        return $this->visible_fields_data;
    }

    public function setVisibleFieldsData($visible_fields_data)
    {
        if (!empty($visible_fields_data['visible_fields'])) {
            $this->visible_fields_data = $visible_fields_data;
        }
    }
}
