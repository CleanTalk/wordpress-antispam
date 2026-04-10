<?php

namespace Cleantalk\Antispam\Integrations;

class QuForm extends IntegrationBase
{
    /**
     * @param $argument
     *
     * @return array|mixed
     * @psalm-suppress UndefinedFunction
     */
    public function getDataForChecking($argument)
    {
        if (
            !is_object($argument) ||
            !method_exists($argument, 'getValues')
        ) {
            return null;
        }

        $this->custom_comment_type = method_exists($argument, 'hasPages') && $argument->hasPages()
            ? 'quforms_multipage'
            : 'quforms_singlepage';

        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $argument->getValues());

        $gfa = ct_gfa_dto($input_array);
        $gfa_array = $gfa->getArray();

        if (!isset($gfa_array['message'])) {
            $gfa_array['message'] = $argument->getValues();
        }

        return $gfa_array;
    }

    public function doBlock($message)
    {
        die(
            json_encode(
                array('type' => 'error', 'apbct' => array('blocked' => true, 'comment' => $message)),
                JSON_HEX_QUOT | JSON_HEX_TAG
            )
        );
    }
}
