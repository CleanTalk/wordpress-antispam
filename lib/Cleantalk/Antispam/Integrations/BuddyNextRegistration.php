<?php

namespace Cleantalk\Antispam\Integrations;

class BuddyNextRegistration extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        if (
            ! apbct_is_plugin_active('buddynext/buddynext.php') ||
            ! isset($argument['email'])
        ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '(buddynext registration integration):' . __LINE__, $_POST);
            return null;
        }

        $event_token = $argument['ct_bot_detector_event_token'] ?? null;
        $user_name = $argument['name'] ?? '';
        $email = $argument['email'];

        $dto = ct_gfa_dto($argument, $email, $user_name);
        $dto->register = true;
        $data = $dto->getArray();
        $data['event_token'] = $event_token;

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        //{
        //    "code": "rest_registration_failed",
        //    "message": "Por favor, corrige los errores a continuaci\u00f3n.",
        //    "data": {
        //        "status": 422,
        //        "fields": {
        //            "email": "Ya existe una cuenta con esta direcci\u00f3n de correo electr\u00f3nico.",
        //            "user_login": "Este nombre de usuario ya est\u00e1 en uso."
        //        }
        //    }
        //}
        wp_send_json(
            [
                'code' => 'rest_registration_failed',
                'message' => $message,
                'data' => [
                    'status' => 422,
                    'fields' => [
                        'email' => $message,
                    ],
                ],
            ]
        );
    }
}
