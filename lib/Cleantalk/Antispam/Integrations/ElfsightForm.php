<?php

namespace Cleantalk\Antispam\Integrations;

/**
 * Elfsight Form Builder widget - FetchProxy integration.
 * Payload formats:
 * - {fields: [{type, id, value?, name, ...}, ...], type: "form-builder"}
 * - [{id, name, value, type}, ...]
 */
class ElfsightForm extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        $payload = '';
        if (isset($argument['elfsight_payload']) && is_string($argument['elfsight_payload'])) {
            $payload = stripslashes($argument['elfsight_payload']);
        } elseif (isset($_POST['elfsight_payload']) && is_string($_POST['elfsight_payload'])) {
            $payload = stripslashes($_POST['elfsight_payload']);
        }

        $decoded = json_decode($payload);
        $fields = [];
        if (is_array($decoded)) {
            $fields = $decoded;
        } elseif (is_object($decoded) && isset($decoded->fields) && is_array($decoded->fields)) {
            $fields = $decoded->fields;
        }
        $result = [];

        foreach ($fields as $field) {
            if (!is_object($field) || !isset($field->name)) {
                continue;
            }
            $value = isset($field->value) ? (is_string($field->value) ? trim($field->value) : $field->value) : '';
            $name = mb_strtolower(trim($field->name));

            if (strpos($name, 'first') !== false && strpos($name, 'name') !== false) {
                $result['nickname'] = $value;
            } elseif (strpos($name, 'last') !== false && strpos($name, 'name') !== false && empty($result['nickname'])) {
                $result['nickname'] = $value;
            } elseif (strpos($name, 'last') !== false && strpos($name, 'name') !== false && !empty($result['nickname'])) {
                $result['nickname'] = $result['nickname'] . ' ' . $value;
            } elseif (strpos($name, 'email') !== false) {
                $result['email'] = $value;
            } elseif (strpos($name, 'message') !== false) {
                $result['message'] = $value;
            }
        }

        if (count($result) > 0) {
            return ct_gfa_dto(
                apply_filters('apbct__filter_post', $result),
                isset($result['email']) ? $result['email'] : '',
                isset($result['nickname']) ? $result['nickname'] : ''
            )->getArray();
        }

        return $argument;
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $message,
                        'stop_script' => apbct__stop_script_after_ajax_checking(),
                        'integration' => 'ElfsightForm',
                    )
                )
            )
        );
    }
}
