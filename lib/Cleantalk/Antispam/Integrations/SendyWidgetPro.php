<?php

namespace Cleantalk\Antispam\Integrations;

class SendyWidgetPro extends IntegrationBase
{
    private $return_argument;

    public function getDataForChecking($argument)
    {
        if (!apbct_is_plugin_active('sendy-widget-pro/sendy-widget-pro.php')) {
            return null;
        }

        $this->return_argument = [];

        $input_array = apply_filters('apbct__filter_post', $_POST);

        if (!empty($_POST['formdata'])) {
            $this->return_argument = $_POST['formdata'];
        }

        return ct_gfa($input_array);
    }

    public function doBlock($message)
    {
        die($message);
    }

    public function allow()
    {
        $_POST['formdata'] = $this->return_argument;

        return true;
    }
}
