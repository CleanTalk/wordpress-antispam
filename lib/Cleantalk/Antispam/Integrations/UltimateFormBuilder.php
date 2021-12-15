<?php

namespace Cleantalk\Antispam\Integrations;

class UltimateFormBuilder extends IntegrationBase
{

    public function getDataForChecking($argument)
    {
        $form_data = array();
        foreach ( $_POST[ 'form_data' ] as $val ) {
            if ( strpos($val[ 'name' ], '[]') !== false ) {
                $form_data_name = str_replace('[]', '', $val[ 'name' ]);
                if ( ! isset($form_data[ $form_data_name ]) ) {
                    $form_data[$form_data_name] = array();
                }
                $form_data[$form_data_name][] = $val['value'];
            } else {
                $form_data[$val['name']] = $val['value'];
            }
        }

        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $form_data);

        return ct_gfa($input_array);
    }

    public function doBlock($message)
    {
        echo json_encode(
            array(
                'response_message' => $message,
                'form_hide' => 0,
                'error_flag' => 1
            )
        );
        die();
    }
}
