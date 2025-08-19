<?php

namespace Cleantalk\Antispam\Integrations;

class WPFunnels extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $input_array = apply_filters('apbct__filter_post', $_POST);
        if ( isset($input_array['postData']) && is_string($input_array['postData']) ) {
            $parsed = [];
            parse_str($input_array['postData'], $parsed);
            if ($input_array instanceof \ArrayAccess) {
                $input_array = (array)$input_array;
            }
            $input_array = array_merge($input_array, $parsed);
            unset($input_array['postData']);
        }

        if ( isset($input_array['ct_bot_detector_event_token']) ) {
            $input_array['event_token'] = $input_array['ct_bot_detector_event_token'];
            unset($input_array['ct_bot_detector_event_token']);
        }

        $data = ct_gfa_dto($input_array)->getArray();

        if ( isset($data['apbct_visible_fields']) ) {
            unset($data['apbct_visible_fields']);
        }

        if (isset($data['message']) && is_array($data['message'])) {
            foreach (['event_token'] as $key) {
                if (isset($data['message'][$key])) {
                    $data[$key] = $data['message'][$key];
                    unset($data['message'][$key]);
                }
            }
        }

        return $data;
    }

    /**
     * @param $message
     *
     * @psalm-suppress UnusedVariable
     */
    public function doBlock($message)
    {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $message,
                        'stop_script' => apbct__stop_script_after_ajax_checking()
                    )
                )
            )
        );
    }
}
